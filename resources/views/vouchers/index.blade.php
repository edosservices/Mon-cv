@extends('layouts.app')
@section('heading', 'Tickets')
@section('content')
<form method="POST" action="{{ route('vouchers.store') }}" class="mb-5 grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-4">
    @csrf
    <label class="text-sm">Zone
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="wifi_zone_id" required>
            @foreach($zones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach
        </select>
    </label>
    <label class="text-sm">Forfait
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="plan_id" required>
            @foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
        </select>
    </label>
    <label class="text-sm">Quantité<input class="mt-1 w-full rounded-lg border px-3 py-2" type="number" name="count" min="1" max="100" value="1"></label>
    <button class="self-end rounded-xl bg-electric px-4 py-2 text-white">Générer</button>
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
            <p class="mt-2 break-words">{{ $voucher->plan->name ?? '' }} · {{ $voucher->customer->phone ?? $voucher->customer->name ?? '—' }}</p>
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
            <a class="mt-3 inline-flex text-electric" href="{{ route('vouchers.show', $voucher) }}">Voir</a>
        </article>
    @endforeach
</div>
<div class="mt-4">{{ $vouchers->links() }}</div>
@endsection
