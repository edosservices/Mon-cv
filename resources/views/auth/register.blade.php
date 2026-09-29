@extends('layouts.guest')
@section('title', 'Inscription')
@section('content')
<form method="POST" action="{{ route('register') }}" class="lm-card space-y-3 p-6">
    @csrf
    <h1 class="text-xl font-semibold">Créer mon entreprise</h1>
    @foreach([
        'name' => 'Nom complet',
        'company' => 'Nom de l’entreprise',
        'phone' => 'Téléphone',
        'email' => 'Email',
        'address' => 'Adresse',
        'city' => 'Ville',
        'country' => 'Pays',
    ] as $field => $label)
        <label class="block text-sm">{{ $label }}
            <input class="mt-1 w-full rounded-lg border px-3 py-2" name="{{ $field }}" value="{{ old($field) }}" {{ in_array($field, ['address']) ? '' : 'required' }} {{ $field === 'email' ? 'type=email' : '' }}>
        </label>
    @endforeach
    <label class="block text-sm">Mot de passe<input class="mt-1 w-full rounded-lg border px-3 py-2" type="password" name="password" required></label>
    <label class="block text-sm">Confirmation<input class="mt-1 w-full rounded-lg border px-3 py-2" type="password" name="password_confirmation" required></label>
    <button class="w-full rounded-xl bg-electric px-4 py-3 text-white">Créer le compte</button>
</form>
@endsection
