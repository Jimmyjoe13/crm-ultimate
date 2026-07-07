// Graphiques de la page Rapports — rendus via Chart.js bundlé par Vite (plus de CDN).
// Les données sont injectées par la vue dans <script type="application/json" id="reports-data">.
import Chart from 'chart.js/auto';
import { chartColors } from './theme.js';

export function initReportCharts() {
    const dataEl = document.getElementById('reports-data');
    if (!dataEl) return;

    let payload;
    try {
        payload = JSON.parse(dataEl.textContent);
    } catch {
        return;
    }

    const caMensuel = payload.ca_mensuel || [];
    const activiteHebdo = payload.activite_hebdo || [];

    const {
        accent: colorAccent,
        ok: colorOk,
        text2: colorText2,
        text3: colorText3,
        border: colorBorder,
    } = chartColors();

    // ── CA mensuel ─────────────────────────────────────────────────────────────
    const ctxCa = document.getElementById('chart-ca-mensuel');
    if (ctxCa && caMensuel.length) {
        new Chart(ctxCa, {
            type: 'bar',
            data: {
                labels: caMensuel.map(r => r.mois),
                datasets: [
                    {
                        label: 'CA gagné',
                        data: caMensuel.map(r => r.ca_gagne),
                        backgroundColor: colorOk,
                        borderRadius: 4,
                        maxBarThickness: 32,
                    },
                    {
                        label: 'Pipeline ouvert',
                        data: caMensuel.map(r => r.pipeline),
                        backgroundColor: colorAccent + '77',
                        borderRadius: 4,
                        maxBarThickness: 32,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: colorText2,
                            font: { family: 'JetBrains Mono, monospace', size: 11 },
                        },
                    },
                    tooltip: {
                        backgroundColor: 'rgba(20, 20, 15, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: { family: 'JetBrains Mono, monospace', size: 12 },
                        bodyFont: { family: 'JetBrains Mono, monospace', size: 11 },
                        padding: 10,
                        borderRadius: 6,
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.raw !== null) {
                                    label += context.raw.toLocaleString('fr-FR') + ' €';
                                }
                                return label;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: colorText3,
                            font: { family: 'JetBrains Mono, monospace', size: 10 },
                        },
                    },
                    y: {
                        grid: { color: colorBorder, drawBorder: false },
                        ticks: {
                            color: colorText3,
                            font: { family: 'JetBrains Mono, monospace', size: 10 },
                            callback: v => v.toLocaleString('fr-FR') + ' €',
                        },
                    },
                },
            },
        });
    }

    // ── Activité hebdomadaire ──────────────────────────────────────────────────
    const ctxHebdo = document.getElementById('chart-activite-hebdo');
    if (ctxHebdo && activiteHebdo.length) {
        const types = ['call', 'email', 'task', 'note', 'email_sent', 'email_opened', 'email_replied'];
        const labels = {
            call: 'Appels', email: 'Emails', task: 'Tâches', note: 'Notes',
            email_sent: 'Envoyés', email_opened: 'Ouverts', email_replied: 'Réponses',
        };

        // Palette personnalisée et vibrante
        const colors = [
            colorAccent,   // Appels
            '#3b82f6',     // Emails
            '#f59e0b',     // Tâches
            colorText2,    // Notes
            '#8b5cf6',     // Envoyés
            '#06b6d4',     // Ouverts
            colorOk,       // Réponses
        ];

        const datasets = types.map((t, i) => ({
            label: labels[t] || t,
            data: activiteHebdo.map(w => w.detail[t] ?? 0),
            borderColor: colors[i] || '#6e6a60',
            backgroundColor: (colors[i] || '#6e6a60') + '15',
            tension: 0.3,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 5,
        })).filter(ds => ds.data.some(v => v > 0));

        new Chart(ctxHebdo, {
            type: 'line',
            data: {
                labels: activiteHebdo.map(w => w.semaine),
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: colorText2,
                            font: { family: 'JetBrains Mono, monospace', size: 11 },
                        },
                    },
                    tooltip: {
                        backgroundColor: 'rgba(20, 20, 15, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: { family: 'JetBrains Mono, monospace', size: 12 },
                        bodyFont: { family: 'JetBrains Mono, monospace', size: 11 },
                        padding: 10,
                        borderRadius: 6,
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: colorText3,
                            font: { family: 'JetBrains Mono, monospace', size: 10 },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: colorBorder, drawBorder: false },
                        ticks: {
                            color: colorText3,
                            font: { family: 'JetBrains Mono, monospace', size: 10 },
                        },
                    },
                },
            },
        });
    }
}
