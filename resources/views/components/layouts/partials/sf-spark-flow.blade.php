{{-- Zentraler Seelenfunke Blickführungs- & 60-FPS-Funkenflug-Baustein (sfSparkFlow) --}}
@once
<style>
    .sf-magic-thin-aura {
        position: relative;
    }
    .sf-magic-thin-aura-ring {
        position: absolute;
        inset: 0;
        padding: 1.35px;
        border-radius: inherit;
        pointer-events: none;
        z-index: 20;
        overflow: hidden;
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        transition: opacity 0.28s ease, transform 0.28s ease;
    }
    .sf-magic-thin-aura-ring::before {
        content: '';
        position: absolute;
        left: 50%;
        top: 50%;
        width: 240%;
        aspect-ratio: 1 / 1;
        transform: translate(-50%, -50%) rotate(0deg);
        background: conic-gradient(
            from 0deg,
            rgba(197, 160, 89, 0.14) 0deg,
            rgba(197, 160, 89, 0.34) 55deg,
            #C5A059 105deg,
            #FDE68A 145deg,
            #FFFBEB 168deg,
            #C5A059 198deg,
            rgba(197, 160, 89, 0.22) 255deg,
            rgba(197, 160, 89, 0.14) 360deg
        );
        animation: sfMagicThinSpin 4.8s linear infinite;
    }
    .sf-magic-thin-aura-cta .sf-magic-thin-aura-ring {
        padding: 1.45px;
    }
    .sf-magic-thin-aura-pulse {
        animation: sfMagicBoxNudge 0.62s cubic-bezier(0.22, 1, 0.36, 1);
    }
    @keyframes sfMagicThinSpin {
        from { transform: translate(-50%, -50%) rotate(0deg); }
        to   { transform: translate(-50%, -50%) rotate(360deg); }
    }
    @keyframes sfMagicBoxNudge {
        0%   { transform: scale(1); box-shadow: 0 0 0 0 rgba(197, 160, 89, 0); }
        38%  { transform: scale(1.012); box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.24); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(197, 160, 89, 0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .sf-magic-thin-aura-ring::before {
            animation: none;
            background: linear-gradient(135deg, rgba(197, 160, 89, 0.55), rgba(253, 230, 138, 0.85), rgba(197, 160, 89, 0.55));
        }
        .sf-magic-thin-aura-pulse {
            animation: none;
        }
    }
</style>
<script>
    (function () {
        if (window.sfSparkFlow) return;

        const cooldownMap = new Map();
        const activeRuns = new Map();
        const PALETTE = ['#C5A059', '#FDE68A', '#FFFBEB', '#E5C17B'];

        function resolveVisibleElement(target) {
            if (!target) return null;
            if (target instanceof Element) {
                const r = target.getBoundingClientRect();
                return (r.width > 0 && r.height > 0) ? target : target;
            }
            if (typeof target === 'string') {
                const nodes = document.querySelectorAll(target);
                let fallback = null;
                for (let i = 0; i < nodes.length; i++) {
                    const el = nodes[i];
                    if (!fallback) fallback = el;
                    const r = el.getBoundingClientRect();
                    if (r.width > 0 && r.height > 0) {
                        return el;
                    }
                }
                return fallback;
            }
            return null;
        }

        function drawFourPointStar(ctx, cx, cy, outerR, innerR, rotRad) {
            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate(rotRad);
            ctx.beginPath();
            for (let i = 0; i < 8; i++) {
                const r = (i % 2 === 0) ? outerR : innerR;
                const a = (i * Math.PI) / 4;
                const x = Math.cos(a) * r;
                const y = Math.sin(a) * r;
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.closePath();
            ctx.fill();
            ctx.restore();
        }

        function pulseElement(el) {
            if (!el || !el.classList) return;
            el.classList.remove('sf-magic-thin-aura-pulse');
            void el.offsetWidth;
            el.classList.add('sf-magic-thin-aura-pulse');
            setTimeout(() => {
                if (el && el.classList) el.classList.remove('sf-magic-thin-aura-pulse');
            }, 660);
        }

        function cancelRun(runKey) {
            if (!runKey || !activeRuns.has(runKey)) return;
            const prev = activeRuns.get(runKey);
            activeRuns.delete(runKey);
            if (prev && typeof prev.cancel === 'function') {
                prev.cancel();
            }
        }

        function fly(fromTarget, toTarget, opts) {
            const options = opts || {};
            const mode = options.mode || 'unlock'; // 'unlock' | 'relock' | 'hint' | 'to-cart'
            const fromEl = resolveVisibleElement(fromTarget);
            const toEl = resolveVisibleElement(toTarget);
            const onArrival = typeof options.onArrival === 'function' ? options.onArrival : null;

            if (!fromEl || !toEl) {
                if (onArrival) onArrival();
                return null;
            }

            const nowMs = Date.now();
            if (mode === 'hint') {
                const cdKey = String(options.cooldownKey || (fromEl.id + '->' + toEl.id) || 'default-hint');
                const cooldownMs = typeof options.cooldownMs === 'number' ? options.cooldownMs : 850;
                const lastAt = cooldownMap.get(cdKey) || 0;
                if (nowMs - lastAt < cooldownMs) {
                    return null;
                }
                cooldownMap.set(cdKey, nowMs);
            }

            const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReduced) {
                if (mode === 'hint' || mode === 'relock') pulseElement(toEl);
                if (onArrival) onArrival();
                return null;
            }

            const srcRect = fromEl.getBoundingClientRect();
            const dstRect = toEl.getBoundingClientRect();
            if ((srcRect.width <= 0 && srcRect.height <= 0) || (dstRect.width <= 0 && dstRect.height <= 0)) {
                if (onArrival) onArrival();
                return null;
            }

            const runKey = options.runKey ? String(options.runKey) : null;
            if (runKey) cancelRun(runKey);

            const count = options.count || (mode === 'hint' ? 26 : (mode === 'relock' ? 34 : (mode === 'to-cart' ? 34 : 40)));
            const durationMs = options.duration || (mode === 'hint' ? 480 : (mode === 'relock' ? 500 : (mode === 'to-cart' ? 680 : 540)));
            const arrivalRatio = typeof options.arrivalRatio === 'number' ? options.arrivalRatio : 0.62;

            const mountRoot = (options.mountRoot instanceof Element) ? options.mountRoot : document.body;
            const canvas = document.createElement('canvas');
            canvas.setAttribute('data-sf-spark-canvas', mode);
            canvas.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:2147483647;';
            mountRoot.appendChild(canvas);

            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const vw = Math.max(1, window.innerWidth || document.documentElement.clientWidth || 1024);
            const vh = Math.max(1, window.innerHeight || document.documentElement.clientHeight || 768);
            canvas.width = Math.round(vw * dpr);
            canvas.height = Math.round(vh * dpr);
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                canvas.remove();
                if (onArrival) onArrival();
                return null;
            }
            ctx.scale(dpr, dpr);

            const particles = Array.from({ length: count }, (_, idx) => {
                const spreadSrc = count > 1 ? (idx / (count - 1)) : 0.5;
                const sx = srcRect.left + srcRect.width * (0.12 + spreadSrc * 0.76) + (Math.random() - 0.5) * 10;
                const sy = srcRect.top + srcRect.height * (0.25 + Math.random() * 0.5);

                const spreadDst = mode === 'to-cart'
                    ? 0.5
                    : (0.14 + ((idx * 7) % count) / Math.max(1, count - 1) * 0.72);
                const dx = dstRect.left + dstRect.width * spreadDst + (mode === 'to-cart' ? (Math.random() - 0.5) * 8 : 0);
                const dy = dstRect.top + dstRect.height * (mode === 'to-cart' ? 0.5 : (0.28 + Math.random() * 0.44));

                const midX = (sx + dx) * 0.5;
                const midY = (sy + dy) * 0.5;
                const sideCurve = ((idx % 2 === 0) ? 1 : -1) * (24 + Math.random() * 54);
                const liftY = mode === 'to-cart' ? (-45 - Math.random() * 65) : ((dy < sy ? -22 : -14) - Math.random() * 28);

                return {
                    sx,
                    sy,
                    cx: midX + sideCurve,
                    cy: midY + liftY,
                    dx,
                    dy,
                    delay: Math.random() * 0.22,
                    speed: 0.88 + Math.random() * 0.28,
                    size: (mode === 'hint' ? 1.7 : 1.9) + Math.random() * (mode === 'hint' ? 2.5 : 3.0),
                    isStar: idx % 3 === 0,
                    color: PALETTE[idx % PALETTE.length],
                    rot: Math.random() * Math.PI * 2,
                };
            });

            let rafId = 0;
            let cancelled = false;
            let arrivalFired = false;
            const startPerf = performance.now();

            const cleanup = () => {
                cancelled = true;
                if (rafId) cancelAnimationFrame(rafId);
                if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
                if (runKey && activeRuns.get(runKey)?.canvas === canvas) {
                    activeRuns.delete(runKey);
                }
            };

            const fireArrivalOnce = () => {
                if (arrivalFired || cancelled) return;
                arrivalFired = true;
                if (mode === 'hint' || mode === 'relock') {
                    pulseElement(toEl);
                } else if (mode === 'to-cart' && typeof toEl.animate === 'function') {
                    toEl.animate([
                        { transform: 'scale(1)' },
                        { transform: 'scale(1.26)' },
                        { transform: 'scale(1)' }
                    ], { duration: 340, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' });
                }
                if (onArrival) onArrival();
            };

            const step = (now) => {
                if (cancelled) return;
                const elapsed = now - startPerf;
                const norm = elapsed / durationMs;
                ctx.clearRect(0, 0, vw, vh);

                if (!arrivalFired && norm >= arrivalRatio) {
                    fireArrivalOnce();
                }

                let alive = 0;
                const tSec = elapsed * 0.001;
                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    const pt = ((norm - p.delay) * p.speed) / 0.82;
                    if (pt < 0) {
                        alive++;
                        continue;
                    }
                    if (pt >= 1) continue;
                    alive++;

                    const u = pt < 0.5 ? 4 * pt * pt * pt : 1 - Math.pow(-2 * pt + 2, 3) / 2;
                    const inv = 1 - u;
                    const x = inv * inv * p.sx + 2 * inv * u * p.cx + u * u * p.dx;
                    const y = inv * inv * p.sy + 2 * inv * u * p.cy + u * u * p.dy;
                    const alpha = Math.sin(Math.min(1, pt) * Math.PI);
                    const sz = p.size * (0.5 + 0.68 * alpha);

                    ctx.save();
                    ctx.globalAlpha = Math.max(0, Math.min(1, alpha));
                    ctx.fillStyle = p.color;
                    if (p.isStar) {
                        drawFourPointStar(ctx, x, y, sz * 1.42, sz * 0.38, p.rot + tSec * 4.2);
                    } else {
                        ctx.beginPath();
                        ctx.arc(x, y, sz * 0.58, 0, Math.PI * 2);
                        ctx.fill();
                    }
                    ctx.restore();
                }

                if (norm < 1.06 && alive > 0) {
                    rafId = requestAnimationFrame(step);
                } else {
                    fireArrivalOnce();
                    cleanup();
                }
            };

            const handle = { cancel: cleanup, canvas };
            if (runKey) activeRuns.set(runKey, handle);
            rafId = requestAnimationFrame(step);
            return handle;
        }

        window.sfSparkFlow = {
            fly,
            pulse: pulseElement,
            cancel: cancelRun,
            resolveVisibleElement,
        };
    })();
</script>
@endonce
