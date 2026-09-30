@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">MikroTik des entrepreneurs</h1>
<div class="overflow-x-auto rounded-2xl bg-white">
    <table class="min-w-full text-sm">
        <thead class="text-left text-slate-500">
            <tr>
                <th class="px-4 py-3">Entrepreneur</th>
                <th class="px-4 py-3">WiFi Zone</th>
                <th class="px-4 py-3">MikroTik</th>
                <th class="px-4 py-3">Statut</th>
                <th class="px-4 py-3">Dernière synchronisation</th>
                <th class="px-4 py-3">Erreur</th>
            </tr>
        </thead>
        <tbody>
            @forelse($routers as $router)
                <tr class="border-t">
                    <td class="px-4 py-3">{{ $router->tenant->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $router->wifiZone->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $router->name }}</td>
                    <td class="px-4 py-3">{{ $router->status ?: 'inconnu' }}</td>
                    <td class="px-4 py-3">{{ $router->last_synced_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? ($router->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'pas encore') }}</td>
                    <td class="px-4 py-3">{{ $router->last_error ?: '—' }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-3" colspan="6">Aucun MikroTik.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
