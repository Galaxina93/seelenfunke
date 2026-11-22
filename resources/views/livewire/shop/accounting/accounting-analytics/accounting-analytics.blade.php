<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <div class="min-h-screen bg-transparent pb-20 font-sans text-gray-700 dark:text-gray-300">
        <script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>

        {{-- Toast Feedback --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" x-cloak
                 class="fixed bottom-6 right-6 bg-emerald-50 dark:bg-emerald-950 border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-5 py-3.5 rounded-2xl shadow-lg z-50 flex items-center gap-3">
                <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500 shrink-0" />
                <span class="font-black uppercase tracking-widest text-[10px]">{{ session('success') }}</span>
            </div>
        @endif

        {{-- Sticky Header: Bereichs-Umschalter + Zeitraum + Netto/Brutto --}}
        <div class="bg-white/90 dark:bg-gray-900/90 backdrop-blur-md shadow-sm border-b border-gray-200 dark:border-gray-800 sticky top-0 z-30 transition-colors">
            <div class="w-full px-4 py-4 md:py-5 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
                <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-3 tracking-tight">
                        <div class="p-2.5 bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] rounded-xl text-[var(--theme-color)] shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <span>Auswertung & Übersicht</span>
                    </h1>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help select-none"
                          title="Getrennte Finanzanalyse für Gewerbe (Mein-Seelenfunke) und Privat-Haushalt inkl. dynamischem Unternehmerlohn und Reinvestitions-Planung.">!</span>

                    {{-- Scope-Tabs: Gewerbe | Privat | Gesamt --}}
                    <div class="inline-flex items-center bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
                        @foreach(['business' => 'Gewerbe', 'private' => 'Privat', 'all' => 'Gesamt'] as $scopeKey => $scopeLabel)
                            <button type="button"
                                    wire:click="setScope('{{ $scopeKey }}')"
                                    class="px-3.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $scope === $scopeKey ? 'bg-[var(--theme-color)] text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                                {{ $scopeLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:gap-3 bg-gray-100 dark:bg-gray-950 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-800 w-full xl:w-auto">
                    {{-- Monat --}}
                    <div class="relative flex-1 sm:flex-none">
                        <select wire:model.live="selectedMonth"
                                class="appearance-none w-full bg-white dark:bg-gray-900 pl-3.5 pr-9 py-2 rounded-xl text-xs font-bold text-gray-800 dark:text-white border border-gray-200 dark:border-gray-800 focus:ring-2 focus:ring-[var(--theme-color-40)] outline-none cursor-pointer sm:w-36">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ \Carbon\Carbon::create()->month($m)->locale('de')->monthName }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2.5 pointer-events-none text-gray-500 dark:text-gray-400">
                            <x-heroicon-m-chevron-down class="w-4 h-4" />
                        </div>
                    </div>

                    {{-- Jahr --}}
                    <div class="relative flex-1 sm:flex-none">
                        <select wire:model.live="selectedYear"
                                class="appearance-none w-full bg-white dark:bg-gray-900 pl-3.5 pr-9 py-2 rounded-xl text-xs font-bold text-gray-800 dark:text-white border border-gray-200 dark:border-gray-800 focus:ring-2 focus:ring-[var(--theme-color-40)] outline-none cursor-pointer sm:w-24">
                            @foreach(range(date('Y') - 2, date('Y') + 3) as $y)
                                <option value="{{ $y }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ $y }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2.5 pointer-events-none text-gray-500 dark:text-gray-400">
                            <x-heroicon-m-chevron-down class="w-4 h-4" />
                        </div>
                    </div>

                    <div class="h-6 w-px bg-gray-300 dark:bg-gray-800 hidden sm:block"></div>

                    {{-- Brutto/Netto Toggle --}}
                    <label class="inline-flex items-center cursor-pointer px-2.5 py-1.5 rounded-xl hover:bg-gray-200/70 dark:hover:bg-gray-900 transition-colors"
                           title="Wechselt gewerbliche Beträge zwischen Netto (ohne USt.) und Brutto (inkl. USt.)">
                        <input type="checkbox" wire:model.live="isNet" class="sr-only peer">
                        <div class="relative w-9 h-5 bg-gray-300 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-full peer peer-checked:bg-[var(--theme-color-20)] peer-checked:border-[var(--theme-color-50)] after:content-[''] after:absolute after:top-[1px] after:start-[1px] after:bg-white dark:after:bg-gray-400 peer-checked:after:bg-[var(--theme-color)] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                        <span class="ms-2 text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 w-[46px]">
                            {{ $isNet ? 'Netto' : 'Brutto' }}
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="w-full px-4 mt-6 md:mt-8 space-y-6 md:space-y-8">

            {{-- ==================== SEKTION 1: DYNAMISCHES GEHALTS- & RÜCKLAGEN-COCKPIT ==================== --}}
            @php
                $pots = $payoutPlan['pots'];
                $mirror = $payoutPlan['private_mirror'];
                $taxProj = $payoutPlan['tax_projection'];
                $safeSalary = (float) $payoutPlan['safe_monthly_salary'];
                $isSim = (bool) $payoutPlan['is_simulation'];
                $salaryTooltip = 'Berechnung: Netto-Shopumsatz minus laufende Betriebskosten, Einkommensteuer-Rücklage (§ 32a EStG inkl. § 35 EStG Anrechnung, ohne Gründungszuschuss), Kontopuffer und aktive Reinvestitions-Sparraten.';
            @endphp

            <section class="bg-white dark:bg-gray-900/80 rounded-[2.5rem] shadow-sm border border-gray-200 dark:border-gray-800 p-5 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1.5 h-full bg-[var(--theme-color)]"></div>

                {{-- Header Zeile --}}
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 border-b border-gray-200 dark:border-gray-800">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                            <x-heroicon-o-banknotes class="w-5 h-5 text-[var(--theme-color)] shrink-0" />
                            <span>Unternehmerlohn & Rücklagen</span>
                        </h2>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help select-none"
                              title="{{ $salaryTooltip }}">!</span>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border {{ $isSim ? 'bg-[var(--theme-color-10)] text-[var(--theme-color)] border-[var(--theme-color-30)]' : 'bg-gray-100 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-800' }}">
                            {{ $isSim ? 'Test-Simulation' : $payoutPlan['period_label'] }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        {{-- Live vs. Simulator Switch --}}
                        <div class="inline-flex items-center bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
                            <button type="button"
                                    wire:click="toggleSimulation"
                                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ !$isSim ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                                    title="Berechnung auf Basis der echten Rechnungen und Belege des gewählten Monats">
                                Live-Ist
                            </button>
                            <button type="button"
                                    wire:click="toggleSimulation"
                                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $isSim ? 'bg-[var(--theme-color)] text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                                    title="Umsatz-Simulator für die Testphase vor Livegang">
                                Simulator
                            </button>
                        </div>

                        {{-- Parameter Modal Button --}}
                        <button type="button"
                                wire:click="$set('showPayoutConfigModal', true)"
                                class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-950 text-gray-800 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 transition-colors cursor-pointer"
                                title="Kontopuffer, Lebenshaltungs-Pauschale und AfA-Rücklage anpassen">
                            <x-heroicon-o-adjustments-horizontal class="w-4 h-4 text-[var(--theme-color)]" />
                            <span>Parameter</span>
                        </button>
                    </div>
                </div>

                {{-- Kompakte Simulator-Leiste (nur aktiv im Simulator-Modus) --}}
                @if($isSim)
                    <div class="mt-4 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/80 border border-[var(--theme-color-30)] flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">Brutto-Monatsumsatz (€)</label>
                            <input type="number" step="100" min="0"
                                   wire:model.live.debounce.400ms="simMonthlyGrossRevenue"
                                   class="w-32 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-1.5 text-xs font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-40)] outline-none">
                            <div class="flex items-center gap-1.5">
                                @foreach([2500, 3800, 5500, 8000] as $preset)
                                    <button type="button"
                                            wire:click="applyQuickSimRevenue({{ $preset }})"
                                            class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-bold border transition-colors cursor-pointer {{ (int) round((float) $simMonthlyGrossRevenue) === $preset ? 'bg-[var(--theme-color)] text-white border-[var(--theme-color)]' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)]' }}">
                                        {{ number_format($preset, 0, ',', '.') }} €
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400"
                                   title="Anteil für Rohlinge, Verpackung und Versand am Nettoumsatz">Material & Versand (%)</label>
                            <input type="number" step="1" min="0" max="90"
                                   wire:model.live.debounce.400ms="simCogsPercent"
                                   class="w-20 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-1.5 text-xs font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-40)] outline-none">
                        </div>
                    </div>
                @endif

                {{-- Haupt-Grid: Auszahlbares Gehalt + 5 Töpfe (links) | Privater Haushalts-Spiegel (rechts) --}}
                <div class="mt-6 grid grid-cols-1 xl:grid-cols-12 gap-6 items-stretch">

                    {{-- Links (8 Spalten): Auszahlbares Gehalt + 5 Töpfe --}}
                    <div class="xl:col-span-8 bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex flex-col justify-between gap-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Sicher auszahlbares Gehalt</span>
                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                                          title="Freie Privatentnahme vom Geschäftskonto nach Abzug aller Steuern, Betriebskosten, Puffer und geplanten Reinvestitionen.">!</span>
                                </div>
                                <div class="mt-1 text-3xl sm:text-5xl font-serif font-black tracking-tight font-mono {{ $safeSalary > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-900 dark:text-white' }}">
                                    {{ number_format($safeSalary, 2, ',', '.') }} €
                                </div>
                            </div>

                            <div class="flex flex-wrap sm:flex-col items-start sm:items-end gap-1.5 text-xs font-mono">
                                <div class="px-3 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300">
                                    Brutto-Zufluss: <span class="font-bold text-gray-900 dark:text-white">{{ number_format($payoutPlan['gross_revenue'], 2, ',', '.') }} €</span>
                                </div>
                                @if($pots['vat_refund_credit'] > 0)
                                    <div class="px-3 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/60 text-[11px] font-bold text-emerald-700 dark:text-emerald-400"
                                         title="Vorsteuer-Überhang aus Einkäufen im gewählten Monat (wird vom Finanzamt erstattet)">
                                        + {{ number_format($pots['vat_refund_credit'], 2, ',', '.') }} € VSt-Erstattung
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Die 5 Rücklagen-Töpfe --}}
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 pt-4 border-t border-gray-200 dark:border-gray-800">
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3"
                                 title="Topf 1: Für das Finanzamt reservierte Umsatzsteuer-Zahllast (Verkaufs-USt abzüglich Vorsteuer)">
                                <div class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">1. USt-Tresor</div>
                                <div class="mt-1 text-sm font-mono font-bold text-gray-900 dark:text-white">-{{ number_format($pots['vat_pot'], 2, ',', '.') }} €</div>
                            </div>

                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3"
                                 title="Topf 2: Gewerbliche Fixkosten ({{ number_format($pots['fix_costs_net'], 2, ',', '.') }} €) + laufender Material-/Betriebsaufwand ({{ number_format($pots['variable_costs_net'], 2, ',', '.') }} €) netto">
                                <div class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">2. Betrieb (Netto)</div>
                                <div class="mt-1 text-sm font-mono font-bold text-rose-600 dark:text-rose-400">-{{ number_format($pots['operating_pot'], 2, ',', '.') }} €</div>
                            </div>

                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3"
                                 title="Topf 3: Monatliche Einkommensteuer-Rücklage nach § 32a EStG (effektiver Satz: {{ number_format($taxProj['effective_tax_rate'], 1, ',', '.') }} %, Grundfreibetrag 12.348 € berücksichtigt)">
                                <div class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">3. ESt-Rücklage</div>
                                <div class="mt-1 text-sm font-mono font-bold text-amber-600 dark:text-amber-400">-{{ number_format($pots['income_tax_pot'], 2, ',', '.') }} €</div>
                            </div>

                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3"
                                 title="Topf 4: Auffüllung des eisernen Geschäftskonto-Puffers (Ziel: {{ number_format($payoutPlan['bank']['safety_buffer'], 2, ',', '.') }} €)">
                                <div class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">4. Kontopuffer</div>
                                <div class="mt-1 text-sm font-mono font-bold text-gray-900 dark:text-white">-{{ number_format($pots['buffer_pot'], 2, ',', '.') }} €</div>
                            </div>

                            <div class="col-span-2 sm:col-span-1 bg-white dark:bg-gray-900 border border-[var(--theme-color-30)] rounded-xl p-3"
                                 title="Topf 5: AfA-Erhaltungsrücklage ({{ number_format($pots['afa_replacement_rate'], 2, ',', '.') }} €) + aktive Reinvestitions-Ziele ({{ number_format($pots['active_plans_rate'], 2, ',', '.') }} €) | Geplant gesamt: {{ number_format($pots['planned_reinvestment_total'], 2, ',', '.') }} €/Monat">
                                <div class="text-[9px] font-black uppercase tracking-wider text-[var(--theme-color)] truncate">5. Reinvestition</div>
                                <div class="mt-1 text-sm font-mono font-bold text-[var(--theme-color)]">-{{ number_format($pots['planned_reinvestment_total'], 2, ',', '.') }} €</div>
                            </div>
                        </div>
                    </div>

                    {{-- Rechts (4 Spalten): Privater Haushalts-Spiegel & Mai-2027-Radar --}}
                    <div class="xl:col-span-4 bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex flex-col justify-between gap-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Privat-Deckung & Bedarf</span>
                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white dark:bg-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                                      title="Abgleich deiner privaten Einnahmen (inkl. Gründungszuschuss bis 30.04.2027) mit deinen privaten Fixkosten ({{ number_format($mirror['private_fix_smoothed'], 2, ',', '.') }} € geglättet) + Lebenshaltungs-Pauschale ({{ number_format($mirror['living_allowance'], 2, ',', '.') }} €).">!</span>
                            </div>
                            @if($mirror['is_gz_active_now'])
                                <span class="px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-[9px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                    GZ Phase 1 aktiv
                                </span>
                            @endif
                        </div>

                        <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between items-center py-1.5 border-b border-gray-200 dark:border-gray-800">
                                <span class="text-gray-600 dark:text-gray-400">Privat-Saldo ({{ $selectedMonth }}/{{ $selectedYear }})</span>
                                <span class="font-mono font-bold {{ $mirror['private_balance_now'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ $mirror['private_balance_now'] >= 0 ? '+' : '' }}{{ number_format($mirror['private_balance_now'], 2, ',', '.') }} €
                                </span>
                            </div>

                            <div class="flex justify-between items-center py-1.5 border-b border-gray-200 dark:border-gray-800">
                                <span class="text-gray-600 dark:text-gray-400" title="Ab 01.05.2027 entfällt der Gründungszuschuss (2.106,30 €). Diesen Betrag muss der Shop dann monatlich als Privatentnahme liefern.">Mindest-Gehalt ab 05/2027</span>
                                <span class="font-mono font-bold text-gray-900 dark:text-white">{{ number_format($mirror['post_gz_needed_salary'], 2, ',', '.') }} €</span>
                            </div>

                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-gray-600 dark:text-gray-400" title="Benötigter monatlicher Brutto-Shopumsatz ab Mai 2027, um Privatbedarf, Betriebskosten, Steuern und geplante Reinvestitionen vollständig zu decken.">Ziel-Umsatz ab 05/2027</span>
                                <span class="font-mono font-black text-[var(--theme-color)]">{{ number_format($mirror['post_gz_required_gross_revenue'], 2, ',', '.') }} €</span>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between text-[11px] font-bold">
                            @if($safeSalary >= $mirror['post_gz_needed_salary'] && $mirror['post_gz_needed_salary'] > 0)
                                <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <x-heroicon-s-check-circle class="w-4 h-4 shrink-0" />
                                    <span>Deckt Bedarf ab 05/2027 (+{{ number_format($safeSalary - $mirror['post_gz_needed_salary'], 2, ',', '.') }} €)</span>
                                </span>
                            @elseif($mirror['needed_from_shop_now'] == 0)
                                <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <x-heroicon-s-shield-check class="w-4 h-4 shrink-0" />
                                    <span>Aktuell 0,00 € Entnahme nötig</span>
                                </span>
                            @else
                                <span class="text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                    <x-heroicon-s-exclamation-triangle class="w-4 h-4 shrink-0" />
                                    <span>Fehlbetrag aktuell: {{ number_format(max(0, $mirror['needed_from_shop_now'] - $safeSalary), 2, ',', '.') }} €</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- ==================== SEKTION 2: REINVESTITIONS-PLANER & ANLAGEVERMÖGEN (AfA) ==================== --}}
            @php
                $plans = $payoutPlan['investment_plans'];
                $depr = $payoutPlan['depreciation'];
            @endphp

            <section class="bg-white dark:bg-gray-900/80 rounded-[2.5rem] shadow-sm border border-gray-200 dark:border-gray-800 p-5 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-gray-200 dark:border-gray-800">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                            <x-heroicon-o-cpu-chip class="w-5 h-5 text-[var(--theme-color)] shrink-0" />
                            <span>Reinvestitionen & Anlagen (AfA)</span>
                        </h2>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help select-none"
                              title="Geplante Neuanschaffungen (kalkulatorische Spar-Töpfe inkl. Zieldatum und § 7g EStG IAB-Steuerhebel) sowie laufende lineare AfA deiner bestehenden Maschinen > 800 € netto.">!</span>

                        <div class="inline-flex items-center bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
                            <button type="button"
                                    wire:click="$set('investmentTab', 'plans')"
                                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $investmentTab === 'plans' ? 'bg-[var(--theme-color)] text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                                Sparziele ({{ count($plans) }})
                            </button>
                            <button type="button"
                                    wire:click="$set('investmentTab', 'assets')"
                                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $investmentTab === 'assets' ? 'bg-[var(--theme-color)] text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                                Anlagegüter & GWG ({{ count($depr['assets']) + count($depr['gwg_items']) }})
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if($taxProj['iab_deduction_total'] > 0)
                            <span class="px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-[11px] font-bold text-emerald-700 dark:text-emerald-400"
                                  title="Aktiver § 7g EStG Investitionsabzugsbetrag (50 % Vorab-Abzug: {{ number_format($taxProj['iab_deduction_total'], 2, ',', '.') }} €)">
                                § 7g IAB aktiv (-{{ number_format($taxProj['iab_deduction_total'], 2, ',', '.') }} € zvE)
                            </span>
                        @endif

                        <button type="button"
                                wire:click="openCreatePlanModal"
                                class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[var(--theme-color)] text-white hover:opacity-90 transition-opacity cursor-pointer">
                            <x-heroicon-m-plus class="w-4 h-4" />
                            <span>Investitionsziel</span>
                        </button>
                    </div>
                </div>

                @if($investmentTab === 'plans')
                    {{-- Schlanke Haupttabelle: Kalkulatorische Reinvestitions-Ziele --}}
                    <div class="mt-4 overflow-x-auto no-scrollbar">
                        <table class="w-full text-xs text-left whitespace-nowrap border-collapse">
                            <thead class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                                <tr>
                                    <th class="py-3 pr-4">Bezeichnung</th>
                                    <th class="py-3 px-4 text-right">Ziel (Netto / Brutto)</th>
                                    <th class="py-3 px-4 text-right">Monatsrate</th>
                                    <th class="py-3 px-4 min-w-[180px]">Angespart</th>
                                    <th class="py-3 px-4">Erreicht am</th>
                                    <th class="py-3 pl-4 text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800/60">
                                @forelse($plans as $p)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors {{ !$p['is_active'] ? 'opacity-50' : '' }}">
                                        <td class="py-3.5 pr-4 font-bold text-gray-900 dark:text-white">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $p['title'] }}</span>
                                                @if($p['use_iab'])
                                                    <span class="px-1.5 py-0.5 rounded bg-[var(--theme-color-15)] text-[var(--theme-color)] text-[9px] font-black uppercase"
                                                          title="§ 7g EStG Investitionsabzugsbetrag aktiv: {{ number_format($p['iab_50_amount'], 2, ',', '.') }} € (50 % Netto) vorab gewinnmindernd">
                                                        § 7g IAB
                                                    </span>
                                                @endif
                                                @if($p['afa_years'] > 0)
                                                    <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-[9px] font-mono"
                                                          title="Spätere AfA nach Kauf: {{ number_format($p['future_monthly_afa'], 2, ',', '.') }} €/Monat über {{ $p['afa_years'] }} Jahre">
                                                        AfA {{ $p['afa_years'] }}J
                                                    </span>
                                                @endif
                                            </div>
                                        </td>

                                        <td class="py-3.5 px-4 text-right font-mono">
                                            <div class="font-bold text-gray-900 dark:text-white">{{ number_format($p['target_net'], 2, ',', '.') }} €</div>
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400"
                                                 title="Beim Kauf sofort erstattbare Vorsteuer: +{{ number_format($p['vat_refund_on_purchase'], 2, ',', '.') }} €">
                                                Brutto {{ number_format($p['target_gross'], 2, ',', '.') }} €
                                            </div>
                                        </td>

                                        <td class="py-3.5 px-4 text-right font-mono">
                                            <div class="font-bold text-[var(--theme-color)]">{{ number_format($p['effective_monthly_rate'], 2, ',', '.') }} €</div>
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400">
                                                {{ $p['allocation_mode'] === 'profit_percent' ? number_format($p['profit_percent'], 1, ',', '.') . ' % Gewinn' : 'Fix / Monat' }}
                                            </div>
                                        </td>

                                        <td class="py-3.5 px-4">
                                            <div class="flex justify-between items-center text-[11px] font-mono mb-1">
                                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ number_format($p['accumulated_saved'], 2, ',', '.') }} €</span>
                                                <span class="text-gray-500 dark:text-gray-400">{{ $p['progress_percent'] }} %</span>
                                            </div>
                                            <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                                                <div class="h-full rounded-full bg-[var(--theme-color)] transition-all duration-300" style="width: {{ $p['progress_percent'] }}%"></div>
                                            </div>
                                        </td>

                                        <td class="py-3.5 px-4">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold {{ $p['on_track'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                                    {{ $p['estimated_reach_label'] }}
                                                </span>
                                                @if($p['months_remaining'] !== null && $p['months_remaining'] > 0)
                                                    <span class="text-[10px] font-mono text-gray-500 dark:text-gray-400">({{ $p['months_remaining'] }} Mon.)</span>
                                                @endif
                                                @if(!$p['on_track'] && $p['required_rate_for_target'])
                                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-amber-100 dark:bg-amber-950 text-[10px] font-black text-amber-700 dark:text-amber-400 cursor-help"
                                                          title="Für Wunschtermin {{ $p['target_label'] }} wären {{ number_format($p['required_rate_for_target'], 2, ',', '.') }} €/Monat nötig.">!</span>
                                                @endif
                                            </div>
                                        </td>

                                        <td class="py-3.5 pl-4 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button"
                                                        wire:click="togglePlanActive('{{ $p['id'] }}')"
                                                        class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                                                        title="{{ $p['is_active'] ? 'Pausieren' : 'Aktivieren' }}">
                                                    @if($p['is_active'])
                                                        <x-heroicon-m-pause-circle class="w-4 h-4 text-emerald-500" />
                                                    @else
                                                        <x-heroicon-m-play-circle class="w-4 h-4" />
                                                    @endif
                                                </button>
                                                <button type="button"
                                                        wire:click="openEditPlanModal('{{ $p['id'] }}')"
                                                        class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                                                        title="Bearbeiten">
                                                    <x-heroicon-m-pencil-square class="w-4 h-4" />
                                                </button>
                                                <button type="button"
                                                        wire:click="deletePlan('{{ $p['id'] }}')"
                                                        wire:confirm="Investitionsziel wirklich entfernen?"
                                                        class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:text-rose-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                                                        title="Löschen">
                                                    <x-heroicon-m-trash class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                            Noch keine kalkulatorischen Reinvestitions-Ziele hinterlegt.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    {{-- Tab 2: Gebuchte Anlagegüter (> 800 € netto, AfA) & GWG (250 € - 800 € netto) --}}
                    <div class="mt-4 space-y-6">
                        <div class="overflow-x-auto no-scrollbar">
                            <table class="w-full text-xs text-left whitespace-nowrap border-collapse">
                                <thead class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                                    <tr>
                                        <th class="py-3 pr-4">Anlagegut (&gt; 800 € Netto)</th>
                                        <th class="py-3 px-4">Kaufdatum</th>
                                        <th class="py-3 px-4 text-right">Anschaffung (Netto)</th>
                                        <th class="py-3 px-4 text-right">Lineare AfA / Monat</th>
                                        <th class="py-3 px-4 text-right">AfA {{ $selectedYear }}</th>
                                        <th class="py-3 pl-4 text-right">Restbuchwert</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800/60">
                                    @forelse($depr['assets'] as $asset)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                            <td class="py-3.5 pr-4 font-bold text-gray-900 dark:text-white max-w-xs truncate" title="{{ $asset['title'] }}">
                                                {{ $asset['title'] }}
                                            </td>
                                            <td class="py-3.5 px-4 font-mono text-gray-600 dark:text-gray-400">
                                                {{ $asset['purchase_date'] }} <span class="text-[10px]">(bis {{ $asset['end_date'] }})</span>
                                            </td>
                                            <td class="py-3.5 px-4 text-right font-mono font-bold text-gray-900 dark:text-white">
                                                {{ number_format($asset['net_cost'], 2, ',', '.') }} €
                                            </td>
                                            <td class="py-3.5 px-4 text-right font-mono text-[var(--theme-color)] font-bold">
                                                {{ number_format($asset['monthly_afa'], 2, ',', '.') }} €
                                            </td>
                                            <td class="py-3.5 px-4 text-right font-mono text-gray-800 dark:text-gray-200">
                                                {{ number_format($asset['year_afa'], 2, ',', '.') }} €
                                            </td>
                                            <td class="py-3.5 pl-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                                {{ number_format($asset['book_value'], 2, ',', '.') }} €
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-6 text-center text-gray-500 dark:text-gray-400">Keine Anlagegüter &gt; 800 € Netto bis {{ $selectedMonth }}/{{ $selectedYear }}.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if(count($depr['gwg_items']) > 0)
                            <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                        GWG & Sofort-Inventar {{ $selectedYear }} (150 € - 800 € Netto)
                                    </span>
                                    <span class="text-xs font-mono font-bold text-gray-900 dark:text-white">
                                        Summe Netto: {{ number_format($depr['gwg_total_net'], 2, ',', '.') }} €
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($depr['gwg_items'] as $gwg)
                                        <div class="px-3 py-1.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs flex items-center gap-2">
                                            <span class="font-medium text-gray-800 dark:text-gray-200 truncate max-w-[220px]" title="{{ $gwg['title'] }}">{{ $gwg['title'] }}</span>
                                            <span class="font-mono font-bold text-[var(--theme-color)]">{{ number_format($gwg['net_cost'], 2, ',', '.') }} €</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            {{-- ==================== SEKTION 3: MONATS-SALDO DES GEWÄHLTEN BEREICHS ==================== --}}
            <div class="bg-white dark:bg-gray-900/80 rounded-[2.5rem] shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="p-6 md:p-8 bg-gray-50/50 dark:bg-gray-950/40">
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">
                                Monats-Saldo: {{ $scope === 'business' ? 'Gewerbe (Mein-Seelenfunke)' : ($scope === 'private' ? 'Privat-Haushalt' : 'Gesamt (Gewerbe + Privat)') }}
                            </span>
                            <label class="inline-flex items-center cursor-pointer bg-white dark:bg-gray-900 px-3 py-1 rounded-full border border-gray-200 dark:border-gray-800"
                                   title="Blendet einmalige Sonderausgaben im Monats-Saldo aus">
                                <input type="checkbox" wire:model.live="excludeSpecialExpenses" class="sr-only peer">
                                <div class="relative w-7 h-4 bg-gray-300 dark:bg-gray-800 rounded-full peer peer-checked:bg-emerald-500/30 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white dark:after:bg-gray-400 peer-checked:after:bg-emerald-500 after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-[12px]"></div>
                                <span class="ms-2 text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 select-none">Ohne Sonderausgaben</span>
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <select wire:model="exportMonth" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 text-[10px] font-black uppercase tracking-widest rounded-xl px-3 py-2 outline-none cursor-pointer">
                                @foreach(range(1, 12) as $m)
                                    <option value="{{ $m }}" class="bg-white dark:bg-gray-900">{{ \Carbon\Carbon::create()->month($m)->locale('de')->shortMonthName }}</option>
                                @endforeach
                            </select>
                            <select wire:model="exportYear" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 text-[10px] font-black uppercase tracking-widest rounded-xl px-3 py-2 outline-none cursor-pointer">
                                @foreach(range(date('Y') - 2, date('Y') + 2) as $y)
                                    <option value="{{ $y }}" class="bg-white dark:bg-gray-900">{{ $y }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="downloadTaxExport"
                                    class="flex items-center gap-1.5 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors cursor-pointer">
                                <x-heroicon-m-arrow-down-tray class="w-4 h-4 text-[var(--theme-color)]" />
                                <span>CSV-Export</span>
                            </button>
                        </div>
                    </div>

                    @php
                        $mainValue = $excludeSpecialExpenses ? ($stats['total_budget'] + $stats['fixed_expenses']) : $stats['available'];
                    @endphp

                    <div class="flex flex-col xl:flex-row items-start xl:items-center justify-between gap-6">
                        <div>
                            <div class="text-4xl md:text-5xl font-serif font-black tracking-tight font-mono {{ $mainValue >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ number_format($mainValue, 2, ',', '.') }} €
                            </div>
                            @if($scope !== 'private')
                                <div class="mt-2 inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                    <span>Shop-Umsatz:</span>
                                    <span class="font-mono font-bold text-[var(--theme-color)] bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] px-2 py-0.5 rounded">
                                        {{ $stats['shop_income'] >= 0 ? '+' : '' }}{{ number_format($stats['shop_income'], 2, ',', '.') }} €
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 w-full xl:w-auto">
                            <div class="bg-white dark:bg-gray-950 p-4 rounded-2xl border border-gray-200 dark:border-gray-800 min-w-[160px]">
                                <div class="text-[9px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Einnahmen</div>
                                <div class="text-xl font-mono font-bold text-gray-900 dark:text-white">+{{ number_format($stats['total_budget'], 2, ',', '.') }} €</div>
                            </div>

                            <div class="bg-white dark:bg-gray-950 p-4 rounded-2xl border border-gray-200 dark:border-gray-800 min-w-[160px]">
                                <div class="text-[9px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Fixkosten</div>
                                <div class="text-xl font-mono font-bold text-rose-600 dark:text-rose-400">{{ number_format($stats['fixed_expenses'], 2, ',', '.') }} €</div>
                            </div>

                            <div class="bg-white dark:bg-gray-950 p-4 rounded-2xl border border-gray-200 dark:border-gray-800 min-w-[160px] transition-opacity {{ $excludeSpecialExpenses ? 'opacity-40 border-dashed' : 'opacity-100' }}">
                                <div class="text-[9px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Sonderausgaben</div>
                                <div class="text-xl font-mono font-bold text-orange-600 dark:text-orange-400">{{ number_format($stats['special_expenses'], 2, ',', '.') }} €</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== SEKTION 4: JAHRESÜBERSICHT & BALKEN-CHART ==================== --}}
            <div class="bg-white dark:bg-gray-900/80 rounded-[2.5rem] shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-6 md:p-8 bg-gray-50/80 dark:bg-gray-950/60 border-b border-gray-200 dark:border-gray-800 gap-4">
                    <h2 class="text-lg md:text-xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-3">
                        <div class="p-2 bg-gray-100 dark:bg-gray-800 rounded-xl text-[var(--theme-color)]">
                            <x-heroicon-o-chart-bar-square class="w-5 h-5" />
                        </div>
                        <span>Jahresübersicht <span class="text-[var(--theme-color)]">{{ $selectedYear }}</span></span>
                    </h2>

                    <div class="flex flex-wrap gap-2 items-center w-full sm:w-auto">
                        <select wire:model.live="chartFilter" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 text-[10px] font-black uppercase tracking-widest rounded-xl px-3.5 py-2.5 outline-none cursor-pointer">
                            <option value="last_12_months" class="bg-white dark:bg-gray-900">Letzte 12 Monate</option>
                            <option value="this_year" class="bg-white dark:bg-gray-900">Dieses Jahr</option>
                            <option value="custom" class="bg-white dark:bg-gray-900">Zeitraum</option>
                        </select>
                        @if($chartFilter === 'custom')
                            <input type="date" wire:model.live="dateFrom" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 text-xs rounded-xl px-3 py-2 outline-none">
                            <input type="date" wire:model.live="dateTo" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 text-xs rounded-xl px-3 py-2 outline-none">
                        @endif
                    </div>
                </div>

                <div>
                    <div id="yearly-bar-data-bridge"
                         data-labels='@json($barData['labels'])'
                         data-income='@json($barData['income'])'
                         data-expense='@json($barData['expense'])'
                         class="hidden"></div>

                    <div class="p-6 md:p-8">
                        <div class="relative h-64 md:h-72 w-full" wire:ignore>
                            <canvas id="yearlyBarChart"></canvas>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-800 overflow-x-auto no-scrollbar pb-2">
                        <table class="w-full text-xs text-right whitespace-nowrap border-collapse">
                            <thead class="bg-gray-50/80 dark:bg-gray-950/80 text-gray-500 dark:text-gray-400 font-bold border-b border-gray-200 dark:border-gray-800">
                                <tr>
                                    <th class="p-4 text-left font-black uppercase tracking-widest text-[9px]">Kategorie</th>
                                    @foreach(range(1, 12) as $m)
                                        <th class="p-4 min-w-[70px] uppercase tracking-widest text-[9px]">{{ \Carbon\Carbon::create()->month($m)->locale('de')->shortMonthName }}</th>
                                    @endforeach
                                    <th class="p-4 font-black uppercase tracking-widest text-[9px] text-[var(--theme-color)] bg-gray-100 dark:bg-gray-900">Jahr</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800/50">
                                @foreach($yearlyMatrix['product_categories'] as $key => $category)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors cursor-pointer group"
                                        wire:click="toggleCategory('{{ $key }}')">
                                        <td class="p-4 text-left font-bold {{ $category['color'] }} flex items-center gap-3">
                                            <div class="w-2 h-2 rounded-full {{ $category['bg'] }}"></div>
                                            <span>{{ $category['label'] }}</span>
                                            <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-gray-500 transition-transform duration-200 {{ in_array($key, $expandedCategories) ? 'rotate-180 text-[var(--theme-color)]' : '' }}" />
                                        </td>
                                        @foreach(range(1, 12) as $m)
                                            <td class="p-4 text-gray-600 dark:text-gray-400 font-medium font-mono">{{ number_format($category['months'][$m], 2, ',', '.') }}</td>
                                        @endforeach
                                        <td class="p-4 font-bold bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white font-mono">{{ number_format($category['year_sum'], 2, ',', '.') }}</td>
                                    </tr>

                                    @if(in_array($key, $expandedCategories))
                                        @foreach($category['items'] as $item)
                                            <tr class="bg-gray-50/70 dark:bg-gray-950/50 text-[11px] text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800/30 last:border-b-0">
                                                <td class="p-3 pl-10 text-left border-l-2 {{ str_replace('bg-', 'border-', $category['bg']) }} font-medium">{{ $item['name'] }}</td>
                                                @foreach(range(1, 12) as $m)
                                                    <td class="p-3 font-mono">{{ $item['months'][$m] != 0 ? number_format($item['months'][$m], 2, ',', '.') : '-' }}</td>
                                                @endforeach
                                                <td class="p-3 font-bold text-gray-800 dark:text-gray-300 bg-gray-100 dark:bg-gray-900/40 font-mono">{{ number_format($item['year_sum'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach

                                <tr class="bg-gray-100 dark:bg-gray-900 border-t-2 border-gray-300 dark:border-gray-700">
                                    <td class="p-4 text-left uppercase text-[10px] font-black tracking-widest text-gray-900 dark:text-white">Saldo</td>
                                    @foreach(range(1, 12) as $m)
                                        <td class="p-4 font-mono font-bold text-sm {{ $yearlyMatrix['totals']['months'][$m] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                            {{ number_format($yearlyMatrix['totals']['months'][$m], 2, ',', '.') }}
                                        </td>
                                    @endforeach
                                    <td class="p-4 text-gray-900 dark:text-white font-mono font-black text-sm bg-gray-200/60 dark:bg-gray-950 border-l border-gray-300 dark:border-gray-800">
                                        {{ number_format($yearlyMatrix['totals']['year_sum'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ==================== SEKTION 5: DETAIL-CHARTS (CLEAN DESIGN) ==================== --}}
            <div x-data="accountingDashboard()"
                 id="analytics-data-bridge"
                 data-invoices='@json($invoiceData)'
                 data-special='@json($specialIssueData)'
                 data-costs='@json($costItemData)'
                 data-groups='@json($groupData)'
                 x-init="initCharts()"
                 @analytics-updated.window="updateCharts()"
                 class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div class="bg-white dark:bg-gray-900/80 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-bold flex items-center gap-2">
                            <x-heroicon-o-chart-bar class="w-5 h-5 text-emerald-500" />
                            <span>Erlösentwicklung (Rechnungen)</span>
                        </h3>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                              title="Bezahlte Rechnungen ({{ $isNet ? 'Netto' : 'Brutto' }}) im gewählten Zeitraum.">!</span>
                    </div>
                    <div class="relative h-60 w-full mt-4" wire:ignore>
                        <canvas id="invoiceChart"></canvas>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900/80 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-bold flex items-center gap-2">
                            <x-heroicon-o-receipt-percent class="w-5 h-5 text-rose-500" />
                            <span>Sonderausgaben-Verlauf</span>
                        </h3>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                              title="Monatlicher Verlauf der erfassten Sonderausgaben ({{ $isNet ? 'Netto' : 'Brutto' }}).">!</span>
                    </div>
                    <div class="relative h-60 w-full mt-4" wire:ignore>
                        <canvas id="specialChart"></canvas>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900/80 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-bold flex items-center gap-2">
                            <x-heroicon-o-building-office class="w-5 h-5 text-[var(--theme-color)]" />
                            <span>Monatliche Fixkosten-Blöcke</span>
                        </h3>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                              title="Auf einen Monat normierte Fixkosten nach Gruppen.">!</span>
                    </div>
                    <div class="relative h-60 w-full mt-4" wire:ignore>
                        <canvas id="costChart"></canvas>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900/80 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-bold flex items-center gap-2">
                            <x-heroicon-o-chart-pie class="w-5 h-5 text-[var(--theme-color)]" />
                            <span>Gruppen-Verteilung</span>
                        </h3>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] font-black text-gray-500 dark:text-gray-300 border border-gray-200 dark:border-gray-700 cursor-help"
                              title="Prozentuale Verteilung der laufenden Fixkosten-Gruppen.">!</span>
                    </div>
                    <div class="relative h-60 w-full mt-4 flex items-center justify-center" wire:ignore>
                        <canvas id="groupChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== MODAL 1: AUSZAHLUNGS- & RÜCKLAGEN-PARAMETER ==================== --}}
        @if($showPayoutConfigModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                 wire:click.self="$set('showPayoutConfigModal', false)">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl w-full max-w-lg p-6 sm:p-7 space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Parameter: Gehalt & Puffer</h3>
                        <button type="button" wire:click="$set('showPayoutConfigModal', false)" class="p-1.5 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="flex items-center gap-1.5 text-xs font-bold text-gray-700 dark:text-gray-300">
                                <span>Kontopuffer FYRST (€)</span>
                                <span class="text-[10px] font-black text-gray-400 cursor-help" title="Mindestbestand auf dem Geschäftskonto, der vor Privatentnahmen geschützt bleibt.">!</span>
                            </label>
                            <input type="number" step="50" min="0" wire:model="configSafetyBuffer"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[var(--theme-color-40)]">
                        </div>

                        <div class="space-y-1.5">
                            <label class="flex items-center gap-1.5 text-xs font-bold text-gray-700 dark:text-gray-300">
                                <span>Lebenshaltung Privat (€)</span>
                                <span class="text-[10px] font-black text-gray-400 cursor-help" title="Monatliche Pauschale für Lebensmittel, Tanken und Alltag zusätzlich zu den festen Privatverträgen.">!</span>
                            </label>
                            <input type="number" step="50" min="0" wire:model="configPrivateLivingAllowance"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[var(--theme-color-40)]">
                        </div>
                    </div>

                    <div class="space-y-3 pt-2">
                        <label class="flex items-center justify-between p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 cursor-pointer">
                            <span class="text-xs font-bold text-gray-800 dark:text-gray-200">AfA bestehender Großgeräte als Erhaltungsrücklage reservieren</span>
                            <input type="checkbox" wire:model="configReserveAfaReplacement" class="w-4 h-4 accent-[var(--theme-color)] rounded">
                        </label>

                        <label class="flex items-center justify-between p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 cursor-pointer">
                            <span class="text-xs font-bold text-gray-800 dark:text-gray-200">Jährliche Gewerbe-Fixkosten auf 12 Monate glätten</span>
                            <input type="checkbox" wire:model="configSmoothAnnualFixCosts" class="w-4 h-4 accent-[var(--theme-color)] rounded">
                        </label>
                    </div>

                    <div class="flex justify-end gap-2.5 pt-3 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="$set('showPayoutConfigModal', false)"
                                class="px-4 py-2.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="button" wire:click="savePayoutSettings"
                                class="px-5 py-2.5 rounded-xl text-xs font-bold bg-[var(--theme-color)] text-white hover:opacity-90 cursor-pointer">
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- ==================== MODAL 2: INVESTITIONSZIEL ANLEGEN / BEARBEITEN ==================== --}}
        @if($showPlanModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm overflow-y-auto"
                 wire:click.self="$set('showPlanModal', false)">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl w-full max-w-xl p-6 sm:p-7 space-y-5 my-8">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white">
                            {{ $editingPlanId ? 'Investitionsziel bearbeiten' : 'Neues Investitionsziel' }}
                        </h3>
                        <button type="button" wire:click="$set('showPlanModal', false)" class="p-1.5 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Bezeichnung</label>
                            <input type="text" wire:model="planTitle" placeholder="z. B. Zweit-Laser xTool F2 Ultra / MOPA"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[var(--theme-color-40)]">
                            @error('planTitle') <p class="text-xs text-rose-500 font-bold">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Zielbetrag Brutto (€)</label>
                                <input type="number" step="10" min="1" wire:model="planTargetGross"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[var(--theme-color-40)]">
                                @error('planTargetGross') <p class="text-xs text-rose-500 font-bold">{{ $message }}</p> @enderror
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Vorsteuer-Satz</label>
                                <select wire:model="planTaxRate"
                                        class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none cursor-pointer">
                                    <option value="19" class="bg-white dark:bg-gray-900">19 % (Standard)</option>
                                    <option value="7" class="bg-white dark:bg-gray-900">7 % (Ermäßigt)</option>
                                    <option value="0" class="bg-white dark:bg-gray-900">0 % (Steuerfrei)</option>
                                </select>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Startguthaben (€)</label>
                                <input type="number" step="10" min="0" wire:model="planSavedInitial"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[var(--theme-color-40)]">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Sparmodus</label>
                                <select wire:model.live="planAllocationMode"
                                        class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none cursor-pointer">
                                    <option value="fixed_monthly" class="bg-white dark:bg-gray-900">Fester Monatsbetrag (€)</option>
                                    <option value="profit_percent" class="bg-white dark:bg-gray-900">Dynamisch (% vom Überschuss)</option>
                                </select>
                            </div>

                            @if($planAllocationMode === 'profit_percent')
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Gewinn-Anteil (%)</label>
                                    <input type="number" step="0.5" min="0" max="100" wire:model="planProfitPercent"
                                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[var(--theme-color-40)]">
                                </div>
                            @else
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Monatliche Sparrate (€)</label>
                                    <input type="number" step="5" min="0" wire:model="planMonthlyRate"
                                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[var(--theme-color-40)]">
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Sparbeginn</label>
                                <input type="date" wire:model="planStartDate"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Wunschtermin</label>
                                <input type="date" wire:model="planTargetDate"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 dark:text-white outline-none">
                                @error('planTargetDate') <p class="text-xs text-rose-500 font-bold">{{ $message }}</p> @enderror
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300">AfA-Dauer (Jahre)</label>
                                <input type="number" min="0" max="20" wire:model.live="planAfaYears"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white outline-none"
                                       title="0 = Sofortaufwand / GWG, 5 = 5 Jahre lineare Abschreibung">
                            </div>
                        </div>

                        @if((int) $planAfaYears > 0)
                            <label class="flex items-center justify-between p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 cursor-pointer"
                                   title="Nach § 7g EStG können bis zu 50 % der geplanten Netto-Anschaffungskosten bis zu 3 Jahre vor dem Kauf gewinnmindernd abgezogen werden.">
                                <span class="text-xs font-bold text-gray-800 dark:text-gray-200">§ 7g EStG Investitionsabzugsbetrag (50 % Vorab-Abzug) einplanen</span>
                                <input type="checkbox" wire:model="planUseIab" class="w-4 h-4 accent-[var(--theme-color)] rounded">
                            </label>
                        @endif

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Notiz (optional)</label>
                            <textarea rows="2" wire:model="planNote" placeholder="Modell, Lieferant oder Einsatzzweck..."
                                      class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[var(--theme-color-40)]"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2.5 pt-3 border-t border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="$set('showPlanModal', false)"
                                class="px-4 py-2.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="button" wire:click="savePlan"
                                class="px-5 py-2.5 rounded-xl text-xs font-bold bg-[var(--theme-color)] text-white hover:opacity-90 cursor-pointer">
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @include('livewire.shop.accounting.accounting-analytics.partials.chart_scripts')
    </div>
</div>
