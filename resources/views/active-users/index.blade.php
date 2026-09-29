@extends('layouts.app')
@section('heading', 'Sessions')
@section('content')
@php
    $rows = [];
    foreach ($groups as $group) {
        foreach ($group['users'] as $user) {
            $rows[] = ['router' => $group['router'], 'user' => $user];
        }
    }
@endphp
<section class="rounded-2xl bg-white p-4 shadow-sm" data-live-table>
    <div class="lm-live-tools">
        <input type="search" data-live-search placeholder="Rechercher" aria-label="Rechercher une session">
        <select data-live-status aria-label="Filtrer le statut">
            <option value="">Tous</option>
            <option value="online">En ligne</option>
            <option value="offline">Hors ligne</option>
        </select>
    </div>
    @if($rows === [])
        @forelse($groups as $group)
            <p class="mt-3 text-sm text-slate-500">{{ $group['router']->name }} · {{ $group['router']->last_error ?: 'Aucun utilisateur connecté.' }}</p>
        @empty
            <p class="mt-3 text-sm text-slate-500">Aucun utilisateur connecté.</p>
        @endforelse
    @else
        <div class="lm-table-wrap mt-3">
            <table class="lm-table">
                <thead>
                    <tr>
                        <th><button class="lm-sort" type="button" data-live-sort="client">Client</button></th>
                        <th><button class="lm-sort" type="button" data-live-sort="username">Username</button></th>
                        <th><button class="lm-sort" type="button" data-live-sort="profile">Profil</button></th>
                        <th><button class="lm-sort" type="button" data-live-sort="ip">IP</button></th>
                        <th><button class="lm-sort" type="button" data-live-sort="rate">Débit</button></th>
                        <th><button class="lm-sort" type="button" data-live-sort="time">Temps</button></th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php $user = $row['user']; @endphp
                        <tr data-live-row data-status="online">
                            <td data-k="client">{{ $user['client_name'] ?: '—' }}</td>
                            <td data-k="username">{{ $user['user'] ?? '' }}</td>
                            <td data-k="profile">{{ $user['profile_name'] ?: '—' }}@if(! empty($user['plan_name']) && $user['plan_name'] !== ($user['profile_name'] ?? null))<span class="block text-xs">{{ $user['plan_name'] }}</span>@endif</td>
                            <td data-k="ip">{{ $user['address'] ?? '—' }}</td>
                            <td data-k="rate">{{ $user['rate'] ?: '—' }}</td>
                            <td data-k="time">
                                {{ $user['uptime'] ?? '—' }}
                                <span class="block text-xs text-slate-500">Expiration {{ $user['commercial_expires']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</span>
                            </td>
                            <td><span class="lm-status is-on">En ligne</span></td>
                            <td>
                                <details class="lm-pop">
                                    <summary aria-label="Actions">⋮</summary>
                                    <div class="lm-pop-panel">
                                        <form method="POST" action="{{ route('active-users.disconnect') }}">
                                            @csrf
                                            <input type="hidden" name="mikrotik_id" value="{{ $row['router']->id }}">
                                            <input type="hidden" name="active_id" value="{{ $user['.id'] ?? '' }}">
                                            <input type="hidden" name="username" value="{{ $user['user'] ?? '' }}">
                                            <button type="submit">Déconnecter</button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-slate-500" data-live-empty hidden>Aucun client pour ce filtre.</p>
        <div class="lm-pager" data-live-pager hidden>
            <button type="button" data-live-prev>Précédent</button>
            <span data-live-page>1 / 1</span>
            <button type="button" data-live-next>Suivant</button>
        </div>
    @endif
    @if($rows !== [])
        @foreach($groups as $group)
            @if($group['users'] === [] && $group['router']->last_error)
                <p class="mt-2 text-sm text-slate-500">{{ $group['router']->name }} · {{ $group['router']->last_error }}</p>
            @endif
        @endforeach
    @endif
</section>
@endsection
