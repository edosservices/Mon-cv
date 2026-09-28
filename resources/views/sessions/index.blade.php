@extends('layouts.app')
@section('heading', 'Sessions')
@section('content')
<form class="mb-4" method="GET"><input class="w-full rounded-lg border px-3 py-2" name="q" value="{{ request('q') }}" placeholder="Utilisateur"></form>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
    <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="text-slate-500"><tr><th class="p-3">Utilisateur</th><th>Zone</th><th>MikroTik</th><th>Début</th><th>Fin</th><th>IP</th></tr></thead>
        <tbody>
        @foreach($sessions as $session)
            <tr class="border-t">
                <td class="p-3">{{ $session->username }}</td>
                <td>{{ $session->wifiZone->name ?? '' }}</td>
                <td>{{ $session->mikrotik->name ?? '' }}</td>
                <td>{{ $session->started_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                <td>{{ $session->ended_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'En cours' }}</td>
                <td>{{ $session->ip_address }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $sessions->links() }}</div>
@endsection
