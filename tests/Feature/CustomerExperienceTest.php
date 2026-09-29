<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Support\QrCodes;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\Support\Platform;
use Tests\TestCase;

class CustomerExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_renders_the_mobile_journey_without_hardcoded_badges(): void
    {
        [$user, $zone, $plan] = $this->shop();

        $this->get('/wifi/'.$zone->slug)
            ->assertOk()
            ->assertSee('width=device-width', false)
            ->assertSee('Internet rapide et accessible')
            ->assertSee('WiFi disponible')
            ->assertSee('Choisissez votre forfait')
            ->assertSee('Forfait')
            ->assertSee('Informations')
            ->assertSee('Paiement')
            ->assertSee('Confirmation')
            ->assertSee('Ticket')
            ->assertDontSee('Populaire')
            ->assertDontSee('Meilleure offre');

        $plan->update(['badge' => 'populaire']);

        $this->get('/wifi/'.$zone->slug)
            ->assertOk()
            ->assertSee('Populaire')
            ->assertSee($plan->name)
            ->assertSee('Acheter');
    }

    public function test_the_phone_step_rejects_an_invalid_number_and_an_inactive_plan(): void
    {
        [$user, $zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug.'/forfait/'.$plan->id, [
            'phone' => '12',
        ])->assertSessionHasErrors('phone');

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->assertRedirect('/wifi/'.$zone->slug.'/forfait/'.$plan->id);

        $plan->update(['status' => 'inactive']);
        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id)->assertNotFound();
        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810000999',
            'provider' => 'manual',
        ])->assertNotFound();

        $zone->update(['status' => 'inactive']);
        $this->get('/wifi/'.$zone->slug)->assertNotFound();
    }

    public function test_a_paid_ticket_shows_the_commercial_clock_the_qr_and_whatsapp(): void
    {
        [$user, $zone, $plan] = $this->shop();
        $voucher = $this->paidTicket($user, $zone, '+243810009999');
        $expires = $voucher->expires_at->copy();
        $label = $expires->timezone(config('app.timezone'))->format('d/m/Y H:i');

        app(TenantManager::class)->forget();
        $this->travel(4)->hours();

        $response = $this->get('/ticket/'.$voucher->public_token);
        $response->assertOk()
            ->assertSee('Actif')
            ->assertSee('Illimité')
            ->assertSee('Temps restant')
            ->assertSee($label)
            ->assertSee('Votre paiement a bien été confirmé.')
            ->assertSee('Votre ticket est créé.')
            ->assertSee('Le WiFi est temporairement en cours de synchronisation.')
            ->assertSee('Non synchronisé')
            ->assertDontSee('Compte WiFi prêt');

        $voucher->refresh();
        $this->assertSame($expires->getTimestamp(), $voucher->expires_at->getTimestamp());
        $this->assertSame('active', $voucher->status);

        $url = route('tickets.public', $voucher->public_token);
        $this->assertStringNotContainsString($voucher->password, $url);
        $response->assertSee(QrCodes::svg($url), false);

        $share = $voucher->fresh(['plan', 'wifiZone'])->shareText();
        $this->assertStringContainsString($zone->name, $share);
        $this->assertStringContainsString($plan->name, $share);
        $this->assertStringContainsString($voucher->username, $share);
        $this->assertStringContainsString($label, $share);
        $this->assertStringContainsString($url, $share);
        $this->assertStringNotContainsString($voucher->password, $share);
        $response->assertSee('wa.me/243860392283?text='.urlencode($share), false);

        $this->travelBack();
    }

    public function test_an_expired_ticket_sends_the_customer_back_to_the_shop(): void
    {
        [$user, $zone] = $this->shop();
        $voucher = $this->paidTicket($user, $zone, '+243810008888');
        $this->travel(25)->hours();
        app(TenantManager::class)->forget();

        $this->get('/ticket/'.$voucher->public_token)
            ->assertOk()
            ->assertSee('Expiré')
            ->assertSee('Ce ticket n’est plus valide.')
            ->assertSee('/wifi/'.$zone->slug)
            ->assertSee('Choisir un nouveau forfait')
            ->assertDontSee('Votre ticket est prêt');

        $this->travelBack();
    }

    public function test_a_guessed_ticket_address_stays_closed(): void
    {
        [$user, $zone] = $this->shop();
        $voucher = $this->paidTicket($user, $zone, '+243810007777');
        app(TenantManager::class)->forget();

        $this->get('/ticket/'.$voucher->id)->assertNotFound();
        $this->get('/ticket/'.str_repeat('b', 40))->assertNotFound();
        $this->get('/ticket/'.$voucher->public_token)->assertOk()->assertSee($voucher->username);

        $other = Platform::entrepreneur('Bob Wifi', 'bob-cx@example.com');
        $otherZone = Platform::zone($other, 'Kasa-Vubu');
        $foreign = $this->paidTicket($other, $otherZone, '+243810006666');

        $this->withSession([
            'customer_tickets.'.$zone->id => [$foreign->public_token],
        ])->get('/wifi/'.$zone->slug.'/mes-tickets')
            ->assertOk()
            ->assertDontSee($foreign->username);
    }

    public function test_the_shop_exposes_a_light_install_manifest(): void
    {
        [$user, $zone] = $this->shop();

        $this->get('/wifi/'.$zone->slug.'/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('name', $zone->name)
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', route('shop.show', $zone->slug));
    }

    public function test_the_dashboard_shows_ticket_badges_and_the_router_state(): void
    {
        [$user, $zone, $plan] = $this->shop();
        $router = Platform::router($user, $zone);
        $router->update([
            'status' => 'error',
            'last_seen_at' => now(),
            'last_error' => 'Connection refused',
        ]);
        $voucher = $this->paidTicket($user, $zone, '+243810005555');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('🟠 Erreur')
            ->assertSee('Dernière vérification')
            ->assertSee('Connection refused')
            ->assertDontSee('secret-router', false);

        $this->actingAs($user)->get('/vouchers')
            ->assertOk()
            ->assertSee($voucher->username)
            ->assertSee('Actif')
            ->assertSee('Non synchronisé')
            ->assertSee('Payé')
            ->assertSee('Réessayer')
            ->assertDontSee('secret-router', false);
    }

    public function test_the_captive_portal_points_to_the_shop_and_keeps_commercial_time(): void
    {
        $login = file_get_contents(base_path('hotspot/login.html'));
        $status = file_get_contents(base_path('hotspot/status.html'));
        $script = file_get_contents(base_path('hotspot/js/app.js'));

        $this->assertStringContainsString('Code du ticket', $login);
        $this->assertStringContainsString('name="password"', $login);
        $this->assertStringContainsString('SE CONNECTER', $login);
        $this->assertStringContainsString("Vous n'avez pas encore de ticket ?", $login);
        $this->assertStringContainsString('ACHETER UN FORFAIT', $login);
        $this->assertStringContainsString('id="buy-link"', $login);
        $this->assertStringContainsString('CONNECTÉ', $status);
        $this->assertStringContainsString('ILLIMITÉ', $status);
        $this->assertStringContainsString('Temps restant', $status);
        $this->assertStringContainsString('id="ticket-link"', $status);
        $this->assertStringContainsString('$(link-logout)', $status);
        $this->assertStringContainsString('session.expiresAt', $script);
        $this->assertStringContainsString('LIMETE_TICKET', $script);

        $check = <<<'JS'
const fs = require('fs');
const vm = require('vm');
const sandbox = { console };
sandbox.window = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync('hotspot/js/app.js', 'utf8'), sandbox);
const started = '2026-09-28T11:15:00+01:00';
const expires = '2026-09-29T11:15:00+01:00';
const view = sandbox.LimetePortal.resolveStatus(
  { username: 'LWTEST', uptime: '10m', uptimeSecs: '600', timeLeft: '23h', timeLeftSecs: '82800' },
  { planLabel: '24 HEURES', startedAt: started, expiresAt: expires, planSeconds: 86400 },
  { timezone: 'Africa/Kinshasa' },
  Date.parse('2026-09-28T15:15:00+01:00')
);
if (view.remainSeconds !== 72000) throw new Error('remain ' + view.remainSeconds);
if (view.state !== 'active') throw new Error('state ' + view.state);
if (!view.endText || view.endText.indexOf('29/09/2026') === -1) throw new Error('end ' + view.endText);
JS;

        $result = Process::path(base_path())->run(['node', '-e', $check]);
        $this->assertTrue($result->successful(), $result->errorOutput());
    }

    private function shop(): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-cx@example.com');
        $zone = Platform::zone($user, 'Limete');
        $zone->update(['description' => 'Internet au rond-point']);
        $plan = Platform::plan($user, $zone);

        return [$user, $zone, $plan];
    }

    private function paidTicket($user, $zone, string $phone): Voucher
    {
        app(TenantManager::class)->set($user->tenant_id);
        $plan = $zone->plans()->first() ?? Platform::plan($user, $zone);

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => $phone,
            'provider' => 'manual',
        ])->assertRedirect();

        $sale = \App\Models\Sale::withoutGlobalScope('tenant')->where('status', 'pending')->latest('id')->first();
        $this->actingAs($user)->post('/sales/'.$sale->id.'/confirm')->assertRedirect();

        return Voucher::withoutGlobalScope('tenant')->where('customer_id', $sale->fresh()->customer_id)->first();
    }
}
