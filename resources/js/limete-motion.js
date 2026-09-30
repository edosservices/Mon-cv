const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const fine = window.matchMedia('(pointer: fine)').matches;

export function reveal(selector = '.lm-motion') {
    const nodes = document.querySelectorAll(selector);
    if (reduce || !('IntersectionObserver' in window)) {
        nodes.forEach((node) => node.classList.add('is-in'));
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-in');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.16 });
    nodes.forEach((node) => observer.observe(node));
}

export function stagger(selector = '[data-stagger]') {
    document.querySelectorAll(selector).forEach((group) => {
        [...group.children].forEach((child, index) => {
            child.style.transitionDelay = reduce ? '0ms' : `${index * 70}ms`;
            child.classList.add('lm-motion');
        });
    });
}

export function counter(selector = '[data-count]') {
    const nodes = [...document.querySelectorAll(selector)];
    const format = (value) => Math.round(value).toLocaleString('fr-FR');
    const run = (node) => {
        const target = Number(node.getAttribute('data-count'));
        if (!Number.isFinite(target) || node.dataset.counted === '1') return;
        node.dataset.counted = '1';
        if (reduce) {
            node.textContent = format(target);
            return;
        }
        const start = performance.now();
        const tick = (now) => {
            const progress = Math.min(1, (now - start) / 900);
            const eased = 1 - Math.pow(1 - progress, 3);
            node.textContent = format(target * eased);
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };
    if (!('IntersectionObserver' in window)) {
        nodes.forEach(run);
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            run(entry.target);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.45 });
    nodes.forEach((node) => observer.observe(node));
}

export function smoothReveal(selector = '[data-smooth]') {
    const nodes = [...document.querySelectorAll(selector)];
    if (reduce || typeof HTMLElement === 'undefined' || !HTMLElement.prototype.animate) {
        nodes.forEach((node) => node.classList.add('is-in'));
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.animate(
                [
                    { opacity: 0, transform: 'translateY(28px)' },
                    { opacity: 1, transform: 'none' },
                ],
                { duration: 520, easing: 'cubic-bezier(.2,.7,.2,1)', fill: 'both' },
            );
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.18 });
    nodes.forEach((node) => {
        node.style.opacity = '0';
        observer.observe(node);
    });
}

export function cursorGlow() {
    if (reduce || !fine) return;
    const glow = document.createElement('div');
    glow.className = 'lp-cursor';
    glow.setAttribute('aria-hidden', 'true');
    document.body.appendChild(glow);
    window.addEventListener('pointermove', (event) => {
        glow.style.transform = `translate3d(${event.clientX}px, ${event.clientY}px, 0)`;
    }, { passive: true });
}

export function parallax(selector = '[data-parallax]') {
    if (reduce || !fine) return;
    const nodes = [...document.querySelectorAll(selector)];
    window.addEventListener('pointermove', (event) => {
        const x = (event.clientX / window.innerWidth - .5) * 10;
        const y = (event.clientY / window.innerHeight - .5) * 8;
        nodes.forEach((node) => {
            node.style.transform = `translate3d(${x}px, ${y}px, 0)`;
        });
    }, { passive: true });
}

export function tiltCard(selector = '[data-tilt]') {
    if (reduce || !fine) return;
    document.querySelectorAll(selector).forEach((card) => {
        card.addEventListener('pointermove', (event) => {
            const box = card.getBoundingClientRect();
            const rx = ((event.clientY - box.top) / box.height - .5) * -6;
            const ry = ((event.clientX - box.left) / box.width - .5) * 6;
            card.style.transform = `perspective(800px) rotateX(${rx}deg) rotateY(${ry}deg)`;
        });
        card.addEventListener('pointerleave', () => { card.style.transform = ''; });
    });
}

export function magneticButton(selector = '[data-magnetic]') {
    if (reduce || !fine) return;
    document.querySelectorAll(selector).forEach((button) => {
        button.addEventListener('pointermove', (event) => {
            const box = button.getBoundingClientRect();
            const x = event.clientX - (box.left + box.width / 2);
            const y = event.clientY - (box.top + box.height / 2);
            button.style.transform = `translate3d(${x * .12}px, ${y * .12}px, 0)`;
        });
        button.addEventListener('pointerleave', () => { button.style.transform = ''; });
    });
}

export function pageTransition() {
    if (reduce) return;
    document.querySelectorAll('a[href^="/"], a[href^="' + location.origin + '"]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#')) return;
            event.preventDefault();
            document.body.classList.add('lp', 'is-leaving');
            window.setTimeout(() => { window.location.href = link.href; }, 180);
        });
    });
}

export function heroVideo() {
    const video = document.querySelector('.lp-video');
    if (!video || reduce || window.innerWidth < 768) return;
    const source = video.getAttribute('data-src');
    if (!source) return;
    video.preload = 'metadata';
    video.src = source;
    video.addEventListener('playing', () => video.classList.add('is-ready'), { once: true });
    video.play().catch(() => {});
}

export function bootMotion() {
    document.documentElement.classList.add('lm-ready');
    stagger();
    reveal();
    smoothReveal();
    counter();
    parallax();
    tiltCard();
    magneticButton();
    cursorGlow();
    heroVideo();
    pageTransition();
    const header = document.querySelector('.lp-header');
    if (header) {
        const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }
}
