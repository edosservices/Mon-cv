<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Administration')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-ink">
    <header class="flex flex-wrap items-center justify-between gap-3 bg-navy px-4 py-4 text-white">
        <div>
            <p class="text-xs uppercase tracking-[0.16em] text-sky-200">Super admin</p>
            <strong>LIMETE WIFI MANAGER</strong>
        </div>
        <nav class="flex flex-wrap gap-3 text-sm">
            <a href="{{ route('admin.dashboard') }}">Tableau</a>
            <a href="{{ route('admin.tenants') }}">Entrepreneurs</a>
            <a href="{{ route('admin.plans') }}">Plans</a>
            <a href="{{ route('admin.payments') }}">Paiements</a>
            <a href="{{ route('admin.logs') }}">Journal</a>
            <a href="{{ route('admin.production-check') }}">Production</a>
        </nav>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg bg-white/10 px-3 py-2">Sortir</button></form>
    </header>
    <main class="mx-auto w-full max-w-6xl px-4 py-6">
        @if(session('status'))<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm">{{ session('status') }}</p>@endif
        @yield('content')
    </main>
</body>
</html>
