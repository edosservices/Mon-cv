<?php

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\MikrotikProfile;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\Mikrotik\MikrotikService;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class CaptiveJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_zone_keeps_its_own_branding_on_the_shop_the_ticket_and_the_pdf(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-brand@example.com', 'business');
        $limete = Platform::zone($user, 'Limete');
        $kingabwa = Platform::zone($user, 'Kingabwa');
        $limete->update([
            'display_name' => 'Limete Market',
            'slogan' => 'Le WiFi du marché',
            'logo_path' => 'logos/limete-mark.png',
            'banner_path' => 'banners/limete-banner.png',
            'primary_color' => '#112233',
            'secondary_color' => '#abcdef',
            'email' => 'limete@example.com',
            'phone' => '+243810001111',
            'location' => 'Marché Limete',
        ]);
        $kingabwa->update([
            'display_name' => 'Kingabwa Centre',
            'slogan' => 'Le WiFi de Kingabwa',
            'logo_path' => 'logos/kingabwa-mark.png',
            'primary_color' => '#990000',
        ]);
        Platform::plan($user, $limete);

        $shop = $this->get('/wifi/'.$limete->slug);
        $shop->assertOk()
            ->assertSee('Limete Market')
            ->assertSee('Le WiFi du marché')
            ->assertSee('/storage/logos/limete-mark.png')
            ->assertSee('/storage/banners/limete-banner.png')
            ->assertSee('limete@example.com')
            ->assertSee('Illimité')
            ->assertDontSee('Kingabwa Centre')
            ->assertDontSee('logos/kingabwa-mark.png');

        $this->get('/wifi/'.$kingabwa->slug)
            ->assertOk()
            ->assertSee('Kingabwa Centre')
            ->assertSee('/storage/logos/kingabwa-mark.png')
            ->assertDontSee('Limete Market')
            ->assertDontSee('logos/limete-mark.png');

        $brand = $this->getJson('/wifi/'.$limete->slug.'/marque');
        $brand->assertOk()
            ->assertJsonPath('name', 'Limete Market')
            ->assertJsonPath('zone', 'Limete')
            ->assertJsonPath('slogan', 'Le WiFi du marché')
            ->assertJsonPath('primary', '#112233')
            ->assertJsonPath('whatsapp', '243860392283');
        $this->assertStringContainsString('/storage/logos/limete-mark.png', (string) $brand->json('logo'));
        $this->assertStringNotContainsString('kingabwa', json_encode($brand->json()));
        $this->assertStringNotContainsString('password', json_encode($brand->json()));

        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($limete->fresh(), $limete->plans()->first(), 1, true)[0];
        app(TenantManager::class)->forget();

        $ticket = $this->get('/ticket/'.$voucher->public_token);
        $ticket->assertOk()
            ->assertSee('Limete Market')
            ->assertSee('/storage/logos/limete-mark.png')
            ->assertDontSee('logos/kingabwa-mark.png')
            ->assertDontSee('Kingabwa Centre');

        $html = view('vouchers.ticket', [
            'voucher' => $voucher->fresh(['plan', 'wifiZone']),
            'qr' => null,
        ])->render();
        $this->assertStringContainsString('/storage/logos/limete-mark.png', $html);
        $this->assertStringNotContainsString('kingabwa-mark', $html);

        $this->get('/ticket/'.$voucher->public_token.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_logo_upload_keeps_a_generated_name_and_rejects_a_script(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-logo@example.com');
        $zone = Platform::zone($user, 'Limete');
        Storage::fake('public');

        $this->actingAs($user)->put('/wifi-zones/'.$zone->id, [
            'name' => 'Limete',
            'display_name' => 'Limete Market',
            'slogan' => 'Au rond-point',
            'location' => 'Limete',
            'phone' => '+243810001111',
            'whatsapp' => '+243860392283',
            'email' => 'zone@example.com',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'status' => 'active',
            'logo' => UploadedFile::fake()->image('logo-client.png', 8, 8),
            'banner' => UploadedFile::fake()->image('banner-client.png', 8, 8),
        ])->assertRedirect();

        $zone->refresh();
        $this->assertStringStartsWith('logos/', $zone->logo_path);
        $this->assertStringStartsWith('banners/', $zone->banner_path);
        $this->assertStringNotContainsString('logo-client', $zone->logo_path);
        $this->assertStringNotContainsString('banner-client', $zone->banner_path);
        Storage::disk('public')->assertExists($zone->logo_path);
        Storage::disk('public')->assertExists($zone->banner_path);

        $this->actingAs($user)->put('/wifi-zones/'.$zone->id, [
            'name' => 'Limete',
            'status' => 'active',
            'logo' => UploadedFile::fake()->create('note.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');
    }

    public function test_a_browser_return_does_not_activate_a_ticket_and_settlement_does(): void
    {
        [$user, $zone, $plan, $router, $fake] = $this->onlineShop();
        $plan->update(['mikrotik_profile' => 'VIP-24H']);
        MikrotikProfile::create([
            'tenant_id' => $user->tenant_id,
            'mikrotik_id' => $router->id,
            'name' => 'VIP-24H',
        ]);

        config(['limete.payments.airtel_money.webhook_secret' => 'whsec-test']);
        app(TenantManager::class)->set($zone->tenant_id);
        $sale = app(\App\Services\SaleService::class)->placeOrder($zone, $plan, [
            'name' => 'Amina',
            'phone' => '+243810009111',
            'purchase_for' => 'self',
        ], 'airtel_money');
        app(TenantManager::class)->forget();
        $this->assertSame('pending', $sale->status);
        $this->assertDatabaseCount('vouchers', 0);

        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'?payment_status=success')
            ->assertOk()
            ->assertSee('Paiement en attente')
            ->assertSee('Le serveur confirme le paiement. Cette page ne le transforme pas en succès.')
            ->assertDontSee('Votre ticket est prêt');
        $this->post('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'/actualiser')->assertRedirect();
        $this->assertDatabaseCount('vouchers', 0);
        $this->assertSame([], $this->adds($fake));

        $this->notify($sale->payment)->assertOk();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame($user->tenant_id, $voucher->tenant_id);
        $this->assertSame($zone->id, $voucher->wifi_zone_id);
        $this->assertSame($plan->id, $voucher->plan_id);
        $this->assertSame($sale->id, $voucher->saleItem->sale_id);
        $this->assertSame($router->id, $voucher->mikrotik_id);
        $this->assertSame('synced', $voucher->sync_status);
        $this->assertSame('active', $voucher->status);
        $this->assertSame($voucher->activated_at->getTimestamp() + 86400, $voucher->expires_at->getTimestamp());
        $this->assertSame('24 HEURES', $voucher->plan->name);
        $add = $this->adds($fake)[0];
        $this->assertContains('=profile=VIP-24H', $add['words']);
        $this->assertNotContains('=profile=24 HEURES', $add['words']);
    }

    public function test_a_ticket_is_refused_on_another_zone_and_another_entrepreneur(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice-lock@example.com', 'business');
        $limete = Platform::zone($alice, 'Limete');
        $kingabwa = Platform::zone($alice, 'Kingabwa');
        $plan = Platform::plan($alice, $limete);
        $plan->update(['mikrotik_profile' => '24H']);
        $home = Platform::router($alice, $limete);
        $otherZone = Platform::router($alice, $kingabwa);
        $home->update(['status' => 'online', 'dns' => 'limete.example']);
        $otherZone->update(['status' => 'online', 'host' => '192.0.2.20', 'dns' => 'kingabwa.example']);
        $fake = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $fake);
        app(TenantManager::class)->set($alice->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($limete, $plan->fresh(), 1, true)[0];

        try {
            app(MikrotikService::class)->createHotspotUser($otherZone->fresh(), $voucher->fresh('plan'));
            $this->fail('Un ticket Limete ne doit pas être créé sur Kingabwa.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('autre WiFi Zone', $exception->getMessage());
        }
        $this->assertSame([], $this->adds($fake));

        $bob = Platform::entrepreneur('Bob Wifi', 'bob-lock@example.com', 'business');
        $foreign = Platform::router($bob, Platform::zone($bob, 'Bandal'));
        $foreign->update(['status' => 'online']);
        app(TenantManager::class)->forget();

        try {
            app(MikrotikService::class)->createHotspotUser(
                Mikrotik::withoutGlobalScope('tenant')->find($foreign->id),
                $voucher->fresh('plan'),
            );
            $this->fail('Un ticket ne doit pas être créé chez un autre entrepreneur.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('entrepreneur', $exception->getMessage());
        }
        $this->assertSame([], $this->adds($fake));

        app(TenantManager::class)->set($alice->tenant_id);
        app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $voucher->refresh();
        $this->assertSame($home->id, $voucher->mikrotik_id);
        $this->assertSame('synced', $voucher->sync_status);
        $this->assertSame(['192.0.2.10'], array_column($this->adds($fake), 'host'));

        app(TenantManager::class)->forget();
        $page = $this->get('/ticket/'.$voucher->public_token);
        $page->assertOk()->assertSee('https://limete.example/login', false)->assertDontSee('kingabwa.example');
    }

    public function test_a_zone_provisions_every_online_router_and_retries_without_a_duplicate(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-many@example.com', 'business');
        $zone = Platform::zone($user, 'Limete');
        $plan = Platform::plan($user, $zone);
        $plan->update(['mikrotik_profile' => '24H']);
        $first = Platform::router($user, $zone);
        $second = Mikrotik::create([
            'tenant_id' => $user->tenant_id,
            'wifi_zone_id' => $zone->id,
            'name' => 'MikroTik B',
            'host' => '192.0.2.11',
            'api_port' => 8728,
            'username' => 'demo',
            'password' => 'secret-router',
            'status' => 'online',
            'is_active' => true,
        ]);
        $inactive = Mikrotik::create([
            'tenant_id' => $user->tenant_id,
            'wifi_zone_id' => $zone->id,
            'name' => 'MikroTik C',
            'host' => '192.0.2.12',
            'api_port' => 8728,
            'username' => 'demo',
            'password' => 'secret-router',
            'status' => 'online',
            'is_active' => false,
        ]);
        $first->update(['status' => 'online']);
        $fake = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $fake);
        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];

        $synced = app(MikrotikService::class)->provisionVoucher($voucher->fresh('plan'));
        $hosts = array_column($this->adds($fake), 'host');
        $this->assertEqualsCanonicalizing(['192.0.2.10', '192.0.2.11'], $hosts);
        $this->assertNotContains('192.0.2.12', $hosts);
        $this->assertSame($first->id, $synced->mikrotik_id);
        $this->assertSame('synced', $synced->sync_status);
        $this->assertTrue($inactive->is_active === false);

        $before = count($this->adds($fake));
        app(MikrotikService::class)->provisionVoucher($synced->fresh('plan'));
        $this->assertCount($before, $this->adds($fake));

        $offline = new FakeHotspotRouter(new RuntimeException('Connexion impossible au routeur.'));
        $this->app->instance(HotspotRouter::class, $offline);
        $again = app(VoucherGenerator::class)->create($zone, $plan->fresh(), 1)[0];
        $failed = app(MikrotikService::class)->provisionVoucher($again->fresh('plan'));
        $this->assertNull($failed->mikrotik_id);
        $this->assertSame('failed', $failed->sync_status);
        $this->assertStringContainsString('Connexion impossible', $failed->sync_error);

        $retry = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $retry);
        $recovered = app(MikrotikService::class)->provisionVoucher($failed->fresh('plan'));
        $this->assertSame('synced', $recovered->sync_status);
        $this->assertCount(2, $this->adds($retry));
        app(MikrotikService::class)->provisionVoucher($recovered->fresh('plan'));
        $this->assertCount(2, $this->adds($retry));
    }

    public function test_the_portal_files_keep_routeros_variables_and_hide_secrets(): void
    {
        foreach (['login.html', 'status.html', 'logout.html', 'error.html'] as $file) {
            $html = file_get_contents(base_path('hotspot/'.$file));
            $this->assertStringContainsString('js/brand.js', $html);
            $this->assertStringNotContainsString('api_password', $html);
            $this->assertStringNotContainsString('secret-router', $html);
            $this->assertDoesNotMatchRegularExpression('/(api[_-]?password|client_secret)\s*[:=]/i', $html);
        }

        $login = file_get_contents(base_path('hotspot/login.html'));
        $status = file_get_contents(base_path('hotspot/status.html'));
        $this->assertStringContainsString('$(link-login-only)', $login);
        $this->assertStringContainsString('$(username)', $login);
        $this->assertStringContainsString('$(chap-id)', $login);
        $this->assertStringContainsString('$(link-logout)', $status);
        $this->assertStringContainsString('$(ip)', $status);
        $this->assertStringContainsString('$(mac)', $status);
        $this->assertStringContainsString('$(session-time-left)', $status);
        $this->assertStringContainsString('Déconnexion', $status);
        $brandJs = file_get_contents(base_path('hotspot/js/brand.js'));
        $this->assertStringNotContainsString('secret-router', $brandJs);
        $this->assertDoesNotMatchRegularExpression('/(api[_-]?password|client_secret)\s*[:=]/i', $brandJs);

        $check = <<<'JS'
const fs = require('fs');
const vm = require('vm');
const sandbox = { console };
sandbox.window = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync('hotspot/js/app.js', 'utf8'), sandbox);
const started = '2026-09-28T14:00:00+01:00';
const expires = '2026-09-29T14:00:00+01:00';
const session = { planLabel: '24 HEURES', startedAt: started, expiresAt: expires, planSeconds: 86400, unlimited: true };
const first = sandbox.LimetePortal.resolveStatus(
  { username: 'LWTEST', uptime: '2h', uptimeSecs: '7200', timeLeft: '1h', timeLeftSecs: '3600' },
  session,
  { timezone: 'Africa/Kinshasa' },
  Date.parse('2026-09-28T16:00:00+01:00')
);
const again = sandbox.LimetePortal.resolveStatus(
  { username: 'LWTEST', uptime: '5m', uptimeSecs: '300', timeLeft: '23h', timeLeftSecs: '82800' },
  session,
  { timezone: 'Africa/Kinshasa' },
  Date.parse('2026-09-28T18:00:00+01:00')
);
const ended = sandbox.LimetePortal.resolveStatus(
  { username: 'LWTEST', uptime: '1m', uptimeSecs: '60', timeLeft: '0s', timeLeftSecs: '0' },
  session,
  { timezone: 'Africa/Kinshasa' },
  Date.parse('2026-09-29T14:05:00+01:00')
);
if (first.endText !== again.endText) throw new Error('clock restarted');
if (first.remainSeconds - again.remainSeconds !== 7200) throw new Error('remain');
if (again.state !== 'active' || again.internet !== 'Illimité') throw new Error('reconnect');
if (ended.state !== 'expired') throw new Error('expired ' + ended.state);
JS;
        $result = Process::path(base_path())->run(['node', '-e', $check]);
        $this->assertTrue($result->successful(), $result->errorOutput().$result->output());
    }

    public function test_the_walled_garden_stays_limited_to_the_portal_and_the_payment_hosts(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-garden@example.com');
        $zone = Platform::zone($user, 'Limete');
        $router = Platform::router($user, $zone);
        $router->update(['dns' => 'limete.example']);
        app(TenantManager::class)->set($user->tenant_id);

        $hosts = app(MikrotikService::class)->portalHostsFor($router->fresh('wifiZone'));

        $this->assertContains(parse_url((string) config('app.url'), PHP_URL_HOST), $hosts);
        $this->assertContains('limete.example', $hosts);
        $this->assertContains('api.ikeepay.com', $hosts);
        $this->assertContains('www.ikeepay.com', $hosts);
        $this->assertNotContains('wa.me', $hosts);
        $this->assertNotContains('api.whatsapp.com', $hosts);
        $this->assertNotContains('web.whatsapp.com', $hosts);
        $this->assertNotContains('*', $hosts);
        $this->assertNotContains('0.0.0.0/0', $hosts);
        foreach ($hosts as $host) {
            $this->assertFalse(str_contains($host, '*'));
            $this->assertFalse(str_contains($host, '/'));
        }
    }

    public function test_the_full_simulated_journey_keeps_the_zone_and_the_commercial_clock(): void
    {
        [$user, $zone, $plan, $router, $fake, $session] = $this->onlineShop(true);
        $router->update(['dns' => 'portail.limete.example']);
        $plan->update(['mikrotik_profile' => '24H']);
        $lines = new \ArrayObject;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($lines) {
            $lines->append($event->message.' '.json_encode($event->context));
        });

        $this->get('/wifi/'.$zone->slug)
            ->assertOk()
            ->assertSee('Limete')
            ->assertSee('24 HEURES')
            ->assertSee('Acheter')
            ->assertSee('Illimité');

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id)
            ->assertOk()
            ->assertSee('24 heures');
        $this->post('/wifi/'.$zone->slug.'/forfait/'.$plan->id, ['phone' => '+243810002424'])->assertRedirect();
        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->assertOk()
            ->assertSee('Zone')
            ->assertSee('Limete')
            ->assertSee('Durée')
            ->assertSee('1 000 FC')
            ->assertSee('+243810002424')
            ->assertDontSee('Airtel Money')
            ->assertDontSee('Orange Money')
            ->assertDontSee('M-Pesa')
            ->assertDontSee('Carte bancaire')
            ->assertSee('Paiement manuel / comptoir');

        config(['limete.payments.airtel_money.webhook_secret' => 'whsec-test']);
        app(TenantManager::class)->set($zone->tenant_id);
        $sale = app(\App\Services\SaleService::class)->placeOrder($zone, $plan, [
            'phone' => '+243810002424',
            'purchase_for' => 'self',
        ], 'airtel_money');
        app(TenantManager::class)->forget();
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'?status=success')
            ->assertOk()
            ->assertDontSee('Votre ticket est prêt');
        $this->assertDatabaseCount('vouchers', 0);

        $this->notify($sale->payment)->assertOk();
        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $expires = $voucher->expires_at->copy();
        $this->assertSame($zone->id, $voucher->wifi_zone_id);
        $this->assertSame($user->tenant_id, $voucher->tenant_id);
        $this->assertSame($router->id, $voucher->mikrotik_id);
        $this->assertSame('synced', $voucher->sync_status);
        $this->assertNotEmpty($voucher->username);
        $this->assertNotEmpty($voucher->public_token);
        $this->assertCount(1, $this->adds($fake));

        $session->user = $voucher->username;
        app(TenantManager::class)->forget();
        $ticketUrl = route('tickets.public', $voucher->public_token);
        $connected = $this->get($ticketUrl);
        $connected->assertOk()
            ->assertSee('Paiement confirmé')
            ->assertSee('Votre ticket est prêt')
            ->assertSee('Synchronisé')
            ->assertSee('10.5.5.8')
            ->assertSee('AA:BB:CC:DD:EE:FF')
            ->assertSee('23h50m')
            ->assertSee('Internet illimité')
            ->assertSee('Se connecter maintenant')
            ->assertSee('https://portail.limete.example/login', false)
            ->assertSee('Partager mon ticket sur WhatsApp')
            ->assertDontSee('secret-router', false)
            ->assertDontSee('Compte créé sur le MikroTik');
        $this->assertStringNotContainsString($voucher->password, $ticketUrl);
        $connected->assertDontSee($ticketUrl.$voucher->password, false);
        $share = $voucher->fresh(['plan', 'wifiZone'])->shareText();
        $this->assertStringContainsString('Limete', $share);
        $this->assertStringContainsString('24 HEURES', $share);
        $this->assertStringContainsString($voucher->username, $share);
        $this->assertStringContainsString($ticketUrl, $share);
        $this->assertStringNotContainsString($voucher->password, $share);
        $this->assertStringNotContainsString('secret-router', $share);
        $connected->assertSee('wa.me/243860392283?text='.urlencode($share), false);
        $this->assertMatchesRegularExpression('/État de connexion.{0,120}connecté/s', $connected->getContent());
        $this->assertDoesNotMatchRegularExpression('/État de connexion.{0,120}déconnecté/s', $connected->getContent());

        $session->user = null;
        $disconnected = $this->get($ticketUrl);
        $disconnected->assertOk();
        $this->assertMatchesRegularExpression('/État de connexion.{0,120}déconnecté/s', $disconnected->getContent());
        $this->assertSame($expires->getTimestamp(), $voucher->fresh()->expires_at->getTimestamp());

        $session->user = $voucher->username;
        $reconnected = $this->get($ticketUrl);
        $reconnected->assertOk();
        $this->assertMatchesRegularExpression('/État de connexion.{0,120}connecté/s', $reconnected->getContent());
        $this->assertDoesNotMatchRegularExpression('/État de connexion.{0,120}déconnecté/s', $reconnected->getContent());
        $voucher->refresh();
        $this->assertSame($expires->getTimestamp(), $voucher->expires_at->getTimestamp());
        $this->assertSame($voucher->activated_at->getTimestamp(), $voucher->fresh()->activated_at->getTimestamp());

        $this->getJson('/hotspot/session/'.$voucher->username)
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('unlimited', true)
            ->assertJsonMissingPath('password');

        $this->travel(25)->hours();
        $this->get($ticketUrl)
            ->assertOk()
            ->assertSee('Expiré')
            ->assertSee('Ce ticket n’est plus valide.')
            ->assertDontSee('Votre ticket est prêt');
        $voucher->refresh();
        $this->assertSame('expired', $voucher->status);
        $this->assertSame($expires->getTimestamp(), $voucher->expires_at->getTimestamp());

        $blob = implode("\n", (array) $lines);
        $this->assertStringNotContainsString($voucher->password, $blob);
        $this->assertStringNotContainsString('secret-router', $blob);
        $this->travelBack();
    }

    private function onlineShop(bool $session = false): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-journey-'.str()->lower(str()->random(4)).'@example.com', 'business');
        $zone = Platform::zone($user, 'Limete');
        $zone->update(['description' => 'Internet au marché', 'slogan' => 'Vite et clair']);
        $plan = Platform::plan($user, $zone);
        $router = Platform::router($user, $zone);
        $router->update(['status' => 'online']);
        $state = new \stdClass;
        $state->user = null;
        $fake = new FakeHotspotRouter(function (array $words) use ($state) {
            if (($words[0] ?? '') === '/ip/hotspot/active/print' && $state->user) {
                return [[
                    '!type' => '!re',
                    'user' => $state->user,
                    'address' => '10.5.5.8',
                    'mac-address' => 'AA:BB:CC:DD:EE:FF',
                    'session-time-left' => '23h50m',
                ], ['!type' => '!done']];
            }

            return [['!type' => '!done']];
        });
        $this->app->instance(HotspotRouter::class, $fake);

        return $session ? [$user, $zone, $plan, $router, $fake, $state] : [$user, $zone, $plan, $router, $fake];
    }

    private function notify(Payment $payment)
    {
        $payload = [
            'internal_reference' => $payment->internal_reference,
            'provider_reference' => 'OP-JOURNEY',
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'status' => 'success',
        ];
        $body = json_encode($payload);
        $secret = (string) config('limete.payments.airtel_money.webhook_secret');

        return $this->call('POST', '/payments/airtel_money/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAYMENT_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    private function adds(FakeHotspotRouter $fake): array
    {
        return array_values(array_filter(
            $fake->commands,
            fn (array $call) => ($call['words'][0] ?? '') === '/ip/hotspot/user/add'
        ));
    }
}
