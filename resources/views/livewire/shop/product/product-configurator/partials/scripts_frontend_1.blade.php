@script
<script>
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
            showFontMenu: false,
            showSizeMenu: false,
            showAlignMenu: false,
            showPosMenu: false,
            isDragging: false,
            isResizing: false,
            isRotating: false,
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

            hasSavedDesign: false,
            showMessage: false,
            messageText: '',

            // Vollbild / Maximal-Ansicht
            isMaximized: false,

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

            get currentTexts() { return this.activeSide === 'front' ? this.texts : this.texts_back; },
            get currentLogos() { return this.activeSide === 'front' ? this.logos : this.logos_back; },

            get areaCenterX() {
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

                        // 2. Durchschnittliche Helligkeit im zentralen Gravurbereich (30%..70%) messen
                        let lumSum = 0;
                        let count = 0;
                        const start = Math.floor(size * 0.30);
                        const end = Math.floor(size * 0.70);
                        for (let y = start; y <= end; y++) {
                            for (let x = start; x <= end; x++) {
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
                        if (this.config.modelPath) {
                            this.$refs.container3d._engine = new window.Configurator3DEngine(this, this.$refs.container3d, this.config.modelPath, this.activeBgImage || this.config.bgPath, this.config);

                            const forceStart3D = () => {
                                if (this.modelLoaded || this.isInitializing) return;
                                this.isInitializing = true;

                                this.$refs.container3d._engine.init(() => {
                                    this.modelLoaded = true;
                                    this.isInitializing = false;
                                    this.updateTexture();

                                    if (this.showDrawingBoard === false) {
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

                    this.$watch('texts', () => {
                        if(this.selectedType === 'text' && !this.texts[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                        this.scheduleDebouncedHistory();
                    }, {deep: true});

                    this.$watch('logos', () => {
                        if(this.selectedType === 'logo' && !this.logos[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                        this.scheduleDebouncedHistory();
                    }, {deep: true});

                    this.$watch('texts_back', () => {
                        if(this.selectedType === 'text' && !this.texts_back[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                        this.scheduleDebouncedHistory();
                    }, {deep: true});

                    this.$watch('logos_back', () => {
                        if(this.selectedType === 'logo' && !this.logos_back[this.selectedIndex]){
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
                        this.selectedType = null;
                        this.selectedIndex = null;
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
                            setTimeout(() => {
                                this.updateScaleFactor(null, true);
                            }, 50);
                        });
                    });

                    this._onResize = () => {
                        this.onWindowResize();
                        let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                        if(engine) engine.resize();
                    };
                    window.addEventListener('resize', this._onResize);

                    if(this.texts && this.texts.length > 0) this.selectItem('text', 0);
                    else if (this.logos && this.logos.length > 0) this.selectItem('logo', 0);

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
                                    this.updateTexture();
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

            addFallbackText() {
                const newId = Math.random().toString(36).substr(2, 9);
                const newItem = {id: newId, text: '', size: 1, x: this.areaCenterX, y: this.areaCenterY, rotation: 0, align: 'center', font: 'Arial'};
                if (this.activeSide === 'front') {
                    this.texts.push(newItem);
                    this.selectItem('text', this.texts.length - 1);
                } else {
                    this.texts_back.push(newItem);
                    this.selectItem('text', this.texts_back.length - 1);
                }
                this.focusedTextId = newId;
                this.$nextTick(() => {
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
                    texts: JSON.parse(JSON.stringify(this.texts)),
                    logos: JSON.parse(JSON.stringify(this.logos)),
                    texts_back: JSON.parse(JSON.stringify(this.texts_back)),
                    logos_back: JSON.parse(JSON.stringify(this.logos_back)),
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
                        if (draft.texts) this.texts = draft.texts;
                        if (draft.logos) this.logos = draft.logos;
                        if (draft.texts_back) this.texts_back = draft.texts_back;
                        if (draft.logos_back) this.logos_back = draft.logos_back;

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
                    this.selectedIndex = null;
                    this.selectedType = null;
                    this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
                    
                    // UI Elemente (Guides, Box-Shadow Masken) vor dem Screenshot verstecken
                    this.isCapturing = true;

                    // Kurz warten, bis Alpine die UI-Overlays (Grüne Ränder) versteckt hat
                    await new Promise(r => requestAnimationFrame(r));
                    await new Promise(r => setTimeout(r, 100));

                    // Snapshot-Erstellung mit TIMEOUT (max. 3.5 Sekunden), damit es auf Mobilgeräten NIEMALS hängt!
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
                                    // TEXTUREN ERZWINGEN ZU RENDERN (Falls der User nie in der 3D Ansicht war)
                                    try {
                                        engine.renderBothCanvases(
                                            Alpine.raw(this.texts),
                                            Alpine.raw(this.logos),
                                            Alpine.raw(this.texts_back),
                                            Alpine.raw(this.logos_back),
                                            Alpine.raw(this.fontMap)
                                        );
                                    } catch (e) { console.error("Force Render failed:", e); }
                                    
                                    await new Promise(r => setTimeout(r, 100)); // Warten bis GPU die Textur hat

                                    if (this.config.has_back_side) {
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

                                        // Back Snapshot (Rückseite inkl. Hintergrundbild/-video statt schwarzem Hintergrund!)
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

                                        // Restore
                                        engine.camera.position.copy(origPos);
                                        engine.controls.target.copy(origTarget);
                                        engine.controls.update();
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
                                    }
                                }
                            } else {
                                // 2D-Fallback-Modus
                                if (!window.html2canvas) {
                                    console.log("Loading html2canvas dynamically...");
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
                                        const prevShowBoard = this.showDrawingBoard;
                                        if (!this.showDrawingBoard) {
                                            this.showDrawingBoard = true;
                                            await new Promise(r => setTimeout(r, 80));
                                        }
                                        if (this.config.has_back_side) {
                                            let origSide = this.activeSide;
                                            
                                            this.activeSide = 'front';
                                            await new Promise(r => setTimeout(r, 150)); // wait for DOM
                                            let canvasF = await window.html2canvas(containerToCapture, { useCORS: true, allowTaint: false, backgroundColor: '#ffffff', scale: 1.5 });
                                            snapshotBase64 = canvasF.toDataURL('image/jpeg', 0.85);

                                            this.activeSide = 'back';
                                            await new Promise(r => setTimeout(r, 150)); // wait for DOM
                                            let canvasB = await window.html2canvas(containerToCapture, { useCORS: true, allowTaint: false, backgroundColor: '#ffffff', scale: 1.5 });
                                            snapshotBackBase64 = canvasB.toDataURL('image/jpeg', 0.85);

                                            this.activeSide = origSide;
                                            await new Promise(r => setTimeout(r, 50)); // restore
                                        } else {
                                            let canvas = await window.html2canvas(containerToCapture, { useCORS: true, allowTaint: false, backgroundColor: '#ffffff', scale: 1.5 });
                                            snapshotBase64 = canvas.toDataURL('image/jpeg', 0.85);
                                        }
                                        this.showDrawingBoard = prevShowBoard;
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

                    // 2. An Livewire übergeben
                    let payload = {};
                    if (snapshotBase64) payload.front = snapshotBase64;
                    if (snapshotBackBase64) payload.back = snapshotBackBase64;

                    await $wire.saveWithSnapshot(payload);
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

            updateTexture() {
                let engine = this.$refs.container3d ? this.$refs.container3d._engine : null;
                if (!engine || !engine.isReady || !engine.textureFront) return;

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
                                    Alpine.raw(this.texts),
                                    Alpine.raw(this.logos),
                                    Alpine.raw(this.texts_back),
                                    Alpine.raw(this.logos_back),
                                    Alpine.raw(this.fontMap)
                                );
                            } catch (e) {
                                console.error("Render-Fehler in updateTexture:", e);
                            } finally {
                                setTimeout(() => {
                                    this._isRendering = false;
                                    if (this._needsAnotherRender) {
                                        this._needsAnotherRender = false;
                                        this.updateTexture();
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

                }, 100);
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
                    e.target.closest('.schwebender-werkzeugkasten') ||
                    e.target.closest('textarea') ||
                    e.target.closest('#image-editor-modal') ||
                    e.target.closest('.configurator-storage-ui'))
                    return;

                this.selectedType = null;
                this.selectedIndex = null;
                this.focusedTextId = null;
                this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
            },

            selectItem(type, index) {
                if(this.context === 'preview') return;
                this.selectedType = type;
                this.selectedIndex = index;
                this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
            },

            getElementZIndex(type, index) {
                const list = type === 'text' ? this.currentTexts : this.currentLogos;
                const item = list ? list[index] : null;
                if (item && typeof item.zIndex === 'number' && !isNaN(item.zIndex)) {
                    return Math.max(1, Math.min(95, Math.round(item.zIndex)));
                }
                return type === 'text' ? (20 + Number(index || 0)) : (10 + Number(index || 0));
            },

            bringToFront() {
                if (this.selectedIndex === null || !this.selectedType) return;
                const tList = this.currentTexts || [];
                const lList = this.currentLogos || [];
                let maxZ = 10;
                tList.forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (20 + idx);
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
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (20 + idx);
                    if (z < minZ) minZ = z;
                });
                lList.forEach((it, idx) => {
                    const z = (it && typeof it.zIndex === 'number') ? it.zIndex : (10 + idx);
                    if (z < minZ) minZ = z;
                });
                const target = this.selectedType === 'text' ? tList[this.selectedIndex] : lList[this.selectedIndex];
                if (target) {
                    if (minZ <= 2) {
                        tList.forEach((it, idx) => { it.zIndex = ((typeof it.zIndex === 'number') ? it.zIndex : (20 + idx)) + 2; });
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
                if(this.selectedIndex === null) return;

                let tName = this.activeSide === 'front' ? 'texts' : 'texts_back';
                let lName = this.activeSide === 'front' ? 'logos' : 'logos_back';

                // FIX: Vor dem Löschen alle Menüs hart schließen, damit Alpine.js keine x-models mehr berechnet!
                this.showFontMenu = false;
                this.showSizeMenu = false;
                this.showAlignMenu = false;
                this.showPosMenu = false;

                if(this.selectedType === 'text') {
                    let newTexts = JSON.parse(JSON.stringify(this[tName]));
                    newTexts.splice(this.selectedIndex, 1);

                    if (newTexts.length === 0) {
                        newTexts.push({id: Math.random().toString(36).substr(2, 9), text: '', size: 1, x: this.areaCenterX, y: this.areaCenterY, rotation: 0, align: 'center', font: 'Arial'});
                    }

                    this[tName] = newTexts;
                    this.selectItem('text', this[tName].length - 1);
                } else if(this.selectedType === 'logo') {
                    let newLogos = JSON.parse(JSON.stringify(this[lName]));
                    newLogos.splice(this.selectedIndex, 1);
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

                const target = (this.selectedType === 'text') ? this[tName] : this[lName];
                let clone = JSON.parse(JSON.stringify(target[this.selectedIndex]));

                clone.id = Math.random().toString(36).substr(2, 9);
                clone.x = Math.min(90, (clone.x ?? this.areaCenterX) + 5);
                clone.y = Math.min(90, (clone.y ?? this.areaCenterY) + 5);

                target.push(clone);
                this.selectItem(this.selectedType, target.length - 1);
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

            startAction(event, type, index, action = 'drag') {
                if(this.context === 'preview' || (!this.showDrawingBoard && this.config.modelPath)) return;
                this.selectItem(type, index);

                if(event.target.tagName === 'TEXTAREA' && action === 'drag') {
                    const tItem = (type === 'text' && this.currentTexts) ? this.currentTexts[index] : null;
                    if (tItem && tItem.id) {
                        this.focusedTextId = tItem.id;
                    }
                    event.target.focus();
                    return;
                }

                let item = (type === 'text') ? this.currentTexts[index] : this.currentLogos[index];
                if(!item) return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const rect = this.$refs.container.getBoundingClientRect();

                this.currentElement = { type, index, action };

                const curX = item.x ?? this.areaCenterX;
                const curY = item.y ?? this.areaCenterY;

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
                if(!this.currentElement || this.context === 'preview' || (!this.showDrawingBoard && this.config.modelPath)) return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const rect = this.$refs.container.getBoundingClientRect();
                const item = this.currentElement.type === 'text' ? this.currentTexts[this.currentElement.index] : this.currentLogos[this.currentElement.index];
                if (!item) return;

                if(this.isDragging) {
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
                            const minX = c.area_left || 0; const maxX = minX + (c.area_width || 100);
                            const minY = c.area_top || 0; const maxY = minY + (c.area_height || 100);
                            res.x = Math.max(minX, Math.min(maxX, nx));
                            res.y = Math.max(minY, Math.min(maxY, ny));
                        } else if (c.area_shape === 'circle') {
                            const cx = (c.area_left || 0) + (c.area_width || 100) / 2;
                            const cy = (c.area_top || 0) + (c.area_height || 100) / 2;
                            const rX = (c.area_width || 100) / 2; const rY = (c.area_height || 100) / 2;
                            const dx = (nx - cx) / rX; const dy = (ny - cy) / rY;
                            const dist = Math.sqrt(dx*dx + dy*dy);
                            if (dist > 1) {
                                res.x = cx + (dx / dist) * rX; res.y = cy + (dy / dist) * rY;
                            }
                        } else if (c.area_shape === 'custom') {
                            const pts = c.custom_points || [];
                            if (pts.length > 2) {
                                let inside = false;
                                for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
                                    let xi = pts[i].x, yi = pts[i].y;
                                    let xj = pts[j].x, yj = pts[j].y;
                                    let intersect = ((yi > ny) != (yj > ny)) && (nx < (xj - xi) * (ny - yi) / (yj - yi) + xi);
                                    if (intersect) inside = !inside;
                                }
                                if (!inside) { return { x: item.x, y: item.y }; }
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
                const wasTransforming = this.isDragging || this.isResizing || this.isRotating;
                this.isDragging = this.isResizing = this.isRotating = false;
                this.currentElement = null;
                this.showGuideX = false;
                this.showGuideY = false;

                if (wasTransforming) {
                    this.recordHistory();
                }
            },

            fitTextarea(id, el) {
                if(!el) return;
                const scrollPos = window.scrollY;

                const item = (this.texts || []).find(t => String(t.id) === String(id))
                          || (this.texts_back || []).find(t => String(t.id) === String(id));

                if (item && typeof item.text === 'string' && el.value !== item.text) {
                    el.value = item.text;
                }

                const scale = this.scaleFactor || 1;
                const fontSizePx = (20 * ((item && item.size) || 1)) * scale;
                const fontFamily = (item && this.fontMap && this.fontMap[item.font]) ? this.fontMap[item.font] : 'Arial';

                el.style.fontSize = fontSizePx + 'px';
                el.style.fontFamily = fontFamily;
                el.style.lineHeight = '1.15';

                const rawText = (el.value !== undefined && el.value !== null && el.value.length > 0)
                    ? el.value
                    : (el.getAttribute('placeholder') || 'Text eingeben...');
                const textLines = rawText.split('\n');
                const lines = textLines.length || 1;
                el.rows = lines;

                // Canvas-Fallback-Messung (z.B. wenn 2D-Container in der 3D-Ansicht auf display:none steht oder Text leer ist)
                let canvasWidth = 60;
                let canvasHeight = Math.ceil(lines * fontSizePx * 1.18);
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

                const isVisible = this.showDrawingBoard && el.offsetParent !== null && el.getClientRects().length > 0;

                let newWidth = canvasWidth;
                let newHeight = canvasHeight;

                if (isVisible) {
                    el.style.width = '0px';
                    el.style.height = 'auto';

                    const domWidth = el.scrollWidth;
                    const domHeight = el.scrollHeight;

                    // Wenn der Text leer ist (Placeholder sichtbar) oder scrollWidth im Übergang 0 liefert, greift das Canvas-Minimum
                    if (el.value && el.value.length > 0 && domWidth > 4) {
                        newWidth = Math.max(domWidth, Math.floor(canvasWidth * 0.85));
                        newHeight = Math.max(domHeight, Math.floor(canvasHeight * 0.85));
                    } else {
                        newWidth = Math.max(domWidth, canvasWidth);
                        newHeight = Math.max(domHeight, canvasHeight);
                    }
                }

                // Mindestgröße sicherstellen, damit die 4 Eck-Icons niemals aufeinanderfallen
                const finalWidth = Math.max(48, newWidth + 10) + 'px';
                const finalHeight = Math.max(28, newHeight + 6) + 'px'; // +6px Puffer zur Vermeidung von vertikalem Abschneiden bei Sub-Pixel Rundungsfehlern

                el.style.width = finalWidth;
                el.style.height = finalHeight;

                this.textDims[id] = { width: finalWidth, height: finalHeight };

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
                    texts: Alpine.raw ? Alpine.raw(this.texts) : this.texts,
                    logos: Alpine.raw ? Alpine.raw(this.logos) : this.logos,
                    texts_back: Alpine.raw ? Alpine.raw(this.texts_back) : this.texts_back,
                    logos_back: Alpine.raw ? Alpine.raw(this.logos_back) : this.logos_back,
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

                    this.texts = JSON.parse(JSON.stringify(data.texts || []));
                    this.logos = JSON.parse(JSON.stringify(data.logos || []));
                    this.texts_back = JSON.parse(JSON.stringify(data.texts_back || []));
                    this.logos_back = JSON.parse(JSON.stringify(data.logos_back || []));

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

                        // Trap-Release: Deaktiviert alle Eltern-Stile, die ein containing-block für fixed erzwingen
                        this._neutralizedParents = [];
                        let p = this.$el ? this.$el.parentElement : null;
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

            handleTextInput(textItem, el) {
                if (!textItem || !el) return;
                const rawVal = el.value;
                const cleanVal = this.filterEmojis(rawVal);
                if (cleanVal !== rawVal) {
                    textItem.text = cleanVal;
                    el.value = cleanVal;
                    this.triggerEmojiWarning();
                } else {
                    textItem.text = rawVal;
                }
                this.fitTextarea(textItem.id, el);
                this.scheduleDebouncedHistory();
            }
        };
    };
</script>
@endscript
