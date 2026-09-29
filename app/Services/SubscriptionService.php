<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\SaasPlan;
use App\Models\Subscription;
use App\Notifications\PlatformNotification;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private PaymentManager $payments, private AuditLogger $audit) {}

    public function startCheckout(Subscription $current, SaasPlan $plan, string $provider, ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($current, $plan, $provider, $reference) {
            $amount = $plan->price ?? 0;

            $payment = Payment::create([
                'payable_type' => Subscription::class,
                'payable_id' => $current->id,
                'amount' => $amount,
                'currency' => $plan->currency ?: config('limete.currency'),
                'provider' => $provider,
                'status' => PaymentStatus::Pending->value,
                'metadata' => ['saas_plan_id' => $plan->id],
            ]);

            return $this->payments->gateway($provider)->initiate($payment, [
                'transaction_reference' => $reference,
            ]);
        });
    }

    public function confirm(Payment $payment): Subscription
    {
        return DB::transaction(function () use ($payment) {
            $payment->forceFill([
                'status' => PaymentStatus::Success->value,
                'paid_at' => now(),
            ])->save();

            $subscription = $payment->payable;
            $planId = $payment->metadata['saas_plan_id'] ?? $subscription->saas_plan_id;
            $plan = SaasPlan::findOrFail($planId);
            $start = now();

            $subscription->forceFill([
                'saas_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active->value,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addDays($plan->interval_days),
            ])->save();

            $this->audit->record('subscription.paid', $subscription, null, [
                'plan' => $plan->code,
                'ends_at' => $subscription->ends_at?->toDateTimeString(),
            ], $subscription->tenant_id);

            $subscription->tenant->users()->whereHas('role', fn ($query) => $query->where('slug', 'entrepreneur'))->first()
                ?->notify(new PlatformNotification('payment.succeeded', 'Paiement réussi', 'Votre abonnement '.$plan->name.' est actif.'));

            return $subscription->fresh('saasPlan');
        });
    }
}
