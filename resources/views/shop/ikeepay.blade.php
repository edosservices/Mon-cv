@extends('layouts.shop')
@section('title', 'Paiement iKeePay — '.$zone->name)
@section('content')
<section class="panel ikeepay-panel" aria-labelledby="ikeepay-title">
    <div class="ikeepay-bar">
        <h1 id="ikeepay-title">Paiement iKeePay</h1>
        <a class="btn ikeepay-close" href="{{ $returnUrl }}">Fermer</a>
    </div>
    <p class="help" id="ikeepay-wait">Ouverture du paiement…</p>
    <p class="help">{{ $amount }} {{ $currency }} · {{ $orderId }}</p>
    @if($publicKey === '' || $email === '')
        <p class="help">Le checkout iKeePay ne peut pas s’ouvrir. Le paiement reste en attente.</p>
    @else
        <iframe
            id="ikeepay-frame"
            class="ikeepay-frame"
            title="Checkout iKeePay"
            data-checkout-url="{{ $checkoutUrl }}"
            data-public-key="{{ $publicKey }}"
            data-amount="{{ $amount }}"
            data-currency="{{ $currency }}"
            data-order-id="{{ $orderId }}"
            data-email="{{ $email }}"
            data-return-url="{{ $returnUrl }}"
        ></iframe>
    @endif
</section>
@if($publicKey !== '' && $email !== '')
    <script>
        (function () {
            var frame = document.getElementById('ikeepay-frame');
            var wait = document.getElementById('ikeepay-wait');
            var returnUrl = frame.getAttribute('data-return-url');
            var params = new URLSearchParams({
                pk: frame.getAttribute('data-public-key'),
                amount: frame.getAttribute('data-amount'),
                currency: frame.getAttribute('data-currency'),
                order_id: frame.getAttribute('data-order-id'),
                email: frame.getAttribute('data-email')
            });
            frame.src = frame.getAttribute('data-checkout-url') + '?' + params.toString();

            function messageName(data) {
                if (typeof data === 'string') {
                    return data;
                }
                if (data && typeof data === 'object') {
                    return data.event || data.type || data.name || '';
                }
                return '';
            }

            window.addEventListener('message', function (event) {
                if (event.origin !== 'https://www.ikeepay.com') {
                    return;
                }
                var name = messageName(event.data);
                if (name === 'ikeepay-ready') {
                    if (wait) {
                        wait.hidden = true;
                    }
                    frame.classList.add('is-ready');
                    return;
                }
                if (name === 'ikeepay-close' || name === 'ikeepay-success') {
                    window.location.assign(returnUrl);
                }
            });
        }());
    </script>
@endif
@endsection
