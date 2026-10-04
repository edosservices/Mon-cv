@php
    $blockedPlan = $subscription?->saasPlan?->name;
    $blockedTitle = $blockedPlan
        ? 'Votre abonnement '.$blockedPlan.' a atteint sa limite de WiFi Zones.'
        : 'Votre abonnement actuel a atteint sa limite de WiFi Zones.';
@endphp
<div class="modal fade" id="zoneUpgradeModal" tabindex="-1" aria-labelledby="zoneUpgradeTitle" aria-describedby="zoneUpgradeText" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 align-items-start pb-0">
                <div class="d-flex align-items-start gap-3 pe-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary flex-shrink-0" style="width:3rem;height:3rem" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M7 11V8a5 5 0 0 1 10 0v3"/>
                            <rect x="5" y="11" width="14" height="10" rx="2"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="modal-title h5 fw-bold mb-1" id="zoneUpgradeTitle">{{ $blockedTitle }}</h2>
                        <p class="text-muted small mb-0">Votre abonnement actuel a atteint sa limite de WiFi Zones.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="zoneUpgradeText">
                <div class="offer-intro mb-3">
                    <p class="mb-1">Pour créer davantage de zones et développer votre activité, passez à une formule supérieure.</p>
                    <p class="mb-1">Passez à une formule supérieure pour débloquer davantage de zones et profiter de nouvelles fonctionnalités.</p>
                    <p class="text-muted mb-0">Choisissez la formule adaptée à votre activité et débloquez davantage de fonctionnalités.</p>
                </div>
                <h3 class="h6 text-center fw-bold mb-3">Formules disponibles</h3>
                @include('subscription.partials.plan-cards')
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Peut-être plus tard</button>
            </div>
        </div>
    </div>
</div>
