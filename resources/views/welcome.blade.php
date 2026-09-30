@extends('layouts.landing')
@section('content')
@php
    $demo = [
        'plan' => '24H',
        'price' => 'Aperçu',
        'duration' => '24 h',
        'username' => '••••••••',
        'password' => '••••••••',
        'qr' => '<svg viewBox="0 0 64 64" aria-hidden="true"><rect width="64" height="64" fill="#fff"/><path fill="#122033" d="M4 4h20v20H4zm4 4v12h12V8zm24-4h20v20H32zm4 4v12h12V8zM4 32h20v20H4zm4 4v12h12V36zm28 0h4v4h-4zm8 0h8v4h-8zm-8 8h12v4H36zm16-8h4v12h-4z"/></svg>',
        'created' => 'exemple',
        'expires' => null,
        'phone' => null,
        'whatsapp' => null,
        'address' => null,
        'color' => '#1463f3',
        'logo' => null,
        'business' => 'LIMETE WIFI',
        'zone' => 'Démonstration',
    ];
    $edos = config('limete.edos_url');
    $edosUrl = is_string($edos) && filter_var($edos, FILTER_VALIDATE_URL) ? $edos : null;
    $payMarks = $payments;
    if (! isset($payMarks['afrimoney'])) {
        $payMarks['afrimoney'] = 'Afrimoney';
    }
    $featured = $offers->first(fn ($plan) => $plan->badge === 'populaire')
        ?? ($offers->count() > 1 ? $offers->get(1) : $offers->first());
    $visibleOffers = $featured
        ? collect([$featured])->concat($offers->reject(fn ($plan) => $plan->is($featured)))->take(4)->values()
        : $offers;
@endphp
<header class="lp-header">
    <div class="container lp-bar">
        <a class="lp-logo" href="{{ route('home') }}">
            <x-brand-logo height="34" alt="LIMETE WIFI MANAGER" />
        </a>
        <button class="lp-burger" type="button" aria-expanded="false" aria-label="Ouvrir le menu"><span></span><span></span><span></span></button>
        <nav class="lp-nav" aria-label="Navigation">
            <a href="{{ route('home') }}">Accueil</a>
            <a href="#forfaits">Forfaits</a>
            <a href="#comment">Comment ça marche</a>
            <a href="#paiement">Paiement</a>
            <a href="#faq">FAQ</a>
        </nav>
        <div class="lp-actions">
            <a class="lp-btn light" href="{{ route('login') }}">Se connecter</a>
            <a class="lp-btn" href="{{ route('client.buy') }}">Acheter</a>
        </div>
    </div>
</header>

<main>
    <section class="vh-hero">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <p class="lp-kicker">Connexion WiFi rapide</p>
                    <h1>Votre connexion.<br>Simplement meilleure.</h1>
                    <p class="lp-lead">Achetez votre forfait WiFi en quelques secondes, sans créer de compte.</p>
                    <div class="lp-cta">
                        <a class="lp-btn" href="{{ route('client.buy') }}">Acheter un forfait</a>
                        <a class="lp-btn line" href="#comment">Comment ça marche</a>
                    </div>
                    <ul class="vh-checks">
                        <li>Sans compte obligatoire</li>
                        <li>Paiement sécurisé</li>
                        <li>Ticket instantané</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <figure class="vh-shot">
                        <img src="{{ asset('images/landing/hero.webp') }}" alt="Jeune femme souriante en tenue africaine, smartphone en main" width="1800" height="1202" fetchpriority="high">
                        <span class="vh-live"><i></i> Connexion disponible</span>
                    </figure>
                </div>
            </div>
        </div>
    </section>

    <section class="vh-trust" aria-label="Repères">
        <div class="container">
            <ul>
                <li>Paiement sécurisé</li>
                <li>Activation rapide</li>
                <li>Sans compte</li>
                <li>QR Code</li>
                <li>Compatible smartphone</li>
            </ul>
        </div>
    </section>

    <section class="lp-section lm-motion" id="forfaits">
        <div class="container">
            <div class="vh-head">
                <h2>Choisissez votre forfait</h2>
                <p class="lp-lead">Une connexion adaptée à vos besoins.</p>
            </div>
            @if($visibleOffers->isEmpty())
                <div class="vh-empty">
                    <p>Aucun forfait n’est en vente pour le moment.</p>
                    <a class="lp-btn" href="{{ route('client.buy') }}">Voir les zones</a>
                </div>
            @else
                <div class="row g-3 justify-content-center">
                    @foreach($visibleOffers as $plan)
                        <div class="col-12 col-sm-6 col-lg-3">
                            <article class="vh-offer {{ $featured && $plan->is($featured) ? 'is-featured' : '' }}">
                                @if($plan->badgeLabel() || ($featured && $plan->is($featured)))
                                    <span class="vh-popular">{{ $plan->badgeLabel() ?: 'Populaire' }}</span>
                                @endif
                                <p class="vh-offer-time">{{ mb_strtoupper($plan->validityLabel()) }}</p>
                                <p class="vh-offer-price">{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
                                <p class="vh-offer-note">{{ $plan->internetLabel() }}</p>
                                <a class="lp-btn" href="{{ \App\Support\PublicCatalog::buyUrl($plan) }}">Acheter</a>
                            </article>
                        </div>
                    @endforeach
                </div>
                @if($offers->count() > 4)
                    <p class="vh-more"><a href="{{ route('client.buy') }}">Voir tous les forfaits</a></p>
                @endif
            @endif
        </div>
    </section>

    <section class="lp-section lm-motion">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <img class="vh-photo" src="{{ asset('images/landing/work.webp') }}" alt="Jeune professionnelle souriante avec un ordinateur portable, en ville" width="1400" height="2097" loading="lazy" decoding="async">
                </div>
                <div class="col-lg-6">
                    <h2>Internet pour votre quotidien</h2>
                    <p class="lp-lead">Travaillez, étudiez, regardez vos contenus préférés et restez connecté avec LIMETE WIFI.</p>
                    <ul class="vh-points">
                        <li><x-icon name="lightning-charge" :size="18" /> Connexion rapide</li>
                        <li><x-icon name="shield-lock" :size="18" /> Connexion sécurisée</li>
                        <li><x-icon name="phone" :size="18" /> Tous vos appareils</li>
                    </ul>
                    <a class="lp-btn" href="{{ route('client.buy') }}">Acheter maintenant</a>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion" id="comment">
        <div class="container">
            <div class="vh-head is-center">
                <h2>Comment ça marche ?</h2>
                <p class="lp-lead">En seulement quelques étapes.</p>
            </div>
            <ol class="vh-timeline">
                <li>
                    <span>01</span>
                    <x-icon name="ticket-perforated" :size="18" />
                    <h3>Choisissez votre forfait</h3>
                </li>
                <li>
                    <span>02</span>
                    <x-icon name="credit-card" :size="18" />
                    <h3>Payez</h3>
                </li>
                <li>
                    <span>03</span>
                    <x-icon name="phone" :size="18" />
                    <h3>Recevez votre ticket</h3>
                </li>
                <li>
                    <span>04</span>
                    <x-icon name="wifi" :size="18" />
                    <h3>Connectez-vous</h3>
                </li>
            </ol>
        </div>
    </section>

    <section class="lp-section lm-motion" id="tickets">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <x-limete-ticket
                        username="akm"
                        password="867"
                        plan="2 JOURS"
                        price="2,000 FC"
                        number="29"
                        login="http://limetewifi.cd"
                        :qr="$demo['qr']"
                    />
                    <p class="vh-demo-note">Modèle visuel. Ce n’est pas un ticket client.</p>
                </div>
                <div class="col-lg-6">
                    <h2>Votre ticket, immédiatement après le paiement</h2>
                    <p class="lp-lead">Tout ce qu’il vous faut pour vous connecter.</p>
                    <ul class="vh-checks">
                        <li>Nom d’utilisateur</li>
                        <li>Mot de passe</li>
                        <li>QR Code</li>
                        <li>Durée du forfait</li>
                        <li>Date d’expiration</li>
                    </ul>
                    <p class="vh-scan">Scannez. Connectez-vous. Profitez.</p>
                </div>
            </div>
            <details class="vh-models">
                <summary>Modèles d’impression</summary>
                <div class="lp-tickets">
                    @foreach(['classique' => 'Classique', 'moderne' => 'Moderne', 'premium' => 'Premium'] as $key => $label)
                        <div>
                            @include('vouchers.templates.'.$key, ['ticket' => $demo])
                            <p class="lp-caption">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
    </section>

    <section class="lp-section lm-motion" id="paiement">
        <div class="container">
            <div class="vh-head is-center">
                <h2>Payez comme vous voulez</h2>
                <p class="lp-lead">Choisissez votre moyen de paiement préféré.</p>
            </div>
            <x-payment-marks :payments="$payMarks" />
        </div>
    </section>

    <section class="vh-banner lm-motion">
        <img src="{{ asset('images/landing/city.webp') }}" alt="Skyline d’une grande ville africaine au soleil" width="1920" height="1277" loading="lazy" decoding="async">
        <div class="vh-banner-copy">
            <h2>Restez connecté à ce qui compte.</h2>
            <p>Une connexion pensée pour votre quotidien.</p>
            <a class="lp-btn" href="{{ route('client.buy') }}">Acheter un forfait</a>
        </div>
    </section>

    <section class="lp-section lm-motion" id="faq">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="vh-head is-center">
                        <h2>Questions fréquentes</h2>
                    </div>
                    <div class="accordion vh-accordion" id="lp-faq">
                        @foreach([
                            ['compte', 'Ai-je besoin d’un compte ?', 'Non. Vous choisissez un forfait et payez sans créer de compte. Un espace client existe si vous voulez retrouver vos tickets plus tard.'],
                            ['acheter', 'Comment acheter un forfait ?', 'Ouvrez les forfaits, choisissez une durée, puis continuez vers le paiement déjà en place.'],
                            ['recevoir', 'Comment recevoir mon ticket ?', 'Dès que le paiement est confirmé, le ticket affiche l’identifiant, le mot de passe et le QR.'],
                            ['qr', 'Comment utiliser le QR Code ?', 'Scannez le QR du ticket. Il ouvre le ticket. Le mot de passe se lit à côté, il n’est pas caché dans le QR.'],
                            ['duree', 'Combien de temps mon ticket reste-t-il valide ?', 'La durée est celle du forfait choisi. Elle est indiquée avant le paiement et sur le ticket.'],
                            ['moyens', 'Quels moyens de paiement sont disponibles ?', 'Ceux qui sont activés pour la zone, à l’étape de paiement. Cette page présente les moyens de la plateforme.'],
                        ] as $item)
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="q-{{ $item[0] }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#a-{{ $item[0] }}" aria-expanded="false" aria-controls="a-{{ $item[0] }}">{{ $item[1] }}</button>
                                </h3>
                                <div id="a-{{ $item[0] }}" class="accordion-collapse collapse" data-bs-parent="#lp-faq">
                                    <div class="accordion-body">{{ $item[2] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion">
        <div class="container">
            <div class="vh-final">
                <h2>Prêt à vous connecter ?</h2>
                <p>Choisissez votre forfait et profitez immédiatement de votre connexion LIMETE WIFI.</p>
                <a class="lp-btn" href="{{ route('client.buy') }}">Acheter mon forfait</a>
            </div>
        </div>
    </section>
</main>

<footer class="lp-footer" id="contact">
    <div class="container">
        <div class="row g-4 vh-foot">
            <div class="col-lg-4">
                <x-brand-logo height="40" alt="LIMETE WIFI MANAGER" />
                <p>Une solution moderne de gestion et de distribution de connexion WiFi.</p>
                <p class="vh-business-note">Gérez votre WiFi comme un vrai business. L’offre est présentée à <strong>5 $</strong> / mois. Cette page n’encaisse aucun paiement. <a href="{{ route('register') }}">Créer mon compte</a></p>
            </div>
            <div class="col-6 col-lg-2">
                <p class="vh-col">Navigation</p>
                <nav aria-label="Pied de page">
                    <a href="#forfaits">Forfaits</a>
                    <a href="#comment">Comment ça marche</a>
                    <a href="#faq">FAQ</a>
                    <a href="#contact">Contact</a>
                </nav>
            </div>
            <div class="col-6 col-lg-3">
                <p class="vh-col">Paiements</p>
                <x-payment-marks :payments="$payMarks" />
            </div>
            <div class="col-lg-3">
                <p class="vh-col">Suivez-nous</p>
                <x-social-links />
            </div>
        </div>
        <div class="vh-copy">
            <div>
                <p>© {{ now()->year }} LIMETE WIFI MANAGER. Tous droits réservés.</p>
                <p class="vh-muted">Photographies : Unsplash, licence Unsplash.</p>
            </div>
            @if($edosUrl)
                <a class="vh-edos" href="{{ $edosUrl }}" target="_blank" rel="noopener noreferrer">
                    <img src="{{ asset('brand/edos-services.png') }}" alt="Design by EDOS SERVICES" width="220" height="74">
                </a>
            @else
                <img class="vh-edos" src="{{ asset('brand/edos-services.png') }}" alt="Design by EDOS SERVICES" width="220" height="74">
            @endif
        </div>
    </div>
</footer>
@endsection
