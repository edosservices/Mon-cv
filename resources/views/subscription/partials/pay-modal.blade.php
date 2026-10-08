@php
    $openedPlan = request()->filled('plan')
        ? $plans->firstWhere('id', (int) request('plan'))
        : null;
@endphp
<div class="modal fade" id="paiement" tabindex="-1" aria-labelledby="payChoiceTitle" aria-hidden="true" @if($openedPlan) data-open-plan="{{ $openedPlan->id }}" data-open-name="{{ $openedPlan->name }}" data-open-price="{{ $catalog->priceLabel($openedPlan) }}" @endif>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('subscription.checkout') }}" id="paiement-form">
                @csrf
                <input type="hidden" name="saas_plan_id" value="{{ $openedPlan?->id }}">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h2 class="modal-title h5 fw-bold mb-1" id="payChoiceTitle">Choisissez votre moyen de paiement</h2>
                        <p class="text-muted small mb-0">Un clic enregistre le paiement. Il reste en attente jusqu’à confirmation.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="pay-choice-plan fw-bold mb-3" data-pay-label>{{ $openedPlan ? $openedPlan->name.' · '.$catalog->priceLabel($openedPlan) : 'Formule' }}</p>
                    <div class="pay-methods">
                        @foreach(config('limete.payment_providers') as $key => $label)
                            <button class="btn btn-primary pay-method" type="submit" name="provider" value="{{ $key }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <p class="text-muted small mb-0 mt-3">L’abonnement n’est pas modifié avant la confirmation réelle.</p>
                </div>
            </form>
        </div>
    </div>
</div>
@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = document.getElementById('paiement');
                if (!modal || modal.dataset.bound === '1' || !window.bootstrap || !window.bootstrap.Modal) {
                    return;
                }
                modal.dataset.bound = '1';
                var input = modal.querySelector('[name="saas_plan_id"]');
                var label = modal.querySelector('[data-pay-label]');

                var fill = function (plan, name, price) {
                    input.value = plan || '';
                    label.textContent = [name, price].filter(Boolean).join(' · ') || 'Formule';
                };

                var showPay = function () {
                    window.bootstrap.Modal.getOrCreateInstance(modal).show();
                };

                document.querySelectorAll('[data-pay-plan]').forEach(function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        fill(button.dataset.payPlan, button.dataset.payName, button.dataset.payPrice);
                        var upgrade = document.getElementById('zoneUpgradeModal');
                        if (upgrade && upgrade.classList.contains('show')) {
                            var handoff = function () {
                                upgrade.addEventListener('hidden.bs.modal', showPay, { once: true });
                                window.bootstrap.Modal.getOrCreateInstance(upgrade).hide();
                            };
                            var opened = window.bootstrap.Modal.getInstance(upgrade);
                            if (opened && opened._isTransitioning) {
                                upgrade.addEventListener('shown.bs.modal', handoff, { once: true });
                            } else {
                                handoff();
                            }
                            return;
                        }
                        showPay();
                    });
                });

                if (modal.dataset.openPlan && window.location.hash === '#paiement') {
                    fill(modal.dataset.openPlan, modal.dataset.openName, modal.dataset.openPrice);
                    showPay();
                }
            });
        </script>
    @endpush
@endonce
