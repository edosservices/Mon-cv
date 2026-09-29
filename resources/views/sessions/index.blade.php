@extends('layouts.app')
@section('heading', 'Sessions')
@section('content')
<form class="mb-4" method="GET"><input class="w-full rounded-lg border px-3 py-2" name="q" value="{{ request('q') }}" placeholder="Utilisateur"></form>
<div class="space-y-3">
    @foreach($sessions as $session)
        <article class="min-w-0 rounded-2xl bg-white p-4 text-sm shadow-sm">
            <p class="font-semibold">{{ $session->username }}</p>
            <p>{{ $session->wifiZone->name ?? '' }} · {{ $session->mikrotik->name ?? '' }}</p>
            <p>Début {{ $session->started_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
            <p>Fin {{ $session->ended_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'En cours' }}</p>
            <p class="break-all">IP {{ $session->ip_address }}</p>
        </article>
    @endforeach
</div>
<div class="mt-4">{{ $sessions->links() }}</div>
@endsection
