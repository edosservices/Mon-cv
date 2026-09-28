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
        $reference = $context['transaction_reference'] ?? ('LM-'.Str::upper(Str::random(8)));

        $payment->forceFill([
            'provider' => $this->provider(),
            'transaction_reference' => $reference,
            'status' => PaymentStatus::Pending->value,
            'metadata' => array_merge($payment->metadata ?? [], [
                'note' => 'En attente de confirmation du paiement.',
            ]),
        ])->save();

        return $payment;
    }
}
