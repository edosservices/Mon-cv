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

    const units = { w: 604800, d: 86400, h: 3600, m: 60, s: 1 };
    const routerSeconds = (value) => {
        if (!/^(\d+[wdhms])+$/.test(value || '')) {
            return null;
        }
        let total = 0;
        String(value).match(/\d+[wdhms]/g).forEach((part) => {
            total += Number(part.slice(0, -1)) * units[part.slice(-1)];
        });
        return total;
    };
    const describeTime = (value) => {
        const seconds = routerSeconds(value);
        if (seconds === null) {
            return '';
        }
        const hours = Math.round((seconds / 3600) * 10) / 10;
        const days = Math.round((seconds / 86400) * 10) / 10;
        const dayText = days >= 1 ? ' · ' + days + (days > 1 ? ' jours' : ' jour') : '';
        return value + ' = ' + hours + ' h = ' + seconds + ' secondes' + dayText;
    };
    const halfRate = (token) => {
        const match = String(token).trim().match(/^(\d+(?:\.\d+)?)([kKmMgG])?$/);
        if (!match) {
            return '';
        }
        let value = Number(match[1]) / 2;
        const raw = (match[2] || '').toLowerCase();
        const order = ['', 'k', 'm', 'g'];
        let index = order.indexOf(raw);
        if (index < 0) {
            return '';
        }
        if (value < 1 && index > 0) {
            value *= 1000;
            index -= 1;
        }
        const shown = Number.isInteger(value) ? String(value) : String(Math.round(value * 10) / 10);
        const unit = index === 0 ? '' : (order[index] === 'k' ? 'k' : order[index].toUpperCase());
        return shown + unit;
    };

    document.querySelectorAll('[data-time-calc]').forEach((root) => {
        const form = root.closest('form') || root;
        const validity = form.querySelector('[data-time-validity]');
        const limit = form.querySelector('[data-time-limit]');
        const readout = root.querySelector('[data-time-readout]');
        const paint = () => {
            if (readout && limit) {
                readout.textContent = describeTime(limit.value.trim());
            }
        };
        root.querySelectorAll('[data-time-pick]').forEach((button) => {
            button.addEventListener('click', () => {
                const code = button.getAttribute('data-time-pick') || '';
                if (validity) {
                    validity.value = code;
                }
                if (limit) {
                    limit.value = code;
                    limit.dataset.touched = '1';
                }
                paint();
                limit?.dispatchEvent(new Event('input'));
            });
        });
        limit?.addEventListener('input', () => {
            limit.dataset.touched = '1';
            paint();
        });
        paint();
    });

    document.querySelectorAll('[data-rate-calc]').forEach((root) => {
        const down = root.querySelector('[data-rate-down]');
        const up = root.querySelector('[data-rate-up]');
        const rate = root.querySelector('[data-rate-value]');
        const write = () => {
            const download = down.value.trim();
            const upload = up.value.trim();
            if (download !== '' && upload !== '') {
                rate.value = upload + '/' + download;
            }
        };
        const existing = (rate.value || '').split('/');
        if (existing.length === 2) {
            up.value = existing[0];
            down.value = existing[1];
        }
        down.addEventListener('input', () => {
            if (up.dataset.touched !== '1') {
                up.value = halfRate(down.value.trim());
            }
            write();
        });
        up.addEventListener('input', () => {
            up.dataset.touched = '1';
            write();
        });
    });

    document.querySelectorAll('[data-user-compose]').forEach((root) => {
        const form = root.querySelector('form');
        if (!form) {
            return;
        }
        const name = form.querySelector('[data-user-name]');
        const profile = form.querySelector('[data-user-profile]');
        const limit = form.querySelector('[data-time-limit]');
        const data = form.querySelector('[data-user-data]');
        const comment = form.querySelector('[data-user-comment]');
        const rate = form.querySelector('[data-user-rate]');
        const paint = () => {
            const option = profile?.selectedOptions?.[0];
            if (rate) {
                const value = option?.getAttribute('data-rate') || '';
                rate.textContent = value ? 'Rate limit du profil : ' + value : '';
            }
            if (!comment || comment.dataset.touched === '1') {
                return;
            }
            const parts = [
                name?.value.trim(),
                profile?.value.trim(),
                limit?.value.trim(),
                data?.value.trim() ? data.value.trim() + ' MB' : '',
            ].filter(Boolean);
            comment.value = parts.join(' · ');
        };
        profile?.addEventListener('change', () => {
            const option = profile.selectedOptions[0];
            const time = option?.getAttribute('data-time') || '';
            if (limit && limit.dataset.touched !== '1' && time) {
                limit.value = time;
                const readout = form.querySelector('[data-time-readout]');
                if (readout) {
                    readout.textContent = describeTime(time);
                }
            }
            paint();
        });
        [name, limit, data].forEach((field) => field?.addEventListener('input', paint));
        comment?.addEventListener('input', () => {
            comment.dataset.touched = comment.value.trim() === '' ? '' : '1';
            if (comment.value.trim() === '') {
                paint();
            }
        });
        if (limit && limit.value.trim() !== '') {
            limit.dataset.touched = '1';
        }
        if (comment && comment.value.trim() !== '') {
            comment.dataset.touched = '1';
        }
        profile?.dispatchEvent(new Event('change'));
    });

    document.querySelectorAll('[data-profile-fill]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-profile-compose]');
            if (!form) {
                return;
            }
            const name = form.querySelector('[data-profile-name]');
            const rate = form.querySelector('[data-rate-value]');
            const validity = form.querySelector('[data-time-validity]');
            const limit = form.querySelector('[data-time-limit]');
            const shared = form.querySelector('[name="shared_users"]');
            const pool = form.querySelector('[name="address_pool"]');
            if (name) {
                name.value = button.getAttribute('data-profile-fill') || '';
            }
            const rateValue = button.getAttribute('data-profile-rate') || '';
            if (rate && rateValue) {
                rate.value = rateValue;
                const parts = rateValue.split('/');
                const up = form.querySelector('[data-rate-up]');
                const down = form.querySelector('[data-rate-down]');
                if (parts.length === 2 && up && down) {
                    up.value = parts[0];
                    down.value = parts[1];
                }
            }
            const time = button.getAttribute('data-profile-time') || '';
            if (time) {
                if (validity) {
                    validity.value = time;
                }
                if (limit) {
                    limit.value = time;
                }
                limit?.dispatchEvent(new Event('input'));
            }
            const sharedValue = button.getAttribute('data-profile-shared') || '';
            if (shared && sharedValue) {
                shared.value = sharedValue;
            }
            const poolValue = button.getAttribute('data-profile-pool') || '';
            if (pool && poolValue) {
                const option = [...pool.options].find((item) => item.value === poolValue);
                if (option) {
                    pool.value = poolValue;
                }
            }
        });
    });
});
