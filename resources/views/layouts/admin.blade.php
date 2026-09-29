<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Administration')</title>
    <link rel="icon" href="{{ asset('brand/logo-limete-wifi.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="lm-admin min-h-screen text-ink">
    <header class="lm-top">
        <a class="lm-brand" href="{{ route('admin.dashboard') }}" style="color: inherit;">
            <x-brand-logo />
            <span>
                <strong>LIMETE WIFI MANAGER</strong>
                <small>Super admin</small>
            </span>
        </a>
        <nav class="lm-actions" aria-label="Administration">
            <a href="{{ route('admin.dashboard') }}">Tableau</a>
            <a href="{{ route('admin.tenants') }}">Entrepreneurs</a>
            <a href="{{ route('admin.mikrotiks') }}">MikroTik</a>
            <a href="{{ route('admin.plans') }}">Plans</a>
            <a href="{{ route('admin.payments') }}">Paiements</a>
            <a href="{{ route('admin.logs') }}">Journal</a>
            <a href="{{ route('admin.production-check') }}">Production</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button>Sortir</button></form>
        </nav>
    </header>
    <main class="lm-content">
        @if(session('status'))<p class="mb-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm" role="status">{{ session('status') }}</p>@endif
        @yield('content')
    </main>
</body>
</html>
