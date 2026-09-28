<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentManager
{
    public function gateway(string $provider): PaymentGateway
    {
        return match ($provider) {
            'manual' => new ManualGateway,
            'airtel_money' => new ConfiguredGateway('airtel_money', 'AIRTEL_MONEY_API_KEY'),
            'orange_money' => new ConfiguredGateway('orange_money', 'ORANGE_MONEY_API_KEY'),
            'mpesa' => new ConfiguredGateway('mpesa', 'MPESA_API_KEY'),
            'card' => new ConfiguredGateway('card', 'CARD_GATEWAY_SECRET'),
            default => throw new InvalidArgumentException('Moyen de paiement inconnu.'),
        };
    }
}
