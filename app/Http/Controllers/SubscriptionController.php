<?php

namespace App\Http\Controllers;

use App\Models\SaasPlan;
use App\Services\SubscriptionCatalog;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(SubscriptionCatalog $catalog)
    {
        $subscription = $catalog->current();

        return view('subscription.show', [
            'subscription' => $subscription,
            'plans' => $catalog->activePlans(),
            'catalog' => $catalog,
            'usage' => $catalog->usage(),
            'expired' => $catalog->isExpired($subscription),
            'daysRemaining' => $catalog->daysRemaining($subscription),
        ]);
    }

    public function checkout(Request $request, SubscriptionService $service)
    {
        $data = $request->validate([
            'saas_plan_id' => ['required', 'exists:saas_plans,id'],
            'provider' => ['required', 'in:manual,airtel_money,orange_money,mpesa,card,unipay'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
        ]);

        $subscription = auth()->user()->tenant->currentSubscription;
        $plan = SaasPlan::where('is_active', true)->findOrFail($data['saas_plan_id']);

        try {
            $payment = $service->startCheckout($subscription, $plan, $data['provider'], $data['transaction_reference'] ?? null);
        } catch (\RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        $provider = config('limete.payment_providers.'.$payment->provider, $payment->provider);
        $reference = trim((string) $payment->transaction_reference);
        $detail = $reference !== '' ? $reference : $provider;

        return redirect()->route('subscription.show')->with('status', 'Paiement '.$detail.' enregistré. Il sera confirmé après réception.');
    }
}
