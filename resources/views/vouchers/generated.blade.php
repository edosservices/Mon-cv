@extends('layouts.app')
@section('heading', 'Tickets générés')
@section('content')
@php
    $template = $batch['template'] ?? 'moderne';
    $perPage = (int) ($batch['per_page'] ?? 6);
@endphp
<div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-600">{{ $vouchers->count() }} ticket(s). Cochez ceux à imprimer ensemble.</p>
    <a class="rounded-xl border px-4 py-3 text-sm font-semibold" href="{{ route('vouchers.generate', ['wifi_zone_id' => $batch['wifi_zone_id'] ?? null, 'plan_id' => $batch['plan_id'] ?? null, 'count' => $batch['count'] ?? null, 'template' => $template, 'per_page' => $perPage]) }}">Dupliquer la configuration</a>
</div>

<form id="ticket-bulk" method="POST" class="no-print mb-4 grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-2">
    @csrf
    <label class="text-sm font-semibold">Modèle
        <select class="mt-1 w-full rounded-xl border px-3 py-3" name="template">
            @foreach($templates as $value => $label)
                <option value="{{ $value }}" @selected($template === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Nombre de tickets par page
        <select class="mt-1 w-full rounded-xl border px-3 py-3" name="per_page">
            @foreach([4 => '4 tickets / page', 6 => '6 tickets / page', 8 => '8 tickets / page'] as $value => $label)
                <option value="{{ $value }}" @selected($perPage === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <div class="flex flex-wrap gap-2 sm:col-span-2">
        <button class="rounded-xl bg-navy px-4 py-3 text-sm font-semibold text-white" formaction="{{ route('vouchers.print') }}">Imprimer la sélection</button>
        <button class="rounded-xl bg-electric px-4 py-3 text-sm font-semibold text-white" formaction="{{ route('vouchers.sheet-pdf') }}">Télécharger PDF</button>
    </div>
</form>

<form method="POST" action="{{ route('vouchers.sheet-pdf') }}" class="no-print mb-4">
    @csrf
    <input type="hidden" name="template" value="{{ $template }}">
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    @foreach($vouchers as $voucher)
        <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
    @endforeach
    <button class="rounded-xl border px-4 py-3 text-sm font-semibold">Télécharger tous</button>
</form>

<label class="no-print mb-3 inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" id="select-all" checked> Tout sélectionner</label>

<div class="space-y-3">
    @foreach($vouchers as $voucher)
        <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <label class="inline-flex items-center gap-2 font-semibold">
                    <input class="ticket-check" form="ticket-bulk" type="checkbox" name="ids[]" value="{{ $voucher->id }}" checked> Sélectionner
                </label>
                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ $voucher->statusLabel() }}</span>
            </div>
            <p class="mt-2 font-semibold">{{ $voucher->username }}</p>
            <p>{{ $voucher->plan->name ?? '' }} · {{ $voucher->wifiZone->name ?? '' }}</p>
            <p>{{ \App\Support\Money::format($voucher->price_amount, $voucher->currency) }} · {{ $voucher->plan->validityLabel() ?? '' }}</p>
            <p>Créé le {{ $voucher->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('vouchers.print') }}">@csrf
                    <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
                    <input type="hidden" name="template" value="{{ $template }}">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <button class="rounded-lg border px-3 py-2">Imprimer</button>
                </form>
                <form method="POST" action="{{ route('vouchers.sheet-pdf') }}">@csrf
                    <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
                    <input type="hidden" name="template" value="{{ $template }}">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <button class="rounded-lg border px-3 py-2">PDF</button>
                </form>
                @if(in_array($voucher->status, ['available', 'active'], true))
                    <form method="POST" action="{{ route('vouchers.destroy', $voucher) }}">@csrf @method('DELETE')
                        <input type="hidden" name="stay" value="1">
                        <button class="rounded-lg border px-3 py-2 text-red-700">Supprimer</button>
                    </form>
                @endif
            </div>
        </article>
    @endforeach
</div>
@endsection
@push('scripts')
<script>
    var all = document.getElementById('select-all');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('.ticket-check').forEach(function (box) { box.checked = all.checked; });
        });
    }
</script>
@endpush
