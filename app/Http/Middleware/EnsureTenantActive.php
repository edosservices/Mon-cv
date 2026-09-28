<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! $user->isSuperAdmin()) {
            if ($user->status !== 'active') {
                abort(403, 'Compte suspendu.');
            }

            $tenant = $user->tenant;
            if (! $tenant || $tenant->trashed() || $tenant->isSuspended()) {
                abort(403, 'Entreprise suspendue.');
            }
        }

        return $next($request);
    }
}
