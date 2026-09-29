<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LIMETE WIFI MANAGER')</title>
    <link rel="icon" href="{{ asset('brand/logo-limete-wifi.svg') }}">
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
    <div class="lm-backdrop" data-lm-backdrop></div>
    <div class="lm-frame">
        <aside class="lm-side no-print" id="lm-side">
            <div class="px-4 py-5">
                <a class="lm-brand" href="{{ route('dashboard') }}">
                    <x-brand-logo />
                    <span>
                        <strong>LIMETE WIFI MANAGER</strong>
                        <small>Gestion hotspot</small>
                    </span>
                </a>
                <button class="lm-collapse" type="button" data-lm-collapse aria-pressed="false">Réduire</button>
            </div>
            <nav class="space-y-1 px-3 pb-8 text-sm" aria-label="Navigation">
                @include('layouts.nav')
            </nav>
        </aside>
        <div class="lm-main min-w-0">
            <header class="lm-top no-print">
                <div class="flex min-w-0 items-center gap-2">
                    <button class="lm-menu lg:hidden" type="button" data-lm-menu aria-expanded="false" aria-controls="lm-side">Menu</button>
                    @if(auth()->user()->tenant?->logoUrl())
                        <img src="{{ auth()->user()->tenant->logoUrl() }}" alt="" class="h-10 w-10 shrink-0 rounded-xl object-cover">
                    @endif
                    <div class="lm-top-title min-w-0">
                        <p class="lm-kicker">{{ auth()->user()->tenant?->name ?? 'Administration' }}</p>
                        <h1 class="truncate text-lg font-semibold">@yield('heading')</h1>
                    </div>
                </div>
                <div class="lm-actions">
                    <a href="{{ route('notifications.index') }}" aria-label="Notifications">Notifications</a>
                    @if(auth()->user()->tenant && auth()->user()->hasPermission('settings.manage'))
                        <a href="{{ route('business.edit') }}">Profil</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button>Déconnexion</button>
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
                <p class="lm-foot">LIMETE WIFI MANAGER</p>
            </main>
        </div>
    </div>
    <nav class="lm-tabbar no-print" aria-label="Navigation mobile">
        <a href="{{ route('dashboard') }}">Accueil</a>
        <a href="{{ route('wifi-zones.index') }}">Zones</a>
        <a href="{{ route('vouchers.index') }}">Tickets</a>
        <a href="{{ route('sales.index') }}">Ventes</a>
        <a href="{{ route('settings.edit') }}">Plus</a>
    </nav>
    @stack('scripts')
</body>
</html>
