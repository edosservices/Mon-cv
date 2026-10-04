@php
    $tenant = auth()->user()->tenant;
    $place = trim(implode(' · ', array_filter([$tenant?->city, $tenant?->country])));
    $tones = ['blue', 'green', 'amber', 'navy', 'red', 'blue'];
    $revenueSeries = collect($report['series'])->map(fn ($row) => [
        'label' => $row['label'],
        'amount' => (float) $row['amount'],
    ])->values();
    $paymentSlices = collect($report['payments'])->map(fn ($row) => [
        'label' => $row['label'],
        'total' => (int) $row['total'],
    ])->values();
    $planBars = collect($report['plans'])->map(fn ($row) => [
        'label' => $row['name'],
        'sold' => (int) $row['sold'],
    ])->values();
    $hourBars = collect($report['hours'])->map(fn ($row) => [
        'label' => $row['label'],
        'total' => (int) $row['total'],
    ])->values();
    $pulseCards = [
        ['label' => 'Chiffre d’affaires du mois', 'value' => \App\Support\Money::format($pulse['month']), 'money' => true, 'tone' => 'blue'],
        ['label' => 'Tickets vendus', 'value' => $pulse['sold'], 'count' => (int) $pulse['sold'], 'tone' => 'green'],
        ['label' => 'Tickets disponibles', 'value' => $pulse['available'], 'count' => (int) $pulse['available'], 'tone' => 'amber'],
        ['label' => 'Sessions actives', 'value' => $pulse['sessions'], 'count' => (int) $pulse['sessions'], 'tone' => 'navy'],
    ];
@endphp

<header class="card border-0 en-hero en-rise">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div class="min-w-0">
                <p class="en-kicker">Mon espace entrepreneur</p>
                <h2 class="en-hello">Bonjour, {{ auth()->user()->name }}</h2>
                <p class="en-place mb-0"><strong>{{ $tenant?->name ?: 'Mon entreprise' }}</strong>@if($place !== '') · {{ $place }}@endif</p>
            </div>
            <form method="GET" class="en-filters d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label" for="dash-zone">WiFi Zone</label>
                    <select class="form-select" id="dash-zone" name="zone" onchange="this.form.submit()">
                        <option value="">Toutes les zones</option>
                        @foreach($report['zones'] as $zone)
                            <option value="{{ $zone->id }}" @selected($filters['zone_id'] === $zone->id)>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="dash-period">Période</label>
                    <select class="form-select" id="dash-period" name="period" onchange="this.form.submit()">
                        @foreach($periods as $value => $label)
                            <option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($filters['period'] === 'custom')
                    <div>
                        <label class="form-label" for="dash-from">Du</label>
                        <input class="form-control" id="dash-from" type="date" name="from" value="{{ $filters['from']->toDateString() }}">
                    </div>
                    <div>
                        <label class="form-label" for="dash-to">Au</label>
                        <input class="form-control" id="dash-to" type="date" name="to" value="{{ $filters['to']->toDateString() }}">
                    </div>
                    <button class="btn btn-primary" type="submit">Appliquer</button>
                @endif
                <input type="hidden" name="grain" value="{{ $filters['grain'] }}">
            </form>
        </div>
        <nav class="en-dock" aria-label="Actions rapides">
            @if(auth()->user()->hasPermission('vouchers.manage'))
                <a class="btn btn-primary" href="{{ route('vouchers.generate') }}" data-bs-toggle="tooltip" title="Générer des tickets">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Générer des tickets
                    <span class="visually-hidden">+ Générer des tickets</span>
                </a>
            @endif
            @if(auth()->user()->hasPermission('sales.confirm'))
                <a class="btn btn-outline-primary" href="{{ route('sales.quick') }}" data-bs-toggle="tooltip" title="+ Vendre un ticket">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v10H4zM8 7V5h8v2"/></svg>
                    Vendre
                    <span class="visually-hidden">+ Vendre un ticket</span>
                </a>
            @endif
            @if(auth()->user()->hasPermission('vouchers.manage'))
                <a class="btn btn-outline-primary" href="{{ route('vouchers.index') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>
                    Tickets
                </a>
            @endif
            @if(auth()->user()->hasPermission('plans.manage'))
                <a class="btn btn-outline-primary" href="{{ route('plans.create') }}" data-bs-toggle="tooltip" title="+ Créer un forfait">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v12H4zM8 7V4h8v3"/></svg>
                    Forfait
                    <span class="visually-hidden">+ Créer un forfait</span>
                </a>
            @endif
            @if(auth()->user()->hasPermission('zones.manage'))
                <a class="btn btn-outline-primary" href="{{ route('wifi-zones.create') }}" data-bs-toggle="tooltip" title="+ Ajouter une WiFi Zone">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12a7 7 0 0 1 14 0M8 12a4 4 0 0 1 8 0M12 12h.01M4 18h16"/></svg>
                    Zone
                    <span class="visually-hidden">+ Ajouter une WiFi Zone</span>
                </a>
            @endif
            @if(auth()->user()->hasPermission('mikrotiks.manage'))
                <a class="btn btn-outline-secondary" href="{{ route('mikrotiks.assistant') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16v8H4zM8 8V6M16 8V6M8 16v2M16 16v2"/></svg>
                    MikroTik
                </a>
            @endif
            @if(auth()->user()->hasPermission('sales.view'))
                <a class="btn btn-outline-secondary" href="{{ route('sales.index', $query) }}">Ventes</a>
                <a class="btn btn-outline-secondary" href="{{ route('reports.index') }}">Mes Rapports</a>
            @endif
            @if(auth()->user()->hasPermission('settings.manage'))
                <a class="btn btn-outline-secondary" href="{{ route('business.edit') }}">Mon Business</a>
            @endif
        </nav>
    </div>
</header>

@if($notices->isNotEmpty())
<section aria-label="Notifications">
    @foreach($notices as $notice)
        <a class="alert alert-warning d-block mb-2" href="{{ route('notifications.index') }}">
            <strong>{{ $notice->data['title'] ?? 'Notification' }}</strong>
            <span class="d-block">{{ $notice->data['body'] ?? '' }}</span>
        </a>
    @endforeach
</section>
@endif

@if($shopZones->isNotEmpty())
<section class="card border-0 en-shop text-white" style="background: var(--en-navy, #071e3d);">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <p class="mb-0 fw-semibold">Boutique publique</p>
        <div class="d-flex flex-wrap gap-2">
            @foreach($shopZones as $zone)
                <a class="btn btn-light btn-sm" href="{{ route('shop.show', $zone->slug) }}" target="_blank" rel="noopener">Voir ma boutique</a>
                <button type="button" class="js-copy btn btn-outline-light btn-sm" data-url="{{ route('shop.show', $zone->slug) }}">Copier le lien</button>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="row g-3" aria-label="Indicateurs">
    @foreach($report['kpis'] as $index => $kpi)
        <div class="col-6 col-xl-3">
            <article class="card border-0 en-kpi" style="--i: {{ $index }}; --tone: var(--en-{{ $tones[$index % count($tones)] }});">
                <div class="card-body">
                    <p class="en-kpi-label">{{ $kpi['label'] }}</p>
                    <p class="en-kpi-value" @if(! $kpi['money'] && is_numeric($kpi['value'])) data-count="{{ (int) $kpi['value'] }}" @endif>
                        {{ $kpi['money'] ? \App\Support\Money::format($kpi['value']) : $kpi['value'] }}
                    </p>
                    @if($kpi['change'] !== null)
                        <span class="badge rounded-pill {{ $kpi['change'] >= 0 ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ $kpi['change'] >= 0 ? '▲' : '▼' }} {{ $kpi['change'] }} % vs hier
                        </span>
                    @endif
                </div>
            </article>
        </div>
    @endforeach
    @foreach($pulseCards as $index => $card)
        <div class="col-6 col-xl-3">
            <article class="card border-0 en-kpi" style="--i: {{ count($report['kpis']) + $index }}; --tone: var(--en-{{ $card['tone'] }});">
                <div class="card-body">
                    <p class="en-kpi-label">{{ $card['label'] }}</p>
                    <p class="en-kpi-value" @isset($card['count']) data-count="{{ $card['count'] }}" @endisset>{{ $card['value'] }}</p>
                </div>
            </article>
        </div>
    @endforeach
</section>

<section class="row g-3">
    <div class="col-lg-8" id="revenus">
        <article class="card border-0 en-chart-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h2>Revenus</h2>
                        <p class="en-figure">{{ \App\Support\Money::format($report['period_revenue']) }}</p>
                        <p class="text-secondary small mb-0">{{ $filters['from']->timezone(config('app.timezone'))->format('d/m/Y') }} – {{ $filters['to']->timezone(config('app.timezone'))->format('d/m/Y') }}</p>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Granularité">
                        @foreach(['day' => 'Aujourd’hui', 'week' => 'Semaine', 'month' => 'Mois'] as $value => $label)
                            <a class="btn {{ $filters['grain'] === $value ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('dashboard', array_merge($query, ['grain' => $value])) }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                @if($revenueSeries->isEmpty())
                    <p class="text-secondary mt-3 mb-0">Pas encore de vente confirmée sur cette période.</p>
                @else
                    <div class="en-chart">
                        <canvas data-en-chart="revenue" data-en-source="en-revenue" aria-label="Évolution du chiffre d'affaires"></canvas>
                    </div>
                @endif
            </div>
        </article>
    </div>
    <div class="col-lg-4" id="paiements">
        <article class="card border-0 en-chart-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <h2>Paiements</h2>
                    <a class="small fw-semibold" href="{{ route('exports.payments', $query) }}">Exporter CSV</a>
                </div>
                @if(collect($report['payments'])->sum('total') > 0)
                    <div class="en-chart is-short">
                        <canvas data-en-chart="payments" data-en-source="en-payments" aria-label="Répartition des paiements"></canvas>
                    </div>
                @endif
                <ul class="en-legend">
                    @foreach($report['payments'] as $state)
                        <li>
                            {{ $state['label'] }} · {{ $state['total'] }}
                            @if($state['status'] === 'pending' && $state['total'] > 0)
                                · Montant en attente {{ \App\Support\Money::format($state['amount']) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </article>
    </div>
</section>

<section class="row g-3">
    <div class="col-lg-7" id="forfaits">
        <article class="card border-0 en-chart-card">
            <div class="card-body">
                <h2>Forfaits</h2>
                @if($planBars->isEmpty())
                    <p class="text-secondary mt-3 mb-0">Aucun forfait.</p>
                @else
                    <div class="en-chart">
                        <canvas data-en-chart="plans" data-en-source="en-plans" aria-label="Tickets vendus par forfait"></canvas>
                    </div>
                    <ul class="en-legend">
                        @foreach($report['plans'] as $plan)
                            <li>{{ $plan['name'] }} · {{ $plan['sold'] }} · {{ \App\Support\Money::format($plan['revenue'], $plan['currency']) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </article>
    </div>
    <div class="col-lg-5" id="heures">
        <article class="card border-0 en-chart-card">
            <div class="card-body">
                <h2>Heures</h2>
                @if($hourBars->isEmpty())
                    <p class="text-secondary mt-3 mb-0">Pas assez de données</p>
                @else
                    <div class="en-chart">
                        <canvas data-en-chart="hours" data-en-source="en-hours" aria-label="Ventes par heure"></canvas>
                    </div>
                @endif
            </div>
        </article>
    </div>
</section>

<script type="application/json" id="en-revenue">@json($revenueSeries)</script>
<script type="application/json" id="en-payments">@json($paymentSlices)</script>
<script type="application/json" id="en-plans">@json($planBars)</script>
<script type="application/json" id="en-hours">@json($hourBars)</script>
