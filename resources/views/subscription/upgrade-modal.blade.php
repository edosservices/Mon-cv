@php
    $upgradePlans = $plans->filter(
        fn ($plan) => $catalog->raisesZoneLimit($subscription?->saasPlan, $plan)
    )->values();
@endphp
<div class="modal fade" id="zoneUpgradeModal" tabindex="-1" aria-labelledby="zoneUpgradeTitle" aria-describedby="zoneUpgradeText" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 d-block position-relative pt-4 pb-2">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Fermer"></button>
                <div class="offer-title-box text-center mx-auto">
                    <h2 class="modal-title offer-title mb-0" id="zoneUpgradeTitle">Passez aux avantages</h2>
                </div>
            </div>
            <div class="modal-body" id="zoneUpgradeText">
                <div class="offer-pitch text-center mx-auto">
                    <p class="fw-bold mb-0">Votre formule actuelle a atteint la limite de WiFi Zones. Passez à une formule supérieure pour créer plus de zones et profiter de nouveaux avantages.</p>
                </div>
                <h3 class="h6 text-center fw-bold mb-3">Les formules supérieures</h3>
                @include('subscription.partials.plan-cards', ['plans' => $upgradePlans])
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peut-être plus tard</button>
            </div>
        </div>
    </div>
</div>
