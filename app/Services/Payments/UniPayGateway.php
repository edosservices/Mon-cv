<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class UniPayGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'unipay';
    }

    public function initiate(Payment $payment, array $context = []): Payment
    {
        return $this->createPayment($payment, $context);
    }

    public function createPayment(Payment $payment, array $context = []): Payment
    {
        $payment->forceFill([
            'provider' => $this->provider(),
            'transaction_reference' => $context['transaction_reference'] ?? $payment->transaction_reference,
            'status' => PaymentStatus::Pending->value,
        ])->save();

        if (! $this->configured()) {
            return $this->remember($payment, [
                'request_sent' => false,
                'mode' => $this->mode(),
                'note' => 'UniPay non configuré',
            ]);
        }

        if ($this->baseUrl() === '') {
            return $this->remember($payment, [
                'request_sent' => false,
                'mode' => $this->mode(),
                'note' => 'UniPay non configuré',
            ]);
        }

        $payload = [
            'reference' => $payment->internal_reference,
            'amount' => $this->money($payment->amount),
            'currency' => strtoupper((string) $payment->currency),
            'customer' => [
                'phone' => $context['customer_phone'] ?? null,
                'name' => $context['customer_name'] ?? null,
            ],
            'callback_url' => route('payments.webhook', ['provider' => 'unipay']),
            'return_url' => $context['return_url'] ?? null,
            'metadata' => [
                'tenant_id' => $context['tenant_id'] ?? $payment->tenant_id,
                'wifi_zone_id' => $context['wifi_zone_id'] ?? null,
                'sale_id' => $context['sale_id'] ?? ($payment->payable_type === \App\Models\Sale::class ? $payment->payable_id : null),
                'plan_reference' => $context['plan_reference'] ?? null,
                'voucher_reference' => $context['voucher_reference'] ?? null,
            ],
        ];

        try {
            $response = $this->http()->post('/payments', $payload);
        } catch (Throwable $exception) {
            $this->trace($payment, 'create', false, $this->redact($exception->getMessage()));

            return $this->remember($payment, [
                'request_sent' => true,
                'mode' => $this->mode(),
                'note' => 'UniPay n’a pas répondu. Aucun ticket n’est créé.',
            ]);
        }

        $body = $response->json();
        $transactionId = is_array($body) ? $this->transactionId($body) : null;
        $this->trace($payment, 'create', $response->successful() && $transactionId !== null, 'HTTP '.$response->status());

        if (! $response->successful() || $transactionId === null) {
            return $this->remember($payment, [
                'request_sent' => true,
                'mode' => $this->mode(),
                'http_status' => $response->status(),
                'note' => 'UniPay n’a pas confirmé l’initiation. Aucun ticket n’est créé.',
            ]);
        }

        $payment->provider_reference = $transactionId;
        $reported = is_array($body) ? $this->mapStatus((string) ($body['status'] ?? $body['state'] ?? 'pending')) : null;
        if ($reported === PaymentStatus::Processing->value) {
            $payment->transitionTo(PaymentStatus::Processing);
        }

        return $this->remember($payment, [
            'request_sent' => true,
            'mode' => $this->mode(),
            'unipay_transaction_id' => $transactionId,
            'reported_status' => $reported,
            'note' => 'Initiation UniPay enregistrée. Le ticket attend une confirmation de transaction.',
        ]);
    }

    public function checkPayment(Payment $payment): Payment
    {
        $payment = $payment->refresh();
        if (! $this->configured() || $this->baseUrl() === '' || ! filled($payment->provider_reference)) {
            return $payment;
        }

        if (! $this->safeId((string) $payment->provider_reference)) {
            return $payment;
        }

        try {
            $response = $this->http()->get('/payments/'.rawurlencode((string) $payment->provider_reference));
        } catch (Throwable $exception) {
            $this->trace($payment, 'check', false, $this->redact($exception->getMessage()));

            return $payment;
        }

        $this->trace($payment, 'check', $response->successful(), 'HTTP '.$response->status());
        $notice = $this->noticeFromResponse($payment, $response);
        if (! $notice) {
            return $payment->refresh();
        }

        app(PaymentSettlement::class)->apply($this->provider(), $notice);

        return $payment->refresh();
    }

    public function verifyPayment(string $rawBody, ?string $signature): PaymentNotice
    {
        $secret = (string) config('services.unipay.webhook_secret');
        $given = strtolower(trim((string) $signature));
        $given = str_starts_with($given, 'sha256=') ? substr($given, 7) : $given;
        $expected = $secret === '' ? '' : hash_hmac('sha256', $rawBody, $secret);

        if ($secret === '' || $given === '' || strlen($given) !== strlen($expected) || ! hash_equals($expected, $given)) {
            throw new InvalidPaymentSignature('Signature de webhook refusée.');
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            throw new InvalidPaymentSignature('Corps de webhook illisible.');
        }

        $notice = $this->noticeFromArray($data);
        if (! $notice) {
            throw new InvalidPaymentSignature('Webhook incomplet.');
        }

        return $notice;
    }

    public function refundPayment(Payment $payment, array $context = []): Payment
    {
        $payment = $payment->refresh();
        if ($payment->status !== PaymentStatus::Success->value || ! filled($payment->provider_reference)) {
            return $payment;
        }

        if (! $this->configured() || $this->baseUrl() === '' || ! $this->safeId((string) $payment->provider_reference)) {
            return $this->remember($payment, [
                'note' => 'Remboursement UniPay non envoyé. UniPay non configuré',
            ]);
        }

        try {
            $response = $this->http()->post('/payments/'.rawurlencode((string) $payment->provider_reference).'/refunds', [
                'reference' => $payment->internal_reference,
                'amount' => $this->money($payment->amount),
                'currency' => strtoupper((string) $payment->currency),
            ]);
        } catch (Throwable $exception) {
            $this->trace($payment, 'refund', false, $this->redact($exception->getMessage()));

            return $payment;
        }

        $body = $response->json();
        $status = is_array($body) ? $this->mapStatus((string) ($body['status'] ?? '')) : null;
        $this->trace($payment, 'refund', $response->successful() && $status === PaymentStatus::Refunded->value, 'HTTP '.$response->status());

        if ($response->successful() && $status === PaymentStatus::Refunded->value) {
            $payment->transitionTo(PaymentStatus::Refunded);
            $payment->save();
        }

        return $payment->refresh();
    }

    public function mode(): string
    {
        return strtolower(trim((string) config('services.unipay.mode'))) === 'live' ? 'live' : 'test';
    }

    public function configured(): bool
    {
        return filled(config('services.unipay.key'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.unipay.base_url'), '/');
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken((string) config('services.unipay.key'))
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->withHeaders(['X-UniPay-Mode' => $this->mode()]);
    }

    private function noticeFromResponse(Payment $payment, Response $response): ?PaymentNotice
    {
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        return $this->noticeFromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function noticeFromArray(array $data): ?PaymentNotice
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data = array_merge($data['data'], array_diff_key($data, ['data' => true]));
        }

        $reference = $data['reference'] ?? $data['internal_reference'] ?? $data['order_id'] ?? null;
        $transactionId = $this->transactionId($data);
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        $status = $this->mapStatus((string) ($data['status'] ?? $data['state'] ?? ''));

        if (! filled($reference) || ! filled($transactionId) || $amount === null || $amount === '' || ! filled($currency) || $status === null) {
            return null;
        }

        if (! in_array($status, ['pending', 'processing', 'success', 'failed', 'cancelled', 'refunded'], true)) {
            return null;
        }

        return new PaymentNotice(
            internalReference: (string) $reference,
            providerReference: (string) $transactionId,
            amount: $this->money($amount),
            currency: strtoupper((string) $currency),
            status: $status,
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transactionId(array $data): ?string
    {
        $id = $data['transaction_id'] ?? $data['id'] ?? $data['provider_reference'] ?? null;
        if (! is_scalar($id) || ! $this->safeId((string) $id)) {
            return null;
        }

        return (string) $id;
    }

    private function mapStatus(string $status): ?string
    {
        return match (strtolower(trim($status))) {
            'pending', 'initiated', 'created', 'new' => PaymentStatus::Pending->value,
            'processing', 'process', 'in_progress', 'in-progress' => PaymentStatus::Processing->value,
            'success', 'successful', 'succeeded', 'paid', 'completed', 'complete' => PaymentStatus::Success->value,
            'failed', 'failure', 'error', 'declined' => PaymentStatus::Failed->value,
            'cancelled', 'canceled', 'expired' => PaymentStatus::Cancelled->value,
            'refunded' => PaymentStatus::Refunded->value,
            default => null,
        };
    }

    private function safeId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/', $id);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function remember(Payment $payment, array $extra): Payment
    {
        $payment->forceFill([
            'metadata' => array_merge($payment->metadata ?? [], $extra),
        ])->save();

        return $payment->refresh();
    }

    private function trace(Payment $payment, string $action, bool $success, string $message): void
    {
        Log::info('unipay.payment', [
            'action' => $action,
            'payment_id' => $payment->id,
            'internal_reference' => $payment->internal_reference,
            'provider_reference' => $payment->provider_reference,
            'mode' => $this->mode(),
            'success' => $success,
            'message' => $message,
        ]);
    }

    private function redact(string $message): string
    {
        foreach ([config('services.unipay.key'), config('services.unipay.webhook_secret')] as $secret) {
            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '[masqué]', $message);
            }
        }

        return $message;
    }
}
