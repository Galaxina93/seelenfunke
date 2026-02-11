<div>
    <div class="min-h-screen bg-gray-50 pb-20 font-sans text-gray-800">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        {{-- Success Notification --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2 animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        @endif

        {{-- Header & Datumswähler --}}
        <div
            class="bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100 sticky top-0 z-30 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3 tracking-tight">
                    <div class="p-2 bg-primary/10 rounded-xl text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span>Auswertung & Übersicht</span>
                </h1>

                <div class="flex items-center gap-3 bg-white p-1 rounded-xl border border-gray-200 shadow-sm">
                    {{-- Monat --}}
                    <div class="relative group">
                        <select wire:model.live="selectedMonth"
                                class="appearance-none bg-transparent pl-4 pr-10 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer border-none outline-none w-32">
                            @foreach(range(1,12) as $m)
                                <option
                                    value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->locale('de')->monthName }}</option>
                            @endforeach
                        </select>
                        <div
                            class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-400 group-hover:text-primary transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="h-6 w-px bg-gray-200"></div>
                    {{-- Jahr --}}
                    <div class="relative group">
                        <select wire:model.live="selectedYear"
                                class="appearance-none bg-transparent pl-4 pr-10 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer border-none outline-none w-24">
                            @foreach(range(date('Y')-2, date('Y')+2) as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                        <div
                            class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-400 group-hover:text-primary transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 mt-8 space-y-8">

            {{-- Section 1: Header Stats + Schnellerfassung --}}
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">

                {{-- OBERER BEREICH: STATISTIKEN --}}
                <div class="p-8 border-b border-gray-100 relative">
                    {{-- Export Button oben rechts --}}
                    <div class="absolute top-2 right-6">
                        <button wire:click="downloadTaxExport"
                                class="flex items-center gap-2 bg-gradient-to-r from-gray-800 to-gray-700 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider hover:shadow-lg transition transform hover:-translate-y-0.5">
                            <svg xmlns="[http://www.w3.org/2000/svg](http://www.w3.org/2000/svg)" class="h-4 w-4"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Export {{ $selectedMonth }}/{{ $selectedYear }}
                        </button>
                    </div>

                    <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                        {{-- Linke Seite der Stats: Hauptzahl --}}
                        <div class="flex-1 text-center md:text-left">
                            <div class="flex items-center justify-center md:justify-start gap-3 mb-1">
                                <span
                                    class="text-sm font-bold text-gray-400 uppercase tracking-wider">Frei Verfügbar</span>
                                <label class="inline-flex items-center cursor-pointer group">
                                    <input type="checkbox" wire:model.live="excludeSpecialExpenses"
                                           class="sr-only peer">
                                    <div
                                        class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:bg-teal-500 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                                    <span
                                        class="ms-2 text-xs text-gray-400 group-hover:text-gray-600 transition select-none">Ohne Sonderausgaben</span>
                                </label>
                            </div>
                            <div
                                class="text-5xl font-extrabold tracking-tight {{ ($excludeSpecialExpenses ? ($stats['total_budget'] + $stats['fixed_expenses']) : $stats['available']) >= 0 ? 'text-teal-600' : 'text-red-500' }}">
                                {{ number_format($excludeSpecialExpenses ? ($stats['total_budget'] + $stats['fixed_expenses']) : $stats['available'], 2, ',', '.') }}
                                €
                            </div>
                            <p class="text-xs text-gray-400 mt-2">Inkl. Shop-Umsatz: <span
                                    class="font-bold text-teal-600">+{{ number_format($stats['shop_income'], 2, ',', '.') }} €</span>
                            </p>
                        </div>

                        {{-- Rechte Seite der Stats --}}
                        <div class="flex flex-col sm:flex-row gap-8 text-center sm:text-left">
                            <div>
                                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">
                                    Monatsbudget
                                </div>
                                <div class="text-xl font-bold text-emerald-600">
                                    + {{ number_format($stats['total_budget'], 2, ',', '.') }} €
                                </div>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Fixkosten
                                </div>
                                <div
                                    class="text-xl font-bold text-rose-500">{{ number_format($stats['fixed_expenses'], 2, ',', '.') }}
                                    €
                                </div>
                            </div>
                            <div
                                class="transition-opacity duration-300 {{ $excludeSpecialExpenses ? 'opacity-30 grayscale' : 'opacity-100' }}">
                                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">
                                    Sonderausgaben
                                </div>
                                <div
                                    class="text-xl font-bold text-orange-500">{{ number_format($stats['special_expenses'], 2, ',', '.') }}
                                    €
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- HIER WIRD DIE NEUE KOMPONENTE GELADEN --}}
                {{--<livewire:shop.financial.financial-quick-entry />--}}
            </div>

            {{-- Section 2: Jahresübersicht + Bar Chart --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div
                    class="flex justify-between items-center p-6 select-none bg-white hover:bg-gray-50 transition-colors">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Jahresübersicht {{ $selectedYear }}
                    </h2>
                </div>

                <div>
                    {{-- Chart Bereich für Jahresübersicht --}}
                    <div class="p-6 bg-white border-t border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-gray-700">Entwicklung Einnahmen vs. Ausgaben</h3>
                            <div class="flex gap-2 items-center">
                                <select wire:model.live="chartFilter" class="text-xs border-gray-300 rounded-lg">
                                    <option value="last_12_months">Letzte 12 Monate</option>
                                    <option value="this_year">Dieses Jahr</option>
                                    <option value="custom">Benutzerdefiniert</option>
                                </select>
                                @if($chartFilter === 'custom')
                                    <input type="date" wire:model.live="dateFrom"
                                           class="text-xs border-gray-300 rounded-lg">
                                    <input type="date" wire:model.live="dateTo"
                                           class="text-xs border-gray-300 rounded-lg">
                                @endif
                            </div>
                        </div>
                        <div class="relative h-64 w-full" wire:ignore>
                            <canvas id="yearlyBarChart"></canvas>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 overflow-x-auto animate-fade-in-down">
                        <table class="w-full text-sm text-right whitespace-nowrap">
                            <thead class="bg-gray-50/50 text-gray-500 font-semibold border-b border-gray-100">
                            <tr>
                                <th class="p-2 text-left font-bold text-gray-400 uppercase text-xs tracking-wider">
                                    Kategorie
                                </th>
                                @foreach(range(1,12) as $m)
                                    <th class="p-2 min-w-[60px]">{{ \Carbon\Carbon::create()->month($m)->locale('de')->shortMonthName }}</th>
                                @endforeach
                                <th class="p-2 font-bold text-gray-600 bg-gray-50">Jahr</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">

                            @foreach($yearlyMatrix['categories'] as $key => $category)
                                <tr class="hover:bg-gray-50/80 transition-colors cursor-pointer"
                                    wire:click="toggleCategory('{{ $key }}')">
                                    <td class="p-2 text-left font-bold {{ $category['color'] }} flex items-center gap-2">
                                        <div class="w-2.5 h-2.5 rounded-full {{ $category['bg'] }}"></div>
                                        {{ $category['label'] }}
                                        <svg
                                            class="w-4 h-4 text-gray-400 transform transition-transform duration-200 {{ in_array($key, $expandedCategories) ? 'rotate-180' : '' }}"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </td>
                                    @foreach(range(1,12) as $m)
                                        <td class="p-2 text-gray-700 font-medium">{{ number_format($category['months'][$m], 0, ',', '.') }}</td>
                                    @endforeach
                                    <td class="p-2 font-bold bg-gray-50/50 text-gray-800">{{ number_format($category['year_sum'], 0, ',', '.') }}</td>
                                </tr>

                                @if(in_array($key, $expandedCategories))
                                    @foreach($category['items'] as $item)
                                        <tr class="bg-gray-50/30 text-xs text-gray-500 animate-fade-in-down">
                                            <td class="p-2 pl-8 text-left border-l-4 {{ str_replace('bg-', 'border-', $category['bg']) }}">{{ $item['name'] }}</td>
                                            @foreach(range(1,12) as $m)
                                                <td class="p-2">{{ $item['months'][$m] != 0 ? number_format($item['months'][$m], 0, ',', '.') : '-' }}</td>
                                            @endforeach
                                            <td class="p-2 font-semibold text-gray-600">{{ number_format($item['year_sum'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach

                            <tr class="bg-gray-100 font-bold border-t-2 border-gray-200">
                                <td class="p-2 text-left uppercase text-xs tracking-wider text-gray-600">Bilanz</td>
                                @foreach(range(1,12) as $m)
                                    <td class="p-2 {{ $yearlyMatrix['totals']['months'][$m] >= 0 ? 'text-teal-600' : 'text-red-500' }}">
                                        {{ number_format($yearlyMatrix['totals']['months'][$m], 0, ',', '.') }}
                                    </td>
                                @endforeach
                                <td class="p-2 text-gray-800 bg-gray-200 border-l border-gray-300">
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
