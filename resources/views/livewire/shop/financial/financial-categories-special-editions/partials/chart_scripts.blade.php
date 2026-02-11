<script>
    document.addEventListener('livewire:initialized', () => {
        const ctx = document.getElementById('categoryChart');
        let myChart = null;

        const drawChart = (labels, data) => {
            if (myChart) {
                myChart.destroy();
            }

            if (ctx) {
                // Farben generieren (Orange/Grau Töne für Kategorien)
                const colors = [
                    '#fb923c', '#f87171', '#fbbf24', '#facc15', '#a3e635', '#4ade80', '#34d399', '#22d3ee', '#38bdf8', '#60a5fa', '#818cf8', '#a78bfa'
                ];

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
                        maintainAspectRatio: false,
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

        // Initials
        drawChart(@json($chartLabels), @json($chartData));

        // Updates
        Livewire.on('update-category-chart', (event) => {
            drawChart(event.labels, event.data);
        });
    });
</script>
