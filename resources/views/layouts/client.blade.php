<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Espace client')</title>
    <link rel="icon" href="{{ asset('brand/logo-limete-wifi-manager.png') }}">
    @vite(['resources/css/client.css', 'resources/js/client.js'])
</head>
<body class="client">
    <x-animated-background />
    <div class="client-wrap">
        <header class="client-top">
            <a class="lm-brand" href="{{ auth()->check() && auth()->user()->isClient() ? route('client.dashboard') : route('home') }}" style="color: inherit;">
                <x-brand-logo />
                <span>
                    <strong>LIMETE WIFI</strong>
                    <small>@yield('heading', 'Espace client')</small>
                </span>
            </a>
            @auth
                @if(auth()->user()->isClient())
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-light">Déconnexion</button>
                    </form>
                @endif
            @endauth
        </header>
        @auth
            @if(auth()->user()->isClient())
                <nav class="client-nav" aria-label="Espace client">
                    <a href="{{ route('client.dashboard') }}" @if(request()->routeIs('client.dashboard')) aria-current="page" @endif>Accueil</a>
                    <a href="{{ route('client.tickets') }}" @if(request()->routeIs('client.tickets')) aria-current="page" @endif>Mes tickets</a>
                    <a href="{{ route('client.buy') }}" @if(request()->routeIs('client.buy')) aria-current="page" @endif>Acheter</a>
                    <a href="{{ route('client.profile') }}" @if(request()->routeIs('client.profile')) aria-current="page" @endif>Profil</a>
                </nav>
            @endif
        @endauth
        @if(session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)<p class="mb-0">{{ $error }}</p>@endforeach
            </div>
        @endif
        @yield('content')
    </div>
</body>
</html>
