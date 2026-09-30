<?php

namespace App\Support;

use App\Models\Plan;
use App\Services\Payments\PaymentManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class PublicCatalog
{
    /**
     * Forfaits actifs des zones publiques. Aucun chiffre d’affaires n’est lu.
     *
     * @return Collection<int, Plan>
     */
    public static function offers(): Collection
    {
        try {
            return Plan::withoutGlobalScope('tenant')
                ->where('status', 'active')
                ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
                ->where(function ($query) {
                    $query->whereNull('wifi_zone_id')
                        ->orWhereIn('wifi_zone_id', function ($sub) {
                            $sub->select('id')
                                ->from('wifi_zones')
                                ->where('status', 'active')
                                ->whereNull('deleted_at');
                        });
                })
                ->with([
                    'wifiZone' => fn ($query) => $query->withoutGlobalScope('tenant'),
                    'tenant:id,name,status',
                ])
                ->orderBy('wifi_zone_id')
                ->orderBy('duration_seconds')
                ->orderBy('price')
                ->limit(36)
                ->get()
                ->filter(function (Plan $plan): bool {
                    if (! $plan->wifi_zone_id) {
                        return true;
                    }

                    return $plan->wifiZone !== null && $plan->wifiZone->status === 'active';
                })
                ->values();
        } catch (QueryException) {
            return collect();
        }
    }

    public static function buyUrl(Plan $plan): string
    {
        $zone = $plan->wifiZone;
        if ($zone && $zone->status === 'active' && filled($zone->slug)) {
            return route('shop.plan', [$zone->slug, $plan->id]);
        }

        return route('client.buy');
    }

    /**
     * Moyens activés dans l’application. Le checkout reste limité aux moyens configurés.
     *
     * @return array<string, string>
     */
    public static function payments(): array
    {
        try {
            $manager = app(PaymentManager::class);

            return collect(config('limete.payment_providers'))
                ->filter(fn ($label, $key) => $manager->enabled($key))
                ->all();
        } catch (QueryException) {
            return [];
        }
    }
}
