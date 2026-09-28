@extends('layouts.shop')
@section('title', $plan->name.' — '.$zone->name)
@section('content')
<a class="back" href="{{ route('shop.show', $zone->slug) }}">Tous les forfaits</a>

<section class="panel">
    <p class="eyebrow">Votre forfait</p>
    <h1>{{ $plan->name }}</h1>
    <p class="plan-price">{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
    <ul class="facts">
        <li>{{ $plan->unlimited_data ? 'Internet illimité' : ($plan->description ?: 'Selon le forfait') }}</li>
        <li>Validité : {{ $plan->validityLabel() }}</li>
        <li>Le temps démarre à la confirmation du paiement et continue même si vous vous déconnectez.</li>
    </ul>
</section>

<form class="panel" method="POST" action="{{ route('shop.checkout', $zone->slug) }}" data-wait>
    @csrf
    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
    <h2>Comment souhaitez-vous payer ?</h2>
    <p class="help">Aucun ticket n’est créé avant la confirmation du paiement. Si l’opérateur n’est pas configuré, la commande reste en attente. Revenir sur cette page ne confirme pas le paiement.</p>
    <label class="field">Téléphone
        <input name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="+243 …" required>
    </label>
    <label class="field">Nom <span>(facultatif)</span>
        <input name="name" type="text" autocomplete="name" value="{{ old('name') }}" maxlength="120">
    </label>
    <div class="choices">
        @foreach($providers as $key => $label)
            <label class="choice">
                <input type="radio" name="provider" value="{{ $key }}" @checked(old('provider', 'manual') === $key) required>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    <label class="field">Référence <span>(si vous l’avez)</span>
        <input name="transaction_reference" type="text" value="{{ old('transaction_reference') }}" maxlength="80" placeholder="Reçu ou référence comptoir">
    </label>
    <button class="btn btn-primary" type="submit">Confirmer la commande</button>
</form>
@endsection
