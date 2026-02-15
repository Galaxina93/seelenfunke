<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('livewire:initialized', () => {
        const ctx = document.getElementById('categoryChart');
        let chartInstance = null;

        // Funktion zum Erstellen oder Aktualisieren des Charts
        const updateChart = (labels, data) => {
            if (chartInstance) {
                // Nur Daten updaten für sanfte Animation
                chartInstance.data.labels = labels;
                chartInstance.data.datasets[0].data = data;
                chartInstance.update();
            } else {
                // Chart neu erstellen
                if(!ctx) return;

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
                                '#e5e7eb'  // gray-200 (fallback)
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
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

        // Initiale Daten aus PHP (optional, falls sie inline übergeben werden sollen)
        // Aber wir verlassen uns hier auf das Event, das beim Render gefeuert wird.
        // Falls der erste Render kein Event feuert, nehmen wir die Daten aus Blade-Attributen (optional).

        // Event Listener: Hört auf $this->dispatch('update-category-chart', ...) aus PHP
        Livewire.on('update-category-chart', (event) => {
            // Livewire sendet Events manchmal als Objekt (event.labels) oder direkt (labels)
            // Je nach Version, hier fangen wir beides ab.
            const labels = event.labels || event[0]?.labels;
            const data = event.data || event[0]?.data;

            if(labels && data) {
                updateChart(labels, data);
            }
        });
    });
</script>
