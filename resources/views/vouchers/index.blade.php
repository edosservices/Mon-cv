@extends('layouts.app')
@section('heading', 'Tickets')
@section('content')
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
            <option value="4">4 tickets / page</option>
            <option value="6" selected>6 tickets / page</option>
            <option value="8">8 tickets / page</option>
        </select>
    </label>
    <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm font-semibold"><input type="checkbox" id="select-all"> Tout sélectionner</label>
        <button class="rounded-xl bg-navy px-4 py-3 text-sm font-semibold text-white" formaction="{{ route('vouchers.print') }}">Imprimer</button>
        <button class="rounded-xl border px-4 py-3 text-sm font-semibold" formaction="{{ route('vouchers.sheet-pdf') }}">PDF</button>
    </div>
</form>
<div class="space-y-3">
    @foreach($vouchers as $voucher)
        @php
            $payment = $voucher->saleItem?->sale?->payment;
            $badge = match ($voucher->status) {
                'active' => 'bg-emerald-50 text-emerald-800',
                'expired' => 'bg-red-50 text-red-800',
                'available' => 'bg-slate-100 text-slate-700',
                default => 'bg-slate-100 text-slate-700',
            };
        @endphp
        <article class="min-w-0 rounded-2xl bg-white p-4 text-sm shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="font-semibold">{{ $voucher->username }}</p>
                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $badge }}">{{ $voucher->statusLabel() }}</span>
            </div>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
            <p class="mt-2 break-words">{{ $voucher->plan->name ?? '' }} · {{ $voucher->wifiZone->name ?? 'Zone' }} · {{ $voucher->customer->phone ?? $voucher->customer->name ?? '—' }}</p>
            <p>Paiement {{ $payment ? (\App\Enums\PaymentStatus::tryFrom($payment->status)?->label() ?? $payment->status) : '—' }}</p>
            <p>Activation {{ $voucher->activated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
            <p>Expiration {{ $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
            @if($voucher->sync_status === 'synced')
                <p class="mt-2">Créé sur le MikroTik</p>
            @else
                <p class="mt-2"><span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900">Non synchronisé</span></p>
                @if($voucher->sync_error)<p class="mt-1 break-words text-amber-800">Erreur : {{ $voucher->sync_error }}</p>@endif
                <form method="POST" action="{{ route('vouchers.sync', $voucher) }}" class="mt-2">@csrf<button class="rounded-lg border px-3 py-2">Réessayer</button></form>
            @endif
                </div>
                <div class="lm-qr" role="img" aria-label="QR du ticket {{ $voucher->username }}">{!! \App\Support\QrCodes::svg(route('tickets.public', $voucher->public_token)) !!}</div>
            </div>
            <div class="no-print mt-3 flex flex-wrap items-center gap-2">
                <label class="inline-flex items-center gap-2"><input class="ticket-check" form="ticket-bulk" type="checkbox" name="ids[]" value="{{ $voucher->id }}"> Sélectionner</label>
                <a class="text-electric" href="{{ route('vouchers.show', $voucher) }}">Voir</a>
                <form method="POST" action="{{ route('vouchers.print') }}">@csrf
                    <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
                    <input type="hidden" name="template" value="moderne">
                    <input type="hidden" name="per_page" value="6">
                    <button class="rounded-lg border px-3 py-2">Imprimer</button>
                </form>
                <form method="POST" action="{{ route('vouchers.sheet-pdf') }}">@csrf
                    <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
                    <input type="hidden" name="template" value="moderne">
                    <input type="hidden" name="per_page" value="6">
                    <button class="rounded-lg border px-3 py-2">PDF</button>
                </form>
                <a class="rounded-lg border px-3 py-2" href="{{ route('vouchers.generate', ['wifi_zone_id' => $voucher->wifi_zone_id, 'plan_id' => $voucher->plan_id, 'template' => 'moderne', 'per_page' => 6]) }}">Dupliquer la configuration</a>
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
