<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Mikrotik;
use App\Models\SaasPlan;
use App\Models\Subscription;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Support\Money;
use Illuminate\Support\Collection;

class SubscriptionCatalog
{
    public function activePlans(): Collection
    {
        return SaasPlan::query()->where('is_active', true)->orderBy('id')->get();
    }

    public function current(): ?Subscription
    {
        $subscription = auth()->user()?->tenant?->currentSubscription;
        $subscription?->loadMissing('saasPlan');

        return $subscription;
    }

    /**
     * @return array{
     *     zones: array{label: string, count: int, max: ?int, percent: ?int},
     *     mikrotiks: array{label: string, count: int, max: ?int, percent: ?int},
     *     tickets: array{label: string, count: int},
     *     clients: array{label: string, count: int}
     * }
     */
    public function usage(): array
    {
        $plan = $this->current()?->saasPlan;

        return [
            'zones' => $this->meter('WiFi Zones', WifiZone::count(), $plan?->max_zones),
            'mikrotiks' => $this->meter('MikroTik', Mikrotik::count(), $plan?->max_mikrotiks),
            'tickets' => ['label' => 'Tickets', 'count' => Voucher::count()],
            'clients' => ['label' => 'Clients', 'count' => Customer::count()],
        ];
    }

    public function priceLabel(SaasPlan $plan): string
    {
        $amount = $this->amountLabel($plan);
        $days = (int) ($plan->interval_days ?? 0);

        if ($days === 30) {
            return $amount.' / mois';
        }

        if ($days > 0) {
            return $amount.' / '.$days.' jours';
        }

        return $amount;
    }

    private function amountLabel(SaasPlan $plan): string
    {
        if ($plan->price === null || $plan->price === '') {
            return 'À définir';
        }

        $currency = $plan->currency ?: config('limete.currency');

        if ($currency === 'USD') {
            $amount = (float) $plan->price;
            $decimals = $amount == 0.0 ? 2 : 1;

            return number_format($amount, $decimals, '.', '').' $';
        }

        return Money::shop($plan->price, $currency);
    }

    public function periodLabel(SaasPlan $plan): ?string
    {
        $days = (int) ($plan->interval_days ?? 0);

        if ($days === 30) {
            return 'Abonnement mensuel';
        }

        if ($days > 0) {
            return 'Période de '.$days.' jours';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function advantages(SaasPlan $plan): array
    {
        $lines = [];
        $lines[] = $this->capLine($plan->max_zones, 'WiFi Zone', 'WiFi Zones');
        $lines[] = $this->capLine($plan->max_mikrotiks, 'MikroTik', 'MikroTik');

        $labels = [
            'vouchers' => 'Génération de tickets',
            'basic_statistics' => 'Statistiques de base',
            'advanced_statistics' => 'Statistiques avancées',
            'user_management' => 'Gestion des utilisateurs',
            'api' => 'Accès API',
        ];

        $features = is_array($plan->features) ? $plan->features : [];

        foreach ($labels as $key => $label) {
            if (! empty($features[$key])) {
                $lines[] = $label;
            }
        }

        if (isset($features['max_tickets_per_generation']) && is_numeric($features['max_tickets_per_generation'])) {
            $lines[] = ((int) $features['max_tickets_per_generation']).' tickets par génération';
        }

        return $lines;
    }

    public function raisesZoneLimit(?SaasPlan $current, SaasPlan $candidate): bool
    {
        if (! $current || $current->id === $candidate->id) {
            return false;
        }

        if ($current->max_zones === null) {
            return false;
        }

        if ($candidate->max_zones === null) {
            return true;
        }

        return (int) $candidate->max_zones > (int) $current->max_zones;
    }

    public function checkoutUrl(SaasPlan $plan): string
    {
        return route('subscription.show', ['plan' => $plan->id]).'#paiement';
    }

    public function isExpired(?Subscription $subscription): bool
    {
        if (! $subscription || ! $subscription->ends_at) {
            return $subscription?->status === SubscriptionStatus::Expired->value;
        }

        if ($subscription->status === SubscriptionStatus::Cancelled->value) {
            return false;
        }

        return $subscription->ends_at->isPast() || $subscription->status === SubscriptionStatus::Expired->value;
    }

    public function daysRemaining(?Subscription $subscription): ?int
    {
        if (! $subscription?->ends_at || $this->isExpired($subscription)) {
            return null;
        }

        $timezone = config('app.timezone');
        $today = now()->timezone($timezone)->startOfDay();
        $endDay = $subscription->ends_at->timezone($timezone)->copy()->startOfDay();

        return (int) round($today->diffInDays($endDay, false));
    }

    /**
     * @return array{label: string, count: int, max: ?int, percent: ?int}
     */
    private function meter(string $label, int $count, mixed $max): array
    {
        $cap = is_numeric($max) ? (int) $max : null;
        $percent = null;

        if ($cap !== null) {
            $percent = $cap > 0 ? (int) min(100, round($count / $cap * 100)) : 100;
        }

        return [
            'label' => $label,
            'count' => $count,
            'max' => $cap,
            'percent' => $percent,
        ];
    }

    private function capLine(mixed $max, string $singular, string $plural): string
    {
        if ($max === null) {
            return $plural.' sans plafond';
        }

        $count = (int) $max;

        return $count.' '.($count > 1 ? $plural : $singular);
    }
}
