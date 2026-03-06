<div>
    <div class="space-y-6 animate-fade-in-up pb-10">

        {{-- HEADER --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6">
            <div>
                <h2 class="text-2xl font-serif font-bold text-white tracking-wide flex items-center gap-3">
                    <div class="p-2.5 bg-blue-500/10 border border-blue-500/20 rounded-xl text-blue-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div> Liquiditätsplanung (Brutto)
                </h2>
                <p class="text-xs text-gray-400 mt-2 max-w-2xl leading-relaxed">Plane deine Ein- und Auszahlungen. Das System berechnet automatisch, wann sich deine Firma nach Wegfall von ALG 1 und Zuschüssen selbst tragen muss.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button wire:click="removeYear" class="px-4 py-2.5 bg-gray-950 border border-gray-800 hover:border-red-500/50 text-gray-400 hover:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-inner">- Jahr</button>
                <button wire:click="addYear" class="px-4 py-2.5 bg-gray-950 border border-gray-800 hover:border-emerald-500/50 text-gray-400 hover:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-inner">+ Jahr hinzufügen</button>

                <button wire:click="syncLiveData" wire:loading.attr="disabled" class="px-5 py-2.5 bg-gray-950 border border-gray-800 hover:border-blue-500/50 text-gray-400 hover:text-blue-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-inner flex items-center gap-2 group">
                    <svg class="w-4 h-4 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span wire:loading.remove wire:target="syncLiveData">Livedaten Sync</span>
                    <span wire:loading wire:target="syncLiveData">Lade Daten...</span>
                </button>

                <button wire:click="exportPdf" wire:loading.class="opacity-50 grayscale cursor-not-allowed" wire:loading.attr="disabled" class="px-6 py-2.5 bg-primary text-gray-900 hover:text-white active:bg-gray-400 active:opacity-50 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-[0_0_15px_rgba(197,160,89,0.3)] flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Als PDF Exportieren</span>
                </button>
            </div>
        </div>

        @if(session()->has('success'))
            <div x-data="{show: true}" x-show="show" x-init="setTimeout(()=> show = false, 3000)" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-6 py-4 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.2)] flex items-center gap-3 animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/> </svg>
                <span class="font-black uppercase tracking-widest text-[10px]">{{session('success')}}</span>
            </div>
        @endif

        {{-- CHART --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
            <h3 class="text-sm font-serif font-bold text-white mb-4 tracking-wide">Prognostizierter Kassenbestand (Liquiditätskurve)</h3>
            <div wire:ignore class="relative h-64 w-full">
                <canvas id="liquidityChart"></canvas>
            </div>
        </div>

        {{-- GOLDENER JAHRES SELECTOR --}}
        <div class="flex items-center gap-2 bg-gray-950 p-2 rounded-2xl border border-gray-800 shadow-[0_0_20px_rgba(0,0,0,0.5)] w-max mx-auto sm:mx-0">
            @foreach($years as $year)
                <button wire:click="setActiveYear({{ $year }})"
                        class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 {{ $activeYear === $year ? 'bg-gradient-to-br from-[#d4af37] to-[#e2c78d] text-gray-900 shadow-[0_0_20px_rgba(212,175,55,0.4)]' : 'text-gray-500 hover:text-[#d4af37] hover:bg-gray-900' }}">
                    Planungsjahr {{ $year }}
                </button>
            @endforeach
        </div>

        {{-- HAUPTTABELLE (Liquidität) --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar w-full relative">
                <table class="w-full text-left border-collapse min-w-max text-sm">
                    <thead>
                    <tr class="bg-gray-950 border-b border-gray-800">
                        <th class="sticky left-0 bg-gray-950 z-20 px-6 py-4 min-w-[300px] border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="text-[10px] font-black uppercase tracking-widest text-primary">Kategorien (in EUR)</div>
                        </th>
                        <th colspan="12" class="px-4 py-3 text-center border-r border-gray-800 text-[10px] font-black uppercase tracking-widest text-white bg-gray-900/50">
                            Detailansicht: {{$activeYear}}
                        </th>
                    </tr>
                    <tr class="bg-gray-900/50 border-b border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-500 shadow-inner">
                        <th class="sticky left-0 bg-gray-950 z-20 px-6 py-3 border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">Monat</th>
                        @for($m=1; $m<=12; $m++)
                            <th class="px-4 py-3 text-right border-r border-gray-800/50 w-[95px]">{{ sprintf('%02d.%02d', $m, $activeYear % 100) }}</th>
                        @endfor
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-800/50">
                    {{-- BESTAND MONATSANFANG --}}
                    <tr class="bg-gray-900/20 group">
                        <td class="sticky left-0 bg-gray-950 z-20 px-6 py-3 border-r border-gray-800 font-bold text-white shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] group-hover:bg-gray-900 transition-colors"> Kasse/Bank - Bestand Monatsanfang </td>
                        @for($m = 1;$m <= 12;$m++)
                            <td class="px-3 py-3 text-right border-r border-gray-800/50 font-mono whitespace-nowrap">
                                @if($activeYear === $years[0] && $m === 1)
                                    <div class="flex items-center justify-end gap-1">
                                        <input type="number" step="0.01" wire:model.live.blur="startBalance" class="w-full bg-transparent text-right font-mono text-white text-sm focus:bg-gray-950 focus:ring-1 focus:ring-primary rounded px-1 outline-none">
                                        <span class="text-gray-500 text-sm font-bold">&nbsp;€</span>
                                    </div>
                                @else
                                    @php $startVal = $totals[$activeYear][$m]['start'] ?? 0; @endphp
                                    @if($startVal == 0)
                                        <span class="text-[10px] text-gray-600/50 font-normal">0,00&nbsp;€</span>
                                    @else
                                        <span class="text-sm text-gray-400">{{ number_format($startVal, 2, ',', '.') }}&nbsp;€</span>
                                    @endif
                                @endif
                            </td>
                        @endfor
                    </tr>

                    {{-- EINZAHLUNGEN HEADER --}}
                    <tr class="bg-emerald-900/10 border-y-2 border-emerald-500/30">
                        <td class="sticky left-0 bg-[#061810] z-20 px-6 py-3 border-r border-emerald-500/30 font-bold text-emerald-400 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]"> Einzahlungen (brutto), Summe </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $inVal = $totals[$activeYear][$m]['in'] ?? 0; @endphp
                            <td class="px-3 py-3 text-right border-r border-emerald-500/10 font-mono font-bold whitespace-nowrap">
                                @if($inVal == 0)
                                    <span class="text-[10px] text-emerald-900 font-normal">0,00&nbsp;€</span>
                                @else
                                    <span class="text-sm text-emerald-400">{{ number_format($inVal, 2, ',', '.') }}&nbsp;€</span>
                                @endif
                            </td>
                        @endfor
                    </tr>

                    {{-- EINZAHLUNGEN ITEMS --}}
                    @foreach($receiptRows as $key => $rowData)
                        @php
                            $rowSum = 0;
                            for($i=1; $i<=12; $i++) { $rowSum += abs((float) ($data[$activeYear][$i]['in'][$key] ?? 0)); }
                            $isZeroRow = $rowSum == 0;
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition-colors group {{ $isZeroRow ? 'opacity-40' : '' }}">
                            <td class="sticky left-0 bg-gray-950 z-20 px-6 py-2 border-r border-gray-800 {{ $isZeroRow ? 'text-gray-600 line-through' : 'text-gray-300' }} shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] group-hover:bg-gray-900 flex items-center justify-between">
                                <span class="truncate pr-2 {{$key === 'subsidy' && !$isZeroRow ? 'text-primary font-bold' : ''}}">{{$rowData['label']}}</span>
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-600 hover:text-primary cursor-help shrink-0" title="{{$rowData['tooltip']}}" />
                            </td>
                            @for($m = 1;$m <= 12;$m++)
                                <td class="px-2 py-1.5 border-r border-gray-800/50 align-middle whitespace-nowrap">
                                    @php $val = $data[$activeYear][$m]['in'][$key] ?? null; @endphp
                                    <div class="flex items-center justify-end gap-1">
                                        <input type="number" step="0.01" value="{{ $val !== null ? number_format((float)$val, 2, '.', '') : '' }}"
                                               wire:change="updateValue({{ $activeYear }}, {{ $m }}, 'in', '{{ $key }}', $event.target.value)"
                                               class="w-full bg-transparent text-right font-mono focus:bg-gray-950 focus:ring-1 focus:ring-primary rounded px-1 outline-none transition-colors {{ empty($val) ? 'text-[10px] text-gray-600/50 font-normal' : 'text-gray-200 text-sm' }} {{ $isZeroRow ? 'line-through text-gray-600' : '' }}"
                                               placeholder="0.00">
                                        <span class="text-gray-600 {{ empty($val) ? 'text-[10px]' : 'text-sm font-bold text-gray-500' }}">&nbsp;€</span>
                                    </div>
                                </td>
                            @endfor
                        </tr>
                    @endforeach

                    {{-- AUSZAHLUNGEN HEADER --}}
                    <tr class="bg-red-900/10 border-y-2 border-red-500/30">
                        <td class="sticky left-0 bg-[#180a0a] z-20 px-6 py-3 border-r border-red-500/30 font-bold text-red-400 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]"> Auszahlungen (brutto), Summe </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $outVal = $totals[$activeYear][$m]['out'] ?? 0; @endphp
                            <td class="px-3 py-3 text-right border-r border-red-500/10 font-mono font-bold whitespace-nowrap">
                                @if($outVal == 0)
                                    <span class="text-[10px] text-red-900 font-normal">0,00&nbsp;€</span>
                                @else
                                    <span class="text-sm text-red-400">{{ number_format($outVal, 2, ',', '.') }}&nbsp;€</span>
                                @endif
                            </td>
                        @endfor
                    </tr>

                    {{-- AUSZAHLUNGEN ITEMS --}}
                    @foreach($expenseRows as $key => $rowData)
                        @php
                            $rowSum = 0;
                            for($i=1; $i<=12; $i++) { $rowSum += abs((float) ($data[$activeYear][$i]['out'][$key] ?? 0)); }
                            $isZeroRow = $rowSum == 0;
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition-colors group {{ $isZeroRow ? 'opacity-40' : '' }}">
                            <td class="sticky left-0 bg-gray-950 z-20 px-6 py-2 border-r border-gray-800 {{ $isZeroRow ? 'text-gray-600 line-through' : 'text-gray-300' }} shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] group-hover:bg-gray-900 flex items-center justify-between">
                                <span class="truncate pr-2 {{$key === 'private' && !$isZeroRow ? 'text-primary font-bold' : ''}}">{{$rowData['label']}}</span>
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-600 hover:text-primary cursor-help shrink-0" title="{{$rowData['tooltip']}}" />
                            </td>
                            @for($m = 1;$m <= 12;$m++)
                                <td class="px-2 py-1.5 border-r border-gray-800/50 align-middle whitespace-nowrap">
                                    @php $val = $data[$activeYear][$m]['out'][$key] ?? null; @endphp
                                    <div class="flex items-center justify-end gap-1">
                                        <input type="number" step="0.01" value="{{ $val !== null ? number_format((float)$val, 2, '.', '') : '' }}"
                                               wire:change="updateValue({{ $activeYear }}, {{ $m }}, 'out', '{{ $key }}', $event.target.value)"
                                               class="w-full bg-transparent text-right font-mono focus:bg-gray-950 focus:ring-1 focus:ring-primary rounded px-1 outline-none transition-colors {{ empty($val) ? 'text-[10px] text-gray-600/50 font-normal' : 'text-gray-200 text-sm' }} {{ $isZeroRow ? 'line-through text-gray-600' : '' }}"
                                               placeholder="0.00">
                                        <span class="text-gray-600 {{ empty($val) ? 'text-[10px]' : 'text-sm font-bold text-gray-500' }}">&nbsp;€</span>
                                    </div>
                                </td>
                            @endfor
                        </tr>
                    @endforeach

                    {{-- ÜBER/UNTERDECKUNG MONAT --}}
                    <tr class="bg-orange-500/10 border-y-2 border-orange-500/30">
                        <td class="sticky left-0 bg-[#160c04] z-20 px-6 py-3 border-r border-orange-500/30 font-bold text-orange-400 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]"> Über-/Unterdeckung / Monat </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $net = $totals[$activeYear][$m]['net'] ?? 0; @endphp
                            <td class="px-3 py-3 text-right border-r border-orange-500/10 font-mono font-bold whitespace-nowrap">
                                @if($net == 0)
                                    <span class="text-[10px] text-orange-900 font-normal">0,00&nbsp;€</span>
                                @else
                                    <span class="text-sm {{ $net < 0 ? 'text-red-400' : 'text-orange-400' }}">{{ number_format($net, 2, ',', '.') }}&nbsp;€</span>
                                @endif
                            </td>
                        @endfor
                    </tr>

                    {{-- AUSGLEICHSMASSNAHMEN HEADER --}}
                    <tr class="bg-gray-900/50 border-y border-gray-800">
                        <td class="sticky left-0 bg-gray-950 z-20 px-6 py-3 border-r border-gray-800 font-bold text-white shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]"> Ausgleichsmaßnahmen </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $adjVal = $totals[$activeYear][$m]['adj'] ?? 0; @endphp
                            <td class="px-3 py-3 text-right border-r border-gray-800/50 font-mono font-bold whitespace-nowrap">
                                @if($adjVal == 0)
                                    <span class="text-[10px] text-gray-700 font-normal">0,00&nbsp;€</span>
                                @else
                                    <span class="text-sm text-gray-400">{{ number_format($adjVal, 2, ',', '.') }}&nbsp;€</span>
                                @endif
                            </td>
                        @endfor
                    </tr>

                    {{-- AUSGLEICHSMASSNAHMEN ITEMS --}}
                    @foreach($adjustmentRows as $key => $rowData)
                        @php
                            $rowSum = 0;
                            for($i=1; $i<=12; $i++) { $rowSum += abs((float) ($data[$activeYear][$i]['adj'][$key] ?? 0)); }
                            $isZeroRow = $rowSum == 0;
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition-colors group {{ $isZeroRow ? 'opacity-40' : '' }}">
                            <td class="sticky left-0 bg-gray-950 z-20 px-6 py-2 border-r border-gray-800 {{ $isZeroRow ? 'text-gray-600 line-through' : 'text-gray-300' }} shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] group-hover:bg-gray-900 flex items-center justify-between">
                                <span>{{$rowData['label']}}</span>
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-600 hover:text-primary cursor-help" title="{{$rowData['tooltip']}}" />
                            </td>
                            @for($m = 1;$m <= 12;$m++)
                                <td class="px-2 py-1.5 border-r border-gray-800/50 align-middle whitespace-nowrap">
                                    @php $val = $data[$activeYear][$m]['adj'][$key] ?? null; @endphp
                                    <div class="flex items-center justify-end gap-1">
                                        <input type="number" step="0.01" value="{{ $val !== null ? number_format((float)$val, 2, '.', '') : '' }}"
                                               wire:change="updateValue({{ $activeYear }}, {{ $m }}, 'adj', '{{ $key }}', $event.target.value)"
                                               class="w-full bg-transparent text-right font-mono focus:bg-gray-950 focus:ring-1 focus:ring-primary rounded px-1 outline-none transition-colors {{ empty($val) ? 'text-[10px] text-gray-600/50 font-normal' : 'text-gray-200 text-sm' }} {{ $isZeroRow ? 'line-through text-gray-600' : '' }}"
                                               placeholder="0.00">
                                        <span class="text-gray-600 {{ empty($val) ? 'text-[10px]' : 'text-sm font-bold text-gray-500' }}">&nbsp;€</span>
                                    </div>
                                </td>
                            @endfor
                        </tr>
                    @endforeach

                    {{-- BESTAND MONATSENDE (KUMULIERT) --}}
                    <tr class="bg-primary/20 border-t-2 border-primary/50">
                        <td class="sticky left-0 bg-[#1f190e] z-20 px-6 py-4 border-r border-primary/50 font-black text-primary text-base shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]"> Bestand Monatsende </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $end = $totals[$activeYear][$m]['end'] ?? 0; @endphp
                            <td class="px-3 py-4 text-right border-r border-primary/20 font-mono font-black whitespace-nowrap">
                                @if($end == 0)
                                    <span class="text-xs text-primary/50 font-normal">0,00&nbsp;€</span>
                                @else
                                    <span class="text-base {{ $end < 0 ? 'text-red-400 drop-shadow-[0_0_5px_currentColor]' : 'text-primary drop-shadow-[0_0_5px_currentColor]' }}">{{ number_format($end, 2, ',', '.') }}&nbsp;€</span>
                                @endif
                            </td>
                        @endfor
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ZUSATZ TABELLEN (Backend) --}}
        <div class="space-y-8 mt-8">

            {{-- Kapitalbedarfsplanung --}}
            <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
                <div class="p-6 md:p-8 border-b border-gray-800 bg-gray-950">
                    <h3 class="text-xl font-serif font-bold text-white tracking-wide flex items-center gap-3">
                        <x-heroicon-o-banknotes class="w-6 h-6 text-primary" />
                        Kapitalbedarfsplanung (Startphase)
                    </h3>
                </div>
                <div class="overflow-x-auto custom-scrollbar w-full relative p-6">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                        <tr class="bg-gray-950 border-b border-gray-800">
                            <th class="px-6 py-3 border-r border-gray-800 text-[10px] font-black uppercase tracking-widest text-primary w-2/3">Positionen</th>
                            <th class="px-4 py-3 text-right border-gray-800/50 text-[10px] font-black uppercase tracking-widest text-white bg-gray-900/50 w-1/3">Betrag</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                        <tr class="bg-gray-900/20"><td colspan="2" class="px-6 py-2 font-bold text-white text-[10px] uppercase">Investitionsgüter</td></tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Grundstück</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Gebäude</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Umbaumaßnahmen</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Geschäfts- und Ladeneinrichtung</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-300 font-bold">Maschinen + Werkzeuge</td>
                            <td class="px-4 py-2.5 text-right font-mono text-white font-bold">{{ number_format($kapitalbedarf['investitionen']['maschinen'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-300 font-bold">Warenanfangsbestand</td>
                            <td class="px-4 py-2.5 text-right font-mono text-white font-bold">{{ number_format($kapitalbedarf['investitionen']['waren'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Fahrzeuge</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Unternehmenswert (bei Unternehmenskauf)</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Sonstiges</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="bg-gray-800/50">
                            <td class="px-6 py-3 border-r border-gray-800 text-white font-black text-xs">Summe Investitionsgüter</td>
                            <td class="px-4 py-3 text-right font-mono text-white font-bold">{{ number_format(array_sum($kapitalbedarf['investitionen'] ?? []), 2, ',', '.') }}&nbsp;€</td>
                        </tr>

                        <tr class="bg-gray-900/20"><td colspan="2" class="px-6 py-2 font-bold text-white text-[10px] uppercase">Mit der Maßnahme einhergehende Aufwendungen (Gründung)</td></tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-300 font-bold">Werbung</td>
                            <td class="px-4 py-2.5 text-right font-mono text-white font-bold">{{ number_format($kapitalbedarf['gruendung']['werbung'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-300 font-bold">Beratungen, Gutachten (Schulung)</td>
                            <td class="px-4 py-2.5 text-right font-mono text-white font-bold">{{ number_format($kapitalbedarf['gruendung']['beratung'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Anmeldungen/Genehmigungen</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Eintragung ins Handelsregister</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Notar</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Sonstiges</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="bg-gray-800/50">
                            <td class="px-6 py-3 border-r border-gray-800 text-white font-black text-xs">Summe Aufwendungen</td>
                            <td class="px-4 py-3 text-right font-mono text-white font-bold">{{ number_format(array_sum($kapitalbedarf['gruendung'] ?? []), 2, ',', '.') }}&nbsp;€</td>
                        </tr>

                        <tr class="bg-gray-950 border-t-2 border-gray-700">
                            <td class="px-6 py-4 border-r border-gray-800 text-primary font-black text-sm uppercase tracking-widest">Gesamter Finanzierungsbedarf</td>
                            <td class="px-4 py-4 text-right font-mono text-primary font-bold text-lg">{{ number_format(array_sum($kapitalbedarf['investitionen'] ?? []) + array_sum($kapitalbedarf['gruendung'] ?? []), 2, ',', '.') }}&nbsp;€</td>
                        </tr>

                        <tr class="bg-gray-900/20"><td colspan="2" class="px-6 py-2 font-bold text-white text-[10px] uppercase border-t border-gray-800">Finanzierungsstruktur</td></tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-emerald-400 font-bold">Eigenmittel</td>
                            <td class="px-4 py-2.5 text-right font-mono text-emerald-400 font-bold">{{ number_format($kapitalbedarf['finanzierung']['eigenmittel'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">zusätzliche Belastung des bestehenden Kontokorrent-Rahmens</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-300">Darlehen und Erweiterung des Kontokorrent-Rahmens</td>
                            <td class="px-4 py-2.5 text-right font-mono text-white">{{ number_format($kapitalbedarf['finanzierung']['darlehen'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-6 py-2.5 border-r border-gray-800 text-gray-500">Liquiditäts-Puffer</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600">0,00&nbsp;€</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Ertrags-/ Rentabilitätsvorschau TABELLE --}}
            <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
                <div class="p-6 md:p-8 border-b border-gray-800 bg-gray-950">
                    <h3 class="text-xl font-serif font-bold text-white tracking-wide flex items-center gap-3">
                        <x-heroicon-o-chart-bar class="w-6 h-6 text-primary" />
                        Ertrags-/ Rentabilitätsvorschau (Netto)
                    </h3>
                </div>
                <div class="overflow-x-auto custom-scrollbar w-full relative">
                    <table class="w-full text-left border-collapse min-w-max text-sm">
                        <thead>
                        <tr class="bg-gray-950 border-b border-gray-800">
                            <th class="sticky left-0 bg-gray-950 z-20 px-6 py-4 min-w-[250px] border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] text-[10px] font-black uppercase tracking-widest text-primary">
                                Beträge in EUR
                            </th>
                            @foreach($years as $index => $y)
                                <th class="px-4 py-3 text-right border-r border-gray-800/50 text-[10px] font-black uppercase tracking-widest text-white bg-gray-900/50 w-32">
                                    {{ $index + 1 }}. Jahr ({{ $y }})
                                </th>
                                <th class="px-4 py-3 text-right border-r border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-500 bg-gray-900/30 w-24">
                                    % vom Umsatz
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                        @foreach($rentRows as $key => $label)
                            @php
                                $isBold = in_array($key, ['rohertrag', 'betriebsergebnis', 'ergebnis_vor_steuern', 'gewinn', 'cashflow']);
                                $isBg = in_array($key, ['rohertrag', 'gewinn']);
                            @endphp
                            <tr class="hover:bg-gray-800/30 transition-colors {{ $isBg ? 'bg-primary/10 border-y border-primary/30' : '' }}">
                                <td class="sticky left-0 bg-gray-950 z-20 px-6 py-3 border-r border-gray-800 {{ $isBold ? 'font-bold text-white' : 'text-gray-400' }} shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                                    {{ $label }}
                                </td>
                                @foreach($years as $y)
                                    @php
                                        $val = $rentabilitaet[$y][$key] ?? 0;
                                        $umsatz = $rentabilitaet[$y]['umsatz'] ?? 0;
                                        $pct = $umsatz > 0 ? ($val / $umsatz) * 100 : 0;
                                    @endphp
                                    <td class="px-4 py-3 text-right border-r border-gray-800/50 font-mono {{ $isBold ? 'font-bold text-gray-200' : 'text-gray-500' }} whitespace-nowrap">
                                        @if($val == 0)
                                            <span class="text-xs text-gray-600/50 font-normal">0,00&nbsp;€</span>
                                        @else
                                            {{ number_format($val, 2, ',', '.') }}&nbsp;€
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right border-r border-gray-800 font-mono text-[10px] text-gray-600 whitespace-nowrap">
                                        @if($pct == 0)
                                            <span class="opacity-50">0,00&nbsp;%</span>
                                        @else
                                            {{ number_format($pct, 2, ',', '.') }}&nbsp;%
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('livewire:initialized',()=>{
                const ctx = document.getElementById('liquidityChart');
                if(!ctx) return;

                let chartInstance = new Chart(ctx,{
                    type: 'line',
                    data:{
                        labels:[],
                        datasets:[{
                            label: 'Kontostand (€)',
                            data:[],
                            borderColor: '#C5A059',
                            backgroundColor: 'rgba(197, 160, 89, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#C5A059',
                            pointRadius: 4
                        }]
                    },
                    options:{
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins:{legend:{display: false}},
                        scales:{
                            y:{grid:{color: 'rgba(255,255,255,0.05)'}, ticks:{color: '#9ca3af', callback: function(val){return val + ' €';}}},
                            x:{grid:{color: 'rgba(255,255,255,0.05)'}, ticks:{color: '#9ca3af'}}
                        }
                    }
                });

                Livewire.on('update-liquidity-chart',(event)=>{
                    const data = event.chartData || event[0]?.chartData;
                    if(data){
                        chartInstance.data.labels = data.labels;
                        chartInstance.data.datasets[0].data = data.balances;
                        chartInstance.update();
                    }
                });
            });
        </script>
    </div>
</div>
