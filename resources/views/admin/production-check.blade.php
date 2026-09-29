@extends('layouts.admin')
@section('title', 'Contrôle de production')
@section('content')
<h1 class="mb-2 text-2xl font-semibold">PHASE 9 — Contrôle réel</h1>
<p class="mb-4 text-sm text-slate-600">Corrélation {{ $report['correlation_id'] }} · {{ $report['checked_at'] }}</p>
@if($report['banner'])
    <p class="mb-4 rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white">{{ $report['banner'] }}</p>
@endif
@if(session('test_user'))
    <p class="mb-4 rounded-xl bg-white px-4 py-3 text-sm">{{ session('test_user.status') }} — {{ session('test_user.detail') }}</p>
@endif

<section class="mb-6">
    <h2 class="mb-2 text-lg font-semibold">Rapport</h2>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach($report['sections'] as $section)
            <article class="rounded-xl bg-white p-3">
                <p class="text-xs text-slate-500">{{ $section['code'] }}. {{ $section['label'] }}</p>
                <p class="font-semibold">{{ $section['status'] }}</p>
                <p class="text-sm text-slate-600">{{ $section['detail'] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="mb-6">
    <h2 class="mb-2 text-lg font-semibold">Environnement</h2>
    <div class="space-y-2">
        @foreach($report['checks'] as $check)
            <article class="flex flex-wrap items-baseline justify-between gap-2 rounded-xl bg-white px-4 py-3">
                <div>
                    <p class="font-semibold">{{ $check['label'] }}</p>
                    <p class="text-sm text-slate-600">{{ $check['detail'] }}</p>
                </div>
                <p class="font-semibold">{{ $check['status'] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="mb-6">
    <h2 class="mb-2 text-lg font-semibold">Paiements</h2>
    <div class="space-y-2">
        @foreach($report['payments'] as $payment)
            <article class="flex flex-wrap items-baseline justify-between gap-2 rounded-xl bg-white px-4 py-3">
                <div>
                    <p class="font-semibold">{{ $payment['label'] }}</p>
                    <p class="text-sm text-slate-600">{{ $payment['detail'] }}</p>
                </div>
                <p class="font-semibold">{{ $payment['status'] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="mb-6">
    <h2 class="mb-2 text-lg font-semibold">MikroTik enregistrés</h2>
    @forelse($report['routers'] as $router)
        <article class="mb-3 rounded-xl bg-white p-4">
            <p class="font-semibold">{{ $router['name'] }} · {{ $router['zone'] ?: 'Zone non associée' }} · {{ $router['tenant'] ?: 'Entreprise non lue' }}</p>
            <p class="text-sm text-slate-600">{{ $router['host'] }} · {{ $router['status'] }}</p>
            <p class="text-sm text-slate-600">{{ $router['detail'] }}</p>
            @if($router['documentation'])
                <p class="mt-1 text-sm">Adresse de documentation. Elle ne peut pas valider un test réel.</p>
            @endif
            <form method="POST" action="{{ route('admin.production-check.test', $router['id']) }}" class="mt-3">
                @csrf
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white">Tester le MikroTik</button>
            </form>
            @if(is_array($router['probe']))
                <div class="mt-3 space-y-1 text-sm">
                    @foreach($router['probe']['reads'] ?? [] as $key => $read)
                        <p><span class="font-semibold">{{ $read['status'] }}</span> {{ $key }} — {{ $read['detail'] }}</p>
                    @endforeach
                    <p class="font-semibold">HotSpot : {{ $router['probe']['hotspot']['status'] ?? 'NOT TESTED' }} — {{ $router['probe']['hotspot']['detail'] ?? '' }}</p>
                    @if(($router['probe']['hotspot']['present'] ?? null) === true)
                        @foreach([
                            'interface' => 'Interface',
                            'profile' => 'Profil',
                            'dns_name' => 'DNS name',
                            'pool' => 'Address pool',
                            'login_by' => 'login-by',
                        ] as $field => $label)
                            <p>{{ $label }} : {{ filled($router['probe']['hotspot'][$field] ?? null) ? $router['probe']['hotspot'][$field] : 'Configuration manquante' }}</p>
                        @endforeach
                        <p>HTTP PAP : {{ $router['probe']['hotspot']['http_pap'] ?? 'NOT TESTED' }}</p>
                        <p>Cookie : {{ $router['probe']['hotspot']['cookie'] ?? 'NOT TESTED' }}</p>
                        <p>HTTPS : {{ $router['probe']['hotspot']['https'] ?? 'NOT TESTED' }}</p>
                    @endif
                    @if(!empty($router['probe']['hotspot']['action']))
                        <p>{{ $router['probe']['hotspot']['action'] }}</p>
                    @endif
                    @foreach($router['probe']['plans'] ?? [] as $plan)
                        <p>{{ $plan['status'] }} — {{ $plan['plan'] }} · {{ $plan['profile'] }} · {{ $plan['detail'] }}</p>
                    @endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('admin.production-check.test-user', $router['id']) }}" class="mt-3 flex flex-wrap gap-2">
                @csrf
                <input class="rounded-lg border px-3 py-2 text-sm" name="profile" placeholder="Profil RouterOS" required>
                <button class="rounded-xl border px-4 py-2 text-sm">Créer un utilisateur de test réel</button>
            </form>
            <form method="POST" action="{{ route('admin.production-check.test-user.delete', $router['id']) }}" class="mt-2 flex flex-wrap gap-2">
                @csrf
                @method('DELETE')
                <input class="rounded-lg border px-3 py-2 text-sm" name="username" value="{{ $router['test_user'] }}" placeholder="LIMETE_TEST_">
                <button class="rounded-xl border px-4 py-2 text-sm">Supprimer l'utilisateur de test</button>
            </form>
        </article>
    @empty
        <p class="rounded-xl bg-white p-4 text-sm">Aucun MikroTik en base. REAL MIKROTIK NOT TESTED.</p>
    @endforelse
</section>

<section>
    <h2 class="mb-2 text-lg font-semibold">Parcours client réel</h2>
    <ol class="space-y-2">
        @foreach($report['journey'] as $step)
            <li class="rounded-xl bg-white px-4 py-3 text-sm">
                <span class="font-semibold">{{ $step['step'] }}. {{ $step['status'] }}</span>
                — {{ $step['label'] }}
                <span class="text-slate-600">{{ $step['detail'] }}</span>
            </li>
        @endforeach
    </ol>
</section>
@endsection
