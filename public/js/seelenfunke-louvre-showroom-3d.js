/*!
 * Mein-Seelenfunke · Vereinte 3D-User-Lobby (Louvre-Produktgalerie + Ehrenportal + Goldene Halle der Helden)
 * 60-FPS Three.js Engine: InstancedMesh, voll artikuliertes Gelenk-Rig, Level 1..10 Funki-GLBs & Multiplayer-Instanzen.
 * On-Demand ES-Modul: 0 KB Initial-Last auf /shop & /mein-konto, 0 % CPU/GPU nach dem Schliessen.
 */

const TWO_PI = Math.PI * 2;
const PROXIMITY_FOCUS_ENTER = 3.4;
const PROXIMITY_FOCUS_LEAVE = 3.75;
const HERO_FOCUS_ENTER = 3.85;
const HERO_FOCUS_LEAVE = 4.35;

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
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: #111827;
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
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    pointer-events: none;
    z-index: 30;
    box-sizing: border-box;
}
.sf-lobby-nav-pill {
    pointer-events: auto;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(197, 160, 89, 0.42);
    border-radius: 999px;
    padding: 5px;
    box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    max-width: 100%;
    box-sizing: border-box;
}
.sf-lobby-zone-btn {
    appearance: none;
    border: none;
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: 0.01em;
    background: transparent;
    color: #111827;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    transition: background-color 150ms ease, color 150ms ease;
}
.sf-lobby-zone-btn:hover {
    background: rgba(197, 160, 89, 0.14);
}
.sf-lobby-zone-btn.is-active {
    background: #C5A059 !important;
    color: #FFFFFF !important;
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
    gap: 8px;
    flex-wrap: wrap;
}
.sf-lobby-pill-btn {
    appearance: none;
    background: rgba(255, 255, 255, 0.94);
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
    width: 276px;
    max-width: calc(100vw - 24px);
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(197, 160, 89, 0.55);
    border-radius: 16px;
    padding: 14px 16px;
    box-shadow: 0 14px 34px rgba(17, 24, 39, 0.12);
    color: #111827;
    pointer-events: auto;
    box-sizing: border-box;
}
.sf-lobby-floating-arrow {
    width: 12px;
    height: 12px;
    background: rgba(255, 255, 255, 0.96);
    border-right: 1px solid rgba(197, 160, 89, 0.55);
    border-bottom: 1px solid rgba(197, 160, 89, 0.55);
    transform: rotate(45deg);
    margin: -6px auto 0;
    pointer-events: none;
}
.sf-lobby-prod-head {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.sf-lobby-prod-thumb {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    object-fit: cover;
    background: #FAF8F5;
    border: 1px solid rgba(197, 160, 89, 0.35);
    flex-shrink: 0;
}
.sf-lobby-prod-cat {
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #9A7738;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sf-lobby-prod-title {
    margin: 2px 0 0;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.28;
    color: #111827;
}
.sf-lobby-prod-price {
    display: inline-block;
    margin-top: 3px;
    font-size: 13px;
    font-weight: 800;
    color: #111827;
}
.sf-lobby-prod-benefits {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(197, 160, 89, 0.25);
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.sf-lobby-prod-benefit-row {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
    color: #111827;
}
.sf-lobby-prod-check {
    color: #C5A059;
    font-weight: 900;
    flex-shrink: 0;
}
.sf-lobby-prod-cta {
    margin-top: 11px;
    width: 100%;
    padding: 9px 14px;
    border-radius: 12px;
    background: #C5A059;
    color: #FFFFFF;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    box-sizing: border-box;
    transition: background-color 150ms ease;
}
.sf-lobby-prod-cta:hover {
    background: #B38E46;
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
    bottom: 92px;
    right: 20px;
    z-index: 24;
    width: 304px;
    max-width: calc(100vw - 24px);
    background: rgba(255, 255, 255, 0.96);
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
.sf-lobby-stick-wrap {
    position: absolute;
    bottom: 22px;
    left: 20px;
    z-index: 22;
}
.sf-lobby-stick {
    width: 84px;
    height: 84px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(197, 160, 89, 0.52);
    box-shadow: 0 10px 26px rgba(17, 24, 39, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    touch-action: none;
    cursor: pointer;
}
.sf-lobby-stick-knob {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    background: #C5A059;
    border: 2px solid #FFFFFF;
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.18);
    pointer-events: none;
}
.sf-lobby-quick-actions {
    position: absolute;
    bottom: 22px;
    right: 20px;
    z-index: 22;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sf-lobby-icon-btn {
    appearance: none;
    height: 44px;
    min-width: 44px;
    padding: 0 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.94);
    color: #111827;
    border: 1px solid rgba(197, 160, 89, 0.55);
    box-shadow: 0 10px 24px rgba(17, 24, 39, 0.10);
    font-size: 13px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
}
.sf-lobby-icon-btn:hover {
    background: #FFFFFF;
    border-color: #C5A059;
}
.sf-lobby-bottom-stack {
    position: absolute;
    bottom: 14px;
    left: 12px;
    right: 12px;
    z-index: 21;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 7px;
    pointer-events: none;
}
.sf-lobby-bottom-pill {
    pointer-events: auto;
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.94);
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
.sf-lobby-exhibit-btn {
    appearance: none;
    padding: 6px 14px;
    border-radius: 999px;
    border: 1px solid rgba(197, 160, 89, 0.35);
    background: #FFFFFF;
    color: #111827;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sf-lobby-hint-pill {
    pointer-events: none;
    padding: 5px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.90);
    border: 1px solid rgba(197, 160, 89, 0.38);
    color: #374151;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.01em;
    box-shadow: 0 6px 18px rgba(17, 24, 39, 0.08);
    white-space: nowrap;
}
@media (max-width: 767px) {
    .sf-lobby-topbar { padding: 10px 12px; gap: 8px; }
    .sf-lobby-zone-btn { padding: 6px 10px; font-size: 11px; }
    .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 6px 10px; font-size: 11px; }
    .sf-lobby-kbd, .sf-lobby-hint-pill, .sf-lobby-hide-sm { display: none !important; }
    .sf-lobby-stick-wrap { bottom: 72px; left: 12px; }
    .sf-lobby-stick { width: 74px; height: 74px; }
    .sf-lobby-quick-actions { bottom: 72px; right: 12px; }
    .sf-lobby-chat-panel { bottom: 132px; right: 12px; }
    .sf-lobby-exhibit-btn { max-width: 175px; }
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

function lerpAngle(a, b, t) {
    let diff = ((b - a + Math.PI) % TWO_PI) - Math.PI;
    if (diff < -Math.PI) diff += TWO_PI;
    return a + diff * t;
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
    const isPlaceholder = !rawHero || Boolean(rawHero.is_placeholder);

    if (isPlaceholder) {
        return {
            slot,
            is_placeholder: true,
            is_me: false,
            display_name: 'Freier Ehrenplatz',
            level: 1,
            level_title: FUNKI_LEVEL_TITLES[1],
            sparks: 0,
            sparks_formatted: '0',
            ship_class: 'KLASSE C',
            titles_count: 0,
        };
    }

    const level = clamp(parseInt(rawHero.level, 10) || 1, 1, 10);
    const levelTitle = sanitizeDisplayText(rawHero.level_title || rawHero.rank_name || FUNKI_LEVEL_TITLES[level] || 'Ursprung');
    const sparksNum = Math.max(0, parseInt(rawHero.sparks ?? rawHero.funken_total_earned ?? (level * 250), 10) || 0);
    const sparksFormatted = rawHero.sparks_formatted
        ? sanitizeDisplayText(rawHero.sparks_formatted)
        : sparksNum.toLocaleString('de-DE');

    const fallbackClassLetter = level >= 9 ? 'S' : (level >= 6 ? 'A' : (level >= 3 ? 'B' : 'C'));
    const rawShipClass = sanitizeDisplayText(rawHero.ship_class || rawHero.rank_class || fallbackClassLetter).toUpperCase();
    const shipClass = rawShipClass.indexOf('KLASSE') === 0 ? rawShipClass : `KLASSE ${rawShipClass}`;

    const rawTitles = rawHero.titles_count ?? rawHero.diamonds_count;
    const titlesCount = (rawTitles !== undefined && rawTitles !== null)
        ? Math.max(0, parseInt(rawTitles, 10) || 0)
        : Math.max(1, Math.ceil(level / 2));

    return {
        slot,
        is_placeholder: false,
        is_me: Boolean(rawHero.is_me),
        is_preview: Boolean(rawHero.is_preview),
        display_name: sanitizeDisplayText(rawHero.display_name || `Held #${slot}`),
        level,
        level_title: levelTitle,
        sparks: sparksNum,
        sparks_formatted: sparksFormatted,
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
    envScene.background = new THREE.Color(0x12151e);

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

    addLightPanel(20, 20, 0xfffdf5, 0, 14, 0, Math.PI * 0.5, 0, 0);
    addLightPanel(16, 6.2, 0xfff5dc, 0, 9.5, -11, 0.52, 0, 0);
    addLightPanel(16, 6.2, 0xfff5dc, 0, 9.5, 11, -0.52, 0, 0);
    addLightPanel(15, 5.8, 0xfaf6ec, -11, 9.0, 0, 0, Math.PI * 0.5, -0.45);
    addLightPanel(15, 5.8, 0xfaf6ec, 11, 9.0, 0, 0, -Math.PI * 0.5, 0.45);

    addLightPanel(22, 1.8, 0xfde68a, -13, 3.0, 0, 0, Math.PI * 0.5, 0);
    addLightPanel(22, 1.8, 0xfde68a, 13, 3.0, 0, 0, -Math.PI * 0.5, 0);
    addLightPanel(18, 1.6, 0xd4af37, 0, 3.4, -13, 0, 0, 0);
    addLightPanel(18, 1.4, 0xfff1c9, 0, 3.6, 13, 0, Math.PI, 0);
    addLightPanel(30, 30, 0x161922, 0, -6, 0, -Math.PI * 0.5, 0, 0);

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
 * 1:1 Carrara-Alabaster & Atlantis-Saphir Pantheon-Marmorboden (#ECEAE4 / #E2DFD7, #94A3B8 Adern, #133B5C Saphir & 24K-Gold-Ringe).
 */
function buildHallOfHeroesMarbleTexture(THREE, maxAniso, disposables) {
    const S = 1024;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    const cx = S * 0.5;
    const cy = S * 0.5;

    const rg = ctx.createRadialGradient(cx, cy, 40, cx, cy, S * 0.68);
    rg.addColorStop(0.0, '#ECEAE4');
    rg.addColorStop(0.62, '#E2DFD7');
    rg.addColorStop(1.0, '#DAD5C9');
    ctx.fillStyle = rg;
    ctx.fillRect(0, 0, S, S);

    const slabs = 8;
    const step = S / slabs;
    const rng = mulberry32(20261445);
    for (let r = 0; r < slabs; r++) {
        for (let col = 0; col < slabs; col++) {
            const shade = Math.round((rng() - 0.5) * 10);
            ctx.fillStyle = `rgba(${234 + shade}, ${231 + shade}, ${223 + shade}, 0.46)`;
            ctx.fillRect(col * step + 1.5, r * step + 1.5, step - 3, step - 3);
        }
    }

    // Feine Carrara-Adern (#94A3B8) + dezente 24K-Gold-Marmoradern (#C5A059)
    for (let i = 0; i < 62; i++) {
        const isGoldVein = (i % 5 === 0);
        ctx.strokeStyle = isGoldVein
            ? 'rgba(197, 160, 89, 0.30)'
            : (i % 3 === 0 ? 'rgba(148, 163, 184, 0.28)' : 'rgba(100, 116, 139, 0.20)');
        ctx.lineWidth = isGoldVein ? 2.2 : (i % 4 === 0 ? 2.0 : 1.2);
        ctx.beginPath();
        let x = rng() * S;
        let y = rng() * S;
        ctx.moveTo(x, y);
        for (let s = 0; s < 6; s++) {
            x += (rng() - 0.42) * 185;
            y += (rng() - 0.48) * 145;
            ctx.lineTo(x, y);
        }
        ctx.stroke();
    }

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.34)';
    ctx.lineWidth = 1.6;
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
    }

    // Aeussere Saphir-Einfassung (#133B5C) flankiert von 24K-Gold-Baendern (#C5A059 / #D4AF37)
    ctx.strokeStyle = '#133B5C';
    ctx.lineWidth = 42;
    ctx.strokeRect(42, 42, S - 84, S - 84);

    ctx.strokeStyle = '#D4AF37';
    ctx.lineWidth = 7;
    ctx.strokeRect(20, 20, S - 40, S - 40);
    ctx.strokeRect(65, 65, S - 130, S - 130);

    // Ehrenlaeufer auf der Mittelachse
    ctx.fillStyle = 'rgba(19, 59, 92, 0.12)';
    ctx.fillRect(cx - 88, 68, 176, S - 136);
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 5;
    ctx.strokeRect(cx - 88, 68, 176, S - 136);

    // Zentrales Saphir- & 24K-Gold-Sonnenmedaillon im Ehrenhof
    const medY = cy - 48;
    ctx.fillStyle = '#133B5C';
    ctx.beginPath();
    ctx.arc(cx, medY, 204, 0, TWO_PI);
    ctx.fill();

    ctx.strokeStyle = '#D4AF37';
    ctx.lineWidth = 9;
    ctx.beginPath();
    ctx.arc(cx, medY, 206, 0, TWO_PI);
    ctx.stroke();

    ctx.fillStyle = '#ECEAE4';
    ctx.beginPath();
    ctx.arc(cx, medY, 168, 0, TWO_PI);
    ctx.fill();

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 7;
    ctx.beginPath();
    ctx.arc(cx, medY, 166, 0, TWO_PI);
    ctx.stroke();

    ctx.strokeStyle = '#133B5C';
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.arc(cx, medY, 112, 0, TWO_PI);
    ctx.stroke();

    for (let k = 0; k < 12; k++) {
        const ang = (k / 12) * TWO_PI;
        ctx.strokeStyle = k % 2 === 0 ? '#C5A059' : '#133B5C';
        ctx.lineWidth = k % 2 === 0 ? 5.0 : 2.8;
        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(ang) * 28, medY + Math.sin(ang) * 28);
        ctx.lineTo(cx + Math.cos(ang) * 162, medY + Math.sin(ang) * 162);
        ctx.stroke();
    }

    ctx.fillStyle = '#D4AF37';
    ctx.beginPath();
    ctx.arc(cx, medY, 26, 0, TWO_PI);
    ctx.fill();

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

function buildContactShadowTexture(THREE, disposables) {
    const S = 128;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');

    const g = ctx.createRadialGradient(S * 0.5, S * 0.5, S * 0.08, S * 0.5, S * 0.5, S * 0.49);
    g.addColorStop(0.0, 'rgba(0, 0, 0, 0.84)');
    g.addColorStop(0.48, 'rgba(0, 0, 0, 0.52)');
    g.addColorStop(0.78, 'rgba(0, 0, 0, 0.16)');
    g.addColorStop(1.0, 'rgba(0, 0, 0, 0.0)');
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

    ctx.font = '600 19px system-ui, -apple-system, "Segoe UI", sans-serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '5px';
    }
    ctx.fillStyle = '#C5A059';
    ctx.fillText(subText, W * 0.5, H * 0.77, W - 90);

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
        ctx.font = '800 38px "Liberation Sans", "Segoe UI", sans-serif';
        ctx.fillText(
            `#${slotNumber} · ${norm.ship_class} · STUFE ${norm.level}${norm.is_me ? ' · DU' : ''}`,
            W * 0.5,
            H * 0.27,
            W - 90
        );

        ctx.fillStyle = 'rgba(197, 160, 89, 0.45)';
        ctx.fillRect(W * 0.18, H * 0.42, W * 0.64, 2.5);

        const ng = ctx.createLinearGradient(0, H * 0.46, 0, H * 0.86);
        ng.addColorStop(0, '#FFFDF5');
        ng.addColorStop(0.35, '#FDE68A');
        ng.addColorStop(0.7, '#D4AF37');
        ng.addColorStop(1, '#B88D3B');
        ctx.fillStyle = ng;
        ctx.font = '900 80px "Liberation Sans", "Segoe UI", sans-serif';
        ctx.fillText(norm.display_name, W * 0.5, H * 0.67, W - 88);
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
    ctx.fillText('✦', W * 0.5, H * 0.16);

    if (!norm.is_placeholder) {
        ctx.fillStyle = '#FDE68A';
        ctx.font = '800 40px "Liberation Sans", sans-serif';
        ctx.fillText(norm.display_name, W * 0.5, H * 0.42, W - 36);

        ctx.fillStyle = '#C5A059';
        ctx.font = '700 26px "Liberation Sans", sans-serif';
        ctx.fillText(`#${slotNumber} · ${norm.ship_class}`, W * 0.5, H * 0.63, W - 36);
        ctx.fillText(`STUFE ${norm.level}${norm.is_me ? ' · DU' : ''}`, W * 0.5, H * 0.79, W - 36);
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

function buildFunkiScreenFaceTexture(THREE, maxAniso, level, disposables) {
    const W = 256;
    const H = 192;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const bg = ctx.createRadialGradient(W * 0.5, H * 0.5, 10, W * 0.5, H * 0.5, W * 0.65);
    bg.addColorStop(0, '#161b26');
    bg.addColorStop(1, '#07090e');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.fillStyle = 'rgba(255, 255, 255, 0.03)';
    for (let y = 0; y < H; y += 4) {
        ctx.fillRect(0, y, W, 1);
    }

    let eyeColor = '#FDE68A';
    if (level >= 10) {
        eyeColor = '#FFFBEB';
    } else if (level === 9) {
        eyeColor = '#7DD3FC';
    } else if (level === 1) {
        eyeColor = '#D6C79C';
    }

    ctx.save();
    ctx.fillStyle = eyeColor;
    ctx.strokeStyle = eyeColor;
    ctx.lineCap = 'round';

    if (level === 1) {
        for (let sx = -1; sx <= 1; sx += 2) {
            const ex = W * 0.5 + sx * 46;
            ctx.beginPath();
            ctx.ellipse(ex, H * 0.46, 16, 13, sx * 0.22, 0, TWO_PI);
            ctx.fill();
            ctx.lineWidth = 4;
            ctx.beginPath();
            ctx.moveTo(ex - sx * 16, H * 0.28);
            ctx.lineTo(ex + sx * 18, H * 0.34);
            ctx.stroke();
        }
        ctx.lineWidth = 5;
        ctx.beginPath();
        ctx.arc(W * 0.5, H * 0.78, 22, Math.PI * 1.18, Math.PI * 1.82, false);
        ctx.stroke();
    } else {
        for (let hx = -1; hx <= 1; hx += 2) {
            const hexX = W * 0.5 + hx * 48;
            ctx.beginPath();
            ctx.ellipse(hexX, H * 0.44, 17, level >= 4 ? 20 : 16, 0, 0, TWO_PI);
            ctx.fill();

            ctx.fillStyle = '#FFFFFF';
            ctx.beginPath();
            ctx.arc(hexX + 5, H * 0.39, 5, 0, TWO_PI);
            ctx.fill();
            ctx.fillStyle = eyeColor;
        }

        ctx.lineWidth = 5.5;
        if (level >= 4) {
            ctx.beginPath();
            ctx.arc(W * 0.5, H * 0.64, 24, Math.PI * 0.15, Math.PI * 0.85, false);
            ctx.stroke();
        } else {
            ctx.beginPath();
            ctx.arc(W * 0.5, H * 0.66, 16, Math.PI * 0.22, Math.PI * 0.78, false);
            ctx.stroke();
        }
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

function buildFunkiChestBadgeTexture(THREE, level, disposables) {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 128;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 256, 128);

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 4;
    ctx.strokeRect(18, 18, 220, 92);

    ctx.fillStyle = '#F3D587';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = '800 30px Georgia, serif';
    ctx.fillText('SEELENFUNKE', 128, 52, 200);

    ctx.fillStyle = '#C5A059';
    ctx.font = '700 20px system-ui, -apple-system, sans-serif';
    ctx.fillText(`FUNKI LV. ${level}`, 128, 86, 190);

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

function buildFunkiVisorTexture(THREE, isBlinking, disposables) {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 160;
    const ctx = c.getContext('2d');

    const bg = ctx.createRadialGradient(128, 76, 12, 128, 80, 140);
    bg.addColorStop(0, '#181c26');
    bg.addColorStop(1, '#090b10');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, 256, 160);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.55)';
    ctx.lineWidth = 4;
    ctx.strokeRect(8, 8, 240, 144);

    ctx.fillStyle = '#F5D98E';
    ctx.strokeStyle = '#F5D98E';
    ctx.lineCap = 'round';

    if (isBlinking) {
        ctx.lineWidth = 7;
        ctx.beginPath();
        ctx.moveTo(62, 68);
        ctx.quadraticCurveTo(84, 76, 106, 68);
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(150, 68);
        ctx.quadraticCurveTo(172, 76, 194, 68);
        ctx.stroke();
    } else {
        ctx.beginPath();
        ctx.ellipse(84, 66, 16, 20, 0, 0, TWO_PI);
        ctx.fill();

        ctx.beginPath();
        ctx.ellipse(172, 66, 16, 20, 0, 0, TWO_PI);
        ctx.fill();

        ctx.fillStyle = '#FFFFFF';
        ctx.beginPath();
        ctx.arc(78, 58, 5.5, 0, TWO_PI);
        ctx.arc(166, 58, 5.5, 0, TWO_PI);
        ctx.fill();
    }

    ctx.strokeStyle = '#E6C687';
    ctx.lineWidth = 6;
    ctx.beginPath();
    ctx.arc(128, 96, 26, 0.18 * Math.PI, 0.82 * Math.PI);
    ctx.stroke();

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
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

export class SeelenfunkeLouvreShowroom3D {
    constructor(options = {}) {
        this.canvas = options.canvas;
        this.floatingCardEl = options.floatingCardEl || null;
        this.floatingHeroCardEl = options.floatingHeroCardEl || null;
        this.floatingPortalCardEl = options.floatingPortalCardEl || null;

        this.products = Array.isArray(options.products) ? options.products : [];
        this.playerFunki = options.playerFunki || { is_guest: true, level: 2 };
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
            (this.playerFunki && !this.playerFunki.is_guest)
            || (this.heroesPayload && this.heroesPayload.customer_logged_in)
        );
        this.isAuthenticatedCustomer = (options.isAuthenticatedCustomer !== undefined)
            ? Boolean(options.isAuthenticatedCustomer)
            : inferredAuth;

        const rawInitialZone = String(options.initialZone || 'gallery').toLowerCase();
        this.initialZone = (rawInitialZone === 'heroes' || rawInitialZone === 'hall' || rawInitialZone === 'my_place')
            ? rawInitialZone
            : 'gallery';

        this.onSelectProduct = typeof options.onSelectProduct === 'function' ? options.onSelectProduct : null;
        this.onActiveExhibitChange = typeof options.onActiveExhibitChange === 'function' ? options.onActiveExhibitChange : null;
        this.onFocusProductChange = typeof options.onFocusProductChange === 'function' ? options.onFocusProductChange : null;
        this.onFocusHeroChange = typeof options.onFocusHeroChange === 'function' ? options.onFocusHeroChange : null;
        this.onZoneChange = typeof options.onZoneChange === 'function' ? options.onZoneChange : null;
        this.onPortalGatePromptChange = typeof options.onPortalGatePromptChange === 'function' ? options.onPortalGatePromptChange : null;

        this.THREE = null;
        this.renderer = null;
        this.scene = null;
        this.camera = null;
        this.envTexture = null;

        this.disposables = [];
        this.glbCache = new Map();
        this.glbTemplateCache = new Map();
        this.glbLoadingPromises = new Map();
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

        this.hallHalfW = 10.6;
        this.hallNorthZ = -18;
        this.hallSouthZ = 14;
        this.portalHalfW = 4.25;
        this.promenadeLength = 10.0;
        this.heroesEntryZ = -28;
        this.pantheonCenterZ = -49.5;
        this.pantheonHalfW = 20.2;
        this.pantheonMinZ = -70.0;
        this.currentZone = 'gallery';

        this.steles = [];
        this.exhibitLoadQueue = [];
        this.isLoadingExhibit = false;
        this.interactiveHitMeshes = [];
        this.heroInteractiveHitMeshes = [];
        this.portalHitMeshes = [];
        this.walkableFloorMeshes = [];
        this.floorMesh = null;
        this.walkTargetRing = null;
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
        this.stagesPreviewMode = 'live'; // 'live' | '1-5' | '6-10'

        this.activeIndex = 0;
        this.focusedSteleIndex = -1;
        this.pinnedSteleIndex = -1;
        this.focusedHeroSlot = -1;
        this.pinnedHeroSlot = -1;
        this.portalGateFocused = false;
        this.pinnedPortalGate = false;

        this._cardScreenX = -9999;
        this._cardScreenY = -9999;
        this._cardVisible = false;

        this._heroCardScreenX = -9999;
        this._heroCardScreenY = -9999;
        this._heroCardVisible = false;

        this._portalCardScreenX = -9999;
        this._portalCardScreenY = -9999;
        this._portalCardVisible = false;

        this.player = {
            root: null,
            joints: null,
            visorMat: null,
            visorTexOpen: null,
            visorTexBlink: null,
            isBlinking: false,
            nextBlinkAt: 2.8,
            blinkEndAt: 0,
            x: 0,
            y: 0,
            z: 8.5,
            vy: 0,
            isJumping: false,
            jumpCount: 0,
            isSomersault: false,
            somersaultProgress: 0.0,
            somersaultDuration: 0.52,
            landSquash: 0,
            yaw: Math.PI,
            walkPhase: 0,
            moveBlend: 0,
            turnBank: 0,
            headYaw: 0,
            headPitch: 0,
            isMoving: false,
            currentSpeed: 0,
            autoTarget: null,
            autoLookAt: null,
            weaponActionSeq: 0,
            weaponSwingTimer: 0,
            weaponCooldown: 0,
            punchSwingTimer: 0,
            punchCooldown: 0,
            punchCombo: 0,
            bubbleSprite: null,
            bubbleTex: null,
            bubbleMat: null,
            bubbleExpiresAt: 0,
        };

        this._avatarShared = null;
        this._funkiFallbackMats = null;
        this.peers = new Map();
        this.weaponBursts = [];
        this.soloMode = Boolean(options.soloMode);
        this.focusProtected = false;
        this.mutedPlayerIds = new Set();
        this.fpsEstimate = 60.0;

        this.camControl = {
            yaw: 0,
            pitch: 0.24,
            distance: 4.35,
            desiredYaw: 0,
            desiredPitch: 0.24,
            desiredDistance: 4.35,
            lookAtCurrent: null,
            isDragging: false,
            dragMoved: 0,
            lastX: 0,
            lastY: 0,
            pinchDist: 0,
        };

        this.keys = {};
        this.joystick = { x: 0, y: 0 };

        this.raycaster = null;
        this.pointerNdc = null;
        this._projVec = null;
        this._collisionOut = { x: 0, z: 0 };
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
        this.renderer.toneMappingExposure = 1.14;
        this.maxAniso = this.renderer.capabilities ? this.renderer.capabilities.getMaxAnisotropy() : 4;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0b0e15);
        this.scene.fog = new THREE.FogExp2(0x0d1018, 0.0032);

        this.envTexture = buildLouvreStudioEnvironment(THREE, this.renderer, this.disposables);
        if (this.envTexture) {
            this.scene.environment = this.envTexture;
        }

        this._buildLighting();
        this._buildLouvreArchitecture(isMobile);
        this._buildGoldenHonorPortalAndPromenade();
        this._buildHallOfHeroes();
        this._populateHeroesMonuments();
        this._buildExhibitSteles(baySpacing);
        this._buildPlayerCharacter();
        this._bindEvents();

        this.resize();

        if ((this.initialZone === 'heroes' || this.initialZone === 'hall' || this.initialZone === 'my_place') && this.isAuthenticatedCustomer) {
            if (this.initialZone === 'my_place') {
                this.goToZone('heroes', true);
                this.goToMyHeroPlace();
            } else {
                this.goToZone('heroes', true);
            }
        } else {
            this.currentZone = 'gallery';
            this.camControl.lookAtCurrent.set(this.player.x, 1.38, this.player.z);
            if (this.onZoneChange) {
                this.onZoneChange('gallery');
            }
        }

        if (this.steles.length > 0 && this.onActiveExhibitChange) {
            this.onActiveExhibitChange(0, this.products[0]);
        }

        this._scheduleNextExhibitLoad(16);

        this.running = true;
        this.lastTimeMs = performance.now();
        this.rafId = requestAnimationFrame(this._boundTick);
    }

    _trackDisposable(item) {
        if (item) this.disposables.push(item);
        return item;
    }

    /**
     * Zero Dynamic Light Overload: HemisphereLight + DirectionalLights ohne Shadow-Maps (0 PointLight, 0 SpotLight).
     */
    _buildLighting() {
        const THREE = this.THREE;

        const hemi = new THREE.HemisphereLight(0xfffaf0, 0x1e2433, 1.24);
        hemi.position.set(0, 22, this.hallNorthZ);
        this.scene.add(hemi);

        const dirMain = new THREE.DirectionalLight(0xfff6e0, 1.62);
        dirMain.position.set(8, 24, 12);
        dirMain.castShadow = false;
        this.scene.add(dirMain);

        const dirPantheon = new THREE.DirectionalLight(0xffe9b8, 0.92);
        dirPantheon.position.set(-8, 26, this.pantheonCenterZ + 18);
        dirPantheon.target.position.set(0, 2, this.pantheonCenterZ - 5);
        dirPantheon.castShadow = false;
        this.scene.add(dirPantheon);
        this.scene.add(dirPantheon.target);
    }

    _buildLouvreArchitecture(isMobile) {
        const THREE = this.THREE;
        const totalLength = (this.hallSouthZ - this.hallNorthZ) + 4.0;
        const centerZ = (this.hallSouthZ + this.hallNorthZ) * 0.5;
        const hallW = 24.0;
        const wallH = 7.4;
        const pyramidApexY = 14.8;

        const mcGold24k = buildStudioMatcapTexture(THREE, '#D4AF37', '#FFFDF2', '#FDE68A', 38, 0.54, this.disposables);
        const mcGoldBrushed = buildStudioMatcapTexture(THREE, '#C5A059', '#FFF5D6', '#E6C687', 26, 0.40, this.disposables);
        const mcObsidian = buildStudioMatcapTexture(THREE, '#111520', '#647087', '#C5A059', 28, 0.24, this.disposables);

        this.mats = {
            obsidianWall: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x0c0f15,
                roughness: 0.64,
                metalness: 0.18,
                envMapIntensity: 0.58,
            })),
            cofferRecess: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x07090d,
                roughness: 0.78,
                metalness: 0.12,
                envMapIntensity: 0.35,
            })),
            archSteel: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x0d1016,
                roughness: 0.38,
                metalness: 0.72,
                envMapIntensity: 0.9,
            })),
            goldBrushed: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xc5a059,
                roughness: 0.24,
                metalness: 0.88,
                envMapIntensity: 1.32,
            })),
            goldChampagne: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xe6c687,
                roughness: 0.18,
                metalness: 0.92,
                envMapIntensity: 1.42,
            })),
            goldMatcapBright: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: mcGold24k,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -2,
            })),
            goldMatcapBrushed: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: mcGoldBrushed,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -2,
            })),
            obsidianMatcap: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: mcObsidian,
            })),
            alabasterStep: this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0xe6e3da })),
            sapphireTrim: this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0x133b5c })),
            beaconGold: this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0xffe89e })),
            mySlotGlow: this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0xfde68a })),
            velvetPad: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x11131a,
                roughness: 0.94,
                metalness: 0.04,
            })),
            pyramidGlass: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xc8d8ec,
                transparent: true,
                opacity: 0.14,
                depthWrite: false,
                side: THREE.DoubleSide,
            })),
            vitrineGlass: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf4f8fc,
                roughness: 0.06,
                metalness: 0.12,
                envMapIntensity: 1.2,
                transparent: true,
                opacity: 0.13,
                depthWrite: false,
            })),
            spotlightCone: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xfff3d6,
                transparent: true,
                opacity: 0.036,
                depthWrite: false,
                side: THREE.DoubleSide,
            })),
        };

        this.contactShadowTex = buildContactShadowTexture(THREE, this.disposables);
        this.sparkSpriteTex = buildSparkParticleTexture(THREE, this.disposables);
        this.mats.contactShadow = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: this.contactShadowTex,
            transparent: true,
            opacity: 0.78,
            depthWrite: false,
        }));

        // Atmosphaerische Himmelskuppel ueber beiden Bereichen (Louvre + Pantheon)
        const skyGeo = this._trackDisposable(new THREE.SphereGeometry(220, 24, 16));
        const skyCanvas = document.createElement('canvas');
        skyCanvas.width = 32;
        skyCanvas.height = 128;
        const skyCtx = skyCanvas.getContext('2d');
        const skyGrad = skyCtx.createLinearGradient(0, 0, 0, 128);
        skyGrad.addColorStop(0.0, '#16233b');
        skyGrad.addColorStop(0.36, '#2a3854');
        skyGrad.addColorStop(0.56, '#4d412c');
        skyGrad.addColorStop(1.0, '#0a0d14');
        skyCtx.fillStyle = skyGrad;
        skyCtx.fillRect(0, 0, 32, 128);
        const skyTex = this._trackDisposable(new THREE.CanvasTexture(skyCanvas));
        applySrgbColorSpace(THREE, skyTex);
        const skyMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: skyTex, side: THREE.BackSide, fog: false }));
        const skyDome = new THREE.Mesh(skyGeo, skyMat);
        skyDome.position.set(0, 0, (this.hallSouthZ + this.pantheonCenterZ) * 0.5);
        this.scene.add(skyDome);

        // Polierter Nero-Marquina-Marmorboden in der Produkt-Eingangshalle
        const repX = hallW / 6.0;
        const repZ = totalLength / 6.0;
        const marbleMaps = buildNeroMarquinaFloorMaps(THREE, this.maxAniso, isMobile, repX, repZ, this.disposables);
        const floorMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            map: marbleMaps.map,
            roughnessMap: marbleMaps.roughnessMap,
            roughness: 0.19,
            metalness: 0.08,
            envMapIntensity: 1.1,
        }));

        const floorGeo = this._trackDisposable(new THREE.PlaneGeometry(hallW, totalLength));
        this.floorMesh = new THREE.Mesh(floorGeo, floorMat);
        this.floorMesh.rotation.x = -Math.PI * 0.5;
        this.floorMesh.position.set(0, 0, centerZ);
        this.floorMesh.userData = { isFloor: true };
        this.scene.add(this.floorMesh);
        this.walkableFloorMeshes.push(this.floorMesh);

        // Goldene Boden-Intarsien als gebuendeltes InstancedMesh
        const inlayLength = totalLength - 3.0;
        const inlayStripGeo = this._trackDisposable(new THREE.PlaneGeometry(0.045, inlayLength));
        const inlayPositions = [-2.25, -2.12, 2.12, 2.25];
        const inlayInst = new THREE.InstancedMesh(inlayStripGeo, this.mats.goldBrushed, inlayPositions.length);
        const dummy = new THREE.Object3D();
        for (let idx = 0; idx < inlayPositions.length; idx++) {
            dummy.position.set(inlayPositions[idx], 0.0025, centerZ);
            dummy.rotation.set(-Math.PI * 0.5, 0, 0);
            dummy.scale.set(1, 1, 1);
            dummy.updateMatrix();
            inlayInst.setMatrixAt(idx, dummy.matrix);
        }
        this.scene.add(inlayInst);

        const medOuter = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(1.18, 1.28, 4)),
            this.mats.goldChampagne
        );
        medOuter.rotation.x = -Math.PI * 0.5;
        medOuter.position.set(0, 0.003, centerZ);
        this.scene.add(medOuter);

        const medInner = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.62, 0.69, 4)),
            this.mats.goldBrushed
        );
        medInner.rotation.x = -Math.PI * 0.5;
        medInner.position.set(0, 0.003, centerZ);
        this.scene.add(medInner);

        // Tap-to-Walk Zielring auf dem Marmorboden
        const targetRingGeo = this._trackDisposable(new THREE.RingGeometry(0.26, 0.34, 28));
        const targetRingMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            color: 0xe6c687,
            transparent: true,
            opacity: 0.0,
            side: THREE.DoubleSide,
            depthWrite: false,
        }));
        this.walkTargetRing = new THREE.Mesh(targetRingGeo, targetRingMat);
        this.walkTargetRing.rotation.x = -Math.PI * 0.5;
        this.walkTargetRing.position.set(0, 0.025, 0);
        this.scene.add(this.walkTargetRing);

        // Seitenwaende & Gesimsleisten
        const sideWallGeo = this._trackDisposable(new THREE.BoxGeometry(0.6, wallH, totalLength));
        const trimGeo = this._trackDisposable(new THREE.BoxGeometry(0.72, 0.08, totalLength));
        const corniceBlockGeo = this._trackDisposable(new THREE.BoxGeometry(0.95, 0.42, totalLength));

        [-hallW * 0.5, hallW * 0.5].forEach((wx) => {
            const wall = new THREE.Mesh(sideWallGeo, this.mats.cofferRecess);
            wall.position.set(wx, wallH * 0.5, centerZ);
            this.scene.add(wall);

            const baseTrim = new THREE.Mesh(trimGeo, this.mats.goldBrushed);
            baseTrim.position.set(wx, 0.12, centerZ);
            this.scene.add(baseTrim);

            const cornice = new THREE.Mesh(corniceBlockGeo, this.mats.obsidianWall);
            cornice.position.set(wx, wallH, centerZ);
            this.scene.add(cornice);

            const topGoldTrim = new THREE.Mesh(trimGeo, this.mats.goldBrushed);
            topGoldTrim.position.set(wx, wallH - 0.22, centerZ);
            this.scene.add(topGoldTrim);
        });

        // Kolonnaden-Pfeiler & Gold-Kapitelle der Produktgalerie via InstancedMesh
        const pierSpacing = 6.2;
        const pierCountSide = Math.max(3, Math.floor(totalLength / pierSpacing));
        const pierGeo = this._trackDisposable(new THREE.BoxGeometry(0.75, wallH - 0.3, 0.75));
        const capGeo = this._trackDisposable(new THREE.BoxGeometry(0.92, 0.12, 0.92));

        const totalPiers = pierCountSide * 2;
        const pierInst = new THREE.InstancedMesh(pierGeo, this.mats.obsidianWall, totalPiers);
        const capInst = new THREE.InstancedMesh(capGeo, this.mats.goldBrushed, totalPiers * 2);

        let pIdx = 0;
        let cIdx = 0;
        for (let i = 0; i < pierCountSide; i++) {
            const pz = this.hallNorthZ + 2.5 + i * ((totalLength - 5.0) / Math.max(1, pierCountSide - 1));
            [-hallW * 0.5 + 0.42, hallW * 0.5 - 0.42].forEach((px) => {
                dummy.position.set(px, (wallH - 0.3) * 0.5, pz);
                dummy.rotation.set(0, 0, 0);
                dummy.scale.set(1, 1, 1);
                dummy.updateMatrix();
                pierInst.setMatrixAt(pIdx++, dummy.matrix);

                dummy.position.set(px, wallH - 0.32, pz);
                dummy.updateMatrix();
                capInst.setMatrixAt(cIdx++, dummy.matrix);

                dummy.position.set(px, 0.18, pz);
                dummy.updateMatrix();
                capInst.setMatrixAt(cIdx++, dummy.matrix);
            });
        }
        this.scene.add(pierInst);
        this.scene.add(capInst);

        // Sued-Stirnwand (Eingang) + Nord-Stirnwand GEÖFFNET fuer das monumentale 24K-Gold-Ehrenportal!
        const southWallGeo = this._trackDisposable(new THREE.BoxGeometry(hallW + 0.6, wallH + 0.4, 0.7));
        const southWall = new THREE.Mesh(southWallGeo, this.mats.obsidianWall);
        southWall.position.set(0, (wallH + 0.4) * 0.5, this.hallSouthZ);
        this.scene.add(southWall);

        const wingSpan = (hallW * 0.5) - this.portalHalfW;
        const wingWallGeo = this._trackDisposable(new THREE.BoxGeometry(wingSpan, wallH + 0.4, 0.75));
        [-1, 1].forEach((sideSign) => {
            const wingWall = new THREE.Mesh(wingWallGeo, this.mats.obsidianWall);
            wingWall.position.set(
                sideSign * (this.portalHalfW + wingSpan * 0.5),
                (wallH + 0.4) * 0.5,
                this.hallNorthZ
            );
            this.scene.add(wingWall);
        });

        // Oberer Architrav ueber dem offenen Ehrenportal (ab y = 5.65 bis wallH + 0.4)
        const portalHeaderH = (wallH + 0.4) - 5.65;
        const portalHeader = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(this.portalHalfW * 2 + 0.4, portalHeaderH, 0.85)),
            this.mats.obsidianWall
        );
        portalHeader.position.set(0, 5.65 + portalHeaderH * 0.5, this.hallNorthZ);
        this.scene.add(portalHeader);

        const gableShape = new THREE.Shape();
        gableShape.moveTo(-hallW * 0.5, wallH);
        gableShape.lineTo(0, pyramidApexY);
        gableShape.lineTo(hallW * 0.5, wallH);
        gableShape.closePath();
        const gableGeo = this._trackDisposable(new THREE.ShapeGeometry(gableShape));

        const northGableGlass = new THREE.Mesh(gableGeo, this.mats.pyramidGlass);
        northGableGlass.position.set(0, 0, this.hallNorthZ);
        this.scene.add(northGableGlass);

        const southGableGlass = new THREE.Mesh(gableGeo, this.mats.pyramidGlass);
        southGableGlass.position.set(0, 0, this.hallSouthZ);
        this.scene.add(southGableGlass);

        // Stirnfries ueber dem goldenen Ehrenportal
        const friezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'HALLE DER HELDEN',
            'EHRENPORTAL · MEIN SEELENFUNKE',
            this.disposables
        );
        const friezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: friezeTex }));
        const friezeBacking = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(10.6, 1.82, 0.24)),
            this.mats.goldMatcapBright
        );
        friezeBacking.position.set(0, 6.58, this.hallNorthZ + 0.44);
        this.scene.add(friezeBacking);

        const friezePlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(10.32, 1.62)),
            friezeMat
        );
        friezePlane.position.set(0, 6.58, this.hallNorthZ + 0.57);
        this.scene.add(friezePlane);

        const southFriezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'UNSERE KOLLEKTION',
            'MANUFAKTUR GIFHORN · 3D USER LOBBY',
            this.disposables
        );
        const southFriezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: southFriezeTex }));
        const southFriezePlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(9.6, 1.72)),
            southFriezeMat
        );
        southFriezePlane.rotation.y = Math.PI;
        southFriezePlane.position.set(0, 4.95, this.hallSouthZ - 0.42);
        this.scene.add(southFriezePlane);

        // Louvre-Glaspyramiden-Dach
        const halfSpanX = hallW * 0.5;
        const riseY = pyramidApexY - wallH;
        const slopeLen = Math.hypot(halfSpanX, riseY);
        const slopeAngle = Math.atan2(riseY, halfSpanX);
        const sharedSlopeGlassGeo = this._trackDisposable(new THREE.PlaneGeometry(slopeLen, totalLength));
        const unitBeamGeo = this._trackDisposable(new THREE.BoxGeometry(1, 0.09, 1));
        const nodeGeo = this._trackDisposable(new THREE.OctahedronGeometry(0.085, 0));

        const uDivs = 6;
        const vDivs = Math.max(8, Math.round(totalLength / 2.8));
        const mapUVToSlope = (u, v) => ({
            x: (u - 0.5) * slopeLen,
            z: (v - 0.5) * totalLength,
        });

        const beamSegments = [];
        const goldNodes = [];
        for (let iu = 0; iu <= uDivs; iu++) {
            const u = iu / uDivs;
            const pA = mapUVToSlope(u, 0);
            const pB = mapUVToSlope(u, 1);
            beamSegments.push([pA.x, 0, pA.z, pB.x, 0, pB.z, 0.055]);
        }
        for (let iv = 0; iv < vDivs; iv++) {
            const v0 = iv / vDivs;
            const v1 = (iv + 1) / vDivs;
            for (let iu = 0; iu < uDivs; iu++) {
                const u0 = iu / uDivs;
                const u1 = (iu + 1) / uDivs;
                const c00 = mapUVToSlope(u0, v0);
                const c10 = mapUVToSlope(u1, v0);
                const c01 = mapUVToSlope(u0, v1);
                const c11 = mapUVToSlope(u1, v1);
                const mid = mapUVToSlope((u0 + u1) * 0.5, (v0 + v1) * 0.5);

                beamSegments.push([c00.x, 0, c00.z, c11.x, 0, c11.z, 0.068]);
                beamSegments.push([c10.x, 0, c10.z, c01.x, 0, c01.z, 0.068]);
                goldNodes.push([c00.x, -0.02, c00.z]);
                goldNodes.push([mid.x, -0.02, mid.z]);
            }
        }

        [-1, 1].forEach((sideSign) => {
            const slopeGroup = new THREE.Group();
            slopeGroup.position.set(sideSign * halfSpanX * 0.5, wallH + riseY * 0.5, centerZ);
            slopeGroup.rotation.z = -sideSign * slopeAngle;

            const glassMesh = new THREE.Mesh(sharedSlopeGlassGeo, this.mats.pyramidGlass);
            glassMesh.rotation.x = -Math.PI * 0.5;
            slopeGroup.add(glassMesh);

            const beamInst = new THREE.InstancedMesh(unitBeamGeo, this.mats.archSteel, beamSegments.length);
            for (let b = 0; b < beamSegments.length; b++) {
                const [x1, y1, z1, x2, y2, z2, thick] = beamSegments[b];
                const dx = x2 - x1;
                const dz = z2 - z1;
                const len = Math.hypot(dx, dz);
                dummy.position.set((x1 + x2) * 0.5, (y1 + y2) * 0.5, (z1 + z2) * 0.5);
                dummy.rotation.set(0, -Math.atan2(dz, dx), 0);
                dummy.scale.set(len, 1.0, thick);
                dummy.updateMatrix();
                beamInst.setMatrixAt(b, dummy.matrix);
            }
            slopeGroup.add(beamInst);

            const nodeInst = new THREE.InstancedMesh(nodeGeo, this.mats.goldChampagne, goldNodes.length);
            for (let n = 0; n < goldNodes.length; n++) {
                dummy.position.set(goldNodes[n][0], goldNodes[n][1], goldNodes[n][2]);
                dummy.rotation.set(0, Math.PI * 0.25, 0);
                dummy.scale.set(1.0, 0.65, 1.0);
                dummy.updateMatrix();
                nodeInst.setMatrixAt(n, dummy.matrix);
            }
            slopeGroup.add(nodeInst);
            this.scene.add(slopeGroup);
        });

        const ridgeBeam = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.24, 0.24, totalLength)),
            this.mats.goldBrushed
        );
        ridgeBeam.position.set(0, pyramidApexY, centerZ);
        this.scene.add(ridgeBeam);
    }

    /**
     * Monumentales 24K-Gold-Ehrenportal + Carrara-Marmor-Ehrenpromenade zwischen Produktgalerie und Halle der Helden.
     */
    _buildGoldenHonorPortalAndPromenade() {
        const THREE = this.THREE;
        const promCenterZ = (this.hallNorthZ + this.heroesEntryZ) * 0.5;
        const promW = this.portalHalfW * 2 + 1.2;

        // 1. Monumentale 24K-Gold-Portalsaeulen & goldener Portalrahmen an hallNorthZ
        const portalPillarGeo = this._trackDisposable(new THREE.CylinderGeometry(0.42, 0.48, 5.65, 16));
        const portalBaseGeo = this._trackDisposable(new THREE.BoxGeometry(1.18, 0.28, 1.18));
        const portalCapGeo = this._trackDisposable(new THREE.BoxGeometry(1.24, 0.36, 1.24));

        [-this.portalHalfW, this.portalHalfW].forEach((px) => {
            const pillar = new THREE.Mesh(portalPillarGeo, this.mats.goldMatcapBright);
            pillar.position.set(px, 2.825, this.hallNorthZ);
            this.scene.add(pillar);

            const base = new THREE.Mesh(portalBaseGeo, this.mats.goldMatcapBright);
            base.position.set(px, 0.14, this.hallNorthZ);
            this.scene.add(base);

            const cap = new THREE.Mesh(portalCapGeo, this.mats.goldMatcapBright);
            cap.position.set(px, 5.56, this.hallNorthZ);
            this.scene.add(cap);
        });

        const lintelGold = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(this.portalHalfW * 2 + 1.4, 0.34, 1.28)),
            this.mats.goldMatcapBright
        );
        lintelGold.position.set(0, 5.62, this.hallNorthZ);
        this.scene.add(lintelGold);

        // 2. Carrara-Alabaster & 24K-Gold Ehrenpromenade (fuehrt direkt in die Nord-Rotunde)
        const promFloor = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(promW, this.promenadeLength + 2.4)),
            this.mats.alabasterStep
        );
        promFloor.rotation.x = -Math.PI * 0.5;
        promFloor.position.set(0, 0.012, promCenterZ);
        promFloor.userData = { isFloor: true };
        this.scene.add(promFloor);
        this.walkableFloorMeshes.push(promFloor);

        const carpetStrip = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(2.6, this.promenadeLength + 3.2)),
            this.mats.sapphireTrim
        );
        carpetStrip.rotation.x = -Math.PI * 0.5;
        carpetStrip.position.set(0, 0.016, promCenterZ);
        this.scene.add(carpetStrip);

        [-1.34, 1.34, -this.portalHalfW + 0.12, this.portalHalfW - 0.12].forEach((gx) => {
            const gLine = new THREE.Mesh(
                this._trackDisposable(new THREE.PlaneGeometry(0.11, this.promenadeLength + 3.2)),
                this.mats.goldMatcapBright
            );
            gLine.rotation.x = -Math.PI * 0.5;
            gLine.position.set(gx, 0.019, promCenterZ);
            this.scene.add(gLine);
        });

        // Seitliche goldene Balustraden & Ehren-Kolonnade entlang der Promenade
        const balustradeGeo = this._trackDisposable(new THREE.BoxGeometry(0.36, 1.02, this.promenadeLength));
        const balustradeCapGeo = this._trackDisposable(new THREE.BoxGeometry(0.50, 0.14, this.promenadeLength));
        [-this.portalHalfW - 0.35, this.portalHalfW + 0.35].forEach((bx) => {
            const bal = new THREE.Mesh(balustradeGeo, this.mats.obsidianMatcap);
            bal.position.set(bx, 0.51, promCenterZ);
            this.scene.add(bal);

            const bCap = new THREE.Mesh(balustradeCapGeo, this.mats.goldMatcapBright);
            bCap.position.set(bx, 1.06, promCenterZ);
            this.scene.add(bCap);
        });

        const promColGeo = this._trackDisposable(new THREE.CylinderGeometry(0.32, 0.38, 5.5, 14));
        const promArchGeo = this._trackDisposable(new THREE.TorusGeometry(this.portalHalfW + 0.35, 0.14, 8, 28, Math.PI));
        for (let s = 0; s < 3; s++) {
            const sz = lerp(this.hallNorthZ - 2.2, this.heroesEntryZ + 2.2, s / 2);
            [-this.portalHalfW - 0.35, this.portalHalfW + 0.35].forEach((cx) => {
                const col = new THREE.Mesh(promColGeo, this.mats.goldMatcapBrushed);
                col.position.set(cx, 2.75, sz);
                this.scene.add(col);
            });
            const arch = new THREE.Mesh(promArchGeo, this.mats.goldMatcapBright);
            arch.position.set(0, 5.5, sz);
            this.scene.add(arch);
        }

        // 3. Dezente goldene Lichtschranke am Ehren-Durchgang fuer nicht eingeloggte Gaeste
        if (!this.isAuthenticatedCustomer) {
            this.portalBarrierGroup = new THREE.Group();
            this.portalBarrierGroup.position.set(0, 0, this.hallNorthZ - 0.28);

            const veilMat = this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xe6c687,
                transparent: true,
                opacity: 0.12,
                side: THREE.DoubleSide,
                depthWrite: false,
            }));
            const veilPlane = new THREE.Mesh(
                this._trackDisposable(new THREE.PlaneGeometry(this.portalHalfW * 2 - 0.4, 5.2)),
                veilMat
            );
            veilPlane.position.y = 2.62;
            this.portalBarrierGroup.add(veilPlane);

            const cordGeo = this._trackDisposable(new THREE.BoxGeometry(this.portalHalfW * 2 - 0.3, 0.032, 0.032));
            [0.85, 1.45, 2.05, 2.65].forEach((cy) => {
                const cord = new THREE.Mesh(cordGeo, this.mats.goldMatcapBright);
                cord.position.y = cy;
                this.portalBarrierGroup.add(cord);
            });

            const hitProxy = new THREE.Mesh(
                this._trackDisposable(new THREE.BoxGeometry(this.portalHalfW * 2, 5.4, 0.8)),
                this._trackDisposable(new THREE.MeshBasicMaterial({ visible: false }))
            );
            hitProxy.position.y = 2.7;
            hitProxy.userData = { isPortalGate: true };
            this.portalBarrierGroup.add(hitProxy);
            this.portalHitMeshes.push(hitProxy);

            this.scene.add(this.portalBarrierGroup);
        }
    }

    /**
     * Bereich 2 (Nord-Rotunde, zone = 'heroes'):
     * 1:1 EXAKTE Uebernahme & goldene Veredelung der Pantheon "Halle der Helden" aus funkenwerk-heroes-3d.js!
     */
    _buildHallOfHeroes() {
        const THREE = this.THREE;
        this.pantheonGroup = new THREE.Group();
        this.pantheonGroup.position.set(0, 0, this.pantheonCenterZ);
        this.scene.add(this.pantheonGroup);

        const floorTopY = 0.02;

        // 1. Abgestuftes Pantheon-Stylobat (Saphir #133B5C, Alabaster #E2DFD7 & 24K-Gold-Rahmen)
        const steps = [
            { w: 53.0, d: 55.0, h: 0.90, y: -0.62, trimY: -0.22, mat: this.mats.obsidianMatcap, trimMat: this.mats.goldMatcapBright },
            { w: 50.0, d: 52.0, h: 0.42, y: -0.24, trimY: -0.06, mat: this.mats.sapphireTrim, trimMat: this.mats.goldMatcapBright },
            { w: 47.6, d: 49.6, h: 0.24, y: -0.11, trimY: 0.00, mat: this.mats.alabasterStep, trimMat: this.mats.sapphireTrim },
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

        // 2. Carrara-Alabaster-Marmorboden (#ECEAE4 / #E2DFD7) mit Saphir-Einfassung (#133B5C) & 24K-Gold-Intarsien
        const marbleTex = buildHallOfHeroesMarbleTexture(THREE, this.maxAniso, this.disposables);
        const marbleFloor = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(46.8, 48.8)),
            this._trackDisposable(new THREE.MeshBasicMaterial({
                map: marbleTex,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -2,
            }))
        );
        marbleFloor.rotation.x = -Math.PI * 0.5;
        marbleFloor.position.set(0, floorTopY, 0);
        marbleFloor.userData = { isFloor: true };
        this.pantheonGroup.add(marbleFloor);
        this.walkableFloorMeshes.push(marbleFloor);

        // Physische 24K-Gold-Intarsienringe auf dem Pantheon-Marmorboden
        [3.8, 6.9, 13.8, 19.6].forEach((rad, rIdx) => {
            const gRing = new THREE.Mesh(
                this._trackDisposable(new THREE.RingGeometry(rad, rad + (rIdx === 2 ? 0.16 : 0.11), 64)),
                this.mats.goldMatcapBright
            );
            gRing.rotation.x = -Math.PI * 0.5;
            gRing.position.set(0, floorTopY + 0.008, -2.2);
            this.pantheonGroup.add(gRing);
        });

        // 3. 28 Monumentale 24K-Gold-Kolonnaden-Saeulen via InstancedMesh (18.4m freie Ehrenachse!)
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

        const colHeight = 12.6;
        const shaftInst = new THREE.InstancedMesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.68, 0.80, colHeight, 16)),
            this.mats.goldMatcapBrushed,
            colCoords.length
        );
        const capInst = new THREE.InstancedMesh(
            this._trackDisposable(new THREE.BoxGeometry(2.05, 0.82, 2.05)),
            this.mats.goldMatcapBright,
            colCoords.length
        );
        const baseInst = new THREE.InstancedMesh(
            this._trackDisposable(new THREE.CylinderGeometry(0.94, 1.04, 0.44, 16)),
            this.mats.goldMatcapBright,
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

            dummy.position.set(cx, floorTopY + 0.012, cz);
            dummy.scale.set(2.9, 1, 2.9);
            dummy.updateMatrix();
            colAoInst.setMatrixAt(cIdx, dummy.matrix);
        }
        shaftInst.instanceMatrix.needsUpdate = true;
        capInst.instanceMatrix.needsUpdate = true;
        baseInst.instanceMatrix.needsUpdate = true;
        colAoInst.instanceMatrix.needsUpdate = true;
        this.pantheonGroup.add(shaftInst, capInst, baseInst, colAoInst);

        // 4. Architrav & 100 % unverdeckte goldene Inschrift "HALLE DER HELDEN"
        const entY = floorTopY + colHeight + 1.45;
        const sideBeamGeo = this._trackDisposable(new THREE.BoxGeometry(2.4, 1.9, 45.0));
        const sideGoldGeo = this._trackDisposable(new THREE.BoxGeometry(2.68, 0.38, 45.4));
        [-21.5, 21.5].forEach((sx) => {
            const b = new THREE.Mesh(sideBeamGeo, this.mats.obsidianMatcap);
            b.position.set(sx, entY, 0);
            this.pantheonGroup.add(b);

            const g = new THREE.Mesh(sideGoldGeo, this.mats.goldMatcapBright);
            g.position.set(sx, entY + 1.15, 0);
            this.pantheonGroup.add(g);
        });

        const crossBeamGeo = this._trackDisposable(new THREE.BoxGeometry(45.6, 2.1, 2.5));
        const crossGoldGeo = this._trackDisposable(new THREE.BoxGeometry(46.2, 0.42, 2.84));
        [-21.5, 21.5].forEach((cz) => {
            const cb = new THREE.Mesh(crossBeamGeo, this.mats.obsidianMatcap);
            cb.position.set(0, entY, cz);
            this.pantheonGroup.add(cb);

            const cg = new THREE.Mesh(crossGoldGeo, this.mats.goldMatcapBright);
            cg.position.set(0, entY + 1.25, cz);
            this.pantheonGroup.add(cg);
        });

        const friezeTex = buildHallBannerPlaqueTexture(THREE, this.maxAniso, 'HALLE DER HELDEN', this.disposables);
        const friezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: friezeTex,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -4,
        }));

        // Sued-Portikus Fries (+Z, Richtung Ehrenpromenade & Produktgalerie)
        const outerFriezeBack = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(24.8, 3.85, 0.9)),
            this.mats.goldMatcapBright
        );
        outerFriezeBack.position.set(0, entY + 2.35, 21.65);
        this.pantheonGroup.add(outerFriezeBack);

        const outerFrieze = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(24.0, 3.45)),
            friezeMat
        );
        outerFrieze.position.set(0, entY + 2.35, 22.15);
        this.pantheonGroup.add(outerFrieze);

        // Innerer Nord-Triumphfries (-Z, direkt ueber den Top-5-Heldenpodesten!)
        const innerFriezeBack = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(22.8, 3.55, 0.82)),
            this.mats.goldMatcapBright
        );
        innerFriezeBack.position.set(0, entY + 2.15, -21.5);
        this.pantheonGroup.add(innerFriezeBack);

        const innerFrieze = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(22.0, 3.18)),
            friezeMat
        );
        innerFrieze.position.set(0, entY + 2.15, -21.04);
        this.pantheonGroup.add(innerFrieze);

        // 5. Goldene Kuppel sauber oberhalb des Frieses (domeBaseY = entY + 5.35)
        //    + versetzte 24K-Gold-Kuppelrippen ((r + 0.5) / 6) * Math.PI + strahlender 24K-Gold-Oculus-Stern!
        const domeBaseY = entY + 5.35;
        const domeRadius = 17.5;
        const oculusRadius = 5.2;
        const ribArc = Math.acos(oculusRadius / domeRadius);

        const drumCorners = [[-14.8, -14.8], [14.8, -14.8], [-14.8, 14.8], [14.8, 14.8]];
        const drumPostGeo = this._trackDisposable(new THREE.CylinderGeometry(0.62, 0.74, 4.6, 14));
        for (let dci = 0; dci < drumCorners.length; dci++) {
            const [dx, dz] = drumCorners[dci];
            const drumPost = new THREE.Mesh(drumPostGeo, this.mats.goldMatcapBright);
            drumPost.position.set(dx, entY + 2.95, dz);
            this.pantheonGroup.add(drumPost);
        }

        // Warme Champagner-Gold-Lichtschale hinter den goldenen Kuppelrippen (heller Seelenfunke-Stil, 0 lila Glow!)
        const canopyShellMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            color: 0xfff3cf,
            transparent: true,
            opacity: 0.14,
            side: THREE.BackSide,
            depthWrite: false,
            fog: false,
        }));
        const canopyShell = new THREE.Mesh(
            this._trackDisposable(new THREE.SphereGeometry(domeRadius + 0.25, 32, 18, 0, TWO_PI, 0, Math.PI * 0.48)),
            canopyShellMat
        );
        canopyShell.position.set(0, domeBaseY, 0);
        this.pantheonGroup.add(canopyShell);

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

        const ribGeo = this._trackDisposable(new THREE.TorusGeometry(domeRadius, 0.26, 8, 34, ribArc));
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

        // Sanfter Champagner-Sonnenlichtkegel aus dem Oculus in das Zentrum der Halle
        const sunbeamCone = new THREE.Mesh(
            this._trackDisposable(new THREE.ConeGeometry(10.5, oculusY - 1.0, 28, 1, true)),
            this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xfff5d6,
                transparent: true,
                opacity: 0.042,
                side: THREE.DoubleSide,
                depthWrite: false,
            }))
        );
        sunbeamCone.position.set(0, (oculusY + 1.0) * 0.5, -2.5);
        this.pantheonGroup.add(sunbeamCone);

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

    _ensureFunkiFallbackMaterials() {
        if (this._funkiFallbackMats) {
            return this._funkiFallbackMats;
        }
        const THREE = this.THREE;
        this._funkiFallbackMats = {
            burlap: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#7C6549', '#B59B78', '#5C4932', 10, 0.16, this.disposables),
            })),
            bronzeLeather: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#8B4F22', '#E5A96C', '#C5A059', 16, 0.24, this.disposables),
            })),
            goldAntique: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#9A742C', '#FDE68A', '#D4AF37', 22, 0.30, this.disposables),
            })),
            silverSteel: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#CBD5E1', '#FFFFFF', '#94A3B8', 32, 0.34, this.disposables),
            })),
            platinum: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#E2E8F0', '#FFFFFF', '#FDE68A', 36, 0.44, this.disposables),
            })),
            sapphireCrystal: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#134E7A', '#E0F2FE', '#7DD3FC', 38, 0.52, this.disposables),
            })),
            scarf: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#7A1E24', '#D96B70', '#C5A059', 12, 0.20, this.disposables),
            })),
        };
        return this._funkiFallbackMats;
    }

    _resolveFunkiModelUrl(lvl, hero) {
        if (hero && !hero.is_placeholder && !hero.is_preview && hero.model_url) {
            return String(hero.model_url);
        }
        if (this.heroesPayload && this.heroesPayload.level_models && this.heroesPayload.level_models[lvl]) {
            return String(this.heroesPayload.level_models[lvl]);
        }
        return DEFAULT_FUNKI_GLB_URLS[lvl] || DEFAULT_FUNKI_GLB_URLS[1];
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

        const url = modelUrl || this._resolveFunkiModelUrl(lvl, null);
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
                const sceneRoot = parsed.scene;
                sceneRoot.updateMatrixWorld(true);
                const bbox = new THREE.Box3().setFromObject(sceneRoot);
                const rawH = Math.max(0.001, bbox.max.y - bbox.min.y);
                const centerX = (bbox.min.x + bbox.max.x) * 0.5;
                const centerZ = (bbox.min.z + bbox.max.z) * 0.5;
                const minY = bbox.min.y;

                const targetH = (lvl === 1) ? 1.56 : 1.98;
                const normScale = targetH / rawH;
                sceneRoot.scale.setScalar(normScale);
                sceneRoot.position.set(-centerX * normScale, -minY * normScale, -centerZ * normScale);

                const frontOriented = new THREE.Group();
                frontOriented.rotation.y = FUNKI_GLB_FRONT_ROTATION_Y;
                frontOriented.add(sceneRoot);

                const templateGroup = new THREE.Group();
                templateGroup.add(frontOriented);
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
     * Sofortiger prozeduraler 3D-Funki-Fallback (sichtbar ab Frame 1 bis das komprimierte .glb geladen ist).
     */
    _buildProceduralFallbackFunki(rawLevel) {
        const THREE = this.THREE;
        const lvl = clamp(parseInt(rawLevel, 10) || 1, 1, 10);
        const fbMats = this._ensureFunkiFallbackMaterials();
        const root = new THREE.Group();

        let armorMat = this.mats.goldMatcapBright;
        let trimMat = this.mats.goldMatcapBrushed;
        if (lvl === 1) {
            armorMat = fbMats.burlap;
            trimMat = fbMats.bronzeLeather;
        } else if (lvl === 2) {
            armorMat = fbMats.bronzeLeather;
            trimMat = fbMats.goldAntique;
        } else if (lvl === 3) {
            armorMat = fbMats.goldAntique;
            trimMat = this.mats.goldMatcapBright;
        } else if (lvl === 4) {
            armorMat = fbMats.silverSteel;
            trimMat = this.mats.obsidianMatcap;
        } else if (lvl === 5) {
            armorMat = fbMats.platinum;
            trimMat = this.mats.goldMatcapBright;
        } else if (lvl === 6) {
            armorMat = this.mats.obsidianMatcap;
            trimMat = fbMats.silverSteel;
        } else if (lvl === 7) {
            armorMat = fbMats.goldAntique;
            trimMat = this.mats.goldMatcapBright;
        } else if (lvl === 9) {
            armorMat = fbMats.platinum;
            trimMat = fbMats.sapphireCrystal;
        }

        const isSitting = (lvl === 1);
        if (isSitting) {
            [-0.18, 0.18].forEach((slx) => {
                const sitLeg = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.09, 0.42, 12), armorMat);
                sitLeg.rotation.x = Math.PI * 0.46;
                sitLeg.position.set(slx, 0.16, 0.24);
                this.heroDynamicDisposables.push(sitLeg.geometry);
                root.add(sitLeg);

                const sitBoot = new THREE.Mesh(new THREE.BoxGeometry(0.20, 0.16, 0.24), trimMat);
                sitBoot.position.set(slx, 0.15, 0.46);
                this.heroDynamicDisposables.push(sitBoot.geometry);
                root.add(sitBoot);
            });
        } else {
            [-0.20, 0.20].forEach((lx) => {
                const boot = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.20, 0.30), trimMat);
                boot.position.set(lx, 0.10, 0.04);
                this.heroDynamicDisposables.push(boot.geometry);
                root.add(boot);

                const shin = new THREE.Mesh(new THREE.CylinderGeometry(0.115, 0.10, 0.46, 12), armorMat);
                shin.position.set(lx, 0.38, 0);
                this.heroDynamicDisposables.push(shin.geometry);
                root.add(shin);
            });
        }

        const upperGroup = new THREE.Group();
        upperGroup.position.set(0, isSitting ? 0.54 : 0.88, 0);
        if (isSitting) upperGroup.rotation.x = 0.08;
        root.add(upperGroup);

        const torsoGeo = new THREE.BoxGeometry(0.64, 0.60, 0.40);
        this.heroDynamicDisposables.push(torsoGeo);
        upperGroup.add(new THREE.Mesh(torsoGeo, armorMat));

        const beltGeo = new THREE.BoxGeometry(0.68, 0.10, 0.44);
        this.heroDynamicDisposables.push(beltGeo);
        const belt = new THREE.Mesh(beltGeo, trimMat);
        belt.position.set(0, -0.23, 0);
        upperGroup.add(belt);

        if (lvl >= 2) {
            const capeGeo = new THREE.BoxGeometry(0.58, 0.68, 0.05);
            this.heroDynamicDisposables.push(capeGeo);
            const cape = new THREE.Mesh(capeGeo, lvl === 2 ? fbMats.scarf : trimMat);
            cape.position.set(0, -0.08, -0.22);
            cape.rotation.x = 0.14;
            upperGroup.add(cape);
        }

        [-1, 1].forEach((ax) => {
            const armGeo = new THREE.CylinderGeometry(0.085, 0.075, 0.42, 12);
            const handGeo = new THREE.SphereGeometry(0.088, 10, 10);
            this.heroDynamicDisposables.push(armGeo, handGeo);

            const armGrp = new THREE.Group();
            armGrp.position.set(ax * 0.41, 0.16, 0);
            armGrp.rotation.z = ax * -0.16;
            const arm = new THREE.Mesh(armGeo, armorMat);
            arm.position.y = -0.18;
            const hand = new THREE.Mesh(handGeo, trimMat);
            hand.position.y = -0.40;
            armGrp.add(arm, hand);
            upperGroup.add(armGrp);
        });

        const headGroup = new THREE.Group();
        headGroup.position.set(0, 0.64, 0.02);
        upperGroup.add(headGroup);

        const tvGeo = new THREE.BoxGeometry(0.72, 0.54, 0.46);
        const bezelGeo = new THREE.BoxGeometry(0.64, 0.44, 0.04);
        const screenGeo = new THREE.PlaneGeometry(0.56, 0.37);
        this.heroDynamicDisposables.push(tvGeo, bezelGeo, screenGeo);

        headGroup.add(new THREE.Mesh(tvGeo, armorMat));
        const bezel = new THREE.Mesh(bezelGeo, trimMat);
        bezel.position.set(0, 0, 0.225);
        headGroup.add(bezel);

        const faceTex = buildFunkiScreenFaceTexture(THREE, this.maxAniso, lvl, this.heroDynamicDisposables);
        const faceMat = new THREE.MeshBasicMaterial({ map: faceTex });
        this.heroDynamicDisposables.push(faceMat);
        const screen = new THREE.Mesh(screenGeo, faceMat);
        screen.position.set(0, 0, 0.248);
        headGroup.add(screen);

        if (lvl >= 6) {
            const hornGeo = new THREE.ConeGeometry(0.075, 0.38, 8);
            this.heroDynamicDisposables.push(hornGeo);
            [-1, 1].forEach((hx) => {
                const horn = new THREE.Mesh(hornGeo, this.mats.goldMatcapBright);
                horn.position.set(hx * 0.33, 0.36, 0);
                horn.rotation.z = hx * -0.42;
                headGroup.add(horn);
            });
        }
        if (lvl === 10) {
            const crownGeo = new THREE.ConeGeometry(0.10, 0.44, 6);
            this.heroDynamicDisposables.push(crownGeo);
            const crown = new THREE.Mesh(crownGeo, this.mats.beaconGold);
            crown.position.set(0, 0.52, 0.06);
            headGroup.add(crown);
        }

        return root;
    }

    /**
     * Erzeugt einen Top-5-Funki auf seinem Ehrenpodest mit sofortigem Fallback, komprimiertem .glb-Upgrade
     * und den individuellen Level-1..10-Ringen/Koronen fuer Baustein B (echtes 24K-Gold, 0 lila KI-Glow!).
     */
    _createFunkiCharacterByLevel(rawLevel, slotNum, scaleFactor, hero) {
        const THREE = this.THREE;
        const lvl = clamp(parseInt(rawLevel, 10) || 1, 1, 10);
        const mountGen = this._monumentGeneration;

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

        // Sofortiger prozeduraler 3D-Funki-Fallback bis das komprimierte .glb geladen ist!
        const fallbackGroup = this._buildProceduralFallbackFunki(lvl);
        motionRig.add(fallbackGroup);

        const animRecord = {
            motionRig,
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

        const modelUrl = this._resolveFunkiModelUrl(lvl, hero || null);
        this._loadFunkiGlbTemplate(lvl, modelUrl)
            .then((templateGroup) => {
                if (this.destroyed || mountGen !== this._monumentGeneration || !this.THREE) return;
                if (fallbackGroup.parent === motionRig) {
                    motionRig.remove(fallbackGroup);
                }
                const inst = templateGroup.clone(true);
                motionRig.add(inst);
            })
            .catch(() => {
                // Prozeduraler 3D-Funki-Fallback bleibt sauber aktiv
            });

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
            if (previewLevelsBySlot) {
                const pLvl = previewLevelsBySlot[sp.slot];
                effectiveHero = {
                    slot: sp.slot,
                    is_placeholder: false,
                    is_preview: true,
                    is_me: false,
                    display_name: `Funki Stufe ${pLvl}`,
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

            const coreGeo = new THREE.CylinderGeometry(sp.w * 0.52, sp.w * 0.60, sp.h, 8);
            const capGeo = new THREE.CylinderGeometry(sp.w * 0.57, sp.w * 0.55, 0.16, 8);
            const baseGeo = new THREE.CylinderGeometry(sp.w * 0.63, sp.w * 0.66, 0.16, 8);
            const aoGeo = new THREE.PlaneGeometry(sp.w * 1.75, sp.w * 1.75);
            this.heroDynamicDisposables.push(coreGeo, capGeo, baseGeo, aoGeo);

            const pedCore = new THREE.Mesh(coreGeo, this.mats.obsidianMatcap);
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
            pedAoMesh.position.y = 0.015;
            pGrp.add(pedAoMesh);

            const funkiChar = this._createFunkiCharacterByLevel(displayLevel, sp.slot, sp.scale, effectiveHero);
            funkiChar.position.set(0, sp.h + 0.13, 0);
            pGrp.add(funkiChar);

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

            const steleObsInst = new THREE.InstancedMesh(steleObsGeo, this.mats.obsidianMatcap, steleCount);
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
            if (this.portalBarrierGroup) {
                this.portalBarrierGroup.visible = false;
            }
        }
        if (this.pantheonDynamicGroup) {
            this._populateHeroesMonuments();
        }
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
        const lightConeGeo = this._trackDisposable(new THREE.ConeGeometry(0.72, 3.4, 20, 1, true));
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
        const monolithInst = new THREE.InstancedMesh(monolithGeo, this.mats.obsidianWall, count);
        const goldCollarInst = new THREE.InstancedMesh(goldCollarGeo, this.mats.goldChampagne, count);
        const velvetPadInst = new THREE.InstancedMesh(velvetTopGeo, this.mats.velvetPad, count);
        const vitrineHoodInst = new THREE.InstancedMesh(vitrineBoxGeo, this.mats.vitrineGlass, count);
        vitrineHoodInst.renderOrder = 4;
        const vitrineCapInst = new THREE.InstancedMesh(vitrineTopCapGeo, this.mats.goldBrushed, count);
        const cornerPostInst = new THREE.InstancedMesh(cornerPostGeo, this.mats.goldBrushed, count * 4);
        const lightConeInst = new THREE.InstancedMesh(lightConeGeo, this.mats.spotlightCone, count);
        lightConeInst.renderOrder = 3;
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
            setLocalInstance(lightConeInst, i, 0, 3.55, 0);
            setLocalInstance(plaqueBackingInst, i, 0, 0.84, 0.48, -0.26, 0, 0);

            for (let p = 0; p < 4; p++) {
                setLocalInstance(cornerPostInst, i * 4 + p, postOffsets[p][0], 1.62, postOffsets[p][1]);
            }

            const steleRoot = new THREE.Group();
            steleRoot.position.set(sx, 0, sz);
            steleRoot.rotation.y = facingYaw;
            this.scene.add(steleRoot);

            const plaqueTex = buildStelePlaqueTexture(THREE, this.maxAniso, product, this.disposables);
            const plaqueMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: plaqueTex }));
            const pFace = new THREE.Mesh(plaqueFaceGeo, plaqueMat);
            pFace.position.set(0, 0.84 + Math.sin(0.26) * 0.019, 0.48 + Math.cos(0.26) * 0.019);
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

        this.scene.add(
            aoInst,
            plinthBaseInst,
            monolithInst,
            goldCollarInst,
            velvetPadInst,
            vitrineHoodInst,
            vitrineCapInst,
            cornerPostInst,
            lightConeInst,
            plaqueBackingInst
        );

        this.focusHaloMesh = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.49, 0.54, 32)),
            this.mats.goldChampagne
        );
        this.focusHaloMesh.rotation.x = -Math.PI * 0.5;
        this.focusHaloMesh.visible = false;
        this.scene.add(this.focusHaloMesh);
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

        if (nextStele.product.glb_url) {
            this._populateExhibitGlb(nextStele).finally(done);
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
     * Geteilter Geometrie- & MatCap-Pool fuer lokalen Avatar & bis zu 19 Mitspieler pro Instanz (20er Hard-Cap).
     */
    _ensureAvatarSharedPool() {
        if (this._avatarShared) {
            return this._avatarShared;
        }
        const THREE = this.THREE;

        const mats = {
            suitObsidian: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#121620', '#5E6882', '#D8B257', 28, 0.50, this.disposables),
            })),
            suitDark: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#0B0D14', '#40485C', '#C5A059', 24, 0.36, this.disposables),
            })),
            gold24k: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#C5A059', '#FFFFFF', '#FFF3CC', 34, 0.62, this.disposables),
            })),
            goldBright: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#E8CA82', '#FFFFFF', '#FFFBE8', 38, 0.66, this.disposables),
            })),
            jointDark: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#0E1118', '#4A5064', '#9B7E45', 16, 0.25, this.disposables),
            })),
            sapphireCrystal: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#1D8AC8', '#FFFFFF', '#A5F3FC', 36, 0.64, this.disposables),
            })),
            emeraldCrystal: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#0D9468', '#FFFFFF', '#A7F3D0', 36, 0.64, this.disposables),
            })),
            cosmosGold: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#F59E0B', '#FFFFFF', '#FEF3C7', 42, 0.72, this.disposables),
            })),
        };

        const torsoProfile = [
            new THREE.Vector2(0.00, 0.00),
            new THREE.Vector2(0.155, 0.00),
            new THREE.Vector2(0.175, 0.14),
            new THREE.Vector2(0.205, 0.30),
            new THREE.Vector2(0.192, 0.40),
            new THREE.Vector2(0.135, 0.43),
            new THREE.Vector2(0.00, 0.43),
        ];

        const geos = {
            footShadow: this._trackDisposable(new THREE.PlaneGeometry(1.12, 1.12)),
            baseRing: this._trackDisposable(new THREE.RingGeometry(0.34, 0.385, 32)),
            pelvis: this._trackDisposable(new THREE.CylinderGeometry(0.155, 0.145, 0.14, 20)),
            beltRing: this._trackDisposable(new THREE.TorusGeometry(0.162, 0.018, 10, 26)),
            buckleGem: this._trackDisposable(new THREE.OctahedronGeometry(0.022, 0)),
            hipBall: this._trackDisposable(new THREE.SphereGeometry(0.064, 14, 10)),
            thigh: this._trackDisposable(new THREE.CylinderGeometry(0.068, 0.056, 0.34, 18)),
            thighStripe: this._trackDisposable(new THREE.BoxGeometry(0.012, 0.30, 0.018)),
            kneeBall: this._trackDisposable(new THREE.SphereGeometry(0.055, 14, 10)),
            kneeRing: this._trackDisposable(new THREE.TorusGeometry(0.054, 0.007, 8, 20)),
            shin: this._trackDisposable(new THREE.CylinderGeometry(0.056, 0.048, 0.34, 18)),
            ankleBall: this._trackDisposable(new THREE.SphereGeometry(0.046, 12, 10)),
            bootCuff: this._trackDisposable(new THREE.TorusGeometry(0.052, 0.010, 8, 20)),
            bootUpper: this._trackDisposable(new THREE.BoxGeometry(0.105, 0.052, 0.175)),
            bootToe: this._trackDisposable(new THREE.SphereGeometry(0.052, 14, 10)),
            bootSole: this._trackDisposable(new THREE.BoxGeometry(0.114, 0.016, 0.215)),
            chest: this._trackDisposable(new THREE.LatheGeometry(torsoProfile, 24)),
            lapel: this._trackDisposable(new THREE.BoxGeometry(0.032, 0.18, 0.016)),
            collarRing: this._trackDisposable(new THREE.TorusGeometry(0.125, 0.018, 10, 24)),
            badgePlane: this._trackDisposable(new THREE.PlaneGeometry(0.19, 0.095)),
            neckCyl: this._trackDisposable(new THREE.CylinderGeometry(0.058, 0.068, 0.075, 16)),
            headShell: this._trackDisposable(new THREE.BoxGeometry(0.33, 0.24, 0.25)),
            headFrame: this._trackDisposable(new THREE.BoxGeometry(0.346, 0.256, 0.028)),
            visorScreen: this._trackDisposable(new THREE.PlaneGeometry(0.305, 0.215)),
            earCap: this._trackDisposable(new THREE.CylinderGeometry(0.038, 0.038, 0.026, 14)),
            crestSmall: this._trackDisposable(new THREE.OctahedronGeometry(0.034, 0)),
            crestMaster: this._trackDisposable(new THREE.OctahedronGeometry(0.048, 0)),
            crownHalo: this._trackDisposable(new THREE.TorusGeometry(0.11, 0.010, 8, 24)),
            shoulderCap: this._trackDisposable(new THREE.SphereGeometry(0.068, 14, 10)),
            shoulderTrim: this._trackDisposable(new THREE.TorusGeometry(0.066, 0.009, 8, 20)),
            upperArm: this._trackDisposable(new THREE.CylinderGeometry(0.052, 0.045, 0.24, 16)),
            elbowBall: this._trackDisposable(new THREE.SphereGeometry(0.046, 12, 10)),
            forearm: this._trackDisposable(new THREE.CylinderGeometry(0.045, 0.038, 0.22, 16)),
            cuffRing: this._trackDisposable(new THREE.TorusGeometry(0.041, 0.008, 8, 18)),
            palm: this._trackDisposable(new THREE.BoxGeometry(0.036, 0.064, 0.062)),
            dorsalPlate: this._trackDisposable(new THREE.BoxGeometry(0.040, 0.046, 0.046)),
            thumb: this._trackDisposable(new THREE.CylinderGeometry(0.010, 0.012, 0.042, 8)),
            finger: this._trackDisposable(new THREE.CylinderGeometry(0.0085, 0.010, 0.048, 8)),
            weaponHilt: this._trackDisposable(new THREE.CylinderGeometry(0.013, 0.015, 0.16, 10)),
            weaponGuard: this._trackDisposable(new THREE.BoxGeometry(0.13, 0.022, 0.032)),
            weaponBlade: this._trackDisposable(new THREE.ConeGeometry(0.028, 0.56, 4)),
            weaponStaffShaft: this._trackDisposable(new THREE.CylinderGeometry(0.012, 0.014, 0.72, 10)),
            weaponStaffOrb: this._trackDisposable(new THREE.OctahedronGeometry(0.055, 0)),
            weaponBowArc: this._trackDisposable(new THREE.TorusGeometry(0.24, 0.013, 8, 20, Math.PI * 0.92)),
            weaponChord: this._trackDisposable(new THREE.CylinderGeometry(0.003, 0.003, 0.46, 6)),
            slashArc: this._trackDisposable(new THREE.RingGeometry(0.58, 0.92, 24, 1, -Math.PI * 0.42, Math.PI * 0.84)),
            sparkDiamond: this._trackDisposable(new THREE.OctahedronGeometry(0.036, 0)),
        };

        const badgeMatByLevel = new Map();
        const getBadgeMat = (lvl) => {
            const safeLvl = clamp(parseInt(lvl, 10) || 2, 1, 10);
            if (!badgeMatByLevel.has(safeLvl)) {
                const tex = buildFunkiChestBadgeTexture(THREE, safeLvl, this.disposables);
                const mat = this._trackDisposable(new THREE.MeshBasicMaterial({
                    map: tex,
                    transparent: true,
                    depthWrite: false,
                }));
                badgeMatByLevel.set(safeLvl, mat);
            }
            return badgeMatByLevel.get(safeLvl);
        };

        const visorTexOpen = buildFunkiVisorTexture(THREE, false, this.disposables);
        const visorTexBlink = buildFunkiVisorTexture(THREE, true, this.disposables);
        const peerVisorMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: visorTexOpen }));

        this._avatarShared = {
            mats,
            geos,
            getBadgeMat,
            visorTexOpen,
            visorTexBlink,
            peerVisorMat,
        };
        return this._avatarShared;
    }

    _buildWeaponAttachment(weaponInfo) {
        if (!weaponInfo || typeof weaponInfo !== 'object') return null;
        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const { mats, geos } = shared;
        const effectType = String(weaponInfo.effect_type || 'gold_blade');

        const wGroup = new THREE.Group();
        wGroup.position.set(0.004, -0.048, 0.026);
        wGroup.rotation.x = Math.PI * 0.42;

        if (effectType === 'sapphire_staff') {
            const shaft = new THREE.Mesh(geos.weaponStaffShaft, mats.gold24k);
            shaft.position.y = 0.18;
            const orb = new THREE.Mesh(geos.weaponStaffOrb, mats.sapphireCrystal);
            orb.position.y = 0.56;
            const halo = new THREE.Mesh(geos.crownHalo, mats.goldBright);
            halo.scale.setScalar(0.68);
            halo.position.y = 0.56;
            wGroup.add(shaft, orb, halo);
        } else if (effectType === 'emerald_bow') {
            const bow = new THREE.Mesh(geos.weaponBowArc, mats.gold24k);
            bow.rotation.z = -Math.PI * 0.46;
            bow.rotation.y = Math.PI * 0.5;
            const chord = new THREE.Mesh(geos.weaponChord, mats.emeraldCrystal);
            chord.position.set(0, 0, -0.03);
            const gem = new THREE.Mesh(geos.buckleGem, mats.emeraldCrystal);
            gem.scale.setScalar(1.4);
            gem.position.set(0, 0, 0.21);
            wGroup.add(bow, chord, gem);
        } else {
            const bladeMat = effectType === 'cosmos_blade' ? mats.cosmosGold : mats.goldBright;
            const hilt = new THREE.Mesh(geos.weaponHilt, mats.suitDark);
            const guard = new THREE.Mesh(geos.weaponGuard, mats.gold24k);
            guard.position.y = 0.08;
            const blade = new THREE.Mesh(geos.weaponBlade, bladeMat);
            blade.scale.set(1.0, 1.0, 0.34);
            blade.position.y = 0.37;
            wGroup.add(hilt, guard, blade);

            if (effectType === 'cosmos_blade') {
                const crest = new THREE.Mesh(geos.crestSmall, mats.cosmosGold);
                crest.position.y = 0.12;
                wGroup.add(crest);
            }
        }

        return wGroup;
    }

    _createArticulatedAvatarInstance(level, weaponInfo, useOwnBlinkingVisor = false) {
        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const { mats, geos } = shared;
        const lvl = clamp(parseInt(level, 10) || 2, 1, 10);

        const root = new THREE.Group();

        const footShadow = new THREE.Mesh(geos.footShadow, this.mats.contactShadow);
        footShadow.rotation.x = -Math.PI * 0.5;
        footShadow.position.y = 0.005;
        root.add(footShadow);

        const baseRing = new THREE.Mesh(geos.baseRing, this.mats.goldBrushed);
        baseRing.rotation.x = -Math.PI * 0.5;
        baseRing.position.y = 0.007;
        root.add(baseRing);

        const pelvisPivot = new THREE.Group();
        pelvisPivot.position.set(0, 0.76, 0);
        root.add(pelvisPivot);

        const pelvisMesh = new THREE.Mesh(geos.pelvis, mats.suitObsidian);
        pelvisMesh.scale.set(1.08, 1.0, 0.82);
        pelvisPivot.add(pelvisMesh);

        const beltRing = new THREE.Mesh(geos.beltRing, mats.gold24k);
        beltRing.rotation.x = Math.PI * 0.5;
        beltRing.scale.set(1.06, 0.82, 1.0);
        beltRing.position.y = 0.055;
        pelvisPivot.add(beltRing);

        const buckleGem = new THREE.Mesh(geos.buckleGem, mats.goldBright);
        buckleGem.scale.set(1.0, 1.15, 0.45);
        buckleGem.position.set(0, 0.055, 0.145);
        pelvisPivot.add(buckleGem);

        const buildArticulatedLeg = (sideSign) => {
            const hipPivot = new THREE.Group();
            hipPivot.position.set(sideSign * 0.112, -0.02, 0);
            pelvisPivot.add(hipPivot);

            const hipBall = new THREE.Mesh(geos.hipBall, mats.jointDark);
            const thigh = new THREE.Mesh(geos.thigh, mats.suitObsidian);
            thigh.position.y = -0.17;
            const stripe = new THREE.Mesh(geos.thighStripe, mats.gold24k);
            stripe.position.set(sideSign * 0.058, -0.17, 0);
            hipPivot.add(hipBall, thigh, stripe);

            const kneePivot = new THREE.Group();
            kneePivot.position.set(0, -0.34, 0);
            hipPivot.add(kneePivot);

            const kneeBall = new THREE.Mesh(geos.kneeBall, mats.suitDark);
            const kneeRing = new THREE.Mesh(geos.kneeRing, mats.gold24k);
            kneeRing.rotation.x = Math.PI * 0.5;
            const shin = new THREE.Mesh(geos.shin, mats.suitObsidian);
            shin.position.y = -0.17;
            kneePivot.add(kneeBall, kneeRing, shin);

            const anklePivot = new THREE.Group();
            anklePivot.position.set(0, -0.34, 0);
            kneePivot.add(anklePivot);

            const ankleBall = new THREE.Mesh(geos.ankleBall, mats.jointDark);
            const bootCuff = new THREE.Mesh(geos.bootCuff, mats.gold24k);
            bootCuff.rotation.x = Math.PI * 0.5;
            bootCuff.position.y = -0.012;

            const bootUpper = new THREE.Mesh(geos.bootUpper, mats.suitDark);
            bootUpper.position.set(0, -0.032, 0.025);

            const bootToe = new THREE.Mesh(geos.bootToe, mats.suitDark);
            bootToe.scale.set(0.98, 0.56, 1.15);
            bootToe.position.set(0, -0.036, 0.092);

            const bootSole = new THREE.Mesh(geos.bootSole, mats.gold24k);
            bootSole.position.set(0, -0.052, 0.036);

            anklePivot.add(ankleBall, bootCuff, bootUpper, bootToe, bootSole);
            return { hipPivot, kneePivot, anklePivot };
        };

        const legL = buildArticulatedLeg(1);
        const legR = buildArticulatedLeg(-1);

        const torsoPivot = new THREE.Group();
        torsoPivot.position.set(0, 0.05, 0);
        pelvisPivot.add(torsoPivot);

        const chestMesh = new THREE.Mesh(geos.chest, mats.suitObsidian);
        chestMesh.scale.set(1.08, 1.0, 0.80);
        torsoPivot.add(chestMesh);

        [-1, 1].forEach((sideSign) => {
            const lapel = new THREE.Mesh(geos.lapel, mats.gold24k);
            lapel.position.set(sideSign * 0.058, 0.31, 0.152);
            lapel.rotation.z = -sideSign * 0.32;
            lapel.rotation.x = -0.14;
            torsoPivot.add(lapel);
        });

        const collarRing = new THREE.Mesh(geos.collarRing, mats.gold24k);
        collarRing.rotation.x = Math.PI * 0.5;
        collarRing.position.y = 0.425;
        torsoPivot.add(collarRing);

        const badgePlane = new THREE.Mesh(geos.badgePlane, shared.getBadgeMat(lvl));
        badgePlane.position.set(0, 0.21, 0.165);
        torsoPivot.add(badgePlane);

        const neckPivot = new THREE.Group();
        neckPivot.position.set(0, 0.435, 0);
        torsoPivot.add(neckPivot);

        const neckCyl = new THREE.Mesh(geos.neckCyl, mats.jointDark);
        neckCyl.position.y = 0.032;
        neckPivot.add(neckCyl);

        const headPivot = new THREE.Group();
        headPivot.position.set(0, 0.068, 0);
        neckPivot.add(headPivot);

        const headShell = new THREE.Mesh(geos.headShell, mats.suitObsidian);
        headShell.position.y = 0.125;
        headPivot.add(headShell);

        const headFrame = new THREE.Mesh(geos.headFrame, mats.gold24k);
        headFrame.position.set(0, 0.125, 0.118);
        headPivot.add(headFrame);

        const visorMat = useOwnBlinkingVisor
            ? this._trackDisposable(new THREE.MeshBasicMaterial({ map: shared.visorTexOpen }))
            : shared.peerVisorMat;

        const visorScreen = new THREE.Mesh(geos.visorScreen, visorMat);
        visorScreen.position.set(0, 0.125, 0.134);
        headPivot.add(visorScreen);

        [-1, 1].forEach((sideSign) => {
            const earCap = new THREE.Mesh(geos.earCap, mats.gold24k);
            earCap.rotation.z = Math.PI * 0.5;
            earCap.position.set(sideSign * 0.175, 0.125, 0);
            headPivot.add(earCap);
        });

        const sparkCrest = new THREE.Mesh(
            lvl >= 8 ? geos.crestMaster : geos.crestSmall,
            mats.goldBright
        );
        sparkCrest.position.set(0, 0.285, 0.02);
        headPivot.add(sparkCrest);

        if (lvl >= 6) {
            const crownHalo = new THREE.Mesh(geos.crownHalo, mats.goldBright);
            crownHalo.rotation.x = Math.PI * 0.5;
            crownHalo.position.set(0, 0.27, 0);
            headPivot.add(crownHalo);
        }

        const buildArticulatedArm = (sideSign) => {
            const shoulderPivot = new THREE.Group();
            shoulderPivot.position.set(sideSign * 0.228, 0.375, 0);
            torsoPivot.add(shoulderPivot);

            const shoulderCap = new THREE.Mesh(geos.shoulderCap, lvl >= 4 ? mats.gold24k : mats.suitObsidian);
            const shoulderTrim = new THREE.Mesh(geos.shoulderTrim, mats.gold24k);
            shoulderTrim.rotation.y = Math.PI * 0.5;
            shoulderTrim.rotation.x = 0.35;

            const upperArm = new THREE.Mesh(geos.upperArm, mats.suitObsidian);
            upperArm.position.y = -0.12;
            shoulderPivot.add(shoulderCap, shoulderTrim, upperArm);

            const elbowPivot = new THREE.Group();
            elbowPivot.position.set(0, -0.24, 0);
            shoulderPivot.add(elbowPivot);

            const elbowBall = new THREE.Mesh(geos.elbowBall, mats.jointDark);
            const forearm = new THREE.Mesh(geos.forearm, mats.suitObsidian);
            forearm.position.y = -0.11;
            const cuffRing = new THREE.Mesh(geos.cuffRing, mats.gold24k);
            cuffRing.rotation.x = Math.PI * 0.5;
            cuffRing.position.y = -0.21;
            elbowPivot.add(elbowBall, forearm, cuffRing);

            const wristPivot = new THREE.Group();
            wristPivot.position.set(0, -0.22, 0);
            elbowPivot.add(wristPivot);

            const palm = new THREE.Mesh(geos.palm, mats.suitDark);
            palm.position.set(0, -0.034, 0.004);
            const dorsalPlate = new THREE.Mesh(geos.dorsalPlate, mats.gold24k);
            dorsalPlate.position.set(sideSign * 0.004, -0.032, 0.004);

            const thumb = new THREE.Mesh(geos.thumb, mats.goldBright);
            thumb.position.set(-sideSign * 0.012, -0.036, 0.036);
            thumb.rotation.x = -0.55;
            thumb.rotation.z = sideSign * 0.25;

            const fingerCurl = new THREE.Group();
            fingerCurl.position.set(0, -0.064, 0.004);
            const zOffsets = [0.020, 0.007, -0.006, -0.019];
            const lengths = [0.96, 1.04, 0.98, 0.84];
            for (let fi = 0; fi < 4; fi++) {
                const fMesh = new THREE.Mesh(geos.finger, mats.gold24k);
                fMesh.scale.y = lengths[fi];
                fMesh.position.set(-sideSign * 0.004, -0.022 * lengths[fi], zOffsets[fi]);
                fMesh.rotation.z = -sideSign * 0.16;
                fingerCurl.add(fMesh);
            }

            wristPivot.add(palm, dorsalPlate, thumb, fingerCurl);
            return { shoulderPivot, elbowPivot, wristPivot, fingerCurl };
        };

        const armL = buildArticulatedArm(1);
        const armR = buildArticulatedArm(-1);

        const weaponMesh = this._buildWeaponAttachment(weaponInfo);
        if (weaponMesh) {
            armR.wristPivot.add(weaponMesh);
        }

        return {
            root,
            visorMat,
            visorTexOpen: shared.visorTexOpen,
            visorTexBlink: shared.visorTexBlink,
            weaponMesh,
            joints: {
                footShadow,
                baseRing,
                pelvisPivot,
                torsoPivot,
                neckPivot,
                headPivot,
                sparkCrest,
                hipPivotL: legL.hipPivot,
                kneePivotL: legL.kneePivot,
                anklePivotL: legL.anklePivot,
                hipPivotR: legR.hipPivot,
                kneePivotR: legR.kneePivot,
                anklePivotR: legR.anklePivot,
                shoulderPivotL: armL.shoulderPivot,
                elbowPivotL: armL.elbowPivot,
                wristPivotL: armL.wristPivot,
                fingerCurlL: armL.fingerCurl,
                shoulderPivotR: armR.shoulderPivot,
                elbowPivotR: armR.elbowPivot,
                wristPivotR: armR.wristPivot,
                fingerCurlR: armR.fingerCurl,
            },
        };
    }

    _buildPlayerCharacter() {
        const lvl = clamp(parseInt(this.playerFunki.level, 10) || 2, 1, 10);
        const weaponInfo = (!this.playerFunki.is_guest && this.playerFunki.weapon) ? this.playerFunki.weapon : null;

        const built = this._createArticulatedAvatarInstance(lvl, weaponInfo, true);
        built.root.position.set(this.player.x, this.player.y, this.player.z);
        built.root.rotation.y = this.player.yaw;
        this.scene.add(built.root);

        this.player.root = built.root;
        this.player.joints = built.joints;
        this.player.visorMat = built.visorMat;
        this.player.visorTexOpen = built.visorTexOpen;
        this.player.visorTexBlink = built.visorTexBlink;
    }

    _updatePlayerKinematics(dt, stepDist, turnRate, nearestForGaze) {
        const j = this.player.joints;
        if (!this.player.root || !j) return;

        const floorOffset = this.player.z < this.hallNorthZ - 0.5 ? 0.02 : 0.0;
        const jumpH = Math.max(0, this.player.y || 0);
        const airBlend = clamp(jumpH / 0.26, 0, 1);
        const squash = this.player.landSquash || 0;

        this.player.root.position.set(this.player.x, floorOffset + jumpH, this.player.z);
        this.player.root.rotation.y = this.player.yaw;
        if (this.player.isSomersault) {
            this.player.root.rotation.x = -this.player.somersaultProgress * TWO_PI;
        } else {
            this.player.root.rotation.x = 0;
        }

        const strideFrequency = 4.48;
        if (stepDist > 0.0001 && airBlend < 0.65) {
            this.player.walkPhase = (this.player.walkPhase + stepDist * strideFrequency) % TWO_PI;
        }

        const instantSpeed = stepDist / Math.max(0.001, dt);
        const targetBlend = this.player.isMoving ? clamp(instantSpeed / 4.8, 0.25, 1.0) : 0.0;
        const blendFactor = 1.0 - Math.exp(-dt * 10.0);
        this.player.moveBlend = lerp(this.player.moveBlend, targetBlend, blendFactor);

        const mb = this.player.moveBlend * (1.0 - airBlend * 0.65);
        const idleWeight = (1.0 - mb) * (1.0 - airBlend);
        const phaseL = this.player.walkPhase;
        const phaseR = phaseL + Math.PI;

        const applyLegGait = (hipPivot, kneePivot, anklePivot, p, sideSign) => {
            const sinP = Math.sin(p);
            const swingForward = Math.max(0, Math.cos(p - 0.16));
            const stanceWeight = Math.max(0, -Math.cos(p));

            const hipPitch = -0.54 * sinP * mb - (sideSign > 0 ? 0.34 : 0.18) * airBlend - squash * 0.42;
            const kneeBend = Math.max(
                0,
                (Math.pow(swingForward, 1.32) * 0.72 + stanceWeight * 0.06) * mb
                    + (sideSign > 0 ? 0.62 : 0.44) * airBlend
                    + squash * 0.85
            );

            const heelStrike = Math.max(0, sinP - 0.45) * 0.22;
            const toePushOff = Math.max(0, -sinP - 0.35) * 0.28;
            const swingToeLift = -swingForward * 0.26;
            const anklePitch = (swingToeLift + heelStrike + toePushOff - stanceWeight * 0.04) * mb
                + 0.22 * airBlend
                - squash * 0.35;

            hipPivot.rotation.x = hipPitch;
            hipPivot.rotation.z = sideSign * (0.015 * idleWeight + 0.022 * mb + 0.03 * airBlend);
            kneePivot.rotation.x = kneeBend;
            anklePivot.rotation.x = anklePitch;
        };

        applyLegGait(j.hipPivotL, j.kneePivotL, j.anklePivotL, phaseL, 1);
        applyLegGait(j.hipPivotR, j.kneePivotR, j.anklePivotR, phaseR, -1);

        if (this.player.isSomersault) {
            const tuck = Math.sin(clamp(this.player.somersaultProgress, 0, 1) * Math.PI);
            j.kneePivotL.rotation.x += 1.35 * tuck;
            j.kneePivotR.rotation.x += 1.35 * tuck;
            j.hipPivotL.rotation.x -= 0.85 * tuck;
            j.hipPivotR.rotation.x -= 0.85 * tuck;
            j.torsoPivot.rotation.x += 0.38 * tuck;
        }

        const applyArmGait = (shoulderPivot, elbowPivot, wristPivot, fingerCurl, oppositeLegPhase, sideSign) => {
            const sinArm = Math.sin(oppositeLegPhase);
            const cosArm = Math.cos(oppositeLegPhase);
            const forwardAmt = Math.max(0, sinArm);
            const idleBreathArm = Math.sin(this.elapsedTime * 1.9 + sideSign * 0.4) * 0.018 * idleWeight;

            shoulderPivot.rotation.x = -0.40 * sinArm * mb + idleBreathArm - 0.38 * airBlend;
            shoulderPivot.rotation.z = sideSign * (0.075 + 0.032 * mb + Math.max(0, -sinArm) * 0.025 * mb + 0.16 * airBlend);
            shoulderPivot.rotation.y = -sideSign * 0.06 * sinArm * mb;

            elbowPivot.rotation.x = -0.16 - (0.11 + 0.34 * forwardAmt) * mb - 0.28 * airBlend;

            wristPivot.rotation.x = (-0.08 * cosArm - 0.05 * forwardAmt) * mb;
            wristPivot.rotation.z = sideSign * (0.04 + 0.05 * sinArm * mb);
            fingerCurl.rotation.z = -sideSign * (0.12 + 0.10 * forwardAmt * mb);
        };

        applyArmGait(j.shoulderPivotL, j.elbowPivotL, j.wristPivotL, j.fingerCurlL, phaseR, 1);
        applyArmGait(j.shoulderPivotR, j.elbowPivotR, j.wristPivotR, j.fingerCurlR, phaseL, -1);

        if (this.player.weaponSwingTimer > 0) {
            const pSwing = 1.0 - (this.player.weaponSwingTimer / 0.52);
            const arc = Math.sin(clamp(pSwing, 0, 1) * Math.PI);
            const sweep = Math.cos(clamp(pSwing, 0, 1) * Math.PI);
            j.shoulderPivotR.rotation.x = -1.18 * arc;
            j.shoulderPivotR.rotation.y = 0.58 * sweep * arc;
            j.shoulderPivotR.rotation.z = -0.34 * arc;
            j.elbowPivotR.rotation.x = -0.42 * arc;
            j.wristPivotR.rotation.x = 0.36 * arc;
        }

        if (this.player.punchSwingTimer > 0) {
            const pPunch = 1.0 - (this.player.punchSwingTimer / 0.35);
            const punchArc = Math.sin(clamp(pPunch, 0, 1) * Math.PI);
            const isLeft = (this.player.punchCombo % 2) === 1;

            if (isLeft) {
                j.torsoPivot.rotation.y -= 0.32 * punchArc;
                j.shoulderPivotL.rotation.x = -1.55 * punchArc;
                j.shoulderPivotL.rotation.z = 0.12 * punchArc;
                j.elbowPivotL.rotation.x = -0.16 * punchArc;
                j.wristPivotL.rotation.x = 0.25 * punchArc;
                j.fingerCurlL.rotation.z = -0.55 * punchArc;
            } else {
                j.torsoPivot.rotation.y += 0.32 * punchArc;
                j.shoulderPivotR.rotation.x = -1.55 * punchArc;
                j.shoulderPivotR.rotation.z = -0.12 * punchArc;
                j.elbowPivotR.rotation.x = -0.16 * punchArc;
                j.wristPivotR.rotation.x = 0.25 * punchArc;
                j.fingerCurlR.rotation.z = 0.55 * punchArc;
            }
        }

        const doubleStepBounce = Math.cos(2.0 * phaseL) * 0.024 * mb;
        const stanceSink = -0.014 * mb - squash * 0.16;
        const idleBreath = Math.sin(this.elapsedTime * 2.05) * 0.009 * idleWeight;

        j.pelvisPivot.position.y = 0.76 + doubleStepBounce + stanceSink + idleBreath;
        j.pelvisPivot.position.x = -Math.sin(phaseL) * 0.018 * mb;
        j.pelvisPivot.rotation.y = Math.sin(phaseL) * 0.065 * mb;
        j.pelvisPivot.rotation.z = -Math.sin(phaseL) * 0.032 * mb;

        const targetBank = clamp(-turnRate * 0.045, -0.085, 0.085) * mb;
        this.player.turnBank = lerp(this.player.turnBank, targetBank, blendFactor);

        j.torsoPivot.rotation.x = 0.068 * mb + Math.sin(this.elapsedTime * 2.05) * 0.012 * idleWeight + squash * 0.12;
        j.torsoPivot.rotation.y = -Math.sin(phaseL) * 0.085 * mb;
        j.torsoPivot.rotation.z = this.player.turnBank + Math.sin(phaseL) * 0.022 * mb;

        j.footShadow.position.y = 0.005 - jumpH;
        j.baseRing.position.y = 0.007 - jumpH;
        const shadowScale = (1.0 - doubleStepBounce * 1.8) / (1.0 + jumpH * 0.65);
        j.footShadow.scale.set(shadowScale, shadowScale, 1);
        j.sparkCrest.rotation.y = this.elapsedTime * 1.4;

        if (this.player.bubbleSprite) {
            const showOwnBubble = !this.focusProtected && !this.soloMode && this.elapsedTime < this.player.bubbleExpiresAt;
            this.player.bubbleSprite.visible = showOwnBubble;
        }

        let desiredHeadYaw = 0;
        let desiredHeadPitch = -0.02 * mb;

        if (nearestForGaze) {
            const worldToTargetYaw = Math.atan2(nearestForGaze.x - this.player.x, nearestForGaze.z - this.player.z);
            let relYaw = ((worldToTargetYaw - this.player.yaw + Math.PI) % TWO_PI) - Math.PI;
            if (relYaw < -Math.PI) relYaw += TWO_PI;

            if (Math.abs(relYaw) < 1.35) {
                const gazeStrength = clamp(1.0 - mb * 0.65, 0.35, 1.0);
                desiredHeadYaw = clamp(relYaw * 0.68, -0.56, 0.56) * gazeStrength;
                desiredHeadPitch = -0.06 * gazeStrength;
            }
        }

        const headSmooth = 1.0 - Math.exp(-dt * 6.5);
        this.player.headYaw = lerp(this.player.headYaw, desiredHeadYaw, headSmooth);
        this.player.headPitch = lerp(this.player.headPitch, desiredHeadPitch, headSmooth);
        j.headPivot.rotation.y = this.player.headYaw;
        j.headPivot.rotation.x = this.player.headPitch;
        j.headPivot.rotation.z = -this.player.headYaw * 0.12;

        if (this.player.visorMat) {
            if (!this.player.isBlinking && this.elapsedTime >= this.player.nextBlinkAt) {
                this.player.isBlinking = true;
                this.player.blinkEndAt = this.elapsedTime + 0.13;
                this.player.visorMat.map = this.player.visorTexBlink;
                this.player.visorMat.needsUpdate = true;
            } else if (this.player.isBlinking && this.elapsedTime >= this.player.blinkEndAt) {
                this.player.isBlinking = false;
                this.player.nextBlinkAt = this.elapsedTime + 3.2 + ((this.elapsedTime * 17.3) % 2.4);
                this.player.visorMat.map = this.player.visorTexOpen;
                this.player.visorMat.needsUpdate = true;
            }
        }
    }

    /**
     * Baustein B: Individuelle Level-1..10-Animationen fuer die Top-5-Funkis auf den Ehrenpodesten (60 FPS).
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

            if (lvl <= 2) {
                // Level 1-2 (Ursprung / Lehrling): Sanftes, neugieriges Wippen & vorsichtiges Umschauen nach links/rechts
                const bob = Math.sin(t * 1.65 + ph);
                rig.position.y = Math.max(0, bob) * 0.014;
                rig.scale.set(1.0 - bob * 0.004, 1.0 + bob * 0.011, 1.0 - bob * 0.004);
                rig.rotation.y = Math.sin(t * 0.85 + ph) * 0.24 + Math.sin(t * 1.7 + ph) * 0.05;
                rig.rotation.z = Math.sin(t * 1.15 + ph) * 0.028;
                rig.rotation.x = 0.02 + Math.sin(t * 0.9 + ph) * 0.014;
            } else if (lvl <= 4) {
                // Level 3-4 (Helfer / Kristall-Novize): Froehlicher rhythmischer Bounce mit sanfter Drehung
                const bounceWave = Math.abs(Math.sin(t * 2.55 + ph));
                rig.position.y = bounceWave * 0.058;
                rig.scale.set(1.0 + (0.5 - bounceWave) * 0.018, 1.0 + (bounceWave - 0.5) * 0.024, 1.0 + (0.5 - bounceWave) * 0.018);
                rig.rotation.y = Math.sin(t * 1.22 + ph) * 0.18;
                rig.rotation.z = Math.cos(t * 2.55 + ph) * 0.024;
                rig.rotation.x = 0;
            } else if (lvl <= 6) {
                // Level 5-6 (Funken-Geselle / Gravur-Handwerker): Stolze Meister-Praesenz mit kraeftigem Brust-Puls & Ehren-Schwenk
                const chestPulse = Math.sin(t * 2.05 + ph);
                rig.position.y = Math.max(0, chestPulse) * 0.022;
                rig.scale.set(1.0 + chestPulse * 0.014, 1.0 + chestPulse * 0.022, 1.0 + chestPulse * 0.016);
                rig.rotation.y = Math.sin(t * 0.62 + ph) * 0.14;
                rig.rotation.z = Math.sin(t * 0.48 + ph) * 0.014;
                rig.rotation.x = -0.018;
            } else if (lvl <= 8) {
                // Level 7-8 (Kunsthandwerker / Meister): Erhabenes leichtes Schweben ueber drehendem Goldring + souveraene Pose
                const hover = Math.sin(t * 1.85 + ph);
                rig.position.y = 0.085 + 0.044 * hover;
                rig.scale.set(1.0 - hover * 0.005, 1.0 + hover * 0.013, 1.0 - hover * 0.005);
                rig.rotation.y = Math.sin(t * 0.58 + ph) * 0.12;
                rig.rotation.z = Math.sin(t * 0.75 + ph) * 0.015;
                rig.rotation.x = -0.012;
            } else if (lvl === 9) {
                // Level 9 (Lichtbringer): Majestaetische Levitation mit rotierendem goldenem Lichtkranz
                const lev = Math.sin(t * 2.05 + ph);
                rig.position.y = 0.145 + 0.062 * lev;
                rig.scale.set(1.0, 1.0 + lev * 0.015, 1.0);
                rig.rotation.y = Math.sin(t * 0.54 + ph) * 0.15;
                rig.rotation.z = Math.cos(t * 0.8 + ph) * 0.018;
                rig.rotation.x = -0.015;
            } else {
                // Level 10 (Gott des Seelenfunkens): Epische goettliche Schwebepose ueber gegenlaeufig rotierenden 24K-Gold-Sternringen!
                const godFloat = Math.sin(t * 2.25 + ph);
                rig.position.y = 0.21 + 0.075 * godFloat;
                rig.scale.set(1.0 + godFloat * 0.008, 1.0 + godFloat * 0.018, 1.0 + godFloat * 0.008);
                rig.rotation.y = Math.sin(t * 0.50 + ph) * 0.16;
                rig.rotation.z = Math.sin(t * 0.90 + ph) * 0.016;
                rig.rotation.x = -0.022;
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

    triggerJump() {
        if (!this.running || this.destroyed) return false;

        // 1. Erster Sprung vom Boden
        if (!this.player.isJumping && (this.player.y || 0) <= 0.05) {
            this.player.isJumping = true;
            this.player.jumpCount = 1;
            this.player.vy = 5.65;
            this.player.isSomersault = false;
            this.player.somersaultProgress = 0.0;
            return true;
        }

        // 2. Doppelsprung mit 360-Grad-Salto in der Luft
        if (this.player.jumpCount === 1 && (this.player.y || 0) > 0.04) {
            this.player.jumpCount = 2;
            this.player.vy = 5.85;
            this.player.isSomersault = true;
            this.player.somersaultProgress = 0.0;
            this._spawnDoubleJumpBurst(this.player.x, (this.player.y || 0) + 0.12, this.player.z);
            return true;
        }

        return false;
    }

    triggerStrikeAction() {
        if (!this.running || this.destroyed) return false;

        // Falls Charakter eine Waffe ausgerüstet hat -> Schwert- / Stab-Schwung
        if (this.playerFunki && !this.playerFunki.is_guest && this.playerFunki.weapon) {
            return this.triggerWeaponAction();
        }

        // Unbewaffneter Faustschlag für Gäste & unbewaffnete Spieler
        if (this.player.punchCooldown > 0) return false;
        this.player.punchCooldown = 0.36;
        this.player.punchSwingTimer = 0.35;
        this.player.punchCombo = (this.player.punchCombo + 1) % 2;

        this._spawnUnarmedPunchBurst(
            this.player.x,
            (this.player.y || 0) + 1.02,
            this.player.z,
            this.player.yaw,
            this.player.punchCombo
        );
        return true;
    }

    triggerWeaponAction() {
        if (!this.running || this.destroyed) return false;
        if (!this.playerFunki || this.playerFunki.is_guest || !this.playerFunki.weapon) {
            return this.triggerStrikeAction();
        }
        if (this.player.weaponCooldown > 0) return false;

        this.player.weaponCooldown = 0.52;
        this.player.weaponSwingTimer = 0.50;
        this.player.weaponActionSeq += 1;

        const w = this.playerFunki.weapon;
        this._spawnWeaponBurst(
            this.player.x,
            (this.player.y || 0) + 1.06,
            this.player.z,
            this.player.yaw,
            w.accent_hex || '#C5A059',
            w.effect_type || 'gold_blade',
            false
        );
        return true;
    }

    _spawnDoubleJumpBurst(x, y, z) {
        if (!this.scene || !this.THREE) return;
        if (this.weaponBursts.length >= 12) {
            const oldest = this.weaponBursts.shift();
            if (oldest) this._disposeWeaponBurst(oldest);
        }

        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const group = new THREE.Group();
        group.position.set(x, y, z);

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

    _spawnUnarmedPunchBurst(x, y, z, yaw, combo) {
        if (!this.scene || !this.THREE) return;
        if (this.weaponBursts.length >= 12) {
            const oldest = this.weaponBursts.shift();
            if (oldest) this._disposeWeaponBurst(oldest);
        }

        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const group = new THREE.Group();
        const forwardOffset = 0.52;
        const sideOffset = combo === 0 ? 0.16 : -0.16;
        group.position.set(
            x + Math.sin(yaw) * forwardOffset + Math.cos(yaw) * sideOffset,
            y,
            z + Math.cos(yaw) * forwardOffset - Math.sin(yaw) * sideOffset
        );
        group.rotation.y = yaw;

        const arcMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#C5A059'),
            transparent: true,
            opacity: 0.90,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const arcMesh = new THREE.Mesh(shared.geos.slashArc, arcMat);
        arcMesh.rotation.x = Math.PI * 0.45;
        arcMesh.scale.set(0.52, 0.52, 0.52);
        group.add(arcMesh);

        const sparks = [];
        const sparkMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color('#FFF8E1'),
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
        });

        for (let i = 0; i < 6; i++) {
            const spread = -0.5 + (i / 5) * 1.0;
            const sMesh = new THREE.Mesh(shared.geos.sparkDiamond, sparkMat);
            sMesh.scale.setScalar(0.7);
            sMesh.position.set(Math.sin(spread) * 0.15, (i % 2 === 0 ? 0.05 : -0.04), Math.cos(spread) * 0.15);
            group.add(sMesh);
            sparks.push({
                mesh: sMesh,
                vx: Math.sin(spread) * 1.8,
                vy: 0.35 + (i % 3) * 0.2,
                vz: Math.cos(spread) * 2.2,
            });
        }

        this.scene.add(group);
        this.weaponBursts.push({
            group,
            slashMesh: arcMesh,
            arcMat,
            sparkMat,
            sparks,
            age: 0,
            duration: 0.36,
            isRemote: false,
        });
    }

    _spawnWeaponBurst(x, y, z, yaw, accentHex, effectType, isRemote) {
        if (!this.scene || !this.THREE) return;
        if (isRemote && (this.focusProtected || this.soloMode || this.fpsEstimate < 40.0)) {
            return;
        }
        if (this.weaponBursts.length >= 10) {
            const oldest = this.weaponBursts.shift();
            if (oldest) this._disposeWeaponBurst(oldest);
        }

        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const group = new THREE.Group();
        group.position.set(x + Math.sin(yaw) * 0.42, y, z + Math.cos(yaw) * 0.42);
        group.rotation.y = yaw;

        const arcMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color(accentHex || '#C5A059'),
            transparent: true,
            opacity: 0.88,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const slashMesh = new THREE.Mesh(shared.geos.slashArc, arcMat);
        slashMesh.rotation.x = Math.PI * 0.42;
        slashMesh.rotation.z = effectType === 'sapphire_staff' ? 0.0 : -0.36;
        group.add(slashMesh);

        const sparks = [];
        const sparkCount = isRemote ? 5 : 8;
        const sparkMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color(accentHex || '#E6C687'),
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
        });

        for (let i = 0; i < sparkCount; i++) {
            const angle = -0.75 + (i / Math.max(1, sparkCount - 1)) * 1.5;
            const sMesh = new THREE.Mesh(shared.geos.sparkDiamond, sparkMat);
            sMesh.position.set(Math.sin(angle) * 0.45, (i % 2 === 0 ? 0.06 : -0.05), Math.cos(angle) * 0.45);
            group.add(sMesh);
            sparks.push({
                mesh: sMesh,
                vx: Math.sin(angle) * (1.6 + (i % 3) * 0.35),
                vy: 0.45 + (i % 3) * 0.25,
                vz: Math.cos(angle) * (1.8 + (i % 2) * 0.4),
            });
        }

        this.scene.add(group);
        this.weaponBursts.push({
            group,
            slashMesh,
            arcMat,
            sparkMat,
            sparks,
            age: 0,
            duration: 0.46,
            isRemote: Boolean(isRemote),
        });
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
            b.arcMat.opacity = 0.85 * fade;
            b.sparkMat.opacity = 0.95 * fade;
            const arcScale = 0.72 + t * 0.68;
            b.slashMesh.scale.set(arcScale, arcScale, 1);
            b.slashMesh.rotation.z += dt * 2.8;

            for (let s = 0; s < b.sparks.length; s++) {
                const sp = b.sparks[s];
                sp.mesh.position.x += sp.vx * dt;
                sp.mesh.position.y += sp.vy * dt;
                sp.mesh.position.z += sp.vz * dt;
                sp.vy -= 3.8 * dt;
                sp.mesh.rotation.y += dt * 6.0;
                sp.mesh.scale.setScalar(Math.max(0.1, 1.0 - t * 0.85));
            }
        }
    }

    getCurrentZone() {
        return this.player.z < (this.hallNorthZ - 4.0) ? 'heroes' : 'gallery';
    }

    getLocalSyncState() {
        return {
            x: Number((this.player.x || 0).toFixed(3)),
            y: Number((this.player.y || 0).toFixed(3)),
            z: Number((this.player.z || 0).toFixed(3)),
            rot_y: Number((this.player.yaw || 0).toFixed(3)),
            rotY: Number((this.player.yaw || 0).toFixed(3)),
            speed: Number((this.player.currentSpeed || 0).toFixed(3)),
            zone: this.getCurrentZone(),
            jumping: Boolean(this.player.isJumping || (this.player.y || 0) > 0.02),
            weaponActionSeq: this.player.weaponActionSeq || 0,
        };
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
        if (this.focusProtected || this.soloMode || this.mutedPlayerIds.has(playerId)) return;

        const THREE = this.THREE;
        const myPid = this.playerFunki && this.playerFunki.player_id ? this.playerFunki.player_id : null;

        if (myPid && playerId === myPid && this.player.root) {
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

        const peer = this.peers.get(String(playerId));
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
     * mit voll artikuliertem 3D-Gelenk-Rig, weicher Lerp-Interpolation & echter Gehanimation bei peer.speed > 0.08!
     */
    updatePeers(peersArray) {
        if (this.destroyed || !this.THREE || !this.scene) return;
        if (this.soloMode || !Array.isArray(peersArray)) {
            this._clearAllPeers();
            return;
        }

        const THREE = this.THREE;
        const incomingIds = new Set();

        for (let i = 0; i < peersArray.length && i < 19; i++) {
            const pData = peersArray[i];
            const rawPid = pData ? (pData.peer_id || pData.player_id) : null;
            if (!rawPid) continue;

            const pid = String(rawPid);
            incomingIds.add(pid);

            let peer = this.peers.get(pid);
            const peerZone = String(pData.zone || '');
            let tx = clamp(Number(pData.x) || 0, -this.pantheonHalfW + 0.6, this.pantheonHalfW - 0.6);
            let tz = clamp(Number(pData.z) || 8.4, this.pantheonMinZ + 0.8, this.hallSouthZ - 1.0);
            if (peerZone === 'heroes' && tz > this.hallNorthZ - 2.0) {
                tz = this.pantheonCenterZ + 8.5;
            }
            const ty = clamp(Number(pData.y) || 0, 0, 4.5);
            const tYaw = Number(pData.rot_y ?? pData.rotY) || 0;
            const reportedSpeed = Math.max(0, Number(pData.speed) || 0);
            const wSeq = parseInt(pData.weapon_seq, 10) || 0;
            const peerLvl = clamp(parseInt(pData.level, 10) || 1, 1, 10);

            if (!peer) {
                const weapon = pData.weapon || null;
                const built = this._createArticulatedAvatarInstance(peerLvl, weapon, false);
                const floorOffset = tz < this.hallNorthZ - 0.5 ? 0.02 : 0.0;
                built.root.position.set(tx, floorOffset + ty, tz);
                built.root.rotation.y = tYaw;

                const fallbackClass = peerLvl >= 9 ? 'KLASSE S' : (peerLvl >= 6 ? 'KLASSE A' : (peerLvl >= 3 ? 'KLASSE B' : 'KLASSE C'));
                const classLabel = (weapon && weapon.class_label) ? weapon.class_label : (pData.ship_class || fallbackClass);
                const accentHex = (weapon && weapon.accent_hex) ? weapon.accent_hex : '#C5A059';
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
                    level: peerLvl,
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
                    reportedSpeed,
                    reportedMoveTimer: reportedSpeed > 0.08 ? 1.9 : 0,
                    walkPhase: 0,
                    moveBlend: 0,
                    weaponSeq: wSeq,
                    weaponSwingTimer: 0,
                    accentHex,
                    effectType: (weapon && weapon.effect_type) ? weapon.effect_type : 'gold_blade',
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
                peer.targetX = tx;
                peer.targetY = ty;
                peer.targetZ = tz;
                peer.targetYaw = tYaw;
                peer.reportedSpeed = reportedSpeed;
                if (reportedSpeed > 0.08) {
                    peer.reportedMoveTimer = 1.9;
                }

                if (wSeq > peer.weaponSeq) {
                    peer.weaponSeq = wSeq;
                    if (!this.mutedPlayerIds.has(pid) && !this.focusProtected) {
                        peer.weaponSwingTimer = 0.52;
                        this._spawnWeaponBurst(
                            peer.x,
                            peer.y + 1.06,
                            peer.z,
                            peer.yaw,
                            peer.accentHex,
                            peer.effectType,
                            true
                        );
                    }
                }
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

        const smooth = 1.0 - Math.exp(-dt * 8.5);
        const strideFrequency = 4.48;

        this.peers.forEach((peer, pid) => {
            const prevX = peer.x;
            const prevZ = peer.z;

            peer.x = lerp(peer.x, peer.targetX, smooth);
            peer.y = lerp(peer.y, peer.targetY, smooth);
            peer.z = lerp(peer.z, peer.targetZ, smooth);
            peer.yaw = lerpAngle(peer.yaw, peer.targetYaw, smooth);

            if (peer.reportedMoveTimer > 0) {
                peer.reportedMoveTimer = Math.max(0, peer.reportedMoveTimer - dt);
            }

            const stepDist = Math.hypot(peer.x - prevX, peer.z - prevZ);
            const measuredSpeed = stepDist / Math.max(0.001, dt);
            const activeReportedSpeed = peer.reportedMoveTimer > 0 ? peer.reportedSpeed : 0;
            const effectiveSpeed = Math.max(measuredSpeed, activeReportedSpeed * 0.65);

            const floorOffset = peer.z < this.hallNorthZ - 0.5 ? 0.02 : 0.0;
            const jumpH = Math.max(0, peer.y);
            const airBlend = clamp(jumpH / 0.26, 0, 1);

            peer.root.position.set(peer.x, floorOffset + jumpH, peer.z);
            peer.root.rotation.y = peer.yaw;

            if ((stepDist > 0.0002 || effectiveSpeed > 0.08) && airBlend < 0.65) {
                const cycleAdvance = stepDist > 0.0004 ? stepDist : (effectiveSpeed * dt);
                peer.walkPhase = (peer.walkPhase + cycleAdvance * strideFrequency) % TWO_PI;
            }

            const targetBlend = effectiveSpeed > 0.08 ? clamp(effectiveSpeed / 4.6, 0.28, 1.0) : 0.0;
            peer.moveBlend = lerp(peer.moveBlend, targetBlend, smooth);

            const mb = peer.moveBlend * (1.0 - airBlend * 0.65);
            const j = peer.joints;
            const pL = peer.walkPhase;
            const pR = pL + Math.PI;

            j.hipPivotL.rotation.x = -0.52 * Math.sin(pL) * mb - 0.30 * airBlend;
            j.hipPivotR.rotation.x = -0.52 * Math.sin(pR) * mb - 0.18 * airBlend;
            j.kneePivotL.rotation.x = Math.max(0, Math.cos(pL - 0.16) * 0.68 * mb + 0.56 * airBlend);
            j.kneePivotR.rotation.x = Math.max(0, Math.cos(pR - 0.16) * 0.68 * mb + 0.42 * airBlend);
            j.anklePivotL.rotation.x = -Math.max(0, Math.cos(pL - 0.16)) * 0.22 * mb;
            j.anklePivotR.rotation.x = -Math.max(0, Math.cos(pR - 0.16)) * 0.22 * mb;

            j.shoulderPivotL.rotation.x = -0.38 * Math.sin(pR) * mb - 0.34 * airBlend;
            j.elbowPivotL.rotation.x = -0.16 - 0.28 * Math.max(0, Math.sin(pR)) * mb;
            j.wristPivotL.rotation.x = -0.08 * Math.cos(pR) * mb;

            if (peer.weaponSwingTimer > 0) {
                peer.weaponSwingTimer = Math.max(0, peer.weaponSwingTimer - dt);
                const pSwing = 1.0 - (peer.weaponSwingTimer / 0.52);
                const arc = Math.sin(clamp(pSwing, 0, 1) * Math.PI);
                j.shoulderPivotR.rotation.x = -1.16 * arc;
                j.shoulderPivotR.rotation.z = -0.32 * arc;
                j.elbowPivotR.rotation.x = -0.40 * arc;
            } else {
                j.shoulderPivotR.rotation.x = -0.38 * Math.sin(pL) * mb - 0.34 * airBlend;
                j.shoulderPivotR.rotation.z = -0.08;
                j.elbowPivotR.rotation.x = -0.16 - 0.28 * Math.max(0, Math.sin(pL)) * mb;
                j.wristPivotR.rotation.x = -0.08 * Math.cos(pL) * mb;
            }

            const bounce = Math.cos(2.0 * pL) * 0.022 * mb;
            j.pelvisPivot.position.y = 0.76 + bounce;
            j.torsoPivot.rotation.y = -Math.sin(pL) * 0.075 * mb;
            j.footShadow.position.y = 0.005 - jumpH;
            j.baseRing.position.y = 0.007 - jumpH;
            const sScale = 1.0 / (1.0 + jumpH * 0.65);
            j.footShadow.scale.set(sScale, sScale, 1);
            j.sparkCrest.rotation.y = this.elapsedTime * 1.4;

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
        const next = Boolean(flag) && !this.isAuthenticatedCustomer;
        if (this.portalGateFocused === next) return;
        this.portalGateFocused = next;
        if (this.onPortalGatePromptChange) {
            this.onPortalGatePromptChange(next);
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
        const topSafeY = Math.min(254, Math.max(185, this._viewH * 0.42));
        const clampedX = clamp(rawX, marginX, Math.max(marginX, this._viewW - marginX));
        const clampedY = clamp(rawY, topSafeY, Math.max(topSafeY, this._viewH - 96));

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
        if (idx >= 0 && this.steles[idx]) {
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
        if (hSlot >= 1 && hSlot <= 5 && this.heroSlotAnchors[hSlot]) {
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
        if (this.portalGateFocused && !this.isAuthenticatedCustomer) {
            this._projectElementToWorld(this.floatingPortalCardEl, 0, 2.75, this.hallNorthZ - 0.25, '_portalCard');
        } else {
            this._hideProjectedElement(this.floatingPortalCardEl, '_portalCard');
        }
    }

    _bindEvents() {
        const el = this.canvas;
        window.addEventListener('resize', this._boundResize);
        document.addEventListener('fullscreenchange', this._boundFullscreenResize);
        document.addEventListener('webkitfullscreenchange', this._boundFullscreenResize);
        window.addEventListener('keydown', this._boundKeyDown);
        window.addEventListener('keyup', this._boundKeyUp);

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

    _onKeyDown(e) {
        if (!this.running) return;
        const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

        const code = e.code || e.key;
        this.keys[code] = true;

        if (code === 'Space' || e.key === ' ') {
            e.preventDefault();
            this.triggerJump();
            return;
        }

        if (code === 'KeyF') {
            this.triggerStrikeAction();
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
            }
        }
    }

    _onKeyUp(e) {
        const code = e.code || e.key;
        this.keys[code] = false;
    }

    _onPointerDown(e) {
        if (!this.running) return;
        this.camControl.isDragging = true;
        this.camControl.dragMoved = 0;
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

        if (this.camControl.isDragging) {
            const dx = e.clientX - this.camControl.lastX;
            const dy = e.clientY - this.camControl.lastY;
            this.camControl.lastX = e.clientX;
            this.camControl.lastY = e.clientY;
            this.camControl.dragMoved += Math.hypot(dx, dy);

            this.camControl.desiredYaw -= dx * 0.0055;
            this.camControl.desiredPitch = clamp(this.camControl.desiredPitch + dy * 0.0042, 0.04, 0.80);
            return;
        }

        // Leichtgewichtiger Raycast-Hover fuer cursor: pointer ueber Top-5-Helden, Produkten & Portal
        this._hoverTickCounter = (this._hoverTickCounter + 1) % 3;
        if (this._hoverTickCounter !== 0 || !this.camera || !this.canvas) return;

        const rect = this.canvas.getBoundingClientRect();
        this.pointerNdc.x = ((e.clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((e.clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);

        const targets = this.currentZone === 'heroes'
            ? this.heroInteractiveHitMeshes
            : this.interactiveHitMeshes.concat(this.portalHitMeshes).concat(this.heroInteractiveHitMeshes);

        if (targets.length > 0 && this.raycaster.intersectObjects(targets, false).length > 0) {
            this.canvas.style.cursor = 'pointer';
        } else {
            this.canvas.style.cursor = 'grab';
        }
    }

    _onPointerUp(e) {
        if (!this.camControl.isDragging) return;
        this.camControl.isDragging = false;
        this.canvas.style.cursor = 'grab';
        try {
            this.canvas.releasePointerCapture(e.pointerId);
        } catch (err) {
            // PointerCapture optional
        }

        if (this.camControl.dragMoved < 8 && (e.button === 0 || e.pointerType === 'touch')) {
            this._handleCanvasTap(e.clientX, e.clientY, e.pointerType || 'mouse');
        } else if (this.camControl.dragMoved < 8 && e.button === 2) {
            this.triggerStrikeAction();
        }
    }

    _onWheel(e) {
        if (!this.running) return;
        e.preventDefault();
        const factor = e.deltaY > 0 ? 1.09 : 0.91;
        const maxDist = this.currentZone === 'heroes' ? 10.5 : 7.8;
        this.camControl.desiredDistance = clamp(this.camControl.desiredDistance * factor, 2.5, maxDist);
    }

    _onTouchStart(e) {
        if (e.touches.length === 2) {
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            this.camControl.pinchDist = Math.hypot(dx, dy);
        }
    }

    _onTouchMove(e) {
        if (e.touches.length === 2 && this.camControl.pinchDist > 8) {
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            const dist = Math.hypot(dx, dy);
            const scale = this.camControl.pinchDist / Math.max(8, dist);
            this.camControl.pinchDist = dist;
            const maxDist = this.currentZone === 'heroes' ? 10.5 : 7.8;
            this.camControl.desiredDistance = clamp(this.camControl.desiredDistance * scale, 2.5, maxDist);
        }
    }

    _handleCanvasTap(clientX, clientY, pointerType = 'mouse') {
        if (!this.canvas || !this.camera) return;
        const rect = this.canvas.getBoundingClientRect();
        this.pointerNdc.x = ((clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);

        // 1. Antippen eines Top-5-Heldenpodests oder einer Ehren-Stele in der Halle der Helden
        if (this.heroInteractiveHitMeshes.length > 0 && this.isAuthenticatedCustomer) {
            const heroHits = this.raycaster.intersectObjects(this.heroInteractiveHitMeshes, false);
            if (heroHits.length > 0) {
                const slot = heroHits[0].object.userData.heroSlot;
                this.goToHeroSlot(slot);
                return;
            }
        }

        // 2. Antippen des Ehrenportals als Gast -> laeuft vor die Lichtschranke & blendet Mitglieder-Lobby-Schild ein
        if (!this.isAuthenticatedCustomer && this.portalHitMeshes.length > 0) {
            const portalHits = this.raycaster.intersectObjects(this.portalHitMeshes, false);
            if (portalHits.length > 0) {
                this.goToZone('heroes', false);
                return;
            }
        }

        // 3. Antippen eines Produkt-Podests in der Eingangshalle
        if (this.interactiveHitMeshes.length > 0) {
            const steleHits = this.raycaster.intersectObjects(this.interactiveHitMeshes, false);
            if (steleHits.length > 0) {
                const idx = steleHits[0].object.userData.steleIndex;
                this.goToExhibit(idx, false);
                return;
            }
        }

        // 4. Mausklick im Raum / auf den Boden -> Schlaganimation (Faust oder Waffe)
        if (pointerType === 'mouse') {
            this.triggerStrikeAction();
            return;
        }

        // 5. Tap-to-Walk auf den Marmorboden (Eingangshalle, Ehrenpromenade oder Halle der Helden fuer Touch)
        if (this.walkableFloorMeshes.length > 0) {
            const floorHits = this.raycaster.intersectObjects(this.walkableFloorMeshes, false);
            if (floorHits.length > 0) {
                const pt = floorHits[0].point;
                const resolved = this._resolveCollisions(pt.x, pt.z);
                this.pinnedSteleIndex = -1;
                this.pinnedHeroSlot = -1;
                this.pinnedPortalGate = false;
                this.player.autoTarget = { x: resolved.x, z: resolved.z };
                this.player.autoLookAt = null;

                if (this.walkTargetRing) {
                    this.walkTargetRing.position.set(resolved.x, resolved.z < this.hallNorthZ - 0.5 ? 0.035 : 0.015, resolved.z);
                    this.walkTargetTimer = 1.35;
                }
            }
        }
    }

    /**
     * Wechselt sanft oder sofort zwischen Bereich 1 ('gallery') und Bereich 2 ('heroes').
     */
    goToZone(targetZone, instant = false) {
        const cleanZone = (targetZone === 'heroes' || targetZone === 'hall') ? 'heroes' : 'gallery';

        if (cleanZone === 'heroes') {
            if (!this.isAuthenticatedCustomer) {
                // Gaeste schreiten bis vor das goldene Ehrenportal & sehen das Mitglieder-Schild
                this.pinnedSteleIndex = -1;
                this.pinnedPortalGate = true;
                this.player.autoTarget = { x: 0, z: this.hallNorthZ + 2.4 };
                this.player.autoLookAt = { x: 0, z: this.hallNorthZ - 2.0 };
                this.camControl.desiredYaw = 0;
                this.camControl.desiredPitch = 0.20;
                this.camControl.desiredDistance = 4.2;
                this._setPortalGateFocused(true);
                return;
            }

            const destX = 0;
            const destZ = this.pantheonCenterZ + 9.2;
            this.pinnedSteleIndex = -1;
            this.pinnedHeroSlot = -1;
            this._setFocusedSteleIndex(-1);

            if (instant || Math.abs(this.player.z - destZ) > 18.0) {
                this.player.x = destX;
                this.player.z = destZ;
                this.player.yaw = Math.PI;
                this.player.autoTarget = null;
                this.player.autoLookAt = null;
                this.camControl.yaw = 0;
                this.camControl.desiredYaw = 0;
                this.camControl.pitch = 0.16;
                this.camControl.desiredPitch = 0.16;
                this.camControl.distance = 5.4;
                this.camControl.desiredDistance = 5.4;
                if (this.camControl.lookAtCurrent) {
                    this.camControl.lookAtCurrent.set(destX, 1.48, destZ);
                }
            } else {
                this.player.autoTarget = { x: destX, z: destZ };
                this.player.autoLookAt = { x: 0, z: this.pantheonCenterZ - 7.8 };
                this.camControl.desiredYaw = 0;
                this.camControl.desiredPitch = 0.16;
                this.camControl.desiredDistance = 5.4;
            }

            if (this.currentZone !== 'heroes') {
                this.currentZone = 'heroes';
                if (this.onZoneChange) this.onZoneChange('heroes');
            }
            return;
        }

        // Zurueck in die Produkt-Eingangshalle ('gallery')
        this.pinnedHeroSlot = -1;
        this.pinnedPortalGate = false;
        this._setFocusedHeroSlot(-1);
        this._setPortalGateFocused(false);

        const galleryDestX = 0;
        const galleryDestZ = this.hallSouthZ - 4.2;
        if (instant || Math.abs(this.player.z - galleryDestZ) > 18.0) {
            this.player.x = galleryDestX;
            this.player.z = galleryDestZ;
            this.player.yaw = Math.PI;
            this.player.autoTarget = null;
            this.player.autoLookAt = null;
            this.camControl.yaw = 0;
            this.camControl.desiredYaw = 0;
            this.camControl.desiredPitch = 0.24;
            this.camControl.desiredDistance = 4.35;
            if (this.camControl.lookAtCurrent) {
                this.camControl.lookAtCurrent.set(galleryDestX, 1.38, galleryDestZ);
            }
        } else {
            this.player.autoTarget = { x: galleryDestX, z: galleryDestZ };
            this.player.autoLookAt = { x: 0, z: this.hallNorthZ };
            this.camControl.desiredYaw = 0;
            this.camControl.desiredPitch = 0.24;
            this.camControl.desiredDistance = 4.35;
        }

        if (this.currentZone !== 'gallery') {
            this.currentZone = 'gallery';
            if (this.onZoneChange) this.onZoneChange('gallery');
        }
    }

    /**
     * Laeuft direkt zu einem Ehrenpodest (#1..#5) oder einer Ehren-Stele (#6+) und zeigt die schwebenden Helden-Stats.
     */
    goToHeroSlot(slotNumber) {
        if (!this.isAuthenticatedCustomer) {
            this.goToZone('heroes', false);
            return;
        }

        const slot = clamp(parseInt(slotNumber, 10) || 1, 1, 999);
        const anchor = this.heroSlotAnchors[slot] || this.heroSlotAnchors[1];
        if (!anchor) return;

        if (this.player.z > this.hallNorthZ - 3.0) {
            this.player.x = 0;
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
        this.camControl.desiredPitch = 0.18;
        this.camControl.desiredDistance = 4.85;

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

        if (this.player.z < this.hallNorthZ - 2.0) {
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
        this.camControl.desiredPitch = 0.24;
        this.camControl.desiredDistance = 3.85;

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

    /**
     * Nahtlose 2-Zonen-Kollisionsaufloesung (Produktgalerie + Ehrenportal/Promenade + Pantheon-Rotunde mit Saeulen & Podesten).
     */
    _resolveCollisions(nx, nz) {
        let x = nx;
        let z = clamp(nz, this.pantheonMinZ + 1.0, this.hallSouthZ - 1.3);

        // Gast-Lichtschranke am goldenen Ehrenportal
        if (!this.isAuthenticatedCustomer && z < this.hallNorthZ + 1.05) {
            z = this.hallNorthZ + 1.05;
        }

        const portalClearHalfW = this.portalHalfW - 0.55;

        if (z >= this.hallNorthZ + 0.9) {
            // Zone 1: Produktgalerie
            x = clamp(x, -this.hallHalfW + 1.0, this.hallHalfW - 1.0);
            if (z < this.hallNorthZ + 1.15 && Math.abs(x) > portalClearHalfW && this.player.z >= this.hallNorthZ + 0.9) {
                z = this.hallNorthZ + 1.15;
            }
        } else if (z >= this.heroesEntryZ + 0.4) {
            // Ehrenportal & Marmor-Ehrenpromenade
            x = clamp(x, -portalClearHalfW, portalClearHalfW);
        } else {
            // Zone 2: Pantheon-Rotunde ("Halle der Helden")
            x = clamp(x, -this.pantheonHalfW + 0.8, this.pantheonHalfW - 0.8);
            // Suedwand der Rotunde ausserhalb der Ehrenpromenade
            if (z > this.heroesEntryZ - 0.6 && Math.abs(x) > portalClearHalfW + 1.2) {
                z = this.heroesEntryZ - 0.6;
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
                const push = (minSteleRadius - dist);
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
        return this._collisionOut;
    }

    _tick(nowMs) {
        if (!this.running || this.destroyed) return;
        this.rafId = requestAnimationFrame(this._boundTick);

        const dt = clamp((nowMs - this.lastTimeMs) / 1000, 0.001, 0.05);
        this.lastTimeMs = nowMs;
        this.elapsedTime += dt;

        const instantFps = 1.0 / Math.max(0.001, dt);
        this.fpsEstimate = lerp(this.fpsEstimate, clamp(instantFps, 15, 120), 0.08);

        if (this.player.weaponCooldown > 0) {
            this.player.weaponCooldown = Math.max(0, this.player.weaponCooldown - dt);
        }
        if (this.player.weaponSwingTimer > 0) {
            this.player.weaponSwingTimer = Math.max(0, this.player.weaponSwingTimer - dt);
        }
        if (this.player.punchCooldown > 0) {
            this.player.punchCooldown = Math.max(0, this.player.punchCooldown - dt);
        }
        if (this.player.punchSwingTimer > 0) {
            this.player.punchSwingTimer = Math.max(0, this.player.punchSwingTimer - dt);
        }
        if (this.player.isJumping || this.player.y > 0) {
            this.player.vy -= 15.2 * dt;
            this.player.y += this.player.vy * dt;

            if (this.player.isSomersault) {
                this.player.somersaultProgress += dt / Math.max(0.01, this.player.somersaultDuration);
                if (this.player.somersaultProgress >= 1.0) {
                    this.player.somersaultProgress = 1.0;
                    this.player.isSomersault = false;
                }
            }

            if (this.player.y <= 0) {
                this.player.y = 0;
                this.player.vy = 0;
                this.player.isJumping = false;
                this.player.jumpCount = 0;
                this.player.isSomersault = false;
                this.player.somersaultProgress = 0.0;
                this.player.landSquash = 0.18;
            }
        }
        if (this.player.landSquash > 0) {
            this.player.landSquash = Math.max(0, this.player.landSquash - dt * 0.85);
        }

        let inputForward = 0;
        let inputRight = 0;

        if (this.keys.KeyW || this.keys.ArrowUp) inputForward += 1;
        if (this.keys.KeyS || this.keys.ArrowDown) inputForward -= 1;
        if (this.keys.KeyD || this.keys.ArrowRight) inputRight += 1;
        if (this.keys.KeyA || this.keys.ArrowLeft) inputRight -= 1;

        if (Math.abs(this.joystick.x) > 0.04 || Math.abs(this.joystick.y) > 0.04) {
            inputRight += this.joystick.x;
            inputForward -= this.joystick.y;
        }

        const hasManualInput = Math.hypot(inputForward, inputRight) > 0.05;
        if (hasManualInput) {
            this.player.autoTarget = null;
            this.player.autoLookAt = null;
            this.pinnedSteleIndex = -1;
            this.pinnedHeroSlot = -1;
            this.pinnedPortalGate = false;
        }

        const moveSpeed = 5.2;
        let vx = 0;
        let vz = 0;

        if (hasManualInput) {
            const len = Math.max(1.0, Math.hypot(inputForward, inputRight));
            const nf = inputForward / len;
            const nr = inputRight / len;
            const sinY = Math.sin(this.camControl.yaw);
            const cosY = Math.cos(this.camControl.yaw);

            vx = (-sinY * nf + cosY * nr) * moveSpeed;
            vz = (-cosY * nf - sinY * nr) * moveSpeed;
        } else if (this.player.autoTarget) {
            const dx = this.player.autoTarget.x - this.player.x;
            const dz = this.player.autoTarget.z - this.player.z;
            const dist = Math.hypot(dx, dz);
            if (dist > 0.08) {
                const stepSpeed = Math.min(moveSpeed * 1.22, Math.max(1.75, dist * 3.4));
                vx = (dx / dist) * stepSpeed;
                vz = (dz / dist) * stepSpeed;
            } else {
                this.player.autoTarget = null;
                this.pinnedSteleIndex = -1;
                if (this.player.autoLookAt) {
                    const faceYaw = Math.atan2(
                        this.player.autoLookAt.x - this.player.x,
                        this.player.autoLookAt.z - this.player.z
                    );
                    this.player.yaw = faceYaw;
                    this.player.autoLookAt = null;
                }
            }
        }

        const speedMag = Math.hypot(vx, vz);
        this.player.isMoving = speedMag > 0.08;

        const prevX = this.player.x;
        const prevZ = this.player.z;
        const prevYaw = this.player.yaw;

        if (this.player.isMoving) {
            const nextPos = this._resolveCollisions(
                this.player.x + vx * dt,
                this.player.z + vz * dt
            );
            this.player.x = nextPos.x;
            this.player.z = nextPos.z;

            const moveYaw = Math.atan2(vx, vz);
            this.player.yaw = lerpAngle(this.player.yaw, moveYaw, 1.0 - Math.exp(-12.0 * dt));
        }

        const stepDist = Math.hypot(this.player.x - prevX, this.player.z - prevZ);
        this.player.currentSpeed = stepDist / Math.max(0.001, dt);

        let yawDiff = ((this.player.yaw - prevYaw + Math.PI) % TWO_PI) - Math.PI;
        if (yawDiff < -Math.PI) yawDiff += TWO_PI;
        const turnRate = yawDiff / dt;

        // Zonen-Tracking ('gallery' <-> 'heroes') live fuer das HUD aktualisieren
        const activeZoneNow = this.getCurrentZone();
        if (activeZoneNow !== this.currentZone) {
            this.currentZone = activeZoneNow;
            if (this.onZoneChange) {
                this.onZoneChange(activeZoneNow);
            }
        }

        // Gast-Lichtschranke am Ehrenportal pruefen
        if (!this.isAuthenticatedCustomer) {
            const nearGate = (this.player.z < this.hallNorthZ + 4.2 && Math.abs(this.player.x) < this.portalHalfW + 0.8) || this.pinnedPortalGate;
            this._setPortalGateFocused(nearGate);
        }

        // Proximity-Fokus fuer Produkt-Exponate (Zone 'gallery') & Top-5-Heldenpodeste (Zone 'heroes')
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

        // Kinematik & 60-FPS Animationen
        this._updatePlayerKinematics(dt, stepDist, turnRate, nearestGaze);
        this._updatePeersKinematics(dt);
        this._updateHeroPedestalAnimations(this.elapsedTime);
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
            this.walkTargetRing.material.opacity = clamp(this.walkTargetTimer * 0.65, 0, 0.75);
        }

        // Gedaempfte Verfolgerkamera mit zonenabhaengigen Raumgrenzen
        const camDamp = 1.0 - Math.exp(-9.5 * dt);
        this.camControl.yaw = lerpAngle(this.camControl.yaw, this.camControl.desiredYaw, camDamp);
        this.camControl.pitch = lerp(this.camControl.pitch, this.camControl.desiredPitch, camDamp);
        this.camControl.distance = lerp(this.camControl.distance, this.camControl.desiredDistance, camDamp);

        this.camControl.lookAtCurrent.x = lerp(this.camControl.lookAtCurrent.x, this.player.x, camDamp);
        this.camControl.lookAtCurrent.y = lerp(this.camControl.lookAtCurrent.y, 1.42 + (this.player.y || 0) * 0.35, camDamp);
        this.camControl.lookAtCurrent.z = lerp(this.camControl.lookAtCurrent.z, this.player.z, camDamp);

        const horizDist = Math.cos(this.camControl.pitch) * this.camControl.distance;
        const rawCamX = this.camControl.lookAtCurrent.x + Math.sin(this.camControl.yaw) * horizDist;
        const rawCamY = this.camControl.lookAtCurrent.y + Math.sin(this.camControl.pitch) * this.camControl.distance;
        const rawCamZ = this.camControl.lookAtCurrent.z + Math.cos(this.camControl.yaw) * horizDist;

        const maxBoundX = activeZoneNow === 'heroes'
            ? (this.pantheonHalfW - 0.4)
            : (this.player.z < this.hallNorthZ + 0.5 ? (this.portalHalfW - 0.25) : (this.hallHalfW - 0.65));
        const maxBoundY = activeZoneNow === 'heroes' ? 19.5 : 11.5;
        const minBoundZ = this.isAuthenticatedCustomer ? (this.pantheonMinZ + 0.6) : (this.hallNorthZ + 0.75);

        this.camera.position.set(
            clamp(rawCamX, -maxBoundX, maxBoundX),
            clamp(rawCamY, 0.85, maxBoundY),
            clamp(rawCamZ, minBoundZ, this.hallSouthZ - 0.55)
        );
        this.camera.lookAt(this.camControl.lookAtCurrent);

        this._updateFloatingCardProjection();

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

        // Strikte Pixel-Ratio-Begrenzung auf max. 1.5 gegen 4K/Retina-Fillrate-Einbrueche
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        this.renderer.setSize(w, h, false);
    }

    destroy() {
        this.running = false;
        this.destroyed = true;
        this._monumentGeneration += 1;

        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = 0;
        }
        if (this.queueTimerId) {
            clearTimeout(this.queueTimerId);
            this.queueTimerId = 0;
        }
        if (this.abortController) {
            try {
                this.abortController.abort();
            } catch (err) {
                // Bereits abgebrochen
            }
            this.abortController = null;
        }

        [this.floatingCardEl, this.floatingHeroCardEl, this.floatingPortalCardEl].forEach((el) => {
            if (el) {
                el.style.opacity = '0';
                el.style.visibility = 'hidden';
                el.style.pointerEvents = 'none';
            }
        });
        this.floatingCardEl = null;
        this.floatingHeroCardEl = null;
        this.floatingPortalCardEl = null;

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
        this.exhibitLoadQueue = [];
        this.steles = [];
        this.heroPedestals = [];
        this.interactiveHitMeshes = [];
        this.heroInteractiveHitMeshes = [];
        this.portalHitMeshes = [];
        this.walkableFloorMeshes = [];

        if (this.renderer) {
            this.renderer.dispose();
            if (typeof this.renderer.forceContextLoss === 'function') {
                try {
                    this.renderer.forceContextLoss();
                } catch (err) {
                    // Context bereits freigegeben
                }
            }
            this.renderer = null;
        }
        this.scene = null;
        this.camera = null;
    }
}

export function createLouvreShowroom(options) {
    const instance = new SeelenfunkeLouvreShowroom3D(options);
    instance.init();
    return instance;
}
