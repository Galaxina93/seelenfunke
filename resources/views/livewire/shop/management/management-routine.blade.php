<div x-data @routine-updated.window="$wire.$refresh()" style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;" class="space-y-6 w-full max-w-[calc(100vw-2rem)] lg:max-w-[calc(100vw-22rem)] min-w-0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    @php
        $typeDotColors = [
            'hygiene' => 'bg-purple-600 dark:bg-purple-400',
            'sport'   => 'bg-rose-600 dark:bg-rose-400',
            'work'    => 'bg-blue-600 dark:bg-blue-400',
            'food'    => 'bg-emerald-600 dark:bg-emerald-400',
            'break'   => 'bg-amber-500 dark:bg-amber-400',
            'sleep'   => 'bg-indigo-600 dark:bg-indigo-400',
            'general' => 'bg-gray-700 dark:bg-gray-300',
        ];
        // Tailwind JIT safelist classes used by PHP controller arrays:
        // bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/80 dark:text-sky-200 dark:border-sky-800 bg-sky-600 dark:bg-sky-500 text-sky-700 dark:text-sky-400
        // bg-red-100 text-red-800 border-red-300 dark:bg-red-950/80 dark:text-red-200 dark:border-red-800 bg-red-600 dark:bg-red-500 text-red-700 dark:text-red-400
        // bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-950/80 dark:text-orange-200 dark:border-orange-800 bg-orange-600 dark:bg-orange-500 text-orange-700 dark:text-orange-400
        // bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950/80 dark:text-amber-200 dark:border-amber-800 bg-amber-500 dark:bg-amber-400 text-amber-700 dark:text-amber-400
        // bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/80 dark:text-emerald-200 dark:border-emerald-800 bg-emerald-600 dark:bg-emerald-500 text-emerald-700 dark:text-emerald-400
        // bg-purple-600 dark:bg-purple-500 text-purple-700 dark:text-purple-300
        // bg-rose-600 dark:bg-rose-500 text-rose-700 dark:text-rose-300
        // bg-blue-600 dark:bg-blue-500 text-blue-700 dark:text-blue-300
        // bg-indigo-600 dark:bg-indigo-500 text-indigo-700 dark:text-indigo-300
        // bg-gray-600 dark:bg-gray-400 text-gray-700 dark:text-gray-300
    @endphp

    {{-- TOP CONTROL & NAVIGATION BAR (Matte & Mobiloptimiert) --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 bg-white dark:bg-gray-900 rounded-2xl sm:rounded-3xl border border-gray-200 dark:border-gray-800 p-3 sm:px-5 sm:py-3.5 shadow-sm">
        <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-1 lg:pb-0">
            <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-semibold shrink-0">
                <button wire:click="$set('viewMode', 'combined')" class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $viewMode === 'combined' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    <x-heroicon-m-squares-2x2 class="w-4 h-4 shrink-0" />
                    <span>Gesamt</span>
                </button>
                <button wire:click="$set('viewMode', 'tracker')" class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $viewMode === 'tracker' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    <x-heroicon-m-chart-bar-square class="w-4 h-4 shrink-0" />
                    <span>Matrix & Charts</span>
                </button>
                <button wire:click="$set('viewMode', 'routine')" class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $viewMode === 'routine' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    <x-heroicon-m-clock class="w-4 h-4 shrink-0" />
                    <span>Tagesroutine</span>
                </button>
            </div>

            <div class="hidden xl:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold shrink-0">
                <span>🌴</span>
                <span>Mo – Fr Fokus · Sa & So frei</span>
            </div>
        </div>

        {{-- Zeitraum-Umschalter (Woche / Monat / Jahr) + Navigation --}}
        <div class="flex items-center justify-between sm:justify-end gap-2 flex-wrap">
            <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-semibold">
                <button wire:click="setEvalPeriod('week')" class="px-2.5 sm:px-3 py-1.5 rounded-lg transition-colors {{ $evalPeriod === 'week' ? 'bg-emerald-600 text-white font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">Woche</button>
                <button wire:click="setEvalPeriod('month')" class="px-2.5 sm:px-3 py-1.5 rounded-lg transition-colors {{ $evalPeriod === 'month' ? 'bg-emerald-600 text-white font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">Monat</button>
                <button wire:click="setEvalPeriod('year')" class="px-2.5 sm:px-3 py-1.5 rounded-lg transition-colors {{ $evalPeriod === 'year' ? 'bg-emerald-600 text-white font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">Jahr</button>
            </div>

            <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
                <button wire:click="prevPeriod" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/70 dark:hover:bg-gray-800 transition-colors" title="Vorheriger Zeitraum">
                    <x-heroicon-m-chevron-left class="w-4 h-4" />
                </button>
                <button wire:click="resetPeriod" class="px-2 sm:px-3 py-1 text-[11px] font-bold text-gray-800 dark:text-gray-200 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors whitespace-nowrap" title="Zum aktuellen Zeitraum springen">
                    {{ $periodLabel }}
                </button>
                <button wire:click="nextPeriod" @disabled($periodOffset >= 0) class="p-1.5 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/70 dark:hover:bg-gray-800 transition-colors disabled:opacity-30 disabled:pointer-events-none" title="Nächster Zeitraum">
                    <x-heroicon-m-chevron-right class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>

    @if($viewMode !== 'routine')
        {{-- ===================================================================== --}}
        {{-- 1. FUNKI BEWERTUNGSSYSTEM & SYSTEM-COACH (MATT, CLEAN, MOBILOPTIMIERT) --}}
        {{-- ===================================================================== --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
            {{-- Funki Evaluation Card --}}
            <div class="xl:col-span-7 bg-white dark:bg-gray-900 rounded-2xl sm:rounded-3xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 lg:p-7 shadow-sm flex flex-col justify-between">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5">
                    {{-- Funki Character Image (Sauber eingefasst, kein überlappender Text!) --}}
                    <div class="w-36 h-36 sm:w-40 sm:h-40 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center p-2.5 shrink-0">
                        <img src="{{ $funkiEvaluation['image'] }}" alt="Funki Status {{ $funkiEvaluation['speech'] }}" class="w-full h-full object-contain">
                    </div>

                    {{-- Score Details & 4-Tier Scale --}}
                    <div class="flex-1 min-w-0 w-full space-y-3.5 text-center sm:text-left">
                        {{-- Top Action & Badge Row --}}
                        <div class="flex flex-wrap items-center justify-center sm:justify-between gap-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold border {{ $funkiEvaluation['badge_bg'] }}">
                                {{ $funkiEvaluation['range'] }}
                            </span>

                            <div class="flex items-center gap-1.5 flex-wrap justify-center">
                                <button
                                    wire:click="toggleSickDay('{{ $selectedDate }}')"
                                    class="text-[11px] font-semibold px-2.5 py-1 rounded-lg border transition-colors flex items-center gap-1
                                    {{ $isSelectedDateSick
                                        ? 'bg-sky-600 text-white border-sky-600 dark:bg-sky-500 dark:text-gray-950 dark:border-sky-500 font-bold'
                                        : 'bg-gray-100 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800' }}"
                                    title="Tag ({{ \Carbon\Carbon::parse($selectedDate)->format('d.m.Y') }}) als Krankheitstag markieren oder aufheben">
                                    <span>{{ $isSelectedDateSick ? '🤒 Krank (' . \Carbon\Carbon::parse($selectedDate)->format('d.m.') . ') aktiv' : '🤒 Krank (' . \Carbon\Carbon::parse($selectedDate)->format('d.m.') . ')' }}</span>
                                </button>
                                <button wire:click="toggleScoreMode" class="text-[11px] font-semibold px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors" title="Zwischen fälligkeitsbasierter Fair-Bewertung und strenger Gesamtzeitraum-Bewertung wechseln">
                                    {{ $scoreMode === 'smart' ? '⚡ Fair-Score' : '🎯 Streng' }}
                                </button>
                            </div>
                        </div>

                        {{-- Title + Big Percentage --}}
                        <div class="flex flex-col sm:flex-row sm:items-baseline justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white leading-snug">
                                    {{ $funkiEvaluation['title'] }}
                                </h3>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                                    {{ $funkiEvaluation['subtitle'] }}
                                </p>
                            </div>
                            <div class="shrink-0 sm:text-right pt-1 sm:pt-0">
                                <div class="text-3xl sm:text-4xl font-black tracking-tight {{ $funkiEvaluation['text_color'] }}">
                                    {{ $overallStats['evaluation_pct'] }}%
                                </div>
                                <div class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ $overallStats['completed_slots'] }}/{{ $scoreMode === 'smart' && $overallStats['due_slots'] > 0 ? $overallStats['due_slots'] : $overallStats['target_slots'] }} erledigt
                                </div>
                            </div>
                        </div>

                        {{-- Matte Fortschrittsleiste & 4 Stufen --}}
                        <div class="space-y-2 pt-0.5">
                            <div class="h-2.5 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="h-full rounded-full {{ $funkiEvaluation['bar_color'] }} transition-all duration-500" style="width: {{ max(2, $overallStats['evaluation_pct']) }}%"></div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-[10px] font-bold text-center">
                                <div class="py-1 px-2 rounded-lg border {{ $funkiEvaluation['tier'] === '0_25' ? 'bg-red-100 border-red-300 text-red-800 dark:bg-red-950/70 dark:border-red-800 dark:text-red-200' : 'bg-gray-50 dark:bg-gray-950 border-gray-200/70 dark:border-gray-800 text-gray-500 dark:text-gray-400' }}">
                                    0–25% Loser
                                </div>
                                <div class="py-1 px-2 rounded-lg border {{ $funkiEvaluation['tier'] === '25_50' ? 'bg-orange-100 border-orange-300 text-orange-800 dark:bg-orange-950/70 dark:border-orange-800 dark:text-orange-200' : 'bg-gray-50 dark:bg-gray-950 border-gray-200/70 dark:border-gray-800 text-gray-500 dark:text-gray-400' }}">
                                    26–50% Ausreden
                                </div>
                                <div class="py-1 px-2 rounded-lg border {{ $funkiEvaluation['tier'] === '50_75' ? 'bg-amber-100 border-amber-300 text-amber-900 dark:bg-amber-950/70 dark:border-amber-800 dark:text-amber-200' : 'bg-gray-50 dark:bg-gray-950 border-gray-200/70 dark:border-gray-800 text-gray-500 dark:text-gray-400' }}">
                                    51–75% Solide
                                </div>
                                <div class="py-1 px-2 rounded-lg border {{ $funkiEvaluation['tier'] === '75_100' ? 'bg-emerald-100 border-emerald-300 text-emerald-800 dark:bg-emerald-950/70 dark:border-emerald-800 dark:text-emerald-200' : 'bg-gray-50 dark:bg-gray-950 border-gray-200/70 dark:border-gray-800 text-gray-500 dark:text-gray-400' }}">
                                    76–100% Champion
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4 Matte KPI Boxen (Sauber einzeilig, kein Umbruch-Chaos) --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-5 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200/80 dark:border-gray-800 rounded-xl px-3 py-2.5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Werktage (Fit)</div>
                        <div class="text-sm font-extrabold text-gray-900 dark:text-white mt-0.5 whitespace-nowrap">
                            {{ $overallStats['workdays_due'] }} / {{ $overallStats['workdays_healthy'] }} Tage
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200/80 dark:border-gray-800 rounded-xl px-3 py-2.5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Krank / Genesung</div>
                        <div class="text-sm font-extrabold {{ $overallStats['sick_days_count'] > 0 ? 'text-sky-700 dark:text-sky-400' : 'text-gray-700 dark:text-gray-300' }} mt-0.5 whitespace-nowrap">
                            🤒 {{ $overallStats['sick_days_count'] }} {{ $overallStats['sick_days_count'] === 1 ? 'Tag' : 'Tage' }}
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200/80 dark:border-gray-800 rounded-xl px-3 py-2.5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Beste Serie</div>
                        <div class="text-sm font-extrabold text-gray-900 dark:text-white mt-0.5 whitespace-nowrap">
                            🔥 {{ $overallStats['best_streak'] }} {{ $overallStats['best_streak'] === 1 ? 'Tag' : 'Tage' }}
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200/80 dark:border-gray-800 rounded-xl px-3 py-2.5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Fokus-Zeit</div>
                        <div class="text-sm font-extrabold text-emerald-700 dark:text-emerald-400 mt-0.5 whitespace-nowrap">
                            {{ $overallStats['completed_hours'] }}h <span class="text-xs font-semibold text-gray-500">/ {{ $overallStats['due_hours'] }}h</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- System-Tipps & Logische Erkenntnisse (Matt & Lesbar) --}}
            <div class="xl:col-span-5 bg-white dark:bg-gray-900 rounded-2xl sm:rounded-3xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 lg:p-7 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white">System-Erkenntnisse & Tipps</h3>
                        <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-0.5">Aus deinen Mo–Fr Daten berechnet</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-700 dark:text-gray-300 shrink-0">
                        <x-heroicon-m-light-bulb class="w-4 h-4" />
                    </div>
                </div>

                <div class="space-y-2.5 flex-1 flex flex-col justify-center">
                    @foreach($systemTips as $tip)
                        @php
                            $leftAccent = match($tip['type']) {
                                'warning' => 'border-l-rose-600 dark:border-l-rose-500',
                                'success' => 'border-l-emerald-600 dark:border-l-emerald-500',
                                'weekend' => 'border-l-amber-500 dark:border-l-amber-400',
                                default   => 'border-l-sky-600 dark:border-l-sky-500',
                            };
                            $badgeClass = match($tip['type']) {
                                'warning' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-200',
                                'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-200',
                                'weekend' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/80 dark:text-amber-200',
                                default   => 'bg-sky-100 text-sky-800 dark:bg-sky-950/80 dark:text-sky-200',
                            };
                        @endphp
                        <div class="rounded-xl p-3 bg-gray-50 dark:bg-gray-950 border border-gray-200/90 dark:border-gray-800 border-l-4 {{ $leftAccent }}">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md {{ $badgeClass }}">
                                    {{ $tip['badge'] }}
                                </span>
                            </div>
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-100 leading-snug">
                                {{ $tip['title'] }}
                            </div>
                            <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-0.5 leading-relaxed">
                                {{ $tip['advice'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ===================================================================== --}}
        {{-- 2. HABIT-TRACKER MATRIX (MATT, CLEAN, MOBILOPTIMIERT)                 --}}
        {{-- ===================================================================== --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl sm:rounded-3xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 lg:p-7 shadow-sm overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white">Routine-Matrix & Tracking</h3>
                        <span class="px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700">
                            {{ $periodLabel }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Kreis antippen zum Abhaken · 🤒 schützt Quote & Serie bei Krankheit
                    </p>
                </div>

                @if($evalPeriod !== 'year')
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            wire:click="toggleSickDay('{{ $selectedDate }}')"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1 border
                            {{ $isSelectedDateSick
                                ? 'bg-sky-600 text-white border-sky-600 dark:bg-sky-500 dark:text-gray-950 font-bold'
                                : 'bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800' }}">
                            <span>{{ $isSelectedDateSick ? '🤒 Krank (' . \Carbon\Carbon::parse($selectedDate)->format('d.m.') . ') aktiv' : '🤒 Krank (' . \Carbon\Carbon::parse($selectedDate)->format('d.m.') . ')' }}</span>
                        </button>
                        <button wire:click="toggleAllForDate('{{ $selectedDate }}', true)" class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-800 text-xs font-semibold transition-colors flex items-center gap-1" title="Alle bisher fälligen Routinen des ausgewählten Tages abhaken">
                            <x-heroicon-m-check-badge class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            <span>Fällige ({{ \Carbon\Carbon::parse($selectedDate)->format('d.m.') }})</span>
                        </button>
                        <button wire:click="toggleAllForDate('{{ $selectedDate }}', false)" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors flex items-center gap-1">
                            <x-heroicon-m-check class="w-4 h-4" />
                            <span>Tag ({{ \Carbon\Carbon::parse($selectedDate)->format('d.m.') }}) komplett</span>
                        </button>
                    </div>
                @endif
            </div>

            {{-- VERLAUFS-KURVE OBEN ÜBER DER MATRIX --}}
            @if(count($trendPoints) > 0)
                @php
                    $ptCount = count($trendPoints);
                    $svgW = 1000;
                    $svgH = 96;
                    $padX = 28;
                    $padY = 16;
                    $usableW = $svgW - ($padX * 2);
                    $usableH = $svgH - ($padY * 2);

                    $coords = [];
                    foreach ($trendPoints as $idx => $tp) {
                        $x = $ptCount > 1 ? $padX + ($idx / ($ptCount - 1)) * $usableW : $svgW / 2;
                        $y = $padY + $usableH - (($tp['pct'] / 100) * $usableH);
                        $coords[] = ['x' => round($x, 1), 'y' => round($y, 1), 'tp' => $tp];
                    }

                    $polylinePoints = implode(' ', array_map(fn($c) => $c['x'] . ',' . $c['y'], $coords));
                    $firstX = $coords[0]['x'] ?? $padX;
                    $lastX = $coords[count($coords) - 1]['x'] ?? ($svgW - $padX);
                    $areaPoints = $firstX . ',' . ($padY + $usableH) . ' ' . $polylinePoints . ' ' . $lastX . ',' . ($padY + $usableH);
                @endphp

                <div class="mb-5 bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800 p-3.5">
                    <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-2">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
                            Verlaufskurve ({{ $evalPeriod === 'week' ? 'Mo – Fr' : ($evalPeriod === 'month' ? 'Werktage im Monat' : '12 Monate') }})
                        </span>
                        <span class="text-emerald-700 dark:text-emerald-400 font-extrabold">{{ $overallStats['evaluation_pct'] }}% Schnitt</span>
                    </div>

                    <div class="relative w-full h-24">
                        <svg viewBox="0 0 {{ $svgW }} {{ $svgH }}" class="w-full h-full overflow-visible" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="routineTrendGradMatte" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#059669" stop-opacity="0.18"/>
                                    <stop offset="100%" stop-color="#059669" stop-opacity="0.0"/>
                                </linearGradient>
                            </defs>
                            <line x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $svgW - $padX }}" y2="{{ $padY }}" stroke="currentColor" class="text-gray-200 dark:text-gray-800" stroke-dasharray="4 4" stroke-width="1" />
                            <line x1="{{ $padX }}" y1="{{ $padY + $usableH / 2 }}" x2="{{ $svgW - $padX }}" y2="{{ $padY + $usableH / 2 }}" stroke="currentColor" class="text-gray-200 dark:text-gray-800" stroke-dasharray="4 4" stroke-width="1" />
                            <line x1="{{ $padX }}" y1="{{ $padY + $usableH }}" x2="{{ $svgW - $padX }}" y2="{{ $padY + $usableH }}" stroke="currentColor" class="text-gray-200 dark:text-gray-800" stroke-width="1" />

                            <polygon points="{{ $areaPoints }}" fill="url(#routineTrendGradMatte)" />
                            <polyline fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" points="{{ $polylinePoints }}" />

                            @foreach($coords as $c)
                                <g>
                                    <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="{{ $ptCount <= 12 ? 4.5 : 3 }}" class="{{ !empty($c['tp']['is_sick']) ? 'fill-sky-600 dark:fill-sky-400' : 'fill-emerald-600 dark:fill-emerald-400' }} stroke-white dark:stroke-gray-950" stroke-width="2">
                                        <title>{{ $c['tp']['full_label'] ?? $c['tp']['label'] }}: {{ !empty($c['tp']['is_sick']) ? 'Krankheitstag (Geschützt)' : ($c['tp']['pct'] . '% (' . $c['tp']['sub'] . ')') }}</title>
                                    </circle>
                                    @if($ptCount <= 12)
                                        <text x="{{ $c['x'] }}" y="{{ max(10, $c['y'] - 7) }}" text-anchor="middle" class="fill-gray-700 dark:fill-gray-300 text-[10px] font-bold">
                                            {{ !empty($c['tp']['is_sick']) ? '🤒' : $c['tp']['pct'] . '%' }}
                                        </text>
                                    @endif
                                </g>
                            @endforeach
                        </svg>
                    </div>
                </div>
            @endif

            {{-- MATRIX TABELLE (Mobiloptimiert & Komplett Scrollbar + Monatsansicht ohne Abschneiden) --}}
            <div x-data class="relative w-full min-w-0">
                @if($evalPeriod === 'month')
                    <div class="flex items-center justify-end gap-1.5 mb-2 xl:hidden">
                        <span class="text-[10px] font-semibold text-gray-400 mr-1">Horizontal scrollen:</span>
                        <button type="button" @click="$refs.matrixScroll.scrollBy({ left: -260, behavior: 'smooth' })" class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-bold border border-gray-200 dark:border-gray-700 hover:bg-gray-200">
                            ← Links
                        </button>
                        <button type="button" @click="$refs.matrixScroll.scrollBy({ left: 260, behavior: 'smooth' })" class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-bold border border-gray-200 dark:border-gray-700 hover:bg-gray-200">
                            Rechts →
                        </button>
                    </div>
                @endif

                <div x-ref="matrixScroll" class="w-full overflow-x-auto matrix-scrollbar pb-2">
                    <table class="w-full border-collapse {{ $evalPeriod === 'month' ? 'min-w-[980px]' : 'min-w-[640px]' }}">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-2.5 pl-2 pr-2 text-left {{ $evalPeriod === 'month' ? 'w-40 sm:w-52 max-w-[210px]' : 'w-44 sm:w-72' }} sticky left-0 bg-white dark:bg-gray-900 z-20">
                                    Routine
                                </th>

                                @if($evalPeriod === 'week')
                                    @foreach($workdaysInPeriod as $wDay)
                                        <th class="py-2.5 px-1.5 text-center min-w-[58px] {{ $wDay['is_sick'] ? 'bg-sky-50/70 dark:bg-sky-950/30' : '' }}">
                                            <div class="flex flex-col items-center gap-1">
                                                <button wire:click="selectDate('{{ $wDay['date'] }}')" class="px-2 py-1 rounded-lg transition-colors {{ $wDay['date'] === $selectedDate ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold' : ($wDay['is_sick'] ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200 font-bold' : ($wDay['is_today'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800')) }}">
                                                    <div>{{ $wDay['day_short'] }} {{ $wDay['is_sick'] ? '🤒' : '' }}</div>
                                                    <div class="text-[9px] opacity-75">{{ $wDay['day_num'] }}.</div>
                                                </button>
                                                <div class="flex items-center gap-1 text-[9px]">
                                                    <button wire:click="toggleAllForDate('{{ $wDay['date'] }}')" class="font-semibold text-gray-500 hover:text-emerald-700 dark:hover:text-emerald-400" title="Alle Routinen für {{ $wDay['label'] }} abhaken">
                                                        Alle ✓
                                                    </button>
                                                    <span class="text-gray-300 dark:text-gray-700">|</span>
                                                    <button wire:click="toggleSickDay('{{ $wDay['date'] }}')" class="font-semibold {{ $wDay['is_sick'] ? 'text-sky-600 dark:text-sky-400' : 'text-gray-400 hover:text-sky-600' }}" title="{{ $wDay['label'] }} als Krank markieren">
                                                        🤒
                                                    </button>
                                                </div>
                                            </div>
                                        </th>
                                    @endforeach
                                    {{-- Heiliges Wochenende Spalte --}}
                                    <th class="py-2.5 px-2 text-center min-w-[76px]">
                                        <div class="px-2 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/70 text-amber-800 dark:text-amber-300 inline-flex flex-col items-center">
                                            <span>🌴 Sa/So</span>
                                            <span class="text-[8px] font-semibold">Frei</span>
                                        </div>
                                    </th>
                                @elseif($evalPeriod === 'month')
                                    @foreach($daysInPeriod as $dObj)
                                        <th class="py-2 px-0.5 text-center {{ $dObj['is_weekend'] ? 'w-4 bg-gray-50 dark:bg-gray-950/60' : 'w-6' }} {{ !$dObj['is_weekend'] && $dObj['is_sick'] ? 'bg-sky-50/70 dark:bg-sky-950/30' : '' }}">
                                            @if($dObj['is_weekend'])
                                                <div class="flex flex-col items-center text-gray-400 dark:text-gray-600 leading-tight" title="Wochenende – Freie Gestaltung">
                                                    <span class="text-[7px]">{{ $dObj['day_short'] }}</span>
                                                    <span class="text-[8px] font-semibold">{{ $dObj['day_num'] }}</span>
                                                </div>
                                            @else
                                                <button wire:click="selectDate('{{ $dObj['date'] }}')" class="flex flex-col items-center mx-auto px-0.5 py-0.5 rounded-md transition-colors leading-tight {{ $dObj['date'] === $selectedDate ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold' : ($dObj['is_sick'] ? 'text-sky-700 dark:text-sky-400 font-bold' : ($dObj['is_today'] ? 'text-emerald-700 dark:text-emerald-400 font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white')) }}" title="Tag {{ $dObj['label'] }} auswählen">
                                                    <span class="text-[8px]">{{ $dObj['is_sick'] ? '🤒' : $dObj['day_short'] }}</span>
                                                    <span class="text-[9px] font-bold">{{ $dObj['day_num'] }}</span>
                                                </button>
                                            @endif
                                        </th>
                                    @endforeach
                                @else
                                    @foreach(['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'] as $mShort)
                                        <th class="py-2.5 px-1.5 text-center min-w-[44px]">{{ $mShort }}</th>
                                    @endforeach
                                @endif

                                {{-- Rechte Auswertungs-Spalten (In der Monatsansicht nicht sticky, damit niemals Tage verdeckt werden!) --}}
                                <th class="py-2.5 pl-2 pr-2 text-right whitespace-nowrap {{ $evalPeriod === 'month' ? 'w-28' : 'min-w-[135px] lg:sticky lg:right-0' }} bg-white dark:bg-gray-900 z-20">
                                    Quote · 🔥 · ⭐
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80 text-xs sm:text-sm">
                            @forelse($matrixRows as $row)
                                @php
                                    $dotClass = $typeDotColors[$row['type']] ?? $typeDotColors['general'];
                                    $pctColor = $row['pct'] >= 76 ? 'text-emerald-700 dark:text-emerald-400' : ($row['pct'] >= 51 ? 'text-amber-700 dark:text-amber-400' : ($row['pct'] >= 26 ? 'text-orange-700 dark:text-orange-400' : 'text-red-700 dark:text-red-400'));
                                @endphp
                                <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                    {{-- Links: Farb-Punkt + Uhrzeit + Titel --}}
                                    <td class="py-2.5 pl-2 pr-2 {{ $evalPeriod === 'month' ? 'w-40 sm:w-52 max-w-[210px]' : '' }} sticky left-0 bg-white dark:bg-gray-900 group-hover:bg-gray-50 dark:group-hover:bg-gray-800/40 z-10">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="w-2 h-2 rounded-full shrink-0 {{ $dotClass }}"></span>
                                            <span class="font-mono text-[10px] sm:text-[11px] font-semibold text-gray-500 dark:text-gray-400 shrink-0">{{ $row['start_time'] }}</span>
                                            <span class="font-semibold text-gray-900 dark:text-gray-100 truncate text-xs" title="{{ $row['title'] }}">
                                                {{ $row['title'] }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Mitte: Matte Check-Kreise pro Tag --}}
                                    @if($evalPeriod === 'week')
                                        @foreach($row['cells'] as $cell)
                                            <td class="py-2 px-1.5 text-center {{ $cell['is_sick'] ? 'bg-sky-50/70 dark:bg-sky-950/30' : '' }}">
                                                @if($cell['is_sick'] && !$cell['is_completed'])
                                                    <button
                                                        wire:click="toggleRoutineLog('{{ $row['id'] }}', '{{ $cell['date'] }}')"
                                                        class="w-8 h-8 rounded-full mx-auto flex items-center justify-center transition-colors bg-sky-100 dark:bg-sky-950 border border-sky-300 dark:border-sky-800 text-sky-700 dark:text-sky-300 hover:bg-sky-200"
                                                        title="Krankheitstag (Geschützt) – Antippen falls trotzdem erledigt">
                                                        <span class="text-xs">🤒</span>
                                                    </button>
                                                @else
                                                    <button
                                                        wire:click="toggleRoutineLog('{{ $row['id'] }}', '{{ $cell['date'] }}')"
                                                        class="w-8 h-8 rounded-full mx-auto flex items-center justify-center transition-colors active:scale-95
                                                        {{ $cell['is_completed']
                                                            ? 'bg-emerald-600 dark:bg-emerald-500 text-white dark:text-gray-950 border border-emerald-700 dark:border-emerald-400'
                                                            : 'bg-gray-100 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 text-transparent hover:border-emerald-600 dark:hover:border-emerald-400' }}"
                                                        title="{{ $row['title'] }} am {{ $cell['day_short'] }} ({{ $cell['date'] }})">
                                                        <x-heroicon-m-check class="w-4 h-4 stroke-[2.5]" />
                                                    </button>
                                                @endif
                                            </td>
                                        @endforeach
                                        {{-- Heiliges Wochenende Zelle --}}
                                        <td class="py-2 px-2 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-[10px] font-semibold text-gray-500 dark:text-gray-400 select-none">
                                                Frei
                                            </span>
                                        </td>
                                    @elseif($evalPeriod === 'month')
                                        @foreach($row['cells'] as $cell)
                                            <td class="py-1.5 px-0.5 text-center {{ $cell['is_weekend'] ? 'bg-gray-50 dark:bg-gray-950/60' : ($cell['is_sick'] ? 'bg-sky-50/70 dark:bg-sky-950/30' : '') }}">
                                                @if($cell['is_weekend'])
                                                    <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-700 inline-block" title="Wochenende (Frei)"></span>
                                                @elseif($cell['is_sick'] && !$cell['is_completed'])
                                                    <button
                                                        wire:click="toggleRoutineLog('{{ $row['id'] }}', '{{ $cell['date'] }}')"
                                                        class="w-5 h-5 rounded-full mx-auto flex items-center justify-center bg-sky-100 dark:bg-sky-950 border border-sky-300 dark:border-sky-800 text-[9px]"
                                                        title="Krankheitstag (Geschützt) · {{ $cell['day_short'] }} {{ $cell['day_num'] }}.">
                                                        🤒
                                                    </button>
                                                @else
                                                    <button
                                                        wire:click="toggleRoutineLog('{{ $row['id'] }}', '{{ $cell['date'] }}')"
                                                        class="w-5 h-5 rounded-full mx-auto flex items-center justify-center transition-colors
                                                        {{ $cell['is_completed']
                                                            ? 'bg-emerald-600 dark:bg-emerald-500 text-white dark:text-gray-950'
                                                            : 'bg-gray-100 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 text-transparent hover:border-emerald-600' }}"
                                                        title="{{ $row['title'] }} · {{ $cell['day_short'] }} {{ $cell['day_num'] }}.">
                                                        <x-heroicon-m-check class="w-3 h-3 stroke-[2.5]" />
                                                    </button>
                                                @endif
                                            </td>
                                        @endforeach
                                    @else
                                        @foreach($row['cells'] as $mCell)
                                            <td class="py-2 px-1 text-center">
                                                @php
                                                    $mBadge = $mCell['pct'] >= 76
                                                        ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-300 dark:border-emerald-800'
                                                        : ($mCell['pct'] >= 51
                                                            ? 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950/70 dark:text-amber-300 dark:border-amber-800'
                                                            : ($mCell['done'] > 0
                                                                ? 'bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-950/70 dark:text-orange-300 dark:border-orange-800'
                                                                : 'bg-gray-100 dark:bg-gray-950 text-gray-500 border-gray-200 dark:border-gray-800'));
                                                @endphp
                                                <div class="px-1.5 py-0.5 rounded-md border text-[10px] font-bold {{ $mBadge }}" title="{{ $mCell['label'] }}: {{ $mCell['done'] }} / {{ $mCell['target'] }} gesunde Arbeitstage ({{ $mCell['pct'] }}%)">
                                                    {{ $mCell['pct'] }}%
                                                </div>
                                            </td>
                                        @endforeach
                                    @endif

                                    {{-- Rechts: % Quote + 🔥 Streak + ⭐ Sterne --}}
                                    <td class="py-2.5 pl-2 pr-2 text-right whitespace-nowrap {{ $evalPeriod === 'month' ? '' : 'lg:sticky lg:right-0' }} bg-white dark:bg-gray-900 group-hover:bg-gray-50 dark:group-hover:bg-gray-800/40 z-10">
                                        <div class="inline-flex items-center justify-end gap-2 font-bold text-xs">
                                            <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                                {{ $row['completed_count'] }}/{{ $scoreMode === 'smart' && $row['due_count'] > 0 ? $row['due_count'] : $row['target_count'] }}
                                            </span>
                                            <span class="w-9 text-right font-extrabold {{ $pctColor }}">
                                                {{ $row['pct'] }}%
                                            </span>
                                            <span class="inline-flex items-center gap-0.5 text-orange-700 dark:text-orange-400 min-w-[26px] justify-end" title="Serie (aufeinanderfolgende Mo–Fr Arbeitstage)">
                                                <span>🔥</span><span>{{ $row['streak'] }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-0.5 text-amber-700 dark:text-amber-400 min-w-[26px] justify-end" title="Im Zeitraum erledigt">
                                                <span>⭐</span><span>{{ $row['stars'] }}</span>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="35" class="py-8 text-center text-gray-500 text-xs uppercase tracking-widest font-bold">
                                        Keine aktiven Routinen vorhanden
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ===================================================================== --}}
        {{-- 3. AUSWERTUNGS-CHARTS (MATT & CLEAN)                                  --}}
        {{-- ===================================================================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Chart A: Wochentags-Effizienz (Mo - Fr) --}}
            <div class="bg-white dark:bg-gray-900 rounded-2xl sm:rounded-3xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between gap-2 mb-5">
                    <div>
                        <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white">Wochentags-Disziplin (Mo – Fr)</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Erfüllungsquote nach Arbeitstag</p>
                    </div>
                    <span class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 shrink-0">
                        🌴 Sa & So Frei
                    </span>
                </div>

                <div class="grid grid-cols-5 gap-2.5 sm:gap-4 items-end h-40 pt-4 px-1">
                    @foreach($weekdayStats as $wd)
                        @php
                            $isAllSickDay = $wd['target'] === 0 && !empty($wd['sick_days']);
                            $barColor = $isAllSickDay
                                ? 'bg-sky-600 dark:bg-sky-500'
                                : ($wd['pct'] >= 76
                                    ? 'bg-emerald-600 dark:bg-emerald-500'
                                    : ($wd['pct'] >= 51
                                        ? 'bg-amber-500 dark:bg-amber-400'
                                        : ($wd['pct'] > 0 ? 'bg-orange-500' : 'bg-gray-300 dark:bg-gray-800')));
                        @endphp
                        <div class="flex flex-col items-center h-full justify-end">
                            <div class="text-xs font-extrabold text-gray-800 dark:text-gray-200 mb-1">
                                {{ $isAllSickDay ? '🤒' : $wd['pct'] . '%' }}
                            </div>
                            <div class="w-full max-w-[44px] h-24 bg-gray-100 dark:bg-gray-950 rounded-xl p-1 border border-gray-200 dark:border-gray-800 flex items-end overflow-hidden">
                                <div class="w-full rounded-lg {{ $barColor }} transition-all duration-500" style="height: {{ max(6, $wd['pct']) }}%"></div>
                            </div>
                            <div class="text-xs font-bold text-gray-800 dark:text-gray-200 mt-1.5">{{ $wd['day'] }}</div>
                            <div class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                {{ $isAllSickDay ? 'Krank' : ($wd['done'] . '/' . $wd['target']) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Chart B: Lebensbereiche / Routine-Kategorien --}}
            <div class="bg-white dark:bg-gray-900 rounded-2xl sm:rounded-3xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 shadow-sm">
                <div class="mb-5">
                    <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white">Performance nach Bereich</h3>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Stärken & Schwachstellen nach Routine-Kategorie</p>
                </div>

                <div class="space-y-3">
                    @foreach($categoryStats as $cat)
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $cat['color'] }}"></span>
                                    {{ $cat['label'] }}
                                </span>
                                <span class="font-bold {{ $cat['text'] }}">
                                    {{ $cat['done'] }}/{{ $cat['target'] }} · {{ $cat['pct'] }}%
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-950 overflow-hidden border border-gray-200/80 dark:border-gray-800">
                                <div class="h-full rounded-full {{ $cat['color'] }} transition-all duration-500" style="width: {{ max(2, $cat['pct']) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if($viewMode !== 'tracker')
        {{-- ===================================================================== --}}
        {{-- 4. DIE KLASSISCHE TAGESROUTINE (BIO-RHYTHMUS TIMELINE) + DIREKT-CHECK --}}
        {{-- ===================================================================== --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl sm:rounded-3xl shadow-sm border border-gray-200 dark:border-gray-800 p-4 sm:p-6 lg:p-8 min-h-[550px] flex flex-col relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 relative z-10">
                <div>
                    <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Tagesroutine</h3>
                    <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400 mt-0.5 uppercase tracking-[0.15em]">Dein Bio-Rhythmus · Direktes Abhaken & Struktur</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if(!$isEditing)
                        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-semibold">
                            <button wire:click="$set('filterDays', 'all')" class="px-3 py-1.5 rounded-lg transition-colors {{ $filterDays === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">Alle</button>
                            <button wire:click="$set('filterDays', 'weekdays')" class="px-3 py-1.5 rounded-lg transition-colors {{ $filterDays === 'weekdays' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">Werktags</button>
                            <button wire:click="$set('filterDays', 'weekend')" class="px-3 py-1.5 rounded-lg transition-colors {{ $filterDays === 'weekend' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">Wochenende</button>
                        </div>
                    @endif
                    <button wire:click="create" class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-950 text-gray-700 dark:text-white flex items-center justify-center border border-gray-200 dark:border-gray-800 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors" title="Neue Routine erstellen">
                        <x-heroicon-m-plus class="w-5 h-5" />
                    </button>
                </div>
            </div>

            {{-- SCHNELL-TAGESWAHL FÜR DAS ABHAKEN IN DER TIMELINE --}}
            @if(!$isEditing)
                <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 bg-gray-50 dark:bg-gray-950 p-2.5 sm:p-3 rounded-2xl border border-gray-200 dark:border-gray-800 relative z-10">
                    <div class="flex items-center gap-1.5 overflow-x-auto custom-scrollbar pb-1 sm:pb-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mr-1 shrink-0">Tag:</span>
                        @foreach($quickWeekDays as $qDay)
                            <button
                                wire:click="selectDate('{{ $qDay['date'] }}')"
                                class="px-2.5 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 border shrink-0
                                {{ $qDay['is_selected']
                                    ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white font-bold'
                                    : ($qDay['is_sick']
                                        ? 'bg-sky-100 border-sky-300 text-sky-800 dark:bg-sky-950 dark:border-sky-800 dark:text-sky-200'
                                        : ($qDay['is_weekend']
                                            ? 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-950/40 dark:border-amber-800/70 dark:text-amber-300'
                                            : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800')) }}">
                                <span>{{ $qDay['day_short'] }} {{ $qDay['day_num'] }}</span>
                                @if($qDay['is_sick'])
                                    <span title="Krankheitstag">🤒</span>
                                @elseif($qDay['is_weekend'])
                                    <span title="Heiliges Wochenende">🌴</span>
                                @else
                                    <span class="text-[10px] px-1.5 py-0.2 rounded-md {{ $qDay['is_selected'] ? 'bg-white/20 text-white dark:bg-black/15 dark:text-gray-900' : ($qDay['pct'] >= 75 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400') }}">
                                        {{ $qDay['done_count'] }}/{{ $qDay['total_count'] }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            wire:click="toggleSickDay('{{ $selectedDate }}')"
                            class="text-xs font-semibold px-3 py-1.5 rounded-xl border transition-colors flex items-center gap-1.5
                            {{ $isSelectedDateSick
                                ? 'bg-sky-600 text-white border-sky-600 dark:bg-sky-500 dark:text-gray-950 font-bold'
                                : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                            <span>{{ $isSelectedDateSick ? '🤒 Krankmeldung aufheben' : '🤒 Krankmelden (' . \Carbon\Carbon::parse($selectedDate)->format('d.m.') . ')' }}</span>
                        </button>

                        @if(\Carbon\Carbon::parse($selectedDate)->isWeekend())
                            <span class="text-xs font-semibold text-amber-800 dark:text-amber-300 px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800">
                                🌴 Wochenende: Freie Gestaltung
                            </span>
                        @elseif(!$isSelectedDateSick)
                            <button wire:click="toggleAllForDate('{{ $selectedDate }}', false)" class="text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                                <x-heroicon-m-check class="w-4 h-4" />
                                <span>Alle ({{ \Carbon\Carbon::parse($selectedDate)->format('d.m.') }}) abhaken</span>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- MEDIZIN-FUNKI GENESUNGS-BANNER IN DER TAGESROUTINE, WENN KRANK --}}
                @if($isSelectedDateSick)
                    <div class="mb-5 rounded-2xl border border-sky-200 dark:border-sky-800 bg-sky-50/80 dark:bg-sky-950/40 p-4 flex flex-col sm:flex-row items-center gap-4 relative z-10">
                        <div class="w-20 h-20 rounded-xl bg-white dark:bg-gray-900 border border-sky-200 dark:border-sky-800 p-1.5 shrink-0 flex items-center justify-center">
                            <img src="{{ asset('shop/projekt/funki/routine/funki_sick.webp') }}" alt="Medizin-Funki Gute Besserung" class="w-full h-full object-contain">
                        </div>
                        <div class="flex-1 text-center sm:text-left">
                            <span class="inline-block px-2 py-0.5 rounded-md bg-sky-100 dark:bg-sky-900 text-sky-800 dark:text-sky-200 text-[10px] font-bold uppercase tracking-wider mb-1">
                                Genesungs-Tag aktiv ({{ \Carbon\Carbon::parse($selectedDate)->format('d.m.Y') }})
                            </span>
                            <h4 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                                Medizin-Funki wünscht dir gute Besserung!
                            </h4>
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5 leading-relaxed">
                                Deine Serien sind eingefroren und dein Score bleibt geschützt. Ruh dich gut aus!
                            </p>
                        </div>
                    </div>
                @endif
            @endif

            {{-- LISTE --}}
            @if(!$isEditing)
                @php
                    $nowFormat = date('H:i');
                    $activeId = null;
                    $sortedRoutines = $routines->sortBy('start_time')->values();

                    foreach ($sortedRoutines as $i => $r) {
                        $start = date('H:i', strtotime($r->start_time));
                        $end = ($r->duration_minutes > 0)
                            ? date('H:i', strtotime($r->start_time . " +{$r->duration_minutes} minutes"))
                            : date('H:i', strtotime(($sortedRoutines[$i + 1] ?? $sortedRoutines[0])->start_time));

                        if ($start <= $end) {
                            if ($nowFormat >= $start && $nowFormat < $end) { $activeId = $r->id; break; }
                        } else {
                            if ($nowFormat >= $start || $nowFormat < $end) { $activeId = $r->id; break; }
                        }
                    }
                @endphp

                <div class="space-y-3 overflow-y-auto custom-scrollbar pr-1 sm:pr-3 flex-1 relative z-10 pt-1 pb-4">
                    @forelse($sortedRoutines as $r)
                        @php
                            $isActive = $r->id === $activeId;
                            $logForDay = $logMapForSelectedDate[$r->id] ?? null;
                            $isCompletedToday = $logForDay && $logForDay->is_completed;
                            $completedStepIds = ($logForDay && is_array($logForDay->completed_steps)) ? $logForDay->completed_steps : ($isCompletedToday ? $r->steps->pluck('id')->map(fn($id) => (string)$id)->toArray() : []);
                            $stepsCount = $r->steps->count();
                            $doneStepsCount = count(array_intersect($r->steps->pluck('id')->map(fn($id) => (string)$id)->toArray(), $completedStepIds));
                            $isStepsExpanded = in_array($r->id, $expandedSteps);
                        @endphp

                        <div class="flex items-stretch gap-2.5 sm:gap-4 group">
                            {{-- Uhrzeit --}}
                            <div class="w-11 sm:w-12 text-right font-mono text-xs font-bold pt-4 shrink-0 {{ $isActive ? 'text-gray-900 dark:text-white' : ($isCompletedToday ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400') }}">
                                {{ \Carbon\Carbon::parse($r->start_time)->format('H:i') }}
                            </div>

                            {{-- Timeline Continuous Track & Dot --}}
                            <div class="hidden sm:flex relative flex-col items-center justify-start shrink-0 w-4 self-stretch pt-4">
                                @if(!$loop->first)
                                    <div class="absolute top-0 {{ $loop->last ? 'h-4' : 'bottom-0' }} w-0.5 {{ $isCompletedToday ? 'bg-emerald-500/40' : 'bg-gray-200 dark:bg-gray-800' }} -translate-x-1/2 left-1/2 z-0"></div>
                                @elseif(!$loop->last)
                                    <div class="absolute top-4 bottom-0 w-0.5 {{ $isCompletedToday ? 'bg-emerald-500/40' : 'bg-gray-200 dark:bg-gray-800' }} -translate-x-1/2 left-1/2 z-0"></div>
                                @endif

                                @if($isActive)
                                    <div class="w-3.5 h-3.5 rounded-full bg-[var(--theme-color)] ring-4 ring-[var(--theme-color-20)] z-10"></div>
                                @elseif($isCompletedToday)
                                    <div class="w-3.5 h-3.5 rounded-full bg-emerald-600 dark:bg-emerald-500 z-10"></div>
                                @else
                                    <div class="w-3 h-3 rounded-full border-2 {{ $r->is_active ? 'border-gray-400 dark:border-gray-600 bg-white dark:bg-gray-950' : 'border-gray-300 dark:border-gray-800 bg-gray-200 dark:bg-gray-900 opacity-40' }} z-10"></div>
                                @endif
                            </div>

                            {{-- Direkt-Abhak-Button (Matt & Klar) --}}
                            <div class="pt-3 shrink-0">
                                <button
                                    wire:click.stop="toggleRoutineLog('{{ $r->id }}', '{{ $selectedDate }}')"
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center transition-colors active:scale-95
                                    {{ $isCompletedToday
                                        ? 'bg-emerald-600 dark:bg-emerald-500 text-white dark:text-gray-950 border border-emerald-700 dark:border-emerald-400'
                                        : ($isSelectedDateSick
                                            ? 'bg-sky-100 dark:bg-sky-950 border border-sky-300 dark:border-sky-800 text-sky-700 dark:text-sky-300'
                                            : 'bg-gray-100 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 text-gray-400 hover:border-emerald-600 hover:text-emerald-600') }}"
                                    title="{{ $isCompletedToday ? 'Als offen markieren' : 'Für ' . \Carbon\Carbon::parse($selectedDate)->format('d.m.Y') . ' abhaken' }}">
                                    @if($isSelectedDateSick && !$isCompletedToday)
                                        <span class="text-xs">🤒</span>
                                    @else
                                        <x-heroicon-m-check class="w-4 h-4 sm:w-5 sm:h-5 stroke-[2.5]" />
                                    @endif
                                </button>
                            </div>

                            {{-- Routine Karte --}}
                            <div class="flex-1 relative transition-colors cursor-pointer rounded-2xl p-3.5 sm:p-5 border flex flex-col justify-center min-w-0
                                {{ !$r->is_active ? 'opacity-60 bg-gray-50 dark:bg-gray-950/40 border-gray-200 dark:border-gray-800' : ($isActive
                                    ? 'bg-amber-50/60 dark:bg-gray-800/70 border-amber-300 dark:border-amber-500/40'
                                    : ($isCompletedToday
                                        ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/60'
                                        : 'bg-gray-50 dark:bg-gray-950/60 border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700')) }}"
                                 wire:click="edit('{{ $r->id }}')">

                                <div class="flex justify-between items-start gap-2.5">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="font-bold text-sm sm:text-base text-gray-900 dark:text-white">
                                                {{ $r->title }}
                                            </h4>
                                            @if($isActive)
                                                <span class="bg-amber-200 text-amber-950 dark:bg-amber-500 dark:text-gray-950 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md">
                                                    In Aktion
                                                </span>
                                            @elseif($isCompletedToday)
                                                <span class="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/70 dark:text-emerald-200 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md">
                                                    Erledigt ✓
                                                </span>
                                            @elseif($isSelectedDateSick)
                                                <span class="bg-sky-100 text-sky-800 dark:bg-sky-900/70 dark:text-sky-200 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md">
                                                    Krank 🤒
                                                </span>
                                            @endif
                                            @if($r->days_of_week && $r->days_of_week !== 'all')
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                    {{ $r->days_of_week === 'weekdays' ? 'Mo - Fr' : 'Sa - So' }}
                                                </span>
                                            @endif
                                            @if(!$r->is_active)
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-gray-200 dark:bg-gray-800 text-gray-600">Pausiert</span>
                                            @endif
                                        </div>

                                        @if($r->message)
                                            <div class="mt-1">
                                                <p class="leading-relaxed text-gray-600 dark:text-gray-400 text-xs">
                                                    {{ $r->message }}
                                                </p>
                                            </div>
                                        @endif

                                        {{-- Ausklappbare Sub-Steps (Checkliste für den Block) --}}
                                        @if($stepsCount > 0)
                                            <div class="mt-2.5 pt-2 border-t border-gray-200/80 dark:border-gray-800" wire:click.stop>
                                                <button
                                                    type="button"
                                                    wire:click.stop="toggleExpandSteps('{{ $r->id }}')"
                                                    class="inline-flex items-center gap-1.5 text-[11px] font-bold {{ $doneStepsCount === $stepsCount ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }} transition-colors">
                                                    <x-heroicon-m-list-bullet class="w-3.5 h-3.5" />
                                                    <span>Checkliste ({{ $doneStepsCount }}/{{ $stepsCount }} Schritte)</span>
                                                    <x-heroicon-m-chevron-down class="w-3.5 h-3.5 transition-transform {{ $isStepsExpanded ? 'rotate-180' : '' }}" />
                                                </button>

                                                @if($isStepsExpanded)
                                                    <div class="mt-2 space-y-1.5">
                                                        @foreach($r->steps as $step)
                                                            @php $stepDone = in_array((string)$step->id, $completedStepIds); @endphp
                                                            <button
                                                                type="button"
                                                                wire:click.stop="toggleStepLog('{{ $r->id }}', '{{ $step->id }}', '{{ $selectedDate }}')"
                                                                class="w-full flex items-center justify-between gap-2 text-left px-3 py-1.5 rounded-xl border transition-colors text-xs
                                                                {{ $stepDone
                                                                    ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 line-through'
                                                                    : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 hover:border-emerald-500' }}">
                                                                <span class="flex items-center gap-2 min-w-0">
                                                                    <span class="w-4 h-4 rounded flex items-center justify-center shrink-0 {{ $stepDone ? 'bg-emerald-600 text-white' : 'border border-gray-400 dark:border-gray-600' }}">
                                                                        @if($stepDone)
                                                                            <x-heroicon-m-check class="w-3 h-3 stroke-[3]" />
                                                                        @endif
                                                                    </span>
                                                                    <span class="truncate font-medium">{{ $step->title }}</span>
                                                                </span>
                                                                <span class="text-[10px] font-semibold text-gray-500 shrink-0">{{ $step->duration_minutes }} Min</span>
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-md border bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300">
                                        {{ $r->duration_minutes }} MIN
                                    </div>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="hidden sm:flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity self-center shrink-0">
                                <button wire:click="toggleActive('{{ $r->id }}')" class="p-2 text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors" title="{{ $r->is_active ? 'Routine pausieren' : 'Routine aktivieren' }}">
                                    @if($r->is_active)
                                        <x-heroicon-m-pause class="w-4 h-4" />
                                    @else
                                        <x-heroicon-m-play class="w-4 h-4" />
                                    @endif
                                </button>
                                <button wire:click="delete('{{ $r->id }}')" wire:confirm="Routine löschen?" class="text-gray-500 hover:text-red-600 transition-colors p-2" title="Löschen">
                                    <x-heroicon-m-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-clock class="w-10 h-10 mx-auto mb-2 opacity-50" />
                            <p class="text-xs uppercase tracking-wider font-bold">Keine Routinen für diesen Filter vorhanden</p>
                        </div>
                    @endforelse
                </div>
            @else
                {{-- EDITOR --}}
                <div class="flex-1 flex flex-col relative z-10">
                    <div class="space-y-5 bg-gray-50 dark:bg-gray-950 p-4 sm:p-6 rounded-2xl border border-gray-200 dark:border-gray-800">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Uhrzeit Start</label>
                                <input type="time" wire:model="r_time" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-xl font-bold text-gray-900 dark:text-white">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Dauer (Minuten)</label>
                                <input type="number" wire:model="r_duration" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-xl text-sm font-bold text-gray-900 dark:text-white">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Typ der Routine</label>
                                <select wire:model="r_type" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-xl text-sm font-bold text-gray-900 dark:text-white">
                                    <option value="general">Allgemeine Arbeit (General)</option>
                                    <option value="work">Deep Work / Business (Work)</option>
                                    <option value="hygiene">Morgen- & Abendpflege (Hygiene)</option>
                                    <option value="sport">Sport / Bewegung (Sport)</option>
                                    <option value="food">Essen / Trinken (Food)</option>
                                    <option value="break">Pause / Entspannung (Break)</option>
                                    <option value="sleep">Schlafenszeit / Nachtruhe (Sleep)</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Wochentage</label>
                                <select wire:model="r_days_of_week" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-xl text-sm font-bold text-gray-900 dark:text-white">
                                    <option value="all">Standard Fokus (Mo - Fr gewertet)</option>
                                    <option value="weekdays">Nur Werktags (Mo - Fr)</option>
                                    <option value="weekend">Nur Wochenende (Sa - So Optional)</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Titel der Routine</label>
                            <input type="text" wire:model="r_title" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-xl text-sm font-bold text-gray-900 dark:text-white" placeholder="z.B. Deep Work Phase">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Funkis Anweisung</label>
                            <textarea wire:model="r_message" class="w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-xl text-sm h-28 resize-none leading-relaxed text-gray-900 dark:text-white" placeholder="Klare Anweisungen für diesen Zeitblock..."></textarea>
                        </div>

                        {{-- Unterschritte (Steps) im Editor verwalten --}}
                        <div class="space-y-2.5 pt-2 border-t border-gray-200 dark:border-gray-800">
                            <div class="flex items-center justify-between">
                                <label class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Unterschritte / Checkliste ({{ count($r_steps) }})</label>
                                <button type="button" wire:click="addEditorStep" class="text-xs font-bold text-emerald-700 dark:text-emerald-400 hover:underline flex items-center gap-1">
                                    <x-heroicon-m-plus class="w-4 h-4" />
                                    <span>Schritt hinzufügen</span>
                                </button>
                            </div>
                            <div class="space-y-2">
                                @foreach($r_steps as $sIdx => $stepItem)
                                    <div class="flex items-center gap-2">
                                        <input type="text" wire:model="r_steps.{{ $sIdx }}.title" placeholder="Schritt-Beschreibung..." class="flex-1 bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-xl text-xs font-semibold text-gray-900 dark:text-white">
                                        <input type="number" wire:model="r_steps.{{ $sIdx }}.duration_minutes" placeholder="Min" class="w-20 bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 rounded-xl text-xs font-semibold text-gray-900 dark:text-white text-center">
                                        <button type="button" wire:click="removeEditorStep({{ $sIdx }})" class="p-2 text-gray-500 hover:text-red-600 transition-colors">
                                            <x-heroicon-m-trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button wire:click="cancel" class="flex-1 py-3.5 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">Abbrechen</button>
                        <button wire:click="save" class="flex-1 py-3.5 text-xs font-bold uppercase tracking-wider text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors">Speichern</button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(156, 163, 175, 0.35); border-radius: 10px; }
        .matrix-scrollbar::-webkit-scrollbar { height: 8px; }
        .matrix-scrollbar::-webkit-scrollbar-track { background: rgba(156, 163, 175, 0.12); border-radius: 8px; }
        .matrix-scrollbar::-webkit-scrollbar-thumb { background: rgba(107, 114, 128, 0.45); border-radius: 8px; }
        .matrix-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(75, 85, 99, 0.7); }
    </style>
</div>
