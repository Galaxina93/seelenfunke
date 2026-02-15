<script>
    document.addEventListener('livewire:initialized', () => {

        let profitChart, expensesChart, customersChart, visitsChart;

        const initCharts = (data) => {
            // Profit Chart
            const profitCtx = document.getElementById('profitChart').getContext('2d');
            const profitGradient = profitCtx.createLinearGradient(0, 0, 0, 300);
            profitGradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
            profitGradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

            if (profitChart) profitChart.destroy();

            profitChart = new Chart(profitCtx, {
                type: 'line',
                data: {
                    labels: data.chart_data.labels,
                    datasets: [
                        {
                            label: 'Gewinn',
                            data: data.chart_data.profit,
                            borderColor: '#10b981', // Emerald 500
                            backgroundColor: profitGradient,
                            borderWidth: 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#10b981',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            fill: true,
                            tension: 0.4,
                            order: 1
                        },
                        {
                            label: 'Umsatz',
                            data: data.chart_data.revenue,
                            borderColor: '#6366f1', // Indigo 500
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#6366f1',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: false,
                            tension: 0.4,
                            order: 0
                        },
                        {
                            label: 'Ausgaben',
                            data: data.chart_data.expenses,
                            borderColor: '#f43f5e', // Rose 500
                            borderWidth: 2,
                            pointRadius: 0,
                            fill: false,
                            tension: 0.4,
                            hidden: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: true, position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 6 } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, border: { display: false } },
                        x: { grid: { display: false }, border: { display: false } }
                    }
                }
            });

            // Expenses Chart
            const expCtx = document.getElementById('expensesChart');
            if(expCtx) {
                if(expensesChart) expensesChart.destroy();
                expensesChart = new Chart(expCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.top_expenses.map(i => i.category),
                        datasets: [{
                            data: data.top_expenses.map(i => i.total),
                            backgroundColor: ['#f43f5e', '#fb923c', '#facc15', '#a78bfa', '#2dd4bf'],
                            borderWidth: 0,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        return label + ': ' + new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
                                    }
                                }
                            }
                        },
                        layout: {
                            padding: 20
                        }
                    }
                });
            }

            // Customers Chart
            const custCtx = document.getElementById('customersChart');
            if(custCtx) {
                if(customersChart) customersChart.destroy();
                customersChart = new Chart(custCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.top_customers.map(i => i.display_name),
                        datasets: [{
                            data: data.top_customers.map(i => i.total),
                            backgroundColor: ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe'],
                            borderWidth: 0,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        return label + ': ' + new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
                                    }
                                }
                            }
                        },
                        layout: {
                            padding: 20
                        }
                    }
                });
            }
        };

        // Init with initial data passed from component
        initCharts(@json($stats));

        // Listen for updates from Livewire
        Livewire.on('update-charts', (event) => {
            // Access the 'stats' property from the event detail
            initCharts(event.stats || event[0].stats);
        });

        // Visits Chart (Static)
        if(document.getElementById('visitsChart')) {
            const ctxVisits = document.getElementById('visitsChart').getContext('2d');
            const gradVisits = ctxVisits.createLinearGradient(0, 0, 0, 300);
            gradVisits.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
            gradVisits.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

            new Chart(ctxVisits, {
                type: 'line',
                data: {
                    labels: @json($stats['visit_days']),
                    datasets: [{
                        label: 'Seitenaufrufe',
                        data: @json($stats['visit_counts']),
                        borderColor: '#3B82F6',
                        backgroundColor: gradVisits,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { display:false }, border: {display:false} }, x: { grid: { display:false }, border: {display:false} } } }
            });
        }
    });
</script>
