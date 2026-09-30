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
        document.querySelectorAll('.lm-nav-group').forEach((group) => {
            const summary = group.querySelector('summary');
            summary?.addEventListener('click', (event) => {
                if (!frame.classList.contains('is-collapsed')) {
                    return;
                }
                event.preventDefault();
                apply(false);
                window.localStorage.setItem('lm-sidebar', 'open');
                group.open = true;
            });
            group.addEventListener('toggle', () => {
                if (!group.open) {
                    return;
                }
                document.querySelectorAll('.lm-nav-group').forEach((other) => {
                    if (other !== group) {
                        other.open = false;
                    }
                });
            });
        });
    }

    document.querySelectorAll('[data-live-table]').forEach((root) => {
        const rows = [...root.querySelectorAll('[data-live-row]')];
        const search = root.querySelector('[data-live-search]');
        const status = root.querySelector('[data-live-status]');
        const empty = root.querySelector('[data-live-empty]');
        const pager = root.querySelector('[data-live-pager]');
        const prev = root.querySelector('[data-live-prev]');
        const next = root.querySelector('[data-live-next]');
        const pageLabel = root.querySelector('[data-live-page]');
        const size = 8;
        let page = 0;
        let sortKey = '';
        let sortDir = 1;
        const filtered = () => {
            const query = (search?.value || '').trim().toLowerCase();
            const state = status?.value || '';
            return rows.filter((row) => {
                const matchesQuery = query === '' || row.textContent.toLowerCase().includes(query);
                const matchesState = state === '' || row.getAttribute('data-status') === state;
                return matchesQuery && matchesState;
            });
        };
        const paint = () => {
            const list = filtered();
            const pages = Math.max(1, Math.ceil(list.length / size));
            page = Math.min(page, pages - 1);
            rows.forEach((row) => { row.hidden = true; });
            list.slice(page * size, page * size + size).forEach((row) => { row.hidden = false; });
            if (empty) {
                empty.hidden = list.length !== 0;
            }
            if (pager) {
                pager.hidden = list.length <= size;
            }
            if (pageLabel) {
                pageLabel.textContent = (page + 1) + ' / ' + pages;
            }
        };
        search?.addEventListener('input', () => { page = 0; paint(); });
        status?.addEventListener('change', () => { page = 0; paint(); });
        prev?.addEventListener('click', () => { page = Math.max(0, page - 1); paint(); });
        next?.addEventListener('click', () => { page += 1; paint(); });
        root.querySelectorAll('[data-live-sort]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-live-sort');
                sortDir = sortKey === key ? sortDir * -1 : 1;
                sortKey = key;
                const body = root.querySelector('tbody');
                [...rows].sort((left, right) => {
                    const a = left.querySelector(`[data-k="${key}"]`)?.textContent.trim().toLowerCase() || '';
                    const b = right.querySelector(`[data-k="${key}"]`)?.textContent.trim().toLowerCase() || '';
                    return a.localeCompare(b, 'fr') * sortDir;
                }).forEach((row) => body.appendChild(row));
                page = 0;
                paint();
            });
        });
        paint();
    });

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

    document.querySelectorAll('.lm-top, .biz-top, .shop-top, .client-top').forEach((bar) => {
        const onScroll = () => bar.classList.toggle('is-scrolled', window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    });

    document.querySelectorAll('[role="status"]').forEach((node) => {
        node.classList.add('lm-toast');
        if (reduce) {
            return;
        }
        window.setTimeout(() => node.classList.add('is-out'), 4600);
    });
});
