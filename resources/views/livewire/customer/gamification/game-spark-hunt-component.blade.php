<div>
    @if($isVisible)
        <div x-data="{
                show: true,
                clicked: false,
                hovered: false,
                tiltX: 0,
                tiltY: 0,
                shineX: 50,
                shineY: 50,
                handleMove(e) {
                    const rect = e.currentTarget.getBoundingClientRect();
                    const relX = (e.clientX - rect.left) / rect.width;
                    const relY = (e.clientY - rect.top) / rect.height;
                    this.shineX = Math.round(relX * 100);
                    this.shineY = Math.round(relY * 100);
                    this.tiltX = ((0.5 - relY) * 22).toFixed(2);
                    this.tiltY = ((relX - 0.5) * 22).toFixed(2);
                },
                resetTilt() {
                    this.hovered = false;
                    this.tiltX = 0;
                    this.tiltY = 0;
                    this.shineX = 50;
                    this.shineY = 50;
                },
                playSweetSparkSound() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        if (!window._sfSparkAudioCtx) {
                            window._sfSparkAudioCtx = new AudioCtx();
                        }
                        const ctx = window._sfSparkAudioCtx;
                        if (ctx.state === 'suspended') {
                            ctx.resume().catch(() => {});
                        }
                        const now = ctx.currentTime;
                        // Süßer, sanfter Spieluhr-/Celesta-Klang (G-Dur Glöckchen-Arpeggio: G5 - B5 - D6 - G6)
                        const notes = [783.99, 987.77, 1174.66, 1567.98];
                        notes.forEach((freq, idx) => {
                            const start = now + idx * 0.065;
                            const osc = ctx.createOscillator();
                            const overtone = ctx.createOscillator();
                            const gain = ctx.createGain();
                            const overtoneGain = ctx.createGain();

                            osc.type = 'sine';
                            overtone.type = 'triangle';
                            osc.frequency.setValueAtTime(freq, start);
                            overtone.frequency.setValueAtTime(freq * 2, start);

                            gain.gain.setValueAtTime(0.0001, start);
                            gain.gain.linearRampToValueAtTime(0.075, start + 0.018);
                            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.68);

                            overtoneGain.gain.setValueAtTime(0.0001, start);
                            overtoneGain.gain.linearRampToValueAtTime(0.022, start + 0.012);
                            overtoneGain.gain.exponentialRampToValueAtTime(0.0001, start + 0.35);

                            osc.connect(gain);
                            overtone.connect(overtoneGain);
                            gain.connect(ctx.destination);
                            overtoneGain.connect(ctx.destination);

                            osc.start(start);
                            overtone.start(start);
                            osc.stop(start + 0.72);
                            overtone.stop(start + 0.38);
                        });
                    } catch (e) {}
                },
                collect() {
                    if (this.clicked) return;
                    this.clicked = true;
                    this.playSweetSparkSound();
                    setTimeout(() => {
                        $wire.collectSpark();
                        this.show = false;
                    }, 620);
                }
             }"
             x-show="show"
             x-transition.opacity.duration.500ms
             class="spark-hunt-anchor"
             style="position: absolute; top: {{ $posTop }}%; left: {{ $posLeft }}%; z-index: 950; pointer-events: auto;">

            <button type="button"
                    @mouseenter="hovered = true"
                    @mousemove="handleMove($event)"
                    @mouseleave="resetTilt()"
                    @click.stop.prevent="collect()"
                    class="spark-hunt-btn"
                    :class="{ 'is-hovered': hovered, 'is-clicked': clicked }"
                    :style="`--shine-x: ${shineX}%; --shine-y: ${shineY}%; --tilt-x: ${tiltX}deg; --tilt-y: ${tiltY}deg;`"
                    aria-label="Geheimen Seelenfunken einsammeln">

                {{-- HOVER: Schwebendes Goldenes Holo-Badge (HUD) --}}
                <div class="spark-holo-tooltip" aria-hidden="true">
                    <div class="spark-holo-tooltip-inner">
                        <span class="spark-holo-tooltip-icon">✦</span>
                        <span class="spark-holo-tooltip-title">Seelenfunke</span>
                        <span class="spark-holo-tooltip-plus">+1 ✨</span>
                    </div>
                </div>

                {{-- IDLE & HOVER: Schwebender Goldener Seelenfunke mit Holo-Prisma --}}
                <div x-show="!clicked" class="spark-hunt-stage">
                    {{-- Weiche goldene Grund-Aura --}}
                    <div class="spark-ambient-glow"></div>

                    {{-- HOVER: Goldenes Hologramm-Prisma (Irisierender Gold-Opal Foil-Effekt) --}}
                    <div class="spark-holo-disc">
                        <div class="spark-holo-foil"></div>
                        <div class="spark-holo-scanlines"></div>
                        <div class="spark-holo-glare"></div>
                    </div>

                    {{-- Äußerer & innerer rotierender Gold-Holo-Lichtkranz --}}
                    <div class="spark-orbit-ring spark-orbit-ring-outer"></div>
                    <div class="spark-orbit-ring spark-orbit-ring-inner"></div>

                    {{-- Goldener Energie-Kern --}}
                    <div class="spark-core-glow"></div>

                    {{-- 3D Gold-Kristall-Funke SVG --}}
                    <svg class="spark-star-svg" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="sparkGoldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#FFFBEB" />
                                <stop offset="35%" stop-color="#FDE047" />
                                <stop offset="70%" stop-color="#F59E0B" />
                                <stop offset="100%" stop-color="#D97706" />
                            </linearGradient>
                            <linearGradient id="sparkHoloFacet" x1="0%" y1="100%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#FEF08A" stop-opacity="0.95" />
                                <stop offset="45%" stop-color="#67E8F9" stop-opacity="0.75" />
                                <stop offset="75%" stop-color="#F472B6" stop-opacity="0.65" />
                                <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0.95" />
                            </linearGradient>
                        </defs>
                        {{-- 4-Strahlen Hauptstern --}}
                        <path d="M16 1.5L19.6 12.4L30.5 16L19.6 19.6L16 30.5L12.4 19.6L1.5 16L12.4 12.4L16 1.5Z"
                              fill="url(#sparkGoldGrad)"
                              stroke="#FEF9C3"
                              stroke-width="0.75" />
                        {{-- Diagonale Holo-Strahlen (erscheinen verstärkt beim Hover) --}}
                        <path class="spark-diagonal-rays"
                              d="M7 7L13.8 13.8M25 7L18.2 13.8M25 25L18.2 18.2M7 25L13.8 18.2"
                              stroke="url(#sparkHoloFacet)"
                              stroke-width="1.4"
                              stroke-linecap="round" />
                        {{-- Inneres Facetten-Prisma --}}
                        <path class="spark-inner-facet"
                              d="M16 6.5L18.1 13.9L25.5 16L18.1 18.1L16 25.5L13.9 18.1L6.5 16L13.9 13.9L16 6.5Z"
                              fill="url(#sparkHoloFacet)"
                              opacity="0.65" />
                        {{-- Diamant-Zentrum --}}
                        <circle cx="16" cy="16" r="2.3" fill="#FFFFFF" />
                    </svg>

                    {{-- Schwebende Begleit-Partikel --}}
                    <span class="spark-micro-dot spark-micro-dot-1"></span>
                    <span class="spark-micro-dot spark-micro-dot-2"></span>
                    <span class="spark-micro-dot spark-micro-dot-3"></span>
                </div>

                {{-- KLICK-ANIMATION: Goldene Holo-Supernova --}}
                <div x-show="clicked" x-cloak class="spark-burst-stage">
                    <div class="spark-burst-wave"></div>
                    <div class="spark-burst-ring"></div>
                    <span class="spark-burst-label">+1 ✨ Funke!</span>
                </div>
            </button>
        </div>

        <style>
            .spark-hunt-anchor {
                user-select: none;
                -webkit-user-select: none;
            }

            .spark-hunt-btn {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 68px;
                height: 68px;
                padding: 0;
                border: none;
                background: transparent;
                cursor: pointer;
                pointer-events: auto;
                outline: none;
                perspective: 600px;
                -webkit-tap-highlight-color: transparent;
            }

            .spark-hunt-stage {
                position: relative;
                width: 58px;
                height: 58px;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: sparkLevitate 3.2s ease-in-out infinite;
                transform-style: preserve-3d;
                transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), filter 0.3s ease;
            }

            .spark-hunt-btn.is-hovered .spark-hunt-stage {
                animation-play-state: paused;
                transform: rotateX(var(--tilt-x, 0deg)) rotateY(var(--tilt-y, 0deg)) scale(1.28);
            }

            /* Grund-Aura */
            .spark-ambient-glow {
                position: absolute;
                inset: 4px;
                border-radius: 9999px;
                background: radial-gradient(circle, rgba(251, 191, 36, 0.55) 0%, rgba(245, 158, 11, 0.22) 55%, transparent 78%);
                filter: blur(8px);
                animation: sparkPulseGlow 2.4s ease-in-out infinite;
                transition: all 0.35s ease;
            }

            .spark-hunt-btn.is-hovered .spark-ambient-glow {
                inset: -10px;
                background: radial-gradient(circle, rgba(253, 224, 71, 0.85) 0%, rgba(245, 158, 11, 0.45) 45%, rgba(56, 189, 248, 0.25) 70%, transparent 88%);
                filter: blur(12px);
            }

            /* GOLDEN HOLO DISC (Aktiviert sich beim Hover) */
            .spark-holo-disc {
                position: absolute;
                inset: -4px;
                border-radius: 9999px;
                overflow: hidden;
                opacity: 0;
                transform: scale(0.72);
                transition: opacity 0.32s cubic-bezier(0.16, 1, 0.3, 1), transform 0.38s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;
                box-shadow:
                    0 0 24px rgba(251, 191, 36, 0.75),
                    inset 0 0 14px rgba(254, 240, 138, 0.75);
                border: 1.5px solid rgba(254, 240, 138, 0.85);
                pointer-events: none;
            }

            .spark-hunt-btn.is-hovered .spark-holo-disc {
                opacity: 1;
                transform: scale(1.06);
            }

            /* Irisierende Gold-Holo-Folie (Regenbogen-Gold-Schimmer) */
            .spark-holo-foil {
                position: absolute;
                inset: -35%;
                background: conic-gradient(
                    from 0deg at 50% 50%,
                    rgba(245, 158, 11, 0.85) 0deg,
                    rgba(254, 240, 138, 0.92) 55deg,
                    rgba(103, 232, 249, 0.72) 110deg,
                    rgba(251, 191, 36, 0.9) 165deg,
                    rgba(244, 114, 182, 0.68) 215deg,
                    rgba(253, 224, 71, 0.95) 275deg,
                    rgba(167, 243, 208, 0.72) 320deg,
                    rgba(245, 158, 11, 0.85) 360deg
                );
                mix-blend-mode: screen;
                animation: sparkHoloSpin 3.6s linear infinite;
                filter: saturate(1.35) contrast(1.1);
            }

            /* Feine Hologramm-Scanlines + Schimmer-Streifen */
            .spark-holo-scanlines {
                position: absolute;
                inset: 0;
                background-image:
                    repeating-linear-gradient(
                        135deg,
                        rgba(255, 255, 255, 0) 0px,
                        rgba(255, 255, 255, 0) 3px,
                        rgba(254, 249, 195, 0.28) 4px,
                        rgba(255, 255, 255, 0) 6px
                    ),
                    repeating-linear-gradient(
                        0deg,
                        rgba(245, 158, 11, 0.12) 0px,
                        transparent 2px,
                        transparent 4px
                    );
                mix-blend-mode: overlay;
                animation: sparkScanShift 2.2s linear infinite;
            }

            /* Dynamischer Maus-Lichtreflex (Glare) */
            .spark-holo-glare {
                position: absolute;
                inset: 0;
                background: radial-gradient(
                    circle at var(--shine-x, 50%) var(--shine-y, 50%),
                    rgba(255, 255, 255, 0.92) 0%,
                    rgba(254, 240, 138, 0.55) 28%,
                    rgba(245, 158, 11, 0.15) 58%,
                    transparent 78%
                );
                mix-blend-mode: hard-light;
            }

            /* Orbit-Ringe */
            .spark-orbit-ring {
                position: absolute;
                border-radius: 9999px;
                pointer-events: none;
                transition: all 0.35s ease;
            }

            .spark-orbit-ring-outer {
                inset: 5px;
                border: 1.5px solid rgba(251, 191, 36, 0.55);
                box-shadow: 0 0 12px rgba(245, 158, 11, 0.55);
                animation: sparkHoloSpin 7s linear infinite;
            }

            .spark-orbit-ring-inner {
                inset: 0px;
                border: 1.5px dashed rgba(254, 240, 138, 0);
                opacity: 0;
                transform: scale(0.8);
                animation: sparkHoloSpinReverse 4.5s linear infinite;
            }

            .spark-hunt-btn.is-hovered .spark-orbit-ring-outer {
                inset: -9px;
                border-color: rgba(254, 240, 138, 0.95);
                box-shadow:
                    0 0 18px rgba(251, 191, 36, 0.95),
                    0 0 32px rgba(56, 189, 248, 0.45);
            }

            .spark-hunt-btn.is-hovered .spark-orbit-ring-inner {
                inset: -15px;
                border-color: rgba(253, 224, 71, 0.78);
                opacity: 1;
                transform: scale(1);
            }

            /* Goldener Kern */
            .spark-core-glow {
                position: absolute;
                inset: 14px;
                border-radius: 9999px;
                background: radial-gradient(circle, #ffffff 0%, #fde047 50%, #f59e0b 100%);
                filter: blur(2px);
                opacity: 0.92;
                transition: transform 0.3s ease, filter 0.3s ease;
            }

            .spark-hunt-btn.is-hovered .spark-core-glow {
                transform: scale(1.18);
                filter: blur(1px);
            }

            /* Haupt-Stern SVG */
            .spark-star-svg {
                position: relative;
                z-index: 10;
                width: 34px;
                height: 34px;
                filter: drop-shadow(0 0 10px rgba(245, 158, 11, 0.95));
                transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), filter 0.35s ease;
            }

            .spark-diagonal-rays {
                opacity: 0.35;
                transition: opacity 0.3s ease;
            }

            .spark-hunt-btn.is-hovered .spark-star-svg {
                transform: rotate(15deg) scale(1.12);
                filter:
                    drop-shadow(0 0 12px rgba(254, 240, 138, 1))
                    drop-shadow(0 0 22px rgba(245, 158, 11, 0.9))
                    drop-shadow(0 0 28px rgba(56, 189, 248, 0.55));
            }

            .spark-hunt-btn.is-hovered .spark-diagonal-rays {
                opacity: 1;
            }

            /* Begleit-Partikel */
            .spark-micro-dot {
                position: absolute;
                border-radius: 9999px;
                pointer-events: none;
            }

            .spark-micro-dot-1 {
                top: 4px;
                right: 4px;
                width: 6px;
                height: 6px;
                background: #fef08a;
                box-shadow: 0 0 8px #fde047;
                animation: sparkTwinkle 1.6s ease-in-out infinite;
            }

            .spark-micro-dot-2 {
                bottom: 5px;
                left: 3px;
                width: 5px;
                height: 5px;
                background: #67e8f9;
                box-shadow: 0 0 8px #38bdf8;
                opacity: 0;
                transition: opacity 0.3s ease, transform 0.3s ease;
            }

            .spark-micro-dot-3 {
                top: 2px;
                left: 6px;
                width: 4px;
                height: 4px;
                background: #f472b6;
                box-shadow: 0 0 8px #ec4899;
                opacity: 0;
                transition: opacity 0.3s ease, transform 0.3s ease;
            }

            .spark-hunt-btn.is-hovered .spark-micro-dot-2,
            .spark-hunt-btn.is-hovered .spark-micro-dot-3 {
                opacity: 0.95;
                transform: scale(1.25);
            }

            /* HOVER BADGE (Goldenes Holo-HUD über dem Funken) */
            .spark-holo-tooltip {
                position: absolute;
                bottom: calc(100% + 8px);
                left: 50%;
                transform: translateX(-50%) translateY(8px) scale(0.88);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.26s cubic-bezier(0.16, 1, 0.3, 1), transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
                z-index: 30;
                white-space: nowrap;
            }

            .spark-hunt-btn.is-hovered .spark-holo-tooltip {
                opacity: 1;
                transform: translateX(-50%) translateY(0) scale(1);
            }

            .spark-holo-tooltip-inner {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 5px 11px;
                border-radius: 9999px;
                background:
                    linear-gradient(135deg, rgba(17, 24, 39, 0.94) 0%, rgba(30, 27, 18, 0.96) 100%) padding-box,
                    linear-gradient(90deg, #fbbf24, #fef08a, #67e8f9, #f472b6, #fbbf24) border-box;
                border: 1.5px solid transparent;
                box-shadow:
                    0 8px 22px rgba(0, 0, 0, 0.35),
                    0 0 18px rgba(251, 191, 36, 0.55);
                backdrop-filter: blur(8px);
            }

            .spark-holo-tooltip-icon {
                color: #fde047;
                font-size: 11px;
                filter: drop-shadow(0 0 6px rgba(253, 224, 71, 0.9));
            }

            .spark-holo-tooltip-title {
                font-size: 10px;
                font-weight: 900;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                background: linear-gradient(90deg, #fef08a 0%, #fbbf24 35%, #67e8f9 70%, #fef08a 100%);
                background-size: 200% auto;
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                animation: sparkTextShimmer 2.4s linear infinite;
            }

            .spark-holo-tooltip-plus {
                font-size: 10px;
                font-weight: 900;
                color: #111827;
                background: linear-gradient(135deg, #fef08a 0%, #f59e0b 100%);
                padding: 1px 6px;
                border-radius: 9999px;
                box-shadow: 0 0 10px rgba(251, 191, 36, 0.7);
            }

            /* KLICK SUPERNOVA */
            .spark-burst-stage {
                position: relative;
                width: 68px;
                height: 68px;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
            }

            .spark-burst-wave {
                position: absolute;
                width: 110px;
                height: 110px;
                border-radius: 9999px;
                background: radial-gradient(circle, rgba(254, 240, 138, 0.9) 0%, rgba(245, 158, 11, 0.65) 45%, rgba(56, 189, 248, 0.3) 75%, transparent 100%);
                filter: blur(10px);
                animation: sparkBurstPing 0.58s cubic-bezier(0, 0, 0.2, 1) forwards;
            }

            .spark-burst-ring {
                position: absolute;
                width: 64px;
                height: 64px;
                border-radius: 9999px;
                border: 2px solid #fef08a;
                box-shadow: 0 0 20px #fbbf24;
                animation: sparkBurstPing 0.48s ease-out forwards;
            }

            .spark-burst-label {
                position: relative;
                z-index: 20;
                white-space: nowrap;
                font-size: 15px;
                font-weight: 900;
                color: #f59e0b;
                text-shadow: 0 2px 12px rgba(251, 191, 36, 0.95), 0 0 4px #ffffff;
                animation: sparkFloatUp 0.62s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            @keyframes sparkLevitate {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-7px) rotate(6deg); }
            }

            @keyframes sparkPulseGlow {
                0%, 100% { opacity: 0.75; transform: scale(0.96); }
                50% { opacity: 1; transform: scale(1.08); }
            }

            @keyframes sparkHoloSpin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            @keyframes sparkHoloSpinReverse {
                0% { transform: rotate(360deg); }
                100% { transform: rotate(0deg); }
            }

            @keyframes sparkScanShift {
                0% { background-position: 0 0, 0 0; }
                100% { background-position: 24px 24px, 0 12px; }
            }

            @keyframes sparkTwinkle {
                0%, 100% { transform: scale(0.8); opacity: 0.6; }
                50% { transform: scale(1.3); opacity: 1; }
            }

            @keyframes sparkTextShimmer {
                0% { background-position: 0% center; }
                100% { background-position: 200% center; }
            }

            @keyframes sparkBurstPing {
                0% { transform: scale(0.6); opacity: 1; }
                100% { transform: scale(1.85); opacity: 0; }
            }

            @keyframes sparkFloatUp {
                0% { transform: translateY(0) scale(0.9); opacity: 1; }
                100% { transform: translateY(-42px) scale(1.3); opacity: 0; }
            }
        </style>
    @endif
</div>
