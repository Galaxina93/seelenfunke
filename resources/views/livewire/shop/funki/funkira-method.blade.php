<div>
    <div class="mb-8">
        <h1 class="text-3xl font-black text-white tracking-tight leading-none mb-3">
            Funkira <span class="text-cyan-400">Methoden & Abdeckung</span>
        </h1>
        <p class="text-sm text-slate-400 max-w-2xl">
            Eine Live-Analyse aller verfügbaren Werkzeuge, die dem KI-Kern aktuell zur Verfügung stehen, sowie ein Abgleich mit potenziell steuerbaren Systembereichen.
        </p>
    </div>

    <!-- Tracking Graphic (Chart) -->
    <div class="mb-12">
        <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
            <i class="bi bi-graph-up-arrow text-cyan-500 max-sm:hidden"></i> KI Werkzeug Tracking (Letzte 7 Tage)
        </h2>
        
        <div class="bg-slate-900/50 backdrop-blur-xl rounded-2xl border border-white/10 p-5 shadow-lg shadow-black/20 w-full overflow-hidden" wire:ignore>
            <div class="h-64 sm:h-80 w-full relative">
                <canvas id="funkiraUsageChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Aktive Werkzeuge (Tools) -->
    <div class="mb-12">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                <i class="bi bi-tools text-cyan-500 max-sm:hidden"></i> Registrierte Werkzeuge ({{ count($methods) }})
            </h2>
            
            <div class="relative w-full sm:w-72">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="bi bi-search text-slate-500"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Werkzeuge durchsuchen..." class="w-full bg-slate-900/50 border border-white/10 rounded-xl py-2 pl-10 pr-4 text-sm text-slate-300 placeholder-slate-500 focus:outline-none focus:border-cyan-500/50 focus:ring-1 focus:ring-cyan-500/50 transition-all shadow-inner">
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($methods as $method)
                <div x-data="{ open: false }" class="bg-slate-900/50 backdrop-blur-xl rounded-2xl border border-white/10 p-5 shadow-lg shadow-black/20 hover:shadow-cyan-900/20 hover:border-cyan-500/30 transition-all group flex flex-col h-full relative overflow-hidden">
                    <!-- Deco Glow -->
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-cyan-500/5 rounded-full blur-2xl group-hover:bg-cyan-500/10 transition-colors pointer-events-none"></div>

                    <div class="flex items-start justify-between mb-3 relative z-10 cursor-pointer select-none" @click="open = !open">
                        <h3 class="font-black text-white text-sm group-hover:text-cyan-400 transition-colors font-mono flex items-center gap-2">
                            {{ $method['name'] }}
                            <i class="bi transition-transform duration-300" :class="open ? 'bi-chevron-up text-cyan-400' : 'bi-chevron-down text-slate-500'"></i>
                        </h3>
                        <div class="flex flex-col items-end gap-1">
                            <span class="bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-[10px] font-bold px-2 py-1 rounded w-fit capitalize h-fit shadow-[0_0_10px_rgba(34,211,238,0.1)]">
                                Aktiv
                            </span>
                            @if($method['usage_count'] > 0)
                                <span class="bg-purple-500/10 border border-purple-500/20 text-purple-400 text-[9px] font-bold px-1.5 py-0.5 rounded w-fit shadow-[0_0_10px_rgba(168,85,247,0.1)] shrink-0">
                                    <i class="bi bi-lightning-charge-fill mr-0.5"></i> {{ $method['usage_count'] }}x
                                </span>
                            @endif
                        </div>
                    </div>
                    
                    <div x-show="open" x-transition class="flex flex-col flex-1">
                        <p class="text-xs text-slate-400 mb-5 flex-1 leading-relaxed relative z-10">
                            {{ $method['description'] }}
                        </p>
                        
                        @if(!empty($method['parameters']))
                            <div class="bg-black/20 rounded-xl p-3 border border-white/5 mt-auto relative z-10">
                                <div class="text-[9px] font-black uppercase text-slate-500 tracking-wider mb-2">Geforderte Parameter</div>
                                <ul class="text-[10px] text-slate-400 space-y-1.5 font-mono">
                                    @foreach($method['parameters'] as $paramName => $paramData)
                                        <li class="flex items-start gap-2">
                                            <span class="text-cyan-400 font-bold shrink-0">{{ $paramName }}:</span>
                                            <span class="text-slate-500 break-words">{{ $paramData['description'] ?? 'Keine Beschreibung' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <div class="bg-black/20 rounded-xl p-2.5 border border-white/5 mt-auto text-center relative z-10">
                                <span class="text-[10px] text-slate-500 italic">Keine Parameter benötigt</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- System Coverage Matrix -->
    <div>
        <h2 class="text-sm font-black text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
            <i class="bi bi-motherboard text-purple-400 max-sm:hidden"></i> System-Coverage Matrix
        </h2>
        
        <div class="bg-slate-900/50 backdrop-blur-xl rounded-3xl border border-white/10 p-1 shadow-lg shadow-black/20 overflow-hidden">
            <div class="divide-y divide-white/5">
                @foreach($systemCoverage as $moduleName => $moduleData)
                    <div class="p-5 sm:p-6 lg:flex lg:items-start lg:gap-12 hover:bg-white/[0.02] transition-colors group">
                        
                        <!-- Module Info -->
                        <div class="lg:w-1/3 mb-6 lg:mb-0 shrink-0">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-slate-400 shrink-0 group-hover:border-white/20 transition-colors">
                                    <i class="bi {{ $moduleData['icon'] }} text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-black text-white text-sm leading-tight">{{ $moduleName }}</h3>
                                    
                                    @php
                                        // Calculate Coverage
                                        $totalFeatures = count($moduleData['features']);
                                        $activeFeatures = count(array_filter($moduleData['features']));
                                        $percentage = $totalFeatures > 0 ? round(($activeFeatures / $totalFeatures) * 100) : 0;
                                        
                                        $colorClass = match(true) {
                                            $percentage >= 80 => 'text-emerald-400',
                                            $percentage >= 40 => 'text-amber-400',
                                            default => 'text-rose-400'
                                        };
                                        $bgClass = match(true) {
                                            $percentage >= 80 => 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]',
                                            $percentage >= 40 => 'bg-amber-500 shadow-[0_0_10px_rgba(245,158,11,0.5)]',
                                            default => 'bg-rose-500 shadow-[0_0_10px_rgba(244,63,94,0.5)]'
                                        };
                                    @endphp
                                    
                                    <div class="flex items-center justify-between mt-1">
                                        <div class="text-[10px] font-black tracking-widest uppercase {{ $colorClass }}">
                                            {{ $percentage }}% Abdeckung
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 mt-3">{{ $moduleData['description'] }}</p>
                            
                            <!-- Mini Progress Bar -->
                            <div class="w-full bg-black/40 h-1.5 rounded-full mt-4 overflow-hidden border border-white/5">
                                <div class="h-full {{ $bgClass }} transition-all duration-1000 relative" style="width: {{ $percentage }}%">
                                    <div class="absolute inset-0 bg-white/20"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Featues List -->
                        <div class="lg:w-2/3 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                            @foreach($moduleData['features'] as $featureName => $hasFeature)
                                <div class="flex items-center gap-3 p-2 rounded-lg {{ $hasFeature ? 'hover:bg-emerald-500/5' : 'hover:bg-rose-500/5' }} transition-colors">
                                    <div class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center {{ $hasFeature ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                        <i class="bi {{ $hasFeature ? 'bi-check' : 'bi-x' }} text-lg"></i>
                                    </div>
                                    <span class="text-xs {{ $hasFeature ? 'text-slate-300 font-medium' : 'text-slate-500' }}">{{ $featureName }}</span>
                                </div>
                            @endforeach
                        </div>
                        
                    </div>
                @endforeach
            </div>
        </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Injection -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            const ctx = document.getElementById('funkiraUsageChart');
            if(!ctx) return;

            const chartDataArray = @json($chartData);
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartDataArray.labels,
                    datasets: [{
                        label: 'Ausgeführte Werkzeuge',
                        data: chartDataArray.data,
                        backgroundColor: 'rgba(6, 182, 212, 0.2)', // Cyan-500
                        borderColor: 'rgba(6, 182, 212, 0.8)',
                        borderWidth: 2,
                        borderRadius: 6,
                        hoverBackgroundColor: 'rgba(6, 182, 212, 0.4)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(15, 23, 42, 0.9)', // slate-900
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1', // slate-300
                            borderColor: 'rgba(6, 182, 212, 0.3)',
                            borderWidth: 1,
                            padding: 10,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' Aufrufe';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false, drawBorder: false },
                            ticks: { color: '#94a3b8', font: { size: 11, family: 'mono' } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                            ticks: { 
                                color: '#94a3b8', 
                                font: { size: 11, family: 'mono' },
                                stepSize: 1
                            }
                        }
                    },
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart'
                    }
                }
            });
        });
    </script>
</div>
