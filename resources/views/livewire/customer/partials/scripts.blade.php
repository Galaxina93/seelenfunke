<script>
    // ==========================================
    // 1. OPT-IN SCREEN (LANDING PAGE)
    // ==========================================
    if (typeof window.optInScreen === 'undefined') {
        window.optInScreen = function() {
            return {
                mouseX: 0,
                mouseY: 0,
                isHovering: false,
                isWarping: false,
                isActivating: false,
                phase: 0,
                flash: false,

                handleMouse(e) {
                    this.isHovering = true;
                    const rect = this.$el.getBoundingClientRect();
                    this.mouseX = e.clientX - rect.left;
                    this.mouseY = e.clientY - rect.top;
                },
                resetMouse() {
                    this.isHovering = false;
                },
                triggerEpicStart() {
                    this.isWarping = true;
                    this.isActivating = true;

                    // Sagt dem HTML, dass es sich ausblenden soll
                    window.dispatchEvent(new CustomEvent('warp-started'));

                    // Löst die Explosionseffekt-Funktion aus dem Universum-Skript aus (falls vorhanden)
                    if (window.spawnEpicExplosion) window.spawnEpicExplosion(this.mouseX, this.mouseY);

                    // Sagt dem Universum, dass es auf Lichtgeschwindigkeit beschleunigen soll
                    if (window.startWarpSpeed) window.startWarpSpeed();

                    setTimeout(() => {
                        this.phase = 1;
                    }, 50);

                    // Nach 2.5 Sekunden Warp-Flug laden wir die neue Seite im Backend
                    setTimeout(() => {
                        window.sessionStorage.setItem('funki_just_activated', 'true');
                    @this.call('optIn');
                    }, 2500);
                }
            };
        };
    }

    // ==========================================
    // 2. FUNKI HUB (DAS DASHBOARD NACH DEM OPT-IN)
    // ==========================================
    if (typeof window.funkiHub === 'undefined') {
        window.funkiHub = function(initialModelPath, initialImagePath) {
            let scene, camera, renderer, controls, currentModel;
            let sparksScene, sparksCamera, sparksRenderer, sparksParticles;
            let threeInitialized = false;

            return {
                darkFade: false,
                startupFlash: false,
                evolutionFlash: false,
                showConfetti: false,
                rewardMessage: '',
                show3DModal: false,
                showTitlesModal: false,
                showGameModal: false,
                activeGame: null, // FEHLERBEHEBUNG: Hier ist die fehlende Variable!
                currentPath: initialModelPath,
                currentImagePath: initialImagePath,
                isMusicPlaying: true,

                initShop() {
                    // Startet den Fade-In aus dem Dunkeln nach dem Warp
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

                    // Konfiguration für das Universum
                    const config = {
                        starsCount: 200,     // Menge der kleinen Funkel-Sterne
                        planetsCount: 2,     // Große Geister-Monde/Sonnen
                        dustCount: 30,       // Schwebender Sternenstaub
                        meteorsCount: 8,     // Vorbeifliegende goldene Sternschnuppen
                        colors: {
                            gold: 'rgba(197, 160, 89,',
                            white: 'rgba(255, 255, 255,',
                            blue: 'rgba(100, 150, 255,',
                            copper: 'rgba(217, 119, 83,'
                        }
                    };

                    const resize = () => {
                        // Container abfragen, da das Canvas sich auf die Größe des Headers beschränkt
                        const container = document.getElementById('shop-header-container');
                        if(!container) return;
                        width = canvas.width = container.offsetWidth;
                        height = canvas.height = container.offsetHeight;
                    };
                    window.addEventListener('resize', resize);
                    resize(); // initialer Aufruf

                    const random = (min, max) => Math.random() * (max - min) + min;

                    // 1. Initialisiere Sterne
                    for (let i = 0; i < config.starsCount; i++) {
                        stars.push({
                            x: random(0, width),
                            y: random(0, height),
                            r: random(0.2, 1.0),
                            baseAlpha: random(0.1, 0.4),
                            angle: random(0, Math.PI * 2),
                            speed: random(0.002, 0.015),
                            color: Math.random() > 0.8 ? config.colors.gold : config.colors.white
                        });
                    }

                    // 2. Initialisiere Monde / Sonnen (Geisterhaft)
                    for (let i = 0; i < config.planetsCount; i++) {
                        planets.push({
                            x: random(0, width),
                            y: random(0, height),
                            r: random(200, 500),
                            vx: random(-0.01, 0.01),
                            vy: random(-0.01, 0.01),
                            color: [config.colors.gold, config.colors.blue][Math.floor(Math.random() * 2)],
                            maxAlpha: random(0.02, 0.05)
                        });
                    }

                    // 3. Initialisiere Sternenstaub (Schwebt hin und her)
                    for (let i = 0; i < config.dustCount; i++) {
                        dust.push({
                            x: random(0, width),
                            y: random(0, height),
                            r: random(0.5, 2),
                            vx: random(-0.03, 0.03),
                            angle: random(0, Math.PI * 2),
                            floatSpeed: random(0.002, 0.01),
                            floatRange: random(10, 40),
                            baseY: random(0, height),
                            alpha: random(0.05, 0.2)
                        });
                    }

                    // 4. Initialisiere Meteoriten
                    const spawnMeteor = (isInitial = false) => {
                        const edge = Math.floor(random(0, 4));
                        let x, y, vx, vy;
                        const speed = random(0.05, 0.2); // Sehr langsam
                        const angle = random(0, Math.PI * 2);

                        if (isInitial) {
                            x = random(0, width);
                            y = random(0, height);
                        } else {
                            if (edge === 0) { x = -50; y = random(0, height); }
                            if (edge === 1) { x = width + 50; y = random(0, height); }
                            if (edge === 2) { x = random(0, width); y = -50; }
                            if (edge === 3) { x = random(0, width); y = height + 50; }
                        }

                        vx = Math.cos(angle) * speed;
                        vy = Math.sin(angle) * speed;

                        meteors.push({
                            x: x, y: y, vx: vx, vy: vy,
                            size: random(0.5, 1.5),
                            alpha: random(0.1, 0.4),
                            length: random(20, 80),
                            color: Math.random() > 0.5 ? config.colors.gold : config.colors.white
                        });
                    };

                    for (let i = 0; i < config.meteorsCount; i++) {
                        spawnMeteor(true);
                    }

                    // ==========================================
                    // RENDER LOOP
                    // ==========================================
                    const loop = () => {
                        // Abbruchbedingung falls Canvas gelöscht wird (zB bei Navigation)
                        if (!document.getElementById('funki-sparks-bg')) return;

                        ctx.clearRect(0, 0, width, height);

                        // 1. Monde & Sonnen zeichnen
                        planets.forEach(p => {
                            p.x += p.vx;
                            p.y += p.vy;

                            if (p.x - p.r > width) p.x = -p.r;
                            if (p.x + p.r < 0) p.x = width + p.r;
                            if (p.y - p.r > height) p.y = -p.r;
                            if (p.y + p.r < 0) p.y = height + p.r;

                            let grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
                            grad.addColorStop(0, `${p.color}${p.maxAlpha})`);
                            grad.addColorStop(1, 'rgba(0,0,0,0)');

                            ctx.beginPath();
                            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                            ctx.fillStyle = grad;
                            ctx.fill();
                        });

                        // 2. Sterne zeichnen (Funkeln)
                        stars.forEach(s => {
                            s.angle += s.speed;
                            let currentAlpha = s.baseAlpha + Math.sin(s.angle) * 0.2;
                            if (currentAlpha < 0) currentAlpha = 0;

                            ctx.beginPath();
                            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                            ctx.fillStyle = `${s.color}${currentAlpha})`;
                            ctx.fill();
                        });

                        // 3. Sternenstaub (Schweben)
                        dust.forEach(d => {
                            d.x += d.vx;
                            d.angle += d.floatSpeed;
                            d.y = d.baseY + Math.sin(d.angle) * d.floatRange;

                            if (d.x > width + 10) d.x = -10;
                            if (d.x < -10) d.x = width + 10;

                            ctx.beginPath();
                            ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
                            ctx.fillStyle = `${config.colors.gold}${d.alpha})`;
                            ctx.fill();
                        });

                        // 4. Meteoriten zeichnen
                        for (let i = meteors.length - 1; i >= 0; i--) {
                            let m = meteors[i];
                            m.x += m.vx;
                            m.y += m.vy;

                            if (m.x > width + 100 || m.x < -100 || m.y > height + 100 || m.y < -100) {
                                meteors.splice(i, 1);
                                spawnMeteor();
                                continue;
                            }

                            // Schweif
                            ctx.beginPath();
                            ctx.moveTo(m.x, m.y);
                            ctx.lineTo(m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                            let grad = ctx.createLinearGradient(m.x, m.y, m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                            grad.addColorStop(0, `${m.color}${m.alpha})`);
                            grad.addColorStop(1, 'rgba(0,0,0,0)');

                            ctx.strokeStyle = grad;
                            ctx.lineWidth = m.size;
                            ctx.lineCap = 'round';
                            ctx.stroke();

                            // Kopf
                            ctx.beginPath();
                            ctx.arc(m.x, m.y, m.size, 0, Math.PI * 2);
                            ctx.fillStyle = `${m.color}${m.alpha + 0.2})`;
                            ctx.fill();
                        }

                        requestAnimationFrame(loop);
                    };
                    loop();
                },

                toggleMusic() {
                    const audio = document.getElementById('funki-bgm');
                    if (!audio) return;
                    if (this.isMusicPlaying) {
                        audio.pause();
                        this.isMusicPlaying = false;
                    } else {
                        audio.volume = 0.12;
                        audio.play().catch(() => {});
                        this.isMusicPlaying = true;
                    }
                },

                handleLevelUp(data) {
                    this.open3DModal();
                    setTimeout(() => {
                        this.evolutionFlash = true;
                        setTimeout(() => {
                            this.currentPath = data.newModelPath;
                            this.currentImagePath = data.newImagePath;
                            if (threeInitialized && window._funki3DLoader) {
                                window._funki3DLoader(this.currentPath, () => {
                                    this.evolutionFlash = false;
                                    setTimeout(() => {
                                        this.rewardMessage = data.reward;
                                        this.showConfetti = true;
                                        setTimeout(() => {
                                            this.showConfetti = false;
                                        }, 6000);
                                    }, 800);
                                });
                            }
                        }, 1000);
                    }, 400);
                },

                open3DModal() {
                    this.show3DModal = true;
                    setTimeout(() => {
                        if (!threeInitialized) this.initThreeJS();
                        else this.resizeThreeJS();

                        const activeTimelineItem = document.querySelector('.scale-110.ring-primary\\/20');
                        if (activeTimelineItem && activeTimelineItem.parentElement) {
                            activeTimelineItem.parentElement.parentElement.scrollLeft = activeTimelineItem.parentElement.offsetLeft - 40;
                        }
                    }, 100);
                },

                close3DModal() {
                    this.show3DModal = false;
                },

                initThreeJS() {
                    const container = document.getElementById('funki-3d-canvas-container');
                    if (!container || typeof window.THREE === 'undefined') return;

                    scene = new window.THREE.Scene();
                    camera = new window.THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 0.1, 1000);
                    renderer = new window.THREE.WebGLRenderer({ antialias: true, alpha: true });

                    renderer.setSize(container.offsetWidth, container.offsetHeight);
                    renderer.setPixelRatio(window.devicePixelRatio);
                    renderer.toneMapping = window.THREE.ACESFilmicToneMapping;
                    container.appendChild(renderer.domElement);

                    scene.add(new window.THREE.AmbientLight(0xffffff, 2.5));
                    const dirLight = new window.THREE.DirectionalLight(0xffffff, 2.0);
                    dirLight.position.set(5, 5, 5);
                    scene.add(dirLight);

                    controls = new window.OrbitControls(camera, renderer.domElement);
                    controls.enableDamping = true;
                    controls.minDistance = 1;
                    controls.maxDistance = 5;

                    window._funki3DLoader = (path, cb) => {
                        const loader = new window.GLTFLoader();
                        if (currentModel) scene.remove(currentModel);
                        loader.load(path, (gltf) => {
                            currentModel = gltf.scene;
                            const box = new window.THREE.Box3().setFromObject(currentModel);
                            const center = box.getCenter(new window.THREE.Vector3());
                            currentModel.position.sub(center);
                            currentModel.rotation.y = Math.PI / -2;
                            scene.add(currentModel);
                            camera.position.set(0, 0.8, 2.5);
                            if (cb) cb();
                        });
                    };

                    window._funki3DLoader(this.currentPath);

                    const animate = () => {
                        requestAnimationFrame(animate);
                        if (currentModel) currentModel.position.y = Math.sin(Date.now() * 0.002) * 0.05;
                        if (controls) controls.update();
                        if (renderer) renderer.render(scene, camera);
                    };
                    animate();
                    threeInitialized = true;

                    window.addEventListener('resize', () => this.resizeThreeJS());
                },

                resizeThreeJS() {
                    const container = document.getElementById('funki-3d-canvas-container');
                    if (!camera || !renderer) return;
                    camera.aspect = container.offsetWidth / container.offsetHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(container.offsetWidth, container.offsetHeight);
                }
            };
        };
    }

    // ==========================================
    // 3. DEEP SPACE & WARP ENGINE (Haupt-Hintergrund)
    // ==========================================
    window.goldDust = function() {
        return {
            init() {
                const canvas = document.getElementById('gold-dust-canvas');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');

                let width, height;
                let stars = [];
                let planets = [];

                // Warp-State
                let isWarping = false;
                let warpSpeedMultiplier = 0;
                let uiFadeOut = 1; // Blendet die Planeten aus

                window.startWarpSpeed = () => {
                    isWarping = true;
                };

                const config = {
                    starsCount: 300,
                    planetsCount: 3,
                    colors: {
                        gold: '197, 160, 89',
                        white: '255, 255, 255',
                        blue: '100, 150, 255',
                        copper: '217, 119, 83'
                    }
                };

                const resize = () => {
                    width = canvas.width = document.body.clientWidth;
                    height = canvas.height = window.innerHeight;
                };
                window.addEventListener('resize', resize);
                resize();

                const random = (min, max) => Math.random() * (max - min) + min;

                // Sterne init
                for (let i = 0; i < config.starsCount; i++) {
                    stars.push({
                        x: random(0, width), y: random(0, height),
                        z: random(0.1, 2), // Tiefe für den 3D-Warp-Effekt
                        r: random(0.2, 1.2),
                        baseAlpha: random(0.1, 0.6),
                        angle: random(0, Math.PI * 2),
                        speed: random(0.005, 0.02),
                        color: Math.random() > 0.8 ? config.colors.gold : config.colors.white
                    });
                }

                // Monde init
                for (let i = 0; i < config.planetsCount; i++) {
                    planets.push({
                        x: random(0, width), y: random(0, height),
                        r: random(200, 500),
                        vx: random(-0.01, 0.01), vy: random(-0.01, 0.01),
                        color: [config.colors.gold, config.colors.blue, config.colors.copper][Math.floor(Math.random() * 3)],
                        maxAlpha: random(0.02, 0.06)
                    });
                }

                const loop = () => {
                    ctx.clearRect(0, 0, width, height);
                    let cx = width / 2;
                    let cy = height / 2;

                    // Warp Beschleunigung
                    if (isWarping) {
                        warpSpeedMultiplier += 0.05;
                        if (warpSpeedMultiplier > 15) warpSpeedMultiplier = 15;
                        uiFadeOut -= 0.02; // Planeten verblassen lassen
                        if (uiFadeOut < 0) uiFadeOut = 0;
                    }

                    // Planeten zeichnen (verblassen beim Warp)
                    if (uiFadeOut > 0) {
                        planets.forEach(p => {
                            p.x += p.vx; p.y += p.vy;
                            if (p.x - p.r > width) p.x = -p.r; if (p.x + p.r < 0) p.x = width + p.r;
                            if (p.y - p.r > height) p.y = -p.r; if (p.y + p.r < 0) p.y = height + p.r;

                            let grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
                            grad.addColorStop(0, `rgba(${p.color}, ${p.maxAlpha * uiFadeOut})`);
                            grad.addColorStop(1, 'rgba(0,0,0,0)');
                            ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2); ctx.fillStyle = grad; ctx.fill();
                        });
                    }

                    // Sterne & Warp-Trails
                    stars.forEach(s => {
                        if (!isWarping) {
                            // Normales Funkeln
                            s.angle += s.speed;
                            let currentAlpha = s.baseAlpha + Math.sin(s.angle) * 0.3;
                            if (currentAlpha < 0) currentAlpha = 0;

                            // Sanfte Bewegung zur Seite
                            s.x -= s.z * 0.1;
                            if (s.x < 0) s.x = width;

                            ctx.beginPath();
                            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                            ctx.fillStyle = `rgba(${s.color}, ${currentAlpha})`;
                            ctx.fill();
                        } else {
                            // WARP SPEED!
                            let dx = s.x - cx;
                            let dy = s.y - cy;
                            let dist = Math.sqrt(dx*dx + dy*dy);
                            if (dist === 0) dist = 0.1;

                            // Beschleunigung weg vom Zentrum
                            let moveX = (dx / dist) * (warpSpeedMultiplier * s.z);
                            let moveY = (dy / dist) * (warpSpeedMultiplier * s.z);

                            s.x += moveX;
                            s.y += moveY;

                            // Schweif zeichnen
                            ctx.beginPath();
                            ctx.moveTo(s.x, s.y);
                            ctx.lineTo(s.x - moveX * 4, s.y - moveY * 4);
                            ctx.strokeStyle = `rgba(${s.color}, ${Math.min(1, s.baseAlpha + 0.5)})`;
                            ctx.lineWidth = s.r * (warpSpeedMultiplier * 0.2); // Wird dicker je schneller
                            ctx.lineCap = 'round';
                            ctx.stroke();

                            // Respawn in der Mitte, wenn sie den Rand verlassen
                            if (s.x < 0 || s.x > width || s.y < 0 || s.y > height) {
                                s.x = cx + (Math.random() - 0.5) * 50;
                                s.y = cy + (Math.random() - 0.5) * 50;
                                s.z = random(0.5, 3); // Neue Geschwindigkeit
                            }
                        }
                    });

                    requestAnimationFrame(loop);
                };
                loop();
            }
        };
    };
</script>
