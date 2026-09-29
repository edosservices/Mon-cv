@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Prix des abonnements</h1>
<p class="mb-4 text-sm text-slate-600">Les prix ne sont pas figés dans le code. Laissez le champ vide tant que le tarif n’est pas décidé.</p>
<div class="space-y-4">
    @foreach($plans as $plan)
        <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="grid gap-2 rounded-2xl bg-white p-4 sm:grid-cols-3">
            @csrf @method('PATCH')
            <label class="text-sm sm:col-span-3">{{ $plan->code }}<input class="mt-1 w-full rounded-lg border px-3 py-2" name="name" value="{{ $plan->name }}"></label>
            <input class="rounded-lg border px-3 py-2" name="price" value="{{ $plan->price }}" placeholder="Prix">
            <input class="rounded-lg border px-3 py-2" name="currency" value="{{ $plan->currency }}">
            <input class="rounded-lg border px-3 py-2" name="interval_days" value="{{ $plan->interval_days }}">
            <input class="rounded-lg border px-3 py-2" name="max_zones" value="{{ $plan->max_zones }}" placeholder="Zones, vide = illimité">
            <input class="rounded-lg border px-3 py-2" name="max_mikrotiks" value="{{ $plan->max_mikrotiks }}" placeholder="MikroTik, vide = illimité">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Actif</label>
            <button class="rounded-lg bg-electric px-3 py-2 text-white sm:col-span-3">Enregistrer</button>
        </form>
    @endforeach
</div>
@endsection
