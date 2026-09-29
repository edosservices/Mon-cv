@props(['variant' => null])
@php
    $name = request()->route()?->getName() ?? '';
    $picked = $variant ?? match (true) {
        $name === 'login' || $name === 'register' || $name === 'client.login' || $name === 'client.register' || $name === 'client.forgot' || $name === 'client.reset' => 'auth',
        $name === 'dashboard' => 'dashboard',
        str_starts_with($name, 'wifi-zones.') || str_starts_with($name, 'plans.') => 'wifi',
        str_starts_with($name, 'mikrotiks.') || $name === 'admin.mikrotiks' => 'mikrotik',
        str_starts_with($name, 'vouchers.') || $name === 'client.tickets' || $name === 'shop.tickets' || str_starts_with($name, 'tickets.') => 'tickets',
        $name === 'client.buy' || str_starts_with($name, 'sales.quick') => 'shop',
        str_starts_with($name, 'shop.') => 'shop',
        str_starts_with($name, 'admin.') => 'admin',
        str_starts_with($name, 'reports.') || str_starts_with($name, 'statistics.') || str_starts_with($name, 'sales.') => 'dashboard',
        str_starts_with($name, 'client.') => 'wifi',
        default => 'network',
    };
    if (! in_array($picked, ['network', 'dashboard', 'wifi', 'mikrotik', 'tickets', 'shop', 'auth', 'admin'], true)) {
        $picked = 'network';
    }
    $photo = in_array($picked, ['network', 'dashboard', 'wifi', 'admin', 'mikrotik'], true);
@endphp
<div class="lm-net lm-net-{{ $picked }}" data-lm-net aria-hidden="true">
    @if($photo)
        <img class="lm-net-photo" alt="" width="1280" height="853" decoding="async" data-lm-photo data-src="{{ asset('media/landing/network.webp') }}">
    @endif
    <div class="lm-net-veil"></div>
    <svg class="lm-net-svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">
        <g class="lm-net-links" fill="none" stroke="currentColor" stroke-width="1.25">
            <path d="M90 150 C 220 90, 340 210, 470 140" />
            <path d="M470 140 C 620 70, 760 220, 930 160" />
            <path d="M180 620 C 340 500, 560 690, 780 540" />
            <path d="M780 540 C 900 450, 1020 600, 1120 480" />
            <path class="is-mid" d="M140 360 C 300 300, 420 460, 610 390" />
            <path class="is-mid" d="M610 390 C 760 330, 880 470, 1040 400" />
            <path class="is-full" d="M260 220 C 300 340, 240 480, 360 560" />
            <path class="is-full" d="M860 250 C 900 360, 820 500, 980 580" />
        </g>
        <g class="lm-net-dots" fill="currentColor">
            <circle cx="90" cy="150" r="3.2" />
            <circle cx="470" cy="140" r="4" class="lm-node" />
            <circle cx="930" cy="160" r="3.2" />
            <circle cx="180" cy="620" r="3" />
            <circle cx="780" cy="540" r="4.2" class="lm-node" />
            <circle cx="1120" cy="480" r="3" />
            <circle class="is-mid" cx="610" cy="390" r="4.4" />
            <circle class="is-mid" cx="1040" cy="400" r="3" />
            <circle class="is-mid" cx="140" cy="360" r="2.6" />
            <circle class="is-full" cx="360" cy="560" r="3" />
            <circle class="is-full" cx="860" cy="250" r="2.8" />
            <circle class="is-full lm-green" cx="980" cy="580" r="3.4" />
            <circle class="is-full lm-green" cx="260" cy="220" r="2.4" />
        </g>
        <g class="lm-net-flows" fill="currentColor">
            <circle r="2.4" />
            <circle r="2.2" />
            <circle r="2.2" />
        </g>
        <g class="lm-glyph lm-glyph-wifi" transform="translate(980 70)">
            <path d="M8 46c16-20 42-20 58 0" />
            <path d="M20 58c9-12 25-12 34 0" />
            <circle cx="37" cy="70" r="3.2" fill="currentColor" stroke="none" />
        </g>
        <g class="lm-glyph lm-glyph-antenna" transform="translate(70 80)">
            <path d="M28 78 V28" />
            <path d="M28 28 l-16 18 M28 28 l16 18" />
            <circle cx="28" cy="78" r="4" fill="currentColor" stroke="none" />
        </g>
        <g class="lm-glyph lm-glyph-cloud" transform="translate(80 640)">
            <path d="M18 36 h46 a16 16 0 0 0 0-32 a20 20 0 0 0-38 8 a14 14 0 0 0-8 24z" />
        </g>
        <g class="lm-glyph lm-glyph-phone" transform="translate(1040 560)">
            <rect x="8" y="4" width="36" height="64" rx="8" />
            <path d="M18 16 h16 M22 54 h8" />
        </g>
        <g class="lm-glyph lm-glyph-qr" transform="translate(60 80)">
            <path d="M4 4 h16 v16 h-16z M8 8 h8 v8 h-8z M28 4 h16 v16 h-16z M32 8 h8 v8 h-8z M4 28 h16 v16 h-16z M8 32 h8 v8 h-8z M28 28 h6 v6 h-6z M40 34 h8 v10 h-8z" />
        </g>
        <g class="lm-glyph lm-glyph-globe" transform="translate(140 180)">
            <circle cx="70" cy="70" r="54" />
            <ellipse cx="70" cy="70" rx="24" ry="54" />
            <path d="M20 70 h100 M28 42 h84 M28 98 h84" />
        </g>
        <g class="lm-glyph lm-glyph-stack" transform="translate(1020 150)">
            <circle cx="70" cy="20" r="5" fill="currentColor" stroke="none" />
            <text x="86" y="24">Internet</text>
            <path d="M70 28 v28" />
            <circle cx="70" cy="68" r="5" fill="currentColor" stroke="none" />
            <text x="86" y="72">Routeur</text>
            <path d="M70 76 v28" />
            <circle cx="70" cy="116" r="5" fill="currentColor" stroke="none" />
            <text x="86" y="120">Switch</text>
            <path d="M70 124 v28" />
            <circle cx="70" cy="164" r="5" fill="currentColor" stroke="none" />
            <text x="86" y="168">Borne</text>
            <path d="M70 172 v28" />
            <circle cx="70" cy="212" r="5" fill="currentColor" stroke="none" />
            <text x="86" y="216">Clients</text>
        </g>
    </svg>
</div>
