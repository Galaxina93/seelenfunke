window.FunkenflugEngine = class FunkenflugEngine {
    constructor(container, layer, callbacks, assets) {
        this.container = container;
        this.layer = layer;
        this.callbacks = callbacks;
        this.assets = assets;

        // Constants & State
        this.isRunning = false;
        this.isGameOver = false;
        this.isDying = false;
        this.deathTimer = 0;
        this.deathStage = 0;
        this.cameraShake = 0;
        this.deathOrigin = new THREE.Vector3();
        this.distance = 0;
        this.scoreMultiplier = 1;
        this.baseSpeed = 10; // units per second
        this.currentSpeed = this.baseSpeed;
        this.timeScale = 1.0;
        this.shieldHP = 100;
        this.maxHullHP = 3;
        this.hullHP = 3;
        this.hullInvulnTimer = 0;
        this.hullFireGroup = null;

        // Weapon Combo & Overdrive System
        this.comboCount = 0;
        this.weaponTier = 1;
        this.comboDecayTimer = 0;

        // Sector Boss ("Kosmischer Wächter" every 1,500m) + Phase 2 Zwillings-Spaltung
        this.nextBossDistance = 1500;
        this.bossActive = false;
        this.bossPhase = 1; // 1 = Haupt-Boss, 2 = 2 kleine Zwillings-Bosse
        this.bossHp = 0;
        this.bossMaxHp = 100;
        this.bossLevel = 0;
        this.bossFireTimer = 0;
        this.bossShotPattern = 0;
        this.bossSpawnTime = 0;
        this.bossCombatTime = 0;
        this.bossIntroTimer = 0;
        this.bossIntroDuration = 0.95;
        this.bossMesh = null;
        this.bossNovaGroup = null;
        this.miniBossMeshes = [];
        this.miniBossActive = [false, false];
        this.miniBossHp = [0, 0];
        this.miniBossMaxHp = 50;
        this.miniBossCombatTime = 0;
        this.miniBossSplitOrigin = new THREE.Vector3(0, 18.5, 0);

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
        this._bulletLeftDir = new THREE.Vector3(-0.15, 0.988, 0).normalize();
        this._bulletRightDir = new THREE.Vector3(0.15, 0.988, 0).normalize();
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
        this.loadedModels = { rocket: false, meteor: false, sharp_stone: false, boss: false, distant_planets: false };
        window.__funkenflugEngine = this;

        // Distant Mystical Planets Biome Transition State
        this.planetBlend = 0.0;
        this._spaceClearColor = new THREE.Color(0x030712);
        this._planetClearColor = new THREE.Color(0x09051a);
        this._currentClearColor = new THREE.Color(0x030712);
        this.planetBiomeGroup = null;
        this.distantPlanets = [];
        this.planetTerrainMat = null;
        this.planetAtmosphereMat = null;
        this.nebulaMaterials = [];

        // Golden Stardust Teleport Trail State (Zero-GC, updates at real-time dtRaw)
        this._teleportAimPos = new THREE.Vector3(0, -10, 0);
        this._lastTeleportAimPos = new THREE.Vector3(0, -10, 0);
        this._hasTeleportAim = false;
        this._teleportDustSpawnAccum = 0;
        this._teleportDustCursor = 0;
        this.teleportBeacon = null;
        this.teleportDustPoints = null;
        this.teleportDustData = null;

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
        this.enemyLasers = [];

        // Player Ship & Skin Stats + Battleship Tactical Camera & Hero Motto Buffs
        this.ship = null;
        this.shipModel = null;
        this.loadedShipModels = {};
        this._gltfLoader = null;
        this.activeSkinKey = (this.assets && this.assets.activeSkin && this.assets.activeSkin.key) ? this.assets.activeSkin.key : 'default';
        this.activeSkinCfg = (this.assets && this.assets.activeSkin) ? this.assets.activeSkin : null;
        this.isBattleshipMode = !!(this.activeSkinCfg && this.activeSkinCfg.category === 'battleship');
        this.baseCameraY = this.isBattleshipMode ? -19 : -15;
        this.baseCameraZ = this.isBattleshipMode ? 40 : 30;
        this.shipMagnetMult = 1.0;
        this.shipHitboxMult = 1.0;
        this.shipSkillDurMult = 1.0;
        this.shipStartShield = 0;
        this.shipSparkDistBonus = 25;
        this.shipWeaponPowerMult = 1.0;
        this.shipSalvoStreams = 1;
        this.mottoMagnetMult = 1.0;
        this.mottoFireRateMult = 1.0;
        this.mottoStartShieldBonus = 0;
        this.mottoSkillDurMult = 1.0;
        this.mottoSparkDistMult = 1.0;
        this.activeMottoText = '';
        this.activeMottoBuffLabel = '';
        this.shipBounds = 1.0; // Collision radius
        this.shipVelocity = new THREE.Vector3();
        this.shipTargetPos = new THREE.Vector3();
        this._bulletOuterLeftDir = new THREE.Vector3(-0.26, 0.965, 0).normalize();
        this._bulletOuterRightDir = new THREE.Vector3(0.26, 0.965, 0).normalize();

        // Skill States
        this.activeSkills = {
            multishoot: { active: false, timer: 0 },
            teleport: { waitingForClick: false, timer: 0 },
            shield: { active: false, timer: 0, mesh: null },
            ultimate: { active: false, timer: 0 }
        };
        this._lastTeleportCountdown = 7;

        // Manufaktur & Funki Sector Boss Profiles (rotierend alle 1.500m)
        this.bossProfiles = [
            { name: 'Lord Lieblos', subtitle: 'Der Massenware-Monolith' },
            { name: 'Der Nebel des Vergessens', subtitle: 'Verschlinger der Erinnerungen' },
            { name: 'Der Paragraphen-Koloss §-0815', subtitle: 'Endgegner der Bürokratie' }
        ];

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
        const aspect = w / h;
        this.camera.aspect = aspect;
        // Adaptive FOV for portrait mobile screens so horizontal playfield width remains generous
        this.camera.fov = aspect < 0.92 ? Math.min(76, Math.round(60 + (0.92 - aspect) * 32)) : 60;
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

    _createGoldDustTexture() {
        const canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 64;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            const grad = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
            grad.addColorStop(0.0, 'rgba(255, 255, 255, 1.0)');
            grad.addColorStop(0.16, 'rgba(254, 240, 138, 0.96)');
            grad.addColorStop(0.45, 'rgba(251, 191, 36, 0.58)');
            grad.addColorStop(0.78, 'rgba(217, 119, 6, 0.14)');
            grad.addColorStop(1.0, 'rgba(0, 0, 0, 0.0)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, 64, 64);

            // Delicate golden starlight cross
            ctx.fillStyle = 'rgba(254, 249, 195, 0.65)';
            ctx.fillRect(31, 10, 2, 44);
            ctx.fillRect(10, 31, 44, 2);
        }
        const tex = new THREE.CanvasTexture(canvas);
        tex.needsUpdate = true;
        return tex;
    }

    _createPlanetAtmosphereTexture() {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 256;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.clearRect(0, 0, 256, 256);
            // Mystical deep-space aurora & planetary horizon glow
            const g1 = ctx.createRadialGradient(85, 110, 5, 85, 110, 120);
            g1.addColorStop(0.0, 'rgba(139, 92, 246, 0.34)');
            g1.addColorStop(0.5, 'rgba(76, 29, 149, 0.14)');
            g1.addColorStop(1.0, 'rgba(0, 0, 0, 0.0)');
            ctx.fillStyle = g1;
            ctx.fillRect(0, 0, 256, 256);

            const g2 = ctx.createRadialGradient(180, 145, 5, 180, 145, 115);
            g2.addColorStop(0.0, 'rgba(245, 158, 11, 0.26)');
            g2.addColorStop(0.5, 'rgba(14, 165, 233, 0.14)');
            g2.addColorStop(1.0, 'rgba(0, 0, 0, 0.0)');
            ctx.fillStyle = g2;
            ctx.fillRect(0, 0, 256, 256);
        }
        const tex = new THREE.CanvasTexture(canvas);
        tex.needsUpdate = true;
        return tex;
    }

    _createPlanetHaloTexture(c0, c1, c2) {
        const canvas = document.createElement('canvas');
        canvas.width = 128;
        canvas.height = 128;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            const grad = ctx.createRadialGradient(64, 64, 12, 64, 64, 64);
            grad.addColorStop(0.0, c0);
            grad.addColorStop(0.45, c1);
            grad.addColorStop(0.78, c2);
            grad.addColorStop(1.0, 'rgba(0, 0, 0, 0.0)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, 128, 128);
        }
        const tex = new THREE.CanvasTexture(canvas);
        tex.needsUpdate = true;
        return tex;
    }

    initTeleportStardust() {
        const goldTex = this._createGoldDustTexture();

        // 1. Golden Stardust Beacon at Target Pointer Position (ALWAYS rendered on top: renderOrder 950, depthTest: false)
        this.teleportBeacon = new THREE.Group();
        this.teleportBeacon.renderOrder = 950;
        const coreSpriteMat = new THREE.SpriteMaterial({
            map: goldTex,
            color: 0xfef08a,
            transparent: true,
            opacity: 0.95,
            depthTest: false,
            depthWrite: false,
            fog: false,
            blending: THREE.AdditiveBlending
        });
        const coreSprite = new THREE.Sprite(coreSpriteMat);
        coreSprite.scale.set(2.9, 2.9, 1);
        coreSprite.renderOrder = 950;

        const innerRing = new THREE.Mesh(
            new THREE.RingGeometry(0.45, 0.62, 24),
            new THREE.MeshBasicMaterial({
                color: 0xfde047,
                side: THREE.DoubleSide,
                transparent: true,
                opacity: 0.85,
                depthTest: false,
                depthWrite: false,
                fog: false,
                blending: THREE.AdditiveBlending
            })
        );
        innerRing.renderOrder = 950;
        const outerRing = new THREE.Mesh(
            new THREE.RingGeometry(0.95, 1.12, 32),
            new THREE.MeshBasicMaterial({
                color: 0xf59e0b,
                side: THREE.DoubleSide,
                transparent: true,
                opacity: 0.65,
                depthTest: false,
                depthWrite: false,
                fog: false,
                blending: THREE.AdditiveBlending
            })
        );
        outerRing.renderOrder = 950;
        this.teleportBeacon.add(coreSprite, innerRing, outerRing);
        this.teleportBeacon.userData = { coreSprite, innerRing, outerRing };
        this.teleportBeacon.visible = false;
        this.scene.add(this.teleportBeacon);

        // 2. Flowing Golden Stardust Comet Trail (Zero-GC Points System, ALWAYS on top)
        const count = this.isMobile ? 90 : 140;
        const positions = new Float32Array(count * 3);
        const colors = new Float32Array(count * 3);
        const velocities = new Float32Array(count * 3);
        const life = new Float32Array(count);
        const maxLife = new Float32Array(count);
        const baseRgb = new Float32Array(count * 3);

        for (let i = 0; i < count; i++) {
            positions[i * 3 + 2] = -500; // Hidden off-camera initially
        }

        const geom = new THREE.BufferGeometry();
        geom.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geom.setAttribute('color', new THREE.BufferAttribute(colors, 3));

        const mat = new THREE.PointsMaterial({
            size: this.isMobile ? 2.1 : 1.85,
            map: goldTex,
            vertexColors: true,
            transparent: true,
            opacity: 1.0,
            depthTest: false,
            depthWrite: false,
            fog: false,
            blending: THREE.AdditiveBlending
        });

        this.teleportDustPoints = new THREE.Points(geom, mat);
        this.teleportDustPoints.renderOrder = 950;
        this.teleportDustPoints.frustumCulled = false;
        this.teleportDustPoints.visible = false;
        this.scene.add(this.teleportDustPoints);

        this.teleportDustData = {
            count,
            positions,
            colors,
            velocities,
            life,
            maxLife,
            baseRgb,
            posAttr: geom.getAttribute('position'),
            colAttr: geom.getAttribute('color')
        };
    }

    spawnTeleportDustMote(x, y, vx, vy, lifeDur = 0.55) {
        if (!this.teleportDustData) return;
        const d = this.teleportDustData;
        const idx = this._teleportDustCursor % d.count;
        this._teleportDustCursor = (this._teleportDustCursor + 1) % d.count;
        const i3 = idx * 3;

        d.positions[i3]     = x + (Math.random() - 0.5) * 0.42;
        d.positions[i3 + 1] = y + (Math.random() - 0.5) * 0.42;
        d.positions[i3 + 2] = (Math.random() - 0.5) * 0.3;

        d.velocities[i3]     = vx + (Math.random() - 0.5) * 2.8;
        d.velocities[i3 + 1] = vy + (Math.random() - 0.5) * 2.8 + 1.1; // Slight upward golden shimmer
        d.velocities[i3 + 2] = (Math.random() - 0.5) * 1.2;

        d.maxLife[idx] = lifeDur * (0.75 + Math.random() * 0.45);
        d.life[idx] = d.maxLife[idx];

        // Rich palette of gold dust: champagne gold, pure solar gold, warm amber
        const pRoll = Math.random();
        if (pRoll < 0.35) {
            d.baseRgb[i3] = 1.0; d.baseRgb[i3 + 1] = 0.96; d.baseRgb[i3 + 2] = 0.65; // Champagne White-Gold
        } else if (pRoll < 0.78) {
            d.baseRgb[i3] = 0.99; d.baseRgb[i3 + 1] = 0.88; d.baseRgb[i3 + 2] = 0.28; // Pure Gold (#fde047)
        } else {
            d.baseRgb[i3] = 0.96; d.baseRgb[i3 + 1] = 0.65; d.baseRgb[i3 + 2] = 0.08; // Warm Amber (#f59e0b)
        }

        d.colors[i3]     = d.baseRgb[i3];
        d.colors[i3 + 1] = d.baseRgb[i3 + 1];
        d.colors[i3 + 2] = d.baseRgb[i3 + 2];

        this.teleportDustPoints.visible = true;
    }

    updateTeleportStardust(dtRaw) {
        if (!this.teleportDustData) return;
        const waiting = !!(this.activeSkills.teleport && this.activeSkills.teleport.waitingForClick);

        if (waiting) {
            // Clamp target to playfield bounds so the player sees the exact valid teleport spot
            let tx = this.pointerPos.x;
            let ty = this.pointerPos.y;
            if (this.bounds) {
                tx = Math.max(this.bounds.xMin, Math.min(this.bounds.xMax, tx));
                ty = Math.max(this.bounds.yMin, Math.min(this.bounds.yMax, ty));
            }
            this._teleportAimPos.set(tx, ty, 0);

            if (!this._hasTeleportAim) {
                this._lastTeleportAimPos.copy(this._teleportAimPos);
                this._hasTeleportAim = true;
            }

            if (this.teleportBeacon) {
                this.teleportBeacon.visible = true;
                this.teleportBeacon.position.copy(this._teleportAimPos);
                const nowSec = performance.now() * 0.001;
                const pulse = 1.0 + Math.sin(nowSec * 9.0) * 0.16;
                this.teleportBeacon.userData.coreSprite.scale.set(2.8 * pulse, 2.8 * pulse, 1);
                this.teleportBeacon.userData.innerRing.rotation.z += dtRaw * 3.8;
                this.teleportBeacon.userData.outerRing.rotation.z -= dtRaw * 2.4;
                this.teleportBeacon.userData.outerRing.scale.setScalar(1.0 + Math.cos(nowSec * 6.5) * 0.1);
            }

            // Emit flowing golden stardust ribbon along mouse movement path
            const dx = this._teleportAimPos.x - this._lastTeleportAimPos.x;
            const dy = this._teleportAimPos.y - this._lastTeleportAimPos.y;
            const moveDist = Math.hypot(dx, dy);

            if (moveDist > 0.04) {
                const steps = Math.min(14, Math.max(2, Math.ceil(moveDist * 3.2)));
                const trailVx = (-dx / Math.max(0.008, dtRaw)) * 0.08;
                const trailVy = (-dy / Math.max(0.008, dtRaw)) * 0.08;
                for (let s = 1; s <= steps; s++) {
                    const t = s / steps;
                    const sx = this._lastTeleportAimPos.x + dx * t;
                    const sy = this._lastTeleportAimPos.y + dy * t;
                    this.spawnTeleportDustMote(sx, sy, trailVx, trailVy, 0.62);
                }
            }

            // Continuous gentle golden stardust sparkle even when holding mouse steady
            this._teleportDustSpawnAccum += dtRaw;
            while (this._teleportDustSpawnAccum >= 0.018) {
                this._teleportDustSpawnAccum -= 0.018;
                const angle = Math.random() * Math.PI * 2;
                const rad = Math.random() * 0.65;
                this.spawnTeleportDustMote(
                    this._teleportAimPos.x + Math.cos(angle) * rad,
                    this._teleportAimPos.y + Math.sin(angle) * rad,
                    Math.cos(angle) * 2.2,
                    Math.sin(angle) * 2.2 + 1.5,
                    0.50
                );
            }

            this._lastTeleportAimPos.copy(this._teleportAimPos);
        } else {
            this._hasTeleportAim = false;
            if (this.teleportBeacon) this.teleportBeacon.visible = false;
        }

        // Update active golden dust motes in real time (unaffected by slow-motion 0.1!)
        const d = this.teleportDustData;
        let anyAlive = false;
        for (let i = 0; i < d.count; i++) {
            if (d.life[i] > 0) {
                d.life[i] -= dtRaw;
                const i3 = i * 3;
                if (d.life[i] <= 0) {
                    d.life[i] = 0;
                    d.positions[i3 + 2] = -500;
                    d.colors[i3] = 0;
                    d.colors[i3 + 1] = 0;
                    d.colors[i3 + 2] = 0;
                } else {
                    anyAlive = true;
                    d.positions[i3]     += d.velocities[i3] * dtRaw;
                    d.positions[i3 + 1] += d.velocities[i3 + 1] * dtRaw;
                    d.positions[i3 + 2] += d.velocities[i3 + 2] * dtRaw;
                    d.velocities[i3]     *= (1 - dtRaw * 2.2);
                    d.velocities[i3 + 1] *= (1 - dtRaw * 2.2);

                    const ratio = Math.max(0, d.life[i] / Math.max(0.01, d.maxLife[i]));
                    const shimmer = 0.78 + 0.22 * Math.sin((d.life[i] * 28.0) + i);
                    const intensity = ratio * shimmer;
                    d.colors[i3]     = d.baseRgb[i3] * intensity;
                    d.colors[i3 + 1] = d.baseRgb[i3 + 1] * intensity;
                    d.colors[i3 + 2] = d.baseRgb[i3 + 2] * intensity;
                }
            }
        }

        if (anyAlive || waiting) {
            d.posAttr.needsUpdate = true;
            d.colAttr.needsUpdate = true;
        } else if (this.teleportDustPoints) {
            this.teleportDustPoints.visible = false;
        }
    }

    initScene() {
        this.scene = new THREE.Scene();
        this.isMobile = window.innerWidth <= 768;

        if (!this.isMobile) {
            this.scene.fog = new THREE.FogExp2(0x030712, 0.008);
        }

        const w = this.container.offsetWidth || 800;
        const h = this.container.offsetHeight || 800;

        // Camera looking slightly down (Zoomed out further from above in Battleship Mode!)
        this.camera = new THREE.PerspectiveCamera(60, w / h, 0.1, 1000);
        this.camera.position.set(0, this.baseCameraY || -15, this.baseCameraZ || 30);
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
            group.userData = { speedMult: speedMult, spanY: spanY, mat: mat, baseOpacity: opacity };
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
        this.nebulaMaterials = [];
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
                this.nebulaMaterials.push(mat);
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

        // --- MYSTICAL DISTANT 3D PLANETS VISTA (Far-away 3D Ringed Worlds & Halos) ---
        this.planetBiomeGroup = new THREE.Group();
        this.planetBiomeGroup.visible = false;
        this.planetBiomeGroup.renderOrder = -25;

        const atmoTex = this._createPlanetAtmosphereTexture();
        this.planetAtmosphereMat = new THREE.MeshBasicMaterial({
            map: atmoTex,
            transparent: true,
            opacity: 0.0,
            depthWrite: false,
            fog: false,
            blending: THREE.AdditiveBlending
        });
        const atmoMesh = new THREE.Mesh(new THREE.PlaneGeometry(195, 155), this.planetAtmosphereMat);
        atmoMesh.position.set(0, 32, -82);
        atmoMesh.renderOrder = -28;
        atmoMesh.frustumCulled = false;
        this.planetBiomeGroup.add(atmoMesh);

        this.distantPlanets = [];
        this.scene.add(this.planetBiomeGroup);

        // --- GOLDEN STARDUST TELEPORT TRAIL & BEACON ---
        this.initTeleportStardust();

        // Window resize
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.container);
        this.resize();
    }

    updateBiomeTransition(dtRaw, dt) {
        // After ~400m of flight, mystical distant 3D planets & cosmic aurora emerge on the far horizon
        // Cycle every 1,850m: [0..400m Deep Space] -> [400..1720m Mystical Distant Planets Vista] -> ...
        const cycleDist = this.distance % 1850;
        const targetBlend = (this.distance >= 400 && cycleDist >= 400 && cycleDist <= 1720) ? 1.0 : 0.0;

        if (Math.abs(this.planetBlend - targetBlend) > 0.0005) {
            const step = dtRaw * 0.24; // ~4.1s dreamy cinematic crossfade
            if (this.planetBlend < targetBlend) {
                this.planetBlend = Math.min(targetBlend, this.planetBlend + step);
            } else {
                this.planetBlend = Math.max(targetBlend, this.planetBlend - step);
            }

            // Smoothstep easing for organic transition
            const b = this.planetBlend;
            const s = b * b * (3 - 2 * b);

            // 1. Interpolate deep-space background tone & fog
            this._currentClearColor.lerpColors(this._spaceClearColor, this._planetClearColor, s);
            if (this.renderer) {
                this.renderer.setClearColor(this._currentClearColor, 1);
            }
            if (this.scene && this.scene.fog) {
                this.scene.fog.color.copy(this._currentClearColor);
            }

            // 2. Gently modulate Nebulae & Far Star Layers so the distant 3D planets & halos shine mystically
            for (let i = 0; i < this.nebulaMaterials.length; i++) {
                this.nebulaMaterials[i].opacity = 0.85 * (1 - s * 0.35);
            }
            if (this.starLayers && this.starLayers.length >= 2) {
                this.starLayers[0].userData.mat.opacity = this.starLayers[0].userData.baseOpacity * (1 - s * 0.25);
            }

            // 3. Smoothly fade in the far-away 3D Planets & their atmospheric coronas
            if (this.planetBiomeGroup) {
                this.planetBiomeGroup.visible = (s > 0.005);
                if (this.planetAtmosphereMat) {
                    this.planetAtmosphereMat.opacity = s * 0.72;
                }
                if (this.planetTerrainMat) {
                    this.planetTerrainMat.opacity = s * 0.92;
                }
                for (let i = 0; i < this.distantPlanets.length; i++) {
                    const dp = this.distantPlanets[i];
                    if (dp.haloMat) {
                        dp.haloMat.opacity = s * 0.78;
                    }
                }
            }
        }

        // Slowly rotate & drift the distant 3D planets in deep space (far behind the action plane)
        if (this.planetBiomeGroup && this.planetBiomeGroup.visible && this.distantPlanets.length > 0) {
            const spanY = 186;
            for (let i = 0; i < this.distantPlanets.length; i++) {
                const dp = this.distantPlanets[i];
                if (dp.mesh) {
                    dp.mesh.rotation.y += dp.rotSpeed * dtRaw;
                }
                if (dp.holder) {
                    dp.holder.position.y -= this.currentSpeed * dp.parallax * dt;
                    if (dp.holder.position.y < -52) {
                        dp.holder.position.y += spanY;
                    }
                }
            }
        }
    }

    loadAssets() {
        // Player Ship Container
        this.ship = new THREE.Group();
        this.scene.add(this.ship);

        // 3D Burning Hull Fire & Smoke Emitters (activates after 1st & 2nd unshielded hit)
        this.hullFireGroup = new THREE.Group();
        this.hullFireGroup.visible = false;
        const fireCoreGeo = new THREE.ConeGeometry(0.48, 1.65, 7);
        const fireOuterMat = new THREE.MeshBasicMaterial({
            color: 0xf97316,
            transparent: true,
            opacity: 0.88,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        });
        const fireInnerMat = new THREE.MeshBasicMaterial({
            color: 0xfef08a,
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        });
        const makeFlameCluster = (x, y, z) => {
            const g = new THREE.Group();
            g.position.set(x, y, z);
            const outer = new THREE.Mesh(fireCoreGeo, fireOuterMat);
            const inner = new THREE.Mesh(fireCoreGeo, fireInnerMat);
            inner.scale.set(0.6, 0.65, 0.6);
            g.add(outer, inner);
            return g;
        };
        this.hullFlameLeft = makeFlameCluster(-0.68, -0.35, 0.45);
        this.hullFlameRight = makeFlameCluster(0.65, 0.15, 0.48);
        this.hullFlameCenter = makeFlameCluster(0.0, -0.55, 0.55);
        this.hullFireGroup.add(this.hullFlameLeft, this.hullFlameRight, this.hullFlameCenter);
        this.ship.add(this.hullFireGroup);

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
            this._gltfLoader = loader;
            loader.load(this.assets.rocket, (gltf) => {
                const defaultScene = gltf.scene;

                // Modell anhand der Konfiguration ausrichten (Grad in Bogenmaß konvertieren)
                defaultScene.position.set(this.modelConfig.position.x, this.modelConfig.position.y, this.modelConfig.position.z);
                defaultScene.rotation.set(
                    this.modelConfig.rotation.x * (Math.PI / 180),
                    this.modelConfig.rotation.y * (Math.PI / 180),
                    this.modelConfig.rotation.z * (Math.PI / 180)
                );
                defaultScene.scale.set(this.modelConfig.scale.x, this.modelConfig.scale.y, this.modelConfig.scale.z);

                // Add emissive engine glow to materials & optimize texture sampling
                defaultScene.traverse((child) => {
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

                this.loadedShipModels['default'] = defaultScene;
                if (this.activeSkinKey === 'default' || !this.loadedShipModels[this.activeSkinKey]) {
                    if (this.shipModel) this.ship.remove(this.shipModel);
                    this.shipModel = defaultScene;
                    this.ship.add(this.shipModel);
                }
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

            // Load 3D Sector Boss GLB ("Kosmischer Wächter" — Vertex-Colored 3D Dreadnought ~6.8KB)
            if (this.assets.boss) {
                loader.load(this.assets.boss, (gltf) => {
                    const bossScene = gltf.scene;
                    bossScene.scale.set(1.18, 1.18, 1.18);
                    const bossMat = new THREE.MeshBasicMaterial({
                        vertexColors: true,
                        toneMapped: false
                    });
                    bossScene.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = false;
                            child.receiveShadow = false;
                            child.frustumCulled = false;
                            child.material = bossMat;
                        }
                    });
                    if (this.bossMesh) {
                        if (this.bossMesh.userData.fallbackCore) {
                            this.bossMesh.userData.fallbackCore.visible = false;
                        }
                        this.bossMesh.userData.glbModel = bossScene;
                        this.bossMesh.add(bossScene);
                    }
                    if (this.miniBossMeshes && this.miniBossMeshes.length === 2) {
                        for (let mi = 0; mi < 2; mi++) {
                            const mb = this.miniBossMeshes[mi];
                            if (mb) {
                                if (mb.userData.fallbackCore) mb.userData.fallbackCore.visible = false;
                                const cloneScene = bossScene.clone();
                                cloneScene.scale.set(0.68, 0.68, 0.68);
                                mb.userData.glbModel = cloneScene;
                                mb.add(cloneScene);
                            }
                        }
                    }
                    this.loadedModels.boss = true;
                    this._prewarmGpuObjects();
                }, undefined, (e) => { console.warn("Could not load boss GLTF."); });
            }

            // Load Mystical Distant 3D Planets GLB ("PlanetAurelia", "PlanetCelestia", "PlanetSolis" ~139KB)
            if (this.assets.distant_planets && this.planetBiomeGroup) {
                loader.load(this.assets.distant_planets, (gltf) => {
                    this.planetTerrainMat = new THREE.MeshBasicMaterial({
                        vertexColors: true,
                        transparent: true,
                        opacity: 0.0,
                        depthWrite: false,
                        fog: false,
                        toneMapped: false
                    });

                    const xMult = this.isMobile ? 0.62 : 1.0;
                    const planetConfigs = [
                        {
                            name: 'PlanetAurelia',
                            x: -24 * xMult, y: 34, z: -64,
                            scale: 0.92, rotX: 0.22, rotZ: -0.14, rotSpeed: 0.09,
                            parallax: 0.18, haloScale: 54,
                            haloColors: ['rgba(245, 158, 11, 0.42)', 'rgba(139, 92, 246, 0.24)', 'rgba(76, 29, 149, 0.08)']
                        },
                        {
                            name: 'PlanetCelestia',
                            x: 24 * xMult, y: 96, z: -70,
                            scale: 0.96, rotX: 0.18, rotZ: 0.16, rotSpeed: -0.12,
                            parallax: 0.14, haloScale: 38,
                            haloColors: ['rgba(56, 189, 248, 0.42)', 'rgba(45, 212, 191, 0.22)', 'rgba(30, 58, 138, 0.08)']
                        },
                        {
                            name: 'PlanetSolis',
                            x: -16 * xMult, y: 158, z: -76,
                            scale: 0.96, rotX: 0.25, rotZ: -0.10, rotSpeed: 0.14,
                            parallax: 0.11, haloScale: 30,
                            haloColors: ['rgba(251, 113, 133, 0.38)', 'rgba(245, 158, 11, 0.20)', 'rgba(136, 19, 55, 0.06)']
                        }
                    ];

                    this.distantPlanets = [];
                    planetConfigs.forEach((cfg, idx) => {
                        const rawNode = gltf.scene.getObjectByName(cfg.name) || gltf.scene.children[idx];
                        if (!rawNode) return;
                        const planetNode = rawNode.clone();
                        planetNode.position.set(0, 0, 0);
                        planetNode.scale.setScalar(cfg.scale);
                        planetNode.rotation.set(cfg.rotX, 0, cfg.rotZ);
                        planetNode.renderOrder = -25;
                        planetNode.traverse((child) => {
                            if (child.isMesh) {
                                child.castShadow = false;
                                child.receiveShadow = false;
                                child.frustumCulled = false;
                                child.renderOrder = -25;
                                child.material = this.planetTerrainMat;
                            }
                        });

                        const haloTex = this._createPlanetHaloTexture(cfg.haloColors[0], cfg.haloColors[1], cfg.haloColors[2]);
                        const haloMat = new THREE.SpriteMaterial({
                            map: haloTex,
                            transparent: true,
                            opacity: 0.0,
                            depthWrite: false,
                            fog: false,
                            blending: THREE.AdditiveBlending
                        });
                        const haloSprite = new THREE.Sprite(haloMat);
                        haloSprite.scale.set(cfg.haloScale, cfg.haloScale, 1);
                        haloSprite.position.set(0, 0, -3);
                        haloSprite.renderOrder = -26;

                        const holder = new THREE.Group();
                        holder.position.set(cfg.x, cfg.y, cfg.z);
                        holder.renderOrder = -25;
                        holder.add(haloSprite, planetNode);
                        this.planetBiomeGroup.add(holder);

                        this.distantPlanets.push({
                            holder,
                            mesh: planetNode,
                            haloMat,
                            rotSpeed: cfg.rotSpeed,
                            parallax: cfg.parallax,
                            initialY: cfg.y
                        });
                    });

                    this.loadedModels.distant_planets = true;
                    if (this.renderer) this.renderer.compile(this.scene, this.camera);
                }, undefined, (e) => { console.warn("Could not load distant planets GLTF."); });
            }
        } else {
            setTimeout(() => { if (this.callbacks.onLoadComplete) this.callbacks.onLoadComplete(); }, 100);
        }

        // Initialize materials for pools
        this.matBullet = new THREE.MeshBasicMaterial({ color: 0x38bdf8 }); // Light blue laser
        this.geomBullet = new THREE.CylinderGeometry(0.15, 0.15, 1.2, this.isMobile ? 4 : 8);

        if (this.assets && this.assets.activeMotto) {
            this.applyMottoBuff(this.assets.activeMotto);
        }
        if (this.assets && this.assets.activeSkin) {
            this.applyShipSkin(this.assets.activeSkin);
        }

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
            new THREE.BoxGeometry(11, 1.2, 1.6)
        ];

        this.matCollect = [
            new THREE.MeshBasicMaterial({ color: 0xfde047 }), // 0: Funken (Yellow)
            new THREE.MeshBasicMaterial({ color: 0x60a5fa }), // 1: Cooldown (Blue)
            new THREE.MeshBasicMaterial({ color: 0xf43f5e })  // 2: Rotes Herzchen (Ruby Red)
        ];
        // Kugel rund auf Desktop, eckiger auf Mobile
        this.geomCollect = new THREE.SphereGeometry(0.6, this.isMobile ? 8 : 16, this.isMobile ? 8 : 16);

        // Echte 3D-Herz-Geometrie für das seltene Rote Herz-Collectible (Rumpf-Reparatur)
        const heartShape = new THREE.Shape();
        heartShape.moveTo(0, 0.25);
        heartShape.bezierCurveTo(0, 0.58, -0.55, 0.68, -0.68, 0.22);
        heartShape.bezierCurveTo(-0.82, -0.25, -0.22, -0.62, 0, -0.88);
        heartShape.bezierCurveTo(0.22, -0.62, 0.82, -0.25, 0.68, 0.22);
        heartShape.bezierCurveTo(0.55, 0.68, 0, 0.58, 0, 0.25);
        this.geomHeart = new THREE.ExtrudeGeometry(heartShape, {
            depth: 0.28,
            bevelEnabled: true,
            bevelSegments: 2,
            steps: 1,
            bevelSize: 0.08,
            bevelThickness: 0.08
        });
        this.geomHeart.center();
        this.geomHeartRing = new THREE.RingGeometry(0.82, 1.02, 24);
        this.matHeartRing = new THREE.MeshBasicMaterial({
            color: 0xfda4af,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.82,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        });

        // Pool materials for particles to prevent recreating material objects 100 times
        this.matParticleOrange = new THREE.MeshBasicMaterial({ color: 0xf97316 });
        this.matParticleYellow = new THREE.MeshBasicMaterial({ color: 0xfef08a });
        this.matParticleBlue   = new THREE.MeshBasicMaterial({ color: 0x60a5fa });
        this.matParticleWhite  = new THREE.MeshBasicMaterial({ color: 0xffffff });
        this.matParticleGold   = new THREE.MeshBasicMaterial({ color: 0xfde047 });
        this.matParticlePurple = new THREE.MeshBasicMaterial({ color: 0xc084fc });
        this.matParticleRed    = new THREE.MeshBasicMaterial({ color: 0xef4444 });
        this.matParticleSmoke  = new THREE.MeshBasicMaterial({ color: 0x475569, transparent: true, opacity: 0.78 });
        this.geomParticle      = new THREE.TetrahedronGeometry(0.3);

        // Reusable Ship Destruction Shockwave & Core Flash (Zero-GC)
        const swGeo = new THREE.RingGeometry(0.8, 1.35, 32);
        this.deathShockwaveMat = new THREE.MeshBasicMaterial({
            color: 0xfbbf24,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.9,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        });
        this.deathShockwaveRing = new THREE.Mesh(swGeo, this.deathShockwaveMat);
        this.deathShockwaveRing.visible = false;
        this.scene.add(this.deathShockwaveRing);

        const cfGeo = new THREE.SphereGeometry(1.6, 16, 16);
        this.deathCoreFlashMat = new THREE.MeshBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        });
        this.deathCoreFlash = new THREE.Mesh(cfGeo, this.deathCoreFlashMat);
        this.deathCoreFlash.visible = false;
        this.scene.add(this.deathCoreFlash);

        // Pre-build Sector Boss Mesh ("Kosmischer Wächter"), 3D Licht-Nova & Enemy Plasma Projectiles Pool
        this.bossMesh = new THREE.Group();
        this.bossMesh.frustumCulled = false;
        const bossCoreGeo = new THREE.OctahedronGeometry(2.6, 1);
        const bossCoreMat = new THREE.MeshBasicMaterial({ color: 0xe11d48, wireframe: false });
        const bossFallbackCore = new THREE.Mesh(bossCoreGeo, bossCoreMat);
        bossFallbackCore.frustumCulled = false;
        const bossCoreWire = new THREE.Mesh(
            new THREE.OctahedronGeometry(2.75, 1),
            new THREE.MeshBasicMaterial({ color: 0xfda4af, wireframe: true, transparent: true, opacity: 0.75 })
        );
        bossCoreWire.frustumCulled = false;
        const bossRing1 = new THREE.Mesh(
            new THREE.TorusGeometry(3.7, 0.22, 8, 28),
            new THREE.MeshBasicMaterial({ color: 0xf59e0b })
        );
        bossRing1.frustumCulled = false;
        const bossRing2 = new THREE.Mesh(
            new THREE.TorusGeometry(4.3, 0.18, 8, 28),
            new THREE.MeshBasicMaterial({ color: 0x8b5cf6 })
        );
        bossRing2.frustumCulled = false;
        bossRing2.rotation.x = Math.PI / 3;
        this.bossMesh.add(bossFallbackCore, bossCoreWire, bossRing1, bossRing2);
        this.bossMesh.userData = { ring1: bossRing1, ring2: bossRing2, wire: bossCoreWire, fallbackCore: bossFallbackCore, glbModel: null };
        this.bossMesh.visible = false;
        this.scene.add(this.bossMesh);

        // Pre-build 2 Twin Mini-Bosses ("Zwillings-Spaltung" für Phase 2 nach Tod des Haupt-Bosses — Zero-GC)
        this.miniBossMeshes = [];
        for (let mi = 0; mi < 2; mi++) {
            const mbGroup = new THREE.Group();
            mbGroup.frustumCulled = false;
            const mbCore = new THREE.Mesh(
                new THREE.OctahedronGeometry(1.65, 1),
                new THREE.MeshBasicMaterial({ color: mi === 0 ? 0xf43f5e : 0xa855f7 })
            );
            mbCore.frustumCulled = false;
            const mbWire = new THREE.Mesh(
                new THREE.OctahedronGeometry(1.78, 1),
                new THREE.MeshBasicMaterial({ color: 0xfde047, wireframe: true, transparent: true, opacity: 0.78 })
            );
            mbWire.frustumCulled = false;
            const mbRing1 = new THREE.Mesh(
                new THREE.TorusGeometry(2.35, 0.15, 8, 24),
                new THREE.MeshBasicMaterial({ color: mi === 0 ? 0xf59e0b : 0x38bdf8 })
            );
            mbRing1.frustumCulled = false;
            const mbRing2 = new THREE.Mesh(
                new THREE.TorusGeometry(2.75, 0.12, 8, 24),
                new THREE.MeshBasicMaterial({ color: mi === 0 ? 0xec4899 : 0x8b5cf6 })
            );
            mbRing2.frustumCulled = false;
            mbRing2.rotation.x = Math.PI / 3;
            mbGroup.add(mbCore, mbWire, mbRing1, mbRing2);
            mbGroup.userData = { ring1: mbRing1, ring2: mbRing2, wire: mbWire, fallbackCore: mbCore, glbModel: null };
            mbGroup.visible = false;
            this.scene.add(mbGroup);
            this.miniBossMeshes.push(mbGroup);
        }

        // Pre-allocated 3D Licht-Nova (Supernova Arrival Effect for Sector Boss — Zero-GC)
        this.bossNovaGroup = new THREE.Group();
        this.bossNovaGroup.position.set(0, 18.5, 0);
        this.bossNovaGroup.frustumCulled = false;

        const novaCoreMat = new THREE.MeshBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 1.0,
            depthWrite: false,
            fog: false,
            blending: THREE.AdditiveBlending
        });
        const novaCore = new THREE.Mesh(new THREE.SphereGeometry(2.4, 20, 20), novaCoreMat);
        novaCore.frustumCulled = false;

        const novaHaloMat = new THREE.SpriteMaterial({
            map: this._createNovaStarburstTexture(),
            color: 0xfef08a,
            transparent: true,
            opacity: 1.0,
            depthWrite: false,
            fog: false,
            blending: THREE.AdditiveBlending
        });
        const novaHalo = new THREE.Sprite(novaHaloMat);
        novaHalo.frustumCulled = false;
        novaHalo.scale.set(16, 16, 1);

        const novaRing1Mat = new THREE.MeshBasicMaterial({
            color: 0xfde047,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
            fog: false,
            blending: THREE.AdditiveBlending
        });
        const novaRing1 = new THREE.Mesh(new THREE.RingGeometry(1.1, 1.95, 40), novaRing1Mat);
        novaRing1.frustumCulled = false;

        const novaRing2Mat = new THREE.MeshBasicMaterial({
            color: 0xf43f5e,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.9,
            depthWrite: false,
            fog: false,
            blending: THREE.AdditiveBlending
        });
        const novaRing2 = new THREE.Mesh(new THREE.RingGeometry(0.85, 1.45, 40), novaRing2Mat);
        novaRing2.frustumCulled = false;
        novaRing2.rotation.x = 0.55;

        this.bossNovaGroup.add(novaCore, novaHalo, novaRing1, novaRing2);
        this.bossNovaGroup.userData = {
            core: novaCore,
            coreMat: novaCoreMat,
            halo: novaHalo,
            haloMat: novaHaloMat,
            ring1: novaRing1,
            ring1Mat: novaRing1Mat,
            ring2: novaRing2,
            ring2Mat: novaRing2Mat
        };
        this.bossNovaGroup.visible = false;
        this.scene.add(this.bossNovaGroup);

        this.geomEnemyLaser = new THREE.SphereGeometry(0.5, 8, 8);
        this.matEnemyLaser = new THREE.MeshBasicMaterial({ color: 0xf43f5e });

        // Populate pools - Ausreichend Instanzen für 360°-Boss-Nova-Meteoriten + reguläre Wellen
        const bulletCount = 120;
        const enemyCount = this.isMobile ? 34 : 52;
        const collectCount = this.isMobile ? 12 : 20;
        const particleCount = this.isMobile ? 85 : 150;
        const enemyLaserCount = 24;

        for(let i=0; i<bulletCount; i++) this.pools.bullets.push(this.createBulletMesh());
        for(let i=0; i<enemyCount; i++) this.pools.enemies.push(this.createEnemyMesh(i));
        for(let i=0; i<collectCount; i++) this.pools.collectibles.push(this.createCollectMesh());
        for(let i=0; i<particleCount; i++) this.pools.particles.push(this.createParticleMesh());
        for(let i=0; i<enemyLaserCount; i++) {
            const el = new THREE.Mesh(this.geomEnemyLaser, this.matEnemyLaser);
            el.frustumCulled = false;
            el.visible = false;
            el.userData = { vel: new THREE.Vector3(0, -25, 0) };
            this.scene.add(el);
            this.pools.lasers.push(el);
        }

        this._prewarmGpuObjects();
    }

    _createNovaStarburstTexture() {
        const canvas = document.createElement('canvas');
        canvas.width = 128;
        canvas.height = 128;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            const grad = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
            grad.addColorStop(0.0, 'rgba(255, 255, 255, 1.0)');
            grad.addColorStop(0.22, 'rgba(254, 240, 138, 0.95)');
            grad.addColorStop(0.52, 'rgba(251, 191, 36, 0.45)');
            grad.addColorStop(0.82, 'rgba(244, 63, 94, 0.16)');
            grad.addColorStop(1.0, 'rgba(0, 0, 0, 0.0)');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, 128, 128);

            // 8-pointed supernova diffraction spikes
            ctx.save();
            ctx.translate(64, 64);
            for (let i = 0; i < 4; i++) {
                ctx.rotate(Math.PI / 4);
                const len = (i % 2 === 0) ? 62 : 44;
                const w = (i % 2 === 0) ? 3.2 : 2.0;
                const rayGrad = ctx.createLinearGradient(-len, 0, len, 0);
                rayGrad.addColorStop(0.0, 'rgba(255, 255, 255, 0.0)');
                rayGrad.addColorStop(0.5, 'rgba(255, 255, 255, 0.92)');
                rayGrad.addColorStop(1.0, 'rgba(255, 255, 255, 0.0)');
                ctx.fillStyle = rayGrad;
                ctx.fillRect(-len, -w * 0.5, len * 2, w);
            }
            ctx.restore();
        }
        const tex = new THREE.CanvasTexture(canvas);
        tex.needsUpdate = true;
        return tex;
    }

    _prewarmGpuObjects() {
        if (!this.renderer || !this.scene || !this.camera) return;
        this._ensureShieldMesh();
        const wasShieldVis = (this.activeSkills && this.activeSkills.shield && this.activeSkills.shield.mesh) ? this.activeSkills.shield.mesh.visible : false;
        const wasBossVis = this.bossMesh ? this.bossMesh.visible : false;
        const wasNovaVis = this.bossNovaGroup ? this.bossNovaGroup.visible : false;
        const wasMini0Vis = (this.miniBossMeshes && this.miniBossMeshes[0]) ? this.miniBossMeshes[0].visible : false;
        const wasMini1Vis = (this.miniBossMeshes && this.miniBossMeshes[1]) ? this.miniBossMeshes[1].visible : false;

        if (this.activeSkills && this.activeSkills.shield && this.activeSkills.shield.mesh && !wasShieldVis) {
            this.activeSkills.shield.mesh.visible = true;
            this.activeSkills.shield.mesh.scale.setScalar(0.0001);
        }
        if (this.bossMesh && !wasBossVis) {
            this.bossMesh.visible = true;
            this.bossMesh.scale.setScalar(0.0001);
        }
        if (this.bossNovaGroup && !wasNovaVis) {
            this.bossNovaGroup.visible = true;
            this.bossNovaGroup.scale.setScalar(0.0001);
        }
        if (this.miniBossMeshes && this.miniBossMeshes[0] && !wasMini0Vis) {
            this.miniBossMeshes[0].visible = true;
            this.miniBossMeshes[0].scale.setScalar(0.0001);
        }
        if (this.miniBossMeshes && this.miniBossMeshes[1] && !wasMini1Vis) {
            this.miniBossMeshes[1].visible = true;
            this.miniBossMeshes[1].scale.setScalar(0.0001);
        }

        this.renderer.compile(this.scene, this.camera);
        if (!this.isRunning) {
            this.renderer.render(this.scene, this.camera);
        }

        if (this.activeSkills && this.activeSkills.shield && this.activeSkills.shield.mesh && !wasShieldVis) {
            this.activeSkills.shield.mesh.visible = false;
            this.activeSkills.shield.mesh.scale.setScalar(1.0);
        }
        if (this.bossMesh && !wasBossVis) {
            this.bossMesh.visible = false;
            this.bossMesh.scale.setScalar(1.0);
        }
        if (this.bossNovaGroup && !wasNovaVis) {
            this.bossNovaGroup.visible = false;
            this.bossNovaGroup.scale.setScalar(1.0);
        }
        if (this.miniBossMeshes && this.miniBossMeshes[0] && !wasMini0Vis) {
            this.miniBossMeshes[0].visible = false;
            this.miniBossMeshes[0].scale.setScalar(1.0);
        }
        if (this.miniBossMeshes && this.miniBossMeshes[1] && !wasMini1Vis) {
            this.miniBossMeshes[1].visible = false;
            this.miniBossMeshes[1].scale.setScalar(1.0);
        }
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

        // 3D Feindliches Raumschiff (Raider-Jäger für Schlachtschiff-Gefechte!)
        const fallbackRaider = new THREE.Group();
        if (!this.geomRaiderHull) {
            this.geomRaiderHull = new THREE.ConeGeometry(0.95, 2.8, 6);
            this.geomRaiderHull.rotateX(-Math.PI / 2); // Zeigt nach unten zum Spieler
            this.geomRaiderWing = new THREE.BoxGeometry(3.2, 0.75, 0.16);
            this.geomRaiderCore = new THREE.OctahedronGeometry(0.48, 0);
            this.matRaiderHull = new THREE.MeshBasicMaterial({ color: 0x1e293b });
            this.matRaiderWing = new THREE.MeshBasicMaterial({ color: 0xef4444 });
            this.matRaiderCore = new THREE.MeshBasicMaterial({ color: 0xfde047 });
        }
        const rHull = new THREE.Mesh(this.geomRaiderHull, this.matRaiderHull);
        const rWing = new THREE.Mesh(this.geomRaiderWing, this.matRaiderWing);
        rWing.position.set(0, 0.25, 0);
        const rCore = new THREE.Mesh(this.geomRaiderCore, this.matRaiderCore);
        rCore.position.set(0, -0.1, 0.35);
        fallbackRaider.add(rHull, rWing, rCore);
        fallbackRaider.visible = false;
        
        container.add(fallbackNormal);
        container.add(fallbackElite);
        container.add(fallbackWall);
        container.add(fallbackRaider);
        
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
            isRaider: false,
            raiderFireTimer: 0,
            fallbackNormal: fallbackNormal,
            fallbackElite: fallbackElite,
            fallbackWall: fallbackWall,
            fallbackRaider: fallbackRaider,
            meshGlbMeteor: null,
            meshGlbSharp: null
        };
        
        this.scene.add(container); 
        return container;
    }
    createCollectMesh() {
        const m = new THREE.Mesh(this.geomCollect, this.matCollect[0]);
        m.frustumCulled = false;
        const heartRing = new THREE.Mesh(this.geomHeartRing, this.matHeartRing);
        heartRing.frustumCulled = false;
        heartRing.visible = false;
        m.add(heartRing);
        m.visible = false;
        m.userData = { type: 0, radius: 0.65, heartRing: heartRing };
        this.scene.add(m);
        return m;
    }
    _configureCollectible(c, type) {
        c.userData.type = type;
        c.geometry = (type === 2) ? this.geomHeart : this.geomCollect;
        c.material = this.matCollect[type] || this.matCollect[0];
        c.rotation.set(0, 0, 0);
        c.userData.radius = (type === 2) ? 0.95 : 0.65;
        if (c.userData.heartRing) {
            c.userData.heartRing.visible = (type === 2);
        }
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
                this.updateScreenCoords(e.changedTouches[0].clientX, e.changedTouches[0].clientY, true);
                
                if (this.activeSkills.teleport.waitingForClick) {
                    this.executeTeleport();
                }
            }
        };
        this._onTouchMove = (e) => {
            if (!this.isRunning || this.activeTouchId === null) return;
            for(let i=0; i<e.changedTouches.length; i++) {
                if (e.changedTouches[i].identifier === this.activeTouchId) {
                    this.updateScreenCoords(e.changedTouches[i].clientX, e.changedTouches[i].clientY, true);
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

    updateScreenCoords(clientX, clientY, isTouch = false) {
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
            // Auf Touchscreens leicht oberhalb der Fingerkuppe (+1.6 Einheiten) fliegen, damit der Daumen das Raumschiff nicht verdeckt
            const touchOffsetY = (isTouch && !this.activeSkills.teleport.waitingForClick) ? 1.6 : 0;
            const maxY = (this.bounds && typeof this.bounds.yMax === 'number') ? this.bounds.yMax : 12;
            this.pointerPos.y = Math.min(maxY, this._intersectTarget.y + touchOffsetY);
            this.pointerPos.z = 0;
        }
    }

    updatePointerPos(e) {
        if (e) this.updateScreenCoords(e.clientX, e.clientY, e.pointerType === 'touch');
    }

    updateTouchPos(touch) {
        if (touch) this.updateScreenCoords(touch.clientX, touch.clientY, true);
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
        this.isDying = false;
        this.deathTimer = 0;
        this.deathStage = 0;
        this.cameraShake = 0;
        this.isPaused = false;
        this.distance = 0;
        this.shieldHP = 0; // Starts at 0, goes to 100 when skill activated
        this.hullHP = this.maxHullHP;
        this.hullInvulnTimer = 0;
        if (this.hullFireGroup) this.hullFireGroup.visible = false;
        if (this.callbacks && this.callbacks.onHullUpdate) {
            this.callbacks.onHullUpdate(this.hullHP, this.maxHullHP);
        }
        this.currentSpeed = this.baseSpeed;
        this.timeScale = 1.0;
        this.comboCount = 0;
        this.weaponTier = 1;
        this.comboDecayTimer = 0;
        this.nextBossDistance = 1500;
        this.bossActive = false;
        this.bossPhase = 1;
        this.bossHp = 0;
        this.bossMaxHp = 100;
        this.bossLevel = 0;
        this.bossFireTimer = 0;
        this.bossCombatTime = 0;
        this.bossIntroTimer = 0;
        this.miniBossActive = [false, false];
        this.miniBossHp = [0, 0];
        this.miniBossCombatTime = 0;
        if (this.bossMesh) this.bossMesh.visible = false;
        if (this.bossNovaGroup) this.bossNovaGroup.visible = false;
        if (this.miniBossMeshes && this.miniBossMeshes.length === 2) {
            this.miniBossMeshes[0].visible = false;
            this.miniBossMeshes[1].visible = false;
        }
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
        if (this.deathShockwaveRing) this.deathShockwaveRing.visible = false;
        if (this.deathCoreFlash) this.deathCoreFlash.visible = false;
        if (this.teleportBeacon) this.teleportBeacon.visible = false;
        if (this.teleportDustPoints) this.teleportDustPoints.visible = false;
        this._hasTeleportAim = false;
        this.planetBlend = 0.0;
        if (this.planetBiomeGroup) this.planetBiomeGroup.visible = false;
        if (this.planetTerrainMat) this.planetTerrainMat.opacity = 0.0;
        if (this.planetAtmosphereMat) this.planetAtmosphereMat.opacity = 0.0;
        if (this.distantPlanets && this.distantPlanets.length > 0) {
            for (let i = 0; i < this.distantPlanets.length; i++) {
                const dp = this.distantPlanets[i];
                if (dp.holder) dp.holder.position.y = dp.initialY;
                if (dp.haloMat) dp.haloMat.opacity = 0.0;
            }
        }
        if (this.renderer) this.renderer.setClearColor(this._spaceClearColor, 1);
        if (this.scene && this.scene.fog) this.scene.fog.color.copy(this._spaceClearColor);
        if (this.camera) {
            this.camera.position.set(0, this.baseCameraY || -15, this.baseCameraZ || 30);
            this.updateBounds();
        }

        // Hide all active entities
        [...this.bullets, ...this.enemies, ...this.collectibles, ...this.particles, ...this.drones, ...this.enemyLasers].forEach(m => {
            m.visible = false;
        });
        this.pools.bullets.push(...this.bullets); this.bullets = [];
        this.pools.enemies.push(...this.enemies); this.enemies = [];
        this.pools.collectibles.push(...this.collectibles); this.collectibles = [];
        this.pools.particles.push(...this.particles); this.particles = [];
        this.pools.drones.push(...this.drones); this.drones = [];
        this.pools.lasers.push(...this.enemyLasers); this.enemyLasers = [];

        // Reset skills
        for(let k in this.activeSkills) {
            if (k === 'shield') {
                if (this.activeSkills.shield.mesh) {
                    this.activeSkills.shield.mesh.visible = false;
                }
            } else if(this.activeSkills[k].mesh) {
                if (this.ship) this.ship.remove(this.activeSkills[k].mesh);
                this.scene.remove(this.activeSkills[k].mesh);
                this.activeSkills[k].mesh = null;
            }
            this.activeSkills[k].active = false;
            this.activeSkills[k].waitingForClick = false;
        }
        this._lastReportedShield = 0;
        this.shieldVisualOpacity = 0.0;
        this.shieldVisualScale = 0.7;
        this.shieldAnimTime = 0.0;

        // Falls der aktive Schiff-Skin oder das Helden-Motto mit Start-Schild ausgestattet ist
        if (this.shipStartShield > 0) {
            this._activateShieldWithHP(this.shipStartShield, 20.0 * (this.shipSkillDurMult || 1.0));
        } else {
            this.callbacks.onShieldUpdate(0);
        }

        // Aktives Helden-Motto als inspirierender Kampfruf + Schlachtschiff-Modus beim Start einblenden
        if (this.isBattleshipMode) {
            this._showFloatingBanner('🚢 SCHLACHTSCHIFF-MODUS: WEITWINKEL-ORBIT & FEINDFLOTTEN AKTIV!', '#ef4444', '#fecaca');
        } else if (this.activeMottoText) {
            this._showFloatingBanner(`💬 „${this.activeMottoText}“ (${this.activeMottoBuffLabel || 'Moral-Buff aktiv'})`, '#f59e0b', '#fef08a');
        }

        this.isRunning = false;
        this._prewarmGpuObjects();
        this.isRunning = true;

        this.lastTime = performance.now();
        this.callbacks.onDistanceUpdate(0);
        if (this.callbacks.onComboUpdate) this.callbacks.onComboUpdate(0, 1);
        if (this.callbacks.onBossUpdate) this.callbacks.onBossUpdate(false, 100);

        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        this.animate();
    }

    applyMottoBuff(mottoCfg) {
        if (!mottoCfg) return;
        this.activeMottoText = mottoCfg.text || '';
        this.activeMottoBuffLabel = mottoCfg.buff_label || '';
        this.mottoMagnetMult = 1.0;
        this.mottoFireRateMult = 1.0;
        this.mottoStartShieldBonus = 0;
        this.mottoSkillDurMult = 1.0;
        this.mottoSparkDistMult = 1.0;

        const stat = mottoCfg.buff_stat || '';
        const val = Number(mottoCfg.buff_value || 1.0);
        if (stat === 'magnet_mult') this.mottoMagnetMult = val;
        else if (stat === 'fire_rate_mult') this.mottoFireRateMult = val;
        else if (stat === 'start_shield_bonus') this.mottoStartShieldBonus = val;
        else if (stat === 'skill_duration_mult') this.mottoSkillDurMult = val;
        else if (stat === 'spark_distance_bonus') this.mottoSparkDistMult = val;

        if (this.activeSkinCfg) {
            this._recomputeCombinedShipStats(this.activeSkinCfg);
        }
    }

    _recomputeCombinedShipStats(skinCfg) {
        const stats = (skinCfg && skinCfg.stats) ? skinCfg.stats : {};
        this.isBattleshipMode = !!(skinCfg && skinCfg.category === 'battleship');
        this.baseCameraY = this.isBattleshipMode ? -19 : -15;
        this.baseCameraZ = this.isBattleshipMode ? 40 : 30;
        if (this.camera) {
            this.camera.position.set(0, this.baseCameraY, this.baseCameraZ);
            this.updateBounds();
        }

        this.maxHullHP = Number(stats.max_hearts || (this.isBattleshipMode ? 5 : 3));
        if (!this.isRunning) {
            this.hullHP = this.maxHullHP;
            if (this.callbacks && this.callbacks.onHullUpdate) {
                this.callbacks.onHullUpdate(this.hullHP, this.maxHullHP);
            }
        }
        this.shipWeaponPowerMult = Number(stats.weapon_power_mult || 1.0);
        this.shipSalvoStreams = Number(stats.salvo_streams || 1);

        this.shipMagnetMult = Number(stats.magnet_mult || 1.0) * (this.mottoMagnetMult || 1.0);
        this.shipHitboxMult = Number(stats.hitbox_mult || 1.0);
        this.shipSkillDurMult = Number(stats.skill_duration_mult || 1.0) * (this.mottoSkillDurMult || 1.0);
        this.shipStartShield = Math.min(100, Number(stats.start_shield || 0) + Number(this.mottoStartShieldBonus || 0));
        this.shipSparkDistBonus = Math.round(Number(stats.spark_distance_bonus || 25) * (this.mottoSparkDistMult || 1.0));
        this.shipBounds = 1.0 * this.shipHitboxMult;
    }

    applyShipSkin(skinCfg) {
        if (!skinCfg) return;
        const key = skinCfg.key || 'default';
        this.activeSkinKey = key;
        this.activeSkinCfg = skinCfg;

        this._recomputeCombinedShipStats(skinCfg);

        if (this.matBullet && skinCfg.laser_color_hex) {
            this.matBullet.color.setHex(skinCfg.laser_color_hex);
        }

        const swapModel = (sceneNode) => {
            if (!this.ship || !sceneNode) return;
            if (this.shipModel) {
                this.ship.remove(this.shipModel);
            }
            this.shipModel = sceneNode;
            this.shipModel.visible = !this.isDying;
            this.ship.add(this.shipModel);
            if (this.renderer && this.scene && this.camera) {
                this.renderer.compile(this.scene, this.camera);
            }
        };

        if (this.loadedShipModels && this.loadedShipModels[key]) {
            swapModel(this.loadedShipModels[key]);
            return;
        }

        const GltfLoaderClass = window.GLTFLoader || (window.THREE && window.THREE.GLTFLoader);
        const shipUrl = (this.assets && this.assets.ships && this.assets.ships[key])
            ? this.assets.ships[key]
            : (this.assets ? this.assets.rocket : null);

        if (GltfLoaderClass && shipUrl) {
            const loader = new GltfLoaderClass();
            loader.load(shipUrl, (gltf) => {
                const scene = gltf.scene;
                if (skinCfg.is_vertex_colored) {
                    scene.position.set(0, 0, 0);
                    scene.rotation.set(0, 0, 0);
                    scene.scale.set(1.12, 1.12, 1.12);
                    if (window.SeelenfunkeShipAnimator) {
                        window.SeelenfunkeShipAnimator.enhanceShipModel(THREE, key, scene);
                    } else {
                        const vMat = new THREE.MeshBasicMaterial({ vertexColors: true, toneMapped: false });
                        scene.traverse((child) => {
                            if (child.isMesh) {
                                child.castShadow = false;
                                child.receiveShadow = false;
                                child.frustumCulled = false;
                                child.material = vMat;
                            }
                        });
                    }
                } else {
                    scene.position.set(this.modelConfig.position.x, this.modelConfig.position.y, this.modelConfig.position.z);
                    scene.rotation.set(
                        this.modelConfig.rotation.x * (Math.PI / 180),
                        this.modelConfig.rotation.y * (Math.PI / 180),
                        this.modelConfig.rotation.z * (Math.PI / 180)
                    );
                    scene.scale.set(this.modelConfig.scale.x, this.modelConfig.scale.y, this.modelConfig.scale.z);
                    scene.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = false;
                            child.receiveShadow = false;
                            child.frustumCulled = false;
                            if (child.material) {
                                const oldMat = child.material;
                                child.material = new THREE.MeshBasicMaterial({ color: oldMat.color, map: oldMat.map, toneMapped: false });
                            }
                        }
                    });
                }
                this.loadedShipModels[key] = scene;
                if (this.activeSkinKey === key) {
                    swapModel(scene);
                }
            }, undefined, (e) => { console.warn("Could not load ship skin GLTF:", key, e); });
        }
    }

    _ensureShieldMesh() {
        if (this.activeSkills.shield.mesh) {
            if (this.ship && this.activeSkills.shield.mesh.parent !== this.ship) {
                this.ship.add(this.activeSkills.shield.mesh);
            }
            return;
        }

        const shieldGroup = new THREE.Group();
        shieldGroup.visible = false;

        this.shieldUniforms = {
            uTime: { value: 0.0 },
            uOpacity: { value: 0.0 },
            uHitIntensity: { value: 0.0 },
            uHitDir: { value: new THREE.Vector3(0.0, 1.0, 0.25) },
            uColorCore: { value: new THREE.Color(0x0ea5e9) },
            uColorRim: { value: new THREE.Color(0x7dd3fc) }
        };

        const shieldVert = `
            uniform float uTime;
            uniform float uHitIntensity;
            varying vec3 vNormal;
            varying vec3 vViewDir;
            varying vec3 vLocalPos;
            void main() {
                vLocalPos = position;
                float breathe = sin(position.y * 1.6 - uTime * 2.2) * cos(position.x * 1.6 + uTime * 1.7) * 0.032;
                float rippleDisp = sin(length(position.xy) * 3.8 - uTime * 12.0) * uHitIntensity * 0.055;
                vec3 displaced = position + normal * (breathe + rippleDisp);
                vec4 mvPosition = modelViewMatrix * vec4(displaced, 1.0);
                vNormal = normalize(normalMatrix * normal);
                vViewDir = normalize(-mvPosition.xyz);
                gl_Position = projectionMatrix * mvPosition;
            }
        `;

        const shieldFrag = `
            uniform float uTime;
            uniform float uOpacity;
            uniform float uHitIntensity;
            uniform vec3 uHitDir;
            uniform vec3 uColorCore;
            uniform vec3 uColorRim;
            varying vec3 vNormal;
            varying vec3 vViewDir;
            varying vec3 vLocalPos;
            void main() {
                vec3 N = normalize(vNormal);
                vec3 V = normalize(vViewDir);
                float viewDot = clamp(abs(dot(N, V)), 0.0, 1.0);

                // Silky Fresnel curve + soft outer edge feathering (zero harsh pixel boundary!)
                float fresnel = pow(1.0 - viewDot, 2.35);
                float outerFeather = smoothstep(0.0, 0.20, viewDot);
                float softRim = fresnel * outerFeather;

                // Smooth organic liquid-plasma currents
                vec3 p = normalize(vLocalPos);
                float w1 = sin(p.y * 4.6 - uTime * 2.3 + sin(p.x * 3.6 + uTime * 1.5) * 1.15);
                float w2 = cos(p.x * 4.2 + uTime * 1.7 - cos(p.z * 4.0 - uTime * 1.3) * 1.1);
                float w3 = sin((p.x + p.y + p.z) * 3.2 - uTime * 1.9);
                float plasma = smoothstep(-0.55, 0.88, (w1 + w2 + w3) * 0.333);

                // Gentle aurora wave gliding from nose to tail
                float aurora = pow(0.5 + 0.5 * sin(p.y * 2.8 - uTime * 2.6 + p.x * 1.1), 2.8);

                // 3D Concentric Impact Shockwave when shield absorbs a hit
                float hitGlow = 0.0;
                if (uHitIntensity > 0.001) {
                    vec3 hitN = normalize(uHitDir);
                    float d = distance(p, hitN);
                    float waveFront = (1.0 - uHitIntensity) * 1.75;
                    float ring = exp(-pow((d - waveFront) * 6.0, 2.0));
                    float spot = exp(-d * 3.0) * uHitIntensity;
                    hitGlow = (ring * 1.3 + spot * 1.1) * uHitIntensity;
                }

                vec3 col = mix(uColorCore, uColorRim, smoothstep(0.12, 0.85, fresnel + plasma * 0.25));
                vec3 shimmerCol = vec3(0.90, 0.98, 1.0);
                col = mix(col, shimmerCol, clamp(aurora * 0.32 + hitGlow * 0.78, 0.0, 1.0));

                float centerVeil = (0.028 + plasma * 0.032) * outerFeather;
                float rimAlpha = softRim * (0.64 + plasma * 0.28 + aurora * 0.20);
                float alpha = (centerVeil + rimAlpha + hitGlow * outerFeather * 0.65) * uOpacity;

                gl_FragColor = vec4(col, clamp(alpha, 0.0, 0.90));
            }
        `;

        const haloFrag = `
            uniform float uOpacity;
            uniform float uHitIntensity;
            uniform vec3 uColorRim;
            varying vec3 vNormal;
            varying vec3 vViewDir;
            void main() {
                vec3 N = normalize(vNormal);
                vec3 V = normalize(vViewDir);
                float viewDot = clamp(abs(dot(N, V)), 0.0, 1.0);
                float halo = pow(1.0 - viewDot, 3.2) * smoothstep(0.0, 0.36, viewDot);
                vec3 col = mix(uColorRim, vec3(0.92, 0.99, 1.0), uHitIntensity * 0.5);
                gl_FragColor = vec4(col, halo * (0.36 + uHitIntensity * 0.22) * uOpacity);
            }
        `;

        const coreGeo = new THREE.SphereGeometry(3.25, 52, 52);
        const coreMat = new THREE.ShaderMaterial({
            uniforms: this.shieldUniforms,
            vertexShader: shieldVert,
            fragmentShader: shieldFrag,
            transparent: true,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        });
        const coreMesh = new THREE.Mesh(coreGeo, coreMat);
        coreMesh.frustumCulled = false;
        shieldGroup.add(coreMesh);

        // Soft outer atmospheric feathering halo
        const haloGeo = new THREE.SphereGeometry(3.58, 36, 36);
        const haloMat = new THREE.ShaderMaterial({
            uniforms: this.shieldUniforms,
            vertexShader: shieldVert,
            fragmentShader: haloFrag,
            transparent: true,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        });
        const haloMesh = new THREE.Mesh(haloGeo, haloMat);
        haloMesh.frustumCulled = false;
        shieldGroup.add(haloMesh);

        this.activeSkills.shield.mesh = shieldGroup;
        this.shieldVisualOpacity = 0.0;
        this.shieldVisualScale = 0.7;
        this.shieldAnimTime = 0.0;

        if (this.ship) {
            this.ship.add(shieldGroup);
        }
    }

    triggerShieldImpact(hitX = 0, hitY = 1) {
        if (!this.shieldUniforms || !this.ship) return;
        const dx = hitX - this.ship.position.x;
        const dy = hitY - this.ship.position.y;
        this.shieldUniforms.uHitDir.value.set(dx, dy, 0.6).normalize();
        this.shieldUniforms.uHitIntensity.value = 1.0;
    }

    _activateShieldWithHP(hp = 100, duration = 20.0) {
        this._ensureShieldMesh();
        const wasAlreadyActive = this.activeSkills.shield.active && this.activeSkills.shield.mesh && this.activeSkills.shield.mesh.visible;

        this.activeSkills.shield.active = true;
        this.activeSkills.shield.timer = duration;
        this.shieldHP = hp;
        this._lastReportedShield = hp;
        this.callbacks.onShieldUpdate(this.shieldHP);

        if (this.activeSkills.shield.mesh) {
            this.activeSkills.shield.mesh.visible = true;
            if (!wasAlreadyActive) {
                this.shieldVisualOpacity = 0.1;
                this.shieldVisualScale = 0.65;
            }
            if (this.shieldUniforms) {
                this.shieldUniforms.uHitDir.value.set(0, 1, 0.3).normalize();
                this.shieldUniforms.uHitIntensity.value = 0.65;
            }
        }
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
        if (!this.isRunning || this.isGameOver || this.isDying) return false;

        if (index === 1) { // Multishoot
            this.activeSkills.multishoot.active = true;
            this.activeSkills.multishoot.timer = 4.0 * (this.shipSkillDurMult || 1.0); // 4s + Skin-Bonus
            return true;
        }
        else if (index === 2) { // Teleport
            if (this.activeSkills.teleport && this.activeSkills.teleport.waitingForClick) return false;
            this.timeScale = 0.1; // Slow motion!
            this.activeSkills.teleport.waitingForClick = true;
            this.activeSkills.teleport.timer = 7.0; // Automatisches Ablaufen nach 7s Echtzeit
            this._lastTeleportCountdown = 7;

            // Initialize Golden Stardust Trail & Target Beacon immediately at current pointer position
            let tx = this.pointerPos.x;
            let ty = this.pointerPos.y;
            if (this.bounds) {
                tx = Math.max(this.bounds.xMin, Math.min(this.bounds.xMax, tx));
                ty = Math.max(this.bounds.yMin, Math.min(this.bounds.yMax, ty));
            }
            this._teleportAimPos.set(tx, ty, 0);
            this._lastTeleportAimPos.copy(this._teleportAimPos);
            this._hasTeleportAim = true;
            if (this.teleportBeacon) {
                this.teleportBeacon.position.copy(this._teleportAimPos);
                this.teleportBeacon.visible = true;
            }
            for (let i = 0; i < 16; i++) {
                const ang = (i / 16) * Math.PI * 2;
                this.spawnTeleportDustMote(tx, ty, Math.cos(ang) * 4.5, Math.sin(ang) * 4.5, 0.65);
            }

            // Visual indicator
            const existingCh = document.getElementById('ff-crosshair');
            if (existingCh) existingCh.remove();
            const crosshair = document.createElement('div');
            crosshair.id = 'ff-crosshair';
            crosshair.style = 'position:absolute; inset:0; background:radial-gradient(circle, transparent 45%, rgba(245,158,11,0.18) 100%); z-index: 50; pointer-events:none; font-family:sans-serif; text-align:center; padding-top:16vh; font-size:1.35rem; font-weight:800; letter-spacing:0.06em; color:#fde047; text-shadow:0 0 14px rgba(245,158,11,0.85);';
            crosshair.innerText = '✨ Goldstaub-Ziel wählen & klicken! (7s)';
            this.layer.appendChild(crosshair);

            return true;
        }
        else if (index === 3) { // Shield
            this._activateShieldWithHP(100, 20.0 * (this.shipSkillDurMult || 1.0));
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

    cancelTeleport() {
        if (!this.activeSkills.teleport.waitingForClick) return;
        this.timeScale = 1.0;
        this.activeSkills.teleport.waitingForClick = false;
        this.activeSkills.teleport.timer = 0;
        this._hasTeleportAim = false;
        if (this.teleportBeacon) this.teleportBeacon.visible = false;
        const ch = document.getElementById('ff-crosshair');
        if (ch) ch.remove();
    }

    executeTeleport() {
        if (!this.activeSkills.teleport.waitingForClick) return;
        this.timeScale = 1.0;
        this.activeSkills.teleport.waitingForClick = false;
        this.activeSkills.teleport.timer = 0;
        this._hasTeleportAim = false;
        if (this.teleportBeacon) this.teleportBeacon.visible = false;

        // Clamp teleport target to safe playfield bounds
        let tx = this.pointerPos.x;
        let ty = this.pointerPos.y;
        if (this.bounds) {
            tx = Math.max(this.bounds.xMin, Math.min(this.bounds.xMax, tx));
            ty = Math.max(this.bounds.yMin, Math.min(this.bounds.yMax, ty));
        }

        // Golden dust burst at departure point
        for (let i = 0; i < 14; i++) {
            const ang = (i / 14) * Math.PI * 2;
            this.spawnTeleportDustMote(this.ship.position.x, this.ship.position.y, Math.cos(ang) * 7.5, Math.sin(ang) * 7.5, 0.55);
        }

        // Move ship to golden stardust target
        this.ship.position.set(tx, ty, 0);
        this.shipTargetPos.set(tx, ty, 0);
        
        if (this.tpAudio) {
            let vol = window.alpineComponentContext ? (window.alpineComponentContext.bgmVolumeUi / 100) : 0.5;
            this.tpAudio.volume = Math.min(1.0, vol * 1.5);
            this.tpAudio.currentTime = 0;
            this.tpAudio.play().catch(e => {});
        }

        // Golden & violet warp particle effect at arrival point
        for (let i = 0; i < 22; i++) {
            const ang = (i / 22) * Math.PI * 2;
            this.spawnTeleportDustMote(tx, ty, Math.cos(ang) * 9.5, Math.sin(ang) * 9.5, 0.65);
        }
        for (let i = 0; i < 20; i++) { this.spawnParticle(this.ship.position, 0xfde047); }
        for (let i = 0; i < 15; i++) { this.spawnParticle(this.ship.position, 0xc084fc); }

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

        // --- SHIP DESTRUCTION ANIMATION SEQUENCE (BEFORE GAME OVER MENU) ---
        if (this.isDying) {
            this.deathTimer -= dtRaw;
            this.currentSpeed = Math.max(2.0, this.currentSpeed * (1 - dtRaw * 1.8));

            const progress = Math.max(0, Math.min(1, 1 - (this.deathTimer / 1.35)));

            // Animate expanding shockwave ring & core flash
            if (this.deathShockwaveRing && this.deathShockwaveRing.visible) {
                const ringScale = 1.0 + progress * 14.5;
                this.deathShockwaveRing.scale.set(ringScale, ringScale, 1);
                this.deathShockwaveRing.rotation.z += dtRaw * 3.5;
                this.deathShockwaveMat.opacity = Math.max(0, 0.9 * (1 - progress));
            }
            if (this.deathCoreFlash && this.deathCoreFlash.visible) {
                const flashScale = progress < 0.25 ? (1.0 + progress * 10.0) : Math.max(0.1, 3.5 - (progress - 0.25) * 4.5);
                this.deathCoreFlash.scale.setScalar(flashScale);
                this.deathCoreFlashMat.opacity = Math.max(0, 1.0 - progress * 1.35);
            }

            // Secondary explosion bursts
            if (this.deathStage === 0 && this.deathTimer <= 1.05) {
                this.deathStage = 1;
                this.cameraShake = 0.45;
                this._tempVec.copy(this.deathOrigin);
                this._tempVec.x += (Math.random() - 0.5) * 2.2;
                this._tempVec.y += (Math.random() - 0.5) * 2.2;
                this.spawnExplosionBurst(this._tempVec, 0xfde047, 18, 22);
                this.spawnExplosionBurst(this._tempVec, 0xc084fc, 12, 18);
            } else if (this.deathStage === 1 && this.deathTimer <= 0.68) {
                this.deathStage = 2;
                this.cameraShake = 0.30;
                this._tempVec.copy(this.deathOrigin);
                this._tempVec.x += (Math.random() - 0.5) * 2.8;
                this._tempVec.y += (Math.random() - 0.5) * 2.8;
                this.spawnExplosionBurst(this._tempVec, 0xf97316, 16, 20);
                this.spawnExplosionBurst(this._tempVec, 0xffffff, 10, 25);
            }

            // Camera shake decay
            if (this.cameraShake > 0.01 && this.camera) {
                this.camera.position.x = (Math.random() - 0.5) * this.cameraShake * 2.2;
                this.camera.position.y = (this.baseCameraY || -15) + (Math.random() - 0.5) * this.cameraShake * 2.2;
                this.cameraShake = Math.max(0, this.cameraShake - dtRaw * 1.2);
            } else if (this.camera) {
                this.camera.position.set(0, this.baseCameraY || -15, this.baseCameraZ || 30);
            }

            this.updateEntities(dtRaw);

            if (this.starLayers && this.starLayers.length) {
                for (let li = 0; li < this.starLayers.length; li++) {
                    const layer = this.starLayers[li];
                    const spanY = layer.userData.spanY || 140;
                    layer.position.y -= this.currentSpeed * (layer.userData.speedMult || 0.5) * dtRaw;
                    if (layer.position.y <= -spanY) layer.position.y += spanY;
                }
            }

            this.renderer.render(this.scene, this.camera);

            if (this.deathTimer <= 0) {
                if (this.deathShockwaveRing) this.deathShockwaveRing.visible = false;
                if (this.deathCoreFlash) this.deathCoreFlash.visible = false;
                if (this.camera) this.camera.position.set(0, this.baseCameraY || -15, this.baseCameraZ || 30);
                this.triggerGameOver();
            }
            return;
        }

        // Progression
        if (this.timeScale === 1.0) {
            this.currentSpeed = Math.min(28, this.baseSpeed + (this.distance / 180)); // Spürbare Beschleunigung schon ab den ersten Sekunden
            const distanceRate = Math.min(58, 36 + (this.currentSpeed - this.baseSpeed) * 1.2); // Konform mit Server-Anti-Cheat (max 65 m/s)
            this.distance += distanceRate * dtRaw;
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

        // 60 FPS 3D-Sub-Animationen für alle Spezial-Raumschiffe (außer Standard-Schiff)
        if (this.activeSkinKey && this.activeSkinKey !== 'default' && this.shipModel && window.SeelenfunkeShipAnimator) {
            window.SeelenfunkeShipAnimator.updateShipAnimation(this.shipModel, now * 0.001, dtRaw, {
                bank: this.ship.rotation.y,
                boost: !!(this.activeSkills.multishoot.active || this.activeSkills.ultimate.active)
            });
        }

        // Firing Logic (Auto fire with 3-tier Weapon Combo, Battleship Broadside Salvos & Helden-Motto Moral-Buff!)
        const rawInterval = this.weaponTier === 3 ? 105 : (this.weaponTier === 2 ? 125 : 150);
        const baseInterval = rawInterval / (this.mottoFireRateMult || 1.0);
        let fireRate = this.activeSkills.multishoot.active ? (48 / this.timeScale) : (baseInterval / this.timeScale);
        if (now - this.lastFireTime > fireRate) {
            let bulletsToFire = Math.floor((now - this.lastFireTime) / fireRate);
            bulletsToFire = Math.min(bulletsToFire, 2); // Prevent massive bursts on extreme lag

            const effectiveTier = this.activeSkills.multishoot.active ? 3 : this.weaponTier;
            const salvoStreams = this.isBattleshipMode ? Math.max(3, this.shipSalvoStreams || 3) : 1;

            for (let i = 0; i < bulletsToFire; i++) {
                const yOffset = i * 2.0;
                if (this.isBattleshipMode) {
                    // SCHLACHTSCHIFF BREITSEITEN-SALVE (3 bis 5 schwere Geschütz-Läufe!)
                    this._tempVec.copy(this.ship.position);
                    this._tempVec.y += yOffset + 0.45;
                    this.fireBullet(this._tempVec, this._bulletDefaultDir, 0xfde047);

                    this._tempVec.copy(this.ship.position);
                    this._tempVec.x -= 0.72;
                    this._tempVec.y += yOffset + 0.15;
                    this.fireBullet(this._tempVec, effectiveTier >= 3 ? this._bulletLeftDir : this._bulletDefaultDir, 0x38bdf8);

                    this._tempVec.copy(this.ship.position);
                    this._tempVec.x += 0.72;
                    this._tempVec.y += yOffset + 0.15;
                    this.fireBullet(this._tempVec, effectiveTier >= 3 ? this._bulletRightDir : this._bulletDefaultDir, 0x38bdf8);

                    if (salvoStreams >= 4 || effectiveTier >= 2) {
                        this._tempVec.copy(this.ship.position);
                        this._tempVec.x -= 1.38;
                        this._tempVec.y += yOffset - 0.1;
                        this.fireBullet(this._tempVec, this._bulletLeftDir, 0xfde047);
                    }
                    if (salvoStreams >= 5 || effectiveTier >= 3) {
                        this._tempVec.copy(this.ship.position);
                        this._tempVec.x += 1.38;
                        this._tempVec.y += yOffset - 0.1;
                        this.fireBullet(this._tempVec, this._bulletRightDir, 0xfde047);
                    }
                } else if (effectiveTier === 1) {
                    this._tempVec.copy(this.ship.position);
                    this._tempVec.y += yOffset;
                    this.fireBullet(this._tempVec, this._bulletDefaultDir, 0x38bdf8);
                } else if (effectiveTier === 2) {
                    // Stufe II: Zwillings-Laser
                    this._tempVec.copy(this.ship.position);
                    this._tempVec.x -= 0.55;
                    this._tempVec.y += yOffset;
                    this.fireBullet(this._tempVec, this._bulletDefaultDir, 0x38bdf8);

                    this._tempVec.copy(this.ship.position);
                    this._tempVec.x += 0.55;
                    this._tempVec.y += yOffset;
                    this.fireBullet(this._tempVec, this._bulletDefaultDir, 0x38bdf8);
                } else {
                    // Stufe III / Multishoot: 3-fach Plasma-Fächer (Gold-Kern + 2 Flanken-Laser)
                    this._tempVec.copy(this.ship.position);
                    this._tempVec.y += yOffset + 0.3;
                    this.fireBullet(this._tempVec, this._bulletDefaultDir, 0xfde047);

                    this._tempVec.copy(this.ship.position);
                    this._tempVec.x -= 0.65;
                    this._tempVec.y += yOffset;
                    this.fireBullet(this._tempVec, this._bulletLeftDir, 0x38bdf8);

                    this._tempVec.copy(this.ship.position);
                    this._tempVec.x += 0.65;
                    this._tempVec.y += yOffset;
                    this.fireBullet(this._tempVec, this._bulletRightDir, 0x38bdf8);
                }
            }
            this.lastFireTime = now;
        }

        // --- WEAPON COMBO DECAY ---
        if (this.comboCount > 0) {
            this.comboDecayTimer -= dtRaw;
            if (this.comboDecayTimer <= 0) {
                this.degradeCombo();
            }
        }

        // --- HULL INVULNERABILITY & 3D BURNING HULL FLAMES ---
        if (this.hullInvulnTimer > 0) {
            this.hullInvulnTimer = Math.max(0, this.hullInvulnTimer - dtRaw);
            if (this.shipModel && !this.isDying) {
                this.shipModel.visible = (Math.floor(this.hullInvulnTimer * 18) % 2 === 0);
            }
            if (this.hullInvulnTimer <= 0 && this.shipModel && !this.isDying) {
                this.shipModel.visible = true;
            }
        }

        if (this.hullFireGroup && !this.isDying) {
            if (this.hullHP < this.maxHullHP) {
                this.hullFireGroup.visible = true;
                const t = now * 0.022;
                if (this.hullFlameLeft) {
                    this.hullFlameLeft.visible = true;
                    this.hullFlameLeft.scale.set(0.85 + Math.sin(t) * 0.25, 0.9 + Math.cos(t * 1.3) * 0.35, 0.85);
                    this.hullFlameLeft.rotation.z = Math.sin(t * 0.8) * 0.22;
                }
                if (this.hullFlameRight) {
                    this.hullFlameRight.visible = (this.hullHP <= 1);
                    this.hullFlameRight.scale.set(0.95 + Math.cos(t * 1.1) * 0.3, 1.05 + Math.sin(t * 1.5) * 0.4, 0.95);
                    this.hullFlameRight.rotation.z = -Math.cos(t * 0.9) * 0.25;
                }
                if (this.hullFlameCenter) {
                    this.hullFlameCenter.visible = (this.hullHP <= 1);
                    this.hullFlameCenter.scale.set(1.1 + Math.sin(t * 1.7) * 0.35, 1.2 + Math.cos(t * 1.4) * 0.45, 1.1);
                }
            } else {
                this.hullFireGroup.visible = false;
            }
        }

        // Camera Shake Decay during active flight (e.g. after Hull Damage)
        if (this.cameraShake > 0.01 && this.camera) {
            this.camera.position.x = (Math.random() - 0.5) * this.cameraShake * 2.0;
            this.camera.position.y = (this.baseCameraY || -15) + (Math.random() - 0.5) * this.cameraShake * 2.0;
            this.cameraShake = Math.max(0, this.cameraShake - dtRaw * 1.4);
            if (this.cameraShake <= 0.01) {
                this.camera.position.set(0, this.baseCameraY || -15, this.baseCameraZ || 30);
            }
        }

        // --- SKILLS UPDATE ---
        this.updateSkills(dtRaw, now);

        // --- GOLDEN STARDUST TELEPORT TRAIL (Real-time dtRaw, unaffected by slow-mo) ---
        this.updateTeleportStardust(dtRaw);

        // --- SPAWN OBJECTS ---
        this.spawnLogic(dtRaw);

        // --- UPDATE ENTITIES ---
        this.updateEntities(dt);

        // --- COLLISIONS ---
        this.checkCollisions();

        // --- SEAMLESS 3D PLANETARY FLYOVER BIOME TRANSITION ---
        this.updateBiomeTransition(dtRaw, dt);

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
        // Teleport (automatisches Abschalten nach 7 Sekunden Echtzeit, wenn nicht geklickt wird)
        if (this.activeSkills.teleport && this.activeSkills.teleport.waitingForClick) {
            this.activeSkills.teleport.timer -= dt;
            if (this.activeSkills.teleport.timer <= 0) {
                this.cancelTeleport();
            } else {
                const remSec = Math.max(1, Math.ceil(this.activeSkills.teleport.timer));
                if (remSec !== this._lastTeleportCountdown) {
                    this._lastTeleportCountdown = remSec;
                    const ch = document.getElementById('ff-crosshair');
                    if (ch) {
                        ch.innerText = `✨ Goldstaub-Ziel wählen & klicken! (${remSec}s)`;
                    }
                }
            }
        }

        // Multishoot
        if (this.activeSkills.multishoot.active) {
            this.activeSkills.multishoot.timer -= dt;

            if (this.activeSkills.multishoot.timer <= 0) {
                this.activeSkills.multishoot.active = false;
            }
        }

        // Shield (Silky Fresnel Plasma Shader Animation + Smooth Fade-In / Fade-Out)
        this.shieldAnimTime = (this.shieldAnimTime || 0) + dt;
        if (this.shieldUniforms) {
            this.shieldUniforms.uTime.value = this.shieldAnimTime;
            if (this.shieldUniforms.uHitIntensity.value > 0.001) {
                this.shieldUniforms.uHitIntensity.value = Math.max(0.0, this.shieldUniforms.uHitIntensity.value - dt * 2.2);
            }
        }

        if (this.activeSkills.shield.active) {
            this.activeSkills.shield.timer -= dt;
            if (this.activeSkills.shield.timer <= 0 || this.shieldHP <= 0) {
                this.activeSkills.shield.active = false;
                for(let i=0; i<15; i++) this.spawnParticle(this.ship.position, 0x60a5fa);
                this.shieldHP = 0;
                this._lastReportedShield = 0;
                this.callbacks.onShieldUpdate(0);
            } else {
                const displayShield = Math.max(1, Math.min(this.shieldHP, Math.ceil((this.activeSkills.shield.timer / 20.0) * this.shieldHP)));
                if (this._lastReportedShield !== displayShield) {
                    this._lastReportedShield = displayShield;
                    this.callbacks.onShieldUpdate(displayShield);
                }

                // Smoothly ease shield opacity & scale toward 1.0
                const blendRate = 1.0 - Math.exp(-dt * 8.5);
                this.shieldVisualOpacity = (this.shieldVisualOpacity || 0) + (1.0 - (this.shieldVisualOpacity || 0)) * blendRate;
                this.shieldVisualScale = (this.shieldVisualScale || 0.7) + (1.0 - (this.shieldVisualScale || 0.7)) * blendRate;

                if (this.activeSkills.shield.mesh) {
                    this.activeSkills.shield.mesh.visible = true;
                    const gentlePulse = 1.0 + Math.sin(this.shieldAnimTime * 2.8) * 0.015;
                    this.activeSkills.shield.mesh.scale.setScalar(this.shieldVisualScale * gentlePulse);
                }
                if (this.shieldUniforms) {
                    // Subtle warning pulse when timer < 3s
                    const lowTimeWave = this.activeSkills.shield.timer < 3.0
                        ? (0.78 + 0.22 * Math.sin(this.shieldAnimTime * 9.0))
                        : 1.0;
                    this.shieldUniforms.uOpacity.value = this.shieldVisualOpacity * lowTimeWave;
                }
            }
        } else if (this.activeSkills.shield.mesh && this.activeSkills.shield.mesh.visible) {
            // Smooth dissolve & expansion when shield expires or breaks (never pop out abruptly!)
            this.shieldVisualOpacity = Math.max(0.0, (this.shieldVisualOpacity || 0) - dt * 4.2);
            this.shieldVisualScale = (this.shieldVisualScale || 1.0) + dt * 0.45;
            this.activeSkills.shield.mesh.scale.setScalar(this.shieldVisualScale);
            if (this.shieldUniforms) {
                this.shieldUniforms.uOpacity.value = this.shieldVisualOpacity;
            }
            if (this.shieldVisualOpacity <= 0.01) {
                this.activeSkills.shield.mesh.visible = false;
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
                        // Find closest enemy (or Sector Boss / Twin Mini-Bosses)
                        let closest = null, minDist = 10000;
                        if (this.bossActive && this.bossPhase === 1 && this.bossMesh && this.bossMesh.visible) {
                            minDist = this.bossMesh.position.distanceToSquared(d.position);
                            closest = this.bossMesh;
                        } else if (this.bossActive && this.bossPhase === 2 && this.miniBossMeshes) {
                            for (let mi = 0; mi < 2; mi++) {
                                if (this.miniBossActive[mi] && this.miniBossMeshes[mi] && this.miniBossMeshes[mi].visible) {
                                    const d2 = this.miniBossMeshes[mi].position.distanceToSquared(d.position);
                                    if (d2 < minDist) {
                                        minDist = d2;
                                        closest = this.miniBossMeshes[mi];
                                    }
                                }
                            }
                        }
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

    _spawnSingleEnemy(config = {}) {
        if (this.pools.enemies.length === 0) return null;
        const m = this.pools.enemies.pop();
        const difficultyLevel = Math.floor(this.distance / 400); // Steigt alle 400m (~8s) spürbar an

        // Reset all visibilities first
        if (m.userData.fallbackNormal) m.userData.fallbackNormal.visible = false;
        if (m.userData.fallbackElite) m.userData.fallbackElite.visible = false;
        if (m.userData.fallbackWall) m.userData.fallbackWall.visible = false;
        if (m.userData.fallbackRaider) m.userData.fallbackRaider.visible = false;
        if (m.userData.meshGlbMeteor) m.userData.meshGlbMeteor.visible = false;
        if (m.userData.meshGlbSharp) m.userData.meshGlbSharp.visible = false;

        const spawnMinX = (this.bounds ? this.bounds.xMin : -14) + 1.5;
        const spawnMaxX = (this.bounds ? this.bounds.xMax : 14) - 1.5;
        const spawnRange = Math.max(4, spawnMaxX - spawnMinX);

        const baseX = (typeof config.x === 'number')
            ? (config.isNovaMeteor ? config.x : Math.max(spawnMinX, Math.min(spawnMaxX, config.x)))
            : (spawnMinX + Math.random() * spawnRange);
        const startY = (typeof config.y === 'number') ? config.y : (this.isBattleshipMode ? 41 : 35);

        m.userData.baseX = baseX;
        m.userData.spawnTime = performance.now();
        m.userData.vx = config.vx || 0;
        m.userData.vy = (typeof config.vy === 'number') ? config.vy : null;
        m.userData.isNovaMeteor = !!config.isNovaMeteor;
        m.userData.isBounced = false;
        m.userData.shieldBounceIgnoreUntil = 0;
        m.userData.isSeeker = !!config.isSeeker;
        m.userData.canSplit = !!config.canSplit;
        m.userData.isFragment = !!config.isFragment;
        m.userData.isWall = !!config.isWall;
        m.userData.isRaider = !!config.isRaider;
        m.userData.raiderFireTimer = 0.75 + Math.random() * 0.65;
        m.userData.driftSpeed = config.driftSpeed || 0;
        m.userData.driftRange = config.driftRange || 0;
        m.userData.fallSpeedMult = config.fallSpeedMult || (1.08 + Math.min(1.1, difficultyLevel * 0.14));

        if (m.userData.isWall) {
            // Plasma-Sperrbarriere (blockiert eine Spur, zwingt zum Ausweichen / Teleport / Schild)
            m.userData.type = 2;
            m.userData.hp = 16 + (difficultyLevel * 4);
            m.userData.radius = 5.5;
            m.scale.set(1, 1, 1);
            if (m.userData.fallbackWall) {
                m.userData.fallbackWall.visible = true;
                m.userData.activeMesh = m.userData.fallbackWall;
            }
        } else if (m.userData.isRaider) {
            // Feindliches Raumschiff (Raider-Jäger): Weicht seitlich aus & feuert Plasma-Salven!
            m.userData.type = 3;
            const scale = config.scale || 1.38;
            m.scale.set(scale, scale, scale);
            m.userData.radius = 1.65;
            m.userData.hp = config.hp || (5 + difficultyLevel * 2.5);
            if (m.userData.fallbackRaider) {
                m.userData.fallbackRaider.rotation.set(0, 0, 0);
                m.userData.fallbackRaider.visible = true;
                m.userData.activeMesh = m.userData.fallbackRaider;
            }
        } else if (config.type === 1) {
            // Kristall-Brochen / Splitter-Meteorit / Sucher
            m.userData.type = 1;
            const scale = config.scale || (config.isGiant ? 4.4 : 1.55);
            m.scale.set(scale, scale, scale);
            m.userData.radius = config.radius || (scale * 1.05);
            m.userData.hp = config.hp || (config.isGiant ? (14 + difficultyLevel * 4) : (3 + difficultyLevel * 2));

            if (m.userData.meshGlbSharp) {
                m.userData.meshGlbSharp.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);
                m.userData.meshGlbSharp.visible = true;
                m.userData.activeMesh = m.userData.meshGlbSharp;
            } else {
                m.userData.fallbackElite.visible = true;
                m.userData.activeMesh = m.userData.fallbackElite;
            }
        } else {
            // Klassischer Gesteins-Meteorit (oder schnelles Splitter-Fragment)
            m.userData.type = 0;
            const scale = config.scale || (config.isGiant ? 4.5 : 1.5);
            m.scale.set(scale, scale, scale);
            m.userData.radius = config.radius || (scale * 0.98);
            m.userData.hp = config.hp || (config.isGiant ? (12 + difficultyLevel * 4) : (2 + Math.floor(difficultyLevel * 1.5)));

            if (m.userData.meshGlbMeteor) {
                m.userData.meshGlbMeteor.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);
                m.userData.meshGlbMeteor.visible = true;
                m.userData.activeMesh = m.userData.meshGlbMeteor;
            } else {
                m.userData.fallbackNormal.visible = true;
                m.userData.activeMesh = m.userData.fallbackNormal;
            }
        }

        m.position.set(baseX, startY, 0);
        m.visible = true;
        this.enemies.push(m);
        return m;
    }

    addCombo(points = 1) {
        const prevTier = this.weaponTier;
        this.comboCount += points;
        this.comboDecayTimer = 5.2; // 5.2s Zeitfenster bis zum Abkühlen

        if (this.comboCount >= 14) {
            this.weaponTier = 3;
        } else if (this.comboCount >= 6) {
            this.weaponTier = 2;
        } else {
            this.weaponTier = 1;
        }

        if (this.weaponTier > prevTier && this.audio && this.audio.playWeaponUpgrade) {
            this.audio.playWeaponUpgrade();
        }
        if (this.callbacks && this.callbacks.onComboUpdate) {
            this.callbacks.onComboUpdate(this.comboCount, this.weaponTier);
        }
    }

    degradeCombo() {
        if (this.comboCount <= 0) return;
        if (this.weaponTier === 3) {
            this.comboCount = 6;
            this.weaponTier = 2;
            this.comboDecayTimer = 4.5;
        } else {
            this.comboCount = 0;
            this.weaponTier = 1;
            this.comboDecayTimer = 0;
        }
        if (this.callbacks && this.callbacks.onComboUpdate) {
            this.callbacks.onComboUpdate(this.comboCount, this.weaponTier);
        }
    }

    _showFloatingBanner(text, borderHex = '#f43f5e', textHex = '#ffe4e6') {
        if (!this.layer) return;
        const el = document.createElement('div');
        el.style.cssText = `position:absolute;top:24%;left:50%;transform:translate(-50%,-50%);padding:6px 14px;border-radius:9999px;background:rgba(15,23,42,0.92);border:1.5px solid ${borderHex};color:${textHex};font-weight:900;font-size:11px;letter-spacing:0.14em;text-transform:uppercase;pointer-events:none;z-index:60;box-shadow:0 0 22px ${borderHex}66;transition:opacity 0.35s ease,transform 0.35s ease;white-space:nowrap;`;
        el.textContent = text;
        this.layer.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translate(-50%,-85%) scale(1.06)';
        }, 1150);
        setTimeout(() => {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 1550);
    }

    spawnSectorBoss() {
        if (this.bossActive || !this.bossMesh) return;
        this.bossActive = true;
        this.bossPhase = 1;
        this.bossLevel += 1;
        // Aufgabe 1: Deutlich stärkerer Haupt-Boss mit massiv erhöhtem Leben + Phase-2-Spaltung!
        this.bossMaxHp = 95 + (this.bossLevel * 45);
        this.bossHp = this.bossMaxHp;
        this.bossFireTimer = 1.65;
        this.bossShotPattern = 0;
        this.bossSpawnTime = performance.now();
        this.bossCombatTime = 0; // Startet bei 0.0 -> sin(0) = 0 -> KEIN seitlicher Sprung!
        this.bossIntroDuration = 0.95;
        this.bossIntroTimer = this.bossIntroDuration;
        this.miniBossActive = [false, false];
        if (this.miniBossMeshes && this.miniBossMeshes.length === 2) {
            this.miniBossMeshes[0].visible = false;
            this.miniBossMeshes[1].visible = false;
        }

        // Boss materialisiert direkt auf Kampfposition (0, 18.5, 0) aus einer gigantischen 3D Licht-Nova
        this.bossMesh.position.set(0, 18.5, 0);
        this.bossMesh.scale.setScalar(0.06);
        this.bossMesh.visible = true;

        // 1. 3D Licht-Nova (Supernova-Dimensionsriss) zünden
        if (this.bossNovaGroup) {
            this.bossNovaGroup.position.set(0, 18.5, 0);
            this.bossNovaGroup.scale.setScalar(1.0);
            const nd = this.bossNovaGroup.userData;
            if (nd.core) { nd.core.scale.setScalar(1.0); nd.coreMat.opacity = 1.0; }
            if (nd.halo) { nd.halo.scale.set(14, 14, 1); nd.haloMat.opacity = 1.0; }
            if (nd.ring1) { nd.ring1.scale.setScalar(0.8); nd.ring1Mat.opacity = 0.98; }
            if (nd.ring2) { nd.ring2.scale.setScalar(0.6); nd.ring2Mat.opacity = 0.92; }
            this.bossNovaGroup.visible = true;
        }

        // 2. Druckwelle, Kamera-Beben & Licht-Partikel-Eruption
        this.cameraShake = 0.45;
        this._tempVec.set(0, 18.5, 0);
        this.spawnExplosionBurst(this._tempVec, 0xffffff, 18, 28);
        this.spawnExplosionBurst(this._tempVec, 0xfde047, 18, 24);
        this.spawnExplosionBurst(this._tempVec, 0xc084fc, 14, 22);

        // 3. 360°-Meteoriten-Schauer: Meteoriten fliegen durch die Nova-Explosion sternförmig in JEDE Richtung!
        const radialCount = this.isMobile ? 8 : 12;
        while (this.pools.enemies.length < radialCount && this.enemies.length > 0) {
            const recycled = this.enemies.shift();
            recycled.visible = false;
            this.pools.enemies.push(recycled);
        }
        for (let k = 0; k < radialCount; k++) {
            // (k + 0.5) verteilt die Winkel symmetrisch in alle 360°-Richtungen, lässt aber exakt vertikal (270°) eine faire Reaktionslücke
            const angle = ((k + 0.5) / radialCount) * Math.PI * 2;
            const speed = 18.5 + (k % 2) * 3.5;
            const vx = Math.cos(angle) * speed;
            const vy = Math.sin(angle) * speed;

            this._spawnSingleEnemy({
                type: k % 2,
                x: Math.cos(angle) * 1.6,
                y: 18.5 + Math.sin(angle) * 1.6,
                vx: vx,
                vy: vy,
                isNovaMeteor: true,
                isFragment: true,
                scale: 1.18 + (k % 2) * 0.22,
                radius: 1.15,
                hp: 1 + Math.floor(this.bossLevel * 0.5)
            });
        }

        if (this.audio && this.audio.playBossWarning) {
            this.audio.playBossWarning();
        }
        const profile = (this.bossProfiles && this.bossProfiles.length)
            ? this.bossProfiles[(this.bossLevel - 1) % this.bossProfiles.length]
            : { name: 'Lord Lieblos', subtitle: 'Der Massenware-Monolith' };
        if (this.callbacks && this.callbacks.onBossUpdate) {
            this.callbacks.onBossUpdate(true, 100, profile.name, profile.subtitle);
        }
    }

    splitSectorBossIntoTwins() {
        if (!this.bossActive || this.bossPhase !== 1) return;
        this.bossPhase = 2;
        this.miniBossSplitOrigin.copy(this.bossMesh.position);
        this.bossMesh.visible = false;

        this.miniBossMaxHp = 45 + (this.bossLevel * 22);
        this.miniBossHp = [this.miniBossMaxHp, this.miniBossMaxHp];
        this.miniBossActive = [true, true];
        this.miniBossCombatTime = 0;
        this.bossFireTimer = 1.05;
        this.bossShotPattern = 0;

        // Licht-Nova & Spaltungs-Explosion am Punkt der Teilung
        this.bossIntroDuration = 0.75;
        this.bossIntroTimer = this.bossIntroDuration;
        if (this.bossNovaGroup) {
            this.bossNovaGroup.position.copy(this.miniBossSplitOrigin);
            this.bossNovaGroup.scale.setScalar(0.85);
            const nd = this.bossNovaGroup.userData;
            if (nd.core) { nd.core.scale.setScalar(1.0); nd.coreMat.opacity = 1.0; }
            if (nd.halo) { nd.halo.scale.set(12, 12, 1); nd.haloMat.opacity = 1.0; }
            if (nd.ring1) { nd.ring1.scale.setScalar(0.7); nd.ring1Mat.opacity = 0.95; }
            if (nd.ring2) { nd.ring2.scale.setScalar(0.5); nd.ring2Mat.opacity = 0.90; }
            this.bossNovaGroup.visible = true;
        }

        for (let mi = 0; mi < 2; mi++) {
            const mb = this.miniBossMeshes[mi];
            if (mb) {
                mb.position.copy(this.miniBossSplitOrigin);
                mb.scale.setScalar(1.0);
                mb.visible = true;
            }
        }

        this.cameraShake = 0.48;
        this.spawnExplosionBurst(this.miniBossSplitOrigin, 0xfde047, 20, 25);
        this.spawnExplosionBurst(this.miniBossSplitOrigin, 0xf43f5e, 18, 22);
        this.spawnExplosionBurst(this.miniBossSplitOrigin, 0xc084fc, 16, 20);

        // 8 sternförmige Splitter-Meteoriten bei der Boss-Teilung!
        const splitMeteors = this.isMobile ? 6 : 8;
        for (let k = 0; k < splitMeteors; k++) {
            const angle = ((k + 0.5) / splitMeteors) * Math.PI * 2;
            const speed = 17.5 + (k % 2) * 3.0;
            this._spawnSingleEnemy({
                type: k % 2,
                x: this.miniBossSplitOrigin.x + Math.cos(angle) * 1.4,
                y: this.miniBossSplitOrigin.y + Math.sin(angle) * 1.4,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                isNovaMeteor: true,
                isFragment: true,
                scale: 1.05,
                radius: 1.05,
                hp: 1
            });
        }

        if (this.audio && this.audio.playShipExplosion) {
            this.audio.playExplosion(1.45);
        }
        if (this.audio && this.audio.playBossWarning) {
            this.audio.playBossWarning();
        }

        const profile = (this.bossProfiles && this.bossProfiles.length)
            ? this.bossProfiles[(this.bossLevel - 1) % this.bossProfiles.length]
            : { name: 'Lord Lieblos', subtitle: 'Der Massenware-Monolith' };
        if (this.callbacks && this.callbacks.onBossUpdate) {
            this.callbacks.onBossUpdate(true, 100, profile.name + ' (Zwillings-Kerne)', 'Phase 2: Besiege beide Teil-Bosse!');
        }
        this._showFloatingBanner('⚡ BOSS-SPALTUNG: 2 ZWILLINGS-KERNE AKTIV! ⚡', '#f59e0b', '#fef08a');
    }

    defeatMiniBoss(index) {
        if (!this.miniBossActive[index]) return;
        this.miniBossActive[index] = false;
        const mb = this.miniBossMeshes[index];
        const pos = mb ? mb.position : this.miniBossSplitOrigin;

        this.cameraShake = 0.42;
        this.spawnExplosionBurst(pos, 0xfde047, 18, 22);
        this.spawnExplosionBurst(pos, 0xf97316, 16, 20);
        this.spawnExplosionBurst(pos, 0xc084fc, 14, 18);
        if (this.audio) this.audio.playExplosion(1.4);

        if (mb) mb.visible = false;
        this.addCombo(4);

        // Kleiner Zwischen-Drop beim Tod des ersten Mini-Bosses
        if (this.pools.collectibles.length > 0) {
            const c = this.pools.collectibles.pop();
            c.position.set(pos.x, pos.y, 0);
            this._configureCollectible(c, 0);
            c.visible = true;
            this.collectibles.push(c);
        }

        // Sind BEIDE Mini-Bosse besiegt -> Sektor-Boss endgültig geschlagen!
        if (!this.miniBossActive[0] && !this.miniBossActive[1]) {
            this.defeatSectorBoss(pos);
        } else if (this.callbacks && this.callbacks.onBossUpdate) {
            const totalHp = (this.miniBossHp[0] + this.miniBossHp[1]);
            const totalMax = this.miniBossMaxHp * 2;
            this.callbacks.onBossUpdate(true, (totalHp / totalMax) * 100);
        }
    }

    fireEnemyProjectile(originPos, vx, vy) {
        if (this.pools.lasers.length === 0) return;
        const proj = this.pools.lasers.pop();
        proj.position.copy(originPos);
        proj.position.y -= 2.2;
        proj.userData.vel.set(vx, vy, 0);
        proj.visible = true;
        this.enemyLasers.push(proj);
    }

    updateSectorBoss(dt) {
        if (!this.bossActive || !this.bossMesh) return;

        let spinMult = 1.0;

        // Animation der 3D Licht-Nova (bei Spawn in Phase 1 und bei Zwillings-Spaltung in Phase 2)
        if (this.bossIntroTimer > 0) {
            this.bossIntroTimer = Math.max(0, this.bossIntroTimer - dt);
            const p = 1.0 - (this.bossIntroTimer / Math.max(0.01, this.bossIntroDuration));
            spinMult = 1.0 + (1.0 - p) * 2.8;

            if (this.bossPhase === 1) {
                // Elastisches Herauswachsen aus dem Nova-Lichtkern (0.06 -> 1.12 -> 1.0)
                const bossScale = p < 0.72
                    ? (0.06 + (p / 0.72) * 1.06)
                    : (1.12 - ((p - 0.72) / 0.28) * 0.12);
                this.bossMesh.scale.setScalar(bossScale);
                this.bossMesh.position.set(0, 18.5, 0);
            }

            if (this.bossNovaGroup && this.bossNovaGroup.visible) {
                const nd = this.bossNovaGroup.userData;
                if (nd.core) {
                    const coreScale = p < 0.28 ? (1.0 + p * 11.0) : Math.max(0.05, 4.08 - (p - 0.28) * 5.2);
                    nd.core.scale.setScalar(coreScale);
                    nd.coreMat.opacity = Math.max(0, 1.0 - p * 1.18);
                }
                if (nd.halo) {
                    const haloScale = 14.0 + p * 34.0;
                    nd.halo.scale.set(haloScale, haloScale, 1);
                    nd.haloMat.opacity = Math.max(0, (1.0 - p) * 0.95);
                }
                if (nd.ring1) {
                    const r1Scale = 0.8 + p * 22.0;
                    nd.ring1.scale.set(r1Scale, r1Scale, 1);
                    nd.ring1.rotation.z += dt * 4.5;
                    nd.ring1Mat.opacity = Math.max(0, (1.0 - p) * 0.95);
                }
                if (nd.ring2) {
                    const r2Scale = 0.6 + p * 16.5;
                    nd.ring2.scale.set(r2Scale, r2Scale, 1);
                    nd.ring2.rotation.z -= dt * 3.8;
                    nd.ring2Mat.opacity = Math.max(0, (1.0 - p) * 0.88);
                }
                if (this.bossIntroTimer <= 0) {
                    this.bossNovaGroup.visible = false;
                    if (this.bossPhase === 1) this.bossMesh.scale.setScalar(1.0);
                }
            }
        }

        // --- PHASE 2: ZWEI KLEINE ZWILLINGS-BOSSE ---
        if (this.bossPhase === 2) {
            this.miniBossCombatTime += dt;
            const maxSpan = this.bounds ? Math.max(4.5, (this.bounds.xMax - this.bounds.xMin) * 0.38) : 9.5;
            const splitBlend = Math.min(1.0, this.miniBossCombatTime / 0.65); // Nahtloses Auseinanderdriften ohne Sprung!
            const easeSplit = 1.0 - Math.pow(1.0 - splitBlend, 3);

            for (let mi = 0; mi < 2; mi++) {
                if (!this.miniBossActive[mi] || !this.miniBossMeshes[mi]) continue;
                const mb = this.miniBossMeshes[mi];
                const dirSign = (mi === 0) ? -1 : 1;
                const targetFlankX = dirSign * (maxSpan * 0.54) + Math.sin(this.miniBossCombatTime * 2.1 + mi * Math.PI) * (maxSpan * 0.34);
                const targetFlankY = 18.2 + Math.sin(this.miniBossCombatTime * 2.7 + mi * 1.5) * 1.8;

                mb.position.x = this.miniBossSplitOrigin.x + (targetFlankX - this.miniBossSplitOrigin.x) * easeSplit;
                mb.position.y = this.miniBossSplitOrigin.y + (targetFlankY - this.miniBossSplitOrigin.y) * easeSplit;

                if (mb.userData.ring1) {
                    mb.userData.ring1.rotation.z += 3.0 * dt;
                    mb.userData.ring1.rotation.x += 1.6 * dt;
                }
                if (mb.userData.ring2) {
                    mb.userData.ring2.rotation.y -= 3.4 * dt;
                    mb.userData.ring2.rotation.z += 1.9 * dt;
                }
                if (mb.userData.wire) {
                    mb.userData.wire.rotation.y += 2.2 * dt;
                }
                if (mb.userData.glbModel) {
                    mb.userData.glbModel.rotation.y = Math.sin(this.miniBossCombatTime * 2.6 + mi) * 0.42;
                    mb.userData.glbModel.rotation.z = dirSign * -0.15 + Math.cos(this.miniBossCombatTime * 1.9) * 0.12;
                }
            }

            // Zwillings-Bosse feuern koordinierte Kreuz-Salven
            this.bossFireTimer -= dt;
            if (this.bossFireTimer <= 0 && !this.isDying) {
                const shotSpeed = 25 + Math.min(12, this.bossLevel * 2.5);
                for (let mi = 0; mi < 2; mi++) {
                    if (!this.miniBossActive[mi] || !this.miniBossMeshes[mi]) continue;
                    const mbPos = this.miniBossMeshes[mi].position;
                    const dx = this.ship.position.x - mbPos.x;
                    const dy = this.ship.position.y - mbPos.y;
                    const len = Math.max(1, Math.hypot(dx, dy));
                    const vx = (dx / len) * shotSpeed;
                    const vy = Math.min(-15, (dy / len) * shotSpeed);

                    this.fireEnemyProjectile(mbPos, vx, vy);
                    if (this.bossShotPattern % 2 === 1) {
                        this.fireEnemyProjectile(mbPos, vx - 5.2, vy);
                        this.fireEnemyProjectile(mbPos, vx + 5.2, vy);
                    }
                }
                if (this.audio && this.audio.playBossShot) {
                    this.audio.playBossShot();
                }
                this.bossShotPattern++;
                const bothAlive = (this.miniBossActive[0] && this.miniBossActive[1]);
                this.bossFireTimer = Math.max(0.85, (bothAlive ? 1.45 : 1.10) - (this.bossLevel * 0.08));
            }
            return;
        }

        // --- PHASE 1: HAUPT-BOSS KAMPFBEWEGUNG ---
        if (this.bossIntroTimer <= 0) {
            // Nahtlose Kampfbewegung ab (0, 18.5) — sin(0) === 0 verhindert JEDEN Positionssprung!
            this.bossCombatTime += dt;
            const maxSpan = this.bounds ? Math.max(4, (this.bounds.xMax - this.bounds.xMin) * 0.36) : 9;
            this.bossMesh.position.x = Math.sin(this.bossCombatTime * 1.35) * maxSpan;
            this.bossMesh.position.y = 18.5 + Math.sin(this.bossCombatTime * 2.1) * 1.4;
        }

        // Gyroskop-Ringe, 3D-Dreadnought & Kern rotieren
        if (this.bossMesh.userData.ring1) {
            this.bossMesh.userData.ring1.rotation.z += 2.2 * spinMult * dt;
            this.bossMesh.userData.ring1.rotation.x += 1.1 * spinMult * dt;
        }
        if (this.bossMesh.userData.ring2) {
            this.bossMesh.userData.ring2.rotation.y -= 2.6 * spinMult * dt;
            this.bossMesh.userData.ring2.rotation.z += 1.4 * spinMult * dt;
        }
        if (this.bossMesh.userData.wire) {
            this.bossMesh.userData.wire.rotation.y += 1.5 * spinMult * dt;
            this.bossMesh.userData.wire.rotation.x -= 1.2 * spinMult * dt;
        }
        if (this.bossMesh.userData.glbModel) {
            const t = this.bossCombatTime;
            this.bossMesh.userData.glbModel.rotation.y = Math.sin(t * 2.1) * 0.35;
            this.bossMesh.userData.glbModel.rotation.x = Math.sin(t * 1.6) * 0.18;
            this.bossMesh.userData.glbModel.rotation.z = Math.sin(t * 1.35) * -0.14;
        }

        // Haupt-Boss Plasma-Salven abfeuern (3 abwechslungsreiche Angriffsmuster)
        this.bossFireTimer -= dt;
        if (this.bossFireTimer <= 0 && !this.isDying) {
            const shotSpeed = 25 + Math.min(13, this.bossLevel * 2.6);
            const pattern = this.bossShotPattern % 3;
            if (pattern === 0) {
                // Muster A: Gezielter Präzisionsschuss + 2 Flankenschüsse
                const dx = this.ship.position.x - this.bossMesh.position.x;
                const dy = this.ship.position.y - this.bossMesh.position.y;
                const len = Math.max(1, Math.hypot(dx, dy));
                const vx = (dx / len) * shotSpeed;
                const vy = Math.min(-14, (dy / len) * shotSpeed);

                this.fireEnemyProjectile(this.bossMesh.position, vx, vy);
                this.fireEnemyProjectile(this.bossMesh.position, vx - 6.8, vy);
                this.fireEnemyProjectile(this.bossMesh.position, vx + 6.8, vy);
            } else if (pattern === 1) {
                // Muster B: Breiter 5er-Plasma-Fächer zur Raumkontrolle
                const fanVx = [-11.0, -5.5, 0, 5.5, 11.0];
                for (let k = 0; k < fanVx.length; k++) {
                    this.fireEnemyProjectile(this.bossMesh.position, fanVx[k], -shotSpeed);
                }
            } else {
                // Muster C: Doppelte Zangen-Salve + gezielter Kernschuss
                const dx = this.ship.position.x - this.bossMesh.position.x;
                const dy = this.ship.position.y - this.bossMesh.position.y;
                const len = Math.max(1, Math.hypot(dx, dy));
                this.fireEnemyProjectile(this.bossMesh.position, (dx / len) * (shotSpeed + 3), Math.min(-15, (dy / len) * (shotSpeed + 3)));
                const clampVx = [-8.5, -2.8, 2.8, 8.5];
                for (let k = 0; k < clampVx.length; k++) {
                    this.fireEnemyProjectile(this.bossMesh.position, clampVx[k], -shotSpeed * 0.94);
                }
            }
            if (this.audio && this.audio.playBossShot) {
                this.audio.playBossShot();
            }
            this.bossShotPattern++;
            this.bossFireTimer = Math.max(0.88, 1.50 - (this.bossLevel * 0.12));
        }
    }

    defeatSectorBoss(finalPos = null) {
        if (!this.bossActive || !this.bossMesh) return;
        const bossPos = finalPos || this.bossMesh.position;

        // Spektakuläre Boss-Detonation
        this.cameraShake = 0.58;
        this.spawnExplosionBurst(bossPos, 0xfde047, 24, 26);
        this.spawnExplosionBurst(bossPos, 0xf97316, 20, 22);
        this.spawnExplosionBurst(bossPos, 0xc084fc, 18, 24);
        this.spawnExplosionBurst(bossPos, 0xffffff, 14, 28);

        if (this.audio && this.audio.playShipExplosion) {
            this.audio.playShipExplosion();
        }

        // Belohnung: Kombo-Boost (+8) & 4 Collectible-Orbs (inkl. 25% Chance auf ein seltenes Rotes Herzchen!)
        this.addCombo(8);
        const dropHeartOnBoss = (Math.random() < 0.25);
        const offsets = [-3.0, -1.0, 1.0, 3.0];
        for (let i = 0; i < offsets.length; i++) {
            if (this.pools.collectibles.length > 0) {
                const c = this.pools.collectibles.pop();
                c.position.set(bossPos.x + offsets[i], bossPos.y - (i % 2) * 1.2, 0);
                let cType = 0;
                if (i === 3) {
                    cType = 1; // Blauer Cooldown-Orb
                } else if (i === 1 && dropHeartOnBoss) {
                    cType = 2; // Seltenes Rotes Herzchen ❤️
                }
                this._configureCollectible(c, cType);
                c.visible = true;
                this.collectibles.push(c);
            }
        }

        // Aktive Boss-Projektile beim Sieg auflösen
        while (this.enemyLasers.length > 0) {
            const el = this.enemyLasers.pop();
            el.visible = false;
            this.pools.lasers.push(el);
        }

        this.bossActive = false;
        this.bossPhase = 1;
        this.bossMesh.visible = false;
        this.miniBossActive = [false, false];
        if (this.miniBossMeshes && this.miniBossMeshes.length === 2) {
            this.miniBossMeshes[0].visible = false;
            this.miniBossMeshes[1].visible = false;
        }
        if (this.bossNovaGroup) this.bossNovaGroup.visible = false;
        this.nextBossDistance = (Math.floor(this.distance / 1500) + 1) * 1500;

        if (this.callbacks && this.callbacks.onBossUpdate) {
            this.callbacks.onBossUpdate(false, 0);
        }
        this._showFloatingBanner('🏆 SEKTOR-BOSS VOLLSTÄNDIG BESIEGT! 🏆', '#10b981', '#a7f3d0');
    }

    spawnLogic(dt) {
        this.enemySpawnTimer -= dt;
        this.collectibleSpawnTimer -= dt;

        // Sektor-Boss alle 1.500 m prüfen
        if (!this.bossActive && this.distance >= this.nextBossDistance) {
            this.spawnSectorBoss();
        }

        if (this.bossActive) {
            this.updateSectorBoss(dt);
            // Während des Bosskampfes (nach der Licht-Nova-Eröffnung) vereinzelte Begleit-Meteoriten spawnen
            if (this.enemySpawnTimer <= 0 && this.bossIntroTimer <= 0) {
                if (this.enemies.length < 3 && this.pools.enemies.length > 0) {
                    this._spawnSingleEnemy({
                        type: 0,
                        scale: 1.25,
                        hp: 2,
                        fallSpeedMult: 1.15
                    });
                }
                this.enemySpawnTimer = 1.45;
            }
            return;
        }

        const difficultyLevel = Math.floor(this.distance / 400);
        // Im Schlachtschiff-Modus deutlich höhere Gegnerdichte, mehr feindliche Raumschiffe & mehr Action!
        const baseSpawnRate = Math.max(0.30, 0.68 - (this.distance / 6000));
        const spawnRate = this.isBattleshipMode ? Math.max(0.19, baseSpawnRate * 0.62) : baseSpawnRate;
        const maxActiveEnemies = this.isBattleshipMode
            ? Math.min(this.isMobile ? 14 : 20, 10 + Math.floor(this.distance / 260))
            : Math.min(this.isMobile ? 9 : 13, 6 + Math.floor(this.distance / 350));

        if (this.enemySpawnTimer <= 0) {
            if (this.enemies.length < maxActiveEnemies && this.pools.enemies.length > 0) {
                const roll = Math.random();
                const spawnMinX = (this.bounds ? this.bounds.xMin : -14) + 2.0;
                const spawnMaxX = (this.bounds ? this.bounds.xMax : 14) - 2.0;
                const centerSpan = Math.max(4, spawnMaxX - spawnMinX);

                // 0. SCHLACHTSCHIFF-MODUS (oder ab 260m): Feindliches Raider-Geschwader (2–3 feindliche Raumschiffe im Angriffsflug!)
                if ((this.isBattleshipMode && roll < 0.34) || (!this.isBattleshipMode && this.distance >= 260 && roll < 0.14)) {
                    const sqCenterX = spawnMinX + 3 + Math.random() * Math.max(2, centerSpan - 6);
                    this._spawnSingleEnemy({
                        isRaider: true,
                        x: sqCenterX,
                        y: this.isBattleshipMode ? 42 : 36,
                        driftSpeed: 2.6,
                        driftRange: 4.5,
                        fallSpeedMult: 1.05 + difficultyLevel * 0.10
                    });
                    if (this.isBattleshipMode && this.pools.enemies.length >= 2) {
                        this._spawnSingleEnemy({
                            isRaider: true,
                            x: sqCenterX - 4.8,
                            y: 45,
                            driftSpeed: 2.3,
                            driftRange: 3.5,
                            fallSpeedMult: 1.08 + difficultyLevel * 0.10
                        });
                        if (!this.isMobile && this.pools.enemies.length >= 1) {
                            this._spawnSingleEnemy({
                                isRaider: true,
                                x: sqCenterX + 4.8,
                                y: 45,
                                driftSpeed: 2.3,
                                driftRange: 3.5,
                                fallSpeedMult: 1.08 + difficultyLevel * 0.10
                            });
                        }
                    }
                }
                // 1. Ab 320m: Plasma-Sperrbarriere (blockiert eine Bahn + zwingt zu Spurwechsel / Teleport / Schild)
                else if (this.distance >= 320 && roll < (this.isBattleshipMode ? 0.44 : 0.25)) {
                    const lane = Math.floor(Math.random() * 3); // 0 = links, 1 = mitte, 2 = rechts
                    const laneX = lane === 0 ? (spawnMinX + 3.5) : (lane === 1 ? 0 : (spawnMaxX - 3.5));
                    this._spawnSingleEnemy({
                        isWall: true,
                        x: laneX,
                        y: 36,
                        fallSpeedMult: 1.05 + Math.min(0.6, difficultyLevel * 0.08)
                    });
                }
                // 2. Ab 150m: V-Formation / Zangen-Welle (2–3 koordinierte Meteoriten)
                else if (this.distance >= 150 && roll < 0.36 && this.pools.enemies.length >= 3) {
                    const centerX = spawnMinX + 3 + Math.random() * Math.max(2, centerSpan - 6);
                    this._spawnSingleEnemy({
                        type: 1,
                        x: centerX,
                        y: 35,
                        canSplit: true,
                        fallSpeedMult: 1.15 + difficultyLevel * 0.12
                    });
                    this._spawnSingleEnemy({
                        type: 0,
                        x: centerX - 4.2,
                        y: 38.5,
                        vx: 2.4,
                        fallSpeedMult: 1.15 + difficultyLevel * 0.12
                    });
                    if (!this.isMobile || difficultyLevel >= 2) {
                        this._spawnSingleEnemy({
                            type: 0,
                            x: centerX + 4.2,
                            y: 38.5,
                            vx: -2.4,
                            fallSpeedMult: 1.15 + difficultyLevel * 0.12
                        });
                    }
                }
                // 3. Ab 220m: Sucher-Komet (lenkt aktiv Richtung Spieler-Schiff, verhindert Stehenbleiben)
                else if (this.distance >= 220 && roll < 0.56) {
                    this._spawnSingleEnemy({
                        type: 1,
                        isSeeker: true,
                        scale: 1.4,
                        hp: 2 + difficultyLevel * 2,
                        fallSpeedMult: 1.38 + Math.min(0.8, difficultyLevel * 0.12)
                    });
                }
                // 4. Splitter-Kristall (zerbricht beim Abschuss in 2 schnelle Diagonal-Fragmente)
                else if (roll < 0.78) {
                    const isGiant = (this.distance >= 600 && Math.random() < 0.14);
                    this._spawnSingleEnemy({
                        type: 1,
                        isGiant: isGiant,
                        canSplit: !isGiant,
                        driftSpeed: (!isGiant && Math.random() < 0.55) ? (2.4 + Math.random() * 2.5) : 0,
                        driftRange: 4.5 + Math.random() * 6.5,
                        fallSpeedMult: (isGiant ? 0.88 : 1.12) + difficultyLevel * 0.12
                    });
                }
                // 5. Schneller Schauer-Meteorit mit Seitwärts-Drift
                else {
                    const doesDrift = Math.random() < (0.45 + Math.min(0.4, difficultyLevel * 0.08));
                    this._spawnSingleEnemy({
                        type: 0,
                        driftSpeed: doesDrift ? (2.2 + Math.random() * 3.2) : 0,
                        driftRange: doesDrift ? (4 + Math.random() * 8) : 0,
                        fallSpeedMult: 1.15 + difficultyLevel * 0.14
                    });
                }
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

                // Aufgabe 2: Sehr seltene Chance (4%) auf ein Rotes Herzchen ❤️, 15% Blauer Cooldown-Orb, sonst Gold-Funke
                const cRoll = Math.random();
                const cType = cRoll < 0.04 ? 2 : (cRoll < 0.19 ? 1 : 0);
                this._configureCollectible(m, cType);

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
        if (!this.isDying && Math.random() < 0.6) {
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

        // --- BURNING HULL FIRE & SMOKE TRAIL (when ship has taken 1 or 2 unshielded hits!) ---
        if (!this.isDying && this.hullHP < this.maxHullHP) {
            const isCritical = (this.hullHP <= 1);
            const fireChance = isCritical ? 0.92 : 0.65;
            if (Math.random() < fireChance && this.pools.particles.length > 0) {
                let fp = this.pools.particles.pop();
                const sideOffset = isCritical ? ((Math.random() > 0.5 ? 0.65 : -0.68) + (Math.random() - 0.5) * 0.35) : (-0.65 + (Math.random() - 0.5) * 0.35);
                fp.position.copy(this.ship.position);
                fp.position.x += sideOffset;
                fp.position.y += (Math.random() - 0.5) * 0.6;
                fp.position.z = 0.5;

                const rollMat = Math.random();
                if (rollMat < 0.38) {
                    fp.material = this.matParticleSmoke || this.matParticleOrange;
                } else if (rollMat < 0.72) {
                    fp.material = this.matParticleRed || this.matParticleOrange;
                } else {
                    fp.material = (Math.random() > 0.4 ? this.matParticleOrange : this.matParticleYellow);
                }

                fp.userData.vel.set((Math.random() - 0.5) * 4.5, -(8 + Math.random() * 10), (Math.random() - 0.5) * 2);
                fp.userData.life = (rollMat < 0.38 ? 0.65 : 0.42) + Math.random() * 0.25;
                fp.scale.setScalar((isCritical ? 1.35 : 1.0) * (0.7 + Math.random() * 0.9));
                fp.visible = true;
                this.particles.push(fp);
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
        const baseFallSpeed = (this.currentSpeed * 1.65) * dt;
        const minX = this.bounds ? (this.bounds.xMin + 0.8) : -14;
        const maxX = this.bounds ? (this.bounds.xMax - 0.8) : 14;

        for(let i=this.enemies.length-1; i>=0; i--) {
            let e = this.enemies[i];

            if ((e.userData.isNovaMeteor || e.userData.isBounced) && typeof e.userData.vy === 'number') {
                // 360°-Boss-Nova-Meteoriten & am Schild abgeprallte Meteoriten fliegen physikalisch davon
                e.position.x += (e.userData.vx || 0) * dt;
                e.position.y += e.userData.vy * dt;
            } else {
                let fallSpeedY = e.userData.isWall
                    ? (this.currentSpeed * 1.35 * dt)
                    : (baseFallSpeed * (e.userData.fallSpeedMult || 1));

                e.position.y -= fallSpeedY;

                // Lateral velocity (Splitter-Fragmente / V-Formation)
                if (e.userData.vx) {
                    e.position.x += e.userData.vx * dt;
                    if (e.position.x <= minX || e.position.x >= maxX) {
                        e.userData.vx *= -1; // Abprallen am Bildschirmrand!
                        e.position.x = Math.max(minX, Math.min(maxX, e.position.x));
                    }
                }
                // Seeker / Homing Comet (lenkt auf den Spieler zu, solange oberhalb des Schiffs)
                else if (e.userData.isSeeker && !this.isDying && e.position.y > this.ship.position.y + 2.5) {
                    const dxToShip = this.ship.position.x - e.position.x;
                    const seekSpeed = Math.max(-7.5, Math.min(7.5, dxToShip * 1.6));
                    e.position.x += seekSpeed * dt;
                }
                // Sinus-Drift / Zick-Zack logic
                else if (e.userData.driftSpeed > 0 && !e.userData.isWall) {
                    let elapsed = (now - e.userData.spawnTime) / 1000;
                    e.position.x = Math.max(minX, Math.min(maxX, e.userData.baseX + (Math.sin(elapsed * e.userData.driftSpeed) * e.userData.driftRange)));
                }
            }
            
            e.position.z = 0; // Lock perfectly onto 2D Plane Z=0

            if(!e.userData.isWall && e.userData.activeMesh) {
                if (e.userData.isRaider) {
                    // Feindliches Raumschiff neigt sich in Flugrichtung und feuert gezielte Plasma-Schüsse!
                    e.userData.activeMesh.rotation.z = Math.sin((now - e.userData.spawnTime) * 0.005) * 0.25;
                    e.userData.raiderFireTimer = (e.userData.raiderFireTimer || 1.0) - dt;
                    if (e.userData.raiderFireTimer <= 0 && !this.isDying && e.position.y > this.ship.position.y + 5 && e.position.y < 36) {
                        const rdx = this.ship.position.x - e.position.x;
                        const rdy = this.ship.position.y - e.position.y;
                        const rlen = Math.max(1, Math.hypot(rdx, rdy));
                        const rSpeed = 23 + Math.min(8, Math.floor(this.distance / 500) * 2);
                        this.fireEnemyProjectile(e.position, (rdx / rlen) * rSpeed, Math.min(-14, (rdy / rlen) * rSpeed));
                        e.userData.raiderFireTimer = this.isBattleshipMode ? 1.25 : 1.65;
                    }
                } else {
                    const rotMult = e.userData.isBounced ? 4.2 : ((e.userData.isFragment || e.userData.isSeeker || e.userData.isNovaMeteor) ? 2.4 : 1.0);
                    e.userData.activeMesh.rotation.x += 1.2 * rotMult * dt; 
                    e.userData.activeMesh.rotation.y += 2.2 * rotMult * dt; 
                }
            }

            if(e.position.y < -20 || ((e.userData.isNovaMeteor || e.userData.isBounced) && (e.position.y > 46 || e.position.x < -36 || e.position.x > 36))) {
                if (!e.userData.isWall && !e.userData.isFragment && !e.userData.isNovaMeteor && !e.userData.isBounced && this.comboCount > 0) {
                    // Durchgebrochene Meteoriten kühlen die Waffen-Kombo ab
                    this.comboDecayTimer = Math.min(this.comboDecayTimer, 1.2);
                }
                e.visible = false; 
                this.pools.enemies.push(e); 
                this._removeAtSwap(this.enemies, i);
            }
        }

        // Boss Enemy Projectiles
        for (let i = this.enemyLasers.length - 1; i >= 0; i--) {
            let el = this.enemyLasers[i];
            el.position.addScaledVector(el.userData.vel, dt);
            if (el.position.y < -22 || el.position.y > 50 || el.position.x < -35 || el.position.x > 35) {
                el.visible = false;
                this.pools.lasers.push(el);
                this._removeAtSwap(this.enemyLasers, i);
            }
        }

        // Collectibles (inkl. Funken-Magnet für fortgeschrittene Raketen-Skins!)
        const pulseScale = 1.0 + Math.sin(now * 0.01) * 0.2;
        const magnetRange = 3.8 * (this.shipMagnetMult || 1.0);
        const magnetRangeSq = magnetRange * magnetRange;
        for(let i=this.collectibles.length-1; i>=0; i--) {
            let c = this.collectibles[i];
            c.position.y -= baseFallSpeed;

            if (!this.isDying && this.ship && this.shipMagnetMult > 1.02) {
                const dx = this.ship.position.x - c.position.x;
                const dy = this.ship.position.y - c.position.y;
                const distSq = dx * dx + dy * dy;
                if (distSq > 0.01 && distSq < magnetRangeSq) {
                    const dist = Math.sqrt(distSq);
                    const pullSpeed = (14.0 * this.shipMagnetMult) * (1.1 - dist / magnetRange);
                    c.position.x += (dx / dist) * pullSpeed * dt;
                    c.position.y += (dy / dist) * pullSpeed * dt;
                }
            }

            if (c.userData.type === 2) {
                // Rotes 3D-Herzchen pulsiert kräftiger und rotiert edel im Raum
                c.scale.setScalar(1.12 + Math.sin(now * 0.014) * 0.22);
                c.rotation.y += 2.6 * dt;
            } else {
                c.scale.setScalar(pulseScale);
            }
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
        // Check Bullets vs Sector Boss (Phase 1), Twin Mini-Bosses (Phase 2) & Enemies
        const bulletDmg = this.shipWeaponPowerMult || 1.0;
        for(let i=this.bullets.length-1; i>=0; i--) {
            let b = this.bullets[i];
            let hit = false;

            // 1a. Treffer gegen aktiven Haupt-Sektor-Boss (Phase 1)
            if (this.bossActive && this.bossPhase === 1 && this.bossMesh && this.bossMesh.visible) {
                const bdx = b.position.x - this.bossMesh.position.x;
                const bdy = b.position.y - this.bossMesh.position.y;
                if ((bdx * bdx + bdy * bdy) < (4.1 * 4.1)) {
                    hit = true;
                    this.bossHp = Math.max(0, this.bossHp - bulletDmg);
                    this.comboDecayTimer = 5.2; // Boss-Treffer halten die Waffen-Kombo aktiv!
                    if (this.callbacks && this.callbacks.onBossUpdate) {
                        this.callbacks.onBossUpdate(true, (this.bossHp / this.bossMaxHp) * 100);
                    }
                    if (this.bossHp <= 0) {
                        // Aufgabe 1: Wenn der Haupt-Boss stirbt, teilt er sich in 2 kleine Zwillings-Bosse!
                        this.splitSectorBossIntoTwins();
                    } else {
                        if (this.audio && this.audio.playHit) this.audio.playHit();
                        this.spawnParticle(b.position, 0xfda4af, 3);
                    }
                }
            }
            // 1b. Treffer gegen die 2 kleinen Zwillings-Bosse (Phase 2)
            else if (this.bossActive && this.bossPhase === 2 && this.miniBossMeshes) {
                for (let mi = 0; mi < 2; mi++) {
                    if (!this.miniBossActive[mi] || !this.miniBossMeshes[mi] || !this.miniBossMeshes[mi].visible) continue;
                    const mb = this.miniBossMeshes[mi];
                    const mbdx = b.position.x - mb.position.x;
                    const mbdy = b.position.y - mb.position.y;
                    if ((mbdx * mbdx + mbdy * mbdy) < (2.75 * 2.75)) {
                        hit = true;
                        this.miniBossHp[mi] = Math.max(0, this.miniBossHp[mi] - bulletDmg);
                        this.comboDecayTimer = 5.2;
                        if (this.miniBossHp[mi] <= 0) {
                            this.defeatMiniBoss(mi);
                        } else {
                            if (this.callbacks && this.callbacks.onBossUpdate) {
                                const totalHp = this.miniBossHp[0] + this.miniBossHp[1];
                                const totalMax = this.miniBossMaxHp * 2;
                                this.callbacks.onBossUpdate(true, (totalHp / totalMax) * 100);
                            }
                            if (this.audio && this.audio.playHit) this.audio.playHit();
                            this.spawnParticle(b.position, 0xfde047, 3);
                        }
                        break;
                    }
                }
            }

            if (!hit) {
                for(let j=this.enemies.length-1; j>=0; j--) {
                    let em = this.enemies[j];
                    const dx = b.position.x - em.position.x;
                    const dy = b.position.y - em.position.y;
                    
                    // Stretch hitbox vertically slightly based on bullet speed to prevent phasing through enemies at low fps
                    let speedStretch = b.userData.dir.y * 120 * (1/60) * 0.5;
                    let dyStretched = Math.max(0, Math.abs(dy) - Math.abs(speedStretch));
                    let isHit = false;

                    if (em.userData.isWall) {
                        if (Math.abs(dx) < 5.6 && dyStretched < 1.1) {
                            isHit = true;
                        }
                    } else {
                        const rSum = em.userData.radius + 0.8;
                        if ((dx * dx + dyStretched * dyStretched) < (rSum * rSum)) {
                            isHit = true;
                        }
                    }

                    if (isHit) {
                        hit = true;
                        // Apply DMG (scaled by Battleship weapon_power_mult!)
                        em.userData.hp -= bulletDmg;
                        if (em.userData.hp <= 0) {
                            this.killEnemy(em, j);
                        } else {
                            // Matter Treffer-Sound & Funken-Effekt
                            if (this.audio && this.audio.playHit) this.audio.playHit();
                            this.spawnParticle(b.position, 0xffffff, 4);
                        }
                        break;
                    }
                }
            }
            if(hit) {
                b.visible = false;
                this.pools.bullets.push(b);
                this._removeAtSwap(this.bullets, i);
            }
        }

        // Check Ship vs Collectibles (0 = Funke, 1 = Cooldown, 2 = Seltenes Rotes Herzchen ❤️)
        for(let i=this.collectibles.length-1; i>=0; i--) {
            let c = this.collectibles[i];
            const rSum = this.shipBounds + c.userData.radius;
            if (this.ship.position.distanceToSquared(c.position) < (rSum * rSum)) {

                // Collect effect
                for(let k=0; k<12; k++) this.spawnParticle(c.position, c.material.color.getHex());

                if (c.userData.type === 2) {
                    // Aufgabe 2: Rotes Herzchen eingesammelt!
                    this.spawnExplosionBurst(c.position, 0xef4444, 14, 16);
                    this.spawnExplosionBurst(c.position, 0xffffff, 8, 14);
                    if (this.audio && this.audio.playHeartPickup) {
                        this.audio.playHeartPickup();
                    } else if (this.audio) {
                        this.audio.playPickup();
                    }

                    if (this.hullHP < this.maxHullHP) {
                        this.hullHP = Math.min(this.maxHullHP, this.hullHP + 1);
                        if (this.callbacks && this.callbacks.onHullUpdate) {
                            this.callbacks.onHullUpdate(this.hullHP, this.maxHullHP);
                        }
                        this._showFloatingBanner('❤️ +1 HERZ — RUMPF REPARIERT!', '#f43f5e', '#ffe4e6');
                    } else {
                        // Bei bereits vollen 3/3 Herzen gibt das seltene Herz einen +35% Schild-Überladungs-Boost!
                        const boostedShield = Math.min(100, (this.shieldHP || 0) + 35);
                        this._activateShieldWithHP(boostedShield, 20.0 * (this.shipSkillDurMult || 1.0));
                        this._showFloatingBanner('❤️ VOLLE HÜLLE: +35% SCHILD-ÜBERLADUNG!', '#38bdf8', '#e0f2fe');
                    }
                } else if (c.userData.type === 0) {
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

            // If teleporting/invulnerable state or just bounced off shield, skip collision
            if(this.activeSkills.teleport.waitingForClick) continue;
            if (em.userData.shieldBounceIgnoreUntil && performance.now() < em.userData.shieldBounceIgnoreUntil) continue;

            const collisionDist = this.activeSkills.shield.active ? 3.25 : this.shipBounds;

            let hit = false;
            const dx = this.ship.position.x - em.position.x;
            const dy = this.ship.position.y - em.position.y;

            if (em.userData.isWall) {
                if (Math.abs(dx) < 5.5 + collisionDist && Math.abs(dy) < 0.65 + collisionDist) hit = true;
            } else {
                const rSum = collisionDist + em.userData.radius;
                if ((dx * dx + dy * dy) < (rSum * rSum)) hit = true;
            }

            if (hit) {

                if (this.activeSkills.shield.active) {
                    // Schild absorbiert den Treffer & nimmt Schaden, Meteoriten prallen physikalisch am Schild ab!
                    this.triggerShieldImpact(em.position.x, em.position.y);
                    this.shieldHP = Math.max(0, this.shieldHP - 34); // 3 hits breaks it
                    this.callbacks.onShieldUpdate(this.shieldHP);
                    this.degradeCombo();

                    if (em.userData.isWall) {
                        this.killEnemy(em, i, false);
                    } else {
                        this.bounceEnemyOffShield(em, collisionDist);
                    }

                    if (this.shieldHP <= 0) {
                        this.activeSkills.shield.timer = 0; // Break shield
                    }
                } else {
                    // Ohne Schild hält das Raumschiff 3 Treffer aus, fängt aber sichtbar an zu brennen!
                    if (!em.userData.isWall) {
                        this.killEnemy(em, i, false);
                    }
                    if (this.handleHullDamage()) {
                        return;
                    }
                }
            }
        }

        // Check Ship vs Boss Plasma Projectiles & Boss / Mini-Boss Bodies
        if (!this.activeSkills.teleport.waitingForClick) {
            const collisionDist = this.activeSkills.shield.active ? 3.0 : this.shipBounds;

            for (let i = this.enemyLasers.length - 1; i >= 0; i--) {
                let el = this.enemyLasers[i];
                const dx = this.ship.position.x - el.position.x;
                const dy = this.ship.position.y - el.position.y;
                const rSum = collisionDist + 0.5;
                if ((dx * dx + dy * dy) < (rSum * rSum)) {
                    el.visible = false;
                    this.pools.lasers.push(el);
                    this._removeAtSwap(this.enemyLasers, i);

                    if (this.activeSkills.shield.active) {
                        this.triggerShieldImpact(el.position.x, el.position.y);
                        this.shieldHP -= 25;
                        this.callbacks.onShieldUpdate(this.shieldHP);
                        this.degradeCombo();
                        this.spawnParticle(this.ship.position, 0x60a5fa, 8);
                        if (this.shieldHP <= 0) {
                            this.activeSkills.shield.timer = 0;
                        }
                    } else {
                        if (this.handleHullDamage()) {
                            return;
                        }
                    }
                }
            }

            if (this.bossActive && this.bossPhase === 1 && this.bossMesh && this.bossMesh.visible) {
                const bdx = this.ship.position.x - this.bossMesh.position.x;
                const bdy = this.ship.position.y - this.bossMesh.position.y;
                const rSum = collisionDist + 3.4;
                if ((bdx * bdx + bdy * bdy) < (rSum * rSum)) {
                    if (this.activeSkills.shield.active) {
                        this.shieldHP = 0;
                        this.activeSkills.shield.timer = 0;
                        this.callbacks.onShieldUpdate(0);
                        this.degradeCombo();
                        this.ship.position.y = Math.min(this.ship.position.y, this.bossMesh.position.y - 6);
                    } else {
                        this.ship.position.y = Math.min(this.ship.position.y, this.bossMesh.position.y - 6);
                        if (this.handleHullDamage()) {
                            return;
                        }
                    }
                }
            } else if (this.bossActive && this.bossPhase === 2 && this.miniBossMeshes) {
                for (let mi = 0; mi < 2; mi++) {
                    if (!this.miniBossActive[mi] || !this.miniBossMeshes[mi] || !this.miniBossMeshes[mi].visible) continue;
                    const mb = this.miniBossMeshes[mi];
                    const mbdx = this.ship.position.x - mb.position.x;
                    const mbdy = this.ship.position.y - mb.position.y;
                    const rSum = collisionDist + 2.3;
                    if ((mbdx * mbdx + mbdy * mbdy) < (rSum * rSum)) {
                        if (this.activeSkills.shield.active) {
                            this.shieldHP = 0;
                            this.activeSkills.shield.timer = 0;
                            this.callbacks.onShieldUpdate(0);
                            this.degradeCombo();
                            this.ship.position.y = Math.min(this.ship.position.y, mb.position.y - 4.5);
                        } else {
                            this.ship.position.y = Math.min(this.ship.position.y, mb.position.y - 4.5);
                            if (this.handleHullDamage()) {
                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    bounceEnemyOffShield(em, shieldRadius) {
        let nx = em.position.x - this.ship.position.x;
        let ny = em.position.y - this.ship.position.y;
        let len = Math.hypot(nx, ny);
        if (len < 0.01) {
            nx = (Math.random() > 0.5 ? 0.45 : -0.45);
            ny = 0.89;
        } else {
            nx /= len;
            ny /= len;
        }
        // Bei fast mittigem Aufprall seitlichen Abprall-Impuls geben, damit der Meteorit dynamisch weggeschleudert wird
        if (Math.abs(nx) < 0.24) {
            nx += (nx >= 0 ? 0.36 : -0.36);
        }
        if (ny > -0.35 && ny < 0.48) {
            ny = 0.55;
        }
        const normLen = Math.hypot(nx, ny) || 1;
        nx /= normLen;
        ny /= normLen;

        // Sofort außerhalb der Schild-Sphäre setzen, damit kein Doppel-Treffer im nächsten Frame passiert
        const safeDist = shieldRadius + (em.userData.radius || 1.2) + 0.38;
        em.position.x = this.ship.position.x + nx * safeDist;
        em.position.y = this.ship.position.y + ny * safeDist;

        const bounceSpeed = Math.max(26, this.currentSpeed * 2.35);
        em.userData.vx = nx * bounceSpeed;
        em.userData.vy = ny * bounceSpeed;
        em.userData.isBounced = true;
        em.userData.isSeeker = false;
        em.userData.driftSpeed = 0;
        em.userData.shieldBounceIgnoreUntil = performance.now() + 360;

        // Plasma-Funken am Einschlagpunkt auf der Schildkuppel
        const contactPos = this._tempVec.set(
            this.ship.position.x + nx * shieldRadius,
            this.ship.position.y + ny * shieldRadius,
            0.5
        );
        this.spawnExplosionBurst(contactPos, 0x38bdf8, 12, 18);
        this.spawnExplosionBurst(contactPos, 0xffffff, 7, 15);
        this.cameraShake = Math.max(this.cameraShake, 0.22);

        if (this.audio && this.audio.playHit) {
            this.audio.playHit();
        }
    }

    handleHullDamage() {
        if (this.isDying || this.isGameOver) return true;
        if (this.hullInvulnTimer > 0) return false;

        this.hullHP = Math.max(0, this.hullHP - 1);
        this.degradeCombo();

        if (this.callbacks && this.callbacks.onHullUpdate) {
            this.callbacks.onHullUpdate(this.hullHP, this.maxHullHP);
        }

        // Beim 3. ungeschützten Treffer (hullHP <= 0) explodiert das Schiff endgültig
        if (this.hullHP <= 0) {
            this.startShipDestruction();
            return true;
        }

        // Treffer 1 & 2: Kurze Unverwundbarkeit (1.45s), Rumpf-Explosion & Schiff fängt an zu brennen!
        this.hullInvulnTimer = 1.45;
        this.cameraShake = (this.hullHP === 1) ? 0.50 : 0.35;

        this.spawnExplosionBurst(this.ship.position, 0xf97316, 16, 20);
        this.spawnExplosionBurst(this.ship.position, 0xef4444, 14, 16);
        this.spawnExplosionBurst(this.ship.position, 0x475569, 10, 12);

        if (this.audio) {
            this.audio.playExplosion(this.hullHP === 1 ? 1.35 : 1.15);
        }

        return false;
    }

    startShipDestruction() {
        if (this.isDying || this.isGameOver) return;
        this.isDying = true;
        if (this.hullFireGroup) this.hullFireGroup.visible = false;
        this.deathTimer = 1.35; // 1.35s dramatische Raumschiff-Explosion vor dem Game-Over-Menü
        this.deathStage = 0;
        this.cameraShake = 0.65;
        this.timeScale = 1.0;
        this.deathOrigin.copy(this.ship.position);

        if (this.shipModel) {
            this.shipModel.visible = false;
        }

        // Aktive Drohnen / Zielkreuze beim Tod ausblenden
        const ch = document.getElementById('ff-crosshair');
        if (ch) ch.remove();
        this.activeSkills.teleport.waitingForClick = false;
        this._hasTeleportAim = false;
        if (this.teleportBeacon) this.teleportBeacon.visible = false;
        if (this.teleportDustPoints) this.teleportDustPoints.visible = false;
        while (this.drones.length > 0) {
            let d = this.drones.pop();
            d.visible = false;
            this.pools.drones.push(d);
        }

        // Schockwellen-Ring & Lichtblitz aktivieren
        if (this.deathShockwaveRing) {
            this.deathShockwaveRing.position.copy(this.deathOrigin);
            this.deathShockwaveRing.scale.set(1, 1, 1);
            this.deathShockwaveMat.opacity = 0.95;
            this.deathShockwaveRing.visible = true;
        }
        if (this.deathCoreFlash) {
            this.deathCoreFlash.position.copy(this.deathOrigin);
            this.deathCoreFlash.scale.setScalar(1.2);
            this.deathCoreFlashMat.opacity = 1.0;
            this.deathCoreFlash.visible = true;
        }

        // Haupt-Partikel-Eruption
        this.spawnExplosionBurst(this.deathOrigin, 0xf97316, 22, 26);
        this.spawnExplosionBurst(this.deathOrigin, 0xfde047, 18, 22);
        this.spawnExplosionBurst(this.deathOrigin, 0xffffff, 14, 30);
        this.spawnExplosionBurst(this.deathOrigin, 0x60a5fa, 12, 20);

        // Kräftiger Raumschiff-Explosions-Sound
        if (this.audio && this.audio.playShipExplosion) {
            this.audio.playShipExplosion();
        } else if (this.audio) {
            this.audio.playExplosion(1.5);
        }

        if (this.callbacks && this.callbacks.onShipDestroyed) {
            this.callbacks.onShipDestroyed();
        }
    }

    spawnExplosionBurst(pos, colorHex, count = 12, speedScale = 16) {
        const sharedMaterial = this.getParticleMaterial(colorHex);
        for (let i = 0; i < count; i++) {
            if (this.pools.particles.length > 0) {
                let p = this.pools.particles.pop();
                p.position.copy(pos);
                p.material = sharedMaterial;
                p.userData.life = 1.1 + Math.random() * 0.4;
                const angle = Math.random() * Math.PI * 2;
                const spd = (0.35 + Math.random() * 0.85) * speedScale;
                p.userData.vel.set(Math.cos(angle) * spd, Math.sin(angle) * spd, (Math.random() - 0.5) * 8);
                p.scale.setScalar(1.2 + Math.random() * 0.9);
                p.visible = true;
                this.particles.push(p);
            }
        }
    }

    killEnemy(enemyMesh, index, allowSplit = true) {
        const wasSplitter = allowSplit && enemyMesh.userData.canSplit && !enemyMesh.userData.isFragment;
        const splitX = enemyMesh.position.x;
        const splitY = enemyMesh.position.y;
        const baseFallMult = enemyMesh.userData.fallSpeedMult || 1.15;

        const soundIntensity = enemyMesh.userData.isWall
            ? 1.5
            : (enemyMesh.userData.radius > 3.0 ? 1.55 : (enemyMesh.userData.type === 1 ? 1.2 : (enemyMesh.userData.isFragment ? 0.78 : 1.0)));
        this.audio.playExplosion(soundIntensity);

        // Explosion particles
        let color = 0xffffff;
        if (enemyMesh.userData.isWall) { color = 0xf97316; }
        else if (enemyMesh.userData.type === 1) { color = 0x8b5cf6; } // Purple
        else if (enemyMesh.userData.type === 0) { color = 0xef4444; } // Red
        
        for(let k=0; k<15; k++) this.spawnParticle(enemyMesh.position, color);

        enemyMesh.visible = false;
        this.pools.enemies.push(enemyMesh);
        this._removeAtSwap(this.enemies, index);

        // Waffen-Kombo erhöhen
        if (allowSplit) {
            this.addCombo(enemyMesh.userData.isWall ? 2 : 1);
        }

        // Splitter-Mechanik: Kristall-Meteoriten zerplatzen beim Abschuss in 2 schnelle Diagonal-Fragmente!
        if (wasSplitter && splitY > this.ship.position.y + 4) {
            this._spawnSingleEnemy({
                type: 0,
                x: splitX - 0.9,
                y: splitY,
                vx: -8.5,
                scale: 0.92,
                radius: 0.95,
                hp: 1,
                isFragment: true,
                fallSpeedMult: baseFallMult * 1.22
            });
            this._spawnSingleEnemy({
                type: 0,
                x: splitX + 0.9,
                y: splitY,
                vx: 8.5,
                scale: 0.92,
                radius: 0.95,
                hp: 1,
                isFragment: true,
                fallSpeedMult: baseFallMult * 1.22
            });
        }

        // Aufgabe 2: Sehr seltene Drop-Chance für ein Rotes 3D-Herzchen ❤️ (1.8% normal, 3.2% wenn das Schiff bereits brennt!)
        const heartChance = (this.hullHP < this.maxHullHP) ? 0.032 : 0.018;
        const dropRoll = Math.random();
        if (dropRoll < heartChance) {
            if (this.pools.collectibles.length > 0) {
                let m = this.pools.collectibles.pop();
                m.position.copy(enemyMesh.position);
                this._configureCollectible(m, 2); // Rotes Herzchen ❤️
                m.visible = true;
                this.collectibles.push(m);
            }
        } else if (dropRoll < (heartChance + 0.19)) {
            if(this.pools.collectibles.length > 0) {
                let m = this.pools.collectibles.pop();
                m.position.copy(enemyMesh.position);
                const isBlue = Math.random() < 0.2;
                this._configureCollectible(m, isBlue ? 1 : 0);
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
        if(colorHex === 0xef4444) return this.matParticleRed || this.matParticleOrange;
        if(colorHex === 0x475569) return this.matParticleSmoke || this.matParticleWhite;
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
