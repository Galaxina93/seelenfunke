(function () {
    'use strict';

    if (window.SeelenfunkeHeroes3D) {
        return;
    }

    var MODULE_URL = '/js/seelenfunke-louvre-showroom-3d.js?v=20261117v2';

    var KEYBIND_ACTIONS = [
        { key: 'jump', label: 'Sprung' },
        { key: 'strike', label: 'Aktion / Waffe' },
        { key: 'sprint', label: '1 · Funken-Sprint' },
        { key: 'titanJump', label: '2 · Titan-Sprung' },
        { key: 'shield', label: '3 · Schutzschild' },
        { key: 'burst', label: '4 · Funkenstoß' },
        { key: 'moveForward', label: 'Vorwärts' },
        { key: 'moveLeft', label: 'Links' },
        { key: 'moveBack', label: 'Zurück' },
        { key: 'moveRight', label: 'Rechts' }
    ];

    var STANDALONE_SCOPED_CSS = [
        '@keyframes sfLobbySpin { to { transform: rotate(360deg); } }',
        '.sf-lobby-modal { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; inset: 0 !important; width: 100vw !important; height: 100vh !important; height: 100dvh !important; z-index: 2147483000 !important; background: #FAF8F5 !important; overflow: hidden !important; margin: 0 !important; padding: 0 !important; box-sizing: border-box !important; user-select: none !important; -webkit-user-select: none !important; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #111827; }',
        '.sf-lobby-modal.is-open { display: flex !important; flex-direction: column !important; }',
        '.sf-lobby-modal.is-closed { display: none !important; }',
        '.sf-lobby-topbar { position: absolute; top: 0; left: 0; right: 0; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; pointer-events: none; z-index: 30; box-sizing: border-box; }',
        '.sf-lobby-nav-pill { pointer-events: auto; background: rgba(255,255,255,0.94); border: 1px solid rgba(197,160,89,0.42); border-radius: 999px; padding: 5px; box-shadow: 0 8px 24px rgba(17,24,39,0.08); display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; max-width: 100%; box-sizing: border-box; }',
        '.sf-lobby-zone-btn { appearance: none; border: none; border-radius: 999px; padding: 7px 14px; font-size: 12px; font-weight: 800; line-height: 1.2; background: transparent; color: #111827; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; transition: background-color 150ms ease, color 150ms ease; }',
        '.sf-lobby-zone-btn:hover { background: rgba(197,160,89,0.14); }',
        '.sf-lobby-zone-btn.is-active { background: #C5A059 !important; color: #FFFFFF !important; }',
        '.sf-lobby-stages-btn { appearance: none; border: 1px solid rgba(197,160,89,0.55); border-radius: 999px; padding: 6px 12px; font-size: 11px; font-weight: 800; line-height: 1.2; background: #FAF8F5; color: #111827; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }',
        '.sf-lobby-actions { pointer-events: auto; display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }',
        '.sf-lobby-pill-btn { appearance: none; background: rgba(255,255,255,0.94); color: #111827; border: 1px solid rgba(197,160,89,0.48); border-radius: 999px; padding: 7px 13px; font-size: 12px; font-weight: 800; line-height: 1.2; box-shadow: 0 8px 24px rgba(17,24,39,0.08); cursor: pointer; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }',
        '.sf-lobby-pill-btn:hover { background: #FFFFFF; border-color: #C5A059; }',
        '.sf-lobby-pill-btn.is-gold { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }',
        '.sf-lobby-close-btn { appearance: none; background: rgba(255,255,255,0.96); color: #111827; border: 1px solid rgba(197,160,89,0.55); border-radius: 999px; padding: 7px 14px; font-size: 12px; font-weight: 800; line-height: 1.2; box-shadow: 0 8px 24px rgba(17,24,39,0.10); cursor: pointer; display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; }',
        '.sf-lobby-close-btn:hover { background: #FFFFFF; border-color: #C5A059; }',
        '.sf-lobby-kbd { display: inline-block; padding: 2px 6px; border-radius: 6px; background: #FAF8F5; border: 1px solid rgba(197,160,89,0.45); color: #6B7280; font-size: 10px; font-weight: 800; line-height: 1.1; }',
        '.sf-lobby-dot { width: 8px; height: 8px; border-radius: 999px; display: inline-block; flex-shrink: 0; }',
        '.sf-lobby-dot.is-live { background: #10B981; }',
        '.sf-lobby-dot.is-solo { background: #9CA3AF; }',
        '.sf-lobby-instance-wrap { position: relative; display: inline-flex; }',
        '.sf-lobby-instance-popover { position: absolute; top: calc(100% + 8px); right: 0; width: 260px; background: #FFFFFF; border: 1px solid rgba(197,160,89,0.45); border-radius: 16px; padding: 12px; box-shadow: 0 18px 40px rgba(17,24,39,0.14); z-index: 60; color: #111827; box-sizing: border-box; }',
        '.sf-lobby-instance-list { max-height: 176px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; }',
        '.sf-lobby-instance-item { appearance: none; width: 100%; padding: 7px 11px; border-radius: 11px; border: 1px solid rgba(197,160,89,0.38); background: #FAF8F5; color: #111827; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: space-between; cursor: pointer; box-sizing: border-box; }',
        '.sf-lobby-instance-item:hover { background: #F5EFE2; }',
        '.sf-lobby-instance-item.is-current { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }',
        '.sf-lobby-instance-form { margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(197,160,89,0.28); display: flex; align-items: center; gap: 6px; }',
        '.sf-lobby-instance-input { width: 58px; padding: 6px 8px; border-radius: 10px; border: 1px solid rgba(197,160,89,0.55); background: #FAF8F5; color: #111827; font-size: 12px; font-weight: 800; outline: none; box-sizing: border-box; }',
        '.sf-lobby-instance-submit { appearance: none; flex: 1; padding: 7px 10px; border-radius: 10px; border: none; background: #C5A059; color: #FFFFFF; font-size: 11px; font-weight: 800; cursor: pointer; }',
        '.sf-lobby-stage { position: relative; flex: 1 1 auto; min-height: 0; width: 100%; height: 100%; overflow: hidden; }',
        '.sf-lobby-canvas { position: absolute; top: 0; left: 0; right: 0; bottom: 0; width: 100% !important; height: 100% !important; display: block; touch-action: none; cursor: grab; }',
        '.sf-lobby-loading { position: absolute; inset: 0; z-index: 26; background: #FAF8F5; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; padding: 24px; text-align: center; }',
        '.sf-lobby-spinner { width: 40px; height: 40px; border-radius: 999px; border: 3px solid rgba(197,160,89,0.26); border-top-color: #C5A059; animation: sfLobbySpin 0.85s linear infinite; }',
        '.sf-lobby-loading-label { font-size: 12px; font-weight: 800; letter-spacing: 0.16em; text-transform: uppercase; color: #9A7738; }',
        '.sf-lobby-floating-anchor { position: absolute; top: 0; left: 0; z-index: 20; will-change: transform; transition: opacity 180ms ease; pointer-events: none; }',
        '.sf-lobby-floating-card { position: relative; width: 276px; max-width: calc(100vw - 24px); background: rgba(255,255,255,0.96); border: 1px solid rgba(197,160,89,0.55); border-radius: 16px; padding: 14px 16px; box-shadow: 0 14px 34px rgba(17,24,39,0.12); color: #111827; pointer-events: auto; box-sizing: border-box; }',
        '.sf-lobby-floating-arrow { width: 12px; height: 12px; background: rgba(255,255,255,0.96); border-right: 1px solid rgba(197,160,89,0.55); border-bottom: 1px solid rgba(197,160,89,0.55); transform: rotate(45deg); margin: -6px auto 0; pointer-events: none; }',
        '.sf-lobby-portal-pill { pointer-events: auto; display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; background: rgba(255,255,255,0.96); color: #111827; border: 1px solid #C5A059; box-shadow: 0 14px 32px rgba(17,24,39,0.14); font-size: 12px; font-weight: 800; text-decoration: none; white-space: nowrap; }',
        '.sf-lobby-lava-chest-card { pointer-events: auto; display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 999px; background: rgba(255,255,255,0.97); color: #111827; border: 1.5px solid #C5A059; box-shadow: 0 14px 32px rgba(17,24,39,0.16); font-size: 12px; font-weight: 800; white-space: nowrap; cursor: pointer; text-decoration: none; }',
        '.sf-lobby-lava-chest-card.is-cta { background: #C5A059; color: #FFFFFF; }',
        '.sf-lobby-lava-chest-card.is-cta:hover { background: #B38E46; }',
        '.sf-lobby-abilities-dock { position: absolute; bottom: 78px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 8px; z-index: 23; pointer-events: auto; }',
        '.sf-ability-btn { position: relative; width: 48px; height: 48px; border-radius: 13px; background: rgba(255,255,255,0.95); border: 1.5px solid rgba(197,160,89,0.55); color: #111827; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 6px 18px rgba(17,24,39,0.12); transition: transform 0.12s ease, border-color 0.15s ease, background 0.15s ease; user-select: none; }',
        '.sf-ability-btn:hover { border-color: #C5A059; transform: translateY(-2px); background: #FFFFFF; }',
        '.sf-ability-btn.is-active { border-color: #C5A059; background: #FAF3E3; box-shadow: 0 6px 20px rgba(197,160,89,0.36); }',
        '.sf-ability-btn.is-cooldown { opacity: 0.56; border-color: rgba(156,163,175,0.55); }',
        '.sf-ability-icon { font-size: 16px; line-height: 1; color: #9A7738; font-weight: 900; }',
        '.sf-ability-key { position: absolute; top: 3px; left: 5px; font-size: 9px; font-weight: 800; color: #6B7280; line-height: 1; }',
        '.sf-ability-cd { position: absolute; bottom: 3px; right: 4px; font-size: 9px; font-weight: 800; color: #FFFFFF; background: rgba(17,24,39,0.85); padding: 1px 4px; border-radius: 5px; line-height: 1; }',
        '.sf-ability-combo-badge { position: absolute; top: -6px; right: -6px; background: #C5A059; color: #FFFFFF; font-size: 9px; font-weight: 900; padding: 2px 5px; border-radius: 999px; border: 1.5px solid #FFFFFF; line-height: 1; box-shadow: 0 3px 8px rgba(17,24,39,0.18); }',
        '.sf-lobby-settings-backdrop { position: absolute; inset: 0; z-index: 48; background: rgba(17,24,39,0.24); display: flex; align-items: center; justify-content: center; padding: 16px; pointer-events: auto; box-sizing: border-box; }',
        '.sf-lobby-settings-card { width: 392px; max-width: calc(100vw - 24px); max-height: calc(100dvh - 40px); overflow-y: auto; background: #FFFFFF; color: #111827; border: 1px solid rgba(197,160,89,0.55); border-radius: 20px; padding: 18px 20px; box-shadow: 0 22px 52px rgba(17,24,39,0.18); display: flex; flex-direction: column; gap: 14px; box-sizing: border-box; }',
        '.sf-lobby-settings-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-bottom: 10px; border-bottom: 1px solid rgba(197,160,89,0.28); }',
        '.sf-lobby-settings-title { margin: 0; font-size: 15px; font-weight: 800; color: #111827; display: flex; align-items: center; gap: 7px; }',
        '.sf-lobby-settings-section { background: #FAF8F5; border: 1px solid rgba(197,160,89,0.34); border-radius: 14px; padding: 11px 13px; display: flex; flex-direction: column; gap: 9px; }',
        '.sf-lobby-settings-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }',
        '.sf-lobby-settings-label { font-size: 12px; font-weight: 800; color: #111827; }',
        '.sf-lobby-slider { width: 100%; height: 6px; accent-color: #C5A059; cursor: pointer; }',
        '.sf-lobby-keybind-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; padding-top: 4px; }',
        '.sf-lobby-keybind-item { display: flex; align-items: center; justify-content: space-between; gap: 6px; padding: 6px 9px; border-radius: 10px; background: #FFFFFF; border: 1px solid rgba(197,160,89,0.35); cursor: pointer; font-size: 11px; font-weight: 700; color: #111827; text-align: left; }',
        '.sf-lobby-keybind-item:hover { border-color: #C5A059; }',
        '.sf-lobby-keybind-item.is-listening { border-color: #C5A059; background: #FAF3E3; }',
        '.sf-lobby-keybind-pill { padding: 2px 7px; border-radius: 6px; background: #FAF8F5; border: 1px solid rgba(197,160,89,0.48); font-size: 10px; font-weight: 800; color: #9A7738; white-space: nowrap; }',
        '.sf-lobby-stick-wrap { position: absolute; bottom: 22px; left: 20px; z-index: 22; }',
        '.sf-lobby-stick { width: 84px; height: 84px; border-radius: 999px; background: rgba(255,255,255,0.88); border: 1px solid rgba(197,160,89,0.52); box-shadow: 0 10px 26px rgba(17,24,39,0.12); display: flex; align-items: center; justify-content: center; position: relative; touch-action: none; cursor: pointer; }',
        '.sf-lobby-stick-knob { width: 34px; height: 34px; border-radius: 999px; background: #C5A059; border: 2px solid #FFFFFF; box-shadow: 0 4px 12px rgba(17,24,39,0.18); pointer-events: none; }',
        '.sf-lobby-quick-actions { position: absolute; bottom: 22px; right: 20px; z-index: 22; display: flex; align-items: center; gap: 8px; }',
        '.sf-lobby-icon-btn { position: relative; appearance: none; height: 44px; min-width: 44px; padding: 0 12px; border-radius: 14px; background: rgba(255,255,255,0.94); color: #111827; border: 1px solid rgba(197,160,89,0.55); box-shadow: 0 10px 24px rgba(17,24,39,0.10); font-size: 15px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }',
        '.sf-lobby-bottom-stack { position: absolute; bottom: 14px; left: 12px; right: 12px; z-index: 21; display: flex; flex-direction: column; align-items: center; gap: 7px; pointer-events: none; }',
        '.sf-lobby-bottom-pill { pointer-events: auto; display: inline-flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: rgba(255,255,255,0.94); border: 1px solid rgba(197,160,89,0.48); box-shadow: 0 10px 28px rgba(17,24,39,0.12); max-width: 100%; box-sizing: border-box; }',
        '.sf-lobby-step-btn { appearance: none; padding: 6px 12px; border-radius: 999px; border: 1px solid rgba(197,160,89,0.40); background: #FAF8F5; color: #111827; font-size: 11px; font-weight: 800; cursor: pointer; white-space: nowrap; }',
        '.sf-lobby-step-btn.is-active { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }',
        '.sf-lobby-exhibit-btn { appearance: none; padding: 6px 14px; border-radius: 999px; border: 1px solid rgba(197,160,89,0.35); background: #FFFFFF; color: #111827; font-size: 11px; font-weight: 800; cursor: pointer; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }',
        '.sf-lobby-hint-pill { pointer-events: none; padding: 5px 14px; border-radius: 999px; background: rgba(255,255,255,0.90); border: 1px solid rgba(197,160,89,0.38); color: #374151; font-size: 11px; font-weight: 700; box-shadow: 0 6px 18px rgba(17,24,39,0.08); white-space: nowrap; }',
        '@media (max-width: 767px) { .sf-lobby-topbar { padding: 10px 12px; gap: 8px; } .sf-lobby-zone-btn { padding: 6px 10px; font-size: 11px; } .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 6px 10px; font-size: 11px; } .sf-lobby-kbd, .sf-lobby-hint-pill { display: none !important; } .sf-lobby-stick-wrap { bottom: 72px; left: 12px; } .sf-lobby-stick { width: 74px; height: 74px; } .sf-lobby-quick-actions { bottom: 72px; right: 12px; } .sf-lobby-abilities-dock { bottom: 126px; gap: 6px; } .sf-ability-btn { width: 40px; height: 40px; border-radius: 10px; } .sf-lobby-exhibit-btn { max-width: 175px; } .sf-lobby-keybind-grid { grid-template-columns: 1fr; } }'
    ].join('\n');

    var FALLBACK_LOBBY_PRODUCTS = [
        {
            id: 'fallback-kristall',
            name: 'Der Seelen Kristall',
            slug: 'seelen-kristall',
            category_name: 'Glas & Kristall',
            price_formatted: '69,90 €',
            product_url: '/shop',
            is_personalizable: true,
            material_hint: 'crystal',
            benefits: [
                'Handveredelt in Deutschland (Manufaktur Gifhorn)',
                'Optisch reines K9 Kristallglas',
                'Feinste 3D-Lasergravur nach Wunsch'
            ]
        },
        {
            id: 'fallback-herz',
            name: 'Das Goldene Seelen-Herz',
            slug: 'goldenes-seelen-herz',
            category_name: 'Schmuck & Unikate',
            price_formatted: '39,90 €',
            product_url: '/shop',
            is_personalizable: true,
            material_hint: 'gold',
            benefits: [
                'Handveredelt in Deutschland (Manufaktur Gifhorn)',
                '14 Karat vergoldeter Edelstahl (Ion-Plating)',
                'Individuelle Wunschgravur inklusive'
            ]
        }
    ];

    // Private Closure-Speicher (0 Zugriff aus Browser-Konsole / window!)
    var privateFacadeMap = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
    var fallbackPrivateFacade = null;

    function getFacade(host) {
        if (privateFacadeMap) return privateFacadeMap.get(host) || null;
        return fallbackPrivateFacade;
    }

    function setFacade(host, facade) {
        if (privateFacadeMap) {
            if (facade) privateFacadeMap.set(host, facade);
            else privateFacadeMap.delete(host);
        } else {
            fallbackPrivateFacade = facade || null;
        }
    }

    function ensureScopedStyleSheet() {
        var existing = document.getElementById('sf-3d-lobby-scoped-css');
        if (!existing) {
            var styleTag = document.createElement('style');
            styleTag.id = 'sf-3d-lobby-scoped-css';
            styleTag.textContent = STANDALONE_SCOPED_CSS;
            (document.head || document.documentElement).appendChild(styleTag);
        } else if (existing.textContent && existing.textContent.indexOf('.sf-lobby-settings-card') === -1) {
            existing.textContent += '\n' + STANDALONE_SCOPED_CSS;
        }
    }

    function getActiveFullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
    }

    function sanitizeText(val) {
        return String(val || '').replace(/\s[\u2013\u2014]\s/g, ' · ').trim();
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function clampInt(val, min, max) {
        var n = parseInt(val, 10);
        if (isNaN(n)) return min;
        return Math.max(min, Math.min(max, n));
    }

    function fallbackKeyLabel(code) {
        var c = String(code || '').trim();
        if (!c) return '?';
        if (c === 'Space') return 'Leertaste';
        if (c.indexOf('Digit') === 0) return c.slice(5);
        if (c.indexOf('Numpad') === 0) return 'Num ' + c.slice(6);
        if (c.indexOf('Key') === 0) return c.slice(3).toUpperCase();
        return c;
    }

    function SeelenfunkeUnifiedLobbyHost() {
        this.isOpen = false;
        this.isFullscreen = false;
        this.modalEl = null;
        this.canvasEl = null;
        this.loadingEl = null;
        this.floatingProductWrap = null;
        this.floatingHeroWrap = null;
        this.floatingPortalWrap = null;
        this.floatingLavaChestWrap = null;
        this.floatingLabyrinthBuzzerWrap = null;

        this._mod = null;
        this._audio = null;
        this._audioUnsub = null;
        this._syncTimer = null;
        this._abilityTimer = null;
        this._syncInFlight = false;
        this._activeSyncZone = null;
        this._openSeq = 0;
        this._fsResizeTimer = null;
        this.prevHtmlOverflow = '';
        this.prevBodyOverflow = '';

        this.currentZone = 'gallery';
        this.stagesMode = 'live';
        this.products = [];
        this.activeIndex = 0;
        this.focusedProduct = null;
        this.focusedHero = null;
        this.heroesPayload = {
            founding_era: true,
            total_heroes: 0,
            my_hero_slot: null,
            customer_logged_in: false,
            customer_opt_in: false,
            level_models: {},
            heroes: []
        };
        this.funkiInfo = { is_guest: false, level: 2, name: '', weapon: null, player_id: null };

        this.lobbyAuth = false;
        this.soloMode = false;
        this.instanceId = 1;
        this.instanceCapacity = 20;
        this.instances = [{ id: 1, count: 1, capacity: 20, is_full: false, is_current: true }];
        this.peers = [];
        this.instanceMenuOpen = false;

        this.settingsOpen = false;
        this.keybindingsExpanded = false;
        this.listeningKeyAction = '';
        this.userSettings = {
            volume: 50,
            cameraSensitivity: 100,
            useRealName: false
        };
        this.keybindings = {
            sprint: 'Digit1',
            titanJump: 'Digit2',
            shield: 'Digit3',
            burst: 'Digit4',
            strike: 'KeyG',
            jump: 'Space',
            moveForward: 'KeyW',
            moveLeft: 'KeyA',
            moveBack: 'KeyS',
            moveRight: 'KeyD'
        };
        this.audioState = {
            bgmVolume: 30,
            bgmMuted: true,
            sfxVolume: 50,
            sfxMuted: false,
            allMuted: false
        };
        this.lavaChestState = {
            nearChest: false,
            isAuthenticated: false,
            claimedToday: false,
            claiming: false,
            canClaim: false,
            statusMessage: ''
        };
        this.labyrinthBuzzerState = {
            nearBuzzer: false,
            isAuthenticated: false,
            claimedToday: false,
            claiming: false,
            canClaim: false,
            statusMessage: ''
        };

        this.stickActive = false;
        this.stickKnobEl = null;
        this.stickZoneEl = null;

        var self = this;
        this._boundKeydown = function (e) {
            if (!self.isOpen) return;
            var key = e.key || '';
            var code = e.code || '';

            if (self.listeningKeyAction !== '') {
                e.preventDefault();
                e.stopPropagation();
                if (key === 'Escape' || code === 'Escape' || e.keyCode === 27) {
                    self.listeningKeyAction = '';
                    self._renderKeybindingsList();
                    return;
                }
                if (code) {
                    self._assignKeybinding(self.listeningKeyAction, code);
                }
                return;
            }

            if (key === 'Escape' || code === 'Escape' || e.keyCode === 27) {
                e.preventDefault();
                e.stopPropagation();
                if (self.settingsOpen) {
                    self._setSettingsOpen(false);
                } else if (getActiveFullscreenElement()) {
                    self.toggleFullscreen();
                } else if (self.instanceMenuOpen) {
                    self._setInstanceMenuOpen(false);
                } else {
                    self.close();
                }
                return;
            }

            var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

            if (!self.settingsOpen && (code === 'KeyF' || key === 'f' || key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey) {
                var fBound = false;
                for (var k in self.keybindings) {
                    if (Object.prototype.hasOwnProperty.call(self.keybindings, k) && self.keybindings[k] === 'KeyF') {
                        fBound = true;
                        break;
                    }
                }
                if (!fBound) {
                    e.preventDefault();
                    self.toggleFullscreen();
                }
            }
        };

        this._boundFullscreenChange = function () {
            self.isFullscreen = Boolean(getActiveFullscreenElement());
            self._syncHudState();
            var facade = getFacade(self);
            if (facade) {
                facade.resize();
                requestAnimationFrame(function () {
                    var f2 = getFacade(self);
                    if (f2) f2.resize();
                });
                if (self._fsResizeTimer) clearTimeout(self._fsResizeTimer);
                self._fsResizeTimer = setTimeout(function () {
                    var f3 = getFacade(self);
                    if (f3) f3.resize();
                }, 120);
            }
        };

        this._boundOutsideClick = function (e) {
            if (!self.instanceMenuOpen || !self.modalEl) return;
            var wrap = self.modalEl.querySelector('[data-lobby-instance-wrap]');
            if (wrap && !wrap.contains(e.target)) {
                self._setInstanceMenuOpen(false);
            }
        };

        this._boundPageHide = function () {
            if (self.isOpen) {
                self._sendLeaveBeacon();
                self.dispose();
            }
        };
    }

    SeelenfunkeUnifiedLobbyHost.prototype._keyLabel = function (actionKey) {
        var code = (this.keybindings && this.keybindings[actionKey]) ? this.keybindings[actionKey] : '';
        if (this._mod && typeof this._mod.formatLobbyKeyLabel === 'function') {
            return this._mod.formatLobbyKeyLabel(code);
        }
        return fallbackKeyLabel(code);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._resolveInstanceBtnIds = function () {
        var existingBtn = document.getElementById('sf-lobby-instance-btn');
        var existingMenu = document.getElementById('sf-lobby-instance-menu');
        return {
            btnId: existingBtn ? 'sf-account-lobby-instance-btn' : 'sf-lobby-instance-btn',
            menuId: existingMenu ? 'sf-account-lobby-instance-menu' : 'sf-lobby-instance-menu'
        };
    };

    SeelenfunkeUnifiedLobbyHost.prototype._ensureModalDom = function () {
        ensureScopedStyleSheet();

        if (this.modalEl) {
            if (!document.body.contains(this.modalEl)) {
                document.body.appendChild(this.modalEl);
            }
            return;
        }

        var ids = this._resolveInstanceBtnIds();
        var root = document.createElement('div');
        root.id = 'sf-user-lobby-3d-modal';
        root.className = 'sf-lobby-modal is-closed';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-label', 'Mein Seelenfunke 3D-Lobby und Halle der Helden');
        root.style.cssText = 'position:fixed !important;top:0 !important;left:0 !important;right:0 !important;bottom:0 !important;width:100vw !important;height:100dvh !important;z-index:2147483000 !important;background:#FAF8F5 !important;overflow:hidden !important;margin:0 !important;padding:0 !important;';

        root.innerHTML = [
            '<div class="sf-lobby-topbar">',
            '  <div class="sf-lobby-nav-pill">',
            '    <button type="button" data-lobby-zone="gallery" data-testid="lobby-zone-gallery-btn" class="sf-lobby-zone-btn is-active">🛍️ Kollektion</button>',
            '    <button type="button" data-lobby-zone="heroes" data-testid="lobby-zone-heroes-btn" class="sf-lobby-zone-btn"><span data-lobby-guest-lock style="display:none;">🔒</span><span>🏛️ Halle der Helden</span></button>',
            '    <button type="button" data-lobby-zone="lava" data-testid="lobby-zone-lava-btn" class="sf-lobby-zone-btn">🌋 Magma-Kammer</button>',
            '    <button type="button" data-lobby-zone="labyrinth" data-testid="lobby-zone-labyrinth-btn" class="sf-lobby-zone-btn">🌀 Labyrinth</button>',
            '    <button type="button" data-lobby-stages-btn data-testid="lobby-stages-preview-btn" class="sf-lobby-stages-btn" style="display:none;">✨ Stufen</button>',
            '  </div>',
            '  <div class="sf-lobby-actions">',
            '    <button type="button" data-lobby-solo-toggle data-testid="louvre-live-solo-toggle" class="sf-lobby-pill-btn" style="display:none;">',
            '      <span data-lobby-solo-dot class="sf-lobby-dot is-live"></span>',
            '      <span data-lobby-solo-label>Live</span>',
            '    </button>',
            '    <div data-lobby-instance-wrap class="sf-lobby-instance-wrap" style="display:none;">',
            '      <button type="button" id="' + ids.btnId + '" data-lobby-instance-toggle class="sf-lobby-pill-btn">',
            '        <span data-lobby-instance-label>🌐 Instanz #1 · 1/20 ▾</span>',
            '      </button>',
            '      <div id="' + ids.menuId + '" data-lobby-instance-popover class="sf-lobby-instance-popover" style="display:none;">',
            '        <div data-lobby-instance-list class="sf-lobby-instance-list"></div>',
            '        <form data-lobby-instance-form class="sf-lobby-instance-form">',
            '          <span style="font-size:11px;font-weight:800;color:#111827;white-space:nowrap;">Instanz #</span>',
            '          <input type="number" data-lobby-instance-input min="1" max="50" value="1" class="sf-lobby-instance-input" aria-label="Instanz Nummer">',
            '          <button type="submit" class="sf-lobby-instance-submit">Wechseln</button>',
            '        </form>',
            '      </div>',
            '    </div>',
            '    <button type="button" data-lobby-audio-mute data-testid="lobby-audio-mute-btn" class="sf-lobby-pill-btn" title="Ton umschalten">🔊</button>',
            '    <button type="button" data-lobby-settings-toggle data-testid="louvre-settings-btn" class="sf-lobby-pill-btn" title="Einstellungen">⚙️</button>',
            '    <button type="button" data-lobby-fullscreen data-testid="lobby-fullscreen-btn" class="sf-lobby-pill-btn" title="Vollbild umschalten (Taste F)">⛶ Vollbild</button>',
            '    <button type="button" data-lobby-close data-testid="lobby-close-btn" class="sf-lobby-close-btn" title="3D-Lobby schließen (Esc)">',
            '      <span>✕ Schließen</span>',
            '      <span class="sf-lobby-kbd">Esc</span>',
            '    </button>',
            '  </div>',
            '</div>',
            '<div class="sf-lobby-stage">',
            '  <canvas data-lobby-canvas class="sf-lobby-canvas"></canvas>',
            '  <div data-lobby-loading class="sf-lobby-loading">',
            '    <div class="sf-lobby-spinner"></div>',
            '    <span data-lobby-loading-text class="sf-lobby-loading-label">3D-Lobby wird geöffnet</span>',
            '  </div>',
            '  <div data-lobby-product-card class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div data-lobby-product-inner class="sf-lobby-floating-card"></div>',
            '    <div class="sf-lobby-floating-arrow"></div>',
            '  </div>',
            '  <div data-lobby-hero-card class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div data-lobby-hero-inner class="sf-lobby-floating-card"></div>',
            '    <div class="sf-lobby-floating-arrow"></div>',
            '  </div>',
            '  <div data-lobby-portal-card class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <a href="/login" class="sf-lobby-portal-pill">🔒 Mitglieder-Bereich · Anmelden →</a>',
            '  </div>',
            '  <div data-lobby-lava-chest-card data-testid="lobby-lava-chest-card" class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div data-lobby-lava-chest-inner style="display:flex;flex-direction:column;align-items:center;gap:6px;"></div>',
            '    <div class="sf-lobby-floating-arrow"></div>',
            '  </div>',
            '  <div data-lobby-labyrinth-buzzer-card data-testid="lobby-labyrinth-buzzer-card" class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div data-lobby-labyrinth-buzzer-inner style="display:flex;flex-direction:column;align-items:center;gap:6px;"></div>',
            '    <div class="sf-lobby-floating-arrow"></div>',
            '  </div>',
            '  <div data-lobby-settings-modal data-testid="lobby-settings-modal" class="sf-lobby-settings-backdrop" style="display:none;">',
            '    <div class="sf-lobby-settings-card" role="dialog" aria-label="Einstellungen">',
            '      <div class="sf-lobby-settings-head">',
            '        <h3 class="sf-lobby-settings-title"><span>⚙️</span><span>Einstellungen</span></h3>',
            '        <button type="button" data-lobby-settings-close style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;" aria-label="Schließen">&times;</button>',
            '      </div>',
            '      <div class="sf-lobby-settings-section">',
            '        <div class="sf-lobby-settings-row">',
            '          <span class="sf-lobby-settings-label">Lautstärke</span>',
            '          <span data-lobby-volume-label style="font-size:11px;font-weight:800;color:#9A7738;">50 %</span>',
            '        </div>',
            '        <input type="range" min="0" max="100" step="1" value="50" data-lobby-volume-slider data-testid="lobby-settings-volume-slider" class="sf-lobby-slider" aria-label="Lautstärke">',
            '      </div>',
            '      <div class="sf-lobby-settings-section">',
            '        <div class="sf-lobby-settings-row">',
            '          <span class="sf-lobby-settings-label">Kamera-Tempo</span>',
            '          <span data-lobby-camera-label style="font-size:11px;font-weight:800;color:#9A7738;">100 %</span>',
            '        </div>',
            '        <input type="range" min="50" max="150" step="1" value="100" data-lobby-camera-slider data-testid="lobby-camera-sensitivity-slider" class="sf-lobby-slider" aria-label="Kamera-Tempo">',
            '      </div>',
            '      <div class="sf-lobby-settings-section">',
            '        <label class="sf-lobby-settings-row" style="cursor:pointer;">',
            '          <span class="sf-lobby-settings-label">Echten Vornamen nutzen</span>',
            '          <input type="checkbox" data-lobby-realname-checkbox data-testid="lobby-use-real-name-checkbox" style="width:17px;height:17px;accent-color:#C5A059;cursor:pointer;">',
            '        </label>',
            '      </div>',
            '      <div class="sf-lobby-settings-section">',
            '        <div class="sf-lobby-settings-row">',
            '          <span class="sf-lobby-settings-label">Steuerung</span>',
            '          <div style="display:flex;align-items:center;gap:6px;">',
            '            <button type="button" data-lobby-keybind-reset data-testid="lobby-keybind-reset-btn" class="sf-lobby-step-btn" style="display:none;">Zurücksetzen</button>',
            '            <button type="button" data-lobby-keybind-expand data-testid="lobby-keybind-expand-btn" class="sf-lobby-step-btn">Ändern</button>',
            '          </div>',
            '        </div>',
            '        <div data-lobby-keybind-grid class="sf-lobby-keybind-grid" style="display:none;"></div>',
            '      </div>',
            '    </div>',
            '  </div>',
            '  <div class="sf-lobby-stick-wrap">',
            '    <div data-lobby-stick class="sf-lobby-stick" aria-label="Bewegungssteuerung">',
            '      <div data-lobby-stick-knob class="sf-lobby-stick-knob"></div>',
            '    </div>',
            '  </div>',
            '  <div class="sf-lobby-quick-actions">',
            '    <button type="button" data-lobby-jump class="sf-lobby-icon-btn" title="Springen (Leertaste)" aria-label="Springen">⤒</button>',
            '    <button type="button" data-lobby-strike class="sf-lobby-icon-btn" style="color:#9A7738;" title="Waffen-Effekt (G)" aria-label="Waffen-Effekt">',
            '      <span>✦</span>',
            '      <span data-ability-combo-badge class="sf-ability-combo-badge" style="display:none;">x1</span>',
            '    </button>',
            '  </div>',
            '  <div data-lobby-abilities-dock data-testid="louvre-abilities-dock" class="sf-lobby-abilities-dock">',
            '    <button type="button" data-ability-slot="1" data-testid="ability-slot-1-btn" class="sf-ability-btn" title="Funken-Sprint">',
            '      <span class="sf-ability-icon">⚡</span>',
            '      <span data-ability-key="sprint" class="sf-ability-key">1</span>',
            '      <span data-ability-cd="1" class="sf-ability-cd" style="display:none;"></span>',
            '    </button>',
            '    <button type="button" data-ability-slot="2" data-testid="ability-slot-2-btn" class="sf-ability-btn" title="Titan-Sprung">',
            '      <span class="sf-ability-icon">▲</span>',
            '      <span data-ability-key="titanJump" class="sf-ability-key">2</span>',
            '      <span data-ability-cd="2" class="sf-ability-cd" style="display:none;"></span>',
            '    </button>',
            '    <button type="button" data-ability-slot="3" data-testid="ability-slot-3-btn" class="sf-ability-btn" title="Goldener Schutzschild">',
            '      <span class="sf-ability-icon">🛡</span>',
            '      <span data-ability-key="shield" class="sf-ability-key">3</span>',
            '      <span data-ability-cd="3" class="sf-ability-cd" style="display:none;"></span>',
            '    </button>',
            '    <button type="button" data-ability-slot="4" data-testid="ability-slot-4-btn" class="sf-ability-btn" title="Funkenstoß">',
            '      <span class="sf-ability-icon">✦</span>',
            '      <span data-ability-key="burst" class="sf-ability-key">4</span>',
            '      <span data-ability-cd="4" class="sf-ability-cd" style="display:none;"></span>',
            '    </button>',
            '  </div>',
            '  <div class="sf-lobby-bottom-stack">',
            '    <div data-lobby-bottom-gallery class="sf-lobby-bottom-pill">',
            '      <button type="button" data-lobby-prev class="sf-lobby-step-btn">‹</button>',
            '      <button type="button" data-lobby-exhibit-pill class="sf-lobby-exhibit-btn">Kollektion</button>',
            '      <button type="button" data-lobby-next class="sf-lobby-step-btn">›</button>',
            '    </div>',
            '    <div data-lobby-bottom-heroes class="sf-lobby-bottom-pill" style="display:none;">',
            '      <button type="button" data-hero-slot="1" class="sf-lobby-step-btn">#1</button>',
            '      <button type="button" data-hero-slot="2" class="sf-lobby-step-btn">#2</button>',
            '      <button type="button" data-hero-slot="3" class="sf-lobby-step-btn">#3</button>',
            '      <button type="button" data-hero-slot="4" class="sf-lobby-step-btn">#4</button>',
            '      <button type="button" data-hero-slot="5" class="sf-lobby-step-btn">#5</button>',
            '      <button type="button" data-hero-my-place class="sf-lobby-step-btn is-active">⭐ Mein Platz</button>',
            '    </div>',
            '    <div data-lobby-bottom-lava class="sf-lobby-bottom-pill" style="display:none;">',
            '      <span style="padding:4px 10px;font-size:11px;font-weight:800;color:#9A7738;">🌋 Magma-Kammer · +5 ✨ Täglich</span>',
            '      <button type="button" data-lava-back-gallery class="sf-lobby-step-btn">Zur Galerie</button>',
            '    </div>',
            '    <div data-lobby-bottom-labyrinth class="sf-lobby-bottom-pill" style="display:none;">',
            '      <span style="padding:4px 10px;font-size:11px;font-weight:800;color:#9A7738;">🌀 Mini-Labyrinth · 3 ✨ Täglich</span>',
            '      <button type="button" data-labyrinth-to-heroes class="sf-lobby-step-btn is-active">🏛️ Heldenhalle</button>',
            '      <button type="button" data-labyrinth-back-gallery class="sf-lobby-step-btn">Zur Galerie</button>',
            '    </div>',
            '    <div class="sf-lobby-hint-pill">WASD / Pfeiltasten · Maus ziehen · Leertaste Sprung · 1 bis 4 Fähigkeiten · ⛶ Vollbild · Esc Schließen</div>',
            '  </div>',
            '</div>'
        ].join('');

        document.body.appendChild(root);
        this.modalEl = root;
        this.canvasEl = root.querySelector('[data-lobby-canvas]');
        this.loadingEl = root.querySelector('[data-lobby-loading]');
        this.floatingProductWrap = root.querySelector('[data-lobby-product-card]');
        this.floatingHeroWrap = root.querySelector('[data-lobby-hero-card]');
        this.floatingPortalWrap = root.querySelector('[data-lobby-portal-card]');
        this.floatingLavaChestWrap = root.querySelector('[data-lobby-lava-chest-card]');
        this.floatingLabyrinthBuzzerWrap = root.querySelector('[data-lobby-labyrinth-buzzer-card]');
        this.stickZoneEl = root.querySelector('[data-lobby-stick]');
        this.stickKnobEl = root.querySelector('[data-lobby-stick-knob]');

        this._bindModalUiEvents();
        this._renderKeybindingsList();
        this._renderLavaChestCard();
        this._renderLabyrinthBuzzerCard();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._setSettingsOpen = function (flag) {
        this.settingsOpen = Boolean(flag);
        if (!this.settingsOpen) {
            this.listeningKeyAction = '';
        }
        if (this.modalEl) {
            var modal = this.modalEl.querySelector('[data-lobby-settings-modal]');
            if (modal) {
                modal.style.display = this.settingsOpen ? 'flex' : 'none';
            }
        }
        this._syncHudState();
        this._renderKeybindingsList();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._assignKeybinding = function (actionKey, code) {
        if (!actionKey || !code) return;
        var next = {};
        for (var k in this.keybindings) {
            if (Object.prototype.hasOwnProperty.call(this.keybindings, k)) {
                next[k] = this.keybindings[k];
            }
        }
        next[actionKey] = code;
        var facade = getFacade(this);
        if (facade && typeof facade.updateSettings === 'function') {
            facade.updateSettings({ keybindings: next });
            var snap = facade.getHudSnapshot();
            if (snap && snap.keybindings) {
                this.keybindings = snap.keybindings;
            } else {
                this.keybindings = next;
            }
        } else if (this._mod && typeof this._mod.saveLobbyKeybindings === 'function') {
            this.keybindings = this._mod.saveLobbyKeybindings(next);
        } else {
            this.keybindings = next;
        }
        this.listeningKeyAction = '';
        if (this._audio) this._audio.playUiSwitch();
        this._renderKeybindingsList();
        this._syncHudState();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._resetKeybindings = function () {
        var facade = getFacade(this);
        if (facade && typeof facade.updateSettings === 'function') {
            facade.updateSettings({ resetKeybindings: true });
            var snap = facade.getHudSnapshot();
            if (snap && snap.keybindings) {
                this.keybindings = snap.keybindings;
            }
        } else if (this._mod && typeof this._mod.resetLobbyKeybindings === 'function') {
            this.keybindings = this._mod.resetLobbyKeybindings();
        }
        this.listeningKeyAction = '';
        if (this._audio) this._audio.playUiSwitch();
        this._renderKeybindingsList();
        this._syncHudState();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderKeybindingsList = function () {
        if (!this.modalEl) return;
        var grid = this.modalEl.querySelector('[data-lobby-keybind-grid]');
        var resetBtn = this.modalEl.querySelector('[data-lobby-keybind-reset]');
        var expandBtn = this.modalEl.querySelector('[data-lobby-keybind-expand]');
        if (!grid) return;

        grid.style.display = this.keybindingsExpanded ? 'grid' : 'none';
        if (resetBtn) {
            resetBtn.style.display = this.keybindingsExpanded ? 'inline-flex' : 'none';
        }
        if (expandBtn) {
            expandBtn.textContent = this.keybindingsExpanded ? 'Fertig' : 'Ändern';
            if (this.keybindingsExpanded) {
                expandBtn.classList.add('is-active');
            } else {
                expandBtn.classList.remove('is-active');
            }
        }

        while (grid.firstChild) {
            grid.removeChild(grid.firstChild);
        }

        var self = this;
        for (var i = 0; i < KEYBIND_ACTIONS.length; i++) {
            (function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                var isListening = (self.listeningKeyAction === item.key);
                btn.className = 'sf-lobby-keybind-item' + (isListening ? ' is-listening' : '');

                var lbl = document.createElement('span');
                lbl.textContent = item.label;
                var pill = document.createElement('span');
                pill.className = 'sf-lobby-keybind-pill';
                pill.textContent = isListening ? 'Taste...' : self._keyLabel(item.key);

                btn.appendChild(lbl);
                btn.appendChild(pill);
                btn.addEventListener('click', function () {
                    self.listeningKeyAction = (self.listeningKeyAction === item.key) ? '' : item.key;
                    self._renderKeybindingsList();
                });
                grid.appendChild(btn);
            })(KEYBIND_ACTIONS[i]);
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderLavaChestCard = function () {
        if (!this.modalEl) return;
        var inner = this.modalEl.querySelector('[data-lobby-lava-chest-inner]');
        if (!inner) return;
        while (inner.firstChild) inner.removeChild(inner.firstChild);

        var self = this;
        var st = this.lavaChestState || {};

        if (!this.lobbyAuth) {
            var loginLink = document.createElement('a');
            loginLink.href = '/login';
            loginLink.className = 'sf-lobby-lava-chest-card';
            loginLink.textContent = '🔒 Magma-Truhe · Anmelden →';
            inner.appendChild(loginLink);
            return;
        }

        if (st.claimedToday) {
            var donePill = document.createElement('div');
            donePill.className = 'sf-lobby-lava-chest-card';
            donePill.textContent = sanitizeText(st.statusMessage || 'Heute bereits geöffnet ✓');
            inner.appendChild(donePill);
            return;
        }

        var titleBadge = document.createElement('span');
        titleBadge.style.cssText = 'pointer-events:none;padding:4px 11px;border-radius:999px;background:rgba(255,255,255,0.96);border:1px solid rgba(197,160,89,0.55);font-size:11px;font-weight:800;color:#111827;';
        titleBadge.textContent = 'Magma-Truhe · +5 ✨ Funken';
        inner.appendChild(titleBadge);

        var claimBtn = document.createElement('button');
        claimBtn.type = 'button';
        claimBtn.className = 'sf-lobby-lava-chest-card is-cta';
        claimBtn.disabled = Boolean(st.claiming);
        claimBtn.textContent = st.claiming ? 'Truhe wird geöffnet...' : 'Truhe öffnen';
        claimBtn.addEventListener('click', function () {
            var facade = getFacade(self);
            if (facade && typeof facade.claimLavaChest === 'function') {
                facade.claimLavaChest();
            }
        });
        inner.appendChild(claimBtn);

        if (st.statusMessage && !st.claiming) {
            var note = document.createElement('span');
            note.style.cssText = 'pointer-events:none;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,0.95);border:1px solid rgba(197,160,89,0.5);font-size:11px;font-weight:700;color:#111827;';
            note.textContent = sanitizeText(st.statusMessage);
            inner.appendChild(note);
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderLabyrinthBuzzerCard = function () {
        if (!this.modalEl) return;
        var inner = this.modalEl.querySelector('[data-lobby-labyrinth-buzzer-inner]');
        if (!inner) return;
        while (inner.firstChild) inner.removeChild(inner.firstChild);

        var self = this;
        var st = this.labyrinthBuzzerState || {};

        if (!this.lobbyAuth) {
            var guestConfettiBtn = document.createElement('button');
            guestConfettiBtn.type = 'button';
            guestConfettiBtn.className = 'sf-lobby-lava-chest-card is-cta';
            guestConfettiBtn.textContent = '🎉 Konfetti zünden';
            guestConfettiBtn.addEventListener('click', function () {
                var facade = getFacade(self);
                if (facade && typeof facade.claimLabyrinthBuzzer === 'function') {
                    facade.claimLabyrinthBuzzer();
                }
            });
            inner.appendChild(guestConfettiBtn);

            var loginLink = document.createElement('a');
            loginLink.href = '/login';
            loginLink.className = 'sf-lobby-lava-chest-card';
            loginLink.textContent = '🔒 Anmelden für +3 ✨ →';
            inner.appendChild(loginLink);
            return;
        }

        if (st.claimedToday) {
            var donePill = document.createElement('div');
            donePill.className = 'sf-lobby-lava-chest-card';
            donePill.textContent = sanitizeText(st.statusMessage || 'Heute bereits geholt ✓');
            inner.appendChild(donePill);

            var funBtn = document.createElement('button');
            funBtn.type = 'button';
            funBtn.className = 'sf-lobby-lava-chest-card is-cta';
            funBtn.textContent = '🎉 Konfetti zünden';
            funBtn.addEventListener('click', function () {
                var facade = getFacade(self);
                if (facade && typeof facade.claimLabyrinthBuzzer === 'function') {
                    facade.claimLabyrinthBuzzer();
                }
            });
            inner.appendChild(funBtn);
            return;
        }

        var titleBadge = document.createElement('span');
        titleBadge.style.cssText = 'pointer-events:none;padding:4px 11px;border-radius:999px;background:rgba(255,255,255,0.96);border:1px solid rgba(197,160,89,0.55);font-size:11px;font-weight:800;color:#111827;';
        titleBadge.textContent = '🌀 Mini-Labyrinth · +3 ✨';
        inner.appendChild(titleBadge);

        var buzzerBtn = document.createElement('button');
        buzzerBtn.type = 'button';
        buzzerBtn.className = 'sf-lobby-lava-chest-card is-cta';
        buzzerBtn.disabled = Boolean(st.claiming);
        buzzerBtn.textContent = st.claiming ? 'Konfetti startet...' : '🎉 Buzzer drücken';
        buzzerBtn.addEventListener('click', function () {
            var facade = getFacade(self);
            if (facade && typeof facade.claimLabyrinthBuzzer === 'function') {
                facade.claimLabyrinthBuzzer();
            }
        });
        inner.appendChild(buzzerBtn);

        if (st.statusMessage && !st.claiming) {
            var note = document.createElement('span');
            note.style.cssText = 'pointer-events:none;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,0.95);border:1px solid rgba(197,160,89,0.5);font-size:11px;font-weight:700;color:#111827;';
            note.textContent = sanitizeText(st.statusMessage);
            inner.appendChild(note);
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._syncAbilityHud = function () {
        var facade = getFacade(this);
        if (!this.modalEl || !facade || typeof facade.getAbilityState !== 'function') return;
        var root = this.modalEl;
        var dock = root.querySelector('[data-lobby-abilities-dock]');
        if (dock) {
            dock.style.display = 'flex';
        }

        var st = facade.getAbilityState();
        var setSlotUi = function (slotNum, activeFlag, cdSec) {
            var btn = root.querySelector('[data-ability-slot="' + slotNum + '"]');
            var cdEl = root.querySelector('[data-ability-cd="' + slotNum + '"]');
            if (btn) {
                if (activeFlag) btn.classList.add('is-active');
                else btn.classList.remove('is-active');
                if (cdSec > 0) btn.classList.add('is-cooldown');
                else btn.classList.remove('is-cooldown');
            }
            if (cdEl) {
                if (cdSec > 0) {
                    cdEl.style.display = 'inline-block';
                    cdEl.textContent = Math.ceil(cdSec) + 's';
                } else {
                    cdEl.style.display = 'none';
                }
            }
        };

        setSlotUi(1, Boolean(st.sprint && st.sprint.active), st.sprint ? st.sprint.cooldown : 0);
        setSlotUi(2, false, st.titanJump ? st.titanJump.cooldown : 0);
        setSlotUi(3, Boolean(st.shield && st.shield.active), st.shield ? st.shield.cooldown : 0);
        setSlotUi(4, false, st.burst ? st.burst.cooldown : 0);

        var comboStage = (st.combo && st.combo.stage) ? st.combo.stage : 0;
        var badge = root.querySelector('[data-ability-combo-badge]');
        if (badge) {
            if (comboStage > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = 'x' + comboStage;
            } else {
                badge.style.display = 'none';
            }
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype.toggleFullscreen = function () {
        if (!this.modalEl) return;
        var self = this;
        var activeFs = getActiveFullscreenElement();
        if (activeFs) {
            try {
                if (typeof document.exitFullscreen === 'function') {
                    Promise.resolve(document.exitFullscreen()).catch(function () {});
                } else if (typeof document.webkitExitFullscreen === 'function') {
                    document.webkitExitFullscreen();
                }
            } catch (err) {
                // Vollbild bereits verlassen
            }
            return;
        }

        try {
            if (typeof this.modalEl.requestFullscreen === 'function') {
                Promise.resolve(this.modalEl.requestFullscreen({ navigationUI: 'hide' }))
                    .then(function () {
                        self.isFullscreen = true;
                        self._syncHudState();
                    })
                    .catch(function () {});
            } else if (typeof this.modalEl.webkitRequestFullscreen === 'function') {
                this.modalEl.webkitRequestFullscreen();
                this.isFullscreen = true;
                this._syncHudState();
            }
        } catch (err2) {
            // Browser blockiert Fullscreen ohne Geste
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._bindModalUiEvents = function () {
        var self = this;
        var root = this.modalEl;
        if (!root) return;

        root.querySelector('[data-lobby-close]').addEventListener('click', function () {
            self.close();
        });

        root.querySelector('[data-lobby-fullscreen]').addEventListener('click', function () {
            self.toggleFullscreen();
        });

        root.querySelector('[data-lobby-solo-toggle]').addEventListener('click', function () {
            if (!self.lobbyAuth) return;
            self.soloMode = !self.soloMode;
            self._setInstanceMenuOpen(false);
            var facade = getFacade(self);
            if (facade && typeof facade.setSoloMode === 'function') {
                facade.setSoloMode(self.soloMode);
            }
            if (self.soloMode) {
                self.peers = [];
                self._activeSyncZone = null;
                self._stopSyncLoop();
                self._sendLeaveBeacon();
            } else if (self.currentZone === 'heroes') {
                self._activeSyncZone = null;
                self._handleZoneSyncTransition('heroes');
            }
            self._syncHudState();
        });

        root.querySelector('[data-lobby-audio-mute]').addEventListener('click', function () {
            if (self._audio && typeof self._audio.toggleQuickMute === 'function') {
                self.audioState = self._audio.toggleQuickMute();
                self._syncHudState();
            }
        });

        root.querySelector('[data-lobby-settings-toggle]').addEventListener('click', function () {
            if (self._audio) self._audio.playUiClick();
            self._setSettingsOpen(!self.settingsOpen);
        });

        root.querySelector('[data-lobby-settings-close]').addEventListener('click', function () {
            self._setSettingsOpen(false);
        });

        root.querySelector('[data-lobby-settings-modal]').addEventListener('click', function (ev) {
            if (ev.target === ev.currentTarget) {
                self._setSettingsOpen(false);
            }
        });

        root.querySelector('[data-lobby-volume-slider]').addEventListener('input', function (ev) {
            var vol = clampInt(ev.target.value, 0, 100);
            self.userSettings.volume = vol;
            var facade = getFacade(self);
            if (facade && typeof facade.updateSettings === 'function') {
                facade.updateSettings({ volume: vol });
            } else if (self._mod && typeof this._mod !== 'undefined') {
                // Fallback
            }
            if (self._audio) {
                self._audio.setSfxVolume(vol);
                self._audio.setSfxMuted(vol <= 0);
                self.audioState = self._audio.getState();
            }
            self._syncHudState();
        });

        root.querySelector('[data-lobby-camera-slider]').addEventListener('input', function (ev) {
            var sens = clampInt(ev.target.value, 50, 150);
            self.userSettings.cameraSensitivity = sens;
            var facade = getFacade(self);
            if (facade && typeof facade.updateSettings === 'function') {
                facade.updateSettings({ cameraSensitivity: sens });
            } else if (self._mod && typeof self._mod.saveLobbyUserSettings === 'function') {
                self._mod.saveLobbyUserSettings({ cameraSensitivity: sens });
            }
            self._syncHudState();
        });

        root.querySelector('[data-lobby-realname-checkbox]').addEventListener('change', function (ev) {
            var useReal = Boolean(ev.target.checked);
            self.userSettings.useRealName = useReal;
            var facade = getFacade(self);
            if (facade && typeof facade.updateSettings === 'function') {
                facade.updateSettings({ useRealName: useReal });
            } else if (self._mod && typeof self._mod.saveLobbyUserSettings === 'function') {
                self._mod.saveLobbyUserSettings({ useRealName: useReal });
            }
            self._syncHudState();
        });

        root.querySelector('[data-lobby-keybind-expand]').addEventListener('click', function () {
            self.keybindingsExpanded = !self.keybindingsExpanded;
            if (!self.keybindingsExpanded) self.listeningKeyAction = '';
            if (self._audio) self._audio.playUiClick();
            self._renderKeybindingsList();
        });

        root.querySelector('[data-lobby-keybind-reset]').addEventListener('click', function () {
            self._resetKeybindings();
        });

        var zoneBtns = root.querySelectorAll('[data-lobby-zone]');
        for (var z = 0; z < zoneBtns.length; z++) {
            zoneBtns[z].addEventListener('click', function (ev) {
                var targetZone = ev.currentTarget.getAttribute('data-lobby-zone');
                if (self._audio) self._audio.playUiClick();
                var facade = getFacade(self);
                if (facade) {
                    facade.setZone(targetZone, false);
                    var snap = facade.getHudSnapshot();
                    self.currentZone = snap ? snap.zone : targetZone;
                } else {
                    self.currentZone = targetZone;
                }
                self._handleZoneSyncTransition(self.currentZone);
                self._syncHudState();
            });
        }

        root.querySelector('[data-lava-back-gallery]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (facade) {
                facade.setZone('gallery', false);
                var snap = facade.getHudSnapshot();
                self.currentZone = snap ? snap.zone : 'gallery';
                self._handleZoneSyncTransition(self.currentZone);
                self._syncHudState();
            }
        });

        root.querySelector('[data-labyrinth-back-gallery]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (facade) {
                facade.setZone('gallery', false);
                var snap = facade.getHudSnapshot();
                self.currentZone = snap ? snap.zone : 'gallery';
                self._handleZoneSyncTransition(self.currentZone);
                self._syncHudState();
            }
        });

        root.querySelector('[data-labyrinth-to-heroes]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (facade) {
                facade.setZone('heroes', true);
                var snap = facade.getHudSnapshot();
                self.currentZone = snap ? snap.zone : 'heroes';
                self._handleZoneSyncTransition(self.currentZone);
                self._syncHudState();
            }
        });

        var stagesBtn = root.querySelector('[data-lobby-stages-btn]');
        stagesBtn.addEventListener('click', function () {
            var facade = getFacade(self);
            if (!facade) return;
            self.stagesMode = facade.cyclePreviewHeroLevel();
            self._syncHudState();
        });

        var instToggle = root.querySelector('[data-lobby-instance-toggle]');
        instToggle.addEventListener('click', function (ev) {
            ev.stopPropagation();
            self._setInstanceMenuOpen(!self.instanceMenuOpen);
        });

        var instForm = root.querySelector('[data-lobby-instance-form]');
        instForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var inp = root.querySelector('[data-lobby-instance-input]');
            var targetId = clampInt(inp ? inp.value : 1, 1, 50);
            self.switchInstance(targetId);
        });

        var abilityBtns = root.querySelectorAll('[data-ability-slot]');
        for (var ab = 0; ab < abilityBtns.length; ab++) {
            abilityBtns[ab].addEventListener('pointerdown', function (ev) {
                ev.stopPropagation();
            });
            abilityBtns[ab].addEventListener('click', function (ev) {
                var sl = parseInt(ev.currentTarget.getAttribute('data-ability-slot'), 10);
                var facade = getFacade(self);
                if (facade && typeof facade.triggerAbility === 'function') {
                    facade.triggerAbility(sl);
                    self._syncAbilityHud();
                }
            });
        }

        root.querySelector('[data-lobby-jump]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (facade) facade.triggerJump();
        });

        root.querySelector('[data-lobby-strike]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (!facade) return;
            facade.triggerWeapon();
            self._syncAbilityHud();
        });

        root.querySelector('[data-lobby-prev]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (!facade || self.products.length === 0) return;
            facade.prevExhibit();
        });

        root.querySelector('[data-lobby-next]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (!facade || self.products.length === 0) return;
            facade.nextExhibit();
        });

        root.querySelector('[data-lobby-exhibit-pill]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (facade && self.products.length > 0) {
                facade.focusExhibit(self.activeIndex, false);
            }
        });

        var slotBtns = root.querySelectorAll('[data-hero-slot]');
        for (var s = 0; s < slotBtns.length; s++) {
            slotBtns[s].addEventListener('click', function (ev) {
                var sl = parseInt(ev.currentTarget.getAttribute('data-hero-slot'), 10);
                var facade = getFacade(self);
                if (facade && sl >= 1) {
                    facade.focusHeroSlot(sl);
                }
            });
        }

        root.querySelector('[data-hero-my-place]').addEventListener('click', function () {
            var facade = getFacade(self);
            if (facade) {
                facade.focusMyHeroPlace();
            }
        });

        var moveStick = function (ev) {
            if (!self.stickActive || !self.stickZoneEl) return;
            var rect = self.stickZoneEl.getBoundingClientRect();
            var cx = rect.left + rect.width * 0.5;
            var cy = rect.top + rect.height * 0.5;
            var maxR = Math.max(16, rect.width * 0.34);
            var dx = ev.clientX - cx;
            var dy = ev.clientY - cy;
            var dist = Math.hypot(dx, dy);
            if (dist > maxR) {
                dx = (dx / dist) * maxR;
                dy = (dy / dist) * maxR;
            }
            if (self.stickKnobEl) {
                self.stickKnobEl.style.transform = 'translate3d(' + Math.round(dx) + 'px,' + Math.round(dy) + 'px,0)';
            }
            var facade = getFacade(self);
            if (facade) {
                facade.setJoystickVector(dx / maxR, dy / maxR);
            }
        };

        var endStick = function () {
            self.stickActive = false;
            if (self.stickKnobEl) {
                self.stickKnobEl.style.transform = 'translate3d(0,0,0)';
            }
            var facade = getFacade(self);
            if (facade) {
                facade.setJoystickVector(0, 0);
            }
        };

        this.stickZoneEl.addEventListener('pointerdown', function (ev) {
            ev.stopPropagation();
            ev.preventDefault();
            self.stickActive = true;
            try { self.stickZoneEl.setPointerCapture(ev.pointerId); } catch (err) { /* optional */ }
            moveStick(ev);
        });
        this.stickZoneEl.addEventListener('pointermove', function (ev) {
            ev.stopPropagation();
            ev.preventDefault();
            moveStick(ev);
        });
        this.stickZoneEl.addEventListener('pointerup', endStick);
        this.stickZoneEl.addEventListener('pointercancel', endStick);

        window.addEventListener('keydown', this._boundKeydown, true);
        document.addEventListener('fullscreenchange', this._boundFullscreenChange);
        document.addEventListener('webkitfullscreenchange', this._boundFullscreenChange);
        document.addEventListener('click', this._boundOutsideClick);
        window.addEventListener('pagehide', this._boundPageHide);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._setInstanceMenuOpen = function (openFlag) {
        this.instanceMenuOpen = Boolean(openFlag);
        if (!this.modalEl) return;
        var pop = this.modalEl.querySelector('[data-lobby-instance-popover]');
        if (pop) {
            pop.style.display = this.instanceMenuOpen ? 'block' : 'none';
        }
        if (this.instanceMenuOpen) {
            this._renderInstanceList();
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderInstanceList = function () {
        if (!this.modalEl) return;
        var listEl = this.modalEl.querySelector('[data-lobby-instance-list]');
        if (!listEl) return;

        while (listEl.firstChild) {
            listEl.removeChild(listEl.firstChild);
        }

        var self = this;
        var items = Array.isArray(this.instances) && this.instances.length > 0
            ? this.instances
            : [{ id: this.instanceId, count: 1 + this.peers.length, capacity: this.instanceCapacity, is_current: true }];

        for (var i = 0; i < items.length; i++) {
            (function (inst) {
                var btn = document.createElement('button');
                btn.type = 'button';
                var isCur = (Number(inst.id) === Number(self.instanceId)) || Boolean(inst.is_current);
                var cap = Number(inst.capacity) || 20;
                var cnt = Math.max(isCur ? 1 : 0, Number(inst.count) || 0);
                btn.className = 'sf-lobby-instance-item' + (isCur ? ' is-current' : '');

                var left = document.createElement('span');
                left.textContent = 'Instanz #' + inst.id;
                var right = document.createElement('span');
                right.textContent = cnt + '/' + cap;
                btn.appendChild(left);
                btn.appendChild(right);

                btn.addEventListener('click', function () {
                    self.switchInstance(inst.id);
                });
                listEl.appendChild(btn);
            })(items[i]);
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._syncHudState = function () {
        if (!this.modalEl) return;
        var root = this.modalEl;

        var zoneBtns = root.querySelectorAll('[data-lobby-zone]');
        for (var i = 0; i < zoneBtns.length; i++) {
            var zb = zoneBtns[i];
            var isAct = zb.getAttribute('data-lobby-zone') === this.currentZone;
            if (isAct) zb.classList.add('is-active');
            else zb.classList.remove('is-active');
        }

        var guestLock = root.querySelector('[data-lobby-guest-lock]');
        if (guestLock) {
            guestLock.style.display = this.lobbyAuth ? 'none' : 'inline';
        }

        var stagesBtn = root.querySelector('[data-lobby-stages-btn]');
        if (stagesBtn) {
            stagesBtn.style.display = (this.currentZone === 'heroes' && this.lobbyAuth) ? 'inline-flex' : 'none';
            stagesBtn.textContent = this.stagesMode === '1-5'
                ? '✨ Stufen-Ansicht 1-5'
                : (this.stagesMode === '6-10' ? '✨ Stufen-Ansicht 6-10' : '✨ Stufen');
        }

        var soloBtn = root.querySelector('[data-lobby-solo-toggle]');
        var soloDot = root.querySelector('[data-lobby-solo-dot]');
        var soloLbl = root.querySelector('[data-lobby-solo-label]');
        if (soloBtn) {
            soloBtn.style.display = (this.currentZone === 'heroes' && this.lobbyAuth) ? 'inline-flex' : 'none';
        }
        if (soloDot) {
            soloDot.className = 'sf-lobby-dot ' + (this.soloMode ? 'is-solo' : 'is-live');
        }
        if (soloLbl) {
            soloLbl.textContent = this.soloMode ? 'Solo' : 'Live';
        }

        var muteBtn = root.querySelector('[data-lobby-audio-mute]');
        if (muteBtn) {
            muteBtn.textContent = (this.audioState.allMuted || this.userSettings.volume <= 0) ? '🔇' : '🔊';
            if (!this.audioState.allMuted && this.userSettings.volume > 0) muteBtn.classList.add('is-gold');
            else muteBtn.classList.remove('is-gold');
        }

        var setBtn = root.querySelector('[data-lobby-settings-toggle]');
        if (setBtn) {
            if (this.settingsOpen) setBtn.classList.add('is-gold');
            else setBtn.classList.remove('is-gold');
        }

        var volLbl = root.querySelector('[data-lobby-volume-label]');
        var volSlider = root.querySelector('[data-lobby-volume-slider]');
        if (volLbl) volLbl.textContent = this.userSettings.volume + ' %';
        if (volSlider) volSlider.value = String(this.userSettings.volume);

        var camLbl = root.querySelector('[data-lobby-camera-label]');
        var camSlider = root.querySelector('[data-lobby-camera-slider]');
        if (camLbl) camLbl.textContent = this.userSettings.cameraSensitivity + ' %';
        if (camSlider) camSlider.value = String(this.userSettings.cameraSensitivity);

        var realChk = root.querySelector('[data-lobby-realname-checkbox]');
        if (realChk) realChk.checked = Boolean(this.userSettings.useRealName);

        var keyMapEls = root.querySelectorAll('[data-ability-key]');
        for (var km = 0; km < keyMapEls.length; km++) {
            var actName = keyMapEls[km].getAttribute('data-ability-key');
            keyMapEls[km].textContent = this._keyLabel(actName);
        }

        var jumpBtn = root.querySelector('[data-lobby-jump]');
        if (jumpBtn) jumpBtn.title = 'Springen (' + this._keyLabel('jump') + ')';
        var strikeBtn = root.querySelector('[data-lobby-strike]');
        if (strikeBtn) strikeBtn.title = 'Waffen-Effekt (' + this._keyLabel('strike') + ')';

        var fsBtn = root.querySelector('[data-lobby-fullscreen]');
        if (fsBtn) {
            fsBtn.textContent = this.isFullscreen ? '🗗 Teilbild' : '⛶ Vollbild';
            if (this.isFullscreen) fsBtn.classList.add('is-gold');
            else fsBtn.classList.remove('is-gold');
        }

        var instWrap = root.querySelector('[data-lobby-instance-wrap]');
        if (instWrap) {
            instWrap.style.display = (this.currentZone === 'heroes' && this.lobbyAuth && !this.soloMode) ? 'inline-flex' : 'none';
        }

        var instLabel = root.querySelector('[data-lobby-instance-label]');
        if (instLabel) {
            var curCount = 1 + (Array.isArray(this.peers) ? this.peers.length : 0);
            for (var k = 0; k < this.instances.length; k++) {
                if (Number(this.instances[k].id) === Number(this.instanceId)) {
                    curCount = Math.max(1, Number(this.instances[k].count) || curCount);
                    this.instanceCapacity = Number(this.instances[k].capacity) || 20;
                    break;
                }
            }
            instLabel.textContent = '🌐 Instanz #' + this.instanceId + ' · ' + curCount + '/' + this.instanceCapacity + ' ▾';
        }

        var btmGallery = root.querySelector('[data-lobby-bottom-gallery]');
        var btmHeroes = root.querySelector('[data-lobby-bottom-heroes]');
        var btmLava = root.querySelector('[data-lobby-bottom-lava]');
        var btmLabyrinth = root.querySelector('[data-lobby-bottom-labyrinth]');
        if (btmGallery && btmHeroes) {
            btmGallery.style.display = (this.currentZone === 'gallery' && this.products.length > 0) ? 'inline-flex' : 'none';
            btmHeroes.style.display = (this.currentZone === 'heroes') ? 'inline-flex' : 'none';
        }
        if (btmLava) {
            btmLava.style.display = (this.currentZone === 'lava') ? 'inline-flex' : 'none';
        }
        if (btmLabyrinth) {
            btmLabyrinth.style.display = (this.currentZone === 'labyrinth') ? 'inline-flex' : 'none';
        }

        var exPill = root.querySelector('[data-lobby-exhibit-pill]');
        if (exPill && this.products.length > 0) {
            var curProd = this.products[this.activeIndex];
            exPill.textContent = 'Exponat ' + (this.activeIndex + 1) + '/' + this.products.length + (curProd ? (' · ' + sanitizeText(curProd.name)) : '');
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderFloatingProductCard = function (prod) {
        if (!this.modalEl) return;
        var inner = this.modalEl.querySelector('[data-lobby-product-inner]');
        if (!inner) return;

        while (inner.firstChild) inner.removeChild(inner.firstChild);
        if (!prod) return;

        var cat = document.createElement('span');
        cat.className = 'sf-lobby-prod-cat';
        cat.textContent = sanitizeText(prod.category_name || 'Kollektion');
        inner.appendChild(cat);

        var title = document.createElement('div');
        title.className = 'sf-lobby-prod-title';
        title.textContent = sanitizeText(prod.name || 'Unikat');
        inner.appendChild(title);

        var rawPrice = sanitizeText(prod.price_formatted || '');
        var priceStr = (prod.is_personalizable && rawPrice && !/^ab\s/i.test(rawPrice)) ? ('ab ' + rawPrice) : rawPrice;
        var price = document.createElement('div');
        price.className = 'sf-lobby-prod-price';
        price.textContent = priceStr;
        inner.appendChild(price);

        var benefits = Array.isArray(prod.benefits) ? prod.benefits.slice(0, 3) : [];
        if (benefits.length > 0) {
            var list = document.createElement('div');
            list.className = 'sf-lobby-prod-benefits';
            for (var b = 0; b < benefits.length; b++) {
                var row = document.createElement('div');
                row.className = 'sf-lobby-prod-benefit-row';
                var chk = document.createElement('span');
                chk.className = 'sf-lobby-prod-check';
                chk.textContent = '✓';
                var txt = document.createElement('span');
                txt.textContent = sanitizeText(benefits[b]);
                row.appendChild(chk);
                row.appendChild(txt);
                list.appendChild(row);
            }
            inner.appendChild(list);
        }

        var link = document.createElement('a');
        link.href = prod.product_url || '/shop';
        link.className = 'sf-lobby-prod-cta';
        link.textContent = (prod.is_personalizable ? 'Jetzt gestalten' : 'Zum Produkt') + ' →';
        inner.appendChild(link);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderFloatingHeroCard = function (hero) {
        if (!this.modalEl) return;
        var inner = this.modalEl.querySelector('[data-lobby-hero-inner]');
        if (!inner) return;

        while (inner.firstChild) inner.removeChild(inner.firstChild);
        if (!hero) return;

        var header = document.createElement('div');
        header.className = 'sf-lobby-hero-head';

        var badge = document.createElement('span');
        badge.className = 'sf-lobby-hero-rank';
        badge.textContent = '#' + hero.slot;
        header.appendChild(badge);

        var nameEl = document.createElement('span');
        nameEl.className = 'sf-lobby-hero-name';
        nameEl.textContent = sanitizeText(hero.display_name || 'Freier Ehrenplatz');
        header.appendChild(nameEl);

        if (hero.is_me) {
            var mePill = document.createElement('span');
            mePill.className = 'sf-lobby-hero-me';
            mePill.textContent = 'DU';
            header.appendChild(mePill);
        }

        inner.appendChild(header);

        if (!hero.is_placeholder) {
            var pills = document.createElement('div');
            pills.className = 'sf-lobby-hero-stats';

            var makePill = function (label) {
                var p = document.createElement('span');
                p.className = 'sf-lobby-hero-chip';
                p.textContent = sanitizeText(label);
                return p;
            };

            pills.appendChild(makePill('Stufe ' + hero.level + ' · ' + hero.level_title));
            pills.appendChild(makePill('✨ ' + (hero.sparks_formatted || hero.sparks) + ' Funken'));
            pills.appendChild(makePill(hero.ship_class || 'KLASSE C'));
            pills.appendChild(makePill('🏆 ' + (hero.titles_count || 1) + ' Erfolge'));
            inner.appendChild(pills);
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._handleZoneSyncTransition = function (nextZone) {
        var z = String(nextZone || 'gallery').toLowerCase();
        var facade = getFacade(this);

        if (z === 'heroes') {
            if (this.lobbyAuth && !this.soloMode && (this._activeSyncZone !== 'heroes' || !this._syncTimer)) {
                this._activeSyncZone = 'heroes';
                this._joinLobby();
            }
            return;
        }

        // Solo-Zone ('gallery', 'lava', 'labyrinth'): Peers sofort leeren, Timer stoppen & Heldenhallen-Platz freigeben!
        var wasInHeroes = (this._activeSyncZone === 'heroes' || Boolean(this._syncTimer));
        this._activeSyncZone = z;
        this._setInstanceMenuOpen(false);
        this._stopSyncLoop();
        this.peers = [];
        if (facade) {
            facade.updatePeers([]);
        }

        if (wasInHeroes && this.lobbyAuth && !this.soloMode && facade) {
            var st = facade.getHudSnapshot();
            fetch('/funkenwerk-welt/lobby/sync', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    instance_id: this.instanceId,
                    x: st.x,
                    y: st.y,
                    z: st.z,
                    rot_y: st.rot_y,
                    speed: st.speed,
                    zone: z,
                    combo_seq: st.combo_seq,
                    combo_stage: st.combo_stage
                })
            }).catch(function () {});
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._joinLobby = function () {
        var self = this;
        var facade = getFacade(this);
        if (!facade || !this.lobbyAuth || this.soloMode || this.currentZone !== 'heroes') return;
        var st = facade.getHudSnapshot();

        fetch('/funkenwerk-welt/lobby/join', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                x: st.x,
                y: st.y,
                z: st.z,
                rot_y: st.rot_y,
                speed: st.speed,
                zone: 'heroes',
                combo_seq: st.combo_seq,
                combo_stage: st.combo_stage
            })
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok || !self.isOpen || self.currentZone !== 'heroes') return;
                self.lobbyAuth = Boolean(data.authenticated);
                if (data.instance_id) self.instanceId = Number(data.instance_id) || 1;
                if (data.hard_cap) self.instanceCapacity = Number(data.hard_cap) || 20;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                self.peers = Array.isArray(data.peers) ? data.peers : [];
                var f2 = getFacade(self);
                if (f2) {
                    f2.updatePeers(self.peers);
                }
                self._syncHudState();
                if (self.lobbyAuth && !self.soloMode && self.currentZone === 'heroes') {
                    self._startSyncLoop();
                }
            })
            .catch(function () {});
    };

    SeelenfunkeUnifiedLobbyHost.prototype._startSyncLoop = function () {
        var self = this;
        this._stopSyncLoop();
        if (!this.isOpen || !this.lobbyAuth || this.soloMode || this.currentZone !== 'heroes') return;

        this._syncTimer = setInterval(function () {
            self._runSyncTick();
        }, 1800);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._stopSyncLoop = function () {
        if (this._syncTimer) {
            clearInterval(this._syncTimer);
            this._syncTimer = null;
        }
        this._syncInFlight = false;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._runSyncTick = function () {
        var self = this;
        var facade = getFacade(this);
        if (!this.isOpen || !this.lobbyAuth || this.soloMode || !facade || this._syncInFlight) {
            return;
        }
        if (this.currentZone !== 'heroes') {
            this._stopSyncLoop();
            return;
        }
        this._syncInFlight = true;
        var st = facade.getHudSnapshot();

        fetch('/funkenwerk-welt/lobby/sync', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                instance_id: this.instanceId,
                x: st.x,
                y: st.y,
                z: st.z,
                rot_y: st.rot_y,
                speed: st.speed,
                zone: 'heroes',
                combo_seq: st.combo_seq,
                combo_stage: st.combo_stage
            })
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                var f2 = getFacade(self);
                if (!data || !data.ok || !self.isOpen || !f2 || self.currentZone !== 'heroes') return;
                if (data.instance_id) self.instanceId = Number(data.instance_id) || self.instanceId;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                self.peers = Array.isArray(data.peers) ? data.peers : [];
                f2.updatePeers(self.peers);
                self._syncHudState();
                if (self.instanceMenuOpen) self._renderInstanceList();
            })
            .catch(function () {})
            .finally(function () {
                self._syncInFlight = false;
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype.switchInstance = function (targetInstance) {
        var self = this;
        var facade = getFacade(this);
        if (!this.isOpen || !this.lobbyAuth || !facade) return;
        var cleanTarget = clampInt(targetInstance, 1, 50);
        if (this.currentZone !== 'heroes') {
            facade.setZone('heroes', false);
            this.currentZone = 'heroes';
            this._activeSyncZone = 'heroes';
            this._syncHudState();
        }
        var st = facade.getHudSnapshot();
        this._setInstanceMenuOpen(false);

        fetch('/funkenwerk-welt/lobby/switch', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                target_instance: cleanTarget,
                x: st.x,
                y: st.y,
                z: st.z,
                rot_y: st.rot_y,
                zone: 'heroes'
            })
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                var f2 = getFacade(self);
                if (!data || !data.ok || !self.isOpen || !f2) return;
                if (data.instance_id) self.instanceId = Number(data.instance_id) || cleanTarget;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                self.peers = Array.isArray(data.peers) ? data.peers : [];
                f2.switchInstance(self.peers);
                self._syncHudState();
                if (self.lobbyAuth && !self.soloMode && self.currentZone === 'heroes') {
                    self._startSyncLoop();
                }
            })
            .catch(function () {});
    };

    SeelenfunkeUnifiedLobbyHost.prototype._sendLeaveBeacon = function () {
        if (!this.lobbyAuth) return;
        var token = getCsrfToken();
        try {
            if (navigator.sendBeacon && typeof FormData !== 'undefined') {
                var fd = new FormData();
                fd.append('_token', token);
                fd.append('instance_id', String(this.instanceId));
                navigator.sendBeacon('/funkenwerk-welt/lobby/leave', fd);
                return;
            }
        } catch (err) {
            // Fallback fetch keepalive
        }
        try {
            fetch('/funkenwerk-welt/lobby/leave', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ instance_id: this.instanceId })
            }).catch(function () {});
        } catch (e) {
            // Ignorieren beim Entladen
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype.open = function (initialMode) {
        var self = this;
        var mode = String(initialMode || 'lobby').toLowerCase();
        var startZone = (mode === 'lava')
            ? 'lava'
            : (mode === 'labyrinth'
                ? 'labyrinth'
                : ((mode === 'hall' || mode === 'heroes' || mode === 'my_place') ? mode : 'gallery'));

        this._ensureModalDom();
        if (!this.isOpen) {
            this.prevHtmlOverflow = document.documentElement.style.overflow || '';
            this.prevBodyOverflow = document.body.style.overflow || '';
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
        }
        this.isOpen = true;
        this.isFullscreen = Boolean(getActiveFullscreenElement());
        this.settingsOpen = false;
        this.listeningKeyAction = '';
        this._openSeq += 1;
        var seq = this._openSeq;

        this.modalEl.classList.remove('is-closed');
        this.modalEl.classList.add('is-open');
        this.modalEl.style.display = 'flex';
        this.currentZone = (startZone === 'lava')
            ? 'lava'
            : (startZone === 'labyrinth'
                ? 'labyrinth'
                : ((startZone === 'gallery') ? 'gallery' : 'heroes'));
        this.stagesMode = 'live';
        this._setSettingsOpen(false);

        if (!this._abilityTimer) {
            this._abilityTimer = setInterval(function () {
                self._syncAbilityHud();
            }, 90);
        }

        var existingFacade = getFacade(this);
        if (existingFacade) {
            if (startZone === 'my_place') {
                existingFacade.setZone('heroes', true);
                existingFacade.focusMyHeroPlace();
            } else {
                existingFacade.setZone(startZone, true);
            }
            existingFacade.resize();
            this._handleZoneSyncTransition(this.currentZone);
            this._syncHudState();
            return;
        }

        if (this.loadingEl) {
            this.loadingEl.style.display = 'flex';
        }

        var showroomReq = fetch('/shop/showroom-data', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .catch(function () { return null; });

        var heroesReq = fetch('/funkenwerk-welt/heroes-data', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .catch(function () { return null; });

        var modReq = import(MODULE_URL);

        Promise.all([showroomReq, heroesReq, modReq])
            .then(function (results) {
                if (!self.isOpen || seq !== self._openSeq) return;
                var shopJson = results[0];
                var heroesJson = results[1];
                var mod = results[2];
                self._mod = mod;

                if (mod && typeof mod.ensureLobbyScopedStyles === 'function') {
                    mod.ensureLobbyScopedStyles();
                }

                self.products = (shopJson && Array.isArray(shopJson.products) && shopJson.products.length > 0)
                    ? shopJson.products
                    : FALLBACK_LOBBY_PRODUCTS;

                if (shopJson && shopJson.player_funki) {
                    self.funkiInfo = shopJson.player_funki;
                }
                if (heroesJson && heroesJson.success) {
                    self.heroesPayload = heroesJson;
                }

                var isAuth = Boolean(
                    (self.funkiInfo && !self.funkiInfo.is_guest)
                    || (self.heroesPayload && self.heroesPayload.customer_logged_in)
                );
                self.lobbyAuth = isAuth;
                self.activeIndex = 0;

                if (mod && typeof mod.loadLobbyUserSettings === 'function') {
                    self.userSettings = mod.loadLobbyUserSettings();
                }
                if (mod && typeof mod.loadLobbyKeybindings === 'function') {
                    self.keybindings = mod.loadLobbyKeybindings();
                }
                if (mod && typeof mod.getLobbyAudioInstance === 'function') {
                    self._audio = mod.getLobbyAudioInstance({ isAuthenticated: isAuth });
                    self._audio.setSfxVolume(self.userSettings.volume);
                    self._audio.setSfxMuted(self.userSettings.volume <= 0);
                    self.audioState = self._audio.getState();
                    if (self._audioUnsub) self._audioUnsub();
                    self._audioUnsub = self._audio.onChange(function (snap) {
                        self.audioState = snap;
                        self._syncHudState();
                    });
                }

                var lavaInfo = (shopJson && shopJson.lava_parkour) ? shopJson.lava_parkour : null;
                var labInfo = (shopJson && shopJson.mini_labyrinth) ? shopJson.mini_labyrinth : null;
                self.lavaChestState = {
                    nearChest: false,
                    isAuthenticated: isAuth,
                    claimedToday: Boolean(lavaInfo && lavaInfo.claimed_today),
                    claiming: false,
                    canClaim: Boolean(isAuth && !(lavaInfo && lavaInfo.claimed_today)),
                    statusMessage: ''
                };
                self.labyrinthBuzzerState = {
                    nearBuzzer: false,
                    isAuthenticated: isAuth,
                    claimedToday: Boolean(labInfo && labInfo.claimed_today),
                    claiming: false,
                    canClaim: Boolean(isAuth && !(labInfo && labInfo.claimed_today)),
                    statusMessage: ''
                };
                self._renderLavaChestCard();
                self._renderLabyrinthBuzzerCard();
                self._renderKeybindingsList();

                if (self.loadingEl) {
                    self.loadingEl.style.display = 'none';
                }

                var createdFacade = mod.createLouvreShowroom({
                    canvas: self.canvasEl,
                    floatingCardEl: self.floatingProductWrap,
                    floatingHeroCardEl: self.floatingHeroWrap,
                    floatingPortalCardEl: self.floatingPortalWrap,
                    floatingLavaChestEl: self.floatingLavaChestWrap,
                    floatingLabyrinthBuzzerEl: self.floatingLabyrinthBuzzerWrap,
                    products: self.products,
                    heroesPayload: self.heroesPayload,
                    playerFunki: self.funkiInfo,
                    lavaParkour: lavaInfo,
                    miniLabyrinth: labInfo,
                    audio: self._audio || undefined,
                    isAuthenticatedCustomer: isAuth,
                    initialZone: startZone === 'my_place' ? 'heroes' : startZone,
                    onFocusProductChange: function (prod, idx) {
                        self.focusedProduct = prod || null;
                        self._renderFloatingProductCard(self.focusedProduct);
                        self._syncAbilityHud();
                        if (typeof idx === 'number' && idx >= 0) {
                            self.activeIndex = idx;
                            self._syncHudState();
                        }
                    },
                    onFocusHeroChange: function (heroInfo) {
                        self.focusedHero = heroInfo || null;
                        self._renderFloatingHeroCard(self.focusedHero);
                    },
                    onActiveExhibitChange: function (idx) {
                        self.activeIndex = idx;
                        self._syncHudState();
                    },
                    onZoneChange: function (zone) {
                        self.currentZone = zone;
                        self._handleZoneSyncTransition(zone);
                        self._syncHudState();
                    },
                    onLavaChestStateChange: function (state) {
                        self.lavaChestState = state;
                        self._renderLavaChestCard();
                    },
                    onLabyrinthBuzzerStateChange: function (state) {
                        self.labyrinthBuzzerState = state;
                        self._renderLabyrinthBuzzerCard();
                    },
                    onSelectProduct: function (prod) {
                        if (prod && prod.product_url) {
                            window.location.href = prod.product_url;
                        }
                    }
                });
                setFacade(self, createdFacade);
                if (startZone === 'my_place') {
                    createdFacade.focusMyHeroPlace();
                }

                self._activeSyncZone = null;
                self._syncHudState();
                self._syncAbilityHud();
                if (isAuth && self.currentZone === 'heroes') {
                    self._handleZoneSyncTransition('heroes');
                }
            })
            .catch(function () {
                if (self.loadingEl) {
                    var txt = self.loadingEl.querySelector('[data-lobby-loading-text]');
                    if (txt) txt.textContent = '3D-Lobby konnte auf diesem Gerät nicht geladen werden.';
                }
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype.close = function () {
        if (!this.isOpen) return;
        this._stopSyncLoop();
        if (this._activeSyncZone === 'heroes') {
            this._sendLeaveBeacon();
        }
        this._activeSyncZone = null;
        this.isOpen = false;
        this.instanceMenuOpen = false;
        this.settingsOpen = false;
        this.listeningKeyAction = '';
        this.peers = [];

        if (this._audioUnsub) {
            this._audioUnsub();
            this._audioUnsub = null;
        }
        if (this._abilityTimer) {
            clearInterval(this._abilityTimer);
            this._abilityTimer = null;
        }

        if (getActiveFullscreenElement()) {
            try {
                if (typeof document.exitFullscreen === 'function') {
                    Promise.resolve(document.exitFullscreen()).catch(function () {});
                } else if (typeof document.webkitExitFullscreen === 'function') {
                    document.webkitExitFullscreen();
                }
            } catch (err) {
                // Vollbild bereits beendet
            }
        }
        this.isFullscreen = false;

        var facade = getFacade(this);
        if (facade) {
            facade.destroy();
            setFacade(this, null);
        }
        if (this.modalEl) {
            this.modalEl.classList.remove('is-open');
            this.modalEl.classList.add('is-closed');
            this.modalEl.style.display = 'none';
        }
        document.documentElement.style.overflow = this.prevHtmlOverflow;
        document.body.style.overflow = this.prevBodyOverflow;
    };

    SeelenfunkeUnifiedLobbyHost.prototype.dispose = function () {
        this.close();
        if (this._fsResizeTimer) {
            clearTimeout(this._fsResizeTimer);
            this._fsResizeTimer = null;
        }
        window.removeEventListener('keydown', this._boundKeydown, true);
        document.removeEventListener('fullscreenchange', this._boundFullscreenChange);
        document.removeEventListener('webkitfullscreenchange', this._boundFullscreenChange);
        document.removeEventListener('click', this._boundOutsideClick);
        window.removeEventListener('pagehide', this._boundPageHide);
        if (this.modalEl && this.modalEl.parentNode) {
            this.modalEl.parentNode.removeChild(this.modalEl);
        }
        this.modalEl = null;
    };

    var privateLobbyHost = new SeelenfunkeUnifiedLobbyHost();
    var publicHeroesApi = Object.freeze({
        open: function (mode) {
            privateLobbyHost.open(mode || 'lobby');
        },
        close: function () {
            privateLobbyHost.close();
        }
    });

    try {
        Object.defineProperty(window, 'SeelenfunkeHeroes3D', {
            value: publicHeroesApi,
            writable: false,
            configurable: false
        });
    } catch (e) {
        window.SeelenfunkeHeroes3D = publicHeroesApi;
    }

    window.openFunkenwerkHeroes3D = function (mode) {
        publicHeroesApi.open(mode || 'lobby');
    };
})();
