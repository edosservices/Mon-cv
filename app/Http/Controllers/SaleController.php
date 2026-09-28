<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Services\BusinessReport;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request, BusinessReport $report)
    {
        if ($request->filled('status') && ! $request->filled('payment')) {
            $request->merge(['payment' => $request->string('status')->toString() === 'paid' ? 'success' : $request->string('status')->toString()]);
        }

        $filters = $report->filters($request);

        return view('sales.index', [
            'sales' => $report->sales($filters),
            'filters' => $filters,
            'plans' => Plan::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Sale $sale)
    {
        $sale->load('customer', 'wifiZone', 'payment', 'items.voucher', 'items.plan');

        return view('sales.show', ['sale' => $sale]);
    }

    public function confirm(Sale $sale, SaleService $sales)
    {
        if ($sale->status === 'paid') {
            return redirect()->route('sales.show', $sale)->with('status', 'Paiement déjà confirmé.');
        }

        abort_unless($sale->status === 'pending', 422);
        $sales->confirm($sale);

        return redirect()->route('sales.show', $sale)->with('status', 'Paiement confirmé et ticket généré.');
    }

    public function sellVoucher(Request $request, Voucher $voucher, SaleService $sales)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $customer = null;
        if (filled($data['phone'] ?? null) || filled($data['name'] ?? null)) {
            $customer = Customer::create([
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);
        }

        $sale = $sales->sellExisting($voucher, $customer);

        return redirect()->route('vouchers.show', $voucher)->with('status', 'Vente enregistrée. Référence '.$sale->public_token);
    }
}
