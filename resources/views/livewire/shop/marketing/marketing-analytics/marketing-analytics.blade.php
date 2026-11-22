<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80;">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8">

        {{-- Header & Controls --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-900/80 backdrop-blur-md p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-xl">
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl border border-[var(--theme-color-20)] bg-[var(--theme-color-10)] text-[var(--theme-color)]">
                    <x-heroicon-o-megaphone class="w-6 h-6" />
                </span>
                <h1 class="text-2xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    Marketing Analyse
                    <x-heroicon-o-information-circle
                        class="w-5 h-5 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors"
                        title="Kennzahlen zu bestätigten Newsletter-Abonnenten, Gutscheinen, Magazin-Beiträgen sowie Kampagnen-, Linktree- und Landingpage-Aufrufen." />
                </h1>
            </div>

            <div class="flex items-center gap-3">
                <label for="marketingDateRangeSelect" class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Zeitraum:</label>
                <select id="marketingDateRangeSelect" wire:model.live="dateRange" class="bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white text-sm rounded-xl focus:ring-2 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] block px-4 py-2 transition-colors cursor-pointer">
                    <option value="7">Letzte 7 Tage</option>
                    <option value="30">Letzte 30 Tage</option>
                    <option value="90">Letzte 90 Tage</option>
                    <option value="365">Letztes Jahr</option>
                    <option value="all">Gesamte Zeit</option>
                </select>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Aktive Abonnenten
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="DSGVO-bestätigte Newsletter-Abonnenten gesamt (+{{ $kpiNewSubscribers }} neu im gewählten Zeitraum)." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white flex items-baseline gap-2">
                        {{ number_format($kpiVerifiedSubscribers, 0, ',', '.') }}
                        @if($kpiNewSubscribers > 0)
                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">+{{ $kpiNewSubscribers }}</span>
                        @endif
                    </div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-envelope-open class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Aktive Gutscheine
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Aktuell gültige Aktions-Gutscheine und Geschenkgutscheine mit Restguthaben." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpiActiveVouchers, 0, ',', '.') }}</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400">
                    <x-heroicon-o-ticket class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Magazin-Beiträge
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Veröffentlichte SEO-/GEO-Blogbeiträge im Seelenfunke-Magazin." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpiPublishedPosts, 0, ',', '.') }}</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <x-heroicon-o-newspaper class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1.5">
                        Kampagnen-Aufrufe
                        <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Summe aller Landingpage-Aufrufe (/l/*), Linktree-Besuche (/links) und Tracking-Link-Klicks im gewählten Zeitraum." />
                    </p>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpiCampaignVisits, 0, ',', '.') }}</div>
                </div>
                <div class="w-12 h-12 rounded-xl bg-[var(--theme-color-10)] flex items-center justify-center text-[var(--theme-color)]">
                    <x-heroicon-o-cursor-arrow-rays class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- Charts Grid --}}
        <div x-data="marketingDashboard()" x-init="initCharts()" @analytics-updated.window="updateCharts()" @theme-changed.window="initCharts()" class="space-y-6">

            <div class="hidden" id="analytics-data-bridge"
                 data-theme-color="{{ $this->themeColorHex }}"
                 data-newsletter='@json($newsletterData)'
                 data-voucher='@json($voucherData)'
                 data-blog='@json($blogData)'
                 data-vouchertype='@json($voucherTypeData)'
                 data-landingpage='@json($landingPageData)'></div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Newsletter Growth --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-envelope-open class="w-5 h-5 text-emerald-500" />
                                Newsletter-Wachstum
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Verlauf neu gewonnener und Double-Opt-In-bestätigter Newsletter-Abonnenten." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="newsletterChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Voucher Generation --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-ticket class="w-5 h-5 text-rose-500" />
                                Gutschein-Generierung
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Im Zeitraum neu angelegte Aktions- und Geschenkgutscheine." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="voucherChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Blog Categories --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-newspaper class="w-5 h-5 text-blue-500" />
                                Magazin-Kategorien
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Verteilung der veröffentlichten Magazin-Beiträge nach Themenkategorie." />
                        </h3>
                        <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="blogChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Voucher Types --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-gift class="w-5 h-5 text-[var(--theme-color)]" />
                                Gutschein-Typen
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Aufschlüsselung nach Prozent-Rabatt, Festbetrag und gekauften Geschenkgutscheinen." />
                        </h3>
                        <div class="relative h-64 w-full mt-4 flex items-center justify-center" wire:ignore>
                            <canvas id="voucherTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Landing Pages Performance --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-globe-alt class="w-5 h-5 text-[var(--theme-color)]" />
                                Landingpage-Aufrufe (/l/*)
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Seitenaufrufe aller Kampagnen-Landingpages unter /l/* im Zeitverlauf." />
                        </h3>
                        <div class="relative h-64 w-full mt-4" wire:ignore>
                            <canvas id="landingPageChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-gray-900 dark:text-white text-base font-serif font-semibold flex items-center justify-between gap-2 mb-4">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-trophy class="w-5 h-5 text-[var(--theme-color)]" />
                                Top 5 Landingpages
                            </span>
                            <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-[var(--theme-color)] cursor-help transition-colors" title="Meistbesuchte Kampagnen-Landingpages im gewählten Zeitraum." />
                        </h3>

                        @if(count($topLandingPages) > 0)
                            <div class="space-y-2.5">
                                @foreach($topLandingPages as $idx => $top)
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-950/60 border border-gray-100 dark:border-gray-800">
                                        <div class="flex items-center gap-3 overflow-hidden">
                                            <span class="w-6 h-6 rounded-full bg-[var(--theme-color-10)] text-[var(--theme-color)] font-bold text-xs flex items-center justify-center shrink-0">
                                                {{ $idx + 1 }}
                                            </span>
                                            <div class="truncate">
                                                <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $top['title'] }}</div>
                                                <a href="{{ $top['path'] }}" target="_blank" rel="noopener" class="text-xs text-gray-500 hover:text-[var(--theme-color)] transition-colors truncate block">{{ $top['path'] }}</a>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0 ml-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                                                {{ number_format($top['views'], 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="h-48 flex flex-col items-center justify-center text-center text-gray-400 dark:text-gray-500">
                                <x-heroicon-o-chart-bar class="w-8 h-8 mb-2 opacity-50" />
                                <p class="text-xs font-medium">Keine Aufrufe im Zeitraum</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
