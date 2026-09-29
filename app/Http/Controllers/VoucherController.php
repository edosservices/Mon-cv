<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use App\Services\Mikrotik\MikrotikService;
use App\Services\PlanLimiter;
use App\Services\TicketBatch;
use App\Services\TicketSheet;
use App\Services\VoucherGenerator;
use App\Support\QrCodes;
use App\Support\TicketTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $vouchers = Voucher::with(['plan', 'wifiZone', 'customer', 'saleItem.sale.payment'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $vouchers->getCollection()->each->refreshExpiry();

        return view('vouchers.index', [
            'vouchers' => $vouchers,
            'templates' => TicketTemplates::options(),
            'limit' => app(PlanLimiter::class)->voucherBatchLimit(),
        ]);
    }

    public function createBatch(PlanLimiter $limits)
    {
        return view('vouchers.generate', [
            'plans' => Plan::with('wifiZone')->orderBy('name')->get(),
            'zones' => WifiZone::orderBy('name')->get(),
            'templates' => TicketTemplates::options(),
            'limit' => $limits->voucherBatchLimit(),
            'prefill' => [
                'wifi_zone_id' => request()->integer('wifi_zone_id') ?: null,
                'plan_id' => request()->integer('plan_id') ?: null,
                'count' => request()->integer('count') ?: null,
                'template' => TicketTemplates::normalize(
                    request()->filled('template')
                        ? request()->string('template')->toString()
                        : (string) request()->user()?->tenant?->ticket_style
                ),
                'per_page' => in_array(request()->integer('per_page'), [4, 6, 8], true) ? request()->integer('per_page') : 6,
            ],
        ]);
    }

    public function store(Request $request, TicketBatch $batch, MikrotikService $mikrotik, AuditLogger $audit)
    {
        $data = $this->validatedBatch($request);
        $zone = WifiZone::findOrFail($data['wifi_zone_id']);
        $plan = Plan::findOrFail($data['plan_id']);
        $created = $batch->generate($zone->id, $plan->id, (int) $data['count']);
        $summary = $mikrotik->provisionMany($created);
        $audit->record('vouchers.created', $zone, null, [
            'count' => count($created),
            'plan' => $plan->name,
            'synced' => $summary['synced'],
            'unsynced' => $summary['unsynced'],
        ]);

        session([
            'voucher_batch' => [
                'ids' => array_map(fn ($voucher) => $voucher->id, $created),
                'wifi_zone_id' => $zone->id,
                'plan_id' => $plan->id,
                'template' => $data['template'],
                'per_page' => (int) $data['per_page'],
                'count' => count($created),
            ],
        ]);

        return redirect()->route('vouchers.generated')->with(
            $summary['unsynced'] > 0 ? 'warning' : 'status',
            $this->provisionMessage(count($created), $summary),
        );
    }

    public function generated(TicketSheet $sheet)
    {
        $batch = session('voucher_batch');
        if (! is_array($batch) || empty($batch['ids'])) {
            return redirect()->route('vouchers.generate')->with('warning', 'Générez des tickets pour afficher une sélection à imprimer.');
        }

        $vouchers = $sheet->owned($batch['ids']);

        return view('vouchers.generated', [
            'vouchers' => $vouchers,
            'batch' => $batch,
            'templates' => TicketTemplates::options(),
        ]);
    }

    public function printSheet(Request $request, TicketSheet $sheet)
    {
        [$vouchers, $template, $perPage, $templatesById] = $this->selection($request, $sheet);
        $built = $sheet->pages($vouchers, $template, $perPage, $templatesById);

        return view('vouchers.sheet', [
            'pages' => $built['pages'],
            'perPage' => $built['per_page'],
            'template' => $built['template'],
            'pdf' => false,
            'ids' => $vouchers->pluck('id')->all(),
        ]);
    }

    public function pdfSheet(Request $request, TicketSheet $sheet)
    {
        [$vouchers, $template, $perPage, $templatesById] = $this->selection($request, $sheet);
        $built = $sheet->pages($vouchers, $template, $perPage, $templatesById);

        return Pdf::loadView('vouchers.sheet', [
            'pages' => $built['pages'],
            'perPage' => $built['per_page'],
            'template' => $built['template'],
            'pdf' => true,
            'ids' => $vouchers->pluck('id')->all(),
        ])->setPaper('a4', 'portrait')->download('tickets-'.now()->format('Ymd-His').'.pdf');
    }

    public function sync(Voucher $voucher, MikrotikService $mikrotik)
    {
        $result = $mikrotik->provisionVoucher($voucher);

        $audit = app(AuditLogger::class);
        $audit->record(
            $result->sync_status === 'synced' ? 'voucher.sync_success' : 'voucher.sync_failed',
            $result,
            null,
            ['sync_status' => $result->sync_status, 'sync_error' => $result->sync_error],
        );

        if ($result->sync_status === 'synced') {
            return back()->with('status', 'Le compte '.$result->username.' a été créé sur le MikroTik.');
        }

        return back()->with('warning', 'Ticket créé, synchronisation MikroTik en attente. Le ticket est enregistré, mais il n’a pas été créé sur le MikroTik. '.$result->sync_error);
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

    public function destroy(Request $request, Voucher $voucher, MikrotikService $mikrotik, AuditLogger $audit)
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

        $redirect = $request->boolean('stay')
            ? back()
            : redirect()->route('vouchers.index');

        return $redirect->with($warning ? 'warning' : 'status', $warning ?? 'Ticket archivé.');
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, Voucher>, 1: string, 2: int, 3: array<int, string>}
     */
    private function selection(Request $request, TicketSheet $sheet): array
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'template' => ['required', Rule::in(TicketTemplates::keys())],
            'per_page' => ['required', Rule::in([4, 6, 8])],
            'templates' => ['nullable', 'array'],
            'templates.*' => ['nullable', Rule::in(TicketTemplates::keys())],
        ]);

        $templatesById = [];
        foreach ($data['templates'] ?? [] as $id => $template) {
            if (is_string($template) && $template !== '') {
                $templatesById[(int) $id] = $template;
            }
        }

        return [$sheet->owned($data['ids']), $data['template'], (int) $data['per_page'], $templatesById];
    }

    /**
     * @return array{wifi_zone_id: int, plan_id: int, count: int, template: string, per_page: int}
     */
    private function validatedBatch(Request $request): array
    {
        $request->merge([
            'template' => $request->input('template', TicketTemplates::MODERN),
            'per_page' => $request->input('per_page', 6),
        ]);

        return $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'template' => ['required', Rule::in(TicketTemplates::keys())],
            'per_page' => ['required', Rule::in([4, 6, 8])],
        ], [
            'count.max' => 'La quantité maximale est de :max tickets par génération.',
            'count.min' => 'Indiquez au moins un ticket.',
        ], [
            'wifi_zone_id' => 'WiFi Zone',
            'plan_id' => 'forfait',
            'count' => 'quantité',
            'template' => 'modèle',
            'per_page' => 'disposition',
        ]);
    }

    private function provisionMessage(int $count, array $summary): string
    {
        if ($summary['synced'] === 0) {
            return $count.' ticket(s) enregistré(s). Aucun compte n’a été créé sur le MikroTik. Ticket créé, synchronisation MikroTik en attente. '.$summary['error'];
        }

        if ($summary['unsynced'] > 0) {
            return $summary['synced'].' compte(s) créé(s) sur le MikroTik, '.$summary['unsynced'].' non synchronisé(s). '.$summary['error'];
        }

        return $count.' ticket(s) créé(s) sur le MikroTik.';
    }
}
