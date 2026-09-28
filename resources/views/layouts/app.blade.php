<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LIMETE WIFI MANAGER')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-ink">
    <div class="min-h-screen lg:grid lg:grid-cols-[240px_1fr]">
        <aside class="no-print hidden bg-navy text-white lg:block">
            <div class="px-5 py-6">
                <p class="text-xs uppercase tracking-[0.18em] text-sky-200">Plateforme</p>
                <a href="{{ route('dashboard') }}" class="mt-1 block text-lg font-semibold">LIMETE WIFI MANAGER</a>
            </div>
            <nav class="space-y-1 px-3 pb-8 text-sm">
                @include('layouts.nav')
            </nav>
        </aside>
        <div class="min-w-0">
            <header class="no-print flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm text-slate-500">{{ auth()->user()->tenant?->name ?? 'Administration' }}</p>
                    <h1 class="truncate text-lg font-semibold">@yield('heading')</h1>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Sortir</button>
                </form>
            </header>
            <main class="mx-auto w-full max-w-6xl px-4 py-5 pb-24 lg:pb-8">
                @if(session('status'))
                    <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
                @endif
                @if(session('warning'))
                    <p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</p>
                @endif
                @if($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    <script>
        document.querySelectorAll('.js-copy').forEach(function (button) {
            button.addEventListener('click', function () {
                var url = button.getAttribute('data-url') || '';
                var done = function () { button.textContent = 'Lien copié'; };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copiez le lien', url); });
                    return;
                }
                window.prompt('Copiez le lien', url);
            });
        });
    </script>
    <nav class="no-print fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-slate-200 bg-white text-center text-[11px] lg:hidden">
        <a class="px-1 py-3" href="{{ route('dashboard') }}">Accueil</a>
        <a class="px-1 py-3" href="{{ route('wifi-zones.index') }}">Zones</a>
        <a class="px-1 py-3" href="{{ route('vouchers.index') }}">Tickets</a>
        <a class="px-1 py-3" href="{{ route('sales.index') }}">Ventes</a>
        <a class="px-1 py-3" href="{{ route('settings.edit') }}">Plus</a>
    </nav>
</body>
</html>
