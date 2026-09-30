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
@endphp
<header class="lp-header">
    <div class="lp-wrap lp-bar">
        <a class="lp-logo" href="{{ route('home') }}">
            <x-brand-logo height="36" alt="LIMETE WIFI MANAGER" />
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
            <a class="lp-btn" href="{{ route('client.buy') }}">Acheter un forfait</a>
        </div>
    </div>
</header>

<main>
    <section class="vh-hero">
        <div class="lp-wrap vh-hero-grid">
            <div>
                <p class="lp-kicker">LIMETE WIFI</p>
                <h1>Internet rapide.<br>Simple. Accessible.</h1>
                <p class="lp-lead">Achetez votre forfait WiFi LIMETE en quelques secondes, sans créer de compte.</p>
                <div class="lp-cta">
                    <a class="lp-btn" href="{{ route('client.buy') }}">Acheter un forfait</a>
                    <a class="lp-btn line" href="#comment">Comment ça marche ?</a>
                </div>
            </div>
            <figure class="vh-shot">
                <img src="{{ asset('images/landing/hero.webp') }}" alt="Jeune femme souriante en tenue africaine, smartphone en main" width="1800" height="1202" fetchpriority="high">
                <span class="vh-chip">WiFi haut débit</span>
                <span class="vh-live"><i></i> Connexion disponible</span>
            </figure>
        </div>
    </section>

    <section class="lp-section lm-motion" id="forfaits">
        <div class="lp-wrap">
            <h2>Choisissez votre forfait</h2>
            <p class="lp-lead">Les cartes reprennent les forfaits en vente. Le bouton ouvre le paiement déjà en place.</p>
            @if($offers->isEmpty())
                <div class="vh-empty">
                    <p>Aucun forfait n’est en vente pour le moment.</p>
                    <a class="lp-btn" href="{{ route('client.buy') }}">Voir les zones</a>
                </div>
            @else
                <div class="vh-passes">
                    @foreach($offers as $plan)
                        <article class="vh-pass">
                            <div class="vh-pass-top">
                                <x-brand-logo height="22" alt="" />
                                <span class="vh-chip-mark" aria-hidden="true"></span>
                            </div>
                            <p class="vh-pass-name">{{ $plan->validityLabel() }}</p>
                            <p class="vh-pass-offer">{{ $plan->internetLabel() }}</p>
                            <p class="vh-pass-meta">{{ $plan->name }}@if($plan->wifiZone) · {{ $plan->wifiZone->name }}@endif</p>
                            <p class="vh-pass-price">{{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
                            <div class="vh-pass-foot">
                                <span>LIMETE WIFI</span>
                                <a href="{{ \App\Support\PublicCatalog::buyUrl($plan) }}">Acheter</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="lp-section lm-motion" id="comment">
        <div class="lp-wrap">
            <h2>Comment ça marche</h2>
            <ol class="vh-steps">
                <li>
                    <span>01</span>
                    <x-icon name="ticket-perforated" />
                    <h3>Choisissez votre forfait</h3>
                    <p>Durée et prix sont affichés avant le paiement.</p>
                </li>
                <li>
                    <span>02</span>
                    <x-icon name="credit-card" />
                    <h3>Payez avec votre moyen préféré</h3>
                    <p>Le parcours utilise les moyens configurés pour la zone.</p>
                </li>
                <li>
                    <span>03</span>
                    <x-icon name="phone" />
                    <h3>Recevez immédiatement votre ticket</h3>
                    <p>Identifiant, mot de passe et QR arrivent après confirmation.</p>
                </li>
                <li>
                    <span>04</span>
                    <x-icon name="wifi" />
                    <h3>Connectez-vous au WiFi</h3>
                    <p>Ouvrez le réseau de la zone et entrez le ticket.</p>
                </li>
            </ol>
        </div>
    </section>

    <section class="lp-section lm-motion" id="paiement">
        <div class="lp-wrap">
            <h2>Payez avec votre moyen préféré</h2>
            <p class="lp-lead">Vous payez à l’étape suivante, avec les moyens activés pour la zone.</p>
            <x-payment-marks :payments="$payments" />
        </div>
    </section>

    <section class="lp-section lm-motion">
        <div class="lp-wrap">
            <h2>Pourquoi LIMETE WIFI</h2>
            <div class="vh-benefits">
                <article>
                    <x-icon name="clock" />
                    <h3>24/7</h3>
                    <p>Connexion</p>
                </article>
                <article>
                    <x-icon name="lightning-charge" />
                    <h3>Activation</h3>
                    <p>Rapide, dès que le paiement est confirmé.</p>
                </article>
                <article>
                    <x-icon name="shield-lock" />
                    <h3>Paiement</h3>
                    <p>Sécurisé, via le parcours déjà en place.</p>
                </article>
                <article>
                    <x-icon name="person" />
                    <h3>Sans compte</h3>
                    <p>Obligatoire pour personne. Le compte client reste facultatif.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion" id="apercu" aria-labelledby="demo-charts">
        <div class="lp-wrap">
            <h2 id="demo-charts">Lecture d’une journée type</h2>
            <p class="lp-lead">Démonstration. Ces courbes ne mesurent aucun réseau, aucune vente et aucun client LIMETE.</p>
            <div class="vh-charts">
                <article>
                    <h3>Performance du réseau</h3>
                    <p class="vh-muted">Indice d’exemple, pas une mesure du réseau.</p>
                    <div class="vh-chart"><canvas data-chart="performance" role="img" aria-label="Courbe illustrative de performance, données d’exemple"></canvas></div>
                </article>
                <article>
                    <h3>Utilisation du WiFi</h3>
                    <p class="vh-muted">Indice d’exemple, pas une mesure des clients.</p>
                    <div class="vh-chart"><canvas data-chart="usage" role="img" aria-label="Barres illustratives d’utilisation, données d’exemple"></canvas></div>
                </article>
                <article>
                    <h3>Disponibilité</h3>
                    <p class="vh-muted">Répartition d’une journée type, pas un taux réel.</p>
                    <div class="vh-chart"><canvas data-chart="availability" role="img" aria-label="Répartition illustrative d’une journée, données d’exemple"></canvas></div>
                </article>
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion">
        <div class="lp-wrap vh-split">
            <img class="vh-photo" src="{{ asset('images/landing/phone.webp') }}" alt="Jeune femme consultant son smartphone" width="1600" height="1068" loading="lazy" decoding="async">
            <div>
                <h2>Le téléphone suffit</h2>
                <p class="lp-lead">Choisissez un forfait, payez, puis gardez le ticket sur l’écran. Aucune application à installer pour acheter.</p>
                <a class="lp-btn" href="#forfaits">Voir les forfaits</a>
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion">
        <div class="lp-wrap vh-split vh-flip">
            <img class="vh-photo" src="{{ asset('images/landing/group.webp') }}" alt="Trois personnes réunies autour d’ordinateurs portables" width="1600" height="1067" loading="lazy" decoding="async">
            <div>
                <h2>Une connexion à partager sur place</h2>
                <p class="lp-lead">Chaque zone vend ses propres forfaits. Vous achetez celui de l’endroit où vous êtes.</p>
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion">
        <div class="lp-wrap vh-split">
            <img class="vh-photo" src="{{ asset('images/landing/work.webp') }}" alt="Jeune professionnelle souriante avec un ordinateur portable, en ville" width="1400" height="2097" loading="lazy" decoding="async">
            <div>
                <h2>Rester en ligne pour travailler</h2>
                <p class="lp-lead">Un forfait plus long couvre une journée ou plusieurs jours, selon ce que la zone propose.</p>
                <a class="lp-btn" href="{{ route('client.buy') }}">Acheter maintenant</a>
            </div>
        </div>
    </section>

    <section class="vh-banner lm-motion">
        <img src="{{ asset('images/landing/city.webp') }}" alt="Rue urbaine animée, symbole d’un réseau disponible en ville" width="1920" height="1280" loading="lazy" decoding="async">
        <div class="vh-banner-copy">
            <h2>Le WiFi de la zone, là où vous êtes</h2>
            <a class="lp-btn" href="{{ route('client.buy') }}">Choisir une zone</a>
        </div>
    </section>

    <section class="lp-section lm-motion" id="sans-compte">
        <div class="lp-wrap vh-reassure">
            <div>
                <h2>Achetez votre connexion sans créer de compte</h2>
                <p class="lp-lead">Pas besoin de compte. Choisissez votre forfait, effectuez votre paiement et recevez votre ticket WiFi.</p>
                <a class="lp-btn" href="{{ route('client.buy') }}">Acheter maintenant</a>
            </div>
            <ol class="vh-path" aria-label="Parcours d’achat">
                <li>Forfait</li>
                <li>Paiement</li>
                <li>Ticket</li>
                <li>Connexion</li>
            </ol>
        </div>
    </section>

    <section class="lp-section lm-motion" id="tickets">
        <div class="lp-wrap">
            <h2>Le ticket, une fois le paiement confirmé</h2>
            <p class="lp-lead">Démonstration visuelle. Ce n’est pas un ticket client. Aucun identifiant réel n’est affiché.</p>
            <div class="vh-ticket-hero">
                <x-brand-logo height="28" alt="" />
                <p class="vh-pass-name">24H</p>
                <p>Internet haut débit</p>
                <dl>
                    <div><dt>Username</dt><dd>••••••••</dd></div>
                    <div><dt>Password</dt><dd>••••••••</dd></div>
                </dl>
                <div class="vh-qr" aria-hidden="true">{!! $demo['qr'] !!}</div>
                <p>Scannez pour vous connecter</p>
            </div>
            <div class="lp-tickets mt-4">
                @foreach(['classique' => 'Classique', 'moderne' => 'Moderne', 'premium' => 'Premium'] as $key => $label)
                    <div>
                        @include('vouchers.templates.'.$key, ['ticket' => $demo])
                        <p class="lp-caption">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion" id="faq">
        <div class="lp-wrap">
            <h2>Questions fréquentes</h2>
            <div class="mt-4 d-grid gap-2">
                @foreach([
                    ['Dois-je avoir un compte pour acheter un ticket ?', 'Non. Le parcours public permet de choisir une WiFi Zone et un forfait sans compte. Un espace client existe aussi pour retrouver ses tickets.'],
                    ['Comment je reçois le ticket ?', 'Après confirmation du paiement, le ticket affiche l’identifiant, le mot de passe et le QR de la zone.'],
                    ['Puis-je imprimer plusieurs tickets ?', 'Oui. L’entrepreneur peut générer un lot, l’imprimer et télécharger un PDF, avec les modèles Classique, Moderne, Compact et Premium.'],
                    ['Quels paiements sont proposés ?', 'Ceux qui sont configurés pour la plateforme. Cette page ne liste pas un moyen qui n’est pas branché.'],
                ] as $item)
                    <article class="lp-faq-item">
                        <h3 class="m-0"><button type="button" aria-expanded="false">{{ $item[0] }}</button></h3>
                        <div class="answer"><p>{{ $item[1] }}</p></div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-section lm-motion" id="tarifs">
        <div class="lp-wrap">
            <article class="vh-business">
                <h2>Gérez votre WiFi comme un vrai business.</h2>
                <p>L’espace entrepreneur reste séparé. L’offre est présentée à <strong>5 $</strong> / mois. L’inscription ouvre l’essai déjà prévu. Cette page n’encaisse aucun paiement.</p>
                <div class="lp-cta">
                    <a class="lp-btn" href="{{ route('register') }}">Créer mon compte</a>
                    <a class="lp-btn line" href="{{ route('login') }}">Se connecter</a>
                </div>
            </article>
        </div>
    </section>
</main>

<footer class="lp-footer" id="contact">
    <div class="lp-wrap vh-foot">
        <div>
            <x-brand-logo height="40" alt="LIMETE WIFI MANAGER" />
            <p>Une solution moderne de gestion et de distribution de connexion WiFi.</p>
        </div>
        <nav aria-label="Pied de page">
            <p class="vh-col">Navigation</p>
            <a href="{{ route('home') }}">Accueil</a>
            <a href="#forfaits">Forfaits</a>
            <a href="#comment">Comment ça marche</a>
            <a href="{{ route('client.buy') }}">Acheter</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>
        </nav>
        <div>
            <p class="vh-col">Paiements</p>
            <x-payment-marks :payments="$payments" />
        </div>
        <div>
            <p class="vh-col">Nous suivre</p>
            <x-social-links />
        </div>
    </div>
    <div class="lp-wrap vh-copy">
        <p>© {{ now()->year }} LIMETE WIFI MANAGER. Tous droits réservés.</p>
        <p>
            @if($edosUrl)
                <a href="{{ $edosUrl }}" target="_blank" rel="noopener noreferrer">Designed by EDOS SERVICES</a>
            @else
                Designed by EDOS SERVICES
            @endif
        </p>
        <p class="vh-muted">Photographies : Unsplash, licence Unsplash.</p>
    </div>
</footer>
@endsection
