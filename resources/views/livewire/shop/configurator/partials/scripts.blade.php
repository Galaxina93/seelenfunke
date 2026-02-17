{{-- SCRIPT: Global definieren --}}
@script
<script>
    window.universalConfigurator = function(configData) {
        return {
            ...configData.wireModels,
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

            // RESPONSIVITÄT & SKALIERUNG
            baseWidth: 500,
            scaleFactor: 1,
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
            showFontMenu: false, // Menü State Schriftart
            showSizeMenu: false, // Slider State Größe
            showAlignMenu: false, // Menü State Ausrichtung (NEU)

            init() {
                this.onMouseMove = this.handleMouseMove.bind(this);
                this.onMouseUp = this.handleMouseUp.bind(this);

                if (typeof Livewire !== 'undefined') {
                    Livewire.on('scroll-top', () => {
                        const anchor = document.getElementById('calculator-anchor');
                        if (anchor) anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }

                this.$nextTick(() => {
                    this.updateScaleFactor();
                    if (this.$refs.container) {
                        this.resizeObserver = new ResizeObserver((entries) => {
                            for (let entry of entries) {
                                this.updateScaleFactor(entry.contentRect.width);
                            }
                        });
                        this.resizeObserver.observe(this.$refs.container);
                    }

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

            updateScaleFactor(width = null) {
                if (!width && this.$refs.container) {
                    width = this.$refs.container.getBoundingClientRect().width;
                }
                if (width && width > 0) {
                    this.scaleFactor = width / this.baseWidth;
                }
                this.$nextTick(() => {
                    const textareas = document.querySelectorAll('textarea[x-model]');
                    textareas.forEach(el => this.fitTextarea(el));
                });
            },

            deselectAll(e) {
                if(this.context === 'preview') return;
                // Verhindert Deselect, wenn man auf Menüs klickt
                if (e.target.closest('.flex.items-center.gap-3.mt-4')) return;

                if (e.target === this.$refs.container || e.target.id === 'stage-overlay') {
                    this.selectedType = null;
                    this.selectedIndex = null;
                    this.showFontMenu = false;
                    this.showSizeMenu = false;
                    this.showAlignMenu = false; // NEU
                }
            },

            selectItem(type, index) {
                if(this.context === 'preview') return;
                const targetArray = (type === 'text') ? this.texts : this.logos;
                if (targetArray && targetArray[index]) {
                    this.selectedType = type;
                    this.selectedIndex = index;
                    // Menüs resetten bei neuem Item
                    this.showFontMenu = false;
                    this.showSizeMenu = false;
                    this.showAlignMenu = false; // NEU
                } else {
                    this.selectedType = null;
                    this.selectedIndex = null;
                }
            },

            duplicateElement() {
                if (this.selectedIndex === null) return;
                let target = (this.selectedType === 'text') ? this.texts : this.logos;
                let original = target[this.selectedIndex];
                let clone = JSON.parse(JSON.stringify(original));
                clone.id = Math.random().toString(36).substr(2, 9);
                clone.x = Math.min(this.area.left + this.area.width, clone.x + 5);
                clone.y = Math.min(this.area.top + this.area.height, clone.y + 5);
                target.push(clone);
                this.selectItem(this.selectedType, target.length - 1);
            },

            centerHorizontal() {
                if (this.selectedIndex === null) return;
                let item = (this.selectedType === 'text') ? this.texts[this.selectedIndex] : this.logos[this.selectedIndex];
                item.x = this.area.left + (this.area.width / 2);
            },

            centerVertical() {
                if (this.selectedIndex === null) return;
                let item = (this.selectedType === 'text') ? this.texts[this.selectedIndex] : this.logos[this.selectedIndex];
                item.y = this.area.top + (this.area.height / 2);
            },

            centerBoth() {
                this.centerHorizontal();
                this.centerVertical();
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
                    let currentPixelX = (parseFloat(item.x || 50) / 100) * rect.width;
                    let currentPixelY = (parseFloat(item.y || 50) / 100) * rect.height;
                    this.dragOffsetX = (clientX - rect.left) - currentPixelX;
                    this.dragOffsetY = (clientY - rect.top) - currentPixelY;
                }
                else if (action === 'resize') {
                    this.isResizing = true;
                    this.currentElement = { type: type, index: index };
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
                    let pX = ((mouseX - this.dragOffsetX) / rect.width) * 100;
                    let pY = ((mouseY - this.dragOffsetY) / rect.height) * 100;

                    this.showVGuide = Math.abs(pX - 50) < this.snapTolerance;
                    this.showHGuide = Math.abs(pY - 50) < this.snapTolerance;
                    if(this.showVGuide) pX = 50;
                    if(this.showHGuide) pY = 50;

                    pX = Math.max(this.area.left, Math.min(this.area.left + this.area.width, pX));
                    pY = Math.max(this.area.top, Math.min(this.area.top + this.area.height, pY));

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
                    } else {
                        item.size = Math.max(20, Math.min(1000, this.initialSize * ratio));
                    }
                }
                else if (this.isRotating) {
                    let centerX = (item.x / 100) * rect.width + rect.left;
                    let centerY = (item.y / 100) * rect.height + rect.top;
                    let angle = Math.atan2(clientY - centerY, clientX - centerX);
                    let deg = (this.initialRotation + (angle - this.initialAngle) * (180 / Math.PI)) % 360;
                    [0, 90, 180, 270, 360].forEach(snap => {
                        if(Math.abs(deg - snap) < this.rotateTolerance) deg = (snap === 360) ? 0 : snap;
                    });
                    item.rotation = deg;
                }
                if(event.cancelable) event.preventDefault();
            },

            handleMouseUp() {
                this.isDragging = this.isResizing = this.isRotating = this.showVGuide = this.showHGuide = false;
                this.currentElement = null;
            },

            toggleAlignment() {
                if (this.selectedType !== 'text') return;
                let item = this.texts[this.selectedIndex];
                const states = ['left', 'center', 'right'];
                let nextIndex = (states.indexOf(item.align) + 1) % 3;
                item.align = states[nextIndex];
            },

            // Optimierte Version: Setzt Breite auf 0, um Schrumpfen zu erlauben
            fitTextarea(el) {
                if(!el) return;
                // Height Reset
                el.style.height = 'auto';
                el.style.height = el.scrollHeight + 'px';

                // Width Reset - WICHTIG für Links/Zentriert
                el.style.width = '0px';
                el.style.width = (el.scrollWidth + 2) + 'px'; // +2 für minimalen Puffer
            }
        }
    }
</script>
@endscript
