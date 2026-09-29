<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\WifiZone;
use Closure;
use Illuminate\Http\Request;

class ResolveCustomDomain
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $next($request);
        }

        $tenant = Tenant::query()->where('custom_domain', $host)->where('status', 'active')->first();

        if ($tenant && $request->is('/')) {
            $zone = WifiZone::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->first();

            if ($zone) {
                return redirect('/wifi/'.$zone->slug);
            }
        }

        return $next($request);
    }
}
