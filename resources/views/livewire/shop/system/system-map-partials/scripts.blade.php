<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('companyMapData', (config) => ({
            nodes: config.nodes,
            edges: config.edges,
            liveAiPulse: config.liveAiPulse,
            activeMap: config.activeMap,
            apiStatuses: @entangle('apiStatuses'),

            action: 'none',
            draggingIndex: null,
            dragOffsetX: 0,
            dragOffsetY: 0,

            scale: 1,
            panX: 0,
            panY: 0,
            startX: 0,
            startY: 0,

            canvasWidth: window.innerWidth,
            canvasHeight: window.innerHeight,

            init() {
                this.updateCanvasSize();
                window.addEventListener('resize', () => this.updateCanvasSize());
                if (this.$refs.canvas) {
                    new ResizeObserver(() => this.updateCanvasSize()).observe(this.$refs.canvas);
                }

                if(window.innerWidth < 768 && this.scale === 1) {
                    this.scale = 0.6;
                }

                Livewire.on('apis-checked', () => {
                    this.nodes = [...this.nodes];
                });
            },

            updateCanvasSize() {
                if (this.$refs.canvas) {
                    const rect = this.$refs.canvas.getBoundingClientRect();
                    // Protect against Livewire DOM Morph 0x0
                    if (rect.width > 0 && rect.height > 0) {
                        this.canvasWidth = rect.width;
                        this.canvasHeight = rect.height;
                    }
                }
            },

            resetView() {
                this.scale = window.innerWidth < 768 ? 0.6 : 1;
                this.panX = 0;
                this.panY = 0;
            },

            startDragNode(e, index) {
                e.preventDefault();
                this.action = 'dragNode';
                this.draggingIndex = index;

                const node = this.nodes[index];
                const clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
                const clientY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;

                const rect = this.$refs.canvas.getBoundingClientRect();
                
                // Screen coordinates wo der Klick passierte
                this.dragOffsetX = clientX;
                this.dragOffsetY = clientY;
                
                // Wir speichern den Originalstand des Knotens in % beim Start
                this._initialNodePosX = node.pos_x;
                this._initialNodePosY = node.pos_y;
            },

            onCanvasMouseDown(e) {
                if (e.target === this.$refs.canvas || e.target.classList.contains('absolute') && !e.target.closest('[data-node]')) {
                    this.startPan(e);
                }
            },

            onCanvasTouchStart(e) {
                if (e.target === this.$refs.canvas || e.target.classList.contains('absolute') && !e.target.closest('[data-node]')) {
                    this.startPanTouch(e);
                }
            },

            startPan(e) {
                this.action = 'pan';
                this.startX = e.clientX - this.panX;
                this.startY = e.clientY - this.panY;
            },

            startPanTouch(e) {
                if (e.touches.length === 1) {
                    this.action = 'pan';
                    this.startX = e.touches[0].clientX - this.panX;
                    this.startY = e.touches[0].clientY - this.panY;
                }
            },

            onMove(e) {
                if (this.action === 'dragNode' && this.draggingIndex !== null) {
                    // Mausbewegung in Pixeln
                    const deltaX = (e.clientX - this.dragOffsetX) / this.scale;
                    const deltaY = (e.clientY - this.dragOffsetY) / this.scale;
                    
                    // Umrechnung in % basierend auf der ECHTEN DOM-Breite/-Höhe
                    const deltaXPercent = (deltaX / this.canvasWidth) * 100;
                    const deltaYPercent = (deltaY / this.canvasHeight) * 100;

                    let x = this._initialNodePosX + deltaXPercent;
                    let y = this._initialNodePosY + deltaYPercent;

                    this.nodes[this.draggingIndex].pos_x = Math.max(-10, Math.min(110, x));
                    this.nodes[this.draggingIndex].pos_y = Math.max(-10, Math.min(110, y));
                } else if (this.action === 'pan') {
                    this.panX = e.clientX - this.startX;
                    this.panY = e.clientY - this.startY;
                }
            },

            onMoveTouch(e) {
                if (e.touches.length === 1) {
                    const ev = { clientX: e.touches[0].clientX, clientY: e.touches[0].clientY };
                    this.onMove(ev);
                }
            },

            onZoom(e) {
                if (!this.$refs.canvas) return;

                const rect = this.$refs.canvas.getBoundingClientRect();

                // Position der Maus relativ zum Canvas-Container
                const mouseX = e.clientX - rect.left;
                const mouseY = e.clientY - rect.top;

                const prevScale = this.scale;

                // Neue Scale berechnen
                const zoomSensitivity = 0.002;
                const delta = -e.deltaY * zoomSensitivity;
                let newScale = this.scale + delta;
                newScale = Math.max(0.2, Math.min(3.0, newScale));

                // Logischen Punkt berechnen (welcher Punkt der Karte exakt unter der Maus liegt)
                const logicalX = (mouseX - this.panX) / prevScale;
                const logicalY = (mouseY - this.panY) / prevScale;

                this.scale = newScale;

                // Pan verschieben, sodass der logische Punkt wieder exakt unter der Maus liegt
                this.panX = mouseX - logicalX * this.scale;
                this.panY = mouseY - logicalY * this.scale;
            },

            stopAction() {
                if (this.action === 'dragNode' && this.draggingIndex !== null) {
                    const node = this.nodes[this.draggingIndex];
                    this.$wire.updateNodePosition(node.id, node.pos_x, node.pos_y);
                }
                this.action = 'none';
                this.draggingIndex = null;
                this.dragOffsetX = 0;
                this.dragOffsetY = 0;
            },

            handleNodeDblClick(node) {
                if (node.component_key) {
                    this.$wire.openNodePanel(node.id);
                } else if (node.link) {
                    window.open(node.link, '_blank');
                }
            },

            calculatePath(edge) {
                if (!edge || !this.canvasWidth) return '';
                const source = this.nodes.find(n => n.id === edge.source_id);
                const target = this.nodes.find(n => n.id === edge.target_id);
                if (!source || !target) return '';

                const x1 = (source.pos_x / 100) * this.canvasWidth;
                const y1 = (source.pos_y / 100) * this.canvasHeight;
                const x2 = (target.pos_x / 100) * this.canvasWidth;
                const y2 = (target.pos_y / 100) * this.canvasHeight;
                
                // Dynamische Kurven logik: Wenn Target links von Source liegt
                // (wird für saubere Back-Flows wie in der KI Architektur genutzt)
                let dx = Math.abs(x2 - x1) * 0.4;
                if (x1 > x2) {
                    return `M ${x1},${y1} Q ${(x1+x2)/2},${y1 + (this.canvasHeight * 0.15)} ${x2},${y2}`;
                }

                return `M ${x1},${y1} C ${x1 + dx},${y1} ${x2 - dx},${y2} ${x2},${y2}`;
            },

            getMidPoint(edge) {
                if (!edge || !this.canvasWidth) return { x: 0, y: 0 };
                const source = this.nodes.find(n => n.id === edge.source_id);
                const target = this.nodes.find(n => n.id === edge.target_id);
                if (!source || !target) return { x: 0, y: 0 };

                const x1 = (source.pos_x / 100) * this.canvasWidth;
                const y1 = (source.pos_y / 100) * this.canvasHeight;
                const x2 = (target.pos_x / 100) * this.canvasWidth;
                const y2 = (target.pos_y / 100) * this.canvasHeight;

                return { x: (x1 + x2) / 2, y: (y1 + y2) / 2 };
            },

            getEdgeColor(status) {
                if (status === 'inactive') return '#ef4444';
                if (status === 'planned')  return '#f59e0b';
                return '#C5A059';
            },

            getNodeClasses(node) {
                if (!node) return '';
                let classes = '';
                if (node.type === 'core') {
                    classes += 'bg-gray-950 text-primary border-primary ';
                } else {
                    classes += 'bg-gray-900 text-gray-400 border-gray-700 hover:text-white hover:border-gray-500 ';
                }
                if (node.status === 'inactive') classes += 'opacity-40 grayscale ';
                return classes;
            },

            getNodeGlow(node) {
                if (!node) return '';
                if (node.status === 'active')   return 'box-shadow: 0 0 30px rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.5);';
                if (node.status === 'planned')  return 'box-shadow: 0 0 30px rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.5);';
                if (node.status === 'inactive') return 'box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8); border-color: rgba(239, 68, 68, 0.3);';
                if (node.type === 'core') return 'box-shadow: 0 0 40px rgba(197, 160, 89, 0.2);';
                return '';
            },

            isImageLogo(iconName) {
                const brands = ['datev', 'dhl', 'etsy', 'finom', 'google', 'mittwald', 'stripe', 'firebase'];
                return brands.includes(iconName);
            },

            getLogoUrl(iconName) {
                return `/images/projekt/brands/${iconName}.svg`;
            },
        }));
    });
</script>
