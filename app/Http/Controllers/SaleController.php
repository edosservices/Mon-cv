<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\BusinessReport;
use App\Services\SaleService;
use App\Services\TicketBatch;
use App\Support\TicketTemplates;
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

    public function quick(Request $request)
    {
        $this->authorizeQuick($request);

        return view('sales.quick', [
            'zones' => WifiZone::orderBy('name')->get(),
            'plans' => Plan::query()->where('status', 'active')->orderBy('name')->get(),
            'prefillZone' => $request->integer('wifi_zone_id') ?: null,
            'prefillPlan' => $request->integer('plan_id') ?: null,
        ]);
    }

    public function storeQuick(Request $request, TicketBatch $batch, SaleService $sales)
    {
        $this->authorizeQuick($request);
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $zone = WifiZone::findOrFail($data['wifi_zone_id']);
        $plan = Plan::findOrFail($data['plan_id']);
        $created = $batch->generate($zone->id, $plan->id, (int) $data['quantity']);

        $customer = null;
        if (filled($data['phone'] ?? null) || filled($data['name'] ?? null)) {
            $customer = Customer::create([
                'name' => $data['name'] ?: null,
                'phone' => $data['phone'] ?: null,
            ]);
        }

        foreach ($created as $voucher) {
            $sales->sellExisting($voucher, $customer);
        }

        session(['quick_sale_ids' => array_map(fn ($voucher) => $voucher->id, $created)]);

        return redirect()->route('sales.quick.done')->with('status', 'Vente enregistrée.');
    }

    public function quickDone(Request $request)
    {
        $this->authorizeQuick($request);
        $ids = session('quick_sale_ids', []);
        if (! is_array($ids) || $ids === []) {
            return redirect()->route('sales.quick')->with('warning', 'Aucune vente à afficher.');
        }

        $vouchers = Voucher::query()->with(['plan', 'wifiZone.tenant'])->whereIn('id', $ids)->get();
        abort_if($vouchers->isEmpty(), 404);

        return view('sales.quick-done', [
            'vouchers' => $vouchers,
            'template' => TicketTemplates::normalize($request->user()->tenant?->ticket_style),
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
        $sale->refresh();

        if ($sale->status !== 'paid') {
            return redirect()->route('sales.show', $sale)->with('warning', 'Le paiement iKeePay n’est pas confirmé. Aucun ticket n’a été créé.');
        }

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

    private function authorizeQuick(Request $request): void
    {
        abort_unless(
            $request->user()?->hasPermission('sales.confirm') && $request->user()?->hasPermission('vouchers.manage'),
            403
        );
    }
}
