@extends('layouts.shop')
@section('title', 'Paiement — '.$zone->name)
@section('content')
<section class="panel ikeepay-panel" aria-labelledby="ikeepay-title">
    <div class="ikeepay-bar">
        <h1 id="ikeepay-title">Paiement en cours…</h1>
        <a class="btn ikeepay-close" href="{{ $returnUrl }}">Fermer</a>
    </div>
    <p class="help" id="ikeepay-wait">Confirmation du paiement…</p>
    <p class="help">{{ $amount }} {{ $currency }} · {{ $orderId }}</p>
    @if($publicKey === '' || $checkoutOrigin === '')
        <p class="help">Le paiement n’a pas été confirmé. Aucun montant n’a été validé sur votre commande.</p>
    @else
        <iframe
            id="ikeepay-frame"
            class="ikeepay-frame"
            title="Paiement sécurisé"
            allowtransparency="true"
            data-checkout-url="{{ $checkoutUrl }}"
            data-origin="{{ $checkoutOrigin }}"
            data-public-key="{{ $publicKey }}"
            data-amount="{{ $amount }}"
            data-currency="{{ $currency }}"
            data-order-id="{{ $orderId }}"
            data-redirect-url="{{ $returnUrl }}"
        ></iframe>
        <form id="ikeepay-refresh" method="POST" action="{{ $refreshUrl }}">
            @csrf
        </form>
    @endif
</section>
@if($publicKey !== '' && $checkoutOrigin !== '')
    <script>
        (function () {
            var frame = document.getElementById('ikeepay-frame');
            var wait = document.getElementById('ikeepay-wait');
            var fields = {
                pk: frame.getAttribute('data-public-key'),
                amount: frame.getAttribute('data-amount'),
                currency: frame.getAttribute('data-currency'),
                order_id: frame.getAttribute('data-order-id')
            };
            var redirectUrl = frame.getAttribute('data-redirect-url');
            if (redirectUrl) {
                fields.redirect_url = redirectUrl;
            }
            var params = new URLSearchParams(fields);
            frame.src = frame.getAttribute('data-checkout-url') + '?' + params.toString();

            window.addEventListener('message', function (event) {
                if (event.origin !== frame.getAttribute('data-origin') || typeof event.data !== 'string') {
                    return;
                }
                if (event.data === 'ikeepay-ready') {
                    if (wait) {
                        wait.hidden = true;
                    }
                    frame.classList.add('is-ready');
                    return;
                }
                if (event.data === 'ikeepay-success') {
                    if (wait) {
                        wait.hidden = false;
                        wait.textContent = 'Paiement en cours de confirmation';
                    }
                    document.getElementById('ikeepay-refresh').submit();
                    return;
                }
                if (event.data === 'ikeepay-close') {
                    window.location.assign(@json($returnUrl));
                }
            });
        }());
    </script>
@endif
@endsection
