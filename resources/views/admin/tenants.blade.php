@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Entrepreneurs</h1>
<div class="space-y-3">
    @foreach($tenants as $tenant)
        <article class="rounded-2xl bg-white p-4">
            <h2 class="font-semibold">{{ $tenant->name }}</h2>
            <p class="text-sm text-slate-500">{{ $tenant->city }} · {{ $tenant->status }} · {{ $tenant->currentSubscription->saasPlan->name ?? '' }} {{ $tenant->currentSubscription?->status }}</p>
            <div class="mt-3 flex gap-2">
                <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $tenant->status === 'active' ? 'suspended' : 'active' }}"><button class="rounded-lg border px-3 py-2 text-sm">{{ $tenant->status === 'active' ? 'Suspendre' : 'Activer' }}</button></form>
                <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}">@csrf @method('DELETE')<button class="rounded-lg border px-3 py-2 text-sm">Archiver</button></form>
            </div>
        </article>
    @endforeach
</div>
<div class="mt-4">{{ $tenants->links() }}</div>
@endsection
