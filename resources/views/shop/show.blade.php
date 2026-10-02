@extends('layouts.shop')
@section('title', 'Acheter — '.$zone->name)
@section('content')
<section class="shop-hero">
    <p class="status-pill"><span class="dot" aria-hidden="true"></span> WiFi disponible</p>
    <h1>Choisissez votre connexion</h1>
    <p class="lede">Internet rapide, stable et accessible.</p>
    <p class="sr-only">Internet rapide et accessible.</p>
    @if($zone->bannerUrl())
        <img class="shop-banner" src="{{ $zone->bannerUrl() }}" alt="">
    @endif
    @if($zone->description)
        <p class="lede">{{ $zone->description }}</p>
    @elseif($zone->slogan)
        <p class="lede">{{ $zone->slogan }}</p>
    @endif
</section>

@if($activeVoucher)
    <section class="live-card" aria-label="Connexion active">
        <p class="status-pill"><span class="dot" aria-hidden="true"></span> Connexion active</p>
        <dl class="summary">
            <div>
                <dt>Forfait</dt>
                <dd>{{ $activeVoucher->plan->name ?? 'Forfait' }}</dd>
            </div>
            <div>
                <dt>Temps restant</dt>
                <dd>{{ $activeVoucher->remainingLabel() }}</dd>
            </div>
            @if($activeVoucher->expires_at)
                <div>
                    <dt>Expiration</dt>
                    <dd>{{ $activeVoucher->expires_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</dd>
                </div>
            @endif
        </dl>
        <a class="btn btn-ghost" href="#forfaits">Acheter une autre connexion</a>
    </section>
@endif

<section class="shop-section" id="forfaits" aria-labelledby="plans-title">
    <h2 id="plans-title">Choisissez votre forfait</h2>
    @if($plans->isEmpty())
        <div class="empty">
            <p>Aucun forfait n’est en vente pour le moment.</p>
            @if($zone->whatsappDigits())
                <a class="btn btn-ghost" href="https://wa.me/{{ $zone->whatsappDigits() }}">Contacter la zone</a>
            @endif
        </div>
    @else
        <div class="plan-list">
            @foreach($plans as $plan)
                @php
                    $price = \App\Support\Money::shop($plan->price, $plan->currency);
                    $featured = $plan->badge === 'populaire' || (int) $plan->duration_seconds === 86400;
                @endphp
                <article class="plan-card {{ $featured ? 'is-featured' : '' }}">
                    @if($plan->badgeLabel())
                        <p class="plan-badge">{{ $plan->badgeLabel() }}</p>
                    @endif
                    <h3>{{ $plan->name }}</h3>
                    <p class="plan-price">{{ $price }}</p>
                    <p class="plan-offer">Connexion pendant {{ $plan->validityLabel() }}</p>
                    @if($plan->unlimited_data)
                        <p class="plan-time">Illimité</p>
                    @endif
                    <button class="btn btn-primary" type="button" data-buy="buy-{{ $plan->id }}" data-fallback="{{ route('shop.plan', [$zone->slug, $plan->id]) }}">Acheter maintenant</button>
                </article>

                <dialog class="buy-sheet" id="buy-{{ $plan->id }}" @if($errors->any() && (int) old('plan_id') === $plan->id) data-reopen @endif>
                    <form method="POST" action="{{ route('shop.checkout', $zone->slug) }}" data-wait>
                        @csrf
                        @if((int) old('plan_id') === $plan->id)
                            @if($errors->any())
                                <div class="note note-bad" role="alert">
                                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                                </div>
                            @endif
                            @if(session('warning'))
                                <p class="note note-warn" role="status">{{ session('warning') }}</p>
                            @endif
                        @endif
                        <div class="sheet-head">
                            <p class="eyebrow">Vous achetez</p>
                            <button class="sheet-close" type="button" data-close aria-label="Fermer">×</button>
                        </div>
                        <h2>{{ $plan->name }}</h2>
                        <p class="plan-price">{{ $price }}</p>
                        <p class="plan-offer">Connexion pendant {{ $plan->validityLabel() }}</p>
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        @if($knownPhone)
                            <input type="hidden" name="phone" value="{{ $knownPhone }}">
                            <p class="help">Numéro utilisé : {{ $knownPhone }}</p>
                        @else
                            <label class="field" for="phone-{{ $plan->id }}">Téléphone
                                <span class="phone-line">
                                    <span class="phone-prefix">+243</span>
                                    <input id="phone-{{ $plan->id }}" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="812 345 678" required>
                                </span>
                            </label>
                        @endif
                        <fieldset class="pay-choices">
                            <legend>Moyen de paiement</legend>
                            <div class="choices">
                                @foreach($providers as $key => $label)
                                    <label class="choice">
                                        <input type="radio" name="provider" value="{{ $key }}" @checked(old('provider', array_key_first($providers)) === $key) required>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="sheet-actions">
                            <button class="btn btn-primary" type="submit" data-busy="Paiement en cours">Payer maintenant — {{ $price }}</button>
                            <a class="back" href="{{ route('shop.plan', [$zone->slug, $plan->id]) }}">Modifier le numéro</a>
                        </div>
                    </form>
                </dialog>
            @endforeach
        </div>
    @endif
</section>
@endsection
