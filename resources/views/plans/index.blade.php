@extends('layouts.app')
@section('heading', 'Forfaits')
@section('content')
<div class="mb-4"><a class="rounded-lg bg-electric px-4 py-2 text-sm text-white" href="{{ route('plans.create') }}">Nouveau forfait</a></div>
<div class="grid gap-3 sm:grid-cols-2">
    @forelse($plans as $plan)
        <article class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">{{ $plan->name }}</h2>
            <p class="text-sm text-slate-500">{{ $plan->durationLabel() }} · {{ $plan->unlimited_data ? 'Internet illimité' : 'Volume limité' }}</p>
            <p class="mt-2 text-xl font-semibold">{{ \App\Support\Money::format($plan->price, $plan->currency) }}</p>
            <p class="text-sm">{{ $plan->wifiZone->name ?? 'Toutes les zones' }} · {{ $plan->status === 'active' ? 'Actif' : 'Inactif' }}</p>
            <a class="mt-2 inline-block text-sm text-electric" href="{{ route('plans.edit', $plan) }}">Modifier</a>
        </article>
    @empty
        <p class="text-sm text-slate-500">Aucun forfait.</p>
    @endforelse
</div>
@endsection
