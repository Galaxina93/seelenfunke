<div>
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Agenten Dashboard (Analyse)</h1>
        <p class="text-gray-400">Übersichtskennzahlen, Token-Ökonomie und Tool-Usage der gesamten digitalen Belegschaft.</p>
    </div>

    <!-- ApexCharts CDN Injection -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- Kachel-KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 mb-8">
        <!-- Tokens Today -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 relative overflow-hidden">
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-emerald-500/20 to-transparent"></div>
            <div class="text-gray-400 text-sm font-medium mb-1">Tokens heute</div>
            <div class="text-3xl font-bold text-white mb-2">{{ number_format($tokensToday, 0, ',', '.') }}</div>
            <div class="text-xs {{ $tokensToday > $tokensYesterday ? 'text-red-400' : 'text-emerald-400' }}">
                <i class="fa-solid fa-trend-{{ $tokensToday > $tokensYesterday ? 'up' : 'down' }} mr-1"></i>
                vs. {{ number_format($tokensYesterday, 0, ',', '.') }} Gestern
            </div>
        </div>

        <!-- Mittwald Pro Paket Auslastung -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 relative overflow-hidden">
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-blue-500/10 to-transparent"></div>
            <div class="text-gray-400 text-sm font-medium mb-1">Paket (Mittwald Pro)</div>
            
            @php 
                $limit = 75000000; 
                $percent = min(100, ($tokensThisMonth / $limit) * 100);
            @endphp
            
            <div class="text-3xl font-bold text-white mb-2">39,00 € <span class="text-sm font-normal text-gray-400">/ Mon.</span></div>
            
            <div class="flex justify-between text-xs text-gray-400 mb-1 mt-2">
                <span>{{ number_format($tokensThisMonth, 0, ',', '.') }} Tokens</span>
                <span>75 Mio. Limit</span>
            </div>
            <!-- ProgressBar -->
            <div class="w-full bg-gray-700 rounded-full h-1.5">
                <div class="{{ $percent > 90 ? 'bg-red-500' : 'bg-blue-500' }} h-1.5 rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
            </div>
        </div>

        <!-- Avg Latency -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5">
            <div class="text-gray-400 text-sm font-medium mb-1">Ø Latenz (30 Tage)</div>
            <div class="text-3xl font-bold text-white mb-2">{{ $avgLatency }} <span class="text-lg text-gray-400">ms</span></div>
            <div class="text-xs text-gray-500">
                Time To First Token (TTFT/Total)
            </div>
        </div>

        <!-- Success Rate -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5">
            <div class="text-gray-400 text-sm font-medium mb-1">Erfolgsquote (30 Tage)</div>
            <div class="text-3xl font-bold {{ $successRate < 95 ? 'text-red-400' : 'text-emerald-400' }} mb-2">{{ $successRate }}%</div>
            <div class="text-xs text-gray-500">
                Erfolgreiche KI Inferences
            </div>
        </div>

        <!-- Chat Interactions -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 relative overflow-hidden">
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-purple-500/10 to-transparent"></div>
            <div class="text-gray-400 text-sm font-medium mb-1">KI-Chats (30 Tage)</div>
            <div class="text-3xl font-bold text-white mb-2">{{ number_format($totalChatMessages, 0, ',', '.') }}</div>
            <div class="text-xs text-gray-500">
                Nachrichten & Interaktionen
            </div>
        </div>
        
        <!-- Top Werkzeuge -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 relative overflow-hidden flex flex-col justify-center">
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-cyan-500/10 to-transparent"></div>
            <div class="text-gray-400 text-[11px] uppercase tracking-widest font-bold mb-3 flex items-center gap-1.5"><i class="bi bi-cpu text-cyan-400"></i> Top KI-Tools</div>
            <div class="space-y-1.5 overflow-hidden">
                @forelse($topToolsAllAgents as $t)
                    <div class="flex justify-between items-center text-xs text-gray-300 font-mono">
                        <span class="truncate pr-2 w-32" title="{{ $t->tool_name }}">{{ Str::limit(str_replace('support_', '', $t->tool_name), 12) }}</span>
                        <span class="text-cyan-400 font-bold bg-gray-900 border border-gray-700 px-1.5 py-0.5 rounded">{{ $t->usage_count }}</span>
                    </div>
                @empty
                    <div class="text-[10px] text-gray-500 italic mt-2">Noch keine Tool-Aktivität heute.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Token-Trend Chart -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 lg:col-span-2" 
             x-data='analyticsTrendChart({{ $trendData }})'>
            <h3 class="text-white font-semibold mb-4 text-lg">Token-Trend (Verbrauch / Letzte 30 Tage)</h3>
            <div class="w-full h-[350px]">
                <div x-ref="chart"></div>
            </div>
        </div>

        <!-- Cognitive Load (Aktuelle Höchstwerte) -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5">
            <h3 class="text-white font-semibold mb-4 text-lg">Kognitive Auslastung (Heute)</h3>
            
            <div class="space-y-4 overflow-y-auto max-h-[350px] pr-2">
                @forelse($cognitiveLoad as $load)
                <div class="border border-gray-700/50 bg-gray-900/50 rounded-lg p-3">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-{{ $load['color'] }}-500"></div>
                            <span class="text-gray-300 font-medium">{{ $load['agent'] }}</span>
                        </div>
                        <span class="text-xs text-secondary-400 font-mono">{{ number_format($load['tokens'], 0, ',', '.') }} / 32k</span>
                    </div>
                    <!-- ProgressBar -->
                    <div class="w-full bg-gray-700 rounded-full h-1.5 dark:bg-gray-700">
                        <div class="{{ $load['percent'] > 80 ? 'bg-red-500' : 'bg-'.$load['color'].'-500' }} h-1.5 rounded-full" style="width: {{ $load['percent'] }}%"></div>
                    </div>
                </div>
                @empty
                    <div class="text-sm text-gray-500 text-center py-6">Heute keine Kontext-Auslastung protokolliert.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Zweite Reihe Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Donut: Ressourcen Verteilung -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5" x-data='analyticsDonutChart({{ $resourceDistribution }})'>
            <h3 class="text-white font-semibold mb-4 text-lg">Ressourcen-Verteilung (Tokens pro Agent / Monat)</h3>
            <div class="flex justify-center h-[300px]">
                <div x-ref="chart"></div>
            </div>
        </div>

        <!-- Bar: Tool Fehler -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5" x-data='analyticsBarChart({{ $toolErrors }})'>
            <h3 class="text-white font-semibold mb-4 text-lg">Tool-Fehlerquote (Most Failed)</h3>
            <div class="w-full h-[300px]">
                <div x-ref="chart"></div>
            </div>
        </div>

    </div>

    <script>
        const registerAiAnalyticsCharts = () => {

            Alpine.data('analyticsTrendChart', (data) => ({
                chart: null,
                init() {
                    const options = {
                        series: [{
                            name: 'Verbrauchte Tokens',
                            data: data.data
                        }],
                        chart: {
                            type: 'area',
                            height: 350,
                            foreColor: '#9ca3af',
                            toolbar: { show: false },
                            background: 'transparent'
                        },
                        colors: ['#0ea5e9'],
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shadeIntensity: 1,
                                opacityFrom: 0.7,
                                opacityTo: 0.1,
                                stops: [0, 90, 100]
                            }
                        },
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            curve: 'smooth',
                            width: 2
                        },
                        xaxis: {
                            categories: data.categories,
                            axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: {
                            min: 0
                        },
                        grid: {
                            borderColor: '#374151',
                            strokeDashArray: 4,
                            yaxis: { lines: { show: true } }
                        },
                        theme: { mode: 'dark' }
                    };

                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();
                }
            }));

            Alpine.data('analyticsDonutChart', (data) => ({
                chart: null,
                init() {
                    if (data.series.length === 0) {
                        this.$refs.chart.innerHTML = '<div class="text-gray-500 flex items-center justify-center h-full text-sm italic">Aktuell liegen noch keine Daten vor.</div>';
                        return;
                    }
                    
                    const options = {
                        series: data.series,
                        labels: data.labels,
                        chart: {
                            type: 'donut',
                            height: 300,
                            background: 'transparent'
                        },
                        theme: { mode: 'dark' },
                        stroke: { show: false },
                        dataLabels: { enabled: false },
                        legend: { position: 'bottom' },
                        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6']
                    };
                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();
                }
            }));

            Alpine.data('analyticsBarChart', (data) => ({
                chart: null,
                init() {
                    if (data.categories.length === 0) {
                        this.$refs.chart.innerHTML = '<div class="text-gray-500 flex items-center justify-center h-full text-sm italic">Aktuell liegen noch keine Daten vor.</div>';
                        return;
                    }
                    const options = {
                        series: [{
                            name: 'Anzahl Fehler',
                            data: data.data
                        }],
                        chart: {
                            type: 'bar',
                            height: 300,
                            foreColor: '#9ca3af',
                            toolbar: { show: false },
                            background: 'transparent'
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                horizontal: true,
                            }
                        },
                        colors: ['#ef4444'],
                        dataLabels: {
                            enabled: true
                        },
                        xaxis: {
                            categories: data.categories,
                        },
                        grid: {
                            borderColor: '#374151',
                            strokeDashArray: 4,
                        },
                        theme: { mode: 'dark' }
                    };

                    this.chart = new ApexCharts(this.$refs.chart, options);
                    this.chart.render();
                }
            }));
        };

        if (window.Alpine) {
            registerAiAnalyticsCharts();
        } else {
            document.addEventListener('alpine:init', registerAiAnalyticsCharts);
        }
    </script>
</div>
