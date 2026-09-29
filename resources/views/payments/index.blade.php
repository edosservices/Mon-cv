@extends('layouts.app')
@section('heading', 'Paiements')
@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    <a class="rounded-full px-3 py-2 text-sm {{ $current === '' ? 'bg-navy text-white' : 'bg-white' }}" href="{{ route('payments.index') }}">Tous</a>
    @foreach($statuses as $status)
        <a class="rounded-full px-3 py-2 text-sm {{ $current === $status->value ? 'bg-navy text-white' : 'bg-white' }}" href="{{ route('payments.index', ['status' => $status->value]) }}">{{ $status->label() }}</a>
    @endforeach
</div>
<div class="space-y-3">
    @forelse($payments as $payment)
        @php
            $sale = $payment->payable instanceof \App\Models\Sale ? $payment->payable : null;
            $item = $sale?->items->first();
            $voucher = $item?->voucher;
        @endphp
        <article class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $payment->internal_reference ?? $payment->transaction_reference ?? 'Paiement' }}</p>
                    <p class="text-sm text-slate-500">{{ $sale?->customer->phone ?? $sale?->customer->name ?? 'Client' }} · {{ $item?->plan->name ?? 'Forfait' }}</p>
                    <p class="mt-1 text-sm">{{ config('limete.payment_providers.'.$payment->provider, $payment->provider) }} · {{ \App\Support\Money::format($payment->amount, $payment->currency) }}</p>
                    <p class="text-sm">{{ $payment->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                </div>
                <div class="text-sm">
                    <p class="font-semibold">{{ \App\Enums\PaymentStatus::tryFrom($payment->status)?->label() ?? $payment->status }}</p>
                    @if($voucher)
                        <p class="mt-1">Ticket {{ $voucher->username }}</p>
                        <p>{{ $voucher->sync_status === 'synced' ? 'Créé sur le MikroTik' : 'Non synchronisé' }}</p>
                        @if($voucher->sync_status !== 'synced' && $voucher->sync_error)
                            <p class="text-amber-800">{{ $voucher->sync_error }}</p>
                        @endif
                    @elseif($payment->status === 'pending' && $payment->provider === 'manual')
                        <p class="mt-1">Paiement à confirmer</p>
                    @elseif($payment->status === 'pending')
                        <p class="mt-1">En attente du fournisseur</p>
                    @endif
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @if($sale)
                    <a class="rounded-lg border px-3 py-2 text-sm" href="{{ route('sales.show', $sale) }}">Voir la vente</a>
                @endif
                @if($voucher && $voucher->sync_status !== 'synced' && auth()->user()->hasPermission('vouchers.manage'))
                    <form method="POST" action="{{ route('vouchers.retry', $voucher) }}">@csrf<button class="rounded-lg bg-electric px-3 py-2 text-sm text-white">Réessayer sur le MikroTik</button></form>
                @endif
                @if($sale && $sale->status === 'pending' && $payment->provider === 'manual' && auth()->user()->hasPermission('sales.confirm'))
                    <form method="POST" action="{{ route('sales.confirm', $sale) }}">@csrf<button class="rounded-lg bg-navy px-3 py-2 text-sm text-white">Confirmer le paiement</button></form>
                @endif
            </div>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-4 text-sm text-slate-500">Aucun paiement.</p>
    @endforelse
</div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
