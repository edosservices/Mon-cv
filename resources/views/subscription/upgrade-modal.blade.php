@php
    $blockedPlan = $subscription?->saasPlan?->name;
    $blockedTitle = $blockedPlan
        ? 'Votre abonnement '.$blockedPlan.' a atteint sa limite de WiFi Zones.'
        : 'Votre abonnement actuel a atteint sa limite de WiFi Zones.';
@endphp
<div class="modal fade" id="zoneUpgradeModal" tabindex="-1" aria-labelledby="zoneUpgradeTitle" aria-describedby="zoneUpgradeText" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 d-block position-relative pt-4 pb-2">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Fermer"></button>
                <div class="offer-title-box text-center mx-auto">
                    <h2 class="modal-title offer-title mb-2" id="zoneUpgradeTitle">{{ $blockedTitle }}</h2>
                    <p class="mb-0 fw-bold">Votre abonnement actuel a atteint sa limite de WiFi Zones.</p>
                </div>
            </div>
            <div class="modal-body" id="zoneUpgradeText">
                <div class="offer-pitch text-center mx-auto">
                    <p class="fw-bold mb-2">Passez à une formule supérieure pour débloquer davantage de zones et profiter de nouvelles fonctionnalités.</p>
                    <p class="fw-bold mb-1">Pour créer davantage de zones et développer votre activité, passez à une formule supérieure.</p>
                    <p class="fw-bold mb-0">Choisissez la formule adaptée à votre activité et débloquez davantage de fonctionnalités.</p>
                </div>
                <h3 class="h6 text-center fw-bold mb-2">Formules disponibles</h3>
                @include('subscription.partials.plan-cards')
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peut-être plus tard</button>
            </div>
        </div>
    </div>
</div>
