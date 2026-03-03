<script>
    document.addEventListener('livewire:initialized', () => {
        let profitChart, expensesChart, customersChart, visitsChart;

        // Standardvorgaben für dunkles Design
        Chart.defaults.color = '#9ca3af'; // gray-400
        Chart.defaults.font.family = "'Inter', 'Helvetica Neue', sans-serif";

        const initCharts = (data) => {
            const profitCtx = document.getElementById('profitChart').getContext('2d');
            const profitGradient = profitCtx.createLinearGradient(0, 0, 0, 300);
            profitGradient.addColorStop(0, 'rgba(197, 160, 89, 0.5)'); // primary
            profitGradient.addColorStop(1, 'rgba(197, 160, 89, 0.0)');

            if (profitChart) profitChart.destroy();
            profitChart = new Chart(profitCtx, {
                type: 'line',
                data: {
                    labels: data.chart_data.labels,
                    datasets: [
                        {
                            label: 'Gewinn',
                            data: data.chart_data.profit,
                            borderColor: '#C5A059', // primary
                            backgroundColor: profitGradient,
                            borderWidth: 3,
                            pointBackgroundColor: '#111827', // gray-900
                            pointBorderColor: '#C5A059',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            fill: true,
                            tension: 0.4,
                            order: 1
                        },
                        {
                            label: 'Umsatz',
                            data: data.chart_data.revenue,
                            borderColor: '#6b7280', // gray-500
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointBackgroundColor: '#111827',
                            pointBorderColor: '#6b7280',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: false,
                            tension: 0.4,
                            order: 0
                        },
                        {
                            label: 'Ausgaben',
                            data: data.chart_data.expenses,
                            borderColor: '#f87171', // red-400
                            borderWidth: 2,
                            pointRadius: 0,
                            fill: false,
                            tension: 0.4,
                            hidden: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                color: '#e5e7eb' // gray-200
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17, 24, 39, 0.9)', // gray-900
                            titleColor: '#f3f4f6', // gray-100
                            bodyColor: '#d1d5db', // gray-300
                            borderColor: 'rgba(197, 160, 89, 0.3)',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            },
                            border: { display: false },
                            ticks: { color: '#9ca3af' }
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { color: '#9ca3af' }
                        }
                    }
                }
            });

            const expCtx = document.getElementById('expensesChart');
            if(expCtx) {
                if (expensesChart) expensesChart.destroy();
                expensesChart = new Chart(expCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.top_expenses.map(i => i.category),
                        datasets: [{
                            data: data.top_expenses.map(i => i.total),
                            backgroundColor: ['#f87171', '#fb923c', '#fbbf24', '#a78bfa', '#2dd4bf'],
                            borderColor: '#111827', // border to match background
                            borderWidth: 2,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        return label + ': ' + new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
                                    }
                                }
                            }
                        },
                        layout: { padding: 20 }
                    }
                });
            }

            const custCtx = document.getElementById('customersChart');
            if(custCtx) {
                if (customersChart) customersChart.destroy();
                customersChart = new Chart(custCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.top_customers.map(i => i.category || i.display_name),
                        datasets: [{
                            data: data.top_customers.map(i => i.total),
                            backgroundColor: ['#C5A059', '#D4AF37', '#b48a3d', '#fde047', '#fef08a'],
                            borderColor: '#111827',
                            borderWidth: 2,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                borderColor: 'rgba(197, 160, 89, 0.3)',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        return label + ': ' + new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
                                    }
                                }
                            }
                        },
                        layout: { padding: 20 }
                    }
                });
            }

            // --- 2. TRAFFIC CHART (NEU) ---
            const vCtx = document.getElementById('visitsChart');
            if(vCtx) {
                if (visitsChart) visitsChart.destroy();
                const ctxVisits = vCtx.getContext('2d');

                const gradViews = ctxVisits.createLinearGradient(0, 0, 0, 300);
                gradViews.addColorStop(0, 'rgba(59, 130, 246, 0.3)'); // blue-500
                gradViews.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

                visitsChart = new Chart(ctxVisits, {
                    type: 'line',
                    data: {
                        labels: data.visit_days,
                        datasets: [
                            {
                                label: 'Seitenaufrufe',
                                data: data.visit_counts,
                                borderColor: '#3b82f6', // blue-500
                                backgroundColor: gradViews,
                                borderWidth: 3,
                                pointBackgroundColor: '#111827',
                                pointBorderColor: '#3b82f6',
                                pointBorderWidth: 2,
                                fill: true,
                                tension: 0.4
                            },
                            {
                                label: 'Eindeutige Besucher',
                                data: data.unique_counts,
                                borderColor: '#34d399', // emerald-400
                                borderWidth: 2,
                                borderDash: [4, 4],
                                pointBackgroundColor: '#111827',
                                pointBorderColor: '#34d399',
                                pointBorderWidth: 2,
                                fill: false,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                borderColor: 'rgba(59, 130, 246, 0.3)',
                                borderWidth: 1,
                                padding: 10
                            }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' }, border: { display: false }, ticks: { precision: 0 } },
                            x: { grid: { display: false }, border: { display: false } }
                        }
                    }
                });
            }
        };

        initCharts(@json($stats));

        Livewire.on('update-charts', (event) => {
            const responseData = event.stats || (event[0] && event[0].stats);
            if (responseData) {
                initCharts(responseData);
            }
        });
    });
</script>
