@extends('layouts.shop')
@section('title', 'Paiement — '.$zone->name)
@section('content')
<a class="back" href="{{ route('shop.plan', [$zone->slug, $plan->id]) }}">Modifier le numéro</a>

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
    <p class="help">Client : {{ $customer['phone'] ?: 'Achat sans compte' }}</p>
</section>

<form class="panel" method="POST" action="{{ route('shop.checkout', $zone->slug) }}" data-wait>
    @csrf
    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
    <input type="hidden" name="phone" value="{{ old('phone', $customer['phone']) }}">
    <input type="hidden" name="name" value="{{ old('name', $customer['name'] ?? '') }}">
    <h2>Comment souhaitez-vous payer ?</h2>
    <p class="help">Aucun ticket n’est créé avant la confirmation du paiement. Si l’opérateur n’est pas configuré, la commande reste en attente.</p>
    <div class="choices">
        @foreach($providers as $key => $label)
            <label class="choice">
                <input type="radio" name="provider" value="{{ $key }}" @checked(old('provider', 'manual') === $key) required>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    <label class="field" for="transaction_reference">Référence <span>(si vous l’avez)</span>
        <input id="transaction_reference" name="transaction_reference" type="text" value="{{ old('transaction_reference') }}" maxlength="80" placeholder="Reçu ou référence comptoir">
    </label>
    <button class="btn btn-primary" type="submit">Payer</button>
</form>
@endsection
