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

<header class="mb-4 min-w-0">
    <p class="text-lg font-semibold">Bonjour, {{ auth()->user()->name }}</p>
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

<section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Indicateurs">
    @foreach($report['kpis'] as $kpi)
        <article class="min-w-0 rounded-2xl bg-white p-4 shadow-sm">
            <p class="flex items-center justify-between gap-2 text-sm text-slate-500">
                <span>{{ $kpi['label'] }}</span>
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-sky-50 text-electric" aria-hidden="true">
                    @switch($kpi['label'])
                        @case("CA aujourd’hui") ▣ @break
                        @case('Ventes aujourd’hui') ≡ @break
                        @case('Tickets actifs') ▤ @break
                        @case('Clients') ● @break
                        @case('Utilisateurs connectés') ◉ @break
                        @default ! 
                    @endswitch
                </span>
            </p>
            <p class="mt-2 break-words text-2xl font-semibold">
                {{ $kpi['money'] ? \App\Support\Money::format($kpi['value']) : $kpi['value'] }}
            </p>
            @if($kpi['change'] !== null)
                <p class="mt-1 text-sm font-semibold {{ $kpi['change'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                    {{ $kpi['change'] >= 0 ? '▲' : '▼' }} {{ $kpi['change'] }} % vs hier
                </p>
            @endif
        </article>
    @endforeach
</section>

<section id="revenus" class="mt-6 min-w-0 rounded-2xl bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">Évolution du chiffre d'affaires</h2>
        <div class="flex flex-wrap gap-2">
            @foreach(['day' => 'Jour', 'week' => 'Semaine', 'month' => 'Mois'] as $value => $label)
                <a class="rounded-lg border px-3 py-2 text-sm {{ $filters['grain'] === $value ? 'border-electric text-electric' : '' }}" href="{{ route('dashboard', array_merge($query, ['grain' => $value])) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <p class="mt-2 text-sm text-slate-500">Période : {{ $filters['from']->timezone(config('app.timezone'))->format('d/m/Y') }} – {{ $filters['to']->timezone(config('app.timezone'))->format('d/m/Y') }} · {{ \App\Support\Money::format($report['period_revenue']) }}</p>
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
    <h2 class="font-semibold">Mes WiFi Zones</h2>
    <div class="mt-3 grid gap-3 md:grid-cols-2">
        @foreach($report['zone_rows'] as $zone)
            <a class="min-w-0 rounded-2xl bg-white p-4 shadow-sm" href="{{ route('dashboard', array_merge($query, ['zone' => $zone['id']])) }}">
                <p class="font-semibold">{{ $zone['name'] }}</p>
                <p class="truncate text-sm text-slate-500">{{ $zone['slug'] }} · {{ $zone['status'] === 'active' ? 'Active' : $zone['status'] }}</p>
                <p class="mt-2 text-sm">MikroTik : {{ $zone['router'] ?: 'Aucun' }}</p>
                <p class="text-sm">Utilisateurs actifs : {{ $zone['online'] }}</p>
                <p class="text-sm">Ventes aujourd’hui : {{ $zone['sales'] }}</p>
                <p class="text-sm">CA aujourd’hui : {{ \App\Support\Money::format($zone['revenue']) }}</p>
            </a>
        @endforeach
    </div>
</section>
@endif

<section id="mikrotik" class="mt-6">
    <h2 class="font-semibold">État du MikroTik</h2>
    <div class="mt-3 grid gap-3 md:grid-cols-2">
        @forelse($report['routers'] as $card)
            @php $router = $card['router']; @endphp
            <article class="min-w-0 rounded-2xl bg-white p-4 shadow-sm">
                <p class="font-semibold">{{ $router->name }}</p>
                <p class="mt-1 text-sm">
                    @if($router->status === 'online') 🟢 Connecté
                    @elseif($router->status === 'error') 🟠 Erreur
                    @elseif($router->status === 'offline') 🔴 Hors ligne
                    @else ⚪ Non vérifié @endif
                </p>
                @if(in_array($router->status, ['offline', 'error'], true))
                    <p class="mt-2 text-sm font-semibold">MikroTik hors ligne</p>
                @endif
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt>Identity</dt><dd class="truncate">{{ $router->identity ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Adresse</dt><dd class="truncate">{{ $router->host }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Utilisateurs actifs</dt><dd>{{ count($card['users']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Dernière vérification</dt><dd>{{ $router->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'pas encore vérifié' }}</dd></div>
                    @if(!empty($card['resource']['version']))<div class="flex justify-between gap-3"><dt>RouterOS</dt><dd>{{ $card['resource']['version'] }}</dd></div>@endif
                    @if(!empty($card['resource']['cpu-load']))<div class="flex justify-between gap-3"><dt>CPU</dt><dd>{{ $card['resource']['cpu-load'] }}</dd></div>@endif
                </dl>
                @if($router->last_error)<p class="mt-2 break-words text-sm text-amber-800">{{ $router->last_error }}</p>@endif
                <form class="mt-3" method="POST" action="{{ route('mikrotiks.test', $router) }}">
                    @csrf
                    <button class="rounded-xl border px-4 py-3 text-sm font-semibold">Tester la connexion</button>
                </form>
            </article>
        @empty
            <p class="text-sm text-slate-500">Aucun routeur relié.</p>
        @endforelse
    </div>
</section>

<section id="connectes" class="mt-6 min-w-0 rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="font-semibold">Utilisateurs actuellement connectés</h2>
    @if($report['sessions'] === [])
        <p class="mt-3 text-sm text-slate-500">
            @if(collect($report['routers'])->contains(fn ($card) => in_array($card['router']->status, ['offline', 'error'], true)))
                MikroTik hors ligne
            @else
                Aucun utilisateur connecté.
            @endif
        </p>
    @else
        <div class="mt-3 space-y-3">
            @foreach($report['sessions'] as $session)
                <article class="min-w-0 rounded-xl bg-slate-50 p-3 text-sm">
                    <p class="font-semibold">{{ $session['username'] }}</p>
                    <p>IP {{ $session['ip'] }} · connexion {{ $session['uptime'] }}</p>
                    <p>Forfait {{ $session['plan'] ?: 'Selon le ticket' }}</p>
                    <p>Début {{ $session['started']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
                    <p>Expiration {{ $session['expires']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
                    <p>Temps restant {{ $session['remaining'] ?: '—' }}</p>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section id="paiements" class="mt-6 min-w-0 rounded-2xl bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">État des paiements</h2>
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
    <h2 class="font-semibold">Performance des forfaits</h2>
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
    <h2 class="font-semibold">Heures de pointe</h2>
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
    <div class="mt-3 space-y-2">
        @forelse($report['customers']['rows'] as $customer)
            <article class="min-w-0 rounded-xl border p-3 text-sm">
                <p class="font-semibold">{{ $customer['name'] }}</p>
                <p class="break-all">{{ $customer['phone'] ?: '—' }} · {{ $customer['zone'] }}</p>
                <p>{{ $customer['purchases'] }} achat(s) · dernier {{ $customer['last_purchase'] ? \Illuminate\Support\Carbon::parse($customer['last_purchase'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}</p>
                <p>Dernière connexion {{ $customer['last_seen'] ? \Illuminate\Support\Carbon::parse($customer['last_seen'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}</p>
            </article>
        @empty
            <p class="text-sm text-slate-500">Aucun client.</p>
        @endforelse
    </div>
</section>

<section id="sync" class="mt-6 min-w-0 rounded-2xl border border-amber-200 bg-amber-50 p-4">
    <h2 class="font-semibold">Tickets nécessitant une synchronisation</h2>
    @if($report['unsynced']->isEmpty())
        <p class="mt-2 text-sm">Aucun ticket en attente de synchronisation.</p>
    @else
        <div class="mt-3 space-y-3">
            @foreach($report['unsynced'] as $voucher)
                <article class="min-w-0 rounded-xl bg-white p-3 text-sm">
                    <p class="font-semibold">{{ $voucher->username }} · {{ $voucher->plan->name ?? 'Forfait' }}</p>
                    <p>{{ $voucher->customer->phone ?? $voucher->customer->name ?? '—' }}</p>
                    <p>{{ $voucher->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                    <p class="break-words">{{ $voucher->sync_label }} @if($voucher->sync_error)· {{ $voucher->sync_error }}@endif</p>
                    <p>Tentatives : {{ $voucher->sync_attempts }}</p>
                    <form class="mt-2" method="POST" action="{{ route('vouchers.sync', $voucher) }}">
                        @csrf
                        <button class="rounded-xl bg-navy px-4 py-3 text-sm font-semibold text-white">Synchroniser</button>
                    </form>
                </article>
            @endforeach
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
    <h2 class="font-semibold">Activité récente</h2>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse($report['activity'] as $event)
            <li class="flex items-baseline justify-between gap-3"><span>{{ $event['label'] }}</span><time class="shrink-0 text-slate-500">{{ $event['at']?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time></li>
        @empty
            <li class="text-slate-500">Aucune activité enregistrée.</li>
        @endforelse
    </ul>
</section>
@endsection
