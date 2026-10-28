<script>
window.funkiHub = function(initialModelPath, initialImagePath, initialLevel = 1) {
    if (window.Alpine) {
        try {
            window.Alpine.store('funki', {
                activeLevel: Math.min(10, initialLevel || 1),
                setLevel(level) { this.activeLevel = level; }
            });
        } catch(e) {}
    }

    let scene, camera, renderer, controls, currentModel;
    let pedestalRingOuter, pedestalRingInner, auraParticles;
    let glbModelCache = {};
    let threeInitialized = false;
    let modelAnimScale = 1.0;
    let modelSpinProgress = 1.0;
    let modelSpinDuration = 0.78;
    let modelSpinTurns = 1.0;
    const BASE_ROTATION_Y = Math.PI / -2;
    let ringBoostMult = 1.0;
    let cameraZoomTargetZ = 2.55;
    let fxAnimId = null;

    // Synthesized Web Audio API Orchestral Level-Up Fanfare (Zero network lag!)
    const playAscensionFanfare = () => {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const now = ctx.currentTime;

            // 1. Phase 1: Power Convergence Riser (0.0s -> 1.05s)
            const riserOsc = ctx.createOscillator();
            const riserGain = ctx.createGain();
            riserOsc.type = 'sawtooth';
            riserOsc.frequency.setValueAtTime(75, now);
            riserOsc.frequency.exponentialRampToValueAtTime(440, now + 1.05);

            const riserFilter = ctx.createBiquadFilter();
            riserFilter.type = 'lowpass';
            riserFilter.frequency.setValueAtTime(180, now);
            riserFilter.frequency.exponentialRampToValueAtTime(2800, now + 1.05);

            riserGain.gain.setValueAtTime(0.001, now);
            riserGain.gain.linearRampToValueAtTime(0.14, now + 0.9);
            riserGain.gain.exponentialRampToValueAtTime(0.001, now + 1.15);

            riserOsc.connect(riserFilter);
            riserFilter.connect(riserGain);
            riserGain.connect(ctx.destination);
            riserOsc.start(now);
            riserOsc.stop(now + 1.16);

            // 2. Phase 2: Sub-Bass Pillar Impact at t = 1.05s
            const impactTime = now + 1.05;
            const subOsc = ctx.createOscillator();
            const subGain = ctx.createGain();
            subOsc.type = 'sine';
            subOsc.frequency.setValueAtTime(130, impactTime);
            subOsc.frequency.exponentialRampToValueAtTime(34, impactTime + 0.75);
            subGain.gain.setValueAtTime(0.32, impactTime);
            subGain.gain.exponentialRampToValueAtTime(0.001, impactTime + 0.8);
            subOsc.connect(subGain);
            subGain.connect(ctx.destination);
            subOsc.start(impactTime);
            subOsc.stop(impactTime + 0.82);

            // 3. Phase 3: Royal Golden Fanfare Arpeggio & Chord (C5 - E5 - G5 - B5 - D6)
            const notes = [523.25, 659.25, 783.99, 987.77, 1174.66];
            notes.forEach((freq, idx) => {
                const startT = impactTime + idx * 0.085;
                const osc1 = ctx.createOscillator();
                const osc2 = ctx.createOscillator();
                const gain = ctx.createGain();

                osc1.type = 'triangle';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(freq, startT);
                osc2.frequency.setValueAtTime(freq * 2, startT);

                gain.gain.setValueAtTime(0.001, startT);
                gain.gain.linearRampToValueAtTime(0.11, startT + 0.04);
                gain.gain.exponentialRampToValueAtTime(0.0008, startT + 2.4);

                osc1.connect(gain);
                osc2.connect(gain);
                gain.connect(ctx.destination);
                osc1.start(startT);
                osc2.start(startT);
                osc1.stop(startT + 2.45);
                osc2.stop(startT + 2.45);
            });
        } catch (e) {}
    };

    return {
        darkFade: false,
        startupFlash: false,
        evolutionFlash: false,
        showConfetti: false,
        rewardMessage: '',
        show3DModal: false,
        showTitlesModal: false,
        showGameModal: false,
        activeGame: null,
        currentPath: initialModelPath || '',
        currentImagePath: initialImagePath || '',
        activeModelLevel: initialLevel,
        isMusicPlaying: true,

        initFunki() {
            this.initShop();
            if (window.Alpine && window.Alpine.store('funki')) {
                window.Alpine.store('funki').setLevel(Math.min(10, this.activeModelLevel || 1));
            }
        },

        initShop() {
            if (!this.currentImagePath && initialImagePath) this.currentImagePath = initialImagePath;
            if (!this.currentPath && initialModelPath) this.currentPath = initialModelPath;

            if (window.sessionStorage.getItem('funki_just_activated')) {
                this.darkFade = true;
                window.sessionStorage.removeItem('funki_just_activated');
                setTimeout(() => { this.darkFade = false; }, 100);
            }

            this.initBackgroundSparks();

            setTimeout(() => {
                const audio = document.getElementById('funki-bgm');
                if (audio) {
                    audio.volume = 0.12;
                    audio.play().catch(() => { this.isMusicPlaying = false; });
                }
            }, 800);
        },

        // 2D Canvas Universum (für den Header im Shop)
        initBackgroundSparks() {
            const canvas = document.getElementById('funki-sparks-bg');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            let width, height;
            let stars = [];
            let planets = [];
            let meteors = [];
            let dust = [];

            const config = {
                starsCount: 200,
                planetsCount: 2,
                dustCount: 30,
                meteorsCount: 8,
                colors: {
                    gold: 'rgba(197, 160, 89,',
                    white: 'rgba(255, 255, 255,',
                    blue: 'rgba(100, 150, 255,',
                    copper: 'rgba(217, 119, 83,'
                }
            };

            const resize = () => {
                const container = document.getElementById('shop-header-container');
                if(!container) return;
                width = canvas.width = container.offsetWidth;
                height = canvas.height = container.offsetHeight;
            };
            window.addEventListener('resize', resize);
            resize();

            const random = (min, max) => Math.random() * (max - min) + min;

            for (let i = 0; i < config.starsCount; i++) {
                stars.push({
                    x: random(0, width), y: random(0, height), r: random(0.2, 1.0),
                    baseAlpha: random(0.1, 0.4), angle: random(0, Math.PI * 2), speed: random(0.002, 0.015),
                    color: Math.random() > 0.8 ? config.colors.gold : config.colors.white
                });
            }

            for (let i = 0; i < config.planetsCount; i++) {
                planets.push({
                    x: random(0, width), y: random(0, height), r: random(200, 500),
                    vx: random(-0.01, 0.01), vy: random(-0.01, 0.01),
                    color: [config.colors.gold, config.colors.blue][Math.floor(Math.random() * 2)],
                    maxAlpha: random(0.02, 0.05)
                });
            }

            for (let i = 0; i < config.dustCount; i++) {
                dust.push({
                    x: random(0, width), y: random(0, height), r: random(0.5, 2),
                    vx: random(-0.03, 0.03), angle: random(0, Math.PI * 2),
                    floatSpeed: random(0.002, 0.01), floatRange: random(10, 40),
                    baseY: random(0, height), alpha: random(0.05, 0.2)
                });
            }

            const spawnMeteor = (isInitial = false) => {
                const edge = Math.floor(random(0, 4));
                let x, y, vx, vy;
                const speed = random(0.05, 0.2);
                const angle = random(0, Math.PI * 2);

                if (isInitial) {
                    x = random(0, width); y = random(0, height);
                } else {
                    if (edge === 0) { x = -50; y = random(0, height); }
                    if (edge === 1) { x = width + 50; y = random(0, height); }
                    if (edge === 2) { x = random(0, width); y = -50; }
                    if (edge === 3) { x = random(0, width); y = height + 50; }
                }

                vx = Math.cos(angle) * speed; vy = Math.sin(angle) * speed;

                meteors.push({
                    x: x, y: y, vx: vx, vy: vy, size: random(0.5, 1.5),
                    alpha: random(0.1, 0.4), length: random(20, 80),
                    color: Math.random() > 0.5 ? config.colors.gold : config.colors.white
                });
            };

            for (let i = 0; i < config.meteorsCount; i++) spawnMeteor(true);

            const loop = () => {
                if (!document.getElementById('funki-sparks-bg')) return;
                ctx.clearRect(0, 0, width, height);

                planets.forEach(p => {
                    p.x += p.vx; p.y += p.vy;
                    if (p.x - p.r > width) p.x = -p.r; if (p.x + p.r < 0) p.x = width + p.r;
                    if (p.y - p.r > height) p.y = -p.r; if (p.y + p.r < 0) p.y = height + p.r;
                    let grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
                    grad.addColorStop(0, `${p.color}${p.maxAlpha})`); grad.addColorStop(1, 'rgba(0,0,0,0)');
                    ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2); ctx.fillStyle = grad; ctx.fill();
                });

                stars.forEach(s => {
                    s.angle += s.speed; let currentAlpha = s.baseAlpha + Math.sin(s.angle) * 0.2;
                    if (currentAlpha < 0) currentAlpha = 0;
                    ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2); ctx.fillStyle = `${s.color}${currentAlpha})`; ctx.fill();
                });

                dust.forEach(d => {
                    d.x += d.vx; d.angle += d.floatSpeed; d.y = d.baseY + Math.sin(d.angle) * d.floatRange;
                    if (d.x > width + 10) d.x = -10; if (d.x < -10) d.x = width + 10;
                    ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2); ctx.fillStyle = `${config.colors.gold}${d.alpha})`; ctx.fill();
                });

                for (let i = meteors.length - 1; i >= 0; i--) {
                    let m = meteors[i]; m.x += m.vx; m.y += m.vy;
                    if (m.x > width + 100 || m.x < -100 || m.y > height + 100 || m.y < -100) {
                        meteors.splice(i, 1); spawnMeteor(); continue;
                    }
                    ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                    let grad = ctx.createLinearGradient(m.x, m.y, m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                    grad.addColorStop(0, `${m.color}${m.alpha})`); grad.addColorStop(1, 'rgba(0,0,0,0)');
                    ctx.strokeStyle = grad; ctx.lineWidth = m.size; ctx.lineCap = 'round'; ctx.stroke();
                    ctx.beginPath(); ctx.arc(m.x, m.y, m.size, 0, Math.PI * 2); ctx.fillStyle = `${m.color}${m.alpha + 0.2})`; ctx.fill();
                }
                requestAnimationFrame(loop);
            };
            loop();
        },

        toggleMusic() {
            const audio = document.getElementById('funki-bgm');
            if (!audio) return;
            if (this.isMusicPlaying) {
                audio.pause(); this.isMusicPlaying = false;
            } else {
                audio.volume = 0.12; audio.play().catch(() => {}); this.isMusicPlaying = true;
            }
        },

        selectEvolutionStage(level, glbPath, imgPath) {
            this.currentPath = glbPath;
            this.currentImagePath = imgPath;
            this.activeModelLevel = level;
            if (window.Alpine && window.Alpine.store('funki')) {
                window.Alpine.store('funki').setLevel(Math.min(10, level));
            }
            if (window._funki3DLoader) {
                window._funki3DLoader(glbPath, () => {
                    modelAnimScale = 0.72;
                    modelSpinProgress = 0.0;
                    modelSpinDuration = 0.78;
                    modelSpinTurns = 1.0;
                    ringBoostMult = 3.2;
                });
            }
        },

        triggerEpicAscensionDemo() {
            this.handleLevelUp({
                newModelPath: this.currentPath,
                newImagePath: this.currentImagePath,
                level: this.activeModelLevel,
                reward: 'Seelen-Aufstieg • Göttliche Manufaktur-Aura'
            });
        },

        handleLevelUp(data) {
            const payload = Array.isArray(data) ? (data[0] || {}) : (data || {});
            const wasAlreadyOpen = this.show3DModal;
            this.open3DModal();

            // Preload new GLB in memory before climax so 0ms stutter occurs at the light pillar peak!
            const targetPath = payload.newModelPath || this.currentPath;
            if (targetPath && !glbModelCache[targetPath] && window.GLTFLoader) {
                const preloader = new window.GLTFLoader();
                preloader.load(targetPath, (gltf) => {
                    glbModelCache[targetPath] = gltf.scene;
                });
            }

            setTimeout(() => {
                this.runEpicAscensionFX(payload);
            }, wasAlreadyOpen ? 40 : 180);
        },

        // AAA 60 FPS 4-Phase Level-Up Ascension Sequence (Genshin / LoL / Clash Royale style)
        runEpicAscensionFX(payload) {
            const fxCanvas = document.getElementById('funki-levelup-fx-canvas');
            if (!fxCanvas) return;
            const ctx = fxCanvas.getContext('2d');
            if (!ctx) return;

            if (fxAnimId) cancelAnimationFrame(fxAnimId);
            this.showConfetti = false;
            this.rewardMessage = payload.reward || 'Neue Evolutionsstufe erreicht!';

            const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
            const resizeFx = () => {
                const w = fxCanvas.clientWidth || window.innerWidth;
                const h = fxCanvas.clientHeight || window.innerHeight;
                fxCanvas.width = Math.floor(w * dpr);
                fxCanvas.height = Math.floor(h * dpr);
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                return { w, h };
            };
            let { w, h } = resizeFx();
            const cx = () => w * 0.5;
            const cy = () => h * 0.52;

            playAscensionFanfare();

            // 1. Phase 1 Orbs (Spiraling inward during 0.0s -> 1.05s)
            const convOrbs = [];
            for (let i = 0; i < 135; i++) {
                const ang = Math.random() * Math.PI * 2;
                const dist = 160 + Math.random() * Math.max(w, h) * 0.58;
                convOrbs.push({
                    angle: ang,
                    dist: dist,
                    startDist: dist,
                    spinSpeed: (2.8 + Math.random() * 3.5) * (Math.random() > 0.5 ? 1 : -1),
                    size: 2.0 + Math.random() * 3.8,
                    color: Math.random() > 0.25 ? '#d97706' : (Math.random() > 0.5 ? '#f59e0b' : '#0284c7')
                });
            }

            // 2. Phase 3 Supernova Confetti & Embers (erupting at t = 1.05s)
            const burstSparks = [];
            const palette = ['#d97706', '#f59e0b', '#b45309', '#0284c7', '#c026d3', '#eab308'];
            for (let i = 0; i < 190; i++) {
                const ang = Math.random() * Math.PI * 2;
                const spd = 120 + Math.random() * 580;
                burstSparks.push({
                    x: 0,
                    y: 0,
                    vx: Math.cos(ang) * spd,
                    vy: Math.sin(ang) * spd - 90,
                    gravity: 160 + Math.random() * 140,
                    drag: 0.965,
                    size: 2.5 + Math.random() * 5.0,
                    rot: Math.random() * Math.PI * 2,
                    rotSpd: (Math.random() - 0.5) * 10,
                    isRibbon: Math.random() > 0.45,
                    color: palette[Math.floor(Math.random() * palette.length)],
                    alpha: 1.0
                });
            }

            // 3D Stage camera & ring acceleration
            ringBoostMult = 7.5;
            cameraZoomTargetZ = 2.05;

            let modelSwapped = false;
            const startTime = performance.now();
            let prevTime = startTime;

            const step = (now) => {
                const dt = Math.min((now - prevTime) / 1000, 0.05);
                prevTime = now;
                const elapsed = (now - startTime) / 1000;

                ctx.clearRect(0, 0, w, h);
                const centerX = cx();
                const centerY = cy();

                // --- PHASE 1: ENERGY CONVERGENCE (0.0s -> 1.05s) ---
                if (elapsed < 1.08) {
                    const p = Math.min(1.0, elapsed / 1.05);
                    const easeIn = p * p * p;

                    // Growing core glow
                    const coreR = 25 + easeIn * 140;
                    const coreGrad = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, coreR);
                    coreGrad.addColorStop(0, `rgba(255, 255, 255, ${0.15 + easeIn * 0.75})`);
                    coreGrad.addColorStop(0.4, `rgba(251, 191, 36, ${0.12 + easeIn * 0.55})`);
                    coreGrad.addColorStop(1, 'rgba(245, 158, 11, 0)');
                    ctx.fillStyle = coreGrad;
                    ctx.beginPath();
                    ctx.arc(centerX, centerY, coreR, 0, Math.PI * 2);
                    ctx.fill();

                    // Spiraling Soul Orbs
                    for (let i = 0; i < convOrbs.length; i++) {
                        const o = convOrbs[i];
                        o.angle += o.spinSpeed * dt * (1 + easeIn * 2.2);
                        const curDist = o.startDist * (1 - easeIn);
                        const ox = centerX + Math.cos(o.angle) * curDist;
                        const oy = centerY + Math.sin(o.angle) * curDist * 0.82;
                        const tailAng = o.angle - o.spinSpeed * 0.045;
                        const tx = centerX + Math.cos(tailAng) * (curDist + 24);
                        const ty = centerY + Math.sin(tailAng) * (curDist + 24) * 0.82;

                        ctx.strokeStyle = o.color;
                        ctx.lineWidth = o.size * 0.75;
                        ctx.globalAlpha = Math.min(1, p * 1.6);
                        ctx.beginPath();
                        ctx.moveTo(tx, ty);
                        ctx.lineTo(ox, oy);
                        ctx.stroke();

                        ctx.fillStyle = '#ffffff';
                        ctx.beginPath();
                        ctx.arc(ox, oy, o.size * 0.65, 0, Math.PI * 2);
                        ctx.fill();
                    }
                    ctx.globalAlpha = 1;
                }

                // --- CLIMAX TRIGGER AT t = 1.05s (SWAP 3D MODEL + PILLAR OF LIGHT) ---
                if (elapsed >= 1.05 && !modelSwapped) {
                    modelSwapped = true;
                    if (payload.newModelPath) this.currentPath = payload.newModelPath;
                    if (payload.newImagePath) this.currentImagePath = payload.newImagePath;
                    this.activeModelLevel = payload.level || payload.newLevel || this.activeModelLevel;
                    if (window.Alpine && window.Alpine.store('funki')) {
                        window.Alpine.store('funki').setLevel(Math.min(10, this.activeModelLevel));
                    }

                    if (window._funki3DLoader && this.currentPath) {
                        window._funki3DLoader(this.currentPath, () => {
                            modelAnimScale = 0.35;
                            modelSpinProgress = 0.0;
                            modelSpinDuration = 1.15;
                            modelSpinTurns = 2.0;
                        });
                    }
                    cameraZoomTargetZ = 2.55;
                    this.showConfetti = true;

                    // Initialize burst positions at center
                    for (let i = 0; i < burstSparks.length; i++) {
                        burstSparks[i].x = centerX;
                        burstSparks[i].y = centerY;
                    }
                }

                // --- PHASE 2 & 3: HEAVEN'S PILLAR, GOD-RAYS, SHOCKWAVES & SUPERNOVA (1.05s -> 5.6s) ---
                if (elapsed >= 1.05) {
                    const postT = elapsed - 1.05;

                    // 1. Rotating Golden God-Rays behind/around Funki
                    if (postT < 4.2) {
                        const rayAlpha = postT < 0.3 ? (postT / 0.3) : Math.max(0, 1 - (postT - 0.3) / 3.9);
                        ctx.save();
                        ctx.translate(centerX, centerY);
                        ctx.rotate(postT * 0.32);
                        const rayCount = 14;
                        const maxRadius = Math.max(w, h) * 0.75;
                        for (let r = 0; r < rayCount; r++) {
                            const a0 = (r / rayCount) * Math.PI * 2;
                            const a1 = a0 + (Math.PI / rayCount) * 0.45;
                            const rGrad = ctx.createRadialGradient(0, 0, 10, 0, 0, maxRadius);
                            rGrad.addColorStop(0, `rgba(254, 240, 138, ${0.38 * rayAlpha})`);
                            rGrad.addColorStop(0.5, `rgba(245, 158, 11, ${0.14 * rayAlpha})`);
                            rGrad.addColorStop(1, 'rgba(245, 158, 11, 0)');
                            ctx.fillStyle = rGrad;
                            ctx.beginPath();
                            ctx.moveTo(0, 0);
                            ctx.arc(0, 0, maxRadius, a0, a1);
                            ctx.closePath();
                            ctx.fill();
                        }
                        ctx.restore();
                    }

                    // 2. Vertical Golden Pillar of Light (1.05s -> 2.15s)
                    if (postT < 1.1) {
                        const pilP = postT / 1.1;
                        const pilAlpha = Math.sin((1 - pilP) * Math.PI * 0.5);
                        const pilWidth = (w < 640 ? 130 : 220) * (1 - pilP * 0.45);
                        const pilGrad = ctx.createLinearGradient(centerX - pilWidth, 0, centerX + pilWidth, 0);
                        pilGrad.addColorStop(0, 'rgba(245, 158, 11, 0)');
                        pilGrad.addColorStop(0.3, `rgba(251, 191, 36, ${0.45 * pilAlpha})`);
                        pilGrad.addColorStop(0.5, `rgba(255, 255, 255, ${0.92 * pilAlpha})`);
                        pilGrad.addColorStop(0.7, `rgba(251, 191, 36, ${0.45 * pilAlpha})`);
                        pilGrad.addColorStop(1, 'rgba(245, 158, 11, 0)');
                        ctx.fillStyle = pilGrad;
                        ctx.fillRect(centerX - pilWidth, 0, pilWidth * 2, h);
                    }

                    // 3. Expanding Concentric Shockwave Rings (1.05s -> 2.25s)
                    if (postT < 1.2) {
                        const rings = [
                            { delay: 0.0, speed: 680, color: '254, 240, 138', width: 8 },
                            { delay: 0.12, speed: 540, color: '56, 189, 248', width: 5 },
                            { delay: 0.24, speed: 420, color: '245, 158, 11', width: 4 }
                        ];
                        rings.forEach(rg => {
                            const rt = postT - rg.delay;
                            if (rt > 0 && rt < 0.95) {
                                const rad = 20 + rt * rg.speed;
                                const alpha = Math.max(0, 1 - rt / 0.95);
                                ctx.strokeStyle = `rgba(${rg.color}, ${alpha * 0.85})`;
                                ctx.lineWidth = rg.width * alpha;
                                ctx.beginPath();
                                ctx.arc(centerX, centerY, rad, 0, Math.PI * 2);
                                ctx.stroke();
                            }
                        });
                    }

                    // 4. Supernova Confetti & Golden Embers (60 FPS Physics)
                    for (let i = 0; i < burstSparks.length; i++) {
                        const s = burstSparks[i];
                        s.vx *= Math.pow(s.drag, dt * 60);
                        s.vy = s.vy * Math.pow(s.drag, dt * 60) + s.gravity * dt;
                        s.x += s.vx * dt;
                        s.y += s.vy * dt;
                        s.rot += s.rotSpd * dt;
                        s.alpha = Math.max(0, 1 - postT / 4.3);

                        if (s.alpha <= 0.01) continue;

                        ctx.save();
                        ctx.globalAlpha = s.alpha;
                        ctx.translate(s.x, s.y);
                        ctx.rotate(s.rot);
                        ctx.fillStyle = s.color;

                        if (s.isRibbon) {
                            ctx.fillRect(-s.size * 1.1, -s.size * 0.45, s.size * 2.2, s.size * 0.9);
                        } else {
                            ctx.beginPath();
                            ctx.arc(0, 0, s.size * 0.55, 0, Math.PI * 2);
                            ctx.fill();
                        }
                        ctx.restore();
                    }
                }

                if (elapsed < 5.6) {
                    fxAnimId = requestAnimationFrame(step);
                } else {
                    ctx.clearRect(0, 0, w, h);
                    this.showConfetti = false;
                    fxAnimId = null;
                }
            };

            fxAnimId = requestAnimationFrame(step);
        },

        open3DModal() {
            this.show3DModal = true;
            setTimeout(() => {
                if (!threeInitialized) this.initThreeJS();
                else this.resizeThreeJS();

                const activeTimelineItem = document.querySelector('.scale-110.ring-\\[\\#C5A059\\]\\/50');
                if (activeTimelineItem && activeTimelineItem.parentElement) {
                    activeTimelineItem.parentElement.parentElement.scrollLeft = activeTimelineItem.parentElement.offsetLeft - 40;
                }
            }, 80);
        },

        close3DModal() {
            this.show3DModal = false;
            this.showConfetti = false;
            if (fxAnimId) {
                cancelAnimationFrame(fxAnimId);
                fxAnimId = null;
            }
        },

        initThreeJS() {
            const container = document.getElementById('funki-3d-canvas-container');
            if (!container || typeof window.THREE === 'undefined') return;
            const THREE = window.THREE;

            scene = new THREE.Scene();
            camera = new THREE.PerspectiveCamera(44, container.offsetWidth / container.offsetHeight, 0.1, 1000);
            renderer = new THREE.WebGLRenderer({ antialias: window.innerWidth > 768, alpha: true, powerPreference: 'high-performance' });
            renderer.setSize(container.offsetWidth, container.offsetHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.35));
            renderer.toneMapping = THREE.ACESFilmicToneMapping;
            container.innerHTML = '';
            container.appendChild(renderer.domElement);

            scene.add(new THREE.AmbientLight(0xfffdf9, 2.55));
            const dirLight = new THREE.DirectionalLight(0xfffbeb, 2.45);
            dirLight.position.set(4, 6, 5);
            scene.add(dirLight);

            const rimLight = new THREE.DirectionalLight(0xf59e0b, 1.45);
            rimLight.position.set(-5, 3, -4);
            scene.add(rimLight);

            const fillLight = new THREE.DirectionalLight(0xfef3c7, 0.95);
            fillLight.position.set(0, -4, 3);
            scene.add(fillLight);

            // Edle helle Atelier-Podest-Scheibe unter Funki
            const baseDiscGeo = new THREE.CircleGeometry(1.06, 64);
            const baseDiscMat = new THREE.MeshBasicMaterial({
                color: 0xebd9b7,
                side: THREE.DoubleSide,
                transparent: true,
                opacity: 0.45
            });
            const baseDisc = new THREE.Mesh(baseDiscGeo, baseDiscMat);
            baseDisc.rotation.x = Math.PI / 2;
            baseDisc.position.y = -0.83;
            scene.add(baseDisc);

            // 3D Golden Arcane Pedestal Rings under Funki (Optimiert für den Hellmodus)
            const rOuterGeo = new THREE.RingGeometry(0.92, 1.02, 64);
            const rOuterMat = new THREE.MeshBasicMaterial({
                color: 0xd97706,
                side: THREE.DoubleSide,
                transparent: true,
                opacity: 0.55
            });
            pedestalRingOuter = new THREE.Mesh(rOuterGeo, rOuterMat);
            pedestalRingOuter.rotation.x = Math.PI / 2;
            pedestalRingOuter.position.y = -0.82;
            scene.add(pedestalRingOuter);

            const rInnerGeo = new THREE.RingGeometry(0.62, 0.67, 48);
            const rInnerMat = new THREE.MeshBasicMaterial({
                color: 0xb45309,
                side: THREE.DoubleSide,
                transparent: true,
                opacity: 0.42
            });
            pedestalRingInner = new THREE.Mesh(rInnerGeo, rInnerMat);
            pedestalRingInner.rotation.x = Math.PI / 2;
            pedestalRingInner.position.y = -0.81;
            scene.add(pedestalRingInner);

            // 3D Floating Soul-Sparks Rising Around Funki
            const pCount = 85;
            const pGeo = new THREE.BufferGeometry();
            const pPos = new Float32Array(pCount * 3);
            for (let i = 0; i < pCount; i++) {
                const ang = Math.random() * Math.PI * 2;
                const rad = 0.55 + Math.random() * 1.4;
                pPos[i * 3]     = Math.cos(ang) * rad;
                pPos[i * 3 + 1] = -0.9 + Math.random() * 2.2;
                pPos[i * 3 + 2] = Math.sin(ang) * rad;
            }
            pGeo.setAttribute('position', new THREE.BufferAttribute(pPos, 3));
            const pMat = new THREE.PointsMaterial({
                color: 0xd97706,
                size: 0.05,
                transparent: true,
                opacity: 0.72
            });
            auraParticles = new THREE.Points(pGeo, pMat);
            scene.add(auraParticles);

            controls = new window.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.07;
            controls.minDistance = 1.2;
            controls.maxDistance = 4.5;
            camera.position.set(0, 0.65, 2.55);

            window._funki3DLoader = (path, cb) => {
                if (!path || path.trim() === '' || path === window.location.pathname || path === '/') {
                    if (cb) cb();
                    return;
                }

                const attachScene = (rawScene) => {
                    if (currentModel) {
                        scene.remove(currentModel);
                        currentModel = null;
                    }
                    currentModel = rawScene;
                    if (!currentModel.userData._centered) {
                        const box = new THREE.Box3().setFromObject(currentModel);
                        const center = box.getCenter(new THREE.Vector3());
                        currentModel.position.sub(center);
                        currentModel.userData._centered = true;
                        currentModel.userData._baseX = currentModel.position.x;
                        currentModel.userData._baseY = currentModel.position.y;
                        currentModel.userData._baseZ = currentModel.position.z;
                    } else {
                        currentModel.position.set(
                            currentModel.userData._baseX || 0,
                            currentModel.userData._baseY || 0,
                            currentModel.userData._baseZ || 0
                        );
                    }
                    currentModel.rotation.y = BASE_ROTATION_Y;
                    scene.add(currentModel);
                    if (cb) cb();
                };

                if (glbModelCache[path]) {
                    attachScene(glbModelCache[path]);
                    return;
                }

                window._funkiLoadId = (window._funkiLoadId || 0) + 1;
                const currentLoadId = window._funkiLoadId;
                const loader = new window.GLTFLoader();

                loader.load(path, (gltf) => {
                    if (currentLoadId !== window._funkiLoadId) {
                        if (cb) cb();
                        return;
                    }
                    glbModelCache[path] = gltf.scene;
                    attachScene(gltf.scene);
                }, undefined, (error) => {
                    console.error("Fehler beim Laden des 3D Modells:", error);
                    if (cb) cb();
                });
            };

            window._funki3DLoader(this.currentPath);

            let prevT = performance.now();
            const animate = (now) => {
                requestAnimationFrame(animate);
                if (!this.show3DModal) {
                    prevT = now;
                    return;
                }
                const dt = Math.min((now - prevT) / 1000, 0.05);
                prevT = now;

                if (currentModel) {
                    const baseY = (currentModel.userData && typeof currentModel.userData._baseY === 'number') ? currentModel.userData._baseY : 0;
                    currentModel.position.y = baseY + Math.sin(now * 0.0022) * 0.045;

                    // Geschmeidige, fließende Scale- & 360°-Dreh-Animation ohne Ruckeln oder Zurückspringen
                    if (modelAnimScale < 1.0) {
                        modelAnimScale = Math.min(1.0, modelAnimScale + dt * 2.2);
                        const easeOutBack = 1 + 1.15 * Math.pow(modelAnimScale - 1, 3) + 0.15 * Math.pow(modelAnimScale - 1, 2);
                        currentModel.scale.setScalar(Math.max(0.2, easeOutBack));
                    } else {
                        currentModel.scale.setScalar(1.0);
                    }

                    if (modelSpinProgress < 1.0) {
                        modelSpinProgress = Math.min(1.0, modelSpinProgress + dt / Math.max(0.25, modelSpinDuration));
                        const easeOutQuart = 1 - Math.pow(1 - modelSpinProgress, 4);
                        currentModel.rotation.y = BASE_ROTATION_Y + (easeOutQuart * Math.PI * 2 * modelSpinTurns);
                    } else {
                        currentModel.rotation.y = BASE_ROTATION_Y;
                    }
                }

                // Pedestal Rings & Rising Sparks
                if (ringBoostMult > 1.0) {
                    ringBoostMult = Math.max(1.0, ringBoostMult - dt * 3.5);
                }
                if (pedestalRingOuter) pedestalRingOuter.rotation.z += dt * 0.55 * ringBoostMult;
                if (pedestalRingInner) pedestalRingInner.rotation.z -= dt * 0.95 * ringBoostMult;

                if (auraParticles) {
                    auraParticles.rotation.y += dt * 0.25 * ringBoostMult;
                    const posAttr = auraParticles.geometry.attributes.position;
                    for (let i = 0; i < posAttr.count; i++) {
                        let y = posAttr.getY(i) + dt * 0.38 * ringBoostMult;
                        if (y > 1.3) y = -0.85;
                        posAttr.setY(i, y);
                    }
                    posAttr.needsUpdate = true;
                }

                if (camera && Math.abs(camera.position.z - cameraZoomTargetZ) > 0.01) {
                    camera.position.z += (cameraZoomTargetZ - camera.position.z) * Math.min(1, dt * 4.5);
                }

                if (controls) controls.update();
                if (renderer) renderer.render(scene, camera);
            };
            requestAnimationFrame(animate);
            threeInitialized = true;

            window.addEventListener('resize', () => this.resizeThreeJS());
        },

        resizeThreeJS() {
            const container = document.getElementById('funki-3d-canvas-container');
            if (!camera || !renderer || !container) return;
            const w = container.offsetWidth;
            const h = container.offsetHeight;
            if (w < 10 || h < 10) return;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        }
    };
};
</script>
