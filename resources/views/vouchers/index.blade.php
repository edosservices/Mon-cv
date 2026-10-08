@extends('layouts.app')
@section('heading', 'Tickets')
@section('content')
<form class="no-print mb-4 grid gap-2 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5" method="GET">
    <label class="text-sm font-semibold">Statut
        <select class="mt-1 w-full rounded-xl border px-3 py-3" name="status">
            <option value="">Tous</option>
            @foreach(['available' => 'Disponibles', 'sold' => 'Vendus', 'active' => 'Actifs', 'used' => 'Utilisés', 'expired' => 'Expirés', 'pending_sync' => 'Sync en attente', 'failed' => 'Échec de sync'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Recherche
        <input class="mt-1 w-full rounded-xl border px-3 py-3" name="q" value="{{ request('q') }}" placeholder="Nom, référence, profil">
    </label>
    <label class="text-sm font-semibold">Du
        <input class="mt-1 w-full rounded-xl border px-3 py-3" type="date" name="from" value="{{ request('from') }}">
    </label>
    <label class="text-sm font-semibold">Au
        <input class="mt-1 w-full rounded-xl border px-3 py-3" type="date" name="to" value="{{ request('to') }}">
    </label>
    <button class="self-end min-h-12 rounded-xl bg-navy px-4 py-3 text-sm font-semibold text-white" type="submit">Filtrer</button>
</form>
<label class="no-print mb-3 block text-sm font-semibold">Rechercher
    <input class="mt-1 w-full rounded-xl border px-3 py-3" type="search" data-filter-list="#ticket-list" placeholder="Nom, zone ou forfait" aria-label="Rechercher un ticket">
</label>
<div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
    <a class="rounded-xl bg-electric px-4 py-3 text-sm font-semibold text-white" href="{{ route('vouchers.generate') }}">Générer</a>
    <p class="text-sm text-slate-600">Maximum {{ $limit }} tickets par génération.</p>
</div>
<form id="ticket-bulk" method="POST" class="no-print mb-4 grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-2">
    @csrf
    <label class="text-sm font-semibold">Modèle
        <select class="mt-1 w-full rounded-xl border px-3 py-3" name="template">
            @foreach($templates as $value => $label)
                <option value="{{ $value }}" @selected($value === 'moderne')>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Nombre de tickets par page
        <select class="mt-1 w-full rounded-xl border px-3 py-3" name="per_page">
            @foreach(\App\Services\TicketSheet::layoutOptions() as $value => $label)
                <option value="{{ $value }}" @selected((int) $value === \App\Services\TicketSheet::ECONOMICAL)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm font-semibold">Impression groupée
        <select class="mt-1 w-full rounded-xl border px-3 py-3" name="density">
            <option value="20">20 / page</option>
            <option value="30">30 / page</option>
            <option value="40">40 / page</option>
            <option value="50" selected>50 / page</option>
        </select>
    </label>
    <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" id="select-all"> Tout sélectionner</label>
        <button class="rounded-xl bg-navy px-4 py-3 text-sm font-semibold text-white" formaction="{{ route('vouchers.print') }}">Imprimer</button>
        <button class="rounded-xl border px-4 py-3 text-sm font-semibold" formaction="{{ route('vouchers.sheet-pdf') }}">PDF</button>
        <button class="rounded-xl border px-4 py-3 text-sm font-semibold" formaction="{{ route('vouchers.bulk') }}">Aperçu</button>
        <button class="rounded-xl border px-4 py-3 text-sm font-semibold" formaction="{{ route('vouchers.bulk') }}">Imprimer la sélection</button>
    </div>
</form>
<div class="lm-table-wrap rounded-2xl bg-white shadow-sm" id="ticket-list">
    <table class="lm-table">
        <thead>
            <tr><th></th><th>Ticket</th><th>Profil</th><th>Client</th><th>Statut</th><th>Sync</th><th></th></tr>
        </thead>
        <tbody>
            @foreach($vouchers as $voucher)
                @php
                    $payment = $voucher->saleItem?->sale?->payment;
                @endphp
                <tr data-filter-item>
                    <td><label class="inline-flex items-center gap-2"><input class="ticket-check" form="ticket-bulk" type="checkbox" name="ids[]" value="{{ $voucher->id }}"> Sélectionner</label></td>
                    <td>
                        <span class="font-semibold">{{ $voucher->username }}</span>
                        <span class="block text-xs text-slate-500">Activation {{ $voucher->activated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</span>
                        <span class="block text-xs text-slate-500">Expiration {{ $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</span>
                    </td>
                    <td>{{ $voucher->plan->name ?? '' }} · {{ $voucher->wifiZone->name ?? 'Zone' }}</td>
                    <td>{{ $voucher->customer->phone ?? $voucher->customer->name ?? '—' }}</td>
                    <td>{{ $voucher->statusLabel() }}<span class="block text-xs">Paiement {{ $payment ? (\App\Enums\PaymentStatus::tryFrom($payment->status)?->label() ?? $payment->status) : '—' }}</span></td>
                    <td class="whitespace-normal">
                        @if($voucher->sync_status === 'synced')
                            Créé sur le MikroTik
                        @else
                            <span>Non synchronisé</span>
                            @if($voucher->sync_error)<span class="block text-xs text-amber-800">Erreur : {{ $voucher->sync_error }}</span>@endif
                            <form method="POST" action="{{ route('vouchers.sync', $voucher) }}" class="mt-1">@csrf<button class="rounded-lg border px-3 py-2">Réessayer</button></form>
                        @endif
                    </td>
                    <td>
                        <details class="lm-pop">
                            <summary aria-label="Actions">⋮</summary>
                            <div class="lm-pop-panel">
                                <a href="{{ route('vouchers.show', $voucher) }}">Voir</a>
                                <button type="button" class="js-copy" data-url="{{ route('tickets.public', $voucher->public_token) }}">Partager</button>
                                <form method="POST" action="{{ route('vouchers.print') }}">@csrf
                                    <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
                                    <input type="hidden" name="template" value="moderne">
                                    <input type="hidden" name="per_page" value="{{ \App\Services\TicketSheet::ECONOMICAL }}">
                                    <button>Imprimer</button>
                                </form>
                                <form method="POST" action="{{ route('vouchers.sheet-pdf') }}">@csrf
                                    <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
                                    <input type="hidden" name="template" value="moderne">
                                    <input type="hidden" name="per_page" value="{{ \App\Services\TicketSheet::ECONOMICAL }}">
                                    <button>PDF</button>
                                </form>
                                <a href="{{ route('vouchers.generate', ['wifi_zone_id' => $voucher->wifi_zone_id, 'plan_id' => $voucher->plan_id, 'template' => 'moderne', 'per_page' => \App\Services\TicketSheet::ECONOMICAL]) }}">Dupliquer la configuration</a>
                                @if(in_array($voucher->status, ['available', 'active'], true))
                                    <form method="POST" action="{{ route('vouchers.destroy', $voucher) }}">@csrf @method('DELETE')
                                        <input type="hidden" name="stay" value="1">
                                        <button class="text-red-700">Supprimer</button>
                                    </form>
                                @endif
                            </div>
                        </details>
                        <div class="lm-qr mt-2" role="img" aria-label="QR du ticket {{ $voucher->username }}">{!! \App\Support\QrCodes::svg(route('tickets.public', $voucher->public_token)) !!}</div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $vouchers->links() }}</div>
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
