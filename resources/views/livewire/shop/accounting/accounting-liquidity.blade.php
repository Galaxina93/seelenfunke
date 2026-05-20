<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <div class="space-y-4 md:space-y-6 animate-fade-in-up pb-10">

        {{-- HEADER --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-2xl md:rounded-[2.5rem] shadow-2xl border border-gray-800 p-4 sm:p-6 md:p-8 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-5 md:gap-6">
            <div class="w-full">
                <h2 class="text-xl md:text-2xl font-serif font-bold text-white tracking-wide flex items-center gap-3">
                    <div class="p-2 md:p-2.5 bg-blue-500/10 border border-blue-500/20 rounded-xl text-blue-400 shrink-0">
                        <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <span>Liquiditätsplanung (Brutto)</span>
                </h2>
                <p class="text-[10px] md:text-xs text-gray-400 mt-2 md:mt-3 max-w-2xl leading-relaxed">Plane deine Ein- und Auszahlungen. Das System berechnet automatisch, wann sich deine Firma nach Wegfall von ALG 1 und Zuschüssen selbst tragen muss.</p>
            </div>

            <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2 md:gap-3 w-full xl:w-auto">
                <button wire:click="removeYear" class="w-full sm:w-auto px-3 md:px-4 py-2.5 bg-gray-950 border border-gray-800 hover:border-red-500/50 text-gray-400 hover:text-red-400 rounded-xl text-[9px] md:text-[10px] font-black uppercase tracking-widest transition-all shadow-inner">- Jahr</button>
                <button wire:click="addYear" class="w-full sm:w-auto px-3 md:px-4 py-2.5 bg-gray-950 border border-gray-800 hover:border-emerald-500/50 text-gray-400 hover:text-emerald-400 rounded-xl text-[9px] md:text-[10px] font-black uppercase tracking-widest transition-all shadow-inner">+ Jahr</button>

                <button wire:click="syncLiveData" wire:loading.attr="disabled" class="col-span-2 w-full sm:w-auto px-4 py-2.5 bg-gray-950 border border-gray-800 hover:border-blue-500/50 text-gray-400 hover:text-blue-400 rounded-xl text-[9px] md:text-[10px] font-black uppercase tracking-widest transition-all shadow-inner flex items-center justify-center gap-2 group">
                    <svg class="w-3.5 h-3.5 md:w-4 md:h-4 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span wire:loading.remove wire:target="syncLiveData">Livedaten Sync</span>
                    <span wire:loading wire:target="syncLiveData">Lade Daten...</span>
                </button>

                <button wire:click="exportPdf" wire:loading.class="opacity-50 grayscale cursor-not-allowed" wire:loading.attr="disabled" class="col-span-2 w-full sm:w-auto px-4 py-2.5 bg-[var(--theme-color)] text-gray-900 hover:text-white active:opacity-50 rounded-xl text-[9px] md:text-[10px] font-black uppercase tracking-widest transition-all shadow-[0_0_15px_var(--theme-color-30)] flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Als PDF Exportieren</span>
                </button>
            </div>
        </div>

        @if(session()->has('success'))
            <div x-data="{show: true}" x-show="show" x-init="setTimeout(()=> show = false, 3000)" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 md:px-6 py-3 md:py-4 rounded-xl md:rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.2)] flex items-center gap-3 animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/> </svg>
                <span class="font-black uppercase tracking-widest text-[9px] md:text-[10px]">{{session('success')}}</span>
            </div>
        @endif

        {{-- CHART --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-2xl md:rounded-[2.5rem] shadow-2xl border border-gray-800 p-4 sm:p-6 md:p-8">
            <h3 class="text-xs md:text-sm font-serif font-bold text-white mb-3 md:mb-4 tracking-wide">Prognostizierter Kassenbestand (Liquiditätskurve)</h3>
            <div wire:ignore class="relative h-48 md:h-64 w-full">
                <canvas id="liquidityChart"></canvas>
            </div>
        </div>

        {{-- CONFIGURATION PANEL --}}
        <div class="bg-gray-900/60 backdrop-blur-md rounded-2xl md:rounded-[2.5rem] border border-gray-800 p-4 sm:p-6 mb-2 mt-4 shadow-xl">
            <h3 class="text-[10px] sm:text-xs font-black uppercase text-[var(--theme-color)] tracking-widest mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Projekt-Start & Darlehens-Parameter
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 md:gap-6">
                {{-- Start Year --}}
                <div class="space-y-1">
                    <label class="flex items-center gap-1.5 text-[9px] md:text-[10px] font-bold text-gray-400">
                        Gründungsjahr
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500 hover:text-white cursor-help transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Das Kalenderjahr, ab dem das Projekt offiziell startet und ab dem das System bei Engpässen ggf. automatische Darlehen genehmigt."><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </label>
                    <input type="number" wire:model.live.debounce.800ms="configStartYear" class="w-full bg-gray-950/50 border border-gray-800 rounded-xl px-3 py-2 text-xs md:text-sm text-gray-200 font-mono focus:ring-1 focus:ring-[var(--theme-color)] outline-none transition-colors focus:bg-gray-900">
                </div>
                {{-- Start Month --}}
                <div class="space-y-1">
                    <label class="flex items-center gap-1.5 text-[9px] md:text-[10px] font-bold text-gray-400">
                        Gründungsmonat (1-12)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500 hover:text-white cursor-help transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Der Monat im angegebenen Gründungsjahr, ab dem die Liquiditätsberechnung startet."><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </label>
                    <input type="number" min="1" max="12" wire:model.live.debounce.800ms="configStartMonth" class="w-full bg-gray-950/50 border border-gray-800 rounded-xl px-3 py-2 text-xs md:text-sm text-gray-200 font-mono focus:ring-1 focus:ring-[var(--theme-color)] outline-none transition-colors focus:bg-gray-900">
                </div>
                {{-- Interest Rate --}}
                <div class="space-y-1">
                    <label class="flex items-center gap-1.5 text-[9px] md:text-[10px] font-bold text-gray-400">
                        Autokredit-Zins p.a. (%)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500 hover:text-white cursor-help transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Die Kredithöhe berechnet das System komplett selbst (min. 3.000 €, immer passend in 1.000 € Schritten), sobald das Konto theoretisch ins Minus rutscht. Hier legst du lediglich den Zins für diese vollautomatischen Kredite fest."><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </label>
                    <input type="number" step="0.1" wire:model.live.debounce.800ms="configInterestRate" class="w-full bg-gray-950/50 border border-gray-800 rounded-xl px-3 py-2 text-xs md:text-sm text-gray-200 font-mono focus:ring-1 focus:ring-[var(--theme-color)] outline-none transition-colors focus:bg-gray-900">
                </div>
                {{-- Repayment Months --}}
                <div class="space-y-1">
                    <label class="flex items-center gap-1.5 text-[9px] md:text-[10px] font-bold text-gray-400">
                        Tilgungsdauer (Monate)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500 hover:text-white cursor-help transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Gibt an, über wie viele Monate ein automatisch aufgenommener Kredit linear abbezahlt werden soll (z. B. 60 Monate = 5 Jahre)."><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </label>
                    <input type="number" min="1" wire:model.live.debounce.800ms="configRepaymentMonths" class="w-full bg-gray-950/50 border border-gray-800 rounded-xl px-3 py-2 text-xs md:text-sm text-gray-200 font-mono focus:ring-1 focus:ring-[var(--theme-color)] outline-none transition-colors focus:bg-gray-900">
                </div>
                {{-- Demo Data Toggle --}}
                <div class="space-y-1 flex flex-col justify-start mt-1 col-span-2 md:col-span-1">
                    <label class="flex items-center gap-1.5 text-[9px] md:text-[10px] font-bold text-gray-400 mb-2">
                        Muster-Plan laden?
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-500 hover:text-white cursor-help transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Lädt realistische Musterdaten (Anfangsinvestitionen, fiktive Start-Umsätze). Deaktivieren für einen komplett leeren Plan, der sich rein aus Live-Daten speist. Wirkt sich direkt inkl. aller Folgen auf den PDF-Export aus!"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" wire:model.live="configLoadDemoData" class="sr-only">
                            <div class="block bg-gray-800 w-10 h-6 rounded-full transition-colors {{ $configLoadDemoData ? 'bg-[var(--theme-color)]' : 'group-hover:bg-gray-700' }}"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform {{ $configLoadDemoData ? 'transform translate-x-4' : '' }}"></div>
                        </div>
                        <div class="ml-3 text-[10px] md:text-xs text-gray-300 font-bold uppercase tracking-wider">
                            {{ $configLoadDemoData ? 'Aktiv' : 'Deaktiviert' }}
                        </div>
                    </label>
                </div>
            </div>
        </div>

        {{-- GOLDENER JAHRES SELECTOR --}}
        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-1.5 md:gap-2 bg-gray-950 p-1.5 md:p-2 rounded-xl md:rounded-2xl border border-gray-800 shadow-[0_0_20px_rgba(0,0,0,0.5)] w-full sm:w-max mx-auto sm:mx-0">
            @foreach($years as $year)
                <button wire:click="setActiveYear({{ $year }})"
                        class="flex-1 sm:flex-none px-3 md:px-6 py-2.5 md:py-3 rounded-lg md:rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 {{ $activeYear === $year ? 'bg-[var(--theme-color)] text-gray-900 shadow-[0_0_20px_var(--theme-color-40)]' : 'text-gray-500 hover:text-[var(--theme-color)] hover:bg-gray-900' }}">
                    Jahr {{ $year }}
                </button>
            @endforeach
        </div>

        {{-- HAUPTTABELLE (Liquidität) --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-2xl md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden pb-1">

            {{-- Wrapper bricht auf Mobile aus dem Padding aus (-mx-4 px-4) --}}
            <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto custom-scrollbar w-full relative">
                <table class="w-full text-left border-collapse min-w-max text-sm">
                    <thead>
                    <tr class="bg-gray-950 border-b border-gray-800">
                        <th class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] min-w-[130px] max-w-[130px] md:min-w-[300px] md:max-w-[300px]">
                            <div class="px-3 md:px-6 py-3 md:py-4 text-[9px] md:text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] text-left">
                                Kategorien (in EUR)
                            </div>
                        </th>
                        <th colspan="12" class="px-2 md:px-4 py-2 md:py-3 text-center border-r border-gray-800 text-[9px] md:text-[10px] font-black uppercase tracking-widest text-white bg-gray-900/50">
                            Detailansicht: {{$activeYear}}
                        </th>
                    </tr>
                    <tr class="bg-gray-900/50 border-b border-gray-800 text-[9px] md:text-[10px] font-black uppercase tracking-widest text-gray-500 shadow-inner">
                        <th class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="px-3 md:px-6 py-2 md:py-3 text-left">Monat</div>
                        </th>
                        @for($m=1; $m<=12; $m++)
                            <th class="px-2 md:px-4 py-2 md:py-3 text-right border-r border-gray-800/50 min-w-[85px] md:min-w-[95px] w-[85px] md:w-[95px]">{{ sprintf('%02d.%02d', $m, $activeYear % 100) }}</th>
                        @endfor
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-800/50">
                    {{-- BESTAND MONATSANFANG --}}
                    <tr class="bg-gray-900/20 group">
                        <td class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 group-hover:bg-gray-900 transition-colors shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="px-3 md:px-6 py-2 md:py-3 font-bold text-white text-[10px] md:text-sm whitespace-normal leading-tight h-full min-h-[44px] flex items-center">
                                Kasse/Bank - Bestand Monatsanfang
                            </div>
                        </td>
                        @for($m = 1;$m <= 12;$m++)
                            <td class="px-1.5 md:px-3 py-1.5 md:py-3 text-right border-r border-gray-800/50 font-mono align-middle">
                                @if($activeYear === $years[0] && $m === 1)
                                    <div class="flex items-center justify-end gap-1">
                                        <input type="number" step="0.01" wire:model.live.blur="startBalance" class="w-full min-w-[65px] md:min-w-[80px] bg-transparent text-right font-mono text-white text-xs md:text-sm focus:bg-gray-950 focus:ring-1 focus:ring-[var(--theme-color)] rounded px-1 outline-none">
                                        <span class="text-gray-500 text-xs md:text-sm font-bold">&nbsp;€</span>
                                    </div>
                                @else
                                    @php $startVal = $totals[$activeYear][$m]['start'] ?? 0; @endphp
                                    @if($startVal == 0)
                                        <span class="text-[9px] md:text-[10px] text-gray-600/50 font-normal pr-1">0,00&nbsp;€</span>
                                    @else
                                        <span class="text-xs md:text-sm text-gray-400 pr-1">{{ number_format($startVal, 2, ',', '.') }}&nbsp;€</span>
                                    @endif
                                @endif
                            </td>
                        @endfor
                    </tr>

                    {{-- EINZAHLUNGEN HEADER --}}
                    <tr class="bg-emerald-900/10 border-y-2 border-emerald-500/30">
                        <td class="sticky left-0 bg-[#061810] z-20 p-0 border-r border-emerald-500/30 align-middle shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="px-3 md:px-6 py-2 md:py-3 font-bold text-emerald-400 text-[10px] md:text-sm whitespace-normal leading-tight flex items-center justify-between gap-1">
                                <span>Einzahlungen (brutto), Summe</span>
                                <x-heroicon-o-information-circle class="w-3 h-3 md:w-4 md:h-4 text-emerald-600 hover:text-emerald-400 cursor-help shrink-0" title="Summe aller voraussichtlichen monatlichen Zahlungseingänge auf dem Konto (inklusive möglicher Umsatzsteuer und staatlicher Zuschüsse)." />
                            </div>
                        </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $inVal = $totals[$activeYear][$m]['in'] ?? 0; @endphp
                            <td class="px-2 md:px-3 py-2 md:py-3 text-right border-r border-emerald-500/10 font-mono font-bold align-middle">
                                @if($inVal == 0)
                                    <span class="text-[9px] md:text-[10px] text-emerald-900 font-normal pr-1">0,00&nbsp;€</span>
                                @else
                                    <span class="text-xs md:text-sm text-emerald-400 pr-1">{{ number_format($inVal, 2, ',', '.') }}&nbsp;€</span>
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
                        <tr class="hover:bg-gray-800/30 transition-colors group {{ $isZeroRow ? 'opacity-30 grayscale' : '' }}">
                            <td class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] align-middle">
                                <div class="px-3 md:px-6 py-2 md:py-2.5 flex items-center justify-between gap-1 h-full min-h-[44px] group-hover:bg-gray-900 transition-colors {{ $isZeroRow ? 'text-gray-600' : 'text-gray-300' }}">
                                        <span class="text-[10px] md:text-sm whitespace-normal break-words leading-tight text-left {{$key === 'subsidy' && !$isZeroRow ? 'text-[var(--theme-color)] font-bold' : ''}}">
                                            {{$rowData['label']}}
                                        </span>
                                    <x-heroicon-o-information-circle class="w-3 h-3 md:w-4 md:h-4 text-gray-600 hover:text-[var(--theme-color)] cursor-help shrink-0 hidden sm:block" title="{{$rowData['tooltip']}}" />
                                </div>
                            </td>
                            @for($m = 1;$m <= 12;$m++)
                                <td class="px-1 md:px-2 py-1 md:py-1.5 border-r border-gray-800/50 align-middle">
                                    @php $val = $data[$activeYear][$m]['in'][$key] ?? null; @endphp
                                    <div class="flex items-center justify-end gap-0.5 md:gap-1">
                                        <input type="number" step="0.01" value="{{ $val !== null ? number_format((float)$val, 2, '.', '') : '' }}"
                                               wire:change="updateValue({{ $activeYear }}, {{ $m }}, 'in', '{{ $key }}', $event.target.value)"
                                               class="w-full min-w-[60px] md:min-w-[80px] bg-transparent text-right font-mono focus:bg-gray-950 focus:ring-1 focus:ring-[var(--theme-color)] rounded px-1 outline-none transition-colors text-xs md:text-sm {{ empty($val) ? 'text-gray-600/50 font-normal' : 'text-gray-200' }} {{ $isZeroRow ? 'text-gray-600' : '' }}"
                                               placeholder="0.00">
                                        <span class="text-gray-600 {{ empty($val) ? 'text-[9px] md:text-[10px]' : 'text-xs md:text-sm font-bold text-gray-500' }}">&nbsp;€</span>
                                    </div>
                                </td>
                            @endfor
                        </tr>
                    @endforeach

                    {{-- AUSZAHLUNGEN HEADER --}}
                    <tr class="bg-red-900/10 border-y-2 border-red-500/30">
                        <td class="sticky left-0 bg-[#180a0a] z-20 p-0 border-r border-red-500/30 align-middle shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="px-3 md:px-6 py-2 md:py-3 font-bold text-red-400 text-[10px] md:text-sm whitespace-normal leading-tight flex items-center justify-between gap-1">
                                <span>Auszahlungen (brutto), Summe</span>
                                <x-heroicon-o-information-circle class="w-3 h-3 md:w-4 md:h-4 text-red-600 hover:text-red-400 cursor-help shrink-0" title="Summe aller voraussichtlichen monatlichen Zahlungsausgänge (inklusive Fixkosten, variabler Kosten, Vorsteuerbeträge und privater Entnahmen)." />
                            </div>
                        </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $outVal = $totals[$activeYear][$m]['out'] ?? 0; @endphp
                            <td class="px-2 md:px-3 py-2 md:py-3 text-right border-r border-red-500/10 font-mono font-bold align-middle">
                                @if($outVal == 0)
                                    <span class="text-[9px] md:text-[10px] text-red-900 font-normal pr-1">0,00&nbsp;€</span>
                                @else
                                    <span class="text-xs md:text-sm text-red-400 pr-1">{{ number_format($outVal, 2, ',', '.') }}&nbsp;€</span>
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
                        <tr class="hover:bg-gray-800/30 transition-colors group {{ $isZeroRow ? 'opacity-30 grayscale' : '' }}">
                            <td class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] align-middle">
                                <div class="px-3 md:px-6 py-2 md:py-2.5 flex items-center justify-between gap-1 h-full min-h-[44px] group-hover:bg-gray-900 transition-colors {{ $isZeroRow ? 'text-gray-600' : 'text-gray-300' }}">
                                        <span class="text-[10px] md:text-sm whitespace-normal break-words leading-tight text-left {{$key === 'private' && !$isZeroRow ? 'text-[var(--theme-color)] font-bold' : ''}}">
                                            {{$rowData['label']}}
                                        </span>
                                    <x-heroicon-o-information-circle class="w-3 h-3 md:w-4 md:h-4 text-gray-600 hover:text-[var(--theme-color)] cursor-help shrink-0 hidden sm:block" title="{{$rowData['tooltip']}}" />
                                </div>
                            </td>
                            @for($m = 1;$m <= 12;$m++)
                                <td class="px-1 md:px-2 py-1 md:py-1.5 border-r border-gray-800/50 align-middle">
                                    @php $val = $data[$activeYear][$m]['out'][$key] ?? null; @endphp
                                    <div class="flex items-center justify-end gap-0.5 md:gap-1">
                                        <input type="number" step="0.01" value="{{ $val !== null ? number_format((float)$val, 2, '.', '') : '' }}"
                                               wire:change="updateValue({{ $activeYear }}, {{ $m }}, 'out', '{{ $key }}', $event.target.value)"
                                               class="w-full min-w-[60px] md:min-w-[80px] bg-transparent text-right font-mono focus:bg-gray-950 focus:ring-1 focus:ring-[var(--theme-color)] rounded px-1 outline-none transition-colors text-xs md:text-sm {{ empty($val) ? 'text-gray-600/50 font-normal' : 'text-gray-200' }} {{ $isZeroRow ? 'text-gray-600' : '' }}"
                                               placeholder="0.00">
                                        <span class="text-gray-600 {{ empty($val) ? 'text-[9px] md:text-[10px]' : 'text-xs md:text-sm font-bold text-gray-500' }}">&nbsp;€</span>
                                    </div>
                                </td>
                            @endfor
                        </tr>
                    @endforeach

                    {{-- ÜBER/UNTERDECKUNG MONAT --}}
                    <tr class="bg-orange-500/10 border-y-2 border-orange-500/30">
                        <td class="sticky left-0 bg-[#160c04] z-20 p-0 border-r border-orange-500/30 align-middle shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="px-3 md:px-6 py-2 md:py-3 font-bold text-orange-400 text-[10px] md:text-sm whitespace-normal leading-tight flex items-center justify-between gap-1">
                                <span>Über-/Unterdeckung / Monat</span>
                                <x-heroicon-o-information-circle class="w-3 h-3 md:w-4 md:h-4 text-orange-600 hover:text-orange-400 cursor-help shrink-0" title="Differenz aus Einzahlungen minus Auszahlungen in diesem Monat. Eine Unterdeckung (Minus) zehrt die Liquiditätsreserven auf." />
                            </div>
                        </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $net = $totals[$activeYear][$m]['net'] ?? 0; @endphp
                            <td class="px-2 md:px-3 py-2 md:py-3 text-right border-r border-orange-500/10 font-mono font-bold align-middle">
                                @if($net == 0)
                                    <span class="text-[9px] md:text-[10px] text-orange-900 font-normal pr-1">0,00&nbsp;€</span>
                                @else
                                    <span class="text-xs md:text-sm {{ $net < 0 ? 'text-red-400' : 'text-orange-400' }} pr-1">{{ number_format($net, 2, ',', '.') }}&nbsp;€</span>
                                @endif
                            </td>
                        @endfor
                    </tr>

                    {{-- AUTO-DARLEHEN HINWEIS --}}
                    <tr class="bg-indigo-900/10 border-b border-indigo-500/30">
                        <td class="sticky left-0 bg-[#0f0c1b] z-20 p-0 border-r border-indigo-500/30 align-middle shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="px-3 md:px-6 py-1 md:py-2 text-[10px] md:text-xs whitespace-normal leading-tight text-indigo-400 italic">
                                ↳ Automatisch durch Darlehen gedeckt
                            </div>
                        </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $loanVal = $data[$activeYear][$m]['adj']['loan'] ?? 0; @endphp
                            <td class="px-2 md:px-3 py-1 md:py-2 text-right border-r border-indigo-500/10 font-mono text-[9px] md:text-[11px] align-middle">
                                @if($loanVal > 0)
                                    <span class="text-indigo-400 font-bold">+{{ number_format($loanVal, 2, ',', '.') }}&nbsp;€</span>
                                @else
                                    <span class="text-indigo-900/50">-</span>
                                @endif
                            </td>
                        @endfor
                    </tr>

                    {{-- AUSGLEICHSMASSNAHMEN HEADER --}}
                    <tr class="bg-gray-900/50 border-y border-gray-800">
                        <td class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 align-middle shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="px-3 md:px-6 py-2 md:py-3 font-bold text-white text-[10px] md:text-sm whitespace-normal leading-tight">
                                Ausgleichsmaßnahmen
                            </div>
                        </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $adjVal = $totals[$activeYear][$m]['adj'] ?? 0; @endphp
                            <td class="px-2 md:px-3 py-2 md:py-3 text-right border-r border-gray-800/50 font-mono font-bold align-middle">
                                @if($adjVal == 0)
                                    <span class="text-[9px] md:text-[10px] text-gray-700 font-normal pr-1">0,00&nbsp;€</span>
                                @else
                                    <span class="text-xs md:text-sm text-gray-400 pr-1">{{ number_format($adjVal, 2, ',', '.') }}&nbsp;€</span>
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
                        <tr class="hover:bg-gray-800/30 transition-colors group {{ $isZeroRow ? 'opacity-30 grayscale' : '' }}">
                            <td class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] align-middle">
                                <div class="px-3 md:px-6 py-2 md:py-2.5 flex items-center justify-between gap-1 h-full min-h-[44px] group-hover:bg-gray-900 transition-colors {{ $isZeroRow ? 'text-gray-600' : 'text-gray-300' }}">
                                        <span class="text-[10px] md:text-sm whitespace-normal break-words leading-tight text-left">
                                            {{$rowData['label']}}
                                        </span>
                                    <x-heroicon-o-information-circle class="w-3 h-3 md:w-4 md:h-4 text-gray-600 hover:text-[var(--theme-color)] cursor-help shrink-0 hidden sm:block" title="{{$rowData['tooltip']}}" />
                                </div>
                            </td>
                            @for($m = 1;$m <= 12;$m++)
                                <td class="px-1 md:px-2 py-1 md:py-1.5 border-r border-gray-800/50 align-middle">
                                    @php $val = $data[$activeYear][$m]['adj'][$key] ?? null; @endphp
                                    <div class="flex items-center justify-end gap-0.5 md:gap-1">
                                        <input type="number" step="0.01" value="{{ $val !== null ? number_format((float)$val, 2, '.', '') : '' }}"
                                               wire:change="updateValue({{ $activeYear }}, {{ $m }}, 'adj', '{{ $key }}', $event.target.value)"
                                               class="w-full min-w-[60px] md:min-w-[80px] bg-transparent text-right font-mono focus:bg-gray-950 focus:ring-1 focus:ring-[var(--theme-color)] rounded px-1 outline-none transition-colors text-xs md:text-sm {{ empty($val) ? 'text-gray-600/50 font-normal' : 'text-gray-200' }} {{ $isZeroRow ? 'text-gray-600' : '' }}"
                                               placeholder="0.00">
                                        <span class="text-gray-600 {{ empty($val) ? 'text-[9px] md:text-[10px]' : 'text-xs md:text-sm font-bold text-gray-500' }}">&nbsp;€</span>
                                    </div>
                                </td>
                            @endfor
                        </tr>
                    @endforeach

                    {{-- BESTAND MONATSENDE (KUMULIERT) --}}
                    <tr class="bg-[var(--theme-color-20)] border-t-2 border-[var(--theme-color-50)]">
                        <td class="sticky left-0 bg-[#1f190e] z-20 p-0 border-r border-[var(--theme-color-50)] align-middle shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)]">
                            <div class="px-3 md:px-6 py-3 md:py-4 font-black text-[var(--theme-color)] text-xs md:text-base whitespace-normal leading-tight">
                                Bestand Monatsende
                            </div>
                        </td>
                        @for($m = 1;$m <= 12;$m++)
                            @php $end = $totals[$activeYear][$m]['end'] ?? 0; @endphp
                            <td class="px-2 md:px-3 py-3 md:py-4 text-right border-r border-[var(--theme-color-20)] font-mono font-black align-middle">
                                @if($end == 0)
                                    <span class="text-[10px] md:text-xs text-[var(--theme-color-50)] font-normal pr-1">0,00&nbsp;€</span>
                                @else
                                    <span class="text-xs md:text-base {{ $end < 0 ? 'text-red-400 drop-shadow-[0_0_5px_currentColor]' : 'text-[var(--theme-color)] drop-shadow-[0_0_5px_currentColor]' }} pr-1">{{ number_format($end, 2, ',', '.') }}&nbsp;€</span>
                                @endif
                            </td>
                        @endfor
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>

        {{-- ZUSATZ TABELLEN (Gepackt untereinander) --}}
        <div class="space-y-6 md:space-y-8 mt-6 md:mt-8">

            {{-- Kapitalbedarfsplanung --}}
            <div class="bg-gray-900/80 backdrop-blur-md rounded-2xl md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
                <div class="p-4 sm:p-6 md:p-8 border-b border-gray-800 bg-gray-950">
                    <h3 class="text-lg md:text-xl font-serif font-bold text-white tracking-wide flex items-center gap-2 md:gap-3">
                        <x-heroicon-o-banknotes class="w-5 h-5 md:w-6 md:h-6 text-[var(--theme-color)] shrink-0" />
                        <span>Kapitalbedarfsplanung</span>
                    </h3>
                </div>
                <div class="w-full relative p-0 sm:p-6">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                        <tr class="bg-gray-950 border-b border-gray-800">
                            <th class="px-3 md:px-6 py-2 md:py-3 border-r border-gray-800 text-[9px] md:text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] w-2/3">Positionen</th>
                            <th class="px-2 md:px-4 py-2 md:py-3 text-right border-gray-800/50 text-[9px] md:text-[10px] font-black uppercase tracking-widest text-white bg-gray-900/50 w-1/3">Betrag</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                        <tr class="bg-gray-900/20"><td colspan="2" class="px-3 md:px-6 py-1.5 md:py-2 font-bold text-white text-[9px] md:text-[10px] uppercase">Investitionsgüter</td></tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm">Grundstück</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm">Gebäude</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm whitespace-normal leading-tight">Umbaumaßnahmen</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm whitespace-normal leading-tight">Geschäfts- und Ladeneinrichtung</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-300 font-bold text-xs md:text-sm whitespace-normal leading-tight">Maschinen + Werkzeuge</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-white font-bold text-xs md:text-sm">{{ number_format($kapitalbedarf['investitionen']['maschinen'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-300 font-bold text-xs md:text-sm whitespace-normal leading-tight">Warenanfangsbestand</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-white font-bold text-xs md:text-sm">{{ number_format($kapitalbedarf['investitionen']['waren'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm">Fahrzeuge</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm whitespace-normal leading-tight">Unternehmenswert (Kauf)</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm">Sonstiges</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="bg-gray-800/50">
                            <td class="px-3 md:px-6 py-2.5 md:py-3 border-r border-gray-800 text-white font-black text-[10px] md:text-xs">Summe Investitionen</td>
                            <td class="px-2 md:px-4 py-2.5 md:py-3 text-right font-mono text-white font-bold text-xs md:text-sm">{{ number_format(array_sum($kapitalbedarf['investitionen'] ?? []), 2, ',', '.') }}&nbsp;€</td>
                        </tr>

                        <tr class="bg-gray-900/20"><td colspan="2" class="px-3 md:px-6 py-1.5 md:py-2 font-bold text-white text-[9px] md:text-[10px] uppercase whitespace-normal leading-tight">Gründungsaufwendungen</td></tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-300 font-bold text-xs md:text-sm">Werbung</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-white font-bold text-xs md:text-sm">{{ number_format($kapitalbedarf['gruendung']['werbung'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-300 font-bold text-xs md:text-sm whitespace-normal leading-tight">Beratungen, Gutachten</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-white font-bold text-xs md:text-sm">{{ number_format($kapitalbedarf['gruendung']['beratung'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm whitespace-normal leading-tight">Anmeldungen/Genehmigungen</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm whitespace-normal leading-tight">Eintragung ins Handelsregister</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm">Notar</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm">Sonstiges</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="bg-gray-800/50">
                            <td class="px-3 md:px-6 py-2.5 md:py-3 border-r border-gray-800 text-white font-black text-[10px] md:text-xs">Summe Aufwendungen</td>
                            <td class="px-2 md:px-4 py-2.5 md:py-3 text-right font-mono text-white font-bold text-xs md:text-sm">{{ number_format(array_sum($kapitalbedarf['gruendung'] ?? []), 2, ',', '.') }}&nbsp;€</td>
                        </tr>

                        <tr class="bg-gray-950 border-t-2 border-gray-700">
                            <td class="px-3 md:px-6 py-3 md:py-4 border-r border-gray-800 text-[var(--theme-color)] font-black text-xs md:text-sm uppercase tracking-widest whitespace-normal leading-tight">Gesamter Finanzierungsbedarf</td>
                            <td class="px-2 md:px-4 py-3 md:py-4 text-right font-mono text-[var(--theme-color)] font-bold text-sm md:text-lg">{{ number_format(array_sum($kapitalbedarf['investitionen'] ?? []) + array_sum($kapitalbedarf['gruendung'] ?? []), 2, ',', '.') }}&nbsp;€</td>
                        </tr>

                        <tr class="bg-gray-900/20"><td colspan="2" class="px-3 md:px-6 py-1.5 md:py-2 font-bold text-white text-[9px] md:text-[10px] uppercase border-t border-gray-800">Finanzierungsstruktur</td></tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-emerald-400 font-bold text-xs md:text-sm">Eigenmittel</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-emerald-400 font-bold text-xs md:text-sm">{{ number_format($kapitalbedarf['finanzierung']['eigenmittel'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm whitespace-normal leading-tight">Belastung des Kontokorrent</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-300 text-xs md:text-sm whitespace-normal leading-tight">Darlehen / Kredite</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-white text-xs md:text-sm">{{ number_format($kapitalbedarf['finanzierung']['darlehen'] ?? 0, 2, ',', '.') }}&nbsp;€</td>
                        </tr>
                        <tr class="hover:bg-gray-800/30">
                            <td class="px-3 md:px-6 py-2 md:py-2.5 border-r border-gray-800 text-gray-500 text-xs md:text-sm">Liquiditäts-Puffer</td>
                            <td class="px-2 md:px-4 py-2 md:py-2.5 text-right font-mono text-gray-600 text-xs md:text-sm">0,00&nbsp;€</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Ertrags-/ Rentabilitätsvorschau TABELLE --}}
            <div class="bg-gray-900/80 backdrop-blur-md rounded-2xl md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
                <div class="p-4 sm:p-6 md:p-8 border-b border-gray-800 bg-gray-950">
                    <h3 class="text-lg md:text-xl font-serif font-bold text-white tracking-wide flex items-center gap-2 md:gap-3">
                        <x-heroicon-o-chart-bar class="w-5 h-5 md:w-6 md:h-6 text-[var(--theme-color)] shrink-0" />
                        <span>Rentabilitätsvorschau</span>
                    </h3>
                </div>

                {{-- Out-of-padding scroll wrapper on mobile --}}
                <div class="-mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto custom-scrollbar w-full relative pb-1">
                    <table class="w-full text-left border-collapse min-w-max text-sm">
                        <thead>
                        <tr class="bg-gray-950 border-b border-gray-800">
                            <th class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] min-w-[130px] max-w-[130px] md:min-w-[250px] md:max-w-none">
                                <div class="px-3 md:px-6 py-3 md:py-4 text-[9px] md:text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] text-left whitespace-normal leading-tight">
                                    Beträge in EUR
                                </div>
                            </th>
                            @foreach($years as $index => $y)
                                <th class="px-2 md:px-4 py-2 md:py-3 text-right border-r border-gray-800/50 text-[9px] md:text-[10px] font-black uppercase tracking-widest text-white bg-gray-900/50 min-w-[80px] md:w-32">
                                    {{ $index + 1 }}. Jahr
                                </th>
                                <th class="px-1.5 md:px-4 py-2 md:py-3 text-right border-r border-gray-800 text-[8px] md:text-[10px] font-black uppercase tracking-widest text-gray-500 bg-gray-900/30 w-16 md:w-24">
                                    %
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
                            <tr class="hover:bg-gray-800/30 transition-colors {{ $isBg ? 'bg-[var(--theme-color-10)] border-y border-[var(--theme-color-30)]' : '' }}">
                                <td class="sticky left-0 bg-gray-950 z-20 p-0 border-r border-gray-800 shadow-[5px_0_15px_-5px_rgba(0,0,0,0.5)] align-middle">
                                    <div class="px-3 md:px-6 py-2 md:py-3 {{ $isBold ? 'font-bold text-white' : 'text-gray-400' }} text-[10px] md:text-sm whitespace-normal break-words leading-tight h-full min-h-[40px] flex items-center">
                                        {{ $label }}
                                    </div>
                                </td>
                                @foreach($years as $y)
                                    @php
                                        $val = $rentabilitaet[$y][$key] ?? 0;
                                        $umsatz = $rentabilitaet[$y]['umsatz'] ?? 0;
                                        $pct = $umsatz > 0 ? ($val / $umsatz) * 100 : 0;
                                    @endphp
                                    <td class="px-2 md:px-4 py-2 md:py-3 text-right border-r border-gray-800/50 font-mono {{ $isBold ? 'font-bold text-gray-200' : 'text-gray-500' }} whitespace-nowrap text-xs md:text-sm align-middle">
                                        @if($val == 0)
                                            <span class="text-[10px] md:text-xs text-gray-600/50 font-normal pr-1">0,00&nbsp;€</span>
                                        @else
                                            <span class="pr-1">{{ number_format($val, 2, ',', '.') }}&nbsp;€</span>
                                        @endif
                                    </td>
                                    <td class="px-1.5 md:px-4 py-2 md:py-3 text-right border-r border-gray-800 font-mono text-[9px] md:text-[10px] text-gray-600 whitespace-nowrap align-middle">
                                        @if($pct == 0)
                                            <span class="opacity-50 pr-1">0,00&nbsp;%</span>
                                        @else
                                            <span class="pr-1">{{ number_format($pct, 1, ',', '.') }}&nbsp;%</span>
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

        {{-- STEUERN & ABGABEN TABELLEN --}}
        <div class="space-y-6 md:space-y-8 mt-6 md:mt-8">
            <div class="bg-gray-900/80 backdrop-blur-md rounded-2xl md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
                <div class="p-4 sm:p-6 md:p-8 border-b border-gray-800 bg-gray-950">
                    <h3 class="text-lg md:text-xl font-serif font-bold text-white tracking-wide flex items-center gap-2 md:gap-3">
                        <x-heroicon-o-scale class="w-5 h-5 md:w-6 md:h-6 text-[var(--theme-color)] shrink-0" />
                        <span>Steuern & Abgaben (Vorausschau)</span>
                    </h3>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 lg:gap-px bg-gray-800">
                    {{-- UMSATZSTEUER --}}
                    <div class="bg-gray-950 p-4 sm:p-6 md:p-8 flex flex-col h-full">
                        <h4 class="text-xs md:text-sm font-black text-gray-300 uppercase tracking-widest mb-4 border-b border-gray-800 pb-2">Umsatzsteuer (Zahllast)</h4>
                        
                        <div class="space-y-3 mb-6 flex-grow">
                            @foreach($years as $y)
                                @php $vatTotal = $taxCalculations['vat'][$y]['total'] ?? 0; @endphp
                                <div class="flex justify-between items-center py-2 border-b border-gray-800/50 hover:bg-gray-900 transition-colors px-2 rounded">
                                    <span class="text-gray-500 font-bold text-xs md:text-sm">Jahr {{ $y }}</span>
                                    <span class="font-mono font-bold text-xs md:text-sm {{ $vatTotal > 0 ? 'text-red-400' : ($vatTotal < 0 ? 'text-emerald-400' : 'text-gray-600') }}">
                                        {{ number_format($vatTotal, 2, ',', '.') }} €
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="h-32 md:h-40 w-full mt-auto">
                            <canvas id="vatChart"></canvas>
                        </div>
                    </div>

                    {{-- GEWERBESTEUER --}}
                    <div class="bg-gray-950 p-4 sm:p-6 md:p-8 flex flex-col h-full">
                        <h4 class="text-xs md:text-sm font-black text-gray-300 uppercase tracking-widest mb-2">Gewerbesteuer</h4>
                        <p class="text-[9px] md:text-[10px] text-gray-500 mb-4 border-b border-gray-800 pb-2">Freibetrag: 24.500 € / Hebesatz: ~380%</p>
                        
                        <div class="space-y-3 mb-6 flex-grow">
                            @foreach($years as $y)
                                @php $tradeTax = $taxCalculations['trade_tax'][$y]['steuer'] ?? 0; @endphp
                                <div class="flex justify-between items-center py-2 border-b border-gray-800/50 hover:bg-gray-900 transition-colors px-2 rounded">
                                    <span class="text-gray-500 font-bold text-xs md:text-sm">Jahr {{ $y }}</span>
                                    <span class="font-mono font-bold text-xs md:text-sm {{ $tradeTax > 0 ? 'text-red-400' : 'text-gray-600' }}">
                                        {{ number_format($tradeTax, 2, ',', '.') }} €
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="h-32 md:h-40 w-full mt-auto">
                            <canvas id="tradeTaxChart"></canvas>
                        </div>
                    </div>

                    {{-- EINKOMMENSTEUER --}}
                    <div class="bg-gray-950 p-4 sm:p-6 md:p-8 flex flex-col h-full">
                        <h4 class="text-xs md:text-sm font-black text-gray-300 uppercase tracking-widest mb-2">Einkommensteuer</h4>
                        <p class="text-[9px] md:text-[10px] text-gray-500 mb-4 border-b border-gray-800 pb-2">Inkl. Progressionsvorbehalt (ALG 1)</p>
                        
                        <div class="space-y-3 mb-6 flex-grow">
                            @foreach($years as $y)
                                @php 
                                    $incomeTax = $taxCalculations['income_tax'][$y]['steuer'] ?? 0;
                                    $satz = $taxCalculations['income_tax'][$y]['steuersatz'] ?? 0;
                                @endphp
                                <div class="flex justify-between items-center py-2 border-b border-gray-800/50 hover:bg-gray-900 transition-colors px-2 rounded">
                                    <div class="flex flex-col">
                                        <span class="text-gray-500 font-bold text-xs md:text-sm">Jahr {{ $y }}</span>
                                        <span class="text-[8px] md:text-[9px] text-gray-600">Satz: {{ number_format($satz, 1, ',', '.') }} %</span>
                                    </div>
                                    <span class="font-mono font-bold text-xs md:text-sm {{ $incomeTax > 0 ? 'text-red-400' : 'text-gray-600' }}">
                                        {{ number_format($incomeTax, 2, ',', '.') }} €
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="h-32 md:h-40 w-full mt-auto">
                            <canvas id="incomeTaxChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function() {
                const init = () => {
                    const ctx = document.getElementById('liquidityChart');
                    const vatCtx = document.getElementById('vatChart');
                    const tradeCtx = document.getElementById('tradeTaxChart');
                    const incomeCtx = document.getElementById('incomeTaxChart');
                    
                    if(!ctx) return;

                    let chartInstance = new Chart(ctx,{
                        type: 'line',
                        data:{
                            labels:[],
                            datasets:[{
                                label: 'Kontostand (€)',
                                data:[],
                                borderColor: '{{ $this->themeColorHex }}',
                                backgroundColor: '{{ $this->themeColorHex }}1A',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '{{ $this->themeColorHex }}',
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

                    // Helper für kleine Tax-Charts
                    const getTaxChartConfig = (color, bg) => ({
                        type: 'bar',
                        data: { labels: [], datasets: [{ data: [], backgroundColor: bg, borderColor: color, borderWidth: 1, borderRadius: 4 }] },
                        options: {
                            responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: '#6b7280', font: { size: 9 } } },
                                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b7280', font: { size: 9 }, callback: (val) => val + '€' } }
                            }
                        }
                    });

                    let vatChartInstance = vatCtx ? new Chart(vatCtx, getTaxChartConfig('#ef4444', 'rgba(239, 68, 68, 0.2)')) : null;
                    let tradeChartInstance = tradeCtx ? new Chart(tradeCtx, getTaxChartConfig('#f59e0b', 'rgba(245, 158, 11, 0.2)')) : null;
                    let incomeChartInstance = incomeCtx ? new Chart(incomeCtx, getTaxChartConfig('#3b82f6', 'rgba(59, 130, 246, 0.2)')) : null;

                    if (window.cleanupLiquidityChartListener) {
                        window.cleanupLiquidityChartListener();
                    }

                    window.cleanupLiquidityChartListener = Livewire.on('update-liquidity-chart',(event)=>{
                        const data = event.chartData || event[0]?.chartData;
                        if(data){
                            // Update Main Chart
                            chartInstance.data.labels = data.labels;
                            chartInstance.data.datasets[0].data = data.balances;
                            chartInstance.update();

                            // Update Tax Charts (aus den tax_calculations generieren wir mini Arrays)
                            if(data.taxCharts && data.taxCharts.years) {
                                if(vatChartInstance) {
                                    vatChartInstance.data.labels = data.taxCharts.years;
                                    vatChartInstance.data.datasets[0].data = data.taxCharts.vat;
                                    vatChartInstance.data.datasets[0].backgroundColor = data.taxCharts.vat.map(v => v < 0 ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)');
                                    vatChartInstance.data.datasets[0].borderColor = data.taxCharts.vat.map(v => v < 0 ? '#10b981' : '#ef4444');
                                    vatChartInstance.update();
                                }
                                if(tradeChartInstance) {
                                    tradeChartInstance.data.labels = data.taxCharts.years;
                                    tradeChartInstance.data.datasets[0].data = data.taxCharts.trade;
                                    tradeChartInstance.update();
                                }
                                if(incomeChartInstance) {
                                    incomeChartInstance.data.labels = data.taxCharts.years;
                                    incomeChartInstance.data.datasets[0].data = data.taxCharts.income;
                                    incomeChartInstance.update();
                                }
                            }
                        }
                    });
                };

                if (window.Livewire) {
                    init();
                } else {
                    document.addEventListener('livewire:initialized', init, { once: true });
                }
            })();
        </script>
    </div>
</div>
