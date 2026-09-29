<?php

namespace App\Services;

use App\Models\Mikrotik;
use App\Models\WifiZone;
use Illuminate\Validation\ValidationException;

class PlanLimiter
{
    public function assertZone(): void
    {
        $max = auth()->user()?->tenant?->currentSubscription?->saasPlan?->max_zones;
        if ($max !== null && WifiZone::count() >= $max) {
            throw ValidationException::withMessages([
                'name' => 'Votre abonnement limite le nombre de WiFi Zones.',
            ]);
        }
    }

    public function assertMikrotik(): void
    {
        $max = auth()->user()?->tenant?->currentSubscription?->saasPlan?->max_mikrotiks;
        if ($max !== null && Mikrotik::count() >= $max) {
            throw ValidationException::withMessages([
                'name' => 'Votre abonnement limite le nombre de MikroTik.',
            ]);
        }
    }
}
