import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('paiement-form');
    if (!form) {
        return;
    }

    form.addEventListener('submit', (event) => {
        const button = event.submitter;
        if (!button) {
            return;
        }

        window.setTimeout(() => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>');
        }, 0);
    });
});
