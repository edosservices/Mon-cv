@extends('layouts.business')
@section('heading', 'Rapports')
@section('content')
@php
    $periods = ['today' => 'Aujourd’hui', '7d' => '7 jours', '30d' => '30 jours', 'custom' => 'Personnalisé'];
    $exportQuery = array_filter($query, fn ($value) => $value !== null && $value !== '');
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h4 mb-0">Rapports</h2>
    <div class="d-flex flex-wrap gap-2 no-print">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimer</button>
        <a class="btn btn-outline-secondary" href="{{ route('exports.sales.pdf', $exportQuery) }}">PDF</a>
        <a class="btn btn-outline-secondary" href="{{ route('exports.sales', $exportQuery) }}">CSV</a>
    </div>
</div>
<nav class="d-flex flex-wrap gap-2 mb-3 no-print" aria-label="Période">
    @foreach($periods as $value => $label)
        <a class="btn btn-sm {{ $filters['period'] === $value ? 'biz-btn' : 'btn-outline-secondary' }}" href="{{ route('reports.index', ['period' => $value]) }}">{{ $label }}</a>
    @endforeach
</nav>
@if($filters['period'] === 'custom')
    <form class="row g-2 mb-3 no-print" method="GET">
        <input type="hidden" name="period" value="custom">
        <div class="col-sm-4">
            <label class="form-label" for="from">Du</label>
            <input class="form-control" id="from" type="date" name="from" value="{{ $filters['from']->toDateString() }}">
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="to">Au</label>
            <input class="form-control" id="to" type="date" name="to" value="{{ $filters['to']->toDateString() }}">
        </div>
        <div class="col-sm-4 d-flex align-items-end">
            <button class="btn biz-btn">Appliquer</button>
        </div>
    </form>
@endif
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><article class="card border-0 shadow-sm"><div class="card-body"><p class="text-secondary mb-1">Ventes</p><strong>{{ $sales->total() }}</strong></div></article></div>
    <div class="col-6 col-lg-3"><article class="card border-0 shadow-sm"><div class="card-body"><p class="text-secondary mb-1">Revenus</p><strong>{{ \App\Support\Money::format($report['period_revenue']) }}</strong></div></article></div>
    <div class="col-6 col-lg-3"><article class="card border-0 shadow-sm"><div class="card-body"><p class="text-secondary mb-1">Tickets</p><strong>{{ collect($report['plans'])->sum('sold') }}</strong></div></article></div>
    <div class="col-6 col-lg-3"><article class="card border-0 shadow-sm"><div class="card-body"><p class="text-secondary mb-1">Zones</p><strong>{{ $report['zones']->count() }}</strong></div></article></div>
</div>
<section class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h3 class="h6">Forfaits</h3>
        @if($report['plans'] === [])
            <p class="mb-0 text-secondary">Aucun forfait.</p>
        @else
            <ul class="mb-0">
                @foreach($report['plans'] as $plan)
                    <li>{{ $plan['name'] }} · {{ $plan['sold'] }} ventes · {{ \App\Support\Money::format($plan['revenue'], $plan['currency']) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
<section class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h3 class="h6">Zones</h3>
        @forelse($report['zones'] as $zone)
            <p class="mb-1">{{ $zone->name }} · {{ $zone->status === 'active' ? 'Active' : 'Inactive' }}</p>
        @empty
            <p class="mb-0 text-secondary">Aucune zone.</p>
        @endforelse
    </div>
</section>
<section class="card border-0 shadow-sm">
    <div class="card-body">
        <h3 class="h6">Ventes</h3>
        @if($sales->isEmpty())
            <p class="mb-0 text-secondary">Aucune vente sur cette période.</p>
        @else
            <ul class="mb-0">
                @foreach($sales as $sale)
                    <li>{{ $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $sale->customer->name ?? $sale->customer->phone ?? 'Comptoir' }} · {{ \App\Support\Money::format($sale->total_amount, $sale->currency) }}</li>
                @endforeach
            </ul>
            <div class="mt-3 no-print">{{ $sales->links() }}</div>
        @endif
    </div>
</section>
@endsection
