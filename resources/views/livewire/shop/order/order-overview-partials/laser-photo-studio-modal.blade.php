@once
    <script>
        window.sfCreateLaserPhotoStudio = function (initialConfig) {
            const cfg = initialConfig || {};

            return {
                studioView: 'result',
                studioZoom: 1,
                studioBg: cfg.defaultBg || 'light',
                originalUrl: cfg.originalUrl || '',
                fallbackPreviewUrl: cfg.fallbackPreviewUrl || '',
                defaultParams: cfg.defaultParams || null,
                profiles: Array.isArray(cfg.profiles) ? cfg.profiles : [],
                activeProfileId: String(cfg.activeProfileId || ''),
                studioDpi: Number(cfg.studioDpi) || 600,
                profileMenuOpen: false,
                showCreateProfileForm: false,
                newProfileTitle: '',
                isSavingProfileTemplate: false,
                profileSavedFlash: false,
                isCreatingProfile: false,
                canvasReady: false,
                isSavingStudio: false,
                rafId: 0,
                loadedUrl: '',
                sourceImg: null,
                naturalWidth: 0,
                naturalHeight: 0,
                srcWidth: 0,
                srcHeight: 0,
                renderDpr: 1,
                cssWidth: 0,
                cssHeight: 0,
                rawRgba: null,
                srcRgba: null,
                legacyMaskMeta: null,
                lastDecodedInvert: null,
                toneLut: new Float32Array(256),
                tonedBuf: null,
                blurTmp: null,
                blurOut: null,
                rBuf: null,
                gBuf: null,
                bBuf: null,
                ditherBuf: null,
                ditherVis: null,
                outImageData: null,
                params: {
                    enabled: true,
                    grayscale: true,
                    invert: false,
                    brightness: 0,
                    contrast: 12,
                    gamma: 1.15,
                    highlight_cap: 85,
                    sharpness: 85,
                    dither_mode: 'jarvis',
                    threshold: 128,
                },

                initStudio() {
                    this.params = this.sanitizeClientParams(cfg.params || {});
                    if (cfg.params && cfg.params.invert !== undefined) {
                        this.studioBg = this.params.invert ? 'dark' : 'light';
                    }
                    if (this.originalUrl) {
                        this.loadSourceImage(this.originalUrl);
                    }
                },

                sanitizeClientParams(raw) {
                    const p = raw || {};
                    let mode = String(p.dither_mode || 'jarvis').trim().toLowerCase();
                    if (mode === 'greyscale') mode = 'grayscale';
                    if (!['jarvis', 'stucki', 'grayscale', 'threshold'].includes(mode)) {
                        mode = 'jarvis';
                    }
                    const clampInt = (val, fallback, min, max) => {
                        const n = Number(val);
                        if (!Number.isFinite(n)) return fallback;
                        return Math.max(min, Math.min(max, Math.round(n)));
                    };
                    const rawGamma = Number(String(p.gamma ?? 1.15).replace(',', '.'));
                    const safeGamma = Number.isFinite(rawGamma)
                        ? Math.round(Math.max(0.35, Math.min(2.50, rawGamma)) * 100) / 100
                        : 1.15;

                    return {
                        enabled: true,
                        grayscale: p.grayscale !== undefined ? Boolean(p.grayscale) : true,
                        invert: p.invert !== undefined ? Boolean(p.invert) : false,
                        brightness: clampInt(p.brightness, 0, -100, 100),
                        contrast: clampInt(p.contrast, 12, -100, 100),
                        gamma: safeGamma,
                        highlight_cap: clampInt(p.highlight_cap, 85, 30, 100),
                        sharpness: clampInt(p.sharpness, 85, 0, 200),
                        dither_mode: mode,
                        threshold: clampInt(p.threshold, 128, 10, 245),
                    };
                },

                getStageBgClass() {
                    if (this.studioBg === 'dark') return 'bg-gray-950 border-gray-800';
                    if (this.studioBg === 'checker') return 'sf-studio-checker border-gray-300 dark:border-gray-700';
                    return 'bg-[#FAF8F5] border-gray-200 dark:border-gray-700';
                },

                setView(mode) {
                    if (this.studioView === mode) return;
                    this.studioView = mode;
                    if (mode !== 'original' && this.sourceImg) {
                        this.$nextTick(() => this.rebuildForDisplaySize());
                    }
                },

                setZoom(zoomLevel) {
                    const nextZoom = zoomLevel > 1 ? 2 : 1;
                    if (this.studioZoom === nextZoom) return;
                    this.studioZoom = nextZoom;
                    if (this.studioView !== 'original' && this.sourceImg) {
                        this.$nextTick(() => this.rebuildForDisplaySize());
                    }
                },

                onViewportResize() {
                    if (this.studioView !== 'original' && this.sourceImg) {
                        this.rebuildForDisplaySize();
                    }
                },

                calcPct(val, min, max) {
                    if (max <= min) return '50.00';
                    const num = Number(val);
                    const safe = Number.isFinite(num) ? Math.max(min, Math.min(max, num)) : min;
                    return (((safe - min) / (max - min)) * 100.0).toFixed(2);
                },

                formatSignedInt(val) {
                    const n = Math.round(Number(val) || 0);
                    return n > 0 ? ('+' + n) : String(n);
                },

                formatGamma(val) {
                    const g = Math.max(0.35, Math.min(2.50, Number(val) || 1.0));
                    return g.toFixed(2).replace('.', ',');
                },

                syncFromServer(detail) {
                    const payload = Array.isArray(detail) ? (detail[0] || {}) : (detail || {});
                    if (typeof payload.activeProfileId === 'string' && payload.activeProfileId !== '') {
                        this.activeProfileId = payload.activeProfileId;
                    }
                    if (payload.dpi !== undefined && Number(payload.dpi) > 0) {
                        this.studioDpi = Math.round(Number(payload.dpi));
                    }
                    if (payload.params && typeof payload.params === 'object') {
                        this.params = this.sanitizeClientParams(payload.params);
                        if (this.studioBg !== 'checker') {
                            this.studioBg = this.params.invert ? 'dark' : 'light';
                        }
                    }
                    if (typeof payload.originalUrl === 'string' && payload.originalUrl !== '' && payload.originalUrl !== this.loadedUrl) {
                        this.originalUrl = payload.originalUrl;
                        this.loadSourceImage(payload.originalUrl);
                        return;
                    }
                    if (this.rawRgba && this.legacyMaskMeta && this.legacyMaskMeta.isLegacyMask) {
                        this.prepareDecodedSourcePixels(Boolean(this.params.invert));
                    }
                    this.scheduleRender();
                },

                syncProfilesFromServer(detail) {
                    const payload = Array.isArray(detail) ? (detail[0] || {}) : (detail || {});
                    if (Array.isArray(payload.profiles)) {
                        this.profiles = payload.profiles;
                    }
                    if (typeof payload.activeProfileId === 'string' && payload.activeProfileId !== '') {
                        this.activeProfileId = payload.activeProfileId;
                    }
                },

                getActiveProfile() {
                    if (this.profiles.length === 0) {
                        return {
                            id: '',
                            title: 'Standard',
                            is_default: true,
                            dpi: this.studioDpi || 600,
                            bitmap_mode: 'Jarvis',
                            photo_studio: null,
                        };
                    }
                    let found = this.profiles.find((p) => String(p.id || '') === String(this.activeProfileId || ''));
                    if (!found) {
                        found = this.profiles.find((p) => Boolean(p.is_default)) || this.profiles[0];
                    }
                    return found;
                },

                mapBitmapToDitherMode(bitmapMode) {
                    const b = String(bitmapMode || '').trim().toLowerCase();
                    if (b.includes('stucki')) return 'stucki';
                    if (b.includes('schwellenwert') || b.includes('threshold')) return 'threshold';
                    if (b.includes('graustufen') || b.includes('greyscale') || b.includes('grayscale')) return 'grayscale';
                    return 'jarvis';
                },

                selectStudioProfile(prof) {
                    if (!prof) return;
                    this.profileMenuOpen = false;
                    const nextId = String(prof.id || '');
                    this.activeProfileId = nextId;
                    if (Number(prof.dpi) > 0) {
                        this.studioDpi = Math.round(Number(prof.dpi));
                    }

                    if (prof.photo_studio && typeof prof.photo_studio === 'object') {
                        this.params = this.sanitizeClientParams(prof.photo_studio);
                    } else {
                        const fallbackBase = Object.assign({}, this.defaultParams || {}, {
                            dither_mode: this.mapBitmapToDitherMode(prof.bitmap_mode),
                        });
                        this.params = this.sanitizeClientParams(fallbackBase);
                    }

                    if (this.studioBg !== 'checker') {
                        this.studioBg = this.params.invert ? 'dark' : 'light';
                    }
                    if (this.rawRgba && this.legacyMaskMeta && this.legacyMaskMeta.isLegacyMask) {
                        this.prepareDecodedSourcePixels(Boolean(this.params.invert));
                    }
                    this.scheduleRender();

                    if (nextId !== '') {
                        this.$wire.selectPhotoStudioLaserProfile(nextId);
                    }
                },

                saveToCurrentLaserProfile() {
                    if (this.isSavingProfileTemplate) return;
                    const active = this.getActiveProfile();
                    const profId = active ? String(active.id || '') : '';
                    const clean = this.sanitizeClientParams(this.params);
                    this.isSavingProfileTemplate = true;

                    const idx = this.profiles.findIndex((p) => String(p.id || '') === profId);
                    if (idx >= 0) {
                        this.profiles[idx].photo_studio = Object.assign({}, clean);
                    }

                    Promise.resolve(this.$wire.savePhotoStudioToActiveLaserProfile(profId, clean))
                        .then(() => {
                            this.profileSavedFlash = true;
                            setTimeout(() => { this.profileSavedFlash = false; }, 2400);
                        })
                        .finally(() => {
                            this.isSavingProfileTemplate = false;
                        });
                },

                submitCreateLaserProfile() {
                    const cleanTitle = String(this.newProfileTitle || '').trim();
                    if (cleanTitle.length < 2 || this.isCreatingProfile) return;
                    const active = this.getActiveProfile();
                    const baseId = active && active.id ? String(active.id) : null;
                    const clean = this.sanitizeClientParams(this.params);
                    this.isCreatingProfile = true;

                    Promise.resolve(this.$wire.createLaserProfileFromPhotoStudio(cleanTitle, baseId, clean))
                        .then(() => {
                            this.newProfileTitle = '';
                            this.showCreateProfileForm = false;
                            this.profileSavedFlash = true;
                            setTimeout(() => { this.profileSavedFlash = false; }, 2400);
                        })
                        .finally(() => {
                            this.isCreatingProfile = false;
                        });
                },

                onInvertChanged() {
                    this.studioBg = this.params.invert ? 'dark' : 'light';
                    if (this.rawRgba && this.legacyMaskMeta && this.legacyMaskMeta.isLegacyMask) {
                        this.prepareDecodedSourcePixels(Boolean(this.params.invert));
                    }
                    this.scheduleRender();
                },

                resetLocalParams() {
                    const base = this.defaultParams || {
                        enabled: false,
                        grayscale: true,
                        invert: cfg.defaultBg === 'dark',
                        brightness: 0,
                        contrast: 15,
                        gamma: 1.0,
                        highlight_cap: cfg.defaultBg === 'dark' ? 58 : 75,
                        sharpness: 85,
                        dither_mode: 'jarvis',
                        threshold: 128,
                    };
                    this.params = this.sanitizeClientParams(base);
                    if (this.studioBg !== 'checker') {
                        this.studioBg = this.params.invert ? 'dark' : 'light';
                    }
                    if (this.rawRgba && this.legacyMaskMeta && this.legacyMaskMeta.isLegacyMask) {
                        this.prepareDecodedSourcePixels(Boolean(this.params.invert));
                    }
                    this.scheduleRender();
                },

                submitStudioSave() {
                    if (this.isSavingStudio) return;
                    this.isSavingStudio = true;
                    const clean = this.sanitizeClientParams(this.params);
                    Promise.resolve(this.$wire.saveLaserPhotoStudio(clean)).finally(() => {
                        this.isSavingStudio = false;
                    });
                },

                loadSourceImage(url) {
                    if (!url) return;
                    this.loadedUrl = url;
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = () => {
                        if (this.loadedUrl !== url) return;
                        this.sourceImg = img;
                        this.naturalWidth = img.naturalWidth || img.width || 1;
                        this.naturalHeight = img.naturalHeight || img.height || 1;
                        this.lastDecodedInvert = null;
                        this.rebuildForDisplaySize(true);
                    };
                    img.onerror = () => {
                        this.canvasReady = false;
                    };
                    img.src = url;
                },

                computeTargetDisplayMetrics() {
                    const origW = this.naturalWidth || 1;
                    const origH = this.naturalHeight || 1;
                    const dpr = Math.min(2, Math.max(1, Number(window.devicePixelRatio) || 1));
                    const isSplit = (this.studioView === 'split');
                    const zoom = this.studioZoom > 1 ? 2 : 1;
                    const targetMaxH = zoom > 1 ? 760 : (isSplit ? 330 : 390);

                    let baseMaxW = isSplit ? 300 : 560;
                    if (isSplit) {
                        const slot = this.$refs.splitResultSlot;
                        if (slot && slot.clientWidth > 40) {
                            baseMaxW = Math.max(40, slot.clientWidth - 4);
                        } else if (this.$refs.stageBox && this.$refs.stageBox.clientWidth > 100) {
                            baseMaxW = Math.max(40, Math.floor((this.$refs.stageBox.clientWidth - 48) / 2));
                        }
                    } else if (this.$refs.stageBox && this.$refs.stageBox.clientWidth > 60) {
                        baseMaxW = Math.max(60, this.$refs.stageBox.clientWidth - 36);
                    }

                    const targetMaxW = zoom > 1 ? (baseMaxW * zoom) : baseMaxW;
                    const maxScale = zoom > 1 ? 2.0 : 1.0;
                    const scaleCss = Math.min(maxScale, targetMaxW / origW, targetMaxH / origH);

                    const maxPhysW = Math.max(1, Math.floor(targetMaxW * dpr + 1e-6));
                    const maxPhysH = Math.max(1, Math.floor(targetMaxH * dpr + 1e-6));
                    const w = Math.min(maxPhysW, Math.max(1, Math.round(origW * scaleCss * dpr)));
                    const h = Math.min(maxPhysH, Math.max(1, Math.round(origH * scaleCss * dpr)));

                    return {
                        w: w,
                        h: h,
                        dpr: dpr,
                        cssW: w / dpr,
                        cssH: h / dpr,
                    };
                },

                rasterizeSourceAntiAliased(img, origW, origH, targetW, targetH) {
                    let curSource = img;
                    let curW = origW;
                    let curH = origH;

                    while (curW * 0.5 > targetW && curH * 0.5 > targetH) {
                        const nextW = Math.max(targetW, Math.round(curW * 0.5));
                        const nextH = Math.max(targetH, Math.round(curH * 0.5));
                        const stepCanvas = document.createElement('canvas');
                        stepCanvas.width = nextW;
                        stepCanvas.height = nextH;
                        const stepCtx = stepCanvas.getContext('2d');
                        if (!stepCtx) break;
                        stepCtx.imageSmoothingEnabled = true;
                        stepCtx.imageSmoothingQuality = 'high';
                        stepCtx.clearRect(0, 0, nextW, nextH);
                        stepCtx.drawImage(curSource, 0, 0, nextW, nextH);
                        curSource = stepCanvas;
                        curW = nextW;
                        curH = nextH;
                    }

                    const off = document.createElement('canvas');
                    off.width = targetW;
                    off.height = targetH;
                    const ctx = off.getContext('2d', { willReadFrequently: true });
                    if (!ctx) return null;
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.clearRect(0, 0, targetW, targetH);
                    ctx.drawImage(curSource, 0, 0, targetW, targetH);
                    return ctx.getImageData(0, 0, targetW, targetH);
                },

                rebuildForDisplaySize(force = false) {
                    if (!this.sourceImg) return;
                    const m = this.computeTargetDisplayMetrics();
                    this.renderDpr = m.dpr;
                    this.cssWidth = m.cssW;
                    this.cssHeight = m.cssH;

                    if (!force && this.canvasReady && this.rawRgba && this.srcWidth === m.w && this.srcHeight === m.h) {
                        this.flushToCanvases();
                        return;
                    }

                    const imgData = this.rasterizeSourceAntiAliased(this.sourceImg, this.naturalWidth, this.naturalHeight, m.w, m.h);
                    if (!imgData) {
                        this.canvasReady = false;
                        return;
                    }

                    this.srcWidth = m.w;
                    this.srcHeight = m.h;
                    this.rawRgba = new Uint8ClampedArray(imgData.data);
                    this.srcRgba = null;
                    this.lastDecodedInvert = null;
                    this.legacyMaskMeta = this.analyzeLegacyAlphaMask(this.rawRgba, m.w, m.h);
                    this.prepareWorkingBuffers(m.w, m.h);
                    this.prepareDecodedSourcePixels(Boolean(this.params.invert));
                    this.canvasReady = true;
                    this.renderNow();
                },

                prepareWorkingBuffers(w, h) {
                    const pxCount = w * h;
                    const stride = w + 4;
                    const paddedLen = (h + 2) * stride;
                    this.tonedBuf = new Float32Array(pxCount);
                    this.blurTmp = new Float32Array(pxCount);
                    this.blurOut = new Float32Array(pxCount);
                    this.ditherBuf = new Float32Array(paddedLen);
                    this.ditherVis = new Uint8Array(paddedLen);
                    this.outImageData = new ImageData(w, h);
                },

                analyzeLegacyAlphaMask(rgba, w, h) {
                    let visible = 0;
                    let fullyOpaqueCount = 0;
                    let strongAlphaCount = 0;
                    let minAlphaSeen = 127;
                    const step = (w > 512 || h > 512) ? 2 : 1;

                    for (let y = 0; y < h; y += step) {
                        const row = y * w;
                        for (let x = 0; x < w; x += step) {
                            const off = (row + x) * 4;
                            const a255 = rgba[off + 3];
                            const gdA = 127 - Math.round((a255 / 255.0) * 127.0);
                            if (gdA >= 126) continue;

                            if (rgba[off] > 10 || rgba[off + 1] > 10 || rgba[off + 2] > 10) {
                                return { isLegacyMask: false };
                            }
                            visible++;
                            if (gdA <= 6) fullyOpaqueCount++;
                            if (gdA < 16) strongAlphaCount++;
                            if (gdA < minAlphaSeen) minAlphaSeen = gdA;
                        }
                    }

                    if (visible <= 0 || (fullyOpaqueCount / visible) > 0.88) {
                        return { isLegacyMask: false };
                    }

                    return {
                        isLegacyMask: true,
                        visible: visible,
                        strongAlphaCount: strongAlphaCount,
                        minAlphaSeen: minAlphaSeen,
                    };
                },

                prepareDecodedSourcePixels(isDarkSurface) {
                    if (!this.rawRgba) return;
                    if (!this.legacyMaskMeta || !this.legacyMaskMeta.isLegacyMask) {
                        this.srcRgba = this.rawRgba;
                        return;
                    }
                    if (this.srcRgba && this.srcRgba !== this.rawRgba && this.lastDecodedInvert === isDarkSurface) {
                        return;
                    }

                    const meta = this.legacyMaskMeta;
                    const isMaskDark = isDarkSurface && ((meta.strongAlphaCount / meta.visible) < 0.05) && meta.minAlphaSeen >= 14;
                    const isHalfCompressedLight = !isMaskDark && meta.minAlphaSeen >= 58;

                    const alphaLut = new Uint8Array(128);
                    for (let a = 0; a <= 127; a++) {
                        let lumNorm;
                        if (isMaskDark) {
                            const base = (123.0 - a) / 105.0;
                            lumNorm = base <= 0.0 ? 0.0 : Math.pow(Math.min(1.0, base), 1.0 / 0.85);
                        } else if (isHalfCompressedLight) {
                            const engraveNorm = Math.min(1.0, Math.max(0.0, (127.0 - a) / 63.75));
                            lumNorm = 1.0 - engraveNorm;
                        } else {
                            const engrave255 = Math.min(255.0, Math.max(0.0, (127.0 - a) * 2.0));
                            lumNorm = (255.0 - engrave255) / 255.0;
                        }
                        alphaLut[a] = Math.max(0, Math.min(255, Math.round(lumNorm * 255.0)));
                    }

                    const raw = this.rawRgba;
                    const decoded = (this.srcRgba && this.srcRgba !== raw)
                        ? this.srcRgba
                        : new Uint8ClampedArray(raw.length);

                    for (let i = 0, len = raw.length; i < len; i += 4) {
                        const a255 = raw[i + 3];
                        const gdA = 127 - Math.round((a255 / 255.0) * 127.0);
                        if (gdA >= 126) {
                            decoded[i] = 0;
                            decoded[i + 1] = 0;
                            decoded[i + 2] = 0;
                            decoded[i + 3] = 0;
                        } else {
                            const g8 = alphaLut[gdA];
                            decoded[i] = g8;
                            decoded[i + 1] = g8;
                            decoded[i + 2] = g8;
                            decoded[i + 3] = 255;
                        }
                    }

                    this.srcRgba = decoded;
                    this.lastDecodedInvert = isDarkSurface;
                },

                separableBlurPass(src, tmp, dst, w, h) {
                    if (w < 3 || h < 3) {
                        dst.set(src);
                        return;
                    }
                    const inv3 = 1.0 / 3.0;
                    const lastX = w - 1;
                    for (let y = 0; y < h; y++) {
                        const row = y * w;
                        tmp[row] = (src[row] * 2.0 + src[row + 1]) * inv3;
                        for (let x = 1; x < lastX; x++) {
                            const idx = row + x;
                            tmp[idx] = (src[idx - 1] + src[idx] + src[idx + 1]) * inv3;
                        }
                        tmp[row + lastX] = (src[row + lastX - 1] + src[row + lastX] * 2.0) * inv3;
                    }

                    for (let x = 0; x < w; x++) {
                        dst[x] = (tmp[x] * 2.0 + tmp[w + x]) * inv3;
                    }
                    const lastY = h - 1;
                    for (let y = 1; y < lastY; y++) {
                        const row = y * w;
                        for (let x = 0; x < w; x++) {
                            const idx = row + x;
                            dst[idx] = (tmp[idx - w] + tmp[idx] + tmp[idx + w]) * inv3;
                        }
                    }
                    const botRow = lastY * w;
                    for (let x = 0; x < w; x++) {
                        const idx = botRow + x;
                        dst[idx] = (tmp[idx - w] + tmp[idx] * 2.0) * inv3;
                    }
                },

                threePassGaussianApprox(src, tmp, dst, w, h) {
                    this.separableBlurPass(src, tmp, dst, w, h);
                    this.separableBlurPass(dst, tmp, dst, w, h);
                    this.separableBlurPass(dst, tmp, dst, w, h);
                },

                scheduleRender() {
                    if (!this.canvasReady || !this.srcRgba) return;
                    if (this.rafId) return;
                    this.rafId = window.requestAnimationFrame(() => {
                        this.rafId = 0;
                        this.renderNow();
                    });
                },

                renderNow() {
                    if (!this.canvasReady || !this.srcRgba || !this.outImageData) return;

                    const p = this.sanitizeClientParams(this.params);
                    const w = this.srcWidth;
                    const h = this.srcHeight;
                    const pxCount = w * h;
                    const src = this.srcRgba;
                    const out = this.outImageData.data;

                    // 1. 256er Soft-Shoulder Contrast + Brightness + Gamma LUT (1:1 LaserPhotoPreprocessor)
                    const bShift = p.brightness / 200.0;
                    const cFactor = Math.tan(((45.0 + 40.0 * (p.contrast / 100.0)) * Math.PI) / 180.0);
                    const gamma = p.gamma;
                    const lut = this.toneLut;

                    for (let v = 0; v < 256; v++) {
                        const norm = (v / 255.0) + bShift;
                        const contrasted = 0.5 + (norm - 0.5) * cFactor;
                        let rolled;
                        if (contrasted < 0.08) {
                            rolled = 0.08 * (1.0 - Math.tanh((0.08 - contrasted) / 0.08));
                        } else if (contrasted > 0.88) {
                            rolled = 0.88 + 0.12 * Math.tanh((contrasted - 0.88) / 0.12);
                        } else {
                            rolled = contrasted;
                        }
                        const clamped = rolled < 0.0 ? 0.0 : (rolled > 1.0 ? 1.0 : rolled);
                        lut[v] = Math.pow(clamped, gamma);
                    }

                    const useGrayscale = p.grayscale;
                    const ditherMode = p.dither_mode;
                    const invert = p.invert;
                    const cap = p.highlight_cap / 100.0;
                    const sharpAmount = (p.sharpness / 100.0) * 1.25;
                    const threshold = p.threshold;

                    // Echte WYSIWYG-Rohlings-Farben fuer die Live-Vorschau:
                    // invert === true (Dunkler Rohling): Laser feuert (0) -> Silber-Weiss (242,245,250), Laser pausiert (255) -> Schwarz-Anthrazit (18,21,28)
                    // invert === false (Heller Rohling): Laser feuert (0) -> Dunkle Gravur (24,24,28), Laser pausiert (255) -> Heller Rohling (250,248,245)
                    const markR = invert ? 242 : 24;
                    const markG = invert ? 245 : 24;
                    const markB = invert ? 250 : 28;
                    const baseR = invert ? 18 : 250;
                    const baseG = invert ? 21 : 248;
                    const baseB = invert ? 28 : 245;
                    const spanR = markR - baseR;
                    const spanG = markG - baseG;
                    const spanB = markB - baseB;

                    // Sonderpfad: Farbige Vorschau bei !grayscale && dither_mode === 'grayscale'
                    if (!useGrayscale && ditherMode === 'grayscale') {
                        if (!this.rBuf || this.rBuf.length !== pxCount) {
                            this.rBuf = new Float32Array(pxCount);
                            this.gBuf = new Float32Array(pxCount);
                            this.bBuf = new Float32Array(pxCount);
                        }
                        const rB = this.rBuf;
                        const gB = this.gBuf;
                        const bB = this.bBuf;

                        for (let i = 0; i < pxCount; i++) {
                            const off = i * 4;
                            if (src[off + 3] <= 15) {
                                rB[i] = 0.5;
                                gB[i] = 0.5;
                                bB[i] = 0.5;
                                continue;
                            }
                            rB[i] = lut[src[off]];
                            gB[i] = lut[src[off + 1]];
                            bB[i] = lut[src[off + 2]];
                        }

                        if (sharpAmount > 0.001 && w >= 3 && h >= 3) {
                            this.threePassGaussianApprox(rB, this.blurTmp, this.blurOut, w, h);
                            for (let i = 0; i < pxCount; i++) {
                                const v = rB[i] + sharpAmount * (rB[i] - this.blurOut[i]);
                                rB[i] = v < 0.0 ? 0.0 : (v > 1.0 ? 1.0 : v);
                            }
                            this.threePassGaussianApprox(gB, this.blurTmp, this.blurOut, w, h);
                            for (let i = 0; i < pxCount; i++) {
                                const v = gB[i] + sharpAmount * (gB[i] - this.blurOut[i]);
                                gB[i] = v < 0.0 ? 0.0 : (v > 1.0 ? 1.0 : v);
                            }
                            this.threePassGaussianApprox(bB, this.blurTmp, this.blurOut, w, h);
                            for (let i = 0; i < pxCount; i++) {
                                const v = bB[i] + sharpAmount * (bB[i] - this.blurOut[i]);
                                bB[i] = v < 0.0 ? 0.0 : (v > 1.0 ? 1.0 : v);
                            }
                        }

                        for (let i = 0; i < pxCount; i++) {
                            const off = i * 4;
                            const a = src[off + 3];
                            if (a <= 15) {
                                out[off] = 0;
                                out[off + 1] = 0;
                                out[off + 2] = 0;
                                out[off + 3] = 0;
                                continue;
                            }
                            const dR = Math.max(0.0, Math.min(cap, (invert ? rB[i] : (1.0 - rB[i])) * cap));
                            const dG = Math.max(0.0, Math.min(cap, (invert ? gB[i] : (1.0 - gB[i])) * cap));
                            const dB = Math.max(0.0, Math.min(cap, (invert ? bB[i] : (1.0 - bB[i])) * cap));
                            out[off] = Math.max(0, Math.min(255, Math.round(baseR + spanR * dR)));
                            out[off + 1] = Math.max(0, Math.min(255, Math.round(baseG + spanG * dG)));
                            out[off + 2] = Math.max(0, Math.min(255, Math.round(baseB + spanB * dB)));
                            out[off + 3] = a;
                        }

                        this.flushToCanvases();
                        return;
                    }

                    // 2. BT.709 Luminanz (bzw. Kanalmittel) + Tonwert-LUT
                    const toned = this.tonedBuf;
                    let visSum = 0.0;
                    let visCnt = 0;

                    for (let i = 0; i < pxCount; i++) {
                        const off = i * 4;
                        if (src[off + 3] <= 15) {
                            toned[i] = -1.0;
                            continue;
                        }
                        const r = src[off];
                        const g = src[off + 1];
                        const b = src[off + 2];
                        const l8 = useGrayscale
                            ? Math.round(0.2126 * r + 0.7152 * g + 0.0722 * b)
                            : Math.round((r + g + b) / 3.0);
                        const tVal = lut[l8 < 0 ? 0 : (l8 > 255 ? 255 : l8)];
                        toned[i] = tVal;
                        visSum += tVal;
                        visCnt++;
                    }

                    const fillMean = visCnt > 0 ? (visSum / visCnt) : 0.5;
                    if (visCnt < pxCount) {
                        for (let i = 0; i < pxCount; i++) {
                            if (toned[i] < 0.0) toned[i] = fillMean;
                        }
                    }

                    // 3. 3 separable Blur-Paesse (Radius ~2 px Gauss-Annaeherung) fuer streifenfreies Unsharp-Mask
                    const hasSharp = sharpAmount > 0.001 && visCnt > 0 && w >= 3 && h >= 3;
                    const blur = this.blurOut;
                    if (hasSharp) {
                        this.threePassGaussianApprox(toned, this.blurTmp, blur, w, h);
                    }

                    // 4. Invert + Highlight-Cap + WYSIWYG-Rohlings-Ausgabe / Dithering-Vorbereitung
                    if (ditherMode === 'threshold') {
                        for (let i = 0; i < pxCount; i++) {
                            const off = i * 4;
                            const a = src[off + 3];
                            if (a <= 15) {
                                out[off] = 0;
                                out[off + 1] = 0;
                                out[off + 2] = 0;
                                out[off + 3] = 0;
                                continue;
                            }
                            let base = toned[i];
                            if (hasSharp) {
                                base = base + sharpAmount * (base - blur[i]);
                                if (base < 0.0) base = 0.0;
                                else if (base > 1.0) base = 1.0;
                            }
                            const rawDensity = invert ? base : (1.0 - base);
                            const unclampedGray = Math.round(255.0 * (1.0 - rawDensity));
                            const newVal = unclampedGray < threshold ? 0.0 : 255.0;
                            const laserFires = (newVal === 0.0);
                            out[off] = laserFires ? markR : baseR;
                            out[off + 1] = laserFires ? markG : baseG;
                            out[off + 2] = laserFires ? markB : baseB;
                            out[off + 3] = a;
                        }
                        this.flushToCanvases();
                        return;
                    }

                    if (ditherMode === 'grayscale') {
                        for (let i = 0; i < pxCount; i++) {
                            const off = i * 4;
                            const a = src[off + 3];
                            if (a <= 15) {
                                out[off] = 0;
                                out[off + 1] = 0;
                                out[off + 2] = 0;
                                out[off + 3] = 0;
                                continue;
                            }
                            let base = toned[i];
                            if (hasSharp) {
                                base = base + sharpAmount * (base - blur[i]);
                                if (base < 0.0) base = 0.0;
                                else if (base > 1.0) base = 1.0;
                            }
                            const rawDensity = invert ? base : (1.0 - base);
                            const cappedDensity = Math.max(0.0, Math.min(cap, rawDensity * cap));
                            out[off] = Math.max(0, Math.min(255, Math.round(baseR + spanR * cappedDensity)));
                            out[off + 1] = Math.max(0, Math.min(255, Math.round(baseG + spanG * cappedDensity)));
                            out[off + 2] = Math.max(0, Math.min(255, Math.round(baseB + spanB * cappedDensity)));
                            out[off + 3] = a;
                        }
                        this.flushToCanvases();
                        return;
                    }

                    // 5. Serpentine 12-Nachbarn Error-Diffusion (Boustrophedon Jarvis 48 / Stucki 42 + IGN Mikro-Jitter)
                    const stride = w + 4;
                    const buf = this.ditherBuf;
                    const vis = this.ditherVis;
                    buf.fill(0.0);
                    vis.fill(0);

                    for (let y = 0; y < h; y++) {
                        const rowSrc = y * w;
                        const rowDst = (y * stride) + 2;
                        for (let x = 0; x < w; x++) {
                            const idx = rowSrc + x;
                            const off = idx * 4;
                            const a = src[off + 3];
                            if (a <= 15) {
                                out[off] = 0;
                                out[off + 1] = 0;
                                out[off + 2] = 0;
                                out[off + 3] = 0;
                                continue;
                            }
                            let base = toned[idx];
                            if (hasSharp) {
                                base = base + sharpAmount * (base - blur[idx]);
                                if (base < 0.0) base = 0.0;
                                else if (base > 1.0) base = 1.0;
                            }
                            const rawDensity = invert ? base : (1.0 - base);
                            const cappedDensity = Math.max(0.0, Math.min(cap, rawDensity * cap));
                            const pIdx = rowDst + x;
                            vis[pIdx] = 1;
                            buf[pIdx] = 255.0 * (1.0 - cappedDensity);
                        }
                    }

                    const thrFloat = Math.max(1, Math.min(254, threshold));
                    const isStucki = (ditherMode === 'stucki');
                    const invDiv = isStucki ? (1.0 / 42.0) : (1.0 / 48.0);

                    const s1m2 = stride - 2;
                    const s1m1 = stride - 1;
                    const s1p0 = stride;
                    const s1p1 = stride + 1;
                    const s1p2 = stride + 2;
                    const s2m2 = (stride * 2) - 2;
                    const s2m1 = (stride * 2) - 1;
                    const s2p0 = stride * 2;
                    const s2p1 = (stride * 2) + 1;
                    const s2p2 = (stride * 2) + 2;

                    if (isStucki) {
                        for (let y = 0; y < h; y++) {
                            const rowSrc = y * w;
                            const rowBase = (y * stride) + 2;
                            if ((y & 1) === 0) {
                                for (let x = 0; x < w; x++) {
                                    const pIdx = rowBase + x;
                                    if (!vis[pIdx]) continue;
                                    const oldVal = buf[pIdx];
                                    const jitter = ((((x * 5281) + (y * 11035)) & 31) - 15.5) * 0.15;
                                    const newVal = (oldVal + jitter) < thrFloat ? 0.0 : 255.0;
                                    const laserFires = (newVal === 0.0);
                                    const off = (rowSrc + x) * 4;
                                    out[off] = laserFires ? markR : baseR;
                                    out[off + 1] = laserFires ? markG : baseG;
                                    out[off + 2] = laserFires ? markB : baseB;
                                    out[off + 3] = src[off + 3];

                                    const err = (oldVal - newVal) * invDiv;
                                    if (err === 0.0) continue;
                                    if (vis[pIdx + 1]) buf[pIdx + 1] += err * 8.0;
                                    if (vis[pIdx + 2]) buf[pIdx + 2] += err * 4.0;
                                    if (vis[pIdx + s1m2]) buf[pIdx + s1m2] += err * 2.0;
                                    if (vis[pIdx + s1m1]) buf[pIdx + s1m1] += err * 4.0;
                                    if (vis[pIdx + s1p0]) buf[pIdx + s1p0] += err * 8.0;
                                    if (vis[pIdx + s1p1]) buf[pIdx + s1p1] += err * 4.0;
                                    if (vis[pIdx + s1p2]) buf[pIdx + s1p2] += err * 2.0;
                                    if (vis[pIdx + s2m2]) buf[pIdx + s2m2] += err * 1.0;
                                    if (vis[pIdx + s2m1]) buf[pIdx + s2m1] += err * 2.0;
                                    if (vis[pIdx + s2p0]) buf[pIdx + s2p0] += err * 4.0;
                                    if (vis[pIdx + s2p1]) buf[pIdx + s2p1] += err * 2.0;
                                    if (vis[pIdx + s2p2]) buf[pIdx + s2p2] += err * 1.0;
                                }
                            } else {
                                for (let x = w - 1; x >= 0; x--) {
                                    const pIdx = rowBase + x;
                                    if (!vis[pIdx]) continue;
                                    const oldVal = buf[pIdx];
                                    const jitter = ((((x * 5281) + (y * 11035)) & 31) - 15.5) * 0.15;
                                    const newVal = (oldVal + jitter) < thrFloat ? 0.0 : 255.0;
                                    const laserFires = (newVal === 0.0);
                                    const off = (rowSrc + x) * 4;
                                    out[off] = laserFires ? markR : baseR;
                                    out[off + 1] = laserFires ? markG : baseG;
                                    out[off + 2] = laserFires ? markB : baseB;
                                    out[off + 3] = src[off + 3];

                                    const err = (oldVal - newVal) * invDiv;
                                    if (err === 0.0) continue;
                                    if (vis[pIdx - 1]) buf[pIdx - 1] += err * 8.0;
                                    if (vis[pIdx - 2]) buf[pIdx - 2] += err * 4.0;
                                    if (vis[pIdx + s1p2]) buf[pIdx + s1p2] += err * 2.0;
                                    if (vis[pIdx + s1p1]) buf[pIdx + s1p1] += err * 4.0;
                                    if (vis[pIdx + s1p0]) buf[pIdx + s1p0] += err * 8.0;
                                    if (vis[pIdx + s1m1]) buf[pIdx + s1m1] += err * 4.0;
                                    if (vis[pIdx + s1m2]) buf[pIdx + s1m2] += err * 2.0;
                                    if (vis[pIdx + s2p2]) buf[pIdx + s2p2] += err * 1.0;
                                    if (vis[pIdx + s2p1]) buf[pIdx + s2p1] += err * 2.0;
                                    if (vis[pIdx + s2p0]) buf[pIdx + s2p0] += err * 4.0;
                                    if (vis[pIdx + s2m1]) buf[pIdx + s2m1] += err * 2.0;
                                    if (vis[pIdx + s2m2]) buf[pIdx + s2m2] += err * 1.0;
                                }
                            }
                        }
                    } else {
                        for (let y = 0; y < h; y++) {
                            const rowSrc = y * w;
                            const rowBase = (y * stride) + 2;
                            if ((y & 1) === 0) {
                                for (let x = 0; x < w; x++) {
                                    const pIdx = rowBase + x;
                                    if (!vis[pIdx]) continue;
                                    const oldVal = buf[pIdx];
                                    const jitter = ((((x * 5281) + (y * 11035)) & 31) - 15.5) * 0.15;
                                    const newVal = (oldVal + jitter) < thrFloat ? 0.0 : 255.0;
                                    const laserFires = (newVal === 0.0);
                                    const off = (rowSrc + x) * 4;
                                    out[off] = laserFires ? markR : baseR;
                                    out[off + 1] = laserFires ? markG : baseG;
                                    out[off + 2] = laserFires ? markB : baseB;
                                    out[off + 3] = src[off + 3];

                                    const err = (oldVal - newVal) * invDiv;
                                    if (err === 0.0) continue;
                                    if (vis[pIdx + 1]) buf[pIdx + 1] += err * 7.0;
                                    if (vis[pIdx + 2]) buf[pIdx + 2] += err * 5.0;
                                    if (vis[pIdx + s1m2]) buf[pIdx + s1m2] += err * 3.0;
                                    if (vis[pIdx + s1m1]) buf[pIdx + s1m1] += err * 5.0;
                                    if (vis[pIdx + s1p0]) buf[pIdx + s1p0] += err * 7.0;
                                    if (vis[pIdx + s1p1]) buf[pIdx + s1p1] += err * 5.0;
                                    if (vis[pIdx + s1p2]) buf[pIdx + s1p2] += err * 3.0;
                                    if (vis[pIdx + s2m2]) buf[pIdx + s2m2] += err * 1.0;
                                    if (vis[pIdx + s2m1]) buf[pIdx + s2m1] += err * 3.0;
                                    if (vis[pIdx + s2p0]) buf[pIdx + s2p0] += err * 5.0;
                                    if (vis[pIdx + s2p1]) buf[pIdx + s2p1] += err * 3.0;
                                    if (vis[pIdx + s2p2]) buf[pIdx + s2p2] += err * 1.0;
                                }
                            } else {
                                for (let x = w - 1; x >= 0; x--) {
                                    const pIdx = rowBase + x;
                                    if (!vis[pIdx]) continue;
                                    const oldVal = buf[pIdx];
                                    const jitter = ((((x * 5281) + (y * 11035)) & 31) - 15.5) * 0.15;
                                    const newVal = (oldVal + jitter) < thrFloat ? 0.0 : 255.0;
                                    const laserFires = (newVal === 0.0);
                                    const off = (rowSrc + x) * 4;
                                    out[off] = laserFires ? markR : baseR;
                                    out[off + 1] = laserFires ? markG : baseG;
                                    out[off + 2] = laserFires ? markB : baseB;
                                    out[off + 3] = src[off + 3];

                                    const err = (oldVal - newVal) * invDiv;
                                    if (err === 0.0) continue;
                                    if (vis[pIdx - 1]) buf[pIdx - 1] += err * 7.0;
                                    if (vis[pIdx - 2]) buf[pIdx - 2] += err * 5.0;
                                    if (vis[pIdx + s1p2]) buf[pIdx + s1p2] += err * 3.0;
                                    if (vis[pIdx + s1p1]) buf[pIdx + s1p1] += err * 5.0;
                                    if (vis[pIdx + s1p0]) buf[pIdx + s1p0] += err * 7.0;
                                    if (vis[pIdx + s1m1]) buf[pIdx + s1m1] += err * 5.0;
                                    if (vis[pIdx + s1m2]) buf[pIdx + s1m2] += err * 3.0;
                                    if (vis[pIdx + s2p2]) buf[pIdx + s2p2] += err * 1.0;
                                    if (vis[pIdx + s2p1]) buf[pIdx + s2p1] += err * 3.0;
                                    if (vis[pIdx + s2p0]) buf[pIdx + s2p0] += err * 5.0;
                                    if (vis[pIdx + s2m1]) buf[pIdx + s2m1] += err * 3.0;
                                    if (vis[pIdx + s2m2]) buf[pIdx + s2m2] += err * 1.0;
                                }
                            }
                        }
                    }

                    this.flushToCanvases();
                },

                flushToCanvases() {
                    if (!this.outImageData) return;
                    const w = this.srcWidth;
                    const h = this.srcHeight;
                    const cssW = this.cssWidth > 0 ? this.cssWidth : w;
                    const cssH = this.cssHeight > 0 ? this.cssHeight : h;

                    const paintCanvas = (c) => {
                        if (!c) return;
                        if (c.width !== w || c.height !== h) {
                            c.width = w;
                            c.height = h;
                        }
                        c.style.width = cssW + 'px';
                        c.style.height = cssH + 'px';
                        const ctx = c.getContext('2d');
                        if (ctx) {
                            ctx.imageSmoothingEnabled = false;
                            ctx.putImageData(this.outImageData, 0, 0);
                        }
                    };

                    paintCanvas(this.$refs.resultCanvas);
                    paintCanvas(this.$refs.splitCanvas);
                },
            };
        };
    </script>
@endonce

@if($isLaserPhotoStudioOpen)
    @php
        $studioCalcPct = static function ($val, float $min, float $max): string {
            if ($max <= $min) {
                return '50.00';
            }
            $clamped = max($min, min($max, (float) $val));

            return number_format((($clamped - $min) / ($max - $min)) * 100.0, 2, '.', '');
        };
        $pContrast = (int) ($photoStudioParams['contrast'] ?? 12);
        $pBrightness = (int) ($photoStudioParams['brightness'] ?? 0);
        $pGamma = (float) ($photoStudioParams['gamma'] ?? 1.15);
        $pHighlightCap = (int) ($photoStudioParams['highlight_cap'] ?? 85);
        $pSharpness = (int) ($photoStudioParams['sharpness'] ?? 85);
        $pThreshold = (int) ($photoStudioParams['threshold'] ?? 128);
        $pDitherMode = (string) ($photoStudioParams['dither_mode'] ?? 'jarvis');
        $defaultStudioBg = $photoStudioIsDarkSurface ? 'dark' : 'light';
        $studioResetDefaults = \App\Services\Laser\LaserPhotoPreprocessor::defaultStudioParams((bool) $photoStudioIsDarkSurface, (int) $photoStudioDpi);
    @endphp

    <div data-laser-photo-studio-modal
         wire:key="laser-photo-studio-{{ $photoStudioItemId }}-{{ md5((string) $photoStudioOriginalRelPath) }}"
         x-data="window.sfCreateLaserPhotoStudio({
            originalUrl: @js($photoStudioOriginalUrl),
            fallbackPreviewUrl: @js($photoStudioPreviewUrl),
            defaultBg: @js($defaultStudioBg),
            defaultParams: @js($studioResetDefaults),
            profiles: @js($photoStudioProfiles ?? []),
            activeProfileId: @js($photoStudioActiveProfileId ?? ''),
            studioDpi: @js((int) $photoStudioDpi),
            params: @js($photoStudioParams)
         })"
         x-init="initStudio()"
         @laser-studio-params-synced.window="syncFromServer($event.detail)"
         @laser-studio-profiles-synced.window="syncProfilesFromServer($event.detail)"
         @resize.window.debounce.120ms="onViewportResize()"
         @keydown.escape.window="$wire.closeLaserPhotoStudio()"
         class="fixed inset-0 z-[140] flex items-start sm:items-center justify-center bg-black/60 dark:bg-black/80 backdrop-blur-sm p-3 sm:p-6 overflow-y-auto">

        <style>
            .sf-studio-checker {
                background-color: #e5e7eb;
                background-image:
                    linear-gradient(45deg, #d1d5db 25%, transparent 25%),
                    linear-gradient(-45deg, #d1d5db 25%, transparent 25%),
                    linear-gradient(45deg, transparent 75%, #d1d5db 75%),
                    linear-gradient(-45deg, transparent 75%, #d1d5db 75%);
                background-size: 16px 16px;
                background-position: 0 0, 0 8px, 8px -8px, -8px 0;
            }
            html.dark .sf-studio-checker {
                background-color: #1f2937;
                background-image:
                    linear-gradient(45deg, #111827 25%, transparent 25%),
                    linear-gradient(-45deg, #111827 25%, transparent 25%),
                    linear-gradient(45deg, transparent 75%, #111827 75%),
                    linear-gradient(-45deg, transparent 75%, #111827 75%);
            }
            .sf-studio-range {
                -webkit-appearance: none;
                appearance: none;
                width: 100%;
                height: 6px;
                border-radius: 9999px;
                outline: none;
                cursor: pointer;
                accent-color: var(--theme-color);
                background: linear-gradient(
                    to right,
                    var(--theme-color) 0%,
                    var(--theme-color) var(--val-pct, 50%),
                    rgba(156, 163, 175, 0.30) var(--val-pct, 50%),
                    rgba(156, 163, 175, 0.30) 100%
                );
            }
            html.dark .sf-studio-range {
                background: linear-gradient(
                    to right,
                    var(--theme-color) 0%,
                    var(--theme-color) var(--val-pct, 50%),
                    rgba(75, 85, 99, 0.55) var(--val-pct, 50%),
                    rgba(75, 85, 99, 0.55) 100%
                );
            }
            .sf-studio-range::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                width: 15px;
                height: 15px;
                border-radius: 9999px;
                background: var(--theme-color);
                border: 2px solid #ffffff;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
                cursor: pointer;
            }
            html.dark .sf-studio-range::-webkit-slider-thumb {
                border-color: #030712;
            }
        </style>

        <div @click.outside="$wire.closeLaserPhotoStudio()"
             class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl w-full max-w-6xl overflow-hidden my-auto flex flex-col max-h-[92vh]">

            {{-- HEADER --}}
            @php
                $initialActiveStudioProfile = collect($photoStudioProfiles ?? [])->firstWhere('id', $photoStudioActiveProfileId ?? '')
                    ?: (collect($photoStudioProfiles ?? [])->first(fn ($p) => !empty($p['is_default'])) ?: (($photoStudioProfiles ?? [])[0] ?? null));
                $initialActiveProfileTitle = is_array($initialActiveStudioProfile)
                    ? (string) ($initialActiveStudioProfile['title'] ?? 'Standard')
                    : 'Standard';
                $initialActiveProfileIsDefault = is_array($initialActiveStudioProfile) && !empty($initialActiveStudioProfile['is_default']);
            @endphp
            <div class="px-5 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex flex-wrap sm:flex-nowrap items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-15)] border border-[var(--theme-color-40)] text-[var(--theme-color)] flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white truncate">
                                Laser-Foto-Studio
                            </h3>
                            <span class="px-2 py-0.5 rounded-md bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)] text-[9px] font-black uppercase tracking-wider"
                                  x-text="studioDpi + ' DPI'">
                                {{ $photoStudioDpi }} DPI
                            </span>
                        </div>
                        <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 truncate">
                            {{ $photoStudioFileName }}
                        </p>
                    </div>
                </div>

                {{-- Rechtsbündig: Produkt-Laserprofil Custom-Dropdown + Schließen-Button --}}
                <div class="flex items-center gap-2 ml-auto shrink-0">
                    <div data-studio-profile-dropdown
                         @click.outside="profileMenuOpen = false"
                         class="relative">
                        <button type="button"
                                data-studio-profile-trigger
                                @click="profileMenuOpen = !profileMenuOpen"
                                title="Aktives Produkt-Laserprofil wählen"
                                :class="profileMenuOpen ? 'border-[var(--theme-color)] ring-1 ring-[var(--theme-color-40)]' : 'border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-50)]'"
                                class="h-9 px-3 rounded-xl bg-white dark:bg-gray-900 border text-left inline-flex items-center gap-2 transition-all cursor-pointer max-w-[240px] sm:max-w-[330px]">
                            <svg class="w-3.5 h-3.5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span class="text-[11px] font-bold text-gray-900 dark:text-white truncate"
                                  x-text="getActiveProfile().title">{{ $initialActiveProfileTitle }}</span>
                            <span :class="getActiveProfile().is_default ? 'inline-flex' : 'hidden'"
                                  class="{{ $initialActiveProfileIsDefault ? 'inline-flex' : 'hidden' }} items-center px-1.5 py-0.5 rounded bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)] text-[9px] font-black uppercase tracking-wider shrink-0">
                                ✓ Standard
                            </span>
                            <svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400 transition-transform duration-150 shrink-0"
                                 :class="profileMenuOpen ? 'rotate-180 text-[var(--theme-color)]' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-cloak
                             data-studio-profile-menu
                             :class="profileMenuOpen ? 'block' : 'hidden'"
                             class="hidden absolute right-0 mt-2 w-80 sm:w-96 max-h-80 overflow-y-auto custom-scrollbar rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl p-1.5 z-50 space-y-1">
                            <template x-for="prof in profiles" :key="prof.id">
                                <button type="button"
                                        @click="selectStudioProfile(prof)"
                                        :class="String(prof.id) === String(getActiveProfile().id)
                                            ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)]'
                                            : 'bg-transparent border-transparent hover:bg-gray-50 dark:hover:bg-gray-950'"
                                        class="w-full px-3 py-2 rounded-xl border text-left transition-colors cursor-pointer flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white truncate" x-text="prof.title"></span>
                                            <span :class="prof.is_default ? 'inline-flex' : 'hidden'"
                                                  class="items-center px-1.5 py-0.5 rounded bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)] text-[9px] font-black uppercase tracking-wider">
                                                ✓ Standard
                                            </span>
                                        </div>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[10px] font-mono text-gray-500 dark:text-gray-400">
                                            <span x-text="(prof.dpi || 600) + ' DPI'"></span>
                                            <span>·</span>
                                            <span x-text="'Raster-Modus: ' + (prof.bitmap_mode || 'Jarvis')"></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0 pt-0.5">
                                        <span :class="prof.photo_studio ? 'inline-flex items-center gap-1' : 'hidden'"
                                              title="Eigene Foto-Vorlage hinterlegt"
                                              class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 text-[9px] font-black uppercase tracking-wider">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Foto-Vorlage</span>
                                        </span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <button type="button"
                            wire:click="closeLaserPhotoStudio"
                            title="Schließen"
                            class="w-9 h-9 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-gray-900 dark:hover:text-white flex items-center justify-center transition-colors cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- BODY: LINKS VORSCHAU · RECHTS STEUERPANEL --}}
            <div class="flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-6 grid grid-cols-1 lg:grid-cols-12 gap-5">

                {{-- LINKS: GROSSE 60-FPS LIVE-VORSCHAU --}}
                <div class="lg:col-span-7 flex flex-col gap-3 min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        {{-- Ansichts-Umschalter --}}
                        <div class="inline-flex items-center p-1 rounded-xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <button type="button"
                                    @click="setView('result')"
                                    :class="studioView === 'result' ? 'bg-[var(--theme-color)] text-gray-950 shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                                    class="px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                Laser-Ergebnis
                            </button>
                            <button type="button"
                                    @click="setView('original')"
                                    :class="studioView === 'original' ? 'bg-[var(--theme-color)] text-gray-950 shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                                    class="px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                Originalfoto
                            </button>
                            <button type="button"
                                    @click="setView('split')"
                                    :class="studioView === 'split' ? 'bg-[var(--theme-color)] text-gray-950 shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                                    class="px-2.5 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                Nebeneinander
                            </button>
                        </div>

                        {{-- Zoom & Material-Hintergrund --}}
                        <div class="flex items-center gap-1.5">
                            <div class="inline-flex items-center p-1 rounded-xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                                <button type="button"
                                        @click="setZoom(1)"
                                        :class="studioZoom === 1 ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 dark:text-gray-400'"
                                        class="px-2 py-1 rounded-lg font-mono text-[10px] font-black cursor-pointer">
                                    100 %
                                </button>
                                <button type="button"
                                        @click="setZoom(2)"
                                        :class="studioZoom === 2 ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 dark:text-gray-400'"
                                        class="px-2 py-1 rounded-lg font-mono text-[10px] font-black cursor-pointer">
                                    200 %
                                </button>
                            </div>

                            <div class="inline-flex items-center p-1 rounded-xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                                <button type="button"
                                        @click="studioBg = 'dark'"
                                        title="Dunkler Rohling-Hintergrund"
                                        :class="studioBg === 'dark' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 dark:text-gray-400'"
                                        class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                    Dunkel
                                </button>
                                <button type="button"
                                        @click="studioBg = 'light'"
                                        title="Heller Rohling-Hintergrund"
                                        :class="studioBg === 'light' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 dark:text-gray-400'"
                                        class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                    Hell
                                </button>
                                <button type="button"
                                        @click="studioBg = 'checker'"
                                        title="Transparenz-Schachbrett"
                                        :class="studioBg === 'checker' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 dark:text-gray-400'"
                                        class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                    Raster
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Bildbühne (wire:ignore verhindert jedes MorphDOM-Duplikat bei Livewire-Updates) --}}
                    <div x-ref="stageBox"
                         wire:ignore
                         :class="getStageBgClass()"
                         class="relative flex-1 min-h-[340px] sm:min-h-[440px] rounded-2xl border overflow-auto custom-scrollbar flex items-center justify-center p-4">

                        <div data-studio-view-panel="result"
                             :class="studioView === 'result' ? 'flex items-center justify-center w-full h-full' : 'hidden'">
                            <canvas x-ref="resultCanvas"
                                    data-studio-live-canvas="result"
                                    :class="canvasReady ? 'block shrink-0 select-none' : 'hidden'"></canvas>

                            <template x-if="!canvasReady && fallbackPreviewUrl">
                                <img :src="fallbackPreviewUrl"
                                     alt="Laser-Ergebnis"
                                     class="max-w-full max-h-[390px] object-contain select-none">
                            </template>
                        </div>

                        <div x-cloak
                             data-studio-view-panel="original"
                             :class="studioView === 'original' ? 'flex items-center justify-center w-full h-full' : 'hidden'">
                            <template x-if="originalUrl">
                                <img :src="originalUrl"
                                     alt="Originalfoto"
                                     :style="studioZoom > 1 ? 'max-height: 760px; max-width: none;' : 'max-height: 390px;'"
                                     class="max-w-full object-contain select-none">
                            </template>
                        </div>

                        <div x-cloak
                             data-studio-view-panel="split"
                             :class="studioView === 'split' ? 'grid grid-cols-1 sm:grid-cols-2 gap-3 w-full items-center' : 'hidden'">
                            <div class="flex flex-col items-center gap-1.5 min-w-0">
                                <span class="px-2 py-0.5 rounded-md bg-gray-900/80 text-white text-[9px] font-black uppercase tracking-wider">Originalfoto</span>
                                <div class="w-full overflow-auto custom-scrollbar flex items-center justify-center">
                                    <template x-if="originalUrl">
                                        <img :src="originalUrl"
                                             alt="Originalfoto"
                                             :style="cssWidth > 0 && cssHeight > 0 ? `width: ${cssWidth}px; height: ${cssHeight}px;` : (studioZoom > 1 ? 'max-height: 760px; max-width: none;' : 'max-height: 330px;')"
                                             class="block shrink-0 object-contain select-none">
                                    </template>
                                </div>
                            </div>
                            <div x-ref="splitResultSlot" class="flex flex-col items-center gap-1.5 min-w-0">
                                <span class="px-2 py-0.5 rounded-md bg-[var(--theme-color)] text-gray-950 text-[9px] font-black uppercase tracking-wider">Laser-Ergebnis</span>
                                <div class="w-full overflow-auto custom-scrollbar flex items-center justify-center">
                                    <canvas x-ref="splitCanvas"
                                            data-studio-live-canvas="split"
                                            :class="canvasReady ? 'block shrink-0 select-none' : 'hidden'"></canvas>

                                    <template x-if="!canvasReady && fallbackPreviewUrl">
                                        <img :src="fallbackPreviewUrl"
                                             alt="Laser-Ergebnis"
                                             class="max-w-full max-h-[330px] object-contain select-none">
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RECHTS: STEUERPANEL (Clean Design ohne Subtexte, Tooltips hinter !, 0 ms Client-Regler) --}}
                <div class="lg:col-span-5 flex flex-col gap-3 min-w-0">

                    {{-- Prominente Automatik + Reset --}}
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="autoOptimizeLaserPhoto"
                                wire:loading.attr="disabled"
                                wire:target="autoOptimizeLaserPhoto"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs font-black uppercase tracking-wider shadow-sm transition-all cursor-pointer">
                            <span wire:loading.remove wire:target="autoOptimizeLaserPhoto">✨ Automatisch Werte setzen</span>
                            <span wire:loading wire:target="autoOptimizeLaserPhoto">✨ Berechne Idealwerte...</span>
                        </button>
                        <button type="button"
                                @click="resetLocalParams()"
                                title="Auf Standardwerte zurücksetzen"
                                class="px-3 py-3 rounded-xl bg-gray-100 dark:bg-gray-950 hover:border-[var(--theme-color)] text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer shrink-0">
                            Zurücksetzen
                        </button>
                    </div>

                    {{-- Schalter: Graustufen & Invertieren --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 cursor-pointer">
                            <span class="text-[11px] font-bold text-gray-900 dark:text-white">Graustufen aktivieren</span>
                            <input type="checkbox"
                                   x-model="params.grayscale"
                                   @change="scheduleRender()"
                                   class="w-4 h-4 rounded border-gray-300 dark:border-gray-700 accent-[var(--theme-color)] cursor-pointer">
                        </label>

                        <label class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 cursor-pointer">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-gray-900 dark:text-white">
                                <span>Invertieren (Dunkler Rohling)</span>
                                <span title="Kehrt Tonwerte für dunkle Oberflächen (z. B. schwarz beschichteter Edelstahl oder Schiefer) um, auf denen der UV-Laser hell abträgt."
                                      class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 inline-flex items-center justify-center text-[9px] font-black cursor-help">!</span>
                            </span>
                            <input type="checkbox"
                                   x-model="params.invert"
                                   @change="onInvertChanged()"
                                   class="w-4 h-4 rounded border-gray-300 dark:border-gray-700 accent-[var(--theme-color)] cursor-pointer">
                        </label>
                    </div>

                    {{-- Regler: Kontrast & Helligkeit --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Kontrast</label>
                                <span class="font-mono text-xs font-black text-[var(--theme-color)]"
                                      x-text="formatSignedInt(params.contrast)">{{ $pContrast > 0 ? '+' . $pContrast : $pContrast }}</span>
                            </div>
                            <input type="range"
                                   min="-100"
                                   max="100"
                                   step="1"
                                   x-model.number="params.contrast"
                                   @input="scheduleRender()"
                                   :style="'--val-pct: ' + calcPct(params.contrast, -100, 100) + '%;'"
                                   style="--val-pct: {{ $studioCalcPct($pContrast, -100, 100) }}%;"
                                   class="sf-studio-range">
                        </div>

                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Helligkeit</label>
                                <span class="font-mono text-xs font-black text-[var(--theme-color)]"
                                      x-text="formatSignedInt(params.brightness)">{{ $pBrightness > 0 ? '+' . $pBrightness : $pBrightness }}</span>
                            </div>
                            <input type="range"
                                   min="-100"
                                   max="100"
                                   step="1"
                                   x-model.number="params.brightness"
                                   @input="scheduleRender()"
                                   :style="'--val-pct: ' + calcPct(params.brightness, -100, 100) + '%;'"
                                   style="--val-pct: {{ $studioCalcPct($pBrightness, -100, 100) }}%;"
                                   class="sf-studio-range">
                        </div>
                    </div>

                    {{-- Regler: Gamma (Mitteltöne) --}}
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                <span>Gamma (Mitteltöne)</span>
                                <span title="Höherer Wert dunkelt überstrahlte Gesichter ab und holt feine Konturen in Stirn und Wangen zurück."
                                      class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 inline-flex items-center justify-center text-[9px] font-black cursor-help">!</span>
                            </label>
                            <span class="font-mono text-xs font-black text-[var(--theme-color)]"
                                  x-text="formatGamma(params.gamma)">{{ number_format($pGamma, 2, ',', '.') }}</span>
                        </div>
                        <input type="range"
                               min="0.35"
                               max="2.50"
                               step="0.05"
                               x-model.number="params.gamma"
                               @input="scheduleRender()"
                               :style="'--val-pct: ' + calcPct(params.gamma, 0.35, 2.50) + '%;'"
                               style="--val-pct: {{ $studioCalcPct($pGamma, 0.35, 2.50) }}%;"
                               class="sf-studio-range">
                    </div>

                    {{-- Regler: Lichter-Schutz & Schärfe --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                    <span>Lichter-Schutz (Max. Laser-Dichte)</span>
                                    <span title="Begrenzt die maximale Punktdichte und verhindert weißes Ausbrennen durch UV-Kraterüberlappung."
                                          class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 inline-flex items-center justify-center text-[9px] font-black cursor-help shrink-0">!</span>
                                </label>
                                <span class="font-mono text-xs font-black text-[var(--theme-color)] shrink-0"
                                      x-text="Math.round(params.highlight_cap) + ' %'">{{ $pHighlightCap }} %</span>
                            </div>
                            <input type="range"
                                   min="30"
                                   max="100"
                                   step="1"
                                   x-model.number="params.highlight_cap"
                                   @input="scheduleRender()"
                                   :style="'--val-pct: ' + calcPct(params.highlight_cap, 30, 100) + '%;'"
                                   style="--val-pct: {{ $studioCalcPct($pHighlightCap, 30, 100) }}%;"
                                   class="sf-studio-range">
                        </div>

                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                    <span>Schärfe (Kanten)</span>
                                    <span title="Unsharp-Mask betont Augen, Wimpern und feine Kanten gegen Materialstreuung."
                                          class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 inline-flex items-center justify-center text-[9px] font-black cursor-help shrink-0">!</span>
                                </label>
                                <span class="font-mono text-xs font-black text-[var(--theme-color)] shrink-0"
                                      x-text="Math.round(params.sharpness) + ' %'">{{ $pSharpness }} %</span>
                            </div>
                            <input type="range"
                                   min="0"
                                   max="200"
                                   step="5"
                                   x-model.number="params.sharpness"
                                   @input="scheduleRender()"
                                   :style="'--val-pct: ' + calcPct(params.sharpness, 0, 200) + '%;'"
                                   style="--val-pct: {{ $studioCalcPct($pSharpness, 0, 200) }}%;"
                                   class="sf-studio-range">
                        </div>
                    </div>

                    {{-- Auswahl: Raster-Modus (+ optionaler Schwellenwert) --}}
                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2.5">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">
                                Raster-Modus
                            </label>
                            <select x-model="params.dither_mode"
                                    @change="scheduleRender()"
                                    class="w-full px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:outline-none cursor-pointer">
                                <option value="jarvis">Jarvis (Porträt-Standard)</option>
                                <option value="stucki">Stucki (Fein)</option>
                                <option value="grayscale">Reine Graustufen</option>
                                <option value="threshold">Schwellenwert (Logo S/W)</option>
                            </select>
                        </div>

                        <div x-cloak
                             :class="params.dither_mode === 'threshold' ? 'pt-2 border-t border-gray-200 dark:border-gray-800 space-y-1.5' : 'hidden'">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Schwellenwert</label>
                                <span class="font-mono text-xs font-black text-[var(--theme-color)]"
                                      x-text="Math.round(params.threshold)">{{ $pThreshold }}</span>
                            </div>
                            <input type="range"
                                   min="10"
                                   max="245"
                                   step="1"
                                   x-model.number="params.threshold"
                                   @input="scheduleRender()"
                                   :style="'--val-pct: ' + calcPct(params.threshold, 10, 245) + '%;'"
                                   style="--val-pct: {{ $studioCalcPct($pThreshold, 10, 245) }}%;"
                                   class="sf-studio-range">
                        </div>
                    </div>

                    {{-- Schlanke Box: LASERPROFIL-VORLAGE (Clean Design ohne Subtexte, Erklärung hinter !-Tooltip) --}}
                    <div data-studio-profile-template-box
                         class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                <span>Laserprofil-Vorlage</span>
                                <span title="Hinterlegt die aktuellen Foto-Reglerwerte dauerhaft am Produkt-Laserprofil oder legt daraus ein neues Laserprofil für künftige Bestellungen an."
                                      class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 inline-flex items-center justify-center text-[9px] font-black cursor-help shrink-0">!</span>
                            </span>
                            <span x-cloak
                                  :class="profileSavedFlash ? 'inline-flex items-center gap-1' : 'hidden'"
                                  class="hidden px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 text-[9px] font-black uppercase tracking-wider">
                                ✓ Gespeichert
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <button type="button"
                                    data-save-active-profile-btn
                                    @click="saveToCurrentLaserProfile()"
                                    :disabled="isSavingProfileTemplate || profiles.length === 0"
                                    class="px-3 py-2 rounded-xl bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-900 dark:text-white border border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer disabled:opacity-50">
                                <span :class="profileSavedFlash ? 'hidden' : 'inline'">Im aktuellen Laserprofil speichern</span>
                                <span x-cloak :class="profileSavedFlash ? 'inline text-emerald-600 dark:text-emerald-400' : 'hidden'">✓ Gespeichert</span>
                            </button>

                            <button type="button"
                                    data-toggle-create-profile-btn
                                    @click="showCreateProfileForm = !showCreateProfileForm"
                                    :class="showCreateProfileForm ? 'border-[var(--theme-color)] text-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)]'"
                                    class="px-3 py-2 rounded-xl border text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                + Als neues Laserprofil speichern
                            </button>
                        </div>

                        <div x-cloak
                             data-create-profile-form
                             :class="showCreateProfileForm ? 'flex items-center gap-2 pt-1' : 'hidden'"
                             class="hidden">
                            <input type="text"
                                   maxlength="80"
                                   x-model="newProfileTitle"
                                   @keydown.enter.prevent="submitCreateLaserProfile()"
                                   placeholder="Profilname, z. B. Porträt Hell"
                                   class="flex-1 min-w-0 px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-[var(--theme-color)] focus:outline-none">
                            <button type="button"
                                    data-submit-create-profile-btn
                                    @click="submitCreateLaserProfile()"
                                    :disabled="isCreatingProfile || String(newProfileTitle || '').trim().length < 2"
                                    class="px-3.5 py-2 rounded-xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-[10px] font-black uppercase tracking-wider shadow-2xs transition-all cursor-pointer disabled:opacity-50 shrink-0">
                                Anlegen
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="px-5 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex items-center justify-between gap-3 shrink-0">
                <button type="button"
                        wire:click="closeLaserPhotoStudio"
                        :disabled="isSavingStudio"
                        class="px-4 py-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer disabled:opacity-50">
                    Abbrechen
                </button>

                <button type="button"
                        @click="submitStudioSave()"
                        :disabled="isSavingStudio"
                        wire:loading.attr="disabled"
                        wire:target="saveLaserPhotoStudio"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs font-black uppercase tracking-wider shadow-md transition-all cursor-pointer disabled:opacity-75">
                    <span :class="isSavingStudio ? 'hidden' : 'inline-flex items-center gap-2'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Für Laser übernehmen</span>
                    </span>
                    <span x-cloak :class="isSavingStudio ? 'inline-flex items-center gap-2' : 'hidden'">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                        </svg>
                        <span>Wird übernommen...</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif
