@extends('layouts.app')
@section('heading', 'Forfait LIMETE')
@section('content')
@php
    $snap = $preview['snapshot'];
    $technical = array_filter([
        'Profil' => $snap['profile'] ?? null,
        'Débit' => $snap['rate_limit'] ?? null,
        'Utilisateurs partagés' => $snap['shared_users'] ?? null,
        'Verrouillage' => $snap['lock_user'] ?? null,
        'Pool d’adresses' => $snap['address_pool'] ?? null,
        'Queue parente' => $snap['parent_queue'] ?? null,
        'Mode d’expiration' => $snap['expired_mode'] ?? null,
        'Limite de temps routeur' => $snap['time_limit'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
    $plans = $preview['plans'] ?? [];
@endphp

<section class="rounded-2xl bg-white p-4 shadow-sm">
    <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">{{ $preview['notice'] }}</p>

    @if($technical !== [])
        <dl class="mt-4 grid gap-3 text-sm">
            @foreach($technical as $label => $value)
                <div class="rounded-xl border px-3 py-3">
                    <dt class="text-slate-500">{{ $label }}</dt>
                    <dd class="text-lg font-semibold">{{ $value }}</dd>
                    <p class="text-xs text-slate-500">Automatique depuis le profil</p>
                </div>
            @endforeach
        </dl>
    @endif

    <form class="mt-4 grid gap-3" method="POST" action="{{ route('vouchers.quick.plan') }}">
        @csrf
        <input type="hidden" name="wifi_zone_id" value="{{ $zone->id }}">
        <input type="hidden" name="profile" value="{{ $snap['profile'] }}">
        <p class="text-sm text-slate-700">Complétez uniquement le forfait commercial. Les paramètres techniques restent ceux du profil MikroTik.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-sm font-semibold">Validity
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="validity" value="{{ old('validity') }}" placeholder="1d" maxlength="32" required>
            </label>
            <label class="text-sm font-semibold">Time Limit
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="time_limit" value="{{ old('time_limit') }}" placeholder="24h" maxlength="32">
            </label>
            <label class="text-sm font-semibold">Price
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="price_amount" min="0" step="0.01" value="{{ old('price_amount') }}" required>
            </label>
            <label class="text-sm font-semibold">Currency
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="price_currency" value="{{ old('price_currency', 'CDF') }}" maxlength="8" required>
            </label>
            <label class="text-sm font-semibold">Selling Price
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="selling_price_amount" min="0" step="0.01" value="{{ old('selling_price_amount') }}">
            </label>
            <label class="text-sm font-semibold">Selling Currency
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="selling_price_currency" value="{{ old('selling_price_currency') }}" maxlength="8">
            </label>
        </div>
        <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit">Créer le forfait LIMETE</button>
    </form>

    @if($plans !== [])
        <form class="mt-6 grid gap-3" method="POST" action="{{ route('vouchers.quick.link') }}">
            @csrf
            <input type="hidden" name="wifi_zone_id" value="{{ $zone->id }}">
            <input type="hidden" name="profile" value="{{ $snap['profile'] }}">
            <label class="text-sm font-semibold">Forfait compatible
                <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="plan_id" required>
                    @foreach($plans as $plan)
                        <option value="{{ $plan['id'] }}">{{ $plan['name'] }}@if($plan['summary'] !== '') · {{ $plan['summary'] }}@endif</option>
                    @endforeach
                </select>
            </label>
            <button class="min-h-14 rounded-xl border px-4 py-3 font-semibold" type="submit">Associer à un forfait existant</button>
        </form>
    @endif
</section>
@endsection
