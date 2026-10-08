<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_shows_plans_from_the_database(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-shop@example.com');
        $zone = Platform::zone($user, 'Limete');
        $zone->update(['description' => 'Internet au marché']);
        $plan = Platform::plan($user, $zone);
        $plan->update(['name' => 'NUIT TEST', 'price' => 2750]);

        $this->get('/wifi/'.$zone->slug)
            ->assertOk()
            ->assertSee('Limete')
            ->assertSee('Internet au marché')
            ->assertSee('Choisissez votre forfait')
            ->assertSee('NUIT TEST')
            ->assertSee('2 750 FC')
            ->assertSee('Acheter')
            ->assertDontSee('500 FC');
    }

    public function test_buying_opens_a_confirmation_step(): void
    {
        [$user, $zone, $plan] = $this->shop();

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id)
            ->assertOk()
            ->assertSee('Votre forfait')
            ->assertSee('24 HEURES')
            ->assertSee('1 000 FC')
            ->assertSee('Entrez votre numéro de téléphone')
            ->assertDontSee($plan->password ?? 'secret-router');

        $user->tenant->forceFill(['ikeepay_public_key' => 'pk_shop_public'])->save();
        $this->post('/wifi/'.$zone->slug.'/forfait/'.$plan->id, [
            'phone' => '+243810000222',
        ])->assertRedirect('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement');

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->assertOk()
            ->assertSee('Comment souhaitez-vous payer')
            ->assertSee('Payer avec iKeePay')
            ->assertSee('Airtel Money — Indisponible', false)
            ->assertSee('Orange Money — Indisponible', false)
            ->assertSee('aucun pays iKeePay n’autorise l’opérateur AIRTEL', false)
            ->assertSee('disabled', false)
            ->assertDontSee('M-Pesa')
            ->assertDontSee('Carte bancaire')
            ->assertSee('Paiement manuel / comptoir')
            ->assertSee('+243810000222');

        $other = Platform::entrepreneur('Bob Wifi', 'bob-shop@example.com');
        $foreign = Platform::plan($other, Platform::zone($other));

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$foreign->id)->assertNotFound();
        $this->get('/wifi/'.$zone->slug.'/forfait/'.$foreign->id.'/paiement')->assertNotFound();
    }

    public function test_checkout_stays_pending_until_payment_is_confirmed(): void
    {
        [$user, $zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810000222',
            'provider' => 'airtel_money',
        ])->assertSessionHasErrors('provider');
        $this->assertDatabaseCount('sales', 0);

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810000222',
            'provider' => 'manual',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->first();
        app(TenantManager::class)->set($user->tenant_id);
        $sale->load('payment');

        $this->assertSame('pending', $sale->status);
        $this->assertSame('pending', $sale->payment->status);
        $this->assertSame('manual', $sale->payment->provider);
        $this->assertDatabaseCount('vouchers', 0);

        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('Paiement en attente')
            ->assertDontSee('Votre ticket est prêt');

        $this->actingAs($user)->post('/sales/'.$sale->id.'/confirm')->assertRedirect();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('active', $voucher->status);
        $this->assertNotNull($voucher->activated_at);
        $this->assertNotNull($voucher->expires_at);
        $this->assertSame(
            $voucher->activated_at->getTimestamp() + 86400,
            $voucher->expires_at->getTimestamp()
        );

        app(TenantManager::class)->forget();

        $this->get('/ticket/'.$voucher->public_token)
            ->assertOk()
            ->assertSee($voucher->username)
            ->assertSee('Actif')
            ->assertSee('24 HEURES')
            ->assertSee('Illimité')
            ->assertSee('Non synchronisé')
            ->assertDontSee('secret-router', false);
    }

    public function test_the_commercial_clock_does_not_restart(): void
    {
        [$user, $zone, $plan] = $this->shop();
        app(TenantManager::class)->set($user->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan, 1, true)[0];
        $expires = $voucher->expires_at->getTimestamp();

        $this->travel(3)->hours();
        app(VoucherGenerator::class)->activate($voucher->fresh());
        $voucher->refresh();
        $this->assertSame($expires, $voucher->expires_at->getTimestamp());
        $this->assertSame('active', $voucher->status);

        $this->travel(22)->hours();
        app(TenantManager::class)->forget();
        $this->get('/ticket/'.$voucher->public_token)
            ->assertOk()
            ->assertSee('Expiré');

        $voucher->refresh();
        $this->assertSame('expired', $voucher->status);
        $this->assertSame($expires, $voucher->expires_at->getTimestamp());
        $this->travelBack();
    }

    public function test_a_customer_cannot_open_someone_elses_ticket_from_the_recovery_form(): void
    {
        [$user, $zone] = $this->shop();
        $voucher = $this->paidTicket($user, $zone, '+243810000333');
        $intruder = $this->paidTicket($user, $zone, '+243810000444');

        $this->post('/wifi/'.$zone->slug.'/mes-tickets', [
            'phone' => '+243810000333',
            'username' => $intruder->username,
        ])->assertSessionHas('warning');

        $this->assertNotContains(
            $intruder->public_token,
            session('customer_tickets.'.$zone->id, [])
        );

        $this->post('/wifi/'.$zone->slug.'/mes-tickets', [
            'phone' => '+243810000333',
            'username' => $voucher->username,
        ])->assertRedirect('/ticket/'.$voucher->public_token);

        $other = Platform::entrepreneur('Bob Wifi', 'bob-tickets@example.com');
        $otherZone = Platform::zone($other, 'Kingabwa');
        $foreign = $this->paidTicket($other, $otherZone, '+243810000555');

        $this->withSession([
            'customer_tickets.'.$zone->id => [$foreign->public_token],
        ])->get('/wifi/'.$zone->slug.'/mes-tickets')
            ->assertOk()
            ->assertDontSee($foreign->username);

        $this->get('/ticket/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_an_offline_router_keeps_the_ticket_unsynced(): void
    {
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter(
            new RuntimeException('Connexion impossible au routeur.')
        ));

        [$user, $zone, $plan] = $this->shop();
        $plan->update(['mikrotik_profile' => '24H']);
        Platform::router($user, $zone);

        $voucher = $this->paidTicket($user, $zone, '+243810000666');

        $this->assertNull($voucher->mikrotik_id);
        $this->assertSame('failed', $voucher->sync_status);

        app(TenantManager::class)->forget();
        $this->get('/ticket/'.$voucher->public_token)
            ->assertOk()
            ->assertSee('Non synchronisé')
            ->assertDontSee('créé sur le MikroTik')
            ->assertDontSee('Créé sur le MikroTik')
            ->assertDontSee('secret-router', false);
    }

    public function test_the_dashboard_links_to_the_public_shop(): void
    {
        [$user, $zone] = $this->shop();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Voir ma boutique')
            ->assertSee('/wifi/'.$zone->slug);

        $this->actingAs($user)->get('/wifi-zones')
            ->assertOk()
            ->assertSee('Copier le lien');
    }

    public function test_the_public_ticket_can_be_downloaded(): void
    {
        [$user, $zone] = $this->shop();
        $voucher = $this->paidTicket($user, $zone, '+243810000777');

        app(TenantManager::class)->forget();
        $this->get('/ticket/'.$voucher->public_token.'/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function shop(): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-flow@example.com');
        $zone = Platform::zone($user, 'Limete');
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
            'transaction_reference' => 'REF'.$phone,
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->where('status', 'pending')->latest('id')->first();
        $this->actingAs($user)->post('/sales/'.$sale->id.'/confirm')->assertRedirect();

        return Voucher::withoutGlobalScope('tenant')->where('customer_id', $sale->fresh()->customer_id)->first();
    }
}
