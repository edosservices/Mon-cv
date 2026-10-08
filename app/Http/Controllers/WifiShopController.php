<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Rules\CustomerPhone;
use App\Services\AuditLogger;
use App\Services\CaptivePortal;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Payments\IkeePayCatalog;
use App\Services\Payments\IkeePayGateway;
use App\Services\Payments\PaymentManager;
use App\Services\SaleService;
use App\Support\QrCodes;
use App\Support\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class WifiShopController extends Controller
{
    public function show(Request $request, string $slug, CaptivePortal $portal)
    {
        $zone = $this->zone($slug);
        $portal->capture($request, $zone);
        $phone = $this->rememberedPhone($zone);

        return view('shop.show', [
            'zone' => $zone,
            'plans' => $this->plans($zone),
            'providers' => app(PaymentManager::class)->enabledProviders(),
            'knownPhone' => $phone,
            'activeVoucher' => $this->activeVoucher($zone, $phone),
            'step' => 0,
        ]);
    }

    public function portal(Request $request, string $slug, CaptivePortal $portal)
    {
        $zone = $this->zone($slug);
        $portal->capture($request, $zone);

        return redirect()->route('shop.show', $zone->slug);
    }

    public function plan(string $slug, int $plan)
    {
        $zone = $this->zone($slug);
        $plan = $this->plans($zone)->firstWhere('id', $plan);
        abort_unless($plan, 404);

        return view('shop.plan', [
            'zone' => $zone,
            'plan' => $plan,
            'step' => 1,
        ]);
    }

    public function saveCustomer(Request $request, string $slug, int $plan)
    {
        $zone = $this->zone($slug);
        $plan = $this->plans($zone)->firstWhere('id', $plan);
        abort_unless($plan, 404);

        $data = $request->validate($this->customerRules(true), $this->customerMessages());
        $data = $this->normalizeCustomer($data);
        session([$this->draftKey($zone, $plan) => $data]);

        return redirect()->route('shop.pay', [$zone->slug, $plan->id]);
    }

    public function pay(string $slug, int $plan)
    {
        $zone = $this->zone($slug);
        $plan = $this->plans($zone)->firstWhere('id', $plan);
        abort_unless($plan, 404);

        $draft = session($this->draftKey($zone, $plan));
        if (! is_array($draft)) {
            return redirect()
                ->route('shop.plan', [$zone->slug, $plan->id])
                ->with('warning', 'Indiquez un numéro ou continuez sans compte.');
        }

        return view('shop.pay', [
            'zone' => $zone,
            'plan' => $plan,
            'customer' => $draft,
            'providers' => app(PaymentManager::class)->enabledProviders(),
            'ikeepayChoices' => app(IkeePayCatalog::class)->choices(),
            'step' => 2,
        ]);
    }

    public function checkout(Request $request, string $slug, SaleService $sales)
    {
        $zone = $this->zone($slug);

        if (! filled($request->input('country')) && filled($request->input('ikeepay_method'))) {
            $parts = explode('|', (string) $request->input('ikeepay_method'), 2);
            $request->merge([
                'country' => $parts[0] ?? '',
                'operator' => $parts[1] ?? '',
            ]);
        }

        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', new CustomerPhone],
            'payer_phone' => ['required_if:purchase_for,other', 'nullable', 'string', 'max:30', new CustomerPhone],
            'purchase_for' => ['nullable', 'in:self,other'],
            'email' => ['nullable', 'email', 'max:160'],
            'provider' => ['required', 'in:manual,airtel_money,orange_money,mpesa,card,unipay,ikeepay'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'size:2'],
            'operator' => ['nullable', 'string', 'max:40'],
            'otp' => ['nullable', 'string', 'max:12'],
        ], [
            'provider.required' => 'Choisissez un moyen de paiement.',
            'provider.in' => 'Ce moyen de paiement n’est pas disponible.',
            'email.email' => 'Indiquez une adresse e-mail valide.',
            'phone.required' => 'Indiquez le numéro du bénéficiaire.',
            'payer_phone.required_if' => 'Indiquez le numéro qui paie.',
        ]);

        $plan = $this->plans($zone)->firstWhere('id', (int) $data['plan_id']);
        abort_unless($plan, 404);
        $data = $this->normalizeCustomer($data);
        $captive = app(CaptivePortal::class)->current($zone);
        if ($captive) {
            $data['captive_session_id'] = $captive->id;
        }

        if (filled($data['operator'] ?? null) || filled($data['country'] ?? null)) {
            $data['country'] = strtoupper((string) ($data['country'] ?? ''));
            $data['operator'] = strtoupper((string) ($data['operator'] ?? ''));

            if (! app(IkeePayCatalog::class)->allows($data['country'], $data['operator'])) {
                return back()->withErrors([
                    'operator' => 'Cet opérateur n’est pas disponible pour ce pays.',
                ])->withInput();
            }

            if (! filled($data['phone'] ?? null)) {
                return back()->withErrors([
                    'phone' => 'Indiquez un numéro de téléphone pour ce paiement.',
                ])->withInput();
            }
        }

        try {
            $sale = $sales->placeOrder($zone, $plan, $data, $data['provider'], $data['transaction_reference'] ?? null);
        } catch (\RuntimeException $exception) {
            if ($exception instanceof \Illuminate\Database\QueryException) {
                throw $exception;
            }

            return back()->with('warning', $exception->getMessage())->withInput();
        }

        session()->forget($this->draftKey($zone, $plan));
        session(['shop_phone.'.$zone->id => $data['phone']]);
        $this->remember($zone, 'customer_sales', $sale->public_token);

        if ($data['provider'] === 'ikeepay') {
            $sale->load('payment');
            $payment = $sale->payment;

            if (($payment?->metadata['flow'] ?? null) === 'h2h') {
                if (($payment->metadata['remote_status'] ?? null) === 'completed') {
                    app(IkeePayGateway::class)->verifyTransaction($payment);
                    $sale->refresh();
                    $payment->refresh();
                }

                if ($sale->status !== 'paid') {
                    $link = $payment->metadata['payment_link'] ?? null;
                    if (is_string($link) && str_starts_with($link, 'https://')) {
                        return redirect()->away($link);
                    }
                }

                return redirect()->route('shop.order', [$zone->slug, $sale->public_token]);
            }

            return redirect()->route('shop.ikeepay', [$zone->slug, $sale->public_token]);
        }

        return redirect()->route('shop.order', [$zone->slug, $sale->public_token]);
    }

    public function ikeepay(string $slug, string $token)
    {
        $zone = $this->zone($slug);
        $sale = Sale::where('public_token', $token)
            ->where('wifi_zone_id', $zone->id)
            ->with('payment', 'customer')
            ->firstOrFail();
        $payment = $sale->payment;
        abort_unless($payment?->provider === 'ikeepay', 404);
        if (($payment->metadata['flow'] ?? null) === 'h2h') {
            return redirect()->route('shop.order', [$zone->slug, $sale->public_token]);
        }

        return view('shop.ikeepay', [
            'zone' => $zone,
            'sale' => $sale,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => strtoupper((string) $payment->currency),
            'orderId' => (string) $payment->internal_reference,
            'email' => (string) ($sale->customer?->email ?? ''),
            'publicKey' => (string) config('services.ikeepay.public_key'),
            'checkoutUrl' => (string) config('services.ikeepay.checkout_url'),
            'returnUrl' => route('shop.order', [$zone->slug, $sale->public_token]),
        ]);
    }

    public function order(string $slug, string $token)
    {
        $zone = $this->zone($slug);
        $sale = Sale::where('public_token', $token)
            ->where('wifi_zone_id', $zone->id)
            ->with('payment', 'items.plan', 'items.voucher.plan', 'customer')
            ->firstOrFail();

        $voucher = $sale->items->first()?->voucher;
        if ($voucher && $sale->status === 'paid') {
            $voucher->load('plan', 'wifiZone');
            $voucher->refreshExpiry();
            $this->remember($zone, 'customer_tickets', $voucher->public_token);
        }

        $shown = $sale->status === 'paid' ? $voucher : null;

        return view('shop.order', [
            'zone' => $zone,
            'sale' => $sale,
            'voucher' => $shown,
            'qr' => $shown ? QrCodes::svg(route('tickets.public', $shown->public_token)) : null,
            'network' => $shown ? $this->networkState($shown) : null,
            'step' => $shown ? 4 : 3,
        ]);
    }

    public function refreshPayment(string $slug, string $token, PaymentManager $payments)
    {
        $zone = $this->zone($slug);
        $sale = Sale::where('public_token', $token)->where('wifi_zone_id', $zone->id)->with('payment')->firstOrFail();

        if ($sale->payment) {
            $payments->gateway($sale->payment->provider)->checkPayment($sale->payment);
        }

        return redirect()->route('shop.order', [$zone->slug, $sale->public_token]);
    }

    public function tickets(string $slug)
    {
        $zone = $this->zone($slug);

        return view('shop.tickets', [
            'zone' => $zone,
            'vouchers' => $this->rememberedVouchers($zone),
            'sales' => $this->rememberedSales($zone),
        ]);
    }

    public function lookup(Request $request, string $slug)
    {
        $zone = $this->zone($slug);
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'username' => ['required', 'string', 'max:40'],
        ]);

        $voucher = Voucher::query()
            ->where('wifi_zone_id', $zone->id)
            ->whereRaw('lower(username) = ?', [mb_strtolower(trim($data['username']))])
            ->with('customer')
            ->first();

        $phone = $this->digits($data['phone']);
        $stored = $this->digits($voucher?->customer?->phone);

        if (! $voucher || ! $this->phonesMatch($phone, $stored)) {
            return back()
                ->with('warning', 'Aucun ticket ne correspond à ce numéro et à ce code.')
                ->withInput();
        }

        $this->remember($zone, 'customer_tickets', $voucher->public_token);

        return redirect()->route('tickets.public', $voucher->public_token);
    }

    public function ticket(string $token)
    {
        $voucher = $this->publicVoucher($token);

        return view('vouchers.public', [
            'zone' => $voucher->wifiZone,
            'voucher' => $voucher,
            'qr' => QrCodes::svg(route('tickets.public', $voucher->public_token)),
            'network' => $this->networkState($voucher),
            'step' => 4,
        ]);
    }

    public function brand(string $slug)
    {
        $zone = $this->zone($slug);

        return response()->json($zone->publicBrand())
            ->header('Cache-Control', 'no-store')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function manifest(string $slug)
    {
        $zone = $this->zone($slug);

        return response()->json([
            'name' => $zone->name,
            'short_name' => mb_substr($zone->name, 0, 12),
            'start_url' => route('shop.show', $zone->slug),
            'scope' => url('/'),
            'display' => 'standalone',
            'background_color' => '#f3f6fb',
            'theme_color' => $zone->brandColor(),
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function retrySync(string $token, MikrotikService $mikrotik, AuditLogger $audit)
    {
        $voucher = $this->publicVoucher($token);

        if ($voucher->isSynced()) {
            return back()->with('status', 'Le ticket est déjà synchronisé.');
        }

        $result = $mikrotik->provisionVoucher($voucher);
        $audit->record(
            $result->sync_status === 'synced' ? 'voucher.sync_success' : 'voucher.sync_failed',
            $result,
            null,
            ['sync_status' => $result->sync_status],
        );

        if ($result->sync_status === 'synced') {
            return back()->with('status', 'Le ticket est synchronisé.');
        }

        return back()->with('warning', 'Synchronisation en attente. Le ticket reste utilisable.');
    }

    public function pdf(string $token)
    {
        $voucher = $this->publicVoucher($token);

        return Pdf::loadView('vouchers.ticket', [
            'voucher' => $voucher,
            'qr' => QrCodes::svg(route('tickets.public', $voucher->public_token)),
        ])->download('ticket-'.$voucher->username.'.pdf');
    }

    /**
     * @return array{state: string, ip: ?string, mac: ?string, session_time_left: ?string}
     */
    private function networkState(Voucher $voucher): array
    {
        try {
            return app(MikrotikService::class)->networkSession($voucher);
        } catch (\Throwable) {
            return [
                'state' => 'session non disponible',
                'ip' => null,
                'mac' => null,
                'session_time_left' => null,
            ];
        }
    }

    private function publicVoucher(string $token): Voucher
    {
        $voucher = Voucher::withoutGlobalScope('tenant')
            ->where('public_token', $token)
            ->firstOrFail();

        app(TenantManager::class)->set($voucher->tenant_id);
        $voucher->load('plan', 'wifiZone', 'customer', 'saleItem.sale');
        $voucher->refreshExpiry();
        if ($voucher->wifiZone) {
            $this->remember($voucher->wifiZone, 'customer_tickets', $voucher->public_token);
        }

        return $voucher->fresh(['plan', 'wifiZone', 'customer', 'saleItem.sale']);
    }

    private function zone(string $slug): WifiZone
    {
        $zone = WifiZone::withoutGlobalScope('tenant')->where('slug', $slug)->where('status', 'active')->firstOrFail();
        app(TenantManager::class)->set($zone->tenant_id);

        return $zone;
    }

    private function plans(WifiZone $zone)
    {
        return Plan::where('status', 'active')
            ->where(function ($query) use ($zone) {
                $query->whereNull('wifi_zone_id')->orWhere('wifi_zone_id', $zone->id);
            })
            ->orderBy('duration_seconds')
            ->get();
    }

    private function rememberedVouchers(WifiZone $zone)
    {
        $tokens = session('customer_tickets.'.$zone->id, []);

        if ($tokens === []) {
            return collect();
        }

        return Voucher::query()
            ->where('wifi_zone_id', $zone->id)
            ->whereIn('public_token', $tokens)
            ->with('plan')
            ->latest()
            ->get()
            ->each->refreshExpiry();
    }

    private function rememberedSales(WifiZone $zone)
    {
        $tokens = session('customer_sales.'.$zone->id, []);

        if ($tokens === []) {
            return collect();
        }

        return Sale::query()
            ->where('wifi_zone_id', $zone->id)
            ->whereIn('public_token', $tokens)
            ->where('status', 'pending')
            ->with('payment', 'items.plan')
            ->latest()
            ->get();
    }

    private function remember(WifiZone $zone, string $bucket, string $token): void
    {
        $key = $bucket.'.'.$zone->id;
        $tokens = array_values(array_unique([...session($key, []), $token]));
        session([$key => array_slice($tokens, -20)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function customerRules(bool $phoneRequired): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => [$phoneRequired ? 'required' : 'nullable', 'string', 'max:30', new CustomerPhone],
            'payer_phone' => ['required_if:purchase_for,other', 'nullable', 'string', 'max:30', new CustomerPhone],
            'purchase_for' => ['nullable', 'in:self,other'],
            'email' => ['nullable', 'email', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customerMessages(): array
    {
        return [
            'email.email' => 'Indiquez une adresse e-mail valide.',
            'phone.required' => 'Indiquez le numéro du bénéficiaire.',
            'payer_phone.required_if' => 'Indiquez le numéro qui paie.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeCustomer(array $data): array
    {
        $data['purchase_for'] = ($data['purchase_for'] ?? 'self') === 'other' ? 'other' : 'self';
        $data['phone'] = filled($data['phone'] ?? null) ? $this->normalizePhone((string) $data['phone']) : null;
        $data['payer_phone'] = filled($data['payer_phone'] ?? null) ? $this->normalizePhone((string) $data['payer_phone']) : null;
        if ($data['purchase_for'] === 'self') {
            $data['payer_phone'] = $data['phone'];
        }

        return $data;
    }

    private function activeVoucher(WifiZone $zone, ?string $phone): ?Voucher
    {
        $remembered = $this->rememberedVouchers($zone)->first(fn ($voucher) => $voucher->status === 'active' && ($voucher->expires_at === null || $voucher->expires_at->isFuture()));
        if ($remembered) {
            return $remembered;
        }

        if (! filled($phone)) {
            return null;
        }

        return Voucher::query()
            ->where('wifi_zone_id', $zone->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('customer', fn ($query) => $query->where('phone', $phone))
            ->with('plan')
            ->latest('id')
            ->first();
    }

    private function rememberedPhone(WifiZone $zone): ?string
    {
        $saved = session('shop_phone.'.$zone->id);
        if (is_string($saved) && $saved !== '') {
            return $saved;
        }

        foreach (session()->all() as $key => $value) {
            if (str_starts_with((string) $key, 'shop_draft.'.$zone->id.'.') && is_array($value) && filled($value['phone'] ?? null)) {
                return (string) $value['phone'];
            }
        }

        return null;
    }

    private function draftKey(WifiZone $zone, Plan $plan): string
    {
        return 'shop_draft.'.$zone->id.'.'.$plan->id;
    }

    private function normalizePhone(string $phone): string
    {
        return \App\Support\PhoneNumbers::normalize($phone) ?? $phone;
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function phonesMatch(string $given, string $stored): bool
    {
        if ($given === '' || $stored === '') {
            return false;
        }

        if ($given === $stored) {
            return true;
        }

        $short = strlen($given) < strlen($stored) ? $given : $stored;
        $long = $short === $given ? $stored : $given;

        return strlen($short) >= 9 && str_ends_with($long, $short);
    }
}
