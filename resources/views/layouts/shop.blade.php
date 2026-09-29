@php
    $brand = $zone->brandColor();
    $brand2 = $zone->secondaryColor();
    $brandBtn = $zone->buttonColor();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $brand }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $zone->displayLabel() }}">
    <title>@yield('title', $zone->displayLabel())</title>
    <link rel="manifest" href="{{ route('shop.manifest', $zone->slug) }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="shop" style="--shop: {{ $brand }}; --shop-2: {{ $brand2 }}; --shop-btn: {{ $brandBtn }}">
    <a class="skip" href="#contenu">Aller au contenu</a>
    <div class="shop-wrap">
        <header class="shop-top">
            <a class="shop-brand" href="{{ route('shop.show', $zone->slug) }}">
                @if($zone->logoUrl())
                    <img class="shop-logo" src="{{ $zone->logoUrl() }}" alt="">
                @else
                    <span class="shop-mark" aria-hidden="true">{{ mb_substr($zone->displayLabel(), 0, 1) }}</span>
                @endif
                <span>
                    <strong>{{ $zone->displayLabel() }}</strong>
                    @if($zone->location)<small>{{ $zone->location }}</small>@endif
                </span>
            </a>
            <a class="shop-link" href="{{ route('shop.tickets', $zone->slug) }}">Mes tickets</a>
        </header>

        @isset($step)
            <ol class="progress" aria-label="Étapes d’achat">
                @foreach(['Forfait', 'Informations', 'Paiement', 'Confirmation', 'Ticket'] as $index => $label)
                    <li class="{{ $step === $index ? 'is-current' : ($step > $index ? 'is-done' : '') }}" @if($step === $index) aria-current="step" @endif>{{ $label }}</li>
                @endforeach
            </ol>
        @endisset

        <main id="contenu">

        @if(session('status'))
            <p class="note note-ok" role="status">{{ session('status') }}</p>
        @endif
        @if(session('warning'))
            <p class="note note-warn" role="status">{{ session('warning') }}</p>
        @endif
        @if($errors->any())
            <div class="note note-bad" role="alert">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @yield('content')
        </main>

        <footer class="shop-foot">
            @if($zone->whatsappDigits())
                <a href="https://wa.me/{{ $zone->whatsappDigits() }}">WhatsApp {{ $zone->whatsapp }}</a>
            @endif
            @if($zone->phone)
                <p>{{ $zone->phone }}</p>
            @endif
            @if($zone->email)
                <p>{{ $zone->email }}</p>
            @endif
            <p>{{ $zone->name }}</p>
        </footer>
    </div>
    <script>
        document.querySelectorAll('form[data-wait]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.textContent = 'Patientez…';
            });
        });
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-copy') || '';
                var done = function () { button.textContent = 'Code copié'; };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(value).then(done).catch(function () { window.prompt('Copiez le code', value); });
                    return;
                }
                window.prompt('Copiez le code', value);
            });
        });
        var poll = document.querySelector('[data-poll]');
        if (poll) {
            var seconds = parseInt(poll.getAttribute('data-poll'), 10) || 15;
            window.setTimeout(function () { window.location.reload(); }, seconds * 1000);
        }
    </script>
</body>
</html>
