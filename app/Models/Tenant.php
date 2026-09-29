<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'slogan', 'phone', 'whatsapp', 'email', 'address', 'city', 'country',
        'latitude', 'longitude', 'status', 'logo_path', 'primary_color', 'secondary_color',
        'button_color', 'ticket_style', 'custom_domain',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function logoUrl(): ?string
    {
        return $this->mediaUrl($this->logo_path);
    }

    public function brandColor(): string
    {
        return $this->validHex($this->primary_color) ?? '#0b5ed7';
    }

    public function secondaryColor(): string
    {
        return $this->validHex($this->secondary_color) ?? '#071e3d';
    }

    public function buttonColor(): string
    {
        return $this->validHex($this->button_color) ?? $this->brandColor();
    }

    private function validHex(mixed $value): ?string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) ? (string) $value : null;
    }

    private function mediaUrl(mixed $path): ?string
    {
        if (! filled($path) || str_contains((string) $path, '..')) {
            return null;
        }

        if (str_starts_with((string) $path, 'http://') || str_starts_with((string) $path, 'https://')) {
            return (string) $path;
        }

        return Storage::url((string) $path);
    }

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
