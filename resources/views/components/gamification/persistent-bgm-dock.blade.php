@php
    $bgmCustomer = auth()->guard('customer')->user();
    $bgmInitialVolume = 35;
    $bgmInitialMuted = false;
    $bgmInitialKeepPlaying = false;

    if ($bgmCustomer) {
        try {
            $bgmProfile = \App\Models\Customer\CustomerGamification::where('customer_id', $bgmCustomer->id)->first();
            if ($bgmProfile) {
                $bgmAudioSettings = app(\App\Services\Gamification\GamificationService::class)->getAudioSettings($bgmProfile);
                $bgmInitialVolume = (int) ($bgmAudioSettings['volume'] ?? 35);
                $bgmInitialMuted = (bool) ($bgmAudioSettings['muted'] ?? false);
                $bgmInitialKeepPlaying = (bool) ($bgmAudioSettings['keep_playing'] ?? false);
            }
        } catch (\Throwable $e) {
            // Fallback auf Standardwerte
        }
    }
@endphp

@if($bgmCustomer)
    @persist('sf-funki-bgm-persistent-container')
    <div id="sf-funki-bgm-persistent-root"
         x-data="{
            volume: {{ (int) $bgmInitialVolume }},
            muted: {{ $bgmInitialMuted ? 'true' : 'false' }},
            keepPlaying: {{ $bgmInitialKeepPlaying ? 'true' : 'false' }},
            modalOpen: false,
            isPlaying: false,
            dockModalOpen: false,
            expanded: false,
            bottomOffsetPx: 212,
            init() {
                const ctrl = window.SfFunkiBgm;
                if (ctrl) {
                    ctrl.initFromServer(this.volume, this.muted, this.keepPlaying);
                    this.volume = ctrl.volume;
                    this.muted = ctrl.muted;
                    this.keepPlaying = ctrl.keepPlaying;
                    this.modalOpen = ctrl.modalOpen;
                    this.isPlaying = ctrl.isPlaying;
                }

                window.addEventListener('sf-funki-bgm-state', (e) => {
                    if (!e.detail) return;
                    this.volume = e.detail.volume;
                    this.muted = e.detail.muted;
                    this.keepPlaying = e.detail.keepPlaying;
                    this.modalOpen = e.detail.modalOpen;
                    this.isPlaying = e.detail.isPlaying;
                });

                const updateDockPosition = () => {
                    const dock = document.getElementById('frontend-action-dock');
                    if (dock) {
                        this.dockModalOpen = dock.classList.contains('sf-dock-modal-open');
                        const rect = dock.getBoundingClientRect();
                        if (rect.height > 0 && rect.top > 0 && rect.top <= window.innerHeight) {
                            const gap = window.innerWidth < 640 ? 8 : 10;
                            this.bottomOffsetPx = Math.max(72, Math.round(window.innerHeight - rect.top + gap));
                            return;
                        }
                    } else {
                        this.dockModalOpen = false;
                    }
                    // Fallback (z.B. im Kunden-Dashboard über der mobilen Bottom-Navigation)
                    this.bottomOffsetPx = window.innerWidth < 1024 ? 84 : 24;
                };

                updateDockPosition();
                setTimeout(updateDockPosition, 150);
                setTimeout(updateDockPosition, 450);

                window.addEventListener('scroll', updateDockPosition, { passive: true });
                window.addEventListener('resize', updateDockPosition, { passive: true });
                window.addEventListener('load', updateDockPosition, { passive: true });
                document.addEventListener('livewire:navigated', () => {
                    updateDockPosition();
                    setTimeout(updateDockPosition, 200);
                });

                const bindDockObservers = () => {
                    const dock = document.getElementById('frontend-action-dock');
                    if (!dock || dock.dataset.sfBgmObserved === '1') return;
                    dock.dataset.sfBgmObserved = '1';
                    if (typeof ResizeObserver !== 'undefined') {
                        const ro = new ResizeObserver(updateDockPosition);
                        ro.observe(dock);
                    }
                    if (typeof MutationObserver !== 'undefined') {
                        const mo = new MutationObserver(updateDockPosition);
                        mo.observe(dock, { attributes: true, childList: true, subtree: true });
                    }
                };
                bindDockObservers();
                document.addEventListener('livewire:navigated', bindDockObservers);
            },
            toggleMute() {
                if (window.SfFunkiBgm) {
                    window.SfFunkiBgm.setMuted(!this.muted, true);
                }
            },
            onVolumeInput(val) {
                if (window.SfFunkiBgm) {
                    window.SfFunkiBgm.setVolume(parseInt(val, 10), false);
                }
            },
            onVolumeCommit(val) {
                if (window.SfFunkiBgm) {
                    window.SfFunkiBgm.setVolume(parseInt(val, 10), true);
                }
            },
            toggleKeepPlaying() {
                if (window.SfFunkiBgm) {
                    window.SfFunkiBgm.setKeepPlaying(!this.keepPlaying, true);
                }
            }
         }"
         wire:ignore>

        {{-- Persistentes Audio-Element im eigenen Container --}}
        <audio id="bgm_funki_game"
               src="{{ asset('shop/gamification/audio/bgm_funki_game.mp3') }}"
               loop
               preload="auto"
               class="hidden"></audio>

        {{-- Schwebendes Audio-Dock rechts direkt über dem "Nach oben scrollen" Button (Standard: nur Lautsprecher-Icon, beim Drücken größer) --}}
        <div x-show="keepPlaying && !modalOpen && !dockModalOpen"
             x-cloak
             @click.outside="expanded = false"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             :style="'bottom: ' + bottomOffsetPx + 'px; right: max(0.75rem, env(safe-area-inset-right, 0px));'"
             class="fixed sm:!right-5 z-[9985] select-none transition-[bottom] duration-200 ease-out"
             role="region"
             aria-label="Funki Musik-Steuerung">

            {{-- STANDARD (EINGEKLAPPT): Nur das runde Lautsprecher-Icon --}}
            <button x-show="!expanded"
                    type="button"
                    @click="expanded = true"
                    title="Funki Musik öffnen"
                    aria-label="Funki Musik öffnen"
                    class="w-[2.625rem] h-[2.625rem] sm:w-10 sm:h-10 rounded-full bg-white/96 dark:bg-gray-900/95 backdrop-blur-xl border border-[#C5A059]/50 dark:border-[#C5A059]/45 text-amber-700 dark:text-[#f5dca0] shadow-[0_6px_18px_rgba(0,0,0,0.14),0_0_0_1px_rgba(255,255,255,0.6)] dark:shadow-[0_8px_24px_rgba(0,0,0,0.45)] flex items-center justify-center cursor-pointer transition-all duration-300 hover:scale-105 hover:border-[#C5A059]">
                <template x-if="muted || volume === 0">
                    <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                    </svg>
                </template>
                <template x-if="!muted && volume > 0">
                    <svg class="w-4 h-4 text-amber-600 dark:text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                    </svg>
                </template>
            </button>

            {{-- GEÖFFNET (ETWAS GRÖSSER): Kompaktes Steuer-Panel beim Draufdrücken --}}
            <div x-show="expanded"
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-90 origin-bottom-right"
                 x-transition:enter-end="opacity-100 scale-100 origin-bottom-right"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 origin-bottom-right"
                 x-transition:leave-end="opacity-0 scale-90 origin-bottom-right"
                 class="bg-white/96 dark:bg-gray-900/95 backdrop-blur-xl border border-[#C5A059]/45 dark:border-[#C5A059]/40 rounded-2xl shadow-[0_10px_28px_rgba(0,0,0,0.16),0_0_0_1px_rgba(255,255,255,0.6)] dark:shadow-[0_12px_35px_rgba(0,0,0,0.45),0_0_20px_rgba(245,158,11,0.18)] p-2.5 sm:p-3 flex flex-col items-center gap-2 text-gray-900 dark:text-white w-[195px] sm:w-[215px] transition-all duration-300">

                {{-- Obere Zeile: Lautsprecher (Mute) + Status + Einklappen --}}
                <div class="w-full flex items-center justify-between gap-2">
                    <button type="button"
                            @click="toggleMute()"
                            :title="muted ? 'Ton einschalten' : 'Stummschalten'"
                            class="w-8 h-8 rounded-xl flex items-center justify-center transition-all shrink-0 border cursor-pointer"
                            :class="muted || volume === 0
                                ? 'bg-red-500/10 dark:bg-red-500/15 border-red-500/30 dark:border-red-500/40 text-red-600 dark:text-red-300 hover:bg-red-500/20'
                                : 'bg-amber-500/15 dark:bg-amber-500/20 border-[#C5A059]/50 dark:border-amber-400/50 text-amber-700 dark:text-amber-300 hover:bg-amber-500/25 shadow-[0_0_12px_rgba(197,160,89,0.2)]'">
                        <template x-if="muted || volume === 0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                            </svg>
                        </template>
                        <template x-if="!muted && volume > 0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                            </svg>
                        </template>
                    </button>

                    <div class="flex flex-col min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-800 dark:text-[#f5dca0] truncate">Funki Musik</span>
                            <span class="text-[10px] font-mono font-bold text-gray-700 dark:text-gray-300 tabular-nums" x-text="(muted ? 0 : volume) + '%'"></span>
                        </div>
                        <span class="text-[9px] text-gray-500 dark:text-gray-400 truncate" x-text="muted ? 'Stummgeschaltet' : 'Spielt konstant weiter'"></span>
                    </div>

                    <button type="button"
                            @click="expanded = false"
                            title="Verkleinern"
                            aria-label="Verkleinern"
                            class="w-6 h-6 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-white flex items-center justify-center cursor-pointer shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Lautstärke-Regler --}}
                <div class="w-full flex items-center gap-2 px-0.5">
                    <input type="range"
                           min="0"
                           max="100"
                           step="1"
                           :value="muted ? 0 : volume"
                           @input="onVolumeInput($event.target.value)"
                           @change="onVolumeCommit($event.target.value)"
                           aria-label="Funki Musik Lautstärke"
                           class="w-full h-1.5 bg-gray-200 dark:bg-gray-800 rounded-lg appearance-none cursor-pointer accent-[#C5A059]">
                </div>

                {{-- Checkbox: Musik laufen lassen --}}
                <label class="w-full flex items-center justify-start gap-2 pt-1.5 border-t border-gray-200 dark:border-white/10 cursor-pointer group"
                       title="Musik beim Schließen & Seitenwechsel konstant weiterlaufen lassen">
                    <input type="checkbox"
                           :checked="keepPlaying"
                           @change="toggleKeepPlaying()"
                           class="w-3.5 h-3.5 rounded border-gray-300 dark:border-amber-400/50 bg-white dark:bg-gray-900 text-[#C5A059] focus:ring-[#C5A059]/40 cursor-pointer accent-[#C5A059]">
                    <span class="text-[10px] font-bold text-gray-700 dark:text-gray-300 group-hover:text-amber-700 dark:group-hover:text-[#f5dca0] transition-colors leading-tight">
                        Musik laufen lassen
                    </span>
                </label>
            </div>
        </div>
    </div>

    <script>
        if (!window.SfFunkiBgm) {
            window.SfFunkiBgm = {
                volume: {{ (int) $bgmInitialVolume }},
                muted: {{ $bgmInitialMuted ? 'true' : 'false' }},
                keepPlaying: {{ $bgmInitialKeepPlaying ? 'true' : 'false' }},
                modalOpen: false,
                isPlaying: false,
                fadeRafId: null,
                fadeDurationMs: 850,
                maxGain: 0.65,
                saveTimeout: null,
                initialized: false,

                getAudioEl() {
                    return document.getElementById('bgm_funki_game');
                },

                targetGain() {
                    if (this.muted || this.volume <= 0) {
                        return 0;
                    }
                    return Math.max(0, Math.min(1, (this.volume / 100) * this.maxGain));
                },

                initFromServer(serverVol, serverMuted, serverKeepPlaying) {
                    if (!this.initialized) {
                        this.initialized = true;
                        this.volume = typeof serverVol === 'number' ? Math.max(0, Math.min(100, serverVol)) : 35;
                        this.muted = !!serverMuted;
                        this.keepPlaying = !!serverKeepPlaying;

                        try {
                            const lsVol = localStorage.getItem('sf_funki_audio_volume');
                            const lsMuted = localStorage.getItem('sf_funki_audio_muted');
                            const lsKeep = localStorage.getItem('sf_funki_audio_keep_playing');
                            if (lsVol !== null && !Number.isNaN(parseInt(lsVol, 10))) {
                                this.volume = Math.max(0, Math.min(100, parseInt(lsVol, 10)));
                            }
                            if (lsMuted !== null) {
                                this.muted = lsMuted === '1' || lsMuted === 'true';
                            }
                            if (lsKeep !== null) {
                                this.keepPlaying = lsKeep === '1' || lsKeep === 'true';
                            }
                        } catch (e) {}

                        this.bindAudioEvents();

                        // Falls "Musik laufen lassen" aktiv ist, beim Seitenwechsel ohne Lautstärke-Einbruch konstant fortsetzen
                        if (this.keepPlaying && !this.muted && this.volume > 0) {
                            this.resumePersistentPlayback();
                        }
                    } else {
                        this.bindAudioEvents();
                        const audio = this.getAudioEl();
                        if (this.keepPlaying && !this.muted && this.volume > 0 && audio && audio.paused) {
                            this.resumePersistentPlayback();
                        }
                    }
                    this.broadcastState();
                },

                saveCurrentPlaybackPosition(audio) {
                    if (!audio) return;
                    try {
                        sessionStorage.setItem('sf_funki_bgm_time', String(audio.currentTime));
                        sessionStorage.setItem('sf_funki_bgm_saved_at', String(Date.now()));
                    } catch (e) {}
                },

                bindAudioEvents() {
                    const audio = this.getAudioEl();
                    if (!audio || audio.dataset.sfBound === '1') return;
                    audio.dataset.sfBound = '1';

                    audio.addEventListener('timeupdate', () => {
                        if (!audio.paused) {
                            this.saveCurrentPlaybackPosition(audio);
                        }
                    });

                    audio.addEventListener('play', () => {
                        this.isPlaying = true;
                        this.broadcastState();
                    });

                    audio.addEventListener('pause', () => {
                        this.isPlaying = false;
                        this.broadcastState();
                    });

                    const persistOnLeave = () => {
                        const el = this.getAudioEl();
                        if (el && !el.paused) {
                            this.saveCurrentPlaybackPosition(el);
                        }
                    };

                    window.addEventListener('pagehide', persistOnLeave);
                    window.addEventListener('beforeunload', persistOnLeave);
                    document.addEventListener('livewire:navigating', () => {
                        // Bei Seitenwechsel ist das Modal auf der neuen Seite geschlossen, Musik bleibt aber bei keepPlaying konstant an
                        this.modalOpen = false;
                        persistOnLeave();
                    });
                    document.addEventListener('livewire:navigated', () => {
                        const el = this.getAudioEl();
                        if (this.keepPlaying && !this.muted && this.volume > 0 && el) {
                            el.volume = this.targetGain();
                            if (el.paused) {
                                this.resumePersistentPlayback();
                            }
                        }
                        this.broadcastState();
                    });
                },

                restoreSavedTime(audio, compensateTransit = false) {
                    if (!audio) return;
                    try {
                        let savedTime = parseFloat(sessionStorage.getItem('sf_funki_bgm_time') || '0');
                        if (compensateTransit && savedTime > 0) {
                            const savedAt = parseInt(sessionStorage.getItem('sf_funki_bgm_saved_at') || '0', 10);
                            if (savedAt > 0) {
                                const deltaSec = Math.max(0, Math.min(2.5, (Date.now() - savedAt) / 1000));
                                savedTime += deltaSec;
                            }
                        }
                        if (savedTime > 0 && audio.currentTime === 0) {
                            const applyTime = () => {
                                try {
                                    if (audio.duration && audio.duration > 0) {
                                        audio.currentTime = savedTime % audio.duration;
                                    } else {
                                        audio.currentTime = savedTime;
                                    }
                                } catch (e) {}
                            };
                            if (audio.readyState >= 1) {
                                applyTime();
                            } else {
                                audio.addEventListener('loadedmetadata', applyTime, { once: true });
                            }
                        }
                    } catch (e) {}
                },

                /**
                 * Konstantes Weiterlaufen bei Seitenwechseln (KEIN Fade-Down/Fade-Up auf 0, sondern konstante Ziel-Lautstärke!)
                 */
                resumePersistentPlayback() {
                    this.cancelFade();
                    const audio = this.getAudioEl();
                    if (!audio) return;

                    this.bindAudioEvents();
                    if (this.muted || this.volume <= 0) {
                        audio.volume = 0;
                        this.broadcastState();
                        return;
                    }

                    const target = this.targetGain();
                    audio.volume = target;

                    if (audio.paused) {
                        this.restoreSavedTime(audio, true);
                        const playPromise = audio.play();
                        if (playPromise && typeof playPromise.catch === 'function') {
                            playPromise.catch(() => {
                                const unlockHandler = () => {
                                    if ((this.modalOpen || this.keepPlaying) && !this.muted && this.volume > 0) {
                                        audio.volume = this.targetGain();
                                        audio.play().catch(() => {});
                                    }
                                    window.removeEventListener('pointerdown', unlockHandler);
                                    window.removeEventListener('keydown', unlockHandler);
                                };
                                window.addEventListener('pointerdown', unlockHandler, { once: true });
                                window.addEventListener('keydown', unlockHandler, { once: true });
                            });
                        }
                    }
                    this.broadcastState();
                },

                cancelFade() {
                    if (this.fadeRafId) {
                        cancelAnimationFrame(this.fadeRafId);
                        this.fadeRafId = null;
                    }
                },

                /**
                 * Geschmeidiges Einblenden (Fade-In) beim Öffnen des Modals.
                 * Läuft die Musik bereits konstant (wegen "Musik laufen lassen"), bleibt sie konstant ohne Einbruch!
                 */
                fadeIn(durationMs = 850, allowAutoplayFallback = false) {
                    this.cancelFade();
                    const audio = this.getAudioEl();
                    if (!audio) return;

                    this.bindAudioEvents();

                    if (this.muted || this.volume <= 0) {
                        audio.volume = 0;
                        this.broadcastState();
                        return;
                    }

                    const target = this.targetGain();
                    const wasPaused = audio.paused;

                    // Wenn die Musik ohnehin schon auf Ziel-Lautstärke läuft, absolut konstant lassen!
                    if (!wasPaused && Math.abs(audio.volume - target) < 0.01) {
                        audio.volume = target;
                        this.broadcastState();
                        return;
                    }

                    if (wasPaused) {
                        this.restoreSavedTime(audio, false);
                        audio.volume = 0.001;
                        const playPromise = audio.play();
                        if (playPromise && typeof playPromise.catch === 'function') {
                            playPromise.catch(() => {
                                if (allowAutoplayFallback) {
                                    const unlockHandler = () => {
                                        if ((this.modalOpen || this.keepPlaying) && !this.muted && this.volume > 0) {
                                            this.fadeIn(durationMs, false);
                                        }
                                        window.removeEventListener('pointerdown', unlockHandler);
                                        window.removeEventListener('keydown', unlockHandler);
                                    };
                                    window.addEventListener('pointerdown', unlockHandler, { once: true });
                                    window.addEventListener('keydown', unlockHandler, { once: true });
                                }
                            });
                        }
                    }

                    const startVol = wasPaused ? 0 : audio.volume;
                    const diff = target - startVol;
                    if (Math.abs(diff) < 0.005) {
                        audio.volume = target;
                        this.broadcastState();
                        return;
                    }

                    const startTime = performance.now();
                    const step = (now) => {
                        const elapsed = now - startTime;
                        const progress = Math.min(1, elapsed / Math.max(100, durationMs));
                        const eased = progress < 0.5
                            ? 4 * progress * progress * progress
                            : 1 - Math.pow(-2 * progress + 2, 3) / 2;

                        audio.volume = Math.max(0, Math.min(1, startVol + diff * eased));

                        if (progress < 1) {
                            this.fadeRafId = requestAnimationFrame(step);
                        } else {
                            audio.volume = target;
                            this.fadeRafId = null;
                            this.broadcastState();
                        }
                    };

                    this.fadeRafId = requestAnimationFrame(step);
                },

                /**
                 * Geschmeidiges Ausblenden (Fade-Out) bis 0 und anschließendes Pausieren.
                 */
                fadeOut(durationMs = 850, onComplete = null) {
                    this.cancelFade();
                    const audio = this.getAudioEl();
                    if (!audio) {
                        if (typeof onComplete === 'function') onComplete();
                        return;
                    }

                    if (audio.paused || audio.volume <= 0.002) {
                        audio.volume = 0;
                        audio.pause();
                        this.broadcastState();
                        if (typeof onComplete === 'function') onComplete();
                        return;
                    }

                    const startVol = audio.volume;
                    const startTime = performance.now();

                    const step = (now) => {
                        const elapsed = now - startTime;
                        const progress = Math.min(1, elapsed / Math.max(100, durationMs));
                        const eased = 1 - Math.pow(1 - progress, 3);
                        audio.volume = Math.max(0, startVol * (1 - eased));

                        if (progress < 1) {
                            this.fadeRafId = requestAnimationFrame(step);
                        } else {
                            audio.volume = 0;
                            this.saveCurrentPlaybackPosition(audio);
                            audio.pause();
                            this.fadeRafId = null;
                            this.broadcastState();
                            if (typeof onComplete === 'function') onComplete();
                        }
                    };

                    this.fadeRafId = requestAnimationFrame(step);
                },

                onModalOpen() {
                    this.modalOpen = true;
                    this.broadcastState();
                    if (!this.muted && this.volume > 0) {
                        this.fadeIn(this.fadeDurationMs, false);
                    }
                },

                onModalClose() {
                    this.modalOpen = false;
                    this.broadcastState();
                    // Wenn "Musik laufen lassen" aktiv ist, läuft sie konstant weiter; sonst geschmeidig ausblenden
                    if (!this.keepPlaying) {
                        this.fadeOut(this.fadeDurationMs);
                    }
                },

                setVolume(newVol, persist = true) {
                    const clamped = Math.max(0, Math.min(100, Number.isNaN(newVol) ? 35 : newVol));
                    this.volume = clamped;
                    if (clamped === 0) {
                        this.muted = true;
                    } else if (this.muted) {
                        this.muted = false;
                    }

                    this.cancelFade();
                    const audio = this.getAudioEl();
                    if (audio) {
                        if (this.muted || this.volume <= 0) {
                            audio.volume = 0;
                        } else {
                            audio.volume = this.targetGain();
                            if (audio.paused && (this.modalOpen || this.keepPlaying)) {
                                audio.play().catch(() => {});
                            }
                        }
                    }

                    this.saveToLocalStorage();
                    this.broadcastState();
                    if (persist) {
                        this.persistToServer();
                    }
                },

                setMuted(newMuted, persist = true) {
                    this.muted = !!newMuted;
                    if (!this.muted && this.volume === 0) {
                        this.volume = 35;
                    }

                    if (this.muted) {
                        this.fadeOut(450);
                    } else if (this.modalOpen || this.keepPlaying) {
                        this.fadeIn(550);
                    }

                    this.saveToLocalStorage();
                    this.broadcastState();
                    if (persist) {
                        this.persistToServer();
                    }
                },

                setKeepPlaying(newKeepPlaying, persist = true) {
                    this.keepPlaying = !!newKeepPlaying;

                    if (this.keepPlaying) {
                        if (!this.muted && this.volume > 0) {
                            this.fadeIn(this.fadeDurationMs);
                        }
                    } else if (!this.modalOpen) {
                        this.fadeOut(this.fadeDurationMs);
                    }

                    this.saveToLocalStorage();
                    this.broadcastState();
                    if (persist) {
                        this.persistToServer();
                    }
                },

                saveToLocalStorage() {
                    try {
                        localStorage.setItem('sf_funki_audio_volume', String(this.volume));
                        localStorage.setItem('sf_funki_audio_muted', this.muted ? '1' : '0');
                        localStorage.setItem('sf_funki_audio_keep_playing', this.keepPlaying ? '1' : '0');
                    } catch (e) {}
                },

                persistToServer() {
                    if (this.saveTimeout) {
                        clearTimeout(this.saveTimeout);
                    }
                    this.saveTimeout = setTimeout(() => {
                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
                        fetch('/customer/gamification/audio-settings', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                volume: this.volume,
                                muted: this.muted,
                                keep_playing: this.keepPlaying
                            })
                        }).catch(() => {});
                    }, 220);
                },

                broadcastState() {
                    window.dispatchEvent(new CustomEvent('sf-funki-bgm-state', {
                        detail: {
                            volume: this.volume,
                            muted: this.muted,
                            keepPlaying: this.keepPlaying,
                            modalOpen: this.modalOpen,
                            isPlaying: this.isPlaying
                        }
                    }));
                }
            };
        }
    </script>
    @endpersist
@endif
