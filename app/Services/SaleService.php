<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Notifications\PlatformNotification;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SaleService
{
    public function __construct(
        private PaymentManager $payments,
        private VoucherGenerator $vouchers,
        private MikrotikService $mikrotik,
        private AuditLogger $audit,
    ) {}

    public function placeOrder(WifiZone $zone, Plan $plan, array $customer, string $provider, ?string $reference = null): Sale
    {
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
                'status' => PaymentStatus::Pending->value,
            ]);

            $this->payments->gateway($provider)->initiate($payment, [
                'transaction_reference' => $reference,
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
            $sale->load('payment', 'items.plan', 'wifiZone');
            $payment = $sale->payment;

            if ($payment && $payment->status !== PaymentStatus::Success->value) {
                $payment->forceFill([
                    'status' => PaymentStatus::Success->value,
                    'paid_at' => now(),
                ])->save();
            }

            $item = $sale->items->first();
            $voucher = $item?->voucher;

            if (! $voucher && $item) {
                $voucher = $this->vouchers->create($sale->wifiZone, $item->plan, 1, true)[0];
                $voucher->forceFill(['customer_id' => $sale->customer_id])->save();
                $item->forceFill(['voucher_id' => $voucher->id])->save();
            }

            $sale->forceFill(['status' => 'paid'])->save();
            $this->pushToRouter($voucher);
            $this->audit->record('sale.confirmed', $sale, null, ['status' => 'paid']);
            $sale->wifiZone->tenant->users()->whereHas('role', fn ($query) => $query->where('slug', 'entrepreneur'))->first()
                ?->notify(new PlatformNotification('ticket.generated', 'Ticket généré', 'Le ticket '.$voucher->username.' est prêt.'));

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

        $this->pushToRouter($voucher->fresh('plan'));
        $this->audit->record('sale.counter', $sale);

        return $sale->load('items.voucher');
    }

    private function pushToRouter(?Voucher $voucher): void
    {
        if (! $voucher) {
            return;
        }

        $router = $voucher->wifiZone->mikrotiks()->where('status', '!=', 'offline')->first()
            ?? $voucher->wifiZone->mikrotiks()->first();

        if (! $router) {
            $voucher->forceFill(['sync_status' => 'pending', 'sync_error' => 'Aucun MikroTik associé.'])->save();

            return;
        }

        try {
            $this->mikrotik->createUser($router, $voucher->load('plan'));
        } catch (Throwable $exception) {
            $voucher->forceFill([
                'mikrotik_id' => $router->id,
                'sync_status' => 'failed',
                'sync_error' => $exception->getMessage(),
            ])->save();
        }
    }
}
