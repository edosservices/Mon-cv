<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class SpaceArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_sees_only_their_space_and_shares_without_the_password(): void
    {
        $owner = Platform::entrepreneur('Alice Wifi', 'alice-space@example.com');
        $zone = Platform::zone($owner, 'Limete');
        $plan = Platform::plan($owner, $zone);
        app(TenantManager::class)->set($owner->tenant_id);
        $customer = Customer::create(['name' => 'Awa', 'phone' => '+243814000333']);
        $other = Customer::create(['name' => 'Bola', 'phone' => '+243815000444']);
        $own = $this->ticket($zone, $plan, $customer->id, 'active');
        $foreign = $this->ticket($zone, $plan, $other->id, 'active');
        app(TenantManager::class)->forget();

        $this->post('/client/register', [
            'phone' => '0814000333',
            'name' => 'Awa',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ])->assertRedirect(route('client.dashboard'));

        $dashboard = $this->get('/client/dashboard');
        $dashboard->assertOk()
            ->assertSee('Tickets actifs')
            ->assertSee('Tickets disponibles')
            ->assertSee('Tickets expirés')
            ->assertSee('Dernier achat')
            ->assertSee($own->username)
            ->assertDontSee($foreign->username);
        $this->assertStringContainsString(route('tickets.public', $own->public_token).'#connexion', $dashboard->getContent());
        $this->assertStringNotContainsString('password=', $dashboard->getContent());
        preg_match_all('/https:\/\/wa\.me\/\?text=([^"\s]+)/', $dashboard->getContent(), $shares);
        $this->assertNotEmpty($shares[1]);
        foreach ($shares[1] as $share) {
            $text = urldecode($share);
            $this->assertStringNotContainsString($own->password, $text);
            $this->assertStringContainsString('Se connecter :', $text);
            $this->assertStringContainsString(route('tickets.public', $own->public_token), $text);
        }

        $this->get('/client/tickets')
            ->assertOk()
            ->assertSee('Ticket')
            ->assertSee('PDF')
            ->assertSee('<svg', false)
            ->assertDontSee($own->password);

        $this->get('/client/historique')->assertOk()->assertSee($own->username);
        $this->get('/client/acheter')->assertOk();
        $this->get('/client/profile')->assertOk();

        $this->get('/dashboard')->assertForbidden();
        $this->get('/entrepreneur')->assertForbidden();
        $this->get('/entrepreneur/profils')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/clients')->assertForbidden();
        $this->get('/mikrotiks')->assertForbidden();
    }

    public function test_entrepreneur_charts_keep_each_currency_separate_and_inside_the_tenant(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice-chart@example.com');
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-chart@example.com');
        $aliceZone = Platform::zone($alice, 'Limete Alice');
        $bobZone = Platform::zone($bob, 'Limete Bob');
        $alicePlan = Platform::plan($alice, $aliceZone);
        $bobPlan = Platform::plan($bob, $bobZone);

        app(TenantManager::class)->set($alice->tenant_id);
        $aliceTicket = $this->ticket($aliceZone, $alicePlan, null, 'available', 'LMALICE1');
        $this->paidSale($aliceZone, $alicePlan, $aliceTicket, 1000, 'CDF');
        Payment::create([
            'amount' => 1000,
            'currency' => 'CDF',
            'provider' => 'airtel_money',
            'status' => 'success',
        ]);
        $router = Platform::router($alice, $aliceZone);
        $router->forceFill([
            'status' => 'online',
            'identity' => 'limete-core',
            'routeros_version' => '7.15',
            'details' => [
                'uptime' => '1d2h',
                'cpu' => '3%',
                'memory' => '40%',
                'interfaces' => [['name' => 'ether1']],
                'hotspot_servers' => [['name' => 'hotspot1']],
                'hotspot_users' => 4,
                'active_users' => 2,
            ],
        ])->save();

        app(TenantManager::class)->set($bob->tenant_id);
        $bobTicket = $this->ticket($bobZone, $bobPlan, null, 'available', 'LMBOB001');
        $this->paidSale($bobZone, $bobPlan, $bobTicket, 5, 'USD');
        app(TenantManager::class)->forget();
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter);

        $page = $this->actingAs($alice)->get('/entrepreneur?chart=30');
        $page->assertOk()
            ->assertSee('Total tickets')
            ->assertSee('Tickets vendus')
            ->assertSee('Utilisateurs MikroTik')
            ->assertSee('Synchronisations en attente')
            ->assertSee('1 000 FC')
            ->assertSee('airtel_money')
            ->assertSee('Connected')
            ->assertSee('limete-core')
            ->assertSee('7.15')
            ->assertSee('Synchroniser')
            ->assertSee('Voir MikroTik')
            ->assertSee('Réessayer')
            ->assertSee($aliceTicket->username)
            ->assertDontSee('5 USD')
            ->assertDontSee($bobTicket->username)
            ->assertDontSee('1 005')
            ->assertDontSee('secret-router', false);

        $this->actingAs($alice)->get('/entrepreneur/profils')->assertOk()->assertSee('Ajouter un profil');
        $this->actingAs($alice)->get('/entrepreneur/generer')->assertOk()->assertSee('Générer des tickets');
        $this->actingAs($alice)->get('/entrepreneur/tickets?status=sold')->assertOk()->assertSee($aliceTicket->username)->assertDontSee($bobTicket->username);
        $this->actingAs($alice)->get('/entrepreneur/tickets?status=available')->assertOk();
        $this->actingAs($bob)->get('/vouchers/'.$aliceTicket->id)->assertNotFound();
    }

    public function test_ticket_filters_and_bulk_print_stay_inside_the_tenant(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice-bulk@example.com');
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-bulk@example.com');
        $aliceZone = Platform::zone($alice, 'Limete Alice');
        $bobZone = Platform::zone($bob, 'Limete Bob');
        $alicePlan = Platform::plan($alice, $aliceZone);
        $bobPlan = Platform::plan($bob, $bobZone);
        app(TenantManager::class)->set($alice->tenant_id);
        $available = $this->ticket($aliceZone, $alicePlan, null, 'available', 'LMFREE01');
        $expired = $this->ticket($aliceZone, $alicePlan, null, 'expired', 'LMUSED01');
        app(TenantManager::class)->set($bob->tenant_id);
        $foreign = $this->ticket($bobZone, $bobPlan, null, 'available', 'LMOTHER1');
        app(TenantManager::class)->forget();

        $this->actingAs($alice)->get('/vouchers?status=available&q=LMFREE')
            ->assertOk()
            ->assertSee('LMFREE01')
            ->assertDontSee('LMUSED01');

        $this->actingAs($alice)->get('/vouchers?status=used')
            ->assertOk()
            ->assertSee('LMUSED01')
            ->assertDontSee('LMFREE01');

        $this->actingAs($alice)->post('/vouchers/bulk', [
            'ids' => [$foreign->id],
            'density' => 50,
        ])->assertOk()->assertDontSee('LMOTHER1');

        $sheet = $this->actingAs($alice)->post('/vouchers/bulk', [
            'ids' => [$available->id, $expired->id],
            'density' => 20,
        ]);
        $sheet->assertOk()->assertSee('LMFREE01')->assertSee('LMUSED01')->assertSee('<svg', false);
        $this->assertStringNotContainsString('secret-router', $sheet->getContent());
    }

    public function test_profile_validation_stores_the_typed_currency_and_syncs_once(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-profile@example.com');
        $zone = Platform::zone($user, 'Limete');
        $base = $this->profilePayload($zone->id);

        $this->actingAs($user)->post('/entrepreneur/profils', array_merge($base, [
            'sync' => '1',
            'price_amount' => 5,
            'price_currency' => 'USD',
            'selling_price_amount' => 5,
            'selling_price_currency' => 'USD',
            'time_limit' => '24h',
            'validity' => '1d',
        ]))->assertRedirect(route('entrepreneur.profiles'))
            ->assertSessionHas('warning');

        $plan = Plan::withoutGlobalScope('tenant')->where('mikrotik_profile', '1Jours')->first();
        $this->assertNotNull($plan);
        $this->assertSame('USD', $plan->currency);
        $this->assertSame('USD', $plan->selling_currency);
        $this->assertEquals(5, (float) $plan->selling_price);
        $this->assertSame('10M/10M', $plan->hotspot['rate_limit']);
        $this->assertSame('Enable', $plan->hotspot['lock_user']);
        $this->assertSame('24h', $plan->hotspot['time_limit']);
        $this->assertSame(86400, $plan->duration_seconds);

        $this->actingAs($user)->from('/entrepreneur/profils/nouveau')->post('/entrepreneur/profils', array_merge($base, [
            'time_limit' => '2d',
            'validity' => '1d',
        ]))->assertRedirect('/entrepreneur/profils/nouveau')
            ->assertSessionHasErrors(['time_limit' => 'Time Limit doit être inférieur à Validity.']);

        $this->actingAs($user)->from('/entrepreneur/profils/nouveau')->post('/entrepreneur/profils', array_merge($base, [
            'name' => 'BadRate',
            'rate_limit' => 'vite',
        ]))->assertSessionHasErrors('validity');

        $this->actingAs($user)->post('/entrepreneur/profils', array_merge($base, [
            'name' => 'Franc',
            'price_currency' => 'FC',
            'selling_price_currency' => 'FC',
        ]))->assertRedirect();
        $this->assertSame('FC', Plan::withoutGlobalScope('tenant')->where('name', 'Franc')->first()->currency);

        $state = (object) ['names' => []];
        $fake = new FakeHotspotRouter(function (array $words) use ($state) {
            $path = $words[0] ?? '';
            if ($path === '/ip/hotspot/user/profile/print') {
                $rows = [];
                foreach ($state->names as $name) {
                    $rows[] = ['!type' => '!re', 'name' => $name];
                }
                $rows[] = ['!type' => '!done'];

                return $rows;
            }
            if ($path === '/ip/hotspot/user/profile/add') {
                foreach ($words as $word) {
                    if (is_string($word) && str_starts_with($word, '=name=')) {
                        $state->names[] = substr($word, 6);
                    }
                }
            }

            return [['!type' => '!done']];
        });
        $this->app->instance(HotspotRouter::class, $fake);
        Platform::router($user, $zone);

        $this->actingAs($user)->post('/entrepreneur/profils', array_merge($base, [
            'name' => 'Live1J',
            'sync' => '1',
            'time_limit' => '24h',
            'validity' => '1d',
            'rate_limit' => '10M/10M',
        ]))->assertRedirect();

        $adds = array_values(array_filter(
            $fake->commands,
            fn (array $command) => ($command['words'][0] ?? '') === '/ip/hotspot/user/profile/add',
        ));
        $this->assertCount(1, $adds);
        $words = implode(' ', $adds[0]['words']);
        $this->assertStringContainsString('=name=Live1J', $words);
        $this->assertStringContainsString('=session-timeout=24h', $words);
        $this->assertStringContainsString('=rate-limit=10M/10M', $words);
        $this->assertStringNotContainsString('address-pool', $words);
        $this->assertStringNotContainsString('secret-router', $words);

        $this->actingAs($user)->post('/entrepreneur/profils', array_merge($base, [
            'name' => 'Live1J',
            'sync' => '1',
        ]))->assertSessionHas('warning', 'Un profil portant ce nom existe déjà avec des paramètres différents.');
        $this->assertCount(1, array_filter(
            $fake->commands,
            fn (array $command) => ($command['words'][0] ?? '') === '/ip/hotspot/user/profile/add',
        ));
    }

    public function test_generation_counts_stay_unique_and_a_replay_does_not_duplicate(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-gen@example.com');
        $zone = Platform::zone($user, 'Limete');
        app(TenantManager::class)->set($user->tenant_id);
        $plan = Plan::create([
            'wifi_zone_id' => $zone->id,
            'name' => '1Jours',
            'duration_seconds' => 86400,
            'price' => 1000,
            'currency' => 'FC',
            'selling_price' => 1000,
            'selling_currency' => 'FC',
            'unlimited_data' => false,
            'status' => 'active',
            'mikrotik_profile' => '1Jours',
            'hotspot' => [
                'validity' => '1d',
                'time_limit' => '24h',
                'rate_limit' => '10M/10M',
                'lock_user' => 'Enable',
                'shared_users' => 1,
            ],
        ]);
        Voucher::create([
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'public_token' => 'taken-token-space-001',
            'username' => 'enhTaken',
            'password' => '4321',
            'status' => 'available',
            'price_amount' => 1000,
            'currency' => 'FC',
            'sync_status' => 'pending',
        ]);
        app(TenantManager::class)->forget();

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->quick($zone->id, [
            'draft' => 'draft-space-collision',
            'mode' => 'add',
            'username' => 'enhTaken',
            'password' => '1111',
        ]))->assertSessionHas('warning', 'Cet utilisateur existe déjà.');

        $before = Voucher::withoutGlobalScope('tenant')->count();
        foreach ([1, 10, 50, 100] as $qty) {
            $draft = 'draft-space-qty-'.str_pad((string) $qty, 4, '0', STR_PAD_LEFT);
            $payload = $this->quick($zone->id, [
                'draft' => $draft,
                'mode' => 'generate',
                'qty' => $qty,
                'prefix' => 'LM',
                'length' => 4,
                'charset' => 'upper',
                'confirm' => '1',
            ]);
            $this->actingAs($user)->post('/vouchers/quick/preview', $payload)->assertOk();
            $this->actingAs($user)->post('/vouchers/quick', $payload)->assertRedirect();
            if ($qty === 1) {
                $this->actingAs($user)->post('/vouchers/quick', $payload)->assertRedirect();
            }
        }

        $created = Voucher::withoutGlobalScope('tenant')->where('username', '!=', 'enhTaken')->get();
        $this->assertCount(161, $created);
        $this->assertSame(161, $created->pluck('username')->unique()->count());
        $this->assertSame($before + 161, Voucher::withoutGlobalScope('tenant')->count());
        $sample = $created->first();
        $this->assertNotSame('', (string) $sample->public_token);
        $this->assertSame('1Jours', $sample->profile_snapshot['profile']);
        $this->assertSame('24h', $sample->profile_snapshot['time_limit']);
        $this->assertSame('FC', $sample->profile_snapshot['price_currency']);
        $this->assertSame('10M/10M', $sample->profile_snapshot['rate_limit']);
        $this->assertSame(5 * 1073741824, $sample->profile_snapshot['data_bytes']);
    }

    public function test_super_admin_sees_global_figures_and_other_roles_do_not(): void
    {
        Platform::seed();
        $admin = User::where('email', 'admin@limetewifi.local')->first();
        $alice = Platform::entrepreneur('Alice Wifi', 'alice-admin@example.com');
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-admin@example.com');
        $aliceZone = Platform::zone($alice, 'Zone Alice');
        $bobZone = Platform::zone($bob, 'Zone Bob');
        app(TenantManager::class)->set($alice->tenant_id);
        $this->paidSale($aliceZone, Platform::plan($alice, $aliceZone), null, 1000, 'CDF');
        app(TenantManager::class)->set($bob->tenant_id);
        $this->paidSale($bobZone, Platform::plan($bob, $bobZone), null, 5, 'USD');
        $this->post('/client/register', [
            'phone' => '0819000111',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ]);
        $client = User::where('client_phone', '+243819000111')->first();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Entrepreneurs')
            ->assertSee('Clients')
            ->assertSee('Routeurs connectés')
            ->assertSee('Tickets vendus')
            ->assertSee('1 000 FC')
            ->assertSee('5 USD')
            ->assertDontSee('1 005');

        $this->actingAs($admin)->get('/admin/clients')->assertOk();
        $this->actingAs($admin)->get('/admin/zones')->assertOk()->assertSee('Zone Alice')->assertSee('Zone Bob');
        $this->actingAs($admin)->get('/admin/tickets')->assertOk();
        $this->actingAs($admin)->get('/admin/sales')->assertOk()->assertSee('1 000 FC')->assertSee('5 USD');
        $this->actingAs($admin)->get('/admin/reports')->assertOk()->assertSee('WiFi Zones');
        $this->actingAs($client)->get('/admin/tickets')->assertForbidden();
        $this->actingAs($alice)->get('/admin')->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(int $zoneId): array
    {
        return [
            'wifi_zone_id' => $zoneId,
            'name' => '1Jours',
            'shared_users' => 1,
            'rate_limit' => '10M/10M',
            'expired_mode' => 'none',
            'price_amount' => 1000,
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
     * @return array<string, mixed>
     */
    private function quick(int $zoneId, array $extra): array
    {
        return $extra + [
            'wifi_zone_id' => $zoneId,
            'profile' => '1Jours',
            'server' => 'all',
            'data_value' => 5,
            'data_unit' => 'GB',
            'mode' => 'generate',
            'qty' => 1,
            'prefix' => 'LM',
            'length' => 4,
            'charset' => 'upper',
            'username' => '',
            'password' => '',
            'draft' => 'draft-space-default1',
        ];
    }

    private function ticket($zone, Plan $plan, ?int $customerId, string $status, ?string $username = null): Voucher
    {
        return Voucher::create([
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'customer_id' => $customerId,
            'public_token' => 'tok-'.str()->lower(str()->random(20)),
            'username' => $username ?: 'LM'.str()->upper(str()->random(6)),
            'password' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'activated_at' => $status === 'active' ? now() : null,
            'expires_at' => $status === 'expired' ? now()->subHour() : now()->addDay(),
            'price_amount' => 1000,
            'currency' => 'CDF',
            'profile_snapshot' => [
                'profile' => '24 HEURES',
                'validity' => '1d',
                'validity_label' => '24 heures',
                'time_limit' => '24h',
                'data_label' => '5 GB',
                'rate_limit' => '10M/10M',
            ],
            'sync_status' => 'pending',
        ]);
    }

    private function paidSale($zone, Plan $plan, ?Voucher $voucher, float $amount, string $currency): Sale
    {
        $sale = Sale::create([
            'wifi_zone_id' => $zone->id,
            'public_token' => 'sale-'.str()->lower(str()->random(20)),
            'total_amount' => $amount,
            'currency' => $currency,
            'status' => 'paid',
            'channel' => 'counter',
        ]);
        if ($voucher) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'voucher_id' => $voucher->id,
                'plan_id' => $plan->id,
                'amount' => $amount,
            ]);
        }

        return $sale;
    }
}
