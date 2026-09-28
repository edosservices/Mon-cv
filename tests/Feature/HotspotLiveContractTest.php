<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class HotspotLiveContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconnection_keeps_the_commercial_expiry_from_the_voucher(): void
    {
        [$user, $zone, $plan] = $this->workspace();
        $start = now()->subHours(2)->startOfSecond();
        $expires = $start->copy()->addDay();
        $voucher = $this->voucher($zone, $plan, $start, $expires, 'LWLIVE1');

        $first = $this->getJson('/hotspot/session/LWLIVE1')->assertOk();
        $second = $this->getJson('/hotspot/session/LWLIVE1')->assertOk();

        $this->assertSame($first->json('expiresAt'), $second->json('expiresAt'));
        $this->assertSame($expires->toIso8601String(), $first->json('expiresAt'));
        $this->assertSame($start->toIso8601String(), $first->json('startedAt'));
        $this->assertSame('24 HEURES', $first->json('planLabel'));
        $this->assertSame(86400, $first->json('planSeconds'));
        $this->assertStringContainsString('/ticket/', (string) $first->json('ticketUrl'));
        $this->assertSame($expires->getTimestamp(), $voucher->fresh()->expires_at->getTimestamp());

        app(TenantManager::class)->set($user->tenant_id);
        app(VoucherGenerator::class)->activate($voucher->fresh());
        $this->assertSame($expires->getTimestamp(), $voucher->fresh()->expires_at->getTimestamp());
    }

    public function test_an_expired_voucher_stays_expired_and_is_not_extended(): void
    {
        [, $zone, $plan, $router] = $this->workspace();
        $expires = now()->subMinute()->startOfSecond();
        $voucher = $this->voucher($zone, $plan, $expires->copy()->subHour(), $expires, 'LWSHORT');
        $voucher->forceFill(['sync_status' => 'synced', 'mikrotik_id' => $router->id])->save();
        $router->update(['status' => 'online']);

        $fake = new FakeHotspotRouter(fn (array $words) => $words[0] === '/ip/hotspot/user/print'
            ? [['!type' => '!re', '.id' => '*A', 'name' => 'LWSHORT'], ['!type' => '!done']]
            : [['!type' => '!done']]);
        $this->app->instance(HotspotRouter::class, $fake);
        $lines = $this->captureLogs();

        $this->getJson('/hotspot/session/LWSHORT')
            ->assertOk()
            ->assertJsonPath('status', 'expired')
            ->assertJsonPath('expiresAt', $expires->toIso8601String())
            ->assertDontSee('2468', false)
            ->assertDontSee('secret-router', false);

        $this->assertSame('expired', $voucher->fresh()->status);
        $this->assertSame($expires->getTimestamp(), $voucher->fresh()->expires_at->getTimestamp());
        $this->assertSame('/ip/hotspot/user/disable', $fake->commands[1]['words'][0]);
        $this->assertLogsHideSecrets($lines, ['2468', 'secret-router']);
    }

    public function test_the_portal_script_exposes_the_ticket_link_and_no_secret(): void
    {
        config(['limete.payments.airtel_money.api_key' => 'super-secret-airtel']);
        [, $zone, $plan] = $this->workspace();
        $this->voucher($zone, $plan, now()->subHour(), now()->addDay(), 'LWSCRIPT');

        $script = $this->get('/hotspot/session.js?username=LWSCRIPT')
            ->assertOk()
            ->assertHeader('content-type', 'application/javascript; charset=UTF-8');

        $body = $script->getContent();
        $this->assertStringContainsString('window.LIMETE_SESSION', $body);
        $this->assertStringContainsString('expiresAt', $body);
        $this->assertStringContainsString('/ticket/', $body);
        $this->assertStringNotContainsString('2468', $body);
        $this->assertStringNotContainsString('secret-router', $body);
        $this->assertStringNotContainsString('super-secret-airtel', $body);

        $this->getJson('/hotspot/session/INCONNU')->assertNotFound();
    }

    public function test_a_shared_username_is_not_given_to_another_tenant(): void
    {
        [, $zone, $plan] = $this->workspace();
        $this->voucher($zone, $plan, now(), now()->addDay(), 'LWSAME');

        $bob = Platform::entrepreneur('Bob Wifi', 'bob-live@example.com');
        $foreign = Platform::zone($bob, 'Kasa');
        $planB = Platform::plan($bob, $foreign);
        $this->voucher($foreign, $planB, now(), now()->addDay(), 'LWSAME');

        $this->getJson('/hotspot/session/LWSAME')->assertNotFound();
    }

    public function test_router_ticket_and_disconnect_routes_stay_inside_the_tenant(): void
    {
        [$alice, $zone, $plan, $router] = $this->workspace();
        $voucher = $this->voucher($zone, $plan, now(), now()->addDay(), 'LWMINE');
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-routes@example.com');
        $foreignZone = Platform::zone($bob, 'Kasa');
        $foreignRouter = Platform::router($bob, $foreignZone);
        $foreignPlan = Platform::plan($bob, $foreignZone);
        $foreignVoucher = $this->voucher($foreignZone, $foreignPlan, now(), now()->addDay(), 'LWBOB');

        $this->actingAs($alice)->post('/mikrotiks/'.$foreignRouter->id.'/test')->assertNotFound();
        $this->actingAs($alice)->post('/vouchers/'.$foreignVoucher->id.'/sync')->assertNotFound();
        $this->actingAs($alice)->post('/active-users/disconnect', [
            'mikrotik_id' => $foreignRouter->id,
            'active_id' => '*1',
            'username' => 'LWBOB',
        ])->assertNotFound();

        $this->actingAs($alice)->get('/mikrotiks')
            ->assertOk()
            ->assertSee('TESTER LA CONNEXION')
            ->assertSee('Identity')
            ->assertSee('Dernière vérification')
            ->assertSee(route('shop.show', $zone->slug), false)
            ->assertSee('/hotspot/session.js?username=$(username)', false)
            ->assertDontSee('secret-router', false);

        $this->assertNotNull($router->id);
        $this->assertNotNull($voucher->id);
    }

    public function test_active_users_show_the_commercial_expiry_from_the_voucher(): void
    {
        [$user, $zone, $plan, $router] = $this->workspace();
        $router->update(['status' => 'online']);
        $expires = now()->addHours(22)->startOfSecond();
        $voucher = $this->voucher($zone, $plan, now()->subHours(2), $expires, 'LWACTIVE');

        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter(fn (array $words) => $words[0] === '/ip/hotspot/active/print'
            ? [['!type' => '!re', '.id' => '*B', 'user' => 'LWACTIVE', 'address' => '10.5.50.9', 'uptime' => '12m', 'session-time-left' => '23h50m'], ['!type' => '!done']]
            : [['!type' => '!done']]));

        $this->actingAs($user)->get('/active-users')
            ->assertOk()
            ->assertSee('LWACTIVE')
            ->assertSee('10.5.50.9')
            ->assertSee('12m')
            ->assertSee('24 HEURES')
            ->assertSee('Expiration')
            ->assertSee($expires->timezone(config('app.timezone'))->format('d/m/Y H:i'))
            ->assertDontSee('23h50m')
            ->assertDontSee('secret-router', false);

        $this->assertSame($expires->getTimestamp(), $voucher->fresh()->expires_at->getTimestamp());
    }

    private function workspace(): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-live-'.str()->lower(str()->random(4)).'@example.com');
        $zone = Platform::zone($user, 'Limete');
        $plan = Platform::plan($user, $zone);
        $plan->update(['mikrotik_profile' => '24H', 'duration_seconds' => 86400, 'name' => '24 HEURES']);
        $router = Platform::router($user, $zone);

        return [$user, $zone, $plan->fresh(), $router];
    }

    private function voucher($zone, $plan, $start, $expires, string $username): Voucher
    {
        app(TenantManager::class)->set($zone->tenant_id);

        return Voucher::create([
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'public_token' => str()->random(40),
            'username' => $username,
            'password' => '2468',
            'status' => 'active',
            'activated_at' => $start,
            'expires_at' => $expires,
            'price_amount' => 1000,
            'currency' => 'CDF',
            'sync_status' => 'pending',
        ]);
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
