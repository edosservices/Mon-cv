<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Production\EndpointProbe;
use App\Services\Production\ProductionCheck;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakeEndpointProbe;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class ProductionCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_production_page_keeps_warnings_and_does_not_pass_a_documentation_router(): void
    {
        $owner = Platform::entrepreneur('LIMETE WIFI', 'demo-doc@limetewifi.local', 'business');
        $zone = Platform::zone($owner, 'Limete Kingabwa');
        $documented = Platform::router($owner, $zone);
        $documented->update([
            'host' => '192.0.2.55',
            'name' => 'Routeur de démonstration',
            'status' => 'offline',
            'last_error' => 'Routeur fictif, aucune connexion réelle.',
        ]);
        $admin = User::where('email', 'admin@limetewifi.local')->first();
        $probe = new FakeEndpointProbe;
        $probe->tcpCalls = 0;
        $this->app->instance(EndpointProbe::class, $probe);
        $router = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $router);

        $this->actingAs($admin)->get('/admin/production-check')
            ->assertOk()
            ->assertSee('REAL MIKROTIK NOT TESTED')
            ->assertSee('PASS')
            ->assertSee('WARN')
            ->assertSee('NOT TESTED')
            ->assertSee('NOT CONFIGURED')
            ->assertSee('192.0.2.55')
            ->assertDontSee('opérationnel')
            ->assertDontSee('secret-router');

        $this->actingAs($admin)->post('/admin/production-check/mikrotiks/'.Mikrotik::withoutGlobalScope('tenant')->where('host', '192.0.2.55')->first()->id.'/test')
            ->assertRedirect();
        $this->assertSame(0, $probe->tcpCalls);
        $this->assertSame([], $router->commands);

        $this->actingAs($admin)->get('/admin/production-check')
            ->assertOk()
            ->assertSee('Adresse de documentation')
            ->assertSee('REAL MIKROTIK NOT TESTED');
    }

    public function test_an_unreachable_router_is_not_reported_as_connected(): void
    {
        [$admin, $router] = $this->liveRouter('10.51.0.9');
        $endpoints = new FakeEndpointProbe(['ok' => false, 'error' => 'timed out', 'milliseconds' => 12]);
        $this->app->instance(EndpointProbe::class, $endpoints);
        $hotspot = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $hotspot);

        $this->actingAs($admin)->post('/admin/production-check/mikrotiks/'.$router->id.'/test')->assertRedirect();

        $router->refresh();
        $this->assertSame('offline', $router->status);
        $this->assertSame('Le serveur Laravel ne peut pas joindre le MikroTik.', $router->last_error);
        $this->assertSame([], $hotspot->commands);
        $this->actingAs($admin)->post('/admin/production-check/mikrotiks/'.$router->id.'/test-user', ['profile' => 'default'])
            ->assertRedirect()
            ->assertSessionHas('test_user', fn (array $result) => $result['status'] === 'FAIL' && $result['confirmed'] === false);
        $this->assertSame([], $hotspot->commands);
        $this->actingAs($admin)->get('/admin/production-check')
            ->assertOk()
            ->assertSee('Le serveur Laravel ne peut pas joindre le MikroTik.')
            ->assertSee('REAL MIKROTIK NOT TESTED')
            ->assertDontSee('API authentifiée');
    }

    public function test_an_authentication_failure_hides_the_password_and_reads_nothing_else(): void
    {
        [$admin, $router] = $this->liveRouter('10.51.0.10');
        $this->app->instance(EndpointProbe::class, new FakeEndpointProbe(['ok' => true, 'error' => null, 'milliseconds' => 2]));
        $hotspot = new FakeHotspotRouter(new RuntimeException('invalid user name or password (secret-router)'));
        $this->app->instance(HotspotRouter::class, $hotspot);

        $this->actingAs($admin)->post('/admin/production-check/mikrotiks/'.$router->id.'/test')->assertRedirect();

        $this->assertSame('error', $router->fresh()->status);
        $this->assertStringNotContainsString('secret-router', (string) $router->fresh()->last_error);
        $page = $this->actingAs($admin)->get('/admin/production-check');
        $page->assertOk()->assertDontSee('secret-router', false)->assertSee('FAIL');
        $this->assertSame(1, count($hotspot->commands));
    }

    public function test_a_missing_hotspot_and_a_missing_profile_are_not_created(): void
    {
        [$admin, $router, $plan] = $this->liveRouter('10.51.0.11', true);
        $plan->update(['name' => 'TEST 1H', 'mikrotik_profile' => 'TEST-1H']);
        $this->app->instance(EndpointProbe::class, new FakeEndpointProbe(['ok' => true, 'error' => null, 'milliseconds' => 2]));
        $hotspot = new FakeHotspotRouter(function (array $words) {
            return match ($words[0]) {
                '/system/identity/print' => [['!type' => '!re', 'name' => 'rt-reel'], ['!type' => '!done']],
                '/system/resource/print' => [['!type' => '!re', 'version' => '7.16', 'uptime' => '1d', 'cpu-load' => '3'], ['!type' => '!done']],
                '/ip/hotspot/user/profile/print' => [['!type' => '!re', 'name' => 'default'], ['!type' => '!done']],
                default => [['!type' => '!done']],
            };
        });
        $this->app->instance(HotspotRouter::class, $hotspot);

        $this->actingAs($admin)->post('/admin/production-check/mikrotiks/'.$router->id.'/test')->assertRedirect();
        $page = $this->actingAs($admin)->get('/admin/production-check');
        $page->assertOk()
            ->assertSee('rt-reel')
            ->assertSee('7.16')
            ->assertSee('Configuration manquante')
            ->assertSee('TEST 1H')
            ->assertSee('TEST-1H')
            ->assertSee('profil inexistant sur le MikroTik');
        $paths = array_map(fn (array $call) => $call['words'][0], $hotspot->commands);
        $this->assertNotContains('/ip/hotspot/add', $paths);
        $this->assertNotContains('/ip/hotspot/user/profile/add', $paths);
        $this->assertNotContains('/ip/hotspot/user/add', $paths);
    }

    public function test_a_real_test_user_is_pass_only_when_routeros_returns_it_and_delete_is_limited(): void
    {
        [$admin, $router] = $this->liveRouter('10.51.0.12');
        $this->app->instance(EndpointProbe::class, new FakeEndpointProbe(['ok' => true, 'error' => null, 'milliseconds' => 2]));
        $present = false;
        $hotspot = new FakeHotspotRouter(function (array $words) use (&$present) {
            if (($words[0] ?? '') === '/ip/hotspot/user/remove') {
                $present = false;

                return [['!type' => '!done']];
            }
            if ($words[0] === '/ip/hotspot/user/print' && str_contains(implode(' ', $words), 'LIMETE_TEST_')) {
                if (! $present) {
                    return [['!type' => '!done']];
                }

                return [['!type' => '!re', '.id' => '*T', 'name' => $this->nameFrom($words), 'comment' => 'limete-test', 'profile' => 'TEST-1H'], ['!type' => '!done']];
            }

            return match ($words[0]) {
                '/system/identity/print' => [['!type' => '!re', 'name' => 'rt-reel'], ['!type' => '!done']],
                '/system/resource/print' => [['!type' => '!re', 'version' => '7.16'], ['!type' => '!done']],
                '/ip/hotspot/user/profile/print' => [['!type' => '!re', 'name' => 'TEST-1H'], ['!type' => '!done']],
                '/ip/hotspot/print' => [['!type' => '!re', 'name' => 'hs1', 'interface' => 'bridge', 'profile' => 'default', 'address-pool' => 'pool1', 'login-by' => 'http-pap,cookie'], ['!type' => '!done']],
                default => [['!type' => '!done']],
            };
        });
        $this->app->instance(HotspotRouter::class, $hotspot);

        $this->actingAs($admin)->post('/admin/production-check/mikrotiks/'.$router->id.'/test-user', ['profile' => 'ABSENT'])
            ->assertRedirect()
            ->assertSessionHas('test_user', fn (array $result) => $result['status'] === 'FAIL' && $result['confirmed'] === false);
        $this->assertNotContains('/ip/hotspot/user/add', array_map(fn (array $call) => $call['words'][0], $hotspot->commands));

        $present = true;
        $created = null;
        $this->actingAs($admin)->from('/admin/production-check')->post('/admin/production-check/mikrotiks/'.$router->id.'/test-user', ['profile' => 'TEST-1H'])
            ->assertRedirect()
            ->assertSessionHas('test_user', function (array $result) use (&$created) {
                $created = $result;

                return $result['status'] === 'PASS' && $result['confirmed'] === true && str_starts_with($result['username'], 'LIMETE_TEST_');
            });
        $this->actingAs($admin)->get('/admin/production-check')->assertOk()->assertDontSee('secret-router', false);

        $this->actingAs($admin)->delete('/admin/production-check/mikrotiks/'.$router->id.'/test-user', ['username' => 'CLIENTOK'])
            ->assertSessionHasErrors('username');
        $this->assertNotContains('/ip/hotspot/user/remove', array_map(fn (array $call) => $call['words'][0], $hotspot->commands));

        $this->actingAs($admin)->delete('/admin/production-check/mikrotiks/'.$router->id.'/test-user', ['username' => $created['username']])
            ->assertRedirect()
            ->assertSessionHas('test_user', fn (array $result) => $result['status'] === 'PASS' && $result['deleted'] === true);
    }

    public function test_portal_payment_and_secrets_stay_explicit(): void
    {
        Platform::seed();
        $admin = User::where('email', 'admin@limetewifi.local')->first();
        config(['limete.payments.airtel_money.api_key' => 'super-secret-airtel']);

        $page = $this->actingAs($admin)->get('/admin/production-check');
        $page->assertOk()
            ->assertSee('login.html')
            ->assertSee('LIMETE_ZONE')
            ->assertSee('PENDING REAL TEST')
            ->assertSee('NOT CONFIGURED')
            ->assertDontSee('super-secret-airtel', false)
            ->assertDontSee('Internet fonctionne');

        $report = app(ProductionCheck::class)->report(request());
        $this->assertSame('NOT TESTED', collect($report['journey'])->firstWhere('step', 15)['status']);
        $this->assertSame('NOT TESTED', collect($report['sections'])->firstWhere('code', 'M')['status']);
        $this->assertSame('NOT TESTED', collect($report['sections'])->firstWhere('code', 'P')['status']);
    }

    public function test_tenants_and_zones_stay_isolated_and_expiration_does_not_restart(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice-prod@example.com', 'business');
        $limete = Platform::zone($alice, 'Limete');
        $kingabwa = Platform::zone($alice, 'Kingabwa');
        $plan = Platform::plan($alice, $limete);
        $home = Platform::router($alice, $limete);
        $other = Platform::router($alice, $kingabwa);
        $other->update(['host' => '10.51.0.20', 'status' => 'online']);
        $home->update(['status' => 'online']);
        $plan->update(['mikrotik_profile' => '24H']);
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter);
        app(TenantManager::class)->set($alice->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($limete, $plan->fresh(), 1, true)[0];
        $expires = $voucher->expires_at->getTimestamp();

        $thrown = false;
        try {
            app(MikrotikService::class)->createHotspotUser($other->fresh(), $voucher->fresh('plan'));
        } catch (RuntimeException $exception) {
            $thrown = str_contains($exception->getMessage(), 'WiFi Zone');
        }
        $this->assertTrue($thrown);

        $this->travel(4)->hours();
        app(VoucherGenerator::class)->activate($voucher->fresh());
        $this->assertSame($expires, $voucher->fresh()->expires_at->getTimestamp());
        $this->assertSame($voucher->activated_at->getTimestamp() + 86400, $expires);
        $this->travelBack();
    }

    public function test_two_authorized_routers_receive_the_same_hotspot_user_and_another_tenant_cannot_open_the_check(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice-two@example.com', 'business');
        $zone = Platform::zone($alice, 'Limete');
        $plan = Platform::plan($alice, $zone);
        $plan->update(['mikrotik_profile' => '24H']);
        $first = Platform::router($alice, $zone);
        $second = Mikrotik::create([
            'tenant_id' => $alice->tenant_id,
            'wifi_zone_id' => $zone->id,
            'name' => 'MikroTik B',
            'host' => '10.51.0.31',
            'api_port' => 8728,
            'username' => 'demo',
            'password' => 'secret-router',
            'status' => 'online',
            'is_active' => true,
        ]);
        $first->update(['status' => 'online', 'host' => '10.51.0.30']);
        $fake = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $fake);
        app(TenantManager::class)->set($alice->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        app(MikrotikService::class)->provisionVoucher($voucher->fresh(['plan', 'wifiZone.mikrotiks']));
        $hosts = array_column(array_values(array_filter($fake->commands, fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add')), 'host');
        $this->assertEqualsCanonicalizing(['10.51.0.30', '10.51.0.31'], $hosts);
        $this->assertSame($first->id, $voucher->fresh()->mikrotik_id);

        $bob = Platform::entrepreneur('Bob Wifi', 'bob-prod@example.com');
        $this->actingAs($bob)->get('/admin/production-check')->assertForbidden();
        $this->actingAs($bob)->get('/mikrotiks/'.$second->id)->assertNotFound();
        $this->actingAs($bob)->get('/vouchers/'.$voucher->id)->assertNotFound();
        app(TenantManager::class)->set($bob->tenant_id);
        $this->assertSame(0, Voucher::query()->count());
        $this->assertNull(Mikrotik::query()->find($second->id));
    }

    public function test_a_pending_payment_is_not_a_real_provider_success(): void
    {
        Platform::seed();
        $admin = User::where('email', 'admin@limetewifi.local')->first();
        $page = $this->actingAs($admin)->get('/admin/production-check');
        $page->assertOk()->assertSee('NOT CONFIGURED')->assertSee('CONFIGURED');
        $this->assertFalse(collect(app(ProductionCheck::class)->report(request())['payments'])->contains(
            fn (array $row) => in_array($row['provider'], ['airtel_money', 'orange_money', 'mpesa', 'card'], true) && $row['status'] === 'CONFIGURED'
        ));
    }

    private function liveRouter(string $host, bool $withPlan = false): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-'.str()->lower(str()->random(4)).'@example.com', 'business');
        $zone = Platform::zone($user, 'Limete');
        $router = Platform::router($user, $zone);
        $router->update(['host' => $host, 'status' => 'unknown']);
        $plan = $withPlan ? Platform::plan($user, $zone) : null;
        Platform::seed();
        $admin = User::where('email', 'admin@limetewifi.local')->first();

        return $withPlan ? [$admin, $router, $plan] : [$admin, $router];
    }

    private function nameFrom(array $words): string
    {
        foreach ($words as $word) {
            if (str_starts_with($word, '?name=')) {
                return substr($word, 6);
            }
        }

        return '';
    }
}
