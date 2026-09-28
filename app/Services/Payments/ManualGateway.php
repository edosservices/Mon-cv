<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Str;

class ManualGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'manual';
    }

    public function initiate(Payment $payment, array $context = []): Payment
    {
        return $this->createPayment($payment, $context);
    }

    public function createPayment(Payment $payment, array $context = []): Payment
    {
        $reference = $context['transaction_reference'] ?? ('LM-'.Str::upper(Str::random(8)));

        $payment->forceFill([
            'provider' => $this->provider(),
            'transaction_reference' => $reference,
            'status' => PaymentStatus::Pending->value,
            'metadata' => array_merge($payment->metadata ?? [], [
                'configured' => true,
                'note' => 'Paiement manuel en attente. Le ticket sera créé seulement après confirmation du comptoir.',
            ]),
        ])->save();

        return $payment->refresh();
    }

    public function checkPayment(Payment $payment): Payment
    {
        return $payment->refresh();
    }

    public function verifyPayment(string $rawBody, ?string $signature): PaymentNotice
    {
        throw new InvalidPaymentSignature('Le paiement manuel n’a pas de webhook.');
    }

    public function refundPayment(Payment $payment, array $context = []): Payment
    {
        $payment->transitionTo(PaymentStatus::Refunded);
        $payment->save();

        return $payment->refresh();
    }
}
