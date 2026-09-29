@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">WiFi Zones</h1>
<div class="space-y-3">
    @forelse($zones as $zone)
        <article class="rounded-2xl bg-white p-4">
            <h2 class="font-semibold">{{ $zone->name }}</h2>
            <p class="text-sm text-slate-500">{{ $zone->tenant->name ?? 'Entreprise' }} · {{ $zone->status }}</p>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-4 text-sm text-slate-500">Aucune zone.</p>
    @endforelse
</div>
<div class="mt-4">{{ $zones->links() }}</div>
@endsection
