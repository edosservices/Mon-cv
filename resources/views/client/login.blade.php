@extends('layouts.client')
@section('heading', 'Connexion')
@section('content')
<form method="POST" action="{{ route('client.login') }}" data-wait class="client-card p-4">
    @csrf
    <h1 class="h4">Se connecter</h1>
    <label class="form-label mt-3" for="phone">Téléphone</label>
    <input class="form-control" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" required>
    <label class="form-label mt-3" for="password">Mot de passe</label>
    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
    <button class="btn client-btn w-100 mt-4">Se connecter</button>
    <div class="d-flex justify-content-between mt-3 small">
        <a href="{{ route('client.register') }}">Créer un compte</a>
        <a href="{{ route('client.forgot') }}">Mot de passe oublié</a>
    </div>
    <p class="mt-3 mb-0"><a href="{{ route('client.buy') }}">Acheter sans compte</a></p>
</form>
@endsection
