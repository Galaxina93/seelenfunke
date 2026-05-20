<script>
    (function() {
        const init = () => {
            const ctx = document.getElementById('groupsChart');
            let myChart = null;

            const drawChart = (labels, data, colors) => {
                if (myChart) {
                    myChart.destroy();
                }

                if (ctx) {
                    myChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: data,
                                backgroundColor: colors,
                                borderWidth: 0,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false, // WICHTIG: Verhindert Abschneiden
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        boxWidth: 12,
                                        font: { size: 11, family: 'sans-serif' },
                                        padding: 20
                                    }
                                }
                            },
                            layout: {
                                padding: 20 // Padding um das Chart, damit Hover-Effekte Platz haben
                            }
                        }
                    });
                }
            };

            // Init
            drawChart(@json($chartLabels), @json($chartData), @json($chartColors));

            // Clean up old listener
            if (window.cleanupGroupsChartListener) {
                window.cleanupGroupsChartListener();
            }

            // Listener für Updates
            window.cleanupGroupsChartListener = Livewire.on('update-groups-chart', (event) => {
                const labels = event.labels || (event[0] && event[0].labels);
                const data = event.data || (event[0] && event[0].data);
                const colors = event.colors || (event[0] && event[0].colors);
                if (labels && data && colors) {
                    drawChart(labels, data, colors);
                }
            });
        };

        if (window.Livewire) {
            init();
        } else {
            document.addEventListener('livewire:initialized', init, { once: true });
        }
    })();
</script>
