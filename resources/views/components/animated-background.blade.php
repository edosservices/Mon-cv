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
        str_starts_with($name, 'client.') => 'client',
        default => 'network',
    };
    if (! in_array($picked, ['network', 'dashboard', 'wifi', 'mikrotik', 'tickets', 'shop', 'auth', 'admin', 'client'], true)) {
        $picked = 'network';
    }
    $photo = in_array($picked, ['network', 'dashboard', 'wifi', 'admin', 'mikrotik'], true);
@endphp
<div class="lm-net lm-net-{{ $picked }}" data-lm-net aria-hidden="true">
    @if($picked === 'dashboard')
        <span class="lm-orb lm-orb-a"></span>
        <span class="lm-orb lm-orb-b"></span>
        <span class="lm-orb lm-orb-c"></span>
        <span class="lm-wave"></span>
    @endif
    @if($photo)
        <img class="lm-net-photo" alt="" width="1280" height="853" decoding="async" data-lm-photo data-src="{{ asset('media/landing/network.webp') }}">
    @endif
    <div class="lm-net-veil"></div>
    <span class="lm-flow-dot"></span>
    <span class="lm-flow-dot lm-flow-dot-b"></span>
    <svg class="lm-net-svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">
        <g class="lm-far" fill="currentColor">
            <circle cx="70" cy="90" r="1.6" />
            <circle cx="240" cy="40" r="1.4" />
            <circle cx="520" cy="70" r="1.8" />
            <circle cx="860" cy="50" r="1.5" />
            <circle class="is-mid" cx="1080" cy="120" r="1.4" />
            <circle class="is-mid" cx="160" cy="720" r="1.6" />
            <circle class="is-full" cx="640" cy="760" r="1.3" />
            <circle class="is-full lm-green" cx="1000" cy="700" r="1.5" />
        </g>
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
        <g class="lm-net-dots">
            <circle class="lm-glow" cx="470" cy="140" r="16" />
            <circle class="lm-glow is-mid" cx="780" cy="540" r="18" />
            <circle class="lm-glow is-mid" cx="610" cy="390" r="16" />
            <circle class="lm-glow is-full lm-green" cx="980" cy="580" r="14" />
            <circle cx="90" cy="150" r="3.2" />
            <circle cx="470" cy="140" r="4.2" class="lm-node" />
            <circle cx="930" cy="160" r="3.2" />
            <circle cx="180" cy="620" r="3" />
            <circle cx="780" cy="540" r="4.4" class="lm-node" />
            <circle cx="1120" cy="480" r="3" />
            <circle class="is-mid lm-node" cx="610" cy="390" r="4.4" />
            <circle class="is-mid" cx="1040" cy="400" r="3" />
            <circle class="is-mid" cx="140" cy="360" r="2.6" />
            <circle class="is-full" cx="360" cy="560" r="3" />
            <circle class="is-full" cx="860" cy="250" r="2.8" />
            <circle class="is-full lm-green lm-node" cx="980" cy="580" r="3.6" />
            <circle class="is-full lm-green" cx="260" cy="220" r="2.4" />
        </g>
        <g class="lm-net-flows">
            <circle r="3.1" />
            <circle r="2" />
            <circle class="lm-green" r="2.8" />
            <circle r="1.8" />
        </g>
        <g class="lm-glyph lm-glyph-wifi" transform="translate(980 60)">
            <path d="M8 46c16-20 42-20 58 0" />
            <path d="M20 58c9-12 25-12 34 0" />
            <circle cx="37" cy="70" r="3.2" fill="currentColor" stroke="none" />
        </g>
        <g class="lm-glyph lm-glyph-wifi-b" transform="translate(40 80)">
            <path d="M6 70c28-36 74-36 102 0" />
            <path d="M28 88c16-20 42-20 58 0" />
            <circle cx="57" cy="104" r="3.4" fill="currentColor" stroke="none" />
        </g>
        <g class="lm-glyph lm-glyph-zones" transform="translate(70 430)">
            <circle cx="64" cy="64" r="48" />
            <circle cx="64" cy="16" r="4" fill="currentColor" stroke="none" />
            <circle cx="112" cy="64" r="4" fill="currentColor" stroke="none" />
            <circle cx="64" cy="112" r="4" fill="currentColor" stroke="none" />
            <circle cx="16" cy="64" r="4" fill="currentColor" stroke="none" />
        </g>
        <g class="lm-glyph lm-glyph-cloud" transform="translate(80 640)">
            <path d="M18 36 h46 a16 16 0 0 0 0-32 a20 20 0 0 0-38 8 a14 14 0 0 0-8 24z" />
        </g>
        <g class="lm-glyph lm-glyph-phone" transform="translate(1040 540)">
            <rect x="8" y="4" width="36" height="64" rx="8" />
            <path d="M18 16 h16 M22 54 h8" />
        </g>
        <g class="lm-glyph lm-glyph-qr" transform="translate(60 80)">
            <path d="M4 4 h16 v16 h-16z M8 8 h8 v8 h-8z M28 4 h16 v16 h-16z M32 8 h8 v8 h-8z M4 28 h16 v16 h-16z M8 32 h8 v8 h-8z M28 28 h6 v6 h-6z M40 34 h8 v10 h-8z" />
        </g>
        <g class="lm-glyph lm-glyph-clusters">
            <g transform="translate(90 90)">
                <path d="M8 28 L36 8 L40 34" />
                <circle cx="8" cy="28" r="3.5" fill="currentColor" stroke="none" />
                <circle cx="36" cy="8" r="3.2" fill="currentColor" stroke="none" />
                <circle cx="40" cy="34" r="3.2" fill="currentColor" stroke="none" />
            </g>
            <g transform="translate(980 70)">
                <path d="M10 12 L42 20 L28 44" />
                <circle cx="10" cy="12" r="3.2" fill="currentColor" stroke="none" />
                <circle cx="42" cy="20" r="3.5" fill="currentColor" stroke="none" />
                <circle cx="28" cy="44" r="3" fill="currentColor" stroke="none" />
            </g>
            <g class="is-full" transform="translate(140 600)">
                <path d="M6 20 L34 6 L48 32" />
                <circle cx="6" cy="20" r="3" fill="currentColor" stroke="none" />
                <circle cx="34" cy="6" r="3.2" fill="currentColor" stroke="none" />
                <circle cx="48" cy="32" r="3.4" fill="currentColor" stroke="none" />
            </g>
        </g>
        <g class="lm-glyph lm-glyph-stack" transform="translate(1000 140)">
            <circle cx="16" cy="16" r="5" fill="currentColor" stroke="none" />
            <text x="32" y="20">Internet</text>
            <path d="M16 24 v36" />
            <circle cx="16" cy="68" r="5" fill="currentColor" stroke="none" />
            <text x="32" y="72">MikroTik</text>
            <path d="M16 76 v36" />
            <circle cx="16" cy="120" r="5" fill="currentColor" stroke="none" />
            <text x="32" y="124">WiFi Zone</text>
            <path d="M16 128 v36" />
            <circle cx="16" cy="172" r="5" fill="currentColor" stroke="none" />
            <text x="32" y="176">Clients</text>
        </g>
    </svg>
</div>
