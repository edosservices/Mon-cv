<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use App\Services\VoucherGenerator;
use App\Support\QrCodes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $vouchers = Voucher::with(['plan', 'wifiZone'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $vouchers->getCollection()->each->refreshExpiry();

        return view('vouchers.index', [
            'vouchers' => $vouchers,
            'plans' => Plan::orderBy('name')->get(),
            'zones' => WifiZone::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, VoucherGenerator $generator, AuditLogger $audit)
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $zone = WifiZone::findOrFail($data['wifi_zone_id']);
        $plan = Plan::findOrFail($data['plan_id']);
        $created = $generator->create($zone, $plan, (int) $data['count']);
        $audit->record('vouchers.created', $zone, null, ['count' => count($created), 'plan' => $plan->name]);

        return redirect()->route('vouchers.index')->with('status', count($created).' ticket(s) créé(s).');
    }

    public function show(Voucher $voucher)
    {
        $voucher->load('plan', 'wifiZone', 'customer');
        $voucher->refreshExpiry();

        return view('vouchers.show', [
            'voucher' => $voucher,
            'qr' => QrCodes::svg(route('tickets.public', $voucher->public_token)),
        ]);
    }

    public function pdf(Voucher $voucher)
    {
        $voucher->load('plan', 'wifiZone');

        return Pdf::loadView('vouchers.ticket', [
            'voucher' => $voucher,
            'qr' => QrCodes::svg(route('tickets.public', $voucher->public_token)),
        ])->download($voucher->username.'.pdf');
    }

    public function updateStatus(Request $request, Voucher $voucher, VoucherGenerator $generator, AuditLogger $audit)
    {
        $status = $request->validate(['status' => ['required', 'in:available,active,disabled']])['status'];
        $old = $voucher->status;

        if ($status === VoucherStatus::Active->value) {
            $generator->activate($voucher);
        } else {
            $voucher->forceFill(['status' => $status])->save();
        }

        $audit->record('voucher.status', $voucher, ['status' => $old], ['status' => $voucher->status]);

        return back()->with('status', 'Statut du ticket mis à jour.');
    }

    public function destroy(Voucher $voucher, AuditLogger $audit)
    {
        $audit->record('voucher.deleted', $voucher, ['username' => $voucher->username]);
        $voucher->delete();

        return redirect()->route('vouchers.index')->with('status', 'Ticket archivé.');
    }
}
