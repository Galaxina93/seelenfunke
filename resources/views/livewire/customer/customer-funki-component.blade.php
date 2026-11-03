<div x-data="{
        activeFunkiTab: @js($initialTab ?: 'vouchers'),
        showActivateMagicModal: false,
        showFunkiZoomModal: false,
        zoomStageData: null,
        heroProfileModal: null,
        audioVolume: @js((int) $funkiAudioVolume),
        audioMuted: @js((bool) $funkiAudioMuted),
        audioKeepPlaying: @js((bool) $funkiAudioKeepPlaying),
        audioExpanded: false,
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

            // Registriert die Funki-Ansicht im Audio-Controller (spielt nur, wenn der Kunde den Ton in dieser Session aktiv eingeschaltet hat)
            this.playFunkiBgm();
        },
        openActivateMagicModal() {
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
            audio.volume = 0;
            try { audio.pause(); } catch (e) {}
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
            } else if (!this.audioMuted) {
                this.playFunkiBgm();
            } else {
                this.pauseFunkiBgm();
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
     @magic-activated-complete.window="showActivateMagicModal = false"
     class="w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-7 animate-fade-in-up space-y-6">

    @php
        $lvlCfg = $currentLevelConfig ?: \App\Services\Gamification\GameConfig::getLevelConfig($funkiLevel ?: 1);
        $lvlImage = asset($lvlCfg['image_path'] ?? 'shop/customer/gamification/models/images/original/funki_lvl_1_rags.png');
        $lvlCircleCss = $lvlCfg['circle_css'] ?? '';
        $lvlVideoUrl = $lvlCfg['video_url'] ?? null;
        $lvlVideoFilter = $lvlCfg['video_filter_css'] ?? '';
        $lvlVideoTransform = $lvlCfg['video_transform_css'] ?? '';
        $lvlAccentHex = $lvlCfg['accent_hex'] ?? '#c5a059';
        $arcText = $epBottomArcText ?: ($isMaxLevel ? '100% • MAXIMALES LEVEL ERREICHT' : ($progressPercentage . '% • NOCH ' . number_format($missingSparks, 0, ',', '.') . ' FUNKEN'));
        $defaultLevelRank = \App\Services\Gamification\GameConfig::getLevelTitles()[$funkiLevel]['name'] ?? 'Kleiner Seelenfunke';
        $headerTitleLabel = $currentRankName ?: ($defaultLevelRank ?? 'Kleiner Seelenfunke');
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
    @endphp

    {{-- =========================================================================
         HERO CARD: DEIN FUNKI, AUDIO-STEUERUNG, KENNZAHLEN & TABS
         ========================================================================= --}}
    <div class="relative overflow-hidden bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-[#C5A059]/30 rounded-3xl sm:rounded-[2rem] p-5 sm:p-7 shadow-sm dark:shadow-2xl">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-amber-500/10 via-transparent to-transparent pointer-events-none"></div>

        {{-- Top Action Row: Zurück zum Dashboard + Musik-Steuerung + Status --}}
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-2 pb-4 mb-4 border-b border-gray-100 dark:border-gray-800/80">
            <a href="{{ route('customer.dashboard') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                <x-heroicon-m-arrow-left class="w-4 h-4" />
                <span>Dashboard</span>
            </a>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Status-Switch Funken-Suche --}}
                <button type="button"
                        role="switch"
                        aria-checked="{{ $isGameModeActive ? 'true' : 'false' }}"
                        @if($isGameModeActive)
                            wire:click="deactivateSeelenfunke"
                            title="Funken-Suche auf den Shop-Seiten deaktivieren"
                        @else
                            @click="openActivateMagicModal()"
                            title="Funken-Suche auf den Shop-Seiten aktivieren"
                        @endif
                        class="group inline-flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-gray-50 dark:bg-gray-900 border {{ $isGameModeActive ? 'border-emerald-500/30 hover:border-emerald-500/60' : 'border-gray-200 dark:border-gray-800 hover:border-[#C5A059]/50' }} transition-all cursor-pointer select-none">
                    <span class="text-[10px] font-black uppercase tracking-wider {{ $isGameModeActive ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-500 dark:text-gray-400' }}">
                        {{ $isGameModeActive ? 'Funken-Suche aktiv' : 'Funken-Suche aus' }}
                    </span>
                    <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-200 {{ $isGameModeActive ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.35)]' : 'bg-gray-300 dark:bg-gray-700' }}">
                        <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform duration-200 {{ $isGameModeActive ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                    </span>
                </button>

                {{-- Audio-Steuerung --}}
                <div @click.outside="audioExpanded = false" class="relative flex items-center">
                    <button x-show="!audioExpanded"
                            type="button"
                            @click="audioExpanded = true"
                            title="Funki Musik-Einstellungen"
                            aria-label="Funki Musik öffnen"
                            class="p-2 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-amber-600 dark:text-[#C5A059] hover:border-[#C5A059] transition-all cursor-pointer shrink-0">
                        <svg x-show="!audioMuted && Number(audioVolume) > 0" class="w-4 h-4 text-amber-600 dark:text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.54 8.46a5 5 0 010 7.07M19.07 4.93a10 10 0 010 14.14" />
                        </svg>
                        <svg x-show="audioMuted || Number(audioVolume) === 0" style="display: none;" class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M23 9l-6 6m0-6l6 6" />
                        </svg>
                    </button>

                    <div x-show="audioExpanded"
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="flex flex-wrap items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-[#C5A059]/40 dark:border-gray-800 shadow-sm min-w-0">
                        <button type="button"
                                @click="toggleMute()"
                                :title="audioMuted ? 'Ton einschalten' : 'Stummschalten'"
                                class="p-1 rounded-lg text-gray-600 dark:text-gray-300 hover:text-amber-600 dark:hover:text-[#f5dca0] transition cursor-pointer shrink-0">
                            <svg x-show="!audioMuted && Number(audioVolume) > 0" class="w-4 h-4 text-amber-600 dark:text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.54 8.46a5 5 0 010 7.07M19.07 4.93a10 10 0 010 14.14" />
                            </svg>
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
                               title="Musik beim Seitenwechsel konstant weiterlaufen lassen">
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
            </div>
        </div>

        {{-- Avatar & Hero Summary --}}
        <div class="relative z-10 flex flex-col md:flex-row items-center gap-5 sm:gap-7">
            <button type="button"
                    @click="openFunkiZoom(@js($currentZoomPayload))"
                    title="Klicken für Großansicht"
                    class="relative w-32 h-32 sm:w-36 sm:h-36 flex items-center justify-center shrink-0 select-none group cursor-pointer focus:outline-none">
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
                        <path id="sf-page-avatar-arc" d="M 22,128 A 83,83 0 0,0 178,128" fill="none" />
                    </defs>
                    <path d="M 32,142 A 83,83 0 0,0 168,142" fill="none" stroke="rgba(11,15,25,0.82)" stroke-width="15" stroke-linecap="round" />
                    <text class="fill-[#f5dca0] font-black uppercase" style="font-size: 8.2px; letter-spacing: 0.14em;">
                        <textPath href="#sf-page-avatar-arc" startOffset="50%" text-anchor="middle">
                            {{ $arcText }}
                        </textPath>
                    </text>
                </svg>

                <div style="{{ $lvlCircleCss }}"
                     class="absolute inset-2.5 rounded-full bg-white dark:bg-[#13151e] border-2 border-[#C5A059] shadow-[0_0_30px_rgba(197,160,89,0.35)] flex items-center justify-center overflow-hidden z-10 transition-transform duration-300 group-hover:scale-[1.03]">
                    @if(!empty($lvlVideoUrl))
                        <video src="{{ asset(ltrim($lvlVideoUrl, '/')) }}"
                               style="{{ $lvlVideoFilter ? 'filter: ' . $lvlVideoFilter . ';' : '' }} {{ $lvlVideoTransform ? 'transform: ' . $lvlVideoTransform . ';' : '' }}"
                               autoplay loop muted playsinline
                               class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                    @endif
                    <div class="absolute inset-[-18%] rounded-full opacity-55 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                         style="background: conic-gradient(from 0deg, transparent 0%, {{ $lvlAccentHex }}55 25%, transparent 50%, {{ $lvlAccentHex }}44 75%, transparent 100%);"></div>
                    <img src="{{ $lvlImage }}" alt="Funki" class="relative z-10 w-full h-full object-contain p-3 drop-shadow-[0_10px_20px_rgba(0,0,0,0.6)]">
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center z-20">
                        <span class="px-2.5 py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[9px] uppercase tracking-widest shadow-lg">
                            🔍 Zoom
                        </span>
                    </div>
                </div>

                <span class="absolute -top-1 -right-1 z-40 w-7 h-7 rounded-full bg-gradient-to-b from-amber-300 via-[#C5A059] to-amber-600 text-gray-950 border border-amber-100 shadow-md flex items-center justify-center font-black text-[11px]">
                    {{ $funkiLevel }}
                </span>
            </button>

            <div class="flex-1 text-center md:text-left min-w-0 w-full">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-500/10 border border-[#C5A059]/30 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest mb-1">
                    <span>✨ Level {{ $funkiLevel }}</span>
                    <span>•</span>
                    <span>{{ $lvlCfg['bg_name'] ?? 'Manufaktur' }}</span>
                </div>

                <h1 class="text-xl sm:text-2xl lg:text-3xl font-serif font-bold text-gray-900 dark:text-white leading-tight break-words">
                    {{ $heroDisplayName }} <span class="text-[#C5A059]">•</span> <span class="text-amber-700 dark:text-[#f5dca0]">{{ $headerTitleLabel }}</span>
                </h1>

                <div class="mt-4 grid grid-cols-3 gap-2.5 sm:gap-3">
                    <div class="p-3 rounded-2xl bg-amber-500/10 border border-[#C5A059]/40">
                        <span class="text-[9px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-300 block">Guthaben</span>
                        <span class="text-base sm:text-xl font-mono font-black text-gray-900 dark:text-[#f5dca0]">{{ number_format($funkenBalance, 0, ',', '.') }} ✨</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800">
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 block">Gesamt-Funken</span>
                        <span class="text-base sm:text-xl font-mono font-black text-gray-900 dark:text-white">{{ number_format($funkenTotalEarned, 0, ',', '.') }} ✨</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800">
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 block">Treue-Bonus</span>
                        <span class="text-xs sm:text-sm font-black text-emerald-600 dark:text-emerald-400 block mt-0.5">1 € = +1 ✨</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Clean 4-Tab Bar --}}
        <div class="relative z-10 grid grid-cols-2 sm:grid-cols-4 gap-2 mt-6 pt-5 border-t border-gray-100 dark:border-gray-800/80">
            <button type="button"
                    @click="activeFunkiTab = 'vouchers'; if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                    :class="activeFunkiTab === 'vouchers' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black shadow-md' : 'bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                    class="px-4 py-2.5 rounded-xl text-xs uppercase tracking-wider transition-all cursor-pointer text-center">
                🎁 Prämien & Rabatte
            </button>
            <button type="button"
                    @click="activeFunkiTab = 'levels'; if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                    :class="activeFunkiTab === 'levels' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black shadow-md' : 'bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                    class="px-4 py-2.5 rounded-xl text-xs uppercase tracking-wider transition-all cursor-pointer text-center">
                ✨ Level 1–10
            </button>
            <button type="button"
                    @click="activeFunkiTab = 'titles'; if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                    :class="activeFunkiTab === 'titles' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black shadow-md' : 'bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                    class="px-4 py-2.5 rounded-xl text-xs uppercase tracking-wider transition-all cursor-pointer text-center">
                🏆 Titel ({{ $titlesData['diamonds_count'] ?? 0 }}/{{ $titlesData['total_titles'] ?? 5 }})
            </button>
            <button type="button"
                    @click="activeFunkiTab = 'hall_of_fame'; if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                    :class="activeFunkiTab === 'hall_of_fame' ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black shadow-md' : 'bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold'"
                    class="px-4 py-2.5 rounded-xl text-xs uppercase tracking-wider transition-all cursor-pointer text-center">
                🏛️ Halle der Helden
            </button>
        </div>
    </div>

    {{-- =========================================================================
         TAB 1: PRÄMIEN & RABATT-GUTSCHEINE (CLEAN & SELBSTERKLÄREND)
         ========================================================================= --}}
    <div x-show="activeFunkiTab === 'vouchers'" class="space-y-6">
        <div class="bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Rabatt-Gutscheine freischalten</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Einmalig für deine nächste Bestellung einlösbar • Dein Level sinkt beim Einlösen nie.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($redeemableVouchers as $vKey => $vCfg)
                    @php
                        $cost = (int) $vCfg['cost_sparks'];
                        $canAfford = $isGameModeActive && $funkenBalance >= $cost;
                        $pctProgress = min(100, (int) round(($funkenBalance / max(1, $cost)) * 100));
                    @endphp
                    <div class="rounded-2xl p-5 border {{ $canAfford ? 'bg-gradient-to-b from-amber-500/10 to-transparent border-[#C5A059]' : 'bg-gray-50 dark:bg-gray-900/60 border-gray-200 dark:border-gray-800' }} flex flex-col justify-between gap-4">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2.5">
                                <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest">
                                    {{ $vCfg['badge'] }}
                                </span>
                                <span class="font-mono text-sm font-black text-gray-900 dark:text-white">
                                    {{ number_format($cost, 0, ',', '.') }} ✨
                                </span>
                            </div>
                            <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white">
                                {{ $vCfg['name'] }}
                            </h3>

                            <div class="mt-4">
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
                                Magie aktivieren
                            </button>
                        @else
                            <button type="button"
                                    @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                    wire:click="redeemVoucher('{{ $vKey }}')"
                                    wire:loading.attr="disabled"
                                    @if(!$canAfford) disabled @endif
                                    class="w-full py-2.5 rounded-xl font-black text-xs uppercase tracking-widest transition {{ $canAfford ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 hover:brightness-105 text-gray-950 shadow-md cursor-pointer' : 'bg-gray-200 dark:bg-gray-800 text-gray-400 dark:text-gray-600 cursor-not-allowed' }}">
                                <span wire:loading.remove wire:target="redeemVoucher('{{ $vKey }}')">
                                    {{ $canAfford ? 'Für ' . $cost . ' ✨ einlösen' : 'Noch ' . ($cost - $funkenBalance) . ' ✨' }}
                                </span>
                                <span wire:loading wire:target="redeemVoucher('{{ $vKey }}')">Wird erstellt…</span>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Freigeschaltete Gutscheine --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-serif font-bold text-gray-900 dark:text-white mb-3">
                    Deine freigeschalteten Gutschein-Codes ({{ count($customerVouchers) }})
                </h3>
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
                        Noch kein Gutschein eingelöst. Ab 60 ✨ kannst du oben deinen ersten Rabatt-Code freischalten.
                    </p>
                @endif
            </div>
        </div>

        {{-- Jährliche Bonus-Funken (Geburtstag & Jahrestag) --}}
        @php
            $bdayFormatted = $loyaltyMilestones['birthday_formatted'] ?? null;
            $bdayDayMonth = $loyaltyMilestones['birthday_day_month'] ?? null;
            $bdaySparks = (int) ($loyaltyMilestones['birthday_sparks'] ?? 50);
            $bdayCanChange = (bool) ($loyaltyMilestones['can_change_birthday'] ?? true);
            $bdayAwardedThisYear = !empty($loyaltyMilestones['birthday_awarded_this_year']);
            $annivDayMonth = $loyaltyMilestones['anniversary_day_month'] ?? null;
            $annivMemberSince = $loyaltyMilestones['member_since_formatted'] ?? null;
            $annivSparks = (int) ($loyaltyMilestones['anniversary_sparks'] ?? 50);
            $annivAwardedThisYear = !empty($loyaltyMilestones['anniversary_awarded_this_year']);
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Geburtstags-Funke --}}
            <div class="rounded-3xl p-5 bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-gray-800 flex flex-col justify-between gap-4 shadow-sm">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest">
                            🎂 Geburtstags-Geschenk
                        </span>
                        <span class="font-mono text-xs font-black text-amber-700 dark:text-[#f5dca0]">+{{ $bdaySparks }} ✨ / Jahr</span>
                    </div>
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white">Jährlicher Geburtstags-Funke</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Automatisch +{{ $bdaySparks }} ✨ an deinem Ehrentag.</p>
                </div>

                <div class="space-y-2">
                    @if($bdayFormatted)
                        <div class="flex items-center justify-between gap-2 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800">
                            <span class="text-xs font-bold text-gray-900 dark:text-white">Jährlich am {{ $bdayDayMonth }} ({{ $bdayFormatted }})</span>
                            <span class="px-2.5 py-0.5 rounded-lg bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase shrink-0">
                                {{ $bdayAwardedThisYear ? '✓ ' . now()->year . ' erhalten' : '✓ Aktiv' }}
                            </span>
                        </div>
                    @else
                        <div class="flex items-center justify-between gap-2 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Geburtsdatum noch nicht hinterlegt</span>
                            <span class="px-2.5 py-0.5 rounded-lg bg-gray-200/80 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[10px] font-black uppercase shrink-0">
                                Nicht aktiv
                            </span>
                        </div>
                    @endif

                    @if(!$bdayFormatted || $bdayCanChange)
                        <div class="flex items-center gap-2">
                            <input type="date"
                                   wire:model.live="customerBirthdayInput"
                                   aria-label="Geburtsdatum"
                                   class="flex-1 py-2 px-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none">
                            <button type="button"
                                    @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                    wire:click="saveCustomerBirthday"
                                    wire:loading.attr="disabled"
                                    class="py-2 px-4 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black text-xs uppercase tracking-widest cursor-pointer shrink-0">
                                {{ $bdayFormatted ? 'Korrigieren' : 'Speichern' }}
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 leading-snug">
                            @if($bdayFormatted)
                                Tippfehler-Schutz: Du kannst dein hinterlegtes Geburtsdatum bei Bedarf noch einmalig korrigieren.
                            @else
                                Freiwillige Angabe (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;a DSGVO) ausschließlich für deine jährliche Geburtstags-Gutschrift.
                            @endif
                        </p>
                    @endif
                    @if($birthdayFeedbackMessage)
                        <div class="p-2.5 rounded-xl border text-xs font-bold {{ $birthdayFeedbackSuccess ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-800 dark:text-emerald-300' : 'bg-red-500/15 border-red-500/40 text-red-700 dark:text-red-300' }}">
                            {{ $birthdayFeedbackMessage }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Jahrestags-Funke --}}
            <div class="rounded-3xl p-5 bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-gray-800 flex flex-col justify-between gap-4 shadow-sm">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest">
                            🕯️ Manufaktur-Jubiläum
                        </span>
                        <span class="font-mono text-xs font-black text-amber-700 dark:text-[#f5dca0]">+{{ $annivSparks }} ✨ / Jahr</span>
                    </div>
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white">Jährlicher Jubiläums-Funke</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Automatisch +{{ $annivSparks }} ✨ zum Jahrestag deiner 1. Bestellung.</p>
                </div>

                <div>
                    @if($annivDayMonth)
                        <div class="flex items-center justify-between gap-2 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800">
                            <span class="text-xs font-bold text-gray-900 dark:text-white">Jährlich am {{ $annivDayMonth }} (seit {{ $annivMemberSince }})</span>
                            <span class="px-2.5 py-0.5 rounded-lg bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] font-black uppercase shrink-0">
                                {{ $annivAwardedThisYear ? '✓ ' . now()->year . ' erhalten' : '✓ Aktiv' }}
                            </span>
                        </div>
                    @else
                        <div class="flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Aktiviert sich automatisch mit deiner 1. Bestellung</span>
                            <span class="px-2.5 py-0.5 rounded-lg bg-gray-200/80 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[10px] font-black uppercase shrink-0">
                                Nicht aktiv
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Transparenz- & Einlösebedingungen gem. § 6 Abs. 1 Nr. 3 & 4 DDG --}}
        <div class="rounded-2xl px-4 py-3 bg-gray-50/80 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800 text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
            <strong class="text-gray-700 dark:text-gray-300">Hinweise zum Treueprogramm (§ 6 Abs. 1 Nr. 3 &amp; 4 DDG):</strong> Gesammelte Seelenfunken sowie freigeschaltete Rabatt-Gutscheine sind personengebunden, nicht übertragbar und nicht in bar auszahlbar. Pro Bestellung ist jeweils ein prozentualer Rabattcode einlösbar. Im Falle eines Widerrufs oder einer Stornierung werden die für die betreffende Bestellung gutgeschriebenen Bestell-Funken wieder mit dem Punktekonto verrechnet (<a href="{{ route('agb') }}#agb-gamification" target="_blank" class="underline hover:text-[#C5A059]">§ 11 AGB</a>).
        </div>
    </div>

    {{-- =========================================================================
         TAB 2: FUNKI LEVEL 1–10 & HINTERGRÜNDE
         ========================================================================= --}}
    <div x-show="activeFunkiTab === 'levels'" style="display: none;" class="bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-5">
        <div>
            <h2 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Funkis 10 Evolutionsstufen</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Klicke auf einen Kreis für die Großansicht oder wähle eine freigeschaltete Stufe als Profilbild.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3.5">
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
                <div class="rounded-2xl p-3.5 border transition-all flex flex-col justify-between {{ $isEquipped ? 'border-2 border-[#C5A059] bg-amber-500/10 shadow-md' : ($isUnlocked ? 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/70 hover:border-[#C5A059]/60' : 'border-gray-200/60 dark:border-gray-800/50 bg-gray-100/60 dark:bg-gray-900/30 opacity-75') }}">
                    <div class="flex flex-col items-center text-center">
                        <span class="text-[9px] font-black uppercase tracking-widest {{ $isUnlocked ? 'text-amber-700 dark:text-[#f5dca0]' : 'text-gray-400' }} mb-2">
                            Level {{ $lvlNum }} {{ $isUnlocked ? '✓' : '🔒' }}
                        </span>

                        <button type="button"
                                @click="openFunkiZoom(@js($stageZoomPayload))"
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
                        </button>

                        <h3 class="text-xs font-bold text-gray-900 dark:text-white leading-tight">
                            {{ str_replace('Funki • ', '', $stage['name']) }}
                        </h3>
                        <span class="text-[10px] text-amber-700 dark:text-amber-300/90 font-medium mt-0.5 block">
                            {{ $stage['bg_name'] }}
                        </span>
                    </div>

                    <div class="mt-3 pt-2 border-t border-gray-200/60 dark:border-gray-800 text-center">
                        @if($isEquipped)
                            <span class="inline-block w-full py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[9px] uppercase tracking-widest">
                                Aktiv
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

    {{-- =========================================================================
         TAB 3: TITEL & RÄNGE
         ========================================================================= --}}
    <div x-show="activeFunkiTab === 'titles'" style="display: none;" class="bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-6">
        @php
            $isSeelengott = !empty($titlesData['is_seelengott']);
            $diamondsCount = (int) ($titlesData['diamonds_count'] ?? 0);
            $totalTitles = (int) ($titlesData['total_titles'] ?? 5);
            $coreTitles = $titlesData['titles'] ?? [];
            $levelTitles = $titlesData['level_titles'] ?? \App\Services\Gamification\GameConfig::getLevelTitles();
        @endphp

        {{-- Ultimativer Rang: SEELENGOTT --}}
        <div class="rounded-2xl p-4 sm:p-5 border-2 {{ $isSeelengott ? 'border-[#C5A059] bg-gradient-to-r from-amber-500/20 via-[#C5A059]/10 to-amber-500/5' : 'border-dashed border-gray-300 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/50' }} flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3.5 text-center sm:text-left flex-col sm:flex-row">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl shrink-0 {{ $isSeelengott ? 'bg-gradient-to-br from-amber-300 via-[#C5A059] to-amber-600 text-gray-950' : 'bg-gray-200 dark:bg-gray-800 text-gray-400' }}">
                    👑
                </div>
                <div>
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-0.5">
                        <span class="text-[10px] font-mono font-bold text-amber-700 dark:text-[#f5dca0]">
                            {{ $diamondsCount }}/{{ $totalTitles }} Diamant-Titel • Level {{ $funkiLevel }}/10
                        </span>
                    </div>
                    <h2 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Seelengott unter Sterblichen</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Alle 5 Kern-Titel auf Diamant + Level 10.</p>
                </div>
            </div>

            <div class="shrink-0 w-full sm:w-auto">
                @if($activeTitleKey === 'seelengott')
                    <span class="inline-block w-full sm:w-auto px-4 py-2 rounded-xl bg-[#C5A059] text-gray-950 font-black text-xs uppercase tracking-widest text-center">
                        👑 Aktiv
                    </span>
                @elseif($isSeelengott)
                    <button type="button"
                            @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                            wire:click="selectTitle('seelengott')"
                            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black text-xs uppercase tracking-widest cursor-pointer">
                        👑 Tragen
                    </button>
                @else
                    <span class="inline-block w-full sm:w-auto px-3.5 py-2 rounded-xl bg-gray-200 dark:bg-gray-800 text-gray-500 font-bold text-[11px] text-center">
                        🔒 Gesperrt
                    </span>
                @endif
            </div>
        </div>

        {{-- 5 Kern-Titel --}}
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
                        'diamant' => '💎 DIAMANT',
                        'gold'    => '🥇 GOLD',
                        'silber'  => '🥈 SILBER',
                        default   => '🔒 GESPERRT',
                    };
                @endphp
                <div class="rounded-2xl p-4 border {{ $isTitleActive ? 'border-2 border-[#C5A059] bg-amber-500/10' : 'border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/60' }} flex flex-col justify-between gap-3">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                <span>{{ $tInfo['icon'] ?? '🏆' }}</span>
                                <span>{{ $tInfo['name'] }}</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full border text-[9px] font-black uppercase tracking-wider {{ $tierBadgeClass }}">
                                {{ $tierLabel }}
                            </span>
                        </div>

                        <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white">{{ $tInfo['tier_name'] }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $tInfo['description'] }}</p>

                        <div class="mt-3">
                            <div class="flex justify-between text-[10px] font-bold text-gray-500 mb-1">
                                <span>{{ $tier === 'diamant' ? 'Max-Stufe erreicht' : ('Nächste Stufe: ' . $tInfo['next_tier_name']) }}</span>
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
                            <span class="px-3 py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[10px] uppercase tracking-widest">Aktiv</span>
                        @elseif($isTitleUnlocked)
                            <button type="button"
                                    @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                                    wire:click="selectTitle('{{ $tKey }}')"
                                    class="px-3 py-1 rounded-lg bg-amber-500/15 hover:bg-[#C5A059] text-amber-900 dark:text-[#f5dca0] hover:text-gray-950 font-black text-[10px] uppercase tracking-widest transition cursor-pointer">
                                Tragen
                            </button>
                        @else
                            <span class="text-[10px] text-gray-400 font-semibold">Ab {{ number_format($tInfo['next_req'], 0, ',', '.') }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Level-Ränge 1 bis 10 --}}
        <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-serif font-bold text-gray-900 dark:text-white mb-3">Level-Ränge (Stufe 1–10)</h3>
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
                                <span class="inline-block w-full py-1 rounded-lg bg-[#C5A059] text-gray-950 font-black text-[9px] uppercase tracking-widest">Aktiv</span>
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

    {{-- =========================================================================
         TAB 4: HALLE DER HELDEN
         ========================================================================= --}}
    <div x-show="activeFunkiTab === 'hall_of_fame'" style="display: none;" class="bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-6">
        <div class="rounded-2xl p-4 sm:p-5 border border-[#C5A059]/40 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-serif font-bold text-gray-900 dark:text-white">Deine Anzeige in der Rangliste</h2>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Wähle frei zwischen eigenem Spielernamen oder Vornamen + Initial.</p>
                </div>
                <label class="inline-flex items-center gap-2.5 px-3.5 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 cursor-pointer shrink-0">
                    <input type="checkbox" wire:model.live="rankingOptIn" @change="if(window.sfCockpitSound) window.sfCockpitSound('click')" class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500 cursor-pointer">
                    <span class="text-xs font-bold text-gray-900 dark:text-white">In Rangliste anzeigen</span>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start pt-2 border-t border-amber-500/20">
                <div class="md:col-span-5">
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button"
                                @click="if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                wire:click="$set('useRealName', false)"
                                class="py-2.5 px-3 rounded-xl border text-xs font-bold transition cursor-pointer {{ !$useRealName ? 'bg-[#C5A059] text-gray-950 border-[#C5A059]' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                            🎮 Spielername
                        </button>
                        <button type="button"
                                @click="if(window.sfCockpitSound) window.sfCockpitSound('switch')"
                                wire:click="$set('useRealName', true)"
                                class="py-2.5 px-3 rounded-xl border text-xs font-bold transition cursor-pointer {{ $useRealName ? 'bg-[#C5A059] text-gray-950 border-[#C5A059]' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                            👤 {{ auth()->user()->first_name }} {{ mb_substr((string) auth()->user()->last_name, 0, 1) }}.
                        </button>
                    </div>
                </div>

                <div class="md:col-span-4">
                    <input type="text"
                           wire:model="playerName"
                           minlength="3"
                           maxlength="20"
                           placeholder="Spielername (3–20 Zeichen)"
                           class="w-full py-2.5 px-3.5 rounded-xl bg-white dark:bg-gray-900 border {{ $playerNameError ? 'border-red-500' : 'border-gray-200 dark:border-gray-800' }} text-gray-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none">
                    @if($playerNameError)
                        <p class="mt-1 text-[11px] font-bold text-red-600 dark:text-red-400">⚠️ {{ $playerNameError }}</p>
                    @endif
                </div>

                <div class="md:col-span-3">
                    <button type="button"
                            @click="if(window.sfCockpitSound) window.sfCockpitSound('click')"
                            wire:click="saveHallOfFameSettings"
                            wire:loading.attr="disabled"
                            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black text-xs uppercase tracking-widest shadow-sm cursor-pointer">
                        Speichern
                    </button>
                </div>
            </div>

            @if($hofFeedbackMessage)
                <div class="p-3 rounded-xl border text-xs font-bold flex items-center justify-between gap-2 {{ $hofFeedbackSuccess ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-800 dark:text-emerald-300' : 'bg-red-500/15 border-red-500/40 text-red-700 dark:text-red-300' }}">
                    <span>{{ $hofFeedbackMessage }}</span>
                    <button type="button" wire:click="$set('hofFeedbackMessage', null)" class="text-current opacity-70 hover:opacity-100 px-1.5 font-black cursor-pointer">&times;</button>
                </div>
            @endif
        </div>

        {{-- Rangliste --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white">
                    Top Sammler ({{ $hallOfFameTotal }})
                </h3>
                <span class="text-[11px] text-gray-500">Seite {{ $hallOfFamePage }} / {{ $hallOfFameLastPage }}</span>
            </div>

            @if(count($hallOfFameLeaders) > 0)
                <div class="space-y-2.5">
                    @foreach($hallOfFameLeaders as $leader)
                        @php
                            $pos = (int) $leader['rank_position'];
                            $posBadge = match($pos) { 1 => '🥇 #1', 2 => '🥈 #2', 3 => '🥉 #3', default => '#' . $pos };
                            $isMe = !empty($leader['is_current_user']);
                            $leaderVideoFilter = $leader['video_filter_css'] ?? '';
                            $leaderVideoTransform = $leader['video_transform_css'] ?? '';
                        @endphp
                        <div class="p-3 sm:p-4 rounded-2xl border {{ $isMe ? 'border-2 border-[#C5A059] bg-amber-500/10' : 'border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/60 hover:border-[#C5A059]/50' }} flex items-center justify-between gap-3 transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-9 sm:w-11 text-center font-mono font-black text-xs sm:text-sm {{ $pos <= 3 ? 'text-amber-600 dark:text-[#f5dca0]' : 'text-gray-400' }} shrink-0">
                                    {{ $posBadge }}
                                </span>

                                <button type="button"
                                        @click="if(window.sfCockpitSound) window.sfCockpitSound('click'); heroProfileModal = @js($leader)"
                                        class="relative w-12 h-12 sm:w-14 sm:h-14 rounded-full shrink-0 group cursor-pointer focus:outline-none">
                                    <div style="{{ $leader['circle_css'] }}"
                                         class="relative w-full h-full rounded-full border-2 border-[#C5A059] overflow-hidden flex items-center justify-center shadow-sm">
                                        @if(!empty($leader['video_url']))
                                            <video src="{{ $leader['video_url'] }}"
                                                   style="{{ $leaderVideoFilter ? 'filter: ' . $leaderVideoFilter . ';' : '' }} {{ $leaderVideoTransform ? 'transform: ' . $leaderVideoTransform . ';' : '' }}"
                                                   autoplay loop muted playsinline
                                                   class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                                        @endif
                                        <img src="{{ $leader['custom_photo_url'] ?: $leader['image_url'] }}"
                                             alt="{{ $leader['display_name'] }}"
                                             class="relative z-10 {{ !empty($leader['custom_photo_url']) ? 'w-full h-full object-cover' : 'w-full h-full object-contain p-1.5' }}">
                                    </div>
                                    <span class="absolute -bottom-1 -right-1 z-20 px-1.5 py-0.5 rounded-full bg-[#C5A059] text-gray-950 text-[8px] font-black">
                                        Lvl. {{ $leader['level'] }}
                                    </span>
                                </button>

                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <button type="button"
                                                @click="if(window.sfCockpitSound) window.sfCockpitSound('click'); heroProfileModal = @js($leader)"
                                                class="font-serif font-bold text-sm sm:text-base text-gray-900 dark:text-white hover:text-[#C5A059] truncate cursor-pointer">
                                            {{ $leader['display_name'] }}
                                        </button>
                                        @if($isMe)
                                            <span class="px-2 py-0.5 rounded-full bg-[#C5A059] text-gray-950 text-[9px] font-black uppercase">Du</span>
                                        @endif
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider border bg-amber-500/15 border-[#C5A059]/40 text-amber-800 dark:text-[#f5dca0]">
                                            {{ !empty($leader['is_seelengott']) ? '👑' : '🏆' }} {{ $leader['rank_name'] }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-gray-500 block mt-0.5 truncate">
                                        Level {{ $leader['level'] }} • {{ $leader['bg_name'] }}
                                    </span>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="font-mono font-black text-xs sm:text-base text-amber-700 dark:text-[#f5dca0] block">
                                    {{ number_format($leader['funken_total_earned'], 0, ',', '.') }} ✨
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($hallOfFameLastPage > 1)
                    <div class="pt-3 flex items-center justify-between gap-2 border-t border-gray-100 dark:border-gray-800">
                        <button type="button"
                                wire:click="prevHallOfFamePage"
                                @if($hallOfFamePage <= 1) disabled @endif
                                class="px-3.5 py-1.5 rounded-xl border text-xs font-bold {{ $hallOfFamePage <= 1 ? 'opacity-40 cursor-not-allowed' : 'border-[#C5A059]/50 cursor-pointer' }}">
                            ← Vorherige
                        </button>
                        <button type="button"
                                wire:click="nextHallOfFamePage"
                                @if($hallOfFamePage >= $hallOfFameLastPage) disabled @endif
                                class="px-3.5 py-1.5 rounded-xl border text-xs font-bold {{ $hallOfFamePage >= $hallOfFameLastPage ? 'opacity-40 cursor-not-allowed' : 'border-[#C5A059]/50 cursor-pointer' }}">
                            Nächste →
                        </button>
                    </div>
                @endif
            @else
                <p class="text-xs text-gray-500 bg-gray-50 dark:bg-gray-900/50 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 text-center">
                    Noch kein Sammler eingetragen. Setze oben den Haken und sei der Erste! ✨
                </p>
            @endif
        </div>
    </div>

    {{-- =========================================================================
         LIGHTBOX 1: GROSSANSICHT FÜR FUNKI & HINTERGRUND
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
                <div class="relative z-10 w-full max-w-lg rounded-[2.5rem] bg-white dark:bg-[#0b0e17] border-2 border-[#C5A059] shadow-[0_0_80px_rgba(197,160,89,0.4)] p-6 sm:p-9 text-center overflow-hidden">
                    <button type="button"
                            @click="showFunkiZoomModal = false; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                            class="absolute top-4 right-4 z-30 p-2.5 rounded-xl bg-gray-100 dark:bg-gray-900 hover:bg-red-500 text-gray-500 hover:text-white transition-colors cursor-pointer">
                        <x-heroicon-m-x-mark class="w-5 h-5" />
                    </button>

                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-amber-500/15 border border-[#C5A059]/50 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest mb-4"
                          x-text="'STUFE ' + zoomStageData.level + ' • GROSSANSICHT'"></span>

                    <div class="relative w-60 h-60 sm:w-80 sm:h-80 mx-auto my-3 flex items-center justify-center select-none">
                        <div :style="zoomStageData.circle_css"
                             class="relative w-full h-full rounded-full border-4 border-[#C5A059] shadow-[0_0_55px_rgba(197,160,89,0.55)] overflow-hidden flex items-center justify-center bg-gray-950">
                            <template x-if="zoomStageData.video_url">
                                <video :src="zoomStageData.video_url"
                                       :style="(zoomStageData.video_filter_css ? 'filter:' + zoomStageData.video_filter_css + ';' : '') + (zoomStageData.video_transform_css ? 'transform:' + zoomStageData.video_transform_css + ';' : '')"
                                       autoplay loop muted playsinline
                                       class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                            </template>
                            <img :src="zoomStageData.image_url"
                                 :alt="zoomStageData.name"
                                 class="relative z-10 w-full h-full object-contain p-4 sm:p-5 drop-shadow-[0_14px_28px_rgba(0,0,0,0.7)]">
                        </div>
                    </div>

                    <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white mt-4" x-text="zoomStageData.name"></h3>
                    <p class="text-xs font-bold text-amber-700 dark:text-[#f5dca0] mt-1" x-text="'Hintergrund: ' + zoomStageData.bg_name"></p>

                    <button type="button"
                            @click="showFunkiZoomModal = false; if(window.sfCockpitSound) window.sfCockpitSound('back')"
                            class="mt-6 w-full py-3 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 font-black text-xs uppercase tracking-widest shadow-md cursor-pointer">
                        Schließen
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- =========================================================================
         LIGHTBOX 2: HELDEN-PROFIL-MODAL
         ========================================================================= --}}
    <template x-teleport="body">
        <div x-show="heroProfileModal !== null"
             style="display: none;"
             @keydown.escape.window="heroProfileModal = null"
             class="fixed inset-0 z-[9600] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/80 backdrop-blur-md"
                 @click="heroProfileModal = null"
                 x-transition.opacity></div>

            <template x-if="heroProfileModal">
                <div class="relative z-10 w-full max-w-md rounded-[2rem] bg-white dark:bg-[#0f131d] border-2 border-[#C5A059] shadow-2xl p-6 sm:p-8 text-center">
                    <button type="button"
                            @click="heroProfileModal = null"
                            class="absolute top-4 right-4 z-20 p-2 rounded-xl bg-gray-100 dark:bg-gray-900 hover:bg-red-500 text-gray-500 hover:text-white transition-colors cursor-pointer">
                        <x-heroicon-m-x-mark class="w-5 h-5" />
                    </button>

                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 border border-[#C5A059]/40 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest mb-4"
                          x-text="'Rang #' + (heroProfileModal.rank_position || 1)"></span>

                    <div class="relative w-40 h-40 sm:w-48 sm:h-48 mx-auto mb-5 flex items-center justify-center">
                        <div :style="heroProfileModal.circle_css"
                             class="relative w-full h-full rounded-full border-2 border-[#C5A059] shadow-[0_0_40px_rgba(197,160,89,0.45)] overflow-hidden flex items-center justify-center bg-gray-950">
                            <img :src="heroProfileModal.custom_photo_url || heroProfileModal.image_url"
                                 :alt="heroProfileModal.display_name"
                                 :class="heroProfileModal.custom_photo_url ? 'w-full h-full object-cover' : 'w-full h-full object-contain p-4'"
                                 class="relative z-10">
                        </div>
                    </div>

                    <h4 class="text-2xl font-serif font-bold text-gray-900 dark:text-white" x-text="heroProfileModal.display_name"></h4>
                    <p class="text-xs font-bold text-amber-700 dark:text-[#f5dca0] mt-1" x-text="heroProfileModal.rank_name"></p>
                    <p class="text-xs text-gray-500 mt-1" x-text="'Level ' + heroProfileModal.level + ' • ' + (heroProfileModal.funken_total_earned || 0).toLocaleString('de-DE') + ' ✨'"></p>

                    <button type="button"
                            @click="heroProfileModal = null"
                            class="mt-5 w-full py-2.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-black uppercase tracking-widest cursor-pointer">
                        Schließen
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- =========================================================================
         MODAL: MAGIE AKTIVIEREN (FALLS NOCH NICHT AKTIV)
         ========================================================================= --}}
    <template x-teleport="body">
        <div x-show="showActivateMagicModal"
             style="display: none;"
             class="fixed inset-0 z-[9400] flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
            <div class="fixed inset-0 bg-black/70 dark:bg-black/85 backdrop-blur-md"
                 @click="showActivateMagicModal = false"
                 x-transition.opacity></div>

            <div x-data="{ agreedToTerms: false }"
                 class="relative z-10 w-full max-w-xl p-6 sm:p-8 rounded-3xl bg-white dark:bg-gray-900 border border-[#C5A059]/40 shadow-2xl text-center space-y-4">
                <h3 class="text-2xl font-serif font-bold text-gray-900 dark:text-white">Seelenfunken-Magie aktivieren</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    Sammle bei jedem Einkauf sowie auf unseren Shop-Seiten Funken für bis zu 15 % Rabatt.
                </p>
                <label class="flex items-start gap-2.5 text-left cursor-pointer">
                    <input type="checkbox" x-model="agreedToTerms" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    <span class="text-xs text-gray-600 dark:text-gray-400">
                        Ich stimme den <a href="{{ route('agb') }}#agb-gamification" target="_blank" class="text-amber-700 dark:text-primary underline">Teilnahmebedingungen</a> und <a href="{{ route('datenschutz') }}#datenschutz-gamification" target="_blank" class="text-amber-700 dark:text-primary underline">Datenschutzhinweisen</a> zu.
                    </span>
                </label>
                <button type="button"
                        :disabled="!agreedToTerms"
                        @click="$wire.activateSeelenfunke(); showActivateMagicModal = false;"
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 disabled:opacity-40 text-gray-950 font-black text-xs uppercase tracking-widest cursor-pointer">
                    ✨ Jetzt aktivieren
                </button>
            </div>
        </div>
    </template>
</div>
