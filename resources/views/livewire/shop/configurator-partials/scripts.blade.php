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
            // currentElement Struktur: { type: 'text'|'logo', index: 0 }
            currentElement: null,
            dragOffsetX: 0,
            dragOffsetY: 0,

            // Für die Regler oben (damit sie auch da bleiben, wenn man nicht mehr draggt)
            selectedType: null, // 'text' oder 'logo'
            selectedIndex: null,

            init() {
                this.onDrag = this.handleDrag.bind(this);
                this.stopDrag = this.handleStop.bind(this);

                // Standardauswahl: Erster Text, wenn vorhanden
                if(this.texts.length > 0) {
                    this.selectItem('text', 0);
                } else if (this.logos.length > 0) {
                    this.selectItem('logo', 0);
                }

                // Watchers für neue Items
                this.$watch('texts', val => {
                    if(val.length > 0 && this.selectedType !== 'text') {
                        // Wenn ein neuer Text dazu kommt und wir grad kein Text bearbeiten, auswählen
                        this.selectItem('text', val.length - 1);
                    }
                });
                this.$watch('logos', val => {
                    if(val.length > 0 && this.selectedType !== 'logo') {
                        this.selectItem('logo', val.length - 1);
                    }
                });
            },

            selectItem(type, index) {
                if(this.context === 'preview') return; // Sperre für Preview
                this.selectedType = type;
                this.selectedIndex = index;
            },

            startDrag(event, type, index) {
                if(this.context === 'preview') return; // Sperre für Preview
                this.isDragging = true;
                this.currentElement = { type: type, index: index };
                this.selectItem(type, index);

                if(event.cancelable) event.preventDefault();

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();

                // Startwerte holen
                let item;
                if (type === 'text') item = this.texts[index];
                else item = this.logos[index];

                let currentPercentX = parseFloat(item.x);
                let currentPercentY = parseFloat(item.y);

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
                    let distance = (dx * dx) / (radiusX * radiusX) + (dy * dy) / (radiusY * radiusY);

                    if (distance > 1) {
                        let angle = Math.atan2(dy, dx);
                        percentX = centerX + radiusX * Math.cos(angle);
                        percentY = centerY + radiusY * Math.sin(angle);
                    }
                } else {
                    // --- RECHTECK LOGIK (Standard) ---
                    let minX = this.area.left;
                    let maxX = this.area.left + this.area.width;
                    let minY = this.area.top;
                    let maxY = this.area.top + this.area.height;

                    percentX = Math.max(minX, Math.min(maxX, percentX));
                    percentY = Math.max(minY, Math.min(maxY, percentY));
                }

                // Update der Livewire-Daten
                if (this.currentElement.type === 'text') {
                    this.texts[this.currentElement.index].x = percentX;
                    this.texts[this.currentElement.index].y = percentY;
                } else {
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

            // Slider Change Handler
            updateSize(size) {
                if(this.context === 'preview') return; // Sperre für Preview
                if (this.selectedType === 'text' && this.texts[this.selectedIndex]) {
                    this.texts[this.selectedIndex].size = parseFloat(size);
                } else if (this.selectedType === 'logo' && this.logos[this.selectedIndex]) {
                    this.logos[this.selectedIndex].size = parseInt(size);
                }
            }
        }
    }
</script>
@endscript
