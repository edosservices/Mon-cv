<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php $shellTenant = auth()->user()?->tenant; @endphp
    <title>@yield('title', $shellTenant?->name ?: 'Espace entrepreneur')</title>
    <link rel="icon" href="{{ $shellTenant?->logoUrl() ?: asset('brand/logo-limete-wifi-manager.png') }}">
    {{-- Coque visible même si le bundle Vite est absent ou périmé : le logo ne peut plus s’afficher en taille native. --}}
    <style>
        .lm-app { color: #122033; font-family: "Segoe UI", system-ui, sans-serif; background: #f3f6fb; }
        .lm-brand-wordmark, .lm-brand-logo, .lm-nav-logo, .lm-account-logo { max-width: 180px; max-height: 52px; width: auto; height: auto; object-fit: contain; }
        .lm-frame { min-height: 100vh; display: grid; }
        .lm-side { display: flex; flex-direction: column; min-width: 0; background: #071428; color: #fff; }
        .lm-side a, .lm-nav-link, .lm-nav summary { color: inherit; text-decoration: none; }
        .lm-brand, .lm-account-card, .lm-word { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .lm-account-copy, .lm-brand-copy { display: grid; min-width: 0; }
        .lm-account-copy strong, .lm-account-copy small, .lm-brand-copy strong, .lm-brand-copy small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lm-collapse { display: none; }
        .lm-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
        @media (max-width: 1023px) {
            .lm-side { position: fixed; inset: 0 auto 0 0; width: min(86vw, 300px); z-index: 40; transform: translateX(-105%); overflow: auto; }
            .lm-side.is-open { transform: none; }
        }
        @media (min-width: 1024px) {
            .lm-frame { grid-template-columns: 248px minmax(0, 1fr); }
            .lm-side { position: sticky; top: 0; height: 100vh; overflow: auto; }
            .lm-menu, .lm-tabbar { display: none; }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @auth
        @if($shellTenant)
            <style>
                .bg-electric { background-color: {{ $shellTenant->buttonColor() }} !important; }
                .text-electric { color: {{ $shellTenant->brandColor() }} !important; }
                .border-electric { border-color: {{ $shellTenant->brandColor() }} !important; }
            </style>
        @endif
    @endauth
</head>
<body class="lm-app min-h-screen text-ink" @auth style="--biz: {{ $shellTenant?->brandColor() ?? '#0b5ed7' }}; --biz-2: {{ $shellTenant?->secondaryColor() ?? '#071e3d' }}; --biz-btn: {{ $shellTenant?->buttonColor() ?? '#0b5ed7' }};" @endauth>
    <x-animated-background />
    <div class="lm-backdrop" data-lm-backdrop></div>
    <div class="lm-frame">
        <aside class="lm-side no-print" id="lm-side">
            @php
                $account = auth()->user();
                $business = $account->tenant;
                $place = trim(implode(' · ', array_filter([$business?->city, $business?->country])));
            @endphp
            <div class="lm-side-head">
                <a class="lm-brand" href="{{ route('dashboard') }}">
                    @if($business?->logoUrl())
                        <img class="lm-brand-logo" src="{{ $business->logoUrl() }}" alt="" width="36" height="36">
                    @else
                        <span class="lm-account-mark">{{ mb_substr($business?->name ?: $account->name, 0, 1) }}</span>
                    @endif
                    <span class="lm-brand-copy">
                        <strong>{{ $business?->name ?: 'Mon espace' }}</strong>
                        <small>Espace entrepreneur</small>
                    </span>
                </a>
                <button class="lm-collapse" type="button" data-lm-collapse aria-pressed="false">Réduire</button>
            </div>
            <nav class="lm-side-nav" aria-label="Navigation">
                @include('layouts.nav')
            </nav>
            <div class="lm-account">
                <a class="lm-account-card" href="{{ $account->hasPermission('settings.manage') ? route('business.edit') : route('dashboard') }}">
                    @if($business?->logoUrl())
                        <img class="lm-account-logo" src="{{ $business->logoUrl() }}" alt="" width="52" height="34">
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
            <p class="lm-sign lm-side-sign">Développé par Edouard Bengehya</p>
        </aside>
        <div class="lm-main min-w-0">
            <header class="lm-top no-print">
                <button class="lm-menu lm-icon-btn" type="button" data-lm-menu aria-expanded="false" aria-controls="lm-side">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <span class="lm-sr">Menu</span>
                </button>
                <a class="lm-word" href="{{ route('dashboard') }}">
                    @if($business?->logoUrl())
                        <img class="lm-nav-logo" src="{{ $business->logoUrl() }}" alt="" width="36" height="36">
                    @else
                        <span class="lm-account-mark">{{ mb_substr($business?->name ?: $account->name, 0, 1) }}</span>
                    @endif
                    <span>{{ $business?->name ?: 'Mon espace' }}</span>
                </a>
                <div class="lm-top-title min-w-0">
                    <h1 class="truncate">@yield('heading')</h1>
                </div>
                <div class="lm-actions">
                    <span class="lm-user-name">{{ $account->name }}</span>
                    <a class="lm-icon-btn" href="{{ route('notifications.index') }}" aria-label="Notifications">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 7 2 7 2 7H4s2 0 2-7Zm4 9a2 2 0 0 0 4 0"/></svg>
                        <span class="lm-sr">Notifications</span>
                    </a>
                    <details class="lm-account-menu">
                        <summary aria-label="Profil">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</summary>
                        <div class="lm-account-panel">
                            @if(auth()->user()->tenant && auth()->user()->hasPermission('settings.manage'))
                                <a href="{{ route('business.edit') }}">Profil</a>
                                <a href="{{ route('settings.edit') }}">Paramètres</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">Déconnexion</button>
                            </form>
                        </div>
                    </details>
                </div>
            </header>
            <main class="lm-content">
                <h1 class="lm-mobile-heading">@yield('heading')</h1>
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
                <span>WiFi</span>
            </a>
            <a href="{{ route('vouchers.index') }}" @if(request()->routeIs('vouchers.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/></svg>
                <span>Tickets</span>
            </a>
            <a href="{{ route('sales.index') }}" @if(request()->routeIs('sales.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h15l-1.5 9h-12zM6 6 5 3H2M9 20a1 1 0 1 0 0.01 0M18 20a1 1 0 1 0 0.01 0"/></svg>
                <span>Ventes</span>
            </a>
            <details class="lm-more">
                <summary>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h.01M12 12h.01M18 12h.01"/></svg>
                    <span>Plus</span>
                </summary>
                <div class="lm-more-sheet">
                    @if(auth()->user()->hasPermission('customers.manage'))
                        <a href="{{ route('customers.index') }}">Clients</a>
                    @endif
                    @if(auth()->user()->hasPermission('mikrotiks.manage'))
                        <a href="{{ route('mikrotiks.index') }}">Routeur</a>
                    @endif
                    @if(auth()->user()->hasPermission('sales.view'))
                        <a href="{{ route('reports.index') }}">Rapports</a>
                    @endif
                    @if(auth()->user()->hasPermission('settings.manage'))
                        <a href="{{ route('settings.edit') }}">Paramètres</a>
                        <a href="{{ route('business.edit') }}">Profil</a>
                    @endif
                    <p class="lm-sign">Développé par Edouard Bengehya</p>
                </div>
            </details>
        </nav>
    </footer>
    @stack('scripts')
</body>
</html>
