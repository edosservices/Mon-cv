@extends('layouts.client')
@section('heading', 'Acheter')
@section('content')
<h1 class="h4">Acheter sans compte</h1>
<p class="text-secondary">Choisissez une WiFi Zone. Le numéro et le nom restent facultatifs.</p>
@forelse($zones as $zone)
    <article class="client-card p-3 mb-3">
        <h2 class="h6 mb-1">{{ $zone->name }}</h2>
        <p class="text-secondary mb-2">{{ $zone->location ?: 'Zone WiFi' }}</p>
        <a class="btn client-btn" href="{{ route('shop.show', $zone->slug) }}">Voir les forfaits</a>
    </article>
@empty
    <div class="client-card p-3">Aucune zone disponible pour le moment.</div>
@endforelse
@endsection
