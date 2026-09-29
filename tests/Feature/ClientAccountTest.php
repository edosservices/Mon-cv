<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\Sms\ArraySmsSender;
use App\Services\Sms\SmsSender;
use App\Services\VoucherGenerator;
use App\Support\PhoneNumbers;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Platform;
use Tests\TestCase;

class ClientAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_registers_with_a_phone_and_logs_in(): void
    {
        Platform::seed();

        $this->get('/client/register')
            ->assertOk()
            ->assertSee('Téléphone')
            ->assertSee('Créer un compte')
            ->assertSee('Acheter sans compte')
            ->assertSee('name="_token"', false);

        $this->post('/client/register', [
            'phone' => '0812000111',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ])->assertRedirect(route('client.dashboard'));

        $user = User::where('client_phone', '+243812000111')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->tenant_id);
        $this->assertNull($user->email);
        $this->assertSame('client', $user->roleSlug());
        $this->assertNotSame('secret-ok', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('secret-ok', $user->password));

        $this->post('/logout');
        $this->post('/client/register', [
            'phone' => '+243812000111',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest();

        $this->get('/client/login')->assertOk()->assertSee('Mot de passe oublié');
        $before = session()->getId();
        $this->post('/client/login', [
            'phone' => '+243 812 000 111',
            'password' => 'secret-ok',
        ])->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());

        $this->post('/logout')->assertRedirect(route('client.login'));
        $this->assertGuest();

        $this->post('/client/login', [
            'phone' => '+243812000111',
            'password' => 'mauvais-pass',
        ])->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_login_is_throttled(): void
    {
        Platform::seed();
        $this->post('/client/register', [
            'phone' => '+243813000222',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ]);
        $this->post('/logout');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/client/login', [
                'phone' => '+243813000222',
                'password' => 'mauvais-pass',
            ])->assertSessionHasErrors('phone');
        }

        $this->post('/client/login', [
            'phone' => '+243813000222',
            'password' => 'mauvais-pass',
        ])->assertStatus(429);
    }

    public function test_a_client_sees_only_their_tickets_and_not_the_business(): void
    {
        $owner = Platform::entrepreneur('Alice Wifi', 'alice-client@example.com');
        $zone = Platform::zone($owner, 'Limete');
        $plan = Platform::plan($owner, $zone);
        app(TenantManager::class)->set($owner->tenant_id);
        $customer = Customer::create(['name' => 'Awa', 'phone' => '+243814000333']);
        $otherCustomer = Customer::create(['name' => 'Bola', 'phone' => '+243815000444']);
        $generator = app(VoucherGenerator::class);
        $own = $generator->create($zone, $plan)[0];
        $foreign = $generator->create($zone, $plan)[0];
        $own->forceFill(['customer_id' => $customer->id, 'status' => 'active', 'activated_at' => now(), 'expires_at' => now()->addDay()])->save();
        $foreign->forceFill(['customer_id' => $otherCustomer->id, 'status' => 'active', 'activated_at' => now(), 'expires_at' => now()->addDay()])->save();
        app(TenantManager::class)->forget();

        $this->post('/client/register', [
            'phone' => '0814000333',
            'name' => 'Awa',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ])->assertRedirect(route('client.dashboard'));

        $this->get('/client/dashboard')
            ->assertOk()
            ->assertSee('Awa')
            ->assertSee($own->username)
            ->assertSee('Limete')
            ->assertDontSee($foreign->username);

        $this->get('/client/tickets')
            ->assertOk()
            ->assertSee($own->username)
            ->assertSee('Ticket')
            ->assertSee('PDF')
            ->assertSee('<svg', false)
            ->assertDontSee($own->password)
            ->assertDontSee($foreign->username);

        $this->get('/dashboard')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->get('/customers')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/mikrotiks')->assertForbidden();

        $this->actingAs($owner)->get('/client/dashboard')->assertForbidden();
        $this->actingAs($owner)->get('/customers')->assertOk()->assertSee('Awa')->assertSee('Bola');
    }

    public function test_public_purchase_does_not_require_an_account(): void
    {
        $owner = Platform::entrepreneur('Alice Wifi', 'alice-guest-buy@example.com');
        $zone = Platform::zone($owner, 'Limete');
        $plan = Platform::plan($owner, $zone);
        $users = User::count();

        $this->get('/client/acheter')->assertOk()->assertSee('Acheter sans compte')->assertSee('Limete');

        $this->post('/wifi/'.$zone->slug.'/forfait/'.$plan->id, [
            'name' => 'Passant',
        ])->assertRedirect('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement');

        $this->get('/wifi/'.$zone->slug.'/forfait/'.$plan->id.'/paiement')
            ->assertOk()
            ->assertSee('Achat sans compte');

        $this->post('/wifi/'.$zone->slug, [
            'plan_id' => $plan->id,
            'name' => 'Passant',
            'provider' => 'airtel_money',
        ])->assertRedirect();

        $this->assertSame($users, User::count());
        $this->assertGuest();
        $this->assertDatabaseHas('sales', ['status' => 'pending', 'channel' => 'public']);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_password_reset_uses_a_code_and_not_a_url(): void
    {
        Platform::seed();
        $this->post('/client/register', [
            'phone' => '+243816000555',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ]);
        $this->post('/logout');

        $this->post('/client/forgot', ['phone' => '+243800000000'])
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame(0, \App\Models\PhonePasswordReset::count());

        $this->post('/client/forgot', ['phone' => '0816000555'])->assertSessionHas('status');
        $sms = app(SmsSender::class);
        $this->assertInstanceOf(ArraySmsSender::class, $sms);
        $this->assertCount(1, $sms->sent);
        preg_match('/(\d{6})/', $sms->sent[0]['message'], $match);
        $code = $match[1];

        $this->post('/client/reset', [
            'phone' => '+243816000555',
            'code' => '000000',
            'password' => 'secret-new',
            'password_confirmation' => 'secret-new',
        ])->assertSessionHasErrors('code');

        $response = $this->post('/client/reset', [
            'phone' => '+243816000555',
            'code' => $code,
            'password' => 'secret-new',
            'password_confirmation' => 'secret-new',
        ]);
        $response->assertRedirect(route('client.login'));
        $this->assertStringNotContainsString('secret-new', (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString($code, (string) $response->headers->get('Location'));

        $user = User::where('client_phone', '+243816000555')->first();
        $this->assertTrue(Hash::check('secret-new', $user->password));
        $this->assertFalse(Hash::check('secret-ok', $user->password));
    }

    public function test_profile_password_and_phone_changes_are_confirmed(): void
    {
        Platform::seed();
        $this->post('/client/register', [
            'phone' => '+243817000666',
            'password' => 'secret-ok',
            'password_confirmation' => 'secret-ok',
        ]);

        $this->put('/client/profile', [
            'phone' => '+243817000666',
            'name' => 'Mado',
            'password' => 'secret-next',
            'password_confirmation' => 'secret-next',
            'current_password' => 'secret-ok',
        ])->assertRedirect();

        $user = User::where('client_phone', '+243817000666')->first();
        $this->assertSame('Mado', $user->name);
        $this->assertTrue(Hash::check('secret-next', $user->password));
        $this->assertNotSame('secret-next', $user->getRawOriginal('password'));

        $this->put('/client/profile', [
            'phone' => '+243818000777',
            'current_password' => 'secret-next',
        ])->assertSessionHasErrors('phone');
        $this->assertSame('+243817000666', $user->fresh()->client_phone);

        $this->put('/client/profile', [
            'phone' => '+243818000777',
            'phone_confirmation' => '+243818000777',
            'confirm_phone' => '1',
            'current_password' => 'secret-next',
        ])->assertRedirect();
        $this->assertSame('+243818000777', $user->fresh()->client_phone);
        $this->assertTrue(PhoneNumbers::matches('0818000777', $user->fresh()->client_phone));
    }
}
