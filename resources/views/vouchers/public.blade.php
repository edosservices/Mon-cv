@extends('layouts.shop')
@section('title', 'Ticket '.$voucher->username)
@section('content')
@if($voucher->status === 'expired')
    <section class="panel">
        <p class="status-pill is-bad">Expiré</p>
        <h1>Ce ticket n’est plus valide.</h1>
        <p class="help">La date d’expiration est dépassée. Le temps ne reprend pas.</p>
        <a class="btn btn-primary" href="{{ route('shop.show', $zone->slug) }}">Choisir un nouveau forfait</a>
    </section>
@else
    <p class="ready">Votre ticket est prêt</p>
@endif

@include('vouchers.ticket')
@include('vouchers.actions')

@if($voucher->status !== 'expired')
    <section class="panel" id="connexion">
        <h2>Se connecter au WiFi</h2>
        <ol class="steps">
            <li>Rejoignez le réseau {{ $zone->name }}.</li>
            <li>Ouvrez le portail qui s’affiche.</li>
            <li>Entrez le code <strong>{{ $voucher->username }}</strong> et le mot de passe du ticket.</li>
        </ol>
        <p class="help">Le temps restant suit la date d’expiration. Il continue même si vous vous déconnectez.</p>
    </section>
@endif
@endsection
