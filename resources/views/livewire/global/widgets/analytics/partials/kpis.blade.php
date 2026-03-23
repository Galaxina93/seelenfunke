<div class="w-full" x-data="{ showDetails: false }">

    {{-- TOP: DER GESUNDHEITS-SCORE & WICHTIGSTE METRIK --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 lg:p-8 shadow-2xl border border-gray-800 flex flex-col md:flex-row items-center justify-between gap-8 md:gap-12 relative overflow-hidden group">
        
        <!-- Score Gauge (Linker Bereich) -->
        <div class="flex items-center gap-6 z-10">
            @php
                $score = $stats['health_score'] ?? 0;
                $colorClass = $score >= 80 ? 'text-emerald-400' : ($score >= 50 ? 'text-amber-400' : 'text-red-400');
                $strokeColor = $score >= 80 ? '#34d399' : ($score >= 50 ? '#fbbf24' : '#f87171');
                $circumference = 2 * pi() * 40;
                $offset = $circumference - ($score / 100) * $circumference;
            @endphp
            <div class="relative w-28 h-28 md:w-32 md:h-32 flex items-center justify-center shrink-0">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="#1f2937" stroke-width="8"></circle>
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $strokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out drop-shadow-[0_0_8px_currentColor]"></circle>
                </svg>
                <div class="absolute flex flex-col items-center justify-center">
                    <span class="text-3xl font-black {{ $colorClass }} drop-shadow-[0_0_10px_currentColor]">{{ $score }}</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Score</span>
                </div>
            </div>
            
            <div class="flex flex-col">
                <h2 class="text-xl md:text-3xl font-serif font-bold text-white mb-1">Shop Health Score</h2>
                <p class="text-[10px] md:text-xs font-bold text-gray-500 max-w-[200px] md:max-w-xs leading-relaxed">Gesundheitswert aus Break-Even, Marge, Trend und Liquidität.</p>
                <div class="mt-4">
                    @include('livewire.global.ai.ask-ai-dropdown', ['actionMethod' => 'startAiRecommendation', 'buttonText' => 'Agent fragen'])
                </div>
            </div>
        </div>

        <!-- Ø Mtl Gewinn (Rechter Bereich) -->
        <div class="flex flex-col items-center md:items-end z-10 border-t md:border-t-0 md:border-l border-gray-800 pt-6 md:pt-0 pl-0 md:pl-12 w-full md:w-auto">
            <span class="text-[10px] md:text-xs font-black uppercase tracking-widest text-gray-500 mb-2">Ø Mtl. Gewinn</span>
            <span class="text-4xl md:text-5xl font-black text-white drop-shadow-[0_0_15px_rgba(255,255,255,0.2)] tracking-tighter">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }} €</span>
            
            <button @click="showDetails = !showDetails" class="mt-6 flex items-center gap-2 px-5 py-2.5 bg-gray-950 border border-gray-700 hover:border-primary/50 text-gray-300 hover:text-white rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all shadow-inner group/btn">
                <span x-text="showDetails ? 'Analyse einklappen' : 'Finanz-Analyse'"></span>
                <i :class="showDetails ? 'bi bi-chevron-up' : 'bi bi-chevron-down'" class="transition-transform group-hover/btn:text-primary"></i>
            </button>
        </div>
    </div>

    @if($aiRecommendation)
    {{-- KI CFO EMPFEHLUNG --}}
    <div class="mt-6 bg-gray-900/50 backdrop-blur-md rounded-[2rem] p-6 lg:p-8 shadow-2xl border border-purple-500/30 relative overflow-hidden group">
        <div class="flex items-center gap-3 mb-4 border-b border-gray-800 pb-4">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400 border border-purple-500/20 shadow-[0_0_15px_rgba(168,85,247,0.2)]">
                <i class="solar-cpu-bold-duotone text-xl"></i>
            </div>
            <h3 class="text-sm font-black text-white uppercase tracking-widest">Virtueller CFO Analyse</h3>
        </div>
        <div class="prose prose-sm prose-invert max-w-none text-gray-300">
            {!! \Illuminate\Support\Str::markdown($aiRecommendation) !!}
        </div>
    </div>
    @endif

    {{-- DETAIL-ANSICHT: DAS 3-SÄULEN DASHBOARD --}}
    <div x-show="showDetails" x-cloak x-collapse.duration.500ms class="mt-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Säule 1: WACHSTUM -->
            <div class="bg-gray-900/50 backdrop-blur-md rounded-[2rem] p-6 border border-gray-800 flex flex-col gap-6 relative overflow-hidden shadow-inner">
                <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/20 shadow-[0_0_15px_rgba(59,130,246,0.2)]">
                        <i class="solar-rocket-bold-duotone text-xl"></i>
                    </div>
                    <h3 class="text-sm font-black text-white uppercase tracking-widest">Wachstum</h3>
                </div>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner group hover:border-blue-500/30 transition-colors">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2" title="{{ $infoTexts['shop_rev'] ?? 'Dein Umsatz aus Produktverkäufen im Filterzeitraum.' }}"><i class="bi bi-exclamation-circle text-gray-500 hover:text-white cursor-help"></i> Shop Umsatz</span>
                        <span class="text-lg font-black text-white">{{ number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') }} €</span>
                    </div>
                    <div class="flex justify-between items-center bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2" title="{{ $infoTexts['trend'] ?? 'Wachstum im Vergleich zum direkten Vorzeitraum.' }}"><i class="bi bi-exclamation-circle text-gray-500 hover:text-white cursor-help"></i> Umsatz-Trend</span>
                        <span class="text-lg font-black {{ ($stats['revenue_growth'] ?? 0) >= 0 ? 'text-emerald-400 drop-shadow-[0_0_10px_rgba(52,211,153,0.3)]' : 'text-red-400' }}">{{ ($stats['revenue_growth'] ?? 0) > 0 ? '+' : '' }}{{ $stats['revenue_growth'] ?? 0 }} %</span>
                    </div>
                    <div class="flex justify-between items-center bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2" title="{{ $infoTexts['prognose'] ?? 'Einnahmen linear aufs Jahr hochgerechnet.' }}"><i class="bi bi-exclamation-circle text-gray-500 hover:text-white cursor-help"></i> Prognose (Jahr)</span>
                        <span class="text-lg font-black text-purple-400">{{ number_format($stats['projected_year'] ?? 0, 0, ',', '.') }} €</span>
                    </div>
                </div>
            </div>

            <!-- Säule 2: EFFIZIENZ -->
            <div class="bg-gray-900/50 backdrop-blur-md rounded-[2rem] p-6 border border-gray-800 flex flex-col gap-6 relative overflow-hidden shadow-inner">
                <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20 shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                        <i class="solar-wallet-money-bold-duotone text-xl"></i>
                    </div>
                    <h3 class="text-sm font-black text-white uppercase tracking-widest">Effizienz</h3>
                </div>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2" title="{{ $infoTexts['marge'] ?? 'Wieviel Prozent des Umsatzes als Gewinn (nach Kosten) hängen bleibt.' }}"><i class="bi bi-exclamation-circle text-gray-500 hover:text-white cursor-help"></i> Gewinn-Marge</span>
                        <span class="text-lg font-black text-emerald-400 drop-shadow-[0_0_10px_rgba(52,211,153,0.3)]">{{ $stats['margin'] ?? 0 }} %</span>
                    </div>
                    <div class="flex justify-between items-center bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2" title="{{ $infoTexts['avg_profit'] ?? 'Der bereinigte Reingewinn (Einnahmen abzgl. aller Kosten).' }}"><i class="bi bi-exclamation-circle text-gray-500 hover:text-white cursor-help"></i> Ø Mtl. Gewinn</span>
                        <span class="text-lg font-black text-white">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }} €</span>
                    </div>
                </div>
            </div>

            <!-- Säule 3: SICHERHEIT -->
            <div class="bg-gray-900/50 backdrop-blur-md rounded-[2rem] p-6 border border-gray-800 flex flex-col gap-6 relative overflow-hidden shadow-inner" x-data="{ showCosts: false }">
                <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 border border-amber-500/20 shadow-[0_0_15px_rgba(245,158,11,0.2)]">
                        <i class="solar-shield-check-bold-duotone text-xl"></i>
                    </div>
                    <h3 class="text-sm font-black text-white uppercase tracking-widest">Sicherheit</h3>
                </div>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2" title="{{ $infoTexts['break_even'] ?? 'Wieviel Umsatz im Monat erwirtschaftet werden muss, um auf 0 zu kommen (ohne Verlust).' }}"><i class="bi bi-exclamation-circle text-gray-500 hover:text-white cursor-help"></i> Break-Even Point</span>
                        <span class="text-lg font-black text-amber-400 drop-shadow-[0_0_10px_rgba(245,158,11,0.3)]">{{ number_format($stats['break_even_monthly'] ?? 0, 0, ',', '.') }} €</span>
                    </div>
                    
                    @php
                        $hasPending = ($stats['pending_invoices']['sum'] ?? 0) > 0;
                        $pendingCount = $stats['pending_invoices']['count'] ?? 0;
                    @endphp
                    <div class="flex justify-between items-center bg-gray-950 p-4 rounded-xl border {{ $hasPending ? 'border-red-500/40 bg-red-900/10 shadow-[0_0_15px_rgba(239,68,68,0.2)]' : 'border-gray-800 shadow-inner' }}">
                        <span class="text-[10px] font-bold uppercase tracking-widest flex items-center gap-2 {{ $hasPending ? 'text-red-300' : 'text-gray-400' }}" title="{{ $infoTexts['offene'] ?? 'Wert aller Bestellungen, die auf Rechnung gekauft, aber noch nicht bezahlt wurden.' }}">
                            <i class="bi bi-exclamation-circle text-gray-500 hover:text-white cursor-help"></i> Offene Posten
                        </span>
                        <div class="text-right">
                            <span class="text-lg font-black {{ $hasPending ? 'text-red-400 drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500' }}">{{ number_format($stats['pending_invoices']['sum'] ?? 0, 0, ',', '.') }} €</span>
                            @if($pendingCount > 0)
                                <span class="text-[8px] text-gray-500 font-black tracking-widest block">({{ $pendingCount }} {{ $pendingCount == 1 ? 'Rechnung' : 'Rechnungen' }})</span>
                            @endif
                        </div>
                    </div>

                    <!-- Kosten Dropdown (Versteckt Details) -->
                    <div class="border-t border-gray-800 pt-4 mt-2">
                        <button @click="showCosts = !showCosts" class="w-full flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition-colors bg-gray-950 px-3 py-2 rounded-xl border border-gray-800">
                            <span>Kosten & Fix-Einnahmen Details</span>
                            <i :class="showCosts ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                        </button>
                        
                        <div x-show="showCosts" x-cloak x-collapse class="space-y-2 mt-3">
                            <div class="flex justify-between items-center px-2 py-1" title="{{ $infoTexts['fix_inc'] ?? 'Regelmäßige Einnahmen wie Mieten oder Gehälter.' }}">
                                <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest flex items-center gap-1.5"><i class="bi bi-exclamation-circle hover:text-white cursor-help"></i> Fix-Einnahmen</span>
                                <span class="text-xs font-black text-emerald-400">{{ number_format($stats['fixed_income_total'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center px-2 py-1" title="{{ $infoTexts['fix_priv'] ?? 'Regelmäßige private Ausgaben (Miete, Versicherungen, Unterhalt).' }}">
                                <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest flex items-center gap-1.5"><i class="bi bi-exclamation-circle hover:text-white cursor-help"></i> Fixkosten (Privat)</span>
                                <span class="text-xs font-black text-pink-400">{{ number_format($stats['fixed_expenses_priv'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center px-2 py-1" title="{{ $infoTexts['fix_bus'] ?? 'Regelmäßige geschäftliche Ausgaben (Server, Software, Miete).' }}">
                                <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest flex items-center gap-1.5"><i class="bi bi-exclamation-circle hover:text-white cursor-help"></i> Fixkosten (Gewerbe)</span>
                                <span class="text-xs font-black text-rose-400">{{ number_format($stats['fixed_expenses_gew'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center px-2 py-1" title="{{ $infoTexts['variabel'] ?? 'Einmalige Ausgaben und Sonderausgaben ohne festes Intervall.' }}">
                                <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest flex items-center gap-1.5"><i class="bi bi-exclamation-circle hover:text-white cursor-help"></i> Variabel</span>
                                <span class="text-xs font-black text-orange-400">{{ number_format($stats['variable_expenses'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
