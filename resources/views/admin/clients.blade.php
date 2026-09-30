@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Clients</h1>
<div class="space-y-3">
    @forelse($clients as $client)
        <article class="rounded-2xl bg-white p-4">
            <h2 class="font-semibold">{{ $client->name }}</h2>
            <p class="text-sm text-slate-500">{{ $client->client_phone ?: $client->phone }}</p>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-4 text-sm text-slate-500">Aucun client.</p>
    @endforelse
</div>
<div class="mt-4">{{ $clients->links() }}</div>
@endsection
