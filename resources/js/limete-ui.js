const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function ready(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

ready(() => {
    const frame = document.querySelector('.lm-frame');
    const collapse = document.querySelector('[data-lm-collapse]');
    if (frame && collapse) {
        const apply = (collapsed) => {
            frame.classList.toggle('is-collapsed', collapsed);
            collapse.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            collapse.textContent = collapsed ? 'Ouvrir' : 'Réduire';
        };
        apply(window.localStorage.getItem('lm-sidebar') === 'collapsed');
        collapse.addEventListener('click', () => {
            const next = !frame.classList.contains('is-collapsed');
            apply(next);
            window.localStorage.setItem('lm-sidebar', next ? 'collapsed' : 'open');
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.parentElement?.querySelector('input');
            if (!input) {
                return;
            }
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Masquer' : 'Afficher';
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    document.querySelectorAll('form[data-steps]').forEach((form) => {
        const steps = [...form.querySelectorAll('[data-step]')];
        const dots = [...form.querySelectorAll('[data-step-dot]')];
        let current = 1;
        const show = (index) => {
            current = index;
            steps.forEach((step) => {
                step.hidden = Number(step.getAttribute('data-step')) !== index;
            });
            dots.forEach((dot) => {
                const value = Number(dot.getAttribute('data-step-dot'));
                dot.classList.toggle('is-current', value === index);
                dot.classList.toggle('is-done', value < index);
            });
            if (index === steps.length) {
                form.querySelectorAll('[data-step-summary]').forEach((node) => {
                    const name = node.getAttribute('data-step-summary');
                    const field = form.querySelector(`[name="${name}"]`);
                    node.textContent = field?.value || '—';
                });
            }
        };
        form.querySelectorAll('[data-step-next]').forEach((button) => {
            button.addEventListener('click', () => {
                const section = form.querySelector(`[data-step="${current}"]`);
                const fields = [...(section?.querySelectorAll('input, select, textarea') || [])];
                const valid = fields.every((field) => field.reportValidity());
                if (valid) {
                    show(Math.min(steps.length, current + 1));
                }
            });
        });
        form.querySelectorAll('[data-step-prev]').forEach((button) => {
            button.addEventListener('click', () => show(Math.max(1, current - 1)));
        });
        show(1);
    });

    document.querySelectorAll('[data-count]').forEach((node) => {
        const target = Number(node.getAttribute('data-count'));
        if (!Number.isFinite(target) || reduce) {
            return;
        }
        const start = performance.now();
        const tick = (now) => {
            const progress = Math.min(1, (now - start) / 500);
            node.textContent = String(Math.round(target * progress));
            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };
        requestAnimationFrame(tick);
    });

    if (!reduce && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        document.querySelectorAll('.lm-reveal').forEach((node) => observer.observe(node));
    } else {
        document.querySelectorAll('.lm-reveal').forEach((node) => node.classList.add('is-in'));
    }

    document.querySelectorAll('[data-filter-list]').forEach((input) => {
        const root = document.querySelector(input.getAttribute('data-filter-list'));
        if (!root) {
            return;
        }
        input.addEventListener('input', () => {
            const query = input.value.trim().toLowerCase();
            root.querySelectorAll('[data-filter-item], .plan-card, .zone-card, article').forEach((item) => {
                const text = item.textContent.toLowerCase();
                item.hidden = query !== '' && !text.includes(query);
            });
        });
    });

    document.querySelectorAll('[data-plan-pick]').forEach((button) => {
        button.addEventListener('click', () => {
            const select = document.querySelector('#plan_id');
            if (!select) {
                return;
            }
            select.value = button.getAttribute('data-plan-pick');
            select.dispatchEvent(new Event('change'));
            document.querySelectorAll('[data-plan-pick]').forEach((item) => {
                item.classList.toggle('is-selected', item === button);
            });
        });
    });

    document.querySelectorAll('form[data-loader]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (!button || button.dataset.loading === '1') {
                return;
            }
            button.dataset.loading = '1';
            button.setAttribute('aria-busy', 'true');
            button.insertAdjacentHTML('afterbegin', '<span class="lm-spin" aria-hidden="true"></span>');
        });
    });

    document.querySelectorAll('.lm-toast, [role="status"].alert, [role="alert"]').forEach((node) => {
        node.classList.add('lm-toast');
    });
});
