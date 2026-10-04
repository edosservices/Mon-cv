<div class="offer-shell">
    <div class="offer-grid" data-offers="{{ $plans->count() }}">
        @foreach($plans as $plan)
            @php
                $isCurrent = $subscription && (int) $subscription->saas_plan_id === (int) $plan->id;
                $canUpgrade = $catalog->raisesZoneLimit($subscription?->saasPlan, $plan);
                $period = $catalog->periodLabel($plan);
                $tones = ['starter' => 0, 'business' => 1, 'pro' => 2];
                $tone = $tones[$plan->code] ?? ($loop->index % 4);
            @endphp
            <article class="card offer-card h-100 rounded-4 {{ $isCurrent ? 'shadow' : 'shadow-sm' }}" @if($isCurrent) aria-current="true" @endif>
                <div class="offer-band offer-tone-{{ $tone }}">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="min-w-0">
                            @if($period)
                                <p class="offer-kicker">{{ $period }}</p>
                            @endif
                            <h3 class="offer-name text-break">{{ $plan->name }}</h3>
                        </div>
                        @if($isCurrent)
                            <span class="badge rounded-pill offer-badge">ACTUEL</span>
                        @endif
                    </div>
                    @if($isCurrent)
                        <p class="offer-kicker mt-2 mb-0">Votre plan actuel</p>
                    @endif
                    <p class="offer-price text-break">{{ $catalog->priceLabel($plan) }}</p>
                </div>
                <div class="card-body">
                    @if($plan->currency)
                        <p class="small text-muted mb-3">Devise : {{ $plan->currency }}</p>
                    @endif
                    <ul class="list-unstyled mb-3">
                        @foreach($catalog->advantages($plan) as $line)
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <span class="offer-check" aria-hidden="true">✓</span>
                                <span>{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-auto">
                        @if($isCurrent)
                            <button type="button" class="btn btn-outline-secondary w-100" disabled aria-current="true">Abonnement actuel</button>
                        @elseif($canUpgrade)
                            <a class="btn btn-primary btn-lg w-100" href="{{ $catalog->checkoutUrl($plan) }}" data-pay-plan="{{ $plan->id }}" data-pay-name="{{ $plan->name }}" data-pay-price="{{ $catalog->priceLabel($plan) }}">Passer aux avantages</a>
                        @else
                            <a class="btn btn-outline-primary w-100" href="{{ $catalog->checkoutUrl($plan) }}" data-pay-plan="{{ $plan->id }}" data-pay-name="{{ $plan->name }}" data-pay-price="{{ $catalog->priceLabel($plan) }}">Choisir cette formule</a>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</div>
