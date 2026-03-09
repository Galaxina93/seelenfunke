@script
<script>
    window._threeEngineInstance = null;

    window.Configurator3DEngine = class Configurator3DEngine {
        constructor(container, modelPath, bgPath, config) {
            this.container = container;
            this.modelPath = modelPath;
            this.bgPath = bgPath;
            this.config = config || {};

            this.scene = null;
            this.camera = null;
            this.renderer = null;
            this.controls = null;

            this.model = null;
            this.modelContainer = null;

            this.texturePlaneFront = null;
            this.textureCanvasFront = null;
            this.textureCtxFront = null;
            this.textureFront = null;

            this.texturePlaneBack = null;
            this.textureCanvasBack = null;
            this.textureCtxBack = null;
            this.textureBack = null;

            this.isReady = false;
        }

        init(onLoadedCallback) {
            if (this.isReady) return;

            const isMobile = window.innerWidth < 768;

            this.scene = new window.THREE.Scene();

            if (!this.bgPath) {
                this.scene.background = new window.THREE.Color('#0b0f19');
            } else {
                this.scene.background = null;
            }

            const aspect = this.container.offsetWidth / this.container.offsetHeight;
            this.camera = new window.THREE.PerspectiveCamera(45, aspect, 0.1, 1000);

            // FIX: 'lowp' entfernt! PBR Materialien brauchen zwingend 'highp' (auch auf dem Handy), sonst werden sie schwarz.
            this.renderer = new window.THREE.WebGLRenderer({
                antialias: !isMobile,
                alpha: true,
                precision: 'highp',
                powerPreference: 'high-performance',
                stencil: false,
                depth: true
            });

            this.renderer.setSize(this.container.offsetWidth, this.container.offsetHeight);
            this.renderer.setPixelRatio(isMobile ? 1 : Math.min(window.devicePixelRatio, 2));
            this.renderer.toneMapping = window.THREE.ACESFilmicToneMapping;
            this.renderer.toneMappingExposure = 1.0;

            this.container.innerHTML = '';
            this.container.appendChild(this.renderer.domElement);

            // Stärkeres Umgebungslicht auf Mobile als Ausgleich
            this.scene.add(new window.THREE.AmbientLight(0xffffff, isMobile ? 3.0 : 1.5));

            const mainLight = new window.THREE.DirectionalLight(0xffffff, 2.5);
            mainLight.position.set(10, 10, 10);
            this.scene.add(mainLight);

            const fillLight = new window.THREE.DirectionalLight(0xffffff, 2.0);
            fillLight.position.set(-10, 5, 10);
            this.scene.add(fillLight);

            const backLight = new window.THREE.DirectionalLight(0xffffff, 2.0);
            backLight.position.set(0, 10, -10);
            this.scene.add(backLight);

            this.controls = new window.OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping = true;
            this.controls.dampingFactor = 0.08;
            this.controls.enablePan = false;
            this.controls.maxPolarAngle = Math.PI / 1.6;

            if(this.bgPath) {
                const loader = new window.THREE.TextureLoader();
                loader.load(this.bgPath, (t) => {
                    t.mapping = window.THREE.EquirectangularReflectionMapping;
                    this.scene.background = t;
                    // HDRI Environment auf Mobile weglassen, um Speicher zu sparen
                    if (!isMobile) {
                        this.scene.environment = t;
                    }
                });
            }

            const texSize = isMobile ? 512 : 2048;

            this.textureCanvasFront = document.createElement('canvas');
            this.textureCanvasFront.width = texSize;
            this.textureCanvasFront.height = texSize;
            this.textureCtxFront = this.textureCanvasFront.getContext('2d');
            try {
                this.textureFront = new window.THREE.CanvasTexture(this.textureCanvasFront);
                if (this.textureFront) {
                    this.textureFront.flipY = true;
                    this.textureFront.wrapS = window.THREE.RepeatWrapping;
                    this.textureFront.generateMipmaps = false;
                    this.textureFront.minFilter = window.THREE.LinearFilter;
                    if (typeof this.textureFront.anisotropy !== 'undefined') this.textureFront.anisotropy = isMobile ? 1 : 4;
                    this.textureFront.needsUpdate = true;
                }
            } catch (e) { this.textureFront = new window.THREE.Texture(); }

            this.textureCanvasBack = document.createElement('canvas');
            this.textureCanvasBack.width = texSize;
            this.textureCanvasBack.height = texSize;
            this.textureCtxBack = this.textureCanvasBack.getContext('2d');
            try {
                this.textureBack = new window.THREE.CanvasTexture(this.textureCanvasBack);
                if (this.textureBack) {
                    this.textureBack.flipY = true;
                    this.textureBack.wrapS = window.THREE.RepeatWrapping;
                    this.textureBack.generateMipmaps = false;
                    this.textureBack.minFilter = window.THREE.LinearFilter;
                    if (typeof this.textureBack.anisotropy !== 'undefined') this.textureBack.anisotropy = isMobile ? 1 : 4;
                    this.textureBack.needsUpdate = true;
                }
            } catch (e) { this.textureBack = new window.THREE.Texture(); }

            const gltfLoader = new window.GLTFLoader();
            gltfLoader.load(
                this.modelPath,
                (gltf) => {
                    this.model = gltf.scene;

                    const box = new window.THREE.Box3().setFromObject(this.model);
                    const size = box.getSize(new window.THREE.Vector3());
                    const center = box.getCenter(new window.THREE.Vector3());
                    this.model.position.sub(center);

                    this.modelContainer = new window.THREE.Group();
                    this.modelContainer.add(this.model);

                    this.updateOverlayGeometry();

                    const maxDim = Math.max(size.x, size.y, size.z);
                    const fov = this.camera.fov * (Math.PI / 180);
                    let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));

                    this.controls.minDistance = maxDim * 0.7;
                    this.controls.maxDistance = maxDim * 3.0;

                    this.camera.position.set(0, maxDim * 0.2, cameraZ * 1.5);
                    this.camera.updateProjectionMatrix();

                    this.scene.add(this.modelContainer);

                    this.applyMaterial();
                    this.applyTransforms();

                    this.isReady = true;
                    if(onLoadedCallback) onLoadedCallback();
                    this.animate();
                },
                undefined,
                (err) => {
                    console.error('GLB Loading Error:', err);
                    if(onLoadedCallback) onLoadedCallback();
                }
            );
        }

        updateOverlayGeometry() {
            if (!this.modelContainer || !this.model) return;

            const isCylinder = this.config.overlay_type === 'cylinder';

            // DYNAMISCHE SPIEGELUNG: Unterscheidet jetzt zwischen Zylinder (Weizenglas) und Flach (Anhänger)!
            if (this.textureFront) {
                this.textureFront.repeat.x = isCylinder ? 1 : -1;
                this.textureFront.offset.x = isCylinder ? 0 : 1;
                this.textureFront.needsUpdate = true;
            }
            if (this.textureBack) {
                this.textureBack.repeat.x = isCylinder ? 1 : -1;
                this.textureBack.offset.x = isCylinder ? 0 : 1;
                this.textureBack.needsUpdate = true;
            }

            if (this.texturePlaneFront) {
                if(this.texturePlaneFront.geometry) this.texturePlaneFront.geometry.dispose();
                this.modelContainer.remove(this.texturePlaneFront);
            }
            if (this.texturePlaneBack) {
                if(this.texturePlaneBack.geometry) this.texturePlaneBack.geometry.dispose();
                this.modelContainer.remove(this.texturePlaneBack);
            }

            const box = new window.THREE.Box3().setFromObject(this.model);
            const size = box.getSize(new window.THREE.Vector3());

            let geo;
            let baseZ = 0;

            if (isCylinder) {
                geo = new window.THREE.CylinderGeometry(1, 1, size.y, 32, 16, true);

                const pos = geo.attributes.position;
                const raycaster = new window.THREE.Raycaster();

                this.model.updateMatrixWorld(true);

                for (let i = 0; i < pos.count; i++) {
                    let py = pos.getY(i);
                    let dir = new window.THREE.Vector3(pos.getX(i), 0, pos.getZ(i)).normalize();

                    let rayOrigin = new window.THREE.Vector3(dir.x * 50, py, dir.z * 50);
                    let rayDir = dir.clone().negate();
                    raycaster.set(rayOrigin, rayDir);

                    let intersects = raycaster.intersectObject(this.model, true);

                    if (intersects.length > 0) {
                        let hit = intersects[0].point;
                        let dist = Math.sqrt(hit.x * hit.x + hit.z * hit.z);
                        let finalRadius = dist + 0.001;
                        pos.setX(i, dir.x * finalRadius);
                        pos.setZ(i, dir.z * finalRadius);
                    } else {
                        pos.setX(i, dir.x * (size.x / 2 + 0.001));
                        pos.setZ(i, dir.z * (size.z / 2 + 0.001));
                    }
                }
                geo.computeVertexNormals();
                baseZ = 0;
            } else {
                const maxPlaneSize = Math.max(size.x, size.y, size.z) * 1.5;
                geo = new window.THREE.PlaneGeometry(maxPlaneSize, maxPlaneSize);
                baseZ = (size.z / 2) + 0.001;
            }

            const matFront = new window.THREE.MeshBasicMaterial({ map: this.textureFront, transparent: true, depthWrite: false, side: window.THREE.DoubleSide });
            this.texturePlaneFront = new window.THREE.Mesh(geo, matFront);
            this.texturePlaneFront.userData.baseZ = baseZ;
            this.modelContainer.add(this.texturePlaneFront);

            if (this.config.has_back_side) {
                const matBack = new window.THREE.MeshBasicMaterial({ map: this.textureBack, transparent: true, depthWrite: false, side: window.THREE.DoubleSide });
                this.texturePlaneBack = new window.THREE.Mesh(geo, matBack);
                this.texturePlaneBack.userData.baseZ = baseZ;
                this.modelContainer.add(this.texturePlaneBack);
            }

            this.applyTransforms();
        }

        applyMaterial() {
            if(!this.model) return;
            const matType = this.config.material_type || 'glass';
            const isMobile = window.innerWidth < 768;

            // WICHTIG: Auf Mobile haben wir kein Environment, also müssen wir das berücksichtigen
            const hasEnv = !isMobile && !!this.bgPath;

            this.model.traverse((child) => {
                if(child.isMesh && child.material) {
                    const oldMap = child.material.map;

                    if(matType === 'glass') {
                        if (isMobile) {
                            // FALLBACK FÜR MOBILE: StandardMaterial (braucht kein HDRI um gut auszusehen)
                            child.material = new window.THREE.MeshStandardMaterial({
                                map: oldMap,
                                color: 0xffffff,
                                roughness: 0.3,
                                metalness: 0.1, // Sehr geringe Metalness, damit es nicht schwarz wird!
                                transparent: true,
                                opacity: 0.5,
                                depthWrite: false,
                                side: window.THREE.FrontSide
                            });
                        } else {
                            // DESKTOP: Das hochauflösende PhysicalMaterial
                            child.material = new window.THREE.MeshPhysicalMaterial({
                                map: oldMap,
                                color: 0xffffff,
                                metalness: hasEnv ? 0.3 : 0.0,
                                roughness: hasEnv ? 0.05 : 0.2,
                                transparent: true,
                                opacity: 0.80,
                                depthWrite: false,
                                side: window.THREE.FrontSide
                            });
                        }
                    } else if(matType === 'wood') {
                        child.material = new window.THREE.MeshStandardMaterial({
                            map: oldMap,
                            color: 0xffffff,
                            roughness: 0.9,
                            metalness: 0.0
                        });
                    } else if(matType === 'metal') {
                        child.material = new window.THREE.MeshStandardMaterial({
                            map: oldMap,
                            color: 0xffffff,
                            roughness: hasEnv ? 0.2 : 0.4,
                            metalness: hasEnv ? 0.9 : 0.0
                        });
                    } else {
                        child.material = new window.THREE.MeshStandardMaterial({
                            map: oldMap,
                            color: 0xffffff,
                            roughness: 0.5,
                            metalness: 0.0
                        });
                    }
                    child.material.needsUpdate = true;
                }
            });
        }

        applyTransforms(activeSide = 'front') {
            if(!this.modelContainer) return;

            const c = this.config;
            const s = (c.model_scale || 100) / 100;
            this.modelContainer.scale.set(s, s, s);

            const posX = (c.model_pos_x || 0) * 0.1;
            const posY = (c.model_pos_y || 0) * 0.1;
            const posZ = (c.model_pos_z || 0) * 0.1;

            this.modelContainer.position.set(posX, posY, posZ);

            if (this.controls) {
                this.controls.target.set(posX, posY, posZ);
                this.controls.update();
            }

            this.modelContainer.rotation.set(
                (c.model_rot_x || 0) * (Math.PI / 180),
                (c.model_rot_y || 0) * (Math.PI / 180),
                (c.model_rot_z || 0) * (Math.PI / 180)
            );

            if(this.texturePlaneFront) {
                const s_front = (c.engraving_scale || 100) / 100;
                this.texturePlaneFront.scale.set(s_front, s_front, s_front);
                this.texturePlaneFront.position.set(
                    (c.engraving_pos_x || 0) * 0.1,
                    (c.engraving_pos_y || 0) * 0.1,
                    (this.texturePlaneFront.userData.baseZ || 0) + ((c.engraving_pos_z || 0) * 0.1)
                );
                this.texturePlaneFront.rotation.set(
                    (c.engraving_rot_x || 0) * (Math.PI / 180),
                    (c.engraving_rot_y || 0) * (Math.PI / 180),
                    (c.engraving_rot_z || 0) * (Math.PI / 180)
                );
            }

            if(this.texturePlaneBack && c.has_back_side) {
                const s_back = (c.back_engraving_scale || 100) / 100;
                this.texturePlaneBack.scale.set(s_back, s_back, s_back);
                this.texturePlaneBack.position.set(
                    (c.back_engraving_pos_x || 0) * 0.1,
                    (c.back_engraving_pos_y || 0) * 0.1,
                    (this.texturePlaneBack.userData.baseZ || 0) + ((c.back_engraving_pos_z || 0) * 0.1)
                );
                this.texturePlaneBack.rotation.set(
                    (c.back_engraving_rot_x || 0) * (Math.PI / 180),
                    (c.back_engraving_rot_y || 0) * (Math.PI / 180),
                    (c.back_engraving_rot_z || 0) * (Math.PI / 180)
                );
            }
        }

        renderBothCanvases(textsFront, logosFront, textsBack, logosBack, fontMap) {
            if(!this.isReady) return;

            const drawToCtx = (ctx, canvas, texts, logos, textureObj) => {
                if(!ctx) return;
                const cw = canvas.width;
                ctx.clearRect(0, 0, cw, cw);

                const toPx = (p) => (p / 100) * cw;

                const applyClipping = (c) => {
                    c.beginPath();
                    let x = (this.config.area_left || 0) / 100 * cw;
                    let y = (this.config.area_top || 0) / 100 * cw;
                    let w = (this.config.area_width || 100) / 100 * cw;
                    let h = (this.config.area_height || 100) / 100 * cw;
                    let shape = this.config.area_shape || 'rect';

                    if(shape === 'rect') {
                        c.rect(x, y, w, h);
                    } else if(shape === 'circle') {
                        c.ellipse(x + w/2, y + h/2, w/2, h/2, 0, 0, Math.PI * 2);
                    } else if(shape === 'custom') {
                        let pts = this.config.custom_points;
                        if(pts && pts.length > 0) {
                            pts.forEach((p, i) => {
                                let px = (p.x || 0) / 100 * cw;
                                let py = (p.y || 0) / 100 * cw;
                                if(i === 0) c.moveTo(px, py);
                                else c.lineTo(px, py);
                            });
                        }
                    }
                    c.closePath();
                    c.clip();
                };

                if(logos) {
                    logos.forEach(logo => {
                        const img = new Image();
                        img.crossOrigin = 'anonymous';
                        img.src = logo.url;
                        img.onload = () => {
                            ctx.save();
                            applyClipping(ctx);
                            ctx.translate(toPx(logo.x || 50), toPx(logo.y || 50));
                            ctx.rotate((logo.rotation || 0) * Math.PI / 180);

                            const scale = ((logo.size || 100) / 500) * cw;
                            const aspect = img.width / img.height;
                            const drawW = scale;
                            const drawH = scale / aspect;

                            // VISUELLER FILTER: Macht alle eingefügten Logos optisch weiß für die "Lasergravur-Optik"
                            // Die Originaldatei (logo.url) bleibt schwarz für den XCS Export!
                            ctx.filter = 'brightness(0) invert(1) opacity(0.85)';
                            ctx.drawImage(img, -drawW/2, -drawH/2, drawW, drawH);
                            ctx.filter = 'none'; // Filter zurücksetzen für andere Operationen

                            ctx.restore();
                            if(textureObj) textureObj.needsUpdate = true;
                        };
                    });
                }

                if(texts) {
                    texts.forEach(item => {
                        if(!item.text) return;

                        ctx.save();
                        applyClipping(ctx);

                        ctx.translate(toPx(item.x || 50), toPx(item.y || 50));
                        ctx.rotate((item.rotation || 0) * Math.PI / 180);

                        const fontSize = (item.size || 1) * (cw / 25);
                        ctx.font = `bold ${fontSize}px ${fontMap[item.font] || 'Arial'}`;

                        ctx.filter = 'grayscale(100%) brightness(1.5)';
                        ctx.fillStyle = (this.config.material_type === 'wood') ? 'rgba(50,30,20,0.9)' : 'rgba(255,255,255,0.95)';
                        ctx.textAlign = item.align || 'center';
                        ctx.textBaseline = 'middle';

                        if(this.config.material_type === 'glass') {
                            ctx.shadowColor = 'rgba(255,255,255,0.8)';
                            ctx.shadowBlur = cw <= 1024 ? 5 : 15;
                        }

                        const lines = item.text.split('\n');
                        const lineHeight = fontSize * 1.15;
                        const totalHeight = (lines.length - 1) * lineHeight;
                        let startY = -totalHeight / 2;

                        const yOffset = fontSize * 0.12;

                        lines.forEach(line => {
                            ctx.fillText(line, 0, startY + yOffset);
                            startY += lineHeight;
                        });

                        ctx.restore();
                        ctx.filter = 'none';
                    });
                }
                if(textureObj) textureObj.needsUpdate = true;
            };

            drawToCtx(this.textureCtxFront, this.textureCanvasFront, textsFront, logosFront, this.textureFront);

            if (this.config.has_back_side) {
                drawToCtx(this.textureCtxBack, this.textureCanvasBack, textsBack, logosBack, this.textureBack);
            }
        }

        resize() {
            if(!this.camera || !this.renderer) return;
            this.camera.aspect = this.container.offsetWidth / this.container.offsetHeight;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(this.container.offsetWidth, this.container.offsetHeight);
        }

        animate() {
            if (window._frontendConfiguratorDataInstance && window._frontendConfiguratorDataInstance.showDrawingBoard) {
                this._isAnimating = false;
                return;
            }

            this._isAnimating = true;
            requestAnimationFrame(() => this.animate());

            if (this.controls) this.controls.update();
            if (this.renderer && this.scene && this.camera) {
                this.renderer.render(this.scene, this.camera);
            }
        }
    };
</script>
@endscript
