<?php

namespace App\Http\Controllers;

use App\Services\ClientPortal;
use App\Support\PhoneNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ClientPortalController extends Controller
{
    public function loginForm()
    {
        return view('client.login');
    }

    public function login(Request $request, ClientPortal $portal)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        $user = $portal->find($data['phone']);

        if (! $user || $user->status !== 'active' || ! Auth::attempt(['client_phone' => $user->client_phone, 'password' => $data['password']])) {
            return back()->withErrors(['phone' => 'Téléphone ou mot de passe incorrect.'])->onlyInput('phone');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('client.dashboard'));
    }

    public function registerForm()
    {
        return view('client.register');
    }

    public function register(Request $request, ClientPortal $portal)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $portal->register($data['phone'], $data['password'], $data['name'] ?? null, $data['email'] ?? null);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard')->with('status', 'Compte créé.');
    }

    public function forgotForm()
    {
        return view('client.forgot');
    }

    public function forgot(Request $request, ClientPortal $portal)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $portal->requestReset($data['phone']);

        return back()->with('status', 'Si un compte existe pour ce numéro, un code sera envoyé par SMS.');
    }

    public function resetForm()
    {
        return view('client.reset');
    }

    public function reset(Request $request, ClientPortal $portal)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:12'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $portal->resetPassword($data['phone'], $data['code'], $data['password']);

        return redirect()->route('client.login')->with('status', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
    }

    public function buy(ClientPortal $portal)
    {
        return view('client.buy', ['zones' => $portal->zones()]);
    }

    public function dashboard(Request $request, ClientPortal $portal)
    {
        $user = $request->user();
        $tickets = $portal->tickets($user);

        $sales = $portal->sales($user);

        return view('client.dashboard', [
            'tickets' => $tickets,
            'active' => $tickets->where('status', 'active'),
            'available' => $tickets->where('status', 'available'),
            'expired' => $tickets->where('status', 'expired'),
            'sales' => $sales,
            'zones' => $portal->zones(),
            'lastSale' => $sales->first(),
        ]);
    }

    public function history(Request $request, ClientPortal $portal)
    {
        $tickets = $portal->tickets($request->user());

        return view('client.history', [
            'tickets' => $tickets,
            'sales' => $portal->sales($request->user(), 20),
        ]);
    }

    public function tickets(Request $request, ClientPortal $portal)
    {
        return view('client.tickets', [
            'tickets' => $portal->tickets($request->user()),
        ]);
    }

    public function profile(Request $request)
    {
        return view('client.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request, ClientPortal $portal)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:30'],
            'phone_confirmation' => ['nullable', 'string', 'max:30'],
            'current_password' => ['nullable', 'string', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'confirm_phone' => ['nullable', 'boolean'],
        ]);

        $normalized = PhoneNumbers::normalize($data['phone']);
        $confirmation = PhoneNumbers::normalize($request->input('phone_confirmation'));
        $phoneChanges = $normalized !== $user->client_phone;

        if ($phoneChanges && (! $request->boolean('confirm_phone') || $confirmation !== $normalized || ! $request->filled('current_password'))) {
            return back()->withErrors([
                'phone' => 'Confirmez le nouveau numéro et le mot de passe actuel avant de le changer.',
            ])->withInput();
        }

        if ($request->filled('password') && ! $request->filled('current_password')) {
            return back()->withErrors([
                'current_password' => 'Indiquez le mot de passe actuel.',
            ])->withInput();
        }

        if ($phoneChanges) {
            $portal->changePhone($user, $data['phone']);
        }

        $user->fill([
            'name' => filled($data['name'] ?? null) ? $data['name'] : 'Client',
            'email' => ($data['email'] ?? null) ?: null,
        ]);

        if ($request->filled('password')) {
            $user->password = $data['password'];
        }

        $user->save();

        return back()->with('status', 'Profil enregistré.');
    }
}
