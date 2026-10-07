@extends('layouts.shop')
@section('title', 'Acheter — '.$zone->name)
@section('content')
<section class="shop-hero position-relative">
    <div class="hero-net" aria-hidden="true">
        <svg class="hero-nodes" viewBox="0 0 640 180" preserveAspectRatio="xMidYMid slice">
            <path d="M20 120 L120 50 L250 110 L390 36 L560 100" fill="none" stroke="rgba(125,211,252,.55)" stroke-width="1.4" />
            <path d="M120 50 L160 140 L340 130 L560 100" fill="none" stroke="rgba(125,211,252,.35)" stroke-width="1.2" />
            <g fill="#e0f2fe">
                <circle cx="20" cy="120" r="3.5" />
                <circle cx="120" cy="50" r="3.5" />
                <circle cx="250" cy="110" r="3.5" />
                <circle cx="390" cy="36" r="3.5" />
                <circle cx="560" cy="100" r="3.5" />
                <circle cx="160" cy="140" r="3" />
                <circle cx="340" cy="130" r="3" />
            </g>
        </svg>
        <span class="wifi-arc a1"></span>
        <span class="wifi-arc a2"></span>
        <span class="wifi-arc a3"></span>
    </div>
    <div class="position-relative">
        <p class="status-pill"><span class="dot" aria-hidden="true"></span> WiFi disponible</p>
        <h1>Choisissez votre connexion</h1>
        <p class="lede">Internet rapide, stable et accessible.</p>
        <p class="visually-hidden">Internet rapide et accessible.</p>
        @if($zone->bannerUrl())
            <img class="shop-banner" src="{{ $zone->bannerUrl() }}" alt="">
        @endif
        @if($zone->description)
            <p class="lede">{{ $zone->description }}</p>
        @elseif($zone->slogan)
            <p class="lede">{{ $zone->slogan }}</p>
        @endif
    </div>
</section>

@if($activeVoucher)
    <section class="card live-card border-0 shadow mt-4" aria-label="Connexion active">
        <div class="card-body">
            <p class="status-pill mb-3"><span class="dot" aria-hidden="true"></span> Connexion active</p>
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
                        <dd>{{ $activeVoucher->expires_at->timezone(config('app.timezone'))->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm') }}</dd>
                    </div>
                @endif
            </dl>
            <a class="btn btn-outline-primary btn-lg rounded-pill w-100" href="#forfaits">Acheter une autre connexion</a>
        </div>
    </section>
@endif

<section class="shop-section" id="forfaits" aria-labelledby="plans-title">
    <h2 id="plans-title">Choisissez votre forfait</h2>
    @if($plans->isEmpty())
        <div class="empty">
            <p>Aucun forfait actif pour cette WiFi Zone.</p>
            <p>Aucun forfait n’est en vente pour le moment.</p>
            @auth
                @if((int) auth()->user()->tenant_id === (int) $zone->tenant_id)
                    <a class="btn btn-primary btn-lg rounded-pill" href="{{ route('plans.index') }}">Créez ou activez un forfait dans Forfaits.</a>
                @endif
            @endauth
            @if($zone->whatsappDigits())
                <a class="btn btn-outline-primary btn-lg rounded-pill" href="https://wa.me/{{ $zone->whatsappDigits() }}">Contacter la zone</a>
            @endif
        </div>
    @else
        <div class="row g-3">
            @foreach($plans as $plan)
                @php
                    $price = \App\Support\Money::shop($plan->price, $plan->currency);
                    $featured = $plan->badge === 'populaire';
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <article class="card plan-card h-100 border-0 shadow {{ $featured ? 'is-featured' : '' }}">
                        <div class="card-body d-flex flex-column">
                            @if($plan->badgeLabel())
                                <span class="badge rounded-pill text-bg-warning align-self-start mb-2">{{ $plan->badgeLabel() }}</span>
                            @endif
                            <h3 class="h4 mb-1">{{ $plan->name }}</h3>
                            <p class="plan-price">{{ $price }}</p>
                            <p class="fw-semibold mt-3 mb-1">Connexion pendant {{ $plan->validityLabel() }}</p>
                            @if($plan->description)
                                <p class="text-secondary mb-2">{{ $plan->description }}</p>
                            @endif
                            @if($plan->unlimited_data)
                                <p class="text-secondary mb-3">Illimité</p>
                            @endif
                            <button class="btn btn-primary btn-lg rounded-pill w-100 mt-auto" type="button" data-buy="buy-{{ $plan->id }}" data-fallback="{{ route('shop.plan', [$zone->slug, $plan->id]) }}">Acheter maintenant</button>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        @foreach($plans as $plan)
            @php($price = \App\Support\Money::shop($plan->price, $plan->currency))
            <div class="offcanvas offcanvas-bottom buy-sheet" tabindex="-1" id="buy-{{ $plan->id }}" aria-labelledby="buy-title-{{ $plan->id }}" @if($errors->any() && (int) old('plan_id') === $plan->id) data-reopen @endif>
                <div class="offcanvas-header align-items-start">
                    <div>
                        <p class="eyebrow mb-1">Vous achetez</p>
                        <h2 class="offcanvas-title h3 mb-0" id="buy-title-{{ $plan->id }}">{{ $plan->name }}</h2>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
                </div>
                <form class="offcanvas-body d-flex flex-column" method="POST" action="{{ route('shop.checkout', $zone->slug) }}" data-wait>
                    @csrf
                    @if((int) old('plan_id') === $plan->id)
                        @if($errors->any())
                            <div class="alert alert-danger" role="alert">
                                @foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach
                            </div>
                        @endif
                        @if(session('warning'))
                            <p class="alert alert-warning" role="status">{{ session('warning') }}</p>
                        @endif
                    @endif
                    <p class="plan-price">{{ $price }}</p>
                    <p class="fw-semibold mt-2 mb-0">Connexion pendant {{ $plan->validityLabel() }}</p>
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    @if($knownPhone)
                        <input type="hidden" name="phone" value="{{ $knownPhone }}">
                        <p class="help">Numéro utilisé : {{ $knownPhone }}</p>
                    @else
                        <label class="form-label fw-bold mt-3" for="phone-{{ $plan->id }}">Téléphone</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">+243</span>
                            <input id="phone-{{ $plan->id }}" class="form-control" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="812 345 678" required>
                        </div>
                    @endif
                    <label class="form-label fw-bold mt-3" for="email-{{ $plan->id }}">E-mail <span class="fw-normal">(pour iKeePay)</span></label>
                    <input id="email-{{ $plan->id }}" class="form-control" name="email" type="email" autocomplete="email" value="{{ old('email') }}" maxlength="160" placeholder="client@mail.com">
                    <fieldset class="pay-choices">
                        <legend>Moyen de paiement</legend>
                        <div class="d-grid gap-2 mt-2">
                            @foreach($providers as $key => $label)
                                <label class="pay-option d-flex align-items-center gap-3">
                                    <input class="form-check-input m-0" type="radio" name="provider" value="{{ $key }}" @checked(old('provider', array_key_first($providers)) === $key) required>
                                    <span class="fw-semibold">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="sheet-actions">
                        <button class="btn btn-primary btn-lg rounded-pill w-100" type="submit" data-busy="Paiement en cours">Payer maintenant — {{ $price }}</button>
                        <a class="back text-center" href="{{ route('shop.plan', [$zone->slug, $plan->id]) }}">Modifier le numéro</a>
                    </div>
                </form>
            </div>
        @endforeach
    @endif
</section>
@endsection
