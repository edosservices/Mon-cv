import './limete-ui.js';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-wait]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (!button || button.dataset.loading === '1') {
                return;
            }
            button.dataset.loading = '1';
            button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>');
        });
    });
});
