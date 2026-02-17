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
            isDragging: false,
            currentElement: null,
            dragOffsetX: 0,
            dragOffsetY: 0,

            // Auswahlstatus für die Toolbar
            selectedType: null,
            selectedIndex: null,

            init() {
                this.onDrag = this.handleDrag.bind(this);
                this.stopDrag = this.handleStop.bind(this);

                // Scroll-Handler für den Calculator-Flow (war fehlend)
                if (typeof Livewire !== 'undefined') {
                    Livewire.on('scroll-top', () => {
                        const anchor = document.getElementById('calculator-anchor');
                        if (anchor) {
                            anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
                }

                // Initial-Auswahl beim Laden mit Sicherheitscheck
                this.$nextTick(() => {
                    if(this.texts && this.texts.length > 0) {
                        this.selectItem('text', 0);
                    } else if (this.logos && this.logos.length > 0) {
                        this.selectItem('logo', 0);
                    }
                });

                // Automatische Auswahl bei neuen Elementen
                this.$watch('texts', val => {
                    if(val && val.length > 0 && this.selectedType !== 'text') {
                        this.$nextTick(() => {
                            // Wichtig: Nur wenn das Element wirklich existiert (Livewire Sync)
                            if(val[val.length - 1]) {
                                this.selectItem('text', val.length - 1);
                            }
                        });
                    }
                });

                this.$watch('logos', val => {
                    if(val && val.length > 0 && this.selectedType !== 'logo') {
                        this.$nextTick(() => {
                            if(val[val.length - 1]) {
                                this.selectItem('logo', val.length - 1);
                            }
                        });
                    }
                });
            },

            deselectAll() {
                if(this.context === 'preview') return;
                this.selectedType = null;
                this.selectedIndex = null;
            },

            selectItem(type, index) {
                if(this.context === 'preview') return;

                // Sicherheit: Existiert das Array und der Index?
                const targetArray = (type === 'text') ? this.texts : this.logos;

                if (targetArray && targetArray[index]) {
                    this.selectedType = type;
                    this.selectedIndex = index;
                } else {
                    // Falls das Element nicht existiert, Auswahl aufheben
                    this.selectedType = null;
                    this.selectedIndex = null;
                }
            },

            startDrag(event, type, index) {
                if(this.context === 'preview') return;

                // Verhindert Drag-Start, wenn man gerade in das Input-Feld tippt (Inline Edit)
                if (event.target.tagName === 'INPUT') return;

                // Sicherheits-Check: Existiert das Element überhaupt?
                let item = (type === 'text') ? this.texts[index] : this.logos[index];
                if (!item) return;

                this.isDragging = true;
                this.currentElement = { type: type, index: index };
                this.selectItem(type, index);

                if(event.cancelable) event.preventDefault();

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();

                let currentPercentX = parseFloat(item.x || 50);
                let currentPercentY = parseFloat(item.y || 50);

                let currentPixelX = (currentPercentX / 100) * container.width;
                let currentPixelY = (currentPercentY / 100) * container.height;

                let mousePixelX = clientX - container.left;
                let mousePixelY = clientY - container.top;

                this.dragOffsetX = mousePixelX - currentPixelX;
                this.dragOffsetY = mousePixelY - currentPixelY;

                window.addEventListener('mousemove', this.onDrag);
                window.addEventListener('touchmove', this.onDrag, { passive: false });
                window.addEventListener('mouseup', this.stopDrag);
                window.addEventListener('touchend', this.stopDrag);
            },

            handleDrag(event) {
                if (!this.isDragging || !this.currentElement || this.context === 'preview') return;

                // Prüfen ob das aktuell gezogene Element noch existiert (während Livewire Syncs)
                if (this.currentElement.type === 'text' && (!this.texts || !this.texts[this.currentElement.index])) return;
                if (this.currentElement.type === 'logo' && (!this.logos || !this.logos[this.currentElement.index])) return;

                if(event.cancelable) event.preventDefault();

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();

                let mouseX = clientX - container.left;
                let mouseY = clientY - container.top;

                let newCenterX = mouseX - this.dragOffsetX;
                let newCenterY = mouseY - this.dragOffsetY;

                // Umrechnung in Prozent
                let percentX = (newCenterX / container.width) * 100;
                let percentY = (newCenterY / container.height) * 100;

                if (this.area.shape === 'circle') {
                    // --- KREIS LOGIK ---
                    const centerX = this.area.left + (this.area.width / 2);
                    const centerY = this.area.top + (this.area.height / 2);
                    const radiusX = this.area.width / 2;
                    const radiusY = this.area.height / 2;

                    let dx = percentX - centerX;
                    let dy = percentY - centerY;

                    // Normalisierte Distanz prüfen
                    let distance = (dx * dx) / (radiusX * radiusX) + (dy * dy) / (radiusY * radiusY);

                    if (distance > 1) {
                        let angle = Math.atan2(dy, dx);
                        percentX = centerX + radiusX * Math.cos(angle);
                        percentY = centerY + radiusY * Math.sin(angle);
                    }
                } else {
                    // --- RECHTECK LOGIK ---
                    let minX = this.area.left;
                    let maxX = this.area.left + this.area.width;
                    let minY = this.area.top;
                    let maxY = this.area.top + this.area.height;

                    percentX = Math.max(minX, Math.min(maxX, percentX));
                    percentY = Math.max(minY, Math.min(maxY, percentY));
                }

                // Update der Daten am korrekten Index
                if (this.currentElement.type === 'text') {
                    this.texts[this.currentElement.index].x = percentX;
                    this.texts[this.currentElement.index].y = percentY;
                } else if (this.currentElement.type === 'logo') {
                    this.logos[this.currentElement.index].x = percentX;
                    this.logos[this.currentElement.index].y = percentY;
                }
            },

            handleStop() {
                this.isDragging = false;
                this.currentElement = null;
                window.removeEventListener('mousemove', this.onDrag);
                window.removeEventListener('touchmove', this.onDrag);
                window.removeEventListener('mouseup', this.stopDrag);
                window.removeEventListener('touchend', this.stopDrag);
            },

            updateSize(size) {
                if(this.context === 'preview') return;
                if (this.selectedType === 'text' && this.texts && this.texts[this.selectedIndex]) {
                    this.texts[this.selectedIndex].size = parseFloat(size);
                } else if (this.selectedType === 'logo' && this.logos && this.logos[this.selectedIndex]) {
                    this.logos[this.selectedIndex].size = parseInt(size);
                }
            }
        }
    }
</script>
@endscript
