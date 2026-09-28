@extends('layouts.shop')
@section('title', 'Commande — '.$zone->name)
@section('content')
@php
    $item = $sale->items->first();
    $plan = $item?->plan ?? $voucher?->plan;
    $payment = $sale->payment;
    $provider = config('limete.payment_providers.'.$payment?->provider, $payment?->provider);
@endphp
<section class="panel">
    @if($sale->status === 'paid' && $voucher)
        <p class="status-pill"><span class="dot"></span> Paiement confirmé</p>
        <h1>Votre ticket est prêt</h1>
        <p class="lede">{{ $plan->name ?? 'Forfait' }} · {{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</p>
        <p class="plan-meta">{{ $plan?->unlimited_data ? 'Internet illimité' : 'Selon le forfait' }}</p>
    @elseif($payment?->status === 'failed')
        <p class="status-pill is-wait">Paiement échoué</p>
        <h1>Le paiement n’a pas été confirmé</h1>
        <p class="help">Aucun ticket n’a été activé. Vous pouvez recommencer depuis la boutique.</p>
        <a class="btn btn-primary" href="{{ route('shop.show', $zone->slug) }}">Retour aux forfaits</a>
    @else
        <p class="status-pill is-wait">Paiement en attente</p>
        <h1>Votre paiement est en cours de vérification</h1>
        <p class="lede">{{ $plan->name ?? 'Forfait' }} · {{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</p>
        <p class="ref">Référence <strong>{{ $payment->internal_reference ?? '' }}</strong></p>
        @if($payment?->transaction_reference)
            <p class="help">Référence communiquée : {{ $payment->transaction_reference }}</p>
        @endif
        <p class="help">Moyen : {{ $provider }}</p>
        <p class="help">Statut : {{ \App\Enums\PaymentStatus::tryFrom($payment->status ?? '')?->label() ?? 'En attente' }}</p>
        <p class="help">{{ $payment->metadata['note'] ?? 'Le ticket apparaîtra après confirmation du paiement.' }}</p>
        <p class="help">Actualiser cette page ne confirme pas le paiement.</p>
        <form method="POST" action="{{ route('shop.payment.refresh', [$zone->slug, $sale->public_token]) }}">
            @csrf
            <button class="btn btn-ghost" type="submit">Actualiser le statut</button>
        </form>
    @endif
</section>
@if($sale->status === 'paid' && $voucher)
    @include('vouchers.ticket')
    <div class="actions">
        <a class="btn btn-primary" href="#connexion">Se connecter</a>
        <a class="btn btn-ghost" href="{{ route('tickets.pdf', $voucher->public_token) }}">Télécharger</a>
        @if($zone->whatsappDigits())
            <a class="btn btn-wa" href="https://wa.me/{{ $zone->whatsappDigits() }}?text={{ urlencode('Bonjour, paiement confirmé pour le ticket '.$voucher->username.' ('.$voucher->plan->name.').') }}">WhatsApp</a>
        @endif
    </div>
    <section class="panel" id="connexion">
        <h2>Se connecter</h2>
        <p class="help">Rejoignez {{ $zone->name }}, puis entrez le code {{ $voucher->username }} et le mot de passe du ticket.</p>
    </section>
@endif
<a class="shop-link center" href="{{ route('shop.tickets', $zone->slug) }}">Mes tickets</a>
@endsection
