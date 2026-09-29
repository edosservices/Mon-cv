@php
    $groups = [
        'Pilotage' => [
            ['dashboard', 'Dashboard', null],
        ],
        'WiFi Zones' => [
            ['wifi-zones.index', 'Toutes les zones', 'zones.manage'],
        ],
        'Ventes' => [
            ['sales.quick', 'Vente rapide', 'sales.confirm'],
            ['sales.index', 'Toutes les ventes', 'sales.view'],
            ['vouchers.index', 'Tickets', 'vouchers.manage'],
            ['vouchers.generate', 'Générer des tickets', 'vouchers.manage'],
        ],
        'Clients' => [
            ['customers.index', 'Clients', 'customers.manage'],
        ],
        'Paiements' => [
            ['payments.index', 'Paiements', 'sales.view'],
        ],
        'MikroTik' => [
            ['mikrotiks.assistant', 'Connecter mon MikroTik', 'mikrotiks.manage'],
            ['mikrotiks.index', 'Routeurs', 'mikrotiks.manage'],
            ['active-users.index', 'Utilisateurs actifs', 'sessions.view'],
        ],
        'Rapports' => [
            ['reports.index', 'Rapports', 'sales.view'],
            ['sales.index', 'Ventes', 'sales.view'],
            ['dashboard', 'Revenus', null],
            ['plans.index', 'Forfaits', 'plans.manage'],
            ['customers.index', 'Clients', 'customers.manage'],
            ['statistics', 'Statistiques', 'statistics.view'],
        ],
        'Paramètres' => [
            ['business.edit', 'Mon Business', 'settings.manage'],
            ['settings.edit', 'Paramètres', 'settings.manage'],
            ['plans.index', 'Forfaits', 'plans.manage'],
            ['subscription.show', 'Abonnement', 'subscription.manage'],
            ['staff.index', 'Équipe', 'staff.manage'],
            ['notifications.index', 'Notifications', null],
            ['audit.index', 'Journal', null],
            ['sessions.index', 'Sessions', 'sessions.view'],
        ],
    ];
@endphp
@foreach($groups as $title => $links)
    <p class="px-3 pb-1 pt-4 text-[11px] uppercase tracking-wide text-sky-200">{{ $title }}</p>
    @foreach($links as [$route, $label, $permission])
        @if($permission === null || auth()->user()->hasPermission($permission))
            <a data-short="{{ mb_substr($label, 0, 1) }}" href="{{ route($route) }}{{ $route === 'dashboard' && $label === 'Revenus' ? '#revenus' : '' }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs($route) && $label !== 'Revenus' ? 'bg-white/15' : 'hover:bg-white/10' }}">{{ $label }}</a>
        @endif
    @endforeach
@endforeach
