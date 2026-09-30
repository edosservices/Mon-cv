@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Rapports</h1>
<div class="lm-grid sm:grid-cols-2 xl:grid-cols-3">
    <x-ui.kpi label="Entrepreneurs">{{ $overview['entrepreneurs'] }}</x-ui.kpi>
    <x-ui.kpi label="Clients">{{ $overview['clients'] }}</x-ui.kpi>
    <x-ui.kpi label="WiFi Zones">{{ $overview['zones'] }}</x-ui.kpi>
    <x-ui.kpi label="Routeurs">{{ $overview['routers'] }}</x-ui.kpi>
    <x-ui.kpi label="Routeurs connectés">{{ $overview['routers_online'] }}</x-ui.kpi>
    <x-ui.kpi label="Tickets">{{ $overview['tickets'] }}</x-ui.kpi>
    <x-ui.kpi label="Ventes">{{ $overview['sales'] }}</x-ui.kpi>
    @foreach($overview['revenue'] as $currency)
        <x-ui.kpi :label="'Revenus '.$currency['currency']">{{ $currency['label'] }}</x-ui.kpi>
    @endforeach
</div>
@endsection
