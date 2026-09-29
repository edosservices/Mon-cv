@extends('layouts.app')
@section('heading', 'Abonnement')
@section('content')
<article class="rounded-2xl bg-white p-5 shadow-sm">
    <p class="text-sm text-slate-500">Plan actuel</p>
    <h2 class="text-2xl font-semibold">{{ $subscription->saasPlan->name ?? 'Aucun' }}</h2>
    <p class="mt-2">Prix : {{ \App\Support\Money::format($subscription->saasPlan->price ?? null, $subscription->saasPlan->currency ?? null) }}</p>
    <p>Début : {{ $subscription->starts_at?->timezone(config('app.timezone'))->format('d/m/Y') }}</p>
    <p>Expiration : {{ $subscription->ends_at?->timezone(config('app.timezone'))->format('d/m/Y') }}</p>
    <p>Statut : {{ $subscription->statusEnum()->label() }}</p>
</article>
<form method="POST" action="{{ route('subscription.checkout') }}" class="mt-5 space-y-3 rounded-2xl bg-white p-5 shadow-sm">
    @csrf
    <h2 class="font-semibold">Changer ou renouveler</h2>
    <label class="block text-sm">Plan
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="saas_plan_id">
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}">{{ $plan->name }} — {{ \App\Support\Money::format($plan->price, $plan->currency) }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">Moyen
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="provider">
            @foreach($providers as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">Référence de transaction<input class="mt-1 w-full rounded-lg border px-3 py-2" name="transaction_reference"></label>
    <p class="text-xs text-slate-500">Les clés des opérateurs restent dans le fichier .env du serveur. Sans clé, seul le paiement manuel est disponible.</p>
    <button class="rounded-xl bg-electric px-4 py-3 text-white">Enregistrer le paiement</button>
</form>
@endsection
