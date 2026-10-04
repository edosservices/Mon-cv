@php
    $offerCount = $plans->count();
    $offerColumn = match (true) {
        $offerCount <= 1 => 'col-12',
        $offerCount === 2 => 'col-12 col-md-6',
        $offerCount === 3 => 'col-12 col-md-6 col-lg-4',
        default => 'col-12 col-md-6 col-lg-4 col-xl-3',
    };
@endphp
<div class="row g-3 g-lg-4">
    @foreach($plans as $plan)
        @php
            $isCurrent = $subscription && (int) $subscription->saas_plan_id === (int) $plan->id;
            $canUpgrade = $catalog->raisesZoneLimit($subscription?->saasPlan, $plan);
            $period = $catalog->periodLabel($plan);
        @endphp
        <div class="{{ $offerColumn }}">
            <article class="card plan-offer h-100 d-flex flex-column shadow-sm border rounded-4 {{ $isCurrent ? 'border-primary shadow' : '' }}" @if($isCurrent) aria-current="true" @endif>
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <h3 class="h5 fw-bold mb-0 text-break">{{ $plan->name }}</h3>
                        @if($isCurrent)
                            <span class="badge text-bg-primary">ACTUEL</span>
                        @endif
                    </div>
                    @if($isCurrent)
                        <p class="small fw-semibold text-primary mb-0 mt-2">Votre plan actuel</p>
                    @elseif($period)
                        <p class="small text-muted mb-0 mt-2">{{ $period }}</p>
                    @endif
                </div>
                <div class="card-body d-flex flex-column pt-3">
                    <p class="h4 fw-bold mb-1 text-break">{{ $catalog->priceLabel($plan) }}</p>
                    @if($plan->currency)
                        <p class="small text-muted mb-3">Devise : {{ $plan->currency }}</p>
                    @endif
                    <ul class="list-unstyled mb-0">
                        @foreach($catalog->advantages($plan) as $line)
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <span class="text-success fw-bold" aria-hidden="true">✓</span>
                                <span>{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="card-footer bg-transparent border-0 mt-auto pt-0 pb-3">
                    @if($isCurrent)
                        <button type="button" class="btn btn-outline-secondary w-100" disabled aria-current="true">Abonnement actuel</button>
                    @elseif($canUpgrade)
                        <a class="btn btn-primary btn-lg w-100" href="{{ $catalog->checkoutUrl($plan) }}">Passer à cette formule</a>
                    @else
                        <a class="btn btn-outline-primary w-100" href="{{ $catalog->checkoutUrl($plan) }}">Choisir cette formule</a>
                    @endif
                </div>
            </article>
        </div>
    @endforeach
</div>
