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
            'services.ikeepay.checkout_url' => 'https://www.ikeepay.com/checkout/v1/inline',
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
            ->assertSee('https://www.ikeepay.com/checkout/v1/inline', false)
            ->assertSee('1000.00', false)
            ->assertSee('CDF', false)
            ->assertSee($payment->internal_reference, false)
            ->assertSee('client@mail.com', false)
            ->assertSee("event.origin !== 'https://www.ikeepay.com'", false)
            ->assertSee('ikeepay-success', false)
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
    }
}
