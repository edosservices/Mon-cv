@extends('layouts.app')
@section('heading', $plan ? 'Modifier le profil' : 'Ajouter un profil')
@section('content')
@if($notice)
    <p class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $notice }}</p>
@endif
@if(($routerProfiles ?? []) !== [])
    <section class="mb-4 rounded-2xl bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold">Profils lus sur le MikroTik</h2>
        <div class="mt-3 grid gap-2">
            @foreach($routerProfiles as $row)
                <button class="rounded-xl border px-3 py-3 text-left" type="button" data-profile-fill="{{ $row['name'] }}" data-profile-rate="{{ $row['rate'] }}" data-profile-time="{{ $row['timeout'] }}" data-profile-shared="{{ $row['shared'] }}" data-profile-pool="{{ $row['pool'] }}">
                    <strong class="block">{{ $row['name'] }}</strong>
                    <small class="text-slate-500">{{ trim(implode(' · ', array_filter([$row['timeout'], $row['rate'], $row['shared'] !== '' ? $row['shared'].' shared' : null, $row['pool']]))) }}</small>
                </button>
            @endforeach
        </div>
    </section>
@endif
<form class="grid gap-4 rounded-2xl bg-white p-4 shadow-sm" method="POST" action="{{ $plan ? route('entrepreneur.profiles.update', $plan) : route('entrepreneur.profiles.store') }}" data-profile-compose>
    @csrf
    @if($plan) @method('PUT') @endif
    <label class="text-sm font-semibold">WiFi Zone
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="wifi_zone_id" required>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('wifi_zone_id', $plan?->wifi_zone_id) === $zone->id)>{{ $zone->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Name
        <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="name" value="{{ old('name', $plan?->mikrotik_profile ?: $plan?->name) }}" required maxlength="32" data-profile-name>
    </label>
    <div data-time-calc>
        <p class="text-sm font-semibold">Durée</p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach(['1h' => '1 heure', '2h' => '2 heures', '12h' => '12 heures', '1d' => '1 jour', '2d' => '2 jours', '4d' => '4 jours', '7d' => '7 jours', '30d' => '30 jours'] as $code => $label)
                <button class="rounded-lg border px-3 py-2 text-sm font-semibold" type="button" data-time-pick="{{ $code }}">{{ $label }}</button>
            @endforeach
        </div>
        <p class="mt-2 text-sm text-slate-600" data-time-readout></p>
    </div>
    <label class="text-sm font-semibold">Address Pool
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="address_pool">
            <option value="none" @selected(old('address_pool', $plan?->hotspot['address_pool'] ?? 'none') === 'none')>none</option>
            @foreach($pools as $pool)
                <option value="{{ $pool }}" @selected(old('address_pool', $plan?->hotspot['address_pool'] ?? '') === $pool)>{{ $pool }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Shared Users
        <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="shared_users" min="1" max="100" value="{{ old('shared_users', $plan?->hotspot['shared_users'] ?? 1) }}" required>
        <span class="mt-1 block font-normal text-slate-500">Nombre de connexions ou d’appareils autorisés selon la configuration HotSpot.</span>
    </label>
    <div class="grid gap-3 sm:grid-cols-2" data-rate-calc>
        <label class="text-sm font-semibold">Download
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" placeholder="20M" data-rate-down autocomplete="off">
        </label>
        <label class="text-sm font-semibold">Upload
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" placeholder="10M" data-rate-up autocomplete="off">
        </label>
        <label class="text-sm font-semibold sm:col-span-2">Rate limit [up/down]
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="rate_limit" value="{{ old('rate_limit', $plan?->hotspot['rate_limit'] ?? '') }}" placeholder="512k/1M" required data-rate-value>
        </label>
        <p class="text-sm text-slate-600 sm:col-span-2" data-rate-readout>Exemple : 512k/1M. Le download calcule l’upload, qui reste modifiable.</p>
    </div>
    <label class="text-sm font-semibold">Expired Mode
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="expired_mode">
            @foreach($expired as $value => $label)
                <option value="{{ $value }}" @selected(old('expired_mode', $plan?->hotspot['expired_mode'] ?? 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="mt-1 block font-normal text-slate-500">Enregistré sur le profil LIMETE. Le routeur n’est pas modifié avec une option qu’il ne connaît pas.</span>
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
        @php
            $priceCurrency = old('price_currency', $plan?->currency ?: 'CDF');
            $sellingCurrency = old('selling_price_currency', $plan?->selling_currency ?: $priceCurrency);
            $priceUnit = strtoupper((string) $priceCurrency) === 'CDF' ? 'FC' : $priceCurrency;
            $sellingUnit = strtoupper((string) $sellingCurrency) === 'CDF' ? 'FC' : $sellingCurrency;
        @endphp
        <label class="text-sm font-semibold">Price {{ $priceUnit }}
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" step="0.01" min="0" name="price_amount" value="{{ old('price_amount', $plan?->price) }}" required>
        </label>
        <input type="hidden" name="price_currency" value="{{ $priceCurrency }}">
        <label class="text-sm font-semibold">Selling Price {{ $sellingUnit }}
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" step="0.01" min="0" name="selling_price_amount" value="{{ old('selling_price_amount', $plan?->selling_price) }}">
        </label>
        <input type="hidden" name="selling_price_currency" value="{{ $sellingCurrency }}">
    </div>
    <label class="text-sm font-semibold">Lock User
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="lock_user">
            <option value="disable" @selected(old('lock_user', ($plan?->hotspot['lock_user'] ?? '') === 'Enable' ? 'enable' : 'disable') === 'disable')>Disable</option>
            <option value="enable" @selected(old('lock_user', ($plan?->hotspot['lock_user'] ?? '') === 'Enable' ? 'enable' : 'disable') === 'enable')>Enable</option>
        </select>
        <span class="mt-1 block font-normal text-slate-500">Enable avec un seul appareil partagé : One device.</span>
    </label>
    <label class="text-sm font-semibold">Parent Queue
        <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="parent_queue">
            <option value="none">none</option>
            @foreach($queues as $queue)
                <option value="{{ $queue }}" @selected(old('parent_queue', $plan?->hotspot['parent_queue'] ?? '') === $queue)>{{ $queue }}</option>
            @endforeach
        </select>
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-semibold">Validity
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="validity" value="{{ old('validity', $plan?->hotspot['validity'] ?? '') }}" placeholder="1d" required data-time-validity>
        </label>
        <label class="text-sm font-semibold">Time Limit
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="time_limit" value="{{ old('time_limit', $plan?->hotspot['time_limit'] ?? '') }}" placeholder="2d" data-time-limit>
        </label>
    </div>
    @unless($plan)
        <label class="inline-flex items-center gap-2 text-sm font-semibold">
            <input type="checkbox" name="sync" value="1" @checked(old('sync', '1'))>
            Enregistrer le profil sur le MikroTik
        </label>
    @endunless
    <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit">Enregistrer</button>
</form>
@endsection
