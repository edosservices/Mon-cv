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
                    <p class="mt-1 break-all text-sm">/wifi/{{ $zone->slug }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($zone->status === 'active')
                        <a class="rounded-xl bg-electric px-4 py-3 text-sm font-semibold text-white" href="{{ route('shop.show', $zone->slug) }}" target="_blank" rel="noopener">Voir ma boutique</a>
                        <button type="button" class="js-copy rounded-xl border border-slate-300 px-4 py-3 text-sm" data-url="{{ route('shop.show', $zone->slug) }}">Copier le lien</button>
                    @endif
                    <a class="rounded-xl border border-slate-300 px-4 py-3 text-sm" href="{{ route('wifi-zones.edit', $zone) }}">Modifier</a>
                </div>
            </div>
        </article>
    @empty
        <p class="text-sm text-slate-500">Aucune zone pour le moment.</p>
    @endforelse
</div>
@endsection
