<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LIMETE WIFI MANAGER')</title>
    <link rel="icon" href="{{ asset('brand/logo-limete-wifi.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="lm-guest min-h-screen">
    <x-animated-background variant="auth" />
    <div class="auth-shell">
        <aside class="auth-brand">
            <a class="lm-guest-brand" href="{{ route('home') }}" style="color: inherit;">
                <x-brand-logo width="64" height="64" />
                <span>LIMETE WIFI MANAGER</span>
            </a>
            <h2>Gérez votre WiFi comme un vrai business.</h2>
            <p>Zones, forfaits, tickets et routeurs dans un seul espace.</p>
            <div class="auth-net" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div>
        </aside>
        <main class="auth-panel">
            <a class="lm-guest-brand auth-mobile-brand" href="{{ route('home') }}">
                <x-brand-logo />
                <span>LIMETE WIFI MANAGER</span>
            </a>
            @if($errors->any())
                <div class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
