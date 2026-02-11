<script>
    document.addEventListener('livewire:initialized', () => {
        let pieChart = null;
        let barChart = null;

        const initCharts = () => {
            // Pie Chart
            const pieCtx = document.getElementById('specialPieChart');
            if (pieCtx) {
                if (pieChart) pieChart.destroy();
                pieChart = new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: @json($pieData['labels']),
                        datasets: [{
                            data: @json($pieData['data']),
                            backgroundColor: [
                                '#f87171', '#fb923c', '#fbbf24', '#facc15', '#a3e635', '#4ade80', '#34d399', '#22d3ee', '#38bdf8', '#60a5fa', '#818cf8', '#a78bfa'
                            ],
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }

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
