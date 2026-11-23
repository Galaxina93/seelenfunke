/*!
 * Mein-Seelenfunke · Autarke 3D-Lobby Audio-Engine (2-Spur Zonen-Musik & Matte Studio-Soundeffekte)
 * Eigenstaendige Verwaltung von Calm-Lobby-Track & Action-Magma-Track mit sanftem 700-ms-Crossfade.
 * 0 Kopplung an globale Shop-Docks, 0 Ton ausserhalb der 3D-Lobby, 0 externe CDNs, Standard-Lautstaerke 15 %,
 * rauschfreie Playback-Buffer-Architektur mit Auto-Idle-Suspend fuer Android & Mobile.
 */

export const LOBBY_BGM_TRACK_URL = '/shop/gamification/audio/bgm_funki_game.mp3?v=20261122v1';
export const LOBBY_BGM_MAGMA_TRACK_URL = '/shop/gamification/audio/bgm_funki_magma_action.mp3?v=20261122v1';

export const LOBBY_SFX_URLS = {
    shield: '/shop/customer/gamification/sounds/shield_active.mp3',
    sprint: '/shop/customer/gamification/sounds/teleport_active.mp3',
};

const LS_BGM_VOL = 'sf_funki_audio_volume';
const LS_BGM_MUTED = 'sf_funki_audio_muted';
const LS_LOBBY_EXPLICIT_MUTED = 'sf_lobby_bgm_explicit_muted';
const LS_LOBBY_VOL_V2_INIT = 'sf_lobby_vol_v2_init';
const LS_LOBBY_VOL_V3_INIT = 'sf_lobby_vol_v3_init';
const LS_SFX_VOL = 'sf_lobby_sfx_volume';
const LS_SFX_MUTED = 'sf_lobby_sfx_muted';
const DEFAULT_LOBBY_BGM_VOLUME = 15;
const DEFAULT_LOBBY_SFX_VOLUME = 15;
const BGM_CROSSFADE_DURATION_MS = 700;
const LOBBY_DOM_WATCHDOG_INTERVAL_MS = 400;
const LOBBY_OPEN_GRACE_MS = 1200;
const SFX_IDLE_SUSPEND_DELAY_MS = 1800;

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

function normalizeAudioZone(rawZone) {
    const z = String(rawZone || 'gallery').toLowerCase();
    if (z === 'lava') return 'lava';
    if (z === 'heroes' || z === 'hall') return 'heroes';
    return 'gallery';
}

function ensureGlobalLobbyAudioRegistry() {
    if (typeof window === 'undefined') return null;
    if (!(window.__sfLobbyAudioRegistry instanceof Set)) {
        const nextSet = new Set();
        if (Array.isArray(window.__sfLobbyAudioRegistry)) {
            for (let i = 0; i < window.__sfLobbyAudioRegistry.length; i++) {
                if (window.__sfLobbyAudioRegistry[i]) {
                    nextSet.add(window.__sfLobbyAudioRegistry[i]);
                }
            }
        }
        window.__sfLobbyAudioRegistry = nextSet;
    }
    if (window.__sfPrewarmLobbyBgmEl) {
        window.__sfLobbyAudioRegistry.add(window.__sfPrewarmLobbyBgmEl);
    }
    return window.__sfLobbyAudioRegistry;
}

export function registerLobbyAudioElement(el) {
    if (!el) return el;
    const reg = ensureGlobalLobbyAudioRegistry();
    if (reg) {
        reg.add(el);
    }
    return el;
}

function silenceSingleAudioElement(el, resetTime = true) {
    if (!el) return;
    try {
        el.pause();
    } catch (e) {}
    try {
        el.volume = 0;
    } catch (e) {}
    if (resetTime) {
        try {
            el.currentTime = 0;
        } catch (e) {}
    }
}

function isModalNodeOpenAndVisible(el, requireIsOpenClass = false) {
    if (!el || typeof window === 'undefined' || typeof document === 'undefined') return false;
    if (!document.documentElement || !document.documentElement.contains(el)) return false;
    if (el.classList) {
        if (el.classList.contains('is-closed')) return false;
        if (requireIsOpenClass && !el.classList.contains('is-open')) return false;
    }
    const cs = typeof window.getComputedStyle === 'function' ? window.getComputedStyle(el) : null;
    if (cs && (cs.display === 'none' || cs.visibility === 'hidden')) {
        return false;
    }
    return el.offsetWidth > 0 && el.offsetHeight > 0;
}

export function isAny3dLobbyModalVisibleInDom() {
    if (typeof document === 'undefined' || typeof window === 'undefined') return false;

    const showroomModals = document.querySelectorAll('[data-sf-3d-lobby-modal="showroom"], #sf-louvre-showroom-modal');
    for (let i = 0; i < showroomModals.length; i++) {
        if (isModalNodeOpenAndVisible(showroomModals[i], false)) {
            return true;
        }
    }

    const standaloneModals = document.querySelectorAll(
        '[data-sf-3d-lobby-modal="heroes"].is-open, #sf-unified-lobby-modal.is-open, #sf-user-lobby-3d-modal.is-open'
    );
    for (let i = 0; i < standaloneModals.length; i++) {
        if (isModalNodeOpenAndVisible(standaloneModals[i], true)) {
            return true;
        }
    }

    return false;
}

function clearGlobalLobbyWatchdogTimer() {
    if (typeof window === 'undefined') return;
    if (window.__sfLobbyAudioWatchdogTimer) {
        clearInterval(window.__sfLobbyAudioWatchdogTimer);
        window.__sfLobbyAudioWatchdogTimer = null;
    }
}

export class SeelenfunkeLobbyAudio {
    constructor(options = {}) {
        this.isAuthenticated = Boolean(options.isAuthenticated);
        this.lobbyOpen = false;
        this.lobbyActive = false;
        this.currentZone = normalizeAudioZone(options.initialZone || 'gallery');

        this.bgmVolume = DEFAULT_LOBBY_BGM_VOLUME;
        this.bgmMuted = false;
        this.sfxVolume = DEFAULT_LOBBY_SFX_VOLUME;
        this.sfxMuted = false;

        this._calmBgmEl = null;
        this._magmaBgmEl = null;
        this._crossfadeTimer = null;
        this._watchdogTimer = null;
        this._openedGraceUntil = 0;
        this._modalConfirmedOpen = false;
        this._inCloseSequence = false;
        this._serverSyncTimer = null;
        this._listeners = new Set();
        this._oneShotUnlockBound = null;

        this._ctx = null;
        this._masterSfxGain = null;
        this._warmNoiseBuffer = null;
        this._idleSuspendTimer = null;

        this._lavaRumblerNodes = null;
        this._lastFootstepAt = 0;

        this._mp3Pool = {
            shield: null,
            sprint: null,
        };

        this._boundGestureUnlock = () => {
            if (!this.lobbyOpen || !this.lobbyActive) return;
            if (typeof document !== 'undefined' && document.hidden) return;
            if (!isAny3dLobbyModalVisibleInDom() && Date.now() >= this._effectiveGraceUntil()) {
                forceStopAllLobbyAudio();
                return;
            }
            if (!this.bgmMuted && this.bgmVolume > 0 && !this._crossfadeTimer) {
                const activeEl = this._activeTrackElement();
                if (activeEl && activeEl.paused) {
                    this._syncActiveBgmPlayback();
                }
            }
        };

        ensureGlobalLobbyAudioRegistry();
        this._loadStoredSettings();
    }

    _isExplicitlyMutedInStorage() {
        if (typeof window === 'undefined') return false;
        try {
            const rawLobbyMuted = localStorage.getItem(LS_LOBBY_EXPLICIT_MUTED);
            if (rawLobbyMuted !== null && rawLobbyMuted !== '') {
                return rawLobbyMuted === '1' || rawLobbyMuted === 'true';
            }
            const rawMuted = localStorage.getItem(LS_BGM_MUTED);
            if (rawMuted !== null && rawMuted !== '') {
                return rawMuted === '1' || rawMuted === 'true';
            }
        } catch (e) {
            return false;
        }
        return false;
    }

    _loadStoredSettings() {
        if (typeof window === 'undefined') return;

        const explicitMuted = this._isExplicitlyMutedInStorage();
        let hasV2Settings = false;
        try {
            hasV2Settings = localStorage.getItem('sf_lobby_user_settings_v2') !== null
                || localStorage.getItem(LS_LOBBY_VOL_V2_INIT) === '1';
            if (!hasV2Settings) {
                localStorage.setItem(LS_LOBBY_VOL_V2_INIT, '1');
                localStorage.setItem(LS_LOBBY_VOL_V3_INIT, '1');
                localStorage.setItem(LS_BGM_VOL, String(DEFAULT_LOBBY_BGM_VOLUME));
                localStorage.setItem(LS_SFX_VOL, String(DEFAULT_LOBBY_SFX_VOLUME));
            } else if (localStorage.getItem(LS_LOBBY_VOL_V3_INIT) !== '1') {
                // Einmaliges Upgrade des bisherigen 5-%-Auto-Defaults auf gut wahrnehmbare 15 %
                localStorage.setItem(LS_LOBBY_VOL_V3_INIT, '1');
                if (!explicitMuted) {
                    const prevBgm = localStorage.getItem(LS_BGM_VOL);
                    if (prevBgm === null || prevBgm === '' || prevBgm === '5') {
                        localStorage.setItem(LS_BGM_VOL, String(DEFAULT_LOBBY_BGM_VOLUME));
                    }
                    const prevSfx = localStorage.getItem(LS_SFX_VOL);
                    if (prevSfx === null || prevSfx === '' || prevSfx === '5') {
                        localStorage.setItem(LS_SFX_VOL, String(DEFAULT_LOBBY_SFX_VOLUME));
                    }
                    const rawUserJson = localStorage.getItem('sf_lobby_user_settings_v2');
                    if (rawUserJson) {
                        const parsedUser = JSON.parse(rawUserJson);
                        if (parsedUser && typeof parsedUser === 'object' && Number(parsedUser.volume) === 5) {
                            parsedUser.volume = DEFAULT_LOBBY_BGM_VOLUME;
                            localStorage.setItem('sf_lobby_user_settings_v2', JSON.stringify(parsedUser));
                        }
                    }
                }
            }
        } catch (e) {}

        try {
            const rawVol = hasV2Settings ? localStorage.getItem(LS_BGM_VOL) : String(DEFAULT_LOBBY_BGM_VOLUME);
            if (rawVol !== null && rawVol !== '') {
                const parsed = Number(rawVol);
                if (Number.isFinite(parsed)) {
                    const scaled = (parsed > 0 && parsed <= 1 && String(rawVol).indexOf('.') !== -1)
                        ? Math.round(parsed * 100)
                        : Math.round(parsed);
                    this.bgmVolume = clampNum(scaled, 0, 100);
                } else {
                    this.bgmVolume = DEFAULT_LOBBY_BGM_VOLUME;
                }
            } else {
                this.bgmVolume = DEFAULT_LOBBY_BGM_VOLUME;
            }
        } catch (e) {
            this.bgmVolume = DEFAULT_LOBBY_BGM_VOLUME;
        }

        this.bgmMuted = Boolean(explicitMuted || this.bgmVolume <= 0);

        try {
            const rawSfxVol = hasV2Settings ? localStorage.getItem(LS_SFX_VOL) : String(DEFAULT_LOBBY_SFX_VOLUME);
            const rawSfxMuted = localStorage.getItem(LS_SFX_MUTED);
            if (rawSfxVol !== null && rawSfxVol !== '') {
                this.sfxVolume = Math.round(clampNum(parseInt(rawSfxVol, 10), 0, 100));
            } else {
                this.sfxVolume = DEFAULT_LOBBY_SFX_VOLUME;
            }
            if (rawSfxMuted !== null) {
                this.sfxMuted = rawSfxMuted === '1' || rawSfxMuted === 'true';
            } else {
                this.sfxMuted = false;
            }
        } catch (e) {
            this.sfxVolume = DEFAULT_LOBBY_SFX_VOLUME;
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
        const bgmAudible = !this.bgmMuted && this.bgmVolume > 0;
        const sfxAudible = !this.sfxMuted && this.sfxVolume > 0;
        return {
            bgmVolume: this.bgmVolume,
            bgmMuted: this.bgmMuted,
            sfxVolume: this.sfxVolume,
            sfxMuted: this.sfxMuted,
            allMuted: !bgmAudible && !sfxAudible,
            zone: this.currentZone,
        };
    }

    _cancelCrossfade() {
        if (this._crossfadeTimer) {
            clearInterval(this._crossfadeTimer);
            this._crossfadeTimer = null;
        }
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
            if (!this.lobbyOpen || !this.lobbyActive || this.bgmMuted || this.bgmVolume <= 0) return;
            this._unlockContextIfNeeded();
            this._syncActiveBgmPlayback();
        };
        window.addEventListener('pointerdown', this._oneShotUnlockBound, { once: true, passive: true });
        window.addEventListener('keydown', this._oneShotUnlockBound, { once: true, passive: true });
    }

    _effectiveGraceUntil() {
        const externalGrace = (typeof window !== 'undefined' && Number.isFinite(Number(window.__sfLobbyAudioGraceUntil)))
            ? Number(window.__sfLobbyAudioGraceUntil)
            : 0;
        return Math.max(this._openedGraceUntil || 0, externalGrace);
    }

    _markLobbyOpeningGrace() {
        const until = Date.now() + LOBBY_OPEN_GRACE_MS;
        this._openedGraceUntil = until;
        this._modalConfirmedOpen = isAny3dLobbyModalVisibleInDom();
        if (typeof window !== 'undefined') {
            window.__sfLobbyAudioGraceUntil = until;
        }
    }

    _stopWatchdog() {
        if (this._watchdogTimer) {
            clearInterval(this._watchdogTimer);
            this._watchdogTimer = null;
        }
        clearGlobalLobbyWatchdogTimer();
    }

    _isAnyRegisteredAudioPlaying() {
        const candidates = [this._calmBgmEl, this._magmaBgmEl];
        if (typeof window !== 'undefined' && window.__sfPrewarmLobbyBgmEl) {
            candidates.push(window.__sfPrewarmLobbyBgmEl);
        }
        const reg = ensureGlobalLobbyAudioRegistry();
        if (reg) {
            reg.forEach((el) => {
                if (el) candidates.push(el);
            });
        }
        for (let i = 0; i < candidates.length; i++) {
            const el = candidates[i];
            if (el && !el.paused) return true;
        }
        return false;
    }

    _startWatchdog() {
        if (typeof window === 'undefined') return;
        this._stopWatchdog();
        const timer = setInterval(() => {
            if (typeof document !== 'undefined' && document.hidden === true) {
                forceStopAllLobbyAudio();
                return;
            }
            const visible = isAny3dLobbyModalVisibleInDom();
            if (visible) {
                this._modalConfirmedOpen = true;
                return;
            }
            if (this._modalConfirmedOpen || Date.now() >= this._effectiveGraceUntil()) {
                forceStopAllLobbyAudio();
                return;
            }
            if (!this.lobbyOpen && !this.lobbyActive && !this._isAnyRegisteredAudioPlaying()) {
                this._stopWatchdog();
            }
        }, LOBBY_DOM_WATCHDOG_INTERVAL_MS);
        this._watchdogTimer = timer;
        window.__sfLobbyAudioWatchdogTimer = timer;
    }

    _adoptPrewarmBgmIfPresent() {
        if (typeof window === 'undefined' || !window.__sfPrewarmLobbyBgmEl) return;
        const preEl = window.__sfPrewarmLobbyBgmEl;
        window.__sfPrewarmLobbyBgmEl = null;
        registerLobbyAudioElement(preEl);
        if (!this._calmBgmEl) {
            preEl.loop = true;
            try {
                preEl.preservesPitch = true;
                preEl.mozPreservesPitch = true;
                preEl.webkitPreservesPitch = true;
            } catch (e) {}
            this._calmBgmEl = preEl;
        } else if (this._calmBgmEl !== preEl) {
            silenceSingleAudioElement(preEl, true);
        }
    }

    _ensureCalmBgmElement() {
        this._adoptPrewarmBgmIfPresent();
        if (this._calmBgmEl) return this._calmBgmEl;
        if (typeof Audio === 'undefined') return null;
        const el = new Audio(LOBBY_BGM_TRACK_URL);
        el.loop = true;
        el.preload = 'auto';
        el.volume = 0;
        try {
            el.preservesPitch = true;
            el.mozPreservesPitch = true;
            el.webkitPreservesPitch = true;
        } catch (e) {}
        registerLobbyAudioElement(el);
        this._calmBgmEl = el;
        return el;
    }

    _ensureMagmaBgmElement() {
        if (this._magmaBgmEl) return this._magmaBgmEl;
        if (typeof Audio === 'undefined') return null;
        const el = new Audio(LOBBY_BGM_MAGMA_TRACK_URL);
        el.loop = true;
        el.preload = 'none';
        el.volume = 0;
        try {
            el.preservesPitch = true;
            el.mozPreservesPitch = true;
            el.webkitPreservesPitch = true;
        } catch (e) {}
        registerLobbyAudioElement(el);
        this._magmaBgmEl = el;
        return el;
    }

    _activeTrackElement() {
        this._adoptPrewarmBgmIfPresent();
        return this.currentZone === 'lava'
            ? this._ensureMagmaBgmElement()
            : this._ensureCalmBgmElement();
    }

    _inactiveTrackElement() {
        this._adoptPrewarmBgmIfPresent();
        return this.currentZone === 'lava'
            ? this._calmBgmEl
            : this._magmaBgmEl;
    }

    _targetBgmGain() {
        if (!this.lobbyOpen || !this.lobbyActive || this.bgmMuted || this.bgmVolume <= 0) return 0;
        return clampNum((this.bgmVolume / 100) * 0.50, 0, 0.50);
    }

    _stopBothBgmElements(resetTime = true) {
        this._cancelCrossfade();
        const visited = new Set();
        const pushEl = (el) => {
            if (el) visited.add(el);
        };
        pushEl(this._calmBgmEl);
        pushEl(this._magmaBgmEl);
        if (typeof window !== 'undefined' && window.__sfPrewarmLobbyBgmEl) {
            const preEl = window.__sfPrewarmLobbyBgmEl;
            registerLobbyAudioElement(preEl);
            pushEl(preEl);
            if (!this._calmBgmEl) {
                preEl.loop = true;
                this._calmBgmEl = preEl;
            }
            window.__sfPrewarmLobbyBgmEl = null;
        }
        const reg = ensureGlobalLobbyAudioRegistry();
        if (reg) {
            reg.forEach((el) => {
                if (!el) return;
                if (el === this._mp3Pool.shield || el === this._mp3Pool.sprint) {
                    if (resetTime) pushEl(el);
                    return;
                }
                pushEl(el);
            });
        }
        visited.forEach((el) => {
            silenceSingleAudioElement(el, resetTime);
        });
    }

    _syncActiveBgmPlayback() {
        this._adoptPrewarmBgmIfPresent();
        if (!this.lobbyOpen || !this.lobbyActive || this.bgmMuted || this.bgmVolume <= 0) {
            this._stopBothBgmElements(false);
            return;
        }
        if (typeof document !== 'undefined' && document.hidden === true) {
            this._stopBothBgmElements(false);
            return;
        }
        this._cancelCrossfade();
        this._startWatchdog();
        const activeEl = this._activeTrackElement();
        const inactiveEl = this._inactiveTrackElement();
        if (inactiveEl) {
            try {
                inactiveEl.volume = 0;
                if (!inactiveEl.paused) inactiveEl.pause();
            } catch (e) {}
        }
        if (!activeEl) return;
        activeEl.loop = true;
        activeEl.volume = this._targetBgmGain();
        if (activeEl.paused) {
            const p = activeEl.play();
            if (p && typeof p.catch === 'function') {
                p.catch(() => {
                    this._registerOneShotUnlock();
                });
            }
        }
    }

    _crossfadeToZoneTrack(toLava, durationMs = BGM_CROSSFADE_DURATION_MS) {
        this._cancelCrossfade();
        this._adoptPrewarmBgmIfPresent();
        if (!this.lobbyOpen || !this.lobbyActive || this.bgmMuted || this.bgmVolume <= 0) {
            this._stopBothBgmElements(false);
            return;
        }
        if (typeof document !== 'undefined' && document.hidden === true) {
            this._stopBothBgmElements(false);
            return;
        }
        this._startWatchdog();

        const inEl = toLava ? this._ensureMagmaBgmElement() : this._ensureCalmBgmElement();
        const outEl = toLava ? this._calmBgmEl : this._magmaBgmEl;
        if (!inEl) return;

        const targetVol = this._targetBgmGain();
        const outStartVol = outEl ? clampNum(outEl.volume || 0, 0, 0.50) : 0;
        const inStartVol = clampNum(inEl.volume || 0, 0, targetVol);

        inEl.loop = true;
        inEl.volume = inStartVol;
        if (inEl.paused) {
            const p = inEl.play();
            if (p && typeof p.catch === 'function') {
                p.catch(() => {
                    this._registerOneShotUnlock();
                });
            }
        }

        if (!outEl || outEl.paused || durationMs <= 0) {
            if (outEl) {
                try {
                    outEl.volume = 0;
                    outEl.pause();
                } catch (e) {}
            }
            inEl.volume = targetVol;
            return;
        }

        const startedAt = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
        this._crossfadeTimer = setInterval(() => {
            if (!this.lobbyOpen || !this.lobbyActive || this.bgmMuted || this.bgmVolume <= 0) {
                this._stopBothBgmElements(false);
                return;
            }
            const now = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
            const rawT = clampNum((now - startedAt) / durationMs, 0, 1);
            const smoothT = 0.5 - 0.5 * Math.cos(rawT * Math.PI);
            const liveTarget = this._targetBgmGain();

            try {
                inEl.volume = clampNum(inStartVol + (liveTarget - inStartVol) * smoothT, 0, 0.50);
            } catch (e) {}
            try {
                outEl.volume = clampNum(outStartVol * (1 - smoothT), 0, 0.50);
            } catch (e) {}

            if (rawT >= 1) {
                this._cancelCrossfade();
                try {
                    inEl.volume = liveTarget;
                    outEl.volume = 0;
                    outEl.pause();
                } catch (e) {}
            }
        }, 35);
    }

    /**
     * Wechselt die aktive Musik-Zone ('gallery' / 'heroes' = Calm-Track, 'lava' = Action-Magma-Track)
     * per sanftem 700-ms-Crossfade. Spielt niemals ausserhalb der geoeffneten 3D-Lobby!
     */
    setZone(zone) {
        const prevWasLava = (this.currentZone === 'lava');
        const cleanZone = normalizeAudioZone(zone);
        const nextIsLava = (cleanZone === 'lava');
        this.currentZone = cleanZone;

        if (!this.lobbyOpen || !this.lobbyActive) {
            this._stopBothBgmElements(false);
            return;
        }
        if (this.bgmMuted || this.bgmVolume <= 0) {
            this._stopBothBgmElements(false);
            return;
        }

        if (prevWasLava !== nextIsLava) {
            this._crossfadeToZoneTrack(nextIsLava, BGM_CROSSFADE_DURATION_MS);
        } else if (!this._crossfadeTimer) {
            this._syncActiveBgmPlayback();
        }
        this._emitChange();
    }

    /**
     * Startet die 3D-Lobby-Hintergrundmusik synchron im Klick-Event ("3D-Lobby oeffnen" / "3D shoppen"),
     * damit das User-Gesture-Token des Browsers zu 100 % erhalten bleibt.
     */
    startLobbyMusicFromGesture(initialZone = undefined) {
        this._adoptPrewarmBgmIfPresent();
        this._loadStoredSettings();
        if (initialZone !== undefined) {
            this.currentZone = normalizeAudioZone(initialZone);
        }
        this.lobbyOpen = true;
        this.lobbyActive = true;
        this._markLobbyOpeningGrace();
        this._startWatchdog();

        if (typeof window !== 'undefined') {
            window.addEventListener('pointerdown', this._boundGestureUnlock, { passive: true });
            window.addEventListener('keydown', this._boundGestureUnlock, { passive: true });
        }

        // Kein proaktives Oeffnen von WebAudio (AudioContext) beim Lobby-Start:
        // Auf Android verhindert dies AAudio-FastTrack-Interferenzen mit der Hardware-MP3-Wiedergabe.
        if (this.currentZone === 'lava') {
            this._ensureMagmaBgmElement();
        }

        const allMuted = this.bgmMuted && this.sfxMuted;
        if (!this.bgmMuted && !allMuted) {
            if (this.bgmVolume <= 0) {
                this.bgmVolume = DEFAULT_LOBBY_BGM_VOLUME;
            }
            try {
                localStorage.setItem(LS_BGM_MUTED, '0');
            } catch (e) {}
            this._syncActiveBgmPlayback();
        } else {
            this._stopBothBgmElements(false);
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

    leaveLobby() {
        this.onLobbyClose();
    }

    close() {
        this.onLobbyClose();
    }

    destroy() {
        this.dispose();
    }

    onLobbyOpen(isAuthenticated = undefined, initialZone = undefined) {
        this._adoptPrewarmBgmIfPresent();
        if (isAuthenticated !== undefined) {
            this.isAuthenticated = Boolean(isAuthenticated);
        }
        if (initialZone !== undefined) {
            this.currentZone = normalizeAudioZone(initialZone);
        }
        const alreadyStartedByGesture = this.lobbyActive && this.lobbyOpen;
        this.lobbyOpen = true;
        this.lobbyActive = true;
        this._markLobbyOpeningGrace();
        this._startWatchdog();
        if (!alreadyStartedByGesture) {
            this._loadStoredSettings();
        }

        if (typeof window !== 'undefined') {
            window.addEventListener('pointerdown', this._boundGestureUnlock, { passive: true });
            window.addEventListener('keydown', this._boundGestureUnlock, { passive: true });
        }

        if (!this.bgmMuted && this.bgmVolume > 0) {
            this._syncActiveBgmPlayback();
        } else {
            this._stopBothBgmElements(false);
        }

        this._emitChange();
    }

    onLobbyClose() {
        this.lobbyOpen = false;
        this.lobbyActive = false;
        this._modalConfirmedOpen = false;
        this._openedGraceUntil = 0;
        this._stopWatchdog();
        this._clearOneShotUnlock();
        this._clearIdleContextSuspend();
        this._stopBothBgmElements(true);
        this.updateLavaAmbience(999, false);

        if (typeof window !== 'undefined') {
            window.removeEventListener('pointerdown', this._boundGestureUnlock);
            window.removeEventListener('keydown', this._boundGestureUnlock);
            if (!this._inCloseSequence && typeof window.__sfForceStopAllLobbyAudio === 'function') {
                this._inCloseSequence = true;
                try {
                    window.__sfForceStopAllLobbyAudio();
                } finally {
                    this._inCloseSequence = false;
                }
            }
        }

        if (this._ctx && this._ctx.state === 'running') {
            this._ctx.suspend().catch(() => {});
        }
        this._emitChange();
    }

    _persistBgmSettings(commitServer = false) {
        try {
            localStorage.setItem(LS_BGM_VOL, String(this.bgmVolume));
            localStorage.setItem(LS_BGM_MUTED, this.bgmMuted ? '1' : '0');
            localStorage.setItem(LS_LOBBY_EXPLICIT_MUTED, this.bgmMuted ? '1' : '0');
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
        if (clamped === 0) {
            this.bgmMuted = true;
        } else if (this.bgmMuted) {
            this.bgmMuted = false;
        }
        this._persistBgmSettings(Boolean(commit));
        if (this.lobbyOpen && this.lobbyActive) {
            if (this._crossfadeTimer && !this.bgmMuted && this.bgmVolume > 0) {
                // Laufender Crossfade liest live _targetBgmGain()
            } else {
                this._syncActiveBgmPlayback();
            }
        } else {
            this._stopBothBgmElements(false);
        }
        this._emitChange();
    }

    setBgmMuted(muted, commit = true) {
        const nextMuted = Boolean(muted);
        this.bgmMuted = nextMuted;
        if (!this.bgmMuted && this.bgmVolume <= 0) {
            this.bgmVolume = DEFAULT_LOBBY_BGM_VOLUME;
        }
        this._persistBgmSettings(Boolean(commit));
        if (this.lobbyOpen && this.lobbyActive) {
            this._syncActiveBgmPlayback();
        } else {
            this._stopBothBgmElements(false);
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
            this.sfxVolume = DEFAULT_LOBBY_SFX_VOLUME;
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
        const norm = clampNum(this.sfxVolume / 100, 0, 1);
        return clampNum(Math.pow(norm, 0.72) * 0.58, 0.02, 0.58);
    }

    _applyMasterSfxGain() {
        if (this._masterSfxGain && this._ctx) {
            this._masterSfxGain.gain.setTargetAtTime(this._effectiveSfxGain(), this._ctx.currentTime, 0.02);
        }
        if (this.sfxMuted || this.sfxVolume <= 0) {
            this.updateLavaAmbience(999, false);
        }
    }

    _clearIdleContextSuspend() {
        if (this._idleSuspendTimer) {
            clearTimeout(this._idleSuspendTimer);
            this._idleSuspendTimer = null;
        }
    }

    _scheduleIdleContextSuspend(extraSeconds = 0.25) {
        this._clearIdleContextSuspend();
        const delayMs = Math.max(600, Math.round(extraSeconds * 1000) + SFX_IDLE_SUSPEND_DELAY_MS);
        this._idleSuspendTimer = setTimeout(() => {
            this._idleSuspendTimer = null;
            if (this._lavaRumblerNodes) return;
            if (this._ctx && this._ctx.state === 'running') {
                this._ctx.suspend().catch(() => {});
            }
        }, delayMs);
    }

    _ensureContext() {
        if (typeof window === 'undefined') return null;
        if (!this._ctx) {
            const Ctor = window.AudioContext || window.webkitAudioContext;
            if (!Ctor) return null;
            try {
                try {
                    this._ctx = new Ctor({ latencyHint: 'playback' });
                } catch (hintErr) {
                    this._ctx = new Ctor();
                }
                this._masterSfxGain = this._ctx.createGain();
                this._masterSfxGain.gain.value = this._effectiveSfxGain();
                if (typeof this._ctx.createDynamicsCompressor === 'function') {
                    this._masterSfxLimiter = this._ctx.createDynamicsCompressor();
                    this._masterSfxLimiter.threshold.value = -8.0;
                    this._masterSfxLimiter.knee.value = 12.0;
                    this._masterSfxLimiter.ratio.value = 12.0;
                    this._masterSfxLimiter.attack.value = 0.003;
                    this._masterSfxLimiter.release.value = 0.16;
                    this._masterSfxGain.connect(this._masterSfxLimiter);
                    this._masterSfxLimiter.connect(this._ctx.destination);
                } else {
                    this._masterSfxGain.connect(this._ctx.destination);
                }
                this._warmNoiseBuffer = this._bakeWarmMatteNoise(this._ctx);
            } catch (e) {
                this._ctx = null;
                return null;
            }
        }
        if (this._ctx.state === 'suspended') {
            this._ctx.resume().catch(() => {});
        }
        this._scheduleIdleContextSuspend(0.25);
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
        const fadeSamples = Math.max(64, Math.floor(sampleRate * 0.025));
        let last = 0;
        for (let i = 0; i < length; i++) {
            const white = Math.random() * 2 - 1;
            last = (last + 0.085 * white) / 1.085;
            let sample = clampNum(last * 2.4, -0.82, 0.82);
            if (i < fadeSamples) {
                sample *= (i / fadeSamples);
            } else if (i >= length - fadeSamples) {
                sample *= ((length - 1 - i) / fadeSamples);
            }
            data[i] = sample;
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
                registerLobbyAudioElement(el);
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

        const safeDelay = Math.max(0, delay);
        const safeDur = Math.max(0.02, duration);
        const t0 = ctx.currentTime + safeDelay;
        const t1 = t0 + safeDur;

        this._scheduleIdleContextSuspend(safeDelay + safeDur + 0.06);

        const osc = ctx.createOscillator();
        const filter = ctx.createBiquadFilter();
        const env = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(Math.max(24, freqStart), t0);
        if (freqEnd !== freqStart) {
            osc.frequency.exponentialRampToValueAtTime(Math.max(24, freqEnd), t1);
        }

        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(clampNum(cutoff, 90, 4400), t0);
        filter.Q.value = 0.7;

        env.gain.setValueAtTime(0.0001, t0);
        env.gain.linearRampToValueAtTime(Math.max(0.001, gain), t0 + Math.min(0.014, safeDur * 0.22));
        env.gain.exponentialRampToValueAtTime(0.0001, t1);
        env.gain.linearRampToValueAtTime(0.00001, t1 + 0.018);

        osc.connect(filter);
        filter.connect(env);
        env.connect(this._masterSfxGain);

        osc.start(t0);
        osc.stop(t1 + 0.024);
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

        const safeDelay = Math.max(0, delay);
        const safeDur = Math.max(0.03, duration);
        const t0 = ctx.currentTime + safeDelay;
        const t1 = t0 + safeDur;

        this._scheduleIdleContextSuspend(safeDelay + safeDur + 0.06);

        const src = ctx.createBufferSource();
        src.buffer = this._warmNoiseBuffer;
        src.loop = true;

        const filter = ctx.createBiquadFilter();
        filter.type = filterType;
        filter.frequency.setValueAtTime(clampNum(freqStart, 80, 4400), t0);
        if (freqEnd !== freqStart) {
            filter.frequency.exponentialRampToValueAtTime(clampNum(freqEnd, 70, 4400), t1);
        }
        filter.Q.value = q;

        const env = ctx.createGain();
        env.gain.setValueAtTime(0.0001, t0);
        env.gain.linearRampToValueAtTime(Math.max(0.001, gain), t0 + Math.min(0.035, safeDur * 0.28));
        env.gain.exponentialRampToValueAtTime(0.0001, t1);
        env.gain.linearRampToValueAtTime(0.00001, t1 + 0.018);

        src.connect(filter);
        filter.connect(env);
        env.connect(this._masterSfxGain);

        src.start(t0, Math.random() * 0.4);
        src.stop(t1 + 0.024);
        src.onended = () => {
            try {
                src.disconnect();
                filter.disconnect();
                env.disconnect();
            } catch (e) {}
        };
    }

    playWeaponSwing(comboStep = 1) {
        if (!this._canPlaySfx()) return;
        const startHz = comboStep === 3 ? 520 : (comboStep === 2 ? 420 : 360);
        this._triggerMatteWhoosh({
            duration: 0.20,
            gain: 0.22,
            filterType: 'bandpass',
            freqStart: startHz,
            freqEnd: 160,
            q: 1.5,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: comboStep === 3 ? 168 : 142,
            freqEnd: 74,
            duration: 0.14,
            gain: 0.16,
            cutoff: 560,
        });
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
        const ctx = this._ensureContext();
        if (!ctx || !this._masterSfxGain) return;

        // 1. Tiefer Sub-Impact beim Eintauchen in die Magma
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 128,
            freqEnd: 32,
            duration: 0.54,
            gain: 0.52,
            cutoff: 340,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 184,
            freqEnd: 48,
            duration: 0.36,
            gain: 0.36,
            cutoff: 540,
        });

        // 2. Heisses Dampf-Zischen (Highpass-Sizzle + Bandpass-Magma-Woge)
        this._triggerMatteWhoosh({
            duration: 0.64,
            gain: 0.38,
            filterType: 'highpass',
            freqStart: 2550,
            freqEnd: 780,
            q: 0.92,
        });
        this._triggerMatteWhoosh({
            duration: 0.68,
            gain: 0.44,
            filterType: 'bandpass',
            freqStart: 1320,
            freqEnd: 240,
            q: 1.25,
        });

        // 3. Sattes Lava-Eintauch-Blubbern (resonante Blubber-Modulations-Echos)
        const bubbles = [
            { delay: 0.06, f0: 84, f1: 186, dur: 0.11, gain: 0.30 },
            { delay: 0.15, f0: 102, f1: 224, dur: 0.12, gain: 0.26 },
            { delay: 0.26, f0: 76, f1: 168, dur: 0.13, gain: 0.22 },
            { delay: 0.38, f0: 92, f1: 198, dur: 0.11, gain: 0.18 },
        ];
        for (let i = 0; i < bubbles.length; i++) {
            const b = bubbles[i];
            const t0 = ctx.currentTime + b.delay;
            const t1 = t0 + b.dur;
            try {
                const osc = ctx.createOscillator();
                const flt = ctx.createBiquadFilter();
                const env = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(b.f0, t0);
                osc.frequency.exponentialRampToValueAtTime(b.f1, t1);
                flt.type = 'bandpass';
                flt.frequency.setValueAtTime(b.f0 * 1.35, t0);
                flt.frequency.exponentialRampToValueAtTime(b.f1 * 1.45, t1);
                flt.Q.value = 3.2;
                env.gain.setValueAtTime(0.0001, t0);
                env.gain.linearRampToValueAtTime(b.gain, t0 + b.dur * 0.26);
                env.gain.exponentialRampToValueAtTime(0.0001, t1);
                osc.connect(flt);
                flt.connect(env);
                env.connect(this._masterSfxGain);
                osc.start(t0);
                osc.stop(t1 + 0.02);
                osc.onended = () => {
                    try {
                        osc.disconnect();
                        flt.disconnect();
                        env.disconnect();
                    } catch (e) {}
                };
            } catch (e) {}
        }
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
        const ctx = this._ensureContext();
        if (!ctx) return;
        const now = (typeof performance !== 'undefined') ? performance.now() : Date.now();
        if (this._lastSparkCollectAt && (now - this._lastSparkCollectAt) < 42) {
            return;
        }
        this._lastSparkCollectAt = now;

        // Originaler suesser G-Dur-Celesta-Gloeckchen-Klang des Website-Seelenfunkens (783.99, 987.77, 1174.66, 1567.98 Hz)
        const celestaNotes = [783.99, 987.77, 1174.66, 1567.98];
        const pitchNudge = 1.0 + ((Math.abs(Math.round(Number(stepIndex) || 0)) % 3) * 0.004);
        for (let i = 0; i < celestaNotes.length; i++) {
            const f = celestaNotes[i] * pitchNudge;
            const d = i * 0.038;
            this._triggerMatteTone({
                type: 'sine',
                freqStart: f,
                freqEnd: f * 1.002,
                duration: 0.34 - i * 0.02,
                gain: 0.135 - i * 0.016,
                cutoff: 3900,
                delay: d,
            });
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: f * 2.0,
                freqEnd: f * 2.0,
                duration: 0.18,
                gain: 0.032,
                cutoff: 4200,
                delay: d + 0.006,
            });
        }
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

    playGateRepulse() {
        if (!this._canPlaySfx()) return;
        const now = (typeof performance !== 'undefined') ? performance.now() : Date.now();
        if (this._lastGateRepulseAt && (now - this._lastGateRepulseAt) < 320) return;
        this._lastGateRepulseAt = now;

        // Gedaempfter Gold-Rot-Schutzfeld-Repulsor beim physischen Rueckstoss vor gesperrten Toren
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 124,
            freqEnd: 38,
            duration: 0.34,
            gain: 0.36,
            cutoff: 290,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 220,
            freqEnd: 92,
            duration: 0.24,
            gain: 0.20,
            cutoff: 540,
            delay: 0.02,
        });
        this._triggerMatteWhoosh({
            duration: 0.32,
            gain: 0.26,
            filterType: 'bandpass',
            freqStart: 520,
            freqEnd: 150,
            q: 1.05,
        });
    }

    playMagicAwaken() {
        if (!this._canPlaySfx()) return;
        const ctx = this._ensureContext();
        if (!ctx) return;
        // Epischer ~1.45s 24K-Gold-Aufstiegs-Akkord passend zur Doppel-Helix-Erweckungs-Choreografie
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 73.42,
            freqEnd: 146.83,
            duration: 1.18,
            gain: 0.28,
            cutoff: 460,
        });
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 146.83,
            freqEnd: 293.66,
            duration: 1.12,
            gain: 0.16,
            cutoff: 760,
            delay: 0.08,
        });
        this._triggerMatteWhoosh({
            duration: 1.28,
            gain: 0.26,
            filterType: 'bandpass',
            freqStart: 220,
            freqEnd: 1180,
            q: 1.05,
        });
        const chord = [261.63, 329.63, 392.00, 493.88, 587.33, 783.99, 987.77, 1174.66];
        for (let i = 0; i < chord.length; i++) {
            const f = chord[i];
            const d = 0.04 + i * 0.115;
            this._triggerMatteTone({
                type: 'sine',
                freqStart: f,
                freqEnd: f * 1.004,
                duration: 0.78,
                gain: Math.max(0.055, 0.17 - i * 0.014),
                cutoff: 2800,
                delay: d,
            });
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: f * 0.5,
                freqEnd: f * 0.5,
                duration: 0.60,
                gain: 0.068,
                cutoff: 1120,
                delay: d + 0.018,
            });
        }
    }

    playDrinkPotion() {
        if (!this._canPlaySfx()) return;
        const ctx = this._ensureContext();
        if (!ctx) return;
        // Edler Kristall-Korken-Plopp + warmer Rubin-Gold-Heilklang (~0.70s)
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 290,
            freqEnd: 780,
            duration: 0.058,
            gain: 0.22,
            cutoff: 2200,
        });
        this._triggerMatteWhoosh({
            duration: 0.095,
            gain: 0.16,
            filterType: 'bandpass',
            freqStart: 1850,
            freqEnd: 920,
            q: 1.4,
            delay: 0.015,
        });
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 185,
            freqEnd: 320,
            duration: 0.11,
            gain: 0.18,
            cutoff: 920,
            delay: 0.10,
        });
        const healNotes = [523.25, 659.25, 783.99, 1046.50];
        for (let i = 0; i < healNotes.length; i++) {
            const f = healNotes[i];
            const d = 0.18 + i * 0.085;
            this._triggerMatteTone({
                type: 'sine',
                freqStart: f,
                freqEnd: f * 1.003,
                duration: 0.46,
                gain: 0.15 - i * 0.016,
                cutoff: 3200,
                delay: d,
            });
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: f * 0.5,
                freqEnd: f * 0.5,
                duration: 0.36,
                gain: 0.065,
                cutoff: 1200,
                delay: d + 0.014,
            });
        }
    }

    playHeartLost(remainingHearts = 5) {
        if (!this._canPlaySfx()) return;
        const accent = clampNum(0.88 + (6 - clampNum(remainingHearts, 0, 6)) * 0.03, 0.85, 1.12);
        this._triggerMatteTone({
            type: 'triangle',
            freqStart: 246.94 * accent,
            freqEnd: 98.00,
            duration: 0.22,
            gain: 0.22,
            cutoff: 620,
        });
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 392.00,
            freqEnd: 196.00,
            duration: 0.16,
            gain: 0.13,
            cutoff: 920,
            delay: 0.03,
        });
    }

    playLevelDemoted() {
        if (!this._canPlaySfx()) return;
        const notes = [293.66, 246.94, 196.00, 146.83];
        for (let i = 0; i < notes.length; i++) {
            this._triggerMatteTone({
                type: 'triangle',
                freqStart: notes[i],
                freqEnd: notes[i] * 0.94,
                duration: 0.34,
                gain: 0.22 - i * 0.02,
                cutoff: 580,
                delay: i * 0.09,
            });
        }
        this._triggerMatteTone({
            type: 'sine',
            freqStart: 82,
            freqEnd: 38,
            duration: 0.56,
            gain: 0.30,
            cutoff: 240,
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
        // BGM beim Verlassen in Richtung Produktseite sofort stummschalten
        this._stopBothBgmElements(true);
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
        this._stopWatchdog();
        this._clearIdleContextSuspend();
        if (this._serverSyncTimer) {
            clearTimeout(this._serverSyncTimer);
            this._serverSyncTimer = null;
        }
        const reg = ensureGlobalLobbyAudioRegistry();
        if (this._calmBgmEl) {
            try {
                this._calmBgmEl.pause();
                this._calmBgmEl.removeAttribute('src');
            } catch (e) {}
            if (reg) reg.delete(this._calmBgmEl);
            this._calmBgmEl = null;
        }
        if (this._magmaBgmEl) {
            try {
                this._magmaBgmEl.pause();
                this._magmaBgmEl.removeAttribute('src');
            } catch (e) {}
            if (reg) reg.delete(this._magmaBgmEl);
            this._magmaBgmEl = null;
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

export function forceStopAllLobbyAudio() {
    clearGlobalLobbyWatchdogTimer();

    const inst = (typeof window !== 'undefined' && window.__sfLobbyAudioSingleton)
        ? window.__sfLobbyAudioSingleton
        : null;

    if (typeof window !== 'undefined') {
        window.__sfLobbyAudioGraceUntil = 0;
    }

    if (inst) {
        inst.lobbyOpen = false;
        inst.lobbyActive = false;
        inst._modalConfirmedOpen = false;
        inst._openedGraceUntil = 0;
        if (inst._watchdogTimer) {
            clearInterval(inst._watchdogTimer);
            inst._watchdogTimer = null;
        }
        if (typeof inst._cancelCrossfade === 'function') {
            inst._cancelCrossfade();
        }
        if (typeof inst._clearOneShotUnlock === 'function') {
            inst._clearOneShotUnlock();
        }
        if (typeof inst._clearIdleContextSuspend === 'function') {
            inst._clearIdleContextSuspend();
        }
        if (typeof window !== 'undefined' && inst._boundGestureUnlock) {
            window.removeEventListener('pointerdown', inst._boundGestureUnlock);
            window.removeEventListener('keydown', inst._boundGestureUnlock);
        }
    }

    const toSilence = new Set();
    const reg = ensureGlobalLobbyAudioRegistry();
    if (reg) {
        reg.forEach((el) => {
            if (el) toSilence.add(el);
        });
    }
    if (inst) {
        if (inst._calmBgmEl) toSilence.add(inst._calmBgmEl);
        if (inst._magmaBgmEl) toSilence.add(inst._magmaBgmEl);
        if (inst._mp3Pool) {
            if (inst._mp3Pool.shield) toSilence.add(inst._mp3Pool.shield);
            if (inst._mp3Pool.sprint) toSilence.add(inst._mp3Pool.sprint);
        }
    }
    if (typeof window !== 'undefined' && window.__sfPrewarmLobbyBgmEl) {
        const preEl = window.__sfPrewarmLobbyBgmEl;
        registerLobbyAudioElement(preEl);
        toSilence.add(preEl);
        if (inst && !inst._calmBgmEl) {
            preEl.loop = true;
            inst._calmBgmEl = preEl;
        }
        window.__sfPrewarmLobbyBgmEl = null;
    }
    if (typeof document !== 'undefined') {
        const domAudios = document.querySelectorAll('audio');
        for (let i = 0; i < domAudios.length; i++) {
            const domEl = domAudios[i];
            const srcAttr = String(domEl.getAttribute('src') || domEl.currentSrc || domEl.src || '');
            let hasFunkiBgm = srcAttr.indexOf('bgm_funki') !== -1;
            if (!hasFunkiBgm) {
                const sourceChild = domEl.querySelector('source[src*="bgm_funki"]');
                hasFunkiBgm = Boolean(sourceChild);
            }
            if (hasFunkiBgm) {
                toSilence.add(domEl);
            }
        }
    }

    toSilence.forEach((el) => {
        silenceSingleAudioElement(el, true);
    });

    if (inst) {
        if (typeof inst.updateLavaAmbience === 'function') {
            inst.updateLavaAmbience(999, false);
        }
        if (inst._ctx && inst._ctx.state === 'running') {
            inst._ctx.suspend().catch(() => {});
        }
        if (typeof inst._emitChange === 'function') {
            inst._emitChange();
        }
    }
}

export function getLobbyAudioInstance(options = {}) {
    if (typeof window === 'undefined') {
        return new SeelenfunkeLobbyAudio(options);
    }

    const existing = window.__sfLobbyAudioSingleton;
    if (existing) {
        if (existing instanceof SeelenfunkeLobbyAudio || typeof existing._startWatchdog === 'function') {
            if (options.isAuthenticated !== undefined && typeof existing.setAuthenticated === 'function') {
                existing.setAuthenticated(options.isAuthenticated);
            }
            return existing;
        }

        // Sichere Migration einer aelteren Modul-Instanz auf window.__sfLobbyAudioSingleton (0 verlorene Audio-Elemente!)
        const migrated = new SeelenfunkeLobbyAudio({
            isAuthenticated: options.isAuthenticated !== undefined ? options.isAuthenticated : existing.isAuthenticated,
            initialZone: existing.currentZone || options.initialZone || 'gallery',
        });
        if (existing._calmBgmEl) {
            registerLobbyAudioElement(existing._calmBgmEl);
            migrated._calmBgmEl = existing._calmBgmEl;
            existing._calmBgmEl = null;
        }
        if (existing._magmaBgmEl) {
            registerLobbyAudioElement(existing._magmaBgmEl);
            migrated._magmaBgmEl = existing._magmaBgmEl;
            existing._magmaBgmEl = null;
        }
        if (existing._mp3Pool) {
            if (existing._mp3Pool.shield) registerLobbyAudioElement(existing._mp3Pool.shield);
            if (existing._mp3Pool.sprint) registerLobbyAudioElement(existing._mp3Pool.sprint);
        }
        if (existing._crossfadeTimer) {
            clearInterval(existing._crossfadeTimer);
            existing._crossfadeTimer = null;
        }
        if (typeof existing._clearOneShotUnlock === 'function') {
            existing._clearOneShotUnlock();
        }
        if (existing._boundGestureUnlock) {
            window.removeEventListener('pointerdown', existing._boundGestureUnlock);
            window.removeEventListener('keydown', existing._boundGestureUnlock);
        }
        if (existing._ctx && existing._ctx.state === 'running') {
            existing._ctx.suspend().catch(() => {});
        }
        const wasActive = Boolean(existing.lobbyOpen && existing.lobbyActive && isAny3dLobbyModalVisibleInDom());
        existing.lobbyOpen = false;
        existing.lobbyActive = false;
        window.__sfLobbyAudioSingleton = migrated;
        if (wasActive) {
            migrated.onLobbyOpen(migrated.isAuthenticated, migrated.currentZone);
        } else {
            forceStopAllLobbyAudio();
        }
        return migrated;
    }

    const created = new SeelenfunkeLobbyAudio(options);
    window.__sfLobbyAudioSingleton = created;
    return created;
}

function bindGlobalLobbyAudioLifecycleGuards() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return;
    window.__sfForceStopAllLobbyAudio = forceStopAllLobbyAudio;
    ensureGlobalLobbyAudioRegistry();

    if (window.__sfLobbyAudioLifecycleGuardsBound) return;
    window.__sfLobbyAudioLifecycleGuardsBound = true;

    const stopIfNoModalVisible = () => {
        if (!isAny3dLobbyModalVisibleInDom()) {
            forceStopAllLobbyAudio();
        }
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(() => {
                if (!isAny3dLobbyModalVisibleInDom()) {
                    forceStopAllLobbyAudio();
                }
            });
        }
        setTimeout(() => {
            if (!isAny3dLobbyModalVisibleInDom()) {
                forceStopAllLobbyAudio();
            }
        }, 80);
    };

    document.addEventListener('livewire:navigating', () => {
        forceStopAllLobbyAudio();
    });
    document.addEventListener('livewire:navigated', stopIfNoModalVisible);
    window.addEventListener('popstate', stopIfNoModalVisible);
    window.addEventListener('pagehide', () => {
        forceStopAllLobbyAudio();
    });
    window.addEventListener('beforeunload', () => {
        forceStopAllLobbyAudio();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            forceStopAllLobbyAudio();
            return;
        }
        // Falls Tab wieder sichtbar wird und 3D-Lobby-Modal offen ist, beim naechsten Klick im Modal reaktivieren
        if (isAny3dLobbyModalVisibleInDom()) {
            const resumeOnInteract = () => {
                if (!document.hidden && isAny3dLobbyModalVisibleInDom() && window.__sfLobbyAudioSingleton) {
                    window.__sfLobbyAudioSingleton.onLobbyOpen(
                        window.__sfLobbyAudioSingleton.isAuthenticated,
                        window.__sfLobbyAudioSingleton.currentZone
                    );
                }
            };
            window.addEventListener('pointerdown', resumeOnInteract, { once: true, passive: true });
        }
    });
}

if (typeof window !== 'undefined') {
    window.SeelenfunkeLobbyAudio = SeelenfunkeLobbyAudio;
    window.getSeelenfunkeLobbyAudio = getLobbyAudioInstance;
    bindGlobalLobbyAudioLifecycleGuards();
}
