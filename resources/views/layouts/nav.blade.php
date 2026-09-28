@php
    $links = [
        ['dashboard', 'Dashboard', 'dashboard'],
        ['wifi-zones.index', 'WiFi Zones', 'zones.manage'],
        ['mikrotiks.index', 'MikroTik', 'mikrotiks.manage'],
        ['customers.index', 'Clients', 'customers.manage'],
        ['vouchers.index', 'Tickets', 'vouchers.manage'],
        ['plans.index', 'Forfaits', 'plans.manage'],
        ['sales.index', 'Ventes', 'sales.view'],
        ['payments.index', 'Paiements', 'sales.view'],
        ['active-users.index', 'Connectés', 'sessions.view'],
        ['sessions.index', 'Sessions', 'sessions.view'],
        ['statistics', 'Statistiques', 'statistics.view'],
        ['subscription.show', 'Abonnement', 'subscription.manage'],
        ['staff.index', 'Équipe', 'staff.manage'],
        ['settings.edit', 'Paramètres', 'settings.manage'],
        ['notifications.index', 'Notifications', null],
        ['audit.index', 'Journal', null],
    ];
@endphp
@foreach($links as [$route, $label, $permission])
    @if($permission === null || auth()->user()->hasPermission($permission))
        <a href="{{ route($route) }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs($route) ? 'bg-white/15' : 'hover:bg-white/10' }}">{{ $label }}</a>
    @endif
@endforeach
