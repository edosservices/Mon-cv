<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\WifiZone;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TicketBatch
{
    public function __construct(
        private VoucherGenerator $generator,
        private PlanLimiter $limits,
    ) {}

    /**
     * @return list<\App\Models\Voucher>
     */
    public function generate(int $zoneId, int $planId, int $count): array
    {
        $this->limits->assertVoucherBatch($count);
        $zone = WifiZone::findOrFail($zoneId);
        $plan = Plan::findOrFail($planId);

        if ($plan->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $zone->id) {
            throw ValidationException::withMessages([
                'plan_id' => 'Ce forfait n’appartient pas à la WiFi Zone choisie. Sélectionnez un forfait de cette zone.',
            ]);
        }

        if ((int) $plan->tenant_id !== (int) $zone->tenant_id) {
            throw ValidationException::withMessages([
                'plan_id' => 'Ce forfait n’appartient pas à votre espace.',
            ]);
        }

        try {
            return $this->generator->create($zone, $plan, $count);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'count' => 'Impossible de réserver des identifiants uniques pour toute la quantité. Aucun ticket de cette tentative n’a été enregistré. Relancez la génération.',
            ]);
        }
    }
}
