<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Voucher;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $sales = Sale::with(['customer', 'wifiZone', 'items.plan'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20);

        return view('sales.index', ['sales' => $sales]);
    }

    public function show(Sale $sale)
    {
        $sale->load('customer', 'wifiZone', 'payment', 'items.voucher', 'items.plan');

        return view('sales.show', ['sale' => $sale]);
    }

    public function confirm(Sale $sale, SaleService $sales)
    {
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
