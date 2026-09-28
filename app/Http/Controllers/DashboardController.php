<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\WifiZone;
use App\Services\DashboardMetrics;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetrics $metrics)
    {
        return view('dashboard', [
            'metrics' => $metrics->entrepreneur(),
            'shopZones' => WifiZone::where('status', 'active')->orderBy('name')->get(),
            'routers' => Mikrotik::query()->orderBy('name')->get(),
        ]);
    }
}
