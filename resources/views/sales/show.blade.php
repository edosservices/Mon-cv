@extends('layouts.app')
@section('heading', 'Vente')
@section('content')
<article class="rounded-2xl bg-white p-5 shadow-sm">
    <p>Statut : {{ $sale->status === 'paid' ? 'Payée' : 'En attente' }}</p>
    <p>Montant : {{ \App\Support\Money::format($sale->total_amount, $sale->currency) }}</p>
    <p>Référence paiement : {{ $sale->payment->transaction_reference ?? '—' }}</p>
    @if($sale->status === 'pending')
        <p class="mt-3 font-medium">Paiement à confirmer</p>
        <form method="POST" action="{{ route('sales.confirm', $sale) }}" class="mt-4">@csrf<button class="rounded-lg bg-electric px-4 py-2 text-white">Confirmer le paiement</button></form>
    @endif
    @foreach($sale->items as $item)
        @if($item->voucher)
            <p class="mt-4"><a class="text-electric" href="{{ route('vouchers.show', $item->voucher) }}">Ticket {{ $item->voucher->username }}</a></p>
        @endif
    @endforeach
</article>
@endsection
