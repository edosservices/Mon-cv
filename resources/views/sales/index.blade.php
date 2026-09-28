@extends('layouts.app')
@section('heading', 'Ventes')
@section('content')
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
    <table class="w-full min-w-[640px] text-left text-sm">
        <thead class="text-slate-500"><tr><th class="p-3">Date</th><th>Client</th><th>Montant</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @foreach($sales as $sale)
            <tr class="border-t">
                <td class="p-3">{{ $sale->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                <td>{{ $sale->customer->phone ?? $sale->customer->name ?? 'Comptoir' }}</td>
                <td>{{ \App\Support\Money::format($sale->total_amount, $sale->currency) }}</td>
                <td>{{ $sale->status === 'paid' ? 'Payée' : 'En attente' }}</td>
                <td class="pr-3 text-right"><a class="text-electric" href="{{ route('sales.show', $sale) }}">Ouvrir</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $sales->links() }}</div>
@endsection
