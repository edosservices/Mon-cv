@extends('layouts.app')
@section('heading', 'Clients')
@section('content')
<form class="mb-4" method="GET"><input class="w-full rounded-lg border px-3 py-2" name="q" value="{{ request('q') }}" placeholder="Nom ou téléphone"></form>
<div class="space-y-2">
    @foreach($customers as $customer)
        <a class="block rounded-2xl bg-white p-4 shadow-sm" href="{{ route('customers.show', $customer) }}">
            <strong>{{ $customer->name ?: 'Client' }}</strong>
            <span class="text-sm text-slate-500">{{ $customer->phone }}</span>
        </a>
    @endforeach
</div>
<div class="mt-4">{{ $customers->links() }}</div>
@endsection
