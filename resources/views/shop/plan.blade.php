@extends('layouts.shop')
@section('title', $plan->name.' — '.$zone->name)
@section('content')
<a class="back" href="{{ route('shop.show', $zone->slug) }}">Tous les forfaits</a>

<section class="panel" aria-labelledby="plan-title">
    <p class="eyebrow">Votre forfait</p>
    <h1 id="plan-title">{{ $plan->name }}</h1>
    <dl class="summary">
        <div>
            <dt>Durée</dt>
            <dd>{{ $plan->validityLabel() }}</dd>
        </div>
        <div>
            <dt>Internet</dt>
            <dd>{{ $plan->unlimited_data ? 'Illimité' : 'Selon le forfait' }}</dd>
        </div>
        <div>
            <dt>Prix</dt>
            <dd class="plan-price">{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</dd>
        </div>
    </dl>
    <p class="help">Le temps démarre à la confirmation du paiement et continue même si vous vous déconnectez.</p>
</section>

<form class="panel" method="POST" action="{{ route('shop.customer', [$zone->slug, $plan->id]) }}" data-wait>
    @csrf
    <h2>Entrez votre numéro de téléphone</h2>
    <p class="help">Ce numéro sert à retrouver votre ticket. Il ne confirme pas le paiement.</p>
    <label class="field" for="phone">Téléphone
        <span class="phone-line">
            <span class="phone-prefix">+243</span>
            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="812 345 678" required>
        </span>
    </label>
    <label class="field" for="name">Nom <span>(facultatif)</span>
        <input id="name" name="name" type="text" autocomplete="name" value="{{ old('name') }}" maxlength="120">
    </label>
    <button class="btn btn-primary" type="submit">Continuer</button>
</form>
@endsection
