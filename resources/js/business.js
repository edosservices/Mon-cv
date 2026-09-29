import * as bootstrap from 'bootstrap';
import './limete-ui.js';

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

    document.querySelectorAll('[data-business-preview]').forEach((preview) => {
        const read = (name) => document.querySelector(`[name="${name}"]`)?.value || '';
        const paint = () => {
            const name = read('name');
            const set = (selector, value) => {
                const node = preview.querySelector(selector);
                if (node) {
                    node.textContent = value;
                }
            };
            set('[data-preview-name]', name);
            set('[data-preview-slogan]', read('slogan'));
            set('[data-preview-phone]', read('phone'));
            set('[data-preview-city]', `${read('city')} ${read('country')}`.trim());
            set('[data-preview-mark]', (name || 'L').slice(0, 1));
            preview.style.setProperty('--preview', read('primary_color') || '#1463f3');
            preview.style.setProperty('--preview-2', read('secondary_color') || '#071428');
            preview.style.setProperty('--preview-btn', read('button_color') || read('primary_color') || '#1463f3');
        };
        ['name', 'slogan', 'phone', 'city', 'country', 'primary_color', 'secondary_color', 'button_color'].forEach((field) => {
            document.querySelector(`[name="${field}"]`)?.addEventListener('input', paint);
        });
    });

    const saleTotal = document.querySelector('[data-sale-total-value]');
    const planSelect = document.querySelector('#plan_id');
    const quantity = document.querySelector('#quantity');
    const paintSale = () => {
        if (!saleTotal || !planSelect) {
            return;
        }
        const label = planSelect.options[planSelect.selectedIndex]?.textContent || 'Selon le forfait';
        const count = quantity?.value || '1';
        saleTotal.textContent = `${count} × ${label}`;
    };
    planSelect?.addEventListener('change', paintSale);
    quantity?.addEventListener('input', paintSale);
    paintSale();

    const chart = document.getElementById('report-chart');
    if (chart) {
        const rows = JSON.parse(chart.getAttribute('data-chart') || '[]');
        const context = chart.getContext('2d');
        const width = chart.parentElement?.clientWidth || 320;
        chart.width = width;
        chart.height = 160;
        context.clearRect(0, 0, chart.width, chart.height);
        const max = Math.max(1, ...rows.map((row) => Number(row.value) || 0));
        const gap = 12;
        const barWidth = rows.length ? (chart.width - gap * (rows.length + 1)) / rows.length : 0;
        rows.forEach((row, index) => {
            const value = Number(row.value) || 0;
            const height = (value / max) * 110;
            const x = gap + index * (barWidth + gap);
            const y = 130 - height;
            context.fillStyle = '#1463f3';
            context.beginPath();
            context.roundRect(x, y, Math.max(barWidth, 4), height, 8);
            context.fill();
        });
    }

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
