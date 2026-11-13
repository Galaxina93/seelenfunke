@script
<script>
    /*!
     * © Mein-Seelenfunke (Inhaberin: Alina Steinhauer, Gifhorn) - 2D/3D-Gravur-Studio Engine
     * Alle Rechte vorbehalten (§§ 2, 10, 69a UrhG, § 4 Nr. 3 UWG).
     * Jede unautorisierte Vervielfältigung, Dekompilierung, Extraktion oder Nachbildung ist untersagt.
     */
    window.frontendConfiguratorData = function(params) {
        return {
            texts: params.wireModels.texts || [],
            logos: params.wireModels.logos || [],
            texts_back: params.wireModels.texts_back || [],
            logos_back: params.wireModels.logos_back || [],
            fontMap: params.fonts || {},
            context: params.context || 'add',
            config: params.config || {},
            textDims: {},
            alignMap: {
                'left': 'text-left',
                'center': 'text-center',
                'right': 'text-right'
            },
            showDrawingBoard: true,
            modelLoaded: false,
            loadingProgress: 0,
            isSaving: false,
            saved: false,

            activeSide: params.wireModels.activeSide !== undefined ? params.wireModels.activeSide : 'front',
            backgroundTheme: params.wireModels.backgroundTheme !== undefined ? params.wireModels.backgroundTheme : 'standard',
            videoEnabled: (params.config && params.config.enableBgVideo !== undefined) ? Boolean(params.config.enableBgVideo) : true,
            isDarkSurface: Boolean(params.config && params.config.isDarkSurface),
            hasTransparentOverlay: Boolean(params.config && params.config.hasTransparentOverlay),

            selectedIndex: null,
            selectedType: null,
            focusedTextId: null,
            lastSelectedFont: (params.wireModels && params.wireModels.texts && params.wireModels.texts[0] && params.wireModels.texts[0].font) ? params.wireModels.texts[0].font : 'Arial',
            showFontMenu: false,
            showSizeMenu: false,
            showAlignMenu: false,
            showPosMenu: false,
            isDragging: false,
            isResizing: false,
            isRotating: false,
            isAdjustingCurve: false,
            liveCurveValue: null,
            _curveRafId: null,
            _justTransformed: false,
            dragStartX: 0,
            dragStartY: 0,
            hasDraggedDistance: false,
            currentElement: null,
            dragOffsetX: 0,
            dragOffsetY: 0,
            scaleFactor: 1,
            baseWidth: 500,
            showGuideX: false,
            showGuideY: false,
            showImageEditor: false,
            fileRobotInstance: null,
            isCapturing: false, // UI-Status während Snapshot

            // Direktes Zuschneiden (In-Place Crop) auf der 2D-Vorschau
            isCropping: false,
            isApplyingCrop: false,
            cropBox: { top: 0, right: 0, bottom: 0, left: 0, shape: 'rect' },
            activeCropHandle: null,
            cropStartX: 0,
            cropStartY: 0,
            cropStartBox: null,
            cropElemW: 100,
            cropElemH: 100,
            cropRotation: 0,

            hasSavedDesign: false,
            showMessage: false,
            messageText: '',

            // Vollbild / Maximal-Ansicht
            isMaximized: false,

            // Zoom & Extra-Zoomfenster (Navigator / Minimap)
            stageZoom: 1.0,
            stagePanX: 0.0,
            stagePanY: 0.0,
            _zoomFocusX: null,
            _zoomFocusY: null,
            isDraggingMinimap: false,
            _minimapOffsetX: 0,
            _minimapOffsetY: 0,

            // Undo / Redo History
            history: [],
            historyIndex: -1,
            maxHistory: 30,
            isPerformingHistoryAction: false,
            _historyDebounceTimeout: null,

            // Emoji Warnung & Hinweise
            showEmojiWarning: false,
            _emojiWarningTimeout: null,
            showNotice: true,
            confirmWarning: false,

            // Formular & Tabs
            activeTab: null,
            draftExists: false,
            isDraftSaving: false,
            isDraftLoading: false,
            storageDraftKey: 'seelenfunke_design_draft_' + (params.productId || (params.config && params.config.productId) || 'default'),

            // Upload & Dropzone States
            uploadError: null,
            isDropping: false,

            get canUndo() {
                return this.historyIndex > 0;
            },
            get canRedo() {
                return this.historyIndex >= 0 && this.historyIndex < this.history.length - 1;
            },

            normalizeArray(val) {
                if (!val) return [];
                const raw = (window.Alpine && typeof window.Alpine.raw === 'function') ? window.Alpine.raw(val) : val;
                if (Array.isArray(raw)) return raw.filter(item => item !== null && item !== undefined);
                if (typeof raw === 'object') return Object.values(raw).filter(item => item !== null && item !== undefined);
                return [];
            },

            get currentTexts() {
                const list = this.activeSide === 'front' ? this.texts : this.texts_back;
                return Array.isArray(list) ? list : this.normalizeArray(list);
            },
            get currentLogos() {
                const list = this.activeSide === 'front' ? this.logos : this.logos_back;
                return Array.isArray(list) ? list : this.normalizeArray(list);
            },

            get areaCenterX() {
                if (this.config && this.config.beginnerCoords && Number(this.config.beginnerCoords.center_x) > 0) {
                    return Number(this.config.beginnerCoords.center_x);
                }
                if (this.config && this.config.area_shape === 'custom') {
                    const pts = this.config.custom_points || [];
                    if (pts.length > 0) {
                        const xs = pts.map(p => Number(p.x ?? 50));
                        return (Math.min(...xs) + Math.max(...xs)) / 2;
                    }
                    return 50;
                }
                const left = Number(this.config?.area_left ?? 0);
                const width = Number(this.config?.area_width ?? 100);
                return left + (width / 2);
            },
            get areaCenterY() {
                if (this.config && this.config.beginnerCoords && Number(this.config.beginnerCoords.center_y) > 0) {
                    return Number(this.config.beginnerCoords.center_y);
                }
                if (this.config && this.config.area_shape === 'custom') {
                    const pts = this.config.custom_points || [];
                    if (pts.length > 0) {
                        const ys = pts.map(p => Number(p.y ?? 50));
                        return (Math.min(...ys) + Math.max(...ys)) / 2;
                    }
                    return 50;
                }
                const top = Number(this.config?.area_top ?? 0);
                const height = Number(this.config?.area_height ?? 100);
                return top + (height / 2);
            },
            getWorkingAreaWidthPercent() {
                if (this.config && this.config.beginnerCoords && Number(this.config.beginnerCoords.area_width_pct) > 0) {
                    return Number(this.config.beginnerCoords.area_width_pct);
                }
                if (this.config && this.config.area_shape === 'custom') {
                    const pts = this.config.custom_points || [];
                    if (pts.length > 0) {
                        const xs = pts.map(p => Number(p.x ?? 50));
                        return Math.max(8, Math.max(...xs) - Math.min(...xs));
                    }
                }
                return Math.max(8, Number(this.config?.area_width ?? 80));
            },
            getDefaultStudioTextSize() {
                if (this.config && this.config.beginnerCoords && Number(this.config.beginnerCoords.studio_text_size) > 0) {
                    return Number(this.config.beginnerCoords.studio_text_size);
                }
                const w = this.getWorkingAreaWidthPercent();
                return Math.round(Math.max(0.48, Math.min(0.88, (w - 10) / 36)) * 100) / 100;
            },
            getMaxEmptyTextWidthPx() {
                const scale = this.scaleFactor || 1;
                const stageWidthPx = 600 * scale;
                const areaWidthPx = stageWidthPx * (this.getWorkingAreaWidthPercent() / 100);
                // 32px für die 4 Eck-Aktionsicons (-left-4 / -right-4) + 12px Sicherheitsabstand zum grünen Arbeitsflächenrand
                return Math.max(64, Math.floor(areaWidthPx - 44));
            },
            getEffectiveTextareaFontSizePx(item) {
                const scale = this.scaleFactor || 1;
                const basePx = (20 * ((item && item.size) || this.getDefaultStudioTextSize())) * scale;
                const hasText = item && item.text !== undefined && item.text !== null && String(item.text).length > 0;
                if (hasText) {
                    return basePx;
                }
                const maxEmptyContentW = Math.max(44, this.getMaxEmptyTextWidthPx() - 24);
                // "Text eingeben..." benötigt in bold Arial ca. 8.3 * fontSizePx Breite
                const estimatedPlaceholderW = basePx * 8.4;
                if (estimatedPlaceholderW > maxEmptyContentW) {
                    return Math.max(8.5, Math.floor(basePx * (maxEmptyContentW / estimatedPlaceholderW) * 10) / 10);
                }
                return basePx;
            },

            get activeThemeObj() {
                const themes = (this.config && this.config.themes) ? this.config.themes : {};
                const key = this.backgroundTheme || 'standard';
                return themes[key] || themes['standard'] || null;
            },
            get activeBgVideo() {
                if (!this.videoEnabled) return '';
                if (this.config && this.config.customBgVideo && (!this.backgroundTheme || this.backgroundTheme === 'standard')) {
                    return this.config.customBgVideo;
                }
                return (this.activeThemeObj && this.activeThemeObj.videoUrl) ? this.activeThemeObj.videoUrl : '';
            },
            get activeBgImage() {
                if (this.activeThemeObj && this.activeThemeObj.imageUrl && this.backgroundTheme && this.backgroundTheme !== 'standard') {
                    return this.activeThemeObj.imageUrl;
                }
                return (this.config && this.config.bgPath) ? this.config.bgPath : ((this.activeThemeObj && this.activeThemeObj.imageUrl) ? this.activeThemeObj.imageUrl : '');
            },
            get activeOverlayImg() {
                if (this.activeThemeObj && this.activeThemeObj.overlayUrl) {
                    return this.activeThemeObj.overlayUrl;
                }
                return (this.config && this.config.fallbackImg) ? this.config.fallbackImg : '';
            },
            setBackgroundTheme(themeKey) {
                this.backgroundTheme = themeKey;
                this.$nextTick(() => {
                    const vid = this.$refs.bgVideo;
                    if (vid && this.activeBgVideo) {
                        vid.load();
                        vid.play().catch(() => {});
                    }
                    const engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                    if (engine && typeof engine.updateBackground === 'function' && this.activeBgImage) {
                        engine.updateBackground(this.activeBgImage);
                    }
                    this.analyzeOverlayContrast(this.activeOverlayImg);
                });
            },

            analyzeOverlayContrast(overrideUrl = null) {
                const targetUrl = overrideUrl || this.activeOverlayImg || (this.config ? this.config.fallbackImg : '');
                if (!targetUrl) return;
                const img = new Image();
                // Nur bei echten Cross-Origin-URLs setzen, damit Mobile-Chrome nicht denselben Bild-Cache-Eintrag wegen CORS vs. No-CORS verwirft
                if (!targetUrl.startsWith(window.location.origin) && !targetUrl.startsWith('/')) {
                    img.crossOrigin = 'anonymous';
                }
                img.onload = () => {
                    try {
                        const canvas = document.createElement('canvas');
                        const size = 64;
                        canvas.width = size;
                        canvas.height = size;
                        const ctx = canvas.getContext('2d', { willReadFrequently: true });
                        if (!ctx) return;
                        ctx.drawImage(img, 0, 0, size, size);
                        const data = ctx.getImageData(0, 0, size, size).data;

                        // 1. Ecken auf Alpha-Transparenz prüfen (Cutout-PNG wie beim Seelenanhänger)
                        const corners = [
                            (1 * size + 1) * 4,
                            (1 * size + (size - 2)) * 4,
                            ((size - 2) * size + 1) * 4,
                            ((size - 2) * size + (size - 2)) * 4
                        ];
                        let transCorners = 0;
                        corners.forEach(idx => {
                            if (data[idx + 3] < 128) transCorners++;
                        });
                        this.hasTransparentOverlay = transCorners >= 2;

                        // 2. Durchschnittliche Helligkeit im zentralen Gravurbereich messen
                        let lumSum = 0;
                        let count = 0;
                        const aLeft = Number(this.areaLeft ?? 30);
                        const aTop = Number(this.areaTop ?? 30);
                        const aWidth = Number(this.areaWidth ?? 40);
                        const aHeight = Number(this.areaHeight ?? 40);
                        const xStart = Math.max(0, Math.min(size - 1, Math.floor(size * ((aLeft + aWidth * 0.25) / 100))));
                        const xEnd = Math.max(xStart, Math.min(size - 1, Math.floor(size * ((aLeft + aWidth * 0.75) / 100))));
                        const yStart = Math.max(0, Math.min(size - 1, Math.floor(size * ((aTop + aHeight * 0.25) / 100))));
                        const yEnd = Math.max(yStart, Math.min(size - 1, Math.floor(size * ((aTop + aHeight * 0.75) / 100))));
                        for (let y = yStart; y <= yEnd; y++) {
                            for (let x = xStart; x <= xEnd; x++) {
                                const idx = (y * size + x) * 4;
                                if (data[idx + 3] > 100) {
                                    lumSum += 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                                    count++;
                                }
                            }
                        }
                        if (count > 0) {
                            const avgLum = lumSum / count;
                            this.isDarkSurface = avgLum < 65;
                        } else if (this.hasTransparentOverlay) {
                            this.isDarkSurface = true;
                        }
                    } catch (e) {
                        // Fallback auf Server-Analyse
                    }
                };
                img.src = targetUrl;
            },

            toggleNotice(val = null) {
                this.showNotice = (typeof val === 'boolean') ? val : !this.showNotice;
                try {
                    localStorage.setItem('seelenfunke_config_show_notice', this.showNotice ? '1' : '0');
                } catch (e) {}
                window.dispatchEvent(new CustomEvent('configurator-prefs-synced', {
                    detail: { showNotice: this.showNotice, videoEnabled: this.videoEnabled }
                }));
            },

            getSmoothPolygonPoints(rawPts) {
                const pts = (Array.isArray(rawPts) ? rawPts : []).filter(p => p && typeof p === 'object' && isFinite(p.x) && isFinite(p.y));
                if (pts.length < 3) return pts;

                let current = pts.map(p => ({ x: Number(p.x), y: Number(p.y) }));
                const passes = 2;

                for (let pass = 0; pass < passes; pass++) {
                    const next = [];
                    const n = current.length;
                    for (let i = 0; i < n; i++) {
                        const pPrev = current[(i - 1 + n) % n];
                        const pCurr = current[i];
                        const pNext = current[(i + 1) % n];

                        const v1x = pCurr.x - pPrev.x, v1y = pCurr.y - pPrev.y;
                        const v2x = pNext.x - pCurr.x, v2y = pNext.y - pCurr.y;
                        const l1 = Math.hypot(v1x, v1y), l2 = Math.hypot(v2x, v2y);
                        const cosA = (l1 > 0.001 && l2 > 0.001) ? ((v1x * v2x + v1y * v2y) / (l1 * l2)) : 1;

                        // Echte Spitzen (z. B. Herz-Einkerbung oben oder Herz-Spitze unten) scharf erhalten,
                        // lange gerade Kanten (z. B. Talisman / ID-Armband) nicht nach innen ziehen (max. 1.2% Corner-Offset)
                        if (cosA < 0.55) {
                            next.push({ x: pCurr.x, y: pCurr.y });
                        } else {
                            const t1 = l1 > 0.001 ? Math.min(0.25, 1.2 / l1) : 0.25;
                            const t2 = l2 > 0.001 ? Math.min(0.25, 1.2 / l2) : 0.25;
                            next.push({
                                x: Number(((1 - t1) * pCurr.x + t1 * pPrev.x).toFixed(2)),
                                y: Number(((1 - t1) * pCurr.y + t1 * pPrev.y).toFixed(2))
                            });
                            next.push({
                                x: Number(((1 - t2) * pCurr.x + t2 * pNext.x).toFixed(2)),
                                y: Number(((1 - t2) * pCurr.y + t2 * pNext.y).toFixed(2))
                            });
                        }
                    }
                    current = next;
                }
                return current;
            },

            getSmoothPolygonClipPath(rawPts) {
                const smooth = this.getSmoothPolygonPoints(rawPts);
                if (!smooth || smooth.length === 0) return 'none';
                return 'polygon(' + smooth.map(p => p.x + '% ' + p.y + '%').join(', ') + ')';
            },

            getSmoothPolygonSvgPoints(rawPts) {
                const smooth = this.getSmoothPolygonPoints(rawPts);
                if (!smooth || smooth.length === 0) return '';
                return smooth.map(p => p.x + ',' + p.y).join(' ');
            },

            toggleAnimation(val = null) {
                this.videoEnabled = (typeof val === 'boolean') ? val : !this.videoEnabled;
                try {
                    localStorage.setItem('seelenfunke_config_show_animation', this.videoEnabled ? '1' : '0');
                } catch (e) {}
                window.dispatchEvent(new CustomEvent('configurator-prefs-synced', {
                    detail: { showNotice: this.showNotice, videoEnabled: this.videoEnabled }
                }));
                if (this.videoEnabled) {
                    this.$nextTick(() => {
                        const vid = this.$refs.bgVideo;
                        if (vid && this.activeBgVideo) {
                            vid.load();
                            vid.play().catch(() => {});
                        }
                    });
                }
            },

            init() {
                window._frontendConfiguratorDataInstance = this;
                this.isInitializing = false;

                // Gespeicherte Kunden-Präferenzen (Hinweis & Jahreszeiten-Animation) produktübergreifend aus localStorage laden
                try {
                    const savedNotice = localStorage.getItem('seelenfunke_config_show_notice');
                    if (savedNotice !== null) {
                        this.showNotice = savedNotice !== '0';
                    }
                    const savedAnim = localStorage.getItem('seelenfunke_config_show_animation');
                    if (savedAnim !== null) {
                        this.videoEnabled = savedAnim !== '0';
                    }
                } catch (e) {}

                window.dispatchEvent(new CustomEvent('configurator-prefs-synced', {
                    detail: { showNotice: this.showNotice, videoEnabled: this.videoEnabled }
                }));

                this._onCartUpdated = () => {
                    this.saved = true;
                    setTimeout(() => { this.saved = false; }, 15000);
                };
                window.addEventListener('cart-updated', this._onCartUpdated);

                this._onToggleHints = (e) => {
                    if (e.detail && typeof e.detail.show !== 'undefined') {
                        this.toggleNotice(Boolean(e.detail.show));
                    } else {
                        this.toggleNotice();
                    }
                };
                window.addEventListener('toggle-configurator-hints', this._onToggleHints);

                this._onToggleAnim = (e) => {
                    if (e.detail && typeof e.detail.enable !== 'undefined') {
                        this.toggleAnimation(Boolean(e.detail.enable));
                    } else {
                        this.toggleAnimation();
                    }
                };
                window.addEventListener('toggle-configurator-animation', this._onToggleAnim);

                this._cleanupFullscreen = () => {
                    try {
                        document.body.style.overflow = '';
                        document.documentElement.classList.remove('configurator-fullscreen-active');
                        if (this._neutralizedParents && this._neutralizedParents.length) {
                            this._neutralizedParents.forEach(item => {
                                item.el.style.transform = item.transform || '';
                                item.el.style.animation = item.animation || '';
                                item.el.style.backdropFilter = item.backdropFilter || '';
                                item.el.style.webkitBackdropFilter = item.webkitBackdropFilter || '';
                                item.el.style.filter = item.filter || '';
                                item.el.style.overflow = item.overflow || '';
                                item.el.style.overflowX = item.overflowX || '';
                                item.el.style.overflowY = item.overflowY || '';
                                item.el.style.borderRadius = item.borderRadius || '';
                            });
                            this._neutralizedParents = [];
                        }
                    } catch(e) {}
                };

                window.addEventListener('beforeunload', this._cleanupFullscreen);
                document.addEventListener('livewire:navigating', this._cleanupFullscreen);

                if(typeof this.config.area_left === 'undefined') this.config.area_left = 10;
                if(typeof this.config.area_top === 'undefined') this.config.area_top = 10;
                if(typeof this.config.area_width === 'undefined') this.config.area_width = 80;
                if(typeof this.config.area_height === 'undefined') this.config.area_height = 80;

                this.draftExists = localStorage.getItem(this.storageDraftKey) !== null;
                this.hasSavedDesign = this.draftExists;

                this.analyzeOverlayContrast();

                this.$nextTick(() => {
                    if (this.$refs.container) {
                        this._resizeObserver = new ResizeObserver((entries) => {
                            for (let entry of entries) {
                                const w = entry.contentRect.width;
                                if (w > 0 && (!this._lastObservedWidth || Math.abs(this._lastObservedWidth - w) >= 1)) {
                                    this._lastObservedWidth = w;
                                    this.updateScaleFactor(w);
                                }
                            }
                        });
                        this._resizeObserver.observe(this.$refs.container);
                    }
                    this.updateScaleFactor();

                    const startConfigurator = () => {
                        if (this.config.modelPath && this.$refs.container3d && this.config.isPersonalizable !== false && (this.config.personalizationMode !== 'custom_fields' || this.config.isBeginnerMode)) {
                            this.$refs.container3d._engine = new window.Configurator3DEngine(this, this.$refs.container3d, this.config.modelPath, this.activeBgImage || this.config.bgPath, this.config);

                            const forceStart3D = () => {
                                if (this.modelLoaded || this.isInitializing || !this.$refs.container3d || !this.$refs.container3d._engine) return;
                                this.isInitializing = true;

                                this.$refs.container3d._engine.init(() => {
                                    this.modelLoaded = true;
                                    this.isInitializing = false;
                                    this.updateTexture();

                                    if (this.showDrawingBoard === false && this.$refs.container3d && this.$refs.container3d._engine) {
                                        this.$refs.container3d._engine.animate();
                                    }
                                });
                            };

                            this._forceStart3D = forceStart3D;

                            const requestStart = () => {
                                if (window.requestIdleCallback) {
                                    window.requestIdleCallback((deadline) => {
                                        if (deadline.timeRemaining() < 10 && !this.isInitializing) {
                                            setTimeout(requestStart, 500);
                                            return;
                                        }
                                        forceStart3D();
                                    }, { timeout: 3000 });
                                } else {
                                    setTimeout(() => forceStart3D(), 1500);
                                }
                            };

                            // Auf Smartphones (width < 768) die WebGL-3D-Engine im 2D-Modus nicht ungefragt im Hintergrund starten,
                            // damit Mobile-Browser (Android/iOS) nicht den 2D-Layer / GPU-Speicher verwerfen.
                            // Sobald der Nutzer auf "3D Ansicht" tippt oder speichert, startet _forceStart3D() sofort bedarfsgesteuert.
                            if (window.innerWidth >= 768 || !this.showDrawingBoard) {
                                requestStart();
                            }

                        } else {
                            this.modelLoaded = true;
                            this.showDrawingBoard = true;
                        }
                    };

                    const checkDependencies = () => {
                        if (window.THREE && window.GLTFLoader && window.Configurator3DEngine) {
                            startConfigurator();
                        } else {
                            setTimeout(checkDependencies, 250);
                        }
                    };

                    checkDependencies();

                    this.$watch('texts', (val) => {
                        if (val && !Array.isArray(val) && typeof val === 'object') {
                            this.texts = this.normalizeArray(val);
                            return;
                        }
                        const list = this.normalizeArray(this.texts);
                        if(this.selectedType === 'text' && this.activeSide === 'front' && !list[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                        this.scheduleDebouncedHistory();
                    }, {deep: true});

                    this.$watch('logos', (val) => {
                        if (val && !Array.isArray(val) && typeof val === 'object') {
                            this.logos = this.normalizeArray(val);
                            return;
                        }
                        const list = this.normalizeArray(this.logos);
                        if (list.length > 0 && this.context !== 'preview' && !this.isPerformingHistoryAction) {
                            const frontTexts = this.normalizeArray(this.texts);
                            if (frontTexts.length > 0 && frontTexts.every(t => !t || !t.text || String(t.text).trim() === '')) {
                                this.focusedTextId = null;
                                this.texts = [];
                                if (this.activeSide === 'front') {
                                    this.selectItem('logo', list.length - 1);
                                }
                            }
                        }
                        if(this.selectedType === 'logo' && this.activeSide === 'front' && !list[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                        this.scheduleDebouncedHistory();
                    }, {deep: true});

                    this.$watch('texts_back', (val) => {
                        if (val && !Array.isArray(val) && typeof val === 'object') {
                            this.texts_back = this.normalizeArray(val);
                            return;
                        }
                        const list = this.normalizeArray(this.texts_back);
                        if(this.selectedType === 'text' && this.activeSide === 'back' && !list[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                        this.scheduleDebouncedHistory();
                    }, {deep: true});

                    this.$watch('logos_back', (val) => {
                        if (val && !Array.isArray(val) && typeof val === 'object') {
                            this.logos_back = this.normalizeArray(val);
                            return;
                        }
                        const list = this.normalizeArray(this.logos_back);
                        if (list.length > 0 && this.context !== 'preview' && !this.isPerformingHistoryAction) {
                            const backTexts = this.normalizeArray(this.texts_back);
                            if (backTexts.length > 0 && backTexts.every(t => !t || !t.text || String(t.text).trim() === '')) {
                                this.focusedTextId = null;
                                this.texts_back = [];
                                if (this.activeSide === 'back') {
                                    this.selectItem('logo', list.length - 1);
                                }
                            }
                        }
                        if(this.selectedType === 'logo' && this.activeSide === 'back' && !list[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                        this.scheduleDebouncedHistory();
                    }, {deep: true});

                    this.$watch('backgroundTheme', () => {
                        this.$nextTick(() => {
                            const vid = this.$refs.bgVideo;
                            if (vid && this.activeBgVideo) {
                                vid.load();
                                vid.play().catch(() => {});
                            }
                            let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                            if (engine && typeof engine.updateBackground === 'function' && this.activeBgImage) {
                                engine.updateBackground(this.activeBgImage);
                            }
                            this.analyzeOverlayContrast(this.activeOverlayImg);
                        });
                    });

                    this.$watch('activeSide', (val) => {
                        if (!this._keepSelectionOnSideSwitch) {
                            this.selectedType = null;
                            this.selectedIndex = null;
                            this.focusedTextId = null;
                        }
                        this._keepSelectionOnSideSwitch = false;

                        let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                        if (engine) {
                            if (typeof engine.rotateToSide === 'function') {
                                engine.rotateToSide(val, true);
                            }
                            if (typeof engine.applyTransforms === 'function') {
                                engine.applyTransforms(val);
                            }
                        }
                        this.updateTexture();

                        this.$nextTick(() => {
                            this.refitAllTextareas();
                        });
                    });

                    this._onResize = () => {
                        this.onWindowResize();
                        let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                        if(engine) engine.resize();
                    };
                    window.addEventListener('resize', this._onResize);

                    if (!this.config.isBeginnerMode) {
                        if(this.texts && this.texts.length > 0) this.selectItem('text', 0);
                        else if (this.logos && this.logos.length > 0) this.selectItem('logo', 0);
                    }

                    document.fonts.ready.then(() => {
                        this.updateScaleFactor(null, true);
                        const initialUpdate = () => {
                            if (window.requestIdleCallback) {
                                window.requestIdleCallback(() => {
                                    this.updateTexture();
                                    this.recordHistory(true);
                                });
                            } else {
                                setTimeout(() => {
                                    this.updateTexture();
                                    this.recordHistory(true);
                                }, 300);
                            }
                        };
                        initialUpdate();
                    });
                });

                this.$watch('showDrawingBoard', (value) => {
                    if (value === true) {
                        this.$nextTick(() => {
                            this.updateScaleFactor(null, true);
                            requestAnimationFrame(() => {
                                this.updateScaleFactor(null, true);
                            });
                            setTimeout(() => {
                                this.updateScaleFactor(null, true);
                            }, 60);
                        });
                    } else {
                        setTimeout(() => {
                            let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                            if (engine) {
                                if (!this.modelLoaded) {
                                    if (typeof this._forceStart3D === 'function') this._forceStart3D();
                                } else {
                                    if (typeof engine.rotateToSide === 'function') {
                                        engine.rotateToSide(this.activeSide, false);
                                    }
                                    engine.animate();
                                    this.updateTexture(true);
                                }
                                requestAnimationFrame(() => {
                                    if(engine) engine.resize();
                                });
                            }
                        }, 10);
                    }
                });
            },

            destroy() {
                if (this._curveRafId) cancelAnimationFrame(this._curveRafId);
                if (this._onCartUpdated) window.removeEventListener('cart-updated', this._onCartUpdated);
                if (this._onToggleHints) window.removeEventListener('toggle-configurator-hints', this._onToggleHints);
                if (this._onToggleAnim) window.removeEventListener('toggle-configurator-animation', this._onToggleAnim);
                if (this._onResize) window.removeEventListener('resize', this._onResize);
                if (this._cleanupFullscreen) {
                    this._cleanupFullscreen();
                    window.removeEventListener('beforeunload', this._cleanupFullscreen);
                    document.removeEventListener('livewire:navigating', this._cleanupFullscreen);
                }
                if (this._resizeObserver) {
                    this._resizeObserver.disconnect();
                    this._resizeObserver = null;
                }
                if (this.fileRobotInstance) {
                    this.closeImageEditor();
                }
                const engine = this.$refs && this.$refs.container3d ? this.$refs.container3d._engine : null;
                if (engine && typeof engine.dispose === 'function') {
                    engine.dispose();
                    this.$refs.container3d._engine = null;
                }
            },

            getEffectiveTextCurve(item, index = null) {
                if (
                    this.isAdjustingCurve &&
                    this.liveCurveValue !== null &&
                    this.selectedType === 'text' &&
                    item &&
                    (
                        (index !== null && Number(this.selectedIndex) === Number(index)) ||
                        (this.currentTexts && this.currentTexts[this.selectedIndex] && this.currentTexts[this.selectedIndex].id === item.id)
                    )
                ) {
                    return Number(this.liveCurveValue);
                }
                return Number((item && item.curve) || 0);
            },

            isTextCurved(item, index = null) {
                return Boolean(item && Math.abs(this.getEffectiveTextCurve(item, index)) >= 0.5 && item.text && String(item.text).trim() !== '');
            },

            computeCurvedTextLayout(item, overrideFontSizePx = null, overrideCurve = null) {
                if (!item || !item.text || String(item.text).trim() === '') {
                    return { chars: [], widthPx: 120, heightPx: 36 };
                }

                const rawCurve = overrideCurve !== null && overrideCurve !== undefined
                    ? Number(overrideCurve)
                    : this.getEffectiveTextCurve(item);
                const curve = Math.max(-100, Math.min(100, rawCurve));
                const fontSizePx = Number((overrideFontSizePx || this.getEffectiveTextareaFontSizePx(item)).toFixed(2));
                const fontFamily = (this.fontMap && this.fontMap[item.font]) ? this.fontMap[item.font] : 'Arial';
                const rawText = String(item.text);

                if (!this._charMeasureCache) {
                    this._charMeasureCache = new Map();
                }
                if (!this._curvedLayoutCache) {
                    this._curvedLayoutCache = new Map();
                }

                const measureKey = fontFamily + '|' + fontSizePx + '|' + rawText;
                const layoutKey = measureKey + '|' + curve;

                const cachedLayout = this._curvedLayoutCache.get(layoutKey);
                if (cachedLayout) {
                    return cachedLayout;
                }

                let measured = this._charMeasureCache.get(measureKey);
                if (!measured) {
                    if (!this._measureCanvas) {
                        this._measureCanvas = document.createElement('canvas');
                    }
                    const ctx = this._measureCanvas.getContext('2d');
                    if (ctx) {
                        ctx.font = 'bold ' + fontSizePx + 'px ' + fontFamily;
                    }

                    const measure = (str) => {
                        if (!str) return 0;
                        if (!ctx) return str.length * fontSizePx * 0.58;
                        return ctx.measureText(str).width;
                    };

                    const textLines = rawText.split('\n');
                    const lineWidths = textLines.map(line => measure(line || ' '));
                    const maxLineW = Math.max(fontSizePx * 1.5, ...lineWidths);
                    const lineHeight = fontSizePx * 1.15;

                    const lineEntries = [];
                    textLines.forEach((line, lineIdx) => {
                        const charsArr = Array.from(line || '');
                        if (charsArr.length === 0) return;

                        const lineW = lineWidths[lineIdx] || 0;
                        const deltaY = (lineIdx - (textLines.length - 1) / 2) * lineHeight;
                        const charOffsets = [];

                        let prefixStr = '';
                        charsArr.forEach((ch) => {
                            const prefixW = measure(prefixStr);
                            const charW = measure(ch);
                            prefixStr += ch;
                            charOffsets.push({
                                ch,
                                u: prefixW + (charW / 2) - (lineW / 2),
                            });
                        });

                        lineEntries.push({ deltaY, charOffsets });
                    });

                    measured = { maxLineW, lineEntries };
                    if (this._charMeasureCache.size > 150) {
                        this._charMeasureCache.clear();
                    }
                    this._charMeasureCache.set(measureKey, measured);
                }

                const thetaMax = (Math.abs(curve) / 100) * Math.PI;
                const rBase = measured.maxLineW / Math.max(0.01, thetaMax);
                const cy = (curve > 0 ? 1 : -1) * rBase * ((1 + Math.cos(thetaMax / 2)) / 2);

                const chars = [];
                let maxAbsX = fontSizePx;
                let maxAbsY = fontSizePx * 0.7;

                for (let i = 0; i < measured.lineEntries.length; i++) {
                    const entry = measured.lineEntries[i];
                    const rLine = Math.max(fontSizePx * 0.8, curve > 0 ? (rBase - entry.deltaY) : (rBase + entry.deltaY));
                    const offsets = entry.charOffsets;

                    for (let j = 0; j < offsets.length; j++) {
                        const { ch, u } = offsets[j];
                        const alpha = u / rLine;
                        const x = rLine * Math.sin(alpha);
                        const y = (curve > 0) ? (cy - rLine * Math.cos(alpha)) : (cy + rLine * Math.cos(alpha));
                        const deg = ((curve > 0) ? alpha : -alpha) * (180 / Math.PI);

                        chars.push({
                            char: ch,
                            x: Number(x.toFixed(2)),
                            y: Number(y.toFixed(2)),
                            deg: Number(deg.toFixed(2)),
                            fontSizePx,
                        });

                        const ax = Math.abs(x) + fontSizePx * 0.65;
                        const ay = Math.abs(y) + fontSizePx * 0.72;
                        if (ax > maxAbsX) maxAbsX = ax;
                        if (ay > maxAbsY) maxAbsY = ay;
                    }
                }

                const widthPx = Math.max(60, Math.ceil(maxAbsX * 2) + 24);
                const heightPx = Math.max(32, Math.ceil(maxAbsY * 2) + 16);

                const result = { chars, widthPx, heightPx };
                if (this._curvedLayoutCache.size > 300) {
                    this._curvedLayoutCache.clear();
                }
                this._curvedLayoutCache.set(layoutKey, result);
                return result;
            },

            getCurvedTextChars(item, index = null) {
                if (!this.isTextCurved(item, index)) return [];
                return this.computeCurvedTextLayout(item, null, this.getEffectiveTextCurve(item, index)).chars;
            },

            ensureTextDims(item, force = false) {
                if (!item || !item.id) {
                    return { width: '120px', height: '36px' };
                }
                if (!this._textDimsMap) {
                    this._textDimsMap = new Map();
                }
                const rawDims = (window.Alpine && typeof window.Alpine.raw === 'function')
                    ? window.Alpine.raw(this.textDims)
                    : this.textDims;

                // Während der Bogen-Schieberegler aktiv gezogen wird, die Box-Größe konstant halten,
                // damit keinerlei Layout-Reflow oder Backdrop-Blur-Reallokation über dem Hintergrundvideo ausgelöst wird.
                const existing = this._textDimsMap.get(item.id) || (rawDims && rawDims[item.id]);
                if (this.isAdjustingCurve && existing && existing.width && existing.height && !force) {
                    return existing;
                }

                const curCurve = Number(item.curve || 0);
                const curScale = Number((this.scaleFactor || 1).toFixed(4));
                if (
                    !force &&
                    existing &&
                    existing.width &&
                    existing.height &&
                    existing._text === String(item.text || '') &&
                    existing._size === Number(item.size || 0) &&
                    existing._font === String(item.font || 'Arial') &&
                    existing._curve === curCurve &&
                    existing._scale === curScale
                ) {
                    return existing;
                }

                const hasText = item.text !== undefined && item.text !== null && String(item.text).length > 0;
                const fontSizePx = this.getEffectiveTextareaFontSizePx(item);

                if (hasText && Math.abs(curCurve) >= 0.5 && String(item.text).trim() !== '') {
                    const curvedLayout = this.computeCurvedTextLayout(item, fontSizePx, curCurve);
                    const computed = {
                        width: curvedLayout.widthPx + 'px',
                        height: curvedLayout.heightPx + 'px',
                        _text: String(item.text || ''),
                        _size: Number(item.size || 0),
                        _font: String(item.font || 'Arial'),
                        _curve: curCurve,
                        _scale: curScale,
                    };
                    this._textDimsMap.set(item.id, computed);
                    if (rawDims) rawDims[item.id] = computed;
                    return computed;
                }

                const fontFamily = (this.fontMap && this.fontMap[item.font]) ? this.fontMap[item.font] : 'Arial';
                const rawText = hasText ? String(item.text) : 'Text eingeben...';
                const textLines = rawText.split('\n');
                const lines = textLines.length || 1;

                let canvasWidth = 60;
                let canvasHeight = Math.ceil(lines * fontSizePx * 1.32);
                try {
                    if (!this._measureCanvas) {
                        this._measureCanvas = document.createElement('canvas');
                    }
                    const ctx = this._measureCanvas.getContext('2d');
                    if (ctx) {
                        ctx.font = 'bold ' + fontSizePx + 'px ' + fontFamily;
                        let maxLineW = 0;
                        textLines.forEach(line => {
                            const w = ctx.measureText(line || ' ').width;
                            if (w > maxLineW) maxLineW = w;
                        });
                        canvasWidth = Math.ceil(maxLineW);
                    }
                } catch (e) {}

                let widthPx = Math.max(60, canvasWidth + 24);
                if (!hasText) {
                    widthPx = Math.min(widthPx, this.getMaxEmptyTextWidthPx());
                }
                const finalWidth = widthPx + 'px';
                const finalHeight = Math.max(32, canvasHeight + 14) + 'px';
                const computed = {
                    width: finalWidth,
                    height: finalHeight,
                    _text: String(item.text || ''),
                    _size: Number(item.size || 0),
                    _font: String(item.font || 'Arial'),
                    _curve: curCurve,
                    _scale: curScale,
                };
                this._textDimsMap.set(item.id, computed);
                if (rawDims) rawDims[item.id] = computed;
                return computed;
            },

            switchSide(side) {
                if (this.activeSide === side) return;
                this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;

                const backTexts = this.normalizeArray(this.texts_back);
                const backLogos = this.normalizeArray(this.logos_back);

                if (side === 'back' && backTexts.length === 0 && backLogos.length === 0 && this.context !== 'preview') {
                    const newId = Math.random().toString(36).substr(2, 9);
                    const newItem = {
                        id: newId,
                        text: '',
                        size: this.getDefaultStudioTextSize(),
                        x: this.areaCenterX,
                        y: this.areaCenterY,
                        rotation: 0,
                        curve: 0,
                        align: 'center',
                        font: this.getPreferredFont()
                    };
                    this.ensureTextDims(newItem);
                    this.texts_back = [newItem];
                    this._keepSelectionOnSideSwitch = true;
                    this.selectedType = 'text';
                    this.selectedIndex = 0;
                    this.focusedTextId = null;
                    this.activeSide = 'back';
                    this.recordHistory();
                    return;
                }

                const targetTexts = side === 'back' ? backTexts : this.normalizeArray(this.texts);
                targetTexts.forEach(t => this.ensureTextDims(t));

                this.selectedType = null;
                this.selectedIndex = null;
                this.focusedTextId = null;
                this.activeSide = side;
            },

            addFallbackText() {
                this.addNewTextElement();
            },

            addNewTextElement() {
                if (this.context === 'preview' || this.config.isBeginnerMode) return;
                this.showDrawingBoard = true;

                const tName = this.activeSide === 'back' ? 'texts_back' : 'texts';
                let currentList = JSON.parse(JSON.stringify(this.normalizeArray(this[tName])));
                currentList = Array.isArray(currentList) ? currentList : Object.values(currentList || {});

                // Falls bereits ein leeres Textfeld auf der aktiven Seite existiert, dieses direkt fokussieren
                const emptyIdx = currentList.findIndex(t => !t || !t.text || String(t.text).trim() === '');
                if (emptyIdx !== -1 && currentList[emptyIdx]) {
                    const existingId = currentList[emptyIdx].id || Math.random().toString(36).substr(2, 9);
                    currentList[emptyIdx].id = existingId;
                    this[tName] = currentList;
                    this.selectItem('text', emptyIdx);
                    this.focusedTextId = existingId;
                    this.$nextTick(() => {
                        const root = this.$root || document;
                        const ta = root.querySelector(`textarea[data-id="${existingId}"]`);
                        if (ta) ta.focus();
                    });
                    return;
                }

                // Neues Textfeld anlegen (leicht versetzt, falls im Zentrum schon ein Text liegt)
                const newId = Math.random().toString(36).substr(2, 9);
                let targetX = this.areaCenterX;
                let targetY = this.areaCenterY;
                if (currentList.length > 0) {
                    const offsetStep = Math.min(3, currentList.length) * 6;
                    targetY = Math.min(82, this.areaCenterY + offsetStep);
                }

                let maxZ = 30;
                currentList.forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (30 + idx);
                    if (z > maxZ) maxZ = z;
                });
                (this.currentLogos || []).forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (10 + idx);
                    if (z > maxZ) maxZ = z;
                });

                const newItem = {
                    id: newId,
                    text: '',
                    size: this.getDefaultStudioTextSize(),
                    x: targetX,
                    y: targetY,
                    rotation: 0,
                    curve: 0,
                    align: 'center',
                    font: this.getPreferredFont(),
                    zIndex: Math.min(95, maxZ + 1)
                };
                this.ensureTextDims(newItem);
                currentList.push(newItem);
                this[tName] = currentList;
                this.selectItem('text', currentList.length - 1);
                this.focusedTextId = newId;
                this.$nextTick(() => {
                    this.refitAllTextareas();
                    const root = this.$root || document;
                    const ta = root.querySelector(`textarea[data-id="${newId}"]`);
                    if (ta) ta.focus();
                });
                this.updateTexture();
                this.recordHistory();
            },

            saveDesign() {
                this.saveDraft();
            },

            toggleTab(tab) {
                this.activeTab = (this.activeTab === tab) ? null : tab;
            },

            saveDraft() {
                const draft = {
                    texts: JSON.parse(JSON.stringify(this.normalizeArray(this.texts))),
                    logos: JSON.parse(JSON.stringify(this.normalizeArray(this.logos))),
                    texts_back: JSON.parse(JSON.stringify(this.normalizeArray(this.texts_back))),
                    logos_back: JSON.parse(JSON.stringify(this.normalizeArray(this.logos_back))),
                    notes: (this.$wire && typeof this.$wire.notes !== 'undefined') ? this.$wire.notes : '',
                    uploaded_files: (this.$wire && Array.isArray(this.$wire.uploaded_files)) ? this.$wire.uploaded_files : []
                };

                try {
                    localStorage.setItem(this.storageDraftKey, JSON.stringify(draft));
                    this.draftExists = true;
                    this.hasSavedDesign = true;
                    this.isDraftSaving = true;
                    setTimeout(() => { this.isDraftSaving = false; }, 2500);
                    this.$dispatch('notify', {message: 'Dein Design wurde erfolgreich auf deinem Gerät gespeichert!'});
                } catch(e) {
                    console.error("Fehler beim Speichern des Entwurfs:", e);
                }
            },

            loadDraft() {
                const draftStr = localStorage.getItem(this.storageDraftKey);
                if (draftStr) {
                    try {
                        const draft = JSON.parse(draftStr);
                        if (draft.texts) this.texts = this.normalizeArray(draft.texts);
                        if (draft.logos) this.logos = this.normalizeArray(draft.logos);
                        if (draft.texts_back) this.texts_back = this.normalizeArray(draft.texts_back);
                        if (draft.logos_back) this.logos_back = this.normalizeArray(draft.logos_back);

                        if (this.$wire) {
                            if (draft.texts) this.$wire.texts = this.texts;
                            if (draft.logos) this.$wire.logos = this.logos;
                            if (draft.texts_back) this.$wire.texts_back = this.texts_back;
                            if (draft.logos_back) this.$wire.logos_back = this.logos_back;
                            if (typeof draft.notes !== 'undefined') this.$wire.notes = draft.notes;
                            if (Array.isArray(draft.uploaded_files)) this.$wire.restoreDraftFiles(draft.uploaded_files);
                        }

                        this.$nextTick(() => {
                            this.updateScaleFactor(null, true);
                            this.updateTexture();
                            this.recordHistory(true);
                        });

                        this.isDraftLoading = true;
                        setTimeout(() => { this.isDraftLoading = false; }, 2500);
                        this.$dispatch('notify', {message: 'Gespeichertes Design erfolgreich geladen!'});
                    } catch(e) {
                        console.error("Fehler beim Laden des Entwurfs:", e);
                    }
                }
            },

            handleDrop(e) {
                this.isDropping = false;
                this.uploadError = null;
                const files = e.dataTransfer ? e.dataTransfer.files : null;
                if (!files || !this.validateFiles(files)) return;
                if (this.$refs.fileInput) {
                    this.$refs.fileInput.files = files;
                    this.$refs.fileInput.dispatchEvent(new Event('change'));
                }
            },

            handleInput(e) {
                this.uploadError = null;
                const files = e.target.files;
                if (!files || !this.validateFiles(files)) {
                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                    return;
                }
            },

            validateFiles(files) {
                const currentCount = (this.$wire && Array.isArray(this.$wire.uploaded_files)) ? this.$wire.uploaded_files.length : 0;
                const newCount = files ? files.length : 0;
                if ((currentCount + newCount) > 10) {
                    this.uploadError = 'Limit erreicht! Maximal 10 Dateien erlaubt.';
                    return false;
                }
                for (let i = 0; i < files.length; i++) {
                    const f = files[i];
                    if (f.size > 20 * 1024 * 1024) {
                        this.uploadError = 'Die Datei "' + f.name + '" ist zu groß! Maximal 20 MB pro Datei sind erlaubt.';
                        return false;
                    }
                }
                return true;
            },

            resolveBeginnerSymbolRelPath(rawVal) {
                const v = String(rawVal || '').trim().toLowerCase();
                if (!v || ['kein symbol', 'keine', 'nein', '-'].includes(v)) return null;
                const map = {
                    'herz': 'Liebe/herz.svg',
                    'pfote': 'Tiere/pfotenabdruck.svg',
                    'unendlichkeit': 'Liebe/unendlichkeit.svg',
                    'stern': 'Symbole/stern.svg',
                    'schutzengel': 'Symbole/schutzengel.svg',
                    'engel': 'Symbole/schutzengel.svg',
                    'lebensbaum': 'Natur/lebensbaum.svg',
                    'baum': 'Natur/lebensbaum.svg',
                    'kleeblatt': 'Symbole/kleeblatt.svg',
                    'klee': 'Symbole/kleeblatt.svg',
                    'krone': 'Symbole/krone.svg',
                    'anker': 'Symbole/anker.svg'
                };
                for (const [key, rel] of Object.entries(map)) {
                    if (v.startsWith(key) || v.includes(key)) return rel;
                }
                return null;
            },

            formatBeginnerTextForStage(rawText) {
                const normalized = String(rawText || '').replace(/\r\n|\r/g, '\n').trim();
                if (!normalized) return '';
                const maxCharsPerLine = Math.max(12, Math.min(28, Math.round(this.getWorkingAreaWidthPercent() * 0.56)));
                const rawLines = normalized.split('\n');
                const wrapped = [];

                rawLines.forEach(line => {
                    const trimmed = line.trim();
                    if (!trimmed) return;
                    if (trimmed.length > maxCharsPerLine && trimmed.includes(' ')) {
                        const words = trimmed.split(/\s+/);
                        let current = '';
                        words.forEach(w => {
                            if (!current) {
                                current = w;
                            } else if ((current.length + 1 + w.length) <= maxCharsPerLine) {
                                current += ' ' + w;
                            } else {
                                wrapped.push(current);
                                current = w;
                            }
                        });
                        if (current) wrapped.push(current);
                    } else {
                        wrapped.push(trimmed);
                    }
                });

                return wrapped.slice(0, 5).join('\n');
            },

            syncBeginnerInputsToStage(forSave = false, explicitFont = null) {
                if (!this.config || !this.config.isBeginnerMode || this.context === 'preview') return;
                const containerEl = document.querySelector('[data-custom-fields-container]')
                    || (this.$refs && this.$refs.stage ? this.$refs.stage.closest('[x-data]') : null)
                    || document;
                const inputs = containerEl.querySelectorAll('[data-custom-field-id]');
                if (!inputs || inputs.length === 0) return;

                const coords = this.config.beginnerCoords || {
                    symbol_x: this.areaCenterX,
                    symbol_y: 38,
                    symbol_size: 80,
                    text_x: this.areaCenterX,
                    text_y: 64,
                    text_size: 0.95,
                    center_x: this.areaCenterX,
                    center_y: this.areaCenterY
                };

                let chosenFont = explicitFont || 'Arial';
                let matchedSymbolRel = null;
                let textLines = [];
                let fallbackPlaceholder = 'Dein Wunschtext';

                inputs.forEach(el => {
                    const fType = el.getAttribute('data-custom-field-type') || 'text';
                    const fRole = el.getAttribute('data-custom-field-role') || '';
                    const rawVal = String(el.value || '').replace(/\r\n|\r/g, '\n').trim();

                    if (fType === 'font') {
                        if (!explicitFont && rawVal) chosenFont = rawVal;
                        return;
                    }
                    if (fType === 'select' && fRole === 'symbol') {
                        matchedSymbolRel = this.resolveBeginnerSymbolRelPath(rawVal);
                        return;
                    }

                    let cleanVal = this.filterEmojis(rawVal).trim();
                    if (fType === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(cleanVal)) {
                        const parts = cleanVal.split('-');
                        cleanVal = `${parts[2]}.${parts[1]}.${parts[0]}`;
                    }

                    if (cleanVal && !['kein symbol', 'keine', 'nein', '-'].includes(cleanVal.toLowerCase())) {
                        const maybeSym = (fType === 'select' && !matchedSymbolRel) ? this.resolveBeginnerSymbolRelPath(cleanVal) : null;
                        if (maybeSym) {
                            matchedSymbolRel = maybeSym;
                        } else {
                            textLines.push(cleanVal);
                        }
                    } else if (el.getAttribute('data-custom-field-required') === '1') {
                        const ph = String(el.getAttribute('data-custom-field-placeholder') || '').replace(/^z\.\s*B\.\s*/i, '').trim();
                        if (ph && fallbackPlaceholder === 'Dein Wunschtext') {
                            fallbackPlaceholder = ph;
                        }
                    }
                });

                if (matchedSymbolRel) {
                    const vectorVal = 'vectors/' + matchedSymbolRel;
                    const vectorUrl = window.location.origin + '/storage/produkte/configurator/vectors/' + matchedSymbolRel;
                    const targetSymX = Number(coords.symbol_x ?? this.areaCenterX);
                    const targetSymY = Number(coords.symbol_y ?? 38);
                    const targetSymSize = Number(coords.symbol_size ?? 80);
                    const currentLogosList = this.normalizeArray(this.logos);

                    if (
                        currentLogosList.length !== 1 ||
                        currentLogosList[0]?.value !== vectorVal ||
                        Number(currentLogosList[0]?.x) !== targetSymX ||
                        Number(currentLogosList[0]?.y) !== targetSymY ||
                        Number(currentLogosList[0]?.size) !== targetSymSize
                    ) {
                        this.logos = [{
                            id: 'beginner_symbol_1',
                            type: 'vector',
                            value: vectorVal,
                            url: vectorUrl,
                            x: targetSymX,
                            y: targetSymY,
                            size: targetSymSize,
                            rotation: 0
                        }];
                    }
                } else if (this.normalizeArray(this.logos).length > 0) {
                    this.logos = [];
                }

                const rawDisplayText = textLines.length > 0 ? textLines.join('\n') : (forSave ? '' : fallbackPlaceholder);
                const displayText = this.formatBeginnerTextForStage(rawDisplayText);
                if (displayText) {
                    const lineCount = displayText.split('\n').length;
                    const baseSize = Number(coords.text_size ?? 0.95);
                    const effectiveSize = lineCount === 2
                        ? Math.round(baseSize * 0.90 * 100) / 100
                        : (lineCount >= 3 ? Math.round(baseSize * 0.80 * 100) / 100 : baseSize);
                    const targetTextX = Number(coords.text_x ?? this.areaCenterX);
                    const targetTextY = Number(matchedSymbolRel ? (coords.text_y ?? 64) : (coords.center_y ?? this.areaCenterY));
                    const currentTextsList = this.normalizeArray(this.texts);

                    if (currentTextsList.length === 1 && currentTextsList[0] && this.texts && this.texts[0]) {
                        if (this.texts[0].text !== displayText) this.texts[0].text = displayText;
                        if (this.texts[0].font !== chosenFont) this.texts[0].font = chosenFont;
                        if (Number(this.texts[0].x) !== targetTextX) this.texts[0].x = targetTextX;
                        if (Number(this.texts[0].y) !== targetTextY) this.texts[0].y = targetTextY;
                        if (Number(this.texts[0].size) !== effectiveSize) this.texts[0].size = effectiveSize;
                    } else {
                        this.texts = [{
                            id: 'beginner_text_1',
                            text: displayText,
                            font: chosenFont,
                            align: 'center',
                            x: targetTextX,
                            y: targetTextY,
                            size: effectiveSize,
                            rotation: 0
                        }];
                    }
                } else if (this.normalizeArray(this.texts).length > 0) {
                    this.texts = [];
                }

                this.updateTexture();
            },

            syncStageTextareasToState() {
                if (this.context === 'preview') return;
                if (this.config && this.config.isBeginnerMode) {
                    this.syncBeginnerInputsToStage(true);
                    return;
                }
                const rootEl = this.$root || document;
                const textareas = rootEl.querySelectorAll('textarea[data-id]');
                if (!textareas || textareas.length === 0) return;

                const frontList = this.normalizeArray(this.texts);
                const backList = this.normalizeArray(this.texts_back);
                let frontChanged = false;
                let backChanged = false;

                textareas.forEach(ta => {
                    const id = ta.getAttribute('data-id');
                    if (!id) return;
                    const val = this.filterEmojis(ta.value || '');
                    const fItem = frontList.find(t => t && String(t.id) === String(id));
                    if (fItem && fItem.text !== val) {
                        fItem.text = val;
                        frontChanged = true;
                    }
                    const bItem = backList.find(t => t && String(t.id) === String(id));
                    if (bItem && bItem.text !== val) {
                        bItem.text = val;
                        backChanged = true;
                    }
                });

                if (frontChanged) {
                    this.texts = JSON.parse(JSON.stringify(frontList));
                }
                if (backChanged) {
                    this.texts_back = JSON.parse(JSON.stringify(backList));
                }
            },

            // NEU: Snapshot Capture vor dem Speichern in den Warenkorb mit Doppel-Klick Schutz & Mobile-Timeout
            async submitConfig() {
                // 0. Prüfen, ob der Bestätigungs-Haken gesetzt wurde (Task 2.4)
                if (this.context !== 'calculator' && this.$wire && !this.$wire.config_confirmed) {
                    this.confirmWarning = true;
                    if (this.$refs && this.$refs.confirmBox) {
                        this.$refs.confirmBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
                this.confirmWarning = false;

                // 1. Doppel-Klick / Mehrfach-Klick Schutz!
                if (this.isSaving) return;
                this.isSaving = true;

                let snapshotBase64 = null;
                let snapshotBackBase64 = null;

                try {
                    this.flushPendingCurve();
                    this.syncStageTextareasToState();
                    this.focusedTextId = null;
                    this.selectedIndex = null;
                    this.selectedType = null;
                    this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;

                    // Snapshot-Erstellung für den 2D/3D Studio-Konfigurator sowie den Neulings-Modus auf Studio-Produkten ausführen
                    if (this.config.isPersonalizable !== false && (this.config.personalizationMode !== 'custom_fields' || this.config.isBeginnerMode)) {
                        try {
                            const timeoutPromise = new Promise(resolve => setTimeout(resolve, 3500));

                            const capturePromise = (async () => {
                            let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                            if (this.config.modelPath && !engine) {
                                if (typeof this._forceStart3D === 'function') this._forceStart3D();
                                await new Promise(resolve => {
                                    let elapsed = 0;
                                    let checkInterval = setInterval(() => {
                                        elapsed += 100;
                                        engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                                        if (engine || elapsed >= 2000) {
                                            clearInterval(checkInterval);
                                            resolve();
                                        }
                                    }, 100);
                                });
                            }

                            if (this.config.modelPath && engine) {
                                // SICHERSTELLEN DASS DAS 3D MODEL GELADEN IST (mit maximal 2 Sekunden Wartezeit!)
                                if (!this.modelLoaded) {
                                    if (typeof this._forceStart3D === 'function') this._forceStart3D();
                                    await new Promise(resolve => {
                                        let elapsed = 0;
                                        let checkInterval = setInterval(() => {
                                            elapsed += 100;
                                            if (this.modelLoaded || elapsed >= 2000) {
                                                clearInterval(checkInterval);
                                                resolve();
                                            }
                                        }, 100);
                                    });
                                }

                                if (this.modelLoaded && engine.renderer && engine.scene && engine.camera) {
                                    // TEXTUREN ERZWINGEN ZU RENDERN (Synchron für sofortigen GPU-Upload beim nächsten Render-Aufruf)
                                    try {
                                        engine.renderBothCanvases(
                                            this.normalizeArray(this.texts),
                                            this.normalizeArray(this.logos),
                                            this.normalizeArray(this.texts_back),
                                            this.normalizeArray(this.logos_back),
                                            Alpine.raw(this.fontMap)
                                        );
                                    } catch (e) { console.error("Force Render failed:", e); }

                                    const backTextsList = this.normalizeArray(this.texts_back);
                                    const backLogosList = this.normalizeArray(this.logos_back);
                                    const hasBackContent = this.config.has_back_side && (
                                        backTextsList.some(t => t && t.text && String(t.text).trim() !== '') ||
                                        backLogosList.length > 0
                                    );

                                    if (hasBackContent) {
                                        const origPos = engine.camera.position.clone();
                                        const origTarget = engine.controls.target.clone();

                                        if (engine.defaultCamPos && engine.defaultCamTarget) {
                                            engine.camera.position.copy(engine.defaultCamPos);
                                            engine.controls.target.copy(engine.defaultCamTarget);
                                        } else {
                                            engine.camera.position.set(0, 0, Math.abs(engine.camera.position.z));
                                            engine.controls.target.set(0, 0, 0);
                                        }

                                        engine.controls.update();
                                        if (typeof engine.captureCompositeSnapshot === 'function') {
                                            snapshotBase64 = engine.captureCompositeSnapshot(this.$refs.bgVideo);
                                        } else {
                                            const prevSceneBg = engine.scene.background;
                                            if (engine._envTexture) engine.scene.background = engine._envTexture;
                                            engine.renderer.render(engine.scene, engine.camera);
                                            snapshotBase64 = engine.renderer.domElement.toDataURL('image/jpeg', 0.85);
                                            engine.scene.background = prevSceneBg;
                                        }

                                        // Back Snapshot (Rückseite inkl. Hintergrundbild/-video)
                                        if (engine.defaultCamPos) {
                                            engine.camera.position.copy(engine.defaultCamPos);
                                            engine.camera.position.z *= -1;
                                            engine.camera.position.x *= -1;
                                        } else {
                                            engine.camera.position.set(0, 0, -Math.abs(engine.camera.position.z));
                                        }

                                        engine.controls.update();
                                        if (typeof engine.captureCompositeSnapshot === 'function') {
                                            snapshotBackBase64 = engine.captureCompositeSnapshot(this.$refs.bgVideo);
                                        } else {
                                            const prevSceneBg = engine.scene.background;
                                            if (engine._envTexture) engine.scene.background = engine._envTexture;
                                            engine.renderer.render(engine.scene, engine.camera);
                                            snapshotBackBase64 = engine.renderer.domElement.toDataURL('image/jpeg', 0.85);
                                            engine.scene.background = prevSceneBg;
                                        }

                                        // Restore und sofort synchron neu rendern, damit die Bühne niemals zuckt!
                                        engine.camera.position.copy(origPos);
                                        engine.controls.target.copy(origTarget);
                                        engine.controls.update();
                                        engine.renderer.render(engine.scene, engine.camera);
                                    } else {
                                        const origPos = engine.camera.position.clone();
                                        const origTarget = engine.controls.target.clone();

                                        if (engine.defaultCamPos && engine.defaultCamTarget) {
                                            engine.camera.position.copy(engine.defaultCamPos);
                                            engine.controls.target.copy(engine.defaultCamTarget);
                                            engine.controls.update();
                                        }

                                        if (typeof engine.captureCompositeSnapshot === 'function') {
                                            snapshotBase64 = engine.captureCompositeSnapshot(this.$refs.bgVideo);
                                        } else {
                                            const prevSceneBg = engine.scene.background;
                                            if (engine._envTexture) engine.scene.background = engine._envTexture;
                                            engine.renderer.render(engine.scene, engine.camera);
                                            snapshotBase64 = engine.renderer.domElement.toDataURL('image/jpeg', 0.85);
                                            engine.scene.background = prevSceneBg;
                                        }

                                        engine.camera.position.copy(origPos);
                                        engine.controls.target.copy(origTarget);
                                        engine.controls.update();
                                        engine.renderer.render(engine.scene, engine.camera);
                                    }
                                }
                            } else {
                                // 2D-Fallback-Modus (Offscreen-Clone via html2canvas ohne Live-DOM-Flackern)
                                if (!window.html2canvas) {
                                    await new Promise((resolve) => {
                                        const script = document.createElement('script');
                                        script.src = '{{ asset('vendor/html2canvas/html2canvas.min.js') }}';
                                        script.onload = resolve;
                                        script.onerror = resolve;
                                        document.head.appendChild(script);
                                    });
                                }

                                if (window.html2canvas) {
                                    const containerToCapture = this.$refs.container || document.querySelector('.configurator-2d-preview');
                                    if (containerToCapture) {
                                        const h2cOpts = {
                                            useCORS: true,
                                            allowTaint: false,
                                            backgroundColor: '#ffffff',
                                            scale: 1.5,
                                            ignoreElements: (el) => Boolean(el && el.hasAttribute && el.hasAttribute('data-snapshot-ignore')),
                                            onclone: (clonedDoc) => {
                                                clonedDoc.querySelectorAll('textarea[data-id]').forEach(ta => {
                                                    if (ta.getAttribute('data-curved') === '1') {
                                                        ta.remove();
                                                        return;
                                                    }
                                                    const div = clonedDoc.createElement('div');
                                                    div.textContent = ta.value || '';
                                                    div.className = ta.className;
                                                    div.style.cssText = ta.style.cssText;
                                                    div.style.height = 'auto';
                                                    div.style.overflow = 'visible';
                                                    if (ta.parentNode) ta.parentNode.replaceChild(div, ta);
                                                });
                                            }
                                        };

                                        const hasBack2D = this.config.has_back_side && (
                                            (this.texts_back && this.texts_back.some(t => t && t.text && String(t.text).trim() !== '')) ||
                                            (this.logos_back && this.logos_back.length > 0)
                                        );

                                        if (hasBack2D) {
                                            let origSide = this.activeSide;

                                            this.activeSide = 'front';
                                            await new Promise(r => setTimeout(r, 80));
                                            let canvasF = await window.html2canvas(containerToCapture, h2cOpts);
                                            snapshotBase64 = canvasF.toDataURL('image/jpeg', 0.85);

                                            this.activeSide = 'back';
                                            await new Promise(r => setTimeout(r, 80));
                                            let canvasB = await window.html2canvas(containerToCapture, h2cOpts);
                                            snapshotBackBase64 = canvasB.toDataURL('image/jpeg', 0.85);

                                            this.activeSide = origSide;
                                        } else {
                                            let canvas = await window.html2canvas(containerToCapture, h2cOpts);
                                            snapshotBase64 = canvas.toDataURL('image/jpeg', 0.85);
                                        }
                                    }
                                }
                            }
                        })();

                            await Promise.race([capturePromise, timeoutPromise]);
                        } catch(captureErr) {
                            console.error("Konnte keinen Snapshot erstellen:", captureErr);
                        } finally {
                            this.isCapturing = false;
                        }
                    }

                    // Vor dem Speichern alle dynamischen Eingabefelder synchronisieren (verhindert Debounce-Verlust bei schnellem Klick)
                    if (this.config.personalizationMode === 'custom_fields') {
                        const containerEl = document.querySelector('[data-custom-fields-container]') || this.$root || document;
                        containerEl.querySelectorAll('[data-custom-field-id]').forEach(el => {
                            const cfId = el.getAttribute('data-custom-field-id');
                            if (cfId) {
                                $wire.set('customFieldValues.' + cfId, el.value ?? '', false);
                            }
                        });
                    }

                    // 2. An Livewire übergeben (inkl. explizitem Stage-State-Sync für In-Place-Mutationen)
                    let payload = {};
                    if (snapshotBase64) payload.front = snapshotBase64;
                    if (snapshotBackBase64) payload.back = snapshotBackBase64;

                    const syncedStageState = {
                        texts: JSON.parse(JSON.stringify(this.normalizeArray(this.texts))),
                        logos: JSON.parse(JSON.stringify(this.normalizeArray(this.logos))),
                        texts_back: JSON.parse(JSON.stringify(this.normalizeArray(this.texts_back))),
                        logos_back: JSON.parse(JSON.stringify(this.normalizeArray(this.logos_back))),
                    };

                    await $wire.saveWithSnapshot(payload, syncedStageState);
                } catch (e) {
                    console.error("Fehler beim Speichern der Konfiguration:", e);
                } finally {
                    this.isSaving = false;
                }
            },

            loadSavedDesign() {
                this.loadDraft();
            },

            triggerMessage(text) {
                this.messageText = text;
                this.showMessage = true;
                setTimeout(() => { this.showMessage = false; }, 3500);
            },

            updateTexture(forceImmediate = false) {
                let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                if (!engine || !engine.isReady || !engine.textureFront) return;

                // Im 2D-Modus (showDrawingBoard = true) ist die 3D-Bühne ausgeblendet (visibility: hidden).
                // Das aufwendige 2048x2048 3D-Canvas-Rendering erst beim Wechsel auf 3D oder beim Speichern ausführen,
                // damit die 2D-Interaktion und das Hintergrundvideo zu 100 % ruckelfrei mit 60 FPS laufen.
                if (this.showDrawingBoard && !this.isCapturing && !forceImmediate) {
                    this._needs3DTextureSync = true;
                    return;
                }
                this._needs3DTextureSync = false;

                if (this._renderTimeout) clearTimeout(this._renderTimeout);

                this._renderTimeout = setTimeout(() => {

                    if (this._isRendering) {
                        this._needsAnotherRender = true;
                        return;
                    }

                    const executeCanvasRender = () => {
                        this._isRendering = true;

                        requestAnimationFrame(() => {
                            try {
                                engine.renderBothCanvases(
                                    this.normalizeArray(this.texts),
                                    this.normalizeArray(this.logos),
                                    this.normalizeArray(this.texts_back),
                                    this.normalizeArray(this.logos_back),
                                    Alpine.raw(this.fontMap)
                                );
                            } catch (e) {
                                console.error("Render-Fehler in updateTexture:", e);
                            } finally {
                                setTimeout(() => {
                                    this._isRendering = false;
                                    if (this._needsAnotherRender) {
                                        this._needsAnotherRender = false;
                                        this.updateTexture(forceImmediate);
                                    }
                                }, 100);
                            }
                        });
                    };

                    if (window.requestIdleCallback) {
                        window.requestIdleCallback(() => executeCanvasRender(), { timeout: 500 });
                    } else {
                        executeCanvasRender();
                    }

                }, forceImmediate ? 16 : 100);
            },

            refitAllTextareas() {
                const runFit = () => {
                    const ctx = this.$root || document;
                    ctx.querySelectorAll('textarea[x-model]').forEach(el => {
                        const textId = el.getAttribute('data-id');
                        if (textId) {
                            this.fitTextarea(textId, el);
                        }
                    });
                };
                this.$nextTick(() => {
                    runFit();
                    requestAnimationFrame(() => runFit());
                });
            },

            updateScaleFactor(width = null, forceRefit = false) {
                if(!width && this.$refs.container) {
                    width = this.$refs.container.getBoundingClientRect().width;
                }
                if(!width || width <= 0) {
                    if (forceRefit) {
                        this.refitAllTextareas();
                    }
                    return;
                }

                const newScale = width / this.baseWidth;
                const hasChanged = !this.scaleFactor || Math.abs(this.scaleFactor - newScale) >= 0.002;
                this.scaleFactor = newScale;

                if (hasChanged || !this._hasInitialFittedTextareas || forceRefit) {
                    this._hasInitialFittedTextareas = true;
                    this.refitAllTextareas();
                }
            },

            onWindowResize() {
                this.updateScaleFactor();
            },

            deselectAll(e) {
                if(this.context === 'preview') return;

                if(e.target.closest('.flex.flex-wrap.items-center') ||
                    e.target.closest('.active-control-corner') ||
                    e.target.closest('.crop-control-handle') ||
                    e.target.closest('.schwebender-werkzeugkasten') ||
                    e.target.closest('textarea') ||
                    e.target.closest('#image-editor-modal') ||
                    e.target.closest('.configurator-storage-ui'))
                    return;

                this.flushPendingCurve();
                this.isCropping = false;
                this.activeCropHandle = null;
                this.selectedType = null;
                this.selectedIndex = null;
                this.focusedTextId = null;
                this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
            },

            selectItem(type, index) {
                if(this.context === 'preview' || this.config.isBeginnerMode) return;
                if (this.selectedType !== type || this.selectedIndex !== index) {
                    this.flushPendingCurve();
                    this.isCropping = false;
                    this.activeCropHandle = null;
                    this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
                }
                this.selectedType = type;
                this.selectedIndex = index;
                if (type === 'text' && this.currentTexts && this.currentTexts[index] && this.currentTexts[index].font) {
                    this.lastSelectedFont = this.currentTexts[index].font;
                }
            },

            getPreferredFont(fallback = 'Arial') {
                if (this.selectedType === 'text' && this.selectedIndex !== null && this.currentTexts && this.currentTexts[this.selectedIndex] && this.currentTexts[this.selectedIndex].font) {
                    this.lastSelectedFont = this.currentTexts[this.selectedIndex].font;
                    return this.lastSelectedFont;
                }
                if (this.lastSelectedFont && (!this.fontMap || Object.keys(this.fontMap).length === 0 || this.fontMap[this.lastSelectedFont])) {
                    return this.lastSelectedFont;
                }
                const activeList = this.currentTexts || [];
                for (let i = activeList.length - 1; i >= 0; i--) {
                    if (activeList[i] && activeList[i].font) {
                        this.lastSelectedFont = activeList[i].font;
                        return this.lastSelectedFont;
                    }
                }
                return fallback;
            },

            setTextFont(fontKey) {
                if (!fontKey) return;
                this.lastSelectedFont = fontKey;
                if (this.selectedType === 'text' && this.selectedIndex !== null && this.currentTexts && this.currentTexts[this.selectedIndex]) {
                    this.currentTexts[this.selectedIndex].font = fontKey;
                }
                this.showFontMenu = false;
                this.updateTexture();
                this.recordHistory();
            },

            getElementZIndex(type, index) {
                const list = type === 'text' ? this.currentTexts : this.currentLogos;
                const item = list ? list[index] : null;
                if (item && typeof item.zIndex === 'number' && !isNaN(item.zIndex)) {
                    return Math.max(1, Math.min(95, Math.round(item.zIndex)));
                }
                return type === 'text' ? (30 + Number(index || 0)) : (10 + Number(index || 0));
            },

            bringToFront() {
                if (this.selectedIndex === null || !this.selectedType) return;
                const tList = this.currentTexts || [];
                const lList = this.currentLogos || [];
                let maxZ = 10;
                tList.forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (30 + idx);
                    if (z > maxZ) maxZ = z;
                });
                lList.forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (10 + idx);
                    if (z > maxZ) maxZ = z;
                });
                const target = this.selectedType === 'text' ? tList[this.selectedIndex] : lList[this.selectedIndex];
                if (target) {
                    target.zIndex = Math.min(95, maxZ + 1);
                    this.updateTexture();
                    this.recordHistory();
                }
            },

            sendToBack() {
                if (this.selectedIndex === null || !this.selectedType) return;
                const tList = this.currentTexts || [];
                const lList = this.currentLogos || [];
                let minZ = 30;
                tList.forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (30 + idx);
                    if (z < minZ) minZ = z;
                });
                lList.forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (10 + idx);
                    if (z < minZ) minZ = z;
                });
                const target = this.selectedType === 'text' ? tList[this.selectedIndex] : lList[this.selectedIndex];
                if (target) {
                    if (minZ <= 2) {
                        tList.forEach((it, idx) => { it.zIndex = ((typeof it.zIndex === 'number') ? it.zIndex : (30 + idx)) + 2; });
                        lList.forEach((it, idx) => { it.zIndex = ((typeof it.zIndex === 'number') ? it.zIndex : (10 + idx)) + 2; });
                        target.zIndex = 1;
                    } else {
                        target.zIndex = minZ - 1;
                    }
                    this.updateTexture();
                    this.recordHistory();
                }
            },

            deleteSelectedItem() {
                if(this.selectedIndex === null || this.context === 'preview' || this.config.isBeginnerMode) return;

                let tName = this.activeSide === 'front' ? 'texts' : 'texts_back';
                let lName = this.activeSide === 'front' ? 'logos' : 'logos_back';

                // FIX: Vor dem Löschen alle Menüs hart schließen, damit Alpine.js keine x-models mehr berechnet!
                this.showFontMenu = false;
                this.showSizeMenu = false;
                this.showAlignMenu = false;
                this.showPosMenu = false;

                if(this.selectedType === 'text') {
                    this.focusedTextId = null;
                    let rawTexts = JSON.parse(JSON.stringify(this.normalizeArray(this[tName])));
                    let newTexts = Array.isArray(rawTexts) ? rawTexts : Object.values(rawTexts || {});
                    const idx = Number(this.selectedIndex);
                    if (!isNaN(idx) && idx >= 0 && idx < newTexts.length) {
                        newTexts.splice(idx, 1);
                    }

                    this[tName] = newTexts;
                    if (newTexts.length > 0) {
                        this.selectItem('text', Math.min(idx, newTexts.length - 1));
                    } else {
                        const activeLogos = this.normalizeArray(this[lName]);
                        if (activeLogos.length > 0) {
                            this.selectItem('logo', activeLogos.length - 1);
                        } else {
                            this.selectedType = null;
                            this.selectedIndex = null;
                        }
                    }
                    this.$nextTick(() => {
                        this.refitAllTextareas();
                    });
                } else if(this.selectedType === 'logo') {
                    let rawLogos = JSON.parse(JSON.stringify(this.normalizeArray(this[lName])));
                    let newLogos = Array.isArray(rawLogos) ? rawLogos : Object.values(rawLogos || {});
                    const idx = Number(this.selectedIndex);
                    if (!isNaN(idx) && idx >= 0 && idx < newLogos.length) {
                        newLogos.splice(idx, 1);
                    }
                    this[lName] = newLogos;
                    this.selectedType = null;
                    this.selectedIndex = null;
                }
                this.updateTexture();
                this.recordHistory();
            },

            duplicateElement() {
                if(this.selectedIndex === null) return;

                let tName = this.activeSide === 'front' ? 'texts' : 'texts_back';
                let lName = this.activeSide === 'front' ? 'logos' : 'logos_back';
                const propName = (this.selectedType === 'text') ? tName : lName;

                let list = JSON.parse(JSON.stringify(this.normalizeArray(this[propName])));
                list = Array.isArray(list) ? list : Object.values(list || {});
                const idx = Number(this.selectedIndex);
                if (isNaN(idx) || !list[idx]) return;

                let clone = JSON.parse(JSON.stringify(list[idx]));
                clone.id = Math.random().toString(36).substr(2, 9);
                clone.x = Math.min(90, (clone.x ?? this.areaCenterX) + 5);
                clone.y = Math.min(90, (clone.y ?? this.areaCenterY) + 5);

                list.push(clone);
                this[propName] = list;
                this.selectItem(this.selectedType, list.length - 1);
                this.updateTexture();
                this.recordHistory();
            },

            getTextBoxWidthPercent(item) {
                if (!item || !item.id) return 0;
                const dimW = parseFloat(this.textDims[item.id]?.width || 0);
                const containerW = (this.$refs && this.$refs.container) ? this.$refs.container.getBoundingClientRect().width : this.baseWidth;
                if (!dimW || !containerW || containerW <= 0) return 0;
                const areaWidthPct = (this.config && this.config.area_shape !== 'custom') ? (Number(this.config.area_width) || 100) : 100;
                return (dimW / containerW) * areaWidthPct;
            },

            setTextAlign(newAlign) {
                if (this.selectedType !== 'text' || this.selectedIndex === null) return;
                const item = this.currentTexts[this.selectedIndex];
                if (!item) return;

                const oldAlign = item.align || 'center';
                if (oldAlign !== newAlign) {
                    const boxW = this.getTextBoxWidthPercent(item);
                    if (boxW > 0) {
                        const visualCenterX = oldAlign === 'left'
                            ? (Number(item.x ?? this.areaCenterX) + boxW / 2)
                            : (oldAlign === 'right' ? (Number(item.x ?? this.areaCenterX) - boxW / 2) : Number(item.x ?? this.areaCenterX));
                        if (newAlign === 'left') {
                            item.x = visualCenterX - boxW / 2;
                        } else if (newAlign === 'right') {
                            item.x = visualCenterX + boxW / 2;
                        } else {
                            item.x = visualCenterX;
                        }
                    }
                    item.align = newAlign;
                }
                this.showAlignMenu = false;
                this.updateTexture();
                this.recordHistory();
            },

            previewTextCurve(newCurve) {
                if (this.selectedType !== 'text' || this.selectedIndex === null) return;
                const item = this.currentTexts[this.selectedIndex];
                if (!item) return;

                const numVal = Math.max(-100, Math.min(100, Math.round(Number(newCurve) || 0)));

                if (Math.abs(numVal) >= 1 && item.align && item.align !== 'center') {
                    const oldAlign = item.align;
                    const boxW = this.getTextBoxWidthPercent(item);
                    if (boxW > 0) {
                        item.x = oldAlign === 'left'
                            ? (Number(item.x ?? this.areaCenterX) + boxW / 2)
                            : (Number(item.x ?? this.areaCenterX) - boxW / 2);
                    }
                    item.align = 'center';
                }

                this._pendingCurveVal = numVal;
                if (this._curveRafId) return;

                this._curveRafId = requestAnimationFrame(() => {
                    this._curveRafId = null;
                    this.isAdjustingCurve = true;
                    this.liveCurveValue = this._pendingCurveVal;
                    if (!this.showDrawingBoard) {
                        const activeItem = this.currentTexts ? this.currentTexts[this.selectedIndex] : null;
                        if (activeItem) {
                            activeItem.curve = this._pendingCurveVal;
                        }
                    }
                });
            },

            flushPendingCurve() {
                if (this._curveRafId) {
                    cancelAnimationFrame(this._curveRafId);
                    this._curveRafId = null;
                }
                if (this.isAdjustingCurve && this.liveCurveValue !== null) {
                    const val = this.liveCurveValue;
                    this.isAdjustingCurve = false;
                    this.liveCurveValue = null;
                    this.setTextCurve(val, true);
                }
            },

            setTextCurve(newCurve, saveHistory = false) {
                if (this._curveRafId) {
                    cancelAnimationFrame(this._curveRafId);
                    this._curveRafId = null;
                }
                this.isAdjustingCurve = false;
                this.liveCurveValue = null;

                if (this.selectedType !== 'text' || this.selectedIndex === null) return;
                const item = this.currentTexts[this.selectedIndex];
                if (!item) return;

                const numVal = Math.max(-100, Math.min(100, Math.round(Number(newCurve) || 0)));

                if (Math.abs(numVal) >= 1 && item.align && item.align !== 'center') {
                    const oldAlign = item.align;
                    const boxW = this.getTextBoxWidthPercent(item);
                    if (boxW > 0) {
                        item.x = oldAlign === 'left'
                            ? (Number(item.x ?? this.areaCenterX) + boxW / 2)
                            : (Number(item.x ?? this.areaCenterX) - boxW / 2);
                    }
                    item.align = 'center';
                }

                item.curve = numVal;
                const dims = this.ensureTextDims(item, true);
                const root = this.$root || document;
                const ta = root.querySelector(`textarea[data-id="${item.id}"]`);
                if (ta && dims) {
                    ta.style.width = dims.width;
                    ta.style.height = dims.height;
                }
                this.updateTexture();
                if (saveHistory) {
                    this.recordHistory();
                } else {
                    this.scheduleDebouncedHistory();
                }
            },

            centerHorizontal() {
                if(this.selectedIndex === null) return;
                const target = (this.selectedType === 'text') ? this.currentTexts : this.currentLogos;
                const item = target[this.selectedIndex];
                if (!item) return;

                let targetX = this.areaCenterX;
                if (this.selectedType === 'text') {
                    const align = item.align || 'center';
                    const boxW = this.getTextBoxWidthPercent(item);
                    if (align === 'left') targetX = this.areaCenterX - (boxW / 2);
                    else if (align === 'right') targetX = this.areaCenterX + (boxW / 2);
                }
                item.x = targetX;
                this.updateTexture();
                this.recordHistory();
            },

            centerVertical() {
                if(this.selectedIndex === null) return;
                const target = (this.selectedType === 'text') ? this.currentTexts : this.currentLogos;
                if (!target[this.selectedIndex]) return;
                target[this.selectedIndex].y = this.areaCenterY;
                this.updateTexture();
                this.recordHistory();
            },

            centerBoth() {
                this.centerHorizontal();
                this.centerVertical();
            },

            // --- IN-PLACE ZUSCHNEIDEN (CROP) AUF DER 2D-BÜHNE ---
            startCropMode() {
                if (this.context === 'preview' || this.config.isBeginnerMode || this.selectedType !== 'logo' || this.selectedIndex === null) return;
                const logo = this.currentLogos[this.selectedIndex];
                if (!logo || !logo.url) return;
                this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
                this.cropBox = { top: 0, right: 0, bottom: 0, left: 0, shape: 'rect' };
                this.activeCropHandle = null;
                this.isCropping = true;
            },

            cancelCropMode() {
                this.isCropping = false;
                this.activeCropHandle = null;
            },

            getLogoClipPath() {
                if (!this.isCropping) return 'none';
                const t = Number(this.cropBox.top || 0);
                const r = Number(this.cropBox.right || 0);
                const b = Number(this.cropBox.bottom || 0);
                const l = Number(this.cropBox.left || 0);
                if (this.cropBox.shape === 'circle') {
                    const rx = Math.max(1, (100 - l - r) / 2);
                    const ry = Math.max(1, (100 - t - b) / 2);
                    const cx = l + rx;
                    const cy = t + ry;
                    return `ellipse(${rx}% ${ry}% at ${cx}% ${cy}%)`;
                }
                return `inset(${t}% ${r}% ${b}% ${l}%)`;
            },

            startCropDrag(event, handle) {
                if (!this.isCropping || this.selectedType !== 'logo' || this.selectedIndex === null) return;
                const logo = this.currentLogos[this.selectedIndex];
                if (!logo) return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const wrapper = event.currentTarget ? event.currentTarget.closest('.logo-crop-wrapper') : null;
                const wrapperRect = wrapper ? wrapper.getBoundingClientRect() : null;

                this.cropStartX = clientX;
                this.cropStartY = clientY;
                this.cropStartBox = { ...this.cropBox };
                this.cropElemW = wrapperRect ? Math.max(24, wrapperRect.width) : Math.max(24, (Number(logo.size || 100) * this.scaleFactor * (this.stageZoom || 1)));
                this.cropElemH = wrapperRect ? Math.max(24, wrapperRect.height) : this.cropElemW;
                this.cropRotation = Number(logo.rotation || 0);
                this.activeCropHandle = handle;

                if (event.cancelable) event.preventDefault();
            },

            async applyCrop() {
                if (!this.isCropping || this.selectedType !== 'logo' || this.selectedIndex === null || this.isApplyingCrop) return;
                const lName = this.activeSide === 'front' ? 'logos' : 'logos_back';
                let list = JSON.parse(JSON.stringify(this.normalizeArray(this[lName])));
                list = Array.isArray(list) ? list : Object.values(list || {});
                const idx = Number(this.selectedIndex);
                const logo = list[idx];
                if (!logo || !logo.url) {
                    this.cancelCropMode();
                    return;
                }

                const top = Math.max(0, Math.min(85, Number(this.cropBox.top || 0)));
                const right = Math.max(0, Math.min(85, Number(this.cropBox.right || 0)));
                const bottom = Math.max(0, Math.min(85, Number(this.cropBox.bottom || 0)));
                const left = Math.max(0, Math.min(85, Number(this.cropBox.left || 0)));
                const shape = this.cropBox.shape || 'rect';

                if (top < 0.5 && right < 0.5 && bottom < 0.5 && left < 0.5 && shape === 'rect') {
                    this.cancelCropMode();
                    return;
                }

                this.isApplyingCrop = true;
                try {
                    const img = await new Promise((resolve, reject) => {
                        const i = new Image();
                        if (!logo.url.startsWith('data:') && !logo.url.startsWith(window.location.origin) && !logo.url.startsWith('/')) {
                            i.crossOrigin = 'anonymous';
                        }
                        i.onload = () => resolve(i);
                        i.onerror = (e) => reject(e);
                        i.src = logo.url;
                    });

                    const natW = img.naturalWidth || img.width || 500;
                    const natH = img.naturalHeight || img.height || 500;
                    const sx = Math.round((left / 100) * natW);
                    const sy = Math.round((top / 100) * natH);
                    const sw = Math.max(2, Math.round(((100 - left - right) / 100) * natW));
                    const sh = Math.max(2, Math.round(((100 - top - bottom) / 100) * natH));

                    const canvas = document.createElement('canvas');
                    canvas.width = sw;
                    canvas.height = sh;
                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        this.isApplyingCrop = false;
                        return;
                    }

                    if (shape === 'circle') {
                        ctx.beginPath();
                        ctx.ellipse(sw / 2, sh / 2, sw / 2, sh / 2, 0, 0, Math.PI * 2);
                        ctx.closePath();
                        ctx.clip();
                    }

                    ctx.drawImage(img, sx, sy, sw, sh, 0, 0, sw, sh);
                    const b64 = canvas.toDataURL('image/png');

                    // Original-Referenz für jederzeitiges Zurücksetzen sichern
                    if (!logo.original_url) logo.original_url = logo.url;
                    if (!logo.original_value) logo.original_value = logo.value;
                    if (!logo.original_size) logo.original_size = logo.size;

                    // Exakte Positions- & Größen-Kompensation, damit das verbleibende Motiv keinen Millimeter auf der Bühne springt
                    const oldSize = Number(logo.size || 100);
                    const wPct = oldSize / 5; // (oldSize / 500) * 100
                    const hPct = wPct * (natH / natW);
                    const dxLocal = ((left - right) / 200) * wPct;
                    const dyLocal = ((top - bottom) / 200) * hPct;
                    const rad = Number(logo.rotation || 0) * Math.PI / 180;
                    const dxStage = dxLocal * Math.cos(rad) - dyLocal * Math.sin(rad);
                    const dyStage = dxLocal * Math.sin(rad) + dyLocal * Math.cos(rad);
                    const widthFraction = Math.max(0.08, (100 - left - right) / 100);

                    const oldVal = logo.value;
                    logo.x = Number((Number(logo.x ?? this.areaCenterX) + dxStage).toFixed(2));
                    logo.y = Number((Number(logo.y ?? this.areaCenterY) + dyStage).toFixed(2));
                    logo.size = Math.max(12, Number((oldSize * widthFraction).toFixed(2)));
                    logo.url = b64;

                    this[lName] = list;
                    this.isCropping = false;
                    this.activeCropHandle = null;
                    this.updateTexture();

                    if (this.$wire && oldVal) {
                        try {
                            const res = await this.$wire.saveCroppedLogo(oldVal, b64, logo.original_value || oldVal);
                            if (res && res.value && res.url) {
                                let freshList = JSON.parse(JSON.stringify(this.normalizeArray(this[lName])));
                                freshList = Array.isArray(freshList) ? freshList : Object.values(freshList || {});
                                if (freshList[idx]) {
                                    freshList[idx].value = res.value;
                                    freshList[idx].url = res.url;
                                    this[lName] = freshList;
                                    this.updateTexture();
                                }
                            }
                        } catch (err) {
                            console.error('Fehler beim Speichern des zugeschnittenen Bildes:', err);
                        }
                    }

                    this.recordHistory();
                } catch (e) {
                    console.error('Zuschnitt-Fehler:', e);
                } finally {
                    this.isApplyingCrop = false;
                }
            },

            async resetLogoToOriginal() {
                if (this.selectedType !== 'logo' || this.selectedIndex === null) return;
                const lName = this.activeSide === 'front' ? 'logos' : 'logos_back';
                let list = JSON.parse(JSON.stringify(this.normalizeArray(this[lName])));
                list = Array.isArray(list) ? list : Object.values(list || {});
                const idx = Number(this.selectedIndex);
                const logo = list[idx];
                if (!logo) return;

                this.cropBox = { top: 0, right: 0, bottom: 0, left: 0, shape: 'rect' };

                if (logo.original_url && logo.original_value) {
                    const currentVal = logo.value;
                    const origVal = logo.original_value;
                    logo.url = logo.original_url;
                    logo.value = origVal;
                    if (logo.original_size) {
                        logo.size = logo.original_size;
                    }
                    delete logo.original_url;
                    delete logo.original_value;
                    delete logo.original_size;

                    this[lName] = list;
                    this.isCropping = false;
                    this.activeCropHandle = null;
                    this.updateTexture();

                    if (this.$wire && currentVal && origVal) {
                        try {
                            await this.$wire.restoreOriginalLogo(currentVal, origVal);
                        } catch (err) {
                            console.error('Fehler beim Wiederherstellen des Originalbildes:', err);
                        }
                    }
                    this.recordHistory();
                }
            },

            getStageRect() {
                if ((this.showDrawingBoard || !this.config.modelPath) && this.$refs.drawingBoard) {
                    return this.$refs.drawingBoard.getBoundingClientRect();
                }
                return this.$refs.container.getBoundingClientRect();
            },

            setStageZoom(val) {
                const z = Math.max(1.0, Math.min(3.0, Number(val) || 1.0));
                if (z <= 1.01) {
                    this.stageZoom = 1.0;
                    this.stagePanX = 0.0;
                    this.stagePanY = 0.0;
                    this._zoomFocusX = null;
                    this._zoomFocusY = null;
                    return;
                }
                if (this._zoomFocusX === null || this._zoomFocusY === null) {
                    this._zoomFocusX = Number(this.areaCenterX ?? 50);
                    this._zoomFocusY = Number(this.areaCenterY ?? 50);
                }
                const maxPan = 50 * (z - 1);
                this.stageZoom = Number(z.toFixed(3));
                this.stagePanX = Number(Math.max(-maxPan, Math.min(maxPan, (50 - this._zoomFocusX) * z)).toFixed(2));
                this.stagePanY = Number(Math.max(-maxPan, Math.min(maxPan, (50 - this._zoomFocusY) * z)).toFixed(2));
            },

            stepStageZoom(delta) {
                const next = Math.max(1.0, Math.min(3.0, Number((Number(this.stageZoom || 1.0) + Number(delta || 0)).toFixed(2))));
                this.setStageZoom(next);
            },

            resetStageZoom() {
                this.setStageZoom(1.0);
            },

            getMinimapViewportStyle() {
                const z = Math.max(1.0, Number(this.stageZoom || 1.0));
                const vw = 100 / z;
                const vh = 100 / z;
                const left = Math.max(0, Math.min(100 - vw, 50 - (50 + Number(this.stagePanX || 0)) / z));
                const top = Math.max(0, Math.min(100 - vh, 50 - (50 + Number(this.stagePanY || 0)) / z));
                return `left: ${left.toFixed(2)}%; top: ${top.toFixed(2)}%; width: ${vw.toFixed(2)}%; height: ${vh.toFixed(2)}%;`;
            },

            startMinimapDrag(event) {
                if (this.stageZoom <= 1.01) return;
                const minimap = this.$refs.zoomMinimap;
                if (!minimap) return;

                const rect = minimap.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const mx = ((clientX - rect.left) / Math.max(1, rect.width)) * 100;
                const my = ((clientY - rect.top) / Math.max(1, rect.height)) * 100;

                const z = Math.max(1.01, Number(this.stageZoom || 1.01));
                const cx = 50 - Number(this.stagePanX || 0) / z;
                const cy = 50 - Number(this.stagePanY || 0) / z;
                const halfW = 50 / z;
                const halfH = 50 / z;

                this.isDraggingMinimap = true;
                if (Math.abs(mx - cx) <= halfW && Math.abs(my - cy) <= halfH) {
                    this._minimapOffsetX = mx - cx;
                    this._minimapOffsetY = my - cy;
                } else {
                    this._minimapOffsetX = 0;
                    this._minimapOffsetY = 0;
                    this.updateMinimapFromEvent(event);
                }
            },

            updateMinimapFromEvent(event) {
                if (!this.isDraggingMinimap || this.stageZoom <= 1.01) return;
                const minimap = this.$refs.zoomMinimap;
                if (!minimap) return;

                const rect = minimap.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const mx = ((clientX - rect.left) / Math.max(1, rect.width)) * 100;
                const my = ((clientY - rect.top) / Math.max(1, rect.height)) * 100;

                const targetCx = mx - (this._minimapOffsetX || 0);
                const targetCy = my - (this._minimapOffsetY || 0);
                const z = Math.max(1.01, Number(this.stageZoom || 1.01));
                const maxPan = 50 * (z - 1);

                const newPanX = Math.max(-maxPan, Math.min(maxPan, (50 - targetCx) * z));
                const newPanY = Math.max(-maxPan, Math.min(maxPan, (50 - targetCy) * z));

                this.stagePanX = Number(newPanX.toFixed(2));
                this.stagePanY = Number(newPanY.toFixed(2));
                this._zoomFocusX = Number((50 - this.stagePanX / z).toFixed(2));
                this._zoomFocusY = Number((50 - this.stagePanY / z).toFixed(2));
            },

            startAction(event, type, index, action = 'drag') {
                if(this.context === 'preview' || this.config.isBeginnerMode || (!this.showDrawingBoard && this.config.modelPath)) return;
                if (this.isCropping && this.selectedType === type && this.selectedIndex === index && action === 'drag') {
                    return;
                }
                this.selectItem(type, index);

                let item = (type === 'text') ? this.currentTexts[index] : this.currentLogos[index];
                if(!item) return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const rect = this.getStageRect();

                const curX = Number(item.x ?? this.areaCenterX);
                const curY = Number(item.y ?? this.areaCenterY);

                this.dragStartX = clientX;
                this.dragStartY = clientY;
                this.hasDraggedDistance = false;

                if(event.target.tagName === 'TEXTAREA' && action === 'drag') {
                    // Click-to-Edit & Drag-to-Move: Erst ab > 4px Bewegung als Drag starten, sonst Fokus zum Tippen/Löschen
                    this.currentElement = { type, index, action: 'drag', fromTextarea: true, targetEl: event.target };
                    this.isDragging = false;
                    this.dragOffsetX = (clientX - rect.left) - (curX / 100 * rect.width);
                    this.dragOffsetY = (clientY - rect.top) - (curY / 100 * rect.height);
                    return;
                }

                this.currentElement = { type, index, action, fromTextarea: false };

                if(action === 'drag') {
                    this.isDragging = true;
                    this.dragOffsetX = (clientX - rect.left) - (curX / 100 * rect.width);
                    this.dragOffsetY = (clientY - rect.top) - (curY / 100 * rect.height);
                } else if (action === 'resize') {
                    this.isResizing = true;
                    this.initialDist = Math.max(1, Math.hypot(
                        clientX - (rect.left + curX / 100 * rect.width),
                        clientY - (rect.top + curY / 100 * rect.height)
                    ));
                    this.initialSize = item.size;
                } else if (action === 'rotate') {
                    this.isRotating = true;
                    this.initialAngle = Math.atan2(
                        clientY - (rect.top + curY / 100 * rect.height),
                        clientX - (rect.left + curX / 100 * rect.width)
                    );
                    this.initialRotation = item.rotation || 0;
                }

                if(event.cancelable && event.target.tagName !== 'TEXTAREA') event.preventDefault();
            },

            handleMouseMove(event) {
                if (this.isDraggingMinimap) {
                    this.updateMinimapFromEvent(event);
                    return;
                }

                if (this.isCropping && this.activeCropHandle) {
                    const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                    const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                    const dxScreen = clientX - this.cropStartX;
                    const dyScreen = clientY - this.cropStartY;
                    const rad = -((this.cropRotation || 0) * Math.PI / 180);
                    const dxLocal = dxScreen * Math.cos(rad) - dyScreen * Math.sin(rad);
                    const dyLocal = dxScreen * Math.sin(rad) + dyScreen * Math.cos(rad);

                    const dPctX = (dxLocal / Math.max(24, this.cropElemW)) * 100;
                    const dPctY = (dyLocal / Math.max(24, this.cropElemH)) * 100;
                    const h = this.activeCropHandle;
                    const maxInset = 82;

                    if (h.includes('n')) {
                        this.cropBox.top = Math.max(0, Math.min(maxInset - this.cropBox.bottom, Number((this.cropStartBox.top + dPctY).toFixed(1))));
                    }
                    if (h.includes('s')) {
                        this.cropBox.bottom = Math.max(0, Math.min(maxInset - this.cropBox.top, Number((this.cropStartBox.bottom - dPctY).toFixed(1))));
                    }
                    if (h.includes('w')) {
                        this.cropBox.left = Math.max(0, Math.min(maxInset - this.cropBox.right, Number((this.cropStartBox.left + dPctX).toFixed(1))));
                    }
                    if (h.includes('e')) {
                        this.cropBox.right = Math.max(0, Math.min(maxInset - this.cropBox.left, Number((this.cropStartBox.right - dPctX).toFixed(1))));
                    }
                    return;
                }

                if(!this.currentElement || this.context === 'preview' || (!this.showDrawingBoard && this.config.modelPath)) return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const rect = this.getStageRect();
                const item = this.currentElement.type === 'text' ? this.currentTexts[this.currentElement.index] : this.currentLogos[this.currentElement.index];
                if (!item) return;

                if (this.currentElement.fromTextarea && this.currentElement.action === 'drag' && !this.isDragging) {
                    const moveDist = Math.hypot(clientX - this.dragStartX, clientY - this.dragStartY);
                    if (moveDist > 4) {
                        this.isDragging = true;
                        this.hasDraggedDistance = true;
                        this.focusedTextId = null;
                        if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                            document.activeElement.blur();
                        }
                        if (window.getSelection) {
                            try { window.getSelection().removeAllRanges(); } catch (e) {}
                        }
                    } else {
                        return;
                    }
                }

                if(this.isDragging) {
                    this.hasDraggedDistance = true;
                    let pX = ((clientX - rect.left - this.dragOffsetX) / rect.width) * 100;
                    let pY = ((clientY - rect.top - this.dragOffsetY) / rect.height) * 100;

                    this.showGuideX = false;
                    this.showGuideY = false;

                    const snapX = this.areaCenterX;
                    const snapY = this.areaCenterY;

                    if(Math.abs(pX - snapX) < 2) { pX = snapX; this.showGuideX = true; }
                    if(Math.abs(pY - snapY) < 2) { pY = snapY; this.showGuideY = true; }

                    const clampToBoundaries = (nx, ny, c) => {
                        let res = { x: nx, y: ny };
                        if (c.area_shape === 'rect') {
                            const minX = Number(c.area_left || 0); const maxX = minX + Number(c.area_width || 100);
                            const minY = Number(c.area_top || 0); const maxY = minY + Number(c.area_height || 100);
                            res.x = Math.max(minX, Math.min(maxX, nx));
                            res.y = Math.max(minY, Math.min(maxY, ny));
                        } else if (c.area_shape === 'circle') {
                            const cx = Number(c.area_left || 0) + Number(c.area_width || 100) / 2;
                            const cy = Number(c.area_top || 0) + Number(c.area_height || 100) / 2;
                            const rX = Number(c.area_width || 100) / 2; const rY = Number(c.area_height || 100) / 2;
                            const dx = (nx - cx) / rX; const dy = (ny - cy) / rY;
                            const dist = Math.sqrt(dx*dx + dy*dy);
                            if (dist > 1) {
                                res.x = cx + (dx / dist) * rX; res.y = cy + (dy / dist) * rY;
                            }
                        } else if (c.area_shape === 'custom') {
                            const pts = c.custom_points || [];
                            if (pts.length > 2) {
                                const isPointInside = (px, py) => {
                                    let inside = false;
                                    for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
                                        const xi = Number(pts[i].x), yi = Number(pts[i].y);
                                        const xj = Number(pts[j].x), yj = Number(pts[j].y);
                                        const intersect = ((yi > py) !== (yj > py)) && (px < (xj - xi) * (py - yi) / ((yj - yi) || 1e-7) + xi);
                                        if (intersect) inside = !inside;
                                    }
                                    return inside;
                                };

                                if (!isPointInside(nx, ny)) {
                                    const startX = Number(item.x ?? this.areaCenterX);
                                    const startY = Number(item.y ?? this.areaCenterY);
                                    let bestX = startX;
                                    let bestY = startY;
                                    for (let step = 1; step <= 8; step++) {
                                        const t = step / 8;
                                        const tx = startX + (nx - startX) * t;
                                        const ty = startY + (ny - startY) * t;
                                        if (isPointInside(tx, ty)) {
                                            bestX = tx;
                                            bestY = ty;
                                        } else {
                                            break;
                                        }
                                    }
                                    return { x: bestX, y: bestY };
                                }
                            }
                        }
                        return res;
                    };

                    let clamped = clampToBoundaries(pX, pY, this.config);
                    item.x = Math.max(0, Math.min(100, clamped.x));
                    item.y = Math.max(0, Math.min(100, clamped.y));

                } else if (this.isResizing) {
                    const dist = Math.max(1, Math.hypot(
                        clientX - (rect.left + (item.x || 50) / 100 * rect.width),
                        clientY - (rect.top + (item.y || 50) / 100 * rect.height)
                    ));
                    item.size = Math.max(0.1, this.initialSize * (dist / this.initialDist));

                } else if (this.isRotating) {
                    const angle = Math.atan2(
                        clientY - (rect.top + (item.y || 50) / 100 * rect.height),
                        clientX - (rect.left + (item.x || 50) / 100 * rect.width)
                    );

                    let newRot = this.initialRotation + (angle - this.initialAngle) * (180 / Math.PI);

                    let snapTol = 5;
                    let modAngle = newRot % 90;
                    if(modAngle < 0) modAngle += 90;

                    this.showGuideX = false;
                    this.showGuideY = false;

                    if(modAngle < snapTol || modAngle > 90 - snapTol) {
                        newRot = Math.round(newRot / 90) * 90;
                        this.showGuideX = true;
                        this.showGuideY = true;
                    }

                    item.rotation = newRot;
                }
            },

            handleMouseUp() {
                if (this.isDraggingMinimap) {
                    this.isDraggingMinimap = false;
                    this._justTransformed = true;
                    setTimeout(() => { this._justTransformed = false; }, 80);
                    return;
                }
                if (this.activeCropHandle) {
                    this.activeCropHandle = null;
                    this._justTransformed = true;
                    setTimeout(() => { this._justTransformed = false; }, 80);
                    return;
                }
                const wasTransforming = this.isDragging || this.isResizing || this.isRotating;
                if (this.currentElement && this.currentElement.fromTextarea && !this.hasDraggedDistance && !wasTransforming) {
                    const tItem = (this.currentTexts && this.currentTexts[this.currentElement.index]) ? this.currentTexts[this.currentElement.index] : null;
                    if (tItem && tItem.id) {
                        this.focusedTextId = tItem.id;
                    }
                    if (this.currentElement.targetEl && document.activeElement !== this.currentElement.targetEl) {
                        this.currentElement.targetEl.focus();
                    }
                }
                this.isDragging = this.isResizing = this.isRotating = false;
                this.hasDraggedDistance = false;
                this.currentElement = null;
                this.showGuideX = false;
                this.showGuideY = false;

                if (wasTransforming) {
                    this._justTransformed = true;
                    setTimeout(() => { this._justTransformed = false; }, 80);
                    this.recordHistory();
                }
            },

            fitTextarea(id, el) {
                if(!el) return;
                const scrollPos = window.scrollY;

                const item = this.normalizeArray(this.texts).find(t => t && String(t.id) === String(id))
                          || this.normalizeArray(this.texts_back).find(t => t && String(t.id) === String(id));

                // Niemals das aktive Eingabefeld überschreiben, während der Nutzer darin tippt oder löscht!
                if (document.activeElement !== el && item && typeof item.text === 'string' && el.value !== item.text) {
                    el.value = item.text;
                }

                const hasText = el.value !== undefined && el.value !== null && el.value.length > 0;
                const fontSizePx = this.getEffectiveTextareaFontSizePx(item || { text: el.value, size: this.getDefaultStudioTextSize() });
                const fontFamily = (item && this.fontMap && this.fontMap[item.font]) ? this.fontMap[item.font] : 'Arial';

                el.style.fontSize = fontSizePx + 'px';
                el.style.fontFamily = fontFamily;
                el.style.lineHeight = '1.28';
                el.style.padding = '4px 10px';

                const rawText = hasText
                    ? el.value
                    : (el.getAttribute('placeholder') || 'Text eingeben...');
                const textLines = rawText.split('\n');
                const lines = textLines.length || 1;
                el.rows = lines;

                // Canvas-Fallback-Messung (z.B. wenn 2D-Container in der 3D-Ansicht auf display:none steht oder Text leer ist)
                let canvasWidth = 60;
                let canvasHeight = Math.ceil(lines * fontSizePx * 1.32);
                try {
                    if (!this._measureCanvas) {
                        this._measureCanvas = document.createElement('canvas');
                    }
                    const ctx = this._measureCanvas.getContext('2d');
                    if (ctx) {
                        ctx.font = 'bold ' + fontSizePx + 'px ' + fontFamily;
                        let maxLineW = 0;
                        textLines.forEach(line => {
                            const w = ctx.measureText(line || ' ').width;
                            if (w > maxLineW) maxLineW = w;
                        });
                        canvasWidth = Math.ceil(maxLineW);
                    }
                } catch (e) {}

                const isCurved = Boolean(hasText && item && this.isTextCurved(item));
                const isVisible = !isCurved && this.showDrawingBoard && el.offsetParent !== null && el.getClientRects().length > 0;

                let newWidth = canvasWidth;
                let newHeight = canvasHeight;

                if (isVisible) {
                    el.style.width = '0px';
                    el.style.height = 'auto';

                    const domWidth = el.scrollWidth;
                    const domHeight = el.scrollHeight;

                    // Wenn der Text leer ist (Placeholder sichtbar) oder scrollWidth im Übergang 0 liefert, greift das Canvas-Minimum
                    if (hasText && domWidth > 4) {
                        newWidth = Math.max(domWidth, Math.floor(canvasWidth * 0.92));
                        newHeight = Math.max(domHeight, Math.floor(canvasHeight * 0.92));
                    } else {
                        newWidth = Math.max(domWidth, canvasWidth);
                        newHeight = Math.max(domHeight, canvasHeight);
                    }
                }

                // Großzügiger Puffer links/rechts (+24px) und oben/unten (+14px), damit geschwungene Schreibschriften
                // (z. B. Lobster Two, Great Vibes, Alex Brush) an den Rändern des Textfelds niemals abgeschnitten werden
                let widthPx = Math.max(60, newWidth + 24);
                let heightPx = Math.max(32, newHeight + 14);
                if (!hasText) {
                    widthPx = Math.min(widthPx, this.getMaxEmptyTextWidthPx());
                } else if (isCurved) {
                    const curvedLayout = this.computeCurvedTextLayout(item, fontSizePx);
                    widthPx = curvedLayout.widthPx;
                    heightPx = curvedLayout.heightPx;
                }
                const finalWidth = widthPx + 'px';
                const finalHeight = heightPx + 'px';

                el.style.width = finalWidth;
                el.style.height = finalHeight;

                const computed = {
                    width: finalWidth,
                    height: finalHeight,
                    _text: String((item && item.text) || el.value || ''),
                    _size: Number((item && item.size) || 0),
                    _font: String((item && item.font) || 'Arial'),
                    _curve: Number((item && item.curve) || 0),
                    _scale: Number((this.scaleFactor || 1).toFixed(4)),
                };
                if (!this._textDimsMap) {
                    this._textDimsMap = new Map();
                }
                this._textDimsMap.set(id, computed);
                const rawDims = (window.Alpine && typeof window.Alpine.raw === 'function')
                    ? window.Alpine.raw(this.textDims)
                    : this.textDims;
                if (rawDims) rawDims[id] = computed;

                if (isVisible) {
                    el.scrollTop = 0;
                    el.scrollLeft = 0;
                    window.scrollTo(0, scrollPos);
                }
            },

            openImageEditor() {
                if(this.selectedType !== 'logo' || this.selectedIndex === null) return;
                const logo = this.currentLogos[this.selectedIndex];

                if(!logo || !logo.url) return;
                if(logo.url.toLowerCase().includes('.svg')) {
                    alert('SVG-Dateien können nicht bearbeitet werden.');
                    return;
                }

                this.showImageEditor = true;

                setTimeout(() => {
                    const modalInner = document.getElementById('image-editor-modal');
                    if(!modalInner) return;

                    modalInner.innerHTML = '<div id="image-editor-container" class="w-full h-full"></div>';
                    const container = document.getElementById('image-editor-container');

                    if(window.FilerobotImageEditor) {
                        this.fileRobotInstance = new window.FilerobotImageEditor(container, {
                            source: logo.url,
                            onSave: async (editedImageObject, designState) => {
                                const b64 = editedImageObject.imageBase64;
                                const oldVal = logo.value;
                                logo.url = b64;
                                this.updateTexture();
                                this.closeImageEditor();

                                if (this.$wire && oldVal && String(oldVal).startsWith('bestellungen/cart-uploads/')) {
                                    try {
                                        const res = await this.$wire.saveEditedLogo(oldVal, b64);
                                        if (res && res.value && res.url) {
                                            logo.value = res.value;
                                            logo.url = res.url;
                                            this.updateTexture();
                                        }
                                    } catch (err) {
                                        console.error('Fehler beim Speichern des bearbeiteten Bildes:', err);
                                    }
                                }
                                this.recordHistory();
                            },
                            onClose: () => {
                                this.closeImageEditor();
                            },
                            language: 'de',
                            theme: {
                                colors: {
                                    primaryBg: '#ffffff',
                                    secondaryBg: '#f3f4f6',
                                    accent: '#C5A059',
                                }
                            }
                        });
                        this.fileRobotInstance.render();
                    }
                }, 150);
            },

            closeImageEditor() {
                if(this.fileRobotInstance) {
                    try {
                        this.fileRobotInstance.terminate();
                    } catch(e) {
                        console.warn('Filerobot terminate cleanup ignored.', e);
                    }
                    this.fileRobotInstance = null;
                }
                const modalInner = document.getElementById('image-editor-modal');
                if(modalInner) modalInner.innerHTML = '';
                this.showImageEditor = false;
            },

            // --- UNDO / REDO & HISTORY ENGINE ---
            getSnapshot() {
                return JSON.stringify({
                    texts: this.normalizeArray(this.texts),
                    logos: this.normalizeArray(this.logos),
                    texts_back: this.normalizeArray(this.texts_back),
                    logos_back: this.normalizeArray(this.logos_back),
                    activeSide: this.activeSide
                });
            },

            recordHistory(force = false) {
                if (this.isPerformingHistoryAction) return;
                const snap = this.getSnapshot();

                if (!force && this.historyIndex >= 0 && this.history[this.historyIndex] === snap) {
                    return;
                }

                if (this.historyIndex < this.history.length - 1) {
                    this.history = this.history.slice(0, this.historyIndex + 1);
                }

                this.history.push(snap);
                if (this.history.length > this.maxHistory) {
                    this.history.shift();
                }
                this.historyIndex = this.history.length - 1;
            },

            scheduleDebouncedHistory() {
                if (this.isPerformingHistoryAction) return;
                if (this._historyDebounceTimeout) clearTimeout(this._historyDebounceTimeout);
                this._historyDebounceTimeout = setTimeout(() => {
                    this.recordHistory();
                }, 400);
            },

            applySnapshot(snapStr) {
                if (!snapStr) return;
                try {
                    this.isPerformingHistoryAction = true;
                    const data = JSON.parse(snapStr);

                    this.texts = JSON.parse(JSON.stringify(this.normalizeArray(data.texts)));
                    this.logos = JSON.parse(JSON.stringify(this.normalizeArray(data.logos)));
                    this.texts_back = JSON.parse(JSON.stringify(this.normalizeArray(data.texts_back)));
                    this.logos_back = JSON.parse(JSON.stringify(this.normalizeArray(data.logos_back)));

                    if (data.activeSide && data.activeSide !== this.activeSide) {
                        this.activeSide = data.activeSide;
                    }

                    this.selectedIndex = null;
                    this.selectedType = null;

                    this.$nextTick(() => {
                        this.updateScaleFactor();
                        this.updateTexture();
                        setTimeout(() => {
                            this.isPerformingHistoryAction = false;
                        }, 60);
                    });
                } catch (e) {
                    console.error("Fehler beim Wiederherstellen des Verlaufs:", e);
                    this.isPerformingHistoryAction = false;
                }
            },

            undo() {
                if (!this.canUndo) return;
                this.historyIndex--;
                this.applySnapshot(this.history[this.historyIndex]);
            },

            redo() {
                if (!this.canRedo) return;
                this.historyIndex++;
                this.applySnapshot(this.history[this.historyIndex]);
            },

            _neutralizedParents: [],

            toggleMaximize() {
                this.isMaximized = !this.isMaximized;
                try {
                    if (this.isMaximized) {
                        document.body.style.overflow = 'hidden';
                        document.documentElement.classList.add('configurator-fullscreen-active');
                        const banner = document.getElementById('referral-coupon-banner');
                        if (banner) banner.style.setProperty('display', 'none', 'important');

                        // Trap-Release: Deaktiviert alle Eltern-Stile OBERHALB der Konfigurator-Wurzel, die ein containing-block für fixed erzwingen
                        this._neutralizedParents = [];
                        const rootEl = this.$root || (this.$el && this.$el.closest('[x-data]')) || this.$el;
                        let p = rootEl ? rootEl.parentElement : null;
                        while (p && p !== document.body && p !== document.documentElement) {
                            const cs = window.getComputedStyle(p);
                            const hasTransform = cs.transform !== 'none' || (cs.animationName && cs.animationName !== 'none');
                            const hasBackdrop = (cs.backdropFilter && cs.backdropFilter !== 'none') || (cs.webkitBackdropFilter && cs.webkitBackdropFilter !== 'none');
                            const hasFilter = cs.filter && cs.filter !== 'none';
                            const hasOverflow = cs.overflow !== 'visible' || cs.overflowX !== 'visible' || cs.overflowY !== 'visible';

                            if (hasTransform || hasBackdrop || hasFilter || hasOverflow) {
                                this._neutralizedParents.push({
                                    el: p,
                                    transform: p.style.transform,
                                    animation: p.style.animation,
                                    backdropFilter: p.style.backdropFilter,
                                    webkitBackdropFilter: p.style.webkitBackdropFilter,
                                    filter: p.style.filter,
                                    overflow: p.style.overflow,
                                    overflowX: p.style.overflowX,
                                    overflowY: p.style.overflowY,
                                    borderRadius: p.style.borderRadius,
                                });
                                p.style.setProperty('transform', 'none', 'important');
                                p.style.setProperty('animation', 'none', 'important');
                                p.style.setProperty('backdrop-filter', 'none', 'important');
                                p.style.setProperty('-webkit-backdrop-filter', 'none', 'important');
                                p.style.setProperty('filter', 'none', 'important');
                                p.style.setProperty('overflow', 'visible', 'important');
                                p.style.setProperty('overflow-x', 'visible', 'important');
                                p.style.setProperty('overflow-y', 'visible', 'important');
                            }
                            p = p.parentElement;
                        }
                    } else {
                        document.body.style.overflow = '';
                        document.documentElement.classList.remove('configurator-fullscreen-active');
                        const banner = document.getElementById('referral-coupon-banner');
                        if (banner) banner.style.removeProperty('display');

                        // Stelle die ursprünglichen Eltern-Stile wieder her
                        if (this._neutralizedParents && this._neutralizedParents.length) {
                            this._neutralizedParents.forEach(item => {
                                item.el.style.transform = item.transform || '';
                                item.el.style.animation = item.animation || '';
                                item.el.style.backdropFilter = item.backdropFilter || '';
                                item.el.style.webkitBackdropFilter = item.webkitBackdropFilter || '';
                                item.el.style.filter = item.filter || '';
                                item.el.style.overflow = item.overflow || '';
                                item.el.style.overflowX = item.overflowX || '';
                                item.el.style.overflowY = item.overflowY || '';
                                item.el.style.borderRadius = item.borderRadius || '';
                            });
                            this._neutralizedParents = [];
                        }
                    }
                } catch(e) {
                    console.error('toggleMaximize error:', e);
                }

                this.$nextTick(() => {
                    setTimeout(() => {
                        this.updateScaleFactor();
                        let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                        if (engine) engine.resize();
                    }, 50);
                    setTimeout(() => {
                        this.updateScaleFactor();
                        let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                        if (engine) engine.resize();
                    }, 200);
                });
            },

            handleKeyDown(e) {
                if (this.context === 'preview') return;

                if (e.key === 'Escape' && this.isMaximized) {
                    e.preventDefault();
                    this.toggleMaximize();
                    return;
                }

                const activeTag = document.activeElement ? document.activeElement.tagName : '';
                const isEditingInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeTag) || (document.activeElement && document.activeElement.isContentEditable);

                if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
                    if (!isEditingInput) {
                        e.preventDefault();
                        if (e.shiftKey) {
                            this.redo();
                        } else {
                            this.undo();
                        }
                    }
                    return;
                } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || e.key === 'Y')) {
                    if (!isEditingInput) {
                        e.preventDefault();
                        this.redo();
                    }
                    return;
                }

                if (!isEditingInput && this.selectedIndex !== null && this.selectedType !== null) {
                    if (e.key === 'Delete' || e.key === 'Backspace') {
                        e.preventDefault();
                        this.deleteSelectedItem();
                        return;
                    }

                    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                        const targetList = this.selectedType === 'text' ? this.currentTexts : this.currentLogos;
                        const item = targetList[this.selectedIndex];
                        if (item) {
                            e.preventDefault();
                            const step = e.shiftKey ? 2 : 0.5;
                            const minX = (this.config.area_shape === 'rect') ? (this.config.area_left ?? 0) : 0;
                            const maxX = (this.config.area_shape === 'rect') ? (minX + (this.config.area_width || 100)) : 100;
                            const minY = (this.config.area_shape === 'rect') ? (this.config.area_top ?? 0) : 0;
                            const maxY = (this.config.area_shape === 'rect') ? (minY + (this.config.area_height || 100)) : 100;

                            if (e.key === 'ArrowLeft') item.x = Math.max(minX, (item.x ?? this.areaCenterX) - step);
                            if (e.key === 'ArrowRight') item.x = Math.min(maxX, (item.x ?? this.areaCenterX) + step);
                            if (e.key === 'ArrowUp') item.y = Math.max(minY, (item.y ?? this.areaCenterY) - step);
                            if (e.key === 'ArrowDown') item.y = Math.min(maxY, (item.y ?? this.areaCenterY) + step);

                            this.updateTexture();
                            this.scheduleDebouncedHistory();
                        }
                    }
                }
            },

            // --- EMOJI & SONDERZEICHEN FILTER ---
            filterEmojis(text) {
                if (!text) return '';
                const emojiRegex = /(\p{Extended_Pictographic}|\p{Emoji_Presentation}|[\u{1F300}-\u{1F9FF}\u{1FA00}-\u{1FAFF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F600}-\u{1F64F}\u{1F680}-\u{1F6FF}\u{FE00}-\u{FE0F}])/gu;
                return text.replace(emojiRegex, '');
            },

            triggerEmojiWarning() {
                this.showEmojiWarning = true;
                if (this._emojiWarningTimeout) clearTimeout(this._emojiWarningTimeout);
                this._emojiWarningTimeout = setTimeout(() => {
                    this.showEmojiWarning = false;
                }, 4500);
            },

            handleTextInput(textItem, el, index = null) {
                if (!el) return;
                const rawVal = el.value;
                const cleanVal = this.filterEmojis(rawVal);
                if (cleanVal !== rawVal) {
                    el.value = cleanVal;
                    this.triggerEmojiWarning();
                }

                if (textItem) {
                    textItem.text = cleanVal;
                }

                const activeList = this.currentTexts;
                const targetId = (textItem && textItem.id) ? textItem.id : el.getAttribute('data-id');

                if (index !== null && activeList && activeList[index]) {
                    activeList[index].text = cleanVal;
                }
                if (targetId && activeList) {
                    const matched = activeList.find(t => t && String(t.id) === String(targetId));
                    if (matched) matched.text = cleanVal;
                }

                this.fitTextarea(targetId, el);
                this.updateTexture();
                this.scheduleDebouncedHistory();
            },

            wrapQuoteForStage(rawText, maxCharsPerLine = null) {
                let areaWPercent = Number(this.config?.area_width || 70);
                if (this.config?.area_shape === 'custom' && Array.isArray(this.config?.custom_points) && this.config.custom_points.length > 2) {
                    const xs = this.config.custom_points.map(p => Number(p.x ?? 50));
                    areaWPercent = Math.max(25, Math.max(...xs) - Math.min(...xs));
                }
                const limit = maxCharsPerLine || (areaWPercent <= 42 ? 16 : 24);

                let rawLines = String(rawText || '').split('\n').map(l => l.trim()).filter(Boolean);
                // Bei sehr schmalen Gravurflächen (z. B. Seelen-Talisman) Fließtext gleichmäßig neu umbrechen, falls Zeilen zu breit sind
                if (areaWPercent <= 42 && rawLines.some(l => l.length > limit)) {
                    rawLines = [rawLines.join(' ')];
                }

                const finalLines = [];

                rawLines.forEach(line => {
                    if (line.length <= limit) {
                        finalLines.push(line);
                        return;
                    }
                    const words = line.split(/\s+/);
                    let current = '';
                    words.forEach(word => {
                        const candidate = current ? (current + ' ' + word) : word;
                        if (candidate.length <= limit || !current) {
                            current = candidate;
                        } else {
                            finalLines.push(current);
                            current = word;
                        }
                    });
                    if (current) finalLines.push(current);
                });

                return finalLines.join('\n');
            },

            computeFitQuoteSize(formattedText, fontKey = 'Cinzel') {
                const lines = String(formattedText || '').split('\n');
                const maxChars = lines.reduce((m, l) => Math.max(m, l.length), 0);
                const isCustomShape = this.config?.area_shape === 'custom';

                let areaWPercent = Number(this.config?.area_width || 70);
                let areaHPercent = Number(this.config?.area_height || 70);

                if (isCustomShape && Array.isArray(this.config?.custom_points) && this.config.custom_points.length > 2) {
                    const xs = this.config.custom_points.map(p => Number(p.x ?? 50));
                    const ys = this.config.custom_points.map(p => Number(p.y ?? 50));
                    areaWPercent = Math.max(30, Math.max(...xs) - Math.min(...xs));
                    areaHPercent = Math.max(30, Math.max(...ys) - Math.min(...ys));
                }

                // Basis-Größe anhand der längsten Zeile und Zeilenanzahl sowie der Gravurflächen-Breite bestimmen
                let targetSize = isCustomShape ? 0.72 : 0.85;
                if (maxChars >= 24) targetSize = isCustomShape ? 0.58 : 0.70;
                else if (maxChars >= 20) targetSize = isCustomShape ? 0.62 : 0.75;
                else if (maxChars >= 16) targetSize = isCustomShape ? 0.68 : 0.80;

                if (lines.length >= 4) {
                    targetSize = Math.min(targetSize, isCustomShape ? 0.58 : 0.68);
                } else if (lines.length >= 3) {
                    targetSize = Math.min(targetSize, isCustomShape ? 0.64 : 0.72);
                }

                // Präzise Canvas-Prüfung gegen die reale 2D-Container-Gravurflächenbreite
                try {
                    const containerEl = this.$refs.container || this.$refs.stage;
                    const stageW = containerEl ? containerEl.clientWidth : 500;
                    const stageH = containerEl ? containerEl.clientHeight : 500;
                    if (stageW > 100) {
                        const safetyFactor = isCustomShape ? 0.58 : 0.74;
                        const usableW = stageW * (Math.min(100, Math.max(25, areaWPercent)) / 100) * safetyFactor;
                        const usableH = stageH * (Math.min(100, Math.max(25, areaHPercent)) / 100) * safetyFactor;
                        const scale = this.scaleFactor || (stageW / 500);
                        const basePx = 20 * scale;
                        const fontFamily = (this.fontMap && this.fontMap[fontKey]) ? this.fontMap[fontKey] : 'Arial';

                        if (!this._measureCanvas) {
                            this._measureCanvas = document.createElement('canvas');
                        }
                        const ctx = this._measureCanvas.getContext('2d');
                        if (ctx && basePx > 0) {
                            ctx.font = 'bold ' + basePx + 'px ' + fontFamily;
                            let maxLinePxAtSize1 = 0;
                            lines.forEach(l => {
                                const w = ctx.measureText(l || ' ').width;
                                if (w > maxLinePxAtSize1) maxLinePxAtSize1 = w;
                            });
                            if (maxLinePxAtSize1 > 0) {
                                const fitByWidth = usableW / maxLinePxAtSize1;
                                const totalHeightAtSize1 = lines.length * basePx * 1.32;
                                const fitByHeight = totalHeightAtSize1 > 0 ? (usableH / totalHeightAtSize1) : 1.0;
                                targetSize = Math.min(targetSize, fitByWidth, fitByHeight);
                            }
                        }
                    }
                } catch (e) {}

                return Number(Math.max(0.42, Math.min(1.0, targetSize)).toFixed(2));
            },

            applyQuoteToStage(quoteText) {
                if (!quoteText || this.context === 'preview') return;
                const filtered = this.filterEmojis(String(quoteText).trim());
                if (!filtered) return;

                // Automatisch in harmonische Zeilen umbrechen, damit nichts über den Rand hinausragt
                const cleanQuote = this.wrapQuoteForStage(filtered);

                const tName = this.activeSide === 'back' ? 'texts_back' : 'texts';
                let clonedList = JSON.parse(JSON.stringify(this.normalizeArray(this[tName])));
                clonedList = Array.isArray(clonedList) ? clonedList : Object.values(clonedList || {});
                const freshId = 'txt_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);
                let targetIdx = 0;

                let maxZ = 30;
                clonedList.forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (30 + idx);
                    if (z > maxZ) maxZ = z;
                });
                (this.currentLogos || []).forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (10 + idx);
                    if (z > maxZ) maxZ = z;
                });

                // 1. Falls aktuell ein Textfeld auf dieser Seite ausgewählt ist, Spruch dort einsetzen & einpassen
                if (this.selectedType === 'text' && this.selectedIndex !== null && clonedList[this.selectedIndex]) {
                    targetIdx = this.selectedIndex;
                    const activeItem = clonedList[targetIdx];
                    activeItem.id = freshId;
                    activeItem.text = cleanQuote;
                    activeItem.align = activeItem.align || 'center';
                    activeItem.size = this.computeFitQuoteSize(cleanQuote, activeItem.font || 'Cinzel');
                    activeItem.x = activeItem.x ?? this.areaCenterX;
                    activeItem.y = activeItem.y ?? this.areaCenterY;
                    activeItem.zIndex = Math.min(95, maxZ + 1);
                } else {
                    // 2. Suche nach einem bereits vorhandenen, noch leeren Textfeld auf der aktiven Seite
                    const emptyIdx = clonedList.findIndex(t => !t.text || String(t.text).trim() === '');
                    if (emptyIdx !== -1) {
                        targetIdx = emptyIdx;
                        const emptyItem = clonedList[targetIdx];
                        emptyItem.id = freshId;
                        emptyItem.text = cleanQuote;
                        emptyItem.align = 'center';
                        emptyItem.size = this.computeFitQuoteSize(cleanQuote, emptyItem.font || 'Cinzel');
                        emptyItem.x = this.areaCenterX;
                        emptyItem.y = this.areaCenterY;
                        emptyItem.zIndex = Math.min(95, maxZ + 1);
                    } else {
                        // 3. Neues zentriertes Textfeld auf der aktiven Seite anlegen
                        const defaultFont = (this.lastSelectedFont && this.lastSelectedFont !== 'Arial') ? this.lastSelectedFont : 'Cinzel';
                        this.lastSelectedFont = defaultFont;
                        const newItem = {
                            id: freshId,
                            text: cleanQuote,
                            font: defaultFont,
                            align: 'center',
                            x: this.areaCenterX,
                            y: this.areaCenterY,
                            size: this.computeFitQuoteSize(cleanQuote, defaultFont),
                            rotation: 0,
                            zIndex: Math.min(95, maxZ + 1)
                        };
                        clonedList.push(newItem);
                        targetIdx = clonedList.length - 1;
                    }
                }

                this.focusedTextId = null;
                this[tName] = clonedList;
                this.selectItem('text', targetIdx);
                this.showDrawingBoard = true;

                this.$nextTick(() => {
                    const root = this.$root || document;
                    const ta = root.querySelector(`textarea[data-id="${freshId}"]`);
                    if (ta && ta.value !== cleanQuote) {
                        ta.value = cleanQuote;
                    }
                    this.refitAllTextareas();
                    this.updateTexture();
                    this.recordHistory();
                });
            }
        };
    };
</script>
@endscript
