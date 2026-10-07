<?php

namespace Tests\Feature;

use App\Jobs\SyncHotspotUser;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\Payments\IkeePayCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\Platform;
use Tests\TestCase;

class IkeePayH2hTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'SECRET-IKEE-DO-NOT-LEAK';

    /**
     * @var array<string, mixed>
     */
    private array $payinBody = [
        'provider_reference' => 'IKP-H2H-B5C9EC0E',
        'payment_link' => 'https://pay.example/wave',
        'status' => 'pending',
    ];

    private int $payinHttpStatus = 200;

    private string $verifyStatus = 'pending';

    private ?string $capturedReference = null;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/h2h-payin')) {
                $this->capturedReference = $request->data()['external_reference'] ?? null;

                return Http::response($this->payinBody, $this->payinHttpStatus);
            }

            if (str_contains($request->url(), '/h2h-verify/')) {
                return Http::response([
                    'status' => 'success',
                    'data' => [
                        'external_reference' => $this->capturedReference,
                        'provider_reference' => $this->payinBody['provider_reference'] ?? null,
                        'amount' => 1000,
                        'currency' => 'CDF',
                        'status' => $this->verifyStatus,
                        'type' => 'payin',
                        'operator' => 'ORANGE',
                        'created_at' => '2026-08-08T18:00:00Z',
                    ],
                ], 200);
            }
        });
        config([
            'services.ikeepay.public_key' => 'pk_test_public_only',
            'services.ikeepay.secret_key' => $this->secret,
            'services.ikeepay.base_url' => 'https://api.ikeepay.com',
            'services.ikeepay.checkout_url' => 'https://www.ikeepay.com/checkout/v1/inline',
            'ikeepay.countries' => [
                'CI' => ['ORANGE', 'MTN', 'WAVE', 'MOOV', 'MOBICASH'],
            ],
        ]);
    }

    public function test_payin_sends_the_sale_and_stores_the_provider_reference_and_link(): void
    {
        $sale = $this->pay('https://pay.example/wave');
        $payment = $sale->payment;

        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $sale->status);
        $this->assertSame('IKP-H2H-B5C9EC0E', $payment->provider_reference);
        $this->assertSame('https://pay.example/wave', $payment->metadata['payment_link']);
        $this->assertSame('PAY-', substr($payment->internal_reference, 0, 4));
        $this->assertSame('1000.00', number_format((float) $payment->amount, 2, '.', ''));
        $this->assertSame('CDF', $payment->currency);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertNotDispatched(SyncHotspotUser::class);
        $this->assertStringNotContainsString($this->secret, json_encode($payment->metadata));

        Http::assertSent(function ($request) use ($payment) {
            $body = $request->data();

            return $request->url() === 'https://api.ikeepay.com/h2h-payin'
                && $request->hasHeader('x-api-key', $this->secret)
                && $body['external_reference'] === $payment->internal_reference
                && $body['amount'] === 1000
                && $body['currency'] === 'CDF'
                && $body['country'] === 'CI'
                && $body['phoneNumber'] === '2250700000000'
                && $body['operator'] === 'ORANGE'
                && $body['customer_email'] === 'client@example.com'
                && ! array_key_exists('otp', $body)
                && ! in_array($this->secret, $body, true);
        });

        $this->get('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('https://pay.example/wave', false)
            ->assertSee('Continuer le paiement', false)
            ->assertDontSee('Revenir au paiement iKeePay', false)
            ->assertDontSee($this->secret, false);

        $this->get('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token.'/ikeepay')
            ->assertRedirect('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token);
    }

    public function test_payin_sends_otp_only_when_the_customer_provides_one(): void
    {
        $this->fakePayin();
        [$zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug, $this->order($plan, ['otp' => '123456']))
            ->assertRedirect('https://pay.example/wave');

        Http::assertSent(fn ($request) => $request->data()['otp'] === '123456');
    }

    public function test_a_refused_payin_creates_no_voucher(): void
    {
        $this->fakePayin(['status' => 'failed'], 422);
        [$zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug, $this->order($plan))
            ->assertRedirect();

        $payment = Payment::withoutGlobalScope('tenant')->first();
        $this->assertSame('failed', $payment->status);
        $this->assertNull($payment->provider_reference);
        $this->assertNull($payment->paid_at);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertNotDispatched(SyncHotspotUser::class);

        $sale = Sale::withoutGlobalScope('tenant')->first();
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('Paiement échoué', false)
            ->assertDontSee($this->secret, false)
            ->assertDontSee('Votre ticket est prêt', false);
    }

    public function test_a_server_error_stays_pending_until_the_webhook_confirms(): void
    {
        $this->payinHttpStatus = 503;
        $this->payinBody = ['status' => 'pending'];
        [$zone, $plan] = $this->shop('timeout@example.com');

        $this->post('/wifi/'.$zone->slug, $this->order($plan, ['email' => 'timeout@example.com']));

        $sale = Sale::withoutGlobalScope('tenant')->with('payment')->first();
        $this->assertSame('pending', $sale->payment->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());

        $this->postJson('/payments/ikeepay/webhook', $this->notice($sale->payment, 'completed', [
            'data' => ['provider_reference' => 'IKP-H2H-TIMEOUT'],
        ]))->assertOk();

        $this->assertSame('success', $sale->payment->fresh()->status);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertDispatchedTimes(SyncHotspotUser::class, 1);
    }

    public function test_an_insecure_payment_link_is_ignored(): void
    {
        $this->fakePayin(['payment_link' => 'http://pay.example/wave', 'status' => 'pending']);
        [$zone, $plan] = $this->shop();

        $response = $this->post('/wifi/'.$zone->slug, $this->order($plan));
        $sale = Sale::withoutGlobalScope('tenant')->first();

        $response->assertRedirect('/wifi/'.$zone->slug.'/commande/'.$sale->public_token);
        $this->assertArrayNotHasKey('payment_link', $sale->payment->metadata ?? []);
    }

    public function test_an_operator_is_not_offered_for_a_country_that_is_not_configured(): void
    {
        config(['ikeepay.countries' => []]);
        [$zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug.'/forfait/'.$plan->id, [
            'phone' => '+2250700000000',
            'email' => 'client@example.com',
        ])->assertRedirect();

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->assertOk()
            ->assertDontSee('Payer avec cet opérateur', false)
            ->assertDontSee('CD —', false)
            ->assertDontSee($this->secret, false);

        $this->from('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->post('/wifi/'.$zone->slug, $this->order($plan, [
                'country' => 'CD',
                'operator' => 'AIRTEL',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('operator');

        $this->assertDatabaseCount('sales', 0);
        Http::assertNothingSent();
        $this->assertSame([], app(IkeePayCatalog::class)->countries());
    }

    public function test_verify_completed_confirms_the_sale_once(): void
    {
        $sale = $this->pay();
        $this->verifyStatus = 'completed';

        $this->post('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token.'/actualiser')
            ->assertRedirect();

        $sale->refresh();
        $this->assertSame('paid', $sale->status);
        $this->assertSame('success', $sale->payment->fresh()->status);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertDispatchedTimes(SyncHotspotUser::class, 1);

        $this->post('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token.'/actualiser')
            ->assertRedirect();

        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertDispatchedTimes(SyncHotspotUser::class, 1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/h2h-verify/IKP-H2H-B5C9EC0E')
            && $request->hasHeader('x-api-key', $this->secret));
    }

    public function test_verify_pending_and_failed_do_not_create_a_voucher(): void
    {
        $sale = $this->pay();
        $this->verifyStatus = 'pending';

        $this->post('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token.'/actualiser');
        $this->assertSame('pending', $sale->payment->fresh()->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());

        $this->verifyStatus = 'failed';

        $this->post('/wifi/'.$sale->wifiZone->slug.'/commande/'.$sale->public_token.'/actualiser');
        $this->assertSame('failed', $sale->payment->fresh()->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertNotDispatched(SyncHotspotUser::class);
    }

    public function test_webhook_created_pending_does_not_confirm(): void
    {
        $sale = $this->pay();

        $this->postJson('/payments/ikeepay/webhook', $this->notice($sale->payment, 'pending', [
            'event' => 'transaction.created',
        ]))->assertOk();

        $this->assertSame('pending', $sale->payment->fresh()->status);
        $this->assertSame('pending', $sale->fresh()->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_webhook_updated_completed_confirms_once_even_when_repeated(): void
    {
        $sale = $this->pay();
        $payload = $this->notice($sale->payment, 'completed');

        $this->postJson('/payments/ikeepay/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'accepted'])
            ->assertDontSee($this->secret, false);

        $this->postJson('/payments/ikeepay/webhook', $payload)->assertOk();
        $this->postJson('/payments/ikeepay/webhook', $payload)->assertOk();

        $sale->refresh();
        $payment = $sale->payment->fresh();
        $this->assertSame('paid', $sale->status);
        $this->assertSame('success', $payment->status);
        $this->assertSame('IKP-H2H-B5C9EC0E', $payment->provider_reference);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertDispatchedTimes(SyncHotspotUser::class, 1);
    }

    public function test_webhook_failed_marks_the_payment_failed(): void
    {
        $sale = $this->pay();

        $this->postJson('/payments/ikeepay/webhook', $this->notice($sale->payment, 'failed'))
            ->assertOk();

        $this->assertSame('failed', $sale->payment->fresh()->status);
        $this->assertSame('pending', $sale->fresh()->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertNotDispatched(SyncHotspotUser::class);
    }

    public function test_webhook_rejects_a_wrong_amount_currency_or_unknown_reference(): void
    {
        $sale = $this->pay();

        $this->postJson('/payments/ikeepay/webhook', $this->notice($sale->payment, 'completed', [
            'data' => ['amount' => 5],
        ]))->assertStatus(422);

        $this->postJson('/payments/ikeepay/webhook', $this->notice($sale->payment, 'completed', [
            'data' => ['currency' => 'EUR'],
        ]))->assertStatus(422);

        $this->postJson('/payments/ikeepay/webhook', $this->notice($sale->payment, 'completed', [
            'data' => ['external_reference' => 'PAY-INCONNUE'],
        ]))->assertNotFound();

        $this->postJson('/payments/ikeepay/webhook', [
            'status' => 'success',
            'order_id' => $sale->payment->internal_reference,
        ])->assertStatus(422);

        $this->assertSame('pending', $sale->payment->fresh()->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        Bus::assertNotDispatched(SyncHotspotUser::class);
    }

    public function test_a_provider_reference_cannot_be_reused_for_another_payment(): void
    {
        $first = $this->pay();
        $second = $this->pay('https://pay.example/second', 'autre@example.com', 'IKP-H2H-SECOND1');
        $second->payment->forceFill(['provider_reference' => null])->save();

        $this->postJson('/payments/ikeepay/webhook', $this->notice($second->payment, 'completed', [
            'data' => ['provider_reference' => $first->payment->provider_reference],
        ]))->assertStatus(422);

        $this->assertSame('pending', $second->payment->fresh()->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function order($plan, array $extra = []): array
    {
        return array_merge([
            'plan_id' => $plan->id,
            'phone' => '+2250700000000',
            'email' => 'client@example.com',
            'provider' => 'ikeepay',
            'country' => 'CI',
            'operator' => 'ORANGE',
            'amount' => '5.00',
            'currency' => 'USD',
        ], $extra);
    }

    private function pay(?string $link = 'https://pay.example/wave', string $email = 'client@example.com', string $reference = 'IKP-H2H-B5C9EC0E'): Sale
    {
        $body = [
            'provider_reference' => $reference,
            'status' => 'pending',
        ];
        if ($link !== null) {
            $body['payment_link'] = $link;
        }

        $this->fakePayin($body);
        [$zone, $plan] = $this->shop($email);
        $this->post('/wifi/'.$zone->slug, $this->order($plan, ['email' => $email]))
            ->assertRedirect($link);

        return Sale::withoutGlobalScope('tenant')->with('payment', 'wifiZone')->whereHas('customer', fn ($query) => $query->where('email', $email))->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function fakePayin(array $body = [], int $status = 200): void
    {
        $body = array_merge([
            'provider_reference' => 'IKP-H2H-B5C9EC0E',
            'payment_link' => 'https://pay.example/wave',
            'status' => 'pending',
        ], $body);

        $this->payinBody = $body;
        $this->payinHttpStatus = $status;
    }

    /**
     * @return array{0: WifiZone, 1: \App\Models\Plan}
     */
    private function shop(string $email = 'client@example.com'): array
    {
        $owner = Platform::entrepreneur('Alice Wifi', $email);
        $zone = Platform::zone($owner, 'Limete '.$email);
        $plan = Platform::plan($owner, $zone);

        return [$zone, $plan];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function notice(Payment $payment, string $status, array $overrides = []): array
    {
        $payload = [
            'event' => 'transaction.updated',
            'data' => [
                'type' => 'payin',
                'external_reference' => $payment->internal_reference,
                'provider_reference' => $payment->provider_reference ?: 'IKP-H2H-B5C9EC0E',
                'amount' => 1000,
                'currency' => 'CDF',
                'country' => 'CI',
                'phone_number' => '2250700000000',
                'operator' => 'ORANGE',
                'status' => $status,
                'site' => 'Production',
                'created_at' => '2026-08-08T18:00:00Z',
                'updated_at' => '2026-08-08T18:00:05Z',
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }
}
