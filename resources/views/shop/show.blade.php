@extends('layouts.shop')
@section('title', $zone->name)
@section('content')
<section class="shop-hero">
    <p class="status-pill"><span class="dot" aria-hidden="true"></span> WiFi disponible</p>
    <h1>{{ $zone->name }}</h1>
    @if($zone->description)
        <p class="lede">{{ $zone->description }}</p>
    @else
        <p class="lede">Choisissez un forfait, payez, puis recevez votre ticket.</p>
    @endif
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
                <article class="plan-card" style="animation-delay: {{ $loop->index * 40 }}ms">
                    <p class="plan-kicker">Forfait</p>
                    <h3>{{ $plan->name }}</h3>
                    <p class="plan-price">{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
                    <p class="plan-meta">{{ $plan->unlimited_data ? 'Internet illimité' : ($plan->description ?: 'Selon le forfait') }}</p>
                    <p class="plan-meta">Validité : {{ $plan->validityLabel() }}</p>
                    <a class="btn btn-primary" href="{{ route('shop.plan', [$zone->slug, $plan->id]) }}">Acheter</a>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
