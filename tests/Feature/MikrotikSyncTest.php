<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\MikrotikProfile;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\Mikrotik\MikrotikService;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class MikrotikSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_voucher_is_created_on_the_router_and_the_link_is_stored_only_after_success(): void
    {
        [$user, $zone, $plan, $router] = $this->workspace();
        $plan->update(['mikrotik_profile' => '24H']);
        $fake = $this->bindRouter();
        $lines = $this->captureLogs();

        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        $voucher->forceFill(['username' => 'LW8F42', 'password' => '7391'])->save();

        $synced = app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));

        $this->assertSame('synced', $synced->sync_status);
        $this->assertSame($router->id, $synced->mikrotik_id);
        $this->assertNull($synced->sync_error);
        $add = collect($fake->commands)->first(fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add');
        $this->assertNotNull($add);
        $this->assertContains('=name=LW8F42', $add['words']);
        $this->assertContains('=password=7391', $add['words']);
        $this->assertContains('=profile=24H', $add['words']);
        $this->assertLogsHideSecrets($lines, ['7391', 'secret-router']);
    }

    public function test_an_offline_router_keeps_the_voucher_unsynced_without_a_router_id(): void
    {
        [, $zone, $plan] = $this->workspace();
        $plan->update(['mikrotik_profile' => '24H']);
        $fake = $this->bindRouter();
        $fake->fail('Connexion impossible au routeur.');

        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        $result = app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));

        $this->assertNotNull(Voucher::withoutGlobalScope('tenant')->find($voucher->id));
        $this->assertNull($result->mikrotik_id);
        $this->assertSame('failed', $result->sync_status);
        $this->assertStringContainsString('Connexion impossible', $result->sync_error);
        $this->assertNotSame('synced', $result->sync_status);
    }

    public function test_profiles_are_stored_for_the_owning_tenant_only(): void
    {
        [$user, , , $router] = $this->workspace();
        $other = Platform::entrepreneur('Bob Wifi', 'bob-profiles@example.com');
        $fake = new FakeHotspotRouter(fn (array $words) => $words[0] === '/ip/hotspot/user/profile/print'
            ? [['!type' => '!re', 'name' => '24H', 'rate-limit' => '2M/2M', 'shared-users' => '1'], ['!type' => '!done']]
            : [['!type' => '!done']]);
        $this->app->instance(HotspotRouter::class, $fake);
        app(TenantManager::class)->set($user->tenant_id);

        app(MikrotikService::class)->getHotspotProfiles($router);

        $this->assertSame('24H', MikrotikProfile::first()->name);
        app(TenantManager::class)->set($other->tenant_id);
        $this->assertSame(0, MikrotikProfile::count());
    }

    public function test_connection_test_reports_online_offline_and_error_without_the_password(): void
    {
        [$user, , , $router] = $this->workspace();
        $fake = new FakeHotspotRouter(fn (array $words) => match ($words[0]) {
            '/system/identity/print' => [['!type' => '!re', 'name' => 'kingabwa-core'], ['!type' => '!done']],
            '/system/resource/print' => [['!type' => '!re', 'version' => '7.15.3'], ['!type' => '!done']],
            default => [['!type' => '!done']],
        });
        $this->app->instance(HotspotRouter::class, $fake);

        $service = app(MikrotikService::class);
        $online = $service->testConnection($router->fresh());
        $this->assertSame('online', $online->status);
        $this->assertSame('kingabwa-core', $online->identity);
        $this->assertSame('7.15.3', $online->routeros_version);

        $fake->fail('Connexion impossible au routeur. secret-router');
        $offline = $service->testConnection($router->fresh());
        $this->assertSame('offline', $offline->status);
        $this->assertStringNotContainsString('secret-router', $offline->last_error);

        $fake->fail('invalid user name or password (secret-router)');
        $error = $service->testConnection($router->fresh());
        $this->assertSame('error', $error->status);
        $this->assertStringNotContainsString('secret-router', $error->last_error);

        $this->actingAs($user)->post('/mikrotiks/'.$router->id.'/test')->assertRedirect();
        $this->actingAs($user)->get('/mikrotiks')->assertOk()->assertSee('TESTER LA CONNEXION')->assertDontSee('secret-router', false);
    }

    public function test_disable_and_disconnect_send_the_router_commands(): void
    {
        [$user, , , $router] = $this->workspace();
        $fake = new FakeHotspotRouter(fn (array $words) => $words[0] === '/ip/hotspot/user/print'
            ? [['!type' => '!re', '.id' => '*A', 'name' => 'LW8F42'], ['!type' => '!done']]
            : [['!type' => '!done']]);
        $this->app->instance(HotspotRouter::class, $fake);
        app(TenantManager::class)->set($user->tenant_id);
        $service = app(MikrotikService::class);

        $service->disableHotspotUser($router, 'LW8F42');
        $service->disconnectActiveUser($router, '*B');

        $paths = array_map(fn (array $call) => $call['words'][0], $fake->commands);
        $this->assertContains('/ip/hotspot/user/disable', $paths);
        $this->assertContains('/ip/hotspot/active/remove', $paths);
    }

    public function test_another_entrepreneur_cannot_use_the_router_from_the_web_or_the_api(): void
    {
        [$alice, $zone, $plan, $router] = $this->workspace('Alice Wifi', 'alice-sync@example.com', 'pro');
        $plan->update(['mikrotik_profile' => '24H']);
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-sync@example.com', 'pro');
        $foreign = Platform::router($bob, Platform::zone($bob, 'Zone Bob'));
        $this->bindRouter();

        $this->actingAs($alice)->post('/mikrotiks/'.$foreign->id.'/test')->assertNotFound();
        $this->actingAs($alice)->post('/mikrotiks/'.$foreign->id.'/profiles')->assertNotFound();
        $this->actingAs($alice)->get('/dashboard')->assertOk();

        Sanctum::actingAs($alice);
        $this->postJson('/api/v1/mikrotiks/'.$foreign->id.'/disconnect-user', ['active_id' => '*1'])->assertNotFound();
        $this->postJson('/api/v1/mikrotiks/test', ['mikrotik_id' => $foreign->id])->assertNotFound();
        $this->getJson('/api/v1/mikrotiks')
            ->assertOk()
            ->assertJsonFragment(['id' => $router->id])
            ->assertDontSee('secret-router', false);

        app(TenantManager::class)->set($alice->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        $this->expectException(\RuntimeException::class);
        app(MikrotikService::class)->createHotspotUser(
            Mikrotik::withoutGlobalScope('tenant')->find($foreign->id),
            $voucher,
        );
    }

    public function test_the_voucher_screen_does_not_claim_a_router_account_when_sync_fails(): void
    {
        [$user, $zone, $plan] = $this->workspace();
        $plan->update(['mikrotik_profile' => '24H']);
        $fake = $this->bindRouter();
        $fake->fail('Connexion impossible au routeur.');

        $this->actingAs($user)->post('/vouchers', [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'count' => 1,
        ])->assertRedirect();

        $voucher = Voucher::first();
        $this->assertNull($voucher->mikrotik_id);
        $this->assertSame('failed', $voucher->sync_status);

        $this->actingAs($user)->get('/vouchers')
            ->assertOk()
            ->assertSee('Non synchronisé')
            ->assertDontSee('Créé sur le MikroTik')
            ->assertDontSee('secret-router', false);
    }

    private function workspace(string $company = 'Alice Wifi', string $email = 'alice-wifi@example.com', string $planCode = 'starter'): array
    {
        $user = Platform::entrepreneur($company, $email, $planCode);
        $zone = Platform::zone($user);
        $plan = Platform::plan($user, $zone);
        $router = Platform::router($user, $zone);
        app(TenantManager::class)->set($user->tenant_id);

        return [$user, $zone, $plan, $router];
    }

    private function bindRouter(): FakeHotspotRouter
    {
        $fake = new FakeHotspotRouter;
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
