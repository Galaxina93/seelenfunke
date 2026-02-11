<script>
    document.addEventListener('livewire:initialized', () => {

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

        // Init on load
        initCharts();

        // Re-init on Livewire update
        Livewire.hook('morph.updated', ({ el, component }) => {
            initCharts();
        });
    });
</script>
