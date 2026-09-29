@extends('layouts.app')
@section('heading', 'Utilisateurs connectés')
@section('content')
@foreach($groups as $group)
    <section class="mb-5 rounded-2xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">{{ $group['router']->name }} · {{ $group['router']->wifiZone->name ?? '' }}</h2>
        @if($group['users'] === [])
            <p class="mt-2 text-sm text-slate-500">{{ $group['router']->last_error ?: 'Aucun utilisateur connecté.' }}</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach($group['users'] as $user)
                    <article class="min-w-0 rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="font-semibold">{{ $user['user'] ?? '' }}</p>
                        <p class="break-all">IP {{ $user['address'] ?? '—' }}</p>
                        <p>Connexion {{ $user['uptime'] ?? '—' }}</p>
                        <p>Forfait {{ $user['plan_name'] ?: 'Selon le ticket' }}</p>
                        <p>Expiration {{ $user['commercial_expires']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
                        <form class="mt-2" method="POST" action="{{ route('active-users.disconnect') }}">
                            @csrf
                            <input type="hidden" name="mikrotik_id" value="{{ $group['router']->id }}">
                            <input type="hidden" name="active_id" value="{{ $user['.id'] ?? '' }}">
                            <input type="hidden" name="username" value="{{ $user['user'] ?? '' }}">
                            <button class="rounded-lg border px-3 py-2 text-red-700">Déconnecter</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endforeach
@endsection
