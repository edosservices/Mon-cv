@extends('layouts.client')
@section('heading', 'Accueil')
@section('content')
<p class="mb-3">{{ auth()->user()->name !== 'Client' ? auth()->user()->name : auth()->user()->client_phone }}<br><span class="text-secondary">{{ auth()->user()->client_phone }}</span></p>
<div class="client-grid mb-3">
    <article class="client-card"><a class="client-action" href="{{ route('client.tickets') }}"><strong>Mes tickets</strong><span>{{ $active->count() }} actif(s)</span></a></article>
    <article class="client-card"><a class="client-action" href="{{ route('client.buy') }}"><strong>Acheter</strong><span>Sans recommencer l’inscription</span></a></article>
    <article class="client-card"><a class="client-action" href="{{ route('client.tickets') }}#historique"><strong>Historique</strong><span>{{ $expired->count() }} expiré(s)</span></a></article>
    <article class="client-card"><a class="client-action" href="{{ route('client.profile') }}"><strong>Profil</strong><span>Téléphone et mot de passe</span></a></article>
</div>
<section class="client-card p-3 mb-3">
    <h2 class="h6">Tickets actifs</h2>
    @forelse($active as $ticket)
        <p class="mb-1">{{ $ticket->wifiZone->name ?? 'Zone' }} · {{ $ticket->plan->name ?? 'Forfait' }} · {{ $ticket->username }}</p>
    @empty
        <p class="mb-0 text-secondary">Aucun ticket actif.</p>
    @endforelse
</section>
<section class="client-card p-3 mb-3" id="historique">
    <h2 class="h6">Achats récents</h2>
    @forelse($sales as $sale)
        <p class="mb-1">{{ $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y') }} · {{ $sale->items->first()?->plan?->name ?? 'Forfait' }} · {{ \App\Support\Money::format($sale->total_amount, $sale->currency) }}</p>
    @empty
        <p class="mb-0 text-secondary">Aucun achat pour ce numéro.</p>
    @endforelse
</section>
@if($zones->isNotEmpty())
    <section class="client-card p-3">
        <h2 class="h6">WiFi Zones</h2>
        @foreach($zones as $zone)
            <p class="mb-1"><a href="{{ route('shop.show', $zone->slug) }}">{{ $zone->name }}</a> @if($zone->location)<span class="text-secondary">· {{ $zone->location }}</span>@endif</p>
        @endforeach
    </section>
@endif
@endsection
