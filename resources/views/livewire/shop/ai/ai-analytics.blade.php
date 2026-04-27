<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="px-4 py-8 max-w-[1600px] mx-auto min-h-screen">
    <!-- Header -->
    <div class="text-center mb-12 mt-4 font-mono">
        <h1 class="text-3xl sm:text-4xl font-black text-[var(--theme-color)] tracking-widest uppercase shadow-[0_0_15px_var(--theme-color)]/20 drop-shadow-md">
            KI Analytics
        </h1>
        <p class="text-gray-400 mt-2 text-sm uppercase tracking-widest">
            Übersichtskennzahlen, Token-Ökonomie und Tool-Usage der digitalen Belegschaft.
        </p>
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

        <!-- Paket Auslastung -->
        @php
            $limit = $activePlan ? $activePlan->token_limit : 75000000;
            $isUnlimited = !$limit;
            $percent = $isUnlimited ? 0 : min(100, ($tokensThisMonth / $limit) * 100);
            $planName = $activePlan ? $activePlan->name : 'Unbekanntes Paket';
            $price = $activePlan ? number_format($activePlan->price_monthly, 2, ',', '.') : '0,00';
            
            function formatTokenSuffix($amount) {
                if ($amount >= 1000000) return round($amount / 1000000, 1) . ' Mio.';
                if ($amount >= 1000) return round($amount / 1000, 1) . 'k';
                return $amount;
            }
        @endphp
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5 relative overflow-hidden">
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-blue-500/10 to-transparent"></div>
            <div class="text-gray-400 text-sm font-medium mb-1">Paket ({{ $planName }})</div>
            
            @if($price === '0,00')
                <div class="text-3xl font-bold text-emerald-400 mb-2">~{{ number_format($estimatedCostThisMonth, 2, ',', '.') }} € <span class="text-sm font-normal text-gray-400 uppercase tracking-widest text-[10px]">Verbrauch</span></div>
            @else
                <div class="text-3xl font-bold text-white mb-2">{{ $price }} € <span class="text-sm font-normal text-gray-400">/ Mon.</span></div>
            @endif
            
            <div class="flex justify-between text-xs text-gray-400 mb-1 mt-2">
                <span>{{ number_format($tokensThisMonth, 0, ',', '.') }} Tokens</span>
                <span>{{ $isUnlimited ? 'Unbegrenzt' : formatTokenSuffix($limit) . ' Limit' }}</span>
            </div>
            <!-- ProgressBar -->
            <div class="w-full bg-gray-700 rounded-full h-1.5">
                <div class="{{ $percent > 90 ? 'bg-red-500' : 'bg-blue-500' }} h-1.5 rounded-full transition-all duration-500" style="width: {{ $isUnlimited ? 100 : $percent }}%"></div>
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
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-[var(--theme-color-15)] to-transparent"></div>
            <div class="text-gray-400 text-[11px] uppercase tracking-widest font-bold mb-3 flex items-center gap-1.5"><i class="bi bi-cpu text-[var(--theme-color)]"></i> Top KI-Tools</div>
            <div class="space-y-1.5 overflow-hidden">
                @forelse($topToolsAllAgents as $t)
                    <div class="flex justify-between items-center text-xs text-gray-300 font-mono">
                        <span class="truncate pr-2 w-32" title="{{ $t->tool_name }}">{{ Str::limit(str_replace('support_', '', $t->tool_name), 12) }}</span>
                        <span class="text-[var(--theme-color)] font-bold bg-gray-900 border border-gray-700 px-1.5 py-0.5 rounded">{{ $t->usage_count }}</span>
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
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('analyticsTrendChart', (data) => ({
            init() {
                if (!this.$refs.chart) return;
                let options = {
                    series: [{ name: 'Tokens', data: data.data }],
                    chart: { type: 'area', height: 350, toolbar: { show: false }, background: 'transparent' },
                    colors: ['{{ $this->themeColorHex }}'],
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', width: 2 },
                    xaxis: { categories: data.product_categories, labels: { style: { colors: '#9ca3af' } }, axisBorder: { show: false }, axisTicks: { show: false } },
                    yaxis: { labels: { style: { colors: '#9ca3af', formatter: (val) => { return Math.round(val) } } } },
                    grid: { borderColor: '#374151', strokeDashArray: 4 },
                    theme: { mode: 'dark' }
                };
                let chart = new ApexCharts(this.$refs.chart, options);
                chart.render();
            }
        }));

        Alpine.data('analyticsDonutChart', (data) => ({
            init() {
                if (!this.$refs.chart) return;
                let options = {
                    series: data.series.length > 0 ? data.series.map(d => Number(d)) : [1],
                    chart: { type: 'donut', height: 300, background: 'transparent' },
                    labels: data.labels.length > 0 ? data.labels : ['Keine Daten'],
                    colors: ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6'],
                    plotOptions: { pie: { donut: { size: '70%' } } },
                    dataLabels: { enabled: false },
                    stroke: { show: true, colors: ['#1f2937'] },
                    legend: { position: 'bottom', labels: { colors: '#9ca3af' } },
                    theme: { mode: 'dark' }
                };
                let chart = new ApexCharts(this.$refs.chart, options);
                chart.render();
            }
        }));

        Alpine.data('analyticsBarChart', (data) => ({
            init() {
                if (!this.$refs.chart) return;
                let options = {
                    series: [{ name: 'Fehler', data: data.data.length > 0 ? data.data : [0] }],
                    chart: { type: 'bar', height: 300, toolbar: { show: false }, background: 'transparent' },
                    colors: ['#ef4444'],
                    plotOptions: { bar: { borderRadius: 4, horizontal: true } },
                    dataLabels: { enabled: false },
                    xaxis: { categories: data.product_categories.length > 0 ? data.product_categories : ['Keine Daten'], labels: { style: { colors: '#9ca3af' } }, axisBorder: { show: false }, axisTicks: { show: false } },
                    yaxis: { labels: { style: { colors: '#9ca3af' } } },
                    grid: { borderColor: '#374151', strokeDashArray: 4 },
                    theme: { mode: 'dark' }
                };
                let chart = new ApexCharts(this.$refs.chart, options);
                chart.render();
            }
        }));
    });
</script>

</div>
