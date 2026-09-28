<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\SaasPlan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantRegistrar
{
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['company'],
                'slug' => $this->slug($data['company']),
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'],
                'country' => $data['country'],
                'status' => 'active',
                'primary_color' => '#0b5ed7',
            ]);

            $role = Role::where('slug', UserRole::Entrepreneur->value)->firstOrFail();

            $user = User::create([
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => 'active',
            ]);

            $plan = SaasPlan::where('code', 'starter')->where('is_active', true)->first()
                ?? SaasPlan::where('is_active', true)->orderBy('id')->firstOrFail();

            Subscription::create([
                'tenant_id' => $tenant->id,
                'saas_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial->value,
                'starts_at' => now(),
                'ends_at' => now()->addDays((int) config('limete.trial_days')),
            ]);

            return $user;
        });
    }

    private function slug(string $company): string
    {
        $base = Str::slug($company) ?: 'wifi';
        $slug = $base;
        $i = 2;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
