<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Rules\CustomerPhone;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Payments\PaymentManager;
use App\Services\SaleService;
use App\Support\QrCodes;
use App\Support\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class WifiShopController extends Controller
{
    public function show(string $slug)
    {
        $zone = $this->zone($slug);

        return view('shop.show', [
            'zone' => $zone,
            'plans' => $this->plans($zone),
            'providers' => app(PaymentManager::class)->enabledProviders(),
            'knownPhone' => $this->rememberedPhone($zone),
            'activeVoucher' => $this->rememberedVouchers($zone)->first(fn ($voucher) => $voucher->status === 'active'),
            'step' => 0,
        ]);
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

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', new CustomerPhone],
        ], [
            'phone.required' => 'Entrez votre numéro de téléphone.',
        ]);

        $data['phone'] = $this->normalizePhone($data['phone']);
        session([$this->draftKey($zone, $plan) => $data]);

        return redirect()->route('shop.pay', [$zone->slug, $plan->id]);
    }

    public function pay(string $slug, int $plan)
    {
        $zone = $this->zone($slug);
        $plan = $this->plans($zone)->firstWhere('id', $plan);
        abort_unless($plan, 404);

        $draft = session($this->draftKey($zone, $plan));
        if (! is_array($draft) || empty($draft['phone'])) {
            return redirect()
                ->route('shop.plan', [$zone->slug, $plan->id])
                ->with('warning', 'Entrez votre numéro de téléphone pour continuer.');
        }

        return view('shop.pay', [
            'zone' => $zone,
            'plan' => $plan,
            'customer' => $draft,
            'providers' => app(PaymentManager::class)->enabledProviders(),
            'step' => 2,
        ]);
    }

    public function checkout(Request $request, string $slug, SaleService $sales)
    {
        $zone = $this->zone($slug);
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', new CustomerPhone],
            'provider' => ['required', 'in:manual,airtel_money,orange_money,mpesa,card,unipay'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
        ], [
            'phone.required' => 'Entrez votre numéro de téléphone.',
            'provider.required' => 'Choisissez un moyen de paiement.',
            'provider.in' => 'Ce moyen de paiement n’est pas disponible.',
        ]);

        $plan = $this->plans($zone)->firstWhere('id', (int) $data['plan_id']);
        abort_unless($plan, 404);
        $data['phone'] = $this->normalizePhone($data['phone']);

        try {
            $sale = $sales->placeOrder($zone, $plan, $data, $data['provider'], $data['transaction_reference'] ?? null);
        } catch (\RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage())->withInput();
        }

        session()->forget($this->draftKey($zone, $plan));
        session(['shop_phone.'.$zone->id => $data['phone']]);
        $this->remember($zone, 'customer_sales', $sale->public_token);

        return redirect()->route('shop.order', [$zone->slug, $sale->public_token]);
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
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '243')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '+243'.$digits;
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
