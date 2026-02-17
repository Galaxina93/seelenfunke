{{-- SCRIPT: Global definieren --}}
@script
<script>
    window.universalConfigurator = function(configData) {
        return {
            ...configData.wireModels, // enthält texts & logos arrays via entangle
            fontMap: configData.fonts,
            alignMap: { 'left': 'text-left', 'center': 'text-center', 'right': 'text-right' },
            area: {
                top: parseFloat(configData.config.area_top || 10),
                left: parseFloat(configData.config.area_left || 10),
                width: parseFloat(configData.config.area_width || 80),
                height: parseFloat(configData.config.area_height || 80),
                shape: configData.config.area_shape || 'rect'
            },
            context: configData.context,

            // State
            isDragging: false,
            isResizing: false,
            isRotating: false,
            currentElement: null,
            dragOffsetX: 0,
            dragOffsetY: 0,

            // RESPONSIVITÄT & SKALIERUNG (WICHTIG FÜR PIXELGENAUE DARSTELLUNG)
            baseWidth: 500, // Referenzbreite in Pixeln
            scaleFactor: 1, // Wird dynamisch berechnet
            resizeObserver: null,

            // Snapping & Guides
            showVGuide: false,
            showHGuide: false,
            snapTolerance: 2.5,
            rotateTolerance: 5,

            // Transform Logik
            initialDist: 0,
            initialSize: 0,
            initialAngle: 0,
            initialRotation: 0,

            // UI State
            selectedType: null,
            selectedIndex: null,
            showFontMenu: false,

            init() {
                this.onMouseMove = this.handleMouseMove.bind(this);
                this.onMouseUp = this.handleMouseUp.bind(this);

                // Scroll-Handler
                if (typeof Livewire !== 'undefined') {
                    Livewire.on('scroll-top', () => {
                        const anchor = document.getElementById('calculator-anchor');
                        if (anchor) anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }

                this.$nextTick(() => {
                    // 1. Initial Scale berechnen
                    this.updateScaleFactor();

                    // 2. ResizeObserver starten, um auf Container-Änderungen zu reagieren
                    if (this.$refs.container) {
                        this.resizeObserver = new ResizeObserver((entries) => {
                            for (let entry of entries) {
                                this.updateScaleFactor(entry.contentRect.width);
                            }
                        });
                        this.resizeObserver.observe(this.$refs.container);
                    }

                    // 3. Initial-Auswahl
                    if(this.texts && this.texts.length > 0) {
                        this.selectItem('text', 0);
                    } else if (this.logos && this.logos.length > 0) {
                        this.selectItem('logo', 0);
                    }
                });

                window.addEventListener('mousemove', this.onMouseMove);
                window.addEventListener('touchmove', this.onMouseMove, { passive: false });
                window.addEventListener('mouseup', this.onMouseUp);
                window.addEventListener('touchend', this.onMouseUp);
            },

            // Berechnet den Faktor basierend auf der aktuellen Breite vs. Basisbreite
            updateScaleFactor(width = null) {
                if (!width && this.$refs.container) {
                    width = this.$refs.container.getBoundingClientRect().width;
                }
                if (width && width > 0) {
                    this.scaleFactor = width / this.baseWidth;
                }

                // Textareas neu anpassen, da sich Font-Size geändert haben könnte
                this.$nextTick(() => {
                    const textareas = document.querySelectorAll('textarea[x-model]');
                    textareas.forEach(el => this.fitTextarea(el));
                });
            },

            deselectAll(e) {
                if(this.context === 'preview') return;
                if (e.target === this.$refs.container || e.target.id === 'stage-overlay') {
                    this.selectedType = null;
                    this.selectedIndex = null;
                    this.showFontMenu = false;
                }
            },

            selectItem(type, index) {
                if(this.context === 'preview') return;
                const targetArray = (type === 'text') ? this.texts : this.logos;
                if (targetArray && targetArray[index]) {
                    this.selectedType = type;
                    this.selectedIndex = index;
                } else {
                    this.selectedType = null;
                    this.selectedIndex = null;
                }
            },

            startAction(event, type, index, action = 'drag') {
                if(this.context === 'preview') return;
                this.selectItem(type, index);
                let item = (type === 'text') ? this.texts[index] : this.logos[index];
                if (!item) return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const rect = this.$refs.container.getBoundingClientRect();

                if (action === 'drag') {
                    if (event.target.tagName === 'TEXTAREA' || event.target.tagName === 'SELECT') return;

                    this.isDragging = true;
                    this.currentElement = { type: type, index: index };

                    // Berechnung des Offsets basierend auf %-Werten und aktueller Containergröße
                    let currentPixelX = (parseFloat(item.x || 50) / 100) * rect.width;
                    let currentPixelY = (parseFloat(item.y || 50) / 100) * rect.height;

                    this.dragOffsetX = (clientX - rect.left) - currentPixelX;
                    this.dragOffsetY = (clientY - rect.top) - currentPixelY;
                }
                else if (action === 'resize') {
                    this.isResizing = true;
                    this.currentElement = { type: type, index: index };
                    // Mittelpunkt des Elements berechnen
                    let centerX = (parseFloat(item.x) / 100) * rect.width + rect.left;
                    let centerY = (parseFloat(item.y) / 100) * rect.height + rect.top;
                    this.initialDist = Math.hypot(clientX - centerX, clientY - centerY);
                    this.initialSize = item.size;
                }
                else if (action === 'rotate') {
                    this.isRotating = true;
                    this.currentElement = { type: type, index: index };
                    let centerX = (parseFloat(item.x) / 100) * rect.width + rect.left;
                    let centerY = (parseFloat(item.y) / 100) * rect.height + rect.top;
                    this.initialAngle = Math.atan2(clientY - centerY, clientX - centerX);
                    this.initialRotation = item.rotation || 0;
                }
                if(event.cancelable) event.preventDefault();
            },

            handleMouseMove(event) {
                if (!this.currentElement || this.context === 'preview') return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const rect = this.$refs.container.getBoundingClientRect();
                let item = (this.currentElement.type === 'text') ? this.texts[this.currentElement.index] : this.logos[this.currentElement.index];

                if (this.isDragging) {
                    let mouseX = clientX - rect.left;
                    let mouseY = clientY - rect.top;

                    // Umrechnung Pixel -> Prozent für responsive Speicherung
                    let pX = ((mouseX - this.dragOffsetX) / rect.width) * 100;
                    let pY = ((mouseY - this.dragOffsetY) / rect.height) * 100;

                    // Snapping & Guides
                    this.showVGuide = Math.abs(pX - 50) < this.snapTolerance;
                    this.showHGuide = Math.abs(pY - 50) < this.snapTolerance;
                    if(this.showVGuide) pX = 50;
                    if(this.showHGuide) pY = 50;

                    // Boundary Check
                    if (this.area.shape === 'circle') {
                        const cX = this.area.left + (this.area.width / 2);
                        const cY = this.area.top + (this.area.height / 2);
                        const rX = this.area.width / 2;
                        const rY = this.area.height / 2;
                        let dx = pX - cX;
                        let dy = pY - cY;
                        if ((dx*dx)/(rX*rX) + (dy*dy)/(rY*rY) > 1) {
                            let angle = Math.atan2(dy, dx);
                            pX = cX + rX * Math.cos(angle);
                            pY = cY + rY * Math.sin(angle);
                        }
                    } else {
                        pX = Math.max(this.area.left, Math.min(this.area.left + this.area.width, pX));
                        pY = Math.max(this.area.top, Math.min(this.area.top + this.area.height, pY));
                    }

                    item.x = pX;
                    item.y = pY;
                }
                else if (this.isResizing) {
                    let centerX = (item.x / 100) * rect.width + rect.left;
                    let centerY = (item.y / 100) * rect.height + rect.top;
                    let dist = Math.hypot(clientX - centerX, clientY - centerY);
                    let ratio = dist / this.initialDist;

                    if (this.currentElement.type === 'text') {
                        item.size = Math.max(0.5, Math.min(8, this.initialSize * ratio));
                        this.$nextTick(() => {
                            const el = document.querySelector('textarea:focus');
                            if(el) this.fitTextarea(el);
                        });
                    } else {
                        // Logos haben Pixel-Werte als Basis, skalieren aber visuell mit
                        item.size = Math.max(20, Math.min(800, this.initialSize * ratio));
                    }
                }
                else if (this.isRotating) {
                    let centerX = (item.x / 100) * rect.width + rect.left;
                    let centerY = (item.y / 100) * rect.height + rect.top;
                    let angle = Math.atan2(clientY - centerY, clientX - centerX);
                    let deg = (this.initialRotation + (angle - this.initialAngle) * (180 / Math.PI)) % 360;

                    // Smart Snap (0, 90, 180, 270)
                    [0, 90, 180, 270, 360].forEach(snap => {
                        if(Math.abs(deg - snap) < this.rotateTolerance || Math.abs(deg + 360 - snap) < this.rotateTolerance) deg = (snap === 360) ? 0 : snap;
                    });
                    item.rotation = deg;
                }
                if(event.cancelable) event.preventDefault();
            },

            handleMouseUp() {
                this.isDragging = this.isResizing = this.isRotating = this.showVGuide = this.showHGuide = false;
                this.currentElement = null;
            },

            updateSize(size) {
                if(this.context === 'preview') return;
                if (this.selectedType === 'text') {
                    this.texts[this.selectedIndex].size = parseFloat(size);
                } else if (this.selectedType === 'logo') {
                    this.logos[this.selectedIndex].size = parseInt(size);
                }
            },

            toggleAlignment() {
                if (this.selectedType !== 'text') return;
                let item = this.texts[this.selectedIndex];
                const states = ['left', 'center', 'right'];
                let nextIndex = (states.indexOf(item.align) + 1) % 3;
                item.align = states[nextIndex];
            },

            fitTextarea(el) {
                if(!el) return;
                el.style.height = 'auto';
                el.style.height = el.scrollHeight + 'px';
                el.style.width = 'auto';
                el.style.width = Math.max(50, el.scrollWidth + 5) + 'px';
            }
        }
    }
</script>
@endscript
