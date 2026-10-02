@extends('layouts.shop')
@section('title', 'Commande — '.$zone->name)
@section('content')
@php
    $item = $sale->items->first();
    $plan = $item?->plan ?? $voucher?->plan;
    $payment = $sale->payment;
    $provider = config('limete.payment_providers.'.$payment?->provider, $payment?->provider);
@endphp

@if($sale->status === 'paid' && $voucher)
    <section class="panel celebrate-panel">
        <p class="confirm-mark mx-auto mb-3" aria-hidden="true">✓</p>
        <div class="alert alert-success d-inline-flex align-items-center gap-2 mb-3" role="status">
            <span class="dot" aria-hidden="true"></span> Paiement confirmé
        </div>
        <h1>Votre ticket est prêt.</h1>
        <p class="lede">Votre connexion est maintenant active.</p>
        <dl class="summary text-start">
            <div>
                <dt>Forfait</dt>
                <dd>{{ $plan->name ?? 'Forfait' }}</dd>
            </div>
            <div>
                <dt>Prix payé</dt>
                <dd>{{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</dd>
            </div>
            @if($voucher->activated_at)
                <div>
                    <dt>Activation</dt>
                    <dd>{{ $voucher->activated_at->timezone(config('app.timezone'))->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm') }}</dd>
                </div>
            @endif
            @if($voucher->expires_at)
                <div>
                    <dt>Expiration</dt>
                    <dd>{{ $voucher->expires_at->timezone(config('app.timezone'))->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm') }}</dd>
                </div>
            @endif
            <div>
                <dt>Identifiant</dt>
                <dd>{{ $voucher->username }}</dd>
            </div>
        </dl>
        <a class="btn btn-primary btn-lg rounded-pill w-100" href="{{ $zone->captiveLoginUrl() ?: '#connexion' }}">Se connecter maintenant</a>
        <a class="btn btn-outline-primary btn-lg rounded-pill w-100" href="{{ route('tickets.public', $voucher->public_token) }}">Voir mon ticket</a>
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
    <section class="panel">
        <div class="alert alert-danger" role="alert">{{ $payment->status === 'cancelled' ? 'Paiement annulé' : 'Paiement échoué' }}</div>
        <h1>Le paiement n’a pas été confirmé</h1>
        <p class="help">Aucun ticket n’a été activé. Vous pouvez recommencer depuis la boutique.</p>
        <a class="btn btn-primary btn-lg rounded-pill w-100" href="{{ route('shop.show', $zone->slug) }}">Réessayer</a>
    </section>
@else
    <section class="panel" aria-live="polite">
        <div class="alert alert-info d-flex align-items-center gap-3" role="status">
            <div class="spinner-border text-info" role="status" aria-hidden="true"></div>
            <div>
                <strong class="d-block">Paiement en attente</strong>
                <span>Paiement en cours</span>
            </div>
        </div>
        <h1>Paiement en cours</h1>
        <dl class="summary text-start">
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
        <p class="help">Le serveur confirme le paiement. Cette page ne le transforme pas en succès.</p>
        <form method="POST" action="{{ route('shop.payment.refresh', [$zone->slug, $sale->public_token]) }}" data-wait>
            @csrf
            <button class="btn btn-primary btn-lg rounded-pill w-100" type="submit" data-busy="Vérification…">Vérifier le paiement</button>
        </form>
    </section>
    <div data-poll="15" hidden></div>
@endif
<a class="shop-link center" href="{{ route('shop.tickets', $zone->slug) }}">Mes tickets</a>
@endsection
