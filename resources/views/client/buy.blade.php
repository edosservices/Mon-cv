@extends('layouts.client')
@section('body-class', 'client-buy')
@section('heading', 'Acheter')
@section('content')
<h1 class="h4">Acheter sans compte</h1>
<p class="text-secondary">Choisissez une WiFi Zone. Le numéro et le nom restent facultatifs.</p>

<section class="buy-guide" data-guide aria-roledescription="carrousel" aria-label="Comment acheter, même la première fois">
    <h2 class="h5 mb-1">Trois étapes</h2>
    <p class="text-secondary mb-3">Regarde d’abord. Ensuite, choisis la zone où tu te trouves.</p>
    <div class="buy-guide-stage">
        <figure class="buy-guide-slide is-on" data-guide-slide>
            <img src="{{ asset('images/guide/forfait.jpg') }}" alt="Page des forfaits : choisir une durée puis appuyer sur Acheter maintenant." width="840" height="840">
            <figcaption>
                <strong>Choisis ton forfait</strong>
                <span>Ouvre la zone où tu es, puis prends la durée qui te convient.</span>
            </figcaption>
        </figure>
        <figure class="buy-guide-slide" data-guide-slide>
            <img src="{{ asset('images/guide/paiement.jpg') }}" alt="Fenêtre de paiement : choisir un moyen puis appuyer sur Payer maintenant." width="840" height="840">
            <figcaption>
                <strong>Paie en un geste</strong>
                <span>Choisis ton moyen de paiement et confirme. Rien d’autre.</span>
            </figcaption>
        </figure>
        <figure class="buy-guide-slide" data-guide-slide>
            <img src="{{ asset('images/guide/succes.jpg') }}" alt="Confirmation Paiement réussi, avec le forfait payé et le bouton Voir mon ticket." width="840" height="840">
            <figcaption>
                <strong>Ton ticket est prêt</strong>
                <span>Après confirmation, le ticket s’affiche. Tu peux l’utiliser tout de suite.</span>
            </figcaption>
        </figure>
    </div>
    <div class="buy-guide-dots">
        <button type="button" data-guide-dot aria-current="true">1. Forfait</button>
        <button type="button" data-guide-dot>2. Paiement</button>
        <button type="button" data-guide-dot>3. Ticket</button>
    </div>
</section>

@forelse($zones as $zone)
    <article class="client-card p-3 mb-3">
        <h2 class="h6 mb-1">{{ $zone->name }}</h2>
        <p class="text-secondary mb-2">{{ $zone->location ?: 'Zone WiFi' }}</p>
        <a class="btn client-btn" href="{{ route('shop.show', $zone->slug) }}">Voir les forfaits</a>
    </article>
@empty
    <div class="client-card p-3">Aucune zone disponible pour le moment.</div>
@endforelse
@endsection
