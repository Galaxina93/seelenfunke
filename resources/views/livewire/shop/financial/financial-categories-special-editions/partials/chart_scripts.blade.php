<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('livewire:initialized', () => {
        const ctx = document.getElementById('categoryChart');
        if (!ctx) return;

        let chartInstance = null;

        // Initiale Daten direkt über Blade injizieren
        const initialLabels = @json($chartLabels ?? []);
        const initialData = @json($chartData ?? []);

        // Funktion zum Erstellen oder Aktualisieren des Charts
        const initOrUpdateChart = (labels, data) => {
            if (chartInstance) {
                chartInstance.data.labels = labels;
                chartInstance.data.datasets[0].data = data;
                chartInstance.update();
            } else {
                chartInstance = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: [
                                '#f97316', // orange-500
                                '#fb923c', // orange-400
                                '#fdba74', // orange-300
                                '#fed7aa', // orange-200
                                '#ffedd5', // orange-100
                                '#e5e7eb'  // fallback
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        // WICHTIG: Padding zwingt Chart.js dazu, Platz am Rand zu lassen,
                        // wodurch die Tooltips nicht mehr vom Rand abgeschnitten werden.
                        layout: {
                            padding: 15
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.parsed || 0;
                                        return label + ': ' + new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        };

        if (initialLabels.length > 0 && initialData.length > 0) {
            initOrUpdateChart(initialLabels, initialData);
        }

        Livewire.on('update-category-chart', (event) => {
            const labels = event.labels || event[0]?.labels;
            const data = event.data || event[0]?.data;
            if(labels && data) {
                initOrUpdateChart(labels, data);
            }
        });

        window.addEventListener('resize', () => {
            if (chartInstance) chartInstance.resize();
        });
    });
</script>
