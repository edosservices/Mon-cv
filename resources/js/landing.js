import './limete-ui.js';
import './limete-background.js';
import { bootMotion } from './limete-motion.js';
import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    bootMotion();
    demoCharts();
    const header = document.querySelector('.lp-header');
    const burger = document.querySelector('.lp-burger');
    if (burger && header) {
        burger.addEventListener('click', () => {
            const open = header.classList.toggle('open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            burger.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
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

function demoCharts() {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const blue = '#1463f3';
    const green = '#0f8a4b';
    const ink = '#5c6e86';
    const grid = 'rgba(18, 32, 51, .08)';
    const hours = ['6h', '9h', '12h', '15h', '18h', '21h'];
    const base = {
        responsive: true,
        maintainAspectRatio: false,
        animation: reduce ? false : { duration: 700 },
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, color: ink } },
            tooltip: { enabled: true },
        },
    };
    const axes = {
        x: { grid: { color: grid }, ticks: { color: ink, maxRotation: 0 } },
        y: {
            beginAtZero: true,
            suggestedMax: 100,
            grid: { color: grid },
            ticks: { color: ink, maxTicksLimit: 5 },
            title: { display: true, text: 'Indice d’exemple', color: ink },
        },
    };

    const performance = document.querySelector('[data-chart="performance"]');
    if (performance) {
        new Chart(performance, {
            type: 'line',
            data: {
                labels: hours,
                datasets: [{
                    label: 'Exemple',
                    data: [46, 62, 74, 68, 81, 57],
                    borderColor: blue,
                    backgroundColor: 'rgba(20, 99, 243, .12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                    pointBackgroundColor: blue,
                }],
            },
            options: { ...base, scales: axes },
        });
    }

    const usage = document.querySelector('[data-chart="usage"]');
    if (usage) {
        new Chart(usage, {
            type: 'bar',
            data: {
                labels: hours,
                datasets: [{
                    label: 'Exemple',
                    data: [28, 44, 70, 63, 86, 51],
                    backgroundColor: green,
                    borderRadius: 6,
                    maxBarThickness: 28,
                }],
            },
            options: { ...base, scales: axes },
        });
    }

    const availability = document.querySelector('[data-chart="availability"]');
    if (availability) {
        new Chart(availability, {
            type: 'doughnut',
            data: {
                labels: ['Matin', 'Journée', 'Soir'],
                datasets: [{
                    label: 'Exemple',
                    data: [22, 48, 30],
                    backgroundColor: [blue, green, '#d7e4fb'],
                    borderWidth: 0,
                }],
            },
            options: { ...base, cutout: '62%' },
        });
    }
}
