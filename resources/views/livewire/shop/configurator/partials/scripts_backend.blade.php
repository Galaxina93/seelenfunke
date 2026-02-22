{{-- resources/views/livewire/shop/configurator/partials/scripts_backend.blade.php --}}
@script
<script>
    /**
     * ENGINE-ID: Diese Instanz wird außerhalb des reaktiven Alpine-Scopes gespeichert,
     * um TypeError: 'get' on proxy Fehlermeldungen bei Three.js Objekten zu vermeiden.
     */
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
            this.texturePlane = null;

            this.textureCanvas = null;
            this.textureCtx = null;
            this.texture = null;

            this.isReady = false;
        }

        init(onLoadedCallback) {
            this.scene = new window.THREE.Scene();
            this.scene.background = null;

            this.camera = new window.THREE.PerspectiveCamera(45, this.container.offsetWidth / this.container.offsetHeight, 0.1, 1000);

            this.renderer = new window.THREE.WebGLRenderer({ antialias: true, alpha: true });
            this.renderer.setSize(this.container.offsetWidth, this.container.offsetHeight);
            this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

            this.renderer.toneMapping = window.THREE.ACESFilmicToneMapping;
            this.renderer.toneMappingExposure = 1.0;

            this.container.innerHTML = '';
            this.container.appendChild(this.renderer.domElement);

            this.scene.add(new window.THREE.AmbientLight(0xffffff, 0.9));
            const mainLight = new window.THREE.DirectionalLight(0xffffff, 1.5);
            mainLight.position.set(5, 10, 7.5);
            this.scene.add(mainLight);

            this.controls = new window.OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping = true;
            this.controls.enablePan = false;

            if(this.config.bgPath) {
                const loader = new window.THREE.TextureLoader();
                loader.load(this.config.bgPath, (t) => {
                    t.mapping = window.THREE.EquirectangularReflectionMapping;
                    this.scene.environment = t;
                });
            }

            this.textureCanvas = document.createElement('canvas');
            this.textureCanvas.width = 2048;
            this.textureCanvas.height = 2048;
            this.textureCtx = this.textureCanvas.getContext('2d');

            this.texture = new window.THREE.CanvasTexture(this.textureCanvas);
            this.texture.flipY = true;
            this.texture.anisotropy = 16;

            const gltfLoader = new window.GLTFLoader();
            gltfLoader.load(this.modelPath, (gltf) => {
                this.model = gltf.scene;

                const box = new window.THREE.Box3().setFromObject(this.model);
                const size = box.getSize(new window.THREE.Vector3());
                const center = box.getCenter(new window.THREE.Vector3());

                this.model.position.sub(center);
                this.modelContainer = new window.THREE.Group();
                this.modelContainer.add(this.model);

                const maxPlaneSize = Math.max(size.x, size.y);
                const planeGeo = new window.THREE.PlaneGeometry(maxPlaneSize, maxPlaneSize);
                const planeMat = new window.THREE.MeshBasicMaterial({
                    map: this.texture,
                    transparent: true,
                    depthWrite: false,
                    side: window.THREE.DoubleSide
                });
                this.texturePlane = new window.THREE.Mesh(planeGeo, planeMat);
                this.texturePlane.userData.baseZ = (size.z / 2) + 0.05;
                this.texturePlane.position.z = this.texturePlane.userData.baseZ;

                this.modelContainer.add(this.texturePlane);

                const maxDim = Math.max(size.x, size.y, size.z);
                const fov = this.camera.fov * (Math.PI / 180);
                let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));

                this.camera.position.set(0, maxDim * 0.2, cameraZ * 1.5);
                this.controls.target.set(0, 0, 0);
                this.camera.updateProjectionMatrix();

                this.scene.add(this.modelContainer);

                this.applyMaterial();
                this.applyTransforms();

                this.isReady = true;
                if(onLoadedCallback) onLoadedCallback();
                this.animate();
            }, undefined, (err) => {
                console.error("GLB Error", err);
                if(onLoadedCallback) onLoadedCallback();
            });
        }

        applyMaterial() {
            if(!this.model) return;
            const matType = this.config.material_type || 'glass';

            this.model.traverse((child) => {
                if (child.isMesh && child.material) {
                    const oldMap = child.material.map;

                    if (matType === 'glass') {
                        child.material = new window.THREE.MeshPhysicalMaterial({
                            map: oldMap,
                            color: 0xffffff,
                            metalness: 0.3, roughness: 0.05,
                            transparent: true, opacity: 0.80,
                            depthWrite: false, side: window.THREE.FrontSide
                        });
                    } else if (matType === 'wood') {
                        child.material = new window.THREE.MeshStandardMaterial({
                            map: oldMap, color: 0xffffff, roughness: 0.9, metalness: 0.0
                        });
                    } else if (matType === 'metal') {
                        child.material = new window.THREE.MeshStandardMaterial({
                            map: oldMap, color: 0xffffff, roughness: 0.2, metalness: 0.9
                        });
                    } else {
                        child.material = new window.THREE.MeshStandardMaterial({
                            map: oldMap, color: 0xffffff, roughness: 0.5, metalness: 0.1
                        });
                    }
                    child.material.needsUpdate = true;
                }
            });
        }

        applyTransforms() {
            if(!this.modelContainer) return;
            const c = this.config;

            const s = (c.model_scale || 100) / 100;
            this.modelContainer.scale.set(s, s, s);
            this.modelContainer.position.set((c.model_pos_x || 0) * 0.1, (c.model_pos_y || 0) * 0.1, (c.model_pos_z || 0) * 0.1);
            this.modelContainer.rotation.set((c.model_rot_x || 0) * (Math.PI / 180), (c.model_rot_y || 0) * (Math.PI / 180), (c.model_rot_z || 0) * (Math.PI / 180));

            if(this.texturePlane) {
                const s_eng = (c.engraving_scale || 100) / 100;
                this.texturePlane.scale.set(s_eng, s_eng, s_eng);
                this.texturePlane.position.set(
                    (c.engraving_pos_x || 0) * 0.1,
                    (c.engraving_pos_y || 0) * 0.1,
                    (this.texturePlane.userData.baseZ || 0) + ((c.engraving_pos_z || 0) * 0.1)
                );
                this.texturePlane.rotation.set(
                    (c.engraving_rot_x || 0) * (Math.PI / 180),
                    (c.engraving_rot_y || 0) * (Math.PI / 180),
                    (c.engraving_rot_z || 0) * (Math.PI / 180)
                );
            }
        }

        renderCanvas(texts, logos, fontMap) {
            if(!this.isReady || !this.textureCtx) return;

            this.textureCtx.clearRect(0, 0, 2048, 2048);
            const toPx = (p) => (p / 100) * 2048;

            const applyClipping = (ctx) => {
                ctx.beginPath();
                let x = (this.config.area_left || 0) / 100 * 2048;
                let y = (this.config.area_top || 0) / 100 * 2048;
                let w = (this.config.area_width || 100) / 100 * 2048;
                let h = (this.config.area_height || 100) / 100 * 2048;
                let shape = this.config.area_shape || 'rect';

                if (shape === 'rect') {
                    ctx.rect(x, y, w, h);
                } else if (shape === 'circle') {
                    ctx.ellipse(x + w/2, y + h/2, w/2, h/2, 0, 0, Math.PI * 2);
                } else if (shape === 'custom') {
                    let pts = this.config.custom_points;
                    if (pts && pts.length > 0) {
                        pts.forEach((p, i) => {
                            let px = (p.x || 0) / 100 * 2048;
                            let py = (p.y || 0) / 100 * 2048;
                            if (i === 0) ctx.moveTo(px, py);
                            else ctx.lineTo(px, py);
                        });
                    }
                }
                ctx.closePath();
                ctx.clip();
            };

            if (logos) {
                logos.forEach(logo => {
                    const img = new Image();
                    img.crossOrigin = "anonymous";
                    img.src = logo.url;
                    img.onload = () => {
                        this.textureCtx.save();
                        applyClipping(this.textureCtx);
                        this.textureCtx.translate(toPx(logo.x || 50), toPx(logo.y || 50));
                        this.textureCtx.rotate((logo.rotation || 0) * Math.PI / 180);
                        const scale = ((logo.size || 100) / 500) * 2048;
                        this.textureCtx.drawImage(img, -scale/2, -scale/2, scale, scale);
                        this.textureCtx.restore();
                        if(this.texture) this.texture.needsUpdate = true;
                    };
                });
            }

            if (texts) {
                texts.forEach(item => {
                    if(!item.text) return;
                    this.textureCtx.save();
                    applyClipping(this.textureCtx);
                    this.textureCtx.translate(toPx(item.x || 50), toPx(item.y || 50));
                    this.textureCtx.rotate((item.rotation || 0) * Math.PI / 180);

                    const fontSize = (item.size || 1) * 81.92;
                    this.textureCtx.font = `bold ${fontSize}px ${fontMap[item.font] || 'Arial'}`;
                    this.textureCtx.fillStyle = (this.config.material_type === 'wood') ? 'rgba(50, 30, 20, 0.9)' : 'rgba(255, 255, 255, 0.95)';
                    this.textureCtx.textAlign = item.align || 'center';
                    this.textureCtx.textBaseline = 'middle';

                    if(this.config.material_type === 'glass') {
                        this.textureCtx.shadowColor = "rgba(255,255,255,0.8)";
                        this.textureCtx.shadowBlur = 15;
                    }

                    const lines = item.text.split('\n');
                    const lineHeight = fontSize * 1.15;
                    const totalHeight = (lines.length - 1) * lineHeight;
                    let startY = -totalHeight / 2;

                    lines.forEach(line => {
                        this.textureCtx.fillText(line, 0, startY);
                        startY += lineHeight;
                    });

                    this.textureCtx.restore();
                });
            }

            if(this.texture) this.texture.needsUpdate = true;
        }

        resize() {
            if(!this.camera || !this.renderer) return;
            this.camera.aspect = this.container.offsetWidth / this.container.offsetHeight;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(this.container.offsetWidth, this.container.offsetHeight);
        }

        animate() {
            requestAnimationFrame(() => this.animate());
            if (this.controls) this.controls.update();
            if (this.renderer && this.scene && this.camera) {
                this.renderer.render(this.scene, this.camera);
            }
        }
    };
</script>
@endscript
