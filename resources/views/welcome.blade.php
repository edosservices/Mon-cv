@extends('layouts.landing')
@section('content')
@php
    $demo = [
        'plan' => '1 HEURE',
        'price' => '500 CDF',
        'duration' => '1 h',
        'username' => 'APERCU',
        'password' => 'EXEMPLE',
        'qr' => '<svg viewBox="0 0 64 64" aria-hidden="true"><rect width="64" height="64" fill="#fff"/><path fill="#122033" d="M4 4h20v20H4zm4 4v12h12V8zm24-4h20v20H32zm4 4v12h12V8zM4 32h20v20H4zm4 4v12h12V36zm28 0h4v4h-4zm8 0h8v4h-8zm-8 8h12v4H36zm16-8h4v12h-4z"/></svg>',
        'created' => 'exemple',
        'expires' => null,
        'phone' => null,
        'whatsapp' => null,
        'address' => null,
        'color' => '#1463f3',
        'logo' => null,
        'business' => 'Aperçu',
        'zone' => 'Zone démo',
    ];
@endphp
<header class="lp-header">
    <div class="lp-wrap lp-bar">
        <a class="lp-logo" href="{{ route('home') }}"><x-brand-logo alt="LIMETE WIFI MANAGER" /><span>LIMETE <span class="lp-logo-rest">WIFI MANAGER</span></span></a>
        <button class="lp-burger" type="button" aria-expanded="false" aria-label="Ouvrir le menu"><span></span><span></span><span></span></button>
        <nav class="lp-nav" aria-label="Sections">
            <a href="#fonctionnalites">Fonctionnalités</a>
            <a href="#comment">Comment ça marche</a>
            <a href="#tarifs">Tarifs</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>
        </nav>
        <div class="lp-actions">
            <a class="lp-btn light" href="{{ route('login') }}">Connexion</a>
            <a class="lp-btn" href="{{ route('register') }}">Créer mon compte</a>
        </div>
    </div>
</header>

<main>
    <section class="lp-hero">
        <img class="lp-poster" src="{{ asset('media/landing/network.webp') }}" alt="" width="1280" height="853">
        <video class="lp-video" poster="{{ asset('media/landing/network.webp') }}" data-src="{{ asset('media/landing/hero.mp4') }}" autoplay muted loop playsinline preload="none"></video>
        <div class="lp-shade"></div>
        <div class="lp-orb lp-orb-a" aria-hidden="true"></div>
        <div class="lp-orb lp-orb-b" aria-hidden="true"></div>
        <svg class="lp-links" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            <line x1="8" y1="22" x2="24" y2="38" />
            <line x1="24" y1="38" x2="18" y2="62" />
            <line x1="70" y1="18" x2="84" y2="34" />
            <line x1="84" y1="34" x2="76" y2="58" />
            <line x1="40" y1="72" x2="58" y2="64" />
            <line x1="58" y1="64" x2="72" y2="78" />
        </svg>
        <div class="lp-wifi" data-parallax aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="lp-dots" aria-hidden="true">
            @for($i = 0; $i < 18; $i++)
                <i style="left: {{ ($i * 53) % 96 }}%; top: {{ ($i * 37) % 90 }}%; animation-delay: {{ $i * .2 }}s"></i>
            @endfor
        </div>
        <div class="lp-float" data-parallax><span class="lp-badge">Aperçu</span><strong>+243 LIMETE</strong><span><i class="dot"></i>Zone illustrée</span></div>
        <div class="lp-wrap lp-hero-grid">
            <div>
                <p class="lp-kicker lp-enter"><x-brand-logo width="56" height="56" alt="LIMETE WIFI MANAGER" /> LIMETE WIFI</p>
                <h1 class="lp-enter">Gérez votre WiFi comme un vrai business.</h1>
                <p class="lp-lead lp-enter">Développez votre business. Une plateforme simple pour gérer vos WiFi Zones, forfaits, tickets, clients, ventes et équipements depuis un seul espace.</p>
                <div class="lp-pills lp-enter">
                    <span class="lp-pill">Entrepreneur : gérer son business</span>
                    <span class="lp-pill">Client : acheter un ticket</span>
                </div>
                <div class="lp-cta">
                    <a class="lp-btn" data-magnetic href="{{ route('register') }}">Commencer maintenant</a>
                    <a class="lp-btn line" href="{{ route('login') }}">Se connecter</a>
                    <a class="lp-btn line" href="{{ route('client.buy') }}">Acheter un ticket</a>
                </div>
            </div>
            <div class="lp-mock lp-stage" data-tilt aria-label="Aperçu illustratif du logiciel">
                <div class="lp-mock-top"><strong>Espace entrepreneur</strong><span class="lp-badge">Aperçu</span></div>
                <div class="lp-kpis">
                    <div class="lp-kpi"><span>Revenus</span><strong>Illustration</strong></div>
                    <div class="lp-kpi"><span>Ventes</span><strong>Illustration</strong></div>
                    <div class="lp-kpi"><span>Tickets</span><strong>Illustration</strong></div>
                </div>
                <div class="lp-split">
                    <div class="lp-card">
                        <strong>Activité</strong>
                        <div class="lp-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
                    </div>
                    <div class="lp-phone">
                        <strong>WiFi Zone</strong>
                        <p class="mb-0">Forfaits et ticket</p>
                    </div>
                </div>
                <p class="lp-note">Visuel de démonstration. Aucun chiffre réel n’est affiché.</p>
            </div>
        </div>
    </section>

    <section class="lp-section" id="reperes">
        <div class="lp-wrap">
            <h2>Une échelle de réseau</h2>
            <p class="lp-lead">Repères de présentation. Ce ne sont pas les chiffres d’un compte connecté.</p>
            <div class="lp-stats mt-4" data-stagger>
                <article class="lp-stat"><strong>+<span data-count="1000">1 000</span></strong><span>Entrepreneurs</span></article>
                <article class="lp-stat"><strong>+<span data-count="10000">10 000</span></strong><span>Tickets</span></article>
                <article class="lp-stat"><strong>+<span data-count="100">100</span></strong><span>WiFi Zones</span></article>
                <article class="lp-stat"><strong>24/7</strong><span>Gestion</span></article>
            </div>
        </div>
    </section>

    <section class="lp-section" id="fonctionnalites">
        <div class="lp-wrap">
            <h2>Tout ce qu'il faut pour gérer votre WiFi</h2>
            <div class="lp-grid mt-4" data-stagger>
                <article class="lp-feature"><h3>🎫 Tickets</h3><p>Générez des tickets uniques.</p></article>
                <article class="lp-feature"><h3>📱 QR Codes</h3><p>Vos clients accèdent facilement à leurs tickets.</p></article>
                <article class="lp-feature"><h3>💰 Ventes</h3><p>Suivez vos ventes et vos revenus.</p></article>
                <article class="lp-feature"><h3>📊 Rapports</h3><p>Comprenez votre activité.</p></article>
                <article class="lp-feature"><h3>📡 MikroTik</h3><p>Centralisez votre gestion réseau.</p></article>
                <article class="lp-feature"><h3>🏪 Boutique</h3><p>Personnalisez votre espace.</p></article>
                <article class="lp-feature"><h3>👥 Clients</h3><p>Retrouvez les acheteurs de vos zones.</p></article>
                <article class="lp-feature"><h3>📍 Localisation</h3><p>Placez le business et la zone.</p></article>
            </div>
        </div>
    </section>

    <section class="lp-section" id="comment">
        <div class="lp-wrap">
            <h2>Comment ça marche</h2>
            <div class="lp-steps mt-4" data-stagger>
                <article class="lp-step"><span class="lp-num">1</span><h3>Créez votre compte</h3></article>
                <article class="lp-step"><span class="lp-num">2</span><h3>Configurez votre business</h3></article>
                <article class="lp-step"><span class="lp-num">3</span><h3>Ajoutez votre WiFi Zone</h3></article>
                <article class="lp-step"><span class="lp-num">4</span><h3>Créez vos forfaits</h3></article>
                <article class="lp-step"><span class="lp-num">5</span><h3>Commencez à vendre</h3></article>
            </div>
        </div>
    </section>

    <section class="lp-section" id="apercu">
        <div class="lp-wrap">
            <h2>Dashboard LIMETE WIFI</h2>
            <p class="lp-lead">Revenu, tickets, clients, zones et activité. Les montants réels restent dans l’espace connecté.</p>
            <div class="lp-panel lp-dash mt-4" data-tilt>
                <div class="lp-dash-top"><strong>Espace entrepreneur</strong><span class="lp-badge">Illustration</span></div>
                <div class="lp-kpis">
                    <div class="lp-kpi"><span>Revenu</span><strong>Exemple</strong></div>
                    <div class="lp-kpi"><span>Tickets vendus</span><strong>Exemple</strong></div>
                    <div class="lp-kpi"><span>Clients</span><strong>Exemple</strong></div>
                    <div class="lp-kpi"><span>Zones</span><strong>Exemple</strong></div>
                </div>
                <svg class="lp-graph lm-motion" viewBox="0 0 240 90" role="img" aria-label="Courbe d’illustration"><path d="M4 70 C 30 68, 40 30, 70 40 S 120 20, 150 34 200 18, 236 22"/></svg>
                <ul class="lp-activity">
                    <li>Vente d’un forfait</li>
                    <li>Ticket préparé pour l’impression</li>
                    <li>WiFi Zone mise à jour</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="lp-section">
        <div class="lp-wrap lp-two">
            <img class="lp-photo" data-smooth src="{{ asset('media/landing/work.webp') }}" alt="Personnes au travail sur un ordinateur portable" width="1200" height="801" loading="lazy">
            <div>
                <h2>Gérez votre WiFi</h2>
                <p class="lp-lead">Préparez les forfaits, vendez un ticket et suivez l’activité depuis le même écran.</p>
                <a class="lp-btn" data-magnetic href="{{ route('register') }}">Créer mon espace</a>
            </div>
        </div>
    </section>

    <section class="lp-section">
        <div class="lp-wrap lp-two">
            <div>
                <h2>Votre connexion. Votre business. Votre zone.</h2>
                <p class="lp-lead">LIMETE WIFI s’adresse aux entrepreneurs qui vendent l’accès internet dans leur zone.</p>
            </div>
            <div class="lp-photo-stack">
                <img class="lp-photo" data-smooth src="{{ asset('media/landing/portrait.webp') }}" alt="Professionnelle souriante, dossier en main" width="800" height="1000" loading="lazy">
                <img class="lp-photo" data-smooth src="{{ asset('media/landing/team.webp') }}" alt="Équipe autour d’ordinateurs portables" width="1200" height="800" loading="lazy">
            </div>
        </div>
    </section>

    <section class="lp-section">
        <div class="lp-wrap lp-two">
            <div>
                <h2>Votre business. Votre espace.</h2>
                <p class="lp-lead">L’entrepreneur gère son identité, ses zones et ses ventes sans mélanger les données d’un autre compte.</p>
                <ul class="lp-list">
                    <li>Logo personnalisé</li><li>Couleurs personnalisées</li><li>WiFi Zones</li><li>Forfaits libres</li>
                    <li>Tickets</li><li>Ventes</li><li>Clients</li><li>Rapports</li>
                    <li>Impression</li><li>PDF</li><li>QR codes</li><li>Gestion MikroTik</li>
                </ul>
                <a class="lp-btn" href="{{ route('register') }}">Créer mon business</a>
            </div>
            <div class="lp-panel" id="personnalisation">
                <h3>Votre identité</h3>
                <div class="lp-brand-mock">
                    <div class="lp-mark">L</div>
                    <div>
                        <strong>Nom du business</strong>
                        <p>Téléphone et WhatsApp de l’entrepreneur.</p>
                        <div class="lp-swatches" aria-hidden="true"><i style="background:#1463f3"></i><i style="background:#0f8a4b"></i><i style="background:#071428"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section" id="clients">
        <div class="lp-wrap lp-two">
            <div class="lp-device lm-motion">
                <div class="lp-device-screen">
                    <x-brand-logo width="40" height="40" alt="" />
                    <p class="mb-1 mt-2">LIMETE WIFI</p>
                    <h2 class="h5">24H</h2>
                    <p class="lm-price">500 FC</p>
                    <a class="lp-btn" href="{{ route('client.buy') }}">Acheter maintenant</a>
                </div>
            </div>
            <div>
            <h2>Acheter un ticket en quelques secondes</h2>
            <div class="lp-flow" aria-label="Parcours client">
                <span>Téléphone</span><span>Choisir forfait</span><span>Acheter</span><span>Recevoir ticket</span><span>Scanner QR</span><span>Se connecter au WiFi</span>
            </div>
            <a class="lp-btn" href="{{ route('client.buy') }}">Acheter un ticket</a>
            </div>
        </div>
    </section>

    <section class="lp-section" id="tickets">
        <div class="lp-wrap">
            <h2>Quatre modèles de ticket</h2>
            <p class="lp-lead">Aperçus des modèles déjà utilisés pour l’impression et le PDF. Ceci n’est pas un ticket réel.</p>
            <div class="lp-tickets mt-4" data-stagger>
                @foreach(['classique' => 'Classique', 'moderne' => 'Moderne', 'compact' => 'Compact', 'premium' => 'Premium'] as $key => $label)
                    <div>
                        <div class="lp-ticket-rot" style="--r: {{ [-1.6, 1.4, -1.1, 1.8][$loop->index] }}deg">
                            @include('vouchers.templates.'.$key, ['ticket' => $demo])
                            <p class="lp-caption">{{ $label }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-section" id="rapports">
        <div class="lp-wrap">
            <h2>Un aperçu de l’activité</h2>
            <div class="lp-report mt-4">
                <div class="lp-panel">
                    <h3>Graphique d’illustration</h3>
                    <div class="lp-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
                    <p>Chiffre d’affaires, ventes et tickets restent dans l’espace connecté. Ici, aucune donnée réelle.</p>
                </div>
                <div class="lp-grid">
                    <article class="lp-feature"><h3>Clients</h3><p>Suivi des acheteurs.</p></article>
                    <article class="lp-feature"><h3>Connectés</h3><p>Sessions hotspot.</p></article>
                    <article class="lp-feature"><h3>Zones</h3><p>WiFi Zones du business.</p></article>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-section" id="mikrotik">
        <div class="lp-wrap">
            <h2>MikroTik, étape par étape</h2>
            <p class="lp-lead">La connexion se fait dans l’espace entrepreneur. Cette page ne contacte aucun routeur.</p>
            <div class="lp-flow-net mt-4" data-stagger aria-label="Parcours réseau">
                <article class="lp-node">Internet</article>
                <article class="lp-node">MikroTik</article>
                <article class="lp-node">WiFi Zone</article>
                <article class="lp-node">Clients</article>
            </div>
        </div>
    </section>

    <section class="lp-section" id="tarifs">
        <div class="lp-wrap">
            <h2>Simple et transparent</h2>
            <article class="lp-price mt-4" data-tilt>
                <p class="lp-kicker">Abonnement entrepreneur</p>
                <p><strong>5 $</strong> / mois</p>
                <ul>
                    <li>Business, logo, couleurs, téléphone et WhatsApp</li>
                    <li>WiFi Zone, forfaits, tickets, QR, impression et PDF</li>
                    <li>Ventes, clients et rapports</li>
                    <li>Assistant MikroTik</li>
                </ul>
                <p>L’inscription ouvre l’essai déjà prévu dans l’application. Cette page n’encaisse aucun paiement.</p>
                <a class="lp-btn" data-magnetic href="{{ route('register') }}">Commencer maintenant</a>
            </article>
        </div>
    </section>

    <section class="lp-section" id="faq">
        <div class="lp-wrap">
            <h2>Questions fréquentes</h2>
            <div class="mt-4 d-grid gap-2">
                @foreach([
                    ['Dois-je avoir un compte pour acheter un ticket ?', 'Non. Le parcours public permet de choisir une WiFi Zone et un forfait sans compte. Un espace client existe aussi pour retrouver ses tickets.'],
                    ['Puis-je imprimer plusieurs tickets ?', 'Oui. L’entrepreneur peut générer un lot, l’imprimer et télécharger un PDF, avec les modèles Classique, Moderne, Compact et Premium.'],
                    ['Puis-je utiliser mon propre logo ?', 'Oui. Le business accepte un logo PNG, JPG, JPEG ou WEBP, avec un nom, des couleurs, un téléphone et un WhatsApp.'],
                    ['Puis-je créer mes propres forfaits ?', 'Oui. Chaque forfait a un nom, une durée, un prix, une limite d’appareils et, si besoin, un débit.'],
                    ['Puis-je connecter un MikroTik ?', 'Oui, depuis l’assistant de l’espace entrepreneur. Cette page d’accueil ne connecte aucun routeur.'],
                    ['Combien coûte l’abonnement ?', 'L’offre entrepreneur est présentée à 5 $ par mois. L’inscription ouvre l’essai existant. Le paiement de l’abonnement n’est pas encaissé ici.'],
                    ['Puis-je gérer plusieurs WiFi Zones ?', 'Chaque entrepreneur gère ses propres zones. L’essai STARTER actuel comprend une zone et un MikroTik. Les formules Business et Pro prévues dans l’application autorisent davantage de zones.'],
                ] as $item)
                    <article class="lp-faq-item">
                        <h3 class="m-0"><button type="button" aria-expanded="false">{{ $item[0] }}</button></h3>
                        <div class="answer"><p>{{ $item[1] }}</p></div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-section">
        <div class="lp-wrap">
            <div class="lp-final">
                <x-brand-logo width="48" height="48" alt="LIMETE WIFI MANAGER" />
                <h2>Prêt à transformer votre WiFi en véritable business ?</h2>
                <p>Créez votre espace, préparez vos forfaits et vendez vos tickets depuis la même plateforme.</p>
                <div class="lp-cta mt-3">
                    <a class="lp-btn" href="{{ route('register') }}">Créer mon compte</a>
                    <a class="lp-btn ghost" href="{{ route('login') }}">Se connecter</a>
                    <a class="lp-btn ghost" href="{{ route('client.buy') }}">Acheter un ticket</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="lp-footer" id="contact">
    <div class="lp-wrap lp-foot">
        <div>
            <x-brand-logo width="40" height="40" alt="LIMETE WIFI MANAGER" />
            <strong>LIMETE WIFI MANAGER</strong>
            <p>Plateforme pour gérer un business WiFi et acheter un ticket.</p>
        </div>
        <nav aria-label="Pied de page">
            <a href="{{ route('home') }}">Accueil</a><br>
            <a href="#fonctionnalites">Fonctionnalités</a><br>
            <a href="#tarifs">Tarifs</a><br>
            <a href="{{ route('login') }}">Connexion</a><br>
            <a href="{{ route('register') }}">Inscription</a>
        </nav>
        <div>
            <p class="mb-1"><strong>Support</strong></p>
            <p class="lp-soon">WhatsApp : coordonnées non publiées.</p>
            <p class="lp-soon">Contact : depuis l’espace après inscription. Cette page ne reçoit pas de message.</p>
            <p class="lp-soon">Confidentialité : page non publiée.</p>
            <p class="lp-soon">Conditions : page non publiée.</p>
        </div>
    </div>
</footer>
@endsection
