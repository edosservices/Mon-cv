<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Mikrotik;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiSession;
use App\Models\WifiZone;
use App\Policies\TenantOwnedPolicy;
use App\Services\BusinessOnboarding;
use App\Services\Sms\ArraySmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Support\PhoneNumbers;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\Mikrotik\RouterOsClient;
use App\Services\Production\EndpointProbe;
use App\Services\Production\SocketEndpointProbe;
use App\Support\Correlation;
use App\Support\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->app->singleton(Correlation::class);
        $this->app->bind(HotspotRouter::class, RouterOsClient::class);
        $this->app->bind(EndpointProbe::class, SocketEndpointProbe::class);
        $this->app->singleton(SmsSender::class, function ($app) {
            return $app->environment('testing') ? new ArraySmsSender : new LogSmsSender;
        });
    }

    public function boot(): void
    {
        foreach ([WifiZone::class, Mikrotik::class, Plan::class, Voucher::class, Customer::class, Sale::class, Payment::class, WifiSession::class] as $model) {
            Gate::policy($model, TenantOwnedPolicy::class);
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('client-login', function (Request $request) {
            $phone = PhoneNumbers::normalize((string) $request->input('phone')) ?? 'guest';

            return Limit::perMinute(5)->by($phone.'|'.$request->ip());
        });

        RateLimiter::for('client-register', function (Request $request) {
            $phone = PhoneNumbers::normalize((string) $request->input('phone')) ?? 'guest';

            return Limit::perMinute(5)->by($phone.'|'.$request->ip());
        });

        RateLimiter::for('client-recover', function (Request $request) {
            $phone = PhoneNumbers::normalize((string) $request->input('phone')) ?? 'guest';

            return Limit::perMinute(5)->by($phone.'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        View::composer(['layouts.app', 'layouts.business'], function ($view): void {
            $tenant = auth()->user()?->tenant;
            if (! $tenant) {
                $view->with('onboardingSteps', [])->with('onboardingNext', null);

                return;
            }

            $guide = app(BusinessOnboarding::class);
            $steps = $guide->steps($tenant);
            $view->with('onboardingSteps', $steps)->with('onboardingNext', $guide->next($steps));
        });
    }
}
