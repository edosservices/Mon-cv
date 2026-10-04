@extends('layouts.app')
@section('heading', 'Générer')
@section('content')
<div class="tg">
@include('vouchers.assist-steps', ['step' => 1])
@php
    $routerOn = $quickZone && $quickRouter && empty($quick['offline']);
@endphp
<header class="tg-hero">
    <div>
        <p class="tg-kicker">Limete WiFi</p>
        <h2>Générer des tickets</h2>
        <p class="tg-lead">Créez plusieurs tickets WiFi en quelques secondes.</p>
    </div>
    @if($quickZone)
        <div class="tg-pills">
            <span class="tg-pill">{{ $quickZone->name }}</span>
            @if($quickRouter)
                <span class="tg-pill"><span class="tg-dot {{ $routerOn ? 'is-on' : '' }}"></span>{{ $routerOn ? 'Routeur connecté' : 'Hors ligne' }}</span>
            @endif
        </div>
    @endif
</header>
@if($quickZone)
    <section class="tg-card">
        <div class="tg-card-head">
            <span class="tg-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12a7 7 0 0 1 14 0"/><path d="M8 12a4 4 0 0 1 8 0"/><circle cx="12" cy="16" r="1.2" fill="currentColor"/></svg></span>
            <div>
                <h2>1. Routeur</h2>
                <p>Le catalogue est relu sur le routeur de la zone choisie.</p>
            </div>
        </div>
        <form class="tg-grid" method="GET" action="{{ url()->current() }}" data-tg-zone>
            <label class="tg-label" for="catalog-zone">WiFi Zone / routeur
                <select id="catalog-zone" name="wifi_zone_id" onchange="var n=this.form.querySelector('[data-tg-zone-wait]'); if(n) n.hidden=false; this.form.submit();">
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" @selected((int) $quickZone->id === (int) $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="tg-router-box">
                <span class="text-sm font-semibold">Routeur</span>
                @if($quickRouter)
                    <strong class="tg-router-name">Routeur sélectionné : {{ $quickRouter->name }}</strong>
                    <small>{{ $quickRouter->host }}</small>
                @else
                    <strong>Aucun routeur actif sur cette zone. Le catalogue local est utilisé s’il existe.</strong>
                @endif
            </div>
            @if($quick['notice'])
                <p class="tg-note">{{ $quick['notice'] }}</p>
            @endif
            <p class="tg-hint" data-tg-zone-wait hidden>Chargement du catalogue…</p>
        </form>
    </section>
    @include('vouchers.sale')
@endif

<details class="tg-advanced">
    <summary>Options avancées</summary>
@if($quickZone)
    @include('vouchers.express')
    @include('vouchers.quick')
@endif

<section class="tg-card">
    <h2 class="text-lg font-semibold">Choisir la durée</h2>
    <p class="mt-1 text-sm text-slate-600">Seules les durées de vos forfaits sont proposées.</p>
    @forelse($zones as $zone)
        @php $choices = $durations[$zone->id] ?? []; @endphp
        <div class="mt-4">
            <p class="text-sm font-semibold">{{ $zone->name }}</p>
            @if($choices === [])
                <p class="mt-2 text-sm text-slate-500">Aucune durée de forfait n’est disponible pour cette zone.</p>
            @else
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach($choices as $choice)
                        <form method="POST" action="{{ route('vouchers.assist.preview') }}">
                            @csrf
                            <input type="hidden" name="wifi_zone_id" value="{{ $zone->id }}">
                            <input type="hidden" name="plan_id" value="{{ $choice['plan_id'] }}">
                            <button class="min-h-14 w-full rounded-xl border px-3 py-3 text-left" type="submit">
                                <strong class="block">{{ $choice['label'] }}</strong>
                                <span class="text-sm text-slate-500">{{ $choice['plan_name'] }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <p class="mt-3 text-sm text-slate-500">Créez une WiFi Zone et un forfait avant un ticket.</p>
    @endforelse
</section>

<h2 class="mb-3 text-lg font-semibold">Générer des tickets</h2>
@php
    $zoneId = old('wifi_zone_id', $prefill['wifi_zone_id']);
    $planId = old('plan_id', $prefill['plan_id']);
    $count = old('count', $prefill['count'] ?: ($limit > 0 ? min(20, $limit) : 1));
    $template = old('template', $prefill['template'] ?: 'moderne');
    $perPage = (int) old('per_page', $prefill['per_page'] ?: 6);
@endphp

@if($limit < 1)
    <p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">Votre abonnement ne comprend pas la génération de tickets. Ouvrez Abonnement et choisissez une formule qui inclut les tickets.</p>
@else
    <p class="mb-4 text-sm text-slate-600">Maximum {{ $limit }} tickets par génération pour votre abonnement. Les identifiants, les mots de passe, les jetons et les QR sont créés automatiquement.</p>
@endif

<form method="POST" action="{{ route('vouchers.store') }}" class="tg-card tg-batch">
    @csrf
    <label class="text-sm font-semibold">WiFi Zone
        <select class="mt-1 w-full rounded-xl border px-3 py-3" name="wifi_zone_id" id="zone" required @disabled($zones->isEmpty() || $limit < 1)>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) $zoneId === (int) $zone->id)>{{ $zone->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Forfait
        <select class="mt-1 w-full rounded-xl border px-3 py-3" name="plan_id" id="plan" required @disabled($plans->isEmpty() || $limit < 1)>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" data-zone="{{ $plan->wifi_zone_id }}" @selected((int) $planId === (int) $plan->id)>{{ $plan->name }} — {{ \App\Support\Money::format($plan->price, $plan->currency) }}</option>
            @endforeach
        </select>
    </label>
    <fieldset class="text-sm font-semibold" @disabled($limit < 1)>
        <legend>Quantité</legend>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach([5, 10, 20, 50, 100] as $preset)
                @if($preset <= $limit)
                    <button type="button" class="js-qty rounded-lg border px-3 py-2 font-semibold" data-qty="{{ $preset }}">{{ $preset }} tickets</button>
                @endif
            @endforeach
        </div>
        <label class="mt-3 block font-normal">Quantité personnalisée
            <input class="mt-1 w-full rounded-xl border px-3 py-3" type="number" name="count" id="count" min="1" max="{{ max(1, $limit) }}" value="{{ $count }}" required>
        </label>
    </fieldset>
    <fieldset class="text-sm font-semibold">
        <legend>Modèle</legend>
        <div class="mt-2 flex flex-wrap gap-3 font-normal">
            @foreach($templates as $value => $label)
                <label class="inline-flex items-center gap-2"><input type="radio" name="template" value="{{ $value }}" @checked($template === $value)> {{ $label }}</label>
            @endforeach
        </div>
    </fieldset>
    <fieldset class="text-sm font-semibold">
        <legend>Nombre de tickets par page</legend>
        <div class="mt-2 flex flex-wrap gap-3 font-normal">
            @foreach([4, 6, 8] as $layout)
                <label class="inline-flex items-center gap-2"><input type="radio" name="per_page" value="{{ $layout }}" @checked($perPage === $layout)> {{ $layout }} tickets / page</label>
            @endforeach
        </div>
    </fieldset>
    <button class="tg-cta" @disabled($zones->isEmpty() || $plans->isEmpty() || $limit < 1)>Générer les tickets</button>
</form>
</details>
</div>
@endsection
@push('scripts')
<script>
    document.querySelectorAll('.js-qty').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById('count');
            if (input) { input.value = button.getAttribute('data-qty'); }
        });
    });
    var zone = document.getElementById('zone');
    var plan = document.getElementById('plan');
    function filterPlans() {
        if (!zone || !plan) { return; }
        var selected = zone.value;
        Array.prototype.forEach.call(plan.options, function (option) {
            var owner = option.getAttribute('data-zone');
            var visible = !owner || owner === selected;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (plan.selectedOptions[0] && plan.selectedOptions[0].disabled) {
            var next = Array.prototype.find.call(plan.options, function (option) { return !option.disabled; });
            if (next) { plan.value = next.value; }
        }
    }
    if (zone) {
        zone.addEventListener('change', filterPlans);
        filterPlans();
    }
</script>
@endpush
