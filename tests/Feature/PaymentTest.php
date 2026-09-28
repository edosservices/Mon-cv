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
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$zone->plans()->first()->id)
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
