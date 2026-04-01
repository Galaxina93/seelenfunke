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
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(w, h);
        
        // CACHE BOUNDING CLIENT RECT FOR POINTER LOGIC (CRITICAL MOBILE PERFORMANCE)
        setTimeout(() => {
            if(this.renderer.domElement) {
                this.cachedRect = this.renderer.domElement.getBoundingClientRect();
            }
        }, 100);
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
        
        // Restore high resolution on mobile, cap at 1.0 to prevent extreme thermal throttling. 
        // We cap desktop to 1.25 as well to prevent 4K GPUs from dying on 30x high-poly meteors
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

        // Try GLTFLoader if available (Skip entirely on mobile to enforce low-poly primitives for 60fps)
        const GltfLoaderClass = window.GLTFLoader || (window.THREE && window.THREE.GLTFLoader);
        if (GltfLoaderClass && !this.isMobile) {
            const loader = new GltfLoaderClass();
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

            // Load Meteor GLB
            this.meteorModel = null;
            if (this.assets.meteor) {
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

            // Load Sharp Stone GLB
            this.sharpstoneModel = null;
            if (this.assets.sharp_stone) {
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
        }

        // Initialize materials for pools
        this.matBullet = new THREE.MeshBasicMaterial({ color: 0x38bdf8 }); // Light blue laser
        this.geomBullet = new THREE.CylinderGeometry(0.1, 0.1, 1, this.isMobile ? 4 : 8);
        this.geomBullet.rotateX(Math.PI / 2);

        this.matEnemy = this.isMobile ? [
            new THREE.MeshBasicMaterial({ color: 0xef4444 }), // Red Meteor
            new THREE.MeshBasicMaterial({ color: 0x8b5cf6 }), // Purple Crystal
            new THREE.MeshBasicMaterial({ color: 0xf97316 }) // Orange Wall
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
        const bulletCount = this.isMobile ? 20 : 50;
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

        // --- MOBILE JOYSTICK ---
        this.joystickVector = { x: 0, y: 0 };
        const joyZone = document.getElementById('ff-joystick-zone');
        const joyKnob = document.getElementById('ff-joystick-knob');
        
        if (joyZone && joyKnob && this.isMobile) {
            let activeTouchId = null;
            let joyRect = null;
            let maxDist = 30; // 90px zone, 12px knob, rough travel limit

            const updateJoy = (clientX, clientY) => {
                if (!joyRect) return;
                let cx = joyRect.left + joyRect.width / 2;
                let cy = joyRect.top + joyRect.height / 2;
                let dx = clientX - cx;
                let dy = clientY - cy;
                let dist = Math.sqrt(dx*dx + dy*dy);
                if(dist > maxDist) {
                    dx = (dx/dist) * maxDist;
                    dy = (dy/dist) * maxDist;
                }
                joyKnob.style.transform = `translate(${dx}px, ${dy}px)`;
                this.joystickVector.x = dx / maxDist;
                this.joystickVector.y = -(dy / maxDist); // Invert Y logically
            };
            const resetJoy = () => {
                activeTouchId = null;
                joyKnob.style.transform = `translate(0px, 0px)`;
                this.joystickVector.x = 0;
                this.joystickVector.y = 0;
            };

            this._jts = (e) => {
                e.preventDefault(); 
                if (activeTouchId !== null) return;
                let t = e.changedTouches[0];
                activeTouchId = t.identifier;
                joyRect = joyZone.getBoundingClientRect();
                updateJoy(t.clientX, t.clientY);
            };
            joyZone.addEventListener('touchstart', this._jts, {passive: false});

            this._jtm = (e) => {
                e.preventDefault();
                if (activeTouchId === null) return;
                for(let i=0; i<e.changedTouches.length; i++){
                    if(e.changedTouches[i].identifier === activeTouchId) {
                        updateJoy(e.changedTouches[i].clientX, e.changedTouches[i].clientY);
                        break;
                    }
                }
            };
            joyZone.addEventListener('touchmove', this._jtm, {passive: false});

            this._jte = (e) => {
                if (activeTouchId === null) return;
                for(let i=0; i<e.changedTouches.length; i++){
                    if(e.changedTouches[i].identifier === activeTouchId) {
                        resetJoy();
                        break;
                    }
                }
            };
            joyZone.addEventListener('touchend', this._jte);
            joyZone.addEventListener('touchcancel', this._jte);
        }

        const onPtrDown = (e) => {
            if (!this.isRunning) return;
            this.isPointerDown = true;
            this.updatePointerPos(e);

            if (this.activeSkills.teleport.waitingForClick) {
                this.executeTeleport();
            }
        };
        const onPtrMove = (e) => {
            // Update pointer immediately if it's a mouse (desktop follow), or if dragging (mobile touch)
            if (e.pointerType === 'mouse' || this.isPointerDown) {
                this.updatePointerPos(e);
            }
        };
        const onPtrUp = () => { this.isPointerDown = false; };

        this.container.addEventListener('pointerdown', onPtrDown);
        this.container.addEventListener('pointermove', onPtrMove);
        window.addEventListener('pointerup', onPtrUp);
        this.container.addEventListener('pointercancel', onPtrUp);
    }

    updatePointerPos(e) {
        // ALWAYS use cachedRect on mobile to prevent catastrophic layout thrashing!
        const rect = this.cachedRect || this.renderer.domElement.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        // Map to -1 to 1 clip space
        const nx = ((clientX - rect.left) / rect.width) * 2 - 1;
        const ny = -((clientY - rect.top) / rect.height) * 2 + 1;

        // Unproject to Z=0 plane
        const vec = new THREE.Vector3(nx, ny, 0.5);
        vec.unproject(this.camera);
        const dir = vec.sub(this.camera.position).normalize();
        const dist = -this.camera.position.z / dir.z;
        const pos = this.camera.position.clone().add(dir.multiplyScalar(dist));

        this.pointerPos.x = pos.x;
        this.pointerPos.y = pos.y + 5; // Offset because finger covers ship
        this.pointerPos.z = 0;
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
                 joyZone.removeEventListener('touchstart', this._jts);
                 joyZone.removeEventListener('touchmove', this._jtm);
                 joyZone.removeEventListener('touchend', this._jte);
                 joyZone.removeEventListener('touchcancel', this._jte);
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
        // Reset state
        this.isRunning = true;
        this.isGameOver = false;
        this.isPaused = false;
        this.distance = 0;
        this.shieldHP = 100;
        this.currentSpeed = this.baseSpeed;
        this.timeScale = 1.0;
        this.ship.position.set(0, -10, 0);
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
            this.activeSkills.ultimate.timer = 10.0;

            // Spawn 2 Drones
            for(let i=0; i<2; i++) {
                let m = this.pools.drones.pop();
                if(!m) {
                    m = new THREE.Mesh(new THREE.OctahedronGeometry(0.5, 0), new THREE.MeshStandardMaterial({color: 0xfde047, emissive: 0xfde047, metalness:0.8}));
                    this.scene.add(m);
                }
                m.visible = true;
                m.userData = { offsetAngle: Math.PI * i, lastFire: 0 };
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
        if (this.audio && this.audio.playTeleport) this.audio.playTeleport();

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
        const moveSpeed = 80 * dt;
        if (!this.activeSkills.teleport.waitingForClick) {
            if (this.isMobile && this.joystickVector && (this.joystickVector.x !== 0 || this.joystickVector.y !== 0)) {
                // Joystick input overrides direct touch tracking
                this.shipTargetPos.x += this.joystickVector.x * moveSpeed;
                this.shipTargetPos.y += this.joystickVector.y * moveSpeed;

                // Keep pointerPos synced so Teleport/Screen bounds remember where the Joystick left off
                this.pointerPos.copy(this.shipTargetPos);
            } else {
                // Touch Drag or Move exactly to pointer
                this.shipTargetPos.copy(this.pointerPos);
            }
            
            // Bounds (Slightly larger Y bounds due to shorter screen height)
            this.shipTargetPos.x = Math.max(-20, Math.min(20, this.shipTargetPos.x));
            this.shipTargetPos.y = Math.max(-10, Math.min(30, this.shipTargetPos.y));
        }

        // Smooth translation
        this.ship.position.lerp(this.shipTargetPos, 15 * dt);

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
            this.fireBullet(this.ship.position);
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
            if (this.activeSkills.ultimate.timer <= 0) {
                this.activeSkills.ultimate.active = false;
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

                    if (now - d.userData.lastFire > 300) {
                        // Find closest enemy
                        let closest = null, minDist = 400;
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
                m.userData.baseX = (Math.random() - 0.5) * 30;
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
                m.position.set((Math.random() - 0.5) * 25, 30, 0);

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
            b.position.addScaledVector(b.userData.dir, 50 * dt);
            if(b.position.y > 40 || b.position.y < -20 || b.position.x > 20 || b.position.x < -20) {
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
                if ((dx * dx + dy * dy) < Math.pow(em.userData.radius + 0.5, 2)) {
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
