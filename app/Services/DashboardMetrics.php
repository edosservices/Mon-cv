<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\VoucherStatus;
use App\Models\Mikrotik;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Models\WifiSession;
use App\Models\WifiZone;
use App\Support\TenantManager;
use Illuminate\Support\Carbon;

class DashboardMetrics
{
    public function entrepreneur(): array
    {
        $today = now()->startOfDay();

        return [
            'revenue_today' => (float) Sale::where('status', 'paid')->where('created_at', '>=', $today)->sum('total_amount'),
            'revenue_week' => (float) Sale::where('status', 'paid')->where('created_at', '>=', now()->startOfWeek())->sum('total_amount'),
            'revenue_month' => (float) Sale::where('status', 'paid')->where('created_at', '>=', now()->startOfMonth())->sum('total_amount'),
            'tickets_today' => Sale::where('status', 'paid')->where('created_at', '>=', $today)->count(),
            'active_customers' => Voucher::where('status', VoucherStatus::Active->value)->count(),
            'mikrotiks_online' => Mikrotik::where('status', 'online')->count(),
            'mikrotiks_total' => Mikrotik::count(),
            'zones' => WifiZone::count(),
            'open_sessions' => WifiSession::whereNull('ended_at')->count(),
            'sales_by_plan' => Sale::query()
                ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                ->join('plans', 'plans.id', '=', 'sale_items.plan_id')
                ->where('sales.status', 'paid')
                ->selectRaw('plans.name as name, count(*) as total')
                ->groupBy('plans.name')
                ->pluck('total', 'name'),
        ];
    }

    public function statistics(Carbon $from, Carbon $to): array
    {
        $sales = Sale::where('status', 'paid')->whereBetween('created_at', [$from, $to]);

        return [
            'revenue' => (float) (clone $sales)->sum('total_amount'),
            'tickets' => (clone $sales)->count(),
            'by_day' => (clone $sales)
                ->selectRaw('date(created_at) as day, sum(total_amount) as total, count(*) as tickets')
                ->groupBy('day')
                ->orderBy('day')
                ->get(),
            'by_plan' => Sale::query()
                ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                ->join('plans', 'plans.id', '=', 'sale_items.plan_id')
                ->where('sales.status', 'paid')
                ->whereBetween('sales.created_at', [$from, $to])
                ->selectRaw('plans.name as name, count(*) as total, sum(sale_items.amount) as amount')
                ->groupBy('plans.name')
                ->get(),
            'sessions' => WifiSession::whereBetween('created_at', [$from, $to])->count(),
            'zones' => WifiZone::count(),
            'mikrotiks' => Mikrotik::count(),
        ];
    }

    public function platform(): array
    {
        app(TenantManager::class)->bypass(true);

        return [
            'tenants' => Tenant::count(),
            'tenants_active' => Tenant::where('status', 'active')->count(),
            'zones' => WifiZone::count(),
            'mikrotiks' => Mikrotik::count(),
            'subscriptions_active' => Subscription::whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trial->value,
            ])->count(),
            'subscriptions_expired' => Subscription::where('status', SubscriptionStatus::Expired->value)->count(),
            'platform_revenue' => (float) Payment::where('payable_type', Subscription::class)
                ->where('status', 'success')
                ->sum('amount'),
        ];
    }
}
