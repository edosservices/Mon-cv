@extends('layouts.app')
@section('heading', 'Profils')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-600">Les profils viennent des forfaits LIMETE. Le routeur n’est pas écrasé automatiquement.</p>
    <a class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" href="{{ route('entrepreneur.profiles.create') }}">Ajouter un profil</a>
</div>
<div class="space-y-3">
    @forelse($plans as $plan)
        @php $hotspot = is_array($plan->hotspot) ? $plan->hotspot : []; @endphp
        <article class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">{{ $plan->mikrotik_profile ?: $plan->name }}</h2>
                    <p class="text-sm text-slate-600">{{ $plan->durationLabel() }} · {{ \App\Support\Money::shop($plan->price, $plan->currency) }}</p>
                    @if($plan->selling_price !== null)
                        <p class="text-sm text-slate-600">Prix de vente {{ \App\Support\Money::shop($plan->selling_price, $plan->selling_currency ?: $plan->currency) }}</p>
                    @endif
                    @if(filled($hotspot['rate_limit'] ?? null))
                        <p class="text-sm">Débit {{ $hotspot['rate_limit'] }}</p>
                    @endif
                    @if(($hotspot['lock_user'] ?? '') === 'Enable')
                        <p class="text-sm">Verrouillage Enable · One device</p>
                    @endif
                </div>
                <a class="min-h-12 rounded-xl border px-4 py-3 text-sm font-semibold" href="{{ route('entrepreneur.profiles.edit', $plan) }}">Modifier</a>
            </div>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-4 text-sm text-slate-600">Aucun profil. Ajoutez-en un à partir de la configuration réelle.</p>
    @endforelse
</div>
@endsection
