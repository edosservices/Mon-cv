<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Models\Mikrotik;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use App\Services\Mikrotik\MikrotikPreparation;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Mikrotik\RouterOsProtocol;
use App\Services\PlanLimiter;
use App\Services\TicketAssist;
use App\Services\TicketBatch;
use App\Services\TicketQuick;
use App\Services\TicketSheet;
use App\Services\VoucherGenerator;
use App\Support\QrCodes;
use App\Support\TicketTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->string('status');
        $vouchers = Voucher::with(['plan', 'wifiZone', 'customer', 'saleItem.sale.payment'])
            ->when(in_array($status, ['available', 'active', 'expired', 'disabled'], true), fn ($query) => $query->where('status', $status))
            ->when($status === 'sold', fn ($query) => $query->whereHas('saleItem.sale', fn ($sale) => $sale->where('status', 'paid')))
            ->when($status === 'used', fn ($query) => $query->whereIn('status', ['active', 'expired']))
            ->when($status === 'pending_sync', fn ($query) => $query->where('sync_status', 'pending'))
            ->when($status === 'failed', fn ($query) => $query->where('sync_status', 'failed'))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('username', 'like', $term)
                        ->orWhere('public_token', 'like', $term)
                        ->orWhereHas('plan', fn ($plan) => $plan->where('name', 'like', $term)->orWhere('mikrotik_profile', 'like', $term));
                });
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
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

    public function createBatch(PlanLimiter $limits, TicketQuick $quick)
    {
        $zones = WifiZone::orderBy('name')->get();
        $assist = app(TicketAssist::class);
        $zone = $zones->firstWhere('id', request()->integer('wifi_zone_id')) ?? $zones->first();
        $catalog = ['profiles' => [], 'servers' => [], 'offline' => true, 'notice' => null, 'last' => null];
        if ($zone) {
            try {
                $catalog = $quick->catalog($zone);
            } catch (RuntimeException $exception) {
                $catalog['notice'] = $assist->readable($exception);
            }
        }

        return view('vouchers.generate', [
            'plans' => Plan::with('wifiZone')->orderBy('name')->get(),
            'zones' => $zones,
            'durations' => $zones->mapWithKeys(fn (WifiZone $zone) => [$zone->id => $assist->durations($zone)]),
            'quickZone' => $zone,
            'quickRouter' => $zone?->mikrotiks()->where('is_active', true)->orderBy('id')->first(),
            'quick' => $catalog,
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

    public function quickPreview(Request $request, TicketQuick $quick, TicketAssist $assist)
    {
        $input = $this->quickInput($request);
        $zone = WifiZone::findOrFail($input['wifi_zone_id']);

        try {
            $preview = $quick->preview($zone, $input);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $assist->readable($exception))->withInput();
        }

        if (! empty($preview['needs_plan'])) {
            return view('vouchers.quick-link', [
                'zone' => $zone,
                'preview' => $preview,
            ]);
        }

        return view('vouchers.quick-preview', [
            'zone' => $zone,
            'preview' => $preview,
            'input' => $input,
        ]);
    }

    public function quickStore(Request $request, TicketQuick $quick, TicketAssist $assist)
    {
        $input = $this->quickInput($request);
        $request->validate(['confirm' => ['accepted']]);
        $zone = WifiZone::findOrFail($input['wifi_zone_id']);

        try {
            $created = $quick->confirm($zone, $input);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $assist->readable($exception))->withInput();
        }

        if (count($created) === 1) {
            return redirect()->route('vouchers.assist.show', $created[0]);
        }

        session([
            'voucher_batch' => [
                'ids' => array_map(fn ($voucher) => $voucher->id, $created),
                'wifi_zone_id' => $zone->id,
                'plan_id' => $created[0]->plan_id,
                'template' => 'moderne',
                'per_page' => 6,
                'count' => count($created),
            ],
        ]);

        return redirect()->route('vouchers.generated')->with('status', count($created).' tickets créés.');
    }

    public function quickExpress(Request $request, TicketQuick $quick, TicketAssist $assist, MikrotikPreparation $preparation, MikrotikService $mikrotik)
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'profile' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/'],
            'qty' => ['required', 'integer', 'min:1', 'max:100'],
            'server' => ['nullable', 'string', 'max:32'],
            'time_limit' => ['nullable', 'string', 'max:32'],
            'data_mb' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'comment' => ['nullable', 'string', 'max:120'],
        ], [
            'profile.regex' => 'Paramètres incompatibles.',
        ]);
        $zone = WifiZone::findOrFail($data['wifi_zone_id']);
        try {
            $time = $preparation->normalizedTime($data['time_limit'] ?? null);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['time_limit' => 'Paramètres incompatibles.']);
        }
        $unlimited = ! filled($data['data_mb'] ?? null);
        $input = [
            'wifi_zone_id' => $zone->id,
            'profile' => $data['profile'],
            'server' => filled($data['server'] ?? null) ? $data['server'] : 'all',
            'mode' => 'generate',
            'qty' => (int) $data['qty'],
            'prefix' => 'LM',
            'length' => 4,
            'charset' => 'mixed',
            'draft' => (string) Str::uuid(),
            'data_unlimited' => $unlimited,
            'data_value' => $unlimited ? null : (int) $data['data_mb'],
            'data_unit' => 'MB',
        ];

        try {
            $result = $quick->commit($zone, $input);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $assist->readable($exception))->withInput();
        }

        if (! empty($result['needs_plan'])) {
            return view('vouchers.quick-link', [
                'zone' => $zone,
                'preview' => $result,
            ]);
        }

        $created = $result['vouchers'];
        $comment = $this->userComment($data, '', $time);
        if (filled($time) || trim((string) ($data['comment'] ?? '')) !== '') {
            foreach ($created as $index => $voucher) {
                $this->applyUserFields($mikrotik, $assist, $zone, $voucher, $time, $comment);
                $created[$index] = $voucher->refresh();
            }
        }
        if (count($created) === 1) {
            return redirect()->route('vouchers.assist.show', $created[0])->with('status', 'Ticket créé et enregistré.');
        }

        session([
            'voucher_batch' => [
                'ids' => array_map(fn ($voucher) => $voucher->id, $created),
                'wifi_zone_id' => $zone->id,
                'plan_id' => $created[0]->plan_id,
                'template' => 'moderne',
                'per_page' => 6,
                'count' => count($created),
            ],
        ]);

        return redirect()->route('vouchers.generated')->with('status', count($created).' tickets créés et enregistrés.');
    }

    public function quickUser(Request $request, TicketQuick $quick, TicketAssist $assist, MikrotikPreparation $preparation, MikrotikService $mikrotik)
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'profile' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/'],
            'server' => ['nullable', 'string', 'max:32'],
            'username' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/'],
            'password' => ['nullable', 'string', 'regex:/^\d{3,8}$/'],
            'time_limit' => ['nullable', 'string', 'max:32'],
            'data_mb' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'comment' => ['nullable', 'string', 'max:120'],
        ], [
            'profile.regex' => 'Paramètres incompatibles.',
            'username.regex' => 'Paramètres incompatibles.',
            'password.regex' => 'Paramètres incompatibles.',
        ]);
        $zone = WifiZone::findOrFail($data['wifi_zone_id']);

        try {
            $time = $preparation->normalizedTime($data['time_limit'] ?? null);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['time_limit' => 'Paramètres incompatibles.']);
        }

        $unlimited = ! filled($data['data_mb'] ?? null);
        $input = [
            'wifi_zone_id' => $zone->id,
            'profile' => $data['profile'],
            'server' => filled($data['server'] ?? null) ? $data['server'] : 'all',
            'mode' => 'add',
            'qty' => 1,
            'username' => $data['username'] ?? '',
            'password' => $data['password'] ?? '',
            'draft' => (string) Str::uuid(),
            'data_unlimited' => $unlimited,
            'data_value' => $unlimited ? null : (int) $data['data_mb'],
            'data_unit' => 'MB',
        ];

        try {
            $result = $quick->commit($zone, $input);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $assist->readable($exception))->withInput();
        }

        if (! empty($result['needs_plan'])) {
            return view('vouchers.quick-link', [
                'zone' => $zone,
                'preview' => $result,
            ]);
        }

        /** @var Voucher $voucher */
        $voucher = $result['vouchers'][0];
        $comment = $this->userComment($data, $voucher->username, $time);
        $this->applyUserFields($mikrotik, $assist, $zone, $voucher, $time, $comment);

        return redirect()->route('vouchers.assist.show', $voucher)->with('status', 'Utilisateur créé.');
    }

    public function quickPlan(Request $request, TicketQuick $quick, TicketAssist $assist, MikrotikPreparation $preparation)
    {
        $commercial = $this->commercialInput($request, $preparation);
        $zone = WifiZone::findOrFail($commercial['wifi_zone_id']);

        try {
            $plan = $quick->storeLinkedPlan($zone, $commercial['profile'], $commercial);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $assist->readable($exception))->withInput();
        }

        return redirect()
            ->route('vouchers.generate', ['wifi_zone_id' => $zone->id])
            ->with('status', 'Forfait LIMETE enregistré pour '.$plan->mikrotik_profile.'.');
    }

    public function quickLink(Request $request, TicketQuick $quick, TicketAssist $assist)
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'profile' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/'],
            'plan_id' => ['required', 'integer'],
        ], [
            'profile.regex' => 'Paramètres incompatibles.',
        ]);
        $zone = WifiZone::findOrFail($data['wifi_zone_id']);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        if ((int) $plan->tenant_id !== (int) $zone->tenant_id) {
            abort(404);
        }

        try {
            $quick->attachPlan($zone, $data['profile'], $plan);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $assist->readable($exception))->withInput();
        }

        return redirect()
            ->route('vouchers.generate', ['wifi_zone_id' => $zone->id])
            ->with('status', 'Forfait LIMETE associé à '.$data['profile'].'.');
    }

    public function quickUsername(Request $request, TicketQuick $quick)
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:32'],
        ]);
        $zone = WifiZone::findOrFail($data['wifi_zone_id']);

        return response()->json($quick->usernameHint($zone, (string) ($data['q'] ?? '')));
    }

    public function assistPreview(Request $request, TicketAssist $assist)
    {
        $zone = WifiZone::findOrFail($request->integer('wifi_zone_id'));
        $plan = Plan::findOrFail($request->integer('plan_id'));
        if ($plan->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $zone->id) {
            abort(404);
        }

        if (! $request->filled('mbps')) {
            try {
                $assist->parameters($plan, 5, 0);
            } catch (RuntimeException $exception) {
                return back()->with('warning', $assist->readable($exception));
            }

            return view('vouchers.assist-config', [
                'zone' => $zone,
                'plan' => $plan,
                'step' => 2,
            ]);
        }

        $data = $this->assistInput($request);

        try {
            $preview = $assist->inspect(
                $zone,
                $plan,
                $data['mbps'],
                $data['data_gb'],
                $data['username'],
                $data['password'],
                $request->boolean('regenerate'),
                $data['alternate_name'],
            );
        } catch (RuntimeException $exception) {
            return back()->with('warning', $assist->readable($exception))->withInput();
        }

        unset($preview['router']);

        return view('vouchers.assist-preview', [
            'zone' => $zone,
            'plan' => $plan,
            'preview' => $preview,
            'draft' => $request->filled('draft') ? $request->string('draft')->toString() : (string) str()->uuid(),
            'step' => ($preview['identity']['collision'] ?? false) ? 4 : 5,
        ]);
    }

    public function assistStore(Request $request, TicketAssist $assist)
    {
        $data = $this->assistInput($request);
        $request->validate(['confirm' => ['accepted'], 'draft' => ['required', 'string', 'max:80']]);
        $zone = WifiZone::findOrFail($data['wifi_zone_id']);
        $plan = Plan::findOrFail($data['plan_id']);
        if ($plan->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $zone->id) {
            abort(404);
        }

        try {
            $parameters = $assist->parameters($plan, $data['mbps'], $data['data_gb']);
            if (filled($data['alternate_name'])) {
                $parameters['profile'] = $data['alternate_name'];
            }
            $voucher = $assist->confirm(
                $zone,
                $plan,
                $parameters,
                ['username' => (string) $data['username'], 'password' => (string) $data['password']],
                $data['profile_choice'] ?: 'create',
                $data['draft'],
                $data['alternate_name'],
            );
        } catch (RuntimeException $exception) {
            return back()->with('warning', $assist->readable($exception))->withInput();
        }

        return redirect()->route('vouchers.assist.show', $voucher);
    }

    public function assistShow(Voucher $voucher)
    {
        $voucher->load('plan', 'wifiZone');
        $voucher->refreshExpiry();

        return view('vouchers.assist-done', [
            'voucher' => $voucher,
            'zone' => $voucher->wifiZone,
            'handover' => Cache::get('ticket-assist.card.'.$voucher->id, []),
            'qr' => QrCodes::svg(route('tickets.public', $voucher->public_token)),
            'step' => 6,
        ]);
    }

    public function store(Request $request, TicketBatch $batch, MikrotikService $mikrotik, AuditLogger $audit)
    {
        $data = $this->validatedBatch($request);
        $zone = WifiZone::findOrFail($data['wifi_zone_id']);
        $plan = Plan::findOrFail($data['plan_id']);
        if ($plan->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $zone->id) {
            throw ValidationException::withMessages([
                'plan_id' => 'Ce forfait n’appartient pas à cette WiFi Zone.',
            ]);
        }
        if ($plan->status !== 'active') {
            throw ValidationException::withMessages([
                'plan_id' => 'Ce forfait n’est pas actif.',
            ]);
        }
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

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'density' => ['required', 'integer', 'in:20,30,40,50'],
        ]);
        $vouchers = Voucher::with(['plan', 'wifiZone'])->whereIn('id', $data['ids'])->orderBy('id')->get();

        return view('vouchers.bulk-ticket', [
            'vouchers' => $vouchers,
            'perPage' => (int) $data['density'],
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
    /**
     * @return array{wifi_zone_id: int, plan_id: int, mbps: int, data_gb: int, username: ?string, password: ?string, profile_choice: ?string, alternate_name: ?string, draft: ?string}
     */
    /**
     * @param  array<string, mixed>  $data
     */
    private function userComment(array $data, string $username, ?string $time): string
    {
        $comment = trim((string) ($data['comment'] ?? ''));
        if ($comment === '') {
            $parts = array_filter([
                $username,
                (string) ($data['profile'] ?? ''),
                $time,
                filled($data['data_mb'] ?? null) ? ((int) $data['data_mb']).' MB' : null,
                now()->timezone(config('app.timezone'))->format('d/m/Y'),
            ]);
            $comment = implode(' · ', $parts);
        }

        $password = (string) ($data['password'] ?? '');
        if ($password !== '') {
            $comment = str_replace($password, '', $comment);
        }
        $comment = trim((string) preg_replace('/[\r\n=]+/', ' ', $comment));

        return mb_substr($comment, 0, 120);
    }

    private function applyUserFields(MikrotikService $mikrotik, TicketAssist $assist, WifiZone $zone, Voucher $voucher, ?string $time, string $comment): void
    {
        $router = Mikrotik::query()->where('wifi_zone_id', $zone->id)->where('is_active', true)->orderBy('id')->first();
        if (! $router || $voucher->sync_status !== 'synced') {
            return;
        }

        $attributes = [];
        if (filled($time)) {
            $attributes['limit-uptime'] = $time;
        }
        if ($comment !== '') {
            $attributes['comment'] = $comment;
        }
        if ($attributes === []) {
            return;
        }

        try {
            $mikrotik->updateHotspotUser($router, $voucher->username, $attributes);
        } catch (\Throwable $exception) {
            $voucher->forceFill([
                'sync_status' => 'failed',
                'sync_error' => $assist->readable($exception),
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function quickInput(Request $request): array
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'profile' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/'],
            'server' => ['nullable', 'string', 'max:32'],
            'data_value' => ['required', 'integer', 'min:1', 'max:100000'],
            'data_unit' => ['required', 'in:MB,GB'],
            'mode' => ['required', 'in:add,generate'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:100'],
            'prefix' => ['nullable', 'string', 'max:12'],
            'length' => ['nullable', 'integer', 'min:3', 'max:8'],
            'charset' => ['nullable', 'in:mixed,digits,lower,upper'],
            'username' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'max:8'],
            'draft' => ['required', 'string', 'max:80'],
        ], [
            'profile.regex' => 'Paramètres incompatibles.',
            'data_value.min' => 'Paramètres incompatibles.',
            'data_unit.in' => 'Paramètres incompatibles.',
        ]);

        return [
            'wifi_zone_id' => (int) $data['wifi_zone_id'],
            'profile' => $data['profile'],
            'server' => $data['server'] ?? 'all',
            'data_value' => (int) $data['data_value'],
            'data_unit' => $data['data_unit'],
            'mode' => $data['mode'],
            'qty' => (int) ($data['qty'] ?? 1),
            'prefix' => $data['prefix'] ?? '',
            'length' => (int) ($data['length'] ?? 4),
            'charset' => $data['charset'] ?? 'mixed',
            'username' => $data['username'] ?? '',
            'password' => $data['password'] ?? '',
            'draft' => $data['draft'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commercialInput(Request $request, MikrotikPreparation $preparation): array
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'profile' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/'],
            'validity' => ['required', 'string', 'max:32'],
            'time_limit' => ['nullable', 'string', 'max:32'],
            'price_amount' => ['required', 'numeric', 'min:0'],
            'price_currency' => ['required', 'string', 'min:2', 'max:8', 'regex:/^[A-Za-z]{2,8}$/'],
            'selling_price_amount' => ['nullable', 'numeric', 'min:0'],
            'selling_price_currency' => ['nullable', 'string', 'min:2', 'max:8', 'regex:/^[A-Za-z]{2,8}$/'],
        ], [
            'profile.regex' => 'Paramètres incompatibles.',
        ]);

        try {
            $data['validity'] = $preparation->normalizedTime($data['validity']);
            $data['time_limit'] = $preparation->normalizedTime($data['time_limit'] ?? null);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['validity' => 'Paramètres incompatibles.']);
        }
        if ($data['validity'] === null) {
            throw ValidationException::withMessages(['validity' => 'Paramètres incompatibles.']);
        }

        $validitySeconds = RouterOsProtocol::routerTimeToSeconds($data['validity']);
        $limitSeconds = filled($data['time_limit'] ?? null) ? RouterOsProtocol::routerTimeToSeconds($data['time_limit']) : null;
        if ($validitySeconds === null || ($limitSeconds !== null && $limitSeconds > $validitySeconds)) {
            throw ValidationException::withMessages([
                'time_limit' => 'Time Limit doit être inférieur à Validity.',
            ]);
        }

        $data['price_currency'] = strtoupper($data['price_currency']);
        $data['selling_price_currency'] = filled($data['selling_price_currency'] ?? null)
            ? strtoupper($data['selling_price_currency'])
            : $data['price_currency'];
        $selling = $data['selling_price_amount'] ?? null;
        if ($selling === null || $selling === '') {
            $data['selling_price_amount'] = $data['price_amount'];
        }

        return $data;
    }

    private function assistInput(Request $request): array
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'mbps' => ['required', 'integer', 'min:1', 'max:1000'],
            'data_gb' => ['required', 'integer', 'in:0,2,5,10,20'],
            'username' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/'],
            'password' => ['nullable', 'string', 'regex:/^\d{3,8}$/'],
            'profile_choice' => ['nullable', 'in:reuse,create,rename,cancel'],
            'alternate_name' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/'],
            'draft' => ['nullable', 'string', 'max:80'],
        ], [
            'username.regex' => 'Paramètres incompatibles.',
            'password.regex' => 'Paramètres incompatibles.',
            'alternate_name.regex' => 'Paramètres incompatibles.',
            'mbps.min' => 'Paramètres incompatibles.',
            'mbps.max' => 'Paramètres incompatibles.',
            'data_gb.in' => 'Paramètres incompatibles.',
        ]);

        return [
            'wifi_zone_id' => (int) $data['wifi_zone_id'],
            'plan_id' => (int) $data['plan_id'],
            'mbps' => (int) $data['mbps'],
            'data_gb' => (int) $data['data_gb'],
            'username' => $data['username'] ?? null,
            'password' => $data['password'] ?? null,
            'profile_choice' => $data['profile_choice'] ?? null,
            'alternate_name' => $data['alternate_name'] ?? null,
            'draft' => $data['draft'] ?? null,
        ];
    }

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
