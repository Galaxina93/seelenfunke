<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80;">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8">

        {{-- Header & Controls --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-900/80 backdrop-blur-md p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-xl">
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl border border-[var(--theme-color-20)] bg-[var(--theme-color-10)] text-[var(--theme-color)]">
                    <x-heroicon-o-shopping-bag class="w-6 h-6" />
                </span>
                <h1 class="text-2xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    Bestell-Analyse & Metriken
                    <x-heroicon-o-information-circle
                        class="w-5 h-5 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors"
                        title="Auswertung bezahlter Bestellungen, Wiederkaufraten, B2B-Quoten, Versand-Durchlaufzeiten, Bestseller sowie Angebots- und Widerrufsverläufe." />
                </h1>
            </div>

            <div class="flex items-center gap-3">
                <label for="orderDateRangeSelect" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Zeitraum:</label>
                <select id="orderDateRangeSelect" wire:model.live="dateRange" class="bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white text-sm rounded-xl focus:ring-2 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] block px-4 py-2 transition-colors cursor-pointer">
                    <option value="7">Letzte 7 Tage</option>
                    <option value="30">Letzte 30 Tage</option>
                    <option value="90">Letzte 90 Tage</option>
                    <option value="365">Letztes Jahr</option>
                    <option value="all">Gesamte Zeit</option>
                </select>
            </div>
        </div>

        {{-- Headline KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Bezahlte Bestellungen
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Anzahl erfolgreich bezahlter, nicht stornierter Bestellungen im gewählten Zeitraum." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpiPaidOrdersCount, 0, ',', '.') }}</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-check-badge class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Brutto-Umsatz
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Gesamter Brutto-Bestellwert bezahlter, aktiver Bestellungen im Zeitraum." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpiGrossRevenueEuro, 2, ',', '.') }} €</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                    <x-heroicon-o-banknotes class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Ø Warenkorbwert
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Durchschnittlicher Brutto-Bestellwert (AOV) je bezahlter Bestellung." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpiAvgOrderValueEuro, 2, ',', '.') }} €</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <x-heroicon-o-shopping-cart class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Storno & Widerruf
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Anteil stornierter, erstatteter oder widerrufener Bestellungen im Zeitraum." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpiCancelRevokeRate, 1, ',', '.') }} %</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400">
                    <x-heroicon-o-arrow-uturn-left class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- Charts Grid --}}
        <div x-data="orderAnalyticsDashboard()" x-init="initCharts()" @analytics-updated.window="updateCharts()" @theme-changed.window="initCharts()" class="space-y-6">

            <div class="hidden" id="analytics-data-bridge"
                 data-theme-color="{{ $this->themeColorHex }}"
                 data-b2b='@json($b2bChartData)'
                 data-processing='@json($processingTimeData)'
                 data-peak='@json($peakTimesData)'
                 data-retention='@json($retentionData)'
                 data-cancellation='@json($cancellationData)'
                 data-bestseller='@json($bestsellerData)'
                 data-weekday='@json($weekdayData)'
                 data-quotes='@json($quotesChartData)'
                 data-revocations='@json($revocationsChartData)'></div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                {{-- 1. Retention Rate --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-arrow-path class="w-5 h-5 text-emerald-500" />
                                Wiederkaufrate
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Verhältnis wiederkehrender Bestandskunden zu Erstbestellern auf Basis bezahlter Bestellungen." />
                        </h3>
                        <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="retentionChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- 2. B2B vs B2C --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-building-office class="w-5 h-5 text-blue-500" />
                                B2B vs. B2C Anteil
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Aufteilung bezahlter Bestellungen nach Privatkunden (B2C) und Firmenkunden mit Rechnungsfirma (B2B)." />
                        </h3>
                        <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="b2bChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- 3. Cancellation Drain --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-x-circle class="w-5 h-5 text-rose-500" />
                                Storno- & Abbruch-Volumen (€)
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Entgangenes Bestellvolumen durch stornierte, erstattete oder fehlgeschlagene Zahlungen in Euro." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="cancellationChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- 4. Bestsellers --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm md:col-span-2 flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-star class="w-5 h-5 text-[var(--theme-color)]" />
                                Top 5 Bestseller
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Meistverkaufte Produkte nach Stückzahl in bezahlten Bestellungen." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="bestsellerChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- 5. Processing Time --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-truck class="w-5 h-5 text-cyan-500" />
                                Ø Versand-Durchlaufzeit (Std.)
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Durchschnittliche Dauer in Stunden vom Bestelleingang bis zur Erstellung der Versandmarke bzw. Versandfreigabe." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="processingChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- 6. Peak Times --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm md:col-span-2 flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-clock class="w-5 h-5 text-blue-500" />
                                Bestell-Stoßzeiten (00:00 bis 23:00 Uhr)
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Tageszeitliche Verteilung bezahlter Bestellungen zur Planung von Support- und Kampagnenzeiten." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="peakChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- 7. Weekday Volume --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-calendar-days class="w-5 h-5 text-[var(--theme-color)]" />
                                Bestellvolumen nach Wochentag
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Verteilung der bezahlten Bestellungen auf die Wochentage zur Steuerung der Laser-Fertigung." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="weekdayChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- 8. Quotes Timeline --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm md:col-span-2 flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-5 h-5 text-indigo-500" />
                                Angebotsanfragen
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Eingegangene B2B- und Großmengen-Angebotsanfragen im Zeitverlauf." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="quotesChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- 9. Revocations Timeline --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-archive-box-x-mark class="w-5 h-5 text-red-500" />
                                Widerrufe
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Eingegangene Widerrufsformulare im gewählten Zeitraum." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="revocationsChart"></canvas>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>
