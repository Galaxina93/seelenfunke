{{-- NAHTLOSER SEELENFUNKEN-PARTIKEL-ÜBERGANG (100% ruckelfrei: Konvergenz in den Gold-Kern -> GPU-Handshake -> Sanftes Aufblühen nach Page-Load) --}}
<style>
    @keyframes sfPortalSpinSlow {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    @keyframes sfPortalSpinReverse {
        from { transform: rotate(360deg); }
        to { transform: rotate(0deg); }
    }
    @keyframes sfPortalCorePulse {
        0%, 100% { transform: scale(1); opacity: 0.92; }
        50% { transform: scale(1.06); opacity: 1; }
    }
    #seelenfunke-page-portal {
        position: fixed;
        inset: 0;
        width: 100vw;
        height: 100vh;
        z-index: 99998;
        display: none;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background-color: rgba(253, 248, 237, 0.96);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        pointer-events: none;
        will-change: opacity;
        transform: translateZ(0);
    }
    #seelenfunke-portal-glow {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background: radial-gradient(circle at 50% 50%, rgba(245, 158, 11, 0.36) 0%, rgba(197, 160, 89, 0.16) 24%, rgba(197, 160, 89, 0.05) 48%, transparent 70%);
        will-change: transform, opacity;
        transform: translateZ(0) scale(0.7);
        opacity: 0;
    }
    #seelenfunke-portal-rings {
        position: relative;
        z-index: 10;
        width: 168px;
        height: 168px;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        will-change: transform, opacity;
        transform: translateZ(0) scale(0.65);
        opacity: 0;
    }
    #seelenfunke-portal-core-orb {
        width: 38px;
        height: 38px;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(254, 243, 199, 1) 0%, rgba(251, 191, 36, 0.92) 42%, rgba(197, 160, 89, 0) 74%);
        box-shadow: 0 0 36px 12px rgba(245, 158, 11, 0.42);
        animation: sfPortalCorePulse 1.8s ease-in-out infinite;
        will-change: transform, opacity;
    }
</style>

<div id="seelenfunke-page-portal" aria-hidden="true">
    <div id="seelenfunke-portal-glow"></div>

    <div id="seelenfunke-portal-rings">
        <svg style="position: absolute; inset: 0; width: 100%; height: 100%; color: rgba(217, 119, 6, 0.45); animation: sfPortalSpinSlow 9s linear infinite;" viewBox="0 0 200 200">
            <circle cx="100" cy="100" r="82" fill="none" stroke="currentColor" stroke-width="1.1" stroke-dasharray="3 9" />
        </svg>
        <svg style="position: absolute; inset: 0; width: 100%; height: 100%; color: rgba(197, 160, 89, 0.55); animation: sfPortalSpinReverse 6.5s linear infinite;" viewBox="0 0 200 200">
            <circle cx="100" cy="100" r="56" fill="none" stroke="currentColor" stroke-width="1.3" stroke-dasharray="18 10 3 10" />
        </svg>
        <div id="seelenfunke-portal-core-orb"></div>
    </div>

    <canvas id="seelenfunke-portal-canvas" style="position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 20;"></canvas>
</div>

<script>
(function() {
    const STORAGE_KEY = 'sf_portal_transition';
    const portal = document.getElementById('seelenfunke-page-portal');
    const glow = document.getElementById('seelenfunke-portal-glow');
    const rings = document.getElementById('seelenfunke-portal-rings');
    const canvas = document.getElementById('seelenfunke-portal-canvas');
    if (!portal || !glow || !rings || !canvas) return;

    let rafId = null;

    // Deterministischer Pseudo-Zufallsgenerator, damit Partikel-Winkel konsistent und harmonisch verteilt sind
    function seededVal(idx, salt) {
        const x = Math.sin(idx * 12.9898 + salt * 78.233) * 43758.5453;
        return x - Math.floor(x);
    }

    /**
     * @param {'inward'|'outward'} direction
     */
    function runSparksAnimation(direction) {
        if (rafId) cancelAnimationFrame(rafId);

        const ctx = canvas.getContext('2d', { alpha: true });
        const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        const w = window.innerWidth;
        const h = window.innerHeight;
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const maxSpan = Math.max(w, h);
        const centerX = w / 2;
        const centerY = h / 2;
        const count = 64;

        // Zeitbasierte Interpolation (unabhängig von Main-Thread-Frame-Drops!)
        const durationMs = direction === 'inward' ? 420 : 660;
        const startTime = performance.now();

        const particles = Array.from({ length: count }, (_, i) => {
            const baseAngle = (i / count) * Math.PI * 2 + seededVal(i, 1) * 0.2;
            const outerR = maxSpan * (0.28 + seededVal(i, 2) * 0.36);
            const innerR = 4 + seededVal(i, 3) * 14;
            const spinDir = i % 2 === 0 ? 1 : -1;
            const totalTurn = (0.55 + seededVal(i, 4) * 0.45) * spinDir;

            return {
                baseAngle: baseAngle,
                outerR: outerR,
                innerR: innerR,
                totalTurn: totalTurn,
                r: 1.7 + seededVal(i, 5) * 2.8,
                twinklePhase: seededVal(i, 6) * Math.PI * 2
            };
        });

        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }

        function easeInOutCubic(t) {
            return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        }

        function frame(now) {
            const elapsed = Math.max(0, now - startTime);
            const rawProgress = Math.min(1, elapsed / durationMs);
            const progress = direction === 'inward' ? easeInOutCubic(rawProgress) : easeOutCubic(rawProgress);

            ctx.clearRect(0, 0, w, h);

            // Sanftes Ein-/Ausblenden der Partikel an den Endpunkten (damit beim Seitenwechsel alle im Kern verschmolzen sind)
            const globalAlpha = direction === 'inward'
                ? (rawProgress < 0.2 ? rawProgress / 0.2 : (1 - Math.pow(rawProgress, 2.6)))
                : (rawProgress < 0.15 ? rawProgress / 0.15 : (1 - Math.pow(rawProgress, 1.8)));

            if (globalAlpha > 0.01) {
                for (let i = 0; i < count; i++) {
                    const p = particles[i];
                    const currentR = direction === 'inward'
                        ? (p.outerR + (p.innerR - p.outerR) * progress)
                        : (p.innerR + (p.outerR - p.innerR) * progress);

                    const currentAngle = p.baseAngle + p.totalTurn * progress;
                    const prevProgress = Math.max(0, progress - 0.045);
                    const prevR = direction === 'inward'
                        ? (p.outerR + (p.innerR - p.outerR) * prevProgress)
                        : (p.innerR + (p.outerR - p.innerR) * prevProgress);
                    const prevAngle = p.baseAngle + p.totalTurn * prevProgress;

                    const x = centerX + Math.cos(currentAngle) * currentR;
                    const y = centerY + Math.sin(currentAngle) * currentR;
                    const px = centerX + Math.cos(prevAngle) * prevR;
                    const py = centerY + Math.sin(prevAngle) * prevR;

                    const twinkle = 0.65 + 0.35 * Math.sin(p.twinklePhase + rawProgress * 8);
                    const alpha = Math.max(0, Math.min(1, globalAlpha * twinkle));

                    // Weicher Lichtschweif
                    ctx.strokeStyle = 'rgba(245, 158, 11, ' + (alpha * 0.42) + ')';
                    ctx.lineWidth = p.r * 0.95;
                    ctx.lineCap = 'round';
                    ctx.beginPath();
                    ctx.moveTo(px, py);
                    ctx.lineTo(x, y);
                    ctx.stroke();

                    // Goldener Funken-Punkt
                    ctx.fillStyle = 'rgba(251, 191, 36, ' + alpha + ')';
                    ctx.beginPath();
                    ctx.arc(x, y, p.r, 0, Math.PI * 2);
                    ctx.fill();
                }
            }

            if (rawProgress < 1) {
                rafId = requestAnimationFrame(frame);
            } else {
                ctx.clearRect(0, 0, w, h);
                rafId = null;
            }
        }

        rafId = requestAnimationFrame(frame);
    }

    function hidePortalImmediate() {
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
        const ctx = canvas.getContext('2d');
        if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
        portal.style.display = 'none';
        portal.style.opacity = '0';
        portal.style.pointerEvents = 'none';
        glow.style.opacity = '0';
        glow.style.transform = 'translateZ(0) scale(0.7)';
        rings.style.opacity = '0';
        rings.style.transform = 'translateZ(0) scale(0.65)';
    }

    // 1. INCOMING UNVEIL: Auf der Zielseite steht ab Frame 0 nahtlos der leuchtende Gold-Kern auf der GPU.
    //    Erst wenn der Main-Thread der neuen Seite fertig gebootet hat, blühen die Funken mit 60 FPS nach außen auf!
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (raw) {
            sessionStorage.removeItem(STORAGE_KEY);
            const parsed = JSON.parse(raw);
            if (parsed && parsed.ts && (Date.now() - parsed.ts) < 8000) {
                portal.style.transition = 'none';
                glow.style.transition = 'none';
                rings.style.transition = 'none';

                portal.style.display = 'flex';
                portal.style.opacity = '1';
                glow.style.opacity = '1';
                glow.style.transform = 'translateZ(0) scale(1)';
                rings.style.opacity = '1';
                rings.style.transform = 'translateZ(0) scale(1)';

                let unveilTriggered = false;
                const triggerSmoothUnveil = () => {
                    if (unveilTriggered) return;
                    unveilTriggered = true;

                    // Zwei rAF-Ticks abwarten, damit der Browser Layout & Paint der neuen Seite komplett abgeschlossen hat
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            runSparksAnimation('outward');

                            portal.style.transition = 'opacity 640ms cubic-bezier(0.22, 1, 0.36, 1)';
                            glow.style.transition = 'opacity 580ms ease-out, transform 640ms cubic-bezier(0.22, 1, 0.36, 1)';
                            rings.style.transition = 'opacity 480ms ease-out, transform 620ms cubic-bezier(0.22, 1, 0.36, 1)';

                            portal.style.opacity = '0';
                            glow.style.opacity = '0';
                            glow.style.transform = 'translateZ(0) scale(1.45)';
                            rings.style.opacity = '0';
                            rings.style.transform = 'translateZ(0) scale(1.55)';

                            setTimeout(hidePortalImmediate, 660);
                        });
                    });
                };

                // Warte bis das initiale Skript-Setup der neuen Seite durch ist (oder max. 260ms Fallback)
                if (document.readyState === 'complete') {
                    setTimeout(triggerSmoothUnveil, 60);
                } else {
                    window.addEventListener('load', () => setTimeout(triggerSmoothUnveil, 50), { once: true });
                    setTimeout(triggerSmoothUnveil, 280);
                }
            }
        }
    } catch (e) {}

    function startOutgoingPortal(mode, onConverged) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ mode: mode || 'checkout', ts: Date.now() }));
        } catch (err) {}

        portal.style.pointerEvents = 'auto';
        portal.style.transition = 'opacity 360ms cubic-bezier(0.22, 1, 0.36, 1)';
        glow.style.transition = 'opacity 380ms ease-out, transform 420ms cubic-bezier(0.22, 1, 0.36, 1)';
        rings.style.transition = 'opacity 360ms ease-out, transform 420ms cubic-bezier(0.22, 1, 0.36, 1)';

        portal.style.display = 'flex';
        runSparksAnimation('inward');

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                portal.style.opacity = '1';
                glow.style.opacity = '1';
                glow.style.transform = 'translateZ(0) scale(1)';
                rings.style.opacity = '1';
                rings.style.transform = 'translateZ(0) scale(1)';
            });
        });

        setTimeout(() => {
            if (typeof onConverged === 'function') {
                onConverged();
            }
        }, 410);
    }

    window.sfRunPortalThen = function(callback, mode) {
        startOutgoingPortal(mode || 'checkout', callback);
    };

    // 2. OUTGOING CONVERGE: Beim Klick fliegen die Funken geschmeidig in den Gold-Kern in der Mitte
    document.addEventListener('click', function(e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const link = e.target && e.target.closest ? e.target.closest('[data-sf-portal]') : null;
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        const mode = link.getAttribute('data-sf-portal') || 'checkout';
        e.preventDefault();

        startOutgoingPortal(mode, function() {
            window.location.href = href;
        });
    });

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            hidePortalImmediate();
        }
    });
})();
</script>
