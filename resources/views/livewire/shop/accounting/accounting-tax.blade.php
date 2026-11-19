<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;"
     x-data="{ showPreview: false, showWorkflowGuide: false, showTaxKnowledge: false, openVault: false }">
    <section class="bg-white dark:bg-gray-900/80 rounded-[2.5rem] shadow-sm border border-gray-200 dark:border-gray-800 p-6 sm:p-10 relative overflow-hidden transition-all duration-300 mt-6 w-full text-gray-700 dark:text-gray-300">
        {{-- Matter Abteilungs-Akzentstreifen --}}
        <div class="absolute top-0 left-0 w-1.5 h-full bg-[var(--theme-color)]"></div>

        {{-- HEADER & TRESOR & INFO-MODAL-BUTTONS --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 relative z-10">
            <div class="flex items-center gap-2.5">
                <h3 class="text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3">
                    <i class="solar-document-text-bold-duotone text-[var(--theme-color)] text-2xl"></i>
                    <span>Umsatzsteuer-Zentrale</span>
                </h3>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help select-none"
                      title="Umsatzsteuervoranmeldung (UStVA), deutscher Vorsteuerabzug (Kz 66), DATEV-Export und verschlüsselte ELSTER-ERiC-Übermittlung.">!</span>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                {{-- Ablauf-Guide --}}
                <button type="button" @click="showWorkflowGuide = true"
                        class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all bg-gray-100 dark:bg-gray-950 text-gray-800 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 cursor-pointer"
                        title="Schritt-für-Schritt Ablauf einblenden">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-[var(--theme-color)] shrink-0" />
                    <span>Ablauf-Guide</span>
                </button>

                {{-- Steuer-1x1 --}}
                <button type="button" @click="showTaxKnowledge = true"
                        class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all bg-gray-100 dark:bg-gray-950 text-gray-800 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 cursor-pointer"
                        title="Steuer-1x1 für Mein Seelenfunke">
                    <x-heroicon-s-academic-cap class="w-4 h-4 text-[var(--theme-color)] shrink-0" />
                    <span>Steuer-1x1</span>
                </button>

                {{-- API-Status-Pill --}}
                <div wire:init="checkApiStatus" class="flex items-center gap-2 px-3 py-2.5 bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl">
                    @if($apiStatus === 'checking')
                        <div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                        <span class="text-[10px] text-gray-600 dark:text-gray-300 uppercase tracking-widest font-black hidden sm:inline">Prüfe API...</span>
                    @elseif($apiStatus === 'online')
                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 uppercase tracking-widest font-black hidden sm:inline" title="{{ $apiStatusMessage }}">ERiC Online</span>
                    @else
                        <div class="w-2 h-2 rounded-full bg-red-500"></div>
                        <span class="text-[10px] text-red-600 dark:text-red-400 uppercase tracking-widest font-black hidden sm:inline" title="{{ $apiStatusMessage }}">ERiC Offline</span>
                    @endif
                </div>

                {{-- Jahresauswahl --}}
                <select wire:model.live="selectedYear" class="bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-all outline-none focus:ring-2 focus:ring-[var(--theme-color-30)] cursor-pointer">
                    @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>

                {{-- Tresor Dropdown --}}
                <div class="relative">
                    <button type="button" @click="openVault = !openVault" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-100 dark:bg-gray-950 text-gray-800 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 cursor-pointer">
                        <x-heroicon-m-archive-box class="w-4 h-4 text-[var(--theme-color)]" />
                        <span>Tresor</span>
                    </button>

                    <div x-show="openVault" @click.away="openVault = false" x-cloak class="absolute right-0 top-full mt-3 w-80 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-xl p-2 z-50">
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800 mb-2">
                            <h4 class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Archivierte Exporte</h4>
                        </div>
                        <div class="max-h-64 overflow-y-auto custom-scrollbar px-2 space-y-2">
                            @forelse($archivedExports as $export)
                                <div class="flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 dark:bg-gray-950 dark:hover:bg-gray-800 transition-colors border border-gray-200 dark:border-gray-800 rounded-xl">
                                    <div class="min-w-0 flex-1">
                                        <h5 class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $export['name'] }}</h5>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 font-mono mt-0.5">{{ $export['date'] }} • {{ $export['size'] }}</p>
                                    </div>
                                    <div class="flex gap-1 ml-3">
                                        <button type="button" wire:click.prevent="downloadTaxExport('{{ $export['name'] }}')" class="p-1.5 text-gray-600 dark:text-gray-300 hover:text-[var(--theme-color)] transition-colors cursor-pointer" title="Herunterladen">
                                            <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                        </button>
                                        <button type="button" wire:click="deleteExport('{{ $export['name'] }}')" wire:confirm="Endgültig löschen?" class="p-1.5 text-gray-600 dark:text-gray-300 hover:text-red-500 transition-colors cursor-pointer" title="Löschen">
                                            <x-heroicon-m-trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-xs text-gray-500 dark:text-gray-400 py-6">Keine Exporte im Tresor.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- FLASH ALERTS --}}
        @if(session()->has('success'))
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-950/40 border-l-4 border-emerald-500 p-4 text-emerald-700 dark:text-emerald-300 rounded-r-xl flex items-center gap-3 text-sm font-bold">
                <x-heroicon-s-check-circle class="w-5 h-5 shrink-0" />
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session()->has('success_receipt'))
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-950/40 border-l-4 border-emerald-500 p-4 text-emerald-700 dark:text-emerald-300 rounded-r-xl flex items-center gap-3 text-sm font-bold">
                <x-heroicon-s-check-circle class="w-5 h-5 shrink-0" />
                <span>{{ session('success_receipt') }}</span>
            </div>
        @endif
        @if(session()->has('error'))
            <div class="mb-6 bg-red-50 dark:bg-red-950/40 border-l-4 border-red-500 p-4 text-red-700 dark:text-red-300 rounded-r-xl flex items-center gap-3 text-sm font-bold">
                <x-heroicon-s-exclamation-circle class="w-5 h-5 shrink-0" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- § 19 UStG KOMPAKT-BADGE (NUR FALLS AKTIV) --}}
        @if(!empty($activeData['is_small_business_year']))
            <div class="mb-6 inline-flex items-center gap-2 bg-blue-50 dark:bg-blue-950/40 px-4 py-2 rounded-xl border border-blue-200 dark:border-blue-800 text-xs font-bold text-blue-700 dark:text-blue-300">
                <x-heroicon-s-shield-check class="w-4 h-4 text-blue-500 shrink-0" />
                <span>Kleinunternehmer (§ 19 UStG) {{ $selectedYear }}</span>
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-200/70 dark:bg-blue-900 text-[10px] font-black cursor-help"
                      title="Für das gesamte Steuerjahr {{ $selectedYear }} gilt die Kleinunternehmerregelung (keine USt-Abführung, kein Vorsteuerabzug).">!</span>
            </div>
        @elseif(!empty($activeData['retroactive_vat_collected']) && $activeData['retroactive_vat_collected'] > 0)
            <div class="mb-6 inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-800 px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-xs font-bold text-gray-900 dark:text-white">
                <x-heroicon-s-exclamation-triangle class="w-4 h-4 text-[var(--theme-color)] shrink-0" />
                <span>Rückwirkende USt-Schuld: {{ number_format($activeData['retroactive_vat_collected'], 2, ',', '.') }} €</span>
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-700 text-[10px] font-black cursor-help"
                      title="Wegen unterjährigem Wechsel zur Regelbesteuerung unterliegt das gesamte Kalenderjahr {{ $selectedYear }} nach § 19 Abs. 2 UStG der Regelbesteuerung.">!</span>
            </div>
        @endif

        {{-- ==================== PROMINENTE KARTE: POTENZIELLE STEUERRÜCKGABE ==================== --}}
        @php
            $currentRefund = $refundSummary[$refundScope] ?? $refundSummary['total_all'];
            $netRefundVal = (float) ($currentRefund['net_refund'] ?? 0);
            $isPositiveRefund = $netRefundVal >= 0;
            $refundTooltipText = 'Belegsicher verbucht: ' . number_format($currentRefund['verified_vorsteuer'] ?? 0, 2, ',', '.') . ' € | Vorläufige Belege (Rechnung nachladen): ' . number_format($currentRefund['pending_doc_vorsteuer'] ?? 0, 2, ',', '.') . ' € (' . ($currentRefund['pending_doc_count'] ?? 0) . ' Posten). Ausländische USt. (z. B. 25 % DK) und Versicherungssteuer (0 %) sind automatisch ausgeschlossen.';
        @endphp
        <div class="mb-8 bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-5 sm:p-7 relative z-20"
             x-data="{ showRefundTooltip: false }">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 pb-5 border-b border-gray-200 dark:border-gray-800">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-300">
                                {{ $isPositiveRefund ? 'Potenzielle Steuerrückgabe' : 'Voraussichtliche USt-Zahllast' }}
                            </span>
                            <div class="relative inline-block"
                                 @mouseenter="showRefundTooltip = true"
                                 @mouseleave="showRefundTooltip = false">
                                <button type="button"
                                        @click="showRefundTooltip = !showRefundTooltip"
                                        class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white dark:bg-gray-800 text-[11px] font-black text-[var(--theme-color)] border border-gray-300 dark:border-gray-700 cursor-help"
                                        title="{{ $refundTooltipText }}">!</button>
                                <div x-show="showRefundTooltip" x-cloak
                                     class="absolute left-0 top-full mt-2 w-72 sm:w-80 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl p-3.5 z-50 text-xs text-gray-800 dark:text-gray-200 space-y-2">
                                    <div class="font-black text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 pb-1.5">
                                        Belegstatus Vorsteuer (Stand {{ $refundSummary['as_of'] ?? '' }})
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600 dark:text-gray-300">Belegsicher (7 % / 19 %)</span>
                                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($currentRefund['verified_vorsteuer'] ?? 0, 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600 dark:text-gray-300">Vorläufige Belege ({{ $currentRefund['pending_doc_count'] ?? 0 }})</span>
                                        <span class="font-mono font-bold text-[var(--theme-color)]">{{ number_format($currentRefund['pending_doc_vorsteuer'] ?? 0, 2, ',', '.') }} €</span>
                                    </div>
                                    @if(($currentRefund['pending_doc_count'] ?? 0) > 0)
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-100 dark:border-gray-800">
                                            Für vorläufige Belege (Bestellübersicht / PayPal-Auszug / fehlender Vertrag) Original-USt-Rechnung nachladen.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-2.5">
                            <span class="text-3xl sm:text-4xl font-serif font-bold font-mono tracking-tight {{ $isPositiveRefund ? 'text-emerald-600 dark:text-emerald-400' : 'text-[var(--theme-color)]' }}">
                                {{ $isPositiveRefund ? '+' : '' }}{{ number_format(abs($netRefundVal), 2, ',', '.') }} €
                            </span>
                            <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg border {{ $isPositiveRefund ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' : 'bg-[var(--theme-color-10)] text-gray-900 dark:text-white border-[var(--theme-color-30)]' }}">
                                {{ $isPositiveRefund ? 'Vorsteuer-Überhang' : 'Zahllast' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Kompakter 3er Tab-Umschalter --}}
                <div class="inline-flex flex-wrap bg-white dark:bg-gray-900 p-1 rounded-xl border border-gray-200 dark:border-gray-800 gap-1 self-start lg:self-center">
                    <button type="button" wire:click="setRefundScope('total_all')"
                            class="px-3 py-2 rounded-lg text-[11px] font-bold transition-all cursor-pointer {{ $refundScope === 'total_all' ? 'bg-[var(--theme-color)] text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        Gesamt Bisher
                    </button>
                    <button type="button" wire:click="setRefundScope('since_launch')"
                            class="px-3 py-2 rounded-lg text-[11px] font-bold transition-all cursor-pointer {{ $refundScope === 'since_launch' ? 'bg-[var(--theme-color)] text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        Ab Gründung 11/26
                    </button>
                    <button type="button" wire:click="setRefundScope('selected_period')"
                            class="px-3 py-2 rounded-lg text-[11px] font-bold transition-all cursor-pointer {{ $refundScope === 'selected_period' ? 'bg-[var(--theme-color)] text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        Gewählter Zeitraum
                    </button>
                </div>
            </div>

            {{-- Kompakte Berechnungs-Aufschlüsselung --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-5">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-3 flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600 dark:text-gray-300">Vorsteuer Sonderausgaben</span>
                    <span class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">+ {{ number_format($currentRefund['vorsteuer_special'] ?? 0, 2, ',', '.') }} €</span>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-3 flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600 dark:text-gray-300">Vorsteuer Fixkosten</span>
                    <span class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">+ {{ number_format($currentRefund['vorsteuer_fix'] ?? 0, 2, ',', '.') }} €</span>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-3 flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600 dark:text-gray-300">Umsatzsteuer Verkäufe</span>
                    <span class="font-mono text-sm font-bold text-gray-900 dark:text-white">- {{ number_format($currentRefund['ust_sales'] ?? 0, 2, ',', '.') }} €</span>
                </div>
            </div>
        </div>

        {{-- ==================== ZEITRAUM-WÄHLER: MONAT / QUARTAL (Q1-Q4) ==================== --}}
        <div class="w-full mb-8 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="inline-flex bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 gap-1">
                    <button type="button" wire:click="setPeriodMode('month')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $periodMode === 'month' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm border border-gray-200 dark:border-gray-700' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        Monat
                    </button>
                    <button type="button" wire:click="setPeriodMode('quarter')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $periodMode === 'quarter' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm border border-gray-200 dark:border-gray-700' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        Quartal (Q1-Q4)
                    </button>
                </div>

                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 font-mono">
                    ELSTER Zeitraum: {{ $activeData['elster_zeitraum'] ?? $activeData['month_number'] }} / {{ $activeData['year'] }}
                </span>
            </div>

            @if($periodMode === 'quarter')
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($quartersNav as $qNum => $qNav)
                        @php
                            $isSelectedQ = $selectedQuarter === $qNum;
                            $qStatusColor = match($qNav['status']) {
                                'ready' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
                                'missing_receipts', 'missing_data' => 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30',
                                'in_progress' => 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30',
                                'future' => 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-700',
                                default => 'bg-gray-100 dark:bg-gray-800 text-gray-500 border-gray-200 dark:border-gray-700'
                            };
                            $qStatusIcon = match($qNav['status']) {
                                'ready' => '✓',
                                'missing_receipts', 'missing_data' => '!',
                                'in_progress' => '⟳',
                                default => '-'
                            };
                        @endphp
                        <button type="button" wire:click="selectQuarter({{ $qNum }})"
                                class="flex items-center justify-between px-4 py-3.5 rounded-2xl border transition-all cursor-pointer {{ $isSelectedQ ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                            <div class="text-left">
                                <div class="text-sm font-black {{ $isSelectedQ ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300' }}">{{ $qNav['name'] }}</div>
                                <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400">{{ $qNav['range'] }}</div>
                            </div>
                            <div class="w-6 h-6 rounded-full border text-[10px] font-black flex items-center justify-center {{ $qStatusColor }}">
                                {{ $qStatusIcon }}
                            </div>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-2 snap-x scroll-smooth w-full">
                    @foreach($monthsNav as $num => $nav)
                        @php
                            $isSelected = (int) $selectedMonth === (int) $num;
                            $statusColor = match($nav['status']) {
                                'ready' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
                                'missing_receipts', 'missing_data' => 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30',
                                'in_progress' => 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30',
                                'future' => 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-700',
                                default => 'bg-gray-100 dark:bg-gray-800 text-gray-500 border-gray-200 dark:border-gray-700'
                            };
                            $statusIcon = match($nav['status']) {
                                'ready' => '✓',
                                'missing_receipts', 'missing_data' => '!',
                                'in_progress' => '⟳',
                                default => '-'
                            };
                        @endphp
                        <button type="button" wire:click="selectMonth({{ $num }})"
                                class="shrink-0 snap-start flex flex-col items-center justify-center w-16 h-20 rounded-2xl border transition-all relative cursor-pointer {{ $isSelected ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                            @if($isSelected)
                                <div class="absolute -top-1 w-6 h-1 bg-[var(--theme-color)] rounded-full"></div>
                            @endif
                            <span class="text-xs font-black uppercase tracking-widest {{ $isSelected ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">{{ $nav['name'] }}</span>
                            <div class="mt-2 w-5 h-5 rounded-full border text-[10px] font-black flex items-center justify-center {{ $statusColor }}">
                                {{ $statusIcon }}
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- SCHRITT-FÜR-SCHRITT PROZESS-LEISTE (STEP WIZARD) --}}
        @php
            $isReady = $activeData['status'] === 'ready';
            $isMissingData = $activeData['status'] === 'missing_data';
            $isFuture = $activeData['status'] === 'future';
            $isDeadlinePassed = now()->gt($activeData['deadline']) && !$isReady && !$isFuture;
        @endphp

        <div class="mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                {{-- Tab 1: Zahlen & Belege --}}
                <button type="button" wire:click="setStep(1)"
                        class="p-4 rounded-2xl border transition-all flex items-center justify-between gap-4 text-left cursor-pointer {{ $activeStep === 1 ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-9 h-9 rounded-xl font-mono text-sm font-black flex items-center justify-center shrink-0 {{ $activeStep === 1 ? 'bg-[var(--theme-color)] text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-white' }}">
                            1
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Schritt 1</div>
                            <div class="text-xs font-bold text-gray-900 dark:text-white truncate">Zahlen & Belege prüfen</div>
                        </div>
                    </div>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 shrink-0"
                          title="Prüfe Umsätze (Kz 81 / Kz 86) und Betriebsausgaben (Kz 66). Fehlende Belege direkt über die Checkliste hochladen.">!</span>
                </button>

                {{-- Tab 2: Parameter & Auth --}}
                <button type="button" wire:click="setStep(2)"
                        class="p-4 rounded-2xl border transition-all flex items-center justify-between gap-4 text-left cursor-pointer {{ $activeStep === 2 ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-9 h-9 rounded-xl font-mono text-sm font-black flex items-center justify-center shrink-0 {{ $activeStep === 2 ? 'bg-[var(--theme-color)] text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-white' }}">
                            2
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Schritt 2</div>
                            <div class="text-xs font-bold text-gray-900 dark:text-white truncate">Meldungsart & Auth</div>
                        </div>
                    </div>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 shrink-0"
                          title="Wähle Erstübermittlung oder berichtigte Anmeldung sowie dein ELSTER Software-Zertifikat (.pfx) oder Hardware-Token.">!</span>
                </button>

                {{-- Tab 3: Vorschau & Übermittlung --}}
                <button type="button" wire:click="setStep(3)"
                        class="p-4 rounded-2xl border transition-all flex items-center justify-between gap-4 text-left cursor-pointer {{ $activeStep === 3 ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-9 h-9 rounded-xl font-mono text-sm font-black flex items-center justify-center shrink-0 {{ $activeStep === 3 ? 'bg-[var(--theme-color)] text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-white' }}">
                            3
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Schritt 3</div>
                            <div class="text-xs font-bold text-gray-900 dark:text-white truncate">Vorschau & Übermittlung</div>
                        </div>
                    </div>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 shrink-0"
                          title="Öffne die amtliche XML-Vorschau, erstelle das DATEV-ZIP-Paket für den Steuerberater oder übermittle direkt per ERiC.">!</span>
                </button>
            </div>
        </div>

        {{-- FOCUS AREA: STEP INHALTE --}}
        <div class="bg-gray-50/70 dark:bg-gray-950/60 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-10 relative">

            {{-- ==================== SCHRITT 1 ==================== --}}
            @if($activeStep === 1)
                <div class="space-y-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        {{-- Linke Spalte: Berechnungs-Schema & Readiness --}}
                        <div class="lg:col-span-5 space-y-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">{{ $activeData['month_name'] }} {{ $activeData['year'] }}</h2>
                                    <div class="flex flex-wrap items-center gap-2.5 mt-3">
                                        <span class="px-3 py-1 bg-white dark:bg-gray-900 border {{ $isDeadlinePassed ? 'border-red-500/50 text-red-500 animate-pulse' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' }} rounded-lg text-[10px] font-black uppercase tracking-widest">
                                            Frist: {{ $activeData['deadline']->format('d.m.Y') }}
                                        </span>
                                        @if($isReady)
                                            <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 text-[10px] font-black px-3 py-1 rounded-lg uppercase tracking-widest flex items-center gap-1">
                                                <x-heroicon-s-check-circle class="w-4 h-4"/> Bereit
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Readiness Bar --}}
                            <div>
                                <div class="flex justify-between items-end mb-2">
                                    <span class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Beleg- & Datenvollständigkeit</span>
                                    <span class="text-lg font-black {{ $isMissingData ? 'text-red-500' : 'text-[var(--theme-color)]' }}">{{ $activeData['progress'] }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-900 h-2.5 rounded-full overflow-hidden border border-gray-200 dark:border-gray-800">
                                    <div class="h-full rounded-full transition-all duration-500 {{ $isMissingData ? 'bg-red-500' : 'bg-[var(--theme-color)]' }}" style="width: {{ $activeData['progress'] }}%"></div>
                                </div>
                            </div>

                            {{-- Steuer-Schema Kacheln --}}
                            @php
                                $vat19Val = (float) ($activeData['vat_collected_19'] ?? $activeData['vat_collected'] ?? 0);
                                $vat7Val = (float) ($activeData['vat_collected_7'] ?? 0);
                            @endphp
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm relative">
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-5 border-b border-gray-200 dark:border-gray-800 pb-3 flex items-center gap-2">
                                    <x-heroicon-s-calculator class="w-4 h-4 text-[var(--theme-color)]" />
                                    <span>Umsatzsteuer Schema (E-Commerce)</span>
                                </h4>
                                <div class="space-y-3 text-xs font-medium">
                                    <div class="flex justify-between items-center text-gray-700 dark:text-gray-300">
                                        <span class="flex items-center gap-2">
                                            <span>Umsatzsteuer 19 %</span>
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded font-mono">Kz 81</span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                  title="Umsatzsteuer aus steuerpflichtigen Lieferungen und Leistungen zum Regelsteuersatz (19 %). Bemessungsgrundlage: {{ number_format($activeData['kz81_base'] ?? 0, 0, ',', '.') }} €.">!</span>
                                        </span>
                                        <span class="font-mono font-bold text-gray-900 dark:text-white">{{ $vat19Val > 0 ? '+' : '' }} {{ number_format($vat19Val, 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center text-gray-700 dark:text-gray-300">
                                        <span class="flex items-center gap-2">
                                            <span>Umsatzsteuer 7 %</span>
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded font-mono">Kz 86</span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                  title="Umsatzsteuer aus steuerpflichtigen Umsätzen zum ermäßigten Steuersatz (7 %). Bemessungsgrundlage: {{ number_format($activeData['kz86_base'] ?? 0, 0, ',', '.') }} €.">!</span>
                                        </span>
                                        <span class="font-mono font-bold text-gray-900 dark:text-white">{{ $vat7Val > 0 ? '+' : '' }} {{ number_format($vat7Val, 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                                        <span class="flex items-center gap-2">
                                            <span>IG Erwerb (§ 1a UStG)</span>
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300 px-1.5 py-0.5 rounded font-mono">Kz 89</span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                  title="Steuer auf innergemeinschaftlichen Erwerb aus dem EU-Ausland.">!</span>
                                        </span>
                                        <span class="font-mono text-gray-800 dark:text-gray-300">{{ $activeData['ig_erwerb_tax'] > 0 ? '+' : '' }} {{ number_format($activeData['ig_erwerb_tax'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                                        <span class="flex items-center gap-2">
                                            <span>Reverse Charge (§ 13b)</span>
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300 px-1.5 py-0.5 rounded font-mono">Kz 46</span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                  title="Steuerschuldnerschaft des Leistungsempfängers nach § 13b UStG.">!</span>
                                        </span>
                                        <span class="font-mono text-gray-800 dark:text-gray-300">{{ $activeData['paragraph_13b_tax'] > 0 ? '+' : '' }} {{ number_format($activeData['paragraph_13b_tax'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-800 pt-3 flex justify-between items-center text-gray-900 dark:text-white font-bold">
                                        <span>= Gesamtsteuer</span>
                                        <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($activeData['total_tax'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center text-gray-700 dark:text-gray-300 pt-2">
                                        <span class="flex items-center gap-2">
                                            <span>- Vorsteuer (Ausgaben)</span>
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded font-mono">Kz 66</span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                  title="Abziehbare deutsche Vorsteuer (7 % und 19 %) aus gewerblichen Sonderausgaben und fälligen Fixkosten.">!</span>
                                        </span>
                                        <span class="font-mono text-red-500 dark:text-red-400 font-bold">- {{ number_format($activeData['vat_paid'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-2 flex justify-between items-center">
                                        <span class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">
                                            <span>{{ $activeData['zahllast'] > 0 ? 'Zahllast ans FA' : 'Erstattung (Kz 83)' }}</span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                  title="Kennzahl 83 (Vorauszahlungssoll): Positiver Wert = Überweisung ans Finanzamt, negativer Wert = Vorsteuer-Erstattungsanspruch.">!</span>
                                        </span>
                                        <span class="text-2xl font-black font-mono {{ $activeData['zahllast'] > 0 ? 'text-[var(--theme-color)]' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            {{ number_format($activeData['zahllast'], 2, ',', '.') }} €
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Rechte Spalte: Master Checkliste & BWA --}}
                        <div class="lg:col-span-7 flex flex-col gap-6">
                            {{-- Checkliste --}}
                            @if(!$activeData['all_checklist_passed'])
                                <div class="bg-white dark:bg-gray-900 border border-red-200 dark:border-red-500/30 rounded-[2rem] p-6 sm:p-8 shadow-sm">
                                    <h4 class="text-[10px] font-black text-red-600 dark:text-red-400 uppercase tracking-widest mb-5 flex items-center gap-2">
                                        <x-heroicon-s-exclamation-triangle class="w-5 h-5" />
                                        <span>Vorbedingungen prüfen</span>
                                    </h4>
                                    <div class="space-y-3">
                                        @foreach($activeData['checklist'] as $key => $check)
                                            <div class="flex items-start gap-4 p-3.5 rounded-xl {{ $check['passed'] ? 'bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800' : 'bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-500/30' }}">
                                                <div class="mt-0.5 shrink-0">
                                                    @if($check['passed'])
                                                        <div class="w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-500/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                                            <x-heroicon-s-check class="w-3 h-3" />
                                                        </div>
                                                    @else
                                                        <div class="w-5 h-5 rounded-full bg-red-500/20 border border-red-500/50 flex items-center justify-center text-red-600 dark:text-red-400">
                                                            <x-heroicon-s-x-mark class="w-3 h-3" />
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <p class="text-sm font-bold {{ $check['passed'] ? 'text-gray-600 dark:text-gray-300' : 'text-gray-900 dark:text-white' }}">{{ $check['name'] }}</p>
                                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help shrink-0"
                                                              title="{{ $check['description'] }}">!</span>
                                                    </div>

                                                    {{-- Inline Uploader bei fehlenden Belegen --}}
                                                    @if(!$check['passed'] && $key === 'buchhaltung/receipts')
                                                        <div class="mt-4 space-y-3 max-h-72 overflow-y-auto custom-scrollbar pr-2">
                                                            @foreach($activeData['missing_items'] as $item)
                                                                <div class="bg-white dark:bg-gray-950 p-3.5 rounded-xl border border-red-200 dark:border-red-500/30 flex flex-col gap-3 shadow-sm">
                                                                    <div class="flex justify-between items-start gap-2">
                                                                        <div class="min-w-0">
                                                                            <p class="text-xs font-bold text-gray-900 dark:text-white truncate" title="{{ $item['title'] }}">{{ $item['title'] }}</p>
                                                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1.5">
                                                                                <span class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-gray-600 dark:text-gray-300 uppercase tracking-widest">{{ $item['type'] === 'fixed' ? 'Fix' : 'Var' }}</span>
                                                                                <span>{{ $item['date'] }}</span>
                                                                            </p>
                                                                        </div>
                                                                        <span class="text-red-600 dark:text-red-400 font-mono font-bold text-[11px] shrink-0 bg-red-500/10 px-2.5 py-1 rounded-full border border-red-500/20">{{ number_format($item['amount'], 2, ',', '.') }} €</span>
                                                                    </div>

                                                                    <div class="flex flex-col gap-2">
                                                                        <label class="flex items-center justify-center w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-900 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors relative overflow-hidden">
                                                                            <div wire:loading wire:target="receiptFiles.{{ $item['id'] }}" class="absolute inset-0 bg-white/90 dark:bg-gray-900/90 flex items-center justify-center z-20">
                                                                                <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] animate-pulse">Lade Beleg hoch...</span>
                                                                            </div>

                                                                            <input type="file" wire:model.live="receiptFiles.{{ $item['id'] }}" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf">

                                                                            <div class="flex items-center gap-2 text-[10px] font-bold text-gray-600 dark:text-gray-300 uppercase tracking-widest">
                                                                                <x-heroicon-o-cloud-arrow-up class="w-4 h-4 text-[var(--theme-color)]" />
                                                                                <span>Beleg auswählen (PDF/Bild)</span>
                                                                            </div>
                                                                        </label>

                                                                        @if(isset($receiptFiles[$item['id']]))
                                                                            <div class="flex flex-col gap-2">
                                                                                <p class="text-[10px] text-emerald-600 dark:text-emerald-400 text-center font-mono truncate">{{ $receiptFiles[$item['id']]->getClientOriginalName() }}</p>
                                                                                <button type="button" wire:click="uploadMissingReceipt('{{ $item['id'] }}', '{{ $item['type'] }}')"
                                                                                        class="w-full py-2 bg-[var(--theme-color)] text-white rounded-lg text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-opacity flex items-center justify-center gap-2 cursor-pointer">
                                                                                    <x-heroicon-s-check-circle class="w-4 h-4" />
                                                                                    <span>Jetzt hochladen & verknüpfen</span>
                                                                                </button>
                                                                            </div>
                                                                        @endif
                                                                        @error('receiptFiles.'.$item['id']) <span class="text-red-500 text-[10px] text-center">{{ $message }}</span> @enderror
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @elseif(!$check['passed'] && ($key === 'tax_id' || $key === 'proprietor'))
                                                        <a href="{{ route('admin.configuration') }}" class="inline-block mt-2.5 text-[10px] font-black uppercase tracking-widest text-gray-900 dark:text-white hover:underline border border-[var(--theme-color-30)] bg-[var(--theme-color-10)] px-3 py-1.5 rounded">
                                                            Stammdaten öffnen &rarr;
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-500/30 rounded-[2rem] p-6 sm:p-8 flex items-center gap-4">
                                    <div class="w-11 h-11 bg-emerald-500/20 border border-emerald-500/40 rounded-full flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                                        <x-heroicon-s-check class="w-6 h-6" />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-base font-bold text-gray-900 dark:text-white">Checkliste 100 % erfüllt</h4>
                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                                              title="Alle Stammdaten, Zertifikate und Belege für {{ $activeData['month_name'] }} {{ $activeData['year'] }} sind vollständig vorhanden.">!</span>
                                    </div>
                                </div>
                            @endif

                            {{-- BWA Schnellübersicht --}}
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm flex flex-col">
                                <h4 class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-4 border-b border-gray-200 dark:border-gray-800 pb-3 flex items-center gap-2">
                                    <x-heroicon-s-chart-bar class="w-4 h-4 text-[var(--theme-color)]" />
                                    <span>Betriebswirtschaftliche Kennzahlen</span>
                                </h4>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800">
                                        <p class="text-[9px] text-gray-500 dark:text-gray-400 uppercase tracking-widest font-black mb-1">Erlöse (Netto)</p>
                                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-white">{{ number_format($activeData['revenue_net'], 2, ',', '.') }} €</p>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 font-mono mt-0.5">{{ $activeData['order_count'] }} Belege</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800">
                                        <p class="text-[9px] text-gray-500 dark:text-gray-400 uppercase tracking-widest font-black mb-1">Ausgaben (Netto)</p>
                                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-white">{{ number_format($activeData['expenses_net'], 2, ',', '.') }} €</p>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 font-mono mt-0.5">{{ $activeData['expense_count'] }} Posten</p>
                                    </div>
                                    <div class="col-span-2 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-4 rounded-xl flex justify-between items-center">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] text-gray-600 dark:text-gray-300 uppercase tracking-widest font-black">Vorläufiges Ergebnis</span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                                                  title="Betriebsergebnis (Erlöse Netto abzüglich gewerbliche Ausgaben Netto) vor Einkommen- und Gewerbesteuer.">!</span>
                                        </div>
                                        <span class="text-2xl font-serif font-bold font-mono {{ $activeData['profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                            {{ number_format($activeData['profit'], 2, ',', '.') }} €
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 1 Navigation Footer --}}
                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="setStep(2)" class="px-6 py-3.5 bg-[var(--theme-color)] hover:opacity-90 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-opacity flex items-center gap-2 cursor-pointer">
                            <span>Weiter zu Schritt 2</span>
                            <x-heroicon-m-arrow-right class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            @endif

            {{-- ==================== SCHRITT 2 ==================== --}}
            @if($activeStep === 2)
                <div class="space-y-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {{-- Kachel 1: Meldungs-Art --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm flex flex-col justify-between">
                            <div>
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-6 flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-3">
                                    <x-heroicon-s-document-text class="w-4 h-4 text-[var(--theme-color)]" />
                                    <span>Meldungs-Art</span>
                                </h4>

                                <div class="space-y-3">
                                    <label class="flex items-center justify-between gap-3 p-4 rounded-xl border cursor-pointer transition-all {{ $submissionType === 'Erstübermittlung' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-950' }}">
                                        <span class="flex items-center gap-3">
                                            <input type="radio" wire:model.live="submissionType" value="Erstübermittlung" class="text-[var(--theme-color)] focus:ring-[var(--theme-color-30)]">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">Erstübermittlung (Regulär)</span>
                                        </span>
                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                              title="Normale Umsatzsteuer-Voranmeldung für den gewählten Zeitraum (Kennzahl 10 = 0).">!</span>
                                    </label>

                                    <label class="flex items-center justify-between gap-3 p-4 rounded-xl border cursor-pointer transition-all {{ $submissionType === 'Berichtigte Anmeldung' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-950' }}">
                                        <span class="flex items-center gap-3">
                                            <input type="radio" wire:model.live="submissionType" value="Berichtigte Anmeldung" class="text-[var(--theme-color)] focus:ring-[var(--theme-color-30)]">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">Berichtigte Anmeldung (Korrektur)</span>
                                        </span>
                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                              title="Überschreibt eine bereits abgegebene Voranmeldung desselben Zeitraums (Kennzahl 10 = 1).">!</span>
                                    </label>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-300 flex items-center justify-between">
                                <span>Auswahl</span>
                                <strong class="text-gray-900 dark:text-white">{{ $submissionType }}</strong>
                            </div>
                        </div>

                        {{-- Kachel 2: Authentifizierung & Tresor --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm">
                            <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-6 flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-3">
                                <x-heroicon-s-key class="w-4 h-4 text-[var(--theme-color)]" />
                                <span>Authentifizierung & ELSTER-Zertifikat</span>
                            </h4>

                            <div class="grid grid-cols-2 gap-3 mb-6">
                                <button type="button" wire:click="$set('authMethod', 'software')"
                                        class="py-3 px-4 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer {{ $authMethod === 'software' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                                    <x-heroicon-s-document-check class="w-4 h-4 shrink-0 text-[var(--theme-color)]" />
                                    <span>Software-Zertifikat (.pfx)</span>
                                </button>
                                <button type="button" wire:click="$set('authMethod', 'hardware')"
                                        class="py-3 px-4 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer {{ $authMethod === 'hardware' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                                    <x-heroicon-s-cpu-chip class="w-4 h-4 shrink-0 text-[var(--theme-color)]" />
                                    <span>Hardware-Token (USB)</span>
                                </button>
                            </div>

                            @if($authMethod === 'hardware')
                                <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-5 rounded-2xl" x-data="{ showHwPin: false }">
                                    <label class="text-[10px] font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-3 flex items-center justify-between">
                                        <span class="flex items-center gap-2"><x-heroicon-s-key class="w-4 h-4 text-[var(--theme-color)]" /> Applikations-PIN (6-stellig)</span>
                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                                              title="Die secunet-PIN wird nicht gespeichert und ausschließlich zur Laufzeit an die ERiC-Signatur übergeben.">!</span>
                                    </label>
                                    <div class="relative">
                                        <input :type="showHwPin ? 'text' : 'password'" wire:model.live="hardwarePin" maxlength="6"
                                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white text-center text-xl tracking-[0.6em] font-mono rounded-xl pl-4 pr-11 py-3 focus:ring-2 focus:ring-[var(--theme-color-30)] outline-none font-black"
                                               placeholder="••••••">
                                        <button type="button" @click="showHwPin = !showHwPin" tabindex="-1" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-500 dark:text-white hover:text-[var(--theme-color)] cursor-pointer">
                                            <x-heroicon-o-eye x-show="!showHwPin" class="w-5 h-5" />
                                            <x-heroicon-o-eye-slash x-show="showHwPin" x-cloak class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-2 flex items-center gap-2">
                                            <x-heroicon-s-folder-open class="w-4 h-4 text-[var(--theme-color)]" />
                                            <span>Zertifikat aus Tresor</span>
                                        </label>

                                        @if(count($tresorCertificates) > 0)
                                            <select wire:model.live="selectedCertName"
                                                    class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold rounded-xl px-4 py-3 focus:ring-2 focus:ring-[var(--theme-color-30)] outline-none cursor-pointer font-mono">
                                                <option value="">-- Zertifikat auswählen --</option>
                                                @foreach($tresorCertificates as $cert)
                                                    <option value="{{ $cert }}">{{ $cert }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 text-xs p-3.5 rounded-xl text-center">
                                                Keine .pfx Zertifikate im Tresor gefunden.
                                            </div>
                                        @endif
                                    </div>

                                    @php
                                        $isTestCert = in_array($selectedCertName, ['test-soft-pse.pfx', 'test-softorg-pse.pfx', 'test-softidnr-pse.pfx']) || str_contains($selectedCertName, 'bescheid');
                                    @endphp

                                    @if($isTestCert)
                                        <div class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 p-3.5 rounded-xl flex items-center justify-between gap-3 font-bold text-xs">
                                            <span class="flex items-center gap-2">
                                                <x-heroicon-s-check-badge class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                <span>ELSTER Sandbox-Zertifikat aktiv</span>
                                            </span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                  title="Die offizielle Test-PIN (123456) wird automatisch an die ERiC-Schnittstelle übergeben.">!</span>
                                        </div>
                                    @elseif($hasEnvPassword)
                                        <div class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 p-3.5 rounded-xl flex items-center justify-between gap-3 font-bold text-xs">
                                            <span class="flex items-center gap-2">
                                                <x-heroicon-s-shield-check class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                <span>Zertifikats-Passwort aus Server-Konfiguration aktiv</span>
                                            </span>
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                  title="Das PFX-Passwort ist geschützt über ERIC_CERT_PASSWORD in der Server-.env hinterlegt.">!</span>
                                        </div>
                                    @else
                                        <div x-data="{ showCertPw: false }">
                                            <label class="text-[10px] font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-2 flex items-center justify-between">
                                                <span class="flex items-center gap-2"><x-heroicon-s-lock-closed class="w-4 h-4 text-[var(--theme-color)]" /> Zertifikats-Passwort</span>
                                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help"
                                                      title="Wird nicht gespeichert. Optional dauerhaft als ERIC_CERT_PASSWORD in der .env hinterlegbar.">!</span>
                                            </label>
                                            <div class="relative">
                                                <input :type="showCertPw ? 'text' : 'password'" wire:model.live="certPassword"
                                                       class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 text-gray-900 dark:text-white text-center text-lg tracking-widest font-mono rounded-xl pl-4 pr-11 py-3 focus:ring-2 focus:ring-[var(--theme-color-30)] outline-none font-bold"
                                                       placeholder="ELSTER Passwort">
                                                <button type="button" @click="showCertPw = !showCertPw" tabindex="-1" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-500 dark:text-white hover:text-[var(--theme-color)] focus:outline-none cursor-pointer">
                                                    <x-heroicon-o-eye x-show="!showCertPw" class="w-5 h-5" />
                                                    <x-heroicon-o-eye-slash x-show="showCertPw" x-cloak class="w-5 h-5" />
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Step 2 Navigation Footer --}}
                    <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="setStep(1)" class="px-5 py-3 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-800 dark:text-white text-xs font-black uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-800 transition-all flex items-center gap-2 cursor-pointer">
                            <x-heroicon-m-arrow-left class="w-4 h-4" />
                            <span>Zurück zu Schritt 1</span>
                        </button>
                        <button type="button" wire:click="setStep(3)" class="px-6 py-3.5 bg-[var(--theme-color)] hover:opacity-90 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-opacity flex items-center gap-2 cursor-pointer">
                            <span>Weiter zu Schritt 3</span>
                            <x-heroicon-m-arrow-right class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            @endif

            {{-- ==================== SCHRITT 3 ==================== --}}
            @if($activeStep === 3)
                <div class="space-y-8">
                    {{-- Aktions-Karten --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {{-- 1. DATEV Export --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 shadow-sm flex flex-col justify-between">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white flex items-center justify-center shrink-0">
                                        <x-heroicon-o-document-arrow-down class="w-6 h-6 text-[var(--theme-color)]" />
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">DATEV EXTF Report (ZIP)</h4>
                                </div>
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help shrink-0"
                                      title="Exportiert den DATEV-Buchungsstapel (CSV Windows-1252), das PDF-Steuerprotokoll und alle Belegdateien als ZIP-Archiv in den Tresor.">!</span>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <button type="button" wire:click="generateDatevExport" wire:loading.attr="disabled" @disabled($isFuture)
                                        class="w-full py-3 bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="generateDatevExport">Report ZIP erstellen</span>
                                    <span wire:loading wire:target="generateDatevExport" class="animate-pulse">Exportiert...</span>
                                </button>
                            </div>
                        </div>

                        {{-- 2. Formular Vorschau --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 shadow-sm flex flex-col justify-between">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white flex items-center justify-center shrink-0">
                                        <x-heroicon-o-document-text class="w-6 h-6 text-[var(--theme-color)]" />
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Formular-Vorschau (XML)</h4>
                                </div>
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help shrink-0"
                                      title="Zeigt die Kennzahlen Kz 81, Kz 86, Kz 66 und Kz 83 sowie die 13-stellige ELSTER-Steuernummer im amtlichen Formular-Layout.">!</span>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <button type="button" @click="showPreview = true"
                                        class="w-full py-3 bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                    <span>Vorschau öffnen</span>
                                </button>
                            </div>
                        </div>

                        {{-- 3. ELSTER Übermittlung --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 shadow-sm flex flex-col justify-between">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-[var(--theme-color-15)] text-[var(--theme-color)] flex items-center justify-center shrink-0">
                                        <x-heroicon-o-paper-airplane class="w-6 h-6" />
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">An ELSTER übermitteln</h4>
                                </div>
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 cursor-help shrink-0"
                                      title="Signiert die Umsatzsteuer-Voranmeldung lokal mit dem Zertifikat und sendet das XML verschlüsselt an datenannahme.elster.de.">!</span>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <button type="button" wire:click="transmitToElster" wire:loading.attr="disabled" @disabled(!$activeData['is_ready_for_transmit'])
                                        class="w-full py-3 bg-[var(--theme-color)] hover:opacity-90 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-opacity flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="transmitToElster">Jetzt absenden</span>
                                    <span wire:loading wire:target="transmitToElster" class="animate-pulse">Sende via ERiC...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Kompakte Status-Rückmeldung Pre-Flight --}}
                    @if(!$activeData['is_ready_for_transmit'])
                        <div class="p-5 border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-950/30 rounded-2xl space-y-2.5">
                            <h5 class="text-xs font-black uppercase text-red-600 dark:text-red-400 tracking-wider flex items-center gap-2">
                                <x-heroicon-s-exclamation-triangle class="w-4 h-4 shrink-0" />
                                <span>Übermittlung gesperrt: Offene Punkte</span>
                            </h5>
                            <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-1.5 list-disc list-inside font-medium">
                                @if($activeData['status'] !== 'ready')
                                    @foreach($activeData['checklist'] as $key => $check)
                                        @if(!$check['passed'])
                                            <li>{{ $check['name'] }}</li>
                                        @endif
                                    @endforeach
                                @endif
                                @if($authMethod === 'software' && !$hasEnvPassword && strlen($certPassword) < 3 && !in_array($selectedCertName, ['test-soft-pse.pfx', 'test-softorg-pse.pfx', 'test-softidnr-pse.pfx']))
                                    <li>Zertifikats-Passwort fehlt (Schritt 2)</li>
                                @endif
                                @if($authMethod === 'hardware' && strlen($hardwarePin) < 6)
                                    <li>6-stellige secunet-PIN fehlt (Schritt 2)</li>
                                @endif
                            </ul>
                        </div>
                    @else
                        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-500/30 rounded-2xl flex items-center gap-3 text-xs text-emerald-800 dark:text-emerald-300 font-bold">
                            <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <span>Alle Vorbedingungen erfüllt: Bereit zur Übermittlung</span>
                        </div>
                    @endif

                    {{-- LIVE SYSTEM- & API-PROTOKOLL TERMINAL --}}
                    <div class="pt-6 border-t border-gray-200 dark:border-gray-800">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                <x-heroicon-s-command-line class="w-4 h-4 text-[var(--theme-color)]" />
                                <span>System- & API-Protokoll</span>
                            </h4>

                            <button type="button" wire:click="checkApiStatus" wire:loading.attr="disabled"
                                    class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-1.5 rounded-lg text-[10px] font-black text-gray-800 dark:text-white uppercase tracking-widest transition-colors flex items-center gap-2 cursor-pointer disabled:opacity-50">
                                <x-heroicon-s-signal class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                <span wire:loading.remove wire:target="checkApiStatus">API Ping Test</span>
                                <span wire:loading wire:target="checkApiStatus" class="animate-pulse">Pinge Server...</span>
                            </button>
                        </div>

                        <div class="bg-gray-950 rounded-2xl border border-gray-800 p-4 h-52 overflow-y-auto custom-scrollbar font-mono text-[11px] relative text-gray-200">
                            <div class="absolute top-0 left-0 w-1 h-full bg-[var(--theme-color)]"></div>
                            <div class="space-y-1.5 pl-3">
                                @forelse($globalLogs as $log)
                                    @php
                                        $colorClass = match($log['type']) {
                                            'success' => 'text-emerald-400',
                                            'error' => 'text-red-400',
                                            'warning' => 'text-amber-400',
                                            'system' => 'text-gray-300',
                                            default => 'text-blue-300'
                                        };
                                    @endphp
                                    <div class="flex gap-3 {{ $colorClass }}">
                                        <span class="text-gray-500 shrink-0">[{{ $log['time'] }}]</span>
                                        <span>{{ $log['message'] }}</span>
                                    </div>
                                @empty
                                    <div class="text-gray-500">Bereit.</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- Kompakte Technische Kennwerte ohne Subtexte --}}
                        <div class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                                <p class="text-[9px] text-gray-500 dark:text-gray-400 uppercase tracking-widest font-black mb-1">Protokoll</p>
                                <p class="text-xs font-mono font-bold text-gray-900 dark:text-white">ERiC C++ Client</p>
                            </div>
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                                <p class="text-[9px] text-gray-500 dark:text-gray-400 uppercase tracking-widest font-black mb-1">Signatur</p>
                                <p class="text-xs font-mono font-bold text-gray-900 dark:text-white">X.509 (.pfx / PIN)</p>
                            </div>
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                                <p class="text-[9px] text-gray-500 dark:text-gray-400 uppercase tracking-widest font-black mb-1">Clearingstelle</p>
                                <p class="text-xs font-mono font-bold text-gray-900 dark:text-white">datenannahme.elster.de</p>
                            </div>
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                                <p class="text-[9px] text-gray-500 dark:text-gray-400 uppercase tracking-widest font-black mb-1">ELSTER Steuernr.</p>
                                <p class="text-xs font-mono font-bold text-gray-900 dark:text-white">{{ $formattedElsterTaxId ?: 'Nicht gesetzt' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3 Navigation Footer --}}
                    <div class="flex justify-start pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="setStep(2)" class="px-5 py-3 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-800 dark:text-white text-xs font-black uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-800 transition-all flex items-center gap-2 cursor-pointer">
                            <x-heroicon-m-arrow-left class="w-4 h-4" />
                            <span>Zurück zu Schritt 2</span>
                        </button>
                    </div>
                </div>
            @endif

        </div>

        {{-- ==================== MODAL: ABLAUF-GUIDE ==================== --}}
        <div x-show="showWorkflowGuide" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div x-show="showWorkflowGuide" x-transition.opacity class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="showWorkflowGuide = false"></div>

            <div x-show="showWorkflowGuide" x-transition.scale.95
                 class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl w-full max-w-3xl text-gray-800 dark:text-gray-200 overflow-hidden font-sans p-6 sm:p-8 z-10">
                <div class="flex justify-between items-center pb-4 border-b border-gray-200 dark:border-gray-800 mb-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-s-rocket-launch class="w-5 h-5 text-[var(--theme-color)]" />
                        <span>Ablauf-Guide: UStVA Übermittlung</span>
                    </h3>
                    <button type="button" @click="showWorkflowGuide = false" class="text-gray-500 dark:text-white hover:opacity-75 p-2 rounded-xl transition-opacity cursor-pointer">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="bg-gray-50 dark:bg-gray-950 p-5 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <div class="w-8 h-8 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-bold flex items-center justify-center text-xs mb-3">1</div>
                        <h5 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">Belege prüfen</h5>
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            Alle gewerblichen Rechnungen als PDF oder Bild verknüpfen, damit die deutsche Vorsteuer (7 % / 19 %) in Kz 66 rechtssicher berücksichtigt wird.
                        </p>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-950 p-5 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <div class="w-8 h-8 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-bold flex items-center justify-center text-xs mb-3">2</div>
                        <h5 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">Authentifizieren</h5>
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            Zertifikat (.pfx) aus dem Tresor wählen und Passwort freigeben (oder automatisch aus der Server-Konfiguration nutzen).
                        </p>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-950 p-5 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <div class="w-8 h-8 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-bold flex items-center justify-center text-xs mb-3">3</div>
                        <h5 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">Export & Versand</h5>
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            DATEV-ZIP für das Steuerbüro archivieren oder die UStVA direkt per ERiC mit offiziellem Transferticket übermitteln.
                        </p>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                    <button type="button" @click="showWorkflowGuide = false" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white rounded-xl text-xs font-bold transition-colors cursor-pointer">
                        Schließen
                    </button>
                </div>
            </div>
        </div>

        {{-- ==================== MODAL: STEUER-1x1 ==================== --}}
        <div x-show="showTaxKnowledge" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div x-show="showTaxKnowledge" x-transition.opacity class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="showTaxKnowledge = false"></div>

            <div x-show="showTaxKnowledge" x-transition.scale.95
                 class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl w-full max-w-4xl text-gray-800 dark:text-gray-200 overflow-hidden font-sans p-6 sm:p-8 z-10 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <div class="flex justify-between items-center pb-4 border-b border-gray-200 dark:border-gray-800 mb-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-s-academic-cap class="w-6 h-6 text-[var(--theme-color)]" />
                        <span>Steuer-1x1 für {{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}</span>
                    </h3>
                    <button type="button" @click="showTaxKnowledge = false" class="text-gray-500 dark:text-white hover:opacity-75 p-2 rounded-xl transition-opacity cursor-pointer">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-gray-50 dark:bg-gray-950 rounded-2xl p-6 border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <h5 class="text-sm font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-mono text-xs flex items-center justify-center">1</span>
                                <span>Einkommensteuer (ESt)</span>
                            </h5>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                                Fällt privat auf den Jahresgewinn der Einnahmen-Überschuss-Rechnung (EÜR) nach Abzug aller Betriebsausgaben und Freibeträge an.
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-800 text-[11px] text-gray-600 dark:text-gray-400">
                            <strong class="text-[var(--theme-color)] block mb-1">Praxistipp:</strong>
                            Monatlich rund 20-30 % des Gewinns auf einem separaten Rücklagenkonto parken.
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-950 rounded-2xl p-6 border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <h5 class="text-sm font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-mono text-xs flex items-center justify-center">2</span>
                                <span>Umsatzsteuer & Vorsteuer</span>
                            </h5>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                                Vereinnahmte deutsche MwSt. (19 % Kz 81 / 7 % Kz 86) abzüglich gezahlter deutscher Vorsteuer (Kz 66). Übersteigt die Vorsteuer die Umsatzsteuer, erstattet das Finanzamt den Differenzbetrag.
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-800 text-[11px] text-gray-600 dark:text-gray-400">
                            <strong class="text-[var(--theme-color)] block mb-1">Wichtig:</strong>
                            Versicherungssteuer (19 % VersSt) und ausländische EU-Mehrwertsteuer sind nicht in Kz 66 abziehbar, mindern aber voll den Gewinn in der EÜR.
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-950 rounded-2xl p-6 border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <h5 class="text-sm font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-mono text-xs flex items-center justify-center">3</span>
                                <span>Gewerbesteuer (GewSt)</span>
                            </h5>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                                Für Einzelunternehmen gilt ein gesetzlicher Freibetrag von 24.500 € Jahresgewinn. Darunter fällt 0,00 € Gewerbesteuer an.
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-800 text-[11px] text-gray-600 dark:text-gray-400">
                            <strong class="text-[var(--theme-color)] block mb-1">Turnus:</strong>
                            Einmal jährlich im Rahmen der Jahressteuererklärung.
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                    <button type="button" @click="showTaxKnowledge = false" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white rounded-xl text-xs font-bold transition-colors cursor-pointer">
                        Schließen
                    </button>
                </div>
            </div>
        </div>

        {{-- ==================== MODAL: ELSTER XML VORSCHAU ==================== --}}
        <div x-show="showPreview" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div x-show="showPreview" x-transition.opacity class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="showPreview = false"></div>

            <div x-show="showPreview" x-transition.scale.95
                 class="relative bg-[#f4f7f4] border-2 border-[#5b8c5a] rounded-2xl shadow-2xl w-full max-w-4xl text-gray-800 overflow-hidden font-sans z-10">
                <div class="bg-[#5b8c5a] text-white p-4 flex justify-between items-center">
                    <h2 class="text-lg font-bold tracking-tight">Umsatzsteuer-Voranmeldung {{ $activeData['year'] }} (Schema v{{ $activeData['year'] }})</h2>
                    <button type="button" @click="showPreview = false" class="text-white hover:bg-white/20 p-2 rounded-full transition-colors cursor-pointer">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-6 sm:p-8 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-b-2 border-[#5b8c5a]/30 pb-5">
                        <div>
                            <p class="text-xs text-[#5b8c5a] font-bold uppercase mb-1">13-stellige ELSTER-Steuernummer</p>
                            <div class="bg-white border border-[#5b8c5a]/50 px-3 py-2 font-mono text-sm rounded">
                                {{ $formattedElsterTaxId ?: shop_setting('owner_tax_id', 'Nicht hinterlegt') }}
                            </div>
                        </div>
                        <div>
                            <p class="text-xs text-[#5b8c5a] font-bold uppercase mb-1">Voranmeldungszeitraum</p>
                            <div class="bg-white border border-[#5b8c5a]/50 px-3 py-2 font-mono text-sm rounded">
                                {{ $activeData['elster_zeitraum'] ?? $activeData['month_number'] }} / {{ $activeData['year'] }} ({{ $activeData['month_name'] }})
                            </div>
                        </div>
                    </div>

                    {{-- Kennzahl 81 --}}
                    <div>
                        <h3 class="text-xs font-bold text-[#5b8c5a] uppercase border-b border-[#5b8c5a]/30 pb-1.5 mb-3">Steuerpflichtige Umsätze (Kz 81 & Kz 86)</h3>
                        <div class="space-y-2.5">
                            <div class="flex items-center gap-4 bg-white p-3 border border-[#5b8c5a]/20 relative pl-12 rounded">
                                <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center rounded-l">81</div>
                                <div class="flex-1 text-xs">Steuerpflichtige Umsätze zum allgemeinen Steuersatz (19 %)</div>
                                <div class="w-36 bg-gray-100 border border-gray-300 px-3 py-1.5 font-mono text-right font-bold text-gray-800 rounded">
                                    {{ number_format($activeData['kz81_base'] ?? 0, 0, '', '') }}
                                </div>
                                <span class="text-xs font-mono text-gray-500 w-16">Volle EUR</span>
                            </div>

                            <div class="flex items-center gap-4 bg-white p-3 border border-[#5b8c5a]/20 relative pl-12 rounded">
                                <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center rounded-l">86</div>
                                <div class="flex-1 text-xs">Steuerpflichtige Umsätze zum ermäßigten Steuersatz (7 %)</div>
                                <div class="w-36 bg-gray-100 border border-gray-300 px-3 py-1.5 font-mono text-right font-bold text-gray-800 rounded">
                                    {{ number_format($activeData['kz86_base'] ?? 0, 0, '', '') }}
                                </div>
                                <span class="text-xs font-mono text-gray-500 w-16">Volle EUR</span>
                            </div>
                        </div>
                    </div>

                    {{-- Kennzahl 66 --}}
                    <div>
                        <h3 class="text-xs font-bold text-[#5b8c5a] uppercase border-b border-[#5b8c5a]/30 pb-1.5 mb-3">Abziehbare Vorsteuerbeträge (Kz 66)</h3>
                        <div class="flex items-center gap-4 bg-white p-3 border border-[#5b8c5a]/20 relative pl-12 rounded">
                            <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center rounded-l">66</div>
                            <div class="flex-1 text-xs">Vorsteuerbeträge aus Rechnungen von anderen Unternehmern (7 % / 19 % DE)</div>
                            <div class="w-36 bg-gray-100 border border-gray-300 px-3 py-1.5 font-mono text-right font-bold text-gray-800 rounded">
                                {{ number_format($activeData['vat_paid'], 2, ',', '.') }}
                            </div>
                            <span class="text-xs font-mono text-gray-500 w-16">EUR, Ct</span>
                        </div>
                    </div>

                    {{-- Kennzahl 83 --}}
                    <div class="pt-3 border-t-2 border-[#5b8c5a]/30">
                        <div class="flex items-center gap-4 bg-[#5b8c5a]/10 p-4 border border-[#5b8c5a]/30 relative pl-12 rounded">
                            <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center rounded-l">83</div>
                            <div class="flex-1 text-xs font-bold text-[#5b8c5a]">Verbleibende Umsatzsteuer-Vorauszahlung / Verbleibender Überschuss</div>
                            <div class="w-36 bg-white border-2 border-[#5b8c5a] px-3 py-2 font-mono text-right font-black text-base text-gray-900 rounded">
                                {{ number_format($activeData['zahllast'], 2, ',', '.') }}
                            </div>
                            <span class="text-xs font-mono text-gray-600 font-bold w-16">EUR, Ct</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.06); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .dark .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.3); }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #4b5563; }
    </style>
</div>
