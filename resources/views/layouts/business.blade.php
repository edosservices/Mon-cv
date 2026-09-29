<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mon business') — {{ auth()->user()->tenant?->name ?? 'LIMETE WIFI' }}</title>
    @vite(['resources/css/business.css', 'resources/js/business.js'])
</head>
<body class="biz" style="--biz: {{ auth()->user()->tenant?->brandColor() ?? '#0b5ed7' }}; --biz-2: {{ auth()->user()->tenant?->secondaryColor() ?? '#071e3d' }}; --biz-btn: {{ auth()->user()->tenant?->buttonColor() ?? '#0b5ed7' }}">
    <header class="biz-top no-print">
        <div class="biz-brand">
            @if(auth()->user()->tenant?->logoUrl())
                <img src="{{ auth()->user()->tenant->logoUrl() }}" alt="">
            @else
                <span class="biz-mark">{{ mb_substr(auth()->user()->tenant?->name ?? 'W', 0, 1) }}</span>
            @endif
            <div>
                <strong>{{ auth()->user()->tenant?->name ?? 'Business' }}</strong>
                <small>@yield('heading')</small>
            </div>
        </div>
        <div class="biz-tools">
            <a href="{{ route('notifications.index') }}" aria-label="Notifications">Notifications</a>
            <a href="{{ route('business.edit') }}">Profil</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sm btn-light">Déconnexion</button>
            </form>
        </div>
    </header>

    <div class="biz-shell">
        <nav class="biz-nav no-print" aria-label="Espace entrepreneur">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('business.edit') }}" @if(request()->routeIs('business.edit', 'settings.edit')) aria-current="page" @endif>Mon Business</a>
            <a href="{{ route('wifi-zones.index') }}" @if(request()->routeIs('wifi-zones.*')) aria-current="page" @endif>WiFi Zones</a>
            <a href="{{ route('plans.index') }}" @if(request()->routeIs('plans.*')) aria-current="page" @endif>Forfaits</a>
            <a href="{{ route('sales.quick') }}" @if(request()->routeIs('sales.quick*')) aria-current="page" @endif>Vente rapide</a>
            <a href="{{ route('customers.index') }}" @if(request()->routeIs('customers.*')) aria-current="page" @endif>Clients</a>
            <a href="{{ route('reports.index') }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif>Rapports</a>
            <a href="{{ route('vouchers.index') }}">Tickets</a>
            <a href="{{ route('mikrotiks.assistant') }}" @if(request()->routeIs('mikrotiks.assistant*')) aria-current="page" @endif>Mon MikroTik</a>
        </nav>
        <main class="biz-main">
            @include('partials.onboarding', ['onboardingTheme' => 'business'])
            @if(session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)<p class="mb-0">{{ $error }}</p>@endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <div class="modal fade" id="confirmDelete" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" id="confirmDeleteForm" class="modal-content">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h2 class="modal-title h5" id="confirmDeleteLabel">Confirmer</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">Cette action retire l’élément de votre espace. Les ventes déjà enregistrées restent dans les rapports.</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
