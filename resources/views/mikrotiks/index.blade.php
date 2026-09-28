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
                    <p class="mt-1 text-sm">
                        @if($router->status === 'online') 🟢 Connecté
                        @elseif($router->status === 'error') ⚠️ Erreur
                        @else 🔴 Hors ligne @endif
                        @if($router->last_seen_at) · vu {{ $router->last_seen_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} @endif
                    </p>
                    @if($router->identity)<p class="text-sm">Identité {{ $router->identity }}</p>@endif
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
        </article>
    @empty
        <p class="text-sm text-slate-500">Aucun MikroTik.</p>
    @endforelse
</div>
@endsection
