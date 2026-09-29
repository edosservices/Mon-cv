<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;

abstract class OperatorGateway implements PaymentGateway
{
    abstract protected function credentialKey(): string;

    abstract protected function webhookSecretKey(): string;

    public function initiate(Payment $payment, array $context = []): Payment
    {
        return $this->createPayment($payment, $context);
    }

    public function createPayment(Payment $payment, array $context = []): Payment
    {
        $configured = filled(config($this->credentialKey()));

        $payment->forceFill([
            'provider' => $this->provider(),
            'transaction_reference' => $context['transaction_reference'] ?? $payment->transaction_reference,
            'status' => PaymentStatus::Pending->value,
            'metadata' => array_merge($payment->metadata ?? [], [
                'configured' => $configured,
                'official_api' => false,
                'note' => $configured
                    ? 'Le connecteur est prévu, mais l’appel opérateur n’est pas branché. Aucun paiement n’a été envoyé. La commande reste en attente.'
                    : 'Ce moyen de paiement n’est pas configuré. Aucun paiement n’a été envoyé à l’opérateur. La commande reste en attente.',
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
        $secret = (string) config($this->webhookSecretKey());
        $given = strtolower(trim((string) $signature));
        $expected = $secret === '' ? '' : hash_hmac('sha256', $rawBody, $secret);

        if ($secret === '' || $given === '' || strlen($given) !== strlen($expected) || ! hash_equals($expected, $given)) {
            throw new InvalidPaymentSignature('Signature de webhook refusée.');
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            throw new InvalidPaymentSignature('Corps de webhook illisible.');
        }

        foreach (['internal_reference', 'provider_reference', 'amount', 'currency', 'status'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
                throw new InvalidPaymentSignature('Webhook incomplet.');
            }
        }

        $status = (string) $data['status'];
        if (! in_array($status, ['success', 'failed', 'cancelled', 'refunded'], true)) {
            throw new InvalidPaymentSignature('Statut de webhook inconnu.');
        }

        return new PaymentNotice(
            internalReference: (string) $data['internal_reference'],
            providerReference: (string) $data['provider_reference'],
            amount: number_format((float) $data['amount'], 2, '.', ''),
            currency: strtoupper((string) $data['currency']),
            status: $status,
            raw: $data,
        );
    }

    public function refundPayment(Payment $payment, array $context = []): Payment
    {
        $payment->transitionTo(PaymentStatus::Refunded);
        $payment->save();

        return $payment->refresh();
    }
}
