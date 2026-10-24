<script>
    (function() {
        const init = () => {
            let profitChart, expensesChart, customersChart, visitsChart, acquisitionChart, sourcesChart;
            let currentStatsData = @json($stats);

            const isDark = () => document.documentElement.classList.contains('dark');
            const getTextColor = () => isDark() ? '#9ca3af' : '#64748b';
            const getLegendColor = () => isDark() ? '#e5e7eb' : '#1e293b';
            const getGridColor = () => isDark() ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)';
            const getTooltipBg = () => isDark() ? 'rgba(17, 24, 39, 0.95)' : 'rgba(255, 255, 255, 0.95)';
            const getTooltipTitle = () => isDark() ? '#f3f4f6' : '#0f172a';
            const getTooltipBody = () => isDark() ? '#d1d5db' : '#334155';
            const getPointBg = () => isDark() ? '#111827' : '#ffffff';
            const getDoughnutBorder = () => isDark() ? '#111827' : '#ffffff';

            const initCharts = (data) => {
                if (!data || !data.chart_data) return;

                Chart.defaults.color = getTextColor();
                Chart.defaults.font.family = "'Inter', 'Helvetica Neue', sans-serif";

                const profitEl = document.getElementById('profitChart');
                if (profitEl) {
                    const profitCtx = profitEl.getContext('2d');
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
                                    pointBackgroundColor: getPointBg(),
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
                                    pointBackgroundColor: getPointBg(),
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
                                },
                                {
                                    label: 'Break-Even',
                                    data: Array(data.chart_data.labels.length).fill(data.break_even_chart_line),
                                    borderColor: '#ef4444', // red-500
                                    borderWidth: 2,
                                    borderDash: [5, 5],
                                    pointRadius: 0,
                                    fill: false,
                                    tension: 0,
                                    order: 2
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
                                        color: getLegendColor()
                                    }
                                },
                                tooltip: {
                                    backgroundColor: getTooltipBg(),
                                    titleColor: getTooltipTitle(),
                                    bodyColor: getTooltipBody(),
                                    borderColor: 'rgba(197, 160, 89, 0.4)',
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
                                        color: getGridColor()
                                    },
                                    border: { display: false },
                                    ticks: { color: getTextColor() }
                                },
                                x: {
                                    grid: { display: false },
                                    border: { display: false },
                                    ticks: { color: getTextColor() }
                                }
                            }
                        }
                    });
                }

                const expCtx = document.getElementById('expensesChart');
                if(expCtx && data.top_expenses) {
                    if (expensesChart) expensesChart.destroy();
                    expensesChart = new Chart(expCtx, {
                        type: 'doughnut',
                        data: {
                            labels: data.top_expenses.map(i => i.category),
                            datasets: [{
                                data: data.top_expenses.map(i => i.total),
                                backgroundColor: ['#f87171', '#fb923c', '#fbbf24', '#a78bfa', '#2dd4bf'],
                                borderColor: getDoughnutBorder(),
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
                                    backgroundColor: getTooltipBg(),
                                    titleColor: getTooltipTitle(),
                                    bodyColor: getTooltipBody(),
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

                const custCtx = document.getElementById('customersChart');
                if(custCtx && data.top_customers) {
                    if (customersChart) customersChart.destroy();
                    customersChart = new Chart(custCtx, {
                        type: 'doughnut',
                        data: {
                            labels: data.top_customers.map(i => i.category || i.display_name),
                            datasets: [{
                                data: data.top_customers.map(i => i.total),
                                backgroundColor: ['#C5A059', '#D4AF37', '#b48a3d', '#fde047', '#fef08a'],
                                borderColor: getDoughnutBorder(),
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
                                    backgroundColor: getTooltipBg(),
                                    titleColor: getTooltipTitle(),
                                    bodyColor: getTooltipBody(),
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

                // --- 2. TRAFFIC CHART ---
                const vCtx = document.getElementById('visitsChart');
                if(vCtx && data.visit_days) {
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
                                    pointBackgroundColor: getPointBg(),
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
                                    pointBackgroundColor: getPointBg(),
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
                                    backgroundColor: getTooltipBg(),
                                    titleColor: getTooltipTitle(),
                                    bodyColor: getTooltipBody(),
                                    borderColor: 'rgba(59, 130, 246, 0.3)',
                                    borderWidth: 1,
                                    padding: 10
                                }
                            },
                            scales: {
                                y: { 
                                    beginAtZero: true, 
                                    grid: { color: getGridColor() }, 
                                    border: { display: false }, 
                                    ticks: { precision: 0, color: getTextColor() } 
                                },
                                x: { 
                                    grid: { display: false }, 
                                    border: { display: false },
                                    ticks: { color: getTextColor() }
                                }
                            }
                        }
                    });
                }

                // --- 3. KUNDENGEWINNUNG CHART ---
                const acqCtx = document.getElementById('acquisitionChart');
                if(acqCtx && data.customer_labels) {
                    if (acquisitionChart) acquisitionChart.destroy();
                    const ctxAcq = acqCtx.getContext('2d');

                    const gradAcq = ctxAcq.createLinearGradient(0, 0, 0, 300);
                    gradAcq.addColorStop(0, 'rgba(217, 70, 239, 0.4)'); // fuchsia-500
                    gradAcq.addColorStop(1, 'rgba(217, 70, 239, 0.0)');

                    acquisitionChart = new Chart(ctxAcq, {
                        type: 'line',
                        data: {
                            labels: data.customer_labels,
                            datasets: [
                                {
                                    label: 'Neue Registrierungen',
                                    data: data.customer_counts,
                                    borderColor: '#d946ef', // fuchsia-500
                                    backgroundColor: gradAcq,
                                    borderWidth: 3,
                                    pointBackgroundColor: getPointBg(),
                                    pointBorderColor: '#d946ef',
                                    pointBorderWidth: 2,
                                    fill: true,
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
                                    backgroundColor: getTooltipBg(),
                                    titleColor: getTooltipTitle(),
                                    bodyColor: getTooltipBody(),
                                    borderColor: 'rgba(217, 70, 239, 0.3)',
                                    borderWidth: 1,
                                    padding: 10
                                }
                            },
                            scales: {
                                y: { 
                                    beginAtZero: true, 
                                    grid: { color: getGridColor() }, 
                                    border: { display: false }, 
                                    ticks: { precision: 0, color: getTextColor() } 
                                },
                                x: { 
                                    grid: { display: false }, 
                                    border: { display: false },
                                    ticks: { color: getTextColor() } 
                                }
                            }
                        }
                    });
                }

                // --- 4. QUELLENANALYSE DOUGHNUT CHART ---
                const sourcesEl = document.getElementById('sourcesDoughnutChart');
                if (sourcesEl && data.traffic_sources && data.traffic_sources.chart_data && data.traffic_sources.chart_data.length > 0) {
                    if (sourcesChart) sourcesChart.destroy();
                    const ctxSources = sourcesEl.getContext('2d');

                    sourcesChart = new Chart(ctxSources, {
                        type: 'doughnut',
                        data: {
                            labels: data.traffic_sources.chart_labels,
                            datasets: [{
                                data: data.traffic_sources.chart_data,
                                backgroundColor: data.traffic_sources.chart_colors,
                                borderColor: getDoughnutBorder(),
                                borderWidth: 3,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: getTooltipBg(),
                                    titleColor: getTooltipTitle(),
                                    bodyColor: getTooltipBody(),
                                    borderColor: 'rgba(255, 255, 255, 0.1)',
                                    borderWidth: 1,
                                    padding: 10,
                                    callbacks: {
                                        label: function(context) {
                                            const val = context.parsed;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                            return ` ${context.label}: ${val} Aufrufe (${pct}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            };

            initCharts(currentStatsData);

            if (window.cleanupMasterChartsListener) {
                window.cleanupMasterChartsListener();
            }

            window.cleanupMasterChartsListener = Livewire.on('update-charts', (event) => {
                const responseData = event.stats || (event[0] && event[0].stats);
                if (responseData) {
                    currentStatsData = responseData;
                    initCharts(currentStatsData);
                }
            });

            if (window.cleanupThemeChangeListener) {
                window.removeEventListener('theme-changed', window.cleanupThemeChangeListener);
            }
            window.cleanupThemeChangeListener = () => {
                if (currentStatsData) {
                    initCharts(currentStatsData);
                }
            };
            window.addEventListener('theme-changed', window.cleanupThemeChangeListener);
        };

        if (window.Livewire) {
            init();
        } else {
            document.addEventListener('livewire:initialized', init, { once: true });
        }
    })();
</script>
