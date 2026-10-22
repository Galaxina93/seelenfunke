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
        ['title' => 'Fundament', 'rev' => 2500, 'label' => '2.500 € / Mt.', 'sub' => 'Basiskosten', 'desc' => 'Kostendeckung & fester Boden'],
        ['title' => 'Garagenhof', 'rev' => 5000, 'label' => '5.000 € / Mt.', 'sub' => 'Auslagerung', 'desc' => 'Erste externe Lagerfläche'],
        ['title' => 'Atelier', 'rev' => 10000, 'label' => '10.000 € / Mt.', 'sub' => 'Fünfstellig', 'desc' => 'Handwerkliche Spitzenklasse'],
        ['title' => 'Werkstatt', 'rev' => 15000, 'label' => '15.000 € / Mt.', 'sub' => 'Strukturen', 'desc' => 'Feste Prozesse & Maschinen'],
        ['title' => 'Packstation', 'rev' => 20000, 'label' => '20.000 € / Mt.', 'sub' => 'Versand-Hub', 'desc' => 'Täglicher Warendurchsatz'],
        ['title' => 'Studio', 'rev' => 30000, 'label' => '30.000 € / Mt.', 'sub' => 'Marken-Ausbau', 'desc' => 'Präsenz & Skalierung'],
        ['title' => 'Manufaktur', 'rev' => 40000, 'label' => '40.000 € / Mt.', 'sub' => '1. Meilenstein', 'desc' => 'Premium-Manufaktur etabliert', 'highlight' => true],
        ['title' => 'Logistikhalle', 'rev' => 55000, 'label' => '55.000 € / Mt.', 'sub' => 'Großlager', 'desc' => 'Paletten & Fördertechnik'],
        ['title' => 'Fabrik', 'rev' => 70000, 'label' => '70.000 € / Mt.', 'sub' => '2. Meilenstein', 'desc' => 'Automatisierte Serienfertigung', 'highlight' => true],
    ];

    $visionGoals = [
        ['title' => 'Gewerbepark', 'rev' => 100000, 'label' => '100.000 € / Mt.', 'sub' => 'Sechsstellig', 'desc' => 'Champions League Monatsumsatz', 'highlight' => true],
        ['title' => 'Firmensitz', 'rev' => 500000, 'label' => '500.000 € / Mt.', 'sub' => 'Campus', 'desc' => 'Eigener millionenschwerer Firmensitz'],
        ['title' => 'Imperium', 'rev' => 1000000, 'label' => '1.000.000 € / Mt.', 'sub' => 'Mega-Ziel', 'desc' => 'Vollbrachte 10-Jahres Vision', 'mega' => true],
    ];
@endphp

<div class="flex flex-col justify-between h-full space-y-6">

    <!-- Header (Aligned with "Finanzielle Gesundheit") -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-200 dark:border-gray-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] font-black uppercase tracking-widest text-primary flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    10-Jahres Firmenreise
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
                <span>Planung: 10 Jahre</span>
                <span>•</span>
                <span class="text-primary font-bold">bis {{ $journey['end_date_formatted'] }}</span>
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

    <!-- STYLISCHER LEITBALKEN (Inspiriert aus dem Kapazitäten-Balken, ohne Schieberegler) -->
    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-5 lg:p-6 shadow-inner relative flex flex-col gap-6">

        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800/80 pb-3">
            <div class="flex items-center gap-2.5">
                <x-heroicon-s-arrow-trending-up class="w-5 h-5 text-primary" />
                <h3 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">
                    Umsatz-Reise (Dachboden bis 1 Mio €)
                </h3>
            </div>
            <span class="text-[10px] font-mono font-bold text-gray-500">
                Live: <span class="text-emerald-500 font-black">{{ number_format($currentRev, 0, ',', '.') }} € / Mt.</span>
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

        <!-- TEILZIELE BIS 70K & WEITERE MEILENSTEINE -->
        <div x-data="{ subTab: '70k' }" class="flex flex-col gap-3 pt-2">

            <!-- Switcher: Etappen bis 70K vs Vision -->
            <div class="flex items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-800 pb-2.5">
                <div class="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 text-[10px] font-bold uppercase tracking-wider">
                    <button type="button"
                            @click="subTab = '70k'"
                            :class="subTab === '70k' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                        Etappen bis 70K ({{ count($subGoals70k) }})
                    </button>
                    <button type="button"
                            @click="subTab = 'vision'"
                            :class="subTab === 'vision' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                        Vision (100K – 1 Mio)
                    </button>
                    <button type="button"
                            @click="subTab = 'all'"
                            :class="subTab === 'all' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                        Alle ({{ count($subGoals70k) + count($visionGoals) }})
                    </button>
                </div>

                <span class="text-[9px] font-mono text-gray-400 hidden sm:inline">
                    Live: <span class="font-bold text-emerald-500">{{ number_format($currentRev, 0, ',', '.') }} € / Mt.</span>
                </span>
            </div>

            <!-- Tab 1: Etappen bis 70K (9 Teilziele) -->
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

            <!-- Tab 2: Vision 100K bis 1 Mio (3 Meilensteine) -->
            <div x-show="subTab === 'vision' || subTab === 'all'" class="space-y-2">
                @if(true)
                    <div x-show="subTab === 'all'" class="pt-2 border-t border-gray-200 dark:border-gray-800">
                        <span class="text-[10px] font-black uppercase tracking-widest text-primary">10-Jahres Vision (100K – 1 Mio)</span>
                    </div>
                @endif
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