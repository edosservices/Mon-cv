<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VoucherStatus;
use App\Models\Customer;
use App\Models\Mikrotik;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SpaceOverview
{
    /**
     * @return array{from: Carbon, to: Carbon, preset: string}
     */
    public function range(Request $request): array
    {
        $preset = (string) $request->string('chart', '7');
        $to = now()->endOfDay();
        $from = match ($preset) {
            'today' => now()->startOfDay(),
            '30' => now()->subDays(29)->startOfDay(),
            '90' => now()->subDays(89)->startOfDay(),
            'year' => now()->startOfYear(),
            'custom' => ($request->date('chart_from') ?? now()->subDays(6))->startOfDay(),
            default => now()->subDays(6)->startOfDay(),
        };
        if ($preset === 'custom' && $request->filled('chart_to')) {
            $to = $request->date('chart_to')->endOfDay();
        }
        if (! in_array($preset, ['today', '7', '30', '90', 'year', 'custom'], true)) {
            $preset = '7';
        }
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return ['from' => $from, 'to' => $to, 'preset' => $preset];
    }

    /**
     * @return array<string, mixed>
     */
    public function entrepreneur(Request $request): array
    {
        $range = $this->range($request);
        $zoneId = $request->integer('zone') ?: null;
        $from = $range['from'];
        $to = $range['to'];

        $vouchers = Voucher::query()->when($zoneId, fn ($query) => $query->where('wifi_zone_id', $zoneId));
        $counts = (clone $vouchers)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $sync = (clone $vouchers)
            ->selectRaw('sync_status, count(*) as total')
            ->groupBy('sync_status')
            ->pluck('total', 'sync_status');

        $sales = Sale::query()
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->when($zoneId, fn ($query) => $query->where('wifi_zone_id', $zoneId));

        $revenue = (clone $sales)
            ->selectRaw('currency, date(created_at) as day, sum(total_amount) as amount, count(*) as total')
            ->groupByRaw('currency, date(created_at)')
            ->orderBy('day')
            ->get()
            ->groupBy('currency')
            ->map(fn ($rows, $currency) => [
                'currency' => (string) $currency,
                'label' => Money::shop($rows->sum('amount'), (string) $currency),
                'amount' => (float) $rows->sum('amount'),
                'count' => (int) $rows->sum('total'),
                'days' => $rows->map(fn ($row) => [
                    'day' => (string) $row->day,
                    'amount' => (float) $row->amount,
                    'count' => (int) $row->total,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $profiles = (clone $vouchers)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->orderByDesc('total')
            ->limit(8)
            ->with('plan:id,name,mikrotik_profile')
            ->get()
            ->map(fn (Voucher $voucher) => [
                'name' => $voucher->plan?->mikrotik_profile ?: ($voucher->plan?->name ?: 'Sans profil'),
                'total' => (int) $voucher->total,
            ])
            ->all();

        $payments = Payment::query()
            ->where('status', 'success')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('provider, currency, count(*) as total, sum(amount) as amount')
            ->groupBy('provider', 'currency')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'provider' => (string) ($row->provider ?: 'manual'),
                'currency' => (string) $row->currency,
                'total' => (int) $row->total,
                'label' => Money::shop($row->amount, (string) $row->currency),
            ])
            ->all();

        $created = (clone $vouchers)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupByRaw('date(created_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => ['day' => (string) $row->day, 'total' => (int) $row->total])
            ->all();

        $sold = (int) (clone $vouchers)
            ->whereHas('saleItem.sale', fn ($sale) => $sale->where('status', 'paid'))
            ->count();

        return [
            'range' => $range,
            'kpis' => [
                'Total tickets' => (int) $counts->sum(),
                'Tickets disponibles' => (int) ($counts[VoucherStatus::Available->value] ?? 0),
                'Tickets vendus' => $sold,
                'Tickets actifs' => (int) ($counts[VoucherStatus::Active->value] ?? 0),
                'Tickets utilisés' => (int) ($counts[VoucherStatus::Active->value] ?? 0) + (int) ($counts[VoucherStatus::Expired->value] ?? 0),
                'Tickets expirés' => (int) ($counts[VoucherStatus::Expired->value] ?? 0),
                'Ventes' => (int) (clone $sales)->count(),
                'Clients' => (int) Customer::query()->when($zoneId, fn ($query) => $query->whereHas('vouchers', fn ($inner) => $inner->where('wifi_zone_id', $zoneId)))->count(),
                'Utilisateurs MikroTik' => (int) (clone $vouchers)->where('sync_status', 'synced')->count(),
                'Routeurs' => (int) Mikrotik::query()->when($zoneId, fn ($query) => $query->where('wifi_zone_id', $zoneId))->count(),
                'Synchronisations en attente' => (int) ($sync['pending'] ?? 0) + (int) ($sync['failed'] ?? 0),
            ],
            'revenue' => $revenue,
            'statuses' => [
                'Générés' => (int) $counts->sum(),
                'Disponibles' => (int) ($counts[VoucherStatus::Available->value] ?? 0),
                'Vendus' => $sold,
                'Actifs' => (int) ($counts[VoucherStatus::Active->value] ?? 0),
                'Utilisés' => (int) ($counts[VoucherStatus::Active->value] ?? 0) + (int) ($counts[VoucherStatus::Expired->value] ?? 0),
                'Expirés' => (int) ($counts[VoucherStatus::Expired->value] ?? 0),
            ],
            'profiles' => $profiles,
            'payments' => $payments,
            'created' => $created,
            'activity' => $this->activity($zoneId),
            'router' => $this->router($zoneId),
        ];
    }

    /**
     * @return list<array{text: string, at: string}>
     */
    private function activity(?int $zoneId): array
    {
        $rows = [];
        $sales = Sale::query()
            ->when($zoneId, fn ($query) => $query->where('wifi_zone_id', $zoneId))
            ->latest('id')
            ->limit(4)
            ->get(['id', 'status', 'created_at']);
        foreach ($sales as $sale) {
            $rows[] = [
                'text' => 'Vente #'.$sale->id.' '.($sale->status === 'paid' ? 'confirmée' : $sale->status),
                'at' => $sale->created_at,
            ];
        }
        $tickets = Voucher::query()
            ->when($zoneId, fn ($query) => $query->where('wifi_zone_id', $zoneId))
            ->latest('id')
            ->limit(4)
            ->get(['id', 'username', 'status', 'sync_status', 'created_at']);
        foreach ($tickets as $ticket) {
            $rows[] = [
                'text' => 'Ticket '.$ticket->username.' '.$ticket->statusLabel(),
                'at' => $ticket->created_at,
            ];
            if ($ticket->sync_status === 'synced') {
                $rows[] = [
                    'text' => 'Utilisateur '.$ticket->username.' synchronisé',
                    'at' => $ticket->created_at,
                ];
            }
        }

        return collect($rows)
            ->sortByDesc(fn (array $row) => $row['at']?->getTimestamp() ?? 0)
            ->take(8)
            ->map(fn (array $row) => [
                'text' => $row['text'],
                'at' => $row['at']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function router(?int $zoneId): ?array
    {
        $router = Mikrotik::query()
            ->withCount('profiles')
            ->when($zoneId, fn ($query) => $query->where('wifi_zone_id', $zoneId))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
        if (! $router) {
            return null;
        }

        $details = is_array($router->details) ? $router->details : [];
        $servers = $details['hotspot_servers'] ?? [];
        $interfaces = $details['interfaces'] ?? [];
        $sessions = $details['sessions'] ?? null;

        return [
            'id' => $router->id,
            'name' => $router->name,
            'status' => $router->status,
            'online' => $router->status === 'online',
            'identity' => $router->identity,
            'version' => $router->routeros_version,
            'uptime' => $details['uptime'] ?? null,
            'cpu' => $details['cpu'] ?? null,
            'memory' => $details['memory'] ?? null,
            'interfaces' => is_array($interfaces) ? count($interfaces) : null,
            'servers' => is_array($servers) ? count($servers) : 0,
            'profiles' => (int) $router->profiles_count,
            'users' => $details['hotspot_users'] ?? null,
            'sessions' => is_array($sessions) ? count($sessions) : ($details['active_users'] ?? null),
            'error' => $router->last_error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function platform(): array
    {
        $clientQuery = fn ($query) => $query->whereHas('role', fn ($role) => $role->where('slug', UserRole::Client->value));
        $clients = User::query()->whereHas('role', fn ($query) => $query->where('slug', UserRole::Client->value))->count();
        $paidSales = Sale::withoutGlobalScope('tenant')->where('status', 'paid');

        $revenue = (clone $paidSales)
            ->selectRaw('currency, sum(total_amount) as amount, count(*) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => (string) $row->currency,
                'total' => (int) $row->total,
                'label' => Money::shop($row->amount, (string) $row->currency),
            ])
            ->all();

        return [
            'clients' => $clients,
            'entrepreneurs' => Tenant::count(),
            'zones' => WifiZone::withoutGlobalScope('tenant')->count(),
            'routers' => Mikrotik::withoutGlobalScope('tenant')->count(),
            'routers_online' => Mikrotik::withoutGlobalScope('tenant')->where('status', 'online')->count(),
            'tickets' => Voucher::withoutGlobalScope('tenant')->count(),
            'tickets_sold' => SaleItem::query()->whereHas('sale', fn ($sale) => $sale->withoutGlobalScope('tenant')->where('status', 'paid'))->count(),
            'tickets_active' => Voucher::withoutGlobalScope('tenant')->where('status', VoucherStatus::Active->value)->count(),
            'tickets_expired' => Voucher::withoutGlobalScope('tenant')->where('status', VoucherStatus::Expired->value)->count(),
            'sales' => (int) (clone $paidSales)->count(),
            'revenue' => $revenue,
            'entrepreneurs_by_month' => $this->byMonth(Tenant::query()),
            'clients_by_month' => $this->byMonth(User::query(), $clientQuery),
            'tickets_by_month' => $this->byMonth(Voucher::withoutGlobalScope('tenant')),
            'sales_by_month' => $this->byMonth(Sale::withoutGlobalScope('tenant'), fn ($query) => $query->where('status', 'paid')),
            'zones_by_month' => $this->byMonth(WifiZone::withoutGlobalScope('tenant')),
            'routers_by_month' => $this->byMonth(Mikrotik::withoutGlobalScope('tenant')),
            'activity' => Voucher::withoutGlobalScope('tenant')
                ->latest('id')
                ->limit(6)
                ->get(['id', 'username', 'status', 'created_at'])
                ->map(fn (Voucher $voucher) => [
                    'text' => 'Ticket '.$voucher->username.' '.$voucher->statusLabel(),
                    'at' => $voucher->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
                ])
                ->all(),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, int>
     */
    private function byMonth($query, ?callable $constrain = null): array
    {
        if ($constrain) {
            $constrain($query);
        }

        $bucket = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "date_format(created_at, '%Y-%m')";

        $rows = $query
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw($bucket.' as month, count(*) as total')
            ->groupByRaw($bucket)
            ->orderBy('month')
            ->get();

        $growth = [];
        foreach ($rows as $row) {
            $growth[(string) $row->month] = (int) $row->total;
        }

        return $growth;
    }
}
