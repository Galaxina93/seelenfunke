<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <section class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-10 relative overflow-hidden transition-all duration-500 mt-6 w-full">
        {{-- Glow-Streifen --}}
        <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-[var(--theme-color)] to-red-600 opacity-60"></div>

        {{-- HEADER & TRESOR --}}
        <div class="flex flex-col md:flex-row justify-between items-start mb-8 gap-6 relative z-10">
            <div>
                <h3 class="text-2xl font-serif font-bold text-white tracking-tight flex items-center gap-3">
                    <i class="solar-document-text-bold-duotone text-[var(--theme-color)] text-2xl"></i>
                    Umsatzsteuer-Zentrale
                </h3>
            </div>

            <div class="flex items-center gap-3">
                <div wire:init="checkApiStatus" class="flex items-center gap-2 px-3 py-1.5 bg-gray-950 border border-gray-800 rounded-lg shadow-inner">
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

                <select wire:model.live="selectedYear" class="bg-gray-950 border border-gray-800 text-gray-300 px-5 py-3 rounded-xl text-sm font-bold shadow-inner transition-all outline-none focus:ring-2 focus:ring-[var(--theme-color-30)] cursor-pointer">
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
                                        <button wire:click.prevent="downloadTaxExport('{{ $export['name'] }}')" class="p-1.5 text-gray-500 hover:text-[var(--theme-color)] transition-colors inline-block focus:outline-none" title="Herunterladen">
                                            <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                        </button>
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
        <div class="mb-8 flex items-start gap-3 bg-[var(--theme-color-5)] p-4 rounded-2xl border border-[var(--theme-color-20)] shadow-inner">
            <x-heroicon-s-information-circle class="w-5 h-5 text-[var(--theme-color)] shrink-0 mt-0.5" />
            <div class="text-xs text-[var(--theme-color)]/70 leading-relaxed font-medium">
                <strong class="text-[var(--theme-color)]">Automatische UStVA:</strong> Die Daten des Monats sind am besten <strong>ab dem 1. des Folgemonats</strong> konsolidiert exportierbar. Frist für die Meldung beim Finanzamt ist regulär der <strong>10. des Folgemonats</strong>. Die PDF fasst alle Werte rechtssicher zusammen.
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
                            class="shrink-0 snap-start flex flex-col items-center justify-center w-16 h-20 rounded-2xl border transition-all duration-300 relative {{ $isSelected ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-[0_0_20px_var(--theme-color-20)]' : 'border-gray-800 bg-gray-950 hover:bg-gray-900' }}">

                        @if($isSelected)
                            <div class="absolute -top-1 w-6 h-1 bg-[var(--theme-color)] rounded-full shadow-[0_0_10px_rgba(249,115,22,0.8)]"></div>
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
            $isMissingData = $activeData['status'] === 'missing_data';
            $isInProgress = $activeData['status'] === 'in_progress';
            $isFuture = $activeData['status'] === 'future';
            $isDeadlinePassed = now()->gt($activeData['deadline']) && !$isReady && !$isFuture;
            $glowClass = $isMissingData ? 'shadow-[inset_0_0_50px_rgba(239,68,68,0.1)]' : ($isReady ? 'shadow-[inset_0_0_50px_rgba(16,185,129,0.05)]' : '');
        @endphp

        <div x-data="{ showPreview: false }" class="bg-gray-950/50 rounded-[2.5rem] border border-gray-800 p-6 sm:p-10 shadow-inner relative animate-fade-in {{ $glowClass }}">

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
                            <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">System-Readiness</span>
                            <span class="text-lg font-black {{ $isMissingData ? 'text-red-400' : 'text-[var(--theme-color)]' }}">{{ $activeData['progress'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-900 h-2.5 rounded-full overflow-hidden border border-gray-800 shadow-inner">
                            <div class="h-full rounded-full transition-all duration-1000 {{ $isMissingData ? 'bg-red-500' : 'bg-gradient-to-r from-[var(--theme-color)] to-amber-400' }}" style="width: {{ $activeData['progress'] }}%"></div>
                        </div>
                        @if($isMissingData)
                            <p class="text-xs text-red-400 font-bold mt-3 flex items-center gap-2">
                                <x-heroicon-s-exclamation-triangle class="w-4 h-4 animate-pulse" />
                                Bitte Checkliste prüfen! Es fehlen entscheidende Daten.
                            </p>
                        @endif
                    </div>

                    {{-- Exaktes Berechnungs-Schema --}}
                    <div class="bg-gray-900 border border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-[var(--theme-color-5)] rounded-bl-full blur-2xl pointer-events-none"></div>
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-5 border-b border-gray-800 pb-3 relative z-10">Umsatzsteuer Schema (E-Commerce)</h4>
                        <div class="space-y-3 relative z-10 text-xs font-medium">
                            <div class="flex justify-between items-center text-gray-300">
                                <span class="flex items-center gap-2">
                                    Umsatzsteuer (Verkäufe) 
                                    <span class="text-[9px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-mono">Kz 81</span>
                                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-500 hover:text-[var(--theme-color)] cursor-help transition-colors select-none" title="Erhaltene Umsatzsteuer aus deinen normalen, inländischen Produktverkäufen (B2C & B2B) an deine Kunden." />
                                </span>
                                <span class="font-mono text-white">{{ $activeData['vat_collected'] > 0 ? '+' : '' }} {{ number_format($activeData['vat_collected'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-400">
                                <span class="flex items-center gap-2">
                                    IG Erwerb (§ 1a UStG) 
                                    <span class="text-[9px] bg-gray-800 text-gray-500 px-1.5 py-0.5 rounded font-mono">Kz 89</span>
                                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-600 hover:text-[var(--theme-color)] cursor-help transition-colors select-none" title="Steuer auf im EU-Ausland eingekaufte physische Waren (z.B. Rohstoffe). Du musst diese Steuer fiktiv anmelden, ziehst sie aber zeitgleich als Vorsteuer wieder ab (Nullsummenspiel)." />
                                </span>
                                <span class="font-mono text-gray-300">{{ $activeData['ig_erwerb_tax'] > 0 ? '+' : '' }} {{ number_format($activeData['ig_erwerb_tax'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-400">
                                <span class="flex items-center gap-2">
                                    Reverse Charge (§ 13b) 
                                    <span class="text-[9px] bg-gray-800 text-gray-500 px-1.5 py-0.5 rounded font-mono">Kz 46</span>
                                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-600 hover:text-[var(--theme-color)] cursor-help transition-colors select-none" title="Steuerschuldnerschaft des Empfängers. Meist für digitale B2B Dienstleistungen aus dem Ausland (z.B. Rechnungen von Meta/Google Ads)." />
                                </span>
                                <span class="font-mono text-gray-300">{{ $activeData['paragraph_13b_tax'] > 0 ? '+' : '' }} {{ number_format($activeData['paragraph_13b_tax'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="border-t border-gray-800 pt-3 flex justify-between items-center text-gray-300 font-bold">
                                <span>= Gesamtsteuer</span>
                                <span class="font-mono text-emerald-400 drop-shadow-[0_0_5px_currentColor]">{{ number_format($activeData['total_tax'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-300 pt-2">
                                <span class="flex items-center gap-2">
                                    - Vorsteuer (Ausgaben) 
                                    <span class="text-[9px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-mono">Kz 66</span>
                                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-500 hover:text-red-400 cursor-help transition-colors select-none" title="Deine gezahlte Umsatzsteuer auf betriebliche Rechnungen, Quittungen und Abos. Du forderst diesen Betrag als Erstattung vom Finanzamt zurück." />
                                </span>
                                <span class="font-mono text-red-400 drop-shadow-[0_0_5px_currentColor]">- {{ number_format($activeData['vat_paid'], 2, ',', '.') }} €</span>
                            </div>
                            <div class="border-t border-gray-700 pt-4 mt-2 flex justify-between items-end">
                                <span class="flex flex-col gap-0.5">
                                    <span class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-white">
                                        Zahllast ans FA
                                        <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors select-none" title="Der absolute Schlusswert. Das ist exakt der Betrag, den du unaufgefordert (!) an die Bankverbindung deines Finanzamts überweisen musst (oder der erstattet wird, wenn negativ)." />
                                    </span>
                                    <span class="text-[9px] text-gray-500 font-medium">Kennzahl 83 (Vorauszahlungssoll)</span>
                                </span>
                                <span class="text-2xl font-black {{ $activeData['zahllast'] > 0 ? 'text-[var(--theme-color)] drop-shadow-[0_0_8px_var(--theme-color-30)]' : 'text-emerald-400 drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]' }}">
                                    {{ number_format($activeData['zahllast'], 2, ',', '.') }} €
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Art der Anmeldung --}}
                    <div x-data="{ expanded: false }" class="bg-gray-900/50 border border-gray-800 rounded-xl p-4">
                        <button @click="expanded = !expanded" class="w-full flex items-center justify-between text-[10px] font-black text-gray-500 uppercase tracking-widest hover:text-white transition-colors">
                            <span>Meldungs-Art: {{ $submissionType }}</span>
                            <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                        </button>
                        <div x-show="expanded" x-collapse class="mt-4 pt-4 border-t border-gray-800">
                            <select wire:model.live="submissionType" class="w-full bg-gray-950 border border-gray-800 text-white text-xs font-bold rounded-lg px-3 py-2 focus:ring-2 focus:ring-[var(--theme-color-30)] outline-none shadow-inner cursor-pointer">
                                <option value="Erstübermittlung">Erstübermittlung (Regulär)</option>
                                <option value="Berichtigte Anmeldung">Berichtigte Anmeldung (Korrektur)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Hardware-Token / Authentifizierung --}}
                    <div x-data="{ expandedAuth: false }" class="bg-gray-900/50 border border-gray-800 rounded-xl p-4 mt-4">
                        <button @click="expandedAuth = !expandedAuth" class="w-full flex items-center justify-between text-[10px] font-black text-gray-500 uppercase tracking-widest hover:text-white transition-colors">
                            <span class="flex items-center gap-2">
                                @if($authMethod === 'hardware') <x-heroicon-s-cpu-chip class="w-4 h-4 text-[var(--theme-color)]" /> @else <x-heroicon-s-document-check class="w-4 h-4 text-emerald-400" /> @endif
                                Auth: {{ $authMethod === 'software' ? 'Zertifikat (.pfx)' : 'Hardware-Token (USB)' }}
                            </span>
                            <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform" x-bind:class="expandedAuth ? 'rotate-180' : ''" />
                        </button>
                        <div x-show="expandedAuth" x-collapse class="mt-4 pt-4 border-t border-gray-800 space-y-4">
                            <select wire:model.live="authMethod" class="w-full bg-gray-950 border border-gray-800 text-white text-xs font-bold rounded-lg px-3 py-2 focus:ring-2 focus:ring-[var(--theme-color-30)] outline-none shadow-inner cursor-pointer">
                                <option value="software">Software-Zertifikat (.pfx / .p12)</option>
                                <option value="hardware">Hardware-Token (secunet SDK / USB-Stick)</option>
                            </select>

                            @if($authMethod === 'hardware')
                                <div class="bg-blue-500/5 border border-blue-500/20 p-4 rounded-xl shadow-inner animate-fade-in relative overflow-hidden">
                                    <div class="absolute top-0 right-0 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>
                                    <label class="block text-[10px] font-black text-blue-400 uppercase tracking-widest mb-3 flex items-center gap-2 relative z-10">
                                        <x-heroicon-s-key class="w-4 h-4" /> Applikations-PIN (6-stellig)
                                    </label>
                                    <input type="password" wire:model.live="hardwarePin" maxlength="6" class="w-full relative z-10 bg-gray-950/80 border border-blue-500/30 text-white text-center text-xl tracking-[1em] font-mono rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500/50 outline-none shadow-inner placeholder-gray-800 transition-all font-black" placeholder="••••••">
                                    <p class="text-[9px] text-gray-500 mt-3 leading-relaxed relative z-10" title="APDU: 00 20 00 01 06">
                                        Die secunet-PIN wird nicht gespeichert. Sie wird zum direkten Entsperren des Hardware-Tokens via ERiC-Schnittstelle benötigt (RSA 3072-bit Raw Signature).
                                    </p>
                                </div>
                            @else
                                <div class="bg-emerald-500/5 border border-emerald-500/20 p-4 rounded-xl shadow-inner animate-fade-in relative overflow-hidden">
                                    <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                                    <div class="mb-4">
                                        <label class="block text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-3 flex items-center gap-2 relative z-10">
                                            <x-heroicon-s-folder-open class="w-4 h-4" /> Zertifikat aus Tresor wählen
                                        </label>

                                        @if(count($tresorCertificates) > 0)
                                            <select wire:model.live="selectedCertName" class="w-full relative z-10 bg-gray-950/80 border border-emerald-500/30 text-emerald-100 text-xs font-bold rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-emerald-500/50 outline-none shadow-inner cursor-pointer font-mono">
                                                <option value="">-- Zertifikat auswählen --</option>
                                                @foreach($tresorCertificates as $cert)
                                                    <option value="{{ $cert }}">{{ $cert }}</option>
                                                @endforeach
                                            </select>

                                            {{-- Offizielle ELSTER Test-Zertifikate Beschreibungen --}}
                                            @if($selectedCertName === 'test-soft-pse.pfx' || $selectedCertName === 'test-softorg-pse.pfx' || $selectedCertName === 'test-softidnr-pse.pfx' || str_contains($selectedCertName, 'bescheid'))
                                                <div class="mt-3 bg-blue-500/10 border border-blue-500/30 rounded-lg p-3 relative overflow-hidden animate-fade-in">
                                                    <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-blue-400 mb-2">
                                                        <x-heroicon-s-information-circle class="w-4 h-4" /> Offizielles ELSTER Test-Zertifikat
                                                    </div>
                                                    <p class="text-[9px] text-gray-300 leading-relaxed mb-2 font-medium">
                                                        @if($selectedCertName === 'test-soft-pse.pfx')
                                                            Enthält ein <b>persönliches Testzertifikat</b> (mit persönlicher Steuernummer).
                                                        @elseif($selectedCertName === 'test-softorg-pse.pfx')
                                                            Enthält ein <b>nicht-persönliches Testzertifikat</b> (Test-Organisationszertifikat).
                                                        @elseif($selectedCertName === 'test-softidnr-pse.pfx')
                                                            Enthält ein <b>persönliches Testzertifikat</b> (mit Identifikationsnummer).
                                                        @elseif(str_contains($selectedCertName, 'bescheid'))
                                                            Enthält ein <b>Testzertifikat für die Bescheidabholung</b>.
                                                        @endif
                                                    </p>
                                                    <p class="text-[8px] text-gray-400 leading-relaxed mb-2">
                                                        Kann nur mit Testmerker an den Server verschickt werden. Es ist <b>kein Login am ElsterOnline-Portal möglich</b>, aber die Signatur wird als positiv geprüft. Darf nur zu Tests durch den Softwarehersteller verwendet werden!
                                                    </p>
                                                    <div class="bg-gray-950 border border-gray-800 rounded px-2 py-1.5 flex items-center justify-between">
                                                        <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black">Test-Zertifikat PIN:</span>
                                                        <span class="text-xs font-mono font-bold text-white tracking-widest">123456</span>
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-[10px] p-3 rounded-lg text-center relative z-10">
                                                Keine .pfx Zertifikate im Ordner <code class="bg-gray-900 border border-red-500/30 px-1 py-0.5 rounded text-[8px]">{{ env('ERIC_TRESOR_PATH', 'storage/app/erictresor') }}</code> gefunden!
                                            </div>
                                        @endif
                                    </div>

                                    @php
                                        $isTestCert = in_array($selectedCertName, ['test-soft-pse.pfx', 'test-softorg-pse.pfx', 'test-softidnr-pse.pfx']) || str_contains($selectedCertName, 'bescheid');
                                    @endphp

                                    @if($isTestCert)
                                        <div class="bg-blue-500/10 border border-blue-500/30 text-blue-400 p-3 rounded-lg flex items-center gap-3 relative z-10 font-medium">
                                            <x-heroicon-s-check-badge class="w-5 h-5 flex-shrink-0" />
                                            <span class="text-[10px] leading-relaxed">
                                                Die Sandbox Test-PIN (123456) wird bei der Übermittlung automatisch vom System an die ERiC API übergeben.
                                            </span>
                                        </div>
                                    @elseif($hasEnvPassword)
                                        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-3 rounded-lg flex items-center gap-3 relative z-10 font-medium">
                                            <x-heroicon-s-shield-check class="w-5 h-5 flex-shrink-0" />
                                            <span class="text-[10px] leading-relaxed">
                                                Das Zertifikats-Passwort wurde hochsicher (verschlüsselt) aus der Server-Konfiguration <code class="bg-gray-950 border border-emerald-500/20 px-1 py-0.5 rounded text-[8px] font-mono">.env</code> geladen und muss nicht manuell eingegeben werden.
                                            </span>
                                        </div>
                                    @else
                                        <label class="block text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-3 flex items-center gap-2 relative z-10">
                                            <x-heroicon-s-lock-closed class="w-4 h-4" /> Zertifikats-Passwort
                                        </label>
                                        <input type="password" wire:model.live="certPassword" class="w-full relative z-10 bg-gray-950/80 border border-emerald-500/30 text-white text-center text-xl tracking-widest font-mono rounded-lg px-4 py-3 focus:ring-2 focus:ring-emerald-500/50 outline-none shadow-inner placeholder-gray-800 transition-all font-black" placeholder="Ihr Passwort">

                                        <p class="text-[9px] text-gray-500 mt-4 leading-relaxed relative z-10 border-t border-emerald-500/10 pt-3">
                                            Das Zertifikats-Passwort wird nicht dauerhaft gespeichert, sondern nur für die temporäre Entschlüsselung benötigt. Der Tresor-Basis-Pfad ist über <code class="bg-gray-900 border border-gray-800 px-1 py-0.5 rounded text-[8px] tracking-normal inline-block text-gray-400">ERIC_TRESOR_PATH</code> sicher gekapselt. Sie können das Passwort auch als <code class="bg-gray-900 border border-gray-800 px-1 py-0.5 rounded text-[8px] tracking-normal inline-block text-gray-400">ERIC_CERT_PASSWORD</code> in der .env hinterlegen.
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Aktions-Buttons --}}
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
                        <button wire:click="generateDatevExport" wire:loading.attr="disabled" @disabled($isFuture)
                        class="py-4 bg-gray-900 border border-gray-700 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg flex flex-col items-center justify-center gap-2 group disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-800 hover:text-white hover:border-blue-500 text-gray-400">
                            <x-heroicon-o-document-arrow-down class="w-6 h-6 group-hover:text-blue-400 transition-colors" />
                            <span wire:loading.remove wire:target="generateDatevExport">Report ZIP (Offline)</span>
                            <span wire:loading wire:target="generateDatevExport" class="text-blue-400 animate-pulse">Exportiert...</span>
                        </button>

                        <button type="button" @click="showPreview = true"
                        class="py-4 bg-gray-900 border border-gray-700 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg flex flex-col items-center justify-center gap-2 group hover:bg-gray-800 hover:text-white hover:border-emerald-500 text-gray-400">
                            <x-heroicon-o-document-text class="w-6 h-6 group-hover:text-emerald-400 transition-colors" />
                            <span>Vorschau (XML Formular)</span>
                        </button>

                        <button wire:click="transmitToElster" wire:loading.attr="disabled" @disabled(!$activeData['is_ready_for_transmit'])
                        class="col-span-2 lg:col-span-1 py-4 bg-gray-900 border border-gray-700 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg flex flex-col items-center justify-center gap-2 group disabled:opacity-50 disabled:cursor-not-allowed hover:border-[var(--theme-color-50)] hover:bg-[var(--theme-color-10)] text-gray-400 relative overflow-hidden">
                            <div class="absolute inset-0 bg-[var(--theme-color-10)] opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <x-heroicon-o-paper-airplane class="w-6 h-6 group-hover:text-[var(--theme-color)] transition-colors relative z-10" />
                            <span wire:loading.remove wire:target="transmitToElster" class="group-hover:text-[var(--theme-color)] transition-colors relative z-10">An ELSTER übermitteln</span>
                            <span wire:loading wire:target="transmitToElster" class="text-[var(--theme-color)] animate-pulse relative z-10">SENDE VIA ERiC...</span>
                        </button>
                    </div>

                    {{-- Info-Box: Warum ist Senden ausgegraut? --}}
                    @if(!$activeData['is_ready_for_transmit'])
                        <div class="mt-4 p-4 border border-red-500/20 bg-red-500/5 rounded-xl relative overflow-hidden group transition-colors animate-fade-in">
                            <h5 class="text-[10px] font-black uppercase text-red-400 tracking-widest mb-3 flex items-center gap-2">
                                <x-heroicon-s-information-circle class="w-4 h-4" />
                                Warten auf Freigabe: Übermittlung gesperrt
                            </h5>
                            <ul class="text-[10px] text-gray-400 space-y-2 list-none leading-relaxed font-medium">
                                @if($activeData['status'] !== 'ready')
                                    @foreach($activeData['checklist'] as $key => $check)
                                        @if(!$check['passed'])
                                        <li class="flex gap-2 items-start opacity-80">
                                            <b class="text-red-400 mt-0.5">•</b>
                                            <span>Fehlt: <b class="text-gray-300">{{ $check['name'] }}</b> (Bitte in der Liste rechts beheben!)</span>
                                        </li>
                                        @endif
                                    @endforeach
                                @endif
                                @if($authMethod === 'software' && strlen($certPassword) < 3)
                                    <li class="flex gap-2 items-start opacity-80">
                                        <b class="text-red-400 mt-0.5">•</b>
                                        <span>Du hast kein gültiges <b class="text-gray-300">Zertifikats-Passwort (Dein normales "Mein ELSTER" Login-Passwort)</b> eingegeben. Die Schnittstelle benötigt dieses zur temporären Laufzeit-Entschlüsselung.</span>
                                    </li>
                                @endif
                                @if($authMethod === 'hardware' && strlen($hardwarePin) < 6)
                                    <li class="flex gap-2 items-start opacity-80">
                                        <b class="text-red-400 mt-0.5">•</b>
                                        <span>Für die Verwendung des secunet-Sticks musst du die exakt <b class="text-gray-300">6-stellige Applikations-PIN</b> in das Authentifizierungsfeld übermitteln.</span>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endif

                    {{-- ELSTER FORMULAR MODAL (Alpine.js) --}}
                    <div x-show="showPreview" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                        <div x-show="showPreview" x-transition.opacity class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="showPreview = false"></div>

                        <div x-show="showPreview" x-transition.scale.95
                             class="relative bg-[#f4f7f4] border-2 border-[#5b8c5a] rounded-xl shadow-2xl w-full max-w-4xl text-gray-800 overflow-hidden font-sans">

                            {{-- Header --}}
                            <div class="bg-[#5b8c5a] text-white p-4 flex justify-between items-center">
                                <div>
                                    <h2 class="text-xl font-bold tracking-tight">Umsatzsteuer-Voranmeldung {{ $activeData['year'] }}</h2>
                                    <p class="text-xs font-medium opacity-90 mt-1">Simulierte Ansicht der XML-Nutzdaten nach Schema finkonsens.de (Nur Leseansicht)</p>
                                </div>
                                <button @click="showPreview = false" class="text-white hover:bg-white/20 p-2 rounded-full transition-colors">
                                    <x-heroicon-o-x-mark class="w-6 h-6" />
                                </button>
                            </div>

                            {{-- Form Body --}}
                            <div class="p-8 space-y-6">
                                <div class="grid grid-cols-2 gap-8 border-b-2 border-[#5b8c5a]/30 pb-6">
                                    <div>
                                        <p class="text-xs text-[#5b8c5a] font-bold uppercase mb-1">Steuernummer</p>
                                        <div class="bg-white border border-[#5b8c5a]/50 px-3 py-2 font-mono text-sm shadow-inner">{{ shop_setting('owner_tax_id', 'Nicht hinterlegt') }}</div>
                                    </div>
                                    <div>
                                        <p class="text-xs text-[#5b8c5a] font-bold uppercase mb-1">Voranmeldungszeitraum</p>
                                        <div class="bg-white border border-[#5b8c5a]/50 px-3 py-2 font-mono text-sm shadow-inner">{{ $activeData['month_number'] }} / {{ $activeData['year'] }}</div>
                                    </div>
                                </div>

                                {{-- Kennzahlen Block --}}
                                <div>
                                    <h3 class="text-sm font-bold text-[#5b8c5a] uppercase border-b border-[#5b8c5a]/30 pb-2 mb-4">Lieferungen und sonstige Leistungen (Kz 81)</h3>
                                    <div class="flex items-center gap-4 bg-white p-3 border border-[#5b8c5a]/20 shadow-sm relative pl-12">
                                        <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center">81</div>
                                        <div class="flex-1 text-xs">Steuerpflichtige Umsätze (Netto) zum allgemeinen Steuersatz</div>
                                        <div class="w-40 bg-gray-100 border border-gray-300 px-3 py-1.5 font-mono text-right font-bold text-gray-700">
                                            {{ number_format($activeData['revenue_net'], 0, '', '') }}
                                        </div>
                                        <span class="text-xs font-mono text-gray-400">Volle EUR</span>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-sm font-bold text-[#5b8c5a] uppercase border-b border-[#5b8c5a]/30 pb-2 mb-4">Abziehbare Vorsteuerbeträge (Kz 66)</h3>
                                    <div class="flex items-center gap-4 bg-white p-3 border border-[#5b8c5a]/20 shadow-sm relative pl-12">
                                        <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center">66</div>
                                        <div class="flex-1 text-xs">Vorsteuerbeträge aus Rechnungen von anderen Unternehmern</div>
                                        <div class="w-40 bg-gray-100 border border-gray-300 px-3 py-1.5 font-mono text-right font-bold text-gray-700">
                                            {{ number_format($activeData['vat_paid'], 2, ',', '.') }}
                                        </div>
                                        <span class="text-xs font-mono text-gray-400">EUR, Ct</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t-4 border-[#5b8c5a]/20">
                                    <div class="flex items-center gap-4 bg-[#5b8c5a]/10 p-4 border border-[#5b8c5a]/30 shadow-md relative pl-12 rounded">
                                        <div class="absolute left-0 top-0 bottom-0 w-10 bg-[#5b8c5a] text-white font-bold flex items-center justify-center">83</div>
                                        <div class="flex-1 text-sm font-bold text-[#5b8c5a]">Verbleibende Umsatzsteuer-Vorauszahlung / Verbleibender Überschuss</div>
                                        <div class="w-40 bg-white border-2 border-[#5b8c5a] px-3 py-2 font-mono text-right font-black text-lg text-gray-800 shadow-inner">
                                            {{ number_format($activeData['zahllast'], 2, ',', '.') }}
                                        </div>
                                        <span class="text-sm font-mono text-gray-500 font-bold">EUR, Ct</span>
                                    </div>
                                </div>

                                <div class="text-[9px] text-gray-400 flex justify-between items-center mt-6">
                                    <span>Generiert von ELSTER ERiC Simulator &bull; Seelenfunke Financials</span>
                                    <span>XML Schema 2023/2024 &bull; Version 11</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RECHTE SPALTE: Checkliste & Details --}}
                <div class="lg:col-span-7 flex flex-col gap-6">

                    {{-- MASTER CHECKLIST: ERiC Schnittstellen-Voraussetzungen --}}
                    @if(!$activeData['all_checklist_passed'])
                        <div class="bg-gray-900 border border-red-500/20 rounded-[2rem] p-6 sm:p-8 shadow-inner relative overflow-hidden">
                            <div class="absolute inset-0 bg-red-500/5 pointer-events-none"></div>
                            <h4 class="text-[10px] font-black text-red-400 uppercase tracking-widest mb-6 flex items-center gap-2 relative z-10">
                                <x-heroicon-s-exclamation-triangle class="w-5 h-5 animate-pulse" />
                                Aktion erforderlich: Strenge ERiC Validierung
                            </h4>
                            <div class="space-y-4 relative z-10">
                                @foreach($activeData['checklist'] as $key => $check)
                                    <div class="flex items-start gap-4 p-3 rounded-xl {{ $check['passed'] ? 'bg-gray-950/50 border border-gray-800' : 'bg-red-500/10 border border-red-500/30 shadow-[inset_0_0_20px_rgba(239,68,68,0.1)]' }}">
                                        <div class="mt-0.5 shrink-0">
                                            @if($check['passed'])
                                                <div class="w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-500/50 flex items-center justify-center text-emerald-400">
                                                    <x-heroicon-s-check class="w-3 h-3" />
                                                </div>
                                            @else
                                                <div class="w-5 h-5 rounded-full bg-red-500/20 border border-red-500/50 flex items-center justify-center text-red-400 animate-pulse">
                                                    <x-heroicon-s-x-mark class="w-3 h-3" />
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-bold {{ $check['passed'] ? 'text-gray-400' : 'text-white' }}">{{ $check['name'] }}</p>
                                            <p class="text-[10px] {{ $check['passed'] ? 'text-gray-600' : 'text-gray-400' }} mt-1 leading-relaxed">{{ $check['description'] }}</p>

                                            {{-- Spezifische Handlungsaufforderung, falls nicht erfüllt --}}
                                            @if(!$check['passed'] && $key === 'buchhaltung/receipts')
                                               <div class="mt-4 space-y-3 max-h-72 overflow-y-auto custom-scrollbar pr-2">
                                                    @if(session()->has('success_receipt'))
                                                        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] p-2.5 rounded-xl text-center animate-fade-in font-bold uppercase tracking-widest shadow-inner mb-2">
                                                            <x-heroicon-s-check-circle class="w-4 h-4 inline-block mr-1" /> {{ session('success_receipt') }}
                                                        </div>
                                                    @endif

                                                    @foreach($activeData['missing_items'] as $item)
                                                        <div class="bg-gray-950 p-3.5 rounded-xl border border-red-500/30 flex flex-col gap-3 group relative overflow-hidden transition-all hover:border-red-500/50 shadow-lg">
                                                            <div class="flex justify-between items-start">
                                                                <div class="min-w-0 pr-3">
                                                                    <p class="text-xs font-bold text-gray-300 truncate" title="{{ $item['title'] }}">{{ $item['title'] }}</p>
                                                                    <p class="text-[9px] text-gray-500 mt-1 flex items-center gap-1.5">
                                                                        <span class="bg-gray-800 px-1.5 py-0.5 rounded text-gray-400 uppercase tracking-widest">{{ $item['type'] === 'fixed' ? 'Fix' : 'Var' }}</span>
                                                                        {{ $item['date'] }}
                                                                    </p>
                                                                </div>
                                                                <span class="text-red-400 font-mono font-bold text-[11px] shrink-0 drop-shadow-[0_0_5px_rgba(239,68,68,0.5)] bg-red-500/10 px-2.5 py-1 rounded-full border border-red-500/20">{{ number_format($item['amount'], 2, ',', '.') }} €</span>
                                                            </div>

                                                            {{-- Inline Uploader --}}
                                                            <div class="mt-1 flex flex-col gap-2">
                                                                <label class="flex items-center justify-center w-full px-3 py-2.5 bg-gray-900 border border-dashed border-gray-700 rounded-lg cursor-pointer hover:bg-gray-800 hover:border-blue-500/50 transition-colors relative overflow-hidden group/upload">
                                                                    <div wire:loading wire:target="receiptFiles.{{ $item['id'] }}" class="absolute inset-0 bg-blue-500/10 flex items-center justify-center backdrop-blur-sm z-20">
                                                                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-400 animate-pulse">Lade Datei in RAM...</span>
                                                                    </div>

                                                                    <input type="file" wire:model.live="receiptFiles.{{ $item['id'] }}" class="hidden" accept="image/jpeg,image/png,application/pdf">

                                                                    <div class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest group-hover/upload:text-blue-400 transition-colors">
                                                                        <x-heroicon-o-cloud-arrow-up class="w-4 h-4" />
                                                                        <span>Beleg (PDF/Bild) auswählen</span>
                                                                    </div>
                                                                </label>

                                                                @if(isset($receiptFiles[$item['id']]))
                                                                    <div class="animate-fade-in flex flex-col gap-2">
                                                                        <p class="text-[9px] text-emerald-400 text-center font-mono truncate">Bereit: {{ $receiptFiles[$item['id']]->getClientOriginalName() }}</p>
                                                                        <button wire:click="uploadMissingReceipt('{{ $item['id'] }}', '{{ $item['type'] }}')"
                                                                                class="w-full py-2 bg-blue-500/10 border border-blue-500/30 rounded-lg text-[10px] font-black text-blue-400 uppercase tracking-widest hover:bg-blue-500/20 transition-colors flex items-center justify-center gap-2 shadow-inner">
                                                                            <x-heroicon-s-check-circle class="w-4 h-4" /> Jetzt hochladen & verknüpfen
                                                                        </button>
                                                                    </div>
                                                                @endif
                                                                @error('receiptFiles.'.$item['id']) <span class="text-red-400 text-[9px] text-center mt-1">{{ $message }}</span> @enderror
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif(!$check['passed'] && ($key === 'tax_id' || $key === 'proprietor'))
                                                <a href="{{ route('admin.configuration') }}" class="inline-block mt-3 text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] hover:text-[var(--theme-color)] border border-[var(--theme-color-30)] bg-[var(--theme-color-10)] px-3 py-1.5 rounded transition-all hover:bg-[var(--theme-color-20)]">Zu Einstellungen &rarr;</a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="bg-emerald-500/5 border border-emerald-500/20 rounded-[2rem] p-8 shadow-[inset_0_0_50px_rgba(16,185,129,0.05)] flex items-center gap-5 relative overflow-hidden">
                            <div class="absolute -right-10 -top-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                            <div class="w-14 h-14 bg-emerald-500/20 border border-emerald-500/40 rounded-full flex items-center justify-center text-emerald-400 shrink-0 shadow-[0_0_15px_rgba(16,185,129,0.3)] relative z-10">
                                <x-heroicon-s-check class="w-7 h-7" />
                            </div>
                            <div class="relative z-10">
                                <h4 class="text-base font-bold text-white mb-1">ERiC API Ready! (100%)</h4>
                                <p class="text-sm text-gray-400 leading-relaxed">Alle Stammdaten (Steuernummer, Name) sind gültig und alle Belege wurden kontiert. Das System ist bereit für die verschlüsselte Live-Übertragung an das Finanzamt.</p>
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
                                <p class="text-2xl font-bold text-white mb-1">
                                    {{ $activeData['order_count'] }} <span class="text-xs text-gray-500 font-medium">Orders</span>
                                    @if(count($activeData['raw_credits'] ?? []) > 0)
                                        <span class="text-red-400 text-xs ml-1 font-black">({{ count($activeData['raw_credits']) }} Stornos)</span>
                                    @endif
                                </p>
                                <p class="text-xs {{ $activeData['revenue_net'] < 0 ? 'text-red-400' : 'text-gray-400' }} font-mono">Netto: {{ number_format($activeData['revenue_net'], 2, ',', '.') }} €</p>
                            </div>

                            <div class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner flex flex-col justify-center">
                                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-black mb-2 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Ausgaben</p>
                                <p class="text-2xl font-bold text-white mb-1">{{ $activeData['expense_count'] }} <span class="text-xs text-gray-500 font-medium">Belege</span></p>
                                <p class="text-xs text-gray-400 font-mono">Netto: {{ number_format($activeData['expenses_net'], 2, ',', '.') }} €</p>
                            </div>

                            <div class="col-span-2">
                                <div class="bg-gradient-to-r from-gray-950 to-gray-900 border border-gray-800 p-5 rounded-2xl flex justify-between items-center shadow-lg mb-4">
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-widest font-black mb-1">Vorläufiger EÜR Gewinn</p>
                                        <p class="text-[10px] text-gray-500 font-medium">Betriebsergebnis (Netto) nach Kosten</p>
                                    </div>
                                    <span class="text-3xl font-serif font-bold {{ $activeData['profit'] >= 0 ? 'text-emerald-400 drop-shadow-[0_0_10px_rgba(16,185,129,0.3)]' : 'text-red-400 drop-shadow-[0_0_10px_rgba(239,68,68,0.3)]' }}">
                                        {{ number_format($activeData['profit'], 2, ',', '.') }} €
                                    </span>
                                </div>

                                {{-- Mini Visualisierung Chart --}}
                                <div class="bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner">
                                    <div class="flex justify-between text-[9px] font-black uppercase tracking-widest text-gray-500 mb-3">
                                       <span>Verhältnis (Netto)</span>
                                    </div>
                                    <div class="flex h-2.5 rounded-full overflow-hidden border border-gray-900">
                                        @php
                                            $absRev = abs($activeData['revenue_net']);
                                            $absExp = abs($activeData['expenses_net']);
                                            $totalForChart = $absRev + $absExp;
                                            $revPct = $totalForChart > 0 ? ($absRev / $totalForChart) * 100 : 50;
                                            $expPct = $totalForChart > 0 ? ($absExp / $totalForChart) * 100 : 50;
                                        @endphp
                                        <div class="bg-blue-500 h-full transition-all duration-1000 shadow-[0_0_10px_rgba(59,130,246,0.5)]" style="width: {{ $revPct }}%" title="Umsätze"></div>
                                        <div class="bg-red-500 h-full transition-all duration-1000 shadow-[0_0_10px_rgba(239,68,68,0.5)]" style="width: {{ $expPct }}%" title="Ausgaben"></div>
                                    </div>
                                    <div class="flex justify-between mt-3 text-[9px] font-black tracking-wider">
                                        <span class="{{ $activeData['revenue_net'] < 0 ? 'text-red-400' : 'text-blue-400' }} uppercase">{{ round($revPct) }}% {{ $activeData['revenue_net'] < 0 ? 'Erlösminderung' : 'Einnahmen' }}</span>
                                        <span class="text-red-400 uppercase">{{ round($expPct) }}% Ausgaben</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SCHRITT FÜR SCHRITT ANLEITUNG --}}
                <div class="col-span-1 lg:col-span-12 mt-6">
                    <div class="bg-gray-900 border border-gray-800 rounded-[2rem] p-6 lg:p-8 relative overflow-hidden shadow-inner">
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 via-transparent to-purple-500/5 pointer-events-none"></div>
                        <h4 class="text-[12px] font-black text-white uppercase tracking-widest mb-6 flex items-center gap-3 relative z-10">
                            <x-heroicon-s-rocket-launch class="w-5 h-5 text-blue-400" />
                            Schritt-für-Schritt zur erfolgreichen Übermittlung
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 relative z-10">
                            <!-- Schritt 1 -->
                            <div class="bg-gray-950/50 border border-gray-800 rounded-2xl p-6 relative overflow-hidden group hover:border-blue-500/50 transition-colors shadow-lg">
                                <div class="absolute -right-4 -top-4 text-[80px] font-black text-white/[0.02] group-hover:text-blue-500/10 transition-colors">1</div>
                                <h5 class="text-[11px] font-black uppercase text-blue-400 tracking-widest mb-3 flex items-center gap-2">
                                    <x-heroicon-o-document-check class="w-5 h-5" />
                                    Belege vervollständigen
                                </h5>
                                <p class="text-[11px] text-gray-400 leading-relaxed font-medium">
                                    Lade alle fehlenden Rechnungen als PDF/Bild hoch. Eine lückenlose Belegführung ist Pflicht für den Vorsteuerabzug. Erst wenn die <b class="text-gray-300">Master Checklist</b> rechts komplett auf <b class="text-emerald-400">Grün</b> springt, ist das System bereit.
                                </p>
                            </div>

                            <!-- Schritt 2 -->
                            <div class="bg-gray-950/50 border border-gray-800 rounded-2xl p-6 relative overflow-hidden group hover:border-purple-500/50 transition-colors shadow-lg">
                                <div class="absolute -right-4 -top-4 text-[80px] font-black text-white/[0.02] group-hover:text-purple-500/10 transition-colors">2</div>
                                <h5 class="text-[11px] font-black uppercase text-purple-400 tracking-widest mb-3 flex items-center gap-2">
                                    <x-heroicon-o-lock-closed class="w-5 h-5" />
                                    Authentifizieren
                                </h5>
                                <p class="text-[11px] text-gray-400 leading-relaxed font-medium">
                                    Wähle dein Zertifikat und tippe dein <b class="text-emerald-400">Zertifikats-Passwort</b> ein. Das ist exakt jenes Passwort, mit dem du dich regulär bei "Mein ELSTER" anmeldest. Wir speichern dieses Passwort zu deiner Sicherheit nicht!
                                </p>
                            </div>

                            <!-- Schritt 3 -->
                            <div class="bg-gray-950/50 border border-gray-800 rounded-2xl p-6 relative overflow-hidden group hover:border-[var(--theme-color-50)] transition-colors shadow-lg">
                                <div class="absolute -right-4 -top-4 text-[80px] font-black text-white/[0.02] group-hover:text-[var(--theme-color)]/10 transition-colors">3</div>
                                <h5 class="text-[11px] font-black uppercase text-[var(--theme-color)] tracking-widest mb-3 flex items-center gap-2">
                                    <x-heroicon-o-paper-airplane class="w-5 h-5" />
                                    Übermittlung starten
                                </h5>
                                <p class="text-[11px] text-gray-400 leading-relaxed font-medium">
                                    Klicke links auf den <b class="text-[var(--theme-color)]">An ELSTER übermitteln</b> Button. Das System jagt nun deine Zahlen durch das offizielle ELSTER-Rechenzentrum sicher und verschlüsselt. Verfolge danach das <b class="text-gray-300">Terminal Log</b> unten für dein Erfolgsticket!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- VOLLE BREITE UNTEN: FUNKI TERMINAL LOG --}}
                <div class="col-span-1 lg:col-span-12 mt-4 pt-8 border-t border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest flex items-center gap-2">
                            <x-heroicon-s-command-line class="w-4 h-4 text-[var(--theme-color)]" />
                            System- & API-Protokoll (Live)
                        </h4>
                        
                        <button wire:click="checkApiStatus" wire:loading.attr="disabled" class="bg-gray-900 hover:bg-gray-800 border border-gray-700 hover:border-blue-500 px-3 py-1.5 rounded-lg text-[9px] font-black text-gray-400 hover:text-blue-400 uppercase tracking-widest transition-colors flex items-center gap-2 shadow-inner group disabled:opacity-50 cursor-pointer">
                            <x-heroicon-s-signal class="w-3 h-3 group-hover:animate-pulse" />
                            <span wire:loading.remove wire:target="checkApiStatus">API Ping Test</span>
                            <span wire:loading wire:target="checkApiStatus" class="text-blue-400">Pinge Server...</span>
                        </button>
                    </div>

                    <div class="bg-[#0D1117] rounded-2xl border border-gray-800 p-4 h-56 overflow-y-auto custom-scrollbar font-mono text-[11px] shadow-inner relative">
                        <div class="absolute top-0 left-0 w-1 h-full bg-[var(--theme-color-20)]"></div>

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

                            {{-- Blinkender Cursor am Ende --}}
                            <div class="flex gap-3 text-[var(--theme-color)] mt-2">
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
                            <p class="text-[10px] font-mono text-[var(--theme-color)] mt-1">Via .pfx Zertifikatsdatei</p>
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

                    {{-- TAX EXPLANATIONS / WISSENS-KACHELN --}}
                    <div class="mt-12 mb-6 animate-fade-in-up">
                        <h4 class="text-xl font-serif font-bold text-white tracking-tight flex items-center gap-3 mb-6">
                            <x-heroicon-s-academic-cap class="w-6 h-6 text-[var(--theme-color)]" />
                            Das 1x1 der Steuern für "{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}"
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            
                            {{-- Einkommenssteuer --}}
                            <div class="bg-gray-900/80 border border-gray-800 rounded-[2rem] p-6 shadow-xl relative overflow-hidden group hover:border-[var(--theme-color-50)] transition-all">
                                <div class="absolute -right-6 -top-6 w-24 h-24 bg-[var(--theme-color-10)] rounded-full blur-2xl group-hover:bg-[var(--theme-color-20)] transition-all duration-500"></div>
                                <h5 class="text-lg font-bold text-white mb-2 flex items-center gap-2 relative z-10">
                                    <span class="w-8 h-8 rounded-full bg-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] border border-[var(--theme-color-30)]">1</span>
                                    Einkommensteuer (ESt)
                                </h5>
                                <p class="text-xs text-gray-400 leading-relaxed relative z-10 mb-4 font-medium">
                                    Diese Steuer zahlst du komplett privat auf deinen "persönlichen Gewinn" am Jahresende. Sie hat nichts mit dem monatlichen Firmenkonto zu tun! Das Finanzamt schaut sich im nächsten Jahr an, wie viel Gewinn "{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}" unterm Strich abgeworfen hat, rechnet deine persönlichen Freibeträge dagegen und besteuert den Überschuss.
                                </p>
                                <div class="bg-gray-950 p-3 rounded-xl border border-gray-800 shadow-inner text-[11px] text-gray-400 relative z-10">
                                    <strong class="text-[var(--theme-color)] block mb-1">💡 Tipp für dich:</strong>
                                    Geld, das du dir privat überweist (Privatentnahmen), mindert deinen zu versteuernden Gewinn <b>nicht</b>. Lege am besten monatlich etwa 20-30% deines Gewinns auf ein separates privates Tagesgeldkonto (z.B. N26/TradeRepublic) zurück, um böse Überraschungen bei der Jahresendabrechnung zu vermeiden!
                                </div>
                            </div>

                            {{-- Umsatzsteuer --}}
                            <div class="bg-gray-900/80 border border-gray-800 rounded-[2rem] p-6 shadow-xl relative overflow-hidden group hover:border-[var(--theme-color-50)] transition-all">
                                <div class="absolute -right-6 -top-6 w-24 h-24 bg-[var(--theme-color-10)] rounded-full blur-2xl group-hover:bg-[var(--theme-color-20)] transition-all duration-500"></div>
                                <h5 class="text-lg font-bold text-white mb-2 flex items-center gap-2 relative z-10">
                                    <span class="w-8 h-8 rounded-full bg-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] border border-[var(--theme-color-30)]">2</span>
                                    Umsatzsteuer (USt)
                                </h5>
                                <p class="text-xs text-gray-400 leading-relaxed relative z-10 mb-4 font-medium">
                                    Das ist das Modul, in dem du dich hier gerade befindest! Bei der Umsatzsteuer bist du eigentlich nur der kostenlose "Geldsammler" für den Staat. Du verlangst von deinen Kunden 19% MwSt. auf Gravuren oder Seelenfunke-Anhänger. Im Gegenzug darfst du dir die gezahlte 19% Steuer von Einkäufen (z.B. Laser-Material, Kartons) wieder zurückholen (Vorsteuer).
                                </p>
                                <div class="bg-gray-950 p-3 rounded-xl border border-gray-800 shadow-inner text-[11px] text-gray-400 relative z-10">
                                    <strong class="text-[var(--theme-color)] block mb-1">💡 Tipp für dich:</strong>
                                    Achtung bei Facebook/Google Ads oder Software-Abos aus dem EU-Ausland: Häufig steht da 0% Steuern. Hier bist du per <b class="text-gray-300">"Reverse Charge"</b> trotzdem verpflichtet, theoretische 19% zu deklarieren, darfst sie aber im selben Moment als Vorsteuer abziehen. Ein bürokratisches Nullsummenspiel!
                                </div>
                            </div>

                            {{-- Gewerbesteuer --}}
                            <div class="bg-gray-900/80 border border-gray-800 rounded-[2rem] p-6 shadow-xl relative overflow-hidden group hover:border-[var(--theme-color-50)] transition-all">
                                <div class="absolute -right-6 -top-6 w-24 h-24 bg-[var(--theme-color-10)] rounded-full blur-2xl group-hover:bg-[var(--theme-color-20)] transition-all duration-500"></div>
                                <h5 class="text-lg font-bold text-white mb-2 flex items-center gap-2 relative z-10">
                                    <span class="w-8 h-8 rounded-full bg-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] border border-[var(--theme-color-30)]">3</span>
                                    Gewerbesteuer (GewSt)
                                </h5>
                                <p class="text-xs text-gray-400 leading-relaxed relative z-10 mb-4 font-medium">
                                    Da du "{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}" als Gewerbe angemeldet hast, möchte deine Stadt/Gemeinde einen kleinen Anteil am Erfolg. Doch Entwarnung: Für Einzelunternehmen gibt es einen fetten gesetzlichen <b>Freibetrag von aktuell 24.500 Euro REINEM GEWINN</b> pro Jahr (Umsatz minus alle Kosten). Bleibst du darunter, fällt exakt 0,00 Euro an!
                                </p>
                                <div class="bg-gray-950 p-3 rounded-xl border border-gray-800 shadow-inner text-[11px] text-gray-400 relative z-10">
                                    <strong class="text-[var(--theme-color)] block mb-1">💡 Tipp für dich:</strong>
                                    Du musst die monatlich überhaupt nicht beachten. Die Gewerbesteuererklärung wird nur 1x jährlich zusammen mit deiner normalen Einkommenssteuer beim Finanzamt via ELSTER eingereicht. Falls Gewinn unter 24.500€, ist das praktisch nur ein kurzes Abnicken.
                                </div>
                            </div>
                            
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
