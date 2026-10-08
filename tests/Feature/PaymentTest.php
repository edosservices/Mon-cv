<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentProviderSetting;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Support\QrCodes;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_checkout_stays_pending_until_a_signed_webhook_confirms_it(): void
    {
        [$user, $zone] = $this->shop();
        $sale = $this->order($zone, 'airtel_money');
        $payment = $sale->payment;

        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->internal_reference);
        $this->assertDatabaseCount('vouchers', 0);
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'?payment_status=success')
            ->assertOk()
            ->assertSee('Paiement en attente')
            ->assertDontSee('Votre ticket est prêt');
        $this->post('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'/actualiser')->assertRedirect();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);

        app(TenantManager::class)->forget();
        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'OP-1000'])->assertOk();

        $payment->refresh();
        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('success', $payment->status);
        $this->assertSame('OP-1000', $payment->provider_reference);
        $this->assertSame('paid', $sale->fresh()->status);
        $this->assertNotNull($voucher);
        $this->assertSame('active', $voucher->status);
        $this->assertSame($voucher->activated_at->getTimestamp() + 86400, $voucher->expires_at->getTimestamp());
        $this->assertSame(1, AuditLog::withoutGlobalScope('tenant')->where('action', 'payment.success')->count());
        $this->assertSame(1, AuditLog::withoutGlobalScope('tenant')->where('action', 'voucher.activated')->count());

        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('Paiement confirmé')
            ->assertSee($voucher->username)
            ->assertSee('1 000 FC');
    }

    public function test_a_failed_webhook_does_not_create_a_ticket(): void
    {
        [, $zone] = $this->shop();
        $payment = $this->order($zone, 'orange_money')->payment;

        $this->notify($payment, ['status' => 'failed', 'provider_reference' => 'OP-FAIL'], 'orange_money')->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'OP-FAIL'], 'orange_money')->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_amount_currency_and_provider_reference_must_match(): void
    {
        [, $zone] = $this->shop();
        $payment = $this->order($zone, 'mpesa')->payment;
        $payment->forceFill(['provider_reference' => 'OP-REAL'])->save();

        $this->notify($payment, ['status' => 'success', 'amount' => '500.00', 'provider_reference' => 'OP-REAL'], 'mpesa')
            ->assertStatus(422);
        $this->notify($payment, ['status' => 'success', 'currency' => 'USD', 'provider_reference' => 'OP-REAL'], 'mpesa')
            ->assertStatus(422);
        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'OP-FAKE'], 'mpesa')
            ->assertStatus(422);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_invalid_and_repeated_webhooks_do_not_create_extra_tickets(): void
    {
        $router = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $router);
        [$user, $zone, $plan] = $this->shop();
        $plan->update(['mikrotik_profile' => '24H']);
        Platform::router($user, $zone);
        $payment = $this->order($zone, 'card')->payment;

        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'CARD-1'], 'card', 'mauvaise-signature')
            ->assertUnauthorized();
        $this->assertSame('pending', $payment->fresh()->status);

        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'CARD-1', 'api_key' => 'secret-card-value'], 'card')
            ->assertOk();
        foreach (range(1, 4) as $ignored) {
            $this->notify($payment, ['status' => 'success', 'provider_reference' => 'CARD-1'], 'card')->assertOk();
        }

        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertSame(1, collect($router->commands)->filter(fn ($command) => ($command['words'][0] ?? '') === '/ip/hotspot/user/add')->count());
        $this->actingAs($user)->post('/sales/'.$payment->payable_id.'/confirm')->assertRedirect();
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $stored = PaymentEvent::withoutGlobalScope('tenant')->where('type', 'webhook')->first();
        $this->assertSame('[masqué]', $stored->payload['api_key']);
        $this->assertStringNotContainsString('secret-card-value', json_encode($stored->payload));
    }

    public function test_paid_ticket_is_unsynced_when_mikrotik_is_offline_and_can_be_retried(): void
    {
        $router = new FakeHotspotRouter(new RuntimeException('Connection refused'));
        $this->app->instance(HotspotRouter::class, $router);
        [$user, $zone, $plan] = $this->shop();
        $plan->update(['mikrotik_profile' => '24H']);
        $routerModel = Platform::router($user, $zone);
        $payment = $this->order($zone, 'airtel_money')->payment;

        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'OP-OFF'])->assertOk();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('active', $voucher->status);
        $this->assertNull($voucher->mikrotik_id);
        $this->assertSame('failed', $voucher->sync_status);
        $this->assertStringContainsString('Connection refused', $voucher->sync_error);

        $this->actingAs($user)->get('/payments')
            ->assertOk()
            ->assertSee('Non synchronisé')
            ->assertSee('Connection refused')
            ->assertSee('Réessayer sur le MikroTik')
            ->assertDontSee('Connexion réussie');

        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter);
        $this->actingAs($user)->post('/vouchers/'.$voucher->id.'/retry-sync')->assertRedirect();

        $voucher->refresh();
        $this->assertSame('synced', $voucher->sync_status);
        $this->assertSame($routerModel->id, $voucher->mikrotik_id);
    }

    public function test_manual_confirmation_activates_one_ticket_and_tenants_stay_isolated(): void
    {
        [$alice, $zone] = $this->shop();
        $sale = $this->order($zone, 'manual', 'MM123456');
        $this->assertSame('pending', $sale->payment->status);
        $this->assertSame('MM123456', $sale->payment->transaction_reference);

        $this->actingAs($alice)->get('/sales/'.$sale->id)->assertOk()->assertSee('Paiement à confirmer');
        $this->actingAs($alice)->post('/sales/'.$sale->id.'/confirm')->assertRedirect();
        $this->actingAs($alice)->post('/sales/'.$sale->id.'/confirm')->assertRedirect();

        $this->assertSame('success', $sale->payment->fresh()->status);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $expires = $voucher->expires_at->getTimestamp();
        $this->travel(25)->hours();
        app(TenantManager::class)->forget();
        $this->get('/ticket/'.$voucher->public_token)->assertOk()->assertSee('Expiré');
        $this->assertSame($expires, $voucher->fresh()->expires_at->getTimestamp());
        $this->travelBack();

        $bob = Platform::entrepreneur('Bob Wifi', 'bob-pay@example.com');
        $this->actingAs($bob)->get('/payments')->assertOk()->assertDontSee($sale->payment->internal_reference);
        $this->actingAs($bob)->get('/sales/'.$sale->id)->assertNotFound();
    }

    public function test_a_disabled_provider_and_secret_values_stay_out_of_the_admin_screen(): void
    {
        config(['limete.payments.airtel_money.api_key' => 'super-secret-airtel']);
        PaymentProviderSetting::query()->where('provider', 'airtel_money')->update(['enabled' => false]);
        [$user, $zone] = $this->shop();

        $planId = $zone->plans()->first()->id;
        $this->get('/wifi/'.$zone->slug.'/forfait/'.$planId)
            ->assertOk()
            ->assertDontSee('Airtel Money');
        $this->post('/wifi/'.$zone->slug.'/forfait/'.$planId, [
            'phone' => '+243810000321',
        ])->assertRedirect();
        $this->get('/wifi/'.$zone->slug.'/forfait/'.$planId.'/paiement')
            ->assertOk()
            ->assertDontSee('Airtel Money');
        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $zone->plans()->first()->id,
            'phone' => '+243810000321',
            'provider' => 'airtel_money',
        ])->assertRedirect();
        $this->assertDatabaseCount('sales', 0);

        Platform::seed();
        $admin = User::where('email', 'admin@limetewifi.local')->first();
        $this->actingAs($admin)->get('/admin/payments')
            ->assertOk()
            ->assertSee('Non configuré')
            ->assertSee('Configuré')
            ->assertSee('Désactivé')
            ->assertDontSee('super-secret-airtel', false);

        $payment = new Payment(['status' => PaymentStatus::Success->value]);
        $this->expectException(RuntimeException::class);
        $payment->transitionTo(PaymentStatus::Pending);
    }

    public function test_a_payment_is_created_pending_and_processing_does_not_activate_a_ticket(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        [, $zone] = $this->shop();
        $sale = $this->order($zone, 'airtel_money');
        $payment = $sale->payment;

        Http::assertNothingSent();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $sale->status);
        $this->assertFalse($payment->metadata['official_api']);
        $this->assertSame(1, AuditLog::withoutGlobalScope('tenant')->where('action', 'payment.created')->count());
        $this->assertDatabaseCount('vouchers', 0);

        $this->notify($payment, ['status' => 'processing', 'provider_reference' => 'OP-WAIT'])->assertOk();
        $this->notify($payment, ['status' => 'processing', 'provider_reference' => 'OP-WAIT'])->assertOk();

        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame('pending', $sale->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'?payment_status=success')
            ->assertOk()
            ->assertSee('Paiement en cours')
            ->assertSee('Vérification')
            ->assertDontSee('Votre ticket est prêt');
    }

    public function test_a_cancelled_webhook_does_not_create_a_ticket(): void
    {
        [, $zone] = $this->shop();
        $sale = $this->order($zone, 'mpesa');
        $payment = $sale->payment;

        $this->notify($payment, ['status' => 'cancelled', 'provider_reference' => 'MP-CANCEL'], 'mpesa')->assertOk();

        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('Paiement annulé')
            ->assertDontSee('Votre ticket est prêt');
        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'MP-CANCEL'], 'mpesa')->assertOk();
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_a_webhook_for_the_wrong_provider_is_ignored(): void
    {
        [, $zone] = $this->shop();
        config(['limete.payments.orange_money.webhook_secret' => 'whsec-test']);
        $payment = $this->order($zone, 'airtel_money')->payment;

        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'OR-1'], 'orange_money')
            ->assertNotFound();
        $this->notify($payment, ['status' => 'success'], 'airtel_money', 'signature-invalide')
            ->assertUnauthorized();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_confirmation_creates_one_ticket_with_a_public_qr_and_syncs_mikrotik(): void
    {
        $router = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $router);
        [$user, $zone, $plan] = $this->shop();
        $plan->update(['mikrotik_profile' => '24H']);
        Platform::router($user, $zone);
        $sale = $this->order($zone, 'card');
        $payment = $sale->payment;

        $this->assertDatabaseCount('vouchers', 0);
        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'CARD-OK'], 'card')->assertOk();
        $this->notify($payment, ['status' => 'success', 'provider_reference' => 'CARD-OK'], 'card')->assertOk();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertSame(1, Sale::withoutGlobalScope('tenant')->count());
        $this->assertSame('paid', $sale->fresh()->status);
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('synced', $voucher->sync_status);
        $this->assertSame(1, collect($router->commands)->filter(fn ($command) => ($command['words'][0] ?? '') === '/ip/hotspot/user/add')->count());

        $url = route('tickets.public', $voucher->public_token);
        $svg = QrCodes::svg($url);
        $this->assertStringNotContainsString($voucher->password, $svg);
        $page = $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token);
        $page->assertOk()
            ->assertSee('Paiement confirmé')
            ->assertSee('Votre ticket est prêt.')
            ->assertSee('24 HEURES')
            ->assertSee('Identifiant')
            ->assertSee($voucher->username)
            ->assertSee('Utiliser')
            ->assertSee('Imprimer')
            ->assertSee('Télécharger')
            ->assertSee('Partager')
            ->assertDontSee('Synchronisation en attente');
        $html = $page->getContent();
        $this->assertStringContainsString($svg, $html);
        $this->assertStringContainsString('data-ticket-url="'.$url.'"', $html);
        $this->assertStringContainsString('data-share="'.$url.'"', $html);
        $this->assertStringNotContainsString($voucher->password, $svg);

        $this->post('/ticket/'.$voucher->public_token.'/synchroniser')->assertRedirect();
        $this->assertSame(1, collect($router->commands)->filter(fn ($command) => ($command['words'][0] ?? '') === '/ip/hotspot/user/add')->count());
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_an_offline_router_leaves_sync_pending_until_retry(): void
    {
        $offline = new FakeHotspotRouter(new RuntimeException('Connection refused'));
        $this->app->instance(HotspotRouter::class, $offline);
        [$user, $zone, $plan] = $this->shop();
        $plan->update(['mikrotik_profile' => '24H']);
        Platform::router($user, $zone);
        $sale = $this->order($zone, 'orange_money');

        $this->notify($sale->payment, ['status' => 'success', 'provider_reference' => 'OR-OFF'], 'orange_money')->assertOk();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertNotNull($voucher);
        $this->assertSame('active', $voucher->status);
        $this->assertSame('failed', $voucher->sync_status);
        $this->assertNull($voucher->mikrotik_id);

        app(TenantManager::class)->forget();
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('Paiement confirmé')
            ->assertSee('Votre ticket est prêt.')
            ->assertSee('Synchronisation en attente')
            ->assertSee('Réessayer')
            ->assertSee($voucher->username);

        $this->post('/ticket/'.$voucher->public_token.'/synchroniser')->assertRedirect();
        $this->assertSame('failed', $voucher->fresh()->sync_status);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());

        $online = new FakeHotspotRouter;
        $this->app->instance(HotspotRouter::class, $online);
        $this->post('/ticket/'.$voucher->public_token.'/synchroniser')->assertRedirect();
        $this->post('/ticket/'.$voucher->public_token.'/synchroniser')->assertRedirect();

        $this->assertSame('synced', $voucher->fresh()->sync_status);
        $this->assertSame(1, collect($online->commands)->filter(fn ($command) => ($command['words'][0] ?? '') === '/ip/hotspot/user/add')->count());
    }

    public function test_a_public_guest_can_start_a_purchase_without_an_account(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        [, $zone, $plan] = $this->shop();

        $this->assertGuest();
        
       $this->get('/wifi/'.$zone->slug)
    ->assertOk()
    ->assertSee('Choisissez votre forfait', false);

$this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id)
    ->assertOk()
    ->assertSee('Sélection du forfait');

        $this->post('/wifi/'.$zone->slug.'/forfait/'.$plan->id, [
            'phone' => '+243810004444',
        ])->assertRedirect();
        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->assertOk()
            ->assertSee('Paiement')
            ->assertSee('Airtel Money')
            ->assertSee('Orange Money')
            ->assertSee('M-Pesa')
            ->assertSee('UniPay')
            ->assertSee('Carte');
        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810004444',
            'provider' => 'airtel_money',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->latest('id')->first();
        Http::assertNothingSent();
        $this->assertGuest();
        $this->assertSame('pending', $sale->status);
        $this->assertSame('pending', $sale->payment->status);
        $this->assertDatabaseCount('vouchers', 0);
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('Paiement en attente')
            ->assertSee('Paiement en cours')
            ->assertDontSee('Votre ticket est prêt');
    }

    public function test_a_signed_in_client_receives_the_ticket_only_after_confirmation(): void
    {
        [, $zone, $plan] = $this->shop();
        $this->post('/client/register', [
            'phone' => '0812000911',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ])->assertRedirect(route('client.dashboard'));

        $this->get('/client/acheter')->assertOk()->assertSee($zone->name);
        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243812000911',
            'name' => 'Amina',
            'provider' => 'airtel_money',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->latest('id')->first()->load('payment');
        $this->assertDatabaseCount('vouchers', 0);
        $this->get('/client/dashboard')
            ->assertOk()
            ->assertSee('Aucun ticket actif.');

        config(['limete.payments.airtel_money.webhook_secret' => 'whsec-test']);
        app(TenantManager::class)->forget();
        $this->notify($sale->payment, ['status' => 'success', 'provider_reference' => 'OP-CLIENT'])->assertOk();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertNotNull($voucher);
        $svg = QrCodes::svg(route('tickets.public', $voucher->public_token));
        $this->assertStringNotContainsString($voucher->password, $svg);
        $this->get('/client/dashboard')
            ->assertOk()
            ->assertSee($voucher->username)
            ->assertSee('24 HEURES');
        $this->assertStringContainsString($svg, $this->get('/client/dashboard')->getContent());
    }

    public function test_a_confirmed_sale_is_not_visible_to_another_tenant(): void
    {
        [, $zone] = $this->shop();
        $sale = $this->order($zone, 'manual', 'MM998877');
        $reference = $sale->payment->internal_reference;

        $bob = Platform::entrepreneur('Bob Wifi', 'bob-isolation@example.com');
        $this->actingAs($bob)->get('/payments')->assertOk()->assertDontSee($reference);
        $this->actingAs($bob)->get('/sales/'.$sale->id)->assertNotFound();
        $this->assertSame('pending', $sale->payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    private function shop(): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-pay@example.com');
        $zone = Platform::zone($user, 'Limete');
        $plan = Platform::plan($user, $zone);

        return [$user, $zone, $plan];
    }

    private function order($zone, string $provider, ?string $reference = null): Sale
    {
        config(['limete.payments.'.$provider.'.webhook_secret' => 'whsec-test']);
        $plan = $zone->plans()->first();
        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'name' => 'Amina',
            'phone' => '+243810009000',
            'provider' => $provider,
            'transaction_reference' => $reference,
        ])->assertRedirect();

        return Sale::withoutGlobalScope('tenant')->latest('id')->first()->load('payment');
    }

    private function notify(Payment $payment, array $overrides = [], string $provider = 'airtel_money', ?string $signature = null)
    {
        $payload = array_merge([
            'internal_reference' => $payment->internal_reference,
            'provider_reference' => 'OP-1',
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'status' => 'success',
        ], $overrides);
        $body = json_encode($payload);
        $secret = (string) config('limete.payments.'.$provider.'.webhook_secret');
        if ($signature === null) {
            $signature = hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', '/payments/'.$provider.'/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAYMENT_SIGNATURE' => $signature,
        ], $body);
    }
}
