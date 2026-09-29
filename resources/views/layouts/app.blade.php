<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LIMETE WIFI MANAGER')</title>
    <link rel="icon" href="{{ asset('brand/logo-limete-wifi-manager.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @auth
        @if(auth()->user()->tenant)
            <style>
                .bg-electric { background-color: {{ auth()->user()->tenant->buttonColor() }} !important; }
                .text-electric { color: {{ auth()->user()->tenant->brandColor() }} !important; }
                .border-electric { border-color: {{ auth()->user()->tenant->brandColor() }} !important; }
            </style>
        @endif
    @endauth
</head>
<body class="lm-app min-h-screen text-ink">
    <x-animated-background />
    <div class="lm-backdrop" data-lm-backdrop></div>
    <div class="lm-frame">
        <aside class="lm-side no-print" id="lm-side">
            <div class="lm-side-head">
                <a class="lm-brand" href="{{ route('dashboard') }}">
                    <img class="lm-brand-wordmark" src="{{ asset('brand/logo-limete-wifi-manager.png') }}" alt="LIMETE WIFI MANAGER">
                </a>
                <button class="lm-collapse" type="button" data-lm-collapse aria-pressed="false">Réduire</button>
            </div>
            <nav class="lm-side-nav" aria-label="Navigation">
                @include('layouts.nav')
            </nav>
            @php
                $account = auth()->user();
                $business = $account->tenant;
                $place = trim(implode(' · ', array_filter([$business?->city, $business?->country])));
            @endphp
            <div class="lm-account">
                <a class="lm-account-card" href="{{ $account->hasPermission('settings.manage') ? route('business.edit') : route('dashboard') }}">
                    @if($business?->logoUrl())
                        <img class="lm-account-logo" src="{{ $business->logoUrl() }}" alt="">
                    @else
                        <span class="lm-account-mark">{{ mb_substr($business?->name ?: $account->name, 0, 1) }}</span>
                    @endif
                    <span class="lm-account-copy">
                        <strong>{{ $account->name }}</strong>
                        <small>{{ $business?->name ?: 'Compte' }}</small>
                        @if($place !== '')
                            <small class="lm-account-place">{{ $place }}</small>
                        @endif
                    </span>
                </a>
                @if($account->hasPermission('settings.manage'))
                    <a class="lm-account-import" href="{{ route('business.edit') }}#logo">Importer le logo</a>
                @endif
            </div>
        </aside>
        <div class="lm-main min-w-0">
            <header class="lm-top no-print">
                <div class="lm-top-brand">
                    @if(auth()->user()->tenant?->logoUrl())
                        <img class="lm-nav-logo" src="{{ auth()->user()->tenant->logoUrl() }}" alt="{{ auth()->user()->tenant->name }}">
                    @else
                        <img class="lm-nav-logo" src="{{ asset('brand/logo-limete-wifi-manager.png') }}" alt="LIMETE WIFI MANAGER">
                    @endif
                    <div class="lm-top-title min-w-0">
                        <p class="lm-kicker">{{ auth()->user()->tenant?->name ?? 'Administration' }}</p>
                        <h1 class="truncate text-lg font-semibold">@yield('heading')</h1>
                    </div>
                </div>
                <div class="lm-actions">
                    <button class="lm-menu lm-icon-btn" type="button" data-lm-menu aria-expanded="false" aria-controls="lm-side">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <span class="lm-icon-label">Menu</span>
                    </button>
                    <a class="lm-icon-btn" href="{{ route('notifications.index') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 7 2 7 2 7H4s2 0 2-7Zm4 9a2 2 0 0 0 4 0"/></svg>
                        <span class="lm-icon-label">Notifications</span>
                    </a>
                    @if(auth()->user()->tenant && auth()->user()->hasPermission('settings.manage'))
                        <a class="lm-icon-btn" href="{{ route('business.edit') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0"/></svg>
                            <span class="lm-icon-label">Profil</span>
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="lm-icon-btn" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 7V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-2M4 12h11m0 0-3-3m3 3-3 3"/></svg>
                            <span class="lm-icon-label">Déconnexion</span>
                        </button>
                    </form>
                </div>
            </header>
            <main class="lm-content">
                @if(session('status'))
                    <p class="mb-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
                @endif
                @if(session('warning'))
                    <p class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">{{ session('warning') }}</p>
                @endif
                @if($errors->any())
                    <div class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                @include('partials.onboarding')
                @yield('content')
            </main>
        </div>
    </div>
    <footer class="lm-dock no-print">
        <p class="lm-sign">Développé par Edouard Bengehya</p>
        <nav class="lm-tabbar" aria-label="Navigation mobile">
            <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard', 'entrepreneur.dashboard')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/></svg>
                <span>Accueil</span>
            </a>
            <a href="{{ route('wifi-zones.index') }}" @if(request()->routeIs('wifi-zones.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm-4.6-3.2a7 7 0 0 1 9.2 0M4.9 12a11 11 0 0 1 14.2 0M2.2 8.6a15 15 0 0 1 19.6 0"/></svg>
                <span>Zones</span>
            </a>
            <a href="{{ route('vouchers.index') }}" @if(request()->routeIs('vouchers.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/></svg>
                <span>Tickets</span>
            </a>
            <a href="{{ route('sales.index') }}" @if(request()->routeIs('sales.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12zM6 6 5 3H2M9 20a1 1 0 1 0 0.01 0M18 20a1 1 0 1 0 0.01 0"/></svg>
                <span>Ventes</span>
            </a>
            <a href="{{ route('settings.edit') }}" @if(request()->routeIs('settings.*', 'business.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm8 4-2.1.6a7 7 0 0 1-.7 1.6l1.2 1.8-1.6 1.6-1.8-1.2a7 7 0 0 1-1.6.7L12 20l-1.4-2.1a7 7 0 0 1-1.6-.7l-1.8 1.2-1.6-1.6 1.2-1.8a7 7 0 0 1-.7-1.6L4 12l2.1-.6a7 7 0 0 1 .7-1.6L5.6 8l1.6-1.6 1.8 1.2a7 7 0 0 1 1.6-.7L12 4l1.4 2.1a7 7 0 0 1 1.6.7l1.8-1.2L18.4 8l-1.2 1.8c.3.5.5 1 .7 1.6z"/></svg>
                <span>Plus</span>
            </a>
        </nav>
    </footer>
    @stack('scripts')
</body>
</html>
