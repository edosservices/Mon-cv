<?php

use App\Enums\SubscriptionStatus;
use App\Enums\VoucherStatus;
use App\Models\Subscription;
use App\Models\Voucher;
use App\Notifications\PlatformNotification;
use App\Support\TenantManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('limete:expire-vouchers', function () {
    app(TenantManager::class)->bypass(true);
    $count = 0;
    Voucher::where('status', VoucherStatus::Active->value)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->orderBy('id')
        ->each(function (Voucher $voucher) use (&$count) {
            $voucher->forceFill(['status' => VoucherStatus::Expired->value])->save();
            $count++;
        });
    $this->info($count.' ticket(s) expiré(s).');
})->purpose('Marque les tickets arrivés à échéance');

Artisan::command('limete:sweep-subscriptions', function () {
    app(TenantManager::class)->bypass(true);
    Subscription::with('tenant.users.role')
        ->whereIn('status', [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value])
        ->whereNotNull('ends_at')
        ->where('ends_at', '<', now()->addDays(3))
        ->each(function (Subscription $subscription) {
            $owner = $subscription->tenant?->users->first(fn ($user) => $user->role?->slug === 'entrepreneur');
            if (! $owner) {
                return;
            }
            if ($subscription->ends_at->isPast()) {
                $subscription->forceFill(['status' => SubscriptionStatus::Expired->value])->save();
                $owner->notify(new PlatformNotification('subscription.expired', 'Abonnement expiré', 'Renouvelez votre abonnement pour continuer.'));
            } else {
                $owner->notify(new PlatformNotification('subscription.expiring', 'Abonnement bientôt expiré', 'Votre accès se termine le '.$subscription->ends_at->timezone(config('app.timezone'))->format('d/m/Y').'.'));
            }
        });
})->purpose('Préviens ou expire les abonnements SaaS');

Schedule::command('limete:expire-vouchers')->hourly();
Schedule::command('limete:sweep-subscriptions')->daily();
