<style>
    #threejs-match3-container canvas,
    #funkenflug-container canvas {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10;
        display: block; width: 100% !important; height: 100% !important; outline: none;
        touch-action: none; /* WICHTIG FÜR MOBILE DRAG AND DROP */
        -webkit-user-select: none; user-select: none;
        transform: translateZ(0);
        will-change: transform;
    }
    .ff-playing-cursor-none,
    .ff-playing-cursor-none canvas {
        cursor: none !important;
    }
    .floating-score {
        position: absolute; font-family: ui-sans-serif, system-ui, sans-serif; font-weight: 900; font-size: 1.5rem;
        pointer-events: none; z-index: 100; transform: translate(-50%, -50%);
        animation: floatUp 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; text-shadow: 0px 4px 10px rgba(0,0,0,0.9), 0px 0px 8px currentColor;
    }
    .floating-bonus {
        font-size: 2rem; color: #f472b6; animation: floatUpBonus 1.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @media (min-width: 640px) {
        .floating-score { font-size: 2.5rem; }
        .floating-bonus { font-size: 3.5rem; }
    }
    @keyframes floatUp { 0% { transform: translate(-50%, -50%) scale(0.5); opacity: 1; } 20% { transform: translate(-50%, -100%) scale(1.5); opacity: 1; } 100% { transform: translate(-50%, -200%) scale(1); opacity: 0; } }
    @keyframes floatUpBonus { 0% { transform: translate(-50%, -50%) scale(0.5) rotate(-10deg); opacity: 1; } 30% { transform: translate(-50%, -150%) scale(1.5) rotate(5deg); opacity: 1; } 100% { transform: translate(-50%, -300%) scale(1.2) rotate(0deg); opacity: 0; } }

    input[type=range].volume-slider { -webkit-appearance: none; width: 100%; background: transparent; }
    input[type=range].volume-slider::-webkit-slider-thumb { -webkit-appearance: none; height: 16px; width: 16px; border-radius: 50%; background: #10b981; cursor: pointer; margin-top: -6px; box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }
    input[type=range].volume-slider::-webkit-slider-runnable-track { width: 100%; height: 4px; cursor: pointer; background: #374151; border-radius: 2px; }

    /* ======================================================================
       SPIELER-COCKPIT 60 FPS FOCUS-MODE & LOADER STYLES (ADMIN DASHBOARD DNA)
       ====================================================================== */
    @keyframes sf-cockpit-energy-flow {
        from { stroke-dashoffset: 260; }
        to   { stroke-dashoffset: 0; }
    }
    .sf-energy-stream {
        stroke-dasharray: 28 232;
        animation: sf-cockpit-energy-flow 2.8s linear infinite;
        will-change: stroke-dashoffset;
    }
    @keyframes sf-cockpit-float {
        0%, 100% { transform: translate3d(0, 0px, 0); }
        50%      { transform: translate3d(0, -4px, 0); }
    }
    .sf-floating-satellite {
        animation: sf-cockpit-float 5.2s ease-in-out infinite;
        will-change: transform;
    }
    .sf-cockpit-stage-hidden {
        opacity: 0;
        transform: translate3d(0, 10px, 0) scale(0.985);
        pointer-events: none;
    }
    .sf-cockpit-stage-ready {
        opacity: 1;
        transform: translate3d(0, 0, 0) scale(1);
        transition: opacity 0.55s cubic-bezier(0.16, 1, 0.3, 1), transform 0.55s cubic-bezier(0.16, 1, 0.3, 1);
        will-change: opacity, transform;
    }

    /* ======================================================================
       EPISCHE RUHMES-AUREN ANIMATIONEN (SHOWROOM & HALLE DER LEGENDEN)
       ====================================================================== */
    @keyframes sf-aura-shimmer-sweep {
        0%   { background-position: 200% 50%; }
        100% { background-position: -200% 50%; }
    }
    @keyframes sf-aura-gold-pulse {
        0%, 100% { box-shadow: 0 0 18px rgba(245, 158, 11, 0.22), inset 0 0 12px rgba(245, 158, 11, 0.08); }
        50%      { box-shadow: 0 0 28px rgba(251, 191, 36, 0.42), inset 0 0 20px rgba(245, 158, 11, 0.16); }
    }
    @keyframes sf-aura-crystal-pulse {
        0%, 100% { box-shadow: 0 0 20px rgba(56, 189, 248, 0.25), inset 0 0 14px rgba(56, 189, 248, 0.1); }
        50%      { box-shadow: 0 0 32px rgba(103, 232, 249, 0.48), inset 0 0 22px rgba(56, 189, 248, 0.2); }
    }
    @keyframes sf-aura-phoenix-flame {
        0%, 100% { box-shadow: 0 0 24px rgba(249, 115, 22, 0.3), 0 0 10px rgba(244, 63, 94, 0.25), inset 0 0 16px rgba(249, 115, 22, 0.12); }
        50%      { box-shadow: 0 0 38px rgba(251, 146, 60, 0.55), 0 0 18px rgba(244, 63, 94, 0.45), inset 0 0 26px rgba(245, 158, 11, 0.24); }
    }
    @keyframes sf-aura-supernova-cosmic {
        0%, 100% { box-shadow: 0 0 26px rgba(168, 85, 247, 0.35), 0 0 12px rgba(232, 121, 249, 0.28), inset 0 0 18px rgba(168, 85, 247, 0.15); }
        50%      { box-shadow: 0 0 42px rgba(217, 70, 239, 0.6), 0 0 22px rgba(99, 102, 241, 0.45), inset 0 0 28px rgba(232, 121, 249, 0.28); }
    }
    @keyframes sf-aura-vip-royal-majesty {
        0%, 100% {
            box-shadow: 0 0 28px rgba(245, 158, 11, 0.45), 0 0 52px rgba(16, 185, 129, 0.25), inset 0 0 22px rgba(251, 191, 36, 0.22);
            border-color: rgba(251, 191, 36, 0.75);
        }
        50% {
            box-shadow: 0 0 45px rgba(253, 224, 71, 0.72), 0 0 70px rgba(16, 185, 129, 0.45), inset 0 0 34px rgba(16, 185, 129, 0.28);
            border-color: rgba(110, 231, 183, 0.9);
        }
    }
    @keyframes sf-vip-OrbitSparkle {
        0%   { transform: translateX(-120%) skewX(-22deg); opacity: 0; }
        20%  { opacity: 0.85; }
        55%  { transform: translateX(220%) skewX(-22deg); opacity: 0; }
        100% { transform: translateX(220%) skewX(-22deg); opacity: 0; }
    }

    .sf-aura-row-gold {
        animation: sf-aura-gold-pulse 3.6s ease-in-out infinite;
    }
    .sf-aura-row-crystal {
        animation: sf-aura-crystal-pulse 3.2s ease-in-out infinite;
    }
    .sf-aura-row-phoenix {
        animation: sf-aura-phoenix-flame 2.6s ease-in-out infinite;
    }
    .sf-aura-row-supernova {
        animation: sf-aura-supernova-cosmic 2.8s ease-in-out infinite;
    }
    .sf-aura-row-vip-royal {
        position: relative;
        overflow: hidden;
        background-image: linear-gradient(115deg, rgba(245,158,11,0.22) 0%, rgba(16,185,129,0.18) 35%, rgba(253,224,71,0.32) 50%, rgba(16,185,129,0.18) 65%, rgba(245,158,11,0.22) 100%) !important;
        background-size: 250% 100% !important;
        animation: sf-aura-vip-royal-majesty 2.8s ease-in-out infinite, sf-aura-shimmer-sweep 5.5s linear infinite;
    }
    .sf-aura-row-vip-royal::after {
        content: "";
        position: absolute;
        top: 0; bottom: 0; left: 0;
        width: 38%;
        background: linear-gradient(90deg, transparent, rgba(255, 251, 235, 0.35), rgba(167, 243, 208, 0.28), transparent);
        pointer-events: none;
        animation: sf-vip-OrbitSparkle 4.2s ease-in-out infinite;
    }
    .sf-aura-badge-vip-royal {
        background-size: 200% 100%;
        animation: sf-aura-shimmer-sweep 3.5s linear infinite;
        box-shadow: 0 0 18px rgba(245, 158, 11, 0.55), 0 0 8px rgba(16, 185, 129, 0.45);
    }
</style>
