(function () {
    'use strict';

    if (window.SeelenfunkeHeroes3D) {
        return;
    }

    var MODULE_URL = '/js/seelenfunke-louvre-showroom-3d.js?v=20261118v10';

    function ensurePrewarmRegistryAndRegister(audioEl) {
        if (!audioEl || typeof window === 'undefined') return audioEl;
        if (!(window.__sfLobbyAudioRegistry instanceof Set)) {
            var nextSet = new Set();
            if (Array.isArray(window.__sfLobbyAudioRegistry)) {
                for (var i = 0; i < window.__sfLobbyAudioRegistry.length; i++) {
                    if (window.__sfLobbyAudioRegistry[i]) nextSet.add(window.__sfLobbyAudioRegistry[i]);
                }
            }
            window.__sfLobbyAudioRegistry = nextSet;
        }
        window.__sfLobbyAudioRegistry.add(audioEl);
        return audioEl;
    }

    function stopPrewarmAndForceLobbySilence() {
        if (typeof window === 'undefined') return;
        if (window.__sfPrewarmLobbyBgmEl) {
            try {
                window.__sfPrewarmLobbyBgmEl.pause();
                window.__sfPrewarmLobbyBgmEl.volume = 0;
                window.__sfPrewarmLobbyBgmEl.currentTime = 0;
            } catch (e) {}
            window.__sfPrewarmLobbyBgmEl = null;
        }
        if (typeof window.__sfForceStopAllLobbyAudio === 'function') {
            try {
                window.__sfForceStopAllLobbyAudio();
            } catch (e2) {}
        }
    }

    var KEYBIND_ACTIONS = [
        { key: 'jump', label: 'Sprung' },
        { key: 'moveForward', label: 'Vorwärts' },
        { key: 'moveLeft', label: 'Links' },
        { key: 'moveBack', label: 'Zurück' },
        { key: 'moveRight', label: 'Rechts' }
    ];

    var STANDALONE_SCOPED_CSS = [
        '@keyframes sfLobbySpin { to { transform: rotate(360deg); } }',
        '.sf-lobby-modal, .sf-lobby-modal * { user-select: none !important; -webkit-user-select: none !important; -webkit-touch-callout: none !important; }',
        '.sf-lobby-modal input, .sf-lobby-modal textarea { user-select: text !important; -webkit-user-select: text !important; }',
        '.sf-lobby-modal { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; inset: 0 !important; width: 100vw !important; height: 100vh !important; height: 100dvh !important; z-index: 2147483000 !important; background: #FAF8F5 !important; overflow: hidden !important; margin: 0 !important; padding: 0 !important; box-sizing: border-box !important; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #111827; }',
        '.sf-lobby-modal.is-open { display: flex !important; flex-direction: column !important; }',
        '.sf-lobby-modal.is-closed { display: none !important; }',
        '.sf-lobby-topbar { position: absolute; top: 0; left: 0; right: 0; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; pointer-events: none; z-index: 30; box-sizing: border-box; }',
        '.sf-lobby-nav-pill { pointer-events: auto; background: rgba(255,255,255,0.95); border: 1px solid rgba(197,160,89,0.45); border-radius: 999px; padding: 4px; box-shadow: 0 8px 24px rgba(17,24,39,0.08); display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; max-width: 100%; box-sizing: border-box; }',
        '.sf-lobby-zone-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 13px; border-radius: 999px; background: #FAF8F5; color: #111827; border: 1px solid rgba(197,160,89,0.40); font-size: 12px; font-weight: 800; line-height: 1.2; letter-spacing: 0.01em; white-space: nowrap; pointer-events: none; cursor: default; }',
        '.sf-lobby-stages-btn { appearance: none; border: 1px solid rgba(197,160,89,0.55); border-radius: 999px; padding: 6px 12px; font-size: 11px; font-weight: 800; line-height: 1.2; background: #FAF8F5; color: #111827; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }',
        '.sf-lobby-actions { pointer-events: auto; display: inline-flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-right: 244px; }',
        '.sf-lobby-pill-btn { appearance: none; background: rgba(255,255,255,0.95); color: #111827; border: 1px solid rgba(197,160,89,0.48); border-radius: 999px; padding: 7px 12px; font-size: 12px; font-weight: 800; line-height: 1.2; box-shadow: 0 8px 24px rgba(17,24,39,0.08); cursor: pointer; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }',
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
        '.sf-lobby-ivory-transition { position: absolute; inset: 0; z-index: 44; pointer-events: none; opacity: 0; background: radial-gradient(circle at 50% 45%, rgba(255,252,244,0.92) 0%, rgba(250,245,232,0.98) 58%, rgba(235,214,170,0.99) 100%); transition: opacity 540ms cubic-bezier(0.22, 1, 0.36, 1); }',
        '.sf-lobby-ivory-transition.is-fading { opacity: 1; pointer-events: auto; }',
        '.sf-lobby-floating-anchor { position: absolute; top: 0; left: 0; z-index: 20; will-change: transform; transition: opacity 180ms ease; pointer-events: none; }',
        '.sf-lobby-floating-card { position: relative; width: 296px; max-width: calc(100vw - 24px); background: rgba(255,255,255,0.98); border: 1px solid rgba(197,160,89,0.62); border-radius: 18px; padding: 14px 16px 12px; box-shadow: 0 16px 38px rgba(17,24,39,0.12), 0 0 0 1px rgba(255,255,255,0.7) inset; color: #111827; pointer-events: auto; box-sizing: border-box; }',
        '.sf-lobby-floating-arrow { width: 12px; height: 12px; background: rgba(255,255,255,0.98); border-right: 1px solid rgba(197,160,89,0.62); border-bottom: 1px solid rgba(197,160,89,0.62); transform: rotate(45deg); margin: -6px auto 0; pointer-events: none; }',
        '.sf-lobby-prod-row1 { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }',
        '.sf-lobby-prod-title { margin: 0; font-family: Georgia, "Times New Roman", serif; font-size: 15px; font-weight: 700; line-height: 1.24; color: #111827; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1 1 auto; min-width: 0; }',
        '.sf-lobby-prod-price-badge { flex-shrink: 0; display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px; background: #FAF6EE; border: 1px solid rgba(197,160,89,0.52); font-size: 11.5px; font-weight: 800; color: #8A692C; white-space: nowrap; }',
        '.sf-lobby-prod-summary { margin: 6px 0 0; font-size: 11.5px; font-weight: 500; line-height: 1.38; color: #4B5563; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }',
        '.sf-lobby-prod-cta { appearance: none; margin-top: 11px; display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 9px 14px; border-radius: 999px; border: 1px solid #B89146; background: #C5A059; color: #FFFFFF !important; font-size: 12px; font-weight: 800; letter-spacing: 0.02em; text-align: center; text-decoration: none !important; box-sizing: border-box; cursor: pointer; box-shadow: 0 6px 16px rgba(197,160,89,0.28); transition: background 150ms ease, transform 130ms ease; }',
        '.sf-lobby-prod-cta:hover { background: #B38E46; transform: translateY(-1px); }',
        '.sf-lobby-hero-head { display: flex; align-items: center; gap: 8px; }',
        '.sf-lobby-hero-rank { width: 28px; height: 28px; border-radius: 999px; background: #C5A059; color: #FFFFFF; font-size: 11px; font-weight: 900; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }',
        '.sf-lobby-hero-name { font-size: 14px; font-weight: 800; color: #111827; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }',
        '.sf-lobby-hero-me { margin-left: auto; padding: 2px 7px; border-radius: 999px; background: #FAF8F5; border: 1px solid #C5A059; font-size: 9px; font-weight: 900; color: #9A7738; }',
        '.sf-lobby-hero-stats { margin-top: 9px; display: flex; flex-wrap: wrap; gap: 5px; }',
        '.sf-lobby-hero-chip { padding: 3px 8px; border-radius: 8px; background: #FAF8F5; border: 1px solid rgba(197,160,89,0.38); font-size: 11px; font-weight: 700; color: #111827; }',
        '.sf-lobby-portal-pill { pointer-events: auto; appearance: none; display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; background: rgba(255,255,255,0.97); color: #111827; border: 1px solid #C5A059; box-shadow: 0 14px 32px rgba(17,24,39,0.14); font-size: 12px; font-weight: 800; text-decoration: none; white-space: nowrap; cursor: pointer; }',
        '.sf-lobby-portal-pill:hover { background: #FFFFFF; border-color: #B38E46; }',
        '.sf-lobby-unit-frame { pointer-events: auto; display: inline-flex; align-items: center; gap: 10px; padding: 7px 14px 7px 8px; border-radius: 999px; background: rgba(255,255,255,0.96); border: 1.5px solid rgba(197,160,89,0.60); box-shadow: 0 8px 24px rgba(17,24,39,0.08); color: #111827; }',
        '.sf-lobby-unit-avatar { width: 34px; height: 34px; border-radius: 999px; background: #FAF8F5; border: 1.5px solid #C5A059; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 900; color: #9A7738; flex-shrink: 0; }',
        '.sf-lobby-unit-meta { display: flex; align-items: center; gap: 10px; }',
        '.sf-lobby-topright-stack { position: absolute; top: 14px; right: 18px; z-index: 25; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; pointer-events: none; }',
        '.sf-lobby-minimap-wrap { position: relative; width: 232px; height: 232px; z-index: 25; pointer-events: auto; display: flex; align-items: center; justify-content: center; }',
        '.sf-lobby-minimap-canvas { width: 100%; height: 100%; border-radius: 999px; background: rgba(255,255,255,0.96); border: 2.5px solid #C5A059; box-shadow: 0 12px 30px rgba(17,24,39,0.16), 0 0 0 3px rgba(255,255,255,0.72); display: block; }',
        '.sf-lobby-minimap-time { position: absolute; bottom: -9px; left: 50%; transform: translateX(-50%); padding: 2px 10px; border-radius: 999px; background: #FFFFFF; border: 1px solid #C5A059; color: #111827; font-size: 10.5px; font-weight: 800; line-height: 1.25; white-space: nowrap; box-shadow: 0 4px 12px rgba(17,24,39,0.12); cursor: help; }',
        '@keyframes sfLobbyPingPulse { 0% { transform: scale(1); opacity: 0.38; } 70% { transform: scale(1.85); opacity: 0; } 100% { transform: scale(1.85); opacity: 0; } }',
        '.sf-lobby-ping-anchor { position: absolute; top: -8px; left: 50%; transform: translateX(-50%); z-index: 34; display: inline-flex; align-items: center; justify-content: center; pointer-events: auto; }',
        '.sf-lobby-ping-dot { appearance: none; width: 18px; height: 18px; padding: 0; margin: 0; border-radius: 999px; background: #FFFFFF; border: 1.5px solid #C5A059; box-shadow: 0 4px 10px rgba(17,24,39,0.14); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; position: relative; outline: none; }',
        '.sf-lobby-ping-core { width: 9px; height: 9px; border-radius: 999px; display: block; position: relative; z-index: 2; background: #10B981; transition: background-color 160ms ease; }',
        '.sf-lobby-ping-pulse { position: absolute; width: 9px; height: 9px; border-radius: 999px; background: #10B981; opacity: 0.34; animation: sfLobbyPingPulse 2.4s ease-out infinite; pointer-events: none; }',
        '.sf-lobby-ping-dot.is-optimal .sf-lobby-ping-core, .sf-lobby-ping-dot.is-optimal .sf-lobby-ping-pulse { background: #10B981; }',
        '.sf-lobby-ping-dot.is-good .sf-lobby-ping-core, .sf-lobby-ping-dot.is-good .sf-lobby-ping-pulse { background: #D97706; }',
        '.sf-lobby-ping-dot.is-delayed .sf-lobby-ping-core, .sf-lobby-ping-dot.is-delayed .sf-lobby-ping-pulse { background: #DC2626; }',
        '.sf-lobby-ping-tooltip { position: absolute; top: calc(100% + 8px); right: -56px; width: 214px; background: #FAF8F5; border: 1px solid #C5A059; border-radius: 14px; padding: 10px 12px; box-shadow: 0 14px 32px rgba(17,24,39,0.15); color: #111827; box-sizing: border-box; opacity: 0; visibility: hidden; transform: translateY(-4px); transition: opacity 150ms ease, transform 150ms ease, visibility 150ms ease; pointer-events: none; z-index: 40; }',
        '.sf-lobby-ping-anchor:hover .sf-lobby-ping-tooltip, .sf-lobby-ping-anchor:focus-within .sf-lobby-ping-tooltip, .sf-lobby-ping-anchor.is-open .sf-lobby-ping-tooltip { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }',
        '.sf-lobby-ping-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-bottom: 6px; margin-bottom: 6px; border-bottom: 1px solid rgba(197,160,89,0.28); font-size: 11.5px; font-weight: 800; color: #111827; }',
        '.sf-lobby-ping-badge { padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 800; line-height: 1.35; white-space: nowrap; background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }',
        '.sf-lobby-ping-badge.is-good { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }',
        '.sf-lobby-ping-badge.is-delayed { background: #FEF2F2; border-color: #FECACA; color: #B91C1C; }',
        '.sf-lobby-ping-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 11px; line-height: 1.45; color: #4B5563; }',
        '.sf-lobby-ping-row strong { font-weight: 800; color: #111827; white-space: nowrap; }',
        '.sf-lobby-exhibit-step-row { margin-top: 8px; padding-top: 7px; border-top: 1px solid rgba(197,160,89,0.22); display: flex; align-items: center; justify-content: space-between; gap: 4px; }',
        '.sf-lobby-sanctum-list { display: flex; flex-direction: column; gap: 8px; }',
        '.sf-lobby-sanctum-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-radius: 12px; background: #FAF8F5; border: 1px solid rgba(197,160,89,0.34); font-size: 13px; font-weight: 800; color: #111827; transition: border-color 150ms ease, background-color 150ms ease; }',
        '.sf-lobby-sanctum-row.is-clickable { cursor: pointer; }',
        '.sf-lobby-sanctum-row.is-clickable:hover { border-color: #C5A059; background: #FFFFFF; }',
        '.sf-lobby-sanctum-row.is-done { background: #FFFFFF; border-color: #C5A059; cursor: default; }',
        '.sf-lobby-sanctum-check { width: 24px; height: 24px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 900; flex-shrink: 0; background: #FEF2F2; border: 1.5px solid #FECACA; color: #DC2626; line-height: 1; }',
        '.sf-lobby-sanctum-row.is-done .sf-lobby-sanctum-check { background: #FAF6EE; border-color: #C5A059; color: #C5A059; }',
        '.sf-lobby-inline-panel { padding: 12px; border-radius: 14px; background: #FAF8F5; border: 1px solid rgba(197,160,89,0.45); display: flex; flex-direction: column; gap: 9px; }',
        '.sf-lobby-inline-input { width: 100%; padding: 9px 11px; border-radius: 10px; border: 1px solid rgba(197,160,89,0.55); background: #FFFFFF; color: #111827; font-size: 12.5px; font-weight: 600; outline: none; box-sizing: border-box; }',
        '.sf-lobby-inline-input:focus { border-color: #C5A059; box-shadow: 0 0 0 2px rgba(197,160,89,0.18); }',
        '.sf-lobby-pwd-wrap { position: relative; width: 100%; display: flex; align-items: center; }',
        '.sf-lobby-pwd-wrap .sf-lobby-inline-input { padding-right: 38px; }',
        '.sf-lobby-pwd-eye { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border-radius: 8px; border: none; background: transparent; color: #6B7280; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; }',
        '.sf-lobby-pwd-eye:hover { color: #111827; background: #FAF6EE; }',
        '.sf-lobby-inline-error { padding: 7px 10px; border-radius: 9px; background: #FEF2F2; border: 1px solid #FECACA; color: #B91C1C; font-size: 11.5px; font-weight: 700; }',
        '.sf-lobby-inline-check { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: #111827; cursor: pointer; }',
        '.sf-lobby-gold-crest-bar { position: absolute; left: 0; right: 0; bottom: 0; min-height: 56px; padding: 6px 20px calc(8px + env(safe-area-inset-bottom, 0px)); background: linear-gradient(180deg, rgba(250,248,245,0.88) 0%, rgba(255,255,255,0.98) 38%, #FAF6EE 100%); border-top: 2px solid #C5A059; box-shadow: 0 -8px 28px rgba(17,24,39,0.09), 0 1px 0 rgba(255,255,255,0.9) inset; display: flex; align-items: center; justify-content: center; z-index: 26; pointer-events: auto; box-sizing: border-box; }',
        '.sf-lobby-gold-crest-bar::before { content: ""; position: absolute; top: 3px; left: 18px; right: 18px; height: 1px; background: linear-gradient(90deg, transparent 0%, rgba(197,160,89,0.55) 18%, rgba(197,160,89,0.85) 50%, rgba(197,160,89,0.55) 82%, transparent 100%); pointer-events: none; }',
        '.sf-lobby-copyright { position: absolute; left: 20px; bottom: calc(16px + env(safe-area-inset-bottom, 0px)); font-size: 11px; font-weight: 700; letter-spacing: 0.03em; color: #7C6334; white-space: nowrap; pointer-events: none; }',
        '.sf-lobby-gear-btn { appearance: none; background: #FFFFFF; color: #111827; border: 1.5px solid rgba(197,160,89,0.65); border-radius: 999px; padding: 7px 14px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 7px; cursor: pointer; box-shadow: 0 6px 16px rgba(17,24,39,0.08); }',
        '.sf-lobby-gear-btn:hover, .sf-lobby-gear-btn.is-active { border-color: #C5A059; background: #FAF6EE; }',
        '.sf-outer-gate-banner { background: #FAF8F5; border: 1.5px solid #C5A059; border-radius: 14px; padding: 14px 16px; text-align: center; font-size: 13.5px; font-weight: 600; line-height: 1.55; color: #1F2937; }',
        '.sf-atelier-star-card { pointer-events: auto; width: 274px; max-width: calc(100vw - 32px); background: #FAF8F5; color: #111827; border: 1.5px solid #C5A059; border-radius: 16px; padding: 13px 15px; box-shadow: 0 18px 42px rgba(17,24,39,0.16); display: flex; flex-direction: column; align-items: center; gap: 9px; box-sizing: border-box; text-align: center; }',
        '.sf-atelier-star-title { margin: 0; font-family: Georgia, "Times New Roman", serif; font-size: 14px; font-weight: 700; color: #111827; line-height: 1.25; }',
        '.sf-atelier-star-row { display: inline-flex; align-items: center; justify-content: center; gap: 5px; }',
        '.sf-atelier-star-btn { appearance: none; border: none; background: transparent; padding: 2px 3px; font-size: 27px; line-height: 1; color: #DDD5C4; cursor: pointer; transition: transform 0.14s ease, color 0.14s ease; }',
        '.sf-atelier-star-btn.is-lit, .sf-atelier-star-btn:hover { color: #C5A059; }',
        '.sf-atelier-star-btn:hover { transform: scale(1.16); }',
        '.sf-atelier-star-preview { font-size: 24px; line-height: 1; color: #C5A059; letter-spacing: 3px; }',
        '.sf-atelier-star-thanks { display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 12px; font-weight: 800; color: #785A28; }',
        '.sf-atelier-star-edit-btn { appearance: none; border: 1px solid rgba(197,160,89,0.45); background: #FFFFFF; color: #4B5563; border-radius: 999px; padding: 2px 8px; font-size: 10.5px; font-weight: 700; cursor: pointer; }',
        '.sf-atelier-star-feedback { width: 100%; display: flex; flex-direction: column; gap: 7px; margin-top: 2px; }',
        '.sf-atelier-star-textarea { width: 100%; min-height: 58px; max-height: 110px; resize: vertical; border-radius: 10px; border: 1px solid rgba(197,160,89,0.52); background: #FFFFFF; color: #111827; padding: 7px 9px; font-size: 12px; font-family: inherit; box-sizing: border-box; outline: none; }',
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
        '.sf-lobby-stick-wrap { display: none; position: absolute; bottom: calc(74px + env(safe-area-inset-bottom, 0px)); left: 14px; z-index: 25; }',
        '.sf-lobby-stick { width: 78px; height: 78px; border-radius: 999px; background: rgba(255,255,255,0.90); border: 1.5px solid rgba(197,160,89,0.58); box-shadow: 0 10px 26px rgba(17,24,39,0.12); display: flex; align-items: center; justify-content: center; position: relative; touch-action: none; cursor: pointer; }',
        '.sf-lobby-stick-knob { width: 32px; height: 32px; border-radius: 999px; background: #C5A059; border: 2px solid #FFFFFF; box-shadow: 0 4px 12px rgba(17,24,39,0.18); pointer-events: none; }',
        '.sf-lobby-bottom-stack { position: absolute; bottom: calc(66px + env(safe-area-inset-bottom, 0px)); left: 12px; right: 12px; z-index: 24; display: flex; flex-direction: column; align-items: center; gap: 7px; pointer-events: none; }',
        '.sf-lobby-bottom-pill { pointer-events: auto; display: inline-flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: rgba(255,255,255,0.95); border: 1px solid rgba(197,160,89,0.48); box-shadow: 0 10px 28px rgba(17,24,39,0.12); max-width: 100%; box-sizing: border-box; }',
        '.sf-lobby-step-btn { appearance: none; padding: 6px 12px; border-radius: 999px; border: 1px solid rgba(197,160,89,0.40); background: #FAF8F5; color: #111827; font-size: 11px; font-weight: 800; cursor: pointer; white-space: nowrap; }',
        '.sf-lobby-step-btn.is-active { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }',
        '.sf-lobby-chat-panel { position: absolute; bottom: calc(68px + env(safe-area-inset-bottom, 0px)); right: 16px; z-index: 28; pointer-events: auto; }',
        '.sf-lobby-chat-box { width: 292px; max-width: calc(100vw - 24px); background: rgba(255,255,255,0.97); border: 1.5px solid #C5A059; border-radius: 16px; padding: 10px 12px; box-shadow: 0 14px 34px rgba(17,24,39,0.14); display: flex; flex-direction: column; gap: 8px; box-sizing: border-box; }',
        '@media (max-width: 900px), (pointer: coarse) { .sf-lobby-topbar { padding: 8px 10px; gap: 6px; } .sf-lobby-actions { margin-right: 0; } .sf-lobby-unit-frame { padding: 5px 11px 5px 6px; gap: 7px; } .sf-lobby-unit-avatar { width: 30px; height: 30px; font-size: 11px; } .sf-lobby-stick-wrap { display: block; bottom: calc(66px + env(safe-area-inset-bottom, 0px)); left: 10px; } .sf-lobby-stick { width: 68px; height: 68px; } .sf-lobby-topright-stack { top: 62px; right: 10px; gap: 6px; } .sf-lobby-minimap-wrap { width: 138px; height: 138px; } .sf-lobby-minimap-time { font-size: 9.5px; padding: 1px 7px; } .sf-lobby-ping-tooltip { right: -48px; width: 202px; } }',
        '@media (max-width: 767px) { .sf-lobby-zone-badge { padding: 4px 8px; font-size: 10px; } .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 5px 8px; font-size: 11px; } .sf-lobby-kbd, .sf-lobby-copyright { display: none !important; } .sf-lobby-keybind-grid { grid-template-columns: 1fr; } .sf-lobby-gold-crest-bar { min-height: 52px; padding: 5px 8px calc(6px + env(safe-area-inset-bottom, 0px)); justify-content: center; } }',
        '@media (max-width: 480px) { .sf-lobby-topright-stack { top: 64px; right: 8px; } .sf-lobby-stick-wrap { bottom: calc(62px + env(safe-area-inset-bottom, 0px)); left: 8px; } .sf-lobby-stick { width: 64px; height: 64px; } .sf-lobby-floating-card { width: 266px; padding: 12px 13px 10px; } .sf-lobby-prod-title { font-size: 13.5px; } }'
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
        } else if (!existing.textContent || existing.textContent.indexOf('.sf-lobby-ping-anchor') === -1) {
            existing.textContent = STANDALONE_SCOPED_CSS;
        }
    }

    function dispatchLivewireAuthSync() {
        if (typeof window !== 'undefined' && window.Livewire && typeof window.Livewire.dispatch === 'function') {
            window.Livewire.dispatch('auth-status-changed');
            window.Livewire.dispatch('cart-updated');
            window.Livewire.dispatch('wishlist-updated');
        }
    }

    function updateCsrfMetaToken(nextToken) {
        if (!nextToken || typeof document === 'undefined') return;
        var clean = String(nextToken).trim();
        if (!clean) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.setAttribute('name', 'csrf-token');
            document.head.appendChild(meta);
        }
        meta.setAttribute('content', clean);
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
        this.floatingOuterGateWrap = null;
        this.floatingStarStationWrap = null;
        this.minimapCanvasEl = null;

        this._mod = null;
        this._audio = null;
        this._audioUnsub = null;
        this._syncTimer = null;
        this._urgentSyncWatchTimer = null;
        this._pingProbeTimer = null;
        this._pingWarmupTimer = null;
        this._hudTimer = null;
        this._syncInFlight = false;
        this._syncPendingImmediate = false;
        this._lastSyncSentAtMs = 0;
        this._lastWatchSyncSnap = null;
        this._pingProbeInFlight = false;
        this._netSampleIsDirectWire = false;
        this._rttWindow = [];
        this._resetPingOnNextWireSample = false;
        this.wsConnected = false;
        this._lobbyWs = null;
        this._wsTickWorker = null;
        this._wsTickWorkerUrl = null;
        this._wsSendTimer = null;
        this._wsPingTimer = null;
        this._wsWarmupPingTimer = null;
        this._wsHandshakeFallbackTimer = null;
        this._wsUrgentTimer = null;
        this._wsTickMs = 33;
        this._lastWsSentAtMs = 0;
        this._lastWsPingSentAtMs = 0;
        this._lastWsSentState = null;
        this._activeSyncZone = null;
        this._openSeq = 0;
        this._fsResizeTimer = null;
        this.prevHtmlOverflow = '';
        this.prevBodyOverflow = '';
        this.pingTooltipOpen = false;
        this.netStats = {
            pingMs: null,
            jitterMs: 0,
            tickHz: 'Atelier · Bereit',
            tickIntervalMs: 165,
            lossPct: 0,
            hasError: false,
            samples: 0,
            lastRttMs: 0,
            sentCount: 0,
            failCount: 0
        };

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
        this.funkiInfo = { is_guest: false, level: 2, name: '', player_id: null };

        this.lobbyAuth = false;
        this.mmorpgCanEnter = false;
        this.mmorpgEnterUrl = '/funkenreich/enter';
        this.atelierReview = {
            area: 'funkenreich',
            label: '3D-Atelier',
            can_rate: false,
            has_rated: false,
            my_rating: 0,
            my_comment: '',
            submit_url: '/customer/experience-reviews'
        };
        this.atelierStarHover = 0;
        this.atelierRatingEditing = false;
        this.atelierFeedbackOpen = false;
        this.atelierFeedbackDraft = '';
        this.atelierRatingBusy = false;
        this._loginOpenedFromStarStation = false;
        this.soloMode = false;
        this.multiplayerDisabled = false;
        this._wsDisabledByServer = false;
        this.instanceId = 1;
        this.instanceCapacity = 20;
        this.instances = [{ id: 1, count: 1, capacity: 20, is_full: false, is_current: true }];
        this.peers = [];
        this.instanceMenuOpen = false;

        this.settingsOpen = false;
        this.sanctumGateOpen = false;
        this.outerGateModalOpen = false;
        this.chatOpen = false;
        this.chatMessages = [];
        this.chatStrikeInfo = {
            strikes: 0,
            isMuted: false,
            remainingSec: 0,
            permanent: false,
            bannerText: '',
            level: ''
        };
        this._chatMuteTimer = null;
        this._chatMuteUntilPerfMs = 0;
        this.unreadChatCount = 0;
        this._seenMessageIds = {};
        this._loggedInDuringSession = false;
        this.sanctumGateZone = 'heroes';
        this.sanctumExpandedStep = '';
        this.inlineLoginShowPassword = false;
        this.inlineLoginTwoFactorRequired = false;
        this.inlineAuthBusy = false;
        this.inlineAuthError = '';
        this.sanctumGateState = {
            canEnter: false,
            reason: 'login_required',
            checks: { login: false, magic: false },
            loginUrl: '/login',
            dashboardUrl: '/dashboard'
        };
        this.environmentInfo = {
            season_key: 'autumn',
            season_label: 'Herbst',
            holiday_key: null,
            holiday_label: null,
            server_time: '12:00'
        };
        this.playerHud = {
            isAuthenticated: false,
            canEnterSanctums: false,
            mmorpgCanEnter: false,
            mmorpgEnterUrl: '/funkenreich/enter',
            name: 'Funki',
            level: 2,
            levelTitle: 'Lehrling',
            funkenBalance: 0,
            statusHintText: ''
        };

        this.keybindingsExpanded = false;
        this.listeningKeyAction = '';
        this.userSettings = {
            volume: 5,
            cameraSensitivity: 100,
            useRealName: false,
            graphicsQuality: 'high'
        };
        this.keybindings = {
            jump: 'Space',
            moveForward: 'KeyW',
            moveLeft: 'KeyA',
            moveBack: 'KeyS',
            moveRight: 'KeyD'
        };
        this._productTransitionTimer = null;
        this._productTransitioning = false;
        this.audioState = {
            bgmVolume: 5,
            bgmMuted: false,
            sfxVolume: 5,
            sfxMuted: false,
            allMuted: false
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
                var fEsc = getFacade(self);
                if (fEsc && typeof fEsc.releasePointerLock === 'function') {
                    fEsc.releasePointerLock();
                }
                if (self.sanctumGateOpen) {
                    self._setSanctumGateOpen(false);
                } else if (self.outerGateModalOpen) {
                    self._setOuterGateModalOpen(false);
                } else if (self.settingsOpen) {
                    self._setSettingsOpen(false);
                } else if (self.instanceMenuOpen) {
                    self._setInstanceMenuOpen(false);
                } else {
                    self._setSettingsOpen(true);
                }
                return;
            }

            var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

            if (!self.settingsOpen && !self.outerGateModalOpen && (code === 'KeyF' || key === 'f' || key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey) {
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
            if (!self.modalEl) return;
            if (self.instanceMenuOpen) {
                var wrap = self.modalEl.querySelector('[data-lobby-instance-wrap]');
                if (wrap && !wrap.contains(e.target)) {
                    self._setInstanceMenuOpen(false);
                }
            }
            if (self.pingTooltipOpen) {
                var pingAnchor = self.modalEl.querySelector('[data-lobby-ping-anchor]');
                if (pingAnchor && !pingAnchor.contains(e.target)) {
                    self.pingTooltipOpen = false;
                    pingAnchor.classList.remove('is-open');
                }
            }
        };

        this._boundPageHide = function () {
            if (self.isOpen) {
                self._closeLobbyWebSocket(true);
                self._sendLeaveBeacon();
                self.dispose();
            } else {
                stopPrewarmAndForceLobbySilence();
            }
        };

        this._boundLivewireNavigating = function () {
            if (self.isOpen) {
                self.close();
            } else {
                stopPrewarmAndForceLobbySilence();
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
        root.setAttribute('data-sf-3d-lobby-modal', 'heroes');
        root.className = 'sf-lobby-modal is-closed';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-label', 'Mein Seelenfunke 3D-Atelier und Halle der Helden');
        root.style.cssText = 'position:fixed !important;top:0 !important;left:0 !important;right:0 !important;bottom:0 !important;width:100vw !important;height:100dvh !important;z-index:2147483000 !important;background:#FAF8F5 !important;overflow:hidden !important;margin:0 !important;padding:0 !important;';

        root.innerHTML = [
            '<div class="sf-lobby-topbar">',
            '  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;pointer-events:none;">',
            '    <div data-lobby-unit-frame data-testid="lobby-player-unit-frame" class="sf-lobby-unit-frame">',
            '      <span class="sf-lobby-unit-avatar">✦</span>',
            '      <div class="sf-lobby-unit-meta" style="font-size:11.5px;font-weight:800;line-height:1.2;">',
            '        <span data-lobby-unit-name style="max-width:128px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Funki</span>',
            '        <span data-lobby-unit-stats style="color:#9A7738;white-space:nowrap;">Stufe 2 · ✨ 0</span>',
            '      </div>',
            '    </div>',
            '    <div class="sf-lobby-nav-pill">',
            '      <span data-lobby-current-zone-badge data-testid="lobby-current-zone-badge" class="sf-lobby-zone-badge">🛍️ Kollektion</span>',
            '      <button type="button" data-lobby-stages-btn data-testid="lobby-stages-preview-btn" class="sf-lobby-stages-btn" style="display:none;">✨ Stufen</button>',
            '    </div>',
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
            '  </div>',
            '</div>',
            '<div class="sf-lobby-stage">',
            '  <canvas data-lobby-canvas class="sf-lobby-canvas"></canvas>',
            '  <div data-lobby-ivory-transition class="sf-lobby-ivory-transition"></div>',
            '  <div class="sf-lobby-topright-stack">',
            '    <div data-lobby-minimap data-testid="lobby-minimap" class="sf-lobby-minimap-wrap">',
            '      <div data-lobby-ping-anchor class="sf-lobby-ping-anchor">',
            '        <button type="button" data-lobby-ping-dot data-testid="lobby-minimap-ping-dot" class="sf-lobby-ping-dot is-optimal" aria-label="Serververbindung">',
            '          <span class="sf-lobby-ping-pulse"></span>',
            '          <span class="sf-lobby-ping-core"></span>',
            '        </button>',
            '        <div data-lobby-ping-tooltip data-testid="lobby-minimap-ping-tooltip" class="sf-lobby-ping-tooltip" role="status">',
            '          <div class="sf-lobby-ping-head"><span>Serververbindung</span><span data-ping-status-badge class="sf-lobby-ping-badge">Optimal</span></div>',
            '          <div class="sf-lobby-ping-row"><span>Ping</span><strong data-ping-ms-val>-- ms</strong></div>',
            '          <div class="sf-lobby-ping-row"><span>Jitter</span><strong data-ping-jitter-val>±0 ms</strong></div>',
            '          <div class="sf-lobby-ping-row"><span>Modus</span><strong data-ping-mode-val>Atelier (Solo)</strong></div>',
            '          <div data-ping-instance-row class="sf-lobby-ping-row" style="display:none;"><span>Instanz</span><strong data-ping-instance-val>Halle #1 (1/20)</strong></div>',
            '        </div>',
            '      </div>',
            '      <canvas data-lobby-minimap-canvas width="232" height="232" class="sf-lobby-minimap-canvas"></canvas>',
            '      <span data-lobby-minimap-time class="sf-lobby-minimap-time" title="Herbst">12:00</span>',
            '    </div>',
            '  </div>',
            '  <div data-lobby-loading class="sf-lobby-loading">',
            '    <div class="sf-lobby-spinner"></div>',
            '    <span data-lobby-loading-text class="sf-lobby-loading-label">3D-Atelier wird geöffnet</span>',
            '  </div>',
            '  <div data-lobby-product-card data-testid="louvre-floating-space-card" class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div data-lobby-product-inner class="sf-lobby-floating-card"></div>',
            '    <div class="sf-lobby-floating-arrow"></div>',
            '  </div>',
            '  <div data-lobby-hero-card class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div data-lobby-hero-inner class="sf-lobby-floating-card"></div>',
            '    <div class="sf-lobby-floating-arrow"></div>',
            '  </div>',
            '  <div data-lobby-portal-card data-testid="lobby-guest-portal-card" class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <button type="button" data-lobby-portal-pill class="sf-lobby-portal-pill">🔒 Halle der Helden · Voraussetzungen →</button>',
            '  </div>',
            '  <div data-lobby-outer-gate-card data-testid="lobby-outer-gate-card" class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <button type="button" data-outer-gate-pill class="sf-lobby-portal-pill">✨ Tor zur Außenwelt · Fantastische Welt &amp; Abenteuer in Planung</button>',
            '  </div>',
            '  <div data-lobby-star-station-card class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div data-atelier-star-inner data-testid="atelier-star-rating-card" class="sf-atelier-star-card"></div>',
            '    <div class="sf-lobby-floating-arrow"></div>',
            '  </div>',
            '  <div data-lobby-outer-gate-modal data-testid="lobby-outer-gate-modal" class="sf-lobby-settings-backdrop" style="display:none;">',
            '    <div class="sf-lobby-settings-card" role="dialog" aria-label="Tor zur Außenwelt">',
            '      <div class="sf-lobby-settings-head">',
            '        <h3 class="sf-lobby-settings-title"><span>✨</span><span>Tor zur Außenwelt</span></h3>',
            '        <button type="button" data-outer-gate-close style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;" aria-label="Schließen">&times;</button>',
            '      </div>',
            '      <div class="sf-outer-gate-banner">Hinter diesem Tor warten bald eine fantastische Welt und spannende Abenteuer auf dich, an denen das Team von Mein-Seelenfunke in Zukunft arbeiten möchte.</div>',
            '      <div data-outer-gate-cta-wrap style="display:flex;flex-direction:column;gap:8px;"></div>',
            '    </div>',
            '  </div>',
            '  <div data-lobby-sanctum-modal data-testid="lobby-sanctum-gate-modal" class="sf-lobby-settings-backdrop" style="display:none;">',
            '    <div class="sf-lobby-settings-card" role="dialog" aria-label="Zugangsvoraussetzungen">',
            '      <div class="sf-lobby-settings-head">',
            '        <h3 class="sf-lobby-settings-title"><span data-sanctum-icon>🏛️</span><span data-sanctum-title>Halle der Helden</span></h3>',
            '        <button type="button" data-sanctum-close style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;" aria-label="Schließen">&times;</button>',
            '      </div>',
            '      <div class="sf-lobby-sanctum-list">',
            '        <div data-sanctum-row="login" data-testid="sanctum-check-login" class="sf-lobby-sanctum-row is-clickable"><span>Angemeldet</span><span data-sanctum-chk="login" class="sf-lobby-sanctum-check">✕</span></div>',
            '        <form data-sanctum-login-panel data-testid="sanctum-inline-login-form" class="sf-lobby-inline-panel" style="display:none;">',
            '          <input type="email" data-sanctum-login-email placeholder="E-Mail" autocomplete="email" required aria-label="E-Mail" class="sf-lobby-inline-input">',
            '          <div class="sf-lobby-pwd-wrap">',
            '            <input type="password" data-sanctum-login-pwd placeholder="Passwort" autocomplete="current-password" required aria-label="Passwort" class="sf-lobby-inline-input">',
            '            <button type="button" data-sanctum-pwd-eye data-testid="sanctum-login-password-eye" class="sf-lobby-pwd-eye" aria-label="Passwort anzeigen" title="Passwort anzeigen">',
            '              <svg data-eye-open width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
            '              <svg data-eye-closed width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>',
            '            </button>',
            '          </div>',
            '          <input type="text" inputmode="numeric" maxlength="10" data-sanctum-login-2fa placeholder="2FA-Code" autocomplete="one-time-code" aria-label="2FA-Code" class="sf-lobby-inline-input" style="display:none;">',
            '          <div data-sanctum-login-error class="sf-lobby-inline-error" style="display:none;"></div>',
            '          <button type="submit" data-sanctum-login-submit data-testid="sanctum-inline-login-submit" class="sf-lobby-prod-cta" style="margin-top:2px;border:none;">Anmelden</button>',
            '        </form>',
            '        <div data-sanctum-row="magic" data-testid="sanctum-check-magic" class="sf-lobby-sanctum-row is-clickable"><span>✨ Magie aktivieren</span><span data-sanctum-chk="magic" class="sf-lobby-sanctum-check">✕</span></div>',
            '        <div data-sanctum-magic-panel data-testid="sanctum-inline-magic-panel" class="sf-lobby-inline-panel" style="display:none;">',
            '          <label class="sf-lobby-inline-check"><input type="checkbox" data-sanctum-magic-terms data-testid="sanctum-magic-terms-checkbox" checked style="width:16px;height:16px;accent-color:#C5A059;cursor:pointer;"><span>Teilnahmebedingungen akzeptieren</span></label>',
            '          <label class="sf-lobby-inline-check"><input type="checkbox" data-sanctum-magic-ranking data-testid="sanctum-magic-ranking-checkbox" checked style="width:16px;height:16px;accent-color:#C5A059;cursor:pointer;"><span>In der Halle der Helden sichtbar</span></label>',
            '          <div data-sanctum-magic-error class="sf-lobby-inline-error" style="display:none;"></div>',
            '          <button type="button" data-sanctum-magic-submit data-testid="sanctum-inline-magic-submit" class="sf-lobby-prod-cta" style="margin-top:2px;border:none;cursor:pointer;">✨ Magie aktivieren</button>',
            '        </div>',
            '      </div>',
            '      <div data-sanctum-cta-wrap></div>',
            '    </div>',
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
            '          <div style="display:flex;align-items:center;gap:8px;">',
            '            <span data-lobby-volume-label style="font-size:11px;font-weight:800;color:#9A7738;">5 %</span>',
            '            <button type="button" data-lobby-settings-mute data-testid="lobby-settings-mute-btn" class="sf-lobby-step-btn is-active" style="padding:4px 10px;font-size:10.5px;">🔊 Ton an</button>',
            '          </div>',
            '        </div>',
            '        <input type="range" min="0" max="100" step="1" value="5" data-lobby-volume-slider data-testid="lobby-settings-volume-slider" class="sf-lobby-slider" aria-label="Lautstärke">',
            '      </div>',
            '      <div class="sf-lobby-settings-section">',
            '        <div class="sf-lobby-settings-row">',
            '          <span class="sf-lobby-settings-label">Vollbild</span>',
            '          <button type="button" data-lobby-settings-fullscreen data-testid="lobby-settings-fullscreen-btn" class="sf-lobby-step-btn">Vollbild aktivieren</button>',
            '        </div>',
            '      </div>',
            '      <div class="sf-lobby-settings-section">',
            '        <div class="sf-lobby-settings-row">',
            '          <span class="sf-lobby-settings-label">Kamera-Tempo</span>',
            '          <span data-lobby-camera-label style="font-size:11px;font-weight:800;color:#9A7738;">100 %</span>',
            '        </div>',
            '        <input type="range" min="50" max="150" step="1" value="100" data-lobby-camera-slider data-testid="lobby-camera-sensitivity-slider" class="sf-lobby-slider" aria-label="Kamera-Tempo">',
            '      </div>',
            '      <div class="sf-lobby-settings-section">',
            '        <div class="sf-lobby-settings-row">',
            '          <span class="sf-lobby-settings-label">Grafik-Stufe</span>',
            '          <div style="display:flex;align-items:center;gap:6px;">',
            '            <button type="button" data-lobby-gfx="high" class="sf-lobby-step-btn is-active">Hoch</button>',
            '            <button type="button" data-lobby-gfx="balanced" class="sf-lobby-step-btn">Ausgewogen</button>',
            '          </div>',
            '        </div>',
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
            '      <div style="display:flex;align-items:center;gap:8px;padding-top:2px;">',
            '        <button type="button" data-lobby-settings-resume class="sf-lobby-prod-cta" style="margin-top:0;flex:1;border:none;cursor:pointer;">Weiter umsehen</button>',
            '        <button type="button" data-lobby-settings-leave data-testid="lobby-settings-exit-btn" class="sf-lobby-step-btn" style="padding:9px 14px;border-radius:12px;">Atelier verlassen</button>',
            '      </div>',
            '    </div>',
            '  </div>',
            '  <div class="sf-lobby-stick-wrap">',
            '    <div data-lobby-stick class="sf-lobby-stick" aria-label="Bewegungssteuerung">',
            '      <div data-lobby-stick-knob class="sf-lobby-stick-knob"></div>',
            '    </div>',
            '  </div>',
            '  <div class="sf-lobby-bottom-stack">',
            '    <div data-lobby-bottom-heroes class="sf-lobby-bottom-pill" style="display:none;">',
            '      <button type="button" data-hero-slot="1" class="sf-lobby-step-btn">#1</button>',
            '      <button type="button" data-hero-slot="2" class="sf-lobby-step-btn">#2</button>',
            '      <button type="button" data-hero-slot="3" class="sf-lobby-step-btn">#3</button>',
            '      <button type="button" data-hero-slot="4" class="sf-lobby-step-btn">#4</button>',
            '      <button type="button" data-hero-slot="5" class="sf-lobby-step-btn">#5</button>',
            '      <button type="button" data-hero-my-place class="sf-lobby-step-btn is-active">⭐ Mein Platz</button>',
            '    </div>',
            '  </div>',
            '  <div data-lobby-chat-panel class="sf-lobby-chat-panel" style="display:none;">',
            '    <button type="button" data-lobby-chat-toggle class="sf-lobby-pill-btn">💬 Chat <span data-lobby-chat-unread style="display:none;padding:1px 6px;border-radius:999px;background:#C5A059;color:#FFFFFF;font-size:10px;font-weight:900;">0</span></button>',
            '    <div data-lobby-chat-box class="sf-lobby-chat-box" style="display:none;margin-top:6px;">',
            '      <div style="display:flex;align-items:center;justify-content:space-between;">',
            '        <span style="font-size:12px;font-weight:900;color:#111827;">Hallen-Chat</span>',
            '        <button type="button" data-lobby-chat-close style="appearance:none;border:none;background:transparent;font-size:15px;font-weight:900;color:#6B7280;cursor:pointer;">&times;</button>',
            '      </div>',
            '      <div data-lobby-chat-messages style="max-height:140px;overflow-y:auto;display:flex;flex-direction:column;gap:5px;font-size:11.5px;"></div>',
            '      <div data-lobby-chat-mod-banner role="alert" style="display:none;padding:7px 9px;border-radius:10px;background:#FEF2F2;border:1px solid #FECACA;border-left:3px solid #DC2626;flex-direction:column;gap:3px;">',
            '        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;flex-wrap:wrap;">',
            '          <span data-lobby-chat-mod-badge style="display:inline-flex;align-items:center;padding:2px 7px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:9.5px;font-weight:900;letter-spacing:0.03em;text-transform:uppercase;">Hausordnung</span>',
            '          <span data-lobby-chat-mod-timer style="display:none;font-size:10px;font-weight:800;color:#991B1B;white-space:nowrap;"></span>',
            '        </div>',
            '        <div data-lobby-chat-mod-text style="font-size:11px;font-weight:700;color:#7F1D1D;line-height:1.35;word-break:break-word;"></div>',
            '      </div>',
            '      <form data-lobby-chat-form style="display:flex;gap:6px;">',
            '        <input type="text" data-lobby-chat-input maxlength="120" placeholder="Nachricht..." class="sf-lobby-inline-input" style="padding:6px 9px;font-size:12px;">',
            '        <button type="submit" data-lobby-chat-submit class="sf-lobby-instance-submit" style="flex:0 0 auto;padding:6px 11px;">Senden</button>',
            '      </form>',
            '    </div>',
            '  </div>',
            '  <div data-testid="lobby-gold-crest-bar" class="sf-lobby-gold-crest-bar">',
            '    <span class="sf-lobby-copyright">© Mein-Seelenfunke</span>',
            '    <button type="button" data-lobby-settings-toggle data-testid="louvre-settings-btn" class="sf-lobby-gear-btn" title="Einstellungen (Esc)" aria-label="Einstellungen">',
            '      <span>⚙️</span>',
            '      <span>Einstellungen</span>',
            '      <span class="sf-lobby-kbd">Esc</span>',
            '    </button>',
            '  </div>',
            '</div>'
        ].join('');

        document.body.appendChild(root);
        this.modalEl = root;
        this.canvasEl = root.querySelector('[data-lobby-canvas]');
        this.minimapCanvasEl = root.querySelector('[data-lobby-minimap-canvas]');
        this.loadingEl = root.querySelector('[data-lobby-loading]');
        this.floatingProductWrap = root.querySelector('[data-lobby-product-card]');
        this.floatingHeroWrap = root.querySelector('[data-lobby-hero-card]');
        this.floatingPortalWrap = root.querySelector('[data-lobby-portal-card]');
        this.floatingOuterGateWrap = root.querySelector('[data-lobby-outer-gate-card]');
        this.floatingStarStationWrap = root.querySelector('[data-lobby-star-station-card]');
        this.stickZoneEl = root.querySelector('[data-lobby-stick]');
        this.stickKnobEl = root.querySelector('[data-lobby-stick-knob]');

        this._bindModalUiEvents();
        this._renderKeybindingsList();
        this._renderOuterGateModal();
        this._renderAtelierStarCard();
        this._renderSanctumGateModal();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._setOuterGateModalOpen = function (flag, incomingMmorpgState) {
        this.outerGateModalOpen = Boolean(flag);
        var facade = getFacade(this);
        if (this.outerGateModalOpen && facade && typeof facade.releasePointerLock === 'function') {
            facade.releasePointerLock();
        }
        var resolved = incomingMmorpgState || (facade && typeof facade.getMmorpgAccessState === 'function' ? facade.getMmorpgAccessState() : null);
        if (resolved && typeof resolved === 'object') {
            if (resolved.canEnter !== undefined) {
                this.mmorpgCanEnter = Boolean(resolved.canEnter);
            }
            if (resolved.enterUrl) {
                this.mmorpgEnterUrl = String(resolved.enterUrl);
            }
        }
        this._renderOuterGateModal();
        if (this.modalEl) {
            var modal = this.modalEl.querySelector('[data-lobby-outer-gate-modal]');
            if (modal) {
                modal.style.display = this.outerGateModalOpen ? 'flex' : 'none';
            }
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderOuterGateModal = function () {
        if (!this.modalEl) return;
        var wrap = this.modalEl.querySelector('[data-outer-gate-cta-wrap]');
        if (!wrap) return;
        while (wrap.firstChild) wrap.removeChild(wrap.firstChild);

        var self = this;
        if (this.mmorpgCanEnter) {
            var enterBtn = document.createElement('button');
            enterBtn.type = 'button';
            enterBtn.setAttribute('data-testid', 'outer-gate-enter-mmorpg-btn');
            enterBtn.className = 'sf-lobby-prod-cta';
            enterBtn.style.cssText = 'margin-top:0;border:none;cursor:pointer;';
            enterBtn.textContent = '✨ In die Außenwelt starten →';
            enterBtn.addEventListener('click', function () {
                stopPrewarmAndForceLobbySilence();
                window.location.href = self.mmorpgEnterUrl || '/funkenreich/enter';
            });
            wrap.appendChild(enterBtn);

            var closeSecondary = document.createElement('button');
            closeSecondary.type = 'button';
            closeSecondary.className = 'sf-lobby-step-btn';
            closeSecondary.style.cssText = 'padding:9px 14px;border-radius:12px;width:100%;';
            closeSecondary.textContent = 'Schließen';
            closeSecondary.addEventListener('click', function () {
                self._setOuterGateModalOpen(false);
            });
            wrap.appendChild(closeSecondary);
        } else {
            var okBtn = document.createElement('button');
            okBtn.type = 'button';
            okBtn.className = 'sf-lobby-prod-cta';
            okBtn.style.cssText = 'margin-top:0;border:none;cursor:pointer;';
            okBtn.textContent = 'Verstanden';
            okBtn.addEventListener('click', function () {
                self._setOuterGateModalOpen(false);
            });
            wrap.appendChild(okBtn);
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._setSanctumGateOpen = function (flag, zone, incomingState) {
        this.sanctumGateOpen = Boolean(flag);
        this.inlineAuthError = '';
        this.sanctumGateZone = 'heroes';
        var facade = getFacade(this);
        if (this.sanctumGateOpen && facade && typeof facade.releasePointerLock === 'function') {
            facade.releasePointerLock();
        }
        var resolved = incomingState || (facade && typeof facade.getSanctumAccessState === 'function' ? facade.getSanctumAccessState() : null);
        if (resolved && typeof resolved === 'object') {
            this.sanctumGateState = {
                canEnter: Boolean(resolved.canEnter),
                reason: resolved.reason || 'login_required',
                checks: {
                    login: Boolean(resolved.checks && resolved.checks.login),
                    magic: Boolean(resolved.checks && resolved.checks.magic)
                },
                loginUrl: resolved.loginUrl || '/login',
                dashboardUrl: resolved.dashboardUrl || '/dashboard'
            };
        }
        if (this.sanctumGateOpen) {
            if (!this.sanctumGateState.checks.login) {
                this.sanctumExpandedStep = 'login';
            } else if (!this.sanctumGateState.checks.magic) {
                this.sanctumExpandedStep = 'magic';
            } else {
                this.sanctumExpandedStep = '';
            }
        }
        this._renderSanctumGateModal();
        if (this.modalEl) {
            var modal = this.modalEl.querySelector('[data-lobby-sanctum-modal]');
            if (modal) {
                modal.style.display = this.sanctumGateOpen ? 'flex' : 'none';
            }
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._applyAtelierReviewPayload = function (rev) {
        if (!rev || typeof rev !== 'object') return;
        var rating = Math.max(0, Math.min(5, parseInt(rev.my_rating || 0, 10) || 0));
        this.atelierReview = {
            area: String(rev.area || 'funkenreich'),
            label: String(rev.label || '3D-Atelier'),
            can_rate: Boolean(rev.can_rate),
            has_rated: Boolean(rev.has_rated || rating > 0),
            my_rating: rating,
            my_comment: typeof rev.my_comment === 'string' ? rev.my_comment : '',
            submit_url: String(rev.submit_url || '/customer/experience-reviews')
        };
        this.atelierFeedbackDraft = this.atelierReview.my_comment || '';
        this._renderAtelierStarCard();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderAtelierStarCard = function () {
        if (!this.modalEl) return;
        var inner = this.modalEl.querySelector('[data-atelier-star-inner]');
        if (!inner) return;
        while (inner.firstChild) inner.removeChild(inner.firstChild);

        var self = this;
        var rev = this.atelierReview || {};
        var title = document.createElement('h4');
        title.className = 'sf-atelier-star-title';
        title.textContent = 'Wie gefällt dir unser Atelier?';
        inner.appendChild(title);

        if (rev.can_rate) {
            var activeStars = this.atelierStarHover > 0 ? this.atelierStarHover : (rev.my_rating || 0);
            var row = document.createElement('div');
            row.className = 'sf-atelier-star-row';

            for (var s = 1; s <= 5; s++) {
                (function (starVal) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'sf-atelier-star-btn' + (starVal <= activeStars ? ' is-lit' : '');
                    btn.setAttribute('data-testid', 'atelier-star-btn-' + starVal);
                    btn.setAttribute('aria-label', starVal + ' Sterne');
                    btn.textContent = '★';
                    btn.addEventListener('mouseenter', function () {
                        self.atelierStarHover = starVal;
                        var f = getFacade(self);
                        if (f && typeof f.previewAtelierStarHover === 'function') {
                            f.previewAtelierStarHover(starVal);
                        }
                        var allBtns = row.querySelectorAll('.sf-atelier-star-btn');
                        for (var b = 0; b < allBtns.length; b++) {
                            if (b + 1 <= starVal) allBtns[b].classList.add('is-lit');
                            else allBtns[b].classList.remove('is-lit');
                        }
                    });
                    btn.addEventListener('mouseleave', function () {
                        self.atelierStarHover = 0;
                        var f = getFacade(self);
                        if (f && typeof f.previewAtelierStarHover === 'function') {
                            f.previewAtelierStarHover(0);
                        }
                        var cur = self.atelierReview.my_rating || 0;
                        var allBtns = row.querySelectorAll('.sf-atelier-star-btn');
                        for (var b = 0; b < allBtns.length; b++) {
                            if (b + 1 <= cur) allBtns[b].classList.add('is-lit');
                            else allBtns[b].classList.remove('is-lit');
                        }
                    });
                    btn.addEventListener('click', function () {
                        self._submitAtelierRating(starVal);
                    });
                    row.appendChild(btn);
                })(s);
            }
            inner.appendChild(row);

            if (this.atelierFeedbackOpen) {
                var fbWrap = document.createElement('form');
                fbWrap.className = 'sf-atelier-star-feedback';
                var ta = document.createElement('textarea');
                ta.className = 'sf-atelier-star-textarea';
                ta.placeholder = 'Was können wir noch besser machen?';
                ta.maxLength = 1500;
                ta.value = this.atelierFeedbackDraft || '';
                ta.addEventListener('input', function () {
                    self.atelierFeedbackDraft = ta.value;
                });
                fbWrap.appendChild(ta);

                var sendBtn = document.createElement('button');
                sendBtn.type = 'submit';
                sendBtn.className = 'sf-lobby-prod-cta';
                sendBtn.style.cssText = 'margin-top:0;border:none;cursor:pointer;padding:7px 12px;font-size:12px;';
                sendBtn.textContent = this.atelierRatingBusy ? 'Wird gesendet...' : 'Senden';
                fbWrap.addEventListener('submit', function (ev) {
                    ev.preventDefault();
                    self._submitAtelierFeedback();
                });
                fbWrap.appendChild(sendBtn);
                inner.appendChild(fbWrap);
            } else if (rev.has_rated && !this.atelierRatingEditing) {
                var thanks = document.createElement('div');
                thanks.className = 'sf-atelier-star-thanks';
                var txt = document.createElement('span');
                txt.textContent = '✓ Danke für deine Bewertung!';
                thanks.appendChild(txt);

                var editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'sf-atelier-star-edit-btn';
                editBtn.textContent = 'Ändern';
                editBtn.addEventListener('click', function () {
                    self.atelierRatingEditing = true;
                    if ((self.atelierReview.my_rating || 0) >= 1 && (self.atelierReview.my_rating || 0) <= 3) {
                        self.atelierFeedbackOpen = true;
                    }
                    self._renderAtelierStarCard();
                });
                thanks.appendChild(editBtn);
                inner.appendChild(thanks);
            }
        } else {
            var prev = document.createElement('div');
            prev.className = 'sf-atelier-star-preview';
            prev.textContent = '★★★★★';
            inner.appendChild(prev);

            var loginBtn = document.createElement('button');
            loginBtn.type = 'button';
            loginBtn.setAttribute('data-testid', 'atelier-star-login-btn');
            loginBtn.className = 'sf-lobby-prod-cta';
            loginBtn.style.cssText = 'margin-top:0;border:none;cursor:pointer;padding:8px 14px;font-size:12px;';
            loginBtn.textContent = 'Anmelden & bewerten';
            loginBtn.addEventListener('click', function () {
                self._loginOpenedFromStarStation = true;
                self._setSanctumGateOpen(true, 'heroes');
            });
            inner.appendChild(loginBtn);
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._submitAtelierRating = function (stars) {
        if (this.atelierRatingBusy) return;
        var cleanStars = Math.max(1, Math.min(5, parseInt(stars, 10) || 5));
        var self = this;

        this.atelierRatingBusy = true;
        this.atelierStarHover = 0;
        this.atelierReview.my_rating = cleanStars;
        this.atelierReview.has_rated = true;
        this.atelierRatingEditing = false;
        this.atelierFeedbackOpen = (cleanStars <= 3);
        this._renderAtelierStarCard();

        var facade = getFacade(this);
        if (facade && typeof facade.setAtelierReviewState === 'function') {
            facade.setAtelierReviewState(this.atelierReview, { celebrate: true, stars: cleanStars });
        }

        fetch(this.atelierReview.submit_url || '/customer/experience-reviews', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                area: this.atelierReview.area || 'funkenreich',
                rating: cleanStars,
                keep_existing_comment: true
            })
        })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (body) {
                self.atelierRatingBusy = false;
                if (body && body.ok && body.review) {
                    self.atelierReview.my_rating = parseInt(body.review.rating || cleanStars, 10) || cleanStars;
                    self.atelierReview.has_rated = true;
                    if (typeof body.review.comment === 'string') {
                        self.atelierReview.my_comment = body.review.comment;
                        self.atelierFeedbackDraft = body.review.comment;
                    }
                }
                self._renderAtelierStarCard();
            })
            .catch(function () {
                self.atelierRatingBusy = false;
                self._renderAtelierStarCard();
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype._submitAtelierFeedback = function () {
        if (this.atelierRatingBusy) return;
        var self = this;
        var rating = Math.max(1, Math.min(5, parseInt(this.atelierReview.my_rating || 3, 10) || 3));
        var commentText = String(this.atelierFeedbackDraft || '').trim();
        this.atelierRatingBusy = true;
        this._renderAtelierStarCard();

        fetch(this.atelierReview.submit_url || '/customer/experience-reviews', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                area: this.atelierReview.area || 'funkenreich',
                rating: rating,
                comment: commentText
            })
        })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (body) {
                self.atelierRatingBusy = false;
                self.atelierFeedbackOpen = false;
                self.atelierRatingEditing = false;
                if (body && body.ok && body.review) {
                    self.atelierReview.my_comment = typeof body.review.comment === 'string' ? body.review.comment : commentText;
                } else {
                    self.atelierReview.my_comment = commentText;
                }
                self._renderAtelierStarCard();
            })
            .catch(function () {
                self.atelierRatingBusy = false;
                self.atelierFeedbackOpen = false;
                self._renderAtelierStarCard();
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype._applyLiveAccountPayload = function (body) {
        if (!body || typeof body !== 'object') return;
        if (body.csrf_token) {
            updateCsrfMetaToken(body.csrf_token);
        }
        if (body.atelier_review && typeof body.atelier_review === 'object') {
            this._applyAtelierReviewPayload(body.atelier_review);
        } else if (body.status === 'authenticated' || body.success) {
            this.atelierReview.can_rate = true;
            this._renderAtelierStarCard();
        }
        if (body.mmorpg_can_enter !== undefined) {
            this.mmorpgCanEnter = Boolean(body.mmorpg_can_enter);
        }
        if (body.mmorpg_enter_url) {
            this.mmorpgEnterUrl = String(body.mmorpg_enter_url);
        }
        if (body.player_funki && typeof body.player_funki === 'object') {
            this.funkiInfo = body.player_funki;
            if (!body.player_funki.is_guest) {
                this.lobbyAuth = true;
            }
        }
        if (body.sanctum_access && typeof body.sanctum_access === 'object') {
            var sa = body.sanctum_access;
            if (sa.is_authenticated !== undefined) {
                this.lobbyAuth = Boolean(sa.is_authenticated);
            }
            this.sanctumGateState = {
                canEnter: Boolean(sa.can_enter_sanctums),
                reason: sa.block_reason || null,
                checks: {
                    login: Boolean(sa.is_authenticated),
                    magic: Boolean(sa.gamification_active)
                },
                loginUrl: sa.login_url || '/login',
                dashboardUrl: sa.dashboard_url || '/dashboard'
            };
        }
        var facade = getFacade(this);
        if (facade && typeof facade.applyLiveAccountUpdate === 'function') {
            var nextAccess = facade.applyLiveAccountUpdate(body);
            if (nextAccess && typeof nextAccess === 'object') {
                this.sanctumGateState = {
                    canEnter: Boolean(nextAccess.canEnter),
                    reason: nextAccess.reason || null,
                    checks: {
                        login: Boolean(nextAccess.checks && nextAccess.checks.login),
                        magic: Boolean(nextAccess.checks && nextAccess.checks.magic)
                    },
                    loginUrl: nextAccess.loginUrl || '/login',
                    dashboardUrl: nextAccess.dashboardUrl || '/dashboard'
                };
            }
            if (typeof facade.getPlayerHudState === 'function') {
                this.playerHud = facade.getPlayerHudState();
            }
        }
        this._syncHudState();
        this._syncRealtimeHud();
        this._renderOuterGateModal();
        this._renderSanctumGateModal();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._submitInlineLogin = function () {
        if (this.inlineAuthBusy || !this.modalEl) return;
        var self = this;
        var emailInp = this.modalEl.querySelector('[data-sanctum-login-email]');
        var pwdInp = this.modalEl.querySelector('[data-sanctum-login-pwd]');
        var tfaInp = this.modalEl.querySelector('[data-sanctum-login-2fa]');
        var email = emailInp ? String(emailInp.value || '').trim() : '';
        var password = pwdInp ? String(pwdInp.value || '') : '';
        if (!email || !password) {
            this.inlineAuthError = 'Bitte E-Mail und Passwort eingeben.';
            this._renderSanctumGateModal();
            return;
        }
        this.inlineAuthBusy = true;
        this.inlineAuthError = '';
        this._renderSanctumGateModal();

        var reqBody = { email: email, password: password };
        if (this.inlineLoginTwoFactorRequired && tfaInp && String(tfaInp.value || '').trim() !== '') {
            reqBody.two_factor_code = String(tfaInp.value || '').trim();
        }

        fetch('/funkenwerk-welt/inline-login', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify(reqBody)
        })
            .then(function (r) {
                return r.json().catch(function () { return null; });
            })
            .then(function (body) {
                self.inlineAuthBusy = false;
                if (!body) {
                    self.inlineAuthError = 'Anmeldung fehlgeschlagen.';
                    self._renderSanctumGateModal();
                    return;
                }
                if (body.csrf_token) {
                    updateCsrfMetaToken(body.csrf_token);
                }
                if (body.status === 'two_factor_required') {
                    self.inlineLoginTwoFactorRequired = true;
                    self.inlineAuthError = body.message || 'Bitte 2FA-Code eingeben.';
                    self._renderSanctumGateModal();
                    return;
                }
                if (body.status === 'password_change_required' && body.redirect_url) {
                    stopPrewarmAndForceLobbySilence();
                    window.location.href = String(body.redirect_url);
                    return;
                }
                if (body.status === 'authenticated' || body.success) {
                    if (pwdInp) pwdInp.value = '';
                    if (tfaInp) tfaInp.value = '';
                    self.inlineLoginTwoFactorRequired = false;
                    self._loggedInDuringSession = true;
                    dispatchLivewireAuthSync();
                    self._applyLiveAccountPayload(body);

                    if (self._loginOpenedFromStarStation || self.sanctumGateState.canEnter) {
                        self._loginOpenedFromStarStation = false;
                        self.sanctumExpandedStep = '';
                        self._setSanctumGateOpen(false);
                    } else if (!self.sanctumGateState.checks.magic) {
                        self.sanctumExpandedStep = 'magic';
                        self._renderSanctumGateModal();
                    }
                    return;
                }
                self.inlineAuthError = body.message || 'Zugangsdaten ungültig.';
                self._renderSanctumGateModal();
            })
            .catch(function () {
                self.inlineAuthBusy = false;
                self.inlineAuthError = 'Verbindung fehlgeschlagen.';
                self._renderSanctumGateModal();
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype._submitInlineActivateMagic = function () {
        if (this.inlineAuthBusy || !this.modalEl) return;
        var termsChk = this.modalEl.querySelector('[data-sanctum-magic-terms]');
        var rankChk = this.modalEl.querySelector('[data-sanctum-magic-ranking]');
        if (!termsChk || !termsChk.checked) return;

        var self = this;
        this.inlineAuthBusy = true;
        this.inlineAuthError = '';
        this._renderSanctumGateModal();

        fetch('/funkenwerk-welt/activate-magic', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                accept_terms: true,
                ranking_opt_in: rankChk ? Boolean(rankChk.checked) : true
            })
        })
            .then(function (r) {
                return r.json().catch(function () { return null; });
            })
            .then(function (body) {
                self.inlineAuthBusy = false;
                if (!body) {
                    self.inlineAuthError = 'Aktivierung fehlgeschlagen.';
                    self._renderSanctumGateModal();
                    return;
                }
                if (body.csrf_token) {
                    updateCsrfMetaToken(body.csrf_token);
                }
                if (body.status === 'magic_activated' || body.success) {
                    body.play_awaken_fx = true;
                    self.sanctumExpandedStep = '';
                    self._setSanctumGateOpen(false);
                    self._applyLiveAccountPayload(body);
                    dispatchLivewireAuthSync();
                    return;
                }
                self.inlineAuthError = body.message || 'Aktivierung nicht möglich.';
                self._renderSanctumGateModal();
            })
            .catch(function () {
                self.inlineAuthBusy = false;
                self.inlineAuthError = 'Verbindung fehlgeschlagen.';
                self._renderSanctumGateModal();
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderSanctumGateModal = function () {
        if (!this.modalEl) return;
        var root = this.modalEl;
        var iconEl = root.querySelector('[data-sanctum-icon]');
        var titleEl = root.querySelector('[data-sanctum-title]');
        if (iconEl) iconEl.textContent = '🏛️';
        if (titleEl) titleEl.textContent = 'Halle der Helden';

        var st = this.sanctumGateState || { checks: { login: false, magic: false }, loginUrl: '/login', dashboardUrl: '/dashboard' };
        var checks = st.checks || {};
        var syncRow = function (key, done) {
            var row = root.querySelector('[data-sanctum-row="' + key + '"]');
            var chk = root.querySelector('[data-sanctum-chk="' + key + '"]');
            if (row) {
                if (done) {
                    row.classList.add('is-done');
                    row.classList.remove('is-clickable');
                } else {
                    row.classList.remove('is-done');
                    row.classList.add('is-clickable');
                }
            }
            if (chk) {
                chk.textContent = done ? '✓' : '✕';
            }
        };
        syncRow('login', Boolean(checks.login));
        syncRow('magic', Boolean(checks.magic));

        var loginPanel = root.querySelector('[data-sanctum-login-panel]');
        if (loginPanel) {
            var showLogin = (!checks.login && this.sanctumExpandedStep === 'login');
            loginPanel.style.display = showLogin ? 'flex' : 'none';
            var tfaInp = root.querySelector('[data-sanctum-login-2fa]');
            if (tfaInp) tfaInp.style.display = this.inlineLoginTwoFactorRequired ? 'block' : 'none';
            var logErr = root.querySelector('[data-sanctum-login-error]');
            if (logErr) {
                logErr.style.display = (showLogin && this.inlineAuthError) ? 'block' : 'none';
                logErr.textContent = this.inlineAuthError || '';
            }
            var logSub = root.querySelector('[data-sanctum-login-submit]');
            if (logSub) {
                logSub.disabled = Boolean(this.inlineAuthBusy);
                logSub.textContent = this.inlineAuthBusy ? 'Anmeldung...' : 'Anmelden';
            }
        }

        var magicPanel = root.querySelector('[data-sanctum-magic-panel]');
        if (magicPanel) {
            var showMagic = (Boolean(checks.login) && !checks.magic && this.sanctumExpandedStep === 'magic');
            magicPanel.style.display = showMagic ? 'flex' : 'none';
            var magErr = root.querySelector('[data-sanctum-magic-error]');
            if (magErr) {
                magErr.style.display = (showMagic && this.inlineAuthError) ? 'block' : 'none';
                magErr.textContent = this.inlineAuthError || '';
            }
            var termsChk = root.querySelector('[data-sanctum-magic-terms]');
            var magSub = root.querySelector('[data-sanctum-magic-submit]');
            if (magSub) {
                var okTerms = Boolean(termsChk && termsChk.checked);
                magSub.disabled = Boolean(this.inlineAuthBusy || !okTerms);
                magSub.style.opacity = (this.inlineAuthBusy || !okTerms) ? '0.55' : '1';
                magSub.style.cursor = (this.inlineAuthBusy || !okTerms) ? 'not-allowed' : 'pointer';
                magSub.textContent = this.inlineAuthBusy ? 'Aktivierung...' : '✨ Magie aktivieren';
            }
        }

        var ctaWrap = root.querySelector('[data-sanctum-cta-wrap]');
        if (!ctaWrap) return;
        while (ctaWrap.firstChild) ctaWrap.removeChild(ctaWrap.firstChild);

        var self = this;
        if (!checks.login && this.sanctumExpandedStep !== 'login') {
            var loginBtn = document.createElement('button');
            loginBtn.type = 'button';
            loginBtn.setAttribute('data-testid', 'sanctum-gate-cta-btn');
            loginBtn.className = 'sf-lobby-prod-cta';
            loginBtn.style.cssText = 'margin-top:2px;border:none;cursor:pointer;';
            loginBtn.textContent = 'Anmelden';
            loginBtn.addEventListener('click', function () {
                self.sanctumExpandedStep = 'login';
                self.inlineAuthError = '';
                self._renderSanctumGateModal();
            });
            ctaWrap.appendChild(loginBtn);
        } else if (checks.login && !checks.magic && this.sanctumExpandedStep !== 'magic') {
            var magicBtn = document.createElement('button');
            magicBtn.type = 'button';
            magicBtn.setAttribute('data-testid', 'sanctum-gate-cta-btn');
            magicBtn.className = 'sf-lobby-prod-cta';
            magicBtn.style.cssText = 'margin-top:2px;border:none;cursor:pointer;';
            magicBtn.textContent = '✨ Magie aktivieren';
            magicBtn.addEventListener('click', function () {
                var tChk = root.querySelector('[data-sanctum-magic-terms]');
                if (tChk && tChk.checked) {
                    self._submitInlineActivateMagic();
                } else {
                    self.sanctumExpandedStep = 'magic';
                    self.inlineAuthError = '';
                    self._renderSanctumGateModal();
                }
            });
            ctaWrap.appendChild(magicBtn);
        }
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

    SeelenfunkeUnifiedLobbyHost.prototype._renderMinimap = function () {
        var canvas = this.minimapCanvasEl;
        var facade = getFacade(this);
        if (!canvas || !facade || typeof facade.getMinimapState !== 'function') return;
        var ctx = canvas.getContext('2d');
        if (!ctx) return;
        var snap = facade.getMinimapState();
        if (!snap) return;

        var w = canvas.width || 232;
        var h = canvas.height || 232;
        var sc = w / 180;
        var cx = w * 0.5;
        var cy = h * 0.5;
        var rad = w * 0.5 - 8 * sc;

        ctx.clearRect(0, 0, w, h);
        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, cy, rad + 5 * sc, 0, Math.PI * 2);
        ctx.clip();

        ctx.fillStyle = '#FAF8F5';
        ctx.fillRect(0, 0, w, h);

        ctx.strokeStyle = 'rgba(197,160,89,0.28)';
        ctx.lineWidth = Math.max(1, 1.3 * sc);
        ctx.beginPath();
        ctx.arc(cx, cy, rad * 0.58, 0, Math.PI * 2);
        ctx.stroke();

        ctx.strokeStyle = 'rgba(197,160,89,0.16)';
        ctx.beginPath();
        ctx.moveTo(cx - rad * 0.85, cy);
        ctx.lineTo(cx + rad * 0.85, cy);
        ctx.moveTo(cx, cy - rad * 0.85);
        ctx.lineTo(cx, cy + rad * 0.85);
        ctx.stroke();

        var markers = Array.isArray(snap.markers) ? snap.markers : [];
        for (var i = 0; i < markers.length; i++) {
            var m = markers[i];
            var mx = cx + (m.u - 0.5) * rad * 1.68;
            var my = cy + (m.v - 0.5) * rad * 1.68;
            ctx.beginPath();
            ctx.fillStyle = m.active ? '#C5A059' : 'rgba(17,24,39,0.36)';
            ctx.arc(mx, my, (m.active ? 4.8 : 3.4) * sc, 0, Math.PI * 2);
            ctx.fill();
        }

        if (snap.player) {
            var px = cx + (snap.player.u - 0.5) * rad * 1.68;
            var py = cy + (snap.player.v - 0.5) * rad * 1.68;
            ctx.save();
            ctx.translate(px, py);
            ctx.rotate(-(snap.player.yaw || 0) + Math.PI);
            ctx.fillStyle = '#111827';
            ctx.beginPath();
            ctx.moveTo(0, -8.2 * sc);
            ctx.lineTo(5.8 * sc, 6.4 * sc);
            ctx.lineTo(0, 3.6 * sc);
            ctx.lineTo(-5.8 * sc, 6.4 * sc);
            ctx.closePath();
            ctx.fill();
            ctx.restore();
        }

        ctx.restore();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._syncRealtimeHud = function () {
        var facade = getFacade(this);
        if (!this.modalEl || !facade) return;
        var root = this.modalEl;

        if (typeof facade.getPlayerHudState === 'function') {
            var pHud = facade.getPlayerHudState();
            if (pHud) {
                this.playerHud = pHud;
                if (pHud.mmorpgCanEnter !== undefined) {
                    this.mmorpgCanEnter = Boolean(pHud.mmorpgCanEnter);
                }
                if (pHud.mmorpgEnterUrl) {
                    this.mmorpgEnterUrl = String(pHud.mmorpgEnterUrl);
                }
                if (pHud.environment) {
                    this.environmentInfo = pHud.environment;
                }
            }
        }
        var uName = root.querySelector('[data-lobby-unit-name]');
        var uStats = root.querySelector('[data-lobby-unit-stats]');
        if (uName && this.playerHud) uName.textContent = sanitizeText(this.playerHud.name || 'Funki');
        if (uStats && this.playerHud) uStats.textContent = 'Stufe ' + (this.playerHud.level || 2) + ' · ✨ ' + (this.playerHud.funkenBalance || 0);

        var mTime = root.querySelector('[data-lobby-minimap-time]');
        if (mTime && this.environmentInfo) {
            mTime.textContent = this.environmentInfo.server_time || '12:00';
            mTime.title = this.environmentInfo.holiday_label
                ? (this.environmentInfo.season_label + ' · ' + this.environmentInfo.holiday_label)
                : (this.environmentInfo.season_label || 'Herbst');
        }
        this._renderPingHud();
        this._renderMinimap();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._nowPerfMs = function () {
        return (typeof performance !== 'undefined' && typeof performance.now === 'function')
            ? performance.now()
            : Date.now();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._recordNetRttSample = function (rttMs, isDirectWire, tickLabel) {
        var cleanRtt = Math.max(1, Number(rttMs) || 1);
        var ns = this.netStats;
        var directFlag = Boolean(isDirectWire);
        var switchingChannel = (directFlag !== Boolean(this._netSampleIsDirectWire)) || Boolean(this._resetPingOnNextWireSample);
        if (switchingChannel || !Array.isArray(this._rttWindow)) {
            this._rttWindow = [];
            this._resetPingOnNextWireSample = false;
        }
        this._netSampleIsDirectWire = directFlag;
        this._rttWindow.push(cleanRtt);
        while (this._rttWindow.length > 5) {
            this._rttWindow.shift();
        }
        var sorted = this._rttWindow.slice().sort(function (a, b) { return a - b; });
        var mid = Math.floor(sorted.length / 2);
        var medianRtt = (sorted.length % 2 === 1)
            ? sorted[mid]
            : (sorted[mid - 1] + sorted[mid]) * 0.5;

        ns.sentCount += 1;
        if (switchingChannel || ns.samples === 0 || ns.pingMs === null) {
            ns.pingMs = Math.max(1, Math.round(medianRtt));
            ns.jitterMs = 0;
        } else {
            var prevRef = ns.lastRttMs || medianRtt;
            var delta = Math.abs(medianRtt - prevRef);
            var nextJitter = this._rttWindow.length <= 1 ? 0 : (ns.jitterMs * 0.72 + delta * 0.28);
            var nextPing = ns.pingMs * 0.65 + medianRtt * 0.35;
            ns.pingMs = Math.max(1, Math.round(nextPing));
            ns.jitterMs = Math.max(0, Math.round(nextJitter));
        }
        ns.lastRttMs = medianRtt;
        ns.samples += 1;
        ns.hasError = false;
        ns.lossPct = ns.sentCount > 0 ? Math.round((ns.failCount / ns.sentCount) * 100) : 0;
        if (tickLabel) {
            ns.tickHz = tickLabel;
        } else if (this.wsConnected) {
            ns.tickHz = '30,0 Hz';
        }
        this._renderPingHud();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._recordNetSample = function (rttMs, tickLabel) {
        if (this.wsConnected || this._lobbyWs) return;
        this._recordNetRttSample(rttMs, false, tickLabel);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._recordNetFailure = function () {
        var ns = this.netStats;
        ns.sentCount += 1;
        ns.failCount += 1;
        ns.hasError = true;
        ns.lossPct = ns.sentCount > 0 ? Math.round((ns.failCount / ns.sentCount) * 100) : 100;
        this._renderPingHud();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderPingHud = function () {
        if (!this.modalEl) return;
        var root = this.modalEl;
        var dotBtn = root.querySelector('[data-lobby-ping-dot]');
        var badgeEl = root.querySelector('[data-ping-status-badge]');
        var pingValEl = root.querySelector('[data-ping-ms-val]');
        var jitterValEl = root.querySelector('[data-ping-jitter-val]');
        var modeValEl = root.querySelector('[data-ping-mode-val]');
        var instRowEl = root.querySelector('[data-ping-instance-row]');
        var instValEl = root.querySelector('[data-ping-instance-val]');

        var ns = this.netStats;
        var pingMs = ns.pingMs;
        var tier = 'optimal';
        var statusText = 'Optimal';
        if (ns.hasError || (pingMs !== null && pingMs > 195)) {
            tier = 'delayed';
            statusText = 'Verzögert';
        } else if (pingMs !== null && pingMs > 95) {
            tier = 'good';
            statusText = 'Gut';
        }

        if (dotBtn) {
            dotBtn.className = 'sf-lobby-ping-dot is-' + tier;
        }
        if (badgeEl) {
            badgeEl.textContent = statusText;
            badgeEl.className = 'sf-lobby-ping-badge' + (tier === 'optimal' ? '' : (' is-' + tier));
        }
        if (pingValEl) {
            pingValEl.textContent = pingMs !== null ? (pingMs + ' ms') : '... ms';
        }
        if (jitterValEl) {
            jitterValEl.textContent = '±' + (ns.jitterMs || 0) + ' ms';
        }

        var isLiveHeroes = (this.currentZone === 'heroes' && this.lobbyAuth && !this.soloMode && !this.multiplayerDisabled);
        if (modeValEl) {
            if (this.currentZone === 'heroes') {
                modeValEl.textContent = isLiveHeroes
                    ? (this.wsConnected ? 'Echtzeit-WS (30 Hz)' : 'Live-Sync (6 Hz)')
                    : 'Halle (Solo)';
            } else {
                modeValEl.textContent = 'Atelier (Solo)';
            }
        }
        if (instRowEl) {
            instRowEl.style.display = isLiveHeroes ? 'flex' : 'none';
        }
        if (instValEl && isLiveHeroes) {
            var curCount = 1 + (Array.isArray(this.peers) ? this.peers.length : 0);
            for (var k = 0; k < this.instances.length; k++) {
                if (Number(this.instances[k].id) === Number(this.instanceId)) {
                    curCount = Math.max(curCount, Number(this.instances[k].count) || curCount);
                    break;
                }
            }
            instValEl.textContent = 'Halle #' + this.instanceId + ' (' + curCount + '/' + (this.instanceCapacity || 20) + ')';
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._probeSoloAtelierPing = function () {
        var self = this;
        if (!this.isOpen || this._pingProbeInFlight) return;
        if (this.currentZone === 'heroes' && this.lobbyAuth && !this.soloMode && !this.multiplayerDisabled && (this.wsConnected || this._syncTimer)) {
            return;
        }
        this._pingProbeInFlight = true;
        var t0 = this._nowPerfMs();

        fetch('/lobby-ping.php', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) {
                if (r.ok) {
                    var rtt = self._nowPerfMs() - t0;
                    self._recordNetRttSample(rtt, true, 'Atelier · Bereit');
                    return null;
                }
                var tFallback = self._nowPerfMs();
                return fetch('/up', {
                    method: 'HEAD',
                    credentials: 'same-origin',
                    cache: 'no-store'
                }).then(function (rUp) {
                    if (rUp.ok) {
                        self._recordNetRttSample(self._nowPerfMs() - tFallback, true, 'Atelier · Bereit');
                    } else {
                        self._recordNetFailure();
                    }
                });
            })
            .catch(function () {
                self._recordNetFailure();
            })
            .finally(function () {
                self._pingProbeInFlight = false;
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype._startAtelierPingLoop = function () {
        var self = this;
        this._stopAtelierPingLoop();
        fetch('/lobby-ping.php', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .catch(function () {})
            .finally(function () {
                if (!self.isOpen) return;
                if (self._pingWarmupTimer) clearTimeout(self._pingWarmupTimer);
                self._pingWarmupTimer = setTimeout(function () {
                    self._pingWarmupTimer = null;
                    self._probeSoloAtelierPing();
                }, 150);
            });
        this._pingProbeTimer = setInterval(function () {
            self._probeSoloAtelierPing();
        }, 10000);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._stopAtelierPingLoop = function () {
        if (this._pingWarmupTimer) {
            clearTimeout(this._pingWarmupTimer);
            this._pingWarmupTimer = null;
        }
        if (this._pingProbeTimer) {
            clearInterval(this._pingProbeTimer);
            this._pingProbeTimer = null;
        }
        this._pingProbeInFlight = false;
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

        root.addEventListener('contextmenu', function (ev) {
            ev.preventDefault();
        });
        var stopOverlayRay = function (ev) {
            ev.stopPropagation();
        };
        var uiBlocks = root.querySelectorAll('.sf-lobby-settings-backdrop, .sf-lobby-floating-card, .sf-lobby-topbar, .sf-lobby-gold-crest-bar, .sf-lobby-chat-panel, .sf-lobby-bottom-pill, .sf-lobby-portal-pill, .sf-lobby-ping-anchor');
        for (var ub = 0; ub < uiBlocks.length; ub++) {
            uiBlocks[ub].addEventListener('pointerdown', stopOverlayRay);
            uiBlocks[ub].addEventListener('mousedown', stopOverlayRay);
            uiBlocks[ub].addEventListener('click', stopOverlayRay);
        }

        var pingAnchorEl = root.querySelector('[data-lobby-ping-anchor]');
        var pingDotBtn = root.querySelector('[data-lobby-ping-dot]');
        if (pingDotBtn && pingAnchorEl) {
            pingDotBtn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                self.pingTooltipOpen = !self.pingTooltipOpen;
                if (self.pingTooltipOpen) pingAnchorEl.classList.add('is-open');
                else pingAnchorEl.classList.remove('is-open');
                self._renderPingHud();
            });
            pingAnchorEl.addEventListener('mouseenter', function () {
                self._renderPingHud();
            });
        }

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

        var settingsMuteBtn = root.querySelector('[data-lobby-settings-mute]');
        if (settingsMuteBtn) {
            settingsMuteBtn.addEventListener('click', function () {
                if (self._audio && typeof self._audio.toggleQuickMute === 'function') {
                    self.audioState = self._audio.toggleQuickMute();
                    self._syncHudState();
                }
            });
        }

        var settingsFsBtn = root.querySelector('[data-lobby-settings-fullscreen]');
        if (settingsFsBtn) {
            settingsFsBtn.addEventListener('click', function () {
                self.toggleFullscreen();
            });
        }

        var setToggleBtn = root.querySelector('[data-lobby-settings-toggle]');
        if (setToggleBtn) {
            setToggleBtn.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
            setToggleBtn.addEventListener('click', function () {
                if (self._audio) self._audio.playUiClick();
                self._setSettingsOpen(!self.settingsOpen);
            });
        }

        root.querySelector('[data-lobby-settings-close]').addEventListener('click', function () {
            self._setSettingsOpen(false);
        });

        var resumeBtn = root.querySelector('[data-lobby-settings-resume]');
        if (resumeBtn) {
            resumeBtn.addEventListener('click', function () {
                self._setSettingsOpen(false);
            });
        }
        var leaveBtn = root.querySelector('[data-lobby-settings-leave]');
        if (leaveBtn) {
            leaveBtn.addEventListener('click', function () {
                self.close();
            });
        }

        var portalPillBtn = root.querySelector('[data-lobby-portal-pill]');
        if (portalPillBtn) {
            portalPillBtn.addEventListener('click', function () {
                self._setSanctumGateOpen(true, 'heroes');
            });
        }

        var outerGatePill = root.querySelector('[data-outer-gate-pill]');
        if (outerGatePill) {
            outerGatePill.addEventListener('click', function () {
                self._setOuterGateModalOpen(true);
            });
        }
        var outerGateClose = root.querySelector('[data-outer-gate-close]');
        if (outerGateClose) {
            outerGateClose.addEventListener('click', function () {
                self._setOuterGateModalOpen(false);
            });
        }
        var outerGateModal = root.querySelector('[data-lobby-outer-gate-modal]');
        if (outerGateModal) {
            outerGateModal.addEventListener('click', function (ev) {
                if (ev.target === ev.currentTarget) {
                    self._setOuterGateModalOpen(false);
                }
            });
        }

        var sanctumClose = root.querySelector('[data-sanctum-close]');
        if (sanctumClose) {
            sanctumClose.addEventListener('click', function () {
                self._setSanctumGateOpen(false);
            });
        }
        var sanctumModal = root.querySelector('[data-lobby-sanctum-modal]');
        if (sanctumModal) {
            sanctumModal.addEventListener('click', function (ev) {
                if (ev.target === ev.currentTarget) {
                    self._setSanctumGateOpen(false);
                }
            });
        }
        var rowLogin = root.querySelector('[data-sanctum-row="login"]');
        if (rowLogin) {
            rowLogin.addEventListener('click', function () {
                if (!(self.sanctumGateState.checks && self.sanctumGateState.checks.login)) {
                    self.sanctumExpandedStep = (self.sanctumExpandedStep === 'login') ? '' : 'login';
                    self.inlineAuthError = '';
                    self._renderSanctumGateModal();
                }
            });
        }
        var rowMagic = root.querySelector('[data-sanctum-row="magic"]');
        if (rowMagic) {
            rowMagic.addEventListener('click', function () {
                var ch = self.sanctumGateState.checks || {};
                if (!ch.login) {
                    self.sanctumExpandedStep = 'login';
                    self._renderSanctumGateModal();
                } else if (!ch.magic) {
                    self.sanctumExpandedStep = (self.sanctumExpandedStep === 'magic') ? '' : 'magic';
                    self.inlineAuthError = '';
                    self._renderSanctumGateModal();
                }
            });
        }

        var chatToggle = root.querySelector('[data-lobby-chat-toggle]');
        var chatClose = root.querySelector('[data-lobby-chat-close]');
        var chatBox = root.querySelector('[data-lobby-chat-box]');
        var chatUnread = root.querySelector('[data-lobby-chat-unread]');
        var syncChatBoxVis = function () {
            if (chatBox) chatBox.style.display = self.chatOpen ? 'flex' : 'none';
            if (self.chatOpen) {
                self.unreadChatCount = 0;
                if (chatUnread) chatUnread.style.display = 'none';
            }
        };
        if (chatToggle) {
            chatToggle.addEventListener('click', function () {
                self.chatOpen = !self.chatOpen;
                syncChatBoxVis();
            });
        }
        if (chatClose) {
            chatClose.addEventListener('click', function () {
                self.chatOpen = false;
                syncChatBoxVis();
            });
        }
        var chatForm = root.querySelector('[data-lobby-chat-form]');
        if (chatForm) {
            chatForm.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var inp = root.querySelector('[data-lobby-chat-input]');
                var msg = inp ? String(inp.value || '').trim() : '';
                if (!msg) return;
                if (inp) inp.value = '';
                self._sendInstanceChatMessage(msg);
            });
        }
        var loginForm = root.querySelector('[data-sanctum-login-panel]');
        if (loginForm) {
            loginForm.addEventListener('submit', function (ev) {
                ev.preventDefault();
                self._submitInlineLogin();
            });
        }
        var pwdEyeBtn = root.querySelector('[data-sanctum-pwd-eye]');
        if (pwdEyeBtn) {
            pwdEyeBtn.addEventListener('click', function () {
                self.inlineLoginShowPassword = !self.inlineLoginShowPassword;
                var pwdInp = root.querySelector('[data-sanctum-login-pwd]');
                var eyeOpen = pwdEyeBtn.querySelector('[data-eye-open]');
                var eyeClosed = pwdEyeBtn.querySelector('[data-eye-closed]');
                if (pwdInp) pwdInp.type = self.inlineLoginShowPassword ? 'text' : 'password';
                if (eyeOpen) eyeOpen.style.display = self.inlineLoginShowPassword ? 'none' : 'block';
                if (eyeClosed) eyeClosed.style.display = self.inlineLoginShowPassword ? 'block' : 'none';
            });
        }
        var magicTerms = root.querySelector('[data-sanctum-magic-terms]');
        if (magicTerms) {
            magicTerms.addEventListener('change', function () {
                self._renderSanctumGateModal();
            });
        }
        var magicSubmit = root.querySelector('[data-sanctum-magic-submit]');
        if (magicSubmit) {
            magicSubmit.addEventListener('click', function () {
                self._submitInlineActivateMagic();
            });
        }

        var gfxBtns = root.querySelectorAll('[data-lobby-gfx]');
        for (var g = 0; g < gfxBtns.length; g++) {
            gfxBtns[g].addEventListener('click', function (ev) {
                var lvl = ev.currentTarget.getAttribute('data-lobby-gfx') === 'balanced' ? 'balanced' : 'high';
                self.userSettings.graphicsQuality = lvl;
                var facade = getFacade(self);
                if (facade && typeof facade.setGraphicsQuality === 'function') {
                    facade.setGraphicsQuality(lvl);
                } else if (self._mod && typeof self._mod.saveLobbyUserSettings === 'function') {
                    self._mod.saveLobbyUserSettings({ graphicsQuality: lvl });
                }
                if (self._audio) self._audio.playUiSwitch();
                self._syncHudState();
            });
        }

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
            }
            if (self._audio) {
                self._audio.setBgmVolume(vol, true);
                self._audio.setBgmMuted(vol === 0, true);
                self._audio.setSfxVolume(vol);
                self._audio.setSfxMuted(vol === 0);
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

        var stagesBtn = root.querySelector('[data-lobby-stages-btn]');
        if (stagesBtn) {
            stagesBtn.addEventListener('click', function () {
                var facade = getFacade(self);
                if (!facade) return;
                self.stagesMode = facade.cyclePreviewHeroLevel();
                self._syncHudState();
            });
        }

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
        window.addEventListener('beforeunload', this._boundPageHide);
        document.addEventListener('livewire:navigating', this._boundLivewireNavigating);
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

        var zoneBadge = root.querySelector('[data-lobby-current-zone-badge]');
        if (zoneBadge) {
            zoneBadge.textContent = this.currentZone === 'heroes' ? '🏛️ Halle der Helden' : '🛍️ Kollektion';
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
            soloBtn.style.display = (this.currentZone === 'heroes' && this.lobbyAuth && !this.multiplayerDisabled) ? 'inline-flex' : 'none';
        }
        if (soloDot) {
            soloDot.className = 'sf-lobby-dot ' + (this.soloMode ? 'is-solo' : 'is-live');
        }
        if (soloLbl) {
            soloLbl.textContent = this.soloMode ? 'Solo' : 'Live';
        }

        var muteBtn = root.querySelector('[data-lobby-settings-mute]');
        var isSoundMuted = Boolean(this.audioState.allMuted || this.userSettings.volume <= 0);
        if (muteBtn) {
            muteBtn.textContent = isSoundMuted ? '🔇 Stumm' : '🔊 Ton an';
            if (!isSoundMuted) muteBtn.classList.add('is-active');
            else muteBtn.classList.remove('is-active');
        }

        var setBtn = root.querySelector('[data-lobby-settings-toggle]');
        if (setBtn) {
            if (this.settingsOpen) setBtn.classList.add('is-active');
            else setBtn.classList.remove('is-active');
        }

        var volLbl = root.querySelector('[data-lobby-volume-label]');
        var volSlider = root.querySelector('[data-lobby-volume-slider]');
        if (volLbl) volLbl.textContent = isSoundMuted ? 'Stumm' : (this.userSettings.volume + ' %');
        if (volSlider) volSlider.value = String(this.userSettings.volume);

        var camLbl = root.querySelector('[data-lobby-camera-label]');
        var camSlider = root.querySelector('[data-lobby-camera-slider]');
        if (camLbl) camLbl.textContent = this.userSettings.cameraSensitivity + ' %';
        if (camSlider) camSlider.value = String(this.userSettings.cameraSensitivity);

        var realChk = root.querySelector('[data-lobby-realname-checkbox]');
        if (realChk) realChk.checked = Boolean(this.userSettings.useRealName);

        var gfxHigh = root.querySelector('[data-lobby-gfx="high"]');
        var gfxBal = root.querySelector('[data-lobby-gfx="balanced"]');
        var curGfx = (this.userSettings && this.userSettings.graphicsQuality === 'balanced') ? 'balanced' : 'high';
        if (gfxHigh) {
            if (curGfx === 'high') gfxHigh.classList.add('is-active');
            else gfxHigh.classList.remove('is-active');
        }
        if (gfxBal) {
            if (curGfx === 'balanced') gfxBal.classList.add('is-active');
            else gfxBal.classList.remove('is-active');
        }

        var fsBtn = root.querySelector('[data-lobby-settings-fullscreen]');
        if (fsBtn) {
            fsBtn.textContent = this.isFullscreen ? 'Vollbild beenden' : 'Vollbild aktivieren';
            if (this.isFullscreen) fsBtn.classList.add('is-active');
            else fsBtn.classList.remove('is-active');
        }

        var instWrap = root.querySelector('[data-lobby-instance-wrap]');
        if (instWrap) {
            instWrap.style.display = (this.currentZone === 'heroes' && this.lobbyAuth && !this.soloMode && !this.multiplayerDisabled) ? 'inline-flex' : 'none';
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

        var btmHeroes = root.querySelector('[data-lobby-bottom-heroes]');
        if (btmHeroes) {
            btmHeroes.style.display = (this.currentZone === 'heroes') ? 'inline-flex' : 'none';
        }
        var chatPanel = root.querySelector('[data-lobby-chat-panel]');
        if (chatPanel) {
            var showChat = (this.currentZone === 'heroes' && this.lobbyAuth && !this.soloMode && !this.multiplayerDisabled);
            chatPanel.style.display = showChat ? 'block' : 'none';
            if (!showChat && this.chatOpen) {
                this.chatOpen = false;
                var chatBoxEl = root.querySelector('[data-lobby-chat-box]');
                if (chatBoxEl) chatBoxEl.style.display = 'none';
            }
        }
        this._renderChatModerationState();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._ingestSyncMessages = function (messages) {
        if (!Array.isArray(messages) || messages.length === 0) return;
        var facade = getFacade(this);
        var changed = false;
        for (var i = 0; i < messages.length; i++) {
            var m = messages[i];
            if (!m || !m.text) continue;
            var mid = String(m.id || ((m.peer_id || m.player_id || '') + ':' + (m.ts || '') + ':' + m.text));
            if (this._seenMessageIds[mid]) continue;
            this._seenMessageIds[mid] = true;
            var peerKey = String(m.peer_id || m.player_id || '');
            this.chatMessages.push({
                id: mid,
                peer_id: peerKey,
                sender: sanitizeText(m.display_name || m.sender || 'Funki'),
                text: sanitizeText(m.text)
            });
            if (this.chatMessages.length > 35) this.chatMessages.shift();
            if (facade && peerKey && typeof facade.showChatBubble === 'function') {
                facade.showChatBubble(peerKey, m.text);
            }
            if (!this.chatOpen) {
                this.unreadChatCount += 1;
            }
            changed = true;
        }
        if (changed) this._renderChatMessages();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderChatMessages = function () {
        if (!this.modalEl) return;
        var box = this.modalEl.querySelector('[data-lobby-chat-messages]');
        var unreadEl = this.modalEl.querySelector('[data-lobby-chat-unread]');
        if (unreadEl) {
            if (!this.chatOpen && this.unreadChatCount > 0) {
                unreadEl.style.display = 'inline-block';
                unreadEl.textContent = String(this.unreadChatCount);
            } else {
                unreadEl.style.display = 'none';
            }
        }
        if (!box) return;
        while (box.firstChild) box.removeChild(box.firstChild);
        var self = this;
        for (var i = 0; i < this.chatMessages.length; i++) {
            (function (msg) {
                var row = document.createElement('div');
                row.style.cssText = 'display:flex;align-items:baseline;justify-content:space-between;gap:6px;background:#FAF8F5;border:1px solid rgba(197,160,89,0.28);border-radius:9px;padding:4px 7px;';
                var txt = document.createElement('span');
                txt.style.cssText = 'color:#111827;word-break:break-word;';
                txt.textContent = msg.sender + ': ' + msg.text;
                row.appendChild(txt);
                if (msg.peer_id && msg.peer_id !== 'self') {
                    var rep = document.createElement('button');
                    rep.type = 'button';
                    rep.title = 'Spieler melden';
                    rep.style.cssText = 'appearance:none;border:none;background:transparent;font-size:10px;color:#9CA3AF;cursor:pointer;flex-shrink:0;';
                    rep.textContent = '⚑';
                    rep.addEventListener('click', function () {
                        self._reportInstancePeer(msg.peer_id);
                    });
                    row.appendChild(rep);
                }
                box.appendChild(row);
            })(this.chatMessages[i]);
        }
        box.scrollTop = box.scrollHeight;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._formatMuteRemaining = function (sec) {
        var s = Math.max(0, Math.ceil(Number(sec) || 0));
        if (s >= 3600) {
            return Math.floor(s / 3600) + ' Std. ' + Math.floor((s % 3600) / 60) + ' Min.';
        }
        if (s >= 60) {
            var m = Math.floor(s / 60);
            var r = s % 60;
            return (m < 10 ? '0' + m : String(m)) + ':' + (r < 10 ? '0' + r : String(r)) + ' Min.';
        }
        return s + ' Sek.';
    };

    SeelenfunkeUnifiedLobbyHost.prototype._stopChatMuteCountdown = function () {
        if (this._chatMuteTimer) {
            clearInterval(this._chatMuteTimer);
            this._chatMuteTimer = null;
        }
        this._chatMuteUntilPerfMs = 0;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._startChatMuteCountdown = function (seconds) {
        var self = this;
        this._stopChatMuteCountdown();
        var cleanSec = Math.max(0, Math.ceil(Number(seconds) || 0));
        if (cleanSec <= 0 || !this.isOpen) return;
        this._chatMuteUntilPerfMs = this._nowPerfMs() + (cleanSec * 1000);
        this._chatMuteTimer = setInterval(function () {
            if (!self.isOpen || !self.chatStrikeInfo || !self.chatStrikeInfo.isMuted || self.chatStrikeInfo.permanent) {
                self._stopChatMuteCountdown();
                return;
            }
            var left = Math.max(0, Math.ceil((self._chatMuteUntilPerfMs - self._nowPerfMs()) / 1000));
            if (left <= 0) {
                self._stopChatMuteCountdown();
                self.chatStrikeInfo.isMuted = false;
                self.chatStrikeInfo.remainingSec = 0;
                self.chatStrikeInfo.level = '';
                self.chatStrikeInfo.bannerText = '';
                self._renderChatModerationState();
                return;
            }
            self.chatStrikeInfo.remainingSec = left;
            self._renderChatModerationState();
        }, 1000);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderChatModerationState = function () {
        if (!this.modalEl) return;
        var root = this.modalEl;
        var bannerEl = root.querySelector('[data-lobby-chat-mod-banner]');
        var badgeEl = root.querySelector('[data-lobby-chat-mod-badge]');
        var timerEl = root.querySelector('[data-lobby-chat-mod-timer]');
        var textEl = root.querySelector('[data-lobby-chat-mod-text]');
        var inpEl = root.querySelector('[data-lobby-chat-input]');
        var subBtn = root.querySelector('[data-lobby-chat-submit]');
        var info = this.chatStrikeInfo || {};
        var hasNotice = Boolean(info.isMuted || info.bannerText || info.level);
        var st = Number(info.strikes) || 0;

        if (bannerEl) {
            bannerEl.style.display = hasNotice ? 'flex' : 'none';
            bannerEl.style.borderLeftColor = info.isMuted ? '#DC2626' : '#C5A059';
            bannerEl.style.borderColor = info.isMuted ? '#FECACA' : 'rgba(197,160,89,0.55)';
        }
        if (badgeEl && hasNotice) {
            if (info.isMuted) {
                badgeEl.textContent = (st === 2 && !info.permanent && info.level !== 'auto_mute_24h' && info.level !== 'auto_mute_permanent')
                    ? 'Abmahnung 2/3'
                    : 'Chat gesperrt';
            } else if (st === 2 || info.level === 'auto_mute_15m') {
                badgeEl.textContent = 'Abmahnung 2/3';
            } else if (st === 1 || info.level === 'auto_warning_1') {
                badgeEl.textContent = 'Verwarnung 1/3';
            } else {
                badgeEl.textContent = 'Hausordnung';
            }
        }
        if (timerEl) {
            if (info.isMuted) {
                timerEl.style.display = 'inline-block';
                timerEl.textContent = info.permanent ? 'Dauerhaft' : (info.remainingSec > 0 ? this._formatMuteRemaining(info.remainingSec) : 'Aktiv');
            } else {
                timerEl.style.display = 'none';
            }
        }
        if (textEl) {
            textEl.textContent = sanitizeText(info.bannerText || '');
            textEl.style.display = info.bannerText ? 'block' : 'none';
        }
        if (inpEl) {
            inpEl.disabled = Boolean(info.isMuted);
            if (info.isMuted) {
                inpEl.placeholder = info.permanent
                    ? 'Chat gesperrt (dauerhaft)'
                    : (info.remainingSec > 0 ? ('Chat gesperrt (' + this._formatMuteRemaining(info.remainingSec) + ')') : 'Chat gesperrt');
                inpEl.style.background = '#F3F4F6';
                inpEl.style.borderColor = '#FECACA';
                inpEl.style.color = '#991B1B';
                inpEl.style.cursor = 'not-allowed';
            } else {
                inpEl.placeholder = 'Nachricht...';
                inpEl.style.background = '#FFFFFF';
                inpEl.style.borderColor = 'rgba(197,160,89,0.55)';
                inpEl.style.color = '#111827';
                inpEl.style.cursor = 'text';
            }
        }
        if (subBtn) {
            subBtn.disabled = Boolean(info.isMuted);
            subBtn.style.opacity = info.isMuted ? '0.5' : '1';
            subBtn.style.cursor = info.isMuted ? 'not-allowed' : 'pointer';
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._syncChatModeration = function (cm) {
        if (!cm || typeof cm !== 'object') return;
        var strikes = Math.max(0, parseInt(cm.strikes || 0, 10) || 0);
        var permanent = Boolean(cm.permanent_mute);
        var remainingSec = permanent ? 0 : Math.max(0, Math.ceil(Number(cm.remaining_seconds) || 0));
        var isMuted = Boolean(cm.is_muted || permanent || remainingSec > 0);
        var prev = this.chatStrikeInfo || {};
        var nextLevel = prev.level || '';
        var nextBanner = prev.bannerText || '';

        if (isMuted) {
            if (permanent) {
                nextLevel = 'auto_mute_permanent';
                if (!nextBanner) nextBanner = 'Hausordnung: Dein Chat-Zugang ist dauerhaft gesperrt.';
            } else if (strikes >= 3) {
                nextLevel = 'auto_mute_24h';
                if (!nextBanner) nextBanner = 'Hausordnung: Dein Chat ist vorübergehend gesperrt.';
            } else if (strikes === 2) {
                nextLevel = 'auto_mute_15m';
                if (!nextBanner) nextBanner = 'Hausordnung: Dein Chat ist für 15 Minuten gesperrt.';
            } else {
                nextLevel = 'chat_muted';
                if (!nextBanner) nextBanner = 'Dein Chat ist aktuell gesperrt.';
            }
        } else if (prev.isMuted) {
            nextLevel = '';
            nextBanner = '';
        }

        this.chatStrikeInfo = {
            strikes: strikes,
            isMuted: isMuted,
            remainingSec: remainingSec,
            permanent: permanent,
            bannerText: sanitizeText(nextBanner),
            level: nextLevel
        };

        if (isMuted && !permanent && remainingSec > 0) {
            this._startChatMuteCountdown(remainingSec);
        } else {
            this._stopChatMuteCountdown();
        }
        this._renderChatModerationState();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._applyChatModerationError = function (body) {
        if (!body || typeof body !== 'object') return false;
        var code = String(body.code || '').trim();
        if (code === 'multiplayer_disabled' || body.multiplayer_disabled === true) {
            this._applyServerMultiplayerDisabled();
            return true;
        }
        var modCodes = ['auto_warning_1', 'auto_mute_15m', 'auto_mute_24h', 'auto_mute_permanent', 'chat_muted', 'muted'];
        if (modCodes.indexOf(code) === -1 && !(body.chat_moderation && typeof body.chat_moderation === 'object')) {
            return false;
        }
        var cm = (body.chat_moderation && typeof body.chat_moderation === 'object') ? body.chat_moderation : {};
        var rawStrike = (body.strike !== undefined) ? body.strike : ((body.strikes !== undefined) ? body.strikes : cm.strikes);
        var strikes = Math.max(0, parseInt(rawStrike || this.chatStrikeInfo.strikes || 0, 10) || 0);
        if (code === 'auto_warning_1' && strikes < 1) strikes = 1;
        if (code === 'auto_mute_15m' && strikes < 2) strikes = 2;
        if (code === 'auto_mute_24h' && strikes < 3) strikes = 3;
        if (code === 'auto_mute_permanent' && strikes < 4) strikes = 4;

        var permanent = Boolean(body.permanent_mute || cm.permanent_mute || code === 'auto_mute_permanent');
        var defRem = code === 'auto_mute_15m' ? 900 : (code === 'auto_mute_24h' ? 86400 : 0);
        var rawRem = body.remaining_seconds !== undefined ? body.remaining_seconds : cm.remaining_seconds;
        var remainingSec = permanent ? 0 : Math.max(0, Math.ceil(Number(rawRem !== undefined ? rawRem : defRem) || 0));
        var isMuted = Boolean(
            permanent
            || remainingSec > 0
            || cm.is_muted
            || code === 'auto_mute_15m'
            || code === 'auto_mute_24h'
            || code === 'auto_mute_permanent'
            || code === 'chat_muted'
            || code === 'muted'
        );
        var fallbackMsg = isMuted
            ? 'Hausordnung: Chat-Sperre aktiv.'
            : 'Bitte beachte unsere freundliche Hausordnung im Raum-Chat.';

        this.chatStrikeInfo = {
            strikes: strikes,
            isMuted: isMuted,
            remainingSec: remainingSec,
            permanent: permanent,
            bannerText: sanitizeText(body.message || fallbackMsg),
            level: code || (isMuted ? 'chat_muted' : 'auto_warning_1')
        };

        if (isMuted && !permanent && remainingSec > 0) {
            this._startChatMuteCountdown(remainingSec);
        } else {
            this._stopChatMuteCountdown();
        }
        this._renderChatModerationState();
        return true;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._sendInstanceChatMessage = function (rawMessage) {
        var self = this;
        var clean = sanitizeText(rawMessage).slice(0, 120);
        if (!clean || !this.lobbyAuth || this.soloMode || this.multiplayerDisabled) return;
        if (this.chatStrikeInfo && this.chatStrikeInfo.isMuted) return;
        fetch('/funkenwerk-welt/lobby/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ message: clean })
        })
            .then(function (r) {
                return r.json().catch(function () { return null; }).then(function (b) {
                    return { ok: r.ok, status: r.status, body: b };
                });
            })
            .then(function (resObj) {
                var data = resObj ? resObj.body : null;
                if (!resObj || !resObj.ok || !data || !(data.ok || data.success)) {
                    self._applyChatModerationError(data);
                    return;
                }
                if (data.chat_moderation && typeof data.chat_moderation === 'object') {
                    self._syncChatModeration(data.chat_moderation);
                }
                var f = getFacade(self);
                var entry = (data.entry && typeof data.entry === 'object') ? data.entry : ((data.message && typeof data.message === 'object') ? data.message : null);
                if (entry) {
                    if (entry.peer_id && f && typeof f.setLocalPeerId === 'function') {
                        f.setLocalPeerId(entry.peer_id);
                    }
                    self._ingestSyncMessages([entry]);
                } else if (f && typeof f.showChatBubble === 'function') {
                    f.showChatBubble('self', clean);
                }
            })
            .catch(function () {});
    };

    SeelenfunkeUnifiedLobbyHost.prototype._reportInstancePeer = function (peerId) {
        if (!peerId || !this.lobbyAuth) return;
        fetch('/funkenwerk-welt/lobby/report', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                target_peer_id: String(peerId),
                target_player_id: String(peerId)
            })
        }).catch(function () {});
    };

    SeelenfunkeUnifiedLobbyHost.prototype._startProductTransition = function (prod) {
        if (!prod || !prod.product_url || this._productTransitioning) return;
        this._productTransitioning = true;
        var self = this;
        var facade = getFacade(this);
        if (facade && typeof facade.startExhibitTransition === 'function') {
            facade.startExhibitTransition(this.activeIndex);
        }
        if (this.floatingProductWrap) {
            this.floatingProductWrap.style.opacity = '0';
            this.floatingProductWrap.style.pointerEvents = 'none';
        }
        if (this.modalEl) {
            var ivoryOverlay = this.modalEl.querySelector('[data-lobby-ivory-transition]');
            if (ivoryOverlay) {
                setTimeout(function () {
                    ivoryOverlay.classList.add('is-fading');
                }, 140);
            }
        }
        if (this._productTransitionTimer) clearTimeout(this._productTransitionTimer);
        this._productTransitionTimer = setTimeout(function () {
            self._productTransitioning = false;
            stopPrewarmAndForceLobbySilence();
            window.location.href = prod.product_url;
        }, 680);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderFloatingProductCard = function (prod) {
        if (!this.modalEl) return;
        var inner = this.modalEl.querySelector('[data-lobby-product-inner]');
        if (!inner) return;

        while (inner.firstChild) inner.removeChild(inner.firstChild);
        if (!prod) return;

        var self = this;
        var row1 = document.createElement('div');
        row1.className = 'sf-lobby-prod-row1';

        var title = document.createElement('h4');
        title.className = 'sf-lobby-prod-title';
        title.textContent = sanitizeText(prod.name || 'Unikat');
        row1.appendChild(title);

        var rawPrice = sanitizeText(prod.price_formatted || '');
        if (rawPrice) {
            var priceStr = (prod.is_personalizable && !/^ab\s/i.test(rawPrice)) ? ('ab ' + rawPrice) : rawPrice;
            var priceBadge = document.createElement('span');
            priceBadge.className = 'sf-lobby-prod-price-badge';
            priceBadge.textContent = priceStr;
            row1.appendChild(priceBadge);
        }
        inner.appendChild(row1);

        var rawSummary = sanitizeText(prod.short_description || '');
        if (!rawSummary && Array.isArray(prod.benefits) && prod.benefits.length > 0) {
            rawSummary = sanitizeText(prod.benefits.slice(0, 2).join(' · '));
        }
        if (rawSummary) {
            var summary = document.createElement('p');
            summary.className = 'sf-lobby-prod-summary';
            summary.textContent = rawSummary;
            inner.appendChild(summary);
        }

        var link = document.createElement('a');
        link.href = prod.product_url || '/shop';
        link.className = 'sf-lobby-prod-cta';
        link.textContent = prod.is_personalizable ? 'Jetzt gestalten' : 'Zum Produkt';
        link.addEventListener('click', function (ev) {
            ev.preventDefault();
            self._startProductTransition(prod);
        });
        inner.appendChild(link);

        if (this.products.length > 1) {
            var stepRow = document.createElement('div');
            stepRow.className = 'sf-lobby-exhibit-step-row';

            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'sf-lobby-step-btn';
            prevBtn.style.cssText = 'padding:4px 9px;font-size:10px;';
            prevBtn.textContent = '‹ Vorheriges';
            prevBtn.addEventListener('click', function () {
                var f = getFacade(self);
                if (f && self.products.length > 0) f.prevExhibit();
            });

            var curLbl = document.createElement('button');
            curLbl.type = 'button';
            curLbl.style.cssText = 'appearance:none;border:none;background:transparent;font-size:10.5px;font-weight:800;color:#6B7280;cursor:pointer;';
            curLbl.textContent = (this.activeIndex + 1) + '/' + this.products.length;
            curLbl.addEventListener('click', function () {
                var f = getFacade(self);
                if (f && self.products.length > 0) f.focusExhibit(self.activeIndex, false);
            });

            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'sf-lobby-step-btn';
            nextBtn.style.cssText = 'padding:4px 9px;font-size:10px;';
            nextBtn.textContent = 'Nächstes ›';
            nextBtn.addEventListener('click', function () {
                var f = getFacade(self);
                if (f && self.products.length > 0) f.nextExhibit();
            });

            stepRow.appendChild(prevBtn);
            stepRow.appendChild(curLbl);
            stepRow.appendChild(nextBtn);
            inner.appendChild(stepRow);
        }
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

    SeelenfunkeUnifiedLobbyHost.prototype._buildWsStatePayload = function (st) {
        return {
            type: 'state',
            x: st.x,
            y: st.y,
            z: st.z,
            vx: st.vx || 0,
            vy: st.vy || 0,
            vz: st.vz || 0,
            rot_y: st.rot_y,
            speed: st.speed || 0,
            jumping: Boolean(st.jumping),
            sprint: Boolean(st.sprint),
            wall_cling: Boolean(st.wall_cling),
            wall_nx: st.wall_nx || 0,
            wall_nz: st.wall_nz || 0,
            somersault_seq: st.somersault_seq || 0,
            somersault_dir: st.somersault_dir || 0,
            somersault_prog: st.somersault_prog || 0,
            emote: st.emote || null
        };
    };

    SeelenfunkeUnifiedLobbyHost.prototype._hasWsStateChanged = function (st, prev, nowMs) {
        if (!prev) return true;
        if ((nowMs - (this._lastWsSentAtMs || 0)) >= 400) return true;
        if (Math.abs((st.x || 0) - (prev.x || 0)) > 0.004) return true;
        if (Math.abs((st.y || 0) - (prev.y || 0)) > 0.004) return true;
        if (Math.abs((st.z || 0) - (prev.z || 0)) > 0.004) return true;
        var dYaw = (st.rot_y || 0) - (prev.rot_y || 0);
        while (dYaw > Math.PI) dYaw -= Math.PI * 2;
        while (dYaw < -Math.PI) dYaw += Math.PI * 2;
        if (Math.abs(dYaw) > 0.012) return true;
        if (((st.speed || 0) <= 0.02) !== ((prev.speed || 0) <= 0.02)) return true;
        if (Math.abs((st.speed || 0) - (prev.speed || 0)) > 0.03) return true;
        if (Boolean(st.jumping) !== Boolean(prev.jumping)) return true;
        if (Boolean(st.sprint) !== Boolean(prev.sprint)) return true;
        if (Boolean(st.wall_cling) !== Boolean(prev.wall_cling)) return true;
        if ((st.somersault_seq || 0) !== (prev.somersault_seq || 0)) return true;
        if ((st.somersault_dir || 0) !== (prev.somersault_dir || 0)) return true;
        if ((st.emote || null) !== (prev.emote || null)) return true;
        return false;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._sendWsStateNow = function (force) {
        if (!this.isOpen || !this.wsConnected || !this._lobbyWs || this._lobbyWs.readyState !== 1) return false;
        if (this.soloMode || this.currentZone !== 'heroes') return false;
        var facade = getFacade(this);
        if (!facade) return false;
        var st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
        if (!st) return false;
        var nowMs = this._nowPerfMs();
        if (!force && !this._hasWsStateChanged(st, this._lastWsSentState, nowMs)) {
            return false;
        }
        var pkt = this._buildWsStatePayload(st);
        try {
            this._lobbyWs.send(JSON.stringify(pkt));
            this._lastWsSentAtMs = nowMs;
            this._lastWsSentState = pkt;
            return true;
        } catch (e) {
            return false;
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._upsertLocalPeerList = function (peerObj) {
        if (!peerObj || typeof peerObj !== 'object') return;
        var pid = String(peerObj.peer_id || peerObj.player_id || '');
        if (!pid) return;
        var next = Array.isArray(this.peers) ? this.peers.slice() : [];
        var foundIdx = -1;
        for (var i = 0; i < next.length; i++) {
            var cur = next[i];
            if (cur && (String(cur.peer_id || '') === pid || String(cur.player_id || '') === pid)) {
                foundIdx = i;
                break;
            }
        }
        if (foundIdx >= 0) {
            next[foundIdx] = Object.assign({}, next[foundIdx], peerObj);
        } else if (next.length < 19) {
            next.push(peerObj);
        }
        this.peers = next;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._removeLocalPeerFromList = function (peerId) {
        if (!peerId || !Array.isArray(this.peers)) return;
        var target = String(peerId);
        this.peers = this.peers.filter(function (p) {
            return p && String(p.peer_id || '') !== target && String(p.player_id || '') !== target;
        });
    };

    SeelenfunkeUnifiedLobbyHost.prototype._stopWsTickLoop = function () {
        if (this._wsSendTimer) {
            clearInterval(this._wsSendTimer);
            this._wsSendTimer = null;
        }
        if (this._wsTickWorker) {
            try {
                this._wsTickWorker.onmessage = null;
                this._wsTickWorker.onerror = null;
                this._wsTickWorker.postMessage(0);
                this._wsTickWorker.terminate();
            } catch (e) {}
            this._wsTickWorker = null;
        }
        if (this._wsTickWorkerUrl && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
            try {
                URL.revokeObjectURL(this._wsTickWorkerUrl);
            } catch (e2) {}
            this._wsTickWorkerUrl = null;
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._startWsTickLoop = function (intervalMs, pingFn) {
        var self = this;
        this._stopWsTickLoop();
        var cleanMs = Math.max(25, Math.min(100, Math.round(Number(intervalMs) || 33)));
        var onTick = function () {
            if (!self.isOpen || !self.wsConnected) return;
            self._sendWsStateNow(false);
            if (typeof pingFn === 'function' && (self._nowPerfMs() - (self._lastWsPingSentAtMs || 0)) >= 1500) {
                pingFn();
            }
        };
        if (typeof Worker !== 'undefined' && typeof Blob !== 'undefined' && typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
            try {
                var blobUrl = URL.createObjectURL(new Blob(
                    ['let t=null;onmessage=e=>{clearInterval(t);if(e.data>0)t=setInterval(()=>postMessage(1),e.data);}'],
                    { type: 'application/javascript' }
                ));
                var worker = new Worker(blobUrl);
                this._wsTickWorkerUrl = blobUrl;
                this._wsTickWorker = worker;
                worker.onmessage = onTick;
                worker.onerror = function () {
                    if (self._wsTickWorker === worker) {
                        self._stopWsTickLoop();
                        if (self.isOpen && self.wsConnected) {
                            self._wsSendTimer = setInterval(onTick, cleanMs);
                        }
                    }
                };
                worker.postMessage(cleanMs);
                return;
            } catch (err) {
                this._stopWsTickLoop();
            }
        }
        this._wsSendTimer = setInterval(onTick, cleanMs);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._clearWsTimers = function () {
        this._stopWsTickLoop();
        if (this._wsPingTimer) {
            clearInterval(this._wsPingTimer);
            this._wsPingTimer = null;
        }
        if (this._wsWarmupPingTimer) {
            clearTimeout(this._wsWarmupPingTimer);
            this._wsWarmupPingTimer = null;
        }
        if (this._wsHandshakeFallbackTimer) {
            clearTimeout(this._wsHandshakeFallbackTimer);
            this._wsHandshakeFallbackTimer = null;
        }
        if (this._wsUrgentTimer) {
            clearTimeout(this._wsUrgentTimer);
            this._wsUrgentTimer = null;
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._closeLobbyWs = function (sendLeave) {
        this._closeLobbyWebSocket(sendLeave);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._applyServerMultiplayerDisabled = function (facadeOverride) {
        this._wsDisabledByServer = true;
        this.multiplayerDisabled = true;
        this._closeLobbyWs(false);
        this._stopHttpSyncTimers();
        this._setInstanceMenuOpen(false);
        this.chatOpen = false;
        this.peers = [];
        this.instances = [];
        this.netStats.tickHz = 'Halle (Solo)';
        var f = facadeOverride || getFacade(this);
        if (f) {
            if (typeof f.updatePeers === 'function') {
                f.updatePeers([]);
            }
            if (typeof f.setSoloMode === 'function') {
                f.setSoloMode(true);
            }
        }
        this._syncHudState();
        this._renderPingHud();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._closeLobbyWebSocket = function (sendLeave) {
        this._clearWsTimers();
        var ws = this._lobbyWs;
        this._lobbyWs = null;
        this.wsConnected = false;
        this._lastWsSentState = null;
        this._rttWindow = [];
        this._resetPingOnNextWireSample = true;
        if (!ws) return;
        ws.onopen = null;
        ws.onmessage = null;
        ws.onerror = null;
        ws.onclose = null;
        try {
            if (sendLeave && ws.readyState === 1) {
                ws.send(JSON.stringify({ type: 'leave' }));
            }
        } catch (e) {}
        try {
            ws.close(1000, 'leave');
        } catch (e2) {}
    };

    SeelenfunkeUnifiedLobbyHost.prototype._connectLobbyWebSocket = function (wsRelay, fallbackHttpRttMs) {
        var self = this;
        if (wsRelay && wsRelay.url && wsRelay.ticket) {
            this._wsDisabledByServer = false;
            this.multiplayerDisabled = false;
        }
        if (this._wsDisabledByServer || this.multiplayerDisabled || this.soloMode) {
            return;
        }
        if (!wsRelay || !wsRelay.url || !wsRelay.ticket || typeof WebSocket === 'undefined') {
            if (typeof fallbackHttpRttMs === 'number' && fallbackHttpRttMs > 0) {
                this._recordNetSample(fallbackHttpRttMs, '6,0 Hz');
            }
            this._scheduleNextSyncTick(165);
            return;
        }
        if (!this.isOpen || !this.lobbyAuth || this.currentZone !== 'heroes') return;

        this._closeLobbyWebSocket(false);
        this._wsTickMs = Math.max(25, Math.min(100, Number(wsRelay.tick_ms) || 33));
        var wsUrl = String(wsRelay.url) + (String(wsRelay.url).indexOf('?') === -1 ? '?' : '&') + 'ticket=' + encodeURIComponent(String(wsRelay.ticket));
        var ws = null;
        try {
            ws = new WebSocket(wsUrl);
        } catch (err) {
            this.wsConnected = false;
            if (typeof fallbackHttpRttMs === 'number' && fallbackHttpRttMs > 0) {
                this._recordNetSample(fallbackHttpRttMs, '6,0 Hz');
            }
            this._scheduleNextSyncTick(165);
            return;
        }
        this._lobbyWs = ws;

        this._wsHandshakeFallbackTimer = setTimeout(function () {
            self._wsHandshakeFallbackTimer = null;
            if (!self.wsConnected && self._lobbyWs === ws && self.isOpen && self.lobbyAuth && !self.soloMode && !self.multiplayerDisabled && !self._wsDisabledByServer && self.currentZone === 'heroes') {
                self._closeLobbyWebSocket(false);
                if (typeof fallbackHttpRttMs === 'number' && fallbackHttpRttMs > 0) {
                    self._recordNetSample(fallbackHttpRttMs, '6,0 Hz');
                }
                self._scheduleNextSyncTick(165);
            }
        }, 2500);

        var sendWsPing = function () {
            if (!self.wsConnected || self._lobbyWs !== ws || ws.readyState !== 1) return;
            var nowP = self._nowPerfMs();
            self._lastWsPingSentAtMs = nowP;
            try {
                ws.send(JSON.stringify({ type: 'ping', c: nowP }));
            } catch (e) {}
        };

        ws.onmessage = function (ev) {
            if (self._lobbyWs !== ws || !self.isOpen) return;
            var msg = null;
            try {
                msg = JSON.parse(ev.data);
            } catch (e) {
                return;
            }
            if (!msg || typeof msg !== 'object') return;
            var facade = getFacade(self);

            if (msg.type === 'multiplayer_disabled' || msg.solo_mode === true) {
                self._applyServerMultiplayerDisabled(facade);
                return;
            }
            if (self.soloMode || self.multiplayerDisabled || self.currentZone !== 'heroes') return;

            if (msg.type === 'welcome') {
                self.wsConnected = true;
                self._rttWindow = [];
                self._resetPingOnNextWireSample = true;
                self._netSampleIsDirectWire = true;
                self.netStats.hasError = false;
                self.netStats.tickHz = '30,0 Hz';
                self.netStats.tickIntervalMs = self._wsTickMs;
                if (msg.instance_id) {
                    self.instanceId = Number(msg.instance_id) || self.instanceId;
                }
                if (facade && msg.peer_id && typeof facade.setLocalPeerId === 'function') {
                    facade.setLocalPeerId(msg.peer_id);
                }
                self.peers = Array.isArray(msg.peers) ? msg.peers : [];
                if (facade) {
                    facade.updatePeers(self.peers);
                }
                self._clearWsTimers();
                self._sendWsStateNow(true);
                sendWsPing();
                self._wsWarmupPingTimer = setTimeout(function () {
                    self._wsWarmupPingTimer = null;
                    sendWsPing();
                }, 300);
                self._startWsTickLoop(self._wsTickMs, sendWsPing);
                self._wsPingTimer = setInterval(function () {
                    if ((self._nowPerfMs() - (self._lastWsPingSentAtMs || 0)) >= 1450) {
                        sendWsPing();
                    }
                }, 1500);
                self._scheduleNextSyncTick(10000);
                self._syncHudState();
                self._renderPingHud();
                return;
            }

            if (msg.type === 'peer_state' && msg.peer) {
                if (facade && typeof facade.upsertPeer === 'function') {
                    facade.upsertPeer(msg.peer);
                }
                self._upsertLocalPeerList(msg.peer);
                return;
            }

            if (msg.type === 'peer_left' && msg.peer_id) {
                if (facade && typeof facade.removePeer === 'function') {
                    facade.removePeer(msg.peer_id);
                }
                self._removeLocalPeerFromList(msg.peer_id);
                self._syncHudState();
                self._renderPingHud();
                return;
            }

            if (msg.type === 'pong') {
                var rtt = Math.max(1, self._nowPerfMs() - Number(msg.c));
                self._recordNetRttSample(rtt, true, '30,0 Hz');
            }
        };

        var handleWsDisconnect = function (ev) {
            if (ev && Number(ev.code) === 4001) {
                self._applyServerMultiplayerDisabled();
                return;
            }
            if (self._lobbyWs !== ws) return;
            self._clearWsTimers();
            self._lobbyWs = null;
            var wasConnected = self.wsConnected;
            self.wsConnected = false;
            self._lastWsSentState = null;
            if (self.isOpen && self.lobbyAuth && !self.soloMode && !self.multiplayerDisabled && !self._wsDisabledByServer && self.currentZone === 'heroes') {
                self.netStats.tickHz = '6,0 Hz';
                if (!wasConnected && typeof fallbackHttpRttMs === 'number' && fallbackHttpRttMs > 0 && self.netStats.pingMs === null) {
                    self._recordNetSample(fallbackHttpRttMs, '6,0 Hz');
                } else {
                    self._renderPingHud();
                }
                if (wasConnected || !self._syncTimer) {
                    self._scheduleNextSyncTick(165);
                }
            }
        };

        ws.onerror = handleWsDisconnect;
        ws.onclose = handleWsDisconnect;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._handleZoneSyncTransition = function (nextZone) {
        var z = String(nextZone || 'gallery').toLowerCase() === 'heroes' ? 'heroes' : 'gallery';
        var facade = getFacade(this);

        if (z === 'heroes') {
            if (facade && typeof facade.refreshHeroesData === 'function') {
                facade.refreshHeroesData(false);
            }
            if (this.lobbyAuth && this.sanctumGateState.canEnter && !this.soloMode && (this._activeSyncZone !== 'heroes' || (!this._syncTimer && !this.wsConnected))) {
                this._activeSyncZone = 'heroes';
                this._joinLobby();
            }
            this._renderPingHud();
            return;
        }

        var wasInHeroes = (this._activeSyncZone === 'heroes' || Boolean(this._syncTimer) || this.wsConnected);
        this._activeSyncZone = 'gallery';
        this._setInstanceMenuOpen(false);
        this._stopSyncLoop();
        this.netStats.tickHz = 'Atelier · Bereit';
        this.peers = [];
        if (facade) {
            facade.updatePeers([]);
        }
        this._renderPingHud();
        this._probeSoloAtelierPing();

        if (wasInHeroes && this.lobbyAuth && !this.soloMode && facade) {
            var st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
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
                    vx: st.vx || 0,
                    vy: st.vy || 0,
                    vz: st.vz || 0,
                    rot_y: st.rot_y,
                    speed: st.speed,
                    jumping: Boolean(st.jumping),
                    sprint: Boolean(st.sprint),
                    wall_cling: Boolean(st.wall_cling),
                    wall_nx: st.wall_nx || 0,
                    wall_nz: st.wall_nz || 0,
                    somersault_seq: st.somersault_seq || 0,
                    somersault_dir: st.somersault_dir || 0,
                    somersault_prog: st.somersault_prog || 0,
                    zone: 'gallery',
                    emote: st.emote || null,
                    combo_seq: 0,
                    combo_stage: 0
                })
            }).catch(function () {});
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._joinLobby = function () {
        var self = this;
        var facade = getFacade(this);
        if (!facade || !this.lobbyAuth || this.soloMode || this.currentZone !== 'heroes') return;
        var st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
        var t0 = this._nowPerfMs();
        this._lastSyncSentAtMs = t0;

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
                vx: st.vx || 0,
                vy: st.vy || 0,
                vz: st.vz || 0,
                rot_y: st.rot_y,
                speed: st.speed,
                jumping: Boolean(st.jumping),
                sprint: Boolean(st.sprint),
                wall_cling: Boolean(st.wall_cling),
                wall_nx: st.wall_nx || 0,
                wall_nz: st.wall_nz || 0,
                somersault_seq: st.somersault_seq || 0,
                somersault_dir: st.somersault_dir || 0,
                somersault_prog: st.somersault_prog || 0,
                zone: 'heroes',
                emote: st.emote || null,
                combo_seq: 0,
                combo_stage: 0
            })
        })
            .then(function (r) {
                var rtt = self._nowPerfMs() - t0;
                return r.json().catch(function () { return null; }).then(function (b) {
                    if (r.status === 401 || r.status === 403) {
                        self._stopSyncLoop();
                        var fGate = getFacade(self);
                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                            fGate.handleSanctumHttpResponse(r.status, b, 'heroes');
                        }
                        return null;
                    }
                    if (r.ok) {
                        var hasWs = Boolean(b && b.ws_relay && b.ws_relay.url && b.ws_relay.ticket);
                        if (!hasWs && !self.wsConnected) {
                            self._recordNetSample(rtt, '6,0 Hz');
                        }
                        if (b && typeof b === 'object') {
                            b._joinHttpRtt = rtt;
                        }
                    } else {
                        self._recordNetFailure();
                    }
                    return r.ok ? b : null;
                });
            })
            .then(function (data) {
                if (!data || !data.ok || !self.isOpen || self.currentZone !== 'heroes') return;
                self.lobbyAuth = Boolean(data.authenticated);
                if (data.chat_moderation && typeof data.chat_moderation === 'object') {
                    self._syncChatModeration(data.chat_moderation);
                }
                var f2 = getFacade(self);
                if (data.multiplayer_disabled || data.solo_mode === true) {
                    self._applyServerMultiplayerDisabled(f2);
                    return;
                }
                if (self.multiplayerDisabled) {
                    self.multiplayerDisabled = false;
                    if (f2 && typeof f2.setSoloMode === 'function') {
                        f2.setSoloMode(Boolean(self.soloMode));
                    }
                }
                if (data.tick_interval_ms && !self.wsConnected) {
                    self.netStats.tickIntervalMs = Math.max(120, Math.min(250, Number(data.tick_interval_ms) || 165));
                }
                if (data.instance_id) self.instanceId = Number(data.instance_id) || 1;
                if (data.hard_cap) self.instanceCapacity = Number(data.hard_cap) || 20;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                self.peers = Array.isArray(data.peers) ? data.peers : [];
                if (f2) {
                    if (data.peer_id && typeof f2.setLocalPeerId === 'function') {
                        f2.setLocalPeerId(data.peer_id);
                    }
                    f2.updatePeers(self.peers);
                }
                if (Array.isArray(data.messages)) {
                    self._ingestSyncMessages(data.messages);
                }
                self._syncHudState();
                self._renderPingHud();
                if (self.lobbyAuth && !self.soloMode && !self.multiplayerDisabled && self.currentZone === 'heroes') {
                    var hasWsRelay = Boolean(data.ws_relay && data.ws_relay.url && data.ws_relay.ticket);
                    if (hasWsRelay) {
                        self._wsDisabledByServer = false;
                    }
                    self._startSyncLoop(hasWsRelay ? 10000 : undefined);
                    if (hasWsRelay) {
                        self._connectLobbyWebSocket(data.ws_relay, data._joinHttpRtt);
                    }
                }
            })
            .catch(function () {
                self._recordNetFailure();
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype._computeAdaptiveSyncDelayMs = function (st) {
        if (this.wsConnected) {
            this.netStats.tickHz = '30,0 Hz';
            return 10000;
        }
        var speed = st ? (Number(st.speed) || 0) : 0;
        var jumping = Boolean(st && st.jumping);
        var hasPeers = Array.isArray(this.peers) && this.peers.length > 0;
        var isActive = speed > 0.05 || jumping || hasPeers;
        this.netStats.tickHz = isActive ? '6,0 Hz' : 'Ruhe-Puls';
        return isActive ? 165 : 450;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._scheduleNextSyncTick = function (overrideDelayMs) {
        var self = this;
        if (this._syncTimer) {
            clearTimeout(this._syncTimer);
            this._syncTimer = null;
        }
        if (!this.isOpen || !this.lobbyAuth || this.soloMode || this.currentZone !== 'heroes') return;
        var facade = getFacade(this);
        var st = facade ? (typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot()) : null;
        var delay = typeof overrideDelayMs === 'number' ? overrideDelayMs : this._computeAdaptiveSyncDelayMs(st);
        this._syncTimer = setTimeout(function () {
            self._syncTimer = null;
            self._runSyncTick();
        }, delay);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._requestImmediateSync = function () {
        var self = this;
        if (!this.isOpen || !this.lobbyAuth || this.soloMode || this.currentZone !== 'heroes') return;

        if (this.wsConnected && this._lobbyWs && this._lobbyWs.readyState === 1) {
            var wsElapsed = this._nowPerfMs() - (this._lastWsSentAtMs || 0);
            if (wsElapsed >= 16) {
                if (this._wsUrgentTimer) {
                    clearTimeout(this._wsUrgentTimer);
                    this._wsUrgentTimer = null;
                }
                this._sendWsStateNow(true);
            } else if (!this._wsUrgentTimer) {
                var wsWait = Math.max(4, Math.ceil(16 - wsElapsed));
                this._wsUrgentTimer = setTimeout(function () {
                    self._wsUrgentTimer = null;
                    self._sendWsStateNow(true);
                }, wsWait);
            }
            return;
        }

        if (this._syncInFlight) {
            this._syncPendingImmediate = true;
            return;
        }
        var elapsed = this._nowPerfMs() - (this._lastSyncSentAtMs || 0);
        if (elapsed < 75) {
            this._syncPendingImmediate = true;
            var waitMs = Math.max(8, Math.ceil(75 - elapsed));
            this._scheduleNextSyncTick(waitMs);
            return;
        }
        this._syncPendingImmediate = false;
        if (this._syncTimer) {
            clearTimeout(this._syncTimer);
            this._syncTimer = null;
        }
        this._runSyncTick();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._checkUrgentWatchState = function () {
        if (!this.isOpen || !this.lobbyAuth || this.soloMode || this.currentZone !== 'heroes') {
            this._lastWatchSyncSnap = null;
            return;
        }
        var facade = getFacade(this);
        if (!facade) return;
        var st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
        if (!st) return;

        var moving = (Number(st.speed) || 0) > 0.08;
        var jumping = Boolean(st.jumping);
        var wallCling = Boolean(st.wall_cling);
        var somersaultSeq = st.somersault_seq || 0;
        var emote = st.emote || null;
        var velAngle = moving ? Math.atan2(Number(st.vx) || 0, Number(st.vz) || 0) : 0;

        if (!this._lastWatchSyncSnap) {
            this._lastWatchSyncSnap = {
                moving: moving,
                jumping: jumping,
                wallCling: wallCling,
                somersaultSeq: somersaultSeq,
                emote: emote,
                velAngle: velAngle
            };
            return;
        }
        var prev = this._lastWatchSyncSnap;
        var rawDiff = velAngle - prev.velAngle;
        while (rawDiff > Math.PI) rawDiff -= Math.PI * 2;
        while (rawDiff < -Math.PI) rawDiff += Math.PI * 2;
        var dirChanged = moving && prev.moving && Math.abs(rawDiff) > (25 * Math.PI / 180);

        if (
            moving !== prev.moving
            || (!prev.jumping && jumping)
            || wallCling !== prev.wallCling
            || somersaultSeq !== prev.somersaultSeq
            || emote !== prev.emote
            || dirChanged
        ) {
            this._lastWatchSyncSnap = {
                moving: moving,
                jumping: jumping,
                wallCling: wallCling,
                somersaultSeq: somersaultSeq,
                emote: emote,
                velAngle: velAngle
            };
            this._requestImmediateSync();
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._stopHttpSyncTimers = function () {
        if (this._syncTimer) {
            clearTimeout(this._syncTimer);
            this._syncTimer = null;
        }
        if (this._urgentSyncWatchTimer) {
            clearInterval(this._urgentSyncWatchTimer);
            this._urgentSyncWatchTimer = null;
        }
        this._syncInFlight = false;
        this._syncPendingImmediate = false;
        this._lastWatchSyncSnap = null;
    };

    SeelenfunkeUnifiedLobbyHost.prototype._startSyncLoop = function (initialDelayMs) {
        var self = this;
        this._stopHttpSyncTimers();
        if (!this.isOpen || !this.lobbyAuth || this.soloMode || this.currentZone !== 'heroes') return;

        this._urgentSyncWatchTimer = setInterval(function () {
            self._checkUrgentWatchState();
        }, 60);
        this._scheduleNextSyncTick(initialDelayMs);
    };

    SeelenfunkeUnifiedLobbyHost.prototype._stopSyncLoop = function () {
        this._closeLobbyWebSocket(true);
        this._stopHttpSyncTimers();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._runSyncTick = function () {
        var self = this;
        var facade = getFacade(this);
        if (!this.isOpen || !this.lobbyAuth || this.soloMode || !facade) {
            return;
        }
        if (this.currentZone !== 'heroes') {
            this._stopSyncLoop();
            return;
        }
        if (this._syncInFlight) {
            if (!this.wsConnected) {
                this._syncPendingImmediate = true;
            }
            return;
        }
        this._syncInFlight = true;
        var st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
        var t0 = this._nowPerfMs();
        this._lastSyncSentAtMs = t0;
        var nextDelayMode = this.wsConnected
            ? '30,0 Hz'
            : (this._computeAdaptiveSyncDelayMs(st) <= 165 ? '6,0 Hz' : 'Ruhe-Puls');

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
                vx: st.vx || 0,
                vy: st.vy || 0,
                vz: st.vz || 0,
                rot_y: st.rot_y,
                speed: st.speed,
                jumping: Boolean(st.jumping),
                sprint: Boolean(st.sprint),
                wall_cling: Boolean(st.wall_cling),
                wall_nx: st.wall_nx || 0,
                wall_nz: st.wall_nz || 0,
                somersault_seq: st.somersault_seq || 0,
                somersault_dir: st.somersault_dir || 0,
                somersault_prog: st.somersault_prog || 0,
                zone: 'heroes',
                emote: st.emote || null,
                combo_seq: 0,
                combo_stage: 0
            })
        })
            .then(function (r) {
                var rtt = self._nowPerfMs() - t0;
                return r.json().catch(function () { return null; }).then(function (b) {
                    if (r.status === 401 || r.status === 403) {
                        self._stopSyncLoop();
                        var fGate = getFacade(self);
                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                            fGate.handleSanctumHttpResponse(r.status, b, 'heroes');
                        }
                        return null;
                    }
                    if (r.ok) {
                        if (!self.wsConnected && !self._lobbyWs) {
                            self._recordNetSample(rtt, nextDelayMode);
                        }
                    } else if (!self.wsConnected && !self._lobbyWs) {
                        self._recordNetFailure();
                    }
                    return r.ok ? b : null;
                });
            })
            .then(function (data) {
                var f2 = getFacade(self);
                if (!data || !data.ok || !self.isOpen || !f2 || self.currentZone !== 'heroes') return;
                if (data.chat_moderation && typeof data.chat_moderation === 'object') {
                    self._syncChatModeration(data.chat_moderation);
                }
                if (data.multiplayer_disabled || data.solo_mode === true) {
                    self._applyServerMultiplayerDisabled(f2);
                    return;
                }
                if (self.multiplayerDisabled) {
                    self.multiplayerDisabled = false;
                    if (typeof f2.setSoloMode === 'function') {
                        f2.setSoloMode(Boolean(self.soloMode));
                    }
                }
                if (data.ws_relay && data.ws_relay.url && data.ws_relay.ticket && !self.wsConnected && !self._lobbyWs) {
                    self._wsDisabledByServer = false;
                    self._connectLobbyWebSocket(data.ws_relay);
                }
                if (data.tick_interval_ms && !self.wsConnected) {
                    self.netStats.tickIntervalMs = Math.max(120, Math.min(250, Number(data.tick_interval_ms) || 165));
                }
                if (data.instance_id) self.instanceId = Number(data.instance_id) || self.instanceId;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                if (data.peer_id && typeof f2.setLocalPeerId === 'function') {
                    f2.setLocalPeerId(data.peer_id);
                }
                if (!self.wsConnected) {
                    self.peers = Array.isArray(data.peers) ? data.peers : [];
                    f2.updatePeers(self.peers);
                }
                if (Array.isArray(data.messages)) {
                    self._ingestSyncMessages(data.messages);
                }
                self._syncHudState();
                self._renderPingHud();
                if (self.instanceMenuOpen) self._renderInstanceList();
            })
            .catch(function () {
                if (!self.wsConnected && !self._lobbyWs) {
                    self._recordNetFailure();
                }
            })
            .finally(function () {
                self._syncInFlight = false;
                if (!self.isOpen || !self.lobbyAuth || self.soloMode || self.multiplayerDisabled || self.currentZone !== 'heroes') {
                    return;
                }
                if (!self.wsConnected && self._syncPendingImmediate) {
                    self._syncPendingImmediate = false;
                    self._requestImmediateSync();
                } else {
                    self._scheduleNextSyncTick(self.wsConnected ? 10000 : undefined);
                }
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
        var st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
        this._setInstanceMenuOpen(false);
        this._closeLobbyWebSocket(true);
        var t0 = this._nowPerfMs();

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
                vx: st.vx || 0,
                vy: st.vy || 0,
                vz: st.vz || 0,
                rot_y: st.rot_y,
                speed: st.speed,
                jumping: Boolean(st.jumping),
                sprint: Boolean(st.sprint),
                wall_cling: Boolean(st.wall_cling),
                wall_nx: st.wall_nx || 0,
                wall_nz: st.wall_nz || 0,
                somersault_seq: st.somersault_seq || 0,
                somersault_dir: st.somersault_dir || 0,
                somersault_prog: st.somersault_prog || 0,
                zone: 'heroes'
            })
        })
            .then(function (r) {
                var rtt = self._nowPerfMs() - t0;
                return r.json().catch(function () { return null; }).then(function (b) {
                    if (r.status === 401 || r.status === 403) {
                        self._stopSyncLoop();
                        var fGate = getFacade(self);
                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                            fGate.handleSanctumHttpResponse(r.status, b, 'heroes');
                        }
                        return null;
                    }
                    if (r.ok) {
                        var hasWsSwitch = Boolean(b && b.ws_relay && b.ws_relay.url && b.ws_relay.ticket);
                        if (!hasWsSwitch && !self.wsConnected) {
                            self._recordNetSample(rtt, '6,0 Hz');
                        }
                        if (b && typeof b === 'object') {
                            b._switchHttpRtt = rtt;
                        }
                    } else {
                        self._recordNetFailure();
                    }
                    return r.ok ? b : null;
                });
            })
            .then(function (data) {
                var f2 = getFacade(self);
                if (!data || !data.ok || !self.isOpen || !f2) return;
                if (data.chat_moderation && typeof data.chat_moderation === 'object') {
                    self._syncChatModeration(data.chat_moderation);
                }
                if (data.multiplayer_disabled || data.solo_mode === true) {
                    self._applyServerMultiplayerDisabled(f2);
                    return;
                }
                if (data.instance_id) self.instanceId = Number(data.instance_id) || cleanTarget;
                if (Array.isArray(data.instances)) self.instances = data.instances;
                if (data.peer_id && typeof f2.setLocalPeerId === 'function') {
                    f2.setLocalPeerId(data.peer_id);
                }
                self.peers = Array.isArray(data.peers) ? data.peers : [];
                f2.switchInstance(self.peers);
                self._syncHudState();
                self._renderPingHud();
                if (self.lobbyAuth && !self.soloMode && !self.multiplayerDisabled && self.currentZone === 'heroes') {
                    var hasWsRelay = Boolean(data.ws_relay && data.ws_relay.url && data.ws_relay.ticket);
                    if (hasWsRelay) {
                        self._wsDisabledByServer = false;
                    }
                    self._startSyncLoop(hasWsRelay ? 10000 : undefined);
                    if (hasWsRelay) {
                        self._connectLobbyWebSocket(data.ws_relay, data._switchHttpRtt);
                    }
                }
            })
            .catch(function () {
                self._recordNetFailure();
            });
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

    SeelenfunkeUnifiedLobbyHost.prototype._startLobbyMusicFromGestureSync = function (targetZone) {
        var cleanZone = (targetZone === 'heroes' || targetZone === 'hall' || targetZone === 'my_place') ? 'heroes' : 'gallery';
        if (this._audio && typeof this._audio.startLobbyMusicFromGesture === 'function') {
            this.audioState = this._audio.startLobbyMusicFromGesture(cleanZone);
            return;
        }
        if (typeof window !== 'undefined' && typeof window.getSeelenfunkeLobbyAudio === 'function') {
            this._audio = window.getSeelenfunkeLobbyAudio({ isAuthenticated: this.lobbyAuth });
            if (this._audio && typeof this._audio.startLobbyMusicFromGesture === 'function') {
                this.audioState = this._audio.startLobbyMusicFromGesture(cleanZone);
                return;
            }
        }
        try {
            var rawExplicit = localStorage.getItem('sf_lobby_bgm_explicit_muted');
            var rawVol = localStorage.getItem('sf_funki_audio_volume');
            var explicitMuted = (rawExplicit === '1' || rawExplicit === 'true') || rawVol === '0';
            if (!explicitMuted && typeof Audio !== 'undefined') {
                localStorage.setItem('sf_funki_audio_muted', '0');
                var preEl = window.__sfPrewarmLobbyBgmEl || new Audio('/shop/gamification/audio/bgm_funki_game.mp3');
                preEl.loop = true;
                preEl.preload = 'auto';
                preEl.volume = 0.025;
                window.__sfPrewarmLobbyBgmEl = preEl;
                ensurePrewarmRegistryAndRegister(preEl);
                window.__sfLobbyAudioGraceUntil = Date.now() + 1200;
                var self = this;
                setTimeout(function () {
                    if (!self.isOpen && window.__sfPrewarmLobbyBgmEl === preEl) {
                        stopPrewarmAndForceLobbySilence();
                    }
                }, 1500);
                var p = preEl.play();
                if (p && typeof p.catch === 'function') p.catch(function () {});
            }
        } catch (e) {}
    };

    SeelenfunkeUnifiedLobbyHost.prototype.open = function (initialMode) {
        var self = this;
        var mode = String(initialMode || 'lobby').toLowerCase();
        var startZone = (mode === 'hall' || mode === 'heroes' || mode === 'my_place') ? mode : 'gallery';

        this._startLobbyMusicFromGestureSync(startZone);

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
        this.sanctumGateOpen = false;
        this.outerGateModalOpen = false;
        this.listeningKeyAction = '';
        this._openSeq += 1;
        var seq = this._openSeq;

        this.modalEl.classList.remove('is-closed');
        this.modalEl.classList.add('is-open');
        this.modalEl.style.display = 'flex';
        this.currentZone = 'gallery';
        this.stagesMode = 'live';
        this._setSettingsOpen(false);
        this._setSanctumGateOpen(false);
        this._setOuterGateModalOpen(false);

        if (!this._hudTimer) {
            this._hudTimer = setInterval(function () {
                self._syncRealtimeHud();
            }, 120);
        }
        this._startAtelierPingLoop();

        var existingFacade = getFacade(this);
        if (existingFacade) {
            if (startZone === 'my_place') {
                existingFacade.setZone('heroes', true);
                existingFacade.focusMyHeroPlace();
            } else {
                existingFacade.setZone(startZone === 'hall' ? 'heroes' : startZone, true);
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
                if (!self.isOpen || seq !== self._openSeq) {
                    if (!self.isOpen) {
                        stopPrewarmAndForceLobbySilence();
                    }
                    return;
                }
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

                var rawPlayer = (shopJson && shopJson.player && typeof shopJson.player === 'object') ? shopJson.player : null;
                if (shopJson && shopJson.environment && typeof shopJson.environment === 'object') {
                    self.environmentInfo = shopJson.environment;
                }

                if (shopJson && shopJson.atelier_review && typeof shopJson.atelier_review === 'object') {
                    self._applyAtelierReviewPayload(shopJson.atelier_review);
                }

                self.mmorpgCanEnter = Boolean(
                    (shopJson && shopJson.mmorpg_can_enter)
                    || (rawPlayer && rawPlayer.mmorpg_can_enter)
                );
                if (shopJson && shopJson.mmorpg_enter_url) {
                    self.mmorpgEnterUrl = String(shopJson.mmorpg_enter_url);
                } else if (rawPlayer && rawPlayer.mmorpg_enter_url) {
                    self.mmorpgEnterUrl = String(rawPlayer.mmorpg_enter_url);
                }

                var isAuth = Boolean(
                    (rawPlayer && rawPlayer.is_authenticated)
                    || (self.funkiInfo && !self.funkiInfo.is_guest)
                    || (self.heroesPayload && self.heroesPayload.customer_logged_in)
                );
                self.lobbyAuth = isAuth;
                self.multiplayerDisabled = Boolean(
                    isAuth
                    && shopJson
                    && shopJson.multiplayer
                    && shopJson.multiplayer.enabled === false
                    && shopJson.multiplayer.solo_only === true
                );
                self._wsDisabledByServer = self.multiplayerDisabled;
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
                    self.audioState = self._audio.startLobbyMusicFromGesture((startZone === 'my_place' || startZone === 'hall') ? 'heroes' : startZone);
                    if (self._audioUnsub) self._audioUnsub();
                    self._audioUnsub = self._audio.onChange(function (snap) {
                        self.audioState = snap;
                        self._syncHudState();
                    });
                }

                self._renderKeybindingsList();
                self._renderOuterGateModal();

                if (self.loadingEl) {
                    self.loadingEl.style.display = 'none';
                }

                var createdFacade = mod.createLouvreShowroom({
                    canvas: self.canvasEl,
                    floatingCardEl: self.floatingProductWrap,
                    floatingHeroCardEl: self.floatingHeroWrap,
                    floatingPortalCardEl: self.floatingPortalWrap,
                    floatingOuterGateEl: self.floatingOuterGateWrap,
                    floatingStarStationEl: self.floatingStarStationWrap,
                    products: self.products,
                    environment: shopJson ? shopJson.environment : null,
                    player: rawPlayer,
                    heroesPayload: self.heroesPayload,
                    playerFunki: self.funkiInfo,
                    atelierReview: self.atelierReview,
                    mmorpgCanEnter: self.mmorpgCanEnter,
                    mmorpgEnterUrl: self.mmorpgEnterUrl,
                    audio: self._audio || undefined,
                    isAuthenticatedCustomer: isAuth,
                    initialZone: (startZone === 'my_place' || startZone === 'hall') ? 'heroes' : startZone,
                    onFocusProductChange: function (prod, idx) {
                        self.focusedProduct = prod || null;
                        if (typeof idx === 'number' && idx >= 0) {
                            self.activeIndex = idx;
                        }
                        self._renderFloatingProductCard(self.focusedProduct);
                        self._syncRealtimeHud();
                        self._syncHudState();
                    },
                    onFocusHeroChange: function (heroInfo) {
                        self.focusedHero = heroInfo || null;
                        self._renderFloatingHeroCard(self.focusedHero);
                    },
                    onActiveExhibitChange: function (idx) {
                        self.activeIndex = idx;
                        if (self.focusedProduct) {
                            self._renderFloatingProductCard(self.focusedProduct);
                        }
                        self._syncHudState();
                    },
                    onZoneChange: function (zone) {
                        self.currentZone = zone;
                        self._handleZoneSyncTransition(zone);
                        self._syncHudState();
                        self._renderPingHud();
                    },
                    onLocalUrgentSyncNeeded: function () {
                        self._requestImmediateSync();
                    },
                    onAuthGatePrompt: function (zone, accessState) {
                        self._setSanctumGateOpen(true, 'heroes', accessState);
                    },
                    onOuterGateModalOpen: function (mmorpgState) {
                        self._setOuterGateModalOpen(true, mmorpgState);
                    },
                    onAtelierStarStationFocusChange: function (focused) {
                        if (focused) {
                            self._renderAtelierStarCard();
                        }
                    },
                    onPlayerHudChange: function (hud) {
                        if (hud) {
                            self.playerHud = hud;
                            if (hud.mmorpgCanEnter !== undefined) {
                                self.mmorpgCanEnter = Boolean(hud.mmorpgCanEnter);
                            }
                            if (hud.mmorpgEnterUrl) {
                                self.mmorpgEnterUrl = String(hud.mmorpgEnterUrl);
                            }
                            if (hud.environment) {
                                self.environmentInfo = hud.environment;
                            }
                        }
                    },
                    onSelectProduct: function (prod) {
                        self._startProductTransition(prod);
                    }
                });
                setFacade(self, createdFacade);
                if (typeof createdFacade.getSanctumAccessState === 'function') {
                    self.sanctumGateState = createdFacade.getSanctumAccessState();
                }
                if (typeof createdFacade.getPlayerHudState === 'function') {
                    self.playerHud = createdFacade.getPlayerHudState();
                }
                self.currentZone = createdFacade.getCurrentZone();
                if (startZone === 'my_place' && self.sanctumGateState.canEnter) {
                    createdFacade.focusMyHeroPlace();
                }

                self._activeSyncZone = null;
                self._syncHudState();
                self._syncRealtimeHud();
                if (isAuth && self.sanctumGateState.canEnter && self.currentZone === 'heroes') {
                    self._handleZoneSyncTransition('heroes');
                }
            })
            .catch(function () {
                stopPrewarmAndForceLobbySilence();
                if (self.loadingEl) {
                    var txt = self.loadingEl.querySelector('[data-lobby-loading-text]');
                    if (txt) txt.textContent = '3D-Atelier konnte auf diesem Gerät nicht geladen werden.';
                }
            });
    };

    SeelenfunkeUnifiedLobbyHost.prototype.close = function () {
        if (!this.isOpen) {
            stopPrewarmAndForceLobbySilence();
            return;
        }
        this._stopSyncLoop();
        this._stopAtelierPingLoop();
        this._stopChatMuteCountdown();
        this.pingTooltipOpen = false;
        if (this.modalEl) {
            var pAnc = this.modalEl.querySelector('[data-lobby-ping-anchor]');
            if (pAnc) pAnc.classList.remove('is-open');
        }
        if (this._activeSyncZone === 'heroes' && !this.soloMode && !this.multiplayerDisabled) {
            this._sendLeaveBeacon();
        }
        this._activeSyncZone = null;
        this.isOpen = false;
        this.instanceMenuOpen = false;
        this.settingsOpen = false;
        this.sanctumGateOpen = false;
        this.outerGateModalOpen = false;
        this.listeningKeyAction = '';
        this.peers = [];
        if (this._loggedInDuringSession) {
            this._loggedInDuringSession = false;
            dispatchLivewireAuthSync();
        }

        if (this._productTransitionTimer) {
            clearTimeout(this._productTransitionTimer);
            this._productTransitionTimer = null;
        }
        this._productTransitioning = false;
        if (this.modalEl) {
            var ivoryEl = this.modalEl.querySelector('[data-lobby-ivory-transition]');
            if (ivoryEl) ivoryEl.classList.remove('is-fading');
            this.modalEl.classList.remove('is-open');
            this.modalEl.classList.add('is-closed');
            this.modalEl.style.display = 'none';
        }

        if (this._audioUnsub) {
            this._audioUnsub();
            this._audioUnsub = null;
        }
        if (this._hudTimer) {
            clearInterval(this._hudTimer);
            this._hudTimer = null;
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

        if (this._audio && typeof this._audio.setLobbyActive === 'function') {
            this._audio.setLobbyActive(false);
        }
        var facade = getFacade(this);
        if (facade) {
            facade.destroy();
            setFacade(this, null);
        }
        stopPrewarmAndForceLobbySilence();
        document.documentElement.style.overflow = this.prevHtmlOverflow;
        document.body.style.overflow = this.prevBodyOverflow;
    };

    SeelenfunkeUnifiedLobbyHost.prototype.dispose = function () {
        this.close();
        stopPrewarmAndForceLobbySilence();
        if (this._fsResizeTimer) {
            clearTimeout(this._fsResizeTimer);
            this._fsResizeTimer = null;
        }
        window.removeEventListener('keydown', this._boundKeydown, true);
        document.removeEventListener('fullscreenchange', this._boundFullscreenChange);
        document.removeEventListener('webkitfullscreenchange', this._boundFullscreenChange);
        document.removeEventListener('click', this._boundOutsideClick);
        window.removeEventListener('pagehide', this._boundPageHide);
        window.removeEventListener('beforeunload', this._boundPageHide);
        document.removeEventListener('livewire:navigating', this._boundLivewireNavigating);
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
