<?php

namespace App\Http\Controllers;

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

        return view('dashboard', [
            'filters' => $filters,
            'report' => $data,
            'sales' => $report->sales($filters, 5),
            'shopZones' => $data['zones']->where('status', 'active'),
            'notices' => $request->user()->unreadNotifications()->latest()->limit(4)->get(),
        ]);
    }
}
