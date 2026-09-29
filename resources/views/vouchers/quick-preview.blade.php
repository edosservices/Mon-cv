@extends('layouts.app')
@section('heading', 'Aperçu du profil')
@section('content')
@php
    $snap = $preview['snapshot'];
    $money = fn ($amount, $currency) => $amount === null ? null : \App\Support\Money::shop($amount, $currency);
    $rows = array_filter([
        'Profil' => $snap['profile'],
        'Validité' => trim(($snap['validity'] ?? '').($snap['validity_label'] ? ' · '.$snap['validity_label'] : '')),
        'Limite de temps' => $snap['time_label'] ?: ($snap['time_limit'] ?? null),
        'Data limit' => $snap['data_label'],
        'Débit' => $snap['rate_limit'] ?? null,
        'Prix' => $money($snap['price_amount'], $snap['price_currency']),
        'Prix de vente' => $money($snap['selling_price_amount'], $snap['selling_price_currency']),
        'Verrouillage' => $snap['lock_user'] ?? null,
        'Utilisateurs partagés' => $snap['shared_users'] ?? null,
        'Pool d’adresses' => $snap['address_pool'] ?? null,
        'Queue parente' => $snap['parent_queue'] ?? null,
        'Mode d’expiration' => $snap['expired_mode'] ?? null,
        'Serveur' => $snap['server'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

@if($preview['notice'])
    <p class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $preview['notice'] }}</p>
@endif

<section class="rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="text-lg font-semibold">Aperçu du profil</h2>
    <article class="mx-auto mt-4 max-w-sm rounded-2xl border p-4">
        <dl class="grid gap-3 text-sm">
            @foreach($rows as $label => $value)
                <div>
                    <dt class="text-slate-500">{{ $label }}</dt>
                    <dd class="text-lg font-semibold">{{ $value }}</dd>
                    @if(! in_array($label, ['Data limit', 'Serveur'], true))
                        <p class="text-xs text-slate-500">Automatique depuis le profil</p>
                    @endif
                </div>
            @endforeach
        </dl>
    </article>
    @if($preview['mode'] === 'add')
        <p class="mt-4 text-sm">Utilisateur <strong>{{ $preview['identities'][0]['username'] }}</strong></p>
        <p class="text-sm">Mot de passe <strong>{{ $preview['identities'][0]['password'] }}</strong></p>
    @else
        <p class="mt-4 text-sm">{{ $preview['qty'] }} tickets. Exemples : {{ implode(', ', $preview['samples']) }}</p>
    @endif
    <form class="mt-4" method="POST" action="{{ route('vouchers.quick.store') }}">
        @csrf
        @foreach(['wifi_zone_id', 'profile', 'server', 'data_value', 'data_unit', 'mode', 'qty', 'prefix', 'length', 'charset', 'username', 'password', 'draft'] as $field)
            <input type="hidden" name="{{ $field }}" value="{{ $input[$field] }}">
        @endforeach
        <input type="hidden" name="confirm" value="1">
        <button class="min-h-14 w-full rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit">
            {{ $preview['mode'] === 'add' ? 'Confirmer la génération' : 'Générer '.$preview['qty'].' tickets' }}
        </button>
    </form>
</section>
@endsection
