<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\SaasPlan;
use App\Models\WifiZone;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Platform;
use Tests\TestCase;

class SubscriptionQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_refuses_a_zone_when_the_quota_is_reached_and_creates_nothing(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-quota@example.com');
        Platform::zone($user, 'Kingabwa');

        $this->actingAs($user)->from('/wifi-zones/create')->post('/wifi-zones', [
            'name' => 'Deuxième zone',
            'location' => 'Lingwala',
            'status' => 'active',
        ])->assertRedirect('/wifi-zones/create')
            ->assertSessionHasErrors('subscription_limit_reached');

        $this->assertSame(1, $this->zones($user->tenant_id));
        $this->assertDatabaseMissing('wifi_zones', ['name' => 'Deuxième zone']);
    }

    public function test_starter_allows_a_zone_while_the_quota_is_available(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-open@example.com');

        $this->actingAs($user)->get('/wifi-zones/create')
            ->assertOk()
            ->assertDontSee('zoneUpgradeModal', false);

        $this->actingAs($user)->post('/wifi-zones', [
            'name' => 'Kingabwa',
            'location' => 'Marché',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertSame(1, $this->zones($user->tenant_id));
    }

    public function test_a_normal_validation_error_stays_on_the_name_field(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-valid@example.com');

        $this->actingAs($user)->from('/wifi-zones/create')->post('/wifi-zones', [
            'location' => 'Marché',
            'status' => 'active',
        ])->assertRedirect('/wifi-zones/create')
            ->assertSessionHasErrors('name')
            ->assertSessionDoesntHaveErrors('subscription_limit_reached');

        $this->assertSame(0, $this->zones($user->tenant_id));
    }

    public function test_the_limit_response_opens_the_real_plans_in_the_upgrade_modal(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-modal@example.com');
        Platform::zone($user, 'Kingabwa');
        $starter = SaasPlan::where('code', 'starter')->firstOrFail();
        $business = SaasPlan::where('code', 'business')->firstOrFail();
        $business->update(['price' => 15000, 'currency' => 'CDF', 'interval_days' => 30]);
        SaasPlan::create([
            'code' => 'premium',
            'name' => 'PREMIUM',
            'price' => 25000,
            'currency' => 'CDF',
            'interval_days' => 30,
            'max_zones' => 10,
            'max_mikrotiks' => 10,
            'features' => ['vouchers' => true, 'api' => true],
            'is_active' => true,
        ]);
        SaasPlan::create([
            'code' => 'archive',
            'name' => 'ARCHIVE',
            'price' => 1000,
            'currency' => 'CDF',
            'interval_days' => 30,
            'max_zones' => 50,
            'max_mikrotiks' => 50,
            'features' => ['vouchers' => true],
            'is_active' => false,
        ]);

        $modal = $this->actingAs($user)->get('/wifi-zones/create');
        $modal->assertOk()
            ->assertSee('Votre abonnement STARTER a atteint sa limite de WiFi Zones.', false)
            ->assertSee('Votre abonnement actuel a atteint sa limite de WiFi Zones.', false)
            ->assertSee('Pour créer davantage de zones et développer votre activité, passez à une formule supérieure.', false)
            ->assertSee('Choisissez la formule adaptée à votre activité et débloquez davantage de fonctionnalités.', false)
            ->assertSee('Passez à une formule supérieure pour débloquer davantage de zones et profiter de nouvelles fonctionnalités.', false)
            ->assertSee('aria-labelledby="zoneUpgradeTitle"', false)
            ->assertSee('aria-describedby="zoneUpgradeText"', false)
            ->assertSee('Abonnement actuel', false)
            ->assertSee('ACTUEL', false)
            ->assertSee('PREMIUM', false)
            ->assertSee('15 000 FC / mois', false)
            ->assertSee('25 000 FC / mois', false)
            ->assertSee('8.9 $ / mois', false)
            ->assertSee('Passer à cette formule', false)
            ->assertSee(route('subscription.show', ['plan' => $business->id]).'#paiement', false)
            ->assertDontSee('ARCHIVE', false)
            ->assertDontSee('Paiement réussi', false);

        $this->assertStringNotContainsString(
            route('subscription.show', ['plan' => $starter->id]).'#paiement',
            $modal->getContent()
        );

        $this->actingAs($user)->get('/wifi-zones')
            ->assertOk()
            ->assertSee('data-bs-target="#zoneUpgradeModal"', false);
    }

    public function test_the_subscription_page_shows_the_current_monthly_plan_and_real_usage(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-page@example.com');
        Platform::zone($user, 'Kingabwa');
        $subscription = $user->tenant->currentSubscription;
        $subscription->update([
            'starts_at' => '2026-10-04 08:00:00',
            'ends_at' => now()->timezone(config('app.timezone'))->startOfDay()->addDays(12)->addHours(8),
        ]);
        SaasPlan::where('code', 'business')->update(['price' => 15000, 'currency' => 'CDF', 'interval_days' => 30]);

        $this->actingAs($user)->get('/subscription')
            ->assertOk()
            ->assertSee('Votre abonnement', false)
            ->assertSee('STARTER', false)
            ->assertSee('0.00 $ / mois', false)
            ->assertSee('Période d’essai', false)
            ->assertSee('Essai', false)
            ->assertSee('04/10/2026', false)
            ->assertSee('Valable jusqu’au', false)
            ->assertSee('Expire dans 12 jours', false)
            ->assertSee('Votre utilisation', false)
            ->assertSee('WiFi Zones', false)
            ->assertSee('1 / 1', false)
            ->assertSee('Développez votre activité', false)
            ->assertSee('Passez à une formule supérieure pour bénéficier de limites plus élevées et de nouvelles fonctionnalités.', false)
            ->assertSee('BUSINESS', false)
            ->assertSee('PRO', false)
            ->assertSee('15 000 FC / mois', false)
            ->assertSee('8.9 $ / mois', false)
            ->assertSee('Passer à cette formule', false)
            ->assertSee(route('subscription.checkout'), false)
            ->assertDontSee('Paiement réussi', false)
            ->assertDontSee('60 / 500', false);
    }

    public function test_checkout_stays_pending_until_a_real_confirmation_then_the_new_quota_applies(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-pay@example.com');
        Platform::zone($user, 'Kingabwa');
        $subscription = $user->tenant->currentSubscription;
        $starterId = $subscription->saas_plan_id;
        $business = SaasPlan::where('code', 'business')->firstOrFail();
        $business->update(['price' => 15000, 'currency' => 'CDF', 'interval_days' => 30]);

        $this->actingAs($user)->post('/subscription', [
            'saas_plan_id' => $business->id,
            'provider' => 'manual',
            'transaction_reference' => 'REF-MENSUEL-1',
        ])->assertRedirect(route('subscription.show'))
            ->assertSessionHas('status');

        $subscription->refresh();
        $this->assertSame($starterId, $subscription->saas_plan_id);
        $this->assertSame(SubscriptionStatus::Trial->value, $subscription->status);

        $payment = Payment::withoutGlobalScope('tenant')->where('transaction_reference', 'REF-MENSUEL-1')->firstOrFail();
        $this->assertSame(PaymentStatus::Pending->value, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertSame($business->id, $payment->metadata['saas_plan_id']);

        $this->actingAs($user)->get('/subscription')
            ->assertSee('Il sera confirmé après réception.', false)
            ->assertDontSee('Paiement réussi', false);

        $this->actingAs($user)->post('/wifi-zones', [
            'name' => 'Avant paiement',
            'status' => 'active',
        ])->assertSessionHasErrors('subscription_limit_reached');
        $this->assertDatabaseMissing('wifi_zones', ['name' => 'Avant paiement']);

        $confirmed = app(SubscriptionService::class)->confirm($payment->fresh());
        $this->assertSame($business->id, $confirmed->saas_plan_id);
        $this->assertSame(SubscriptionStatus::Active->value, $confirmed->status);
        $this->assertSame(PaymentStatus::Success->value, $payment->fresh()->status);
        $this->assertTrue($confirmed->ends_at->greaterThan(now()->addDays(27)));

        $this->actingAs($user->fresh())->post('/wifi-zones', [
            'name' => 'Après confirmation',
            'status' => 'active',
        ])->assertRedirect(route('wifi-zones.index'));

        $this->assertSame(2, $this->zones($user->tenant_id));
    }

    public function test_a_higher_numeric_quota_blocks_again_once_it_is_filled(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-cap@example.com');
        Platform::zone($user, 'Kingabwa');
        $plus = SaasPlan::create([
            'code' => 'plus',
            'name' => 'PLUS',
            'price' => 8000,
            'currency' => 'CDF',
            'interval_days' => 30,
            'max_zones' => 2,
            'max_mikrotiks' => 2,
            'features' => ['vouchers' => true],
            'is_active' => true,
        ]);
        $user->tenant->currentSubscription->update(['saas_plan_id' => $plus->id]);

        $this->actingAs($user)->post('/wifi-zones', [
            'name' => 'Deuxième',
            'status' => 'active',
        ])->assertRedirect();

        $this->actingAs($user)->from('/wifi-zones/create')->post('/wifi-zones', [
            'name' => 'Troisième',
            'status' => 'active',
        ])->assertSessionHasErrors('subscription_limit_reached');

        $this->assertSame(2, $this->zones($user->tenant_id));
        $this->assertDatabaseMissing('wifi_zones', ['name' => 'Troisième']);
    }

    public function test_an_expired_subscription_offers_the_existing_monthly_renewal(): void
    {
        $user = Platform::entrepreneur('Alice Wifi', 'alice-expired@example.com');
        $user->tenant->currentSubscription->update([
            'status' => SubscriptionStatus::Expired->value,
            'starts_at' => '2026-08-04 08:00:00',
            'ends_at' => '2026-09-04 08:00:00',
        ]);

        $this->actingAs($user)->get('/subscription')
            ->assertOk()
            ->assertSee('Votre abonnement a expiré.', false)
            ->assertSee('Renouvelez votre abonnement pour continuer à utiliser toutes les fonctionnalités.', false)
            ->assertSee('Renouveler mon abonnement', false)
            ->assertSee('04/09/2026', false)
            ->assertSee('4.9 $ / mois', false)
            ->assertSee('8.9 $ / mois', false)
            ->assertSee(route('subscription.checkout'), false)
            ->assertDontSee('Paiement réussi', false);
    }

    public function test_an_entrepreneur_cannot_see_or_change_another_subscription(): void
    {
        $alice = Platform::entrepreneur('Alice Wifi', 'alice-iso@example.com');
        $bob = Platform::entrepreneur('Bob Wifi', 'bob-iso@example.com');
        $bob->tenant->currentSubscription->update([
            'ends_at' => '2031-01-15 10:00:00',
            'starts_at' => '2030-12-15 10:00:00',
        ]);
        $bobPlan = $bob->tenant->currentSubscription->saas_plan_id;
        $business = SaasPlan::where('code', 'business')->firstOrFail();

        $this->actingAs($alice)->get('/subscription')
            ->assertOk()
            ->assertDontSee('15/01/2031', false)
            ->assertDontSee('Bob Wifi', false);

        $this->actingAs($alice)->post('/subscription', [
            'saas_plan_id' => $business->id,
            'provider' => 'manual',
            'transaction_reference' => 'REF-ALICE',
        ])->assertRedirect();

        $this->assertSame($bobPlan, $bob->tenant->currentSubscription->fresh()->saas_plan_id);
        $this->assertSame('2031-01-15', $bob->tenant->currentSubscription->fresh()->ends_at->timezone(config('app.timezone'))->format('Y-m-d'));
        $this->assertSame(0, Payment::withoutGlobalScope('tenant')->where('tenant_id', $bob->tenant_id)->count());
        $this->assertSame(1, Payment::withoutGlobalScope('tenant')->where('tenant_id', $alice->tenant_id)->count());
    }

    private function zones(int $tenantId): int
    {
        return WifiZone::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->count();
    }
}
