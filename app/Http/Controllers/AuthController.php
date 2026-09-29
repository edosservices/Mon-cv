<?php

namespace App\Http\Controllers;

use App\Services\TenantRegistrar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if ($user->status !== 'active' || ($user->tenant && ($user->tenant->trashed() || $user->tenant->isSuspended()))) {
            Auth::logout();

            return back()->withErrors(['email' => 'Ce compte est suspendu.']);
        }

        return redirect()->intended($user->isSuperAdmin() ? route('admin.dashboard') : route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request, TenantRegistrar $registrar)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
        ]);

        $user = $registrar->register($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        $client = $request->user()?->isClient();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($client ? 'client.login' : 'login');
    }
}
