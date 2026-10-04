@extends('layouts.business')
@section('heading', 'Forfaits')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h4 mb-0">Mes forfaits</h2>
    <a class="btn biz-btn" href="{{ route('plans.create') }}">+ Créer un forfait</a>
</div>
<div class="row g-3">
    @forelse($plans as $plan)
        <div class="col-12 col-md-6">
            <article class="card plan-card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between gap-2">
                        <h3 class="h5 mb-1">{{ $plan->name }}</h3>
                        <span class="badge {{ $plan->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $plan->status === 'active' ? 'Actif' : 'Inactif' }}</span>
                    </div>
                    <p class="text-secondary mb-1">{{ $plan->durationLabel() }}</p>
                    <p class="lm-price mb-1">{{ \App\Support\Money::format($plan->price, $plan->currency) }}</p>
                    <p class="text-secondary mb-1">{{ $plan->validityLabel() }}</p>
                    <p class="mb-1">{{ $plan->unlimited_data ? 'Internet illimité' : 'Données limitées' }}</p>
                    @php
                        $zoneProfiles = $syncedProfiles->filter(fn ($profile) => (int) $profile->mikrotik?->wifi_zone_id === (int) $plan->wifi_zone_id);
                        $profileKnown = $zoneProfiles->contains(fn ($profile) => $profile->name === $plan->mikrotik_profile);
                    @endphp
                    @if($plan->mikrotik_profile && $zoneProfiles->isNotEmpty() && ! $profileKnown)
                        <p class="mb-1">Profil MikroTik indisponible</p>
                    @elseif($plan->mikrotik_profile)
                        <p class="mb-1">Profil MikroTik : {{ $plan->mikrotik_profile }}</p>
                    @else
                        <p class="mb-1">Profil MikroTik : non associé</p>
                    @endif
                    <p class="mb-3">{{ $plan->sold_count }} tickets vendus</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('plans.edit', $plan) }}">Modifier</a>
                        <form method="POST" action="{{ route('plans.duplicate', $plan) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">Dupliquer</button>
                        </form>
                        <form method="POST" action="{{ route('plans.status', $plan) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-sm btn-outline-secondary">{{ $plan->status === 'active' ? 'Désactiver' : 'Activer' }}</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDelete" data-delete-action="{{ route('plans.destroy', $plan) }}" data-delete-title="Supprimer ce forfait ?">Supprimer</button>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('vouchers.generate', ['plan_id' => $plan->id, 'wifi_zone_id' => $plan->wifi_zone_id]) }}">Générer des tickets</a>
                    </div>
                </div>
            </article>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="mb-2">Aucun forfait.</p>
                    <a class="btn biz-btn" href="{{ route('plans.create') }}">Créer un forfait</a>
                </div>
            </div>
        </div>
    @endforelse
</div>
@endsection
