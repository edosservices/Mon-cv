<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetrics;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function __invoke(Request $request, DashboardMetrics $metrics)
    {
        $range = $request->string('range', '7');
        $to = now();
        $from = match ((string) $range) {
            'today' => now()->startOfDay(),
            '30' => now()->subDays(30),
            'custom' => $request->date('from') ?? now()->subDays(7),
            default => now()->subDays(7),
        };

        if ((string) $range === 'custom' && $request->filled('to')) {
            $to = $request->date('to')->endOfDay();
        }

        $plan = auth()->user()->tenant->currentSubscription?->saasPlan;
        abort_unless($plan?->allows('basic_statistics') || $plan?->allows('advanced_statistics'), 403);

        return view('statistics.index', [
            'stats' => $metrics->statistics($from, $to),
            'range' => (string) $range,
            'advanced' => (bool) $plan?->allows('advanced_statistics'),
        ]);
    }
}
