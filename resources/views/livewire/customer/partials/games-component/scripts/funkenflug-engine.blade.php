window.FunkenflugEngine = class FunkenflugEngine {
    constructor(container, layer, callbacks, assets) {
        this.container = container;
        this.layer = layer;
        this.callbacks = callbacks;
        this.assets = assets;

        // Constants & State
        this.isRunning = false;
        this.isGameOver = false;
        this.distance = 0;
        this.scoreMultiplier = 1;
        this.baseSpeed = 10; // units per second
        this.currentSpeed = this.baseSpeed;
        this.timeScale = 1.0;
        this.shieldHP = 100;

        // Input state
        this.keys = { w:false, a:false, s:false, d:false };
        this.isPointerDown = false;
        this.pointerPos = new THREE.Vector3(0, 0, 0);

        // Pre-allocated Scratch Math Objects (Zero-GC in Game Loop & Touch Handlers)
        this._rayVec = new THREE.Vector3();
        this._rayDir = new THREE.Vector3();
        this._z0Plane = new THREE.Plane(new THREE.Vector3(0, 0, 1), 0);
        this._ray = new THREE.Ray();
        this._intersectTarget = new THREE.Vector3();
        this._bulletDefaultDir = new THREE.Vector3(0, 1, 0);
        this._tempVec = new THREE.Vector3();
        this._droneDir = new THREE.Vector3();
        this.canvasRect = null;

        // Real-time FPS & Frame-Time Telemetry
        this.fps = 60;
        this.frameTimeMs = 16.67;
        this.minFps = 60;
        this.maxFrameTimeMs = 0;
        this.framesRendered = 0;
        this._fpsAccumulator = 0;
        this._fpsFrames = 0;
        this.loadedModels = { rocket: false, meteor: false, sharp_stone: false };
        window.__funkenflugEngine = this;

        // Pools
        this.pools = {
            bullets: [],
            enemies: [],
            particles: [],
            collectibles: [],
            drones: [],
            lasers: []
        };

        // Active Entities
        this.bullets = [];
        this.enemies = [];
        this.particles = [];
        this.collectibles = [];
        this.drones = [];

        // Player Ship
        this.ship = null;
        this.shipModel = null;
        this.shipBounds = 1.0; // Collision radius
        this.shipVelocity = new THREE.Vector3();
        this.shipTargetPos = new THREE.Vector3();

        // Skill States
        this.activeSkills = {
            multishoot: { active: false, timer: 0 },
            teleport: { waitingForClick: false },
            shield: { active: false, timer: 0, mesh: null },
            ultimate: { active: false, timer: 0 }
        };

        // Timers
        this.lastFireTime = 0;
        this.enemySpawnTimer = 0;
        this.collectibleSpawnTimer = 0;

        // Visuals
        this.starfield = null;

        // Audio
        this.audio = new window.ArcadeAudio();
        this.tpAudio = new Audio('{{ asset("shop/customer/gamification/sounds/teleport_active.mp3") }}');
        this.tpAudio.preload = 'auto';

        this.initScene();
        this.loadAssets();
        this.setupInteractions();
    }

    updateCanvasRect() {
        if (this.renderer && this.renderer.domElement) {
            this.canvasRect = this.renderer.domElement.getBoundingClientRect();
        }
    }

    resize() {
        if (!this.container || !this.camera || !this.renderer) return;
        const w = this.container.offsetWidth;
        const h = this.container.offsetHeight;
        if (w === 0 || h === 0) return; // Prevent NaN/Infinity aspect corruption
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(w, h, false);
        this.updateCanvasRect();
        
        // Update bounds asynchronously to ensure layout has settled
        if (this._boundsTimeout) clearTimeout(this._boundsTimeout);
        this._boundsTimeout = setTimeout(() => {
            this.updateCanvasRect();
            this.updateBounds();
        }, 60);
    }

    updateBounds() {
        if (!this.camera || !this.renderer) return;
        this.camera.updateProjectionMatrix();

        // The game action happens at Z=0 plane
        const z0plane = new window.THREE.Plane(new window.THREE.Vector3(0, 0, 1), 0);
        
        const getIntersect = (nx, ny) => {
            const vec = new window.THREE.Vector3(nx, ny, 0.5);
            vec.unproject(this.camera);
            const dir = vec.sub(this.camera.position).normalize();
            const ray = new window.THREE.Ray(this.camera.position, dir);
            const target = new window.THREE.Vector3();
            const hit = ray.intersectPlane(z0plane, target);
            if (!hit) {
                // If ray points away from Z=0 (e.g. extreme aspect ratio wide corners), 
                // project a point very far away in that direction as a safe boundary
                return new window.THREE.Vector3(nx * 1000, ny * 1000, 0);
            }
            return target;
        };

        // Y bounds only depend on vertical edges (safest in center to avoid wide FOV distortion)
        const topCenter = getIntersect(0, 1);
        const bottomCenter = getIntersect(0, -1);
        
        // X bounds: use the narrowest part of the frustum (the bottom)
        const bottomLeft = getIntersect(-1, -1);
        const bottomRight = getIntersect(1, -1);

        this.bounds = {
            xMin: bottomLeft.x + 0.5,
            xMax: bottomRight.x - 0.5,
            yMin: bottomCenter.y + 1.2,
            yMax: topCenter.y - 1.5
        };

        // Failsafe: If layout hasn't settled and bounds invert, use safe fallbacks
        if (isNaN(this.bounds.yMin) || isNaN(this.bounds.yMax) || this.bounds.yMax <= this.bounds.yMin) {
            this.bounds.yMin = -8;
            this.bounds.yMax = 28;
        }
        if (isNaN(this.bounds.xMin) || isNaN(this.bounds.xMax) || this.bounds.xMax <= this.bounds.xMin) {
            this.bounds.xMin = -15;
            this.bounds.xMax = 15;
        }
    }

    _createStarSpriteTexture() {
        const canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 64;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            // Soft circular halo
            const grad = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
            grad.addColorStop(0.0, 'rgba(255, 255, 255, 1.0)');
            grad.addColorStop(0.18, 'rgba(240, 248, 255, 0.92)');
            grad.addColorStop(0.42, 'rgba(165, 210, 255, 0.35)');
            grad.addColorStop(0.75, 'rgba(90, 150, 255, 0.08)');
            grad.addColorStop(1.0, 'rgba(0, 0, 0, 0.0)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, 64, 64);

            // Subtle stellar diffraction cross
            ctx.fillStyle = 'rgba(255, 255, 255, 0.45)';
            ctx.fillRect(31, 8, 2, 48);
            ctx.fillRect(8, 31, 48, 2);
        }
        const tex = new THREE.CanvasTexture(canvas);
        tex.needsUpdate = true;
        return tex;
    }

    _createNebulaTexture(palette) {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 256;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.clearRect(0, 0, 256, 256);
            palette.forEach(blob => {
                const grad = ctx.createRadialGradient(blob.x, blob.y, 0, blob.x, blob.y, blob.r);
                grad.addColorStop(0.0, blob.c0);
                grad.addColorStop(0.45, blob.c1);
                grad.addColorStop(1.0, 'rgba(0, 0, 0, 0)');
                ctx.fillStyle = grad;
                ctx.beginPath();
                ctx.arc(blob.x, blob.y, blob.r, 0, Math.PI * 2);
                ctx.fill();
            });
        }
        const tex = new THREE.CanvasTexture(canvas);
        tex.needsUpdate = true;
        return tex;
    }

    initScene() {
        this.scene = new THREE.Scene();
        this.isMobile = window.innerWidth <= 768;

        if (!this.isMobile) {
            this.scene.fog = new THREE.FogExp2(0x030712, 0.008);
        }

        const w = this.container.offsetWidth || 800;
        const h = this.container.offsetHeight || 800;

        // Camera looking slightly down
        this.camera = new THREE.PerspectiveCamera(60, w / h, 0.1, 1000);
        this.camera.position.set(0, -15, 30);
        this.camera.lookAt(0, 5, 0);

        // Turn off antialiasing on mobile for huge performance gain, use high-performance GPU power preference
        this.renderer = new THREE.WebGLRenderer({
            antialias: !this.isMobile,
            alpha: false,
            stencil: false,
            powerPreference: 'high-performance'
        });
        this.renderer.setSize(w, h, false);

        const pixelRatio = this.isMobile ? Math.min(window.devicePixelRatio || 1, 1.5) : Math.min(window.devicePixelRatio || 1, 1.5);
        this.renderer.setPixelRatio(pixelRatio);
        this.renderer.setClearColor(0x030712, 1);

        this.renderer.domElement.style.position = 'absolute';
        this.renderer.domElement.style.width = '100%';
        this.renderer.domElement.style.height = '100%';

        this.container.appendChild(this.renderer.domElement);

        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
        this.scene.add(ambientLight);

        const dirLight = new THREE.DirectionalLight(0xffffff, 1.5);
        dirLight.position.set(10, -10, 20);
        this.scene.add(dirLight);

        // --- REALISTIC MULTI-LAYER COSMOS & PARALLAX STARFIELD ---
        const starTex = this._createStarSpriteTexture();
        const spectralPalette = [
            new THREE.Color(0xffffff), // Crisp White (A-Type)
            new THREE.Color(0x93c5fd), // Icy Blue-White (O/B-Type)
            new THREE.Color(0x38bdf8), // Cyan Giant
            new THREE.Color(0xfde68a), // Solar Gold (G-Type)
            new THREE.Color(0xfbbf24), // Warm Amber (K-Type)
            new THREE.Color(0xc4b5fd), // Soft Violet Stardust
            new THREE.Color(0xfda4af)  // Distant Red-Rose Giant (M-Type)
        ];

        const buildStarLayer = (count, size, zMin, zMax, opacity, speedMult) => {
            const geom = new THREE.BufferGeometry();
            const verts = new Float32Array(count * 3);
            const colors = new Float32Array(count * 3);
            const spanY = 140;
            const spanX = 95;

            for (let i = 0; i < count; i++) {
                verts[i * 3] = (Math.random() - 0.5) * spanX;
                verts[i * 3 + 1] = (Math.random() - 0.5) * spanY;
                verts[i * 3 + 2] = zMin + Math.random() * (zMax - zMin);

                const col = spectralPalette[Math.floor(Math.random() * spectralPalette.length)];
                const brightness = 0.65 + Math.random() * 0.35;
                colors[i * 3] = col.r * brightness;
                colors[i * 3 + 1] = col.g * brightness;
                colors[i * 3 + 2] = col.b * brightness;
            }
            geom.setAttribute('position', new THREE.BufferAttribute(verts, 3));
            geom.setAttribute('color', new THREE.BufferAttribute(colors, 3));

            const mat = new THREE.PointsMaterial({
                size: size,
                map: starTex,
                vertexColors: true,
                transparent: true,
                opacity: opacity,
                depthWrite: false,
                blending: THREE.AdditiveBlending
            });

            const group = new THREE.Group();
            const pts1 = new THREE.Points(geom, mat);
            const pts2 = new THREE.Points(geom, mat);
            pts1.frustumCulled = false;
            pts2.frustumCulled = false;
            pts2.position.y = spanY;
            group.add(pts1, pts2);
            group.userData = { speedMult: speedMult, spanY: spanY };
            this.scene.add(group);
            return group;
        };

        this.starLayers = [
            // 1. Deep Space Stardust (slow parallax)
            buildStarLayer(this.isMobile ? 280 : 650, this.isMobile ? 0.9 : 0.75, -34, -20, 0.75, 0.22),
            // 2. Mid-Space Spectral Stars & Giants (medium parallax)
            buildStarLayer(this.isMobile ? 150 : 340, this.isMobile ? 1.55 : 1.35, -18, -8, 0.92, 0.52),
            // 3. Foreground Bright Warp Stars (fast parallax)
            buildStarLayer(this.isMobile ? 55 : 110, this.isMobile ? 2.1 : 1.85, -6, 1, 0.98, 1.12)
        ];
        this.starfieldGroup = this.starLayers[1];

        // --- COSMIC NEBULAE & GALAXY CLOUDS ---
        const nebTexViolet = this._createNebulaTexture([
            { x: 115, y: 125, r: 115, c0: 'rgba(139, 92, 246, 0.42)', c1: 'rgba(76, 29, 149, 0.16)' },
            { x: 155, y: 105, r: 90,  c0: 'rgba(236, 72, 153, 0.30)', c1: 'rgba(131, 24, 67, 0.10)' },
            { x: 95,  y: 150, r: 80,  c0: 'rgba(56, 189, 248, 0.25)', c1: 'rgba(12, 74, 110, 0.08)' }
        ]);
        const nebTexCyanGold = this._createNebulaTexture([
            { x: 130, y: 130, r: 115, c0: 'rgba(14, 165, 233, 0.36)', c1: 'rgba(15, 23, 42, 0.12)' },
            { x: 105, y: 110, r: 85,  c0: 'rgba(245, 158, 11, 0.28)', c1: 'rgba(120, 53, 15, 0.08)' },
            { x: 160, y: 150, r: 75,  c0: 'rgba(45, 212, 191, 0.24)', c1: 'rgba(19, 78, 74, 0.06)' }
        ]);
        const nebTexGalaxy = this._createNebulaTexture([
            { x: 128, y: 128, r: 110, c0: 'rgba(99, 102, 241, 0.38)', c1: 'rgba(30, 27, 75, 0.14)' },
            { x: 128, y: 128, r: 48,  c0: 'rgba(224, 242, 254, 0.50)', c1: 'rgba(56, 189, 248, 0.18)' }
        ]);

        this.nebulaGroup = new THREE.Group();
        const nebSpanY = 160;
        const nebConfigs = [
            { tex: nebTexViolet,   x: -14, y: -15, z: -32, s: 58, rot: 0.4 },
            { tex: nebTexCyanGold, x:  15, y:  28, z: -30, s: 54, rot: -0.6 },
            { tex: nebTexGalaxy,   x:  -6, y:  62, z: -34, s: 46, rot: 1.1 },
            { tex: nebTexViolet,   x:  12, y: -55, z: -33, s: 50, rot: 2.2 }
        ];

        const buildNebulaTile = (offsetY) => {
            const tile = new THREE.Group();
            tile.position.y = offsetY;
            const planeGeo = new THREE.PlaneGeometry(1, 1);
            nebConfigs.forEach(cfg => {
                const mat = new THREE.MeshBasicMaterial({
                    map: cfg.tex,
                    transparent: true,
                    opacity: 0.85,
                    depthWrite: false,
                    blending: THREE.AdditiveBlending
                });
                const mesh = new THREE.Mesh(planeGeo, mat);
                mesh.position.set(cfg.x, cfg.y, cfg.z);
                mesh.scale.set(cfg.s, cfg.s, 1);
                mesh.rotation.z = cfg.rot;
                mesh.frustumCulled = false;
                tile.add(mesh);
            });
            return tile;
        };

        this.nebulaGroup.add(buildNebulaTile(0), buildNebulaTile(nebSpanY));
        this.nebulaGroup.userData = { spanY: nebSpanY };
        this.scene.add(this.nebulaGroup);

        // Window resize
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.container);
        this.resize();
    }

    loadAssets() {
        // Player Ship Container
        this.ship = new THREE.Group();
        this.scene.add(this.ship);

        // Fallback geometry
        const fbGeom = new THREE.ConeGeometry(1, 3, 8);
        fbGeom.rotateX(Math.PI / 2); // Point upwards
        const fbMat = this.isMobile ? 
            new THREE.MeshLambertMaterial({ color: 0x3b82f6 }) : 
            new THREE.MeshStandardMaterial({ color: 0x3b82f6, metalness: 0.8, roughness: 0.2 });
        this.shipModel = new THREE.Mesh(fbGeom, fbMat);
        this.ship.add(this.shipModel);

        // --- KONFIGURATION FÜR 3D MODELL ---
        // Hier können alle Werte für das 3D Spieler-Modell (Rakete) angepasst werden.
        this.modelConfig = {
            position: { x: 0, y: 0, z: 0 },
            rotation: { x: 45, y: -90, z: 45 },
            scale: { x: 6.0, y: 6.0, z: 6.0 }
        };

        // Try GLTFLoader if available
        const GltfLoaderClass = window.GLTFLoader || (window.THREE && window.THREE.GLTFLoader);
        if (GltfLoaderClass) {
            this.loadingManager = new THREE.LoadingManager();
            this.loadingManager.onProgress = (url, itemsLoaded, itemsTotal) => {
                if (this.callbacks.onLoadProgress) this.callbacks.onLoadProgress((itemsLoaded / itemsTotal) * 100);
            };
            this.loadingManager.onLoad = () => {
                if (this.callbacks.onLoadComplete) this.callbacks.onLoadComplete();
            };

            const loader = new GltfLoaderClass(this.loadingManager);
            loader.load(this.assets.rocket, (gltf) => {
                this.ship.remove(this.shipModel);
                this.shipModel = gltf.scene;

                // Modell anhand der Konfiguration ausrichten (Grad in Bogenmaß konvertieren)
                this.shipModel.position.set(this.modelConfig.position.x, this.modelConfig.position.y, this.modelConfig.position.z);
                this.shipModel.rotation.set(
                    this.modelConfig.rotation.x * (Math.PI / 180),
                    this.modelConfig.rotation.y * (Math.PI / 180),
                    this.modelConfig.rotation.z * (Math.PI / 180)
                );
                this.shipModel.scale.set(this.modelConfig.scale.x, this.modelConfig.scale.y, this.modelConfig.scale.z);

                // Add emissive engine glow to materials & optimize texture sampling
                this.shipModel.traverse((child) => {
                    if (child.isMesh) {
                        child.castShadow = false;
                        child.receiveShadow = false;
                        child.frustumCulled = false;
                        if(child.material) {
                            const oldMat = child.material;
                            if (oldMat.map) {
                                oldMat.map.anisotropy = 1;
                                oldMat.map.generateMipmaps = true;
                            }
                            child.material = new THREE.MeshBasicMaterial({ color: oldMat.color, map: oldMat.map, toneMapped: false });
                        }
                    }
                });

                this.ship.add(this.shipModel);
                this.loadedModels.rocket = true;
                if (this.renderer) this.renderer.compile(this.scene, this.camera);
            }, undefined, (e) => { console.warn("Could not load rocket GLTF, using fallback."); });

            // Load Meteor GLB (Optimized ~335KB / 4.9k tris — enabled on both Mobile & Desktop!)
            this.meteorModel = null;
            if (this.assets.meteor) {
                loader.load(this.assets.meteor, (gltf) => {
                    this.meteorModel = gltf.scene;
                    this.meteorModel.scale.set(1.5, 1.5, 1.5);
                    let sharedMeteorMat = null;
                    this.meteorModel.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = false;
                            child.receiveShadow = false;
                            child.frustumCulled = false;
                            if (child.material) {
                                if (!sharedMeteorMat) {
                                    const oldMat = child.material;
                                    if (oldMat.map) {
                                        oldMat.map.anisotropy = 1;
                                    }
                                    sharedMeteorMat = new THREE.MeshBasicMaterial({ color: oldMat.color, map: oldMat.map, toneMapped: false });
                                }
                                child.material = sharedMeteorMat;
                            }
                        }
                    });
                    
                    // Clone into all pool items AND any currently active enemies
                    const allEnemyContainers = [...this.pools.enemies, ...this.enemies];
                    allEnemyContainers.forEach(m => {
                        if(m && m.userData && !m.userData.meshGlbMeteor) {
                            let clone = this.meteorModel.clone();
                            clone.visible = (m.visible && m.userData.type === 0);
                            if (clone.visible && m.userData.fallbackNormal) {
                                m.userData.fallbackNormal.visible = false;
                                m.userData.activeMesh = clone;
                            }
                            m.userData.meshGlbMeteor = clone;
                            m.add(clone);
                        }
                    });
                    this.loadedModels.meteor = true;
                    if (this.renderer) this.renderer.compile(this.scene, this.camera);
                }, undefined, (e) => { console.warn("Could not load meteor GLTF."); });
            }

            // Load Sharp Stone GLB (Optimized ~258KB / 4.9k tris — enabled on both Mobile & Desktop!)
            this.sharpstoneModel = null;
            if (this.assets.sharp_stone) {
                loader.load(this.assets.sharp_stone, (gltf) => {
                    this.sharpstoneModel = gltf.scene;
                    this.sharpstoneModel.scale.set(1.5, 1.5, 1.5);
                    let sharedSharpMat = null;
                    this.sharpstoneModel.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = false;
                            child.receiveShadow = false;
                            child.frustumCulled = false;
                            if (child.material) {
                                if (!sharedSharpMat) {
                                    const oldMat = child.material;
                                    if (oldMat.map) {
                                        oldMat.map.anisotropy = 1;
                                    }
                                    sharedSharpMat = new THREE.MeshBasicMaterial({ color: oldMat.color, map: oldMat.map, toneMapped: false });
                                }
                                child.material = sharedSharpMat;
                            }
                        }
                    });
                    
                    // Clone into all pool items AND any currently active enemies
                    const allEnemyContainers = [...this.pools.enemies, ...this.enemies];
                    allEnemyContainers.forEach(m => {
                        if(m && m.userData && !m.userData.meshGlbSharp) {
                            let clone = this.sharpstoneModel.clone();
                            clone.visible = (m.visible && m.userData.type === 1);
                            if (clone.visible && m.userData.fallbackElite) {
                                m.userData.fallbackElite.visible = false;
                                m.userData.activeMesh = clone;
                            }
                            m.userData.meshGlbSharp = clone;
                            m.add(clone);
                        }
                    });
                    this.loadedModels.sharp_stone = true;
                    if (this.renderer) this.renderer.compile(this.scene, this.camera);
                }, undefined, (e) => { console.warn("Could not load sharp stone GLTF."); });
            }
        } else {
            setTimeout(() => { if (this.callbacks.onLoadComplete) this.callbacks.onLoadComplete(); }, 100);
        }

        // Initialize materials for pools
        this.matBullet = new THREE.MeshBasicMaterial({ color: 0x38bdf8 }); // Light blue laser
        this.geomBullet = new THREE.CylinderGeometry(0.15, 0.15, 1.2, this.isMobile ? 4 : 8);

        this.matEnemy = this.isMobile ? [
            new THREE.MeshBasicMaterial({ color: 0xef4444 }), // Red Meteor
            new THREE.MeshBasicMaterial({ color: 0x8b5cf6 }), // Purple Crystal
            new THREE.MeshBasicMaterial({ color: 0xf97316 }) // Orange WallWall
        ] : [
            new THREE.MeshStandardMaterial({ color: 0xef4444, metalness: 0.6 }), // Red Meteor
            new THREE.MeshStandardMaterial({ color: 0x8b5cf6, metalness: 0.2 }), // Purple Crystal
            new THREE.MeshStandardMaterial({ color: 0xf97316, metalness: 0.8, roughness: 0.2 }) // Orange Wall
        ];
        this.geomEnemy = [
            // Details auf Mobile drastisch reduzieren (Detail level 0 statt 1)
            new THREE.DodecahedronGeometry(1.5, this.isMobile ? 0 : 1),
            new THREE.OctahedronGeometry(1.2, 0),
            new THREE.BoxGeometry(32, 1.5, 2)
        ];

        this.matCollect = [
            new THREE.MeshBasicMaterial({ color: 0xfde047 }), // Funken (Yellow)
            new THREE.MeshBasicMaterial({ color: 0x60a5fa })  // Cooldown (Blue)
        ];
        // Kugel rund auf Desktop, eckiger auf Mobile
        this.geomCollect = new THREE.SphereGeometry(0.6, this.isMobile ? 8 : 16, this.isMobile ? 8 : 16);

        // Pool materials for particles to prevent recreating material objects 100 times
        this.matParticleOrange = new THREE.MeshBasicMaterial({ color: 0xf97316 });
        this.matParticleYellow = new THREE.MeshBasicMaterial({ color: 0xfef08a });
        this.matParticleBlue   = new THREE.MeshBasicMaterial({ color: 0x60a5fa });
        this.matParticleWhite  = new THREE.MeshBasicMaterial({ color: 0xffffff });
        this.matParticleGold   = new THREE.MeshBasicMaterial({ color: 0xfde047 });
        this.matParticlePurple = new THREE.MeshBasicMaterial({ color: 0xc084fc });
        this.geomParticle      = new THREE.TetrahedronGeometry(0.3);

        // Populate pools - Weniger Instanzen auf Mobile um RAM zu schonen
        const bulletCount = 100;
        const enemyCount = this.isMobile ? 15 : 30;
        const collectCount = this.isMobile ? 10 : 20;
        const particleCount = this.isMobile ? 40 : 100;

        for(let i=0; i<bulletCount; i++) this.pools.bullets.push(this.createBulletMesh());
        for(let i=0; i<enemyCount; i++) this.pools.enemies.push(this.createEnemyMesh(i));
        for(let i=0; i<collectCount; i++) this.pools.collectibles.push(this.createCollectMesh());
        for(let i=0; i<particleCount; i++) this.pools.particles.push(this.createParticleMesh());
    }

    // --- POOL FACTORIES ---
    createBulletMesh() {
        if (!this.matBulletGold) {
            this.matBulletGold = new THREE.MeshBasicMaterial({ color: 0xfde047 });
        }
        const m = new THREE.Mesh(this.geomBullet, this.matBullet);
        m.frustumCulled = false;
        m.userData = { dir: new THREE.Vector3(0, 1, 0) };
        m.visible = false; this.scene.add(m); return m;
    }
    createEnemyMesh(index) {
        // Pool stores generic GROUPs now, so we can swap in GLTF or fallback Mesh easily
        const container = new THREE.Group();
        
        // Setup fallbacks
        const fallbackNormal = new THREE.Mesh(this.geomEnemy[0], this.matEnemy[0]);
        const fallbackElite = new THREE.Mesh(this.geomEnemy[1], this.matEnemy[1]);
        const fallbackWall = new THREE.Mesh(this.geomEnemy[2], this.matEnemy[2]);
        
        container.add(fallbackNormal);
        container.add(fallbackElite);
        container.add(fallbackWall);
        
        fallbackNormal.visible = false;
        fallbackElite.visible = false;
        fallbackWall.visible = false;
        
        container.visible = false; 
        
        // Wir speichern die Fallbacks und GLBs im userData, um später nur noch `visible` zu toggeln
        container.userData = { 
            type: 0, 
            hp: 0, 
            radius: 1.5, 
            isWall: false, 
            fallbackNormal: fallbackNormal,
            fallbackElite: fallbackElite,
            fallbackWall: fallbackWall,
            meshGlbMeteor: null,
            meshGlbSharp: null
        };
        
        this.scene.add(container); 
        return container;
    }
    createCollectMesh() {
        const m = new THREE.Mesh(this.geomCollect, this.matCollect[0]);
        m.frustumCulled = false;
        m.visible = false; m.userData = { type: 0, radius: 0.6 };
        this.scene.add(m); return m;
    }
    createParticleMesh() {
        const m = new THREE.Mesh(this.geomParticle, this.matParticleWhite);
        m.frustumCulled = false;
        m.visible = false; m.userData = { vel: new THREE.Vector3(), life: 0 };
        this.scene.add(m); return m;
    }

    setupInteractions() {
        const km = (e, state) => {
            // Hotkeys for skills (WASD or 1-4)
            if(state) {
                let skillIndex = 0;
                if(e.key?.toLowerCase() === 'w' || e.key === '1') skillIndex = 1;
                if(e.key?.toLowerCase() === 'a' || e.key === '2') skillIndex = 2;
                if(e.key?.toLowerCase() === 's' || e.key === '3') skillIndex = 3;
                if(e.key?.toLowerCase() === 'd' || e.key === '4') skillIndex = 4;

                if(skillIndex > 0 && window.alpineComponentContext) {
                    window.alpineComponentContext.useSkill(skillIndex);
                }
            }
        };
        this._kd = e => km(e, true); window.addEventListener('keydown', this._kd);
        this._ku = e => km(e, false); window.addEventListener('keyup', this._ku);
        this._onScroll = () => this.updateCanvasRect();
        window.addEventListener('scroll', this._onScroll, { passive: true });

        this.activeTouchId = null;

        // POINTER EVENTS (Desktop Mouse & Pen)
        this._onPointerMove = (e) => {
            if (!this.isRunning) return;
            if (e.pointerType === 'mouse' || e.pointerType === 'pen') {
                this.updateScreenCoords(e.clientX, e.clientY);
            }
        };
        this._onPointerDown = (e) => {
            if (!this.isRunning) return;
            if (e.pointerType === 'mouse' || e.pointerType === 'pen') {
                this.updateCanvasRect();
                this.updateScreenCoords(e.clientX, e.clientY);
                if (this.activeSkills.teleport.waitingForClick) {
                    this.executeTeleport();
                }
            }
        };

        // TOUCH EVENTS (Mobile Multi-Touch — Passive for Zero Compositor Lag)
        this._onTouchStart = (e) => {
            if (!this.isRunning) return;
            if (e.target && e.target.tagName === 'BUTTON') return; 
            
            if (this.activeTouchId === null && e.changedTouches.length > 0) {
                this.updateCanvasRect();
                this.activeTouchId = e.changedTouches[0].identifier;
                this.updateScreenCoords(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
                
                if (this.activeSkills.teleport.waitingForClick) {
                    this.executeTeleport();
                }
            }
        };
        this._onTouchMove = (e) => {
            if (!this.isRunning || this.activeTouchId === null) return;
            for(let i=0; i<e.changedTouches.length; i++) {
                if (e.changedTouches[i].identifier === this.activeTouchId) {
                    this.updateScreenCoords(e.changedTouches[i].clientX, e.changedTouches[i].clientY);
                    break;
                }
            }
        };
        this._onTouchEnd = (e) => {
            for(let i=0; i<e.changedTouches.length; i++) {
                if (e.changedTouches[i].identifier === this.activeTouchId) {
                    this.activeTouchId = null;
                    break;
                }
            }
        };

        this.container.addEventListener('pointerdown', this._onPointerDown, { passive: true });
        window.addEventListener('pointermove', this._onPointerMove, { passive: true });
        
        this.container.addEventListener('touchstart', this._onTouchStart, { passive: true });
        window.addEventListener('touchmove', this._onTouchMove, { passive: true });
        window.addEventListener('touchend', this._onTouchEnd, { passive: true });
        window.addEventListener('touchcancel', this._onTouchEnd, { passive: true });
    }

    updateScreenCoords(clientX, clientY) {
        if (typeof clientX !== 'number' || typeof clientY !== 'number') return;
        if (!this.canvasRect || this.canvasRect.width === 0 || this.canvasRect.height === 0) {
            this.updateCanvasRect();
            if (!this.canvasRect || this.canvasRect.width === 0 || this.canvasRect.height === 0) return;
        }

        const rect = this.canvasRect;
        const nx = ((clientX - rect.left) / rect.width) * 2 - 1;
        const ny = -((clientY - rect.top) / rect.height) * 2 + 1;

        this._rayVec.set(nx, ny, 0.5).unproject(this.camera);
        this._rayDir.copy(this._rayVec).sub(this.camera.position).normalize();
        this._ray.set(this.camera.position, this._rayDir);
        
        if (this._ray.intersectPlane(this._z0Plane, this._intersectTarget)) {
            this.pointerPos.x = this._intersectTarget.x;
            this.pointerPos.y = this._intersectTarget.y; // Exakt 1:1 dort positionieren, wo der Finger drückt
            this.pointerPos.z = 0;
        }
    }

    updatePointerPos(e) {
        if (e) this.updateScreenCoords(e.clientX, e.clientY);
    }

    updateTouchPos(touch) {
        if (touch) this.updateScreenCoords(touch.clientX, touch.clientY);
    }

    cleanup() {
        this.isRunning = false;
        this.isGameOver = true;
        this.isPaused = false;
        window.__PauseBackgroundCanvas = false;
        if(this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        if(this._settleInterval) clearInterval(this._settleInterval);
        if(this._boundsTimeout) clearTimeout(this._boundsTimeout);
        window.removeEventListener('keydown', this._kd);
        window.removeEventListener('keyup', this._ku);
        window.removeEventListener('scroll', this._onScroll);
        window.removeEventListener('pointermove', this._onPointerMove);
        window.removeEventListener('touchmove', this._onTouchMove);
        window.removeEventListener('touchend', this._onTouchEnd);
        window.removeEventListener('touchcancel', this._onTouchEnd);
        if (this.container) {
            this.container.removeEventListener('pointerdown', this._onPointerDown);
            this.container.removeEventListener('touchstart', this._onTouchStart);
        }
        if(this.resizeObserver) this.resizeObserver.disconnect();

        if (this.scene) {
            this.scene.clear();
        }
        if (this.renderer) {
            if (this.renderer.domElement && this.renderer.domElement.parentNode) {
                this.renderer.domElement.parentNode.removeChild(this.renderer.domElement);
            }
            this.renderer.dispose();
        }
    }

    start() {
        // Pause background decorative canvas during active gameplay for maximum mobile FPS
        window.__PauseBackgroundCanvas = true;

        // Force a layout recalculation now that the game is fully visible
        this.resize();
        
        // Alpine transitions and flexbox layout might take a moment to settle.
        if (this._settleInterval) clearInterval(this._settleInterval);
        let resizeTicks = 0;
        this._settleInterval = setInterval(() => {
            this.resize();
            resizeTicks++;
            if (resizeTicks > 10) {
                clearInterval(this._settleInterval);
                this._settleInterval = null;
            }
        }, 100);

        // Reset state & telemetry
        this.isRunning = true;
        this.isGameOver = false;
        this.isPaused = false;
        this.distance = 0;
        this.shieldHP = 0; // Starts at 0, goes to 100 when skill activated
        this.currentSpeed = this.baseSpeed;
        this.timeScale = 1.0;
        this.fps = 60;
        this.frameTimeMs = 16.67;
        this.minFps = 60;
        this.maxFrameTimeMs = 0;
        this.framesRendered = 0;
        this._fpsAccumulator = 0;
        this._fpsFrames = 0;
        this.ship.position.set(0, -10, 0);
        this.shipTargetPos.set(0, -10, 0);
        this.pointerPos.set(0, -10, 0);
        this.ship.rotation.set(0, 0, 0);
        if (this.shipModel) this.shipModel.visible = true;

        // Hide all active entities
        [...this.bullets, ...this.enemies, ...this.collectibles, ...this.particles, ...this.drones].forEach(m => {
            m.visible = false;
        });
        this.pools.bullets.push(...this.bullets); this.bullets = [];
        this.pools.enemies.push(...this.enemies); this.enemies = [];
        this.pools.collectibles.push(...this.collectibles); this.collectibles = [];
        this.pools.particles.push(...this.particles); this.particles = [];
        this.pools.drones.push(...this.drones); this.drones = [];

        // Reset skills
        for(let k in this.activeSkills) {
            if(this.activeSkills[k].mesh) {
                if (this.ship) this.ship.remove(this.activeSkills[k].mesh);
                this.scene.remove(this.activeSkills[k].mesh);
                this.activeSkills[k].mesh = null;
            }
            this.activeSkills[k].active = false;
            this.activeSkills[k].waitingForClick = false;
        }
        this._lastReportedShield = 0;

        this.lastTime = performance.now();
        this.callbacks.onShieldUpdate(this.shieldHP);
        this.callbacks.onDistanceUpdate(0);

        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        this.animate();
    }

    pause() {
        this.isPaused = true;
        window.__PauseBackgroundCanvas = false;
    }
    resume() {
        this.isPaused = false;
        window.__PauseBackgroundCanvas = true;
        this.lastTime = performance.now(); // Prevents huge delta time jump
    }

    // --- SKILLS ---
    triggerSkill(index) {
        if (!this.isRunning || this.isGameOver) return false;

        if (index === 1) { // Multishoot
            this.activeSkills.multishoot.active = true;
            this.activeSkills.multishoot.timer = 4.0; // 4 seconds
            return true;
        }
        else if (index === 2) { // Teleport
            if (this.activeSkills.teleport && this.activeSkills.teleport.waitingForClick) return false;
            this.timeScale = 0.1; // Slow motion!
            this.activeSkills.teleport.waitingForClick = true;

            // Visual indicator
            const crosshair = document.createElement('div');
            crosshair.id = 'ff-crosshair';
            crosshair.style = 'position:absolute; inset:0; background:radial-gradient(circle, transparent 40%, rgba(168,85,247,0.3) 100%); z-index: 50; pointer-events:none; font-family:sans-serif; text-align:center; padding-top:20vh; font-size:2rem; font-weight:bold; color:#d8b4fe; text-shadow:0 0 10px #c084fc;';
            crosshair.innerText = 'Tippe zum Teleportieren!';
            this.layer.appendChild(crosshair);

            return true;
        }
        else if (index === 3) { // Shield
            this.activeSkills.shield.active = true;
            this.activeSkills.shield.timer = 20.0;
            this.shieldHP = 100;
            this._lastReportedShield = 100;
            this.callbacks.onShieldUpdate(this.shieldHP);

            if(!this.activeSkills.shield.mesh) {
                const shieldGroup = new THREE.Group();

                // Inner glowing forcefield bubble
                const bubbleGeo = new THREE.SphereGeometry(3.2, 24, 24);
                const bubbleMat = new THREE.MeshBasicMaterial({
                    color: 0x38bdf8,
                    transparent: true,
                    opacity: 0.26,
                    depthWrite: false,
                    blending: THREE.AdditiveBlending
                });
                shieldGroup.add(new THREE.Mesh(bubbleGeo, bubbleMat));

                // Geodesic energy lattice
                const wireGeo = new THREE.IcosahedronGeometry(3.35, 2);
                const wireMat = new THREE.MeshBasicMaterial({
                    color: 0x7dd3fc,
                    wireframe: true,
                    transparent: true,
                    opacity: 0.72,
                    depthWrite: false,
                    blending: THREE.AdditiveBlending
                });
                shieldGroup.add(new THREE.Mesh(wireGeo, wireMat));

                // Equatorial energy ring
                const ringGeo = new THREE.RingGeometry(3.45, 3.75, 32);
                const ringMat = new THREE.MeshBasicMaterial({
                    color: 0x60a5fa,
                    side: THREE.DoubleSide,
                    transparent: true,
                    opacity: 0.65,
                    depthWrite: false,
                    blending: THREE.AdditiveBlending
                });
                shieldGroup.add(new THREE.Mesh(ringGeo, ringMat));

                this.activeSkills.shield.mesh = shieldGroup;
            }
            this.ship.add(this.activeSkills.shield.mesh);
            return true;
        }
        else if (index === 4) { // Ultimate Drones
            this.activeSkills.ultimate.active = true;
            this.activeSkills.ultimate.timer = 12.0;

            if (this.shipModel) {
                this.shipModel.traverse((child) => {
                    if (child.isMesh && child.material) {
                        if (!child.userData.originalColor) child.userData.originalColor = child.material.color.clone();
                        child.material.color.setHex(0xfde047); // Golden glow
                    }
                });
            }

            // Spawn 4 Drones
            for(let i=0; i<4; i++) {
                let m = this.pools.drones.pop();
                if(!m) {
                    m = new THREE.Mesh(new THREE.OctahedronGeometry(0.5, 0), new THREE.MeshStandardMaterial({color: 0xfde047, emissive: 0xfde047, metalness:0.8}));
                    this.scene.add(m);
                }
                m.visible = true;
                m.userData = { offsetAngle: (Math.PI / 2) * i, lastFire: 0 };
                this.drones.push(m);
            }
            return true;
        }
        return false;
    }

    executeTeleport() {
        if (!this.activeSkills.teleport.waitingForClick) return;
        this.timeScale = 1.0;
        this.activeSkills.teleport.waitingForClick = false;

        // Move ship
        this.ship.position.copy(this.pointerPos);
        this.shipTargetPos.copy(this.pointerPos);
        
        if (this.tpAudio) {
            let vol = window.alpineComponentContext ? (window.alpineComponentContext.bgmVolumeUi / 100) : 0.5;
            this.tpAudio.volume = Math.min(1.0, vol * 1.5);
            this.tpAudio.currentTime = 0;
            this.tpAudio.play().catch(e => {});
        }

        // Create warp particle effect
        for(let i=0; i<30; i++) { this.spawnParticle(this.ship.position, 0xc084fc); }

        const ch = document.getElementById('ff-crosshair');
        if(ch) ch.remove();
    }

    // --- GAME LOOP ---
    animate() {
        if (!this.isRunning) return;
        this.animationFrameId = requestAnimationFrame(() => this.animate());

        const now = performance.now();
        const elapsedMs = now - this.lastTime;
        const dtRaw = Math.min(elapsedMs / 1000, 0.1); // Max 100ms dt
        this.lastTime = now;

        if (this.isGameOver || this.isPaused) return; // Freeze processing

        // FPS & Frame-Time Telemetry
        if (elapsedMs > 0 && elapsedMs < 250) {
            this.frameTimeMs = elapsedMs;
            this.framesRendered++;
            this._fpsAccumulator += elapsedMs;
            this._fpsFrames++;
            if (this.framesRendered > 10 && elapsedMs > this.maxFrameTimeMs) {
                this.maxFrameTimeMs = elapsedMs;
            }
            if (this._fpsAccumulator >= 250) {
                this.fps = Math.round((this._fpsFrames * 1000) / this._fpsAccumulator);
                if (this.framesRendered > 30 && this.fps < this.minFps) {
                    this.minFps = this.fps;
                }
                this._fpsAccumulator = 0;
                this._fpsFrames = 0;
            }
        }

        const dt = dtRaw * this.timeScale;

        // Progression
        if (this.timeScale === 1.0) {
            this.currentSpeed = this.baseSpeed + (this.distance / 500); // gets faster FASTER
            this.distance += this.currentSpeed * dtRaw * 8; // Global game speed up
            this.callbacks.onDistanceUpdate(this.distance);
        }

        // Input & Ship Movement
        if (!this.activeSkills.teleport.waitingForClick) {
            // Move exactly to pointer
            this.shipTargetPos.copy(this.pointerPos);

            // Bounds dynamically calculated from the camera frustum at Z=0 plane
            if (this.bounds) {
                let clampedX = Math.max(this.bounds.xMin, Math.min(this.bounds.xMax, this.shipTargetPos.x));
                let clampedY = Math.max(this.bounds.yMin, Math.min(this.bounds.yMax, this.shipTargetPos.y));
                this.shipTargetPos.x = isNaN(clampedX) ? 0 : clampedX;
                this.shipTargetPos.y = isNaN(clampedY) ? 0 : clampedY;
            } else {
                this.shipTargetPos.x = Math.max(-15, Math.min(15, this.shipTargetPos.x));
                this.shipTargetPos.y = Math.max(-5, Math.min(20, this.shipTargetPos.y));
            }
        }

        // Smooth translation (clamped factor <= 1.0 for high-refresh 120Hz/60Hz stability)
        this.ship.position.lerp(this.shipTargetPos, Math.min(1.0, 30 * dt));

        // Physik: Neigung und Rollen basierend auf der Bewegung
        const deltaX = this.shipTargetPos.x - this.ship.position.x;
        const deltaY = this.shipTargetPos.y - this.ship.position.y;

        const targetRoll = deltaX * -0.2;  // Y-Achse: Fassrolle beim Seitwärtsflug
        const targetYaw = deltaX * -0.05;  // Z-Achse: Nase lenkt in die Kurve
        const targetPitch = deltaY * 0.05; // X-Achse: Nase hebt/senkt sich

        if (this.activeSkills.multishoot.active) {
            this.ship.rotation.y += 20 * dt; // Spin rapidly during ultimate
        } else {
            // Apply smoothly to parent group (so base config stays completely untouched)
            this.ship.rotation.y += (targetRoll - this.ship.rotation.y) * Math.min(1.0, 10 * dt);
        }
        this.ship.rotation.z += (targetYaw - this.ship.rotation.z) * Math.min(1.0, 10 * dt);
        this.ship.rotation.x += (targetPitch - this.ship.rotation.x) * Math.min(1.0, 10 * dt);

        // Firing Logic (Auto fire always active, Zero-Allocation)
        let fireRate = this.activeSkills.multishoot.active ? (50 / this.timeScale) : (150 / this.timeScale);
        if (now - this.lastFireTime > fireRate) {
            let bulletsToFire = Math.floor((now - this.lastFireTime) / fireRate);
            bulletsToFire = Math.min(bulletsToFire, 3); // Prevent massive bursts on extreme lag

            for(let i = 0; i < bulletsToFire; i++) {
                this._tempVec.copy(this.ship.position);
                this._tempVec.y += i * 2.0; // Space them out visually if firing multiple in one frame
                this.fireBullet(this._tempVec, this._bulletDefaultDir, 0x38bdf8);
            }
            this.lastFireTime = now;
        }

        // --- SKILLS UPDATE ---
        this.updateSkills(dtRaw, now);

        // --- SPAWN OBJECTS ---
        this.spawnLogic(dtRaw);

        // --- UPDATE ENTITIES ---
        this.updateEntities(dt);

        // --- COLLISIONS ---
        this.checkCollisions();

        // Environment: Multi-layer parallax starfields & cosmic nebulae
        if (this.starLayers && this.starLayers.length) {
            for (let li = 0; li < this.starLayers.length; li++) {
                const layer = this.starLayers[li];
                const spanY = layer.userData.spanY || 140;
                layer.position.y -= this.currentSpeed * (layer.userData.speedMult || 0.5) * dt;
                if (layer.position.y <= -spanY) {
                    layer.position.y += spanY;
                }
            }
        } else if (this.starfieldGroup) {
            this.starfieldGroup.position.y -= this.currentSpeed * 0.5 * dt;
            if(this.starfieldGroup.position.y <= -200) {
                this.starfieldGroup.position.y += 200; // Seamless reset
            }
        }

        if (this.nebulaGroup) {
            const nebSpanY = this.nebulaGroup.userData.spanY || 160;
            this.nebulaGroup.position.y -= this.currentSpeed * 0.14 * dt;
            if (this.nebulaGroup.position.y <= -nebSpanY) {
                this.nebulaGroup.position.y += nebSpanY;
            }
        }

        this.renderer.render(this.scene, this.camera);
    }

    updateSkills(dt, now) {
        // Multishoot
        if (this.activeSkills.multishoot.active) {
            this.activeSkills.multishoot.timer -= dt;

            if (this.activeSkills.multishoot.timer <= 0) {
                this.activeSkills.multishoot.active = false;
            }
        }

        // Shield
        if (this.activeSkills.shield.active) {
            this.activeSkills.shield.timer -= dt;
            if (this.activeSkills.shield.timer <= 0 || this.shieldHP <= 0) {
                this.activeSkills.shield.active = false;
                if (this.activeSkills.shield.mesh) {
                    this.ship.remove(this.activeSkills.shield.mesh);
                }
                for(let i=0; i<15; i++) this.spawnParticle(this.ship.position, 0x60a5fa);
                this.shieldHP = 0;
                this._lastReportedShield = 0;
                this.callbacks.onShieldUpdate(0);
            } else {
                if (this.activeSkills.shield.mesh) {
                    this.activeSkills.shield.mesh.rotation.y += dt * 2;
                    this.activeSkills.shield.mesh.rotation.x += dt;
                }
                const displayShield = Math.max(1, Math.min(this.shieldHP, Math.ceil((this.activeSkills.shield.timer / 20.0) * this.shieldHP)));
                if (this._lastReportedShield !== displayShield) {
                    this._lastReportedShield = displayShield;
                    this.callbacks.onShieldUpdate(displayShield);
                }
            }
        }

        // Ultimate Drones
        if (this.activeSkills.ultimate.active) {
            this.activeSkills.ultimate.timer -= dt;

            // Golden trail effect (Zero-Allocation)
            this._tempVec.copy(this.ship.position);
            this._tempVec.x += (Math.random() - 0.5) * 1.5;
            this._tempVec.y -= 1.0;
            this.spawnParticle(this._tempVec, 0xfde047, 1);

            if (this.activeSkills.ultimate.timer <= 0) {
                this.activeSkills.ultimate.active = false;
                
                // Revert golden glow
                if (this.shipModel) {
                    this.shipModel.traverse((child) => {
                        if (child.isMesh && child.material && child.userData.originalColor) {
                            child.material.color.copy(child.userData.originalColor);
                        }
                    });
                }

                while(this.drones.length > 0) {
                    let d = this.drones.pop();
                    d.visible = false;
                    for(let i=0; i<5; i++) this.spawnParticle(d.position, 0xfde047);
                    this.pools.drones.push(d);
                }
            } else {
                // Drone movement and firing taking into timescale
                const droneDt = dt * (this.timeScale === 0.1 ? 10 : 1); // Drones ignore slow-mo!

                for (let di = 0; di < this.drones.length; di++) {
                    const d = this.drones[di];
                    d.userData.offsetAngle += droneDt * 3;
                    d.position.x = this.ship.position.x + Math.cos(d.userData.offsetAngle) * 3;
                    d.position.y = this.ship.position.y + Math.sin(d.userData.offsetAngle) * 3;
                    d.position.z = this.ship.position.z;

                    if (now - d.userData.lastFire > 150) {
                        // Find closest enemy
                        let closest = null, minDist = 10000;
                        for (let ei = 0; ei < this.enemies.length; ei++) {
                            const em = this.enemies[ei];
                            const dist = em.position.distanceToSquared(d.position);
                            if (dist < minDist && em.position.y > d.position.y - 5) {
                                minDist = dist;
                                closest = em;
                            }
                        }

                        if (closest) {
                            this._droneDir.copy(closest.position).sub(d.position).normalize();
                            this.fireBullet(d.position, this._droneDir, 0xfde047);
                            d.userData.lastFire = now;
                        }
                    }
                }
            }
        }
    }

    spawnLogic(dt) {
        this.enemySpawnTimer -= dt;
        this.collectibleSpawnTimer -= dt;

        let spawnRate = Math.max(0.6, 1.0 - (this.distance / 20000)); // Capped spawning rate to ensure low density

        if (this.enemySpawnTimer <= 0) {
            // HARD CAP: Never more than 5 enemies on screen to guarantee 60fps on any device
            if(this.enemies.length < 5 && this.pools.enemies.length > 0) {
                let m = this.pools.enemies.pop();
                let seed = Math.random();
                let difficultyLevel = Math.floor(this.distance / 3000); // 0, 1, 2, 3...
                
                // SPEED & DRIFT SETUP
                let baseFallSpeedMult = 1.0 + (difficultyLevel * 0.15); 
                let isGiant = (seed < 0.05 && difficultyLevel >= 2); 
                let doesDrift = (seed > 0.4 && difficultyLevel >= 1);

                m.userData.fallSpeedMult = baseFallSpeedMult;
                m.userData.driftSpeed = doesDrift ? (2 + Math.random() * 3) : 0;
                m.userData.driftRange = doesDrift ? (5 + Math.random() * 10) : 0;
                
                // Spawn inside dynamic bounds
                let spawnRange = this.bounds.xMax - this.bounds.xMin - 2;
                m.userData.baseX = this.bounds.xMin + 1 + (Math.random() * spawnRange);
                m.userData.spawnTime = performance.now();

                // Reset all visibilities first
                if (m.userData.fallbackNormal) m.userData.fallbackNormal.visible = false;
                if (m.userData.fallbackElite) m.userData.fallbackElite.visible = false;
                if (m.userData.fallbackWall) m.userData.fallbackWall.visible = false;
                if (m.userData.meshGlbMeteor) m.userData.meshGlbMeteor.visible = false;
                if (m.userData.meshGlbSharp) m.userData.meshGlbSharp.visible = false;

                if (seed < 0.15 + (this.distance / 50000)) {
                    // Tough purple enemy
                    m.userData.type = 1; 
                    m.userData.hp = 3 + (difficultyLevel * 3); // HP Scaling (Bullet sponge)
                    m.userData.isWall = false;
                    
                    if (m.userData.meshGlbSharp) {
                        m.userData.meshGlbSharp.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);
                        m.userData.meshGlbSharp.visible = true;
                        m.userData.activeMesh = m.userData.meshGlbSharp; 
                    } else {
                        m.userData.fallbackElite.visible = true;
                        m.userData.activeMesh = m.userData.fallbackElite;
                    }
                    
                    let scale = isGiant ? 6.0 : (1.5 + (difficultyLevel * 0.1));
                    m.scale.set(scale, scale, scale);
                    m.userData.radius = isGiant ? 6.0 : (1.8 + (difficultyLevel * 0.1));
                    if(isGiant) m.userData.hp *= 5; // Boss HP
                    
                    m.position.set(m.userData.baseX, 35, 0); 
                } else {
                    // Normal Meteor
                    m.userData.type = 0; 
                    m.userData.hp = 1 + (difficultyLevel * 2); 
                    m.userData.isWall = false;
                    
                    if (m.userData.meshGlbMeteor) {
                        m.userData.meshGlbMeteor.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);
                        m.userData.meshGlbMeteor.visible = true;
                        m.userData.activeMesh = m.userData.meshGlbMeteor;
                    } else {
                        m.userData.fallbackNormal.visible = true;
                        m.userData.activeMesh = m.userData.fallbackNormal;
                    }
                    
                    let scale = isGiant ? 6.0 : 1.5;
                    m.scale.set(scale, scale, scale);
                    m.userData.radius = isGiant ? 6.0 : 1.5;
                    if(isGiant) m.userData.hp *= 5; // Boss HP
                    
                    m.position.set(m.userData.baseX, 35, 0); 
                }

                m.visible = true;
                this.enemies.push(m);
            }
            this.enemySpawnTimer = spawnRate;
        }

        if (this.collectibleSpawnTimer <= 0) {
            if(this.pools.collectibles.length > 0) {
                let m = this.pools.collectibles.pop();
                
                // Spawn inside dynamic bounds
                let spawnRange = this.bounds.xMax - this.bounds.xMin - 2;
                let collectX = this.bounds.xMin + 1 + (Math.random() * spawnRange);
                m.position.set(collectX, 30, 0);

                // 15% chance for blue cooldown reduced
                const isBlue = Math.random() < 0.15;
                m.userData.type = isBlue ? 1 : 0;
                m.material = this.matCollect[isBlue ? 1 : 0];

                m.visible = true;
                this.collectibles.push(m);
            }
            this.collectibleSpawnTimer = 18.0; // Drastically reduced sparks
        }
    }

    fireBullet(pos, dir, color = 0x38bdf8) {
        if(this.pools.bullets.length > 0) {
            let m = this.pools.bullets.pop();
            m.position.copy(pos);
            m.position.y += 1.0;
            m.userData.dir.copy(dir || this._bulletDefaultDir);
            m.material = (color === 0xfde047 && this.matBulletGold) ? this.matBulletGold : this.matBullet;
            m.visible = true;
            this.bullets.push(m);
        }
    }

    _removeAtSwap(arr, index) {
        const last = arr.length - 1;
        if (index !== last) {
            arr[index] = arr[last];
        }
        arr.pop();
    }

    updateEntities(dt) {
        const now = this.lastTime || performance.now();

        // --- Exhaust Particles (Fire) ---
        if (Math.random() < 0.6) {
            if(this.pools.particles.length > 0) {
                let p = this.pools.particles.pop();
                // Spawn behind the ship
                p.position.copy(this.ship.position);
                p.position.y -= 1.5;
                p.position.x += (Math.random() - 0.5) * 0.5;

                // Orange/Yellow fire colors
                p.material = (Math.random() > 0.5 ? this.matParticleOrange : this.matParticleYellow);

                let speed = 20 + Math.random() * 10;
                p.userData.vel.set((Math.random() - 0.5) * 2, -speed, 0);
                p.userData.life = 0.3 + Math.random() * 0.2;

                p.scale.setScalar(Math.random() * 1.5 + 0.5);
                p.visible = true;
                this.particles.push(p);
            }
        }

        // Bullets
        for(let i=this.bullets.length-1; i>=0; i--) {
            let b = this.bullets[i];
            b.position.addScaledVector(b.userData.dir, 120 * dt);
            if(b.position.y > 60 || b.position.y < -30 || b.position.x > 80 || b.position.x < -80) {
                b.visible = false;
                this.pools.bullets.push(b);
                this._removeAtSwap(this.bullets, i);
            }
        }

        // Enemies
        const baseFallSpeed = (this.currentSpeed * 1.5) * dt; // Game base speed
        for(let i=this.enemies.length-1; i>=0; i--) {
            let e = this.enemies[i];
            let fallSpeedY = e.userData.isWall ? (15 * dt) : (baseFallSpeed * (e.userData.fallSpeedMult || 1));

            e.position.y -= fallSpeedY;
            
            // Drift / Zick-Zack logic
            if (e.userData.driftSpeed > 0 && !e.userData.isWall) {
                let elapsed = (now - e.userData.spawnTime) / 1000;
                e.position.x = e.userData.baseX + (Math.sin(elapsed * e.userData.driftSpeed) * e.userData.driftRange);
            }
            
            e.position.z = 0; // Lock perfectly onto 2D Plane Z=0

            if(!e.userData.isWall && e.userData.activeMesh) { 
                e.userData.activeMesh.rotation.x += 1 * dt; 
                e.userData.activeMesh.rotation.y += 2 * dt; 
            }

            if(e.position.y < -20) {
                e.visible = false; 
                this.pools.enemies.push(e); 
                this._removeAtSwap(this.enemies, i);
            }
        }

        // Collectibles
        const pulseScale = 1.0 + Math.sin(now * 0.01) * 0.2;
        for(let i=this.collectibles.length-1; i>=0; i--) {
            let c = this.collectibles[i];
            c.position.y -= baseFallSpeed;
            c.scale.setScalar(pulseScale);
            if(c.position.y < -20) {
                c.visible = false;
                this.pools.collectibles.push(c);
                this._removeAtSwap(this.collectibles, i);
            }
        }

        // Particles
        for(let i=this.particles.length-1; i>=0; i--) {
            let p = this.particles[i];
            p.userData.life -= dt * 2.0;
            if(p.userData.life <= 0) {
                p.visible = false;
                this.pools.particles.push(p);
                this._removeAtSwap(this.particles, i);
            } else {
                p.position.addScaledVector(p.userData.vel, dt);
                p.scale.setScalar(p.userData.life);
            }
        }
    }

    checkCollisions() {
        // Check Bullets vs Enemies
        for(let i=this.bullets.length-1; i>=0; i--) {
            let b = this.bullets[i];
            let hit = false;
            for(let j=this.enemies.length-1; j>=0; j--) {
                let em = this.enemies[j];
                const dx = b.position.x - em.position.x;
                const dy = b.position.y - em.position.y;
                
                // Stretch hitbox vertically slightly based on bullet speed to prevent phasing through enemies at low fps
                let speedStretch = b.userData.dir.y * 120 * (1/60) * 0.5;
                let dyStretched = Math.max(0, Math.abs(dy) - Math.abs(speedStretch));
                const rSum = em.userData.radius + 0.8;

                if ((dx * dx + dyStretched * dyStretched) < (rSum * rSum)) {
                    hit = true;
                    // Apply DMG
                    em.userData.hp -= 1;
                    if (em.userData.hp <= 0) {
                        this.killEnemy(em, j);
                    } else {
                        // Flash white/damage effect
                        this.spawnParticle(b.position, 0xffffff, 5);
                    }
                    break;
                }
            }
            if(hit) {
                b.visible = false;
                this.pools.bullets.push(b);
                this._removeAtSwap(this.bullets, i);
            }
        }

        // Check Ship vs Collectibles
        for(let i=this.collectibles.length-1; i>=0; i--) {
            let c = this.collectibles[i];
            const rSum = this.shipBounds + c.userData.radius;
            if (this.ship.position.distanceToSquared(c.position) < (rSum * rSum)) {

                // Collect effect
                for(let k=0; k<10; k++) this.spawnParticle(c.position, c.material.color.getHex());

                if (c.userData.type === 0) {
                    this.callbacks.onSparkCollected(Math.floor(Math.random() * 5) + 1); // 1-5 Funken
                    this.audio.playPickup();
                } else {
                    this.callbacks.onCooldownReduction(5); // -5s
                    this.audio.playPickup();
                }

                c.visible = false;
                this.pools.collectibles.push(c);
                this._removeAtSwap(this.collectibles, i);
            }
        }

        // Check Ship vs Enemies (Game Over or Shield hit)
        for(let i=this.enemies.length-1; i>=0; i--) {
            let em = this.enemies[i];

            // If teleporting/invulnerable state, skip collision
            if(this.activeSkills.teleport.waitingForClick) continue;

            const collisionDist = this.activeSkills.shield.active ? 3.0 : this.shipBounds;

            let hit = false;
            const dx = this.ship.position.x - em.position.x;
            const dy = this.ship.position.y - em.position.y;

            if (em.userData.isWall) {
                if (Math.abs(dx) < 16 + collisionDist && Math.abs(dy) < 0.75 + collisionDist) hit = true;
            } else {
                const rSum = collisionDist + em.userData.radius;
                if ((dx * dx + dy * dy) < (rSum * rSum)) hit = true;
            }

            if (hit) {

                if (this.activeSkills.shield.active) {
                    // Shield hit!
                    this.shieldHP -= 34; // 3 hits breaks it
                    this.callbacks.onShieldUpdate(this.shieldHP);
                    this.killEnemy(em, i);

                    if (this.shieldHP <= 0) {
                        this.activeSkills.shield.timer = 0; // Break shield
                    }
                } else {
                    // GAME OVER!
                    for(let k=0; k<50; k++) this.spawnParticle(this.ship.position, 0xffaa00);
                    this.shipModel.visible = false;
                    this.triggerGameOver();
                    return;
                }
            }
        }
    }

    killEnemy(enemyMesh, index) {
        this.audio.playExplosion();
        // Explosion particles
        let color = 0xffffff;
        if (enemyMesh.userData.type === 1) { color = 0x8b5cf6; } // Purple
        else if (enemyMesh.userData.type === 0) { color = 0xef4444; } // Red
        
        for(let k=0; k<15; k++) this.spawnParticle(enemyMesh.position, color);

        enemyMesh.visible = false;
        this.pools.enemies.push(enemyMesh);
        this._removeAtSwap(this.enemies, index);

        // Small chance to drop collectible
        if (Math.random() < 0.2) {
            if(this.pools.collectibles.length > 0) {
                let m = this.pools.collectibles.pop();
                m.position.copy(enemyMesh.position);
                const isBlue = Math.random() < 0.2;
                m.userData.type = isBlue ? 1 : 0;
                m.material = this.matCollect[isBlue ? 1 : 0];
                m.visible = true;
                this.collectibles.push(m);
            }
        }
    }

    getParticleMaterial(colorHex) {
        if(colorHex === 0xf97316) return this.matParticleOrange;
        if(colorHex === 0xfef08a) return this.matParticleYellow;
        if(colorHex === 0xffffff) return this.matParticleWhite;
        if(colorHex === 0x60a5fa) return this.matParticleBlue;
        if(colorHex === 0xfde047) return this.matParticleGold;
        if(colorHex === 0xc084fc || colorHex === 0x8b5cf6) return this.matParticlePurple;
        if(colorHex === 0xef4444) return this.matParticleOrange; // fallback Red to orange
        if(colorHex === 0xffaa00) return this.matParticleOrange; 
        return this.matParticleWhite; // fallback
    }

    spawnParticle(pos, colorHex, count = 1) {
        const sharedMaterial = this.getParticleMaterial(colorHex);
        for(let i=0; i<count; i++) {
            if(this.pools.particles.length > 0) {
                let p = this.pools.particles.pop();
                p.position.copy(pos);
                p.material = sharedMaterial;
                p.userData.life = 1.0;
                p.userData.vel.set((Math.random()-0.5)*10, (Math.random()-0.5)*10, (Math.random()-0.5)*10);
                p.visible = true;
                this.particles.push(p);
            }
        }
    }

    getPerformanceMetrics() {
        return {
            fps: this.fps,
            minFps: this.minFps,
            frameTimeMs: Number(this.frameTimeMs.toFixed(2)),
            maxFrameTimeMs: Number(this.maxFrameTimeMs.toFixed(2)),
            framesRendered: this.framesRendered,
            loadedModels: this.loadedModels,
            isMobile: this.isMobile,
            activeEntities: {
                bullets: this.bullets.length,
                enemies: this.enemies.length,
                particles: this.particles.length,
                collectibles: this.collectibles.length,
                drones: this.drones.length
            },
            renderInfo: this.renderer ? {
                calls: this.renderer.info.render.calls,
                triangles: this.renderer.info.render.triangles,
                geometries: this.renderer.info.memory.geometries,
                textures: this.renderer.info.memory.textures
            } : null
        };
    }

    triggerGameOver() {
        this.isRunning = false;
        this.isGameOver = true;
        window.__PauseBackgroundCanvas = false;
        this.callbacks.onGameOver();
    }
};
