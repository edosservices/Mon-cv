<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class HotspotProfileSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_profile_is_created_with_address_pool_and_parent_queue(): void
    {
        [$user, $zone, $fake] = $this->router();

        $this->actingAs($user)->post('/entrepreneur/profils', $this->payload($zone->id, [
            'name' => '1Jours',
            'address_pool' => 'pool1',
            'parent_queue' => 'parent1',
            'sync' => '1',
        ]))->assertRedirect(route('entrepreneur.profiles'));

        $words = implode(' ', $this->added($fake)[0]['words']);
        $this->assertStringContainsString('=name=1Jours', $words);
        $this->assertStringContainsString('=address-pool=pool1', $words);
        $this->assertStringContainsString('=parent-queue=parent1', $words);
        $this->assertStringContainsString('=session-timeout=24h', $words);
        $this->assertStringContainsString('=rate-limit=10M/10M', $words);
        $this->assertStringNotContainsString('secret-router', $words);

        $plan = Plan::withoutGlobalScope('tenant')->where('mikrotik_profile', '1Jours')->first();
        $this->assertSame('pool1', $plan->hotspot['address_pool']);
        $this->assertSame('parent1', $plan->hotspot['parent_queue']);
        $this->assertSame('Enable', $plan->hotspot['lock_user']);
        $this->assertSame('remove', $plan->hotspot['expired_mode']);
    }

    public function test_an_identical_router_profile_is_reused(): void
    {
        [$user, $zone, $fake] = $this->router([
            '1Jours' => [
                'session-timeout' => '24h',
                'rate-limit' => '10M/10M',
                'shared-users' => '1',
                'address-pool' => 'pool1',
                'parent-queue' => 'parent1',
            ],
        ]);

        $this->actingAs($user)->post('/entrepreneur/profils', $this->payload($zone->id, [
            'sync' => '1',
            'address_pool' => 'pool1',
            'parent_queue' => 'parent1',
        ]))->assertRedirect(route('entrepreneur.profiles'))
            ->assertSessionHas('status', 'Profil 1Jours déjà présent. Il est réutilisé.');

        $this->assertSame([], $this->added($fake));
        $this->assertNotNull(Plan::withoutGlobalScope('tenant')->where('mikrotik_profile', '1Jours')->first());
    }

    public function test_a_different_router_profile_is_refused_without_overwrite(): void
    {
        [$user, $zone, $fake] = $this->router([
            '1Jours' => [
                'session-timeout' => '24h',
                'rate-limit' => '1M/1M',
                'shared-users' => '1',
                'address-pool' => 'pool1',
                'parent-queue' => 'parent1',
            ],
        ]);

        $this->actingAs($user)->from('/entrepreneur/profils/nouveau')->post('/entrepreneur/profils', $this->payload($zone->id, [
            'sync' => '1',
            'address_pool' => 'pool1',
            'parent_queue' => 'parent1',
        ]))->assertRedirect('/entrepreneur/profils/nouveau')
            ->assertSessionHas('warning', 'Un profil portant ce nom existe déjà avec des paramètres différents.');

        $this->assertSame([], $this->added($fake));
        $this->assertNull(Plan::withoutGlobalScope('tenant')->where('mikrotik_profile', '1Jours')->first());
    }

    public function test_required_sync_does_not_claim_success_when_the_router_is_unreachable(): void
    {
        $user = Platform::entrepreneur('Sync Offline', 'sync-off-'.str()->lower(str()->random(4)).'@example.com');
        $zone = Platform::zone($user, 'Zone offline');
        Platform::router($user, $zone);
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter(new RuntimeException('Connection refused')));

        $this->actingAs($user)->post('/entrepreneur/profils', $this->payload($zone->id, [
            'sync' => '1',
        ]))->assertRedirect(route('entrepreneur.profiles'))
            ->assertSessionHas('warning', 'Routeur hors ligne. Le profil est enregistré dans LIMETE.')
            ->assertSessionMissing('status');

        $plan = Plan::withoutGlobalScope('tenant')->where('mikrotik_profile', '1Jours')->first();
        $this->assertNotNull($plan);
        $this->assertNull($plan->hotspot['synced'] ?? null);
    }

    public function test_changing_zone_reloads_that_router_catalog(): void
    {
        $user = Platform::entrepreneur('Deux routeurs', 'two-routers-'.str()->lower(str()->random(4)).'@example.com');
        $first = Platform::zone($user, 'Zone Alpha');
        $second = Platform::zone($user, 'Zone Beta');
        $routerA = Platform::router($user, $first);
        $routerB = Platform::router($user, $second);
        $routerA->forceFill(['host' => '192.0.2.11', 'name' => 'Routeur Alpha'])->save();
        $routerB->forceFill(['host' => '192.0.2.12', 'name' => 'Routeur Beta'])->save();
        app(TenantManager::class)->set($user->tenant_id);
        Plan::create([
            'wifi_zone_id' => $first->id,
            'name' => '1Jours',
            'duration_seconds' => 86400,
            'price' => 1000,
            'currency' => 'CDF',
            'selling_price' => 1000,
            'selling_currency' => 'CDF',
            'unlimited_data' => false,
            'status' => 'active',
            'mikrotik_profile' => '1Jours',
            'hotspot' => ['rate_limit' => '10M/10M', 'shared_users' => 1, 'time_limit' => '24h', 'validity' => '1d'],
        ]);
        Plan::create([
            'wifi_zone_id' => $second->id,
            'name' => '7Jours',
            'duration_seconds' => 604800,
            'price' => 5000,
            'currency' => 'CDF',
            'unlimited_data' => false,
            'status' => 'active',
            'mikrotik_profile' => '7Jours',
            'hotspot' => ['rate_limit' => '2M/2M', 'shared_users' => 1],
        ]);
        app(TenantManager::class)->forget();

        $profiles = [
            '192.0.2.11' => ['1Jours' => ['session-timeout' => '24h', 'rate-limit' => '10M/10M', 'shared-users' => '1', 'address-pool' => 'pool1', 'parent-queue' => 'parent1']],
            '192.0.2.12' => ['7Jours' => ['session-timeout' => '7d', 'rate-limit' => '2M/2M', 'shared-users' => '2', 'address-pool' => 'pool7']],
        ];
        $fake = null;
        $fake = new FakeHotspotRouter(function (array $words) use (&$fake, $profiles) {
            $host = $fake->commands[array_key_last($fake->commands)]['host'];
            $path = $words[0] ?? '';
            if ($path === '/ip/hotspot/print') {
                return [['!type' => '!re', 'name' => 'hotspot-'.$host], ['!type' => '!done']];
            }
            if ($path !== '/ip/hotspot/user/profile/print') {
                return [['!type' => '!done']];
            }
            $rows = [];
            foreach ($profiles[$host] ?? [] as $name => $profile) {
                $rows[] = ['!type' => '!re', 'name' => $name] + $profile;
            }
            $rows[] = ['!type' => '!done'];

            return $rows;
        });
        $this->app->instance(HotspotRouter::class, $fake);

        $alpha = $this->actingAs($user)->get('/vouchers/generate?wifi_zone_id='.$first->id);
        $alpha->assertOk()
            ->assertSee('1. Routeur')
            ->assertSee('Routeur sélectionné')
            ->assertSee('Routeur Alpha')
            ->assertSee('2. Profile MikroTik')
            ->assertSee('value="1Jours"', false)
            ->assertSee('10M/10M')
            ->assertSee('pool1')
            ->assertSee('parent1')
            ->assertSee('1 000 FC')
            ->assertSee('3. Paramètres du ticket')
            ->assertSee('4. Quantité')
            ->assertSee('5. Génération')
            ->assertDontSee('value="7Jours"', false)
            ->assertDontSee('secret-router', false);

        $beta = $this->actingAs($user)->get('/vouchers/generate?wifi_zone_id='.$second->id);
        $beta->assertOk()
            ->assertSee('Routeur Beta')
            ->assertSee('value="7Jours"', false)
            ->assertSee('2M/2M')
            ->assertSee('5 000 FC')
            ->assertDontSee('value="1Jours"', false);
    }

    public function test_a_forged_profile_and_a_forged_price_are_refused(): void
    {
        $user = Platform::entrepreneur('Forge', 'forge-'.str()->lower(str()->random(4)).'@example.com');
        $zone = Platform::zone($user, 'Zone forge');
        Platform::router($user, $zone);
        app(TenantManager::class)->set($user->tenant_id);
        Plan::create([
            'wifi_zone_id' => $zone->id,
            'name' => '1Jours',
            'duration_seconds' => 86400,
            'price' => 1000,
            'currency' => 'CDF',
            'selling_price' => 1000,
            'selling_currency' => 'CDF',
            'unlimited_data' => false,
            'status' => 'active',
            'mikrotik_profile' => '1Jours',
        ]);
        app(TenantManager::class)->forget();
        $this->bindProfiles(['1Jours' => ['session-timeout' => '24h', 'rate-limit' => '10M/10M', 'shared-users' => '1']]);

        $page = $this->actingAs($user)->get('/vouchers/generate?wifi_zone_id='.$zone->id);
        $page->assertOk()->assertSee('Débit')->assertSee('10M/10M')->assertSee('1 000 FC')->assertSee('Prix');

        $this->actingAs($user)->from('/vouchers/generate')->post('/vouchers/quick/express', [
            'wifi_zone_id' => $zone->id,
            'profile' => 'FakeProfile',
            'qty' => 1,
            'price_amount' => 1,
        ])->assertSessionHas('warning', 'Profil introuvable.');
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());

        $this->actingAs($user)->post('/vouchers/quick/express', [
            'wifi_zone_id' => $zone->id,
            'profile' => '1Jours',
            'qty' => 1,
            'price_amount' => 1,
            'price_currency' => 'USD',
            'selling_price_amount' => 1,
        ])->assertRedirect();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertEquals(1000, (float) $voucher->price_amount);
        $this->assertSame('CDF', $voucher->currency);
        $this->assertEquals(1000, (float) $voucher->profile_snapshot['price_amount']);
        $this->assertArrayNotHasKey('password', $voucher->profile_snapshot);
    }

    /**
     * @param  array<string, array<string, string>>  $profiles
     * @return array{0: \App\Models\User, 1: \App\Models\WifiZone, 2: FakeHotspotRouter}
     */
    private function router(array $profiles = []): array
    {
        $user = Platform::entrepreneur('Profil Sync', 'profile-sync-'.str()->lower(str()->random(4)).'@example.com');
        $zone = Platform::zone($user, 'Zone profil');
        Platform::router($user, $zone);
        $fake = $this->bindProfiles($profiles);

        return [$user, $zone, $fake];
    }

    /**
     * @param  array<string, array<string, string>>  $profiles
     */
    private function bindProfiles(array $profiles): FakeHotspotRouter
    {
        $state = (object) ['profiles' => $profiles];
        $fake = new FakeHotspotRouter(function (array $words) use ($state) {
            $path = $words[0] ?? '';
            $fields = [];
            foreach ($words as $word) {
                if (is_string($word) && str_starts_with($word, '=') && str_contains(substr($word, 1), '=')) {
                    [$key, $value] = explode('=', substr($word, 1), 2);
                    $fields[$key] = $value;
                }
            }
            if ($path === '/ip/pool/print') {
                return [['!type' => '!re', 'name' => 'pool1'], ['!type' => '!re', 'name' => 'pool7'], ['!type' => '!done']];
            }
            if ($path === '/queue/simple/print') {
                return [['!type' => '!re', 'name' => 'parent1'], ['!type' => '!done']];
            }
            if ($path === '/ip/hotspot/user/profile/print') {
                $rows = [];
                foreach ($state->profiles as $name => $profile) {
                    $rows[] = ['!type' => '!re', 'name' => $name] + $profile;
                }
                $rows[] = ['!type' => '!done'];

                return $rows;
            }
            if ($path === '/ip/hotspot/user/profile/add') {
                $name = $fields['name'] ?? '';
                unset($fields['name']);
                $state->profiles[$name] = $fields;

                return [['!type' => '!done']];
            }
            if ($path === '/ip/hotspot/print') {
                return [['!type' => '!re', 'name' => 'hotspot1'], ['!type' => '!done']];
            }
            if ($path === '/ip/hotspot/user/print') {
                return [['!type' => '!done']];
            }

            return [['!type' => '!done']];
        });
        $this->app->instance(HotspotRouter::class, $fake);

        return $fake;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(int $zoneId, array $extra = []): array
    {
        return $extra + [
            'wifi_zone_id' => $zoneId,
            'name' => '1Jours',
            'shared_users' => 1,
            'rate_limit' => '10M/10M',
            'expired_mode' => 'remove',
            'price_amount' => 500,
            'price_currency' => 'CDF',
            'selling_price_amount' => 1000,
            'selling_price_currency' => 'CDF',
            'lock_user' => 'enable',
            'validity' => '1d',
            'time_limit' => '24h',
            'address_pool' => 'none',
            'parent_queue' => 'none',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function added(FakeHotspotRouter $fake): array
    {
        return array_values(array_filter(
            $fake->commands,
            fn (array $command) => ($command['words'][0] ?? '') === '/ip/hotspot/user/profile/add',
        ));
    }
}
