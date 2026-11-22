<script>
    (function() {
        const init = () => {
            let barChart = null;

            const initCharts = () => {
                const barCtx = document.getElementById('yearlyBarChart');
                const barBridge = document.getElementById('yearly-bar-data-bridge');
                if (barCtx && barBridge) {
                    if (barChart) barChart.destroy();
                    const isDark = document.documentElement.classList.contains('dark');
                    const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)';
                    const tickColor = isDark ? '#9ca3af' : '#6b7280';

                    let labels = [];
                    let income = [];
                    let expense = [];
                    try {
                        labels = JSON.parse(barBridge.dataset.labels || '[]');
                        income = JSON.parse(barBridge.dataset.income || '[]');
                        expense = JSON.parse(barBridge.dataset.expense || '[]');
                    } catch (e) {}

                    barChart = new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Einnahmen',
                                    data: income,
                                    backgroundColor: '#34d399',
                                    borderRadius: 4
                                },
                                {
                                    label: 'Ausgaben',
                                    data: expense,
                                    backgroundColor: '#f87171',
                                    borderRadius: 4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: gridColor, drawBorder: false },
                                    ticks: { color: tickColor }
                                },
                                x: {
                                    grid: { display: false, drawBorder: false },
                                    ticks: { color: tickColor }
                                }
                            }
                        }
                    });
                }
            };

            window.initAccountingAnalyticsCharts = initCharts;
            initCharts();

            if (!window.hasAccountingAnalyticsHook) {
                window.hasAccountingAnalyticsHook = true;
                Livewire.hook('commit', ({ component, succeed }) => {
                    succeed(() => {
                        if (
                            component.name === 'shop.accounting.accounting-analytics' ||
                            component.name === 'shop.accounting.accounting-evaluation'
                        ) {
                            setTimeout(() => {
                                if (typeof window.initAccountingAnalyticsCharts === 'function') {
                                    window.initAccountingAnalyticsCharts();
                                }
                            }, 50);
                        }
                    });
                });
            }
        };

        if (window.Livewire) {
            init();
        } else {
            document.addEventListener('livewire:initialized', init, { once: true });
        }
    })();
</script>
