@extends('layouts.business')
@section('heading', $first ? 'Crée ta première WiFi Zone' : ($zone->exists ? 'Modifier la zone' : 'Nouvelle WiFi Zone'))
@section('content')
@if($first)
    <p class="text-secondary">Étape simple : le nom, puis l’endroit. Le routeur pourra être ajouté plus tard.</p>
@endif
<form method="POST" action="{{ $zone->exists ? route('wifi-zones.update', $zone) : route('wifi-zones.store') }}" enctype="multipart/form-data" data-loader class="card border-0 shadow-sm">
    @csrf
    @if($zone->exists) @method('PUT') @endif
    <div class="card-body">
        <label class="form-label" for="zone-name">Nom</label>
        <input class="form-control" id="zone-name" name="name" value="{{ old('name', $zone->name) }}" required maxlength="160">
        <label class="form-label mt-3" for="location">Adresse / localisation</label>
        <input class="form-control" id="location" name="location" value="{{ old('location', $zone->location) }}" maxlength="255">
        <div class="row g-2 mt-1">
            <div class="col-sm-6">
                <label class="form-label" for="zone-latitude">Latitude</label>
                <input class="form-control" id="zone-latitude" name="latitude" inputmode="decimal" value="{{ old('latitude', $zone->latitude) }}">
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="zone-longitude">Longitude</label>
                <input class="form-control" id="zone-longitude" name="longitude" inputmode="decimal" value="{{ old('longitude', $zone->longitude) }}">
            </div>
        </div>
        <button type="button" class="btn btn-outline-secondary mt-3" data-geo data-lat="latitude" data-lng="longitude">Utiliser ma position</button>
        <p class="form-text" data-geo-note></p>
        @unless($zone->exists)
            <input type="hidden" name="status" value="active">
        @endunless

        <details class="mt-4">
            <summary>Paramètres avancés</summary>
            <div class="mt-3">
                <label class="form-label" for="display_name">Nom affiché</label>
                <input class="form-control" id="display_name" name="display_name" value="{{ old('display_name', $zone->display_name) }}">
                <label class="form-label mt-3" for="zone-slogan">Slogan</label>
                <input class="form-control" id="zone-slogan" name="slogan" value="{{ old('slogan', $zone->slogan) }}">
                <label class="form-label mt-3" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $zone->description) }}</textarea>
                <label class="form-label mt-3" for="zone-phone">Téléphone</label>
                <input class="form-control" id="zone-phone" name="phone" value="{{ old('phone', $zone->phone) }}">
                <label class="form-label mt-3" for="zone-whatsapp">WhatsApp</label>
                <input class="form-control" id="zone-whatsapp" name="whatsapp" value="{{ old('whatsapp', $zone->whatsapp) }}">
                <label class="form-label mt-3" for="zone-email">Email</label>
                <input class="form-control" id="zone-email" type="email" name="email" value="{{ old('email', $zone->email) }}">
                <label class="form-label mt-3" for="zone-primary">Couleur principale</label>
                <input class="form-control" id="zone-primary" name="primary_color" value="{{ old('primary_color', $zone->primary_color) }}" placeholder="Reprend la couleur du business">
                <label class="form-label mt-3" for="zone-secondary">Couleur secondaire</label>
                <input class="form-control" id="zone-secondary" name="secondary_color" value="{{ old('secondary_color', $zone->secondary_color) }}">
                <label class="form-label mt-3" for="zone-logo">Logo de la zone</label>
                <input class="form-control" id="zone-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
                <label class="form-label mt-3" for="banner">Bannière</label>
                <input class="form-control" id="banner" type="file" name="banner" accept="image/jpeg,image/png,image/webp,image/gif">
                @if($zone->exists)
                    <label class="form-label mt-3" for="status">Statut</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" @selected(old('status', $zone->status ?: 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $zone->status) === 'inactive')>Inactive</option>
                    </select>
                @endif
            </div>
        </details>
        <button class="btn biz-btn mt-4">{{ $first ? 'Créer ma zone' : 'Enregistrer' }}</button>
    </div>
</form>
@endsection
