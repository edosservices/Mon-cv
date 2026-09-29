import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        new bootstrap.Tooltip(el);
    });

    document.querySelectorAll('.js-copy').forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.getAttribute('data-url') || '';
            const done = () => { button.textContent = 'Lien copié'; };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done).catch(() => window.prompt('Copiez le lien', url));
                return;
            }
            window.prompt('Copiez le lien', url);
        });
    });

    document.querySelectorAll('[data-logo-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            const img = document.querySelector(input.getAttribute('data-logo-input'));
            if (!file || !img) {
                return;
            }
            const reader = new FileReader();
            reader.onload = () => {
                img.src = reader.result;
                img.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });
    });

    document.querySelectorAll('[data-geo]').forEach((button) => {
        button.addEventListener('click', () => {
            const lat = document.querySelector(`[name="${button.getAttribute('data-lat')}"]`);
            const lng = document.querySelector(`[name="${button.getAttribute('data-lng')}"]`);
            const note = button.parentElement?.querySelector('[data-geo-note]');
            if (!lat || !lng) {
                return;
            }
            if (!navigator.geolocation) {
                if (note) {
                    note.textContent = 'Ce navigateur ne propose pas la localisation.';
                }
                return;
            }
            button.disabled = true;
            navigator.geolocation.getCurrentPosition((position) => {
                lat.value = position.coords.latitude.toFixed(6);
                lng.value = position.coords.longitude.toFixed(6);
                button.disabled = false;
                if (note) {
                    note.textContent = 'Position enregistrée dans le formulaire. Validez pour la conserver.';
                }
            }, () => {
                button.disabled = false;
                if (note) {
                    note.textContent = 'Localisation refusée ou indisponible. Saisissez la position à la main.';
                }
            }, { enableHighAccuracy: false, timeout: 10000 });
        });
    });

    const preview = document.querySelector('[data-plan-preview]');
    if (preview) {
        const name = document.querySelector('[name="name"]');
        const price = document.querySelector('[name="price"]');
        const value = document.querySelector('[name="duration_value"]');
        const unit = document.querySelector('[name="duration_unit"]');
        const unlimited = document.querySelector('[name="unlimited_data"]');
        const units = { minutes: ['MINUTE', 'MINUTES'], hours: ['HEURE', 'HEURES'], days: ['JOUR', 'JOURS'] };
        const render = () => {
            const amount = Number(value?.value || 0);
            const pair = units[unit?.value] || units.hours;
            preview.querySelector('[data-preview-duration]').textContent = `${amount} ${amount > 1 ? pair[1] : pair[0]}`;
            const raw = String(price?.value || '').replace(',', '.');
            const number = Number(raw);
            preview.querySelector('[data-preview-price]').textContent = Number.isFinite(number) && raw !== ''
                ? `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(number)} CDF`
                : 'Prix à définir';
            preview.querySelector('[data-preview-data]').textContent = unlimited?.checked ? 'Internet illimité' : (name?.value || 'Données selon description');
        };
        [name, price, value, unit, unlimited].forEach((el) => {
            el?.addEventListener('input', render);
            el?.addEventListener('change', render);
        });
    }

    document.querySelectorAll('form[data-loader]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (!button || button.dataset.loading === '1') {
                return;
            }
            button.dataset.loading = '1';
            button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>');
        });
    });

    const modal = document.getElementById('confirmDelete');
    if (modal) {
        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const form = document.getElementById('confirmDeleteForm');
            const title = document.getElementById('confirmDeleteLabel');
            if (trigger && form) {
                form.action = trigger.getAttribute('data-delete-action') || form.action;
            }
            if (trigger && title && trigger.getAttribute('data-delete-title')) {
                title.textContent = trigger.getAttribute('data-delete-title');
            }
        });
    }
});
