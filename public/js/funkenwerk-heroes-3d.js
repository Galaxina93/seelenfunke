(function () {
    'use strict';

    if (window.SeelenfunkeHeroes3D) {
        return;
    }

    var MODULE_URL = '/js/seelenfunke-louvre-showroom-3d.js?v=20261116v4';

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
        '.sf-lobby-stick-wrap { position: absolute; bottom: 22px; left: 20px; z-index: 22; }',
        '.sf-lobby-stick { width: 84px; height: 84px; border-radius: 999px; background: rgba(255,255,255,0.88); border: 1px solid rgba(197,160,89,0.52); box-shadow: 0 10px 26px rgba(17,24,39,0.12); display: flex; align-items: center; justify-content: center; position: relative; touch-action: none; cursor: pointer; }',
        '.sf-lobby-stick-knob { width: 34px; height: 34px; border-radius: 999px; background: #C5A059; border: 2px solid #FFFFFF; box-shadow: 0 4px 12px rgba(17,24,39,0.18); pointer-events: none; }',
        '.sf-lobby-quick-actions { position: absolute; bottom: 22px; right: 20px; z-index: 22; display: flex; align-items: center; gap: 8px; }',
        '.sf-lobby-icon-btn { appearance: none; height: 44px; min-width: 44px; padding: 0 12px; border-radius: 14px; background: rgba(255,255,255,0.94); color: #111827; border: 1px solid rgba(197,160,89,0.55); box-shadow: 0 10px 24px rgba(17,24,39,0.10); font-size: 15px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }',
        '.sf-lobby-bottom-stack { position: absolute; bottom: 14px; left: 12px; right: 12px; z-index: 21; display: flex; flex-direction: column; align-items: center; gap: 7px; pointer-events: none; }',
        '.sf-lobby-bottom-pill { pointer-events: auto; display: inline-flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: rgba(255,255,255,0.94); border: 1px solid rgba(197,160,89,0.48); box-shadow: 0 10px 28px rgba(17,24,39,0.12); max-width: 100%; box-sizing: border-box; }',
        '.sf-lobby-step-btn { appearance: none; padding: 6px 12px; border-radius: 999px; border: 1px solid rgba(197,160,89,0.40); background: #FAF8F5; color: #111827; font-size: 11px; font-weight: 800; cursor: pointer; white-space: nowrap; }',
        '.sf-lobby-step-btn.is-active { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }',
        '.sf-lobby-exhibit-btn { appearance: none; padding: 6px 14px; border-radius: 999px; border: 1px solid rgba(197,160,89,0.35); background: #FFFFFF; color: #111827; font-size: 11px; font-weight: 800; cursor: pointer; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }',
        '.sf-lobby-hint-pill { pointer-events: none; padding: 5px 14px; border-radius: 999px; background: rgba(255,255,255,0.90); border: 1px solid rgba(197,160,89,0.38); color: #374151; font-size: 11px; font-weight: 700; box-shadow: 0 6px 18px rgba(17,24,39,0.08); white-space: nowrap; }',
        '@media (max-width: 767px) { .sf-lobby-topbar { padding: 10px 12px; gap: 8px; } .sf-lobby-zone-btn { padding: 6px 10px; font-size: 11px; } .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 6px 10px; font-size: 11px; } .sf-lobby-kbd, .sf-lobby-hint-pill { display: none !important; } .sf-lobby-stick-wrap { bottom: 72px; left: 12px; } .sf-lobby-stick { width: 74px; height: 74px; } .sf-lobby-quick-actions { bottom: 72px; right: 12px; } .sf-lobby-exhibit-btn { max-width: 175px; } }'
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

    function ensureScopedStyleSheet() {
        var existing = document.getElementById('sf-3d-lobby-scoped-css');
        if (!existing) {
            var styleTag = document.createElement('style');
            styleTag.id = 'sf-3d-lobby-scoped-css';
            styleTag.textContent = STANDALONE_SCOPED_CSS;
            (document.head || document.documentElement).appendChild(styleTag);
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

    function SeelenfunkeUnifiedLobbyHost() {
        this.isOpen = false;
        this.isFullscreen = false;
        this.modalEl = null;
        this.canvasEl = null;
        this.loadingEl = null;
        this.floatingProductWrap = null;
        this.floatingHeroWrap = null;
        this.floatingPortalWrap = null;

        this._engine = null;
        this._syncTimer = null;
        this._syncInFlight = false;
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

        this.stickActive = false;
        this.stickKnobEl = null;
        this.stickZoneEl = null;

        var self = this;
        this._boundKeydown = function (e) {
            if (!self.isOpen) return;
            var key = e.key || '';
            var code = e.code || '';

            if (key === 'Escape' || code === 'Escape' || e.keyCode === 27) {
                e.preventDefault();
                e.stopPropagation();
                if (self.instanceMenuOpen) {
                    self._setInstanceMenuOpen(false);
                } else {
                    self.close();
                }
                return;
            }

            var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

            if ((code === 'KeyF' || key === 'f' || key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                self.toggleFullscreen();
            }
        };

        this._boundFullscreenChange = function () {
            self.isFullscreen = Boolean(getActiveFullscreenElement());
            self._syncHudState();
            if (self._engine) {
                self._engine.resize();
                requestAnimationFrame(function () {
                    if (self._engine) self._engine.resize();
                });
                if (self._fsResizeTimer) clearTimeout(self._fsResizeTimer);
                self._fsResizeTimer = setTimeout(function () {
                    if (self._engine) self._engine.resize();
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
            '    <button type="button" data-lobby-zone="gallery" class="sf-lobby-zone-btn is-active">🛍️ Kollektion</button>',
            '    <button type="button" data-lobby-zone="heroes" class="sf-lobby-zone-btn"><span data-lobby-guest-lock style="display:none;">🔒</span><span>🏛️ Halle der Helden</span></button>',
            '    <button type="button" data-lobby-stages-btn class="sf-lobby-stages-btn" style="display:none;">✨ Stufen</button>',
            '  </div>',
            '  <div class="sf-lobby-actions">',
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
            '    <a href="/login" class="sf-lobby-portal-pill">🔒 Mitglieder-Lobby · Anmelden →</a>',
            '  </div>',
            '  <div class="sf-lobby-stick-wrap">',
            '    <div data-lobby-stick class="sf-lobby-stick" aria-label="Bewegungssteuerung">',
            '      <div data-lobby-stick-knob class="sf-lobby-stick-knob"></div>',
            '    </div>',
            '  </div>',
            '  <div class="sf-lobby-quick-actions">',
            '    <button type="button" data-lobby-jump class="sf-lobby-icon-btn" title="Springen (Leertaste)" aria-label="Springen">⤒</button>',
            '    <button type="button" data-lobby-strike class="sf-lobby-icon-btn" style="color:#9A7738;" title="Aktion (Rechtsklick / Taste G)" aria-label="Aktion">✦</button>',
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
            '    <div class="sf-lobby-hint-pill">WASD / Pfeiltasten · Maus ziehen · ⛶ Vollbild · Esc Schließen</div>',
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
        this.stickZoneEl = root.querySelector('[data-lobby-stick]');
        this.stickKnobEl = root.querySelector('[data-lobby-stick-knob]');

        this._bindModalUiEvents();
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
                // Ignorieren falls Browser Vollbild bereits verlassen hat
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
        } catch (err) {
            // Browser ohne Fullscreen-Freigabe bleibt sauber im 100vw x 100dvh Viewport-Overlay
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

        var zoneBtns = root.querySelectorAll('[data-lobby-zone]');
        for (var z = 0; z < zoneBtns.length; z++) {
            zoneBtns[z].addEventListener('click', function (ev) {
                var targetZone = ev.currentTarget.getAttribute('data-lobby-zone');
                if (self._engine) {
                    self._engine.goToZone(targetZone, false);
                    self.currentZone = self._engine.getCurrentZone();
                } else {
                    self.currentZone = targetZone;
                }
                self._syncHudState();
            });
        }

        var stagesBtn = root.querySelector('[data-lobby-stages-btn]');
        stagesBtn.addEventListener('click', function () {
            if (!self._engine) return;
            self.stagesMode = self._engine.cycleStagesPreviewMode();
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

        root.querySelector('[data-lobby-jump]').addEventListener('click', function () {
            if (self._engine) self._engine.triggerJump();
        });

        root.querySelector('[data-lobby-strike]').addEventListener('click', function () {
            if (!self._engine) return;
            if (typeof self._engine.triggerStrikeAction === 'function') {
                self._engine.triggerStrikeAction();
            } else if (typeof self._engine.triggerWeaponAction === 'function') {
                self._engine.triggerWeaponAction();
            }
        });

        root.querySelector('[data-lobby-prev]').addEventListener('click', function () {
            if (!self._engine || self.products.length === 0) return;
            var prev = (self.activeIndex - 1 + self.products.length) % self.products.length;
            self._engine.goToExhibit(prev, false);
        });

        root.querySelector('[data-lobby-next]').addEventListener('click', function () {
            if (!self._engine || self.products.length === 0) return;
            var nxt = (self.activeIndex + 1) % self.products.length;
            self._engine.goToExhibit(nxt, false);
        });

        root.querySelector('[data-lobby-exhibit-pill]').addEventListener('click', function () {
            if (self._engine && self.products.length > 0) {
                self._engine.goToExhibit(self.activeIndex, false);
            }
        });

        var slotBtns = root.querySelectorAll('[data-hero-slot]');
        for (var s = 0; s < slotBtns.length; s++) {
            slotBtns[s].addEventListener('click', function (ev) {
                var sl = parseInt(ev.currentTarget.getAttribute('data-hero-slot'), 10);
                if (self._engine && sl >= 1) {
                    self._engine.goToHeroSlot(sl);
                }
            });
        }

        root.querySelector('[data-hero-my-place]').addEventListener('click', function () {
            if (self._engine) {
                self._engine.goToMyHeroPlace();
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
            if (self._engine) {
                self._engine.setJoystickVector(dx / maxR, dy / maxR);
            }
        };

        var endStick = function () {
            self.stickActive = false;
            if (self.stickKnobEl) {
                self.stickKnobEl.style.transform = 'translate3d(0,0,0)';
            }
            if (self._engine) {
                self._engine.setJoystickVector(0, 0);
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

        // Capture-Phase Escape-Handler: schliesst zuverlaessig unabhaengig vom aktuellen Fokus
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
            if (isAct) {
                zb.classList.add('is-active');
            } else {
                zb.classList.remove('is-active');
            }
        }

        var guestLock = root.querySelector('[data-lobby-guest-lock]');
        if (guestLock) {
            guestLock.style.display = this.lobbyAuth ? 'none' : 'inline';
        }

        var stagesBtn = root.querySelector('[data-lobby-stages-btn]');
        if (stagesBtn) {
            stagesBtn.style.display = (this.currentZone === 'heroes' && this.lobbyAuth) ? 'inline-flex' : 'none';
            stagesBtn.textContent = this.stagesMode === '1-5'
                ? '✨ Stufen 1-5'
                : (this.stagesMode === '6-10' ? '✨ Stufen 6-10' : '✨ Stufen');
        }

        var fsBtn = root.querySelector('[data-lobby-fullscreen]');
        if (fsBtn) {
            fsBtn.textContent = this.isFullscreen ? '🗗 Teilbild' : '⛶ Vollbild';
            if (this.isFullscreen) {
                fsBtn.classList.add('is-gold');
            } else {
                fsBtn.classList.remove('is-gold');
            }
        }

        var instWrap = root.querySelector('[data-lobby-instance-wrap]');
        if (instWrap) {
            instWrap.style.display = this.lobbyAuth ? 'inline-flex' : 'none';
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
        if (btmGallery && btmHeroes) {
            btmGallery.style.display = (this.currentZone === 'gallery' && this.products.length > 0) ? 'inline-flex' : 'none';
            btmHeroes.style.display = (this.currentZone === 'heroes') ? 'inline-flex' : 'none';
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

    SeelenfunkeUnifiedLobbyHost.prototype._joinLobby = function () {
        var self = this;
        if (!this._engine) return;
        var st = this._engine.getLocalSyncState();

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
                z: st.z,
                rot_y: st.rot_y,
                speed: st.speed,
                zone: st.zone
            })
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok || !self.isOpen) return;
                self.lobbyAuth = Boolean(data.authenticated);
                if (data.instance_id) self.instanceId = Number(data.instance_id) || 1;
                if (data.hard_cap) self.instanceCapacity = Number(data.hard_cap) || 20;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                self.peers = Array.isArray(data.peers) ? data.peers : [];
                if (self._engine) {
                    self._engine.updatePeers(self.peers);
                }
                self._syncHudState();
                if (self.lobbyAuth) {
                    self._startSyncLoop();
                }
            })
            .catch(function () {});
    };

    SeelenfunkeUnifiedLobbyHost.prototype._startSyncLoop = function () {
        var self = this;
        this._stopSyncLoop();
        if (!this.isOpen || !this.lobbyAuth || this.soloMode) return;

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
        if (!this.isOpen || !this.lobbyAuth || this.soloMode || !this._engine || this._syncInFlight) {
            return;
        }
        this._syncInFlight = true;
        var st = this._engine.getLocalSyncState();

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
                z: st.z,
                rot_y: st.rot_y,
                speed: st.speed,
                zone: st.zone
            })
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok || !self.isOpen || !self._engine) return;
                if (data.instance_id) self.instanceId = Number(data.instance_id) || self.instanceId;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                self.peers = Array.isArray(data.peers) ? data.peers : [];
                self._engine.updatePeers(self.peers);
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
        if (!this.isOpen || !this.lobbyAuth || !this._engine) return;
        var cleanTarget = clampInt(targetInstance, 1, 50);
        var st = this._engine.getLocalSyncState();
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
                z: st.z,
                rot_y: st.rot_y,
                zone: st.zone
            })
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok || !self.isOpen || !self._engine) return;
                if (data.instance_id) self.instanceId = Number(data.instance_id) || cleanTarget;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                self.peers = Array.isArray(data.peers) ? data.peers : [];
                self._engine.updatePeers(self.peers);
                self._syncHudState();
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
        var startZone = (mode === 'hall' || mode === 'heroes' || mode === 'my_place') ? mode : 'gallery';

        this._ensureModalDom();
        if (!this.isOpen) {
            this.prevHtmlOverflow = document.documentElement.style.overflow || '';
            this.prevBodyOverflow = document.body.style.overflow || '';
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
        }
        this.isOpen = true;
        this.isFullscreen = Boolean(getActiveFullscreenElement());
        this._openSeq += 1;
        var seq = this._openSeq;

        this.modalEl.classList.remove('is-closed');
        this.modalEl.classList.add('is-open');
        this.modalEl.style.display = 'flex';
        this.currentZone = (startZone === 'gallery') ? 'gallery' : 'heroes';
        this.stagesMode = 'live';
        this._syncHudState();

        if (this._engine) {
            if (startZone === 'my_place') {
                this._engine.goToZone('heroes', true);
                this._engine.goToMyHeroPlace();
            } else {
                this._engine.goToZone(startZone, true);
            }
            this._engine.resize();
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

                if (self.loadingEl) {
                    self.loadingEl.style.display = 'none';
                }

                self._engine = mod.createLouvreShowroom({
                    canvas: self.canvasEl,
                    floatingCardEl: self.floatingProductWrap,
                    floatingHeroCardEl: self.floatingHeroWrap,
                    floatingPortalCardEl: self.floatingPortalWrap,
                    products: self.products,
                    heroesPayload: self.heroesPayload,
                    playerFunki: self.funkiInfo,
                    isAuthenticatedCustomer: isAuth,
                    initialZone: startZone,
                    onFocusProductChange: function (prod, idx) {
                        self.focusedProduct = prod || null;
                        self._renderFloatingProductCard(self.focusedProduct);
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
                        self._syncHudState();
                    },
                    onSelectProduct: function (prod) {
                        if (prod && prod.product_url) {
                            window.location.href = prod.product_url;
                        }
                    }
                });

                self._syncHudState();
                if (isAuth) {
                    self._joinLobby();
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
        this._sendLeaveBeacon();
        this.isOpen = false;
        this.instanceMenuOpen = false;
        this.peers = [];

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

        if (this._engine) {
            this._engine.destroy();
            this._engine = null;
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

    window.SeelenfunkeHeroes3D = new SeelenfunkeUnifiedLobbyHost();
    window.openFunkenwerkHeroes3D = function (mode) {
        window.SeelenfunkeHeroes3D.open(mode || 'lobby');
    };
})();
