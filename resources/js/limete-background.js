export function bootBackground() {
    const layer = document.querySelector('[data-lm-net]');
    if (!layer) {
        return;
    }
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const saveData = navigator.connection && navigator.connection.saveData;
    const photo = layer.querySelector('[data-lm-photo]');
    if (photo && !saveData && window.innerWidth >= 768) {
        photo.src = photo.dataset.src || '';
    }
    if (reduce) {
        layer.dataset.static = '1';
        return;
    }
    const pause = () => {
        layer.dataset.paused = document.hidden ? '1' : '0';
    };
    pause();
    document.addEventListener('visibilitychange', pause);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootBackground);
} else {
    bootBackground();
}
