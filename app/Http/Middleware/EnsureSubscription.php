<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $subscription = $user->tenant?->currentSubscription;
        if ($subscription && $subscription->ends_at && $subscription->ends_at->isPast() && $subscription->status !== 'cancelled') {
            $subscription->forceFill(['status' => 'expired'])->save();
            $user->tenant->unsetRelation('currentSubscription');
        }

        if (! $user->tenant?->subscriptionAllowsAccess()) {
            return redirect()->route('subscription.show')->with('warning', 'Votre abonnement ne permet plus d’utiliser la plateforme.');
        }

        return $next($request);
    }
}
