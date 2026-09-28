@extends('layouts.app')
@section('heading', $router->exists ? 'Modifier le MikroTik' : 'Ajouter un MikroTik')
@section('content')
<form method="POST" action="{{ $router->exists ? route('mikrotiks.update', $router) : route('mikrotiks.store') }}" class="min-w-0 space-y-3 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
    @csrf
    @if($router->exists) @method('PUT') @endif
    <label class="block text-sm font-semibold">Nom du routeur
        <input class="mt-1 w-full rounded-xl border px-3 py-3" name="name" value="{{ old('name', $router->name) }}" required>
    </label>
    <label class="block text-sm font-semibold">WiFi Zone
        <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="wifi_zone_id" required>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}" @selected(old('wifi_zone_id', $router->wifi_zone_id) == $zone->id)>{{ $zone->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-semibold">Adresse IP / Hostname
        <input class="mt-1 w-full rounded-xl border px-3 py-3" name="host" value="{{ old('host', $router->host) }}" required autocomplete="off">
    </label>
    <label class="block text-sm font-semibold">Port API
        <input class="mt-1 w-full rounded-xl border px-3 py-3" name="api_port" inputmode="numeric" value="{{ old('api_port', $router->api_port ?: 8728) }}" required>
    </label>
    <label class="block text-sm font-semibold">Username
        <input class="mt-1 w-full rounded-xl border px-3 py-3" name="username" value="{{ old('username', $router->username) }}" required autocomplete="off">
    </label>
    <label class="block text-sm font-semibold">Password
        <input class="mt-1 w-full rounded-xl border px-3 py-3" type="password" name="password" {{ $router->exists ? '' : 'required' }} autocomplete="new-password" placeholder="{{ $router->exists ? 'Laisser vide pour conserver' : '' }}">
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
</form>

@if(session('probe'))
    @php $probe = session('probe'); @endphp
    <section class="mt-4 min-w-0 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
        <p class="font-semibold">✓ MikroTik détecté</p>
        <dl class="mt-3 space-y-1">
            <div class="flex justify-between gap-3"><dt>Identity</dt><dd class="truncate">{{ $probe['identity'] ?: '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt>RouterOS</dt><dd>{{ $probe['version'] ?: '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt>Uptime</dt><dd>{{ $probe['uptime'] ?: '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt>CPU</dt><dd>{{ $probe['cpu'] !== null && $probe['cpu'] !== '' ? $probe['cpu'] : '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt>Mémoire</dt><dd>{{ $probe['memory'] ?: '—' }}</dd></div>
            @if(!empty($probe['architecture']))<div class="flex justify-between gap-3"><dt>Architecture</dt><dd>{{ $probe['architecture'] }}</dd></div>@endif
        </dl>
        <p class="mt-3">HotSpot détecté : {{ !empty($probe['hotspot']) ? 'Oui' : 'Non' }}</p>
        <p class="mt-2 font-semibold">Profils HotSpot</p>
        @if(($probe['user_profiles'] ?? []) === [])
            <p>Aucun profil utilisateur lu sur le routeur.</p>
        @else
            <ul class="mt-1 list-disc pl-5">
                @foreach($probe['user_profiles'] as $name)
                    <li>{{ $name }}</li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

@if(session('probe_error'))
    <section class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm">
        <p class="font-semibold">✗ Impossible de joindre le MikroTik</p>
        <p class="mt-2 break-words">{{ session('probe_error') }}</p>
    </section>
@endif

@if($router->exists)
<form method="POST" action="{{ route('mikrotiks.destroy', $router) }}" class="mt-3">@csrf @method('DELETE')<button class="rounded-xl border px-4 py-3 text-sm text-red-700">Supprimer</button></form>
@endif
@endsection
