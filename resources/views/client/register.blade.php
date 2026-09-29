@extends('layouts.client')
@section('heading', 'Créer un compte')
@section('content')
<form method="POST" action="{{ route('client.register') }}" data-wait class="client-card p-4">
    @csrf
    <h1 class="h4">Créer un compte</h1>
    <label class="form-label mt-3" for="phone">Téléphone</label>
    <input class="form-control" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="+243 812 345 678" required>
    <label class="form-label mt-3" for="password">Mot de passe</label>
    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
    <label class="form-label mt-3" for="password_confirmation">Confirmer le mot de passe</label>
    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    <label class="form-label mt-3" for="name">Nom <span class="text-secondary">(facultatif)</span></label>
    <input class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="120">
    <label class="form-label mt-3" for="email">Email <span class="text-secondary">(facultatif)</span></label>
    <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" maxlength="160">
    <button class="btn client-btn w-100 mt-4">Créer mon compte</button>
    <p class="mt-3 mb-0"><a href="{{ route('client.login') }}">J’ai déjà un compte</a></p>
    <p class="mb-0"><a href="{{ route('client.buy') }}">Acheter sans compte</a></p>
</form>
@endsection
