@extends('layouts.business')
@section('heading', 'Mes WiFi Zones')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h4 mb-0">Mes WiFi Zones</h2>
    @unless($first)
        <a class="btn biz-btn" href="{{ route('wifi-zones.create') }}">+ Nouvelle WiFi Zone</a>
    @endunless
</div>

@if($first)
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h3 class="h5">Crée ta première WiFi Zone</h3>
            <p class="text-secondary mb-3">Un nom et une adresse suffisent. Les réglages avancés restent masqués.</p>
            <a class="btn biz-btn" href="{{ route('wifi-zones.create') }}">Créer ma zone</a>
        </div>
    </section>
@else
    <div class="row g-3">
        @foreach($zones as $zone)
            <div class="col-12 col-md-6">
                <article class="card zone-card shadow-sm h-100">
                    <div class="card-body">
                        @php
                            $routers = $zone->mikrotiks;
                            $routerState = $routers->isEmpty()
                                ? 'pending'
                                : ($routers->contains(fn ($router) => $router->status === 'online')
                                    ? 'active'
                                    : ($routers->contains(fn ($router) => in_array($router->status, ['sync', 'syncing'], true)) ? 'sync' : 'offline'));
                            $routerLabel = match ($routerState) {
                                'active' => 'Routeur en ligne',
                                'sync' => 'Synchronisation',
                                'offline' => 'Hors ligne',
                                default => 'Routeur en attente',
                            };
                        @endphp
                        <div class="d-flex justify-content-between gap-2">
                            <h3 class="h5 mb-1">{{ $zone->name }}</h3>
                            <x-ui.badge :class="$zone->status === 'active' ? 'is-active' : 'is-inactive'">{{ $zone->status === 'active' ? 'Active' : 'Inactive' }}</x-ui.badge>
                        </div>
                        <p class="text-secondary mb-2">{{ $zone->location ?: 'Localisation non renseignée' }}</p>
                        <p class="mb-2">{{ $zone->plans()->count() }} forfaits · {{ $zone->vouchers_count }} tickets · {{ (int) ($clientCounts[$zone->id] ?? 0) }} clients</p>
                        <p class="mb-3"><x-ui.badge :class="'is-'.$routerState">{{ $routerLabel }}</x-ui.badge></p>
                        <div class="d-flex flex-wrap gap-2">
                            @if($zone->status === 'active')
                                <a class="btn btn-sm biz-btn" href="{{ route('shop.show', $zone->slug) }}" target="_blank" rel="noopener">Ouvrir</a>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('shop.show', $zone->slug) }}" target="_blank" rel="noopener">Voir ma boutique</a>
                                <button type="button" class="js-copy btn btn-sm btn-outline-secondary" data-url="{{ route('shop.show', $zone->slug) }}">Copier le lien</button>
                            @endif
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('wifi-zones.edit', $zone) }}">Modifier</a>
                            <form method="POST" action="{{ route('wifi-zones.status', $zone) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-outline-secondary">{{ $zone->status === 'active' ? 'Désactiver' : 'Activer' }}</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDelete" data-delete-action="{{ route('wifi-zones.destroy', $zone) }}" data-delete-title="Supprimer cette WiFi Zone ?">Supprimer</button>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('vouchers.generate', ['wifi_zone_id' => $zone->id]) }}">Générer des tickets</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('sales.quick', ['wifi_zone_id' => $zone->id]) }}">Vendre</a>
                        </div>
                    </div>
                </article>
            </div>
        @endforeach
    </div>
@endif
@endsection
