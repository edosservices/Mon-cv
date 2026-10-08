@extends('layouts.app')
@section('heading', $router->name)
@section('content')
@php
    $details = $router->details ?? [];
    $missing = function ($value) {
        return ($value === null || $value === '' || $value === []) ? 'Non détecté' : $value;
    };
    $dns = $router->dns ?: ($details['dns_name'] ?? null);
    $portalUrl = filled($dns) ? 'https://'.$dns.'/login' : null;
    $shop = $router->wifiZone ? route('shop.show', $router->wifiZone->slug) : null;
    $server = collect($details['hotspot_servers'] ?? [])->firstWhere('name', $router->hotspot_server) ?? ($details['hotspot_servers'][0] ?? null);
    $tabs = [
        'overview' => 'Vue générale',
        'connection' => 'Connexion',
        'hotspot' => 'HotSpot',
        'profiles' => 'Profils',
        'plans' => 'Forfaits',
        'users' => 'Utilisateurs',
        'sessions' => 'Sessions',
        'interfaces' => 'Interfaces',
        'ip' => 'IP',
        'portal' => 'Portail',
        'prepare' => 'Préparer',
        'journal' => 'Journal',
    ];
@endphp
<p class="mb-3 text-sm">
    @if($router->status === 'online') ● CONNECTÉ
    @elseif($router->status === 'error') ● ERREUR
    @elseif($router->status === 'unknown') ● UNKNOWN
    @else ● HORS LIGNE @endif
    <span class="text-slate-500">Dernière vérification {{ $router->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'pas encore vérifié' }}</span>
</p>
@if($router->last_error)<p class="mb-3 break-words text-sm text-amber-800">{{ $router->last_error }}</p>@endif
<div class="mb-4 flex flex-wrap gap-2 text-sm">
    <form method="POST" action="{{ route('mikrotiks.test', $router) }}">@csrf<button class="rounded-xl border bg-white px-4 py-3 font-semibold">Tester la connexion</button></form>
    <form method="POST" action="{{ route('mikrotiks.sync', $router) }}">@csrf<button class="rounded-xl border bg-white px-4 py-3 font-semibold">Synchroniser maintenant</button></form>
    <form method="POST" action="{{ route('mikrotiks.pending', $router) }}">@csrf<button class="rounded-xl border bg-white px-4 py-3 font-semibold">Synchroniser les tickets en attente</button></form>
    <a class="rounded-xl border bg-white px-4 py-3 font-semibold" href="{{ route('mikrotiks.edit', $router) }}">Modifier</a>
</div>
<nav class="mb-4 flex gap-2 overflow-x-auto text-sm">
    @foreach($tabs as $key => $label)
        <a class="shrink-0 rounded-full px-3 py-2 {{ $tab === $key ? 'bg-navy text-white' : 'bg-white' }}" href="{{ route('mikrotiks.show', $router) }}?tab={{ $key }}">{{ $label }}</a>
    @endforeach
</nav>

@if($tab === 'overview')
    <section class="grid gap-3 sm:grid-cols-2">
        <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
            <h2 class="font-semibold">{{ $router->name }}</h2>
            <p class="mt-2">Identity {{ $missing($router->identity) }}</p>
            <p>RouterOS {{ $missing($router->routeros_version) }}</p>
            <p>Ce routeur est associé à :</p>
            <p>WiFi Zone : {{ $router->wifiZone->name ?? 'Non détecté' }}</p>
            <p>Zone {{ $router->wifiZone->name ?? 'Non détecté' }}</p>
            <p>HotSpot {{ $missing($router->hotspot_server) }}</p>
            <p>DNS {{ filled($dns) ? $dns : 'DNS non configuré' }}</p>
            <p>Utilisateurs {{ $details['hotspot_users'] ?? 'Non détecté' }}</p>
            <p>Actifs {{ $details['active_users'] ?? 'Non détecté' }}</p>
        </article>
        <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
            <h2 class="font-semibold">ROUTEROS</h2>
            <p class="mt-2">Identity {{ $missing($router->identity) }}</p>
            <p>Version {{ $missing($router->routeros_version) }}</p>
            <p>Architecture {{ $missing($router->architecture ?: ($details['architecture'] ?? null)) }}</p>
            <p>Board {{ $missing($router->board ?: ($details['board'] ?? null)) }}</p>
            <p>Uptime {{ $missing($details['uptime'] ?? null) }}</p>
            <p>CPU {{ isset($details['cpu']) && $details['cpu'] !== '' ? $details['cpu'].' %' : 'Non détecté' }}</p>
            <p>Mémoire {{ isset($details['memory_percent']) ? $details['memory_percent'].' %' : $missing($details['memory'] ?? null) }}</p>
            <p>Stockage {{ $missing($details['disk'] ?? null) }}</p>
        </article>
    </section>
@endif

@if($tab === 'connection')
    <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Connexion RouterOS</h2>
        <p class="mt-2 break-all">Host {{ $router->host }}</p>
        <p>Port API {{ $router->api_port }}</p>
        <p>Port API-SSL {{ $router->api_ssl_port ?: 8729 }}</p>
        <p>Type {{ $router->connection_type === 'api-ssl' ? 'API-SSL' : 'API' }}</p>
        <p>Username {{ $router->username }}</p>
        <p>Password enregistré, jamais affiché</p>
        <p>Timeout {{ $router->timeout ?: 5 }} s</p>
        <p>Statut routeur {{ $router->is_active ? 'actif' : 'inactif' }}</p>
        <p class="mt-2">Dernière vérification {{ $router->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'pas encore vérifié' }}</p>
        @if($router->last_error)<p class="mt-1 break-words text-amber-800">{{ $router->last_error }}</p>@endif
    </article>
@endif

@if($tab === 'hotspot')
    <section class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Configuration HotSpot</h2>
        @if(($details['hotspot_servers'] ?? []) === [])
            <p class="mt-2">Non détecté</p>
        @else
            <form method="POST" action="{{ route('mikrotiks.hotspot', $router) }}" class="mt-3 space-y-2">
                @csrf
                <label class="block font-semibold">HotSpot Server
                    <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="hotspot_server">
                        @foreach($details['hotspot_servers'] as $item)
                            <option value="{{ $item['name'] ?? '' }}" @selected(($router->hotspot_server ?: ($server['name'] ?? null)) === ($item['name'] ?? null))>{{ $item['name'] ?? 'Non détecté' }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-xl border px-3 py-2">Utiliser ce HotSpot</button>
            </form>
        @endif
        <dl class="mt-4 space-y-1">
            <div class="flex justify-between gap-3"><dt>Interface</dt><dd>{{ $missing(is_array($server) ? ($server['interface'] ?? null) : null) }}</dd></div>
            <div class="flex justify-between gap-3"><dt>HotSpot Profile</dt><dd>{{ $missing(is_array($server) ? ($server['profile'] ?? null) : null) }}</dd></div>
            <div class="flex justify-between gap-3"><dt>Address Pool</dt><dd>{{ $missing(is_array($server) ? ($server['address-pool'] ?? null) : null) }}</dd></div>
            <div class="flex justify-between gap-3"><dt>DNS Name</dt><dd>{{ filled($dns) ? $dns : 'DNS non configuré' }}</dd></div>
            <div class="flex justify-between gap-3"><dt>Login URL</dt><dd class="truncate">{{ $portalUrl ?: 'DNS non configuré' }}</dd></div>
            <div class="flex justify-between gap-3"><dt>IP locale du HotSpot</dt><dd class="truncate">{{ $missing(is_array($server) ? ($server['addresses'] ?? null) : null) }}</dd></div>
            <div class="flex justify-between gap-3"><dt>Réseau client</dt><dd class="truncate">{{ $missing(data_get($details, 'pools.0.ranges')) }}</dd></div>
        </dl>
    </section>
@endif

@if($tab === 'profiles')
    <section class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Profils HotSpot</h2>
            <form method="POST" action="{{ route('mikrotiks.profiles', $router) }}">@csrf<button class="rounded-xl border px-3 py-2">Synchroniser les profils</button></form>
        </div>
        <p class="mt-1 text-slate-500">Profils MikroTik disponibles. Un forfait Laravel n’est pas un profil RouterOS.</p>
        @forelse($router->profiles as $profile)
            @php
                $raw = $profile->raw ?? [];
                $linkedPlan = $plans->first(fn ($plan) => $plan->mikrotik_profile === $profile->name && $plan->status === 'active');
            @endphp
            <article class="mt-3 rounded-xl bg-slate-50 p-3">
                <p class="font-semibold">{{ $profile->name }}</p>
                <p>Rate Limit : {{ $profile->rate_limit ?: 'Non détecté' }}</p>
                <p>Pool : {{ $raw['address-pool'] ?? 'none' }}</p>
                <p>Queue : {{ $raw['parent-queue'] ?? 'none' }}</p>
                <p>Session Timeout : {{ $raw['session-timeout'] ?? 'Non détecté' }}</p>
                <p>Idle Timeout : {{ $raw['idle-timeout'] ?? 'Non détecté' }}</p>
                <p>Keepalive Timeout : {{ $raw['keepalive-timeout'] ?? 'Non détecté' }}</p>
                <p>Shared users : {{ $profile->shared_users ?? 'Non détecté' }}</p>
                <p>Statut : {{ ($raw['disabled'] ?? 'false') === 'true' ? 'désactivé' : 'actif' }}</p>
                @if($linkedPlan)
                    <p>Forfait LIMETE : {{ $linkedPlan->name }} — {{ \App\Support\Money::shop($linkedPlan->price, $linkedPlan->currency) }}</p>
                @else
                    <p>Forfait LIMETE : Non associé</p>
                    <a class="mt-2 inline-block rounded-xl border bg-white px-3 py-2" href="{{ route('plans.create', ['wifi_zone_id' => $router->wifi_zone_id, 'mikrotik_profile' => $profile->name]) }}">Associer un forfait</a>
                @endif
            </article>
        @empty
            <p class="mt-3">Non détecté</p>
        @endforelse
    </section>
@endif

@if($tab === 'plans')
    <section class="space-y-2">
        <h2 class="font-semibold">Association des forfaits</h2>
        @foreach($plans as $plan)
            @php
                $linked = $router->planLinks->firstWhere('plan_id', $plan->id);
                $names = $router->profiles->pluck('name');
            @endphp
            <form method="POST" action="{{ route('mikrotiks.plan-profile', $router) }}" class="rounded-2xl bg-white p-4 text-sm shadow-sm">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                <p class="font-semibold">{{ $plan->name }}</p>
                @if(! $linked && ! $plan->mikrotik_profile)
                    <p class="text-amber-800">Profil non associé</p>
                @elseif($plan->mikrotik_profile && ! $names->contains($plan->mikrotik_profile))
                    <p class="text-amber-800">Profil non trouvé</p>
                @endif
                <label class="mt-2 block">Profil MikroTik
                    <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="mikrotik_profile" required>
                        @foreach($names as $name)
                            <option value="{{ $name }}" @selected(($linked?->profile?->name ?? $plan->mikrotik_profile) === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                @if($names->isNotEmpty())
                    <button class="mt-2 rounded-xl border px-3 py-2">Associer</button>
                @endif
            </form>
        @endforeach
    </section>
@endif

@if($tab === 'users')
    <section class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Utilisateurs HotSpot</h2>
        <form method="GET" class="mt-3 grid gap-2 sm:grid-cols-3">
            <input type="hidden" name="tab" value="users">
            <input class="rounded-xl border px-3 py-3" name="q" value="{{ request('q') }}" placeholder="Recherche">
            <select class="rounded-xl border bg-white px-3 py-3" name="profile">
                <option value="">Tous les profils</option>
                @foreach($router->profiles as $profile)
                    <option value="{{ $profile->name }}" @selected(request('profile') === $profile->name)>{{ $profile->name }}</option>
                @endforeach
            </select>
            <select class="rounded-xl border bg-white px-3 py-3" name="state">
                <option value="">Tous</option>
                <option value="active" @selected(request('state') === 'active')>Actifs</option>
                <option value="disabled" @selected(request('state') === 'disabled')>Désactivés</option>
            </select>
            <button class="rounded-xl border px-3 py-2 sm:col-span-3">Filtrer</button>
        </form>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[640px] text-left">
                <thead><tr><th>username</th><th>profile</th><th>uptime</th><th>bytes in</th><th>bytes out</th><th>disabled</th><th>commentaire</th><th>expiration</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr class="border-t">
                        <td class="py-2">{{ $user['name'] ?? 'Non détecté' }}</td>
                        <td>{{ $user['profile'] ?? 'Non détecté' }}</td>
                        <td>{{ $user['uptime'] ?? 'Non détecté' }}</td>
                        <td>{{ $user['bytes-in'] ?? 'Non détecté' }}</td>
                        <td>{{ $user['bytes-out'] ?? 'Non détecté' }}</td>
                        <td>{{ ($user['disabled'] ?? 'false') === 'true' ? 'oui' : 'non' }}</td>
                        <td>{{ $user['comment'] ?? 'Non détecté' }}</td>
                        <td>{{ $user['limit-uptime'] ?? 'Non détecté' }}</td>
                    </tr>
                @empty
                    <tr><td class="py-3" colspan="8">Non détecté</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

@if($tab === 'sessions')
    <section class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Sessions actives</h2>
        @if($router->status !== 'online')
            <p class="mt-2 text-amber-800">Impossible de joindre le MikroTik.</p>
        @endif
        <div class="mt-3 space-y-3">
            @forelse($sessions as $session)
                <article class="rounded-xl bg-slate-50 p-3">
                    <p class="font-semibold">{{ $session['user'] ?? 'Non détecté' }}</p>
                    <p>address {{ $session['address'] ?? 'Non détecté' }}</p>
                    <p>MAC {{ $session['mac-address'] ?? 'Non détecté' }}</p>
                    <p>uptime {{ $session['uptime'] ?? 'Non détecté' }}</p>
                    <p>session time left {{ $session['session-time-left'] ?? 'Non détecté' }}</p>
                    <p>bytes in {{ $session['bytes-in'] ?? 'Non détecté' }}</p>
                    <p>bytes out {{ $session['bytes-out'] ?? 'Non détecté' }}</p>
                    <p>server {{ $session['server'] ?? 'Non détecté' }}</p>
                    @if(!empty($session['.id']))
                        <form method="POST" action="{{ route('mikrotiks.disconnect', $router) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="active_id" value="{{ $session['.id'] }}">
                            <input type="hidden" name="username" value="{{ $session['user'] ?? '' }}">
                            <button class="rounded-xl border px-3 py-2">Déconnecter</button>
                        </form>
                    @endif
                </article>
            @empty
                <p>Non détecté</p>
            @endforelse
        </div>
    </section>
@endif

@if($tab === 'interfaces')
    <section class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Interfaces</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[520px] text-left">
                <thead><tr><th>nom</th><th>type</th><th>running</th><th>MAC</th><th>RX</th><th>TX</th></tr></thead>
                <tbody>
                @forelse($details['interfaces'] ?? [] as $interface)
                    <tr class="border-t">
                        <td class="py-2">{{ $interface['name'] ?? 'Non détecté' }}</td>
                        <td>{{ $interface['type'] ?? 'Non détecté' }}</td>
                        <td>{{ $interface['running'] ?? 'Non détecté' }}</td>
                        <td>{{ $interface['mac-address'] ?? 'Non détecté' }}</td>
                        <td>{{ $interface['rx-byte'] ?? 'Non détecté' }}</td>
                        <td>{{ $interface['tx-byte'] ?? 'Non détecté' }}</td>
                    </tr>
                @empty
                    <tr><td class="py-3" colspan="6">Non détecté</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

@if($tab === 'ip')
    <section class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Adresses IP</h2>
        <p class="mt-1 text-slate-500">Adresse du routeur. Distincte du DNS HotSpot et de l’adresse API.</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[420px] text-left">
                <thead><tr><th>address</th><th>interface</th><th>network</th></tr></thead>
                <tbody>
                @forelse($details['addresses'] ?? [] as $address)
                    <tr class="border-t">
                        <td class="py-2">{{ $address['address'] ?? 'Non détecté' }}</td>
                        <td>{{ $address['interface'] ?? 'Non détecté' }}</td>
                        <td>{{ $address['network'] ?? 'Non détecté' }}</td>
                    </tr>
                @empty
                    <tr><td class="py-3" colspan="3">Non détecté</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

@if($tab === 'portal')
    <section class="space-y-3 rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Portail captif</h2>
        <p>URL publique de la boutique : {{ $shop ?: 'Non détecté' }}</p>
        <p>URL du portail captif : {{ $portalUrl ?: 'DNS non configuré' }}</p>
        <p>DNS Name : {{ filled($dns) ? $dns : 'DNS non configuré' }}</p>
        <p>login.html</p>
        <p>status.html</p>
        <p>logout.html</p>
        <h3 class="pt-2 font-semibold">Configuration à appliquer au MikroTik</h3>
        <textarea class="w-full rounded-xl border p-3 font-mono text-xs" rows="8" readonly>{{ implode("\n", $commands) }}</textarea>
        <form method="POST" action="{{ route('mikrotiks.portal', $router) }}">
            @csrf
            <button class="rounded-xl border px-4 py-3 font-semibold">Appliquer cette configuration</button>
        </form>
        <p class="text-xs text-slate-500">Rien n’est envoyé au routeur tant que ce bouton n’est pas utilisé. Les commandes retirant ou réinitialisant la configuration ne sont pas proposées.</p>
    </section>
@endif

@if($tab === 'prepare')
    @include('mikrotiks.partials.prepare')
@endif

@if($tab === 'journal')
    <section class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h2 class="font-semibold">Journal</h2>
        <ul class="mt-3 space-y-2">
            @forelse($logs as $log)
                <li class="rounded-xl bg-slate-50 p-3">
                    <p class="font-semibold">{{ $log->user->name ?? 'Système' }} — {{ $router->name }}</p>
                    <p>{{ $log->action }}</p>
                    <p>{{ $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                    @if(is_array($log->new_values))
                        @if(!empty($log->new_values['change']))<p>Changement : {{ $log->new_values['change'] }}</p>@endif
                        @if(!empty($log->new_values['result']))<p>Résultat : {{ $log->new_values['result'] }}</p>@endif
                        @if(!empty($log->new_values['error']))<p>Erreur : {{ $log->new_values['error'] }}</p>@endif
                    @endif
                </li>
            @empty
                <li>Non détecté</li>
            @endforelse
        </ul>
    </section>
@endif
@endsection
