import './limete-ui.js';
import './limete-background.js';

document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-lm-menu]');
    const side = document.getElementById('lm-side');
    const backdrop = document.querySelector('[data-lm-backdrop]');
    const setOpen = (open) => {
        if (!side || !button) {
            return;
        }
        side.classList.toggle('is-open', open);
        backdrop?.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    button?.addEventListener('click', () => setOpen(!side.classList.contains('is-open')));
    backdrop?.addEventListener('click', () => setOpen(false));

    document.querySelectorAll('.js-copy').forEach((copyButton) => {
        copyButton.addEventListener('click', () => {
            const url = copyButton.getAttribute('data-url') || '';
            const done = () => { copyButton.textContent = 'Lien copié'; };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done).catch(() => window.prompt('Copiez le lien', url));
                return;
            }
            window.prompt('Copiez le lien', url);
        });
    });
});
