<?php

namespace App\Services;

use App\Enums\VoucherStatus;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\WifiZone;
use Illuminate\Support\Str;

class VoucherGenerator
{
    public function create(WifiZone $zone, Plan $plan, int $count = 1, bool $activate = false): array
    {
        $count = max(1, min($count, 100));
        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $created[] = Voucher::create([
                'wifi_zone_id' => $zone->id,
                'plan_id' => $plan->id,
                'public_token' => Str::random(40),
                'username' => $this->username(),
                'password' => (string) random_int(1000, 9999),
                'status' => $activate ? VoucherStatus::Active->value : VoucherStatus::Available->value,
                'activated_at' => $activate ? now() : null,
                'expires_at' => $activate ? now()->addSeconds($plan->duration_seconds) : null,
                'price_amount' => $plan->price,
                'currency' => $plan->currency ?: config('limete.currency'),
            ]);
        }

        return $created;
    }

    public function activate(Voucher $voucher): Voucher
    {
        if ($voucher->status === VoucherStatus::Disabled->value) {
            return $voucher;
        }

        $voucher->forceFill([
            'status' => VoucherStatus::Active->value,
            'activated_at' => $voucher->activated_at ?? now(),
            'expires_at' => now()->addSeconds($voucher->plan->duration_seconds),
        ])->save();

        return $voucher->refresh();
    }

    private function username(): string
    {
        do {
            $username = 'LW'.Str::upper(Str::random(4));
        } while (Voucher::where('username', $username)->exists());

        return $username;
    }
}
