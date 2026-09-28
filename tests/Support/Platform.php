<?php

namespace Tests\Support;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Mikrotik;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SaasPlan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WifiZone;
use App\Support\TenantManager;
use Database\Seeders\DatabaseSeeder;

class Platform
{
    public static function seed(): void
    {
        (new DatabaseSeeder)->run();
    }

    public static function entrepreneur(string $company, string $email, string $plan = 'starter'): User
    {
        self::seed();
        $tenant = Tenant::create([
            'name' => $company,
            'slug' => str($company)->slug().'-'.str()->lower(str()->random(4)),
            'phone' => '+243800000000',
            'email' => $email,
            'city' => 'Kinshasa',
            'country' => 'RDC',
            'status' => 'active',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'role_id' => Role::where('slug', UserRole::Entrepreneur->value)->first()->id,
            'name' => $company,
            'email' => $email,
            'phone' => '+243800000000',
            'password' => 'password-ok',
            'status' => 'active',
        ]);

        Subscription::create([
            'tenant_id' => $tenant->id,
            'saas_plan_id' => SaasPlan::where('code', $plan)->first()->id,
            'status' => SubscriptionStatus::Trial->value,
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
        ]);

        return $user;
    }

    public static function zone(User $user, string $name = 'Zone test'): WifiZone
    {
        app(TenantManager::class)->set($user->tenant_id);

        return WifiZone::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(4)),
            'location' => 'Kinshasa',
            'status' => 'active',
            'whatsapp' => '+243860392283',
            'primary_color' => '#0b5ed7',
        ]);
    }

    public static function router(User $user, WifiZone $zone): Mikrotik
    {
        app(TenantManager::class)->set($user->tenant_id);

        return Mikrotik::create([
            'wifi_zone_id' => $zone->id,
            'name' => 'Routeur '.$zone->name,
            'host' => '192.0.2.10',
            'api_port' => 8728,
            'username' => 'demo',
            'password' => 'secret-router',
            'status' => 'unknown',
        ]);
    }

    public static function plan(User $user, WifiZone $zone): Plan
    {
        app(TenantManager::class)->set($user->tenant_id);

        return Plan::create([
            'wifi_zone_id' => $zone->id,
            'name' => '24 HEURES',
            'duration_seconds' => 86400,
            'price' => 1000,
            'currency' => 'CDF',
            'unlimited_data' => true,
            'status' => 'active',
        ]);
    }
}
