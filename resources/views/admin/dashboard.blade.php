@extends('layouts.admin')
@section('content')
<p class="lm-kicker">Réseau global</p>
<h1 class="mb-4 text-2xl font-semibold">Plateforme</h1>
<div class="lm-grid sm:grid-cols-2 xl:grid-cols-3">
    @foreach([
        'Entrepreneurs' => $metrics['tenants'],
        'Entrepreneurs actifs' => $metrics['tenants_active'],
        'WiFi Zones' => $metrics['zones'],
        'MikroTik' => $metrics['mikrotiks'],
        'Abonnements actifs' => $metrics['subscriptions_active'],
        'Abonnements expirés' => $metrics['subscriptions_expired'],
        'Revenus plateforme' => \App\Support\Money::format($metrics['platform_revenue']),
    ] as $label => $value)
        <x-ui.kpi :label="$label">{{ $value }}</x-ui.kpi>
    @endforeach
    <x-ui.kpi label="Clients">{{ $overview['clients'] }}</x-ui.kpi>
    <x-ui.kpi label="Routeurs connectés">{{ $overview['routers_online'] }}</x-ui.kpi>
    <x-ui.kpi label="Tickets">{{ $overview['tickets'] }}</x-ui.kpi>
    <x-ui.kpi label="Tickets vendus">{{ $overview['tickets_sold'] }}</x-ui.kpi>
    <x-ui.kpi label="Tickets actifs">{{ $overview['tickets_active'] }}</x-ui.kpi>
    <x-ui.kpi label="Tickets expirés">{{ $overview['tickets_expired'] }}</x-ui.kpi>
    <x-ui.kpi label="Ventes">{{ $overview['sales'] }}</x-ui.kpi>
    @forelse($overview['revenue'] as $currency)
        <x-ui.kpi :label="'Revenus '.$currency['currency']">{{ $currency['label'] }}</x-ui.kpi>
    @empty
        <x-ui.kpi label="Revenus">—</x-ui.kpi>
    @endforelse
</div>
<div class="mt-6 grid gap-4 lg:grid-cols-2">
    @foreach([
        'Croissance entrepreneurs' => $overview['entrepreneurs_by_month'],
        'Croissance clients' => $overview['clients_by_month'],
        'Tickets générés' => $overview['tickets_by_month'],
        'Tickets vendus par mois' => $overview['sales_by_month'],
        'Zones WiFi' => $overview['zones_by_month'],
        'Routeurs' => $overview['routers_by_month'],
    ] as $title => $series)
        <article class="rounded-2xl bg-white p-4">
            <h2 class="font-semibold">{{ $title }}</h2>
            @forelse($series as $month => $total)
                <p class="mt-2 text-sm">{{ $month }} · {{ $total }}</p>
            @empty
                <p class="mt-2 text-sm text-slate-500">Aucune donnée.</p>
            @endforelse
        </article>
    @endforeach
    <article class="rounded-2xl bg-white p-4">
        <h2 class="font-semibold">Activité plateforme</h2>
        @forelse($overview['activity'] as $item)
            <p class="mt-2 text-sm">{{ $item['text'] }} · {{ $item['at'] }}</p>
        @empty
            <p class="mt-2 text-sm text-slate-500">Aucune activité.</p>
        @endforelse
    </article>
</div>
@endsection
