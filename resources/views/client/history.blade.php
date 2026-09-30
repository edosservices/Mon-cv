@extends('layouts.client')
@section('heading', 'Historique')
@section('content')
<h1 class="h4">Historique</h1>
@forelse($sales as $sale)
    <p class="mb-1">{{ $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $sale->items->first()?->plan?->name ?? 'Forfait' }} · {{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</p>
@empty
    <p class="text-secondary">Aucun achat pour ce numéro.</p>
@endforelse
<h2 class="h6 mt-3">Tickets</h2>
@forelse($tickets as $ticket)
    @include('client.ticket-card', ['reveal' => true])
@empty
    <p class="text-secondary">Aucun ticket.</p>
@endforelse
@endsection
