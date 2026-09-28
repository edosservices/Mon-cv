<?php

namespace App\Services\Payments;

use App\Models\PaymentProviderSetting;
use InvalidArgumentException;

class PaymentManager
{
    public function gateway(string $provider): PaymentGateway
    {
        return match ($provider) {
            'manual' => new ManualGateway,
            'airtel_money' => new AirtelMoneyGateway,
            'orange_money' => new OrangeMoneyGateway,
            'mpesa' => new MpesaGateway,
            'card' => new CardGateway,
            'unipay' => new UniPayGateway,
            default => throw new InvalidArgumentException('Moyen de paiement inconnu.'),
        };
    }

    public function enabled(string $provider): bool
    {
        if (! array_key_exists($provider, config('limete.payment_providers'))) {
            return false;
        }

        $setting = PaymentProviderSetting::query()->where('provider', $provider)->first();

        return $setting?->enabled ?? true;
    }

    public function configured(string $provider): bool
    {
        if ($provider === 'manual') {
            return true;
        }

        $key = match ($provider) {
            'airtel_money' => 'limete.payments.airtel_money.api_key',
            'orange_money' => 'limete.payments.orange_money.api_key',
            'mpesa' => 'limete.payments.mpesa.api_key',
            'card' => 'limete.payments.card.secret',
            'unipay' => 'services.unipay.key',
            default => null,
        };

        return $key !== null && filled(config($key));
    }

    /**
     * @return array<string, string>
     */
    public function enabledProviders(): array
    {
        return collect(config('limete.payment_providers'))
            ->filter(fn ($label, $key) => $this->enabled($key))
            ->all();
    }
}
