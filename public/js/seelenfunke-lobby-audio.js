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

        this.bgmVolume = 30;
        this.bgmMuted = true;
        this.sfxVolume = 50;
        this.sfxMuted = false;

        this._fallbackBgmEl = null;
        this._serverSyncTimer = null;
        this._listeners = new Set();

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
            if (typeof ev.detail.muted === 'boolean') {
                this.bgmMuted = ev.detail.muted;
            }
            this._emitChange();
        };

        this._boundGestureUnlock = () => {
            this._unlockContextIfNeeded();
            if (this.lobbyOpen && !this.bgmMuted && this.bgmVolume > 0 && !this._hasDashboardBgm()) {
                this._syncFallbackBgmPlayback();
            }
        };

        this._loadStoredSettings();
    }

    _hasDashboardBgm() {
        return typeof window !== 'undefined' && Boolean(window.SfFunkiBgm);
    }

    _loadStoredSettings() {
        if (typeof window === 'undefined') return;

        if (this._hasDashboardBgm()) {
            const ctrl = window.SfFunkiBgm;
            this.bgmVolume = Math.round(clampNum(ctrl.volume ?? 30, 0, 100));
            this.bgmMuted = Boolean(ctrl.muted);
        } else {
            try {
                const sessionOptIn = sessionStorage.getItem(SS_BGM_SESSION) === '1';
                const rawVol = localStorage.getItem(LS_BGM_VOL);
                const rawMuted = localStorage.getItem(LS_BGM_MUTED);
                if (rawVol !== null && rawVol !== '') {
                    this.bgmVolume = Math.round(clampNum(parseInt(rawVol, 10), 0, 100));
                } else {
                    this.bgmVolume = 30;
                }
                if (sessionOptIn) {
                    this.bgmMuted = rawMuted === null ? false : (rawMuted === '1' || rawMuted === 'true');
                } else {
                    this.bgmMuted = true;
                }
            } catch (e) {
                this.bgmVolume = 30;
                this.bgmMuted = true;
            }
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
        if (this._hasDashboardBgm()) {
            this.bgmVolume = Math.round(clampNum(window.SfFunkiBgm.volume ?? this.bgmVolume, 0, 100));
            this.bgmMuted = Boolean(window.SfFunkiBgm.muted);
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

    onLobbyOpen(isAuthenticated = undefined) {
        if (isAuthenticated !== undefined) {
            this.isAuthenticated = Boolean(isAuthenticated);
        }
        this.lobbyOpen = true;
        this._loadStoredSettings();

        if (typeof window !== 'undefined') {
            window.addEventListener('sf-funki-bgm-state', this._boundBgmEvent);
            window.addEventListener('pointerdown', this._boundGestureUnlock, { passive: true });
            window.addEventListener('keydown', this._boundGestureUnlock, { passive: true });
        }

        if (this._hasDashboardBgm()) {
            window.SfFunkiBgm.onModalOpen();
        } else if (!this.bgmMuted && this.bgmVolume > 0) {
            this._syncFallbackBgmPlayback();
        }

        this._emitChange();
    }

    onLobbyClose() {
        this.lobbyOpen = false;
        this.updateLavaAmbience(999, false);

        if (typeof window !== 'undefined') {
            window.removeEventListener('sf-funki-bgm-state', this._boundBgmEvent);
            window.removeEventListener('pointerdown', this._boundGestureUnlock);
            window.removeEventListener('keydown', this._boundGestureUnlock);
        }

        if (this._hasDashboardBgm()) {
            window.SfFunkiBgm.onModalClose();
        } else if (this._fallbackBgmEl) {
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
        if (this._fallbackBgmEl || typeof Audio === 'undefined') {
            return this._fallbackBgmEl;
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
                p.catch(() => {});
            }
        }
    }

    _persistFallbackBgmSettings(commitServer = false) {
        try {
            localStorage.setItem(LS_BGM_VOL, String(this.bgmVolume));
            localStorage.setItem(LS_BGM_MUTED, this.bgmMuted ? '1' : '0');
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
        if (this._hasDashboardBgm()) {
            window.SfFunkiBgm.setMuted(nextMuted, Boolean(commit));
            this.bgmVolume = Math.round(clampNum(window.SfFunkiBgm.volume, 0, 100));
            this.bgmMuted = Boolean(window.SfFunkiBgm.muted);
        } else {
            this.bgmMuted = nextMuted;
            if (!this.bgmMuted && this.bgmVolume <= 0) {
                this.bgmVolume = 30;
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

    playChestOpenFanfare() {
        if (!this._canPlaySfx()) return;
        // Warmer G-Dur Celesta-Gold-Akkord (G4, H4, D5, G5) mit sanftem Tiefpass
        const notes = [392.00, 493.88, 587.33, 783.99];
        for (let i = 0; i < notes.length; i++) {
            const f = notes[i];
            const d = i * 0.085;
            this._triggerMatteTone({
                type: 'sine',
                freqStart: f,
                freqEnd: f * 0.998,
                duration: 0.68,
                gain: 0.20 - i * 0.02,
                cutoff: 1850,
                delay: d,
            });
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: f * 0.5,
                freqEnd: f * 0.5,
                duration: 0.55,
                gain: 0.08,
                cutoff: 920,
                delay: d,
            });
        }
    }

    updateLavaAmbience(distanceMeters, active = true) {
        const shouldRun = Boolean(active) && this._canPlaySfx() && distanceMeters < 24.0;
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

        const proximity = clampNum(1.0 - (distanceMeters / 24.0), 0, 1);
        const targetGain = 0.002 + proximity * proximity * 0.075;

        if (!this._lavaRumblerNodes && this._warmNoiseBuffer && this._masterSfxGain) {
            try {
                const noise = this._ctx.createBufferSource();
                noise.buffer = this._warmNoiseBuffer;
                noise.loop = true;

                const subOsc = this._ctx.createOscillator();
                subOsc.type = 'sine';
                subOsc.frequency.value = 46;

                const subGain = this._ctx.createGain();
                subGain.gain.value = 0.35;

                const filter = this._ctx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.value = 112;
                filter.Q.value = 0.8;

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
