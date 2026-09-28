@extends('layouts.app')
@section('heading', 'Tableau de bord')
@section('content')
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
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    @foreach([
        'MikroTik' => $metrics['mikrotiks_online'].' / '.$metrics['mikrotiks_total'].' connectés',
        'Tickets actifs' => $metrics['active_customers'],
        'Tickets vendus aujourd’hui' => $metrics['tickets_today'],
        'CA aujourd’hui' => \App\Support\Money::format($metrics['revenue_today']),
        'CA cette semaine' => \App\Support\Money::format($metrics['revenue_week']),
        'CA ce mois' => \App\Support\Money::format($metrics['revenue_month']),
        'WiFi Zones' => $metrics['zones'],
        'Sessions ouvertes' => $metrics['open_sessions'],
    ] as $label => $value)
        <article class="rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ $value }}</p>
        </article>
    @endforeach
</div>
<section class="mt-6 rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="font-semibold">Ventes par forfait</h2>
    <ul class="mt-3 space-y-2">
        @forelse($metrics['sales_by_plan'] as $name => $total)
            <li class="flex items-center justify-between gap-3 text-sm">
                <span>{{ $name }}</span>
                <span class="font-semibold">{{ $total }}</span>
            </li>
        @empty
            <li class="text-sm text-slate-500">Aucune vente pour le moment.</li>
        @endforelse
    </ul>
</section>
@endsection
