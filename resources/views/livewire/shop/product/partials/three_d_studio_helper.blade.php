@script
<script>
    if (!window.Seelenfunke3DStudio) {
        window.Seelenfunke3DStudio = {
            DEFAULT_PRESETS: {
                dark_metal_studio: {
                    key: 'dark_metal_studio',
                    tone_mapping_exposure: 0.88,
                    mobile_exposure: 0.94,
                    ambient_intensity: 0.24,
                    mobile_ambient: 0.48,
                    hemi_sky_color: '#fff4e2',
                    hemi_ground_color: '#0b0e14',
                    hemi_intensity: 0.26,
                    mobile_hemi: 0.40,
                    env_intensity: 0.30,
                    material_roughness: 0.34,
                    material_metalness: 0.72,
                    material_env_intensity: 0.24,
                    key_light: { color: '#fff6ea', intensity: 1.75, pos: [16, 18, 4.2] },
                    fill_light: { color: '#e8f0ff', intensity: 0.60, pos: [-16, -5, 4.0] },
                    rim_light: { color: '#ffdfaa', intensity: 1.85, pos: [0, 16, -11] },
                    back_key_light: { color: '#fff6ea', intensity: 1.70, pos: [-16, 18, -4.2] },
                    back_fill_light: { color: '#e8f0ff', intensity: 0.55, pos: [16, -5, -4.0] }
                },
                precious_metal_studio: {
                    key: 'precious_metal_studio',
                    tone_mapping_exposure: 0.96,
                    mobile_exposure: 1.00,
                    ambient_intensity: 0.85,
                    mobile_ambient: 1.45,
                    hemi_sky_color: '#fff8ec',
                    hemi_ground_color: '#1a1c24',
                    hemi_intensity: 0.45,
                    mobile_hemi: 0.60,
                    env_intensity: 0.80,
                    material_roughness: 0.22,
                    material_metalness: 0.55,
                    material_env_intensity: 0.80,
                    key_light: { color: '#ffffff', intensity: 2.15, pos: [11, 12, 8.5] },
                    fill_light: { color: '#fff8f0', intensity: 1.45, pos: [-11, 5, 8.5] },
                    rim_light: { color: '#ffe9c2', intensity: 1.85, pos: [0, 12, -11] },
                    back_key_light: { color: '#ffffff', intensity: 2.05, pos: [-11, 12, -8.5] },
                    back_fill_light: { color: '#fff8f0', intensity: 1.40, pos: [11, 5, -8.5] }
                },
                crystal_studio: {
                    key: 'crystal_studio',
                    tone_mapping_exposure: 1.00,
                    mobile_exposure: 1.04,
                    ambient_intensity: 1.15,
                    mobile_ambient: 2.40,
                    hemi_sky_color: '#ffffff',
                    hemi_ground_color: '#162030',
                    hemi_intensity: 0.35,
                    mobile_hemi: 0.55,
                    env_intensity: 0.95,
                    material_roughness: 0.04,
                    material_metalness: 0.04,
                    material_env_intensity: 0.45,
                    key_light: { color: '#ffffff', intensity: 2.35, pos: [10, 10, 10] },
                    fill_light: { color: '#ffffff', intensity: 1.85, pos: [-10, 5, 10] },
                    rim_light: { color: '#ffffff', intensity: 1.50, pos: [0, 12, -10] },
                    back_key_light: { color: '#ffffff', intensity: 2.25, pos: [-10, 10, -10] },
                    back_fill_light: { color: '#ffffff', intensity: 1.80, pos: [10, 5, -10] }
                },
                matte_studio: {
                    key: 'matte_studio',
                    tone_mapping_exposure: 0.96,
                    mobile_exposure: 1.00,
                    ambient_intensity: 0.85,
                    mobile_ambient: 1.40,
                    hemi_sky_color: '#fffaf0',
                    hemi_ground_color: '#181512',
                    hemi_intensity: 0.35,
                    mobile_hemi: 0.50,
                    env_intensity: 0.55,
                    material_roughness: 0.70,
                    material_metalness: 0.00,
                    material_env_intensity: 0.35,
                    key_light: { color: '#fff9f0', intensity: 1.80, pos: [10, 11, 8] },
                    fill_light: { color: '#f3f6ff', intensity: 1.10, pos: [-10, 4, 8] },
                    rim_light: { color: '#ffe8c8', intensity: 1.20, pos: [0, 11, -10] },
                    back_key_light: { color: '#fff9f0', intensity: 1.70, pos: [-10, 11, -8] },
                    back_fill_light: { color: '#f3f6ff', intensity: 1.05, pos: [10, 4, -8] }
                }
            },

            configureRenderer(renderer, THREE, exposure = 1.0) {
                if (!renderer || !THREE) return;
                if (typeof THREE.ACESFilmicToneMapping !== 'undefined') {
                    renderer.toneMapping = THREE.ACESFilmicToneMapping;
                    renderer.toneMappingExposure = Number.isFinite(Number(exposure)) ? Number(exposure) : 1.0;
                }
                if (typeof THREE.SRGBColorSpace !== 'undefined') {
                    renderer.outputColorSpace = THREE.SRGBColorSpace;
                }
            },

            clampNullable(val, min, max) {
                if (val === null || val === undefined || val === '') return null;
                const num = Number(val);
                if (!Number.isFinite(num)) return null;
                return Math.round(Math.max(min, Math.min(max, num)) * 1000) / 1000;
            },

            isDarkTextureImage(tex) {
                if (!tex || !tex.image) return false;
                if (tex.userData && typeof tex.userData._sfIsDarkTex === 'boolean') {
                    return tex.userData._sfIsDarkTex;
                }
                const img = tex.image;
                const w = img.naturalWidth || img.width || 0;
                const h = img.naturalHeight || img.height || 0;
                if (!w || !h) return false;

                try {
                    const c = document.createElement('canvas');
                    const sz = 24;
                    c.width = sz;
                    c.height = sz;
                    const ctx = c.getContext('2d', { willReadFrequently: true });
                    if (!ctx) return false;
                    ctx.drawImage(img, 0, 0, sz, sz);
                    const data = ctx.getImageData(0, 0, sz, sz).data;

                    const x0 = Math.floor(sz * 0.30);
                    const x1 = Math.ceil(sz * 0.70);
                    const y0 = Math.floor(sz * 0.45);
                    const y1 = Math.ceil(sz * 0.78);
                    let lumSum = 0;
                    let count = 0;

                    for (let y = y0; y <= y1; y++) {
                        for (let x = x0; x <= x1; x++) {
                            const idx = (y * sz + x) * 4;
                            if (data[idx + 3] > 128) {
                                lumSum += 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                                count++;
                            }
                        }
                    }

                    const isDark = count > 0 ? ((lumSum / count) < 75) : false;
                    tex.userData = tex.userData || {};
                    tex.userData._sfIsDarkTex = isDark;
                    return isDark;
                } catch (e) {
                    return false;
                }
            },

            detectDarkModelTexture(model) {
                let detected = false;
                if (model && typeof model.traverse === 'function') {
                    model.traverse((child) => {
                        if (detected || !child.isMesh || !child.material) return;
                        const firstMat = Array.isArray(child.material) ? child.material[0] : child.material;
                        const map = (child.userData && child.userData._origMap) || (firstMat ? firstMat.map : null);
                        if (map && this.isDarkTextureImage(map)) {
                            detected = true;
                        }
                    });
                }
                return detected;
            },

            resolveLightingProfile(opts = {}) {
                const map = (opts.presets && typeof opts.presets === 'object' && Object.keys(opts.presets).length > 0)
                    ? opts.presets
                    : this.DEFAULT_PRESETS;
                const isMobile = Boolean(opts.isMobile);
                const requested = String(opts.lightingPreset || 'auto').trim().toLowerCase();
                const matType = String(opts.materialType || 'glass').trim().toLowerCase();
                const pkgKey = String(opts.templatePackage || 'custom').trim().toLowerCase();
                const polarity = String(opts.xtoolPhotoSubstratePolarity || '').trim().toLowerCase();
                const modelPath = String(opts.modelPath || '').toLowerCase();

                let presetKey = 'crystal_studio';
                let isDarkMetal = false;

                if (requested !== 'auto' && map[requested]) {
                    presetKey = requested;
                    isDarkMetal = (presetKey === 'dark_metal_studio');
                } else {
                    const isBrightPrecious = [
                        'seelen-talisman',
                        'goldenes-seelen-herz',
                        'echtgold-seelen-herz',
                        'id-armband-silber-herz',
                        'id-armband-gold-herz'
                    ].includes(pkgKey);

                    const darkSignal = Boolean(opts.isDarkSurface)
                        || (typeof opts.surfaceLuminance === 'number' && !Number.isNaN(opts.surfaceLuminance) && opts.surfaceLuminance < 75)
                        || pkgKey.includes('seelen-anhaenger')
                        || modelPath.includes('seelenanhaenger')
                        || polarity === 'white_on_dark'
                        || this.detectDarkModelTexture(opts.model || null);

                    if (matType === 'dark_metal' || matType === 'black_aluminum' || (matType === 'metal' && !isBrightPrecious && darkSignal)) {
                        presetKey = 'dark_metal_studio';
                        isDarkMetal = true;
                    } else if (matType === 'metal' || matType === 'silver' || matType === 'gold') {
                        presetKey = 'precious_metal_studio';
                    } else if (matType === 'glass') {
                        presetKey = 'crystal_studio';
                    } else {
                        presetKey = 'matte_studio';
                    }
                }

                if (typeof opts.isDarkMetal === 'boolean') {
                    isDarkMetal = opts.isDarkMetal;
                    if (isDarkMetal && requested === 'auto') {
                        presetKey = 'dark_metal_studio';
                    }
                }

                const base = map[presetKey] || this.DEFAULT_PRESETS[presetKey] || this.DEFAULT_PRESETS.crystal_studio;
                const customExposure = this.clampNullable(opts.lightingExposure, 0.35, 1.80);
                const ambientMult = this.clampNullable(opts.lightingAmbient, 0.15, 2.00) ?? 1.0;
                const specMult = this.clampNullable(opts.lightingSpecular, 0.15, 2.00) ?? 1.0;

                const exposure = customExposure ?? Number(isMobile ? base.mobile_exposure : base.tone_mapping_exposure);
                const scaleLight = (l) => ({
                    color: l.color,
                    intensity: Math.round(Number(l.intensity) * specMult * 1000) / 1000,
                    pos: Array.isArray(l.pos) ? l.pos : [10, 10, 10]
                });

                return {
                    preset: presetKey,
                    isDarkMetal: isDarkMetal,
                    is_dark_metal: isDarkMetal,
                    tone_mapping_exposure: Math.round(exposure * 1000) / 1000,
                    ambient_intensity: Math.round(Number(isMobile ? base.mobile_ambient : base.ambient_intensity) * ambientMult * 1000) / 1000,
                    hemi_sky_color: base.hemi_sky_color,
                    hemi_ground_color: base.hemi_ground_color,
                    hemi_intensity: Math.round(Number(isMobile ? base.mobile_hemi : base.hemi_intensity) * ambientMult * 1000) / 1000,
                    env_intensity: Math.round(Number(base.env_intensity) * specMult * 1000) / 1000,
                    material_roughness: Number(base.material_roughness),
                    material_metalness: Number(base.material_metalness),
                    material_env_intensity: Math.round(Number(base.material_env_intensity) * specMult * 1000) / 1000,
                    key_light: scaleLight(base.key_light),
                    fill_light: scaleLight(base.fill_light),
                    rim_light: scaleLight(base.rim_light),
                    back_key_light: scaleLight(base.back_key_light),
                    back_fill_light: scaleLight(base.back_fill_light)
                };
            },

            applyStudioLighting(scene, THREE, opts = {}) {
                if (!scene || !THREE) return null;

                const profile = this.resolveLightingProfile(opts);
                if (opts.renderer) {
                    this.configureRenderer(opts.renderer, THREE, profile.tone_mapping_exposure);
                }
                if (typeof scene.environmentIntensity === 'number') {
                    scene.environmentIntensity = Number(profile.env_intensity ?? 1.0);
                }

                const toRemove = [];
                scene.traverse((obj) => {
                    if (obj && obj.userData && obj.userData._sfStudioLight) {
                        toRemove.push(obj);
                    }
                });
                toRemove.forEach((l) => {
                    if (l.parent) l.parent.remove(l);
                    if (typeof l.dispose === 'function') l.dispose();
                });

                const addLight = (light, pos) => {
                    if (Array.isArray(pos) && pos.length === 3) {
                        light.position.set(pos[0], pos[1], pos[2]);
                    }
                    light.userData = light.userData || {};
                    light.userData._sfStudioLight = true;
                    scene.add(light);
                    return light;
                };

                addLight(new THREE.AmbientLight(0xffffff, profile.ambient_intensity));

                if (typeof THREE.HemisphereLight !== 'undefined') {
                    addLight(new THREE.HemisphereLight(
                        profile.hemi_sky_color || '#ffffff',
                        profile.hemi_ground_color || '#0b0e14',
                        profile.hemi_intensity
                    ));
                }

                [profile.key_light, profile.fill_light, profile.rim_light, profile.back_key_light, profile.back_fill_light].forEach((cfg) => {
                    if (!cfg) return;
                    addLight(new THREE.DirectionalLight(cfg.color || '#ffffff', cfg.intensity), cfg.pos);
                });

                return profile;
            },

            applyEnvironmentTexture(scene, tex, THREE, renderer = null, stateHolder = null, applyToScene = true) {
                if (!tex || !THREE) return null;
                if (typeof THREE.SRGBColorSpace !== 'undefined') {
                    tex.colorSpace = THREE.SRGBColorSpace;
                }
                tex.mapping = THREE.EquirectangularReflectionMapping;

                if (!applyToScene || !scene) {
                    return null;
                }

                if (renderer && typeof THREE.PMREMGenerator !== 'undefined') {
                    try {
                        const pmrem = new THREE.PMREMGenerator(renderer);
                        pmrem.compileEquirectangularShader();
                        const rt = pmrem.fromEquirectangular(tex);
                        pmrem.dispose();

                        if (stateHolder && stateHolder._pmremTarget && stateHolder._pmremTarget !== rt && typeof stateHolder._pmremTarget.dispose === 'function') {
                            stateHolder._pmremTarget.dispose();
                        }
                        if (stateHolder) {
                            stateHolder._pmremTarget = rt;
                        }
                        scene.environment = rt.texture;
                        return rt.texture;
                    } catch (e) {
                        // Fallback auf direkte Equirectangular-Map
                    }
                }

                scene.environment = tex;
                return tex;
            },

            applyMaterialToModel(model, matType, opts = {}, THREE = window.THREE) {
                if (!model || !THREE) return { isDarkMetal: false };

                const isMobile = Boolean(opts.isMobile);
                const hasEnv = Boolean(opts.hasEnv);
                const type = String(matType || 'glass').toLowerCase();
                const profile = this.resolveLightingProfile(Object.assign({}, opts, { materialType: type, model }));
                const isDarkMetal = Boolean(profile.isDarkMetal);

                model.traverse((child) => {
                    if (!child.isMesh || !child.material) return;

                    const firstMat = Array.isArray(child.material) ? child.material[0] : child.material;
                    if (!child.userData._origMap && firstMat && firstMat.map) {
                        child.userData._origMap = firstMat.map;
                    }
                    if (!child.userData._origMatName && firstMat && firstMat.name) {
                        child.userData._origMatName = firstMat.name;
                    }

                    const oldMap = child.userData._origMap || (firstMat ? firstMat.map : null);
                    const origMatName = child.userData._origMatName || '';
                    const isOpticalCrystal = (origMatName === 'Optical_K9_Crystal_Glass')
                        || Boolean(opts.modelPath && String(opts.modelPath).includes('seelenk'));

                    if (type === 'glass') {
                        child.renderOrder = 1;
                        if (isOpticalCrystal && child.geometry && child.geometry.index) {
                            const idxCount = child.geometry.index.count;
                            child.geometry.clearGroups();
                            child.geometry.addGroup(0, idxCount, 0);
                            child.geometry.addGroup(0, idxCount, 1);

                            const matBack = isMobile
                                ? new THREE.MeshStandardMaterial({
                                    map: oldMap,
                                    color: 0xdff6fa,
                                    roughness: 0.08,
                                    metalness: 0.02,
                                    transparent: true,
                                    opacity: 0.38,
                                    depthWrite: false,
                                    side: THREE.BackSide
                                })
                                : new THREE.MeshPhysicalMaterial({
                                    map: oldMap,
                                    color: 0xe6f8fc,
                                    metalness: 0.02,
                                    roughness: 0.05,
                                    ior: 1.52,
                                    reflectivity: 0.9,
                                    envMapIntensity: hasEnv ? 0.35 : 0.0,
                                    transparent: true,
                                    opacity: 0.44,
                                    depthWrite: false,
                                    side: THREE.BackSide
                                });

                            const matFront = isMobile
                                ? new THREE.MeshStandardMaterial({
                                    map: oldMap,
                                    color: 0xffffff,
                                    roughness: 0.05,
                                    metalness: 0.05,
                                    transparent: true,
                                    opacity: 0.90,
                                    depthWrite: false,
                                    side: THREE.FrontSide
                                })
                                : new THREE.MeshPhysicalMaterial({
                                    map: oldMap,
                                    color: 0xffffff,
                                    metalness: 0.04,
                                    roughness: 0.03,
                                    ior: 1.52,
                                    reflectivity: 1.0,
                                    clearcoat: 1.0,
                                    clearcoatRoughness: 0.02,
                                    envMapIntensity: hasEnv ? 0.45 : 0.0,
                                    transparent: true,
                                    opacity: 0.96,
                                    depthWrite: false,
                                    side: THREE.FrontSide
                                });

                            child.material = [matBack, matFront];
                        } else if (isMobile) {
                            child.material = new THREE.MeshStandardMaterial({
                                map: oldMap,
                                color: 0xffffff,
                                roughness: 0.3,
                                metalness: 0.1,
                                transparent: true,
                                opacity: 0.5,
                                depthWrite: false,
                                side: THREE.FrontSide
                            });
                        } else {
                            child.material = new THREE.MeshPhysicalMaterial({
                                map: oldMap,
                                color: 0xffffff,
                                metalness: hasEnv ? 0.3 : 0.0,
                                roughness: hasEnv ? 0.05 : 0.2,
                                envMapIntensity: hasEnv ? Number(profile.material_env_intensity ?? 0.45) : 0.0,
                                transparent: true,
                                opacity: 0.80,
                                depthWrite: false,
                                side: THREE.FrontSide
                            });
                        }
                    } else if (type === 'wood') {
                        child.material = new THREE.MeshStandardMaterial({
                            map: oldMap,
                            color: 0xffffff,
                            roughness: 0.9,
                            metalness: 0.0,
                            envMapIntensity: hasEnv ? Number(profile.material_env_intensity ?? 0.15) : 0.0
                        });
                    } else if (type === 'metal' || type === 'dark_metal' || type === 'black_aluminum' || type === 'silver' || type === 'gold') {
                        if (isDarkMetal) {
                            child.material = new THREE.MeshStandardMaterial({
                                map: oldMap,
                                color: 0xffffff,
                                roughness: hasEnv ? Number(profile.material_roughness ?? 0.34) : 0.44,
                                metalness: hasEnv ? Number(profile.material_metalness ?? 0.72) : 0.18,
                                envMapIntensity: hasEnv ? Number(profile.material_env_intensity ?? 0.24) : 0.0,
                                alphaTest: oldMap ? 0.35 : 0.0,
                                side: THREE.DoubleSide
                            });
                        } else {
                            const isPreciousMetal = (type === 'silver' || type === 'gold');
                            child.material = new THREE.MeshStandardMaterial({
                                map: oldMap,
                                color: 0xffffff,
                                roughness: hasEnv ? (isPreciousMetal ? 0.20 : Number(profile.material_roughness ?? 0.24)) : 0.36,
                                metalness: hasEnv ? (isPreciousMetal ? 0.65 : Number(profile.material_metalness ?? 0.45)) : 0.0,
                                envMapIntensity: hasEnv ? Number(profile.material_env_intensity ?? 0.65) : 0.0,
                                alphaTest: oldMap ? 0.35 : 0.0,
                                side: THREE.DoubleSide
                            });
                        }
                    } else {
                        child.material = new THREE.MeshStandardMaterial({
                            map: oldMap,
                            color: 0xffffff,
                            roughness: 0.5,
                            metalness: 0.0,
                            envMapIntensity: hasEnv ? Number(profile.material_env_intensity ?? 0.35) : 0.0
                        });
                    }

                    if (Array.isArray(child.material)) {
                        child.material.forEach((m) => { m.needsUpdate = true; });
                    } else {
                        child.material.needsUpdate = true;
                    }
                });

                return { isDarkMetal };
            }
        };
    }
</script>
@endscript
