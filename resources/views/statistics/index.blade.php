@extends('layouts.app')
@section('heading', 'Statistiques')
@section('content')
<form class="mb-4 flex flex-wrap gap-2" method="GET">
    @foreach(['today' => 'Aujourd’hui', '7' => '7 jours', '30' => '30 jours', 'custom' => 'Personnalisé'] as $value => $label)
        <button name="range" value="{{ $value }}" class="rounded-lg border bg-white px-3 py-2 text-sm {{ $range === $value ? 'border-electric text-electric' : '' }}">{{ $label }}</button>
    @endforeach
    @if($range === 'custom')
        <input type="date" name="from" class="rounded-lg border px-3 py-2">
        <input type="date" name="to" class="rounded-lg border px-3 py-2">
        <button class="rounded-lg bg-electric px-3 py-2 text-sm text-white">Voir</button>
    @endif
</form>
<div class="grid gap-3 sm:grid-cols-3">
    <article class="rounded-2xl bg-white p-4"><p class="text-sm text-slate-500">Revenus</p><p class="text-2xl font-semibold">{{ \App\Support\Money::format($stats['revenue']) }}</p></article>
    <article class="rounded-2xl bg-white p-4"><p class="text-sm text-slate-500">Tickets</p><p class="text-2xl font-semibold">{{ $stats['tickets'] }}</p></article>
    <article class="rounded-2xl bg-white p-4"><p class="text-sm text-slate-500">Sessions</p><p class="text-2xl font-semibold">{{ $stats['sessions'] }}</p></article>
</div>
<section class="mt-5 rounded-2xl bg-white p-4">
    <h2 class="font-semibold">Ventes par jour</h2>
    @php $max = max(1, $stats['by_day']->max('total')); @endphp
    <ul class="mt-3 space-y-2">
        @foreach($stats['by_day'] as $row)
            <li>
                <div class="flex justify-between text-sm"><span>{{ $row->day }}</span><span>{{ \App\Support\Money::format($row->total) }}</span></div>
                <div class="mt-1 h-2 rounded bg-slate-100"><div class="h-2 rounded bg-electric" style="width: {{ max(4, ($row->total / $max) * 100) }}%"></div></div>
            </li>
        @endforeach
    </ul>
</section>
@if($advanced)
<section class="mt-5 rounded-2xl bg-white p-4">
    <h2 class="font-semibold">Ventes par forfait</h2>
    <ul class="mt-3 space-y-2 text-sm">
        @foreach($stats['by_plan'] as $row)
            <li class="flex justify-between"><span>{{ $row->name }}</span><span>{{ $row->total }} · {{ \App\Support\Money::format($row->amount) }}</span></li>
        @endforeach
    </ul>
</section>
@endif
@endsection
