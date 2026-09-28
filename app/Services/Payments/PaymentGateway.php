<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function provider(): string;

    public function initiate(Payment $payment, array $context = []): Payment;

    public function createPayment(Payment $payment, array $context = []): Payment;

    public function checkPayment(Payment $payment): Payment;

    public function verifyPayment(string $rawBody, ?string $signature): PaymentNotice;

    public function refundPayment(Payment $payment, array $context = []): Payment;
}
