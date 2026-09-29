<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $slug = $request->user()?->roleSlug();

        if (! $slug || ! in_array($slug, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
