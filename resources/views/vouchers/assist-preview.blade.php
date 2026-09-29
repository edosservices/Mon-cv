@extends('layouts.app')
@section('heading', 'Créer un ticket')
@section('content')
@include('vouchers.assist-steps', ['step' => 5])
@php
    $params = $preview['parameters'];
    $profile = $preview['profile'];
    $identity = $preview['identity'];
    $choice = $profile['state'] === 'same' ? 'reuse' : ($profile['state'] === 'missing' ? 'create' : null);
@endphp

@if($preview['notice'])
    <p class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">{{ $preview['notice'] }}</p>
@endif

<section class="rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="text-lg font-semibold">Vérifier le profil</h2>
    @if($profile['state'] === 'same')
        <p class="mt-2 font-semibold text-emerald-700">✓ Profil existant trouvé</p>
    @elseif($profile['state'] === 'different')
        <p class="mt-2 font-semibold text-amber-800">{{ $profile['message'] }}</p>
    @else
        <p class="mt-2 text-slate-700">{{ $profile['message'] }}</p>
    @endif
    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
        <div><dt class="text-slate-500">Nom</dt><dd class="font-semibold">{{ $profile['name'] }}</dd></div>
        <div><dt class="text-slate-500">Time limit</dt><dd class="font-semibold">{{ $profile['time_limit'] }}</dd></div>
        <div><dt class="text-slate-500">Data limit</dt><dd class="font-semibold">{{ $profile['data_limit'] }}</dd></div>
        <div><dt class="text-slate-500">Rate limit</dt><dd class="font-semibold">{{ $profile['rate_limit'] }}</dd></div>
    </dl>
</section>

<section class="mt-4 rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="text-lg font-semibold">Utilisateurs HotSpot</h2>
    <label class="mt-2 block text-sm font-semibold">Rechercher
        <input class="mt-1 w-full rounded-xl border px-3 py-3" type="search" data-filter-list="#hotspot-users" placeholder="Nom ou profil">
    </label>
    <div class="mt-3 overflow-x-auto" id="hotspot-users">
        <p class="grid grid-cols-2 gap-1 border-b py-2 text-xs font-semibold text-slate-500 sm:grid-cols-4">
            <span>Nom</span><span>Profil</span><span>Statut</span><span>Dernière activité</span>
        </p>
        @forelse($preview['users'] as $user)
            <p class="user-row grid grid-cols-2 gap-1 border-b py-2 text-sm sm:grid-cols-4" data-filter-item>
                <span>{{ $user['name'] }}</span>
                <span>{{ $user['profile'] }}</span>
                <span>{{ $user['status'] }}</span>
                <span>{{ $user['activity'] }}</span>
            </p>
        @empty
            <p class="text-sm text-slate-500">Aucun utilisateur lu sur le routeur.</p>
        @endforelse
    </div>
</section>

<section class="mt-4 rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="text-lg font-semibold">Aperçu</h2>
    @if($identity['collision'])
        <p class="mt-2 font-semibold text-amber-800">⚠ {{ $identity['message'] }}</p>
        <form class="mt-3" method="POST" action="{{ route('vouchers.assist.preview') }}">
            @csrf
            <input type="hidden" name="wifi_zone_id" value="{{ $zone->id }}">
            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
            <input type="hidden" name="mbps" value="{{ $params['mbps'] }}">
            <input type="hidden" name="data_gb" value="{{ $params['data_bytes'] ? (int) round($params['data_bytes'] / 1073741824) : 0 }}">
            <input type="hidden" name="regenerate" value="1">
            <input type="hidden" name="draft" value="{{ $draft }}">
            <input type="hidden" name="alternate_name" value="{{ $params['profile'] }}">
            <button class="min-h-14 rounded-xl border px-4 py-3 font-semibold" type="submit">Générer un autre identifiant</button>
        </form>
    @else
        <article class="mx-auto mt-3 max-w-sm rounded-2xl border p-4">
            <p class="text-xs font-semibold tracking-wide text-slate-500">TICKET</p>
            <dl class="mt-2 grid gap-2 text-sm">
                <div><dt>Utilisateur</dt><dd class="text-lg font-semibold">{{ $identity['username'] }}</dd></div>
                <div><dt>Mot de passe</dt><dd class="text-lg font-semibold">{{ $identity['password'] }}</dd></div>
                <div><dt>Durée</dt><dd class="font-semibold">{{ $params['duration_label'] }}</dd></div>
                <div><dt>Time limit</dt><dd class="font-semibold">{{ $params['time_limit'] }}</dd></div>
                <div><dt>Data limit</dt><dd class="font-semibold">{{ $params['data_label'] }}</dd></div>
                <div><dt>Débit</dt><dd class="font-semibold">{{ $params['mbps'] }} Mbps</dd></div>
                <div><dt>Profil MikroTik</dt><dd class="font-semibold">{{ $params['profile'] }}</dd></div>
                <div><dt>Statut</dt><dd class="font-semibold">{{ $profile['state'] === 'same' ? '✓ Profil existant' : $profile['message'] }}</dd></div>
            </dl>
        </article>
        @if($profile['state'] === 'different')
            <div class="mt-4 grid gap-2">
                <form method="POST" action="{{ route('vouchers.assist.store') }}">
                    @csrf
                    @include('vouchers.assist-fields')
                    <input type="hidden" name="profile_choice" value="reuse">
                    <input type="hidden" name="confirm" value="1">
                    <button class="min-h-14 w-full rounded-xl border px-4 py-3 font-semibold" type="submit">Réutiliser</button>
                </form>
                <form class="grid gap-2" method="POST" action="{{ route('vouchers.assist.preview') }}">
                    @csrf
                    <input type="hidden" name="wifi_zone_id" value="{{ $zone->id }}">
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <input type="hidden" name="mbps" value="{{ $params['mbps'] }}">
                    <input type="hidden" name="data_gb" value="{{ $params['data_bytes'] ? (int) round($params['data_bytes'] / 1073741824) : 0 }}">
                    <input type="hidden" name="username" value="{{ $identity['username'] }}">
                    <input type="hidden" name="password" value="{{ $identity['password'] }}">
                    <input type="hidden" name="profile_choice" value="rename">
                    <label class="text-sm font-semibold">Autre nom de profil
                        <input class="mt-1 w-full rounded-xl border px-3 py-3" name="alternate_name" required maxlength="32">
                    </label>
                    <button class="min-h-14 rounded-xl border px-4 py-3 font-semibold" type="submit">Choisir un autre nom</button>
                </form>
                <a class="min-h-14 rounded-xl border px-4 py-3 text-center font-semibold" href="{{ route('vouchers.generate') }}">Annuler</a>
            </div>
        @else
            <form class="mt-4" method="POST" action="{{ route('vouchers.assist.store') }}">
                @csrf
                @include('vouchers.assist-fields')
                <input type="hidden" name="profile_choice" value="{{ $choice }}">
                <input type="hidden" name="confirm" value="1">
                @if($profile['state'] === 'same')
                    <button class="min-h-14 w-full rounded-xl border px-4 py-3 font-semibold" type="submit">Réutiliser ce profil</button>
                @endif
                <button class="mt-2 min-h-14 w-full rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit">Confirmer et créer</button>
            </form>
        @endif
    @endif
</section>
@endsection
