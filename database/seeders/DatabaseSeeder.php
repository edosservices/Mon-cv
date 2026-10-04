<?php

namespace Database\Seeders;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\VoucherStatus;
use App\Models\Customer;
use App\Models\Mikrotik;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SaasPlan;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect([
            UserRole::SuperAdmin->value => 'Super admin',
            UserRole::Entrepreneur->value => 'Entrepreneur',
            UserRole::Staff->value => 'Collaborateur',
            UserRole::Client->value => 'Client',
        ])->mapWithKeys(fn ($name, $slug) => [$slug => Role::firstOrCreate(['slug' => $slug], ['name' => $name])]);

        $permissions = [
            'zones.manage' => 'Gérer les WiFi Zones',
            'mikrotiks.manage' => 'Gérer les MikroTik',
            'plans.manage' => 'Gérer les forfaits',
            'vouchers.manage' => 'Gérer les tickets',
            'customers.manage' => 'Gérer les clients',
            'sales.view' => 'Voir les ventes',
            'sales.confirm' => 'Confirmer une vente',
            'sessions.view' => 'Voir les sessions',
            'sessions.disconnect' => 'Déconnecter un utilisateur',
            'statistics.view' => 'Voir les statistiques',
            'subscription.manage' => 'Gérer l’abonnement',
            'staff.manage' => 'Gérer l’équipe',
            'settings.manage' => 'Gérer les paramètres',
        ];

        foreach ($permissions as $slug => $name) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        foreach ([
            ['code' => 'starter', 'name' => 'STARTER', 'price' => null, 'currency' => 'CDF', 'max_zones' => 1, 'max_mikrotiks' => 1, 'features' => ['vouchers' => true, 'basic_statistics' => true, 'advanced_statistics' => false, 'user_management' => false, 'api' => false]],
            ['code' => 'business', 'name' => 'BUSINESS', 'price' => 4.90, 'currency' => 'USD', 'max_zones' => null, 'max_mikrotiks' => null, 'features' => ['vouchers' => true, 'basic_statistics' => true, 'advanced_statistics' => true, 'user_management' => true, 'api' => false]],
            ['code' => 'pro', 'name' => 'PRO', 'price' => 8.90, 'currency' => 'USD', 'max_zones' => null, 'max_mikrotiks' => null, 'features' => ['vouchers' => true, 'basic_statistics' => true, 'advanced_statistics' => true, 'user_management' => true, 'api' => true]],
        ] as $plan) {
            SaasPlan::firstOrCreate(['code' => $plan['code']], [
                'name' => $plan['name'],
                'price' => $plan['price'],
                'currency' => $plan['currency'],
                'interval_days' => 30,
                'max_zones' => $plan['max_zones'],
                'max_mikrotiks' => $plan['max_mikrotiks'],
                'features' => $plan['features'],
                'is_active' => true,
            ]);
        }

        User::firstOrCreate(['email' => env('SUPER_ADMIN_EMAIL', 'admin@limetewifi.local')], [
            'name' => 'Super Admin',
            'role_id' => $roles[UserRole::SuperAdmin->value]->id,
            'password' => env('SUPER_ADMIN_PASSWORD', 'local-admin-change-me'),
            'status' => 'active',
            'phone' => null,
        ]);

        if (! app()->environment('local')) {
            return;
        }

        $tenant = Tenant::firstOrCreate(['slug' => 'limete-wifi'], [
            'name' => 'LIMETE WIFI',
            'phone' => '+243860392283',
            'email' => 'demo@limetewifi.local',
            'address' => 'Kingabwa',
            'city' => 'Kinshasa',
            'country' => 'République démocratique du Congo',
            'status' => 'active',
            'primary_color' => '#0b5ed7',
        ]);

        User::firstOrCreate(['email' => 'demo@limetewifi.local'], [
            'tenant_id' => $tenant->id,
            'role_id' => $roles[UserRole::Entrepreneur->value]->id,
            'name' => 'Jean Dupont',
            'phone' => '+243860392283',
            'password' => env('DEMO_PASSWORD', 'local-demo-change-me'),
            'status' => 'active',
        ]);

        Subscription::firstOrCreate(['tenant_id' => $tenant->id], [
            'saas_plan_id' => SaasPlan::where('code', 'business')->first()->id,
            'status' => SubscriptionStatus::Trial->value,
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) config('limete.trial_days')),
        ]);

        app(TenantManager::class)->set($tenant->id);

        $zone = WifiZone::firstOrCreate(['slug' => 'limete'], [
            'name' => 'Limete Kingabwa',
            'location' => 'Kingabwa, Kinshasa',
            'description' => 'Zone WiFi publique',
            'phone' => '+243860392283',
            'whatsapp' => '+243860392283',
            'primary_color' => '#0b5ed7',
            'status' => 'active',
        ]);

        Mikrotik::firstOrCreate(['host' => '192.0.2.55', 'tenant_id' => $tenant->id], [
            'wifi_zone_id' => $zone->id,
            'name' => 'Routeur de démonstration',
            'api_port' => 8728,
            'username' => 'demo',
            'password' => 'not-a-real-router-password',
            'status' => 'offline',
            'last_error' => 'Routeur fictif, aucune connexion réelle.',
        ]);

        $definitions = [
            ['1 HEURE', 3600, 500],
            ['24 HEURES', 86400, 1000],
            ['48 HEURES', 172800, 2000],
            ['7 JOURS', 604800, 5000],
            ['30 JOURS', 2592000, 15000],
        ];

        foreach ($definitions as [$name, $seconds, $price]) {
            Plan::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name], [
                'wifi_zone_id' => $zone->id,
                'duration_seconds' => $seconds,
                'price' => $price,
                'currency' => 'CDF',
                'mikrotik_profile' => 'default',
                'unlimited_data' => true,
                'status' => 'active',
                'description' => 'Exemple de forfait modifiable.',
            ]);
        }

        if (Voucher::count() === 0) {
            $plan = Plan::where('name', '24 HEURES')->first();
            $customer = Customer::create(['name' => 'Amina', 'phone' => '+243810000001']);
            $voucher = Voucher::create([
                'wifi_zone_id' => $zone->id,
                'plan_id' => $plan->id,
                'customer_id' => $customer->id,
                'public_token' => Str::random(40),
                'username' => 'LW8F42',
                'password' => '7391',
                'status' => VoucherStatus::Active->value,
                'activated_at' => now()->subHour(),
                'expires_at' => now()->addDay(),
                'price_amount' => 1000,
                'currency' => 'CDF',
                'sync_status' => 'pending',
            ]);
            $sale = Sale::create([
                'wifi_zone_id' => $zone->id,
                'customer_id' => $customer->id,
                'public_token' => Str::random(40),
                'total_amount' => 1000,
                'currency' => 'CDF',
                'status' => 'paid',
                'channel' => 'counter',
            ]);
            $sale->items()->create(['voucher_id' => $voucher->id, 'plan_id' => $plan->id, 'amount' => 1000]);
        }

        app(TenantManager::class)->forget();
    }
}
