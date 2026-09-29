<?php

namespace App\Services;

use App\Models\Tenant;

class BusinessOnboarding
{
    /**
     * @return list<array{key: string, label: string, done: bool, url: string}>
     */
    public function steps(Tenant $tenant): array
    {
        $hasZone = $tenant->wifiZones()->withoutGlobalScope('tenant')->exists();
        $hasPlan = $tenant->plans()->withoutGlobalScope('tenant')->exists();
        $hasSale = $tenant->sales()->withoutGlobalScope('tenant')->where('status', 'paid')->exists();

        return [
            ['key' => 'business', 'label' => 'Business', 'done' => filled($tenant->name), 'url' => route('business.edit')],
            ['key' => 'logo', 'label' => 'Logo', 'done' => filled($tenant->logo_path), 'url' => route('business.edit').'#logo'],
            ['key' => 'zone', 'label' => 'WiFi Zone', 'done' => $hasZone, 'url' => route('wifi-zones.create')],
            ['key' => 'plan', 'label' => 'Forfait', 'done' => $hasPlan, 'url' => route('plans.create')],
            ['key' => 'sale', 'label' => 'Première vente', 'done' => $hasSale, 'url' => route('sales.quick')],
        ];
    }

    /**
     * @param  list<array{key: string, label: string, done: bool, url: string}>  $steps
     */
    public function next(array $steps): ?string
    {
        foreach ($steps as $step) {
            if (! $step['done']) {
                return $step['url'];
            }
        }

        return null;
    }

    public function complete(array $steps): bool
    {
        return $steps !== [] && $this->next($steps) === null;
    }
}
