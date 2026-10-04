@php
    $salePlans = $plans->filter(function ($plan) use ($quickZone) {
        return $plan->status === 'active'
            && ($plan->wifi_zone_id === null || (int) $plan->wifi_zone_id === (int) $quickZone->id);
    })->values();
    $picked = (int) old('plan_id', $prefill['plan_id'] ?: ($salePlans->first()->id ?? 0));
    $saleQty = max(1, (int) old('count', $prefill['count'] ?: min(10, max(1, $limit))));
    if ($limit > 0) {
        $saleQty = min($saleQty, $limit);
    }
    $pickedPlan = $salePlans->firstWhere('id', $picked) ?? $salePlans->first();
    $unitLabel = $pickedPlan ? \App\Support\Money::shop($pickedPlan->price, $pickedPlan->currency) : '—';
    $totalLabel = $pickedPlan && $pickedPlan->price !== null
        ? \App\Support\Money::shop(((float) $pickedPlan->price) * $saleQty, $pickedPlan->currency)
        : $unitLabel;
@endphp
<section class="tg-card" data-sale>
    <div class="tg-card-head">
        <span class="tg-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 8h16v10H4z"/><path d="M8 8V6h8v2"/></svg></span>
        <div>
            <h2>Choisir un forfait</h2>
            <p>Le prix et la durée viennent du forfait enregistré pour cette WiFi Zone.</p>
        </div>
    </div>
    @if($salePlans->isEmpty())
        <p class="tg-note">Aucun forfait actif pour cette WiFi Zone.</p>
        <p class="tg-hint"><a href="{{ route('plans.create', ['wifi_zone_id' => $quickZone->id]) }}">Créez ou activez un forfait dans Forfaits.</a></p>
    @else
        <form method="POST" action="{{ route('vouchers.store') }}" data-sale-form>
            @csrf
            <input type="hidden" name="wifi_zone_id" value="{{ $quickZone->id }}">
            <input type="hidden" name="template" value="moderne">
            <input type="hidden" name="per_page" value="{{ \App\Services\TicketSheet::ECONOMICAL }}">
            <div class="tg-offers">
                @foreach($salePlans as $plan)
                    @php
                        $rate = is_array($plan->hotspot) ? (string) ($plan->hotspot['rate_limit'] ?? '') : '';
                        $label = \App\Support\Money::shop($plan->price, $plan->currency);
                    @endphp
                    <label class="tg-offer {{ $picked === (int) $plan->id ? 'is-selected' : '' }}">
                        <input type="radio" name="plan_id" value="{{ $plan->id }}" data-sale-plan data-unit-price="{{ $plan->price !== null ? $plan->price : '' }}" data-price-label="{{ $label }}" @checked($picked === (int) $plan->id) @disabled($limit < 1) required>
                        <strong>{{ $plan->name }}</strong>
                        <span>{{ $label }}</span>
                        <small>{{ $plan->validityLabel() }}@if($rate !== '') · {{ $rate }}@endif</small>
                    </label>
                @endforeach
            </div>
            <div class="tg-qty">
                <p class="text-sm font-semibold">Quantité</p>
                <div class="tg-stepper">
                    <button type="button" data-sale-minus aria-label="Diminuer">−</button>
                    <input type="number" name="count" min="1" max="{{ max(1, $limit) }}" value="{{ $saleQty }}" required inputmode="numeric" data-sale-qty @disabled($limit < 1)>
                    <button type="button" data-sale-plus aria-label="Augmenter">+</button>
                </div>
                <div class="tg-picks">
                    @foreach([1, 5, 10, 20, 50, 100] as $preset)
                        @if($preset <= max(1, $limit))
                            <button type="button" data-sale-preset="{{ $preset }}">{{ $preset }}</button>
                        @endif
                    @endforeach
                </div>
            </div>
            <p class="tg-math"><span data-sale-math>{{ $saleQty }}</span> × <span data-sale-unit>{{ $unitLabel }}</span></p>
            <div class="tg-total">
                <span>Total</span>
                <strong data-sale-total>{{ $totalLabel }}</strong>
            </div>
            <button class="tg-cta" type="submit" data-sale-go @disabled($limit < 1)>
                <span class="tg-cta-idle">GÉNÉRER {{ $saleQty }} TICKETS</span>
                <span class="tg-cta-busy">Génération...</span>
            </button>
        </form>
    @endif
</section>
