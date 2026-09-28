<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function provider(): string;

    public function initiate(Payment $payment, array $context = []): Payment;
}
