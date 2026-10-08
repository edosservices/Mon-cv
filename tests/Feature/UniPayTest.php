<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\Payments\UniPayGateway;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class UniPayTest extends TestCase
{
    use RefreshDatabase;

    private string $key = 'fake-unipay-key';

    private string $secret = 'fake-unipay-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter);
    }

    public function test_unipay_is_not_configured_and_sends_nothing(): void
    {
        [, $zone] = $this->shop();

        $sale = $this->checkout($zone);
        $payment = $sale->payment;

        Http::assertNothingSent();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('UniPay non configuré', $payment->metadata['note']);
        $this->assertDatabaseCount('vouchers', 0);
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'?payment_status=success')
            ->assertOk()
            ->assertSee('Paiement en cours…')
            ->assertSee('Confirmation du paiement…')
            ->assertSee('Paiement en attente')
            ->assertDontSee('UniPay non configuré', false)
            ->assertDontSee('Votre ticket est prêt')
            ->assertDontSee($this->key, false);
    }

    public function test_a_configured_initiation_stays_pending_until_a_verified_success(): void
    {
        [, $zone] = $this->shop();
        $this->configure();
        Http::fake([
            'https://unipay.test/payments' => Http::response([
                'transaction_id' => 'UP-100',
                'reference' => 'ignored-until-read',
                'status' => 'SUCCESS',
                'amount' => '1000.00',
                'currency' => 'CDF',
            ], 201),
        ]);

        $sale = $this->checkout($zone);
        $payment = $sale->payment->fresh();

        Http::assertSent(function ($request) use ($payment, $zone, $sale) {
            return $request->method() === 'POST'
                && $request->url() === 'https://unipay.test/payments'
                && $request->hasHeader('Authorization', 'Bearer '.$this->key)
                && $request->hasHeader('X-UniPay-Mode', 'test')
                && $request['reference'] === $payment->internal_reference
                && $request['amount'] === '1000.00'
                && $request['currency'] === 'CDF'
                && $request['customer']['phone'] === '+243810009000'
                && $request['customer']['name'] === 'Amina'
                && $request['callback_url'] === route('payments.webhook', ['provider' => 'unipay'])
                && $request['metadata']['tenant_id'] === $zone->tenant_id
                && $request['metadata']['wifi_zone_id'] === $zone->id
                && $request['metadata']['sale_id'] === $sale->id
                && $request['metadata']['plan_reference'] === '24 HEURES';
        });
        $this->assertSame('pending', $payment->status);
        $this->assertSame('UP-100', $payment->provider_reference);
        $this->assertDatabaseCount('vouchers', 0);

        $sent = count(Http::recorded());
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token.'?payment_status=success')
            ->assertOk()
            ->assertSee('Paiement en attente')
            ->assertDontSee('Votre ticket est prêt');
        $this->assertCount($sent, Http::recorded());
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_status_checks_map_pending_processing_success_failed_and_cancelled(): void
    {
        [, $zone] = $this->shop();
        $this->configure();
        $payment = null;
        $next = 'processing';
        Http::fake(function ($request) use (&$payment, &$next) {
            if ($request->method() === 'POST') {
                return Http::response(['transaction_id' => 'UP-200', 'status' => 'pending'], 201);
            }

            return Http::response($this->statusBody($payment, $next), 200);
        });
        $payment = $this->checkout($zone)->payment->fresh();

        $this->post('/wifi/'.$zone->slug.'/commande/'.$payment->payable->public_token.'/actualiser')->assertRedirect();
        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);

        foreach (['failed', 'cancelled'] as $status) {
            Payment::withoutGlobalScope('tenant')->whereKey($payment->id)->update(['status' => 'pending']);
            $next = $status;
            app(UniPayGateway::class)->checkPayment($payment->fresh());
            $this->assertSame($status, $payment->fresh()->status);
            $this->assertDatabaseCount('vouchers', 0);
        }

        Payment::withoutGlobalScope('tenant')->whereKey($payment->id)->update(['status' => 'pending']);
        $next = 'paid';
        app(UniPayGateway::class)->checkPayment($payment->fresh());
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_a_webhook_rejects_a_bad_signature_amount_currency_or_reference_and_ignores_duplicates(): void
    {
        [, $zone] = $this->shop();
        $this->configure();
        Http::fake([
            'https://unipay.test/payments' => Http::response(['transaction_id' => 'UP-300', 'status' => 'pending'], 201),
        ]);
        $payment = $this->checkout($zone)->payment;

        $this->notify($payment, ['status' => 'success'], 'mauvaise-signature')->assertUnauthorized();
        $this->notify($payment, ['reference' => 'PAY-INCONNUE', 'status' => 'success'])->assertNotFound();
        $this->notify($payment, ['amount' => '500.00', 'status' => 'success'])->assertStatus(422);
        $this->notify($payment, ['currency' => 'USD', 'status' => 'success'])->assertStatus(422);
        $this->notify($payment, ['transaction_id' => 'UP-AUTRE', 'status' => 'success'])->assertStatus(422);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);

        $this->notify($payment, ['status' => 'success', 'api_key' => $this->key])->assertOk();
        $this->notify($payment, ['status' => 'success'])->assertOk();
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertSame('success', $payment->fresh()->status);
        $stored = PaymentEvent::withoutGlobalScope('tenant')->where('type', 'webhook')->first();
        $this->assertSame('[masqué]', $stored->payload['api_key']);
        $this->assertStringNotContainsString($this->key, json_encode($stored->payload));
    }

    public function test_pending_and_failed_webhooks_do_not_activate_a_ticket(): void
    {
        [, $zone] = $this->shop();
        $this->configure();
        Http::fake([
            'https://unipay.test/payments' => Http::response(['id' => 'UP-400', 'status' => 'pending'], 201),
        ]);
        $payment = $this->checkout($zone)->payment;

        $this->notify($payment, ['status' => 'pending'])->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->notify($payment, ['status' => 'failed'])->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->notify($payment, ['status' => 'success'])->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_live_mode_is_explicit_and_the_test_double_does_not_call_a_network(): void
    {
        [, $zone] = $this->shop();
        $this->configure('live');
        Http::fake([
            'https://unipay.test/payments' => Http::response(['transaction_id' => 'UP-LIVE', 'status' => 'pending'], 201),
        ]);

        $this->checkout($zone);

        Http::assertSent(fn ($request) => $request->url() === 'https://unipay.test/payments' && $request->hasHeader('X-UniPay-Mode', 'live'));
        $this->assertSame('live', app(UniPayGateway::class)->mode());
        config(['services.unipay.mode' => 'production']);
        $this->assertSame('test', app(UniPayGateway::class)->mode());
    }

    public function test_another_tenant_cannot_see_the_sale_and_secrets_stay_hidden(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message.' '.json_encode($event->context);
        });
        [$alice, $zone] = $this->shop();
        $this->configure();
        Http::fake([
            'https://unipay.test/payments' => Http::response([
                'transaction_id' => 'UP-500',
                'status' => 'pending',
                'message' => $this->key,
            ], 201),
        ]);
        $sale = $this->checkout($zone);

        $bob = Platform::entrepreneur('Bob Wifi', 'bob-unipay@example.com');
        $this->actingAs($bob)->get('/sales/'.$sale->id)->assertNotFound();
        $this->actingAs($bob)->get('/payments')->assertOk()->assertDontSee($sale->payment->internal_reference);
        app(TenantManager::class)->set($bob->tenant_id);
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, Voucher::query()->count());

        $admin = User::where('email', 'admin@limetewifi.local')->first();
        $this->actingAs($admin)->get('/admin/production-check')
            ->assertOk()
            ->assertSee('UniPay API key')
            ->assertSee('CONFIGURED')
            ->assertSee('UniPay mode')
            ->assertSee('TEST')
            ->assertSee('Webhook')
            ->assertSee('Le secret de signature est présent')
            ->assertSee('CONFIGURED')
            ->assertDontSee($this->key, false)
            ->assertDontSee($this->secret, false);

        $blob = AuditLog::withoutGlobalScope('tenant')->get()->toJson();
        $this->assertStringNotContainsString($this->key, $blob);
        $this->assertStringNotContainsString($this->secret, $blob);
        $journal = implode("\n", $logged);
        $this->assertStringContainsString('unipay.payment', $journal);
        $this->assertStringNotContainsString($this->key, $journal);
        $this->assertStringNotContainsString($this->secret, $journal);
        $this->assertNotSame($alice->id, $bob->id);
    }

    public function test_a_refund_is_recorded_only_when_unipay_confirms_it(): void
    {
        [, $zone] = $this->shop();
        $this->configure();
        $refundStatus = 'pending';
        Http::fake(function ($request) use (&$refundStatus) {
            if (str_ends_with($request->url(), '/refunds')) {
                return Http::response(['status' => $refundStatus], 200);
            }

            return Http::response(['transaction_id' => 'UP-600', 'status' => 'pending'], 201);
        });
        $payment = $this->checkout($zone)->payment;
        $this->notify($payment, ['status' => 'success'])->assertOk();

        app(UniPayGateway::class)->refundPayment($payment->fresh());
        $this->assertSame('success', $payment->fresh()->status);

        $refundStatus = 'refunded';
        app(UniPayGateway::class)->refundPayment($payment->fresh());
        $this->assertSame('refunded', $payment->fresh()->status);
    }

    public function test_the_production_page_shows_an_unconfigured_unipay_without_a_key(): void
    {
        Platform::seed();
        $admin = User::where('email', 'admin@limetewifi.local')->first();

        $this->actingAs($admin)->get('/admin/production-check')
            ->assertOk()
            ->assertSee('UniPay API key')
            ->assertSee('UniPay non configuré')
            ->assertSee('UniPay mode')
            ->assertSee('TEST')
            ->assertSee('Webhook')
            ->assertSee('UNIPAY_WEBHOOK_SECRET est vide')
            ->assertSee('NOT CONFIGURED')
            ->assertDontSee('LIVE');
    }

    private function shop(): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-unipay-'.str()->lower(str()->random(4)).'@example.com');
        $zone = Platform::zone($user, 'Limete');
        Platform::plan($user, $zone);

        return [$user, $zone];
    }

    private function configure(string $mode = 'test'): void
    {
        config([
            'services.unipay.key' => $this->key,
            'services.unipay.base_url' => 'https://unipay.test',
            'services.unipay.webhook_secret' => $this->secret,
            'services.unipay.mode' => $mode,
        ]);
    }

    private function checkout($zone): Sale
    {
        $plan = $zone->plans()->first();
        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'name' => 'Amina',
            'phone' => '+243810009000',
            'provider' => 'unipay',
        ])->assertRedirect();

        return Sale::withoutGlobalScope('tenant')->latest('id')->first()->load('payment');
    }

    private function statusBody(Payment $payment, string $status): array
    {
        return [
            'transaction_id' => $payment->provider_reference,
            'reference' => $payment->internal_reference,
            'status' => $status,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
        ];
    }

    private function notify(Payment $payment, array $overrides = [], ?string $signature = null)
    {
        $payload = array_merge([
            'reference' => $payment->internal_reference,
            'transaction_id' => $payment->provider_reference ?: 'UP-1',
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'status' => 'success',
        ], $overrides);
        $body = json_encode($payload);
        $signature ??= hash_hmac('sha256', $body, (string) config('services.unipay.webhook_secret'));

        return $this->call('POST', '/payments/unipay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_UNIPAY_SIGNATURE' => $signature,
        ], $body);
    }
}
