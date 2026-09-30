@extends('layouts.client')
@section('heading', 'Accueil')
@section('content')
<h2 class="h4">Bonjour @if(auth()->user()->name !== 'Client'), {{ auth()->user()->name }}@endif</h2>
<p class="mb-3 text-secondary">{{ auth()->user()->client_phone }}</p>
@if($active->isNotEmpty())
    @php $lead = $active->first(); @endphp
    <section class="client-card client-hero mb-3">
        <p class="mb-1">Ticket actif</p>
        <h2 class="h5 mb-1">{{ $lead->plan->name ?? 'Forfait' }}</h2>
        <p class="mb-1">{{ $lead->wifiZone->name ?? 'Zone' }}</p>
        <p class="mb-1">Expiration {{ $lead->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
        <p class="mb-2">{{ $lead->statusLabel() }}</p>
        <div class="ticket-qr bg-white rounded-3 d-inline-block p-2">{!! \App\Support\QrCodes::svg(route('tickets.public', $lead->public_token)) !!}</div>
    </section>
@endif
<div class="client-grid mb-3">
    <article class="client-card"><strong>Tickets actifs</strong><span>{{ $active->count() }}</span></article>
    <article class="client-card"><strong>Tickets disponibles</strong><span>{{ $available->count() }}</span></article>
    <article class="client-card"><strong>Tickets expirés</strong><span>{{ $expired->count() }}</span></article>
    <article class="client-card"><strong>Dernier achat</strong><span>{{ $lastSale?->created_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? '—' }}</span></article>
</div>
<div class="client-grid mb-3">
    <article class="client-card"><a class="client-action" href="{{ route('client.tickets') }}"><strong>Mes tickets</strong><span>{{ $active->count() }} actif(s)</span></a></article>
    <article class="client-card"><a class="client-action" href="{{ route('client.buy') }}"><strong>Acheter</strong><span>Sans recommencer l’inscription</span></a></article>
    <article class="client-card"><a class="client-action" href="{{ route('client.tickets') }}#historique"><strong>Historique</strong><span>{{ $expired->count() }} expiré(s)</span></a></article>
    <article class="client-card"><a class="client-action" href="{{ route('client.profile') }}"><strong>Profil</strong><span>Téléphone et mot de passe</span></a></article>
</div>
<section class="mb-3">
    <h2 class="h6">Tickets actifs</h2>
    @forelse($active as $ticket)
        @include('client.ticket-card', ['reveal' => true])
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
