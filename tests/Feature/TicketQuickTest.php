<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MikrotikProfile;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\TicketQuick;
use App\Support\Money;
use App\Support\QrCodes;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class TicketQuickTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_comes_from_the_router_and_linked_plans(): void
    {
        [$user, $zone] = $this->shop();
        $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
            '15J' => ['session-timeout' => '15d', 'rate-limit' => '5M/5M'],
            '2Jours' => $this->profileRow('2d', '8M/8M'),
        ]);
        $this->plan($user, $zone, '2Jours', 172800, '2Jours', 2000);

        $page = $this->actingAs($user)->get('/vouchers/generate');
        $page->assertOk()
            ->assertSee('Générer depuis un profil')
            ->assertSee('Rechercher un profil...')
            ->assertSee('Rechercher...')
            ->assertSee('1Jours')
            ->assertSee('15J')
            ->assertSee('2Jours')
            ->assertSee('hotspot1')
            ->assertSee('hotspot2')
            ->assertSee('1 jour')
            ->assertSee('24 heures')
            ->assertSee('10M/10M')
            ->assertSee('1 000 FC')
            ->assertSee('2 000 FC')
            ->assertSee('Data limit')
            ->assertSee('Générer des tickets')
            ->assertSee('Générer les tickets')
            ->assertDontSee('1Heure', false)
            ->assertDontSee('30Jours', false)
            ->assertDontSee('secret-router', false);

        $this->assertStringNotContainsString('"name":"15min"', $page->getContent());
    }

    public function test_search_matches_the_profile_name_only(): void
    {
        [$user, $zone] = $this->shop();
        $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
            '15J' => ['session-timeout' => '15d'],
            '2Jours' => $this->profileRow('2d'),
            '30Jour' => ['session-timeout' => '30d'],
            'ZZ' => ['session-timeout' => '1d', 'rate-limit' => '1M/1M'],
        ]);
        $this->plan($user, $zone, '2Jours', 172800, '2Jours', 2000);
        $this->plan($user, $zone, 'ZZ', 86400, 'ZZ', 1000);
        app(TenantManager::class)->set($user->tenant_id);

        $profiles = app(TicketQuick::class)->catalog($zone)['profiles'];
        $found = array_column(app(TicketQuick::class)->search($profiles, '1'), 'name');

        $this->assertEqualsCanonicalizing(['1Jours', '15J'], $found);
        $this->assertNotContains('2Jours', $found);
        $this->assertNotContains('30Jour', $found);
        $this->assertNotContains('ZZ', $found);
        $this->assertContains('ZZ', array_column(app(TicketQuick::class)->search($profiles, 'z'), 'name'));
    }

    public function test_selecting_a_profile_fills_inherited_fields_without_creating_a_user(): void
    {
        [$user, $zone] = $this->shop();
        [$fake] = $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ], [
            'deja' => $this->account('router-secret-99', '1Jours'),
        ]);

        $preview = $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-preview-1',
            'price' => 1,
            'currency' => 'USD',
        ]));
        $preview->assertOk()
            ->assertSee('Aperçu du profil')
            ->assertSee('1Jours')
            ->assertSee('1d')
            ->assertSee('1 jour')
            ->assertSee('24 heures')
            ->assertSee('5 GB')
            ->assertSee('10M/10M')
            ->assertSee('1 000 FC')
            ->assertSee('Enable')
            ->assertSee('pool1')
            ->assertSee('parent1')
            ->assertSee('remove')
            ->assertSee('hotspot1')
            ->assertSee('Automatique depuis le profil')
            ->assertSee('enh2')
            ->assertSee('4321')
            ->assertSee('Confirmer la génération')
            ->assertDontSee('router-secret-99', false)
            ->assertDontSee('secret-router', false);
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/profile/add'));
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_a_different_router_time_limit_is_kept_visible(): void
    {
        [$user, $zone] = $this->shop();
        $this->bindRouter([
            '1Jours' => $this->profileRow('2d'),
        ]);

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-time-1',
        ]))
            ->assertOk()
            ->assertSee('2d')
            ->assertSee('1d')
            ->assertSee('1 jour')
            ->assertDontSee('24 heures');
    }

    public function test_currency_follows_the_plan(): void
    {
        [$user, $zone, $plan] = $this->shop(true, [
            'price' => 5,
            'currency' => 'USD',
        ]);
        $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        app(TenantManager::class)->set($user->tenant_id);

        $profile = app(TicketQuick::class)->catalog($zone)['profiles'][0];
        $this->assertSame('5 USD', Money::shop($profile['snapshot']['price_amount'], $profile['snapshot']['price_currency']));
        $this->assertSame('USD', $profile['snapshot']['price_currency']);
        $this->assertSame('USD', $profile['snapshot']['selling_price_currency']);
        $this->assertEquals(5, (float) $profile['snapshot']['selling_price_amount']);

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-usd-1',
        ]))
            ->assertOk()
            ->assertSee('5 USD')
            ->assertDontSee('FC');

        $this->assertNotSame('FC', $plan->currency);
    }

    public function test_data_limit_is_converted_and_written_on_the_user(): void
    {
        [$user, $zone] = $this->shop();
        [$fake] = $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        $quick = app(TicketQuick::class);
        $this->assertSame(5 * 1073741824, $quick->dataBytes(5, 'GB'));
        $this->assertSame(500 * 1048576, $quick->dataBytes(500, 'MB'));

        $draft = 'draft-quick-data-500';
        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => $draft,
            'data_value' => 500,
            'data_unit' => 'MB',
            'server' => 'all',
        ]))->assertOk()->assertSee('500 MB');

        $this->actingAs($user)->post('/vouchers/quick', $this->payload($zone, [
            'draft' => $draft,
            'data_value' => 500,
            'data_unit' => 'MB',
            'server' => 'all',
            'confirm' => '1',
        ]))->assertRedirect();

        $added = implode(' ', $this->calls($fake, '/ip/hotspot/user/add')[0]['words']);
        $this->assertStringContainsString('=profile=1Jours', $added);
        $this->assertStringNotContainsString('=server=', $added);
        $this->assertStringNotContainsString('limit-uptime', $added);
        $this->assertStringContainsString('=limit-bytes-total='.(500 * 1048576), implode(' ', $this->calls($fake, '/ip/hotspot/user/set')[0]['words']));
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/profile/add'));

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame(500 * 1048576, $voucher->profile_snapshot['data_bytes']);
        $this->assertSame('500 MB', $voucher->profile_snapshot['data_label']);
        $this->assertSame('synced', $voucher->sync_status);
    }

    public function test_a_forged_price_is_ignored_and_old_tickets_keep_their_snapshot(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake] = $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        $lines = $this->captureLogs();
        $draft = 'draft-quick-snapshot1';

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => $draft,
            'price' => 1,
            'price_currency' => 'USD',
            'selling_price_amount' => 1,
        ]))->assertOk()->assertSee('1 000 FC');

        $this->actingAs($user)->post('/vouchers/quick', $this->payload($zone, [
            'draft' => $draft,
            'price' => 1,
            'price_currency' => 'USD',
            'confirm' => '1',
        ]))->assertRedirect();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertEquals(1000, (float) $voucher->price_amount);
        $this->assertSame('CDF', $voucher->currency);
        $this->assertEquals(1000, (float) $voucher->profile_snapshot['price_amount']);
        $this->assertSame('CDF', $voucher->profile_snapshot['price_currency']);
        $this->assertEquals(1000, (float) $voucher->profile_snapshot['selling_price_amount']);
        $this->assertSame('1d', $voucher->profile_snapshot['validity']);
        $this->assertSame('24 heures', $voucher->profile_snapshot['time_label']);
        $this->assertSame('10M/10M', $voucher->profile_snapshot['rate_limit']);
        $this->assertSame('Enable', $voucher->profile_snapshot['lock_user']);
        $this->assertArrayNotHasKey('password', $voucher->profile_snapshot);

        $plan->forceFill(['price' => 1500, 'currency' => 'USD'])->save();
        $voucher->refresh();
        $this->assertEquals(1000, (float) $voucher->price_amount);
        $this->assertSame('CDF', $voucher->currency);
        $this->assertEquals(1000, (float) $voucher->profile_snapshot['price_amount']);
        $this->assertSame('CDF', $voucher->profile_snapshot['price_currency']);

        $url = route('tickets.public', $voucher->public_token);
        $page = $this->actingAs($user)->get('/vouchers/assist/'.$voucher->id);
        $page->assertOk()
            ->assertSee('Ticket créé')
            ->assertSee('1Jours')
            ->assertSee('1 jour')
            ->assertSee('5 GB')
            ->assertSee('10M/10M')
            ->assertSee('Synchronisé')
            ->assertSee('data-ticket-url="'.$url.'"', false)
            ->assertDontSee('secret-router', false);
        $this->assertStringNotContainsString($voucher->password, QrCodes::svg($url));
        $this->assertStringNotContainsString('secret-router', QrCodes::svg($url));
        $this->assertLogsHideSecrets($lines, ['secret-router', '4321']);
        $this->assertCount(1, $this->calls($fake, '/ip/hotspot/user/add'));
    }

    public function test_unknown_profiles_are_refused_before_creation(): void
    {
        [$user, $zone] = $this->shop();
        [$fake] = $this->bindRouter([
            '15J' => ['session-timeout' => '15d', 'rate-limit' => '5M/5M'],
        ]);

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-missing-1',
            'profile' => '1Jours',
        ]))->assertSessionHas('warning', 'Profil introuvable.');

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-unpriced-1',
            'profile' => '15J',
        ]))->assertSessionHas('warning', 'Ce profil n’a pas de forfait LIMETE.');

        $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-server-1',
            'server' => 'hotspot9',
        ]))->assertSessionHas('warning', 'Paramètres incompatibles.');

        $this->actingAs($user)->post('/vouchers/quick', $this->payload($zone, [
            'draft' => 'draft-quick-expired-1',
            'confirm' => '1',
        ]))->assertSessionHas('warning', 'Aperçu expiré.');

        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/profile/add'));
    }

    public function test_a_taken_username_is_warned_and_never_suggested(): void
    {
        [$user, $zone, $plan] = $this->shop();
        $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ], [
            'LM01' => $this->account('router-secret-99', '1Jours'),
        ]);
        app(TenantManager::class)->set($user->tenant_id);
        Voucher::create([
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'username' => 'LM99',
            'password' => '7777',
            'public_token' => str()->random(40),
            'status' => 'available',
            'price_amount' => 1000,
            'currency' => 'CDF',
        ])->delete();

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-taken-1',
            'username' => 'LM01',
            'password' => '4321',
        ]))->assertSessionHas('warning', 'Cet utilisateur existe déjà.');

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-taken-2',
            'username' => 'LM99',
            'password' => '4321',
        ]))->assertSessionHas('warning', 'Cet utilisateur existe déjà.');

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-taken-3',
            'username' => 'libre1',
            'password' => '7777',
        ]))->assertSessionHas('warning', 'Ce mot de passe est déjà utilisé.');

        $hint = $this->actingAs($user)->get('/vouchers/quick/username?wifi_zone_id='.$zone->id.'&q=LM01');
        $hint->assertOk();
        $payload = $hint->json();
        $this->assertTrue($payload['taken']);
        $this->assertSame('Nom déjà utilisé', $payload['message']);
        $this->assertNotContains('LM01', $payload['suggestions']);
        $this->assertNotContains('LM99', $payload['suggestions']);
        foreach ($payload['suggestions'] as $suggestion) {
            $this->assertMatchesRegularExpression('/^LM[A-Za-z0-9]{4}$/', $suggestion);
        }
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->withTrashed()->count());
    }

    public function test_generated_names_follow_the_prefix_and_stop_when_none_are_free(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake] = $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        $draft = 'draft-quick-batch-01';

        $preview = $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => $draft,
            'mode' => 'generate',
            'qty' => 3,
            'prefix' => 'LM',
            'length' => 4,
            'charset' => 'digits',
            'username' => '',
            'password' => '',
        ]));
        $preview->assertOk()
            ->assertSee('Générer 3 tickets')
            ->assertSee('3 tickets')
            ->assertDontSee('Mot de passe');
        $this->assertSame(1, preg_match('/Exemples : ([^<]+)/', $preview->getContent(), $found));
        $samples = array_map('trim', explode(',', html_entity_decode($found[1])));
        $this->assertCount(3, $samples);
        foreach ($samples as $sample) {
            $this->assertMatchesRegularExpression('/^LM[2-9]{4}$/', $sample);
        }
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));

        app(TenantManager::class)->set($user->tenant_id);
        $alphabet = '23456789';
        $rows = [];
        $now = now();
        $length = strlen($alphabet);
        for ($left = 0; $left < $length; $left++) {
            for ($middle = 0; $middle < $length; $middle++) {
                for ($right = 0; $right < $length; $right++) {
                    $rows[] = [
                        'tenant_id' => $user->tenant_id,
                        'wifi_zone_id' => $zone->id,
                        'plan_id' => $plan->id,
                        'username' => 'Z'.$alphabet[$left].$alphabet[$middle].$alphabet[$right],
                        'password' => 'x',
                        'public_token' => str()->random(48),
                        'status' => 'available',
                        'currency' => 'CDF',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }
        Voucher::insert($rows);
        $before = Voucher::withoutGlobalScope('tenant')->count();

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-exhaust1',
            'mode' => 'generate',
            'qty' => 1,
            'prefix' => 'Z',
            'length' => 3,
            'charset' => 'digits',
        ]))->assertSessionHas('warning', 'Impossible de proposer un identifiant libre.');
        $this->assertSame($before, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_confirmation_reuses_the_preview_and_a_second_post_does_not_duplicate(): void
    {
        [$user, $zone] = $this->shop();
        [$fake] = $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        $draft = 'draft-quick-idem-001';
        $input = $this->payload($zone, [
            'draft' => $draft,
            'mode' => 'generate',
            'qty' => 2,
            'prefix' => 'LM',
            'length' => 4,
            'charset' => 'upper',
            'server' => 'hotspot1',
            'username' => '',
            'password' => '',
            'confirm' => '1',
        ]);

        $this->actingAs($user)->post('/vouchers/quick/preview', $input)->assertOk();
        $this->actingAs($user)->post('/vouchers/quick', $input)->assertRedirect('/vouchers/generated');
        $this->actingAs($user)->post('/vouchers/quick', $input)->assertRedirect('/vouchers/generated');

        $this->assertSame(2, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertCount(2, $this->calls($fake, '/ip/hotspot/user/add'));
        foreach ($this->calls($fake, '/ip/hotspot/user/add') as $call) {
            $words = implode(' ', $call['words']);
            $this->assertStringContainsString('=server=hotspot1', $words);
            $this->assertStringContainsString('=profile=1Jours', $words);
            $this->assertMatchesRegularExpression('/=name=LM[A-Z]{4}/', $words);
        }

        $page = $this->actingAs($user)->get('/vouchers/generated');
        $page->assertOk()->assertDontSee('secret-router', false);
        foreach (Voucher::withoutGlobalScope('tenant')->get() as $voucher) {
            $page->assertDontSee($voucher->password, false);
            $this->assertSame('1Jours', $voucher->profile_snapshot['profile']);
        }

        $changed = $input;
        $changed['qty'] = 4;
        $this->actingAs($user)->post('/vouchers/quick', $changed)->assertSessionHas('warning', 'Aperçu expiré.');
        $this->assertSame(2, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_quantity_above_the_subscription_limit_is_refused(): void
    {
        [$user, $zone] = $this->shop();
        $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        $saas = $user->tenant->currentSubscription->saasPlan;
        $features = $saas->features;
        $features['max_tickets_per_generation'] = 10;
        $saas->forceFill(['features' => $features])->save();

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-limit-01',
            'mode' => 'generate',
            'qty' => 11,
        ]))->assertSessionHasErrors('count');
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_an_offline_router_keeps_the_local_ticket_and_its_snapshot(): void
    {
        [$user, $zone] = $this->shop();
        $router = $zone->mikrotiks()->first();
        $router->forceFill([
            'details' => ['hotspot_servers' => [['name' => 'hotspot1']]],
        ])->save();
        app(TenantManager::class)->set($user->tenant_id);
        MikrotikProfile::create([
            'mikrotik_id' => $router->id,
            'name' => '1Jours',
            'rate_limit' => '10M/10M',
            'shared_users' => 1,
            'raw' => [
                'session-timeout' => '1d',
                'rate-limit' => '10M/10M',
                'lock-user' => 'Enable',
            ],
        ]);
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter(new RuntimeException('Connection refused')));

        $this->actingAs($user)->get('/vouchers/generate')
            ->assertOk()
            ->assertSee('1Jours')
            ->assertSee('Routeur hors ligne.')
            ->assertSee('hotspot1')
            ->assertSee('1 000 FC');

        $draft = 'draft-quick-offline1';
        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => $draft,
        ]))->assertOk()->assertSee('Routeur hors ligne.')->assertSee('1 000 FC');

        $this->actingAs($user)->post('/vouchers/quick', $this->payload($zone, [
            'draft' => $draft,
            'confirm' => '1',
        ]))->assertRedirect();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertNotNull($voucher);
        $this->assertNotSame('synced', $voucher->sync_status);
        $this->assertEquals(1000, (float) $voucher->price_amount);
        $this->assertSame('1Jours', $voucher->profile_snapshot['profile']);
        $this->assertStringNotContainsString('secret-router', (string) $voucher->sync_error);

        $this->actingAs($user)->get('/vouchers/assist/'.$voucher->id)
            ->assertOk()
            ->assertSee('Synchronisation en attente')
            ->assertSee('Réessayer')
            ->assertDontSee('secret-router', false);
    }

    public function test_a_zone_without_a_router_still_creates_the_local_ticket(): void
    {
        [$user, $zone] = $this->shop(false);
        $draft = 'draft-quick-local-01';

        $this->actingAs($user)->get('/vouchers/generate')
            ->assertOk()
            ->assertSee('1Jours')
            ->assertSee('Routeur hors ligne.');

        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => $draft,
            'server' => 'all',
        ]))->assertOk();

        $this->actingAs($user)->post('/vouchers/quick', $this->payload($zone, [
            'draft' => $draft,
            'server' => 'all',
            'confirm' => '1',
        ]))->assertRedirect();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('pending', $voucher->sync_status);
        $this->assertSame('1Jours', $voucher->profile_snapshot['profile']);
    }

    public function test_the_last_profile_is_only_a_suggestion(): void
    {
        [$user, $zone] = $this->shop();
        $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        $draft = 'draft-quick-last-001';
        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, ['draft' => $draft]))->assertOk();
        $this->actingAs($user)->post('/vouchers/quick', $this->payload($zone, [
            'draft' => $draft,
            'confirm' => '1',
        ]))->assertRedirect();

        $this->actingAs($user)->get('/vouchers/generate')
            ->assertOk()
            ->assertSee('Utiliser 1Jours')
            ->assertSee('data-use-profile="1Jours"', false);
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
    }

    public function test_tenant_isolation_and_permissions_block_other_accounts(): void
    {
        [$user, $zone] = $this->shop();
        [$fake] = $this->bindRouter([
            '1Jours' => $this->profileRow('1d'),
        ]);
        $draft = 'draft-quick-tenant-1';
        $this->actingAs($user)->post('/vouchers/quick/preview', $this->payload($zone, ['draft' => $draft]))->assertOk();
        $this->actingAs($user)->post('/vouchers/quick', $this->payload($zone, [
            'draft' => $draft,
            'confirm' => '1',
        ]))->assertRedirect();
        $voucher = Voucher::withoutGlobalScope('tenant')->first();

        $other = Platform::entrepreneur('Autre', 'autre-quick@example.com');
        $this->actingAs($other)->get('/vouchers/assist/'.$voucher->id)->assertNotFound();
        $this->actingAs($other)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-tenant-2',
        ]))->assertNotFound();

        $client = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Client->value)->first()->id,
            'name' => 'Client',
            'email' => 'client-quick@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);
        $this->actingAs($client)->post('/vouchers/quick/preview', $this->payload($zone, [
            'draft' => 'draft-quick-tenant-3',
        ]))->assertForbidden();

        $staff = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Staff->value)->first()->id,
            'name' => 'Staff',
            'email' => 'staff-quick@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);
        $this->actingAs($staff)->post('/vouchers/quick', $this->payload($zone, [
            'draft' => 'draft-quick-tenant-4',
            'confirm' => '1',
        ]))->assertForbidden();
        $staff->permissions()->attach(Permission::where('slug', 'vouchers.manage')->first());
        $this->actingAs($staff)->get('/vouchers/generate')->assertOk();
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertCount(1, $this->calls($fake, '/ip/hotspot/user/add'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: \App\Models\WifiZone, 2: Plan}
     */
    private function shop(bool $withRouter = true, array $overrides = []): array
    {
        $user = Platform::entrepreneur('Limete Quick', 'quick-'.str()->lower(str()->random(6)).'@example.com');
        $zone = Platform::zone($user, 'Zone quick');
        app(TenantManager::class)->set($user->tenant_id);
        $plan = Plan::create([
            'wifi_zone_id' => $zone->id,
            'name' => '1Jours',
            'duration_seconds' => 86400,
            'price' => $overrides['price'] ?? 1000,
            'currency' => $overrides['currency'] ?? 'CDF',
            'unlimited_data' => false,
            'status' => 'active',
            'mikrotik_profile' => '1Jours',
        ]);
        if ($withRouter) {
            Platform::router($user, $zone);
        }

        return [$user, $zone, $plan->fresh()];
    }

    private function plan(User $user, $zone, string $name, int $seconds, string $profile, int $price): Plan
    {
        app(TenantManager::class)->set($user->tenant_id);

        return Plan::create([
            'wifi_zone_id' => $zone->id,
            'name' => $name,
            'duration_seconds' => $seconds,
            'price' => $price,
            'currency' => 'CDF',
            'unlimited_data' => false,
            'status' => 'active',
            'mikrotik_profile' => $profile,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function profileRow(string $timeout, string $rate = '10M/10M'): array
    {
        return [
            'session-timeout' => $timeout,
            'rate-limit' => $rate,
            'shared-users' => '1',
            'lock-user' => 'Enable',
            'address-pool' => 'pool1',
            'parent-queue' => 'parent1',
            'expired-mode' => 'remove',
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $profiles
     * @param  array<string, array<string, string>>  $users
     * @param  list<string>  $servers
     * @return array{0: FakeHotspotRouter, 1: object}
     */
    private function bindRouter(array $profiles = [], array $users = [], array $servers = ['hotspot1', 'hotspot2']): array
    {
        $state = (object) ['profiles' => $profiles, 'users' => $users, 'servers' => $servers, 'next' => 1];
        $fake = new FakeHotspotRouter(function (array $words) use ($state) {
            $path = $words[0] ?? '';
            $fields = [];
            foreach ($words as $word) {
                if (is_string($word) && str_starts_with($word, '=') && str_contains(substr($word, 1), '=')) {
                    [$key, $value] = explode('=', substr($word, 1), 2);
                    $fields[$key] = $value;
                }
            }

            if ($path === '/ip/hotspot/user/profile/print') {
                $rows = [];
                foreach ($state->profiles as $name => $profile) {
                    $rows[] = ['!type' => '!re', 'name' => $name] + $profile;
                }
                $rows[] = ['!type' => '!done'];

                return $rows;
            }

            if ($path === '/ip/hotspot/print') {
                $rows = [];
                foreach ($state->servers as $name) {
                    $rows[] = ['!type' => '!re', 'name' => $name];
                }
                $rows[] = ['!type' => '!done'];

                return $rows;
            }

            if ($path === '/ip/hotspot/user/print') {
                $wanted = null;
                foreach ($words as $word) {
                    if (is_string($word) && str_starts_with($word, '?name=')) {
                        $wanted = substr($word, 6);
                    }
                }
                $rows = [];
                foreach ($state->users as $name => $user) {
                    if ($wanted !== null && $wanted !== $name) {
                        continue;
                    }
                    $rows[] = [
                        '!type' => '!re',
                        '.id' => $user['id'],
                        'name' => $name,
                        'password' => $user['password'],
                        'profile' => $user['profile'],
                    ];
                }
                $rows[] = ['!type' => '!done'];

                return $rows;
            }

            if ($path === '/ip/hotspot/user/add') {
                $state->users[$fields['name']] = [
                    'id' => '*'.$state->next,
                    'password' => $fields['password'] ?? '',
                    'profile' => $fields['profile'] ?? '',
                ];
                $state->next++;

                return [['!type' => '!done']];
            }

            return [['!type' => '!done']];
        });
        $this->app->instance(HotspotRouter::class, $fake);

        return [$fake, $state];
    }

    /**
     * @return array<string, string>
     */
    private function account(string $password, string $profile): array
    {
        return [
            'id' => '*9',
            'password' => $password,
            'profile' => $profile,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload($zone, array $extra = []): array
    {
        return $extra + [
            'wifi_zone_id' => $zone->id,
            'profile' => '1Jours',
            'server' => 'hotspot1',
            'data_value' => 5,
            'data_unit' => 'GB',
            'mode' => 'add',
            'qty' => 1,
            'prefix' => 'LM',
            'length' => 4,
            'charset' => 'mixed',
            'username' => 'enh2',
            'password' => '4321',
            'draft' => 'draft-quick-default01',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function calls(FakeHotspotRouter $fake, string $path): array
    {
        return array_values(array_filter(
            $fake->commands,
            fn (array $command) => ($command['words'][0] ?? '') === $path,
        ));
    }

    private function captureLogs(): \ArrayObject
    {
        $lines = new \ArrayObject;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($lines) {
            $lines->append($event->message.' '.json_encode($event->context));
        });

        return $lines;
    }

    private function assertLogsHideSecrets(\ArrayObject $lines, array $secrets): void
    {
        foreach ($lines as $line) {
            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $line);
            }
        }
    }
}
