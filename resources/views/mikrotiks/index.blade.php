@extends('layouts.app')
@section('heading', 'Routeur')
@section('content')
<ol class="lm-chain" aria-label="Parcours du réseau">
    <li>Internet</li>
    <li>MikroTik</li>
    <li>WiFi Zone</li>
    <li>Clients</li>
</ol>
<div class="mb-4">
    <a class="inline-flex rounded-xl bg-electric px-4 py-3 text-sm font-semibold text-white" href="{{ route('mikrotiks.assistant') }}">+ Ajouter mon MikroTik</a>
    <a class="ml-2 inline-flex rounded-xl border px-4 py-3 text-sm font-semibold" href="{{ route('mikrotiks.create') }}">Formulaire détaillé</a>
</div>
<div class="space-y-3">
    @forelse($routers as $router)
        @php
            $details = $router->details ?? [];
            $names = $router->profiles->pluck('name');
            $zonePlans = $plans->filter(function ($plan) use ($router) {
                if (! $router->wifi_zone_id) {
                    return $plan->wifi_zone_id === null;
                }

                return $plan->wifi_zone_id === null || (int) $plan->wifi_zone_id === (int) $router->wifi_zone_id;
            });
        @endphp
        <article class="min-w-0 rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold">
                @if($router->status === 'online') ● CONNECTÉ
                @elseif($router->status === 'error') ● ERREUR
                @else ● HORS LIGNE @endif
            </p>
            <h2 class="mt-1 text-lg font-semibold">{{ $router->name }}</h2>
            <p class="text-sm">Zone : {{ $router->wifiZone->name ?? 'Zone non liée' }}</p>
            <p class="text-sm">Identity : {{ $router->identity ?: '—' }}</p>
            <p class="text-sm">RouterOS : {{ $router->routeros_version ?: '—' }}</p>
            <p class="break-all text-sm">IP : {{ $router->host }}:{{ $router->api_port }}</p>
            <p class="text-sm">HotSpot : {{ $router->hotspot_server ?: (array_key_exists('hotspot', $details) ? ($details['hotspot'] ? '✓' : 'Non') : '—') }}</p>
            <p class="text-sm">DNS : {{ $router->dns ?: ($details['dns_name'] ?? null ?: 'DNS non configuré') }}</p>
            <p class="text-sm">Utilisateurs : {{ $details['hotspot_users'] ?? '—' }}</p>
            <p class="text-sm">Actifs : {{ $details['active_users'] ?? '—' }}</p>
            <p class="text-sm">Dernière vérification {{ $router->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'pas encore vérifié' }}</p>
            @if(in_array($router->status, ['offline', 'error'], true))
                <p class="mt-2 text-sm font-semibold">Impossible de joindre le MikroTik.</p>
            @endif
            @if($router->last_error)<p class="mt-1 break-words text-sm text-amber-800">{{ $router->last_error }}</p>@endif
            @if($details['uptime'] ?? null)<p class="text-sm">Uptime {{ $details['uptime'] }}</p>@endif
            @if(($details['cpu'] ?? null) !== null && $details['cpu'] !== '')<p class="text-sm">CPU {{ $details['cpu'] }}</p>@endif
            @if($details['memory'] ?? null)<p class="text-sm">Mémoire {{ $details['memory'] }}</p>@endif

            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <form method="POST" action="{{ route('mikrotiks.test', $router) }}">@csrf<button class="rounded-xl border px-4 py-3 font-semibold">TESTER LA CONNEXION</button></form>
                <form method="POST" action="{{ route('mikrotiks.sync', $router) }}">@csrf<button class="rounded-xl border px-4 py-3 font-semibold">Synchroniser</button></form>
                <a class="rounded-xl border px-4 py-3 font-semibold" href="{{ route('mikrotiks.show', $router) }}">Gérer</a>
                <a class="rounded-xl border px-4 py-3 font-semibold" href="{{ route('active-users.index') }}">Utilisateurs</a>
                <a class="rounded-xl border px-4 py-3 font-semibold" href="{{ route('mikrotiks.edit', $router) }}">Modifier</a>
                <form method="POST" action="{{ route('mikrotiks.destroy', $router) }}">@csrf @method('DELETE')<button class="rounded-xl border px-4 py-3 font-semibold text-red-700">Supprimer</button></form>
            </div>

            <section class="mt-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold">Profils MikroTik</h3>
                    <form method="POST" action="{{ route('mikrotiks.profiles', $router) }}">@csrf<button class="rounded-xl border px-3 py-2 text-sm">Synchroniser les profils</button></form>
                </div>
                @if($names->isEmpty())
                    <p class="mt-2 text-sm text-slate-500">Aucun profil lu. Synchronisez le routeur.</p>
                @else
                    <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                        @foreach($names as $name)
                            <li class="rounded-full bg-slate-100 px-3 py-1">{{ $name }} ✓</li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-3 space-y-2">
                    @foreach($zonePlans as $plan)
                        <form method="POST" action="{{ route('mikrotiks.plan-profile', $router) }}" class="min-w-0 rounded-xl bg-slate-50 p-3 text-sm">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <p class="font-semibold">{{ $plan->name }}</p>
                            <p class="text-slate-500">Le nom du forfait n’est pas le profil du routeur.</p>
                            @php $linked = $router->planLinks->firstWhere('plan_id', $plan->id); @endphp
                            @if(! $linked && ! $plan->mikrotik_profile)
                                <p class="mt-1 font-semibold text-amber-800">Profil non associé</p>
                            @elseif($plan->mikrotik_profile && ! $names->contains($plan->mikrotik_profile))
                                <p class="mt-1 font-semibold text-amber-800">Profil non trouvé</p>
                            @endif
                            <label class="mt-2 block">Profil MikroTik
                                <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="mikrotik_profile" required>
                                    @foreach($names as $name)
                                        <option value="{{ $name }}" @selected($plan->mikrotik_profile === $name)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @if($names->isNotEmpty())
                                <button class="mt-2 rounded-xl border px-3 py-2">Associer</button>
                            @endif
                        </form>
                    @endforeach
                </div>
            </section>

            @if($router->wifiZone)
                @php
                    $origin = rtrim(url('/'), '/');
                    $shop = route('shop.show', $router->wifiZone->slug);
                    $portal = "<script>\nwindow.LIMETE_SHOP = ".json_encode($shop, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\nwindow.LIMETE_ORIGIN = ".json_encode($origin, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\nwindow.LIMETE_ZONE = ".json_encode($router->wifiZone->slug, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\n</script>\n<script src=\"".$origin."/hotspot/session.js?username=$(username)\"></script>";
                    $onLogin = str_replace('__LIMETE_APP_URL__', $origin, file_get_contents(base_path('hotspot/on-login.rsc')));
                @endphp
                <label class="mt-4 block text-sm font-semibold">Portail de {{ $router->wifiZone->name }}
                    <textarea class="mt-1 w-full min-w-0 rounded-xl border p-3 font-mono text-xs" rows="6" readonly>{{ $portal."\n\n".$onLogin }}</textarea>
                </label>
                <p class="mt-1 text-xs text-slate-500">Copier le dossier hotspot/ sur le routeur, puis ce bloc dans login.html et status.html avant js/plan-data.js. Le script on-login lit l’expiration déjà enregistrée. Il ne la recalcule pas.</p>
            @endif
        </article>
    @empty
        <p class="text-sm text-slate-500">Aucun MikroTik. Ajoutez le routeur de votre WiFi Zone.</p>
    @endforelse
</div>
@endsection
