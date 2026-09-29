import './limete-ui.js';

document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.lp-header');
    const burger = document.querySelector('.lp-burger');
    if (burger && header) {
        burger.addEventListener('click', () => {
            const open = header.classList.toggle('open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        header.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                header.classList.remove('open');
                burger.setAttribute('aria-expanded', 'false');
            });
        });
    }

    document.querySelectorAll('.lp-faq-item button').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('.lp-faq-item');
            const open = item.classList.contains('open');
            document.querySelectorAll('.lp-faq-item').forEach((node) => {
                node.classList.remove('open');
                node.querySelector('button')?.setAttribute('aria-expanded', 'false');
            });
            if (!open) {
                item.classList.add('open');
                button.setAttribute('aria-expanded', 'true');
            }
        });
    });
});
