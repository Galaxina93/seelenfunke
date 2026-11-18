(function () {
    'use strict';

    if (window.SeelenfunkeHeroes3D) {
        return;
    }

    var MODULE_URL = '/js/seelenfunke-louvre-showroom-3d.js?v=20261118v2';

    var KEYBIND_ACTIONS = [
        { key: 'jump', label: 'Sprung' },
        { key: 'strike', label: '1 · Funken-Hieb' },
        { key: 'sprint', label: '2 · Licht-Sprint' },
        { key: 'titanJump', label: '3 · Aufwind-Impuls' },
        { key: 'shield', label: '4 · Aegis-Schild' },
        { key: 'burst', label: '5 · Seelen-Nova' },
        { key: 'moveForward', label: 'Vorwärts' },
        { key: 'moveLeft', label: 'Links' },
        { key: 'moveBack', label: 'Zurück' },
        { key: 'moveRight', label: 'Rechts' }
    ];

    var ABILITY_BAR_DEFS = [
        {
            slot: 1,
            testIdAttr: 'data-testid="ability-slot-1-btn"',
            actionKey: 'strike',
            name: 'Funken-Hieb',
            manaText: '0 Mana',
            cdText: 'Kombo 1-4',
            desc: '4-stufige Nahkampf-Kombo mit goldenem Wirbel und Finisher-Druckwelle.',
            svg: '<svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.5 4.5L9.5 16.5"/><path d="M17.5 4.5H21.5V8.5"/><path d="M6.5 14.5L13.5 21.5"/><path d="M5 23L9.5 18.5"/><path d="M18.5 12.5C21 15 21.5 18.8 19.2 21.5" stroke-opacity="0.62"/></svg>'
        },
        {
            slot: 2,
            testIdAttr: 'data-testid="ability-slot-2-btn"',
            actionKey: 'sprint',
            name: 'Licht-Sprint',
            manaText: '18 Mana',
            cdText: '10 s Abklingzeit',
            desc: 'Erhöht dein Lauftempo 4,5 Sekunden lang um 55 % mit goldener Funkenspur.',
            svg: '<svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 10.5H15.5C17.7 10.5 19.2 9 19.2 7.2C19.2 5.5 17.8 4.3 16.2 4.3C14.8 4.3 13.7 5.2 13.5 6.5"/><path d="M3.5 15H20.5C22.6 15 24.2 16.5 24.2 18.5C24.2 20.4 22.7 21.8 20.9 21.8C19.3 21.8 18.1 20.8 17.9 19.4"/><path d="M6.5 19.5H13.5"/><circle cx="22" cy="9.5" r="1.3" fill="currentColor" stroke="none"/></svg>'
        },
        {
            slot: 3,
            testIdAttr: 'data-testid="ability-slot-3-btn"',
            actionKey: 'titanJump',
            name: 'Aufwind-Impuls',
            manaText: '22 Mana',
            cdText: '8 s Abklingzeit',
            desc: 'Katapultiert dich kraftvoll in die Höhe zum Überwinden weiter Schluchten und Ringe.',
            svg: '<svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4.5L8.2 11.2H11.8V18.5H16.2V11.2H19.8L14 4.5Z"/><path d="M7 21.2C9.2 19.6 11.5 19 14 19C16.5 19 18.8 19.6 21 21.2"/><path d="M9.5 24C11 23.1 12.4 22.7 14 22.7C15.6 22.7 17 23.1 18.5 24" stroke-opacity="0.65"/></svg>'
        },
        {
            slot: 4,
            testIdAttr: 'data-testid="ability-slot-4-btn"',
            actionKey: 'shield',
            name: 'Aegis-Schild',
            manaText: '25 Mana',
            cdText: '15 s Abklingzeit',
            desc: 'Schützt 6,5 s vor Schockwellen und katapultiert dich bei Lava-Kontakt einmalig hoch hinaus, bevor das Schild zerbricht.',
            svg: '<svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3.8L22 7.2V13.6C22 18.6 18.6 22.5 14 24.4C9.4 22.5 6 18.6 6 13.6V7.2L14 3.8Z"/><path d="M14 8.2L15.6 11.8L19.4 13.4L15.6 15L14 18.6L12.4 15L8.6 13.4L12.4 11.8L14 8.2Z" stroke-width="1.4"/></svg>'
        },
        {
            slot: 5,
            testIdAttr: 'data-testid="ability-slot-5-btn"',
            actionKey: 'burst',
            name: 'Seelen-Nova',
            manaText: '30 Mana',
            cdText: '20 s Abklingzeit',
            desc: 'Entfesselt eine radiale 24K-Gold-Energiewelle und löst nahe Gefahrenringe im Umkreis auf.',
            svg: '<svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="14" cy="14" r="3.6"/><circle cx="14" cy="14" r="8.2" stroke-dasharray="2.6 2.6"/><path d="M14 2.8V5.8M14 22.2V25.2M2.8 14H5.8M22.2 14H25.2M6.1 6.1L8.2 8.2M19.8 19.8L21.9 21.9M21.9 6.1L19.8 8.2M8.2 19.8L6.1 21.9"/></svg>'
        }
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
        '.sf-lobby-actions { pointer-events: auto; display: inline-flex; align-items: center; gap: 7px; flex-wrap: wrap; }',
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
        '.sf-lobby-portal-pill { pointer-events: auto; display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px; background: rgba(255,255,255,0.97); color: #111827; border: 1px solid #C5A059; box-shadow: 0 14px 32px rgba(17,24,39,0.14); font-size: 12px; font-weight: 800; text-decoration: none; white-space: nowrap; }',
        '.sf-lobby-lava-chest-card { pointer-events: auto; display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 999px; background: rgba(255,255,255,0.98); color: #111827; border: 1.5px solid #C5A059; box-shadow: 0 14px 32px rgba(17,24,39,0.16); font-size: 12px; font-weight: 800; white-space: nowrap; cursor: pointer; text-decoration: none; }',
        '.sf-lobby-lava-chest-card.is-cta { background: #C5A059; color: #FFFFFF; }',
        '.sf-lobby-lava-chest-card.is-cta:hover { background: #B38E46; }',
        '.sf-lobby-unit-frame { pointer-events: auto; display: inline-flex; align-items: center; gap: 9px; padding: 6px 13px 6px 7px; border-radius: 999px; background: rgba(255,255,255,0.96); border: 1px solid rgba(197,160,89,0.55); box-shadow: 0 8px 24px rgba(17,24,39,0.08); color: #111827; }',
        '.sf-lobby-unit-avatar { width: 32px; height: 32px; border-radius: 999px; background: #FAF8F5; border: 1.5px solid #C5A059; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 900; color: #9A7738; flex-shrink: 0; }',
        '.sf-lobby-unit-meta { display: flex; flex-direction: column; gap: 3px; min-width: 134px; }',
        '.sf-lobby-unit-mana-row { display: flex; align-items: center; gap: 6px; }',
        '.sf-lobby-unit-mana-label { font-size: 9.5px; font-weight: 900; letter-spacing: 0.06em; text-transform: uppercase; color: #1D4ED8; line-height: 1; flex-shrink: 0; }',
        '.sf-lobby-unit-mana-track { flex: 1 1 auto; height: 6px; border-radius: 999px; background: rgba(29,78,216,0.16); border: 1px solid rgba(29,78,216,0.26); overflow: hidden; }',
        '.sf-lobby-unit-mana-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #1d4ed8 0%, #38bdf8 100%); transition: width 160ms ease; }',
        '.sf-lobby-unit-mana-val { font-size: 9.5px; font-weight: 800; color: #1E40AF; line-height: 1; flex-shrink: 0; font-variant-numeric: tabular-nums; }',
        '.sf-lobby-topright-stack { position: absolute; top: 60px; right: 18px; z-index: 25; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; pointer-events: none; }',
        '.sf-lobby-vital-hud { pointer-events: auto; width: 180px; padding: 8px 11px 7px; border-radius: 16px; background: rgba(255,255,255,0.96); border: 1.5px solid #C5A059; box-shadow: 0 10px 26px rgba(17,24,39,0.12); display: flex; flex-direction: column; gap: 6px; box-sizing: border-box; }',
        '.sf-lobby-vital-hearts { display: flex; align-items: center; justify-content: space-between; gap: 3px; }',
        '.sf-lobby-heart-icon { width: 21px; height: 21px; display: inline-flex; align-items: center; justify-content: center; transition: transform 220ms cubic-bezier(0.22,1,0.36,1), opacity 220ms ease; }',
        '.sf-lobby-heart-icon svg { width: 100%; height: 100%; display: block; overflow: visible; }',
        '.sf-lobby-heart-icon.is-shattering { animation: sfHeartShatter 520ms cubic-bezier(0.22,1,0.36,1); }',
        '@keyframes sfHeartShatter { 0% { transform: scale(1.28) rotate(-8deg); filter: brightness(1.3); } 45% { transform: scale(0.72) rotate(10deg); } 100% { transform: scale(1) rotate(0deg); filter: none; } }',
        '.sf-lobby-demote-banner { position: absolute; top: 68px; left: 50%; transform: translateX(-50%); z-index: 42; padding: 9px 18px; border-radius: 999px; background: rgba(255,255,255,0.98); border: 1.5px solid #DC2626; color: #991B1B; font-size: 12.5px; font-weight: 800; box-shadow: 0 14px 32px rgba(17,24,39,0.18); white-space: nowrap; pointer-events: none; }',
        '.sf-lobby-minimap-wrap { position: relative; width: 180px; height: 180px; z-index: 25; pointer-events: auto; display: flex; align-items: center; justify-content: center; }',
        '.sf-lobby-minimap-canvas { width: 100%; height: 100%; border-radius: 999px; background: rgba(255,255,255,0.96); border: 2.5px solid #C5A059; box-shadow: 0 12px 30px rgba(17,24,39,0.16), 0 0 0 3px rgba(255,255,255,0.72); display: block; }',
        '.sf-lobby-minimap-time { position: absolute; bottom: -9px; left: 50%; transform: translateX(-50%); padding: 2px 10px; border-radius: 999px; background: #FFFFFF; border: 1px solid #C5A059; color: #111827; font-size: 10.5px; font-weight: 800; line-height: 1.25; white-space: nowrap; box-shadow: 0 4px 12px rgba(17,24,39,0.12); cursor: help; }',
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
        '.sf-lobby-gold-crest-bar { position: absolute; left: 0; right: 0; bottom: 0; min-height: 62px; padding: 6px 20px calc(8px + env(safe-area-inset-bottom, 0px)); background: linear-gradient(180deg, rgba(250,248,245,0.88) 0%, rgba(255,255,255,0.98) 38%, #FAF6EE 100%); border-top: 2px solid #C5A059; box-shadow: 0 -8px 28px rgba(17,24,39,0.09), 0 1px 0 rgba(255,255,255,0.9) inset; display: flex; align-items: center; justify-content: center; z-index: 26; pointer-events: auto; box-sizing: border-box; }',
        '.sf-lobby-gold-crest-bar::before { content: ""; position: absolute; top: 3px; left: 18px; right: 18px; height: 1px; background: linear-gradient(90deg, transparent 0%, rgba(197,160,89,0.55) 18%, rgba(197,160,89,0.85) 50%, rgba(197,160,89,0.55) 82%, transparent 100%); pointer-events: none; }',
        '.sf-lobby-copyright { position: absolute; left: 20px; bottom: calc(18px + env(safe-area-inset-bottom, 0px)); font-size: 11px; font-weight: 700; letter-spacing: 0.03em; color: #7C6334; white-space: nowrap; pointer-events: none; }',
        '.sf-lobby-abilities-dock { position: relative; display: flex; align-items: center; justify-content: center; gap: 10px; z-index: 27; pointer-events: auto; }',
        '.sf-ability-btn { position: relative; width: 48px; height: 48px; border-radius: 13px; background: #FFFFFF; border: 1.5px solid rgba(197,160,89,0.68); color: #8A692C; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 14px rgba(17,24,39,0.08), 0 0 0 1px rgba(250,246,238,0.9) inset; transition: transform 0.14s ease, border-color 0.15s ease, background 0.15s ease, color 0.15s ease; padding: 0; }',
        '.sf-ability-btn:hover { border-color: #C5A059; transform: translateY(-2px); background: #FAF6EE; color: #6E501B; }',
        '.sf-ability-btn.is-active { border-color: #C5A059; background: #FAF3E3; color: #78561C; box-shadow: 0 6px 18px rgba(197,160,89,0.34); }',
        '.sf-ability-btn.is-cooldown { opacity: 0.56; border-color: rgba(156,163,175,0.55); color: #6B7280; }',
        '.sf-ability-svg { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; pointer-events: none; }',
        '.sf-ability-svg svg { width: 100%; height: 100%; display: block; }',
        '.sf-ability-key { position: absolute; top: 3px; left: 5px; font-size: 9px; font-weight: 900; color: #6B7280; line-height: 1; pointer-events: none; }',
        '.sf-ability-cd { position: absolute; bottom: 3px; right: 4px; font-size: 9px; font-weight: 800; color: #FFFFFF; background: rgba(17,24,39,0.88); padding: 1px 4px; border-radius: 5px; line-height: 1; pointer-events: none; }',
        '.sf-ability-combo-badge { position: absolute; top: -6px; right: -6px; background: #C5A059; color: #FFFFFF; font-size: 9px; font-weight: 900; padding: 2px 5px; border-radius: 999px; border: 1.5px solid #FFFFFF; line-height: 1; box-shadow: 0 3px 8px rgba(17,24,39,0.18); pointer-events: none; }',
        '.sf-ability-tooltip { position: absolute; bottom: calc(100% + 12px); left: 50%; transform: translateX(-50%) translateY(4px); width: 224px; padding: 10px 12px; border-radius: 13px; background: rgba(255,255,255,0.98); border: 1px solid #C5A059; box-shadow: 0 14px 32px rgba(17,24,39,0.16); color: #111827; text-align: left; opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 140ms ease, transform 140ms ease; z-index: 60; box-sizing: border-box; white-space: normal; }',
        '.sf-ability-btn:hover .sf-ability-tooltip, .sf-ability-btn:focus-visible .sf-ability-tooltip { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }',
        '.sf-ability-tooltip-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 4px; }',
        '.sf-ability-tooltip-name { font-size: 12px; font-weight: 800; color: #111827; }',
        '.sf-ability-tooltip-key { padding: 1px 6px; border-radius: 5px; background: #FAF6EE; border: 1px solid rgba(197,160,89,0.5); font-size: 9.5px; font-weight: 800; color: #8A692C; }',
        '.sf-ability-tooltip-meta { display: flex; align-items: center; gap: 8px; font-size: 10px; font-weight: 800; color: #1D4ED8; margin-bottom: 5px; }',
        '.sf-ability-tooltip-cd { color: #6B7280; }',
        '.sf-ability-tooltip-desc { margin: 0; font-size: 10.5px; font-weight: 500; line-height: 1.36; color: #374151; }',
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
        '.sf-lobby-bottom-stack { position: absolute; bottom: calc(70px + env(safe-area-inset-bottom, 0px)); left: 12px; right: 12px; z-index: 24; display: flex; flex-direction: column; align-items: center; gap: 7px; pointer-events: none; }',
        '.sf-lobby-bottom-pill { pointer-events: auto; display: inline-flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: rgba(255,255,255,0.95); border: 1px solid rgba(197,160,89,0.48); box-shadow: 0 10px 28px rgba(17,24,39,0.12); max-width: 100%; box-sizing: border-box; }',
        '.sf-lobby-step-btn { appearance: none; padding: 6px 12px; border-radius: 999px; border: 1px solid rgba(197,160,89,0.40); background: #FAF8F5; color: #111827; font-size: 11px; font-weight: 800; cursor: pointer; white-space: nowrap; }',
        '.sf-lobby-step-btn.is-active { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }',
        '@media (max-width: 900px), (pointer: coarse) { .sf-lobby-stick-wrap { display: block; bottom: calc(70px + env(safe-area-inset-bottom, 0px)); left: 12px; } .sf-lobby-stick { width: 70px; height: 70px; } .sf-lobby-topright-stack { top: 56px; right: 12px; gap: 6px; } .sf-lobby-vital-hud { width: 148px; padding: 6px 8px; } .sf-lobby-heart-icon { width: 17px; height: 17px; } .sf-lobby-minimap-wrap { width: 136px; height: 136px; } .sf-ability-btn { width: 44px; height: 44px; border-radius: 11px; } .sf-ability-svg { width: 21px; height: 21px; } .sf-ability-tooltip { display: none; } }',
        '@media (max-width: 767px) { .sf-lobby-topbar { padding: 8px 10px; gap: 6px; } .sf-lobby-zone-badge { padding: 5px 9px; font-size: 11px; } .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 6px 9px; font-size: 11px; } .sf-lobby-kbd { display: none !important; } .sf-lobby-copyright { font-size: 9.5px; left: 10px; bottom: calc(20px + env(safe-area-inset-bottom, 0px)); } .sf-lobby-keybind-grid { grid-template-columns: 1fr; } }',
        '@media (max-width: 559px) { .sf-lobby-topright-stack { top: 92px; right: 10px; gap: 5px; } .sf-lobby-vital-hud { width: 132px; padding: 5px 7px; } .sf-lobby-heart-icon { width: 15px; height: 15px; } .sf-lobby-minimap-wrap { width: 116px; height: 116px; } .sf-lobby-unit-meta { min-width: 112px; } .sf-lobby-gold-crest-bar { min-height: 56px; padding: 5px 8px calc(6px + env(safe-area-inset-bottom, 0px)); justify-content: flex-end; } .sf-lobby-copyright { left: 10px; font-size: 9px; max-width: 96px; overflow: hidden; text-overflow: ellipsis; } .sf-lobby-abilities-dock { gap: 6px; } .sf-ability-btn { width: 40px; height: 40px; border-radius: 10px; } .sf-ability-svg { width: 19px; height: 19px; } .sf-ability-key { display: none; } .sf-lobby-floating-card { width: 266px; padding: 12px 13px 10px; } .sf-lobby-prod-title { font-size: 13.5px; } }'
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
        } else if (!existing.textContent || existing.textContent.indexOf('.sf-lobby-vital-hud') === -1) {
            existing.textContent = STANDALONE_SCOPED_CSS;
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

    function buildRubyHeartSvg(isFilled) {
        if (isFilled) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true">'
                + '<path d="M12 21.2L10.4 19.7C5.1 14.9 2 12.1 2 8.4C2 5.4 4.4 3 7.4 3C9.2 3 10.9 3.9 12 5.2C13.1 3.9 14.8 3 16.6 3C19.6 3 22 5.4 22 8.4C22 12.1 18.9 14.9 13.6 19.7L12 21.2Z" fill="#DC2626" stroke="#C5A059" stroke-width="1.55" stroke-linejoin="round"/>'
                + '<path d="M12 5.4L9.2 9.4L12 19.4L14.8 9.4L12 5.4Z" fill="#EF4444" fill-opacity="0.45"/>'
                + '<path d="M6.2 6.1C7.1 5.1 8.5 4.9 9.5 5.4" fill="none" stroke="#FEE2E2" stroke-width="1.35" stroke-linecap="round"/>'
                + '</svg>';
        }
        return '<svg viewBox="0 0 24 24" aria-hidden="true">'
            + '<path d="M12 21.2L10.4 19.7C5.1 14.9 2 12.1 2 8.4C2 5.4 4.4 3 7.4 3C9.2 3 10.9 3.9 12 5.2C13.1 3.9 14.8 3 16.6 3C19.6 3 22 5.4 22 8.4C22 12.1 18.9 14.9 13.6 19.7L12 21.2Z" fill="#1F2937" fill-opacity="0.22" stroke="#9CA3AF" stroke-width="1.35" stroke-linejoin="round"/>'
            + '</svg>';
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
        this.floatingOuterGateWrap = null;
        this.minimapCanvasEl = null;

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
        this.sanctumGateOpen = false;
        this.sanctumGateZone = 'heroes';
        this.sanctumExpandedStep = '';
        this.inlineLoginEmail = '';
        this.inlineLoginPassword = '';
        this.inlineLoginShowPassword = false;
        this.inlineLoginTwoFactorRequired = false;
        this.inlineLoginTwoFactorCode = '';
        this.inlineMagicAcceptTerms = false;
        this.inlineMagicRankingOptIn = true;
        this.inlineAuthBusy = false;
        this.inlineAuthError = '';
        this.sanctumGateState = {
            canEnter: false,
            reason: 'login_required',
            checks: { login: false, order: false, magic: false },
            loginUrl: '/login',
            dashboardUrl: '/dashboard'
        };
        this.masterKeyModalOpen = false;
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
            name: 'Funki',
            level: 2,
            levelTitle: 'Lehrling',
            funkenBalance: 0,
            mana: 100,
            maxMana: 100,
            hearts: 6,
            maxHearts: 6,
            lastHeartLostSeq: 0,
            lastHeartLostIndex: -1,
            demoteBannerText: '',
            giftVoucherUrl: '/produkt/geschenkgutschein'
        };
        this._lastSeenHeartLostSeq = 0;
        this._heartShatterIdx = -1;
        this._heartShatterTimer = null;
        this.keybindingsExpanded = false;
        this.listeningKeyAction = '';
        this.userSettings = {
            volume: 50,
            cameraSensitivity: 100,
            useRealName: false,
            graphicsQuality: 'high'
        };
        this.keybindings = {
            strike: 'Digit1',
            sprint: 'Digit2',
            titanJump: 'Digit3',
            shield: 'Digit4',
            burst: 'Digit5',
            jump: 'Space',
            moveForward: 'KeyW',
            moveLeft: 'KeyA',
            moveBack: 'KeyS',
            moveRight: 'KeyD'
        };
        this._productTransitionTimer = null;
        this._productTransitioning = false;
        this.audioState = {
            bgmVolume: 50,
            bgmMuted: false,
            sfxVolume: 50,
            sfxMuted: false,
            allMuted: false
        };
        this.lavaChestState = {
            level: 1,
            rewardPreview: 5,
            nearChest: false,
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
                var fEsc = getFacade(self);
                if (fEsc && typeof fEsc.releasePointerLock === 'function') {
                    fEsc.releasePointerLock();
                }
                if (self.sanctumGateOpen) {
                    self._setSanctumGateOpen(false);
                } else if (self.masterKeyModalOpen) {
                    self._setMasterKeyModalOpen(false);
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

        var abilityButtonsHtml = [];
        for (var ai = 0; ai < ABILITY_BAR_DEFS.length; ai++) {
            var ad = ABILITY_BAR_DEFS[ai];
            abilityButtonsHtml.push(
                '<button type="button" data-ability-slot="' + ad.slot + '" ' + ad.testIdAttr + ' class="sf-ability-btn" aria-label="' + ad.name + '">',
                '  <span class="sf-ability-svg">' + ad.svg + '</span>',
                '  <span data-ability-key="' + ad.actionKey + '" class="sf-ability-key">' + ad.slot + '</span>',
                '  <span data-ability-cd="' + ad.slot + '" class="sf-ability-cd" style="display:none;"></span>',
                (ad.slot === 1 ? '  <span data-ability-combo-badge class="sf-ability-combo-badge" style="display:none;">x1</span>' : ''),
                '  <div class="sf-ability-tooltip" role="tooltip">',
                '    <div class="sf-ability-tooltip-head">',
                '      <span class="sf-ability-tooltip-name">' + ad.name + '</span>',
                '      <span data-ability-tooltip-key="' + ad.actionKey + '" class="sf-ability-tooltip-key">Taste ' + ad.slot + '</span>',
                '    </div>',
                '    <div class="sf-ability-tooltip-meta">',
                '      <span>' + ad.manaText + '</span>',
                '      <span class="sf-ability-tooltip-cd">· ' + ad.cdText + '</span>',
                '    </div>',
                '    <p class="sf-ability-tooltip-desc">' + ad.desc + '</p>',
                '  </div>',
                '</button>'
            );
        }

        root.innerHTML = [
            '<div class="sf-lobby-topbar">',
            '  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;pointer-events:none;">',
            '    <div data-lobby-unit-frame data-testid="lobby-player-unit-frame" class="sf-lobby-unit-frame">',
            '      <span class="sf-lobby-unit-avatar">✦</span>',
            '      <div class="sf-lobby-unit-meta">',
            '        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:11px;font-weight:800;line-height:1.15;">',
            '          <span data-lobby-unit-name style="max-width:112px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Funki</span>',
            '          <span data-lobby-unit-stats style="color:#9A7738;">Stufe 2 · ✨ 0</span>',
            '        </div>',
            '        <div class="sf-lobby-unit-mana-row">',
            '          <span class="sf-lobby-unit-mana-label">Mana</span>',
            '          <div class="sf-lobby-unit-mana-track">',
            '            <div data-lobby-unit-mana class="sf-lobby-unit-mana-fill" style="width:100%;"></div>',
            '          </div>',
            '          <span data-lobby-unit-mana-val class="sf-lobby-unit-mana-val">100</span>',
            '        </div>',
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
            '  <div data-lobby-ivory-transition class="sf-lobby-ivory-transition"></div>',
            '  <div data-lobby-demote-banner data-testid="lobby-lava-demote-banner" class="sf-lobby-demote-banner" style="display:none;"></div>',
            '  <div class="sf-lobby-topright-stack">',
            '    <div data-lobby-vital-hud data-testid="lobby-vital-hud" class="sf-lobby-vital-hud">',
            '      <div data-lobby-vital-hearts class="sf-lobby-vital-hearts" aria-label="Lebensherzen">',
            '        <span data-heart-slot="0" class="sf-lobby-heart-icon">' + buildRubyHeartSvg(true) + '</span>',
            '        <span data-heart-slot="1" class="sf-lobby-heart-icon">' + buildRubyHeartSvg(true) + '</span>',
            '        <span data-heart-slot="2" class="sf-lobby-heart-icon">' + buildRubyHeartSvg(true) + '</span>',
            '        <span data-heart-slot="3" class="sf-lobby-heart-icon">' + buildRubyHeartSvg(true) + '</span>',
            '        <span data-heart-slot="4" class="sf-lobby-heart-icon">' + buildRubyHeartSvg(true) + '</span>',
            '        <span data-heart-slot="5" class="sf-lobby-heart-icon">' + buildRubyHeartSvg(true) + '</span>',
            '      </div>',
            '      <div class="sf-lobby-unit-mana-row">',
            '        <span class="sf-lobby-unit-mana-label">Mana</span>',
            '        <div class="sf-lobby-unit-mana-track">',
            '          <div data-lobby-vital-mana class="sf-lobby-unit-mana-fill" style="width:100%;"></div>',
            '        </div>',
            '        <span data-lobby-vital-mana-val class="sf-lobby-unit-mana-val">100/100</span>',
            '      </div>',
            '    </div>',
            '    <div data-lobby-minimap data-testid="lobby-minimap" class="sf-lobby-minimap-wrap">',
            '      <canvas data-lobby-minimap-canvas width="180" height="180" class="sf-lobby-minimap-canvas"></canvas>',
            '      <span data-lobby-minimap-time class="sf-lobby-minimap-time" title="Herbst">12:00</span>',
            '    </div>',
            '  </div>',
            '  <div data-lobby-loading class="sf-lobby-loading">',
            '    <div class="sf-lobby-spinner"></div>',
            '    <span data-lobby-loading-text class="sf-lobby-loading-label">3D-Lobby wird geöffnet</span>',
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
            '    <button type="button" data-lobby-portal-pill class="sf-lobby-portal-pill" style="cursor:pointer;">🔒 Halle der Helden · Voraussetzungen →</button>',
            '  </div>',
            '  <div data-lobby-lava-chest-card data-testid="lobby-lava-chest-card" class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div data-lobby-lava-chest-inner style="display:flex;flex-direction:column;align-items:center;gap:6px;"></div>',
            '    <div class="sf-lobby-floating-arrow"></div>',
            '  </div>',
            '  <div data-lobby-outer-gate-card data-testid="lobby-outer-gate-card" class="sf-lobby-floating-anchor" style="opacity:0;visibility:hidden;pointer-events:none;transform:translate3d(-9999px,-9999px,0);">',
            '    <div class="sf-lobby-portal-pill">🔒 Tor zur Außenwelt · Bald geöffnet</div>',
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
            '        <div data-sanctum-row="order" data-testid="sanctum-check-order" class="sf-lobby-sanctum-row is-clickable"><span>Mindestens 1 Bestellung</span><span data-sanctum-chk="order" class="sf-lobby-sanctum-check">✕</span></div>',
            '        <div data-sanctum-row="magic" data-testid="sanctum-check-magic" class="sf-lobby-sanctum-row is-clickable"><span>✨ Magie aktivieren</span><span data-sanctum-chk="magic" class="sf-lobby-sanctum-check">✕</span></div>',
            '        <div data-sanctum-magic-panel data-testid="sanctum-inline-magic-panel" class="sf-lobby-inline-panel" style="display:none;">',
            '          <label class="sf-lobby-inline-check"><input type="checkbox" data-sanctum-magic-terms data-testid="sanctum-magic-terms-checkbox" style="width:16px;height:16px;accent-color:#C5A059;cursor:pointer;"><span>Teilnahmebedingungen akzeptieren</span></label>',
            '          <label class="sf-lobby-inline-check"><input type="checkbox" data-sanctum-magic-ranking data-testid="sanctum-magic-ranking-checkbox" checked style="width:16px;height:16px;accent-color:#C5A059;cursor:pointer;"><span>In der Halle der Helden sichtbar</span></label>',
            '          <div data-sanctum-magic-error class="sf-lobby-inline-error" style="display:none;"></div>',
            '          <button type="button" data-sanctum-magic-submit data-testid="sanctum-inline-magic-submit" class="sf-lobby-prod-cta" style="margin-top:2px;border:none;opacity:0.55;cursor:not-allowed;" disabled>✨ Magie aktivieren</button>',
            '        </div>',
            '      </div>',
            '      <div data-sanctum-cta-wrap></div>',
            '    </div>',
            '  </div>',
            '  <div data-lobby-master-key-modal data-testid="lobby-master-key-modal" class="sf-lobby-settings-backdrop" style="display:none;">',
            '    <div class="sf-lobby-settings-card" role="dialog" aria-label="Manufaktur-Meisterschlüssel">',
            '      <div class="sf-lobby-settings-head">',
            '        <h3 class="sf-lobby-settings-title"><span>🔑</span><span>Manufaktur-Meisterschlüssel</span></h3>',
            '        <button type="button" data-master-key-close style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;" aria-label="Schließen">&times;</button>',
            '      </div>',
            '      <div class="sf-lobby-sanctum-list">',
            '        <div class="sf-lobby-sanctum-row is-done"><span>Magma-Stufen 6 bis 10</span><span class="sf-lobby-sanctum-check">✓</span></div>',
            '      </div>',
            '      <div style="display:flex;flex-direction:column;gap:8px;">',
            '        <a href="/shop" class="sf-lobby-prod-cta" style="margin-top:0;">Unikat im Shop gestalten →</a>',
            '        <a data-master-key-voucher href="/produkt/geschenkgutschein" class="sf-lobby-step-btn" style="text-align:center;text-decoration:none;padding:9px 14px;border-radius:12px;">15,00 € Gutschein sichern →</a>',
            '      </div>',
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
            '        <button type="button" data-lobby-settings-resume class="sf-lobby-prod-cta" style="margin-top:0;flex:1;border:none;cursor:pointer;">Weiterspielen</button>',
            '        <button type="button" data-lobby-settings-leave class="sf-lobby-step-btn" style="padding:9px 14px;border-radius:12px;">Lobby verlassen</button>',
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
            '  <div data-testid="lobby-gold-crest-bar" class="sf-lobby-gold-crest-bar">',
            '    <span class="sf-lobby-copyright">© Mein-Seelenfunke</span>',
            '    <div data-lobby-abilities-dock data-testid="louvre-abilities-dock" class="sf-lobby-abilities-dock">',
            abilityButtonsHtml.join(''),
            '    </div>',
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
        this.floatingLavaChestWrap = root.querySelector('[data-lobby-lava-chest-card]');
        this.floatingOuterGateWrap = root.querySelector('[data-lobby-outer-gate-card]');
        this.stickZoneEl = root.querySelector('[data-lobby-stick]');
        this.stickKnobEl = root.querySelector('[data-lobby-stick-knob]');

        this._bindModalUiEvents();
        this._renderKeybindingsList();
        this._renderLavaChestCard();
        this._renderSanctumGateModal();
    };

    SeelenfunkeUnifiedLobbyHost.prototype._setSanctumGateOpen = function (flag, zone, incomingState) {
        this.sanctumGateOpen = Boolean(flag);
        this.inlineAuthError = '';
        if (zone) {
            this.sanctumGateZone = (zone === 'lava') ? 'lava' : 'heroes';
        }
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
                    order: Boolean(resolved.checks && resolved.checks.order),
                    magic: Boolean(resolved.checks && resolved.checks.magic)
                },
                loginUrl: resolved.loginUrl || '/login',
                dashboardUrl: resolved.dashboardUrl || '/dashboard'
            };
        }
        if (this.sanctumGateOpen) {
            if (!this.sanctumGateState.checks.login) {
                this.sanctumExpandedStep = 'login';
            } else if (this.sanctumGateState.checks.order && !this.sanctumGateState.checks.magic) {
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

    SeelenfunkeUnifiedLobbyHost.prototype._focusNearestVitrineFromModal = function () {
        this._setSanctumGateOpen(false);
        if (this._audio) this._audio.playUiClick();
        var facade = getFacade(this);
        if (facade && typeof facade.focusNearestExhibit === 'function') {
            facade.focusNearestExhibit();
        } else if (facade && typeof facade.focusExhibit === 'function') {
            facade.focusExhibit(this.activeIndex || 0, false);
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._applyLiveAccountPayload = function (body) {
        if (!body || typeof body !== 'object') return;
        if (body.csrf_token) {
            updateCsrfMetaToken(body.csrf_token);
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
                    order: Boolean(sa.has_completed_order),
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
                        order: Boolean(nextAccess.checks && nextAccess.checks.order),
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
        this._syncAbilityHud();
        this._renderLavaChestCard();
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
                    window.location.href = String(body.redirect_url);
                    return;
                }
                if (body.status === 'authenticated' || body.success) {
                    if (pwdInp) pwdInp.value = '';
                    if (tfaInp) tfaInp.value = '';
                    self.inlineLoginTwoFactorRequired = false;
                    self._applyLiveAccountPayload(body);

                    if (self.sanctumGateState.canEnter) {
                        self.sanctumExpandedStep = '';
                        self._renderSanctumGateModal();
                        setTimeout(function () {
                            self._setSanctumGateOpen(false);
                        }, 440);
                    } else if (!self.sanctumGateState.checks.order) {
                        self.sanctumExpandedStep = '';
                        self._renderSanctumGateModal();
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
                    self._applyLiveAccountPayload(body);
                    self.sanctumExpandedStep = '';
                    self._renderSanctumGateModal();
                    setTimeout(function () {
                        self._setSanctumGateOpen(false);
                    }, 480);
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

    SeelenfunkeUnifiedLobbyHost.prototype._setMasterKeyModalOpen = function (flag) {
        this.masterKeyModalOpen = Boolean(flag);
        var facade = getFacade(this);
        if (this.masterKeyModalOpen && facade && typeof facade.releasePointerLock === 'function') {
            facade.releasePointerLock();
        }
        if (this.modalEl) {
            var modal = this.modalEl.querySelector('[data-lobby-master-key-modal]');
            if (modal) {
                modal.style.display = this.masterKeyModalOpen ? 'flex' : 'none';
            }
            var vLink = this.modalEl.querySelector('[data-master-key-voucher]');
            if (vLink && this.playerHud && this.playerHud.giftVoucherUrl) {
                vLink.href = this.playerHud.giftVoucherUrl;
            }
        }
    };

    SeelenfunkeUnifiedLobbyHost.prototype._renderSanctumGateModal = function () {
        if (!this.modalEl) return;
        var root = this.modalEl;
        var iconEl = root.querySelector('[data-sanctum-icon]');
        var titleEl = root.querySelector('[data-sanctum-title]');
        if (iconEl) iconEl.textContent = this.sanctumGateZone === 'lava' ? '🌋' : '🏛️';
        if (titleEl) titleEl.textContent = this.sanctumGateZone === 'lava' ? 'Magma-Welt' : 'Halle der Helden';

        var st = this.sanctumGateState || { checks: { login: false, order: false, magic: false }, loginUrl: '/login', dashboardUrl: '/dashboard' };
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
        syncRow('order', Boolean(checks.order));
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
            var showMagic = (Boolean(checks.login) && Boolean(checks.order) && !checks.magic && this.sanctumExpandedStep === 'magic');
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
            loginBtn.textContent = 'Anmelden →';
            loginBtn.addEventListener('click', function () {
                self.sanctumExpandedStep = 'login';
                self.inlineAuthError = '';
                self._renderSanctumGateModal();
            });
            ctaWrap.appendChild(loginBtn);
        } else if (checks.login && !checks.order) {
            var shopBtn = document.createElement('button');
            shopBtn.type = 'button';
            shopBtn.setAttribute('data-testid', 'sanctum-gate-cta-btn');
            shopBtn.className = 'sf-lobby-prod-cta';
            shopBtn.style.cssText = 'margin-top:2px;border:none;cursor:pointer;';
            shopBtn.textContent = 'Kollektion ansehen';
            shopBtn.addEventListener('click', function () {
                self._focusNearestVitrineFromModal();
            });
            ctaWrap.appendChild(shopBtn);
        } else if (checks.login && checks.order && !checks.magic && this.sanctumExpandedStep !== 'magic') {
            var magicBtn = document.createElement('button');
            magicBtn.type = 'button';
            magicBtn.setAttribute('data-testid', 'sanctum-gate-cta-btn');
            magicBtn.className = 'sf-lobby-prod-cta';
            magicBtn.style.cssText = 'margin-top:2px;border:none;cursor:pointer;';
            magicBtn.textContent = '✨ Magie aktivieren →';
            magicBtn.addEventListener('click', function () {
                self.sanctumExpandedStep = 'magic';
                self.inlineAuthError = '';
                self._renderSanctumGateModal();
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

    SeelenfunkeUnifiedLobbyHost.prototype._renderLavaChestCard = function () {
        if (!this.modalEl) return;
        var inner = this.modalEl.querySelector('[data-lobby-lava-chest-inner]');
        if (!inner) return;
        while (inner.firstChild) inner.removeChild(inner.firstChild);

        var self = this;
        var st = this.lavaChestState || {};

        if (!this.lobbyAuth) {
            var loginBtn = document.createElement('button');
            loginBtn.type = 'button';
            loginBtn.className = 'sf-lobby-lava-chest-card';
            loginBtn.textContent = '🔒 Anmelden →';
            loginBtn.addEventListener('click', function () {
                self._setSanctumGateOpen(true, 'lava');
            });
            inner.appendChild(loginBtn);
            return;
        }

        if (st.claimedToday) {
            var donePill = document.createElement('div');
            donePill.className = 'sf-lobby-lava-chest-card';
            donePill.textContent = sanitizeText(st.statusMessage || 'Heute bereits geöffnet ✓');
            inner.appendChild(donePill);
            return;
        }

        var rewardVal = st.rewardPreview || 5;
        var titleBadge = document.createElement('span');
        titleBadge.style.cssText = 'pointer-events:none;padding:4px 11px;border-radius:999px;background:rgba(255,255,255,0.96);border:1px solid rgba(197,160,89,0.55);font-size:11px;font-weight:800;color:#111827;';
        titleBadge.textContent = 'Magma-Truhe · +' + rewardVal + ' ✨ Funken';
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

    SeelenfunkeUnifiedLobbyHost.prototype._renderMinimap = function () {
        var canvas = this.minimapCanvasEl;
        var facade = getFacade(this);
        if (!canvas || !facade || typeof facade.getMinimapState !== 'function') return;
        var ctx = canvas.getContext('2d');
        if (!ctx) return;
        var snap = facade.getMinimapState();
        if (!snap) return;

        var w = canvas.width || 180;
        var h = canvas.height || 180;
        var sc = w / 180;
        var cx = w * 0.5;
        var cy = h * 0.5;
        var rad = w * 0.5 - 8 * sc;

        ctx.clearRect(0, 0, w, h);
        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, cy, rad + 5 * sc, 0, Math.PI * 2);
        ctx.clip();

        ctx.fillStyle = snap.zone === 'lava' ? '#FBF5EC' : '#FAF8F5';
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

        setSlotUi(1, Boolean(st.combo && st.combo.active), st.combo ? st.combo.cooldown : 0);
        setSlotUi(2, Boolean(st.sprint && st.sprint.active), st.sprint ? st.sprint.cooldown : 0);
        setSlotUi(3, false, st.titanJump ? st.titanJump.cooldown : 0);
        setSlotUi(4, Boolean(st.shield && st.shield.active), st.shield ? st.shield.cooldown : 0);
        setSlotUi(5, false, st.burst ? st.burst.cooldown : 0);

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

        if (typeof facade.getPlayerHudState === 'function') {
            var pHud = facade.getPlayerHudState();
            if (pHud) {
                this.playerHud = pHud;
                if (pHud.environment) {
                    this.environmentInfo = pHud.environment;
                }
            }
        }
        var uName = root.querySelector('[data-lobby-unit-name]');
        var uStats = root.querySelector('[data-lobby-unit-stats]');
        var uMana = root.querySelector('[data-lobby-unit-mana]');
        var uManaVal = root.querySelector('[data-lobby-unit-mana-val]');
        var vMana = root.querySelector('[data-lobby-vital-mana]');
        var vManaVal = root.querySelector('[data-lobby-vital-mana-val]');
        var demoteEl = root.querySelector('[data-lobby-demote-banner]');
        if (uName && this.playerHud) uName.textContent = sanitizeText(this.playerHud.name || 'Funki');
        if (uStats && this.playerHud) uStats.textContent = 'Stufe ' + (this.playerHud.level || 2) + ' · ✨ ' + (this.playerHud.funkenBalance || 0);
        if (this.playerHud) {
            var curMana = (typeof this.playerHud.mana === 'number') ? Math.round(this.playerHud.mana) : 100;
            var maxMana = Math.max(1, this.playerHud.maxMana || 100);
            var pct = Math.max(0, Math.min(100, Math.round((curMana / maxMana) * 100)));
            if (uMana) uMana.style.width = pct + '%';
            if (uManaVal) uManaVal.textContent = String(curMana);
            if (vMana) vMana.style.width = pct + '%';
            if (vManaVal) vManaVal.textContent = curMana + '/' + maxMana;

            if (this.playerHud.lastHeartLostSeq && this.playerHud.lastHeartLostSeq !== this._lastSeenHeartLostSeq) {
                this._lastSeenHeartLostSeq = this.playerHud.lastHeartLostSeq;
                this._heartShatterIdx = typeof this.playerHud.lastHeartLostIndex === 'number' ? this.playerHud.lastHeartLostIndex : -1;
                var selfRef = this;
                if (this._heartShatterTimer) clearTimeout(this._heartShatterTimer);
                this._heartShatterTimer = setTimeout(function () {
                    selfRef._heartShatterIdx = -1;
                }, 560);
            }

            var curHearts = (typeof this.playerHud.hearts === 'number') ? clampInt(this.playerHud.hearts, 0, 6) : 6;
            for (var hi = 0; hi < 6; hi++) {
                var hSlot = root.querySelector('[data-heart-slot="' + hi + '"]');
                if (hSlot) {
                    var filled = (hi < curHearts);
                    if (hSlot.getAttribute('data-filled') !== (filled ? '1' : '0')) {
                        hSlot.setAttribute('data-filled', filled ? '1' : '0');
                        hSlot.innerHTML = (this._mod && typeof this._mod.getLobbyHeartSvgMarkup === 'function')
                            ? this._mod.getLobbyHeartSvgMarkup(filled)
                            : buildRubyHeartSvg(filled);
                    }
                    if (this._heartShatterIdx === hi) hSlot.classList.add('is-shattering');
                    else hSlot.classList.remove('is-shattering');
                }
            }

            if (demoteEl) {
                if (this.playerHud.demoteBannerText) {
                    demoteEl.style.display = 'block';
                    demoteEl.textContent = sanitizeText(this.playerHud.demoteBannerText);
                } else {
                    demoteEl.style.display = 'none';
                }
            }
        }
        var mTime = root.querySelector('[data-lobby-minimap-time]');
        if (mTime && this.environmentInfo) {
            mTime.textContent = this.environmentInfo.server_time || '12:00';
            mTime.title = this.environmentInfo.holiday_label
                ? (this.environmentInfo.season_label + ' · ' + this.environmentInfo.holiday_label)
                : (this.environmentInfo.season_label || 'Herbst');
        }
        this._renderMinimap();
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

        // Punkt 11: Rechtsklick-Kontextmenue in allen Sprechblasen, Modals & der gesamten 3D-Lobby unterbinden
        root.addEventListener('contextmenu', function (ev) {
            ev.preventDefault();
        });

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
        var rowOrder = root.querySelector('[data-sanctum-row="order"]');
        if (rowOrder) {
            rowOrder.addEventListener('click', function () {
                if (!(self.sanctumGateState.checks && self.sanctumGateState.checks.order)) {
                    self._focusNearestVitrineFromModal();
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
                } else if (!ch.order) {
                    self._focusNearestVitrineFromModal();
                } else if (!ch.magic) {
                    self.sanctumExpandedStep = (self.sanctumExpandedStep === 'magic') ? '' : 'magic';
                    self.inlineAuthError = '';
                    self._renderSanctumGateModal();
                }
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

        var mkClose = root.querySelector('[data-master-key-close]');
        if (mkClose) {
            mkClose.addEventListener('click', function () {
                self._setMasterKeyModalOpen(false);
            });
        }
        var mkModal = root.querySelector('[data-lobby-master-key-modal]');
        if (mkModal) {
            mkModal.addEventListener('click', function (ev) {
                if (ev.target === ev.currentTarget) {
                    self._setMasterKeyModalOpen(false);
                }
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

        var zoneBadge = root.querySelector('[data-lobby-current-zone-badge]');
        if (zoneBadge) {
            zoneBadge.textContent = this.currentZone === 'heroes'
                ? '🏛️ Halle der Helden'
                : (this.currentZone === 'lava' ? '🌋 Magma-Welt' : '🛍️ Kollektion');
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

        var keyMapEls = root.querySelectorAll('[data-ability-key]');
        for (var km = 0; km < keyMapEls.length; km++) {
            var actName = keyMapEls[km].getAttribute('data-ability-key');
            keyMapEls[km].textContent = this._keyLabel(actName);
        }
        var ttKeyEls = root.querySelectorAll('[data-ability-tooltip-key]');
        for (var tk = 0; tk < ttKeyEls.length; tk++) {
            var ttAct = ttKeyEls[tk].getAttribute('data-ability-tooltip-key');
            ttKeyEls[tk].textContent = 'Taste ' + this._keyLabel(ttAct);
        }

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

        var btmHeroes = root.querySelector('[data-lobby-bottom-heroes]');
        if (btmHeroes) {
            btmHeroes.style.display = (this.currentZone === 'heroes') ? 'inline-flex' : 'none';
        }
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

        // Zeile 1: Edle Serif-Ueberschrift + Preis-Badge ("ab 29,90 €")
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

        // Zeile 2: Maximal 1 bis 2 kurze Zeilen Kern-Info (line-clamp: 2, 0 Aufzaehlungs-Overload!)
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

        // Edler goldener Hauptbutton + ~680 ms Kamera- & Elfenbein-Gold-Uebergangssequenz
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

    SeelenfunkeUnifiedLobbyHost.prototype._handleZoneSyncTransition = function (nextZone) {
        var z = String(nextZone || 'gallery').toLowerCase();
        var facade = getFacade(this);

        if (z === 'heroes') {
            if (facade && typeof facade.refreshHeroesData === 'function') {
                facade.refreshHeroesData(false);
            }
            if (this.lobbyAuth && this.sanctumGateState.canEnter && !this.soloMode && (this._activeSyncZone !== 'heroes' || !this._syncTimer)) {
                this._activeSyncZone = 'heroes';
                this._joinLobby();
            }
            return;
        }

        // Solo-Zone ('gallery', 'lava'): Peers sofort leeren, Timer stoppen & Heldenhallen-Platz freigeben!
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
            .then(function (r) {
                return r.json().catch(function () { return null; }).then(function (b) {
                    if (r.status === 401 || r.status === 403) {
                        self._stopSyncLoop();
                        var fGate = getFacade(self);
                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                            fGate.handleSanctumHttpResponse(r.status, b, 'heroes');
                        }
                        return null;
                    }
                    return r.ok ? b : null;
                });
            })
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
            .then(function (r) {
                return r.json().catch(function () { return null; }).then(function (b) {
                    if (r.status === 401 || r.status === 403) {
                        self._stopSyncLoop();
                        var fGate = getFacade(self);
                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                            fGate.handleSanctumHttpResponse(r.status, b, 'heroes');
                        }
                        return null;
                    }
                    return r.ok ? b : null;
                });
            })
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
            .then(function (r) {
                return r.json().catch(function () { return null; }).then(function (b) {
                    if (r.status === 401 || r.status === 403) {
                        self._stopSyncLoop();
                        var fGate = getFacade(self);
                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                            fGate.handleSanctumHttpResponse(r.status, b, 'heroes');
                        }
                        return null;
                    }
                    return r.ok ? b : null;
                });
            })
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

    SeelenfunkeUnifiedLobbyHost.prototype._startLobbyMusicFromGestureSync = function () {
        if (this._audio && typeof this._audio.startLobbyMusicFromGesture === 'function') {
            this.audioState = this._audio.startLobbyMusicFromGesture();
            return;
        }
        if (typeof window !== 'undefined' && typeof window.getSeelenfunkeLobbyAudio === 'function') {
            this._audio = window.getSeelenfunkeLobbyAudio({ isAuthenticated: this.lobbyAuth });
            if (this._audio && typeof this._audio.startLobbyMusicFromGesture === 'function') {
                this.audioState = this._audio.startLobbyMusicFromGesture();
                return;
            }
        }
        try {
            var rawExplicit = localStorage.getItem('sf_lobby_bgm_explicit_muted');
            var rawVol = localStorage.getItem('sf_funki_audio_volume');
            var explicitMuted = (rawExplicit === '1' || rawExplicit === 'true') || rawVol === '0';
            sessionStorage.setItem('sf_funki_bgm_session_enabled', '1');
            if (!explicitMuted) {
                localStorage.setItem('sf_funki_audio_muted', '0');
                localStorage.setItem('sf_funki_bgm_muted', '0');
                if (window.SfFunkiBgm) {
                    if ((window.SfFunkiBgm.volume || 0) <= 0) {
                        window.SfFunkiBgm.setVolume(50, false);
                    }
                    window.SfFunkiBgm.setMuted(false, false);
                    window.SfFunkiBgm.onModalOpen();
                } else if (typeof Audio !== 'undefined') {
                    var preEl = window.__sfPrewarmLobbyBgmEl || new Audio('/shop/gamification/audio/bgm_funki_game.mp3');
                    preEl.loop = true;
                    preEl.preload = 'auto';
                    preEl.volume = 0.25;
                    window.__sfPrewarmLobbyBgmEl = preEl;
                    var p = preEl.play();
                    if (p && typeof p.catch === 'function') p.catch(function () {});
                }
            }
        } catch (e) {}
    };

    SeelenfunkeUnifiedLobbyHost.prototype.open = function (initialMode) {
        var self = this;
        var mode = String(initialMode || 'lobby').toLowerCase();
        var startZone = (mode === 'lava')
            ? 'lava'
            : ((mode === 'hall' || mode === 'heroes' || mode === 'my_place') ? mode : 'gallery');

        // User-Gesture-Token zu 100 % synchron im Klick-Event sichern (VOR fetch / import)!
        this._startLobbyMusicFromGestureSync();

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
        this.masterKeyModalOpen = false;
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
        this._setMasterKeyModalOpen(false);

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

                var rawPlayer = (shopJson && shopJson.player && typeof shopJson.player === 'object') ? shopJson.player : null;
                if (shopJson && shopJson.environment && typeof shopJson.environment === 'object') {
                    self.environmentInfo = shopJson.environment;
                }

                var isAuth = Boolean(
                    (rawPlayer && rawPlayer.is_authenticated)
                    || (self.funkiInfo && !self.funkiInfo.is_guest)
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
                    self.audioState = self._audio.startLobbyMusicFromGesture();
                    if (self._audioUnsub) self._audioUnsub();
                    self._audioUnsub = self._audio.onChange(function (snap) {
                        self.audioState = snap;
                        self._syncHudState();
                    });
                }

                var lavaInfo = (shopJson && shopJson.lava_parkour) ? shopJson.lava_parkour : null;
                self.lavaChestState = {
                    level: 1,
                    rewardPreview: 5,
                    nearChest: false,
                    isAuthenticated: isAuth,
                    claimedToday: Boolean(lavaInfo && lavaInfo.claimed_today),
                    claiming: false,
                    canClaim: Boolean(isAuth && !(lavaInfo && lavaInfo.claimed_today)),
                    statusMessage: ''
                };
                self._renderLavaChestCard();
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
                    floatingOuterGateEl: self.floatingOuterGateWrap,
                    products: self.products,
                    environment: shopJson ? shopJson.environment : null,
                    player: rawPlayer,
                    heroesPayload: self.heroesPayload,
                    playerFunki: self.funkiInfo,
                    lavaParkour: lavaInfo,
                    audio: self._audio || undefined,
                    isAuthenticatedCustomer: isAuth,
                    initialZone: startZone === 'my_place' ? 'heroes' : startZone,
                    onFocusProductChange: function (prod, idx) {
                        self.focusedProduct = prod || null;
                        if (typeof idx === 'number' && idx >= 0) {
                            self.activeIndex = idx;
                        }
                        self._renderFloatingProductCard(self.focusedProduct);
                        self._syncAbilityHud();
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
                    },
                    onAuthGatePrompt: function (zone, accessState) {
                        self._setSanctumGateOpen(true, zone, accessState);
                    },
                    onMasterKeyPromptChange: function (flag) {
                        if (flag) {
                            self._setMasterKeyModalOpen(true);
                        }
                    },
                    onPlayerHudChange: function (hud) {
                        if (hud) {
                            self.playerHud = hud;
                            if (hud.environment) {
                                self.environmentInfo = hud.environment;
                            }
                        }
                    },
                    onLavaChestStateChange: function (state) {
                        self.lavaChestState = state;
                        self._renderLavaChestCard();
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
                self._syncAbilityHud();
                if (isAuth && self.sanctumGateState.canEnter && self.currentZone === 'heroes') {
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
        this.sanctumGateOpen = false;
        this.masterKeyModalOpen = false;
        this.listeningKeyAction = '';
        this.peers = [];

        if (this._productTransitionTimer) {
            clearTimeout(this._productTransitionTimer);
            this._productTransitionTimer = null;
        }
        this._productTransitioning = false;
        if (this.modalEl) {
            var ivoryEl = this.modalEl.querySelector('[data-lobby-ivory-transition]');
            if (ivoryEl) ivoryEl.classList.remove('is-fading');
        }

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

        if (this._audio && typeof this._audio.setLobbyActive === 'function') {
            this._audio.setLobbyActive(false);
        }
        if (typeof window !== 'undefined' && window.__sfPrewarmLobbyBgmEl) {
            try {
                window.__sfPrewarmLobbyBgmEl.pause();
                window.__sfPrewarmLobbyBgmEl.volume = 0;
            } catch (e) {}
        }
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
