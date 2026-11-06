{{-- Global Persistent Audio Engine & Floating Corner Mini-Player --}}
<div id="funkenwerk-global-music-shell"
     x-data="funkenwerkGlobalMusicShell()"
     x-init="initShell()"
     x-on:livewire:navigating.window="onNavigating()"
     x-on:livewire:navigated.window="onNavigated()"
     x-on:popstate.window="onNavigated()"
     class="pointer-events-none">

    {{-- Floating Corner Mini-Player (Shown on all Admin pages except /admin/music when a session is active) --}}
    <div x-cloak
         x-show="!$store.fwMusic.isOnMusicPage && $store.fwMusic.currentTrack && ($store.fwMusic.hasActiveSession || $store.fwMusic.isPlaying)"
         style="display: none;"
         x-transition:enter="transition-all duration-300 ease-out"
         x-transition:enter-start="translate-y-8 translate-x-4 scale-90 opacity-0"
         x-transition:enter-end="translate-y-0 translate-x-0 scale-100 opacity-100"
         x-transition:leave="transition-all duration-200 ease-in"
         x-transition:leave-start="translate-y-0 translate-x-0 scale-100 opacity-100"
         x-transition:leave-end="translate-y-6 translate-x-3 scale-95 opacity-0"
         class="fixed bottom-5 right-5 z-[95] pointer-events-auto select-none">

        <div class="w-[305px] sm:w-[340px] rounded-2xl bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200/90 dark:border-gray-800 shadow-[0_12px_32px_rgba(0,0,0,0.14)] dark:shadow-[0_16px_42px_rgba(0,0,0,0.7)] overflow-hidden transition-colors duration-200">
            <div class="p-2.5 flex items-center gap-3">
                {{-- Cover Art (Click navigates back to /admin/music) --}}
                <a href="{{ route('admin.music') }}"
                   wire:navigate
                   title="Zum Funkenwerk Soundplayer (/admin/music)"
                   class="relative w-11 h-11 rounded-xl overflow-hidden bg-gray-800 border border-gray-200/80 dark:border-gray-700/80 shrink-0 group block">
                    <img :src="$store.fwMusic.currentTrack ? $store.fwMusic.currentTrack.cover : '/shop/projekt/logo/funkenwerk.png'"
                         alt=""
                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">

                    {{-- Subtle Animated Equalizer Overlay when Playing --}}
                    <div x-show="$store.fwMusic.isPlaying"
                         style="display: none;"
                         class="absolute inset-0 bg-black/35 flex items-end justify-center gap-0.5 pb-2">
                        <span class="w-0.5 bg-[#C5A059] rounded-full animate-[fwEqBar1_0.75s_ease-in-out_infinite]" style="height: 10px;"></span>
                        <span class="w-0.5 bg-[#C5A059] rounded-full animate-[fwEqBar2_0.55s_ease-in-out_infinite]" style="height: 15px;"></span>
                        <span class="w-0.5 bg-[#C5A059] rounded-full animate-[fwEqBar3_0.85s_ease-in-out_infinite]" style="height: 8px;"></span>
                    </div>
                </a>

                {{-- Track Info (Click navigates back to /admin/music) --}}
                <a href="{{ route('admin.music') }}"
                   wire:navigate
                   title="Musikplayer öffnen"
                   class="min-w-0 flex-1 group block">
                    <div class="text-xs sm:text-[13px] font-bold text-gray-900 dark:text-white truncate group-hover:text-[#C5A059] transition-colors"
                         x-text="$store.fwMusic.currentTrack ? $store.fwMusic.currentTrack.title : 'Kein Titel'"></div>
                    <div class="flex items-center gap-1.5 text-[11px] text-[#C5A059] font-semibold truncate mt-0.5">
                        <span class="truncate" x-text="$store.fwMusic.currentTrack ? $store.fwMusic.currentTrack.artist : 'Funkenwerk'"></span>
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span class="font-mono text-[10px] text-gray-400 dark:text-gray-500 shrink-0"
                              x-text="$store.fwMusic.formatSec($store.fwMusic.currentTime)">0:00</span>
                    </div>
                </a>

                {{-- Transport Controls --}}
                <div class="flex items-center gap-1 shrink-0">
                    {{-- Previous --}}
                    <button type="button"
                            @click.stop="$store.fwMusic.prevTrack()"
                            class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-[#C5A059] hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                            title="Vorheriger Titel">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
                    </button>

                    {{-- Play / Pause --}}
                    <button type="button"
                            @click.stop="$store.fwMusic.togglePlay()"
                            class="w-8 h-8 rounded-full bg-[#C5A059] text-gray-950 flex items-center justify-center shadow-sm hover:scale-105 active:scale-95 transition cursor-pointer"
                            title="Wiedergabe / Pause">
                        <svg x-show="!$store.fwMusic.isPlaying" class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <svg x-show="$store.fwMusic.isPlaying" style="display: none;" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                        </svg>
                    </button>

                    {{-- Next --}}
                    <button type="button"
                            @click.stop="$store.fwMusic.nextTrack()"
                            class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-[#C5A059] hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer"
                            title="Nächster Titel">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                    </button>

                    {{-- Close Mini-Player --}}
                    <button type="button"
                            @click.stop="$store.fwMusic.closeMiniPlayer()"
                            class="w-6 h-6 rounded-lg flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer ml-0.5"
                            title="Player schließen">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Bottom Interactive Progress Bar --}}
            <div class="w-full h-1.5 bg-gray-200 dark:bg-gray-800 cursor-pointer relative group"
                 @click.stop="$store.fwMusic.seekTo($event)"
                 title="Position wählen">
                <div class="h-full bg-[#C5A059] transition-all duration-100"
                     :style="'width: ' + ($store.fwMusic.duration > 0 ? Math.min(100, ($store.fwMusic.currentTime / $store.fwMusic.duration) * 100) : 0) + '%'"></div>
            </div>
        </div>
    </div>

    <style>
        @keyframes fwEqBar1 {
            0%, 100% { height: 5px; }
            50% { height: 14px; }
        }
        @keyframes fwEqBar2 {
            0%, 100% { height: 14px; }
            50% { height: 6px; }
        }
        @keyframes fwEqBar3 {
            0%, 100% { height: 7px; }
            50% { height: 13px; }
        }
    </style>

    <script>
        (function () {
            const STATE_KEY = 'fw_music_player_state_v1';
            const PREFS_KEY = 'fw_music_prefs_v1';

            function createFwMusicStoreObject() {
                return {
                    tracks: [],
                    viewTracks: [],
                    currentIndex: 0,
                    currentTrack: null,
                    isPlaying: false,
                    isBuffering: false,
                    isShuffle: false,
                    isRepeat: false,
                    currentTime: 0,
                    duration: 0,
                    volume: 0.85,
                    hasActiveSession: false,
                    isOnMusicPage: false,
                    _playRequestId: 0,
                    _lastPreloadedUrl: '',
                    _lastSavedAt: 0,
                    _engineReady: false,

                    getAudio() {
                        if (!window.__fwGlobalAudio) {
                            const a = new window.Audio();
                            a.preload = 'auto';
                            window.__fwGlobalAudio = a;
                        }
                        return window.__fwGlobalAudio;
                    },

                    getPreloadAudio() {
                        if (!window.__fwGlobalAudioPreload) {
                            const p = new window.Audio();
                            p.preload = 'auto';
                            p.muted = true;
                            window.__fwGlobalAudioPreload = p;
                        }
                        return window.__fwGlobalAudioPreload;
                    },

                    initEngine() {
                        this.syncPageLocation();
                        const audio = this.getAudio();
                        this.getPreloadAudio();

                        if (this._engineReady && window.__fwGlobalAudioBound) {
                            return;
                        }
                        this._engineReady = true;

                        // Load user preferences (volume, shuffle, repeat)
                        try {
                            const rawPrefs = localStorage.getItem(PREFS_KEY);
                            if (rawPrefs) {
                                const prefs = JSON.parse(rawPrefs);
                                if (typeof prefs.volume === 'number' && !isNaN(prefs.volume)) {
                                    this.volume = Math.max(0, Math.min(1, prefs.volume));
                                }
                                if (typeof prefs.isShuffle === 'boolean') this.isShuffle = prefs.isShuffle;
                                if (typeof prefs.isRepeat === 'boolean') this.isRepeat = prefs.isRepeat;
                            }
                        } catch (e) {}

                        audio.volume = this.volume;

                        if (!window.__fwGlobalAudioBound) {
                            window.__fwGlobalAudioBound = true;

                            audio.addEventListener('loadstart', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (s) s.isBuffering = true;
                            });

                            audio.addEventListener('waiting', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (s) s.isBuffering = true;
                            });

                            audio.addEventListener('canplay', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (s) s.isBuffering = false;
                            });

                            audio.addEventListener('canplaythrough', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (s) {
                                    s.isBuffering = false;
                                    s.preloadNextTrack();
                                }
                            });

                            audio.addEventListener('play', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (s) {
                                    s.isPlaying = true;
                                    s.hasActiveSession = true;
                                    s.saveSessionState(true);
                                    s.updateMediaSession();
                                }
                            });

                            audio.addEventListener('playing', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (s) {
                                    s.isPlaying = true;
                                    s.isBuffering = false;
                                    s.hasActiveSession = true;
                                }
                            });

                            audio.addEventListener('pause', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (s && !audio.seeking && !audio.ended) {
                                    s.isPlaying = false;
                                    s.saveSessionState(true);
                                }
                            });

                            audio.addEventListener('timeupdate', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (!s) return;
                                s.currentTime = audio.currentTime || 0;
                                if (s.currentTime > 1.5) {
                                    s.preloadNextTrack();
                                }
                                const now = Date.now();
                                if (now - s._lastSavedAt > 1500) {
                                    s._lastSavedAt = now;
                                    s.saveSessionState(false);
                                }
                            });

                            audio.addEventListener('loadedmetadata', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (!s) return;
                                if (audio.duration && !isNaN(audio.duration) && isFinite(audio.duration) && audio.duration > 0) {
                                    s.duration = Math.round(audio.duration);
                                } else if (s.currentTrack) {
                                    s.duration = s.currentTrack.duration || 0;
                                }
                            });

                            audio.addEventListener('durationchange', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (!s) return;
                                if (audio.duration && !isNaN(audio.duration) && isFinite(audio.duration) && audio.duration > 0) {
                                    s.duration = Math.round(audio.duration);
                                }
                            });

                            audio.addEventListener('ended', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (!s) return;
                                if (s.isRepeat) {
                                    audio.currentTime = 0;
                                    s.currentTime = 0;
                                    const p = audio.play();
                                    if (p !== undefined) {
                                        p.then(() => { s.isPlaying = true; }).catch(() => {});
                                    }
                                } else {
                                    s.nextTrack();
                                }
                            });

                            audio.addEventListener('error', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (!s) return;
                                s.isBuffering = false;
                                if (s.isPlaying && s.tracks.length > 1) {
                                    setTimeout(() => {
                                        if (s.isPlaying) s.nextTrack();
                                    }, 600);
                                } else {
                                    s.isPlaying = false;
                                }
                            });

                            window.addEventListener('beforeunload', () => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (s) s.saveSessionState(true);
                            });

                            // Intercept plain internal /admin/... links when music session is active so wire:navigate keeps the JS context alive
                            document.addEventListener('click', (e) => {
                                const s = window.Alpine ? Alpine.store('fwMusic') : null;
                                if (!s || (!s.isPlaying && !s.hasActiveSession)) return;
                                if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

                                const link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
                                if (!link) return;
                                if (link.hasAttribute('wire:navigate') || link.hasAttribute('wire:navigate.hover') || link.hasAttribute('download')) return;
                                if (link.target && link.target !== '_self') return;

                                const rawHref = link.getAttribute('href') || '';
                                if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) return;

                                try {
                                    const url = new URL(link.href, window.location.origin);
                                    if (url.origin !== window.location.origin) return;
                                    if (!url.pathname.startsWith('/admin')) return;
                                    if (url.pathname.includes('/stream/') || url.pathname.endsWith('.pdf') || url.pathname.includes('/download') || url.pathname.includes('/attachment/')) return;

                                    if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                                        e.preventDefault();
                                        window.Livewire.navigate(url.pathname + url.search + url.hash);
                                    }
                                } catch (err) {}
                            }, true);
                        }

                        // Restore active session state if browser was hard-reloaded
                        if (!this.currentTrack && !audio.src) {
                            this.restoreSessionState();
                        }
                    },

                    syncPageLocation() {
                        const path = (window.location.pathname || '').replace(/\/+$/, '');
                        this.isOnMusicPage = (path === '/admin/music');
                    },

                    savePrefs() {
                        try {
                            localStorage.setItem(PREFS_KEY, JSON.stringify({
                                volume: this.volume,
                                isShuffle: this.isShuffle,
                                isRepeat: this.isRepeat,
                            }));
                        } catch (e) {}
                    },

                    saveSessionState(force = false) {
                        try {
                            if (!this.currentTrack) {
                                if (force) sessionStorage.removeItem(STATE_KEY);
                                return;
                            }
                            const audio = this.getAudio();
                            sessionStorage.setItem(STATE_KEY, JSON.stringify({
                                currentTrack: this.currentTrack,
                                currentIndex: this.currentIndex,
                                tracks: (this.tracks || []).slice(0, 500),
                                currentTime: audio ? (audio.currentTime || this.currentTime || 0) : this.currentTime,
                                duration: this.duration || 0,
                                isPlaying: this.isPlaying,
                                hasActiveSession: this.hasActiveSession,
                                savedAt: Date.now(),
                            }));
                        } catch (e) {}
                    },

                    restoreSessionState() {
                        try {
                            const raw = sessionStorage.getItem(STATE_KEY);
                            if (!raw) return;
                            const data = JSON.parse(raw);
                            if (!data || !data.currentTrack || !data.currentTrack.id) return;

                            this.tracks = Array.isArray(data.tracks) ? data.tracks : [data.currentTrack];
                            this.currentTrack = data.currentTrack;
                            this.currentIndex = typeof data.currentIndex === 'number' ? data.currentIndex : 0;
                            this.duration = data.duration || data.currentTrack.duration || 0;
                            this.currentTime = typeof data.currentTime === 'number' ? data.currentTime : 0;
                            this.hasActiveSession = Boolean(data.hasActiveSession || data.isPlaying);

                            if (this.hasActiveSession) {
                                const audio = this.getAudio();
                                audio.src = this.currentTrack.url;
                                audio.volume = this.volume;
                                const targetTime = this.currentTime;
                                const onLoaded = () => {
                                    audio.removeEventListener('loadedmetadata', onLoaded);
                                    if (targetTime > 0 && (!audio.duration || targetTime < audio.duration - 1)) {
                                        try { audio.currentTime = targetTime; } catch (e) {}
                                    }
                                };
                                audio.addEventListener('loadedmetadata', onLoaded);
                                audio.load();

                                if (data.isPlaying) {
                                    const p = audio.play();
                                    if (p !== undefined) {
                                        p.then(() => {
                                            this.isPlaying = true;
                                        }).catch(() => {
                                            this.isPlaying = false;
                                        });
                                    }
                                }
                            }
                        } catch (e) {}
                    },

                    syncViewTracks(newTracks) {
                        if (!Array.isArray(newTracks)) return;
                        this.viewTracks = newTracks;

                        // Update metadata of currently selected track if edited in Track Inspector
                        if (this.currentTrack) {
                            const updatedInView = newTracks.find(t => t.id === this.currentTrack.id);
                            if (updatedInView) {
                                this.currentTrack.title = updatedInView.title;
                                this.currentTrack.artist = updatedInView.artist;
                                this.currentTrack.cover = updatedInView.cover;
                            }
                            const updatedInQueue = this.tracks.find(t => t.id === this.currentTrack.id);
                            if (updatedInQueue && updatedInView) {
                                updatedInQueue.title = updatedInView.title;
                                updatedInQueue.artist = updatedInView.artist;
                                updatedInQueue.cover = updatedInView.cover;
                            }
                        }

                        // Only replace active queue if user hasn't started an active playback session
                        if (!this.isPlaying && !this.hasActiveSession) {
                            this.tracks = [...newTracks];
                            if (newTracks.length > 0 && !this.currentTrack) {
                                this.currentIndex = 0;
                                this.currentTrack = newTracks[0];
                                this.duration = this.currentTrack.duration || 0;
                                this.currentTime = 0;
                            }
                        }
                    },

                    playTrackById(id, fallbackTrack = null) {
                        if (!id) return;
                        if (this.viewTracks.length > 0) {
                            this.tracks = [...this.viewTracks];
                        }
                        let trackObj = this.tracks.find(t => t.id === id) || fallbackTrack;
                        if (!trackObj) return;
                        this.playTrackItem(trackObj, this.tracks);
                    },

                    playTrackItem(trackObj, customQueue = null) {
                        if (!trackObj || !trackObj.id) return;
                        if (Array.isArray(customQueue) && customQueue.length > 0) {
                            this.tracks = [...customQueue];
                        } else if (this.viewTracks.length > 0) {
                            this.tracks = [...this.viewTracks];
                        }

                        const audio = this.getAudio();
                        if (this.currentTrack && this.currentTrack.id === trackObj.id && audio.src && audio.src.includes(trackObj.id)) {
                            this.togglePlay();
                            return;
                        }

                        const idx = this.tracks.findIndex(t => t.id === trackObj.id);
                        this.currentIndex = idx !== -1 ? idx : 0;
                        this.currentTrack = trackObj;
                        this.duration = trackObj.duration || 0;
                        this.currentTime = 0;
                        this.hasActiveSession = true;
                        this._startCurrentTrack();
                    },

                    playAllFromView(shuffleMode = false) {
                        if (this.viewTracks.length > 0) {
                            this.tracks = [...this.viewTracks];
                        }
                        if (this.tracks.length === 0) return;
                        this.isShuffle = Boolean(shuffleMode);
                        this.savePrefs();
                        if (this.isShuffle && this.tracks.length > 1) {
                            const rnd = Math.floor(Math.random() * this.tracks.length);
                            this.playIndex(rnd);
                        } else {
                            this.playIndex(0);
                        }
                    },

                    playIndex(idx) {
                        if (idx < 0 || idx >= this.tracks.length) return;
                        this.currentIndex = idx;
                        this.currentTrack = this.tracks[idx];
                        this.duration = this.currentTrack.duration || 0;
                        this.currentTime = 0;
                        this.hasActiveSession = true;
                        this._startCurrentTrack();
                    },

                    _startCurrentTrack() {
                        if (!this.currentTrack) return;
                        const reqId = ++this._playRequestId;
                        const audio = this.getAudio();

                        this.isPlaying = true;
                        this.isBuffering = true;
                        this.hasActiveSession = true;

                        audio.volume = this.volume;
                        audio.src = this.currentTrack.url;
                        audio.load();

                        const playPromise = audio.play();
                        if (playPromise !== undefined) {
                            playPromise.then(() => {
                                if (reqId !== this._playRequestId) return;
                                this.isPlaying = true;
                                this.isBuffering = false;
                                this.saveSessionState(true);
                                this.updateMediaSession();
                                this.preloadNextTrack();
                            }).catch((err) => {
                                if (reqId !== this._playRequestId) return;
                                if (err && err.name === 'AbortError') return;
                                this.isPlaying = false;
                                this.isBuffering = false;
                            });
                        }
                    },

                    togglePlay() {
                        if (!this.currentTrack && this.viewTracks.length > 0) {
                            this.tracks = [...this.viewTracks];
                            this.playIndex(0);
                            return;
                        }
                        if (!this.currentTrack) return;

                        const audio = this.getAudio();
                        this.hasActiveSession = true;

                        if (!audio.src || !audio.src.includes(this.currentTrack.id)) {
                            this._startCurrentTrack();
                            return;
                        }

                        if (audio.paused) {
                            const reqId = ++this._playRequestId;
                            this.isPlaying = true;
                            const p = audio.play();
                            if (p !== undefined) {
                                p.then(() => {
                                    if (reqId !== this._playRequestId) return;
                                    this.isPlaying = true;
                                    this.saveSessionState(true);
                                }).catch((err) => {
                                    if (reqId !== this._playRequestId) return;
                                    if (err && err.name === 'AbortError') return;
                                    this.isPlaying = false;
                                });
                            }
                        } else {
                            audio.pause();
                            this.isPlaying = false;
                            this.saveSessionState(true);
                        }
                    },

                    nextTrack() {
                        if (this.tracks.length === 0 && this.viewTracks.length > 0) {
                            this.tracks = [...this.viewTracks];
                        }
                        if (this.tracks.length === 0) return;
                        const curIdx = this.currentTrack ? this.tracks.findIndex(t => t.id === this.currentTrack.id) : this.currentIndex;
                        const baseIdx = curIdx !== -1 ? curIdx : 0;

                        if (this.isShuffle && this.tracks.length > 1) {
                            let rnd = Math.floor(Math.random() * this.tracks.length);
                            if (rnd === baseIdx) rnd = (rnd + 1) % this.tracks.length;
                            this.playIndex(rnd);
                        } else {
                            const next = (baseIdx + 1) % this.tracks.length;
                            this.playIndex(next);
                        }
                    },

                    prevTrack() {
                        if (this.tracks.length === 0 && this.viewTracks.length > 0) {
                            this.tracks = [...this.viewTracks];
                        }
                        if (this.tracks.length === 0) return;

                        const audio = this.getAudio();
                        if (audio && audio.currentTime > 3) {
                            audio.currentTime = 0;
                            this.currentTime = 0;
                            return;
                        }

                        const curIdx = this.currentTrack ? this.tracks.findIndex(t => t.id === this.currentTrack.id) : this.currentIndex;
                        const baseIdx = curIdx !== -1 ? curIdx : 0;
                        const prev = (baseIdx - 1 + this.tracks.length) % this.tracks.length;
                        this.playIndex(prev);
                    },

                    seekTo(e) {
                        if (!this.duration || !e || !e.currentTarget) return;
                        const rect = e.currentTarget.getBoundingClientRect();
                        if (!rect.width) return;
                        const ratio = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
                        const target = ratio * this.duration;
                        const audio = this.getAudio();
                        if (audio && audio.src) {
                            try { audio.currentTime = target; } catch (err) {}
                        }
                        this.currentTime = target;
                        this.saveSessionState(true);
                    },

                    setVolume(val) {
                        const num = parseFloat(val);
                        if (isNaN(num)) return;
                        this.volume = Math.max(0, Math.min(1, num));
                        const audio = this.getAudio();
                        if (audio) audio.volume = this.volume;
                        this.savePrefs();
                    },

                    toggleShuffle() {
                        this.isShuffle = !this.isShuffle;
                        this.savePrefs();
                    },

                    toggleRepeat() {
                        this.isRepeat = !this.isRepeat;
                        this.savePrefs();
                    },

                    closeMiniPlayer() {
                        const audio = this.getAudio();
                        if (audio) {
                            audio.pause();
                        }
                        this.isPlaying = false;
                        this.hasActiveSession = false;
                        try { sessionStorage.removeItem(STATE_KEY); } catch (e) {}
                    },

                    preloadNextTrack() {
                        if (this.isShuffle || this.tracks.length <= 1 || !this.currentTrack) return;
                        const curIdx = this.tracks.findIndex(t => t.id === this.currentTrack.id);
                        const nextIdx = ((curIdx !== -1 ? curIdx : this.currentIndex) + 1) % this.tracks.length;
                        const nextTrack = this.tracks[nextIdx];
                        if (!nextTrack || !nextTrack.url || this._lastPreloadedUrl === nextTrack.url) return;

                        this._lastPreloadedUrl = nextTrack.url;
                        const preload = this.getPreloadAudio();
                        preload.src = nextTrack.url;
                        preload.load();
                    },

                    updateMediaSession() {
                        if (!('mediaSession' in navigator) || !this.currentTrack) return;
                        try {
                            navigator.mediaSession.metadata = new MediaMetadata({
                                title: this.currentTrack.title || 'Funkenwerk Soundplayer',
                                artist: this.currentTrack.artist || 'Mein Seelenfunke',
                                album: 'Funkenwerk Soundplayer',
                                artwork: [
                                    { src: this.currentTrack.cover || '/shop/projekt/logo/funkenwerk.png', sizes: '512x512', type: 'image/png' }
                                ]
                            });
                            navigator.mediaSession.setActionHandler('play', () => this.togglePlay());
                            navigator.mediaSession.setActionHandler('pause', () => this.togglePlay());
                            navigator.mediaSession.setActionHandler('previoustrack', () => this.prevTrack());
                            navigator.mediaSession.setActionHandler('nexttrack', () => this.nextTrack());
                        } catch (e) {}
                    },

                    formatSec(sec) {
                        if (!sec || isNaN(sec) || sec < 0) return '0:00';
                        const s = Math.floor(sec);
                        const m = Math.floor(s / 60);
                        const rem = s % 60;
                        return m + ':' + (rem < 10 ? '0' + rem : rem);
                    }
                };
            }

            function ensureFwMusicStore() {
                if (!window.Alpine) return null;
                let store = Alpine.store('fwMusic');
                if (!store) {
                    Alpine.store('fwMusic', createFwMusicStoreObject());
                    store = Alpine.store('fwMusic');
                }
                window.FwMusic = store;
                return store;
            }

            document.addEventListener('alpine:init', () => {
                const s = ensureFwMusicStore();
                if (s) s.initEngine();
            });

            if (window.Alpine) {
                const s = ensureFwMusicStore();
                if (s) s.initEngine();
            }

            window.funkenwerkGlobalMusicShell = function () {
                return {
                    initShell() {
                        const s = ensureFwMusicStore();
                        if (s) {
                            s.initEngine();
                            s.syncPageLocation();
                        }
                    },
                    onNavigating() {
                        const s = ensureFwMusicStore();
                        if (s) s.saveSessionState(true);
                    },
                    onNavigated() {
                        const s = ensureFwMusicStore();
                        if (s) {
                            s.syncPageLocation();
                        }
                    }
                };
            };

            window.funkenwerkMusicStudioPage = function () {
                return {
                    get store() {
                        return ensureFwMusicStore();
                    },
                    get currentTrack() {
                        return this.store ? this.store.currentTrack : null;
                    },
                    get isPlaying() {
                        return this.store ? this.store.isPlaying : false;
                    },
                    get isBuffering() {
                        return this.store ? this.store.isBuffering : false;
                    },
                    get isShuffle() {
                        return this.store ? this.store.isShuffle : false;
                    },
                    set isShuffle(val) {
                        if (this.store) {
                            this.store.isShuffle = Boolean(val);
                            this.store.savePrefs();
                        }
                    },
                    get isRepeat() {
                        return this.store ? this.store.isRepeat : false;
                    },
                    set isRepeat(val) {
                        if (this.store) {
                            this.store.isRepeat = Boolean(val);
                            this.store.savePrefs();
                        }
                    },
                    get currentTime() {
                        return this.store ? this.store.currentTime : 0;
                    },
                    get duration() {
                        return this.store ? this.store.duration : 0;
                    },
                    get volume() {
                        return this.store ? this.store.volume : 0.85;
                    },
                    init() {
                        const s = this.store;
                        if (!s) return;
                        s.initEngine();
                        s.isOnMusicPage = true;
                        const initialTracks = this.readViewTracksFromDom();
                        if (initialTracks.length > 0) {
                            s.syncViewTracks(initialTracks);
                        }
                    },
                    readViewTracksFromDom() {
                        if (this.$refs.viewTracksData && this.$refs.viewTracksData.dataset.tracks) {
                            try {
                                const parsed = JSON.parse(this.$refs.viewTracksData.dataset.tracks);
                                if (Array.isArray(parsed)) return parsed;
                            } catch (e) {}
                        }
                        return [];
                    },
                    onViewTracksSynced(newTracks) {
                        if (!this.store) return;
                        if (Array.isArray(newTracks)) {
                            this.store.syncViewTracks(newTracks);
                        } else {
                            this.store.syncViewTracks(this.readViewTracksFromDom());
                        }
                    },
                    playTrackById(id) {
                        if (!this.store) return;
                        const domTracks = this.readViewTracksFromDom();
                        if (domTracks.length > 0) {
                            this.store.viewTracks = domTracks;
                        }
                        this.store.playTrackById(id);
                    },
                    playTrackItem(trackObj) {
                        if (!this.store) return;
                        const domTracks = this.readViewTracksFromDom();
                        if (domTracks.length > 0) {
                            this.store.viewTracks = domTracks;
                        }
                        this.store.playTrackItem(trackObj);
                    },
                    playAllFromView(shuffleMode = false) {
                        if (!this.store) return;
                        const domTracks = this.readViewTracksFromDom();
                        if (domTracks.length > 0) {
                            this.store.viewTracks = domTracks;
                        }
                        this.store.playAllFromView(shuffleMode);
                    },
                    togglePlay() {
                        if (this.store) this.store.togglePlay();
                    },
                    nextTrack() {
                        if (this.store) this.store.nextTrack();
                    },
                    prevTrack() {
                        if (this.store) this.store.prevTrack();
                    },
                    seekTo(e) {
                        if (this.store) this.store.seekTo(e);
                    },
                    setVolume(val) {
                        if (this.store) this.store.setVolume(val);
                    },
                    toggleShuffle() {
                        if (this.store) this.store.toggleShuffle();
                    },
                    toggleRepeat() {
                        if (this.store) this.store.toggleRepeat();
                    },
                    formatSec(sec) {
                        return this.store ? this.store.formatSec(sec) : '0:00';
                    }
                };
            };
        })();
    </script>
</div>
