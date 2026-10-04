@extends('layouts.app')
@section('heading', 'Abonnement')
@push('head')
    @vite(['resources/css/subscription.css', 'resources/js/subscription.js'])
@endpush
@section('content')
@php
    $timezone = config('app.timezone');
    $plan = $subscription?->saasPlan;
    $starts = $subscription?->starts_at?->timezone($timezone);
    $ends = $subscription?->ends_at?->timezone($timezone);
@endphp
<div class="sub-page container-fluid px-0">
    @if($expired)
        <section class="card border-0 shadow-sm rounded-4 mb-4 border-start border-warning border-4" role="status">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-2">Votre abonnement a expiré.</h2>
                <p class="text-muted mb-3">Renouvelez votre abonnement pour continuer à utiliser toutes les fonctionnalités.</p>
                @if($plan)
                    <button type="button" class="btn btn-primary btn-lg" data-pay-plan="{{ $plan->id }}" data-pay-name="{{ $plan->name }}" data-pay-price="{{ $catalog->priceLabel($plan) }}">Renouveler mon abonnement</button>
                @endif
            </div>
        </section>
    @endif

    <section class="mb-4" aria-labelledby="current-plan-title">
        <h2 class="h4 fw-bold mb-3" id="current-plan-title">Votre abonnement</h2>
        <article class="card border-0 shadow rounded-4">
            <div class="card-body p-4">
                @if($subscription && $plan)
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <p class="text-muted small mb-1">Plan actuel</p>
                            <h3 class="h3 fw-bold mb-1">{{ $plan->name }}</h3>
                            <p class="h4 fw-semibold mb-0">{{ $catalog->priceLabel($plan) }}</p>
                            @if($catalog->periodLabel($plan))
                                <p class="text-muted small mb-0 mt-1">{{ $catalog->periodLabel($plan) }}</p>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @if($subscription->status === \App\Enums\SubscriptionStatus::Trial->value && ! $expired)
                                <span class="badge rounded-pill text-bg-info">Période d’essai</span>
                            @endif
                            <span class="badge rounded-pill {{ $expired ? 'text-bg-warning' : 'text-bg-primary' }}">{{ $expired ? 'Expiré' : $subscription->statusEnum()->label() }}</span>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <p class="text-muted small mb-1">Devise</p>
                            <p class="fw-semibold mb-0">{{ $plan->currency ?: '—' }}</p>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <p class="text-muted small mb-1">Début</p>
                            <p class="fw-semibold mb-0">{{ $starts?->format('d/m/Y') ?: '—' }}</p>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <p class="text-muted small mb-1">Expiration</p>
                            <p class="fw-semibold mb-0">{{ $ends?->format('d/m/Y') ?: '—' }}</p>
                            @if($ends && ! $expired)
                                <p class="small text-muted mb-0">Valable jusqu’au {{ $ends->format('d/m/Y') }}</p>
                                @if($daysRemaining === 0)
                                    <p class="small mb-0">Expire aujourd’hui</p>
                                @elseif($daysRemaining !== null && $daysRemaining > 0)
                                    <p class="small mb-0">Expire dans {{ $daysRemaining }} {{ $daysRemaining > 1 ? 'jours' : 'jour' }}</p>
                                @endif
                            @endif
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <p class="text-muted small mb-1">Statut</p>
                            <p class="fw-semibold mb-0">{{ $expired ? 'Expiré' : $subscription->statusEnum()->label() }}</p>
                        </div>
                    </div>
                    @if($advantages = $catalog->advantages($plan))
                        <ul class="list-unstyled d-flex flex-wrap gap-2 mt-4 mb-0">
                            @foreach($advantages as $line)
                                <li><span class="badge rounded-pill text-bg-light border">{{ $line }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="mb-0">Aucun abonnement en cours.</p>
                @endif
            </div>
        </article>
    </section>

    <section class="mb-4" aria-labelledby="usage-title">
        <h2 class="h4 fw-bold mb-3" id="usage-title">Votre utilisation</h2>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="row g-4">
                    @foreach(['zones', 'mikrotiks'] as $meterKey)
                        @php $meter = $usage[$meterKey]; @endphp
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <span class="fw-semibold">{{ $meter['label'] }}</span>
                                <span class="text-muted">
                                    @if($meter['max'] !== null)
                                        {{ $meter['count'] }} / {{ $meter['max'] }}
                                    @else
                                        {{ $meter['count'] }} · sans plafond
                                    @endif
                                </span>
                            </div>
                            @if($meter['percent'] !== null)
                                <div class="progress" role="progressbar" aria-label="{{ $meter['label'] }}" aria-valuenow="{{ $meter['count'] }}" aria-valuemin="0" aria-valuemax="{{ $meter['max'] }}">
                                    <div class="progress-bar" style="width: {{ $meter['percent'] }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <div class="col-12 col-sm-6">
                        <p class="text-muted small mb-1">Tickets</p>
                        <p class="h5 fw-bold mb-0">{{ $usage['tickets']['count'] }}</p>
                    </div>
                    <div class="col-12 col-sm-6">
                        <p class="text-muted small mb-1">Clients</p>
                        <p class="h5 fw-bold mb-0">{{ $usage['clients']['count'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-4" aria-labelledby="offers-title">
        <div class="text-center mb-4">
            <h2 class="h4 fw-bold mb-2" id="offers-title">Passez aux avantages</h2>
            <p class="text-muted mb-0">Votre formule actuelle est affichée à côté des formules supérieures. Choisissez celle qui vous donne plus de zones et plus d’avantages.</p>
        </div>
        @include('subscription.partials.plan-cards')
    </section>
</div>
@include('subscription.partials.pay-modal')
@endsection
