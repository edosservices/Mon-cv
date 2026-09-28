<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use App\Services\Mikrotik\MikrotikService;
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

    public function store(Request $request, VoucherGenerator $generator, MikrotikService $mikrotik, AuditLogger $audit)
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $zone = WifiZone::findOrFail($data['wifi_zone_id']);
        $plan = Plan::findOrFail($data['plan_id']);
        $created = $generator->create($zone, $plan, (int) $data['count']);
        $summary = $mikrotik->provisionMany($created);
        $audit->record('vouchers.created', $zone, null, [
            'count' => count($created),
            'plan' => $plan->name,
            'synced' => $summary['synced'],
            'unsynced' => $summary['unsynced'],
        ]);

        return redirect()->route('vouchers.index')->with(
            $summary['unsynced'] > 0 ? 'warning' : 'status',
            $this->provisionMessage(count($created), $summary),
        );
    }

    public function sync(Voucher $voucher, MikrotikService $mikrotik)
    {
        $result = $mikrotik->provisionVoucher($voucher);

        if ($result->sync_status === 'synced') {
            return back()->with('status', 'Le compte '.$result->username.' a été créé sur le MikroTik.');
        }

        return back()->with('warning', 'Le ticket est enregistré, mais il n’a pas été créé sur le MikroTik. '.$result->sync_error);
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

    public function updateStatus(Request $request, Voucher $voucher, VoucherGenerator $generator, MikrotikService $mikrotik, AuditLogger $audit)
    {
        $status = $request->validate(['status' => ['required', 'in:available,active,disabled']])['status'];
        $old = $voucher->status;
        $warning = null;

        if ($status === VoucherStatus::Active->value) {
            $generator->activate($voucher);
            if ($voucher->sync_status !== 'synced') {
                $result = $mikrotik->provisionVoucher($voucher->fresh());
                if ($result->sync_status !== 'synced') {
                    $warning = 'Le ticket est actif ici, mais pas encore créé sur le MikroTik. '.$result->sync_error;
                }
            }
        } else {
            $voucher->forceFill(['status' => $status])->save();
            if ($status === VoucherStatus::Disabled->value && $voucher->sync_status === 'synced' && $voucher->mikrotik) {
                try {
                    $mikrotik->disableHotspotUser($voucher->mikrotik, $voucher->username);
                } catch (\RuntimeException $exception) {
                    $warning = 'Le ticket est désactivé ici. Le routeur n’a pas confirmé la désactivation : '.$exception->getMessage();
                }
            }
        }

        $audit->record('voucher.status', $voucher, ['status' => $old], ['status' => $voucher->status]);

        return back()->with($warning ? 'warning' : 'status', $warning ?? 'Statut du ticket mis à jour.');
    }

    public function destroy(Voucher $voucher, MikrotikService $mikrotik, AuditLogger $audit)
    {
        $warning = null;
        if ($voucher->sync_status === 'synced' && $voucher->mikrotik) {
            try {
                $mikrotik->deleteHotspotUser($voucher->mikrotik, $voucher->username);
            } catch (\RuntimeException $exception) {
                $warning = 'Le ticket est archivé ici. Le compte est peut-être encore présent sur le MikroTik : '.$exception->getMessage();
            }
        }

        $audit->record('voucher.deleted', $voucher, ['username' => $voucher->username]);
        $voucher->delete();

        return redirect()->route('vouchers.index')->with($warning ? 'warning' : 'status', $warning ?? 'Ticket archivé.');
    }

    private function provisionMessage(int $count, array $summary): string
    {
        if ($summary['synced'] === 0) {
            return $count.' ticket(s) enregistré(s). Aucun compte n’a été créé sur le MikroTik. '.$summary['error'];
        }

        if ($summary['unsynced'] > 0) {
            return $summary['synced'].' compte(s) créé(s) sur le MikroTik, '.$summary['unsynced'].' non synchronisé(s). '.$summary['error'];
        }

        return $count.' ticket(s) créé(s) sur le MikroTik.';
    }
}
