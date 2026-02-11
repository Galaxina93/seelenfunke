<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ expanded: @entangle('showYearlySection') }">
    <div class="flex justify-between items-center p-6 cursor-pointer select-none bg-white hover:bg-gray-50 transition-colors" @click="expanded = !expanded">
        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Jahresübersicht {{ $selectedYear }}
        </h2>
        <svg class="w-5 h-5 text-gray-400 transform transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </div>

    <div x-show="expanded" x-collapse>
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
                        <input type="date" wire:model.live="dateFrom" class="text-xs border-gray-300 rounded-lg">
                        <input type="date" wire:model.live="dateTo" class="text-xs border-gray-300 rounded-lg">
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
                    <th class="p-2 text-left font-bold text-gray-400 uppercase text-xs tracking-wider">Kategorie</th>
                    @foreach(range(1,12) as $m)
                        <th class="p-2 min-w-[60px]">{{ \Carbon\Carbon::create()->month($m)->locale('de')->shortMonthName }}</th>
                    @endforeach
                    <th class="p-2 font-bold text-gray-600 bg-gray-50">Jahr</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">

                @foreach($yearlyMatrix['categories'] as $key => $category)
                    <tr class="hover:bg-gray-50/80 transition-colors cursor-pointer" wire:click="toggleCategory('{{ $key }}')">
                        <td class="p-2 text-left font-bold {{ $category['color'] }} flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full {{ $category['bg'] }}"></div>
                            {{ $category['label'] }}
                            <svg class="w-4 h-4 text-gray-400 transform transition-transform duration-200 {{ in_array($key, $expandedCategories) ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
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
