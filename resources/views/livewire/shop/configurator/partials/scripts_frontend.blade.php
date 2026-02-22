@script
<script>
    window.frontendConfiguratorData = function(params) {
        return {
            texts: params.wireModels.texts || [],
            logos: params.wireModels.logos || [],
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
            selectedIndex: null,
            selectedType: null,
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
            _isRendering: false,
            _needsAnotherRender: false,

            // --- NEU: Lokaler Speicher (Storage) ---
            hasSavedDesign: false,
            showMessage: false,
            messageText: '',

            init() {
                // Standardwerte setzen, falls nicht vorhanden
                if(typeof this.config.area_left === 'undefined') this.config.area_left = 10;
                if(typeof this.config.area_top === 'undefined') this.config.area_top = 10;
                if(typeof this.config.area_width === 'undefined') this.config.area_width = 80;
                if(typeof this.config.area_height === 'undefined') this.config.area_height = 80;

                // Prüfen, ob bereits ein Design für dieses Produkt im Browser liegt
                const storageKey = 'seelenfunke_design_' + (this.config.productId || 'default');
                this.hasSavedDesign = localStorage.getItem(storageKey) !== null;

                this.$nextTick(() => {
                    this.updateScaleFactor();

                    const startConfigurator = () => {
                        if (this.config.modelPath) {
                            window._threeEngineInstance = new window.Configurator3DEngine(this.$refs.container3d, this.config.modelPath, this.config.bgPath, this.config);
                            window._threeEngineInstance.init(() => {
                                this.modelLoaded = true;
                                this.updateTexture();
                            });
                        } else {
                            this.modelLoaded = true;
                            this.showDrawingBoard = true;
                        }
                    };

                    const checkDependencies = () => {
                        if (window.THREE && window.GLTFLoader && window.Configurator3DEngine) {
                            startConfigurator();
                        } else {
                            setTimeout(checkDependencies, 50);
                        }
                    };

                    checkDependencies();

                    // Livewire-Daten beobachten und UI/Canvas aktualisieren
                    this.$watch('texts', () => {
                        if(this.selectedType === 'text' && !this.texts[this.selectedIndex]){
                            this.selectedType = null;
                            this.selectedIndex = null;
                        }
                        this.updateTexture();
                    }, {deep: true});

                    this.$watch('logos', () => {
                        if(this.selectedType === 'logo' && !this.logos[this.selectedIndex]){
                            this.selectedType = null;
                            this.selectedIndex = null;
                        }
                        this.updateTexture();
                    }, {deep: true});

                    window.addEventListener('resize', () => {
                        this.onWindowResize();
                        if(window._threeEngineInstance) window._threeEngineInstance.resize();
                    });

                    if(this.texts && this.texts.length > 0) this.selectItem('text', 0);
                    else if (this.logos && this.logos.length > 0) this.selectItem('logo', 0);

                    document.fonts.ready.then(() => {
                        this.updateTexture();
                    });
                });
            },

            // --- NEU: Funktionen für Speichern / Laden / Teilen ---

            saveDesign() {
                const storageKey = 'seelenfunke_design_' + (this.config.productId || 'default');

                // Sammle die aktuellen Konfigurator-Daten
                const designData = {
                    texts: Alpine.raw(this.texts),
                    logos: Alpine.raw(this.logos)
                };

                try {
                    localStorage.setItem(storageKey, JSON.stringify(designData));
                    this.hasSavedDesign = true;
                    this.triggerMessage('Design erfolgreich in deinem Browser gesichert!');
                } catch (e) {
                    console.error("Konnte nicht speichern:", e);
                    this.triggerMessage('Fehler beim Speichern. Ist der private Modus aktiv?');
                }
            },

            loadDesign() {
                const storageKey = 'seelenfunke_design_' + (this.config.productId || 'default');
                const savedData = localStorage.getItem(storageKey);

                if (savedData) {
                    try {
                        const parsedData = JSON.parse(savedData);

                        // 1. Auswahl aufheben, um Fehler zu vermeiden
                        this.selectedType = null;
                        this.selectedIndex = null;

                        // 2. WICHTIG: Arrays komplett leeren (zwingt Alpine.js zum Neuaufbau des HTMLs)
                        this.texts = [];
                        this.logos = [];

                        // 3. Einen Takt warten, bis das HTML wirklich leer ist
                        this.$nextTick(() => {

                            // 4. Die gespeicherten Daten einfüllen
                            if (parsedData.texts) this.texts = parsedData.texts;
                            if (parsedData.logos) this.logos = parsedData.logos;

                            // 5. Warten, bis die neuen Textfelder im HTML gerendert wurden
                            this.$nextTick(() => {
                                // Größen neu kalkulieren lassen
                                this.updateScaleFactor();

                                // 6. Die Grafik auf das Canvas stempeln
                                // Wir nutzen gestaffelte Timeouts als absoluten Fallback
                                setTimeout(() => {
                                    this.updateTexture();
                                }, 100);

                                setTimeout(() => {
                                    this.updateTexture();
                                }, 300); // 2. Durchlauf, falls Fonts/Bilder minimal länger brauchten
                            });

                        });

                        this.triggerMessage('Gesichertes Design wurde geladen!');
                    } catch (e) {
                        console.error("Fehler beim Laden:", e);
                        this.triggerMessage('Die gespeicherten Daten sind fehlerhaft.');
                    }
                }
            },

            triggerMessage(text) {
                this.messageText = text;
                this.showMessage = true;
                setTimeout(() => { this.showMessage = false; }, 3500);
            },

            // --- Bestehende Funktionen ---

            updateTexture() {
                if(!window._threeEngineInstance || !window._threeEngineInstance.isReady) return;
                if(this._isRendering) {
                    this._needsAnotherRender = true;
                    return;
                }
                this._isRendering = true;
                requestAnimationFrame(() => {
                    window._threeEngineInstance.renderCanvas(Alpine.raw(this.texts), Alpine.raw(this.logos), Alpine.raw(this.fontMap));
                    setTimeout(() => {
                        this._isRendering = false;
                        if(this._needsAnotherRender) {
                            this._needsAnotherRender = false;
                            this.updateTexture();
                        }
                    }, 40);
                });
            },

            updateScaleFactor(width = null) {
                if(!width && this.$refs.container) {
                    width = this.$refs.container.getBoundingClientRect().width;
                }
                if(width && width > 0) this.scaleFactor = width / this.baseWidth;

                this.$nextTick(() => {
                    document.querySelectorAll('textarea[x-model]').forEach(el => {
                        const textId = el.getAttribute('data-id');
                        if(textId) {
                            this.fitTextarea(textId, el);
                        }
                    });
                });
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
                    e.target.closest('.configurator-storage-ui')) // Klicks auf die neuen Buttons ignorieren
                    return;

                this.selectedType = null;
                this.selectedIndex = null;
                this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
            },

            selectItem(type, index) {
                if(this.context === 'preview') return;
                this.selectedType = type;
                this.selectedIndex = index;
                this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
            },

            deleteSelectedItem() {
                if(this.selectedIndex === null) return;

                if(this.selectedType === 'text') {
                    let newTexts = JSON.parse(JSON.stringify(this.texts));
                    newTexts.splice(this.selectedIndex, 1);

                    // Fallback, damit immer ein leeres Feld bleibt
                    if (newTexts.length === 0) {
                        newTexts.push({id: Math.random().toString(36).substr(2, 9), text: '', size: 1, x: 50, y: 50, rotation: 0, align: 'center', font: 'Arial'});
                    }

                    this.texts = newTexts;
                    this.selectItem('text', this.texts.length - 1);
                } else if(this.selectedType === 'logo') {
                    let newLogos = JSON.parse(JSON.stringify(this.logos));
                    newLogos.splice(this.selectedIndex, 1);
                    this.logos = newLogos;
                    this.selectedType = null;
                    this.selectedIndex = null;
                }
                this.updateTexture();
            },

            duplicateElement() {
                if(this.selectedIndex === null) return;

                const target = (this.selectedType === 'text') ? this.texts : this.logos;
                let clone = JSON.parse(JSON.stringify(target[this.selectedIndex]));

                clone.id = Math.random().toString(36).substr(2, 9);
                clone.x = Math.min(90, (clone.x || 50) + 5);
                clone.y = Math.min(90, (clone.y || 50) + 5);

                target.push(clone);
                this.selectItem(this.selectedType, target.length - 1);
                this.updateTexture();
            },

            centerHorizontal() {
                if(this.selectedIndex === null) return;
                const target = (this.selectedType === 'text') ? this.texts : this.logos;
                target[this.selectedIndex].x = 50;
                this.updateTexture();
            },

            centerVertical() {
                if(this.selectedIndex === null) return;
                const target = (this.selectedType === 'text') ? this.texts : this.logos;
                target[this.selectedIndex].y = 50;
                this.updateTexture();
            },

            centerBoth() {
                this.centerHorizontal();
                this.centerVertical();
            },

            startAction(event, type, index, action = 'drag') {
                if(this.context === 'preview' || (!this.showDrawingBoard && this.config.modelPath)) return;
                this.selectItem(type, index);

                if(event.target.tagName === 'TEXTAREA' && action === 'drag') {
                    event.target.focus();
                    return;
                }

                let item = (type === 'text') ? this.texts[index] : this.logos[index];
                if(!item) return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const rect = this.$refs.container.getBoundingClientRect();

                this.currentElement = { type, index, action };

                if(action === 'drag') {
                    this.isDragging = true;
                    this.dragOffsetX = (clientX - rect.left) - ((item.x || 50) / 100 * rect.width);
                    this.dragOffsetY = (clientY - rect.top) - ((item.y || 50) / 100 * rect.height);
                } else if (action === 'resize') {
                    this.isResizing = true;
                    this.initialDist = Math.max(1, Math.hypot(
                        clientX - (rect.left + (item.x || 50) / 100 * rect.width),
                        clientY - (rect.top + (item.y || 50) / 100 * rect.height)
                    ));
                    this.initialSize = item.size;
                } else if (action === 'rotate') {
                    this.isRotating = true;
                    this.initialAngle = Math.atan2(
                        clientY - (rect.top + (item.y || 50) / 100 * rect.height),
                        clientX - (rect.left + (item.x || 50) / 100 * rect.width)
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
                const item = this.currentElement.type === 'text' ? this.texts[this.currentElement.index] : this.logos[this.currentElement.index];

                if(this.isDragging) {
                    let pX = ((clientX - rect.left - this.dragOffsetX) / rect.width) * 100;
                    let pY = ((clientY - rect.top - this.dragOffsetY) / rect.height) * 100;

                    this.showGuideX = false;
                    this.showGuideY = false;

                    // Snap to center
                    if(Math.abs(pX - 50) < 2) { pX = 50; this.showGuideX = true; }
                    if(Math.abs(pY - 50) < 2) { pY = 50; this.showGuideY = true; }

                    item.x = Math.max(0, Math.min(100, pX));
                    item.y = Math.max(0, Math.min(100, pY));

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

                    // Snap an 90 Grad Winkel
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
                this.isDragging = this.isResizing = this.isRotating = false;
                this.currentElement = null;
                this.showGuideX = false;
                this.showGuideY = false;
            },

            fitTextarea(id, el) {
                if(!el) return;
                const scrollPos = window.scrollY;

                el.style.width = '0px';
                el.style.height = 'auto';

                const lines = el.value.split('\n').length || 1;
                el.rows = lines;

                const newWidth = el.scrollWidth;
                const newHeight = el.scrollHeight;

                const finalWidth = (newWidth + 10) + 'px';
                const finalHeight = newHeight + 'px';

                el.style.width = finalWidth;
                el.style.height = finalHeight;

                this.textDims[id] = { width: finalWidth, height: finalHeight };

                el.scrollTop = 0;
                el.scrollLeft = 0;
                window.scrollTo(0, scrollPos);
            },

            openImageEditor() {
                if(this.selectedType !== 'logo' || this.selectedIndex === null) return;
                const logo = this.logos[this.selectedIndex];

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
                            onSave: (editedImageObject, designState) => {
                                logo.url = editedImageObject.imageBase64;
                                this.updateTexture();
                                this.closeImageEditor();
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
            }
        };
    };
</script>
@endscript
