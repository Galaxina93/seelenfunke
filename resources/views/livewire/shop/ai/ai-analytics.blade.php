<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80;">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8"
         x-data="aiAnalyticsCharts()"
         x-init="initCharts()"
         @ai-analytics-updated.window="$nextTick(() => initCharts())"
         @theme-changed.window="initCharts()">

        <div id="ai-analytics-data-bridge" class="hidden" data-dailytokens='@json($dailyTokens)'></div>

        {{-- Header & Controls --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-900/80 backdrop-blur-md p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-xl">
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl border border-[var(--theme-color-20)] bg-[var(--theme-color-10)] text-[var(--theme-color)]">
                    <x-heroicon-o-cpu-chip class="w-6 h-6" />
                </span>
                <h1 class="text-2xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    KI-Telemetrie & Metriken
                    <x-heroicon-o-information-circle
                        class="w-5 h-5 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors"
                        title="Echtzeit-Überwachung von Token-Verbrauch, Vertex-AI-Monatskosten, Antwort-Latenzen, Erfolgsraten und Werkzeug-Aufrufen aller internen Agenten." />
                </h1>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Vertex AI Aktiv
                </span>

                <label for="aiDateRangeSelect" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Zeitraum:</label>
                <select id="aiDateRangeSelect" wire:model.live="dateRange" class="bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white text-sm rounded-xl focus:ring-2 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] block px-4 py-2 transition-colors cursor-pointer">
                    <option value="7">Letzte 7 Tage</option>
                    <option value="30">Letzte 30 Tage</option>
                    <option value="90">Letzte 90 Tage</option>
                    <option value="365">Letztes Jahr</option>
                </select>
            </div>
        </div>

        {{-- Top 4 KPI Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

            {{-- Card 1: Tokens Heute --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        Tokens Heute (24h)
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Summe aller Input- und Output-Tokens seit Tagesbeginn (00:00 Uhr)." />
                    </span>
                    <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <x-heroicon-o-bolt class="w-5 h-5" />
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2 flex items-baseline gap-2">
                        {{ \App\Livewire\Shop\Ai\AiAnalytics::formatCompactTokens($tokensToday) }}
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">/ 1 Mio.</span>
                    </h3>
                    <div class="w-full bg-gray-100 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-emerald-500 h-full rounded-full transition-all duration-500" style="width: {{ min(100, ($tokensToday / 1000000) * 100) }}%"></div>
                    </div>
                </div>
            </div>

            {{-- Card 2: Geschätzte Kosten (Monat) --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        Monatskosten ({{ now()->locale('de')->translatedFormat('M') }})
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Modellgenaue Kostenschätzung (Flash, Flash-Lite, Pro, Live) im laufenden Kalendermonat." />
                    </span>
                    <div class="p-2 rounded-xl bg-[var(--theme-color-10)] text-[var(--theme-color)]">
                        <x-heroicon-o-banknotes class="w-5 h-5" />
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2 flex items-baseline gap-2">
                        {{ number_format($estimatedCostThisMonth, 2, ',', '.') }} €
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">/ {{ number_format($monthlyBudgetEur, 2, ',', '.') }} € Budget</span>
                    </h3>
                    @php
                        $totalTokensMonth = $inputTokensThisMonth + $outputTokensThisMonth;
                        $usagePercent = $planTokenLimit > 0
                            ? min(100, ($totalTokensMonth / $planTokenLimit) * 100)
                            : ($monthlyBudgetEur > 0 ? min(100, ($estimatedCostThisMonth / $monthlyBudgetEur) * 100) : 0);
                    @endphp
                    <div class="w-full bg-gray-100 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-[var(--theme-color)] h-full rounded-full transition-all duration-500" style="width: {{ $usagePercent }}%"></div>
                    </div>
                    <div class="flex justify-between items-center text-[10px] text-gray-500 dark:text-gray-400 font-bold tracking-wider uppercase mt-2 gap-2">
                        <span class="truncate">{{ $planDisplayName }}</span>
                        <span class="shrink-0">In {{ \App\Livewire\Shop\Ai\AiAnalytics::formatCompactTokens($inputTokensThisMonth) }} · Out {{ \App\Livewire\Shop\Ai\AiAnalytics::formatCompactTokens($outputTokensThisMonth) }}</span>
                    </div>
                </div>
            </div>

            {{-- Card 3: Ø Latenz --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        Ø Antwortzeit (TTFT)
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Durchschnittliche Inferenz-Antwortzeit in Millisekunden im gewählten Zeitraum." />
                    </span>
                    <div class="p-2 rounded-xl bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400">
                        <x-heroicon-o-clock class="w-5 h-5" />
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        {{ $avgLatency }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">ms</span>
                    </h3>
                    <div class="text-[10px] font-bold uppercase tracking-wider {{ $avgLatency > 3000 ? 'text-rose-500' : ($avgLatency > 1500 ? 'text-amber-500' : 'text-emerald-600 dark:text-emerald-400') }}">
                        {{ $avgLatency > 3000 ? 'Hohe Latenz' : ($avgLatency > 1500 ? 'Normale Last' : 'Schnelle Antwortzeit') }}
                    </div>
                </div>
            </div>

            {{-- Card 4: Erfolgsrate --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        Erfolgsquote
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Anteil fehlerfrei beantworteter KI-Anfragen im gewählten Zeitraum ({{ number_format($totalInferences, 0, ',', '.') }} Calls)." />
                    </span>
                    <div class="p-2 rounded-xl {{ $successRate >= 95 ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400' }}">
                        <x-heroicon-o-shield-check class="w-5 h-5" />
                    </div>
                </div>
                <div>
                    <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        {{ $successRate }} %
                    </h3>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ number_format($totalInferences, 0, ',', '.') }} Inferenzen geprüft
                    </div>
                </div>
            </div>

        </div>

        {{-- Charts & Agent Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Token Verlauf Chart --}}
            <div class="lg:col-span-2 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-serif font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-[var(--theme-color)]" />
                        Token-Verlauf ({{ $dateRange }} Tage)
                    </h2>
                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Tägliches Aufkommen an Input-Prompts und generierten Output-Tokens im gewählten Zeitraum." />
                </div>

                <div class="flex-1 w-full min-h-[300px]" wire:ignore>
                    <div id="tokenChart" class="w-full h-full"></div>
                </div>
            </div>

            {{-- Agenten Aktivität --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-serif font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-user-group class="w-5 h-5 text-[var(--theme-color)]" />
                        Agenten-Auslastung
                    </h2>
                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Aufrufe, durchschnittliche Antwortzeit und Token-Volumen je KI-Agent im gewählten Zeitraum." />
                </div>

                <div class="flex-1 overflow-y-auto space-y-3 pr-1 max-h-[320px]">
                    @forelse($agentUsage as $usage)
                        @php $ag = $usage->agent; @endphp
                        @if($ag)
                            <div class="p-3.5 bg-gray-50 dark:bg-gray-950/60 border border-gray-200/70 dark:border-gray-800 rounded-xl flex items-center justify-between hover:border-[var(--theme-color-30)] transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-2.5 h-2.5 rounded-full shrink-0 {{ \App\Livewire\Shop\Ai\AiAnalytics::resolveAgentDotClass($ag->color) }}"></div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $ag->name }}</div>
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400 font-mono mt-0.5 truncate">{{ $usage->calls }} Calls · Ø {{ round($usage->avg_latency) }}ms</div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0 ml-2">
                                    <div class="text-xs font-black text-emerald-600 dark:text-emerald-400">{{ \App\Livewire\Shop\Ai\AiAnalytics::formatCompactTokens($usage->total_tokens) }}</div>
                                    <div class="text-[9px] uppercase tracking-widest text-gray-400 font-bold">Tokens</div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="text-center py-10 text-gray-400 dark:text-gray-500 text-xs font-medium">
                            Keine Agenten-Aktivität im Zeitraum
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Tools & Error Log --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Meistgenutzte Tools --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-serif font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-[var(--theme-color)]" />
                        Top 10 Werkzeuge (Function Calling)
                    </h2>
                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Meistgenutzte interne Systemfunktionen der KI-Agenten inklusive Fehlerzähler." />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800 text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                <th class="pb-3 font-black">Werkzeug</th>
                                <th class="pb-3 font-black text-right">Aufrufe</th>
                                <th class="pb-3 font-black text-right">Fehler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60 text-xs">
                            @forelse($topTools as $tool)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                    <td class="py-3 font-mono text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                        <x-heroicon-o-code-bracket class="w-4 h-4 text-[var(--theme-color)] shrink-0" />
                                        {{ $tool->tool_name }}
                                    </td>
                                    <td class="py-3 text-right font-bold text-gray-900 dark:text-white">{{ number_format($tool->uses, 0, ',', '.') }}</td>
                                    <td class="py-3 text-right">
                                        @if($tool->errors > 0)
                                            <span class="px-2 py-0.5 rounded bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 font-bold border border-rose-200 dark:border-rose-500/20">{{ $tool->errors }}</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-600">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-8 text-center text-gray-400 dark:text-gray-500 text-xs">Keine Werkzeug-Aufrufe im Zeitraum</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Letzte Systemfehler --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-serif font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-rose-500" />
                        Inferenz-Fehlerprotokoll
                    </h2>
                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Die letzten 5 fehlgeschlagenen KI-Inferenzaufrufe (z. B. API-Timeouts oder Rate-Limits)." />
                </div>

                <div class="space-y-3">
                    @forelse($recentErrors as $err)
                        <div class="p-3.5 bg-rose-50/60 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-500/20 rounded-xl flex items-start gap-3">
                            <x-heroicon-o-x-circle class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1 gap-2">
                                    <span class="text-xs font-bold text-rose-700 dark:text-rose-300 truncate">{{ $err->agent ? $err->agent->name : 'System' }}</span>
                                    <span class="text-[10px] font-mono text-gray-500 shrink-0">{{ $err->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-[11px] font-mono text-rose-700/80 dark:text-rose-200/80 break-words leading-relaxed">
                                    {{ $err->error_message ?: 'Unbekannter API-Abbruch' }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-10 text-center border border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                            <x-heroicon-o-check-badge class="w-10 h-10 text-emerald-500/60 mb-2" />
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300">0 Inferenz-Fehler im Zeitraum</span>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

    @push('scripts')
    <script src="{{ asset('vendor/apexcharts/apexcharts.min.js') }}"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            window.aiTokenChartInstance = window.aiTokenChartInstance || null;

            Alpine.data('aiAnalyticsCharts', () => ({
                readDailyData() {
                    const bridge = document.getElementById('ai-analytics-data-bridge');
                    if (!bridge) return [];
                    try {
                        return JSON.parse(bridge.getAttribute('data-dailytokens') || '[]');
                    } catch (e) {
                        return [];
                    }
                },
                initCharts() {
                    const el = document.querySelector('#tokenChart');
                    if (!el || typeof ApexCharts === 'undefined') return;

                    if (window.aiTokenChartInstance) {
                        try { window.aiTokenChartInstance.destroy(); } catch (e) {}
                        window.aiTokenChartInstance = null;
                    }
                    el.innerHTML = '';

                    const raw = this.readDailyData();
                    const dates = raw.map(d => d.date);
                    const inputs = raw.map(d => parseInt(d.input_sum || 0, 10));
                    const outputs = raw.map(d => parseInt(d.output_sum || 0, 10));
                    const isDark = document.documentElement.classList.contains('dark');
                    const axisColor = isDark ? '#94a3b8' : '#64748b';
                    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';

                    const options = {
                        series: [
                            { name: 'Input Tokens (Prompts)', data: inputs },
                            { name: 'Output Tokens (Antworten)', data: outputs }
                        ],
                        chart: {
                            type: 'area',
                            height: 300,
                            background: 'transparent',
                            toolbar: { show: false },
                            fontFamily: 'inherit',
                            animations: { enabled: true, easing: 'easeinout', speed: 450 }
                        },
                        colors: ['#10b981', '#c5a059'],
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shadeIntensity: 1,
                                opacityFrom: isDark ? 0.35 : 0.25,
                                opacityTo: 0.02,
                                stops: [0, 95, 100]
                            }
                        },
                        dataLabels: { enabled: false },
                        stroke: { curve: 'smooth', width: 2 },
                        xaxis: {
                            categories: dates,
                            labels: { style: { colors: axisColor, fontSize: '10px' } },
                            axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: {
                            labels: {
                                style: { colors: axisColor, fontSize: '10px' },
                                formatter: (val) => val >= 1000 ? (val / 1000).toFixed(1) + 'k' : Math.round(val)
                            }
                        },
                        grid: {
                            borderColor: gridColor,
                            strokeDashArray: 4,
                            yaxis: { lines: { show: true } }
                        },
                        theme: { mode: isDark ? 'dark' : 'light' },
                        legend: {
                            position: 'top',
                            horizontalAlign: 'right',
                            labels: { colors: axisColor }
                        },
                        noData: {
                            text: 'Keine Token-Daten im gewählten Zeitraum',
                            style: { color: axisColor, fontSize: '12px' }
                        }
                    };

                    window.aiTokenChartInstance = new ApexCharts(el, options);
                    window.aiTokenChartInstance.render();
                }
            }));
        });
    </script>
    @endpush
</div>
