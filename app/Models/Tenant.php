<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'phone', 'email', 'address', 'city', 'country',
        'status', 'logo_path', 'primary_color', 'custom_domain',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function wifiZones(): HasMany
    {
        return $this->hasMany(WifiZone::class);
    }

    public function mikrotiks(): HasMany
    {
        return $this->hasMany(Mikrotik::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function subscriptionAllowsAccess(): bool
    {
        $subscription = $this->currentSubscription;

        if (! $subscription) {
            return false;
        }

        if ($subscription->ends_at && $subscription->ends_at->isPast() && $subscription->status !== SubscriptionStatus::Cancelled->value) {
            return false;
        }

        return SubscriptionStatus::from($subscription->status)->allowsAccess();
    }
}
