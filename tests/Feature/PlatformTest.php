<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Support\Platform;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_business_registration_page_is_reachable(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Créer mon entreprise')
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('register').'"', false);
    }

    public function test_registration_creates_the_catalog_when_it_is_missing(): void
    {
        $this->assertSame(0, Role::query()->count());

        $this->post('/register', [
            'name' => 'Aline Kabila',
            'company' => 'Aline Wifi',
            'phone' => '+243810000077',
            'email' => 'aline@example.com',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
            'city' => 'Kinshasa',
            'country' => 'RDC',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertNotNull(Role::query()->where('slug', 'entrepreneur')->first());
        $this->assertDatabaseHas('tenants', ['name' => 'Aline Wifi']);
    }

    public function test_registration_creates_tenant_user_and_trial(): void
    {
        Platform::seed();

        $this->post('/register', [
            'name' => 'Jean Dupont',
            'company' => 'Jean Wifi Zone',
            'phone' => '+243810000099',
            'email' => 'jean@example.com',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
            'address' => 'Kingabwa',
            'city' => 'Kinshasa',
            'country' => 'RDC',
        ])->assertRedirect('/dashboard');

        $tenant = Tenant::where('name', 'Jean Wifi Zone')->first();
        $this->assertNotNull($tenant);
        $this->assertDatabaseHas('users', ['email' => 'jean@example.com', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $tenant->id, 'status' => 'trial']);
    }

    public function test_entrepreneur_cannot_open_another_tenants_router(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice@example.com');
        $bob = Platform::entrepreneur('Bob Wifi', 'bob@example.com');
        $router = Platform::router($bob, Platform::zone($bob, 'Zone Bob'));

        $this->actingAs($alice)->get('/mikrotiks/'.$router->id.'/edit')->assertNotFound();
    }

    public function test_pro_api_is_isolated_and_starter_is_blocked(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice@example.com', 'pro');
        $bob = Platform::entrepreneur('Bob Wifi', 'bob@example.com', 'pro');
        $router = Platform::router($bob, Platform::zone($bob));
        $starter = Platform::entrepreneur('Starter Wifi', 'starter@example.com', 'starter');

        Sanctum::actingAs($starter);
        $this->getJson('/api/v1/mikrotiks')->assertForbidden();

        Sanctum::actingAs($alice);
        $this->getJson('/api/v1/mikrotiks/'.$router->id.'/active-users')->assertNotFound();
        $this->getJson('/api/v1/mikrotiks')->assertOk()->assertJsonCount(0);
    }

    public function test_router_password_is_not_rendered(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice@example.com');
        $router = Platform::router($user, Platform::zone($user));

        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'/edit')
            ->assertOk()
            ->assertDontSee('secret-router', false);

        $stored = Mikrotik::withoutGlobalScope('tenant')->find($router->id);
        $this->assertNotSame('secret-router', $stored->getRawOriginal('password'));
        $this->assertSame('secret-router', $stored->password);
    }

    public function test_public_sale_stays_inside_the_zone_until_payment_is_confirmed(): void
    {
        $this->app->instance(HotspotRouter::class, new class implements HotspotRouter
        {
            public function command(string $host, int $port, string $username, string $password, array $words, int $timeout = 5, bool $secure = false): array
            {
                throw new RuntimeException('Connexion impossible au routeur.');
            }
        });

        $user = Platform::entrepreneur('Alice Wifi', 'alice@example.com');
        $zone = Platform::zone($user, 'Limete');
        $plan = Platform::plan($user, $zone);

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810000111',
            'provider' => 'manual',
            'transaction_reference' => 'MM123',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->first();
        $this->assertSame('pending', $sale->status);
        $this->assertSame($user->tenant_id, $sale->tenant_id);
        $this->assertDatabaseCount('vouchers', 0);

        app(TenantManager::class)->set($user->tenant_id);
        $this->actingAs($user)->post('/sales/'.$sale->id.'/confirm')->assertRedirect();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('active', $voucher->status);
        $this->assertSame($user->tenant_id, $voucher->tenant_id);
        $this->assertNotNull($voucher->expires_at);
        $this->get('/ticket/'.$voucher->public_token)->assertOk()->assertSee($voucher->username);
    }

    public function test_suspended_tenant_cannot_use_the_dashboard(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice@example.com');
        $user->tenant->update(['status' => 'suspended']);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_super_admin_lists_tenants_and_staff_without_permission_is_blocked(): void
    {
        Platform::seed();
        $admin = User::where('email', 'admin@limetewifi.local')->first();
        $owner = Platform::entrepreneur('Alice Wifi', 'alice@example.com');

        $this->actingAs($admin)->get('/admin/tenants')->assertOk()->assertSee('Alice Wifi');

        $staff = User::create([
            'tenant_id' => $owner->tenant_id,
            'role_id' => Role::where('slug', 'staff')->first()->id,
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);

        $this->actingAs($staff)->get('/vouchers')->assertForbidden();
    }
}
