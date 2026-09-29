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

    public function voucherBatchLimit(): int
    {
        $plan = auth()->user()?->tenant?->currentSubscription?->saasPlan;
        if (! $plan || ! $plan->allows('vouchers')) {
            return 0;
        }

        $configured = $plan->features['max_tickets_per_generation'] ?? 100;

        return min(100, max(0, (int) $configured));
    }

    public function assertVoucherBatch(int $count): void
    {
        $plan = auth()->user()?->tenant?->currentSubscription?->saasPlan;
        if (! $plan || ! $plan->allows('vouchers')) {
            throw ValidationException::withMessages([
                'count' => 'Votre abonnement ne comprend pas la génération de tickets. Ouvrez Abonnement et choisissez une formule qui inclut les tickets.',
            ]);
        }

        $max = $this->voucherBatchLimit();
        if ($count < 1 || $count > $max) {
            throw ValidationException::withMessages([
                'count' => 'Votre abonnement autorise au maximum '.$max.' tickets par génération. Indiquez '.$max.' tickets ou moins, ou changez de formule dans Abonnement.',
            ]);
        }
    }
}
