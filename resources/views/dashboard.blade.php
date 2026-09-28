@extends('layouts.app')
@section('heading', 'Tableau de bord')
@section('content')
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
