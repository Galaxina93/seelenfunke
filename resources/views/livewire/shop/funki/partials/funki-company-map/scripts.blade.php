<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('companyMapData', (config) => ({
            nodes: config.nodes,
            edges: config.edges,

            // Drag & Pan State
            action: 'none', // 'dragNode' | 'pan'
            draggingIndex: null,
            dragOffsetX: 0,  // Offset der Maus relativ zur Node-Mitte beim Greifen
            dragOffsetY: 0,

            // Pan & Zoom
            scale: 1,
            panX: 0,
            panY: 0,
            startX: 0,
            startY: 0,

            canvasWidth: 1200,
            canvasHeight: 1000,

            init() {
                this.updateCanvasSize();
                window.addEventListener('resize', () => this.updateCanvasSize());
                if (this.$refs.canvas) {
                    new ResizeObserver(() => this.updateCanvasSize()).observe(this.$refs.canvas);
                }
            },

            updateCanvasSize() {
                if (this.$refs.canvas) {
                    const rect = this.$refs.canvas.getBoundingClientRect();
                    if (rect.width > 0 && rect.height > 0) {
                        this.canvasWidth = rect.width;
                        this.canvasHeight = rect.height;
                    }
                }
            },

            resetView() {
                this.scale = 1;
                this.panX = 0;
                this.panY = 0;
            },

            // ========================================================
            // KNOTEN ZIEHEN — FIX: Offset wird beim Greifen berechnet
            // ========================================================
            startDragNode(e, index) {
                e.preventDefault();
                this.action = 'dragNode';
                this.draggingIndex = index;

                const rect = this.$refs.canvas.getBoundingClientRect();
                const node = this.nodes[index];

                // Aktuelle Pixel-Position der Node-Mitte auf dem Canvas
                const clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
                const clientY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;

                // Wo genau IN der Node wurde geklickt? (relativ zur Node-Mitte)
                const nodeCenterX = (node.pos_x / 100) * this.canvasWidth * this.scale + this.panX + rect.left;
                const nodeCenterY = (node.pos_y / 100) * this.canvasHeight * this.scale + this.panY + rect.top;

                this.dragOffsetX = clientX - nodeCenterX;
                this.dragOffsetY = clientY - nodeCenterY;
            },

            // ========================================================
            // Canvas MouseDown: Pan nur starten wenn nicht auf Node
            // ========================================================
            onCanvasMouseDown(e) {
                // Nur panning starten, wenn direkt auf den Canvas geklickt (nicht auf Kind-Elemente)
                if (e.target === this.$refs.canvas || e.target.classList.contains('absolute') && !e.target.closest('[data-node]')) {
                    this.startPan(e);
                }
            },

            onCanvasTouchStart(e) {
                if (e.target === this.$refs.canvas) {
                    this.startPanTouch(e);
                }
            },

            // MAP PANNING
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

            // ========================================================
            // BEWEGUNG — Node folgt exakt der Maus (Offset-korrigiert)
            // ========================================================
            onMove(e) {
                if (this.action === 'dragNode' && this.draggingIndex !== null) {
                    const rect = this.$refs.canvas.getBoundingClientRect();

                    // Zielposition in Canvas-Koordinaten (unter Berücksichtigung von Offset)
                    const canvasX = (e.clientX - rect.left - this.dragOffsetX - this.panX) / this.scale;
                    const canvasY = (e.clientY - rect.top  - this.dragOffsetY - this.panY) / this.scale;

                    // In Prozentwerte umrechnen
                    let x = (canvasX / this.canvasWidth) * 100;
                    let y = (canvasY / this.canvasHeight) * 100;

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

            // ZOOMEN
            onZoom(e) {
                const zoomSensitivity = 0.002;
                const delta = -e.deltaY * zoomSensitivity;
                let newScale = this.scale + delta;
                newScale = Math.max(0.3, Math.min(2.5, newScale));
                this.scale = newScale;
            },

            // AKTION BEENDEN
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

            // Doppelklick auf Node
            handleNodeDblClick(node) {
                if (node.component_key) {
                    this.$wire.openNodePanel(node.id);
                } else if (node.link) {
                    window.open(node.link, '_blank');
                }
            },

            // ========================================================
            // PAN per Mittelklick oder auf leerem Bereich
            // ========================================================
            isPanTarget(e) {
                return e.target === this.$refs.canvas;
            },

            // LINIEN BERECHNEN
            calculatePath(edge) {
                if (!edge) return '';
                const source = this.nodes.find(n => n.id === edge.source_id);
                const target = this.nodes.find(n => n.id === edge.target_id);
                if (!source || !target) return '';

                const x1 = (source.pos_x / 100) * this.canvasWidth;
                const y1 = (source.pos_y / 100) * this.canvasHeight;
                const x2 = (target.pos_x / 100) * this.canvasWidth;
                const y2 = (target.pos_y / 100) * this.canvasHeight;
                const dx = Math.abs(x2 - x1) * 0.4;

                return `M ${x1},${y1} C ${x1 + dx},${y1} ${x2 - dx},${y2} ${x2},${y2}`;
            },

            getMidPoint(edge) {
                if (!edge) return { x: 0, y: 0 };
                const source = this.nodes.find(n => n.id === edge.source_id);
                const target = this.nodes.find(n => n.id === edge.target_id);
                if (!source || !target) return { x: 0, y: 0 };

                const x1 = (source.pos_x / 100) * this.canvasWidth;
                const y1 = (source.pos_y / 100) * this.canvasHeight;
                const x2 = (target.pos_x / 100) * this.canvasWidth;
                const y2 = (target.pos_y / 100) * this.canvasHeight;

                return { x: (x1 + x2) / 2, y: (y1 + y2) / 2 };
            },

            // FARBEN
            getEdgeColor(status) {
                if (status === 'inactive') return '#ef4444';
                if (status === 'planned')  return '#f59e0b';
                return '#C5A059';
            },

            getNodeClasses(node) {
                if (!node) return '';
                let classes = '';
                if (node.type === 'core') {
                    classes += 'bg-slate-900 text-primary border-primary ';
                } else {
                    classes += 'text-slate-600 border-slate-200 ';
                }
                if (node.status === 'inactive') classes += 'opacity-50 grayscale ';
                return classes;
            },

            getNodeGlow(node) {
                if (!node) return '';
                if (node.status === 'active')   return 'box-shadow: 0 0 30px rgba(16, 185, 129, 0.6); border-color: rgba(16, 185, 129, 0.8);';
                if (node.status === 'planned')  return 'box-shadow: 0 0 30px rgba(245, 158, 11, 0.6); border-color: rgba(245, 158, 11, 0.8);';
                if (node.status === 'inactive') return 'box-shadow: 0 0 30px rgba(239, 68, 68, 0.6); border-color: rgba(239, 68, 68, 0.8);';
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
