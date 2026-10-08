@extends('layouts.app')
@section('heading', 'Tickets générés')
@section('content')
@php
    $template = $batch['template'] ?? 'moderne';
    $perPage = (int) ($batch['per_page'] ?? \App\Services\TicketSheet::ECONOMICAL);
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
            @foreach(\App\Services\TicketSheet::layoutOptions() as $value => $label)
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

<p class="no-print mb-3 text-sm font-semibold">{{ $vouchers->count() }} tickets générés avec succès.</p>
<label class="no-print mb-3 inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" id="select-all" checked> Tout sélectionner</label>
@php
    $boardPages = max(1, (int) ceil($vouchers->count() / 24));
    $boardPage = min($boardPages, max(1, (int) request('page', 1)));
    $board = $vouchers->forPage($boardPage, 24);
@endphp
<div class="ticket-board">
    @foreach($board as $voucher)
        <article class="ticket-card">
            <img src="{{ asset('brand/logo-limete-wifi-manager.png') }}" alt="LIMETE WIFI">
            <label class="no-print">
                <input class="ticket-check" form="ticket-bulk" type="checkbox" name="ids[]" value="{{ $voucher->id }}" checked> Sélectionner
            </label>
            <strong>{{ $voucher->username }}</strong>
            <p>{{ $voucher->plan->name ?? '' }}</p>
            <p>{{ $voucher->plan->validityLabel() ?? '' }}</p>
            <p>{{ \App\Support\Money::shop($voucher->price_amount, $voucher->currency) }}</p>
            <p>{{ $voucher->wifiZone->name ?? '' }}</p>
            @if($voucher->sync_status === 'synced')
                <p>Synchronisé</p>
            @else
                <p>Synchronisation en attente</p>
                @if($voucher->sync_error)
                    <p>{{ $voucher->sync_error }}</p>
                @endif
            @endif
            <p class="no-print">{{ $voucher->statusLabel() }} · Créé le {{ $voucher->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
            <div class="ticket-qr" aria-label="QR du ticket">{!! \App\Support\QrCodes::svg(route('tickets.public', $voucher->public_token)) !!}</div>
            <div class="no-print ticket-actions">
                <form method="POST" action="{{ route('vouchers.print') }}">@csrf
                    <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
                    <input type="hidden" name="template" value="{{ $template }}">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <button>Imprimer</button>
                </form>
                <form method="POST" action="{{ route('vouchers.sheet-pdf') }}">@csrf
                    <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
                    <input type="hidden" name="template" value="{{ $template }}">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <button>PDF</button>
                </form>
                @if(in_array($voucher->status, ['available', 'active'], true))
                    <form method="POST" action="{{ route('vouchers.destroy', $voucher) }}">@csrf @method('DELETE')
                        <input type="hidden" name="stay" value="1">
                        <button>Supprimer</button>
                    </form>
                @endif
            </div>
        </article>
    @endforeach
</div>
@if($boardPages > 1)
    <nav class="ticket-pages no-print" aria-label="Pages de tickets">
        @if($boardPage > 1)
            <a href="{{ request()->fullUrlWithQuery(['page' => $boardPage - 1]) }}">←</a>
        @endif
        @for($page = 1; $page <= $boardPages; $page++)
            <a href="{{ request()->fullUrlWithQuery(['page' => $page]) }}" @if($page === $boardPage) aria-current="page" @endif>{{ $page }}</a>
        @endfor
        @if($boardPage < $boardPages)
            <a href="{{ request()->fullUrlWithQuery(['page' => $boardPage + 1]) }}">→</a>
        @endif
    </nav>
@endif
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
