<div>
    <div class="min-h-screen bg-transparent pb-20 font-sans text-gray-300">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        {{-- Success Notification --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="fixed bottom-6 right-6 bg-emerald-500/10 border border-emerald-500/30 backdrop-blur-md text-emerald-400 px-6 py-4 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.2)] z-50 flex items-center gap-3 animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-black uppercase tracking-widest text-[10px]">{{ session('success') }}</span>
            </div>
        @endif

        {{-- Header & Datumswähler --}}
        <div class="bg-gray-900/80 backdrop-blur-md shadow-2xl border-b border-gray-800 sticky top-0 z-30 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 py-4 md:py-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-white flex items-center gap-3 tracking-tight">
                    <div class="p-2.5 bg-primary/10 border border-primary/20 shadow-inner rounded-xl text-primary shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span>Auswertung & Übersicht</span>
                </h1>

                <div class="flex items-center gap-2 sm:gap-3 bg-gray-950 p-1.5 rounded-2xl border border-gray-800 shadow-inner w-full sm:w-auto">
                    {{-- Monat --}}
                    <div class="relative group flex-1 sm:flex-none">
                        <select wire:model.live="selectedMonth"
                                class="appearance-none w-full bg-transparent pl-4 pr-10 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest text-gray-400 hover:bg-gray-900 hover:text-white focus:bg-black focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all cursor-pointer border-none outline-none sm:w-40 shadow-inner">
                            @foreach(range(1,12) as $m)
                                <option value="{{ $m }}" class="bg-gray-900">{{ \Carbon\Carbon::create()->month($m)->locale('de')->monthName }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-500 group-hover:text-primary transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="h-8 w-px bg-gray-800"></div>
                    {{-- Jahr --}}
                    <div class="relative group flex-1 sm:flex-none">
                        <select wire:model.live="selectedYear"
                                class="appearance-none w-full bg-transparent pl-4 pr-10 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest text-gray-400 hover:bg-gray-900 hover:text-white focus:bg-black focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all cursor-pointer border-none outline-none sm:w-28 shadow-inner">
                            @foreach(range(date('Y')-2, date('Y')+2) as $y)
                                <option value="{{ $y }}" class="bg-gray-900">{{ $y }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-500 group-hover:text-primary transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="h-8 w-px bg-gray-800 hidden sm:block"></div>

                    {{-- Brutto/Netto Toggle --}}
                    <label class="hidden sm:inline-flex items-center cursor-pointer group px-3 py-1.5 rounded-xl hover:bg-gray-900 transition-colors" title="Wechsel zwischen Brutto- und Nettoansicht">
                        <input type="checkbox" wire:model.live="isNet" class="sr-only peer">
                        <div class="relative w-9 h-5 bg-gray-900 border border-gray-700 peer-focus:outline-none rounded-full peer peer-checked:bg-primary/20 peer-checked:border-primary/50 after:content-[''] after:absolute after:top-[1px] after:start-[1px] after:bg-gray-500 peer-checked:after:bg-primary after:border-gray-500 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full shadow-inner peer-checked:after:border-white"></div>
                        <span class="ms-2 text-[10px] font-black uppercase tracking-widest text-gray-400 group-hover:text-white transition-colors w-[50px]">
                            {{ $isNet ? 'Netto' : 'Brutto' }}
                        </span>
                    </label>
                </div>
            </div>
            
            {{-- Mobile Brutto/Netto Toggle --}}
            <div class="sm:hidden px-4 pb-4 flex justify-end">
                <label class="inline-flex items-center cursor-pointer group px-3 py-2 bg-gray-950 rounded-xl border border-gray-800 shadow-inner">
                    <input type="checkbox" wire:model.live="isNet" class="sr-only peer">
                    <div class="relative w-9 h-5 bg-gray-900 border border-gray-700 peer-focus:outline-none rounded-full peer peer-checked:bg-primary/20 peer-checked:border-primary/50 after:content-[''] after:absolute after:top-[1px] after:start-[1px] after:bg-gray-500 peer-checked:after:bg-primary after:border-gray-500 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full shadow-inner peer-checked:after:border-white"></div>
                    <span class="ms-2 text-[10px] font-black uppercase tracking-widest text-gray-400 w-[50px]">
                        {{ $isNet ? 'Netto Ansicht' : 'Brutto Ansicht' }}
                    </span>
                </label>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 mt-8 md:mt-12 space-y-8 md:space-y-12 animate-fade-in-up" style="animation-delay: 100ms;">

            {{-- Section 1: Header Stats + Schnellerfassung --}}
            <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden relative group">
                <div class="absolute top-0 left-0 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[80px] -translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>

                {{-- OBERER BEREICH: STATISTIKEN --}}
                <div class="p-6 md:p-10 border-b border-gray-800 relative z-10 bg-gray-950/50">
                    {{-- Export Button (Jetzt flexibel und nicht mehr absolut schwebend) --}}
                    <div class="flex justify-end mb-8">
                        <button wire:click="downloadTaxExport"
                                class="w-full md:w-auto flex items-center justify-center gap-2 bg-gray-800 border border-gray-700 text-gray-300 px-5 py-3 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-gray-700 hover:text-white hover:border-gray-500 shadow-inner transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Export {{ $selectedMonth }}/{{ $selectedYear }}
                        </button>
                    </div>

                    <div class="flex flex-col xl:flex-row items-center justify-between gap-10">
                        {{-- Linke Seite der Stats: Hauptzahl --}}
                        <div class="flex-1 text-center xl:text-left w-full">
                            <div class="flex flex-col sm:flex-row items-center justify-center xl:justify-start gap-4 mb-3">
                                <span class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] bg-gray-950 px-3 py-1 rounded-md border border-gray-800">Frei Verfügbar</span>

                                <label class="inline-flex items-center cursor-pointer group bg-gray-900/50 px-3 py-1.5 rounded-full border border-gray-800 shadow-inner hover:bg-gray-800 transition-colors">
                                    <input type="checkbox" wire:model.live="excludeSpecialExpenses" class="sr-only peer">
                                    <div class="relative w-8 h-4 bg-gray-950 border border-gray-700 peer-focus:outline-none rounded-full peer peer-checked:bg-emerald-500/20 peer-checked:border-emerald-500/50 after:content-[''] after:absolute after:top-[1px] after:start-[1px] after:bg-gray-500 peer-checked:after:bg-emerald-400 after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-[14px]"></div>
                                    <span class="ms-2 text-[9px] font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-300 transition-colors select-none">Ohne Sonderausgaben</span>
                                </label>
                            </div>

                            @php
                                $mainValue = $excludeSpecialExpenses ? ($stats['total_budget'] + $stats['fixed_expenses']) : $stats['available'];
                            @endphp

                            <div class="text-5xl md:text-7xl font-serif font-black tracking-tighter mb-2 {{ $mainValue >= 0 ? 'text-emerald-400 drop-shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'text-red-400 drop-shadow-[0_0_15px_rgba(239,68,68,0.2)]' }}">
                                {{ number_format($mainValue, 2, ',', '.') }}&nbsp;€
                            </div>

                            <p class="text-xs text-gray-500 font-medium">Inkl. Shop-Umsatz: <span class="font-bold text-primary bg-primary/10 px-2 py-0.5 rounded ml-1 border border-primary/20">+&nbsp;{{ number_format($stats['shop_income'], 2, ',', '.') }}&nbsp;€</span></p>
                        </div>

                        {{-- Rechte Seite der Stats --}}
                        <div class="flex flex-col sm:flex-row gap-4 sm:gap-6 text-center sm:text-left w-full xl:w-auto overflow-x-auto no-scrollbar pb-2">
                            <div class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner flex-1 min-w-[150px]">
                                <div class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Monatsbudget</div>
                                <div class="text-xl sm:text-2xl font-bold text-white">+&nbsp;{{ number_format($stats['total_budget'], 2, ',', '.') }}&nbsp;€</div>
                            </div>

                            <div class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner flex-1 min-w-[150px]">
                                <div class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Fixkosten</div>
                                <div class="text-xl sm:text-2xl font-bold text-rose-400">{{ number_format($stats['fixed_expenses'], 2, ',', '.') }}&nbsp;€</div>
                            </div>

                            <div class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner flex-1 min-w-[150px] transition-all duration-300 {{ $excludeSpecialExpenses ? 'opacity-40 grayscale-[0.8] scale-95 border-dashed' : 'opacity-100' }}">
                                <div class="text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2">Sonderausgaben</div>
                                <div class="text-xl sm:text-2xl font-bold text-orange-400">{{ number_format($stats['special_expenses'], 2, ',', '.') }}&nbsp;€</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- HIER WIRD DIE NEUE KOMPONENTE GELADEN --}}
                <div class="bg-gray-900/30">
                    <livewire:shop.financial.financial-quick-entry />
                </div>
            </div>

            {{-- Section 2: Jahresübersicht + Bar Chart --}}
            <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden relative">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-6 md:p-8 bg-gray-950 border-b border-gray-800 shadow-inner gap-4">
                    <h2 class="text-lg md:text-xl font-serif font-bold text-white flex items-center gap-3">
                        <div class="p-2 bg-gray-800 rounded-xl text-gray-400 shadow-inner">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        Jahresübersicht <span class="text-primary">{{ $selectedYear }}</span>
                    </h2>
                </div>

                <div>
                    {{-- Chart Bereich für Jahresübersicht --}}
                    <div class="p-6 md:p-8">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                            <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em]">Entwicklung Einnahmen vs. Ausgaben</h3>
                            <div class="flex flex-wrap gap-3 items-center w-full md:w-auto">
                                <select wire:model.live="chartFilter" class="flex-1 md:flex-none bg-gray-950 border border-gray-800 text-gray-400 text-[10px] font-black uppercase tracking-widest rounded-xl p-3 focus:ring-2 focus:ring-primary/50 outline-none shadow-inner cursor-pointer">
                                    <option value="last_12_months">Letzte 12 Monate</option>
                                    <option value="this_year">Dieses Jahr</option>
                                    <option value="custom">Benutzerdefiniert</option>
                                </select>
                                @if($chartFilter === 'custom')
                                    <input type="date" wire:model.live="dateFrom" class="flex-1 md:flex-none bg-gray-950 border border-gray-800 text-gray-400 text-xs rounded-xl p-2.5 focus:ring-2 focus:ring-primary/50 outline-none shadow-inner [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)]">
                                    <input type="date" wire:model.live="dateTo" class="flex-1 md:flex-none bg-gray-950 border border-gray-800 text-gray-400 text-xs rounded-xl p-2.5 focus:ring-2 focus:ring-primary/50 outline-none shadow-inner [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)]">
                                @endif
                            </div>
                        </div>
                        <div class="relative h-64 md:h-80 w-full" wire:ignore>
                            <canvas id="yearlyBarChart"></canvas>
                        </div>
                    </div>

                    <div class="border-t border-gray-800 overflow-x-auto no-scrollbar pb-2">
                        <table class="w-full text-xs text-right whitespace-nowrap border-collapse">
                            <thead class="bg-gray-950/80 text-gray-500 font-bold border-b border-gray-800">
                            <tr>
                                <th class="p-4 text-left font-black uppercase tracking-[0.2em] text-[9px]">
                                    Kategorie
                                </th>
                                @foreach(range(1,12) as $m)
                                    <th class="p-4 min-w-[70px] uppercase tracking-widest text-[9px]">{{ \Carbon\Carbon::create()->month($m)->locale('de')->shortMonthName }}</th>
                                @endforeach
                                <th class="p-4 font-black uppercase tracking-widest text-[9px] text-primary bg-gray-900 shadow-inner">Jahr</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50">

                            @foreach($yearlyMatrix['categories'] as $key => $category)
                                <tr class="hover:bg-gray-800/30 transition-colors cursor-pointer group"
                                    wire:click="toggleCategory('{{ $key }}')">
                                    <td class="p-4 text-left font-bold {{ $category['color'] }} flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full {{ $category['bg'] }} shadow-[0_0_8px_currentColor]"></div>
                                        {{ $category['label'] }}
                                        <svg class="w-3.5 h-3.5 text-gray-600 group-hover:text-gray-400 transform transition-transform duration-300 {{ in_array($key, $expandedCategories) ? 'rotate-180 text-primary' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </td>
                                    @foreach(range(1,12) as $m)
                                        <td class="p-4 text-gray-400 font-medium font-mono">{{ number_format($category['months'][$m], 0, ',', '.') }}</td>
                                    @endforeach
                                    <td class="p-4 font-bold bg-gray-900 shadow-inner text-white font-mono">{{ number_format($category['year_sum'], 0, ',', '.') }}</td>
                                </tr>

                                @if(in_array($key, $expandedCategories))
                                    @foreach($category['items'] as $item)
                                        <tr class="bg-gray-950/50 text-[11px] text-gray-500 animate-fade-in-down border-b border-gray-800/30 last:border-b-0">
                                            <td class="p-3 pl-10 text-left border-l-2 {{ str_replace('bg-', 'border-', $category['bg']) }} font-medium">{{ $item['name'] }}</td>
                                            @foreach(range(1,12) as $m)
                                                <td class="p-3 font-mono">{{ $item['months'][$m] != 0 ? number_format($item['months'][$m], 0, ',', '.') : '-' }}</td>
                                            @endforeach
                                            <td class="p-3 font-bold text-gray-400 bg-gray-900/30 font-mono">{{ number_format($item['year_sum'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach

                            <tr class="bg-gray-900 border-t-2 border-gray-700">
                                <td class="p-5 text-left uppercase text-[10px] font-black tracking-[0.2em] text-white">Bilanz</td>
                                @foreach(range(1,12) as $m)
                                    <td class="p-5 font-mono font-bold text-sm {{ $yearlyMatrix['totals']['months'][$m] >= 0 ? 'text-emerald-400 drop-shadow-[0_0_5px_currentColor]' : 'text-red-400 drop-shadow-[0_0_5px_currentColor]' }}">
                                        {{ number_format($yearlyMatrix['totals']['months'][$m], 0, ',', '.') }}
                                    </td>
                                @endforeach
                                <td class="p-5 text-white font-mono font-black text-base bg-gray-950 border-l border-gray-800 shadow-inner">
                                    {{ number_format($yearlyMatrix['totals']['year_sum'], 0, ',', '.') }}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        {{-- Chart Scripts --}}
        @include('livewire.shop.financial.financial-evaluation.partials.chart_scripts')
    </div>
</div>
