<div>
    <section class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-10 relative overflow-hidden transition-all duration-500 mt-6 w-full">
        {{-- Glow-Streifen --}}
        <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-orange-500 to-red-600 opacity-60"></div>

        {{-- HEADER & TRESOR --}}
        <div class="flex flex-col md:flex-row justify-between items-start mb-8 gap-6 relative z-10">
            <div>
                <h3 class="text-2xl font-serif font-bold text-white tracking-tight flex items-center gap-3">
                    <i class="solar-document-text-bold-duotone text-orange-400 text-2xl"></i>
                    Umsatzsteuer-Zentrale
                </h3>
                <p class="text-[10px] font-mono text-gray-500 mt-2 uppercase tracking-widest bg-black/40 px-2 py-0.5 rounded border border-gray-800 inline-block">
                    Native ERiC Integration (Offline Mode)
                </p>
            </div>

            <div class="flex items-center gap-3">
                <select wire:model.live="selectedYear" class="bg-gray-950 border border-gray-800 text-gray-300 px-5 py-3 rounded-xl text-sm font-bold shadow-inner transition-all outline-none focus:ring-2 focus:ring-orange-500/30 cursor-pointer">
                    @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>

                <div class="relative" x-data="{ openVault: false }">
                    <button @click="openVault = !openVault" class="flex items-center gap-2 px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-950 text-gray-400 hover:text-white border border-gray-800 shadow-inner hover:scale-[1.02]">
                        <x-heroicon-m-archive-box class="w-4 h-4" />
                        Tresor
                    </button>

                    <div x-show="openVault" @click.away="openVault = false" x-cloak class="absolute right-0 top-full mt-3 w-80 bg-gray-900 border border-gray-800 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.8)] p-2 z-50 animate-fade-in-up">
                        <div class="px-4 py-3 border-b border-gray-800 mb-2">
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Archivierte Exporte</h4>
                        </div>
                        <div class="max-h-64 overflow-y-auto custom-scrollbar px-2 space-y-2">
                            @forelse($archivedExports as $export)
                                <div class="flex items-center justify-between p-3 bg-gray-950 rounded-xl hover:bg-gray-800 transition-colors group border border-gray-800 shadow-inner">
                                    <div class="min-w-0 flex-1">
                                        <h5 class="text-xs font-bold text-gray-200 truncate">{{ $export['name'] }}</h5>
                                        <p class="text-[9px] text-gray-500 font-mono mt-1">{{ $export['date'] }} • {{ $export['size'] }}</p>
                                    </div>
                                    <div class="flex gap-1 ml-3">
                                        <a href="{{ route('admin.tax-export.download', $export['name']) }}" target="_blank" class="p-1.5 text-gray-500 hover:text-orange-400 transition-colors inline-block" title="Herunterladen">
                                            <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                        </a>
                                        <button wire:click="deleteExport('{{ $export['name'] }}')" wire:confirm="Endgültig löschen?" class="p-1.5 text-gray-500 hover:text-red-500 transition-colors" title="Löschen">
                                            <x-heroicon-m-trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-xs text-gray-500 py-6 italic font-serif">Der Tresor ist leer.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ALERTS --}}
        @if(session()->has('success'))
            <div class="mb-6 bg-emerald-500/10 border-l-4 border-emerald-500 p-4 text-emerald-400 shadow-inner rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
                <x-heroicon-s-check-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" />
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session()->has('error'))
            <div class="mb-6 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-400 shadow-inner rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
                <x-heroicon-s-exclamation-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- HINWEIS BOX --}}
        <div class="mb-8 flex items-start gap-3 bg-orange-500/5 p-4 rounded-2xl border border-orange-500/20 shadow-inner">
            <x-heroicon-s-information-circle class="w-5 h-5 text-orange-400 shrink-0 mt-0.5" />
            <div class="text-xs text-orange-100/70 leading-relaxed font-medium">
                <strong class="text-orange-400">Automatische UStVA:</strong> Die Daten des Monats sind am besten <strong>ab dem 1. des Folgemonats</strong> konsolidiert exportierbar. Frist für die Meldung beim Finanzamt ist regulär der <strong>10. des Folgemonats</strong>. Die PDF fasst alle Werte rechtssicher zusammen.
            </div>
        </div>

        {{-- MONATS-NAVIGATION (Slider) --}}
        <div class="w-full mb-8 relative group" x-data="{ scrollAmount: 0 }">
            <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-3 snap-x scroll-smooth w-full px-1">
                @foreach($monthsNav as $num => $nav)
                    @php
                        $isSelected = $selectedMonth == $num;
                        $statusColor = match($nav['status']) {
                            'ready' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
                            'missing_receipts' => 'bg-red-500/20 text-red-400 border-red-500/30',
                            'in_progress' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                            'future' => 'bg-gray-800 text-gray-600 border-gray-700/50',
                            default => 'bg-gray-800 text-gray-500 border-gray-700'
                        };
                        $statusIcon = match($nav['status']) {
                            'ready' => '✓',
                            'missing_receipts' => '!',
                            'in_progress' => '⟳',
                            'future' => '−',
                            default => '−'
                        };
                    @endphp
                    <button wire:click="selectMonth({{ $num }})"
                            class="shrink-0 snap-start flex flex-col items-center justify-center w-16 h-20 rounded-2xl border transition-all duration-300 relative {{ $isSelected ? 'border-orange-500 bg-orange-500/10 shadow-[0_0_20px_rgba(249,115,22,0.2)]' : 'border-gray-800 bg-gray-950 hover:bg-gray-900' }}">

                        @if($isSelected)
                            <div class="absolute -top-1 w-6 h-1 bg-orange-500 rounded-full shadow-[0_0_10px_rgba(249,115,22,0.8)]"></div>
                        @endif

                        <span class="text-xs font-black uppercase tracking-widest {{ $isSelected ? 'text-white' : 'text-gray-400' }}">{{ $nav['name'] }}</span>
                        <div class="mt-2 w-5 h-5 rounded-full border text-[10px] font-black flex items-center justify-center {{ $statusColor }}">
                            {{ $statusIcon }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- FOCUS AREA: DETAILS ZUM AUSGEWÄHLTEN MONAT --}}
        @php
            $isReady = $activeData['status'] === 'ready';
            $isMissing = $activeData['status'] === 'missing_receipts';
            $isInProgress = $activeData['status'] === 'in_progress';
            $isFuture = $activeData['status'] === 'future';
            $isDeadlinePassed = now()->gt($activeData['deadline']) && !$isReady && !$isFuture;
        @endphp

        <div class="bg-gray-950/50 rounded-[2.5rem] border border-gray-800 p-6 sm:p-10 shadow-inner relative animate-fade-in">

            @if($isMissing)
                <div class="absolute inset-0 rounded-[2.5rem] shadow-[inset_0_0_50px_rgba(239,68,68,0.1)] pointer-events-none"></div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 relative z-10">

                {{-- LINKE SPALTE: Overview & Score --}}
                <div class="lg:col-span-5 space-y-8">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-4xl font-serif font-bold text-white tracking-tight">{{ $activeData['month_name'] }} {{ $activeData['year'] }}</h2>
                            <div class="flex flex-wrap items-center gap-3 mt-3">
                                <span class="px-3 py-1 bg-gray-900 border {{ $isDeadlinePassed ? 'border-red-500/50 text-red-400 animate-pulse shadow-[0_0_10px_rgba(239,68,68,0.3)]' : 'border-gray-700 text-gray-400' }} rounded-md text-[10px] font-black uppercase tracking-widest">
                                    Frist: {{ $activeData['deadline']->format('d.m.Y') }}
                                </span>
                                @if($isReady)
                                    <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-[10px] font-black px-3 py-1 rounded-md uppercase tracking-widest flex items-center gap-1 shadow-inner"><x-heroicon-s-check-circle class="w-4 h-4"/> Bereit</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Status Bar --}}
                    <div>
                        <div class="flex justify-between items-end mb-2">
                            <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Daten-Vollständigkeit</span>
                            <span class="text-lg font-black {{ $isMissing ? 'text-red-400' : 'text-orange-400' }}">{{ $activeData['progress'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-900 h-2.5 rounded-full overflow-hidden border border-gray-800 shadow-inner">
                            <div class="h-full rounded-full transition-all duration-1000 {{ $isMissing ? 'bg-red-500' : 'bg-gradient-to-r from-orange-500 to-amber-400' }}" style="width: {{ $activeData['progress'] }}%"></div>
                        </div>
                        @if($isMissing)
                            <p class="text-xs text-red-400 font-bold mt-3 flex items-center gap-2">
                                <x-heroicon-s-exclamation-triangle class="w-4 h-4 animate-pulse" />
                                Aktion erforderlich: Es fehlen {{ $activeData['missing_receipts_count'] }} Belege!
                            </p>
                        @endif
                    </div>

                    {{-- Exaktes Berechnungs-Schema --}}
                    <div class="bg-gray-900 border border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full blur-2xl pointer-events-none"></div>
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-5 border-b border-gray-800 pb-3 relative z-10">Umsatzsteuer Schema (E-Commerce)</h4>
                        <div class="space-y-3 relative z-10 text-xs font-medium">
                            <div class="flex justify-between items-center text-gray-300">
                                <span>Umsatzsteuer (Verkäufe)</span>
                                <span class="font-mono">+ {{ number_format($activeData['vat_collected'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-400">
                                <span>IG Erwerb (§ 1a UStG)</span>
                                <span class="font-mono">+ {{ number_format($activeData['ig_erwerb_tax'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-400">
                                <span>Reverse Charge (§ 13b UStG)</span>
                                <span class="font-mono">+ {{ number_format($activeData['paragraph_13b_tax'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="border-t border-gray-800 pt-3 flex justify-between items-center text-gray-300 font-bold">
                                <span>= Gesamtsteuer</span>
                                <span class="font-mono text-emerald-400 drop-shadow-[0_0_5px_currentColor]">{{ number_format($activeData['total_tax'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-300 pt-2">
                                <span>- Vorsteuer (Ausgaben)</span>
                                <span class="font-mono text-red-400 drop-shadow-[0_0_5px_currentColor]">- {{ number_format($activeData['vat_paid'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="border-t border-gray-700 pt-4 mt-2 flex justify-between items-end">
                                <span class="text-xs font-black uppercase tracking-widest text-white">Zahllast ans FA</span>
                                <span class="text-2xl font-black {{ $activeData['zahllast'] > 0 ? 'text-orange-400' : 'text-emerald-400' }}">
                                    {{ number_format($activeData['zahllast'], 2, ',', '.') }} €
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Art der Anmeldung --}}
                    <div x-data="{ expanded: false }" class="bg-gray-900/50 border border-gray-800 rounded-xl p-4">
                        <button @click="expanded = !expanded" class="w-full flex items-center justify-between text-[10px] font-black text-gray-500 uppercase tracking-widest hover:text-white transition-colors">
                            <span>Einstellung: {{ $submissionType }}</span>
                            <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                        </button>
                        <div x-show="expanded" x-collapse class="mt-4 pt-4 border-t border-gray-800">
                            <select wire:model.live="submissionType" class="w-full bg-gray-950 border border-gray-800 text-white text-xs font-bold rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/30 outline-none shadow-inner cursor-pointer">
                                <option value="Erstübermittlung">Erstübermittlung (Regulär)</option>
                                <option value="Berichtigte Anmeldung">Berichtigte Anmeldung (Korrektur)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Aktions-Buttons --}}
                    <div class="grid grid-cols-2 gap-4 pt-2">
                        <button wire:click="generateDatevExport" wire:loading.attr="disabled" @disabled($isFuture)
                        class="py-4 bg-gray-900 border border-gray-700 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg flex flex-col items-center justify-center gap-2 group disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-800 hover:text-white hover:border-blue-500 text-gray-400">
                            <x-heroicon-o-document-arrow-down class="w-6 h-6 group-hover:text-blue-400 transition-colors" />
                            <span wire:loading.remove wire:target="generateDatevExport">Report ZIP (Offline)</span>
                            <span wire:loading wire:target="generateDatevExport" class="text-blue-400 animate-pulse">Exportiert...</span>
                        </button>

                        <button wire:click="transmitToElster" wire:loading.attr="disabled" @disabled(!$isReady)
                        class="py-4 bg-gray-900 border border-gray-700 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg flex flex-col items-center justify-center gap-2 group disabled:opacity-50 disabled:cursor-not-allowed hover:border-orange-500/50 hover:bg-orange-500/10 text-gray-400">
                            <x-heroicon-o-paper-airplane class="w-6 h-6 group-hover:text-orange-400 transition-colors" />
                            <span wire:loading.remove wire:target="transmitToElster" class="group-hover:text-orange-400 transition-colors">Test-Senden (ERiC)</span>
                            <span wire:loading wire:target="transmitToElster" class="text-orange-400 animate-pulse">SENDE...</span>
                        </button>
                    </div>
                </div>

                {{-- RECHTE SPALTE: Checkliste & Details --}}
                <div class="lg:col-span-7 flex flex-col gap-6">

                    {{-- Tasks / Fehlende Belege Box --}}
                    @if($isMissing)
                        <div class="bg-red-500/5 border border-red-500/20 rounded-[2rem] p-6 sm:p-8 shadow-inner">
                            <h4 class="text-xs font-black text-red-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                                <x-heroicon-s-exclamation-triangle class="w-5 h-5" />
                                Fehlende Dokumente ({{ $activeData['missing_receipts_count'] }})
                            </h4>
                            <div class="space-y-3 max-h-64 overflow-y-auto custom-scrollbar pr-2">
                                @foreach($activeData['missing_items'] as $item)
                                    <div class="bg-gray-950 p-4 rounded-xl border border-red-500/30 flex justify-between items-center shadow-inner group hover:border-red-500/50 transition-colors">
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-white truncate">{{ $item['title'] }}</p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-[9px] bg-gray-800 text-gray-400 px-2 py-0.5 rounded uppercase font-black tracking-widest">{{ $item['type'] === 'fixed' ? 'Fixkosten' : 'Variabel' }}</span>
                                                <span class="text-[10px] text-gray-500 font-mono">{{ $item['date'] }}</span>
                                            </div>
                                        </div>
                                        <span class="text-red-400 font-mono font-bold text-sm shrink-0 drop-shadow-[0_0_5px_currentColor]">{{ number_format($item['amount'], 2, ',', '.') }} €</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-[10px] text-gray-500 mt-4 italic leading-relaxed">Lade die entsprechenden Belege in der Finanzen-Verwaltung hoch, um 100% Readiness zu erreichen und eine saubere Buchhaltung zu garantieren.</p>
                        </div>
                    @else
                        <div class="bg-emerald-500/5 border border-emerald-500/20 rounded-[2rem] p-8 shadow-inner flex items-center gap-5">
                            <div class="w-14 h-14 bg-emerald-500/20 border border-emerald-500/40 rounded-full flex items-center justify-center text-emerald-400 shrink-0 shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                                <x-heroicon-s-check class="w-7 h-7" />
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-white mb-1">Alle Belege vorhanden!</h4>
                                <p class="text-sm text-gray-400">Die vorbereitende Buchhaltung für diesen Monat ist lückenlos. Du bist bereit für den Export oder die direkte API Übertragung.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Detail-Statistik für BWA --}}
                    <div class="bg-gray-900 border border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-inner flex flex-col mb-6">
                        <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-6 border-b border-gray-800 pb-3 flex items-center gap-2">
                            <x-heroicon-s-chart-bar class="w-4 h-4 text-gray-600" />
                            Betriebswirtschaftliche Kennzahlen
                        </h4>

                        <div class="grid grid-cols-2 gap-6">
                            <div class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner flex flex-col justify-center">
                                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-2 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>Einnahmen</p>
                                <p class="text-2xl font-bold text-white mb-1">{{ $activeData['order_count'] }} <span class="text-xs text-gray-500 font-medium">Orders</span></p>
                                <p class="text-xs text-gray-400 font-mono">Netto: {{ number_format($activeData['revenue_net'], 2, ',', '.') }} €</p>
                            </div>

                            <div class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner flex flex-col justify-center">
                                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-2 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Ausgaben</p>
                                <p class="text-2xl font-bold text-white mb-1">{{ $activeData['expense_count'] }} <span class="text-xs text-gray-500 font-medium">Belege</span></p>
                                <p class="text-xs text-gray-400 font-mono">Netto: {{ number_format($activeData['expenses_net'], 2, ',', '.') }} €</p>
                            </div>

                            <div class="col-span-2">
                                <div class="bg-gradient-to-r from-gray-950 to-gray-900 border border-gray-800 p-5 rounded-2xl flex justify-between items-center shadow-lg">
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-widest font-black mb-1">Vorläufiger EÜR Gewinn</p>
                                        <p class="text-[10px] text-gray-500 font-medium">Betriebsergebnis (Netto) nach Kosten</p>
                                    </div>
                                    <span class="text-3xl font-serif font-bold {{ $activeData['profit'] >= 0 ? 'text-emerald-400 drop-shadow-[0_0_10px_rgba(16,185,129,0.3)]' : 'text-red-400 drop-shadow-[0_0_10px_rgba(239,68,68,0.3)]' }}">
                                        {{ number_format($activeData['profit'], 2, ',', '.') }} €
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- VOLLE BREITE UNTEN: FUNKI TERMINAL LOG --}}
                <div class="col-span-1 lg:col-span-12 mt-4 pt-8 border-t border-gray-800">
                    <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <x-heroicon-s-command-line class="w-4 h-4 text-orange-500" />
                        System- & API-Protokoll (Live)
                    </h4>

                    <div class="bg-[#0D1117] rounded-2xl border border-gray-800 p-4 h-56 overflow-y-auto custom-scrollbar font-mono text-[11px] shadow-inner relative">
                        <div class="absolute top-0 left-0 w-1 h-full bg-orange-500/20"></div>

                        <div class="space-y-1.5 pl-3">
                            @forelse($Logs as $log)
                                @php
                                    $colorClass = match($log['type']) {
                                        'success' => 'text-emerald-400',
                                        'error' => 'text-red-400',
                                        'warning' => 'text-amber-400',
                                        'system' => 'text-purple-400',
                                        default => 'text-blue-300'
                                    };
                                @endphp
                                <div class="flex gap-3 {{ $colorClass }}">
                                    <span class="text-gray-600 shrink-0">[{{ $log['time'] }}]</span>
                                    <span>{{ $log['message'] }}</span>
                                </div>
                            @empty
                                <div class="text-gray-600 italic">Warte auf Aktionen...</div>
                            @endforelse

                            {{-- Blinkender Cursor am Ende --}}
                            <div class="flex gap-3 text-orange-500 mt-2">
                                <span class="text-gray-600 shrink-0">[{{ now()->format('H:i:s.v') }}]</span>
                                <span class="animate-pulse">_</span>
                            </div>
                        </div>
                    </div>

                    {{-- TECHNISCHE SPEZIFIKATIONEN & ELSTER INFOS --}}
                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-4 shadow-inner">
                            <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Übertragungs-Protokoll</p>
                            <p class="text-xs font-mono text-gray-300">ERiC API (Elster Rich Client)</p>
                            <p class="text-[10px] font-mono text-gray-500 mt-1">Kein manueller Web-Login nötig</p>
                        </div>
                        <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-4 shadow-inner">
                            <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Authentifizierung</p>
                            <p class="text-xs font-mono text-gray-300">Lokale X.509 Signatur</p>
                            <p class="text-[10px] font-mono text-orange-400 mt-1">Via .pfx Zertifikatsdatei</p>
                        </div>
                        <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-4 shadow-inner">
                            <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Clearingstelle (Ziel)</p>
                            <p class="text-xs font-mono text-gray-300">datenannahme.elster.de</p>
                            <p class="text-[10px] font-mono text-emerald-500 mt-1">TLS 1.3 / Port 443</p>
                        </div>
                        <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-4 shadow-inner">
                            <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Datenstruktur</p>
                            <p class="text-xs font-mono text-gray-300">UStG Konformes XML</p>
                            <p class="text-[10px] font-mono text-blue-400 mt-1">Strikte Validierung vor Versand</p>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #4b5563; }
    </style>
</div>
