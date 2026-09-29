<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Mikrotik;
use App\Models\SaasPlan;
use App\Models\MikrotikProfile;
use App\Models\Permission;
use App\Models\PlanMikrotikProfile;
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
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class MikrotikManageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_entrepreneur_can_add_a_router_from_the_full_form(): void
    {
        [$user, $zone] = $this->workspace(false);
        $this->bindRichRouter();

        $this->actingAs($user)->get('/mikrotiks/create')
            ->assertOk()
            ->assertSee('Ajouter un MikroTik')
            ->assertSee('Connectez et configurez votre routeur MikroTik pour votre WiFi Zone.')
            ->assertSee('Informations générales')
            ->assertSee('Connexion RouterOS')
            ->assertSee('value="8728"', false)
            ->assertSee('value="8729"', false)
            ->assertSee('type="password"', false);

        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, [
            'dns' => 'wifi.limetewifi.com',
            'description' => 'Routeur de la zone',
            'connection_type' => 'api',
            'timeout' => 8,
        ]))->assertRedirect();

        $router = Mikrotik::where('host', '10.8.0.1')->first();
        $this->assertSame('wifi.limetewifi.com', $router->dns);
        $this->assertSame('LIMETE-ROUTER', $router->identity);
        $this->assertSame('7.15.3', $router->routeros_version);
        $this->assertSame('arm', $router->architecture);
        $this->assertSame('RB951', $router->board);
        $this->assertSame('hotspot1', $router->hotspot_server);
        $this->assertTrue($router->is_active);
    }

    public function test_a_client_cannot_open_or_change_a_router(): void
    {
        [$user] = $this->workspace();
        $router = Mikrotik::first();
        $client = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Client->value)->first()->id,
            'name' => 'Client',
            'email' => 'client-manage@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);

        $this->actingAs($client)->get('/mikrotiks/'.$router->id)->assertForbidden();
        $this->actingAs($client)->post('/mikrotiks/'.$router->id.'/sync')->assertForbidden();
        $this->actingAs($client)->delete('/mikrotiks/'.$router->id)->assertForbidden();
    }

    public function test_staff_access_follows_the_mikrotik_permission(): void
    {
        [$user] = $this->workspace();
        $router = Mikrotik::first();
        $staff = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Staff->value)->first()->id,
            'name' => 'Staff',
            'email' => 'staff-manage@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);

        $this->actingAs($staff)->get('/mikrotiks/'.$router->id)->assertForbidden();
        $staff->permissions()->attach(Permission::where('slug', 'mikrotiks.manage')->first());
        $this->actingAs($staff)->get('/mikrotiks/'.$router->id)->assertOk()->assertSee('Vue générale');
    }

    public function test_another_tenant_receives_not_found_for_every_router_action(): void
    {
        [$alice] = $this->workspace();
        $router = Mikrotik::where('tenant_id', $alice->tenant_id)->first();
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-manage@example.com', 'business');
        $foreignZone = Platform::zone($bob, 'Kasa');
        Platform::plan($bob, $foreignZone);

        $this->actingAs($bob)->get('/mikrotiks/'.$router->id)->assertNotFound();
        $this->actingAs($bob)->put('/mikrotiks/'.$router->id, [
            'name' => 'Pris',
            'wifi_zone_id' => $foreignZone->id,
            'host' => '10.9.9.9',
            'api_port' => 8728,
            'username' => 'admin',
        ])->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/'.$router->id.'/test')->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/'.$router->id.'/sync')->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/'.$router->id.'/pending')->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/'.$router->id.'/disconnect', ['active_id' => '*1'])->assertNotFound();
        $this->actingAs($bob)->delete('/mikrotiks/'.$router->id)->assertNotFound();
        $this->assertNotNull(Mikrotik::withoutGlobalScope('tenant')->find($router->id));
    }

    public function test_connection_test_marks_online_or_offline_without_the_password(): void
    {
        [$user, , , $router] = $this->ready();
        $lines = $this->captureLogs();

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/test')->assertRedirect();
        $router->refresh();
        $this->assertSame('online', $router->status);
        $this->assertSame('LIMETE-ROUTER', $router->identity);
        $this->assertSame('7.15.3', $router->routeros_version);
        $this->assertNotNull($router->last_seen_at);

        $secret = $router->password;
        app(HotspotRouter::class)->fail('Connexion impossible au routeur. '.$secret);
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/test')->assertRedirect();
        $router->refresh();
        $this->assertSame('offline', $router->status);
        $this->assertStringNotContainsString($secret, (string) $router->last_error);
        $this->actingAs($user)->get('/mikrotiks/'.$router->id)
            ->assertSee('● HORS LIGNE')
            ->assertSee('Connexion impossible')
            ->assertDontSee($secret, false);
        $this->assertLogsHideSecrets($lines, [$secret, 'should-not-leak']);
    }

    public function test_sync_stores_detected_router_hotspot_profiles_interfaces_addresses_users_and_sessions(): void
    {
        [$user, , , $router] = $this->ready();

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/sync')
            ->assertRedirect()
            ->assertSessionHas('status');

        $router->refresh();
        $this->assertSame('LIMETE-ROUTER', $router->identity);
        $this->assertSame('7.15.3', $router->routeros_version);
        $this->assertSame('hotspot1', $router->hotspot_server);
        $this->assertSame('wifi.limetewifi.com', $router->dns);
        $this->assertSame('ether1', $router->details['interfaces'][0]['name']);
        $this->assertSame('192.168.88.1/24', $router->details['addresses'][0]['address']);
        $this->assertSame('bridge', $router->details['addresses'][0]['interface']);
        $this->assertSame('guest', $router->details['users'][0]['name']);
        $this->assertArrayNotHasKey('password', $router->details['users'][0]);
        $this->assertSame('*B', $router->details['sessions'][0]['.id']);
        $this->assertSame('24H', MikrotikProfile::where('mikrotik_id', $router->id)->where('name', '24H')->first()->name);

        $page = $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=overview');
        $page->assertSee('Synchronisation terminée', false);
        $page->assertSee('LIMETE-ROUTER')->assertSee('7.15.3')->assertSee('hotspot1')->assertSee('wifi.limetewifi.com');

        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=profiles')
            ->assertSee('Profils HotSpot')->assertSee('24H')->assertSee('1d')->assertSee('2M/2M');
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=interfaces')
            ->assertSee('ether1')->assertSee('bridge');
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=ip')
            ->assertSee('192.168.88.1/24')->assertSee('bridge');
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=users')
            ->assertSee('guest')->assertSee('24H')->assertDontSee('should-not-leak', false);
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=sessions')
            ->assertSee('AA:BB:CC:DD:EE:FF')->assertSee('Déconnecter');
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=hotspot')
            ->assertSee('Configuration HotSpot')->assertSee('bridge-hotspot')->assertSee('https://wifi.limetewifi.com/login');
        $this->actingAs($user)->get('/mikrotiks')
            ->assertSee('Gérer')->assertSee('wifi.limetewifi.com')->assertDontSee('should-not-leak', false);
    }

    public function test_missing_detection_is_labeled_and_dns_can_be_saved_by_the_entrepreneur(): void
    {
        [$user, $zone] = $this->workspace(false);
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter(function (array $words) {
            return match ($words[0]) {
                '/system/identity/print' => [['!type' => '!re', 'name' => 'SEUL'], ['!type' => '!done']],
                '/system/resource/print' => [['!type' => '!re', 'version' => '7.1'], ['!type' => '!done']],
                default => [['!type' => '!done']],
            };
        }));

        $this->actingAs($user)->from('/mikrotiks/create')->post('/mikrotiks/probe', [
            'host' => '10.8.0.1',
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'router-secret-77',
        ])->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/create')
            ->assertSee('Informations détectées')
            ->assertSee('Non détecté')
            ->assertSee('DNS non configuré');

        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, ['dns' => 'wifi.limetewifi.com']))->assertRedirect();
        $router = Mikrotik::where('host', '10.8.0.1')->first();
        $this->assertSame('wifi.limetewifi.com', $router->dns);
        $this->assertNull($router->details['dns_name']);
    }

    public function test_a_plan_maps_to_a_different_routeros_profile_name(): void
    {
        [$user, , $plan, $router] = $this->ready();
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/sync')->assertRedirect();
        MikrotikProfile::where('mikrotik_id', $router->id)->where('name', '24H')->update(['name' => 'VIP-24H']);

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/plan-profile', [
            'plan_id' => $plan->id,
            'mikrotik_profile' => 'VIP-24H',
        ])->assertRedirect();

        $link = PlanMikrotikProfile::where('plan_id', $plan->id)->where('mikrotik_id', $router->id)->first();
        $this->assertNotNull($link);
        $this->assertSame('VIP-24H', $link->profile->name);
        $this->assertSame('VIP-24H', $plan->fresh()->mikrotik_profile);
        $this->assertNotSame($plan->name, $link->profile->name);

        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=plans')
            ->assertSee('Association des forfaits')
            ->assertSee('24 HEURES')
            ->assertSee('VIP-24H');
    }

    public function test_an_unmapped_or_unknown_profile_does_not_create_a_hotspot_user(): void
    {
        [$user, $zone, $plan, $router] = $this->ready();
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/sync')->assertRedirect();
        $router->refresh();
        $router->forceFill(['status' => 'online'])->save();
        $fake = app(HotspotRouter::class);
        $fake->commands = [];
        app(TenantManager::class)->set($user->tenant_id);

        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        $result = app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $this->assertNotSame('synced', $result->sync_status);
        $this->assertNull($result->mikrotik_id);
        $this->assertStringContainsString('Profil non associé', $result->sync_error);
        $this->assertFalse(collect($fake->commands)->contains(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add'));

        $plan->update(['mikrotik_profile' => 'ABSENT']);
        $again = app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $this->assertNotSame('synced', $again->sync_status);
        $this->assertStringContainsString('introuvable', $again->sync_error);
    }

    public function test_the_password_stays_encrypted_and_out_of_html_json_and_logs(): void
    {
        [$user, $zone] = $this->workspace(false);
        $this->bindRichRouter();
        $lines = $this->captureLogs();

        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone))->assertRedirect();
        $router = Mikrotik::where('host', '10.8.0.1')->first();
        $stored = DB::table('mikrotiks')->where('id', $router->id)->value('password');
        $this->assertNotSame('router-secret-77', $stored);
        $this->assertStringNotContainsString('router-secret-77', (string) $stored);
        $this->assertSame('router-secret-77', $router->password);

        $html = $this->actingAs($user)->get('/mikrotiks/'.$router->id)->assertOk();
        $html->assertDontSee('router-secret-77', false);
        $html->assertDontSee('should-not-leak', false);
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'/edit')
            ->assertDontSee('router-secret-77', false)
            ->assertSee('type="password"', false);

        $user->tenant->currentSubscription->update([
            'saas_plan_id' => SaasPlan::where('code', 'pro')->first()->id,
        ]);
        $user->unsetRelations();
        Sanctum::actingAs($user);
        $json = $this->getJson('/api/v1/mikrotiks')->assertOk()->getContent();
        $this->assertStringNotContainsString('router-secret-77', $json);
        $this->assertStringNotContainsString('should-not-leak', $json);

        $audit = AuditLog::query()->where('resource_id', $router->id)->get();
        $this->assertTrue($audit->contains(fn (AuditLog $log) => $log->action === 'mikrotik.created'));
        $this->assertStringNotContainsString('router-secret-77', $audit->toJson());
        $this->assertStringNotContainsString('should-not-leak', json_encode($router->details));
        $this->assertLogsHideSecrets($lines, ['router-secret-77', 'should-not-leak']);
    }

    public function test_an_offline_sale_stays_unsynced_until_a_pending_retry_and_is_not_duplicated(): void
    {
        [$user, $zone, $plan, $router] = $this->ready();
        $plan->update(['mikrotik_profile' => '24H']);
        $fake = app(HotspotRouter::class);
        $fake->fail('Connexion impossible au routeur.');

        $this->actingAs($user)->post('/vouchers', [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'count' => 1,
        ])->assertRedirect()->assertSessionHas('warning');

        $voucher = Voucher::first();
        $this->assertNull($voucher->mikrotik_id);
        $this->assertSame('failed', $voucher->sync_status);
        $this->actingAs($user)->get('/vouchers/'.$voucher->id)
            ->assertSee('Ticket créé, synchronisation MikroTik en attente.')
            ->assertDontSee('router-secret-77', false);

        $this->app->instance(HotspotRouter::class, $this->richRouter());
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/pending')->assertRedirect();
        $voucher->refresh();
        $this->assertSame('synced', $voucher->sync_status);
        $this->assertSame($router->id, $voucher->mikrotik_id);

        $adds = fn () => collect(app(HotspotRouter::class)->commands)
            ->filter(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add')
            ->count();
        $this->assertSame(1, $adds());

        app(HotspotRouter::class)->commands = [];
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/pending')->assertRedirect();
        $this->assertSame(0, $adds());

        $present = new FakeHotspotRouter(function (array $words) use ($voucher) {
            if ($words[0] === '/ip/hotspot/user/print') {
                return [['!type' => '!re', '.id' => '*1', 'name' => $voucher->username], ['!type' => '!done']];
            }

            return [['!type' => '!done']];
        });
        $this->app->instance(HotspotRouter::class, $present);
        $voucher->forceFill(['sync_status' => 'failed', 'mikrotik_id' => null])->save();
        app(TenantManager::class)->set($user->tenant_id);
        $router->forceFill(['status' => 'online'])->save();
        app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $this->assertSame('synced', $voucher->fresh()->sync_status);
        $this->assertFalse(collect($present->commands)->contains(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add'));
    }

    public function test_disconnect_runs_on_the_router_and_reports_a_clear_error_when_offline(): void
    {
        [$user, , , $router] = $this->ready();
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/sync')->assertRedirect();
        $fake = app(HotspotRouter::class);

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/disconnect', [
            'active_id' => '*B',
            'username' => 'guest',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertTrue(collect($fake->commands)->contains(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/active/remove'));
        $this->assertTrue(AuditLog::where('action', 'session.disconnected')->exists());

        $fake->fail('Connexion impossible au routeur.');
        $this->actingAs($user)->from('/mikrotiks/'.$router->id.'?tab=sessions')
            ->post('/mikrotiks/'.$router->id.'/disconnect', ['active_id' => '*B'])
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_starter_allows_one_router_and_business_allows_several(): void
    {
        [$user, $zone] = $this->workspace();
        $this->bindRichRouter();
        $this->actingAs($user)->post('/mikrotiks', $this->payload($zone, [
            'name' => 'Deuxième',
            'host' => '10.9.9.9',
        ]))->assertSessionHasErrors('name');

        $business = Platform::entrepreneur('Biz Wifi', 'biz-manage@example.com', 'business');
        $bizZone = Platform::zone($business, 'Gombe');
        $this->actingAs($business)->post('/mikrotiks', $this->payload($bizZone, ['name' => 'Un', 'host' => '10.1.0.1']))->assertRedirect();
        $this->actingAs($business)->post('/mikrotiks', $this->payload($bizZone, ['name' => 'Deux', 'host' => '10.1.0.2']))->assertRedirect();
        $this->assertSame(2, Mikrotik::withoutGlobalScope('tenant')->where('tenant_id', $business->tenant_id)->count());
    }

    public function test_the_entrepreneur_selects_one_hotspot_and_portal_commands_are_explicit(): void
    {
        [$user, , , $router] = $this->ready();
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/sync')->assertRedirect();
        $router->refresh();
        $router->forceFill([
            'details' => array_merge($router->details, [
                'hotspot_servers' => [
                    ['name' => 'hotspot1', 'interface' => 'bridge-hotspot', 'address-pool' => 'hotspot-pool'],
                    ['name' => 'hotspot2', 'interface' => 'wlan1', 'address-pool' => 'pool-2'],
                ],
            ]),
            'hotspot_server' => null,
        ])->save();

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/hotspot', ['hotspot_server' => 'hotspot2'])
            ->assertRedirect();
        $this->assertSame('hotspot2', $router->fresh()->hotspot_server);
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/hotspot', ['hotspot_server' => 'invente'])
            ->assertSessionHas('warning');

        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=portal')
            ->assertSee('Portail captif')
            ->assertSee('login.html')
            ->assertSee('walled-garden')
            ->assertSee('Configuration à appliquer au MikroTik');

        $before = count(app(HotspotRouter::class)->commands);
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/portal')->assertRedirect();
        $paths = array_map(fn (array $call) => $call['words'][0], array_slice(app(HotspotRouter::class)->commands, $before));
        $this->assertContains('/ip/hotspot/walled-garden/add', $paths);
        $this->assertNotContains('/system/reset-configuration', $paths);
    }

    public function test_api_ssl_uses_the_same_client_with_the_secure_flag(): void
    {
        [$user, , , $router] = $this->ready();
        $router->forceFill([
            'connection_type' => 'api-ssl',
            'api_ssl_port' => 8729,
            'timeout' => 9,
        ])->save();

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/test')->assertRedirect();
        $call = app(HotspotRouter::class)->commands[0];
        $this->assertTrue($call['secure']);
        $this->assertSame(8729, $call['port']);
        $this->assertSame(9, $call['timeout']);
    }

    private function workspace(bool $withRouter = true): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-manage-'.str()->lower(str()->random(4)).'@example.com');
        $zone = Platform::zone($user, 'LIMETE WIFI');
        $plan = Platform::plan($user, $zone);
        $router = $withRouter ? Platform::router($user, $zone) : null;

        return [$user, $zone, $plan, $router];
    }

    private function ready(): array
    {
        [$user, $zone, $plan, $router] = $this->workspace();
        $this->bindRichRouter();
        app(TenantManager::class)->set($user->tenant_id);

        return [$user, $zone, $plan, $router];
    }

    private function bindRichRouter(): FakeHotspotRouter
    {
        $fake = $this->richRouter();
        $this->app->instance(HotspotRouter::class, $fake);

        return $fake;
    }

    private function richRouter(): FakeHotspotRouter
    {
        return new FakeHotspotRouter(function (array $words) {
            return match ($words[0]) {
                '/system/identity/print' => [['!type' => '!re', 'name' => 'LIMETE-ROUTER'], ['!type' => '!done']],
                '/system/resource/print' => [['!type' => '!re', 'version' => '7.15.3', 'uptime' => '12d', 'cpu-load' => '18', 'free-memory' => '681574400', 'total-memory' => '1073741824', 'architecture-name' => 'arm', 'board-name' => 'RB951', 'free-hdd-space' => '10000000', 'total-hdd-space' => '16000000'], ['!type' => '!done']],
                '/ip/hotspot/print' => [['!type' => '!re', 'name' => 'hotspot1', 'interface' => 'bridge-hotspot', 'profile' => 'hsprof1', 'address-pool' => 'hotspot-pool', 'dns-name' => 'wifi.limetewifi.com', 'addresses' => '10.5.50.1/24'], ['!type' => '!done']],
                '/ip/hotspot/profile/print' => [['!type' => '!re', '.id' => '*P', 'name' => 'hsprof1', 'dns-name' => 'wifi.limetewifi.com'], ['!type' => '!done']],
                '/ip/hotspot/user/profile/print' => [['!type' => '!re', 'name' => '24H', 'rate-limit' => '2M/2M', 'session-timeout' => '1d', 'idle-timeout' => '5m', 'keepalive-timeout' => '2m', 'shared-users' => '1'], ['!type' => '!re', 'name' => '1H', 'session-timeout' => '1h'], ['!type' => '!done']],
                '/ip/hotspot/active/print' => [['!type' => '!re', '.id' => '*B', 'user' => 'guest', 'address' => '10.5.50.10', 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'uptime' => '3m', 'session-time-left' => '23h', 'bytes-in' => '100', 'bytes-out' => '200', 'server' => 'hotspot1'], ['!type' => '!done']],
                '/ip/hotspot/user/print' => [['!type' => '!re', 'name' => 'guest', 'password' => 'should-not-leak', 'profile' => '24H', 'uptime' => '3m', 'bytes-in' => '10', 'bytes-out' => '20', 'comment' => 'limete'], ['!type' => '!done']],
                '/interface/print' => [['!type' => '!re', 'name' => 'ether1', 'type' => 'ether', 'running' => 'true', 'mac-address' => '11:22:33:44:55:66', 'rx-byte' => '5', 'tx-byte' => '6'], ['!type' => '!re', 'name' => 'bridge', 'type' => 'bridge'], ['!type' => '!done']],
                '/ip/address/print' => [['!type' => '!re', 'address' => '192.168.88.1/24', 'interface' => 'bridge', 'network' => '192.168.88.0'], ['!type' => '!done']],
                '/ip/pool/print' => [['!type' => '!re', 'name' => 'hotspot-pool', 'ranges' => '10.5.50.2-10.5.50.254'], ['!type' => '!done']],
                default => [['!type' => '!done']],
            };
        });
    }

    private function payload($zone, array $overrides = []): array
    {
        return array_merge([
            'name' => 'MikroTik Limete',
            'wifi_zone_id' => $zone->id,
            'host' => '10.8.0.1',
            'api_port' => 8728,
            'api_ssl_port' => 8729,
            'username' => 'admin',
            'password' => 'router-secret-77',
            'is_active' => '1',
        ], $overrides);
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
