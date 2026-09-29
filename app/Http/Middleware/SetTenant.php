<?php

namespace App\Http\Middleware;

use App\Support\TenantManager;
use Closure;
use Illuminate\Http\Request;

class SetTenant
{
    public function __construct(private TenantManager $tenants) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user?->isSuperAdmin() && ($request->is('admin') || $request->is('admin/*'))) {
            $this->tenants->bypass(true);
        } elseif ($user?->tenant_id) {
            $this->tenants->set($user->tenant_id);
        }

        return $next($request);
    }
}
