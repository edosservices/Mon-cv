<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Voucher;
use App\Services\SaleService;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Platform;
use Tests\TestCase;

class EntrepreneurBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_entrepreneur_dashboard_shows_business_actions(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-dash@example.com');
        Platform::zone($user, 'Limete');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Alice Wifi')
            ->assertSee('CA aujourd’hui')
            ->assertSee('Chiffre d’affaires du mois')
            ->assertSee('Tickets vendus')
            ->assertSee('Tickets disponibles')
            ->assertSee('Tickets actifs')
            ->assertSee('Clients')
            ->assertSee('Sessions actives')
            ->assertSee('+ Vendre un ticket')
            ->assertSee('+ Générer des tickets')
            ->assertSee('+ Créer un forfait')
            ->assertSee('+ Ajouter une WiFi Zone')
            ->assertSee('Mon Business')
            ->assertSee('Mes Rapports')
            ->assertSee('Déconnexion')
            ->assertSee('Notifications')
            ->assertSee('Profil');

        $client = \App\Models\User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => \App\Models\Role::where('slug', 'client')->first()->id,
            'name' => 'Client',
            'email' => 'client-dash@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);

        $this->actingAs($client)->get('/dashboard')->assertForbidden();
    }

    public function test_business_profile_logo_and_location_stay_on_the_tenant(): void
    {
        Storage::fake('public');
        $user = Platform::entrepreneur('Alice Wifi', 'alice-biz-page@example.com');
        $other = Platform::entrepreneur('Bob Wifi', 'bob-biz-page@example.com');

        $this->actingAs($user)->get('/business')
            ->assertOk()
            ->assertSee('Mon Business')
            ->assertSee('Utiliser ma position')
            ->assertSee('id="logo"', false);

        $this->actingAs($user)->put('/business', [
            'name' => 'Alice Market',
            'slogan' => 'Le wifi du quartier',
            'phone' => '+243810001122',
            'whatsapp' => '+243810001122',
            'email' => 'alice@example.com',
            'address' => '12 avenue',
            'city' => 'Kinshasa',
            'country' => 'RDC',
            'latitude' => -4.321,
            'longitude' => 15.321,
            'primary_color' => '#aa1122',
            'secondary_color' => '#112233',
            'button_color' => '#334455',
            'ticket_style' => 'classique',
            'logo' => UploadedFile::fake()->image('logo.png', 80, 80),
        ])->assertRedirect();

        $user->tenant->refresh();
        $this->assertSame('Alice Market', $user->tenant->name);
        $this->assertNull($user->tenant->ikeepaySecret());
        $this->assertSame('Le wifi du quartier', $user->tenant->slogan);
        $this->assertSame('+243810001122', $user->tenant->whatsapp);
        $this->assertEquals(-4.321, (float) $user->tenant->latitude);
        $this->assertEquals(15.321, (float) $user->tenant->longitude);
        $this->assertSame('#aa1122', $user->tenant->primary_color);
        $this->assertSame('classique', $user->tenant->ticket_style);
        $this->assertStringStartsWith('logos/', $user->tenant->logo_path);
        Storage::disk('public')->assertExists($user->tenant->logo_path);
        $this->assertNotSame('Alice Market', $other->tenant->fresh()->name);

        $this->actingAs($user)->put('/business', [
            'name' => 'Alice Market',
            'logo' => UploadedFile::fake()->create('logo.svg', 12, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');

        $this->actingAs($user)->put('/business', [
            'name' => 'Alice Market',
            'logo' => UploadedFile::fake()->image('tiny.png', 8, 8),
        ])->assertSessionHasErrors('logo');

        $this->actingAs($user)->put('/business', [
            'name' => 'Alice Market',
            'logo' => UploadedFile::fake()->create('logo.php', 20, 'application/x-php'),
        ])->assertSessionHasErrors('logo');
    }

    public function test_branding_is_reused_on_the_shop_and_tickets(): void
    {
        Storage::fake('public');
        $user = Platform::entrepreneur('Alice Wifi', 'alice-brand@example.com');
        $zone = Platform::zone($user, 'Limete');
        $plan = Platform::plan($user, $zone);
        $path = UploadedFile::fake()->image('mark.png', 80, 80)->store('logos', 'public');
        $user->tenant->update([
            'logo_path' => $path,
            'primary_color' => '#aa1122',
            'button_color' => '#334455',
            'phone' => '+243810009900',
            'slogan' => 'Toujours ouvert',
        ]);
        $zone->update([
            'primary_color' => null,
            'secondary_color' => null,
            'logo_path' => null,
            'phone' => null,
            'slogan' => null,
        ]);

       $this->get('/wifi/'.$zone->slug)
    ->assertOk()
    ->assertSee('#aa1122', false);

        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone->fresh(), $plan->fresh())[0];

        $this->actingAs($user)->post('/vouchers/print', [
            'ids' => [$voucher->id],
            'template' => 'moderne',
            'per_page' => 4,
        ])->assertOk()
            ->assertSee('#aa1122', false)
            ->assertSee('+243810009900', false);
    }

    public function test_a_first_zone_and_its_location_can_be_created_without_mikrotik_fields(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-zone@example.com');

        $this->actingAs($user)->get('/wifi-zones')
            ->assertOk()
            ->assertSee('Crée ta première WiFi Zone')
            ->assertSee('Créer ma zone');

        $this->actingAs($user)->get('/wifi-zones/create')
            ->assertOk()
            ->assertSee('Crée ta première WiFi Zone')
            ->assertSee('Utiliser ma position')
            ->assertDontSee('Profil MikroTik');

        $this->actingAs($user)->post('/wifi-zones', [
            'name' => 'Kingabwa',
            'location' => 'Marché',
            'latitude' => -4.4,
            'longitude' => 15.3,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('wifi_zones', [
            'tenant_id' => $user->tenant_id,
            'name' => 'Kingabwa',
            'location' => 'Marché',
        ]);

        $other = Platform::entrepreneur('Bob Wifi', 'bob-zone@example.com');
        $foreign = \App\Models\WifiZone::withoutGlobalScope('tenant')->where('name', 'Kingabwa')->first();
        $this->actingAs($other)->get('/wifi-zones/'.$foreign->id.'/edit')->assertNotFound();
    }

    public function test_a_plan_can_be_created_and_duplicated(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-plan@example.com');
        $zone = Platform::zone($user, 'Limete');

        $this->actingAs($user)->get('/plans/create')
            ->assertOk()
            ->assertSee('Internet illimité')
            ->assertSee('Paramètres avancés')
            ->assertSee('data-plan-preview', false);

        $this->actingAs($user)->post('/plans', [
            'name' => '24 HEURES',
            'price' => 1000,
            'duration_value' => 24,
            'duration_unit' => 'hours',
            'unlimited_data' => 1,
            'description' => 'Journée',
            'wifi_zone_id' => $zone->id,
            'currency' => 'CDF',
            'status' => 'active',
        ])->assertRedirect(route('plans.index'));

        $plan = Plan::withoutGlobalScope('tenant')->where('name', '24 HEURES')->first();
        $this->assertSame(86400, (int) $plan->duration_seconds);
        $this->assertTrue($plan->unlimited_data);

        $this->actingAs($user)->post('/plans/'.$plan->id.'/duplicate')->assertRedirect();
        $copy = Plan::withoutGlobalScope('tenant')->where('name', 'Copie de 24 HEURES')->first();
        $this->assertNotNull($copy);
        $this->assertNotSame($plan->id, $copy->id);
        $this->assertSame(86400, (int) $copy->duration_seconds);
        $this->assertSame('1000.00', (string) $copy->price);

        $other = Platform::entrepreneur('Bob Wifi', 'bob-plan@example.com');
        $this->actingAs($other)->post('/plans/'.$plan->id.'/duplicate')->assertNotFound();
    }

    public function test_quick_sale_creates_a_ticket_without_a_client_account(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-sale@example.com');
        $zone = Platform::zone($user, 'Limete');
        $plan = Platform::plan($user, $zone);
        $other = Platform::entrepreneur('Bob Wifi', 'bob-sale@example.com');
        $otherZone = Platform::zone($other, 'Autre');
        $otherPlan = Platform::plan($other, $otherZone);

        $this->actingAs($user)->get('/sales/quick')->assertOk()->assertSee('Vente rapide')->assertSee('Créer / vendre');

        $this->actingAs($user)->post('/sales/quick', [
            'wifi_zone_id' => $otherZone->id,
            'plan_id' => $otherPlan->id,
            'quantity' => 1,
        ])->assertNotFound();

        $response = $this->actingAs($user)->post('/sales/quick', [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'quantity' => 1,
            'phone' => '243810000777',
            'name' => 'Passant',
        ]);
        $response->assertRedirect(route('sales.quick.done'));

        $voucher = Voucher::withoutGlobalScope('tenant')->where('tenant_id', $user->tenant_id)->first();
        $this->assertNotNull($voucher);
        $this->assertSame('active', $voucher->status);
        $this->assertNotNull($voucher->expires_at);
        $this->assertSame(1, Customer::withoutGlobalScope('tenant')->where('tenant_id', $user->tenant_id)->count());
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->where('tenant_id', $other->tenant_id)->count());

        $this->actingAs($user)->get('/sales/quick/done')
            ->assertOk()
            ->assertSee($voucher->username)
            ->assertSee('Imprimer')
            ->assertSee('PDF')
            ->assertSee('Partager')
            ->assertSee('<svg', false);
    }

    public function test_clients_and_reports_stay_inside_the_tenant(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice-report@example.com');
        $zone = Platform::zone($alice, 'Limete');
        $plan = Platform::plan($alice, $zone);
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-report@example.com');
        $bobZone = Platform::zone($bob, 'Bob zone');
        $bobPlan = Platform::plan($bob, $bobZone);

        app(TenantManager::class)->set($alice->tenant_id);
        $aliceVoucher = app(VoucherGenerator::class)->create($zone, $plan)[0];
        app(SaleService::class)->sellExisting($aliceVoucher, Customer::create(['name' => 'Aline', 'phone' => '243810000111']));

        app(TenantManager::class)->set($bob->tenant_id);
        $bobVoucher = app(VoucherGenerator::class)->create($bobZone, $bobPlan)[0];
        app(SaleService::class)->sellExisting($bobVoucher, Customer::create(['name' => 'Bruno', 'phone' => '243899900222']));

        $this->actingAs($alice)->get('/customers')
            ->assertOk()
            ->assertSee('Aline')
            ->assertSee('243810000111')
            ->assertSee('Voir')
            ->assertSee('Tickets')
            ->assertDontSee('Bruno')
            ->assertDontSee('243899900222');

        $this->actingAs($alice)->get('/reports')
            ->assertOk()
            ->assertSee('Aujourd’hui')
            ->assertSee('7 jours')
            ->assertSee('30 jours')
            ->assertSee('Personnalisé')
            ->assertSee('Ventes')
            ->assertSee('Revenus')
            ->assertSee('Imprimer')
            ->assertSee('PDF')
            ->assertSee('CSV')
            ->assertSee('Aline')
            ->assertDontSee('Bruno');

        $this->actingAs($alice)->get('/reports?zone='.$bobZone->id)->assertNotFound();

        $csv = $this->actingAs($alice)->get('/exports/sales.csv');
        $csv->assertOk();
        $this->assertStringContainsString('243810000111', $csv->streamedContent());
        $this->assertStringNotContainsString('243899900222', $csv->streamedContent());

        $this->actingAs($alice)->get('/exports/sales.pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_onboarding_tracks_the_first_sale_and_geolocation_waits_for_a_click(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-onboard@example.com');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Business')
            ->assertSee('Logo')
            ->assertSee('WiFi Zone')
            ->assertSee('Forfait')
            ->assertSee('Première vente')
            ->assertSee('Continuer la configuration');

        $js = file_get_contents(resource_path('js/business.js'));
        $before = strstr($js, 'navigator.geolocation.getCurrentPosition', true);
        $this->assertNotFalse($before);
        $this->assertStringContainsString("addEventListener('click'", $before);

        $zone = Platform::zone($user, 'Limete');
        $plan = Platform::plan($user, $zone);
        $user->tenant->update(['logo_path' => 'logos/deja.png']);
        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan)[0];
        app(SaleService::class)->sellExisting($voucher);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('Continuer la configuration');
    }

    public function test_ikeepay_keys_stay_on_the_entrepreneur_and_the_secret_is_not_shown(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-ikee-key@example.com');
        $other = Platform::entrepreneur('Bob Wifi', 'bob-ikee-key@example.com');

        $this->actingAs($user)->put('/business', [
            'name' => 'Alice Wifi',
            'ikeepay_public_key' => 'pk_alice',
            'ikeepay_secret_key' => 'sk_alice_secret',
        ])->assertRedirect();

        $user->tenant->refresh();
        $this->assertSame('pk_alice', $user->tenant->ikeepayPublicKey());
        $this->assertSame('sk_alice_secret', $user->tenant->ikeepaySecret());
        $raw = \Illuminate\Support\Facades\DB::table('tenants')->where('id', $user->tenant_id)->value('ikeepay_secret_key');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk_alice_secret', $raw);
        $this->assertNull($other->tenant->fresh()->ikeepaySecret());

        $this->actingAs($user)->get('/business')
            ->assertOk()
            ->assertSee('pk_alice', false)
            ->assertSee('Clé enregistrée', false)
            ->assertDontSee('sk_alice_secret', false);

        $this->actingAs($user)->put('/business', [
            'name' => 'Alice Wifi',
            'ikeepay_public_key' => 'pk_alice',
            'ikeepay_secret_key' => '',
        ])->assertRedirect();

        $this->assertSame('sk_alice_secret', $user->tenant->fresh()->ikeepaySecret());
    }
}
