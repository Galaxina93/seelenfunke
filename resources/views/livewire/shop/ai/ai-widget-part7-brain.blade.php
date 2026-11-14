<!-- Neurale Fehleranalyse (3D Projekt Gehirn) -->
<script>
    window.brainMapInstance = null;
    window.brainMapData = null;
    window.selectedBrainNodeId = null;

    window.copyBrainNodePath = function(btn) {
        const el = document.getElementById('brain-selected-node-name');
        const text = el ? (el.dataset.fullPath || el.innerText || '').trim() : '';
        if (!text) return;

        const finish = () => {
            if (!btn) return;
            const originalLabel = btn.dataset.originalLabel || btn.innerText;
            btn.dataset.originalLabel = originalLabel;
            btn.innerText = 'Kopiert!';
            setTimeout(() => { btn.innerText = originalLabel; }, 2000);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(finish).catch(() => fallbackCopy(text, finish));
        } else {
            fallbackCopy(text, finish);
        }
    };

    function fallbackCopy(text, cb) {
        try {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            if (cb) cb();
        } catch (e) {
            console.warn('Clipboard fallback failed:', e);
        }
    }

    function getGroupColor(group) {
        switch (group) {
            case 2: return 'rgba(16, 185, 129, 0.85)';  // Models
            case 3: return 'rgba(59, 130, 246, 0.85)';  // Controllers
            case 4: return 'rgba(236, 72, 153, 0.85)';  // Livewire
            case 5: return 'rgba(245, 158, 11, 0.80)';  // Views
            case 6: return 'rgba(139, 92, 246, 0.85)';  // Routes
            case 7: return 'rgba(148, 163, 184, 0.80)'; // Config
            case 8: return 'rgba(6, 182, 212, 0.85)';   // Services
            case 9: return 'rgba(249, 115, 22, 0.85)';  // Commands
            default: return 'rgba(156, 163, 175, 0.80)';
        }
    }

    function getNodeRenderColor(node) {
        if (node.__color) return node.__color;
        if (node.isError) return 'rgba(255, 0, 68, 1)'; // Fehlerkern (Rot)
        if (node.isErrorNeighbor) return 'rgba(245, 158, 11, 0.95)'; // Verbundene Nachbardatei um Fehlerkern
        return getGroupColor(node.group);
    }

    function getNodeRenderVal(node) {
        if (node.__val) return node.__val;
        if (node.isError) return 8;
        if (node.isErrorNeighbor) return 3;
        return 1;
    }

    function getLinkEndpointId(ep) {
        return (ep && typeof ep === 'object') ? ep.id : ep;
    }

    function isErrorConnectedLink(link) {
        if (link.isErrorLink) return true;
        if (!window.brainMapData || !window.brainMapData.nodes) return false;
        const srcId = getLinkEndpointId(link.source);
        const tgtId = getLinkEndpointId(link.target);
        const srcNode = window.brainMapData.nodes.find(n => n.id === srcId);
        const tgtNode = window.brainMapData.nodes.find(n => n.id === tgtId);
        return Boolean((srcNode && srcNode.isError) || (tgtNode && tgtNode.isError));
    }

    window.initBrainMap = async function() {
        if (typeof ForceGraph3D === 'undefined') {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = "//unpkg.com/3d-force-graph";
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        if (window.brainMapInstance) {
            try {
                window.brainMapInstance.zoomToFit(1000, 50);
            } catch (e) {
                console.warn('zoomToFit skipped due to layout instability:', e);
            }
            window.scanSystemErrors();
            return;
        }

        const container = document.getElementById('brain-canvas-container');
        if (!container) return;

        if (container.clientWidth === 0 || container.clientHeight === 0) {
            setTimeout(window.initBrainMap, 100);
            return;
        }

        try {
            const response = await fetch('/api/ai/system-brain-map?t=' + Date.now(), {
                headers: { 'Accept': 'application/json' }
            });
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

        const initialErrorCount = (window.brainMapData && window.brainMapData.error_summary)
            ? (window.brainMapData.error_summary.error_core_count || 0)
            : 0;
        window.dispatchEvent(new CustomEvent('brain-errors-updated', { detail: { count: initialErrorCount } }));

        window.brainMapInstance = ForceGraph3D()(container)
            .width(window.innerWidth)
            .height(window.innerHeight)
            .graphData(window.brainMapData)
            .nodeLabel(node => node.isError ? `[FEHLERKERN] ${node.id}` : node.id)
            .nodeColor(node => getNodeRenderColor(node))
            .nodeVal(node => getNodeRenderVal(node))
            .nodeRelSize(4)
            .linkColor(link => isErrorConnectedLink(link) ? 'rgba(255, 68, 102, 0.78)' : 'rgba(255,255,255,0.12)')
            .linkWidth(link => isErrorConnectedLink(link) ? 1.8 : 0.5)
            .backgroundColor('rgba(0,0,0,0)');

        window.addEventListener('resize', () => {
            if (window.brainMapInstance) {
                window.brainMapInstance
                    .width(window.innerWidth)
                    .height(window.innerHeight);
            }
        });

        window.brainMapInstance.onNodeClick(node => {
            flyCameraToNode(node, 1500);

            if (node.isError) {
                Livewire.dispatch('execute-system-command', {
                    payload: {
                        command: 'system_analyze_neural_error',
                        args: {
                            file_path: node.id,
                            error_context: node.errorMessage || 'Kein detaillierter Kontext verfügbar.'
                        }
                    }
                });

                window.dispatchEvent(new CustomEvent('ai-speech-feedback', {
                    detail: { text: "Fehlerkern erkannt in Datei: " + node.name }
                }));
            } else {
                window.dispatchEvent(new CustomEvent('ai-speech-feedback', { detail: { text: node.name } }));
            }

            window.openNeuralNodeTile(node);
        });

        window.openNeuralNodeTile = function(node) {
            window.selectedBrainNodeId = node.id;
            new Audio('/shop/ai/sounds/project_brain/click_file_in_project_brain.mp3').play().catch(() => {});

            const tileContainer = document.getElementById('brain-selected-node-container');
            const nameSpan = document.getElementById('brain-selected-node-name');
            const mobileNameSpan = document.getElementById('brain-selected-node-mobile-name');
            const depsList = document.getElementById('brain-dependencies-list');
            const errorTile = document.getElementById('brain-error-tile');
            const errorContent = document.getElementById('brain-error-content');

            if (tileContainer && nameSpan) {
                const fullPath = node.id || node.name || '';
                const shortName = fullPath.split('/').pop() || fullPath;
                nameSpan.innerText = fullPath;
                nameSpan.dataset.fullPath = fullPath;
                if (mobileNameSpan) {
                    mobileNameSpan.innerText = shortName;
                    mobileNameSpan.title = fullPath;
                }
                tileContainer.classList.remove('hidden');
                tileContainer.classList.add('flex');

                const incoming = [];
                const outgoing = [];

                if (window.brainMapData && Array.isArray(window.brainMapData.links)) {
                    window.brainMapData.links.forEach(l => {
                        const sId = getLinkEndpointId(l.source);
                        const tId = getLinkEndpointId(l.target);
                        if (tId === node.id && sId !== node.id) {
                            const sNode = (typeof l.source === 'object') ? l.source : (window.brainMapData.nodes.find(n => n.id === sId) || { id: sId, name: sId });
                            if (!incoming.some(i => i.id === sNode.id)) incoming.push(sNode);
                        } else if (sId === node.id && tId !== node.id) {
                            const tNode = (typeof l.target === 'object') ? l.target : (window.brainMapData.nodes.find(n => n.id === tId) || { id: tId, name: tId });
                            if (!outgoing.some(o => o.id === tNode.id)) outgoing.push(tNode);
                        }
                    });
                }

                if (Array.isArray(node.incomingFiles)) {
                    node.incomingFiles.forEach(fp => {
                        if (!incoming.some(i => i.id === fp)) {
                            const found = (window.brainMapData && window.brainMapData.nodes.find(n => n.id === fp)) || { id: fp, name: fp.split('/').pop() };
                            incoming.push(found);
                        }
                    });
                }
                if (Array.isArray(node.outgoingFiles)) {
                    node.outgoingFiles.forEach(fp => {
                        if (!outgoing.some(o => o.id === fp)) {
                            const found = (window.brainMapData && window.brainMapData.nodes.find(n => n.id === fp)) || { id: fp, name: fp.split('/').pop() };
                            outgoing.push(found);
                        }
                    });
                }

                window.dispatchEvent(new CustomEvent('brain-node-selected', {
                    detail: {
                        id: fullPath,
                        depsCount: incoming.length + outgoing.length,
                        incomingCount: incoming.length,
                        outgoingCount: outgoing.length,
                        isError: Boolean(node.isError),
                        isErrorNeighbor: Boolean(node.isErrorNeighbor)
                    }
                }));

                if (depsList) {
                    depsList.innerHTML = '';
                    depsList.scrollTop = 0;

                    const appendSectionHeader = (label, badgeClass) => {
                        const hdr = document.createElement('div');
                        hdr.className = 'text-[10px] font-bold uppercase tracking-wider pt-1 pb-0.5 ' + badgeClass;
                        hdr.textContent = label;
                        depsList.appendChild(hdr);
                    };

                    const appendNeighborItem = (arrow, relNode, isIncoming) => {
                        const relPath = relNode.id || relNode.name || '';
                        const relFile = relPath.split('/').pop() || relPath;

                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = relNode.isError
                            ? 'w-full text-left px-2.5 py-2 rounded-xl bg-rose-950/50 hover:bg-rose-900/60 active:bg-rose-800/70 border border-rose-500/40 transition-colors flex flex-col gap-0.5 cursor-pointer'
                            : (isIncoming
                                ? 'w-full text-left px-2.5 py-2 rounded-xl bg-amber-500/[0.06] hover:bg-amber-500/15 active:bg-amber-500/25 border border-amber-500/20 transition-colors flex flex-col gap-0.5 cursor-pointer'
                                : 'w-full text-left px-2.5 py-2 rounded-xl bg-emerald-500/[0.06] hover:bg-emerald-500/15 active:bg-emerald-500/25 border border-emerald-500/20 transition-colors flex flex-col gap-0.5 cursor-pointer');

                        const topRow = document.createElement('div');
                        topRow.className = 'flex items-center justify-between gap-2 w-full';

                        const titleSpan = document.createElement('span');
                        titleSpan.className = relNode.isError
                            ? 'text-xs font-bold text-rose-200 truncate'
                            : (isIncoming ? 'text-xs font-semibold text-amber-200 truncate' : 'text-xs font-semibold text-emerald-100 truncate');
                        titleSpan.textContent = arrow + ' ' + relFile;
                        topRow.appendChild(titleSpan);

                        if (relNode.isError) {
                            const errBadge = document.createElement('span');
                            errBadge.className = 'px-1.5 py-0.2 rounded bg-rose-600 text-white text-[9px] font-bold uppercase shrink-0';
                            errBadge.textContent = 'Fehler';
                            topRow.appendChild(errBadge);
                        }

                        const pathRow = document.createElement('div');
                        pathRow.className = 'text-[10px] text-gray-400 break-all leading-snug';
                        pathRow.textContent = relPath;

                        btn.appendChild(topRow);
                        btn.appendChild(pathRow);
                        btn.onclick = () => {
                            if (window.searchBrainMap) window.searchBrainMap(relPath);
                        };
                        depsList.appendChild(btn);
                    };

                    if (incoming.length === 0 && outgoing.length === 0) {
                        const emptySpan = document.createElement('div');
                        emptySpan.className = 'text-emerald-400/60 italic py-3 text-center';
                        emptySpan.textContent = 'Keine Abhängigkeiten bekannt';
                        depsList.appendChild(emptySpan);
                    } else {
                        if (incoming.length > 0) {
                            appendSectionHeader('← Aufrufer (' + incoming.length + ')', 'text-amber-300/90');
                            incoming.forEach(rel => appendNeighborItem('←', rel, true));
                        }
                        if (outgoing.length > 0) {
                            appendSectionHeader('→ Referenziert (' + outgoing.length + ')', 'text-emerald-300/90');
                            outgoing.forEach(rel => appendNeighborItem('→', rel, false));
                        }
                    }
                }

                if (errorTile && errorContent) {
                    const headerSpan = errorTile.querySelector('span');
                    errorContent.innerHTML = '';
                    errorContent.scrollTop = 0;

                    if (node.isError) {
                        errorTile.classList.remove('border-emerald-500/40', 'border-amber-500/60');
                        errorTile.classList.add('border-rose-500/70');
                        if (headerSpan) {
                            headerSpan.className = "text-[10px] text-rose-400 font-bold uppercase tracking-widest shrink-0";
                            headerSpan.textContent = "Fehlerkern (System-Log)";
                        }
                        errorContent.className = "brain-touch-scroll flex-1 min-h-0 w-full overflow-y-auto overscroll-contain touch-pan-y custom-scrollbar text-xs font-mono text-rose-100 pr-1.5 whitespace-pre-wrap pointer-events-auto";
                        errorContent.textContent = node.errorMessage || "Fehler in dieser Datei im System-Log aktiv.";
                    } else if (node.isErrorNeighbor && Array.isArray(node.connectedErrorCores) && node.connectedErrorCores.length > 0) {
                        errorTile.classList.remove('border-emerald-500/40', 'border-rose-500/70');
                        errorTile.classList.add('border-amber-500/60');
                        if (headerSpan) {
                            headerSpan.className = "text-[10px] text-amber-400 font-bold uppercase tracking-widest shrink-0";
                            headerSpan.textContent = "Nachbar eines Fehlerkerns";
                        }
                        errorContent.className = "brain-touch-scroll flex-1 min-h-0 w-full overflow-y-auto overscroll-contain touch-pan-y custom-scrollbar text-xs font-mono text-amber-100 pr-1.5 space-y-1.5 pointer-events-auto";
                        node.connectedErrorCores.forEach(coreId => {
                            const row = document.createElement('button');
                            row.type = 'button';
                            row.className = 'w-full text-left px-2.5 py-2 rounded-xl bg-rose-950/40 hover:bg-rose-900/50 border border-rose-500/30 cursor-pointer text-rose-200 break-all';
                            row.textContent = 'Verbunden mit Fehlerkern: ' + coreId;
                            row.onclick = () => { if (window.searchBrainMap) window.searchBrainMap(coreId); };
                            errorContent.appendChild(row);
                        });
                    } else if (node.blueprint) {
                        const bp = node.blueprint;
                        errorTile.classList.remove('border-rose-500/70', 'border-amber-500/60');
                        errorTile.classList.add('border-emerald-500/40');
                        if (headerSpan) {
                            headerSpan.className = "text-[10px] text-emerald-400/80 font-bold uppercase tracking-widest shrink-0";
                            headerSpan.textContent = "Typenschild & Status";
                        }
                        errorContent.className = "brain-touch-scroll flex-1 min-h-0 w-full overflow-y-auto overscroll-contain touch-pan-y custom-scrollbar text-[11px] font-mono text-emerald-100/90 pr-1.5 space-y-1.5 pointer-events-auto";
                        const regs = (bp.regulation_labels && bp.regulation_labels.length) ? bp.regulation_labels.join(', ') : 'Intern';

                        const addLine = (txt, cls) => {
                            const d = document.createElement('div');
                            d.className = cls;
                            d.textContent = txt;
                            errorContent.appendChild(d);
                        };
                        addLine(bp.label || node.name, 'font-bold text-white');
                        addLine((bp.department_label || '') + ' • ' + (bp.retention_label || ''), 'text-[10px] text-emerald-300/90');
                        addLine('KI-Guardrail: ' + (bp.ai_access_label || ''), 'text-[10px] text-amber-300/90');
                        addLine('Gesetze: ' + regs, 'text-[10px] text-indigo-300/90');
                    } else {
                        errorTile.classList.remove('border-rose-500/70', 'border-amber-500/60');
                        errorTile.classList.add('border-emerald-500/40');
                        if (headerSpan) {
                            headerSpan.className = "text-[10px] text-emerald-400/80 font-bold uppercase tracking-widest shrink-0";
                            headerSpan.textContent = "Typenschild & Status";
                        }
                        errorContent.className = "brain-touch-scroll flex-1 min-h-0 w-full overflow-y-auto overscroll-contain touch-pan-y custom-scrollbar text-xs font-mono text-emerald-100/80 pr-1.5 flex items-center pointer-events-auto";
                        errorContent.innerHTML = '<span class="text-emerald-400 flex items-center gap-1.5"><svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Systemstatus 100% OK</span>';
                    }
                }
            }
        };

        window.closeNeuralNodeTile = function() {
            if (window.selectedBrainNodeId) {
                new Audio('/shop/ai/sounds/project_brain/close_file_in_project_brain.mp3').play().catch(() => {});
            }
            window.selectedBrainNodeId = null;
            const tileContainer = document.getElementById('brain-selected-node-container');
            if (tileContainer) {
                tileContainer.classList.add('hidden');
                tileContainer.classList.remove('flex');
            }

            if (window.brainMapInstance && window.brainMapData) {
                window.brainMapData.nodes.forEach(node => {
                    delete node.__color;
                    delete node.__val;
                });
                refreshBrainVisuals();
            }
        };

        window.brainMapInstance.onBackgroundClick(() => {
            window.closeNeuralNodeTile();
        });

        window.brainMapInstance.d3Force('charge').strength(-120);

        setTimeout(() => {
            window.scanSystemErrors();
        }, 600);
    };

    function flyCameraToNode(node, duration = 1600) {
        if (!window.brainMapInstance || !node) return;
        const distance = 42;
        const hypot = Math.hypot(node.x || 0, node.y || 0, node.z || 0);
        const camPos = hypot > 0.001
            ? { x: node.x * (1 + distance / hypot), y: node.y * (1 + distance / hypot), z: node.z * (1 + distance / hypot) }
            : { x: 0, y: 0, z: distance };
        window.brainMapInstance.cameraPosition(camPos, node, duration);
    }

    function refreshBrainVisuals() {
        if (!window.brainMapInstance) return;
        window.brainMapInstance
            .nodeColor(node => getNodeRenderColor(node))
            .nodeVal(node => getNodeRenderVal(node))
            .linkColor(link => isErrorConnectedLink(link) ? 'rgba(255, 68, 102, 0.78)' : 'rgba(255,255,255,0.12)')
            .linkWidth(link => isErrorConnectedLink(link) ? 1.8 : 0.5);
    }

    window.searchBrainMap = function(query) {
        if (!window.brainMapInstance || !window.brainMapData) return;
        const q = (query || '').trim().toLowerCase();

        window.brainMapData.nodes.forEach(node => {
            if (q && node.id.toLowerCase().includes(q)) {
                node.__color = node.isError ? 'rgba(255, 0, 68, 1)' : 'rgba(255, 255, 255, 1)';
                node.__val = 10;
            } else {
                delete node.__color;
                delete node.__val;
            }
        });

        refreshBrainVisuals();

        if (q.length > 2) {
            const exact = window.brainMapData.nodes.find(n => n.id.toLowerCase() === q);
            const matches = exact ? [exact] : window.brainMapData.nodes.filter(n => n.id.toLowerCase().includes(q));
            if (matches.length === 1) {
                flyCameraToNode(matches[0], 1600);
                if (window.openNeuralNodeTile) {
                    window.openNeuralNodeTile(matches[0]);
                }
            }
        }
    };

    window.filterBrainByBlueprint = function(mode) {
        if (!window.brainMapInstance || !window.brainMapData) return;

        window.brainMapData.nodes.forEach(node => {
            delete node.__color;
            delete node.__val;
            if (mode === 'all') return;

            if (mode === 'errors') {
                if (node.isError) {
                    node.__color = 'rgba(255, 0, 68, 1)';
                    node.__val = 10;
                } else if (node.isErrorNeighbor) {
                    node.__color = 'rgba(245, 158, 11, 0.95)';
                    node.__val = 4;
                } else {
                    node.__color = 'rgba(55, 65, 81, 0.10)';
                    node.__val = 1;
                }
                return;
            }

            const bp = node.blueprint || null;
            let matched = false;
            let highlightColor = 'rgba(245, 158, 11, 1)';

            if (bp) {
                if (mode === 'gobd' && bp.is_gobd_protected) {
                    matched = true;
                    highlightColor = 'rgba(245, 158, 11, 1)';
                } else if (mode === 'gdpr_export' && bp.is_gdpr_art15_exportable) {
                    matched = true;
                    highlightColor = 'rgba(16, 185, 129, 1)';
                } else if (mode === 'ai_protected' && !bp.allows_ai_write) {
                    matched = true;
                    highlightColor = 'rgba(244, 63, 94, 1)';
                } else if (mode === 'channels' && bp.external_channels && bp.external_channels.length > 0) {
                    matched = true;
                    highlightColor = 'rgba(6, 182, 212, 1)';
                }
            }

            node.__color = matched ? highlightColor : 'rgba(55, 65, 81, 0.14)';
            node.__val = matched ? 6 : 1;
        });

        refreshBrainVisuals();
    };

    window.highlightBrainStructure = function() {
        if (!window.selectedBrainNodeId || !window.brainMapInstance || !window.brainMapData) return;

        const relatedNodeIds = new Set([window.selectedBrainNodeId]);
        window.brainMapData.links.forEach(l => {
            const sId = getLinkEndpointId(l.source);
            const tId = getLinkEndpointId(l.target);
            if (sId === window.selectedBrainNodeId) relatedNodeIds.add(tId);
            if (tId === window.selectedBrainNodeId) relatedNodeIds.add(sId);
        });

        window.brainMapInstance
            .nodeColor(node => relatedNodeIds.has(node.id) ? getNodeRenderColor(node) : 'rgba(50, 50, 50, 0.10)')
            .linkColor(link => {
                const sId = getLinkEndpointId(link.source);
                const tId = getLinkEndpointId(link.target);
                if (relatedNodeIds.has(sId) && relatedNodeIds.has(tId)) {
                    return isErrorConnectedLink(link) ? 'rgba(255, 68, 102, 0.90)' : 'rgba(255,255,255,0.45)';
                }
                return 'rgba(255,255,255,0.02)';
            });
    };

    window.generateBrainStructure = function() {
        if (!window.selectedBrainNodeId) return;
        new Audio('/shop/ai/sounds/project_brain/create_structure_in_project_brain.mp3').play().catch(() => {});
        Livewire.dispatch('generate-neural-structure', { file_path: window.selectedBrainNodeId });
        window.dispatchEvent(new CustomEvent('ai-speech-feedback', { detail: { text: "Struktur wird generiert." } }));
    };

    function applyErrorOverlayToGraph(errorsList, neighborsMap = {}) {
        if (!window.brainMapInstance || !window.brainMapData) return 0;

        const errorByPath = new Map();
        (Array.isArray(errorsList) ? errorsList : []).forEach(err => {
            if (err && err.file_path) {
                errorByPath.set(err.file_path, err);
            }
        });

        let errorNodesFound = 0;
        let firstErrorNode = null;

        window.brainMapData.nodes.forEach(node => {
            const err = errorByPath.get(node.id);
            if (err) {
                node.isError = true;
                node.isErrorNeighbor = false;
                node.errorMessage = err.error_state || 'Fehler im System-Log aktiv.';
                node.connectedFiles = err.connected_files || [];
                node.incomingFiles = err.incoming_files || [];
                node.outgoingFiles = err.outgoing_files || [];
                errorNodesFound++;
                if (!firstErrorNode) firstErrorNode = node;
            } else if (neighborsMap && Array.isArray(neighborsMap[node.id])) {
                node.isError = false;
                node.isErrorNeighbor = true;
                node.connectedErrorCores = neighborsMap[node.id];
                node.errorMessage = null;
            } else {
                node.isError = false;
                node.isErrorNeighbor = false;
                node.connectedErrorCores = [];
                node.errorMessage = null;
            }
        });

        window.brainMapData.links.forEach(link => {
            const sId = getLinkEndpointId(link.source);
            const tId = getLinkEndpointId(link.target);
            link.isErrorLink = errorByPath.has(sId) || errorByPath.has(tId);
        });

        refreshBrainVisuals();
        window.dispatchEvent(new CustomEvent('brain-errors-updated', { detail: { count: errorNodesFound } }));

        if (firstErrorNode) {
            flyCameraToNode(firstErrorNode, 1500);
            if (window.openNeuralNodeTile) {
                window.openNeuralNodeTile(firstErrorNode);
            }
        }

        return errorNodesFound;
    }

    window.scanSystemErrors = async function(realErrors = null, providedNeighbors = null) {
        if (!window.brainMapInstance || !window.brainMapData) return;

        let errorsList = realErrors;
        let neighborsMap = providedNeighbors || {};

        if (!Array.isArray(errorsList)) {
            try {
                const res = await fetch('/api/ai/system-brain-errors?t=' + Date.now(), {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const payload = await res.json();
                    errorsList = Array.isArray(payload.errors) ? payload.errors : [];
                    neighborsMap = payload.neighbors || {};
                } else {
                    errorsList = [];
                }
            } catch (e) {
                console.warn('SystemLog-Fehlerabruf fehlgeschlagen:', e);
                errorsList = [];
            }
        }

        const errorNodesFound = applyErrorOverlayToGraph(errorsList, neighborsMap);

        if (errorNodesFound > 0) {
            window.dispatchEvent(new CustomEvent('ai-speech-feedback', {
                detail: { text: "Achtung: " + errorNodesFound + " Fehlerkern im Projekt Gehirn rot markiert." }
            }));
        }

        window.dispatchEvent(new CustomEvent('neural-scan-complete'));
    };

    window.runBrainSystemCheck = async function() {
        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const token = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const res = await fetch('/api/ai/system-brain-check', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({})
            });

            if (!res.ok) {
                window.dispatchEvent(new CustomEvent('brain-system-check-complete', {
                    detail: { healthy: false, label: 'Prüfung fehlgeschlagen' }
                }));
                return;
            }

            const data = await res.json();
            const report = data.report || {};
            const errorsList = Array.isArray(data.errors) ? data.errors : [];
            const neighborsMap = data.neighbors || {};
            const foundCores = applyErrorOverlayToGraph(errorsList, neighborsMap);
            const healthy = Boolean(report.is_healthy) && foundCores === 0;

            const totalChecks = report.total_checks || 0;
            const passedChecks = report.passed_checks || 0;
            const label = healthy
                ? `100% OK (${passedChecks}/${totalChecks})`
                : `${report.failed_checks || foundCores} Fehler`;

            window.dispatchEvent(new CustomEvent('brain-system-check-complete', {
                detail: { healthy, label, report }
            }));

            window.dispatchEvent(new CustomEvent('ai-speech-feedback', {
                detail: {
                    text: healthy
                        ? `Systemprüfung abgeschlossen. Alle ${totalChecks} Prüfungen sind fehlerfrei.`
                        : `Systemprüfung abgeschlossen. Es wurden Probleme erkannt und rot im Projekt Gehirn hervorgehoben.`
                }
            }));
        } catch (e) {
            console.error('Systemprüfung fehlgeschlagen:', e);
            window.dispatchEvent(new CustomEvent('brain-system-check-complete', {
                detail: { healthy: false, label: 'Fehler' }
            }));
        }
    };

    window.addEventListener('ai-neural-scan-trigger', (e) => {
        let errors = [];
        let neighbors = {};
        if (e.detail && Array.isArray(e.detail) && e.detail.length > 0) {
            const payload = e.detail[0];
            if (payload && Array.isArray(payload.errors)) errors = payload.errors;
            if (payload && payload.neighbors) neighbors = payload.neighbors;
        } else if (e.detail && Array.isArray(e.detail.errors)) {
            errors = e.detail.errors;
            neighbors = e.detail.neighbors || {};
        }
        if (window.scanSystemErrors) window.scanSystemErrors(errors, neighbors);
    });

    window.addEventListener('ai-fly-to-neural-node', (e) => {
        const id = e.detail ? (e.detail.id || (Array.isArray(e.detail) && e.detail[0] ? e.detail[0].id : null)) : null;
        if (id && window.searchBrainMap) {
            window.searchBrainMap(id);
        }
    });

    window.addEventListener('ai-neural-analysis-complete', (e) => {
        console.log("Neural Analysis Complete:", e.detail ? e.detail.file_path : '');
    });

    window.addEventListener('system-brain-map-generated', () => {
        window.brainMapInstance = null;
        if (window.initBrainMap) {
            window.initBrainMap();
        }
    });
</script>
