@extends('layouts.app')
@section('heading', 'Générer des tickets')
@section('content')
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

<form method="POST" action="{{ route('vouchers.store') }}" class="grid gap-4 rounded-2xl bg-white p-4 shadow-sm">
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
    <button class="rounded-xl bg-electric px-4 py-3 font-semibold text-white" @disabled($zones->isEmpty() || $plans->isEmpty() || $limit < 1)>Générer les tickets</button>
</form>
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
