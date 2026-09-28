@php
    $brand = $zone->brandColor();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $brand }}">
    <title>@yield('title', $zone->name)</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="shop" style="--shop: {{ $brand }}">
    <div class="shop-wrap">
        <header class="shop-top">
            <a class="shop-brand" href="{{ route('shop.show', $zone->slug) }}">
                @if($zone->logoUrl())
                    <img class="shop-logo" src="{{ $zone->logoUrl() }}" alt="">
                @else
                    <span class="shop-mark" aria-hidden="true">{{ mb_substr($zone->name, 0, 1) }}</span>
                @endif
                <span>
                    <strong>{{ $zone->name }}</strong>
                    @if($zone->location)<small>{{ $zone->location }}</small>@endif
                </span>
            </a>
            <a class="shop-link" href="{{ route('shop.tickets', $zone->slug) }}">Mes tickets</a>
        </header>

        @isset($step)
            <ol class="progress" aria-label="Étapes">
                @foreach(['Forfait', 'Paiement', 'Confirmation', 'Ticket'] as $index => $label)
                    <li class="{{ $step === $index ? 'is-current' : ($step > $index ? 'is-done' : '') }}">{{ $label }}</li>
                @endforeach
            </ol>
        @endisset

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

        <footer class="shop-foot">
            @if($zone->whatsappDigits())
                <a href="https://wa.me/{{ $zone->whatsappDigits() }}">WhatsApp {{ $zone->whatsapp }}</a>
            @endif
            <p>{{ $zone->name }}</p>
        </footer>
    </div>
    <script>
        document.querySelectorAll('form[data-wait]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('[type="submit"]');
                if (!button) return;
                button.disabled = true;
                button.textContent = 'Patientez…';
            });
        });
    </script>
</body>
</html>
