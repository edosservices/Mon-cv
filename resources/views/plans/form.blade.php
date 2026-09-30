@extends('layouts.business')
@section('heading', $plan->exists ? 'Modifier le forfait' : 'Nouveau forfait')
@section('content')
@php
    $parts = $plan->durationParts();
    $durationValue = old('duration_value', $parts['value']);
    $durationUnit = old('duration_unit', $parts['unit']);
    $unitLabels = ['minutes' => 'MINUTES', 'hours' => 'HEURES', 'days' => 'JOURS'];
    $unitOne = ['minutes' => 'MINUTE', 'hours' => 'HEURE', 'days' => 'JOUR'];
@endphp
<form method="POST" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}" data-loader class="row g-3">
    @csrf
    @if($plan->exists) @method('PUT') @endif
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <label class="form-label" for="plan-name">Nom</label>
                <input class="form-control" id="plan-name" name="name" value="{{ old('name', $plan->name) }}" required maxlength="120">
                <label class="form-label mt-3" for="price">Prix</label>
                <input class="form-control" id="price" type="number" min="0" step="1" name="price" value="{{ old('price', $plan->price) }}">
                <div class="row g-2 mt-1">
                    <div class="col-7">
                        <label class="form-label" for="duration_value">Durée</label>
                        <input class="form-control" id="duration_value" type="number" min="1" name="duration_value" value="{{ $durationValue }}" required>
                    </div>
                    <div class="col-5">
                        <label class="form-label" for="duration_unit">Unité</label>
                        <select class="form-select" id="duration_unit" name="duration_unit">
                            <option value="minutes" @selected($durationUnit === 'minutes')>Minutes</option>
                            <option value="hours" @selected($durationUnit === 'hours')>Heures</option>
                            <option value="days" @selected($durationUnit === 'days')>Jours</option>
                        </select>
                    </div>
                </div>
                <p class="form-label mt-3 mb-1">Données</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="unlimited_data" value="1" id="unlimited_data" @checked(old('unlimited_data', $plan->exists ? $plan->unlimited_data : true))>
                    <label class="form-check-label" for="unlimited_data">Illimité</label>
                </div>
                <label class="form-label mt-3" for="plan-description">Description</label>
                <textarea class="form-control" id="plan-description" name="description" rows="3">{{ old('description', $plan->description) }}</textarea>

                <details class="mt-4">
                    <summary>Paramètres avancés</summary>
                    <div class="mt-3">
                        <label class="form-label" for="currency">Devise</label>
                        <input class="form-control" id="currency" name="currency" value="{{ old('currency', $plan->currency ?: 'CDF') }}" maxlength="3">
                        <label class="form-label mt-3" for="wifi_zone_id">WiFi Zone</label>
                        <select class="form-select" id="wifi_zone_id" name="wifi_zone_id">
                            <option value="">Toutes les zones</option>
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}" @selected(old('wifi_zone_id', $plan->wifi_zone_id) == $zone->id)>{{ $zone->name }}</option>
                            @endforeach
                        </select>
                        <label class="form-label mt-3" for="mikrotik_profile">Profil MikroTik</label>
                        <input class="form-control" id="mikrotik_profile" name="mikrotik_profile" value="{{ old('mikrotik_profile', $plan->mikrotik_profile) }}">
                        <label class="form-label mt-3" for="badge">Badge</label>
                        <select class="form-select" id="badge" name="badge">
                            <option value="">Aucun</option>
                            <option value="populaire" @selected(old('badge', $plan->badge) === 'populaire')>Populaire</option>
                            <option value="meilleure_offre" @selected(old('badge', $plan->badge) === 'meilleure_offre')>Meilleure offre</option>
                        </select>
                        <label class="form-label mt-3" for="plan-status">Statut</label>
                        <select class="form-select" id="plan-status" name="status">
                            <option value="active" @selected(old('status', $plan->status ?: 'active') === 'active')>Actif</option>
                            <option value="inactive" @selected(old('status', $plan->status) === 'inactive')>Inactif</option>
                        </select>
                    </div>
                </details>
                <button class="btn biz-btn mt-4">Enregistrer</button>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <aside class="preview-card p-4" data-plan-preview aria-live="polite">
            <p class="duration" data-preview-duration>{{ $durationValue }} {{ (int) $durationValue > 1 ? ($unitLabels[$durationUnit] ?? 'HEURES') : ($unitOne[$durationUnit] ?? 'HEURE') }}</p>
            <p class="price mt-2" data-preview-price>{{ $plan->price !== null && $plan->price !== '' ? \App\Support\Money::format($plan->price, 'CDF') : 'Prix à définir' }}</p>
            <p class="mt-2" data-preview-data>{{ old('unlimited_data', $plan->exists ? $plan->unlimited_data : true) ? 'Internet illimité' : 'Données selon description' }}</p>
        </aside>
    </div>
</form>
@endsection
