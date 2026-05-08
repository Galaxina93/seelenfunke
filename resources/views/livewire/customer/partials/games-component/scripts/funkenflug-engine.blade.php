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

        this.initScene();
        this.loadAssets();
        this.setupInteractions();
    }

    resize() {
        if (!this.container || !this.camera || !this.renderer) return;
        const w = this.container.offsetWidth;
        const h = this.container.offsetHeight;
        if (w === 0 || h === 0) return; // Prevent NaN/Infinity aspect corruption
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(w, h);
        
        // Update bounds asynchronously to ensure layout has settled
        setTimeout(() => {
            this.updateBounds();
        }, 100);
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
            yMin: bottomCenter.y + 4,
            yMax: topCenter.y - 4
        };

        // Failsafe: If layout hasn't settled and bounds invert, use safe fallbacks
        if (isNaN(this.bounds.yMin) || isNaN(this.bounds.yMax) || this.bounds.yMax <= this.bounds.yMin) {
            this.bounds.yMin = -5;
            this.bounds.yMax = 25;
        }
        if (isNaN(this.bounds.xMin) || isNaN(this.bounds.xMax) || this.bounds.xMax <= this.bounds.xMin) {
            this.bounds.xMin = -15;
            this.bounds.xMax = 15;
        }
    }

    initScene() {
        this.scene = new THREE.Scene();
        this.isMobile = window.innerWidth <= 768;
        
        // Fog is expensive on mobile fragment shaders
        if (!this.isMobile) {
            this.scene.fog = new THREE.FogExp2(0x0f172a, 0.015);
        }

        const w = this.container.offsetWidth || 800;
        const h = this.container.offsetHeight || 800;

        // Camera looking slightly down
        this.camera = new THREE.PerspectiveCamera(60, w / h, 0.1, 1000);
        this.camera.position.set(0, -15, 30);
        this.camera.lookAt(0, 5, 0);

        // Turn off antialiasing on mobile for huge performance gain
        this.renderer = new THREE.WebGLRenderer({ antialias: !this.isMobile, alpha: true });
        this.renderer.setSize(w, h);
        
        // Standard setup: Keep resolution sharp
        const pixelRatio = this.isMobile ? 1.0 : Math.min(window.devicePixelRatio, 1.25);
        this.renderer.setPixelRatio(pixelRatio);
        this.renderer.setClearColor(0x0f172a, 1);

        this.renderer.domElement.style.position = 'absolute';
        this.renderer.domElement.style.width = '100%';
        this.renderer.domElement.style.height = '100%';

        this.container.appendChild(this.renderer.domElement);

        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
        this.scene.add(ambientLight);

        const dirLight = new THREE.DirectionalLight(0xffffff, 1.5);
        dirLight.position.set(10, -10, 20);
        this.scene.add(dirLight);

        // Create Starfield (moving background) - Less stars on mobile
        const starCount = this.isMobile ? 100 : 1000;
        const starGeom = new THREE.BufferGeometry();
        const starMat = new THREE.PointsMaterial({ color: 0xffffff, size: 0.2, transparent: true, opacity: 0.8 });
        const starVerts = [];
        for(let i=0; i<starCount; i++) {
            starVerts.push((Math.random() - 0.5) * 200, (Math.random() - 0.5) * 200, (Math.random() - 0.5) * 50 - 10);
        }
        starGeom.setAttribute('position', new THREE.Float32BufferAttribute(starVerts, 3));
        
        this.starfieldGroup = new THREE.Group();
        this.starfield1 = new THREE.Points(starGeom, starMat);
        this.starfield2 = new THREE.Points(starGeom, starMat);
        this.starfield2.position.y = 200; // Place exactly seamlessly above the first one
        
        this.starfieldGroup.add(this.starfield1, this.starfield2);
        this.scene.add(this.starfieldGroup);

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

                // Add emissive engine glow to materials
                this.shipModel.traverse((child) => {
                    if (child.isMesh) {
                        child.castShadow = false;
                        if(child.material) {
                            const oldMat = child.material;
                            child.material = new THREE.MeshBasicMaterial({ color: oldMat.color, map: oldMat.map });
                        }
                    }
                });

                this.ship.add(this.shipModel);
            }, undefined, (e) => { console.warn("Could not load rocket GLTF, using fallback."); });

            // Load Meteor GLB (Skip on mobile for performance)
            this.meteorModel = null;
            if (this.assets.meteor && !this.isMobile) {
                loader.load(this.assets.meteor, (gltf) => {
                    this.meteorModel = gltf.scene;
                    // Standard scaling/rotation for meteor
                    this.meteorModel.scale.set(1.5, 1.5, 1.5);
                    this.meteorModel.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = false;
                            child.receiveShadow = false;
                            if (child.material) {
                                const oldMat = child.material;
                                child.material = new THREE.MeshBasicMaterial({ color: oldMat.color, map: oldMat.map });
                            }
                        }
                    });
                    
                    // ONCE IT LOADS: CLONE INTO ALL POOL ITEMS IMMEDIATELY
                    this.pools.enemies.forEach(m => {
                        if(m && m.userData) {
                            let clone = this.meteorModel.clone();
                            clone.visible = false;
                            m.userData.meshGlbMeteor = clone;
                            m.add(clone);
                        }
                    });
                }, undefined, (e) => { console.warn("Could not load meteor GLTF."); });
            }

            // Load Sharp Stone GLB (Skip on mobile for performance)
            this.sharpstoneModel = null;
            if (this.assets.sharp_stone && !this.isMobile) {
                loader.load(this.assets.sharp_stone, (gltf) => {
                    this.sharpstoneModel = gltf.scene;
                    this.sharpstoneModel.scale.set(1.5, 1.5, 1.5);
                    this.sharpstoneModel.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = false;
                            child.receiveShadow = false;
                            if (child.material) {
                                const oldMat = child.material;
                                child.material = new THREE.MeshBasicMaterial({ color: oldMat.color, map: oldMat.map });
                            }
                        }
                    });
                    
                    // ONCE IT LOADS: CLONE INTO ALL POOL ITEMS IMMEDIATELY
                    this.pools.enemies.forEach(m => {
                        if(m && m.userData) {
                            let clone = this.sharpstoneModel.clone();
                            clone.visible = false;
                            m.userData.meshGlbSharp = clone;
                            m.add(clone);
                        }
                    });
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
        const m = new THREE.Mesh(this.geomBullet, this.matBullet);
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
        m.visible = false; m.userData = { type: 0, radius: 0.6 };
        this.scene.add(m); return m;
    }
    createParticleMesh() {
        const m = new THREE.Mesh(this.geomParticle, this.matParticleWhite);
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


        this.activeTouchId = null;

        // POINTER EVENTS (Desktop Mouse & Pen)
        const onPointerMove = (e) => {
            if (!this.isRunning) return;
            if (e.pointerType === 'mouse' || e.pointerType === 'pen') {
                this.updatePointerPos(e);
            }
        };
        const onPointerDown = (e) => {
            if (!this.isRunning) return;
            if (e.pointerType === 'mouse' || e.pointerType === 'pen') {
                this.updatePointerPos(e);
                if (this.activeSkills.teleport.waitingForClick) {
                    this.executeTeleport();
                }
            }
        };

        // TOUCH EVENTS (Mobile Multi-Touch)
        const onTouchStart = (e) => {
            if (!this.isRunning) return;
            if (e.target.tagName === 'BUTTON') return; 
            
            if (this.activeTouchId === null && e.changedTouches.length > 0) {
                this.activeTouchId = e.changedTouches[0].identifier;
                this.updateTouchPos(e.changedTouches[0]);
                
                if (this.activeSkills.teleport.waitingForClick) {
                    this.executeTeleport();
                }
            }
        };
        const onTouchMove = (e) => {
            if (!this.isRunning || this.activeTouchId === null) return;
            for(let i=0; i<e.changedTouches.length; i++) {
                if (e.changedTouches[i].identifier === this.activeTouchId) {
                    this.updateTouchPos(e.changedTouches[i]);
                    break;
                }
            }
        };
        const onTouchEnd = (e) => {
            for(let i=0; i<e.changedTouches.length; i++) {
                if (e.changedTouches[i].identifier === this.activeTouchId) {
                    this.activeTouchId = null;
                    break;
                }
            }
        };

        this.container.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('pointermove', onPointerMove);
        
        this.container.addEventListener('touchstart', onTouchStart, {passive: false});
        window.addEventListener('touchmove', onTouchMove, {passive: false});
        window.addEventListener('touchend', onTouchEnd);
        window.addEventListener('touchcancel', onTouchEnd);
    }

    updatePointerPos(e) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;
        if (typeof e.clientX !== 'number' || typeof e.clientY !== 'number') return;

        const clientX = e.clientX;
        const clientY = e.clientY;

        const nx = ((clientX - rect.left) / rect.width) * 2 - 1;
        const ny = -((clientY - rect.top) / rect.height) * 2 + 1;

        const vec = new THREE.Vector3(nx, ny, 0.5);
        vec.unproject(this.camera);
        const dir = vec.sub(this.camera.position).normalize();
        
        const z0plane = new THREE.Plane(new THREE.Vector3(0, 0, 1), 0);
        const ray = new THREE.Ray(this.camera.position, dir);
        const target = new THREE.Vector3();
        
        if (ray.intersectPlane(z0plane, target)) {
            this.pointerPos.x = target.x;
            this.pointerPos.y = target.y + 5; // Offset because finger covers ship
            this.pointerPos.z = 0;
        }
    }

    updateTouchPos(touch) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;
        if (!touch || typeof touch.clientX !== 'number' || typeof touch.clientY !== 'number') return;

        const clientX = touch.clientX;
        const clientY = touch.clientY;

        const nx = ((clientX - rect.left) / rect.width) * 2 - 1;
        const ny = -((clientY - rect.top) / rect.height) * 2 + 1;

        const vec = new THREE.Vector3(nx, ny, 0.5);
        vec.unproject(this.camera);
        const dir = vec.sub(this.camera.position).normalize();
        
        const z0plane = new THREE.Plane(new THREE.Vector3(0, 0, 1), 0);
        const ray = new THREE.Ray(this.camera.position, dir);
        const target = new THREE.Vector3();
        
        if (ray.intersectPlane(z0plane, target)) {
            this.pointerPos.x = target.x;
            this.pointerPos.y = target.y + 5; // Offset because finger covers ship
            this.pointerPos.z = 0;
        }
    }

    cleanup() {
        this.isRunning = false;
        this.isGameOver = true;
        this.isPaused = false;
        if(this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        window.removeEventListener('keydown', this._kd);
        window.removeEventListener('keyup', this._ku);
        if(this._jts) {
            const joyZone = document.getElementById('ff-joystick-zone');
            if(joyZone){
                 joyZone.removeEventListener('pointerdown', this._jts);
                 joyZone.removeEventListener('pointermove', this._jtm);
                 window.removeEventListener('pointerup', this._jte);
                 window.removeEventListener('pointercancel', this._jte);
            }
        }
        if(this.resizeObserver) this.resizeObserver.disconnect();

        while(this.scene.children.length > 0){
            this.scene.remove(this.scene.children[0]);
        }
        if (this.renderer && this.renderer.domElement.parentNode) {
            this.renderer.domElement.parentNode.removeChild(this.renderer.domElement);
        }
    }

    start() {
        // Force a layout recalculation now that the game is fully visible
        this.resize();
        
        // Alpine transitions and flexbox layout might take a moment to settle.
        // We poll resize for 1.5 seconds to guarantee the math perfectly matches the visual screen.
        let resizeTicks = 0;
        const settleInterval = setInterval(() => {
            this.resize();
            resizeTicks++;
            if (resizeTicks > 15) clearInterval(settleInterval);
        }, 100);

        // Reset state
        this.isRunning = true;
        this.isGameOver = false;
        this.isPaused = false;
        this.distance = 0;
        this.shieldHP = 0; // Starts at 0, goes to 100 when skill activated
        this.currentSpeed = this.baseSpeed;
        this.timeScale = 1.0;
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
                this.scene.remove(this.activeSkills[k].mesh);
                this.activeSkills[k].mesh = null;
            }
            this.activeSkills[k].active = false;
            this.activeSkills[k].waitingForClick = false;
        }

        this.lastTime = performance.now();
        this.callbacks.onShieldUpdate(this.shieldHP);
        this.callbacks.onDistanceUpdate(0);

        this.animate();
    }

    pause() { this.isPaused = true; }
    resume() {
        this.isPaused = false;
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
            this.callbacks.onShieldUpdate(this.shieldHP);

            if(!this.activeSkills.shield.mesh) {
                const geo = new THREE.SphereGeometry(2.5, 32, 32);
                const mat = new THREE.MeshBasicMaterial({ color: 0x60a5fa, transparent: true, opacity: 0.4, wireframe: true });
                this.activeSkills.shield.mesh = new THREE.Mesh(geo, mat);
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
        
        let tpAudio = new Audio('{{ asset("shop/customer/gamification/sounds/teleport_active.mp3") }}');
        let vol = window.alpineComponentContext ? (window.alpineComponentContext.bgmVolumeUi / 100) : 0.5;
        tpAudio.volume = Math.min(1.0, vol * 1.5); // Make it slightly louder than BGM
        tpAudio.play().catch(e => console.warn(e));

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
        const dtRaw = Math.min((now - this.lastTime) / 1000, 0.1); // Max 100ms dt
        this.lastTime = now;

        const dt = dtRaw * this.timeScale;

        if (this.isGameOver || this.isPaused) return; // Freeze processing

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

        // Smooth translation
        this.ship.position.lerp(this.shipTargetPos, 30 * dt); // Doubled lerp speed for instant reaction

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
            this.ship.rotation.y += (targetRoll - this.ship.rotation.y) * 10 * dt;
        }
        this.ship.rotation.z += (targetYaw - this.ship.rotation.z) * 10 * dt;
        this.ship.rotation.x += (targetPitch - this.ship.rotation.x) * 10 * dt;

        // Firing Logic (Auto fire always active)
        let fireRate = this.activeSkills.multishoot.active ? (50 / this.timeScale) : (150 / this.timeScale);
        if (now - this.lastFireTime > fireRate) {
            let bulletsToFire = Math.floor((now - this.lastFireTime) / fireRate);
            bulletsToFire = Math.min(bulletsToFire, 3); // Prevent massive bursts on extreme lag

            for(let i = 0; i < bulletsToFire; i++) {
                let offsetPos = this.ship.position.clone();
                offsetPos.y += i * 2.0; // Space them out visually if firing multiple in one frame
                this.fireBullet(offsetPos);
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

        // Environment
        if (this.starfieldGroup) {
            this.starfieldGroup.position.y -= this.currentSpeed * 0.5 * dt;
            if(this.starfieldGroup.position.y <= -200) {
                this.starfieldGroup.position.y += 200; // Seamless reset
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
                this.ship.remove(this.activeSkills.shield.mesh);
                for(let i=0; i<15; i++) this.spawnParticle(this.ship.position, 0x60a5fa);
                this.shieldHP = 0;
                this.callbacks.onShieldUpdate(0);
            } else {
                this.activeSkills.shield.mesh.rotation.y += dt * 2;
                this.activeSkills.shield.mesh.rotation.x += dt;
            }
        } else {
            // Natural shield regen? (Optional, skipping for now)
        }

        // Ultimate Drones
        if (this.activeSkills.ultimate.active) {
            this.activeSkills.ultimate.timer -= dt;

            // Golden trail effect
            let trailPos = this.ship.position.clone();
            trailPos.x += (Math.random() - 0.5) * 1.5;
            trailPos.y -= 1.0;
            this.spawnParticle(trailPos, 0xfde047, 0.8);

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

                this.drones.forEach(d => {
                    d.userData.offsetAngle += droneDt * 3;
                    d.position.x = this.ship.position.x + Math.cos(d.userData.offsetAngle) * 3;
                    d.position.y = this.ship.position.y + Math.sin(d.userData.offsetAngle) * 3;
                    d.position.z = this.ship.position.z;

                    if (now - d.userData.lastFire > 150) {
                        // Find closest enemy
                        let closest = null, minDist = 10000;
                        this.enemies.forEach(em => {
                            let dist = em.position.distanceToSquared(d.position);
                            if (dist < minDist && em.position.y > d.position.y - 5) { minDist = dist; closest = em; }
                        });

                        if (closest) {
                            let dir = closest.position.clone().sub(d.position).normalize();
                            this.fireBullet(d.position, dir, 0xfde047);
                            d.userData.lastFire = now;
                        }
                    }
                });
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

    fireBullet(pos, dir = new THREE.Vector3(0,1,0), color = 0x38bdf8) {
        if(this.pools.bullets.length > 0) {
            let m = this.pools.bullets.pop();
            m.position.copy(pos);
            m.position.y += 1.0;
            m.userData.dir = dir;
            m.material.color.setHex(color);
            m.visible = true;
            this.bullets.push(m);
        }
    }

    updateEntities(dt) {
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
                b.visible = false; this.pools.bullets.push(b); this.bullets.splice(i, 1);
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
                let elapsed = (performance.now() - e.userData.spawnTime) / 1000;
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
                this.enemies.splice(i, 1);
            }
        }

        // Collectibles
        for(let i=this.collectibles.length-1; i>=0; i--) {
            let c = this.collectibles[i];
            c.position.y -= baseFallSpeed;
            c.scale.setScalar(1.0 + Math.sin(performance.now() * 0.01) * 0.2); // Pulse
            if(c.position.y < -20) {
                c.visible = false; this.pools.collectibles.push(c); this.collectibles.splice(i, 1);
            }
        }

        // Particles
        for(let i=this.particles.length-1; i>=0; i--) {
            let p = this.particles[i];
            p.userData.life -= dt * 2.0;
            if(p.userData.life <= 0) {
                p.visible = false; this.pools.particles.push(p); this.particles.splice(i, 1);
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
                let speedStretch = b.userData.dir.y * 120 * (1/60) * 0.5; // rough half-frame travel distance
                let dyStretched = Math.max(0, Math.abs(dy) - Math.abs(speedStretch));

                if ((dx * dx + dyStretched * dyStretched) < Math.pow(em.userData.radius + 0.8, 2)) {
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
                b.visible = false; this.pools.bullets.push(b); this.bullets.splice(i, 1);
            }
        }

        // Check Ship vs Collectibles
        for(let i=this.collectibles.length-1; i>=0; i--) {
            let c = this.collectibles[i];
            if (this.ship.position.distanceToSquared(c.position) < Math.pow(this.shipBounds + c.userData.radius, 2)) {

                // Collect effect
                for(let k=0; k<10; k++) this.spawnParticle(c.position, c.material.color.getHex());

                if (c.userData.type === 0) {
                    this.callbacks.onSparkCollected(Math.floor(Math.random() * 5) + 1); // 1-5 Funken
                    this.audio.playPickup();
                } else {
                    this.callbacks.onCooldownReduction(5); // -5s
                    this.audio.playPickup();
                }

                c.visible = false; this.pools.collectibles.push(c); this.collectibles.splice(i, 1);
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
                if ((dx * dx + dy * dy) < Math.pow(collisionDist + em.userData.radius, 2)) hit = true;
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
        this.enemies.splice(index, 1);

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

    triggerGameOver() {
        this.isRunning = false;
        this.isGameOver = true;
        this.callbacks.onGameOver();
    }
};
