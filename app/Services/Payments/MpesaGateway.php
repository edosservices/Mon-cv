<?php

namespace App\Services\Payments;

class MpesaGateway extends OperatorGateway
{
    public function provider(): string
    {
        return 'mpesa';
    }

    protected function credentialKey(): string
    {
        return 'limete.payments.mpesa.api_key';
    }

    protected function webhookSecretKey(): string
    {
        return 'limete.payments.mpesa.webhook_secret';
    }
}
