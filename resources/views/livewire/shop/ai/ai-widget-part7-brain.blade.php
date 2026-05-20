<!-- Neurale Fehleranalyse (3D Projekt Gehirn) -->
<script src="//unpkg.com/3d-force-graph"></script>
<script>
    window.brainMapInstance = null;
    window.brainMapData = null;

    window.initBrainMap = async function() {
        if (window.brainMapInstance) {
            // Already initialized, just fit to screen
            try {
                window.brainMapInstance.zoomToFit(1000, 50);
            } catch (e) {
                console.warn('zoomToFit skipped due to layout instability:', e);
            }
            return;
        }

        const container = document.getElementById('brain-canvas-container');
        if (!container) return;
        
        // Verhindere THREE.js NaN Errors, wenn der Container noch unsichtbar ist (display: none / size 0)
        if (container.clientWidth === 0 || container.clientHeight === 0) {
            setTimeout(window.initBrainMap, 100);
            return;
        }

        // Lade die JSON Daten
        try {
            // Append a cache-buster so we don't fetch a stale 404 when it was just generated
            const response = await fetch('/storage/system-brain-map.json?t=' + Date.now());
            if (!response.ok) {
                console.warn('System Brain Map JSON nicht gefunden. Generiere neu...');
                window.dispatchEvent(new CustomEvent('ai-speech-feedback', { detail: { text: "Ich baue das Systemgehirn zum ersten Mal auf. Bitte einen kleinen Moment Geduld." } }));
                Livewire.dispatch('generate-system-brain-map');
                return;
            }
            window.brainMapData = await response.json();
        } catch (e) {
            console.error('Fehler beim Laden der System Brain Map:', e);
            return;
        }

        // Initialize 3D Force Graph with explicit screen dimensions
        window.brainMapInstance = ForceGraph3D()(container)
            .width(window.innerWidth)
            .height(window.innerHeight)
            .graphData(window.brainMapData)
            .nodeLabel('id')
            .nodeColor(node => {
                if (node.isError) return 'rgba(255, 0, 68, 1)'; // Blutrot
                
                // Group Colors
                switch(node.group) {
                    case 2: return 'rgba(16, 185, 129, 0.8)'; // Models (Emerald)
                    case 3: return 'rgba(59, 130, 246, 0.8)'; // Controllers (Blue)
                    case 4: return 'rgba(236, 72, 153, 0.8)'; // Livewire (Pink)
                    case 5: return 'rgba(245, 158, 11, 0.8)'; // Views (Amber)
                    default: return 'rgba(156, 163, 175, 0.8)'; // Gray
                }
            })
            .nodeRelSize(4)
            .linkColor(() => 'rgba(255,255,255,0.1)')
            .linkWidth(0.5)
            .backgroundColor('rgba(0,0,0,0)'); // Transparent to show background

        // Register window resize listener to keep canvas sized correctly
        window.addEventListener('resize', () => {
            if (window.brainMapInstance) {
                window.brainMapInstance
                    .width(window.innerWidth)
                    .height(window.innerHeight);
            }
        });

        // Set click listener on node
        window.brainMapInstance.onNodeClick(node => {
                // Focus on node
                const distance = 40;
                const hypot = Math.hypot(node.x, node.y, node.z);
                const camPos = hypot > 0.001 
                    ? { x: node.x * (1 + distance/hypot), y: node.y * (1 + distance/hypot), z: node.z * (1 + distance/hypot) }
                    : { x: 0, y: 0, z: distance };
                
                window.brainMapInstance.cameraPosition(
                    camPos,
                    node,
                    1500
                );

                if (node.isError) {
                    console.log('Error Node clicked:', node.id);
                    // Sende Event an Livewire, dass der Agent analysieren soll
                    Livewire.dispatch('execute-system-command', {
                        payload: {
                            command: 'system_analyze_neural_error',
                            args: {
                                file_path: node.id,
                                error_context: node.errorMessage || 'Kein detaillierter Kontext verfügbar.'
                            }
                        }
                    });
                    
                    
                    // Trigger a UI notification or agent response via Alpine
                    window.dispatchEvent(new CustomEvent('ai-speech-feedback', { detail: { text: "Ich initiiere eine tiefgreifende neurale Analyse der betroffenen Datei: " + node.name } }));
                } else {
                    window.dispatchEvent(new CustomEvent('ai-speech-feedback', { detail: { text: node.name } }));
                }

                window.openNeuralNodeTile(node);
            });

        window.openNeuralNodeTile = function(node) {
            // Show selected node UI
            window.selectedBrainNodeId = node.id;
            new Audio('/shop/ai/sounds/project_brain/click_file_in_project_brain.mp3').play().catch(e=>console.log(e));

            const container = document.getElementById('brain-selected-node-container');
            const nameSpan = document.getElementById('brain-selected-node-name');
            const depsList = document.getElementById('brain-dependencies-list');
            const errorTile = document.getElementById('brain-error-tile');
            const errorContent = document.getElementById('brain-error-content');

            if (container && nameSpan) {
                nameSpan.innerText = node.name;
                container.classList.remove('hidden');
                container.classList.add('flex');
                
                // Fülle Zusammenhänge
                if (depsList) {
                    depsList.innerHTML = '';
                    const relatedLinks = window.brainMapData.links.filter(l => 
                        (l.source.id === node.id || l.target.id === node.id)
                    );
                    if(relatedLinks.length === 0) {
                        depsList.innerHTML = '<span class="text-emerald-500/50 italic">Keine Abhängigkeiten bekannt</span>';
                    } else {
                        relatedLinks.forEach(link => {
                            const relatedNode = link.source.id === node.id ? link.target : link.source;
                            const div = document.createElement('div');
                            div.className = "break-all hover:text-white cursor-pointer transition-colors pb-1 border-b border-emerald-500/10 last:border-0";
                            div.innerText = "- " + relatedNode.name;
                            div.onclick = () => {
                                if(window.searchBrainMap) window.searchBrainMap(relatedNode.id);
                            };
                            depsList.appendChild(div);
                        });
                    }
                }

                // Fülle Fehler
                if (errorTile && errorContent) {
                    if (node.isError) {
                        errorTile.classList.remove('border-emerald-500/40', 'shadow-[0_10px_30px_-10px_rgba(16,185,129,0.4)]');
                        errorTile.classList.add('border-rose-500/40', 'shadow-[0_10px_30px_-10px_rgba(244,63,94,0.4)]');
                        errorTile.querySelector('span').className = "text-[10px] text-rose-400/80 font-bold uppercase tracking-widest flex-shrink-0";
                        errorContent.className = "absolute inset-0 overflow-y-auto custom-scrollbar text-xs font-mono text-rose-100/80 pr-2";
                        errorContent.innerText = node.errorMessage || "Fehler in dieser Datei festgestellt!";
                    } else {
                        errorTile.classList.remove('border-rose-500/40', 'shadow-[0_10px_30px_-10px_rgba(244,63,94,0.4)]');
                        errorTile.classList.add('border-emerald-500/40', 'shadow-[0_10px_30px_-10px_rgba(16,185,129,0.4)]');
                        errorTile.querySelector('span').className = "text-[10px] text-emerald-400/80 font-bold uppercase tracking-widest flex-shrink-0";
                        errorContent.className = "absolute inset-0 overflow-y-auto custom-scrollbar text-xs font-mono text-emerald-100/80 pr-2 flex items-center h-full";
                        errorContent.innerHTML = '<span class="text-emerald-400 flex items-center gap-1.5"><svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Systemstatus 100% OK</span>';
                    }
                }
            }
        };

        // Click on background to clear selection
        window.brainMapInstance.onBackgroundClick(() => {
            if (window.selectedBrainNodeId) {
                new Audio('/shop/ai/sounds/project_brain/close_file_in_project_brain.mp3').play().catch(e=>console.log(e));
            }
            window.selectedBrainNodeId = null;
            const container = document.getElementById('brain-selected-node-container');
            if (container) {
                container.classList.add('hidden');
                container.classList.remove('flex');
            }
            
            // Reset colors
            if (window.brainMapInstance && window.brainMapData) {
                window.brainMapData.nodes.forEach(node => {
                    delete node.__color;
                });
                window.brainMapInstance
                    .nodeColor(node => node.isError ? 'rgba(255, 0, 68, 1)' : getGroupColor(node.group))
                    .linkColor(() => 'rgba(255,255,255,0.2)');
            }
        });

        // Add some basic light
        window.brainMapInstance.d3Force('charge').strength(-120);
        
        setTimeout(() => {
            window.scanSystemErrors();
        }, 1000);
    };

    window.searchBrainMap = function(query) {
        if (!window.brainMapInstance || !window.brainMapData) return;
        query = query.toLowerCase();

        window.brainMapData.nodes.forEach(node => {
            if (query && node.id.toLowerCase().includes(query)) {
                node.__color = 'rgba(255, 255, 255, 1)';
            } else {
                delete node.__color;
            }
        });

        window.brainMapInstance
            .nodeColor(node => node.__color || (node.isError ? 'rgba(255, 0, 68, 1)' : getGroupColor(node.group)))
            .nodeVal(node => (query && node.id.toLowerCase().includes(query)) ? 10 : 1);
            
        // If there is exactly one good match, fly to it
        if (query.length > 3) {
            const matches = window.brainMapData.nodes.filter(n => n.id.toLowerCase().includes(query));
            if (matches.length === 1) {
                const node = matches[0];
                const distance = 40;
                const hypot = Math.hypot(node.x, node.y, node.z);
                const camPos = hypot > 0.001 
                    ? { x: node.x * (1 + distance/hypot), y: node.y * (1 + distance/hypot), z: node.z * (1 + distance/hypot) }
                    : { x: 0, y: 0, z: distance };
                
                window.brainMapInstance.cameraPosition(
                    camPos,
                    node,
                    2000
                );
            }
        }
    };

    window.highlightBrainStructure = function() {
        if (!window.selectedBrainNodeId || !window.brainMapInstance || !window.brainMapData) return;
        
        // Finde alle verknüpften Knoten
        const relatedNodeIds = new Set();
        relatedNodeIds.add(window.selectedBrainNodeId);
        
        window.brainMapData.links.forEach(l => {
            if (l.source.id === window.selectedBrainNodeId) relatedNodeIds.add(l.target.id);
            if (l.target.id === window.selectedBrainNodeId) relatedNodeIds.add(l.source.id);
        });

        // Setze Farbe auf grau für alle anderen
        window.brainMapInstance
            .nodeColor(node => {
                if (relatedNodeIds.has(node.id)) {
                    return node.__color || (node.isError ? 'rgba(255, 0, 68, 1)' : getGroupColor(node.group));
                }
                return 'rgba(50, 50, 50, 0.1)';
            })
            .linkColor(link => {
                if (relatedNodeIds.has(link.source.id) && relatedNodeIds.has(link.target.id)) {
                    return 'rgba(255,255,255,0.4)';
                }
                return 'rgba(255,255,255,0.02)';
            });
    };

    function getGroupColor(group) {
        switch(group) {
            case 2: return 'rgba(16, 185, 129, 0.8)';
            case 3: return 'rgba(59, 130, 246, 0.8)';
            case 4: return 'rgba(236, 72, 153, 0.8)';
            case 5: return 'rgba(245, 158, 11, 0.8)';
            default: return 'rgba(156, 163, 175, 0.8)';
        }
    }

    window.selectedBrainNodeId = null;

    window.generateBrainStructure = function() {
        if (!window.selectedBrainNodeId) return;
        
        new Audio('/shop/ai/sounds/project_brain/create_structure_in_project_brain.mp3').play().catch(e=>console.log(e));

        // Find node in DB via Livewire
        Livewire.dispatch('generate-neural-structure', { file_path: window.selectedBrainNodeId });
        
        window.dispatchEvent(new CustomEvent('ai-speech-feedback', { detail: { text: "Struktur wird generiert." } }));
    };

    window.scanSystemErrors = async function(realErrors = []) {
        if (!window.brainMapInstance || !window.brainMapData) return;
        
        let errorNodesFound = 0;
        window.brainMapData.nodes.forEach(node => {
            const errorData = realErrors.find(e => node.id === e.file_path);
            if (errorData) {
                node.isError = true;
                node.errorMessage = errorData.error_state;
                errorNodesFound++;
            } else {
                node.isError = false;
                node.errorMessage = null;
            }
        });

        // Update colors
        window.brainMapInstance.nodeColor(node => node.isError ? 'rgba(255, 0, 68, 1)' : getGroupColor(node.group));

        if (errorNodesFound > 0) {
            window.dispatchEvent(new CustomEvent('ai-speech-feedback', { detail: { text: "Warnung: Es wurden Systemanomalien im neuronalen Netzwerk gefunden." } }));
        } else {
            window.dispatchEvent(new CustomEvent('ai-speech-feedback', { detail: { text: "Das System-Gehirn arbeitet fehlerfrei." } }));
        }
        
        window.dispatchEvent(new CustomEvent('neural-scan-complete'));
    };

    window.addEventListener('ai-neural-scan-trigger', (e) => {
        let errors = [];
        if (e.detail && e.detail.length > 0) {
            // Livewire sometimes wraps the payload in an array
            const payload = e.detail[0];
            if (payload && payload.errors) errors = payload.errors;
        } else if (e.detail && e.detail.errors) {
            errors = e.detail.errors;
        }
        if (window.scanSystemErrors) window.scanSystemErrors(errors);
    });

    window.addEventListener('ai-fly-to-neural-node', (e) => {
        const id = e.detail.id;
        if (window.searchBrainMap) {
            window.searchBrainMap(id);
            setTimeout(() => {
                if (window.brainMapData && window.brainMapData.nodes) {
                    const node = window.brainMapData.nodes.find(n => n.id === id);
                    if (node && window.openNeuralNodeTile) {
                        window.openNeuralNodeTile(node);
                    }
                }
            }, 1600); // 1500ms is the camera flight time
        }
    });

    window.addEventListener('ai-neural-analysis-complete', (e) => {
        // Bericht wurde im Hintergrund erstellt, kein störendes alert() mehr
        console.log("Neural Analysis Complete:", e.detail.file_path);
    });

    window.addEventListener('system-brain-map-generated', () => {
        if (window.initBrainMap) {
            window.initBrainMap();
        }
    });
</script>
