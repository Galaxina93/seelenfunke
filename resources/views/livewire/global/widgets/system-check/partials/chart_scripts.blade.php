<script>
    document.addEventListener('livewire:initialized', () => {
        const ctx = document.getElementById('visitsChart').getContext('2d');

        // Gradient erstellen
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.5)'); // Start color (Blue)
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)'); // End color (Transparent)

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($stats['visit_days']),
                datasets: [{
                    label: 'Seitenaufrufe',
                    data: @json($stats['visit_counts']),
                    borderColor: '#3B82F6',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.4, // Weiche Kurven
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#FFFFFF',
                    pointBorderColor: '#3B82F6',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 }
                        },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 }
                        },
                        border: { display: false }
                    }
                }
            }
        });
    });
</script>
