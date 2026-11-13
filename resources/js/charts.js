import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

// Design tokens (single-series charts use the brand blue; grid and axes stay recessive)
const INK = '#52514e';
const GRID = '#e8e8ec';
const BAR = '#1f48a0';

Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.color = INK;

/**
 * Render every <canvas data-chart="bar|hbar"> on the page.
 * Data comes from data-labels / data-values (JSON) and data-label (series name).
 */
document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    const horizontal = canvas.dataset.chart === 'hbar';
    const labels = JSON.parse(canvas.dataset.labels);
    const values = JSON.parse(canvas.dataset.values);
    const name = canvas.dataset.label || 'Total';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: name,
                data: values,
                backgroundColor: BAR,
                hoverBackgroundColor: '#153680',
                borderRadius: 4,
                borderSkipped: 'bottom',
                maxBarThickness: 28,
            }],
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0b0b0b',
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: { label: (ctx) => `${name}: ${ctx.parsed[horizontal ? 'x' : 'y']}` },
                },
            },
            scales: {
                [horizontal ? 'x' : 'y']: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: GRID },
                    border: { display: false },
                },
                [horizontal ? 'y' : 'x']: {
                    grid: { display: false },
                    border: { color: GRID },
                },
            },
        },
    });
});
