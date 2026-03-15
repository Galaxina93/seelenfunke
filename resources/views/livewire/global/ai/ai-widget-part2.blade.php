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

        Alpine.data('funkiView', (initialState = 'good', initialSparks = 42, avgProfit = 0, totalOrders = 0, lastSync = '') => ({
            // State
            showFunkiView: false,
            showErrorPanel: false,
            isAudioMuted: localStorage.getItem('funki_isAudioMuted') !== null ? localStorage.getItem('funki_isAudioMuted') === 'true' : true, // Default to muted as requested
            bgVolume: localStorage.getItem('funki_bgVolume') !== null ? parseInt(localStorage.getItem('funki_bgVolume')) : 15,       // Default background volume
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
            continuousMode: localStorage.getItem('funki_continuousMode') === 'true',
            requireWakeWord: localStorage.getItem('funki_requireWakeWord') === 'true', // Default: Reacts to everything
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
            activeAgentName: 'System', // Neu: Speichert den Namen des antwortenden Agenten

            isOutputActive() {
                return this.isSpeaking;
            },

            // --- AI VOICE CHAT LOGIC ---
            toggleMobileContinuous() {
                if (!this.recognition) return;

                if (this.continuousMode) {
                    this.isAudioMuted = true; // Auto-mute background logic
                    this.enforceAudioMuteState();

                    if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                    if (this.synthesis && this.synthesis.speaking) this.synthesis.cancel();

                    this.listening = true;
                    this.updateCoreColor();
                    try { this.recognition.start(); } catch(e) {}
                } else {
                    this.listening = false;
                    this.updateCoreColor();
                    this.recognition.stop();
                }
            },

            toggleSpeech() {
                if (this.thinking) return;

                if (this.isMobile) {
                    return;
                }

                if (this.continuousMode) {
                    this.continuousMode = false;
                    this.listening = false;
                    if (this.recognition) {
                        this.recognition.onend = null;
                        try { this.recognition.abort(); } catch(e) {}
                        this.recognition = null;
                    }
                } else {
                    if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                    if (this.synthesis && this.synthesis.speaking) this.synthesis.cancel();

                    this.continuousMode = true;
                    this.listening = true;
                    this.restartCount = 0;
                    if (!this.recognition) this.initSpeech();
                    try {
                        this.recognition.start();
                    } catch (e) {
                        console.error('Failed to start recognition', e);
                        if (e.name !== 'InvalidStateError') this.listening = false;
                    }
                    this.resetWatchdog();
                }
            },

            startPushToTalk() {
                if (this.thinking || this.isOutputActive()) return;

                if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                if (this.synthesis) this.synthesis.cancel();

                this.playClickSound();
                this.continuousMode = false;
                this.listening = true;
                this.updateCoreColor();
                this.restartCount = 0;

                this.initSpeech();
                try {
                    this.recognition.start();
                } catch(e) {}
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
                        body: JSON.stringify({ history: this.chatHistory })
                    });

                    let data;
                    try {
                        data = await response.json();
                    } catch (jsonErr) {
                        this.thinking = false;
                        this.updateCoreColor();

                        const errorTextHTML = await response.text();
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
                                    window.location.href = evt.url;
                                } else if (evt.type === 'dispatch') {
                                    window.dispatchEvent(new Event(evt.name));
                                }
                            });
                        }

                        if (data.agent_name) {
                            this.activeAgentName = data.agent_name;
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
                            this.speakResponse(data.response); 
                        }
                    } else {
                        console.error("AI Error:", data);
                        this.speakResponse("Verbindung zum Schiffscomputer fehlgeschlagen.");
                        this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: `Fehler: API lieferte keinen Erfolgsstatus.` });
                        Livewire.dispatch('log-widget-error', { message: 'Widget API lieferte keinen Erfolgsstatus: ' + (data.error || 'Unbekannt') });
                        if (this.funkiLogs.length > 15) this.funkiLogs = this.funkiLogs.slice(-15);
                    }
                } catch (err) {
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
