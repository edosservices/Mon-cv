@extends('layouts.app')
@section('heading', $plan ? 'Modifier le profil' : 'Ajouter un profil')
@section('content')
@if($notice)
    <p class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $notice }}</p>
@endif
<form class="grid gap-4 rounded-2xl bg-white p-4 shadow-sm" method="POST" action="{{ $plan ? route('entrepreneur.profiles.update', $plan) : route('entrepreneur.profiles.store') }}">
    @csrf
    @if($plan) @method('PUT') @endif
    <label class="text-sm font-semibold">WiFi Zone
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="wifi_zone_id" required>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('wifi_zone_id', $plan?->wifi_zone_id) === $zone->id)>{{ $zone->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Nom
        <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="name" value="{{ old('name', $plan?->mikrotik_profile ?: $plan?->name) }}" required maxlength="32">
    </label>
    <label class="text-sm font-semibold">Address pool
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="address_pool">
            <option value="none" @selected(old('address_pool', $plan?->hotspot['address_pool'] ?? 'none') === 'none')>none</option>
            @foreach($pools as $pool)
                <option value="{{ $pool }}" @selected(old('address_pool', $plan?->hotspot['address_pool'] ?? '') === $pool)>{{ $pool }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Shared users
        <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="shared_users" min="1" max="100" value="{{ old('shared_users', $plan?->hotspot['shared_users'] ?? 1) }}" required>
        <span class="mt-1 block font-normal text-slate-500">Nombre de connexions ou d’appareils autorisés selon la configuration HotSpot.</span>
    </label>
    <label class="text-sm font-semibold">Rate limit
        <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="rate_limit" value="{{ old('rate_limit', $plan?->hotspot['rate_limit'] ?? '') }}" placeholder="10M/10M" required>
    </label>
    <label class="text-sm font-semibold">Expired mode
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="expired_mode">
            @foreach($expired as $value => $label)
                <option value="{{ $value }}" @selected(old('expired_mode', $plan?->hotspot['expired_mode'] ?? 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="mt-1 block font-normal text-slate-500">Enregistré sur le profil LIMETE. Le routeur n’est pas modifié avec une option qu’il ne connaît pas.</span>
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-semibold">Prix
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" step="0.01" min="0" name="price_amount" value="{{ old('price_amount', $plan?->price) }}" required>
        </label>
        <label class="text-sm font-semibold">Devise
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="price_currency" value="{{ old('price_currency', $plan?->currency) }}" maxlength="3" required>
        </label>
        <label class="text-sm font-semibold">Prix de vente
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" step="0.01" min="0" name="selling_price_amount" value="{{ old('selling_price_amount', $plan?->selling_price) }}">
        </label>
        <label class="text-sm font-semibold">Devise de vente
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="selling_price_currency" value="{{ old('selling_price_currency', $plan?->selling_currency) }}" maxlength="3">
        </label>
    </div>
    <label class="text-sm font-semibold">Lock user
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="lock_user">
            <option value="disable" @selected(old('lock_user', ($plan?->hotspot['lock_user'] ?? '') === 'Enable' ? 'enable' : 'disable') === 'disable')>Disable</option>
            <option value="enable" @selected(old('lock_user', ($plan?->hotspot['lock_user'] ?? '') === 'Enable' ? 'enable' : 'disable') === 'enable')>Enable</option>
        </select>
        <span class="mt-1 block font-normal text-slate-500">Enable avec un seul appareil partagé : One device.</span>
    </label>
    <label class="text-sm font-semibold">Parent queue
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="parent_queue">
            <option value="none">none</option>
            @foreach($queues as $queue)
                <option value="{{ $queue }}" @selected(old('parent_queue', $plan?->hotspot['parent_queue'] ?? '') === $queue)>{{ $queue }}</option>
            @endforeach
        </select>
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-semibold">Validity
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="validity" value="{{ old('validity', $plan?->hotspot['validity'] ?? '') }}" placeholder="1d" required>
        </label>
        <label class="text-sm font-semibold">Time limit
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="time_limit" value="{{ old('time_limit', $plan?->hotspot['time_limit'] ?? '') }}" placeholder="24h">
        </label>
    </div>
    @unless($plan)
        <label class="inline-flex items-center gap-2 text-sm font-semibold">
            <input type="checkbox" name="sync" value="1" @checked(old('sync'))>
            Créer le profil sur le MikroTik s’il n’existe pas
        </label>
    @endunless
    <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit">Enregistrer</button>
</form>
@endsection
