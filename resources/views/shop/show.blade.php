@extends('layouts.shop')
@section('title', $zone->name)
@section('content')
<section class="shop-hero">
    <p class="status-pill"><span class="dot" aria-hidden="true"></span> WiFi disponible</p>
    <h1>Internet rapide et accessible</h1>
    @if($zone->sloganLine())
        <p class="lede">{{ $zone->sloganLine() }}</p>
    @endif
    @if($zone->bannerUrl())
        <img class="shop-logo" src="{{ $zone->bannerUrl() }}" alt="">
    @endif
    @if($zone->description)
        <p class="lede">{{ $zone->description }}</p>
    @else
        <p class="lede">Choisissez un forfait, payez, puis recevez votre ticket.</p>
    @endif
    <ol class="path" aria-label="Parcours">
        <li>Choisir</li>
        <li>Payer</li>
        <li>Recevoir</li>
    </ol>
    @include('shop.journey', ['current' => 1])
</section>

<section class="shop-section" aria-labelledby="plans-title">
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
                <article class="plan-card">
                    @if($plan->badgeLabel())
                        <p class="plan-badge">{{ $plan->badgeLabel() }}</p>
                    @endif
                    <h3>{{ $plan->name }}</h3>
                    <p class="plan-price">{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
                    <p class="plan-offer">{{ $plan->internetLabel() }}</p>
                    @if($plan->unlimited_data)
                        <p class="plan-time">Illimité</p>
                    @endif
                    <p class="plan-time">{{ $plan->validityLabel() }}</p>
                    <a class="btn btn-primary" href="{{ route('shop.plan', [$zone->slug, $plan->id]) }}">Acheter</a>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
