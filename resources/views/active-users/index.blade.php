@extends('layouts.app')
@section('heading', 'Utilisateurs connectés')
@section('content')
@foreach($groups as $group)
    <section class="mb-5 rounded-2xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">{{ $group['router']->name }} · {{ $group['router']->wifiZone->name ?? '' }}</h2>
        @if($group['users'] === [])
            <p class="mt-2 text-sm text-slate-500">{{ $group['router']->last_error ?: 'Aucun utilisateur connecté.' }}</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead><tr><th>Utilisateur</th><th>IP</th><th>MAC</th><th>Temps</th><th></th></tr></thead>
                    <tbody>
                    @foreach($group['users'] as $user)
                        <tr class="border-t">
                            <td class="py-2">{{ $user['user'] ?? '' }}</td>
                            <td>{{ $user['address'] ?? '' }}</td>
                            <td>{{ $user['mac-address'] ?? '' }}</td>
                            <td>{{ $user['uptime'] ?? '' }} / {{ $user['session-time-left'] ?? '' }}</td>
                            <td>
                                <form method="POST" action="{{ route('active-users.disconnect') }}">
                                    @csrf
                                    <input type="hidden" name="mikrotik_id" value="{{ $group['router']->id }}">
                                    <input type="hidden" name="active_id" value="{{ $user['.id'] ?? '' }}">
                                    <input type="hidden" name="username" value="{{ $user['user'] ?? '' }}">
                                    <button class="text-red-700">Déconnecter</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endforeach
@endsection
