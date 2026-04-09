<div>
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-serif font-bold text-white tracking-tight flex items-center gap-3">
                <x-heroicon-s-cpu-chip class="w-8 h-8 text-blue-500 drop-shadow-[0_0_8px_rgba(59,130,246,0.8)]" />
                Agenten Control Center
            </h1>
            <p class="text-xs text-gray-400 mt-2 font-medium">Verwalte die künstliche Intelligenz deines Systems. Analysiere Live-Logs, editiere Rollen und weise Agenten neue Fähigkeiten zu.</p>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="mb-8 overflow-x-auto custom-scrollbar">
        <nav class="flex space-x-2 border-b border-gray-800/80 pb-3" aria-label="Tabs">
            @foreach($tabs as $key => $tab)
                <button 
                    wire:click="selectTab('{{ $key }}')"
                    class="
                        flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300
                        {{ $activeTab === $key 
                            ? 'bg-blue-500/10 text-blue-400 border border-blue-500/30 shadow-[inset_0_0_20px_rgba(59,130,246,0.15)] glow-tab' 
                            : 'text-gray-500 hover:text-gray-300 hover:bg-white/5 border border-transparent' 
                        }}
                    "
                >
                    <x-dynamic-component :component="'heroicon-' . ($activeTab === $key ? 's' : 'o') . '-' . $tab['icon']" class="w-4 h-4 {{ $activeTab === $key ? 'animate-pulse-slow' : '' }}" />
                    {{ $tab['name'] }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Dynamic Content Area --}}
    <div class="relative min-h-[500px]">
        {{-- Lade-Indikator, falls Sub-Komponenten beim Tab-Wechsel etwas laden muessen --}}
        <div wire:loading wire:target="selectTab" class="absolute inset-0 bg-gray-950/80 backdrop-blur-sm z-50 flex items-center justify-center rounded-3xl">
            <div class="flex flex-col items-center gap-4 animate-fade-in">
                <div class="w-12 h-12 rounded-full border-4 border-blue-500/20 border-t-blue-500 animate-spin shadow-[0_0_15px_rgba(59,130,246,0.5)]"></div>
                <span class="text-[10px] font-black uppercase tracking-widest text-blue-400 animate-pulse">Lade Terminal-Modul...</span>
            </div>
        </div>

        {{-- Hier werden die Sub-Module geladen --}}
        <div wire:loading.remove wire:target="selectTab" class="animate-fade-in-up">
            @if($activeTab === 'analytics')
                <livewire:shop.ai.ai-analytics />
            @elseif($activeTab === 'roles')
                <livewire:shop.ai.ai-role-manager />
            @elseif($activeTab === 'agents')
                <livewire:shop.ai.ai-agent-manager />
            @elseif($activeTab === 'chat')
                <livewire:shop.ai.ai-chat />
            @elseif($activeTab === 'wiki')
                <livewire:shop.ai.ai-knowledge-base />
            @elseif($activeTab === 'genui')
                <livewire:shop.ai.ai-visualization-registry />
            @endif
        </div>
    </div>

    <!-- ApexCharts CDN Injection -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        document.addEventListener('alpine:init', () => {
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
                            categories: data.product_categories,
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
                    if (data.product_categories.length === 0) {
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
                            categories: data.product_categories,
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
        });
    </script>

    <style>
        .glow-tab {
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.2), inset 0 0 10px rgba(59, 130, 246, 0.1);
        }
        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .custom-scrollbar::-webkit-scrollbar { height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #4b5563; }
    </style>
</div>
