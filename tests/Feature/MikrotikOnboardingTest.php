<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Mikrotik;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\Mikrotik\HotspotRouter;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class MikrotikOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_entrepreneur_can_open_the_form_and_a_client_cannot(): void
    {
        [$user, $zone] = $this->workspace();

        $this->actingAs($user)->get('/mikrotiks/create')
            ->assertOk()
            ->assertSee('Ajouter un MikroTik')
            ->assertSee('value="8728"', false)
            ->assertSee('type="password"', false)
            ->assertSee($zone->name)
            ->assertDontSee('secret-router', false);

        $client = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Client->value)->first()->id,
            'name' => 'Client',
            'email' => 'client-router@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);

        $this->actingAs($client)->get('/mikrotiks/create')->assertForbidden();
        $this->actingAs($client)->get('/mikrotiks')->assertForbidden();
    }

    public function test_host_and_port_are_validated(): void
    {
        [$user, $zone] = $this->workspace();

        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, ['host' => '']))
            ->assertSessionHasErrors('host');
        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, ['host' => '192.168.0.1 espace']))
            ->assertSessionHasErrors('host');
        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, ['api_port' => 0]))
            ->assertSessionHasErrors('api_port');
        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, ['api_port' => 70000]))
            ->assertSessionHasErrors('api_port');
        $this->actingAs($user)->post('/mikrotiks/probe', [
            'host' => '10.0.0.1',
            'api_port' => 'abc',
            'username' => 'admin',
            'password' => 'router-secret-77',
        ])->assertSessionHasErrors('api_port');
    }

    public function test_a_probe_detects_routeros_without_saving_or_leaking_the_password(): void
    {
        [$user] = $this->workspace();
        $this->bindRouter();
        $lines = $this->captureLogs();
        $before = Mikrotik::count();

        $this->actingAs($user)->post('/mikrotiks/probe', [
            'host' => '10.8.0.1',
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'router-secret-77',
        ])->assertRedirect()
            ->assertSessionHas('probe')
            ->assertSessionMissing('password');

        $this->actingAs($user)->get('/mikrotiks/create')
            ->assertSee('MikroTik détecté')
            ->assertSee('LIMETE-ROUTER')
            ->assertSee('7.15.3')
            ->assertSee('1d2h')
            ->assertSee('HotSpot détecté')
            ->assertSee('Oui')
            ->assertSee('24H')
            ->assertSee('1H')
            ->assertDontSee('router-secret-77', false)
            ->assertDontSee('should-not-leak', false);

        $this->assertSame($before, Mikrotik::count());
        $this->assertLogsHideSecrets($lines, ['router-secret-77', 'should-not-leak']);
    }

    public function test_a_failed_probe_reports_the_error_without_the_password(): void
    {
        [$user] = $this->workspace();
        $fake = new FakeHotspotRouter;
        $fake->fail('Connexion impossible au routeur. router-secret-77');
        $this->app->instance(HotspotRouter::class, $fake);

        $this->actingAs($user)->from('/mikrotiks/create')->followingRedirects()->post('/mikrotiks/probe', [
            'host' => '10.8.0.1',
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'router-secret-77',
        ])->assertOk()
            ->assertSee('Impossible de joindre le MikroTik')
            ->assertDontSee('router-secret-77', false);

        $this->assertSame(1, Mikrotik::count());
    }

    public function test_saving_a_router_stores_identity_version_zone_and_an_encrypted_password(): void
    {
        [$user, $zone, $plan] = $this->workspace(false);
        $plan->update(['mikrotik_profile' => 'ABSENT']);
        $this->bindRouter();

        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, [
            'name' => 'LIMETE-ROUTER',
            'host' => '10.8.0.1',
            'password' => 'router-secret-77',
        ]))->assertRedirect();

        $router = Mikrotik::where('host', '10.8.0.1')->first();
        $this->assertNotNull($router);
        $this->assertSame($zone->id, $router->wifi_zone_id);
        $this->assertSame($user->tenant_id, $router->tenant_id);
        $this->assertSame('online', $router->status);
        $this->assertSame('LIMETE-ROUTER', $router->identity);
        $this->assertSame('7.15.3', $router->routeros_version);
        $this->assertNotNull($router->last_seen_at);
        $this->assertTrue($router->details['hotspot']);
        $this->assertSame(1, $router->details['active_users']);
        $this->assertSame('router-secret-77', $router->password);
        $stored = DB::table('mikrotiks')->where('id', $router->id)->value('password');
        $this->assertNotSame('router-secret-77', $stored);
        $this->assertStringNotContainsString('router-secret-77', (string) $stored);
        $this->assertStringNotContainsString('should-not-leak', json_encode($router->details));

        $this->actingAs($user)->get('/mikrotiks')
            ->assertOk()
            ->assertSee('● CONNECTÉ')
            ->assertSee('24H ✓')
            ->assertSee('1H ✓')
            ->assertSee('Profil non trouvé')
            ->assertDontSee('router-secret-77', false)
            ->assertDontSee('should-not-leak', false);

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/plan-profile', [
            'plan_id' => $plan->id,
            'mikrotik_profile' => '24H',
        ])->assertRedirect();
        $this->assertSame('24H', $plan->fresh()->mikrotik_profile);

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/plan-profile', [
            'plan_id' => $plan->id,
            'mikrotik_profile' => 'INCONNU',
        ])->assertRedirect()->assertSessionHas('warning', 'Profil non trouvé');
        $this->assertSame('24H', $plan->fresh()->mikrotik_profile);
    }

    public function test_an_offline_router_can_be_saved_and_retried_without_deleting_it(): void
    {
        [$user, $zone] = $this->workspace(false);
        $fake = new FakeHotspotRouter;
        $fake->fail('Connexion impossible au routeur.');
        $this->app->instance(HotspotRouter::class, $fake);

        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, [
            'name' => 'Routeur distant',
            'host' => '10.9.0.1',
            'password' => 'router-secret-77',
        ]))->assertRedirect();

        $router = Mikrotik::where('host', '10.9.0.1')->first();
        $this->assertSame('offline', $router->status);
        $this->assertNull($router->identity);
        $this->assertStringContainsString('Connexion impossible', $router->last_error);
        $this->assertNotNull($router->last_seen_at);

        $this->actingAs($user)->get('/mikrotiks')
            ->assertSee('● HORS LIGNE')
            ->assertSee('Impossible de joindre le MikroTik.')
            ->assertDontSee('router-secret-77', false);

        $this->app->instance(HotspotRouter::class, $this->routerDouble());
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/sync')->assertRedirect();
        $router->refresh();
        $this->assertSame('online', $router->status);
        $this->assertSame('LIMETE-ROUTER', $router->identity);
        $this->assertSame('7.15.3', $router->routeros_version);
        $this->assertNull($router->last_error);
    }

    public function test_router_actions_stay_inside_the_tenant_and_respect_the_plan_limit(): void
    {
        [$alice, $zone] = $this->workspace();
        $router = Mikrotik::where('tenant_id', $alice->tenant_id)->first();
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-onboard@example.com');
        $foreignZone = Platform::zone($bob, 'Kasa');
        $foreign = Platform::router($bob, $foreignZone);

        $this->actingAs($alice)->post('/mikrotiks/'.$foreign->id.'/sync')->assertNotFound();
        $this->actingAs($alice)->post('/mikrotiks/'.$foreign->id.'/test')->assertNotFound();
        $this->actingAs($alice)->delete('/mikrotiks/'.$foreign->id)->assertNotFound();
        $this->assertNotNull(Mikrotik::withoutGlobalScope('tenant')->find($foreign->id));

        $this->actingAs($alice)->post('/mikrotiks', $this->payload($zone, [
            'name' => 'Deuxième',
            'host' => '10.9.9.9',
        ]))->assertSessionHasErrors('name');

        $this->actingAs($alice)->delete('/mikrotiks/'.$router->id)->assertRedirect();
        $this->assertSoftDeleted($router);
        app(TenantManager::class)->set($bob->tenant_id);
        $this->assertNotNull(Mikrotik::find($foreign->id));
    }

    private function workspace(bool $withRouter = true): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-onboard-'.str()->lower(str()->random(4)).'@example.com');
        $zone = Platform::zone($user, 'LIMETE WIFI');
        $plan = Platform::plan($user, $zone);
        if ($withRouter) {
            Platform::router($user, $zone);
        }

        return [$user, $zone, $plan];
    }

    private function payload($zone, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Routeur test',
            'wifi_zone_id' => $zone->id,
            'host' => '10.1.1.1',
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'router-secret-77',
        ], $overrides);
    }

    private function bindRouter(): FakeHotspotRouter
    {
        $fake = $this->routerDouble();
        $this->app->instance(HotspotRouter::class, $fake);

        return $fake;
    }

    private function routerDouble(): FakeHotspotRouter
    {
        return new FakeHotspotRouter(function (array $words) {
            return match ($words[0]) {
                '/system/identity/print' => [['!type' => '!re', 'name' => 'LIMETE-ROUTER'], ['!type' => '!done']],
                '/system/resource/print' => [['!type' => '!re', 'version' => '7.15.3', 'uptime' => '1d2h', 'cpu-load' => '4', 'free-memory' => '1048576', 'total-memory' => '2097152', 'architecture-name' => 'arm'], ['!type' => '!done']],
                '/ip/hotspot/print' => [['!type' => '!re', 'name' => 'hotspot1'], ['!type' => '!done']],
                '/ip/hotspot/profile/print' => [['!type' => '!re', 'name' => 'hsprof1'], ['!type' => '!done']],
                '/ip/hotspot/user/profile/print' => [['!type' => '!re', 'name' => '24H'], ['!type' => '!re', 'name' => '1H'], ['!type' => '!done']],
                '/ip/hotspot/active/print' => [['!type' => '!re', 'user' => 'guest'], ['!type' => '!done']],
                '/ip/hotspot/user/print' => [['!type' => '!re', 'name' => 'guest', 'password' => 'should-not-leak'], ['!type' => '!done']],
                default => [['!type' => '!done']],
            };
        });
    }

    private function captureLogs(): \ArrayObject
    {
        $lines = new \ArrayObject;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($lines) {
            $lines->append($event->message.' '.json_encode($event->context));
        });

        return $lines;
    }

    private function assertLogsHideSecrets(\ArrayObject $lines, array $secrets): void
    {
        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $line);
            }
        }
    }
}
