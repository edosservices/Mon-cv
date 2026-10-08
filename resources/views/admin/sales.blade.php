@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Ventes</h1>
<div class="space-y-3">
    @forelse($sales as $sale)
        <article class="rounded-2xl bg-white p-4">
            <h2 class="font-semibold">Vente #{{ $sale->id }}</h2>
            <p class="text-sm text-slate-500">{{ $sale->tenant->name ?? 'Entreprise' }} · {{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }} · {{ $sale->status }}</p>
            @if($sale->beneficiary_phone)
                <p class="text-sm">Bénéficiaire : {{ $sale->beneficiary_phone }}@if($sale->purchase_for === 'other' && $sale->payer_phone) · Paiement : {{ $sale->payer_phone }}@endif</p>
            @endif
        </article>
    @empty
        <p class="rounded-2xl bg-white p-4 text-sm text-slate-500">Aucune vente.</p>
    @endforelse
</div>
<div class="mt-4">{{ $sales->links() }}</div>
@endsection
