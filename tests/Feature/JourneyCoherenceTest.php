<?php

namespace Tests\Feature;

use App\Models\MikrotikProfile;
use App\Models\Plan;
use App\Models\Voucher;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Platform;
use Tests\TestCase;

class JourneyCoherenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_zone_without_a_plan_explains_the_empty_shop(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-empty-shop@example.com');
        $zone = Platform::zone($user, 'Limete vide');

        $this->get('/wifi/'.$zone->slug)
            ->assertOk()
            ->assertSee('Aucun forfait actif pour cette WiFi Zone.')
            ->assertSee('Aucun forfait n’est en vente pour le moment.')
            ->assertDontSee('Acheter maintenant');
    }

    public function test_a_mikrotik_profile_without_a_plan_is_not_for_sale(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-profile-only@example.com');
        $zone = Platform::zone($user, 'Limete profil');
        $router = Platform::router($user, $zone);
        app(TenantManager::class)->set($user->tenant_id);
        MikrotikProfile::create([
            'mikrotik_id' => $router->id,
            'name' => '1jour-technique',
            'rate_limit' => '5M/2M',
            'shared_users' => 1,
        ]);

        $this->get('/wifi/'.$zone->slug)
            ->assertOk()
            ->assertDontSee('1jour-technique')
            ->assertSee('Aucun forfait actif pour cette WiFi Zone.');
    }

    public function test_an_active_plan_is_sold_and_an_inactive_or_foreign_plan_is_hidden(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-sale@example.com');
        $zone = Platform::zone($user, 'Limete vente');
        $otherZone = Platform::zone($user, 'Autre zone');
        $plan = Platform::plan($user, $zone);
        $plan->update(['name' => '1 JOUR', 'mikrotik_profile' => '1jour']);
        $paused = Platform::plan($user, $zone);
        $paused->update(['name' => 'PAUSE JOUR', 'status' => 'inactive']);
        $elsewhere = Platform::plan($user, $otherZone);
        $elsewhere->update(['name' => 'AUTRE ZONE']);
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-sale@example.com');
        $foreign = Platform::plan($bob, Platform::zone($bob, 'Zone Bob'));
        $foreign->update(['name' => 'FORFAIT BOB']);

        $shop = $this->get('/wifi/'.$zone->slug);
        $shop->assertOk()
            ->assertSee('1 JOUR')
            ->assertSee('Acheter')
            ->assertDontSee('PAUSE JOUR')
            ->assertDontSee('AUTRE ZONE')
            ->assertDontSee('FORFAIT BOB');

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$paused->id)->assertNotFound();
        $this->get('/wifi/'.$zone->slug.'/forfait/'.$elsewhere->id)->assertNotFound();
        $this->get('/wifi/'.$zone->slug.'/forfait/'.$foreign->id)->assertNotFound();
    }

    public function test_generation_uses_the_plan_and_paginates_twenty_four_tickets(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-gen@example.com', 'business');
        $zone = Platform::zone($user, 'Limete gen');
        $plan = Platform::plan($user, $zone);
        $plan->update(['name' => '1 JOUR', 'price' => 1000]);

        $this->actingAs($user)->get('/vouchers/generate?wifi_zone_id='.$zone->id)
            ->assertOk()
            ->assertSee('Générer des tickets')
            ->assertSee('Choisir un forfait')
            ->assertSee('1 JOUR')
            ->assertSee('Options avancées');

        $this->actingAs($user)->post('/vouchers', [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'count' => 10,
            'template' => 'moderne',
            'per_page' => 6,
            'price' => 1,
            'mikrotik_profile' => 'faux-profil',
        ])->assertRedirect('/vouchers/generated');

        $created = Voucher::withoutGlobalScope('tenant')->get();
        $this->assertCount(10, $created);
        $this->assertTrue($created->every(fn (Voucher $voucher) => (float) $voucher->price_amount === 1000.0));
        $this->assertTrue($created->every(fn (Voucher $voucher) => $voucher->sync_status !== 'synced'));
        $this->actingAs($user)->get('/vouchers/generated')
            ->assertOk()
            ->assertSee('10 tickets générés avec succès.')
            ->assertSee('Synchronisation en attente');

        app(TenantManager::class)->set($user->tenant_id);
        $batch = app(VoucherGenerator::class)->create($zone, $plan, 30);
        $this->actingAs($user)->withSession([
            'voucher_batch' => [
                'ids' => array_map(fn (Voucher $voucher) => $voucher->id, $batch),
                'wifi_zone_id' => $zone->id,
                'plan_id' => $plan->id,
                'template' => 'moderne',
                'per_page' => 6,
                'count' => 30,
            ],
        ])->get('/vouchers/generated')
            ->assertOk()
            ->assertSee($batch[0]->username)
            ->assertDontSee($batch[24]->username);

        $this->actingAs($user)->withSession([
            'voucher_batch' => [
                'ids' => array_map(fn (Voucher $voucher) => $voucher->id, $batch),
                'template' => 'moderne',
                'per_page' => 6,
            ],
        ])->get('/vouchers/generated?page=2')
            ->assertOk()
            ->assertSee($batch[24]->username)
            ->assertDontSee($batch[0]->username);
    }

    public function test_a_forged_plan_and_an_inactive_plan_are_refused(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-forge-plan@example.com');
        $zone = Platform::zone($user, 'Limete forge');
        $plan = Platform::plan($user, $zone);
        $plan->update(['status' => 'inactive']);
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-forge-plan@example.com');
        $foreign = Platform::plan($bob, Platform::zone($bob, 'Zone Bob'));

        $this->actingAs($user)->post('/vouchers', [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'count' => 1,
            'template' => 'moderne',
            'per_page' => 6,
        ])->assertSessionHasErrors('plan_id');

        $this->actingAs($user)->post('/vouchers', [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $foreign->id,
            'count' => 1,
            'template' => 'moderne',
            'per_page' => 6,
        ])->assertNotFound();

        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_a_forged_profile_name_is_rejected_when_the_router_catalog_exists(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-profile-guard@example.com');
        $zone = Platform::zone($user, 'Limete garde');
        $router = Platform::router($user, $zone);
        app(TenantManager::class)->set($user->tenant_id);
        MikrotikProfile::create([
            'mikrotik_id' => $router->id,
            'name' => '1jour',
            'rate_limit' => '5M/2M',
        ]);

        $this->actingAs($user)->post('/plans', [
            'name' => '1 Jour',
            'wifi_zone_id' => $zone->id,
            'duration_value' => 1,
            'duration_unit' => 'days',
            'price' => 1000,
            'selling_price' => 1000,
            'currency' => 'CDF',
            'mikrotik_profile' => 'profil-invente',
            'status' => 'active',
        ])->assertSessionHasErrors('mikrotik_profile');

        $this->actingAs($user)->post('/plans', [
            'name' => '1 Jour',
            'wifi_zone_id' => $zone->id,
            'duration_value' => 1,
            'duration_unit' => 'days',
            'price' => 1000,
            'selling_price' => 1200,
            'currency' => 'CDF',
            'mikrotik_profile' => '1jour',
            'status' => 'active',
        ])->assertRedirect(route('plans.index'));

        $plan = Plan::query()->where('name', '1 Jour')->first();
        $this->assertNotNull($plan);
        $this->assertSame('1jour', $plan->mikrotik_profile);
        $this->assertSame('1200.00', (string) $plan->selling_price);
        $this->assertNotNull($plan->mikrotikLinks()->first());
    }
}
