@extends('layouts.business')
@section('heading', 'Mon Business')
@section('content')
<form method="POST" action="{{ route('business.update') }}" enctype="multipart/form-data" data-loader class="row g-3">
    @csrf
    @method('PUT')

    <section class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Identité</h2>
                <label class="form-label mt-2" for="business-name">Nom du business</label>
                <input class="form-control" id="business-name" name="name" value="{{ old('name', $tenant->name) }}" required maxlength="160">
                <label class="form-label mt-3" for="slogan">Slogan <span class="text-secondary">(facultatif)</span></label>
                <input class="form-control" id="slogan" name="slogan" value="{{ old('slogan', $tenant->slogan) }}" maxlength="200">
                <div class="mt-3 lm-logo-drop" id="logo">
                    <label class="form-label" for="logo-file">Logo</label>
                    <p class="form-text mb-2">Ce logo apparaît sur vos tickets, la boutique et le compte entrepreneur.</p>
                    <input class="form-control" id="logo-file" type="file" name="logo" accept="image/png,image/jpeg,image/webp" data-logo-input="#logo-preview">
                    <p class="form-text">PNG, JPG, JPEG ou WEBP. 2 Mo maximum. Entre 32 et 4096 pixels.</p>
                    <img id="logo-preview" class="logo-preview mt-2 {{ $tenant->logoUrl() ? '' : 'd-none' }}" src="{{ $tenant->logoUrl() }}" alt="Aperçu du logo">
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h5">Contact</h2>
                <label class="form-label mt-2" for="phone">Téléphone</label>
                <input class="form-control" id="phone" name="phone" value="{{ old('phone', $tenant->phone) }}" maxlength="30">
                <label class="form-label mt-3" for="whatsapp">WhatsApp</label>
                <input class="form-control" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $tenant->whatsapp) }}" maxlength="30">
                <label class="form-label mt-3" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $tenant->email) }}" maxlength="160">
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h5">iKeePay</h2>
                <p class="text-secondary small">Clés de votre compte iKeePay uniquement. Elles ne sont jamais utilisées pour un autre entrepreneur, et la clé secrète n’est pas renvoyée au navigateur.</p>
                <label class="form-label mt-2" for="ikeepay_public_key">Clé publique</label>
                <input class="form-control" id="ikeepay_public_key" name="ikeepay_public_key" value="{{ old('ikeepay_public_key', $tenant->ikeepayPublicKey()) }}" maxlength="255" autocomplete="off">
                <label class="form-label mt-3" for="ikeepay_secret_key">Clé secrète</label>
                <input class="form-control" id="ikeepay_secret_key" name="ikeepay_secret_key" type="password" value="" maxlength="255" autocomplete="new-password" placeholder="{{ $tenant->ikeepaySecret() ? 'Clé enregistrée — laissez vide pour la conserver' : '' }}">
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h5">Adresse</h2>
                <label class="form-label mt-2" for="address">Adresse</label>
                <input class="form-control" id="address" name="address" value="{{ old('address', $tenant->address) }}" maxlength="255">
                <div class="row g-2 mt-1">
                    <div class="col-sm-6">
                        <label class="form-label" for="city">Ville</label>
                        <input class="form-control" id="city" name="city" value="{{ old('city', $tenant->city) }}" maxlength="120">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="country">Pays</label>
                        <input class="form-control" id="country" name="country" value="{{ old('country', $tenant->country) }}" maxlength="120">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3 lm-geo-card">
            <div class="card-body">
                <h2 class="h5">Localisation</h2>
                <p class="text-secondary small mb-3">La position n’est lue qu’après votre clic. Elle sert à situer le business.</p>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label" for="latitude">Latitude</label>
                        <input class="form-control" id="latitude" name="latitude" inputmode="decimal" value="{{ old('latitude', $tenant->latitude) }}">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="longitude">Longitude</label>
                        <input class="form-control" id="longitude" name="longitude" inputmode="decimal" value="{{ old('longitude', $tenant->longitude) }}">
                    </div>
                </div>
                <button type="button" class="btn btn-outline-primary mt-3" data-geo data-lat="latitude" data-lng="longitude">Utiliser ma position</button>
                <p class="form-text" data-geo-note></p>
                @include('partials.geo-map', ['lat' => old('latitude', $tenant->latitude), 'lng' => old('longitude', $tenant->longitude)])
            </div>
        </div>
    </section>

    <section class="col-12 col-lg-5">
        <aside class="lm-preview mb-3" data-business-preview style="--preview: {{ $tenant->brandColor() }}; --preview-2: {{ $tenant->secondaryColor() }}; --preview-btn: {{ $tenant->buttonColor() }};">
            <p class="mb-2">Voici à quoi votre business ressemble</p>
            <div class="d-flex align-items-center gap-2 mb-2">
                @if($tenant->logoUrl())
                    <img src="{{ $tenant->logoUrl() }}" alt="" width="48" height="48" class="rounded-3">
                @else
                    <span class="lm-mark" data-preview-mark>{{ mb_substr($tenant->name, 0, 1) }}</span>
                @endif
                <div>
                    <strong class="d-block" data-preview-name>{{ $tenant->name }}</strong>
                    <span data-preview-slogan>{{ $tenant->slogan }}</span>
                </div>
            </div>
            <p class="mb-1" data-preview-phone>{{ $tenant->phone }}</p>
            <p class="mb-0" data-preview-city>{{ trim(($tenant->city ?? '').' '.($tenant->country ?? '')) }}</p>
            <div class="preview-samples">
                <button type="button" data-preview-button>Bouton</button>
                <div class="sample">Ticket · {{ $tenant->name }}</div>
                <div class="sample">Carte WiFi · {{ $tenant->city ?: 'Zone' }}</div>
            </div>
        </aside>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Design</h2>
                <label class="form-label mt-2" for="primary_color">Couleur principale</label>
                <input class="form-control form-control-color" id="primary_color" type="color" name="primary_color" value="{{ old('primary_color', $tenant->primary_color ?: $tenant->brandColor()) }}">
                <label class="form-label mt-3" for="secondary_color">Couleur secondaire</label>
                <input class="form-control form-control-color" id="secondary_color" type="color" name="secondary_color" value="{{ old('secondary_color', $tenant->secondary_color ?: $tenant->secondaryColor()) }}">
                <label class="form-label mt-3" for="button_color">Couleur des boutons</label>
                <input class="form-control form-control-color" id="button_color" type="color" name="button_color" value="{{ old('button_color', $tenant->button_color ?: $tenant->buttonColor()) }}">
                <label class="form-label mt-3" for="ticket_style">Style des tickets</label>
                <select class="form-select" id="ticket_style" name="ticket_style">
                    <option value="">Moderne (par défaut)</option>
                    @foreach($templates as $key => $label)
                        <option value="{{ $key }}" @selected(old('ticket_style', $tenant->ticket_style) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="form-text">Logo, couleurs, téléphone et style sont repris sur le dashboard, la boutique, les tickets et les PDF.</p>
            </div>
        </div>
        <button class="btn biz-btn w-100 mt-3 py-2" data-bs-toggle="tooltip" title="Enregistre l’identité de votre business">Enregistrer</button>
    </section>
</form>
@endsection
