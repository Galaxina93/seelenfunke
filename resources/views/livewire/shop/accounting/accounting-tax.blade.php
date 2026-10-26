<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;"
     x-data="{ showPreview: false, showWorkflowGuide: false, showTaxKnowledge: false, openVault: false }">
    <section class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-10 relative overflow-hidden transition-all duration-500 mt-6 w-full text-gray-700 dark:text-gray-300">
        {{-- Glow-Streifen --}}
        <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-[var(--theme-color)] to-red-600 opacity-60"></div>

        {{-- HEADER & TRESOR & INFO-MODAL-BUTTONS --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6 relative z-10">
            <div>
                <h3 class="text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3">
                    <i class="solar-document-text-bold-duotone text-[var(--theme-color)] text-2xl"></i>
                    Umsatzsteuer-Zentrale
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Umsatzsteuervoranmeldung (UStVA), Vorsteuerabzug & ERiC-Übermittlung</p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                {{-- Info-Icon Button 1: Ablauf-Guide --}}
                <button type="button" @click="showWorkflowGuide = true"
                        class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800 shadow-sm cursor-pointer"
                        title="Schritt-für-Schritt Ablauf einblenden">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 shrink-0" />
                    <span>Ablauf-Guide</span>
                </button>

                {{-- Info-Icon Button 2: Steuer-1x1 --}}
                <button type="button" @click="showTaxKnowledge = true"
                        class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all bg-gray-100 dark:bg-gray-950 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 shadow-sm cursor-pointer"
                        title="Steuer-1x1 für Mein Seelenfunke">
                    <x-heroicon-s-academic-cap class="w-4 h-4 text-[var(--theme-color)] shrink-0" />
                    <span>Steuer-1x1</span>
                </button>

                {{-- API-Status-Pill --}}
                <div wire:init="checkApiStatus" class="flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl shadow-none dark:shadow-inner">
                    @if($apiStatus === 'checking')
                        <div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse shadow-[0_0_8px_rgba(251,191,36,0.8)]"></div>
                        <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black hidden sm:inline">Prüfe API...</span>
                    @elseif($apiStatus === 'online')
                        <div class="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></div>
                        <span class="text-[9px] text-emerald-500 uppercase tracking-widest font-black hidden sm:inline" title="{{ $apiStatusMessage }}">ERiC Online</span>
                    @else
                        <div class="w-2 h-2 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]"></div>
                        <span class="text-[9px] text-red-500 uppercase tracking-widest font-black hidden sm:inline" title="{{ $apiStatusMessage }}">ERiC Offline</span>
                    @endif
                </div>

                {{-- Jahresauswahl --}}
                <select wire:model.live="selectedYear" class="bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-300 px-4 py-2.5 rounded-xl text-xs font-bold shadow-none dark:shadow-inner transition-all outline-none focus:ring-2 focus:ring-[var(--theme-color-30)] cursor-pointer">
                    @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>

                {{-- Tresor Dropdown --}}
                <div class="relative">
                    <button @click="openVault = !openVault" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-100 dark:bg-gray-950 text-gray-700 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-gray-800 shadow-none dark:shadow-inner hover:scale-[1.02]">
                        <x-heroicon-m-archive-box class="w-4 h-4 text-[var(--theme-color)]" />
                        Tresor
                    </button>

                    <div x-show="openVault" @click.away="openVault = false" x-cloak class="absolute right-0 top-full mt-3 w-80 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-xl dark:shadow-[0_20px_50px_rgba(0,0,0,0.8)] p-2 z-50 animate-fade-in-up">
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800 mb-2">
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Archivierte Exporte</h4>
                        </div>
                        <div class="max-h-64 overflow-y-auto custom-scrollbar px-2 space-y-2">
                            @forelse($archivedExports as $export)
                                <div class="flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 dark:bg-gray-950 dark:hover:bg-gray-800 transition-colors group border border-gray-200 dark:border-gray-800 rounded-xl shadow-none dark:shadow-inner">
                                    <div class="min-w-0 flex-1">
                                        <h5 class="text-xs font-bold text-gray-900 dark:text-gray-200 truncate">{{ $export['name'] }}</h5>
                                        <p class="text-[9px] text-gray-500 font-mono mt-1">{{ $export['date'] }} • {{ $export['size'] }}</p>
                                    </div>
                                    <div class="flex gap-1 ml-3">
                                        <button wire:click.prevent="downloadTaxExport('{{ $export['name'] }}')" class="p-1.5 text-gray-500 hover:text-[var(--theme-color)] transition-colors inline-block focus:outline-none cursor-pointer" title="Herunterladen">
                                            <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                        </button>
                                        <button wire:click="deleteExport('{{ $export['name'] }}')" wire:confirm="Endgültig löschen?" class="p-1.5 text-gray-500 hover:text-red-500 transition-colors cursor-pointer" title="Löschen">
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

        {{-- FLASH ALERTS --}}
        @if(session()->has('success'))
            <div class="mb-6 bg-emerald-500/10 border-l-4 border-emerald-500 p-4 text-emerald-600 dark:text-emerald-400 shadow-inner rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
                <x-heroicon-s-check-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor] shrink-0" />
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session()->has('success_receipt'))
            <div class="mb-6 bg-emerald-500/10 border-l-4 border-emerald-500 p-4 text-emerald-600 dark:text-emerald-400 shadow-inner rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
                <x-heroicon-s-check-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor] shrink-0" />
                <span>{{ session('success_receipt') }}</span>
            </div>
        @endif
        @if(session()->has('error'))
            <div class="mb-6 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-600 dark:text-red-400 shadow-inner rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
                <x-heroicon-s-exclamation-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor] shrink-0" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- § 19 UStG STATUS BANNER --}}
        @if(!empty($activeData['is_small_business_year']))
            <div class="mb-6 flex items-start gap-3 bg-blue-500/10 p-4 rounded-2xl border border-blue-500/20 shadow-inner">
                <x-heroicon-s-shield-check class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
                <div class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed font-medium">
                    <strong class="text-blue-600 dark:text-blue-400">Kleinunternehmer (§ 19 UStG):</strong> Für das gesamte Steuerjahr {{ $selectedYear }} gilt die Kleinunternehmerregelung. Es wird keine Umsatzsteuer abgeführt und kein Vorsteuerabzug geltend gemacht (UStVA-Zahllast: 0,00 €).
                </div>
            </div>
        @elseif(!empty($activeData['retroactive_vat_collected']) && $activeData['retroactive_vat_collected'] > 0)
            <div class="mb-6 flex items-start gap-3 bg-amber-500/10 p-4 rounded-2xl border border-amber-500/30 shadow-inner">
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                <div class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed font-medium">
                    <strong class="text-amber-600 dark:text-amber-400">Rückwirkende USt-Schuld (§ 19 UStG):</strong> Wegen des unterjährigen Wechsels zur Regelbesteuerung sind in diesem Monat <strong>{{ number_format($activeData['retroactive_vat_collected'], 2, ',', '.') }} €</strong> Umsatzsteuer fällig (aus den Brutto-Umsätzen herausgerechnet), da nach § 19 Abs. 2 UStG das gesamte Kalenderjahr {{ $selectedYear }} der Regelbesteuerung unterliegt.
                </div>
            </div>
        @endif

        {{-- MONATS-NAVIGATION (Slider) --}}
        <div class="w-full mb-8 relative group">
            <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-3 snap-x scroll-smooth w-full px-1">
                @foreach($monthsNav as $num => $nav)
                    @php
                        $isSelected = $selectedMonth == $num;
                        $statusColor = match($nav['status']) {
                            'ready' => 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
                            'missing_receipts', 'missing_data' => 'bg-red-500/20 text-red-600 dark:text-red-400 border-red-500/30',
                            'in_progress' => 'bg-blue-500/20 text-blue-600 dark:text-blue-400 border-blue-500/30',
                            'future' => 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600 border-gray-200 dark:border-gray-700/50',
                            default => 'bg-gray-100 dark:bg-gray-800 text-gray-500 border-gray-200 dark:border-gray-700'
                        };
                        $statusIcon = match($nav['status']) {
                            'ready' => '✓',
                            'missing_receipts', 'missing_data' => '!',
                            'in_progress' => '⟳',
                            'future' => '−',
                            default => '−'
                        };
                    @endphp
                    <button wire:click="selectMonth({{ $num }})"
                            class="shrink-0 snap-start flex flex-col items-center justify-center w-16 h-20 rounded-2xl border transition-all duration-300 relative cursor-pointer {{ $isSelected ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-[0_0_20px_var(--theme-color-20)]' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 hover:bg-gray-100 dark:hover:bg-gray-900' }}">

                        @if($isSelected)
                            <div class="absolute -top-1 w-6 h-1 bg-[var(--theme-color)] rounded-full shadow-[0_0_10px_var(--theme-color)]"></div>
                        @endif

                        <span class="text-xs font-black uppercase tracking-widest {{ $isSelected ? 'text-[var(--theme-color)] dark:text-white font-bold' : 'text-gray-500 dark:text-gray-400' }}">{{ $nav['name'] }}</span>
                        <div class="mt-2 w-5 h-5 rounded-full border text-[10px] font-black flex items-center justify-center {{ $statusColor }}">
                            {{ $statusIcon }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- SCHRITT-FÜR-SCHRITT PROZESS-LEISTE (STEP WIZARD) --}}
        @php
            $isReady = $activeData['status'] === 'ready';
            $isMissingData = $activeData['status'] === 'missing_data';
            $isInProgress = $activeData['status'] === 'in_progress';
            $isFuture = $activeData['status'] === 'future';
            $isDeadlinePassed = now()->gt($activeData['deadline']) && !$isReady && !$isFuture;
            $glowClass = $isMissingData ? 'shadow-[inset_0_0_50px_rgba(239,68,68,0.06)]' : ($isReady ? 'shadow-[inset_0_0_50px_rgba(16,185,129,0.05)]' : '');
        @endphp

        <div class="mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                {{-- Tab 1: Zahlen & Vorsteuer --}}
                <button wire:click="setStep(1)"
                        class="p-4 rounded-2xl border transition-all flex items-center gap-4 text-left cursor-pointer {{ $activeStep === 1 ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-sm' : 'border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                    <div class="w-9 h-9 rounded-xl font-mono text-sm font-black flex items-center justify-center shrink-0 {{ $activeStep === 1 ? 'bg-[var(--theme-color)] text-white shadow-md' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}">
                        1
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Schritt 1</div>
                        <div class="text-xs font-bold text-gray-900 dark:text-white truncate">Zahlen & Belege prüfen</div>
                    </div>
                </button>

                {{-- Tab 2: Parameter & Authentifizierung --}}
                <button wire:click="setStep(2)"
                        class="p-4 rounded-2xl border transition-all flex items-center gap-4 text-left cursor-pointer {{ $activeStep === 2 ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-sm' : 'border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                    <div class="w-9 h-9 rounded-xl font-mono text-sm font-black flex items-center justify-center shrink-0 {{ $activeStep === 2 ? 'bg-[var(--theme-color)] text-white shadow-md' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}">
                        2
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Schritt 2</div>
                        <div class="text-xs font-bold text-gray-900 dark:text-white truncate">Meldungsart & Auth</div>
                    </div>
                </button>

                {{-- Tab 3: Vorschau & Übermittlung --}}
                <button wire:click="setStep(3)"
                        class="p-4 rounded-2xl border transition-all flex items-center gap-4 text-left cursor-pointer {{ $activeStep === 3 ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-sm' : 'border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                    <div class="w-9 h-9 rounded-xl font-mono text-sm font-black flex items-center justify-center shrink-0 {{ $activeStep === 3 ? 'bg-[var(--theme-color)] text-white shadow-md' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}">
                        3
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Schritt 3</div>
                        <div class="text-xs font-bold text-gray-900 dark:text-white truncate">Vorschau & Übermittlung</div>
                    </div>
                </button>
            </div>
        </div>

        {{-- FOCUS AREA: STEP INHALTE --}}
        <div class="bg-gray-50/50 dark:bg-gray-950/50 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-10 shadow-none dark:shadow-inner relative animate-fade-in {{ $glowClass }}">

            {{-- ==================== SCHRITT 1 ==================== --}}
            @if($activeStep === 1)
                <div class="space-y-8">
                    {{-- Blauer Info-Hinweis mit Ausrufezeichen (!) --}}
                    <div class="flex items-start gap-4 bg-blue-50 dark:bg-blue-950/40 p-5 sm:p-6 rounded-2xl border border-blue-200 dark:border-blue-800/70 shadow-sm">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-black flex items-center justify-center text-sm shrink-0 shadow-sm mt-0.5">!</div>
                        <div class="text-xs text-blue-900 dark:text-blue-200 leading-relaxed font-medium space-y-1">
                            <strong class="text-blue-700 dark:text-blue-300 font-bold block text-sm">Schritt 1: Prüfen & Vorbereiten ({{ $activeData['month_name'] }} {{ $activeData['year'] }})</strong>
                            <p>Überprüfe alle Umsätze (Kz 81) und Betriebsausgaben (Kz 66). Fehlende Belege führen zum Ausschluss des Vorsteuerabzugs. Lade fehlende Belege direkt über die Master-Checkliste hoch, bis das System 100% Bereit meldet.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        {{-- Linke Spalte: Berechnungs-Schema & Readiness --}}
                        <div class="lg:col-span-5 space-y-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">{{ $activeData['month_name'] }} {{ $activeData['year'] }}</h2>
                                    <div class="flex flex-wrap items-center gap-2.5 mt-3">
                                        <span class="px-3 py-1 bg-white dark:bg-gray-900 border {{ $isDeadlinePassed ? 'border-red-500/50 text-red-500 animate-pulse shadow-sm' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400' }} rounded-lg text-[10px] font-black uppercase tracking-widest">
                                            Frist: {{ $activeData['deadline']->format('d.m.Y') }}
                                        </span>
                                        @if($isReady)
                                            <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 text-[10px] font-black px-3 py-1 rounded-lg uppercase tracking-widest flex items-center gap-1 shadow-inner">
                                                <x-heroicon-s-check-circle class="w-4 h-4"/> Bereit
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Readiness Bar --}}
                            <div>
                                <div class="flex justify-between items-end mb-2">
                                    <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Beleg- & Datenvollständigkeit</span>
                                    <span class="text-lg font-black {{ $isMissingData ? 'text-red-500' : 'text-[var(--theme-color)]' }}">{{ $activeData['progress'] }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-900 h-2.5 rounded-full overflow-hidden border border-gray-200 dark:border-gray-800 shadow-none dark:shadow-inner">
                                    <div class="h-full rounded-full transition-all duration-1000 {{ $isMissingData ? 'bg-red-500' : 'bg-gradient-to-r from-[var(--theme-color)] to-amber-400' }}" style="width: {{ $activeData['progress'] }}%"></div>
                                </div>
                                @if($isMissingData)
                                    <p class="text-xs text-red-500 dark:text-red-400 font-bold mt-2.5 flex items-center gap-2">
                                        <x-heroicon-s-exclamation-triangle class="w-4 h-4 animate-pulse shrink-0" />
                                        <span>Bitte Checkliste rechts prüfen: Es fehlen noch Belege oder Stammdaten!</span>
                                    </p>
                                @endif
                            </div>

                            {{-- Steuer-Schema Kacheln --}}
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm dark:shadow-xl relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-32 h-32 bg-[var(--theme-color-5)] rounded-bl-full blur-2xl pointer-events-none"></div>
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-5 border-b border-gray-200 dark:border-gray-800 pb-3 relative z-10 flex items-center gap-2">
                                    <x-heroicon-s-calculator class="w-4 h-4 text-[var(--theme-color)]" />
                                    Umsatzsteuer Schema (E-Commerce)
                                </h4>
                                <div class="space-y-3 relative z-10 text-xs font-medium">
                                    <div class="flex justify-between items-center text-gray-700 dark:text-gray-300">
                                        <span class="flex items-center gap-2">
                                            Umsatzsteuer (Verkäufe) 
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded font-mono">Kz 81</span>
                                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors select-none" title="Erhaltene Umsatzsteuer aus deinen normalen Produktverkäufen an Kunden." />
                                        </span>
                                        <span class="font-mono font-bold text-gray-900 dark:text-white">{{ $activeData['vat_collected'] > 0 ? '+' : '' }} {{ number_format($activeData['vat_collected'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                                        <span class="flex items-center gap-2">
                                            IG Erwerb (§ 1a UStG) 
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-500 px-1.5 py-0.5 rounded font-mono">Kz 89</span>
                                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors select-none" title="Steuer auf im EU-Ausland eingekaufte Waren (Nullsummenspiel)." />
                                        </span>
                                        <span class="font-mono text-gray-800 dark:text-gray-300">{{ $activeData['ig_erwerb_tax'] > 0 ? '+' : '' }} {{ number_format($activeData['ig_erwerb_tax'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center text-gray-600 dark:text-gray-400">
                                        <span class="flex items-center gap-2">
                                            Reverse Charge (§ 13b) 
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-500 px-1.5 py-0.5 rounded font-mono">Kz 46</span>
                                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors select-none" title="Steuerschuldnerschaft des Empfängers für B2B Werbeanzeigen (Meta/Google)." />
                                        </span>
                                        <span class="font-mono text-gray-800 dark:text-gray-300">{{ $activeData['paragraph_13b_tax'] > 0 ? '+' : '' }} {{ number_format($activeData['paragraph_13b_tax'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-800 pt-3 flex justify-between items-center text-gray-900 dark:text-gray-300 font-bold">
                                        <span>= Gesamtsteuer</span>
                                        <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($activeData['total_tax'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center text-gray-700 dark:text-gray-300 pt-2">
                                        <span class="flex items-center gap-2">
                                            - Vorsteuer (Ausgaben) 
                                            <span class="text-[9px] bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded font-mono">Kz 66</span>
                                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-red-500 cursor-help transition-colors select-none" title="Gezahlte Umsatzsteuer auf betriebliche Rechnungen, Quittungen und Abos." />
                                        </span>
                                        <span class="font-mono text-red-500 dark:text-red-400 font-bold">- {{ number_format($activeData['vat_paid'], 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-2 flex justify-between items-end">
                                        <span class="flex flex-col gap-0.5">
                                            <span class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">
                                                Zahllast ans FA
                                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors select-none" title="Der Endbetrag, der ans Finanzamt zu überweisen ist bzw. erstattet wird." />
                                            </span>
                                            <span class="text-[9px] text-gray-500 font-medium">Kennzahl 83 (Vorauszahlungssoll)</span>
                                        </span>
                                        <span class="text-2xl font-black {{ $activeData['zahllast'] > 0 ? 'text-[var(--theme-color)]' : 'text-emerald-600 dark:text-emerald-400' }}">
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
                                <div class="bg-white dark:bg-gray-900 border border-red-200 dark:border-red-500/20 rounded-[2rem] p-6 sm:p-8 shadow-sm dark:shadow-inner relative overflow-hidden">
                                    <div class="absolute inset-0 bg-red-500/5 pointer-events-none"></div>
                                    <h4 class="text-[10px] font-black text-red-600 dark:text-red-400 uppercase tracking-widest mb-6 flex items-center gap-2 relative z-10">
                                        <x-heroicon-s-exclamation-triangle class="w-5 h-5 animate-pulse" />
                                        Aktion erforderlich: Vorbedingungen prüfen
                                    </h4>
                                    <div class="space-y-4 relative z-10">
                                        @foreach($activeData['checklist'] as $key => $check)
                                            <div class="flex items-start gap-4 p-3 rounded-xl {{ $check['passed'] ? 'bg-gray-50/80 dark:bg-gray-950/50 border border-gray-200 dark:border-gray-800' : 'bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30' }}">
                                                <div class="mt-0.5 shrink-0">
                                                    @if($check['passed'])
                                                        <div class="w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-500/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                                            <x-heroicon-s-check class="w-3 h-3" />
                                                        </div>
                                                    @else
                                                        <div class="w-5 h-5 rounded-full bg-red-500/20 border border-red-500/50 flex items-center justify-center text-red-600 dark:text-red-400 animate-pulse">
                                                            <x-heroicon-s-x-mark class="w-3 h-3" />
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-bold {{ $check['passed'] ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $check['name'] }}</p>
                                                    <p class="text-[10px] {{ $check['passed'] ? 'text-gray-500 dark:text-gray-600' : 'text-gray-600 dark:text-gray-400' }} mt-1 leading-relaxed">{{ $check['description'] }}</p>

                                                    {{-- Inline Uploader bei fehlenden Belegen --}}
                                                    @if(!$check['passed'] && $key === 'buchhaltung/receipts')
                                                        <div class="mt-4 space-y-3 max-h-72 overflow-y-auto custom-scrollbar pr-2">
                                                            @if(session()->has('success_receipt'))
                                                                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-[10px] p-2.5 rounded-xl text-center animate-fade-in font-bold uppercase tracking-widest shadow-inner mb-2">
                                                                    <x-heroicon-s-check-circle class="w-4 h-4 inline-block mr-1" /> {{ session('success_receipt') }}
                                                                </div>
                                                            @endif

                                                            @foreach($activeData['missing_items'] as $item)
                                                                <div class="bg-white dark:bg-gray-950 p-3.5 rounded-xl border border-red-200 dark:border-red-500/30 flex flex-col gap-3 group relative overflow-hidden transition-all shadow-sm">
                                                                    <div class="flex justify-between items-start">
                                                                        <div class="min-w-0 pr-3">
                                                                            <p class="text-xs font-bold text-gray-900 dark:text-gray-300 truncate" title="{{ $item['title'] }}">{{ $item['title'] }}</p>
                                                                            <p class="text-[9px] text-gray-500 mt-1 flex items-center gap-1.5">
                                                                                <span class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-gray-600 dark:text-gray-400 uppercase tracking-widest">{{ $item['type'] === 'fixed' ? 'Fix' : 'Var' }}</span>
                                                                                {{ $item['date'] }}
                                                                            </p>
                                                                        </div>
                                                                        <span class="text-red-500 font-mono font-bold text-[11px] shrink-0 bg-red-500/10 px-2.5 py-1 rounded-full border border-red-500/20">{{ number_format($item['amount'], 2, ',', '.') }} €</span>
                                                                    </div>

                                                                    {{-- Uploader --}}
                                                                    <div class="mt-1 flex flex-col gap-2">
                                                                        <label class="flex items-center justify-center w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-900 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 hover:border-blue-500/50 transition-colors relative overflow-hidden group/upload">
                                                                            <div wire:loading wire:target="receiptFiles.{{ $item['id'] }}" class="absolute inset-0 bg-blue-500/10 flex items-center justify-center backdrop-blur-sm z-20">
                                                                                <span class="text-[10px] font-black uppercase tracking-widest text-blue-500 animate-pulse">Lade Beleg hoch...</span>
                                                                            </div>

                                                                            <input type="file" wire:model.live="receiptFiles.{{ $item['id'] }}" class="hidden" accept="image/jpeg,image/png,application/pdf">

                                                                            <div class="flex items-center gap-2 text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest group-hover/upload:text-blue-500 transition-colors">
                                                                                <x-heroicon-o-cloud-arrow-up class="w-4 h-4" />
                                                                                <span>Beleg auswählen (PDF/Bild)</span>
                                                                            </div>
                                                                        </label>

                                                                        @if(isset($receiptFiles[$item['id']]))
                                                                            <div class="animate-fade-in flex flex-col gap-2">
                                                                                <p class="text-[9px] text-emerald-600 dark:text-emerald-400 text-center font-mono truncate">Bereit: {{ $receiptFiles[$item['id']]->getClientOriginalName() }}</p>
                                                                                <button wire:click="uploadMissingReceipt('{{ $item['id'] }}', '{{ $item['type'] }}')"
                                                                                        class="w-full py-2 bg-blue-500/10 border border-blue-500/30 rounded-lg text-[10px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest hover:bg-blue-500/20 transition-colors flex items-center justify-center gap-2 shadow-inner cursor-pointer">
                                                                                    <x-heroicon-s-check-circle class="w-4 h-4" /> Jetzt hochladen & verknüpfen
                                                                                </button>
                                                                            </div>
                                                                        @endif
                                                                        @error('receiptFiles.'.$item['id']) <span class="text-red-500 text-[9px] text-center mt-1">{{ $message }}</span> @enderror
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @elseif(!$check['passed'] && ($key === 'tax_id' || $key === 'proprietor'))
                                                        <a href="{{ route('admin.configuration') }}" class="inline-block mt-3 text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] hover:underline border border-[var(--theme-color-30)] bg-[var(--theme-color-10)] px-3 py-1.5 rounded transition-all">
                                                            Zu den Stammdaten-Einstellungen &rarr;
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="bg-emerald-50 dark:bg-emerald-500/5 border border-emerald-200 dark:border-emerald-500/20 rounded-[2rem] p-6 sm:p-8 shadow-sm flex items-center gap-5 relative overflow-hidden">
                                    <div class="w-12 h-12 bg-emerald-500/20 border border-emerald-500/40 rounded-full flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                                        <x-heroicon-s-check class="w-6 h-6" />
                                    </div>
                                    <div>
                                        <h4 class="text-base font-bold text-gray-900 dark:text-white mb-1">Checkliste zu 100% erfüllt</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">Alle Stammdaten und Belege für {{ $activeData['month_name'] }} sind vollständig kontiert.</p>
                                    </div>
                                </div>
                            @endif

                            {{-- BWA Schnellübersicht --}}
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm dark:shadow-inner flex flex-col">
                                <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-4 border-b border-gray-200 dark:border-gray-800 pb-3 flex items-center gap-2">
                                    <x-heroicon-s-chart-bar class="w-4 h-4 text-gray-500" />
                                    Betriebswirtschaftliche Monats-Kennzahlen
                                </h4>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800">
                                        <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Erlöse (Netto)</p>
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($activeData['revenue_net'], 2, ',', '.') }} €</p>
                                        <p class="text-[10px] text-gray-400 font-mono mt-0.5">{{ $activeData['order_count'] }} Bestellungen</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800">
                                        <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Ausgaben (Netto)</p>
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($activeData['expenses_net'], 2, ',', '.') }} €</p>
                                        <p class="text-[10px] text-gray-400 font-mono mt-0.5">{{ $activeData['expense_count'] }} Posten</p>
                                    </div>
                                    <div class="col-span-2 bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-950 dark:to-gray-900 border border-gray-200 dark:border-gray-800 p-4 rounded-xl flex justify-between items-center">
                                        <div>
                                            <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black">Vorläufiges Monatsergebnis</p>
                                            <p class="text-[10px] text-gray-400">Betriebsergebnis vor ESt/GewSt</p>
                                        </div>
                                        <span class="text-2xl font-serif font-bold {{ $activeData['profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                            {{ number_format($activeData['profit'], 2, ',', '.') }} €
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 1 Navigation Footer --}}
                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button wire:click="setStep(2)" class="px-6 py-3.5 bg-[var(--theme-color)] hover:bg-[var(--theme-color-70)] text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md flex items-center gap-2 cursor-pointer">
                            <span>Weiter zu Schritt 2: Parameter & Auth</span>
                            <x-heroicon-m-arrow-right class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            @endif

            {{-- ==================== SCHRITT 2 ==================== --}}
            @if($activeStep === 2)
                <div class="space-y-8">
                    {{-- Blauer Info-Hinweis mit Ausrufezeichen (!) --}}
                    <div class="flex items-start gap-4 bg-blue-50 dark:bg-blue-950/40 p-5 sm:p-6 rounded-2xl border border-blue-200 dark:border-blue-800/70 shadow-sm">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-black flex items-center justify-center text-sm shrink-0 shadow-sm mt-0.5">!</div>
                        <div class="text-xs text-blue-900 dark:text-blue-200 leading-relaxed font-medium space-y-1">
                            <strong class="text-blue-700 dark:text-blue-300 font-bold block text-sm">Schritt 2: Meldungsart & Authentifizierung festlegen</strong>
                            <p>Wähle, ob es sich um eine Erstübermittlung oder um eine Korrektur (berichtigte Anmeldung) handelt. Zur elektronischen Signatur nutzt du entweder ein Software-Zertifikat (.pfx aus dem Tresor) oder einen Hardware-Token (secunet USB).</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {{-- Kachel 1: Meldungs-Art --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm flex flex-col justify-between">
                            <div>
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-6 flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-3">
                                    <x-heroicon-s-document-text class="w-4 h-4 text-[var(--theme-color)]" />
                                    Meldungs-Art (Voranmeldungs-Typ)
                                </h4>

                                <div class="space-y-3">
                                    <label class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-all {{ $submissionType === 'Erstübermittlung' ? 'border-[var(--theme-color)] bg-[var(--theme-color-5)]' : 'border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-950' }}">
                                        <input type="radio" wire:model.live="submissionType" value="Erstübermittlung" class="text-[var(--theme-color)] focus:ring-[var(--theme-color-30)]">
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-gray-900 dark:text-white">Erstübermittlung (Regulär)</p>
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Normale monatliche Voranmeldung (Kennzahl 10 = 0)</p>
                                        </div>
                                    </label>

                                    <label class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-all {{ $submissionType === 'Berichtigte Anmeldung' ? 'border-[var(--theme-color)] bg-[var(--theme-color-5)]' : 'border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-950' }}">
                                        <input type="radio" wire:model.live="submissionType" value="Berichtigte Anmeldung" class="text-[var(--theme-color)] focus:ring-[var(--theme-color-30)]">
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-gray-900 dark:text-white">Berichtigte Anmeldung (Korrektur)</p>
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Überschreibt eine bereits abgegebene Voranmeldung (Kennzahl 10 = 1)</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800 text-[11px] text-gray-500">
                                Aktuell gewählt: <strong class="text-gray-900 dark:text-white">{{ $submissionType }}</strong>
                            </div>
                        </div>

                        {{-- Kachel 2: Authentifizierung & Tresor --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm">
                            <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-6 flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-3">
                                <x-heroicon-s-key class="w-4 h-4 text-emerald-500" />
                                Authentifizierung & ELSTER-Zertifikat
                            </h4>

                            {{-- Auth Methode Auswahlschalter --}}
                            <div class="grid grid-cols-2 gap-3 mb-6">
                                <button type="button" wire:click="$set('authMethod', 'software')"
                                        class="py-3 px-4 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer {{ $authMethod === 'software' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 shadow-sm' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                                    <x-heroicon-s-document-check class="w-4 h-4 shrink-0" />
                                    <span>Software-Zertifikat (.pfx)</span>
                                </button>
                                <button type="button" wire:click="$set('authMethod', 'hardware')"
                                        class="py-3 px-4 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer {{ $authMethod === 'hardware' ? 'border-blue-500 bg-blue-500/10 text-blue-700 dark:text-blue-400 shadow-sm' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900' }}">
                                    <x-heroicon-s-cpu-chip class="w-4 h-4 shrink-0" />
                                    <span>Hardware-Token (USB)</span>
                                </button>
                            </div>

                            @if($authMethod === 'hardware')
                                <div class="bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/60 p-5 rounded-2xl">
                                    <label class="block text-[10px] font-black text-blue-700 dark:text-blue-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                        <x-heroicon-s-key class="w-4 h-4" /> Applikations-PIN (6-stellig)
                                    </label>
                                    <input type="password" wire:model.live="hardwarePin" maxlength="6"
                                           class="w-full bg-white dark:bg-gray-950 border border-blue-300 dark:border-blue-800 text-gray-900 dark:text-white text-center text-xl tracking-[1em] font-mono rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none shadow-sm transition-all font-black"
                                           placeholder="••••••">
                                    <p class="text-[10px] text-gray-500 mt-3 leading-relaxed">
                                        Die secunet-PIN wird nicht gespeichert. Sie wird zur Signaturerzeugung via ERiC-Schnittstelle benötigt.
                                    </p>
                                </div>
                            @else
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-2 flex items-center gap-2">
                                            <x-heroicon-s-folder-open class="w-4 h-4 text-emerald-500" />
                                            Zertifikat aus Tresor wählen
                                        </label>

                                        @if(count($tresorCertificates) > 0)
                                            <select wire:model.live="selectedCertName"
                                                    class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold rounded-xl px-4 py-3 focus:ring-2 focus:ring-emerald-500 outline-none shadow-sm cursor-pointer font-mono hover:border-emerald-500 transition-colors">
                                                <option value="">-- Zertifikat auswählen --</option>
                                                @foreach($tresorCertificates as $cert)
                                                    <option value="{{ $cert }}">{{ $cert }}</option>
                                                @endforeach
                                            </select>

                                            {{-- Offizielle ELSTER Test-Zertifikate Hinweise --}}
                                            @if($selectedCertName === 'test-soft-pse.pfx' || $selectedCertName === 'test-softorg-pse.pfx' || $selectedCertName === 'test-softidnr-pse.pfx' || str_contains($selectedCertName, 'bescheid'))
                                                <div class="mt-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-xl p-3.5 animate-fade-in text-xs">
                                                    <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400 mb-1.5">
                                                        <x-heroicon-s-information-circle class="w-4 h-4" /> Offizielles ELSTER Test-Zertifikat
                                                    </div>
                                                    <p class="text-[11px] text-gray-700 dark:text-gray-300 leading-relaxed mb-2 font-medium">
                                                        Sandbox-Testzertifikat aktiv. Die Test-PIN (123456) wird bei der Übermittlung automatisch übergeben.
                                                    </p>
                                                </div>
                                            @endif
                                        @else
                                            <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 text-xs p-3.5 rounded-xl text-center">
                                                Keine .pfx Zertifikate im Tresor-Pfad <code class="bg-gray-100 dark:bg-gray-900 px-1 py-0.5 rounded text-[10px]">{{ env('ERIC_TRESOR_PATH', 'storage/app/buchhaltung/erictresor') }}</code> gefunden!
                                            </div>
                                        @endif
                                    </div>

                                    @php
                                        $isTestCert = in_array($selectedCertName, ['test-soft-pse.pfx', 'test-softorg-pse.pfx', 'test-softidnr-pse.pfx']) || str_contains($selectedCertName, 'bescheid');
                                    @endphp

                                    @if($isTestCert)
                                        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 p-3.5 rounded-xl flex items-center gap-3 font-medium text-xs">
                                            <x-heroicon-s-check-badge class="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" />
                                            <span>Die Sandbox Test-PIN (123456) wird automatisch an die ERiC API übergeben.</span>
                                        </div>
                                    @elseif($hasEnvPassword)
                                        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 p-3.5 rounded-xl flex items-center gap-3 font-medium text-xs">
                                            <x-heroicon-s-shield-check class="w-5 h-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" />
                                            <span>Zertifikats-Passwort wurde hochsicher aus der Server-Konfiguration <code class="bg-white dark:bg-gray-950 px-1 py-0.5 rounded text-[10px] font-mono">.env</code> geladen.</span>
                                        </div>
                                    @else
                                        <div x-data="{ showCertPw: false }">
                                            <label class="block text-[10px] font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-2 flex items-center gap-2">
                                                <x-heroicon-s-lock-closed class="w-4 h-4 text-emerald-500" />
                                                Zertifikats-Passwort (Mein ELSTER)
                                            </label>
                                            <div class="relative">
                                                <input :type="showCertPw ? 'text' : 'password'" wire:model.live="certPassword"
                                                       class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 text-gray-900 dark:text-white text-center text-lg tracking-widest font-mono rounded-xl pl-4 pr-11 py-3 focus:ring-2 focus:ring-emerald-500 outline-none shadow-sm hover:border-emerald-500 transition-colors font-bold"
                                                       placeholder="Ihr ELSTER Passwort">
                                                <button type="button" @click="showCertPw = !showCertPw" tabindex="-1" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-emerald-500 focus:outline-none cursor-pointer">
                                                    <x-heroicon-o-eye x-show="!showCertPw" class="w-5 h-5" />
                                                    <x-heroicon-o-eye-slash x-show="showCertPw" x-cloak class="w-5 h-5 text-emerald-500" />
                                                </button>
                                            </div>
                                            <p class="text-[10px] text-gray-500 mt-2 leading-relaxed">
                                                Wird nicht gespeichert. Sie können das Passwort auch dauerhaft als <code class="bg-gray-100 dark:bg-gray-900 px-1 py-0.5 rounded text-[9px]">ERIC_CERT_PASSWORD</code> in der .env hinterlegen.
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Step 2 Navigation Footer --}}
                    <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button wire:click="setStep(1)" class="px-5 py-3 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-xs font-black uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-800 transition-all flex items-center gap-2 cursor-pointer">
                            <x-heroicon-m-arrow-left class="w-4 h-4" />
                            <span>Zurück zu Schritt 1</span>
                        </button>
                        <button wire:click="setStep(3)" class="px-6 py-3.5 bg-[var(--theme-color)] hover:bg-[var(--theme-color-70)] text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md flex items-center gap-2 cursor-pointer">
                            <span>Weiter zu Schritt 3: Vorschau & Übermittlung</span>
                            <x-heroicon-m-arrow-right class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            @endif

            {{-- ==================== SCHRITT 3 ==================== --}}
            @if($activeStep === 3)
                <div class="space-y-8">
                    {{-- Blauer Info-Hinweis mit Ausrufezeichen (!) --}}
                    <div class="flex items-start gap-4 bg-blue-50 dark:bg-blue-950/40 p-5 sm:p-6 rounded-2xl border border-blue-200 dark:border-blue-800/70 shadow-sm">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-black flex items-center justify-center text-sm shrink-0 shadow-sm mt-0.5">!</div>
                        <div class="text-xs text-blue-900 dark:text-blue-200 leading-relaxed font-medium space-y-1">
                            <strong class="text-blue-700 dark:text-blue-300 font-bold block text-sm">Schritt 3: Vorschau, DATEV-Export & ERiC-Übermittlung</strong>
                            <p>Führe die Abschlusskontrolle durch. Du kannst die simulierte XML-Vorschau des amtlichen Formulars öffnen, ein vollständiges DATEV-ZIP-Paket für den Steuerberater generieren oder die Daten verschlüsselt an die ELSTER-Clearingstelle übermitteln.</p>
                        </div>
                    </div>

                    {{-- Aktions-Karten --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {{-- 1. DATEV Export --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-4">
                                    <x-heroicon-o-document-arrow-down class="w-6 h-6" />
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-2">DATEV EXTF Report (ZIP)</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    Exportiert den standardisierten DATEV-Buchungsstapel (CSV), ein PDF-Steuerprotokoll und alle Belegdateien als komprimiertes ZIP-Archiv.
                                </p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <button wire:click="generateDatevExport" wire:loading.attr="disabled" @disabled($isFuture)
                                        class="w-full py-3 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="generateDatevExport">Report ZIP erstellen</span>
                                    <span wire:loading wire:target="generateDatevExport" class="animate-pulse">Exportiert...</span>
                                </button>
                            </div>
                        </div>

                        {{-- 2. Formular Vorschau --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4">
                                    <x-heroicon-o-document-text class="w-6 h-6" />
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-2">Formular-Vorschau (XML)</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    Zeigt die exakten Kennzahlen (Kz 81, 66, 83) im amtlichen ELSTER-Layout an, wie sie an die Clearingstelle übertragen werden.
                                </p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <button type="button" @click="showPreview = true"
                                        class="w-full py-3 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                    <span>Vorschau öffnen</span>
                                </button>
                            </div>
                        </div>

                        {{-- 3. ELSTER Übermittlung --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 shadow-sm flex flex-col justify-between relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-24 h-24 bg-[var(--theme-color-10)] rounded-bl-full pointer-events-none"></div>
                            <div>
                                <div class="w-12 h-12 rounded-2xl bg-[var(--theme-color-15)] text-[var(--theme-color)] flex items-center justify-center mb-4">
                                    <x-heroicon-o-paper-airplane class="w-6 h-6" />
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-2">An ELSTER übermitteln</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    Signiert die Voranmeldung mit dem Zertifikat und sendet das UStVA-Payload verschlüsselt an datenannahme.elster.de.
                                </p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <button wire:click="transmitToElster" wire:loading.attr="disabled" @disabled(!$activeData['is_ready_for_transmit'])
                                        class="w-full py-3 bg-[var(--theme-color)] hover:bg-[var(--theme-color-70)] text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="transmitToElster">Jetzt absenden</span>
                                    <span wire:loading wire:target="transmitToElster" class="animate-pulse">Sende via ERiC...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Sperr-Hinweis falls nicht bereit --}}
                    @if(!$activeData['is_ready_for_transmit'])
                        <div class="p-5 sm:p-6 border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/5 rounded-2xl animate-fade-in space-y-3">
                            <h5 class="text-xs font-black uppercase text-red-600 dark:text-red-400 tracking-wider flex items-center gap-2">
                                <x-heroicon-s-exclamation-triangle class="w-5 h-5 shrink-0" />
                                <span>Übermittlung noch gesperrt: Fehlende Voraussetzungen</span>
                            </h5>
                            <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-2 list-disc list-inside font-medium pl-1">
                                @if($activeData['status'] !== 'ready')
                                    @foreach($activeData['checklist'] as $key => $check)
                                        @if(!$check['passed'])
                                            <li><strong class="text-gray-800 dark:text-gray-200">{{ $check['name'] }}</strong> (Bitte in Schritt 1 prüfen)</li>
                                        @endif
                                    @endforeach
                                @endif
                                @if($authMethod === 'software' && !$hasEnvPassword && strlen($certPassword) < 3 && !in_array($selectedCertName, ['test-soft-pse.pfx', 'test-softorg-pse.pfx', 'test-softidnr-pse.pfx']))
                                    <li>Zertifikats-Passwort fehlt in Schritt 2</li>
                                @endif
                                @if($authMethod === 'hardware' && strlen($hardwarePin) < 6)
                                    <li>6-stellige secunet-PIN fehlt in Schritt 2</li>
                                @endif
                            </ul>
                        </div>
                    @else
                        <div class="p-5 sm:p-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 rounded-2xl flex items-center gap-3.5 text-xs text-emerald-800 dark:text-emerald-300 font-bold">
                            <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <span>Pre-Flight Check erfolgreich: Alle Belege, Stammdaten und Authentifizierungs-Parameter sind bereit für die Übermittlung.</span>
                        </div>
                    @endif

                    {{-- LIVE SYSTEM- & API-PROTOKOLL TERMINAL --}}
                    <div class="pt-6 border-t border-gray-200 dark:border-gray-800">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest flex items-center gap-2">
                                <x-heroicon-s-command-line class="w-4 h-4 text-[var(--theme-color)]" />
                                System- & API-Protokoll (Live)
                            </h4>

                            <button wire:click="checkApiStatus" wire:loading.attr="disabled"
                                    class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-1.5 rounded-lg text-[9px] font-black text-gray-700 dark:text-gray-400 uppercase tracking-widest transition-colors flex items-center gap-2 shadow-sm cursor-pointer disabled:opacity-50">
                                <x-heroicon-s-signal class="w-3 h-3 text-blue-500" />
                                <span wire:loading.remove wire:target="checkApiStatus">API Ping Test</span>
                                <span wire:loading wire:target="checkApiStatus" class="text-blue-500 animate-pulse">Pinge Server...</span>
                            </button>
                        </div>

                        <div class="bg-gray-950 dark:bg-[#0D1117] rounded-2xl border border-gray-800 p-4 h-56 overflow-y-auto custom-scrollbar font-mono text-[11px] shadow-inner relative text-gray-200">
                            <div class="absolute top-0 left-0 w-1 h-full bg-[var(--theme-color-30)]"></div>
                            <div class="space-y-1.5 pl-3">
                                @forelse($globalLogs as $log)
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

                                <div class="flex gap-3 text-[var(--theme-color)] mt-2">
                                    <span class="text-gray-600 shrink-0">[{{ now()->format('H:i:s.v') }}]</span>
                                    <span class="animate-pulse">_</span>
                                </div>
                            </div>
                        </div>

                        {{-- Technische Spezifikationen --}}
                        <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5 shadow-sm">
                                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Übertragungs-Protokoll</p>
                                <p class="text-xs font-mono text-gray-800 dark:text-gray-300">ERiC API Client</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">Kein Web-Login nötig</p>
                            </div>
                            <div class="bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5 shadow-sm">
                                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Authentifizierung</p>
                                <p class="text-xs font-mono text-gray-800 dark:text-gray-300">Lokale X.509 Signatur</p>
                                <p class="text-[10px] text-[var(--theme-color)] mt-0.5">Via .pfx Zertifikat</p>
                            </div>
                            <div class="bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5 shadow-sm">
                                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Clearingstelle</p>
                                <p class="text-xs font-mono text-gray-800 dark:text-gray-300">datenannahme.elster.de</p>
                                <p class="text-[10px] text-emerald-500 mt-0.5">TLS 1.3 / Port 443</p>
                            </div>
                            <div class="bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5 shadow-sm">
                                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-1">Datenstruktur</p>
                                <p class="text-xs font-mono text-gray-800 dark:text-gray-300">UStG Finkonsens XML</p>
                                <p class="text-[10px] text-blue-500 mt-0.5">Schema-Validiert</p>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3 Navigation Footer --}}
                    <div class="flex justify-start pt-4 border-t border-gray-200 dark:border-gray-800">
                        <button wire:click="setStep(2)" class="px-5 py-3 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-xs font-black uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-800 transition-all flex items-center gap-2 cursor-pointer">
                            <x-heroicon-m-arrow-left class="w-4 h-4" />
                            <span>Zurück zu Schritt 2: Parameter & Auth</span>
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
                        <x-heroicon-s-rocket-launch class="w-5 h-5 text-blue-500" />
                        Schritt-für-Schritt zur erfolgreichen Übermittlung
                    </h3>
                    <button @click="showWorkflowGuide = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-white p-2 rounded-xl transition-colors cursor-pointer">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    {{-- Schritt 1 Guide --}}
                    <div class="bg-gray-50 dark:bg-gray-950 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <div class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-500 font-bold flex items-center justify-center text-xs mb-3">1</div>
                            <h5 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">Belege vervollständigen</h5>
                            <p class="text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed font-medium">
                                Lade alle fehlenden Rechnungen als PDF oder Bild hoch. Eine lückenlose Belegführung ist Pflicht für den Vorsteuerabzug (Kz 66).
                            </p>
                        </div>
                    </div>

                    {{-- Schritt 2 Guide --}}
                    <div class="bg-gray-50 dark:bg-gray-950 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <div class="w-8 h-8 rounded-full bg-purple-500/10 text-purple-500 font-bold flex items-center justify-center text-xs mb-3">2</div>
                            <h5 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">Authentifizieren</h5>
                            <p class="text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed font-medium">
                                Wähle dein Zertifikat aus dem Tresor und gib dein Zertifikats-Passwort ein. Das Passwort wird sicher zur Laufzeit entschlüsselt und nicht dauerhaft gespeichert.
                            </p>
                        </div>
                    </div>

                    {{-- Schritt 3 Guide --}}
                    <div class="bg-gray-50 dark:bg-gray-950 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <div class="w-8 h-8 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-bold flex items-center justify-center text-xs mb-3">3</div>
                            <h5 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">Übermittlung starten</h5>
                            <p class="text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed font-medium">
                                Klicke auf "An ELSTER übermitteln". Nach erfolgreicher Validierung erhältst du im Terminal-Log dein offizielles Transferticket von der Clearingstelle.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                    <button @click="showWorkflowGuide = false" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-xl text-xs font-bold transition-colors cursor-pointer">
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
                        Das 1x1 der Steuern für "{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}"
                    </h3>
                    <button @click="showTaxKnowledge = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-white p-2 rounded-xl transition-colors cursor-pointer">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Einkommensteuer --}}
                    <div class="bg-gray-50 dark:bg-gray-950 rounded-2xl p-6 border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <h5 class="text-sm font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-mono text-xs flex items-center justify-center">1</span>
                                Einkommensteuer (ESt)
                            </h5>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4 font-medium">
                                Zahlst du privat auf deinen Jahresgewinn. Das Finanzamt ermittelt am Jahresende den Gewinn aus der EÜR und besteuert den Überschuss nach Abzug deiner Freibeträge.
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-800 text-[11px] text-gray-600 dark:text-gray-400">
                            <strong class="text-[var(--theme-color)] block mb-1">💡 Praxistipp:</strong>
                            Privatentnahmen mindern deinen Gewinn nicht. Lege monatlich rund 20-30% deines Gewinns auf ein Rücklagenkonto zurück.
                        </div>
                    </div>

                    {{-- Umsatzsteuer --}}
                    <div class="bg-gray-50 dark:bg-gray-950 rounded-2xl p-6 border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <h5 class="text-sm font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-mono text-xs flex items-center justify-center">2</span>
                                Umsatzsteuer (USt)
                            </h5>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4 font-medium">
                                Du nimmst 19% MwSt. von deinen Kunden ein und ziehst die gezahlte Steuer deiner Betriebsausgaben als Vorsteuer ab. Die Differenz ist deine Zahllast.
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-800 text-[11px] text-gray-600 dark:text-gray-400">
                            <strong class="text-[var(--theme-color)] block mb-1">💡 Praxistipp:</strong>
                            Bei Software-Abos oder Ads aus dem EU-Ausland gilt meist "Reverse Charge" (§ 13b): Deklaration und zeitgleicher Vorsteuerabzug (Nullsumme).
                        </div>
                    </div>

                    {{-- Gewerbesteuer --}}
                    <div class="bg-gray-50 dark:bg-gray-950 rounded-2xl p-6 border border-gray-200 dark:border-gray-800 flex flex-col justify-between">
                        <div>
                            <h5 class="text-sm font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] font-mono text-xs flex items-center justify-center">3</span>
                                Gewerbesteuer (GewSt)
                            </h5>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4 font-medium">
                                Für Einzelunternehmen gilt ein gesetzlicher Freibetrag von 24.500 Euro reinem Gewinn pro Jahr. Liegt dein Gewinn darunter, fällt 0,00 € Gewerbesteuer an.
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-800 text-[11px] text-gray-600 dark:text-gray-400">
                            <strong class="text-[var(--theme-color)] block mb-1">💡 Praxistipp:</strong>
                            Wird nur 1x jährlich im Rahmen der Steuererklärung gemeldet. Bleibst du unter dem Freibetrag, ist dies formal schnell erledigt.
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                    <button @click="showTaxKnowledge = false" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-xl text-xs font-bold transition-colors cursor-pointer">
                        Schließen
                    </button>
                </div>
            </div>
        </div>

        {{-- ==================== MODAL: ELSTER XML VORSCHAU ==================== --}}
        <div x-show="showPreview" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div x-show="showPreview" x-transition.opacity class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="showPreview = false"></div>

            <div x-show="showPreview" x-transition.scale.95
                 class="relative bg-[#f4f7f4] border-2 border-[#5b8c5a] rounded-2xl shadow-2xl w-full max-w-4xl text-gray-800 overflow-hidden font-sans">
                <div class="bg-[#5b8c5a] text-white p-4 flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight">Umsatzsteuer-Voranmeldung {{ $activeData['year'] }}</h2>
                        <p class="text-xs font-medium opacity-90 mt-1">Simulierte Ansicht der XML-Nutzdaten nach Schema finkonsens.de</p>
                    </div>
                    <button @click="showPreview = false" class="text-white hover:bg-white/20 p-2 rounded-full transition-colors cursor-pointer">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-6 sm:p-8 space-y-6">
                    <div class="grid grid-cols-2 gap-6 border-b-2 border-[#5b8c5a]/30 pb-6">
                        <div>
                            <p class="text-xs text-[#5b8c5a] font-bold uppercase mb-1">Steuernummer</p>
                            <div class="bg-white border border-[#5b8c5a]/50 px-3 py-2 font-mono text-sm shadow-inner rounded">{{ shop_setting('owner_tax_id', 'Nicht hinterlegt') }}</div>
                        </div>
                        <div>
                            <p class="text-xs text-[#5b8c5a] font-bold uppercase mb-1">Voranmeldungszeitraum</p>
                            <div class="bg-white border border-[#5b8c5a]/50 px-3 py-2 font-mono text-sm shadow-inner rounded">{{ $activeData['month_number'] }} / {{ $activeData['year'] }}</div>
                        </div>
                    </div>

                    {{-- Kennzahl 81 --}}
                    <div>
                        <h3 class="text-xs font-bold text-[#5b8c5a] uppercase border-b border-[#5b8c5a]/30 pb-1.5 mb-3">Lieferungen und sonstige Leistungen (Kz 81)</h3>
                        <div class="flex items-center gap-4 bg-white p-3 border border-[#5b8c5a]/20 shadow-sm relative pl-12 rounded">
                            <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center rounded-l">81</div>
                            <div class="flex-1 text-xs">Steuerpflichtige Umsätze (Netto) zum allgemeinen Steuersatz</div>
                            <div class="w-40 bg-gray-100 border border-gray-300 px-3 py-1.5 font-mono text-right font-bold text-gray-700 rounded">
                                {{ number_format($activeData['revenue_net'], 0, '', '') }}
                            </div>
                            <span class="text-xs font-mono text-gray-400">Volle EUR</span>
                        </div>
                    </div>

                    {{-- Kennzahl 66 --}}
                    <div>
                        <h3 class="text-xs font-bold text-[#5b8c5a] uppercase border-b border-[#5b8c5a]/30 pb-1.5 mb-3">Abziehbare Vorsteuerbeträge (Kz 66)</h3>
                        <div class="flex items-center gap-4 bg-white p-3 border border-[#5b8c5a]/20 shadow-sm relative pl-12 rounded">
                            <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center rounded-l">66</div>
                            <div class="flex-1 text-xs">Vorsteuerbeträge aus Rechnungen von anderen Unternehmern</div>
                            <div class="w-40 bg-gray-100 border border-gray-300 px-3 py-1.5 font-mono text-right font-bold text-gray-700 rounded">
                                {{ number_format($activeData['vat_paid'], 2, ',', '.') }}
                            </div>
                            <span class="text-xs font-mono text-gray-400">EUR, Ct</span>
                        </div>
                    </div>

                    {{-- Kennzahl 83 --}}
                    <div class="pt-4 border-t-2 border-[#5b8c5a]/30">
                        <div class="flex items-center gap-4 bg-[#5b8c5a]/10 p-4 border border-[#5b8c5a]/30 shadow-md relative pl-12 rounded">
                            <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center rounded-l">83</div>
                            <div class="flex-1 text-xs font-bold text-[#5b8c5a]">Verbleibende Umsatzsteuer-Vorauszahlung / Verbleibender Überschuss</div>
                            <div class="w-40 bg-white border-2 border-[#5b8c5a] px-3 py-2 font-mono text-right font-black text-base text-gray-800 shadow-inner rounded">
                                {{ number_format($activeData['zahllast'], 2, ',', '.') }}
                            </div>
                            <span class="text-xs font-mono text-gray-500 font-bold">EUR, Ct</span>
                        </div>
                    </div>

                    <div class="text-[9px] text-gray-400 flex justify-between items-center pt-2">
                        <span>Generiert von ELSTER ERiC Schema &bull; Seelenfunke Financials</span>
                        <span>XML Schema Finkonsens &bull; Version 11</span>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .dark .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.3); }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #4b5563; }
    </style>
</div>
