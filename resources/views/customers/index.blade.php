@extends('layouts.business')
@section('heading', 'Clients')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h4 mb-0">Clients</h2>
</div>
<form class="mb-3" method="GET">
    <label class="form-label" for="q">Recherche</label>
    <input class="form-control" id="q" name="q" value="{{ request('q') }}" placeholder="Nom ou téléphone">
</form>
@if($customers->isEmpty())
    <div class="card border-0 shadow-sm"><div class="card-body">Aucun client pour le moment.</div></div>
@else
    <div class="table-responsive card border-0 shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Téléphone</th>
                    <th>Tickets</th>
                    <th>Dernier achat</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($customers as $customer)
                    <tr>
                        <td>{{ $customer->name ?: 'Client' }}</td>
                        <td>{{ $customer->phone ?: '—' }}</td>
                        <td>{{ $customer->vouchers_count }}</td>
                        <td>{{ $customer->sales_max_created_at ? \Illuminate\Support\Carbon::parse($customer->sales_max_created_at)->timezone(config('app.timezone'))->format('d/m/Y') : '—' }}</td>
                        <td><span class="badge {{ $customer->active_vouchers_count > 0 ? 'text-bg-success' : 'text-bg-light' }}">{{ $customer->active_vouchers_count > 0 ? 'Actif' : 'Client' }}</span></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('customers.show', $customer) }}">Voir</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('customers.show', $customer) }}#tickets">Tickets</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $customers->links() }}</div>
@endif
@endsection
