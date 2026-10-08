<?php

namespace Tests\Feature;

use App\Jobs\SyncHotspotUser;
use App\Models\Sale;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\Platform;
use Tests\TestCase;

class IkeePayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private string $publicKey = 'pk_test_public_only';

    private string $secret = 'SECRET-IKEE-DO-NOT-LEAK';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ikeepay.public_key' => $this->publicKey,
            'services.ikeepay.secret_key' => $this->secret,
            'services.ikeepay.checkout_url' => 'https://ikeepay.com/checkout/v1/inline',
            'services.ikeepay.base_url' => 'https://api.ikeepay.com',
        ]);
    }

    public function test_the_inline_checkout_uses_the_stored_sale_and_stays_pending(): void
    {
        Bus::fake();
        Http::preventStrayRequests();

        $owner = Platform::entrepreneur('Alice Wifi', 'alice-ikeepay@example.com');
        $owner->tenant->forceFill([
            'ikeepay_public_key' => $this->publicKey,
            'ikeepay_secret_key' => $this->secret,
        ])->save();
        config(['services.ikeepay.public_key' => 'pk_global_must_not_appear']);
        $zone = Platform::zone($owner, 'Limete');
        $plan = Platform::plan($owner, $zone);

        $this->post('/wifi/'.$zone->slug.'/forfait/'.$plan->id, [
            'phone' => '+243810004242',
            'email' => 'client@mail.com',
        ])->assertRedirect();

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810004242',
            'email' => 'client@mail.com',
            'provider' => 'ikeepay',
            'amount' => '1.00',
            'currency' => 'USD',
            'order_id' => 'ORDER_test_123',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->with('payment', 'customer')->first();
        $payment = $sale->payment;

        $this->assertSame('pending', $sale->status);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('ikeepay', $payment->provider);
        $this->assertSame('1000.00', number_format((float) $payment->amount, 2, '.', ''));
        $this->assertSame('CDF', $payment->currency);
        $this->assertStringStartsWith('PAY-', $payment->internal_reference);
        $this->assertSame('client@mail.com', $sale->customer->email);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertNotDispatched(SyncHotspotUser::class);

        $page = $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'/ikeepay');
        $page->assertOk()
            ->assertSee($this->publicKey, false)
            ->assertSee('https://ikeepay.com/checkout/v1/inline', false)
            ->assertSee('data-origin="https://ikeepay.com"', false)
            ->assertSee('1000.00', false)
            ->assertSee('CDF', false)
            ->assertSee($payment->internal_reference, false)
            ->assertSee('redirect_url', false)
            ->assertSee('ikeepay-ready', false)
            ->assertSee('ikeepay-success', false)
            ->assertSee('ikeepay-close', false)
            ->assertDontSee('client@mail.com', false)
            ->assertDontSee('email=', false)
            ->assertDontSee('Paiement validé', false)
            ->assertDontSee($this->secret, false)
            ->assertDontSee('pk_global_must_not_appear', false)
            ->assertDontSee('ORDER_test_', false)
            ->assertDontSee('markPaymentAsPaid', false);

        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('Paiement en attente', false)
            ->assertDontSee('Votre ticket est prêt', false);

        $this->postJson('/payments/ikeepay/webhook', [
            'status' => 'success',
            'order_id' => $payment->internal_reference,
            'amount' => '1000.00',
            'currency' => 'CDF',
        ])->assertStatus(422);

        $payment->refresh();
        $sale->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertSame('pending', $sale->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertNotDispatched(SyncHotspotUser::class);
        Http::assertNothingSent();
    }

    public function test_ikeepay_requires_a_valid_email_and_ignores_a_browser_amount(): void
    {
        $owner = Platform::entrepreneur('Alice Wifi', 'alice-ikeepay-mail@example.com');
        $owner->tenant->forceFill(['ikeepay_public_key' => $this->publicKey])->save();
        $zone = Platform::zone($owner, 'Limete');
        $plan = Platform::plan($owner, $zone);

        $this->from('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->post('/wifi/'.$zone->slug, [
                'plan_id' => $plan->id,
                'phone' => '+243810001111',
                'email' => 'pas-un-email',
                'provider' => 'ikeepay',
                'amount' => '5.00',
            ])->assertRedirect('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('vouchers', 0);

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810001111',
            'provider' => 'ikeepay',
            'amount' => '5.00',
        ])->assertRedirect();

        $sale = \App\Models\Sale::withoutGlobalScope('tenant')->first();
        $this->assertSame('1000.00', number_format((float) $sale->total_amount, 2, '.', ''));
        $this->assertSame('CDF', $sale->currency);
        $this->assertDatabaseCount('vouchers', 0);

        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'/ikeepay')
            ->assertOk()
            ->assertSee('id="ikeepay-frame"', false)
            ->assertDontSee('ne peut pas s’ouvrir', false);
    }

    public function test_the_documented_inline_webhook_confirms_the_sale_once(): void
    {
        Bus::fake();
        Http::preventStrayRequests();
        $sale = $this->inlineSale();
        $payment = $sale->payment;

        $payload = [
            'event' => 'payment.success',
            'ikeepay_ref' => 'IKP-H2H-B5C9EC0E',
            'order_id' => $payment->internal_reference,
            'amount' => 1000,
            'currency' => 'CDF',
            'status' => 'completed',
        ];

        $this->postJson('/payments/ikeepay/webhook', $payload)->assertOk();
        $this->postJson('/payments/ikeepay/webhook', $payload)->assertOk();

        $sale->refresh();
        $this->assertSame('paid', $sale->status);
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('IKP-H2H-B5C9EC0E', $payment->fresh()->provider_reference);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertDispatchedTimes(SyncHotspotUser::class, 1);
    }

    public function test_an_inline_webhook_with_the_wrong_amount_creates_no_ticket(): void
    {
        Bus::fake();
        $sale = $this->inlineSale();

        $this->postJson('/payments/ikeepay/webhook', [
            'event' => 'payment.success',
            'ikeepay_ref' => 'IKP-H2H-B5C9EC0E',
            'order_id' => $sale->payment->internal_reference,
            'amount' => 1,
            'currency' => 'CDF',
            'status' => 'completed',
        ])->assertStatus(422);

        $this->assertSame('pending', $sale->payment->fresh()->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertNotDispatched(SyncHotspotUser::class);
    }

    public function test_inline_verify_uses_get_checkout_and_the_entrepreneur_secret(): void
    {
        Bus::fake();
        config(['services.ikeepay.secret_key' => 'GLOBAL-KEY-MUST-NOT-BE-USED']);
        $sale = $this->inlineSale();
        $payment = $sale->payment;

        Http::fake([
            'https://api.ikeepay.com/checkout/'.$payment->internal_reference => Http::response([
                'event' => 'payment.success',
                'ikeepay_ref' => 'IKP-H2H-B5C9EC0E',
                'order_id' => $payment->internal_reference,
                'amount' => 1000,
                'currency' => 'CDF',
                'status' => 'completed',
            ]),
        ]);

        $this->post('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token.'/actualiser')
            ->assertRedirect();

        $this->assertSame('paid', $sale->fresh()->status);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        Http::assertSent(fn ($request) => $request->url() === 'https://api.ikeepay.com/checkout/'.$payment->internal_reference
            && $request->method() === 'GET'
            && $request->hasHeader('x-api-key', $this->secret)
            && ! $request->hasHeader('x-api-key', 'GLOBAL-KEY-MUST-NOT-BE-USED'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/h2h-verify/'));

        $this->post('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token.'/actualiser')
            ->assertRedirect();
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_a_repeated_inline_checkout_reuses_the_pending_order(): void
    {
        Http::preventStrayRequests();
        $sale = $this->inlineSale();

        $this->post('/wifi/'.$sale->wifiZone->slug, [
            'plan_id' => $sale->items()->first()->plan_id,
            'phone' => '+243810004242',
            'provider' => 'ikeepay',
        ])->assertRedirect('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token.'/ikeepay');

        $this->assertSame(1, Sale::withoutGlobalScope('tenant')->count());
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
    }

    private function inlineSale(): Sale
    {
        $owner = Platform::entrepreneur('Alice Wifi', 'alice-inline-'.uniqid().'@example.com');
        $owner->tenant->forceFill([
            'ikeepay_public_key' => $this->publicKey,
            'ikeepay_secret_key' => $this->secret,
        ])->save();
        $zone = Platform::zone($owner, 'Limete');
        $plan = Platform::plan($owner, $zone);

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810004242',
            'provider' => 'ikeepay',
        ])->assertRedirect();

        return Sale::withoutGlobalScope('tenant')->with('payment', 'wifiZone')->firstOrFail();
    }
}
