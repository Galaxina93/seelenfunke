<div x-show="activeTab === 'seasons'" class="space-y-8 animate-fade-in" style="display: none;">
    @php
        $isAutoMode = filter_var($settings['season_auto_mode'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $manualAnimKey = (string) ($settings['manual_active_animation'] ?? '');
    @endphp

    {{-- 1. DIE 4 JAHRESZEITEN ALS KACHELN ZENTRIERT IN ERSTER REIHE + SWITCH "AUTOMATISCH" --}}
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 shadow-sm dark:shadow-2xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 mb-8 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3.5">
                <div class="p-3 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner shrink-0">
                    <x-heroicon-o-sun class="w-6 h-6" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h3 class="text-gray-900 dark:text-white font-serif font-bold text-xl tracking-wide">Die 4 Jahreszeiten</h3>
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border {{ $isAutoMode ? 'bg-[var(--theme-color-10)] text-[var(--theme-color)] border-[var(--theme-color-30)]' : 'bg-amber-500/10 text-amber-500 border-amber-500/30' }}">
                            {{ $isAutoMode ? 'Automatisch gesteuert' : 'Manuelle Auswahl aktiv' }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Es ist immer exakt eine Jahreszeit aktiv. Schalte „Automatisch“ aus, um eine Jahreszeit händisch per Klick zu aktivieren.
                    </p>
                </div>
            </div>

            {{-- SWITCH: AUTOMATISCH AN / AUS --}}
            <div class="flex items-center gap-4 bg-gray-50 dark:bg-gray-950/90 border border-gray-200 dark:border-gray-800 px-5 py-3.5 rounded-2xl shadow-inner self-start md:self-auto">
                <div class="text-right">
                    <span class="block text-[10px] font-black uppercase tracking-widest {{ $isAutoMode ? 'text-[var(--theme-color)]' : 'text-gray-500 dark:text-gray-400' }}">
                        Automatisch
                    </span>
                    <span class="block text-[10px] text-gray-500 font-medium">
                        {{ $isAutoMode ? 'An (Kalender-Automatik)' : 'Aus (Manuelle Wahl)' }}
                    </span>
                </div>
                <button
                    type="button"
                    wire:click="toggleSeasonAutoMode"
                    role="switch"
                    aria-checked="{{ $isAutoMode ? 'true' : 'false' }}"
                    style="width: 3.25rem; height: 1.75rem;"
                    class="relative inline-flex items-center shrink-0 cursor-pointer rounded-full p-0.5 transition-colors duration-200 ease-in-out focus:outline-none {{ $isAutoMode ? 'bg-[var(--theme-color)] shadow-[0_0_15px_var(--theme-color-40)]' : 'bg-gray-300 dark:bg-gray-700' }}"
                >
                    <span class="sr-only">Automatisch umschalten</span>
                    <span
                        aria-hidden="true"
                        style="width: 1.4rem; height: 1.4rem; transform: translateX({{ $isAutoMode ? '1.5rem' : '0rem' }});"
                        class="pointer-events-none inline-block rounded-full bg-white dark:bg-gray-900 shadow-md ring-0 transition-transform duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>

        {{-- 4 JAHRESZEITEN KACHELN (ZENTRIERT IN ERSTER REIHE) --}}
        <div class="max-w-5xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 justify-items-center">
            @foreach($seasonsDefinition ?? [] as $sKey => $season)
                @php
                    $isSeasonActive = ($activeSeasonKey ?? '') === $sKey;
                @endphp
                <div
                    @if(!$isAutoMode)
                        wire:click="selectManualSeason('{{ $sKey }}')"
                    @endif
                    class="w-full rounded-[2rem] p-6 transition-all duration-300 relative flex flex-col items-center text-center group select-none
                        {{ !$isAutoMode ? 'cursor-pointer hover:-translate-y-1' : 'cursor-default' }}
                        {{ $isSeasonActive
                            ? 'bg-[var(--theme-color-10)] dark:bg-[var(--theme-color-10)] border-2 border-[var(--theme-color)] shadow-[0_0_30px_var(--theme-color-30)] ring-1 ring-[var(--theme-color-40)] scale-[1.02] z-10'
                            : 'bg-gray-50/80 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)] opacity-75 hover:opacity-100'
                        }}"
                >
                    {{-- Top Row: Zeitraum & Haken / Aktiv-Badge --}}
                    <div class="w-full flex items-center justify-between gap-2 mb-4">
                        <span class="text-[9px] font-mono font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg border {{ $isSeasonActive ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-30)]' : 'bg-white dark:bg-gray-900 text-gray-500 border-gray-200 dark:border-gray-800' }}">
                            {{ $season['period'] }}
                        </span>

                        <div class="flex items-center gap-1.5">
                            @if($isSeasonActive)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-[var(--theme-color)] text-gray-900 shadow-[0_0_12px_var(--theme-color-50)]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-900 animate-ping"></span>
                                    Aktiv
                                </span>
                            @endif

                            {{-- Manuelle Checkbox / Haken --}}
                            <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all {{ $isSeasonActive ? 'bg-[var(--theme-color)] border-[var(--theme-color)] text-gray-900 shadow-[0_0_10px_var(--theme-color-40)]' : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-transparent group-hover:border-[var(--theme-color-50)]' }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Icon --}}
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-4xl mb-3 transition-transform duration-300 group-hover:scale-110 {{ $isSeasonActive ? 'bg-[var(--theme-color-15)] border border-[var(--theme-color-30)] shadow-[0_0_20px_var(--theme-color-20)]' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-inner' }}">
                        {{ $season['icon'] }}
                    </div>

                    {{-- Jahreszeit Name --}}
                    <h4 class="text-lg font-serif font-bold tracking-wide mb-1 {{ $isSeasonActive ? 'text-[var(--theme-color)] drop-shadow-[0_0_8px_var(--theme-color-30)]' : 'text-gray-900 dark:text-white' }}">
                        {{ $season['name'] }}
                    </h4>

                    {{-- Animation & Funki Details --}}
                    <div class="text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Animation: <span class="{{ $isSeasonActive ? 'text-[var(--theme-color)]' : 'text-gray-500 dark:text-gray-400' }}">{{ $season['animation_label'] }}</span>
                    </div>
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 leading-snug mb-4">
                        Funki: {{ $season['funki_outfit'] }}
                    </div>

                    {{-- Footer Status / Action Hint --}}
                    <div class="mt-auto w-full pt-3 border-t {{ $isSeasonActive ? 'border-[var(--theme-color-30)]' : 'border-gray-200 dark:border-gray-800/80' }} flex items-center justify-between text-[9px] font-black uppercase tracking-widest">
                        @if($isSeasonActive)
                            <span class="text-[var(--theme-color)] flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] animate-pulse"></span>
                                Leuchtet aktiv
                            </span>
                        @elseif(!$isAutoMode)
                            <span class="text-gray-500 group-hover:text-[var(--theme-color)] transition-colors">
                                Haken setzen ➔
                            </span>
                        @else
                            <span class="text-gray-400 dark:text-gray-600">
                                Standby
                            </span>
                        @endif

                        <a href="{{ asset('storage/' . $season['video']) }}" target="_blank" @click.stop class="text-[var(--theme-color)] hover:underline font-sans font-bold">
                            Video ↗
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 2. AKTIVE ANIMATIONEN & JAHRESZEITEN-STEUERUNG (INKL. MANUELLE HAKEN Z. B. FÜR "SCHNEE" & TIMELINE) --}}
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 shadow-sm dark:shadow-2xl">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h3 class="text-gray-900 dark:text-white font-serif font-bold text-xl tracking-wide">Aktive Animationen & Jahreszeiten-Steuerung</h3>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                        {{ (!empty($currentActiveAnimation['is_manual'])) ? 'Manuell übersteuert' : 'Vollautomatisch' }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Neben der Automatisierung kannst du hier jederzeit manuell einen Haken setzen, um z. B. gezielt den <strong>Schnee (Schneefall)</strong> oder eine andere Animation sofort zu aktivieren.
                </p>
            </div>

            @if(!empty($currentActiveAnimation))
                <div class="flex flex-wrap items-center gap-3 bg-gray-50 dark:bg-gray-950/90 border border-[var(--theme-color-30)] px-4 py-3 rounded-2xl shadow-[0_0_20px_var(--theme-color-10)]">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl leading-none">{{ $currentActiveAnimation['theme']['icon'] ?? '✨' }}</span>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] animate-ping"></span>
                                <span class="text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)]">Aktuell im System aktiv</span>
                            </div>
                            <div class="text-xs font-bold text-gray-900 dark:text-white">
                                {{ $currentActiveAnimation['theme']['label'] ?? 'Manufaktur' }}
                                <span class="text-gray-400 font-normal">({{ $currentActiveAnimation['event_name'] ?? 'Standard' }})</span>
                            </div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">
                                Funki-Outfit: <strong class="text-[var(--theme-color)]">{{ $currentActiveAnimation['funki_outfit'] ?? 'Standard' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- MANUELLE ANIMATIONS-HAKEN (Z.B. "SCHNEE" DIREKT AKTIVIEREN) --}}
        <div class="mb-8 p-5 rounded-3xl bg-gray-50/90 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] flex items-center gap-2">
                    <x-heroicon-s-sparkles class="w-4 h-4" />
                    Animation manuell per Haken aktivieren (Sofort-Effekt im Konfigurator)
                </span>
                @if($manualAnimKey !== '')
                    <button
                        type="button"
                        wire:click="toggleManualAnimation('{{ $manualAnimKey }}')"
                        class="text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-[var(--theme-color)] transition-colors cursor-pointer"
                    >
                        Manuellen Haken zurücksetzen ✕
                    </button>
                @endif
            </div>

            @php
                $manualEffectLabels = [
                    'winter'     => ['title' => 'Schnee (Schneefall)',       'sub' => 'Winter-Animation'],
                    'ostern'     => ['title' => 'Blütenflug',                'sub' => 'Frühlings-Animation'],
                    'sommer'     => ['title' => 'Sonnenstrahlen',            'sub' => 'Sommer-Animation'],
                    'herbst'     => ['title' => 'Goldenes Laub',             'sub' => 'Herbst-Animation'],
                    'hochzeit'   => ['title' => 'Romantik & Herzen',         'sub' => 'Hochzeit / Valentinstag'],
                    'geburtstag' => ['title' => 'Party & Konfetti',          'sub' => 'Geburtstag / Silvester'],
                    'standard'   => ['title' => 'Manufaktur-Funken',         'sub' => 'Standard-Animation'],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3">
                @foreach($manualEffectLabels as $effectKey => $effectMeta)
                    @php
                        $themeData = $backgroundThemes[$effectKey] ?? null;
                        $isEffectChecked = ($manualAnimKey === $effectKey) || ($manualAnimKey === '' && ($currentActiveAnimation['theme_key'] ?? '') === $effectKey);
                        $isExplicitlyPinned = ($manualAnimKey === $effectKey);
                    @endphp
                    @if($themeData)
                        <button
                            type="button"
                            wire:click="toggleManualAnimation('{{ $effectKey }}')"
                            class="p-3.5 rounded-2xl border text-left transition-all flex items-center gap-3 cursor-pointer group
                                {{ $isEffectChecked
                                    ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)] shadow-[0_0_18px_var(--theme-color-20)] ring-1 ring-[var(--theme-color-30)]'
                                    : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]'
                                }}"
                        >
                            <div class="w-5 h-5 rounded-md border-2 shrink-0 flex items-center justify-center transition-all
                                {{ $isEffectChecked
                                    ? 'bg-[var(--theme-color)] border-[var(--theme-color)] text-gray-900 shadow-sm'
                                    : 'bg-gray-100 dark:bg-gray-950 border-gray-300 dark:border-gray-700 text-transparent group-hover:border-[var(--theme-color-50)]'
                                }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-base leading-none">{{ $themeData['icon'] }}</span>
                                    <span class="text-xs font-bold truncate {{ $isEffectChecked ? 'text-[var(--theme-color)]' : 'text-gray-900 dark:text-white' }}">
                                        {{ $effectMeta['title'] }}
                                    </span>
                                </div>
                                <span class="block text-[9px] text-gray-500 truncate mt-0.5">
                                    {{ $isExplicitlyPinned ? '✓ Manuell fixiert' : $effectMeta['sub'] }}
                                </span>
                            </div>
                        </button>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Timeline Slider (Analog zu /admin/newsletter) --}}
        <div class="relative group/slider w-full mt-4" x-data="{
                container: null,
                init() { this.container = this.$refs.sliderContainer; },
                scroll(direction) {
                    const scrollVal = 340;
                    if (direction === 'left') this.container.scrollBy({ left: -scrollVal, behavior: 'smooth' });
                    else this.container.scrollBy({ left: scrollVal, behavior: 'smooth' });
                }
            }">
            <button type="button" @click.stop="scroll('left')" class="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/90 dark:bg-gray-800/90 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-full shadow-lg flex items-center justify-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:border-[var(--theme-color)] transition-all opacity-0 group-hover/slider:opacity-100 -translate-x-4 group-hover/slider:translate-x-2 duration-300 cursor-pointer">
                <x-heroicon-m-chevron-left class="w-6 h-6" />
            </button>
            <button type="button" @click.stop="scroll('right')" class="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/90 dark:bg-gray-800/90 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-full shadow-lg flex items-center justify-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:border-[var(--theme-color)] transition-all opacity-0 group-hover/slider:opacity-100 translate-x-4 group-hover/slider:-translate-x-2 duration-300 cursor-pointer">
                <x-heroicon-m-chevron-right class="w-6 h-6" />
            </button>

            <div x-ref="sliderContainer" class="flex gap-6 overflow-x-auto pb-6 pt-2 custom-scrollbar snap-x scroll-smooth relative z-10 w-full px-2">
                @php $foundActiveOrNext = false; @endphp
                @foreach($animationTimeline ?? [] as $animEvent)
                    @php
                        $isActiveNow = !empty($animEvent['is_active_now']);
                        $isPast = !$isActiveNow && $animEvent['end_date']->isPast() && !$animEvent['end_date']->isToday();
                        $isNext = $isActiveNow || (!$isPast && !$foundActiveOrNext);
                        if ($isNext) {
                            $foundActiveOrNext = true;
                        }
                    @endphp
                    <div class="relative group w-[290px] sm:w-[330px] shrink-0 snap-start flex flex-col">
                        <div class="relative transition-all duration-500 group-hover:-translate-y-1.5 flex-1">
                            <x-shop.timeline-card
                                :date="$animEvent['event_date']"
                                :title="$animEvent['title']"
                                :subtitle="$animEvent['subtitle']"
                                :event-key="$animEvent['event_key'] ?? null"
                                :event-name="$animEvent['event_name'] ?? null"
                                :state="$isPast ? 'past' : ($isNext ? 'next' : 'future')"
                                :is-active-now="$isActiveNow"
                                :active-end-date="$animEvent['end_date'] ?? null"
                                countdown-label="Aktivierung in"
                                type="animation"
                            />
                        </div>
                        <div class="mt-2 ml-2 px-3 py-2 rounded-2xl bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800/80 flex items-center justify-between gap-2 text-[9px] font-mono text-gray-500">
                            <span class="truncate" title="{{ $animEvent['video_path'] }}">
                                🎬 {{ basename($animEvent['video_path']) }}
                                @if(!empty($animEvent['video_kb']))
                                    <span class="text-[var(--theme-color)] font-bold">({{ $animEvent['video_kb'] }} KB)</span>
                                @endif
                            </span>
                            <a href="{{ $animEvent['video_url'] }}" target="_blank" class="text-[var(--theme-color)] hover:underline font-sans font-bold uppercase tracking-wider shrink-0">
                                Vorschau ↗
                            </a>
                        </div>
                    </div>
                @endforeach

                {{-- Fallback-Kachel: Standard Manufaktur Animation --}}
                @if(isset($backgroundThemes['standard']))
                    @php
                        $stdTheme = $backgroundThemes['standard'];
                        $stdDisk = storage_path('app/public/' . $stdTheme['video']);
                        $stdKb = is_file($stdDisk) ? (int) max(1, round(filesize($stdDisk) / 1024)) : null;
                    @endphp
                    <div class="relative group w-[290px] sm:w-[330px] shrink-0 snap-start flex flex-col">
                        <div class="relative transition-all duration-500 group-hover:-translate-y-1.5 flex-1">
                            <x-shop.timeline-card
                                :date="now()"
                                :title="$stdTheme['icon'] . ' ' . $stdTheme['label']"
                                :subtitle="'Immer verfügbarer Fallback • Funki: ' . $stdTheme['funki_outfit']"
                                event-key="standard_fallback"
                                event-name="Standard / Zwischenzeit-Fallback"
                                state="future"
                                countdown-label="Bereit als Fallback"
                                type="animation"
                            />
                        </div>
                        <div class="mt-2 ml-2 px-3 py-2 rounded-2xl bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800/80 flex items-center justify-between gap-2 text-[9px] font-mono text-gray-500">
                            <span class="truncate" title="storage/app/public/{{ $stdTheme['video'] }}">
                                🎬 {{ basename($stdTheme['video']) }}
                                @if($stdKb)
                                    <span class="text-[var(--theme-color)] font-bold">({{ $stdKb }} KB)</span>
                                @endif
                            </span>
                            <a href="{{ asset('storage/' . $stdTheme['video']) }}" target="_blank" class="text-[var(--theme-color)] hover:underline font-sans font-bold uppercase tracking-wider shrink-0">
                                Vorschau ↗
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
