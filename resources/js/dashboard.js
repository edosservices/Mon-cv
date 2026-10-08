import Chart from 'chart.js/auto';
import * as bootstrap from 'bootstrap';

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const blue = '#1463f3';
const green = '#0f8a4b';
const amber = '#d97706';
const red = '#d14343';
const navy = '#071e3d';
const palette = [blue, green, amber, red, '#0b3f91', '#7aa2ff', '#12315c', '#5d6b80'];

Chart.defaults.font.family = '"Instrument Sans", "Segoe UI", system-ui, sans-serif';
Chart.defaults.color = '#5d6b80';
Chart.defaults.animation = reduce ? false : { duration: 900, easing: 'easeOutQuart' };
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.boxHeight = 10;
Chart.defaults.plugins.tooltip.backgroundColor = navy;

function read(id) {
    const node = document.getElementById(id);
    if (!node) {
        return null;
    }
    try {
        return JSON.parse(node.textContent || 'null');
    } catch {
        return null;
    }
}

function mount(name, build) {
    const canvas = document.querySelector(`[data-en-chart="${name}"]`);
    if (!canvas) {
        return;
    }
    const data = read(canvas.getAttribute('data-en-source'));
    if (!data || (Array.isArray(data) && data.length === 0)) {
        return;
    }
    build(canvas, data);
}

const grid = 'rgba(18, 32, 51, 0.06)';
const scales = {
    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
    y: { beginAtZero: true, grid: { color: grid }, ticks: { maxTicksLimit: 5 } },
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((node) => {
        new bootstrap.Tooltip(node);
    });

    mount('revenue', (canvas, series) => {
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: series.map((row) => row.label),
                datasets: [{
                    label: 'Revenus',
                    data: series.map((row) => row.amount),
                    borderColor: blue,
                    backgroundColor: 'rgba(20, 99, 243, 0.14)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: blue,
                    borderWidth: 2,
                }],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales },
        });
    });

    mount('payments', (canvas, rows) => {
        rows = rows.filter((row) => row.total > 0);
        if (rows.length === 0) {
            return;
        }
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: rows.map((row) => row.label),
                datasets: [{
                    data: rows.map((row) => row.total),
                    backgroundColor: palette,
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: { maintainAspectRatio: false, cutout: '68%' },
        });
    });

    mount('plans', (canvas, rows) => {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.label),
                datasets: [{
                    label: 'Vendus',
                    data: rows.map((row) => row.sold),
                    backgroundColor: blue,
                    borderRadius: 8,
                    maxBarThickness: 28,
                }],
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales,
            },
        });
    });

    mount('hours', (canvas, rows) => {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.label),
                datasets: [{
                    label: 'Ventes',
                    data: rows.map((row) => row.total),
                    backgroundColor: rows.map((_, index) => palette[index % palette.length]),
                    borderRadius: 8,
                    maxBarThickness: 36,
                }],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales },
        });
    });

    mount('statuses', (canvas, rows) => {
        const entries = Object.entries(rows);
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: entries.map(([label]) => label),
                datasets: [{
                    data: entries.map(([, value]) => value),
                    backgroundColor: palette,
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: { maintainAspectRatio: false, cutout: '66%' },
        });
    });

    mount('profiles', (canvas, rows) => {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.name),
                datasets: [{
                    label: 'Tickets',
                    data: rows.map((row) => row.total),
                    backgroundColor: green,
                    borderRadius: 8,
                    maxBarThickness: 26,
                }],
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales,
            },
        });
    });

    mount('providers', (canvas, rows) => {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.provider),
                datasets: [{
                    label: 'Paiements',
                    data: rows.map((row) => row.total),
                    backgroundColor: amber,
                    borderRadius: 8,
                    maxBarThickness: 32,
                }],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales },
        });
    });
});
