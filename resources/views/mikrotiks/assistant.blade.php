@extends('layouts.business')
@section('title', 'Connecter mon MikroTik')
@section('heading', 'Connecter mon MikroTik')
@section('content')
@php
    $draft = $draft ?? null;
    $step = (int) ($draft['step'] ?? 0);
    $labels = [
        1 => 'Nom',
        2 => 'Adresse',
        3 => 'Identifiants',
        4 => 'Tester',
        5 => 'Choisir la WiFi Zone',
        6 => 'Lire le routeur',
        7 => 'Associer les forfaits',
        8 => 'Terminer',
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Connecter mon MikroTik</h1>
    @if($step === 0)
        <form method="POST" action="{{ route('mikrotiks.assistant.start') }}">
            @csrf
            <button class="btn biz-btn">+ Ajouter mon MikroTik</button>
        </form>
    @endif
</div>

@if($step >= 1)
    <section class="card zone-card shadow-sm mb-3">
        <div class="card-body">
            <p class="mb-2">Étape {{ $step }} sur 8 — {{ $labels[$step] ?? 'Assistant' }}</p>
            <div class="progress" role="progressbar" aria-valuenow="{{ $step }}" aria-valuemin="1" aria-valuemax="8" aria-label="Progression">
                <div class="progress-bar" style="width: {{ max(12, (int) round($step / 8 * 100)) }}%"></div>
            </div>
            <ol class="biz-steps mt-3">
                @foreach($labels as $number => $label)
                    <li @class(['is-done' => $number < $step, 'is-current' => $number === $step])>{{ $number }}. {{ $label }}</li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="card zone-card shadow-sm">
        <div class="card-body">
            @if($step === 1)
                <form method="POST" action="{{ route('mikrotiks.assistant.step') }}" data-loader>
                    @csrf
                    <input type="hidden" name="step" value="1">
                    <label class="form-label" for="router-name">Nom du routeur</label>
                    <input class="form-control" id="router-name" name="name" value="{{ old('name', $draft['name']) }}" required maxlength="160" placeholder="MikroTik Limete">
                    <button class="btn biz-btn mt-3">Continuer</button>
                </form>
            @elseif($step === 2)
                <form method="POST" action="{{ route('mikrotiks.assistant.step') }}" data-loader>
                    @csrf
                    <input type="hidden" name="step" value="2">
                    <label class="form-label" for="router-host">Adresse IP ou nom</label>
                    <input class="form-control" id="router-host" name="host" value="{{ old('host', $draft['host']) }}" required placeholder="192.168.88.1">
                    <label class="form-label mt-3" for="router-port">Port</label>
                    <input class="form-control" id="router-port" name="api_port" inputmode="numeric" value="{{ old('api_port', $draft['api_port'] ?: 8728) }}" required>
                    <p class="form-text">Laissez 8728 si vous ne l’avez pas changé sur le routeur.</p>
                    <details class="mt-3">
                        <summary>Paramètres avancés</summary>
                        <label class="form-label mt-3" for="router-ssl">Port sécurisé</label>
                        <input class="form-control" id="router-ssl" name="api_ssl_port" inputmode="numeric" value="{{ old('api_ssl_port', $draft['api_ssl_port'] ?: 8729) }}">
                        <label class="form-label mt-3" for="router-type">Type de connexion</label>
                        <select class="form-select" id="router-type" name="connection_type">
                            <option value="api" @selected(old('connection_type', $draft['connection_type']) === 'api')>Standard</option>
                            <option value="api-ssl" @selected(old('connection_type', $draft['connection_type']) === 'api-ssl')>Sécurisée</option>
                        </select>
                        <label class="form-label mt-3" for="router-timeout">Délai d’attente (secondes)</label>
                        <input class="form-control" id="router-timeout" name="timeout" inputmode="numeric" value="{{ old('timeout', $draft['timeout'] ?: 5) }}">
                    </details>
                    <button class="btn biz-btn mt-3">Continuer</button>
                </form>
            @elseif($step === 3)
                <form method="POST" action="{{ route('mikrotiks.assistant.step') }}" data-loader>
                    @csrf
                    <input type="hidden" name="step" value="3">
                    <label class="form-label" for="router-user">Nom d’utilisateur</label>
                    <input class="form-control" id="router-user" name="username" value="{{ old('username', $draft['username']) }}" required autocomplete="off">
                    <label class="form-label mt-3" for="router-password">Mot de passe</label>
                    <input class="form-control" id="router-password" name="password" type="password" required autocomplete="new-password">
                    <p class="form-text">Le mot de passe n’est plus affiché après cette étape.</p>
                    <button class="btn biz-btn mt-3">Continuer</button>
                </form>
            @elseif($step === 4)
                <p>Nous allons vérifier que le routeur répond, sans l’enregistrer.</p>
                <form method="POST" action="{{ route('mikrotiks.assistant.test') }}" data-loader>
                    @csrf
                    <button class="btn biz-btn">Tester la connexion</button>
                </form>
                @if(is_array($draft['probe'] ?? null))
                    @php $probe = $draft['probe']; @endphp
                    <div class="alert {{ ($draft['mode'] ?? '') === 'real' ? 'alert-success' : 'alert-warning' }} mt-3" role="status">
                        @if(($draft['mode'] ?? '') === 'real')
                            ✓ Routeur connecté
                        @else
                            <span class="badge text-bg-warning">SIMULATION</span>
                            Aucun routeur réel n’est connecté.
                        @endif
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nom détecté</dt><dd class="col-sm-8">{{ $probe['identity'] ?: '—' }}</dd>
                        <dt class="col-sm-4">Version</dt><dd class="col-sm-8">{{ $probe['version'] ?: '—' }}</dd>
                        <dt class="col-sm-4">Temps de marche</dt><dd class="col-sm-8">{{ $probe['uptime'] ?: '—' }}</dd>
                        <dt class="col-sm-4">WiFi du routeur</dt><dd class="col-sm-8">{{ ($probe['hotspot'] ?? false) ? 'Détecté' : 'Pas encore configuré' }}</dd>
                    </dl>
                    @if(($probe['interfaces'] ?? []) !== [])
                        <p class="mt-2 mb-1">Interfaces</p>
                        <ul>
                            @foreach(array_slice($probe['interfaces'], 0, 6) as $interface)
                                <li>{{ $interface['name'] ?? 'Interface' }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if(($probe['user_profiles'] ?? []) !== [])
                        <p class="mt-2 mb-1">Profils trouvés</p>
                        <ul>
                            @foreach($probe['user_profiles'] as $name)
                                <li>{{ $name }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <form method="POST" action="{{ route('mikrotiks.assistant.continue') }}" class="mt-3">
                        @csrf
                        <button class="btn biz-btn">Choisir la WiFi Zone</button>
                    </form>
                @endif
            @else
                <form method="POST" action="{{ route('mikrotiks.assistant.save') }}" data-loader>
                    @csrf
                    <label class="form-label" for="wifi-zone">WiFi Zone</label>
                    <select class="form-select" id="wifi-zone" name="wifi_zone_id" required>
                        <option value="">Choisir</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    @if($zones->isEmpty())
                        <p class="form-text">Créez d’abord une WiFi Zone.</p>
                    @endif
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="auto-sync" name="auto_sync" value="1" checked>
                        <label class="form-check-label" for="auto-sync">Synchroniser automatiquement les tickets</label>
                    </div>
                    <p class="form-text">Cette option envoie seulement les comptes des tickets. Elle ne modifie pas le reste du routeur.</p>
                    <button class="btn biz-btn mt-3" @disabled($zones->isEmpty())>Enregistrer</button>
                </form>
            @endif
        </div>
    </section>
@endif

<section class="mt-4">
    <h2 class="h5">Mes routeurs</h2>
    @forelse($routers as $router)
        <article class="card zone-card shadow-sm mt-3">
            <div class="card-body">
                <h3 class="h6 mb-1">{{ $router->name }}</h3>
                <p class="mb-1">WiFi Zone : {{ $router->wifiZone->name ?? 'Non choisie' }}</p>
                <p class="mb-2">
                    @if(($router->details['connection_mode'] ?? '') === 'simulation')
                        <span class="badge text-bg-warning">SIMULATION</span>
                    @elseif($router->status === 'online')
                        <span class="badge text-bg-success">Connecté</span>
                    @elseif($router->status === 'offline')
                        <span class="badge text-bg-danger">Hors ligne</span>
                    @elseif(! $router->wifi_zone_id)
                        <span class="badge text-bg-warning">Configuration incomplète</span>
                    @else
                        <span class="badge text-bg-secondary">En attente</span>
                    @endif
                </p>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('mikrotiks.assistant.show', $router) }}">Ouvrir</a>
            </div>
        </article>
    @empty
        <p class="text-secondary">Aucun routeur pour le moment. Ajoutez le vôtre avec le bouton ci-dessus.</p>
    @endforelse
    @if($pending > 0)
        <p class="mt-3">{{ $pending }} ticket(s) en attente de synchronisation.</p>
    @endif
</section>
@endsection
