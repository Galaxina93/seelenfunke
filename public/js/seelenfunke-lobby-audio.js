/*!
 * Mein-Seelenfunke · 3D-Lobby Audio-Engine (Hintergrundmusik & Matte Studio-Soundeffekte)
 * 1:1 Kopplung an window.SfFunkiBgm (Kunden-Dashboard) + Pre-Baked Matte WebAudio-Synthese.
 * Zero-Autoplay ohne User-Opt-in, 0 externe CDN-Abhaengigkeiten, 0 % Last nach dem Schliessen.
 */

export const LOBBY_BGM_TRACK_URL = '/shop/gamification/audio/bgm_funki_game.mp3';

export const LOBBY_SFX_URLS = {
    shield: '/shop/customer/gamification/sounds/shield_active.mp3',
    sprint: '/shop/customer/gamification/sounds/teleport_active.mp3',
};

const LS_BGM_VOL = 'sf_funki_audio_volume';
const LS_BGM_MUTED = 'sf_funki_audio_muted';
const LS_LOBBY_EXPLICIT_MUTED = 'sf_lobby_bgm_explicit_muted';
const SS_BGM_SESSION = 'sf_funki_bgm_session_enabled';
const LS_SFX_VOL = 'sf_lobby_sfx_volume';
const LS_SFX_MUTED = 'sf_lobby_sfx_muted';

function clampNum(val, min, max) {
    const n = Number(val);
    if (!Number.isFinite(n)) return min;
    return Math.max(min, Math.min(max, n));
}

function readCsrfToken() {
    if (typeof document === 'undefined') return '';
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? (el.getAttribute('content') || '') : '';
}

export class SeelenfunkeLobbyAudio {
    constructor(options = {}) {
        this.isAuthenticated = Boolean(options.isAuthenticated);
        this.lobbyOpen = false;
        this.lobbyActive = false;

        this.bgmVolume = 50;
        this.bgmMuted = false;
        this.sfxVolume = 50;
        this.sfxMuted = false;

        this._fallbackBgmEl = null;
        this._serverSyncTimer = null;
        this._listeners = new Set();
        this._oneShotUnlockBound = null;

        this._ctx = null;
        this._masterSfxGain = null;
        this._warmNoiseBuffer = null;

        this._lavaRumblerNodes = null;
        this._lastFootstepAt = 0;

        this._mp3Pool = {
            shield: null,
            sprint: null,
        };

        this._boundBgmEvent = (ev) => {
            if (!ev || !ev.detail) return;
            if (typeof ev.detail.volume === 'number') {
                this.bgmVolume = Math.round(clampNum(ev.detail.volume, 0, 100));
            }
            if (typeof ev.detail.muted === 'boolean' && this.lobbyActive) {
                this.bgmMuted = ev.detail.muted;
                try {
                    localStorage.setItem(LS_LOBBY_EXPLICIT_MUTED, this.bgmMuted ? '1' : '0');
                } catch (e) {}
            }
            this._emitChange();
        };

        this._boundGestureUnlock = () => {
            this._unlockContextIfNeeded();
            if (this.lobbyOpen && !this.bgmMuted && this.bgmVolume > 0) {
                if (this._hasDashboardBgm()) {
                    const dockEl = window.SfFunkiBgm.getAudioEl ? window.SfFunkiBgm.getAudioEl() : null;
                    if (dockEl && dockEl.paused && !window.SfFunkiBgm.muted) {
                        dockEl.play().catch(() => {});
                    }
                } else {
                    this._syncFallbackBgmPlayback();
                }
            }
        };

        this._loadStoredSettings();
    }

    _hasDashboardBgm() {
        return typeof window !== 'undefined' && Boolean(window.SfFunkiBgm);
    }

    _isExplicitlyMutedInStorage() {
        if (typeof window === 'undefined') return false;
        try {
            const rawLobbyMuted = localStorage.getItem(LS_LOBBY_EXPLICIT_MUTED);
            if (rawLobbyMuted !== null && rawLobbyMuted !== '') {
                return rawLobbyMuted === '1' || rawLobbyMuted === 'true';
            }
            if (!this._hasDashboardBgm()) {
                const rawMuted = localStorage.getItem(LS_BGM_MUTED);
                if (rawMuted !== null && rawMuted !== '') {
                    return rawMuted === '1' || rawMuted === 'true';
                }
            }
        } catch (e) {
            return false;
        }
        return false;
    }

    _loadStoredSettings() {
        if (typeof window === 'undefined') return;

        const explicitMuted = this._isExplicitlyMutedInStorage();

        try {
            const rawVol = localStorage.getItem(LS_BGM_VOL);
            if (rawVol !== null && rawVol !== '') {
                const parsed = Number(rawVol);
                if (Number.isFinite(parsed)) {
                    const scaled = (parsed > 0 && parsed <= 1 && String(rawVol).indexOf('.') !== -1)
                        ? Math.round(parsed * 100)
                        : Math.round(parsed);
                    this.bgmVolume = clampNum(scaled, 0, 100);
                } else {
                    this.bgmVolume = 50;
                }
            } else if (this._hasDashboardBgm() && typeof window.SfFunkiBgm.volume === 'number' && window.SfFunkiBgm.volume > 0) {
                this.bgmVolume = Math.round(clampNum(window.SfFunkiBgm.volume, 0, 100));
            } else {
                this.bgmVolume = 50;
            }
        } catch (e) {
            this.bgmVolume = 50;
        }

        if (explicitMuted || this.bgmVolume <= 0) {
            this.bgmMuted = true;
        } else if (this._hasDashboardBgm()) {
            let sessionAlreadyOptedIn = false;
            try {
                sessionAlreadyOptedIn = sessionStorage.getItem(SS_BGM_SESSION) === '1';
            } catch (e) {}
            this.bgmMuted = sessionAlreadyOptedIn ? Boolean(window.SfFunkiBgm.muted) : false;
        } else {
            this.bgmMuted = false;
        }

        try {
            const rawSfxVol = localStorage.getItem(LS_SFX_VOL);
            const rawSfxMuted = localStorage.getItem(LS_SFX_MUTED);
            if (rawSfxVol !== null && rawSfxVol !== '') {
                this.sfxVolume = Math.round(clampNum(parseInt(rawSfxVol, 10), 0, 100));
            } else {
                this.sfxVolume = 50;
            }
            if (rawSfxMuted !== null) {
                this.sfxMuted = rawSfxMuted === '1' || rawSfxMuted === 'true';
            } else {
                this.sfxMuted = false;
            }
        } catch (e) {
            this.sfxVolume = 50;
            this.sfxMuted = false;
        }
    }

    setAuthenticated(flag) {
        this.isAuthenticated = Boolean(flag);
    }

    onChange(listener) {
        if (typeof listener === 'function') {
            this._listeners.add(listener);
        }
        return () => {
            this._listeners.delete(listener);
        };
    }

    _emitChange() {
        const snap = this.getState();
        this._listeners.forEach((fn) => {
            try {
                fn(snap);
            } catch (e) {
                // Listener-Fehler isolieren
            }
        });
    }

    getState() {
        if (this._hasDashboardBgm() && this.lobbyActive) {
            let sessionOptIn = false;
            try {
                sessionOptIn = sessionStorage.getItem(SS_BGM_SESSION) === '1';
            } catch (e) {}
            if (sessionOptIn) {
                this.bgmVolume = Math.round(clampNum(window.SfFunkiBgm.volume ?? this.bgmVolume, 0, 100));
                this.bgmMuted = Boolean(window.SfFunkiBgm.muted);
            }
        }
        const bgmAudible = !this.bgmMuted && this.bgmVolume > 0;
        const sfxAudible = !this.sfxMuted && this.sfxVolume > 0;
        return {
            bgmVolume: this.bgmVolume,
            bgmMuted: this.bgmMuted,
            sfxVolume: this.sfxVolume,
            sfxMuted: this.sfxMuted,
            allMuted: !bgmAudible && !sfxAudible,
        };
    }

    _clearOneShotUnlock() {
        if (!this._oneShotUnlockBound || typeof window === 'undefined') return;
        window.removeEventListener('pointerdown', this._oneShotUnlockBound);
        window.removeEventListener('keydown', this._oneShotUnlockBound);
        this._oneShotUnlockBound = null;
    }

    _registerOneShotUnlock() {
        if (this._oneShotUnlockBound || typeof window === 'undefined') return;
        this._oneShotUnlockBound = () => {
            this._clearOneShotUnlock();
            if (!this.lobbyActive || this.bgmMuted || this.bgmVolume <= 0) return;
            this._unlockContextIfNeeded();
            if (this._hasDashboardBgm()) {
                const ctrl = window.SfFunkiBgm;
                if (!ctrl.muted && ctrl.volume > 0) {
                    ctrl.onModalOpen();
                    const dockEl = ctrl.getAudioEl ? ctrl.getAudioEl() : null;
                    if (dockEl && dockEl.paused) {
                        dockEl.volume = Math.max(0.05, ctrl.targetGain ? ctrl.targetGain() : 0.30);
                        dockEl.play().catch(() => {});
                    }
                }
            } else {
                const el = this._ensureFallbackBgmElement();
                if (el) {
                    el.volume = this._fallbackTargetGain();
                    const p = el.play();
                    if (p && typeof p.catch === 'function') p.catch(() => {});
                }
            }
        };
        window.addEventListener('pointerdown', this._oneShotUnlockBound, { once: true, passive: true });
        window.addEventListener('keydown', this._oneShotUnlockBound, { once: true, passive: true });
    }

    /**
     * Startet die 3D-Lobby-Hintergrundmusik synchron im Klick-Event ("3D-Lobby oeffnen" / "3D shoppen"),
     * damit das User-Gesture-Token des Browsers zu 100 % erhalten bleibt.
     */
    startLobbyMusicFromGesture() {
        this._loadStoredSettings();
        this.lobbyOpen = true;
        this.lobbyActive = true;

        try {
            sessionStorage.setItem(SS_BGM_SESSION, '1');
        } catch (e) {}

        if (typeof window !== 'undefined') {
            window.addEventListener('sf-funki-bgm-state', this._boundBgmEvent);
            window.addEventListener('pointerdown', this._boundGestureUnlock, { passive: true });
            window.addEventListener('keydown', this._boundGestureUnlock, { passive: true });
        }

        this._unlockContextIfNeeded();

        const allMuted = this.bgmMuted && this.sfxMuted;
        if (!this.bgmMuted && !allMuted) {
            if (this.bgmVolume <= 0) {
                this.bgmVolume = 50;
            }
            try {
                localStorage.setItem(LS_BGM_MUTED, '0');
                localStorage.setItem('sf_funki_bgm_muted', '0');
            } catch (e) {}

            if (this._hasDashboardBgm()) {
                if (typeof window !== 'undefined' && window.__sfPrewarmLobbyBgmEl) {
                    try {
                        window.__sfPrewarmLobbyBgmEl.pause();
                        window.__sfPrewarmLobbyBgmEl.volume = 0;
                    } catch (e) {}
                    window.__sfPrewarmLobbyBgmEl = null;
                }
                const ctrl = window.SfFunkiBgm;
                if ((ctrl.volume ?? 0) <= 0) {
                    ctrl.setVolume(this.bgmVolume, false);
                }
                ctrl.setMuted(false, false);
                ctrl.onModalOpen();
                const dockEl = ctrl.getAudioEl ? ctrl.getAudioEl() : null;
                if (dockEl && dockEl.paused) {
                    dockEl.loop = true;
                    dockEl.volume = Math.max(0.05, ctrl.targetGain ? ctrl.targetGain() : 0.30);
                    const p = dockEl.play();
                    if (p && typeof p.catch === 'function') {
                        p.catch(() => {
                            this._registerOneShotUnlock();
                        });
                    }
                }
            } else {
                const el = this._ensureFallbackBgmElement();
                if (el) {
                    el.loop = true;
                    el.volume = this._fallbackTargetGain();
                    if (el.paused) {
                        const p = el.play();
                        if (p && typeof p.catch === 'function') {
                            p.catch(() => {
                                this._registerOneShotUnlock();
                            });
                        }
                    }
                }
            }
        }

        this._emitChange();
        return this.getState();
    }

    setLobbyActive(active) {
        if (active) {
            this.onLobbyOpen(this.isAuthenticated);
        } else {
            this.onLobbyClose();
        }
    }

    close() {
        this.onLobbyClose();
    }

    onLobbyOpen(isAuthenticated = undefined) {
        if (isAuthenticated !== undefined) {
            this.isAuthenticated = Boolean(isAuthenticated);
        }
        const alreadyStartedByGesture = this.lobbyActive && this.lobbyOpen;
        this.lobbyOpen = true;
        this.lobbyActive = true;
        if (!alreadyStartedByGesture) {
            this._loadStoredSettings();
        }

        try {
            if (!this.bgmMuted && this.bgmVolume > 0) {
                sessionStorage.setItem(SS_BGM_SESSION, '1');
            }
        } catch (e) {}

        if (typeof window !== 'undefined') {
            window.addEventListener('sf-funki-bgm-state', this._boundBgmEvent);
            window.addEventListener('pointerdown', this._boundGestureUnlock, { passive: true });
            window.addEventListener('keydown', this._boundGestureUnlock, { passive: true });
        }

        if (!this.bgmMuted && this.bgmVolume > 0) {
            if (this._hasDashboardBgm()) {
                if (window.SfFunkiBgm.muted) {
                    window.SfFunkiBgm.setMuted(false, false);
                }
                window.SfFunkiBgm.onModalOpen();
            } else {
                this._syncFallbackBgmPlayback();
            }
        } else if (this._hasDashboardBgm()) {
            window.SfFunkiBgm.onModalOpen();
        }

        this._emitChange();
    }

    onLobbyClose() {
        this.lobbyOpen = false;
        this.lobbyActive = false;
        this._clearOneShotUnlock();
        this.updateLavaAmbience(999, false);

        if (typeof window !== 'undefined') {
            window.removeEventListener('sf-funki-bgm-state', this._boundBgmEvent);
            window.removeEventListener('pointerdown', this._boundGestureUnlock);
            window.removeEventListener('keydown', this._boundGestureUnlock);
            if (window.__sfPrewarmLobbyBgmEl && window.__sfPrewarmLobbyBgmEl !== this._fallbackBgmEl) {
                try {
                    window.__sfPrewarmLobbyBgmEl.pause();
                    window.__sfPrewarmLobbyBgmEl.volume = 0;
                } catch (e) {}
            }
        }

        if (this._hasDashboardBgm()) {
            window.SfFunkiBgm.onModalClose();
        }
        if (this._fallbackBgmEl) {
            try {
                this._fallbackBgmEl.pause();
                this._fallbackBgmEl.volume = 0;
            } catch (e) {}
        }

        if (this._ctx && this._ctx.state === 'running') {
            this._ctx.suspend().catch(() => {});
        }
    }

    _ensureFallbackBgmElement() {
        if (this._fallbackBgmEl) {
            return this._fallbackBgmEl;
        }
        if (typeof window !== 'undefined' && window.__sfPrewarmLobbyBgmEl) {
            this._fallbackBgmEl = window.__sfPrewarmLobbyBgmEl;
            window.__sfPrewarmLobbyBgmEl = null;
            this._fallbackBgmEl.loop = true;
            return this._fallbackBgmEl;
        }
        if (typeof Audio === 'undefined') {
            return null;
        }
        const el = new Audio(LOBBY_BGM_TRACK_URL);
        el.loop = true;
        el.preload = 'auto';
        el.volume = 0;
        this._fallbackBgmEl = el;
        return el;
    }

    _fallbackTargetGain() {
        if (this.bgmMuted || this.bgmVolume <= 0) return 0;
        return clampNum((this.bgmVolume / 100) * 0.50, 0, 0.50);
    }

    _syncFallbackBgmPlayback() {
        if (this._hasDashboardBgm()) return;
        const el = this._ensureFallbackBgmElement();
        if (!el) return;

        if (!this.lobbyOpen || this.bgmMuted || this.bgmVolume <= 0) {
            el.volume = 0;
            if (!el.paused) {
                el.pause();
            }
            return;
        }

        el.volume = this._fallbackTargetGain();
        if (el.paused) {
            const p = el.play();
            if (p && typeof p.catch === 'function') {
                p.catch(() => {
                    this._registerOneShotUnlock();
                });
            }
        }
    }

    _persistFallbackBgmSettings(commitServer = false) {
        try {
            localStorage.setItem(LS_BGM_VOL, String(this.bgmVolume));
            localStorage.setItem(LS_BGM_MUTED, this.bgmMuted ? '1' : '0');
            localStorage.setItem(LS_LOBBY_EXPLICIT_MUTED, this.bgmMuted ? '1' : '0');
            if (!this.bgmMuted && this.bgmVolume > 0) {
                sessionStorage.setItem(SS_BGM_SESSION, '1');
            } else {
                sessionStorage.removeItem(SS_BGM_SESSION);
            }
        } catch (e) {}

        if (commitServer && this.isAuthenticated && typeof fetch === 'function') {
            if (this._serverSyncTimer) clearTimeout(this._serverSyncTimer);
            this._serverSyncTimer = setTimeout(() => {
                fetch('/customer/gamification/audio-settings', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': readCsrfToken(),
                    },
                    body: JSON.stringify({
                        volume: this.bgmVolume,
                        muted: this.bgmMuted,
                        keep_playing: false,
                    }),
                }).catch(() => {});
            }, 240);
        }
    }

    setBgmVolume(vol, commit = true) {
        const clamped = Math.round(clampNum(vol, 0, 100));
        this.bgmVolume = clamped;

        if (this._hasDashboardBgm()) {
            window.SfFunkiBgm.setVolume(clamped, Boolean(commit));
            this.bgmVolume = Math.round(clampNum(window.SfFunkiBgm.volume, 0, 100));
            this.bgmMuted = Boolean(window.SfFunkiBgm.muted);
            try {
                localStorage.setItem(LS_LOBBY_EXPLICIT_MUTED, this.bgmMuted ? '1' : '0');
            } catch (e) {}
        } else {
            if (clamped === 0) {
                this.bgmMuted = true;
            } else if (this.bgmMuted) {
                this.bgmMuted = false;
            }
            this._persistFallbackBgmSettings(Boolean(commit));
            this._syncFallbackBgmPlayback();
        }
        this._emitChange();
    }

    setBgmMuted(muted, commit = true) {
        const nextMuted = Boolean(muted);
        try {
            localStorage.setItem(LS_LOBBY_EXPLICIT_MUTED, nextMuted ? '1' : '0');
        } catch (e) {}
        if (this._hasDashboardBgm()) {
            if (!nextMuted && (window.SfFunkiBgm.volume ?? 0) <= 0) {
                window.SfFunkiBgm.setVolume(this.bgmVolume > 0 ? this.bgmVolume : 50, false);
            }
            window.SfFunkiBgm.setMuted(nextMuted, Boolean(commit));
            this.bgmVolume = Math.round(clampNum(window.SfFunkiBgm.volume, 0, 100));
            this.bgmMuted = Boolean(window.SfFunkiBgm.muted);
        } else {
            this.bgmMuted = nextMuted;
            if (!this.bgmMuted && this.bgmVolume <= 0) {
                this.bgmVolume = 50;
            }
            this._persistFallbackBgmSettings(Boolean(commit));
            this._syncFallbackBgmPlayback();
        }
        this._emitChange();
    }

    setSfxVolume(vol) {
        const clamped = Math.round(clampNum(vol, 0, 100));
        this.sfxVolume = clamped;
        if (clamped === 0) {
            this.sfxMuted = true;
        } else if (this.sfxMuted) {
            this.sfxMuted = false;
        }
        this._saveSfxStorage();
        this._applyMasterSfxGain();
        this._emitChange();
    }

    setSfxMuted(muted) {
        this.sfxMuted = Boolean(muted);
        if (!this.sfxMuted && this.sfxVolume <= 0) {
            this.sfxVolume = 50;
        }
        this._saveSfxStorage();
        this._applyMasterSfxGain();
        this._emitChange();
    }

    toggleQuickMute() {
        const st = this.getState();
        if (st.allMuted) {
            this.setSfxMuted(false);
            this.setBgmMuted(false, true);
            this.playUiSwitch();
        } else {
            this.setBgmMuted(true, true);
            this.setSfxMuted(true);
        }
        return this.getState();
    }

    _saveSfxStorage() {
        try {
            localStorage.setItem(LS_SFX_VOL, String(this.sfxVolume));
            localStorage.setItem(LS_SFX_MUTED, this.sfxMuted ? '1' : '0');
        } catch (e) {}
    }

    _effectiveSfxGain() {
        if (this.sfxMuted || this.sfxVolume <= 0) return 0;
        return clampNum((this.sfxVolume / 100) * 0.68, 0, 0.72);
    }

    _applyMasterSfxGain() {
        if (this._masterSfxGain && this._ctx) {
            this._masterSfxGain.gain.setTargetAtTime(this._effectiveSfxGain(), this._ctx.currentTime, 0.02);
        }
        if (this.sfxMuted || this.sfxVolume <= 0) {
            this.updateLavaAmbience(999, false);
        }
    }

    _ensureContext() {
        if (typeof window === 'undefined') return null;
        if (!this._ctx) {
            const Ctor = window.AudioContext || window.webkitAudioContext;
            if (!Ctor) return null;
            try {
                this._ctx = new Ctor();
                this._masterSfxGain = this._ctx.createGain();
                this._masterSfxGain.gain.value = this._effectiveSfxGain();
                this._masterSfxGain.connect(this._ctx.destination);
                this._warmNoiseBuffer = this._bakeWarmMatteNoise(this._ctx);
            } catch (e) {
                this._ctx = null;
                return null;
            }
        }
        if (this._ctx.state === 'suspended') {
            this._ctx.resume().catch(() => {});
        }
        return this._ctx;
    }

    _unlockContextIfNeeded() {
        if (this.sfxMuted || this.sfxVolume <= 0) return;
        this._ensureContext();
    }

    _bakeWarmMatteNoise(ctx) {
        const sampleRate = ctx.sampleRate || 44100;
        const length = Math.floor(sampleRate * 1.4);
        const buf = ctx.createBuffer(1, length, sampleRate);
        const data = buf.getChannelData(0);
        let last = 0;
        for (let i = 0; i < length; i++) {
            const white = Math.random() * 2 - 1;
            last = (last + 0.085 * white) / 1.085;
            data[i] = clampNum(last * 2.8, -0.95, 0.95);
        }
        return buf;
    }

    _canPlaySfx() {
        return this.lobbyOpen && !this.sfxMuted && this.sfxVolume > 0;
    }

    _playMp3Layer(kind, gainMultiplier = 0.45, playbackRate = 1.0) {
        if (!this._canPlaySfx() || typeof Audio === 'undefined') return;
        const url = LOBBY_SFX_URLS[kind];
        if (!url) return;
        try {
            let el = this._mp3Pool[kind];
            if (!el) {
                el = new Audio(url);
                el.preload = 'auto';
                this._mp3Pool[kind] = el;
            }
            el.pause();
            el.currentTime = 0;
            el.playbackRate = clampNum(playbackRate, 0.65, 1.45);
            el.volume = clampNum(this._effectiveSfxGain() * gainMultiplier, 0, 0.85);
            const p = el.play();
            if (p && typeof p.catch === 'function') {
                p.catch(() => {});
            }
        } catch (e) {}
    }

    _triggerMatteTone({
        type = 'triangle',
        freqStart = 180,
        freqEnd = 90,
        duration = 0.16,
        gain = 0.22,
        cutoff = 900,
        delay = 0,
    }) {
        const ctx = this._ensureContext();
        if (!ctx || !this._masterSfxGain) return;

        const t0 = ctx.currentTime + Math.max(0, delay);
        const t1 = t0 + Math.max(0.02, duration);

        const osc = ctx.createOscillator();
        const filter = ctx.createBiquadFilter();
        const env = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(Math.max(24, freqStart), t0);
        if (freqEnd !== freqStart) {
            osc.frequency.exponentialRampToValueAtTime(Math.max(24, freqEnd), t1);
        }

        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(clampNum(cutoff, 90, 2400), t0);
        filter.Q.value = 0.7;

        env.gain.setValueAtTime(0.0001, t0);
        env.gain.linearRampToValueAtTime(Math.max(0.001, gain), t0 + Math.min(0.014, duration * 0.22));
        env.gain.exponentialRampToValueAtTime(0.0001, t1);

        osc.connect(filter);
        filter.connect(env);
        env.connect(this._masterSfxGain);

        osc.start(t0);
        osc.stop(t1 + 0.02);
        osc.onended = () => {
            try {
                osc.disconnect();
                filter.disconnect();
                env.disconnect();
            } catch (e) {}
        };
    }

    _triggerMatteWhoosh({
        duration = 0.22,
        gain = 0.24,
        filterType = 'bandpass',
        freqStart = 380,
        freqEnd = 180,
        q = 1.1,
        delay = 0,
    }) {
        const ctx = this._ensureContext();
        if (!ctx || !this._masterSfxGain || !this._warmNoiseBuffer) return;

        const t0 = ctx.currentTime + Math.max(0, delay);
        const t1 = t0 + Math.max(0.03, duration);

        const src = ctx.createBufferSource();
        src.buffer = this._warmNoiseBuffer;
        src.loop = true;

        const filter = ctx.createBiquadFilter();
        filter.type = filterType;
        filter.frequency.setValueAtTime(clampNum(freqStart, 80, 2200), t0);
        if (freqEnd !== freqStart) {
            filter.frequency.exponentialRampToValueAtTime(clampNum(freqEnd, 70, 2200), t1);
        }
        filter.Q.value = q;

        const env = ctx.createGain();
        env.gain.setValueAtTime(0.0001, t0);
        env.gain.linearRampToValueAtTime(Math.max(0.001, gain), t0 + Math.min(0.035, duration * 0.28));
        env.gain.exponentialRampToValueAtTime(0.0001, t1);

        src.connect(filter);
        filter.connect(env);
        env.connect(this._masterSfxGain);

        src.start(t0, Math.random() * 0.4);
        src.stop(t1 + 0.02);
        src.onended = () => {
            try {
                src.disconnect();
                filter.disconnect();
                env.disconnect();
            } catch (e) {}
        };
    }

    playUiClick() {
        if (!this._canPlaySfx()) return;
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 390,
            freqEnd: 230,
            duration: 0.055,
            gain: 0.14,
            cutoff: 950,
        });
    }

    playUiSwitch() {
        if (!this._canPlaySfx()) return;
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 310,
            freqEnd: 440,
            duration: 0.075,
            gain: 0.15,
            cutoff: 1100,
        });
    }

    playComboStrike(stage = 1) {
        if (!this._canPlaySfx()) return;
        const st = clampNum(Math.round(stage), 1, 4);

        if (st === 1) {
            // Stufe 1: Knackiger matter Rechts-Hieb
            this._triggerMatteWhoosh({ duration: 0.18, gain: 0.28, freqStart: 540, freqEnd: 220, q: 1.0 });
            this._triggerMatteTone({ type: 'triangle', freqStart: 185, freqEnd: 78, duration: 0.14, gain: 0.24, cutoff: 680 });
        } else if (st === 2) {
            // Stufe 2: Tieferer Doppel-Schwung (Links-Aufwaertshaken)
            this._triggerMatteWhoosh({ duration: 0.24, gain: 0.34, freqStart: 320, freqEnd: 640, q: 1.15 });
            this._triggerMatteTone({ type: 'triangle', freqStart: 145, freqEnd: 68, duration: 0.18, gain: 0.28, cutoff: 620 });
            this._triggerMatteTone({ type: 'sine', freqStart: 245, freqEnd: 330, duration: 0.15, gain: 0.15, cutoff: 980, delay: 0.05 });
        } else if (st === 3) {
            // Stufe 3: 360-Grad-Wirbel-Swoosh
            this._triggerMatteWhoosh({ duration: 0.40, gain: 0.38, freqStart: 260, freqEnd: 720, q: 1.25 });
            this._triggerMatteWhoosh({ duration: 0.26, gain: 0.28, freqStart: 620, freqEnd: 210, q: 1.1, delay: 0.16 });
            this._triggerMatteTone({ type: 'triangle', freqStart: 160, freqEnd: 112, duration: 0.34, gain: 0.24, cutoff: 760 });
        } else {
            // Stufe 4 Phase A: Kraftvolle Sprung-Aufladung (Einschlag folgt exakt bei Bodenkontakt via playComboFinisherImpact)
            this._triggerMatteWhoosh({ duration: 0.34, gain: 0.32, freqStart: 220, freqEnd: 680, q: 1.2 });
            this._triggerMatteTone({ type: 'sine', freqStart: 130, freqEnd: 310, duration: 0.36, gain: 0.24, cutoff: 1050 });
            this._triggerMatteTone({ type: 'triangle', freqStart: 195, freqEnd: 390, duration: 0.32, gain: 0.14, cutoff: 1200, delay: 0.04 });
        }
    }

    playComboFinisherImpact() {
        if (!this._canPlaySfx()) return;
        // Stufe 4 Phase C: Wuchtiger matter Sub-Bass-Bodeneinschlag + warmer 24K-Gold-Kristall-Nachhall
        this._triggerMatteTone({ type: 'sine', freqStart: 96, freqEnd: 33, duration: 0.48, gain: 0.48, cutoff: 260 });
        this._triggerMatteTone({ type: 'triangle', freqStart: 138, freqEnd: 44, duration: 0.32, gain: 0.32, cutoff: 420 });
        this._triggerMatteWhoosh({ duration: 0.44, gain: 0.42, filterType: 'lowpass', freqStart: 560, freqEnd: 120, q: 0.8 });
        // Warmer Gold-Kristall-Nachhall (G4 + D5, tiefpassgefiltert)
        this._triggerMatteTone({ type: 'sine', freqStart: 392.0, freqEnd: 390.0, duration: 0.52, gain: 0.12, cutoff: 1350, delay: 0.04 });
        this._triggerMatteTone({ type: 'sine', freqStart: 587.33, freqEnd: 584.0, duration: 0.46, gain: 0.09, cutoff: 1450, delay: 0.08 });
    }

    playSprint() {
        if (!this._canPlaySfx()) return;
        this._playMp3Layer('sprint', 0.34, 1.06);
        this._triggerMatteWhoosh({ duration: 0.32, gain: 0.26, freqStart: 240, freqEnd: 620, q: 1.0 });
        this._triggerMatteTone({ type: 'triangle', freqStart: 135, freqEnd: 255, duration: 0.24, gain: 0.18, cutoff: 860 });
    }

    playShield() {
        if (!this._canPlaySfx()) return;
        this._playMp3Layer('shield', 0.50, 1.0);
        this._triggerMatteTone({ type: 'sine', freqStart: 196.0, freqEnd: 196.0, duration: 0.45, gain: 0.18, cutoff: 980 });
        this._triggerMatteTone({ type: 'triangle', freqStart: 293.66, freqEnd: 293.66, duration: 0.42, gain: 0.12, cutoff: 1120, delay: 0.03 });
    }

    playHighJump() {
        if (!this._canPlaySfx()) return;
        this._triggerMatteWhoosh({ duration: 0.34, gain: 0.34, freqStart: 190, freqEnd: 690, q: 0.95 });
        this._triggerMatteTone({ type: 'sine', freqStart: 88, freqEnd: 245, duration: 0.28, gain: 0.30, cutoff: 780 });
        this._triggerMatteTone({ type: 'triangle', freqStart: 176, freqEnd: 352, duration: 0.22, gain: 0.15, cutoff: 960, delay: 0.03 });
    }

    playJump(isDouble = false) {
        if (!this._canPlaySfx()) return;
        if (isDouble) {
            this._triggerMatteWhoosh({ duration: 0.22, gain: 0.24, freqStart: 340, freqEnd: 640, q: 1.1 });
            this._triggerMatteTone({ type: 'sine', freqStart: 210, freqEnd: 340, duration: 0.16, gain: 0.18, cutoff: 960 });
        } else {
            this._triggerMatteWhoosh({ duration: 0.13, gain: 0.16, freqStart: 240, freqEnd: 410, q: 0.9 });
            this._triggerMatteTone({ type: 'sine', freqStart: 135, freqEnd: 215, duration: 0.12, gain: 0.16, cutoff: 720 });
        }
    }

    playFootstep(surface = 'marble') {
        if (!this._canPlaySfx()) return;
        const now = (typeof performance !== 'undefined') ? performance.now() : Date.now();
        if (now - this._lastFootstepAt < 190) return;
        this._lastFootstepAt = now;

        const isBasalt = surface === 'basalt' || surface === 'lava';
        const pitchJitter = 0.94 + Math.random() * 0.12;
        this._triggerMatteTone({
            type: 'sine',
            freqStart: (isBasalt ? 115 : 155) * pitchJitter,
            freqEnd: (isBasalt ? 58 : 74) * pitchJitter,
            duration: 0.055,
            gain: isBasalt ? 0.10 : 0.075,
            cutoff: isBasalt ? 380 : 520,
        });
    }

    playCrumbleTick() {
        if (!this._canPlaySfx()) return;
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 138,
            freqEnd: 74,
            duration: 0.085,
            gain: 0.19,
            cutoff: 440,
        });
    }

    playLavaSplash() {
        if (!this._canPlaySfx()) return;
        this._triggerMatteWhoosh({
            duration: 0.52,
            gain: 0.38,
            filterType: 'lowpass',
            freqStart: 780,
            freqEnd: 160,
            q: 0.85,
        });
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 112,
            freqEnd: 40,
            duration: 0.42,
            gain: 0.34,
            cutoff: 320,
        });
    }

    playChestWobble() {
        if (!this._canPlaySfx()) return;
        // Sattes, warm-gedaempftes Truhen-Wackeln (~0.55s Antizipation, 0 schrille Hoehen)
        const offsets = [0.0, 0.105, 0.21, 0.315, 0.42];
        for (let i = 0; i < offsets.length; i++) {
            const d = offsets[i];
            const accent = (i % 2 === 0) ? 1.08 : 0.94;
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: 138 * accent,
                freqEnd: 64 * accent,
                duration: 0.085,
                gain: 0.22 - i * 0.015,
                cutoff: 390,
                delay: d,
            });
            this._triggerMatteTone({
                type: 'sine',
                freqStart: 248 * accent,
                freqEnd: 142 * accent,
                duration: 0.05,
                gain: 0.09,
                cutoff: 580,
                delay: d + 0.015,
            });
        }
        this._triggerMatteWhoosh({
            duration: 0.48,
            gain: 0.14,
            filterType: 'lowpass',
            freqStart: 280,
            freqEnd: 150,
            q: 0.75,
        });
    }

    playChestBurstOpen() {
        if (!this._canPlaySfx()) return;
        // Warmer matter Explosions- und Oeffnungs-Klang der Belohnungstruhe
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 92,
            freqEnd: 35,
            duration: 0.46,
            gain: 0.42,
            cutoff: 250,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 146.83,
            freqEnd: 58,
            duration: 0.30,
            gain: 0.28,
            cutoff: 430,
        });
        this._triggerMatteWhoosh({
            duration: 0.48,
            gain: 0.36,
            filterType: 'lowpass',
            freqStart: 540,
            freqEnd: 130,
            q: 0.85,
        });
        this.playChestOpenFanfare();
    }

    playChestOpenFanfare() {
        if (!this._canPlaySfx()) return;
        // Edler, tiefpassgefilterter Dunkelgold-Akkord (G3, D4, G4, A4, D5) ohne schrille Spitzen
        const notes = [196.00, 293.66, 392.00, 440.00, 587.33];
        for (let i = 0; i < notes.length; i++) {
            const f = notes[i];
            const d = 0.04 + i * 0.075;
            this._triggerMatteTone({
                type: 'sine',
                freqStart: f,
                freqEnd: f * 0.998,
                duration: 0.72,
                gain: 0.17 - i * 0.018,
                cutoff: 1180,
                delay: d,
            });
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: f * 0.5,
                freqEnd: f * 0.5,
                duration: 0.58,
                gain: 0.085,
                cutoff: 680,
                delay: d,
            });
        }
    }

    playSparkCollect(stepIndex = 0) {
        if (!this._canPlaySfx()) return;
        const now = (typeof performance !== 'undefined') ? performance.now() : Date.now();
        if (this._lastSparkCollectAt && (now - this._lastSparkCollectAt) < 42) {
            return;
        }
        this._lastSparkCollectAt = now;

        // Sanftes, samtig-mattes Einsammel-Klingen der Boden-Funken (warme Pentatonik, tiefpassgefiltert)
        const scale = [293.66, 329.63, 392.00, 440.00, 523.25, 587.33];
        const idx = Math.abs(Math.round(Number(stepIndex) || 0)) % scale.length;
        const baseFreq = scale[idx];

        this._triggerMatteTone({
            type: 'sine',
            freqStart: baseFreq,
            freqEnd: baseFreq * 1.012,
            duration: 0.24,
            gain: 0.12,
            cutoff: 1040,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: baseFreq * 0.5,
            freqEnd: baseFreq * 0.5,
            duration: 0.19,
            gain: 0.065,
            cutoff: 620,
            delay: 0.012,
        });
    }

    playTeleporter() {
        if (!this._canPlaySfx()) return;
        // Tiefer, weich gefilterter Sub-Impuls + edle dunkle Kristall-Resonanz beim Portal-/Raumwechsel
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 64,
            freqEnd: 34,
            duration: 0.56,
            gain: 0.38,
            cutoff: 210,
        });
        this._triggerMatteWhoosh({
            duration: 0.54,
            gain: 0.28,
            filterType: 'bandpass',
            freqStart: 165,
            freqEnd: 410,
            q: 1.05,
        });
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 196.00,
            freqEnd: 194.50,
            duration: 0.62,
            gain: 0.14,
            cutoff: 740,
            delay: 0.04,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 293.66,
            freqEnd: 292.00,
            duration: 0.58,
            gain: 0.10,
            cutoff: 820,
            delay: 0.09,
        });
    }

    playPortalPass() {
        this.playTeleporter();
    }

    playGateLocked() {
        if (!this._canPlaySfx()) return;
        const now = (typeof performance !== 'undefined') ? performance.now() : Date.now();
        if (this._lastGateLockedAt && (now - this._lastGateLockedAt) < 420) return;
        this._lastGateLockedAt = now;

        // Schweres mattes Manufaktur-Vorhaengeschloss
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 92,
            freqEnd: 48,
            duration: 0.18,
            gain: 0.26,
            cutoff: 340,
        });
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 168,
            freqEnd: 112,
            duration: 0.12,
            gain: 0.14,
            cutoff: 520,
            delay: 0.055,
        });
    }

    playEpicGateOpen() {
        if (!this._canPlaySfx()) return;
        // Monumentales Obsidian-Gold-Tor schwingt schwer und majestätisch auf
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 54,
            freqEnd: 32,
            duration: 0.85,
            gain: 0.40,
            cutoff: 190,
        });
        this._triggerMatteWhoosh({
            duration: 0.82,
            gain: 0.30,
            filterType: 'lowpass',
            freqStart: 240,
            freqEnd: 95,
            q: 0.9,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 146.83,
            freqEnd: 220.00,
            duration: 0.70,
            gain: 0.15,
            cutoff: 680,
            delay: 0.10,
        });
    }

    playUpdraftWhoosh() {
        if (!this._canPlaySfx()) return;
        const now = (typeof performance !== 'undefined') ? performance.now() : Date.now();
        if (this._lastUpdraftAt && (now - this._lastUpdraftAt) < 480) return;
        this._lastUpdraftAt = now;

        this._triggerMatteWhoosh({
            duration: 0.42,
            gain: 0.22,
            filterType: 'bandpass',
            freqStart: 150,
            freqEnd: 360,
            q: 0.95,
        });
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 110,
            freqEnd: 196,
            duration: 0.34,
            gain: 0.14,
            cutoff: 560,
        });
    }

    playShieldDeflect() {
        if (!this._canPlaySfx()) return;
        const now = (typeof performance !== 'undefined') ? performance.now() : Date.now();
        if (this._lastShieldDeflectAt && (now - this._lastShieldDeflectAt) < 360) return;
        this._lastShieldDeflectAt = now;

        this._triggerMatteTone({
            type: 'sine',
            freqStart: 164.81,
            freqEnd: 98.00,
            duration: 0.24,
            gain: 0.22,
            cutoff: 640,
        });
    }

    playShieldShatter() {
        if (!this._canPlaySfx()) return;
        // Kraftvoller Katapult-Impuls + edles kristallines Zerbersten des Aegis-Schilds
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 72,
            freqEnd: 186,
            duration: 0.34,
            gain: 0.38,
            cutoff: 540,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 136,
            freqEnd: 44,
            duration: 0.38,
            gain: 0.30,
            cutoff: 380,
        });
        this._triggerMatteWhoosh({
            duration: 0.46,
            gain: 0.36,
            filterType: 'bandpass',
            freqStart: 680,
            freqEnd: 210,
            q: 1.05,
        });
        const shards = [587.33, 493.88, 392.00, 311.13, 246.94];
        for (let i = 0; i < shards.length; i++) {
            const f = shards[i];
            this._triggerMatteTone({
                type: i % 2 === 0 ? 'triangle' : 'sine',
                freqStart: f,
                freqEnd: f * 0.74,
                duration: 0.16,
                gain: 0.15 - i * 0.018,
                cutoff: 1280,
                delay: 0.02 + i * 0.042,
            });
        }
    }

    playShockRingHit(kind = 'gold') {
        if (!this._canPlaySfx()) return;
        const isBlack = kind === 'black';
        if (isBlack) {
            this._triggerMatteTone({
                type: 'sine',
                freqStart: 118,
                freqEnd: 34,
                duration: 0.40,
                gain: 0.42,
                cutoff: 280,
            });
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: 164,
                freqEnd: 52,
                duration: 0.28,
                gain: 0.28,
                cutoff: 460,
            });
            this._triggerMatteWhoosh({
                duration: 0.38,
                gain: 0.34,
                filterType: 'lowpass',
                freqStart: 440,
                freqEnd: 110,
                q: 0.95,
            });
        } else {
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: 224,
                freqEnd: 78,
                duration: 0.28,
                gain: 0.32,
                cutoff: 640,
            });
            this._triggerMatteTone({
                type: 'sine',
                freqStart: 330,
                freqEnd: 165,
                duration: 0.22,
                gain: 0.18,
                cutoff: 920,
                delay: 0.02,
            });
            this._triggerMatteWhoosh({
                duration: 0.30,
                gain: 0.28,
                filterType: 'bandpass',
                freqStart: 580,
                freqEnd: 195,
                q: 1.1,
            });
        }
    }

    playProductTransition() {
        if (!this._canPlaySfx()) return;
        // Sanft gleitender Elfenbein-Gold-Uebergangsklang (~680 ms) beim Wechsel zum Produkt-Konfigurator
        this._triggerMatteWhoosh({
            duration: 0.58,
            gain: 0.20,
            filterType: 'bandpass',
            freqStart: 220,
            freqEnd: 540,
            q: 0.95,
        });
        const notes = [261.63, 329.63, 392.00, 493.88, 587.33];
        for (let i = 0; i < notes.length; i++) {
            const f = notes[i];
            const d = i * 0.065;
            this._triggerMatteTone({
                type: 'sine',
                freqStart: f,
                freqEnd: f * 1.002,
                duration: 0.52,
                gain: 0.13 - i * 0.014,
                cutoff: 1240,
                delay: d,
            });
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: f * 0.5,
                freqEnd: f * 0.5,
                duration: 0.42,
                gain: 0.06,
                cutoff: 680,
                delay: d + 0.015,
            });
        }
    }

    updateLavaAmbience(distanceMeters, active = true) {
        const shouldRun = Boolean(active) && this._canPlaySfx() && distanceMeters < 32.0;
        if (!shouldRun) {
            if (this._lavaRumblerNodes && this._ctx) {
                try {
                    this._lavaRumblerNodes.gain.gain.setTargetAtTime(0.0001, this._ctx.currentTime, 0.08);
                    const toStop = this._lavaRumblerNodes;
                    this._lavaRumblerNodes = null;
                    setTimeout(() => {
                        try {
                            toStop.noise.stop();
                            toStop.subOsc.stop();
                            toStop.noise.disconnect();
                            toStop.subOsc.disconnect();
                            toStop.filter.disconnect();
                            toStop.gain.disconnect();
                        } catch (e) {}
                    }, 220);
                } catch (e) {
                    this._lavaRumblerNodes = null;
                }
            }
            return;
        }

        if (!this._ctx || this._ctx.state !== 'running') {
            return;
        }

        const proximity = clampNum(1.0 - (distanceMeters / 32.0), 0, 1);
        const targetGain = 0.003 + proximity * proximity * 0.072;

        if (!this._lavaRumblerNodes && this._warmNoiseBuffer && this._masterSfxGain) {
            try {
                const noise = this._ctx.createBufferSource();
                noise.buffer = this._warmNoiseBuffer;
                noise.loop = true;

                const subOsc = this._ctx.createOscillator();
                subOsc.type = 'sine';
                subOsc.frequency.value = 42;

                const subGain = this._ctx.createGain();
                subGain.gain.value = 0.38;

                const filter = this._ctx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.value = 96;
                filter.Q.value = 0.78;

                const gain = this._ctx.createGain();
                gain.gain.value = 0.0001;

                noise.connect(filter);
                subOsc.connect(subGain);
                subGain.connect(filter);
                filter.connect(gain);
                gain.connect(this._masterSfxGain);

                noise.start();
                subOsc.start();

                this._lavaRumblerNodes = { noise, subOsc, subGain, filter, gain };
            } catch (e) {
                this._lavaRumblerNodes = null;
                return;
            }
        }

        if (this._lavaRumblerNodes) {
            this._lavaRumblerNodes.gain.gain.setTargetAtTime(targetGain, this._ctx.currentTime, 0.14);
        }
    }

    dispose() {
        this.onLobbyClose();
        if (this._serverSyncTimer) {
            clearTimeout(this._serverSyncTimer);
            this._serverSyncTimer = null;
        }
        if (this._fallbackBgmEl) {
            try {
                this._fallbackBgmEl.pause();
                this._fallbackBgmEl.removeAttribute('src');
            } catch (e) {}
            this._fallbackBgmEl = null;
        }
        if (this._ctx) {
            try {
                this._ctx.close();
            } catch (e) {}
            this._ctx = null;
            this._masterSfxGain = null;
            this._warmNoiseBuffer = null;
        }
        this._listeners.clear();
    }
}

let sharedLobbyAudioSingleton = null;

export function getLobbyAudioInstance(options = {}) {
    if (!sharedLobbyAudioSingleton) {
        sharedLobbyAudioSingleton = new SeelenfunkeLobbyAudio(options);
    } else if (options.isAuthenticated !== undefined) {
        sharedLobbyAudioSingleton.setAuthenticated(options.isAuthenticated);
    }
    return sharedLobbyAudioSingleton;
}

if (typeof window !== 'undefined') {
    window.SeelenfunkeLobbyAudio = SeelenfunkeLobbyAudio;
    window.getSeelenfunkeLobbyAudio = getLobbyAudioInstance;
}
