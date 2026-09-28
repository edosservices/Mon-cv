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
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
    <table class="w-full min-w-[880px] text-left text-sm">
        <thead class="text-slate-500">
            <tr>
                <th class="p-3">Code</th>
                <th>Forfait</th>
                <th>Client</th>
                <th>Paiement</th>
                <th>Activation</th>
                <th>Expiration</th>
                <th>MikroTik</th>
                <th>Statut</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
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
            <tr class="border-t align-top">
                <td class="p-3 font-medium">{{ $voucher->username }}</td>
                <td>{{ $voucher->plan->name ?? '' }}</td>
                <td>{{ $voucher->customer->phone ?? $voucher->customer->name ?? '—' }}</td>
                <td>{{ $payment ? (\App\Enums\PaymentStatus::tryFrom($payment->status)?->label() ?? $payment->status) : '—' }}</td>
                <td>{{ $voucher->activated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                <td>
                    @if($voucher->sync_status === 'synced')
                        Créé sur le MikroTik
                    @else
                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900">Non synchronisé</span>
                        @if($voucher->sync_error)<span class="mt-1 block text-amber-800">Erreur : {{ $voucher->sync_error }}</span>@endif
                        <form method="POST" action="{{ route('vouchers.sync', $voucher) }}" class="mt-2">@csrf<button class="rounded-lg border px-3 py-2">Réessayer</button></form>
                    @endif
                </td>
                <td><span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $badge }}">{{ $voucher->statusLabel() }}</span></td>
                <td class="pr-3 text-right"><a class="text-electric" href="{{ route('vouchers.show', $voucher) }}">Voir</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $vouchers->links() }}</div>
@endsection
