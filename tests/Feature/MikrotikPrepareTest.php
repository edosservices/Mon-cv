<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Mikrotik;
use App\Models\MikrotikSnapshot;
use App\Services\Mikrotik\HostResolver;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\Mikrotik\MikrotikPreparation;
use App\Services\Mikrotik\MikrotikService;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class MikrotikPrepareTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_prepare_page_reads_nothing_and_shows_the_difference_before_a_change(): void
    {
        [$user, , , $router] = $this->ready();
        $fake = app(HotspotRouter::class);
        $router->forceFill([
            'status' => 'online',
            'identity' => 'LIMETE-ROUTER',
            'routeros_version' => '7.15.3',
            'dns' => 'wifi.limetewifi.com',
            'details' => [
                'dns_name' => 'wifi.limetewifi.com',
                'hotspot_servers' => [['name' => 'hotspot1', 'interface' => 'bridge-hotspot', 'address-pool' => 'hotspot-pool']],
                'walled_garden' => [],
                'interfaces' => [['name' => 'bridge-hotspot']],
                'pools' => [['name' => 'hotspot-pool']],
            ],
        ])->save();

        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=prepare')
            ->assertOk()
            ->assertSee('Préparer mon MikroTik')
            ->assertSee('Connectivité API')
            ->assertSee('Identity')
            ->assertSee('RouterOS')
            ->assertSee('Walled Garden')
            ->assertSee('Portail captif')
            ->assertSee('Profils HotSpot')
            ->assertSee('Vérification finale')
            ->assertSee('VALEUR ACTUELLE')
            ->assertSee('VALEUR PROPOSÉE')
            ->assertSee('IMPACT')
            ->assertSee('COMMANDE ROUTEROS')
            ->assertSee('wifi.limetewifi.com')
            ->assertSee('✓ présent')
            ->assertSee('login.html')
            ->assertSee('Résolution non vérifiée')
            ->assertDontSee('opérationnel')
            ->assertDontSee('secret-router', false);

        $this->assertSame([], $fake->commands);
    }

    public function test_a_change_requires_confirmation_and_reads_before_it_writes(): void
    {
        [$user, , , $router] = $this->ready();
        $fake = app(HotspotRouter::class);

        $this->actingAs($user)->from('/mikrotiks/'.$router->id.'?tab=prepare')->post('/mikrotiks/'.$router->id.'/prepare/apply', [
            'group' => 'dns',
            'dns' => 'wifi.limetewifi.com',
        ])->assertSessionHasErrors('confirm');
        $this->assertSame([], $fake->commands);

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/prepare/apply', [
            'group' => 'dns',
            'dns' => 'wifi.limetewifi.com',
            'confirm' => '1',
        ])->assertRedirect();

        $paths = array_column(array_map(fn (array $call) => $call['words'], $fake->commands), 0);
        $printAt = array_search('/ip/hotspot/profile/print', $paths, true);
        $setAt = array_search('/ip/hotspot/profile/set', $paths, true);
        $this->assertNotFalse($printAt);
        $this->assertNotFalse($setAt);
        $this->assertLessThan($setAt, $printAt);
        $this->assertTrue(MikrotikSnapshot::where('mikrotik_id', $router->id)->where('group', 'dns')->exists());
        $joined = json_encode($fake->commands);
        $this->assertStringNotContainsString('reset-configuration', $joined);
        $this->assertStringNotContainsString('/ip/address/', $joined);
        $this->assertStringNotContainsString('/ip/route/', $joined);
        $this->assertStringNotContainsString('/ip/hotspot/remove', $joined);
    }

    public function test_a_dns_snapshot_can_be_restored_and_a_hotspot_snapshot_cannot_delete_it(): void
    {
        [$user, , , $router] = $this->ready();
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/prepare/apply', [
            'group' => 'dns',
            'dns' => 'wifi.limetewifi.com',
            'confirm' => '1',
        ])->assertRedirect();
        $snapshot = MikrotikSnapshot::where('group', 'dns')->first();
        $this->assertSame('ancien.example', $snapshot->before['dns-name']);

        app(HotspotRouter::class)->commands = [];
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/snapshots/'.$snapshot->id.'/restore')
            ->assertRedirect()
            ->assertSessionHas('status');
        $set = collect(app(HotspotRouter::class)->commands)->first(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/profile/set');
        $this->assertContains('=dns-name=ancien.example', $set['words']);
        $this->assertNotNull($snapshot->fresh()->restored_at);

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/prepare/apply', [
            'group' => 'hotspot',
            'hotspot_name' => 'hotspot2',
            'interface' => 'bridge-hotspot',
            'address_pool' => 'hotspot-pool',
            'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('warning');
        $this->assertFalse(collect(app(HotspotRouter::class)->commands)->contains(
            fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/add'
        ));
    }

    public function test_missing_hotspot_is_created_only_after_confirmation_from_detected_values(): void
    {
        [$user, , , $router] = $this->ready(false);
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=prepare')
            ->assertSee('HotSpot non configuré');

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/prepare/apply', [
            'group' => 'hotspot',
            'hotspot_name' => 'hotspot1',
            'interface' => 'bridge-hotspot',
            'address_pool' => 'hotspot-pool',
            'confirm' => '1',
        ])->assertRedirect();

        $add = collect(app(HotspotRouter::class)->commands)->first(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/add');
        $this->assertNotNull($add);
        $this->assertContains('=interface=bridge-hotspot', $add['words']);
        $this->assertContains('=address-pool=hotspot-pool', $add['words']);
        $snapshot = MikrotikSnapshot::where('group', 'hotspot')->first();
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/snapshots/'.$snapshot->id.'/restore')
            ->assertSessionHas('warning');
        $this->assertFalse(collect(app(HotspotRouter::class)->commands)->contains(
            fn (array $call) => str_contains(implode(' ', $call['words']), 'reset-configuration')
        ));
    }

    public function test_dns_detection_resolution_and_profile_creation_do_not_overwrite(): void
    {
        [$user, , $plan, $router] = $this->ready();
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return ['checked' => false, 'addresses' => [], 'label' => 'Résolution non vérifiée'];
            }
        });

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/prepare/read')->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=prepare')
            ->assertSee('LIMETE-ROUTER')
            ->assertSee('7.15.3')
            ->assertSee('ancien.example')
            ->assertSee('24H')
            ->assertSee('1d');

        $this->actingAs($user)->from('/mikrotiks/'.$router->id.'?tab=prepare')->post('/mikrotiks/'.$router->id.'/prepare/dns', [
            'dns' => 'wifi.limetewifi.com',
        ])->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=prepare')
            ->assertSee('Résolution non vérifiée')
            ->assertDontSee('opérationnel');

        app(HotspotRouter::class)->commands = [];
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/prepare/apply', [
            'group' => 'profile',
            'profile_name' => '24H',
            'session_timeout' => '1d',
            'confirm' => '1',
        ])->assertSessionHas('warning');
        $this->assertFalse(collect(app(HotspotRouter::class)->commands)->contains(
            fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/profile/add'
        ));

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/prepare/apply', [
            'group' => 'profile',
            'profile_name' => 'VIP-24H',
            'session_timeout' => '1d',
            'rate_limit' => '2M/2M',
            'idle_timeout' => '5m',
            'shared_users' => 1,
            'confirm' => '1',
        ])->assertSessionHas('status');
        $created = collect(app(HotspotRouter::class)->commands)->first(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/profile/add');
        $this->assertContains('=name=VIP-24H', $created['words']);
        $this->assertContains('=session-timeout=1d', $created['words']);

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/sync')->assertRedirect();
        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/plan-profile', [
            'plan_id' => $plan->id,
            'mikrotik_profile' => '24H',
        ])->assertRedirect();
        $this->assertSame('24H', $plan->fresh()->mikrotik_profile);
        $this->assertNotSame($plan->name, '24H');
    }

    public function test_verification_creates_and_deletes_a_temporary_user_without_leaking_secrets(): void
    {
        [$user, , , $router] = $this->ready();
        $lines = $this->captureLogs();
        $fake = app(HotspotRouter::class);

        $this->actingAs($user)->from('/mikrotiks/'.$router->id.'?tab=prepare')
            ->post('/mikrotiks/'.$router->id.'/prepare/verify')
            ->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks/'.$router->id.'?tab=prepare')
            ->assertSee('Vérifier ma configuration')
            ->assertSee('LIMETE_TEST_')
            ->assertSee('supprimé')
            ->assertSee('Aucune session pour ce compte de test.')
            ->assertDontSee('secret-router', false)
            ->assertDontSee('temp-secret-value', false);

        $paths = array_map(fn (array $call) => $call['words'][0], $fake->commands);
        $this->assertContains('/ip/hotspot/user/add', $paths);
        $this->assertContains('/ip/hotspot/user/remove', $paths);
        $add = collect($fake->commands)->first(fn (array $call) => $call['words'][0] === '/ip/hotspot/user/add');
        $this->assertTrue(collect($add['words'])->contains(fn (string $word) => str_starts_with($word, '=name=LIMETE_TEST_')));
        $audit = AuditLog::where('action', 'mikrotik.configuration_verified')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('temp-secret-value', $audit->toJson());
        $this->assertStringNotContainsString('secret-router', $audit->toJson());
        $this->assertLogsHideSecrets($lines, ['secret-router', 'temp-secret-value']);
    }

    public function test_pending_tickets_are_not_created_twice_and_another_tenant_is_hidden(): void
    {
        [$user, $zone, $plan, $router] = $this->ready();
        $plan->update(['mikrotik_profile' => '24H']);
        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        $voucher->forceFill(['username' => 'LWRETRY1', 'sync_status' => 'failed', 'mikrotik_id' => null])->save();

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/pending')->assertRedirect();
        $this->assertSame('synced', $voucher->fresh()->sync_status);
        $adds = collect(app(HotspotRouter::class)->commands)->filter(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add')->count();
        $this->assertSame(1, $adds);

        app(HotspotRouter::class)->commands = [];
        $voucher->forceFill(['sync_status' => 'failed', 'mikrotik_id' => null])->save();
        app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $this->assertSame(0, collect(app(HotspotRouter::class)->commands)->filter(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add')->count());
        $this->assertSame('synced', $voucher->fresh()->sync_status);

        $bob = Platform::entrepreneur('Bob Wifi', 'bob-prepare@example.com', 'business');
        $this->actingAs($bob)->get('/mikrotiks/'.$router->id.'?tab=prepare')->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/'.$router->id.'/prepare/verify')->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/'.$router->id.'/prepare/apply', [
            'group' => 'dns',
            'dns' => 'wifi.limetewifi.com',
            'confirm' => '1',
        ])->assertNotFound();
        $this->actingAs($bob)->post('/mikrotiks/'.$router->id.'/pending')->assertNotFound();
        $snapshot = MikrotikSnapshot::withoutGlobalScope('tenant')->first();
        if ($snapshot) {
            $this->actingAs($bob)->post('/mikrotiks/'.$router->id.'/snapshots/'.$snapshot->id.'/restore')->assertNotFound();
        }
    }

    public function test_destructive_commands_are_refused_before_they_reach_the_router(): void
    {
        [$user, , , $router] = $this->ready();
        app(TenantManager::class)->set($user->tenant_id);
        $service = app(MikrotikService::class);
        $fake = app(HotspotRouter::class);

        foreach ([
            ['/system/reset-configuration'],
            ['/ip/address/set', '=.id=*1', '=address=1.2.3.4/24'],
            ['/ip/route/set', '=.id=*1', '=gateway=1.2.3.4'],
            ['/ip/hotspot/remove', '=.id=*1'],
            ['/ip/hotspot/user/profile/remove', '=.id=*1'],
        ] as $words) {
            try {
                $service->guardedCommand($router, $words);
                $this->fail('La commande aurait dû être refusée.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Commande refusée.', $exception->getMessage());
            }
        }
        $this->assertSame([], $fake->commands);

        $missing = app(MikrotikPreparation::class)->portalFiles(sys_get_temp_dir().'/absent-hotspot');
        $this->assertSame('login.html', $missing[0]['file']);
        $this->assertFalse($missing[0]['present']);
    }

    private function ready(bool $withHotspot = true): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-prepare-'.str()->lower(str()->random(4)).'@example.com');
        $zone = Platform::zone($user, 'LIMETE WIFI');
        $plan = Platform::plan($user, $zone);
        $router = Platform::router($user, $zone);
        $retryLooks = 0;
        $fake = new FakeHotspotRouter(function (array $words) use ($withHotspot, &$retryLooks) {
            $path = $words[0];
            if ($path === '/ip/hotspot/user/print') {
                $name = null;
                foreach ($words as $word) {
                    if (str_starts_with($word, '?name=')) {
                        $name = substr($word, 6);
                    }
                }
                if ($name === 'LWRETRY1') {
                    $retryLooks++;
                    if ($retryLooks === 1) {
                        return [['!type' => '!done']];
                    }

                    return [['!type' => '!re', '.id' => '*R', 'name' => 'LWRETRY1'], ['!type' => '!done']];
                }
                if (is_string($name) && str_starts_with($name, 'LIMETE_TEST_')) {
                    return [['!type' => '!re', '.id' => '*T', 'name' => $name, 'password' => 'temp-secret-value', 'profile' => '24H'], ['!type' => '!done']];
                }

                return [['!type' => '!done']];
            }

            return match ($path) {
                '/system/identity/print' => [['!type' => '!re', 'name' => 'LIMETE-ROUTER'], ['!type' => '!done']],
                '/system/resource/print' => [['!type' => '!re', 'version' => '7.15.3', 'uptime' => '1d', 'cpu-load' => '1'], ['!type' => '!done']],
                '/ip/hotspot/print' => $withHotspot
                    ? [['!type' => '!re', 'name' => 'hotspot1', 'interface' => 'bridge-hotspot', 'address-pool' => 'hotspot-pool', 'dns-name' => 'ancien.example'], ['!type' => '!done']]
                    : [['!type' => '!done']],
                '/ip/hotspot/profile/print' => [['!type' => '!re', '.id' => '*P', 'name' => 'hsprof1', 'dns-name' => 'ancien.example'], ['!type' => '!done']],
                '/ip/hotspot/user/profile/print' => [['!type' => '!re', '.id' => '*U', 'name' => '24H', 'session-timeout' => '1d', 'idle-timeout' => '5m', 'rate-limit' => '2M/2M', 'shared-users' => '1'], ['!type' => '!done']],
                '/ip/hotspot/active/print' => [['!type' => '!done']],
                '/ip/hotspot/walled-garden/print' => [['!type' => '!done']],
                '/interface/print' => [['!type' => '!re', 'name' => 'bridge-hotspot', 'type' => 'bridge'], ['!type' => '!done']],
                '/ip/pool/print' => [['!type' => '!re', 'name' => 'hotspot-pool', 'ranges' => '10.5.50.2-10.5.50.254'], ['!type' => '!done']],
                default => [['!type' => '!done']],
            };
        });
        $this->app->instance(HotspotRouter::class, $fake);
        app(TenantManager::class)->set($user->tenant_id);

        return [$user, $zone, $plan, $router];
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
