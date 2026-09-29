@extends('layouts.business')
@section('title', $router->name)
@section('heading', 'Mon MikroTik')
@section('content')
@php
    $mode = $router->detail('connection_mode');
    $zoneName = $router->wifiZone->name ?? 'Non choisie';
    $linked = $router->planLinks->mapWithKeys(fn ($link) => [$link->plan_id => $link->profile->name ?? null]);
@endphp

<p class="mb-3"><a href="{{ route('mikrotiks.assistant') }}">Tous mes routeurs</a></p>

<section class="card zone-card shadow-sm mb-3">
    <div class="card-body">
        <h1 class="h4">Routeur</h1>
        <p class="mb-1">WiFi Zone : {{ $zoneName }}</p>
        <p class="mb-1">Routeur : {{ $router->name }}</p>
        <p class="mb-1">Adresse : {{ $router->host }}</p>
        <p class="mb-0">
            Statut :
            @if($mode === 'simulation')
                <span class="badge text-bg-warning">SIMULATION</span>
                Aucun routeur réel n’est connecté.
            @elseif($router->status === 'online')
                <span class="badge text-bg-success">Connecté</span> ✓ Routeur connecté
            @elseif($router->status === 'offline')
                <span class="badge text-bg-danger">Hors ligne</span>
            @elseif(! $router->wifi_zone_id || ! $router->identity)
                <span class="badge text-bg-warning">Configuration incomplète</span>
            @else
                <span class="badge text-bg-secondary">En attente</span>
            @endif
        </p>
    </div>
</section>

<div class="row g-3">
    <div class="col-md-6">
        <section class="card zone-card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Connexion</h2>
                <p class="mb-1">Nom détecté : {{ $router->identity ?: '—' }}</p>
                <p class="mb-1">Version : {{ $router->routeros_version ?: '—' }}</p>
                <p class="mb-1">Temps de marche : {{ $router->detail('uptime') ?: '—' }}</p>
                <p class="mb-1">Dernière synchronisation : {{ $router->last_synced_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'pas encore' }}</p>
                <p class="mb-1">Dernière erreur : {{ $router->last_error ?: 'Aucune' }}</p>
                <p class="mb-1">Utilisateurs actifs : {{ $router->detail('active_users', 0) }}</p>
                <p class="mb-3">Tickets en attente : {{ $pending->count() }}</p>
                <form method="POST" action="{{ route('mikrotiks.assistant.read', $router) }}" data-loader>
                    @csrf
                    <button class="btn biz-btn">Synchroniser</button>
                </form>
                <form class="mt-2" method="POST" action="{{ route('mikrotiks.assistant.auto', $router) }}">
                    @csrf
                    <input type="hidden" name="auto_sync" value="{{ $router->auto_sync ? 0 : 1 }}">
                    <button class="btn btn-outline-primary">{{ $router->auto_sync ? 'Désactiver la synchro automatique' : 'Synchroniser automatiquement' }}</button>
                </form>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card zone-card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Profils</h2>
                @if($profiles === [] && $router->profiles->isEmpty())
                    <p class="text-secondary">Aucun profil lu pour le moment. Synchronisez le routeur.</p>
                @else
                    <ul class="list-unstyled">
                        @forelse($profiles as $profile)
                            <li class="border-bottom py-2">
                                <strong>{{ $profile['name'] ?? 'Profil' }}</strong>
                                <span class="d-block">Temps : {{ $profile['session-timeout'] ?? '—' }}</span>
                                <span class="d-block">Limite données : {{ $profile['limit-bytes-total'] ?? 'Sans limite' }}</span>
                                <span class="d-block">Débit : {{ $profile['rate-limit'] ?? 'Non défini' }}</span>
                            </li>
                        @empty
                            @foreach($router->profiles as $profile)
                                <li class="border-bottom py-2">
                                    <strong>{{ $profile->name }}</strong>
                                    <span class="d-block">Débit : {{ $profile->rate_limit ?: 'Non défini' }}</span>
                                </li>
                            @endforeach
                        @endforelse
                    </ul>
                @endif
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card zone-card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Associer les forfaits</h2>
                @forelse($plans as $plan)
                    @php $current = $linked[$plan->id] ?? $plan->mikrotik_profile; @endphp
                    <article class="border rounded p-2 mb-2">
                        <p class="mb-1"><strong>{{ $plan->name }}</strong> · {{ $plan->durationLabel() }}</p>
                        <p class="mb-2">{{ $current ? 'Profil : '.$current : 'Aucun profil associé' }}</p>
                        @if($router->profiles->isNotEmpty())
                            <form method="POST" action="{{ route('mikrotiks.plan-profile', $router) }}" class="d-flex gap-2">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <select class="form-select form-select-sm" name="mikrotik_profile" required>
                                    @foreach($router->profiles as $profile)
                                        <option value="{{ $profile->name }}" @selected($current === $profile->name)>{{ $profile->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-primary">Associer</button>
                            </form>
                        @endif
                        @if(! $current)
                            <form class="mt-2" method="POST" action="{{ route('mikrotiks.assistant.profile-preview', $router) }}">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <button class="btn btn-sm btn-outline-secondary">Créer automatiquement le profil</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-secondary">Créez un forfait avant de l’associer.</p>
                @endforelse

                @if(is_array($preview))
                    <div class="alert alert-info mt-3">
                        <p class="mb-1">Voici ce qui sera créé. Rien n’est envoyé avant confirmation.</p>
                        <p class="mb-1">Nom du profil : {{ $preview['name'] }}</p>
                        <p class="mb-1">Durée : {{ $preview['duration_label'] }}</p>
                        <p class="mb-1">Limites : {{ $preview['data_label'] }}</p>
                        <p class="mb-2">Débit : {{ $preview['rate_label'] }}</p>
                        <form method="POST" action="{{ route('mikrotiks.assistant.profile', $router) }}" data-loader>
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $previewPlanId }}">
                            <input type="hidden" name="confirm" value="1">
                            <button class="btn biz-btn">Confirmer la création</button>
                        </form>
                    </div>
                @endif
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card zone-card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Tickets en attente</h2>
                @forelse($pending as $voucher)
                    <article class="border rounded p-2 mb-2">
                        <p class="mb-1"><strong>{{ $voucher->username }}</strong> · {{ $voucher->plan->name ?? 'Forfait' }}</p>
                        <p class="mb-0">Synchronisation en attente</p>
                    </article>
                @empty
                    <p class="text-secondary">Aucun ticket en attente.</p>
                @endforelse
                @if($pending->isNotEmpty())
                    <form method="POST" action="{{ route('mikrotiks.assistant.retry', $router) }}" data-loader>
                        @csrf
                        <button class="btn biz-btn">Réessayer</button>
                    </form>
                @endif
            </div>
        </section>
    </div>
    <div class="col-12">
        <section class="card zone-card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <h2 class="h5 mb-0">Utilisateurs connectés</h2>
                    <a href="{{ route('active-users.index') }}">Ouvrir la liste</a>
                </div>
                @forelse($sessions as $session)
                    <article class="border rounded p-2 mt-2">
                        <p class="mb-1"><strong>{{ $session['user'] ?? '—' }}</strong></p>
                        <p class="mb-1">Adresse IP : {{ $session['address'] ?? '—' }}</p>
                        <p class="mb-1">MAC : {{ $session['mac-address'] ?? '—' }}</p>
                        <p class="mb-1">Temps : {{ $session['uptime'] ?? '—' }}</p>
                        <p class="mb-1">Trafic : {{ $session['bytes-in'] ?? '—' }} / {{ $session['bytes-out'] ?? '—' }}</p>
                        <p class="mb-2">Profil : {{ $session['server'] ?? '—' }}</p>
                        @if(! empty($session['.id']))
                            <form method="POST" action="{{ route('mikrotiks.disconnect', $router) }}">
                                @csrf
                                <input type="hidden" name="active_id" value="{{ $session['.id'] }}">
                                <input type="hidden" name="username" value="{{ $session['user'] ?? '' }}">
                                <button class="btn btn-sm btn-outline-danger">Déconnecter</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-secondary mt-2">Aucun utilisateur connecté dans la dernière lecture.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
