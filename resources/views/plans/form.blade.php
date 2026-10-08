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
                <label class="form-label mt-3" for="wifi_zone_id">WiFi Zone</label>
                <select class="form-select" id="wifi_zone_id" name="wifi_zone_id" data-plan-zone>
                    <option value="">Choisir une WiFi Zone</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" @selected((string) old('wifi_zone_id', $plan->wifi_zone_id ?: request('wifi_zone_id')) === (string) $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
                @if($zones->isEmpty())
                    <p class="form-text">Aucune WiFi Zone. Créez votre première zone WiFi pour commencer.</p>
                @endif
                <label class="form-label mt-3" for="mikrotik_profile">Profil MikroTik</label>
                @php
                    $selectedProfile = old('mikrotik_profile', $plan->mikrotik_profile ?: request('mikrotik_profile'));
                    $profileNames = $profiles->pluck('name');
                @endphp
                @if($profiles->isNotEmpty())
                    <select class="form-select" id="mikrotik_profile" name="mikrotik_profile" data-plan-profile>
                        <option value="">Choisir un profil du routeur</option>
                        @foreach($profiles as $profile)
                            <option value="{{ $profile->name }}" data-zone="{{ $profile->mikrotik?->wifi_zone_id }}" data-rate="{{ $profile->rate_limit }}" data-pool="{{ $profile->raw['address-pool'] ?? 'none' }}" data-queue="{{ $profile->raw['parent-queue'] ?? 'none' }}" data-session="{{ $profile->raw['session-timeout'] ?? '' }}" @selected($selectedProfile === $profile->name)>{{ $profile->name }}</option>
                        @endforeach
                        @if($selectedProfile && ! $profileNames->contains($selectedProfile))
                            <option value="{{ $selectedProfile }}" selected>Profil MikroTik indisponible — {{ $selectedProfile }}</option>
                        @endif
                    </select>
                    <p class="form-text" data-plan-tech>Les informations techniques viennent du routeur.</p>
                @else
                    <input class="form-control" id="mikrotik_profile" name="mikrotik_profile" value="{{ $selectedProfile }}" maxlength="80" placeholder="Synchronisez le routeur pour choisir un profil">
                    <p class="form-text">Aucun profil n’est encore lu sur le routeur. Le nom reste enregistré tel quel.</p>
                @endif
                <label class="form-label mt-3" for="price">Prix</label>
                <input class="form-control" id="price" type="number" min="0" step="1" name="price" value="{{ old('price', $plan->price) }}">
                <label class="form-label mt-3" for="selling_price">Prix de vente</label>
                <input class="form-control" id="selling_price" type="number" min="0" step="1" name="selling_price" value="{{ old('selling_price', $plan->selling_price) }}">
                <input type="hidden" name="selling_currency" value="{{ old('selling_currency', $plan->selling_currency ?: ($plan->currency ?: 'CDF')) }}">
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
                <label class="form-label mt-3" for="plan-status">Statut</label>
                <select class="form-select" id="plan-status" name="status">
                    <option value="active" @selected(old('status', $plan->status ?: 'active') === 'active')>Actif</option>
                    <option value="inactive" @selected(old('status', $plan->status) === 'inactive')>Inactif</option>
                </select>

                <details class="mt-4">
                    <summary>Paramètres avancés</summary>
                    <div class="mt-3">
                        <label class="form-label" for="currency">Devise</label>
                        <input class="form-control" id="currency" name="currency" value="{{ old('currency', $plan->currency ?: 'CDF') }}" maxlength="3">
                        <label class="form-label mt-3" for="badge">Badge</label>
                        <select class="form-select" id="badge" name="badge">
                            <option value="">Aucun</option>
                            <option value="populaire" @selected(old('badge', $plan->badge) === 'populaire')>Populaire</option>
                            <option value="meilleure_offre" @selected(old('badge', $plan->badge) === 'meilleure_offre')>Meilleure offre</option>
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
@push('scripts')
<script>
    var zone = document.querySelector('[data-plan-zone]');
    var profile = document.querySelector('[data-plan-profile]');
    var tech = document.querySelector('[data-plan-tech]');
    function showProfile() {
        if (!zone || !profile) { return; }
        var selected = zone.value;
        Array.prototype.forEach.call(profile.options, function (option) {
            var owner = option.getAttribute('data-zone');
            var visible = !owner || !selected || owner === selected;
            option.hidden = !visible;
            option.disabled = owner ? !visible : false;
        });
        if (profile.selectedOptions[0] && profile.selectedOptions[0].disabled) {
            profile.value = '';
        }
        var current = profile.selectedOptions[0];
        if (tech && current) {
            var bits = [];
            if (current.getAttribute('data-rate')) { bits.push('Débit : ' + current.getAttribute('data-rate')); }
            if (current.getAttribute('data-pool')) { bits.push('Pool : ' + current.getAttribute('data-pool')); }
            if (current.getAttribute('data-queue')) { bits.push('Queue : ' + current.getAttribute('data-queue')); }
            if (current.getAttribute('data-session')) { bits.push('Session : ' + current.getAttribute('data-session')); }
            tech.textContent = bits.length ? bits.join(' · ') : 'Les informations techniques viennent du routeur.';
        }
    }
    if (zone) {
        zone.addEventListener('change', showProfile);
        profile && profile.addEventListener('change', showProfile);
        showProfile();
    }
</script>
@endpush
