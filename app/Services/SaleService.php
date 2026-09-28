<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Jobs\SyncHotspotUser;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Notifications\PlatformNotification;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleService
{
    public function __construct(
        private PaymentManager $payments,
        private VoucherGenerator $vouchers,
        private AuditLogger $audit,
    ) {}

    public function placeOrder(WifiZone $zone, Plan $plan, array $customer, string $provider, ?string $reference = null): Sale
    {
        if (! $this->payments->enabled($provider)) {
            throw new \RuntimeException('Ce moyen de paiement est désactivé.');
        }

        return DB::transaction(function () use ($zone, $plan, $customer, $provider, $reference) {
            $buyer = null;
            if (filled($customer['phone'] ?? null) || filled($customer['name'] ?? null)) {
                $buyer = Customer::query()
                    ->when(filled($customer['phone'] ?? null), fn ($query) => $query->where('phone', $customer['phone']))
                    ->first();

                if (! $buyer) {
                    $buyer = Customer::create([
                        'name' => $customer['name'] ?? null,
                        'phone' => $customer['phone'] ?? null,
                        'email' => $customer['email'] ?? null,
                    ]);
                }
            }

            $sale = Sale::create([
                'wifi_zone_id' => $zone->id,
                'customer_id' => $buyer?->id,
                'public_token' => Str::random(40),
                'total_amount' => $plan->price ?? 0,
                'currency' => $plan->currency ?: config('limete.currency'),
                'status' => 'pending',
                'channel' => 'public',
            ]);

            $payment = Payment::create([
                'payable_type' => Sale::class,
                'payable_id' => $sale->id,
                'amount' => $sale->total_amount,
                'currency' => $sale->currency,
                'provider' => $provider,
                'internal_reference' => $this->internalReference(),
                'status' => PaymentStatus::Pending->value,
            ]);

            $this->payments->gateway($provider)->createPayment($payment, [
                'transaction_reference' => $reference,
            ]);
            $this->audit->record('payment.created', $payment, null, [
                'provider' => $provider,
                'internal_reference' => $payment->internal_reference,
                'amount' => number_format((float) $sale->total_amount, 2, '.', ''),
                'currency' => $sale->currency,
            ]);

            $sale->forceFill(['payment_id' => $payment->id])->save();
            $sale->items()->create([
                'plan_id' => $plan->id,
                'amount' => $sale->total_amount,
            ]);

            return $sale->load('payment', 'items');
        });
    }

    public function confirm(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $sale->load('payment', 'items.plan', 'items.voucher', 'wifiZone');
            $item = $sale->items->first();

            if ($sale->status === 'paid' && $item?->voucher) {
                return $sale->fresh(['items.voucher', 'payment', 'customer']);
            }

            $payment = $sale->payment;
            if ($payment && $payment->status !== PaymentStatus::Success->value) {
                $payment->transitionTo(PaymentStatus::Success);
                $payment->save();
                $this->audit->record('payment.success', $payment, null, [
                    'provider' => $payment->provider,
                    'internal_reference' => $payment->internal_reference,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'currency' => $payment->currency,
                ]);
            }

            $voucher = $item?->voucher;
            if (! $voucher && $item) {
                $voucher = $this->vouchers->create($sale->wifiZone, $item->plan, 1, true)[0];
                $voucher->forceFill(['customer_id' => $sale->customer_id])->save();
                $item->forceFill(['voucher_id' => $voucher->id])->save();
                $this->audit->record('voucher.activated', $voucher, null, [
                    'activated_at' => $voucher->activated_at?->toIso8601String(),
                    'expires_at' => $voucher->expires_at?->toIso8601String(),
                ]);
                dispatch_sync(new SyncHotspotUser($voucher->id));
                $voucher = $voucher->fresh();
                $this->audit->record(
                    $voucher->sync_status === 'synced' ? 'voucher.sync_success' : 'voucher.sync_failed',
                    $voucher,
                    null,
                    ['sync_status' => $voucher->sync_status, 'sync_error' => $voucher->sync_error],
                );
            }

            $sale->forceFill(['status' => 'paid'])->save();
            $this->audit->record('sale.confirmed', $sale, null, ['status' => 'paid']);
            if ($voucher) {
                $sale->wifiZone->tenant->users()->whereHas('role', fn ($query) => $query->where('slug', 'entrepreneur'))->first()
                    ?->notify(new PlatformNotification('ticket.generated', 'Ticket généré', 'Le ticket '.$voucher->username.' est prêt.'));
            }

            return $sale->fresh(['items.voucher', 'payment', 'customer']);
        });
    }

    public function sellExisting(Voucher $voucher, ?Customer $customer = null): Sale
    {
        $voucher = $this->vouchers->activate($voucher);
        if ($customer) {
            $voucher->forceFill(['customer_id' => $customer->id])->save();
        }

        $sale = Sale::create([
            'wifi_zone_id' => $voucher->wifi_zone_id,
            'customer_id' => $customer?->id,
            'public_token' => Str::random(40),
            'total_amount' => $voucher->price_amount ?? $voucher->plan->price ?? 0,
            'currency' => $voucher->currency,
            'status' => 'paid',
            'channel' => 'counter',
        ]);

        $payment = Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'amount' => $sale->total_amount,
            'currency' => $sale->currency,
            'provider' => 'manual',
            'internal_reference' => $this->internalReference(),
            'transaction_reference' => 'COMPTOIR-'.$sale->id,
            'status' => PaymentStatus::Success->value,
            'paid_at' => now(),
        ]);

        $sale->forceFill(['payment_id' => $payment->id])->save();
        $sale->items()->create([
            'voucher_id' => $voucher->id,
            'plan_id' => $voucher->plan_id,
            'amount' => $sale->total_amount,
        ]);

        dispatch_sync(new SyncHotspotUser($voucher->id));
        $this->audit->record('sale.counter', $sale);

        return $sale->load('items.voucher');
    }

    private function internalReference(): string
    {
        do {
            $reference = 'PAY-'.Str::upper(Str::random(10));
        } while (Payment::withoutGlobalScope('tenant')->where('internal_reference', $reference)->exists());

        return $reference;
    }
}
