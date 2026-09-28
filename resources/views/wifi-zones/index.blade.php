@extends('layouts.app')
@section('heading', 'WiFi Zones')
@section('content')
<div class="mb-4"><a class="rounded-lg bg-electric px-4 py-2 text-sm text-white" href="{{ route('wifi-zones.create') }}">Nouvelle zone</a></div>
<div class="space-y-3">
    @forelse($zones as $zone)
        <article class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-semibold">{{ $zone->name }}</h2>
                    <p class="text-sm text-slate-500">{{ $zone->location }} · {{ $zone->status === 'active' ? 'Active' : 'Inactive' }}</p>
                    <p class="mt-1 text-sm">Page publique : /wifi/{{ $zone->slug }}</p>
                </div>
                <a class="text-sm text-electric" href="{{ route('wifi-zones.edit', $zone) }}">Modifier</a>
            </div>
        </article>
    @empty
        <p class="text-sm text-slate-500">Aucune zone pour le moment.</p>
    @endforelse
</div>
@endsection
