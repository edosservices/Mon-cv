<?php

namespace App\Services\Payments;

class OrangeMoneyGateway extends OperatorGateway
{
    public function provider(): string
    {
        return 'orange_money';
    }

    protected function credentialKey(): string
    {
        return 'limete.payments.orange_money.api_key';
    }

    protected function webhookSecretKey(): string
    {
        return 'limete.payments.orange_money.webhook_secret';
    }
}
