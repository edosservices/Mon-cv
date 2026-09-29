@extends('layouts.admin')
@section('content')
<p class="lm-kicker">Réseau global</p>
<h1 class="mb-4 text-2xl font-semibold">Plateforme</h1>
<div class="lm-grid sm:grid-cols-2 xl:grid-cols-3">
    @foreach([
        'Entrepreneurs' => $metrics['tenants'],
        'Entrepreneurs actifs' => $metrics['tenants_active'],
        'WiFi Zones' => $metrics['zones'],
        'MikroTik' => $metrics['mikrotiks'],
        'Abonnements actifs' => $metrics['subscriptions_active'],
        'Abonnements expirés' => $metrics['subscriptions_expired'],
        'Revenus plateforme' => \App\Support\Money::format($metrics['platform_revenue']),
    ] as $label => $value)
        <x-ui.kpi :label="$label">{{ $value }}</x-ui.kpi>
    @endforeach
</div>
@endsection
