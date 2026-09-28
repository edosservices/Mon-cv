@extends('layouts.app')
@section('heading', $plan->exists ? 'Modifier le forfait' : 'Nouveau forfait')
@section('content')
<form method="POST" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
    @csrf
    @if($plan->exists) @method('PUT') @endif
    <label class="block text-sm">Nom<input class="mt-1 w-full rounded-lg border px-3 py-2" name="name" value="{{ old('name', $plan->name) }}" required></label>
    <label class="block text-sm">Durée en secondes<input class="mt-1 w-full rounded-lg border px-3 py-2" type="number" name="duration_seconds" value="{{ old('duration_seconds', $plan->duration_seconds) }}" required></label>
    <label class="block text-sm">Prix<input class="mt-1 w-full rounded-lg border px-3 py-2" type="number" step="1" name="price" value="{{ old('price', $plan->price) }}"></label>
    <label class="block text-sm">Devise<input class="mt-1 w-full rounded-lg border px-3 py-2" name="currency" value="{{ old('currency', $plan->currency ?: 'CDF') }}" maxlength="3" required></label>
    <label class="block text-sm">Zone
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="wifi_zone_id">
            <option value="">Toutes les zones</option>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}" @selected(old('wifi_zone_id', $plan->wifi_zone_id) == $zone->id)>{{ $zone->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">Profil MikroTik<input class="mt-1 w-full rounded-lg border px-3 py-2" name="mikrotik_profile" value="{{ old('mikrotik_profile', $plan->mikrotik_profile) }}"></label>
    <label class="block text-sm">Description<textarea class="mt-1 w-full rounded-lg border px-3 py-2" name="description">{{ old('description', $plan->description) }}</textarea></label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="unlimited_data" value="1" @checked(old('unlimited_data', $plan->unlimited_data))> Internet illimité</label>
    <label class="block text-sm">Statut
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="status">
            <option value="active" @selected(old('status', $plan->status ?: 'active') === 'active')>Actif</option>
            <option value="inactive" @selected(old('status', $plan->status) === 'inactive')>Inactif</option>
        </select>
    </label>
    <button class="rounded-xl bg-electric px-4 py-3 text-white">Enregistrer</button>
</form>
@endsection
