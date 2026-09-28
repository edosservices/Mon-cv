@extends('layouts.shop')
@section('title', 'Commande — '.$zone->name)
@section('content')
@php
    $item = $sale->items->first();
    $plan = $item?->plan ?? $voucher?->plan;
@endphp
<section class="panel">
    @if($sale->status === 'paid' && $voucher)
        <p class="status-pill"><span class="dot"></span> Paiement confirmé</p>
        <h1>Votre ticket est prêt</h1>
        <p class="lede">{{ $plan->name ?? 'Forfait' }} · {{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</p>
        <a class="btn btn-primary" href="{{ route('tickets.public', $voucher->public_token) }}">Voir mon ticket</a>
    @else
        <p class="status-pill is-wait">Paiement en attente</p>
        <h1>Commande enregistrée</h1>
        <p class="lede">{{ $plan->name ?? 'Forfait' }} · {{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</p>
        @if($sale->payment?->transaction_reference)
            <p class="ref">Référence <strong>{{ $sale->payment->transaction_reference }}</strong></p>
        @endif
        <p class="help">{{ $sale->payment->metadata['note'] ?? 'Le ticket apparaîtra après confirmation du paiement.' }}</p>
        <p class="help">Conservez cette page. Aucun compte WiFi n’est créé tant que le paiement n’est pas confirmé.</p>
    @endif
</section>
<a class="shop-link center" href="{{ route('shop.tickets', $zone->slug) }}">Mes tickets</a>
@endsection
