<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\HotspotRouter;
use App\Services\TicketAssist;
use App\Services\VoucherGenerator;
use App\Support\QrCodes;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\FakeHotspotRouter;
use Tests\Support\Platform;
use Tests\TestCase;

class TicketAssistTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_durations_backed_by_a_plan_are_offered(): void
    {
        [$user, $zone] = $this->shop(false);
        app(TenantManager::class)->set($user->tenant_id);
        $this->plan($user, $zone, '15 MINUTES', 900);
        $this->plan($user, $zone, '24 HEURES', 86400);
        $this->plan($user, $zone, '2 JOURS BIS', 172800);
        $this->plan($user, $zone, 'HORS CATALOGUE', 2700);

        $choices = app(TicketAssist::class)->durations($zone);
        $seconds = array_column($choices, 'seconds');

        $this->assertSame([900, 86400, 172800], $seconds);
        $this->assertSame(['15 minutes', '1 jour', '2 jours'], array_column($choices, 'label'));

        $this->actingAs($user)->get('/vouchers/generate')
            ->assertOk()
            ->assertSee('15 minutes')
            ->assertSee('1 jour')
            ->assertSee('2 jours')
            ->assertSee('Générer des tickets')
            ->assertSee('Générer les tickets')
            ->assertDontSee('45 minutes');
    }

    public function test_parameters_are_derived_once_from_the_selected_duration(): void
    {
        [$user, $zone, $plan] = $this->shop(false);
        $assist = app(TicketAssist::class);
        $params = $assist->parameters($plan, 5, 10);

        $this->assertSame('2JOURS', $params['profile']);
        $this->assertSame('2d', $params['time_limit']);
        $this->assertSame('2 jours', $params['duration_label']);
        $this->assertSame('10 GB', $params['data_label']);
        $this->assertSame('10G', $params['data_router']);
        $this->assertSame(10 * 1073741824, $params['data_bytes']);
        $this->assertSame('5M/5M', $params['rate_limit']);
        $this->assertSame('5M', $params['rate_short']);

        $quarter = $this->plan($user, $zone, '15 MINUTES', 900);
        $short = $assist->parameters($quarter, 5, 0);
        $this->assertSame('15MIN', $short['profile']);
        $this->assertSame('15m', $short['time_limit']);
        $this->assertSame('15 minutes', $short['duration_label']);
        $this->assertNull($short['data_bytes']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Paramètres incompatibles.');
        $assist->parameters($plan, 0, 3);
    }

    public function test_an_existing_profile_is_reused_and_the_ticket_is_created_once(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake, $state] = $this->bindRouter(
            ['2JOURS' => ['session-timeout' => '2d', 'rate-limit' => '5M']],
            ['deja' => $this->account('654321', '2JOURS', '2026-09-01 10:00:00')],
        );
        $lines = $this->captureLogs();

        $preview = $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan));
        $preview->assertOk()
            ->assertSee('Profil existant trouvé')
            ->assertSee('2JOURS')
            ->assertSee('2d')
            ->assertSee('10G')
            ->assertSee('5M')
            ->assertSee('deja')
            ->assertSee('Actif')
            ->assertSee('2026-09-01 10:00:00')
            ->assertSee('enh')
            ->assertSee('432')
            ->assertSee('Réutiliser ce profil')
            ->assertSee('Confirmer et créer')
            ->assertDontSee('654321', false)
            ->assertDontSee('secret-router', false);
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/profile/add'));

        $draft = 'draft-assist-existing-1';
        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, $draft, 'reuse'))
            ->assertRedirect();
        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, $draft, 'reuse'))
            ->assertRedirect();

        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertCount(1, $this->calls($fake, '/ip/hotspot/user/add'));
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/profile/add'));
        $added = implode(' ', $this->calls($fake, '/ip/hotspot/user/add')[0]['words']);
        $this->assertStringContainsString('=name=enh', $added);
        $this->assertStringContainsString('=profile=2JOURS', $added);
        $this->assertStringNotContainsString('limit-uptime', $added);
        $this->assertStringContainsString('=limit-bytes-total='.(10 * 1073741824), implode(' ', $this->calls($fake, '/ip/hotspot/user/set')[0]['words']));
        $this->assertSame('2d', $state->profiles['2JOURS']['session-timeout']);

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('synced', $voucher->sync_status);
        $this->assertSame('available', $voucher->status);
        $this->assertSame('enh', $voucher->username);
        $this->assertSame('432', $voucher->password);

        $url = route('tickets.public', $voucher->public_token);
        $page = $this->actingAs($user)->get('/vouchers/assist/'.$voucher->id);
        $page->assertOk()
            ->assertSee('Ticket créé')
            ->assertSee('enh')
            ->assertSee('432')
            ->assertSee('2JOURS')
            ->assertSee('2 jours')
            ->assertSee('10 GB')
            ->assertSee('5 Mbps')
            ->assertSee('Synchronisé')
            ->assertDontSee('Synchronisation en attente')
            ->assertDontSee('secret-router', false)
            ->assertSee('data-ticket-url="'.$url.'"', false);
        $svg = QrCodes::svg($url);
        $this->assertStringNotContainsString('432', $svg);
        $this->assertStringNotContainsString('secret-router', $svg);

        $dashboard = $this->actingAs($user)->get('/dashboard');
        $dashboard->assertOk()->assertSee('Tickets disponibles');
        $this->assertMatchesRegularExpression('/Tickets disponibles[\s\S]{0,180}data-count="1"/', $dashboard->getContent());

        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-existing-2', 'reuse'))
            ->assertSessionHas('warning', 'Cet utilisateur existe déjà.');
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertCount(1, $this->calls($fake, '/ip/hotspot/user/add'));
        $this->assertLogsHideSecrets($lines, ['secret-router', '654321', '432']);
    }

    public function test_a_missing_profile_is_created_without_overwriting_a_second_time(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake] = $this->bindRouter();

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan))
            ->assertOk()
            ->assertSee('Profil introuvable. Il peut être créé.')
            ->assertSee('Confirmer et créer');

        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-missing-1', 'create'))
            ->assertRedirect();

        $profiles = $this->calls($fake, '/ip/hotspot/user/profile/add');
        $this->assertCount(1, $profiles);
        $words = implode(' ', $profiles[0]['words']);
        $this->assertStringContainsString('=name=2JOURS', $words);
        $this->assertStringContainsString('=session-timeout=2d', $words);
        $this->assertStringContainsString('=rate-limit=5M/5M', $words);
        $this->assertCount(1, $this->calls($fake, '/ip/hotspot/user/add'));
        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('synced', $voucher->sync_status);
        $this->assertSame('2JOURS', $plan->fresh()->mikrotik_profile);
    }

    public function test_a_profile_with_different_parameters_is_not_overwritten(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake, $state] = $this->bindRouter([
            '2JOURS' => ['session-timeout' => '1d', 'rate-limit' => '1M/1M'],
        ]);

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan))
            ->assertOk()
            ->assertSee('Un profil portant ce nom existe déjà avec des paramètres différents.')
            ->assertSee('Réutiliser')
            ->assertSee('Choisir un autre nom')
            ->assertSee('Annuler')
            ->assertDontSee('Confirmer et créer');

        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-diff-1', 'create'))
            ->assertSessionHas('warning', 'Un profil portant ce nom existe déjà avec des paramètres différents.');
        $this->assertSame(0, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/profile/add'));
        $this->assertSame('1d', $state->profiles['2JOURS']['session-timeout']);

        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-diff-1', 'cancel'))
            ->assertSessionHas('warning', 'Création annulée.');

        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-diff-2', 'reuse'))
            ->assertRedirect();
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/profile/add'));
        $this->assertCount(1, $this->calls($fake, '/ip/hotspot/user/add'));
        $this->assertSame('1d', $state->profiles['2JOURS']['session-timeout']);
        $this->assertSame('1M/1M', $state->profiles['2JOURS']['rate-limit']);
    }

    public function test_another_profile_name_is_created_when_the_current_one_differs(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake, $state] = $this->bindRouter([
            '2JOURS' => ['session-timeout' => '1d', 'rate-limit' => '1M/1M'],
        ]);

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan, [
            'alternate_name' => '2JOURSALT',
            'profile_choice' => 'rename',
        ]))->assertOk()->assertSee('2JOURSALT')->assertSee('Profil introuvable. Il peut être créé.');

        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-rename-1', 'create', [
            'alternate_name' => '2JOURSALT',
        ]))->assertRedirect();

        $words = implode(' ', $this->calls($fake, '/ip/hotspot/user/profile/add')[0]['words']);
        $this->assertStringContainsString('=name=2JOURSALT', $words);
        $this->assertArrayNotHasKey('changed', $state->profiles);
        $this->assertSame('1d', $state->profiles['2JOURS']['session-timeout']);
        $this->assertSame('2d', $state->profiles['2JOURSALT']['session-timeout']);
        $added = implode(' ', $this->calls($fake, '/ip/hotspot/user/add')[0]['words']);
        $this->assertStringContainsString('=profile=2JOURSALT', $added);
    }

    public function test_an_existing_username_is_not_replaced_and_regeneration_finds_a_free_pair(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake] = $this->bindRouter([], [
            'enh' => $this->account('654321', '2JOURS', 'hier'),
        ]);

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan))
            ->assertOk()
            ->assertSee('Cet utilisateur existe déjà.')
            ->assertSee('Générer un autre identifiant')
            ->assertDontSee('654321', false);
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));

        $fresh = $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan, [
            'regenerate' => '1',
        ]));
        $fresh->assertOk()->assertDontSee('Cet utilisateur existe déjà.');
        $this->assertMatchesRegularExpression('/Utilisateur<\/dt><dd class="text-lg font-semibold">(?!enh)[a-z]{3}<\/dd>/', $fresh->getContent());
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));
    }

    public function test_a_password_already_used_is_refused(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake] = $this->bindRouter([], [
            'autre' => $this->account('432', '2JOURS', ''),
        ]);

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan, [
            'username' => 'enh',
        ]))->assertOk()
            ->assertSee('Ce mot de passe est déjà utilisé.')
            ->assertDontSee('Confirmer et créer');
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));
    }

    public function test_history_blocks_a_deleted_ticket_combination(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake] = $this->bindRouter();
        app(TenantManager::class)->set($user->tenant_id);
        app(VoucherGenerator::class)->issue($zone, $plan, 'enh', '432')->delete();

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan))
            ->assertOk()
            ->assertSee('Cet utilisateur existe déjà.');

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan, [
            'username' => 'libre',
            'password' => '432',
        ]))->assertOk()->assertSee('Ce mot de passe est déjà utilisé.');
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));
    }

    public function test_automatic_generation_proposes_a_short_free_combination(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake] = $this->bindRouter([], [
            'enh' => $this->account('432', '2JOURS', ''),
        ]);

        $page = $this->actingAs($user)->post('/vouchers/assist/preview', [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'mbps' => 5,
            'data_gb' => 10,
        ]);
        $page->assertOk()->assertSee('TICKET');
        $this->assertSame(1, preg_match(
            '/Utilisateur<\/dt><dd class="text-lg font-semibold">([a-z]{3})<\/dd>.*Mot de passe<\/dt><dd class="text-lg font-semibold">(\d{3,4})<\/dd>/s',
            $page->getContent(),
            $found,
        ));
        $this->assertNotSame('enh', $found[1]);
        $this->assertNotSame('432', $found[2]);
        $this->assertSame([], $this->calls($fake, '/ip/hotspot/user/add'));
    }

    public function test_generation_stops_after_the_attempt_limit(): void
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';
        $names = [];
        $length = strlen($letters);
        for ($left = 0; $left < $length; $left++) {
            for ($middle = 0; $middle < $length; $middle++) {
                for ($right = 0; $right < $length; $right++) {
                    $names[$letters[$left].$letters[$middle].$letters[$right]] = true;
                }
            }
        }

        $method = new ReflectionMethod(TicketAssist::class, 'generate');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Impossible de proposer un identifiant libre.');
        $method->invoke(app(TicketAssist::class), $names, []);
    }

    public function test_an_offline_router_keeps_the_ticket_and_waits_for_sync(): void
    {
        [$user, $zone, $plan] = $this->shop();
        $this->app->instance(HotspotRouter::class, new FakeHotspotRouter(new RuntimeException('Connection refused')));

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan))
            ->assertOk()
            ->assertSee('Routeur hors ligne.');

        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-offline-1', 'create'))
            ->assertRedirect();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertNotNull($voucher);
        $this->assertNotSame('synced', $voucher->sync_status);
        $this->assertStringNotContainsString('secret-router', (string) $voucher->sync_error);

        $this->actingAs($user)->get('/vouchers/assist/'.$voucher->id)
            ->assertOk()
            ->assertSee('Ticket créé')
            ->assertSee('Synchronisation en attente')
            ->assertSee('Réessayer')
            ->assertDontSee('secret-router', false);
    }

    public function test_a_zone_without_a_router_still_creates_the_local_ticket(): void
    {
        [$user, $zone, $plan] = $this->shop(false);

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan))
            ->assertOk()
            ->assertSee('Routeur hors ligne.');

        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-norouter-1', 'create'))
            ->assertRedirect();

        $voucher = Voucher::withoutGlobalScope('tenant')->first();
        $this->assertSame('pending', $voucher->sync_status);
        $this->actingAs($user)->get('/vouchers/assist/'.$voucher->id)
            ->assertSee('Synchronisation en attente')
            ->assertSee('Réessayer');
    }

    public function test_tenant_isolation_and_permissions_block_other_accounts(): void
    {
        [$user, $zone, $plan] = $this->shop();
        [$fake] = $this->bindRouter([
            '2JOURS' => ['session-timeout' => '2d', 'rate-limit' => '5M/5M'],
        ]);
        $this->actingAs($user)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-tenant-1', 'reuse'))
            ->assertRedirect();
        $voucher = Voucher::withoutGlobalScope('tenant')->first();

        $other = Platform::entrepreneur('Autre', 'autre-assist@example.com');
        $this->actingAs($other)->get('/vouchers/assist/'.$voucher->id)->assertNotFound();
        $this->actingAs($other)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan))->assertNotFound();

        $client = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Client->value)->first()->id,
            'name' => 'Client',
            'email' => 'client-assist@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);
        $this->actingAs($client)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan))->assertForbidden();

        $staff = User::create([
            'tenant_id' => $user->tenant_id,
            'role_id' => Role::where('slug', UserRole::Staff->value)->first()->id,
            'name' => 'Staff',
            'email' => 'staff-assist@example.com',
            'password' => 'password-ok',
            'status' => 'active',
        ]);
        $this->actingAs($staff)->post('/vouchers/assist', $this->storePayload($zone, $plan, 'draft-assist-staff-1', 'reuse'))
            ->assertForbidden();
        $staff->permissions()->attach(Permission::where('slug', 'vouchers.manage')->first());
        $this->actingAs($staff)->get('/vouchers/generate')->assertOk();
        $this->assertSame(1, Voucher::withoutGlobalScope('tenant')->count());
        $this->assertCount(1, $this->calls($fake, '/ip/hotspot/user/add'));
    }

    public function test_incompatible_input_stays_readable(): void
    {
        [$user, $zone, $plan] = $this->shop(false);
        app(TenantManager::class)->set($user->tenant_id);
        $odd = $this->plan($user, $zone, 'HORS CATALOGUE', 2700);

        $this->actingAs($user)->post('/vouchers/assist/preview', [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $odd->id,
        ])->assertSessionHas('warning', 'Paramètres incompatibles.');

        $this->actingAs($user)->post('/vouchers/assist/preview', $this->previewPayload($zone, $plan, [
            'data_gb' => 3,
        ]))->assertSessionHasErrors('data_gb');

        $assist = app(TicketAssist::class);
        $this->assertSame(
            'Un profil portant ce nom existe déjà avec des paramètres différents.',
            $assist->readable(new RuntimeException('Un profil porte déjà ce nom. Il n’est pas écrasé.')),
        );
        $this->assertSame('Erreur RouterOS', $assist->readable(new RuntimeException('invalid user name or password (secret-router)')));
        $this->assertSame('Routeur hors ligne.', $assist->readable(new RuntimeException('Connection refused')));
        $this->assertSame('Profil introuvable.', $assist->readable(new RuntimeException('Le profil RouterOS est introuvable sur ce MikroTik.')));
    }

    /**
     * @return array{0: User, 1: \App\Models\WifiZone, 2?: Plan, 3?: mixed}
     */
    private function shop(bool $withRouter = true): array
    {
        $user = Platform::entrepreneur('Limete Assist', 'assist-'.str()->lower(str()->random(6)).'@example.com');
        $zone = Platform::zone($user, 'Zone assist');
        $plan = Platform::plan($user, $zone);
        $plan->forceFill([
            'name' => '2 JOURS',
            'duration_seconds' => 172800,
            'mikrotik_profile' => null,
        ])->save();
        if ($withRouter) {
            Platform::router($user, $zone);
        }

        return [$user, $zone, $plan->fresh()];
    }

    private function plan(User $user, $zone, string $name, int $seconds): Plan
    {
        app(TenantManager::class)->set($user->tenant_id);

        return Plan::create([
            'wifi_zone_id' => $zone->id,
            'name' => $name,
            'duration_seconds' => $seconds,
            'price' => 1000,
            'currency' => 'CDF',
            'unlimited_data' => false,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, array<string, string>>  $profiles
     * @param  array<string, array<string, string>>  $users
     * @return array{0: FakeHotspotRouter, 1: object}
     */
    private function bindRouter(array $profiles = [], array $users = []): array
    {
        $state = (object) ['profiles' => $profiles, 'users' => $users, 'next' => 1];
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

            if ($path === '/ip/hotspot/user/profile/add') {
                $state->profiles[$fields['name']] = [
                    'session-timeout' => $fields['session-timeout'] ?? '',
                    'rate-limit' => $fields['rate-limit'] ?? '',
                ];

                return [['!type' => '!done']];
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
                        'disabled' => 'false',
                        'last-logged-out' => $user['activity'],
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
                    'activity' => '',
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
    private function account(string $password, string $profile, string $activity): array
    {
        return [
            'id' => '*9',
            'password' => $password,
            'profile' => $profile,
            'activity' => $activity,
        ];
    }

    private function previewPayload($zone, Plan $plan, array $extra = []): array
    {
        return $extra + [
            'wifi_zone_id' => $zone->id,
            'plan_id' => $plan->id,
            'mbps' => 5,
            'data_gb' => 10,
            'username' => 'enh',
            'password' => '432',
        ];
    }

    private function storePayload($zone, Plan $plan, string $draft, string $choice, array $extra = []): array
    {
        return $extra + $this->previewPayload($zone, $plan) + [
            'draft' => $draft,
            'confirm' => '1',
            'profile_choice' => $choice,
            'alternate_name' => '2JOURS',
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
