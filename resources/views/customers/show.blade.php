@extends('layouts.business')
@section('heading', $customer->name ?: 'Client')
@section('content')
<article class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5">{{ $customer->name ?: 'Client' }}</h2>
        <p class="mb-1">{{ $customer->phone ?: 'Téléphone non renseigné' }}</p>
        <p class="mb-0">{{ $customer->email }}</p>
    </div>
</article>
<section class="card border-0 shadow-sm mt-3" id="tickets">
    <div class="card-body">
        <h2 class="h5">Tickets</h2>
        @if($customer->vouchers->isEmpty())
            <p class="mb-0 text-secondary">Aucun ticket.</p>
        @else
            <ul class="list-group list-group-flush">
                @foreach($customer->vouchers as $voucher)
                    <li class="list-group-item px-0">{{ $voucher->username }} · {{ $voucher->plan->name ?? '' }} · {{ $voucher->statusLabel() }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
@endsection
