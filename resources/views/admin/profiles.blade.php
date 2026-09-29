@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Profils</h1>
<div class="space-y-3">
    @forelse($plans as $plan)
        <article class="rounded-2xl bg-white p-4">
            <h2 class="font-semibold">{{ $plan->mikrotik_profile ?: $plan->name }}</h2>
            <p class="text-sm text-slate-500">{{ $plan->tenant->name ?? 'Entreprise' }} · {{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-4 text-sm text-slate-500">Aucun profil.</p>
    @endforelse
</div>
<div class="mt-4">{{ $plans->links() }}</div>
@endsection
