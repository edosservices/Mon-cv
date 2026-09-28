@extends('layouts.app')
@section('heading', 'Ventes récentes')
@section('content')
<form method="GET" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
    <input type="hidden" name="zone" value="{{ $filters['zone_id'] }}">
    <label class="text-sm">Recherche
        <input class="mt-1 w-full rounded-xl border px-3 py-3" name="q" value="{{ $filters['q'] }}" placeholder="Client, ticket, référence">
    </label>
    <label class="text-sm">Forfait
        <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="plan">
            <option value="">Tous</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected($filters['plan_id'] === $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm">Paiement
        <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="payment">
            <option value="">Tous</option>
            @foreach(\App\Enums\PaymentStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($filters['payment'] === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-sm">MikroTik
        <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="sync">
            <option value="">Tous</option>
            <option value="synced" @selected($filters['sync'] === 'synced')>Synchronisé</option>
            <option value="unsynced" @selected($filters['sync'] === 'unsynced')>Non synchronisé</option>
            <option value="failed" @selected($filters['sync'] === 'failed')>Erreur</option>
        </select>
    </label>
    <label class="text-sm">Du
        <input class="mt-1 w-full rounded-xl border px-3 py-3" type="date" name="from" value="{{ request('from', $filters['from']->toDateString()) }}">
    </label>
    <label class="text-sm">Au
        <input class="mt-1 w-full rounded-xl border px-3 py-3" type="date" name="to" value="{{ request('to', $filters['to']->toDateString()) }}">
    </label>
    <input type="hidden" name="period" value="custom">
    <button class="rounded-xl bg-electric px-4 py-3 text-sm font-semibold text-white">Filtrer</button>
</form>
<div class="mt-3 flex flex-wrap gap-3 text-sm font-semibold">
    <a class="text-electric" href="{{ route('exports.sales', request()->query()) }}">Exporter CSV</a>
    <a class="text-electric" href="{{ route('exports.sales.pdf', request()->query()) }}">PDF</a>
    <a class="text-electric" href="{{ route('exports.tickets', request()->query()) }}">Tickets CSV</a>
</div>
@include('sales.cards')
@endsection
