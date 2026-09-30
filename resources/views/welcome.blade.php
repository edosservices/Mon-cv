@extends('layouts.landing')
@section('content')
@php
    $hasVideo = is_file(public_path('videos/limete-wifi.mp4'));
    $sample = $offers->first(fn ($plan) => $plan->badge === 'populaire') ?? $offers->first();
    $demo = [
        'plan' => $sample?->name ?: 'Aperçu',
        'price' => $sample ? \App\Support\Money::shop($sample->price, $sample->currency) : 'Aperçu',
        'duration' => $sample?->validityLabel() ?: 'Aperçu',
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
@endphp
<div class="vh-telecom" aria-hidden="true">
    @foreach(['wifi', 'phone', 'lightning-charge', 'ticket-perforated', 'shield-lock', 'credit-card'] as $icon)
        <span class="vh-tel"><x-icon :name="$icon" :size="32" /></span>
    @endforeach
</div>
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
            @if($offers->isEmpty())
                <div class="vh-empty">
                    <p>Aucun forfait n’est en vente pour le moment.</p>
                    <a class="lp-btn" href="{{ route('client.buy') }}">Voir les zones</a>
                </div>
            @else
                <div class="vh-rail" data-rail>
                    <button class="vh-rail-btn prev" type="button" data-rail-prev aria-label="Forfaits précédents" hidden>‹</button>
                    <div class="row flex-nowrap g-3 vh-rail-track" data-rail-track tabindex="0" role="region" aria-label="Forfaits disponibles">
                        @foreach($offers as $plan)
                            @php($zoneLabel = $plan->wifiZone?->displayLabel())
                            <div class="col-10 col-sm-6 col-lg-3">
                                <article class="card h-100 vh-offer {{ $plan->badge === 'populaire' ? 'is-featured border-primary' : '' }}">
                                    <div class="card-body d-flex flex-column">
                                        <span class="vh-ico-bubble" aria-hidden="true">
                                            <x-icon :name="['wifi', 'clock', 'phone', 'lightning-charge'][$loop->index % 4]" :size="22" />
                                        </span>
                                        @if($plan->badgeLabel())
                                            <span class="vh-popular">{{ $plan->badgeLabel() }}</span>
                                        @endif
                                        <p class="vh-offer-zone card-subtitle">{{ $zoneLabel ?: 'LIMETE WIFI' }}</p>
                                        <h3 class="card-title vh-offer-time">{{ mb_strtoupper($plan->validityLabel()) }}</h3>
                                        <p class="vh-offer-price">{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
                                        <p class="vh-offer-name card-text">{{ $plan->name }}</p>
                                        @if($zoneLabel)
                                            <p class="vh-offer-note">Zone : {{ $zoneLabel }}</p>
                                        @endif
                                        @if($plan->tenant && $zoneLabel && strcasecmp($plan->tenant->name, $zoneLabel) !== 0)
                                            <p class="vh-offer-seller">{{ $plan->tenant->name }}</p>
                                        @endif
                                        <a class="lp-btn" href="{{ \App\Support\PublicCatalog::buyUrl($plan) }}">Acheter</a>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                    <button class="vh-rail-btn next" type="button" data-rail-next aria-label="Forfaits suivants" hidden>›</button>
                </div>
                @if($offers->count() >= 36)
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

    <section class="lp-section lm-motion" id="quotidien">
        <div class="container">
            <div class="vh-head is-center">
                <h2>Une connexion pensée pour votre quotidien.</h2>
            </div>
            <div class="vh-video-frame">
                @if($hasVideo)
                    <video data-vh-video controls preload="metadata" muted loop playsinline poster="{{ asset('images/landing/city.webp') }}">
                        <source src="{{ asset('videos/limete-wifi.mp4') }}" type="video/mp4">
                    </video>
                @else
                    <img class="vh-video-still" src="{{ asset('images/landing/city.webp') }}" alt="Skyline d’une grande ville africaine, en attendant la vidéo LIMETE WIFI" width="1920" height="1277" loading="lazy" decoding="async">
                @endif
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion" id="comment">
        <div class="container">
            <div class="vh-head is-center">
                <h2>Comment ça marche ?</h2>
                <p class="lp-lead">En seulement quelques étapes.</p>
            </div>
            <div class="row g-3 vh-explains">
                <div class="col-md-4">
                    <article class="card h-100 border-0 shadow-sm overflow-hidden">
                        <img class="vh-explain" src="{{ asset('images/landing/phone.webp') }}" alt="Personne connectée, portrait en lumière tamisée" width="1400" height="1866" loading="lazy" decoding="async">
                        <div class="card-body"><p class="card-text mb-0">Une connexion pour chaque moment.</p></div>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="card h-100 border-0 shadow-sm overflow-hidden">
                        <img class="vh-explain" src="{{ asset('images/landing/cafe.webp') }}" alt="Téléphone et ordinateur sur une table de travail" width="1600" height="1067" loading="lazy" decoding="async">
                        <div class="card-body"><p class="card-text mb-0">Pour travailler, étudier ou regarder.</p></div>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="card h-100 border-0 shadow-sm overflow-hidden">
                        <img class="vh-explain" src="{{ asset('images/landing/group.webp') }}" alt="Trois personnes réunies autour d’ordinateurs" width="1600" height="1067" loading="lazy" decoding="async">
                        <div class="card-body"><p class="card-text mb-0">Plusieurs appareils, une même connexion.</p></div>
                    </article>
                </div>
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
                <div class="col-lg-7">
                    <figure class="vh-ticket-photo">
                        <img src="{{ asset('images/landing/ticket-limete.webp') }}" alt="Modèle du ticket LIMETE WIFI, avec identifiant, mot de passe et QR" width="1536" height="1024">
                    </figure>
                </div>
                <div class="col-lg-5">
                    <h2>Votre ticket, prêt à vous connecter.</h2>
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
            <div class="col-lg-5">
                <x-brand-logo height="40" alt="LIMETE WIFI MANAGER" />
                <p>Une solution moderne de gestion et de distribution de connexion WiFi.</p>
                <p class="vh-business-note">Gérez votre WiFi comme un vrai business. L’offre est présentée à <strong>5 $</strong> / mois. Cette page n’encaisse aucun paiement. <a href="{{ route('register') }}">Créer mon compte</a></p>
            </div>
            <div class="col-6 col-lg-3">
                <p class="vh-col">Navigation</p>
                <nav aria-label="Pied de page">
                    <a href="#forfaits">Forfaits</a>
                    <a href="#comment">Comment ça marche</a>
                    <a href="#faq">FAQ</a>
                    <a href="#contact">Contact</a>
                </nav>
            </div>
            <div class="col-6 col-lg-4">
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
