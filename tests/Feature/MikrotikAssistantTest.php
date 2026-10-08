<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SyncHotspotUser;
use App\Models\Mikrotik;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\Mikrotik\MikrotikService;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class MikrotikAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_wizard_saves_an_encrypted_password_and_labels_a_fake_router_as_simulation(): void
    {
        [$user, $zone, $plan] = $this->workspace();
        $this->bindRichRouter();
        $lines = $this->captureLogs();

        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertOk()
            ->assertSee('Ajouter mon MikroTik')
            ->assertSee('Aucun routeur');

        $this->actingAs($user)->post('/mikrotiks/assistant/start')->assertRedirect('/mikrotiks/assistant');
        $this->actingAs($user)->post('/mikrotiks/assistant/step', ['step' => 1, 'name' => 'MikroTik Limete'])->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertSee('Paramètres avancés')
            ->assertSee('8728');
        $this->actingAs($user)->post('/mikrotiks/assistant/step', [
            'step' => 2,
            'host' => '10.8.0.1',
            'api_port' => 8728,
        ])->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertSee('type="password"', false)
            ->assertDontSee('router-secret-77', false);

        $this->actingAs($user)->post('/mikrotiks/assistant/step', [
            'step' => 3,
            'username' => 'admin',
            'password' => 'router-secret-77',
        ])->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertDontSee('router-secret-77', false)
            ->assertSee('Tester la connexion');

        $this->actingAs($user)->post('/mikrotiks/assistant/test')->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertSee('Simulation réussie')
            ->assertSee('Aucun routeur réel')
            ->assertSee('LIMETE-ROUTER')
            ->assertSee('7.15.3')
            ->assertSee('ether1')
            ->assertSee('24H')
            ->assertDontSee('✓ Routeur connecté', false)
            ->assertDontSee('router-secret-77', false)
            ->assertDontSee('/ip/hotspot', false)
            ->assertDontSee('Stack trace', false);

        $this->actingAs($user)->post('/mikrotiks/assistant/continue')->assertRedirect();
        $this->actingAs($user)->post('/mikrotiks/assistant/save', [
            'wifi_zone_id' => $zone->id,
            'auto_sync' => '1',
        ])->assertRedirect();

        $router = Mikrotik::where('host', '10.8.0.1')->first();
        $this->assertNotNull($router);
        $this->assertSame($zone->id, $router->wifi_zone_id);
        $this->assertSame($user->tenant_id, $router->tenant_id);
        $this->assertSame('router-secret-77', $router->password);
        $this->assertTrue($router->auto_sync);
        $this->assertNotNull($router->last_synced_at);
        $this->assertSame('simulation', $router->details['connection_mode']);
        $stored = DB::table('mikrotiks')->where('id', $router->id)->value('password');
        $this->assertNotSame('router-secret-77', $stored);
        $this->assertStringNotContainsString('router-secret-77', (string) $stored);

        $this->actingAs($user)->get('/mikrotiks/assistant/'.$router->id)
            ->assertOk()
            ->assertSee('MikroTik Limete')
            ->assertSee($zone->name)
            ->assertSee('SIMULATION')
            ->assertSee('LIMETE-ROUTER')
            ->assertSee('7.15.3')
            ->assertSee('2M/2M')
            ->assertSee($plan->name)
            ->assertDontSee('✓ Routeur connecté', false)
            ->assertDontSee('router-secret-77', false)
            ->assertDontSee('/system/', false);

        $this->assertLogsHideSecrets($lines, ['router-secret-77']);
    }

    public function test_documentation_addresses_are_refused_before_any_router_call(): void
    {
        [$user] = $this->workspace();
        $fake = $this->bindRichRouter();

        $this->actingAs($user)->post('/mikrotiks/assistant/start');
        $this->actingAs($user)->post('/mikrotiks/assistant/step', ['step' => 1, 'name' => 'Doc']);
        $this->actingAs($user)->from('/mikrotiks/assistant')->post('/mikrotiks/assistant/step', [
            'step' => 2,
            'host' => '192.0.2.55',
            'api_port' => 8728,
        ])->assertRedirect()
            ->assertSessionHas('warning', 'Cette adresse ne correspond pas à un routeur réel.');

        $this->assertSame([], $fake->commands);
        $this->assertSame(0, Mikrotik::count());
    }

    public function test_a_bad_password_and_a_timeout_stay_understandable_and_secret(): void
    {
        [$user] = $this->workspace();
        $fake = new FakeHotspotRouter;
        $fake->fail('invalid user name or password (router-secret-77)');
        $this->app->instance(HotspotRouter::class, $fake);
        $this->reachTestStep($user);

        $this->actingAs($user)->from('/mikrotiks/assistant')->post('/mikrotiks/assistant/test')->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertSee('Identifiants RouterOS incorrects')
            ->assertDontSee('router-secret-77', false)
            ->assertDontSee('/system/identity', false);

        $fake->fail('Connection timed out. router-secret-77');
        $this->actingAs($user)->post('/mikrotiks/assistant/test')->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertSee('MikroTik non joignable depuis le serveur')
            ->assertDontSee('router-secret-77', false);
        $this->assertSame(0, Mikrotik::count());
    }

    public function test_the_test_step_can_be_modified_and_a_failed_read_stays_explicit(): void
    {
        [$user] = $this->workspace();
        $this->reachTestStep($user);

        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertOk()
            ->assertSee('Modifier')
            ->assertSee('10.8.0.1')
            ->assertSee('Le test de connexion est effectué depuis le serveur Limete WiFi. Une adresse privée comme 192.168.x.x peut être accessible depuis votre téléphone ou ordinateur connecté au MikroTik, mais inaccessible depuis le serveur.')
            ->assertDontSee('router-secret-77', false);

        $this->actingAs($user)->post('/mikrotiks/assistant/revise')->assertRedirect('/mikrotiks/assistant');
        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertSee('Adresse IP ou nom')
            ->assertSee('value="10.8.0.1"', false)
            ->assertDontSee('router-secret-77', false);

        $this->actingAs($user)->post('/mikrotiks/assistant/step', [
            'step' => 2,
            'host' => '192.168.88.1',
            'api_port' => 8728,
        ])->assertRedirect();
        $this->actingAs($user)->post('/mikrotiks/assistant/step', [
            'step' => 3,
            'username' => 'admin',
            'password' => '',
        ])->assertRedirect();

        $fake = new FakeHotspotRouter;
        $fake->fail('Connection timed out. router-secret-77');
        $this->app->instance(HotspotRouter::class, $fake);
        $this->actingAs($user)->post('/mikrotiks/assistant/test')->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant')
            ->assertSee('192.168.88.1')
            ->assertSee('MikroTik non joignable depuis le serveur')
            ->assertSee('Modifier')
            ->assertDontSee('router-secret-77', false);
        $this->assertSame(0, Mikrotik::count());
    }

    public function test_a_failed_read_names_the_cause_and_keeps_the_saved_address(): void
    {
        [$owner, , , $router] = $this->readyRouter();
        $secret = $router->password;
        app(HotspotRouter::class)->fail('Connexion impossible au routeur. '.$secret);

        $this->actingAs($owner)->get('/mikrotiks/assistant/'.$router->id)
            ->assertOk()
            ->assertSee('Lire le routeur')
            ->assertSee('Modifier')
            ->assertDontSee($secret, false);
        $this->actingAs($owner)->post('/mikrotiks/assistant/'.$router->id.'/read')->assertRedirect();
        $this->actingAs($owner)->get('/mikrotiks/assistant/'.$router->id)
            ->assertSee('Le MikroTik n’a pas pu être lu.')
            ->assertSee('MikroTik non joignable depuis le serveur')
            ->assertDontSee($secret, false);
        $this->assertSame('10.8.0.1', $router->fresh()->host);
        $this->assertSame($secret, $router->fresh()->password);
    }

    public function test_tenant_isolation_and_permissions(): void
    {
        [$user, $zone] = $this->workspace();
        $router = Platform::router($user, $zone);
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-assistant@example.com');
        $foreign = Platform::zone($bob, 'Kasa');

        $this->actingAs($bob)->get('/mikrotiks/assistant/'.$router->id)->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/assistant/'.$router->id.'/read')->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/assistant/'.$router->id.'/retry')->assertNotFound();

        $this->actingAs($user)->post('/mikrotiks/assistant/start');
        $this->actingAs($user)->post('/mikrotiks/assistant/step', ['step' => 1, 'name' => 'Mine']);
        $this->actingAs($user)->post('/mikrotiks/assistant/step', ['step' => 2, 'host' => '10.8.0.1', 'api_port' => 8728]);
        $this->actingAs($user)->post('/mikrotiks/assistant/step', ['step' => 3, 'username' => 'admin', 'password' => 'router-secret-77']);
        $this->bindRichRouter();
        $this->actingAs($user)->post('/mikrotiks/assistant/test');
        $this->actingAs($user)->post('/mikrotiks/assistant/continue');
        $this->actingAs($user)->post('/mikrotiks/assistant/save', ['wifi_zone_id' => $foreign->id])
            ->assertSessionHasErrors('wifi_zone_id');

        $client = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Client->value)->first()->id,
            'name' => 'Client',
            'email' => 'client-assistant@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);
        $this->actingAs($client)->get('/mikrotiks/assistant')->assertForbidden();

        $staff = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Staff->value)->first()->id,
            'name' => 'Staff',
            'email' => 'staff-assistant@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);
        $this->actingAs($staff)->get('/mikrotiks/assistant')->assertForbidden();
        $staff->permissions()->attach(Permission::where('slug', 'mikrotiks.manage')->first());
        $this->actingAs($staff)->get('/mikrotiks/assistant/'.$router->id)->assertOk();
        $this->actingAs($staff)->post('/mikrotiks/assistant/'.$router->id.'/profile', [
            'plan_id' => Plan::first()->id,
            'confirm' => '1',
        ])->assertForbidden();
        $this->actingAs($staff)->post('/active-users/disconnect', [
            'mikrotik_id' => $router->id,
            'active_id' => '*1',
        ])->assertForbidden();
    }

    public function test_profiles_can_be_linked_and_created_only_after_confirmation(): void
    {
        [$user, $zone, $plan, $router] = $this->readyRouter();
        $service = app(MikrotikService::class);
        $this->assertSame('24h', $service->suggestProfileName($plan));
        $this->assertSame('48h', $service->suggestProfileName(new Plan(['name' => '48 HEURES', 'duration_seconds' => 172800])));
        $this->assertSame('7j', $service->suggestProfileName(new Plan(['name' => '7 JOURS', 'duration_seconds' => 604800])));

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/plan-profile', [
            'plan_id' => $plan->id,
            'mikrotik_profile' => '24H',
        ])->assertRedirect();
        $this->assertSame('24H', $plan->fresh()->mikrotik_profile);

        $other = Platform::plan($user, $zone);
        $other->update(['name' => '48 HEURES', 'duration_seconds' => 172800, 'mikrotik_profile' => null]);
        $this->actingAs($user)->post('/mikrotiks/assistant/'.$router->id.'/profile', [
            'plan_id' => $other->id,
        ])->assertSessionHasErrors('confirm');
        $this->assertFalse(collect(app(HotspotRouter::class)->commands)->contains(
            fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/profile/add'
        ));

        $this->actingAs($user)->post('/mikrotiks/assistant/'.$router->id.'/profile-preview', [
            'plan_id' => $other->id,
        ])->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant/'.$router->id)
            ->assertSee('Voici ce qui sera créé')
            ->assertSee('48h')
            ->assertSee('Sans limite')
            ->assertDontSee('/ip/hotspot/user/profile/add', false);

        $this->actingAs($user)->post('/mikrotiks/assistant/'.$router->id.'/profile', [
            'plan_id' => $other->id,
            'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('48h', $other->fresh()->mikrotik_profile);
        $this->assertTrue($router->profiles()->where('name', '48h')->exists());

        $blocked = Platform::plan($user, $zone);
        $blocked->update(['name' => '24 HEURES BIS', 'duration_seconds' => 86400, 'mikrotik_profile' => null]);
        app(HotspotRouter::class)->commands = [];
        $this->actingAs($user)->post('/mikrotiks/assistant/'.$router->id.'/profile', [
            'plan_id' => $blocked->id,
            'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('warning');
        $this->assertNull($blocked->fresh()->mikrotik_profile);
        $this->assertFalse(collect(app(HotspotRouter::class)->commands)->contains(
            fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/profile/add'
        ));
    }

    public function test_pending_tickets_survive_an_offline_router_and_retries_stay_bounded(): void
    {
        [$user, $zone, $plan, $router] = $this->readyRouter();
        $plan->update(['mikrotik_profile' => '24H']);
        $fake = app(HotspotRouter::class);
        $fake->fail('Connexion impossible au routeur.');
        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        $expires = now()->addDay()->startOfSecond();
        $voucher->forceFill(['expires_at' => $expires, 'activated_at' => now()->startOfSecond()])->save();

        $result = app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $this->assertNotNull(Voucher::find($voucher->id));
        $this->assertNotSame('synced', $result->sync_status);
        $this->assertSame($expires->getTimestamp(), $result->fresh()->expires_at->getTimestamp());

        $fake->commands = [];
        $this->actingAs($user)->post('/mikrotiks/assistant/'.$router->id.'/retry')->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/assistant/'.$router->id)
            ->assertSee('Synchronisation en attente')
            ->assertSee('Réessayer');
        $attempts = collect($fake->commands)->filter(
            fn (array $call) => ($call['words'][0] ?? '') === '/system/identity/print'
        )->count();
        $this->assertGreaterThan(0, $attempts);
        $this->assertLessThanOrEqual(3, $attempts);
        $this->assertNotNull(Voucher::find($voucher->id));

        $fake->commands = [];
        $fake->fail('invalid user name or password');
        app(MikrotikService::class)->retryPending($router->fresh(), 3);
        $this->assertSame(1, collect($fake->commands)->filter(
            fn (array $call) => ($call['words'][0] ?? '') === '/system/identity/print'
        )->count());
    }

    public function test_hotspot_user_errors_do_not_drop_the_ticket(): void
    {
        [$user, $zone, $plan, $router] = $this->readyRouter();
        $router->forceFill(['status' => 'online'])->save();
        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];

        $missing = app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $this->assertNotSame('synced', $missing->sync_status);
        $this->assertStringContainsString('Profil non associé', $missing->sync_error);
        $this->assertNotNull(Voucher::find($voucher->id));

        $plan->update(['mikrotik_profile' => '24H']);
        $username = $voucher->username;
        $seen = false;
        $fake = new FakeHotspotRouter(function (array $words) use (&$seen, $username) {
            if ($words[0] === '/ip/hotspot/user/print') {
                return $seen
                    ? [['!type' => '!re', '.id' => '*1', 'name' => $username], ['!type' => '!done']]
                    : [['!type' => '!done']];
            }
            if ($words[0] === '/ip/hotspot/user/add') {
                $seen = true;

                return [['!type' => '!done']];
            }

            return [['!type' => '!done']];
        });
        $this->app->instance(HotspotRouter::class, $fake);
        $voucher->forceFill(['sync_status' => 'pending', 'mikrotik_id' => null])->save();
        app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $adds = collect($fake->commands)->filter(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add')->count();
        $this->assertSame(1, $adds);
        $this->assertSame('synced', $voucher->fresh()->sync_status);
    }

    public function test_automatic_sync_can_be_turned_off_and_destructive_commands_are_refused(): void
    {
        [$user, $zone, $plan, $router] = $this->readyRouter();
        $plan->update(['mikrotik_profile' => '24H']);
        $router->forceFill(['auto_sync' => false, 'status' => 'online'])->save();
        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        $before = count(app(HotspotRouter::class)->commands);

        (new SyncHotspotUser($voucher->id))->handle(app(MikrotikService::class));

        $this->assertSame('pending', $voucher->fresh()->sync_status);
        $this->assertSame($before, count(app(HotspotRouter::class)->commands));

        $this->actingAs($user)->post('/mikrotiks/assistant/'.$router->id.'/auto-sync', ['auto_sync' => '1'])
            ->assertRedirect();
        $this->assertTrue($router->fresh()->auto_sync);

        try {
            app(MikrotikService::class)->guardedCommand($router, ['/system/reboot']);
            $this->fail('Une commande destructive a été acceptée.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Commande refusée.', $exception->getMessage());
        }
    }

    public function test_simulation_is_distinct_from_a_real_client_and_the_super_admin_sees_no_password(): void
    {
        $service = app(MikrotikService::class);
        $this->assertSame('real', $service->connectionMode('10.1.1.1'));
        $this->assertSame('simulation', $service->connectionMode('192.0.2.10'));
        $this->assertSame('simulation', $service->connectionMode('example.com'));
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter);
        $this->assertSame('simulation', app(MikrotikService::class)->connectionMode('10.1.1.1'));

        [$user, $zone] = $this->workspace();
        $router = Platform::router($user, $zone);
        $router->forceFill(['last_error' => 'Connexion impossible', 'status' => 'offline'])->save();
        Platform::seed();
        $admin = User::where('email', 'admin@limetewifi.local')->first();

        $this->actingAs($admin)->get('/admin/mikrotiks')
            ->assertOk()
            ->assertSee($user->tenant->name)
            ->assertSee($zone->name)
            ->assertSee($router->name)
            ->assertSee('offline')
            ->assertSee('Connexion impossible')
            ->assertDontSee('secret-router', false);

        $this->actingAs($user)->get('/admin/mikrotiks')->assertForbidden();
    }

    private function workspace(): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-assistant@example.com');
        $zone = Platform::zone($user, 'Limete Kingabwa');
        $plan = Platform::plan($user, $zone);

        return [$user, $zone, $plan];
    }

    private function readyRouter(): array
    {
        [$user, $zone, $plan] = $this->workspace();
        $router = Platform::router($user, $zone);
        $router->forceFill(['host' => '10.8.0.1', 'status' => 'online'])->save();
        $this->bindProfileRouter();
        app(TenantManager::class)->set($user->tenant_id);
        app(MikrotikService::class)->syncRouter($router->fresh());

        return [$user, $zone, $plan, $router->fresh()];
    }

    private function reachTestStep(User $user): void
    {
        $this->actingAs($user)->post('/mikrotiks/assistant/start');
        $this->actingAs($user)->post('/mikrotiks/assistant/step', ['step' => 1, 'name' => 'MikroTik Limete']);
        $this->actingAs($user)->post('/mikrotiks/assistant/step', ['step' => 2, 'host' => '10.8.0.1', 'api_port' => 8728]);
        $this->actingAs($user)->post('/mikrotiks/assistant/step', [
            'step' => 3,
            'username' => 'admin',
            'password' => 'router-secret-77',
        ]);
    }

    private function bindRichRouter(): FakeHotspotRouter
    {
        $fake = new FakeHotspotRouter(function (array $words) {
            return match ($words[0]) {
                '/system/identity/print' => [['!type' => '!re', 'name' => 'LIMETE-ROUTER'], ['!type' => '!done']],
                '/system/resource/print' => [['!type' => '!re', 'version' => '7.15.3', 'uptime' => '12d', 'cpu-load' => '4'], ['!type' => '!done']],
                '/ip/hotspot/print' => [['!type' => '!re', 'name' => 'hotspot1', 'interface' => 'bridge', 'profile' => 'hsprof1'], ['!type' => '!done']],
                '/ip/hotspot/profile/print' => [['!type' => '!re', '.id' => '*P', 'name' => 'hsprof1'], ['!type' => '!done']],
                '/ip/hotspot/user/profile/print' => [['!type' => '!re', 'name' => '24H', 'rate-limit' => '2M/2M', 'session-timeout' => '1d'], ['!type' => '!re', 'name' => '1H', 'session-timeout' => '1h'], ['!type' => '!done']],
                '/ip/hotspot/active/print' => [['!type' => '!re', '.id' => '*B', 'user' => 'guest', 'address' => '10.5.50.10', 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'uptime' => '3m', 'bytes-in' => '100', 'bytes-out' => '200'], ['!type' => '!done']],
                '/interface/print' => [['!type' => '!re', 'name' => 'ether1', 'type' => 'ether', 'running' => 'true'], ['!type' => '!done']],
                '/ip/address/print' => [['!type' => '!re', 'address' => '10.8.0.1/24', 'interface' => 'bridge'], ['!type' => '!done']],
                '/ip/pool/print' => [['!type' => '!done']],
                '/ip/hotspot/user/print' => [['!type' => '!re', 'name' => 'guest', 'password' => 'should-not-leak', 'profile' => '24H'], ['!type' => '!done']],
                default => [['!type' => '!done']],
            };
        });
        $this->app->instance(HotspotRouter::class, $fake);

        return $fake;
    }

    private function bindProfileRouter(): FakeHotspotRouter
    {
        $added = [];
        $fake = new FakeHotspotRouter(function (array $words) use (&$added) {
            if ($words[0] === '/ip/hotspot/user/profile/add') {
                foreach ($words as $word) {
                    if (str_starts_with($word, '=name=')) {
                        $added[] = substr($word, 6);
                    }
                }

                return [['!type' => '!done']];
            }

            if ($words[0] === '/ip/hotspot/user/profile/print') {
                $rows = [
                    ['!type' => '!re', 'name' => '24H', 'rate-limit' => '2M/2M', 'session-timeout' => '1d'],
                    ['!type' => '!re', 'name' => '24h', 'session-timeout' => '1d'],
                ];
                foreach ($added as $name) {
                    $rows[] = ['!type' => '!re', 'name' => $name, 'session-timeout' => '1d'];
                }
                $rows[] = ['!type' => '!done'];

                return $rows;
            }

            return match ($words[0]) {
                '/system/identity/print' => [['!type' => '!re', 'name' => 'LIMETE-ROUTER'], ['!type' => '!done']],
                '/system/resource/print' => [['!type' => '!re', 'version' => '7.15.3', 'uptime' => '2h'], ['!type' => '!done']],
                '/ip/hotspot/print' => [['!type' => '!re', 'name' => 'hotspot1', 'interface' => 'bridge'], ['!type' => '!done']],
                default => [['!type' => '!done']],
            };
        });
        $this->app->instance(HotspotRouter::class, $fake);

        return $fake;
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
