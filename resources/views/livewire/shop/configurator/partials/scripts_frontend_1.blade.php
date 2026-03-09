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

            activeSide: params.wireModels.activeSide !== undefined ? params.wireModels.activeSide : 'front',

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

            hasSavedDesign: false,
            showMessage: false,
            messageText: '',

            get currentTexts() { return this.activeSide === 'front' ? this.texts : this.texts_back; },
            get currentLogos() { return this.activeSide === 'front' ? this.logos : this.logos_back; },

            init() {
                window._frontendConfiguratorDataInstance = this;
                this.isInitializing = false;

                if(typeof this.config.area_left === 'undefined') this.config.area_left = 10;
                if(typeof this.config.area_top === 'undefined') this.config.area_top = 10;
                if(typeof this.config.area_width === 'undefined') this.config.area_width = 80;
                if(typeof this.config.area_height === 'undefined') this.config.area_height = 80;

                const storageKey = 'seelenfunke_design_' + (this.config.productId || 'default');
                this.hasSavedDesign = localStorage.getItem(storageKey) !== null;

                this.$nextTick(() => {
                    this.updateScaleFactor();

                    const startConfigurator = () => {
                        if (this.config.modelPath) {
                            window._threeEngineInstance = new window.Configurator3DEngine(this.$refs.container3d, this.config.modelPath, this.config.bgPath, this.config);

                            const forceStart3D = () => {
                                if (this.modelLoaded || this.isInitializing) return;
                                this.isInitializing = true;

                                window._threeEngineInstance.init(() => {
                                    this.modelLoaded = true;
                                    this.isInitializing = false;
                                    this.updateTexture();

                                    if (this.showDrawingBoard === false) {
                                        window._threeEngineInstance.animate();
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
                            requestStart();

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
                    }, {deep: true});

                    this.$watch('logos', () => {
                        if(this.selectedType === 'logo' && !this.logos[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                    }, {deep: true});

                    this.$watch('texts_back', () => {
                        if(this.selectedType === 'text' && !this.texts_back[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                    }, {deep: true});

                    this.$watch('logos_back', () => {
                        if(this.selectedType === 'logo' && !this.logos_back[this.selectedIndex]){
                            this.selectedType = null; this.selectedIndex = null;
                        }
                        this.updateTexture();
                    }, {deep: true});

                    this.$watch('activeSide', (val) => {
                        this.selectedType = null;
                        this.selectedIndex = null;
                        if (window._threeEngineInstance && window._threeEngineInstance.camera) {
                            window._threeEngineInstance.camera.position.z *= -1;
                            window._threeEngineInstance.camera.position.x *= -1;
                            window._threeEngineInstance.controls.update();
                            window._threeEngineInstance.applyTransforms(val);
                        }
                        this.updateTexture();

                        if (this.showDrawingBoard) {
                            this.$nextTick(() => {
                                setTimeout(() => { this.updateScaleFactor(); }, 50);
                            });
                        }
                    });

                    window.addEventListener('resize', () => {
                        this.onWindowResize();
                        if(window._threeEngineInstance) window._threeEngineInstance.resize();
                    });

                    if(this.texts && this.texts.length > 0) this.selectItem('text', 0);
                    else if (this.logos && this.logos.length > 0) this.selectItem('logo', 0);

                    document.fonts.ready.then(() => {
                        const initialUpdate = () => {
                            if (window.requestIdleCallback) {
                                window.requestIdleCallback(() => this.updateTexture());
                            } else {
                                setTimeout(() => this.updateTexture(), 300);
                            }
                        };
                        initialUpdate();
                    });
                });

                this.$watch('showDrawingBoard', (value) => {
                    if (value === true) {
                        setTimeout(() => {
                            this.updateScaleFactor();
                        }, 50);
                    } else {
                        setTimeout(() => {
                            if (window._threeEngineInstance) {
                                if (!this.modelLoaded) {
                                    if (typeof this._forceStart3D === 'function') this._forceStart3D();
                                } else {
                                    window._threeEngineInstance.animate();
                                    this.updateTexture();
                                }
                                requestAnimationFrame(() => {
                                    if(window._threeEngineInstance) window._threeEngineInstance.resize();
                                });
                            }
                        }, 10);
                    }
                });
            },

            addFallbackText() {
                const newItem = {id: Math.random().toString(36).substr(2, 9), text: 'Neuer Text', size: 1, x: 50, y: 50, rotation: 0, align: 'center', font: 'Arial'};
                if (this.activeSide === 'front') {
                    this.texts.push(newItem);
                    this.selectItem('text', this.texts.length - 1);
                } else {
                    this.texts_back.push(newItem);
                    this.selectItem('text', this.texts_back.length - 1);
                }
                this.updateTexture();
            },

            saveDesign() {
                const storageKey = 'seelenfunke_design_' + (this.config.productId || 'default');

                const designData = {
                    texts: JSON.parse(JSON.stringify(this.texts)),
                    logos: JSON.parse(JSON.stringify(this.logos)),
                    texts_back: JSON.parse(JSON.stringify(this.texts_back)),
                    logos_back: JSON.parse(JSON.stringify(this.logos_back))
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

            // NEU: Snapshot Capture vor dem Speichern in den Warenkorb
            async submitConfig() {
                this.isSaving = true;
                this.selectedIndex = null;
                this.selectedType = null;
                this.showFontMenu = this.showSizeMenu = this.showAlignMenu = this.showPosMenu = false;
                
                // Kurze Pause, damit die UI (Hervorhebungsboxen etc.) verschwindet
                await new Promise(r => setTimeout(r, 100));

                let snapshotBase64 = null;
                let snapshotBackBase64 = null;

                // 1. Snapshot erstellen
                try {
                    if (window._threeEngineInstance && window._threeEngineInstance.renderer && this.config.modelPath) {
                        if (this.config.has_back_side) {
                            const origX = window._threeEngineInstance.camera.position.x;
                            const origZ = window._threeEngineInstance.camera.position.z;
                            let camFrontX = origX;
                            let camFrontZ = origZ;
                            if (this.activeSide === 'back') {
                                camFrontX = origX * -1;
                                camFrontZ = origZ * -1;
                            }

                            // Front Snapshot
                            window._threeEngineInstance.camera.position.x = camFrontX;
                            window._threeEngineInstance.camera.position.z = camFrontZ;
                            window._threeEngineInstance.controls.update();
                            window._threeEngineInstance.renderer.render(window._threeEngineInstance.scene, window._threeEngineInstance.camera);
                            snapshotBase64 = window._threeEngineInstance.renderer.domElement.toDataURL('image/jpeg', 0.85);

                            // Back Snapshot
                            window._threeEngineInstance.camera.position.x = camFrontX * -1;
                            window._threeEngineInstance.camera.position.z = camFrontZ * -1;
                            window._threeEngineInstance.controls.update();
                            window._threeEngineInstance.renderer.render(window._threeEngineInstance.scene, window._threeEngineInstance.camera);
                            snapshotBackBase64 = window._threeEngineInstance.renderer.domElement.toDataURL('image/jpeg', 0.85);

                            // Restore
                            window._threeEngineInstance.camera.position.x = origX;
                            window._threeEngineInstance.camera.position.z = origZ;
                            window._threeEngineInstance.controls.update();
                        } else {
                            window._threeEngineInstance.renderer.render(window._threeEngineInstance.scene, window._threeEngineInstance.camera);
                            snapshotBase64 = window._threeEngineInstance.renderer.domElement.toDataURL('image/jpeg', 0.85);
                        }
                    } else {
                        // 2D-Fallback-Modus
                        if (!window.html2canvas) {
                            console.log("Loading html2canvas dynamically...");
                            await new Promise((resolve) => {
                                const script = document.createElement('script');
                                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                                script.onload = resolve;
                                script.onerror = resolve;
                                document.head.appendChild(script);
                            });
                        }

                        if (window.html2canvas) {
                            const containerToCapture = this.$refs.container || document.querySelector('.configurator-2d-preview');
                            if (containerToCapture) {
                                if (this.config.has_back_side) {
                                    let origSide = this.activeSide;
                                    
                                    this.activeSide = 'front';
                                    await new Promise(r => setTimeout(r, 200)); // wait for DOM
                                    let canvasF = await window.html2canvas(containerToCapture, { useCORS: true, allowTaint: false, backgroundColor: null, scale: 2 });
                                    snapshotBase64 = canvasF.toDataURL('image/jpeg', 0.85);

                                    this.activeSide = 'back';
                                    await new Promise(r => setTimeout(r, 200)); // wait for DOM
                                    let canvasB = await window.html2canvas(containerToCapture, { useCORS: true, allowTaint: false, backgroundColor: null, scale: 2 });
                                    snapshotBackBase64 = canvasB.toDataURL('image/jpeg', 0.85);

                                    this.activeSide = origSide;
                                    await new Promise(r => setTimeout(r, 100)); // restore
                                } else {
                                    let canvas = await window.html2canvas(containerToCapture, { useCORS: true, allowTaint: false, backgroundColor: null, scale: 2 });
                                    snapshotBase64 = canvas.toDataURL('image/jpeg', 0.85);
                                }
                            }
                        }
                    }
                } catch(e) {
                    console.error("Konnte keinen Snapshot erstellen:", e);
                }

                // 2. An Livewire übergeben
                let payload = {};
                if (snapshotBase64) payload.front = snapshotBase64;
                if (snapshotBackBase64) payload.back = snapshotBackBase64;

                $wire.saveWithSnapshot(payload).finally(() => {
                    this.isSaving = false;
                });
            },

            loadSavedDesign() {
                const storageKey = 'seelenfunke_design_' + (this.config.productId || 'default');
                const savedData = localStorage.getItem(storageKey);

                if (savedData) {
                    try {
                        const parsedData = JSON.parse(savedData);

                        this.selectedType = null;
                        this.selectedIndex = null;

                        this.texts = [];
                        this.logos = [];
                        this.texts_back = [];
                        this.logos_back = [];

                        this.$nextTick(() => {
                            if (parsedData.texts) this.texts = parsedData.texts;
                            if (parsedData.logos) this.logos = parsedData.logos;
                            if (parsedData.texts_back) this.texts_back = parsedData.texts_back;
                            if (parsedData.logos_back) this.logos_back = parsedData.logos_back;

                            this.$nextTick(() => {
                                this.updateScaleFactor();
                                setTimeout(() => { this.updateTexture(); }, 100);
                                setTimeout(() => { this.updateTexture(); }, 300);
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

            updateTexture() {
                if (!window._threeEngineInstance || !window._threeEngineInstance.isReady || !window._threeEngineInstance.textureFront) return;

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
                                window._threeEngineInstance.renderBothCanvases(
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
                    e.target.closest('.configurator-storage-ui'))
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
                        newTexts.push({id: Math.random().toString(36).substr(2, 9), text: '', size: 1, x: 50, y: 50, rotation: 0, align: 'center', font: 'Arial'});
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
            },

            duplicateElement() {
                if(this.selectedIndex === null) return;

                let tName = this.activeSide === 'front' ? 'texts' : 'texts_back';
                let lName = this.activeSide === 'front' ? 'logos' : 'logos_back';

                const target = (this.selectedType === 'text') ? this[tName] : this[lName];
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
                const target = (this.selectedType === 'text') ? this.currentTexts : this.currentLogos;
                target[this.selectedIndex].x = 50;
                this.updateTexture();
            },

            centerVertical() {
                if(this.selectedIndex === null) return;
                const target = (this.selectedType === 'text') ? this.currentTexts : this.currentLogos;
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

                let item = (type === 'text') ? this.currentTexts[index] : this.currentLogos[index];
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
                const item = this.currentElement.type === 'text' ? this.currentTexts[this.currentElement.index] : this.currentLogos[this.currentElement.index];

                if(this.isDragging) {
                    let pX = ((clientX - rect.left - this.dragOffsetX) / rect.width) * 100;
                    let pY = ((clientY - rect.top - this.dragOffsetY) / rect.height) * 100;

                    this.showGuideX = false;
                    this.showGuideY = false;

                    if(Math.abs(pX - 50) < 2) { pX = 50; this.showGuideX = true; }
                    if(Math.abs(pY - 50) < 2) { pY = 50; this.showGuideY = true; }

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
