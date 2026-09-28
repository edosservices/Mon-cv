@extends('layouts.app')
@section('heading', 'MikroTik')
@section('content')
<div class="mb-4"><a class="rounded-lg bg-electric px-4 py-2 text-sm text-white" href="{{ route('mikrotiks.create') }}">Connecter un routeur</a></div>
<div class="space-y-3">
    @forelse($routers as $router)
        <article class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">{{ $router->name }}</h2>
                    <p class="text-sm text-slate-500">{{ $router->host }}:{{ $router->api_port }} · {{ $router->wifiZone->name ?? 'Zone non liée' }}</p>
                    <p class="mt-1 text-sm">Statut
                        @if($router->status === 'online') 🟢 Connecté
                        @elseif($router->status === 'error') ⚠️ Erreur
                        @else 🔴 Hors ligne @endif
                    </p>
                    <p class="text-sm">Identity {{ $router->identity ?: '—' }}</p>
                    <p class="text-sm">Dernière vérification {{ $router->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'pas encore vérifié' }}</p>
                    @if($router->routeros_version)<p class="text-sm">RouterOS {{ $router->routeros_version }}</p>@endif
                    @if($router->profiles->isNotEmpty())
                        <p class="text-sm">Profils : {{ $router->profiles->pluck('name')->join(', ') }}</p>
                    @endif
                    @if($router->last_error)<p class="text-sm text-amber-800">{{ $router->last_error }}</p>@endif
                </div>
                <div class="flex flex-col gap-2 text-sm">
                    <a class="text-electric" href="{{ route('mikrotiks.edit', $router) }}">Modifier</a>
                    <form method="POST" action="{{ route('mikrotiks.test', $router) }}">@csrf<button>TESTER LA CONNEXION</button></form>
                    <form method="POST" action="{{ route('mikrotiks.profiles', $router) }}">@csrf<button>Synchroniser les profils</button></form>
                </div>
            </div>
            @if($router->wifiZone)
                @php
                    $origin = rtrim(url('/'), '/');
                    $shop = route('shop.show', $router->wifiZone->slug);
                    $portal = "<script>\nwindow.LIMETE_SHOP = ".json_encode($shop, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\nwindow.LIMETE_ORIGIN = ".json_encode($origin, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).";\n</script>\n<script src=\"".$origin."/hotspot/session.js?username=$(username)\"></script>";
                    $onLogin = str_replace('__LIMETE_APP_URL__', $origin, file_get_contents(base_path('hotspot/on-login.rsc')));
                @endphp
                <label class="mt-4 block text-sm font-semibold">Portail de {{ $router->wifiZone->name }}
                    <textarea class="mt-1 w-full min-w-0 rounded-xl border p-3 font-mono text-xs" rows="6" readonly>{{ $portal."\n\n".$onLogin }}</textarea>
                </label>
                <p class="mt-1 text-xs text-slate-500">Copier le dossier hotspot/ sur le routeur, puis ce bloc dans login.html et status.html avant js/plan-data.js. Le script on-login lit l’expiration déjà enregistrée. Il ne la recalcule pas.</p>
            @endif
        </article>
    @empty
        <p class="text-sm text-slate-500">Aucun MikroTik.</p>
    @endforelse
</div>
@endsection
