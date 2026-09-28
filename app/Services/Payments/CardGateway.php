<?php

namespace App\Services\Payments;

class CardGateway extends OperatorGateway
{
    public function provider(): string
    {
        return 'card';
    }

    protected function credentialKey(): string
    {
        return 'limete.payments.card.secret';
    }

    protected function webhookSecretKey(): string
    {
        return 'limete.payments.card.webhook_secret';
    }
}
