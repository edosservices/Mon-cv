<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\SaleService;
use App\Support\QrCodes;
use App\Support\TenantManager;
use Illuminate\Http\Request;

class WifiShopController extends Controller
{
    public function show(string $slug)
    {
        $zone = $this->zone($slug);
        $plans = Plan::where('status', 'active')
            ->where(function ($query) use ($zone) {
                $query->whereNull('wifi_zone_id')->orWhere('wifi_zone_id', $zone->id);
            })
            ->orderBy('duration_seconds')
            ->get();

        return view('shop.show', [
            'zone' => $zone,
            'plans' => $plans,
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

        $plan = Plan::where('status', 'active')->findOrFail($data['plan_id']);

        try {
            $sale = $sales->placeOrder($zone, $plan, $data, $data['provider'], $data['transaction_reference'] ?? null);
        } catch (\RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage())->withInput();
        }

        return redirect()->route('shop.order', [$zone->slug, $sale->public_token]);
    }

    public function order(string $slug, string $token)
    {
        $zone = $this->zone($slug);
        $sale = Sale::where('public_token', $token)->where('wifi_zone_id', $zone->id)->with('payment', 'items.voucher.plan')->firstOrFail();

        return view('shop.order', ['zone' => $zone, 'sale' => $sale]);
    }

    public function ticket(string $token)
    {
        $voucher = Voucher::withoutGlobalScope('tenant')->where('public_token', $token)->with('plan', 'wifiZone')->firstOrFail();
        $voucher->refreshExpiry();
        app(TenantManager::class)->set($voucher->tenant_id);

        return view('vouchers.public', [
            'voucher' => $voucher,
            'qr' => QrCodes::svg(route('tickets.public', $voucher->public_token)),
        ]);
    }

    private function zone(string $slug): WifiZone
    {
        $zone = WifiZone::withoutGlobalScope('tenant')->where('slug', $slug)->where('status', 'active')->firstOrFail();
        app(TenantManager::class)->set($zone->tenant_id);

        return $zone;
    }
}
