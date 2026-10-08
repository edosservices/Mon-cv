<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Sale;
use App\Services\AuditLogger;
use App\Services\SaleService;
use App\Support\TenantManager;
use Illuminate\Support\Facades\DB;

class PaymentSettlement
{
    public function __construct(
        private SaleService $sales,
        private AuditLogger $audit,
    ) {}

    public function apply(string $provider, PaymentNotice $notice): string
    {
        return DB::transaction(function () use ($provider, $notice) {
            $payment = Payment::withoutGlobalScope('tenant')
                ->where('internal_reference', $notice->internalReference)
                ->lockForUpdate()
                ->first();

            if (! $payment || $payment->provider !== $provider) {
                return 'missing';
            }

            app(TenantManager::class)->set($payment->tenant_id);

            if (filled($payment->provider_reference) && $payment->provider_reference !== $notice->providerReference) {
                $this->store($payment, 'rejected', $notice);

                return 'rejected';
            }

            if (filled($notice->providerReference)) {
                $taken = Payment::withoutGlobalScope('tenant')
                    ->where('provider_reference', $notice->providerReference)
                    ->where('id', '!=', $payment->id)
                    ->exists();

                if ($taken) {
                    $this->store($payment, 'rejected', $notice);

                    return 'rejected';
                }
            }

            if (! $this->sameAmount($payment->amount, $notice->amount) || strtoupper((string) $payment->currency) !== $notice->currency) {
                $this->store($payment, 'rejected', $notice);

                return 'rejected';
            }

            if (! $this->saleMatches($payment)) {
                $this->store($payment, 'rejected', $notice);

                return 'rejected';
            }

            $current = PaymentStatus::from($payment->status);
            $incoming = PaymentStatus::from($notice->status);

            if ($current === PaymentStatus::Success && $incoming === PaymentStatus::Success) {
                $this->store($payment, 'duplicate', $notice);

                return 'duplicate';
            }

            if (! $current->canTransitionTo($incoming)) {
                $this->store($payment, 'ignored', $notice);

                return 'duplicate';
            }

            $payment->provider_reference = $payment->provider_reference ?: $notice->providerReference;
            $payment->transitionTo($incoming);
            $payment->save();

            $this->audit->record('payment.'.$incoming->value, $payment, null, [
                'provider' => $payment->provider,
                'internal_reference' => $payment->internal_reference,
                'provider_reference' => $payment->provider_reference,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => $payment->currency,
            ]);
            $this->store($payment, 'webhook', $notice);

            if ($incoming === PaymentStatus::Success && $payment->payable_type === Sale::class) {
                $sale = Sale::query()->find($payment->payable_id);
                if ($sale) {
                    $this->sales->confirm($sale);
                }
            }

            return 'accepted';
        });
    }

    private function saleMatches(Payment $payment): bool
    {
        if ($payment->payable_type !== Sale::class) {
            return true;
        }

        $sale = Sale::withoutGlobalScope('tenant')->with('items')->find($payment->payable_id);
        if (! $sale || (int) $sale->tenant_id !== (int) $payment->tenant_id) {
            return false;
        }

        $meta = $payment->metadata ?? [];
        if (isset($meta['wifi_zone_id']) && (int) $meta['wifi_zone_id'] !== (int) $sale->wifi_zone_id) {
            return false;
        }

        if (isset($meta['plan_id'])) {
            $planId = $sale->items->first()?->plan_id;
            if ($planId === null || (int) $meta['plan_id'] !== (int) $planId) {
                return false;
            }
        }

        if (! $this->sameAmount($sale->total_amount, $this->money($payment->amount)) || strtoupper((string) $sale->currency) !== strtoupper((string) $payment->currency)) {
            return false;
        }

        return true;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function sameAmount(mixed $expected, string $given): bool
    {
        return number_format((float) $expected, 2, '.', '') === number_format((float) $given, 2, '.', '');
    }

    private function store(Payment $payment, string $type, PaymentNotice $notice): void
    {
        PaymentEvent::create([
            'tenant_id' => $payment->tenant_id,
            'payment_id' => $payment->id,
            'type' => $type,
            'payload' => $this->scrub($notice->raw),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function scrub(array $values): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->sensitive($key)) {
                $clean[$key] = '[masqué]';

                continue;
            }
            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }

    private function sensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (['password', 'secret', 'token', 'api_key', 'authorization', 'cvv', 'pin', 'client_secret'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
