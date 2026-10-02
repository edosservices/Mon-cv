@php
    $can = fn (?string $permission) => $permission === null || auth()->user()->hasPermission($permission);
    $groups = [
        [
            'label' => 'Ventes',
            'links' => array_values(array_filter([
                $can('sales.confirm') ? ['sales.quick', 'Nouvelle vente', ['sales.quick', 'sales.quick.done']] : null,
                $can('sales.view') ? ['sales.index', 'Historique', ['sales.index', 'sales.show', 'entrepreneur.sales']] : null,
                $can('sales.view') ? ['payments.index', 'Paiements', ['payments.index']] : null,
                ['audit.index', 'Journal', ['audit.index']],
            ])),
        ],
        [
            'label' => 'Tickets',
            'links' => array_values(array_filter([
                $can('vouchers.manage') ? ['vouchers.index', 'Mes tickets', ['vouchers.index', 'vouchers.show', 'entrepreneur.tickets']] : null,
                $can('vouchers.manage') ? ['vouchers.generate', 'Générer', ['vouchers.generate', 'vouchers.generated', 'entrepreneur.generate']] : null,
            ])),
        ],
        [
            'label' => 'WiFi',
            'links' => array_values(array_filter([
                $can('zones.manage') ? ['wifi-zones.index', 'Mes zones', ['wifi-zones.index', 'wifi-zones.create', 'wifi-zones.edit', 'entrepreneur.zones']] : null,
                $can('mikrotiks.manage') ? ['mikrotiks.index', 'Routeurs', ['mikrotiks.index', 'mikrotiks.show', 'mikrotiks.create', 'mikrotiks.edit', 'entrepreneur.mikrotik']] : null,
                $can('mikrotiks.manage') ? ['mikrotiks.assistant', 'Connexion MikroTik', ['mikrotiks.assistant', 'mikrotiks.assistant.show']] : null,
                $can('plans.manage') ? ['plans.index', 'Forfaits', ['plans.index', 'plans.create', 'plans.edit']] : null,
                $can('plans.manage') ? ['entrepreneur.profiles', 'Profils', ['entrepreneur.profiles', 'entrepreneur.profiles.*']] : null,
                $can('sessions.view') ? ['active-users.index', 'Connectés', ['active-users.index', 'entrepreneur.users']] : null,
            ])),
        ],
        [
            'label' => 'Clients',
            'links' => array_values(array_filter([
                $can('customers.manage') ? ['customers.index', 'Clients', ['customers.index', 'customers.show', 'entrepreneur.customers']] : null,
                $can('sessions.view') ? ['sessions.index', 'Sessions', ['sessions.index']] : null,
            ])),
        ],
        [
            'label' => 'Statistiques',
            'links' => array_values(array_filter([
                $can('sales.view') ? ['reports.index', 'Rapports', ['reports.index', 'entrepreneur.reports']] : null,
                $can('statistics.view') ? ['statistics', 'Statistiques', ['statistics', 'entrepreneur.statistics']] : null,
            ])),
        ],
        [
            'label' => 'Personnalisation',
            'links' => array_values(array_filter([
                $can('settings.manage') ? ['business.edit', 'Mon entreprise', ['business.edit', 'entrepreneur.business']] : null,
            ])),
        ],
        [
            'label' => 'Paramètres',
            'links' => array_values(array_filter([
                $can('settings.manage') ? ['settings.edit', 'Configuration', ['settings.edit', 'entrepreneur.settings']] : null,
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
    <a class="lm-nav-link {{ request()->routeIs('dashboard', 'entrepreneur.dashboard') ? 'is-current' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard', 'entrepreneur.dashboard')) aria-current="page" @endif>
        <span class="lm-nav-label">Accueil</span>
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
                    <span class="lm-nav-label">{{ $group['label'] }}</span>
                </summary>
                <div class="lm-nav-sub">
                    @foreach($group['links'] as [$route, $label, $names])
                        <a class="{{ $current($names) ? 'is-current' : '' }}" href="{{ route($route) }}" @if($current($names)) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</div>
