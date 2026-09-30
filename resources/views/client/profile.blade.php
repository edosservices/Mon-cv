@extends('layouts.client')
@section('heading', 'Mon profil')
@section('content')
<form method="POST" action="{{ route('client.profile.update') }}" data-wait class="client-card p-4">
    @csrf
    @method('PUT')
    <h1 class="h4">Mon profil</h1>
    <label class="form-label mt-3" for="phone">Téléphone</label>
    <input class="form-control" id="phone" name="phone" type="tel" value="{{ old('phone', $user->client_phone) }}" required>
    <label class="form-label mt-3" for="phone_confirmation">Confirmer le numéro</label>
    <input class="form-control" id="phone_confirmation" name="phone_confirmation" type="tel" value="{{ old('phone_confirmation') }}">
    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" name="confirm_phone" value="1" id="confirm_phone">
        <label class="form-check-label" for="confirm_phone">Je confirme le changement de numéro</label>
    </div>
    <label class="form-label mt-3" for="name">Nom</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $user->name === 'Client' ? '' : $user->name) }}">
    <label class="form-label mt-3" for="email">Email</label>
    <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user->email) }}">
    <label class="form-label mt-3" for="current_password">Mot de passe actuel</label>
    <input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password">
    <label class="form-label mt-3" for="password">Nouveau mot de passe</label>
    <input class="form-control" id="password" type="password" name="password" autocomplete="new-password">
    <label class="form-label mt-3" for="password_confirmation">Confirmer le mot de passe</label>
    <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
    <button class="btn client-btn w-100 mt-4">Enregistrer</button>
</form>
@endsection
