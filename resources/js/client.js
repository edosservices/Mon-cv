import './limete-ui.js';
import './limete-background.js';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-guide]').forEach((root) => {
        const slides = [...root.querySelectorAll('[data-guide-slide]')];
        const dots = [...root.querySelectorAll('[data-guide-dot]')];
        if (slides.length < 2) {
            return;
        }
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let index = 0;
        let timer = 0;
        const show = (next) => {
            index = (next + slides.length) % slides.length;
            slides.forEach((slide, i) => slide.classList.toggle('is-on', i === index));
            dots.forEach((dot, i) => dot.setAttribute('aria-current', i === index ? 'true' : 'false'));
        };
        const play = () => {
            window.clearInterval(timer);
            if (reduce) {
                return;
            }
            timer = window.setInterval(() => show(index + 1), 4500);
        };
        dots.forEach((dot, i) => {
            dot.addEventListener('click', () => {
                show(i);
                play();
            });
        });
        show(0);
        play();
    });

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
