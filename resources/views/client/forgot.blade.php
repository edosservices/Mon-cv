@extends('layouts.client')
@section('heading', 'Mot de passe oublié')
@section('content')
<form method="POST" action="{{ route('client.forgot') }}" data-wait class="client-card p-4">
    @csrf
    <h1 class="h4">Mot de passe oublié</h1>
    <p class="text-secondary">Un code sera envoyé par SMS lorsque le service sera branché. Le nouveau mot de passe n’apparaît jamais dans l’adresse.</p>
    <label class="form-label" for="phone">Téléphone</label>
    <input class="form-control" id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required>
    <button class="btn client-btn w-100 mt-4">Recevoir un code</button>
    <p class="mt-3 mb-0"><a href="{{ route('client.reset') }}">J’ai déjà un code</a></p>
</form>
@endsection
