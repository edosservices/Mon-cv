<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\PhoneNumbers;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class IkeePayGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'ikeepay';
    }

    public function initiate(Payment $payment, array $context = []): Payment
    {
        return $this->createPayment($payment, $context);
    }

    public function createPayment(Payment $payment, array $context = []): Payment
    {
        $operator = strtoupper(trim((string) ($context['operator'] ?? '')));
        if ($operator === '') {
            return $this->prepareInline($payment, $context);
        }

        return $this->createPayin($payment, $context, $operator);
    }

    public function checkPayment(Payment $payment): Payment
    {
        return $this->verifyTransaction($payment);
    }

    public function verifyTransaction(Payment $payment): Payment
    {
        if (! $this->configured()) {
            return $payment->refresh();
        }

        $reference = filled($payment->provider_reference)
            ? (string) $payment->provider_reference
            : (string) $payment->internal_reference;

        if ($reference === '') {
            return $payment->refresh();
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->acceptJson()
                ->timeout(20)
                ->get($this->endpoint('/h2h-verify/'.rawurlencode($reference)));
        } catch (ConnectionException) {
            return $payment->refresh();
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return $payment->refresh();
        }

        $body = $response->json();
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $status = $data['status'] ?? null;
        $external = $data['external_reference'] ?? null;

        if (! in_array($status, ['completed', 'pending', 'failed'], true)) {
            return $payment->refresh();
        }

        if (! is_string($external) || $external !== $payment->internal_reference) {
            return $payment->refresh();
        }

        if (! isset($data['amount'], $data['currency']) || ! is_numeric($data['amount']) || ! is_string($data['currency'])) {
            return $payment->refresh();
        }

        $providerReference = $data['provider_reference'] ?? '';
        if (! is_string($providerReference)) {
            $providerReference = '';
        }

        app(PaymentSettlement::class)->apply($this->provider(), new PaymentNotice(
            internalReference: $external,
            providerReference: $providerReference,
            amount: $this->money($data['amount']),
            currency: strtoupper($data['currency']),
            status: $this->localStatus($status),
            raw: [
                'source' => 'h2h-verify',
                'status' => $status,
                'provider_reference' => $providerReference,
                'operator' => is_string($data['operator'] ?? null) ? $data['operator'] : null,
            ],
        ));

        return $payment->refresh();
    }

    public function verifyPayment(string $rawBody, ?string $signature): PaymentNotice
    {
        // La documentation iKeePay ne fournit aucune signature webhook.
        $payload = json_decode($rawBody, true);
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;
        $event = is_array($payload) ? ($payload['event'] ?? null) : null;

        if (! in_array($event, ['transaction.created', 'transaction.updated'], true) || ! is_array($data)) {
            throw new \InvalidArgumentException('Notification iKeePay invalide.');
        }

        if (($data['type'] ?? null) !== 'payin') {
            throw new \InvalidArgumentException('Notification iKeePay invalide.');
        }

        $reference = $data['external_reference'] ?? null;
        $status = $data['status'] ?? null;
        $currency = $data['currency'] ?? null;

        if (! is_string($reference) || $reference === '' || ! is_string($currency) || $currency === '') {
            throw new \InvalidArgumentException('Notification iKeePay invalide.');
        }

        if (! in_array($status, ['completed', 'pending', 'failed'], true)) {
            throw new \InvalidArgumentException('Notification iKeePay invalide.');
        }

        if (! isset($data['amount']) || ! is_numeric($data['amount'])) {
            throw new \InvalidArgumentException('Notification iKeePay invalide.');
        }

        $providerReference = $data['provider_reference'] ?? '';
        if (! is_string($providerReference)) {
            $providerReference = '';
        }

        return new PaymentNotice(
            internalReference: $reference,
            providerReference: $providerReference,
            amount: $this->money($data['amount']),
            currency: strtoupper($currency),
            status: $this->localStatus($status),
            raw: [
                'event' => $event,
                'type' => 'payin',
                'status' => $status,
                'provider_reference' => $providerReference,
                'operator' => is_string($data['operator'] ?? null) ? $data['operator'] : null,
                'country' => is_string($data['country'] ?? null) ? $data['country'] : null,
            ],
        );
    }

    public function refundPayment(Payment $payment, array $context = []): Payment
    {
        return $payment->refresh();
    }

    private function prepareInline(Payment $payment, array $context): Payment
    {
        $payment->forceFill([
            'provider' => $this->provider(),
            'status' => PaymentStatus::Pending->value,
            'paid_at' => null,
            'metadata' => array_merge($payment->metadata ?? [], [
                'flow' => 'inline',
                'email' => $context['customer_email'] ?? null,
                'note' => 'Checkout iKeePay ouvert. Le paiement reste en attente jusqu’à confirmation serveur.',
            ]),
        ])->save();

        return $payment->refresh();
    }

    private function createPayin(Payment $payment, array $context, string $operator): Payment
    {
        $country = strtoupper(trim((string) ($context['country'] ?? '')));
        $phone = PhoneNumbers::digits($context['customer_phone'] ?? null);
        $email = (string) ($context['customer_email'] ?? '');
        $catalog = app(IkeePayCatalog::class);

        if (! $catalog->allows($country, $operator) || $phone === '' || $email === '') {
            return $this->markFailed($payment, 'Les informations du paiement mobile sont incomplètes. Aucun ticket n’a été créé.');
        }

        if (! $this->configured()) {
            return $this->keepPending($payment, $context, $country, $operator, 'La clé iKeePay n’est pas configurée. Aucun ticket n’a été créé.');
        }

        $payload = [
            'amount' => $this->amountValue($payment->amount),
            'currency' => strtoupper((string) $payment->currency),
            'country' => $country,
            'phoneNumber' => $phone,
            'operator' => $operator,
            'external_reference' => (string) $payment->internal_reference,
            'customer_email' => $email,
        ];

        $otp = trim((string) ($context['otp'] ?? ''));
        if ($otp !== '') {
            $payload['otp'] = $otp;
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->asJson()
                ->acceptJson()
                ->timeout(20)
                ->post($this->endpoint('/h2h-payin'), $payload);
        } catch (ConnectionException) {
            return $this->keepPending($payment, $context, $country, $operator, 'iKeePay est indisponible. Aucun ticket n’a été créé.');
        }

        $body = is_array($response->json()) ? $response->json() : [];
        $fields = $this->documentedFields($body);

        if (($fields['status'] ?? null) === 'failed' || $response->clientError()) {
            return $this->markFailed($payment, 'iKeePay n’a pas accepté le paiement. Aucun ticket n’a été créé.');
        }

        if (! $response->successful()) {
            return $this->keepPending($payment, $context, $country, $operator, 'iKeePay n’a pas répondu. Le paiement reste en attente.');
        }

        if ($fields['provider_reference'] !== null && Payment::withoutGlobalScope('tenant')->where('provider_reference', $fields['provider_reference'])->where('id', '!=', $payment->id)->exists()) {
            return $this->markFailed($payment, 'iKeePay a renvoyé une référence déjà utilisée. Aucun ticket n’a été créé.');
        }

        $metadata = array_merge($payment->metadata ?? [], [
            'flow' => 'h2h',
            'email' => $email,
            'country' => $country,
            'operator' => $operator,
            'phone_number' => $phone,
            'remote_status' => $fields['status'] ?? 'pending',
            'note' => 'Paiement envoyé à iKeePay. Le ticket sera créé après confirmation.',
        ]);

        if ($fields['payment_link'] !== null) {
            $metadata['payment_link'] = $fields['payment_link'];
        }

        $payment->forceFill([
            'provider' => $this->provider(),
            'status' => PaymentStatus::Pending->value,
            'paid_at' => null,
            'provider_reference' => $fields['provider_reference'],
            'metadata' => $metadata,
        ])->save();

        return $payment->refresh();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{provider_reference: ?string, payment_link: ?string, status: ?string}
     */
    private function documentedFields(array $body): array
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return [
            'provider_reference' => $this->text($data['provider_reference'] ?? $body['provider_reference'] ?? null),
            'payment_link' => $this->httpsLink($data['payment_link'] ?? $body['payment_link'] ?? null),
            'status' => $this->remoteStatus($data['status'] ?? null) ?? $this->remoteStatus($body['status'] ?? null),
        ];
    }

    private function markFailed(Payment $payment, string $note): Payment
    {
        $payment->forceFill([
            'provider' => $this->provider(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'flow' => 'h2h',
                'remote_status' => 'failed',
                'note' => $note,
            ]),
        ]);
        $payment->transitionTo(PaymentStatus::Failed);
        $payment->save();

        return $payment->refresh();
    }

    private function keepPending(Payment $payment, array $context, string $country, string $operator, string $note): Payment
    {
        $payment->forceFill([
            'provider' => $this->provider(),
            'status' => PaymentStatus::Pending->value,
            'paid_at' => null,
            'metadata' => array_merge($payment->metadata ?? [], [
                'flow' => 'h2h',
                'email' => $context['customer_email'] ?? null,
                'country' => $country,
                'operator' => $operator,
                'remote_status' => 'pending',
                'note' => $note,
            ]),
        ])->save();

        return $payment->refresh();
    }

    private function localStatus(string $status): string
    {
        return match ($status) {
            'completed' => PaymentStatus::Success->value,
            'failed' => PaymentStatus::Failed->value,
            default => PaymentStatus::Pending->value,
        };
    }

    private function remoteStatus(mixed $status): ?string
    {
        return in_array($status, ['completed', 'pending', 'failed'], true) ? $status : null;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function httpsLink(mixed $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, 'https://')) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function amountValue(mixed $amount): int|float
    {
        $value = round((float) $amount, 2);

        if (abs($value - round($value)) < 0.001) {
            return (int) round($value);
        }

        return $value;
    }

    private function configured(): bool
    {
        return filled(config('services.ikeepay.secret_key'));
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'x-api-key' => (string) config('services.ikeepay.secret_key'),
            'Accept' => 'application/json',
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.ikeepay.base_url'), '/').$path;
    }
}
