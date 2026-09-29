<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\VoucherGenerator;
use App\Support\QrCodes;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Platform;
use Tests\TestCase;

class VoucherBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_ticket_receives_its_own_credentials(): void
    {
        [$user, $zone, $plan] = $this->shop();

        $this->actingAs($user)->post('/vouchers', $this->payload($zone, $plan, 1))
            ->assertRedirect('/vouchers/generated');

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertNotNull($voucher);
        $this->assertSame('available', $voucher->status);
        $this->assertNull($voucher->expires_at);
        $this->assertSame($user->tenant_id, $voucher->tenant_id);
        $this->assertSame($zone->id, $voucher->wifi_zone_id);
        $this->assertMatchesRegularExpression('/^LW[A-HJ-NP-Z2-9]{8}$/', $voucher->username);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Za-km-np-z2-9]{10}$/', $voucher->password);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $voucher->public_token);
        $this->assertNotSame($voucher->password, $voucher->username);
        $this->assertNotSame($voucher->password, $voucher->getRawOriginal('password'));

        $page = $this->actingAs($user)->get('/vouchers/generated');
        $page->assertOk()->assertSee('Tickets générés')->assertSee($voucher->username);
        $page->assertDontSee($voucher->password, false);
    }

    public function test_ten_tickets_are_unique_and_listed_for_selection(): void
    {
        [$user, $zone, $plan] = $this->shop();

        $this->actingAs($user)->post('/vouchers', $this->payload($zone, $plan, 10, 'moderne', 6))
            ->assertRedirect('/vouchers/generated');

        $vouchers = Voucher::withoutGlobalScope('tenant')->get();
        $this->assertCount(10, $vouchers);
        $this->assertCount(10, $vouchers->pluck('username')->unique());
        $this->assertCount(10, $vouchers->pluck('public_token')->unique());
        $this->assertCount(10, $vouchers->pluck('password')->unique());
        $this->assertTrue($vouchers->every(fn (Voucher $voucher) => $voucher->expires_at === null));

        $first = $vouchers[0];
        $second = $vouchers[1];
        $left = QrCodes::svg(route('tickets.public', $first->public_token));
        $right = QrCodes::svg(route('tickets.public', $second->public_token));
        $this->assertNotSame($left, $right);
        $this->assertStringNotContainsString($first->password, $left);
        $this->assertStringNotContainsString($second->password, $right);

        $this->actingAs($user)->get('/vouchers/generated')
            ->assertOk()
            ->assertSee('Tout sélectionner')
            ->assertSee('Imprimer la sélection')
            ->assertSee('Télécharger PDF')
            ->assertSee('Télécharger tous')
            ->assertSee('Sélectionner')
            ->assertSee('Dupliquer la configuration')
            ->assertDontSee($first->password, false);
    }

    public function test_one_hundred_tickets_stay_inside_the_subscription_limit_and_stay_unique(): void
    {
        [$user, $zone, $plan] = $this->shop();
        app(TenantManager::class)->set($user->tenant_id);

        $created = app(VoucherGenerator::class)->create($zone, $plan, 100);
        $this->assertCount(100, $created);
        $usernames = array_map(fn (Voucher $voucher) => $voucher->username, $created);
        $tokens = array_map(fn (Voucher $voucher) => $voucher->public_token, $created);
        $passwords = array_map(fn (Voucher $voucher) => $voucher->password, $created);
        $this->assertCount(100, array_unique($usernames));
        $this->assertCount(100, array_unique($tokens));
        $this->assertCount(100, array_unique($passwords));

        $this->actingAs($user)->post('/vouchers', $this->payload($zone, $plan, 100))
            ->assertRedirect('/vouchers/generated');
        $this->assertSame(200, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_quantity_above_the_subscription_limit_is_refused_on_the_server(): void
    {
        [$user, $zone, $plan] = $this->shop();
        $saas = $user->tenant->currentSubscription->saasPlan;
        $features = $saas->features;
        $features['max_tickets_per_generation'] = 10;
        $saas->forceFill(['features' => $features])->save();

        $this->actingAs($user)->post('/vouchers', $this->payload($zone, $plan, 11))
            ->assertSessionHasErrors('count');
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());

        $features['vouchers'] = false;
        $saas->forceFill(['features' => $features])->save();
        $this->actingAs($user)->post('/vouchers', $this->payload($zone, $plan, 1))
            ->assertSessionHasErrors('count');
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_a_collision_regenerates_only_the_conflicting_ticket(): void
    {
        [$user, $zone, $plan] = $this->shop();
        app(TenantManager::class)->set($user->tenant_id);
        $existing = app(VoucherGenerator::class)->create($zone, $plan, 1)[0];

        $taken = $existing;
        $database = new class($taken) extends VoucherGenerator
        {
            public function __construct(private Voucher $taken) {}

            public int $calls = 0;

            protected function makeUsername(): string
            {
                $this->calls++;

                return $this->calls === 1
                    ? $this->taken->username
                    : 'LWABCD'.str_pad((string) $this->calls, 4, '0', STR_PAD_LEFT);
            }

            protected function makeToken(): string
            {
                return $this->calls === 1
                    ? $this->taken->public_token
                    : str_pad(dechex($this->calls), 40, 'b', STR_PAD_LEFT);
            }

            protected function makePassword(): string
            {
                return 'Pw'.$this->calls.'abcdef';
            }
        };

        $created = $database->create($zone->fresh(), $plan->fresh(), 1);
        $this->assertCount(1, $created);
        $this->assertNotSame($existing->username, $created[0]->username);
        $this->assertNotSame($existing->public_token, $created[0]->public_token);
        $this->assertGreaterThan(1, $database->calls);

        $passwords = new class extends VoucherGenerator
        {
            public int $calls = 0;

            protected function makeUsername(): string
            {
                $this->calls++;

                return 'LWUSER'.str_pad((string) $this->calls, 4, '0', STR_PAD_LEFT);
            }

            protected function makeToken(): string
            {
                return str_pad(dechex(100 + $this->calls), 40, 'c', STR_PAD_LEFT);
            }

            protected function makePassword(): string
            {
                return $this->calls < 3 ? 'SamePass12' : 'OtherPass'.$this->calls;
            }
        };

        $pair = $passwords->create($zone->fresh(), $plan->fresh(), 2);
        $this->assertCount(2, $pair);
        $this->assertNotSame($pair[0]->password, $pair[1]->password);
        $this->assertSame('SamePass12', $pair[0]->password);
        $this->assertGreaterThan(2, $passwords->calls);
        $this->assertSame(4, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_another_tenant_cannot_generate_or_print_these_tickets(): void
    {
        [$alice, $zone, $plan] = $this->shop('Alice Wifi', 'alice-batch@example.com');
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-batch@example.com');
        $foreignZone = Platform::zone($bob, 'Zone Bob');
        $foreignPlan = Platform::plan($bob, $foreignZone);
        app(TenantManager::class)->set($alice->tenant_id);
        $voucher = app(VoucherGenerator::class)->create($zone, $plan, 1)[0];
        app(TenantManager::class)->set($bob->tenant_id);
        $foreign = app(VoucherGenerator::class)->create($foreignZone, $foreignPlan, 1)[0];

        $this->actingAs($bob)->post('/vouchers', $this->payload($zone, $plan, 1))->assertNotFound();
        $this->actingAs($bob)->get('/vouchers/'.$voucher->id)->assertNotFound();
        $this->actingAs($bob)->post('/vouchers/print', [
            'ids' => [$voucher->id],
            'template' => 'moderne',
            'per_page' => 4,
        ])->assertNotFound();
        $this->actingAs($bob)->post('/vouchers/pdf-sheet', [
            'ids' => [$voucher->id],
            'template' => 'moderne',
            'per_page' => 4,
        ])->assertNotFound();

        $mixed = $this->actingAs($alice)->post('/vouchers/print', [
            'ids' => [$voucher->id, $foreign->id],
            'template' => 'moderne',
            'per_page' => 4,
        ]);
        $mixed->assertOk();
        $mixed->assertSee($voucher->username);
        $mixed->assertDontSee($foreign->username, false);
        $mixed->assertDontSee($foreign->password, false);
    }

    public function test_print_selection_uses_only_the_chosen_tickets_and_hides_the_dashboard(): void
    {
        [$user, $zone, $plan] = $this->shop();
        app(TenantManager::class)->set($user->tenant_id);
        $tickets = app(VoucherGenerator::class)->create($zone, $plan, 2);
        $zone->forceFill([
            'phone' => '+243810001111',
            'whatsapp' => '+243860392283',
            'location' => 'Kingabwa',
            'display_name' => 'Limete Kingabwa',
        ])->save();

        $html = $this->actingAs($user)->post('/vouchers/print', [
            'ids' => [$tickets[0]->id],
            'template' => 'classique',
            'per_page' => 4,
        ])->assertOk()->getContent();

        $this->assertStringContainsString($tickets[0]->username, $html);
        $this->assertStringContainsString($tickets[0]->password, $html);
        $this->assertStringNotContainsString($tickets[1]->username, $html);
        $this->assertStringNotContainsString($tickets[1]->password, $html);
        $this->assertStringContainsString('data-model="classique"', $html);
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('size: A4 portrait', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringContainsString('Limete Kingabwa', $html);
        $this->assertStringContainsString('Kingabwa', $html);
        $this->assertStringContainsString('+243810001111', $html);
        $this->assertStringNotContainsString('Sortir', $html);
        $this->assertStringNotContainsString('Tableau de bord', $html);
        $this->assertSame(1, substr_count($html, $tickets[0]->password));
    }

    public function test_each_print_model_keeps_distinct_credentials(): void
    {
        [$user, $zone, $plan] = $this->shop();
        app(TenantManager::class)->set($user->tenant_id);
        $tickets = app(VoucherGenerator::class)->create($zone, $plan, 2);

        foreach (['classique' => 4, 'moderne' => 6, 'compact' => 8, 'premium' => 4] as $template => $perPage) {
            $html = $this->actingAs($user)->post('/vouchers/print', [
                'ids' => [$tickets[0]->id, $tickets[1]->id],
                'template' => $template,
                'per_page' => $perPage,
            ])->assertOk()->getContent();

            $this->assertStringContainsString('data-model="'.$template.'"', $html);
            $this->assertStringContainsString('layout-'.$perPage, $html);
            $this->assertSame(1, substr_count($html, $tickets[0]->password));
            $this->assertSame(1, substr_count($html, $tickets[1]->password));
            $this->assertStringContainsString($tickets[0]->username, $html);
            $this->assertStringContainsString($tickets[1]->username, $html);
        }

        $mixed = $this->actingAs($user)->post('/vouchers/print', [
            'ids' => [$tickets[0]->id, $tickets[1]->id],
            'template' => 'classique',
            'per_page' => 6,
            'templates' => [$tickets[1]->id => 'premium'],
        ])->assertOk()->getContent();
        $this->assertStringContainsString('data-model="classique"', $mixed);
        $this->assertStringContainsString('data-model="premium"', $mixed);
        $this->assertSame(1, substr_count($mixed, $tickets[0]->password));
        $this->assertSame(1, substr_count($mixed, $tickets[1]->password));
    }

    public function test_pdf_contains_only_the_selected_tickets(): void
    {
        [$user, $zone, $plan] = $this->shop();
        app(TenantManager::class)->set($user->tenant_id);
        $tickets = app(VoucherGenerator::class)->create($zone, $plan, 2);

        $response = $this->actingAs($user)->post('/vouchers/pdf-sheet', [
            'ids' => [$tickets[0]->id],
            'template' => 'premium',
            'per_page' => 8,
        ]);
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $other = $this->actingAs($user)->post('/vouchers/pdf-sheet', [
            'ids' => [$tickets[1]->id],
            'template' => 'premium',
            'per_page' => 8,
        ])->assertOk();
        $this->assertNotSame($response->getContent(), $other->getContent());

        $built = app(\App\Services\TicketSheet::class)->pages(collect([$tickets[0]]), 'premium', 8);
        $html = view('vouchers.sheet', [
            'pages' => $built['pages'],
            'perPage' => $built['per_page'],
            'template' => $built['template'],
            'pdf' => true,
            'ids' => [$tickets[0]->id],
        ])->render();
        $this->assertStringContainsString('data-model="premium"', $html);
        $this->assertStringContainsString($tickets[0]->username, $html);
        $this->assertStringContainsString($tickets[0]->password, $html);
        $this->assertStringNotContainsString($tickets[1]->username, $html);
        $this->assertStringNotContainsString($tickets[1]->password, $html);
        $this->assertStringNotContainsString('Sortir', $html);
    }

    public function test_dashboard_and_ticket_list_expose_the_fast_actions(): void
    {
        [$user, $zone, $plan] = $this->shop();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Générer des tickets');
        $this->actingAs($user)->get('/vouchers/generate')
            ->assertOk()
            ->assertSee('WiFi Zone')
            ->assertSee('24 HEURES')
            ->assertSee('1 000 CDF')
            ->assertSee('Moderne')
            ->assertSee('6 tickets / page')
            ->assertSee('Générer les tickets');

        app(TenantManager::class)->set($user->tenant_id);
        app(VoucherGenerator::class)->create($zone, $plan, 1);

        $this->actingAs($user)->get('/vouchers')
            ->assertOk()
            ->assertSee('Générer')
            ->assertSee('Imprimer')
            ->assertSee('PDF')
            ->assertSee('Dupliquer la configuration')
            ->assertSee('Sélectionner')
            ->assertSee('Supprimer');
    }

    public function test_a_plan_from_another_zone_is_rejected(): void
    {
        [$user, $zone, $plan] = $this->shop();
        $other = Platform::zone($user, 'Autre zone');
        $foreignPlan = Platform::plan($user, $other);

        $this->actingAs($user)->post('/vouchers', $this->payload($zone, $foreignPlan, 1))
            ->assertSessionHasErrors('plan_id');
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertNotNull($plan->id);
    }

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\WifiZone, 2: \App\Models\Plan}
     */
    private function shop(string $company = 'Alice Wifi', string $email = 'alice-batch@example.com'): array
    {
        $user = Platform::entrepreneur($company, $email);
        $zone = Platform::zone($user, 'Limete Kingabwa');
        $plan = Platform::plan($user, $zone);

        return [$user, $zone, $plan];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload($zone, $plan, int $count, string $template = 'moderne', int $perPage = 6): array
    {
        return [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'count' => $count,
            'template' => $template,
            'per_page' => $perPage,
        ];
    }
}
