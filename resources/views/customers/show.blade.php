@extends('layouts.app')
@section('heading', $customer->name ?: 'Client')
@section('content')
<article class="rounded-2xl bg-white p-5 shadow-sm">
    <p>{{ $customer->phone }}</p>
    <p>{{ $customer->email }}</p>
    <h2 class="mt-4 font-semibold">Tickets</h2>
    <ul class="mt-2 space-y-1 text-sm">
        @foreach($customer->vouchers as $voucher)
            <li>{{ $voucher->username }} · {{ $voucher->plan->name ?? '' }} · {{ $voucher->status }}</li>
        @endforeach
    </ul>
</article>
@endsection
