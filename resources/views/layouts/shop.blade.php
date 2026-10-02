@php
    $brand = $zone->brandColor();
    $brand2 = $zone->secondaryColor();
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
    @vite(['resources/css/shop.css', 'resources/js/shop.js'])
</head>
<body class="shop" style="--shop: {{ $brand }}; --shop-2: {{ $brand2 }}">
    <div class="net-bg" aria-hidden="true">
        <span class="halo halo-a"></span>
        <span class="halo halo-b"></span>
        <svg class="net-web" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">
            <path d="M40 180 L220 120 L420 240 L640 90 L860 220 L1120 140" />
            <path d="M120 520 L300 360 L520 480 L760 340 L980 500 L1160 390" />
            <path d="M220 120 L300 360 M420 240 L520 480 M640 90 L760 340 M860 220 L980 500" />
            <g fill="#e0f2fe">
                <circle cx="40" cy="180" r="4" />
                <circle cx="220" cy="120" r="4" />
                <circle cx="420" cy="240" r="4" />
                <circle cx="640" cy="90" r="4" />
                <circle cx="860" cy="220" r="4" />
                <circle cx="1120" cy="140" r="4" />
                <circle cx="120" cy="520" r="3.5" />
                <circle cx="300" cy="360" r="3.5" />
                <circle cx="520" cy="480" r="3.5" />
                <circle cx="760" cy="340" r="3.5" />
                <circle cx="980" cy="500" r="3.5" />
            </g>
        </svg>
        <svg class="wifi-float f1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M5 12.5a10 10 0 0 1 14 0" />
            <path d="M8.2 15.2a5.5 5.5 0 0 1 7.6 0" />
            <path d="M12 18.5h.01" />
        </svg>
        <svg class="wifi-float f2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M5 12.5a10 10 0 0 1 14 0" />
            <path d="M8.2 15.2a5.5 5.5 0 0 1 7.6 0" />
            <path d="M12 18.5h.01" />
        </svg>
        <svg class="wifi-float f3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M5 12.5a10 10 0 0 1 14 0" />
            <path d="M8.2 15.2a5.5 5.5 0 0 1 7.6 0" />
            <path d="M12 18.5h.01" />
        </svg>
    </div>
    <a class="skip" href="#contenu">Aller au contenu</a>
    <div class="container shop-wrap">
        <header class="d-flex align-items-center justify-content-between gap-3">
            <a class="shop-brand d-flex align-items-center gap-3" href="{{ route('shop.show', $zone->slug) }}">
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
            <a class="shop-link flex-shrink-0" href="{{ route('shop.tickets', $zone->slug) }}">Mes tickets</a>
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
</body>
</html>
