@extends('layouts.client')
@section('heading', 'Nouveau mot de passe')
@section('content')
<form method="POST" action="{{ route('client.reset') }}" data-wait class="client-card p-4">
    @csrf
    <h1 class="h4">Nouveau mot de passe</h1>
    <label class="form-label mt-3" for="phone">Téléphone</label>
    <input class="form-control" id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required>
    <label class="form-label mt-3" for="code">Code reçu</label>
    <input class="form-control" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required>
    <label class="form-label mt-3" for="password">Nouveau mot de passe</label>
    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
    <label class="form-label mt-3" for="password_confirmation">Confirmer</label>
    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    <button class="btn client-btn w-100 mt-4">Enregistrer</button>
</form>
@endsection
