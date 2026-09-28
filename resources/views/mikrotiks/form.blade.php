@extends('layouts.app')
@section('heading', $router->exists ? 'Modifier le MikroTik' : 'Nouveau MikroTik')
@section('content')
<form method="POST" action="{{ $router->exists ? route('mikrotiks.update', $router) : route('mikrotiks.store') }}" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
    @csrf
    @if($router->exists) @method('PUT') @endif
    <label class="block text-sm">Nom<input class="mt-1 w-full rounded-lg border px-3 py-2" name="name" value="{{ old('name', $router->name) }}" required></label>
    <label class="block text-sm">WiFi Zone
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="wifi_zone_id" required>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}" @selected(old('wifi_zone_id', $router->wifi_zone_id) == $zone->id)>{{ $zone->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">Adresse IP ou nom d’hôte<input class="mt-1 w-full rounded-lg border px-3 py-2" name="host" value="{{ old('host', $router->host) }}" required></label>
    <label class="block text-sm">Port API<input class="mt-1 w-full rounded-lg border px-3 py-2" name="api_port" value="{{ old('api_port', $router->api_port ?: 8728) }}" required></label>
    <label class="block text-sm">Utilisateur<input class="mt-1 w-full rounded-lg border px-3 py-2" name="username" value="{{ old('username', $router->username) }}" required></label>
    <label class="block text-sm">Mot de passe
        <input class="mt-1 w-full rounded-lg border px-3 py-2" type="password" name="password" {{ $router->exists ? '' : 'required' }} autocomplete="new-password" placeholder="{{ $router->exists ? 'Laisser vide pour conserver' : '' }}">
    </label>
    <p class="text-xs text-slate-500">Le mot de passe est chiffré et n’est jamais renvoyé dans la page.</p>
    <button class="rounded-xl bg-electric px-4 py-3 text-white">Enregistrer</button>
</form>
@if($router->exists)
<form method="POST" action="{{ route('mikrotiks.destroy', $router) }}" class="mt-3">@csrf @method('DELETE')<button class="text-sm text-red-700">Archiver</button></form>
@endif
@endsection
