@php
    $can = fn (?string $permission) => $permission === null || auth()->user()->hasPermission($permission);
    $groups = [
        [
            'label' => 'Ventes',
            'icon' => '≡',
            'links' => array_values(array_filter([
                $can('sales.view') ? ['sales.index', 'Ventes', ['sales.index', 'entrepreneur.sales']] : null,
                $can('sales.confirm') ? ['sales.quick', 'Encaisser', ['sales.quick']] : null,
                $can('sales.view') ? ['payments.index', 'Paiements', ['payments.index']] : null,
                ['audit.index', 'Historique', ['audit.index']],
            ])),
        ],
        [
            'label' => 'Tickets',
            'icon' => '▤',
            'links' => array_values(array_filter([
                $can('vouchers.manage') ? ['vouchers.index', 'Tickets', ['vouchers.index', 'entrepreneur.tickets']] : null,
                $can('vouchers.manage') ? ['vouchers.generate', 'Générer', ['vouchers.generate', 'entrepreneur.generate']] : null,
            ])),
        ],
        [
            'label' => 'Clients',
            'icon' => '●',
            'links' => array_values(array_filter([
                $can('customers.manage') ? ['customers.index', 'Clients', ['customers.index', 'entrepreneur.customers']] : null,
                $can('sessions.view') ? ['sessions.index', 'Sessions', ['sessions.index']] : null,
            ])),
        ],
        [
            'label' => 'WiFi',
            'icon' => '⌁',
            'links' => array_values(array_filter([
                $can('zones.manage') ? ['wifi-zones.index', 'Zones', ['wifi-zones.index', 'entrepreneur.zones']] : null,
                $can('plans.manage') ? ['entrepreneur.profiles', 'Profils', ['entrepreneur.profiles', 'entrepreneur.profiles.*']] : null,
                $can('plans.manage') ? ['plans.index', 'Forfaits', ['plans.index', 'plans.*']] : null,
                $can('sessions.view') ? ['active-users.index', 'Utilisateurs', ['active-users.index', 'entrepreneur.users']] : null,
            ])),
        ],
        [
            'label' => 'Routeur',
            'icon' => '⌂',
            'links' => array_values(array_filter([
                $can('mikrotiks.manage') ? ['mikrotiks.index', 'Vue', ['mikrotiks.index', 'mikrotiks.show', 'entrepreneur.mikrotik']] : null,
                $can('mikrotiks.manage') ? ['mikrotiks.assistant', 'HotSpot', ['mikrotiks.assistant']] : null,
                $can('sessions.view') ? ['active-users.index', 'Sessions', ['active-users.index']] : null,
                $can('settings.manage') ? ['settings.edit', 'Paramètres', ['settings.edit', 'entrepreneur.settings']] : null,
            ])),
        ],
        [
            'label' => 'Rapports',
            'icon' => '▥',
            'links' => array_values(array_filter([
                $can('sales.view') ? ['reports.index', 'Résumé', ['reports.index', 'entrepreneur.reports']] : null,
                $can('sales.view') ? ['sales.index', 'Ventes', ['sales.index']] : null,
                $can('statistics.view') ? ['statistics', 'Utilisation', ['statistics', 'entrepreneur.statistics']] : null,
            ])),
        ],
        [
            'label' => 'Plus',
            'icon' => '⋯',
            'links' => array_values(array_filter([
                $can('settings.manage') ? ['business.edit', 'Business', ['business.edit', 'entrepreneur.business']] : null,
                $can('staff.manage') ? ['staff.index', 'Équipe', ['staff.index']] : null,
                $can('subscription.manage') ? ['subscription.show', 'Abonnement', ['subscription.show']] : null,
                ['notifications.index', 'Alertes', ['notifications.index']],
            ])),
        ],
    ];
    $current = function (array $names): bool {
        foreach ($names as $name) {
            if (request()->routeIs($name)) {
                return true;
            }
        }

        return false;
    };
@endphp
<div class="lm-nav">
    @php $exclusiveOpen = false; @endphp
    <a class="lm-nav-link {{ request()->routeIs('dashboard', 'entrepreneur.dashboard') ? 'is-current' : '' }}" href="{{ route('dashboard') }}">
        <span class="lm-ico" aria-hidden="true">▣</span>
        <span class="lm-nav-label">Tableau</span>
    </a>
    @foreach($groups as $group)
        @if($group['links'] !== [])
            @php
                $opened = false;
                if (! $exclusiveOpen) {
                    foreach ($group['links'] as $link) {
                        if ($current($link[2])) {
                            $opened = true;
                            $exclusiveOpen = true;
                            break;
                        }
                    }
                }
            @endphp
            <details class="lm-nav-group" @if($opened) open @endif>
                <summary>
                    <span class="lm-ico" aria-hidden="true">{{ $group['icon'] }}</span>
                    <span class="lm-nav-label">{{ $group['label'] }}</span>
                </summary>
                <div class="lm-nav-sub">
                    @foreach($group['links'] as [$route, $label, $names])
                        <a class="{{ $current($names) ? 'is-current' : '' }}" href="{{ route($route) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</div>
