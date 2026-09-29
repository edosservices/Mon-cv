<?php

namespace App\Http\Controllers;

use App\Services\BusinessReport;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request, BusinessReport $report)
    {
        $filters = $report->filters($request);

        return view('reports.index', [
            'filters' => $filters,
            'report' => $report->build($filters),
            'sales' => $report->sales($filters, 20),
            'query' => $request->query(),
        ]);
    }
}
