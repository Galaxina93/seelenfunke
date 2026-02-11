<script>
    document.addEventListener('livewire:initialized', () => {
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

        // Listener für Updates
        Livewire.on('update-groups-chart', (event) => {
            drawChart(event.labels, event.data, event.colors);
        });
    });
</script>
