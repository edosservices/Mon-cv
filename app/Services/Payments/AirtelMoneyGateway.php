<?php

namespace App\Services\Payments;

class AirtelMoneyGateway extends OperatorGateway
{
    public function provider(): string
    {
        return 'airtel_money';
    }

    protected function credentialKey(): string
    {
        return 'limete.payments.airtel_money.api_key';
    }

    protected function webhookSecretKey(): string
    {
        return 'limete.payments.airtel_money.webhook_secret';
    }
}
