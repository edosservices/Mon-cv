document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('paiement');
    if (!form) {
        return;
    }

    form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');
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
