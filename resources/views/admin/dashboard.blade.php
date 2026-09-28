@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Plateforme</h1>
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach([
        'Entrepreneurs' => $metrics['tenants'],
        'Entrepreneurs actifs' => $metrics['tenants_active'],
        'WiFi Zones' => $metrics['zones'],
        'MikroTik' => $metrics['mikrotiks'],
        'Abonnements actifs' => $metrics['subscriptions_active'],
        'Abonnements expirés' => $metrics['subscriptions_expired'],
        'Revenus plateforme' => \App\Support\Money::format($metrics['platform_revenue']),
    ] as $label => $value)
        <article class="rounded-2xl bg-white p-4"><p class="text-sm text-slate-500">{{ $label }}</p><p class="text-2xl font-semibold">{{ $value }}</p></article>
    @endforeach
</div>
@endsection
