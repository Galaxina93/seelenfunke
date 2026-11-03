@php
    $rank = $this->currentCompanyRank;
    $journey = $this->journeyProgress;
    $ranks = $this->getCompanyRanks();
    $currentRev = $journey['current_revenue'];
    $trackPercent = $journey['track_percent'];

    $pins = [
        ['name' => 'Dachboden', 'label' => '0 €', 'pos' => 2, 'rev' => 0],
        ['name' => 'Fundament', 'label' => '2.5K', 'pos' => 10, 'rev' => 2500],
        ['name' => 'Garagenhof', 'label' => '5K', 'pos' => 18, 'rev' => 5000],
        ['name' => 'Atelier', 'label' => '10K', 'pos' => 26, 'rev' => 10000],
        ['name' => 'Werkstatt', 'label' => '15K', 'pos' => 34, 'rev' => 15000],
        ['name' => 'Packstation', 'label' => '20K', 'pos' => 42, 'rev' => 20000],
        ['name' => 'Studio', 'label' => '30K', 'pos' => 50, 'rev' => 30000],
        ['name' => 'Manufaktur', 'label' => '40K', 'pos' => 58, 'rev' => 40000, 'primary' => true],
        ['name' => 'Logistikhalle', 'label' => '55K', 'pos' => 66, 'rev' => 55000],
        ['name' => 'Fabrik', 'label' => '70K', 'pos' => 74, 'rev' => 70000, 'primary' => true],
        ['name' => 'Gewerbepark', 'label' => '100K', 'pos' => 82, 'rev' => 100000, 'primary' => true],
        ['name' => 'Firmensitz', 'label' => '500K', 'pos' => 91, 'rev' => 500000],
        ['name' => 'Imperium', 'label' => '1 Mio', 'pos' => 100, 'rev' => 1000000, 'mega' => true],
    ];

    $subGoals70k = [
        ['title' => 'Fundament', 'rev' => 2500, 'label' => '2.500 € / Mt.', 'sub' => '30K / Jahr • ~2–3 Best./Tag', 'desc' => '~30 Min./Tag am Laser • Fixkosten gedeckt'],
        ['title' => 'Garagenhof', 'rev' => 5000, 'label' => '5.000 € / Mt.', 'sub' => '60K / Jahr • ~4–5 Best./Tag', 'desc' => '~45 Min./Tag • ca. 2.500 € Gewinn/Mt.'],
        ['title' => 'Atelier', 'rev' => 10000, 'label' => '10.000 € / Mt.', 'sub' => '120K / Jahr • ~8–10 Best./Tag', 'desc' => '~1,5 Std./Tag • Fünfstellig mit 1 UV-Laser'],
        ['title' => 'Werkstatt', 'rev' => 15000, 'label' => '15.000 € / Mt.', 'sub' => '180K / Jahr • ~12–15 Best./Tag', 'desc' => '~2 Std./Tag Halbtags • ca. 7.500 € Gewinn/Mt.'],
        ['title' => 'Packstation', 'rev' => 20000, 'label' => '20.000 € / Mt.', 'sub' => '240K / Jahr • ~16–20 Best./Tag', 'desc' => '~2,5 Std./Tag • Jahr-1-Zielkorridor erreicht'],
        ['title' => 'Studio', 'rev' => 30000, 'label' => '30.000 € / Mt.', 'sub' => '360K / Jahr • ~25 Best./Tag', 'desc' => '~4 Std./Tag • Erste B2B-Conveyor-Aufträge'],
        ['title' => 'Manufaktur', 'rev' => 40000, 'label' => '40.000 € / Mt.', 'sub' => '480K / Jahr • ~35 Best./Tag', 'desc' => '~5 Std./Tag • ~18.500 € Gewinn/Mt. (1 Laser)', 'highlight' => true],
        ['title' => 'Logistikhalle', 'rev' => 55000, 'label' => '55.000 € / Mt.', 'sub' => '660K / Jahr • ~45 Best./Tag', 'desc' => 'Saison-Spitze mit 1 UV-Laser + 1 Packhilfe'],
        ['title' => 'Fabrik', 'rev' => 70000, 'label' => '70.000 € / Mt.', 'sub' => '840K / Jahr • Max. 1 Laser', 'desc' => 'Techn. Limit 1× F2 Ultra UV (~30.000 € Gewinn/Mt.)', 'highlight' => true],
    ];

    $visionGoals = [
        ['title' => 'Gewerbepark', 'rev' => 100000, 'label' => '100.000 € / Mt.', 'sub' => '1,2 Mio. € / Jahr (Jahr 2–3)', 'desc' => '~65 Best./Tag • 2 UV-Laser + 1–2 Packhilfen', 'highlight' => true],
        ['title' => 'Firmensitz', 'rev' => 500000, 'label' => '500.000 € / Mt.', 'sub' => '6,0 Mio. € / Jahr (Jahr 4–6)', 'desc' => '~300 Best./Tag • 4–6 Laser-Stationen & B2B-Führung'],
        ['title' => 'Imperium', 'rev' => 1000000, 'label' => '1.000.000 € / Mt.', 'sub' => '12,0 Mio. € / Jahr (Jahr 7–10)', 'desc' => '~600+ Best./Tag • DACH-Marktführer & Exit-Reife', 'mega' => true],
    ];

    $singleLaserStages = [
        [
            'stage'  => '1. Anlaufphase / Nebenbei',
            'sub'    => 'Organisch, TikTok/Reels, erste Ads',
            'orders' => '3 – 5 / Tag (~110 / Mt.)',
            'time'   => 'ca. 45 Min. / Tag',
            'rev'    => '4.000 € – 5.000 €',
            'profit' => 'ca. 1.900 € – 2.500 €',
        ],
        [
            'stage'  => '2. Solider Halbtags-Betrieb',
            'sub'    => 'Konstante Meta/Google Ads & SEO',
            'orders' => '12 – 15 / Tag (~400 / Mt.)',
            'time'   => 'ca. 2 – 2,5 Std. / Tag',
            'rev'    => '15.000 € – 18.000 €',
            'profit' => 'ca. 7.000 € – 8.800 €',
        ],
        [
            'stage'  => '3. Vollzeit / Saisongeschäft',
            'sub'    => 'Weihnachten, Muttertag, Valentinstag',
            'orders' => '30 – 45 / Tag (~1.050 / Mt.)',
            'time'   => 'ca. 5 – 6 Std. / Tag',
            'rev'    => '38.000 € – 48.000 €',
            'profit' => 'ca. 16.500 € – 22.000 €',
            'highlight' => true,
        ],
        [
            'stage'  => '4. Technisches Maximum (inkl. B2B)',
            'sub'    => 'Firmenaufträge über Conveyor-Band',
            'orders' => '50 – 70 Teile / Tag',
            'time'   => 'ca. 7 – 8 Std. / Tag',
            'rev'    => '60.000 € – 75.000 €',
            'profit' => 'ca. 26.000 € – 34.000 €',
            'highlight' => true,
        ],
    ];

    $tenYearRoadmap = [
        [
            'period' => 'Jahr 1 (2026/27)',
            'phase'  => 'Markteintritt & erstes Q4',
            'yearly' => '150.000 € – 300.000 €',
            'monthly'=> '12.500 € – 25.000 € / Mt.',
            'orders' => '10 – 20 / Tag',
            'setup'  => 'Du allein + 1× xTool F2 Ultra UV (Dachboden). Fokus auf Reels/TikTok, Google Shopping & erste B2B-Kunden.',
        ],
        [
            'period' => 'Jahr 2 – 3 (2028/29)',
            'phase'  => 'Die 1-Million-Marke knacken',
            'yearly' => '750.000 € – 1.200.000 €',
            'monthly'=> '62.500 € – 100.000 € / Mt.',
            'orders' => '45 – 70 / Tag',
            'setup'  => '2. UV-Laser (Parallel-Linie & Ausfallsicherheit im Garagenhof) + 1–2 Packhilfen (Minijob/Teilzeit) am Packtisch.',
            'highlight' => true,
        ],
        [
            'period' => 'Jahr 4 – 6 (2030–32)',
            'phase'  => 'DACH-Skalierung & B2B-Rahmenverträge',
            'yearly' => '2,5 Mio. € – 4,5 Mio. €',
            'monthly'=> '210.000 € – 375.000 € / Mt.',
            'orders' => '150 – 250 / Tag',
            'setup'  => 'Eigene Manufaktur-Fläche in Gifhorn, 4 Laser-Stationen, feste B2B-Verträge (Makler, Autohäuser, Hotels, Events), Ausbau AT & CH.',
        ],
        [
            'period' => 'Jahr 7 – 10 (2033–36)',
            'phase'  => 'Marktführer-Status & Exit-Reife',
            'yearly' => '5,0 Mio. € – 12,0+ Mio. €',
            'monthly'=> '420.000 € – 1.000.000 € / Mt.',
            'orders' => '300 – 600+ / Tag',
            'setup'  => 'Eigener Firmensitz, etablierte DACH-Geschenkemarke mit Creator-/Affiliate-Netzwerk und Millionen-Unternehmenswert.',
            'mega' => true,
        ],
    ];
@endphp

<div class="flex flex-col justify-between h-full space-y-6">

    <!-- Header (Aligned with "Finanzielle Gesundheit") -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-200 dark:border-gray-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-primary flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    10-Jahres Firmenreise & Umsatzplan
                </span>
            </div>
            <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span>Aktueller Status:</span>
                <span class="text-primary font-black uppercase tracking-wider">{{ $rank['title'] }}</span>
            </h2>
            <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md mt-1">
                "{{ $rank['motto'] }}"
            </p>
        </div>

        <!-- Right: Configurable Start Date & Timeline Info -->
        <div class="flex flex-col sm:items-end gap-1.5 shrink-0 bg-gray-50 dark:bg-gray-950 p-3 rounded-2xl border border-gray-200 dark:border-gray-800/80">
            <div class="flex items-center gap-2">
                <label for="journey_start_input" class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 cursor-pointer flex items-center gap-1">
                    <x-heroicon-m-calendar class="w-3.5 h-3.5 text-primary" /> Start:
                </label>
                <input id="journey_start_input"
                       type="date"
                       wire:model.live="journeyStartDate"
                       wire:change="saveJourneyStartDate"
                       class="bg-transparent border-none p-0 text-xs font-mono font-bold text-gray-800 dark:text-gray-200 focus:ring-0 cursor-pointer">
            </div>
            <div class="text-[10px] font-mono text-gray-500 flex items-center gap-1.5">
                <span>1 Mio. €/J. in Jahr 2–3</span>
                <span>•</span>
                <span class="text-primary font-bold">10J-Vision bis {{ $journey['end_date_formatted'] }}</span>
            </div>
        </div>
    </div>

    <!-- Faith Quote Banner (Compact & Inspiring) -->
    <div class="bg-[var(--theme-color-5)] border border-[var(--theme-color-20)] rounded-xl px-4 py-2.5 flex items-center gap-3">
        <div class="w-7 h-7 rounded-lg bg-[var(--theme-color-10)] text-[var(--theme-color)] flex items-center justify-center shrink-0">
            <x-heroicon-m-sparkles class="w-4 h-4" />
        </div>
        <p class="text-xs text-gray-700 dark:text-gray-300 italic font-medium leading-snug">
            {{ $rank['faith'] }}
        </p>
    </div>

    <!-- STYLISCHER LEITBALKEN -->
    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-5 lg:p-6 shadow-inner relative flex flex-col gap-6">

        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-800/80 pb-3">
            <div class="flex items-center gap-2.5">
                <x-heroicon-s-arrow-trending-up class="w-5 h-5 text-primary" />
                <h3 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">
                    Monatsumsatz-Achse (Bis 1 Mio. € / Monat = 12 Mio. € / Jahr)
                </h3>
            </div>
            <span class="text-[10px] font-mono font-bold text-gray-500">
                Live: <span class="text-emerald-500 font-black">{{ number_format($currentRev, 0, ',', '.') }} € / Mt.</span>
                <span class="text-gray-400">({{ number_format($currentRev * 12, 0, ',', '.') }} € / Jahr)</span>
            </span>
        </div>

        <!-- Der Leitbalken Track -->
        <div class="relative w-full pt-8 pb-10 px-2 sm:px-4 select-none">

            <!-- Floating Indicator Pin über dem Balken -->
            <div class="absolute z-30 transition-all duration-700 ease-out pointer-events-none"
                 style="left: clamp(35px, {{ $trackPercent }}%, calc(100% - 35px)); transform: translateX(-50%); top: 0px;">
                <div class="relative bg-gray-900 dark:bg-white text-white dark:text-gray-950 font-black font-mono text-[10px] sm:text-xs px-2 py-0.5 rounded-lg shadow-xl border border-gray-700 dark:border-white whitespace-nowrap flex items-center gap-1">
                    <span>{{ number_format($currentRev, 0, ',', '.') }} €</span>
                    <span class="text-[8px] opacity-75 font-sans uppercase">({{ $rank['title'] }})</span>
                    <div class="absolute w-2 h-2 bg-gray-900 dark:bg-white rotate-45 -bottom-1 left-1/2 -translate-x-1/2"></div>
                </div>
            </div>

            <!-- Balken Hintergrund -->
            <div class="w-full h-7 sm:h-8 bg-gray-200 dark:bg-black/60 rounded-full border border-gray-300 dark:border-gray-800 dark:shadow-inner relative overflow-visible">

                <!-- Gradient Füllbalken -->
                <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-700 ease-out flex items-center justify-end pr-2 overflow-hidden bg-gradient-to-r from-emerald-600 via-amber-400 to-amber-500 dark:shadow-[0_0_20px_rgba(245,158,11,0.5)]"
                     style="width: {{ $trackPercent }}%;">
                    <!-- Glanz-Effekt -->
                    <div class="absolute inset-0 bg-gradient-to-b from-white/25 to-transparent pointer-events-none"></div>
                </div>

                <!-- Meilenstein Pins entlang der Achse -->
                @foreach($pins as $pin)
                    @php
                        $isPassed = ($currentRev >= $pin['rev']);
                    @endphp
                    <div class="absolute top-0 h-full -ml-[1px] flex flex-col items-center pointer-events-none z-10"
                         style="left: {{ $pin['pos'] }}%;">
                        <!-- Vertikaler Markierungsstrich -->
                        <div class="w-[2px] h-full {{ $isPassed ? 'bg-white/90 shadow-sm' : 'bg-gray-400/60 dark:bg-gray-700' }}"></div>

                        <!-- Label unter dem Balken -->
                        <div class="absolute top-full mt-1.5 flex flex-col items-center whitespace-nowrap">
                            <span class="text-[8px] sm:text-[9px] font-black font-mono {{ $isPassed ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $pin['label'] }}
                            </span>
                            <span class="text-[7px] sm:text-[8px] font-bold uppercase tracking-wider hidden sm:block {{ isset($pin['mega']) ? 'text-amber-500 font-black' : (isset($pin['primary']) ? 'text-primary' : 'text-gray-400 dark:text-gray-500') }}">
                                {{ $pin['name'] }}
                            </span>
                        </div>
                    </div>
                @endforeach

            </div>
        </div>

        <!-- TEILZIELE, SKALIERUNG & 10-JAHRES-UMSATZPLAN -->
        <div x-data="{ subTab: 'plan' }" class="flex flex-col gap-3 pt-2">

            <!-- Switcher: Umsatzplan vs Etappen bis 70K (1 Laser) vs Vision (1,2–12 Mio./J.) -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-800 pb-2.5">
                <div class="flex flex-wrap items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 text-[10px] font-bold uppercase tracking-wider">
                    <button type="button"
                            @click="subTab = 'plan'"
                            :class="subTab === 'plan' ? 'bg-white dark:bg-gray-800 text-primary shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer flex items-center gap-1">
                        <span>📈 Umsatzplan (1-Laser & 10J)</span>
                    </button>
                    <button type="button"
                            @click="subTab = '70k'"
                            :class="subTab === '70k' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                        1-Laser-Etappen bis 70K/Mt. ({{ count($subGoals70k) }})
                    </button>
                    <button type="button"
                            @click="subTab = 'vision'"
                            :class="subTab === 'vision' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                        Skalierung (1,2 – 12 Mio./J.)
                    </button>
                    <button type="button"
                            @click="subTab = 'all'"
                            :class="subTab === 'all' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                        Alle ({{ count($subGoals70k) + count($visionGoals) }})
                    </button>
                </div>

                <span class="text-[9px] font-mono text-gray-400 hidden sm:inline">
                    Ø Warenkorb (AOV): <span class="font-bold text-primary">~40–45 €</span> • <span class="font-bold text-emerald-500">~10–12 Best./Std.</span>
                </span>
            </div>

            <!-- Tab 0: Konkreter Umsatz- & Kapazitätsplan (1× xTool F2 Ultra UV & 10-Jahres-Roadmap) -->
            <div x-show="subTab === 'plan'" class="space-y-4">
                <!-- Teil A: Kapazität mit 1× xTool F2 Ultra UV -->
                <div class="bg-white/90 dark:bg-gray-900/70 rounded-2xl border border-gray-200 dark:border-gray-800 p-3.5 space-y-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-primary">Teil 1 • Maschinen- & Zeitkapazität (1× xTool F2 Ultra UV + Conveyor + RA3)</span>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white">Was du allein mit 1 Lasergerät pro Monat schaffst (~5–6 Min. Handarbeit pro Bestellung)</h4>
                        </div>
                        <span class="text-[9px] font-mono font-bold px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            Max. ~75.000 € / Monat mit 1 Gerät
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($singleLaserStages as $s)
                            <div class="p-2.5 rounded-xl border {{ !empty($s['highlight']) ? 'border-primary/40 bg-primary/5' : 'border-gray-200/80 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60' }} flex flex-col justify-between gap-1.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-[11px] font-black text-gray-900 dark:text-white">{{ $s['stage'] }}</p>
                                        <p class="text-[9px] text-gray-500">{{ $s['sub'] }}</p>
                                    </div>
                                    <span class="text-[10px] font-mono font-black text-primary whitespace-nowrap">{{ $s['rev'] }} / Mt.</span>
                                </div>
                                <div class="grid grid-cols-3 gap-1 pt-1.5 border-t border-gray-200/60 dark:border-gray-800/60 text-[9px] font-mono">
                                    <div>
                                        <span class="text-gray-400 block text-[8px] uppercase">Bestellungen</span>
                                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ $s['orders'] }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 block text-[8px] uppercase">Zeitaufwand</span>
                                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ $s['time'] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-gray-400 block text-[8px] uppercase">Gewinn (vor ESt.)</span>
                                        <span class="font-black text-emerald-600 dark:text-emerald-400">{{ $s['profit'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Teil B: 10-Jahres-Skalierung (1 Mio. €/Jahr bereits in Jahr 2–3 -> 10+ Mio. €/Jahr in Jahr 7–10) -->
                <div class="bg-white/90 dark:bg-gray-900/70 rounded-2xl border border-gray-200 dark:border-gray-800 p-3.5 space-y-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-amber-500">Teil 2 • Strategische 10-Jahres-Kurve (2026 – 2036)</span>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white">1 Mio. € Jahresumsatz bereits in Jahr 2–3 (~60 Best./Tag) • 10+ Mio. € Jahresumsatz bis Jahr 10</h4>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($tenYearRoadmap as $r)
                            <div class="p-2.5 rounded-xl border {{ !empty($r['mega']) ? 'border-amber-400/50 bg-amber-400/5' : (!empty($r['highlight']) ? 'border-emerald-500/40 bg-emerald-500/5' : 'border-gray-200/80 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60') }} flex flex-col justify-between gap-1.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-[9px] font-black uppercase tracking-wider {{ !empty($r['mega']) ? 'text-amber-500' : (!empty($r['highlight']) ? 'text-emerald-600 dark:text-emerald-400' : 'text-primary') }}">
                                            {{ $r['period'] }} • {{ $r['orders'] }}
                                        </span>
                                        <p class="text-[11px] font-black text-gray-900 dark:text-white">{{ $r['phase'] }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-[11px] font-mono font-black text-gray-900 dark:text-white block">{{ $r['yearly'] }} / J.</span>
                                        <span class="text-[8px] font-mono text-gray-500 block">{{ $r['monthly'] }}</span>
                                    </div>
                                </div>
                                <p class="text-[9px] text-gray-500 dark:text-gray-400 leading-snug pt-1 border-t border-gray-200/60 dark:border-gray-800/60">
                                    {{ $r['setup'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Tab 1: Etappen bis 70K (9 Teilziele mit 1× xTool F2 Ultra UV) -->
            <div x-show="subTab === '70k' || subTab === 'all'" class="space-y-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    @foreach($subGoals70k as $km)
                        @php
                            $isAchieved = $currentRev >= $km['rev'];
                            $diff = max(0, $km['rev'] - $currentRev);
                            $mPercent = min(100, round(($currentRev / $km['rev']) * 100, 1));
                        @endphp
                        <div class="bg-white/80 dark:bg-gray-900/60 p-3 rounded-2xl border transition-all {{ $isAchieved ? 'border-emerald-500/40 bg-emerald-50/20 dark:bg-emerald-950/20 shadow-sm' : (isset($km['highlight']) ? 'border-primary/40 bg-primary/5' : 'border-gray-200 dark:border-gray-800') }} flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <span class="text-[9px] font-bold uppercase tracking-wider {{ $isAchieved ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }} truncate">
                                        {{ $km['sub'] }}
                                    </span>
                                    @if($isAchieved)
                                        <span class="text-[9px] text-emerald-600 dark:text-emerald-400 font-black flex items-center gap-0.5">
                                            <x-heroicon-s-check-circle class="w-3 h-3" /> Erreicht
                                        </span>
                                    @else
                                        <span class="text-[9px] font-mono font-bold text-gray-500 dark:text-gray-400">{{ $mPercent }}%</span>
                                    @endif
                                </div>

                                <div class="flex items-baseline justify-between gap-1">
                                    <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider">{{ $km['title'] }}</h4>
                                    <span class="text-[10px] font-mono font-bold text-gray-700 dark:text-gray-300">{{ $km['label'] }}</span>
                                </div>

                                @if(!empty($km['desc']))
                                    <p class="text-[9px] text-gray-400 dark:text-gray-500 line-clamp-1 mt-0.5">{{ $km['desc'] }}</p>
                                @endif
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-800/60">
                                @if($isAchieved)
                                    <div class="w-full bg-emerald-500 h-1.5 rounded-full"></div>
                                @else
                                    <div class="w-full bg-gray-200 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden mb-1">
                                        <div class="bg-primary h-full rounded-full transition-all duration-500" style="width: {{ $mPercent }}%;"></div>
                                    </div>
                                    <div class="flex justify-between items-center text-[8px] font-mono text-gray-400">
                                        <span>Noch nötig:</span>
                                        <span class="font-bold text-gray-600 dark:text-gray-300">-{{ number_format($diff, 0, ',', '.') }} €</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Tab 2: Skalierung & Vision (1,2 Mio. € bis 12 Mio. € Jahresumsatz) -->
            <div x-show="subTab === 'vision' || subTab === 'all'" class="space-y-2">
                <div x-show="subTab === 'all'" class="pt-2 border-t border-gray-200 dark:border-gray-800">
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary">Skalierung & 10-Jahres-Vision (1,2 Mio. € bis 12 Mio. € Jahresumsatz)</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    @foreach($visionGoals as $km)
                        @php
                            $isAchieved = $currentRev >= $km['rev'];
                            $diff = max(0, $km['rev'] - $currentRev);
                            $mPercent = min(100, round(($currentRev / $km['rev']) * 100, 1));
                        @endphp
                        <div class="bg-white/80 dark:bg-gray-900/60 p-3 rounded-2xl border transition-all {{ $isAchieved ? 'border-emerald-500/40 bg-emerald-50/20 dark:bg-emerald-950/20 shadow-sm' : (isset($km['mega']) ? 'border-amber-400/50 bg-amber-400/5' : 'border-purple-500/30 bg-purple-500/5') }} flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <span class="text-[9px] font-bold uppercase tracking-wider {{ $isAchieved ? 'text-emerald-600 dark:text-emerald-400' : (isset($km['mega']) ? 'text-amber-500' : 'text-purple-500') }} truncate">
                                        {{ $km['sub'] }}
                                    </span>
                                    @if($isAchieved)
                                        <span class="text-[9px] text-emerald-600 dark:text-emerald-400 font-black flex items-center gap-0.5">
                                            <x-heroicon-s-check-circle class="w-3 h-3" /> Erreicht
                                        </span>
                                    @else
                                        <span class="text-[9px] font-mono font-bold text-gray-500 dark:text-gray-400">{{ $mPercent }}%</span>
                                    @endif
                                </div>

                                <div class="flex items-baseline justify-between gap-1">
                                    <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-wider">{{ $km['title'] }}</h4>
                                    <span class="text-[10px] font-mono font-bold text-gray-700 dark:text-gray-300">{{ $km['label'] }}</span>
                                </div>

                                @if(!empty($km['desc']))
                                    <p class="text-[9px] text-gray-400 dark:text-gray-500 line-clamp-1 mt-0.5">{{ $km['desc'] }}</p>
                                @endif
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-800/60">
                                @if($isAchieved)
                                    <div class="w-full bg-emerald-500 h-1.5 rounded-full"></div>
                                @else
                                    <div class="w-full bg-gray-200 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden mb-1">
                                        <div class="{{ isset($km['mega']) ? 'bg-amber-400' : 'bg-purple-500' }} h-full rounded-full transition-all duration-500" style="width: {{ $mPercent }}%;"></div>
                                    </div>
                                    <div class="flex justify-between items-center text-[8px] font-mono text-gray-400">
                                        <span>Noch nötig:</span>
                                        <span class="font-bold text-gray-600 dark:text-gray-300">-{{ number_format($diff, 0, ',', '.') }} €</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

    </div>

</div>
