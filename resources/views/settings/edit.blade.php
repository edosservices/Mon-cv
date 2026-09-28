@extends('layouts.app')
@section('heading', 'Paramètres')
@section('content')
<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
    @csrf @method('PUT')
    <label class="block text-sm">Entreprise<input class="mt-1 w-full rounded-lg border px-3 py-2" name="name" value="{{ old('name', $tenant->name) }}" required></label>
    <label class="block text-sm">Téléphone<input class="mt-1 w-full rounded-lg border px-3 py-2" name="phone" value="{{ old('phone', $tenant->phone) }}"></label>
    <label class="block text-sm">Adresse<input class="mt-1 w-full rounded-lg border px-3 py-2" name="address" value="{{ old('address', $tenant->address) }}"></label>
    <label class="block text-sm">Ville<input class="mt-1 w-full rounded-lg border px-3 py-2" name="city" value="{{ old('city', $tenant->city) }}"></label>
    <label class="block text-sm">Pays<input class="mt-1 w-full rounded-lg border px-3 py-2" name="country" value="{{ old('country', $tenant->country) }}"></label>
    <label class="block text-sm">Couleur<input class="mt-1 w-full rounded-lg border px-3 py-2" name="primary_color" value="{{ old('primary_color', $tenant->primary_color) }}"></label>
    <label class="block text-sm">Domaine personnalisé (préparé pour la marque blanche)<input class="mt-1 w-full rounded-lg border px-3 py-2" name="custom_domain" value="{{ old('custom_domain', $tenant->custom_domain) }}" placeholder="wifi.mondomaine.com"></label>
    <label class="block text-sm">Logo<input class="mt-1 w-full" type="file" name="logo" accept="image/*"></label>
    <button class="rounded-xl bg-electric px-4 py-3 text-white">Enregistrer</button>
</form>
@endsection
