<?php

namespace Tests\Feature;

use App\Jobs\SyncHotspotUser;
use App\Models\CaptiveSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiSession;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\VoucherGenerator;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class GuestWifiPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_buy_without_an_account_or_a_login_redirect(): void
    {
        [$zone, $plan] = $this->shop();
        $users = \App\Models\User::count();

        $response = $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810001111',
            'name' => 'Awa',
            'provider' => 'manual',
            'amount' => '1',
            'currency' => 'USD',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('/login', $location);
        $this->assertStringNotContainsString('/register', $location);
        $this->assertGuest();
        $this->assertSame($users, \App\Models\User::count());

        $sale = Sale::withoutGlobalScope('tenant')->first();
        $this->assertSame('self', $sale->purchase_for);
        $this->assertSame('pending', $sale->status);
        $this->assertSame('+243810001111', $sale->beneficiary_phone);
        $this->assertSame('+243810001111', $sale->payer_phone);
        $this->assertSame('1000.00', number_format((float) $sale->total_amount, 2, '.', ''));
        $this->assertSame('CDF', $sale->currency);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        $this->get($location)->assertOk()->assertSee('Paiement en cours')->assertDontSee('PAYÉ');
    }

    public function test_a_buyer_can_pay_for_someone_else(): void
    {
        [$zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug.'/forfait/'.$plan->id, [
            'purchase_for' => 'other',
            'phone' => '+243822222222',
            'payer_phone' => '+243811111111',
        ])->assertRedirect('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement');

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->assertOk()
            ->assertSee('Achat sans compte')
            ->assertSee('+243822222222')
            ->assertSee('+243811111111')
            ->assertDontSee('/register');

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'purchase_for' => 'other',
            'phone' => '+243822222222',
            'payer_phone' => '+243811111111',
            'provider' => 'manual',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->first();
        $this->assertSame('other', $sale->purchase_for);
        $this->assertSame('+243822222222', $sale->beneficiary_phone);
        $this->assertSame('+243811111111', $sale->payer_phone);
        $this->assertSame('+243822222222', $sale->customer->phone);
        $this->assertNotSame('+243811111111', $sale->customer->phone);
    }

    public function test_buying_for_someone_else_requires_the_payer_phone(): void
    {
        [$zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'purchase_for' => 'other',
            'phone' => '+243822222222',
            'provider' => 'manual',
        ])->assertSessionHasErrors('payer_phone');

        $this->assertSame(0, Sale::withoutGlobalScope('tenant')->count());
    }

    public function test_a_foreign_plan_and_a_spoofed_price_do_not_change_the_sale(): void
    {
        [$zone, $plan] = $this->shop();
        $other = Platform::entrepreneur('Bob Wifi', 'bob-guest@example.com');
        $foreign = Platform::plan($other, Platform::zone($other, 'Ailleurs'));

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $foreign->id,
            'phone' => '+243810001111',
            'provider' => 'manual',
            'amount' => '1',
        ])->assertNotFound();

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810001111',
            'provider' => 'manual',
            'amount' => '1',
            'currency' => 'USD',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->first();
        $this->assertSame($plan->id, $sale->items()->first()->plan_id);
        $this->assertSame('1000.00', number_format((float) $sale->payment->amount, 2, '.', ''));
    }

    public function test_ikeepay_confirmation_creates_one_ticket_and_ignores_repeated_webhooks(): void
    {
        Bus::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.ikeepay.com/h2h-payin' => Http::response([
                'provider_reference' => 'IKP-GUEST-1',
                'status' => 'pending',
            ], 200),
        ]);
        config([
            'services.ikeepay.secret_key' => 'SECRET-IKEE-DO-NOT-LEAK',
            'services.ikeepay.base_url' => 'https://api.ikeepay.com',
            'ikeepay.countries' => ['CI' => ['ORANGE']],
        ]);
        [$zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+2250700000000',
            'email' => 'client@example.com',
            'provider' => 'ikeepay',
            'country' => 'CI',
            'operator' => 'ORANGE',
            'amount' => '5.00',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->with('payment')->first();
        $this->assertSame('pending', $sale->payment->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertDontSee('PAYÉ');

        $failed = $this->notice($sale->payment, 'failed', ['data' => ['amount' => 1]]);
        $this->postJson('/payments/ikeepay/webhook', $failed)->assertStatus(422);
        $this->assertSame('pending', $sale->payment->fresh()->status);
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());

        $this->postJson('/payments/ikeepay/webhook', $this->notice($sale->payment, 'pending'))->assertOk();
        $this->assertSame('pending', $sale->fresh()->status);

        $completed = $this->notice($sale->payment, 'completed');
        $this->postJson('/payments/ikeepay/webhook', $completed)->assertOk();
        $this->postJson('/payments/ikeepay/webhook', $completed)->assertOk();
        $this->postJson('/payments/ikeepay/webhook', $completed)->assertOk();

        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertSame('paid', $sale->fresh()->status);
        Bus::assertDispatchedTimes(SyncHotspotUser::class, 1);
        $this->get('/wifi/'.$zone->slug.'/commande/'.$sale->public_token)
            ->assertOk()
            ->assertSee('PAYÉ')
            ->assertSee('Se connecter')
            ->assertSee('Copier le ticket')
            ->assertSee('Partager le ticket');
    }

    public function test_a_failed_payment_never_opens_internet_or_a_ticket(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.ikeepay.com/h2h-payin' => Http::response([
                'provider_reference' => 'IKP-GUEST-2',
                'status' => 'failed',
            ], 200),
        ]);
        config([
            'services.ikeepay.secret_key' => 'SECRET-IKEE-DO-NOT-LEAK',
            'services.ikeepay.base_url' => 'https://api.ikeepay.com',
            'ikeepay.countries' => ['CI' => ['ORANGE']],
        ]);
        [$zone, $plan] = $this->shop();

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+2250700000000',
            'email' => 'client@example.com',
            'provider' => 'ikeepay',
            'country' => 'CI',
            'operator' => 'ORANGE',
        ])->assertRedirect();

        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertSame(0, WifiSession::withoutGlobalScope('tenant')->count());
    }

    public function test_the_portal_keeps_only_a_device_confirmed_by_the_zone_router(): void
    {
        [$zone, $plan, $router] = $this->shop(withRouter: true);
        $otherOwner = Platform::entrepreneur('Bob Wifi', 'bob-portal@example.com');
        $otherZone = Platform::zone($otherOwner, 'Kingabwa');
        $other = Platform::router($otherOwner, $otherZone);
        $router->update(['host' => '192.0.2.10', 'status' => 'online']);
        $other->update(['host' => '192.0.2.20', 'status' => 'online']);
        $fake = new FakeHotspotRouter(function (array $words) use (&$fake) {
            $host = $fake->commands[array_key_last($fake->commands)]['host'] ?? '';
            $line = implode(' ', $words);
            if ($host === '192.0.2.10' && str_contains($line, 'mac-address=AA:BB:CC:DD:EE:01')) {
                return [[
                    '!type' => '!re',
                    'mac-address' => 'AA:BB:CC:DD:EE:01',
                    'address' => '10.5.5.8',
                ]];
            }

            return [['!type' => '!done']];
        });
        $this->app->instance(HotspotRouter::class, $fake);

        $this->get('/wifi/'.$zone->slug.'/portail?mac=AA:BB:CC:DD:EE:01&ip=10.5.5.8&username=trial&server=hotspot1')
            ->assertRedirect('/wifi/'.$zone->slug);

        $this->assertSame(1, CaptiveSession::withoutGlobalScope('tenant')->count());
        $session = CaptiveSession::withoutGlobalScope('tenant')->first();
        $this->assertSame($router->id, $session->mikrotik_id);
        $this->assertNotNull($session->verified_at);

        $this->get('/wifi/'.$otherZone->slug.'/portail?mac=AA:BB:CC:DD:EE:01&ip=10.5.5.8')
            ->assertRedirect('/wifi/'.$otherZone->slug);
        $this->assertSame(1, CaptiveSession::withoutGlobalScope('tenant')->count());

        $this->get('/wifi/'.$zone->slug.'/portail?mac=AA:BB:CC:DD:EE:99&ip=10.5.5.9')
            ->assertRedirect('/wifi/'.$zone->slug);
        $this->assertSame(1, CaptiveSession::withoutGlobalScope('tenant')->count());

        $this->get('/wifi/'.$zone->slug)->assertOk();
    }

    public function test_a_confirmed_payment_activates_the_verified_device_on_mikrotik(): void
    {
        [$zone, $plan, $router] = $this->shop(withRouter: true);
        $plan->update(['mikrotik_profile' => '24H']);
        $router->update(['status' => 'online']);
        $fake = new FakeHotspotRouter(function (array $words) use (&$fake) {
            $line = implode(' ', $words);
            if (str_contains($line, '?name=')) {
                $name = substr($line, strpos($line, '?name=') + 6);

                return [['!type' => '!re', '.id' => '*1', 'name' => strtok($name, ' ')]];
            }

            return [['!type' => '!done']];
        });
        $this->app->instance(HotspotRouter::class, $fake);
        app(TenantManager::class)->set($zone->tenant_id);
        $captive = CaptiveSession::create([
            'wifi_zone_id' => $zone->id,
            'mikrotik_id' => $router->id,
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'ip_address' => '10.5.5.8',
            'hotspot_username' => 'trial',
            'verified_at' => now(),
        ]);
        app(TenantManager::class)->forget();

        $this->withSession(['captive.'.$zone->id => $captive->id])->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'phone' => '+243810001111',
            'provider' => 'manual',
        ])->assertRedirect();

        $sale = Sale::withoutGlobalScope('tenant')->with('payment')->first();
        $this->assertSame($captive->id, $sale->captive_session_id);
        $sale->payment->transitionTo(\App\Enums\PaymentStatus::Success);
        $sale->payment->save();
        app(TenantManager::class)->set($zone->tenant_id);
        app(\App\Services\SaleService::class)->confirm($sale);
        app(TenantManager::class)->forget();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('AA:BB:CC:DD:EE:01', $voucher->mac_address);
        $this->assertSame(1, WifiSession::withoutGlobalScope('tenant')->count());
        $bound = false;
        foreach ($fake->commands as $command) {
            if (in_array('=mac-address=AA:BB:CC:DD:EE:01', $command['words'], true)) {
                $bound = true;
            }
        }
        $this->assertTrue($bound);
    }

    public function test_an_active_plan_is_shown_without_opening_a_new_session(): void
    {
        [$zone, $plan] = $this->shop();
        app(TenantManager::class)->set($zone->tenant_id);
        $customer = Customer::create(['name' => 'Awa', 'phone' => '+243810001111']);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan, 1, true)[0];
        $voucher->forceFill(['customer_id' => $customer->id])->save();
        app(TenantManager::class)->forget();
        $before = Voucher::withoutGlobalScope('tenant')->count();

        $this->withSession(['shop_phone.'.$zone->id => '+243810001111'])
            ->get('/wifi/'.$zone->slug)
            ->assertOk()
            ->assertSee('Connexion active')
            ->assertSee('24 HEURES')
            ->assertSee('Temps restant');

        $this->assertSame($before, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertSame(0, WifiSession::withoutGlobalScope('tenant')->count());
    }

    /**
     * @return array{0: \App\Models\WifiZone, 1: \App\Models\Plan, 2?: \App\Models\Mikrotik}
     */
    private function shop(bool $withRouter = false): array
    {
        $owner = Platform::entrepreneur('Alice Wifi', 'alice-guest-'.str()->lower(str()->random(6)).'@example.com');
        $zone = Platform::zone($owner, 'Limete');
        $plan = Platform::plan($owner, $zone);
        if (! $withRouter) {
            return [$zone, $plan];
        }

        return [$zone, $plan, Platform::router($owner, $zone)];
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
                'provider_reference' => $payment->provider_reference ?: 'IKP-GUEST-1',
                'amount' => 1000,
                'currency' => 'CDF',
                'country' => 'CI',
                'operator' => 'ORANGE',
                'status' => $status,
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }
}
