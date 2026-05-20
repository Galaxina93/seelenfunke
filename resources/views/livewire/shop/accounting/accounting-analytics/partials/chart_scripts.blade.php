<script>
    (function() {
        const init = () => {
            let barChart = null;

            const initCharts = () => {
                // Bar Chart
                const barCtx = document.getElementById('yearlyBarChart');
                if (barCtx) {
                    if (barChart) barChart.destroy();
                    barChart = new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: @json($barData['labels']),
                            datasets: [
                                {
                                    label: 'Einnahmen',
                                    data: @json($barData['income']),
                                    backgroundColor: '#34d399',
                                    borderRadius: 4
                                },
                                {
                                    label: 'Ausgaben',
                                    data: @json($barData['expense']),
                                    backgroundColor: '#f87171',
                                    borderRadius: 4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }
            };

            // Expose current initCharts globally
            window.initAccountingAnalyticsCharts = initCharts;

            // Init on load
            initCharts();

            // Re-init on Livewire update (only for this specific component to avoid global polls triggering it)
            if (!window.hasAccountingAnalyticsHook) {
                window.hasAccountingAnalyticsHook = true;
                Livewire.hook('commit', ({ component, succeed }) => {
                    succeed(() => {
                        // Check if the component that just updated is the Evaluation Component
                        if (component.name === 'shop.accounting.accounting-evaluation') {
                            // Give DOM a tick to replace elements
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
