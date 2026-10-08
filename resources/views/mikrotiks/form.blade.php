@extends('layouts.app')
@section('heading', $router->exists ? 'Modifier le MikroTik' : 'Ajouter un MikroTik')
@section('content')
@if(! $router->exists)
    <p class="mb-4 text-sm text-slate-600">Connectez et configurez votre routeur MikroTik pour votre WiFi Zone.</p>
@endif
<form method="POST" action="{{ $router->exists ? route('mikrotiks.update', $router) : route('mikrotiks.store') }}" class="min-w-0 space-y-4">
    @csrf
    @if($router->exists) @method('PUT') @endif

    <fieldset class="min-w-0 space-y-3 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
        <legend class="px-1 text-sm font-semibold">Informations générales</legend>
        <label class="block text-sm font-semibold">Nom du MikroTik
            <input class="mt-1 w-full rounded-xl border px-3 py-3" name="name" value="{{ old('name', $router->name) }}" required>
        </label>
        <label class="block text-sm font-semibold">WiFi Zone
            <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="wifi_zone_id" required>
                @foreach($zones as $zone)
                    <option value="{{ $zone->id }}" @selected(old('wifi_zone_id', $router->wifi_zone_id) == $zone->id)>{{ $zone->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-semibold">Description
            <textarea class="mt-1 w-full rounded-xl border px-3 py-3" name="description" rows="2">{{ old('description', $router->description) }}</textarea>
        </label>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2 text-sm font-semibold">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $router->is_active ?? true))> Statut actif
        </label>
    </fieldset>

    <fieldset class="min-w-0 space-y-3 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
        <legend class="px-1 text-sm font-semibold">Connexion RouterOS</legend>
        <p class="text-sm text-slate-600">Le test de connexion est effectué depuis le serveur Limete WiFi. Une adresse privée comme 192.168.x.x peut être accessible depuis votre téléphone ou ordinateur connecté au MikroTik, mais inaccessible depuis le serveur.</p>
        <label class="block text-sm font-semibold">Host / Adresse IP
            <input class="mt-1 w-full rounded-xl border px-3 py-3" name="host" value="{{ old('host', $router->host) }}" required autocomplete="off">
        </label>
        <label class="block text-sm font-semibold">DNS / Hostname
            <input class="mt-1 w-full rounded-xl border px-3 py-3" name="dns" value="{{ old('dns', $router->dns) }}" placeholder="wifi.exemple.com" autocomplete="off">
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm font-semibold">Port API
                <input class="mt-1 w-full rounded-xl border px-3 py-3" name="api_port" inputmode="numeric" value="{{ old('api_port', $router->api_port ?: 8728) }}" required>
            </label>
            <label class="block text-sm font-semibold">Port API-SSL
                <input class="mt-1 w-full rounded-xl border px-3 py-3" name="api_ssl_port" inputmode="numeric" value="{{ old('api_ssl_port', $router->api_ssl_port ?: 8729) }}" required>
            </label>
        </div>
        <label class="block text-sm font-semibold">Type de connexion
            <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="connection_type">
                <option value="api" @selected(old('connection_type', $router->connection_type ?: 'api') === 'api')>API</option>
                <option value="api-ssl" @selected(old('connection_type', $router->connection_type) === 'api-ssl')>API-SSL</option>
            </select>
        </label>
        <label class="block text-sm font-semibold">Username
            <input class="mt-1 w-full rounded-xl border px-3 py-3" name="username" value="{{ old('username', $router->username) }}" required autocomplete="off">
        </label>
        <label class="block text-sm font-semibold">Password
            <input class="mt-1 w-full rounded-xl border px-3 py-3" type="password" name="password" value="" {{ $router->exists ? '' : 'required' }} autocomplete="new-password" placeholder="{{ $router->exists ? 'Laisser vide pour conserver' : '' }}">
        </label>
        <label class="block text-sm font-semibold">Timeout
            <input class="mt-1 w-full rounded-xl border px-3 py-3" name="timeout" inputmode="numeric" value="{{ old('timeout', $router->timeout ?: 5) }}">
        </label>
        <p class="text-xs text-slate-500">Le mot de passe est chiffré. Il n’est jamais renvoyé dans la page.</p>
        @if(! $router->exists)
            <div class="flex flex-col gap-2 sm:flex-row">
                <button class="rounded-xl border px-4 py-3 text-sm font-semibold" type="submit" formaction="{{ route('mikrotiks.probe') }}" formnovalidate>Tester la connexion</button>
                <button class="rounded-xl bg-electric px-4 py-3 text-sm font-semibold text-white">Ajouter le MikroTik</button>
            </div>
        @else
            <button class="rounded-xl bg-electric px-4 py-3 text-sm font-semibold text-white">Enregistrer</button>
        @endif
    </fieldset>
</form>

@if($router->exists)
    <form method="POST" action="{{ route('mikrotiks.test', $router) }}" class="mt-3">
        @csrf
        <button class="rounded-xl border bg-white px-4 py-3 text-sm font-semibold">Tester la connexion</button>
        <p class="mt-2 text-xs text-slate-500">Le test utilise l’adresse déjà enregistrée. Enregistrez d’abord une adresse modifiée.</p>
    </form>
@endif

@if(session('probe'))
    @include('mikrotiks.partials.detected', ['probe' => session('probe')])
@endif

@if(session('probe_error'))
    <section class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm">
        <p class="font-semibold">✗ {{ session('probe_error') }}</p>
        <p class="mt-2">Impossible de joindre le MikroTik depuis le serveur Limete WiFi.</p>
    </section>
@endif

@if($router->exists)
<form method="POST" action="{{ route('mikrotiks.destroy', $router) }}" class="mt-3">@csrf @method('DELETE')<button class="rounded-xl border px-4 py-3 text-sm text-red-700">Supprimer</button></form>
@endif
@endsection
