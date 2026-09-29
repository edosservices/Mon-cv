@extends('layouts.business')
@section('heading', 'Vente rapide')
@section('content')
<h2 class="h4">Vente rapide</h2>
@if($zones->isEmpty() || $plans->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p>Il faut une WiFi Zone et un forfait avant de vendre.</p>
            @if($zones->isEmpty())
                <a class="btn biz-btn" href="{{ route('wifi-zones.create') }}">Créer ma zone</a>
            @else
                <a class="btn biz-btn" href="{{ route('plans.create') }}">Créer un forfait</a>
            @endif
        </div>
    </div>
@else
    <form method="POST" action="{{ route('sales.quick.store') }}" data-loader class="card border-0 shadow-sm">
        @csrf
        <div class="card-body">
            <div class="row g-3">
            <div class="col-12 col-lg-7">
            <label class="form-label" for="wifi_zone_id">WiFi Zone</label>
            <select class="form-select" id="wifi_zone_id" name="wifi_zone_id" required>
                @foreach($zones as $zone)
                    <option value="{{ $zone->id }}" @selected((int) old('wifi_zone_id', $prefillZone) === (int) $zone->id)>{{ $zone->name }}</option>
                @endforeach
            </select>
            <label class="form-label mt-3" for="plan_id">Forfait</label>
            <div class="lm-picks mb-2">
                @foreach($plans as $plan)
                    <button type="button" class="lm-offer-pick" data-plan-pick="{{ $plan->id }}">
                        <strong>{{ $plan->name }}</strong>
                        <span>{{ \App\Support\Money::format($plan->price, $plan->currency) }}</span>
                        <small>{{ $plan->validityLabel() }}@if($plan->mikrotik_profile) · {{ $plan->mikrotik_profile }}@endif</small>
                    </button>
                @endforeach
            </div>
            <select class="form-select" id="plan_id" name="plan_id" required>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected((int) old('plan_id', $prefillPlan) === (int) $plan->id)>{{ $plan->name }} · {{ \App\Support\Money::format($plan->price, $plan->currency) }}</option>
                @endforeach
            </select>
            <label class="form-label mt-3" for="quantity">Quantité</label>
            <input class="form-control" id="quantity" type="number" name="quantity" min="1" max="100" value="{{ old('quantity', 1) }}" required>
            <h3 class="h6 mt-4">Client</h3>
            <p class="text-secondary small">Facultatif. Le client n’a pas besoin d’un compte.</p>
            <label class="form-label" for="phone">Téléphone</label>
            <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30">
            <label class="form-label mt-3" for="customer-name">Nom</label>
            <input class="form-control" id="customer-name" name="name" value="{{ old('name') }}" maxlength="120">
            </div>
            <div class="col-12 col-lg-5">
            <div class="lm-total" data-sale-total>
                <p>Total</p>
                <strong data-sale-total-value>Selon le forfait</strong>
                <p class="mt-2">Quantité × prix du forfait choisi.</p>
            </div>
            <button class="btn biz-btn w-100 mt-3 py-3">Créer / vendre</button>
            </div>
            </div>
        </div>
    </form>
@endif
@endsection
