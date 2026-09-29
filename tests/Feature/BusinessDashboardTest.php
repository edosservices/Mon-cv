<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WifiSession;
use App\Services\Payments\ManualGateway;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class BusinessDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_entrepreneur_sees_the_dashboard_and_a_client_does_not(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-biz@example.com');
        Platform::zone($user, 'Limete');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Bonjour, '.$user->name)
            ->assertSee('CA aujourd’hui')
            ->assertSee('Ventes aujourd’hui')
            ->assertSee('Tickets actifs')
            ->assertSee('width=device-width', false)
            ->assertDontSee('vs hier');

        $client = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Client->value)->first()->id,
            'name' => 'Client',
            'email' => 'client-biz@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);

        $this->actingAs($client)->get('/dashboard')->assertForbidden();
    }

    public function test_revenue_ignores_unconfirmed_payments_and_deducts_refunds(): void
    {
        [$user, $zone] = $this->workspace();
        $this->payment($user, $zone, 1800, PaymentStatus::Success);
        $this->payment($user, $zone, 640, PaymentStatus::Pending);
        $this->payment($user, $zone, 275, PaymentStatus::Failed);
        $this->payment($user, $zone, 90, PaymentStatus::Cancelled);
        $refunded = $this->payment($user, $zone, 300, PaymentStatus::Success);
        app(TenantManager::class)->set($user->tenant_id);
        app(ManualGateway::class)->refundPayment($refunded);

        $report = $this->actingAs($user)->get('/dashboard')->assertOk()->viewData('report');
        $kpis = collect($report['kpis'])->keyBy('label');

        $this->assertSame(1800.0, (float) $kpis['CA aujourd’hui']['value']);
        $this->assertSame(1, (int) $kpis['Ventes aujourd’hui']['value']);
        $this->assertNull($kpis['CA aujourd’hui']['change']);

        $pending = collect($report['payments'])->firstWhere('status', 'pending');
        $this->assertSame(1, $pending['total']);
        $this->assertSame(640.0, (float) $pending['amount']);
        $this->assertSame(0, collect($report['payments'])->firstWhere('status', 'failed')['total'] === 1 ? 0 : 1);
        $this->assertSame(1, collect($report['payments'])->firstWhere('status', 'failed')['total']);
        $this->assertSame(1, collect($report['payments'])->firstWhere('status', 'cancelled')['total']);
        $this->assertSame(1, collect($report['payments'])->firstWhere('status', 'refunded')['total']);
    }

    public function test_today_is_compared_with_yesterday_only_when_yesterday_has_revenue(): void
    {
        [$user, $zone] = $this->workspace();
        $this->payment($user, $zone, 900, PaymentStatus::Success, now()->subDay());
        $this->payment($user, $zone, 1800, PaymentStatus::Success);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('100 % vs hier');
    }

    public function test_period_and_zone_filters_do_not_cross_tenants(): void
    {
        [$alice, $zone] = $this->workspace();
        $other = Platform::zone($alice, 'Kingabwa');
        $sale = $this->payment($alice, $zone, 1800, PaymentStatus::Success, now()->subDay())->payable;
        $username = $sale->items()->first()->voucher->username;

        $this->actingAs($alice)->get('/dashboard?period=today')
            ->assertOk()
            ->assertViewHas('report', fn ($report) => (float) $report['period_revenue'] === 0.0);

        $this->actingAs($alice)->get('/dashboard?period=yesterday')
            ->assertOk()
            ->assertViewHas('report', fn ($report) => (float) $report['period_revenue'] === 1800.0)
            ->assertSee($username);

        $this->actingAs($alice)->get('/dashboard?period=yesterday&zone='.$other->id)
            ->assertOk()
            ->assertDontSee($username)
            ->assertViewHas('report', fn ($report) => (float) $report['period_revenue'] === 0.0);

        $bob = Platform::entrepreneur('Bob Wifi', 'bob-biz@example.com');
        $foreign = Platform::zone($bob, 'Kasa');

        $foreignSaleId = $this->payment($bob, $foreign, 5000, PaymentStatus::Success)->payable_id;
        $this->actingAs($alice)->get('/dashboard?zone='.$foreign->id)->assertNotFound();
        $this->actingAs($alice)->get('/sales/'.$foreignSaleId)->assertNotFound();
        $this->actingAs($alice)->get('/exports/sales.csv?period=yesterday')
            ->assertOk();

        $csv = $this->actingAs($alice)->get('/exports/sales.csv?period=yesterday')->streamedContent();
        $this->assertStringContainsString($username, $csv);
        $this->assertStringNotContainsString('5000', $csv);

        Sanctum::actingAs($alice);
        $sales = $this->getJson('/api/v1/sales')->assertOk()->json('data');
        $ids = collect($sales)->pluck('id');
        $this->assertTrue($ids->contains($sale->id));
        $this->assertFalse($ids->contains((int) $foreignSaleId));

        $starter = Platform::entrepreneur('Starter Wifi', 'starter-biz@example.com');
        Sanctum::actingAs($starter);
        $this->getJson('/api/v1/sales')->assertForbidden();
    }

    public function test_plans_clients_and_ticket_counts_come_from_the_database(): void
    {
        [$user, $zone, $plan] = $this->workspace(true);
        $active = $this->payment($user, $zone, 1800, PaymentStatus::Success);
        $voucher = $active->payable->items()->first()->voucher;
        $expired = Voucher::create([
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'public_token' => str()->random(40),
            'username' => 'LWEXP1',
            'password' => '1111',
            'status' => 'active',
            'activated_at' => now()->subDays(2),
            'expires_at' => now()->subHour(),
            'price_amount' => 1800,
            'currency' => 'CDF',
            'sync_status' => 'synced',
            'mikrotik_id' => null,
        ]);

        $customer = Customer::create(['name' => 'Amina', 'phone' => '+243810009001']);
        $voucher->forceFill(['customer_id' => $customer->id])->save();
        $this->payment($user, $zone, 1800, PaymentStatus::Success);
        Sale::query()->whereNull('customer_id')->update(['customer_id' => $customer->id]);

        WifiSession::create([
            'wifi_zone_id' => $zone->id,
            'username' => $voucher->username,
            'started_at' => now()->subMinutes(20),
        ]);

        $report = $this->actingAs($user)->get('/dashboard')->assertOk()->viewData('report');
        $kpis = collect($report['kpis'])->keyBy('label');
        $this->assertSame(2, (int) $kpis['Tickets actifs']['value']);
        $row = collect($report['plans'])->firstWhere('name', '24 HEURES');
        $this->assertSame(2, $row['sold']);
        $this->assertSame(2, $row['active']);
        $this->assertSame(1, $row['expired']);
        $this->assertSame(1, $report['customers']['repeat']);
        $this->assertSame('+243810009001', $report['customers']['rows'][0]['phone'] ?? $report['customers']['rows']->first()['phone']);
        $this->assertNotNull($expired->id);
    }

    public function test_connected_users_use_the_router_and_keep_commercial_expiry(): void
    {
        [$user, $zone, $plan] = $this->workspace(true);
        $payment = $this->payment($user, $zone, 1800, PaymentStatus::Success);
        $voucher = $payment->payable->items()->first()->voucher;
        $expires = $voucher->expires_at->copy();
        $router = Platform::router($user, $zone);
        $router->update(['status' => 'online', 'identity' => 'LIMETE-ROUTER', 'last_seen_at' => now()]);

        $this->app->instance(\App\Services\Mikrotik\HotspotRouter::class, new FakeHotspotRouter(function (array $words) use ($voucher) {
            if ($words[0] === '/ip/hotspot/active/print') {
                return [['!type' => '!re', 'user' => $voucher->username, 'address' => '10.5.50.8', 'uptime' => '12m'], ['!type' => '!done']];
            }

            return [['!type' => '!done']];
        }));

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee($voucher->username)
            ->assertSee('10.5.50.8')
            ->assertSee('LIMETE-ROUTER')
            ->assertSee($expires->timezone(config('app.timezone'))->format('d/m/Y H:i'))
            ->assertDontSee('secret-router', false);

        $this->assertSame($expires->getTimestamp(), $voucher->fresh()->expires_at->getTimestamp());
    }

    public function test_an_offline_router_does_not_invent_connected_users(): void
    {
        [$user, $zone] = $this->workspace();
        $router = Platform::router($user, $zone);
        $router->update(['status' => 'offline', 'last_error' => 'Connection refused']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('MikroTik hors ligne')
            ->assertSee('Connection refused')
            ->assertSee('Tester la connexion')
            ->assertDontSee('10.5.50.8')
            ->assertDontSee('secret-router', false);

        $this->actingAs($user)->get('/dashboard');
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_an_unsynced_ticket_can_be_retried_from_the_dashboard(): void
    {
        [$user, $zone, $plan] = $this->workspace(true);
        $plan->update(['mikrotik_profile' => '24H']);
        Platform::router($user, $zone);
        $payment = $this->payment($user, $zone, 1800, PaymentStatus::Success);
        $voucher = $payment->payable->items()->first()->voucher;
        $voucher->forceFill(['sync_status' => 'failed', 'sync_error' => 'Connection refused', 'mikrotik_id' => null])->save();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Tickets nécessitant une synchronisation')
            ->assertSee('Connection refused')
            ->assertSee('Synchroniser');

        $this->app->instance(\App\Services\Mikrotik\HotspotRouter::class, new FakeHotspotRouter);
        $this->actingAs($user)->post('/vouchers/'.$voucher->id.'/sync')->assertRedirect();

        $this->assertSame('synced', $voucher->fresh()->sync_status);
        $this->assertNotNull($voucher->fresh()->mikrotik_id);
        $this->actingAs($user)->get('/dashboard')->assertSee('Aucun ticket en attente de synchronisation.');
    }

    public function test_sales_can_be_filtered_exported_and_do_not_leak_secrets(): void
    {
        config(['limete.payments.airtel_money.api_key' => 'super-secret-airtel']);
        [$user, $zone, $plan] = $this->workspace(true);
        $payment = $this->payment($user, $zone, 1800, PaymentStatus::Success);
        $username = $payment->payable->items()->first()->voucher->username;
        Customer::create(['name' => 'Amina', 'phone' => '+243810009111']);
        $payment->payable->forceFill(['customer_id' => Customer::first()->id])->save();

        $this->actingAs($user)->get('/sales?period=30d&q='.urlencode('+243810009111'))
            ->assertOk()
            ->assertSee($username)
            ->assertSee('Ventes récentes');

        $this->actingAs($user)->get('/sales?period=30d&plan='.$plan->id.'&payment=success')
            ->assertOk()
            ->assertSee($username);

        $this->actingAs($user)->get('/sales?period=30d&payment=failed')
            ->assertOk()
            ->assertDontSee($username);

        $csv = $this->actingAs($user)->get('/exports/sales.csv?period=30d')->assertOk()->streamedContent();
        $this->assertStringContainsString($username, $csv);
        $this->assertStringNotContainsString('super-secret-airtel', $csv);
        $this->assertStringNotContainsString('secret-router', $csv);

        $this->actingAs($user)->get('/exports/tickets.csv?period=30d')->assertOk()->streamedContent();
        $this->actingAs($user)->get('/exports/payments.csv?period=30d')->assertOk();
        $this->actingAs($user)->get('/exports/customers.csv?period=30d')->assertOk();
        $this->actingAs($user)->get('/exports/sales.pdf?period=30d')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_peak_hours_stay_empty_without_confirmed_sales(): void
    {
        [$user] = $this->workspace();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Pas assez de données');
    }

    private function workspace(bool $withPlan = false): array
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-biz-'.str()->lower(str()->random(4)).'@example.com', 'pro');
        $zone = Platform::zone($user, 'Limete');
        $plan = $withPlan ? Platform::plan($user, $zone) : null;

        return [$user, $zone, $plan];
    }

    private function payment($user, $zone, float $amount, PaymentStatus $status, $when = null): Payment
    {
        app(TenantManager::class)->set($user->tenant_id);
        $when ??= now();
        $plan = $zone->plans()->first() ?? Platform::plan($user, $zone);
        $voucher = null;

        if ($status === PaymentStatus::Success || $status === PaymentStatus::Refunded) {
            $voucher = Voucher::create([
                'wifi_zone_id' => $zone->id,
                'plan_id' => $plan->id,
                'public_token' => str()->random(40),
                'username' => 'LW'.str()->upper(str()->random(4)),
                'password' => '2468',
                'status' => 'active',
                'activated_at' => $when,
                'expires_at' => $when->copy()->addDay(),
                'price_amount' => $amount,
                'currency' => 'CDF',
                'sync_status' => 'pending',
            ]);
        }

        $sale = Sale::create([
            'wifi_zone_id' => $zone->id,
            'public_token' => str()->random(40),
            'total_amount' => $amount,
            'currency' => 'CDF',
            'status' => $status === PaymentStatus::Success ? 'paid' : 'pending',
            'channel' => 'counter',
            'created_at' => $when,
            'updated_at' => $when,
        ]);
        $sale->items()->create([
            'plan_id' => $plan->id,
            'voucher_id' => $voucher?->id,
            'amount' => $amount,
        ]);
        $payment = Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'amount' => $amount,
            'currency' => 'CDF',
            'provider' => 'manual',
            'internal_reference' => 'PAY-'.str()->upper(str()->random(8)),
            'status' => $status->value,
            'paid_at' => $status === PaymentStatus::Success ? $when : null,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
        $sale->forceFill([
            'payment_id' => $payment->id,
            'created_at' => $when,
            'updated_at' => $when,
        ])->save();
        $voucher?->forceFill(['created_at' => $when, 'updated_at' => $when])->save();

        return $payment->fresh();
    }
}
