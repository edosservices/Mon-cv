<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetrics;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetrics $metrics)
    {
        return view('dashboard', ['metrics' => $metrics->entrepreneur()]);
    }
}
