@extends('layouts.shop')
@section('title', 'Commande — '.$zone->name)
@section('content')
@php
    $item = $sale->items->first();
    $plan = $item?->plan ?? $voucher?->plan;
    $payment = $sale->payment;
    $provider = config('limete.payment_providers.'.$payment?->provider, $payment?->provider);
    $processing = $payment?->status === 'processing';
@endphp

@if($sale->status === 'paid' && $voucher)
    @include('shop.journey', ['current' => 4])
    <section class="panel celebrate-panel">
        <p class="celebrate" aria-hidden="true">✓</p>
        <p class="status-pill"><span class="dot" aria-hidden="true"></span> Paiement confirmé</p>
        <h1>Votre ticket est prêt.</h1>
        <dl class="summary">
            <div>
                <dt>Forfait</dt>
                <dd>{{ $plan->name ?? 'Forfait' }}</dd>
            </div>
            <div>
                <dt>Durée</dt>
                <dd>{{ $plan?->validityLabel() ?? 'Selon le forfait' }}</dd>
            </div>
            <div>
                <dt>Identifiant</dt>
                <dd>{{ $voucher->username }}</dd>
            </div>
            <div>
                <dt>Prix</dt>
                <dd>{{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</dd>
            </div>
            <div>
                <dt>Internet</dt>
                <dd>{{ $plan?->unlimited_data ? 'Illimité' : 'Selon le forfait' }}</dd>
            </div>
        </dl>
    </section>
    @include('vouchers.ticket')
    @include('vouchers.network')
    @include('vouchers.actions')
    @if($voucher->status !== 'expired')
        <section class="panel" id="connexion">
            <h2>Se connecter au WiFi</h2>
            <ol class="steps">
                <li>Rejoignez le réseau {{ $zone->name }}.</li>
                <li>Ouvrez le portail qui s’affiche.</li>
                <li>Entrez le code <strong>{{ $voucher->username }}</strong> et le mot de passe du ticket.</li>
            </ol>
            <p class="help">Le temps restant suit la date d’expiration. Il continue même si vous vous déconnectez.</p>
        </section>
    @endif
@elseif(in_array($payment?->status, ['failed', 'cancelled'], true))
    @include('shop.journey', ['current' => 3])
    <section class="panel">
        <p class="status-pill is-bad">{{ $payment->status === 'cancelled' ? 'Paiement annulé' : 'Paiement échoué' }}</p>
        <h1>Le paiement n’a pas été confirmé</h1>
        <p class="help">Aucun ticket n’a été activé. Vous pouvez recommencer depuis la boutique.</p>
        <a class="btn btn-primary" href="{{ route('shop.show', $zone->slug) }}">Retour aux forfaits</a>
    </section>
@else
    @include('shop.journey', ['current' => 3])
    <section class="panel pay-wait" aria-live="polite">
        <div class="spinner" aria-hidden="true"></div>
        @if($processing)
            <p class="status-pill is-wait">Paiement en cours...</p>
        @else
            <p class="status-pill is-wait">Paiement en attente</p>
        @endif
        <h1>Paiement en cours...</h1>
        <dl class="summary">
            <div>
                <dt>Montant</dt>
                <dd>{{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</dd>
            </div>
            <div>
                <dt>Forfait</dt>
                <dd>{{ $plan->name ?? 'Forfait' }}</dd>
            </div>
            <div>
                <dt>Référence</dt>
                <dd>{{ $payment->internal_reference ?? '—' }}</dd>
            </div>
            <div>
                <dt>Moyen de paiement</dt>
                <dd>{{ $provider }}</dd>
            </div>
        </dl>
        @if($payment?->transaction_reference)
            <p class="help">Référence communiquée : {{ $payment->transaction_reference }}</p>
        @endif
        <p class="help">Statut : {{ \App\Enums\PaymentStatus::tryFrom($payment->status ?? '')?->label() ?? 'En attente' }}</p>
        <p class="help">{{ $payment->metadata['note'] ?? 'Le ticket apparaîtra après confirmation du paiement.' }}</p>
        <p class="help">Aucun ticket n’est créé avant la confirmation officielle.</p>
        <p class="help">Le serveur confirme le paiement. Cette page ne le transforme pas en succès.</p>
        <form method="POST" action="{{ route('shop.payment.refresh', [$zone->slug, $sale->public_token]) }}" data-wait>
            @csrf
            <button class="btn btn-primary" type="submit">Vérifier le paiement</button>
        </form>
    </section>
    <div data-poll="15" hidden></div>
@endif
<a class="shop-link center no-print" href="{{ route('shop.tickets', $zone->slug) }}">Mes tickets</a>
@endsection
