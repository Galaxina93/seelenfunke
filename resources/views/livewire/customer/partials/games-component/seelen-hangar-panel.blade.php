@php
    $shipSkinsJs = [];
    foreach ($shipSkins as $k => $s) {
        $shipSkinsJs[$k] = [
            'key'               => $k,
            'name'              => $s['name'],
            'subtitle'          => $s['subtitle'],
            'category'          => $s['category'] ?? 'fighter',
            'category_label'    => $s['category_label'] ?? 'Gleiter & Jäger',
            'rarity'            => $s['rarity'] ?? 'common',
            'rarity_label'      => $s['rarity_label'] ?? 'Standard',
            'badge_icon'        => $s['badge_icon'] ?? '🚀',
            'price_sparks'      => (int) ($s['price_sparks'] ?? 0),
            'min_level'         => (int) ($s['min_level'] ?? 1),
            'vip_buyer_unlock'  => !empty($s['vip_buyer_unlock']),
            'glb_url'           => asset('shop/customer/gamification/images/' . ($s['glb_file'] ?? 'funki_rocket.glb')),
            'is_vertex_colored' => !empty($s['is_vertex_colored']),
            'laser_color_hex'   => $s['laser_color_hex'] ?? 0x38bdf8,
            'laser_color_css'   => $s['laser_color_css'] ?? '#38bdf8',
            'trail_color_hex'   => $s['trail_color_hex'] ?? 0x60a5fa,
            'stats'             => $s['stats'] ?? [],
            'perks'             => $s['perks'] ?? [],
        ];
    }

    $bUnlock = $battleshipUnlock ?? [
        'has_verified_order'    => false,
        'has_five_star_review'  => false,
        'has_all_fighters'      => false,
        'unlocked_fighters_cnt' => 1,
        'total_fighters_cnt'    => 6,
        'completed_premises'    => 0,
        'is_unlocked'           => false,
    ];
    $stationsPlaceholder = $spaceStationsPlaceholder ?? \App\Services\Gamification\GameConfig::getSpaceStationsPlaceholder();
    $mottosDetailsList = !empty($heroMottosDetails) ? $heroMottosDetails : \App\Services\Gamification\GameConfig::getHeroMottosDetails();
    $currentMottoDetail = $mottosDetailsList[$activeMottoKey ?? 'default'] ?? ($mottosDetailsList['default'] ?? []);
@endphp

<div x-data="seelenHangarShowroom(@js($shipSkinsJs), @js($activeShipSkinKey ?: 'default'), @js($activeHallAuraKey ?: 'default'))"
     x-init="initShowroom()"
     @ship-skin-changed.window="if ($event.detail && $event.detail.skin) { selectPreviewShip($event.detail.skin.key || 'default'); }"
     class="w-full text-left">

    {{-- HANGAR HEADER & GUTHABEN --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-amber-500/20 pb-4 mb-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-[10px] font-black uppercase tracking-widest mb-1.5">
                <span>🚀</span>
                <span>3D-Seelen-Hangar & Ruhmeshalle</span>
            </div>
            <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white">Flotte, Prestige-Auren & Motto</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Begutachte jedes Raumschiff & Schlachtschiff live in 3D, teste Ruhmes-Auren und aktiviere dein Piloten-Motto mit Moral-Buff!</p>
        </div>

        <div class="flex items-center gap-3 self-start sm:self-auto">
            <div class="bg-gray-100 dark:bg-gray-950 border border-amber-500/40 px-4 py-2 rounded-2xl flex items-center gap-2 shadow-inner">
                <span class="text-base">✨</span>
                <div>
                    <p class="text-[8px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 leading-none">Dein Guthaben</p>
                    <p class="text-sm font-black text-amber-600 dark:text-amber-400 leading-tight mt-0.5">{{ number_format($balance, 0, ',', '.') }} Funken</p>
                </div>
            </div>
            @if(!empty($showBackButton))
                <button type="button"
                        @click="window.dispatchEvent(new CustomEvent('close-hangar-panel'))"
                        class="group text-gray-700 dark:text-gray-200 hover:text-amber-700 dark:hover:text-amber-300 transition-all duration-300 bg-gray-100 dark:bg-gray-800/90 hover:bg-amber-500/15 dark:hover:bg-amber-500/20 px-4 py-2.5 rounded-xl text-xs uppercase font-black tracking-widest border border-gray-200 dark:border-gray-700 hover:border-amber-500/50 shadow-sm shrink-0 flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4 transition-transform duration-300 group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Zurück</span>
                </button>
            @endif
        </div>
    </div>

    {{-- SUB-NAVIGATION TABS --}}
    <div class="flex overflow-x-auto no-scrollbar sm:flex-wrap gap-1.5 sm:gap-2 pb-1 sm:pb-0 mb-5 sm:mb-6">
        <button type="button" @click="switchTab('ships')"
                :class="tab === 'ships' ? 'bg-amber-500 text-gray-950 border-amber-400 shadow-[0_0_20px_rgba(245,158,11,0.35)]' : 'bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:text-gray-900 dark:hover:text-white'"
                class="shrink-0 px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-black uppercase tracking-wider border transition-all flex items-center gap-1.5 sm:gap-2 cursor-pointer">
            <span>🚀</span>
            <span class="sm:hidden">3D-Flotte ({{ count($unlockedSkins) }}/{{ count($shipSkins) }})</span>
            <span class="hidden sm:inline">3D-Raketen & Charakter-Skins ({{ count($unlockedSkins) }}/{{ count($shipSkins) }})</span>
        </button>
        <button type="button" @click="switchTab('auras')"
                :class="tab === 'auras' ? 'bg-amber-500 text-gray-950 border-amber-400 shadow-[0_0_20px_rgba(245,158,11,0.35)]' : 'bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:text-gray-900 dark:hover:text-white'"
                class="shrink-0 px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-black uppercase tracking-wider border transition-all flex items-center gap-1.5 sm:gap-2 cursor-pointer">
            <span>🌟</span>
            <span>Ruhmes-Auren ({{ count($unlockedAuras) }}/{{ count($hallAuras) }})</span>
        </button>
        <button type="button" @click="switchTab('mottos')"
                :class="tab === 'mottos' ? 'bg-amber-500 text-gray-950 border-amber-400 shadow-[0_0_20px_rgba(245,158,11,0.35)]' : 'bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:text-gray-900 dark:hover:text-white'"
                class="shrink-0 px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-black uppercase tracking-wider border transition-all flex items-center gap-1.5 sm:gap-2 cursor-pointer">
            <span>💬</span>
            <span>Helden-Motto & Moral-Buff</span>
        </button>
    </div>

    {{-- TAB 1: 3D-HOLODECK-SHOWROOM + KATEGORIEFILTER + 3D-KACHELN --}}
    <div x-show="tab === 'ships'" x-transition.opacity class="space-y-6">

        {{-- LOGISCHER KATEGORIE- & ANSICHTSFILTER (1. GLEITER & JÄGER | 2. SCHLACHTSCHIFFE | 3. RAUMSTATIONEN) --}}
        <div class="rounded-2xl bg-gray-100/90 dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 p-2 sm:p-2.5 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-2">
            <div class="flex items-center gap-2 px-2 py-1">
                <span class="text-sm">🧭</span>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 leading-none">Flotten-Klassenfilter</p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Wähle zwischen wendigen Jägern, kolossalen Schlachtschiffen und orbitalen Raumstationen</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5">
                <button type="button"
                        @click="setShipCategory('fighter')"
                        :class="shipCategory === 'fighter' ? 'bg-gradient-to-r from-amber-500 to-yellow-400 text-gray-950 border-amber-300 shadow-[0_0_18px_rgba(245,158,11,0.35)]' : 'bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-amber-500/40'"
                        class="px-3.5 py-2.5 rounded-xl border text-left transition-all flex items-center justify-between gap-2 cursor-pointer">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-base shrink-0">🚀</span>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider truncate leading-tight">Gleiter & Jäger</p>
                            <p class="text-[9px] opacity-80 truncate">{{ $bUnlock['unlocked_fighters_cnt'] }}/6 freigeschaltet</p>
                        </div>
                    </div>
                    <span class="px-1.5 py-0.5 rounded-md text-[8px] font-black uppercase bg-black/10 dark:bg-white/10">Stufe I</span>
                </button>

                <button type="button"
                        @click="setShipCategory('battleship')"
                        :class="shipCategory === 'battleship' ? 'bg-gradient-to-r from-red-600 via-amber-500 to-yellow-400 text-gray-950 border-amber-300 shadow-[0_0_22px_rgba(239,68,68,0.4)]' : 'bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-red-500/50'"
                        class="px-3.5 py-2.5 rounded-xl border text-left transition-all flex items-center justify-between gap-2 cursor-pointer">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-base shrink-0">🚢</span>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider truncate leading-tight">Schlachtschiffe</p>
                            <p class="text-[9px] opacity-80 truncate">
                                @if($bUnlock['is_unlocked'])
                                    ✓ Freigeschaltet (inkl. Dark Perl)
                                @else
                                    🔒 {{ $bUnlock['completed_premises'] }}/3 Prämissen erfüllt
                                @endif
                            </p>
                        </div>
                    </div>
                    <span class="px-1.5 py-0.5 rounded-md text-[8px] font-black uppercase {{ $bUnlock['is_unlocked'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-400' }}">Stufe II</span>
                </button>

                <button type="button"
                        @click="setShipCategory('station')"
                        :class="shipCategory === 'station' ? 'bg-gradient-to-r from-indigo-500 to-cyan-400 text-gray-950 border-cyan-300 shadow-[0_0_18px_rgba(56,189,248,0.35)]' : 'bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-cyan-500/40'"
                        class="px-3.5 py-2.5 rounded-xl border text-left transition-all flex items-center justify-between gap-2 cursor-pointer">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-base shrink-0">🛰️</span>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider truncate leading-tight">Raumstationen</p>
                            <p class="text-[9px] opacity-80 truncate">3 Megastrukturen (Vorschau)</p>
                        </div>
                    </div>
                    <span class="px-1.5 py-0.5 rounded-md text-[8px] font-black uppercase bg-cyan-500/20 text-cyan-400">Stufe III</span>
                </button>
            </div>
        </div>

        {{-- SCHLACHTSCHIFF-FREISCHALTUNGS-PRÄMISSEN BANNER (SICHTBAR IN KATEGORIE SCHLACHTSCHIFFE) --}}
        <div x-show="shipCategory === 'battleship'" x-transition.opacity
             class="rounded-3xl border-2 {{ $bUnlock['is_unlocked'] ? 'border-emerald-500/50 bg-gradient-to-br from-emerald-950/40 via-gray-950 to-gray-900' : 'border-red-500/40 bg-gradient-to-br from-[#16070b] via-gray-950 to-[#090d16]' }} p-5 sm:p-6 text-white shadow-xl">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-4">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full {{ $bUnlock['is_unlocked'] ? 'bg-emerald-500/20 border-emerald-400/40 text-emerald-300' : 'bg-red-500/20 border-red-400/40 text-red-300' }} border text-[10px] font-black uppercase tracking-widest mb-2">
                        <span>{{ $bUnlock['is_unlocked'] ? '🔓' : '🔒' }}</span>
                        <span>{{ $bUnlock['is_unlocked'] ? 'Kommando-Freigabe erteilt: Schwere Schlachtschiff-Flotte aktiv!' : 'Geheime Kommando-Freigabe: 3 Prämissen für Schlachtschiffe' }}</span>
                    </div>
                    <h4 class="text-lg sm:text-xl font-serif font-bold text-white">
                        Große Schlachtschiffe & Elite-Dreadnought „Dark Perl“
                    </h4>
                    <p class="text-xs text-gray-300 mt-1 max-w-3xl">
                        Schlachtschiffe spielen im <strong class="text-amber-300">Funkenflug-Express auf einer größeren taktischen Kamera-Ansicht (weiter von oben)</strong>. In diesem Modus greifen <strong class="text-red-400">deutlich mehr feindliche Raumschiffe</strong> an – dafür verfügt dein Schlachtschiff über <strong class="text-emerald-300">5 bis 7 Rumpf-Herzen, 3- bis 5-fache Breitseiten-Salven und bis zu 260% Feuerkraft</strong>!
                    </p>
                </div>

                <div class="shrink-0 bg-black/50 border border-white/10 rounded-2xl px-4 py-3 text-center">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Freischaltungs-Status</p>
                    <p class="text-2xl font-black {{ $bUnlock['is_unlocked'] ? 'text-emerald-400' : 'text-amber-400' }} mt-0.5">
                        {{ $bUnlock['completed_premises'] }} / 3
                    </p>
                    <p class="text-[10px] text-gray-400">Prämissen erfüllt</p>
                </div>
            </div>

            {{-- DIE 3 PRÄMISSEN ALS CHECKLISTE --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                {{-- Prämisse 1: Verifizierter Kauf --}}
                <div class="rounded-2xl border p-3.5 flex items-start gap-3 {{ $bUnlock['has_verified_order'] ? 'bg-emerald-500/10 border-emerald-500/40' : 'bg-white/5 border-white/10' }}">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm font-black shrink-0 {{ $bUnlock['has_verified_order'] ? 'bg-emerald-500 text-gray-950' : 'bg-gray-800 text-gray-400' }}">
                        {{ $bUnlock['has_verified_order'] ? '✓' : '1' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-wider {{ $bUnlock['has_verified_order'] ? 'text-emerald-300' : 'text-white' }}">
                            1. Verifizierter Kauf
                        </p>
                        <p class="text-[11px] text-gray-300 mt-0.5">
                            Mindestens 1 abgeschlossene Bestellung im Seelenfunke-Shop getätigt.
                        </p>
                    </div>
                </div>

                {{-- Prämisse 2: Verifizierte 5-Sterne-Bewertung --}}
                <div class="rounded-2xl border p-3.5 flex items-start gap-3 {{ $bUnlock['has_five_star_review'] ? 'bg-emerald-500/10 border-emerald-500/40' : 'bg-white/5 border-white/10' }}">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm font-black shrink-0 {{ $bUnlock['has_five_star_review'] ? 'bg-emerald-500 text-gray-950' : 'bg-gray-800 text-gray-400' }}">
                        {{ $bUnlock['has_five_star_review'] ? '✓' : '2' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-wider {{ $bUnlock['has_five_star_review'] ? 'text-emerald-300' : 'text-white' }}">
                            2. Verifizierte 5★ Bewertung
                        </p>
                        <p class="text-[11px] text-gray-300 mt-0.5">
                            Mindestens 1 freigegebene 5-Sterne-Produktbewertung abgegeben.
                        </p>
                    </div>
                </div>

                {{-- Prämisse 3: Alle 6 Gleiter & Jäger freigeschaltet --}}
                <div class="rounded-2xl border p-3.5 flex items-start gap-3 {{ $bUnlock['has_all_fighters'] ? 'bg-emerald-500/10 border-emerald-500/40' : 'bg-white/5 border-white/10' }}">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm font-black shrink-0 {{ $bUnlock['has_all_fighters'] ? 'bg-emerald-500 text-gray-950' : 'bg-gray-800 text-gray-400' }}">
                        {{ $bUnlock['has_all_fighters'] ? '✓' : '3' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-wider {{ $bUnlock['has_all_fighters'] ? 'text-emerald-300' : 'text-white' }}">
                            3. Alle 6 Gleiter & Jäger ({{ $bUnlock['unlocked_fighters_cnt'] }}/{{ $bUnlock['total_fighters_cnt'] }})
                        </p>
                        <p class="text-[11px] text-gray-300 mt-0.5">
                            Schalte alle 6 Schiffe der Kategorie „Gleiter & Jäger“ im Hangar frei.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- INTERAKTIVE 60-FPS 3D-SHOWROOM-BÜHNE (FÜR GLEITER/JÄGER & SCHLACHTSCHIFFE) --}}
        <div x-show="shipCategory !== 'station'" x-ref="showroomBox"
             class="relative rounded-3xl border-2 border-amber-500/40 bg-[#060914] text-white overflow-hidden shadow-[0_0_50px_rgba(245,158,11,0.15)] grid grid-cols-1 lg:grid-cols-12">
            {{-- LINKS: 3D WEBGL TURNTABLE & FLUG-SIMULATION --}}
            <div class="lg:col-span-7 relative h-64 sm:h-80 lg:h-[24rem] flex flex-col justify-between overflow-hidden select-none border-b lg:border-b-0 lg:border-r border-amber-500/20"
                 style="touch-action: pan-y;"
                 @pointerdown="startDrag($event)"
                 @pointermove="onDrag($event)"
                 @pointerup="endDrag()"
                 @pointercancel="endDrag()"
                 @pointerleave="endDrag()">

                {{-- DYNAMISCHER HINTERGRUND-SCHIMMER JE NACH SELTENHEIT --}}
                <div class="absolute inset-0 pointer-events-none opacity-55 transition-all duration-500"
                     :style="`background: radial-gradient(circle at 50% 55%, ${currentShip.laser_color_css || '#38bdf8'}3d 0%, rgba(6,9,20,0) 72%);`"></div>

                {{-- 3D CANVAS CONTAINER --}}
                <div x-ref="hangarCanvasWrap" wire:ignore class="absolute inset-0 w-full h-full z-10 cursor-grab active:cursor-grabbing"></div>

                {{-- TOP OVERLAY: MODELL-NAME & NAVIGATION --}}
                <div class="relative z-20 p-3 sm:p-4 flex items-center justify-between gap-2 pointer-events-none">
                    <div class="flex items-center gap-2 sm:gap-2.5 bg-gray-950/85 backdrop-blur-md border border-white/10 px-2.5 sm:px-3.5 py-1.5 rounded-2xl min-w-0">
                        <span class="text-lg sm:text-xl shrink-0" x-text="currentShip.badge_icon">🚀</span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-amber-400" x-text="currentShip.category_label || 'Live 3D-Vorschau'"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                            </div>
                            <p class="text-xs sm:text-sm font-serif font-bold text-white leading-tight truncate" x-text="currentShip.name"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 pointer-events-auto shrink-0">
                        <button type="button" @click="cycleShip(-1)" class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gray-950/85 hover:bg-amber-500 hover:text-gray-950 text-gray-300 border border-white/15 flex items-center justify-center font-black transition-all active:scale-90 cursor-pointer" title="Vorheriges Schiff">
                            ◀
                        </button>
                        <button type="button" @click="cycleShip(1)" class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gray-950/85 hover:bg-amber-500 hover:text-gray-950 text-gray-300 border border-white/15 flex items-center justify-center font-black transition-all active:scale-90 cursor-pointer" title="Nächstes Schiff">
                            ▶
                        </button>
                    </div>
                </div>

                {{-- BOTTOM OVERLAY: INTERAKTIVE DEMO-BUTTONS (LASER, SCHILD, BOOST) --}}
                <div class="relative z-20 p-3 sm:p-4 flex flex-wrap items-center justify-between gap-2 bg-gradient-to-t from-black/80 via-black/30 to-transparent pointer-events-none">
                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-widest text-gray-300/90 flex items-center gap-1">
                        <span>🔄</span>
                        <span>360° drehbar • <span x-text="(currentShip.stats?.salvo_streams || 2) + 'x Salve'"></span></span>
                    </span>

                    <div class="flex items-center gap-1 sm:gap-1.5 pointer-events-auto">
                        <button type="button" @click="triggerLaserDemo()"
                                class="px-2 sm:px-2.5 py-1.5 rounded-xl bg-gray-900/90 hover:bg-cyan-500/20 border border-cyan-400/40 text-cyan-300 text-[9px] sm:text-[10px] font-black uppercase tracking-wider transition-all flex items-center gap-1 active:scale-95 cursor-pointer">
                            <span>🔫</span>
                            <span>Salve</span>
                        </button>
                        <button type="button" @click="toggleShieldDemo()"
                                :class="demoShieldActive ? 'bg-blue-500 text-white border-blue-300 shadow-[0_0_15px_rgba(59,130,246,0.6)]' : 'bg-gray-900/90 text-blue-300 border-blue-400/40 hover:bg-blue-500/20'"
                                class="px-2 sm:px-2.5 py-1.5 rounded-xl border text-[9px] sm:text-[10px] font-black uppercase tracking-wider transition-all flex items-center gap-1 active:scale-95 cursor-pointer">
                            <span>🛡️</span>
                            <span>Schild</span>
                        </button>
                        <button type="button" @click="triggerBoostSpin()"
                                class="px-2 sm:px-2.5 py-1.5 rounded-xl bg-gray-900/90 hover:bg-amber-500/20 border border-amber-400/40 text-amber-300 text-[9px] sm:text-[10px] font-black uppercase tracking-wider transition-all flex items-center gap-1 active:scale-95 cursor-pointer">
                            <span>🔥</span>
                            <span>Manöver</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- RECHTS: TELEMETRIE, EIGENSCHAFTEN & SOFORT-AUSWAHL --}}
            <div class="lg:col-span-5 p-5 sm:p-6 flex flex-col justify-between bg-gray-950/95">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border border-amber-400/40 bg-amber-500/15 text-amber-300" x-text="currentShip.rarity_label"></span>
                        <span class="text-[10px] font-bold text-gray-400" x-text="currentShip.subtitle"></span>
                    </div>

                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h4 class="text-xl font-serif font-bold text-white" x-text="currentShip.name"></h4>
                        <span class="px-2.5 py-1 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-[10px] font-black whitespace-nowrap"
                              x-text="(currentShip.stats?.max_hearts || 3) + ' ❤️ Rumpf'"></span>
                    </div>

                    {{-- ANIMIERTE TELEMETRIE-BALKEN --}}
                    <div class="space-y-2.5 mb-4">
                        <div>
                            <div class="flex justify-between text-[10px] font-black uppercase tracking-wider mb-1">
                                <span class="text-gray-400">💥 Feuerkraft & Salven-Strahlen</span>
                                <span class="text-rose-400" x-text="Math.round((currentShip.stats?.weapon_power_mult || 1) * 100) + '% Kraft • ' + (currentShip.stats?.salvo_streams || 2) + 'x Laser'"></span>
                            </div>
                            <div class="w-full h-2 bg-gray-900 rounded-full overflow-hidden border border-gray-800">
                                <div class="h-full bg-gradient-to-r from-rose-500 via-orange-500 to-amber-300 transition-all duration-500"
                                     :style="`width: ${Math.min(100, Math.round(((currentShip.stats?.weapon_power_mult || 1) / 2.6) * 100))}%`"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-[10px] font-black uppercase tracking-wider mb-1">
                                <span class="text-gray-400">🧲 Funken-Magnet-Radius</span>
                                <span class="text-amber-400" x-text="Math.round((currentShip.stats?.magnet_mult || 1) * 100) + '%'"></span>
                            </div>
                            <div class="w-full h-2 bg-gray-900 rounded-full overflow-hidden border border-gray-800">
                                <div class="h-full bg-gradient-to-r from-amber-500 to-yellow-300 transition-all duration-500"
                                     :style="`width: ${Math.min(100, Math.round(((currentShip.stats?.magnet_mult || 1) / 3.0) * 100))}%`"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-[10px] font-black uppercase tracking-wider mb-1">
                                <span class="text-gray-400">⚡ Skill-Dauer & Start-Schild</span>
                                <span class="text-cyan-400" x-text="'+' + Math.round(((currentShip.stats?.skill_duration_mult || 1) - 1) * 100) + '% Dauer / ' + (currentShip.stats?.start_shield || 0) + '% Schild'"></span>
                            </div>
                            <div class="w-full h-2 bg-gray-900 rounded-full overflow-hidden border border-gray-800">
                                <div class="h-full bg-gradient-to-r from-blue-500 to-cyan-300 transition-all duration-500"
                                     :style="`width: ${Math.min(100, 25 + Math.round(((currentShip.stats?.skill_duration_mult || 1) - 1) * 65) + Math.round((currentShip.stats?.start_shield || 0) * 0.25))}%`"></div>
                            </div>
                        </div>
                    </div>

                    <ul class="space-y-1.5 border-t border-gray-800 pt-3 mb-4">
                        <template x-for="(pText, pIdx) in (currentShip.perks || [])" :key="pIdx">
                            <li class="flex items-start gap-2 text-[11px] text-gray-300">
                                <span class="text-amber-400 font-bold leading-tight">✦</span>
                                <span x-text="pText"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="pt-3 border-t border-gray-800/80">
                    <p class="text-[10px] text-gray-400 text-center">
                        Jede Kachel unten zeigt dir <strong class="text-amber-400">live die 3D-Animation des jeweiligen Schiffes</strong>. Klicke auf eine Kachel für die große 360°-Inspektion!
                    </p>
                </div>
            </div>
        </div>

        {{-- KARTEN-GRID MIT LIVE-3D-ANIMATION IN JEDER KACHEL (GEFILTERT NACH GLEITER/JÄGER ODER SCHLACHTSCHIFFE) --}}
        <div x-show="shipCategory !== 'station'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($shipSkins as $sKey => $skin)
                @php
                    $sCategory = $skin['category'] ?? 'fighter';
                    $isUnlocked = in_array($sKey, $unlockedSkins, true);
                    $isActive = ($activeShipSkinKey === $sKey);
                    $canAfford = $balance >= ($skin['price_sparks'] ?? 0);
                    $isDarkPerl = ($sKey === 'dark_perl');
                    $battleshipLockedByPremises = ($sCategory === 'battleship' && !$bUnlock['is_unlocked'] && !$isUnlocked);
                    $levelLocked = (($skin['min_level'] ?? 1) > 1 && $level < (int) $skin['min_level'] && !$isUnlocked);

                    $rarityColors = match($skin['rarity'] ?? 'common') {
                        'rare'      => 'border-cyan-500/40 dark:border-cyan-400/40 from-cyan-500/10',
                        'epic'      => 'border-orange-500/40 dark:border-orange-400/40 from-orange-500/10',
                        'legendary' => 'border-amber-500/50 dark:border-amber-400/50 from-amber-500/15',
                        'mythic'    => 'border-fuchsia-500/50 dark:border-fuchsia-400/50 from-fuchsia-500/15',
                        'vip'       => $isDarkPerl
                                        ? 'border-red-500/90 dark:border-red-500/90 from-red-950/60 via-gray-950 to-black shadow-[0_0_35px_rgba(239,68,68,0.32)]'
                                        : 'border-yellow-400/80 dark:border-yellow-300/80 from-yellow-400/20 shadow-[0_0_25px_rgba(250,204,21,0.18)]',
                        default     => 'border-gray-200 dark:border-gray-800 from-blue-500/5',
                    };
                    $rarityBadge = match($skin['rarity'] ?? 'common') {
                        'rare'      => 'bg-cyan-500/20 text-cyan-700 dark:text-cyan-300 border-cyan-400/40',
                        'epic'      => 'bg-orange-500/20 text-orange-700 dark:text-orange-300 border-orange-400/40',
                        'legendary' => 'bg-amber-500/20 text-amber-700 dark:text-amber-300 border-amber-400/50',
                        'mythic'    => 'bg-fuchsia-500/20 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-400/50',
                        'vip'       => $isDarkPerl
                                        ? 'bg-gradient-to-r from-red-600 via-orange-500 to-amber-400 text-white border-red-400 font-black shadow-[0_0_15px_rgba(239,68,68,0.5)]'
                                        : 'bg-gradient-to-r from-amber-500 to-yellow-300 text-gray-950 border-yellow-200 font-black',
                        default     => 'bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-700',
                    };
                @endphp
                <div x-show="shipCategory === '{{ $sCategory }}'"
                     x-transition.opacity
                     @click="selectPreviewShip('{{ $sKey }}', true)"
                     :class="previewKey === '{{ $sKey }}' ? 'ring-2 ring-amber-400 shadow-[0_0_30px_rgba(245,158,11,0.28)] -translate-y-0.5' : 'hover:border-amber-400/60'"
                     class="cursor-pointer relative rounded-2xl border bg-gradient-to-b {{ $rarityColors }} to-transparent {{ $isDarkPerl ? 'bg-[#07070b] text-white' : 'bg-white dark:bg-gray-900/90' }} p-4 sm:p-5 flex flex-col justify-between transition-all">
                    <div>
                        {{-- TOP BADGES --}}
                        <div class="flex items-center justify-between gap-2 mb-2.5">
                            <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border {{ $rarityBadge }}">
                                {{ $skin['badge_icon'] }} {{ $skin['rarity_label'] }}
                            </span>
                            <button type="button" @click.stop="selectPreviewShip('{{ $sKey }}', true)"
                                    :class="previewKey === '{{ $sKey }}' ? 'bg-amber-500 text-gray-950 border-amber-400' : 'bg-gray-100 dark:bg-gray-800 text-amber-600 dark:text-amber-400 border-amber-500/30 hover:bg-amber-500 hover:text-gray-950'"
                                    class="px-2.5 py-1 rounded-xl text-[9px] font-black uppercase tracking-wider border transition-all flex items-center gap-1 cursor-pointer">
                                <span>👁️</span>
                                <span x-text="previewKey === '{{ $sKey }}' ? 'Im Großen Dock' : 'Groß-Dock'"></span>
                            </button>
                        </div>

                        {{-- LIVE 3D-ANIMATION DIREKT IN DER SCHIFFS-KACHEL (AUFGABE 9) --}}
                        <div class="relative h-36 sm:h-40 w-full rounded-2xl bg-[#050811] border {{ $isDarkPerl ? 'border-red-500/60 shadow-[inset_0_0_25px_rgba(220,38,38,0.35)]' : 'border-gray-800/90' }} overflow-hidden mb-3 shadow-inner">
                            <div class="absolute inset-0 pointer-events-none opacity-55"
                                 style="background: radial-gradient(circle at 50% 52%, {{ $skin['laser_color_css'] ?? '#38bdf8' }}42 0%, rgba(5,8,17,0) 74%);"></div>

                            <canvas data-ship-card-canvas="{{ $sKey }}" width="300" height="165" wire:ignore
                                    class="w-full h-full object-contain block relative z-10"></canvas>

                            <div class="absolute top-2 left-2.5 z-20 pointer-events-none flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full {{ $isDarkPerl ? 'bg-red-500' : 'bg-emerald-400' }} animate-ping"></span>
                                <span class="text-[8px] font-black uppercase tracking-widest {{ $isDarkPerl ? 'text-red-400' : 'text-gray-300' }}">3D Live-Modell</span>
                            </div>

                            <div class="absolute bottom-2 left-2.5 right-2.5 z-20 flex items-center justify-between pointer-events-none">
                                <span class="px-2 py-0.5 rounded-md bg-gray-950/85 border border-white/10 text-[8px] font-black uppercase tracking-wider text-amber-300">
                                    {{ $skin['stats']['max_hearts'] ?? 3 }} ❤️ • {{ $skin['stats']['salvo_streams'] ?? 2 }}x Salve
                                </span>
                                <span class="px-2 py-0.5 rounded-md bg-gray-950/85 border border-white/10 text-[8px] font-black uppercase tracking-wider text-cyan-300">
                                    {{ (int) round(($skin['stats']['weapon_power_mult'] ?? 1) * 100) }}% Feuerkraft
                                </span>
                            </div>
                        </div>

                        <h4 class="text-base font-serif font-bold {{ $isDarkPerl ? 'text-red-50' : 'text-gray-900 dark:text-white' }} flex items-center gap-2">
                            <span>{{ $skin['name'] }}</span>
                            @if($isActive)
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                            @endif
                        </h4>
                        <p class="text-[11px] {{ $isDarkPerl ? 'text-red-200/80' : 'text-gray-500 dark:text-gray-400' }} mt-0.5 mb-3">{{ $skin['subtitle'] }}</p>

                        {{-- VORTEILE & EIGENSCHAFTEN --}}
                        <ul class="space-y-1.5 mb-4 border-t {{ $isDarkPerl ? 'border-red-900/50' : 'border-gray-200/70 dark:border-gray-800/80' }} pt-3">
                            @foreach($skin['perks'] as $perk)
                                <li class="flex items-start gap-2 text-[11px] {{ $isDarkPerl ? 'text-gray-200' : 'text-gray-700 dark:text-gray-300' }}">
                                    <span class="{{ $isDarkPerl ? 'text-red-400' : 'text-amber-500' }} font-bold leading-tight">✦</span>
                                    <span>{{ $perk }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="pt-2 border-t {{ $isDarkPerl ? 'border-red-900/50' : 'border-gray-200/60 dark:border-gray-800/60' }} flex flex-col gap-2" @click.stop>
                        @if($isActive)
                            <div class="w-full py-2.5 rounded-xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-500 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest text-center">
                                ✓ Aktuell Ausgerüstet
                            </div>
                        @elseif($isUnlocked)
                            <button type="button" wire:click="buyOrEquipSkin('{{ $sKey }}')" @click="selectPreviewShip('{{ $sKey }}')"
                                    class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-950 text-[10px] font-black uppercase tracking-widest transition-all shadow-sm cursor-pointer">
                                Jetzt Ausrüsten
                            </button>
                        @elseif($battleshipLockedByPremises)
                            <div class="w-full py-2.5 px-3 rounded-xl bg-red-500/15 border border-red-500/40 text-red-500 dark:text-red-300 text-[9px] font-black uppercase tracking-wider text-center">
                                🔒 Erfülle zuerst alle 3 Schlachtschiff-Prämissen ({{ $bUnlock['completed_premises'] }}/3)
                            </div>
                            <p class="text-[9px] text-center text-gray-500 dark:text-gray-400 font-bold">
                                Preis nach Freigabe: {{ number_format($skin['price_sparks'], 0, ',', '.') }} ✨ @if(($skin['min_level'] ?? 1) > 1) • Ab Level {{ $skin['min_level'] }} @endif
                            </p>
                        @elseif($levelLocked)
                            <div class="w-full py-2.5 px-3 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-[9px] font-black uppercase tracking-wider text-center">
                                🔒 Benötigt Prestige-Level {{ $skin['min_level'] }} (Aktuell Lv. {{ $level }})
                            </div>
                        @else
                            <button type="button" wire:click="buyOrEquipSkin('{{ $sKey }}')" @click="selectPreviewShip('{{ $sKey }}')"
                                    @if(!$canAfford) disabled @endif
                                    class="w-full py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-1.5 {{ $canAfford ? ($isDarkPerl ? 'bg-gradient-to-r from-red-600 via-orange-500 to-amber-400 hover:from-red-500 hover:to-amber-300 text-white shadow-[0_0_20px_rgba(239,68,68,0.5)] cursor-pointer' : 'bg-amber-500 hover:bg-amber-400 text-gray-950 shadow-md cursor-pointer') : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 border border-gray-200 dark:border-gray-700 cursor-not-allowed' }}">
                                <span>Freischalten für {{ number_format($skin['price_sparks'], 0, ',', '.') }} ✨</span>
                            </button>
                            @if(!empty($skin['vip_buyer_unlock']) && !$isVipBuyer && $sCategory === 'fighter')
                                <a href="{{ url('/') }}" class="w-full py-2 rounded-xl bg-gradient-to-r from-amber-500/20 to-yellow-500/20 hover:from-amber-500/30 hover:to-yellow-500/30 border border-yellow-400/50 text-amber-700 dark:text-yellow-300 text-[9px] font-black uppercase tracking-wider text-center transition-all">
                                    🎁 Oder gratis bei 1 Shop-Bestellung!
                                </a>
                            @elseif(($skin['min_level'] ?? 1) > 1 && $sCategory === 'fighter')
                                <p class="text-[9px] text-center text-fuchsia-600 dark:text-fuchsia-400 font-bold uppercase tracking-wider">
                                    ✨ Ab Prestige-Level {{ $skin['min_level'] }} automatisch gratis!
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- DRITTE KATEGORIE: RAUMSTATIONEN (PLATZHALTER / VORSCHAU — AUFGABE 9) --}}
        <div x-show="shipCategory === 'station'" x-cloak x-transition.opacity class="space-y-5">
            <div class="rounded-3xl border-2 border-cyan-500/40 bg-gradient-to-br from-[#061320] via-gray-950 to-[#090d16] p-6 text-white shadow-xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-300 text-[10px] font-black uppercase tracking-widest mb-2">
                    <span>🛰️</span>
                    <span>Flotten-Erweiterung Stufe III • Raumstationen (Konzept-Vorschau)</span>
                </div>
                <h4 class="text-xl sm:text-2xl font-serif font-bold text-white">Orbitale Raumstationen & Kommandobasen</h4>
                <p class="text-xs sm:text-sm text-gray-300 mt-1 max-w-3xl">
                    Nach den wendigen <strong class="text-amber-300">Gleitern & Jägern (Stufe I)</strong> und den schweren <strong class="text-red-400">Schlachtschiffen (Stufe II)</strong> erwarten dich in der dritten großen Ausbaustufe gigantische <strong class="text-cyan-300">Raumstationen</strong> als mobile Heimatbasen mit automatischen Verteidigungsringen und passiven Funken-Generatoren.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($stationsPlaceholder as $stKey => $station)
                    <div class="rounded-2xl border border-cyan-500/30 bg-white dark:bg-gray-900/90 p-5 flex flex-col justify-between relative overflow-hidden">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/15 border border-cyan-400/40 text-cyan-700 dark:text-cyan-300 text-[9px] font-black uppercase tracking-widest">
                                    {{ $station['rarity_label'] }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-600 dark:text-amber-400 text-[9px] font-black uppercase tracking-wider">
                                    {{ $station['eta_badge'] }}
                                </span>
                            </div>

                            <div class="h-32 rounded-2xl bg-[#050b14] border border-cyan-500/20 flex flex-col items-center justify-center mb-3 relative overflow-hidden">
                                <div class="w-20 h-20 rounded-full border border-dashed border-cyan-400/40 animate-spin flex items-center justify-center" style="animation-duration: 14s;">
                                    <div class="w-12 h-12 rounded-full border border-amber-400/40"></div>
                                </div>
                                <span class="text-4xl absolute">{{ $station['badge_icon'] }}</span>
                            </div>

                            <h5 class="text-base font-serif font-bold text-gray-900 dark:text-white">{{ $station['name'] }}</h5>
                            <p class="text-[11px] font-bold text-cyan-600 dark:text-cyan-400 mt-0.5 mb-2">{{ $station['subtitle'] }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4">{{ $station['description'] }}</p>
                        </div>

                        <div class="w-full py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-[10px] font-black uppercase tracking-widest text-center">
                            🔒 Platzhalter • Kommt in Update Phase III
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- TAB 2: RUHMES-AUREN FÜR DIE HALLE DER LEGENDEN (INKL. INTERAKTIVER LIVE-SHOWROOM-VORSCHAU & VIP ANIMATION — AUFGABE 1) --}}
    <div x-show="tab === 'auras'" x-cloak x-transition.opacity class="space-y-6">

        {{-- INTERAKTIVE RUHMES-AUREN LIVE-VORSCHAU-BÜHNE --}}
        <div class="rounded-3xl border-2 border-amber-500/40 bg-[#070a14] text-white p-5 sm:p-6 shadow-[0_0_45px_rgba(245,158,11,0.15)]">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 border-b border-white/10 pb-3">
                <div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-amber-400 block">🌟 Live-Effekt-Simulator</span>
                    <h4 class="text-lg sm:text-xl font-serif font-bold text-white">Ruhmes-Auren Vorschau (Rangliste & Helden-Visitenkarte)</h4>
                    <p class="text-xs text-gray-400">Klicke auf eine beliebige Aura, um ihren animierten Leuchteffekt hier live an deinem Profil zu erleben!</p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($hallAuras as $aKey => $aura)
                        <button type="button"
                                @click="previewAuraKey = '{{ $aKey }}'"
                                :class="previewAuraKey === '{{ $aKey }}' ? 'bg-amber-500 text-gray-950 border-amber-300 shadow-[0_0_15px_rgba(245,158,11,0.4)]' : 'bg-gray-900 text-gray-300 border-white/10 hover:border-amber-400/50'"
                                class="px-2.5 py-1.5 rounded-xl border text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                            {{ $aura['badge_icon'] }} {{ $aura['name'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- DYNAMISCHE BÜHNE FÜR JEDE AURA --}}
            @foreach($hallAuras as $aKey => $aura)
                <div x-show="previewAuraKey === '{{ $aKey }}'" x-transition.opacity class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-300">
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black border {{ $aura['badge_class'] }}">
                                {{ $aura['badge_icon'] }} {{ $aura['name'] }}
                            </span>
                            <span class="text-gray-400">{{ $aura['subtitle'] }}</span>
                        </div>
                        @if($aKey === 'manufaktur_royal')
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/50 text-emerald-300 text-[9px] font-black uppercase tracking-widest">
                                👑 Exklusive VIP-Hologramm-Animation
                            </span>
                        @endif
                    </div>

                    {{-- SIMULIERTE HALLE DER LEGENDEN ZEILE IN VOLLER PRACHT --}}
                    <div class="rounded-2xl border p-4 sm:p-5 transition-all {{ $aura['row_class'] ?: 'bg-gray-900/90 border-gray-800' }}">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
                            <div class="flex items-center gap-3.5">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-300 via-amber-500 to-yellow-600 text-gray-950 font-black flex items-center justify-center shadow-md shrink-0">
                                    1
                                </div>
                                <div class="relative w-12 h-12 rounded-2xl overflow-hidden border-2 border-amber-400 shadow-md shrink-0 bg-gray-900">
                                    <img src="{{ $imagePath }}" alt="Avatar" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-bold text-base text-white">{{ auth('customer')->user()?->first_name ?? 'Dein Held' }}</span>
                                        <span class="text-[9px] bg-amber-500 text-gray-950 px-2 py-0.5 rounded-full font-black uppercase tracking-widest">Du</span>
                                        <span class="text-sm" title="Aktives Raumschiff">{{ $activeShipSkin['badge_icon'] ?? '🚀' }}</span>
                                        @if(!empty($aura['badge']))
                                            <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black border {{ $aura['badge_class'] }}">
                                                {{ $aura['badge_icon'] }} {{ $aura['badge'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] italic text-amber-200/90 mt-0.5">„{{ $heroMottos[$activeMottoKey] ?? 'Jeder Funke trägt eine Seele.' }}“</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 self-end sm:self-center">
                                <div class="text-right">
                                    <span class="text-[9px] uppercase tracking-widest text-gray-300 block">Seelen-Stufe</span>
                                    <span class="text-sm font-black text-amber-300">Level {{ $level }}</span>
                                </div>
                                <div class="px-3.5 py-2 rounded-xl bg-black/50 border border-amber-400/40 text-amber-300 font-mono font-black text-sm">
                                    {{ number_format(max(1250, $balance), 0, ',', '.') }} ✨
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400">{{ $aura['description'] ?? $aura['subtitle'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- KARTEN-GRID ALLER RUHMES-AUREN MIT ANIMIERTER VORSCHAU IN JEDER KACHEL --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($hallAuras as $aKey => $aura)
                @php
                    $isUnlocked = in_array($aKey, $unlockedAuras, true);
                    $isActive = ($activeHallAuraKey === $aKey);
                    $canAfford = $balance >= ($aura['price_sparks'] ?? 0);
                    $isVipAura = ($aKey === 'manufaktur_royal');
                @endphp
                <div @click="previewAuraKey = '{{ $aKey }}'"
                     class="cursor-pointer rounded-2xl border {{ $isVipAura ? 'border-amber-400/80 dark:border-amber-400/80 bg-gradient-to-br from-amber-950/30 via-gray-900 to-emerald-950/30 shadow-[0_0_30px_rgba(245,158,11,0.2)]' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/90' }} p-5 flex flex-col justify-between gap-4 transition-all {{ $isActive ? 'ring-2 ring-amber-400' : 'hover:border-amber-400/60' }}">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="px-3 py-1 rounded-full text-[10px] font-bold border {{ $aura['badge_class'] }}">
                                {{ $aura['badge_icon'] }} {{ $aura['name'] }}
                            </span>
                            <div class="flex items-center gap-2">
                                @if($isVipAura)
                                    <span class="px-2 py-0.5 rounded-full bg-amber-500/20 border border-amber-400/50 text-amber-400 text-[9px] font-black uppercase tracking-wider">VIP Krone</span>
                                @endif
                                @if($isActive)
                                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-500">✓ Aktiv</span>
                                @endif
                            </div>
                        </div>
                        <p class="text-xs font-bold {{ $isVipAura ? 'text-amber-200' : 'text-gray-700 dark:text-gray-300' }} mb-1">{{ $aura['subtitle'] }}</p>
                        <p class="text-[11px] {{ $isVipAura ? 'text-gray-300' : 'text-gray-500 dark:text-gray-400' }} mb-3">{{ $aura['description'] ?? '' }}</p>

                        {{-- ANIMIERTE LIVE-VORSCHAU DER RANGLISTEN-ZEILE DIREKT IN DER KACHEL --}}
                        <div class="rounded-xl border border-gray-300 dark:border-gray-800 p-3.5 {{ $aura['row_class'] ?: 'bg-gray-50 dark:bg-gray-950' }}">
                            <div class="flex items-center justify-between text-xs relative z-10">
                                <div class="flex items-center gap-2.5 font-bold text-gray-900 dark:text-white">
                                    <span class="w-6 h-6 rounded-lg bg-amber-500 text-gray-950 font-black text-[11px] flex items-center justify-center">#1</span>
                                    <img src="{{ $imagePath }}" alt="" class="w-7 h-7 rounded-lg object-cover border border-amber-400/60">
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span>{{ auth('customer')->user()?->first_name ?? 'Dein Name' }}</span>
                                            <span class="text-sm">{{ $activeShipSkin['badge_icon'] ?? '🚀' }}</span>
                                            @if(!empty($aura['badge']))
                                                <span class="text-[8px] px-2 py-0.5 rounded-full font-black border {{ $aura['badge_class'] }}">
                                                    {{ $aura['badge_icon'] }} {{ $aura['badge'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[9px] font-normal opacity-80 italic">Live-Animations-Vorschau</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-300">Lv. {{ $level }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2" @click.stop>
                        @if($isActive)
                            <div class="w-full py-2 rounded-xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-600 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest text-center">
                                ✓ In der Halle der Legenden aktiv
                            </div>
                        @elseif($isUnlocked)
                            <button type="button" wire:click="buyOrEquipAura('{{ $aKey }}')"
                                    class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-950 text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer">
                                Aura Aktivieren
                            </button>
                        @else
                            <button type="button" wire:click="buyOrEquipAura('{{ $aKey }}')"
                                    @if(!$canAfford) disabled @endif
                                    class="w-full py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $canAfford ? 'bg-amber-500 hover:bg-amber-400 text-gray-950 shadow-md cursor-pointer' : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 border border-gray-200 dark:border-gray-700 cursor-not-allowed' }}">
                                Freischalten für {{ number_format($aura['price_sparks'], 0, ',', '.') }} ✨
                            </button>
                            @if(!empty($aura['vip_buyer_unlock']) && !$isVipBuyer)
                                <p class="text-[9px] text-center text-amber-600 dark:text-yellow-400 font-bold uppercase tracking-wider">
                                    👑 Automatisch gratis ab deiner 1. Shop-Bestellung!
                                </p>
                            @elseif(($aura['min_level'] ?? 1) > 1)
                                <p class="text-[9px] text-center text-cyan-600 dark:text-cyan-400 font-bold uppercase tracking-wider">
                                    ✨ Automatisch gratis ab Level {{ $aura['min_level'] }}!
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- TAB 3: HELDEN-MOTTO & MORAL-BUFF (AUFGABE 2: KLARER SINN, LIVE-VORSCHAU & SPIEL-BONUS) --}}
    <div x-show="tab === 'mottos'" x-cloak x-transition.opacity class="space-y-5">

        {{-- ERKLÄRUNGS- & LIVE-VORSCHAU-BANNER: WAS BRINGT DAS HELDEN-MOTTO? --}}
        <div class="rounded-3xl border-2 border-amber-500/40 bg-gradient-to-br from-gray-950 via-[#0b1020] to-gray-900 text-white p-5 sm:p-6 shadow-xl">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-center">
                <div class="lg:col-span-7 space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/20 border border-amber-400/40 text-amber-300 text-[10px] font-black uppercase tracking-widest">
                        <span>💬</span>
                        <span>Piloten-Kodex & Aktiver Moral-Buff</span>
                    </div>
                    <h4 class="text-lg sm:text-xl font-serif font-bold text-white">Was bringt dir dein Helden-Motto?</h4>
                    <p class="text-xs text-gray-300 leading-relaxed">
                        Dein Helden-Motto ist weit mehr als nur ein Spruch – es ist dein <strong class="text-amber-300">persönlicher Kampfruf im Cockpit</strong>, dein <strong class="text-cyan-300">Aushängeschild in der Halle der Legenden</strong> und verleiht deinem Schiff im <strong class="text-emerald-300">Funkenflug-Express einen aktiven Moral-Buff</strong>:
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2">
                        <div class="rounded-xl bg-white/5 border border-white/10 p-2.5">
                            <p class="text-[10px] font-black uppercase text-amber-400">1. Spieler-Cockpit</p>
                            <p class="text-[11px] text-gray-300 mt-0.5">Erscheint prominent unter deinem Helden-Namen im Orbital-Cockpit.</p>
                        </div>
                        <div class="rounded-xl bg-white/5 border border-white/10 p-2.5">
                            <p class="text-[10px] font-black uppercase text-cyan-400">2. Helden-Visitenkarte</p>
                            <p class="text-[11px] text-gray-300 mt-0.5">Wird jedem Spieler angezeigt, der dein Profil in der Rangliste inspiziert.</p>
                        </div>
                        <div class="rounded-xl bg-emerald-500/10 border border-emerald-400/30 p-2.5">
                            <p class="text-[10px] font-black uppercase text-emerald-300">3. Aktiver Spiel-Buff</p>
                            <p class="text-[11px] text-gray-200 mt-0.5">Gewährt im Funkenflug-Express einen dauerhaften passiven Moral-Vorteil!</p>
                        </div>
                    </div>
                </div>

                {{-- LIVE-VORSCHAU DES AKTUELLEN MOTTOS & MORAL-BUFFS --}}
                <div class="lg:col-span-5 rounded-2xl bg-black/60 border border-amber-500/40 p-4 flex flex-col justify-between gap-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[9px] font-black uppercase tracking-widest text-amber-400">Aktives Piloten-Motto</span>
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-300 text-[9px] font-black uppercase">
                            {{ $currentMottoDetail['buff_label'] ?? '+10% Magnet-Reichweite' }}
                        </span>
                    </div>
                    <div class="p-3 rounded-xl bg-gray-900/90 border border-white/10">
                        <p class="text-xs font-black text-amber-300 flex items-center gap-1.5">
                            <span>{{ $currentMottoDetail['icon'] ?? '✨' }}</span>
                            <span>{{ $currentMottoDetail['title'] ?? 'Funken-Wächter' }}</span>
                        </p>
                        <p class="text-sm italic font-serif text-white mt-1">„{{ $heroMottos[$activeMottoKey] ?? 'Jeder Funke trägt eine Seele.' }}“</p>
                    </div>
                    <p class="text-[11px] text-emerald-300/90 flex items-center gap-1.5">
                        <span>⚡</span>
                        <span><strong>Moral-Effekt im Spiel:</strong> {{ $currentMottoDetail['buff_description'] ?? 'Erhöht deinen Funken-Magneten im Spiel.' }}</span>
                    </p>
                </div>
            </div>
        </div>

        {{-- AUSWAHL-KACHELN MIT MORAL-BUFF ANZEIGE --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            @foreach($mottosDetailsList as $mKey => $mInfo)
                @php $isSelected = ($activeMottoKey === $mKey); @endphp
                <button type="button" wire:click="selectHeroMotto('{{ $mKey }}')"
                        class="p-4 sm:p-5 rounded-2xl border text-left transition-all flex flex-col justify-between gap-3 cursor-pointer {{ $isSelected ? 'bg-amber-500/15 border-amber-400 ring-2 ring-amber-400/40 shadow-md' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 hover:border-amber-500/50' }}">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                <span>{{ $mInfo['icon'] ?? '💬' }}</span>
                                <span>{{ $mInfo['title'] ?? 'Helden-Motto' }}</span>
                            </span>
                            @if($isSelected)
                                <span class="shrink-0 px-2.5 py-0.5 rounded-full bg-amber-500 text-gray-950 text-[9px] font-black uppercase tracking-wider">✓ Aktiv</span>
                            @endif
                        </div>
                        <p class="text-xs sm:text-sm italic font-serif font-semibold text-gray-900 dark:text-white mb-2">„{{ $mInfo['text'] }}“</p>
                    </div>

                    <div class="pt-2.5 border-t border-gray-200/70 dark:border-gray-800 flex items-center justify-between gap-2">
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase tracking-wider">
                            ⚡ {{ $mInfo['buff_label'] }}
                        </span>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 truncate">{{ $mInfo['buff_description'] }}</span>
                    </div>
                </button>
            @endforeach
        </div>
    </div>
</div>
@once
<script>
window.SeelenfunkeShipAnimator = window.SeelenfunkeShipAnimator || {
    enhanceShipModel(THREE, key, rawScene) {
        if (!THREE || !rawScene || !key || key === 'default') return null;
        if (rawScene.userData && rawScene.userData.sfAnimRig) return rawScene.userData.sfAnimRig;

        const rig = {
            key: key,
            hullMats: [],
            thrusters: [],
            parts: {}
        };

        // 1. Upgrade vertex-colored hull to high-contrast 3D PBR material with subtle emissive pulse
        const emissiveColorMap = {
            crystal_fighter:   0x0d9488,
            slate_phantom:     0xc2410c,
            gold_phoenix:      0xb45309,
            funki_comet:       0x86198f,
            royal_dreadnought: 0xa16207,
            aegis_titan:       0x0284c7,
            valkyrie_prime:    0xb91c1c,
            leviathan_frost:   0x0891b2,
            hyperion_sun:      0xd97706,
            celestial_ark:     0x7e22ce,
            dark_perl:         0xdc2626
        };
        const isDarkPerl = (key === 'dark_perl');
        const hullMat = new THREE.MeshStandardMaterial({
            vertexColors: true,
            metalness: isDarkPerl ? 0.88 : (key === 'crystal_fighter' ? 0.55 : (key === 'slate_phantom' ? 0.65 : 0.58)),
            roughness: isDarkPerl ? 0.16 : (key === 'crystal_fighter' ? 0.18 : 0.25),
            emissive: new THREE.Color(emissiveColorMap[key] || 0x1e293b),
            emissiveIntensity: isDarkPerl ? 0.34 : 0.18
        });
        rawScene.traverse((ch) => {
            if (ch.isMesh) {
                ch.material = hullMat;
                ch.frustumCulled = false;
            }
        });
        rig.hullMats.push(hullMat);

        const animGroup = new THREE.Group();
        animGroup.name = 'sf_ship_anim_group_' + key;
        rawScene.add(animGroup);

        // Helper: Dual-layer glowing plasma thruster plume (points toward -Y tail)
        const makeThrusterPlume = (x, y, z, radius, len, outerHex, innerHex) => {
            const g = new THREE.Group();
            g.position.set(x, y, z);
            const outerGeo = new THREE.ConeGeometry(radius, len, 8);
            outerGeo.translate(0, -len * 0.5, 0);
            outerGeo.rotateZ(Math.PI);
            const outerMat = new THREE.MeshBasicMaterial({
                color: outerHex,
                transparent: true,
                opacity: 0.78,
                depthWrite: false,
                blending: THREE.AdditiveBlending
            });
            const innerGeo = new THREE.ConeGeometry(radius * 0.55, len * 0.72, 6);
            innerGeo.translate(0, -len * 0.36, 0);
            innerGeo.rotateZ(Math.PI);
            const innerMat = new THREE.MeshBasicMaterial({
                color: innerHex,
                transparent: true,
                opacity: 0.95,
                depthWrite: false,
                blending: THREE.AdditiveBlending
            });
            g.add(new THREE.Mesh(outerGeo, outerMat), new THREE.Mesh(innerGeo, innerMat));
            animGroup.add(g);
            rig.thrusters.push(g);
            return g;
        };

        // =========================================================================
        // 1. SAPHIR-KRISTALLJÄGER (crystal_fighter)
        // =========================================================================
        if (key === 'crystal_fighter') {
            const shardMat = new THREE.MeshStandardMaterial({
                color: 0x2dd4bf,
                emissive: 0x0ea5e9,
                emissiveIntensity: 0.65,
                metalness: 0.7,
                roughness: 0.12
            });
            const glowWireMat = new THREE.MeshBasicMaterial({
                color: 0x99f6e4,
                wireframe: true,
                transparent: true,
                opacity: 0.55,
                blending: THREE.AdditiveBlending
            });
            const shardGeo = new THREE.OctahedronGeometry(0.24, 0);
            shardGeo.scale(0.65, 1.85, 0.55);

            const shardConfigs = [
                { x: -1.65, y: -0.15, z: 0.22, phase: 0.0,  tilt: -0.25 },
                { x:  1.65, y: -0.15, z: 0.22, phase: Math.PI, tilt: 0.25 },
                { x: -2.32, y: -0.68, z: 0.12, phase: 1.4,  tilt: -0.42 },
                { x:  2.32, y: -0.68, z: 0.12, phase: 4.5,  tilt: 0.42 }
            ];
            rig.parts.shards = shardConfigs.map(cfg => {
                const holder = new THREE.Group();
                holder.position.set(cfg.x, cfg.y, cfg.z);
                const mesh = new THREE.Mesh(shardGeo, shardMat);
                const wire = new THREE.Mesh(shardGeo, glowWireMat);
                wire.scale.setScalar(1.12);
                holder.add(mesh, wire);
                holder.userData = cfg;
                animGroup.add(holder);
                return holder;
            });

            const prismHub = new THREE.Group();
            prismHub.position.set(0, -0.12, 0.58);
            const ringMat = new THREE.MeshBasicMaterial({
                color: 0x5eead4,
                transparent: true,
                opacity: 0.85,
                side: THREE.DoubleSide,
                blending: THREE.AdditiveBlending
            });
            const ring1 = new THREE.Mesh(new THREE.TorusGeometry(0.42, 0.032, 8, 24), ringMat);
            const ring2 = new THREE.Mesh(new THREE.TorusGeometry(0.30, 0.026, 8, 20), ringMat);
            const coreGem = new THREE.Mesh(new THREE.OctahedronGeometry(0.16, 0), shardMat);
            prismHub.add(ring1, ring2, coreGem);
            animGroup.add(prismHub);
            rig.parts.prismRing1 = ring1;
            rig.parts.prismRing2 = ring2;
            rig.parts.prismCore = coreGem;

            makeThrusterPlume(-0.48, -1.72, 0.02, 0.22, 1.15, 0x14b8a6, 0xccfbf1);
            makeThrusterPlume( 0.48, -1.72, 0.02, 0.22, 1.15, 0x14b8a6, 0xccfbf1);
        }

        // =========================================================================
        // 2. SCHIEFER-PHANTOM (slate_phantom)
        // =========================================================================
        else if (key === 'slate_phantom') {
            const bladeMat = new THREE.MeshStandardMaterial({
                color: 0x1e293b,
                emissive: 0xea580c,
                emissiveIntensity: 0.42,
                metalness: 0.82,
                roughness: 0.22
            });
            const edgeMat = new THREE.MeshBasicMaterial({
                color: 0xf97316,
                transparent: true,
                opacity: 0.9,
                blending: THREE.AdditiveBlending
            });

            const buildStealthBlade = (side) => {
                const pivot = new THREE.Group();
                pivot.position.set(side * 1.05, 0.05, 0.04);
                const bladeGeo = new THREE.ConeGeometry(0.22, 1.55, 4);
                bladeGeo.scale(1.2, 1.0, 0.22);
                bladeGeo.translate(0, 0.65, 0);
                bladeGeo.rotateZ(side * -0.58);
                const bladeMesh = new THREE.Mesh(bladeGeo, bladeMat);

                const edgeGeo = new THREE.CylinderGeometry(0.025, 0.025, 1.38, 6);
                edgeGeo.translate(side * 0.42, 0.58, 0.04);
                edgeGeo.rotateZ(side * -0.52);
                const edgeMesh = new THREE.Mesh(edgeGeo, edgeMat);

                pivot.add(bladeMesh, edgeMesh);
                animGroup.add(pivot);
                return pivot;
            };
            rig.parts.leftBlade = buildStealthBlade(-1);
            rig.parts.rightBlade = buildStealthBlade(1);

            const rotorMat = new THREE.MeshBasicMaterial({
                color: 0xfbbf24,
                transparent: true,
                opacity: 0.88,
                blending: THREE.AdditiveBlending
            });
            const statorMat = new THREE.MeshStandardMaterial({
                color: 0xf59e0b,
                emissive: 0xd97706,
                emissiveIntensity: 0.5,
                metalness: 0.85,
                roughness: 0.2
            });
            const buildTurbine = (xPos) => {
                const hub = new THREE.Group();
                hub.position.set(xPos, -0.35, 0.16);
                const housing = new THREE.Mesh(new THREE.TorusGeometry(0.32, 0.035, 8, 20), statorMat);
                const rotorGroup = new THREE.Group();
                const bladeGeo = new THREE.BoxGeometry(0.56, 0.06, 0.02);
                for (let b = 0; b < 3; b++) {
                    const rb = new THREE.Mesh(bladeGeo, rotorMat);
                    rb.rotation.z = (b * Math.PI) / 3;
                    rotorGroup.add(rb);
                }
                hub.add(housing, rotorGroup);
                animGroup.add(hub);
                return rotorGroup;
            };
            rig.parts.leftTurbine = buildTurbine(-1.05);
            rig.parts.rightTurbine = buildTurbine(1.05);

            const scanRing = new THREE.Mesh(
                new THREE.TorusGeometry(0.75, 0.024, 6, 24),
                new THREE.MeshBasicMaterial({
                    color: 0xf97316,
                    transparent: true,
                    opacity: 0.65,
                    blending: THREE.AdditiveBlending
                })
            );
            scanRing.rotation.x = Math.PI / 2;
            scanRing.scale.set(1.8, 0.38, 1.0);
            animGroup.add(scanRing);
            rig.parts.scanRing = scanRing;

            makeThrusterPlume(-0.55, -1.72, 0.0, 0.19, 1.05, 0xea580c, 0xfef08a);
            makeThrusterPlume( 0.00, -1.78, 0.02, 0.24, 1.28, 0xf97316, 0xffffff);
            makeThrusterPlume( 0.55, -1.72, 0.0, 0.19, 1.05, 0xea580c, 0xfef08a);
        }

        // =========================================================================
        // 3. AURELIA GOLD-PHÖNIX (gold_phoenix)
        // =========================================================================
        else if (key === 'gold_phoenix') {
            const goldFeatherMat = new THREE.MeshStandardMaterial({
                color: 0xfacc15,
                emissive: 0xd97706,
                emissiveIntensity: 0.55,
                metalness: 0.85,
                roughness: 0.18
            });
            const amethystFeatherMat = new THREE.MeshBasicMaterial({
                color: 0xc084fc,
                transparent: true,
                opacity: 0.88,
                blending: THREE.AdditiveBlending
            });

            const buildPhoenixWing = (side) => {
                const innerPivot = new THREE.Group();
                innerPivot.position.set(side * 0.58, 0.18, 0.16);
                for (let f = 0; f < 3; f++) {
                    const fGeo = new THREE.ConeGeometry(0.16, 1.15 - f * 0.15, 5);
                    fGeo.scale(1.0, 1.0, 0.25);
                    fGeo.rotateZ(side * -(Math.PI * 0.42 + f * 0.14));
                    fGeo.translate(side * (0.48 + f * 0.12), -f * 0.22, 0);
                    const fMesh = new THREE.Mesh(fGeo, f === 1 ? amethystFeatherMat : goldFeatherMat);
                    innerPivot.add(fMesh);
                }
                const outerPivot = new THREE.Group();
                outerPivot.position.set(side * 1.05, -0.05, 0.04);
                for (let k = 0; k < 3; k++) {
                    const tipGeo = new THREE.ConeGeometry(0.13, 1.10 - k * 0.14, 5);
                    tipGeo.scale(1.0, 1.0, 0.22);
                    tipGeo.rotateZ(side * -(Math.PI * 0.34 + k * 0.16));
                    tipGeo.translate(side * (0.45 + k * 0.14), 0.12 - k * 0.22, 0);
                    const tipMesh = new THREE.Mesh(tipGeo, k === 2 ? amethystFeatherMat : goldFeatherMat);
                    outerPivot.add(tipMesh);
                }
                innerPivot.add(outerPivot);
                animGroup.add(innerPivot);
                return { inner: innerPivot, outer: outerPivot };
            };

            rig.parts.leftWing = buildPhoenixWing(-1);
            rig.parts.rightWing = buildPhoenixWing(1);

            const haloGroup = new THREE.Group();
            haloGroup.position.set(0, 0.08, 0.68);
            const sunRing = new THREE.Mesh(
                new THREE.TorusGeometry(0.46, 0.032, 8, 28),
                new THREE.MeshBasicMaterial({ color: 0xfde047, transparent: true, opacity: 0.9, blending: THREE.AdditiveBlending })
            );
            const soulHeart = new THREE.Mesh(
                new THREE.OctahedronGeometry(0.18, 0),
                new THREE.MeshStandardMaterial({ color: 0xe879f9, emissive: 0xa855f7, emissiveIntensity: 0.85, metalness: 0.5, roughness: 0.15 })
            );
            haloGroup.add(sunRing, soulHeart);
            animGroup.add(haloGroup);
            rig.parts.sunRing = sunRing;
            rig.parts.soulHeart = soulHeart;

            rig.parts.tailPlumes = [-0.42, 0.0, 0.42].map((xOff, idx) => {
                const tp = new THREE.Group();
                tp.position.set(xOff, -1.78, 0.05);
                const pGeo = new THREE.ConeGeometry(0.14, 1.25, 5);
                pGeo.translate(0, -0.62, 0);
                pGeo.rotateZ(Math.PI);
                const pMesh = new THREE.Mesh(pGeo, idx === 1 ? amethystFeatherMat : goldFeatherMat);
                tp.add(pMesh);
                animGroup.add(tp);
                return tp;
            });

            makeThrusterPlume(-0.38, -1.80, 0.0, 0.22, 1.20, 0xf59e0b, 0xfef9c3);
            makeThrusterPlume( 0.38, -1.80, 0.0, 0.22, 1.20, 0xf59e0b, 0xfef9c3);
        }

        // =========================================================================
        // 4. FUNKI AUF DEM KOMETEN (funki_comet)
        // =========================================================================
        else if (key === 'funki_comet') {
            const makeOrbitRing = (radius, hex, tube) => {
                const m = new THREE.Mesh(
                    new THREE.TorusGeometry(radius, tube, 8, 32),
                    new THREE.MeshBasicMaterial({ color: hex, transparent: true, opacity: 0.78, blending: THREE.AdditiveBlending })
                );
                m.position.set(0, -0.25, 0.15);
                animGroup.add(m);
                return m;
            };
            rig.parts.orbit1 = makeOrbitRing(1.38, 0xe879f9, 0.032);
            rig.parts.orbit2 = makeOrbitRing(1.12, 0x38bdf8, 0.026);
            rig.parts.orbit3 = makeOrbitRing(0.88, 0xfde047, 0.024);

            const capeMat = new THREE.MeshStandardMaterial({
                color: 0xd946ef,
                emissive: 0x9333ea,
                emissiveIntensity: 0.55,
                side: THREE.DoubleSide,
                roughness: 0.3
            });
            rig.parts.capeSegments = [0, 1, 2].map((idx) => {
                const seg = new THREE.Mesh(
                    new THREE.BoxGeometry(0.68 + idx * 0.14, 0.38, 0.045),
                    capeMat
                );
                seg.position.set(0, -0.32 - idx * 0.34, 0.66 - idx * 0.08);
                animGroup.add(seg);
                return seg;
            });

            const wandGroup = new THREE.Group();
            wandGroup.position.set(0.58, 0.15, 0.88);
            const starTip = new THREE.Mesh(
                new THREE.OctahedronGeometry(0.20, 0),
                new THREE.MeshBasicMaterial({ color: 0xfef08a })
            );
            const starHalo = new THREE.Mesh(
                new THREE.TorusGeometry(0.28, 0.025, 6, 18),
                new THREE.MeshBasicMaterial({ color: 0xf472b6, transparent: true, opacity: 0.85, blending: THREE.AdditiveBlending })
            );
            wandGroup.add(starTip, starHalo);
            animGroup.add(wandGroup);
            rig.parts.wandGroup = wandGroup;
            rig.parts.starTip = starTip;

            const moonGeo = new THREE.OctahedronGeometry(0.18, 0);
            const moonMat = new THREE.MeshStandardMaterial({
                color: 0xf0abfc,
                emissive: 0xc026d3,
                emissiveIntensity: 0.7,
                metalness: 0.5,
                roughness: 0.2
            });
            rig.parts.moonlets = [0, 1, 2, 3].map((i) => {
                const m = new THREE.Mesh(moonGeo, moonMat);
                m.userData = { angleOffset: (i * Math.PI) / 2, radius: 1.55 + (i % 2) * 0.25, zTilt: (i % 2 === 0 ? 0.38 : -0.38) };
                animGroup.add(m);
                return m;
            });

            const tailOrbGeo = new THREE.SphereGeometry(0.16, 8, 8);
            const tailOrbMat1 = new THREE.MeshBasicMaterial({ color: 0xe879f9, transparent: true, opacity: 0.82, blending: THREE.AdditiveBlending });
            const tailOrbMat2 = new THREE.MeshBasicMaterial({ color: 0x38bdf8, transparent: true, opacity: 0.82, blending: THREE.AdditiveBlending });
            rig.parts.helixOrbs = [];
            for (let h = 0; h < 6; h++) {
                const orb = new THREE.Mesh(tailOrbGeo, h % 2 === 0 ? tailOrbMat1 : tailOrbMat2);
                orb.userData = { idx: h };
                animGroup.add(orb);
                rig.parts.helixOrbs.push(orb);
            }

            makeThrusterPlume(0.0, -2.05, 0.0, 0.45, 1.65, 0xd946ef, 0xfef08a);
        }

        // =========================================================================
        // 5. MANUFAKTUR-MAJESTÄT (royal_dreadnought)
        // =========================================================================
        else if (key === 'royal_dreadnought') {
            const royalGoldMat = new THREE.MeshStandardMaterial({
                color: 0xfacc15,
                emissive: 0xb45309,
                emissiveIntensity: 0.6,
                metalness: 0.9,
                roughness: 0.15
            });
            const rubyGlowMat = new THREE.MeshBasicMaterial({
                color: 0xfef08a,
                transparent: true,
                opacity: 0.95,
                blending: THREE.AdditiveBlending
            });

            const crownGroup = new THREE.Group();
            crownGroup.position.set(0, 0.05, 0.88);
            const crownBase = new THREE.Mesh(new THREE.TorusGeometry(0.36, 0.045, 8, 24), royalGoldMat);
            crownGroup.add(crownBase);
            for (let s = 0; s < 6; s++) {
                const ang = (s / 6) * Math.PI * 2;
                const spire = new THREE.Mesh(new THREE.ConeGeometry(0.065, 0.24, 5), royalGoldMat);
                spire.position.set(Math.cos(ang) * 0.36, Math.sin(ang) * 0.36, 0.12);
                spire.rotation.x = Math.PI / 2;
                const jewel = new THREE.Mesh(new THREE.OctahedronGeometry(0.05, 0), rubyGlowMat);
                jewel.position.set(Math.cos(ang) * 0.36, Math.sin(ang) * 0.36, 0.25);
                crownGroup.add(spire, jewel);
            }
            animGroup.add(crownGroup);
            rig.parts.crownGroup = crownGroup;

            const astrolabeMat = new THREE.MeshBasicMaterial({
                color: 0xfde047,
                transparent: true,
                opacity: 0.75,
                blending: THREE.AdditiveBlending
            });
            const gimbalOuter = new THREE.Mesh(new THREE.TorusGeometry(0.72, 0.028, 8, 32), astrolabeMat);
            gimbalOuter.position.set(0, -0.05, 0.35);
            const gimbalInner = new THREE.Mesh(new THREE.TorusGeometry(0.52, 0.024, 8, 24), astrolabeMat);
            gimbalInner.position.set(0, -0.05, 0.35);
            animGroup.add(gimbalOuter, gimbalInner);
            rig.parts.gimbalOuter = gimbalOuter;
            rig.parts.gimbalInner = gimbalInner;

            const buildEscortDrone = (cfg) => {
                const dGroup = new THREE.Group();
                const bodyGeo = new THREE.ConeGeometry(0.18, 0.68, 4);
                bodyGeo.scale(1.25, 1.0, 0.45);
                const body = new THREE.Mesh(bodyGeo, royalGoldMat);
                const wingGeo = new THREE.BoxGeometry(0.58, 0.16, 0.04);
                const wing = new THREE.Mesh(wingGeo, royalGoldMat);
                wing.position.set(0, -0.1, 0);
                const jetGeo = new THREE.ConeGeometry(0.08, 0.38, 6);
                jetGeo.translate(0, -0.35, 0);
                jetGeo.rotateZ(Math.PI);
                const jet = new THREE.Mesh(jetGeo, rubyGlowMat);
                dGroup.add(body, wing, jet);
                dGroup.userData = cfg;
                animGroup.add(dGroup);
                return dGroup;
            };
            rig.parts.escortDrones = [
                buildEscortDrone({ baseX: -2.45, baseY:  0.35, baseZ: 0.25, phase: 0.0,  side: -1 }),
                buildEscortDrone({ baseX:  2.45, baseY:  0.35, baseZ: 0.25, phase: Math.PI, side: 1 }),
                buildEscortDrone({ baseX: -1.85, baseY: -1.05, baseZ: 0.42, phase: 1.57, side: -1 }),
                buildEscortDrone({ baseX:  1.85, baseY: -1.05, baseZ: 0.42, phase: 4.71, side:  1 })
            ];

            makeThrusterPlume(-0.98, -1.82, 0.0, 0.20, 1.15, 0xf59e0b, 0xfef9c3);
            makeThrusterPlume(-0.38, -1.88, 0.04, 0.28, 1.45, 0xfacc15, 0xffffff);
            makeThrusterPlume( 0.38, -1.88, 0.04, 0.28, 1.45, 0xfacc15, 0xffffff);
            makeThrusterPlume( 0.98, -1.82, 0.0, 0.20, 1.15, 0xf59e0b, 0xfef9c3);
        }

        // =========================================================================
        // 6. SCHLACHTSCHIFFE (aegis_titan, valkyrie_prime, leviathan_frost, hyperion_sun, celestial_ark, dark_perl)
        // =========================================================================
        else if (['aegis_titan', 'valkyrie_prime', 'leviathan_frost', 'hyperion_sun', 'celestial_ark', 'dark_perl'].includes(key)) {
            const paletteMap = {
                aegis_titan:     { primary: 0x38bdf8, accent: 0xbae6fd, emissive: 0x0284c7, plumeOut: 0x0ea5e9, plumeIn: 0xe0f2fe },
                valkyrie_prime:  { primary: 0xf43f5e, accent: 0xfde047, emissive: 0xbe123c, plumeOut: 0xe11d48, plumeIn: 0xfef08a },
                leviathan_frost: { primary: 0x22d3ee, accent: 0xcffafe, emissive: 0x0891b2, plumeOut: 0x06b6d4, plumeIn: 0xffffff },
                hyperion_sun:    { primary: 0xfacc15, accent: 0xf97316, emissive: 0xd97706, plumeOut: 0xf59e0b, plumeIn: 0xfef9c3 },
                celestial_ark:   { primary: 0xc084fc, accent: 0x34d399, emissive: 0x7e22ce, plumeOut: 0xa855f7, plumeIn: 0xa7f3d0 },
                dark_perl:       { primary: 0xff2a00, accent: 0xef4444, emissive: 0xb91c1c, plumeOut: 0xdc2626, plumeIn: 0xfde047 }
            };
            const pal = paletteMap[key] || paletteMap.aegis_titan;

            const armorMat = new THREE.MeshStandardMaterial({
                color: key === 'dark_perl' ? 0x09090d : pal.primary,
                emissive: pal.emissive,
                emissiveIntensity: key === 'dark_perl' ? 0.85 : 0.65,
                metalness: 0.9,
                roughness: 0.16
            });
            const energyMat = new THREE.MeshBasicMaterial({
                color: pal.primary,
                transparent: true,
                opacity: 0.92,
                blending: THREE.AdditiveBlending
            });
            const accentMat = new THREE.MeshBasicMaterial({
                color: pal.accent,
                transparent: true,
                opacity: 0.85,
                blending: THREE.AdditiveBlending
            });

            // Dorsal Battleship Reactor Core & Dual Gimbal Rings
            const coreHub = new THREE.Group();
            coreHub.position.set(0, -0.08, 0.92);
            const ringOuter = new THREE.Mesh(new THREE.TorusGeometry(key === 'dark_perl' ? 0.92 : 0.78, 0.04, 8, 32), energyMat);
            const ringInner = new THREE.Mesh(new THREE.TorusGeometry(key === 'dark_perl' ? 0.64 : 0.54, 0.032, 8, 28), accentMat);
            const coreCrystal = new THREE.Mesh(new THREE.OctahedronGeometry(key === 'dark_perl' ? 0.30 : 0.24, 1), armorMat);
            coreHub.add(ringOuter, ringInner, coreCrystal);
            animGroup.add(coreHub);
            rig.parts.gimbalOuter = ringOuter;
            rig.parts.gimbalInner = ringInner;
            rig.parts.prismCore = coreCrystal;

            // Dark Perl Special: Pulsing Lava-Red Saw-Blade Crown & Magma Veins
            if (key === 'dark_perl') {
                const lavaCrown = new THREE.Group();
                lavaCrown.position.set(0, 0.25, 1.05);
                for (let b = 0; b < 8; b++) {
                    const ang = (b / 8) * Math.PI * 2;
                    const spike = new THREE.Mesh(new THREE.ConeGeometry(0.08, 0.38, 4), energyMat);
                    spike.position.set(Math.cos(ang) * 0.52, Math.sin(ang) * 0.52, 0.08);
                    spike.rotation.z = ang - Math.PI / 2;
                    lavaCrown.add(spike);
                }
                animGroup.add(lavaCrown);
                rig.parts.crownGroup = lavaCrown;
            }

            // 4 Heavy Battleship Escort / Broadside Drones
            const buildBattleshipDrone = (cfg) => {
                const dGroup = new THREE.Group();
                const hull = new THREE.Mesh(new THREE.OctahedronGeometry(0.24, 0), armorMat);
                hull.scale.set(0.85, 1.85, 0.55);
                const halo = new THREE.Mesh(new THREE.TorusGeometry(0.28, 0.025, 6, 16), energyMat);
                dGroup.add(hull, halo);
                dGroup.userData = cfg;
                animGroup.add(dGroup);
                return dGroup;
            };
            rig.parts.escortDrones = [
                buildBattleshipDrone({ baseX: -2.75, baseY:  0.45, baseZ: 0.30, phase: 0.0 }),
                buildBattleshipDrone({ baseX:  2.75, baseY:  0.45, baseZ: 0.30, phase: Math.PI }),
                buildBattleshipDrone({ baseX: -2.15, baseY: -1.15, baseZ: 0.45, phase: 1.57 }),
                buildBattleshipDrone({ baseX:  2.15, baseY: -1.15, baseZ: 0.45, phase: 4.71 })
            ];

            // Heavy Quad / Hex Battleship Thrusters
            makeThrusterPlume(-1.25, -2.35, 0.0, 0.25, 1.35, pal.plumeOut, pal.plumeIn);
            makeThrusterPlume(-0.48, -2.48, 0.05, 0.34, 1.75, pal.primary, 0xffffff);
            makeThrusterPlume( 0.48, -2.48, 0.05, 0.34, 1.75, pal.primary, 0xffffff);
            makeThrusterPlume( 1.25, -2.35, 0.0, 0.25, 1.35, pal.plumeOut, pal.plumeIn);
        }

        rawScene.userData.sfAnimRig = rig;
        return rig;
    },

    updateShipAnimation(modelNode, timeSec, dt, state = {}) {
        if (!modelNode) return;
        let rig = modelNode.userData ? modelNode.userData.sfAnimRig : null;
        if (!rig && modelNode.children && modelNode.children.length > 0) {
            for (let i = 0; i < modelNode.children.length; i++) {
                if (modelNode.children[i].userData && modelNode.children[i].userData.sfAnimRig) {
                    rig = modelNode.children[i].userData.sfAnimRig;
                    break;
                }
            }
        }
        if (!rig || rig.key === 'default') return;

        const t = timeSec || 0;
        const speedMult = state.boost ? 2.15 : 1.0;
        const bank = state.bank || 0;

        // 1. Subtle breathing emissive pulse on hull (stronger lava pulse on Dark Perl)
        const baseEmissive = rig.key === 'dark_perl' ? 0.34 : 0.16;
        const ampEmissive = rig.key === 'dark_perl' ? 0.22 : 0.10;
        const hullPulse = baseEmissive + ampEmissive * Math.sin(t * 3.8 * speedMult);
        for (let i = 0; i < rig.hullMats.length; i++) {
            rig.hullMats[i].emissiveIntensity = hullPulse;
        }

        // 2. High-frequency 60 FPS plasma thruster flicker & boost stretch
        for (let i = 0; i < rig.thrusters.length; i++) {
            const th = rig.thrusters[i];
            const flicker = 0.88 + 0.24 * Math.sin(t * 34.0 + i * 1.7) + (state.boost ? 0.45 : 0);
            const widthPulse = 0.92 + 0.12 * Math.cos(t * 26.0 + i);
            th.scale.set(widthPulse, flicker, widthPulse);
        }

        const p = rig.parts;

        // 3. Ship-Specific 60 FPS Articulated 3D Animations
        if (rig.key === 'crystal_fighter') {
            if (p.shards) {
                for (let i = 0; i < p.shards.length; i++) {
                    const sh = p.shards[i];
                    const cfg = sh.userData;
                    const wave = Math.sin(t * 3.8 * speedMult + cfg.phase);
                    sh.position.x = cfg.x + Math.cos(t * 2.6 + cfg.phase) * 0.14;
                    sh.position.y = cfg.y + wave * 0.12;
                    sh.position.z = cfg.z + Math.sin(t * 4.5 + cfg.phase) * 0.18;
                    sh.rotation.y = t * 2.4 * speedMult + cfg.phase;
                    sh.rotation.z = cfg.tilt + wave * 0.22 - bank * 0.35;
                }
            }
            if (p.prismRing1) {
                p.prismRing1.rotation.x = t * 2.8 * speedMult;
                p.prismRing1.rotation.y = t * 1.9 * speedMult;
            }
            if (p.prismRing2) {
                p.prismRing2.rotation.y = -t * 3.4 * speedMult;
                p.prismRing2.rotation.z = t * 2.2 * speedMult;
            }
            if (p.prismCore) {
                p.prismCore.rotation.z = -t * 4.0;
                p.prismCore.scale.setScalar(0.95 + Math.sin(t * 6.5) * 0.22);
            }
        }
        else if (rig.key === 'slate_phantom') {
            const sweep = Math.sin(t * 3.2 * speedMult) * 0.13 + (state.boost ? 0.18 : 0);
            if (p.leftBlade) {
                p.leftBlade.rotation.z = sweep;
                p.leftBlade.rotation.y = Math.sin(t * 4.0) * 0.10 - bank * 0.25;
            }
            if (p.rightBlade) {
                p.rightBlade.rotation.z = -sweep;
                p.rightBlade.rotation.y = -Math.sin(t * 4.0) * 0.10 - bank * 0.25;
            }
            if (p.leftTurbine) p.leftTurbine.rotation.z = t * 18.0 * speedMult;
            if (p.rightTurbine) p.rightTurbine.rotation.z = -t * 18.0 * speedMult;
            if (p.scanRing) {
                const scanCycle = (t * 0.85 * speedMult) % 1.0;
                p.scanRing.position.y = 2.15 - scanCycle * 3.65;
                const widthEnvelope = Math.sin(scanCycle * Math.PI);
                p.scanRing.scale.set(0.45 + widthEnvelope * 1.95, 0.22 + widthEnvelope * 0.25, 1.0);
                p.scanRing.material.opacity = 0.15 + widthEnvelope * 0.65;
            }
        }
        else if (rig.key === 'gold_phoenix') {
            const flapSpeed = 4.6 * speedMult;
            const innerFlap = Math.sin(t * flapSpeed) * 0.42;
            const outerFlap = Math.sin(t * flapSpeed - 0.72) * 0.48;
            const forwardStroke = Math.cos(t * flapSpeed) * 0.12;

            if (p.leftWing) {
                p.leftWing.inner.rotation.y = innerFlap;
                p.leftWing.inner.rotation.z = forwardStroke;
                p.leftWing.outer.rotation.y = outerFlap;
            }
            if (p.rightWing) {
                p.rightWing.inner.rotation.y = -innerFlap;
                p.rightWing.inner.rotation.z = -forwardStroke;
                p.rightWing.outer.rotation.y = -outerFlap;
            }
            if (p.sunRing) {
                p.sunRing.rotation.z = t * 2.2 * speedMult;
                p.sunRing.rotation.x = Math.sin(t * 2.5) * 0.35;
            }
            if (p.soulHeart) {
                p.soulHeart.position.z = 0.08 + Math.sin(t * 5.2) * 0.10;
                p.soulHeart.rotation.z = -t * 3.5;
                p.soulHeart.scale.setScalar(0.95 + Math.sin(t * 7.5) * 0.18);
            }
            if (p.tailPlumes) {
                for (let i = 0; i < p.tailPlumes.length; i++) {
                    const tp = p.tailPlumes[i];
                    tp.rotation.z = Math.sin(t * 6.5 * speedMult + i * 0.9) * 0.22;
                    tp.rotation.x = Math.cos(t * 5.2 * speedMult + i) * 0.18;
                }
            }
        }
        else if (rig.key === 'funki_comet') {
            if (p.orbit1) {
                p.orbit1.rotation.x = t * 2.1 * speedMult;
                p.orbit1.rotation.y = t * 1.4 * speedMult;
            }
            if (p.orbit2) {
                p.orbit2.rotation.y = -t * 2.6 * speedMult;
                p.orbit2.rotation.z = t * 1.8 * speedMult;
            }
            if (p.orbit3) {
                p.orbit3.rotation.x = -t * 3.1 * speedMult;
                p.orbit3.rotation.z = -t * 2.2 * speedMult;
            }
            if (p.capeSegments) {
                for (let i = 0; i < p.capeSegments.length; i++) {
                    const seg = p.capeSegments[i];
                    const wave = Math.sin(t * 11.5 * speedMult - i * 0.85);
                    seg.position.z = (0.66 - i * 0.08) + wave * (0.06 + i * 0.04);
                    seg.rotation.x = 0.18 + wave * 0.24;
                    seg.rotation.y = Math.cos(t * 8.5 - i) * 0.14;
                }
            }
            if (p.wandGroup) {
                p.wandGroup.position.z = 0.88 + Math.sin(t * 5.5) * 0.12;
                p.wandGroup.rotation.z = Math.sin(t * 4.2) * 0.35;
                if (p.starTip) {
                    p.starTip.rotation.y = t * 6.0;
                    p.starTip.rotation.z = t * 4.5;
                    p.starTip.scale.setScalar(0.95 + Math.sin(t * 9.0) * 0.25);
                }
            }
            if (p.moonlets) {
                for (let i = 0; i < p.moonlets.length; i++) {
                    const m = p.moonlets[i];
                    const cfg = m.userData;
                    const ang = t * 3.0 * speedMult + cfg.angleOffset;
                    m.position.x = Math.cos(ang) * cfg.radius;
                    m.position.y = -0.25 + Math.sin(ang) * (cfg.radius * 0.65);
                    m.position.z = 0.15 + Math.sin(ang) * cfg.zTilt;
                    m.rotation.x = t * 4.0;
                    m.rotation.y = t * 5.0;
                }
            }
            if (p.helixOrbs) {
                for (let i = 0; i < p.helixOrbs.length; i++) {
                    const orb = p.helixOrbs[i];
                    const idx = orb.userData.idx;
                    const strand = (idx % 2 === 0) ? 1 : -1;
                    const progress = idx / 6;
                    const spiralAng = t * 8.5 * speedMult - progress * 4.2 + (strand > 0 ? 0 : Math.PI);
                    const rad = 0.42 * (1.0 - progress * 0.35);
                    orb.position.set(
                        Math.cos(spiralAng) * rad,
                        -1.55 - idx * 0.34,
                        0.05 + Math.sin(spiralAng) * rad
                    );
                    orb.scale.setScalar((1.0 - progress * 0.45) * (0.9 + 0.2 * Math.sin(t * 12.0 + idx)));
                }
            }
        }
        else if (['royal_dreadnought', 'aegis_titan', 'valkyrie_prime', 'leviathan_frost', 'hyperion_sun', 'celestial_ark', 'dark_perl'].includes(rig.key)) {
            if (p.crownGroup) {
                p.crownGroup.position.z = (rig.key === 'dark_perl' ? 1.05 : 0.88) + Math.sin(t * 3.8) * 0.10;
                p.crownGroup.rotation.z = t * (rig.key === 'dark_perl' ? -2.6 : 1.8) * speedMult;
                p.crownGroup.rotation.x = Math.sin(t * 2.4) * 0.14;
            }
            if (p.gimbalOuter) {
                p.gimbalOuter.rotation.x = t * 2.4 * speedMult;
                p.gimbalOuter.rotation.y = t * 1.6 * speedMult;
            }
            if (p.gimbalInner) {
                p.gimbalInner.rotation.y = -t * 3.2 * speedMult;
                p.gimbalInner.rotation.z = t * 2.5 * speedMult;
            }
            if (p.prismCore) {
                p.prismCore.rotation.z = -t * 3.8;
                p.prismCore.scale.setScalar(0.95 + Math.sin(t * 6.8) * 0.20);
            }
            if (p.escortDrones) {
                for (let i = 0; i < p.escortDrones.length; i++) {
                    const d = p.escortDrones[i];
                    const cfg = d.userData;
                    const orbitT = t * 3.2 * speedMult + cfg.phase;
                    d.position.x = cfg.baseX + Math.cos(orbitT) * 0.32;
                    d.position.y = cfg.baseY + Math.sin(orbitT * 2.0) * 0.28;
                    d.position.z = cfg.baseZ + Math.sin(orbitT) * 0.26;
                    d.rotation.y = Math.sin(orbitT) * 0.45 - bank * 0.4;
                    d.rotation.z = Math.cos(orbitT) * -0.18;
                }
            }
        }
    }
};

window.seelenHangarShowroom = function(skinsMap, initialKey, initialAuraKey = 'default') {
    let scene = null, camera = null, renderer = null;
    let shipHolder = null, activeMesh = null;
    let holodeckRing1 = null, holodeckRing2 = null, shieldGroup = null;
    let starPoints = null, laserBolts = [], exhaustParticles = [];
    let laserMat = null, exhaustMat = null;
    let modelCache = {};

    // Shared Offscreen 3D Card Renderer (renders all visible ship cards at 60 FPS with only 1 WebGL context!)
    let cardScene = null, cardCamera = null, cardRenderer = null, cardShipHolder = null, cardRing = null;
    let cardModelCache = {};
    let cardLoadingKeys = {};

    let animId = null;
    let lastTs = 0;
    let warpScale = 1.0;
    let boostSpinTimer = 0;
    let autoFireTimer = 0;
    let resizeObs = null;

    const skinKeys = Object.keys(skinsMap || {});
    const resolvedInitKey = (initialKey && skinsMap[initialKey]) ? initialKey : (skinKeys[0] || 'default');
    const resolvedInitCat = (skinsMap[resolvedInitKey] && skinsMap[resolvedInitKey].category) ? skinsMap[resolvedInitKey].category : 'fighter';

    return {
        tab: 'ships',
        shipCategory: resolvedInitCat,
        previewAuraKey: initialAuraKey || 'default',
        skins: skinsMap || {},
        previewKey: resolvedInitKey,
        demoShieldActive: false,
        isDragging: false,
        dragStartX: 0,
        userRotY: 0.45,

        get currentShip() {
            return this.skins[this.previewKey] || this.skins['default'] || {};
        },

        initShowroom() {
            const startStatus = (this.currentShip.stats && this.currentShip.stats.start_shield > 0);
            this.demoShieldActive = !!startStatus;

            const checkAndBoot = () => {
                const wrap = this.$refs.hangarCanvasWrap;
                if (!wrap || typeof window.THREE === 'undefined') return;
                if (!cardRenderer) {
                    this.setupCardMiniRenderer();
                }
                if (wrap.offsetWidth > 20 && wrap.offsetHeight > 20) {
                    if (!renderer) {
                        this.setupThreeStage(wrap);
                    } else {
                        this.resizeStage();
                    }
                }
            };

            setTimeout(checkAndBoot, 120);
            setTimeout(checkAndBoot, 450);

            window.addEventListener('hangar-panel-opened', () => {
                requestAnimationFrame(() => {
                    checkAndBoot();
                    warpScale = 0.28;
                    boostSpinTimer = 0.55;
                });
                setTimeout(checkAndBoot, 90);
            });

            if (typeof IntersectionObserver !== 'undefined' && this.$refs.hangarCanvasWrap) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            checkAndBoot();
                        }
                    });
                }, { threshold: 0.05 });
                io.observe(this.$refs.hangarCanvasWrap);
            }
        },

        switchTab(newTab) {
            if (window.sfCockpitSound) window.sfCockpitSound('switch');
            this.tab = newTab;
            if (newTab === 'ships') {
                setTimeout(() => this.resizeStage(), 60);
            }
        },

        setShipCategory(cat) {
            if (window.sfCockpitSound) window.sfCockpitSound('switch');
            this.shipCategory = cat;
            if (cat === 'fighter' || cat === 'battleship') {
                if ((this.currentShip.category || 'fighter') !== cat) {
                    const firstInCat = skinKeys.find(k => (this.skins[k]?.category || 'fighter') === cat);
                    if (firstInCat) {
                        this.selectPreviewShip(firstInCat, false);
                    }
                }
                setTimeout(() => this.resizeStage(), 60);
            }
        },

        cycleShip(dir) {
            if (window.sfCockpitSound) window.sfCockpitSound('click');
            const catKeys = skinKeys.filter(k => (this.skins[k]?.category || 'fighter') === (this.currentShip.category || 'fighter'));
            const list = catKeys.length ? catKeys : skinKeys;
            const idx = Math.max(0, list.indexOf(this.previewKey));
            const nextIdx = (idx + dir + list.length) % list.length;
            this.selectPreviewShip(list[nextIdx], false);
        },

        selectPreviewShip(key, scrollIfMobile = false) {
            if (!this.skins[key]) return;
            if (window.sfCockpitSound) window.sfCockpitSound('click');
            this.previewKey = key;
            const cfg = this.skins[key];
            if (cfg.category && this.shipCategory !== cfg.category) {
                this.shipCategory = cfg.category;
            }
            this.demoShieldActive = !!(cfg.stats && cfg.stats.start_shield > 0);
            this.loadShipIntoStage(cfg);
            if (scrollIfMobile && window.innerWidth < 768 && this.$refs.showroomBox) {
                this.$refs.showroomBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        },

        startDrag(e) {
            this.isDragging = true;
            this.dragStartX = e.clientX || 0;
        },
        onDrag(e) {
            if (!this.isDragging) return;
            const dx = (e.clientX || 0) - this.dragStartX;
            this.dragStartX = e.clientX || 0;
            this.userRotY += dx * 0.012;
        },
        endDrag() {
            this.isDragging = false;
        },

        triggerLaserDemo() {
            this.spawnShowroomLasers(true);
        },
        toggleShieldDemo() {
            this.demoShieldActive = !this.demoShieldActive;
            if (shieldGroup && this.demoShieldActive) shieldGroup.visible = true;
        },
        triggerBoostSpin() {
            boostSpinTimer = 0.85;
            this.spawnShowroomLasers(true);
        },

        setupCardMiniRenderer() {
            if (cardRenderer || typeof window.THREE === 'undefined') return;
            const THREE = window.THREE;
            cardScene = new THREE.Scene();
            cardCamera = new THREE.PerspectiveCamera(42, 300 / 165, 0.1, 100);
            cardCamera.position.set(0, 2.35, 7.2);
            cardCamera.lookAt(0, 0.0, 0);

            cardRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
            cardRenderer.setSize(300, 165, false);
            cardRenderer.setPixelRatio(1);

            cardScene.add(new THREE.AmbientLight(0xffffff, 2.1));
            const dl1 = new THREE.DirectionalLight(0xffffff, 2.4);
            dl1.position.set(5, 9, 7);
            cardScene.add(dl1);
            const dl2 = new THREE.DirectionalLight(0xfbbf24, 1.5);
            dl2.position.set(-5, -3, 5);
            cardScene.add(dl2);

            const ringGeo = new THREE.RingGeometry(1.95, 2.12, 36);
            const ringMat = new THREE.MeshBasicMaterial({ color: 0xf59e0b, side: THREE.DoubleSide, transparent: true, opacity: 0.5, blending: THREE.AdditiveBlending });
            cardRing = new THREE.Mesh(ringGeo, ringMat);
            cardRing.rotation.x = Math.PI / 2;
            cardRing.position.y = -1.45;
            cardScene.add(cardRing);

            cardShipHolder = new THREE.Group();
            cardScene.add(cardShipHolder);

            // Preload all 12 ship models for the card canvases
            skinKeys.forEach(k => this.ensureCardModelLoaded(k));
        },

        ensureCardModelLoaded(key) {
            if (cardModelCache[key] || cardLoadingKeys[key]) return;
            const cfg = this.skins[key];
            if (!cfg || !cfg.glb_url || typeof window.THREE === 'undefined') return;
            const LoaderClass = window.GLTFLoader || (window.THREE && window.THREE.GLTFLoader);
            if (!LoaderClass) return;

            cardLoadingKeys[key] = true;
            const THREE = window.THREE;
            const loader = new LoaderClass();
            loader.load(cfg.glb_url, (gltf) => {
                const wrapper = new THREE.Group();
                const rawScene = gltf.scene;
                const isBattleship = (cfg.category === 'battleship');

                if (cfg.is_vertex_colored) {
                    rawScene.rotation.set(Math.PI / 2, Math.PI, 0);
                    if (window.SeelenfunkeShipAnimator) {
                        window.SeelenfunkeShipAnimator.enhanceShipModel(THREE, cfg.key, rawScene);
                    }
                    wrapper.userData.targetScale = isBattleship ? 0.54 : 0.68;
                } else {
                    const box = new THREE.Box3().setFromObject(rawScene);
                    const center = box.getCenter(new THREE.Vector3());
                    rawScene.position.sub(center);
                    rawScene.rotation.set(0, -Math.PI / 2, 0);
                    wrapper.userData.targetScale = 2.95;
                }
                wrapper.scale.setScalar(wrapper.userData.targetScale);
                wrapper.add(rawScene);
                cardModelCache[key] = wrapper;
            }, undefined, () => {
                cardLoadingKeys[key] = false;
            });
        },

        renderVisibleShipCards(nowSec, dt) {
            if (!cardRenderer || !cardScene || !cardCamera || !cardShipHolder) return;
            const canvases = this.$el ? this.$el.querySelectorAll('canvas[data-ship-card-canvas]') : [];
            if (!canvases.length) return;

            for (let i = 0; i < canvases.length; i++) {
                const cvs = canvases[i];
                const key = cvs.getAttribute('data-ship-card-canvas');
                const cfg = this.skins[key];
                if (!cfg || (cfg.category || 'fighter') !== this.shipCategory) continue;
                if (!cvs.offsetParent) continue;

                const modelNode = cardModelCache[key];
                if (!modelNode) {
                    this.ensureCardModelLoaded(key);
                    continue;
                }

                while (cardShipHolder.children.length > 0) {
                    cardShipHolder.remove(cardShipHolder.children[0]);
                }
                cardShipHolder.add(modelNode);

                const phase = i * 0.85;
                cardShipHolder.rotation.y = nowSec * 0.85 + phase;
                cardShipHolder.rotation.z = Math.sin(nowSec * 2.2 + phase) * 0.18;
                cardShipHolder.rotation.x = Math.cos(nowSec * 1.7 + phase) * 0.10;
                cardShipHolder.position.y = Math.sin(nowSec * 2.8 + phase) * 0.14;

                if (cardRing && cfg.laser_color_hex) {
                    cardRing.material.color.setHex(cfg.laser_color_hex);
                    cardRing.rotation.z = nowSec * 0.9 + phase;
                }

                if (key !== 'default' && window.SeelenfunkeShipAnimator) {
                    window.SeelenfunkeShipAnimator.updateShipAnimation(modelNode, nowSec + phase, dt, {
                        boost: this.previewKey === key,
                        bank: cardShipHolder.rotation.z
                    });
                }

                cardRenderer.render(cardScene, cardCamera);
                const ctx2d = cvs.getContext('2d');
                if (ctx2d) {
                    ctx2d.clearRect(0, 0, cvs.width, cvs.height);
                    ctx2d.drawImage(cardRenderer.domElement, 0, 0, cvs.width, cvs.height);
                }
            }
        },

        setupThreeStage(wrap) {
            const THREE = window.THREE;
            const w = wrap.offsetWidth || 500;
            const h = wrap.offsetHeight || 320;

            scene = new THREE.Scene();
            camera = new THREE.PerspectiveCamera(44, w / h, 0.1, 200);
            camera.position.set(0, 2.2, 8.2);
            camera.lookAt(0, 0.1, 0);

            renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
            renderer.setSize(w, h, false);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
            wrap.innerHTML = '';
            wrap.appendChild(renderer.domElement);

            scene.add(new THREE.AmbientLight(0xffffff, 1.8));
            const dirLight1 = new THREE.DirectionalLight(0xffffff, 2.2);
            dirLight1.position.set(6, 10, 8);
            scene.add(dirLight1);

            const dirLight2 = new THREE.DirectionalLight(0xfbbf24, 1.4);
            dirLight2.position.set(-6, -4, 5);
            scene.add(dirLight2);

            const r1Geo = new THREE.RingGeometry(2.35, 2.52, 48);
            const r1Mat = new THREE.MeshBasicMaterial({ color: 0xf59e0b, side: THREE.DoubleSide, transparent: true, opacity: 0.55, blending: THREE.AdditiveBlending });
            holodeckRing1 = new THREE.Mesh(r1Geo, r1Mat);
            holodeckRing1.rotation.x = Math.PI / 2;
            holodeckRing1.position.y = -1.65;
            scene.add(holodeckRing1);

            const r2Geo = new THREE.RingGeometry(1.65, 1.72, 36);
            const r2Mat = new THREE.MeshBasicMaterial({ color: 0x38bdf8, side: THREE.DoubleSide, transparent: true, opacity: 0.45, blending: THREE.AdditiveBlending });
            holodeckRing2 = new THREE.Mesh(r2Geo, r2Mat);
            holodeckRing2.rotation.x = Math.PI / 2;
            holodeckRing2.position.y = -1.62;
            scene.add(holodeckRing2);

            const starCount = 120;
            const starGeo = new THREE.BufferGeometry();
            const starPos = new Float32Array(starCount * 3);
            for (let i = 0; i < starCount; i++) {
                starPos[i * 3]     = (Math.random() - 0.5) * 18;
                starPos[i * 3 + 1] = (Math.random() - 0.5) * 12;
                starPos[i * 3 + 2] = -2 - Math.random() * 14;
            }
            starGeo.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
            const starMat = new THREE.PointsMaterial({ color: 0xfde68a, size: 0.09, transparent: true, opacity: 0.75, blending: THREE.AdditiveBlending });
            starPoints = new THREE.Points(starGeo, starMat);
            scene.add(starPoints);

            shipHolder = new THREE.Group();
            scene.add(shipHolder);

            shieldGroup = new THREE.Group();
            const hangarShieldUniforms = {
                uTime: { value: 0.0 },
                uOpacity: { value: this.demoShieldActive ? 1.0 : 0.0 },
                uColorCore: { value: new THREE.Color(0x0ea5e9) },
                uColorRim: { value: new THREE.Color(0x7dd3fc) }
            };
            const hangarShieldVert = `
                uniform float uTime;
                varying vec3 vNormal;
                varying vec3 vViewDir;
                varying vec3 vLocalPos;
                void main() {
                    vLocalPos = position;
                    float breathe = sin(position.y * 1.8 - uTime * 2.1) * cos(position.x * 1.8 + uTime * 1.6) * 0.025;
                    vec3 displaced = position + normal * breathe;
                    vec4 mvPosition = modelViewMatrix * vec4(displaced, 1.0);
                    vNormal = normalize(normalMatrix * normal);
                    vViewDir = normalize(-mvPosition.xyz);
                    gl_Position = projectionMatrix * mvPosition;
                }
            `;
            const hangarShieldFrag = `
                uniform float uTime;
                uniform float uOpacity;
                uniform vec3 uColorCore;
                uniform vec3 uColorRim;
                varying vec3 vNormal;
                varying vec3 vViewDir;
                varying vec3 vLocalPos;
                void main() {
                    vec3 N = normalize(vNormal);
                    vec3 V = normalize(vViewDir);
                    float viewDot = clamp(abs(dot(N, V)), 0.0, 1.0);
                    float fresnel = pow(1.0 - viewDot, 2.35);
                    float outerFeather = smoothstep(0.0, 0.22, viewDot);
                    float softRim = fresnel * outerFeather;

                    vec3 p = normalize(vLocalPos);
                    float w1 = sin(p.y * 4.4 - uTime * 2.2 + sin(p.x * 3.5 + uTime * 1.4) * 1.1);
                    float w2 = cos(p.x * 4.0 + uTime * 1.6 - cos(p.z * 3.8 - uTime * 1.2) * 1.0);
                    float plasma = smoothstep(-0.5, 0.85, (w1 + w2) * 0.5);
                    float aurora = pow(0.5 + 0.5 * sin(p.y * 2.6 - uTime * 2.4 + p.x * 1.1), 2.8);

                    vec3 col = mix(uColorCore, uColorRim, smoothstep(0.12, 0.85, fresnel + plasma * 0.25));
                    col = mix(col, vec3(0.90, 0.98, 1.0), aurora * 0.32);

                    float centerVeil = (0.026 + plasma * 0.028) * outerFeather;
                    float rimAlpha = softRim * (0.62 + plasma * 0.26 + aurora * 0.20);
                    float alpha = (centerVeil + rimAlpha) * uOpacity;
                    gl_FragColor = vec4(col, clamp(alpha, 0.0, 0.88));
                }
            `;
            const hangarHaloFrag = `
                uniform float uOpacity;
                uniform vec3 uColorRim;
                varying vec3 vNormal;
                varying vec3 vViewDir;
                void main() {
                    vec3 N = normalize(vNormal);
                    vec3 V = normalize(vViewDir);
                    float viewDot = clamp(abs(dot(N, V)), 0.0, 1.0);
                    float halo = pow(1.0 - viewDot, 3.2) * smoothstep(0.0, 0.36, viewDot);
                    gl_FragColor = vec4(uColorRim, halo * 0.36 * uOpacity);
                }
            `;
            const sbMesh = new THREE.Mesh(
                new THREE.SphereGeometry(2.18, 48, 48),
                new THREE.ShaderMaterial({
                    uniforms: hangarShieldUniforms,
                    vertexShader: hangarShieldVert,
                    fragmentShader: hangarShieldFrag,
                    transparent: true,
                    depthWrite: false,
                    blending: THREE.AdditiveBlending
                })
            );
            const shMesh = new THREE.Mesh(
                new THREE.SphereGeometry(2.42, 36, 36),
                new THREE.ShaderMaterial({
                    uniforms: hangarShieldUniforms,
                    vertexShader: hangarShieldVert,
                    fragmentShader: hangarHaloFrag,
                    transparent: true,
                    depthWrite: false,
                    blending: THREE.AdditiveBlending
                })
            );
            shieldGroup.add(sbMesh, shMesh);
            shieldGroup.userData = { uniforms: hangarShieldUniforms, curOpacity: this.demoShieldActive ? 1.0 : 0.0 };
            shieldGroup.visible = this.demoShieldActive;
            shipHolder.add(shieldGroup);

            laserMat = new THREE.MeshBasicMaterial({ color: 0x38bdf8 });
            const boltGeo = new THREE.CylinderGeometry(0.06, 0.06, 0.75, 6);
            boltGeo.rotateX(Math.PI / 2);
            for (let i = 0; i < 20; i++) {
                const b = new THREE.Mesh(boltGeo, laserMat);
                b.visible = false;
                b.userData = { life: 0, vx: 0, vy: 0, vz: 0 };
                scene.add(b);
                laserBolts.push(b);
            }

            exhaustMat = new THREE.MeshBasicMaterial({ color: 0x60a5fa, transparent: true, opacity: 0.85, blending: THREE.AdditiveBlending });
            const exGeo = new THREE.SphereGeometry(0.11, 6, 6);
            for (let i = 0; i < 24; i++) {
                const p = new THREE.Mesh(exGeo, exhaustMat);
                p.visible = false;
                p.userData = { life: 0, maxLife: 0.4 };
                shipHolder.add(p);
                exhaustParticles.push(p);
            }

            this.loadShipIntoStage(this.currentShip);

            resizeObs = new ResizeObserver(() => this.resizeStage());
            resizeObs.observe(wrap);

            lastTs = performance.now();
            const loop = (now) => {
                animId = requestAnimationFrame(loop);
                if (this.tab !== 'ships') return;

                const dt = Math.min((now - lastTs) / 1000, 0.1);
                lastTs = now;

                // Always update the live 3D ship animations inside the visible cards!
                this.renderVisibleShipCards(now * 0.001, dt);

                if (!wrap.offsetParent || this.shipCategory === 'station') return;

                if (warpScale < 1.0) {
                    warpScale = Math.min(1.0, warpScale + dt * 3.8);
                    const ease = 1 - Math.pow(1 - warpScale, 3);
                    if (activeMesh) activeMesh.scale.setScalar(activeMesh.userData.targetScale * ease);
                }

                const isCustomShip = (this.previewKey && this.previewKey !== 'default');
                if (!this.isDragging) {
                    this.userRotY += dt * (isCustomShip ? 0.72 : 0.65);
                }
                if (boostSpinTimer > 0) {
                    boostSpinTimer = Math.max(0, boostSpinTimer - dt);
                    const rollProgress = (0.85 - boostSpinTimer) / 0.85;
                    shipHolder.rotation.z = isCustomShip
                        ? (rollProgress * Math.PI * 2)
                        : (Math.sin(rollProgress * Math.PI * 2) * 0.65);
                    shipHolder.rotation.x = isCustomShip ? (Math.sin(rollProgress * Math.PI) * -0.22) : 0;
                } else if (isCustomShip) {
                    shipHolder.rotation.z = Math.sin(now * 0.0026) * 0.22 + Math.cos(now * 0.0013) * 0.08;
                    shipHolder.rotation.x = Math.sin(now * 0.0019) * 0.11;
                } else {
                    shipHolder.rotation.z = Math.sin(now * 0.0022) * 0.12;
                    shipHolder.rotation.x = 0;
                }
                shipHolder.rotation.y = this.userRotY;
                shipHolder.position.y = Math.sin(now * 0.0032) * (isCustomShip ? 0.22 : 0.16);
                shipHolder.position.x = isCustomShip ? (Math.sin(now * 0.0016) * 0.18) : 0;

                if (isCustomShip && activeMesh && window.SeelenfunkeShipAnimator) {
                    window.SeelenfunkeShipAnimator.updateShipAnimation(activeMesh, now * 0.001, dt, {
                        boost: boostSpinTimer > 0,
                        bank: shipHolder.rotation.z
                    });
                }

                if (holodeckRing1) holodeckRing1.rotation.z += dt * 0.6;
                if (holodeckRing2) holodeckRing2.rotation.z -= dt * 1.1;
                if (shieldGroup && shieldGroup.userData && shieldGroup.userData.uniforms) {
                    shieldGroup.userData.uniforms.uTime.value = now * 0.001;
                    const targetOp = this.demoShieldActive ? 1.0 : 0.0;
                    shieldGroup.userData.curOpacity += (targetOp - shieldGroup.userData.curOpacity) * Math.min(1.0, dt * 8.0);
                    shieldGroup.userData.uniforms.uOpacity.value = shieldGroup.userData.curOpacity;
                    shieldGroup.visible = shieldGroup.userData.curOpacity > 0.01;
                    const sScale = 0.88 + 0.12 * shieldGroup.userData.curOpacity + Math.sin(now * 0.0025) * 0.015;
                    shieldGroup.scale.setScalar(sScale);
                }

                autoFireTimer -= dt;
                if (autoFireTimer <= 0) {
                    this.spawnShowroomLasers(false);
                    autoFireTimer = 1.65;
                }

                for (let i = 0; i < laserBolts.length; i++) {
                    const b = laserBolts[i];
                    if (!b.visible) continue;
                    b.userData.life -= dt;
                    if (b.userData.life <= 0) {
                        b.visible = false;
                    } else {
                        b.position.x += b.userData.vx * dt;
                        b.position.y += b.userData.vy * dt;
                        b.position.z += b.userData.vz * dt;
                    }
                }

                if (Math.random() < 0.75) {
                    const freeEx = exhaustParticles.find(p => !p.visible);
                    if (freeEx) {
                        freeEx.visible = true;
                        freeEx.userData.life = 0.35;
                        freeEx.position.set((Math.random() - 0.5) * 0.35, -0.15 + (Math.random() - 0.5) * 0.15, -1.35);
                        freeEx.scale.setScalar(1.0);
                    }
                }
                for (let i = 0; i < exhaustParticles.length; i++) {
                    const p = exhaustParticles[i];
                    if (!p.visible) continue;
                    p.userData.life -= dt;
                    if (p.userData.life <= 0) {
                        p.visible = false;
                    } else {
                        p.position.z -= dt * 5.5;
                        p.scale.setScalar(Math.max(0.1, p.userData.life / 0.35));
                    }
                }

                if (starPoints) {
                    const posAttr = starPoints.geometry.attributes.position;
                    for (let i = 0; i < posAttr.count; i++) {
                        let y = posAttr.getY(i) - dt * 3.5;
                        if (y < -6) y = 6;
                        posAttr.setY(i, y);
                    }
                    posAttr.needsUpdate = true;
                }

                renderer.render(scene, camera);
            };
            animId = requestAnimationFrame(loop);
        },

        spawnShowroomLasers(forceMaxSalvo) {
            if (!shipHolder || !laserBolts.length) return;
            const forward = new window.THREE.Vector3(0, 0, 1).applyAxisAngle(new window.THREE.Vector3(0, 1, 0), this.userRotY);
            const right = new window.THREE.Vector3(1, 0, 0).applyAxisAngle(new window.THREE.Vector3(0, 1, 0), this.userRotY);
            const streams = (this.currentShip.stats && this.currentShip.stats.salvo_streams) ? this.currentShip.stats.salvo_streams : (forceMaxSalvo ? 3 : 2);
            let offsets = [-0.42, 0.42];
            if (streams >= 5) offsets = [-0.95, -0.48, 0, 0.48, 0.95];
            else if (streams === 4) offsets = [-0.82, -0.32, 0.32, 0.82];
            else if (streams === 3 || forceMaxSalvo) offsets = [-0.58, 0, 0.58];

            offsets.forEach(off => {
                const bolt = laserBolts.find(b => !b.visible);
                if (!bolt) return;
                bolt.visible = true;
                bolt.userData.life = 0.48;
                bolt.position.set(
                    shipHolder.position.x + right.x * off + forward.x * 1.35,
                    shipHolder.position.y + 0.1,
                    shipHolder.position.z + right.z * off + forward.z * 1.35
                );
                bolt.rotation.y = this.userRotY;
                bolt.userData.vx = forward.x * 24;
                bolt.userData.vy = 0;
                bolt.userData.vz = forward.z * 24;
            });
        },

        loadShipIntoStage(cfg) {
            if (!shipHolder || !cfg || typeof window.THREE === 'undefined') return;
            const THREE = window.THREE;

            if (laserMat && cfg.laser_color_hex) laserMat.color.setHex(cfg.laser_color_hex);
            if (exhaustMat && cfg.trail_color_hex) exhaustMat.color.setHex(cfg.trail_color_hex);
            if (holodeckRing1 && cfg.laser_color_hex) holodeckRing1.material.color.setHex(cfg.laser_color_hex);
            if (shieldGroup) shieldGroup.visible = this.demoShieldActive;

            const attachModel = (modelNode) => {
                if (activeMesh) {
                    shipHolder.remove(activeMesh);
                }
                activeMesh = modelNode;
                warpScale = 0.15;
                activeMesh.scale.setScalar(activeMesh.userData.targetScale * warpScale);
                shipHolder.add(activeMesh);
            };

            if (modelCache[cfg.key]) {
                attachModel(modelCache[cfg.key]);
                return;
            }

            const LoaderClass = window.GLTFLoader || (window.THREE && window.THREE.GLTFLoader);
            if (!LoaderClass || !cfg.glb_url) return;

            const loader = new LoaderClass();
            loader.load(cfg.glb_url, (gltf) => {
                const wrapper = new THREE.Group();
                const rawScene = gltf.scene;
                const isBattleship = (cfg.category === 'battleship');

                if (cfg.is_vertex_colored) {
                    rawScene.rotation.set(Math.PI / 2, Math.PI, 0);
                    if (window.SeelenfunkeShipAnimator) {
                        window.SeelenfunkeShipAnimator.enhanceShipModel(THREE, cfg.key, rawScene);
                    } else {
                        const vMat = new THREE.MeshStandardMaterial({
                            vertexColors: true,
                            metalness: 0.35,
                            roughness: 0.35
                        });
                        rawScene.traverse(ch => {
                            if (ch.isMesh) {
                                ch.material = vMat;
                            }
                        });
                    }
                    wrapper.userData.targetScale = isBattleship ? 0.58 : 0.72;
                } else {
                    const box = new THREE.Box3().setFromObject(rawScene);
                    const center = box.getCenter(new THREE.Vector3());
                    rawScene.position.sub(center);
                    rawScene.rotation.set(0, -Math.PI / 2, 0);
                    wrapper.userData.targetScale = 3.2;
                }

                wrapper.add(rawScene);
                modelCache[cfg.key] = wrapper;
                if (this.previewKey === cfg.key) {
                    attachModel(wrapper);
                }
            });
        },

        resizeStage() {
            const wrap = this.$refs.hangarCanvasWrap;
            if (!wrap || !renderer || !camera) return;
            const w = wrap.offsetWidth;
            const h = wrap.offsetHeight;
            if (w < 20 || h < 20) return;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h, false);
        }
    };
};
</script>
@endonce
