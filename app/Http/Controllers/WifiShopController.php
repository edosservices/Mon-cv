<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
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
            'providers' => config('limete.payment_providers'),
        ]);
    }

    public function checkout(Request $request, string $slug, SaleService $sales)
    {
        $zone = $this->zone($slug);
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'provider' => ['required', 'in:manual,airtel_money,orange_money,mpesa,card'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
        ]);

        $plan = $this->plans($zone)->firstWhere('id', (int) $data['plan_id']);
        abort_unless($plan, 404);

        try {
            $sale = $sales->placeOrder($zone, $plan, $data, $data['provider'], $data['transaction_reference'] ?? null);
        } catch (\RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage())->withInput();
        }

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
        if ($voucher) {
            $voucher->refreshExpiry();
            $this->remember($zone, 'customer_tickets', $voucher->public_token);
        }

        return view('shop.order', ['zone' => $zone, 'sale' => $sale, 'voucher' => $voucher]);
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
        ]);
    }

    public function pdf(string $token)
    {
        $voucher = $this->publicVoucher($token);

        return Pdf::loadView('vouchers.ticket', [
            'voucher' => $voucher,
            'qr' => QrCodes::svg(route('tickets.public', $voucher->public_token)),
        ])->download('ticket-'.$voucher->username.'.pdf');
    }

    private function publicVoucher(string $token): Voucher
    {
        $voucher = Voucher::withoutGlobalScope('tenant')
            ->where('public_token', $token)
            ->with('plan', 'wifiZone', 'customer')
            ->firstOrFail();

        app(TenantManager::class)->set($voucher->tenant_id);
        $voucher->refreshExpiry();
        $this->remember($voucher->wifiZone, 'customer_tickets', $voucher->public_token);

        return $voucher->fresh(['plan', 'wifiZone', 'customer']);
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
