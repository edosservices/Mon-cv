@extends('layouts.shop')
@section('title', $plan->name.' — '.$zone->name)
@section('content')
<a class="back" href="{{ route('shop.show', $zone->slug) }}">Tous les forfaits</a>
@include('shop.journey', ['current' => 1])

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
    <p class="help">Achat sans compte. Le numéro du bénéficiaire suffit. Le nom et l’e-mail sont facultatifs.</p>
    <fieldset>
        <legend class="field">Pour qui ?</legend>
        <label class="choice"><input type="radio" name="purchase_for" value="self" @checked(old('purchase_for', 'self') === 'self')> <span>Pour moi</span></label>
        <label class="choice"><input type="radio" name="purchase_for" value="other" @checked(old('purchase_for') === 'other')> <span>Pour quelqu’un d’autre</span></label>
    </fieldset>
    <label class="field" for="phone">Numéro du bénéficiaire
        <span class="phone-line">
            <span class="phone-prefix">+243</span>
            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required value="{{ old('phone', auth()->user()?->isClient() ? auth()->user()->client_phone : '') }}" placeholder="812 345 678">
        </span>
    </label>
    <label class="field" for="payer_phone">Numéro qui paie <span>(si c’est pour quelqu’un d’autre)</span>
        <input id="payer_phone" name="payer_phone" type="tel" inputmode="tel" value="{{ old('payer_phone') }}" placeholder="+243">
    </label>
    <label class="field" for="name">Nom <span>(facultatif)</span>
        <input id="name" name="name" type="text" autocomplete="name" value="{{ old('name') }}" maxlength="120">
    </label>
    <label class="field" for="email">E-mail <span>(facultatif, ce n’est pas un compte)</span>
        <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" maxlength="160" placeholder="client@mail.com">
    </label>
    <button class="btn btn-primary" type="submit">Continuer</button>
    <p class="help">Un compte reste facultatif pour <a href="{{ route('client.login') }}">retrouver ses tickets</a>.</p>
</form>
@endsection
