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

<form class="panel" method="POST" action="{{ route('shop.checkout', $zone->slug) }}" data-wait>
    @csrf
    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
    <input type="hidden" name="phone" value="{{ old('phone', $customer['phone']) }}">
    <input type="hidden" name="payer_phone" value="{{ old('payer_phone', $customer['payer_phone'] ?? '') }}">
    <input type="hidden" name="purchase_for" value="{{ old('purchase_for', $customer['purchase_for'] ?? 'self') }}">
    <input type="hidden" name="name" value="{{ old('name', $customer['name'] ?? '') }}">
    <input type="hidden" name="email" value="{{ old('email', $customer['email'] ?? '') }}">
    <h2>Payer avec iKeePay</h2>
    <p class="help">Le montant vient du forfait. Le paiement reste en attente jusqu’à la confirmation du serveur.</p>
    <button class="btn btn-primary" type="submit" name="provider" value="ikeepay">Payer avec iKeePay</button>
</form>

@if(($ikeepayChoices ?? []) !== [])
    <form class="panel" method="POST" action="{{ route('shop.checkout', $zone->slug) }}" data-wait>
        @csrf
        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
        <input type="hidden" name="phone" value="{{ old('phone', $customer['phone']) }}">
        <input type="hidden" name="name" value="{{ old('name', $customer['name'] ?? '') }}">
        <input type="hidden" name="email" value="{{ old('email', $customer['email'] ?? '') }}">
        <h2>Paiement mobile iKeePay</h2>
        <p class="help">Choisissez un pays et un opérateur pris en charge. Le montant reste celui du forfait.</p>
        <label class="field" for="ikeepay_method">Pays et opérateur
            <select id="ikeepay_method" name="ikeepay_method" required>
                @foreach($ikeepayChoices as $choice)
                    <option value="{{ $choice['country'] }}|{{ $choice['operator'] }}">{{ $choice['country'] }} — {{ $choice['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="field" for="otp">Code OTP <span>(si l’opérateur le demande)</span>
            <input id="otp" name="otp" value="{{ old('otp') }}" inputmode="numeric" maxlength="12" autocomplete="one-time-code">
        </label>
        <button class="btn btn-primary" type="submit" name="provider" value="ikeepay">Payer avec cet opérateur</button>
    </form>
@endif

<form class="panel" method="POST" action="{{ route('shop.checkout', $zone->slug) }}" data-wait>
    @csrf
    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
    <input type="hidden" name="phone" value="{{ old('phone', $customer['phone']) }}">
    <input type="hidden" name="payer_phone" value="{{ old('payer_phone', $customer['payer_phone'] ?? '') }}">
    <input type="hidden" name="purchase_for" value="{{ old('purchase_for', $customer['purchase_for'] ?? 'self') }}">
    <input type="hidden" name="name" value="{{ old('name', $customer['name'] ?? '') }}">
    <input type="hidden" name="email" value="{{ old('email', $customer['email'] ?? '') }}">
    <h2>Comment souhaitez-vous payer ?</h2>
    <p class="help">Aucun ticket n’est créé avant la confirmation du paiement. Si l’opérateur n’est pas configuré, la commande reste en attente.</p>
    <div class="choices">
        @foreach($providers as $key => $label)
            @continue($key === 'ikeepay')
            <label class="choice">
                <input type="radio" name="provider" value="{{ $key }}" @checked(old('provider', 'manual') === $key) required>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    <label class="field" for="transaction_reference">Référence <span>(si vous l’avez)</span>
        <input id="transaction_reference" name="transaction_reference" type="text" value="{{ old('transaction_reference') }}" maxlength="80" placeholder="Reçu ou référence comptoir">
    </label>
    <p class="help">Confirmation : le ticket n’est créé qu’après la réponse officielle du moyen de paiement.</p>
    <button class="btn btn-primary" type="submit">Confirmer et payer</button>
</form>
@endsection
