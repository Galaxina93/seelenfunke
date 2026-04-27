<script>
    document.addEventListener('alpine:init', () => {
        let t3 = {
            scene: null,
            camera: null,
            renderer: null,
            coreMesh: null,
            hitboxMesh: null,
            coreMaterial: null,
            raymarchUniforms: null,
            coreLight: null,
            cssRenderer: null,
            cssObject: null,
            animationId: null,
            controls: null,
            raycaster: null,
            mouse: null,
            mouseMoveListener: null,
            clickListener: null,
            startTime: null,
            shutdownTime: null,
            isShuttingDown: false,
            bgAudio: null,
            heartbeatAudio: null,
        };

        Alpine.data('funkiView', (initialAgentColor = 'emerald-500', initialAgentId = null, initialState = 'good', initialSparks = 42, avgProfit = 0, totalOrders = 0, lastSync = '', initialVolume = 15, initialAgentName = 'System') => ({
            activeAgentName: initialAgentName,
            // State
            agentColor: initialAgentColor,
            activeAgentId: initialAgentId,
            showFunkiView: false,
            showErrorPanel: false,
            showDebugLog: false,
            showTasks: false,
            showFiles: false,
            isAudioMuted: localStorage.getItem('funki_isAudioMuted') !== null ? localStorage.getItem('funki_isAudioMuted') === 'true' : true, // Default to muted as requested
            bgVolume: initialVolume,
            systemState: initialState, // 'good', 'warning', 'error', true, false
            activeSparks: initialSparks,
            avgProfit: avgProfit + ' €',
            totalOrders: totalOrders,
            lastSync: lastSync,
            errorText: '',
            chatHistory: [],
            idleProgress: 0, // 0-100 indicating time until spontaneous action
            isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
            // Voice AI State
            listening: false,
            thinking: false,
            isLiveMode: false,
            agentWakeWord: '{{ addslashes($agentWakeWord ?? "funkira") }}'.toLowerCase(),
            allowSpontaneous: true, // Default: On (Spontaneous Self-Analysis)
            recognition: null,
            synthesis: window.speechSynthesis,
            tokenUsage: '',
            funkiLogs: [],
            watchdogTimer: null,
            lastActivityTime: Date.now(),
            restartCount: 0,
            lastRestartTime: 0,
            isSpeaking: false,
            activeAgentName: initialAgentName, // Neu: Speichert den Namen des antwortenden Agenten
            agentTtsEnabled: false, // Prevents calling TTS apis

            chatAbortController: null,
            voiceAbortController: null,

            // Live API State
            isLiveMode: false,
            liveWs: null,
            audioContext: null,

            updateAgentConfig(color, name, wakeWord, agentId) {
                this.agentColor = color || 'emerald-500';
                if (name) this.activeAgentName = name;
                if (wakeWord) this.agentWakeWord = wakeWord.toLowerCase();
                if (agentId) this.activeAgentId = agentId;
                this.updateCoreColor(true); 
                
                if (this.isLiveMode) {
                    this.toggleLiveMode(); // Gracefully turn off Live Mode (UI & WS)
                    setTimeout(() => {
                        this.toggleLiveMode(); // Gracefully turn it back on after cleanup
                    }, 1200); // 1.2s gives the old AudioContext and WebSocket enough time to fully close
                }
            },

            getColorHex(colorStr) {
                const map = {
                    'red': 0xef4444,
                    'orange': 0xf97316,
                    'amber': 0xf59e0b,
                    'yellow': 0xeab308,
                    'lime': 0x84cc16,
                    'green': 0x22c55e,
                    'emerald': 0x10b981,
                    'teal': 0x14b8a6,
                    'cyan': 0x06b6d4,
                    'sky': 0x0ea5e9,
                    'blue': 0x3b82f6,
                    'indigo': 0x6366f1,
                    'violet': 0x8b5cf6,
                    'purple': 0xa855f7,
                    'fuchsia': 0xd946ef,
                    'pink': 0xec4899,
                    'rose': 0xf43f5e
                };
                for (const key in map) {
                    if (colorStr.includes(key)) return map[key];
                }
                return 0x10b981; // fallback emerald
            },

            isOutputActive() {
                return this.isSpeaking || this.thinking;
            },

            // --- AI VOICE CHAT LOGIC ---
            toggleLiveMode() {
                this.isLiveMode = !this.isLiveMode;
                if (this.isLiveMode) {
                    if (this.listening) {
                        this.listening = false;
                        if (this.recognition) {
                            this.recognition.onstart = null;
                            this.recognition.onend = null;
                            try { this.recognition.abort(); } catch(e) {}
                            try { this.recognition.stop(); } catch(e) {}
                        }
                        this.stopSpeech();
                    }
                    this.systemState = 'good';
                    this.thinking = true; // Show loading state
                    this.updateCoreColor(true);
                    
                    if (typeof this.initLiveMode === 'function') {
                        this.initLiveMode();
                    } else {
                        console.error("Live Mode Logik (part7) fehlt!");
                        this.isLiveMode = false;
                        this.thinking = false;
                        this.updateCoreColor(true);
                    }
                } else {
                    if (typeof this.stopLiveMode === 'function') {
                        this.stopLiveMode();
                    }
                }
            },

            toggleSpeech() {
                if (this.thinking || this.isLiveMode) return;

                if (this.isMobile) {
                    return;
                }

                if (this.listening) {
                    this.listening = false;
                    if (this.recognition) {
                        this.recognition.onend = null;
                        try { this.recognition.abort(); } catch(e) {}
                        this.recognition = null;
                    }
                } else {
                    if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                    if (this.synthesis && this.synthesis.speaking) this.synthesis.cancel();

                    this.listening = true;
                    this.restartCount = 0;
                    if (!this.recognition) this.initSpeech();
                    this.startSafeRecognition(200);
                }
            },

            fullStop() {
                if (this.isLiveMode) {
                    this.toggleLiveMode();
                    return;
                }
                this.listening = false;
                if (this.recognition) {
                    this.recognition.onstart = null;
                    this.recognition.onend = null;
                    try { this.recognition.abort(); } catch(e) {}
                    try { this.recognition.stop(); } catch(e) {}
                }
                this.stopSpeech();
                this.updateCoreColor();
            },

            startPushToTalk() {
                if (this.thinking || this.isOutputActive()) return;

                if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                if (this.synthesis) this.synthesis.cancel();

                this.playClickSound();
                this.listening = true;
                this.updateCoreColor();
                this.restartCount = 0;

                this.initSpeech();
                this.startSafeRecognition(100);
            },

            stopPushToTalk() {
                if (!this.listening || !this.recognition) return;
                this.playUnclickSound();
                this.listening = false;
                this.updateCoreColor();
                this.recognition.stop(); 
            },

            async sendToAI(promptText, isSpontaneous = false) {
                this.thinking = true;
                this.updateCoreColor();

                if (this.chatAbortController) this.chatAbortController.abort();
                this.chatAbortController = new AbortController();

                const pulseAudio = document.getElementById('audio-funki-pulse');
                if (pulseAudio) {
                    pulseAudio.volume = 0.5;
                    pulseAudio.play().catch(e => console.log('Pulse blocked', e));
                }

                try {
                    if (!isSpontaneous) {
                        this.chatHistory.push({ role: 'user', content: promptText });
                        this.funkiLogs.push({ role: 'user', time: new Date().toLocaleTimeString('de-DE'), message: promptText });
                    } else {
                        this.chatHistory.push({ role: 'system', content: 'SYSTEM-BEFEHL (Verdeckt): ' + promptText });
                        this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: 'Spontane Analyse ausgelöst.' });
                    }

                    const response = await fetch('/api/ai/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ 
                            history: this.chatHistory,
                            agent_id: this.activeAgentId || {!! $widgetAgent ? "'" . $widgetAgent->id . "'" : 'null' !!}
                        }),
                        signal: this.chatAbortController.signal
                    });

                    let data;
                    try {
                        const clonedResponse = response.clone();
                        data = await response.json();
                    } catch (jsonErr) {
                        this.thinking = false;
                        this.updateCoreColor();

                        let errorTextHTML = "Unbekannter Fehler beim Lesen der Antwort.";
                        try {
                            errorTextHTML = await clonedResponse.text(); 
                        } catch(e) { /* Ignore */ }
                        
                        console.error("SyntaxError Fallback:", errorTextHTML);

                        this.errorText = "⚠️ Subraum Kommunikation abgebrochen:\nDer Server hat eine HTML-Fehlerseite (Status " + response.status + ") statt JSON zurückgegeben.\n\nDies bedeutet meist, dass der API Code abgestürzt ist.\n\nAuszug:\n" + errorTextHTML.substring(0, 300) + "...\n\nBitte sende diesen Fehler an Gemini!";
                        this.showErrorPanel = true;
                        this.systemState = 'error';
                        this.updateCoreColor(true);
                        return;
                    }

                    if(data.status === 'success') {
                        if (data.history) {
                            this.chatHistory = data.history;
                        }

                        if (data.context_data && data.context_data.length > 0) {
                            data.context_data.forEach(ctx => {
                                this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: `Werkzeug: ${ctx.function}` });
                            });
                        }
                        
                        if (data.events_data && data.events_data.length > 0) {
                            data.events_data.forEach(evt => {
                                if (evt.name === 'open-ai-visualization') {
                                    this.playClickSound(); 
                                    Livewire.dispatch('open-ai-visualization', { payload: evt.detail });
                                } else if (evt.type === 'navigate') {
                                    if (this.showFunkiView) {
                                        localStorage.setItem('funki_isOpen', 'false');
                                    }
                                    setTimeout(() => {
                                        window.location.href = evt.url;
                                    }, 400);
                                } else if (evt.type === 'dispatch') {
                                    window.dispatchEvent(new Event(evt.name));
                                }
                            });
                        }

                        if (data.agent_name) {
                            this.activeAgentName = data.agent_name;
                        }
                        
                        if (data.tts_enabled !== undefined) {
                            this.agentTtsEnabled = data.tts_enabled;
                        }

                        if (data.response) {
                            this.funkiLogs.push({ role: 'ai', time: new Date().toLocaleTimeString('de-DE'), message: data.response.replace(/\[.*?\]/s, '') });
                        }

                        if (this.funkiLogs.length > 15) this.funkiLogs = this.funkiLogs.slice(-15);

                        if (data.usage && data.usage.total_tokens) {
                            this.tokenUsage = `TOKENS: ${data.usage.total_tokens}`;
                        }

                        if (data.audio) {
                            this.playAudioBase64(data.audio);
                        } else if (data.response && data.response.trim() !== '') {
                            if (this.agentTtsEnabled) {
                                this.speakResponse(data.response); 
                            }
                        }
                    } else {
                        console.error("AI Error:", data);
                        this.speakResponse("Verbindung zum Schiffscomputer fehlgeschlagen.");
                        this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: `Fehler: API lieferte keinen Erfolgsstatus.` });
                        Livewire.dispatch('log-widget-error', { message: 'Widget API lieferte keinen Erfolgsstatus: ' + (data.error || 'Unbekannt') });
                        if (this.funkiLogs.length > 15) this.funkiLogs = this.funkiLogs.slice(-15);
                    }
                } catch (err) {
                    if (err.name === 'AbortError') {
                        console.log('AI Fetch aborted by user.');
                        return;
                    }
                    console.error("Fetch Error:", err);
                    this.speakResponse("Subraumkommunikation abgebrochen.");
                    this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: `Fehler: ${err.message}` });
                    Livewire.dispatch('log-widget-error', { message: 'Widget Fetch-Fehler: ' + err.message });
                    if (this.funkiLogs.length > 15) this.funkiLogs = this.funkiLogs.slice(-15);
                } finally {
                    this.thinking = false;
                    this.updateCoreColor();


                    const pulseAudio = document.getElementById('audio-funki-pulse');
                    if (pulseAudio) {
                        pulseAudio.pause();
                        pulseAudio.currentTime = 0;
                    }

                    setTimeout(() => {
                        if (this.continuousMode && !this.isOutputActive()) {
                            this.listening = true;
                            try { this.recognition.start(); } catch(e) {}
                        }
                    }, 100);
                }
            },

            renderAnalytics(contextData, aiResponseText = '') {
                if (!contextData || !Array.isArray(contextData)) return;

                const closeCommand = contextData.find(c => c.function === 'close_ui');
                if (closeCommand && closeCommand.data && closeCommand.data.status === 'success') {
                    this.showInfoPanel = false;
                    this.showChartPanel = false;
                    this.showErrorPanel = false;
                    this.tableData = [];
                    this.chartListData = [];
                    this.destroyCurrentChart();
                    this.showChartCanvas = false;
                    return; 
                }

                const switchCommand = contextData.find(c => c.function === 'system_switch_agent');
                if (switchCommand && switchCommand.data && switchCommand.data.status === 'success') {
                    if (switchCommand.data.agent_id) {
                        this.$wire.set('agentId', switchCommand.data.agent_id);
                        return;
                    }
                }

                let chartType = 'doughnut';
                let chartLabels = [];
                let chartDataset = [];
                let title = 'System Metriken';
                let foundData = false;

                this.chartListData = []; 
                this.tableData = [];     
                this.tableHeaders = [];  

                const aiText = aiResponseText.toLowerCase();
                const lastUserMsg = this.chatHistory.slice().reverse().find(msg => msg.role === 'user');
                const lastUserContent = lastUserMsg ? lastUserMsg.content.toLowerCase() : '';

                const userRequestedGraphic = (
                    lastUserContent.includes('grafik') ||
                    lastUserContent.includes('diagramm') ||
                    lastUserContent.includes('chart')
                );

                const statData = contextData.find(c => c.function === 'get_shop_stats');
                if (statData && statData.data && statData.data.scaling_metrics) {
                    title = 'Marketing & Skalierung';
                    foundData = true;

                    if (userRequestedGraphic) {
                        chartLabels = ['Active Auto-Vouchers', 'Active Manual-Vouchers', 'Abandoned Carts (24h)'];
                        chartDataset = [
                            statData.data.scaling_metrics.active_auto_vouchers || 0,
                            statData.data.scaling_metrics.active_manual_vouchers || 0,
                            statData.data.scaling_metrics.abandoned_carts_count || 0
                        ];
                        chartType = 'polarArea';
                    } else {
                        this.tableHeaders = ['Metrik', 'Wert'];
                        this.tableData.push({
                            cells: [
                                { value: 'Auto-Gutscheine (Aktiv)', color: 'text-gray-300' },
                                { value: statData.data.scaling_metrics.active_auto_vouchers + 'x', color: 'text-emerald-400 font-bold' }
                            ]
                        });
                        this.tableData.push({
                            cells: [
                                { value: 'Manuelle-Gutscheine (Aktiv)', color: 'text-gray-300' },
                                { value: statData.data.scaling_metrics.active_manual_vouchers + 'x', color: 'text-blue-400 font-bold' }
                            ]
                        });
                        this.tableData.push({
                            cells: [
                                { value: 'Abgebrochene Carts (24h)', color: 'text-gray-300' },
                                { value: statData.data.scaling_metrics.abandoned_carts_count + 'x', color: 'text-yellow-400 font-bold' }
                            ]
                        });

                        this.chartListData.push({
                            title: 'Verlorener Umsatz (24h)',
                            titleColor: 'text-rose-400',
                            badge: 'Warnung',
                            subtitle: `Potenzieller Verlust: ${statData.data.scaling_metrics.potential_lost_revenue || 0} €`
                        });
                    }
                }

                const financeData = contextData.find(c => c.function === 'get_finances');
                if (!foundData && financeData && financeData.data && financeData.data.financial_data_net) {
                    title = 'Finanzübersicht (' + (financeData.data.current_month || 'Laufender Monat') + ')';
                    foundData = true;

                    let fd = financeData.data.financial_data_net;

                    if (userRequestedGraphic || true) { 
                        chartLabels = ['Shop Netto-Umsatz', 'Fixkosten', 'Sonderausgaben'];
                        chartDataset = [
                            fd.shop_income || 0,
                            Math.abs(fd.fixed_expenses || 0),
                            Math.abs(fd.special_expenses || 0)
                        ];
                        chartType = 'doughnut';

                        let sum = chartDataset.reduce((a,b) => a+b, 0);
                        if (sum === 0) {
                            chartLabels = ['Keine Daten in diesem Monat'];
                            chartDataset = [1]; 
                        }
                    }
                }

                const taskData = contextData.find(c => c.function === 'get_tasks');
                if (!foundData && taskData && taskData.data && taskData.data.tasks) {
                    title = 'Meine Aufgaben';
                    foundData = true;

                    if (taskData.data.tasks.length === 0) {
                        this.chartListData.push({
                            title: 'Alles erledigt!',
                            titleColor: 'text-emerald-400',
                            badge: 'Aufgaben',
                            subtitle: 'Du hast aktuell keine offenen Aufgaben.'
                        });
                    } else if (userRequestedGraphic) {
                        chartType = 'bar';
                        chartLabels = ['Hoch', 'Mittel', 'Niedrig'];
                        let high = 0, med = 0, low = 0;
                        taskData.data.tasks.forEach(t => {
                            if (t.priority === 'hoch') high++;
                            else if (t.priority === 'mittel') med++;
                            else low++;
                        });
                        chartDataset = [high, med, low];
                    } else {
                        this.tableHeaders = ['Titel', 'Priorität', 'Erstellt am'];
                        taskData.data.tasks.forEach(t => {
                            let pColor = 'text-gray-300';
                            let renderPriority = (t.priority || 'Normal').toString().toUpperCase();
                            let rawPrio = renderPriority.toLowerCase();

                            if (rawPrio === 'high' || rawPrio === 'hoch' || rawPrio === '1') {
                                pColor = 'text-red-400';
                            } else if (rawPrio === 'medium' || rawPrio === 'mittel' || rawPrio === '2') {
                                pColor = 'text-yellow-400';
                            } else {
                                pColor = 'text-gray-300';
                            }

                            let dateStr = t.created_at ? new Date(t.created_at).toLocaleDateString('de-DE') : 'Neu';

                            this.tableData.push({
                                cells: [
                                    { value: t.title, color: 'text-gray-100' },
                                    { value: renderPriority, color: pColor },
                                    { value: dateStr, color: 'text-gray-500' }
                                ]
                            });
                        });
                    }
                }

                const actionData = contextData.find(c => c.function === 'create_task' || c.function === 'complete_task' || c.function === 'delete_task');
                if (!foundData && actionData && actionData.data && actionData.data.status === 'success') {
                    title = 'System-Aktion';
                    foundData = true;
                    this.chartListData.push({
                        title: actionData.data.message || 'Aktion erfolgreich',
                        titleColor: 'text-emerald-400',
                        badge: 'OK',
                        subtitle: 'Von Funkira ausgeführt'
                    });
                }

                const errorData = contextData.find(c => c.data && c.data.status === 'error');
                if (!foundData && errorData) {
                    foundData = true;
                    this.errorText = `Modul: ${errorData.function}\nFehlerbericht:\n${errorData.data.message || 'Unbekannter Systemfehler'}`;
                    this.showErrorPanel = true;
                    this.systemState = 'error';
                    this.updateCoreColor();
                }

                const calData = contextData.find(c => c.function === 'get_calendar_events');
                if (!foundData && calData && calData.data && calData.data.upcoming_events) {
                    title = 'Meine Termine';
                    foundData = true;

                    if (calData.data.upcoming_events.length === 0) {
                        this.chartListData.push({
                            title: 'Freie Zeit',
                            titleColor: 'text-emerald-400',
                            badge: 'Kalender',
                            subtitle: 'Du hast in den nächsten 7 Tagen keine Termine.'
                        });
                    } else if (userRequestedGraphic) {
                        chartType = 'polarArea';
                        let categories = {};
                        calData.data.upcoming_events.forEach(e => {
                            let cat = e.category || 'Allgemein';
                            categories[cat] = (categories[cat] || 0) + 1;
                        });
                        chartLabels = Object.keys(categories);
                        chartDataset = Object.values(categories);
                    } else {
                        calData.data.upcoming_events.forEach(e => {
                            let dateStr = new Date(e.start_date).toLocaleString('de-DE', { weekday:'short', day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
                            this.chartListData.push({
                                title: e.title,
                                titleColor: 'text-indigo-400',
                                badge: e.category || 'Termin',
                                subtitle: `Zeit: ${dateStr}`
                            });
                        });
                    }
                }

                const routineData = contextData.find(c => c.function === 'get_day_routines');
                if (!foundData && routineData && routineData.data && routineData.data.routines) {
                    title = 'Fokus-Routinen';
                    foundData = true;

                    if (routineData.data.routines.length === 0) {
                        this.chartListData.push({
                            title: 'Keine Routinen',
                            titleColor: 'text-emerald-400',
                            badge: 'Routinen',
                            subtitle: 'Du hast heute noch keine Fokus-Routinen geplant.'
                        });
                    } else {
                        chartType = 'doughnut';
                        routineData.data.routines.forEach(r => {
                            let rTitle = r.title || 'Unbenannt';
                            let duration = r.duration_minutes || 10;
                            chartLabels.push(rTitle);
                            chartDataset.push(duration);

                            let stepText = '';
                            if (r.steps && r.steps.length > 0) {
                                stepText = r.steps.map(s => `• ${s.title}`).join('\n');
                            }

                            this.chartListData.push({
                                title: rTitle,
                                titleColor: 'text-emerald-400',
                                badge: duration + ' Min',
                                subtitle: stepText
                            });
                        });
                    }
                }

                const lbData = contextData.find(c => c.function === 'get_gamification_leaderboard');
                if (!foundData && lbData && lbData.data && lbData.data.leaderboard) {
                    title = 'Seelenfunke Highscores';
                    foundData = true;

                    if (lbData.data.leaderboard.length === 0) {
                        this.chartListData.push({
                            title: 'Keine Rekorde',
                            titleColor: 'text-gray-400',
                            badge: 'Gamification',
                            subtitle: 'Noch hat sich niemand in der Rangliste qualifiziert.'
                        });
                    } else {
                        this.tableHeaders = ['Rang', 'Kunde', 'Titel', 'Level', 'XP'];
                        lbData.data.leaderboard.forEach(l => {
                            let rankColor = l.rank === 1 ? 'text-yellow-400 font-bold' : (l.rank === 2 ? 'text-gray-300 font-bold' : (l.rank === 3 ? 'text-amber-600 font-bold' : 'text-indigo-400'));
                            this.tableData.push({
                                cells: [
                                    { value: '#' + l.rank, color: rankColor },
                                    { value: l.customer, color: 'text-white' },
                                    { value: l.title, color: 'text-gray-400' },
                                    { value: 'LVL ' + l.level, color: 'text-emerald-400' },
                                    { value: l.xp, color: 'text-emerald-600' }
                                ]
                            });
                        });
                    }
                }

                if (!foundData) {
                    const healthData = contextData.find(c => c.function === 'get_system_health');
                    if (healthData && healthData.data && healthData.data.active_sessions !== undefined) {
                        title = 'Sitzungen vs Bestellungen';
                        chartType = 'bar';
                        chartLabels = ['Aktive Sitzungen', 'Bestellungen'];
                        chartDataset = [healthData.data.active_sessions || 0, healthData.data.total_orders || 0];
                    } else {
                        return;
                    }
                }

                this.chartTitle = title;
                this.showChartPanel = true;

                this.$nextTick(() => {
                    this.playClickSound(); 
                    this.destroyCurrentChart();

                    const chartWrapper = document.getElementById('funki-canvas-wrapper');

                    if (this.chartListData.length > 0 || this.tableData.length > 0) {
                        this.showChartCanvas = false;
                        return;
                    }

                    this.showChartCanvas = true;

                    setTimeout(() => {
                        const ctx = document.getElementById('funkiDynamicChart');
                        if (!ctx) return;

                        if (typeof Chart === 'undefined') {
                            console.error('Chart.js is not loaded yet.');
                            return;
                        }

                        Chart.defaults.color = "rgba(255, 255, 255, 0.6)";
                        Chart.defaults.font.family = "'JetBrains Mono', monospace";
                        Chart.defaults.plugins.tooltip.backgroundColor = "rgba(0, 0, 0, 0.8)";

                        this.currentChart = new Chart(ctx, {
                            type: chartType,
                            data: {
                                labels: chartLabels,
                                datasets: [{
                                    data: chartDataset,
                                    backgroundColor: [
                                        'rgba(16, 185, 129, 0.6)',
                                        'rgba(59, 130, 246, 0.6)',
                                        'rgba(139, 92, 246, 0.6)'
                                    ],
                                    borderColor: [
                                        'rgba(16, 185, 129, 1)',
                                        'rgba(59, 130, 246, 1)',
                                        'rgba(139, 92, 246, 1)'
                                    ],
                                    borderWidth: 1,
                                    hoverOffset: 4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: chartType === 'doughnut' ? '75%' : undefined,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: { padding: 20, color: 'rgba(255,255,255,0.7)' }
                                    }
                                },
                                animation: {
                                    animateScale: true,
                                    animateRotate: true
                                }
                            }
                        });
                    }, 300); 
                });
            },

            destroyCurrentChart() {
                if (this.currentChart) {
                    this.currentChart.destroy();
                    this.currentChart = null;
                }
            },

            playClickSound() {
                const clickAudio = document.getElementById('audio-funki-click');
                if (clickAudio) {
                    clickAudio.currentTime = 0;
                    clickAudio.volume = 0.6;
                    clickAudio.play().catch(e => console.log(e));
                }
            },

            // --- Multimodal Live API ---
            nextPlayTime: 0,
            
            async initLiveMode() {
                try {
                    this.thinking = true;
                    this.updateCoreColor(true);

                    // 1. Fetch Credentials securely
                    const response = await fetch('/api/ai/live-credentials?agent_id=' + (this.activeAgentId || '') + '&session_id={{ session()->getId() }}&t=' + Date.now(), {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        }
                    });

                    if (!response.ok) throw new Error('Konnte keine Live-Credentials abrufen.');
                    const creds = await response.json();

                    if (!creds.api_key) throw new Error('API Key fehlt.');

                    // 2. Setup WebSocket
                    const wsUrl = `wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=${creds.api_key}`;
                    this.liveWs = new WebSocket(wsUrl);

                    this.liveWs.onopen = () => {
                        console.log('Gemini Live WS Connected');
                        
                        // Send Initial Setup
                        const setupMsg = {
                            setup: {
                                model: "models/gemini-3.1-flash-live-preview",
                                systemInstruction: {
                                    parts: [{ text: creds.system_instruction }]
                                },
                                generationConfig: {
                                    responseModalities: ["AUDIO"],
                                    speechConfig: {
                                        voiceConfig: {
                                            prebuiltVoiceConfig: {
                                                voiceName: creds.voice_name || "Puck"
                                            }
                                        }
                                    }
                                },
                                tools: creds.tools || []
                            }
                        };
                        this.liveWs.send(JSON.stringify(setupMsg));
                        
                        this.thinking = false;
                        this.updateCoreColor(true);
                        
                        // Start Microphone ONLY after setupComplete is received!
                        // this.startMicrophone();
                    };

                    this.liveWs.onmessage = async (event) => {
                        let data;
                        if (event.data instanceof Blob) {
                            const text = await event.data.text();
                            data = JSON.parse(text);
                        } else {
                            data = JSON.parse(event.data);
                        }

                        this.handleWsMessage(data);
                    };

                    this.liveWs.onerror = (error) => {
                        console.error('WebSocket Error:', error);
                        alert('WebSocket Error aufgetreten (siehe Console).');
                        this.stopLiveMode();
                    };

                    this.liveWs.onclose = (event) => {
                        console.log('WebSocket Closed', event);
                        if (event.code !== 1000 && event.code !== 1005) {
                            alert('WebSocket geschlossen! Code: ' + event.code + ' Reason: ' + event.reason);
                        }
                        this.stopLiveMode();
                    };

                } catch (err) {
                    console.error("Live Mode Init Error:", err);
                    alert("Init Error: " + err.message);
                    this.stopLiveMode();
                }
            },

            startLiveSpeechRecognition() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) return;
                
                this.liveRecognition = new SpeechRecognition();
                this.liveRecognition.continuous = true;
                this.liveRecognition.interimResults = false;
                this.liveRecognition.lang = 'de-DE';

                this.liveRecognition.onresult = (event) => {
                    const lastResult = event.results[event.results.length - 1];
                    if (lastResult.isFinal) {
                        const transcript = lastResult[0].transcript.trim();
                        if (transcript) {
                            try { @this.appendLiveChatMemory('user', transcript); } catch(e) {}
                            this.funkiLogs.push({ role: 'user', time: new Date().toLocaleTimeString('de-DE'), message: transcript });
                            
                            // Signal Gemini that the user has finished their turn
                            if (this.liveWs && this.liveWs.readyState === WebSocket.OPEN) {
                                this.liveWs.send(JSON.stringify({
                                    clientContent: {
                                        turnComplete: true
                                    }
                                }));
                            }
                        }
                    }
                };

                this.liveRecognition.onend = () => {
                    if (this.isLiveMode) {
                        try { this.liveRecognition.start(); } catch(e) {}
                    }
                };

                try { this.liveRecognition.start(); } catch(e) {}
            },

            async startMicrophone() {
                try {
                    this.startLiveSpeechRecognition();

                    // Zurück zum Standard (mit echoCancellation), damit die iOS Hardware nicht crasht
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: {
                        channelCount: 1,
                        sampleRate: 16000,
                        echoCancellation: true,
                        noiseSuppression: true
                    } });

                    this.audioInput = this.audioContext.createMediaStreamSource(stream);
                    
                    const processor = this.audioContext.createScriptProcessor(4096, 1, 1);
                    this.audioWorklet = processor;
                    
                    processor.onaudioprocess = (e) => {
                        if (!this.liveWs || this.liveWs.readyState !== WebSocket.OPEN) return;
                        
                        const inputData = e.inputBuffer.getChannelData(0);
                        const pcm16 = new Int16Array(inputData.length);
                        
                        for (let i = 0; i < inputData.length; i++) {
                            const s = Math.max(-1, Math.min(1, inputData[i]));
                            pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
                        }
                        
                        const base64Pcm = btoa(String.fromCharCode.apply(null, new Uint8Array(pcm16.buffer)));
                        
                        const msg = {
                            realtimeInput: {
                                audio: {
                                    mimeType: "audio/pcm;rate=16000",
                                    data: base64Pcm
                                }
                            }
                        };
                        this.liveWs.send(JSON.stringify(msg));
                    };

                    this.audioInput.connect(processor);
                    processor.connect(this.audioContext.destination);

                } catch (err) {
                    console.error("Mic Access Denied", err);
                    alert("Mikrofon-Fehler: " + err.message);
                    this.stopLiveMode();
                }
            },

            async handleWsMessage(data) {
                if (data.setupComplete) {
                    console.log('Gemini Live Setup Complete received');
                    this.startMicrophone();
                    return;
                }
                if (data.serverContent && data.serverContent.modelTurn) {
                    const parts = data.serverContent.modelTurn.parts;
                    let fullText = "";
                    parts.forEach(part => {
                        if (part.inlineData && part.inlineData.data) {
                            this.playLiveAudioChunk(part.inlineData.data);
                        }
                        if (part.text) {
                            fullText += part.text;
                        }
                    });
                    
                    if (fullText) {
                        this.chatHistory.push({ role: 'model', parts: [{ text: fullText }] });
                        try {
                            @this.appendLiveChatMemory('model', fullText);
                        } catch(e) {}
                        this.funkiLogs.push({ role: 'ai', time: new Date().toLocaleTimeString('de-DE'), message: fullText.replace(/\[.*?\]/s, '') });
                    }
                }
                
                if (data.serverContent && data.serverContent.interrupted) {
                    this.stopCurrentAudioPlayback();
                }

                // Handle Tool Calls from WebSocket
                if (data.toolCall) {
                    const call = data.toolCall.functionCalls[0];
                    if (call) {
                        try {
                            const res = await fetch('/api/ai/execute', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                                },
                                body: JSON.stringify({
                                    function: call.name,
                                    args: call.args,
                                    session_id: '{{ session()->getId() }}'
                                })
                            });
                            const resultData = await res.json();
                            
                            // Visualize
                            this.renderAnalytics([resultData]);
                            
                            // MAGIC: Synchronize frontend UI if the agent decided to switch itself!
                            if (call.name === 'system_switch_agent' && resultData.result && resultData.result.status === 'success') {
                                // This triggers the Livewire updatedAgentId hook which in turn dispatches 'agent-changed',
                                // naturally shutting down this WS connection and spinning up the new one,
                                // exactly as if the user clicked the dropdown manually!
                                @this.set('agentId', resultData.result.agent_id);
                            }

                            // MAGIC: Handle explicit UI events returned by tools (like navigation)
                            if (resultData.result && resultData.result._event) {
                                const ev = resultData.result._event;
                                if (ev.type === 'navigate' && ev.url) {
                                    if (typeof window.Livewire !== 'undefined') {
                                        window.Livewire.navigate(ev.url);
                                    } else {
                                        window.location.href = ev.url;
                                    }
                                } else if (ev.type === 'dispatch' && ev.name) {
                                    window.dispatchEvent(new CustomEvent(ev.name, { detail: ev.detail || {} }));
                                }
                            }

                            // Send Tool Response back to Gemini
                            const toolResp = {
                                toolResponse: {
                                    functionResponses: [{
                                        id: call.id,
                                        name: call.name,
                                        response: {
                                            result: resultData
                                        }
                                    }]
                                }
                            };
                            this.liveWs.send(JSON.stringify(toolResp));
                        } catch (e) {
                            console.error("Tool execution failed", e);
                        }
                    }
                }
            },
            
            stopCurrentAudioPlayback() {
                if (this.audioContext) {
                    this.audioContext.suspend();
                }
                if (this.activeAudioSources && this.activeAudioSources.length > 0) {
                    this.activeAudioSources.forEach(source => {
                        try { source.stop(); } catch(e) {}
                        try { source.disconnect(); } catch(e) {}
                    });
                    this.activeAudioSources = [];
                }
                this.nextPlayTime = 0;
                this.isSpeaking = false;
                this.updateCoreColor(true);
                
                if (this.audioContext) {
                    setTimeout(() => {
                        if (this.audioContext) this.audioContext.resume();
                    }, 20);
                }
            },

            playLiveAudioChunk(base64Data) {
                if (!this.audioContext) return;
                
                this.thinking = false;
                this.isSpeaking = true;
                this.updateCoreColor(true);

                const binaryString = window.atob(base64Data);
                const len = binaryString.length;
                const bytes = new Uint8Array(len);
                for (let i = 0; i < len; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }
                
                const pcm16 = new Int16Array(bytes.buffer);
                const float32 = new Float32Array(pcm16.length);
                for (let i = 0; i < pcm16.length; i++) {
                    float32[i] = pcm16[i] / 32768;
                }

                const audioBuffer = this.audioContext.createBuffer(1, float32.length, 24000);
                audioBuffer.getChannelData(0).set(float32);

                const source = this.audioContext.createBufferSource();
                source.buffer = audioBuffer;
                
                // Directly connect to output for pure, clean Gemini audio
                source.connect(this.audioContext.destination);
                
                if (this.nextPlayTime < this.audioContext.currentTime) {
                    this.nextPlayTime = this.audioContext.currentTime;
                }
                
                if (!this.activeAudioSources) this.activeAudioSources = [];
                this.activeAudioSources.push(source);
                
                source.start(this.nextPlayTime);
                this.nextPlayTime += audioBuffer.duration;
                
                source.onended = () => {
                    this.activeAudioSources = this.activeAudioSources.filter(s => s !== source);
                    if (this.audioContext && this.audioContext.currentTime >= this.nextPlayTime - 0.1) {
                        this.isSpeaking = false;
                        this.updateCoreColor(true);
                    }
                };
            },

            stopLiveMode() {
                this.isLiveMode = false;
                if (this.liveWs) {
                    this.liveWs.close();
                    this.liveWs = null;
                }
                if (this.audioInput) {
                    this.audioInput.disconnect();
                    this.audioInput = null;
                }
                if (this.audioWorklet) {
                    this.audioWorklet.disconnect();
                    this.audioWorklet = null;
                }
                if (this.audioContext) {
                    this.audioContext.close();
                    this.audioContext = null;
                }
                if (this.liveRecognition) {
                    this.liveRecognition.onend = null;
                    try { this.liveRecognition.abort(); } catch(e) {}
                    try { this.liveRecognition.stop(); } catch(e) {}
                    this.liveRecognition = null;
                }
                this.nextPlayTime = 0;
                this.isSpeaking = false;
                this.thinking = false;
                this.updateCoreColor(true);
            },
