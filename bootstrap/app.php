<?php

use App\Http\Middleware\EnsureApiFeature;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSubscription;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\ResolveCustomDomain;
use App\Http\Middleware\SetTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenant::class,
            'tenant.active' => EnsureTenantActive::class,
            'subscription' => EnsureSubscription::class,
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'api.feature' => EnsureApiFeature::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, SetTenant::class);
        $middleware->validateCsrfTokens(except: [
            'payments/*/webhook',
        ]);
        $middleware->web(append: [ResolveCustomDomain::class]);
        $middleware->throttleApi();
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
