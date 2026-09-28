<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiFeature
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        $plan = $user?->tenant?->currentSubscription?->saasPlan;

        if (! $plan || ! $plan->allows('api')) {
            return response()->json([
                'message' => 'L’API est incluse dans le plan PRO.',
            ], 403);
        }

        return $next($request);
    }
}
