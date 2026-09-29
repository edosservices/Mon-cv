@extends('layouts.guest')
@section('title', 'Connexion')
@section('content')
<form method="POST" action="{{ route('login') }}" class="lm-card space-y-4 p-6" data-loader>
    @csrf
    <h1 class="text-xl font-semibold">Connexion</h1>
    <label class="lm-field">Email
        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
    </label>
    <label class="lm-field">Mot de passe
        <span class="lm-password">
            <input type="password" name="password" required autocomplete="current-password">
            <button type="button" data-password-toggle aria-label="Afficher le mot de passe">Afficher</button>
        </span>
    </label>
    <button class="lm-btn primary w-full">Entrer</button>
    <a class="block text-center text-sm text-electric" href="{{ route('register') }}">Créer une entreprise</a>
</form>
@endsection
