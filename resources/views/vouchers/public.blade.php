@extends('layouts.shop')
@section('title', 'Ticket '.$voucher->username)
@section('content')
<p class="ready">Votre ticket est prêt</p>
@include('vouchers.ticket')

<div class="actions">
    <a class="btn btn-primary" href="#connexion">Se connecter au WiFi</a>
    <a class="btn btn-ghost" href="{{ route('tickets.pdf', $voucher->public_token) }}">Télécharger le ticket</a>
    @if($zone->whatsappDigits())
        <a class="btn btn-wa" href="https://wa.me/{{ $zone->whatsappDigits() }}?text={{ urlencode('Bonjour, j’ai besoin d’aide pour le ticket '.$voucher->username.' ('.$voucher->plan->name.').') }}">Envoyer sur WhatsApp</a>
    @endif
</div>

<section class="panel" id="connexion">
    <h2>Se connecter au WiFi</h2>
    <ol class="steps">
        <li>Rejoignez le réseau {{ $zone->name }}.</li>
        <li>Ouvrez le portail qui s’affiche.</li>
        <li>Entrez le code <strong>{{ $voucher->username }}</strong> et le mot de passe du ticket.</li>
    </ol>
    <p class="help">Le temps restant suit la date d’expiration. Il continue même si vous vous déconnectez.</p>
</section>
@endsection
