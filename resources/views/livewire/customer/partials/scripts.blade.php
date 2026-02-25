<script>
    if (typeof window.optInScreen === 'undefined') {
        window.optInScreen = function() {
            return {
                mouseX: 0,
                mouseY: 0,
                isHovering: false,
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
                triggerEpicStart(e) {
                    this.isActivating = true;
                    if (window.spawnEpicExplosion) window.spawnEpicExplosion(e.clientX, e.clientY);

                    setTimeout(() => {
                        this.phase = 1;
                    }, 50);

                    setTimeout(() => {
                        this.flash = true;
                    }, 1500);

                    setTimeout(() => {
                        window.sessionStorage.setItem('funki_just_activated', 'true');
                    @this.call('optIn');
                    }, 1800);
                }
            };
        };
    }

    if (typeof window.funkiHub === 'undefined') {
        window.funkiHub = function(initialModelPath, initialImagePath) {
            let scene, camera, renderer, controls, currentModel;
            let sparksScene, sparksCamera, sparksRenderer, sparksParticles;
            let threeInitialized = false;

            return {
                startupFlash: false,
                evolutionFlash: false,
                showConfetti: false,
                rewardMessage: '',
                show3DModal: false,
                showTitlesModal: false,
                currentPath: initialModelPath,
                currentImagePath: initialImagePath,
                isMusicPlaying: true,

                initShop() {
                    if (window.sessionStorage.getItem('funki_just_activated')) {
                        this.startupFlash = true;
                        window.sessionStorage.removeItem('funki_just_activated');
                        setTimeout(() => { this.startupFlash = false; }, 500);
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

                initBackgroundSparks() {
                    const canvas = document.getElementById('funki-sparks-bg');
                    const container = document.getElementById('shop-header-container');
                    if (!canvas || typeof window.THREE === 'undefined') return;

                    sparksScene = new window.THREE.Scene();
                    sparksCamera = new window.THREE.PerspectiveCamera(60, canvas.offsetWidth / canvas.offsetHeight, 1, 1000);
                    sparksCamera.position.z = 200;

                    sparksRenderer = new window.THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
                    sparksRenderer.setSize(canvas.offsetWidth, canvas.offsetHeight);
                    sparksRenderer.setPixelRatio(window.devicePixelRatio);

                    const particleCount = 200;
                    const geometry = new window.THREE.BufferGeometry();
                    const positions = [];
                    const velocities = [];

                    for (let i = 0; i < particleCount; i++) {
                        positions.push(
                            (Math.random() - 0.5) * 800,
                            (Math.random() - 0.5) * 500,
                            (Math.random() - 0.5) * 300
                        );
                        velocities.push(Math.random() * 0.15 + 0.05);
                    }

                    geometry.setAttribute('position', new window.THREE.Float32BufferAttribute(positions, 3));
                    geometry.setAttribute('velocity', new window.THREE.Float32BufferAttribute(velocities, 1));

                    const material = new window.THREE.PointsMaterial({
                        size: 4,
                        transparent: true,
                        blending: window.THREE.AdditiveBlending,
                        opacity: 0.6,
                        color: 0xc5a059
                    });

                    sparksParticles = new window.THREE.Points(geometry, material);
                    sparksScene.add(sparksParticles);

                    const animateSparks = () => {
                        requestAnimationFrame(animateSparks);
                        const pos = sparksParticles.geometry.attributes.position.array;
                        const vel = sparksParticles.geometry.attributes.velocity.array;

                        for (let i = 0; i < particleCount; i++) {
                            const i3 = i * 3;
                            pos[i3 + 1] += vel[i];
                            pos[i3] += Math.sin(Date.now() * 0.0005 + i) * 0.05;

                            if (pos[i3 + 1] > 250) {
                                pos[i3 + 1] = -250;
                            }
                        }

                        sparksParticles.geometry.attributes.position.needsUpdate = true;
                        sparksRenderer.render(sparksScene, sparksCamera);
                    };

                    animateSparks();

                    window.addEventListener('resize', () => {
                        if (!sparksCamera || !sparksRenderer) return;
                        sparksCamera.aspect = canvas.offsetWidth / canvas.offsetHeight;
                        sparksCamera.updateProjectionMatrix();
                        sparksRenderer.setSize(canvas.offsetWidth, canvas.offsetHeight);
                    });
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

    window.tiltCard = function() {
        return {
            tiltStyle: '',
            handleMouse(e) {
                const rect = this.$el.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width - 0.5) * 15;
                const y = ((e.clientY - rect.top) / rect.height - 0.5) * -15;
                this.tiltStyle = `transform: perspective(1000px) rotateX(${y}deg) rotateY(${x}deg)`;
            },
            resetMouse() {
                this.tiltStyle = '';
            }
        };
    };

    window.goldDust = function() {
        return {
            showProfileModal: false,
            activeProfileTab: 'profile',
            showProfileSuccess: false,
            showPasswordSuccess: false,
            maxMeteors: 60, // Die ruhigen Meteore im Hintergrund

            init() {
                const canvas = document.getElementById('gold-dust-canvas');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                let particles = [];
                let meteors = [];

                const resize = () => {
                    // Verhindert Ausbrechen auf Mobile
                    canvas.width = document.body.clientWidth;
                    canvas.height = window.innerHeight;
                };
                window.addEventListener('resize', resize);
                resize();

                // Funktion zum Erstellen eines langsamen Meteoriten (Hintergrund-Effekt)
                const spawnMeteor = (initialSpawn = false) => {
                    const goRight = Math.random() > 0.5;
                    let vx = (Math.random() * 0.1 + 0.05);
                    if (!goRight) vx = -vx;
                    let vy = (Math.random() - 0.5) * 0.05;

                    meteors.push({
                        x: initialSpawn ? (Math.random() * canvas.width) : (goRight ? -50 : canvas.width + 50),
                        y: Math.random() * canvas.height,
                        vx: vx,
                        vy: vy,
                        size: Math.random() * 2.5 + 1.5,
                        alpha: Math.random() * 0.3 + 0.1,
                        tailLength: Math.random() * 60 + 30
                    });
                };

                for (let i = 0; i < this.maxMeteors; i++) {
                    spawnMeteor(true);
                }

                // Interaktive Funken (für Hover Effekte)
                window.spawnSparks = (e) => {
                    for (let i = 0; i < 12; i++) {
                        particles.push({
                            x: e.clientX,
                            y: e.clientY,
                            vx: (Math.random() - 0.5) * 5,
                            vy: (Math.random() - 0.5) * 5,
                            life: 1,
                            decay: 0.03,
                            size: Math.random() * 3 + 1,
                            color: '212, 175, 55'
                        });
                    }
                };

                // Explosionseffekt (für das Upgrade)
                window.spawnEpicExplosion = (x, y) => {
                    for (let i = 0; i < 300; i++) {
                        let angle = Math.random() * Math.PI * 2;
                        let speed = Math.random() * 12 + 2;
                        particles.push({
                            x: x,
                            y: y,
                            vx: Math.cos(angle) * speed,
                            vy: Math.sin(angle) * speed,
                            life: 1,
                            decay: Math.random() * 0.01 + 0.005,
                            size: Math.random() * 5 + 1,
                            color: Math.random() > 0.2 ? '197, 160, 89' : '255, 255, 255'
                        });
                    }
                };

                // Kleine Funken bei allgemeiner Mausbewegung
                window.addEventListener('mousemove', (e) => {
                    if (Math.random() < 0.3) {
                        particles.push({
                            x: e.clientX,
                            y: e.clientY,
                            vx: (Math.random() - 0.5) * 0.5,
                            vy: Math.random() * 0.5 + 0.2,
                            life: 1,
                            decay: 0.01,
                            size: Math.random() * 2 + 0.5,
                            color: '197, 160, 89'
                        });
                    }
                });

                const loop = () => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);

                    // 1. Die Meteoriten im Hintergrund zeichnen
                    for (let i = meteors.length - 1; i >= 0; i--) {
                        let m = meteors[i];

                        m.x += m.vx;
                        m.y += m.vy;

                        if ((m.vx > 0 && m.x > canvas.width + 100) ||
                            (m.vx < 0 && m.x < -100) ||
                            m.y > canvas.height + 100 ||
                            m.y < -100) {
                            meteors.splice(i, 1);
                            spawnMeteor();
                            continue;
                        }

                        ctx.beginPath();
                        ctx.moveTo(m.x, m.y);
                        ctx.lineTo(m.x - (m.vx * m.tailLength), m.y - (m.vy * m.tailLength));
                        ctx.strokeStyle = `rgba(197, 160, 89, ${m.alpha * 0.4})`;
                        ctx.lineWidth = m.size * 0.8;
                        ctx.stroke();

                        ctx.beginPath();
                        ctx.arc(m.x, m.y, m.size, 0, Math.PI * 2);
                        ctx.fillStyle = `rgba(212, 175, 55, ${m.alpha + 0.2})`;
                        ctx.shadowBlur = m.size * 4;
                        ctx.shadowColor = `rgba(197, 160, 89, ${m.alpha})`;
                        ctx.fill();
                    }

                    // 2. Die interaktiven Funken (im Vordergrund)
                    for (let i = particles.length - 1; i >= 0; i--) {
                        let p = particles[i];
                        p.x += p.vx;
                        p.y += p.vy;
                        p.life -= p.decay;

                        if (p.life <= 0) {
                            particles.splice(i, 1);
                            continue;
                        }

                        ctx.beginPath();
                        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                        ctx.fillStyle = `rgba(${p.color}, ${p.life})`;
                        ctx.shadowBlur = p.size * 3;
                        ctx.shadowColor = `rgba(${p.color}, ${p.life})`;
                        ctx.fill();
                    }

                    requestAnimationFrame(loop);
                };
                loop();
            }
        };
    };
</script>
