@extends('layouts.app')
@section('heading', 'Tableau de bord')
@section('content')
@php
    $periods = [
        'today' => 'Aujourd’hui',
        'yesterday' => 'Hier',
        '7d' => '7 derniers jours',
        '30d' => '30 derniers jours',
        'month' => 'Ce mois',
        'prev_month' => 'Mois précédent',
        'custom' => 'Personnalisé',
    ];
    $query = request()->except('page');
@endphp

<div class="lm-dash">
<header class="lm-hero min-w-0">
    <p class="lm-page-sub">Vue générale de votre activité</p>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="lm-hello">Bonjour, {{ auth()->user()->name }}</p>
        @if(auth()->user()->hasPermission('vouchers.manage'))
            <a class="lm-cta" href="{{ route('vouchers.generate') }}">Générer des tickets</a>
        @endif
    </div>
    <form method="GET" class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-sm font-semibold">WiFi Zone
            <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="zone" onchange="this.form.submit()">
                <option value="">Toutes les zones</option>
                @foreach($report['zones'] as $zone)
                    <option value="{{ $zone->id }}" @selected($filters['zone_id'] === $zone->id)>{{ $zone->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-semibold">Période
            <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="period" onchange="this.form.submit()">
                @foreach($periods as $value => $label)
                    <option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if($filters['period'] === 'custom')
            <label class="text-sm font-semibold">Du
                <input class="mt-1 w-full rounded-xl border px-3 py-3" type="date" name="from" value="{{ $filters['from']->toDateString() }}">
            </label>
            <label class="text-sm font-semibold">Au
                <input class="mt-1 w-full rounded-xl border px-3 py-3" type="date" name="to" value="{{ $filters['to']->toDateString() }}">
            </label>
            <button class="rounded-xl bg-electric px-4 py-3 text-sm font-semibold text-white sm:col-span-2">Appliquer</button>
        @endif
        <input type="hidden" name="grain" value="{{ $filters['grain'] }}">
    </form>
</header>

<details class="lm-quick-fold" open>
    <summary>Actions rapides</summary>
<nav class="lm-quick" aria-label="Actions rapides">
    @if(auth()->user()->hasPermission('sales.confirm'))
        <a href="{{ route('sales.quick') }}">+ Vendre un ticket</a>
    @endif
    @if(auth()->user()->hasPermission('vouchers.manage'))
        <a href="{{ route('vouchers.generate') }}">+ Générer des tickets</a>
    @endif
    @if(auth()->user()->hasPermission('plans.manage'))
        <a href="{{ route('plans.create') }}">+ Créer un forfait</a>
    @endif
    @if(auth()->user()->hasPermission('zones.manage'))
        <a href="{{ route('wifi-zones.create') }}">+ Ajouter une WiFi Zone</a>
    @endif
    @if(auth()->user()->hasPermission('settings.manage'))
        <a href="{{ route('business.edit') }}">Mon Business</a>
        @if(auth()->user()->hasPermission('mikrotiks.manage'))
            <a href="{{ route('mikrotiks.assistant') }}">Connecter mon MikroTik</a>
        @endif
    @endif
    @if(auth()->user()->hasPermission('sales.view'))
        <a href="{{ route('reports.index') }}">Mes Rapports</a>
    @endif
</nav>
</details>

@if($notices->isNotEmpty())
<section class="mb-4 space-y-2" aria-label="Notifications">
    @foreach($notices as $notice)
        <a class="block min-w-0 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm" href="{{ route('notifications.index') }}">
            <p class="font-semibold">{{ $notice->data['title'] ?? 'Notification' }}</p>
            <p class="break-words text-slate-700">{{ $notice->data['body'] ?? '' }}</p>
        </a>
    @endforeach
</section>
@endif

@if($shopZones->isNotEmpty())
<section class="mb-4 rounded-2xl bg-navy p-4 text-white">
    <p class="text-sm text-sky-100">Boutique publique</p>
    <div class="mt-3 space-y-3">
        @foreach($shopZones as $zone)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="min-w-0 font-semibold">{{ $zone->name }}</p>
                <div class="flex flex-wrap gap-2">
                    <a class="rounded-xl bg-white px-4 py-3 text-sm font-semibold text-navy" href="{{ route('shop.show', $zone->slug) }}" target="_blank" rel="noopener">Voir ma boutique</a>
                    <button type="button" class="js-copy rounded-xl border border-white/30 px-4 py-3 text-sm" data-url="{{ route('shop.show', $zone->slug) }}">Copier le lien</button>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

<section class="lm-grid" aria-label="Indicateurs">
    @foreach($report['kpis'] as $kpi)
        <article class="lm-kpi lm-reveal" title="{{ $kpi['label'] }}">
            <p class="text-sm text-slate-500">{{ $kpi['label'] }}</p>
            <p class="mt-2 break-words text-2xl font-semibold" @if(! $kpi['money'] && is_numeric($kpi['value'])) data-count="{{ (int) $kpi['value'] }}" @endif>
                {{ $kpi['money'] ? \App\Support\Money::format($kpi['value']) : $kpi['value'] }}
            </p>
            @if($kpi['change'] !== null)
                <p class="mt-1 text-sm font-semibold {{ $kpi['change'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                    {{ $kpi['change'] >= 0 ? '▲' : '▼' }} {{ $kpi['change'] }} % vs hier
                </p>
            @endif
        </article>
    @endforeach
    <article class="lm-kpi">
        <p class="lm-kpi-label">Chiffre d’affaires du mois</p>
        <p class="lm-kpi-value">{{ \App\Support\Money::format($pulse['month']) }}</p>
    </article>
    <article class="lm-kpi">
        <p class="lm-kpi-label">Tickets vendus</p>
        <p class="lm-kpi-value" data-count="{{ (int) $pulse['sold'] }}">{{ $pulse['sold'] }}</p>
    </article>
    <article class="lm-kpi">
        <p class="lm-kpi-label">Tickets disponibles</p>
        <p class="lm-kpi-value" data-count="{{ (int) $pulse['available'] }}">{{ $pulse['available'] }}</p>
    </article>
    <article class="lm-kpi">
        <p class="lm-kpi-label">Sessions actives</p>
        <p class="lm-kpi-value" data-count="{{ (int) $pulse['sessions'] }}">{{ $pulse['sessions'] }}</p>
    </article>
</section>

<article class="lm-panel lm-router-card {{ ($charts['router']['online'] ?? false) ? 'is-online' : 'is-down' }}">
    <h2>Routeur</h2>
    @if($charts['router'])
        <p class="lm-router-state">{{ $charts['router']['online'] ? 'Connected' : 'Offline' }}</p>
        <p class="lm-router-name">{{ $charts['router']['name'] }}</p>
        <p class="lm-meta">{{ $charts['router']['identity'] ?: '—' }} · RouterOS {{ $charts['router']['version'] ?: '—' }}</p>
        <dl class="lm-router-grid">
            <div><dt>CPU</dt><dd>{{ $charts['router']['cpu'] ?: '—' }}</dd></div>
            <div><dt>Mémoire</dt><dd>{{ $charts['router']['memory'] ?: '—' }}</dd></div>
            <div><dt>Uptime</dt><dd>{{ $charts['router']['uptime'] ?: '—' }}</dd></div>
            <div><dt>Interfaces</dt><dd>{{ $charts['router']['interfaces'] ?? '—' }}</dd></div>
            <div><dt>HotSpot</dt><dd>{{ $charts['router']['servers'] }}</dd></div>
            <div><dt>Profils</dt><dd>{{ $charts['router']['profiles'] }}</dd></div>
            <div><dt>Utilisateurs</dt><dd>{{ $charts['router']['users'] ?? '—' }}</dd></div>
            <div><dt>Sessions</dt><dd>{{ $charts['router']['sessions'] ?? '—' }}</dd></div>
        </dl>
        @if(auth()->user()->hasPermission('mikrotiks.manage'))
            <div class="lm-router-actions">
                <form class="lm-act-sync" method="POST" action="{{ route('mikrotiks.sync', $charts['router']['id']) }}">@csrf<button type="submit">Synchroniser</button></form>
                <a class="lm-act-view" href="{{ route('mikrotiks.show', $charts['router']['id']) }}">Voir MikroTik</a>
                <form class="lm-act-retry" method="POST" action="{{ route('mikrotiks.sync', $charts['router']['id']) }}">@csrf<button type="submit">Réessayer</button></form>
            </div>
        @endif
    @else
        <p class="mt-2 text-sm text-slate-500">Aucun routeur associé.</p>
    @endif
</article>

<section id="revenus" class="lm-panel">
    <div class="lm-panel-head">
        <h2>Revenus</h2>
        <div class="lm-tabs">
            @foreach(['day' => 'Aujourd’hui', 'week' => 'Semaine', 'month' => 'Mois'] as $value => $label)
                <a class="{{ $filters['grain'] === $value ? 'is-on' : '' }}" href="{{ route('dashboard', array_merge($query, ['grain' => $value])) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <p class="lm-figure">{{ \App\Support\Money::format($report['period_revenue']) }}</p>
    <p class="lm-meta">{{ $filters['from']->timezone(config('app.timezone'))->format('d/m/Y') }} – {{ $filters['to']->timezone(config('app.timezone'))->format('d/m/Y') }}</p>
    @if($report['series'] === [])
        <p class="mt-4 text-sm text-slate-500">Pas encore de vente confirmée sur cette période.</p>
    @else
        @php $max = max(1, collect($report['series'])->max(fn ($row) => abs($row['amount']))); @endphp
        <div class="chart mt-4">
            <svg viewBox="0 0 100 36" width="100%" height="140" role="img" aria-label="Évolution du chiffre d'affaires">
                @foreach($report['series'] as $index => $point)
                    @php
                        $count = max(1, count($report['series']));
                        $x = $count === 1 ? 50 : ($index / ($count - 1)) * 100;
                        $height = min(32, (abs($point['amount']) / $max) * 32);
                        $y = 34 - $height;
                    @endphp
                    <rect x="{{ $x - (80 / $count / 2) }}" y="{{ $y }}" width="{{ max(1.2, 70 / $count) }}" height="{{ $height }}" rx="0.6" fill="{{ $point['amount'] < 0 ? '#a11d1d' : '#0b5ed7' }}"></rect>
                @endforeach
            </svg>
        </div>
        <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600">
            @foreach($report['series'] as $point)
                <li class="min-w-0">{{ $point['label'] }} · {{ \App\Support\Money::format($point['amount']) }}</li>
            @endforeach
        </ul>
    @endif
</section>

@if($report['zone_rows'] !== [])
<section id="zones" class="mt-6">
    <h2 class="font-semibold">Zones</h2>
    <div class="lm-table-wrap mt-3 rounded-2xl bg-white shadow-sm">
        <table class="lm-table">
            <thead>
                <tr><th>Zone</th><th>Routeur</th><th>En ligne</th><th>Ventes</th><th>CA</th></tr>
            </thead>
            <tbody>
                @foreach($report['zone_rows'] as $zone)
                    <tr>
                        <td><a class="font-semibold" href="{{ route('dashboard', array_merge($query, ['zone' => $zone['id']])) }}">{{ $zone['name'] }}</a><span class="block text-xs text-slate-500">{{ $zone['slug'] }} · {{ $zone['status'] === 'active' ? 'Active' : $zone['status'] }}</span></td>
                        <td>{{ $zone['router'] ?: 'Aucun' }}</td>
                        <td>{{ $zone['online'] }}</td>
                        <td>{{ $zone['sales'] }}</td>
                        <td>{{ \App\Support\Money::format($zone['revenue']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

<section id="mikrotik" class="lm-panel">
    <h2>État MikroTik</h2>
    @if($report['routers'] === [])
        <p class="mt-3 text-sm text-slate-500">Aucun routeur relié.</p>
    @else
        <div class="lm-table-wrap lm-router-table">
            <table class="lm-table">
                <thead>
                    <tr><th>Nom</th><th>État</th><th>Identity</th><th>Adresse</th><th>Actifs</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($report['routers'] as $card)
                        @php
                            $router = $card['router'];
                            $pendingTickets = $report['unsynced']->where('wifi_zone_id', $router->wifi_zone_id)->count();
                            $incomplete = ! $router->wifi_zone_id || ! filled($router->host) || ! filled($router->username);
                        @endphp
                        <tr>
                            <td class="font-semibold">
                                {{ $router->name }}
                                @php
                                    $resource = is_array($card['resource'] ?? null) ? $card['resource'] : [];
                                    $stored = is_array($router->details) ? $router->details : [];
                                    $cpu = $resource['cpu-load'] ?? ($stored['cpu'] ?? null);
                                    if (is_numeric($cpu)) {
                                        $cpu = rtrim(rtrim(number_format((float) $cpu, 2, '.', ''), '0'), '.').'%';
                                    }
                                    $memBytes = $resource['free-memory'] ?? null;
                                    $hddBytes = $resource['free-hdd-space'] ?? null;
                                    $asMib = function ($bytes) {
                                        if (! is_numeric($bytes)) {
                                            return null;
                                        }

                                        return rtrim(rtrim(number_format(((float) $bytes) / 1048576, 2, '.', ''), '0'), '.').' MiB';
                                    };
                                    $memory = $asMib($memBytes) ?: ($stored['memory'] ?? null);
                                    $disk = $asMib($hddBytes) ?: ($stored['disk'] ?? null);
                                @endphp
                                <div class="lm-metrics">
                                    <div><span>CPU</span><strong>{{ filled($cpu) ? $cpu : '—' }}</strong><small class="lm-sr">CPU Load : {{ filled($cpu) ? $cpu : '—' }}</small></div>
                                    <div><span>Mémoire</span><strong>{{ filled($memory) ? $memory : '—' }}</strong><small class="lm-sr">Free Memory : {{ filled($memory) ? $memory : '—' }}</small></div>
                                    <div><span>Disque</span><strong>{{ filled($disk) ? $disk : '—' }}</strong><small class="lm-sr">Free HDD : {{ filled($disk) ? $disk : '—' }}</small></div>
                                </div>
                                <details class="lm-inline-details">
                                    <summary>Voir détails</summary>
                                    <p>{{ $router->identity ?: '—' }}</p>
                                    <p>{{ $router->host }}</p>
                                    <p>{{ count($card['users']) }} actifs</p>
                                    <form method="POST" action="{{ route('mikrotiks.test', $router) }}">
                                        @csrf
                                        <button class="lm-chip-btn" type="submit">Tester la connexion</button>
                                    </form>
                                </details>
                            </td>
                            <td>
                                @if($router->status === 'online') 🟢 Connecté
                                @elseif($router->status === 'error') 🟠 Erreur
                                @elseif($router->status === 'offline') 🔴 Hors ligne
                                @else 🟠 En attente @endif
                                @if(in_array($router->status, ['offline', 'error'], true))
                                    <span class="block text-xs font-semibold">MikroTik hors ligne</span>
                                @endif
                                @if($incomplete)<span class="block text-xs">Configuration incomplète</span>@endif
                                @if($pendingTickets > 0)<span class="block text-xs">Tickets en attente : {{ $pendingTickets }}</span>@endif
                                @if(($router->details['connection_mode'] ?? null) === 'simulation')
                                    <span class="block text-xs">SIMULATION — aucun routeur réel n’est connecté.</span>
                                @endif
                                @if($router->last_error)<span class="block max-w-xs whitespace-normal text-xs text-amber-800">{{ $router->last_error }}</span>@endif
                                <span class="block text-xs text-slate-500">Dernière vérification {{ $router->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'pas encore vérifié' }}</span>
                            </td>
                            <td class="lm-cell-extra">{{ $router->identity ?: '—' }}</td>
                            <td class="lm-cell-extra">{{ $router->host }}</td>
                            <td class="lm-cell-extra">{{ count($card['users']) }}</td>
                            <td class="lm-cell-extra">
                                <form method="POST" action="{{ route('mikrotiks.test', $router) }}">
                                    @csrf
                                    <button class="lm-chip-btn" type="submit">Tester la connexion</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section id="connectes" class="lm-panel" @if($report['sessions'] !== []) data-live-table @endif>
    <div class="lm-panel-head">
        <div>
            <h2>Clients connectés</h2>
            <p class="lm-meta">
                @if(count($report['sessions']) > 0)
                    <span class="lm-dot" aria-hidden="true"></span>
                @endif
                {{ count($report['sessions']) }} en ligne
                @if($report['sessions'] === [])
                    ·
                    @if(collect($report['routers'])->contains(fn ($card) => in_array($card['router']->status, ['offline', 'error'], true)))
                        MikroTik hors ligne
                    @else
                        Aucun utilisateur connecté.
                    @endif
                @endif
            </p>
        </div>
        <a class="lm-text-link" href="{{ route('active-users.index') }}">Voir tous</a>
    </div>
    @if($report['sessions'] === [])
        <p class="lm-fold-body text-sm text-slate-500">
            <a href="{{ route('active-users.index') }}">Ouvrir les sessions</a>
        </p>
    @else
        <div class="lm-live-tools">
            <input type="search" data-live-search placeholder="Rechercher" aria-label="Rechercher un client connecté">
            <select data-live-status aria-label="Filtrer le statut">
                <option value="">Tous</option>
                <option value="online">En ligne</option>
                <option value="offline">Hors ligne</option>
            </select>
        </div>
        <div class="lm-table-wrap mt-3">
            <table class="lm-table">
                <thead>
                    <tr>
                        <th class="lm-hide-sm"><button class="lm-sort" type="button" data-live-sort="client">Client</button></th>
                        <th><button class="lm-sort" type="button" data-live-sort="username">Username</button></th>
                        <th><button class="lm-sort" type="button" data-live-sort="profile">Profil</button></th>
                        <th class="lm-hide-sm"><button class="lm-sort" type="button" data-live-sort="ip">IP</button></th>
                        <th class="lm-hide-sm"><button class="lm-sort" type="button" data-live-sort="rate">Débit</button></th>
                        <th><button class="lm-sort" type="button" data-live-sort="time">Temps</button></th>
                        <th class="lm-hide-sm"><button class="lm-sort" type="button" data-live-sort="status">Statut</button></th>
                        <th class="lm-hide-sm">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(collect($report['sessions'])->take(5) as $session)
                        <tr data-live-row data-status="{{ $session['status'] }}">
                            <td class="lm-hide-sm" data-k="client">{{ $session['client'] ?: '—' }}</td>
                            <td data-k="username">{{ $session['username'] }}</td>
                            <td data-k="profile">{{ $session['profile'] ?: ($session['plan'] ?: '—') }}</td>
                            <td class="lm-hide-sm" data-k="ip">{{ $session['ip'] }}</td>
                            <td class="lm-hide-sm" data-k="rate">{{ $session['rate'] ?: '—' }}</td>
                            <td data-k="time">
                                {{ $session['uptime'] }}
                                <span class="block text-xs text-slate-500">Expiration {{ $session['expires']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</span>
                            </td>
                            <td class="lm-hide-sm" data-k="status"><span class="lm-status is-on">En ligne</span></td>
                            <td class="lm-hide-sm">
                                <details class="lm-pop">
                                    <summary aria-label="Actions">⋮</summary>
                                    <div class="lm-pop-panel">
                                        <a href="{{ route('active-users.index') }}">Sessions</a>
                                        @if(auth()->user()->hasPermission('sessions.disconnect') && filled($session['active_id']))
                                            <form method="POST" action="{{ route('active-users.disconnect') }}">
                                                @csrf
                                                <input type="hidden" name="mikrotik_id" value="{{ $session['mikrotik_id'] }}">
                                                <input type="hidden" name="active_id" value="{{ $session['active_id'] }}">
                                                <input type="hidden" name="username" value="{{ $session['username'] }}">
                                                <button type="submit">Déconnecter</button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-slate-500" data-live-empty hidden>Aucun client pour ce filtre.</p>
        <div class="lm-pager" data-live-pager hidden>
            <button type="button" data-live-prev>Précédent</button>
            <span data-live-page>1 / 1</span>
            <button type="button" data-live-next>Suivant</button>
        </div>
        <a class="lm-text-link lm-more-link" href="{{ route('active-users.index') }}">Voir tous les clients →</a>
    @endif
</section>

@include('partials.space-charts')

<section id="paiements" class="lm-panel">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">Paiements</h2>
        <a class="text-sm font-semibold text-electric" href="{{ route('exports.payments', $query) }}">Exporter CSV</a>
    </div>
    <ul class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($report['payments'] as $state)
            <li class="min-w-0 rounded-xl bg-slate-50 p-3 text-sm">
                <p class="text-slate-500">{{ $state['label'] }}</p>
                <p class="text-xl font-semibold">{{ $state['total'] }}</p>
                @if($state['status'] === 'pending' && $state['total'] > 0)
                    <p>Montant en attente {{ \App\Support\Money::format($state['amount']) }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</section>

<section id="forfaits" class="mt-6 min-w-0 rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="font-semibold">Forfaits</h2>
    @if($report['plans'] === [])
        <p class="mt-3 text-sm text-slate-500">Aucun forfait.</p>
    @else
        @php $planMax = max(1, collect($report['plans'])->max('sold')); @endphp
        <ul class="mt-4 space-y-3">
            @foreach($report['plans'] as $plan)
                <li class="min-w-0">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span class="font-semibold">{{ $plan['name'] }}</span>
                        <span>{{ $plan['sold'] }} vendus · {{ \App\Support\Money::format($plan['revenue'], $plan['currency']) }}</span>
                    </div>
                    <p class="text-xs text-slate-500">{{ $plan['duration'] }} · {{ \App\Support\Money::format($plan['price'], $plan['currency']) }} · {{ $plan['active'] }} actifs · {{ $plan['expired'] }} expirés</p>
                    <div class="mt-1 h-2 rounded bg-slate-100"><div class="h-2 rounded bg-electric" style="width: {{ max($plan['sold'] > 0 ? 4 : 0, ($plan['sold'] / $planMax) * 100) }}%"></div></div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section id="heures" class="mt-6 min-w-0 rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="font-semibold">Heures</h2>
    @if($report['hours'] === [])
        <p class="mt-3 text-sm text-slate-500">Pas assez de données</p>
    @else
        <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
            @foreach($report['hours'] as $hour)
                <li class="rounded-xl bg-slate-50 p-3 text-sm"><span class="block text-slate-500">{{ $hour['label'] }}</span><span class="text-lg font-semibold">{{ $hour['total'] }}</span></li>
            @endforeach
        </ul>
    @endif
</section>

<section id="clients" class="mt-6 min-w-0 rounded-2xl bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">Clients</h2>
        <a class="text-sm font-semibold text-electric" href="{{ route('exports.customers', $query) }}">Exporter CSV</a>
    </div>
    <ul class="mt-3 grid grid-cols-2 gap-2 text-sm lg:grid-cols-4">
        <li class="rounded-xl bg-slate-50 p-3">Total <strong class="block text-lg">{{ $report['customers']['total'] }}</strong></li>
        <li class="rounded-xl bg-slate-50 p-3">Nouveaux <strong class="block text-lg">{{ $report['customers']['news'] }}</strong></li>
        <li class="rounded-xl bg-slate-50 p-3">Actifs <strong class="block text-lg">{{ $report['customers']['active'] }}</strong></li>
        <li class="rounded-xl bg-slate-50 p-3">Plusieurs achats <strong class="block text-lg">{{ $report['customers']['repeat'] }}</strong></li>
    </ul>
    @if($report['customers']['rows'] === [] || (is_countable($report['customers']['rows']) && count($report['customers']['rows']) === 0))
        <p class="mt-3 text-sm text-slate-500">Aucun client.</p>
    @else
        <div class="lm-table-wrap mt-3">
            <table class="lm-table">
                <thead><tr><th>Client</th><th>Téléphone</th><th>Zone</th><th>Achats</th><th>Dernier</th></tr></thead>
                <tbody>
                    @foreach($report['customers']['rows'] as $customer)
                        <tr>
                            <td>{{ $customer['name'] }}</td>
                            <td>{{ $customer['phone'] ?: '—' }}</td>
                            <td>{{ $customer['zone'] }}</td>
                            <td>{{ $customer['purchases'] }}</td>
                            <td>{{ $customer['last_purchase'] ? \Illuminate\Support\Carbon::parse($customer['last_purchase'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section id="sync" class="mt-6 min-w-0 rounded-2xl border border-amber-200 bg-amber-50 p-4">
    <h2 class="font-semibold">Tickets nécessitant une synchronisation</h2>
    @if($report['unsynced']->isEmpty())
        <p class="mt-2 text-sm">Aucun ticket en attente de synchronisation.</p>
    @else
        <div class="lm-table-wrap mt-3">
            <table class="lm-table">
                <thead><tr><th>Ticket</th><th>Client</th><th>Date</th><th>Sync</th><th></th></tr></thead>
                <tbody>
                    @foreach($report['unsynced'] as $voucher)
                        <tr>
                            <td>{{ $voucher->username }} · {{ $voucher->plan->name ?? 'Forfait' }}</td>
                            <td>{{ $voucher->customer->phone ?? $voucher->customer->name ?? '—' }}</td>
                            <td>{{ $voucher->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-normal">{{ $voucher->sync_label }} @if($voucher->sync_error)· {{ $voucher->sync_error }}@endif<span class="block text-xs">Tentatives : {{ $voucher->sync_attempts }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('vouchers.sync', $voucher) }}">
                                    @csrf
                                    <button class="min-h-10 rounded-lg bg-navy px-3 py-2 text-sm font-semibold text-white">Synchroniser</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section id="ventes" class="mt-6 min-w-0">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">Ventes récentes</h2>
        <div class="flex flex-wrap gap-3 text-sm font-semibold">
            <a class="text-electric" href="{{ route('sales.index', $query) }}">Toutes les ventes</a>
            <a class="text-electric" href="{{ route('exports.sales', $query) }}">Exporter CSV</a>
            <a class="text-electric" href="{{ route('exports.sales.pdf', $query) }}">PDF</a>
        </div>
    </div>
    @include('sales.cards', ['sales' => $sales])
</section>

<section id="activite" class="mt-6 min-w-0 rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="font-semibold">Activité</h2>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse($report['activity'] as $event)
            <li class="flex items-baseline justify-between gap-3"><span>{{ $event['label'] }}</span><time class="shrink-0 text-slate-500">{{ $event['at']?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time></li>
        @empty
            <li class="text-slate-500">Aucune activité enregistrée.</li>
        @endforelse
    </ul>
</section>
</div>
@endsection
