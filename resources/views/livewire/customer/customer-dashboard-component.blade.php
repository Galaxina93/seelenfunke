<div x-data="{
        showFunkiModal: false,
        activeFunkiTab: 'vouchers',
        showActivateMagicModal: false,
        showFunkiZoomModal: false,
        zoomStageData: null,
        heroProfileModal: null,
        audioVolume: @js((int) $funkiAudioVolume),
        audioMuted: @js((bool) $funkiAudioMuted),
        audioKeepPlaying: @js((bool) $funkiAudioKeepPlaying),
        audioExpanded: false,
        localFadeRaf: null,
        init() {
            const sessionEnabled = sessionStorage.getItem('sf_funki_bgm_session_enabled') === '1';
            const savedVol = localStorage.getItem('sf_funki_audio_volume') ?? localStorage.getItem('sf_funki_bgm_vol');
            const savedMute = localStorage.getItem('sf_funki_audio_muted') ?? localStorage.getItem('sf_funki_bgm_muted');
            const savedKeep = localStorage.getItem('sf_funki_audio_keep_playing');
            if (savedVol !== null && !isNaN(Number(savedVol))) {
                this.audioVolume = Math.max(0, Math.min(100, Number(savedVol)));
            }
            if (sessionEnabled) {
                if (savedMute !== null) {
                    this.audioMuted = savedMute === '1' || savedMute === 'true';
                }
                if (savedKeep !== null) {
                    this.audioKeepPlaying = savedKeep === '1' || savedKeep === 'true';
                }
            } else {
                this.audioMuted = true;
                this.audioKeepPlaying = false;
            }
            window._sfFunkiVolume = this.audioVolume;
            window._sfFunkiMuted = this.audioMuted;

            if (window.SfFunkiBgm) {
                window.SfFunkiBgm.initFromServer(this.audioVolume, this.audioMuted, this.audioKeepPlaying);
                this.audioVolume = window.SfFunkiBgm.volume;
                this.audioMuted = window.SfFunkiBgm.muted;
                this.audioKeepPlaying = window.SfFunkiBgm.keepPlaying;
            }

            window.addEventListener('sf-funki-bgm-state', (e) => {
                if (!e.detail) return;
                this.audioVolume = e.detail.volume;
                this.audioMuted = e.detail.muted;
                this.audioKeepPlaying = e.detail.keepPlaying;
                window._sfFunkiVolume = this.audioVolume;
                window._sfFunkiMuted = this.audioMuted;
            });

            this.$watch('showFunkiModal', (val) => {
                if (val) {
                    this.showActivateMagicModal = false;
                    this.playFunkiBgm();
                } else {
                    this.pauseFunkiBgm();
                }
            });
            this.$watch('showActivateMagicModal', (val) => {
                if (val) {
                    this.showFunkiModal = false;
                    this.pauseFunkiBgm();
                }
            });
        },
        openFunkiModal(tab = 'vouchers') {
            if (!@js((bool) $isGameModeActive)) {
                this.openActivateMagicModal();
                return;
            }
            this.showActivateMagicModal = false;
            if (window.sfCockpitSound) window.sfCockpitSound('click');
            const baseUrl = '{{ route('customer.funki') }}';
            const targetUrl = (tab && tab !== 'vouchers') ? (baseUrl + '?tab=' + encodeURIComponent(tab)) : baseUrl;
            if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                window.Livewire.navigate(targetUrl);
            } else {
                window.location.href = targetUrl;
            }
        },
        closeFunkiModal() {
            this.showFunkiModal = false;
            if (window.sfCockpitSound) window.sfCockpitSound('back');
            this.pauseFunkiBgm();
        },
        openActivateMagicModal() {
            this.showFunkiModal = false;
            this.pauseFunkiBgm();
            this.showActivateMagicModal = true;
            if (window.sfCockpitSound) window.sfCockpitSound('click');
        },
        playFunkiBgm() {
            if (window.SfFunkiBgm) {
                window.SfFunkiBgm.onModalOpen();
                return;
            }
            const audio = document.getElementById('bgm_funki_game');
            if (!audio) return;
            if (this.audioMuted || Number(this.audioVolume) <= 0) {
                audio.volume = 0;
                return;
            }
            const target = Math.max(0, Math.min(1, (Number(this.audioVolume) / 100) * 0.65));
            audio.volume = 0.001;
            const p = audio.play();
            if (p && typeof p.catch === 'function') p.catch(() => {});
            const start = performance.now();
            const step = (now) => {
                const t = Math.min(1, (now - start) / 850);
                audio.volume = target * t;
                if (t < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        },
        pauseFunkiBgm() {
            if (window.SfFunkiBgm) {
                window.SfFunkiBgm.onModalClose();
                return;
            }
            if (this.audioKeepPlaying) return;
            const audio = document.getElementById('bgm_funki_game');
            if (!audio || audio.paused) return;
            const startVol = audio.volume;
            const start = performance.now();
            const step = (now) => {
                const t = Math.min(1, (now - start) / 850);
                audio.volume = Math.max(0, startVol * (1 - t));
                if (t < 1) {
                    requestAnimationFrame(step);
                } else {
                    audio.volume = 0;
                    try { audio.pause(); } catch (e) {}
                }
            };
            requestAnimationFrame(step);
        },
        toggleMute() {
            this.audioMuted = !this.audioMuted;
            if (!this.audioMuted && Number(this.audioVolume) === 0) {
                this.audioVolume = 35;
            }
            try {
                if (!this.audioMuted) {
                    sessionStorage.setItem('sf_funki_bgm_session_enabled', '1');
                } else {
                    sessionStorage.removeItem('sf_funki_bgm_session_enabled');
                }
            } catch (e) {}
            if (window.SfFunkiBgm) {
                window.SfFunkiBgm.setMuted(this.audioMuted, false);
                this.audioKeepPlaying = window.SfFunkiBgm.keepPlaying;
            }
            this.applyAudioState(true);
            if (!this.audioMuted && window.sfCockpitSound) {
                window.sfCockpitSound('click');
            }
        },
        onVolumeChange(saveToServer = false) {
            this.audioVolume = Math.max(0, Math.min(100, Number(this.audioVolume)));
            if (this.audioVolume === 0) {
                this.audioMuted = true;
            } else if (this.audioMuted) {
                this.audioMuted = false;
            }
            try {
                if (!this.audioMuted && this.audioVolume > 0) {
                    sessionStorage.setItem('sf_funki_bgm_session_enabled', '1');
                } else {
                    sessionStorage.removeItem('sf_funki_bgm_session_enabled');
                }
            } catch (e) {}
            if (window.SfFunkiBgm) {
                window.SfFunkiBgm.setVolume(this.audioVolume, false);
                this.audioKeepPlaying = window.SfFunkiBgm.keepPlaying;
            }
            this.applyAudioState(saveToServer);
        },
        toggleKeepPlaying() {
            if (window.sfCockpitSound) window.sfCockpitSound('click');
            if (window.SfFunkiBgm) {
                window.SfFunkiBgm.setKeepPlaying(this.audioKeepPlaying, false);
            }
            this.applyAudioState(true);
        },
        applyAudioState(saveToServer = false) {
            window._sfFunkiVolume = Number(this.audioVolume);
            window._sfFunkiMuted = Boolean(this.audioMuted);
            try {
                localStorage.setItem('sf_funki_bgm_vol', String(this.audioVolume));
                localStorage.setItem('sf_funki_bgm_muted', this.audioMuted ? '1' : '0');
                localStorage.setItem('sf_funki_audio_volume', String(this.audioVolume));
                localStorage.setItem('sf_funki_audio_muted', this.audioMuted ? '1' : '0');
                localStorage.setItem('sf_funki_audio_keep_playing', this.audioKeepPlaying ? '1' : '0');
            } catch (e) {}
            if (saveToServer) {
                if (window.SfFunkiBgm && typeof window.SfFunkiBgm.persistToServer === 'function') {
                    window.SfFunkiBgm.persistToServer();
                } else {
                    this.$wire.saveFunkiAudioSettings(Number(this.audioVolume), Boolean(this.audioMuted), Boolean(this.audioKeepPlaying));
                }
            }
        },
        openFunkiZoom(stageData) {
            if (window.sfCockpitSound) window.sfCockpitSound('click');
            this.zoomStageData = stageData;
            this.showFunkiZoomModal = true;
        }
     }"
     @open-funki-modal.window="openFunkiModal(($event.detail && $event.detail.tab) ? $event.detail.tab : 'vouchers')"
     @magic-activated-complete.window="showActivateMagicModal = false; window.location.href = '{{ route('customer.funki') }}'"
     class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6 lg:py-7 animate-fade-in-up">

    {{-- Alerts & Profile Completion Banner --}}
    @if(count($profileSteps) > 0)
        <div class="mb-6 p-4 bg-red-500/5 border border-red-500/20 rounded-2xl sm:rounded-3xl flex flex-col sm:flex-row items-center justify-between gap-4 shadow-[0_0_20px_rgba(239,68,68,0.05)]">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500">
                    <x-heroicon-s-exclamation-triangle class="w-5 h-5" />
                </div>
                <div>
                    <h4 class="text-red-700 dark:text-white font-bold text-sm">Profil unvollständig</h4>
                    <p class="text-red-600/80 dark:text-gray-400 text-xs mt-0.5">Bitte vervollständige deine Daten für eine reibungslose Abwicklung zukünftiger Käufe.</p>
                </div>
            </div>
            @foreach($profileSteps as $step)
                <button @click="{!! $step['action'] !!}" class="w-full sm:w-auto px-4 py-2 bg-red-500 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:scale-105 transition-all shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:bg-red-600 cursor-pointer">
                    {{ $step['label'] }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Welcome & Stats Header Grid (Mit dem goldenen Funki-Profilbild-Kreis wie in Screenshot 1) --}}
    @php
        $hasCustomPhoto = auth()->user()->profile && !empty(auth()->user()->profile->photo_path);
        $lvlCfg = $currentLevelConfig ?: \App\Services\Gamification\GameConfig::getLevelConfig($funkiLevel ?: 1);
        $lvlImage = asset($lvlCfg['image_path'] ?? 'shop/customer/gamification/models/images/original/funki_lvl_1_rags.png');
        $lvlCircleCss = $lvlCfg['circle_css'] ?? '';
        $lvlVideoUrl = $lvlCfg['video_url'] ?? null;
        $lvlVideoFilter = $lvlCfg['video_filter_css'] ?? '';
        $lvlVideoTransform = $lvlCfg['video_transform_css'] ?? '';
        $lvlAccentHex = $lvlCfg['accent_hex'] ?? '#c5a059';
        $arcText = $epBottomArcText ?: ($isMaxLevel ? '100% • MAXIMALES LEVEL ERREICHT' : ($progressPercentage . '% • NOCH ' . number_format($missingSparks, 0, ',', '.') . ' FUNKEN'));
    @endphp
    <div class="relative overflow-hidden bg-white dark:bg-gradient-to-r dark:from-gray-900 dark:via-gray-900 dark:to-indigo-950/20 border border-gray-200 dark:border-gray-800 rounded-2xl sm:rounded-3xl px-5 py-4 sm:px-6 sm:py-5 md:px-7 md:py-5 mb-6 shadow-sm dark:shadow-xl">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-amber-500/5 via-transparent to-transparent pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4 sm:gap-6">
            <div class="flex flex-col sm:flex-row items-center sm:items-center gap-4 sm:gap-5 text-center sm:text-left">

                {{-- PROFILBILD: Wenn kein eigenes Foto hinterlegt ist -> Goldener Funki-Kreis (Stufe 1-10 + kostenloser Stufen-Hintergrund wie in Screenshot 1) --}}
                @if($hasCustomPhoto)
                    @php
                        $pp = auth()->user()->profile->photo_path;
                        $src = (str_starts_with($pp, 'shopverwaltung/images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/'))
                               ? asset($pp) : (\Illuminate\Support\Str::startsWith($pp, 'shop/') ? asset($pp) : Storage::url($pp));
                    @endphp
                    <button type="button"
                            @click="{{ !$isGameModeActive ? 'openActivateMagicModal()' : "openFunkiModal('vouchers')" }}"
                            title="Klicken, um Funki (Level {{ $funkiLevel }}) & deine Seelenfunken-Gutscheine zu öffnen"
                            class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-gray-100 dark:bg-gray-950 border-2 border-[#C5A059] overflow-hidden flex items-center justify-center shrink-0 shadow-md group cursor-pointer hover:scale-105 transition-transform">
                        <img src="{{ $src }}" class="w-full h-full object-cover">
                        <span class="absolute bottom-0 inset-x-0 bg-gray-950/85 text-[#f5dca0] text-[8px] font-black uppercase tracking-wider py-0.5 text-center">
                            ✨ Lvl. {{ $funkiLevel }}
                        </span>
                    </button>
                @else
                    {{-- GOLDENER FUNKI PROFIL-KREIS (EXAKTES DESIGN AUS SCREENSHOT 1) --}}
                    <button type="button"
                            @click="{{ !$isGameModeActive ? 'openActivateMagicModal()' : "openFunkiModal('vouchers')" }}"
                            title="Klicken, um Funki (Level 1–10) & deine Seelenfunken-Gutscheine anzusehen"
                            class="relative w-28 h-28 sm:w-32 sm:h-32 flex items-center justify-center shrink-0 group cursor-pointer focus:outline-none">
                        {{-- Äußere dezente Radar-/Runenringe --}}
                        <div class="absolute inset-[-6px] rounded-full border border-dashed border-[#C5A059]/40 pointer-events-none"></div>
                        <div class="absolute inset-[-12px] rounded-full border border-dotted border-[#C5A059]/25 animate-[spin_28s_linear_infinite] pointer-events-none"></div>

                        {{-- XP FORTSCHRITTS-RING --}}
                        <svg class="absolute inset-0 w-full h-full -rotate-90 pointer-events-none z-20" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="47.5" fill="none" stroke="#e2e8f0" class="dark:stroke-gray-800" stroke-width="2.5"></circle>
                            <circle cx="50" cy="50" r="47.5" fill="none" stroke="#c5a059" stroke-width="4" stroke-linecap="round"
                                    stroke-dasharray="298.45"
                                    stroke-dashoffset="{{ 298.45 - ($progressPercentage / 100) * 298.45 }}"
                                    class="transition-all duration-1000 ease-out"></circle>
                        </svg>

                        {{-- GEBOGENE LEVEL-ANZEIGE UNTEN IM KREIS ("100% • MAXIMALES LEVEL ERREICHT" / "XX% • NOCH XX FUNKEN") --}}
                        <svg class="absolute inset-0 w-full h-full pointer-events-none z-30 overflow-visible" viewBox="0 0 200 200">
                            <defs>
                                <path id="sf-dash-avatar-arc" d="M 22,128 A 83,83 0 0,0 178,128" fill="none" />
                            </defs>
                            <path d="M 32,142 A 83,83 0 0,0 168,142" fill="none" stroke="rgba(11,15,25,0.82)" stroke-width="15" stroke-linecap="round" />
                            <text class="fill-[#f5dca0] font-black uppercase" style="font-size: 8.2px; letter-spacing: 0.14em;">
                                <textPath href="#sf-dash-avatar-arc" startOffset="50%" text-anchor="middle">
                                    {{ $arcText }}
                                </textPath>
                            </text>
                        </svg>

                        {{-- INNERER HINTERGRUND (WIRD PRO STUFE AUTOMATISCH EPISCHER) + 2D FUNKI --}}
                        <div style="{{ $lvlCircleCss }}"
                             class="absolute inset-2 rounded-full bg-white dark:bg-[#13151e] border-2 border-[#C5A059] shadow-[0_0_25px_rgba(197,160,89,0.35)] flex items-center justify-center overflow-hidden z-10 transition-transform duration-300 group-hover:scale-[1.03]">
                            @if(!empty($lvlVideoUrl))
                                <video src="{{ asset(ltrim($lvlVideoUrl, '/')) }}"
                                       style="{{ $lvlVideoFilter ? 'filter: ' . $lvlVideoFilter . ';' : '' }} {{ $lvlVideoTransform ? 'transform: ' . $lvlVideoTransform . ';' : '' }}"
                                       autoplay loop muted playsinline
                                       class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                            @endif
                            <div class="absolute inset-[-18%] rounded-full opacity-50 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                 style="background: conic-gradient(from 0deg, transparent 0%, {{ $lvlAccentHex }}55 25%, transparent 50%, {{ $lvlAccentHex }}44 75%, transparent 100%);"></div>
                            <img src="{{ $lvlImage }}" alt="Funki Level {{ $displayFunkiLevel }}" class="relative z-10 w-full h-full object-contain p-2.5 drop-shadow-[0_8px_16px_rgba(0,0,0,0.55)]">
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center z-20">
                                <span class="px-2.5 py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[9px] uppercase tracking-widest shadow-lg">
                                    {{ !$isGameModeActive ? '✨ Magie aktivieren' : 'Funki öffnen' }}
                                </span>
                            </div>
                        </div>

                        {{-- Kleines Level-Schild oben rechts am Kreis --}}
                        <span class="absolute -top-1 -right-1 z-40 w-7 h-7 rounded-full bg-gradient-to-b from-amber-300 via-[#C5A059] to-amber-600 text-gray-950 border border-amber-100 shadow-md flex items-center justify-center font-black text-[11px]">
                            {{ $funkiLevel }}
                        </span>
                    </button>
                @endif

                <div>
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <span class="text-primary text-[10px] sm:text-xs font-black uppercase tracking-[0.2em]">Mitgliederbereich</span>
                        <button type="button"
                                @click="{{ !$isGameModeActive ? 'openActivateMagicModal()' : "openFunkiModal('vouchers')" }}"
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-500/15 border border-[#C5A059]/50 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-wider hover:bg-[#C5A059] hover:text-gray-950 transition-colors cursor-pointer">
                            <span>✨ Funki Lvl. {{ $funkiLevel }}</span>
                            <span>•</span>
                            <span>{{ number_format($funkenBalance, 0, ',', '.') }} Funken</span>
                        </button>
                        @php
                            $defaultLevelRank = \App\Services\Gamification\GameConfig::getLevelTitles()[$funkiLevel]['name'] ?? 'Kleiner Seelenfunke';
                            $displayRankLabel = $currentRankName ?: ($isGameModeActive ? $defaultLevelRank : null);
                        @endphp
                        @if($displayRankLabel)
                            <button type="button"
                                    @click="openFunkiModal('titles')"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full {{ $activeTitleKey === 'seelengott' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 border-amber-200' : 'bg-gray-100 dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300' }} border text-[10px] font-black uppercase tracking-wider hover:border-[#C5A059] transition-colors cursor-pointer">
                                <span>{{ $activeTitleKey === 'seelengott' ? '👑' : '🏆' }}</span>
                                <span>{{ $displayRankLabel }}</span>
                            </button>
                        @endif
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mt-1 tracking-tight">
                        Hallo, {{ auth()->user()->first_name }}
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5 max-w-lg leading-relaxed">
                        Klicke auf dein Funki-Profilbild, um deine Evolutionsstufen (Level 1–10) und deine Rabatt-Gutscheine (bis 15 %) anzusehen.
                    </p>
                </div>
            </div>

            {{-- Quick Stats Row --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-3 shrink-0">
                <button type="button"
                        @click="{{ !$isGameModeActive ? 'openActivateMagicModal()' : "openFunkiModal('vouchers')" }}"
                        class="text-left bg-amber-500/10 dark:bg-amber-500/15 hover:bg-amber-500/20 backdrop-blur-md border border-[#C5A059]/40 rounded-xl px-3.5 py-2 min-w-[125px] shadow-sm transition-colors cursor-pointer">
                    <span class="text-amber-800 dark:text-amber-300 text-[9px] font-black uppercase tracking-widest block mb-0.5">Seelenfunken</span>
                    <span class="text-amber-900 dark:text-[#f5dca0] font-mono text-xs font-black flex items-center gap-1">
                        {{ number_format($funkenBalance, 0, ',', '.') }} ✨
                        <span class="text-[9px] font-sans font-bold text-amber-700 dark:text-amber-400/80">(Einlösen)</span>
                    </span>
                </button>

                <div x-data="{ copied: false, code: '{{ strtoupper(explode('-', auth()->guard('customer')->id())[0]) }}' }"
                     class="bg-gray-50/80 dark:bg-gray-950/60 backdrop-blur-md border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2 min-w-[125px] shadow-sm dark:shadow-inner cursor-pointer hover:border-gray-300 dark:hover:border-gray-700 transition-colors"
                     @click="window.sfCopyText(code); copied = true; setTimeout(() => copied = false, 2200); $dispatch('notify', { message: 'Kundennummer ' + code + ' in die Zwischenablage kopiert!', type: 'success' })">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block mb-0.5">Kundennummer</span>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-primary font-mono text-xs font-bold tracking-wider" x-text="copied ? 'Kopiert! ✓' : code" :class="copied ? 'text-emerald-600 dark:text-emerald-400' : 'text-primary'"></span>
                        <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-primary transition-colors" />
                        <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                    </div>
                </div>

                @if(count($activeGiftVouchers) > 0)
                    <div class="bg-amber-500/10 dark:bg-amber-500/20 backdrop-blur-md border border-amber-500/30 rounded-xl px-3.5 py-2 min-w-[125px] shadow-sm col-span-2 sm:col-span-1">
                        <span class="text-amber-800 dark:text-amber-300 text-[9px] font-black uppercase tracking-widest block mb-0.5">Guthaben</span>
                        <span class="text-amber-900 dark:text-amber-200 font-mono text-xs font-black">{{ number_format($activeGiftVouchers->sum('current_balance') / 100, 2, ',', '.') }} €</span>
                    </div>
                @else
                    <div class="bg-gray-50/80 dark:bg-gray-950/60 backdrop-blur-md border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2 min-w-[125px] shadow-sm dark:shadow-inner col-span-2 sm:col-span-1">
                        <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block mb-0.5">Konto-Typ</span>
                        <span class="text-gray-900 dark:text-white text-xs font-bold flex items-center gap-1.5">
                            @if(isset(auth()->user()->profile) && auth()->user()->profile->is_business)
                                <x-heroicon-s-briefcase class="w-3.5 h-3.5 text-amber-500" /> Gewerblich
                            @else
                                <x-heroicon-s-user class="w-3.5 h-3.5 text-blue-400" /> Privatkunde
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- BANNER NACH DEM 1. KAUF: "MAGIE AKTIVIEREN" (FALLS NOCH NICHT AKTIVIERT) --}}
    @if($hasCompletedOrder && !$isGameModeActive)
        <div class="mb-6 p-5 sm:p-6 bg-gradient-to-r from-amber-500/15 via-primary/10 to-amber-500/5 border-2 border-[#C5A059]/50 rounded-3xl shadow-md flex flex-col lg:flex-row items-center justify-between gap-5">
            <div class="flex items-center gap-4 text-center sm:text-left flex-col sm:flex-row">
                <img src="{{ asset('shop/customer/gamification/models/images/original/funki_lvl_1_rags.png') }}" alt="Funki Level 1" class="w-16 h-16 object-contain drop-shadow-md shrink-0">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-amber-500/20 text-amber-800 dark:text-amber-300 text-[10px] font-black uppercase tracking-widest mb-1">
                        ✦ Durch deinen Einkauf freigeschaltet
                    </span>
                    <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white">
                        Dein Begleiter Funki (Level {{ $funkiLevel }}) & {{ number_format($funkenBalance, 0, ',', '.') }} ✨ Seelenfunken warten auf dich!
                    </h3>
                    <p class="text-gray-600 dark:text-gray-300 text-xs mt-0.5 max-w-2xl">
                        Aktiviere jetzt kostenlos das Seelenfunken-Sammelspiel: Finde geheime Funken auf unseren Seiten, sammle bei jedem Artikelkauf automatisch Extra-Funken und tausche sie gegen <strong>5 %, 10 % oder 15 % Rabatt-Gutscheine</strong> ein!
                    </p>
                </div>
            </div>
            <button type="button"
                    @click="openActivateMagicModal()"
                    class="w-full lg:w-auto shrink-0 px-7 py-3.5 bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 hover:from-amber-300 hover:to-amber-400 text-gray-950 font-black uppercase tracking-widest text-xs rounded-2xl shadow-[0_8px_25px_rgba(245,158,11,0.35)] hover:scale-105 transition-all flex items-center justify-center gap-2.5 cursor-pointer">
                <span>✨ Magie aktivieren</span>
            </button>
        </div>
    @endif

    {{-- Aktives Einkaufsguthaben Banner --}}
    @if(count($activeGiftVouchers) > 0)
        @php
            $voucherCount = count($activeGiftVouchers);
            $totalBalanceFormatted = number_format($activeGiftVouchers->sum('current_balance') / 100, 2, ',', '.');
        @endphp
        <div class="mb-6 p-4 sm:p-5 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-amber-500/30 rounded-2xl sm:rounded-3xl shadow-sm dark:shadow-xl">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="max-w-md">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-800 dark:text-amber-300 text-[9px] font-black uppercase tracking-widest">Verfügbares Einkaufsguthaben</span>
                        @if($voucherCount > 1)
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-900 dark:text-amber-200">
                                {{ $voucherCount }} Codes
                            </span>
                        @endif
                    </div>
                    <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white mt-0.5">
                        {{ $totalBalanceFormatted }} € Guthaben bereit zum Einlösen
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 text-xs mt-0.5">
                        Einfach im Warenkorb oder Checkout eingeben, um den Betrag abzuziehen.
                    </p>
                </div>

                {{-- Scrollbarer Code-Bereich --}}
                <div class="w-full lg:w-auto lg:min-w-[320px] max-w-lg">
                    <div class="max-h-36 overflow-y-auto pr-1.5 space-y-1.5 overscroll-contain"
                         style="scrollbar-width: thin; scrollbar-color: rgba(245, 158, 11, 0.4) transparent;">
                        @foreach($activeGiftVouchers as $gv)
                            <div x-data="{ copied: false }" class="bg-white dark:bg-gray-900 border border-amber-300/80 rounded-xl px-3 py-2 flex items-center justify-between gap-3 text-xs shadow-sm hover:border-amber-400 transition-colors">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-mono font-bold text-gray-900 dark:text-white tracking-wider truncate">{{ $gv->code }}</span>
                                    <span class="text-amber-600 dark:text-amber-400 font-bold shrink-0">({{ number_format($gv->current_balance / 100, 2, ',', '.') }} €)</span>
                                </div>
                                <button @click="window.sfCopyText('{{ $gv->code }}'); copied = true; setTimeout(() => copied = false, 2500); $dispatch('notify', { message: 'Guthaben-Code {{ $gv->code }} in die Zwischenablage kopiert!', type: 'success' })"
                                        type="button" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white font-bold text-[11px] cursor-pointer shrink-0 ml-1">
                                    <span x-show="!copied">Code kopieren</span>
                                    <span x-show="copied" style="display: none;" class="text-emerald-500 font-bold">Kopiert! ✓</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Obere Zeile: Letzte Bestellungen (2/3) & Profildaten (1/3) nebeneinander --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch mb-6">
        {{-- Letzte Bestellungen (2/3 width on desktop) --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-900/40 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden h-full flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white">Letzte Bestellungen</h3>
                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">Deine jüngsten Einkäufe auf einen Blick.</p>
                        </div>
                        <a href="{{ route('customer.orders') }}" wire:navigate class="text-xs font-black uppercase tracking-widest text-primary hover:opacity-80 transition-colors flex items-center gap-1.5">
                            Alle ansehen <x-heroicon-m-chevron-right class="w-4 h-4" />
                        </a>
                    </div>

                    @if(count($recentOrders) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[540px]">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-800/60 pb-2.5">
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 pl-4 pr-3">Bestellung</th>
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Datum</th>
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Betrag</th>
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Status</th>
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 pl-3 pr-4 sm:pr-5 text-right">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/40">
                                    @foreach($recentOrders as $order)
                                        <tr class="group hover:bg-gray-50/80 dark:hover:bg-gray-800/20 transition-colors rounded-xl">
                                            <td class="py-3 pl-4 pr-3 rounded-l-xl">
                                                <span class="text-gray-900 dark:text-white font-mono text-xs font-bold">#{{ $order->order_number }}</span>
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="text-gray-600 dark:text-gray-400 text-xs">{{ $order->created_at->format('d.m.Y') }}</span>
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="text-primary font-mono text-xs font-bold">{{ number_format($order->total_price / 100, 2, ',', '.') }} €</span>
                                            </td>
                                            <td class="py-3 px-3">
                                                @php
                                                    $statusClass = match($order->status) {
                                                        'pending' => 'bg-yellow-500/10 border-yellow-500/20 text-yellow-600 dark:text-yellow-500',
                                                        'processing' => 'bg-blue-500/10 border-blue-500/20 text-blue-600 dark:text-blue-400',
                                                        'shipped' => 'bg-purple-500/10 border-purple-500/20 text-purple-600 dark:text-purple-400',
                                                        'completed' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400',
                                                        'cancelled' => 'bg-red-500/10 border-red-500/20 text-red-600 dark:text-red-500',
                                                        'refunded' => 'bg-gray-500/10 border-gray-500/20 text-gray-600 dark:text-gray-400',
                                                        default => 'bg-gray-500/10 border-gray-500/20 text-gray-600 dark:text-gray-400',
                                                    };
                                                    $statusLabel = match($order->status) {
                                                        'pending' => 'Im Atelier',
                                                        'processing' => 'In der Manufaktur',
                                                        'shipped' => 'Unterwegs zu dir',
                                                        'completed' => 'Angekommen',
                                                        'cancelled' => 'Storniert',
                                                        'refunded' => 'Erstattet',
                                                        default => ucfirst($order->status),
                                                    };
                                                @endphp
                                                <span class="inline-flex px-2 py-0.5 border rounded-full text-[9px] font-black uppercase tracking-wider {{ $statusClass }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </td>
                                            <td class="py-3 pl-3 pr-4 sm:pr-5 text-right rounded-r-xl">
                                                <a href="{{ route('customer.orders', ['order' => $order->id]) }}" wire:navigate title="Bestellung ansehen" class="inline-flex items-center justify-center p-1.5 bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary hover:border-primary/30 transition-colors">
                                                    <x-heroicon-m-eye class="w-4 h-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-6 bg-gray-50 dark:bg-gray-950/40 rounded-2xl border border-gray-200 dark:border-gray-800/40 shadow-sm dark:shadow-inner">
                            <x-heroicon-o-shopping-bag class="w-8 h-8 text-gray-400 dark:text-gray-600 mx-auto mb-2" />
                            <h4 class="text-gray-900 dark:text-white font-bold text-sm">Noch keine Bestellungen</h4>
                            <p class="text-gray-500 text-xs mt-0.5 mb-3">Du hast bisher noch keine Einkäufe getätigt.</p>
                            <a href="{{ route('shop') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary text-gray-900 rounded-xl text-xs font-black uppercase tracking-widest hover:scale-105 transition-all shadow-sm">
                                Zum Shop <x-heroicon-m-shopping-cart class="w-4 h-4" />
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Profildaten (1/3 width on desktop) --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-900/40 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden h-full flex flex-col justify-between">
                <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-full blur-2xl pointer-events-none"></div>
                <div>
                    <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white mb-3.5 flex items-center gap-2">
                        <x-heroicon-s-identification class="w-5 h-5 text-primary" /> Profildaten
                    </h3>

                    <div class="space-y-2.5">
                        {{-- Personal Data Summary --}}
                        <div class="p-2.5 bg-gray-50 dark:bg-gray-950/40 rounded-xl border border-gray-200 dark:border-gray-800/60">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Name & E-Mail</span>
                            <span class="text-gray-900 dark:text-white text-xs font-bold block mt-0.5">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                            <span class="text-gray-600 dark:text-gray-400 text-xs font-medium block mt-0.5 truncate">{{ auth()->user()->email }}</span>
                        </div>

                        {{-- Address Summary --}}
                        <div class="p-2.5 bg-gray-50 dark:bg-gray-950/40 rounded-xl border border-gray-200 dark:border-gray-800/60">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Liefer- & Rechnungsadresse</span>
                            @if(isset(auth()->user()->profile) && !empty(auth()->user()->profile->street))
                                <span class="text-gray-900 dark:text-white text-xs font-bold block mt-0.5 truncate">
                                    {{ auth()->user()->profile->street }} {{ auth()->user()->profile->house_number }}
                                </span>
                                <span class="text-gray-600 dark:text-gray-400 text-xs font-medium block mt-0.5 truncate">
                                    {{ auth()->user()->profile->postal }} {{ auth()->user()->profile->city }} ({{ auth()->user()->profile->country === 'DE' ? 'Deutschland' : auth()->user()->profile->country }})
                                </span>
                            @else
                                <span class="text-red-400/80 text-xs font-bold block mt-0.5">Keine Adresse hinterlegt</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-3.5">
                    <button @click="$dispatch('open-profile-modal', {tab: 'profile'})" class="w-full py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-950 dark:hover:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 rounded-xl text-xs font-black uppercase tracking-widest transition-colors shadow-sm cursor-pointer">
                        Profil bearbeiten
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Untere Zeile: 3 Kacheln nebeneinander (Freunde werben, Seelenfunken & Gutscheine, Hilfe & Support) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">

        {{-- Kachel 1: Freunde werben --}}
        @if($hasCompletedOrder)
            @php
                $dashRefLink = route('shop', ['ref' => $referralCode, 'coupon' => $referralCode]);
                $dashWaMsg = "Hey! 🥰 Schau unbedingt mal bei Seelenfunke vorbei: Die fertigen so wunderschöne, personalisierte Unikate und Geschenkideen an! ✨ Mit meinem persönlichen Code " . $referralCode . " sparst du jetzt 10% auf dein eigenes Unikat: " . $dashRefLink;
                $dashWaUrl = 'https://api.whatsapp.com/send?text=' . urlencode($dashWaMsg);
            @endphp
            <div x-data="{
                    linkCopied: false,
                    codeCopied: false,
                    shareUrl: '{{ $dashRefLink }}',
                    couponCode: '{{ $referralCode }}',
                    copyLink() {
                        window.sfCopyText(this.shareUrl);
                        this.linkCopied = true;
                        setTimeout(() => this.linkCopied = false, 2500);
                        this.$dispatch('notify', { message: 'Empfehlungs-Link in die Zwischenablage kopiert!', type: 'success' });
                    },
                    copyCode() {
                        window.sfCopyText(this.couponCode);
                        this.codeCopied = true;
                        setTimeout(() => this.codeCopied = false, 2500);
                        this.$dispatch('notify', { message: 'Empfehlungs-Code ' + this.couponCode + ' in die Zwischenablage kopiert!', type: 'success' });
                    }
                 }"
                 class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-primary/10 border-2 border-primary/30 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden flex flex-col justify-between h-full">
                <div>
                    <div class="flex items-center justify-between mb-2.5">
                        <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-lg">🎁</span> Freunde werben
                        </h3>
                        <span class="text-[9px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-300 bg-amber-500/15 border border-amber-500/30 px-2 py-0.5 rounded-full">
                            5 € Guthaben
                        </span>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 text-xs leading-relaxed mb-3">
                        Freunde erhalten <strong>10 % Rabatt</strong>, du bekommst <strong>5 € Einkaufsguthaben</strong> für jede erfolgreiche Bestellung!
                    </p>

                    {{-- Rabattcode Box --}}
                    <div class="bg-white/80 dark:bg-gray-950/80 rounded-xl p-2.5 border border-amber-200/60 dark:border-gray-800 flex items-center justify-between text-xs mb-3 shadow-sm">
                        <div class="min-w-0">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">Dein Rabattcode</span>
                            <span class="font-mono font-bold text-primary truncate block">{{ $referralCode }}</span>
                        </div>
                        <button @click="copyCode()" type="button" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white font-bold text-[11px] cursor-pointer shrink-0 ml-2">
                            <span x-show="!codeCopied">Code kopieren</span>
                            <span x-show="codeCopied" style="display: none;" class="text-emerald-500 font-bold">Kopiert! ✓</span>
                        </button>
                    </div>

                    @if($successfulReferralsCount > 0)
                        <div class="mb-3 px-3 py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-between text-[11px]">
                            <span class="font-bold text-emerald-800 dark:text-emerald-300">🎉 {{ $successfulReferralsCount }} {{ $successfulReferralsCount === 1 ? 'Freund' : 'Freunde' }} geworben</span>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">+{{ number_format($totalReferralEarnedCents / 100, 2, ',', '.') }} €</span>
                        </div>
                    @endif
                </div>

                {{-- Action Buttons --}}
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <a href="{{ $dashWaUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       style="background-color: #25D366 !important; color: #ffffff !important;"
                       onmouseover="this.style.backgroundColor='#20bd5a'"
                       onmouseout="this.style.backgroundColor='#25D366'"
                       class="py-2 px-3 rounded-xl font-bold text-xs shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg style="width: 14px; height: 14px; fill: #ffffff !important;" class="shrink-0" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span style="color: #ffffff !important; font-weight: 700;">WhatsApp</span>
                    </a>
                    <button @click="copyLink()"
                            type="button"
                            class="py-2 px-3 rounded-xl bg-white dark:bg-gray-950 hover:bg-gray-50 dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 font-bold text-xs shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <span x-show="!linkCopied">Link kopieren</span>
                        <span x-show="linkCopied" style="display: none;" class="text-emerald-500 font-bold">Kopiert! ✓</span>
                    </button>
                </div>
                <p class="mt-2 text-[10px] text-gray-500 dark:text-gray-400 leading-snug">
                    * 10&nbsp;% Rabatt für Neukunden ab der 1.&nbsp;Bestellung (keine Eigenwerbung). 5,00&nbsp;€ Gutschrift nach Zahlungseingang, keine Barauszahlung.
                </p>
            </div>
        @else
            {{-- Teaser für Neuregistrierte Kunden vor dem ersten Einkauf --}}
            <div class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-primary/10 border border-dashed border-primary/40 rounded-3xl p-5 sm:p-6 shadow-sm relative overflow-hidden flex flex-col justify-between h-full">
                <div>
                    <div class="flex items-center justify-between mb-2.5">
                        <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-lg">🎁</span> Freunde werben
                        </h3>
                        <span class="text-[9px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-300 bg-amber-500/15 border border-amber-500/30 px-2 py-0.5 rounded-full">
                            5 € Guthaben
                        </span>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 text-xs leading-relaxed mb-4">
                        Schalte <strong>5 € Einkaufsguthaben</strong> frei! Nach deiner ersten Bestellung kannst du deinen persönlichen 10 % Rabattcode mit deinen Liebsten teilen.
                    </p>
                </div>
                <a href="{{ route('shop') }}" class="w-full py-2.5 px-4 bg-primary hover:bg-primary/90 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                    <span>Jetzt im Shop stöbern</span>
                    <x-heroicon-m-arrow-right class="w-4 h-4" />
                </a>
            </div>
        @endif

        {{-- Kachel 2: Seelenfunken & Rabatt-Gutscheine (Funki Level 1-10) --}}
        <div class="bg-white dark:bg-gray-900/40 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden group hover:border-[#C5A059]/60 transition-colors flex flex-col justify-between h-full">
            <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl pointer-events-none group-hover:bg-amber-500/15 transition-colors"></div>
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="text-lg">✨</span> Seelenfunken & Prämien
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest {{ $isGameModeActive ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/20' : 'text-amber-700 dark:text-amber-300 bg-amber-500/15 border-amber-500/30' }} border px-2 py-0.5 rounded-full">
                        {{ $isGameModeActive ? 'Aktiv • Lvl. ' . $funkiLevel : 'Treueprogramm' }}
                    </span>
                </div>
                <p class="text-gray-600 dark:text-gray-400 text-xs leading-relaxed mb-3">
                    Sammle bei jedem gekauften Artikel sowie auf unseren Shop-Seiten Seelenfunken, steige mit <strong>Funki von Level 1 bis 10</strong> auf und sichere dir exklusive Rabatt-Gutscheine:
                </p>
                <div class="space-y-1.5 text-[11px] text-gray-600 dark:text-gray-300 mb-4">
                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-950/50 px-2.5 py-1.5 rounded-lg border border-gray-200/70 dark:border-gray-800">
                        <span class="font-bold">60 ✨ / 150 ✨ / 350 ✨</span>
                        <span class="text-amber-700 dark:text-[#f5dca0] font-black">5 % / 10 % / 15 % Gutschein</span>
                    </div>
                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-950/50 px-2.5 py-1.5 rounded-lg border border-gray-200/70 dark:border-gray-800">
                        <span class="font-bold">Dein Guthaben:</span>
                        <span class="text-primary font-mono font-black">{{ number_format($funkenBalance, 0, ',', '.') }} ✨ Funken</span>
                    </div>
                </div>
            </div>

            @if(!$isGameModeActive)
                <button type="button"
                        @click="openActivateMagicModal()"
                        class="w-full py-2.5 bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 hover:from-amber-300 hover:to-amber-400 text-gray-950 font-black uppercase tracking-widest text-xs rounded-xl hover:scale-[1.02] transition-all shadow-[0_0_15px_rgba(245,158,11,0.25)] cursor-pointer">
                    ✨ Magie aktivieren
                </button>
            @else
                <button type="button"
                        @click="openFunkiModal('vouchers')"
                        class="w-full py-2.5 bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 hover:from-amber-300 hover:to-amber-400 text-gray-950 font-black uppercase tracking-widest text-xs rounded-xl hover:scale-[1.02] transition-all shadow-[0_0_15px_rgba(245,158,11,0.25)] cursor-pointer">
                    Funki & Gutscheine öffnen
                </button>
            @endif
        </div>

        {{-- Kachel 3: Hilfe & Support Box --}}
        <div class="bg-white dark:bg-gray-900/40 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden group hover:border-amber-500/40 transition-colors flex flex-col justify-between h-full">
            <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl pointer-events-none group-hover:bg-amber-500/10 transition-colors"></div>
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-s-lifebuoy class="w-5 h-5 text-amber-500" /> Hilfe & Support
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-300 bg-amber-500/15 border border-amber-500/30 px-2 py-0.5 rounded-full">
                        &lt; 24 Std.
                    </span>
                </div>
                <p class="text-gray-600 dark:text-gray-400 text-xs leading-relaxed mb-3">
                    Hast du Fragen zu deiner Bestellung, möchtest eine Änderung vornehmen oder benötigst Hilfe?
                </p>
                <div class="space-y-1.5 text-[11px] text-gray-500 dark:text-gray-400 mb-4">
                    <div class="flex items-center gap-2">
                        <x-heroicon-s-clock class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                        <span>Antwortzeit: Meist unter 24 Stunden</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon-s-envelope class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                        <span>Direkter Support durch unser Manufaktur-Team</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('customer.support') }}" wire:navigate class="block text-center py-2.5 bg-amber-500 hover:bg-amber-400 text-gray-950 font-black uppercase tracking-widest text-xs rounded-xl hover:scale-105 transition-all shadow-[0_0_15px_rgba(245,158,11,0.2)]">
                Ticket erstellen
            </a>
        </div>

    </div>

    {{-- =========================================================================
         MODAL 1: "MAGIE AKTIVIEREN" OPT-IN SCREEN MIT VOLLBILD-SUPERNOVA-ANIMATION
         ========================================================================= --}}
    <template x-teleport="body">
        <div x-show="showActivateMagicModal"
             style="display: none;"
             class="fixed inset-0 z-[9400] flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
            <div class="fixed inset-0 bg-black/70 dark:bg-black/85 backdrop-blur-md"
                 @click="showActivateMagicModal = false"
                 x-transition.opacity></div>

            <div x-data="optInScreen()"
                 x-init="initAmbientSparks()"
                 :class="isWarping ? 'opacity-0 scale-[0.98] pointer-events-none' : 'opacity-100 scale-100'"
                 class="relative z-10 w-full max-w-4xl p-6 sm:p-10 lg:p-14 flex flex-col lg:flex-row items-center gap-8 lg:gap-12 transition-[opacity,transform] duration-500 ease-out rounded-[2.5rem] bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-amber-500/30 shadow-2xl overflow-hidden">

                <button type="button"
                        @click="showActivateMagicModal = false; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                        class="absolute top-5 right-5 z-30 p-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-white hover:bg-red-500 transition-colors cursor-pointer">
                    <x-heroicon-m-x-mark class="w-5 h-5" />
                </button>

                <canvas x-ref="ambientSparksCanvas" class="absolute inset-0 w-full h-full pointer-events-none z-0 opacity-85"></canvas>

                {{-- Vollbild "Magie aktivieren" Animation --}}
                <template x-teleport="body">
                    <div x-show="isActivating"
                         style="display: none;"
                         class="fixed inset-0 z-[9999] pointer-events-none flex items-center justify-center overflow-hidden">
                        <div class="fixed rounded-full pointer-events-none will-change-transform transition-transform duration-[750ms] ease-[cubic-bezier(0.22,1,0.36,1)] bg-gradient-to-br from-[#fffdf7] via-[#fdf6e4] to-[#f7e7c4] dark:from-[#06080f] dark:via-[#0b0f1c] dark:to-[#17120a]"
                             :style="`left: ${originX}px; top: ${originY}px; width: 120px; height: 120px; transform: translate3d(-50%, -50%, 0) scale(${eclipseRadius > 0 ? 38 : 0});`">
                        </div>
                        <div class="absolute inset-0 transition-opacity duration-300 bg-[#fdf8ed] dark:bg-[#070a14]"
                             :class="phase >= 1 ? 'opacity-100' : 'opacity-0'">
                        </div>
                        <div class="absolute inset-0 transition-opacity duration-500"
                             :class="phase >= 1 ? 'opacity-100' : 'opacity-0'"
                             style="background: radial-gradient(circle at 50% 50%, rgba(245, 158, 11, 0.22) 0%, rgba(197, 160, 89, 0.08) 38%, transparent 70%);">
                        </div>
                        <canvas x-ref="magicSparksCanvas"
                                class="absolute inset-0 w-full h-full z-10 transition-opacity duration-400"
                                :class="phase === 3 ? 'opacity-0' : 'opacity-100'"></canvas>

                        <div class="relative z-20 flex flex-col items-center justify-center text-center px-6 transition-[opacity,transform] duration-500 transform-gpu"
                             :class="phase === 0 ? 'scale-75 opacity-0' : (phase === 1 ? 'scale-100 opacity-100' : (phase === 2 ? 'scale-105 opacity-100' : 'scale-110 opacity-0'))">
                            <div class="relative w-36 h-36 sm:w-44 sm:h-44 flex items-center justify-center mb-6">
                                <div class="absolute inset-0 rounded-full border-2 border-amber-400/80 transition-all duration-1000 ease-out"
                                     :class="phase >= 2 ? 'scale-[2.6] opacity-0' : 'scale-75 opacity-0'"></div>
                                <svg class="absolute inset-0 w-full h-full text-amber-600/40 dark:text-amber-400/40 animate-[spin_12s_linear_infinite]" viewBox="0 0 200 200">
                                    <circle cx="100" cy="100" r="92" fill="none" stroke="currentColor" stroke-width="1" stroke-dasharray="4 8" />
                                    <circle cx="100" cy="100" r="82" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="24 12 4 12" />
                                </svg>
                                <div class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full flex items-center justify-center transition-all duration-500 shadow-[0_0_50px_rgba(245,158,11,0.55)] bg-gradient-to-br from-amber-300 via-amber-500 to-yellow-600 border-2 border-white/80"
                                     :class="phase >= 2 ? 'scale-110 shadow-[0_0_90px_rgba(251,191,36,0.95)]' : 'scale-95'">
                                    <svg class="w-10 h-10 sm:w-12 sm:h-12 text-gray-950 transition-transform duration-700"
                                         :class="phase >= 2 ? 'rotate-180 scale-110' : 'rotate-0 scale-100'"
                                         viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2L14.6 9.4L22 12L14.6 14.6L12 22L9.4 14.6L2 12L9.4 9.4L12 2Z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="transition-all duration-500"
                                 :class="phase >= 1 ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                                <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-amber-500/15 border border-amber-500/40 text-amber-800 dark:text-amber-300 text-[10px] sm:text-xs font-black uppercase tracking-[0.3em] mb-3">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                    <span x-text="phase >= 2 ? 'Seelenfunken entfesselt' : 'Seelenfunken sammeln sich...'"></span>
                                </span>
                                <h3 class="text-3xl sm:text-5xl font-serif font-black tracking-tight text-gray-900 dark:text-white">
                                    Die Magie <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-700 dark:from-amber-200 dark:via-amber-400 dark:to-yellow-200">erwacht</span>
                                </h3>
                                <p class="mt-2 text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-300">
                                    Dein Begleiter Funki & das Seelenfunken-Sammelspiel werden aktiviert…
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="relative z-10 flex-1 text-center lg:text-left">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-serif font-bold text-gray-900 dark:text-white mb-4 leading-tight">
                        Sammle Funken & sichere dir <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-600 dark:from-primary dark:via-amber-200 dark:to-amber-400">bis zu 15 % Rabatt.</span>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 text-xs sm:text-sm mb-6 leading-relaxed">
                        Begleite deinen persönlichen Gefährten <strong>Funki von Level 1 bis Level 10</strong>! Mit jedem gekauften Artikel und durch seltene, versteckte Seelenfunken auf unseren Seiten sammelst du Punkte für einmalige Rabatt-Gutscheine (<strong>60 ✨ = 5 %</strong>, <strong>150 ✨ = 10 %</strong>, <strong>350 ✨ = 15 %</strong>) und schaltest mit jeder Stufe kostenlos noch epischere Profilbild-Hintergründe frei.
                    </p>

                    <div class="mb-6 flex flex-col gap-2 text-left">
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <div class="relative flex items-center justify-center shrink-0 mt-0.5">
                                <input type="checkbox" x-model="agreedToTerms" class="peer appearance-none w-5 h-5 border-2 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-900 checked:bg-primary checked:border-primary transition-all cursor-pointer">
                                <svg class="absolute w-3 h-3 text-gray-900 opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span class="text-xs text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-300 transition-colors leading-relaxed">
                                Ich habe die <a href="{{ route('agb') }}#agb-gamification" target="_blank" @click.stop class="text-amber-700 dark:text-primary hover:underline font-semibold">Teilnahmebedingungen zum Seelenfunken-Programm</a> gelesen und stimme diesen zu. Zudem nehme ich die <a href="{{ route('datenschutz') }}#datenschutz-gamification" target="_blank" @click.stop class="text-amber-700 dark:text-primary hover:underline font-semibold">Datenschutzhinweise</a> bezüglich der Speicherung meines Funken-Guthabens und Funki-Levels zur Kenntnis.
                            </span>
                        </label>
                    </div>

                    <button :disabled="!agreedToTerms || isActivating"
                            @click="if(agreedToTerms && !isActivating) triggerEpicStart($event)"
                            class="w-full sm:w-auto group relative px-8 sm:px-10 py-4 bg-gradient-to-r from-amber-400 via-primary to-amber-500 hover:from-amber-300 hover:to-amber-400 text-gray-950 border border-amber-300/80 disabled:from-gray-200 disabled:to-gray-200 dark:disabled:from-gray-800 dark:disabled:to-gray-800 disabled:border-transparent disabled:text-gray-400 rounded-2xl font-black uppercase tracking-widest text-xs sm:text-sm shadow-[0_10px_30px_rgba(245,158,11,0.35)] disabled:shadow-none transition-all duration-300 flex items-center justify-center gap-3 overflow-hidden mx-auto lg:mx-0 disabled:cursor-not-allowed hover:scale-[1.03] cursor-pointer">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-950 disabled:text-gray-400 transition-transform duration-500 group-hover:rotate-90" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2L14.4 9.6L22 12L14.4 14.4L12 22L9.6 14.4L2 12L9.6 9.6L12 2Z" />
                        </svg>
                        <span class="relative z-10">Magie aktivieren</span>
                    </button>
                </div>

                <div class="relative z-10 w-44 h-44 sm:w-60 sm:h-60 flex justify-center shrink-0">
                    <div class="absolute inset-0 bg-gradient-to-tr from-amber-500/25 via-primary/20 to-yellow-300/20 rounded-full blur-[35px] animate-pulse"></div>
                    <img src="{{ asset('shop/customer/gamification/models/images/original/funki_lvl_10_god_of_soul.png') }}" draggable="false" class="relative w-full h-full object-contain drop-shadow-[0_15px_30px_rgba(197,160,89,0.4)] z-10">
                </div>
            </div>
        </div>
    </template>

    {{-- =========================================================================
         MODAL 2: FUNKI (LEVEL 1–10) & RABATT-GUTSCHEINE (KLICK AUF PROFILBILD)
         ========================================================================= --}}
    <template x-teleport="body">
        <div x-show="showFunkiModal"
             style="display: none;"
             @keydown.escape.window="closeFunkiModal()"
             class="fixed inset-0 z-[9400] flex items-center justify-center p-2.5 sm:p-6 overflow-y-auto">
            <div class="fixed inset-0 bg-black/70 dark:bg-black/85 backdrop-blur-md"
                 @click="closeFunkiModal()"
                 x-transition.opacity></div>

            <div class="relative z-10 w-full max-w-5xl max-h-[94vh] sm:max-h-[92vh] flex flex-col bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-[#C5A059]/30 rounded-3xl sm:rounded-[2rem] shadow-2xl overflow-hidden"
                 x-transition:enter="transition ease-out duration-250"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                {{-- Modal Header mit Soundregler + Mute-Taste + "Musik laufen lassen" + Schließen in sauberer eigener Kopfzeile (überlappt niemals den Text) --}}
                <div class="relative px-4 sm:px-8 pt-3.5 sm:pt-5 pb-4 sm:pb-5 border-b border-gray-200 dark:border-gray-800 bg-gradient-to-b from-amber-50/70 via-white to-white dark:from-[#151926] dark:via-[#0d1017] dark:to-[#0d1017] shrink-0">

                    {{-- Eigene Top-Leiste für Audio-Steuerung (Standard: nur Lautsprecher-Icon, beim Drücken größer) & Schließen-Button --}}
                    <div class="w-full flex items-center justify-end gap-2 mb-3 sm:mb-2">
                        <div @click.outside="audioExpanded = false" class="relative flex items-center">
                            {{-- Standard: Nur Lautsprecher-Icon --}}
                            <button x-show="!audioExpanded"
                                    type="button"
                                    @click="audioExpanded = true"
                                    title="Funki Musik öffnen"
                                    aria-label="Funki Musik öffnen"
                                    class="p-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-amber-600 dark:text-[#C5A059] hover:border-[#C5A059] transition-all cursor-pointer shrink-0 shadow-2xs">
                                <svg x-show="!audioMuted && Number(audioVolume) > 0" class="w-5 h-5 text-amber-600 dark:text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.54 8.46a5 5 0 010 7.07M19.07 4.93a10 10 0 010 14.14" />
                                </svg>
                                <svg x-show="audioMuted || Number(audioVolume) === 0" style="display: none;" class="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M23 9l-6 6m0-6l6 6" />
                                </svg>
                            </button>

                            {{-- Geöffnet (etwas größer): Komplette Lautstärke- & "Musik laufen lassen"-Steuerung --}}
                            <div x-show="audioExpanded"
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="flex flex-wrap items-center gap-1.5 sm:gap-2.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-white/95 dark:bg-gray-900/95 border border-[#C5A059]/40 dark:border-gray-800 shadow-sm min-w-0">
                                <button type="button"
                                        @click="toggleMute()"
                                        :title="audioMuted ? 'Ton einschalten' : 'Stummschalten'"
                                        class="p-1 rounded-lg text-gray-600 dark:text-gray-300 hover:text-amber-600 dark:hover:text-[#f5dca0] transition cursor-pointer shrink-0">
                                    {{-- Speaker On --}}
                                    <svg x-show="!audioMuted && Number(audioVolume) > 0" class="w-4 h-4 text-amber-600 dark:text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.54 8.46a5 5 0 010 7.07M19.07 4.93a10 10 0 010 14.14" />
                                    </svg>
                                    {{-- Speaker Muted --}}
                                    <svg x-show="audioMuted || Number(audioVolume) === 0" style="display: none;" class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M23 9l-6 6m0-6l6 6" />
                                    </svg>
                                </button>

                                <input type="range"
                                       min="0"
                                       max="100"
                                       step="1"
                                       x-model.number="audioVolume"
                                       @input="onVolumeChange(false)"
                                       @change="onVolumeChange(true)"
                                       aria-label="Musik- und Sound-Lautstärke"
                                       class="w-16 sm:w-24 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-[#C5A059]">

                                <span class="text-[10px] font-mono font-bold text-gray-600 dark:text-gray-300 w-7 text-right select-none shrink-0"
                                      x-text="audioMuted ? 'Aus' : (audioVolume + '%')"></span>

                                <div class="h-3.5 w-px bg-gray-200 dark:bg-gray-700 mx-0.5"></div>

                                <label class="inline-flex items-center gap-1.5 cursor-pointer select-none group"
                                       title="Wenn aktiviert, läuft die Funki-Musik beim Schließen des Modals und beim Seitenwechsel konstant weiter">
                                    <input type="checkbox"
                                           x-model="audioKeepPlaying"
                                           @change="toggleKeepPlaying()"
                                           class="w-3.5 h-3.5 rounded border-gray-300 dark:border-gray-600 text-[#C5A059] focus:ring-[#C5A059]/40 cursor-pointer accent-[#C5A059]">
                                    <span class="text-[10px] font-bold text-gray-700 dark:text-gray-300 group-hover:text-amber-600 dark:group-hover:text-[#f5dca0] transition-colors whitespace-nowrap">
                                        Musik laufen lassen
                                    </span>
                                </label>
                            </div>
                        </div>

                        <button type="button"
                                @click="closeFunkiModal()"
                                title="Schließen"
                                class="p-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-white hover:bg-red-500 transition-colors cursor-pointer shrink-0">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="flex flex-col md:flex-row items-center gap-4 sm:gap-6">
                        {{-- Großer goldener Funki-Kreis (Klickbar für Großansicht von Bild & animiertem Hintergrund) --}}
                        @php
                            $currentZoomPayload = [
                                'level'               => $displayFunkiLevel,
                                'name'                => $lvlCfg['name'] ?? ('Funki Level ' . $displayFunkiLevel),
                                'subtitle'            => $lvlCfg['subtitle'] ?? '',
                                'bg_name'             => $lvlCfg['bg_name'] ?? '',
                                'bg_subtitle'         => $lvlCfg['bg_subtitle'] ?? '',
                                'image_url'           => $lvlImage,
                                'video_url'           => !empty($lvlVideoUrl) ? asset(ltrim($lvlVideoUrl, '/')) : null,
                                'video_filter_css'    => $lvlVideoFilter,
                                'video_transform_css' => $lvlVideoTransform,
                                'circle_css'          => $lvlCircleCss,
                                'accent_hex'          => $lvlAccentHex,
                            ];
                            $headerTitleLabel = $displayRankLabel ?: ($defaultLevelRank ?? 'Kleiner Seelenfunke');
                        @endphp
                        <button type="button"
                                @click="openFunkiZoom(@js($currentZoomPayload))"
                                title="Klicken, um Funki & Hintergrund in großer Ansicht zu betrachten"
                                class="relative w-32 h-32 sm:w-40 sm:h-40 md:w-44 md:h-44 flex items-center justify-center shrink-0 select-none group cursor-pointer focus:outline-none">
                            <div class="absolute inset-[-6px] rounded-full border border-dashed border-[#C5A059]/40 pointer-events-none"></div>
                            <div class="absolute inset-[-12px] rounded-full border border-dotted border-[#C5A059]/25 animate-[spin_28s_linear_infinite] pointer-events-none"></div>

                            <svg class="absolute inset-0 w-full h-full -rotate-90 pointer-events-none z-20" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="47.5" fill="none" stroke="#e2e8f0" class="dark:stroke-gray-800" stroke-width="2.5"></circle>
                                <circle cx="50" cy="50" r="47.5" fill="none" stroke="#c5a059" stroke-width="4" stroke-linecap="round"
                                        stroke-dasharray="298.45"
                                        stroke-dashoffset="{{ 298.45 - ($progressPercentage / 100) * 298.45 }}"
                                        class="transition-all duration-1000 ease-out"></circle>
                            </svg>

                            <svg class="absolute inset-0 w-full h-full pointer-events-none z-30 overflow-visible" viewBox="0 0 200 200">
                                <defs>
                                    <path id="sf-modal-avatar-arc" d="M 22,128 A 83,83 0 0,0 178,128" fill="none" />
                                </defs>
                                <path d="M 32,142 A 83,83 0 0,0 168,142" fill="none" stroke="rgba(11,15,25,0.82)" stroke-width="15" stroke-linecap="round" />
                                <text class="fill-[#f5dca0] font-black uppercase" style="font-size: 8.2px; letter-spacing: 0.14em;">
                                    <textPath href="#sf-modal-avatar-arc" startOffset="50%" text-anchor="middle">
                                        {{ $arcText }}
                                    </textPath>
                                </text>
                            </svg>

                            <div style="{{ $lvlCircleCss }}"
                                 class="absolute inset-2.5 rounded-full bg-white dark:bg-[#13151e] border-2 border-[#C5A059] shadow-[0_0_35px_rgba(197,160,89,0.4)] flex items-center justify-center overflow-hidden z-10 transition-transform duration-300 group-hover:scale-[1.03]">
                                @if(!empty($lvlVideoUrl))
                                    <video src="{{ asset(ltrim($lvlVideoUrl, '/')) }}"
                                           style="{{ $lvlVideoFilter ? 'filter: ' . $lvlVideoFilter . ';' : '' }} {{ $lvlVideoTransform ? 'transform: ' . $lvlVideoTransform . ';' : '' }}"
                                           autoplay loop muted playsinline
                                           class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                                @endif
                                <div class="absolute inset-[-18%] rounded-full opacity-55 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                     style="background: conic-gradient(from 0deg, transparent 0%, {{ $lvlAccentHex }}55 25%, transparent 50%, {{ $lvlAccentHex }}44 75%, transparent 100%);"></div>
                                <img src="{{ $lvlImage }}" alt="Funki" class="relative z-10 w-full h-full object-contain p-3 sm:p-3.5 drop-shadow-[0_10px_20px_rgba(0,0,0,0.6)]">
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center z-20">
                                    <span class="px-2.5 py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[9px] uppercase tracking-widest shadow-lg">
                                        🔍 Großansicht
                                    </span>
                                </div>
                            </div>
                        </button>

                        {{-- Kundenname / Spielername • Ausgewählter Titel + Zähler --}}
                        <div class="flex-1 text-center md:text-left min-w-0 w-full">
                            <h2 class="text-xl sm:text-2xl lg:text-3xl font-serif font-bold text-gray-900 dark:text-white leading-tight break-words">
                                {{ $heroDisplayName }} <span class="text-[#C5A059]">•</span> <span class="text-amber-700 dark:text-[#f5dca0]">{{ $headerTitleLabel }}</span>
                            </h2>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 max-w-xl mx-auto md:mx-0">
                                {{ $lvlCfg['subtitle'] ?? '' }} • Dein Hintergrund verwandelt sich mit jedem Funki-Aufstieg automatisch und kostenlos in eine noch epischere Darstellung!
                            </p>

                            <div class="mt-3.5 sm:mt-4 grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-2.5">
                                <div class="p-2.5 sm:p-3 rounded-2xl bg-amber-500/10 border border-[#C5A059]/40">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-300 block">Einlösbares Guthaben</span>
                                    <span class="text-sm sm:text-lg font-mono font-black text-gray-900 dark:text-[#f5dca0]">{{ number_format($funkenBalance, 0, ',', '.') }} ✨</span>
                                </div>
                                <div class="p-2.5 sm:p-3 rounded-2xl bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 block">Lebenszeit-Funken (Level)</span>
                                    <span class="text-sm sm:text-lg font-mono font-black text-gray-900 dark:text-white">{{ number_format($funkenTotalEarned, 0, ',', '.') }} ✨</span>
                                </div>
                                <div class="col-span-2 sm:col-span-1 p-2.5 sm:p-3 rounded-2xl bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 block">Treue-Bonus (100 %)</span>
                                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 block mt-0.5">1,00 € Warenwert = +1 ✨ Funke</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Navigation Tabs im Modal (Mobil-optimiert 2x2 Grid auf Smartphones, Flex-Reihe ab sm) --}}
                    <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2 mt-4 sm:mt-5">
                        <button type="button"
                                @click="activeFunkiTab = 'vouchers'; if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                :class="activeFunkiTab === 'vouchers' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black shadow-md' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                                class="px-3 sm:px-5 py-2.5 rounded-xl text-[11px] sm:text-xs uppercase tracking-wider sm:tracking-widest transition-all cursor-pointer text-center">
                            🎁 Rabatte
                        </button>
                        <button type="button"
                                @click="activeFunkiTab = 'levels'; if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                :class="activeFunkiTab === 'levels' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black shadow-md' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                                class="px-3 sm:px-5 py-2.5 rounded-xl text-[11px] sm:text-xs uppercase tracking-wider sm:tracking-widest transition-all cursor-pointer text-center">
                            ✨ Level
                        </button>
                        <button type="button"
                                @click="activeFunkiTab = 'titles'; if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                :class="activeFunkiTab === 'titles' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black shadow-md' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                                class="px-3 sm:px-5 py-2.5 rounded-xl text-[11px] sm:text-xs uppercase tracking-wider sm:tracking-widest transition-all cursor-pointer text-center">
                            🏆 Titel & Ränge ({{ $titlesData['diamonds_count'] ?? 0 }}/{{ $titlesData['total_titles'] ?? 5 }} Max)
                        </button>
                        <button type="button"
                                @click="activeFunkiTab = 'hall_of_fame'; if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                :class="activeFunkiTab === 'hall_of_fame' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black shadow-md' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                                class="px-3 sm:px-5 py-2.5 rounded-xl text-[11px] sm:text-xs uppercase tracking-wider sm:tracking-widest transition-all cursor-pointer text-center">
                            🏛️ Halle der Helden
                        </button>
                    </div>
                </div>

                {{-- Modal Body --}}
                <div class="flex-1 overflow-y-auto p-4 sm:p-8 space-y-6">

                    {{-- TAB 1: RABATT-GUTSCHEINE (60 = 5%, 150 = 10%, 350 = 15%) --}}
                    <div x-show="activeFunkiTab === 'vouchers'" class="space-y-6">
                        <div>
                            <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white">
                                Gesammelte Seelenfunken gegen Gutscheine eintauschen
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Jeder eingelöste Gutschein ist <strong>einmalig für deine nächste Bestellung</strong> gültig. Dein Funki-Level (Lebenszeit-Funken) sinkt beim Einlösen niemals!
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($redeemableVouchers as $vKey => $vCfg)
                                @php
                                    $cost = (int) $vCfg['cost_sparks'];
                                    $canAfford = $isGameModeActive && $funkenBalance >= $cost;
                                    $pctProgress = min(100, (int) round(($funkenBalance / max(1, $cost)) * 100));
                                @endphp
                                <div class="rounded-2xl p-5 border {{ $canAfford ? 'bg-gradient-to-b from-amber-500/10 to-transparent border-[#C5A059]' : 'bg-gray-50 dark:bg-gray-900/60 border-gray-200 dark:border-gray-800' }} flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-3">
                                            <span class="px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest">
                                                {{ $vCfg['badge'] }}
                                            </span>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">
                                                {{ number_format($cost, 0, ',', '.') }} ✨
                                            </span>
                                        </div>
                                        <h4 class="text-xl font-serif font-bold text-gray-900 dark:text-white mb-1">
                                            {{ $vCfg['name'] }}
                                        </h4>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                                            {{ $vCfg['description'] }}
                                        </p>

                                        {{-- Fortschrittsbalken --}}
                                        <div class="mb-4">
                                            <div class="flex justify-between text-[10px] font-bold text-gray-500 mb-1">
                                                <span>Fortschritt</span>
                                                <span>{{ min($funkenBalance, $cost) }} / {{ $cost }} ✨</span>
                                            </div>
                                            <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-amber-400 to-[#C5A059] transition-all duration-500"
                                                     style="width: {{ $pctProgress }}%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    @if(!$isGameModeActive)
                                        <button type="button"
                                                @click="openActivateMagicModal()"
                                                class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-950 font-black text-xs uppercase tracking-widest transition cursor-pointer">
                                            Zuerst Magie aktivieren
                                        </button>
                                    @else
                                        <button type="button"
                                                @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                                wire:click="redeemVoucher('{{ $vKey }}')"
                                                wire:loading.attr="disabled"
                                                @if(!$canAfford) disabled @endif
                                                class="w-full py-2.5 rounded-xl font-black text-xs uppercase tracking-widest transition {{ $canAfford ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 hover:brightness-105 text-gray-950 shadow-md cursor-pointer' : 'bg-gray-200 dark:bg-gray-800 text-gray-400 dark:text-gray-600 cursor-not-allowed' }}">
                                            <span wire:loading.remove wire:target="redeemVoucher('{{ $vKey }}')">
                                                {{ $canAfford ? 'Jetzt für ' . $cost . ' ✨ einlösen' : 'Noch ' . ($cost - $funkenBalance) . ' ✨ benötigt' }}
                                            </span>
                                            <span wire:loading wire:target="redeemVoucher('{{ $vKey }}')">Wird erstellt…</span>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Liste bereits freigeschalteter persönlicher Gutscheine --}}
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                            <h4 class="text-sm font-serif font-bold text-gray-900 dark:text-white mb-3">
                                Deine freigeschalteten Rabatt-Gutscheine ({{ count($customerVouchers) }})
                            </h4>
                            @if(count($customerVouchers) > 0)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($customerVouchers as $cv)
                                        <div x-data="{ copied: false }"
                                             class="p-3.5 rounded-2xl border {{ $cv['is_used'] ? 'bg-gray-100/60 dark:bg-gray-900/30 border-gray-200 dark:border-gray-800 opacity-60' : 'bg-white dark:bg-gray-900 border-[#C5A059]/50 shadow-xs' }} flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase {{ $cv['is_used'] ? 'bg-gray-300 dark:bg-gray-800 text-gray-600 dark:text-gray-400' : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' }}">
                                                        {{ $cv['is_used'] ? 'Eingelöst' : ($cv['value'] . '% Rabatt • Bereit') }}
                                                    </span>
                                                    <span class="text-[10px] text-gray-400">{{ $cv['created_at'] }}</span>
                                                </div>
                                                <span class="font-mono font-black text-sm text-gray-900 dark:text-white block mt-1 truncate">
                                                    {{ $cv['code'] }}
                                                </span>
                                            </div>
                                            @if(!$cv['is_used'])
                                                <button type="button"
                                                        @click="if(window.sfCockpitSound) window.sfCockpitSound('click'); window.sfCopyText('{{ $cv['code'] }}'); copied = true; setTimeout(() => copied = false, 2200); $dispatch('notify', { message: 'Gutscheincode {{ $cv['code'] }} in die Zwischenablage kopiert!', type: 'success' })"
                                                        :class="copied ? 'bg-emerald-500 text-white shadow-sm' : 'bg-amber-500/15 hover:bg-[#C5A059] text-amber-900 dark:text-[#f5dca0] hover:text-gray-950'"
                                                        class="px-3 py-1.5 rounded-xl font-bold text-xs transition shrink-0 cursor-pointer">
                                                    <span x-show="!copied">Kopieren</span>
                                                    <span x-show="copied" style="display: none;">✓ Kopiert!</span>
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 p-4 rounded-2xl border border-gray-200 dark:border-gray-800">
                                    Du hast noch keinen Rabatt-Gutschein eingelöst. Sammle Seelenfunken durch deine Einkäufe oder auf den Shop-Seiten und tausche sie oben ab 60 ✨ direkt ein!
                                </p>
                            @endif
                        </div>

                        {{-- JÄHRLICHE TREUE-FUNKEN (GEBURTSTAGS-FUNKE +50 ✨ & MANUFAKTUR-JAHRESTAG +50 ✨) --}}
                        @php
                            $bdayFormatted = $loyaltyMilestones['birthday_formatted'] ?? null;
                            $bdayDayMonth = $loyaltyMilestones['birthday_day_month'] ?? null;
                            $bdaySparks = (int) ($loyaltyMilestones['birthday_sparks'] ?? 50);
                            $bdayCanChange = (bool) ($loyaltyMilestones['can_change_birthday'] ?? true);
                            $bdayCorrections = (int) ($loyaltyMilestones['birthday_corrections'] ?? 0);
                            $bdayAwardedThisYear = !empty($loyaltyMilestones['birthday_awarded_this_year']);
                            $annivDayMonth = $loyaltyMilestones['anniversary_day_month'] ?? null;
                            $annivMemberSince = $loyaltyMilestones['member_since_formatted'] ?? null;
                            $annivSparks = (int) ($loyaltyMilestones['anniversary_sparks'] ?? 50);
                            $annivAwardedThisYear = !empty($loyaltyMilestones['anniversary_awarded_this_year']);
                        @endphp
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-800 space-y-3">
                            <div>
                                <h4 class="text-sm font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <span>✨</span>
                                    <span>Jährliche Treue-Funken für Stammkunden</span>
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Als aktives Mitglied im Seelenfunken-Programm beschenkt dich Funki automatisch zu deinen persönlichen Ehrentagen per E-Mail &amp; Direktgutschrift:
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Karte 1: Geburtstags-Funke (+50 ✨) --}}
                                <div class="rounded-2xl p-4 sm:p-5 border border-[#C5A059]/40 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent flex flex-col justify-between gap-3">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest">
                                                🎂 Geburtstags-Funke
                                            </span>
                                            <span class="font-mono text-xs font-black text-amber-700 dark:text-[#f5dca0]">
                                                +{{ $bdaySparks }} ✨ / Jahr
                                            </span>
                                        </div>

                                        <h5 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                                            Dein jährliches Geburtstagsgeschenk
                                        </h5>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                                            Hinterlege dein Geburtsdatum und erhalte jedes Jahr an deinem Ehrentag automatisch <strong>+{{ $bdaySparks }} ✨ Seelenfunken</strong> von Funki geschenkt.
                                        </p>
                                    </div>

                                    <div class="space-y-2 pt-2 border-t border-amber-500/20">
                                        @if($bdayFormatted)
                                            <div class="flex items-center justify-between gap-2 bg-white/80 dark:bg-gray-900/80 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-800">
                                                <div>
                                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Hinterlegter Geburtstag</span>
                                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $bdayFormatted }} (jedes Jahr am {{ $bdayDayMonth }})</span>
                                                </div>
                                                <span class="px-2.5 py-1 rounded-lg bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase tracking-wider shrink-0">
                                                    {{ $bdayAwardedThisYear ? '✓ ' . now()->year . ' erhalten' : '✓ Aktiv' }}
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex items-center justify-between gap-2 bg-white/80 dark:bg-gray-900/80 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-800">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Geburtsdatum noch nicht hinterlegt</span>
                                                <span class="px-2.5 py-1 rounded-lg bg-gray-200/80 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[10px] font-black uppercase tracking-wider shrink-0">
                                                    Nicht aktiv
                                                </span>
                                            </div>
                                        @endif

                                        @if(!$bdayFormatted || $bdayCanChange)
                                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                                <input type="date"
                                                       wire:model.live="customerBirthdayInput"
                                                       aria-label="Geburtsdatum"
                                                       class="flex-1 py-2 px-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none">
                                                <button type="button"
                                                        @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                                        wire:click="saveCustomerBirthday"
                                                        wire:loading.attr="disabled"
                                                        class="py-2 px-4 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 hover:brightness-105 text-gray-950 font-black text-xs uppercase tracking-widest shadow-xs transition cursor-pointer shrink-0">
                                                    <span wire:loading.remove wire:target="saveCustomerBirthday">
                                                        {{ $bdayFormatted ? '1× Korrigieren' : 'Speichern' }}
                                                    </span>
                                                    <span wire:loading wire:target="saveCustomerBirthday">Speichert…</span>
                                                </button>
                                            </div>
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400">
                                                @if($bdayFormatted)
                                                    Aktiv für den <strong>{{ $bdayDayMonth }}</strong> ({{ $bdayFormatted }}). Falls du dich vertippt hast, kannst du das Datum noch <strong>1× selbst korrigieren</strong>.
                                                @else
                                                    Tippfehler-Schutz: Nach dem ersten Eintragen ist bei Bedarf noch 1 Selbst-Korrektur möglich.
                                                @endif
                                            </p>
                                        @else
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400">
                                                Dein Geburtsdatum wurde bereits einmalig korrigiert und ist nun fest hinterlegt.
                                            </p>
                                        @endif

                                        @if($birthdayFeedbackMessage)
                                            <div wire:key="bday-feedback-{{ md5($birthdayFeedbackMessage) }}"
                                                 class="p-3 rounded-xl border text-xs font-bold flex items-center gap-2 shadow-xs {{ $birthdayFeedbackSuccess ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-800 dark:text-emerald-300' : 'bg-red-500/15 border-red-500/40 text-red-700 dark:text-red-300' }}">
                                                <span class="text-sm shrink-0">{{ $birthdayFeedbackSuccess ? '✓' : '⚠️' }}</span>
                                                <span>{{ $birthdayFeedbackMessage }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Karte 2: Manufaktur-Jahrestags-Funke (+50 ✨) --}}
                                <div class="rounded-2xl p-4 sm:p-5 border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/60 flex flex-col justify-between gap-3">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <span class="px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest">
                                                🕯️ Manufaktur-Jahrestag
                                            </span>
                                            <span class="font-mono text-xs font-black text-amber-700 dark:text-[#f5dca0]">
                                                +{{ $annivSparks }} ✨ / Jahr
                                            </span>
                                        </div>

                                        <h5 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                                            Vorfreude-Funken zu deinem Kauf-Jubiläum
                                        </h5>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                                            Pünktlich 14 Tage vor dem Jahrestag deiner ersten Manufaktur-Bestellung schreibt dir Funki automatisch <strong>+{{ $annivSparks }} ✨ Vorfreude-Funken</strong> für dein nächstes Geschenk gut.
                                        </p>
                                    </div>

                                    <div class="pt-2 border-t border-gray-200 dark:border-gray-800">
                                        @if($annivDayMonth)
                                            <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-900 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-800">
                                                <div>
                                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Dein Manufaktur-Jubiläum</span>
                                                    <span class="text-xs font-bold text-gray-900 dark:text-white">Jährlich zum {{ $annivDayMonth }} (seit {{ $annivMemberSince }})</span>
                                                </div>
                                                <span class="px-2.5 py-1 rounded-lg bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase tracking-wider">
                                                    {{ $annivAwardedThisYear ? '✓ ' . now()->year . ' erhalten' : '✓ Automatisch aktiv' }}
                                                </span>
                                            </div>
                                        @else
                                            <div class="px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                                                Aktiviert sich vollautomatisch mit deiner ersten abgeschlossenen Bestellung in unserer Manufaktur.
                                            </div>
                                        @endif
                                        <div class="pt-1 flex justify-end">
                                            <a href="{{ route('newsletter.page', ['tab' => 'unsubscribe', 'type' => 'anniversary', 'email' => $user->email ?? '']) }}"
                                               class="text-[10px] text-gray-400 hover:text-[#C5A059] underline underline-offset-2 transition-colors">
                                                E-Mail-Benachrichtigungen (Seelenpost, Geburtstag &amp; Jubiläum) verwalten
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 2: FUNKI LEVEL 1–10 & AUTOMATISCHE HINTERGRÜNDE --}}
                    <div x-show="activeFunkiTab === 'levels'" style="display: none;" class="space-y-4">
                        <div>
                            <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white">
                                Funkis 10 Evolutionsstufen & kostenlose Level-Hintergründe
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Mit jeder Stufe erhält dein Funki automatisch ein neues 2D-Aussehen und einen noch epischeren, einzigartigen Hintergrund bis hin zur <strong>Gotteshalle aus purem Gold (Level 10)</strong>. Klicke auf ein Bild für die Großansicht:
                            </p>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 sm:gap-3.5">
                            @foreach($allFunkiLevels as $lvlNum => $stage)
                                @php
                                    $isUnlocked = $lvlNum <= $funkiLevel;
                                    $isEquipped = $lvlNum === $displayFunkiLevel;
                                    $reqSparks = (int) $stage['required_lifetime_sparks'];
                                    $stageVideoUrl = !empty($stage['video_url']) ? asset(ltrim($stage['video_url'], '/')) : null;
                                    $stageVideoFilter = $stage['video_filter_css'] ?? '';
                                    $stageVideoTransform = $stage['video_transform_css'] ?? '';
                                    $stageAccentHex = $stage['accent_hex'] ?? '#c5a059';
                                    $stageZoomPayload = [
                                        'level'               => $lvlNum,
                                        'name'                => $stage['name'],
                                        'subtitle'            => $stage['subtitle'] ?? '',
                                        'bg_name'             => $stage['bg_name'],
                                        'bg_subtitle'         => $stage['bg_subtitle'] ?? '',
                                        'image_url'           => asset($stage['image_path']),
                                        'video_url'           => $stageVideoUrl,
                                        'video_filter_css'    => $stageVideoFilter,
                                        'video_transform_css' => $stageVideoTransform,
                                        'circle_css'          => $stage['circle_css'],
                                        'accent_hex'          => $stageAccentHex,
                                    ];
                                @endphp
                                <div class="rounded-2xl p-3 border transition-all flex flex-col justify-between {{ $isEquipped ? 'border-2 border-[#C5A059] bg-amber-500/10 shadow-md' : ($isUnlocked ? 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/70 hover:border-[#C5A059]/60' : 'border-gray-200/60 dark:border-gray-800/50 bg-gray-100/60 dark:bg-gray-900/30 opacity-75') }}">
                                    <div class="flex flex-col items-center text-center">
                                        <span class="text-[9px] font-black uppercase tracking-widest {{ $isUnlocked ? 'text-amber-700 dark:text-[#f5dca0]' : 'text-gray-400' }} mb-2">
                                            Level {{ $lvlNum }} {{ $isUnlocked ? '✓' : '🔒' }}
                                        </span>

                                        {{-- Vorschau-Kreis mit animiertem Stufen-Hintergrund & 2D Funki (Klickbar für Großansicht) --}}
                                        <button type="button"
                                                @click="openFunkiZoom(@js($stageZoomPayload))"
                                                title="Level {{ $lvlNum }} ({{ $stage['bg_name'] }}) in Großansicht betrachten"
                                                style="{{ $stage['circle_css'] }}"
                                                class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full border-2 {{ $isUnlocked ? 'border-[#C5A059]' : 'border-gray-400 dark:border-gray-700' }} overflow-hidden flex items-center justify-center mb-2.5 shadow-inner group cursor-pointer focus:outline-none">
                                            @if($stageVideoUrl)
                                                <video src="{{ $stageVideoUrl }}"
                                                       style="{{ $stageVideoFilter ? 'filter: ' . $stageVideoFilter . ';' : '' }} {{ $stageVideoTransform ? 'transform: ' . $stageVideoTransform . ';' : '' }}"
                                                       autoplay loop muted playsinline
                                                       class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                                            @endif
                                            <div class="absolute inset-[-18%] rounded-full opacity-55 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                                 style="background: conic-gradient(from 0deg, transparent 0%, {{ $stageAccentHex }}55 25%, transparent 50%, {{ $stageAccentHex }}44 75%, transparent 100%);"></div>
                                            <img src="{{ asset($stage['image_path']) }}"
                                                 alt="Funki Level {{ $lvlNum }}"
                                                 loading="lazy"
                                                 class="relative z-10 w-full h-full object-contain p-2 transition-transform duration-300 group-hover:scale-110">
                                            <div class="absolute inset-0 bg-black/45 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center z-20">
                                                <span class="px-2 py-0.5 rounded bg-[#C5A059] text-gray-950 font-black text-[8px] uppercase tracking-wider">
                                                    🔍 Groß
                                                </span>
                                            </div>
                                        </button>

                                        <h5 class="text-xs font-bold text-gray-900 dark:text-white leading-tight">
                                            {{ str_replace('Funki • ', '', $stage['name']) }}
                                        </h5>
                                        <span class="text-[10px] text-amber-700 dark:text-amber-300/90 font-medium mt-0.5 block">
                                            {{ $stage['bg_name'] }}
                                        </span>
                                    </div>

                                    <div class="mt-3 pt-2 border-t border-gray-200/60 dark:border-gray-800 text-center">
                                        @if($isEquipped)
                                            <span class="inline-block w-full py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[9px] uppercase tracking-widest">
                                                Aktiv im Profil
                                            </span>
                                        @elseif($isUnlocked)
                                            <button type="button"
                                                    @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                                    wire:click="selectFunkiStage({{ $lvlNum }})"
                                                    class="w-full py-1 rounded-lg bg-amber-500/15 hover:bg-[#C5A059] text-amber-900 dark:text-[#f5dca0] hover:text-gray-950 font-black text-[9px] uppercase tracking-widest transition cursor-pointer">
                                                Auswählen
                                            </button>
                                        @else
                                            <span class="text-[9px] font-mono font-bold text-gray-500 block">
                                                ab {{ number_format($reqSparks, 0, ',', '.') }} ✨
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- TAB 3: TITEL & RÄNGE (5 KERN-TITEL + LEVEL 1–10 RÄNGE BIS ZUM "SEELENGOTT") --}}
                    <div x-show="activeFunkiTab === 'titles'" style="display: none;" class="space-y-6">
                        @php
                            $isSeelengott = !empty($titlesData['is_seelengott']);
                            $diamondsCount = (int) ($titlesData['diamonds_count'] ?? 0);
                            $totalTitles = (int) ($titlesData['total_titles'] ?? 5);
                            $coreTitles = $titlesData['titles'] ?? [];
                            $levelTitles = $titlesData['level_titles'] ?? \App\Services\Gamification\GameConfig::getLevelTitles();
                        @endphp

                        {{-- Ultimativer Rang: SEELENGOTT (Alle 5 Titel auf Max-Stufe + Level 10) --}}
                        <div class="rounded-2xl p-5 border-2 {{ $isSeelengott ? 'border-[#C5A059] bg-gradient-to-r from-amber-500/20 via-[#C5A059]/10 to-amber-500/5 shadow-lg' : 'border-dashed border-gray-300 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/50' }} flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="flex items-center gap-4 text-center sm:text-left flex-col sm:flex-row">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shrink-0 {{ $isSeelengott ? 'bg-gradient-to-br from-amber-300 via-[#C5A059] to-amber-600 text-gray-950 shadow-md' : 'bg-gray-200 dark:bg-gray-800 text-gray-400' }}">
                                    👑
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1">
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest {{ $isSeelengott ? 'bg-[#C5A059] text-gray-950' : 'bg-gray-200 dark:bg-gray-800 text-gray-500' }}">
                                            {{ $isSeelengott ? 'HÖCHSTER RANG FREIGESCHALTET' : 'ULTIMATIVES ZIEL' }}
                                        </span>
                                        <span class="text-[10px] font-mono font-bold text-amber-700 dark:text-[#f5dca0]">
                                            {{ $diamondsCount }}/{{ $totalTitles }} Titel auf Max • Level {{ $funkiLevel }}/10
                                        </span>
                                    </div>
                                    <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white">
                                        Seelengott unter Sterblichen
                                    </h3>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                        Erreiche mit allen <strong>5 Kern-Titeln die Max-Stufe (Diamant)</strong> und steige auf <strong>Funki-Level 10</strong> auf, um den Titel <strong>„Seelengott unter Sterblichen“</strong> zu tragen.
                                    </p>
                                </div>
                            </div>

                            <div class="shrink-0 w-full sm:w-auto">
                                @if($activeTitleKey === 'seelengott')
                                    <span class="inline-block w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#C5A059] text-gray-950 font-black text-xs uppercase tracking-widest text-center">
                                        👑 Aktiv getragen
                                    </span>
                                @elseif($isSeelengott)
                                    <button type="button"
                                            @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                            wire:click="selectTitle('seelengott')"
                                            class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 hover:brightness-105 text-gray-950 font-black text-xs uppercase tracking-widest shadow-md cursor-pointer">
                                        👑 Als Titel tragen
                                    </button>
                                @else
                                    <span class="inline-block w-full sm:w-auto px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-bold text-[11px] text-center">
                                        🔒 Noch gesperrt
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Die 5 schlichten Kern-Titel (jeweils Grau -> Silber -> Gold -> Diamant [Max]) --}}
                        <div>
                            <div class="mb-3">
                                <h4 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                                    Die 5 Manufaktur-Titel (bis zur Max-Stufe Diamant)
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Ab der Silber-Stufe kannst du jeden Titel frei in deinem Profil auswählen.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                @foreach($coreTitles as $tKey => $tInfo)
                                    @php
                                        $tier = $tInfo['tier'] ?? 'grau';
                                        $isTitleUnlocked = $tier !== 'grau';
                                        $isTitleActive = $activeTitleKey === $tKey;
                                        $tierBadgeClass = match($tier) {
                                            'diamant' => 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/40',
                                            'gold'    => 'bg-amber-500/15 text-amber-800 dark:text-amber-300 border-amber-500/40',
                                            'silber'  => 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-400/40',
                                            default   => 'bg-gray-200 dark:bg-gray-800 text-gray-500 border-gray-300 dark:border-gray-700',
                                        };
                                        $tierLabel = match($tier) {
                                            'diamant' => '💎 MAX • DIAMANT',
                                            'gold'    => '🥇 GOLD',
                                            'silber'  => '🥈 SILBER',
                                            default   => '🔒 GESPERRT',
                                        };
                                    @endphp
                                    <div class="rounded-2xl p-4 border {{ $isTitleActive ? 'border-2 border-[#C5A059] bg-amber-500/10' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/60' }} flex flex-col justify-between gap-3">
                                        <div>
                                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                                    <span>{{ $tInfo['icon'] ?? '🏆' }}</span>
                                                    <span>{{ $tInfo['name'] }}</span>
                                                </span>
                                                <span class="px-2 py-0.5 rounded-full border text-[9px] font-black uppercase tracking-wider {{ $tierBadgeClass }}">
                                                    {{ $tierLabel }}
                                                </span>
                                            </div>

                                            <h5 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                                                {{ $tInfo['tier_name'] }}
                                            </h5>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $tInfo['description'] }}
                                            </p>

                                            <div class="mt-3">
                                                <div class="flex justify-between text-[10px] font-bold text-gray-500 mb-1">
                                                    <span>{{ $tier === 'diamant' ? 'Maximale Stufe erreicht' : ('Nächste Stufe: ' . $tInfo['next_tier_name'] . ' (' . $tInfo['next_tier_label'] . ')') }}</span>
                                                    <span class="font-mono">{{ number_format($tInfo['current_value'], 0, ',', '.') }} / {{ number_format($tInfo['next_req'], 0, ',', '.') }}</span>
                                                </div>
                                                <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                                                    <div class="h-full {{ $tier === 'diamant' ? 'bg-gradient-to-r from-sky-400 to-indigo-500' : 'bg-gradient-to-r from-amber-400 to-[#C5A059]' }} transition-all duration-500"
                                                         style="width: {{ $tInfo['percentage'] }}%"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end">
                                            @if($isTitleActive)
                                                <span class="px-3 py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[10px] uppercase tracking-widest">
                                                    Aktiv im Profil
                                                </span>
                                            @elseif($isTitleUnlocked)
                                                <button type="button"
                                                        @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                                        wire:click="selectTitle('{{ $tKey }}')"
                                                        class="px-3 py-1 rounded-lg bg-amber-500/15 hover:bg-[#C5A059] text-amber-900 dark:text-[#f5dca0] hover:text-gray-950 font-black text-[10px] uppercase tracking-widest transition cursor-pointer">
                                                    Als Titel tragen
                                                </button>
                                            @else
                                                <span class="text-[10px] text-gray-400 font-semibold">
                                                    Freischaltung ab {{ number_format($tInfo['next_req'], 0, ',', '.') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Aufstiegs-Ränge (Level 1 bis 10) --}}
                        <div class="pt-2 border-t border-gray-200 dark:border-gray-800">
                            <div class="mb-3">
                                <h4 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                                    Deine Level-Ränge (Stufe 1 bis 10)
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Mit jedem Funki-Level schaltest du automatisch einen neuen Manufaktur-Rang frei:
                                </p>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                                @foreach($levelTitles as $lvlNum => $lvlTitle)
                                    @php
                                        $lvlKey = $lvlTitle['key'] ?? ('level_' . $lvlNum);
                                        $lvlUnlocked = $lvlNum <= $funkiLevel;
                                        $lvlActive = $activeTitleKey === $lvlKey || (!$activeTitleKey && $lvlNum === $funkiLevel);
                                    @endphp
                                    <div class="rounded-xl p-3 border {{ $lvlActive ? 'border-[#C5A059] bg-amber-500/10' : ($lvlUnlocked ? 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/50' : 'border-gray-200/60 dark:border-gray-800/50 bg-gray-100/50 dark:bg-gray-900/20 opacity-60') }} flex flex-col justify-between text-center">
                                        <div>
                                            <span class="text-[9px] font-black uppercase tracking-widest {{ $lvlUnlocked ? 'text-amber-700 dark:text-[#f5dca0]' : 'text-gray-400' }} block">
                                                Level {{ $lvlNum }} {{ $lvlUnlocked ? '✓' : '🔒' }}
                                            </span>
                                            <span class="text-xs font-bold text-gray-900 dark:text-white block mt-1 leading-snug">
                                                {{ $lvlTitle['name'] }}
                                            </span>
                                        </div>
                                        <div class="mt-2.5">
                                            @if($lvlActive)
                                                <span class="inline-block w-full py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[9px] uppercase tracking-widest">
                                                    Aktiv
                                                </span>
                                            @elseif($lvlUnlocked && $isGameModeActive)
                                                <button type="button"
                                                        @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                                        wire:click="selectTitle('{{ $lvlKey }}')"
                                                        class="w-full py-1 rounded-lg bg-amber-500/15 hover:bg-[#C5A059] text-amber-900 dark:text-[#f5dca0] hover:text-gray-950 font-black text-[9px] uppercase tracking-widest transition cursor-pointer">
                                                    Tragen
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- TAB 4: HALLE DER HELDEN (SPIELERNAME ODER ECHTNAME + RANGLISTE MIT PAGINIERUNG) --}}
                    <div x-show="activeFunkiTab === 'hall_of_fame'" style="display: none;" class="space-y-6">
                        {{-- Einstellungen für Halle der Helden & Spielername --}}
                        <div class="rounded-2xl p-4 sm:p-5 border border-[#C5A059]/40 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <span>🏛️</span>
                                        <span>Halle der Helden • Deine Anzeige-Einstellungen</span>
                                    </h3>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                        Entscheide selbst, ob du in der Halle der Helden sichtbar sein möchtest und ob dein <strong>Spielername</strong> oder dein <strong>Echtname (Vorname + Initial)</strong> angezeigt wird.
                                    </p>
                                </div>
                                <label class="inline-flex items-center gap-2.5 px-3.5 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 cursor-pointer shrink-0">
                                    <input type="checkbox" wire:model.live="rankingOptIn" @change="if(window.sfCockpitSound) window.sfCockpitSound('click')" class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500 cursor-pointer">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">In der Halle der Helden anzeigen (DSGVO Opt-In)</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start pt-2 border-t border-amber-500/20">
                                {{-- Auswahl: Spielername vs. Echtname --}}
                                <div class="md:col-span-5">
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">
                                        Anzeige-Modus
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button"
                                                @click="if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                                wire:click="$set('useRealName', false)"
                                                class="py-2.5 px-3 rounded-xl border text-xs font-bold transition cursor-pointer {{ !$useRealName ? 'bg-[#C5A059] text-gray-950 border-[#C5A059] shadow-xs' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                                            🎮 Spielername
                                        </button>
                                        <button type="button"
                                                @click="if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                                wire:click="$set('useRealName', true)"
                                                class="py-2.5 px-3 rounded-xl border text-xs font-bold transition cursor-pointer {{ $useRealName ? 'bg-[#C5A059] text-gray-950 border-[#C5A059] shadow-xs' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                                            👤 Echtname ({{ auth()->user()->first_name }} {{ mb_substr((string) auth()->user()->last_name, 0, 1) }}.)
                                        </button>
                                    </div>
                                </div>

                                {{-- Spielername Eingabefeld mit Hinweis & Validierung --}}
                                <div class="md:col-span-4">
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">
                                        Dein Spielername (3 bis 20 Zeichen • Einzigartig)
                                    </label>
                                    <input type="text"
                                           wire:model="playerName"
                                           minlength="3"
                                           maxlength="20"
                                           placeholder="z. B. Sternenfunke99"
                                           class="w-full py-2.5 px-3.5 rounded-xl bg-white dark:bg-gray-900 border {{ $playerNameError ? 'border-red-500 ring-1 ring-red-500/30' : 'border-gray-200 dark:border-gray-800' }} text-gray-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none">
                                    <p class="mt-1 text-[10px] text-gray-500 dark:text-gray-400 leading-snug">
                                        Mindestens 3 bis 20 Zeichen (Buchstaben, Zahlen, <code class="font-mono">-</code> und <code class="font-mono">_</code>) • Einzigartig &amp; frei von Beleidigungen/Extremismus.
                                    </p>
                                    @if($playerNameError)
                                        <p class="mt-1.5 text-[11px] font-bold text-red-600 dark:text-red-400 flex items-start gap-1">
                                            <span>⚠️</span>
                                            <span>{{ $playerNameError }}</span>
                                        </p>
                                    @endif
                                </div>

                                {{-- Speichern Button --}}
                                <div class="md:col-span-3 md:pt-5">
                                    <button type="button"
                                            @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                            wire:click="saveHallOfFameSettings"
                                            wire:loading.attr="disabled"
                                            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 hover:brightness-105 text-gray-950 font-black text-xs uppercase tracking-widest shadow-md transition cursor-pointer">
                                        <span wire:loading.remove wire:target="saveHallOfFameSettings">Speichern</span>
                                        <span wire:loading wire:target="saveHallOfFameSettings">Speichert…</span>
                                    </button>
                                </div>
                            </div>

                            @if($hofFeedbackMessage)
                                <div class="p-3 rounded-xl border text-xs font-bold flex items-center justify-between gap-2 animate-fade-in-up {{ $hofFeedbackSuccess ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-800 dark:text-emerald-300' : 'bg-red-500/15 border-red-500/40 text-red-700 dark:text-red-300' }}">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm">{{ $hofFeedbackSuccess ? '✅' : '⚠️' }}</span>
                                        <span>{{ $hofFeedbackMessage }}</span>
                                    </div>
                                    <button type="button" wire:click="$set('hofFeedbackMessage', null)" class="text-current opacity-70 hover:opacity-100 px-1.5 font-black cursor-pointer">&times;</button>
                                </div>
                            @endif
                        </div>

                        {{-- Rangliste der Helden mit logischer Paginierung --}}
                        <div class="space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <h4 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                                    Top Seelenfunken-Sammler ({{ $hallOfFameTotal }} {{ $hallOfFameTotal === 1 ? 'Held' : 'Helden' }})
                                </h4>
                                <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                    Seite {{ $hallOfFamePage }} von {{ $hallOfFameLastPage }} • Klicke auf ein Profilbild für die Detailansicht
                                </span>
                            </div>

                            @if(count($hallOfFameLeaders) > 0)
                                <div class="space-y-2.5">
                                    @foreach($hallOfFameLeaders as $leader)
                                        @php
                                            $pos = (int) $leader['rank_position'];
                                            $posBadge = match($pos) {
                                                1 => '🥇 #1',
                                                2 => '🥈 #2',
                                                3 => '🥉 #3',
                                                default => '#' . $pos,
                                            };
                                            $isMe = !empty($leader['is_current_user']);
                                            $leaderVideoFilter = $leader['video_filter_css'] ?? '';
                                            $leaderVideoTransform = $leader['video_transform_css'] ?? '';
                                        @endphp
                                        <div class="p-3 sm:p-4 rounded-2xl border {{ $isMe ? 'border-2 border-[#C5A059] bg-amber-500/10 shadow-md' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/60 hover:border-[#C5A059]/50' }} flex items-center justify-between gap-2.5 sm:gap-4 transition">
                                            <div class="flex items-center gap-2.5 sm:gap-4 min-w-0">
                                                <span class="w-9 sm:w-11 text-center font-mono font-black text-xs sm:text-sm {{ $pos <= 3 ? 'text-amber-600 dark:text-[#f5dca0]' : 'text-gray-400' }} shrink-0">
                                                    {{ $posBadge }}
                                                </span>

                                                {{-- Animierter Funki-Kreis des Helden --}}
                                                <button type="button"
                                                        @click="if(window.sfCockpitSound) window.sfCockpitSound('click'); heroProfileModal = @js($leader)"
                                                        title="Profil von {{ $leader['display_name'] }} öffnen"
                                                        class="relative w-12 h-12 sm:w-16 sm:h-16 rounded-full shrink-0 group cursor-pointer focus:outline-none">
                                                    <div style="{{ $leader['circle_css'] }}"
                                                         class="relative w-full h-full rounded-full border-2 border-[#C5A059] overflow-hidden flex items-center justify-center shadow-sm transition-transform duration-300 group-hover:scale-105">
                                                        @if(!empty($leader['video_url']))
                                                            <video src="{{ $leader['video_url'] }}"
                                                                   style="{{ $leaderVideoFilter ? 'filter: ' . $leaderVideoFilter . ';' : '' }} {{ $leaderVideoTransform ? 'transform: ' . $leaderVideoTransform . ';' : '' }}"
                                                                   autoplay loop muted playsinline
                                                                   class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                                                        @endif
                                                        <div class="absolute inset-[-18%] rounded-full opacity-55 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                                             style="background: conic-gradient(from 0deg, transparent 0%, {{ $leader['accent_hex'] }}55 25%, transparent 50%, {{ $leader['accent_hex'] }}44 75%, transparent 100%);"></div>
                                                        <img src="{{ $leader['custom_photo_url'] ?: $leader['image_url'] }}"
                                                             alt="{{ $leader['display_name'] }}"
                                                             class="relative z-10 {{ !empty($leader['custom_photo_url']) ? 'w-full h-full object-cover' : 'w-full h-full object-contain p-1.5 drop-shadow-[0_4px_8px_rgba(0,0,0,0.5)]' }}">
                                                    </div>
                                                    <span class="absolute -bottom-1 -right-1 z-20 px-1.5 py-0.5 rounded-full bg-gradient-to-r from-amber-300 via-[#C5A059] to-amber-500 text-gray-950 border border-white text-[8px] font-black">
                                                        Lvl. {{ $leader['level'] }}
                                                    </span>
                                                </button>

                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                                                        <button type="button"
                                                                @click="if(window.sfCockpitSound) window.sfCockpitSound('click'); heroProfileModal = @js($leader)"
                                                                class="font-serif font-bold text-sm sm:text-base text-gray-900 dark:text-white hover:text-amber-600 dark:hover:text-[#f5dca0] truncate cursor-pointer">
                                                            {{ $leader['display_name'] }}
                                                        </button>
                                                        @if($isMe)
                                                            <span class="px-2 py-0.5 rounded-full bg-[#C5A059] text-gray-950 text-[9px] font-black uppercase">Du</span>
                                                        @endif
                                                        <span class="px-2 sm:px-2.5 py-0.5 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-wider border {{ !empty($leader['is_seelengott']) ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 border-amber-300' : 'bg-amber-500/15 border-[#C5A059]/40 text-amber-800 dark:text-[#f5dca0]' }}">
                                                            {{ !empty($leader['is_seelengott']) ? '👑' : '🏆' }} {{ $leader['rank_name'] }}
                                                        </span>
                                                    </div>
                                                    <span class="text-[10px] sm:text-[11px] text-gray-500 dark:text-gray-400 block mt-0.5 truncate">
                                                        Level {{ $leader['level'] }} • {{ $leader['bg_name'] }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="text-right shrink-0">
                                                <span class="font-mono font-black text-xs sm:text-base text-amber-700 dark:text-[#f5dca0] block">
                                                    {{ number_format($leader['funken_total_earned'], 0, ',', '.') }} ✨
                                                </span>
                                                <span class="text-[9px] uppercase tracking-wider font-bold text-gray-400 block">
                                                    Lebenszeit-Funken
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Paginierungs-Leiste für die Halle der Helden --}}
                                @if($hallOfFameLastPage > 1)
                                    <div class="pt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 dark:border-gray-800">
                                        <button type="button"
                                                @click="if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                                wire:click="prevHallOfFamePage"
                                                @if($hallOfFamePage <= 1) disabled @endif
                                                class="px-3.5 py-1.5 rounded-xl border text-xs font-bold transition {{ $hallOfFamePage <= 1 ? 'border-gray-200 dark:border-gray-800 text-gray-400 cursor-not-allowed opacity-50' : 'border-[#C5A059]/50 bg-white dark:bg-gray-900 text-gray-900 dark:text-white hover:bg-[#C5A059] hover:text-gray-950 cursor-pointer' }}">
                                            ← Vorherige
                                        </button>

                                        <div class="flex items-center gap-1">
                                            @for($p = 1; $p <= $hallOfFameLastPage; $p++)
                                                @if($p === 1 || $p === $hallOfFameLastPage || abs($p - $hallOfFamePage) <= 2)
                                                    <button type="button"
                                                            @click="if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                                            wire:click="gotoHallOfFamePage({{ $p }})"
                                                            class="w-8 h-8 rounded-xl text-xs font-black transition cursor-pointer {{ $p === $hallOfFamePage ? 'bg-[#C5A059] text-gray-950 shadow-xs' : 'bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:bg-amber-500/20' }}">
                                                        {{ $p }}
                                                    </button>
                                                @elseif(abs($p - $hallOfFamePage) === 3)
                                                    <span class="text-xs text-gray-400 px-1">…</span>
                                                @endif
                                            @endfor
                                        </div>

                                        <button type="button"
                                                @click="if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                                wire:click="nextHallOfFamePage"
                                                @if($hallOfFamePage >= $hallOfFameLastPage) disabled @endif
                                                class="px-3.5 py-1.5 rounded-xl border text-xs font-bold transition {{ $hallOfFamePage >= $hallOfFameLastPage ? 'border-gray-200 dark:border-gray-800 text-gray-400 cursor-not-allowed opacity-50' : 'border-[#C5A059]/50 bg-white dark:bg-gray-900 text-gray-900 dark:text-white hover:bg-[#C5A059] hover:text-gray-950 cursor-pointer' }}">
                                            Nächste →
                                        </button>
                                    </div>
                                @endif
                            @else
                                <p class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 text-center">
                                    Aktuell hat sich noch kein Sammler für die Halle der Helden eingetragen. Setze oben den Haken und sei der Erste! ✨
                                </p>
                            @endif
                        </div>
                    </div>

                </div>

                {{-- Modal Footer: Funken-Suche Status & Umschalter (Kunde hat es selbst in der Hand wie bei Kaufland XTRA) --}}
                <div class="px-4 sm:px-8 py-3.5 sm:py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/70 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                        <span class="w-2 h-2 rounded-full {{ $isGameModeActive ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                        <span>
                            Funken-Suche auf den Shop-Seiten:
                            <strong class="text-gray-900 dark:text-white">{{ $isGameModeActive ? 'Aktiviert' : 'Pausiert (Fortschritt gespeichert)' }}</strong>
                        </span>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-2.5 w-full sm:w-auto justify-end">
                        @if($isGameModeActive)
                            <button type="button"
                                    @click="closeFunkiModal()"
                                    wire:click="deactivateSeelenfunke"
                                    class="flex-1 sm:flex-none px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800 text-[10px] font-black uppercase tracking-widest transition cursor-pointer">
                                Funken-Suche pausieren
                            </button>
                        @else
                            <button type="button"
                                    @click="openActivateMagicModal()"
                                    class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 text-[10px] font-black uppercase tracking-widest transition cursor-pointer">
                                ✨ Magie aktivieren
                            </button>
                        @endif
                        <button type="button"
                                @click="closeFunkiModal()"
                                class="px-5 py-2 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-[10px] font-black uppercase tracking-widest transition cursor-pointer">
                            Schließen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- =========================================================================
         MODAL 3: GROSSANSICHT (LIGHTBOX) FÜR FUNKI & ANIMIERTEN HINTERGRUND
         ========================================================================= --}}
    <template x-teleport="body">
        <div x-show="showFunkiZoomModal && zoomStageData"
             style="display: none;"
             @keydown.escape.window="showFunkiZoomModal = false; if(window.sfCockpitSound) window.sfCockpitSound('back')"
             class="fixed inset-0 z-[9600] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/85 backdrop-blur-lg"
                 @click="showFunkiZoomModal = false; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                 x-transition.opacity></div>

            <template x-if="zoomStageData">
                <div class="relative z-10 w-full max-w-lg rounded-[2.5rem] bg-white dark:bg-[#0b0e17] border-2 border-[#C5A059] shadow-[0_0_80px_rgba(197,160,89,0.4)] p-5 sm:p-9 text-center overflow-hidden">
                    <button type="button"
                            @click="showFunkiZoomModal = false; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                            class="absolute top-4 right-4 sm:top-5 sm:right-5 z-30 p-2.5 rounded-xl bg-gray-100 dark:bg-gray-900 hover:bg-red-500 text-gray-500 hover:text-white transition-colors cursor-pointer">
                        <x-heroicon-m-x-mark class="w-5 h-5" />
                    </button>

                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-amber-500/15 border border-[#C5A059]/50 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest mb-4"
                          x-text="'STUFE ' + zoomStageData.level + ' • GROSSANSICHT'"></span>

                    {{-- Riesiger animierter Kreis (Mobil-optimiert w-60 h-60 sm:w-80 sm:h-80) --}}
                    <div class="relative w-60 h-60 sm:w-80 sm:h-80 mx-auto my-3 flex items-center justify-center select-none">
                        <div class="absolute inset-[-8px] rounded-full border border-dashed border-[#C5A059]/50 pointer-events-none"></div>
                        <div class="absolute inset-[-16px] rounded-full border border-dotted border-[#C5A059]/30 animate-[spin_28s_linear_infinite] pointer-events-none"></div>

                        <div :style="zoomStageData.circle_css"
                             class="relative w-full h-full rounded-full border-4 border-[#C5A059] shadow-[0_0_55px_rgba(197,160,89,0.55)] overflow-hidden flex items-center justify-center bg-gray-950">
                            <template x-if="zoomStageData.video_url">
                                <video :src="zoomStageData.video_url"
                                       :style="(zoomStageData.video_filter_css ? 'filter:' + zoomStageData.video_filter_css + ';' : '') + (zoomStageData.video_transform_css ? 'transform:' + zoomStageData.video_transform_css + ';' : '')"
                                       autoplay loop muted playsinline
                                       class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                            </template>
                            <div class="absolute inset-[-18%] rounded-full opacity-60 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                 :style="`background: conic-gradient(from 0deg, transparent 0%, ${zoomStageData.accent_hex}66 25%, transparent 50%, ${zoomStageData.accent_hex}55 75%, transparent 100%);`"></div>
                            <img :src="zoomStageData.image_url"
                                 :alt="zoomStageData.name"
                                 class="relative z-10 w-full h-full object-contain p-4 sm:p-5 drop-shadow-[0_14px_28px_rgba(0,0,0,0.7)]">
                        </div>
                    </div>

                    <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white mt-4" x-text="zoomStageData.name"></h3>
                    <p class="text-xs font-bold text-amber-700 dark:text-[#f5dca0] mt-1" x-text="'Hintergrund: ' + zoomStageData.bg_name"></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="zoomStageData.bg_subtitle || zoomStageData.subtitle"></p>

                    <button type="button"
                            @click="showFunkiZoomModal = false; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                            class="mt-6 w-full py-3 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black text-xs uppercase tracking-widest shadow-md cursor-pointer">
                        Großansicht schließen
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- =========================================================================
         MODAL 4: HELDEN-PROFIL-MODAL (KLICK AUF SPIELER IN DER HALLE DER HELDEN)
         ========================================================================= --}}
    <template x-teleport="body">
        <div x-show="heroProfileModal !== null"
             style="display: none;"
             @keydown.escape.window="heroProfileModal = null; if(window.sfCockpitSound) window.sfCockpitSound('back')"
             class="fixed inset-0 z-[9600] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/80 backdrop-blur-md"
                 @click="heroProfileModal = null; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                 x-transition.opacity></div>

            <template x-if="heroProfileModal">
                <div class="relative z-10 w-full max-w-md rounded-[2rem] bg-white dark:bg-[#0f131d] border-2 border-[#C5A059] shadow-2xl p-6 sm:p-8 text-center">
                    <button type="button"
                            @click="heroProfileModal = null; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                            class="absolute top-4 right-4 z-20 p-2 rounded-xl bg-gray-100 dark:bg-gray-900 hover:bg-red-500 text-gray-500 hover:text-white transition-colors cursor-pointer">
                        <x-heroicon-m-x-mark class="w-5 h-5" />
                    </button>

                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 border border-[#C5A059]/40 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest mb-4">
                        <span>🏛️</span>
                        <span x-text="'Halle der Helden • Rang #' + (heroProfileModal.rank_position || 1)"></span>
                    </span>

                    <div class="relative w-40 h-40 sm:w-52 sm:h-52 mx-auto mb-5 flex items-center justify-center">
                        <div class="absolute inset-[-6px] rounded-full border border-dashed border-[#C5A059]/50 pointer-events-none"></div>
                        <div :style="heroProfileModal.circle_css"
                             class="relative w-full h-full rounded-full border-2 border-[#C5A059] shadow-[0_0_40px_rgba(197,160,89,0.45)] overflow-hidden flex items-center justify-center bg-gray-950">
                            <template x-if="heroProfileModal.video_url">
                                <video :src="heroProfileModal.video_url"
                                       :style="(heroProfileModal.video_filter_css ? 'filter:' + heroProfileModal.video_filter_css + ';' : '') + (heroProfileModal.video_transform_css ? 'transform:' + heroProfileModal.video_transform_css + ';' : '')"
                                       autoplay loop muted playsinline
                                       class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                            </template>
                            <div class="absolute inset-[-18%] rounded-full opacity-55 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                 :style="`background: conic-gradient(from 0deg, transparent 0%, ${heroProfileModal.accent_hex}55 25%, transparent 50%, ${heroProfileModal.accent_hex}44 75%, transparent 100%);`"></div>
                            <img :src="heroProfileModal.custom_photo_url || heroProfileModal.image_url"
                                 :alt="heroProfileModal.display_name"
                                 :class="heroProfileModal.custom_photo_url ? 'w-full h-full object-cover' : 'w-full h-full object-contain p-4 drop-shadow-[0_10px_20px_rgba(0,0,0,0.65)]'"
                                 class="relative z-10">
                        </div>
                    </div>

                    <h4 class="text-2xl font-serif font-bold text-gray-900 dark:text-white" x-text="heroProfileModal.display_name"></h4>
                    <div class="mt-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border"
                              :class="heroProfileModal.is_seelengott ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 border-amber-300' : 'bg-amber-500/15 border-[#C5A059]/50 text-amber-900 dark:text-[#f5dca0]'">
                            <span x-text="heroProfileModal.is_seelengott ? '👑' : '🏆'"></span>
                            <span x-text="heroProfileModal.rank_name"></span>
                        </span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3" x-text="'Level ' + heroProfileModal.level + ' • ' + heroProfileModal.bg_name"></p>

                    <div class="mt-4 grid grid-cols-2 gap-2.5">
                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">Diamant-Titel</span>
                            <span class="text-sm font-mono font-black text-amber-700 dark:text-[#f5dca0]" x-text="heroProfileModal.diamonds_count + ' / ' + heroProfileModal.total_titles + ' 💎'"></span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">Lebenszeit-Funken</span>
                            <span class="text-sm font-mono font-black text-amber-700 dark:text-[#f5dca0]" x-text="(heroProfileModal.funken_total_earned || 0).toLocaleString('de-DE') + ' ✨'"></span>
                        </div>
                    </div>

                    <button type="button"
                            @click="heroProfileModal = null; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                            class="mt-5 w-full py-2.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-black uppercase tracking-widest transition cursor-pointer">
                        Schließen
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- WebAudio UI-Sound-Synthesizer (sfCockpitSound) + 60 FPS "Magie aktivieren" Script --}}
    <script>
        window.sfCockpitSound = function(type = 'click') {
            try {
                if (window._sfFunkiMuted) return;
                const volScale = Math.max(0, Math.min(1, (typeof window._sfFunkiVolume === 'number' ? window._sfFunkiVolume : 35) / 100));
                if (volScale <= 0.01) return;

                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                if (!window._sfCockpitAudioCtx) {
                    window._sfCockpitAudioCtx = new Ctx();
                }
                const ctx = window._sfCockpitAudioCtx;
                if (ctx.state === 'suspended') {
                    ctx.resume().catch(() => {});
                }
                const now = ctx.currentTime;

                if (type === 'activate') {
                    const notes = [440, 554.37, 659.25, 880, 1174.66];
                    notes.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'triangle';
                        const start = now + idx * 0.09;
                        osc.frequency.setValueAtTime(freq, start);
                        osc.frequency.exponentialRampToValueAtTime(freq * 1.06, start + 0.22);
                        gain.gain.setValueAtTime(0.0001, start);
                        gain.gain.linearRampToValueAtTime(0.08 * volScale, start + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.26);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(start);
                        osc.stop(start + 0.27);
                    });
                    return;
                }

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);

                if (type === 'hover') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(980, now);
                    osc.frequency.exponentialRampToValueAtTime(1180, now + 0.035);
                    gain.gain.setValueAtTime(0.018 * volScale, now);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.04);
                    osc.start(now);
                    osc.stop(now + 0.045);
                } else if (type === 'switch') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(520, now);
                    osc.frequency.exponentialRampToValueAtTime(880, now + 0.09);
                    gain.gain.setValueAtTime(0.065 * volScale, now);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.10);
                    osc.start(now);
                    osc.stop(now + 0.11);
                } else if (type === 'back') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(620, now);
                    osc.frequency.exponentialRampToValueAtTime(380, now + 0.09);
                    gain.gain.setValueAtTime(0.055 * volScale, now);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.10);
                    osc.start(now);
                    osc.stop(now + 0.11);
                } else {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(740, now);
                    osc.frequency.exponentialRampToValueAtTime(1120, now + 0.075);
                    gain.gain.setValueAtTime(0.065 * volScale, now);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.085);
                    osc.start(now);
                    osc.stop(now + 0.09);
                }
            } catch (e) {}
        };

        window.optInScreen = function() {
            return {
                agreedToTerms: false,
                isWarping: false,
                isActivating: false,
                phase: 0,
                originX: window.innerWidth / 2,
                originY: window.innerHeight / 2,
                eclipseRadius: 0,
                _ambientRaf: null,
                _magicRaf: null,

                isDark() {
                    return document.documentElement.classList.contains('dark');
                },

                initAmbientSparks() {
                    const canvas = this.$refs.ambientSparksCanvas;
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    let w = 0, h = 0;
                    const dpr = Math.min(window.devicePixelRatio || 1, 2);

                    const resize = () => {
                        if (!canvas.parentElement) return;
                        const rect = canvas.parentElement.getBoundingClientRect();
                        w = Math.max(300, rect.width);
                        h = Math.max(300, rect.height);
                        canvas.width = w * dpr;
                        canvas.height = h * dpr;
                        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                    };
                    resize();

                    const sparks = Array.from({ length: 28 }, () => ({
                        x: Math.random() * w,
                        y: Math.random() * h,
                        r: 1 + Math.random() * 2.2,
                        vx: (Math.random() - 0.5) * 0.35,
                        vy: -(0.25 + Math.random() * 0.55),
                        phase: Math.random() * Math.PI * 2,
                        speed: 0.02 + Math.random() * 0.03
                    }));

                    const renderAmbient = () => {
                        if (this.isActivating) return;
                        ctx.clearRect(0, 0, w, h);
                        const dark = this.isDark();
                        for (const s of sparks) {
                            s.x += s.vx + Math.sin(s.phase) * 0.15;
                            s.y += s.vy;
                            s.phase += s.speed;
                            if (s.y < -10) { s.y = h + 10; s.x = Math.random() * w; }
                            const alpha = (0.25 + 0.5 * (0.5 + 0.5 * Math.sin(s.phase)));
                            ctx.fillStyle = dark ? `rgba(253, 224, 71, ${alpha})` : `rgba(217, 119, 6, ${alpha})`;
                            ctx.beginPath();
                            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                            ctx.fill();
                        }
                        this._ambientRaf = requestAnimationFrame(renderAmbient);
                    };
                    this._ambientRaf = requestAnimationFrame(renderAmbient);
                },

                triggerEpicStart(event) {
                    if (window.sfCockpitSound) {
                        window.sfCockpitSound('activate');
                    }
                    if (event && event.currentTarget) {
                        const rect = event.currentTarget.getBoundingClientRect();
                        this.originX = rect.left + rect.width / 2;
                        this.originY = rect.top + rect.height / 2;
                    }
                    if (this._ambientRaf) {
                        cancelAnimationFrame(this._ambientRaf);
                        this._ambientRaf = null;
                    }
                    this.isWarping = true;
                    this.isActivating = true;
                    this.phase = 0;
                    this.eclipseRadius = 0;

                    this.$nextTick(() => {
                        this.startGoldenSparksSequence();
                        requestAnimationFrame(() => {
                            this.eclipseRadius = 150;
                            this.phase = 1;
                        });
                    });

                    setTimeout(() => { this.phase = 2; }, 900);
                    setTimeout(() => { this.phase = 3; }, 1800);
                    setTimeout(() => {
                        if (this._magicRaf) {
                            cancelAnimationFrame(this._magicRaf);
                            this._magicRaf = null;
                        }
                        this.isActivating = false;
                        this.isWarping = false;
                        Promise.resolve(this.$wire.activateSeelenfunke()).finally(() => {
                            try {
                                sessionStorage.removeItem('sf_funki_bgm_session_enabled');
                                localStorage.setItem('sf_funki_audio_muted', '1');
                                localStorage.setItem('sf_funki_bgm_muted', '1');
                            } catch (e) {}
                            if (window.SfFunkiBgm) {
                                window.SfFunkiBgm.setMuted(true, false);
                            }
                            window.dispatchEvent(new CustomEvent('magic-activated-complete'));
                            window.location.href = '{{ route('customer.funki') }}';
                        });
                    }, 2050);
                },

                startGoldenSparksSequence() {
                    const canvas = this.$refs.magicSparksCanvas;
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d', { alpha: true });
                    const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
                    const w = window.innerWidth;
                    const h = window.innerHeight;
                    canvas.width = w * dpr;
                    canvas.height = h * dpr;
                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                    const centerX = w / 2;
                    const centerY = h / 2;
                    const particles = Array.from({ length: 72 }, (_, i) => {
                        const angle = Math.random() * Math.PI * 2;
                        const dist = 40 + Math.random() * Math.max(w, h) * 0.4;
                        return {
                            x: centerX + Math.cos(angle) * dist,
                            y: centerY + Math.sin(angle) * dist,
                            orbitAngle: angle,
                            orbitRadius: dist,
                            orbitSpeed: (0.05 + Math.random() * 0.05) * (i % 2 === 0 ? 1 : -1),
                            vx: Math.cos(angle) * (5 + Math.random() * 12),
                            vy: Math.sin(angle) * (5 + Math.random() * 12),
                            r: 2 + Math.random() * 3.5,
                            exploded: false
                        };
                    });

                    const startTime = performance.now();
                    const animate = (now) => {
                        const elapsed = now - startTime;
                        ctx.clearRect(0, 0, w, h);
                        const isSupernova = elapsed >= 900;

                        for (const p of particles) {
                            if (!isSupernova) {
                                p.orbitAngle += p.orbitSpeed;
                                p.orbitRadius = Math.max(6, p.orbitRadius * 0.94);
                                p.x = centerX + Math.cos(p.orbitAngle) * p.orbitRadius;
                                p.y = centerY + Math.sin(p.orbitAngle) * p.orbitRadius;
                            } else {
                                if (!p.exploded) {
                                    p.exploded = true;
                                    p.x = centerX;
                                    p.y = centerY;
                                }
                                p.x += p.vx;
                                p.y += p.vy;
                                p.vx *= 0.96;
                                p.vy *= 0.96;
                            }
                            ctx.fillStyle = '#FBBF24';
                            ctx.beginPath();
                            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                            ctx.fill();
                        }

                        if (elapsed < 2050) {
                            this._magicRaf = requestAnimationFrame(animate);
                        }
                    };
                    this._magicRaf = requestAnimationFrame(animate);
                }
            };
        };
    </script>
</div>
