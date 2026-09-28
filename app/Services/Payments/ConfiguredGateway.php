<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;

class ConfiguredGateway implements PaymentGateway
{
    public function __construct(private string $name, private string $envKey) {}

    public function provider(): string
    {
        return $this->name;
    }

    public function initiate(Payment $payment, array $context = []): Payment
    {
        $configured = filled(env($this->envKey));

        $payment->forceFill([
            'provider' => $this->name,
            'transaction_reference' => $context['transaction_reference'] ?? $payment->transaction_reference,
            'status' => PaymentStatus::Pending->value,
            'metadata' => array_merge($payment->metadata ?? [], [
                'configured' => $configured,
                'note' => $configured
                    ? 'Le connecteur est prévu, mais l’appel opérateur n’est pas branché. Aucun paiement n’a été envoyé. La commande reste en attente.'
                    : 'Ce moyen de paiement n’est pas configuré. Aucun paiement n’a été envoyé à l’opérateur. La commande reste en attente.',
            ]),
        ])->save();

        return $payment;
    }
}
