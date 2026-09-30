@extends('layouts.guest')
@section('title', 'Inscription')
@section('content')
<form method="POST" action="{{ route('register') }}" class="lm-card space-y-3 p-6" data-steps data-loader>
    @csrf
    <h1 class="text-xl font-semibold">Créer mon entreprise</h1>
    <ol class="lm-steps" aria-label="Étapes d’inscription">
        <li data-step-dot="1">1. Informations</li>
        <li data-step-dot="2">2. Business</li>
        <li data-step-dot="3">3. Confirmation</li>
    </ol>
    <section data-step="1">
        <label class="lm-field">Nom complet<input name="name" value="{{ old('name') }}" required></label>
        <label class="lm-field">Téléphone<input name="phone" value="{{ old('phone') }}" required></label>
        <label class="lm-field">Email<input type="email" name="email" value="{{ old('email') }}" required></label>
        <label class="lm-field">Mot de passe
            <span class="lm-password">
                <input type="password" name="password" required>
                <button type="button" data-password-toggle aria-label="Afficher le mot de passe">Afficher</button>
            </span>
        </label>
        <label class="lm-field">Confirmation<input type="password" name="password_confirmation" required></label>
        <button class="lm-btn primary mt-3" type="button" data-step-next>Continuer</button>
    </section>
    <section data-step="2">
        <label class="lm-field">Nom de l’entreprise<input name="company" value="{{ old('company') }}" required></label>
        <label class="lm-field">Adresse<input name="address" value="{{ old('address') }}"></label>
        <label class="lm-field">Ville<input name="city" value="{{ old('city') }}" required></label>
        <label class="lm-field">Pays<input name="country" value="{{ old('country') }}" required></label>
        <div class="mt-3 flex gap-2">
            <button class="lm-btn" type="button" data-step-prev>Retour</button>
            <button class="lm-btn primary" type="button" data-step-next>Continuer</button>
        </div>
    </section>
    <section data-step="3">
        <p>Vérifiez avant de créer le compte.</p>
        <p>Nom : <strong data-step-summary="name"></strong></p>
        <p>Email : <strong data-step-summary="email"></strong></p>
        <p>Entreprise : <strong data-step-summary="company"></strong></p>
        <div class="mt-3 flex gap-2">
            <button class="lm-btn" type="button" data-step-prev>Retour</button>
            <button class="lm-btn primary">Créer le compte</button>
        </div>
    </section>
</form>
@endsection
