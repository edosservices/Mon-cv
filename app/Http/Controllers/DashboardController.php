<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Services\BusinessReport;
use App\Services\DashboardAlerts;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BusinessReport $report, DashboardAlerts $alerts)
    {
        $filters = $report->filters($request);
        $data = $report->build($filters);
        $alerts->sync($request->user(), $data);
        $zoneId = $filters['zone_id'];
        $vouchers = Voucher::query()->when($zoneId, fn ($query) => $query->where('wifi_zone_id', $zoneId));

        return view('dashboard', [
            'filters' => $filters,
            'report' => $data,
            'sales' => $report->sales($filters, 5),
            'shopZones' => $data['zones']->where('status', 'active'),
            'notices' => $request->user()->unreadNotifications()->latest()->limit(4)->get(),
            'pulse' => [
                'month' => $report->netRevenue(now()->startOfMonth(), now()->endOfMonth(), $zoneId),
                'sold' => (clone $vouchers)->whereIn('status', [VoucherStatus::Active->value, VoucherStatus::Expired->value])->count(),
                'available' => (clone $vouchers)->where('status', VoucherStatus::Available->value)->count(),
                'sessions' => is_countable($data['sessions']) ? count($data['sessions']) : 0,
            ],
        ]);
    }
}
