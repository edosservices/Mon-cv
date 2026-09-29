<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\VoucherStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiSession;
use App\Models\WifiZone;
use App\Services\Mikrotik\MikrotikService;
use App\Support\SyncLabel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BusinessReport
{
    public function __construct(private MikrotikService $mikrotik) {}

    public function filters(Request $request): array
    {
        $period = (string) $request->input('period', 'today');
        $allowed = ['today', 'yesterday', '7d', '30d', 'month', 'prev_month', 'custom'];
        if (! in_array($period, $allowed, true)) {
            $period = 'today';
        }

        $grain = (string) $request->input('grain', 'day');
        if (! in_array($grain, ['day', 'week', 'month'], true)) {
            $grain = 'day';
        }

        [$from, $to] = $this->bounds($period, $request);
        $zoneId = null;
        if ($request->filled('zone')) {
            $zoneId = $request->integer('zone');
            abort_unless(WifiZone::query()->whereKey($zoneId)->exists(), 404);
        }

        $planId = $request->filled('plan') ? $request->integer('plan') : null;
        if ($planId && ! Plan::query()->whereKey($planId)->exists()) {
            abort(404);
        }

        $payment = $request->filled('payment') ? (string) $request->input('payment') : null;
        if ($payment && ! in_array($payment, array_column(PaymentStatus::cases(), 'value'), true)) {
            $payment = null;
        }

        $sync = $request->filled('sync') ? (string) $request->input('sync') : null;
        if ($sync && ! in_array($sync, ['synced', 'unsynced', 'failed'], true)) {
            $sync = null;
        }

        return [
            'period' => $period,
            'grain' => $grain,
            'zone_id' => $zoneId,
            'from' => $from,
            'to' => $to,
            'q' => trim((string) $request->input('q', '')),
            'plan_id' => $planId,
            'payment' => $payment,
            'sync' => $sync,
        ];
    }

    public function build(array $filters): array
    {
        $zoneId = $filters['zone_id'];
        $today = [now()->startOfDay(), now()->endOfDay()];
        $yesterday = [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()];

        $revenueToday = $this->netRevenue($today[0], $today[1], $zoneId);
        $revenueYesterday = $this->netRevenue($yesterday[0], $yesterday[1], $zoneId);
        $salesToday = $this->confirmedCount($today[0], $today[1], $zoneId);
        $salesYesterday = $this->confirmedCount($yesterday[0], $yesterday[1], $zoneId);

        $zones = WifiZone::query()->with('mikrotiks:id,wifi_zone_id,name,status')->orderBy('name')->get();
        $routers = $this->routers($zoneId);
        $presence = $this->presence($routers);

        return [
            'filters' => $filters,
            'zones' => $zones,
            'kpis' => [
                $this->kpi('CA aujourd’hui', $revenueToday, $this->compare($revenueToday, $revenueYesterday, $this->hasPayments($yesterday[0], $yesterday[1], $zoneId)), true),
                $this->kpi('Ventes aujourd’hui', $salesToday, $this->compare($salesToday, $salesYesterday, $salesYesterday > 0), false),
                $this->kpi('Tickets actifs', $this->activeTickets($zoneId), null, false),
                $this->kpi('Clients', $this->customerCount($zoneId), null, false),
                $this->kpi('Utilisateurs connectés', $presence['online_users'], null, false),
                $this->kpi('Tickets non synchronisés', $this->unsyncedCount($zoneId), null, false),
            ],
            'series' => $this->series($filters['from'], $filters['to'], $zoneId, $filters['grain']),
            'plans' => $this->plans($filters['from'], $filters['to'], $zoneId),
            'payments' => $this->paymentStates($filters['from'], $filters['to'], $zoneId),
            'hours' => $this->hours($filters['from'], $filters['to'], $zoneId),
            'customers' => $this->customerSummary($filters['from'], $filters['to'], $zoneId),
            'unsynced' => $this->unsynced($zoneId),
            'activity' => $this->activity(),
            'routers' => $presence['routers'],
            'sessions' => $presence['sessions'],
            'zone_rows' => $this->zoneRows($zones, $presence['routers']),
            'period_revenue' => $this->netRevenue($filters['from'], $filters['to'], $zoneId),
        ];
    }

    public function sales(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->salesQuery($filters)
            ->latest('sales.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function salesQuery(array $filters): Builder
    {
        return Sale::query()
            ->with(['customer:id,name,phone', 'wifiZone:id,name', 'payment:id,status,provider,amount,internal_reference', 'items.plan:id,name', 'items.voucher:id,username,sync_status,sync_error'])
            ->whereBetween('sales.created_at', [$filters['from'], $filters['to']])
            ->when($filters['zone_id'], fn (Builder $query) => $query->where('wifi_zone_id', $filters['zone_id']))
            ->when($filters['plan_id'], fn (Builder $query) => $query->whereHas('items', fn (Builder $items) => $items->where('plan_id', $filters['plan_id'])))
            ->when($filters['payment'], fn (Builder $query) => $query->whereHas('payment', fn (Builder $payment) => $payment->where('status', $filters['payment'])))
            ->when($filters['sync'], function (Builder $query) use ($filters) {
                $query->whereHas('items.voucher', function (Builder $voucher) use ($filters) {
                    if ($filters['sync'] === 'synced') {
                        $voucher->where('sync_status', 'synced');
                    } elseif ($filters['sync'] === 'failed') {
                        $voucher->where('sync_status', 'failed');
                    } else {
                        $voucher->where('sync_status', '!=', 'synced');
                    }
                });
            })
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $query->where(function (Builder $inner) use ($term) {
                    $inner->whereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $term)->orWhere('phone', 'like', $term))
                        ->orWhereHas('items.voucher', fn (Builder $voucher) => $voucher->where('username', 'like', $term))
                        ->orWhereHas('payment', fn (Builder $payment) => $payment->where('internal_reference', 'like', $term)->orWhere('transaction_reference', 'like', $term));
                });
            });
    }

    public function exportSales(array $filters): Collection
    {
        return $this->salesQuery($filters)->latest('sales.created_at')->limit(5000)->get();
    }

    public function exportCustomers(array $filters): Collection
    {
        return $this->customerList($filters['zone_id'], 5000);
    }

    public function exportTickets(array $filters): Collection
    {
        return Voucher::query()
            ->with(['plan:id,name', 'customer:id,name,phone', 'wifiZone:id,name'])
            ->when($filters['zone_id'], fn (Builder $query) => $query->where('wifi_zone_id', $filters['zone_id']))
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->latest()
            ->limit(5000)
            ->get();
    }

    public function exportPayments(array $filters): Collection
    {
        return $this->paymentQuery($filters['zone_id'])
            ->whereBetween('payments.created_at', [$filters['from'], $filters['to']])
            ->latest('payments.created_at')
            ->limit(5000)
            ->get(['payments.id', 'payments.amount', 'payments.currency', 'payments.provider', 'payments.status', 'payments.internal_reference', 'payments.created_at', 'payments.paid_at']);
    }

    public function netRevenue(Carbon $from, Carbon $to, ?int $zoneId): float
    {
        $gross = (float) $this->recognized($zoneId)
            ->whereRaw($this->paidExpression().' between ? and ?', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->sum('payments.amount');

        $refunds = (float) $this->paymentQuery($zoneId)
            ->where('payments.status', PaymentStatus::Refunded->value)
            ->whereBetween('payments.updated_at', [$from, $to])
            ->sum('payments.amount');

        return $gross - $refunds;
    }

    private function bounds(string $period, Request $request): array
    {
        $now = now();

        return match ($period) {
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'prev_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom' => $this->customBounds($request),
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private function customBounds(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->subDays(6)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        return [$from, $to];
    }

    private function paymentQuery(?int $zoneId): Builder
    {
        return Payment::query()
            ->where('payments.payable_type', Sale::class)
            ->when($zoneId, function (Builder $query) use ($zoneId) {
                $query->whereIn('payments.payable_id', Sale::query()->where('wifi_zone_id', $zoneId)->select('id'));
            });
    }

    private function recognized(?int $zoneId): Builder
    {
        return $this->paymentQuery($zoneId)->whereIn('payments.status', [
            PaymentStatus::Success->value,
            PaymentStatus::Refunded->value,
        ]);
    }

    private function paidExpression(): string
    {
        return 'coalesce(payments.paid_at, payments.created_at)';
    }

    public function confirmedCount(Carbon $from, Carbon $to, ?int $zoneId): int
    {
        return $this->paymentQuery($zoneId)
            ->where('payments.status', PaymentStatus::Success->value)
            ->whereRaw($this->paidExpression().' between ? and ?', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->count();
    }

    private function hasPayments(Carbon $from, Carbon $to, ?int $zoneId): bool
    {
        return $this->recognized($zoneId)
            ->whereRaw($this->paidExpression().' between ? and ?', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->exists();
    }

    private function compare(float|int $current, float|int $previous, bool $hasPrevious): ?float
    {
        if (! $hasPrevious || (float) $previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function kpi(string $label, float|int $value, ?float $change, bool $money): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'money' => $money,
            'change' => $change,
        ];
    }

    private function activeTickets(?int $zoneId): int
    {
        return Voucher::query()
            ->when($zoneId, fn (Builder $query) => $query->where('wifi_zone_id', $zoneId))
            ->where('status', VoucherStatus::Active->value)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
    }

    private function customerCount(?int $zoneId): int
    {
        if (! $zoneId) {
            return Customer::query()->count();
        }

        return Customer::query()->where(function (Builder $query) use ($zoneId) {
            $query->whereHas('vouchers', fn (Builder $inner) => $inner->where('wifi_zone_id', $zoneId))
                ->orWhereHas('sales', fn (Builder $inner) => $inner->where('wifi_zone_id', $zoneId));
        })->count();
    }

    private function unsyncedCount(?int $zoneId): int
    {
        return Voucher::query()
            ->when($zoneId, fn (Builder $query) => $query->where('wifi_zone_id', $zoneId))
            ->where('sync_status', '!=', 'synced')
            ->count();
    }

    private function series(Carbon $from, Carbon $to, ?int $zoneId, string $grain): array
    {
        $gross = $this->buckets($this->recognized($zoneId), $this->paidExpression(), $from, $to, $grain);
        $refunds = $this->buckets(
            $this->paymentQuery($zoneId)->where('payments.status', PaymentStatus::Refunded->value),
            'payments.updated_at',
            $from,
            $to,
            $grain,
        );

        $keys = array_unique([...array_keys($gross), ...array_keys($refunds)]);
        sort($keys);
        if ($keys === []) {
            return [];
        }

        return array_map(fn (string $key) => [
            'label' => $key,
            'amount' => round(($gross[$key] ?? 0) - ($refunds[$key] ?? 0), 2),
        ], $keys);
    }

    private function buckets(Builder $query, string $column, Carbon $from, Carbon $to, string $grain): array
    {
        $bucket = $this->bucketExpression($column, $grain);
        $rows = (clone $query)
            ->whereRaw($column.' between ? and ?', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->selectRaw($bucket.' as bucket, sum(payments.amount) as total')
            ->groupByRaw($bucket)
            ->pluck('total', 'bucket');

        $out = [];
        foreach ($rows as $bucket => $total) {
            if ($bucket === null || $bucket === '') {
                continue;
            }
            $out[(string) $bucket] = (float) $total;
        }

        return $out;
    }

    private function bucketExpression(string $column, string $grain): string
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        return match ($grain) {
            'week' => $sqlite ? "strftime('%Y-W%W', $column)" : "DATE_FORMAT($column, '%x-W%v')",
            'month' => $sqlite ? "strftime('%Y-%m', $column)" : "DATE_FORMAT($column, '%Y-%m')",
            default => $sqlite ? "date($column)" : "DATE($column)",
        };
    }

    private function hours(Carbon $from, Carbon $to, ?int $zoneId): array
    {
        $column = $this->paidExpression();
        $hour = DB::getDriverName() === 'sqlite'
            ? "cast(strftime('%H', $column) as integer)"
            : "HOUR($column)";

        $rows = $this->paymentQuery($zoneId)
            ->where('payments.status', PaymentStatus::Success->value)
            ->whereRaw($column.' between ? and ?', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->selectRaw($hour.' as hour, count(*) as total')
            ->groupByRaw($hour)
            ->orderByRaw($hour)
            ->get();

        return $rows->map(fn ($row) => [
            'label' => str_pad((string) $row->hour, 2, '0', STR_PAD_LEFT).'h',
            'total' => (int) $row->total,
        ])->all();
    }

    private function paymentStates(Carbon $from, Carbon $to, ?int $zoneId): array
    {
        $counts = $this->paymentQuery($zoneId)
            ->whereBetween('payments.created_at', [$from, $to])
            ->selectRaw('payments.status, count(*) as total, sum(payments.amount) as amount')
            ->groupBy('payments.status')
            ->get()
            ->keyBy('status');

        $states = [];
        foreach (PaymentStatus::cases() as $status) {
            $row = $counts->get($status->value);
            $states[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($row->total ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        }

        return $states;
    }

    private function plans(Carbon $from, Carbon $to, ?int $zoneId): array
    {
        $plans = Plan::query()
            ->when($zoneId, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereNull('wifi_zone_id')->orWhere('wifi_zone_id', $zoneId)))
            ->orderBy('name')
            ->get(['id', 'name', 'duration_seconds', 'price', 'currency']);

        $tenantId = (int) app(\App\Support\TenantManager::class)->id();
        $sold = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('payments', 'payments.id', '=', 'sales.payment_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('payments.status', PaymentStatus::Success->value)
            ->whereRaw($this->paidExpression().' between ? and ?', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->when($zoneId, fn ($query) => $query->where('sales.wifi_zone_id', $zoneId))
            ->groupBy('sale_items.plan_id')
            ->selectRaw('sale_items.plan_id as plan_id, count(*) as sold, sum(sale_items.amount) as revenue')
            ->get()
            ->keyBy('plan_id');

        $stock = Voucher::query()
            ->when($zoneId, fn (Builder $query) => $query->where('wifi_zone_id', $zoneId))
            ->selectRaw('plan_id, sum(case when status = ? and (expires_at is null or expires_at > ?) then 1 else 0 end) as active_count, sum(case when status = ? or (status = ? and expires_at is not null and expires_at <= ?) then 1 else 0 end) as expired_count', [
                VoucherStatus::Active->value, now(),
                VoucherStatus::Expired->value, VoucherStatus::Active->value, now(),
            ])
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        return $plans->map(function (Plan $plan) use ($sold, $stock) {
            return [
                'name' => $plan->name,
                'duration' => $plan->validityLabel(),
                'price' => $plan->price,
                'currency' => $plan->currency,
                'sold' => (int) ($sold[$plan->id]->sold ?? 0),
                'revenue' => (float) ($sold[$plan->id]->revenue ?? 0),
                'active' => (int) ($stock[$plan->id]->active_count ?? 0),
                'expired' => (int) ($stock[$plan->id]->expired_count ?? 0),
            ];
        })->all();
    }

    private function customerSummary(Carbon $from, Carbon $to, ?int $zoneId): array
    {
        $base = Customer::query()
            ->when($zoneId, fn (Builder $query) => $query->where(function (Builder $inner) use ($zoneId) {
                $inner->whereHas('sales', fn (Builder $sales) => $sales->where('wifi_zone_id', $zoneId))
                    ->orWhereHas('vouchers', fn (Builder $vouchers) => $vouchers->where('wifi_zone_id', $zoneId));
            }));

        return [
            'total' => (clone $base)->count(),
            'news' => (clone $base)->whereBetween('customers.created_at', [$from, $to])->count(),
            'active' => (clone $base)->whereHas('vouchers', function (Builder $query) use ($zoneId) {
                $query->where('status', VoucherStatus::Active->value)
                    ->where(fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->when($zoneId, fn (Builder $inner) => $inner->where('wifi_zone_id', $zoneId));
            })->count(),
            'repeat' => (clone $base)->whereHas('sales', function (Builder $query) use ($zoneId) {
                $query->where('status', 'paid')->when($zoneId, fn (Builder $inner) => $inner->where('wifi_zone_id', $zoneId));
            }, '>=', 2)->count(),
            'rows' => $this->customerList($zoneId, 8),
        ];
    }

    private function customerList(?int $zoneId, int $limit): Collection
    {
        $customers = Customer::query()
            ->when($zoneId, fn (Builder $query) => $query->where(function (Builder $inner) use ($zoneId) {
                $inner->whereHas('sales', fn (Builder $sales) => $sales->where('wifi_zone_id', $zoneId))
                    ->orWhereHas('vouchers', fn (Builder $vouchers) => $vouchers->where('wifi_zone_id', $zoneId));
            }))
            ->withCount(['sales as purchases' => fn (Builder $query) => $query->where('status', 'paid')])
            ->withMax(['sales as last_purchase' => fn (Builder $query) => $query->where('status', 'paid')], 'created_at')
            ->with(['vouchers:id,customer_id,username,wifi_zone_id'])
            ->latest()
            ->limit($limit)
            ->get();

        $usernames = $customers->flatMap(fn (Customer $customer) => $customer->vouchers->pluck('username'))->filter()->unique()->values();
        $seen = $usernames->isEmpty()
            ? collect()
            : WifiSession::query()->whereIn('username', $usernames)->selectRaw('username, max(started_at) as seen')->groupBy('username')->pluck('seen', 'username');
        $zoneNames = WifiZone::query()->pluck('name', 'id');

        return $customers->map(function (Customer $customer) use ($seen, $zoneNames) {
            $lastSeen = null;
            foreach ($customer->vouchers as $voucher) {
                $value = $seen[$voucher->username] ?? null;
                if ($value && ($lastSeen === null || $value > $lastSeen)) {
                    $lastSeen = $value;
                }
            }

            return [
                'name' => $customer->name ?: 'Client',
                'phone' => $customer->phone,
                'zone' => $zoneNames[$customer->vouchers->first()?->wifi_zone_id] ?? '—',
                'purchases' => (int) $customer->purchases,
                'last_purchase' => $customer->last_purchase,
                'last_seen' => $lastSeen,
            ];
        });
    }

    private function unsynced(?int $zoneId): Collection
    {
        $vouchers = Voucher::query()
            ->with(['plan:id,name', 'customer:id,name,phone'])
            ->when($zoneId, fn (Builder $query) => $query->where('wifi_zone_id', $zoneId))
            ->where('sync_status', '!=', 'synced')
            ->latest()
            ->limit(8)
            ->get();

        $attempts = $vouchers->isEmpty()
            ? collect()
            : AuditLog::query()
                ->where('resource_type', Voucher::class)
                ->whereIn('resource_id', $vouchers->pluck('id')->map(fn ($id) => (string) $id))
                ->where('action', 'like', 'voucher.sync%')
                ->selectRaw('resource_id, count(*) as attempts')
                ->groupBy('resource_id')
                ->pluck('attempts', 'resource_id');

        return $vouchers->each(function (Voucher $voucher) use ($attempts) {
            $voucher->setAttribute('sync_attempts', (int) ($attempts[(string) $voucher->id] ?? 0));
            $voucher->setAttribute('sync_label', SyncLabel::for($voucher->sync_status));
        });
    }

    private function activity(): Collection
    {
        return AuditLog::query()->latest('created_at')->limit(8)->get()->map(function (AuditLog $log) {
            return [
                'label' => $this->activityLabel($log->action),
                'at' => $log->created_at,
            ];
        });
    }

    private function activityLabel(string $action): string
    {
        return match ($action) {
            'sale.confirmed', 'sale.counter' => 'Ticket vendu',
            'payment.success' => 'Paiement confirmé',
            'payment.failed' => 'Paiement échoué',
            'payment.created' => 'Paiement enregistré',
            'voucher.sync_success' => 'Ticket synchronisé',
            'voucher.sync_failed' => 'Erreur de synchronisation',
            'voucher.activated' => 'Ticket activé',
            'user.disconnected' => 'Client déconnecté',
            default => ucfirst(str_replace(['.', '_'], ' ', $action)),
        };
    }

    private function routers(?int $zoneId): Collection
    {
        return \App\Models\Mikrotik::query()
            ->when($zoneId, fn (Builder $query) => $query->where('wifi_zone_id', $zoneId))
            ->orderBy('name')
            ->get();
    }

    private function presence(Collection $routers): array
    {
        $sessions = [];
        $onlineUsers = 0;
        $cards = [];

        $usernames = [];
        foreach ($routers as $router) {
            $users = [];
            $resource = [];
            if ($router->status === 'online') {
                try {
                    $users = $this->mikrotik->getActiveUsers($router);
                    try {
                        $resource = $this->mikrotik->getSystemResource($router);
                    } catch (RuntimeException) {
                        $resource = [];
                    }
                    $router->forceFill([
                        'last_seen_at' => now(),
                        'last_error' => null,
                        'routeros_version' => $resource['version'] ?? $router->routeros_version,
                    ])->save();
                } catch (RuntimeException $exception) {
                    $users = [];
                    $router->forceFill([
                        'status' => 'offline',
                        'last_error' => $exception->getMessage(),
                    ])->save();
                }
            }

            foreach ($users as $user) {
                if (! empty($user['user'])) {
                    $usernames[] = $user['user'];
                }
            }
            $onlineUsers += count($users);
            $cards[] = ['router' => $router, 'users' => $users, 'resource' => $resource];
        }

        $vouchers = $usernames === []
            ? collect()
            : Voucher::query()->with(['plan:id,name,mikrotik_profile', 'customer:id,name'])->whereIn('username', $usernames)->get()->keyBy('username');

        foreach ($cards as $card) {
            foreach ($card['users'] as $user) {
                $voucher = $vouchers->get($user['user'] ?? '');
                if ($voucher) {
                    $voucher->refreshExpiry();
                }
                $snapshot = is_array($voucher?->profile_snapshot) ? $voucher->profile_snapshot : [];
                $sessions[] = [
                    'client' => $voucher?->customer?->name ?: null,
                    'username' => $user['user'] ?? '—',
                    'ip' => $user['address'] ?? '—',
                    'uptime' => $user['uptime'] ?? '—',
                    'plan' => $voucher?->plan?->name,
                    'profile' => $user['profile'] ?? ($voucher?->plan?->mikrotik_profile ?: $voucher?->plan?->name),
                    'rate' => $user['rate-limit'] ?? ($snapshot['rate_limit'] ?? null),
                    'started' => $voucher?->activated_at,
                    'expires' => $voucher?->expires_at,
                    'remaining' => $voucher?->remainingLabel(),
                    'router' => $card['router']->name,
                    'status' => 'online',
                    'mikrotik_id' => $card['router']->id,
                    'active_id' => $user['.id'] ?? null,
                ];
            }
        }

        return [
            'online_users' => $onlineUsers,
            'routers' => $cards,
            'sessions' => $sessions,
        ];
    }

    private function zoneRows(Collection $zones, array $cards): array
    {
        if ($zones->count() < 2) {
            return [];
        }

        $online = [];
        foreach ($cards as $card) {
            $id = $card['router']->wifi_zone_id;
            $online[$id] = ($online[$id] ?? 0) + count($card['users']);
        }

        $today = [now()->startOfDay(), now()->endOfDay()];
        $rows = [];
        foreach ($zones as $zone) {
            $rows[] = [
                'id' => $zone->id,
                'name' => $zone->name,
                'slug' => $zone->slug,
                'status' => $zone->status,
                'router' => $zone->mikrotiks->first()?->name,
                'router_status' => $zone->mikrotiks->first()?->status,
                'online' => $online[$zone->id] ?? 0,
                'sales' => $this->confirmedCount($today[0], $today[1], $zone->id),
                'revenue' => $this->netRevenue($today[0], $today[1], $zone->id),
            ];
        }

        return $rows;
    }
}
