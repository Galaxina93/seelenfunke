@if($stats['product_ranking']->isNotEmpty())
    <div class="bg-white rounded-[2.5rem] md:rounded-[3rem] p-6 md:p-10 shadow-sm border border-slate-100 text-center relative overflow-hidden group">

        {{-- Quality Score Badge mit Erklärung --}}
        @php
            $qs = $stats['shop_quality_score'] ?? 0;
            $qsColor = match(true) {
                $qs >= 75 => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                $qs >= 50 => 'bg-amber-50 text-amber-600 border-amber-100',
                default   => 'bg-rose-50 text-rose-600 border-rose-100',
            };
        @endphp

        {{-- Wrapper mit x-data für den Tooltip --}}
        <div class="absolute top-4 right-12 md:right-14 z-20" x-data="{ showQsInfo: false }">
            <div class="relative flex items-center gap-2 px-3 py-1 rounded-full border {{ $qsColor }} cursor-help transition-transform hover:scale-105"
                 @mouseenter="showQsInfo = true"
                 @mouseleave="showQsInfo = false">

                <span class="text-[10px] font-black uppercase tracking-wider">Quality Score</span>
                <span class="text-sm font-black">{{ $qs }}</span>

                {{-- Das gewünschte kleine Info-Icon --}}
                <div class="ml-1 w-3.5 h-3.5 flex items-center justify-center rounded-full bg-current opacity-20">
                    <i class="solar-info-circle-bold text-[10px] text-white"></i>
                </div>

                {{-- TOOLTIP / POPUP --}}
                <div x-show="showQsInfo"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-2"
                     class="absolute top-full right-0 mt-3 w-64 bg-slate-800/95 backdrop-blur-sm text-white p-4 rounded-2xl shadow-2xl border border-slate-700 z-50 text-left pointer-events-none">

                    <div class="flex items-center gap-2 mb-3 pb-3 border-b border-slate-700/50">
                        <span class="text-lg">📊</span>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Analyse-Zusammensetzung</div>
                            <div class="text-[9px] text-slate-500">Berechnung live aus Finanzdaten</div>
                        </div>
                    </div>

                    <ul class="space-y-2 text-[10px] text-slate-300">
                        <li class="flex justify-between items-center">
                            <span>Profitabilität & Break-Even</span>
                            <span class="font-bold text-emerald-400">40%</span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span>Gewinn-Marge</span>
                            <span class="font-bold text-indigo-400">30%</span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span>Umsatz-Wachstum</span>
                            <span class="font-bold text-blue-400">30%</span>
                        </li>
                    </ul>

                    <div class="mt-3 pt-2 border-t border-slate-700/50 text-[9px] text-slate-500 leading-relaxed italic">
                        Dieser Score indiziert die operative Gesundheit des Shops basierend auf aktuellen KPIs.
                    </div>
                </div>
            </div>
        </div>

        {{-- Genereller Info Button für das Podest --}}
        <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help transition-colors" title="Zeigt die meistverkauften Produkte (Menge) im gewählten Zeitraum.">
            <i class="solar-info-circle-bold-duotone text-xl"></i>
        </div>

        <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
        <h3 class="text-xs md:text-sm font-black text-slate-400 uppercase tracking-[0.2em] md:tracking-[0.3em] mb-8 md:mb-12 relative z-10">Meistverkaufte Unikate (Zeitraum)</h3>

        {{-- Responsive Flex-Container: Spalte auf Mobile, Podest auf Desktop --}}
        <div class="flex flex-col md:flex-row justify-center items-center md:items-end gap-6 md:gap-12 relative z-10">
            @php $ranks = $stats['product_ranking']; @endphp

            {{-- Platz 2 --}}
            @if(isset($ranks[1]))
                <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-2 md:order-1 w-full md:w-auto bg-slate-50/50 md:bg-transparent p-4 md:p-0 rounded-2xl border border-slate-100 md:border-0 group/item">
                    <div class="mb-0 md:mb-3 text-left md:text-center flex-1 md:flex-none">
                        <span class="block text-xs font-bold text-slate-500 truncate max-w-[150px] md:max-w-[120px]">{{ $ranks[1]->product_name }}</span>
                        <span class="text-[10px] font-black text-slate-400 bg-white md:bg-slate-100 px-2 py-0.5 rounded-full border border-slate-100 md:border-0">{{ $ranks[1]->qty }} Stk.</span>
                    </div>
                    <div class="w-12 h-12 md:w-32 md:h-32 bg-slate-200 rounded-xl md:rounded-t-2xl flex items-center justify-center border border-slate-300 shadow-inner group-hover/item:bg-slate-300 transition-colors">
                        <span class="text-xl md:text-4xl font-black text-slate-400 opacity-50">2</span>
                    </div>
                </div>
            @endif

            {{-- Platz 1 (Fokus) --}}
            <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-1 md:order-2 w-full md:w-auto bg-indigo-50/30 md:bg-transparent p-4 md:p-0 rounded-2xl border border-indigo-100 md:border-0 relative group/item">
                <div class="hidden md:block absolute -top-8 left-[38%] -translate-x-1/2 text-3xl animate-bounce">👑</div>
                <div class="mb-0 md:mb-4 text-left md:text-center flex-1 md:flex-none">
                    <div class="md:hidden text-lg inline-block mr-1">👑</div>
                    <span class="block text-sm font-black text-indigo-700 truncate max-w-[180px] md:max-w-[160px]">{{ $ranks[0]->product_name }}</span>
                    <span class="text-xs font-bold text-white bg-indigo-500 px-3 py-1 rounded-full shadow-lg shadow-indigo-200">{{ $ranks[0]->qty }} Stk.</span>
                </div>
                <div class="w-14 h-14 md:w-44 md:h-48 bg-gradient-to-b from-indigo-500 to-indigo-600 rounded-xl md:rounded-t-[2.5rem] flex items-center justify-center shadow-2xl relative overflow-hidden group-hover/item:scale-105 transition-transform">
                    <div class="absolute inset-0 bg-white/10 opacity-0 group-hover/item:opacity-100 transition-opacity"></div>
                    <span class="text-2xl md:text-6xl font-black text-white drop-shadow-md">1</span>
                </div>
            </div>

            {{-- Platz 3 --}}
            @if(isset($ranks[2]))
                <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-3 md:order-3 w-full md:w-auto bg-slate-50/50 md:bg-transparent p-4 md:p-0 rounded-2xl border border-slate-100 md:border-0 group/item">
                    <div class="mb-0 md:mb-3 text-left md:text-center flex-1 md:flex-none">
                        <span class="block text-xs font-bold text-slate-500 truncate max-w-[150px] md:max-w-[120px]">{{ $ranks[2]->product_name }}</span>
                        <span class="text-[10px] font-black text-slate-400 bg-white md:bg-slate-100 px-2 py-0.5 rounded-full border border-slate-100 md:border-0">{{ $ranks[2]->qty }} Stk.</span>
                    </div>
                    <div class="w-10 h-10 md:w-28 md:h-20 bg-slate-100 rounded-xl md:rounded-t-xl flex items-center justify-center border border-slate-200 shadow-inner group-hover/item:bg-slate-200 transition-colors">
                        <span class="text-lg md:text-2xl font-black text-slate-300 opacity-50">3</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
