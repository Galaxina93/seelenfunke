/*!
 * Mein-Seelenfunke · Edles 3D-Produkt-Atelier & Goldene Halle der Helden
 * 60-FPS Three.js Engine: InstancedMesh, 24-Bone SkinnedMesh mit Hand-/Finger- & Umhang-Physik, Jahreszeiten & Tor zur Aussenwelt.
 * On-Demand ES-Modul: 0 KB Initial-Last auf /shop & /mein-konto, 0 % CPU/GPU nach dem Schliessen.
 */

import { getLobbyAudioInstance, SeelenfunkeLobbyAudio } from './seelenfunke-lobby-audio.js?v=20261118v3';

const TWO_PI = Math.PI * 2;
const PROXIMITY_FOCUS_ENTER = 3.4;
const PROXIMITY_FOCUS_LEAVE = 3.75;
const HERO_FOCUS_ENTER = 3.85;
const HERO_FOCUS_LEAVE = 4.35;

export const LOBBY_KEYBINDINGS_STORAGE_KEY = 'sf_lobby_keybindings_v4';
export const LOBBY_SETTINGS_STORAGE_KEY = 'sf_lobby_user_settings_v2';

export const DEFAULT_LOBBY_KEYBINDINGS = {
    jump: 'Space',
    moveForward: 'KeyW',
    moveLeft: 'KeyA',
    moveBack: 'KeyS',
    moveRight: 'KeyD',
};

export const DEFAULT_LOBBY_USER_SETTINGS = {
    volume: 5,
    cameraSensitivity: 100,
    useRealName: false,
    graphicsQuality: 'high',
};

export function loadLobbyUserSettings() {
    const out = Object.assign({}, DEFAULT_LOBBY_USER_SETTINGS);
    if (typeof localStorage === 'undefined') return out;
    try {
        const raw = localStorage.getItem(LOBBY_SETTINGS_STORAGE_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                if (Number.isFinite(Number(parsed.volume))) {
                    out.volume = Math.max(0, Math.min(100, Math.round(Number(parsed.volume))));
                }
                if (Number.isFinite(Number(parsed.cameraSensitivity))) {
                    out.cameraSensitivity = Math.max(50, Math.min(150, Math.round(Number(parsed.cameraSensitivity))));
                }
                if (typeof parsed.useRealName === 'boolean') {
                    out.useRealName = parsed.useRealName;
                }
                if (parsed.graphicsQuality === 'balanced' || parsed.graphicsQuality === 'high') {
                    out.graphicsQuality = parsed.graphicsQuality;
                }
            }
        }
    } catch (e) {}
    return out;
}

export function saveLobbyUserSettings(partial) {
    const merged = Object.assign(loadLobbyUserSettings(), partial || {});
    merged.volume = Math.max(0, Math.min(100, Math.round(Number(merged.volume ?? 5))));
    merged.cameraSensitivity = Math.max(50, Math.min(150, Math.round(Number(merged.cameraSensitivity ?? 100))));
    merged.useRealName = Boolean(merged.useRealName);
    merged.graphicsQuality = merged.graphicsQuality === 'balanced' ? 'balanced' : 'high';
    if (typeof localStorage !== 'undefined') {
        try {
            localStorage.setItem(LOBBY_SETTINGS_STORAGE_KEY, JSON.stringify(merged));
        } catch (e) {}
    }
    return merged;
}

export function resolveLobbyEnvironment(rawEnv) {
    const now = new Date();
    const month = now.getMonth() + 1;
    const day = now.getDate();
    let fallbackKey = 'autumn';
    let fallbackLabel = 'Herbst';
    if (month === 12 || month === 1 || month === 2) {
        fallbackKey = 'winter';
        fallbackLabel = 'Winter';
    } else if (month >= 3 && month <= 5) {
        fallbackKey = 'spring';
        fallbackLabel = 'Frühling';
    } else if (month >= 6 && month <= 8) {
        fallbackKey = 'summer';
        fallbackLabel = 'Sommer';
    }

    let fallbackHolidayKey = null;
    let fallbackHolidayLabel = null;
    if (month === 12 && day >= 1 && day <= 26) {
        fallbackHolidayKey = 'weihnachten';
        fallbackHolidayLabel = 'Weihnachtszeit';
    } else if ((month === 12 && day === 31) || (month === 1 && day === 1)) {
        fallbackHolidayKey = 'neujahr';
        fallbackHolidayLabel = 'Neujahr';
    } else if (month === 2 && day === 14) {
        fallbackHolidayKey = 'valentinstag';
        fallbackHolidayLabel = 'Valentinstag';
    } else if (month === 10 && day >= 25 && day <= 31) {
        fallbackHolidayKey = 'halloween';
        fallbackHolidayLabel = 'Halloween';
    }

    const pad2 = (n) => (n < 10 ? '0' + n : String(n));
    const localTime = pad2(now.getHours()) + ':' + pad2(now.getMinutes());

    const env = (rawEnv && typeof rawEnv === 'object') ? rawEnv : {};
    const validSeasons = ['spring', 'summer', 'autumn', 'winter'];
    const seasonKey = validSeasons.indexOf(String(env.season_key || '').toLowerCase()) !== -1
        ? String(env.season_key).toLowerCase()
        : fallbackKey;
    const seasonLabels = {
        spring: 'Frühling',
        summer: 'Sommer',
        autumn: 'Herbst',
        winter: 'Winter',
    };
    const seasonLabel = String(env.season_label || seasonLabels[seasonKey] || fallbackLabel);
    const holidayKey = env.holiday_key ? String(env.holiday_key).toLowerCase() : fallbackHolidayKey;
    const holidayLabel = env.holiday_label ? String(env.holiday_label) : fallbackHolidayLabel;
    const serverTime = (typeof env.server_time === 'string' && /^\d{2}:\d{2}$/.test(env.server_time))
        ? env.server_time
        : localTime;

    return {
        season_key: seasonKey,
        season_label: seasonLabel,
        holiday_key: holidayKey,
        holiday_label: holidayLabel,
        server_time: serverTime,
    };
}

export function loadLobbyKeybindings() {
    const out = Object.assign({}, DEFAULT_LOBBY_KEYBINDINGS);
    if (typeof localStorage === 'undefined') return out;
    try {
        const raw = localStorage.getItem(LOBBY_KEYBINDINGS_STORAGE_KEY);
        if (!raw) return out;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
            Object.keys(DEFAULT_LOBBY_KEYBINDINGS).forEach((k) => {
                if (typeof parsed[k] === 'string' && parsed[k].trim() !== '') {
                    out[k] = parsed[k].trim();
                }
            });
        }
    } catch (e) {}
    return out;
}

export function saveLobbyKeybindings(bindings) {
    const clean = Object.assign({}, DEFAULT_LOBBY_KEYBINDINGS, bindings || {});
    if (typeof localStorage !== 'undefined') {
        try {
            localStorage.setItem(LOBBY_KEYBINDINGS_STORAGE_KEY, JSON.stringify(clean));
        } catch (e) {}
    }
    return clean;
}

export function resetLobbyKeybindings() {
    if (typeof localStorage !== 'undefined') {
        try {
            localStorage.removeItem(LOBBY_KEYBINDINGS_STORAGE_KEY);
        } catch (e) {}
    }
    return Object.assign({}, DEFAULT_LOBBY_KEYBINDINGS);
}

export function formatLobbyKeyLabel(code) {
    const c = String(code || '').trim();
    if (!c) return '?';
    if (c === 'Space') return 'Leertaste';
    if (c.indexOf('Digit') === 0) return c.slice(5);
    if (c.indexOf('Numpad') === 0) return 'Num ' + c.slice(6);
    if (c.indexOf('Key') === 0) return c.slice(3).toUpperCase();
    const map = {
        ArrowUp: '↑',
        ArrowDown: '↓',
        ArrowLeft: '←',
        ArrowRight: '→',
        ShiftLeft: 'Shift',
        ShiftRight: 'Shift',
        ControlLeft: 'Strg',
        ControlRight: 'Strg',
        AltLeft: 'Alt',
        AltRight: 'Alt',
        Tab: 'Tab',
        Enter: 'Enter',
        Backspace: 'Löschen',
    };
    return map[c] || c;
}

function matchesActionKey(code, boundCode) {
    if (!code || !boundCode) return false;
    if (code === boundCode) return true;
    if (boundCode.indexOf('Digit') === 0 && code === ('Numpad' + boundCode.slice(5))) {
        return true;
    }
    return false;
}

export { getLobbyAudioInstance, SeelenfunkeLobbyAudio };

export const FUNKI_GLB_FRONT_ROTATION_Y = -Math.PI * 0.5;

export const DEFAULT_FUNKI_GLB_URLS = {
    1: '/shop/customer/gamification/models/funki_lvl_1_rags.glb',
    2: '/shop/customer/gamification/models/funki_lvl_2_basic.glb',
    3: '/shop/customer/gamification/models/funki_lvl_3_helper.glb',
    4: '/shop/customer/gamification/models/funki_lvl_4_novice.glb',
    5: '/shop/customer/gamification/models/funki_lvl_5_apprentice.glb',
    6: '/shop/customer/gamification/models/funki_lvl_6_craftsman.glb',
    7: '/shop/customer/gamification/models/funki_lvl_7_artisan.glb',
    8: '/shop/customer/gamification/models/funki_lvl_8_master.glb',
    9: '/shop/customer/gamification/models/funki_lvl_9_lightbringer.glb',
    10: '/shop/customer/gamification/models/funki_lvl_10_god_of_soul.glb',
};

export const FUNKI_LEVEL_TITLES = {
    1: 'Ursprung',
    2: 'Lehrling',
    3: 'Helfer',
    4: 'Kristall-Novize',
    5: 'Funken-Geselle',
    6: 'Gravur-Handwerker',
    7: 'Kunsthandwerker',
    8: 'Meister',
    9: 'Lichtbringer',
    10: 'Gott des Seelenfunkens',
};

export const SHOWROOM_TEXTURE_URLS = {
    calacattaGoldFloor: '/shop/showroom/textures/calacatta_gold_floor.jpg',
    louvreBoiserieWall: '/shop/showroom/textures/louvre_boiserie_wall.jpg',
    neroMarquinaPedestal: '/shop/showroom/textures/nero_marquina_pedestal.jpg',
    museumGoldenHourEnv: '/shop/showroom/textures/museum_golden_hour_env.jpg',
    pantheonCofferedDome: '/shop/showroom/textures/pantheon_coffered_dome.jpg',
};

function createBoiserieWallPlaneGeo(THREE, widthMeters, heightMeters, panelRepeatUnit = 7.8) {
    const geo = new THREE.PlaneGeometry(widthMeters, heightMeters);
    const uvAttr = geo.attributes.uv;
    const repU = Math.max(0.45, widthMeters / panelRepeatUnit);
    for (let i = 0; i < uvAttr.count; i++) {
        uvAttr.setX(i, uvAttr.getX(i) * repU);
    }
    uvAttr.needsUpdate = true;
    return geo;
}

const TOTAL_HERO_DISPLAY_SLOTS = 29;

export const SF_LOBBY_SCOPED_CSS = `
[x-cloak] { display: none !important; }
@keyframes sfLobbySpin { to { transform: rotate(360deg); } }
.sf-lobby-modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    inset: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    height: 100dvh !important;
    z-index: 2147483000 !important;
    background: #FAF8F5 !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
    user-select: none !important;
    -webkit-user-select: none !important;
    -webkit-touch-callout: none !important;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: #111827;
}
.sf-lobby-modal *, .sf-lobby-modal *::before, .sf-lobby-modal *::after {
    user-select: none !important;
    -webkit-user-select: none !important;
    -webkit-touch-callout: none !important;
}
.sf-lobby-modal input, .sf-lobby-modal textarea {
    user-select: text !important;
    -webkit-user-select: text !important;
}
.sf-lobby-modal.is-open {
    display: flex !important;
    flex-direction: column !important;
}
.sf-lobby-modal.is-closed {
    display: none !important;
}
.sf-lobby-topbar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    pointer-events: none;
    z-index: 30;
    box-sizing: border-box;
}
.sf-lobby-nav-pill {
    pointer-events: auto;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(197, 160, 89, 0.48);
    border-radius: 999px;
    padding: 4px;
    box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    max-width: 100%;
    box-sizing: border-box;
}
.sf-lobby-zone-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 13px;
    border-radius: 999px;
    background: #FAF8F5;
    color: #111827;
    border: 1px solid rgba(197, 160, 89, 0.42);
    font-size: 12px;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: 0.01em;
    white-space: nowrap;
    pointer-events: none;
    cursor: default;
}
.sf-lobby-stages-btn {
    appearance: none;
    border: 1px solid rgba(197, 160, 89, 0.55);
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 800;
    line-height: 1.2;
    background: #FAF8F5;
    color: #111827;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.sf-lobby-stages-btn:hover {
    background: #FFFFFF;
}
.sf-lobby-actions {
    pointer-events: auto;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
    margin-right: 244px;
}
.sf-lobby-pill-btn {
    appearance: none;
    background: rgba(255, 255, 255, 0.95);
    color: #111827;
    border: 1px solid rgba(197, 160, 89, 0.48);
    border-radius: 999px;
    padding: 7px 13px;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.2;
    box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    transition: background-color 150ms ease, border-color 150ms ease;
}
.sf-lobby-pill-btn:hover {
    background: #FFFFFF;
    border-color: #C5A059;
}
.sf-lobby-pill-btn.is-gold {
    background: #C5A059;
    color: #FFFFFF;
    border-color: #C5A059;
}
.sf-lobby-close-btn {
    appearance: none;
    background: rgba(255, 255, 255, 0.96);
    color: #111827;
    border: 1px solid rgba(197, 160, 89, 0.55);
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.2;
    box-shadow: 0 8px 24px rgba(17, 24, 39, 0.10);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}
.sf-lobby-close-btn:hover {
    background: #FFFFFF;
    border-color: #C5A059;
}
.sf-lobby-kbd {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 6px;
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.45);
    color: #6B7280;
    font-size: 10px;
    font-weight: 800;
    line-height: 1.1;
}
.sf-lobby-badge {
    background: rgba(255, 255, 255, 0.94);
    color: #111827;
    border: 1px solid rgba(197, 160, 89, 0.45);
    border-radius: 999px;
    padding: 6px 11px;
    font-size: 11px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    box-shadow: 0 6px 18px rgba(17, 24, 39, 0.06);
}
.sf-lobby-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
    flex-shrink: 0;
}
.sf-lobby-dot.is-live { background: #10B981; }
.sf-lobby-dot.is-solo { background: #9CA3AF; }
.sf-lobby-instance-wrap {
    position: relative;
    display: inline-flex;
}
.sf-lobby-instance-popover {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 260px;
    background: #FFFFFF;
    border: 1px solid rgba(197, 160, 89, 0.45);
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 18px 40px rgba(17, 24, 39, 0.14);
    z-index: 60;
    color: #111827;
    box-sizing: border-box;
}
.sf-lobby-instance-list {
    max-height: 176px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.sf-lobby-instance-item {
    appearance: none;
    width: 100%;
    padding: 7px 11px;
    border-radius: 11px;
    border: 1px solid rgba(197, 160, 89, 0.38);
    background: #FAF8F5;
    color: #111827;
    font-size: 11px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    box-sizing: border-box;
}
.sf-lobby-instance-item:hover {
    background: #F5EFE2;
}
.sf-lobby-instance-item.is-current {
    background: #C5A059;
    color: #FFFFFF;
    border-color: #C5A059;
}
.sf-lobby-instance-form {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(197, 160, 89, 0.28);
    display: flex;
    align-items: center;
    gap: 6px;
}
.sf-lobby-instance-input {
    width: 58px;
    padding: 6px 8px;
    border-radius: 10px;
    border: 1px solid rgba(197, 160, 89, 0.55);
    background: #FAF8F5;
    color: #111827;
    font-size: 12px;
    font-weight: 800;
    outline: none;
    box-sizing: border-box;
}
.sf-lobby-instance-submit {
    appearance: none;
    flex: 1;
    padding: 7px 10px;
    border-radius: 10px;
    border: none;
    background: #C5A059;
    color: #FFFFFF;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
}
.sf-lobby-stage {
    position: relative;
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
}
.sf-lobby-canvas {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100% !important;
    height: 100% !important;
    display: block;
    touch-action: none;
    cursor: grab;
}
.sf-lobby-ivory-transition {
    position: absolute;
    inset: 0;
    z-index: 44;
    pointer-events: none;
    opacity: 0;
    visibility: hidden;
    background: radial-gradient(circle at 50% 46%, rgba(255, 252, 244, 0.94) 0%, rgba(246, 236, 214, 0.99) 100%);
    transition: opacity 420ms cubic-bezier(0.22, 0.61, 0.36, 1), visibility 420ms ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sf-lobby-ivory-transition.is-fading {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}
.sf-lobby-ivory-transition-badge {
    padding: 10px 22px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.96);
    border: 1.5px solid #C5A059;
    box-shadow: 0 16px 38px rgba(197, 160, 89, 0.24);
    font-family: Georgia, "Times New Roman", serif;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #111827;
}
.sf-lobby-loading {
    position: absolute;
    inset: 0;
    z-index: 26;
    background: #FAF8F5;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 24px;
    text-align: center;
}
.sf-lobby-spinner {
    width: 40px;
    height: 40px;
    border-radius: 999px;
    border: 3px solid rgba(197, 160, 89, 0.26);
    border-top-color: #C5A059;
    animation: sfLobbySpin 0.85s linear infinite;
}
.sf-lobby-loading-label {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #9A7738;
}
.sf-lobby-error-card {
    background: #FFFFFF;
    color: #111827;
    border: 1px solid rgba(197, 160, 89, 0.55);
    border-radius: 18px;
    padding: 22px 24px;
    max-width: 360px;
    box-shadow: 0 16px 36px rgba(17, 24, 39, 0.12);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
}
.sf-lobby-floating-anchor {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 20;
    will-change: transform;
    transition: opacity 180ms ease;
    pointer-events: none;
}
.sf-lobby-floating-card {
    position: relative;
    width: 288px;
    max-width: calc(100vw - 24px);
    background: rgba(255, 255, 255, 0.98);
    border: 1.5px solid rgba(197, 160, 89, 0.68);
    border-radius: 18px;
    padding: 13px 15px 11px;
    box-shadow: 0 16px 38px rgba(17, 24, 39, 0.13);
    color: #111827;
    pointer-events: auto;
    box-sizing: border-box;
}
.sf-lobby-floating-arrow {
    width: 12px;
    height: 12px;
    background: rgba(255, 255, 255, 0.98);
    border-right: 1.5px solid rgba(197, 160, 89, 0.68);
    border-bottom: 1.5px solid rgba(197, 160, 89, 0.68);
    transform: rotate(45deg);
    margin: -6px auto 0;
    pointer-events: none;
}
.sf-lobby-prod-row1 {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}
.sf-lobby-prod-title {
    margin: 0;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.25;
    color: #111827;
    flex: 1;
    min-width: 0;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.sf-lobby-prod-price-badge {
    flex-shrink: 0;
    padding: 2px 8px;
    border-radius: 999px;
    background: #FAF6EE;
    border: 1px solid rgba(197, 160, 89, 0.52);
    font-size: 11px;
    font-weight: 800;
    line-height: 1.3;
    color: #111827;
    white-space: nowrap;
}
.sf-lobby-prod-summary {
    margin: 5px 0 0;
    font-size: 11.5px;
    font-weight: 600;
    line-height: 1.35;
    color: #4B5563;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.sf-lobby-prod-cta {
    appearance: none;
    margin-top: 10px;
    width: 100%;
    padding: 9px 14px;
    border-radius: 999px;
    border: 1px solid #B89146;
    background: linear-gradient(180deg, #CFA962 0%, #B89146 100%);
    color: #FFFFFF;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    box-sizing: border-box;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(197, 160, 89, 0.28);
    transition: transform 140ms ease, filter 140ms ease;
}
.sf-lobby-prod-cta:hover {
    filter: brightness(1.04);
    transform: translateY(-1px);
}
.sf-lobby-exhibit-step-row {
    margin-top: 8px;
    padding-top: 7px;
    border-top: 1px solid rgba(197, 160, 89, 0.22);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
}
.sf-lobby-exhibit-nav-link {
    appearance: none;
    border: none;
    background: transparent;
    padding: 2px 5px;
    border-radius: 6px;
    font-size: 10.5px;
    font-weight: 800;
    color: #785A28;
    cursor: pointer;
    white-space: nowrap;
}
.sf-lobby-exhibit-nav-link:hover {
    background: #FAF6EE;
    color: #111827;
}
.sf-lobby-exhibit-counter {
    appearance: none;
    border: none;
    background: transparent;
    padding: 2px 6px;
    font-size: 10.5px;
    font-weight: 800;
    color: #6B7280;
    cursor: pointer;
    white-space: nowrap;
}
.sf-lobby-hero-head {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.sf-lobby-hero-rank {
    padding: 3px 9px;
    border-radius: 999px;
    background: #C5A059;
    color: #FFFFFF;
    font-size: 11px;
    font-weight: 900;
    flex-shrink: 0;
}
.sf-lobby-hero-name {
    margin: 0;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}
.sf-lobby-hero-me {
    padding: 2px 7px;
    border-radius: 999px;
    background: #FAF8F5;
    border: 1px solid #C5A059;
    color: #9A7738;
    font-size: 9px;
    font-weight: 900;
    flex-shrink: 0;
}
.sf-lobby-hero-stats {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(197, 160, 89, 0.28);
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.sf-lobby-hero-chip {
    padding: 4px 9px;
    border-radius: 9px;
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.45);
    color: #111827;
    font-size: 11px;
    font-weight: 800;
}
.sf-lobby-portal-pill {
    pointer-events: auto;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.96);
    color: #111827;
    border: 1px solid #C5A059;
    box-shadow: 0 14px 32px rgba(17, 24, 39, 0.14);
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}
.sf-lobby-portal-pill:hover {
    background: #FFFFFF;
}
.sf-lobby-chat-panel {
    position: absolute;
    bottom: 84px;
    right: 20px;
    z-index: 26;
    width: 304px;
    max-width: calc(100vw - 24px);
    background: rgba(255, 255, 255, 0.97);
    color: #111827;
    border: 1px solid rgba(197, 160, 89, 0.55);
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 16px 38px rgba(17, 24, 39, 0.14);
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-sizing: border-box;
}
.sf-lobby-chat-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(197, 160, 89, 0.25);
}
.sf-lobby-chat-scroll {
    max-height: 156px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-right: 2px;
}
.sf-lobby-chat-msg {
    border-radius: 11px;
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.26);
    padding: 6px 9px;
    font-size: 11px;
    color: #111827;
}
.sf-lobby-chat-panel.is-expanded { width: 440px; }
.sf-lobby-chat-panel.is-expanded .sf-lobby-chat-scroll { max-height: 280px; }
.sf-lobby-friends-scroll {
    max-height: 156px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding-right: 2px;
}
.sf-lobby-chat-panel.is-expanded .sf-lobby-friends-scroll { max-height: 280px; }
.sf-emote-chip {
    appearance: none;
    border-radius: 6px;
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.35);
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 700;
    color: #4B5563;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
}
.sf-emote-chip:hover {
    border-color: #C5A059;
    color: #111827;
    background: #FFFFFF;
}
.sf-lobby-stick-wrap { display: none; position: absolute; bottom: calc(74px + env(safe-area-inset-bottom, 0px)); left: 14px; z-index: 24; }
.sf-lobby-stick {
    width: 80px;
    height: 80px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.90);
    border: 1.5px solid rgba(197, 160, 89, 0.58);
    box-shadow: 0 10px 26px rgba(17, 24, 39, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    touch-action: none;
    cursor: pointer;
}
.sf-lobby-stick-knob {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    background: #C5A059;
    border: 2px solid #FFFFFF;
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.18);
    pointer-events: none;
}
.sf-lobby-bottom-stack {
    position: absolute;
    bottom: calc(74px + env(safe-area-inset-bottom, 0px));
    left: 12px;
    right: 12px;
    z-index: 22;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    pointer-events: none;
}
.sf-lobby-bottom-pill {
    pointer-events: auto;
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(197, 160, 89, 0.48);
    box-shadow: 0 10px 28px rgba(17, 24, 39, 0.12);
    max-width: 100%;
    box-sizing: border-box;
}
.sf-lobby-step-btn {
    appearance: none;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid rgba(197, 160, 89, 0.40);
    background: #FAF8F5;
    color: #111827;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}
.sf-lobby-step-btn:hover {
    background: #F5EFE2;
}
.sf-lobby-step-btn.is-active {
    background: #C5A059;
    color: #FFFFFF;
    border-color: #C5A059;
}
.sf-lobby-gold-crest-bar {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 25;
    min-height: 64px;
    padding: 6px 20px calc(7px + env(safe-area-inset-bottom, 0px));
    background: linear-gradient(180deg, rgba(252, 250, 245, 0.96) 0%, rgba(244, 238, 226, 0.99) 100%);
    border-top: 1.5px solid #C5A059;
    box-shadow: 0 -8px 26px rgba(17, 24, 39, 0.09), inset 0 1px 0 rgba(255, 255, 255, 0.85);
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 12px;
    pointer-events: auto;
    box-sizing: border-box;
}
.sf-lobby-gold-crest-bar::before {
    content: "";
    position: absolute;
    top: 3px;
    left: 18px;
    right: 18px;
    height: 1px;
    background: linear-gradient(90deg, rgba(197, 160, 89, 0.12) 0%, rgba(197, 160, 89, 0.65) 50%, rgba(197, 160, 89, 0.12) 100%);
    pointer-events: none;
}
.sf-lobby-crest-brand {
    justify-self: start;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #785A28;
    white-space: nowrap;
}
.sf-lobby-crest-ornament {
    justify-self: end;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(197, 160, 89, 0.78);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.16em;
    pointer-events: none;
}
.sf-lobby-gear-btn {
    position: relative;
    justify-self: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 40px;
    padding: 0 14px;
    border-radius: 999px;
    background: #FFFFFF;
    border: 1.5px solid rgba(197, 160, 89, 0.72);
    color: #111827;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(17, 24, 39, 0.08);
    transition: border-color 0.15s ease, transform 0.12s ease, background 0.15s ease;
    pointer-events: auto;
}
.sf-lobby-gear-btn:hover,
.sf-lobby-gear-btn.is-active {
    border-color: #C5A059;
    background: #FAF6EE;
    transform: translateY(-1px);
}
.sf-outer-gate-banner {
    background: #FAF8F5;
    border: 1.5px solid #C5A059;
    border-radius: 14px;
    padding: 14px 16px;
    text-align: center;
    font-size: 13.5px;
    font-weight: 600;
    line-height: 1.55;
    color: #1F2937;
    letter-spacing: 0.005em;
}
.sf-atelier-star-card {
    pointer-events: auto;
    width: 274px;
    max-width: calc(100vw - 32px);
    background: #FAF8F5;
    color: #111827;
    border: 1.5px solid #C5A059;
    border-radius: 16px;
    padding: 13px 15px;
    box-shadow: 0 18px 42px rgba(17, 24, 39, 0.16), 0 0 0 1px rgba(255, 255, 255, 0.9) inset;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 9px;
    box-sizing: border-box;
    text-align: center;
}
.sf-atelier-star-title {
    margin: 0;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 14px;
    font-weight: 700;
    color: #111827;
    line-height: 1.25;
}
.sf-atelier-star-row {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.sf-atelier-star-btn {
    appearance: none;
    border: none;
    background: transparent;
    padding: 2px 3px;
    font-size: 27px;
    line-height: 1;
    color: #DDD5C4;
    cursor: pointer;
    transition: transform 0.14s ease, color 0.14s ease;
}
.sf-atelier-star-btn.is-lit {
    color: #C5A059;
}
.sf-atelier-star-btn:hover {
    transform: scale(1.16);
    color: #C5A059;
}
.sf-atelier-star-preview {
    font-size: 24px;
    line-height: 1;
    color: #C5A059;
    letter-spacing: 3px;
    user-select: none;
}
.sf-atelier-star-thanks {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 800;
    color: #785A28;
}
.sf-atelier-star-edit-btn {
    appearance: none;
    border: 1px solid rgba(197, 160, 89, 0.45);
    background: #FFFFFF;
    color: #4B5563;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 10.5px;
    font-weight: 700;
    cursor: pointer;
}
.sf-atelier-star-edit-btn:hover {
    border-color: #C5A059;
    color: #111827;
}
.sf-atelier-star-feedback {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-top: 2px;
}
.sf-atelier-star-textarea {
    width: 100%;
    min-height: 58px;
    max-height: 110px;
    resize: vertical;
    border-radius: 10px;
    border: 1px solid rgba(197, 160, 89, 0.52);
    background: #FFFFFF;
    color: #111827;
    padding: 7px 9px;
    font-size: 12px;
    font-family: inherit;
    box-sizing: border-box;
    outline: none;
}
.sf-atelier-star-textarea:focus {
    border-color: #C5A059;
}
.sf-lobby-settings-backdrop {
    position: absolute;
    inset: 0;
    z-index: 48;
    background: rgba(17, 24, 39, 0.24);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    pointer-events: auto;
    box-sizing: border-box;
}
.sf-lobby-settings-card {
    width: 392px;
    max-width: calc(100vw - 24px);
    max-height: calc(100dvh - 36px);
    overflow-y: auto;
    background: #FFFFFF;
    color: #111827;
    border: 1px solid rgba(197, 160, 89, 0.55);
    border-radius: 20px;
    padding: 18px 20px;
    box-shadow: 0 22px 52px rgba(17, 24, 39, 0.18);
    display: flex;
    flex-direction: column;
    gap: 14px;
    box-sizing: border-box;
}
.sf-lobby-settings-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(197, 160, 89, 0.28);
}
.sf-lobby-settings-title {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 7px;
}
.sf-lobby-settings-section {
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.34);
    border-radius: 14px;
    padding: 11px 13px;
    display: flex;
    flex-direction: column;
    gap: 9px;
}
.sf-lobby-settings-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.sf-lobby-settings-label {
    font-size: 12px;
    font-weight: 800;
    color: #111827;
}
.sf-lobby-slider {
    width: 100%;
    height: 6px;
    accent-color: #C5A059;
    cursor: pointer;
}
.sf-lobby-keybind-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px;
    padding-top: 4px;
}
.sf-lobby-keybind-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 10px;
    background: #FFFFFF;
    border: 1px solid rgba(197, 160, 89, 0.35);
    cursor: pointer;
    font-size: 11px;
    font-weight: 700;
    color: #111827;
    text-align: left;
}
.sf-lobby-keybind-item:hover {
    border-color: #C5A059;
}
.sf-lobby-keybind-item.is-listening {
    border-color: #C5A059;
    background: #FAF3E3;
}
.sf-lobby-keybind-pill {
    padding: 2px 7px;
    border-radius: 6px;
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.48);
    font-size: 10px;
    font-weight: 800;
    color: #9A7738;
    white-space: nowrap;
}
.sf-lobby-unit-frame {
    pointer-events: auto;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 7px 13px 7px 8px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.96);
    border: 1.5px solid rgba(197, 160, 89, 0.60);
    box-shadow: 0 8px 24px rgba(17, 24, 39, 0.09);
    color: #111827;
}
.sf-lobby-unit-avatar {
    width: 38px;
    height: 38px;
    border-radius: 999px;
    background: #FAF8F5;
    border: 1.5px solid #C5A059;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 900;
    color: #9A7738;
    flex-shrink: 0;
}
.sf-lobby-unit-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 120px;
}
.sf-lobby-topright-stack {
    position: absolute;
    top: 14px;
    right: 18px;
    z-index: 25;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 9px;
    pointer-events: none;
}
.sf-lobby-minimap-wrap {
    position: relative;
    width: 232px;
    height: 232px;
    z-index: 25;
    pointer-events: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sf-lobby-minimap-canvas {
    width: 100%;
    height: 100%;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.95);
    border: 2.5px solid #C5A059;
    box-shadow: 0 12px 30px rgba(17, 24, 39, 0.15);
    display: block;
}
.sf-lobby-minimap-time {
    position: absolute;
    bottom: -9px;
    left: 50%;
    transform: translateX(-50%);
    padding: 2px 10px;
    border-radius: 999px;
    background: #FFFFFF;
    border: 1px solid #C5A059;
    color: #111827;
    font-size: 10.5px;
    font-weight: 800;
    line-height: 1.2;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(17, 24, 39, 0.10);
    cursor: help;
}
.sf-lobby-sanctum-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.sf-lobby-sanctum-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.34);
    font-size: 13px;
    font-weight: 800;
    color: #111827;
    transition: border-color 140ms ease, background-color 140ms ease;
}
.sf-lobby-sanctum-row.is-actionable {
    cursor: pointer;
}
.sf-lobby-sanctum-row.is-actionable:hover {
    background: #FFFFFF;
    border-color: #C5A059;
}
.sf-lobby-sanctum-row.is-done {
    background: #FFFFFF;
    border-color: #C5A059;
    cursor: default;
}
.sf-lobby-sanctum-check {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 900;
    flex-shrink: 0;
    background: #FEF2F2;
    border: 1.5px solid #FECACA;
    color: #DC2626;
    line-height: 1;
}
.sf-lobby-sanctum-row.is-done .sf-lobby-sanctum-check {
    background: #FAF6EE;
    border-color: #C5A059;
    color: #C5A059;
}
.sf-lobby-inline-panel {
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.42);
    border-radius: 14px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 9px;
}
.sf-lobby-inline-input {
    width: 100%;
    padding: 9px 11px;
    border-radius: 10px;
    border: 1px solid rgba(197, 160, 89, 0.48);
    background: #FFFFFF;
    color: #111827;
    font-size: 13px;
    font-weight: 600;
    outline: none;
    box-sizing: border-box;
}
.sf-lobby-inline-input:focus {
    border-color: #C5A059;
    box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.18);
}
.sf-lobby-pwd-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}
.sf-lobby-pwd-wrap .sf-lobby-inline-input {
    padding-right: 38px;
}
.sf-lobby-pwd-eye {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    appearance: none;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #6B7280;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
}
.sf-lobby-pwd-eye:hover {
    color: #111827;
    background: #FAF6EE;
}
.sf-lobby-inline-error {
    padding: 7px 10px;
    border-radius: 9px;
    background: #FEF2F2;
    border: 1px solid #FECACA;
    color: #DC2626;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.3;
}
.sf-lobby-inline-check-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #111827;
    cursor: pointer;
}
@media (max-width: 900px), (pointer: coarse) {
    .sf-lobby-topbar { padding: 8px 10px; gap: 6px; }
    .sf-lobby-actions { margin-right: 0; }
    .sf-lobby-unit-frame { padding: 5px 10px 5px 6px; gap: 7px; border-radius: 15px; }
    .sf-lobby-unit-avatar { width: 34px; height: 34px; font-size: 12px; }
    .sf-lobby-unit-meta { min-width: 108px; gap: 2px; }
    .sf-lobby-stick-wrap { display: block; bottom: calc(68px + env(safe-area-inset-bottom, 0px)); left: 10px; }
    .sf-lobby-stick { width: 68px; height: 68px; }
    .sf-lobby-topright-stack { top: 62px; right: 10px; gap: 6px; }
    .sf-lobby-minimap-wrap { width: 138px; height: 138px; }
    .sf-lobby-minimap-time { font-size: 9.5px; padding: 1px 7px; }
}
@media (max-width: 767px) {
    .sf-lobby-zone-badge { padding: 4px 8px; font-size: 10px; }
    .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 5px 8px; font-size: 11px; }
    .sf-lobby-kbd, .sf-lobby-hide-sm, .sf-lobby-crest-ornament, .sf-lobby-crest-brand { display: none !important; }
    .sf-lobby-chat-panel { bottom: calc(68px + env(safe-area-inset-bottom, 0px)); right: 10px; }
    .sf-lobby-chat-panel.is-expanded { width: calc(100vw - 20px); }
    .sf-lobby-keybind-grid { grid-template-columns: 1fr; }
    .sf-lobby-gold-crest-bar { display: flex; align-items: center; justify-content: center; padding: 5px 8px calc(6px + env(safe-area-inset-bottom, 0px)); min-height: 54px; gap: 6px; }
}
@media (max-width: 480px) {
    .sf-lobby-topright-stack { top: 64px; right: 8px; }
    .sf-lobby-unit-meta { min-width: 100px; }
    .sf-lobby-stick-wrap { bottom: calc(64px + env(safe-area-inset-bottom, 0px)); left: 8px; }
    .sf-lobby-stick { width: 64px; height: 64px; }
    .sf-lobby-bottom-stack { bottom: calc(68px + env(safe-area-inset-bottom, 0px)); }
    .sf-lobby-floating-card { width: 266px; padding: 11px 13px 10px; }
}
`;

export function ensureLobbyScopedStyles() {
    if (typeof document === 'undefined') return;
    let styleEl = document.getElementById('sf-3d-lobby-scoped-css');
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'sf-3d-lobby-scoped-css';
        styleEl.textContent = SF_LOBBY_SCOPED_CSS;
        (document.head || document.documentElement).appendChild(styleEl);
    } else if (!styleEl.textContent || styleEl.textContent.indexOf('.sf-atelier-star-card') === -1) {
        styleEl.textContent = SF_LOBBY_SCOPED_CSS;
    }
}

export function isLobbyFullscreenActive() {
    if (typeof document === 'undefined') return false;
    return Boolean(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
}

export function requestLobbyFullscreen(targetEl) {
    const el = targetEl || (typeof document !== 'undefined' ? document.documentElement : null);
    if (!el) return Promise.resolve(false);
    try {
        if (typeof el.requestFullscreen === 'function') {
            return Promise.resolve(el.requestFullscreen({ navigationUI: 'hide' })).then(() => true).catch(() => false);
        }
        if (typeof el.webkitRequestFullscreen === 'function') {
            el.webkitRequestFullscreen();
            return Promise.resolve(true);
        }
    } catch (err) {
        return Promise.resolve(false);
    }
    return Promise.resolve(false);
}

export function exitLobbyFullscreen() {
    if (!isLobbyFullscreenActive()) return Promise.resolve(false);
    try {
        if (typeof document.exitFullscreen === 'function') {
            return Promise.resolve(document.exitFullscreen()).then(() => true).catch(() => false);
        }
        if (typeof document.webkitExitFullscreen === 'function') {
            document.webkitExitFullscreen();
            return Promise.resolve(true);
        }
    } catch (err) {
        return Promise.resolve(false);
    }
    return Promise.resolve(false);
}

export function toggleLobbyFullscreen(targetEl) {
    if (isLobbyFullscreenActive()) {
        return exitLobbyFullscreen().then(() => false);
    }
    return requestLobbyFullscreen(targetEl).then(() => isLobbyFullscreenActive());
}

function clamp(v, lo, hi) {
    return Math.max(lo, Math.min(hi, v));
}

function lerp(a, b, t) {
    return a + (b - a) * t;
}

function normalizeAngleSigned(rad) {
    return (((rad + Math.PI) % TWO_PI) + TWO_PI) % TWO_PI - Math.PI;
}

function shortestAngleDiff(fromRad, toRad) {
    return normalizeAngleSigned(toRad - fromRad);
}

function lerpAngle(a, b, t) {
    return normalizeAngleSigned(a + shortestAngleDiff(a, b) * t);
}

function hexToRgb(hex) {
    const n = typeof hex === 'number' ? hex : parseInt(String(hex).replace('#', ''), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function sanitizeDisplayText(val) {
    return String(val || '').replace(/\s[\u2013\u2014]\s/g, ' · ').trim();
}

function mulberry32(seed) {
    let a = seed >>> 0;
    return function () {
        a |= 0;
        a = (a + 0x6D2B79F5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function applySrgbColorSpace(THREE, tex) {
    if (!tex) return tex;
    if ('colorSpace' in tex && THREE.SRGBColorSpace) {
        tex.colorSpace = THREE.SRGBColorSpace;
    } else if (THREE.sRGBEncoding) {
        tex.encoding = THREE.sRGBEncoding;
    }
    return tex;
}

function applyLinearColorSpace(THREE, tex) {
    if (!tex) return tex;
    if ('colorSpace' in tex && typeof THREE.NoColorSpace !== 'undefined') {
        tex.colorSpace = THREE.NoColorSpace;
    } else if (THREE.LinearEncoding) {
        tex.encoding = THREE.LinearEncoding;
    }
    return tex;
}

export function normalizeHeroCardData(slotNumber, rawHero) {
    const slot = clamp(parseInt(slotNumber, 10) || 1, 1, 999);
    const isPlaceholder = !rawHero || ((Boolean(rawHero.is_placeholder) || rawHero.occupied === false) && !rawHero.is_preview);

    if (isPlaceholder) {
        return {
            slot,
            occupied: false,
            is_placeholder: true,
            is_me: false,
            display_name: 'Freier Ehrenplatz',
            level: 1,
            level_title: FUNKI_LEVEL_TITLES[1],
            model_url: null,
            sparks: 0,
            sparks_formatted: '0',
            hero_score: 0,
            magma_highest_level: 0,
            ship_class: 'KLASSE C',
            titles_count: 0,
        };
    }

    const level = clamp(parseInt(rawHero.level, 10) || 1, 1, 10);
    const levelTitle = sanitizeDisplayText(rawHero.level_title || rawHero.rank_name || FUNKI_LEVEL_TITLES[level] || 'Ursprung');
    const sparksNum = Math.max(0, parseInt(rawHero.sparks ?? rawHero.funken_total_earned ?? rawHero.hero_score ?? (level * 250), 10) || 0);
    const sparksFormatted = rawHero.sparks_formatted
        ? sanitizeDisplayText(rawHero.sparks_formatted)
        : sparksNum.toLocaleString('de-DE');
    const heroScore = Math.max(sparksNum, parseInt(rawHero.hero_score ?? sparksNum, 10) || 0);
    const magmaHighest = Math.max(0, parseInt(rawHero.magma_highest_level ?? 0, 10) || 0);

    const fallbackClassLetter = level >= 9 ? 'S' : (level >= 6 ? 'A' : (level >= 3 ? 'B' : 'C'));
    const rawShipClass = sanitizeDisplayText(rawHero.ship_class || rawHero.rank_class || fallbackClassLetter).toUpperCase();
    const shipClass = rawShipClass.indexOf('KLASSE') === 0 ? rawShipClass : `KLASSE ${rawShipClass}`;

    const rawTitles = rawHero.titles_count ?? rawHero.diamonds_count;
    const titlesCount = (rawTitles !== undefined && rawTitles !== null)
        ? Math.max(0, parseInt(rawTitles, 10) || 0)
        : Math.max(1, Math.ceil(level / 2));

    return {
        slot,
        occupied: true,
        is_placeholder: false,
        is_me: Boolean(rawHero.is_me),
        is_preview: Boolean(rawHero.is_preview),
        display_name: sanitizeDisplayText(rawHero.display_name || `Held #${slot}`),
        level,
        level_title: levelTitle,
        model_url: rawHero.model_url || rawHero.glb_url || null,
        sparks: sparksNum,
        sparks_formatted: sparksFormatted,
        hero_score: heroScore,
        magma_highest_level: magmaHighest,
        ship_class: shipClass,
        titles_count: titlesCount,
    };
}

export function loadThreeLibrary() {
    if (window.THREE) {
        return Promise.resolve(window.THREE);
    }
    if (window.__sfThreeLoadingPromise) {
        return window.__sfThreeLoadingPromise;
    }
    window.__sfThreeLoadingPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = '/vendor/three/three.min.js';
        script.async = true;
        script.onload = () => {
            if (window.THREE) {
                resolve(window.THREE);
            } else {
                window.__sfThreeLoadingPromise = null;
                reject(new Error('Three.js konnte nicht initialisiert werden.'));
            }
        };
        script.onerror = () => {
            window.__sfThreeLoadingPromise = null;
            reject(new Error('Three.js konnte nicht geladen werden.'));
        };
        document.head.appendChild(script);
    });
    return window.__sfThreeLoadingPromise;
}

/**
 * Erzeugt eine schnelle, vorberechnete Studio-MatCap-Textur fuer Avatare, 24K-Gold-Saeulen & Funki-Statuen
 * (null Echtzeit-Lichtberechnung pro Gliedmasse, edle 24K-Gold- und Obsidian-Glanzkanten).
 */
function buildStudioMatcapTexture(THREE, baseHex, specHex, rimHex, specPower, rimStrength, disposables) {
    const size = 128;
    const c = document.createElement('canvas');
    c.width = size;
    c.height = size;
    const ctx = c.getContext('2d');
    const img = ctx.createImageData(size, size);
    const d = img.data;

    const base = hexToRgb(baseHex);
    const spec = hexToRgb(specHex);
    const rim = hexToRgb(rimHex);

    const lx1 = -0.34;
    const ly1 = 0.62;
    const lz1 = 0.706;
    const hx1 = -0.19;
    const hy1 = 0.38;
    const hz1 = 0.905;
    const lx2 = 0.50;
    const ly2 = 0.34;
    const lz2 = 0.796;
    const hx2 = 0.31;
    const hy2 = 0.22;
    const hz2 = 0.925;
    const half = size * 0.5;

    for (let y = 0; y < size; y++) {
        const ny = (half - y - 0.5) / half;
        for (let x = 0; x < size; x++) {
            const nx = (x + 0.5 - half) / half;
            const r2 = nx * nx + ny * ny;
            let r;
            let g;
            let b;

            if (r2 <= 1.0) {
                const nz = Math.sqrt(Math.max(0, 1.0 - r2));
                const ndl1 = Math.max(0, nx * lx1 + ny * ly1 + nz * lz1);
                const ndl2 = Math.max(0, nx * lx2 + ny * ly2 + nz * lz2);
                const skyBounce = 0.38 + 0.32 * (ny * 0.5 + 0.5);
                const horizon = Math.exp(-Math.pow((ny + 0.14) * 5.0, 2)) * (specPower >= 22 ? 0.13 : 0.04);
                const hemi = skyBounce + 0.50 * ndl1 + 0.22 * ndl2 + horizon;

                const ndh1 = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
                const ndh2 = Math.max(0, nx * hx2 + ny * hy2 + nz * hz2);
                const spBroad = Math.pow(ndh1, Math.max(6, specPower * 0.45)) * 0.28;
                const spCrisp = Math.pow(ndh1, specPower * 1.35) * 0.82 + Math.pow(ndh2, specPower * 0.9) * 0.20;
                const sp = spBroad + spCrisp;
                const fresnel = Math.pow(1.0 - nz, 2.4) * rimStrength;

                r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
                g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
                b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
            } else {
                const len = Math.sqrt(r2);
                const uy = ny / len;
                const h2 = 0.36 + 0.26 * (uy * 0.5 + 0.5);
                r = base[0] * h2 + rim[0] * rimStrength * 0.9;
                g = base[1] * h2 + rim[1] * rimStrength * 0.9;
                b = base[2] * h2 + rim[2] * rimStrength * 0.9;
            }

            const p = (y * size + x) * 4;
            d[p] = clamp(Math.round(r), 0, 255);
            d[p + 1] = clamp(Math.round(g), 0, 255);
            d[p + 2] = clamp(Math.round(b), 0, 255);
            d[p + 3] = 255;
        }
    }

    ctx.putImageData(img, 0, 0);
    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

function downscaleImageToCanvas(imgEl, maxSide) {
    const srcW = imgEl.naturalWidth || imgEl.width || 256;
    const srcH = imgEl.naturalHeight || imgEl.height || 256;
    if (srcW <= maxSide && srcH <= maxSide) {
        return imgEl;
    }
    const scale = Math.min(maxSide / srcW, maxSide / srcH);
    const dstW = Math.max(64, Math.round(srcW * scale));
    const dstH = Math.max(64, Math.round(srcH * scale));
    const c = document.createElement('canvas');
    c.width = dstW;
    c.height = dstH;
    const ctx = c.getContext('2d');
    ctx.drawImage(imgEl, 0, 0, dstW, dstH);
    return c;
}

/**
 * Nativer, abhaengigkeitsfreier GLB 2.0 Parser fuer komprimierte Seelenfunke Produkt- & Funki-Modelle (0 externe CDN-Assets).
 */
export function parseGlbBinary(THREE, arrayBuffer, maxAniso, disposables) {
    if (!arrayBuffer || arrayBuffer.byteLength < 20) {
        return Promise.reject(new Error('Ungueltiger GLB-Datenstrom.'));
    }

    const dv = new DataView(arrayBuffer);
    const magic = dv.getUint32(0, true);
    const version = dv.getUint32(4, true);
    if (magic !== 0x46546C67 || version !== 2) {
        return Promise.reject(new Error('Nicht unterstuetztes GLB-2.0-Format.'));
    }

    let jsonObj = null;
    let binOffset = 0;
    let binLength = 0;
    let offset = 12;

    while (offset + 8 <= arrayBuffer.byteLength) {
        const chunkLen = dv.getUint32(offset, true);
        const chunkType = dv.getUint32(offset + 4, true);
        const dataStart = offset + 8;
        if (dataStart + chunkLen > arrayBuffer.byteLength) break;

        if (chunkType === 0x4E4F534A) {
            const jsonBytes = new Uint8Array(arrayBuffer, dataStart, chunkLen);
            const jsonStr = new TextDecoder('utf-8').decode(jsonBytes);
            jsonObj = JSON.parse(jsonStr);
        } else if (chunkType === 0x004E4942) {
            binOffset = dataStart;
            binLength = chunkLen;
        }
        offset = dataStart + chunkLen;
    }

    if (!jsonObj || !binLength) {
        return Promise.reject(new Error('Unvollstaendige GLB-Chunks.'));
    }

    const COMP_SPECS = {
        5120: { Ctor: Int8Array, bytes: 1, normDiv: 127.0, read: (v, o) => v.getInt8(o) },
        5121: { Ctor: Uint8Array, bytes: 1, normDiv: 255.0, read: (v, o) => v.getUint8(o) },
        5122: { Ctor: Int16Array, bytes: 2, normDiv: 32767.0, read: (v, o) => v.getInt16(o, true) },
        5123: { Ctor: Uint16Array, bytes: 2, normDiv: 65535.0, read: (v, o) => v.getUint16(o, true) },
        5125: { Ctor: Uint32Array, bytes: 4, normDiv: 4294967295.0, read: (v, o) => v.getUint32(o, true) },
        5126: { Ctor: Float32Array, bytes: 4, normDiv: 1.0, read: (v, o) => v.getFloat32(o, true) },
    };
    const TYPE_COUNTS = { SCALAR: 1, VEC2: 2, VEC3: 3, VEC4: 4, MAT4: 16 };

    function readAccessorArray(accIdx) {
        if (accIdx === undefined || accIdx === null || !jsonObj.accessors || !jsonObj.accessors[accIdx]) {
            return null;
        }
        const acc = jsonObj.accessors[accIdx];
        const bv = (jsonObj.bufferViews && jsonObj.bufferViews[acc.bufferView]) ? jsonObj.bufferViews[acc.bufferView] : null;
        const spec = COMP_SPECS[acc.componentType];
        const itemSize = TYPE_COUNTS[acc.type] || 1;
        if (!bv || !spec) return null;

        const count = acc.count || 0;
        const byteStart = binOffset + (bv.byteOffset || 0) + (acc.byteOffset || 0);
        const packedStride = itemSize * spec.bytes;
        const stride = bv.byteStride || packedStride;

        let rawArr;
        if (stride === packedStride && (byteStart % spec.bytes) === 0) {
            rawArr = new spec.Ctor(arrayBuffer, byteStart, count * itemSize).slice();
        } else {
            rawArr = new spec.Ctor(count * itemSize);
            for (let i = 0; i < count; i++) {
                const baseOff = byteStart + i * stride;
                for (let c = 0; c < itemSize; c++) {
                    rawArr[i * itemSize + c] = spec.read(dv, baseOff + c * spec.bytes);
                }
            }
        }

        if (acc.normalized && acc.componentType !== 5126) {
            const floatOut = new Float32Array(rawArr.length);
            for (let k = 0; k < rawArr.length; k++) {
                floatOut[k] = Math.max(-1.0, rawArr[k] / spec.normDiv);
            }
            return { array: floatOut, itemSize };
        }

        return { array: rawArr, itemSize };
    }

    const localTexPromises = {};
    const localMatPromises = {};

    function loadEmbeddedTexture(texIndex, isSrgb) {
        if (texIndex === undefined || texIndex === null || !jsonObj.textures || !jsonObj.textures[texIndex]) {
            return Promise.resolve(null);
        }
        const key = `${texIndex}:${isSrgb ? 'srgb' : 'lin'}`;
        if (localTexPromises[key]) return localTexPromises[key];

        const texDef = jsonObj.textures[texIndex];
        const imgDef = (jsonObj.images && texDef.source !== undefined) ? jsonObj.images[texDef.source] : null;
        if (!imgDef || imgDef.bufferView === undefined || !jsonObj.bufferViews || !jsonObj.bufferViews[imgDef.bufferView]) {
            return Promise.resolve(null);
        }

        const bv = jsonObj.bufferViews[imgDef.bufferView];
        const imgStart = binOffset + (bv.byteOffset || 0);
        const imgBytes = new Uint8Array(arrayBuffer, imgStart, bv.byteLength);
        const blob = new Blob([imgBytes], { type: imgDef.mimeType || 'image/jpeg' });
        const blobUrl = URL.createObjectURL(blob);

        localTexPromises[key] = new Promise((resolve) => {
            const imgEl = new Image();
            imgEl.onload = () => {
                URL.revokeObjectURL(blobUrl);
                const source = downscaleImageToCanvas(imgEl, 512);
                const tex = new THREE.Texture(source);
                tex.flipY = false;
                tex.wrapS = THREE.RepeatWrapping;
                tex.wrapT = THREE.RepeatWrapping;
                tex.generateMipmaps = true;
                tex.minFilter = THREE.LinearMipmapLinearFilter;
                tex.magFilter = THREE.LinearFilter;
                tex.anisotropy = Math.min(maxAniso || 4, 4);
                if (isSrgb) {
                    applySrgbColorSpace(THREE, tex);
                } else {
                    applyLinearColorSpace(THREE, tex);
                }
                tex.needsUpdate = true;
                if (disposables) disposables.push(tex);
                resolve(tex);
            };
            imgEl.onerror = () => {
                URL.revokeObjectURL(blobUrl);
                resolve(null);
            };
            imgEl.src = blobUrl;
        });

        return localTexPromises[key];
    }

    function buildMaterial(matIndex) {
        const key = (matIndex === undefined || matIndex === null) ? 'default' : String(matIndex);
        if (localMatPromises[key]) return localMatPromises[key];

        const matDef = (jsonObj.materials && jsonObj.materials[matIndex]) ? jsonObj.materials[matIndex] : {};
        const pbr = matDef.pbrMetallicRoughness || {};
        const baseIdx = (pbr.baseColorTexture && pbr.baseColorTexture.index !== undefined) ? pbr.baseColorTexture.index : null;
        const mrIdx = (pbr.metallicRoughnessTexture && pbr.metallicRoughnessTexture.index !== undefined) ? pbr.metallicRoughnessTexture.index : null;
        const normIdx = (matDef.normalTexture && matDef.normalTexture.index !== undefined) ? matDef.normalTexture.index : null;
        const emisIdx = (matDef.emissiveTexture && matDef.emissiveTexture.index !== undefined) ? matDef.emissiveTexture.index : null;

        localMatPromises[key] = Promise.all([
            loadEmbeddedTexture(baseIdx, true),
            loadEmbeddedTexture(mrIdx, false),
            loadEmbeddedTexture(normIdx, false),
            loadEmbeddedTexture(emisIdx, true),
        ]).then((texArr) => {
            const [baseTex, mrTex, normTex, emisTex] = texArr;
            const bcf = Array.isArray(pbr.baseColorFactor) ? pbr.baseColorFactor : [1, 1, 1, 1];
            const rawMetal = (typeof pbr.metallicFactor === 'number') ? pbr.metallicFactor : (mrTex ? 1.0 : 0.18);
            const rawRough = (typeof pbr.roughnessFactor === 'number') ? pbr.roughnessFactor : 0.65;

            const opts = {
                name: matDef.name || '',
                color: new THREE.Color(bcf[0], bcf[1], bcf[2]),
                metalness: clamp(rawMetal, 0.0, 1.0),
                roughness: clamp(rawRough, 0.03, 1.0),
                side: matDef.doubleSided ? THREE.DoubleSide : THREE.FrontSide,
            };
            if (baseTex) opts.map = baseTex;
            if (mrTex) {
                opts.roughnessMap = mrTex;
                opts.metalnessMap = mrTex;
            }
            if (normTex) {
                opts.normalMap = normTex;
                const ns = (matDef.normalTexture && typeof matDef.normalTexture.scale === 'number') ? matDef.normalTexture.scale : 0.85;
                opts.normalScale = new THREE.Vector2(ns, ns);
            }
            if (emisTex) {
                opts.emissiveMap = emisTex;
                const ef = Array.isArray(matDef.emissiveFactor) ? matDef.emissiveFactor : [1, 1, 1];
                opts.emissive = new THREE.Color(ef[0], ef[1], ef[2]);
            } else if (Array.isArray(matDef.emissiveFactor)) {
                opts.emissive = new THREE.Color(matDef.emissiveFactor[0], matDef.emissiveFactor[1], matDef.emissiveFactor[2]);
            }
            if (matDef.alphaMode === 'MASK') {
                opts.alphaTest = (typeof matDef.alphaCutoff === 'number') ? matDef.alphaCutoff : 0.45;
            } else if (matDef.alphaMode === 'BLEND') {
                opts.transparent = true;
                opts.opacity = (typeof bcf[3] === 'number') ? bcf[3] : 1.0;
            }

            const mat = new THREE.MeshStandardMaterial(opts);
            mat.userData = { extensions: matDef.extensions || {} };
            if (disposables) disposables.push(mat);
            return mat;
        });

        return localMatPromises[key];
    }

    const tmpMat4 = new THREE.Matrix4();

    function buildNodeAsync(nodeIdx) {
        const nDef = (jsonObj.nodes && jsonObj.nodes[nodeIdx]) ? jsonObj.nodes[nodeIdx] : null;
        if (!nDef) return Promise.resolve(new THREE.Group());

        const nodeObj = new THREE.Group();
        if (Array.isArray(nDef.matrix) && nDef.matrix.length === 16) {
            tmpMat4.fromArray(nDef.matrix);
            tmpMat4.decompose(nodeObj.position, nodeObj.quaternion, nodeObj.scale);
        } else {
            if (Array.isArray(nDef.translation)) nodeObj.position.fromArray(nDef.translation);
            if (Array.isArray(nDef.rotation)) nodeObj.quaternion.fromArray(nDef.rotation);
            if (Array.isArray(nDef.scale)) nodeObj.scale.fromArray(nDef.scale);
        }

        const primTasks = [];
        if (nDef.mesh !== undefined && jsonObj.meshes && jsonObj.meshes[nDef.mesh]) {
            const prims = jsonObj.meshes[nDef.mesh].primitives || [];
            for (let pi = 0; pi < prims.length; pi++) {
                const prim = prims[pi];
                primTasks.push(buildMaterial(prim.material).then((mat) => {
                    const geo = new THREE.BufferGeometry();
                    const attrs = prim.attributes || {};
                    const posData = readAccessorArray(attrs.POSITION);
                    if (!posData) return;
                    geo.setAttribute('position', new THREE.BufferAttribute(posData.array, 3));

                    const normData = readAccessorArray(attrs.NORMAL);
                    if (normData) {
                        geo.setAttribute('normal', new THREE.BufferAttribute(normData.array, 3));
                    }

                    const uvData = readAccessorArray(attrs.TEXCOORD_0);
                    if (uvData) {
                        geo.setAttribute('uv', new THREE.BufferAttribute(uvData.array, 2));
                    }

                    const colData = readAccessorArray(attrs.COLOR_0);
                    if (colData) {
                        geo.setAttribute('color', new THREE.BufferAttribute(colData.array, colData.itemSize));
                        mat.vertexColors = true;
                    }

                    const idxData = readAccessorArray(prim.indices);
                    if (idxData) {
                        geo.setIndex(new THREE.BufferAttribute(idxData.array, 1));
                    }

                    if (!normData) {
                        geo.computeVertexNormals();
                    }
                    geo.computeBoundingBox();
                    geo.computeBoundingSphere();
                    if (disposables) disposables.push(geo);

                    const mesh = new THREE.Mesh(geo, mat);
                    nodeObj.add(mesh);
                }));
            }
        }

        const childIndices = Array.isArray(nDef.children) ? nDef.children : [];
        const childTasks = childIndices.map((ci) => buildNodeAsync(ci).then((childGrp) => {
            nodeObj.add(childGrp);
        }));

        return Promise.all(primTasks.concat(childTasks)).then(() => nodeObj);
    }

    const sceneIdx = (typeof jsonObj.scene === 'number') ? jsonObj.scene : 0;
    const rootNodes = (jsonObj.scenes && jsonObj.scenes[sceneIdx] && Array.isArray(jsonObj.scenes[sceneIdx].nodes))
        ? jsonObj.scenes[sceneIdx].nodes
        : [0];

    const rootGroup = new THREE.Group();
    return Promise.all(rootNodes.map((ri) => buildNodeAsync(ri).then((child) => {
        rootGroup.add(child);
    }))).then(() => ({
        scene: rootGroup,
        animations: Array.isArray(jsonObj.animations) ? jsonObj.animations : [],
    }));
}

/**
 * Erzeugt ein warmgoldenes Louvre- & Pantheon-Environment via PMREMGenerator,
 * damit K9-Kristallglas, 24K-Gold-Kolonnaden und Funki-GLBs ohne Echtzeit-PointLights brillant reflektieren.
 */
function buildLouvreStudioEnvironment(THREE, renderer, disposables) {
    if (!THREE.PMREMGenerator) return null;

    const pmrem = new THREE.PMREMGenerator(renderer);
    if (typeof pmrem.compileCubemapShader === 'function') {
        pmrem.compileCubemapShader();
    }

    const envScene = new THREE.Scene();
    envScene.background = new THREE.Color(0xf6efe2);

    const tempMeshes = [];
    const addLightPanel = (w, h, colorHex, x, y, z, rx, ry, rz) => {
        const g = new THREE.PlaneGeometry(w, h);
        const m = new THREE.MeshBasicMaterial({ color: colorHex, side: THREE.DoubleSide });
        const mesh = new THREE.Mesh(g, m);
        mesh.position.set(x, y, z);
        mesh.rotation.set(rx || 0, ry || 0, rz || 0);
        envScene.add(mesh);
        tempMeshes.push(g, m);
    };

    addLightPanel(22, 22, 0xfffdf8, 0, 14, 0, Math.PI * 0.5, 0, 0);
    addLightPanel(18, 6.8, 0xfff4d8, 0, 9.5, -11, 0.52, 0, 0);
    addLightPanel(18, 6.8, 0xfff4d8, 0, 9.5, 11, -0.52, 0, 0);
    addLightPanel(16, 6.2, 0xfcf7eb, -11, 9.0, 0, 0, Math.PI * 0.5, -0.45);
    addLightPanel(16, 6.2, 0xfcf7eb, 11, 9.0, 0, 0, -Math.PI * 0.5, 0.45);

    addLightPanel(22, 2.4, 0xfde69a, -13, 3.2, 0, 0, Math.PI * 0.5, 0);
    addLightPanel(22, 2.4, 0xfde69a, 13, 3.2, 0, 0, -Math.PI * 0.5, 0);
    addLightPanel(18, 2.0, 0xe4bf62, 0, 3.4, -13, 0, 0, 0);
    addLightPanel(18, 1.8, 0xfff3d1, 0, 3.6, 13, 0, Math.PI, 0);
    addLightPanel(32, 32, 0xe5dac7, 0, -6, 0, -Math.PI * 0.5, 0, 0);

    const envRT = pmrem.fromScene(envScene, 0.03);
    tempMeshes.forEach((d) => d.dispose());
    pmrem.dispose();

    if (disposables && envRT) {
        disposables.push(envRT);
    }
    return envRT ? envRT.texture : null;
}

function buildNeroMarquinaFloorMaps(THREE, maxAniso, isMobile, repeatX, repeatZ, disposables) {
    const S = isMobile ? 512 : 1024;

    const cAlbedo = document.createElement('canvas');
    cAlbedo.width = S;
    cAlbedo.height = S;
    const ctxA = cAlbedo.getContext('2d');

    const cRough = document.createElement('canvas');
    cRough.width = S;
    cRough.height = S;
    const ctxR = cRough.getContext('2d');

    const rng = mulberry32(20261116);

    ctxA.fillStyle = '#090b10';
    ctxA.fillRect(0, 0, S, S);

    ctxR.fillStyle = 'rgb(40, 40, 40)';
    ctxR.fillRect(0, 0, S, S);

    for (let i = 0; i < 42; i++) {
        const cx = rng() * S;
        const cy = rng() * S;
        const rad = (0.08 + rng() * 0.20) * S;
        const bright = rng() > 0.5;
        const bx = Math.max(0, Math.floor(cx - rad));
        const by = Math.max(0, Math.floor(cy - rad));
        const bw = Math.min(S - bx, Math.ceil(rad * 2));
        const bh = Math.min(S - by, Math.ceil(rad * 2));

        const gA = ctxA.createRadialGradient(cx, cy, rad * 0.05, cx, cy, rad);
        gA.addColorStop(0, bright ? 'rgba(24, 29, 40, 0.24)' : 'rgba(4, 5, 8, 0.30)');
        gA.addColorStop(1, 'rgba(9, 11, 16, 0)');
        ctxA.fillStyle = gA;
        ctxA.fillRect(bx, by, bw, bh);

        const gR = ctxR.createRadialGradient(cx, cy, rad * 0.05, cx, cy, rad);
        gR.addColorStop(0, bright ? 'rgba(56, 56, 56, 0.18)' : 'rgba(28, 28, 28, 0.18)');
        gR.addColorStop(1, 'rgba(40, 40, 40, 0)');
        ctxR.fillStyle = gR;
        ctxR.fillRect(bx, by, bw, bh);
    }

    const drawMarbleVein = (isGold, thicknessScale) => {
        let x = rng() * S;
        let y = rng() * S;
        let angle = (rng() * 0.65 + 0.18) * Math.PI * (rng() > 0.5 ? 1 : -1);
        const steps = 52 + Math.floor(rng() * 44);
        const stepLen = (S / 90) * (0.75 + rng() * 0.65);

        ctxA.beginPath();
        ctxR.beginPath();
        ctxA.moveTo(x, y);
        ctxR.moveTo(x, y);

        for (let s = 0; s < steps; s++) {
            angle += (rng() - 0.5) * 0.44;
            x += Math.cos(angle) * stepLen;
            y += Math.sin(angle) * stepLen;
            ctxA.lineTo(x, y);
            ctxR.lineTo(x, y);

            if (rng() > 0.87) {
                let bx = x;
                let by = y;
                let bAng = angle + (rng() > 0.5 ? 0.58 : -0.58);
                ctxA.moveTo(bx, by);
                for (let bs = 0; bs < 12; bs++) {
                    bAng += (rng() - 0.5) * 0.35;
                    bx += Math.cos(bAng) * stepLen * 0.6;
                    by += Math.sin(bAng) * stepLen * 0.6;
                    ctxA.lineTo(bx, by);
                }
                ctxA.moveTo(x, y);
            }
        }

        const lw = (isGold ? (1.1 + rng() * 2.0) : (0.9 + rng() * 2.3)) * (S / 1024) * thicknessScale;
        ctxA.strokeStyle = isGold
            ? `rgba(197, 160, 89, ${(0.28 + rng() * 0.34).toFixed(2)})`
            : `rgba(236, 240, 247, ${(0.16 + rng() * 0.28).toFixed(2)})`;
        ctxA.lineWidth = lw;
        ctxA.lineCap = 'round';
        ctxA.lineJoin = 'round';
        ctxA.stroke();

        ctxR.strokeStyle = isGold ? 'rgba(24, 24, 24, 0.42)' : 'rgba(96, 96, 96, 0.36)';
        ctxR.lineWidth = lw * 1.3;
        ctxR.stroke();
    };

    for (let v = 0; v < 32; v++) drawMarbleVein(false, 1.0);
    for (let gv = 0; gv < 16; gv++) drawMarbleVein(true, 0.95);

    const slabs = 4;
    const step = S / slabs;
    for (let i = 0; i <= slabs; i++) {
        const pos = Math.round(i * step);
        ctxA.fillStyle = 'rgba(197, 160, 89, 0.16)';
        ctxA.fillRect(pos - 1, 0, 2, S);
        ctxA.fillRect(0, pos - 1, S, 2);

        ctxR.fillStyle = 'rgb(126, 126, 126)';
        ctxR.fillRect(pos - 2, 0, 3, S);
        ctxR.fillRect(0, pos - 2, S, 3);
    }

    const setupRepeatTex = (canvas, isSrgb) => {
        const t = new THREE.CanvasTexture(canvas);
        t.wrapS = THREE.RepeatWrapping;
        t.wrapT = THREE.RepeatWrapping;
        t.repeat.set(repeatX, repeatZ);
        t.generateMipmaps = true;
        t.minFilter = THREE.LinearMipmapLinearFilter;
        t.magFilter = THREE.LinearFilter;
        t.anisotropy = Math.min(maxAniso || 4, 8);
        if (isSrgb) {
            applySrgbColorSpace(THREE, t);
        } else {
            applyLinearColorSpace(THREE, t);
        }
        t.needsUpdate = true;
        if (disposables) disposables.push(t);
        return t;
    };

    return {
        map: setupRepeatTex(cAlbedo, true),
        roughnessMap: setupRepeatTex(cRough, false),
    };
}

/**
 * 1:1 Polierter Nero-Marquina-Marmorboden (#0B0E14 / #121620) fuer die Halle der Helden
 * mit MeshStandardMaterial, 24K-Goldadern (#C5A059 / #D4AF37), Carrara-Nuancen (#ECEAE4),
 * Saphir-Einfassung (#133B5C), Sonnenmedaillon und spiegelnder Roughness-Map!
 */
function buildHallOfHeroesMarbleMaps(THREE, maxAniso, disposables) {
    const S = 1024;
    const cAlbedo = document.createElement('canvas');
    cAlbedo.width = S;
    cAlbedo.height = S;
    const ctx = cAlbedo.getContext('2d');

    const cRough = document.createElement('canvas');
    cRough.width = S;
    cRough.height = S;
    const ctxR = cRough.getContext('2d');

    // Warmer italienischer Calacatta-Gold-Marmor (#FAF7F0 bis #ECEAE4) mit feiner Saphir-Nuance (#133B5C)
    const lg = ctx.createLinearGradient(0, 0, S, S);
    lg.addColorStop(0.0, '#FAF7F0');
    lg.addColorStop(0.52, '#F4EFE4');
    lg.addColorStop(1.0, '#ECEAE4');
    ctx.fillStyle = lg;
    ctx.fillRect(0, 0, S, S);

    ctxR.fillStyle = '#292929';
    ctxR.fillRect(0, 0, S, S);

    const slabs = 4;
    const step = S / slabs;
    const rng = mulberry32(20261445);
    for (let r = 0; r < slabs; r++) {
        for (let col = 0; col < slabs; col++) {
            const delta = Math.round((rng() - 0.5) * 8);
            ctx.fillStyle = 'rgba(' + (246 + delta) + ', ' + (240 + delta) + ', ' + (228 + delta) + ', 0.34)';
            ctx.fillRect(col * step + 1, r * step + 1, step - 2, step - 2);
            ctxR.fillStyle = 'rgb(' + (40 + delta) + ', ' + (40 + delta) + ', ' + (40 + delta) + ')';
            ctxR.fillRect(col * step + 1, r * step + 1, step - 2, step - 2);
        }
    }

    for (let i = 0; i < 46; i++) {
        const isGoldVein = (i % 3 === 0);
        ctx.strokeStyle = isGoldVein
            ? (i % 6 === 0 ? 'rgba(197, 160, 89, 0.34)' : 'rgba(212, 175, 55, 0.24)')
            : (i % 9 === 0 ? 'rgba(19, 59, 92, 0.08)' : 'rgba(182, 170, 152, 0.26)');
        ctx.lineWidth = isGoldVein ? (i % 6 === 0 ? 2.4 : 1.5) : 1.4;
        ctx.beginPath();
        let x = rng() * S;
        let y = rng() * S;
        ctx.moveTo(x, y);
        for (let s = 0; s < 6; s++) {
            x += (rng() - 0.38) * 210;
            y += (rng() - 0.44) * 165;
            ctx.lineTo(x, y);
        }
        ctx.stroke();

        ctxR.strokeStyle = isGoldVein ? '#202020' : '#343434';
        ctxR.lineWidth = ctx.lineWidth;
        ctxR.beginPath();
        ctxR.moveTo(x, y);
        ctxR.lineTo(x + 12, y + 8);
        ctxR.stroke();
    }

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.22)';
    ctx.lineWidth = 1.1;
    ctxR.strokeStyle = '#525252';
    ctxR.lineWidth = 1.4;
    for (let g = 1; g < slabs; g++) {
        const p = g * step;
        ctx.beginPath();
        ctx.moveTo(p, 0);
        ctx.lineTo(p, S);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(0, p);
        ctx.lineTo(S, p);
        ctx.stroke();

        ctxR.beginPath();
        ctxR.moveTo(p, 0);
        ctxR.lineTo(p, S);
        ctxR.stroke();
        ctxR.beginPath();
        ctxR.moveTo(0, p);
        ctxR.lineTo(S, p);
        ctxR.stroke();
    }

    const albedoTex = new THREE.CanvasTexture(cAlbedo);
    albedoTex.wrapS = THREE.RepeatWrapping;
    albedoTex.wrapT = THREE.RepeatWrapping;
    albedoTex.repeat.set(3.2, 3.2);
    albedoTex.generateMipmaps = true;
    albedoTex.minFilter = THREE.LinearMipmapLinearFilter;
    albedoTex.magFilter = THREE.LinearFilter;
    albedoTex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, albedoTex);
    albedoTex.needsUpdate = true;
    if (disposables) disposables.push(albedoTex);

    const roughTex = new THREE.CanvasTexture(cRough);
    roughTex.wrapS = THREE.RepeatWrapping;
    roughTex.wrapT = THREE.RepeatWrapping;
    roughTex.repeat.set(3.2, 3.2);
    roughTex.generateMipmaps = true;
    roughTex.minFilter = THREE.LinearMipmapLinearFilter;
    roughTex.magFilter = THREE.LinearFilter;
    roughTex.anisotropy = Math.min(maxAniso || 4, 8);
    roughTex.needsUpdate = true;
    if (disposables) disposables.push(roughTex);

    return { map: albedoTex, roughnessMap: roughTex };
}

function buildHallOfHeroesMarbleTexture(THREE, maxAniso, disposables) {
    const maps = buildHallOfHeroesMarbleMaps(THREE, maxAniso, disposables);
    return maps.map;
}

function buildContactShadowTexture(THREE, disposables) {
    const S = 128;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');

    const g = ctx.createRadialGradient(S * 0.5, S * 0.5, S * 0.08, S * 0.5, S * 0.5, S * 0.49);
    g.addColorStop(0.0, 'rgba(34, 26, 16, 0.36)');
    g.addColorStop(0.48, 'rgba(34, 26, 16, 0.17)');
    g.addColorStop(0.78, 'rgba(34, 26, 16, 0.05)');
    g.addColorStop(1.0, 'rgba(34, 26, 16, 0.0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, S, S);

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

function buildSparkParticleTexture(THREE, disposables) {
    const S = 64;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(S * 0.5, S * 0.5, 1, S * 0.5, S * 0.5, S * 0.48);
    g.addColorStop(0.0, 'rgba(255, 255, 255, 1.0)');
    g.addColorStop(0.28, 'rgba(255, 238, 170, 0.92)');
    g.addColorStop(0.65, 'rgba(212, 175, 55, 0.36)');
    g.addColorStop(1.0, 'rgba(197, 160, 89, 0.0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, S, S);
    const tex = new THREE.CanvasTexture(c);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

function buildLouvreFriezeTexture(THREE, maxAniso, titleText, subText, disposables) {
    const W = 1024;
    const H = 256;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#0c0f16');
    bg.addColorStop(0.5, '#07090e');
    bg.addColorStop(1, '#0c0f16');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 5;
    ctx.strokeRect(10, 10, W - 20, H - 20);

    ctx.strokeStyle = 'rgba(230, 198, 135, 0.45)';
    ctx.lineWidth = 1.5;
    ctx.strokeRect(20, 20, W - 40, H - 40);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const goldGrad = ctx.createLinearGradient(0, H * 0.18, 0, H * 0.72);
    goldGrad.addColorStop(0.0, '#FFF8E1');
    goldGrad.addColorStop(0.38, '#E6C687');
    goldGrad.addColorStop(0.72, '#C5A059');
    goldGrad.addColorStop(1.0, '#9A7738');

    ctx.font = '700 58px Georgia, "Times New Roman", serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '8px';
    }
    ctx.fillStyle = 'rgba(0, 0, 0, 0.85)';
    ctx.fillText(titleText, W * 0.5 + 2, H * 0.43 + 3, W - 80);
    ctx.fillStyle = goldGrad;
    ctx.fillText(titleText, W * 0.5, H * 0.43, W - 80);

    const isLongSub = String(subText || '').length > 28;
    ctx.font = isLongSub
        ? '600 19px system-ui, -apple-system, "Segoe UI", sans-serif'
        : '600 22px system-ui, -apple-system, "Segoe UI", sans-serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = isLongSub ? '2.5px' : '5px';
    }
    const subMaxWidth = isLongSub ? (W - 64) : (W - 90);
    ctx.fillStyle = 'rgba(0, 0, 0, 0.82)';
    ctx.fillText(subText, W * 0.5 + 1.5, H * 0.77 + 2, subMaxWidth);
    ctx.fillStyle = '#E6C687';
    ctx.fillText(subText, W * 0.5, H * 0.77, subMaxWidth);

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Dezente prozedurale Baumwoll-Feinkarton-Papierkorn-Bump-Textur fuer den edlen Geschenkgutschein-Umschlag.
 */
function buildFineCardstockBumpTexture(THREE, disposables) {
    const S = 128;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    const imgData = ctx.createImageData(S, S);
    const data = imgData.data;

    let seed = 41927;
    const nextRand = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;
        return seed / 4294967296;
    };

    for (let y = 0; y < S; y++) {
        const fiberWave = Math.sin((y / S) * TWO_PI * 11.0) * 6.0;
        for (let x = 0; x < S; x++) {
            const weave = Math.cos((x / S) * TWO_PI * 13.0) * 5.0;
            const grain = (nextRand() - 0.5) * 34.0;
            const v = clamp(Math.round(128 + fiberWave + weave + grain), 72, 188);
            const idx = (y * S + x) * 4;
            data[idx] = v;
            data[idx + 1] = v;
            data[idx + 2] = v;
            data[idx + 3] = 255;
        }
    }
    ctx.putImageData(imgData, 0, 0);

    const tex = new THREE.CanvasTexture(c);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.repeat.set(3.2, 2.2);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Edle mattschwarze Rueckseiten-Heissfolienpraegung "MEIN SEELENFUNKE" fuer die 360-Grad-Drehung des Gutschein-Umschlags.
 */
function buildVoucherBackCrestTexture(THREE, maxAniso, disposables) {
    const W = 768;
    const H = 540;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.fillStyle = '#141518';
    ctx.fillRect(0, 0, W, H);

    const goldGrad = ctx.createLinearGradient(0, H * 0.22, W, H * 0.78);
    goldGrad.addColorStop(0.0, '#B89246');
    goldGrad.addColorStop(0.32, '#F3DDA6');
    goldGrad.addColorStop(0.55, '#C5A059');
    goldGrad.addColorStop(0.82, '#E6C687');
    goldGrad.addColorStop(1.0, '#9E7A36');

    const drawChamferRect = (pad, ch) => {
        ctx.beginPath();
        ctx.moveTo(pad + ch, pad);
        ctx.lineTo(W - pad - ch, pad);
        ctx.lineTo(W - pad, pad + ch);
        ctx.lineTo(W - pad, H - pad - ch);
        ctx.lineTo(W - pad - ch, H - pad);
        ctx.lineTo(pad + ch, H - pad);
        ctx.lineTo(pad, H - pad - ch);
        ctx.lineTo(pad, pad + ch);
        ctx.closePath();
    };

    ctx.strokeStyle = goldGrad;
    ctx.lineWidth = 4.5;
    drawChamferRect(26, 22);
    ctx.stroke();

    ctx.strokeStyle = 'rgba(230, 198, 135, 0.52)';
    ctx.lineWidth = 1.6;
    drawChamferRect(38, 16);
    ctx.stroke();

    const cx = W * 0.5;
    const starY = H * 0.37;
    ctx.fillStyle = goldGrad;
    ctx.beginPath();
    ctx.moveTo(cx, starY - 16);
    ctx.lineTo(cx + 4.5, starY - 4.5);
    ctx.lineTo(cx + 16, starY);
    ctx.lineTo(cx + 4.5, starY + 4.5);
    ctx.lineTo(cx, starY + 16);
    ctx.lineTo(cx - 4.5, starY + 4.5);
    ctx.lineTo(cx - 16, starY);
    ctx.lineTo(cx - 4.5, starY - 4.5);
    ctx.closePath();
    ctx.fill();

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = '600 34px Georgia, "Times New Roman", serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '7px';
    }
    ctx.fillStyle = 'rgba(0, 0, 0, 0.72)';
    ctx.fillText('MEIN SEELENFUNKE', cx + 1.5, H * 0.51 + 2, W - 140);
    ctx.fillStyle = goldGrad;
    ctx.fillText('MEIN SEELENFUNKE', cx, H * 0.51, W - 140);

    ctx.strokeStyle = 'rgba(230, 198, 135, 0.58)';
    ctx.lineWidth = 1.4;
    ctx.beginPath();
    ctx.moveTo(cx - 118, H * 0.59);
    ctx.lineTo(cx + 118, H * 0.59);
    ctx.stroke();

    ctx.font = '600 15px system-ui, -apple-system, "Segoe UI", sans-serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '4px';
    }
    ctx.fillStyle = '#D9B875';
    ctx.fillText('GESCHENKGUTSCHEIN · MANUFAKTUR GIFHORN', cx, H * 0.65, W - 140);

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Erzeugt ein exakt auf Gehrung geschnittenes 2D-THREE.Shape entlang eines Polylinien-Pfads (CCW fuer saubere ExtrudeGeometry-Normalen).
 */
function buildMiteredRibbonShape(THREE, pts, halfWidth) {
    const shape = new THREE.Shape();
    if (!pts || pts.length < 2) return shape;

    const segNormals = [];
    for (let i = 0; i < pts.length - 1; i++) {
        const dx = pts[i + 1][0] - pts[i][0];
        const dy = pts[i + 1][1] - pts[i][1];
        const len = Math.max(1e-6, Math.hypot(dx, dy));
        segNormals.push([-dy / len, dx / len]);
    }

    const leftPts = [];
    const rightPts = [];
    for (let i = 0; i < pts.length; i++) {
        let nx = 0;
        let ny = 0;
        let scale = halfWidth;
        if (i === 0) {
            nx = segNormals[0][0];
            ny = segNormals[0][1];
        } else if (i === pts.length - 1) {
            nx = segNormals[segNormals.length - 1][0];
            ny = segNormals[segNormals.length - 1][1];
        } else {
            const n0 = segNormals[i - 1];
            const n1 = segNormals[i];
            const mx = n0[0] + n1[0];
            const my = n0[1] + n1[1];
            const mLen = Math.hypot(mx, my);
            if (mLen < 1e-4) {
                nx = n0[0];
                ny = n0[1];
            } else {
                nx = mx / mLen;
                ny = my / mLen;
                const dot = Math.max(0.38, nx * n0[0] + ny * n0[1]);
                scale = halfWidth / dot;
            }
        }
        leftPts.push([pts[i][0] + nx * scale, pts[i][1] + ny * scale]);
        rightPts.push([pts[i][0] - nx * scale, pts[i][1] - ny * scale]);
    }

    shape.moveTo(rightPts[0][0], rightPts[0][1]);
    for (let i = 1; i < rightPts.length; i++) {
        shape.lineTo(rightPts[i][0], rightPts[i][1]);
    }
    for (let i = leftPts.length - 1; i >= 0; i--) {
        shape.lineTo(leftPts[i][0], leftPts[i][1]);
    }
    shape.closePath();
    return shape;
}

/**
 * Unverdeckter 24K-Gold-Architrav-Fries "HALLE DER HELDEN" fuer die Pantheon-Rotunde.
 */
function buildHallBannerPlaqueTexture(THREE, maxAniso, textStr, disposables) {
    const W = 2048;
    const H = 320;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#121622');
    bg.addColorStop(0.5, '#090c13');
    bg.addColorStop(1, '#121622');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#D4AF37';
    ctx.lineWidth = 12;
    ctx.strokeRect(12, 12, W - 24, H - 24);
    ctx.strokeStyle = 'rgba(253, 230, 138, 0.65)';
    ctx.lineWidth = 3.5;
    ctx.strokeRect(28, 28, W - 56, H - 56);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = '900 116px "Liberation Serif", "Georgia", serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '14px';
    }

    ctx.fillStyle = 'rgba(0, 0, 0, 0.92)';
    ctx.fillText(textStr, W * 0.5 + 4, H * 0.53 + 5, W - 100);

    const tg = ctx.createLinearGradient(0, H * 0.20, 0, H * 0.82);
    tg.addColorStop(0.0, '#FFFDF4');
    tg.addColorStop(0.3, '#FDE68A');
    tg.addColorStop(0.58, '#D4AF37');
    tg.addColorStop(0.85, '#FFF2C2');
    tg.addColorStop(1.0, '#AD8333');
    ctx.fillStyle = tg;
    ctx.fillText(textStr, W * 0.5, H * 0.53, W - 100);

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Stufe 1: Helles Seelenfunke Light-Mode Museums-Schild (3D Plaque) direkt an der Vorderkante jedes Produkt-Podests.
 */
function buildStelePlaqueTexture(THREE, maxAniso, product, disposables) {
    const W = 512;
    const H = 256;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.fillStyle = '#FAF8F5';
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 8;
    ctx.strokeRect(10, 10, W - 20, H - 20);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.45)';
    ctx.lineWidth = 2;
    ctx.strokeRect(22, 22, W - 44, H - 44);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const cat = sanitizeDisplayText(product.category_name || 'KOLLEKTION').toUpperCase();
    ctx.fillStyle = '#9A7738';
    ctx.font = '700 18px system-ui, -apple-system, sans-serif';
    ctx.fillText(cat, W * 0.5, 52, W - 72);

    const name = sanitizeDisplayText(product.name || 'Unikat');
    ctx.fillStyle = '#111827';
    ctx.font = '700 30px Georgia, serif';
    if (name.length > 24) {
        const words = name.split(' ');
        let line1 = '';
        let line2 = '';
        for (let i = 0; i < words.length; i++) {
            if ((line1 + ' ' + words[i]).trim().length <= 22 || line1 === '') {
                line1 = (line1 + ' ' + words[i]).trim();
            } else {
                line2 = (line2 + ' ' + words[i]).trim();
            }
        }
        if (line2) {
            ctx.fillText(line1, W * 0.5, 104, W - 64);
            ctx.fillText(line2, W * 0.5, 140, W - 64);
        } else {
            ctx.fillText(name, W * 0.5, 120, W - 64);
        }
    } else {
        ctx.fillText(name, W * 0.5, 120, W - 64);
    }

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.45)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(136, 170);
    ctx.lineTo(W - 136, 170);
    ctx.stroke();

    const rawPrice = sanitizeDisplayText(product.price_formatted || '');
    const displayPrice = (product.is_personalizable && rawPrice && !/^ab\s/i.test(rawPrice))
        ? `ab ${rawPrice}`
        : rawPrice;

    ctx.fillStyle = '#111827';
    ctx.font = '800 30px system-ui, -apple-system, sans-serif';
    ctx.fillText(displayPrice, W * 0.5, 206, W - 72);

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * POT-Canvas-Textur (512x128) mit Mipmaps fuer edle Light-Mode & Schwarz-Gold Plaketten (0 Z-Fighting, 0 KI-Gedankenstriche).
 */
function buildLightModeMiniPlaqueTexture(THREE, maxAniso, textStr, disposables, darkStyle = false) {
    const W = 512;
    const H = 128;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.fillStyle = darkStyle ? '#0E1117' : '#FAF8F5';
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 7;
    ctx.strokeRect(8, 8, W - 16, H - 16);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.45)';
    ctx.lineWidth = 2;
    ctx.strokeRect(18, 18, W - 36, H - 36);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = darkStyle ? '#FDE68A' : '#111827';
    ctx.font = '800 28px system-ui, -apple-system, "Segoe UI", sans-serif';
    ctx.fillText(sanitizeDisplayText(textStr), W * 0.5, H * 0.52, W - 56);

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Saisonale Aussen-Himmelspartikel-Textur (Schneeflocke, Bluetenblatt, Herbstblatt, Sonnenfunke) streng fuer AUSSERHALB des Glasdachs!
 */
function buildSeasonalOutdoorSpriteTexture(THREE, seasonKey, holidayKey, disposables) {
    const S = 128;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, S, S);
    const cx = S * 0.5;
    const cy = S * 0.5;

    const isWinter = seasonKey === 'winter' || holidayKey === 'weihnachten' || holidayKey === 'neujahr';
    const isSpring = seasonKey === 'spring' || holidayKey === 'ostern' || holidayKey === 'valentinstag';
    const isAutumn = seasonKey === 'autumn' || holidayKey === 'halloween';

    if (isWinter) {
        ctx.save();
        ctx.translate(cx, cy);
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineCap = 'round';
        ctx.lineWidth = 4.5;
        for (let i = 0; i < 6; i++) {
            ctx.save();
            ctx.rotate((i / 6) * TWO_PI);
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(0, -44);
            ctx.moveTo(0, -26);
            ctx.lineTo(-11, -35);
            ctx.moveTo(0, -26);
            ctx.lineTo(11, -35);
            ctx.stroke();
            ctx.restore();
        }
        const rg = ctx.createRadialGradient(0, 0, 2, 0, 0, 48);
        rg.addColorStop(0, 'rgba(255,255,255,0.95)');
        rg.addColorStop(0.55, 'rgba(226,240,255,0.45)');
        rg.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = rg;
        ctx.beginPath();
        ctx.arc(0, 0, 48, 0, TWO_PI);
        ctx.fill();
        ctx.restore();
    } else if (isSpring) {
        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(0.35);
        const pg = ctx.createRadialGradient(-4, -6, 4, 0, 0, 42);
        pg.addColorStop(0, '#FFFDF9');
        pg.addColorStop(0.65, holidayKey === 'valentinstag' ? '#FCA5A5' : '#FBCFE8');
        pg.addColorStop(1, 'rgba(251, 207, 232, 0)');
        ctx.fillStyle = pg;
        ctx.beginPath();
        ctx.ellipse(0, 0, 24, 38, 0, 0, TWO_PI);
        ctx.fill();
        ctx.restore();
    } else if (isAutumn) {
        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(-0.3);
        const lg = ctx.createLinearGradient(-28, -36, 28, 36);
        lg.addColorStop(0, '#FDE68A');
        lg.addColorStop(0.55, '#D97706');
        lg.addColorStop(1, '#92400E');
        ctx.fillStyle = lg;
        ctx.beginPath();
        ctx.moveTo(0, -42);
        ctx.quadraticCurveTo(32, -14, 18, 26);
        ctx.quadraticCurveTo(0, 40, -18, 26);
        ctx.quadraticCurveTo(-32, -14, 0, -42);
        ctx.fill();
        ctx.restore();
    } else {
        const sg = ctx.createRadialGradient(cx, cy, 2, cx, cy, 48);
        sg.addColorStop(0, 'rgba(255, 252, 235, 1)');
        sg.addColorStop(0.4, 'rgba(253, 230, 138, 0.65)');
        sg.addColorStop(1, 'rgba(197, 160, 89, 0)');
        ctx.fillStyle = sg;
        ctx.fillRect(0, 0, S, S);
    }

    const tex = new THREE.CanvasTexture(c);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Tiefengestaffeltes, sanft beleuchtetes Panorama-Vorschaubild der Seelenfunke-Aussenwelt hinter dem West-Prunktor.
 */
function buildOuterWorldPanoramaTexture(THREE, maxAniso, seasonKey, holidayKey, disposables) {
    const W = 1024;
    const H = 1024;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const skyGrad = ctx.createLinearGradient(0, 0, 0, H * 0.75);
    if (seasonKey === 'winter' || holidayKey === 'weihnachten') {
        skyGrad.addColorStop(0.0, '#BFD8F2');
        skyGrad.addColorStop(0.42, '#E8F1FA');
        skyGrad.addColorStop(0.78, '#F9EBD2');
        skyGrad.addColorStop(1.0, '#E5ECF4');
    } else if (seasonKey === 'spring') {
        skyGrad.addColorStop(0.0, '#BFE3F7');
        skyGrad.addColorStop(0.45, '#F9EFE6');
        skyGrad.addColorStop(0.82, '#FCE5C8');
        skyGrad.addColorStop(1.0, '#EDE4D3');
    } else if (seasonKey === 'summer') {
        skyGrad.addColorStop(0.0, '#9CC9F5');
        skyGrad.addColorStop(0.45, '#EBF4FC');
        skyGrad.addColorStop(0.82, '#FBE8BD');
        skyGrad.addColorStop(1.0, '#E9DFC8');
    } else {
        skyGrad.addColorStop(0.0, '#C4D3E8');
        skyGrad.addColorStop(0.44, '#F5E4CA');
        skyGrad.addColorStop(0.80, '#F3C78B');
        skyGrad.addColorStop(1.0, '#DFC49E');
    }
    ctx.fillStyle = skyGrad;
    ctx.fillRect(0, 0, W, H);

    // Sanfte Sonnenscheibe & volumetrischer Horizontglanz
    const sunGlow = ctx.createRadialGradient(W * 0.5, H * 0.44, 18, W * 0.5, H * 0.44, W * 0.46);
    sunGlow.addColorStop(0.0, 'rgba(255, 251, 235, 0.96)');
    sunGlow.addColorStop(0.28, 'rgba(253, 230, 138, 0.55)');
    sunGlow.addColorStop(1.0, 'rgba(253, 230, 138, 0.0)');
    ctx.fillStyle = sunGlow;
    ctx.fillRect(0, 0, W, H);

    // Ferne Gebirgs-/Huegelkette
    ctx.fillStyle = seasonKey === 'winter' ? '#9FB4CA' : '#9AA6B2';
    ctx.beginPath();
    ctx.moveTo(0, H * 0.64);
    ctx.quadraticCurveTo(W * 0.22, H * 0.50, W * 0.46, H * 0.59);
    ctx.quadraticCurveTo(W * 0.74, H * 0.48, W, H * 0.61);
    ctx.lineTo(W, H);
    ctx.lineTo(0, H);
    ctx.closePath();
    ctx.fill();

    // Mittlere Park- & Manufaktur-Garten-Silhouette mit Zypressen / Tannen
    ctx.fillStyle = seasonKey === 'winter' ? '#6D8299' : (seasonKey === 'autumn' ? '#7C5D41' : '#5B6E62');
    ctx.beginPath();
    ctx.moveTo(0, H * 0.71);
    ctx.quadraticCurveTo(W * 0.30, H * 0.63, W * 0.55, H * 0.68);
    ctx.quadraticCurveTo(W * 0.82, H * 0.62, W, H * 0.70);
    ctx.lineTo(W, H);
    ctx.lineTo(0, H);
    ctx.closePath();
    ctx.fill();

    const treePositions = [0.14, 0.23, 0.33, 0.68, 0.77, 0.87];
    ctx.fillStyle = seasonKey === 'winter' ? '#4B5E73' : (seasonKey === 'autumn' ? '#5E432C' : '#3B4D43');
    for (let ti = 0; ti < treePositions.length; ti++) {
        const tx = W * treePositions[ti];
        const baseH = H * (0.69 + (ti % 2) * 0.02);
        const topH = baseH - (130 + (ti % 3) * 32);
        ctx.beginPath();
        ctx.moveTo(tx, topH);
        ctx.quadraticCurveTo(tx + 28, (topH + baseH) * 0.55, tx + 20, baseH);
        ctx.lineTo(tx - 20, baseH);
        ctx.quadraticCurveTo(tx - 28, (topH + baseH) * 0.55, tx, topH);
        ctx.fill();
    }

    // Helle Naturstein-Promenade im Vordergrund bis zur Schwelle
    const terraceGrad = ctx.createLinearGradient(0, H * 0.72, 0, H);
    terraceGrad.addColorStop(0.0, seasonKey === 'winter' ? '#DCE6F0' : '#E7DEC9');
    terraceGrad.addColorStop(1.0, '#CBBBA0');
    ctx.fillStyle = terraceGrad;
    ctx.fillRect(0, H * 0.72, W, H * 0.28);

    // Goldene Perspektivlinien des Aussenwegs
    ctx.strokeStyle = 'rgba(197, 160, 89, 0.65)';
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.moveTo(W * 0.43, H * 0.72);
    ctx.lineTo(W * 0.20, H);
    ctx.moveTo(W * 0.57, H * 0.72);
    ctx.lineTo(W * 0.80, H);
    ctx.stroke();

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Entfernt enthaltene Boden-/Sockel-/Pedestal-Meshes sowie eingebackene flache Sockelplatten (Lv. 1, 2, 6, 8, 10)
 * zuverlaessig aus jedem Funki-GLB, ruft .dispose() auf verworfene Geometrien auf und kalibriert die Fusssohlen exakt auf Y = 0.000 m!
 */
function stripFunkiPedestalAndNormalizeGeometry(THREE, sceneRoot) {
    sceneRoot.updateMatrixWorld(true);
    const overallBox = new THREE.Box3().setFromObject(sceneRoot);
    const overallH = Math.max(0.001, overallBox.max.y - overallBox.min.y);
    const pedestalNameRe = /base|pedestal|sockel|stand|floor|ground|disc|plinth|plate|stage|platform/i;

    const candidateMeshes = [];
    const discardMeshes = [];
    sceneRoot.traverse((child) => {
        if (!child.isMesh || !child.geometry) return;
        const mName = String(child.name || '') + ' ' + String((child.parent && child.parent.name) || '');
        const mb = new THREE.Box3().setFromObject(child);
        const mh = mb.max.y - mb.min.y;
        const mw = Math.max(mb.max.x - mb.min.x, mb.max.z - mb.min.z);
        const isNamedPedestal = pedestalNameRe.test(mName);
        const isFlatBottomDisc = (mh < overallH * 0.09) && (mw > mh * 2.4) && (mb.min.y <= overallBox.min.y + overallH * 0.04);

        if (isNamedPedestal || isFlatBottomDisc) {
            discardMeshes.push(child);
        } else {
            candidateMeshes.push(child);
        }
    });

    let srcMesh = candidateMeshes[0] || null;
    if (!srcMesh && discardMeshes.length > 0) {
        srcMesh = discardMeshes.shift();
    }
    for (let di = 0; di < discardMeshes.length; di++) {
        const dm = discardMeshes[di];
        if (dm.parent) dm.parent.remove(dm);
        if (dm.geometry && typeof dm.geometry.dispose === 'function') dm.geometry.dispose();
        if (dm.material) {
            const mats = Array.isArray(dm.material) ? dm.material : [dm.material];
            for (let mi = 0; mi < mats.length; mi++) {
                if (mats[mi] && typeof mats[mi].dispose === 'function') mats[mi].dispose();
            }
        }
    }
    if (!srcMesh) {
        throw new Error('Kein Charakter-Mesh im Funki-GLB gefunden.');
    }

    let workGeo = srcMesh.geometry.clone();
    const bakeMat = new THREE.Matrix4()
        .makeRotationY(FUNKI_GLB_FRONT_ROTATION_Y)
        .multiply(srcMesh.matrixWorld);
    workGeo.applyMatrix4(bakeMat);
    workGeo.computeBoundingBox();

    let bb = workGeo.boundingBox;
    let rawH = Math.max(0.001, bb.max.y - bb.min.y);
    const posAttr = workGeo.attributes.position;
    const normAttr = workGeo.attributes.normal;
    const uvAttr = workGeo.attributes.uv;
    const idxAttr = workGeo.index;

    // 1%-Hoehen-Slice-Pruefung auf eingebackene flache Bodenplatte am Fusspunkt
    const binCnt = new Int32Array(8);
    const binFlat = new Int32Array(8);
    for (let vi = 0; vi < posAttr.count; vi++) {
        const u = (posAttr.getY(vi) - bb.min.y) / rawH;
        if (u >= 0 && u < 0.08) {
            const b = Math.min(7, Math.max(0, Math.floor(u * 100)));
            binCnt[b] += 1;
            const ny = normAttr ? Math.abs(normAttr.getY(vi)) : 0;
            if (ny > 0.70) {
                binFlat[b] += 1;
            }
        }
    }

    if (binCnt[0] > 420 && binFlat[0] > binCnt[0] * 0.56) {
        let pinchBin = 3;
        for (let b = 1; b <= 6; b++) {
            if (binFlat[b] < 90) {
                pinchBin = b;
                break;
            }
        }
        const cutY = bb.min.y + clamp(pinchBin * 0.01 + 0.003, 0.013, 0.048) * rawH;

        const keptOldToNew = new Int32Array(posAttr.count);
        keptOldToNew.fill(-1);
        const newIndices = [];
        const newPos = [];
        const newNorm = [];
        const newUv = [];

        const emitVertex = (oldVi) => {
            let mapped = keptOldToNew[oldVi];
            if (mapped !== -1) return mapped;
            mapped = newPos.length / 3;
            keptOldToNew[oldVi] = mapped;
            newPos.push(posAttr.getX(oldVi), posAttr.getY(oldVi), posAttr.getZ(oldVi));
            if (normAttr) {
                newNorm.push(normAttr.getX(oldVi), normAttr.getY(oldVi), normAttr.getZ(oldVi));
            }
            if (uvAttr) {
                newUv.push(uvAttr.getX(oldVi), uvAttr.getY(oldVi));
            }
            return mapped;
        };

        const triCount = idxAttr ? Math.floor(idxAttr.count / 3) : Math.floor(posAttr.count / 3);
        for (let ti = 0; ti < triCount; ti++) {
            const i0 = idxAttr ? idxAttr.getX(ti * 3) : (ti * 3);
            const i1 = idxAttr ? idxAttr.getX(ti * 3 + 1) : (ti * 3 + 1);
            const i2 = idxAttr ? idxAttr.getX(ti * 3 + 2) : (ti * 3 + 2);
            const y0 = posAttr.getY(i0);
            const y1 = posAttr.getY(i1);
            const y2 = posAttr.getY(i2);
            const maxTriY = Math.max(y0, y1, y2);
            const avgTriY = (y0 + y1 + y2) / 3.0;
            if (maxTriY >= cutY && avgTriY >= cutY - 0.002 * rawH) {
                newIndices.push(emitVertex(i0), emitVertex(i1), emitVertex(i2));
            }
        }

        if (newIndices.length >= 300) {
            const strippedGeo = new THREE.BufferGeometry();
            strippedGeo.setAttribute('position', new THREE.Float32BufferAttribute(newPos, 3));
            if (newNorm.length > 0) {
                strippedGeo.setAttribute('normal', new THREE.Float32BufferAttribute(newNorm, 3));
            }
            if (newUv.length > 0) {
                strippedGeo.setAttribute('uv', new THREE.Float32BufferAttribute(newUv, 2));
            }
            strippedGeo.setIndex(newIndices);
            workGeo.dispose();
            workGeo = strippedGeo;
            workGeo.computeBoundingBox();
            bb = workGeo.boundingBox;
            rawH = Math.max(0.001, bb.max.y - bb.min.y);
        }
    }

    // Ursprungs-Geometrie aus dem geparsten GLB freigeben
    if (srcMesh.geometry && typeof srcMesh.geometry.dispose === 'function') {
        srcMesh.geometry.dispose();
    }

    const cx = (bb.min.x + bb.max.x) * 0.5;
    const cz = (bb.min.z + bb.max.z) * 0.5;
    const minY = bb.min.y;
    const targetH = 1.62;
    const scale = targetH / rawH;

    const normMat = new THREE.Matrix4()
        .makeScale(scale, scale, scale)
        .multiply(new THREE.Matrix4().makeTranslation(-cx, -minY, -cz));
    workGeo.applyMatrix4(normMat);
    workGeo.computeVertexNormals();
    workGeo.computeBoundingBox();
    workGeo.computeBoundingSphere();

    // Exakte Kalibrierung der tiefsten Schuhsohle auf Y = 0.000 m (0 mm Schweben/Einsinken)
    const finalMinY = workGeo.boundingBox ? workGeo.boundingBox.min.y : 0;
    if (Math.abs(finalMinY) > 0.00001) {
        workGeo.translate(0, -finalMinY, 0);
        workGeo.computeBoundingBox();
        workGeo.computeBoundingSphere();
    }

    return {
        geometry: workGeo,
        material: srcMesh.material,
        height: targetH,
    };
}

/**
 * 24K-Gold Frontplakette an den Top-5-Ehrenpodesten der Halle der Helden.
 */
function paintHeroPedestalCanvas(c, slotNumber, hero) {
    const W = c.width;
    const H = c.height;
    const ctx = c.getContext('2d');
    const norm = normalizeHeroCardData(slotNumber, hero);

    const bg = ctx.createRadialGradient(W * 0.5, H * 0.5, 20, W * 0.5, H * 0.5, W * 0.62);
    bg.addColorStop(0, norm.is_me ? '#241b09' : '#141720');
    bg.addColorStop(0.7, '#0a0c12');
    bg.addColorStop(1, '#06070b');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = norm.is_me ? '#FDE68A' : '#D4AF37';
    ctx.lineWidth = norm.is_me ? 13 : 10;
    ctx.strokeRect(14, 14, W - 28, H - 28);
    ctx.strokeStyle = 'rgba(253, 230, 138, 0.52)';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(30, 30, W - 60, H - 60);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    if (!norm.is_placeholder) {
        ctx.fillStyle = norm.is_me ? '#FDE68A' : '#C5A059';
        ctx.font = '800 34px "Liberation Sans", "Segoe UI", sans-serif';
        ctx.fillText(
            `#${slotNumber} · STUFE ${norm.level} · ${norm.level_title.toUpperCase()}${norm.is_me ? ' · DU' : ''}`,
            W * 0.5,
            H * 0.23,
            W - 90
        );

        ctx.fillStyle = 'rgba(197, 160, 89, 0.45)';
        ctx.fillRect(W * 0.16, H * 0.36, W * 0.68, 2.5);

        const ng = ctx.createLinearGradient(0, H * 0.40, 0, H * 0.72);
        ng.addColorStop(0, '#FFFDF5');
        ng.addColorStop(0.35, '#FDE68A');
        ng.addColorStop(0.7, '#D4AF37');
        ng.addColorStop(1, '#B88D3B');
        ctx.fillStyle = ng;
        ctx.font = '900 72px "Liberation Sans", "Segoe UI", sans-serif';
        ctx.fillText(norm.display_name, W * 0.5, H * 0.56, W - 88);

        ctx.fillStyle = '#E6C687';
        ctx.font = '800 33px "Liberation Sans", "Segoe UI", sans-serif';
        const scoreMeta = norm.magma_highest_level > 0
            ? `${norm.sparks_formatted} FUNKEN · MAGMA RAUM ${norm.magma_highest_level}`
            : `${norm.sparks_formatted} FUNKEN · ${norm.ship_class}`;
        ctx.fillText(scoreMeta, W * 0.5, H * 0.80, W - 92);
    } else {
        ctx.fillStyle = 'rgba(212, 175, 55, 0.90)';
        ctx.font = '800 36px "Liberation Sans", "Segoe UI", sans-serif';
        ctx.fillText(`✦ EHRENPLATZ #${slotNumber} ✦`, W * 0.5, H * 0.28, W - 90);

        ctx.fillStyle = 'rgba(197, 160, 89, 0.34)';
        ctx.fillRect(W * 0.22, H * 0.44, W * 0.56, 2);

        ctx.fillStyle = '#FDE68A';
        ctx.font = '800 68px "Liberation Sans", "Segoe UI", sans-serif';
        ctx.fillText('Freier Ehrenplatz', W * 0.5, H * 0.67, W - 88);
    }
}

/**
 * 24K-Gold Ehren-Stelen-Tafel fuer Platz #6 bis #10+ in der Kolonnade.
 */
function paintHeroSteleCanvas(c, slotNumber, hero) {
    const W = c.width;
    const H = c.height;
    const ctx = c.getContext('2d');
    const norm = normalizeHeroCardData(slotNumber, hero);

    ctx.fillStyle = norm.is_me ? '#1f1708' : '#090b10';
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = norm.is_me ? '#FDE68A' : '#C5A059';
    ctx.lineWidth = norm.is_me ? 10 : 7;
    ctx.strokeRect(10, 10, W - 20, H - 20);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    ctx.fillStyle = '#D4AF37';
    ctx.font = '900 38px "Liberation Sans", sans-serif';
    ctx.fillText('✦', W * 0.5, H * 0.14);

    if (!norm.is_placeholder) {
        ctx.fillStyle = '#FDE68A';
        ctx.font = '800 38px "Liberation Sans", sans-serif';
        ctx.fillText(norm.display_name, W * 0.5, H * 0.38, W - 36);

        ctx.fillStyle = '#C5A059';
        ctx.font = '700 25px "Liberation Sans", sans-serif';
        ctx.fillText(`#${slotNumber} · STUFE ${norm.level}`, W * 0.5, H * 0.58, W - 36);
        ctx.fillStyle = '#E6C687';
        ctx.fillText(`${norm.sparks_formatted} Funken${norm.is_me ? ' · DU' : ''}`, W * 0.5, H * 0.76, W - 36);
    } else {
        ctx.fillStyle = 'rgba(212, 175, 55, 0.88)';
        ctx.font = '700 28px "Liberation Sans", sans-serif';
        ctx.fillText(`#${slotNumber}`, W * 0.5, H * 0.36);

        ctx.fillStyle = '#FDE68A';
        ctx.font = '800 33px "Liberation Sans", sans-serif';
        ctx.fillText('Freier', W * 0.5, H * 0.56, W - 36);
        ctx.fillText('Ehrenplatz', W * 0.5, H * 0.70, W - 36);
    }
}

function buildPeerNameplateTexture(THREE, displayName, level, classLabel, accentHex) {
    const W = 384;
    const H = 96;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = 'rgba(11, 14, 20, 0.88)';
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
        ctx.roundRect(8, 8, W - 16, H - 16, 20);
    } else {
        ctx.rect(8, 8, W - 16, H - 16);
    }
    ctx.fill();

    ctx.strokeStyle = accentHex || '#C5A059';
    ctx.lineWidth = 3;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#FAF8F5';
    ctx.font = '700 24px system-ui, -apple-system, "Segoe UI", sans-serif';
    ctx.fillText(sanitizeDisplayText(displayName || 'Funki').slice(0, 22), W * 0.5, 36, W - 40);

    ctx.fillStyle = '#E6C687';
    ctx.font = '700 16px system-ui, -apple-system, "Segoe UI", sans-serif';
    const metaLabel = `LV. ${clamp(parseInt(level, 10) || 1, 1, 10)} · ${sanitizeDisplayText(classLabel || 'KLASSE C')}`;
    ctx.fillText(metaLabel, W * 0.5, 66, W - 44);

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    return tex;
}

function buildSpeechBubbleTexture(THREE, rawText) {
    const W = 512;
    const H = 160;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = 'rgba(250, 248, 245, 0.97)';
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 4;

    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
        ctx.roundRect(12, 10, W - 24, H - 36, 24);
    } else {
        ctx.rect(12, 10, W - 24, H - 36);
    }
    ctx.fill();
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(W * 0.5 - 14, H - 26);
    ctx.lineTo(W * 0.5, H - 8);
    ctx.lineTo(W * 0.5 + 14, H - 26);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    const clean = sanitizeDisplayText(rawText || '').slice(0, 100);
    const words = clean.split(/\s+/);
    let line1 = '';
    let line2 = '';
    for (let i = 0; i < words.length; i++) {
        const w = words[i];
        if ((line1 + ' ' + w).trim().length <= 34 || line1 === '') {
            line1 = (line1 + ' ' + w).trim();
        } else {
            line2 = (line2 + ' ' + w).trim();
        }
    }
    if (line2.length > 38) {
        line2 = line2.slice(0, 36) + '...';
    }

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#111827';
    ctx.font = '700 22px system-ui, -apple-system, "Segoe UI", sans-serif';

    if (line2) {
        ctx.fillText(line1, W * 0.5, 50, W - 56);
        ctx.fillText(line2, W * 0.5, 86, W - 56);
    } else {
        ctx.fillText(line1, W * 0.5, 68, W - 56);
    }

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    return tex;
}

function buildSeelenfunkeHoloTexture(THREE, disposables) {
    const S = 128;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, S, S);

    const rad = ctx.createRadialGradient(S * 0.5, S * 0.5, 1, S * 0.5, S * 0.5, S * 0.48);
    rad.addColorStop(0.0, 'rgba(255, 255, 255, 0.95)');
    rad.addColorStop(0.28, 'rgba(254, 240, 138, 0.72)');
    rad.addColorStop(0.62, 'rgba(103, 232, 249, 0.32)');
    rad.addColorStop(1.0, 'rgba(103, 232, 249, 0.0)');
    ctx.fillStyle = rad;
    ctx.fillRect(0, 0, S, S);

    ctx.save();
    ctx.translate(S * 0.5, S * 0.5);
    for (let a = 0; a < 2; a++) {
        ctx.rotate(Math.PI * 0.25 + a * Math.PI * 0.5);
        const rayG = ctx.createLinearGradient(-S * 0.46, 0, S * 0.46, 0);
        rayG.addColorStop(0.0, 'rgba(103, 232, 249, 0.0)');
        rayG.addColorStop(0.35, 'rgba(103, 232, 249, 0.58)');
        rayG.addColorStop(0.5, 'rgba(254, 240, 138, 0.95)');
        rayG.addColorStop(0.65, 'rgba(103, 232, 249, 0.58)');
        rayG.addColorStop(1.0, 'rgba(103, 232, 249, 0.0)');
        ctx.fillStyle = rayG;
        ctx.fillRect(-S * 0.46, -2.5, S * 0.92, 5.0);
    }
    ctx.restore();

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

function createArchPortalVeilGeometry(THREE, halfW, springY, archRadius = halfW, segments = 32) {
    const r = Math.max(0.05, Number(archRadius) || Number(halfW) || 1.0);
    const hw = Math.max(0.05, Number(halfW) || r);
    const sy = Math.max(0.0, Number(springY) || 1.0);
    const shape = new THREE.Shape();
    shape.moveTo(-hw, 0);
    shape.lineTo(hw, 0);
    shape.lineTo(r, sy);
    shape.absarc(0, sy, r, 0, Math.PI, false);
    shape.lineTo(-hw, 0);
    shape.closePath();

    const geo = new THREE.ShapeGeometry(shape, segments);
    const pos = geo.attributes.position;
    const uv = geo.attributes.uv;
    const maxSpanW = Math.max(hw, r) * 2.0;
    const totalH = Math.max(0.05, sy + r);
    for (let i = 0; i < pos.count; i++) {
        uv.setXY(i, (pos.getX(i) / maxSpanW) + 0.5, pos.getY(i) / totalH);
    }
    uv.needsUpdate = true;
    return geo;
}

class SeelenfunkeLouvreShowroom3D {
    constructor(options = {}) {
        this.canvas = options.canvas;
        this.floatingCardEl = options.floatingCardEl || null;
        this.floatingHeroCardEl = options.floatingHeroCardEl || null;
        this.floatingPortalCardEl = options.floatingPortalCardEl || null;
        this.floatingOuterGateEl = options.floatingOuterGateEl || null;
        this.floatingStarStationEl = options.floatingStarStationEl || null;

        this.products = Array.isArray(options.products) ? options.products : [];
        this.environmentInfo = resolveLobbyEnvironment(options.environment || null);

        const rawPlayerObj = (options.player && typeof options.player === 'object') ? options.player : null;
        const legacyFunki = options.playerFunki || { is_guest: true, level: 2 };
        this.heroesPayload = options.heroesPayload || {
            founding_era: true,
            total_heroes: 0,
            my_hero_slot: null,
            customer_logged_in: false,
            customer_opt_in: false,
            level_models: {},
            heroes: [],
        };

        const inferredAuth = Boolean(
            (rawPlayerObj && rawPlayerObj.is_authenticated)
            || (legacyFunki && !legacyFunki.is_guest)
            || (this.heroesPayload && this.heroesPayload.customer_logged_in)
        );
        this.isAuthenticatedCustomer = (options.isAuthenticatedCustomer !== undefined)
            ? Boolean(options.isAuthenticatedCustomer)
            : inferredAuth;

        const initLevel = clamp(
            parseInt((rawPlayerObj && rawPlayerObj.level) || legacyFunki.level || (this.isAuthenticatedCustomer ? 1 : 2), 10) || 1,
            1,
            10
        );
        const initBalance = Math.max(0, parseInt((rawPlayerObj && rawPlayerObj.funken_balance) ?? legacyFunki.funken_balance ?? 0, 10) || 0);
        const initTotal = Math.max(0, parseInt((rawPlayerObj && rawPlayerObj.funken_total_earned) ?? legacyFunki.funken_total_earned ?? 0, 10) || 0);
        const initName = sanitizeDisplayText(
            (rawPlayerObj && rawPlayerObj.name)
            || legacyFunki.display_name
            || (this.isAuthenticatedCustomer ? 'Seelenfunke' : 'Gast')
        );
        const initModelUrl = String(
            (rawPlayerObj && rawPlayerObj.model_url)
            || legacyFunki.glb_url
            || legacyFunki.model_url
            || DEFAULT_FUNKI_GLB_URLS[initLevel]
            || DEFAULT_FUNKI_GLB_URLS[2]
        );

        this.mmorpgCanEnter = Boolean(options.mmorpgCanEnter ?? (rawPlayerObj && rawPlayerObj.mmorpg_can_enter));
        this.mmorpgEnterUrl = String(options.mmorpgEnterUrl || (rawPlayerObj && rawPlayerObj.mmorpg_enter_url) || '/funkenreich/enter');
        this.atelierReview = this._normalizeAtelierReview(options.atelierReview);

        this.playerProfile = {
            isAuthenticated: this.isAuthenticatedCustomer,
            gamificationActive: rawPlayerObj ? Boolean(rawPlayerObj.gamification_active ?? true) : this.isAuthenticatedCustomer,
            name: initName,
            level: initLevel,
            levelTitle: sanitizeDisplayText((rawPlayerObj && rawPlayerObj.level_title) || FUNKI_LEVEL_TITLES[initLevel] || 'Ursprung'),
            modelUrl: initModelUrl,
            funkenBalance: initBalance,
            displayedFunkenBalance: initBalance,
            funkenTotalEarned: initTotal,
            statusHintText: '',
        };

        // 2-Stufen-Zugangsvoraussetzung fuer 'Halle der Helden': 1. Angemeldet  2. Magie aktiviert
        this.sanctumAccess = {
            isAuthenticated: this.isAuthenticatedCustomer,
            hasCompletedOrder: Boolean(rawPlayerObj && (rawPlayerObj.has_completed_order ?? false)),
            gamificationActive: Boolean(rawPlayerObj && rawPlayerObj.gamification_active),
            serverCanEnter: (rawPlayerObj && rawPlayerObj.can_enter_sanctums !== undefined) ? Boolean(rawPlayerObj.can_enter_sanctums) : null,
            serverReason: (rawPlayerObj && rawPlayerObj.sanctum_block_reason) ? String(rawPlayerObj.sanctum_block_reason) : null,
            loginUrl: String((rawPlayerObj && rawPlayerObj.login_url) || '/login'),
            dashboardUrl: String((rawPlayerObj && rawPlayerObj.dashboard_url) || '/dashboard'),
        };
        this.canEnterSanctums = this._computeSanctumAccess();

        this.playerFunki = Object.assign({}, legacyFunki, {
            is_guest: !this.isAuthenticatedCustomer,
            level: initLevel,
            glb_url: initModelUrl,
            model_url: initModelUrl,
        });

        this.audio = options.audio || getLobbyAudioInstance({ isAuthenticated: this.isAuthenticatedCustomer });
        this.userSettings = loadLobbyUserSettings();
        this.cameraSensitivityFactor = clamp((this.userSettings.cameraSensitivity || 100) / 100, 0.5, 1.5);
        if (this.audio) {
            if (typeof this.audio.setSfxVolume === 'function') this.audio.setSfxVolume(this.userSettings.volume);
            if (typeof this.audio.setSfxMuted === 'function') this.audio.setSfxMuted(this.userSettings.volume <= 0);
        }
        this.keybindings = loadLobbyKeybindings();

        const rawInitialZone = String(options.initialZone || 'gallery').toLowerCase();
        this._requestedInitialZone = rawInitialZone;
        this.initialZone = (rawInitialZone === 'heroes' || rawInitialZone === 'hall' || rawInitialZone === 'my_place')
            ? rawInitialZone
            : 'gallery';

        this.onEngineReady = typeof options.onEngineReady === 'function' ? options.onEngineReady : null;
        this.onSelectProduct = typeof options.onSelectProduct === 'function' ? options.onSelectProduct : null;
        this.onActiveExhibitChange = typeof options.onActiveExhibitChange === 'function' ? options.onActiveExhibitChange : null;
        this.onFocusProductChange = typeof options.onFocusProductChange === 'function' ? options.onFocusProductChange : null;
        this.onFocusHeroChange = typeof options.onFocusHeroChange === 'function' ? options.onFocusHeroChange : null;
        this.onZoneChange = typeof options.onZoneChange === 'function' ? options.onZoneChange : null;
        this.onPortalGatePromptChange = typeof options.onPortalGatePromptChange === 'function' ? options.onPortalGatePromptChange : null;
        const rawAuthGateCb = typeof options.onAuthGatePrompt === 'function' ? options.onAuthGatePrompt : null;
        this.onAuthGatePrompt = rawAuthGateCb ? (zone) => rawAuthGateCb(zone, this.getSanctumAccessState()) : null;
        this.onOuterGatePromptChange = typeof options.onOuterGatePromptChange === 'function' ? options.onOuterGatePromptChange : null;
        this.onOuterGateModalOpen = typeof options.onOuterGateModalOpen === 'function' ? options.onOuterGateModalOpen : null;
        this.onAtelierStarStationFocusChange = typeof options.onAtelierStarStationFocusChange === 'function' ? options.onAtelierStarStationFocusChange : null;
        this.onTeleportTransition = typeof options.onTeleportTransition === 'function' ? options.onTeleportTransition : null;
        this.onPlayerHudChange = typeof options.onPlayerHudChange === 'function' ? options.onPlayerHudChange : null;

        this.THREE = null;
        this.renderer = null;
        this.scene = null;
        this.camera = null;
        this.envTexture = null;
        this._engineReady = false;
        this.galleryGroup = null;
        this.charFollowLights = null;
        this.outdoorWeatherGroup = null;
        this.outdoorWeatherPosAttr = null;
        this.outdoorWeatherMeta = [];
        this.westOuterGateGroup = null;
        this.westGateHitMeshes = [];
        this.westGateFocused = false;
        this._westOuterGateArmed = true;
        this._outerGatePromptPendingTimer = 0;
        this._outerGateCardScreenX = -9999;
        this._outerGateCardScreenY = -9999;
        this._outerGateCardVisible = false;
        this._heroesAuthGateTriggered = false;
        this._gateRepulseTimer = 0;
        this._gateRepulseDuration = 0.38;
        this._gateRepulseDirX = 0;
        this._gateRepulseDirZ = 0;
        this._gateRepulseInputDampTimer = 0;
        this._gatePromptCooldown = 0;
        this._gatePromptPendingTimer = 0;
        this.teleportFovBoost = 0;
        this.sprintFovBoost = 0;
        this._portalWalkCd = 0;

        this.disposables = [];
        this.glbCache = new Map();
        this.glbTemplateCache = new Map();
        this.glbLoadingPromises = new Map();
        this.skinnedFunkiCache = new Map();
        this.skinnedFunkiPromises = new Map();
        this.heroDynamicDisposables = [];
        this._monumentGeneration = 0;
        this.abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        this.running = false;
        this.destroyed = false;
        this.rafId = 0;
        this.queueTimerId = 0;
        this.lastTimeMs = 0;
        this.elapsedTime = 0;
        this.maxAniso = 4;
        this._viewW = 1;
        this._viewH = 1;

        this.hallHalfW = 12.0;
        this.hallNorthZ = -18;
        this.hallSouthZ = 14;
        this.portalHalfW = 4.25;
        this.promenadeLength = 10.0;
        this.heroesEntryZ = -28;
        this.pantheonCenterZ = -49.5;
        this.pantheonHalfW = 20.2;
        this.pantheonMinZ = -70.0;
        this.currentZone = 'gallery';

        this._exhibitTransition = null;
        this._lastHeroesRefreshMs = 0;
        this._heroesRefreshInFlight = false;
        this._lastRightTapMs = 0;
        this._touchSwipeStart = null;

        this.steles = [];
        this.exhibitLoadQueue = [];
        this.isLoadingExhibit = false;
        this.interactiveHitMeshes = [];
        this.heroInteractiveHitMeshes = [];
        this.portalHitMeshes = [];
        this.walkableFloorMeshes = [];
        this.floorMesh = null;
        this.walkTargetRing = null;
        this.clickMoveRing = null;
        this.walkTargetTimer = 0;
        this.focusHaloMesh = null;
        this.heroFocusHaloMesh = null;
        this.portalBarrierGroup = null;

        this.pantheonGroup = null;
        this.pantheonDynamicGroup = null;
        this.pantheonColumnColliders = [];
        this.heroPedestalColliders = [];
        this.heroPedestals = [];
        this.heroSlotAnchors = {};
        this.animatedFunkiFigures = [];
        this.pedestalSparkMeta = [];
        this.pedestalSparkPosAttr = null;
        this.sparkSpriteTex = null;
        this.seelenfunkeHoloTex = null;
        this._magicAwakenChoreo = null;
        this._statusHintTimer = 0;
        this.stagesPreviewMode = 'live'; // 'live' | '1-5' | '6-10'

        this.activeIndex = 0;
        this.focusedSteleIndex = -1;
        this.pinnedSteleIndex = -1;
        this.focusedHeroSlot = -1;
        this.pinnedHeroSlot = -1;
        this.portalGateFocused = false;
        this.pinnedPortalGate = false;
        this.atelierStarStation = null;
        this.atelierStarStationFocused = false;
        this.atelierStarHoverPreview = 0;

        this._cardScreenX = -9999;
        this._cardScreenY = -9999;
        this._cardVisible = false;

        this._heroCardScreenX = -9999;
        this._heroCardScreenY = -9999;
        this._heroCardVisible = false;

        this._portalCardScreenX = -9999;
        this._portalCardScreenY = -9999;
        this._portalCardVisible = false;

        this._starStationCardScreenX = -9999;
        this._starStationCardScreenY = -9999;
        this._starStationCardVisible = false;

        this.player = {
            root: null,
            joints: null,
            skinnedRig: null,
            builtRecord: null,
            x: 0,
            y: 0,
            groundY: 0,
            z: 8.5,
            vy: 0,
            isJumping: false,
            jumpCount: 0,
            coyoteTimer: 0,
            isWallClinging: false,
            wallClingTimer: 0.0,
            wallClingUsed: false,
            wallClingBlend: 0.0,
            wallNormalX: 0.0,
            wallNormalZ: 0.0,
            wallFlipVx: 0.0,
            wallFlipVz: 0.0,
            isSomersault: false,
            somersaultDir: 1,
            somersaultProgress: 0.0,
            somersaultDuration: 0.52,
            landSquash: 0,
            yaw: Math.PI,
            walkPhase: 0,
            lastStepHalfCycle: -1,
            moveBlend: 0,
            turnBank: 0,
            headYaw: 0,
            headPitch: 0,
            isMoving: false,
            isSprinting: false,
            sprintBoost: 0.0,
            currentSpeed: 0,
            smoothInputForward: 0.0,
            smoothInputRight: 0.0,
            velX: 0.0,
            velZ: 0.0,
            smoothTurnRate: 0.0,
            autoTarget: null,
            autoLookAt: null,
            bubbleSprite: null,
            bubbleTex: null,
            bubbleMat: null,
            bubbleExpiresAt: 0,
            emote: null,
            emoteTimer: 0,
        };

        this.freePedestalCrests = [];
        this._heroesNeedsLiveRefresh = false;

        this._avatarShared = null;
        this._voucherShared = null;
        this.peers = new Map();
        this.weaponBursts = [];
        this.soloMode = Boolean(options.soloMode);
        this.focusProtected = false;
        this.mutedPlayerIds = new Set();
        this.fpsEstimate = 60.0;

        this.camControl = {
            yaw: 0,
            pitch: 0.22,
            distance: 4.35,
            desiredYaw: 0,
            desiredPitch: 0.22,
            desiredDistance: 4.35,
            isFirstPerson: false,
            lookAtCurrent: null,
            isDragging: false,
            dragMoved: 0,
            lastX: 0,
            lastY: 0,
            pinchDist: 0,
            mouseLeftDown: false,
            mouseRightDown: false,
            bothMouseWalk: false,
            hadChordWalk: false,
            downButton: -1,
            shakeTimer: 0,
        };

        this.keys = {};
        this.joystick = { x: 0, y: 0 };

        this.raycaster = null;
        this.pointerNdc = null;
        this._projVec = null;
        this._collisionOut = { x: 0, z: 0, hitWall: false, wallNormalX: 0, wallNormalZ: 0 };
        this._hoverTickCounter = 0;

        this._boundTick = (ts) => this._tick(ts);
        this._boundResize = () => this.resize();
        this._resizeObserver = null;
        this._fsResizeTimer = 0;
        this._boundFullscreenResize = () => {
            if (this.destroyed) return;
            this.resize();
            requestAnimationFrame(() => {
                if (!this.destroyed) this.resize();
            });
            if (this._fsResizeTimer) clearTimeout(this._fsResizeTimer);
            this._fsResizeTimer = setTimeout(() => {
                if (!this.destroyed) this.resize();
            }, 120);
        };
        this._boundKeyDown = (e) => this._onKeyDown(e);
        this._boundKeyUp = (e) => this._onKeyUp(e);
        this._boundPointerDown = (e) => this._onPointerDown(e);
        this._boundPointerMove = (e) => this._onPointerMove(e);
        this._boundPointerUp = (e) => this._onPointerUp(e);
        this._boundWindowBlur = () => this._resetMouseButtons();
        this._boundWheel = (e) => this._onWheel(e);
        this._boundTouchStart = (e) => this._onTouchStart(e);
        this._boundTouchMove = (e) => this._onTouchMove(e);
        this._boundContextMenu = (e) => e.preventDefault();
    }

    async init() {
        ensureLobbyScopedStyles();
        const THREE = await loadThreeLibrary();
        if (this.destroyed || !this.canvas) return;

        this.THREE = THREE;
        this.raycaster = new THREE.Raycaster();
        this.pointerNdc = new THREE.Vector2();
        this._projVec = new THREE.Vector3();
        this.camControl.lookAtCurrent = new THREE.Vector3(0, 1.42, 4.0);

        const isMobile = window.innerWidth < 768;
        const count = Math.max(2, this.products.length);
        const rows = Math.ceil(count / 2);
        const baySpacing = 6.2;
        const gallerySpan = Math.max(16, rows * baySpacing + 6);

        this.hallSouthZ = 12.0;
        this.hallNorthZ = this.hallSouthZ - gallerySpan - 8.0;
        this.heroesEntryZ = this.hallNorthZ - this.promenadeLength;
        this.pantheonCenterZ = this.heroesEntryZ - 21.5;
        this.pantheonMinZ = this.pantheonCenterZ - 20.2;

        this.player.x = 0;
        this.player.z = this.hallSouthZ - 3.6;
        this.player.yaw = Math.PI;

        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            antialias: !isMobile,
            alpha: false,
            precision: 'highp',
            powerPreference: 'high-performance',
            stencil: false,
            depth: true,
        });

        // 0 Echtzeit-Shadow-Maps fuer kompromisslose 60 FPS
        this.renderer.shadowMap.enabled = false;
        if (this.renderer.debug) {
            this.renderer.debug.checkShaderErrors = false;
        }

        if ('outputColorSpace' in this.renderer && THREE.SRGBColorSpace) {
            this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        } else if (THREE.sRGBEncoding) {
            this.renderer.outputEncoding = THREE.sRGBEncoding;
        }
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.08;
        this.maxAniso = this.renderer.capabilities ? this.renderer.capabilities.getMaxAnisotropy() : 4;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0xf5efe4);
        this.scene.fog = null;

        this.envTexture = buildLouvreStudioEnvironment(THREE, this.renderer, this.disposables);
        if (this.envTexture) {
            this.scene.environment = this.envTexture;
        }

        const totalGalleryLength = (this.hallSouthZ - this.hallNorthZ) + 4.0;
        const promWidth = this.portalHalfW * 2 + 1.2;
        const promTotalLen = this.promenadeLength + 2.4;

        this._buildLighting();
        this._buildLouvreArchitecture(isMobile);
        this._buildSeasonalSkyAndOutdoorWeather(isMobile);
        this._buildSeasonalIndoorAccents();
        this._buildWestOuterWorldGate();
        this._buildGoldenHonorPortalAndPromenade();
        this._buildHallOfHeroes();
        this._buildAtelierStarRatingStation();
        const coreTexturesPromise = this._loadPhotorealisticTextures(24.0, totalGalleryLength, promWidth, promTotalLen);
        this._populateHeroesMonuments();
        this._buildExhibitSteles(baySpacing);
        const playerReadyPromise = this._buildPlayerCharacter();
        this._bindEvents();

        const resolvedInitAudioZone = ((this.initialZone === 'heroes' || this.initialZone === 'hall' || this.initialZone === 'my_place') && this.canEnterSanctums)
            ? 'heroes'
            : 'gallery';
        if (this.audio && typeof this.audio.onLobbyOpen === 'function') {
            this.audio.onLobbyOpen(this.isAuthenticatedCustomer, resolvedInitAudioZone);
        }

        this.resize();

        if ((this.initialZone === 'heroes' || this.initialZone === 'hall' || this.initialZone === 'my_place') && this.canEnterSanctums) {
            if (this.initialZone === 'my_place') {
                this.goToZone('heroes', true);
                this.goToMyHeroPlace();
            } else {
                this.goToZone('heroes', true);
            }
        } else {
            this.currentZone = 'gallery';
            this._syncZoneGroupVisibility();
            this.camControl.lookAtCurrent.set(this.player.x, 1.38, this.player.z);
            if (this.onZoneChange) {
                this.onZoneChange('gallery');
            }
            if (
                !this.canEnterSanctums
                && this.onAuthGatePrompt
                && (this._requestedInitialZone === 'heroes'
                    || this._requestedInitialZone === 'hall'
                    || this._requestedInitialZone === 'my_place')
            ) {
                this.onAuthGatePrompt('heroes');
            }
        }

        if (this.steles.length > 0 && this.onActiveExhibitChange) {
            this.onActiveExhibitChange(0, this.products[0]);
        }

        // Echte asynchrone Lade-Schranke ("Magie aktivieren"):
        // Spieler bleibt unsichtbar (visible = false), bis Level-GLB, initiale Idle-Pose (mixer.update(0)),
        // Basis-Texturen und GPU-Shader-Kompilierung (compileAsync / compile) abgeschlossen sind (0 T-Pose, 0 Pop-in!).
        await Promise.race([
            Promise.all([
                playerReadyPromise.catch(() => {}),
                coreTexturesPromise.catch(() => {}),
            ]),
            this._delayMs(2400),
        ]);
        if (this.destroyed || !this.renderer || !this.scene || !this.camera) return;

        this._updatePlayerKinematics(0.016, 0, 0, null, 0);
        this._updateCharacterFollowLights();
        if (this.player.builtRecord && this.player.builtRecord.skinnedRig && this.player.builtRecord.skinnedRig.mixer) {
            this.player.builtRecord.skinnedRig.mixer.update(0);
        }

        try {
            if (typeof this.renderer.compileAsync === 'function') {
                await Promise.race([
                    this.renderer.compileAsync(this.scene, this.camera),
                    this._delayMs(1400),
                ]);
            } else if (typeof this.renderer.compile === 'function') {
                this.renderer.compile(this.scene, this.camera);
            }
        } catch (compileErr) {
            try {
                if (typeof this.renderer.compile === 'function') {
                    this.renderer.compile(this.scene, this.camera);
                }
            } catch (e2) {}
        }
        if (this.destroyed || !this.renderer) return;

        this._engineReady = true;
        if (this.player.root) {
            this.player.root.visible = !(this.camControl.distance < 0.55);
        }
        this.renderer.render(this.scene, this.camera);

        this._scheduleNextExhibitLoad(16);

        this.running = true;
        this.lastTimeMs = performance.now();
        this.rafId = requestAnimationFrame(this._boundTick);

        if (this.onEngineReady) {
            this.onEngineReady();
        }
    }

    _syncZoneGroupVisibility() {
        if (this.galleryGroup) {
            this.galleryGroup.visible = true;
        }
        if (this.pantheonGroup) {
            this.pantheonGroup.visible = true;
        }
        if (this.outdoorWeatherGroup) {
            this.outdoorWeatherGroup.visible = true;
        }
        if (this.audio && typeof this.audio.setZone === 'function') {
            this.audio.setZone(this.currentZone);
        }
    }

    _trackDisposable(item) {
        if (item) this.disposables.push(item);
        return item;
    }

    /**
     * Zero Dynamic Light Overload: Saisonales Museums-Tageslicht + GPU-schonendes (schattenfreies) 3-Punkt-Character-Follow-Light am Spieler
     * (0 Shadow-Maps, 0 PointLight, 0 SpotLight!).
     */
    _buildLighting() {
        const THREE = this.THREE;
        const sKey = this.environmentInfo ? this.environmentInfo.season_key : 'autumn';

        let hemiSky = 0xfffdf8;
        let hemiGround = 0xd8cbb4;
        let sunHex = 0xffecc8;
        if (sKey === 'winter') {
            hemiSky = 0xf4f8ff;
            hemiGround = 0xd6d2c8;
            sunHex = 0xfff3dd;
        } else if (sKey === 'spring') {
            hemiSky = 0xfffbf7;
            hemiGround = 0xdfd3c0;
            sunHex = 0xffefd2;
        } else if (sKey === 'summer') {
            hemiSky = 0xfafcff;
            hemiGround = 0xdccfb8;
            sunHex = 0xfff0c4;
        }

        const hemi = new THREE.HemisphereLight(hemiSky, hemiGround, 1.26);
        hemi.position.set(0, 26, this.hallNorthZ);
        this.scene.add(hemi);

        const dirMain = new THREE.DirectionalLight(sunHex, 1.38);
        dirMain.position.set(11, 28, 14);
        dirMain.castShadow = false;
        this.scene.add(dirMain);

        const dirPantheon = new THREE.DirectionalLight(0xfff8ea, 0.68);
        dirPantheon.position.set(-10, 25, this.pantheonCenterZ + 18);
        dirPantheon.target.position.set(0, 2, this.pantheonCenterZ - 5);
        dirPantheon.castShadow = false;
        this.scene.add(dirPantheon);
        this.scene.add(dirPantheon.target);

        // Schattenfreies 3-Punkt-Character-Follow-Light (Warmes Key/Fill von schraeg vorn-oben + #C5A059 Rim-Light von hinten)
        const keyLight = new THREE.DirectionalLight(0xfff6e5, 0.72);
        keyLight.castShadow = false;
        const fillLight = new THREE.DirectionalLight(0xeef3ff, 0.34);
        fillLight.castShadow = false;
        const rimLight = new THREE.DirectionalLight(0xc5a059, 0.58);
        rimLight.castShadow = false;

        this.scene.add(keyLight, keyLight.target, fillLight, fillLight.target, rimLight, rimLight.target);
        this.charFollowLights = { keyLight, fillLight, rimLight };
    }

    _updateCharacterFollowLights() {
        if (!this.charFollowLights) return;
        const px = this.player.x || 0;
        const py = (this.player.y || 0) + this._computeFloorBaseY(px, this.player.z || 0);
        const pz = this.player.z || 0;
        const camY = this.camControl ? (this.camControl.yaw || 0) : 0;
        const sinC = Math.sin(camY);
        const cosC = Math.cos(camY);

        const { keyLight, fillLight, rimLight } = this.charFollowLights;
        keyLight.target.position.set(px, py + 1.05, pz);
        keyLight.position.set(px + sinC * 3.2 + cosC * 2.1, py + 4.4, pz + cosC * 3.2 - sinC * 2.1);

        fillLight.target.position.set(px, py + 0.95, pz);
        fillLight.position.set(px + sinC * 2.8 - cosC * 2.4, py + 2.6, pz + cosC * 2.8 + sinC * 2.4);

        rimLight.target.position.set(px, py + 1.15, pz);
        rimLight.position.set(px - sinC * 3.6, py + 3.2, pz - cosC * 3.6);
    }

    /**
     * Asynchroner High-Anisotropy PBR-Texturlader fuer alle Museums-Fototexturen + Golden-Hour PMREM.
     */
    _loadPhotorealisticTextures(galleryWidth, galleryLength, promWidth, promLength) {
        const THREE = this.THREE;
        if (!THREE || !THREE.TextureLoader || !this.mats) return Promise.resolve();

        const loader = new THREE.TextureLoader();
        const aniso = Math.max(4, this.maxAniso || 8);

        const configureStoneTex = (tex, wrapS, wrapT, repX, repY) => {
            applySrgbColorSpace(THREE, tex);
            tex.wrapS = wrapS;
            tex.wrapT = wrapT;
            tex.repeat.set(repX, repY);
            tex.generateMipmaps = true;
            tex.minFilter = THREE.LinearMipmapLinearFilter;
            tex.magFilter = THREE.LinearFilter;
            tex.anisotropy = aniso;
            tex.needsUpdate = true;
            this._trackDisposable(tex);
            return tex;
        };

        let pendingPrimary = 2;
        let resolvePrimary = null;
        const primaryDonePromise = new Promise((res) => {
            resolvePrimary = res;
        });
        const markPrimaryStep = () => {
            pendingPrimary -= 1;
            if (pendingPrimary <= 0 && resolvePrimary) {
                resolvePrimary();
                resolvePrimary = null;
            }
        };

        // 1. Echte 360-Grad Beaux-Arts Golden-Hour Glasatrium-Umgebung -> PMREMGenerator.fromEquirectangular
        loader.load(SHOWROOM_TEXTURE_URLS.museumGoldenHourEnv, (equirectTex) => {
            if (this.destroyed || !this.renderer || !this.scene || !THREE.PMREMGenerator) {
                if (equirectTex && equirectTex.dispose) equirectTex.dispose();
                return;
            }
            applySrgbColorSpace(THREE, equirectTex);
            equirectTex.mapping = THREE.EquirectangularReflectionMapping;

            const pmremGen = new THREE.PMREMGenerator(this.renderer);
            if (typeof pmremGen.compileEquirectangularShader === 'function') {
                pmremGen.compileEquirectangularShader();
            }
            const realEnvRT = pmremGen.fromEquirectangular(equirectTex);
            equirectTex.dispose();
            pmremGen.dispose();

            if (realEnvRT && realEnvRT.texture) {
                this._trackDisposable(realEnvRT);
                this.envTexture = realEnvRT.texture;
                this.scene.environment = realEnvRT.texture;
            }
        }, undefined, () => {});

        // 2. Polierter italienischer Calacatta-Gold-Marmorboden (Eingangshalle + Ehrenpromenade + Halle der Helden in ~3.8m Grossplatten!)
        loader.load(SHOWROOM_TEXTURE_URLS.calacattaGoldFloor, (tex) => {
            if (this.destroyed || !this.mats) {
                if (tex && tex.dispose) tex.dispose();
                markPrimaryStep();
                return;
            }
            configureStoneTex(tex, THREE.RepeatWrapping, THREE.RepeatWrapping, galleryWidth / 5.4, galleryLength / 5.4);
            if (this.mats.galleryFloor) {
                this.mats.galleryFloor.color.setHex(0xffffff);
                this.mats.galleryFloor.map = tex;
                this.mats.galleryFloor.bumpMap = tex;
                this.mats.galleryFloor.bumpScale = 0.006;
                this.mats.galleryFloor.roughnessMap = null;
                this.mats.galleryFloor.needsUpdate = true;
            }

            if (this.mats.promenadeFloor && tex.image) {
                const promTex = new THREE.Texture(tex.image);
                configureStoneTex(promTex, THREE.RepeatWrapping, THREE.RepeatWrapping, promWidth / 5.4, promLength / 5.4);
                this.mats.promenadeFloor.color.setHex(0xffffff);
                this.mats.promenadeFloor.map = promTex;
                this.mats.promenadeFloor.bumpMap = promTex;
                this.mats.promenadeFloor.bumpScale = 0.006;
                this.mats.promenadeFloor.needsUpdate = true;
            }

            if (this.mats.pantheonFloor && tex.image) {
                const hallSlabTex = new THREE.Texture(tex.image);
                configureStoneTex(hallSlabTex, THREE.RepeatWrapping, THREE.RepeatWrapping, 46.8 / 3.8, 48.8 / 3.8);
                this.mats.pantheonFloor.color.setHex(0xffffff);
                this.mats.pantheonFloor.map = hallSlabTex;
                this.mats.pantheonFloor.bumpMap = hallSlabTex;
                this.mats.pantheonFloor.bumpScale = 0.006;
                this.mats.pantheonFloor.roughness = 0.16;
                this.mats.pantheonFloor.envMapIntensity = 0.88;
                this.mats.pantheonFloor.roughnessMap = null;
                this.mats.pantheonFloor.needsUpdate = true;
            }
            markPrimaryStep();
        }, undefined, () => { markPrimaryStep(); });

        // 3. Pariser Louvre-Kalksteinwand mit plastischer Haussmann-Boiserie, Kranzgesims & Nero-Marquina-Sockel
        loader.load(SHOWROOM_TEXTURE_URLS.louvreBoiserieWall, (tex) => {
            if (this.destroyed || !this.mats) {
                if (tex && tex.dispose) tex.dispose();
                markPrimaryStep();
                return;
            }
            configureStoneTex(tex, THREE.RepeatWrapping, THREE.ClampToEdgeWrapping, 1.0, 1.0);
            if (this.mats.boiserieWall) {
                this.mats.boiserieWall.color.setHex(0xffffff);
                this.mats.boiserieWall.map = tex;
                this.mats.boiserieWall.bumpMap = tex;
                this.mats.boiserieWall.bumpScale = 0.014;
                this.mats.boiserieWall.needsUpdate = true;
            }
            markPrimaryStep();
        }, undefined, () => { markPrimaryStep(); });

        // 4. Satinierter Nero-Marquina-Naturstein (Podeste, Kolonnaden-Randfassung in Halle der Helden & Magma-Felskammer)
        loader.load(SHOWROOM_TEXTURE_URLS.neroMarquinaPedestal, (tex) => {
            if (this.destroyed || !this.mats) {
                if (tex && tex.dispose) tex.dispose();
                return;
            }
            configureStoneTex(tex, THREE.RepeatWrapping, THREE.RepeatWrapping, 1.25, 1.25);
            if (this.mats.neroPedestal) {
                this.mats.neroPedestal.color.setHex(0xffffff);
                this.mats.neroPedestal.map = tex;
                this.mats.neroPedestal.bumpMap = tex;
                this.mats.neroPedestal.bumpScale = 0.012;
                this.mats.neroPedestal.needsUpdate = true;
            }
            if (this.mats.pantheonNeroBorder && tex.image) {
                const borderTex = new THREE.Texture(tex.image);
                configureStoneTex(borderTex, THREE.RepeatWrapping, THREE.RepeatWrapping, 7.5, 1.4);
                this.mats.pantheonNeroBorder.color.setHex(0xffffff);
                this.mats.pantheonNeroBorder.map = borderTex;
                this.mats.pantheonNeroBorder.bumpMap = borderTex;
                this.mats.pantheonNeroBorder.bumpScale = 0.008;
                this.mats.pantheonNeroBorder.needsUpdate = true;
            }
            if (this.mats.lavaCavernNero && tex.image) {
                const cavTex = new THREE.Texture(tex.image);
                configureStoneTex(cavTex, THREE.RepeatWrapping, THREE.RepeatWrapping, 8.5, 2.5);
                this.mats.lavaCavernNero.color.setHex(0xb8aea4);
                this.mats.lavaCavernNero.map = cavTex;
                this.mats.lavaCavernNero.bumpMap = cavTex;
                this.mats.lavaCavernNero.bumpScale = 0.018;
                this.mats.lavaCavernNero.needsUpdate = true;
            }
        }, undefined, () => {});

        // 5. Klassische Pantheon-Travertin-Kassettendecke mit 24K-Gold-Rosetten & Sonnenlicht-Oculus
        loader.load(SHOWROOM_TEXTURE_URLS.pantheonCofferedDome, (tex) => {
            if (this.destroyed || !this.mats) {
                if (tex && tex.dispose) tex.dispose();
                return;
            }
            configureStoneTex(tex, THREE.ClampToEdgeWrapping, THREE.ClampToEdgeWrapping, 1.0, 1.0);
            if (this.mats.pantheonDome) {
                this.mats.pantheonDome.color.setHex(0xffffff);
                this.mats.pantheonDome.map = tex;
                this.mats.pantheonDome.bumpMap = tex;
                this.mats.pantheonDome.bumpScale = 0.016;
                this.mats.pantheonDome.needsUpdate = true;
            }
        }, undefined, () => {});

        return primaryDonePromise;
    }

    _buildLouvreArchitecture(isMobile) {
        const THREE = this.THREE;
        this.galleryGroup = new THREE.Group();
        this.scene.add(this.galleryGroup);

        const totalLength = (this.hallSouthZ - this.hallNorthZ) + 4.0;
        const centerZ = (this.hallSouthZ + this.hallNorthZ) * 0.5;
        const hallW = 24.0;
        const wallH = 7.4;

        this.mats = {
            boiserieWall: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf4ecde,
                roughness: 0.63,
                metalness: 0.04,
                envMapIntensity: 0.76,
            })),
            limestoneArch: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xeee5d4,
                roughness: 0.58,
                metalness: 0.04,
                envMapIntensity: 0.74,
            })),
            neroPedestal: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x1b1917,
                roughness: 0.26,
                metalness: 0.08,
                envMapIntensity: 1.08,
            })),
            obsidianWall: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xede3d1,
                roughness: 0.62,
                metalness: 0.04,
                envMapIntensity: 0.70,
            })),
            cofferRecess: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf7f2e8,
                roughness: 0.72,
                metalness: 0.03,
                envMapIntensity: 0.55,
            })),
            archSteel: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x3d3428,
                roughness: 0.30,
                metalness: 0.80,
                envMapIntensity: 1.18,
            })),
            wroughtIronMatte: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x17191f,
                roughness: 0.42,
                metalness: 0.62,
                envMapIntensity: 0.92,
            })),
            goldBrushed: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xc5a059,
                roughness: 0.22,
                metalness: 0.90,
                envMapIntensity: 1.35,
            })),
            goldChampagne: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xe6c687,
                roughness: 0.18,
                metalness: 0.93,
                envMapIntensity: 1.42,
            })),
            goldMatcapBright: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xd4af37,
                roughness: 0.19,
                metalness: 0.92,
                envMapIntensity: 1.40,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -2,
            })),
            goldMatcapBrushed: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xc5a059,
                roughness: 0.24,
                metalness: 0.90,
                envMapIntensity: 1.32,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -2,
            })),
            obsidianMatcap: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x1b1917,
                roughness: 0.28,
                metalness: 0.08,
                envMapIntensity: 1.05,
            })),
            galleryFloor: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf7f2e7,
                roughness: 0.15,
                metalness: 0.04,
                envMapIntensity: 0.88,
            })),
            promenadeFloor: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf7f2e7,
                roughness: 0.15,
                metalness: 0.04,
                envMapIntensity: 0.88,
            })),
            pantheonFloor: null,
            pantheonNeroBorder: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x181615,
                roughness: 0.22,
                metalness: 0.08,
                envMapIntensity: 0.96,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -3,
            })),
            lavaCavernNero: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x1b1715,
                roughness: 0.54,
                metalness: 0.14,
                envMapIntensity: 0.72,
            })),
            avatarBaseRing: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xc5a059,
                roughness: 0.23,
                metalness: 0.90,
                envMapIntensity: 1.32,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -2,
            })),
            pantheonDome: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf5ebd8,
                roughness: 0.56,
                metalness: 0.14,
                envMapIntensity: 0.88,
                side: THREE.BackSide,
            })),
            alabasterStep: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf2ede2,
                roughness: 0.26,
                metalness: 0.05,
                envMapIntensity: 0.82,
            })),
            sapphireTrim: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x133b5c,
                roughness: 0.22,
                metalness: 0.16,
                envMapIntensity: 0.96,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -3,
            })),
            coveWarmGlow: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xfff5d8,
            })),
            beaconGold: this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0xffe89e })),
            mySlotGlow: this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0xfde68a })),
            velvetPad: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x171412,
                roughness: 0.92,
                metalness: 0.04,
            })),
            pyramidGlass: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xfffdf7,
                roughness: 0.08,
                metalness: 0.10,
                envMapIntensity: 1.18,
                transparent: true,
                opacity: 0.10,
                depthWrite: false,
                side: THREE.DoubleSide,
            })),
            vitrineGlass: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xffffff,
                roughness: 0.05,
                metalness: 0.10,
                envMapIntensity: 1.42,
                transparent: true,
                opacity: 0.14,
                depthWrite: false,
            })),
            frostedCrystalGlass: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf8fafc,
                roughness: 0.18,
                metalness: 0.08,
                envMapIntensity: 1.25,
                transparent: true,
                opacity: 0.38,
                depthWrite: false,
                side: THREE.DoubleSide,
            })),
        };

        this.contactShadowTex = buildContactShadowTexture(THREE, this.disposables);
        this.sparkSpriteTex = buildSparkParticleTexture(THREE, this.disposables);
        this.mats.contactShadow = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: this.contactShadowTex,
            transparent: true,
            opacity: 0.72,
            depthWrite: false,
            polygonOffset: true,
            polygonOffsetFactor: -1,
            polygonOffsetUnits: -2,
        }));

        // Polierter italienischer Calacatta-Gold-Marmorboden in der Produkt-Eingangshalle
        const floorGeo = this._trackDisposable(new THREE.PlaneGeometry(hallW, totalLength));
        this.floorMesh = new THREE.Mesh(floorGeo, this.mats.galleryFloor);
        this.floorMesh.rotation.x = -Math.PI * 0.5;
        this.floorMesh.position.set(0, 0, centerZ);
        this.floorMesh.userData = { isFloor: true };
        this.galleryGroup.add(this.floorMesh);
        this.walkableFloorMeshes.push(this.floorMesh);

        // Gebuerstete 24K-Gold-Bodenintarsien als gebuendeltes InstancedMesh
        const inlayLength = totalLength - 3.0;
        const inlayStripGeo = this._trackDisposable(new THREE.PlaneGeometry(0.05, inlayLength));
        const inlayPositions = [-2.35, -2.20, 2.20, 2.35];
        const inlayInst = new THREE.InstancedMesh(inlayStripGeo, this.mats.goldMatcapBrushed, inlayPositions.length);
        const dummy = new THREE.Object3D();
        for (let idx = 0; idx < inlayPositions.length; idx++) {
            dummy.position.set(inlayPositions[idx], 0.005, centerZ);
            dummy.rotation.set(-Math.PI * 0.5, 0, 0);
            dummy.scale.set(1, 1, 1);
            dummy.updateMatrix();
            inlayInst.setMatrixAt(idx, dummy.matrix);
        }
        this.galleryGroup.add(inlayInst);

        const medOuter = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(1.18, 1.30, 48)),
            this.mats.goldMatcapBright
        );
        medOuter.rotation.x = -Math.PI * 0.5;
        medOuter.position.set(0, 0.006, centerZ);
        this.galleryGroup.add(medOuter);

        const medInner = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.62, 0.70, 48)),
            this.mats.goldMatcapBrushed
        );
        medInner.rotation.x = -Math.PI * 0.5;
        medInner.position.set(0, 0.006, centerZ);
        this.galleryGroup.add(medInner);

        // Click-to-Walk / Tap-to-Walk 24K-Gold-Zielring auf dem Boden
        const targetRingGeo = this._trackDisposable(new THREE.RingGeometry(0.28, 0.39, 32));
        const targetRingMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            color: 0xc5a059,
            transparent: true,
            opacity: 0.0,
            side: THREE.DoubleSide,
            depthWrite: false,
        }));
        this.walkTargetRing = new THREE.Mesh(targetRingGeo, targetRingMat);
        this.walkTargetRing.rotation.x = -Math.PI * 0.5;
        this.walkTargetRing.position.set(0, 0.025, 0);
        this.clickMoveRing = this.walkTargetRing;
        this.scene.add(this.walkTargetRing);

        // Pariser Haussmann-Boiserie-Waende mit abgerundeten Sockelprofilen, Kranzgesimsen & sanften Schattenfugen
        const corniceUpperGeo = this._trackDisposable(new THREE.BoxGeometry(0.96, 0.38, totalLength + 0.4));
        const corniceRoundMoldingGeo = this._trackDisposable(new THREE.CylinderGeometry(0.085, 0.085, totalLength + 0.4, 14));
        corniceRoundMoldingGeo.rotateX(Math.PI * 0.5);
        const coveLightStripGeo = this._trackDisposable(new THREE.PlaneGeometry(0.18, totalLength - 0.6));

        const galleryMinZ = centerZ - totalLength * 0.5;
        const galleryMaxZ = centerZ + totalLength * 0.5;
        const westGateHalfSpan = 2.65;
        const westOpenMinZ = -westGateHalfSpan;
        const westOpenMaxZ = westGateHalfSpan;
        const westOpenH = 5.55;

        const addRoundedWallSkirting = (xPos, zStart, zEnd, inwardSign) => {
            const segLen = Math.max(0.3, zEnd - zStart);
            const segCz = (zStart + zEnd) * 0.5;
            const plinth = new THREE.Mesh(
                this._trackDisposable(new THREE.BoxGeometry(0.16, 0.24, segLen)),
                this.mats.neroPedestal
            );
            plinth.position.set(xPos + inwardSign * 0.08, 0.12, segCz);
            this.galleryGroup.add(plinth);

            const roundTop = new THREE.Mesh(
                this._trackDisposable(new THREE.CylinderGeometry(0.048, 0.048, segLen, 12)),
                this.mats.goldBrushed
            );
            roundTop.rotation.x = Math.PI * 0.5;
            roundTop.position.set(xPos + inwardSign * 0.14, 0.255, segCz);
            this.galleryGroup.add(roundTop);
        };

        [-1, 1].forEach((sideSign) => {
            const wx = sideSign * hallW * 0.5;
            if (sideSign < 0) {
                const westNorthLen = Math.max(0.5, westOpenMinZ - galleryMinZ);
                const westSouthLen = Math.max(0.5, galleryMaxZ - westOpenMaxZ);
                const westNorthCz = (galleryMinZ + westOpenMinZ) * 0.5;
                const westSouthCz = (westOpenMaxZ + galleryMaxZ) * 0.5;

                [
                    { len: westNorthLen, cz: westNorthCz, minZ: galleryMinZ, maxZ: westOpenMinZ },
                    { len: westSouthLen, cz: westSouthCz, minZ: westOpenMaxZ, maxZ: galleryMaxZ },
                ].forEach((seg) => {
                    const segBack = new THREE.Mesh(
                        this._trackDisposable(new THREE.BoxGeometry(0.55, wallH + 0.35, seg.len)),
                        this.mats.limestoneArch
                    );
                    segBack.position.set(wx - 0.28, (wallH + 0.35) * 0.5, seg.cz);
                    this.galleryGroup.add(segBack);

                    const segFace = new THREE.Mesh(
                        this._trackDisposable(createBoiserieWallPlaneGeo(THREE, seg.len, wallH, 7.8)),
                        this.mats.boiserieWall
                    );
                    segFace.position.set(wx + 0.025, wallH * 0.5, seg.cz);
                    segFace.rotation.y = Math.PI * 0.5;
                    this.galleryGroup.add(segFace);

                    addRoundedWallSkirting(wx, seg.minZ, seg.maxZ, 1);
                });

                const westLintelH = Math.max(0.5, (wallH + 0.35) - westOpenH);
                const westLintel = new THREE.Mesh(
                    this._trackDisposable(new THREE.BoxGeometry(0.58, westLintelH, westGateHalfSpan * 2 + 0.2)),
                    this.mats.limestoneArch
                );
                westLintel.position.set(wx - 0.26, westOpenH + westLintelH * 0.5, 0.0);
                this.galleryGroup.add(westLintel);
            } else {
                const eastBack = new THREE.Mesh(
                    this._trackDisposable(new THREE.BoxGeometry(0.55, wallH + 0.35, totalLength)),
                    this.mats.limestoneArch
                );
                eastBack.position.set(wx + 0.28, (wallH + 0.35) * 0.5, centerZ);
                this.galleryGroup.add(eastBack);

                const eastFace = new THREE.Mesh(
                    this._trackDisposable(createBoiserieWallPlaneGeo(THREE, totalLength, wallH, 7.8)),
                    this.mats.boiserieWall
                );
                eastFace.position.set(wx - 0.025, wallH * 0.5, centerZ);
                eastFace.rotation.y = -Math.PI * 0.5;
                this.galleryGroup.add(eastFace);

                addRoundedWallSkirting(wx, galleryMinZ, galleryMaxZ, -1);
            }

            const cornice = new THREE.Mesh(corniceUpperGeo, this.mats.limestoneArch);
            cornice.position.set(wx - sideSign * 0.14, wallH + 0.18, centerZ);
            this.galleryGroup.add(cornice);

            const roundGoldTrim = new THREE.Mesh(corniceRoundMoldingGeo, this.mats.goldBrushed);
            roundGoldTrim.position.set(wx - sideSign * 0.42, wallH - 0.02, centerZ);
            this.galleryGroup.add(roundGoldTrim);

            const coveStrip = new THREE.Mesh(coveLightStripGeo, this.mats.coveWarmGlow);
            coveStrip.rotation.x = -Math.PI * 0.5;
            coveStrip.position.set(wx - sideSign * 0.42, wallH + 0.38, centerZ);
            this.galleryGroup.add(coveStrip);
        });

        // Abgerundete Halbrund-Wandpilaster mit gedrehten 24K-Gold-Torus-Basen & Kapitellen via InstancedMesh
        const pierSpacing = 6.2;
        const pierCountSide = Math.max(3, Math.floor(totalLength / pierSpacing));
        const pierGeo = this._trackDisposable(new THREE.CylinderGeometry(0.30, 0.34, wallH - 0.52, 16));
        const capGeo = this._trackDisposable(new THREE.TorusGeometry(0.35, 0.085, 10, 20));
        capGeo.rotateX(Math.PI * 0.5);
        const plinthGeo = this._trackDisposable(new THREE.CylinderGeometry(0.42, 0.46, 0.34, 16));

        this._galleryPierPlacements = [];
        for (let i = 0; i < pierCountSide; i++) {
            const pz = this.hallNorthZ + 2.5 + i * ((totalLength - 5.0) / Math.max(1, pierCountSide - 1));
            [-hallW * 0.5 + 0.20, hallW * 0.5 - 0.20].forEach((px) => {
                if (px < 0 && Math.abs(pz - 0.0) < (westGateHalfSpan + 0.85)) {
                    return;
                }
                this._galleryPierPlacements.push([px, pz]);
            });
        }

        const totalPiers = Math.max(1, this._galleryPierPlacements.length);
        const pierInst = new THREE.InstancedMesh(pierGeo, this.mats.limestoneArch, totalPiers);
        const capInst = new THREE.InstancedMesh(capGeo, this.mats.goldMatcapBright, totalPiers);
        const plinthInst = new THREE.InstancedMesh(plinthGeo, this.mats.neroPedestal, totalPiers);

        const vaultRibCount = pierCountSide;
        const vaultArcSpan = Math.PI * 0.72;
        const vaultRibRadius = hallW * 0.555;
        const vaultCenterY = wallH - Math.cos(vaultArcSpan * 0.5) * vaultRibRadius;
        this._galleryRoofApexY = vaultCenterY + vaultRibRadius;

        const vaultRibGeo = this._trackDisposable(new THREE.TorusGeometry(vaultRibRadius, 0.11, 10, 40, vaultArcSpan));
        const vaultGoldFilletGeo = this._trackDisposable(new THREE.TorusGeometry(vaultRibRadius - 0.09, 0.048, 8, 40, vaultArcSpan));
        const vaultRibInst = new THREE.InstancedMesh(vaultRibGeo, this.mats.archSteel, vaultRibCount);
        const vaultGoldInst = new THREE.InstancedMesh(vaultGoldFilletGeo, this.mats.goldChampagne, vaultRibCount);

        for (let pIdx = 0; pIdx < this._galleryPierPlacements.length; pIdx++) {
            const [px, pz] = this._galleryPierPlacements[pIdx];
            dummy.position.set(px, 0.34 + (wallH - 0.52) * 0.5, pz);
            dummy.rotation.set(0, 0, 0);
            dummy.scale.set(1, 1, 1);
            dummy.updateMatrix();
            pierInst.setMatrixAt(pIdx, dummy.matrix);

            dummy.position.set(px, wallH - 0.16, pz);
            dummy.updateMatrix();
            capInst.setMatrixAt(pIdx, dummy.matrix);

            dummy.position.set(px, 0.17, pz);
            dummy.updateMatrix();
            plinthInst.setMatrixAt(pIdx, dummy.matrix);
        }

        for (let i = 0; i < pierCountSide; i++) {
            const pz = this.hallNorthZ + 2.5 + i * ((totalLength - 5.0) / Math.max(1, pierCountSide - 1));
            dummy.position.set(0, vaultCenterY, pz);
            dummy.rotation.set(0, 0, (Math.PI - vaultArcSpan) * 0.5);
            dummy.scale.set(1, 1, 1);
            dummy.updateMatrix();
            vaultRibInst.setMatrixAt(i, dummy.matrix);
            vaultGoldInst.setMatrixAt(i, dummy.matrix);
        }
        pierInst.instanceMatrix.needsUpdate = true;
        capInst.instanceMatrix.needsUpdate = true;
        plinthInst.instanceMatrix.needsUpdate = true;
        vaultRibInst.instanceMatrix.needsUpdate = true;
        vaultGoldInst.instanceMatrix.needsUpdate = true;
        this.galleryGroup.add(pierInst, capInst, plinthInst, vaultRibInst, vaultGoldInst);

        // Sued-Stirnwand (Eingang) & Nord-Stirnwand in echter Louvre-Boiserie (geoeffnet fuer das 24K-Gold-Ehrenportal!)
        const southWallBacking = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(hallW + 0.8, wallH + 0.4, 0.6)),
            this.mats.limestoneArch
        );
        southWallBacking.position.set(0, (wallH + 0.4) * 0.5, this.hallSouthZ + 0.32);
        this.galleryGroup.add(southWallBacking);

        const southBoiserie = new THREE.Mesh(
            this._trackDisposable(createBoiserieWallPlaneGeo(THREE, hallW, wallH, 7.8)),
            this.mats.boiserieWall
        );
        southBoiserie.rotation.y = Math.PI;
        southBoiserie.position.set(0, wallH * 0.5, this.hallSouthZ - 0.025);
        this.galleryGroup.add(southBoiserie);

        const wingSpan = (hallW * 0.5) - this.portalHalfW;
        const wingWallGeo = this._trackDisposable(new THREE.BoxGeometry(wingSpan, wallH + 0.4, 0.65));
        const wingBoiserieGeo = this._trackDisposable(createBoiserieWallPlaneGeo(THREE, wingSpan, wallH, 7.8));
        [-1, 1].forEach((sideSign) => {
            const wxCenter = sideSign * (this.portalHalfW + wingSpan * 0.5);
            const wingWall = new THREE.Mesh(wingWallGeo, this.mats.limestoneArch);
            wingWall.position.set(wxCenter, (wallH + 0.4) * 0.5, this.hallNorthZ - 0.30);
            this.galleryGroup.add(wingWall);

            const wingFace = new THREE.Mesh(wingBoiserieGeo, this.mats.boiserieWall);
            wingFace.position.set(wxCenter, wallH * 0.5, this.hallNorthZ + 0.05);
            this.galleryGroup.add(wingFace);
        });

        const portalHeaderH = (wallH + 0.4) - 5.65;
        const portalHeader = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(this.portalHalfW * 2 + 0.4, portalHeaderH, 0.80)),
            this.mats.limestoneArch
        );
        portalHeader.position.set(0, 5.65 + portalHeaderH * 0.5, this.hallNorthZ - 0.12);
        this.galleryGroup.add(portalHeader);

        // Luftiges Pariser Beaux-Arts Glas- & 24K-Gold-Tonnengewoelbe ueber der gesamten Eingangshalle
        const barrelSpanLength = Math.max(8.0, this.hallSouthZ - this.hallNorthZ);
        const barrelGlassGeo = this._trackDisposable(
            new THREE.CylinderGeometry(vaultRibRadius + 0.04, vaultRibRadius + 0.04, barrelSpanLength, 32, 1, true, -vaultArcSpan * 0.5, vaultArcSpan)
        );
        const barrelGlass = new THREE.Mesh(barrelGlassGeo, this.mats.pyramidGlass);
        barrelGlass.rotation.set(-Math.PI * 0.5, 0, 0);
        barrelGlass.position.set(0, vaultCenterY, centerZ);
        this.galleryGroup.add(barrelGlass);

        const purlinAngles = [-0.42, -0.21, 0.0, 0.21, 0.42];
        const purlinGeo = this._trackDisposable(new THREE.CylinderGeometry(0.045, 0.045, totalLength, 10));
        purlinGeo.rotateX(Math.PI * 0.5);
        const purlinInst = new THREE.InstancedMesh(purlinGeo, this.mats.goldBrushed, purlinAngles.length);
        for (let pi = 0; pi < purlinAngles.length; pi++) {
            const ang = purlinAngles[pi];
            dummy.position.set(
                Math.sin(ang) * vaultRibRadius,
                vaultCenterY + Math.cos(ang) * vaultRibRadius,
                centerZ
            );
            dummy.rotation.set(0, 0, -ang);
            dummy.scale.set(1, 1, 1);
            dummy.updateMatrix();
            purlinInst.setMatrixAt(pi, dummy.matrix);
        }
        purlinInst.instanceMatrix.needsUpdate = true;
        this.galleryGroup.add(purlinInst);

        const lunetteShape = new THREE.Shape();
        const lunetteSegs = 28;
        for (let li = 0; li <= lunetteSegs; li++) {
            const a = -vaultArcSpan * 0.5 + (li / lunetteSegs) * vaultArcSpan;
            const lx = Math.sin(a) * vaultRibRadius;
            const ly = vaultCenterY + Math.cos(a) * vaultRibRadius;
            if (li === 0) lunetteShape.moveTo(lx, ly);
            else lunetteShape.lineTo(lx, ly);
        }
        lunetteShape.closePath();
        const lunetteGeo = this._trackDisposable(new THREE.ShapeGeometry(lunetteShape));

        const southLunette = new THREE.Mesh(lunetteGeo, this.mats.pyramidGlass);
        southLunette.position.set(0, 0, this.hallSouthZ);
        this.galleryGroup.add(southLunette);

        // Stirnfries ueber dem goldenen Ehrenportal (dauerhaft Z-Fighting-frei: +0.028 m Normalen-Abstand + polygonOffset -2/-2 + depthWrite!)
        const friezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'HALLE DER HELDEN',
            'EHRENPORTAL · MEIN SEELENFUNKE',
            this.disposables
        );
        const friezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: friezeTex,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2,
            depthWrite: true,
        }));
        const friezeBacking = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(10.6, 1.82, 0.24)),
            this.mats.goldMatcapBright
        );
        friezeBacking.position.set(0, 6.58, this.hallNorthZ + 0.36);
        this.galleryGroup.add(friezeBacking);

        const friezePlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(10.32, 1.62)),
            friezeMat
        );
        friezePlane.position.set(0, 6.58, this.hallNorthZ + 0.36 + 0.12 + 0.028);
        this.galleryGroup.add(friezePlane);

        const southFriezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'UNSERE KOLLEKTION',
            'MANUFAKTUR GIFHORN · 3D USER LOBBY',
            this.disposables
        );
        const southFriezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: southFriezeTex,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2,
            depthWrite: true,
        }));
        const southFriezeBack = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(9.88, 1.96, 0.20)),
            this.mats.goldMatcapBright
        );
        southFriezeBack.position.set(0, 5.15, this.hallSouthZ - 0.16);
        this.galleryGroup.add(southFriezeBack);

        const southFriezePlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(9.6, 1.72)),
            southFriezeMat
        );
        southFriezePlane.rotation.y = Math.PI;
        southFriezePlane.position.set(0, 5.15, this.hallSouthZ - 0.16 - 0.10 - 0.028);
        this.galleryGroup.add(southFriezePlane);
    }

    /**
     * Baustein 2a: Dynamischer Jahreszeiten-Himmel & Aussenpartikel ueber dem Glas-Kuppeldach (STRIKT AUSSERHALB y >= 14.85 m, 0 Flocken im Innenraum!).
     */
    _buildSeasonalSkyAndOutdoorWeather(isMobile) {
        const THREE = this.THREE;
        const sKey = this.environmentInfo ? this.environmentInfo.season_key : 'autumn';
        const hKey = this.environmentInfo ? this.environmentInfo.holiday_key : null;

        const skyGeo = this._trackDisposable(new THREE.SphereGeometry(240, 24, 16));
        const skyCanvas = document.createElement('canvas');
        skyCanvas.width = 32;
        skyCanvas.height = 128;
        const skyCtx = skyCanvas.getContext('2d');
        const skyGrad = skyCtx.createLinearGradient(0, 0, 0, 128);

        if (sKey === 'winter' || hKey === 'weihnachten') {
            skyGrad.addColorStop(0.0, '#bdd4ed');
            skyGrad.addColorStop(0.38, '#e6eff9');
            skyGrad.addColorStop(0.72, '#f5ede0');
            skyGrad.addColorStop(1.0, '#e3e8ef');
        } else if (sKey === 'spring' || hKey === 'ostern') {
            skyGrad.addColorStop(0.0, '#c8e3f8');
            skyGrad.addColorStop(0.36, '#fbf2e9');
            skyGrad.addColorStop(0.70, '#f8e6ce');
            skyGrad.addColorStop(1.0, '#ece2d0');
        } else if (sKey === 'summer') {
            skyGrad.addColorStop(0.0, '#a8cff5');
            skyGrad.addColorStop(0.34, '#f3f2e8');
            skyGrad.addColorStop(0.68, '#f7e5be');
            skyGrad.addColorStop(1.0, '#eae0ca');
        } else {
            skyGrad.addColorStop(0.0, '#c6d4ea');
            skyGrad.addColorStop(0.35, '#f6ead6');
            skyGrad.addColorStop(0.70, '#efcfa2');
            skyGrad.addColorStop(1.0, '#e2ceb0');
        }
        skyCtx.fillStyle = skyGrad;
        skyCtx.fillRect(0, 0, 32, 128);
        const skyTex = this._trackDisposable(new THREE.CanvasTexture(skyCanvas));
        applySrgbColorSpace(THREE, skyTex);
        const skyMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: skyTex, side: THREE.BackSide, fog: false }));
        const skyDome = new THREE.Mesh(skyGeo, skyMat);
        skyDome.position.set(0, 0, (this.hallSouthZ + this.pantheonCenterZ) * 0.5);
        this.galleryGroup.add(skyDome);

        this.outdoorWeatherGroup = new THREE.Group();
        this.scene.add(this.outdoorWeatherGroup);

        const isBalanced = (this.userSettings && this.userSettings.graphicsQuality === 'balanced') || isMobile;
        const count = isBalanced ? 90 : 180;
        const positions = new Float32Array(count * 3);
        this.outdoorWeatherMeta = [];
        const rng = mulberry32(20261119);
        const roofMinY = Math.max(14.85, (this._galleryRoofApexY || 13.8) + 0.95);
        const roofMaxY = 27.5;

        for (let i = 0; i < count; i++) {
            const overPantheon = (i % 4 === 0);
            const minY = overPantheon ? 38.2 : roofMinY;
            const maxY = overPantheon ? 47.5 : roofMaxY;
            const bx = overPantheon ? (-9.0 + rng() * 18.0) : (-15.0 + rng() * 30.0);
            const bz = overPantheon
                ? (this.pantheonCenterZ - 9.0 + rng() * 18.0)
                : (this.hallNorthZ - 4.0 + rng() * ((this.hallSouthZ - this.hallNorthZ) + 8.0));
            const ph = rng();
            const fallSpeed = (sKey === 'summer') ? (0.04 + rng() * 0.05) : (0.11 + rng() * 0.12);
            const swayAmp = 0.8 + rng() * 1.4;
            this.outdoorWeatherMeta.push({ bx, bz, minY, maxY, ph, fallSpeed, swayAmp });

            positions[i * 3] = bx;
            positions[i * 3 + 1] = minY + ph * (maxY - minY);
            positions[i * 3 + 2] = bz;
        }

        const weatherGeo = this._trackDisposable(new THREE.BufferGeometry());
        this.outdoorWeatherPosAttr = new THREE.BufferAttribute(positions, 3);
        weatherGeo.setAttribute('position', this.outdoorWeatherPosAttr);

        const weatherSprite = buildSeasonalOutdoorSpriteTexture(THREE, sKey, hKey, this.disposables);
        const pSize = (sKey === 'winter' || hKey === 'weihnachten') ? 0.44 : (sKey === 'summer' ? 0.36 : 0.48);
        const weatherMat = this._trackDisposable(new THREE.PointsMaterial({
            size: pSize,
            map: weatherSprite,
            transparent: true,
            opacity: sKey === 'summer' ? 0.62 : 0.88,
            depthWrite: false,
        }));
        const weatherPoints = new THREE.Points(weatherGeo, weatherMat);
        weatherPoints.frustumCulled = false;
        this.outdoorWeatherGroup.add(weatherPoints);
    }

    _updateSeasonalOutdoorWeather() {
        if (!this.outdoorWeatherPosAttr || this.outdoorWeatherMeta.length === 0) {
            return;
        }
        const t = this.elapsedTime;
        const arr = this.outdoorWeatherPosAttr.array;
        for (let i = 0; i < this.outdoorWeatherMeta.length; i++) {
            const m = this.outdoorWeatherMeta[i];
            const u = 1.0 - (((m.ph + t * m.fallSpeed) % 1 + 1) % 1);
            const safeY = Math.max(m.minY, m.minY + u * (m.maxY - m.minY));
            const i3 = i * 3;
            arr[i3] = m.bx + Math.sin(t * 0.85 + m.ph * TWO_PI) * m.swayAmp;
            arr[i3 + 1] = safeY;
            arr[i3 + 2] = m.bz + Math.cos(t * 0.72 + m.ph * TWO_PI) * m.swayAmp * 0.75;
        }
        this.outdoorWeatherPosAttr.needsUpdate = true;
    }

    /**
     * Geschmeidiger FOV-Glide beim Zonen-/Raumwechsel (Teleporter): Boost klingt exponentiell ab, 0 Ruckler.
     */
    _updateTeleportFovGlide(dt) {
        if (!this.camera) return;
        const baseFov = 50;
        if (this.teleportFovBoost > 0.01) {
            this.teleportFovBoost = this.teleportFovBoost * Math.exp(-dt * 3.6);
        } else {
            this.teleportFovBoost = 0;
        }
        const sprintTargetFov = (this.player && this.player.sprintBoost) ? (this.player.sprintBoost * 3.2) : 0;
        this.sprintFovBoost = lerp(this.sprintFovBoost || 0, sprintTargetFov, 1.0 - Math.exp(-dt * 7.5));
        const targetFov = baseFov + this.teleportFovBoost + this.sprintFovBoost;
        if (Math.abs(this.camera.fov - targetFov) > 0.01) {
            this.camera.fov = targetFov;
            this.camera.updateProjectionMatrix();
        }
    }

    /**
     * Baustein 2b: Geschmackvolle Innenraum-Akzente im edlen Schwarz-Gold-Manufaktur-Stil (#C5A059) passend zu Jahreszeit & Feiertag.
     */
    _buildSeasonalIndoorAccents() {
        if (!this._galleryPierPlacements || this._galleryPierPlacements.length === 0 || !this.galleryGroup) return;
        const THREE = this.THREE;
        const sKey = this.environmentInfo ? this.environmentInfo.season_key : 'autumn';
        const hKey = this.environmentInfo ? this.environmentInfo.holiday_key : null;

        let wreathHex = 0xc5a059;
        let accentGemHex = 0xfde68a;
        if (hKey === 'weihnachten' || sKey === 'winter') {
            wreathHex = 0x1d3b2a;
            accentGemHex = 0xd4af37;
        } else if (hKey === 'valentinstag') {
            wreathHex = 0x1f191a;
            accentGemHex = 0xbe123c;
        } else if (sKey === 'spring' || hKey === 'ostern') {
            wreathHex = 0x2f4838;
            accentGemHex = 0xfbcfe8;
        } else if (sKey === 'autumn' || hKey === 'halloween') {
            wreathHex = 0x5c3419;
            accentGemHex = 0xd97706;
        }

        const wreathFoliageMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            color: wreathHex,
            roughness: 0.52,
            metalness: 0.24,
            envMapIntensity: 0.90,
        }));
        const accentOrnamentMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            color: accentGemHex,
            roughness: 0.22,
            metalness: 0.78,
            envMapIntensity: 1.30,
        }));

        const wreathTorusGeo = this._trackDisposable(new THREE.TorusGeometry(0.36, 0.068, 10, 24));
        const innerGoldRingGeo = this._trackDisposable(new THREE.TorusGeometry(0.28, 0.022, 8, 24));
        const ornamentGeo = this._trackDisposable(new THREE.SphereGeometry(0.042, 10, 10));
        const warmDotGeo = this._trackDisposable(new THREE.SphereGeometry(0.022, 8, 8));

        const maxWreaths = Math.min(8, this._galleryPierPlacements.length);
        for (let wi = 0; wi < maxWreaths; wi++) {
            const [px, pz] = this._galleryPierPlacements[wi];
            const inwardSign = px < 0 ? 1 : -1;
            const wGroup = new THREE.Group();
            wGroup.position.set(px + inwardSign * 0.34, 4.55, pz);
            wGroup.rotation.y = inwardSign * Math.PI * 0.5;

            wGroup.add(new THREE.Mesh(wreathTorusGeo, wreathFoliageMat));
            const innerRing = new THREE.Mesh(innerGoldRingGeo, this.mats.goldMatcapBright);
            innerRing.position.z = 0.03;
            wGroup.add(innerRing);

            for (let oi = 0; oi < 6; oi++) {
                const ang = (oi / 6) * TWO_PI;
                const orn = new THREE.Mesh(ornamentGeo, oi % 2 === 0 ? this.mats.goldMatcapBright : accentOrnamentMat);
                orn.position.set(Math.cos(ang) * 0.36, Math.sin(ang) * 0.36, 0.055);
                wGroup.add(orn);

                const dot = new THREE.Mesh(warmDotGeo, this.mats.coveWarmGlow);
                dot.position.set(Math.cos(ang + 0.52) * 0.35, Math.sin(ang + 0.52) * 0.35, 0.062);
                wGroup.add(dot);
            }
            this.galleryGroup.add(wGroup);
        }
    }

    /**
     * Baustein 3: Monumentales "Episches Verschlossenes Tor zur Aussenwelt" exakt an der Westwand (X = -11.92, Z = 0.0).
     */
    _buildWestOuterWorldGate() {
        const THREE = this.THREE;
        this.westOuterGateGroup = new THREE.Group();
        this.westOuterGateGroup.position.set(-11.92, 0, 0.0);
        this.galleryGroup.add(this.westOuterGateGroup);

        const halfSpan = 2.45;
        const springY = 3.55;
        const sKey = this.environmentInfo ? this.environmentInfo.season_key : 'autumn';
        const hKey = this.environmentInfo ? this.environmentInfo.holiday_key : null;

        const panoTex = buildOuterWorldPanoramaTexture(THREE, this.maxAniso, sKey, hKey, this.disposables);
        const panoMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: panoTex }));
        const panoBackdrop = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(halfSpan * 2 + 0.5, 6.2)),
            panoMat
        );
        panoBackdrop.rotation.y = Math.PI * 0.5;
        panoBackdrop.position.set(-0.78, 3.10, 0);
        this.westOuterGateGroup.add(panoBackdrop);

        const nicheSideGeo = this._trackDisposable(new THREE.BoxGeometry(0.92, 6.4, 0.24));
        [-halfSpan - 0.16, halfSpan + 0.16].forEach((nz) => {
            const ns = new THREE.Mesh(nicheSideGeo, this.mats.limestoneArch);
            ns.position.set(-0.42, 3.2, nz);
            this.westOuterGateGroup.add(ns);
        });
        const nicheCap = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.96, 0.28, halfSpan * 2 + 0.6)),
            this.mats.limestoneArch
        );
        nicheCap.position.set(-0.42, 6.32, 0);
        this.westOuterGateGroup.add(nicheCap);

        const colGeo = this._trackDisposable(new THREE.CylinderGeometry(0.32, 0.36, springY, 20));
        const baseTorusGeo = this._trackDisposable(new THREE.TorusGeometry(0.39, 0.08, 10, 24));
        baseTorusGeo.rotateX(Math.PI * 0.5);
        const capTorusGeo = this._trackDisposable(new THREE.TorusGeometry(0.37, 0.09, 10, 24));
        capTorusGeo.rotateX(Math.PI * 0.5);

        [-halfSpan, halfSpan].forEach((cz) => {
            const col = new THREE.Mesh(colGeo, this.mats.alabasterStep);
            col.position.set(0.08, springY * 0.5, cz);
            this.westOuterGateGroup.add(col);

            const bRing = new THREE.Mesh(baseTorusGeo, this.mats.goldMatcapBright);
            bRing.position.set(0.08, 0.14, cz);
            this.westOuterGateGroup.add(bRing);

            const cRing = new THREE.Mesh(capTorusGeo, this.mats.goldMatcapBright);
            cRing.position.set(0.08, springY, cz);
            this.westOuterGateGroup.add(cRing);
        });

        const outerArch = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(halfSpan, 0.26, 14, 44, Math.PI)),
            this.mats.alabasterStep
        );
        outerArch.rotation.y = Math.PI * 0.5;
        outerArch.position.set(0.08, springY, 0);
        this.westOuterGateGroup.add(outerArch);

        const goldArchivolt = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(halfSpan - 0.16, 0.10, 12, 44, Math.PI)),
            this.mats.goldMatcapBright
        );
        goldArchivolt.rotation.y = Math.PI * 0.5;
        goldArchivolt.position.set(0.16, springY, 0);
        this.westOuterGateGroup.add(goldArchivolt);

        const keystone = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.24, 0.32, 0.34)),
            this.mats.goldMatcapBright
        );
        keystone.position.set(0.14, springY + halfSpan - 0.04, 0);
        this.westOuterGateGroup.add(keystone);

        const fanlightDisc = new THREE.Mesh(
            this._trackDisposable(new THREE.CircleGeometry(halfSpan - 0.20, 32, 0, Math.PI)),
            this.mats.frostedCrystalGlass
        );
        fanlightDisc.rotation.y = Math.PI * 0.5;
        fanlightDisc.position.set(-0.04, springY, 0);
        this.westOuterGateGroup.add(fanlightDisc);

        const spokeGeo = this._trackDisposable(new THREE.CylinderGeometry(0.022, 0.022, halfSpan - 0.24, 8));
        for (let si = 1; si <= 7; si++) {
            const ang = (si / 8) * Math.PI;
            const spoke = new THREE.Mesh(spokeGeo, this.mats.goldBrushed);
            spoke.position.set(
                0.0,
                springY + Math.sin(ang) * (halfSpan - 0.24) * 0.5,
                Math.cos(ang) * (halfSpan - 0.24) * 0.5
            );
            spoke.rotation.x = ang - Math.PI * 0.5;
            this.westOuterGateGroup.add(spoke);
        }

        const transomBar = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.16, 0.14, halfSpan * 2 - 0.2)),
            this.mats.goldMatcapBrushed
        );
        transomBar.position.set(0.02, springY, 0);
        this.westOuterGateGroup.add(transomBar);

        const barGeo = this._trackDisposable(new THREE.CylinderGeometry(0.024, 0.024, springY - 0.16, 8));
        const spearTipGeo = this._trackDisposable(new THREE.ConeGeometry(0.042, 0.16, 6));
        const scrollRingGeo = this._trackDisposable(new THREE.TorusGeometry(0.11, 0.018, 8, 18));
        scrollRingGeo.rotateY(Math.PI * 0.5);

        const barCount = 16;
        for (let bi = 0; bi <= barCount; bi++) {
            const bz = lerp(-halfSpan + 0.24, halfSpan - 0.24, bi / barCount);
            const isCenterStile = Math.abs(bz) < 0.16;
            const bar = new THREE.Mesh(barGeo, isCenterStile ? this.mats.goldMatcapBrushed : this.mats.wroughtIronMatte);
            bar.position.set(0.02, (springY - 0.16) * 0.5 + 0.06, bz);
            this.westOuterGateGroup.add(bar);

            const tip = new THREE.Mesh(spearTipGeo, this.mats.goldMatcapBright);
            tip.position.set(0.02, springY - 0.12, bz);
            this.westOuterGateGroup.add(tip);

            if (bi < barCount && bi % 2 === 0) {
                const midZ = bz + ((halfSpan * 2 - 0.48) / barCount) * 0.5;
                const sRingLow = new THREE.Mesh(scrollRingGeo, this.mats.goldBrushed);
                sRingLow.position.set(0.02, 0.92, midZ);
                const sRingHigh = new THREE.Mesh(scrollRingGeo, this.mats.goldBrushed);
                sRingHigh.position.set(0.02, 2.58, midZ);
                this.westOuterGateGroup.add(sRingLow, sRingHigh);
            }
        }

        [0.14, 1.68, 2.92].forEach((ry) => {
            const rail = new THREE.Mesh(
                this._trackDisposable(new THREE.BoxGeometry(0.09, 0.08, halfSpan * 2 - 0.32)),
                this.mats.wroughtIronMatte
            );
            rail.position.set(0.02, ry, 0);
            this.westOuterGateGroup.add(rail);
        });

        const lockGroup = new THREE.Group();
        lockGroup.position.set(0.16, 1.66, 0);
        this.westOuterGateGroup.add(lockGroup);

        const chainRing = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(0.22, 0.032, 8, 20)),
            this.mats.goldMatcapBrushed
        );
        chainRing.rotation.y = Math.PI * 0.35;
        chainRing.position.set(-0.06, 0.12, 0);
        lockGroup.add(chainRing);

        const padlockBody = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.14, 0.34, 0.28)),
            this.mats.goldMatcapBright
        );
        padlockBody.position.set(0.02, -0.06, 0);
        lockGroup.add(padlockBody);

        const padlockShackle = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(0.095, 0.028, 10, 22, Math.PI)),
            this.mats.goldChampagne
        );
        padlockShackle.rotation.y = Math.PI * 0.5;
        padlockShackle.position.set(0.02, 0.11, 0);
        lockGroup.add(padlockShackle);

        const keyhole = new THREE.Mesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.028, 0.018, 0.16, 10)),
            this.mats.neroPedestal
        );
        keyhole.rotation.z = Math.PI * 0.5;
        keyhole.position.set(0.025, -0.06, 0);
        lockGroup.add(keyhole);

        const spillCanvas = document.createElement('canvas');
        spillCanvas.width = 256;
        spillCanvas.height = 256;
        const sCtx = spillCanvas.getContext('2d');
        const spillGrad = sCtx.createRadialGradient(32, 128, 12, 110, 128, 140);
        spillGrad.addColorStop(0.0, 'rgba(255, 248, 220, 0.52)');
        spillGrad.addColorStop(0.45, 'rgba(230, 198, 135, 0.22)');
        spillGrad.addColorStop(1.0, 'rgba(255, 248, 220, 0.0)');
        sCtx.fillStyle = spillGrad;
        sCtx.fillRect(0, 0, 256, 256);
        const spillTex = this._trackDisposable(new THREE.CanvasTexture(spillCanvas));
        applySrgbColorSpace(THREE, spillTex);
        const spillMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: spillTex,
            transparent: true,
            depthWrite: false,
            polygonOffset: true,
            polygonOffsetFactor: -1,
            polygonOffsetUnits: -2,
        }));
        const spillPlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(4.6, 5.2)),
            spillMat
        );
        spillPlane.rotation.x = -Math.PI * 0.5;
        spillPlane.position.set(2.35, 0.007, 0);
        this.westOuterGateGroup.add(spillPlane);

        const outerGateFriezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'Tor zur Außenwelt',
            'Bald warten hier fantastische Welten & Abenteuer',
            this.disposables
        );
        const outerGateFriezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: outerGateFriezeTex,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2,
            depthWrite: true,
        }));
        const outerFriezeY = springY + halfSpan + 0.68;
        const outerFriezeBack = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.22, 1.06, 5.2)),
            this.mats.goldMatcapBright
        );
        outerFriezeBack.position.set(0.24, outerFriezeY, 0);
        this.westOuterGateGroup.add(outerFriezeBack);

        const outerFriezePlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(4.96, 0.92)),
            outerGateFriezeMat
        );
        outerFriezePlane.rotation.y = Math.PI * 0.5;
        outerFriezePlane.position.set(0.24 + 0.11 + 0.028, outerFriezeY, 0);
        this.westOuterGateGroup.add(outerFriezePlane);

        const gateHitProxy = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.85, 5.6, halfSpan * 2)),
            this._trackDisposable(new THREE.MeshBasicMaterial({ visible: false }))
        );
        gateHitProxy.position.set(0.15, 2.8, 0);
        gateHitProxy.userData = { isWestOuterGate: true };
        this.westOuterGateGroup.add(gateHitProxy);
        this.westGateHitMeshes.push(gateHitProxy);
    }

    _normalizeAtelierReview(raw) {
        const src = (raw && typeof raw === 'object') ? raw : {};
        const myRating = clamp(parseInt(src.my_rating ?? src.myRating ?? 0, 10) || 0, 0, 5);
        return {
            area: String(src.area || 'funkenreich'),
            label: sanitizeDisplayText(src.label || '3D-Atelier'),
            can_rate: Boolean(src.can_rate ?? src.canRate ?? false),
            has_rated: Boolean(src.has_rated ?? src.hasRated ?? (myRating > 0)),
            my_rating: myRating,
            my_comment: typeof (src.my_comment ?? src.myComment) === 'string' ? String(src.my_comment ?? src.myComment) : '',
            submit_url: String(src.submit_url || src.submitUrl || '/customer/experience-reviews'),
        };
    }

    getAtelierReviewState() {
        return Object.assign({}, this.atelierReview || this._normalizeAtelierReview(null));
    }

    setAtelierReviewState(nextReview, opts = null) {
        this.atelierReview = this._normalizeAtelierReview(Object.assign({}, this.atelierReview || {}, nextReview || {}));
        this._syncAtelierStarMaterials();
        if (opts && opts.celebrate) {
            const stars = clamp(parseInt(opts.stars ?? this.atelierReview.my_rating ?? 5, 10) || 5, 1, 5);
            this._triggerAtelierStarCelebration(stars);
        }
        if (this.onAtelierStarStationFocusChange && this.atelierStarStationFocused) {
            this.onAtelierStarStationFocusChange(true, this.getAtelierReviewState());
        }
        return this.getAtelierReviewState();
    }

    previewAtelierStarHover(hoverStars = 0) {
        const nextHover = clamp(parseInt(hoverStars, 10) || 0, 0, 5);
        if (this.atelierStarHoverPreview === nextHover) return;
        this.atelierStarHoverPreview = nextHover;
        this._syncAtelierStarMaterials();
    }

    _syncAtelierStarMaterials() {
        const st = this.atelierStarStation;
        if (!st || !st.stars || !this.mats) return;
        const preview = this.atelierStarHoverPreview || 0;
        const saved = (this.atelierReview && this.atelierReview.my_rating) ? this.atelierReview.my_rating : 0;
        const activeCount = preview > 0 ? preview : saved;

        for (let i = 0; i < st.stars.length; i++) {
            const item = st.stars[i];
            if (!item || !item.mesh) continue;
            if (activeCount > 0) {
                item.isLit = (i < activeCount);
                item.mesh.material = item.isLit ? this.mats.goldMatcapBright : st.dimStarMat;
            } else {
                item.isLit = false;
                item.mesh.material = this.mats.goldChampagne;
            }
        }
    }

    /**
     * Edle 3D-Sternebewertungs-Station rechts neben dem goldenen Ehrenportal zur Halle der Helden (Schwarz-Gold #C5A059, 60 FPS).
     */
    _buildAtelierStarRatingStation() {
        if (!this.galleryGroup || !this.THREE || !this.mats) return;
        const THREE = this.THREE;
        const stX = 6.85;
        const stZ = this.hallNorthZ + 1.95;
        const stRotY = -0.26;

        const group = new THREE.Group();
        group.position.set(stX, 0, stZ);
        group.rotation.y = stRotY;
        this.galleryGroup.add(group);

        const dimStarMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            color: 0x6f5b38,
            roughness: 0.56,
            metalness: 0.42,
            envMapIntensity: 0.62,
        }));

        // Kontakt-Schatten & goldener Boden-Fokusring
        const shadowDisc = new THREE.Mesh(
            this._trackDisposable(new THREE.CircleGeometry(1.06, 32)),
            this.mats.contactShadow
        );
        shadowDisc.rotation.x = -Math.PI * 0.5;
        shadowDisc.position.y = 0.004;
        group.add(shadowDisc);

        const floorRing = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.79, 0.87, 48)),
            this.mats.goldMatcapBright
        );
        floorRing.rotation.x = -Math.PI * 0.5;
        floorRing.position.y = 0.009;
        group.add(floorRing);

        const floorInnerRing = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.68, 0.71, 44)),
            this.mats.goldBrushed
        );
        floorInnerRing.rotation.x = -Math.PI * 0.5;
        floorInnerRing.position.y = 0.008;
        group.add(floorInnerRing);

        // 24K-Gold-Sockel + Nero-Marquina-Marmorpult + kannelierter Säulenschaft
        const goldBase = new THREE.Mesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.56, 0.62, 0.09, 8)),
            this.mats.goldMatcapBright
        );
        goldBase.position.y = 0.045;
        group.add(goldBase);

        const neroPlinth = new THREE.Mesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.44, 0.50, 0.22, 8)),
            this.mats.neroPedestal
        );
        neroPlinth.position.y = 0.20;
        group.add(neroPlinth);

        const baseTorus = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(0.43, 0.038, 10, 28)),
            this.mats.goldMatcapBright
        );
        baseTorus.rotation.x = Math.PI * 0.5;
        baseTorus.position.y = 0.31;
        group.add(baseTorus);

        const shaft = new THREE.Mesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.20, 0.25, 0.88, 18)),
            this.mats.neroPedestal
        );
        shaft.position.y = 0.75;
        group.add(shaft);

        const fluteGeo = this._trackDisposable(new THREE.BoxGeometry(0.022, 0.82, 0.022));
        for (let fi = 0; fi < 4; fi++) {
            const fang = (fi / 4) * TWO_PI + Math.PI * 0.25;
            const flute = new THREE.Mesh(fluteGeo, this.mats.goldBrushed);
            flute.position.set(Math.cos(fang) * 0.225, 0.75, Math.sin(fang) * 0.225);
            flute.rotation.y = -fang;
            group.add(flute);
        }

        const capital = new THREE.Mesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.34, 0.22, 0.12, 18)),
            this.mats.goldMatcapBright
        );
        capital.position.y = 1.24;
        group.add(capital);

        // Geneigte Gold-Schrifttafel ("Atelier bewerten" / "Deine Meinung zählt")
        const deskPivot = new THREE.Group();
        deskPivot.position.set(0, 1.33, 0.02);
        deskPivot.rotation.x = 0.48;
        group.add(deskPivot);

        const deskGoldFrame = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.92, 0.052, 0.60)),
            this.mats.goldMatcapBright
        );
        deskPivot.add(deskGoldFrame);

        const deskMarble = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.86, 0.064, 0.54)),
            this.mats.neroPedestal
        );
        deskMarble.position.y = 0.012;
        deskPivot.add(deskMarble);

        const deskLip = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.92, 0.044, 0.036)),
            this.mats.goldMatcapBright
        );
        deskLip.position.set(0, 0.042, 0.282);
        deskPivot.add(deskLip);

        const plaqueCanvas = document.createElement('canvas');
        plaqueCanvas.width = 512;
        plaqueCanvas.height = 320;
        const pCtx = plaqueCanvas.getContext('2d');
        const pBg = pCtx.createLinearGradient(0, 0, 0, 320);
        pBg.addColorStop(0, '#101218');
        pBg.addColorStop(0.5, '#090b10');
        pBg.addColorStop(1, '#101218');
        pCtx.fillStyle = pBg;
        pCtx.fillRect(0, 0, 512, 320);
        pCtx.strokeStyle = '#C5A059';
        pCtx.lineWidth = 5;
        pCtx.strokeRect(12, 12, 488, 296);
        pCtx.strokeStyle = 'rgba(230, 198, 135, 0.42)';
        pCtx.lineWidth = 1.5;
        pCtx.strokeRect(22, 22, 468, 276);
        pCtx.textAlign = 'center';
        pCtx.textBaseline = 'middle';
        pCtx.fillStyle = '#E6C687';
        pCtx.font = '700 32px Georgia, "Times New Roman", serif';
        pCtx.fillText('★  ★  ★  ★  ★', 256, 78);
        const pGold = pCtx.createLinearGradient(0, 115, 0, 195);
        pGold.addColorStop(0, '#FFF8E1');
        pGold.addColorStop(0.5, '#E6C687');
        pGold.addColorStop(1, '#C5A059');
        pCtx.fillStyle = pGold;
        pCtx.font = '700 42px Georgia, "Times New Roman", serif';
        pCtx.fillText('Atelier bewerten', 256, 162, 440);
        pCtx.fillStyle = '#E6C687';
        pCtx.font = '600 22px system-ui, -apple-system, "Segoe UI", sans-serif';
        if ('letterSpacing' in pCtx) pCtx.letterSpacing = '3px';
        pCtx.fillText('Deine Meinung zählt', 256, 236, 440);

        const plaqueTex = this._trackDisposable(new THREE.CanvasTexture(plaqueCanvas));
        plaqueTex.anisotropy = Math.min(this.maxAniso || 4, 8);
        applySrgbColorSpace(THREE, plaqueTex);
        const plaqueMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: plaqueTex,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2,
        }));
        const plaqueMesh = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(0.80, 0.48)),
            plaqueMat
        );
        plaqueMesh.rotation.x = -Math.PI * 0.5;
        plaqueMesh.position.y = 0.048;
        deskPivot.add(plaqueMesh);

        // Feiner rotierender Doppel-Gold-Halo hinter dem Sternenbogen
        const haloAnchor = new THREE.Group();
        haloAnchor.position.set(0, 2.26, -0.06);
        group.add(haloAnchor);

        const haloOuter = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(0.88, 0.013, 10, 56)),
            this.mats.goldChampagne
        );
        haloAnchor.add(haloOuter);

        const haloInner = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(0.72, 0.009, 8, 48)),
            this.mats.goldMatcapBright
        );
        haloInner.rotation.y = 0.32;
        haloAnchor.add(haloInner);

        // 5 plastische, fuenfzackige 3D-Sterne (echte 5-Zacken-Sternform via ExtrudeGeometry mit feiner Fase/Bevel!)
        const starShape = new THREE.Shape();
        const outerR = 0.148;
        const innerR = 0.062;
        for (let pt = 0; pt < 10; pt++) {
            const rad = (pt % 2 === 0) ? outerR : innerR;
            const ang = (pt * Math.PI) / 5 + Math.PI * 0.5;
            const sx = Math.cos(ang) * rad;
            const sy = Math.sin(ang) * rad;
            if (pt === 0) starShape.moveTo(sx, sy);
            else starShape.lineTo(sx, sy);
        }
        starShape.closePath();

        const starGeo = this._trackDisposable(new THREE.ExtrudeGeometry(starShape, {
            depth: 0.042,
            bevelEnabled: true,
            bevelSegments: 3,
            steps: 1,
            bevelSize: 0.013,
            bevelThickness: 0.015,
        }));
        starGeo.center();

        const stars = [];
        for (let si = 0; si < 5; si++) {
            const u = (si - 2) * 0.5; // -1.0, -0.5, 0.0, +0.5, +1.0
            const baseX = u * 0.68;
            const baseY = 2.40 - (u * u) * 0.23; // 2.17 .. 2.40 m harmonischer Bogen
            const baseZ = -(u * u) * 0.09;

            const starMesh = new THREE.Mesh(starGeo, this.mats.goldChampagne);
            starMesh.position.set(baseX, baseY, baseZ);
            group.add(starMesh);

            stars.push({
                index: si + 1,
                mesh: starMesh,
                baseX,
                baseY,
                baseZ,
                baseTiltZ: -u * 0.16,
                isLit: false,
            });
        }

        // Aufsteigende goldene Seelenfunken-Partikel rund um Pult & Sternenbogen
        const sparkCount = 26;
        const sparkPositions = new Float32Array(sparkCount * 3);
        const sparkColors = new Float32Array(sparkCount * 3);
        const sparkMeta = [];
        const rng = mulberry32(20261118);

        for (let k = 0; k < sparkCount; k++) {
            const ph = rng();
            const rad = 0.28 + rng() * 0.58;
            const hSpan = 1.35 + rng() * 0.55;
            const spd = 0.18 + rng() * 0.16;
            const sw = (0.75 + rng() * 0.85) * (k % 2 === 0 ? 1 : -1);
            const startY = 1.18 + rng() * 0.22;
            sparkMeta.push({ rad, hSpan, spd, ph, sw, startY });

            sparkPositions[k * 3] = Math.cos(ph * TWO_PI) * rad;
            sparkPositions[k * 3 + 1] = startY + ph * hSpan;
            sparkPositions[k * 3 + 2] = Math.sin(ph * TWO_PI) * rad * 0.65;

            const bright = (k % 3 === 0);
            sparkColors[k * 3] = 1.0;
            sparkColors[k * 3 + 1] = bright ? 0.96 : 0.85;
            sparkColors[k * 3 + 2] = bright ? 0.80 : 0.42;
        }

        const sparkGeo = this._trackDisposable(new THREE.BufferGeometry());
        const sparkPosAttr = new THREE.BufferAttribute(sparkPositions, 3);
        sparkGeo.setAttribute('position', sparkPosAttr);
        sparkGeo.setAttribute('color', new THREE.BufferAttribute(sparkColors, 3));
        const sparkMat = this._trackDisposable(new THREE.PointsMaterial({
            size: 0.16,
            map: this.sparkSpriteTex || null,
            vertexColors: true,
            transparent: true,
            opacity: 0.86,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
        }));
        const sparkPoints = new THREE.Points(sparkGeo, sparkMat);
        sparkPoints.frustumCulled = false;
        group.add(sparkPoints);

        // Klick-Proxy fuer automatisches Hinlaufen aus der Distanz
        const hitProxy = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(1.65, 2.65, 1.35)),
            this._trackDisposable(new THREE.MeshBasicMaterial({ visible: false }))
        );
        hitProxy.position.set(0, 1.32, 0.05);
        hitProxy.userData = { isAtelierStarStation: true };
        group.add(hitProxy);
        this.interactiveHitMeshes.push(hitProxy);

        const standDist = 1.92;
        const standX = stX + Math.sin(stRotY) * standDist;
        const standZ = stZ + Math.cos(stRotY) * standDist;

        this.atelierStarStation = {
            group,
            x: stX,
            z: stZ,
            rotY: stRotY,
            radius: 0.78,
            standX,
            standZ,
            stars,
            dimStarMat,
            haloOuter,
            haloInner,
            floorRing,
            sparkPosAttr,
            sparkMeta,
            celebTimer: 0,
            celebDuration: 1.35,
            celebStarsCount: 0,
        };

        this._syncAtelierStarMaterials();
    }

    _triggerAtelierStarCelebration(starsCount = 5) {
        const st = this.atelierStarStation;
        if (!st) return;
        const count = clamp(parseInt(starsCount, 10) || 5, 1, 5);
        st.celebStarsCount = count;
        st.celebTimer = st.celebDuration;

        this._spawnDoubleJumpBurst(st.x, 2.24, st.z);
        if (this.audio) {
            if (typeof this.audio.playSparkCollect === 'function') {
                this.audio.playSparkCollect(Math.max(0, count - 1));
            }
            if (count >= 4 && typeof this.audio.playChestOpenFanfare === 'function') {
                this.audio.playChestOpenFanfare();
            }
        }
    }

    _updateAtelierStarRatingStation(dt) {
        const st = this.atelierStarStation;
        if (!st || !st.group || !st.group.visible) return;
        const t = this.elapsedTime;

        let celebProgress = -1;
        if (st.celebTimer > 0) {
            st.celebTimer = Math.max(0, st.celebTimer - dt);
            celebProgress = 1.0 - (st.celebTimer / Math.max(0.1, st.celebDuration));
        }

        const speedBoost = celebProgress >= 0 ? 2.4 : 1.0;
        if (st.haloOuter) {
            st.haloOuter.rotation.z = t * 0.42 * speedBoost;
        }
        if (st.haloInner) {
            st.haloInner.rotation.z = -t * 0.68 * speedBoost;
            st.haloInner.rotation.y = Math.sin(t * 0.9) * 0.28;
        }
        if (st.floorRing) {
            const ringPulse = this.atelierStarStationFocused
                ? (1.03 + Math.sin(t * 3.4) * 0.025)
                : 1.0;
            st.floorRing.scale.set(ringPulse, ringPulse, 1);
        }

        for (let i = 0; i < st.stars.length; i++) {
            const item = st.stars[i];
            const wave = Math.sin(t * 2.35 - i * 0.58);
            const gentleYaw = Math.sin(t * 1.45 + i * 0.72) * 0.28;
            let hopY = 0;
            let spinY = 0;
            let celebScale = item.isLit ? 1.08 : 1.0;

            if (celebProgress >= 0 && i < st.celebStarsCount) {
                const stagger = i * 0.08;
                const localP = clamp((celebProgress - stagger) / Math.max(0.25, 1.0 - stagger * 0.8), 0, 1);
                const easeOut = 1.0 - Math.pow(1.0 - localP, 3);
                spinY = easeOut * TWO_PI;
                hopY = Math.sin(localP * Math.PI) * 0.24;
                celebScale += Math.sin(localP * Math.PI) * 0.18;
            }

            item.mesh.position.set(
                item.baseX,
                item.baseY + wave * 0.048 + hopY,
                item.baseZ
            );
            item.mesh.rotation.set(
                Math.sin(t * 1.8 + i) * 0.06,
                gentleYaw + spinY,
                item.baseTiltZ + Math.cos(t * 1.6 + i * 0.5) * 0.04
            );
            item.mesh.scale.setScalar(celebScale);
        }

        if (st.sparkPosAttr && st.sparkMeta) {
            const posArr = st.sparkPosAttr.array;
            const fountainMult = celebProgress >= 0 ? (1.0 + Math.sin(celebProgress * Math.PI) * 1.45) : 1.0;
            for (let k = 0; k < st.sparkMeta.length; k++) {
                const sm = st.sparkMeta[k];
                const u = ((sm.ph + t * sm.spd * fountainMult) % 1 + 1) % 1;
                const ang = t * sm.sw * fountainMult + sm.ph * TWO_PI;
                const rNow = sm.rad * (0.72 + 0.32 * Math.sin(u * Math.PI));
                const k3 = k * 3;
                posArr[k3] = Math.cos(ang) * rNow;
                posArr[k3 + 1] = sm.startY + u * sm.hSpan * fountainMult;
                posArr[k3 + 2] = Math.sin(ang) * rNow * 0.65;
            }
            st.sparkPosAttr.needsUpdate = true;
        }
    }

    goToAtelierStarStation() {
        const st = this.atelierStarStation;
        if (!st) return;
        const keepFp = Boolean(this.running && this.camControl.desiredDistance < 0.55);
        if (this.currentZone !== 'gallery') {
            this.goToZone('gallery', true);
        }
        this.pinnedSteleIndex = -1;
        this.pinnedHeroSlot = -1;
        this.pinnedPortalGate = false;
        this._setFocusedSteleIndex(-1);

        this.player.autoTarget = { x: st.standX, z: st.standZ };
        this.player.autoLookAt = { x: st.x, z: st.z };
        this.camControl.desiredYaw = Math.atan2(st.standX - st.x, st.standZ - st.z);
        this.camControl.desiredPitch = keepFp ? 0.06 : 0.20;
        this.camControl.desiredDistance = keepFp ? 0 : 3.95;

        if (this.walkTargetRing) {
            this.walkTargetRing.position.set(st.standX, 0.015, st.standZ);
            this.walkTargetTimer = 1.25;
        }
        if (this.audio) this.audio.playUiClick();
    }

    /**
     * Monumentales 24K-Gold-Ehrenportal + Calacatta-Gold-Ehrenpromenade zwischen Produktgalerie und Halle der Helden.
     */
    _buildGoldenHonorPortalAndPromenade() {
        const THREE = this.THREE;
        const promCenterZ = (this.hallNorthZ + this.heroesEntryZ) * 0.5;
        const promW = this.portalHalfW * 2 + 1.2;

        const portalPillarGeo = this._trackDisposable(new THREE.CylinderGeometry(0.42, 0.48, 5.65, 24));
        const portalTorusBaseGeo = this._trackDisposable(new THREE.TorusGeometry(0.54, 0.10, 10, 28));
        portalTorusBaseGeo.rotateX(Math.PI * 0.5);
        const portalBaseGeo = this._trackDisposable(new THREE.CylinderGeometry(0.62, 0.68, 0.28, 24));
        const portalCapGeo = this._trackDisposable(new THREE.CylinderGeometry(0.66, 0.52, 0.36, 24));

        [-this.portalHalfW, this.portalHalfW].forEach((px) => {
            const pillar = new THREE.Mesh(portalPillarGeo, this.mats.goldMatcapBright);
            pillar.position.set(px, 2.825, this.hallNorthZ);
            this.galleryGroup.add(pillar);

            const base = new THREE.Mesh(portalBaseGeo, this.mats.neroPedestal);
            base.position.set(px, 0.14, this.hallNorthZ);
            this.galleryGroup.add(base);

            const tBase = new THREE.Mesh(portalTorusBaseGeo, this.mats.goldMatcapBright);
            tBase.position.set(px, 0.30, this.hallNorthZ);
            this.galleryGroup.add(tBase);

            const cap = new THREE.Mesh(portalCapGeo, this.mats.goldMatcapBright);
            cap.position.set(px, 5.56, this.hallNorthZ);
            this.galleryGroup.add(cap);
        });

        const lintelGold = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(this.portalHalfW * 2 + 1.4, 0.34, 1.28)),
            this.mats.goldMatcapBright
        );
        lintelGold.position.set(0, 5.62, this.hallNorthZ);
        this.galleryGroup.add(lintelGold);

        const promSpanZ = Math.max(2.0, (this.hallNorthZ - 2.02) - (this.heroesEntryZ + 0.05));
        const promNonOverlapZ = ((this.hallNorthZ - 2.02) + (this.heroesEntryZ + 0.05)) * 0.5;
        const promFloor = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(promW, promSpanZ)),
            this.mats.promenadeFloor
        );
        promFloor.rotation.x = -Math.PI * 0.5;
        promFloor.position.set(0, 0.004, promNonOverlapZ);
        promFloor.userData = { isFloor: true };
        this.galleryGroup.add(promFloor);
        this.walkableFloorMeshes.push(promFloor);

        const carpetSouthZ = this.hallNorthZ + 1.65;
        const carpetNorthZ = this.pantheonCenterZ + 18.65;
        const carpetLen = Math.max(4.0, carpetSouthZ - carpetNorthZ);
        const carpetCenterZ = (carpetSouthZ + carpetNorthZ) * 0.5;
        this.mats.honorCarpetVelvet = this._trackDisposable(new THREE.MeshStandardMaterial({
            color: 0x133b5c,
            roughness: 0.72,
            metalness: 0.04,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -4,
        }));
        const carpetStrip = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(2.6, 0.014, carpetLen)),
            this.mats.honorCarpetVelvet
        );
        carpetStrip.position.set(0, 0.029, carpetCenterZ);
        carpetStrip.renderOrder = 3;
        this.galleryGroup.add(carpetStrip);

        const carpetSideGoldGeo = this._trackDisposable(new THREE.BoxGeometry(0.11, 0.018, carpetLen));
        [-1.34, 1.34].forEach((gx) => {
            const gLine = new THREE.Mesh(carpetSideGoldGeo, this.mats.goldMatcapBright);
            gLine.position.set(gx, 0.029, carpetCenterZ);
            gLine.renderOrder = 3;
            this.galleryGroup.add(gLine);
        });

        const carpetEndGoldGeo = this._trackDisposable(new THREE.BoxGeometry(2.82, 0.018, 0.12));
        [carpetSouthZ, carpetNorthZ].forEach((ez) => {
            const endBar = new THREE.Mesh(carpetEndGoldGeo, this.mats.goldMatcapBright);
            endBar.position.set(0, 0.029, ez);
            endBar.renderOrder = 3;
            this.galleryGroup.add(endBar);
        });

        const promOuterGoldGeo = this._trackDisposable(new THREE.BoxGeometry(0.11, 0.012, this.promenadeLength + 3.2));
        [-this.portalHalfW + 0.12, this.portalHalfW - 0.12].forEach((gx) => {
            const gOuter = new THREE.Mesh(promOuterGoldGeo, this.mats.goldMatcapBright);
            gOuter.position.set(gx, 0.022, promCenterZ);
            gOuter.renderOrder = 2;
            this.galleryGroup.add(gOuter);
        });

        const balustradeGeo = this._trackDisposable(new THREE.BoxGeometry(0.36, 1.02, this.promenadeLength));
        const balustradeCapGeo = this._trackDisposable(new THREE.CylinderGeometry(0.24, 0.24, this.promenadeLength, 14));
        balustradeCapGeo.rotateX(Math.PI * 0.5);
        const promWallGeo = this._trackDisposable(createBoiserieWallPlaneGeo(THREE, this.promenadeLength + 0.6, 6.2, 7.8));
        [-1, 1].forEach((sideSign) => {
            const bx = sideSign * (this.portalHalfW + 0.35);
            const bal = new THREE.Mesh(balustradeGeo, this.mats.neroPedestal);
            bal.position.set(bx, 0.51, promCenterZ);
            this.galleryGroup.add(bal);

            const bCap = new THREE.Mesh(balustradeCapGeo, this.mats.goldMatcapBright);
            bCap.position.set(bx, 1.04, promCenterZ);
            this.galleryGroup.add(bCap);

            const outerWall = new THREE.Mesh(promWallGeo, this.mats.boiserieWall);
            outerWall.position.set(sideSign * (this.portalHalfW + 1.45), 3.1, promCenterZ);
            outerWall.rotation.y = -sideSign * Math.PI * 0.5;
            this.galleryGroup.add(outerWall);
        });

        const promColGeo = this._trackDisposable(new THREE.CylinderGeometry(0.32, 0.38, 5.5, 20));
        const promArchGeo = this._trackDisposable(new THREE.TorusGeometry(this.portalHalfW + 0.35, 0.15, 10, 32, Math.PI));
        for (let s = 0; s < 3; s++) {
            const sz = lerp(this.hallNorthZ - 2.2, this.heroesEntryZ + 2.2, s / 2);
            [-this.portalHalfW - 0.35, this.portalHalfW + 0.35].forEach((cx) => {
                const col = new THREE.Mesh(promColGeo, this.mats.goldMatcapBrushed);
                col.position.set(cx, 2.75, sz);
                this.galleryGroup.add(col);
            });
            const arch = new THREE.Mesh(promArchGeo, this.mats.goldMatcapBright);
            arch.position.set(0, 5.5, sz);
            this.galleryGroup.add(arch);
        }

        this.portalBarrierGroup = new THREE.Group();
        this.portalBarrierGroup.position.set(0, 0, this.hallNorthZ - 0.28);
        this.portalBarrierGroup.visible = !this.canEnterSanctums;

        const hitProxy = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(this.portalHalfW * 2, 5.4, 0.8)),
            this._trackDisposable(new THREE.MeshBasicMaterial({ visible: false }))
        );
        hitProxy.position.y = 2.7;
        hitProxy.userData = { isPortalGate: true };
        this.portalBarrierGroup.add(hitProxy);
        this.portalHitMeshes.push(hitProxy);

        this.galleryGroup.add(this.portalBarrierGroup);
    }

    /**
     * Bereich 2 (Nord-Rotunde, zone = 'heroes'):
     * Fotorealistische roemische Pantheon "Halle der Helden" mit Opus-Sectile-Marmorboden, 24K-Gold-Kolonnaden & Travertin-Kassettenkuppel!
     */
    _buildHallOfHeroes() {
        const THREE = this.THREE;
        this.pantheonGroup = new THREE.Group();
        this.pantheonGroup.position.set(0, 0, this.pantheonCenterZ);
        this.scene.add(this.pantheonGroup);

        const floorTopY = 0.02;

        // 1. Abgestuftes Pantheon-Stylobat (Nero Marquina, Saphir #133B5C, Alabaster #ECEAE4 & 24K-Gold-Rahmen mit >= 4cm Stufentrennung!)
        const steps = [
            { w: 53.0, d: 55.0, h: 0.90, y: -0.66, trimY: -0.32, mat: this.mats.neroPedestal, trimMat: this.mats.goldMatcapBright },
            { w: 50.0, d: 52.0, h: 0.42, y: -0.28, trimY: -0.18, mat: this.mats.sapphireTrim, trimMat: this.mats.goldMatcapBright },
            { w: 47.6, d: 49.6, h: 0.24, y: -0.14, trimY: -0.13, mat: this.mats.alabasterStep, trimMat: this.mats.sapphireTrim },
        ];
        for (let si = 0; si < steps.length; si++) {
            const s = steps[si];
            const sm = new THREE.Mesh(this._trackDisposable(new THREE.BoxGeometry(s.w, s.h, s.d)), s.mat);
            sm.position.set(0, s.y, 0);
            this.pantheonGroup.add(sm);

            const trim = new THREE.Mesh(this._trackDisposable(new THREE.BoxGeometry(s.w + 0.8, 0.14, s.d + 0.8)), s.trimMat);
            trim.position.set(0, s.trimY, 0);
            this.pantheonGroup.add(trim);
        }

        // 2. Massiver Calacatta-Gold-Marmorboden (~3.8m Platten, BoxGeometry 46.8 x 0.28 x 48.8, Oberkante exakt 0.020 ohne polygonOffset!)
        const fallbackMarbleMaps = buildHallOfHeroesMarbleMaps(THREE, this.maxAniso, this.disposables);
        this.mats.pantheonFloor = this._trackDisposable(new THREE.MeshStandardMaterial({
            color: 0xffffff,
            map: fallbackMarbleMaps.map,
            bumpMap: fallbackMarbleMaps.map,
            bumpScale: 0.006,
            roughness: 0.16,
            metalness: 0.04,
            envMapIntensity: 0.88,
            polygonOffset: false,
        }));
        const marbleFloor = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(46.8, 0.28, 48.8)),
            this.mats.pantheonFloor
        );
        marbleFloor.position.set(0, floorTopY - 0.14, 0);
        marbleFloor.userData = { isFloor: true, isHeroesFloor: true, surfaceY: floorTopY };
        this.pantheonGroup.add(marbleFloor);
        this.walkableFloorMeshes.push(marbleFloor);

        const borderY = 0.023;
        const inlayGoldY = 0.026;
        const westEastBorderGeo = this._trackDisposable(new THREE.PlaneGeometry(4.4, 46.8));
        [-21.1, 21.1].forEach((bx) => {
            const bMesh = new THREE.Mesh(westEastBorderGeo, this.mats.pantheonNeroBorder);
            bMesh.rotation.x = -Math.PI * 0.5;
            bMesh.position.set(bx, borderY, 0);
            this.pantheonGroup.add(bMesh);
        });
        const northBorderMesh = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(37.8, 4.4)),
            this.mats.pantheonNeroBorder
        );
        northBorderMesh.rotation.x = -Math.PI * 0.5;
        northBorderMesh.position.set(0, borderY, -21.1);
        this.pantheonGroup.add(northBorderMesh);

        const southBorderSegW = (37.8 - (this.portalHalfW * 2 + 1.2)) * 0.5;
        const southBorderSegGeo = this._trackDisposable(new THREE.PlaneGeometry(southBorderSegW, 4.4));
        [-1, 1].forEach((sSign) => {
            const sb = new THREE.Mesh(southBorderSegGeo, this.mats.pantheonNeroBorder);
            sb.rotation.x = -Math.PI * 0.5;
            sb.position.set(sSign * (18.9 - southBorderSegW * 0.5), borderY, 21.1);
            this.pantheonGroup.add(sb);
        });

        const innerFrameSideGeo = this._trackDisposable(new THREE.PlaneGeometry(0.11, 37.9));
        [-18.9, 18.9].forEach((gx) => {
            const gl = new THREE.Mesh(innerFrameSideGeo, this.mats.goldMatcapBrushed);
            gl.rotation.x = -Math.PI * 0.5;
            gl.position.set(gx, inlayGoldY, 0);
            this.pantheonGroup.add(gl);
        });
        const innerFrameNorth = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(37.9, 0.11)),
            this.mats.goldMatcapBrushed
        );
        innerFrameNorth.rotation.x = -Math.PI * 0.5;
        innerFrameNorth.position.set(0, inlayGoldY, -18.9);
        this.pantheonGroup.add(innerFrameNorth);

        [4.2, 8.6, 13.4, 18.2].forEach((rad, rIdx) => {
            const gRing = new THREE.Mesh(
                this._trackDisposable(new THREE.RingGeometry(rad, rad + (rIdx === 2 ? 0.15 : 0.10), 72)),
                rIdx % 2 === 0 ? this.mats.goldMatcapBrushed : this.mats.goldMatcapBright
            );
            gRing.rotation.x = -Math.PI * 0.5;
            gRing.position.set(0, inlayGoldY, 0);
            this.pantheonGroup.add(gRing);
        });

        const colHeight = 12.6;
        const rotundaWallH = colHeight + 0.55;
        const sideRotundaBoiserieGeo = this._trackDisposable(createBoiserieWallPlaneGeo(THREE, 46.8, rotundaWallH, 9.4));
        const northRotundaBoiserieGeo = this._trackDisposable(createBoiserieWallPlaneGeo(THREE, 46.8, rotundaWallH, 9.4));
        [-1, 1].forEach((sideSign) => {
            const sidePlane = new THREE.Mesh(sideRotundaBoiserieGeo, this.mats.boiserieWall);
            sidePlane.position.set(sideSign * 23.35, floorTopY + rotundaWallH * 0.5, 0);
            sidePlane.rotation.y = -sideSign * Math.PI * 0.5;
            this.pantheonGroup.add(sidePlane);
        });

        const northPlane = new THREE.Mesh(northRotundaBoiserieGeo, this.mats.boiserieWall);
        northPlane.position.set(0, floorTopY + rotundaWallH * 0.5, -23.35);
        this.pantheonGroup.add(northPlane);

        const southWingW = (46.8 - (this.portalHalfW * 2 + 2.8)) * 0.5;
        const southWingBoiserieGeo = this._trackDisposable(createBoiserieWallPlaneGeo(THREE, southWingW, rotundaWallH, 9.4));
        [-1, 1].forEach((sideSign) => {
            const swPlane = new THREE.Mesh(southWingBoiserieGeo, this.mats.boiserieWall);
            swPlane.position.set(sideSign * (23.4 - southWingW * 0.5), floorTopY + rotundaWallH * 0.5, 23.35);
            swPlane.rotation.y = Math.PI;
            this.pantheonGroup.add(swPlane);
        });

        const colCoords = [];
        for (let ci = 0; ci < 8; ci++) {
            const cz = lerp(-21.5, 21.5, ci / 7);
            colCoords.push([-21.5, cz], [21.5, cz]);
        }
        const porticoXs = [-15.2, -9.2, 9.2, 15.2];
        for (let pxi = 0; pxi < porticoXs.length; pxi++) {
            colCoords.push([porticoXs[pxi], 21.5], [porticoXs[pxi], -21.5]);
        }
        colCoords.push([-14.8, -14.8], [14.8, -14.8], [-14.8, 14.8], [14.8, 14.8]);

        const shaftInst = new THREE.InstancedMesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.68, 0.80, colHeight, 24)),
            this.mats.goldMatcapBrushed,
            colCoords.length
        );
        const capInst = new THREE.InstancedMesh(
            this._trackDisposable(new THREE.CylinderGeometry(1.12, 0.78, 0.82, 24)),
            this.mats.goldMatcapBright,
            colCoords.length
        );
        const baseInst = new THREE.InstancedMesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.94, 1.08, 0.44, 24)),
            this.mats.neroPedestal,
            colCoords.length
        );
        const aoPlaneGeo = this._trackDisposable(new THREE.PlaneGeometry(1, 1));
        aoPlaneGeo.rotateX(-Math.PI * 0.5);
        const colAoInst = new THREE.InstancedMesh(aoPlaneGeo, this.mats.contactShadow, colCoords.length);

        const dummy = new THREE.Object3D();
        this.pantheonColumnColliders = [];

        for (let cIdx = 0; cIdx < colCoords.length; cIdx++) {
            const [cx, cz] = colCoords[cIdx];
            this.pantheonColumnColliders.push({
                x: cx,
                z: this.pantheonCenterZ + cz,
                r: 1.18,
            });

            dummy.position.set(cx, floorTopY + colHeight * 0.5, cz);
            dummy.rotation.set(0, 0, 0);
            dummy.scale.set(1, 1, 1);
            dummy.updateMatrix();
            shaftInst.setMatrixAt(cIdx, dummy.matrix);

            dummy.position.set(cx, floorTopY + colHeight + 0.35, cz);
            dummy.updateMatrix();
            capInst.setMatrixAt(cIdx, dummy.matrix);

            dummy.position.set(cx, floorTopY + 0.22, cz);
            dummy.updateMatrix();
            baseInst.setMatrixAt(cIdx, dummy.matrix);

            dummy.position.set(cx, floorTopY + 0.010, cz);
            dummy.scale.set(3.35, 1, 3.35);
            dummy.updateMatrix();
            colAoInst.setMatrixAt(cIdx, dummy.matrix);
        }
        shaftInst.instanceMatrix.needsUpdate = true;
        capInst.instanceMatrix.needsUpdate = true;
        baseInst.instanceMatrix.needsUpdate = true;
        colAoInst.instanceMatrix.needsUpdate = true;
        this.pantheonGroup.add(shaftInst, capInst, baseInst, colAoInst);

        // 4. Kalkstein-Architrav & 100 % Z-Fighting-freie goldene Inschrift "HALLE DER HELDEN" (+0.032 m Normalen-Abstand, 0 Koplanaritaet!)
        const entY = floorTopY + colHeight + 1.45;
        const sideBeamGeo = this._trackDisposable(new THREE.BoxGeometry(3.4, 1.9, 47.0));
        const sideGoldGeo = this._trackDisposable(new THREE.BoxGeometry(3.68, 0.38, 47.4));
        [-22.1, 22.1].forEach((sx) => {
            const b = new THREE.Mesh(sideBeamGeo, this.mats.limestoneArch);
            b.position.set(sx, entY, 0);
            this.pantheonGroup.add(b);

            const g = new THREE.Mesh(sideGoldGeo, this.mats.goldMatcapBright);
            g.position.set(sx, entY + 1.15, 0);
            this.pantheonGroup.add(g);
        });

        const crossBeamGeo = this._trackDisposable(new THREE.BoxGeometry(47.4, 2.1, 3.4));
        const crossGoldGeo = this._trackDisposable(new THREE.BoxGeometry(47.8, 0.42, 3.74));
        [-22.1, 22.1].forEach((cz) => {
            const cb = new THREE.Mesh(crossBeamGeo, this.mats.limestoneArch);
            cb.position.set(0, entY, cz);
            this.pantheonGroup.add(cb);

            const cg = new THREE.Mesh(crossGoldGeo, this.mats.goldMatcapBright);
            cg.position.set(0, entY + 1.25, cz);
            this.pantheonGroup.add(cg);
        });

        const spandrelRingShape = new THREE.Shape();
        spandrelRingShape.moveTo(-23.6, -23.6);
        spandrelRingShape.lineTo(23.6, -23.6);
        spandrelRingShape.lineTo(23.6, 23.6);
        spandrelRingShape.lineTo(-23.6, 23.6);
        spandrelRingShape.closePath();
        const drumHole = new THREE.Path();
        drumHole.absarc(0, 0, 17.55, 0, TWO_PI, true);
        spandrelRingShape.holes.push(drumHole);
        const spandrelCeiling = new THREE.Mesh(
            this._trackDisposable(new THREE.ShapeGeometry(spandrelRingShape, 48)),
            this.mats.limestoneArch
        );
        spandrelCeiling.rotation.x = Math.PI * 0.5;
        spandrelCeiling.position.set(0, entY + 1.12, 0);
        this.pantheonGroup.add(spandrelCeiling);

        const friezeTex = buildHallBannerPlaqueTexture(THREE, this.maxAniso, 'HALLE DER HELDEN', this.disposables);
        const friezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: friezeTex,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2,
            depthWrite: true,
        }));

        const outerFriezeBack = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(24.8, 3.45, 0.54)),
            this.mats.goldMatcapBright
        );
        outerFriezeBack.position.set(0, entY + 3.25, 23.68);
        this.pantheonGroup.add(outerFriezeBack);

        const outerFrieze = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(24.0, 3.08)),
            friezeMat
        );
        outerFrieze.position.set(0, entY + 3.25, 23.68 + 0.27 + 0.032);
        this.pantheonGroup.add(outerFrieze);

        const innerFriezeBack = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(22.8, 3.25, 0.52)),
            this.mats.goldMatcapBright
        );
        innerFriezeBack.position.set(0, entY + 2.98, -19.85);
        this.pantheonGroup.add(innerFriezeBack);

        const innerFrieze = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(22.0, 2.90)),
            friezeMat
        );
        innerFrieze.position.set(0, entY + 2.98, -19.85 + 0.26 + 0.032);
        this.pantheonGroup.add(innerFrieze);

        const domeBaseY = entY + 5.35;
        const domeRadius = 17.5;
        const oculusRadius = 5.2;
        const ribArc = Math.acos(oculusRadius / domeRadius);

        const drumHeight = domeBaseY - (entY + 1.08);
        const tambourDrum = new THREE.Mesh(
            this._trackDisposable(new THREE.CylinderGeometry(domeRadius + 0.12, domeRadius + 0.12, drumHeight, 56, 1, true)),
            this.mats.limestoneArch
        );
        tambourDrum.material.side = THREE.DoubleSide;
        tambourDrum.position.set(0, (entY + 1.08) + drumHeight * 0.5, 0);
        this.pantheonGroup.add(tambourDrum);

        const drumLowerGoldRing = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(domeRadius + 0.02, 0.28, 10, 56)),
            this.mats.goldMatcapBrushed
        );
        drumLowerGoldRing.rotation.x = Math.PI * 0.5;
        drumLowerGoldRing.position.set(0, entY + 1.18, 0);
        this.pantheonGroup.add(drumLowerGoldRing);

        const drumCorners = [[-14.8, -14.8], [14.8, -14.8], [-14.8, 14.8], [14.8, 14.8]];
        const drumPostGeo = this._trackDisposable(new THREE.CylinderGeometry(0.62, 0.74, 4.6, 16));
        for (let dci = 0; dci < drumCorners.length; dci++) {
            const [dx, dz] = drumCorners[dci];
            const drumPost = new THREE.Mesh(drumPostGeo, this.mats.goldMatcapBright);
            drumPost.position.set(dx, entY + 2.95, dz);
            this.pantheonGroup.add(drumPost);
        }

        // Polare Radial-UV-Projektion fuer die echte Pantheon-Kassettendecke (pantheon_coffered_dome.jpg)
        const domeShellGeo = this._trackDisposable(
            new THREE.SphereGeometry(domeRadius + 0.22, 56, 28, 0, TWO_PI, 0, Math.PI * 0.5)
        );
        const domePosAttr = domeShellGeo.attributes.position;
        const domeUvAttr = domeShellGeo.attributes.uv;
        for (let vi = 0; vi < domePosAttr.count; vi++) {
            const vx = domePosAttr.getX(vi);
            const vy = domePosAttr.getY(vi);
            const vz = domePosAttr.getZ(vi);
            const rLen = Math.max(0.0001, Math.hypot(vx, vy, vz));
            const theta = Math.atan2(vz, vx);
            const phi = Math.acos(clamp(vy / rLen, 0, 1));
            const polarR = (phi / (Math.PI * 0.5)) * 0.492;
            domeUvAttr.setXY(vi, 0.5 + Math.cos(theta) * polarR, 0.5 + Math.sin(theta) * polarR);
        }
        domeUvAttr.needsUpdate = true;

        const cofferedDomeMesh = new THREE.Mesh(domeShellGeo, this.mats.pantheonDome);
        cofferedDomeMesh.position.set(0, domeBaseY, 0);
        this.pantheonGroup.add(cofferedDomeMesh);

        const domeBaseRing = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(domeRadius, 0.44, 10, 60)),
            this.mats.goldMatcapBright
        );
        domeBaseRing.rotation.x = Math.PI * 0.5;
        domeBaseRing.position.set(0, domeBaseY, 0);
        this.pantheonGroup.add(domeBaseRing);

        const midCofferRingR = Math.cos(ribArc * 0.52) * domeRadius;
        const midCofferRingY = domeBaseY + Math.sin(ribArc * 0.52) * domeRadius;
        const midCofferRing = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(midCofferRingR, 0.22, 8, 52)),
            this.mats.goldMatcapBrushed
        );
        midCofferRing.rotation.x = Math.PI * 0.5;
        midCofferRing.position.set(0, midCofferRingY, 0);
        this.pantheonGroup.add(midCofferRing);

        const ribGeo = this._trackDisposable(new THREE.TorusGeometry(domeRadius, 0.24, 8, 34, ribArc));
        for (let r = 0; r < 12; r++) {
            const rib = new THREE.Mesh(ribGeo, this.mats.goldMatcapBright);
            rib.position.set(0, domeBaseY, 0);
            rib.rotation.y = ((r + 0.5) / 6) * Math.PI;
            this.pantheonGroup.add(rib);
        }

        const oculusY = domeBaseY + Math.sqrt(domeRadius * domeRadius - oculusRadius * oculusRadius);
        const oculusRing = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(oculusRadius, 0.38, 10, 44)),
            this.mats.goldMatcapBright
        );
        oculusRing.rotation.x = Math.PI * 0.5;
        oculusRing.position.set(0, oculusY, 0);
        this.pantheonGroup.add(oculusRing);

        // Strahlender 24K-Gold-Oculus-Stern im Zenit
        this.oculusStarGroup = new THREE.Group();
        this.oculusStarGroup.position.set(0, oculusY + 2.2, 0);
        const domeStarCore = new THREE.Mesh(
            this._trackDisposable(new THREE.OctahedronGeometry(1.55, 0)),
            this.mats.beaconGold
        );
        this.oculusStarGroup.add(domeStarCore);

        const starRayGeo = this._trackDisposable(new THREE.ConeGeometry(0.28, 3.6, 6));
        for (let sr = 0; sr < 8; sr++) {
            const ray = new THREE.Mesh(starRayGeo, this.mats.goldMatcapBright);
            const ang = (sr / 8) * TWO_PI;
            ray.position.set(Math.cos(ang) * 1.4, 0, Math.sin(ang) * 1.4);
            ray.rotation.z = -Math.PI * 0.5;
            ray.rotation.y = -ang;
            this.oculusStarGroup.add(ray);
        }
        this.pantheonGroup.add(this.oculusStarGroup);

        // Punkt 3: Kein halbtransparenter Sonnenlichtkegel-Schleier im Durchblick der Rotunde

        // Goldener Fokus-Indikatorring fuer das aktuell fokussierte Heldenpodest
        this.heroFocusHaloMesh = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(1.42, 1.56, 44)),
            this.mats.mySlotGlow
        );
        this.heroFocusHaloMesh.rotation.x = -Math.PI * 0.5;
        this.heroFocusHaloMesh.visible = false;
        this.scene.add(this.heroFocusHaloMesh);

        this.pantheonDynamicGroup = new THREE.Group();
        this.pantheonGroup.add(this.pantheonDynamicGroup);
    }

    _readCsrfToken() {
        if (typeof document === 'undefined') return '';
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? (m.getAttribute('content') || '') : '';
    }

    _emitPlayerHudUpdate() {
        if (this.onPlayerHudChange) {
            this.onPlayerHudChange(this.getPlayerHudState());
        }
    }

    _computeSanctumAccess() {
        const a = this.sanctumAccess || {};
        const clientOk = Boolean(a.isAuthenticated && a.gamificationActive);
        if (a.serverCanEnter === null || a.serverCanEnter === undefined) {
            return clientOk;
        }
        return Boolean(a.serverCanEnter && clientOk);
    }

    /**
     * Zugangs-Checkliste (Angemeldet -> Magie aktivieren) fuer das helle Voraussetzungs-Modal (keine Bestellpflicht!).
     */
    getSanctumAccessState() {
        const a = this.sanctumAccess || {};
        let reason = null;
        if (!a.isAuthenticated) {
            reason = 'login_required';
        } else if (!a.gamificationActive) {
            reason = 'magic_activation_required';
        }
        if (!this.canEnterSanctums && !reason) {
            reason = (a.serverReason && a.serverReason !== 'order_required') ? a.serverReason : 'magic_activation_required';
        }
        return {
            canEnter: Boolean(this.canEnterSanctums),
            reason: this.canEnterSanctums ? null : reason,
            checks: {
                login: Boolean(a.isAuthenticated),
                magic: Boolean(a.gamificationActive),
            },
            loginUrl: a.loginUrl || '/login',
            dashboardUrl: a.dashboardUrl || '/dashboard',
        };
    }

    /**
     * Faengt HTTP 401/403 der Lobby-API ab (login_required | order_required | magic_activation_required).
     * Gesperrte Antworten liefern body = null zurueck, damit 0 Folge-Requests ausgeloest werden.
     */
    _interceptSanctumResponse(res, targetZone = 'heroes') {
        const status = res ? Number(res.status) || 0 : 0;
        const body = res ? res.body : null;
        if (status !== 401 && status !== 403) {
            return { status, body };
        }
        const sanctumReasons = ['login_required', 'order_required', 'magic_activation_required'];
        const rawReason = body ? String(body.reason || body.sanctum_block_reason || body.block_reason || body.error || '') : '';
        const code = body ? String(body.code || '').toUpperCase() : '';
        let reason = sanctumReasons.indexOf(rawReason) !== -1 ? rawReason : null;
        if (!reason) {
            if (status === 401 || code === 'LOGIN_REQUIRED' || code === 'AUTH_REQUIRED' || code === 'UNAUTHENTICATED') reason = 'login_required';
            else if (code === 'ORDER_REQUIRED' || code === 'FIRST_ORDER_REQUIRED') reason = 'order_required';
            else if (code === 'GAMIFICATION_INACTIVE' || code === 'MAGIC_ACTIVATION_REQUIRED' || code === 'MAGIC_INACTIVE') reason = 'magic_activation_required';
        }
        if (reason) {
            this._handleSanctumForbidden(reason, targetZone);
            return { status, body: null };
        }
        return { status, body };
    }

    /**
     * Backend meldet HTTP 403 (login_required | order_required | magic_activation_required):
     * Zugang lokal sperren, Spieler sanft in die Galerie zurueckfuehren & Voraussetzungs-Modal zeigen.
     */
    _handleSanctumForbidden(rawReason, targetZone = 'heroes') {
        const reason = String(rawReason || '');
        if (reason === 'login_required') {
            this.sanctumAccess.isAuthenticated = false;
        } else if (reason === 'order_required') {
            this.sanctumAccess.hasCompletedOrder = false;
        } else if (reason === 'magic_activation_required') {
            this.sanctumAccess.gamificationActive = false;
        }
        this.sanctumAccess.serverCanEnter = false;
        this.sanctumAccess.serverReason = reason || this.sanctumAccess.serverReason;
        this.canEnterSanctums = false;
        if (this.portalBarrierGroup) {
            this.portalBarrierGroup.visible = true;
        }
        if (this.currentZone !== 'gallery' && !this.destroyed) {
            this.goToZone('gallery', true);
        }
        this._emitPlayerHudUpdate();
        if (this.onAuthGatePrompt) {
            this.onAuthGatePrompt('heroes');
        }
    }

    getMmorpgAccessState() {
        return {
            canEnter: Boolean(this.mmorpgCanEnter),
            enterUrl: this.mmorpgEnterUrl || '/funkenreich/enter',
        };
    }

    getPlayerHudState() {
        return {
            isAuthenticated: Boolean(this.isAuthenticatedCustomer),
            canEnterSanctums: Boolean(this.canEnterSanctums),
            mmorpgCanEnter: Boolean(this.mmorpgCanEnter),
            mmorpgEnterUrl: this.mmorpgEnterUrl || '/funkenreich/enter',
            name: this.playerProfile.name,
            level: this.playerProfile.level,
            levelTitle: this.playerProfile.levelTitle,
            funkenBalance: this.playerProfile.displayedFunkenBalance,
            funkenActualBalance: this.playerProfile.funkenBalance,
            funkenTotalEarned: this.playerProfile.funkenTotalEarned,
            statusHintText: this.playerProfile.statusHintText || '',
            environment: this.environmentInfo,
        };
    }

    _disposeMagicAwakenChoreo() {
        const ch = this._magicAwakenChoreo;
        if (!ch) return;
        if (this.scene && ch.group) {
            this.scene.remove(ch.group);
        }
        if (ch.ringOuterMat) ch.ringOuterMat.dispose();
        if (ch.ringInnerMat) ch.ringInnerMat.dispose();
        if (ch.sparkMatA) ch.sparkMatA.dispose();
        if (ch.sparkMatB) ch.sparkMatB.dispose();
        this._magicAwakenChoreo = null;
        if (this.player) {
            this.player.awakenLiftY = 0;
            this.player.awakenPoseWeight = 0;
        }
    }

    _spawnMagicAwakenAura() {
        if (!this.scene || !this.THREE || !this.player) return;
        this._disposeMagicAwakenChoreo();

        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const px = this.player.x || 0;
        const pz = this.player.z || 0;
        const py = (this.player.y || 0) + this._computeFloorBaseY(px, pz);

        const group = new THREE.Group();
        group.position.set(px, py + 0.04, pz);

        // 2 expandierende 24K-Gold-Bodenringe
        const ringOuterMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#C5A059'),
            transparent: true,
            opacity: 0.96,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const ringInnerMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#FDE047'),
            transparent: true,
            opacity: 0.92,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const ringOuter = new THREE.Mesh(shared.geos.whirlRing, ringOuterMat);
        ringOuter.rotation.x = -Math.PI * 0.5;
        ringOuter.scale.setScalar(0.35);
        group.add(ringOuter);

        const ringInner = new THREE.Mesh(shared.geos.shockwaveRing, ringInnerMat);
        ringInner.rotation.x = -Math.PI * 0.5;
        ringInner.position.y = 0.015;
        ringInner.scale.setScalar(0.28);
        group.add(ringInner);

        // Doppelte goldene Doppel-Helix-Funkenspirale (2 Straenge x 24 Funken = 48 Funken)
        const sparkMatA = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#FDE047'),
            transparent: true,
            opacity: 0.98,
            depthWrite: false,
        });
        const sparkMatB = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#FFFBEB'),
            transparent: true,
            opacity: 0.96,
            depthWrite: false,
        });
        const helixSparks = [];
        const perStrand = 24;
        for (let strand = 0; strand < 2; strand++) {
            const mat = strand === 0 ? sparkMatA : sparkMatB;
            const strandOffset = strand * Math.PI;
            for (let i = 0; i < perStrand; i++) {
                const frac = i / perStrand;
                const sm = new THREE.Mesh(shared.geos.seelenfunkeStar || shared.geos.sparkDiamond, mat);
                sm.scale.setScalar(shared.geos.seelenfunkeStar ? 0.24 : 1.15);
                group.add(sm);
                helixSparks.push({
                    mesh: sm,
                    strandOffset,
                    frac,
                });
            }
        }

        this.scene.add(group);

        const keepFp = Boolean(this.camControl && this.camControl.desiredDistance < 0.55);
        const origDist = keepFp ? 3.2 : clamp(this.camControl.desiredDistance || 4.8, 2.8, 6.2);
        const origPitch = clamp(this.camControl.desiredPitch || 0.20, 0.08, 0.45);
        if (!keepFp && this.camControl) {
            this.camControl.desiredDistance = Math.min(origDist, 2.85);
            this.camControl.desiredPitch = 0.14;
        }

        this._magicAwakenChoreo = {
            elapsed: 0,
            duration: 1.45,
            baseYaw: this.player.yaw || 0,
            origDist,
            origPitch,
            keepFp,
            group,
            ringOuter,
            ringInner,
            ringOuterMat,
            ringInnerMat,
            sparkMatA,
            sparkMatB,
            helixSparks,
        };
    }

    _updateMagicAwakenChoreo(dt) {
        const ch = this._magicAwakenChoreo;
        if (!ch) return;
        ch.elapsed += dt;
        const u = clamp(ch.elapsed / ch.duration, 0, 1);
        if (u >= 1.0) {
            if (!ch.keepFp && this.camControl) {
                this.camControl.desiredDistance = ch.origDist;
                this.camControl.desiredPitch = ch.origPitch;
            }
            this.player.yaw = ch.baseYaw;
            this._disposeMagicAwakenChoreo();
            return;
        }

        const smoothU = u * u * (3.0 - 2.0 * u);
        const bell = Math.sin(u * Math.PI);
        const px = this.player.x || 0;
        const pz = this.player.z || 0;
        const py = (this.player.y || 0) + this._computeFloorBaseY(px, pz);

        // Funki schwebt 0.42 m empor + 360-Grad-Drehung + leicht erhobene Arme
        this.player.awakenLiftY = bell * 0.42;
        this.player.awakenPoseWeight = bell;
        this.player.yaw = normalizeAngleSigned(ch.baseYaw + smoothU * TWO_PI);

        ch.group.position.set(px, py + 0.04, pz);

        // 2 expandierende 24K-Gold-Bodenringe
        const sOuter = lerp(0.35, 2.65, 1.0 - Math.pow(1.0 - u, 2.2));
        const uInner = clamp((u - 0.14) / 0.86, 0, 1);
        const sInner = lerp(0.25, 2.20, 1.0 - Math.pow(1.0 - uInner, 2.2));
        ch.ringOuter.scale.setScalar(sOuter);
        ch.ringOuter.rotation.z = u * 2.2;
        ch.ringInner.scale.setScalar(sInner);
        ch.ringInner.rotation.z = -u * 2.8;

        const fade = u < 0.72 ? 1.0 : clamp((1.0 - u) / 0.28, 0, 1);
        ch.ringOuterMat.opacity = 0.94 * fade;
        ch.ringInnerMat.opacity = (u > 0.12 ? 0.90 : 0.0) * fade;
        ch.sparkMatA.opacity = 0.98 * fade;
        ch.sparkMatB.opacity = 0.94 * fade;

        // Aufsteigende Doppel-Helix-Funkenspirale rund um Funki
        for (let i = 0; i < ch.helixSparks.length; i++) {
            const hs = ch.helixSparks[i];
            const hProg = (hs.frac + u * 1.35) % 1.0;
            const yLocal = 0.08 + hProg * 2.45;
            const radius = lerp(0.68, 0.42, hProg) + 0.08 * Math.sin(u * Math.PI);
            const theta = hs.strandOffset + hProg * TWO_PI * 2.4 + u * TWO_PI * 1.6;
            hs.mesh.position.set(Math.cos(theta) * radius, yLocal, Math.sin(theta) * radius);
            hs.mesh.rotation.y = theta + u * 6.0;
            hs.mesh.rotation.z = u * 4.0;
        }

        if (!ch.keepFp && this.camControl && u > 0.76) {
            const backU = clamp((u - 0.76) / 0.24, 0, 1);
            this.camControl.desiredDistance = lerp(2.85, ch.origDist, backU);
            this.camControl.desiredPitch = lerp(0.14, ch.origPitch, backU);
        }
    }

    focusNearestExhibit() {
        if (this.destroyed || !this.steles || this.steles.length === 0) return false;
        if (this.currentZone !== 'gallery') {
            this.goToZone('gallery', true);
        }
        let bestIdx = 0;
        let bestDist = Infinity;
        for (let i = 0; i < this.steles.length; i++) {
            const st = this.steles[i];
            const d = Math.hypot((this.player.x || 0) - st.x, (this.player.z || 0) - st.z);
            if (d < bestDist) {
                bestDist = d;
                bestIdx = i;
            }
        }
        this.goToExhibit(bestIdx, false);
        return true;
    }

    /**
     * Live-Synchronisation nach In-Game-Login oder 1-Klick-Magie-Aktivierung OHNE Seiten-Reload:
     * Aktualisiert Sperr-Proxies, MMORPG-Freigabe und laedt nahtlos das echte Kunden-Level-.glb nach.
     */
    applyLiveAccountUpdate(payload = {}) {
        if (this.destroyed || !payload || typeof payload !== 'object') {
            return this.getSanctumAccessState();
        }

        const sa = (payload.sanctum_access && typeof payload.sanctum_access === 'object')
            ? payload.sanctum_access
            : ((payload.sanctumAccess && typeof payload.sanctumAccess === 'object') ? payload.sanctumAccess : null);
        const pf = (payload.player_funki && typeof payload.player_funki === 'object')
            ? payload.player_funki
            : ((payload.playerFunki && typeof payload.playerFunki === 'object') ? payload.playerFunki : null);

        if (payload.mmorpg_can_enter !== undefined) {
            this.mmorpgCanEnter = Boolean(payload.mmorpg_can_enter);
        } else if (payload.mmorpgCanEnter !== undefined) {
            this.mmorpgCanEnter = Boolean(payload.mmorpgCanEnter);
        } else if (pf && pf.mmorpg_can_enter !== undefined) {
            this.mmorpgCanEnter = Boolean(pf.mmorpg_can_enter);
        }
        if (payload.mmorpg_enter_url) {
            this.mmorpgEnterUrl = String(payload.mmorpg_enter_url);
        } else if (payload.mmorpgEnterUrl) {
            this.mmorpgEnterUrl = String(payload.mmorpgEnterUrl);
        } else if (pf && pf.mmorpg_enter_url) {
            this.mmorpgEnterUrl = String(pf.mmorpg_enter_url);
        }

        if (pf) {
            this.playerFunki = Object.assign({}, this.playerFunki || {}, pf);
            if (pf.is_guest !== undefined) {
                this.isAuthenticatedCustomer = !Boolean(pf.is_guest);
            }
        }
        if (sa) {
            if (sa.is_authenticated !== undefined) {
                this.sanctumAccess.isAuthenticated = Boolean(sa.is_authenticated);
                this.isAuthenticatedCustomer = Boolean(sa.is_authenticated);
            }
            if (sa.has_completed_order !== undefined) {
                this.sanctumAccess.hasCompletedOrder = Boolean(sa.has_completed_order);
            }
            if (sa.gamification_active !== undefined) {
                this.sanctumAccess.gamificationActive = Boolean(sa.gamification_active);
            }
            if (sa.can_enter_sanctums !== undefined) {
                this.sanctumAccess.serverCanEnter = Boolean(sa.can_enter_sanctums);
            }
            if (sa.block_reason !== undefined) {
                this.sanctumAccess.serverReason = sa.block_reason ? String(sa.block_reason) : null;
            }
            if (sa.login_url) this.sanctumAccess.loginUrl = String(sa.login_url);
            if (sa.dashboard_url) this.sanctumAccess.dashboardUrl = String(sa.dashboard_url);
        }

        this.canEnterSanctums = this._computeSanctumAccess();
        if (this.portalBarrierGroup) {
            this.portalBarrierGroup.visible = !this.canEnterSanctums;
        }
        if (this.canEnterSanctums) {
            this._setPortalGateFocused(false);
            this.pinnedPortalGate = false;
            this._heroesAuthGateTriggered = false;
        }

        if (this.audio && typeof this.audio.setAuthenticated === 'function') {
            this.audio.setAuthenticated(this.isAuthenticatedCustomer);
        }

        if (pf) {
            const nextLvl = clamp(parseInt(pf.level ?? this.playerProfile.level ?? 1, 10) || 1, 1, 10);
            const nextName = sanitizeDisplayText(pf.display_name || pf.name || (this.isAuthenticatedCustomer ? 'Funki' : 'Gast'));
            this.playerProfile.name = nextName;
            this.playerProfile.levelTitle = sanitizeDisplayText(pf.level_title || FUNKI_LEVEL_TITLES[nextLvl] || 'Ursprungsfunke');
            if (pf.funken_balance !== undefined) {
                const bal = Math.max(0, parseInt(pf.funken_balance, 10) || 0);
                this.playerProfile.funkenBalance = bal;
                this.playerProfile.displayedFunkenBalance = bal;
            }
            if (pf.funken_total_earned !== undefined) {
                this.playerProfile.funkenTotalEarned = Math.max(0, parseInt(pf.funken_total_earned, 10) || 0);
            }
            const nextModelUrl = pf.glb_url || pf.model_url || DEFAULT_FUNKI_GLB_URLS[nextLvl];
            this.upgradePlayerModel(nextLvl, nextModelUrl);
        }

        if (payload.atelier_review && typeof payload.atelier_review === 'object') {
            this.setAtelierReviewState(payload.atelier_review);
        } else if (payload.atelierReview && typeof payload.atelierReview === 'object') {
            this.setAtelierReviewState(payload.atelierReview);
        }

        if (payload.play_awaken_fx || payload.status === 'magic_activated') {
            this._spawnMagicAwakenAura();
            if (this.audio && typeof this.audio.playMagicAwaken === 'function') {
                this.audio.playMagicAwaken();
            }
        }

        this._heroesNeedsLiveRefresh = true;
        this._emitPlayerHudUpdate();
        return this.getSanctumAccessState();
    }

    _showStatusHint(text, durationSec = 2.1) {
        this.playerProfile.statusHintText = sanitizeDisplayText(text || '');
        this._statusHintTimer = Math.max(0.4, Number(durationSec) || 2.1);
        this._emitPlayerHudUpdate();
    }

    _delayMs(ms) {
        const wait = Math.max(0, Math.ceil(Number(ms) || 0));
        if (wait <= 0) return Promise.resolve();
        return new Promise((resolve) => {
            setTimeout(resolve, wait);
        });
    }

    _nowClientMs() {
        return (typeof performance !== 'undefined') ? performance.now() : Date.now();
    }

    _spawnChestFountainBurst(x, y, z) {
        if (!this.scene || !this.THREE) return;
        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const group = new THREE.Group();
        group.position.set(Number(x) || 0, Number(y) || 0, Number(z) || 0);

        const ringMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#D4AF37'),
            transparent: true,
            opacity: 0.95,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const ringMesh = new THREE.Mesh(shared.geos.baseRing, ringMat);
        ringMesh.rotation.x = -Math.PI * 0.5;
        ringMesh.scale.setScalar(1.1);
        group.add(ringMesh);

        const sparks = [];
        const sparkMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#FFF8DC'),
            transparent: true,
            opacity: 0.98,
            depthWrite: false,
        });
        for (let i = 0; i < 32; i++) {
            const useStar = Boolean(shared.geos.seelenfunkeStar && i % 2 === 0);
            const sm = new THREE.Mesh(useStar ? shared.geos.seelenfunkeStar : shared.geos.sparkDiamond, sparkMat);
            const ang = (i / 32) * TWO_PI;
            const spd = 1.2 + (i % 5) * 0.52;
            sm.scale.setScalar(useStar ? 0.28 : 1.25);
            sm.position.set(0, 0.05, 0);
            group.add(sm);
            sparks.push({
                mesh: sm,
                vx: Math.cos(ang) * spd,
                vy: 3.6 + (i % 4) * 0.9,
                vz: Math.sin(ang) * spd,
            });
        }
        this.scene.add(group);
        this.weaponBursts.push({
            group,
            slashMesh: ringMesh,
            arcMat: ringMat,
            sparkMat,
            sparks,
            age: 0,
            duration: 1.05,
            isRemote: false,
        });
    }

    _sanitizeStandingFunkiUrl(lvl, candidateUrl) {
        const safeLvl = clamp(parseInt(lvl, 10) || 1, 1, 10);
        const raw = String(candidateUrl || '').trim();
        if (raw !== '' && raw.indexOf('/shop/customer/gamification/models/funki_lvl_') !== -1) {
            return raw;
        }
        if (raw !== '' && raw.endsWith('.glb')) {
            return raw;
        }
        return DEFAULT_FUNKI_GLB_URLS[safeLvl] || DEFAULT_FUNKI_GLB_URLS[1];
    }

    _resolveFunkiModelUrl(lvl, hero) {
        const safeLvl = clamp(parseInt(lvl, 10) || 1, 1, 10);
        if (hero && !hero.is_placeholder && !hero.is_preview && hero.model_url) {
            return this._sanitizeStandingFunkiUrl(safeLvl, hero.model_url);
        }
        if (this.heroesPayload && this.heroesPayload.level_models && this.heroesPayload.level_models[safeLvl]) {
            return this._sanitizeStandingFunkiUrl(safeLvl, this.heroesPayload.level_models[safeLvl]);
        }
        return this._sanitizeStandingFunkiUrl(safeLvl, DEFAULT_FUNKI_GLB_URLS[safeLvl] || DEFAULT_FUNKI_GLB_URLS[1]);
    }

    _loadFunkiGlbTemplate(rawLevel, modelUrl) {
        const THREE = this.THREE;
        const lvl = clamp(parseInt(rawLevel, 10) || 1, 1, 10);

        if (this.glbTemplateCache.has(lvl)) {
            return Promise.resolve(this.glbTemplateCache.get(lvl));
        }
        if (this.glbLoadingPromises.has(lvl)) {
            return this.glbLoadingPromises.get(lvl);
        }

        const url = this._sanitizeStandingFunkiUrl(lvl, modelUrl || this._resolveFunkiModelUrl(lvl, null));
        const fetchOpts = { method: 'GET', credentials: 'same-origin' };
        if (this.abortController) {
            fetchOpts.signal = this.abortController.signal;
        }

        const p = fetch(url, fetchOpts)
            .then((resp) => {
                if (!resp.ok) {
                    throw new Error(`GLB HTTP ${resp.status}`);
                }
                return resp.arrayBuffer();
            })
            .then((buf) => parseGlbBinary(THREE, buf, this.maxAniso, this.disposables))
            .then((parsed) => {
                const stripped = stripFunkiPedestalAndNormalizeGeometry(THREE, parsed.scene);
                this._trackDisposable(stripped.geometry);
                const mesh = new THREE.Mesh(stripped.geometry, stripped.material);
                const templateGroup = new THREE.Group();
                templateGroup.add(mesh);
                templateGroup.userData = { level: lvl };

                this.glbTemplateCache.set(lvl, templateGroup);
                this.glbLoadingPromises.delete(lvl);
                return templateGroup;
            })
            .catch((err) => {
                this.glbLoadingPromises.delete(lvl);
                throw err;
            });

        this.glbLoadingPromises.set(lvl, p);
        return p;
    }

    /**
     * Erzeugt einen Top-5-Funki auf seinem Ehrenpodest mit aufrecht stehendem, voll artikuliertem 3D-Exoskelett + 15-Bone SkinnedMesh
     * sowie den individuellen Level-7..10-Ringen/Koronen (echtes 24K-Gold, 0 sitzender Level 1, 0 starres Klotz-Wackeln!).
     */
    _createFunkiCharacterByLevel(rawLevel, slotNum, scaleFactor, hero) {
        const THREE = this.THREE;
        const lvl = clamp(parseInt(rawLevel, 10) || 1, 1, 10);

        const root = new THREE.Group();
        root.scale.setScalar(scaleFactor || 1.0);

        // Basis-Ehrenring am Podest
        const auraOuterGeo = new THREE.TorusGeometry(0.88, 0.032, 10, 40);
        const auraInnerGeo = new THREE.RingGeometry(0.52, 0.62, 8);
        this.heroDynamicDisposables.push(auraOuterGeo, auraInnerGeo);

        const auraOuter = new THREE.Mesh(auraOuterGeo, this.mats.goldMatcapBright);
        auraOuter.rotation.x = Math.PI * 0.5;
        auraOuter.position.y = 0.032;
        root.add(auraOuter);

        const auraInner = new THREE.Mesh(auraInnerGeo, this.mats.beaconGold);
        auraInner.rotation.x = -Math.PI * 0.5;
        auraInner.position.y = 0.024;
        root.add(auraInner);

        // Level 7..10: Erhabene goldene Schwebekraenze / Sternringe / Funken-Korona
        let levitationRing = null;
        let lightbringerHalo = null;
        let godStarRingOuter = null;
        let godStarRingInner = null;
        let godCorona = null;

        if (lvl >= 7) {
            const levGeo = new THREE.TorusGeometry(0.72, 0.026, 8, 36);
            this.heroDynamicDisposables.push(levGeo);
            levitationRing = new THREE.Mesh(levGeo, this.mats.goldMatcapBright);
            levitationRing.rotation.x = Math.PI * 0.5;
            levitationRing.position.y = 0.11;
            root.add(levitationRing);
        }

        if (lvl === 9) {
            const haloGeo = new THREE.TorusGeometry(0.92, 0.028, 8, 40);
            this.heroDynamicDisposables.push(haloGeo);
            lightbringerHalo = new THREE.Mesh(haloGeo, this.mats.beaconGold);
            lightbringerHalo.position.set(0, 1.18, -0.08);
            lightbringerHalo.rotation.x = 0.34;
            root.add(lightbringerHalo);
        }

        if (lvl === 10) {
            const starOuterGeo = new THREE.TorusGeometry(1.06, 0.032, 8, 8);
            const starInnerGeo = new THREE.TorusGeometry(0.78, 0.026, 8, 6);
            this.heroDynamicDisposables.push(starOuterGeo, starInnerGeo);

            godStarRingOuter = new THREE.Mesh(starOuterGeo, this.mats.goldMatcapBright);
            godStarRingOuter.rotation.x = Math.PI * 0.5;
            godStarRingOuter.position.y = 0.14;
            root.add(godStarRingOuter);

            godStarRingInner = new THREE.Mesh(starInnerGeo, this.mats.beaconGold);
            godStarRingInner.rotation.x = Math.PI * 0.5;
            godStarRingInner.position.y = 0.22;
            root.add(godStarRingInner);

            godCorona = new THREE.Group();
            godCorona.position.set(0, 1.48, -0.18);
            const coronaRingGeo = new THREE.TorusGeometry(0.68, 0.024, 8, 32);
            const coronaSpireGeo = new THREE.ConeGeometry(0.045, 0.28, 4);
            this.heroDynamicDisposables.push(coronaRingGeo, coronaSpireGeo);
            godCorona.add(new THREE.Mesh(coronaRingGeo, this.mats.beaconGold));
            for (let ci = 0; ci < 8; ci++) {
                const ang = (ci / 8) * TWO_PI;
                const sp = new THREE.Mesh(coronaSpireGeo, this.mats.goldMatcapBright);
                sp.position.set(Math.cos(ang) * 0.80, Math.sin(ang) * 0.80, 0);
                sp.rotation.z = ang - Math.PI * 0.5;
                godCorona.add(sp);
            }
            root.add(godCorona);
        }

        const motionRig = new THREE.Group();
        root.add(motionRig);

        const heroModelUrlForRig = this._resolveFunkiModelUrl(lvl, hero || null);
        const avatarBuilt = this._createArticulatedAvatarInstance(
            lvl,
            null,
            false,
            heroModelUrlForRig,
            this.heroDynamicDisposables
        );
        if (avatarBuilt && avatarBuilt.joints && avatarBuilt.joints.baseRing) {
            avatarBuilt.joints.baseRing.visible = false;
        }
        motionRig.add(avatarBuilt.root);

        const animRecord = {
            motionRig,
            avatarBuilt,
            auraOuter,
            auraInner,
            levitationRing,
            lightbringerHalo,
            godStarRingOuter,
            godStarRingInner,
            godCorona,
            level: lvl,
            phase: slotNum * 1.19,
        };
        this.animatedFunkiFigures.push(animRecord);

        return root;
    }

    /**
     * Bestueckt die Top-5-Ehrenpodeste & Kolonnaden-Stelen (#6 bis #29) in der goldenen Halle der Helden.
     */
    _populateHeroesMonuments() {
        if (!this.pantheonDynamicGroup || !this.THREE) return;
        const THREE = this.THREE;

        while (this.pantheonDynamicGroup.children.length > 0) {
            this.pantheonDynamicGroup.remove(this.pantheonDynamicGroup.children[0]);
        }
        for (let i = 0; i < this.heroDynamicDisposables.length; i++) {
            const d = this.heroDynamicDisposables[i];
            if (d && typeof d.dispose === 'function') {
                d.dispose();
            }
        }
        this.heroDynamicDisposables = [];
        this.animatedFunkiFigures = [];
        this.freePedestalCrests = [];
        this.pedestalSparkMeta = [];
        this.pedestalSparkPosAttr = null;
        this.heroInteractiveHitMeshes = [];
        this.heroPedestalColliders = [];
        this.heroPedestals = [];
        this.heroSlotAnchors = {};
        this._monumentGeneration += 1;

        const heroesList = (this.heroesPayload && Array.isArray(this.heroesPayload.heroes))
            ? this.heroesPayload.heroes
            : [];
        const heroBySlot = {};
        for (let hi = 0; hi < heroesList.length; hi++) {
            const hObj = heroesList[hi];
            heroBySlot[hObj.slot || (hi + 1)] = hObj;
        }

        // Stufen-Vorschau-Modus ('1-5' oder '6-10'), falls vom User ueber "✨ Stufen" aktiviert
        const previewLevelsBySlot = this.stagesPreviewMode === '1-5'
            ? { 1: 5, 2: 4, 3: 3, 4: 2, 5: 1 }
            : (this.stagesPreviewMode === '6-10' ? { 1: 10, 2: 9, 3: 8, 4: 7, 5: 6 } : null);

        const floorTopY = 0.02;

        const pedestalSpecs = [
            { slot: 1, x: 0.0, z: -7.8, h: 1.72, w: 2.65, scale: 1.18, rotY: 0.0 },
            { slot: 2, x: -4.9, z: -4.6, h: 1.38, w: 2.30, scale: 1.04, rotY: 0.22 },
            { slot: 3, x: 4.9, z: -4.6, h: 1.38, w: 2.30, scale: 1.04, rotY: -0.22 },
            { slot: 4, x: -9.2, z: -0.8, h: 1.14, w: 2.12, scale: 0.96, rotY: 0.36 },
            { slot: 5, x: 9.2, z: -0.8, h: 1.14, w: 2.12, scale: 0.96, rotY: -0.36 },
        ];

        for (let pi = 0; pi < pedestalSpecs.length; pi++) {
            const sp = pedestalSpecs[pi];
            let effectiveHero = heroBySlot[sp.slot] || null;
            // Stufen-Ansicht (rein optisches Modell-Showcase auf freien Ehrenplaetzen; aendert NIEMALS das eigene Spieler-Level!)
            if (previewLevelsBySlot && (!effectiveHero || effectiveHero.is_placeholder)) {
                const pLvl = previewLevelsBySlot[sp.slot];
                effectiveHero = {
                    slot: sp.slot,
                    is_placeholder: false,
                    is_preview: true,
                    is_me: false,
                    display_name: 'Stufen-Ansicht · Stufe ' + pLvl,
                    level: pLvl,
                    level_title: FUNKI_LEVEL_TITLES[pLvl],
                    sparks: pLvl * 500,
                    sparks_formatted: (pLvl * 500).toLocaleString('de-DE'),
                    ship_class: pLvl >= 9 ? 'KLASSE S' : (pLvl >= 6 ? 'KLASSE A' : (pLvl >= 3 ? 'KLASSE B' : 'KLASSE C')),
                    titles_count: Math.max(1, Math.ceil(pLvl / 2)),
                };
            }

            const cardData = normalizeHeroCardData(sp.slot, effectiveHero);
            const displayLevel = cardData.level;

            const pGrp = new THREE.Group();
            pGrp.position.set(sp.x, floorTopY, sp.z);
            pGrp.rotation.y = sp.rotY;
            this.pantheonDynamicGroup.add(pGrp);
            const coreGeo = new THREE.CylinderGeometry(sp.w * 0.52, sp.w * 0.60, sp.h, 16);
            const capGeo = new THREE.CylinderGeometry(sp.w * 0.57, sp.w * 0.55, 0.16, 16);
            const baseGeo = new THREE.CylinderGeometry(sp.w * 0.63, sp.w * 0.66, 0.16, 16);
            const aoGeo = new THREE.PlaneGeometry(sp.w * 2.05, sp.w * 2.05);
            this.heroDynamicDisposables.push(coreGeo, capGeo, baseGeo, aoGeo);

            const pedCore = new THREE.Mesh(coreGeo, this.mats.neroPedestal);
            pedCore.position.y = sp.h * 0.5;
            pGrp.add(pedCore);

            const pedCap = new THREE.Mesh(capGeo, this.mats.goldMatcapBright);
            pedCap.position.y = sp.h + 0.05;
            pGrp.add(pedCap);

            const pedBase = new THREE.Mesh(baseGeo, this.mats.goldMatcapBright);
            pedBase.position.y = 0.08;
            pGrp.add(pedBase);

            if (cardData.is_me) {
                const myRingGeo = new THREE.TorusGeometry(sp.w * 0.72, 0.075, 10, 36);
                this.heroDynamicDisposables.push(myRingGeo);
                const myRing = new THREE.Mesh(myRingGeo, this.mats.mySlotGlow);
                myRing.rotation.x = Math.PI * 0.5;
                myRing.position.y = 0.12;
                pGrp.add(myRing);
            }

            const plaqueCanvas = document.createElement('canvas');
            plaqueCanvas.width = 1024;
            plaqueCanvas.height = 512;
            paintHeroPedestalCanvas(plaqueCanvas, sp.slot, effectiveHero);
            const plaqueTex = new THREE.CanvasTexture(plaqueCanvas);
            plaqueTex.generateMipmaps = true;
            plaqueTex.minFilter = THREE.LinearMipmapLinearFilter;
            plaqueTex.magFilter = THREE.LinearFilter;
            plaqueTex.anisotropy = Math.min(this.maxAniso || 4, 4);
            applySrgbColorSpace(THREE, plaqueTex);
            plaqueTex.needsUpdate = true;

            const pW = sp.w * 0.84;
            const pH = pW * 0.50;
            const frameGeo = new THREE.BoxGeometry(pW + 0.11, pH + 0.11, 0.07);
            const faceGeo = new THREE.PlaneGeometry(pW, pH);
            const faceMat = new THREE.MeshBasicMaterial({
                map: plaqueTex,
                polygonOffset: true,
                polygonOffsetFactor: -2,
                polygonOffsetUnits: -4,
            });
            this.heroDynamicDisposables.push(plaqueTex, frameGeo, faceGeo, faceMat);

            const plaqueFrame = new THREE.Mesh(frameGeo, this.mats.goldMatcapBright);
            plaqueFrame.position.set(0, sp.h * 0.54, sp.w * 0.56);
            plaqueFrame.rotation.x = -0.08;
            pGrp.add(plaqueFrame);

            const plaqueMesh = new THREE.Mesh(faceGeo, faceMat);
            plaqueMesh.position.set(0, sp.h * 0.54, sp.w * 0.56 + 0.042);
            plaqueMesh.rotation.x = -0.08;
            pGrp.add(plaqueMesh);

            const pedAoMesh = new THREE.Mesh(aoGeo, this.mats.contactShadow);
            pedAoMesh.rotation.x = -Math.PI * 0.5;
            pedAoMesh.position.y = 0.010;
            pGrp.add(pedAoMesh);

            // Punkt 17: Unbesetzte Ehrenplaetze ("Freier Ehrenplatz") bleiben edel frei (0 Dummy-Funki!), besetzte zeigen das echte Level-GLB!
            if (!cardData.is_placeholder) {
                const funkiChar = this._createFunkiCharacterByLevel(displayLevel, sp.slot, sp.scale, effectiveHero);
                funkiChar.position.set(0, sp.h + 0.13, 0);
                pGrp.add(funkiChar);
            } else {
                const freeHolder = new THREE.Group();
                freeHolder.position.set(0, sp.h + 0.14, 0);
                const freeRingGeo = new THREE.TorusGeometry(sp.w * 0.30, 0.022, 8, 36);
                const freeGemGeo = new THREE.OctahedronGeometry(0.14 * sp.scale, 0);
                this.heroDynamicDisposables.push(freeRingGeo, freeGemGeo);

                const freeRing = new THREE.Mesh(freeRingGeo, this.mats.goldMatcapBrushed);
                freeRing.rotation.x = Math.PI * 0.5;
                freeRing.position.y = 0.02;
                freeHolder.add(freeRing);

                const freeGem = new THREE.Mesh(freeGemGeo, this.mats.beaconGold);
                freeGem.position.y = 0.42;
                freeHolder.add(freeGem);

                pGrp.add(freeHolder);
                this.freePedestalCrests.push({ gem: freeGem, ring: freeRing, phase: sp.slot * 0.9 });
            }

            const proxyGeo = new THREE.CylinderGeometry(sp.w * 0.72, sp.w * 0.72, sp.h + 2.8, 12);
            const proxyMat = new THREE.MeshBasicMaterial({ visible: false });
            this.heroDynamicDisposables.push(proxyGeo, proxyMat);
            const proxy = new THREE.Mesh(proxyGeo, proxyMat);
            proxy.position.y = (sp.h + 2.8) * 0.5;
            proxy.userData = { heroSlot: sp.slot, isTopFive: true };
            pGrp.add(proxy);
            this.heroInteractiveHitMeshes.push(proxy);

            const worldX = sp.x;
            const worldZ = this.pantheonCenterZ + sp.z;
            const standDist = sp.w * 0.66 + 1.85;
            const standX = worldX + Math.sin(sp.rotY) * standDist;
            const standZ = worldZ + Math.cos(sp.rotY) * standDist;

            this.heroPedestalColliders.push({
                x: worldX,
                z: worldZ,
                r: sp.w * 0.65 + 0.36,
            });

            const pedRecord = {
                slot: sp.slot,
                worldX,
                worldZ,
                topY: floorTopY + sp.h + 0.13,
                cardAnchorY: floorTopY + sp.h + 2.38 * sp.scale,
                haloRadius: sp.w * 0.68,
                standX,
                standZ,
                cardData,
            };
            this.heroPedestals.push(pedRecord);
            this.heroSlotAnchors[sp.slot] = pedRecord;
        }

        // Aufsteigende goldene Seelenfunken-Partikel um alle 5 Ehrenpodeste (1 gebuendelter Points Draw-Call!)
        const sparksPerPedestal = 22;
        const totalPedSparks = pedestalSpecs.length * sparksPerPedestal;
        const sparkPositions = new Float32Array(totalPedSparks * 3);
        const sparkColors = new Float32Array(totalPedSparks * 3);
        const sparkRng = mulberry32(20261117);

        for (let spi = 0; spi < pedestalSpecs.length; spi++) {
            const pSpec = pedestalSpecs[spi];
            const pedRec = this.heroPedestals[spi];
            const pLvl = pedRec ? pedRec.cardData.level : 1;
            const levelSpeedBoost = 0.8 + pLvl * 0.08;

            for (let sj = 0; sj < sparksPerPedestal; sj++) {
                const sIdx = spi * sparksPerPedestal + sj;
                const phaseVal = sparkRng();
                const baseR = pSpec.w * (0.34 + sparkRng() * 0.28);
                const spanH = (2.15 + sparkRng() * 0.95) * pSpec.scale;
                const spd = (0.12 + sparkRng() * 0.15) * levelSpeedBoost;
                const sw = (0.60 + sparkRng() * 0.75) * (sj % 2 === 0 ? 1 : -1);
                const startY = floorTopY + pSpec.h + 0.15;

                this.pedestalSparkMeta.push({
                    baseX: pSpec.x,
                    baseY: startY,
                    baseZ: pSpec.z,
                    radius: baseR,
                    heightSpan: spanH,
                    speed: spd,
                    phase: phaseVal,
                    swirl: sw,
                });

                sparkPositions[sIdx * 3] = pSpec.x + Math.cos(phaseVal * TWO_PI) * baseR;
                sparkPositions[sIdx * 3 + 1] = startY + phaseVal * spanH;
                sparkPositions[sIdx * 3 + 2] = pSpec.z + Math.sin(phaseVal * TWO_PI) * baseR;

                const warmWhite = (sj % 3 === 0);
                sparkColors[sIdx * 3] = 1.0;
                sparkColors[sIdx * 3 + 1] = warmWhite ? 0.96 : 0.86;
                sparkColors[sIdx * 3 + 2] = warmWhite ? 0.82 : 0.45;
            }
        }

        const pedSparkGeo = new THREE.BufferGeometry();
        this.pedestalSparkPosAttr = new THREE.BufferAttribute(sparkPositions, 3);
        pedSparkGeo.setAttribute('position', this.pedestalSparkPosAttr);
        pedSparkGeo.setAttribute('color', new THREE.BufferAttribute(sparkColors, 3));
        const pedSparkMat = new THREE.PointsMaterial({
            size: 0.20,
            map: this.sparkSpriteTex || null,
            vertexColors: true,
            transparent: true,
            opacity: 0.88,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
        });
        this.heroDynamicDisposables.push(pedSparkGeo, pedSparkMat);
        const pedSparkPoints = new THREE.Points(pedSparkGeo, pedSparkMat);
        pedSparkPoints.frustumCulled = false;
        this.pantheonDynamicGroup.add(pedSparkPoints);

        // Kolonnaden-Ehren-Stelen fuer Platz #6 bis #10 (und bis #29) via InstancedMesh
        const totalSlots = Math.max(TOTAL_HERO_DISPLAY_SLOTS, Math.min(99, heroesList.length + 6));
        const steleCount = Math.max(0, totalSlots - 5);

        if (steleCount > 0) {
            const steleObsGeo = new THREE.BoxGeometry(1.25, 2.75, 0.32);
            const steleGoldGeo = new THREE.BoxGeometry(1.38, 2.88, 0.24);
            const steleFaceGeo = new THREE.PlaneGeometry(1.10, 2.16);
            const steleProxyGeo = new THREE.BoxGeometry(1.55, 2.95, 0.72);
            const steleProxyMat = new THREE.MeshBasicMaterial({ visible: false });
            this.heroDynamicDisposables.push(steleObsGeo, steleGoldGeo, steleFaceGeo, steleProxyGeo, steleProxyMat);

            const steleObsInst = new THREE.InstancedMesh(steleObsGeo, this.mats.neroPedestal, steleCount);
            const steleGoldInst = new THREE.InstancedMesh(steleGoldGeo, this.mats.goldMatcapBright, steleCount);
            steleObsInst.frustumCulled = false;
            steleGoldInst.frustumCulled = false;
            this.pantheonDynamicGroup.add(steleGoldInst, steleObsInst);

            const dummy = new THREE.Object3D();
            for (let i = 0; i < steleCount; i++) {
                const slot = 6 + i;
                const sHero = heroBySlot[slot] || null;
                const normStele = normalizeHeroCardData(slot, sHero);

                const isRight = (i % 2 === 1);
                const row = Math.floor(i / 2);
                const tierOffset = Math.floor(row / 12) * 2.8;
                const rowInColonnade = row % 12;
                const sx = (isRight ? 16.4 : -16.4) + (isRight ? -tierOffset : tierOffset);
                const sz = lerp(-15.5, 16.5, rowInColonnade / 11);
                const rotY = isRight ? -Math.PI * 0.42 : Math.PI * 0.42;

                dummy.position.set(sx, floorTopY + 1.44, sz);
                dummy.rotation.set(0, rotY, 0);
                dummy.scale.set(1, 1, 1);
                dummy.updateMatrix();
                steleObsInst.setMatrixAt(i, dummy.matrix);
                steleGoldInst.setMatrixAt(i, dummy.matrix);

                const stCanvas = document.createElement('canvas');
                stCanvas.width = 256;
                stCanvas.height = 512;
                paintHeroSteleCanvas(stCanvas, slot, sHero);
                const stTex = new THREE.CanvasTexture(stCanvas);
                stTex.minFilter = THREE.LinearFilter;
                stTex.magFilter = THREE.LinearFilter;
                applySrgbColorSpace(THREE, stTex);
                stTex.needsUpdate = true;

                const stMat = new THREE.MeshBasicMaterial({
                    map: stTex,
                    polygonOffset: true,
                    polygonOffsetFactor: -2,
                    polygonOffsetUnits: -4,
                });
                this.heroDynamicDisposables.push(stTex, stMat);

                const facePlane = new THREE.Mesh(steleFaceGeo, stMat);
                facePlane.position.set(
                    sx + Math.sin(rotY) * 0.175,
                    floorTopY + 1.48,
                    sz + Math.cos(rotY) * 0.175
                );
                facePlane.rotation.y = rotY;
                this.pantheonDynamicGroup.add(facePlane);

                if (normStele.is_me) {
                    const mySteleRingGeo = new THREE.TorusGeometry(1.05, 0.065, 8, 28);
                    this.heroDynamicDisposables.push(mySteleRingGeo);
                    const mySteleRing = new THREE.Mesh(mySteleRingGeo, this.mats.mySlotGlow);
                    mySteleRing.rotation.x = Math.PI * 0.5;
                    mySteleRing.position.set(sx, floorTopY + 0.06, sz);
                    this.pantheonDynamicGroup.add(mySteleRing);
                }

                const worldX = sx;
                const worldZ = this.pantheonCenterZ + sz;
                const standX = worldX + Math.sin(rotY) * 2.35;
                const standZ = worldZ + Math.cos(rotY) * 2.35;

                const stProxy = new THREE.Mesh(steleProxyGeo, steleProxyMat);
                stProxy.position.set(sx, floorTopY + 1.48, sz);
                stProxy.rotation.y = rotY;
                stProxy.userData = { heroSlot: slot, isTopFive: false };
                this.pantheonDynamicGroup.add(stProxy);
                this.heroInteractiveHitMeshes.push(stProxy);

                this.heroSlotAnchors[slot] = {
                    slot,
                    worldX,
                    worldZ,
                    standX,
                    standZ,
                    cardData: normStele,
                };
            }

            steleObsInst.instanceMatrix.needsUpdate = true;
            steleGoldInst.instanceMatrix.needsUpdate = true;
        }

        // Falls gerade ein Top-5-Podest fokussiert ist, aktualisierte Helden-Daten an das HUD melden
        if (this.focusedHeroSlot >= 1 && this.focusedHeroSlot <= 5) {
            const activePed = this.heroSlotAnchors[this.focusedHeroSlot];
            if (activePed && this.onFocusHeroChange) {
                this.onFocusHeroChange(activePed.cardData, this.focusedHeroSlot);
            }
        }
    }

    setHeroesPayload(payload) {
        if (!payload || typeof payload !== 'object') return;
        this.heroesPayload = payload;
        if (payload.customer_logged_in) {
            this.isAuthenticatedCustomer = true;
            this.sanctumAccess.isAuthenticated = true;
            this.canEnterSanctums = this._computeSanctumAccess();
            if (this.portalBarrierGroup && this.canEnterSanctums) {
                this.portalBarrierGroup.visible = false;
            }
        }
        if (this.pantheonDynamicGroup) {
            this._populateHeroesMonuments();
        }
    }

    /**
     * Punkt 17: Laedt aktuelle Helden-Daten live vom Server nach (z. B. beim Betreten der Halle der Helden nach Magma-Funken-Gewinn).
     */
    refreshHeroesData(force = false) {
        if (this.destroyed || typeof fetch !== 'function') {
            return Promise.resolve(null);
        }
        const nowMs = this._nowClientMs();
        if (!force && !this._heroesNeedsLiveRefresh && (nowMs - (this._lastHeroesRefreshMs || 0)) < 2500) {
            return Promise.resolve(this.heroesPayload);
        }
        this._lastHeroesRefreshMs = nowMs;
        this._heroesNeedsLiveRefresh = false;

        return fetch('/funkenwerk-welt/heroes-data', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
            },
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((json) => {
                if (!this.destroyed && json && typeof json === 'object' && Array.isArray(json.heroes)) {
                    this.setHeroesPayload(json);
                }
                return json;
            })
            .catch(() => null);
    }

    setStagesPreviewMode(mode) {
        const clean = (mode === '1-5' || mode === '6-10') ? mode : 'live';
        this.stagesPreviewMode = clean;
        this._populateHeroesMonuments();
        return this.stagesPreviewMode;
    }

    cycleStagesPreviewMode() {
        const next = this.stagesPreviewMode === 'live'
            ? '1-5'
            : (this.stagesPreviewMode === '1-5' ? '6-10' : 'live');
        return this.setStagesPreviewMode(next);
    }

    /**
     * Baut alle Produkt-Podeste ueber gebuendelte InstancedMeshes (minimale Draw-Calls)
     * plus physisches Light-Mode Podest-Schild (Stufe 1) an jeder Vorderkante.
     */
    _buildExhibitSteles(baySpacing) {
        const THREE = this.THREE;
        const count = this.products.length;
        if (count === 0) return;

        const shadowGeo = this._trackDisposable(new THREE.PlaneGeometry(2.15, 2.15));
        const plinthBaseGeo = this._trackDisposable(new THREE.BoxGeometry(1.04, 0.08, 1.04));
        const monolithGeo = this._trackDisposable(new THREE.BoxGeometry(0.92, 1.04, 0.92));
        const goldCollarGeo = this._trackDisposable(new THREE.BoxGeometry(0.96, 0.045, 0.96));
        const velvetTopGeo = this._trackDisposable(new THREE.BoxGeometry(0.84, 0.04, 0.84));
        const vitrineBoxGeo = this._trackDisposable(new THREE.BoxGeometry(0.82, 0.86, 0.82));
        const vitrineTopCapGeo = this._trackDisposable(new THREE.BoxGeometry(0.84, 0.022, 0.84));
        const cornerPostGeo = this._trackDisposable(new THREE.BoxGeometry(0.016, 0.86, 0.016));
        const plaqueBackingGeo = this._trackDisposable(new THREE.BoxGeometry(0.68, 0.34, 0.032));
        const plaqueFaceGeo = this._trackDisposable(new THREE.PlaneGeometry(0.64, 0.315));
        const clickProxyGeo = this._trackDisposable(new THREE.BoxGeometry(1.18, 2.18, 1.18));
        const placeholderCrystalGeo = this._trackDisposable(new THREE.OctahedronGeometry(0.14, 0));

        this._sharedFrameGeos = {
            outer: this._trackDisposable(new THREE.BoxGeometry(0.46, 0.46, 0.026)),
            passepartout: this._trackDisposable(new THREE.BoxGeometry(0.42, 0.42, 0.03)),
            innerBevel: this._trackDisposable(new THREE.BoxGeometry(0.355, 0.355, 0.033)),
            photoPlane: this._trackDisposable(new THREE.PlaneGeometry(0.34, 0.34)),
            standFoot: this._trackDisposable(new THREE.BoxGeometry(0.18, 0.02, 0.14)),
        };

        const invisibleMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            transparent: true,
            opacity: 0.0,
            depthWrite: false,
        }));

        const aoInst = new THREE.InstancedMesh(shadowGeo, this.mats.contactShadow, count);
        const plinthBaseInst = new THREE.InstancedMesh(plinthBaseGeo, this.mats.goldBrushed, count);
        const monolithInst = new THREE.InstancedMesh(monolithGeo, this.mats.neroPedestal, count);
        const goldCollarInst = new THREE.InstancedMesh(goldCollarGeo, this.mats.goldChampagne, count);
        const velvetPadInst = new THREE.InstancedMesh(velvetTopGeo, this.mats.velvetPad, count);
        const vitrineHoodInst = new THREE.InstancedMesh(vitrineBoxGeo, this.mats.vitrineGlass, count);
        vitrineHoodInst.renderOrder = 4;
        const vitrineCapInst = new THREE.InstancedMesh(vitrineTopCapGeo, this.mats.goldBrushed, count);
        const cornerPostInst = new THREE.InstancedMesh(cornerPostGeo, this.mats.goldBrushed, count * 4);
        const plaqueBackingInst = new THREE.InstancedMesh(plaqueBackingGeo, this.mats.goldBrushed, count);

        const parentDummy = new THREE.Object3D();
        const childDummy = new THREE.Object3D();
        const combinedMat = new THREE.Matrix4();
        const postOff = 0.405;
        const postOffsets = [
            [-postOff, -postOff],
            [postOff, -postOff],
            [-postOff, postOff],
            [postOff, postOff],
        ];

        const setLocalInstance = (instMesh, index, lx, ly, lz, rx = 0, ry = 0, rz = 0) => {
            childDummy.position.set(lx, ly, lz);
            childDummy.rotation.set(rx, ry, rz);
            childDummy.scale.set(1, 1, 1);
            childDummy.updateMatrix();
            combinedMat.multiplyMatrices(parentDummy.matrix, childDummy.matrix);
            instMesh.setMatrixAt(index, combinedMat);
        };

        for (let i = 0; i < count; i++) {
            const product = this.products[i];
            const row = Math.floor(i / 2);
            const isLeft = (i % 2 === 0);

            // Freie Sichtachse in der Mitte (x = 0) direkt auf das goldene Ehrenportal!
            const sx = isLeft ? -4.45 : 4.45;
            const sz = (this.hallSouthZ - 6.4) - row * baySpacing;
            const facingYaw = isLeft ? (Math.PI * 0.34) : (-Math.PI * 0.34);

            parentDummy.position.set(sx, 0, sz);
            parentDummy.rotation.set(0, facingYaw, 0);
            parentDummy.scale.set(1, 1, 1);
            parentDummy.updateMatrix();

            setLocalInstance(aoInst, i, 0, 0.004, 0, -Math.PI * 0.5, 0, 0);
            setLocalInstance(plinthBaseInst, i, 0, 0.04, 0);
            setLocalInstance(monolithInst, i, 0, 0.60, 0);
            setLocalInstance(goldCollarInst, i, 0, 1.135, 0);
            setLocalInstance(velvetPadInst, i, 0, 1.17, 0);
            setLocalInstance(vitrineHoodInst, i, 0, 1.62, 0);
            setLocalInstance(vitrineCapInst, i, 0, 2.06, 0);
            setLocalInstance(plaqueBackingInst, i, 0, 0.84, 0.48, -0.26, 0, 0);

            for (let p = 0; p < 4; p++) {
                setLocalInstance(cornerPostInst, i * 4 + p, postOffsets[p][0], 1.62, postOffsets[p][1]);
            }

            const steleRoot = new THREE.Group();
            steleRoot.position.set(sx, 0, sz);
            steleRoot.rotation.y = facingYaw;
            (this.galleryGroup || this.scene).add(steleRoot);

            const plaqueTex = buildStelePlaqueTexture(THREE, this.maxAniso, product, this.disposables);
            const plaqueMat = this._trackDisposable(new THREE.MeshBasicMaterial({
                map: plaqueTex,
                polygonOffset: true,
                polygonOffsetFactor: -2,
                polygonOffsetUnits: -2,
                depthWrite: true,
            }));
            const pFace = new THREE.Mesh(plaqueFaceGeo, plaqueMat);
            pFace.position.set(0, 0.84 + Math.sin(0.26) * 0.026, 0.48 + Math.cos(0.26) * 0.026);
            pFace.rotation.x = -0.26;
            steleRoot.add(pFace);

            const exhibitTurntable = new THREE.Group();
            exhibitTurntable.position.set(0, 1.56, 0);
            steleRoot.add(exhibitTurntable);

            const placeholderMesh = new THREE.Mesh(placeholderCrystalGeo, this.mats.goldChampagne);
            exhibitTurntable.add(placeholderMesh);

            const hitBox = new THREE.Mesh(clickProxyGeo, invisibleMat);
            hitBox.position.y = 1.08;
            hitBox.userData = { steleIndex: i };
            steleRoot.add(hitBox);
            this.interactiveHitMeshes.push(hitBox);

            const standDist = 2.15;
            const standX = sx + Math.sin(facingYaw) * standDist;
            const standZ = sz + Math.cos(facingYaw) * standDist;

            const steleRecord = {
                index: i,
                product,
                x: sx,
                z: sz,
                facingYaw,
                standX,
                standZ,
                turntable: exhibitTurntable,
                placeholderMesh,
                loaded: false,
            };
            this.steles.push(steleRecord);
            this.exhibitLoadQueue.push(steleRecord);
        }

        (this.galleryGroup || this.scene).add(
            aoInst,
            plinthBaseInst,
            monolithInst,
            goldCollarInst,
            velvetPadInst,
            vitrineHoodInst,
            vitrineCapInst,
            cornerPostInst,
            plaqueBackingInst
        );

        this.focusHaloMesh = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.49, 0.54, 32)),
            this.mats.goldChampagne
        );
        this.focusHaloMesh.rotation.x = -Math.PI * 0.5;
        this.focusHaloMesh.visible = false;
        (this.galleryGroup || this.scene).add(this.focusHaloMesh);
    }

    _scheduleNextExhibitLoad(delayMs = 55) {
        if (this.destroyed || this.isLoadingExhibit || this.exhibitLoadQueue.length === 0) {
            return;
        }
        if (this.queueTimerId) {
            clearTimeout(this.queueTimerId);
        }
        this.queueTimerId = setTimeout(() => {
            this.queueTimerId = 0;
            this._processNextExhibitInQueue();
        }, delayMs);
    }

    _processNextExhibitInQueue() {
        if (this.destroyed || this.isLoadingExhibit || this.exhibitLoadQueue.length === 0) {
            return;
        }

        let bestIdx = 0;
        let bestDistSq = Infinity;
        for (let i = 0; i < this.exhibitLoadQueue.length; i++) {
            const st = this.exhibitLoadQueue[i];
            const dx = this.player.x - st.x;
            const dz = this.player.z - st.z;
            const dSq = dx * dx + dz * dz;
            if (dSq < bestDistSq) {
                bestDistSq = dSq;
                bestIdx = i;
            }
        }

        const nextStele = this.exhibitLoadQueue.splice(bestIdx, 1)[0];
        if (!nextStele || nextStele.loaded) {
            this._scheduleNextExhibitLoad(20);
            return;
        }

        this.isLoadingExhibit = true;
        const done = () => {
            nextStele.loaded = true;
            this.isLoadingExhibit = false;
            if (!this.destroyed && this.exhibitLoadQueue.length > 0) {
                this._scheduleNextExhibitLoad(60);
            }
        };

        const isVoucherExhibit = Boolean(
            nextStele.product
            && (nextStele.product.slug === 'geschenkgutschein' || nextStele.product.collection_world === 'voucher')
        );
        if (nextStele.product.glb_url) {
            this._populateExhibitGlb(nextStele).finally(done);
        } else if (isVoucherExhibit) {
            this._populateVoucherEnvelope3D(nextStele).finally(done);
        } else {
            this._populateFramedPhotoExhibit(nextStele).finally(done);
        }
    }

    _loadGlbCached(url) {
        if (this.glbCache.has(url)) {
            return this.glbCache.get(url);
        }
        const fetchOpts = { method: 'GET', credentials: 'same-origin' };
        if (this.abortController) {
            fetchOpts.signal = this.abortController.signal;
        }
        const promise = fetch(url, fetchOpts)
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.arrayBuffer();
            })
            .then((buf) => parseGlbBinary(this.THREE, buf, this.maxAniso, this.disposables));

        this.glbCache.set(url, promise);
        return promise;
    }

    _clearPlaceholder(steleRecord) {
        if (steleRecord.placeholderMesh && steleRecord.turntable) {
            steleRecord.turntable.remove(steleRecord.placeholderMesh);
            steleRecord.placeholderMesh = null;
        }
    }

    _populateExhibitGlb(steleRecord) {
        const THREE = this.THREE;
        const { product, turntable } = steleRecord;

        return this._loadGlbCached(product.glb_url)
            .then((parsed) => {
                if (this.destroyed) return;
                const modelClone = parsed.scene.clone(true);
                modelClone.updateMatrixWorld(true);

                const bbox = new THREE.Box3().setFromObject(modelClone);
                const size = bbox.getSize(new THREE.Vector3());
                const center = bbox.getCenter(new THREE.Vector3());
                const maxDim = Math.max(0.001, size.x, size.y, size.z);

                const targetSize = product.material_hint === 'crystal' ? 0.48 : 0.44;
                const scale = targetSize / maxDim;
                modelClone.scale.setScalar(scale);
                modelClone.position.set(
                    -center.x * scale,
                    -center.y * scale,
                    -center.z * scale
                );

                this._refineProductPbrMaterial(modelClone, product);
                this._clearPlaceholder(steleRecord);
                turntable.add(modelClone);
            })
            .catch(() => {
                if (!this.destroyed) {
                    return this._populateFramedPhotoExhibit(steleRecord);
                }
            });
    }

    _refineProductPbrMaterial(modelRoot, product) {
        const THREE = this.THREE;
        const hint = product.material_hint || 'metal';

        modelRoot.traverse((child) => {
            if (!child.isMesh || !child.material) return;
            const origMat = Array.isArray(child.material) ? child.material[0] : child.material;
            const origMap = origMat ? origMat.map : null;
            const matName = origMat ? (origMat.name || '') : '';

            if (hint === 'crystal' || matName === 'Optical_K9_Crystal_Glass') {
                child.renderOrder = 2;
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: 0xf4fbff,
                    metalness: 0.14,
                    roughness: 0.04,
                    envMapIntensity: 1.5,
                    transparent: true,
                    opacity: 0.86,
                    depthWrite: false,
                    side: THREE.DoubleSide,
                }));
            } else if (hint === 'glass') {
                child.renderOrder = 2;
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: 0xf7fbff,
                    metalness: 0.1,
                    roughness: 0.06,
                    envMapIntensity: 1.3,
                    transparent: true,
                    opacity: 0.78,
                    depthWrite: false,
                    side: THREE.DoubleSide,
                }));
            } else if (hint === 'gold') {
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: origMap ? 0xfff6e0 : 0xd8b263,
                    metalness: origMap ? 0.72 : 0.9,
                    roughness: 0.21,
                    envMapIntensity: 1.35,
                    side: THREE.DoubleSide,
                }));
            } else if (hint === 'silver') {
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: origMap ? 0xffffff : 0xe5e9ef,
                    metalness: origMap ? 0.68 : 0.88,
                    roughness: 0.22,
                    envMapIntensity: 1.25,
                    side: THREE.DoubleSide,
                }));
            } else if (hint === 'slate') {
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: origMap ? 0xffffff : 0x262930,
                    metalness: 0.08,
                    roughness: 0.78,
                    envMapIntensity: 0.65,
                    side: THREE.DoubleSide,
                }));
            }
        });
    }

    _populateFramedPhotoExhibit(steleRecord) {
        const THREE = this.THREE;
        const { product, turntable } = steleRecord;
        const geos = this._sharedFrameGeos;

        const frameGroup = new THREE.Group();
        frameGroup.rotation.x = -0.14;

        frameGroup.add(new THREE.Mesh(geos.outer, this.mats.goldBrushed));
        frameGroup.add(new THREE.Mesh(geos.passepartout, this.mats.cofferRecess));
        frameGroup.add(new THREE.Mesh(geos.innerBevel, this.mats.goldChampagne));

        const photoMat = this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0xfaf8f5 }));
        const photoPlane = new THREE.Mesh(geos.photoPlane, photoMat);
        photoPlane.position.z = 0.018;
        frameGroup.add(photoPlane);

        const photoPlaneBack = new THREE.Mesh(geos.photoPlane, photoMat);
        photoPlaneBack.rotation.y = Math.PI;
        photoPlaneBack.position.z = -0.018;
        frameGroup.add(photoPlaneBack);

        const standFoot = new THREE.Mesh(geos.standFoot, this.mats.goldBrushed);
        standFoot.position.set(0, -0.23, -0.04);
        frameGroup.add(standFoot);

        this._clearPlaceholder(steleRecord);
        turntable.add(frameGroup);

        if (!product.preview_image_url) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                if (!this.destroyed) {
                    const scaled = downscaleImageToCanvas(img, 512);
                    const tex = this._trackDisposable(new THREE.Texture(scaled));
                    tex.generateMipmaps = true;
                    tex.minFilter = THREE.LinearMipmapLinearFilter;
                    tex.magFilter = THREE.LinearFilter;
                    tex.anisotropy = Math.min(this.maxAniso || 4, 4);
                    applySrgbColorSpace(THREE, tex);
                    tex.needsUpdate = true;
                    photoMat.map = tex;
                    photoMat.color.setHex(0xffffff);
                    photoMat.needsUpdate = true;
                }
                resolve();
            };
            img.onerror = () => resolve();
            img.src = product.preview_image_url;
        });
    }

    /**
     * Dedizierte 3D-Manufaktur-Skulptur fuer den Geschenkgutschein (C6-Format 162 x 114 mm -> 0.420 x 0.296 m):
     * Mattschwarzer Feinkarton-Umschlag (#141518) mit echten 3D-Klappen & Schattenfugen,
     * 4 diagonalen 24K-Gold-Zierleisten, plastischem 3D-Gold-Wachssiegel (Lorbeerkranz + 3D-Herz-Relief),
     * edler "MEIN SEELENFUNKE"-Rueckseitenpraegung und dezentem 24K-Gold-Aufsteller.
     */
    _populateVoucherEnvelope3D(steleRecord) {
        if (this.destroyed || !steleRecord || !steleRecord.turntable) {
            return Promise.resolve();
        }
        const THREE = this.THREE;
        const { turntable } = steleRecord;

        const W = 0.420;
        const H = 0.296;
        const halfW = W * 0.5;
        const halfH = H * 0.5;
        const sealY = -0.018;

        const paperBumpTex = buildFineCardstockBumpTexture(THREE, this.disposables);
        const matteBlackCardMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            color: 0x141518,
            roughness: 0.74,
            metalness: 0.06,
            bumpMap: paperBumpTex,
            bumpScale: 0.0018,
            envMapIntensity: 0.62,
        }));
        const flapSeamShadowMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            color: 0x08090c,
            roughness: 0.90,
            metalness: 0.02,
            envMapIntensity: 0.25,
        }));
        const waxCastGoldMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            color: 0xd6af54,
            roughness: 0.24,
            metalness: 0.90,
            envMapIntensity: 1.38,
        }));

        const backCrestTex = buildVoucherBackCrestTexture(THREE, this.maxAniso, this.disposables);
        const backCrestMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            map: backCrestTex,
            bumpMap: paperBumpTex,
            bumpScale: 0.0014,
            roughness: 0.66,
            metalness: 0.18,
            envMapIntensity: 0.85,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2,
        }));

        const voucherRoot = new THREE.Group();
        const envelopeGroup = new THREE.Group();
        envelopeGroup.rotation.x = -0.15;
        envelopeGroup.position.set(0, 0.014, 0.006);
        voucherRoot.add(envelopeGroup);

        // 1. Mattschwarzer Feinkarton-Korpus mit sanft gefasten Kanten (Tiefe ~ 0.012 m)
        const cornerR = 0.004;
        const bodyShape = new THREE.Shape();
        const bx = halfW - 0.0016;
        const by = halfH - 0.0016;
        bodyShape.moveTo(-bx + cornerR, -by);
        bodyShape.lineTo(bx - cornerR, -by);
        bodyShape.quadraticCurveTo(bx, -by, bx, -by + cornerR);
        bodyShape.lineTo(bx, by - cornerR);
        bodyShape.quadraticCurveTo(bx, by, bx - cornerR, by);
        bodyShape.lineTo(-bx + cornerR, by);
        bodyShape.quadraticCurveTo(-bx, by, -bx, by - cornerR);
        bodyShape.lineTo(-bx, -by + cornerR);
        bodyShape.quadraticCurveTo(-bx, -by, -bx + cornerR, -by);
        bodyShape.closePath();

        const bodyGeo = this._trackDisposable(new THREE.ExtrudeGeometry(bodyShape, {
            depth: 0.0088,
            bevelEnabled: true,
            bevelThickness: 0.0016,
            bevelSize: 0.0016,
            bevelSegments: 3,
            curveSegments: 8,
        }));
        bodyGeo.translate(0, 0, -0.0044);
        envelopeGroup.add(new THREE.Mesh(bodyGeo, matteBlackCardMat));

        // 2. Echte 3D-Umschlagklappen auf der Vorderseite (seitliche Faltflaechen + untere Tasche + obere V-Verschlussklappe)
        const leftSideShape = new THREE.Shape();
        leftSideShape.moveTo(-halfW + 0.0015, -halfH + 0.004);
        leftSideShape.lineTo(-0.026, sealY);
        leftSideShape.lineTo(-halfW + 0.0015, halfH - 0.004);
        leftSideShape.closePath();

        const rightSideShape = new THREE.Shape();
        rightSideShape.moveTo(halfW - 0.0015, halfH - 0.004);
        rightSideShape.lineTo(0.026, sealY);
        rightSideShape.lineTo(halfW - 0.0015, -halfH + 0.004);
        rightSideShape.closePath();

        const sideFlapOpts = {
            depth: 0.0009,
            bevelEnabled: true,
            bevelThickness: 0.00035,
            bevelSize: 0.00035,
            bevelSegments: 2,
        };
        const leftSideMesh = new THREE.Mesh(this._trackDisposable(new THREE.ExtrudeGeometry(leftSideShape, sideFlapOpts)), matteBlackCardMat);
        leftSideMesh.position.z = 0.0061;
        const rightSideMesh = new THREE.Mesh(this._trackDisposable(new THREE.ExtrudeGeometry(rightSideShape, sideFlapOpts)), matteBlackCardMat);
        rightSideMesh.position.z = 0.0061;
        envelopeGroup.add(leftSideMesh, rightSideMesh);

        const bottomFlapShape = new THREE.Shape();
        bottomFlapShape.moveTo(-halfW + 0.003, -halfH + 0.001);
        bottomFlapShape.lineTo(halfW - 0.003, -halfH + 0.001);
        bottomFlapShape.lineTo(halfW - 0.012, -halfH + 0.024);
        bottomFlapShape.lineTo(0.046, sealY + 0.022);
        bottomFlapShape.lineTo(-0.046, sealY + 0.022);
        bottomFlapShape.lineTo(-halfW + 0.012, -halfH + 0.024);
        bottomFlapShape.closePath();

        const bottomShadowGeo = this._trackDisposable(new THREE.ExtrudeGeometry(bottomFlapShape, {
            depth: 0.0005,
            bevelEnabled: false,
        }));
        const bottomShadowMesh = new THREE.Mesh(bottomShadowGeo, flapSeamShadowMat);
        bottomShadowMesh.position.set(0, 0.0016, 0.0070);
        envelopeGroup.add(bottomShadowMesh);

        const bottomFlapGeo = this._trackDisposable(new THREE.ExtrudeGeometry(bottomFlapShape, {
            depth: 0.0011,
            bevelEnabled: true,
            bevelThickness: 0.0004,
            bevelSize: 0.0004,
            bevelSegments: 2,
        }));
        const bottomFlapMesh = new THREE.Mesh(bottomFlapGeo, matteBlackCardMat);
        bottomFlapMesh.position.z = 0.0075;
        envelopeGroup.add(bottomFlapMesh);

        // Untere 2 diagonale 24K-Gold-Zierkanten (von den unteren Ecken bis exakt an die obere V-Klappe)
        const lowerGoldTrimOpts = {
            depth: 0.0014,
            bevelEnabled: true,
            bevelThickness: 0.00045,
            bevelSize: 0.00042,
            bevelSegments: 2,
        };
        const lowerTrimShapeL = buildMiteredRibbonShape(THREE, [
            [-halfW + 0.0038, -halfH + 0.0016],
            [-halfW + 0.0120, -halfH + 0.0240],
            [-0.0480, 0.0073],
        ], 0.0032);
        const lowerTrimShapeR = buildMiteredRibbonShape(THREE, [
            [0.0480, 0.0073],
            [halfW - 0.0120, -halfH + 0.0240],
            [halfW - 0.0038, -halfH + 0.0016],
        ], 0.0032);

        const lowerGoldMeshL = new THREE.Mesh(this._trackDisposable(new THREE.ExtrudeGeometry(lowerTrimShapeL, lowerGoldTrimOpts)), this.mats.goldChampagne);
        lowerGoldMeshL.position.z = 0.0083;
        const lowerGoldMeshR = new THREE.Mesh(this._trackDisposable(new THREE.ExtrudeGeometry(lowerTrimShapeR, lowerGoldTrimOpts)), this.mats.goldChampagne);
        lowerGoldMeshR.position.z = 0.0083;
        envelopeGroup.add(lowerGoldMeshL, lowerGoldMeshR);

        // Obere dreieckige V-Verschlussklappe (physisch leicht abgesetzt mit echter Schattenfuge)
        const upperTipY = sealY - 0.012;
        const upperFlapShape = new THREE.Shape();
        upperFlapShape.moveTo(0.0, upperTipY);
        upperFlapShape.lineTo(halfW - 0.012, halfH - 0.024);
        upperFlapShape.lineTo(halfW - 0.003, halfH - 0.001);
        upperFlapShape.lineTo(-halfW + 0.003, halfH - 0.001);
        upperFlapShape.lineTo(-halfW + 0.012, halfH - 0.024);
        upperFlapShape.closePath();

        const upperShadowGeo = this._trackDisposable(new THREE.ExtrudeGeometry(upperFlapShape, {
            depth: 0.0006,
            bevelEnabled: false,
        }));
        const upperShadowMesh = new THREE.Mesh(upperShadowGeo, flapSeamShadowMat);
        upperShadowMesh.position.set(0, -0.0022, 0.0096);
        envelopeGroup.add(upperShadowMesh);

        const upperFlapGeo = this._trackDisposable(new THREE.ExtrudeGeometry(upperFlapShape, {
            depth: 0.0013,
            bevelEnabled: true,
            bevelThickness: 0.00045,
            bevelSize: 0.00045,
            bevelSegments: 2,
        }));
        const upperFlapMesh = new THREE.Mesh(upperFlapGeo, matteBlackCardMat);
        upperFlapMesh.position.z = 0.0101;
        envelopeGroup.add(upperFlapMesh);

        // Obere 2 diagonale 24K-Gold-Zierleisten entlang der V-Verschlussklappe bis unter das Wachssiegel
        const upperGoldTrimOpts = {
            depth: 0.0015,
            bevelEnabled: true,
            bevelThickness: 0.00048,
            bevelSize: 0.00045,
            bevelSegments: 2,
        };
        const upperTrimShapeL = buildMiteredRibbonShape(THREE, [
            [-halfW + 0.0038, halfH - 0.0016],
            [-halfW + 0.0120, halfH - 0.0240],
            [0.0, upperTipY + 0.001],
        ], 0.0033);
        const upperTrimShapeR = buildMiteredRibbonShape(THREE, [
            [0.0, upperTipY + 0.001],
            [halfW - 0.0120, halfH - 0.0240],
            [halfW - 0.0038, halfH - 0.0016],
        ], 0.0033);

        const upperGoldMeshL = new THREE.Mesh(this._trackDisposable(new THREE.ExtrudeGeometry(upperTrimShapeL, upperGoldTrimOpts)), this.mats.goldChampagne);
        upperGoldMeshL.position.z = 0.0110;
        const upperGoldMeshR = new THREE.Mesh(this._trackDisposable(new THREE.ExtrudeGeometry(upperTrimShapeR, upperGoldTrimOpts)), this.mats.goldChampagne);
        upperGoldMeshR.position.z = 0.0110;
        envelopeGroup.add(upperGoldMeshL, upperGoldMeshR);

        // 4. Plastisches 3D-Gold-Wachssiegel (radius ~ 0.046 m) mittig auf der Klappenspitze (y = -0.018)
        const sealGroup = new THREE.Group();
        sealGroup.position.set(0.0, sealY, 0.0128);
        envelopeGroup.add(sealGroup);

        const waxOuterShape = new THREE.Shape();
        const waxSegs = 56;
        for (let wi = 0; wi <= waxSegs; wi++) {
            const ang = (wi / waxSegs) * TWO_PI;
            const organicR = 0.0445
                + 0.0016 * Math.sin(ang * 5.0 + 0.42)
                + 0.0011 * Math.cos(ang * 8.0 - 0.68)
                + 0.0006 * Math.sin(ang * 13.0 + 1.15);
            const wx = Math.cos(ang) * organicR;
            const wy = Math.sin(ang) * organicR;
            if (wi === 0) waxOuterShape.moveTo(wx, wy);
            else waxOuterShape.lineTo(wx, wy);
        }
        waxOuterShape.closePath();

        const waxDiscGeo = this._trackDisposable(new THREE.ExtrudeGeometry(waxOuterShape, {
            depth: 0.0026,
            bevelEnabled: true,
            bevelThickness: 0.0019,
            bevelSize: 0.0017,
            bevelSegments: 4,
        }));
        sealGroup.add(new THREE.Mesh(waxDiscGeo, waxCastGoldMat));

        const waxRimTorus = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(0.0392, 0.0046, 14, 52)),
            waxCastGoldMat
        );
        waxRimTorus.scale.set(1.015, 0.992, 0.80);
        waxRimTorus.position.z = 0.0034;
        sealGroup.add(waxRimTorus);

        const sealInnerMirror = new THREE.Mesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.0362, 0.0362, 0.0014, 44)),
            this.mats.goldBrushed
        );
        sealInnerMirror.rotation.x = Math.PI * 0.5;
        sealInnerMirror.position.z = 0.0036;
        sealGroup.add(sealInnerMirror);

        const sealInnerRing = new THREE.Mesh(
            this._trackDisposable(new THREE.TorusGeometry(0.0328, 0.0011, 10, 48)),
            this.mats.goldChampagne
        );
        sealInnerRing.position.z = 0.0046;
        sealGroup.add(sealInnerRing);

        // Zarter 3D-Lorbeerkranz links & rechts im Siegelrund
        const wreathR = 0.0256;
        const wreathArcSpan = Math.PI * 0.76;
        const stemGeo = this._trackDisposable(new THREE.TorusGeometry(wreathR, 0.00068, 8, 30, wreathArcSpan));
        const rightStem = new THREE.Mesh(stemGeo, this.mats.goldChampagne);
        rightStem.rotation.z = -wreathArcSpan * 0.5;
        rightStem.position.z = 0.0046;
        const leftStem = new THREE.Mesh(stemGeo, this.mats.goldChampagne);
        leftStem.rotation.z = Math.PI - wreathArcSpan * 0.5;
        leftStem.position.z = 0.0046;
        sealGroup.add(rightStem, leftStem);

        const leafShape = new THREE.Shape();
        leafShape.moveTo(0, 0);
        leafShape.quadraticCurveTo(0.0015, 0.0022, 0.0, 0.0058);
        leafShape.quadraticCurveTo(-0.0015, 0.0022, 0.0, 0);
        leafShape.closePath();
        const leafGeo = this._trackDisposable(new THREE.ExtrudeGeometry(leafShape, {
            depth: 0.0008,
            bevelEnabled: true,
            bevelThickness: 0.00032,
            bevelSize: 0.00028,
            bevelSegments: 2,
            curveSegments: 6,
        }));

        const branchSteps = 9;
        [-1, 1].forEach((side) => {
            for (let li = 0; li < branchSteps; li++) {
                const frac = (li + 0.45) / branchSteps;
                const theta = -Math.PI * 0.37 + frac * (Math.PI * 0.74);
                const ang = side > 0 ? theta : (Math.PI - theta);
                const bxLeaf = Math.cos(ang) * wreathR;
                const byLeaf = Math.sin(ang) * wreathR;
                const tangentDir = side > 0 ? (ang + Math.PI * 0.5) : (ang - Math.PI * 0.5);

                [-1, 1].forEach((splay) => {
                    if (li === branchSteps - 1 && splay < 0) return;
                    const leafMesh = new THREE.Mesh(leafGeo, this.mats.goldChampagne);
                    const splayAngle = (tangentDir - Math.PI * 0.5) + splay * side * 0.46;
                    leafMesh.position.set(bxLeaf, byLeaf, 0.0046);
                    leafMesh.rotation.set(0.08 * splay, 0, splayAngle);
                    const leafScale = 1.0 - frac * 0.18;
                    leafMesh.scale.setScalar(leafScale);
                    sealGroup.add(leafMesh);
                });
            }
        });

        const stemTieShape = buildMiteredRibbonShape(THREE, [
            [-0.0052, -wreathR - 0.0012],
            [0.0, -wreathR + 0.0012],
            [0.0052, -wreathR - 0.0012],
        ], 0.00075);
        const stemTieMesh = new THREE.Mesh(
            this._trackDisposable(new THREE.ExtrudeGeometry(stemTieShape, { depth: 0.0009, bevelEnabled: false })),
            this.mats.goldChampagne
        );
        stemTieMesh.position.z = 0.0047;
        sealGroup.add(stemTieMesh);

        // Plastisches 3D-Herz-Relief exakt in der Mitte des Siegels
        const heartCurveShape = new THREE.Shape();
        heartCurveShape.moveTo(0.0, -0.0124);
        heartCurveShape.bezierCurveTo(0.0038, -0.0046, 0.0112, 0.0026, 0.0088, 0.0098);
        heartCurveShape.bezierCurveTo(0.0066, 0.0136, 0.0016, 0.0112, 0.0, 0.0050);
        heartCurveShape.bezierCurveTo(-0.0016, 0.0112, -0.0066, 0.0136, -0.0088, 0.0098);
        heartCurveShape.bezierCurveTo(-0.0112, 0.0026, -0.0038, -0.0046, 0.0, -0.0124);
        heartCurveShape.closePath();

        const heartBaseReliefGeo = this._trackDisposable(new THREE.ExtrudeGeometry(heartCurveShape, {
            depth: 0.0008,
            bevelEnabled: true,
            bevelThickness: 0.0003,
            bevelSize: 0.00025,
            bevelSegments: 2,
            curveSegments: 14,
        }));
        const heartBaseMesh = new THREE.Mesh(heartBaseReliefGeo, waxCastGoldMat);
        heartBaseMesh.position.set(0, 0.0006, 0.0044);
        sealGroup.add(heartBaseMesh);

        const sampledPts = heartCurveShape.getPoints(36);
        const heartRibbonPts = [];
        for (let hi = 0; hi < sampledPts.length; hi++) {
            heartRibbonPts.push([sampledPts[hi].x, sampledPts[hi].y]);
        }
        if (heartRibbonPts.length > 2) {
            heartRibbonPts.push([heartRibbonPts[0][0], heartRibbonPts[0][1]]);
        }
        const heartOutlineShape = buildMiteredRibbonShape(THREE, heartRibbonPts, 0.00112);
        const heartOutlineGeo = this._trackDisposable(new THREE.ExtrudeGeometry(heartOutlineShape, {
            depth: 0.0017,
            bevelEnabled: true,
            bevelThickness: 0.00048,
            bevelSize: 0.00042,
            bevelSegments: 2,
        }));
        const heartOutlineMesh = new THREE.Mesh(heartOutlineGeo, this.mats.goldChampagne);
        heartOutlineMesh.position.set(0, 0.0006, 0.0048);
        sealGroup.add(heartOutlineMesh);

        // 5. Edle Rueckseite fuer die 360-Grad-Drehung auf dem Vitrinen-Drehteller
        const backPlateMesh = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(W - 0.009, H - 0.009)),
            backCrestMat
        );
        backPlateMesh.rotation.y = Math.PI;
        backPlateMesh.position.z = -0.0063;
        envelopeGroup.add(backPlateMesh);

        const backGoldBarH = this._trackDisposable(new THREE.BoxGeometry(W - 0.032, 0.0022, 0.0012));
        [-halfH + 0.015, halfH - 0.015].forEach((byBar) => {
            const bBar = new THREE.Mesh(backGoldBarH, this.mats.goldChampagne);
            bBar.position.set(0, byBar, -0.0065);
            envelopeGroup.add(bBar);
        });

        // 6. Feiner, dezenter 24K-Gold-Museums-Aufsteller (laesst die Rueckseitenpraegung in der Mitte zu 100 % frei!)
        const easelGroup = new THREE.Group();
        voucherRoot.add(easelGroup);

        const standBase = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.24, 0.011, 0.115)),
            this.mats.goldBrushed
        );
        standBase.position.set(0, -0.146, -0.014);
        easelGroup.add(standBase);

        const cradleCrossbar = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.22, 0.0065, 0.016)),
            this.mats.goldChampagne
        );
        cradleCrossbar.position.set(0, -0.135, 0.025);
        easelGroup.add(cradleCrossbar);

        const lipGeo = this._trackDisposable(new THREE.BoxGeometry(0.014, 0.016, 0.005));
        const rearStrutGeo = this._trackDisposable(new THREE.BoxGeometry(0.008, 0.092, 0.008));
        [-0.092, 0.092].forEach((sxStand) => {
            const frontLip = new THREE.Mesh(lipGeo, this.mats.goldChampagne);
            frontLip.position.set(sxStand, -0.128, 0.034);
            frontLip.rotation.x = -0.15;
            easelGroup.add(frontLip);

            const rearStrut = new THREE.Mesh(rearStrutGeo, this.mats.goldBrushed);
            rearStrut.position.set(sxStand, -0.102, -0.026);
            rearStrut.rotation.x = 0.44;
            easelGroup.add(rearStrut);
        });

        this._clearPlaceholder(steleRecord);
        turntable.add(voucherRoot);
        return Promise.resolve();
    }

    /**
     * Geteilter Geometrie- & Material-Pool fuer lokalen Avatar, 3D-Exoskelett & bis zu 19 Mitspieler pro Instanz (20er Hard-Cap).
     */
    _ensureAvatarSharedPool() {
        if (this._avatarShared) {
            return this._avatarShared;
        }
        const THREE = this.THREE;

        const mats = {
            exoObsidian: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x121620,
                roughness: 0.36,
                metalness: 0.28,
                envMapIntensity: 1.12,
            })),
            exoTitanium: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x242b38,
                roughness: 0.32,
                metalness: 0.64,
                envMapIntensity: 1.22,
            })),
            suitObsidian: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x121620,
                roughness: 0.32,
                metalness: 0.22,
                envMapIntensity: 1.15,
            })),
            suitDark: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x0d0f13,
                roughness: 0.38,
                metalness: 0.18,
                envMapIntensity: 1.02,
            })),
            gold24k: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xc5a059,
                roughness: 0.23,
                metalness: 0.90,
                envMapIntensity: 1.34,
            })),
            goldBright: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xe6c687,
                roughness: 0.18,
                metalness: 0.93,
                envMapIntensity: 1.40,
            })),
            jointDark: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x161b26,
                roughness: 0.40,
                metalness: 0.36,
                envMapIntensity: 0.96,
            })),
            thrusterGlow: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xfde68a,
            })),
            sapphireCrystal: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x1d8ac8,
                roughness: 0.08,
                metalness: 0.18,
                envMapIntensity: 1.35,
            })),
            emeraldCrystal: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x0d9468,
                roughness: 0.08,
                metalness: 0.18,
                envMapIntensity: 1.35,
            })),
            cosmosGold: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf59e0b,
                roughness: 0.14,
                metalness: 0.94,
                envMapIntensity: 1.48,
            })),
            exoIronUrsprung: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x2b2622,
                roughness: 0.54,
                metalness: 0.42,
                envMapIntensity: 0.88,
            })),
            exoBronzeUrsprung: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x9a7447,
                roughness: 0.44,
                metalness: 0.68,
                envMapIntensity: 0.96,
            })),
            ursprungLinenCore: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xa8947b,
                roughness: 0.78,
                metalness: 0.12,
                envMapIntensity: 0.72,
            })),
            seelenfunkeGold: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xfde047,
                emissive: 0xf59e0b,
                emissiveIntensity: 0.62,
                roughness: 0.14,
                metalness: 0.88,
                envMapIntensity: 1.45,
            })),
            seelenfunkeOrbit: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xfde047,
                transparent: true,
                opacity: 0.84,
                depthWrite: false,
            })),
            seelenfunkeWhiteCore: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xffffff,
            })),
            rubyPotionCore: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xdc2626,
                emissive: 0x991b1b,
                emissiveIntensity: 0.65,
                roughness: 0.12,
                metalness: 0.16,
                envMapIntensity: 1.35,
            })),
            rubyPotionGlass: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xfca5a5,
                transparent: true,
                opacity: 0.46,
                depthWrite: false,
            })),
        };

        // 1:1 Website-Seelenfunken 4-Strahlen-Gold-Kristall-Stern:
        // Pfad: 0,1 -> 0.22,0.22 -> 1,0 -> 0.22,-0.22 -> 0,-1 -> -0.22,-0.22 -> -1,0 -> -0.22,0.22
        const sfStarShape = new THREE.Shape();
        sfStarShape.moveTo(0, 1);
        sfStarShape.lineTo(0.22, 0.22);
        sfStarShape.lineTo(1, 0);
        sfStarShape.lineTo(0.22, -0.22);
        sfStarShape.lineTo(0, -1);
        sfStarShape.lineTo(-0.22, -0.22);
        sfStarShape.lineTo(-1, 0);
        sfStarShape.lineTo(-0.22, 0.22);
        sfStarShape.closePath();
        const sfStarGeo = this._trackDisposable(new THREE.ExtrudeGeometry(sfStarShape, {
            depth: 0.16,
            bevelEnabled: true,
            bevelThickness: 0.045,
            bevelSize: 0.04,
            bevelSegments: 2,
        }));
        sfStarGeo.translate(0, 0, -0.08);
        sfStarGeo.scale(0.22, 0.22, 0.22);

        const geos = {
            seelenfunkeStar: sfStarGeo,
            seelenfunkeCore: this._trackDisposable(new THREE.OctahedronGeometry(0.068, 1)),
            seelenfunkeOrbitA: this._trackDisposable(new THREE.TorusGeometry(0.26, 0.0085, 8, 32)),
            seelenfunkeOrbitB: this._trackDisposable(new THREE.TorusGeometry(0.31, 0.0070, 8, 32)),
            potionVialBulb: this._trackDisposable(new THREE.SphereGeometry(0.064, 14, 12)),
            potionVialShell: this._trackDisposable(new THREE.SphereGeometry(0.074, 14, 12)),
            potionVialNeck: this._trackDisposable(new THREE.CylinderGeometry(0.024, 0.032, 0.056, 10)),
            footShadow: this._trackDisposable(new THREE.PlaneGeometry(1.18, 1.18)),
            baseRing: this._trackDisposable(new THREE.RingGeometry(0.34, 0.385, 32)),
            beltRing: this._trackDisposable(new THREE.TorusGeometry(0.215, 0.022, 10, 28)),
            buckleGem: this._trackDisposable(new THREE.OctahedronGeometry(0.026, 0)),
            exoSpineRail: this._trackDisposable(new THREE.BoxGeometry(0.056, 0.44, 0.048)),
            exoVertebra: this._trackDisposable(new THREE.BoxGeometry(0.092, 0.036, 0.062)),
            exoReactorHex: this._trackDisposable(new THREE.CylinderGeometry(0.115, 0.128, 0.068, 6)),
            exoReactorRing: this._trackDisposable(new THREE.TorusGeometry(0.096, 0.013, 8, 6)),
            exoReactorCore: this._trackDisposable(new THREE.OctahedronGeometry(0.048, 0)),
            exoShoulderYoke: this._trackDisposable(new THREE.BoxGeometry(0.055, 0.045, 0.22)),
            exoRibBrace: this._trackDisposable(new THREE.TorusGeometry(0.205, 0.014, 8, 22, Math.PI * 0.85)),
            exoPauldron: this._trackDisposable(new THREE.CylinderGeometry(0.068, 0.092, 0.11, 8, 1, false, 0, Math.PI * 1.15)),
            exoPistonSleeve: this._trackDisposable(new THREE.CylinderGeometry(0.017, 0.017, 0.15, 10)),
            exoPistonRod: this._trackDisposable(new THREE.CylinderGeometry(0.009, 0.009, 0.21, 8)),
            exoArmStrut: this._trackDisposable(new THREE.BoxGeometry(0.028, 0.22, 0.036)),
            exoElbowServo: this._trackDisposable(new THREE.CylinderGeometry(0.042, 0.042, 0.038, 14)),
            exoForearmGuard: this._trackDisposable(new THREE.BoxGeometry(0.048, 0.19, 0.056)),
            exoHipPod: this._trackDisposable(new THREE.CylinderGeometry(0.048, 0.048, 0.052, 14)),
            exoThighStrut: this._trackDisposable(new THREE.BoxGeometry(0.034, 0.30, 0.048)),
            exoKneeServo: this._trackDisposable(new THREE.CylinderGeometry(0.048, 0.048, 0.046, 14)),
            exoKneePlate: this._trackDisposable(new THREE.BoxGeometry(0.078, 0.092, 0.052)),
            exoShinDamper: this._trackDisposable(new THREE.CylinderGeometry(0.022, 0.018, 0.28, 10)),
            exoMagBootFrame: this._trackDisposable(new THREE.BoxGeometry(0.132, 0.068, 0.215)),
            exoHeelNozzle: this._trackDisposable(new THREE.CylinderGeometry(0.024, 0.034, 0.065, 12)),
            exoThrusterFlame: this._trackDisposable(new THREE.ConeGeometry(0.032, 0.14, 10)),
            thighStripe: this._trackDisposable(new THREE.BoxGeometry(0.012, 0.28, 0.018)),
            bootCuff: this._trackDisposable(new THREE.TorusGeometry(0.056, 0.011, 8, 18)),
            bootSole: this._trackDisposable(new THREE.BoxGeometry(0.136, 0.020, 0.228)),
            lapel: this._trackDisposable(new THREE.BoxGeometry(0.030, 0.17, 0.016)),
            earCap: this._trackDisposable(new THREE.CylinderGeometry(0.036, 0.036, 0.024, 12)),
            crestSmall: this._trackDisposable(new THREE.OctahedronGeometry(0.034, 0)),
            crestMaster: this._trackDisposable(new THREE.OctahedronGeometry(0.048, 0)),
            crownHalo: this._trackDisposable(new THREE.TorusGeometry(0.11, 0.010, 8, 24)),
            shoulderTrim: this._trackDisposable(new THREE.TorusGeometry(0.064, 0.009, 8, 18)),
            cuffRing: this._trackDisposable(new THREE.TorusGeometry(0.044, 0.009, 8, 18)),
            palm: this._trackDisposable(new THREE.BoxGeometry(0.040, 0.066, 0.064)),
            dorsalPlate: this._trackDisposable(new THREE.BoxGeometry(0.044, 0.050, 0.050)),
            thumb: this._trackDisposable(new THREE.CylinderGeometry(0.010, 0.012, 0.042, 8)),
            finger: this._trackDisposable(new THREE.CylinderGeometry(0.009, 0.011, 0.048, 8)),
            weaponHilt: this._trackDisposable(new THREE.CylinderGeometry(0.013, 0.015, 0.16, 10)),
            weaponGuard: this._trackDisposable(new THREE.BoxGeometry(0.13, 0.022, 0.032)),
            weaponBlade: this._trackDisposable(new THREE.ConeGeometry(0.028, 0.56, 4)),
            weaponStaffShaft: this._trackDisposable(new THREE.CylinderGeometry(0.012, 0.014, 0.72, 10)),
            weaponStaffOrb: this._trackDisposable(new THREE.OctahedronGeometry(0.055, 0)),
            weaponBowArc: this._trackDisposable(new THREE.TorusGeometry(0.24, 0.013, 8, 20, Math.PI * 0.92)),
            weaponChord: this._trackDisposable(new THREE.CylinderGeometry(0.003, 0.003, 0.46, 6)),
            slashArc: this._trackDisposable(new THREE.RingGeometry(0.58, 0.94, 24, 1, -Math.PI * 0.44, Math.PI * 0.88)),
            whirlRing: this._trackDisposable(new THREE.RingGeometry(1.18, 1.85, 36)),
            shockwaveRing: this._trackDisposable(new THREE.RingGeometry(0.62, 1.0, 40)),
            sparkDiamond: this._trackDisposable(new THREE.OctahedronGeometry(0.038, 0)),
        };

        this._avatarShared = {
            mats,
            geos,
        };
        return this._avatarShared;
    }

    /**
     * Anatomisches 22-Bone THREE.SkinnedMesh Auto-Rigging fuer alle echten Kunden-Level .glb-Modelle (Lv. 1..10):
     * - Entfernt programmatisch alle enthaltenen Boden-/Sockel-Meshes (stripFunkiPedestalAndNormalizeGeometry + .dispose())
     * - Kalibriert die Fusssohlen millimetergenau auf Y = 0.000 m
     * - Aktiviert Hand- & Finger-Bones (handL/R, fingersL/R) sowie eine 3-Gliedrige Spring-Bone-Kette fuer den Umhang (capeUpper/Mid/Lower)!
     */
    _loadRiggedFunkiTemplate(rawLevel, customModelUrl) {
        const THREE = this.THREE;
        const lvl = clamp(parseInt(rawLevel, 10) || 1, 1, 10);
        const url = this._sanitizeStandingFunkiUrl(lvl, customModelUrl || this._resolveFunkiModelUrl(lvl, null));
        const cacheKey = `${lvl}:${url}`;

        if (this.skinnedFunkiCache.has(cacheKey)) {
            return Promise.resolve(this.skinnedFunkiCache.get(cacheKey));
        }
        if (this.skinnedFunkiPromises.has(cacheKey)) {
            return this.skinnedFunkiPromises.get(cacheKey);
        }

        const fetchOpts = { method: 'GET', credentials: 'same-origin' };
        if (this.abortController) {
            fetchOpts.signal = this.abortController.signal;
        }

        const smoothstep = (e0, e1, v) => {
            const t = clamp((v - e0) / Math.max(0.0001, e1 - e0), 0, 1);
            return t * t * (3.0 - 2.0 * t);
        };

        const promise = fetch(url, fetchOpts)
            .then((resp) => {
                if (!resp.ok) throw new Error(`Funki GLB HTTP ${resp.status}`);
                return resp.arrayBuffer();
            })
            .then((buf) => parseGlbBinary(THREE, buf, this.maxAniso, this.disposables))
            .then((parsed) => {
                const stripped = stripFunkiPedestalAndNormalizeGeometry(THREE, parsed.scene);
                const riggedGeo = stripped.geometry;
                const H = stripped.height || 1.62;

                const posAttr = riggedGeo.attributes.position;
                const vCount = posAttr.count;
                const skinIndices = new Uint16Array(vCount * 4);
                const skinWeights = new Float32Array(vCount * 4);
                const wBuf = new Float32Array(22);

                // 22-Bone Index-Belegung (1:1 auf Exoskelett, Haende/Finger & Umhang kalibriert):
                // 0: root, 1: pelvis, 2: spine, 3: chest, 4: head,
                // 5: shoulderL, 6: foreArmL, 7: handL, 8: fingersL,
                // 9: shoulderR, 10: foreArmR, 11: handR, 12: fingersR,
                // 13: hipL, 14: kneeL, 15: footL, 16: hipR, 17: kneeR, 18: footR,
                // 19: capeUpper, 20: capeMid, 21: capeLower
                for (let vi = 0; vi < vCount; vi++) {
                    const vx = posAttr.getX(vi);
                    const vy = posAttr.getY(vi);
                    const vz = posAttr.getZ(vi);
                    const u = clamp(vy / H, 0, 1);
                    const ax = Math.abs(vx) / H;

                    wBuf.fill(0);

                    // Punkt 16: Chibi-kalibriertes 22-Bone-Skinning (0 % Gesichts- & Schulter-Verzerrungen!)
                    // 1. Arm-, Hand- & Finger-Zone strikt unterhalb des Kopfes (0.28 <= u <= 0.53, |x| > 0.165 * H)
                    let wArmTotal = 0;
                    if (u >= 0.28 && u <= 0.53 && ax > 0.165) {
                        const armEnterY = smoothstep(0.28, 0.35, u);
                        const armExitY = 1.0 - smoothstep(0.47, 0.53, u);
                        const armLat = smoothstep(0.165, 0.225, ax);
                        const backWingGuard = (vz < -0.062 * H && u > 0.45)
                            ? (1.0 - smoothstep(0.062 * H, 0.130 * H, -vz))
                            : 1.0;
                        wArmTotal = armEnterY * armExitY * armLat * backWingGuard;

                        if (wArmTotal > 0.0001) {
                            const distalAmt = clamp(
                                Math.max(smoothstep(0.22, 0.35, ax), 1.0 - smoothstep(0.32, 0.50, u)),
                                0,
                                1
                            );
                            const fingerFrac = smoothstep(0.76, 0.94, distalAmt);
                            const handFrac = (1.0 - fingerFrac) * smoothstep(0.54, 0.76, distalAmt);
                            const foreFrac = (1.0 - fingerFrac - handFrac) * smoothstep(0.24, 0.54, distalAmt);
                            const shFrac = Math.max(0, 1.0 - fingerFrac - handFrac - foreFrac);

                            if (vx < 0) {
                                wBuf[5] += wArmTotal * shFrac;
                                wBuf[6] += wArmTotal * foreFrac;
                                wBuf[7] += wArmTotal * handFrac;
                                wBuf[8] += wArmTotal * fingerFrac;
                            } else {
                                wBuf[9] += wArmTotal * shFrac;
                                wBuf[10] += wArmTotal * foreFrac;
                                wBuf[11] += wArmTotal * handFrac;
                                wBuf[12] += wArmTotal * fingerFrac;
                            }
                        }
                    }

                    // 2. Umhang-Zone strikt unterhalb der Kapuze/Kopfkante (vz < -0.072 * H, 0.08 <= u <= 0.53)
                    let wCapeTotal = 0;
                    if (vz < -0.072 * H && u >= 0.08 && u <= 0.53 && ax <= 0.24) {
                        const capeDepth = smoothstep(0.072 * H, 0.135 * H, -vz);
                        const capeYMask = smoothstep(0.08, 0.15, u) * (1.0 - smoothstep(0.46, 0.53, u));
                        const capeLatMask = 1.0 - smoothstep(0.19, 0.24, ax);
                        wCapeTotal = capeDepth * capeYMask * capeLatMask * (1.0 - wArmTotal) * 0.90;
                        if (wCapeTotal > 0.0001) {
                            const lowerFrac = 1.0 - smoothstep(0.18, 0.31, u);
                            const midFrac = (1.0 - lowerFrac) * (1.0 - smoothstep(0.34, 0.45, u));
                            const upperFrac = Math.max(0, 1.0 - lowerFrac - midFrac);
                            wBuf[19] += wCapeTotal * upperFrac;
                            wBuf[20] += wCapeTotal * midFrac;
                            wBuf[21] += wCapeTotal * lowerFrac;
                        }
                    }

                    // 3. Bein-Zone (u < 0.40) mit Mitten-Saumschutz & Umhang-Freistellung
                    let wLegTotal = 0;
                    if (u < 0.40) {
                        const legY = 1.0 - smoothstep(0.32, 0.40, u);
                        const seamPelvis = 1.0 - smoothstep(0.016, 0.072, ax);
                        wLegTotal = legY * (1.0 - seamPelvis) * Math.max(0, 1.0 - wArmTotal - wCapeTotal);

                        if (wLegTotal > 0.0001) {
                            const fFrac = 1.0 - smoothstep(0.045, 0.110, u);
                            const kFrac = (1.0 - fFrac) * (1.0 - smoothstep(0.17, 0.26, u));
                            const hFrac = Math.max(0, 1.0 - fFrac - kFrac);

                            if (vx < 0) {
                                wBuf[13] += wLegTotal * hFrac;
                                wBuf[14] += wLegTotal * kFrac;
                                wBuf[15] += wLegTotal * fFrac;
                            } else {
                                wBuf[16] += wLegTotal * hFrac;
                                wBuf[17] += wLegTotal * kFrac;
                                wBuf[18] += wLegTotal * fFrac;
                            }
                        }
                    }

                    // 4. Starre Kopf-Zone (ab u >= 0.58 ueber ALLE ax zu 100 % starr auf Bone 4 head, sanfter Halsuebergang 0.52..0.58!)
                    let wHead = 0;
                    if (u > 0.52) {
                        const headY = u >= 0.58 ? 1.0 : smoothstep(0.52, 0.58, u);
                        wHead = headY * Math.max(0, 1.0 - wArmTotal - wCapeTotal);
                        wBuf[4] += wHead;
                    }

                    // 5. Rumpf-Kern (pelvis -> spine -> chest)
                    const coreRem = Math.max(0, 1.0 - wLegTotal - wArmTotal - wCapeTotal - wHead);
                    if (coreRem > 0.0001) {
                        const toSpine = smoothstep(0.35, 0.44, u);
                        const toChest = smoothstep(0.45, 0.53, u);
                        const wPelvis = coreRem * (1.0 - toSpine);
                        const wSpine = coreRem * toSpine * (1.0 - toChest);
                        const wChest = coreRem * toSpine * toChest;
                        wBuf[1] += wPelvis;
                        wBuf[2] += wSpine;
                        wBuf[3] += wChest;
                    }

                    // Top-4 Knochengewichte selektieren & exakt auf Summe 1.0 normieren
                    const topIdx = [1, 0, 0, 0];
                    const topVal = [0, 0, 0, 0];
                    for (let bi = 0; bi < 22; bi++) {
                        const w = wBuf[bi];
                        if (w <= topVal[3]) continue;
                        if (w > topVal[0]) {
                            topVal[3] = topVal[2]; topIdx[3] = topIdx[2];
                            topVal[2] = topVal[1]; topIdx[2] = topIdx[1];
                            topVal[1] = topVal[0]; topIdx[1] = topIdx[0];
                            topVal[0] = w; topIdx[0] = bi;
                        } else if (w > topVal[1]) {
                            topVal[3] = topVal[2]; topIdx[3] = topIdx[2];
                            topVal[2] = topVal[1]; topIdx[2] = topIdx[1];
                            topVal[1] = w; topIdx[1] = bi;
                        } else if (w > topVal[2]) {
                            topVal[3] = topVal[2]; topIdx[3] = topIdx[2];
                            topVal[2] = w; topIdx[2] = bi;
                        } else {
                            topVal[3] = w; topIdx[3] = bi;
                        }
                    }

                    const sumW = topVal[0] + topVal[1] + topVal[2] + topVal[3];
                    const base4 = vi * 4;
                    if (sumW > 0.00001) {
                        const inv = 1.0 / sumW;
                        skinIndices[base4] = topIdx[0];
                        skinIndices[base4 + 1] = topIdx[1];
                        skinIndices[base4 + 2] = topIdx[2];
                        skinIndices[base4 + 3] = topIdx[3];
                        const nw0 = topVal[0] * inv;
                        const nw1 = topVal[1] * inv;
                        const nw2 = topVal[2] * inv;
                        const nw3 = Math.max(0, 1.0 - (nw0 + nw1 + nw2));
                        skinWeights[base4] = nw0;
                        skinWeights[base4 + 1] = nw1;
                        skinWeights[base4 + 2] = nw2;
                        skinWeights[base4 + 3] = nw3;
                    } else {
                        skinIndices[base4] = 1;
                        skinIndices[base4 + 1] = 0;
                        skinIndices[base4 + 2] = 0;
                        skinIndices[base4 + 3] = 0;
                        skinWeights[base4] = 1.0;
                        skinWeights[base4 + 1] = 0.0;
                        skinWeights[base4 + 2] = 0.0;
                        skinWeights[base4 + 3] = 0.0;
                    }
                }

                riggedGeo.setAttribute('skinIndex', new THREE.Uint16BufferAttribute(skinIndices, 4));
                riggedGeo.setAttribute('skinWeight', new THREE.Float32BufferAttribute(skinWeights, 4));
                this._trackDisposable(riggedGeo);

                // Ermittle automatisch das reale Handflaechen-/Faust-Zentrum der anatomisch RECHTEN Hand (-X Seite, da Funki nach +Z blickt!)
                let minRightArmX = 0;
                for (let vi = 0; vi < vCount; vi++) {
                    const vx = posAttr.getX(vi);
                    const vy = posAttr.getY(vi);
                    const vz = posAttr.getZ(vi);
                    if (vx < -0.20 && vy >= 0.34 && vy <= 0.88 && vz >= -0.14 * H) {
                        const b4 = vi * 4;
                        let rArmW = 0;
                        for (let k = 0; k < 4; k++) {
                            const bi = skinIndices[b4 + k];
                            if (bi >= 5 && bi <= 8) rArmW += skinWeights[b4 + k];
                        }
                        if (rArmW > 0.25 && vx < minRightArmX) {
                            minRightArmX = vx;
                        }
                    }
                }

                const accumulateRightPalmCluster = (xMaxThreshold) => {
                    let sumW = 0;
                    let sx = 0;
                    let sy = 0;
                    let sz = 0;
                    const accBoneW = [0, 0, 0, 0];
                    for (let vi = 0; vi < vCount; vi++) {
                        const vx = posAttr.getX(vi);
                        const vy = posAttr.getY(vi);
                        const vz = posAttr.getZ(vi);
                        if (vx < xMaxThreshold && vx <= minRightArmX + 0.115 && vy >= 0.34 && vy <= 0.88 && vz >= -0.14 * H) {
                            const b4 = vi * 4;
                            let w5 = 0;
                            let w6 = 0;
                            let w7 = 0;
                            let w8 = 0;
                            for (let k = 0; k < 4; k++) {
                                const bi = skinIndices[b4 + k];
                                const bw = skinWeights[b4 + k];
                                if (bi === 5) w5 += bw;
                                else if (bi === 6) w6 += bw;
                                else if (bi === 7) w7 += bw;
                                else if (bi === 8) w8 += bw;
                            }
                            const armSum = w5 + w6 + w7 + w8;
                            if (armSum > 0.30) {
                                const palmBias = 0.28 + w7 * 1.15 + w8 * 1.35;
                                sx += vx * palmBias;
                                sy += vy * palmBias;
                                sz += vz * palmBias;
                                accBoneW[0] += (w5 / armSum) * palmBias;
                                accBoneW[1] += (w6 / armSum) * palmBias;
                                accBoneW[2] += (w7 / armSum) * palmBias;
                                accBoneW[3] += (w8 / armSum) * palmBias;
                                sumW += palmBias;
                            }
                        }
                    }
                    return { sumW, sx, sy, sz, accBoneW };
                };

                // Primaer: Vertex-Cluster der aeusseren rechten Hand (X < -0.32, Y in [0.34, 0.88]), mit Fallback fuer schmale Chibi-Modelle
                let palmScan = accumulateRightPalmCluster(-0.32);
                if (palmScan.sumW < 0.001 && minRightArmX < -0.20) {
                    palmScan = accumulateRightPalmCluster(Math.min(-0.22, minRightArmX + 0.10));
                }

                let rightHandPalmBind = { x: -0.345, y: 0.505, z: 0.016 };
                let rightHandSkinWeights = new Float32Array([0.04, 0.20, 0.54, 0.22]);
                if (palmScan.sumW > 0.001) {
                    const invS = 1.0 / palmScan.sumW;
                    rightHandPalmBind = {
                        x: palmScan.sx * invS,
                        y: palmScan.sy * invS,
                        z: palmScan.sz * invS,
                    };
                    const bwTotal = palmScan.accBoneW[0] + palmScan.accBoneW[1] + palmScan.accBoneW[2] + palmScan.accBoneW[3];
                    if (bwTotal > 0.0001) {
                        rightHandSkinWeights = new Float32Array([
                            palmScan.accBoneW[0] / bwTotal,
                            palmScan.accBoneW[1] / bwTotal,
                            palmScan.accBoneW[2] / bwTotal,
                            palmScan.accBoneW[3] / bwTotal,
                        ]);
                    }
                }

                const tpl = {
                    level: lvl,
                    height: H,
                    geometry: riggedGeo,
                    material: stripped.material,
                    rightHandPalmBind,
                    rightHandSkinWeights,
                };
                this.skinnedFunkiCache.set(cacheKey, tpl);
                this.skinnedFunkiPromises.delete(cacheKey);
                return tpl;
            })
            .catch((err) => {
                this.skinnedFunkiPromises.delete(cacheKey);
                throw err;
            });

        this.skinnedFunkiPromises.set(cacheKey, promise);
        return promise;
    }

    _createSkinnedFunkiFromTemplate(tpl, customDisposableBucket = null) {
        const THREE = this.THREE;
        const H = tpl.height || 1.62;
        const restPelvisY = 0.58;

        // 22-Bone Hierarchie exakt auf Funkis echte Chibi-Proportionen (Hals bei y = 0.94 / u = 0.58, Schultern bei y = 0.82) kalibriert!
        const bRoot = new THREE.Bone(); bRoot.name = 'root'; bRoot.position.set(0, 0, 0);
        const bPelvis = new THREE.Bone(); bPelvis.name = 'pelvis'; bPelvis.position.set(0, restPelvisY, 0);
        const bSpine = new THREE.Bone(); bSpine.name = 'spine'; bSpine.position.set(0, 0.13, 0);
        const bChest = new THREE.Bone(); bChest.name = 'chest'; bChest.position.set(0, 0.12, 0);
        const bHead = new THREE.Bone(); bHead.name = 'head'; bHead.position.set(0, 0.11, 0);

        const bShoulderL = new THREE.Bone(); bShoulderL.name = 'shoulderL'; bShoulderL.position.set(-0.24, -0.01, -0.01);
        const bForeArmL = new THREE.Bone(); bForeArmL.name = 'foreArmL'; bForeArmL.position.set(-0.030, -0.160, 0);
        const bHandL = new THREE.Bone(); bHandL.name = 'handL'; bHandL.position.set(-0.012, -0.140, 0);
        const bFingersL = new THREE.Bone(); bFingersL.name = 'fingersL'; bFingersL.position.set(0, -0.050, 0.004);

        const bShoulderR = new THREE.Bone(); bShoulderR.name = 'shoulderR'; bShoulderR.position.set(0.24, -0.01, -0.01);
        const bForeArmR = new THREE.Bone(); bForeArmR.name = 'foreArmR'; bForeArmR.position.set(0.030, -0.160, 0);
        const bHandR = new THREE.Bone(); bHandR.name = 'handR'; bHandR.position.set(0.012, -0.140, 0);
        const bFingersR = new THREE.Bone(); bFingersR.name = 'fingersR'; bFingersR.position.set(0, -0.050, 0.004);

        const bHipL = new THREE.Bone(); bHipL.name = 'hipL'; bHipL.position.set(-0.140, -0.020, 0);
        const bKneeL = new THREE.Bone(); bKneeL.name = 'kneeL'; bKneeL.position.set(0, -0.270, 0);
        const bFootL = new THREE.Bone(); bFootL.name = 'footL'; bFootL.position.set(0, -0.250, 0.025);

        const bHipR = new THREE.Bone(); bHipR.name = 'hipR'; bHipR.position.set(0.140, -0.020, 0);
        const bKneeR = new THREE.Bone(); bKneeR.name = 'kneeR'; bKneeR.position.set(0, -0.270, 0);
        const bFootR = new THREE.Bone(); bFootR.name = 'footR'; bFootR.position.set(0, -0.250, 0.025);

        const bCapeUpper = new THREE.Bone(); bCapeUpper.name = 'capeUpper'; bCapeUpper.position.set(0, 0.010, -0.095);
        const bCapeMid = new THREE.Bone(); bCapeMid.name = 'capeMid'; bCapeMid.position.set(0, -0.260, -0.020);
        const bCapeLower = new THREE.Bone(); bCapeLower.name = 'capeLower'; bCapeLower.position.set(0, -0.280, -0.020);

        bRoot.add(bPelvis);
        bPelvis.add(bSpine, bHipL, bHipR);
        bSpine.add(bChest);
        bChest.add(bHead, bShoulderL, bShoulderR, bCapeUpper);
        bShoulderL.add(bForeArmL);
        bForeArmL.add(bHandL);
        bHandL.add(bFingersL);
        bShoulderR.add(bForeArmR);
        bForeArmR.add(bHandR);
        bHandR.add(bFingersR);
        bHipL.add(bKneeL);
        bKneeL.add(bFootL);
        bHipR.add(bKneeR);
        bKneeR.add(bFootR);
        bCapeUpper.add(bCapeMid);
        bCapeMid.add(bCapeLower);

        const bones = [
            bRoot, bPelvis, bSpine, bChest, bHead,
            bShoulderL, bForeArmL, bHandL, bFingersL,
            bShoulderR, bForeArmR, bHandR, bFingersR,
            bHipL, bKneeL, bFootL, bHipR, bKneeR, bFootR,
            bCapeUpper, bCapeMid, bCapeLower,
        ];

        let matInstance = tpl.material;
        if (matInstance && typeof matInstance.clone === 'function') {
            matInstance = matInstance.clone();
            if (Array.isArray(customDisposableBucket)) {
                customDisposableBucket.push(matInstance);
            } else {
                this._trackDisposable(matInstance);
            }
            matInstance.skinning = true;
            if ('envMapIntensity' in matInstance) {
                matInstance.envMapIntensity = tpl.level === 1 ? 0.96 : 1.24;
            }
            if (tpl.level === 1 && !matInstance.map && matInstance.color) {
                matInstance.color.set('#A8947B');
            }
            matInstance.needsUpdate = true;
        }

        const skinnedMesh = new THREE.SkinnedMesh(tpl.geometry, matInstance);
        skinnedMesh.frustumCulled = false;
        skinnedMesh.add(bRoot);
        skinnedMesh.updateMatrixWorld(true);

        const skeleton = new THREE.Skeleton(bones);
        skinnedMesh.bind(skeleton);

        // Echter Hand-Socket fuer die goldene Klinge direkt an der anatomisch RECHTEN Hand (-X Seite, bones[5..8] handL/fingersL) des SkinnedMesh!
        const palmBind = tpl.rightHandPalmBind || { x: -0.345, y: 0.505, z: 0.016 };
        const palmWeights = tpl.rightHandSkinWeights || new Float32Array([0.04, 0.20, 0.54, 0.22]);
        const rightHandWeaponSocket = new THREE.Group();
        rightHandWeaponSocket.name = 'rightHandWeaponSocket';
        rightHandWeaponSocket.position.set(palmBind.x, palmBind.y, palmBind.z);
        skinnedMesh.add(rightHandWeaponSocket);

        // Weiches THREE.AnimationMixer-State-System (0.18s..0.28s Crossfade zwischen Idle, Run, Jump & Cast; 0 T-Pose via mixer.update(0))
        let mixer = null;
        const actions = {};
        if (THREE.AnimationMixer && THREE.AnimationClip && THREE.VectorKeyframeTrack) {
            mixer = new THREE.AnimationMixer(skinnedMesh);
            const makeStateClip = (name, dur, yPeak) => new THREE.AnimationClip(name, dur, [
                new THREE.VectorKeyframeTrack('.scale', [0, dur * 0.5, dur], [1, 1, 1, 1, yPeak, 1, 1, 1, 1]),
            ]);
            actions.idle = mixer.clipAction(makeStateClip('idle', 2.4, 1.006));
            actions.run = mixer.clipAction(makeStateClip('run', 0.64, 1.012));
            actions.jump = mixer.clipAction(makeStateClip('jump', 0.90, 1.016));
            actions.cast = mixer.clipAction(makeStateClip('cast', 0.52, 1.020));
            actions.idle.play();
            mixer.update(0);
        }

        return {
            skinnedMesh,
            skeleton,
            bones,
            rightHandWeaponSocket,
            rightHandPalmBind: palmBind,
            rightHandSkinWeights: palmWeights,
            mixer,
            actions,
            activeState: 'idle',
            capeAngles: new Float32Array([0.055, 0.065, 0.075]),
            capeVels: new Float32Array(3),
            capeYawAngles: new Float32Array(3),
            capeYawVels: new Float32Array(3),
            restPelvisY,
            height: H,
            level: tpl.level,
        };
    }

    _crossfadeSkinnedRigState(skinnedRig, nextState, fadeSec = 0.22) {
        if (!skinnedRig || !skinnedRig.mixer || !skinnedRig.actions) return;
        const cleanNext = skinnedRig.actions[nextState] ? nextState : 'idle';
        if (skinnedRig.activeState === cleanNext) return;

        const prevAct = skinnedRig.actions[skinnedRig.activeState];
        const nextAct = skinnedRig.actions[cleanNext];
        if (!nextAct) return;

        const dur = clamp(fadeSec, 0.18, 0.28);
        nextAct.reset();
        nextAct.setEffectiveTimeScale(1);
        nextAct.setEffectiveWeight(1);
        nextAct.play();
        if (prevAct && prevAct !== nextAct) {
            prevAct.crossFadeTo(nextAct, dur, false);
        }
        skinnedRig.activeState = cleanNext;
    }

    /**
     * Allokationsfreie 3-Segment Spring-Bone-Simulation fuer den Umhang:
     * Faellt im Stand natuerlich der Schwerkraft folgend nach unten, weht bei Lauf/Sprung/Drehung realistisch mit
     * und klemmt strikt >= +0.04 rad (0 % Clipping durch Ruecken oder Beine!).
     */
    _updateCapeSpringPhysics(skinnedRig, dt, speed = 0, vy = 0, turnRate = 0) {
        if (!skinnedRig || !skinnedRig.bones || skinnedRig.bones.length < 22) return;
        const stepDt = clamp(dt || 0.016, 0.001, 0.04);
        const speedNorm = clamp((speed || 0) / 8.0, 0, 1.0);
        const fallLift = vy < -0.2 ? clamp((-vy) * 0.042, 0, 0.34) : (vy > 0.4 ? 0.06 : 0.0);
        const flutter = Math.sin(this.elapsedTime * (3.2 + speedNorm * 8.5) + (skinnedRig.level || 1)) * (0.012 + speedNorm * 0.048);
        const targetYawBase = clamp(-(turnRate || 0) * 0.042, -0.26, 0.26);

        const stiffness = 36.0;
        const damping = 8.4;

        for (let si = 0; si < 3; si++) {
            const segWeight = (si === 0) ? 0.58 : (si === 1 ? 0.85 : 1.08);
            const restGravity = 0.055 + si * 0.012;
            const targetPitch = clamp(
                restGravity + (speedNorm * 0.34 + fallLift) * segWeight + flutter * (si * 0.45 + 0.35),
                0.04,
                0.56
            );
            const curP = skinnedRig.capeAngles[si];
            const accP = (targetPitch - curP) * stiffness - skinnedRig.capeVels[si] * damping;
            skinnedRig.capeVels[si] += accP * stepDt;
            const nextP = clamp(curP + skinnedRig.capeVels[si] * stepDt, 0.04, 0.58);
            skinnedRig.capeAngles[si] = nextP;

            const targetY = targetYawBase * (0.45 + si * 0.32);
            const curY = skinnedRig.capeYawAngles[si];
            const accY = (targetY - curY) * 28.0 - skinnedRig.capeYawVels[si] * 7.5;
            skinnedRig.capeYawVels[si] += accY * stepDt;
            const nextY = clamp(curY + skinnedRig.capeYawVels[si] * stepDt, -0.32, 0.32);
            skinnedRig.capeYawAngles[si] = nextY;

            const bone = skinnedRig.bones[19 + si];
            bone.rotation.set(nextP, nextY, -nextY * 0.42);
        }
    }

    _syncSkinnedFunkiBones(skinnedRig, j, somersaultTuck = 0, kinMeta = null) {
        if (!skinnedRig || !skinnedRig.bones || !j) return;
        const b = skinnedRig.bones;
        const legSync = 0.84;
        const armSync = 0.84;
        const tuck = clamp(somersaultTuck || 0, 0, 1);
        const wallClingBlend = (kinMeta && typeof kinMeta.wallClingBlend === 'number')
            ? clamp(kinMeta.wallClingBlend, 0, 1)
            : 0;
        const minShoulderRx = -1.15 - 0.22 * wallClingBlend;
        const maxShoulderRx = 0.78;
        const maxHeadRy = 0.52 + 0.12 * wallClingBlend;

        // 1: pelvis
        const pelvisDy = Math.max(-0.02, (j.pelvisPivot.position.y - 0.776));
        b[1].position.y = skinnedRig.restPelvisY + pelvisDy;
        b[1].position.x = (j.pelvisPivot.position.x || 0) * 0.88;
        b[1].position.z = (j.pelvisPivot.position.z || 0) * 0.88;
        b[1].rotation.set(
            j.pelvisPivot.rotation.x,
            clamp(j.pelvisPivot.rotation.y * 0.85, -0.65, 0.65),
            clamp(j.pelvisPivot.rotation.z * 0.82, -0.45, 0.45)
        );

        // 2: spine & 3: chest
        const torsoRx = j.torsoPivot.rotation.x + 0.18 * tuck;
        b[2].rotation.set(
            clamp(torsoRx * 0.46, -0.58, 0.72),
            clamp(j.torsoPivot.rotation.y * 0.46, -0.52, 0.52),
            clamp(j.torsoPivot.rotation.z * 0.46, -0.36, 0.36)
        );
        b[3].rotation.set(
            clamp(torsoRx * 0.50, -0.58, 0.72),
            clamp(j.torsoPivot.rotation.y * 0.50, -0.58, 0.58),
            clamp(j.torsoPivot.rotation.z * 0.50, -0.38, 0.38)
        );

        // 4: head (sanft geklemmt fuer 100 % verzerrungsfreies Chibi-Gesicht)
        b[4].rotation.set(
            clamp(j.headPivot.rotation.x * 0.62 + 0.14 * tuck, -0.34, 0.42),
            clamp(j.headPivot.rotation.y * (0.68 + 0.14 * wallClingBlend), -maxHeadRy, maxHeadRy),
            clamp(j.headPivot.rotation.z * 0.55, -0.22, 0.22)
        );

        // 5..8: Left Arm + Hand + Fingers
        b[5].rotation.set(
            clamp(j.shoulderPivotL.rotation.x * (0.68 + 0.12 * wallClingBlend), minShoulderRx, maxShoulderRx),
            clamp(j.shoulderPivotL.rotation.y * 0.68, -0.48, 0.48),
            clamp(j.shoulderPivotL.rotation.z * 0.64, -0.58, 0.58)
        );
        b[6].rotation.set(clamp(j.elbowPivotL.rotation.x * 0.68, -1.10, 0.36), 0, 0);
        if (b[7] && j.wristPivotL) {
            b[7].rotation.set(
                clamp(j.wristPivotL.rotation.x * 0.85, -0.65, 0.65),
                clamp((j.wristPivotL.rotation.y || 0) * 0.85, -0.55, 0.55),
                clamp(j.wristPivotL.rotation.z * 0.85, -0.65, 0.65)
            );
        }
        if (b[8] && j.fingerCurlL) {
            b[8].rotation.set(
                clamp((j.fingerCurlL.rotation.x || 0) * 0.88, -0.85, 0.85),
                0,
                clamp(j.fingerCurlL.rotation.z * 0.88, -0.92, 0.92)
            );
        }

        // 9..12: Right Arm + Hand + Fingers
        b[9].rotation.set(
            clamp(j.shoulderPivotR.rotation.x * (0.68 + 0.12 * wallClingBlend), minShoulderRx, maxShoulderRx),
            clamp(j.shoulderPivotR.rotation.y * 0.68, -0.48, 0.48),
            clamp(j.shoulderPivotR.rotation.z * 0.64, -0.58, 0.58)
        );
        b[10].rotation.set(clamp(j.elbowPivotR.rotation.x * 0.68, -1.10, 0.36), 0, 0);
        if (b[11] && j.wristPivotR) {
            b[11].rotation.set(
                clamp(j.wristPivotR.rotation.x * 0.85, -0.65, 0.65),
                clamp((j.wristPivotR.rotation.y || 0) * 0.85, -0.55, 0.55),
                clamp(j.wristPivotR.rotation.z * 0.85, -0.65, 0.65)
            );
        }
        if (b[12] && j.fingerCurlR) {
            b[12].rotation.set(
                clamp((j.fingerCurlR.rotation.x || 0) * 0.88, -0.85, 0.85),
                0,
                clamp(j.fingerCurlR.rotation.z * 0.88, -0.92, 0.92)
            );
        }

        // 13..15: Left Leg & 16..18: Right Leg
        b[13].rotation.set(
            clamp(j.hipPivotL.rotation.x * legSync - 0.22 * tuck, -1.55, 0.85),
            0,
            clamp(j.hipPivotL.rotation.z * 0.72, -0.28, 0.28)
        );
        b[14].rotation.set(clamp(j.kneePivotL.rotation.x * legSync + 0.25 * tuck, 0, 1.85), 0, 0);
        b[15].rotation.set(clamp(j.anklePivotL.rotation.x * 0.76, -0.58, 0.52), 0, 0);

        b[16].rotation.set(
            clamp(j.hipPivotR.rotation.x * legSync - 0.22 * tuck, -1.55, 0.85),
            0,
            clamp(j.hipPivotR.rotation.z * 0.72, -0.28, 0.28)
        );
        b[17].rotation.set(clamp(j.kneePivotR.rotation.x * legSync + 0.25 * tuck, 0, 1.85), 0, 0);
        b[18].rotation.set(clamp(j.anklePivotR.rotation.x * 0.76, -0.58, 0.52), 0, 0);

        const dt = (kinMeta && kinMeta.dt) ? kinMeta.dt : 0.016;
        const speed = (kinMeta && kinMeta.speed) ? kinMeta.speed : 0;
        const vy = (kinMeta && kinMeta.vy) ? kinMeta.vy : 0;
        const turnRate = (kinMeta && kinMeta.turnRate) ? kinMeta.turnRate : 0;
        const animState = (kinMeta && kinMeta.animState) ? kinMeta.animState : 'idle';
        const sprintBoost = (kinMeta && typeof kinMeta.sprintBoost === 'number')
            ? clamp(kinMeta.sprintBoost, 0, 1.0)
            : clamp((speed - 5.2) / 3.6, 0, 1.0);

        this._crossfadeSkinnedRigState(skinnedRig, animState, 0.22);
        if (skinnedRig.actions && skinnedRig.actions.run && skinnedRig.activeState === 'run') {
            const runTimeScale = lerp(1.0, 1.69, sprintBoost > 0.01 ? sprintBoost : clamp((speed / 5.2 - 1.0) / 0.69, 0, 1.0));
            skinnedRig.actions.run.setEffectiveTimeScale(runTimeScale);
        }
        if (skinnedRig.mixer) {
            skinnedRig.mixer.update(dt);
        }
        this._updateCapeSpringPhysics(skinnedRig, dt, speed, vy, turnRate);

        // Punkt 6: Transformiere das reale rechte Handflaechen-/Faust-Zentrum (-X Seite, bones[5..8]) exakt mit bone.matrixWorld (0 Drift, 0 Allokationen!)
        if (skinnedRig.rightHandWeaponSocket && skinnedRig.skeleton && skinnedRig.skinnedMesh && this.THREE) {
            if (!this._handSocketScratch) {
                const THREE = this.THREE;
                this._handSocketScratch = {
                    meshInv: new THREE.Matrix4(),
                    boneDelta: new THREE.Matrix4(),
                    rotExtract: new THREE.Matrix4(),
                    bindPt: new THREE.Vector3(),
                    tmpPt: new THREE.Vector3(),
                    accumPt: new THREE.Vector3(),
                };
            }
            const sc = this._handSocketScratch;
            skinnedRig.skinnedMesh.updateMatrixWorld(true);
            sc.meshInv.copy(skinnedRig.skinnedMesh.matrixWorld).invert();

            const pb = skinnedRig.rightHandPalmBind || { x: -0.345, y: 0.505, z: 0.016 };
            const sw = skinnedRig.rightHandSkinWeights || [0.04, 0.20, 0.54, 0.22];
            sc.bindPt.set(pb.x, pb.y, pb.z);
            sc.accumPt.set(0, 0, 0);

            const inverses = skinnedRig.skeleton.boneInverses;
            for (let k = 0; k < 4; k++) {
                const wk = sw[k] || 0;
                if (wk <= 0.0001) continue;
                const bIdx = 5 + k;
                const bone = b[bIdx];
                const bInv = inverses[bIdx];
                if (!bone || !bInv) continue;
                sc.boneDelta.multiplyMatrices(sc.meshInv, bone.matrixWorld).multiply(bInv);
                sc.tmpPt.copy(sc.bindPt).applyMatrix4(sc.boneDelta);
                sc.accumPt.addScaledVector(sc.tmpPt, wk);
            }
            skinnedRig.rightHandWeaponSocket.position.copy(sc.accumPt);

            const handBone = b[7] || b[6];
            const handInv = inverses[7] || inverses[6];
            if (handBone && handInv) {
                sc.boneDelta.multiplyMatrices(sc.meshInv, handBone.matrixWorld).multiply(handInv);
                sc.rotExtract.extractRotation(sc.boneDelta);
                skinnedRig.rightHandWeaponSocket.quaternion.setFromRotationMatrix(sc.rotExtract);
            }
        }
    }

    /**
     * Erzeugt den Funki-Charakter (Stufe 1 bis 10) mit edlem 3D-Exoskelett, Hand-/Finger-Gelenken,
     * 22-Bone SkinnedMesh, Umhang-Spring-Physik und 0 Roboter-Fallback-Meshes!
     */
    _createArticulatedAvatarInstance(level, weaponInfo, isLocalPlayer = false, customModelUrl = null, customDisposableBucket = null) {
        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const { mats, geos } = shared;
        const lvl = clamp(parseInt(level, 10) || 1, 1, 10);
        const isUrsprung = (lvl === 1);
        const exoPrimaryMat = isUrsprung ? mats.exoIronUrsprung : mats.exoObsidian;
        const exoSecondaryMat = isUrsprung ? mats.exoIronUrsprung : mats.exoTitanium;
        const exoTrimMat = isUrsprung ? mats.exoBronzeUrsprung : mats.gold24k;

        const root = new THREE.Group();
        // Bleibt unsichtbar, bis das echte Level-GLB inkl. Idle-Pose vollstaendig bereit ist (0 T-Pose, 0 Pop-in!)
        root.visible = false;

        const footShadow = new THREE.Mesh(geos.footShadow, this.mats.contactShadow);
        footShadow.rotation.x = -Math.PI * 0.5;
        footShadow.position.y = 0.002;
        root.add(footShadow);

        const baseRing = new THREE.Mesh(geos.baseRing, this.mats.avatarBaseRing || this.mats.goldMatcapBrushed);
        baseRing.rotation.x = -Math.PI * 0.5;
        baseRing.position.y = 0.002;
        root.add(baseRing);

        const skinnedHostGroup = new THREE.Group();
        skinnedHostGroup.position.y = 0.004;
        root.add(skinnedHostGroup);

        const pelvisPivot = new THREE.Group();
        pelvisPivot.position.set(0, 0.776, 0);
        root.add(pelvisPivot);

        const gyroBelt = new THREE.Mesh(geos.beltRing, exoSecondaryMat);
        gyroBelt.rotation.x = Math.PI * 0.5;
        gyroBelt.scale.set(1.06, 0.92, 1.0);
        gyroBelt.position.set(0, -0.02, -0.01);
        pelvisPivot.add(gyroBelt);

        const gyroGoldTrim = new THREE.Mesh(geos.beltRing, exoTrimMat);
        gyroGoldTrim.rotation.x = Math.PI * 0.5;
        gyroGoldTrim.scale.set(1.09, 0.94, 0.65);
        gyroGoldTrim.position.set(0, -0.02, -0.01);
        pelvisPivot.add(gyroGoldTrim);

        const buckleGem = new THREE.Mesh(geos.buckleGem, isUrsprung ? mats.exoBronzeUrsprung : mats.goldBright);
        buckleGem.scale.set(1.05, 1.2, 0.5);
        buckleGem.position.set(0, -0.02, 0.202);
        pelvisPivot.add(buckleGem);

        const buildExoLeg = (sideSign) => {
            const hipPivot = new THREE.Group();
            hipPivot.position.set(sideSign * 0.145, -0.04, 0);
            pelvisPivot.add(hipPivot);

            const hipPod = new THREE.Mesh(geos.exoHipPod, exoSecondaryMat);
            hipPod.rotation.z = Math.PI * 0.5;
            hipPod.position.set(sideSign * 0.062, 0, 0);
            const hipGoldCap = new THREE.Mesh(geos.earCap, exoTrimMat);
            hipGoldCap.rotation.z = Math.PI * 0.5;
            hipGoldCap.position.set(sideSign * 0.090, 0, 0);

            const exoThighStrut = new THREE.Mesh(geos.exoThighStrut, exoPrimaryMat);
            exoThighStrut.position.set(sideSign * 0.064, -0.165, 0);
            const exoThighPiston = new THREE.Mesh(geos.exoPistonRod, exoTrimMat);
            exoThighPiston.position.set(sideSign * 0.078, -0.165, -0.020);
            hipPivot.add(hipPod, hipGoldCap, exoThighStrut, exoThighPiston);

            const kneePivot = new THREE.Group();
            kneePivot.position.set(0, -0.33, 0);
            hipPivot.add(kneePivot);

            const kneeServo = new THREE.Mesh(geos.exoKneeServo, exoTrimMat);
            kneeServo.rotation.z = Math.PI * 0.5;
            kneeServo.position.set(sideSign * 0.064, 0, 0);
            const kneeArmor = new THREE.Mesh(geos.exoKneePlate, exoSecondaryMat);
            kneeArmor.position.set(sideSign * 0.026, 0.01, 0.064);

            const shinDamper = new THREE.Mesh(geos.exoShinDamper, exoSecondaryMat);
            shinDamper.position.set(sideSign * 0.060, -0.155, -0.010);
            const shinGoldStrut = new THREE.Mesh(geos.thighStripe, exoTrimMat);
            shinGoldStrut.position.set(sideSign * 0.074, -0.155, 0.012);
            kneePivot.add(kneeServo, kneeArmor, shinDamper, shinGoldStrut);

            const anklePivot = new THREE.Group();
            anklePivot.position.set(0, -0.32, 0);
            kneePivot.add(anklePivot);

            const bootCuff = new THREE.Mesh(geos.bootCuff, exoTrimMat);
            bootCuff.rotation.x = Math.PI * 0.5;
            bootCuff.position.y = -0.008;

            const magBootFrame = new THREE.Mesh(geos.exoMagBootFrame, exoPrimaryMat);
            magBootFrame.position.set(0, -0.038, 0.028);

            const bootSole = new THREE.Mesh(geos.bootSole, exoTrimMat);
            bootSole.position.set(0, -0.072, 0.030);

            const heelNozzle = new THREE.Mesh(geos.exoHeelNozzle, exoSecondaryMat);
            heelNozzle.rotation.x = -0.28;
            heelNozzle.position.set(0, -0.026, -0.092);

            const thrusterFlame = new THREE.Mesh(geos.exoThrusterFlame, mats.thrusterGlow);
            thrusterFlame.rotation.x = Math.PI - 0.28;
            thrusterFlame.position.set(0, -0.095, -0.112);
            thrusterFlame.visible = false;

            anklePivot.add(bootCuff, magBootFrame, bootSole, heelNozzle, thrusterFlame);
            return { hipPivot, kneePivot, anklePivot, thrusterFlame };
        };

        const legL = buildExoLeg(-1);
        const legR = buildExoLeg(1);

        const torsoPivot = new THREE.Group();
        torsoPivot.position.set(0, 0.05, 0);
        pelvisPivot.add(torsoPivot);

        const exoBackFrame = new THREE.Group();
        exoBackFrame.position.set(0, 0.20, -0.195);
        torsoPivot.add(exoBackFrame);

        const spineRail = new THREE.Mesh(geos.exoSpineRail, exoPrimaryMat);
        exoBackFrame.add(spineRail);

        for (let vi = 0; vi < 5; vi++) {
            const vert = new THREE.Mesh(geos.exoVertebra, vi % 2 === 0 ? exoSecondaryMat : exoTrimMat);
            vert.position.set(0, -0.16 + vi * 0.08, -0.015);
            exoBackFrame.add(vert);
        }

        const reactorHousing = new THREE.Mesh(geos.exoReactorHex, exoPrimaryMat);
        reactorHousing.rotation.x = Math.PI * 0.5;
        reactorHousing.position.set(0, 0.08, -0.042);
        exoBackFrame.add(reactorHousing);

        const reactorRing = new THREE.Mesh(geos.exoReactorRing, isUrsprung ? mats.exoBronzeUrsprung : mats.goldBright);
        reactorRing.position.set(0, 0.08, -0.078);
        exoBackFrame.add(reactorRing);

        const reactorCore = new THREE.Mesh(geos.exoReactorCore, mats.thrusterGlow);
        reactorCore.scale.set(1.0, 1.0, 0.55);
        reactorCore.position.set(0, 0.08, -0.082);
        exoBackFrame.add(reactorCore);

        [-1, 1].forEach((sSign) => {
            const yoke = new THREE.Mesh(geos.exoShoulderYoke, exoSecondaryMat);
            yoke.position.set(sSign * 0.16, 0.20, 0.085);
            yoke.rotation.x = 0.18;
            yoke.rotation.y = sSign * 0.16;
            exoBackFrame.add(yoke);

            const yokeTrim = new THREE.Mesh(geos.lapel, exoTrimMat);
            yokeTrim.rotation.x = Math.PI * 0.5;
            yokeTrim.position.set(sSign * 0.16, 0.226, 0.095);
            exoBackFrame.add(yokeTrim);
        });

        const ribBrace = new THREE.Mesh(geos.exoRibBrace, exoTrimMat);
        ribBrace.rotation.x = Math.PI * 0.5;
        ribBrace.rotation.z = -Math.PI * 0.92;
        ribBrace.position.set(0, 0.14, 0.0);
        torsoPivot.add(ribBrace);

        const neckPivot = new THREE.Group();
        neckPivot.position.set(0, 0.435, 0);
        torsoPivot.add(neckPivot);

        const headPivot = new THREE.Group();
        headPivot.position.set(0, 0.068, 0);
        neckPivot.add(headPivot);

        const sparkCrest = new THREE.Mesh(
            lvl >= 8 ? geos.crestMaster : geos.crestSmall,
            isUrsprung ? mats.exoBronzeUrsprung : mats.goldBright
        );
        sparkCrest.position.set(0, 0.46, 0.0);
        headPivot.add(sparkCrest);

        const buildExoArm = (sideSign) => {
            const shoulderPivot = new THREE.Group();
            shoulderPivot.position.set(sideSign * 0.252, 0.365, -0.01);
            torsoPivot.add(shoulderPivot);

            const pauldron = new THREE.Mesh(geos.exoPauldron, exoPrimaryMat);
            pauldron.rotation.z = sideSign * -0.45;
            pauldron.rotation.y = sideSign > 0 ? 0 : Math.PI;
            pauldron.position.set(sideSign * 0.024, 0.015, 0);

            const shoulderTrim = new THREE.Mesh(geos.shoulderTrim, exoTrimMat);
            shoulderTrim.rotation.y = Math.PI * 0.5;
            shoulderTrim.rotation.x = 0.35;
            shoulderTrim.position.set(sideSign * 0.018, 0.01, 0);

            const armStrut = new THREE.Mesh(geos.exoArmStrut, exoSecondaryMat);
            armStrut.position.set(sideSign * 0.042, -0.115, 0);

            const pistonA = new THREE.Mesh(geos.exoPistonSleeve, exoPrimaryMat);
            pistonA.position.set(sideSign * 0.056, -0.09, 0.016);
            const pistonRodA = new THREE.Mesh(geos.exoPistonRod, exoTrimMat);
            pistonRodA.position.set(sideSign * 0.056, -0.12, 0.016);

            const pistonB = new THREE.Mesh(geos.exoPistonRod, exoTrimMat);
            pistonB.position.set(sideSign * 0.056, -0.12, -0.016);

            shoulderPivot.add(pauldron, shoulderTrim, armStrut, pistonA, pistonRodA, pistonB);

            const elbowPivot = new THREE.Group();
            elbowPivot.position.set(0, -0.23, 0);
            shoulderPivot.add(elbowPivot);

            const elbowServo = new THREE.Mesh(geos.exoElbowServo, exoTrimMat);
            elbowServo.rotation.z = Math.PI * 0.5;
            elbowServo.position.set(sideSign * 0.040, 0, 0);

            const forearmGuard = new THREE.Mesh(geos.exoForearmGuard, exoPrimaryMat);
            forearmGuard.position.set(sideSign * 0.034, -0.105, 0);

            const cuffRing = new THREE.Mesh(geos.cuffRing, exoTrimMat);
            cuffRing.rotation.x = Math.PI * 0.5;
            cuffRing.position.set(sideSign * 0.014, -0.20, 0);
            elbowPivot.add(elbowServo, forearmGuard, cuffRing);

            const wristPivot = new THREE.Group();
            wristPivot.position.set(sideSign * 0.014, -0.21, 0);
            elbowPivot.add(wristPivot);

            const palm = new THREE.Mesh(geos.palm, exoSecondaryMat);
            palm.position.set(0, -0.034, 0.004);
            const dorsalPlate = new THREE.Mesh(geos.dorsalPlate, exoTrimMat);
            dorsalPlate.position.set(sideSign * 0.008, -0.032, 0.004);

            const thumbPivot = new THREE.Group();
            thumbPivot.position.set(-sideSign * 0.012, -0.026, 0.028);
            const thumb = new THREE.Mesh(geos.thumb, isUrsprung ? mats.exoBronzeUrsprung : mats.goldBright);
            thumb.position.set(0, -0.016, 0.010);
            thumb.rotation.x = -0.52;
            thumb.rotation.z = -sideSign * 0.24;
            thumbPivot.add(thumb);

            const fingerCurl = new THREE.Group();
            fingerCurl.position.set(0, -0.064, 0.004);
            const zOffsets = [0.020, 0.007, -0.006, -0.019];
            const lengths = [0.96, 1.04, 0.98, 0.84];
            for (let fi = 0; fi < 4; fi++) {
                const fMesh = new THREE.Mesh(geos.finger, exoTrimMat);
                fMesh.scale.y = lengths[fi];
                fMesh.position.set(sideSign * 0.004, -0.022 * lengths[fi], zOffsets[fi]);
                fMesh.rotation.z = sideSign * 0.16;
                fingerCurl.add(fMesh);
            }

            wristPivot.add(palm, dorsalPlate, thumbPivot, fingerCurl);
            return { shoulderPivot, elbowPivot, wristPivot, thumbPivot, fingerCurl };
        };

        const armL = buildExoArm(-1);
        const armR = buildExoArm(1);

        // Punkt 7: Saemtliche prozeduralen Roboter-/Exoskelett-Meshes (Stiefelsohlen, Roboterfuesse, Schienen, Haende usw.)
        // standardmaessig ausblenden, sodass ausschliesslich das echte Funki-.glb (+ optionale Waffe & Bodenschatten) sichtbar ist!
        const proceduralExoMeshes = [];
        pelvisPivot.traverse((node) => {
            if (node && node.isMesh) {
                node.visible = false;
                proceduralExoMeshes.push(node);
            }
        });

        const weaponMesh = null;

        const builtRecord = {
            root,
            skinnedHostGroup,
            weaponMesh,
            proceduralExoMeshes,
            skinnedRig: null,
            readyPromise: null,
            joints: {
                footShadow,
                baseRing,
                pelvisPivot,
                torsoPivot,
                neckPivot,
                headPivot,
                sparkCrest,
                reactorRing,
                reactorCore,
                thrusterFlameL: legL.thrusterFlame,
                thrusterFlameR: legR.thrusterFlame,
                hipPivotL: legL.hipPivot,
                kneePivotL: legL.kneePivot,
                anklePivotL: legL.anklePivot,
                hipPivotR: legR.hipPivot,
                kneePivotR: legR.kneePivot,
                anklePivotR: legR.anklePivot,
                shoulderPivotL: armL.shoulderPivot,
                elbowPivotL: armL.elbowPivot,
                wristPivotL: armL.wristPivot,
                thumbPivotL: armL.thumbPivot,
                fingerCurlL: armL.fingerCurl,
                shoulderPivotR: armR.shoulderPivot,
                elbowPivotR: armR.elbowPivot,
                wristPivotR: armR.wristPivot,
                thumbPivotR: armR.thumbPivot,
                fingerCurlR: armR.fingerCurl,
            },
        };

        const resolvedUrl = this._sanitizeStandingFunkiUrl(lvl, customModelUrl || this._resolveFunkiModelUrl(lvl, null));
        const genAtCreate = this._monumentGeneration;
        builtRecord.readyPromise = this._loadRiggedFunkiTemplate(lvl, resolvedUrl)
            .then((tpl) => {
                if (this.destroyed || !this.THREE) return null;
                if (Array.isArray(customDisposableBucket) && genAtCreate !== this._monumentGeneration) return null;
                const rig = this._createSkinnedFunkiFromTemplate(tpl, customDisposableBucket);
                if (builtRecord.weaponMesh && rig.rightHandWeaponSocket) {
                    rig.rightHandWeaponSocket.add(builtRecord.weaponMesh);
                }
                skinnedHostGroup.add(rig.skinnedMesh);
                builtRecord.skinnedRig = rig;
                this._syncSkinnedFunkiBones(rig, builtRecord.joints, 0, { dt: 0.016, speed: 0, vy: 0, turnRate: 0, animState: 'idle' });
                if (rig.mixer) {
                    rig.mixer.update(0);
                }
                if (!isLocalPlayer || this._engineReady) {
                    root.visible = isLocalPlayer ? !(this.camControl && this.camControl.distance < 0.55) : true;
                }
                return rig;
            })
            .catch(() => {
                // Nur falls das GLB offline/im Test nicht geladen werden konnte, Notfall-Meshes + -X Hand einblenden
                if (builtRecord.weaponMesh && armL && armL.wristPivot) {
                    armL.wristPivot.add(builtRecord.weaponMesh);
                }
                for (let mi = 0; mi < proceduralExoMeshes.length; mi++) {
                    const pm = proceduralExoMeshes[mi];
                    if (pm !== legL.thrusterFlame && pm !== legR.thrusterFlame) {
                        pm.visible = true;
                    }
                }
                if (!isLocalPlayer || this._engineReady) {
                    root.visible = isLocalPlayer ? !(this.camControl && this.camControl.distance < 0.55) : true;
                }
                return null;
            });

        return builtRecord;
    }

    _buildPlayerCharacter() {
        const lvl = clamp(parseInt(this.playerProfile.level, 10) || 1, 1, 10);
        const rawPlayerUrl = this.playerProfile.modelUrl
            || (this.playerFunki && (this.playerFunki.glb_url || this.playerFunki.model_url))
            || this._resolveFunkiModelUrl(lvl, null);
        const playerModelUrl = this._sanitizeStandingFunkiUrl(lvl, rawPlayerUrl);

        const built = this._createArticulatedAvatarInstance(lvl, null, true, playerModelUrl);
        built.root.visible = false;
        built.root.position.set(this.player.x, this.player.y, this.player.z);
        built.root.rotation.y = this.player.yaw;
        this.scene.add(built.root);

        this.player.builtRecord = built;
        this.player.root = built.root;
        this.player.joints = built.joints;

        return built.readyPromise || Promise.resolve();
    }

    upgradePlayerModel(rawNewLevel, rawNewModelUrl = null) {
        if (this.destroyed || !this.THREE || !this.player.builtRecord) {
            return Promise.resolve(false);
        }
        const newLvl = clamp(parseInt(rawNewLevel, 10) || this.playerProfile.level || 1, 1, 10);
        const nextUrl = this._sanitizeStandingFunkiUrl(newLvl, rawNewModelUrl || DEFAULT_FUNKI_GLB_URLS[newLvl]);

        this.playerProfile.level = newLvl;
        this.playerProfile.levelTitle = FUNKI_LEVEL_TITLES[newLvl] || this.playerProfile.levelTitle;
        this.playerProfile.modelUrl = nextUrl;
        this.playerFunki.level = newLvl;
        this.playerFunki.glb_url = nextUrl;
        this.playerFunki.model_url = nextUrl;
        this._emitPlayerHudUpdate();

        return this._loadRiggedFunkiTemplate(newLvl, nextUrl)
            .then((tpl) => {
                if (this.destroyed || !this.player.builtRecord) return false;
                const host = this.player.builtRecord.skinnedHostGroup;
                const prevRig = this.player.builtRecord.skinnedRig;
                const newRig = this._createSkinnedFunkiFromTemplate(tpl);
                if (this.player.builtRecord.weaponMesh && newRig.rightHandWeaponSocket) {
                    newRig.rightHandWeaponSocket.add(this.player.builtRecord.weaponMesh);
                }
                if (host) {
                    host.add(newRig.skinnedMesh);
                }
                if (prevRig && prevRig.skinnedMesh && host) {
                    host.remove(prevRig.skinnedMesh);
                }
                this._syncSkinnedFunkiBones(newRig, this.player.joints, 0, {
                    dt: 0.016,
                    speed: 0,
                    vy: 0,
                    turnRate: 0,
                    animState: 'idle',
                });
                if (newRig.mixer) newRig.mixer.update(0);

                this.player.builtRecord.skinnedRig = newRig;
                this._spawnAbilityBurst(this.player.x, (this.player.y || 0) + 0.14, this.player.z, 'highJump');
                return true;
            })
            .catch(() => false);
    }

    _computeFloorBaseY(x, z) {
        // 0. Erhabener Saphir-Samtlaeufer mit 24K-Goldkanten (Oberkante 0.036 m)
        const carpetSouthZ = this.hallNorthZ + 1.65;
        const carpetNorthZ = this.pantheonCenterZ + 18.65;
        if (Math.abs(x) <= 1.41 && z <= carpetSouthZ && z >= carpetNorthZ) {
            return 0.036;
        }
        // 1. Halle der Helden (Nord-Rotunde, 46.8 m Breite -> bis |x| <= 25.0 m): feste Marmorboden-Standhoehe 0.026 m!
        const heroesRotundaHalfX = Math.max(this.pantheonHalfW || 23.4, 23.4) + 1.6;
        if (z <= this.heroesEntryZ + 0.6 && Math.abs(x) <= heroesRotundaHalfX) {
            return 0.026;
        }
        // 2. Sanfte Rampe auf der Ehrenpromenade zwischen Produktgalerie (hallNorthZ + 0.8 -> 0.0) und Heldenhalle (heroesEntryZ + 0.6 -> 0.026)
        if (z < this.hallNorthZ + 0.8 && Math.abs(x) <= this.portalHalfW + 1.8) {
            const rampSpan = Math.max(0.4, (this.hallNorthZ + 0.8) - (this.heroesEntryZ + 0.6));
            const u = clamp(((this.hallNorthZ + 0.8) - z) / rampSpan, 0, 1);
            return u * 0.026;
        }
        // 3. Produktgalerie & Magma-Welt (x > 12.1 bei z > hallNorthZ - 1.0)
        return 0.0;
    }

    _computeAvatarMinSoleY(j) {
        if (!j || !j.pelvisPivot) return 0.004;

        const px = j.pelvisPivot.position.x || 0;
        const py = j.pelvisPivot.position.y || 0.776;
        const pz = j.pelvisPivot.position.z || 0;
        const prx = j.pelvisPivot.rotation.x || 0;
        const pry = j.pelvisPivot.rotation.y || 0;
        const prz = j.pelvisPivot.rotation.z || 0;
        const cpx = Math.cos(prx), spx = Math.sin(prx);
        const cpy = Math.cos(pry), spy = Math.sin(pry);
        const cpz = Math.cos(prz), spz = Math.sin(prz);

        const evalLegMinY = (hipPivot, kneePivot, anklePivot, sideSign) => {
            const hrx = hipPivot.rotation.x || 0;
            const hrz = hipPivot.rotation.z || 0;
            const krx = kneePivot.rotation.x || 0;
            const arx = anklePivot.rotation.x || 0;
            const chx = Math.cos(hrx), shx = Math.sin(hrx);
            const chz = Math.cos(hrz), shz = Math.sin(hrz);
            const ckx = Math.cos(krx), skx = Math.sin(krx);
            const cax = Math.cos(arx), sax = Math.sin(arx);

            let legMinY = Infinity;
            // Alle 4 Unterkanten-Ecken der 24K-Gold-MagBoot-Sohle (y = -0.082, z = -0.084..+0.144, x = +-0.068)
            const soleZs = [-0.084, 0.144];
            const soleXs = [-0.068, 0.068];
            for (let zi = 0; zi < 2; zi++) {
                const sz = soleZs[zi];
                const sy = -0.082;
                // 1. anklePivot rotateX + translate(0, -0.32, 0)
                const ky = (sy * cax - sz * sax) - 0.32;
                const kz = (sy * sax + sz * cax);
                // 2. kneePivot rotateX + translate(0, -0.33, 0)
                const hy = (ky * ckx - kz * skx) - 0.33;
                const hz = (ky * skx + kz * ckx);
                for (let xi = 0; xi < 2; xi++) {
                    const hx = soleXs[xi];
                    // 3. hipPivot Euler XYZ (hrx, 0, hrz): erst Z, dann X + translate(sideSign * 0.145, -0.04, 0)
                    const zRotX = hx * chz - hy * shz;
                    const zRotY = hx * shz + hy * chz;
                    const lx = zRotX + sideSign * 0.145;
                    const ly = (zRotY * chx - hz * shx) - 0.04;
                    const lz = (zRotY * shx + hz * chx);
                    // 4. pelvisPivot Euler XYZ (prx, pry, prz): erst Z, dann Y, dann X + translate(px, py, pz)
                    const p1x = lx * cpz - ly * spz;
                    const p1y = lx * spz + ly * cpz;
                    const p2z = -p1x * spy + lz * cpy;
                    const rootY = py + (p1y * cpx - p2z * spx);
                    if (rootY < legMinY) legMinY = rootY;
                }
            }
            return legMinY;
        };

        const soleBottomL = evalLegMinY(j.hipPivotL, j.kneePivotL, j.anklePivotL, -1);
        const soleBottomR = evalLegMinY(j.hipPivotR, j.kneePivotR, j.anklePivotR, 1);
        return Math.min(soleBottomL, soleBottomR);
    }

    _updatePlayerKinematics(dt, stepDist, turnRate, nearestForGaze, shadowSurfaceY = 0) {
        const j = this.player.joints;
        if (!this.player.root || !j) return;

        const floorOffset = this._computeFloorBaseY(this.player.x, this.player.z);
        const basePlayerY = this.player.y || 0;
        const awakenLift = this.player.awakenLiftY || 0;
        const visualY = basePlayerY + awakenLift;
        const heightAboveSupport = Math.max(0, visualY - shadowSurfaceY);
        const airBlend = clamp(heightAboveSupport / 0.26, 0, 1);
        const squash = this.player.landSquash || 0;
        const sprintBoost = clamp(this.player.sprintBoost || 0, 0, 1.0);
        this.player.wallClingBlend = lerp(
            this.player.wallClingBlend || 0,
            this.player.isWallClinging ? 1.0 : 0.0,
            1.0 - Math.exp(-dt * 18.0)
        );
        const wc = clamp(this.player.wallClingBlend || 0, 0, 1);

        this.player.root.position.set(this.player.x, floorOffset + visualY, this.player.z);
        this.player.root.rotation.y = this.player.yaw;
        this.player.root.rotation.x = 0;

        const strideFrequency = 4.48 + 0.72 * sprintBoost;
        if (stepDist > 0.0001 && airBlend < 0.65) {
            this.player.walkPhase = (this.player.walkPhase + stepDist * strideFrequency) % TWO_PI;
            const halfCycle = Math.floor((this.player.walkPhase / Math.PI) % 2);
            if (halfCycle !== this.player.lastStepHalfCycle && stepDist > 0.015) {
                this.player.lastStepHalfCycle = halfCycle;
                if (this.audio) {
                    this.audio.playFootstep('marble');
                }
            }
        }

        const instantSpeed = stepDist / Math.max(0.001, dt);
        const targetBlend = this.player.isMoving ? clamp(instantSpeed / 4.8, 0.25, 1.0) : 0.0;
        const blendFactor = 1.0 - Math.exp(-dt * 10.0);
        this.player.moveBlend = lerp(this.player.moveBlend, targetBlend, blendFactor);

        const mb = this.player.moveBlend * (1.0 - airBlend * 0.65);
        const idleWeight = (1.0 - mb) * (1.0 - airBlend);
        const idleSway = Math.sin(this.elapsedTime * 1.18) * idleWeight;
        const phaseL = this.player.walkPhase;
        const phaseR = phaseL + Math.PI;
        const strideAmpScale = 1.0 + 0.18 * sprintBoost;

        const applyLegGait = (hipPivot, kneePivot, anklePivot, p, sideSign) => {
            const sinP = Math.sin(p);
            const swingForward = Math.max(0, Math.cos(p - 0.16));
            const stanceWeight = Math.max(0, -Math.cos(p));
            const idleKneeRelax = Math.max(0, sideSign * idleSway) * 0.045;

            const hipPitch = -0.56 * strideAmpScale * sinP * mb
                - (sideSign < 0 ? 0.38 : 0.20) * airBlend
                - squash * 0.46
                - idleKneeRelax * 0.5;
            const kneeBend = Math.max(
                0,
                (Math.pow(swingForward, 1.28) * 0.76 * strideAmpScale + stanceWeight * 0.07) * mb
                    + (sideSign < 0 ? 0.66 : 0.46) * airBlend
                    + squash * 0.90
                    + idleKneeRelax
            );

            const heelStrike = Math.max(0, sinP - 0.45) * 0.22;
            const toePushOff = Math.max(0, -sinP - 0.32) * (0.32 + 0.14 * sprintBoost);
            const swingToeLift = -swingForward * 0.26;
            const anklePitch = (swingToeLift + heelStrike + toePushOff - stanceWeight * 0.04) * mb
                + 0.24 * airBlend
                - squash * 0.38;

            hipPivot.rotation.x = hipPitch;
            hipPivot.rotation.z = sideSign * (0.018 * idleWeight + 0.024 * mb + 0.032 * airBlend) - idleSway * 0.018;
            kneePivot.rotation.x = kneeBend;
            anklePivot.rotation.x = anklePitch;
        };

        applyLegGait(j.hipPivotL, j.kneePivotL, j.anklePivotL, phaseL, -1);
        applyLegGait(j.hipPivotR, j.kneePivotR, j.anklePivotR, phaseR, 1);

        let somersaultTuck = 0;
        if (this.player.isSomersault) {
            somersaultTuck = Math.sin(clamp(this.player.somersaultProgress, 0, 1) * Math.PI);
            j.kneePivotL.rotation.x += 2.25 * somersaultTuck;
            j.kneePivotR.rotation.x += 2.25 * somersaultTuck;
            j.hipPivotL.rotation.x -= 1.85 * somersaultTuck;
            j.hipPivotR.rotation.x -= 1.85 * somersaultTuck;
            j.anklePivotL.rotation.x -= 0.65 * somersaultTuck;
            j.anklePivotR.rotation.x -= 0.65 * somersaultTuck;
        }

        const applyArmGait = (shoulderPivot, elbowPivot, wristPivot, thumbPivot, fingerCurl, oppositeLegPhase, sideSign) => {
            const sinArm = Math.sin(oppositeLegPhase);
            const cosArm = Math.cos(oppositeLegPhase);
            const forwardAmt = Math.max(0, sinArm);
            const idleBreathArm = Math.sin(this.elapsedTime * 1.95 + sideSign * 0.45) * 0.024 * idleWeight;
            const idleFingerWave = Math.sin(this.elapsedTime * 2.15 + sideSign * 0.7) * 0.065 * idleWeight;
            const armSwingScale = 1.0 - 0.58 * sprintBoost;
            const ninjaSweepBack = 0.56 * sprintBoost * mb;

            shoulderPivot.rotation.x = (-0.44 * armSwingScale * sinArm * mb) + ninjaSweepBack + idleBreathArm - 0.42 * airBlend - squash * 0.14;
            shoulderPivot.rotation.z = sideSign * (0.072 + (0.036 + 0.145 * sprintBoost) * mb + Math.max(0, -sinArm) * 0.028 * mb + 0.18 * airBlend + Math.sin(this.elapsedTime * 2.05) * 0.012 * idleWeight);
            shoulderPivot.rotation.y = -sideSign * (0.075 * armSwingScale * sinArm - 0.08 * sprintBoost) * mb;

            elbowPivot.rotation.x = -0.18 - ((0.14 + 0.36 * forwardAmt) * armSwingScale + 0.08 * sprintBoost) * mb - 0.32 * airBlend - squash * 0.18;

            wristPivot.rotation.x = ((-0.09 * cosArm - 0.06 * forwardAmt) * armSwingScale + 0.14 * sprintBoost) * mb + 0.10 * airBlend + idleFingerWave * 0.45;
            wristPivot.rotation.z = sideSign * (0.04 + 0.05 * armSwingScale * sinArm * mb - 0.06 * airBlend);
            fingerCurl.rotation.x = (-0.12 * airBlend) + (0.20 * forwardAmt * armSwingScale * mb) + idleFingerWave;
            fingerCurl.rotation.z = sideSign * (0.14 + idleFingerWave + (0.18 * forwardAmt * armSwingScale + 0.06 * sprintBoost) * mb - 0.08 * airBlend);
            if (thumbPivot) {
                thumbPivot.rotation.x = 0.16 * forwardAmt * armSwingScale * mb - 0.10 * airBlend + idleFingerWave * 0.6;
            }
        };

        applyArmGait(j.shoulderPivotL, j.elbowPivotL, j.wristPivotL, j.thumbPivotL, j.fingerCurlL, phaseR, -1);
        applyArmGait(j.shoulderPivotR, j.elbowPivotR, j.wristPivotR, j.thumbPivotR, j.fingerCurlR, phaseL, 1);

        const doubleStepBounce = Math.cos(2.0 * phaseL) * (0.026 + 0.012 * sprintBoost) * mb;
        const stanceSink = -(0.016 + 0.022 * sprintBoost) * mb - squash * 0.18;
        const idleBreath = Math.sin(this.elapsedTime * 2.05) * 0.011 * idleWeight;

        // Dynamischer Vorwaerts- (+1) & Wall-Cling-Rueckwaertssalto (-1)
        const somersaultDir = this.player.somersaultDir || 1;
        j.pelvisPivot.position.y = 0.776 + doubleStepBounce + stanceSink + idleBreath;
        j.pelvisPivot.position.x = -Math.sin(phaseL) * 0.020 * mb + idleSway * 0.016;
        j.pelvisPivot.position.z = 0.032 * sprintBoost * mb;
        j.pelvisPivot.rotation.x = (this.player.isSomersault && heightAboveSupport > 0.24)
            ? ((this.player.somersaultDir || 1) * this.player.somersaultProgress * TWO_PI)
            : 0;
        j.pelvisPivot.rotation.y = Math.sin(phaseL) * 0.075 * mb + idleSway * 0.025;
        j.pelvisPivot.rotation.z = -Math.sin(phaseL) * 0.036 * mb - idleSway * 0.020;

        const targetBank = clamp(-turnRate * 0.052, -0.11, 0.11) * mb;
        this.player.turnBank = lerp(this.player.turnBank, targetBank, blendFactor);

        j.torsoPivot.rotation.x = (0.095 + 0.215 * sprintBoost) * mb
            + Math.sin(this.elapsedTime * 2.05 - 0.35) * 0.018 * idleWeight
            + squash * 0.18
            + (somersaultDir >= 0 ? 1.35 : 0.85) * somersaultTuck;
        j.torsoPivot.rotation.y = -Math.sin(phaseL) * (0.095 + 0.035 * sprintBoost) * mb - idleSway * 0.032;
        j.torsoPivot.rotation.z = this.player.turnBank + Math.sin(phaseL) * 0.024 * mb + idleSway * 0.016;

        // Echte Ninja Wall-Cling-Pose (beide Haende schulterhoch flach an der Wand, Knie athletisch abgestuetzt)
        if (wc > 0.002) {
            j.pelvisPivot.position.z = lerp(j.pelvisPivot.position.z, 0.085, wc);
            j.pelvisPivot.rotation.y = lerp(j.pelvisPivot.rotation.y, -0.10, wc);
            j.pelvisPivot.rotation.z = lerp(j.pelvisPivot.rotation.z, 0.03, wc);
            j.torsoPivot.rotation.x = lerp(j.torsoPivot.rotation.x, -0.06, wc);
            j.torsoPivot.rotation.y = lerp(j.torsoPivot.rotation.y, 0.08, wc);

            j.shoulderPivotL.rotation.x = lerp(j.shoulderPivotL.rotation.x, -1.42, wc);
            j.shoulderPivotL.rotation.y = lerp(j.shoulderPivotL.rotation.y, 0.18, wc);
            j.shoulderPivotL.rotation.z = lerp(j.shoulderPivotL.rotation.z, -0.46, wc);
            j.elbowPivotL.rotation.x = lerp(j.elbowPivotL.rotation.x, -0.46, wc);
            j.wristPivotL.rotation.x = lerp(j.wristPivotL.rotation.x, 0.58, wc);
            j.wristPivotL.rotation.z = lerp(j.wristPivotL.rotation.z, -0.14, wc);
            j.fingerCurlL.rotation.x = lerp(j.fingerCurlL.rotation.x, -0.22, wc);
            j.fingerCurlL.rotation.z = lerp(j.fingerCurlL.rotation.z, -0.05, wc);

            j.shoulderPivotR.rotation.x = lerp(j.shoulderPivotR.rotation.x, -1.42, wc);
            j.shoulderPivotR.rotation.y = lerp(j.shoulderPivotR.rotation.y, -0.18, wc);
            j.shoulderPivotR.rotation.z = lerp(j.shoulderPivotR.rotation.z, 0.46, wc);
            j.elbowPivotR.rotation.x = lerp(j.elbowPivotR.rotation.x, -0.46, wc);
            j.wristPivotR.rotation.x = lerp(j.wristPivotR.rotation.x, 0.58, wc);
            j.wristPivotR.rotation.z = lerp(j.wristPivotR.rotation.z, 0.14, wc);
            j.fingerCurlR.rotation.x = lerp(j.fingerCurlR.rotation.x, -0.22, wc);
            j.fingerCurlR.rotation.z = lerp(j.fingerCurlR.rotation.z, 0.05, wc);

            j.hipPivotL.rotation.x = lerp(j.hipPivotL.rotation.x, -1.04, wc);
            j.hipPivotL.rotation.z = lerp(j.hipPivotL.rotation.z, -0.20, wc);
            j.kneePivotL.rotation.x = lerp(j.kneePivotL.rotation.x, 1.30, wc);
            j.anklePivotL.rotation.x = lerp(j.anklePivotL.rotation.x, -0.26, wc);

            j.hipPivotR.rotation.x = lerp(j.hipPivotR.rotation.x, -0.68, wc);
            j.hipPivotR.rotation.z = lerp(j.hipPivotR.rotation.z, 0.18, wc);
            j.kneePivotR.rotation.x = lerp(j.kneePivotR.rotation.x, 0.96, wc);
            j.anklePivotR.rotation.x = lerp(j.anklePivotR.rotation.x, -0.16, wc);
        }

        if (this.player.isSomersault) {
            j.shoulderPivotL.rotation.x -= 1.35 * somersaultTuck;
            j.shoulderPivotR.rotation.x -= 1.35 * somersaultTuck;
            j.shoulderPivotL.rotation.z -= 0.45 * somersaultTuck;
            j.shoulderPivotR.rotation.z += 0.45 * somersaultTuck;
            j.elbowPivotL.rotation.x -= 1.85 * somersaultTuck;
            j.elbowPivotR.rotation.x -= 1.85 * somersaultTuck;
            j.fingerCurlL.rotation.z = -0.75 * somersaultTuck;
            j.fingerCurlR.rotation.z = 0.75 * somersaultTuck;
            j.headPivot.rotation.x += 0.95 * somersaultTuck;
            j.pelvisPivot.position.y -= 0.14 * somersaultTuck;
        } else if (this.player.emote) {
            const em = this.player.emote;
            if (em === 'dance') {
                const dTime = this.elapsedTime * 7.5;
                j.pelvisPivot.position.y += Math.abs(Math.sin(dTime)) * 0.06;
                j.pelvisPivot.rotation.z += Math.sin(dTime * 0.5) * 0.14;
                j.torsoPivot.rotation.z -= Math.sin(dTime * 0.5) * 0.12;
                j.shoulderPivotL.rotation.x = -1.2 + Math.sin(dTime) * 0.5;
                j.shoulderPivotR.rotation.x = -1.2 - Math.sin(dTime) * 0.5;
                j.elbowPivotL.rotation.x = -0.8 + Math.cos(dTime) * 0.4;
                j.elbowPivotR.rotation.x = -0.8 - Math.cos(dTime) * 0.4;
                j.kneePivotL.rotation.x += Math.max(0, Math.sin(dTime)) * 0.4;
                j.kneePivotR.rotation.x += Math.max(0, -Math.sin(dTime)) * 0.4;
            } else if (em === 'wave') {
                const wTime = this.elapsedTime * 12;
                j.shoulderPivotR.rotation.x = -2.35;
                j.shoulderPivotR.rotation.z = 0.35;
                j.elbowPivotR.rotation.x = -0.35;
                j.wristPivotR.rotation.z = Math.sin(wTime) * 0.45;
            } else if (em === 'bow') {
                const bP = Math.sin(clamp((2.4 - this.player.emoteTimer) / 2.4, 0, 1) * Math.PI);
                j.torsoPivot.rotation.x += 0.85 * bP;
                j.headPivot.rotation.x += 0.35 * bP;
                j.shoulderPivotL.rotation.x = 0.2 * bP;
                j.shoulderPivotR.rotation.x = 0.2 * bP;
            } else if (em === 'cheer') {
                const cTime = this.elapsedTime * 10;
                const cJump = Math.abs(Math.sin(cTime * 0.5)) * 0.12;
                j.pelvisPivot.position.y += cJump;
                j.shoulderPivotL.rotation.x = -2.5 + Math.sin(cTime) * 0.25;
                j.shoulderPivotR.rotation.x = -2.5 - Math.sin(cTime) * 0.25;
                j.shoulderPivotL.rotation.z = -0.35;
                j.shoulderPivotR.rotation.z = 0.35;
            } else if (em === 'sit') {
                j.pelvisPivot.position.y = 0.22;
                j.hipPivotL.rotation.x = -1.55;
                j.hipPivotR.rotation.x = -1.55;
                j.kneePivotL.rotation.x = 1.65;
                j.kneePivotR.rotation.x = 1.65;
                j.shoulderPivotL.rotation.x = -0.4;
                j.shoulderPivotR.rotation.x = -0.4;
            } else if (em === 'clap') {
                const clTime = this.elapsedTime * 14;
                j.shoulderPivotL.rotation.x = -1.25;
                j.shoulderPivotR.rotation.x = -1.25;
                j.shoulderPivotL.rotation.z = -0.45 - Math.sin(clTime) * 0.18;
                j.shoulderPivotR.rotation.z = 0.45 + Math.sin(clTime) * 0.18;
                j.elbowPivotL.rotation.x = -0.7;
                j.elbowPivotR.rotation.x = -0.7;
            }
        }

        if ((this.player.awakenPoseWeight || 0) > 0.001) {
            const aw = clamp(this.player.awakenPoseWeight, 0, 1);
            j.shoulderPivotL.rotation.x = lerp(j.shoulderPivotL.rotation.x, -1.08, aw);
            j.shoulderPivotR.rotation.x = lerp(j.shoulderPivotR.rotation.x, -1.08, aw);
            j.shoulderPivotL.rotation.z = lerp(j.shoulderPivotL.rotation.z, -0.52, aw);
            j.shoulderPivotR.rotation.z = lerp(j.shoulderPivotR.rotation.z, 0.52, aw);
            j.elbowPivotL.rotation.x = lerp(j.elbowPivotL.rotation.x, -0.34, aw);
            j.elbowPivotR.rotation.x = lerp(j.elbowPivotR.rotation.x, -0.34, aw);
            j.torsoPivot.rotation.x = lerp(j.torsoPivot.rotation.x, -0.10, aw);
        }

        if (j.thrusterFlameL && j.thrusterFlameR) {
            const showThrusters = !(this.player.builtRecord && this.player.builtRecord.skinnedRig) && sprintBoost > 0.15;
            j.thrusterFlameL.visible = showThrusters;
            j.thrusterFlameR.visible = showThrusters;
        }
        if (j.reactorRing) {
            j.reactorRing.rotation.z = this.elapsedTime * (1.1 + 2.9 * sprintBoost);
        }

        // Punkt 2: Exakte 0-mm-Boden-Buendigkeit der tieferen Schuhsohle auf groundY + 0.004!
        const minSoleLocalY = this._computeAvatarMinSoleY(j);
        if (heightAboveSupport <= 0.001 && this.player.emote !== 'cheer' && this.player.emote !== 'dance') {
            j.pelvisPivot.position.y += (0.004 - minSoleLocalY);
        } else {
            const minAllowedLocalY = (shadowSurfaceY - visualY) + 0.004;
            if (minSoleLocalY < minAllowedLocalY) {
                j.pelvisPivot.position.y += (minAllowedLocalY - minSoleLocalY);
            }
        }

        // Mitwandernder Fuss-Schatten & Basisring knapp unter der Schuhsohle (0.004) fuer 0 Clipping!
        const localShadowY = (shadowSurfaceY - visualY) + 0.002;
        j.footShadow.position.y = localShadowY;
        j.baseRing.position.y = localShadowY;
        const shadowScale = (1.0 - doubleStepBounce * 1.8) / (1.0 + heightAboveSupport * 0.55);
        j.footShadow.scale.set(shadowScale, shadowScale, 1);
        j.sparkCrest.rotation.y = this.elapsedTime * 1.4;

        if (this.player.bubbleSprite) {
            const showOwnBubble = !this.focusProtected && !this.soloMode && this.elapsedTime < this.player.bubbleExpiresAt;
            this.player.bubbleSprite.visible = showOwnBubble;
        }

        let desiredHeadYaw = 0;
        let desiredHeadPitch = (-0.02 - 0.11 * sprintBoost) * mb;

        if (wc > 0.35) {
            desiredHeadYaw = 0.54 * wc;
            desiredHeadPitch = 0.05 * wc;
        } else if (nearestForGaze) {
            const worldToTargetYaw = Math.atan2(nearestForGaze.x - this.player.x, nearestForGaze.z - this.player.z);
            const relYaw = shortestAngleDiff(this.player.yaw, worldToTargetYaw);
            const gazeStrength = clamp(1.0 - mb * 2.5, 0, 1);

            if (Math.abs(relYaw) < 1.35 && gazeStrength > 0.005) {
                desiredHeadYaw = clamp(relYaw * 0.68, -0.56, 0.56) * gazeStrength;
                desiredHeadPitch = lerp(desiredHeadPitch, -0.06, gazeStrength);
            }
        }

        const headSmooth = 1.0 - Math.exp(-dt * 8.5);
        this.player.headYaw = lerp(this.player.headYaw, desiredHeadYaw, headSmooth);
        this.player.headPitch = lerp(this.player.headPitch, desiredHeadPitch, headSmooth);
        j.headPivot.rotation.y = this.player.headYaw;
        j.headPivot.rotation.x = this.player.headPitch;
        j.headPivot.rotation.z = -this.player.headYaw * 0.12;

        const isAwakening = Boolean((this.player.awakenPoseWeight || 0) > 0.05);
        const animState = isAwakening
            ? 'cast'
            : ((airBlend > 0.25 || wc > 0.25) ? 'jump' : (mb > 0.18 ? 'run' : 'idle'));

        // Sanft geklemmte Knochenwinkel, Hand-/Finger-Posen, Crossfading & Umhang-Spring-Physik auf das 22-Bone THREE.SkinnedMesh uebertragen!
        if (this.player.builtRecord && this.player.builtRecord.skinnedRig) {
            this._syncSkinnedFunkiBones(this.player.builtRecord.skinnedRig, j, somersaultTuck, {
                dt,
                speed: instantSpeed,
                vy: this.player.vy || 0,
                turnRate,
                animState,
                sprintBoost,
                wallClingBlend: wc,
            });
        }
    }

    /**
     * Baustein B: Echte artikulierte Exoskelett- & 15-Bone-Gelenkposen fuer die Top-5-Funkis auf den Ehrenpodesten (60 FPS, 0 starres Klotz-Wackeln, 0 sitzender Level 1!).
     */
    _updateHeroPedestalAnimations(t) {
        if (this.oculusStarGroup) {
            this.oculusStarGroup.rotation.y = t * 0.25;
        }

        for (let fi = 0; fi < this.animatedFunkiFigures.length; fi++) {
            const fg = this.animatedFunkiFigures[fi];
            const rig = fg.motionRig;
            if (!rig) continue;

            const lvl = fg.level;
            const ph = fg.phase;
            const built = fg.avatarBuilt;
            const j = built ? built.joints : null;

            rig.scale.set(1, 1, 1);
            rig.rotation.set(0, 0, 0);

            if (j) {
                const breath = Math.sin(t * 2.0 + ph);
                const sway = Math.sin(t * 0.95 + ph * 0.7);
                const lookWave = Math.sin(t * 0.68 + ph);
                const isFloatingTier = (lvl >= 7);

                if (!isFloatingTier) {
                    // Stufen 1..6: Aufrechter, stolzer Stand mit lebendiger Ruheatmung, sanfter Gewichtsverlagerung & Blickwendung
                    rig.position.y = 0;
                    const stanceConfidence = clamp((lvl - 1) / 5, 0, 1);

                    j.pelvisPivot.position.set(sway * 0.016, 0.776 + breath * 0.010, 0);
                    j.pelvisPivot.rotation.set(0, sway * 0.045, -sway * 0.022);

                    j.torsoPivot.rotation.set(
                        -0.025 * stanceConfidence + breath * 0.022,
                        lookWave * 0.085 - sway * 0.035,
                        sway * 0.018
                    );

                    const relaxL = Math.max(0, sway) * 0.055;
                    const relaxR = Math.max(0, -sway) * 0.055;
                    j.hipPivotL.rotation.set(-relaxL * 0.5, 0, -0.028 - stanceConfidence * 0.015);
                    j.kneePivotL.rotation.set(relaxL, 0, 0);
                    j.anklePivotL.rotation.set(-relaxL * 0.45, 0, 0);

                    j.hipPivotR.rotation.set(-relaxR * 0.5, 0, 0.028 + stanceConfidence * 0.015);
                    j.kneePivotR.rotation.set(relaxR, 0, 0);
                    j.anklePivotR.rotation.set(-relaxR * 0.45, 0, 0);

                    // Edle Schulter- & Armdynamik (Stufe 1-2 aufmerksam neugierig, Stufe 5-6 selbstbewusste Meister-Haltung)
                    const armSpread = 0.08 + stanceConfidence * 0.06 + Math.max(0, breath) * 0.018;
                    j.shoulderPivotL.rotation.set(-0.08 + Math.sin(t * 1.45 + ph) * 0.04, 0.06, -armSpread);
                    j.elbowPivotL.rotation.set(-0.26 - stanceConfidence * 0.14 + breath * 0.03, 0, 0);
                    j.wristPivotL.rotation.set(-0.05, 0, -0.06);
                    j.fingerCurlL.rotation.z = -0.18;

                    j.shoulderPivotR.rotation.set(-0.10 - Math.sin(t * 1.45 + ph) * 0.04, -0.06, armSpread);
                    j.elbowPivotR.rotation.set(-0.28 - stanceConfidence * 0.16 - breath * 0.03, 0, 0);
                    j.wristPivotR.rotation.set(-0.05, 0, 0.06);
                    j.fingerCurlR.rotation.z = 0.22;

                    j.headPivot.rotation.set(
                        -0.03 * stanceConfidence + Math.sin(t * 1.1 + ph) * 0.035,
                        lookWave * (lvl <= 2 ? 0.26 : 0.18),
                        -lookWave * 0.03
                    );

                    const minSolePedY = this._computeAvatarMinSoleY(j);
                    j.pelvisPivot.position.y += (0.004 - minSolePedY);
                    if (j.footShadow) {
                        j.footShadow.position.y = 0.003;
                        j.footShadow.scale.set(0.95, 0.95, 1);
                    }
                    if (j.thrusterFlameL && j.thrusterFlameR) {
                        j.thrusterFlameL.visible = false;
                        j.thrusterFlameR.visible = false;
                    }
                    if (j.reactorRing) {
                        j.reactorRing.rotation.z = t * (0.9 + lvl * 0.15);
                    }
                } else {
                    // Stufen 7..10: Erhabenes Schweben mit angewinkelter Hover-Beinpose, aktiven Fersen-Schubduesen & Reaktorring!
                    const hoverBase = lvl === 10 ? 0.22 : (lvl === 9 ? 0.16 : 0.10);
                    const hoverAmp = lvl === 10 ? 0.068 : (lvl === 9 ? 0.054 : 0.042);
                    const hoverWave = Math.sin(t * (1.85 + (lvl - 7) * 0.14) + ph);
                    rig.position.y = hoverBase + hoverWave * hoverAmp;

                    j.pelvisPivot.position.set(0, 0.776, 0);
                    j.pelvisPivot.rotation.set(0.04, Math.sin(t * 0.55 + ph) * 0.08, sway * 0.02);

                    j.torsoPivot.rotation.set(
                        -0.06 + breath * 0.024,
                        Math.sin(t * 0.55 + ph) * 0.10,
                        sway * 0.018
                    );

                    // Heroische Levitations-Beinpose (rechtes Knie leicht staerker angezogen, Stiefel entspannt geneigt)
                    j.hipPivotL.rotation.set(-0.16 + hoverWave * 0.03, 0, -0.055);
                    j.kneePivotL.rotation.set(0.28 - hoverWave * 0.04, 0, 0);
                    j.anklePivotL.rotation.set(0.22, 0, 0);

                    j.hipPivotR.rotation.set(-0.32 - hoverWave * 0.03, 0, 0.065);
                    j.kneePivotR.rotation.set(0.54 + hoverWave * 0.05, 0, 0);
                    j.anklePivotR.rotation.set(0.28, 0, 0);

                    // Majestaetisch geoeffnete Exoskelett-Arme
                    const openWing = lvl === 10 ? 0.34 : (lvl === 9 ? 0.26 : 0.18);
                    j.shoulderPivotL.rotation.set(-0.24 + hoverWave * 0.06, 0.12, -openWing);
                    j.elbowPivotL.rotation.set(-0.38 + breath * 0.04, 0, 0);
                    j.wristPivotL.rotation.set(0.08, 0, -0.10);
                    j.fingerCurlL.rotation.z = -0.12;

                    j.shoulderPivotR.rotation.set(-0.24 + hoverWave * 0.06, -0.12, openWing);
                    j.elbowPivotR.rotation.set(-0.38 + breath * 0.04, 0, 0);
                    j.wristPivotR.rotation.set(0.08, 0, 0.10);
                    j.fingerCurlR.rotation.z = 0.12;

                    j.headPivot.rotation.set(
                        -0.05 + hoverWave * 0.025,
                        lookWave * 0.15,
                        -lookWave * 0.02
                    );

                    if (j.footShadow) {
                        j.footShadow.position.y = -rig.position.y + 0.004;
                        const shScale = clamp(0.85 - rig.position.y * 0.85, 0.48, 0.88);
                        j.footShadow.scale.set(shScale, shScale, 1);
                    }
                    if (j.thrusterFlameL && j.thrusterFlameR) {
                        const showPedFlame = !(built && built.skinnedRig);
                        j.thrusterFlameL.visible = showPedFlame;
                        j.thrusterFlameR.visible = showPedFlame;
                        if (showPedFlame) {
                            const flamePulse = 0.82 + Math.sin(t * 32.0 + ph) * 0.22;
                            j.thrusterFlameL.scale.set(0.92, flamePulse, 0.92);
                            j.thrusterFlameR.scale.set(0.92, flamePulse, 0.92);
                        }
                    }
                    if (j.reactorRing) {
                        j.reactorRing.rotation.z = t * (3.2 + (lvl - 7) * 0.55);
                    }
                }

                if (j.sparkCrest) {
                    j.sparkCrest.rotation.y = t * 1.45 + ph;
                }
                if (built.skinnedRig) {
                    this._syncSkinnedFunkiBones(built.skinnedRig, j, 0);
                }
            }

            if (fg.auraOuter) {
                const auraPulse = 1.0 + Math.sin(t * 2.2 + ph) * 0.032;
                fg.auraOuter.scale.set(auraPulse, auraPulse, 1.0);
                fg.auraOuter.rotation.z = t * 0.65 + ph;
            }
            if (fg.auraInner) {
                fg.auraInner.rotation.z = -t * 0.95 - ph;
            }
            if (fg.levitationRing) {
                fg.levitationRing.rotation.z = t * 1.35 + ph;
                fg.levitationRing.position.y = 0.10 + Math.sin(t * 2.0 + ph) * 0.02;
            }
            if (fg.lightbringerHalo) {
                fg.lightbringerHalo.rotation.z = t * 1.15;
                fg.lightbringerHalo.rotation.y = Math.sin(t * 0.7 + ph) * 0.28;
            }
            if (fg.godStarRingOuter) {
                fg.godStarRingOuter.rotation.z = t * 1.40;
                fg.godStarRingOuter.rotation.y = Math.sin(t * 0.9) * 0.18;
            }
            if (fg.godStarRingInner) {
                fg.godStarRingInner.rotation.z = -t * 1.95;
                fg.godStarRingInner.rotation.x = Math.PI * 0.5 + Math.cos(t * 1.1) * 0.20;
            }
            if (fg.godCorona) {
                fg.godCorona.rotation.z = -t * 0.85;
                fg.godCorona.position.y = 1.54 + Math.sin(t * 2.25 + ph) * 0.075;
            }
        }

        if (this.freePedestalCrests && this.freePedestalCrests.length > 0) {
            for (let fci = 0; fci < this.freePedestalCrests.length; fci++) {
                const fc = this.freePedestalCrests[fci];
                if (fc.gem) {
                    fc.gem.rotation.y = t * 1.15 + fc.phase;
                    fc.gem.position.y = 0.42 + Math.sin(t * 1.85 + fc.phase) * 0.045;
                }
                if (fc.ring) {
                    fc.ring.rotation.z = -t * 0.55 + fc.phase;
                }
            }
        }

        if (this.pedestalSparkPosAttr && this.pedestalSparkMeta.length > 0) {
            const posArr = this.pedestalSparkPosAttr.array;
            for (let si = 0; si < this.pedestalSparkMeta.length; si++) {
                const sm = this.pedestalSparkMeta[si];
                const su = ((sm.phase + t * sm.speed) % 1 + 1) % 1;
                const sang = t * sm.swirl + sm.phase * TWO_PI;
                const sr = sm.radius * (0.76 + 0.24 * Math.sin(su * Math.PI));
                const p3 = si * 3;
                posArr[p3] = sm.baseX + Math.cos(sang) * sr;
                posArr[p3 + 1] = sm.baseY + su * sm.heightSpan;
                posArr[p3 + 2] = sm.baseZ + Math.sin(sang) * sr;
            }
            this.pedestalSparkPosAttr.needsUpdate = true;
        }

    }

    getUserSettings() {
        return Object.assign({}, this.userSettings);
    }

    updateSettings(partial = {}) {
        if (!partial || typeof partial !== 'object') {
            return this.getUserSettings();
        }
        const settingsPatch = {};
        if (partial.volume !== undefined) {
            const vol = clamp(Math.round(Number(partial.volume)), 0, 100);
            settingsPatch.volume = vol;
            if (this.audio) {
                if (typeof this.audio.setBgmVolume === 'function') this.audio.setBgmVolume(vol, true);
                if (typeof this.audio.setBgmMuted === 'function') this.audio.setBgmMuted(vol === 0, true);
                if (typeof this.audio.setSfxVolume === 'function') this.audio.setSfxVolume(vol);
                if (typeof this.audio.setSfxMuted === 'function') this.audio.setSfxMuted(vol === 0);
            }
        }
        if (partial.cameraSensitivity !== undefined) {
            const sens = clamp(Math.round(Number(partial.cameraSensitivity)), 50, 150);
            settingsPatch.cameraSensitivity = sens;
            this.cameraSensitivityFactor = sens / 100;
        }
        if (partial.useRealName !== undefined) {
            settingsPatch.useRealName = Boolean(partial.useRealName);
        }
        if (partial.graphicsQuality !== undefined) {
            settingsPatch.graphicsQuality = partial.graphicsQuality === 'balanced' ? 'balanced' : 'high';
        }
        if (Object.keys(settingsPatch).length > 0) {
            this.userSettings = saveLobbyUserSettings(settingsPatch);
            if (settingsPatch.graphicsQuality !== undefined) {
                this.resize();
            }
        }
        if (partial.resetKeybindings) {
            this.resetKeybindings();
        } else if (partial.keybindings && typeof partial.keybindings === 'object') {
            this.setKeybindings(partial.keybindings);
        }
        if (partial.soloMode !== undefined) {
            this.setSoloMode(Boolean(partial.soloMode));
        }
        if (partial.focusProtected !== undefined) {
            this.setFocusProtected(Boolean(partial.focusProtected));
        }
        if (Array.isArray(partial.mutedPlayers)) {
            this.setMutedPlayers(partial.mutedPlayers);
        }
        return this.getUserSettings();
    }

    getKeybindings() {
        return Object.assign({}, this.keybindings);
    }

    setKeybindings(nextBindings) {
        this.keybindings = saveLobbyKeybindings(nextBindings);
        return this.getKeybindings();
    }

    resetKeybindings() {
        this.keybindings = resetLobbyKeybindings();
        return this.getKeybindings();
    }
    triggerJump() {
        if (!this.running || this.destroyed) return false;

        // 0. Epischer Rueckwaertssalto (Wall-Backflip) direkt aus dem 1-Sekunden Wall-Cling an der Wand!
        if (this.player.isWallClinging) {
            const wnX = this.player.wallNormalX || 0;
            const wnZ = this.player.wallNormalZ || 0;
            this.player.isWallClinging = false;
            this.player.wallClingTimer = 0;
            this.player.isJumping = true;
            this.player.jumpCount = 2;
            this.player.vy = 6.75;
            this.player.wallFlipVx = wnX * 6.5;
            this.player.wallFlipVz = wnZ * 6.5;
            this.player.velX = this.player.wallFlipVx;
            this.player.velZ = this.player.wallFlipVz;
            this.player.isSomersault = true;
            this.player.somersaultDir = -1;
            this.player.somersaultDuration = 0.62;
            this.player.somersaultProgress = 0.0;
            this._spawnWallKickBurst(
                this.player.x - wnX * 0.28,
                (this.player.y || 0) + 0.85,
                this.player.z - wnZ * 0.28,
                wnX,
                wnZ
            );
            if (this.audio) this.audio.playJump(true);
            return true;
        }

        const onGroundOrCoyote = (!this.player.isJumping && Math.abs((this.player.y || 0) - (this.player.groundY || 0)) <= 0.08)
            || (this.player.jumpCount === 0 && this.player.coyoteTimer > 0);

        // 1. Erster Sprung vom Boden (inkl. 110 ms Coyote-Time)
        if (onGroundOrCoyote) {
            this.player.isJumping = true;
            this.player.highJumpActive = false;
            this.player.jumpCount = 1;
            this.player.coyoteTimer = 0;
            this.player.standingPlatform = null;
            this.player.wallClingUsed = false;
            this.player.isWallClinging = false;
            this.player.wallClingTimer = 0;
            this.player.vy = 5.8;
            this.player.isSomersault = false;
            this.player.somersaultDir = 1;
            this.player.somersaultDuration = 0.52;
            this.player.somersaultProgress = 0.0;
            if (this.audio) this.audio.playJump(false);
            return true;
        }

        // 2. Doppelsprung mit 360-Grad Vorwaertssalto in der Luft
        if (this.player.jumpCount === 1) {
            this.player.isJumping = true;
            this.player.jumpCount = 2;
            this.player.vy = 5.85;
            this.player.isSomersault = true;
            this.player.somersaultDir = 1;
            this.player.somersaultDuration = 0.52;
            this.player.somersaultProgress = 0.0;
            this._spawnDoubleJumpBurst(this.player.x, (this.player.y || 0) + 0.12, this.player.z);
            if (this.audio) this.audio.playJump(true);
            return true;
        }

        return false;
    }

    _spawnWallKickBurst(x, y, z, wnX = 0, wnZ = 1) {
        if (!this.scene || !this.THREE) return;
        if (this.weaponBursts.length >= 14) {
            const oldest = this.weaponBursts.shift();
            if (oldest) this._disposeWeaponBurst(oldest);
        }

        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const group = new THREE.Group();
        group.position.set(Number(x) || 0, Number(y) || 0, Number(z) || 0);

        const ringMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#C5A059'),
            transparent: true,
            opacity: 0.94,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const ringMesh = new THREE.Mesh(shared.geos.baseRing, ringMat);
        ringMesh.rotation.y = Math.atan2(wnX, wnZ);
        ringMesh.scale.set(0.55, 0.55, 0.55);
        group.add(ringMesh);

        const sparks = [];
        const sparkMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#FFF3CC'),
            transparent: true,
            opacity: 0.96,
            depthWrite: false,
        });
        const sideX = -wnZ;
        const sideZ = wnX;
        for (let i = 0; i < 10; i++) {
            const angle = (i / 10) * TWO_PI;
            const cA = Math.cos(angle);
            const sA = Math.sin(angle);
            const sMesh = new THREE.Mesh(shared.geos.sparkDiamond, sparkMat);
            sMesh.position.set(sideX * cA * 0.22, sA * 0.22, sideZ * cA * 0.22);
            group.add(sMesh);
            sparks.push({
                mesh: sMesh,
                vx: sideX * cA * 1.9 + wnX * 1.6,
                vy: sA * 1.9 + 0.5,
                vz: sideZ * cA * 1.9 + wnZ * 1.6,
            });
        }

        this.scene.add(group);
        this.weaponBursts.push({
            group,
            slashMesh: ringMesh,
            arcMat: ringMat,
            sparkMat,
            sparks,
            age: 0,
            duration: 0.44,
            maxRingScale: 1.75,
            isRemote: false,
        });
    }

    _spawnDoubleJumpBurst(x, y, z) {
        if (!this.scene || !this.THREE) return;
        if (this.weaponBursts.length >= 14) {
            const oldest = this.weaponBursts.shift();
            if (oldest) this._disposeWeaponBurst(oldest);
        }

        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const group = new THREE.Group();
        group.position.set(Number(x) || 0, Number(y) || 0, Number(z) || 0);

        const ringMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#C5A059'),
            transparent: true,
            opacity: 0.92,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const ringMesh = new THREE.Mesh(shared.geos.baseRing, ringMat);
        ringMesh.rotation.x = -Math.PI * 0.5;
        ringMesh.scale.set(0.6, 0.6, 0.6);
        group.add(ringMesh);

        const sparks = [];
        const sparkMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#FFF3CC'),
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
        });

        for (let i = 0; i < 8; i++) {
            const angle = (i / 8) * TWO_PI;
            const sMesh = new THREE.Mesh(shared.geos.sparkDiamond, sparkMat);
            sMesh.position.set(Math.sin(angle) * 0.35, 0.02, Math.cos(angle) * 0.35);
            group.add(sMesh);
            sparks.push({
                mesh: sMesh,
                vx: Math.sin(angle) * 1.5,
                vy: -0.6 - (i % 3) * 0.2,
                vz: Math.cos(angle) * 1.5,
            });
        }

        this.scene.add(group);
        this.weaponBursts.push({
            group,
            slashMesh: ringMesh,
            arcMat: ringMat,
            sparkMat,
            sparks,
            age: 0,
            duration: 0.48,
            isRemote: false,
        });
    }

    _spawnAbilityBurst(x, y, z, type) {
        if (!this.scene || !this.THREE) return;
        if (this.weaponBursts.length >= 16) {
            const oldest = this.weaponBursts.shift();
            if (oldest) this._disposeWeaponBurst(oldest);
        }

        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const group = new THREE.Group();
        group.position.set(Number(x) || 0, Number(y) || 0, Number(z) || 0);

        const colorHex = (type === 'shield' || type === 'highJump') ? '#D4AF37' : '#C5A059';
        const ringMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color(colorHex),
            transparent: true,
            opacity: 0.95,
            side: THREE.DoubleSide,
            depthWrite: false,
        });

        const ringHolder = new THREE.Group();
        const ringMesh = new THREE.Mesh(shared.geos.baseRing, ringMat);
        ringMesh.rotation.x = -Math.PI * 0.5;
        ringHolder.add(ringMesh);

        if (type === 'highJump') {
            // Doppelter Gold-Schubring unter den Exoskelett-Stiefeln!
            const lowerRing = new THREE.Mesh(shared.geos.baseRing, ringMat);
            lowerRing.rotation.x = -Math.PI * 0.5;
            lowerRing.scale.setScalar(1.38);
            lowerRing.position.y = -0.14;
            ringHolder.add(lowerRing);
        }
        group.add(ringHolder);

        const sparks = [];
        const sparkCount = (type === 'highJump') ? 16 : 10;
        const sparkMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#FFF6DB'),
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
        });

        for (let i = 0; i < sparkCount; i++) {
            const sm = new THREE.Mesh(shared.geos.sparkDiamond, sparkMat);
            const ang = (i / sparkCount) * TWO_PI;
            const spd = (type === 'highJump') ? (2.2 + (i % 3) * 0.65) : (1.5 + (i % 3) * 0.45);
            const vy = (type === 'jump' || type === 'highJump') ? (-0.7 - (i % 3) * 0.45) : (0.5 + (i % 3) * 0.5);
            sm.position.set(0, 0.05, 0);
            group.add(sm);
            sparks.push({
                mesh: sm,
                vx: Math.cos(ang) * spd,
                vy,
                vz: Math.sin(ang) * spd,
            });
        }

        this.scene.add(group);
        this.weaponBursts.push({
            group,
            slashMesh: ringHolder,
            arcMat: ringMat,
            sparkMat,
            sparks,
            age: 0,
            duration: (type === 'highJump') ? 0.58 : 0.46,
            maxRingScale: (type === 'highJump') ? 2.1 : 1.4,
            isRemote: false,
        });
    }

    /**
     * Punkt 1: Kinoreife ~680 ms Uebergangssequenz beim Klick auf "Jetzt gestalten"
     * (Kamera gleitet sanft auf das beleuchtete Exponat zu + goldene Funken steigen auf + edler Klang).
     */
    startExhibitTransition(steleIdx) {
        if (this.destroyed || !this.running || !this.camera) return false;
        const idx = (typeof steleIdx === 'number' && steleIdx >= 0) ? steleIdx : this.focusedSteleIndex;
        const st = (idx >= 0 && this.steles[idx]) ? this.steles[idx] : null;
        if (this.audio && typeof this.audio.playProductTransition === 'function') {
            this.audio.playProductTransition();
        }
        if (!st) return true;

        this._spawnChestFountainBurst(st.x, 1.48, st.z);
        const standR = 1.24;
        this._exhibitTransition = {
            elapsed: 0,
            duration: 0.68,
            startCamX: this.camera.position.x,
            startCamY: this.camera.position.y,
            startCamZ: this.camera.position.z,
            targetCamX: st.x + Math.sin(st.facingYaw) * standR,
            targetCamY: 1.72,
            targetCamZ: st.z + Math.cos(st.facingYaw) * standR,
            startLookX: this.camControl.lookAtCurrent ? this.camControl.lookAtCurrent.x : st.x,
            startLookY: this.camControl.lookAtCurrent ? this.camControl.lookAtCurrent.y : 1.56,
            startLookZ: this.camControl.lookAtCurrent ? this.camControl.lookAtCurrent.z : st.z,
            lookX: st.x,
            lookY: 1.56,
            lookZ: st.z,
        };
        return true;
    }

    triggerEmote(name) {
        if (!this.running || this.destroyed || !name) return false;
        const validEmotes = ['dance', 'wave', 'bow', 'cheer', 'spark', 'sit', 'clap'];
        if (validEmotes.indexOf(name) === -1) return false;
        if (name === 'spark') {
            this._spawnDoubleJumpBurst(this.player.x || 0, (this.player.y || 0) + 0.15, this.player.z || 0);
        }
        this.player.emote = name;
        const durations = {
            dance: 8.0,
            wave: 2.8,
            bow: 2.4,
            cheer: 3.0,
            spark: 2.4,
            sit: 999.0,
            clap: 3.2,
        };
        this.player.emoteTimer = durations[name] || 3.0;
        return true;
    }

    _disposeWeaponBurst(burst) {
        if (!burst) return;
        if (this.scene && burst.group) {
            this.scene.remove(burst.group);
        }
        if (burst.arcMat) burst.arcMat.dispose();
        if (burst.sparkMat) burst.sparkMat.dispose();
    }

    _updateWeaponBursts(dt) {
        for (let i = this.weaponBursts.length - 1; i >= 0; i--) {
            const b = this.weaponBursts[i];
            if (!b || !b.slashMesh || !b.arcMat || !b.sparkMat) {
                this.weaponBursts.splice(i, 1);
                continue;
            }
            if (b.isRemote && (this.focusProtected || this.soloMode)) {
                this._disposeWeaponBurst(b);
                this.weaponBursts.splice(i, 1);
                continue;
            }

            b.age += dt;
            const t = b.age / b.duration;
            if (t >= 1.0) {
                this._disposeWeaponBurst(b);
                this.weaponBursts.splice(i, 1);
                continue;
            }

            const fade = Math.max(0, 1.0 - t * t);
            b.arcMat.opacity = 0.88 * fade;
            b.sparkMat.opacity = 0.96 * fade;
            const targetMax = b.maxRingScale || 1.4;
            const arcScale = lerp(0.55, targetMax, 1.0 - Math.pow(1.0 - t, 2.2));
            b.slashMesh.scale.set(arcScale, arcScale, arcScale);
            b.slashMesh.rotation.y += dt * 3.2;

            for (let s = 0; s < b.sparks.length; s++) {
                const sp = b.sparks[s];
                sp.mesh.position.x += sp.vx * dt;
                sp.mesh.position.y += sp.vy * dt;
                sp.mesh.position.z += sp.vz * dt;
                sp.vy -= 4.2 * dt;
                sp.mesh.rotation.y += dt * 6.0;
                sp.mesh.scale.setScalar(Math.max(0.1, 1.0 - t * 0.85));
            }
        }
    }

    getCurrentZone() {
        if (this.player.z < (this.hallNorthZ - 4.0)) {
            return 'heroes';
        }
        return 'gallery';
    }

    getLocalSyncState() {
        const rawYaw = this.player.yaw || 0;
        const normYaw = Math.atan2(Math.sin(rawYaw), Math.cos(rawYaw));
        const activeEmote = (this.player.emote && (this.player.emoteTimer || 0) > 0) ? String(this.player.emote) : null;
        return {
            x: Number((this.player.x || 0).toFixed(3)),
            y: Number((this.player.y || 0).toFixed(3)),
            z: Number((this.player.z || 0).toFixed(3)),
            rot_y: Number(normYaw.toFixed(3)),
            rotY: Number(normYaw.toFixed(3)),
            speed: Number((this.player.currentSpeed || 0).toFixed(3)),
            zone: this.getCurrentZone(),
            jumping: Boolean(this.player.isJumping || (this.player.y || 0) > 0.02),
            emote: activeEmote,
            combo_seq: 0,
            combo_stage: 0,
        };
    }

    setLocalPeerId(peerId) {
        this._localPeerId = peerId ? String(peerId) : null;
    }

    setSoloMode(flag) {
        this.soloMode = Boolean(flag);
        if (this.soloMode) {
            this._clearAllPeers();
        }
    }

    setFocusProtected(flag) {
        this.focusProtected = Boolean(flag);
    }

    setMutedPlayers(playerIds) {
        this.mutedPlayerIds = new Set(Array.isArray(playerIds) ? playerIds : []);
        this.peers.forEach((peer, pid) => {
            if (this.mutedPlayerIds.has(pid) && peer.bubbleSprite) {
                peer.bubbleSprite.visible = false;
            }
        });
    }

    showChatBubble(playerId, text) {
        if (this.destroyed || !this.THREE || !text) return;
        const rawId = playerId ? String(playerId) : '';
        if (this.focusProtected || this.soloMode || (rawId && this.mutedPlayerIds.has(rawId))) return;

        const THREE = this.THREE;
        const myPid = this.playerFunki && this.playerFunki.player_id ? String(this.playerFunki.player_id) : null;
        const myPeerId = this._localPeerId ? String(this._localPeerId) : null;
        const isMe = rawId === 'self'
            || (myPid && rawId === myPid)
            || (myPeerId && rawId === myPeerId);

        if (isMe && this.player.root) {
            if (this.player.bubbleTex) this.player.bubbleTex.dispose();
            const tex = buildSpeechBubbleTexture(THREE, text);
            if (!this.player.bubbleSprite) {
                const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false });
                const sprite = new THREE.Sprite(mat);
                sprite.scale.set(1.75, 0.55, 1);
                sprite.position.set(0, 2.38, 0);
                this.player.root.add(sprite);
                this.player.bubbleSprite = sprite;
                this.player.bubbleMat = mat;
            } else {
                this.player.bubbleMat.map = tex;
                this.player.bubbleMat.needsUpdate = true;
            }
            this.player.bubbleTex = tex;
            this.player.bubbleExpiresAt = this.elapsedTime + 7.0;
            this.player.bubbleSprite.visible = true;
            return;
        }

        if (this.getCurrentZone() !== 'heroes') return;
        let peer = this.peers.get(rawId);
        if (!peer) {
            this.peers.forEach((p) => {
                if (!peer && (p.peerId === rawId || p.legacyPlayerId === rawId)) {
                    peer = p;
                }
            });
        }
        if (!peer || !peer.root) return;

        if (peer.bubbleTex) peer.bubbleTex.dispose();
        const tex = buildSpeechBubbleTexture(THREE, text);
        if (!peer.bubbleSprite) {
            const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false });
            const sprite = new THREE.Sprite(mat);
            sprite.scale.set(1.75, 0.55, 1);
            sprite.position.set(0, 2.52, 0);
            peer.root.add(sprite);
            peer.bubbleSprite = sprite;
            peer.bubbleMat = mat;
        } else {
            peer.bubbleMat.map = tex;
            peer.bubbleMat.needsUpdate = true;
        }
        peer.bubbleTex = tex;
        peer.bubbleExpiresAt = this.elapsedTime + 7.0;
        peer.bubbleSprite.visible = true;
    }

    _disposePeerEntry(peer) {
        if (!peer) return;
        if (this.scene && peer.root) {
            this.scene.remove(peer.root);
        }
        if (peer.nameplateTex) peer.nameplateTex.dispose();
        if (peer.nameplateMat) peer.nameplateMat.dispose();
        if (peer.bubbleTex) peer.bubbleTex.dispose();
        if (peer.bubbleMat) peer.bubbleMat.dispose();
    }

    _clearAllPeers() {
        this.peers.forEach((peer) => this._disposePeerEntry(peer));
        this.peers.clear();
    }

    /**
     * Baustein D: Synchronisiert bis zu 19 Mitspieler derselben Multiplayer-Instanz (20er Hard-Cap)
     * mit 15-Bone SkinnedMesh + 3D-Exoskelett, weicher Lerp-Interpolation & Kombo-Effekten!
     */
    updatePeers(peersArray) {
        if (this.destroyed || !this.THREE || !this.scene) return;
        if (this.soloMode || this.getCurrentZone() !== 'heroes' || !Array.isArray(peersArray)) {
            this._clearAllPeers();
            return;
        }

        const THREE = this.THREE;
        const incomingIds = new Set();

        for (let i = 0; i < peersArray.length && i < 19; i++) {
            const pData = peersArray[i];
            const rawPid = pData ? (pData.peer_id || pData.player_id) : null;
            if (!rawPid) continue;

            const peerZone = String((pData && pData.zone) ? pData.zone : 'heroes').toLowerCase();
            if (peerZone !== 'heroes') {
                continue;
            }

            const pid = String(rawPid);
            incomingIds.add(pid);

            let peer = this.peers.get(pid);
            const maxPeerX = this.pantheonHalfW - 0.6;
            let tx = clamp(Number(pData.x) || 0, -this.pantheonHalfW + 0.6, maxPeerX);
            let tz = clamp(Number(pData.z) || 8.4, this.pantheonMinZ + 0.8, this.hallSouthZ - 1.0);
            if (peerZone === 'heroes' && tz > this.hallNorthZ - 2.0) {
                tz = this.pantheonCenterZ + 8.5;
            }
            const ty = clamp(Number(pData.y) || 0, -1.5, 9.5);
            const tYaw = Number(pData.rot_y ?? pData.rotY) || 0;
            const reportedSpeed = Math.max(0, Number(pData.speed) || 0);
            const wSeq = parseInt(pData.combo_seq ?? pData.weapon_seq, 10) || 0;
            const cStage = clamp(parseInt(pData.combo_stage, 10) || 1, 1, 4);
            const peerLvl = clamp(parseInt(pData.level, 10) || 1, 1, 10);

            if (!peer) {
                const built = this._createArticulatedAvatarInstance(peerLvl, null, false);
                const floorOffset = this._computeFloorBaseY(tx, tz);
                built.root.position.set(tx, floorOffset + ty, tz);
                built.root.rotation.y = tYaw;

                const fallbackClass = peerLvl >= 9 ? 'KLASSE S' : (peerLvl >= 6 ? 'KLASSE A' : (peerLvl >= 3 ? 'KLASSE B' : 'KLASSE C'));
                const classLabel = pData.ship_class || fallbackClass;
                const accentHex = '#C5A059';
                const nameplateTex = buildPeerNameplateTexture(
                    THREE,
                    pData.display_name || 'Funki',
                    peerLvl,
                    classLabel,
                    accentHex
                );
                const nameplateMat = new THREE.SpriteMaterial({
                    map: nameplateTex,
                    transparent: true,
                    depthWrite: false,
                });
                const nameplateSprite = new THREE.Sprite(nameplateMat);
                nameplateSprite.scale.set(1.28, 0.32, 1);
                nameplateSprite.position.set(0, 2.04, 0);
                built.root.add(nameplateSprite);

                this.scene.add(built.root);

                peer = {
                    playerId: pid,
                    peerId: pData.peer_id ? String(pData.peer_id) : pid,
                    legacyPlayerId: pData.player_id ? String(pData.player_id) : pid,
                    level: peerLvl,
                    builtRecord: built,
                    root: built.root,
                    joints: built.joints,
                    x: tx,
                    y: ty,
                    z: tz,
                    yaw: tYaw,
                    targetX: tx,
                    targetY: ty,
                    targetZ: tz,
                    targetYaw: tYaw,
                    vx: Number.isFinite(Number(pData.vx)) ? Number(pData.vx) : 0,
                    vz: Number.isFinite(Number(pData.vz)) ? Number(pData.vz) : 0,
                    lastPacketAt: this.elapsedTime,
                    reportedSpeed,
                    emote: pData.emote ? String(pData.emote) : null,
                    walkPhase: 0,
                    moveBlend: 0,
                    nameplateSprite,
                    nameplateTex,
                    nameplateMat,
                    bubbleSprite: null,
                    bubbleTex: null,
                    bubbleMat: null,
                    bubbleExpiresAt: 0,
                };
                this.peers.set(pid, peer);
            } else {
                const dtPacket = Math.max(0.18, Math.min(2.2, this.elapsedTime - (peer.lastPacketAt || (this.elapsedTime - 0.95))));
                const inferredVx = clamp((tx - peer.targetX) / dtPacket, -8.8, 8.8);
                const inferredVz = clamp((tz - peer.targetZ) / dtPacket, -8.8, 8.8);
                peer.vx = Number.isFinite(Number(pData.vx))
                    ? clamp(Number(pData.vx), -8.8, 8.8)
                    : (reportedSpeed > 0.08 ? inferredVx : 0);
                peer.vz = Number.isFinite(Number(pData.vz))
                    ? clamp(Number(pData.vz), -8.8, 8.8)
                    : (reportedSpeed > 0.08 ? inferredVz : 0);
                peer.lastPacketAt = this.elapsedTime;
                peer.targetX = tx;
                peer.targetY = ty;
                peer.targetZ = tz;
                peer.targetYaw = tYaw;
                peer.reportedSpeed = reportedSpeed;
                peer.emote = pData.emote ? String(pData.emote) : null;
                if (pData.peer_id) peer.peerId = String(pData.peer_id);
                if (pData.player_id) peer.legacyPlayerId = String(pData.player_id);
            }
        }

        this.peers.forEach((peer, pid) => {
            if (!incomingIds.has(pid)) {
                this._disposePeerEntry(peer);
                this.peers.delete(pid);
            }
        });
    }

    _updatePeersKinematics(dt) {
        if (this.peers.size === 0) return;

        // Sanfte Positions-Nachfuehrung (~4.2) + Velocity-Extrapolation: 0 Stop-and-Go-Ruckeln bei 950 ms Sync!
        const smooth = 1.0 - Math.exp(-dt * 4.2);
        const blendSmooth = 1.0 - Math.exp(-dt * 7.5);
        const strideFrequency = 4.48;
        const maxPeerX = this.pantheonHalfW - 0.6;

        this.peers.forEach((peer, pid) => {
            const prevX = peer.x;
            const prevZ = peer.z;

            const sincePacket = Math.max(0, this.elapsedTime - (peer.lastPacketAt || this.elapsedTime));
            const extrapFactor = sincePacket < 1.05 ? (1.0 - (sincePacket / 1.05) * 0.45) : 0.0;
            const predX = clamp(peer.targetX + (peer.vx || 0) * Math.min(sincePacket, 0.95) * extrapFactor, -maxPeerX, maxPeerX);
            const predZ = clamp(peer.targetZ + (peer.vz || 0) * Math.min(sincePacket, 0.95) * extrapFactor, this.pantheonMinZ + 0.8, this.hallNorthZ - 2.0);

            peer.x = lerp(peer.x, predX, smooth);
            peer.y = lerp(peer.y, peer.targetY, smooth);
            peer.z = lerp(peer.z, predZ, smooth);
            peer.yaw = lerpAngle(peer.yaw, peer.targetYaw, smooth);

            // Lauf-Animation orientiert sich strikt an echter Bewegungsdistanz (kein starrer 1.9s-Timer!)
            const stepDist = Math.hypot(peer.x - prevX, peer.z - prevZ);
            const measuredSpeed = stepDist / Math.max(0.001, dt);
            const effectiveSpeed = measuredSpeed;

            const floorOffset = this._computeFloorBaseY(peer.x, peer.z);
            const support = { shadowY: 0 };
            const aboveGround = Math.max(0, peer.y - support.shadowY);
            const airBlend = clamp(aboveGround / 0.26, 0, 1);
            const peerSprint = effectiveSpeed > 6.4 ? 1.0 : 0.0;

            peer.root.position.set(peer.x, floorOffset + peer.y, peer.z);
            peer.root.rotation.y = peer.yaw;

            if (stepDist > 0.00035 && airBlend < 0.65) {
                peer.walkPhase = (peer.walkPhase + stepDist * strideFrequency) % TWO_PI;
            }

            const targetBlend = effectiveSpeed > 0.14 ? clamp(effectiveSpeed / 4.6, 0.24, 1.0) : 0.0;
            peer.moveBlend = lerp(peer.moveBlend, targetBlend, blendSmooth);

            const mb = peer.moveBlend * (1.0 - airBlend * 0.65);
            const idlePeer = (1.0 - mb) * (1.0 - airBlend);
            const idleBreathPeer = Math.sin(this.elapsedTime * 2.0 + (peer.level || 1)) * 0.010 * idlePeer;
            const j = peer.joints;
            const pL = peer.walkPhase;
            const pR = pL + Math.PI;

            j.hipPivotL.rotation.x = -0.54 * Math.sin(pL) * mb - 0.32 * airBlend;
            j.hipPivotR.rotation.x = -0.54 * Math.sin(pR) * mb - 0.20 * airBlend;
            j.hipPivotL.rotation.z = -(0.018 * idlePeer + 0.024 * mb);
            j.hipPivotR.rotation.z = (0.018 * idlePeer + 0.024 * mb);
            j.kneePivotL.rotation.x = Math.max(0, Math.cos(pL - 0.16) * 0.72 * mb + 0.58 * airBlend);
            j.kneePivotR.rotation.x = Math.max(0, Math.cos(pR - 0.16) * 0.72 * mb + 0.44 * airBlend);
            j.anklePivotL.rotation.x = -Math.max(0, Math.cos(pL - 0.16)) * 0.24 * mb + 0.20 * airBlend;
            j.anklePivotR.rotation.x = -Math.max(0, Math.cos(pR - 0.16)) * 0.24 * mb + 0.20 * airBlend;

            j.shoulderPivotR.rotation.x = -0.42 * Math.sin(pL) * mb - 0.36 * airBlend;
            j.shoulderPivotR.rotation.z = 0.08;
            j.elbowPivotR.rotation.x = -0.18 - 0.32 * Math.max(0, Math.sin(pL)) * mb;
            j.wristPivotR.rotation.x = -0.08 * Math.cos(pL) * mb;

            j.shoulderPivotL.rotation.x = -0.42 * Math.sin(pR) * mb - 0.36 * airBlend;
            j.shoulderPivotL.rotation.z = -0.08;
            j.elbowPivotL.rotation.x = -0.18 - 0.32 * Math.max(0, Math.sin(pR)) * mb;
            j.wristPivotL.rotation.x = -0.08 * Math.cos(pR) * mb;

            const bounce = Math.cos(2.0 * pL) * 0.024 * mb;
            j.pelvisPivot.position.y = 0.776 + bounce - 0.014 * mb + idleBreathPeer;
            j.pelvisPivot.position.x = -Math.sin(pL) * 0.018 * mb;
            j.pelvisPivot.rotation.y = Math.sin(pL) * 0.065 * mb;
            j.pelvisPivot.rotation.z = -Math.sin(pL) * 0.030 * mb;
            j.torsoPivot.rotation.x = (0.08 + 0.10 * peerSprint) * mb + idleBreathPeer;
            j.torsoPivot.rotation.y = -Math.sin(pL) * 0.085 * mb;

            if (peer.emote && mb < 0.25) {
                const em = peer.emote;
                if (em === 'wave') {
                    j.shoulderPivotR.rotation.x = -2.35;
                    j.shoulderPivotR.rotation.z = 0.35;
                    j.elbowPivotR.rotation.x = -0.35;
                    j.wristPivotR.rotation.z = Math.sin(this.elapsedTime * 12.0) * 0.45;
                } else if (em === 'dance') {
                    const dT = this.elapsedTime * 7.5;
                    j.pelvisPivot.position.y += Math.abs(Math.sin(dT)) * 0.05;
                    j.shoulderPivotL.rotation.x = -1.2 + Math.sin(dT) * 0.5;
                    j.shoulderPivotR.rotation.x = -1.2 - Math.sin(dT) * 0.5;
                } else if (em === 'cheer') {
                    const cT = this.elapsedTime * 10.0;
                    j.pelvisPivot.position.y += Math.abs(Math.sin(cT * 0.5)) * 0.10;
                    j.shoulderPivotL.rotation.x = -2.45;
                    j.shoulderPivotR.rotation.x = -2.45;
                } else if (em === 'bow') {
                    j.torsoPivot.rotation.x += 0.72;
                    j.headPivot.rotation.x = 0.30;
                } else if (em === 'sit') {
                    j.pelvisPivot.position.y = 0.22;
                    j.hipPivotL.rotation.x = -1.55;
                    j.hipPivotR.rotation.x = -1.55;
                    j.kneePivotL.rotation.x = 1.65;
                    j.kneePivotR.rotation.x = 1.65;
                } else if (em === 'clap') {
                    const clT = this.elapsedTime * 14.0;
                    j.shoulderPivotL.rotation.x = -1.25;
                    j.shoulderPivotR.rotation.x = -1.25;
                    j.shoulderPivotL.rotation.z = -0.45 - Math.sin(clT) * 0.18;
                    j.shoulderPivotR.rotation.z = 0.45 + Math.sin(clT) * 0.18;
                    j.elbowPivotL.rotation.x = -0.7;
                    j.elbowPivotR.rotation.x = -0.7;
                }
            }

            if (j.thrusterFlameL && j.thrusterFlameR) {
                const peerThrusters = !(peer.builtRecord && peer.builtRecord.skinnedRig) && peerSprint > 0;
                j.thrusterFlameL.visible = peerThrusters;
                j.thrusterFlameR.visible = peerThrusters;
            }
            if (j.reactorRing) {
                j.reactorRing.rotation.z = this.elapsedTime * (peerSprint > 0 ? 4.0 : 1.1);
            }

            // Exakte Sohlen-Bodenkorrektur auf 0.004 - minSolePeerY + sauberer footShadow/baseRing-Sitz!
            const minSolePeerY = this._computeAvatarMinSoleY(j);
            if (aboveGround <= 0.001) {
                j.pelvisPivot.position.y += (0.004 - minSolePeerY);
            } else {
                const minAllowedPeerY = (support.shadowY - peer.y) + 0.004;
                if (minSolePeerY < minAllowedPeerY) {
                    j.pelvisPivot.position.y += (minAllowedPeerY - minSolePeerY);
                }
            }
            const localShY = (support.shadowY - peer.y) + 0.002;
            j.footShadow.position.y = localShY;
            j.baseRing.position.y = localShY;
            const sScale = 1.0 / (1.0 + aboveGround * 0.55);
            j.footShadow.scale.set(sScale, sScale, 1);
            j.sparkCrest.rotation.y = this.elapsedTime * 1.4;

            if (peer.builtRecord && peer.builtRecord.skinnedRig) {
                const peerState = airBlend > 0.25 ? 'jump' : (mb > 0.18 ? 'run' : 'idle');
                this._syncSkinnedFunkiBones(peer.builtRecord.skinnedRig, j, 0, {
                    dt,
                    speed: effectiveSpeed,
                    vy: 0,
                    turnRate: 0,
                    animState: peerState,
                });
            }

            if (peer.nameplateSprite) {
                peer.nameplateSprite.visible = !this.focusProtected && !this.soloMode;
            }
            if (peer.bubbleSprite) {
                const bubbleVisible = !this.focusProtected
                    && !this.soloMode
                    && !this.mutedPlayerIds.has(pid)
                    && this.elapsedTime < peer.bubbleExpiresAt;
                peer.bubbleSprite.visible = bubbleVisible;
            }
        });
    }

    _setFocusedSteleIndex(nextIdx) {
        if (this.focusedSteleIndex === nextIdx) return;
        this.focusedSteleIndex = nextIdx;

        if (nextIdx >= 0 && this.steles[nextIdx]) {
            const st = this.steles[nextIdx];
            this.activeIndex = nextIdx;
            this.focusProtected = true;
            this._setFocusedHeroSlot(-1);
            if (this.onActiveExhibitChange) {
                this.onActiveExhibitChange(nextIdx, st.product);
            }
            if (this.onFocusProductChange) {
                this.onFocusProductChange(st.product, nextIdx);
            }
        } else {
            this.focusProtected = false;
            if (this.onFocusProductChange) {
                this.onFocusProductChange(null, -1);
            }
        }
    }

    _setFocusedHeroSlot(nextSlot) {
        if (this.focusedHeroSlot === nextSlot) return;
        this.focusedHeroSlot = nextSlot;

        if (nextSlot >= 1 && nextSlot <= 5 && this.heroSlotAnchors[nextSlot]) {
            const ped = this.heroSlotAnchors[nextSlot];
            if (this.onFocusHeroChange) {
                this.onFocusHeroChange(ped.cardData, nextSlot);
            }
        } else if (this.onFocusHeroChange) {
            this.onFocusHeroChange(null, -1);
        }
    }

    _setPortalGateFocused(flag) {
        const next = Boolean(flag) && !this.canEnterSanctums;
        if (this.portalGateFocused === next) return;
        this.portalGateFocused = next;
        if (this.onPortalGatePromptChange) {
            this.onPortalGatePromptChange(next);
        }
    }

    _setWestOuterGateFocused(flag) {
        const next = Boolean(flag);
        if (this.westGateFocused === next) return;
        this.westGateFocused = next;
        if (this.onOuterGatePromptChange) {
            this.onOuterGatePromptChange(next);
        }
    }

    _setAtelierStarStationFocused(flag) {
        const next = Boolean(flag);
        if (this.atelierStarStationFocused === next) return;
        this.atelierStarStationFocused = next;
        if (!next && this.atelierStarHoverPreview !== 0) {
            this.atelierStarHoverPreview = 0;
            this._syncAtelierStarMaterials();
        }
        if (this.onAtelierStarStationFocusChange) {
            this.onAtelierStarStationFocusChange(next, this.getAtelierReviewState());
        }
    }

    _projectElementToWorld(el, worldX, worldY, worldZ, statePrefix) {
        if (!el || !this.camera) return;

        this._projVec.set(worldX, worldY, worldZ).project(this.camera);
        const visKey = `${statePrefix}Visible`;
        const xKey = `${statePrefix}ScreenX`;
        const yKey = `${statePrefix}ScreenY`;

        if (this._projVec.z < -1.0 || this._projVec.z > 1.0) {
            if (this[visKey]) {
                this[visKey] = false;
                el.style.opacity = '0';
                el.style.visibility = 'hidden';
                el.style.pointerEvents = 'none';
            }
            return;
        }

        const rawX = (this._projVec.x * 0.5 + 0.5) * this._viewW;
        const rawY = (-this._projVec.y * 0.5 + 0.5) * this._viewH;

        const marginX = Math.min(156, this._viewW * 0.46);
        const topSafeY = Math.min(254, Math.max(140, this._viewH * 0.34));
        const clampedX = clamp(rawX, marginX, Math.max(marginX, this._viewW - marginX));
        const clampedY = clamp(rawY, topSafeY, Math.max(topSafeY, this._viewH - 110));

        if (!this[visKey]) {
            this[visKey] = true;
            this[xKey] = clampedX;
            this[yKey] = clampedY;
            el.style.visibility = 'visible';
            el.style.opacity = '1';
            el.style.pointerEvents = 'none';
            el.style.transform = `translate3d(${clampedX.toFixed(1)}px, ${clampedY.toFixed(1)}px, 0) translate(-50%, -100%)`;
            return;
        }

        if (Math.abs(clampedX - this[xKey]) > 0.2 || Math.abs(clampedY - this[yKey]) > 0.2) {
            this[xKey] = clampedX;
            this[yKey] = clampedY;
            el.style.transform = `translate3d(${clampedX.toFixed(1)}px, ${clampedY.toFixed(1)}px, 0) translate(-50%, -100%)`;
        }
    }

    _hideProjectedElement(el, statePrefix) {
        const visKey = `${statePrefix}Visible`;
        if (el && this[visKey]) {
            this[visKey] = false;
            el.style.opacity = '0';
            el.style.visibility = 'hidden';
            el.style.pointerEvents = 'none';
        }
    }

    _updateFloatingCardProjection() {
        // 1. Schwebende Produktkarte (Bereich 1: Produkt-Eingangshalle)
        const idx = this.focusedSteleIndex;
        if (idx >= 0 && this.steles[idx] && this.currentZone === 'gallery') {
            const st = this.steles[idx];
            if (this.focusHaloMesh) {
                this.focusHaloMesh.visible = true;
                this.focusHaloMesh.position.set(st.x, 2.075, st.z);
                this.focusHaloMesh.rotation.z = this.elapsedTime * 0.45;
            }
            this._projectElementToWorld(this.floatingCardEl, st.x, 2.24, st.z, '_card');
        } else {
            if (this.focusHaloMesh) this.focusHaloMesh.visible = false;
            this._hideProjectedElement(this.floatingCardEl, '_card');
        }

        // 2. Schwebende Helden-Stats-Tafel (Bereich 2: Top-5-Ehrenpodeste in der Halle der Helden)
        const hSlot = this.focusedHeroSlot;
        if (hSlot >= 1 && hSlot <= 5 && this.heroSlotAnchors[hSlot] && this.currentZone === 'heroes') {
            const ped = this.heroSlotAnchors[hSlot];
            if (this.heroFocusHaloMesh) {
                this.heroFocusHaloMesh.visible = true;
                this.heroFocusHaloMesh.position.set(ped.worldX, 0.045, ped.worldZ);
                const s = ped.haloRadius / 1.48;
                this.heroFocusHaloMesh.scale.set(s, s, 1);
                this.heroFocusHaloMesh.rotation.z = this.elapsedTime * 0.55;
            }
            this._projectElementToWorld(this.floatingHeroCardEl, ped.worldX, ped.cardAnchorY, ped.worldZ, '_heroCard');
        } else {
            if (this.heroFocusHaloMesh) this.heroFocusHaloMesh.visible = false;
            this._hideProjectedElement(this.floatingHeroCardEl, '_heroCard');
        }

        // 3. Schwebendes Mitglieder-Lobby-Schild am Ehrenportal fuer nicht eingeloggte Gaeste
        if (this.portalGateFocused && !this.canEnterSanctums && this.currentZone === 'gallery') {
            this._projectElementToWorld(this.floatingPortalCardEl, 0, 2.75, this.hallNorthZ - 0.25, '_portalCard');
        } else {
            this._hideProjectedElement(this.floatingPortalCardEl, '_portalCard');
        }

        // 4. Schwebender Hinweis am monumentalen West-Prunktor ("Tor zur Außenwelt")
        if (this.westGateFocused && this.floatingOuterGateEl && this.currentZone === 'gallery') {
            this._projectElementToWorld(this.floatingOuterGateEl, -11.65, 2.75, 0.0, '_outerGateCard');
        } else {
            this._hideProjectedElement(this.floatingOuterGateEl, '_outerGateCard');
        }

        // 5. 1-Klick Light-Mode Bewertungs-Kaertchen an der 3D-Sternebewertungs-Station neben dem Ehrenportal
        if (this.atelierStarStationFocused && this.atelierStarStation && this.floatingStarStationEl && this.currentZone === 'gallery') {
            this._projectElementToWorld(
                this.floatingStarStationEl,
                this.atelierStarStation.x,
                2.64,
                this.atelierStarStation.z,
                '_starStationCard'
            );
        } else {
            this._hideProjectedElement(this.floatingStarStationEl, '_starStationCard');
        }
    }

    openOuterGateModalFromWorld() {
        if (this.destroyed || !this.running) return false;
        this.releasePointerLock();
        if (this.audio && typeof this.audio.playUiSwitch === 'function') {
            this.audio.playUiSwitch();
        } else if (this.audio && typeof this.audio.playGateLocked === 'function') {
            this.audio.playGateLocked();
        }
        if (this.onOuterGateModalOpen) {
            this.onOuterGateModalOpen(this.getMmorpgAccessState());
            return true;
        }
        return false;
    }

    _triggerOuterGateRepulse() {
        if (this._gateRepulseTimer > 0.04) return;

        this._gateRepulseDuration = 0.28;
        this._gateRepulseTimer = 0.28;
        this._gateRepulseInputDampTimer = 0.28;
        this._gateRepulseDirX = 1.0;
        this._gateRepulseDirZ = 0.0;

        this.player.autoTarget = null;
        this.player.autoLookAt = null;
        this.walkTargetTimer = 0;
        this.player.isJumping = true;
        this.player.jumpCount = 1;
        this.player.vy = 2.65;

        this._spawnDoubleJumpBurst(-11.55, 1.10, this.player.z || 0);
        if (this.audio && typeof this.audio.playGateLocked === 'function') {
            this.audio.playGateLocked();
        }
        this._setWestOuterGateFocused(true);

        if (this._outerGatePromptPendingTimer) {
            clearTimeout(this._outerGatePromptPendingTimer);
        }
        this._outerGatePromptPendingTimer = setTimeout(() => {
            this._outerGatePromptPendingTimer = 0;
            if (!this.destroyed && this.running) {
                this.openOuterGateModalFromWorld();
            }
        }, 140);
    }

    _bindEvents() {
        const el = this.canvas;
        window.addEventListener('resize', this._boundResize);
        document.addEventListener('fullscreenchange', this._boundFullscreenResize);
        document.addEventListener('webkitfullscreenchange', this._boundFullscreenResize);
        window.addEventListener('keydown', this._boundKeyDown);
        window.addEventListener('keyup', this._boundKeyUp);
        window.addEventListener('blur', this._boundWindowBlur);

        if (typeof ResizeObserver !== 'undefined' && el && el.parentElement) {
            this._resizeObserver = new ResizeObserver(() => {
                if (!this.destroyed) this.resize();
            });
            this._resizeObserver.observe(el.parentElement);
        }

        el.addEventListener('contextmenu', this._boundContextMenu);
        el.addEventListener('pointerdown', this._boundPointerDown);
        el.addEventListener('pointermove', this._boundPointerMove);
        el.addEventListener('pointerup', this._boundPointerUp);
        el.addEventListener('pointercancel', this._boundPointerUp);
        el.addEventListener('wheel', this._boundWheel, { passive: false });
        el.addEventListener('touchstart', this._boundTouchStart, { passive: true });
        el.addEventListener('touchmove', this._boundTouchMove, { passive: true });
    }

    _resetMouseButtons() {
        this.camControl.mouseLeftDown = false;
        this.camControl.mouseRightDown = false;
        this.camControl.bothMouseWalk = false;
        this.camControl.hadChordWalk = false;
        this.camControl.isDragging = false;
        this.keys = {};
        if (this.player) {
            this.player.isSprinting = false;
        }
    }

    _onKeyDown(e) {
        if (!this.running) return;
        const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
        if (e.target && e.target.closest && e.target.closest('[data-testid="lobby-settings-modal"], [data-testid="lobby-sanctum-gate-modal"], [data-testid="lobby-outer-gate-modal"], [data-testid="atelier-star-rating-card"]')) {
            return;
        }

        const code = e.code || e.key;
        const kb = this.keybindings;

        if (code) {
            this.keys[code] = true;
        }
        if (code === 'ShiftLeft' || code === 'ShiftRight' || e.key === 'Shift' || e.shiftKey === true) {
            if (code === 'ShiftLeft') this.keys.ShiftLeft = true;
            if (code === 'ShiftRight') this.keys.ShiftRight = true;
            this.keys.Shift = true;
        }

        if (matchesActionKey(code, kb.jump) || (kb.jump === 'Space' && e.key === ' ')) {
            e.preventDefault();
            this.triggerJump();
            return;
        }

        if (code === 'Enter' || code === 'KeyE') {
            if (this.focusedSteleIndex >= 0 && this.steles[this.focusedSteleIndex]) {
                const prod = this.steles[this.focusedSteleIndex].product;
                if (this.onSelectProduct) {
                    this.onSelectProduct(prod, this.focusedSteleIndex);
                } else if (prod && prod.product_url) {
                    window.location.href = prod.product_url;
                }
                return;
            }
            if (this.currentZone === 'gallery' && this.westGateFocused) {
                e.preventDefault();
                this.openOuterGateModalFromWorld();
            }
        }
    }

    _onKeyUp(e) {
        const code = e.code || e.key;
        if (code) {
            this.keys[code] = false;
        }
        if (code === 'ShiftLeft' || code === 'ShiftRight' || e.key === 'Shift') {
            if (code === 'ShiftLeft') this.keys.ShiftLeft = false;
            if (code === 'ShiftRight') this.keys.ShiftRight = false;
            if (!this.keys.ShiftLeft && !this.keys.ShiftRight) {
                this.keys.Shift = false;
            }
        }
        if (e.shiftKey === false) {
            this.keys.ShiftLeft = false;
            this.keys.ShiftRight = false;
            this.keys.Shift = false;
        }
    }

    _syncMouseChordFromButtons(buttonsMask) {
        if (typeof buttonsMask !== 'number') return;
        this.camControl.mouseLeftDown = (buttonsMask & 1) === 1;
        this.camControl.mouseRightDown = (buttonsMask & 2) === 2;
        const bothNow = (buttonsMask & 3) === 3;
        this.camControl.bothMouseWalk = bothNow;
        if (bothNow) {
            this.camControl.hadChordWalk = true;
        }
    }

    _hasActiveMovementKeys() {
        const kb = this.keybindings || {};
        if (this.keys[kb.moveForward] || this.keys[kb.moveBack] || this.keys[kb.moveLeft] || this.keys[kb.moveRight]) return true;
        if (this.keys.ArrowUp || this.keys.ArrowDown || this.keys.ArrowLeft || this.keys.ArrowRight) return true;
        if (this.camControl.bothMouseWalk) return true;
        if (Math.abs(this.joystick.x) > 0.04 || Math.abs(this.joystick.y) > 0.04) return true;
        return false;
    }

    _onPointerDown(e) {
        if (!this.running) return;
        if (e && e.target && e.target !== this.canvas && typeof e.target.closest === 'function') {
            if (e.target.closest('.sf-lobby-settings-backdrop, .sf-lobby-floating-card, .sf-atelier-star-card, .sf-lobby-topbar, .sf-lobby-gold-crest-bar, .sf-lobby-chat-panel, .sf-lobby-instance-popover, .sf-lobby-bottom-pill')) {
                return;
            }
        }
        if (e.pointerType === 'mouse' || e.pointerType === undefined) {
            if (typeof e.buttons === 'number' && e.buttons > 0) {
                this._syncMouseChordFromButtons(e.buttons);
            } else {
                if (e.button === 0) this.camControl.mouseLeftDown = true;
                if (e.button === 2) this.camControl.mouseRightDown = true;
                this.camControl.bothMouseWalk = Boolean(this.camControl.mouseLeftDown && this.camControl.mouseRightDown);
                if (this.camControl.bothMouseWalk) {
                    this.camControl.hadChordWalk = true;
                }
            }
            this.camControl.downButton = e.button;
        } else {
            this.camControl.downButton = 0;
        }

        if (!this.camControl.isDragging) {
            this.camControl.dragMoved = 0;
        }
        this.camControl.isDragging = true;
        this.camControl.lastX = e.clientX;
        this.camControl.lastY = e.clientY;
        this.canvas.style.cursor = 'grabbing';
        try {
            this.canvas.setPointerCapture(e.pointerId);
        } catch (err) {
            // PointerCapture optional
        }
    }

    _onPointerMove(e) {
        if (!this.running) return;

        const isPointerLocked = typeof document !== 'undefined' && document.pointerLockElement === this.canvas;
        if (isPointerLocked) {
            const dxLock = Number(e.movementX) || 0;
            const dyLock = Number(e.movementY) || 0;
            if (dxLock !== 0 || dyLock !== 0) {
                const sens = this.cameraSensitivityFactor || 1.0;
                this.camControl.desiredYaw -= dxLock * 0.0048 * sens;
                const minPitchLock = this.camControl.desiredDistance < 0.55 ? -1.18 : -0.72;
                this.camControl.desiredPitch = clamp(this.camControl.desiredPitch + dyLock * 0.0038 * sens, minPitchLock, 0.82);
                if (this.camControl.desiredDistance < 0.55) {
                    this.player.yaw = Math.atan2(
                        Math.sin(this.camControl.desiredYaw + Math.PI),
                        Math.cos(this.camControl.desiredYaw + Math.PI)
                    );
                }
            }
            return;
        }

        if (e.pointerType === 'mouse' && typeof e.buttons === 'number') {
            this._syncMouseChordFromButtons(e.buttons);
            if (e.buttons === 0 && this.camControl.isDragging) {
                this.camControl.isDragging = false;
                this.camControl.hadChordWalk = false;
                this.canvas.style.cursor = 'grab';
            }
        }

        if (this.camControl.isDragging) {
            const dx = e.clientX - this.camControl.lastX;
            const dy = e.clientY - this.camControl.lastY;
            this.camControl.lastX = e.clientX;
            this.camControl.lastY = e.clientY;
            this.camControl.dragMoved += Math.hypot(dx, dy);

            const sens = this.cameraSensitivityFactor || 1.0;
            this.camControl.desiredYaw -= dx * 0.0055 * sens;
            const minPitch = this.camControl.desiredDistance < 0.55 ? -1.18 : -0.72;
            this.camControl.desiredPitch = clamp(this.camControl.desiredPitch + dy * 0.0042 * sens, minPitch, 0.82);

            if (this.camControl.desiredDistance < 0.55) {
                this.player.yaw = normalizeAngleSigned(this.camControl.desiredYaw + Math.PI);
            } else if (this.camControl.bothMouseWalk) {
                this.camControl.yaw = this.camControl.desiredYaw;
            } else if (
                this.camControl.mouseRightDown
                && !this.camControl.mouseLeftDown
                && this.camControl.dragMoved > 2
                && !this._hasActiveMovementKeys()
                && !this.player.isMoving
                && !this.player.isWallClinging
            ) {
                this.player.yaw = lerpAngle(this.player.yaw, this.camControl.desiredYaw + Math.PI, 0.35);
            }
            return;
        }

        this._hoverTickCounter = (this._hoverTickCounter + 1) % 3;
        if (this._hoverTickCounter !== 0 || !this.camera || !this.canvas) return;

        const rect = this.canvas.getBoundingClientRect();
        this.pointerNdc.x = ((e.clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((e.clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);

        const targets = this.interactiveHitMeshes
            .concat(this.canEnterSanctums ? [] : this.portalHitMeshes)
            .concat(this.heroInteractiveHitMeshes)
            .concat(this.westGateHitMeshes);

        if (targets.length > 0 && this.raycaster.intersectObjects(targets, false).length > 0) {
            this.canvas.style.cursor = 'pointer';
        } else {
            this.canvas.style.cursor = 'grab';
        }
    }

    _onPointerUp(e) {
        const wasChord = this.camControl.hadChordWalk;
        const moved = this.camControl.dragMoved;

        if (e.pointerType === 'mouse' || e.pointerType === undefined) {
            if (typeof e.buttons === 'number') {
                this._syncMouseChordFromButtons(e.buttons);
            } else {
                if (e.button === 0) this.camControl.mouseLeftDown = false;
                if (e.button === 2) this.camControl.mouseRightDown = false;
                this.camControl.bothMouseWalk = false;
            }
        } else {
            this.camControl.mouseLeftDown = false;
            this.camControl.mouseRightDown = false;
            this.camControl.bothMouseWalk = false;
        }

        const anyStillHeld = Boolean(this.camControl.mouseLeftDown || this.camControl.mouseRightDown);
        if (!anyStillHeld) {
            this.camControl.isDragging = false;
            this.camControl.hadChordWalk = false;
            this.canvas.style.cursor = 'grab';
            try {
                this.canvas.releasePointerCapture(e.pointerId);
            } catch (err) {
                // PointerCapture optional
            }
        }

        if (!wasChord && moved < 7 && (e.button === 0 || e.pointerType === 'touch') && !this.camControl.mouseRightDown) {
            if (e.pointerType === 'touch' && this.canvas) {
                const rect = this.canvas.getBoundingClientRect();
                if (e.clientX > rect.left + rect.width * 0.48) {
                    const nowTap = this._nowClientMs();
                    if ((nowTap - (this._lastRightTapMs || 0)) < 310) {
                        this._lastRightTapMs = 0;
                        this.triggerJump();
                        return;
                    }
                    this._lastRightTapMs = nowTap;
                }
            }
            this._handleCanvasTap(e.clientX, e.clientY);
        }
    }

    _onWheel(e) {
        if (!this.running) return;
        e.preventDefault();
        const maxDist = this.currentZone === 'heroes' ? 10.5 : 7.8;
        if (e.deltaY < 0) {
            let next = this.camControl.desiredDistance * 0.88 - 0.14;
            if (next < 0.95) next = 0;
            this.camControl.desiredDistance = Math.max(0, next);
        } else if (e.deltaY > 0) {
            if (this.camControl.desiredDistance < 0.95) {
                this.camControl.desiredDistance = 1.65;
            } else {
                this.camControl.desiredDistance = clamp(this.camControl.desiredDistance * 1.09 + 0.08, 0, maxDist);
            }
            if (this.camControl.desiredDistance >= 0.95) {
                this.camControl.desiredPitch = clamp(this.camControl.desiredPitch, -0.72, 0.82);
            }
        }
    }

    _onTouchStart(e) {
        if (e.touches.length === 1) {
            this._touchSwipeStart = {
                x: e.touches[0].clientX,
                y: e.touches[0].clientY,
                t: this._nowClientMs(),
                jumped: false,
            };
        } else if (e.touches.length === 2) {
            this._touchSwipeStart = null;
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            this.camControl.pinchDist = Math.hypot(dx, dy);
        }
    }

    _onTouchMove(e) {
        if (e.touches.length === 1 && this._touchSwipeStart && !this._touchSwipeStart.jumped && this.canvas) {
            const rect = this.canvas.getBoundingClientRect();
            if (this._touchSwipeStart.x > rect.left + rect.width * 0.44) {
                const dy = e.touches[0].clientY - this._touchSwipeStart.y;
                const dx = e.touches[0].clientX - this._touchSwipeStart.x;
                const elapsed = this._nowClientMs() - this._touchSwipeStart.t;
                if (dy < -42 && Math.abs(dx) < 48 && elapsed < 280) {
                    this._touchSwipeStart.jumped = true;
                    this.triggerJump();
                    return;
                }
            }
        }
        if (e.touches.length === 2 && this.camControl.pinchDist > 8) {
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            const dist = Math.hypot(dx, dy);
            const scale = this.camControl.pinchDist / Math.max(8, dist);
            this.camControl.pinchDist = dist;
            const maxDist = this.currentZone === 'heroes' ? 10.5 : 7.8;
            if (scale < 0.996) {
                let next = scale < 0.90
                    ? (this.camControl.desiredDistance * 0.88 - 0.14)
                    : (this.camControl.desiredDistance * scale - (1 - scale) * 1.2);
                if (next < 0.95) next = 0;
                this.camControl.desiredDistance = Math.max(0, next);
            } else if (scale > 1.004) {
                if (this.camControl.desiredDistance < 0.95) {
                    this.camControl.desiredDistance = 1.65;
                } else {
                    this.camControl.desiredDistance = clamp(this.camControl.desiredDistance * scale + (scale - 1) * 0.5, 0, maxDist);
                }
                if (this.camControl.desiredDistance >= 0.95) {
                    this.camControl.desiredPitch = clamp(this.camControl.desiredPitch, -0.72, 0.82);
                }
            }
        }
    }

    _handleCanvasTap(clientX, clientY) {
        if (!this.canvas || !this.camera) return;
        const rect = this.canvas.getBoundingClientRect();
        this.pointerNdc.x = ((clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);

        // 1. Monumentales Tor zur Aussenwelt an der Westwand
        if (this.westGateHitMeshes.length > 0) {
            const wgHits = this.raycaster.intersectObjects(this.westGateHitMeshes, false);
            if (wgHits.length > 0) {
                this._setWestOuterGateFocused(true);
                if (this.currentZone === 'gallery') {
                    this.player.autoTarget = { x: -10.35, z: 0.0 };
                    this.player.autoLookAt = { x: -11.92, z: 0.0 };
                }
                this.openOuterGateModalFromWorld();
                return;
            }
        }

        // 2. Top-5-Heldenpodeste oder Ehren-Stelen in der Halle der Helden
        if (this.heroInteractiveHitMeshes.length > 0 && this.canEnterSanctums) {
            const heroHits = this.raycaster.intersectObjects(this.heroInteractiveHitMeshes, false);
            if (heroHits.length > 0) {
                const slot = heroHits[0].object.userData.heroSlot;
                this.goToHeroSlot(slot);
                return;
            }
        }

        // 3. Ehrenportal als Gast (berechtigte Spieler laufen frei hindurch!)
        if (!this.canEnterSanctums && this.portalHitMeshes.length > 0) {
            const portalHits = this.raycaster.intersectObjects(this.portalHitMeshes, false);
            if (portalHits.length > 0) {
                this.goToZone('heroes', false);
                return;
            }
        }

        // 4. 3D-Sternebewertungs-Station & Produkt-Podeste im 3D-Produkt-Atelier
        if (this.interactiveHitMeshes.length > 0) {
            const steleHits = this.raycaster.intersectObjects(this.interactiveHitMeshes, false);
            if (steleHits.length > 0) {
                const hitData = steleHits[0].object.userData || {};
                if (hitData.isAtelierStarStation) {
                    this.goToAtelierStarStation();
                    return;
                }
                const idx = hitData.steleIndex;
                if (typeof idx === 'number') {
                    this.goToExhibit(idx, false);
                    return;
                }
            }
        }

        // 5. Click-to-Walk auf begehbare Boeden
        if (this.walkableFloorMeshes.length > 0) {
            const floorHits = this.raycaster.intersectObjects(this.walkableFloorMeshes, false);
            if (floorHits.length > 0) {
                const pt = floorHits[0].point;
                const targetPos = this._resolveCollisions(pt.x, pt.z);

                this.pinnedSteleIndex = -1;
                this.pinnedHeroSlot = -1;
                this.pinnedPortalGate = false;
                this.player.autoTarget = { x: targetPos.x, z: targetPos.z };
                this.player.autoLookAt = null;

                if (this.clickMoveRing) {
                    const ringY = targetPos.z < this.hallNorthZ - 0.5 ? 0.036 : 0.018;
                    this.clickMoveRing.position.set(targetPos.x, ringY, targetPos.z);
                    this.walkTargetTimer = 1.35;
                }
                if (this.audio) this.audio.playUiClick();
            }
        }
    }

    /**
     * Wechselt sanft oder sofort zwischen 'gallery' und 'heroes' (inkl. Login-Gating).
     */
    goToZone(targetZone, instant = false) {
        const keepFp = Boolean(this.running && this.camControl.desiredDistance < 0.55);
        const raw = String(targetZone || 'gallery').toLowerCase();
        const cleanZone = (raw === 'heroes' || raw === 'hall') ? 'heroes' : 'gallery';

        if (cleanZone === 'heroes') {
            this._setWestOuterGateFocused(false);
            if (!this.canEnterSanctums) {
                if (this.player.x > this.hallHalfW || this.player.x < -this.hallHalfW) {
                    this.player.x = 0;
                    this.player.y = 0;
                    this.player.groundY = 0;
                    this.player.z = 2.0;
                }
                this.pinnedSteleIndex = -1;
                this.pinnedPortalGate = true;
                this.player.autoTarget = { x: 0, z: this.hallNorthZ + 2.4 };
                this.player.autoLookAt = { x: 0, z: this.hallNorthZ - 2.0 };
                this.camControl.desiredYaw = 0;
                this.camControl.desiredPitch = keepFp ? 0.05 : 0.20;
                this.camControl.desiredDistance = keepFp ? 0 : 4.2;
                this._setPortalGateFocused(true);
                if (this.onAuthGatePrompt) {
                    this.onAuthGatePrompt('heroes');
                }
                if (this.currentZone !== 'gallery') {
                    this.currentZone = 'gallery';
                    this._syncZoneGroupVisibility();
                    if (this.onZoneChange) this.onZoneChange('gallery');
                }
                return;
            }

            const destX = 0;
            const destZ = this.pantheonCenterZ + 9.2;
            this.pinnedSteleIndex = -1;
            this.pinnedHeroSlot = -1;
            this._setFocusedSteleIndex(-1);

            if (instant || this.player.x > this.hallHalfW || Math.abs(this.player.z - destZ) > 18.0) {
                this.player.x = destX;
                this.player.y = 0;
                this.player.groundY = 0;
                this.player.vy = 0;
                this.player.isJumping = false;
                this.player.jumpCount = 0;
                this.player.z = destZ;
                this.player.yaw = Math.PI;
                this.player.autoTarget = null;
                this.player.autoLookAt = null;
                this.camControl.yaw = 0;
                this.camControl.desiredYaw = 0;
                this.camControl.pitch = keepFp ? 0.04 : 0.16;
                this.camControl.desiredPitch = keepFp ? 0.04 : 0.16;
                this.camControl.distance = keepFp ? 0 : 5.4;
                this.camControl.desiredDistance = keepFp ? 0 : 5.4;
                if (this.camControl.lookAtCurrent) {
                    this.camControl.lookAtCurrent.set(destX, 1.48, destZ);
                }
            } else {
                this.player.autoTarget = { x: destX, z: destZ };
                this.player.autoLookAt = { x: 0, z: this.pantheonCenterZ - 7.8 };
                this.camControl.desiredYaw = 0;
                this.camControl.desiredPitch = keepFp ? 0.04 : 0.16;
                this.camControl.desiredDistance = keepFp ? 0 : 5.4;
            }

            if (this.currentZone !== 'heroes') {
                this.currentZone = 'heroes';
                this._syncZoneGroupVisibility();
                if (this.onZoneChange) this.onZoneChange('heroes');
            } else {
                this._syncZoneGroupVisibility();
            }
            this.refreshHeroesData(false);
            return;
        }

        // Zurueck in das 3D-Produkt-Atelier ('gallery')
        this.pinnedHeroSlot = -1;
        this.pinnedPortalGate = false;
        this._setFocusedHeroSlot(-1);
        this._setPortalGateFocused(false);

        const galleryDestX = 0;
        const galleryDestZ = this.hallSouthZ - 4.2;
        if (instant || this.player.x > 12.2 || Math.abs(this.player.z - galleryDestZ) > 18.0) {
            this.player.x = galleryDestX;
            this.player.y = 0;
            this.player.groundY = 0;
            this.player.vy = 0;
            this.player.isJumping = false;
            this.player.jumpCount = 0;
            this.player.z = galleryDestZ;
            this.player.yaw = Math.PI;
            this.player.autoTarget = null;
            this.player.autoLookAt = null;
            this.camControl.yaw = 0;
            this.camControl.desiredYaw = 0;
            this.camControl.desiredPitch = keepFp ? 0.06 : 0.24;
            this.camControl.distance = keepFp ? 0 : this.camControl.distance;
            this.camControl.desiredDistance = keepFp ? 0 : 4.35;
            if (this.camControl.lookAtCurrent) {
                this.camControl.lookAtCurrent.set(galleryDestX, 1.38, galleryDestZ);
            }
        } else {
            this.player.autoTarget = { x: galleryDestX, z: galleryDestZ };
            this.player.autoLookAt = { x: 0, z: this.hallNorthZ };
            this.camControl.desiredYaw = 0;
            this.camControl.desiredPitch = keepFp ? 0.06 : 0.24;
            this.camControl.desiredDistance = keepFp ? 0 : 4.35;
        }

        if (this.currentZone !== 'gallery') {
            this.currentZone = 'gallery';
            this._syncZoneGroupVisibility();
            if (this.onZoneChange) this.onZoneChange('gallery');
        } else {
            this._syncZoneGroupVisibility();
        }
    }

    goToHeroSlot(slotNumber) {
        if (!this.canEnterSanctums) {
            this.goToZone('heroes', false);
            return;
        }

        const keepFp = Boolean(this.running && this.camControl.desiredDistance < 0.55);
        const slot = clamp(parseInt(slotNumber, 10) || 1, 1, 999);
        const anchor = this.heroSlotAnchors[slot] || this.heroSlotAnchors[1];
        if (!anchor) return;

        if (this.player.z > this.hallNorthZ - 3.0 || this.player.x > this.hallHalfW) {
            this.player.x = 0;
            this.player.y = 0;
            this.player.groundY = 0;
            this.player.z = this.pantheonCenterZ + 11.5;
            if (this.camControl.lookAtCurrent) {
                this.camControl.lookAtCurrent.set(0, 1.48, this.player.z);
            }
        }

        this.pinnedSteleIndex = -1;
        this._setFocusedSteleIndex(-1);

        this.player.autoTarget = { x: anchor.standX, z: anchor.standZ };
        this.player.autoLookAt = { x: anchor.worldX, z: anchor.worldZ };

        const lookYaw = Math.atan2(anchor.standX - anchor.worldX, anchor.standZ - anchor.worldZ);
        this.camControl.desiredYaw = lookYaw;
        this.camControl.desiredPitch = keepFp ? 0.04 : 0.18;
        this.camControl.desiredDistance = keepFp ? 0 : 4.85;

        if (this.walkTargetRing) {
            this.walkTargetRing.position.set(anchor.standX, 0.035, anchor.standZ);
            this.walkTargetTimer = 1.25;
        }

        if (slot <= 5) {
            this.pinnedHeroSlot = slot;
            this._setFocusedHeroSlot(slot);
        } else {
            this.pinnedHeroSlot = -1;
            this._setFocusedHeroSlot(-1);
        }

        if (this.currentZone !== 'heroes') {
            this.currentZone = 'heroes';
            this._syncZoneGroupVisibility();
            if (this.onZoneChange) this.onZoneChange('heroes');
        }
    }

    goToMyHeroPlace() {
        const mySlot = (this.heroesPayload && this.heroesPayload.my_hero_slot)
            ? parseInt(this.heroesPayload.my_hero_slot, 10)
            : 0;
        if (mySlot > 0 && this.heroSlotAnchors[mySlot]) {
            this.goToHeroSlot(mySlot);
            return;
        }
        const firstFree = Math.min(TOTAL_HERO_DISPLAY_SLOTS, ((this.heroesPayload && this.heroesPayload.total_heroes) || 0) + 1);
        this.goToHeroSlot(firstFree);
    }

    goToExhibit(index, triggerSelectCallback = false) {
        if (this.steles.length === 0) return;

        const keepFp = Boolean(this.running && this.camControl.desiredDistance < 0.55);
        if (this.player.z < this.hallNorthZ - 2.0 || this.player.x > this.hallHalfW + 1.1) {
            this.goToZone('gallery', true);
        }

        const safeIdx = ((index % this.steles.length) + this.steles.length) % this.steles.length;
        const stele = this.steles[safeIdx];
        this.activeIndex = safeIdx;
        this.pinnedSteleIndex = safeIdx;
        this.pinnedHeroSlot = -1;

        this.player.autoTarget = { x: stele.standX, z: stele.standZ };
        this.player.autoLookAt = { x: stele.x, z: stele.z };

        const lookYaw = Math.atan2(stele.standX - stele.x, stele.standZ - stele.z);
        this.camControl.desiredYaw = lookYaw;
        this.camControl.desiredPitch = keepFp ? 0.08 : 0.24;
        this.camControl.desiredDistance = keepFp ? 0 : 3.85;

        if (this.walkTargetRing) {
            this.walkTargetRing.position.set(stele.standX, 0.015, stele.standZ);
            this.walkTargetTimer = 1.2;
        }

        this._setFocusedSteleIndex(safeIdx);

        if (triggerSelectCallback && this.onSelectProduct) {
            this.onSelectProduct(stele.product, safeIdx);
        }
    }

    setJoystickVector(nx, ny) {
        this.joystick.x = clamp(Number(nx) || 0, -1, 1);
        this.joystick.y = clamp(Number(ny) || 0, -1, 1);
    }

    _findNearestStele(maxRadius) {
        let best = null;
        let bestDist = maxRadius;
        for (let i = 0; i < this.steles.length; i++) {
            const st = this.steles[i];
            const d = Math.hypot(this.player.x - st.x, this.player.z - st.z);
            if (d < bestDist) {
                bestDist = d;
                best = st;
            }
        }
        return best;
    }

    _findNearestHeroPedestal(maxRadius) {
        let best = null;
        let bestDist = maxRadius;
        for (let i = 0; i < this.heroPedestals.length; i++) {
            const ped = this.heroPedestals[i];
            const surfaceDist = Math.hypot(this.player.x - ped.worldX, this.player.z - ped.worldZ);
            if (surfaceDist < bestDist) {
                bestDist = surfaceDist;
                best = ped;
            }
        }
        return best;
    }

    _spawnGateRepulseFieldBurst() {
        if (!this.scene || !this.THREE) return;
        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const gx = this.player.x || 0;
        const gy = 1.15;
        const gz = this.hallNorthZ + 0.45;

        const group = new THREE.Group();
        group.position.set(gx, gy, gz);

        const ringMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#DC2626'),
            transparent: true,
            opacity: 0.92,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const ringMesh = new THREE.Mesh(shared.geos.shockwaveRing, ringMat);
        group.add(ringMesh);

        const sparks = [];
        const sparkMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#C5A059'),
            transparent: true,
            opacity: 0.96,
            depthWrite: false,
        });
        for (let i = 0; i < 18; i++) {
            const sm = new THREE.Mesh(shared.geos.sparkDiamond, sparkMat);
            const ang = (i / 18) * TWO_PI;
            const r0 = 0.25;
            sm.position.set(Math.cos(ang) * r0, Math.sin(ang) * r0, 0);
            sparks.push({
                mesh: sm,
                vx: Math.cos(ang) * 1.65,
                vy: Math.sin(ang) * 1.65,
                vz: 1.4 + (i % 3) * 0.6,
            });
            group.add(sm);
        }

        this.scene.add(group);
        this.weaponBursts.push({
            group,
            slashMesh: ringMesh,
            arcMat: ringMat,
            sparkMat,
            sparks,
            age: 0,
            duration: 0.54,
            maxRingScale: 1.95,
            isRemote: false,
        });
    }

    _triggerSanctumGateRepulse() {
        if (this._gateRepulseTimer > 0.04) return;

        this._gateRepulseDuration = 0.38;
        this._gateRepulseTimer = 0.38;
        this._gateRepulseInputDampTimer = 0.38;
        this._gateRepulseDirX = 0.0;
        this._gateRepulseDirZ = 1.0;

        this.player.autoTarget = null;
        this.player.autoLookAt = null;
        this.walkTargetTimer = 0;
        this.pinnedPortalGate = false;
        this.player.isJumping = true;
        this.player.jumpCount = 1;
        this.player.vy = 4.1;
        this.camControl.shakeTimer = 0.11;

        this._spawnGateRepulseFieldBurst();
        if (this.audio && typeof this.audio.playGateRepulse === 'function') {
            this.audio.playGateRepulse();
        } else if (this.audio && typeof this.audio.playGateLocked === 'function') {
            this.audio.playGateLocked();
        }

        if (this._gatePromptCooldown <= 0 && this.onAuthGatePrompt) {
            this._gatePromptCooldown = 1.4;
            if (this._gatePromptPendingTimer) {
                clearTimeout(this._gatePromptPendingTimer);
            }
            this._gatePromptPendingTimer = setTimeout(() => {
                this._gatePromptPendingTimer = 0;
                if (!this.destroyed && !this.canEnterSanctums && this.onAuthGatePrompt) {
                    this.onAuthGatePrompt('heroes');
                }
            }, 170);
        }
    }

    /**
     * Nahtlose 2-Zonen-Kollisionsaufloesung (3D-Produkt-Atelier inkl. Tor zur Aussenwelt + Ehrenportal/Promenade + Goldene Halle der Helden).
     */
    _resolveCollisions(nx, nz) {
        let x = nx;
        let z = nz;
        let hitWall = false;
        let wallNormalX = 0;
        let wallNormalZ = 0;

        const recordWall = (wnx, wnz) => {
            hitWall = true;
            wallNormalX = wnx;
            wallNormalZ = wnz;
        };

        const minPantheonZ = this.pantheonMinZ + 0.42;
        const maxSouthZ = this.hallSouthZ - 0.42;
        if (z < minPantheonZ) {
            z = minPantheonZ;
            recordWall(0, 1);
        } else if (z > maxSouthZ) {
            z = maxSouthZ;
            recordWall(0, -1);
        }

        // Unberechtigte Spieler (!canEnterSanctums) an der Nord-Schwelle zur Halle der Helden sanft abweisen
        if (!this.canEnterSanctums && z < this.hallNorthZ + 0.85) {
            z = this.hallNorthZ + 0.85;
            if (Math.abs(x) <= 4.25) {
                this._triggerSanctumGateRepulse();
            } else {
                recordWall(0, 1);
            }
        } else if (z > this.hallNorthZ + 1.35) {
            this._heroesAuthGateTriggered = false;
        }

        const portalClearHalfW = this.portalHalfW - 0.52;

        if (z >= this.hallNorthZ + 0.35) {
            // Zone 1: 3D-Produkt-Atelier (realistisch nahe Wandabstaende 0.38..0.44 m + West-Tor bei X = -11.35)
            if (Math.abs(z) <= 2.45 && x < -11.35) {
                x = -11.35;
                recordWall(1, 0);
                if (this._westOuterGateArmed) {
                    this._westOuterGateArmed = false;
                    this._triggerOuterGateRepulse();
                }
            }
            const minWestX = -this.hallHalfW + 0.38;
            const maxEastX = this.hallHalfW - 0.38;
            if (x < minWestX) {
                x = minWestX;
                recordWall(1, 0);
            } else if (x > maxEastX) {
                x = maxEastX;
                recordWall(-1, 0);
            }
            if (z < this.hallNorthZ + 0.44 && Math.abs(x) > portalClearHalfW && this.player.z >= this.hallNorthZ + 0.30) {
                z = this.hallNorthZ + 0.44;
                recordWall(0, 1);
            }

            // Sanfte Kreiskollisionen um Halbrund-Wandpilaster (r = 0.54)
            if (this._galleryPierPlacements) {
                for (let pi = 0; pi < this._galleryPierPlacements.length; pi++) {
                    const pier = this._galleryPierPlacements[pi];
                    const dx = x - pier[0];
                    const dz = z - pier[1];
                    const dist = Math.hypot(dx, dz);
                    if (dist < 0.54 && dist > 0.0001) {
                        const push = 0.54 - dist;
                        const pnx = dx / dist;
                        const pnz = dz / dist;
                        x += pnx * push;
                        z += pnz * push;
                        recordWall(pnx, pnz);
                    }
                }
            }

            // Goldene Ehrenportal-Saeulen (r = 0.72)
            [-this.portalHalfW, this.portalHalfW].forEach((ppx) => {
                const dx = x - ppx;
                const dz = z - this.hallNorthZ;
                const dist = Math.hypot(dx, dz);
                if (dist < 0.72 && dist > 0.0001) {
                    const push = 0.72 - dist;
                    x += (dx / dist) * push;
                    z += (dz / dist) * push;
                }
            });

            // Sanfte Kreiskollision um die 3D-Sternebewertungs-Station (r = 0.78)
            if (this.atelierStarStation) {
                const dx = x - this.atelierStarStation.x;
                const dz = z - this.atelierStarStation.z;
                const dist = Math.hypot(dx, dz);
                if (dist < 0.78 && dist > 0.0001) {
                    const push = 0.78 - dist;
                    x += (dx / dist) * push;
                    z += (dz / dist) * push;
                }
            }
        } else if (z >= this.heroesEntryZ + 0.38) {
            // Ehrenportal & Marmor-Ehrenpromenade
            if (x < -portalClearHalfW) {
                x = -portalClearHalfW;
                recordWall(1, 0);
            } else if (x > portalClearHalfW) {
                x = portalClearHalfW;
                recordWall(-1, 0);
            }
        } else {
            // Zone 2: Pantheon-Rotunde ("Halle der Helden")
            const minPanX = -(this.pantheonHalfW - 0.42);
            const maxPanX = this.pantheonHalfW - 0.42;
            if (x < minPanX) {
                x = minPanX;
                recordWall(1, 0);
            } else if (x > maxPanX) {
                x = maxPanX;
                recordWall(-1, 0);
            }
            if (z > this.heroesEntryZ - 0.42 && Math.abs(x) > portalClearHalfW + 1.2) {
                z = this.heroesEntryZ - 0.42;
                recordWall(0, -1);
            }
        }

        // Kreiskollisionen um Produkt-Podeste
        const minSteleRadius = 0.98;
        for (let i = 0; i < this.steles.length; i++) {
            const st = this.steles[i];
            const dx = x - st.x;
            const dz = z - st.z;
            const dist = Math.hypot(dx, dz);
            if (dist < minSteleRadius && dist > 0.0001) {
                const push = minSteleRadius - dist;
                x += (dx / dist) * push;
                z += (dz / dist) * push;
            }
        }

        // Kreiskollisionen um Pantheon-Saeulen & Top-5-Ehrenpodeste
        if (z < this.hallNorthZ - 4.0) {
            for (let c = 0; c < this.pantheonColumnColliders.length; c++) {
                const col = this.pantheonColumnColliders[c];
                const dx = x - col.x;
                const dz = z - col.z;
                const dist = Math.hypot(dx, dz);
                if (dist < col.r && dist > 0.0001) {
                    const push = col.r - dist;
                    x += (dx / dist) * push;
                    z += (dz / dist) * push;
                }
            }

            for (let p = 0; p < this.heroPedestalColliders.length; p++) {
                const ped = this.heroPedestalColliders[p];
                const dx = x - ped.x;
                const dz = z - ped.z;
                const dist = Math.hypot(dx, dz);
                if (dist < ped.r && dist > 0.0001) {
                    const push = ped.r - dist;
                    x += (dx / dist) * push;
                    z += (dz / dist) * push;
                }
            }
        }

        this._collisionOut.x = x;
        this._collisionOut.z = z;
        this._collisionOut.hitWall = hitWall;
        this._collisionOut.wallNormalX = wallNormalX;
        this._collisionOut.wallNormalZ = wallNormalZ;
        return this._collisionOut;
    }

    _detectWallForCling(px, pz, hitWall, wnX, wnZ) {
        const faceDirX = Math.sin(this.player.yaw || 0);
        const faceDirZ = Math.cos(this.player.yaw || 0);
        const vLen = Math.hypot(this.player.velX || 0, this.player.velZ || 0);
        const moveDirX = vLen > 0.25 ? (this.player.velX / vLen) : faceDirX;
        const moveDirZ = vLen > 0.25 ? (this.player.velZ / vLen) : faceDirZ;

        if (hitWall && (wnX !== 0 || wnZ !== 0)) {
            const intoWall = moveDirX * (-wnX) + moveDirZ * (-wnZ);
            if (intoWall > 0.15) {
                return { ok: true, nx: wnX, nz: wnZ };
            }
        }

        // Wandabstand <= 0.46 m in Blick- oder Bewegungsrichtung pruefen (auch beim Hochspringen dicht vor der Wand!)
        const portalClearHalfW = this.portalHalfW - 0.52;
        const candidates = [];

        if (pz >= this.hallNorthZ + 0.30) {
            const westSurfaceX = Math.abs(pz) <= 2.45 ? -11.81 : -this.hallHalfW;
            candidates.push({ dist: px - westSurfaceX, nx: 1, nz: 0 });
            candidates.push({ dist: this.hallHalfW - px, nx: -1, nz: 0 });
            candidates.push({ dist: this.hallSouthZ - pz, nx: 0, nz: -1 });
            if (Math.abs(px) > portalClearHalfW) {
                candidates.push({ dist: pz - this.hallNorthZ, nx: 0, nz: 1 });
            }
        } else if (pz >= this.heroesEntryZ + 0.38) {
            candidates.push({ dist: px - (-this.portalHalfW), nx: 1, nz: 0 });
            candidates.push({ dist: this.portalHalfW - px, nx: -1, nz: 0 });
        } else {
            candidates.push({ dist: px - (-this.pantheonHalfW), nx: 1, nz: 0 });
            candidates.push({ dist: this.pantheonHalfW - px, nx: -1, nz: 0 });
            candidates.push({ dist: pz - this.pantheonMinZ, nx: 0, nz: 1 });
            if (Math.abs(px) > portalClearHalfW + 1.2) {
                candidates.push({ dist: this.heroesEntryZ - pz, nx: 0, nz: -1 });
            }
        }

        let best = null;
        let bestDist = 0.46;
        for (let i = 0; i < candidates.length; i++) {
            const c = candidates[i];
            if (c.dist <= bestDist && c.dist >= -0.05) {
                const alignFace = faceDirX * (-c.nx) + faceDirZ * (-c.nz);
                const alignMove = moveDirX * (-c.nx) + moveDirZ * (-c.nz);
                if (alignFace > 0.35 || alignMove > 0.35) {
                    bestDist = c.dist;
                    best = c;
                }
            }
        }
        return best ? { ok: true, nx: best.nx, nz: best.nz } : null;
    }

    _tick(nowMs) {
        if (!this.running || this.destroyed) return;
        this.rafId = requestAnimationFrame(this._boundTick);

        const dt = clamp((nowMs - this.lastTimeMs) / 1000, 0.001, 0.05);
        this.lastTimeMs = nowMs;
        this.elapsedTime += dt;

        const instantFps = 1.0 / Math.max(0.001, dt);
        this.fpsEstimate = lerp(this.fpsEstimate, clamp(instantFps, 15, 120), 0.08);

        this._updateSeasonalOutdoorWeather(dt);
        this._updateTeleportFovGlide(dt);
        if (this._portalWalkCd > 0) {
            this._portalWalkCd = Math.max(0, this._portalWalkCd - dt);
        }
        if (this._gatePromptCooldown > 0) {
            this._gatePromptCooldown = Math.max(0, this._gatePromptCooldown - dt);
        }
        if (this._gateRepulseInputDampTimer > 0) {
            this._gateRepulseInputDampTimer = Math.max(0, this._gateRepulseInputDampTimer - dt);
        }
        if (this._statusHintTimer > 0) {
            this._statusHintTimer = Math.max(0, this._statusHintTimer - dt);
            if (this._statusHintTimer <= 0 && this.playerProfile.statusHintText) {
                this.playerProfile.statusHintText = '';
                this._emitPlayerHudUpdate();
            }
        }
        if (this._magicAwakenChoreo) {
            this._updateMagicAwakenChoreo(dt);
        }
        if (this.player.emoteTimer > 0) {
            this.player.emoteTimer = Math.max(0, this.player.emoteTimer - dt);
            if (this.player.emoteTimer <= 0) {
                this.player.emote = null;
            }
        }
        if (this.camControl.shakeTimer > 0) {
            this.camControl.shakeTimer = Math.max(0, this.camControl.shakeTimer - dt);
        }

        // Frei belegbare Bewegungs-Tasten + Pfeiltasten + MMO L+R-Mauslauf + Mobile-Joystick
        const kb = this.keybindings;
        let inputForward = 0;
        let inputRight = 0;

        if (this.keys[kb.moveForward] || this.keys.ArrowUp) inputForward += 1;
        if (this.keys[kb.moveBack] || this.keys.ArrowDown) inputForward -= 1;
        if (this.keys[kb.moveRight] || this.keys.ArrowRight) inputRight += 1;
        if (this.keys[kb.moveLeft] || this.keys.ArrowLeft) inputRight -= 1;

        if (this.camControl.bothMouseWalk) {
            inputForward = Math.max(inputForward, 1);
        }

        if (Math.abs(this.joystick.x) > 0.04 || Math.abs(this.joystick.y) > 0.04) {
            inputRight += this.joystick.x;
            inputForward -= this.joystick.y;
        }

        const hasManualInput = Math.hypot(inputForward, inputRight) > 0.05;
        if (hasManualInput) {
            this.player.emote = null;
            this.player.emoteTimer = 0;
            this.player.autoTarget = null;
            this.player.autoLookAt = null;
            this.pinnedSteleIndex = -1;
            this.pinnedHeroSlot = -1;
            this.pinnedPortalGate = false;
        }

        // Analoge Input-Glaettung (verhindert jedes Diagonal-Zucken bei leicht versetztem W+A / W+D!)
        const rawInLen = Math.max(1.0, Math.hypot(inputForward, inputRight));
        const targetInFwd = hasManualInput ? (inputForward / rawInLen) : 0.0;
        const targetInRight = hasManualInput ? (inputRight / rawInLen) : 0.0;
        const inputSmoothAlpha = 1.0 - Math.exp(-dt * 24.0);
        this.player.smoothInputForward = lerp(this.player.smoothInputForward || 0, targetInFwd, inputSmoothAlpha);
        this.player.smoothInputRight = lerp(this.player.smoothInputRight || 0, targetInRight, inputSmoothAlpha);
        if (!hasManualInput && Math.hypot(this.player.smoothInputForward, this.player.smoothInputRight) < 0.002) {
            this.player.smoothInputForward = 0;
            this.player.smoothInputRight = 0;
        }

        const isShiftHeld = Boolean(this.keys.ShiftLeft || this.keys.ShiftRight || this.keys.Shift);
        const moveSpeed = isShiftHeld ? 8.8 : 5.2;
        let targetVx = 0;
        let targetVz = 0;

        const smoothInMag = Math.hypot(this.player.smoothInputForward, this.player.smoothInputRight);
        if (!this.player.isWallClinging) {
            if (hasManualInput || smoothInMag > 0.015) {
                const clampedMag = Math.min(1.0, smoothInMag);
                const scaleIn = smoothInMag > 0.0001 ? (clampedMag / smoothInMag) : 0;
                const nf = this.player.smoothInputForward * scaleIn;
                const nr = this.player.smoothInputRight * scaleIn;
                const sinY = Math.sin(this.camControl.yaw);
                const cosY = Math.cos(this.camControl.yaw);

                targetVx = (-sinY * nf + cosY * nr) * moveSpeed;
                targetVz = (-cosY * nf - sinY * nr) * moveSpeed;
            } else if (this.player.autoTarget) {
                const dx = this.player.autoTarget.x - this.player.x;
                const dz = this.player.autoTarget.z - this.player.z;
                const dist = Math.hypot(dx, dz);
                if (dist > 0.09) {
                    const stepSpeed = Math.min(8.8, moveSpeed * 1.18, Math.max(1.85, dist * 3.4));
                    targetVx = (dx / dist) * stepSpeed;
                    targetVz = (dz / dist) * stepSpeed;
                } else {
                    this.player.autoTarget = null;
                    this.pinnedSteleIndex = -1;
                    if (this.player.autoLookAt) {
                        const faceYaw = Math.atan2(
                            this.player.autoLookAt.x - this.player.x,
                            this.player.autoLookAt.z - this.player.z
                        );
                        this.player.yaw = normalizeAngleSigned(faceYaw);
                        this.player.autoLookAt = null;
                    }
                }
            }
        }

        // Knackige Ninja-Trägheits-Physik (Beschleunigung accel = 22.0, Abbremsen decel = 18.0)
        const hasDriveIntent = !this.player.isWallClinging && (hasManualInput || Boolean(this.player.autoTarget));
        const velRate = hasDriveIntent ? 22.0 : 18.0;
        const velAlpha = 1.0 - Math.exp(-dt * velRate);
        this.player.velX = lerp(this.player.velX || 0, targetVx, velAlpha);
        this.player.velZ = lerp(this.player.velZ || 0, targetVz, velAlpha);
        if (!hasDriveIntent && Math.hypot(this.player.velX, this.player.velZ) < 0.02) {
            this.player.velX = 0;
            this.player.velZ = 0;
        }

        // Sanftes Ausklingen des Wall-Backflip-Abstoss-Impulses in der Luft
        let wfVx = 0;
        let wfVz = 0;
        if (Math.hypot(this.player.wallFlipVx || 0, this.player.wallFlipVz || 0) > 0.02) {
            const wfDecay = Math.exp(-dt * 2.8);
            this.player.wallFlipVx *= wfDecay;
            this.player.wallFlipVz *= wfDecay;
            wfVx = this.player.wallFlipVx;
            wfVz = this.player.wallFlipVz;
            if (Math.hypot(wfVx, wfVz) < 0.03) {
                this.player.wallFlipVx = 0;
                this.player.wallFlipVz = 0;
            }
        }

        let vx = this.player.velX + wfVx * 0.65;
        let vz = this.player.velZ + wfVz * 0.65;

        const speedMag = Math.hypot(this.player.velX, this.player.velZ);
        this.player.isMoving = !this.player.isWallClinging && (speedMag > 0.08 || hasDriveIntent);
        this.player.isSprinting = isShiftHeld && this.player.isMoving;
        this.player.sprintBoost = lerp(this.player.sprintBoost || 0, this.player.isSprinting ? 1.0 : 0.0, 1.0 - Math.exp(-dt * 12.0));

        const prevX = this.player.x;
        const prevZ = this.player.z;
        const prevYaw = this.player.yaw;

        let gateRepVx = 0;
        let gateRepVz = 0;
        if (this._gateRepulseInputDampTimer > 0) {
            const intoGate = vx * (-this._gateRepulseDirX) + vz * (-this._gateRepulseDirZ);
            if (intoGate > 0) {
                vx += this._gateRepulseDirX * intoGate;
                vz += this._gateRepulseDirZ * intoGate;
            }
        }
        if (this._gateRepulseTimer > 0) {
            const repDur = Math.max(0.05, this._gateRepulseDuration || 0.38);
            const repRatio = clamp(this._gateRepulseTimer / repDur, 0, 1);
            const repSpeed = (4.60 / repDur) * repRatio;
            gateRepVx = this._gateRepulseDirX * repSpeed;
            gateRepVz = this._gateRepulseDirZ * repSpeed;
            this._gateRepulseTimer = Math.max(0, this._gateRepulseTimer - dt);
        }

        let frameHitWall = false;
        let frameWnX = 0;
        let frameWnZ = 0;

        if (!this.player.isWallClinging && (Math.hypot(vx, vz) > 0.005 || gateRepVx !== 0 || gateRepVz !== 0)) {
            const totalVx = vx + gateRepVx;
            const totalVz = vz + gateRepVz;
            const nextPos = this._resolveCollisions(
                this.player.x + totalVx * dt,
                this.player.z + totalVz * dt
            );
            this.player.x = nextPos.x;
            this.player.z = nextPos.z;
            frameHitWall = Boolean(nextPos.hitWall);
            frameWnX = nextPos.wallNormalX || 0;
            frameWnZ = nextPos.wallNormalZ || 0;

            const isWallBackflipping = Boolean(this.player.isSomersault && this.player.somersaultDir === -1);
            if (hasDriveIntent && Math.hypot(targetVx, targetVz) > 0.12 && this._gateRepulseTimer <= 0 && !isWallBackflipping) {
                const moveYaw = Math.atan2(this.player.velX || targetVx, this.player.velZ || targetVz);
                this.player.yaw = lerpAngle(this.player.yaw, moveYaw, 1.0 - Math.exp(-16.0 * dt));
            }
        }

        // 1-Sekunde Wall-Cling + Boden- & Sprung-Physik in Produkt-Atelier, Ehrenpromenade & Halle der Helden (y = 0)
        this.player.groundY = 0;

        if (this.player.isWallClinging) {
            this.player.vy = 0;
            this.player.velX = 0;
            this.player.velZ = 0;
            const faceWallYaw = Math.atan2(-(this.player.wallNormalX || 0), -(this.player.wallNormalZ || 0));
            this.player.yaw = lerpAngle(this.player.yaw, faceWallYaw, 1.0 - Math.exp(-dt * 22.0));

            this.player.wallClingTimer -= dt;
            if (this.player.wallClingTimer < 0.20 && this.player.wallClingTimer > 0) {
                this.player.y = Math.max(0.05, (this.player.y || 0) - 0.15 * dt);
            }
            if (this.player.wallClingTimer <= 0) {
                this.player.isWallClinging = false;
                this.player.wallClingTimer = 0;
                this.player.vy = -0.35;
            }
        } else if (this.player.isJumping || this.player.y > 0) {
            const impactSpeedBefore = Math.max(0, -(this.player.vy || 0));
            this.player.vy -= 15.2 * dt;
            this.player.y += this.player.vy * dt;

            // Startet 1-Sekunde Wall-Cling beim Sprung nahe an / gegen eine Wand
            if (
                this.player.isJumping
                && this.player.y > 0.26
                && this.player.vy > -3.5
                && !this.player.wallClingUsed
                && this._gateRepulseTimer <= 0
            ) {
                const clingWall = this._detectWallForCling(this.player.x, this.player.z, frameHitWall, frameWnX, frameWnZ);
                if (clingWall && clingWall.ok) {
                    this.player.isWallClinging = true;
                    this.player.wallClingTimer = 1.0;
                    this.player.wallClingUsed = true;
                    this.player.wallNormalX = clingWall.nx;
                    this.player.wallNormalZ = clingWall.nz;
                    this.player.vy = 0;
                    this.player.velX = 0;
                    this.player.velZ = 0;
                    this.player.isSomersault = false;
                    this.player.somersaultProgress = 0.0;
                    this.player.yaw = Math.atan2(-clingWall.nx, -clingWall.nz);
                    if (this.audio) this.audio.playFootstep('marble');
                }
            }

            if (this.player.isSomersault) {
                this.player.somersaultProgress += dt / Math.max(0.01, this.player.somersaultDuration);
                if (this.player.somersaultProgress >= 1.0) {
                    this.player.somersaultProgress = 1.0;
                    this.player.isSomersault = false;
                }
            }

            if (!this.player.isWallClinging && this.player.y <= 0) {
                this.player.y = 0;
                this.player.vy = 0;
                this.player.isJumping = false;
                this.player.highJumpActive = false;
                this.player.jumpCount = 0;
                this.player.wallClingUsed = false;
                this.player.isWallClinging = false;
                this.player.wallClingTimer = 0;
                this.player.wallFlipVx = 0;
                this.player.wallFlipVz = 0;
                this.player.isSomersault = false;
                this.player.somersaultDir = 1;
                this.player.somersaultProgress = 0.0;
                this.player.landSquash = clamp(0.14 + impactSpeedBefore * 0.012, 0.15, 0.25);
            }
        }

        // Tor zur Aussenwelt (Westwand X = -11.92, Z = 0.0): schwebender Hinweis bei Annaeherung & Re-Arm beim Zuruecktreten
        if (this.currentZone === 'gallery') {
            if (this.player.x > -10.2) {
                this._westOuterGateArmed = true;
            }
            const nearOuterGate = (this.player.x < -9.2 && Math.abs(this.player.z) < 3.5);
            if (nearOuterGate !== this.westGateFocused) {
                this._setWestOuterGateFocused(nearOuterGate);
            }
        } else if (this.westGateFocused) {
            this._setWestOuterGateFocused(false);
        }

        // 3D-Sternebewertungs-Station neben dem Ehrenportal (dist < 2.95 m in gallery)
        if (this.currentZone === 'gallery' && this.atelierStarStation) {
            const stDist = Math.hypot(
                this.player.x - this.atelierStarStation.x,
                this.player.z - this.atelierStarStation.z
            );
            const focusThreshold = this.atelierStarStationFocused ? 3.20 : 2.95;
            const nearStarStation = stDist < focusThreshold;
            if (nearStarStation !== this.atelierStarStationFocused) {
                this._setAtelierStarStationFocused(nearStarStation);
            }
        } else if (this.atelierStarStationFocused) {
            this._setAtelierStarStationFocused(false);
        }

        if (this.player.landSquash > 0) {
            this.player.landSquash = this.player.landSquash * Math.exp(-dt * 7.5);
            if (this.player.landSquash < 0.003) this.player.landSquash = 0;
        }

        const stepDist = Math.hypot(this.player.x - prevX, this.player.z - prevZ);
        this.player.currentSpeed = stepDist / Math.max(0.001, dt);

        const yawDiff = shortestAngleDiff(prevYaw, this.player.yaw);
        const rawTurnRate = clamp(yawDiff / Math.max(0.004, dt), -14.0, 14.0);
        this.player.smoothTurnRate = lerp(this.player.smoothTurnRate || 0, rawTurnRate, 1.0 - Math.exp(-dt * 14.0));
        const turnRate = this.player.smoothTurnRate;

        const activeZoneNow = this.getCurrentZone();
        if (activeZoneNow !== this.currentZone) {
            this.currentZone = activeZoneNow;
            this._syncZoneGroupVisibility();
            if (activeZoneNow === 'heroes') {
                this.refreshHeroesData(false);
            }
            if (this.onZoneChange) {
                this.onZoneChange(activeZoneNow);
            }
        }

        if (!this.canEnterSanctums) {
            const nearGate = (activeZoneNow === 'gallery' && this.player.z < this.hallNorthZ + 4.2 && Math.abs(this.player.x) < this.portalHalfW + 0.8) || this.pinnedPortalGate;
            this._setPortalGateFocused(nearGate);
        }

        let nearestGaze = null;
        if (activeZoneNow === 'gallery') {
            this._setFocusedHeroSlot(-1);
            const nearSteleGaze = this._findNearestStele(4.2);
            if (nearSteleGaze) {
                nearestGaze = { x: nearSteleGaze.x, z: nearSteleGaze.z };
            }

            if (this.pinnedSteleIndex >= 0) {
                this._setFocusedSteleIndex(this.pinnedSteleIndex);
            } else {
                const radius = this.focusedSteleIndex >= 0 ? PROXIMITY_FOCUS_LEAVE : PROXIMITY_FOCUS_ENTER;
                const nearFocus = this._findNearestStele(radius);
                if (nearFocus) {
                    const enterCheck = (nearFocus.index === this.focusedSteleIndex)
                        || (Math.hypot(this.player.x - nearFocus.x, this.player.z - nearFocus.z) < PROXIMITY_FOCUS_ENTER);
                    if (enterCheck) {
                        this._setFocusedSteleIndex(nearFocus.index);
                    }
                } else {
                    this._setFocusedSteleIndex(-1);
                }
            }
        } else {
            this._setFocusedSteleIndex(-1);
            const nearHeroGaze = this._findNearestHeroPedestal(5.4);
            if (nearHeroGaze) {
                nearestGaze = { x: nearHeroGaze.worldX, z: nearHeroGaze.worldZ };
            }

            if (this.pinnedHeroSlot >= 1 && this.pinnedHeroSlot <= 5) {
                this._setFocusedHeroSlot(this.pinnedHeroSlot);
            } else {
                const hRadius = this.focusedHeroSlot >= 1 ? HERO_FOCUS_LEAVE : HERO_FOCUS_ENTER;
                const nearHero = this._findNearestHeroPedestal(hRadius + 1.2);
                if (nearHero) {
                    const distToRim = Math.hypot(this.player.x - nearHero.worldX, this.player.z - nearHero.worldZ) - nearHero.haloRadius;
                    const enterOk = (nearHero.slot === this.focusedHeroSlot && distToRim < HERO_FOCUS_LEAVE)
                        || (distToRim < HERO_FOCUS_ENTER);
                    if (enterOk) {
                        this._setFocusedHeroSlot(nearHero.slot);
                    } else if (nearHero.slot === this.focusedHeroSlot) {
                        this._setFocusedHeroSlot(-1);
                    }
                } else {
                    this._setFocusedHeroSlot(-1);
                }
            }
        }

        this._updatePlayerKinematics(dt, stepDist, turnRate, nearestGaze, 0);
        this._updatePeersKinematics(dt);
        this._updateHeroPedestalAnimations(this.elapsedTime);
        this._updateAtelierStarRatingStation(dt);
        this._updateWeaponBursts(dt);

        for (let i = 0; i < this.steles.length; i++) {
            const st = this.steles[i];
            if (st.turntable) {
                st.turntable.rotation.y = this.elapsedTime * 0.34 + i * 0.6;
                st.turntable.position.y = 1.56 + Math.sin(this.elapsedTime * 1.5 + i) * 0.012;
            }
        }

        if (this.walkTargetRing && this.walkTargetTimer > 0) {
            this.walkTargetTimer = Math.max(0, this.walkTargetTimer - dt);
            const pulse = 1.0 + (1.35 - this.walkTargetTimer) * 0.35;
            this.walkTargetRing.scale.set(pulse, pulse, 1);
            this.walkTargetRing.material.opacity = clamp(this.walkTargetTimer * 0.65, 0, 0.78);
        }

        const camDamp = 1.0 - Math.exp(-9.5 * dt);
        this.camControl.yaw = lerpAngle(this.camControl.yaw, this.camControl.desiredYaw, camDamp);
        this.camControl.pitch = lerp(this.camControl.pitch, this.camControl.desiredPitch, camDamp);
        this.camControl.distance = lerp(this.camControl.distance, this.camControl.desiredDistance, camDamp);

        const isFirstPerson = this.camControl.distance < 0.55;
        this.camControl.isFirstPerson = isFirstPerson;
        if (this.player.root) {
            this.player.root.visible = !isFirstPerson;
        }
        if (isFirstPerson) {
            if (this.player.bubbleSprite) {
                this.player.bubbleSprite.visible = false;
            }
            this.player.yaw = normalizeAngleSigned(this.camControl.yaw + Math.PI);
        }

        const jumpOffsetY = Math.max(0, this.player.y || 0);
        const targetLookY = 1.42 + jumpOffsetY * 0.42 + (this.player.awakenLiftY || 0) * 0.55;
        this.camControl.lookAtCurrent.x = lerp(this.camControl.lookAtCurrent.x, this.player.x, camDamp);
        this.camControl.lookAtCurrent.y = lerp(this.camControl.lookAtCurrent.y, targetLookY, camDamp);
        this.camControl.lookAtCurrent.z = lerp(this.camControl.lookAtCurrent.z, this.player.z, camDamp);

        const shakeOffset = this.camControl.shakeTimer > 0
            ? Math.sin(this.elapsedTime * 68.0) * (this.camControl.shakeTimer / 0.14) * 0.12
            : 0;

        if (isFirstPerson) {
            const cosPitch = Math.cos(this.camControl.pitch);
            const fwdX = -Math.sin(this.camControl.yaw) * cosPitch;
            const fwdY = -Math.sin(this.camControl.pitch);
            const fwdZ = -Math.cos(this.camControl.yaw) * cosPitch;
            const fpFloorY = this._computeFloorBaseY(this.player.x, this.player.z);
            const fpBob = (this.player.currentSpeed > 0.25 && !this.player.isJumping)
                ? Math.sin((this.player.walkPhase || 0) * 2.0) * 0.016
                : 0;
            const eyeY = fpFloorY + (this.player.y || 0) + 1.56 + fpBob + shakeOffset;

            this.camera.position.set(this.player.x, eyeY, this.player.z);
            this.camera.lookAt(
                this.player.x + fwdX * 12.0,
                eyeY + fwdY * 12.0,
                this.player.z + fwdZ * 12.0
            );
        } else {
            const horizDist = Math.cos(this.camControl.pitch) * this.camControl.distance;
            const rawCamX = this.camControl.lookAtCurrent.x + Math.sin(this.camControl.yaw) * horizDist;
            const rawCamY = this.camControl.lookAtCurrent.y + Math.sin(this.camControl.pitch) * this.camControl.distance + shakeOffset;
            const rawCamZ = this.camControl.lookAtCurrent.z + Math.cos(this.camControl.yaw) * horizDist;

            // Kamera-Wandschutz (0.24 m Innenabstand zur Wand, niemals Blick von hinter der Aussenwand!)
            const wallMargin = 0.24;
            let maxBoundX = this.hallHalfW - wallMargin;
            let minBoundZ = this.canEnterSanctums ? (this.pantheonMinZ + wallMargin) : (this.hallNorthZ + 0.29);
            let maxBoundZ = this.hallSouthZ - wallMargin;

            if (activeZoneNow === 'heroes') {
                maxBoundX = this.pantheonHalfW - wallMargin;
                if (Math.abs(rawCamX) > this.portalHalfW + 0.9 && this.player.z < this.heroesEntryZ - 0.1) {
                    maxBoundZ = this.heroesEntryZ - wallMargin;
                }
            } else if (this.player.z < this.hallNorthZ + 0.30) {
                maxBoundX = this.portalHalfW + 0.10;
            } else {
                maxBoundX = this.hallHalfW - wallMargin;
                if (Math.abs(rawCamX) > this.portalHalfW - wallMargin) {
                    minBoundZ = Math.max(minBoundZ, this.hallNorthZ + 0.29);
                }
            }
            const maxBoundY = activeZoneNow === 'heroes' ? 19.5 : 11.5;

            this.camera.position.set(
                clamp(rawCamX, -maxBoundX, maxBoundX),
                clamp(rawCamY, 0.85, maxBoundY),
                clamp(rawCamZ, minBoundZ, maxBoundZ)
            );
            this.camera.lookAt(this.camControl.lookAtCurrent);
        }

        if (this._exhibitTransition) {
            const tr = this._exhibitTransition;
            tr.elapsed += dt;
            const uTr = clamp(tr.elapsed / Math.max(0.1, tr.duration), 0, 1);
            const easeTr = uTr * uTr * (3.0 - 2.0 * uTr);
            this.camera.position.set(
                lerp(tr.startCamX, tr.targetCamX, easeTr),
                lerp(tr.startCamY, tr.targetCamY, easeTr),
                lerp(tr.startCamZ, tr.targetCamZ, easeTr)
            );
            this.camera.lookAt(
                lerp(tr.startLookX, tr.lookX, easeTr),
                lerp(tr.startLookY, tr.lookY, easeTr),
                lerp(tr.startLookZ, tr.lookZ, easeTr)
            );
            if (uTr >= 1.0) {
                this._exhibitTransition = null;
            }
        }

        this._updateFloatingCardProjection();
        this._updateCharacterFollowLights();

        this.renderer.render(this.scene, this.camera);
    }

    resize() {
        if (!this.renderer || !this.canvas) return;
        const parent = this.canvas.parentElement;
        const w = Math.max(1, parent ? parent.clientWidth : window.innerWidth);
        const h = Math.max(1, parent ? parent.clientHeight : window.innerHeight);
        this._viewW = w;
        this._viewH = h;

        if (!this.camera) {
            this.camera = new this.THREE.PerspectiveCamera(50, w / h, 0.1, 380);
        } else {
            this.camera.aspect = w / h;
            this.camera.updateProjectionMatrix();
        }

        const isBalancedQuality = Boolean(this.userSettings && this.userSettings.graphicsQuality === 'balanced');
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, isBalancedQuality ? 1.0 : 1.5));
        this.renderer.setSize(w, h, false);
    }

    releasePointerLock() {
        try {
            if (typeof document !== 'undefined' && document.pointerLockElement && typeof document.exitPointerLock === 'function') {
                document.exitPointerLock();
            }
        } catch (err) {
            // Pointer-Lock optional
        }
        this._resetMouseButtons();
    }

    getMinimapState() {
        const zone = this.currentZone;
        let minX;
        let maxX;
        let minZ;
        let maxZ;
        if (zone === 'heroes') {
            minX = -this.pantheonHalfW;
            maxX = this.pantheonHalfW;
            minZ = this.pantheonMinZ;
            maxZ = this.heroesEntryZ + 1.0;
        } else {
            minX = -this.hallHalfW - 1.4;
            maxX = this.hallHalfW + 1.4;
            minZ = this.hallNorthZ - 1.0;
            maxZ = this.hallSouthZ;
        }
        const spanX = Math.max(1, maxX - minX);
        const spanZ = Math.max(1, maxZ - minZ);
        const toUv = (px, pz) => ({
            u: clamp((px - minX) / spanX, 0, 1),
            v: clamp((pz - minZ) / spanZ, 0, 1),
        });
        const markers = [];
        if (zone === 'gallery') {
            for (let i = 0; i < this.steles.length; i++) {
                const p = toUv(this.steles[i].x, this.steles[i].z);
                markers.push({ type: 'exhibit', u: p.u, v: p.v, active: i === this.focusedSteleIndex });
            }
            const wg = toUv(-11.92, 0.0);
            markers.push({ type: 'gate', u: wg.u, v: wg.v, active: Boolean(this.mmorpgCanEnter) });
            if (this.atelierStarStation) {
                const sp = toUv(this.atelierStarStation.x, this.atelierStarStation.z);
                markers.push({ type: 'star_station', u: sp.u, v: sp.v, active: Boolean(this.atelierStarStationFocused) });
            }
        } else if (zone === 'heroes') {
            for (let i = 0; i < this.heroPedestals.length; i++) {
                const ped = this.heroPedestals[i];
                const p = toUv(ped.worldX, ped.worldZ);
                markers.push({ type: 'hero', u: p.u, v: p.v, active: ped.slot === this.focusedHeroSlot });
            }
        }
        const me = toUv(this.player.x || 0, this.player.z || 0);
        return {
            zone,
            aspect: spanX / spanZ,
            player: { u: me.u, v: me.v, yaw: this.player.yaw || 0 },
            markers,
        };
    }

    getEnvironmentInfo() {
        return Object.assign({}, this.environmentInfo || {});
    }

    destroy() {
        this._disposeMagicAwakenChoreo();
        this.running = false;
        this.destroyed = true;
        this._monumentGeneration += 1;
        this.releasePointerLock();

        if (this.audio && typeof this.audio.onLobbyClose === 'function') {
            this.audio.onLobbyClose();
        }

        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = 0;
        }
        if (this.queueTimerId) {
            clearTimeout(this.queueTimerId);
            this.queueTimerId = 0;
        }
        if (this._gatePromptPendingTimer) {
            clearTimeout(this._gatePromptPendingTimer);
            this._gatePromptPendingTimer = 0;
        }
        if (this._outerGatePromptPendingTimer) {
            clearTimeout(this._outerGatePromptPendingTimer);
            this._outerGatePromptPendingTimer = 0;
        }
        if (this.abortController) {
            try {
                this.abortController.abort();
            } catch (err) {
                // Bereits abgebrochen
            }
            this.abortController = null;
        }

        [
            this.floatingCardEl,
            this.floatingHeroCardEl,
            this.floatingPortalCardEl,
            this.floatingOuterGateEl,
            this.floatingStarStationEl,
        ].forEach((el) => {
            if (el) {
                el.style.opacity = '0';
                el.style.visibility = 'hidden';
                el.style.pointerEvents = 'none';
            }
        });
        this.floatingCardEl = null;
        this.floatingHeroCardEl = null;
        this.floatingPortalCardEl = null;
        this.floatingOuterGateEl = null;
        this.floatingStarStationEl = null;
        this.atelierStarStation = null;

        if (this._fsResizeTimer) {
            clearTimeout(this._fsResizeTimer);
            this._fsResizeTimer = 0;
        }
        if (this._resizeObserver) {
            this._resizeObserver.disconnect();
            this._resizeObserver = null;
        }
        window.removeEventListener('resize', this._boundResize);
        document.removeEventListener('fullscreenchange', this._boundFullscreenResize);
        document.removeEventListener('webkitfullscreenchange', this._boundFullscreenResize);
        window.removeEventListener('keydown', this._boundKeyDown);
        window.removeEventListener('keyup', this._boundKeyUp);
        window.removeEventListener('blur', this._boundWindowBlur);

        if (this.canvas) {
            this.canvas.removeEventListener('contextmenu', this._boundContextMenu);
            this.canvas.removeEventListener('pointerdown', this._boundPointerDown);
            this.canvas.removeEventListener('pointermove', this._boundPointerMove);
            this.canvas.removeEventListener('pointerup', this._boundPointerUp);
            this.canvas.removeEventListener('pointercancel', this._boundPointerUp);
            this.canvas.removeEventListener('wheel', this._boundWheel);
            this.canvas.removeEventListener('touchstart', this._boundTouchStart);
            this.canvas.removeEventListener('touchmove', this._boundTouchMove);
        }

        this._clearAllPeers();
        for (let bi = 0; bi < this.weaponBursts.length; bi++) {
            this._disposeWeaponBurst(this.weaponBursts[bi]);
        }
        this.weaponBursts = [];

        if (this.player.bubbleTex) {
            this.player.bubbleTex.dispose();
            this.player.bubbleTex = null;
        }
        if (this.player.bubbleMat) {
            this.player.bubbleMat.dispose();
            this.player.bubbleMat = null;
        }
        this.player.bubbleSprite = null;

        for (let hi = 0; hi < this.heroDynamicDisposables.length; hi++) {
            const hd = this.heroDynamicDisposables[hi];
            if (hd && typeof hd.dispose === 'function') {
                hd.dispose();
            }
        }
        this.heroDynamicDisposables = [];

        for (let i = 0; i < this.disposables.length; i++) {
            const d = this.disposables[i];
            if (d && typeof d.dispose === 'function') {
                d.dispose();
            }
        }
        this.disposables = [];
        this._avatarShared = null;
        this._funkiFallbackMats = null;
        this.glbCache.clear();
        this.glbTemplateCache.clear();
        this.glbLoadingPromises.clear();
        this.skinnedFunkiCache.clear();
        this.skinnedFunkiPromises.clear();
        this.exhibitLoadQueue = [];
        this.steles = [];
        this.heroPedestals = [];
        this.interactiveHitMeshes = [];
        this.heroInteractiveHitMeshes = [];
        this.portalHitMeshes = [];
        this.westGateHitMeshes = [];
        this.walkableFloorMeshes = [];
        this.charFollowLights = null;
        this.outdoorWeatherPosAttr = null;
        this.outdoorWeatherMeta = [];

        if (this.renderer) {
            this.renderer.dispose();
            this.renderer = null;
        }
        this.scene = null;
        this.camera = null;
    }
}

export function createLouvreShowroom(options) {
    const engine = new SeelenfunkeLouvreShowroom3D(options);
    engine.init();

    // Manipulationssichere eingefrorene Fassade fuer das ruhige Shop-Atelier & die Halle der Helden
    return Object.freeze({
        open(zone) {
            engine.goToZone(zone || 'gallery', true);
        },
        close() {
            engine.destroy();
        },
        destroy() {
            engine.destroy();
        },
        resize() {
            engine.resize();
        },
        setZone(targetZone, instant = false) {
            engine.goToZone(targetZone, Boolean(instant));
        },
        goToZone(targetZone, instant = false) {
            engine.goToZone(targetZone, Boolean(instant));
        },
        getCurrentZone() {
            return engine.getCurrentZone();
        },
        switchInstance(peersArray) {
            engine.updatePeers(Array.isArray(peersArray) ? peersArray : []);
        },
        triggerJump() {
            return engine.triggerJump();
        },
        triggerEmote(name) {
            return engine.triggerEmote(name);
        },
        getHudSnapshot() {
            const sync = engine.getLocalSyncState();
            return Object.freeze({
                zone: engine.getCurrentZone(),
                isFirstPerson: Boolean(engine.camControl && engine.camControl.distance < 0.55),
                activeIndex: engine.activeIndex || 0,
                stagesPreviewMode: engine.stagesPreviewMode || 'live',
                x: sync.x,
                y: sync.y,
                z: sync.z,
                rot_y: sync.rot_y,
                rotY: sync.rotY,
                speed: sync.speed,
                jumping: sync.jumping,
                emote: sync.emote,
                player: Object.freeze(engine.getPlayerHudState()),
                mmorpg: Object.freeze(engine.getMmorpgAccessState()),
                settings: Object.freeze(engine.getUserSettings()),
                keybindings: Object.freeze(engine.getKeybindings()),
            });
        },
        updateSettings(partial) {
            return Object.freeze(engine.updateSettings(partial));
        },
        focusExhibit(index, triggerSelect = false) {
            engine.goToExhibit(index, Boolean(triggerSelect));
        },
        goToExhibit(index, triggerSelect = false) {
            engine.goToExhibit(index, Boolean(triggerSelect));
        },
        nextExhibit() {
            engine.goToExhibit((engine.activeIndex || 0) + 1, false);
        },
        prevExhibit() {
            engine.goToExhibit((engine.activeIndex || 0) - 1, false);
        },
        focusHeroSlot(slotNum) {
            engine.goToHeroSlot(slotNum);
        },
        goToHeroSlot(slotNum) {
            engine.goToHeroSlot(slotNum);
        },
        focusMyHeroPlace() {
            engine.goToMyHeroPlace();
        },
        goToMyHeroPlace() {
            engine.goToMyHeroPlace();
        },
        cyclePreviewHeroLevel() {
            return engine.cycleStagesPreviewMode();
        },
        openOuterGateModal() {
            return engine.openOuterGateModalFromWorld();
        },
        getMmorpgAccessState() {
            return Object.freeze(engine.getMmorpgAccessState());
        },
        getAtelierReviewState() {
            return Object.freeze(engine.getAtelierReviewState());
        },
        setAtelierReviewState(reviewPayload, opts) {
            return Object.freeze(engine.setAtelierReviewState(reviewPayload, opts || null));
        },
        previewAtelierStarHover(stars) {
            engine.previewAtelierStarHover(stars);
        },
        focusAtelierStarStation() {
            engine.goToAtelierStarStation();
        },
        setLocalPeerId(peerId) {
            engine.setLocalPeerId(peerId);
        },
        getPlayerHudState() {
            return Object.freeze(engine.getPlayerHudState());
        },
        getSanctumAccessState() {
            return Object.freeze(engine.getSanctumAccessState());
        },
        applyLiveAccountUpdate(payload) {
            return Object.freeze(engine.applyLiveAccountUpdate(payload));
        },
        focusNearestExhibit() {
            return engine.focusNearestExhibit();
        },
        handleSanctumHttpResponse(status, body, zone = 'heroes') {
            return engine._interceptSanctumResponse({ status, body }, zone === 'heroes' ? 'heroes' : 'gallery');
        },
        getMinimapState() {
            return engine.getMinimapState();
        },
        getEnvironment() {
            return Object.freeze(engine.getEnvironmentInfo());
        },
        releasePointerLock() {
            engine.releasePointerLock();
        },
        setGraphicsQuality(level) {
            return Object.freeze(engine.updateSettings({ graphicsQuality: level === 'balanced' ? 'balanced' : 'high' }));
        },
        updatePeers(peersArray) {
            engine.updatePeers(peersArray);
        },
        setHeroesPayload(payload) {
            engine.setHeroesPayload(payload);
        },
        refreshHeroesData(force = false) {
            return engine.refreshHeroesData(Boolean(force));
        },
        startExhibitTransition(steleIdx) {
            return engine.startExhibitTransition(steleIdx);
        },
        showChatBubble(playerId, text) {
            engine.showChatBubble(playerId, text);
        },
        setSoloMode(flag) {
            engine.setSoloMode(Boolean(flag));
        },
        setFocusProtected(flag) {
            engine.setFocusProtected(Boolean(flag));
        },
        setMutedPlayers(ids) {
            engine.setMutedPlayers(Array.isArray(ids) ? ids : []);
        },
        setJoystickVector(nx, ny) {
            engine.setJoystickVector(nx, ny);
        },
    });
}
