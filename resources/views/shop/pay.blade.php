@extends('layouts.shop')
@section('title', 'Paiement — '.$zone->name)
@section('content')
<a class="back" href="{{ route('shop.plan', [$zone->slug, $plan->id]) }}">Modifier le numéro</a>
@include('shop.journey', ['current' => 2])

<section class="panel" aria-labelledby="order-title">
    <p class="eyebrow">Votre commande</p>
    <h1 id="order-title">{{ $plan->name }}</h1>
    <dl class="summary">
        <div>
            <dt>Zone</dt>
            <dd>{{ $zone->displayLabel() }}</dd>
        </div>
        <div>
            <dt>Durée</dt>
            <dd>{{ $plan->validityLabel() }}</dd>
        </div>
        <div>
            <dt>Prix</dt>
            <dd>{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</dd>
        </div>
    </dl>
    <p class="plan-offer">{{ $plan->internetLabel() }}</p>
    <p class="plan-price">{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
    <p class="help">Achat sans compte.</p>
    <p class="help">Bénéficiaire : {{ $customer['phone'] ?: '—' }}</p>
    @if(($customer['purchase_for'] ?? 'self') === 'other')
        <p class="help">Paiement depuis : {{ $customer['payer_phone'] ?? '—' }}</p>
    @endif
    @if(filled($customer['email'] ?? null))
        <p class="help">E-mail : {{ $customer['email'] }}</p>
    @endif
</section>

@if($ikeepayReady ?? false)
    <form class="panel" method="POST" action="{{ route('shop.checkout', $zone->slug) }}" data-wait>
        @csrf
        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
        <input type="hidden" name="phone" value="{{ old('phone', $customer['phone']) }}">
        <input type="hidden" name="payer_phone" value="{{ old('payer_phone', $customer['payer_phone'] ?? '') }}">
        <input type="hidden" name="purchase_for" value="{{ old('purchase_for', $customer['purchase_for'] ?? 'self') }}">
        <input type="hidden" name="name" value="{{ old('name', $customer['name'] ?? '') }}">
        <input type="hidden" name="email" value="{{ old('email', $customer['email'] ?? '') }}">
        <input type="hidden" name="provider" value="ikeepay">
        <h2>Choisissez votre mode de paiement</h2>
        <p class="help">La page de paiement sécurisée affiche les moyens disponibles. Votre ticket WiFi sera créé après confirmation.</p>
        <button class="btn btn-primary" type="submit">Payer</button>
    </form>
@else
    <section class="panel">
        <h2>Paiement indisponible</h2>
        <p class="help">Le paiement en ligne de cette zone n’est pas encore disponible. Aucune commande ne sera créée.</p>
        <button class="btn btn-primary" type="button" disabled>Payer</button>
    </section>
@endif
@endsection
