<div @open-funki.window="openFunkiView()"
     @funki-event.window="updateFunkiStatus($event.detail.state)"
     @keyup.escape.window="closeFunkiView()">

    <template x-teleport="body">
        <div x-show="showFunkiView"
             x-transition:enter="transition ease-out duration-1000"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-1000"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             style="display: none;"
             class="fixed inset-0 z-[99999] bg-[#03050a] overflow-hidden font-mono">

    <!-- CSS2D Container for HTML elements in 3D -->
    <div id="css2d-container" class="absolute inset-0 w-full h-full pointer-events-none z-10" style="pointer-events: none;"></div>

    <!-- Canvas Container -->
    <div id="funki-canvas-container" class="absolute inset-0 w-full h-full"></div>

    <!-- UI Overlay Navigation -->
    <div class="absolute top-6 right-6 z-50 flex flex-col items-end gap-2" x-transition:enter="transition ease-out duration-1000 delay-500" x-transition:enter-start="opacity-0 translate-y-[-20px]" x-transition:enter-end="opacity-100 translate-y-0">

        <div class="flex items-center gap-4 transition-transform hover:scale-105">
            <!-- Token Usage -->
            <div x-show="tokenUsage" x-transition class="flex items-center gap-2 px-3 py-1 bg-gray-900/80 border border-gray-700 rounded-lg shadow-[0_0_15px_rgba(16,185,129,0.2)] backdrop-blur-md" style="display: none;">
                <i class="bi bi-cpu text-emerald-400 text-sm"></i>
                <span class="text-[10px] font-mono font-bold text-gray-300 tracking-wider" x-text="tokenUsage"></span>
            </div>

            <!-- Audio Stop -->
            <button x-show="isOutputActive()" @click="stopSpeech()" class="px-3 py-2 bg-red-900/80 border border-red-700 rounded-lg text-[10px] font-black uppercase tracking-widest text-red-100 hover:text-white hover:border-red-400 hover:bg-red-800 transition-all shadow-[0_0_15px_rgba(239,68,68,0.5)] flex items-center gap-2 backdrop-blur-md" title="Funkira unterbrechen">
                <i class="bi bi-stop-circle-fill text-sm"></i> Stopp
            </button>
        </div>

        <!-- Action Debug Log -->
        <div x-show="funkiLogs.length > 0" class="w-64 mt-2 p-2 bg-black/60 border border-emerald-900/50 rounded-lg backdrop-blur-md shadow-[0_0_20px_rgba(16,185,129,0.1)] flex flex-col gap-1 max-h-48 overflow-y-auto pointer-events-none" style="display: none;">
            <div class="text-[8px] font-black uppercase tracking-widest text-emerald-500/50 border-b border-emerald-900/30 pb-1 mb-1">KI Aktionen (Live-Log)</div>
            <template x-for="(log, i) in funkiLogs.slice().reverse()" :key="i">
                <div class="text-[9px] font-mono leading-tight text-gray-300 break-words flex gap-2">
                    <span class="text-emerald-500 shrink-0">►</span>
                    <span x-text="log"></span>
                </div>
            </template>
        </div>
    </div>

    <div class="absolute bottom-6 left-6 z-50 flex flex-col items-start gap-4" x-transition:enter="transition ease-out duration-1000 delay-500" x-transition:enter-start="opacity-0 translate-y-[20px]" x-transition:enter-end="opacity-100 translate-y-0">
        <!-- Wake Word Toggle -->
        <label class="flex items-center gap-2 px-3 py-1 bg-gray-900/80 border border-gray-700 rounded-lg shadow-[0_0_15px_rgba(16,185,129,0.2)] backdrop-blur-md cursor-pointer hover:border-emerald-500 transition-colors" title="Aktivierungswort (Funkira) nutzen oder auf jedes Wort reagieren">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Aktivierungswort</span>
            <div class="relative inline-block w-8 outline-none focus:outline-none">
                <input type="checkbox" x-model="requireWakeWord" class="peer sr-only">
                <div class="block h-4 bg-gray-700 rounded-full peer-checked:bg-emerald-500 transition-all"></div>
                <div class="dot absolute left-1 top-1 w-2 h-2 bg-white rounded-full transition peer-checked:translate-x-4"></div>
            </div>
        </label>



        <!-- Audio Toggle & Slider -->
        <div class="flex items-center gap-2 px-3 py-1 bg-gray-900/80 border border-gray-700 rounded-full shadow-glow backdrop-blur-md transition-all hover:border-emerald-500 hover:bg-black group">
            <button @click="toggleBackgroundAudio()" class="w-8 h-8 flex justify-center items-center text-gray-300 hover:text-emerald-400 transition-colors" title="Hintergrundmusik an/aus">
                <template x-if="isAudioMuted">
                    <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM12.293 7.293a1 1 0 011.414 0L15 8.586l1.293-1.293a1 1 0 111.414 1.414L16.414 10l1.293 1.293a1 1 0 01-1.414 1.414L15 11.414l-1.293 1.293a1 1 0 01-1.414-1.414L13.586 10l-1.293-1.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </template>
                <template x-if="!isAudioMuted">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"></path>
                    </svg>
                </template>
            </button>
            <input type="range" min="0" max="100" x-model="bgVolume"
                   class="w-20 h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer hidden group-hover:block accent-emerald-500"
                   title="Lautstärke" />
        </div>

        <!-- Close Button -->
        <button @click="closeFunkiView()" class="px-5 py-2.5 bg-gray-900/80 border border-gray-700 rounded-full text-xs font-black uppercase tracking-widest text-gray-300 hover:text-white hover:border-primary hover:bg-black transition-all shadow-glow flex items-center gap-2 backdrop-blur-md">
            <i class="bi bi-x-lg"></i> Funkira - Zentrum verlassen
        </button>
    </div>

    <!-- Audio Elements -->
    <audio id="audio-funki-background" src="{{ asset('funkira/sounds/funkira_background.mp3') }}" preload="auto" loop></audio>
    <audio id="audio-funki-default-ambient" src="{{ asset('funkira/sounds/funkira_default_universum.mp3') }}" preload="auto" loop></audio>
    <audio id="audio-funki-pulse" src="{{ asset('funkira/sounds/funkira_pulse.mp3') }}" preload="auto" loop></audio>
    <audio id="audio-funki-init" src="{{ asset('funkira/sounds/funkira_Initialize.mp3') }}" preload="auto"></audio>
    <audio id="audio-funki-shutdown" src="{{ asset('funkira/sounds/funkira_shutdown.mp3') }}" preload="auto"></audio>
    <audio id="audio-funki-heartbeat" src="{{ asset('funkira/sounds/funkira_heartbeat.mp3') }}" preload="auto" loop></audio>
    <audio id="audio-funki-waiting" src="{{ asset('funkira/sounds/funkira_heartbeat_waiting.mp3') }}" preload="auto" loop></audio>
    <audio id="audio-funki-click" src="{{ asset('funkira/sounds/funkira_click.mp3') }}" preload="auto"></audio>
    <audio id="audio-funki-unclick" src="{{ asset('funkira/sounds/funkira_unclick.mp3') }}" preload="auto"></audio>

    <!-- Unified Floating UI Panel (Mapped to 3D Space) -->
    <div id="diagnostic-panel"
         x-show="showInfoPanel || showChartPanel"
         class="w-[800px] max-w-[90vw] pointer-events-none flex flex-col justify-center items-center gap-6"
         style="display: none;">

        <!-- 1. Kern-Diagnostik Module -->
        <div x-show="showInfoPanel"
             x-transition:enter="transition ease-out duration-500 delay-100"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="w-[600px] max-w-[90vw] pointer-events-auto overflow-hidden self-end drop-shadow-2xl">

            <!-- Header -->
            <div class="px-6 py-4 flex justify-between items-center drop-shadow-md border-b border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="w-2.5 h-2.5 rounded-full shadow-[0_0_10px_rgba(16,185,129,1)]"
                         :class="{'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,1)]': stateColor === 'good', 'bg-yellow-500 shadow-[0_0_10px_rgba(234,179,8,1)]': stateColor === 'warning', 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,1)]': stateColor === 'error'}"></div>
                    <h3 class="font-bold text-gray-100 tracking-wider">Kern-Diagnostik</h3>
                </div>
                <button @click="showInfoPanel = false; playUnclickSound();" class="text-gray-400 hover:text-white transition-colors drop-shadow-md">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 grid grid-cols-2 gap-6 relative drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                <div class="space-y-4 relative z-10">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Status</p>
                        <p class="text-lg font-mono text-emerald-400"
                           :class="{'text-emerald-400': stateColor === 'good', 'text-yellow-400': stateColor === 'warning', 'text-red-400': stateColor === 'error'}">
                           <span x-text="displayState"></span>
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Aktive Sitzungen</p>
                        <p class="text-2xl font-mono text-white flex items-baseline gap-2">
                            <span x-text="activeSparks"></span>
                            <span class="text-xs text-primary bg-primary/20 px-1.5 py-0.5 rounded">Online</span>
                        </p>
                    </div>
                </div>

                <div class="space-y-4 relative z-10">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Ø Tagesgewinn / Orders</p>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-mono text-emerald-400" x-text="avgProfit"></span>
                            <span class="text-gray-600">|</span>
                            <span class="text-xs font-mono text-gray-300" x-text="totalOrders + ' Orders'"></span>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Letzter Sync</p>
                        <p class="text-sm font-mono text-gray-300" x-text="lastSync"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Dynamic Analytics Chart/Table Module -->
        <div x-show="showChartPanel"
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="w-full pointer-events-auto overflow-hidden self-start drop-shadow-2xl">

            <!-- Header -->
            <div class="px-6 py-4 flex justify-between items-center drop-shadow-md">
                <div class="flex items-center gap-3">
                    <div class="w-2.5 h-2.5 rounded-full shadow-[0_0_10px_rgba(16,185,129,1)] bg-emerald-500"></div>
                    <h3 class="font-bold text-gray-100 tracking-wider">
                        <span x-text="chartTitle">Daten-Analyse</span>
                    </h3>
                </div>
                <button @click="showChartPanel = false; playUnclickSound();" class="text-gray-400 hover:text-white transition-colors drop-shadow-md">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 relative">

                <!-- Chart Container -->
                <div x-show="showChartCanvas" 
                     x-transition:enter="transition ease-out duration-700 delay-100"
                     x-transition:enter-start="opacity-0 scale-90 translate-y-4"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-90 translate-y-4"
                     id="funki-canvas-wrapper" class="w-full h-[280px] drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] relative">
                    <!-- Individual Close Button for Chart -->
                    <button @click="destroyCurrentChart(); showChartCanvas = false; playUnclickSound();" class="absolute -top-3 -right-3 w-6 h-6 bg-red-600/80 hover:bg-red-500 rounded-full flex items-center justify-center text-white text-xs drop-shadow-md z-20 border border-red-400">
                        <i class="bi bi-x"></i>
                    </button>
                    <canvas id="funkiDynamicChart"></canvas>
                </div>

                <!-- Dynamic Table Container -->
                <div x-show="tableData.length > 0" 
                     x-transition:enter="transition ease-out duration-700 delay-200"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                     class="w-full relative mt-4">
                    <!-- Individual Close Button for Table -->
                    <button @click="tableData = []; playUnclickSound();" class="absolute -top-3 -right-3 w-6 h-6 bg-red-600/80 hover:bg-red-500 rounded-full flex items-center justify-center text-white text-xs drop-shadow-md z-20 border border-red-400">
                        <i class="bi bi-x"></i>
                    </button>
                    
                    <div class="overflow-y-auto max-h-[400px] custom-scrollbar transition-opacity duration-300 opacity-100">
                        <div class="relative drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] z-10 w-full bg-gray-900/50 rounded-xl border border-gray-700/50 overflow-hidden">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-700/50">
                                    <template x-for="(header, index) in tableHeaders" :key="index">
                                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-widest text-emerald-500/70" x-text="header"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50">
                                <template x-for="(row, rIndex) in tableData" :key="rIndex">
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <template x-for="(cell, cIndex) in row.cells" :key="cIndex">
                                            <td class="py-3 px-4 text-sm font-mono" :class="cell.color || 'text-gray-300'" x-text="cell.value"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Dynamic List Container -->
                <div x-show="chartListData.length > 0" 
                     x-transition:enter="transition ease-out duration-700 delay-300"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                     class="w-full relative mt-4">
                    <!-- Individual Close Button for List -->
                    <button @click="chartListData = []; playUnclickSound();" class="absolute -top-3 -right-3 w-6 h-6 bg-red-600/80 hover:bg-red-500 rounded-full flex items-center justify-center text-white text-xs drop-shadow-md z-20 border border-red-400">
                        <i class="bi bi-x"></i>
                    </button>
                    
                    <div class="overflow-y-auto max-h-[400px] pr-2 custom-scrollbar transition-opacity duration-300 opacity-100">
                        <div class="grid grid-cols-2 gap-6 relative drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] z-10">
                        <template x-for="(item, index) in chartListData" :key="index">
                            <div class="space-y-2">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500" x-text="item.badge"></p>
                                <p class="text-lg font-mono" :class="item.titleColor || 'text-emerald-400'" x-text="item.title"></p>
                                <p class="text-xs font-mono text-gray-300 whitespace-pre-wrap leading-relaxed" x-text="item.subtitle" x-show="item.subtitle"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- End of CSS2D Elements -->


    </template>
</div>

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
            showInfoPanel: false,
            showChartPanel: false,
            showChartCanvas: false,
            isAudioMuted: true, // Default to muted as requested
            bgVolume: 15,       // Default background volume
            chartTitle: 'Analyse',
            chartListData: [],
            tableHeaders: [],
            tableData: [],
            currentChart: null,
            systemState: initialState, // 'good', 'warning', 'error', true, false
            activeSparks: initialSparks,
            avgProfit: avgProfit + ' €',
            totalOrders: totalOrders,
            lastSync: lastSync,
            chatHistory: [],
            idleProgress: 0, // 0-100 indicating time until spontaneous action

            // Voice AI State
            listening: false,
            thinking: false,
            continuousMode: false,
            requireWakeWord: false, // Default: Reacts to everything
            allowSpontaneous: true, // Default: On (Spontaneous Self-Analysis)
            recognition: null,
            synthesis: window.speechSynthesis,
            tokenUsage: '',
            funkiLogs: [],

            isOutputActive() {
                const audioPlaying = window.funkiAudioPlayer && !window.funkiAudioPlayer.paused && !window.funkiAudioPlayer.ended;
                const synthSpeaking = this.synthesis && this.synthesis.speaking;
                return audioPlaying || synthSpeaking;
            },

            // --- AI VOICE CHAT LOGIC ---
            toggleSpeech() {
                if (!this.recognition) return;
                if (this.thinking) return;

                if (this.continuousMode) {
                    // Turn off always-listening completely
                    this.continuousMode = false;
                    this.listening = false;
                    this.recognition.stop();
                } else {
                    // Turn on always-listening
                    if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                    if (this.synthesis && this.synthesis.speaking) this.synthesis.cancel();

                    this.continuousMode = true;
                    this.listening = true;
                    try {
                        this.recognition.start();
                    } catch (e) {
                        console.error('Failed to start recognition', e);
                    }
                }
            },

            async sendToAI(promptText, isSpontaneous = false) {
                this.thinking = true;
                this.updateCoreColor();

                // Play pulse sound while thinking
                const pulseAudio = document.getElementById('audio-funki-pulse');
                if (pulseAudio) {
                    pulseAudio.volume = 0.5;
                    pulseAudio.play().catch(e => console.log('Pulse blocked', e));
                }

                try {
                    // Append user message to memory
                    if (!isSpontaneous) {
                        this.chatHistory.push({ role: 'user', content: promptText });
                    } else {
                        this.chatHistory.push({ role: 'system', content: 'SYSTEM-BEFEHL (Verdeckt): ' + promptText });
                    }

                    const response = await fetch('/api/ai/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ history: this.chatHistory })
                    });

                    const data = await response.json();

                    if(data.status === 'success') {
                        // Update memory with new state from backend
                        if (data.history) {
                            this.chatHistory = data.history;
                        }

                        // Render Analytics if the AI used any tools
                        if (data.context_data && data.context_data.length > 0) {
                            data.context_data.forEach(ctx => {
                                this.funkiLogs.push(`Führte aus: ${ctx.function}`);
                            });
                            // Keep only last 8 logs
                            if (this.funkiLogs.length > 8) this.funkiLogs = this.funkiLogs.slice(-8);

                            this.renderAnalytics(data.context_data, data.response || '');
                        }

                        if (data.usage && data.usage.total_tokens) {
                            this.tokenUsage = `TOKENS: ${data.usage.total_tokens}`;
                        }

                        if (data.audio) {
                            this.playAudioBase64(data.audio);
                        } else {
                            this.speakResponse(data.response); // Fallback
                        }
                    } else {
                        console.error("AI Error:", data);
                        this.speakResponse("Verbindung zum Schiffscomputer fehlgeschlagen.");
                    }
                } catch (err) {
                    console.error("Fetch Error:", err);
                    this.speakResponse("Subraumkommunikation abgebrochen.");
                } finally {
                    this.thinking = false;
                    this.updateCoreColor();

                    // Stop pulse sound
                    const pulseAudio = document.getElementById('audio-funki-pulse');
                    if (pulseAudio) {
                        pulseAudio.pause();
                        pulseAudio.currentTime = 0;
                    }

                    // Restart mic if continuous mode is on and no audio is playing
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

                // 0. Check for Close UI Command First
                const closeCommand = contextData.find(c => c.function === 'close_ui');
                if (closeCommand && closeCommand.data && closeCommand.data.status === 'success') {
                    this.showInfoPanel = false;
                    this.showChartPanel = false;
                    this.tableData = [];
                    this.chartListData = [];
                    this.destroyCurrentChart();
                    this.showChartCanvas = false;
                    return; // Stop rendering anything else
                }

                let chartType = 'doughnut';
                let chartLabels = [];
                let chartDataset = [];
                let title = 'System Metriken';
                let foundData = false;

                this.chartListData = []; // Reset generic list!
                this.tableData = [];     // Reset table data!
                this.tableHeaders = [];  // Reset table headers!

                const aiText = aiResponseText.toLowerCase();
                // Check if user explicitly asked for a graphic in their last message
                const lastUserMsg = this.chatHistory.slice().reverse().find(msg => msg.role === 'user');
                const lastUserContent = lastUserMsg ? lastUserMsg.content.toLowerCase() : '';

                const userRequestedGraphic = (
                    lastUserContent.includes('grafik') ||
                    lastUserContent.includes('diagramm') ||
                    lastUserContent.includes('chart')
                );

                // 1. Check for Shop Stats (Potential Revenue & Vouchers)
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
                        // Display as Table/List
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

                // 1b. Check for Finances (Income/Expenses)
                const financeData = contextData.find(c => c.function === 'get_finances');
                if (!foundData && financeData && financeData.data && financeData.data.financial_data_net) {
                    title = 'Finanzübersicht (' + (financeData.data.current_month || 'Laufender Monat') + ')';
                    foundData = true;
                    
                    let fd = financeData.data.financial_data_net;
                    
                    if (userRequestedGraphic || true) { // Always prefer chart for finances to look cool
                        chartLabels = ['Shop Netto-Umsatz', 'Fixkosten', 'Sonderausgaben'];
                        chartDataset = [
                            fd.shop_income || 0,
                            Math.abs(fd.fixed_expenses || 0),
                            Math.abs(fd.special_expenses || 0)
                        ];
                        chartType = 'doughnut';
                    }
                }

                // 2. Check for ToDos
                const todoData = contextData.find(c => c.function === 'get_todos');
                if (!foundData && todoData && todoData.data && todoData.data.todos) {
                    title = 'Meine ToDos';
                    foundData = true;

                    if (todoData.data.todos.length === 0) {
                        this.chartListData.push({
                            title: 'Alles erledigt!',
                            titleColor: 'text-emerald-400',
                            badge: 'ToDos',
                            subtitle: 'Du hast aktuell keine offenen ToDos.'
                        });
                    } else if (userRequestedGraphic) {
                        // user wants a chart!
                        chartType = 'bar';
                        chartLabels = ['Hoch', 'Mittel', 'Niedrig'];
                        let high = 0, med = 0, low = 0;
                        todoData.data.todos.forEach(t => {
                            if (t.priority === 'high') high++;
                            else if (t.priority === 'medium') med++;
                            else low++;
                        });
                        chartDataset = [high, med, low];
                    } else {
                        // Default: render as structured Table
                        this.tableHeaders = ['Titel', 'Priorität', 'Erstellt am'];
                        todoData.data.todos.forEach(t => {
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

                // 2b. Check for ToDo Actions (Visually represent as a List Item)
                const actionData = contextData.find(c => c.function === 'create_todo' || c.function === 'complete_todo');
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

                // 2c. Check for Tool Errors (Visually represent silently)
                const errorData = contextData.find(c => c.data && c.data.status === 'error');
                if (!foundData && errorData) {
                    title = 'System Warnung';
                    foundData = true;
                    this.chartListData.push({
                         title: errorData.data.message || 'Ein Fehler ist aufgetreten',
                         titleColor: 'text-red-400',
                         badge: 'FEHLER',
                         subtitle: 'Ausführung abgebrochen'
                    });
                    this.systemState = 'error';
                    this.updateCoreColor();
                }

                // 3. Check for Calendar Events
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

                // 4. Check for Routine duration breakdown
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
                    } else if (userRequestedGraphic) {
                        chartType = 'doughnut';
                        routineData.data.routines.forEach(r => {
                            let rTitle = r.title || 'Unbenannt';
                            let duration = r.duration_minutes || 10; // Default to 10 if missing to avoid 0s
                            chartLabels.push(rTitle);
                            chartDataset.push(duration);
                        });
                    } else {
                        routineData.data.routines.forEach(r => {
                            let stepText = '';
                            if (r.steps && r.steps.length > 0) {
                                stepText = r.steps.map(s => `• ${s.title}`).join('\n');
                            }

                            this.chartListData.push({
                                title: r.title,
                                titleColor: 'text-emerald-400',
                                badge: r.duration_minutes ? r.duration_minutes + ' Min' : 'Aktiv',
                                subtitle: stepText
                            });
                        });
                    }
                }

                // 5. Check for Inventory
                const inventoryData = contextData.find(c => c.function === 'check_inventory');
                if (!foundData && inventoryData && inventoryData.data && inventoryData.data.products) {
                    title = 'Lagerbestand';
                    foundData = true;

                    if (inventoryData.data.products.length === 0) {
                        this.chartListData.push({
                            title: 'Leeres Ergebnis',
                            titleColor: 'text-yellow-400',
                            badge: 'Lager',
                            subtitle: 'Es wurden keine passenden Produkte im Lager gefunden.'
                        });
                    } else {
                        this.tableHeaders = ['SKU', 'Produkt', 'Bestand', 'Preis'];
                        inventoryData.data.products.forEach(p => {
                            let qColor = p.quantity <= 0 ? 'text-red-400' : (p.quantity < 5 ? 'text-yellow-400' : 'text-emerald-400');
                            this.tableData.push({
                                cells: [
                                    { value: p.sku || 'N/A', color: 'text-gray-500' },
                                    { value: p.name || 'Unbekannt', color: 'text-gray-200' },
                                    { value: p.quantity + ' Stück', color: qColor },
                                    { value: (p.price_formatted || (p.price ? p.price + ' €' : '-')), color: 'text-emerald-500' }
                                ]
                            });
                        });
                    }
                }

                // 6. Check for Order Details
                const orderData = contextData.find(c => c.function === 'get_order');
                if (!foundData && orderData && orderData.data && orderData.data.orders) {
                    title = 'Auftragsdetails';
                    foundData = true;

                    if (orderData.data.orders.length === 0) {
                        this.chartListData.push({
                            title: 'Nicht gefunden',
                            titleColor: 'text-yellow-400',
                            badge: 'Auftrag',
                            subtitle: 'Es konnten keine passenden Bestellungen gefunden werden.'
                        });
                    } else {
                        this.tableHeaders = ['Bestell-Nr.', 'Kunde', 'Status', 'Summe', 'Datum'];
                        orderData.data.orders.forEach(o => {
                            let statusColor = o.status === 'completed' ? 'text-emerald-400' : (o.status === 'pending' ? 'text-yellow-400' : 'text-gray-400');
                            this.tableData.push({
                                cells: [
                                    { value: o.order_number || '-', color: 'text-indigo-400' },
                                    { value: o.customer || 'Unbekannt', color: 'text-white font-bold' },
                                    { value: o.status || '-', color: statusColor },
                                    { value: o.total || '-', color: 'text-emerald-500' },
                                    { value: o.date || '-', color: 'text-gray-400' }
                                ]
                            });
                            
                            // If it's a specific single order, we could show items in a list below
                            if (orderData.data.orders.length === 1 && o.items_summary) {
                                this.chartListData.push({
                                    title: 'Bestellte Artikel',
                                    titleColor: 'text-emerald-400',
                                    badge: o.order_number,
                                    subtitle: o.items_summary
                                });
                            }
                        });
                    }
                }

                // 7. Check for Support Tickets
                const ticketData = contextData.find(c => c.function === 'get_tickets');
                if (!foundData && ticketData && ticketData.data && ticketData.data.tickets) {
                    title = 'Support Tickets';
                    foundData = true;

                    if (ticketData.data.tickets.length === 0) {
                        this.chartListData.push({
                            title: 'Alles erledigt!',
                            titleColor: 'text-emerald-400',
                            badge: 'Support',
                            subtitle: 'Es gibt aktuell keine offenen Support-Tickets.'
                        });
                    } else {
                        this.tableHeaders = ['Ticket-ID', 'Betreff', 'Status', 'Priorität', 'Datum'];
                        ticketData.data.tickets.forEach(t => {
                            let statusColor = t.status === 'open' ? 'text-emerald-400' : (t.status === 'pending' ? 'text-yellow-400' : 'text-gray-400');
                            let prioColor = t.priority === 'high' ? 'text-red-400' : 'text-gray-300';
                            this.tableData.push({
                                cells: [
                                    { value: '#' + t.id, color: 'text-indigo-400' },
                                    { value: t.subject || 'Ohne Betreff', color: 'text-white' },
                                    { value: t.status, color: statusColor },
                                    { value: t.priority, color: prioColor },
                                    { value: t.date, color: 'text-gray-500 font-mono text-xs' }
                                ]
                            });
                        });
                    }
                }

                // 8. Check for Product Reviews
                const reviewData = contextData.find(c => c.function === 'get_product_reviews');
                if (!foundData && reviewData && reviewData.data && reviewData.data.reviews) {
                    title = 'Neue Bewertungen';
                    foundData = true;

                    if (reviewData.data.reviews.length === 0) {
                        this.chartListData.push({
                            title: 'Alles geprüft!',
                            titleColor: 'text-emerald-400',
                            badge: 'Bewertungen',
                            subtitle: 'Es warten aktuell keine neuen Bewertungen auf Freigabe.'
                        });
                    } else {
                        // For reviews, we use the chartListData (dynamic generic list) because it's text heavy
                        reviewData.data.reviews.forEach(r => {
                            this.chartListData.push({
                                title: r.product_name,
                                titleColor: 'text-white',
                                badge: r.rating,
                                subtitle: `Von: ${r.customer}\n"${r.comment}"`
                            });
                        });
                    }
                }

                // 9. Check for Gamification Leaderboard
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

                // 10. Check for Customer Search
                const customerData = contextData.find(c => c.function === 'search_customers');
                if (!foundData && customerData && customerData.data && customerData.data.customers) {
                    title = 'Kundenakten';
                    foundData = true;

                    if (customerData.data.customers.length === 0) {
                        this.chartListData.push({
                            title: 'Niemand gefunden',
                            titleColor: 'text-yellow-400',
                            badge: 'Kundensuche',
                            subtitle: 'Zu diesem Suchbegriff wurde kein Kunde in der Datenbank gefunden.'
                        });
                    } else {
                        this.tableHeaders = ['Name', 'Email', 'Kunde seit', 'Bestellungen', 'Umsatz'];
                        customerData.data.customers.forEach(c => {
                            this.tableData.push({
                                cells: [
                                    { value: c.name, color: 'text-white font-bold' },
                                    { value: c.email, color: 'text-gray-400 text-xs' },
                                    { value: c.registered_since, color: 'text-gray-500' },
                                    { value: c.total_orders + 'x', color: 'text-emerald-400' },
                                    { value: c.total_spent, color: 'text-emerald-500 font-mono' }
                                ]
                            });
                        });
                    }
                }

                // 11. Default: System Health Check Flow
                if (!foundData) {
                    const healthData = contextData.find(c => c.function === 'get_system_health');
                    if (healthData && healthData.data && healthData.data.active_sessions !== undefined) {
                        title = 'Sitzungen vs Bestellungen';
                        chartLabels = ['Aktive Sitzungen', 'Bestellungen'];
                        chartDataset = [healthData.data.active_sessions || 0, healthData.data.total_orders || 0];
                    } else {
                        // Keep hidden if no relevant visualization logic triggers
                        return;
                    }
                }

                this.chartTitle = title;
                this.showChartPanel = true;

                this.$nextTick(() => {
                    this.playClickSound(); // Trigger sound for visual reveal
                    this.destroyCurrentChart();

                    const chartWrapper = document.getElementById('funki-canvas-wrapper');

                    // IF we have list data OR table data, do NOT init Chart.js!
                    if (this.chartListData.length > 0 || this.tableData.length > 0) {
                        this.showChartCanvas = false;
                        return;
                    }

                    this.showChartCanvas = true;

                    setTimeout(() => {
                        const ctx = document.getElementById('funkiDynamicChart');
                        if (!ctx) return;

                        // Ensure Chart variable is globally available
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
                    }, 300); // Allow DOM reflow & Alpine transitions to create height/width
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

            playAudioBase64(base64str) {
                if (window.funkiAudioPlayer) {
                    window.funkiAudioPlayer.pause();
                }
                const audio = new Audio("data:audio/mp3;base64," + base64str);

                // Play at natural speed
                audio.playbackRate = 1.0;

                audio.play().catch(e => console.error("Audio play prevented:", e));

                window.funkiAudioPlayer = audio;

                audio.onended = () => {
                    // Turn listening back on after speaking
                    if (this.continuousMode) {
                        this.listening = true;
                        try { this.recognition.start(); } catch(e) {}
                    }
                };
            },

            stopSpeech() {
                if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                if (this.synthesis && this.synthesis.speaking) this.synthesis.cancel();
                if (this.continuousMode && !this.listening) {
                    this.listening = true;
                    try { this.recognition.start(); } catch(e) {}
                }
            },

            speakResponse(text) {
                if (!this.synthesis) return;

                this.synthesis.cancel();

                // Fix numbers combined with characters (e.g. 2 H -> 2 Stunden), and strip Markdown
                const cleanText = text.replace(/[*_#`~>]/g, '')
                                      .replace(/%0?0|\0/g, '') // Strip empty percent artifacts or null bytes that TTS misreads
                                      .replace(/\b([0-9\.]+)\s*(?:H|h)\b/g, '$1 Stunden')
                                      .replace(/\b([0-9\.]+)\s*[Mm](?=\s|$|[.,!?])/g, '$1 Minuten')
                                      .replace(/\b(?<!\w)h(?!\w)\b/gi, ' Stunden ');

                const utterance = new SpeechSynthesisUtterance(cleanText);
                utterance.lang = 'de-DE';

                const voices = this.synthesis.getVoices();
                const germanVoice = voices.find(v => v.lang === 'de-DE' && (v.name.includes('Google') || v.name.includes('Neural')));
                if (germanVoice) {
                    utterance.voice = germanVoice;
                }

                utterance.rate = 1.05;  // Natural talking speed
                utterance.pitch = 0.95; // Natural pitch

                utterance.onend = () => {
                    if (this.continuousMode) {
                        this.listening = true;
                        try { this.recognition.start(); } catch(e) {}
                    }
                };

                this.synthesis.speak(utterance);
            },
            // --- END AI VOICE CHAT LOGIC ---

            get displayState() {
                if (this.systemState === true || this.systemState === 'good') return 'GOOD';
                if (this.systemState === 'warning') return 'WARNING';
                if (this.systemState === false || this.systemState === 'error') return 'ERROR';
                return String(this.systemState).toUpperCase();
            },

            get stateColor() {
                if (this.systemState === true || this.systemState === 'good') return 'good';
                if (this.systemState === 'warning') return 'warning';
                if (this.systemState === false || this.systemState === 'error') return 'error';
                return 'good';
            },

            init() {
                this.$watch('bgVolume', value => {
                    const bgAudio = document.getElementById('audio-funki-background');
                    if(bgAudio && !this.isAudioMuted && !t3.isShuttingDown) {
                        bgAudio.volume = value / 100;
                    }
                });

                // Bind listeners once to prevent memory leaks in the animation loop
                this.boundAnimate = () => this.animate();

                // Expose method to window so Livewire or Echo can trigger events
                window.updateFunkiStatus = (state) => {
                    this.systemState = state;
                    if(t3.coreMesh) {
                        this.updateCoreColor();
                    }
                };

                // --- Initialize Speech Recognition ---
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (SpeechRecognition) {
                    this.recognition = new SpeechRecognition();
                    this.recognition.lang = 'de-DE';
                    this.recognition.continuous = true;
                    this.recognition.interimResults = false;

                    this.recognition.onstart = () => {
                        this.listening = true;
                    };

                    this.recognition.onend = () => {
                        this.listening = false;
                        // Restart if continuous mode is active AND we are not currently speaking or thinking
                        if (this.continuousMode && !this.thinking && !this.isOutputActive()) {
                            try {
                                this.recognition.start();
                            } catch(e) {}
                        }
                    };

                    this.recognition.onresult = (event) => {
                        if (window.t3) window.t3.lastActivityTime = performance.now();
                        const transcript = event.results[event.results.length - 1][0].transcript.trim();

                        if (transcript.length > 0) {
                            const textToLower = transcript.toLowerCase();

                            // 1. Voice Interruption
                            const stopWords = ['stop', 'stopp', 'halt', 'ruhe', 'aufhören', 'pause', 'schweig', 'psst', 'leise'];
                            if (this.isOutputActive()) {
                                if (stopWords.some(w => textToLower === w || textToLower.startsWith(w) || textToLower.endsWith(w))) {
                                    console.log('Interrupted by user:', transcript);
                                    this.stopSpeech();
                                    return; // Do not send to AI
                                }
                            }

                            if (!this.continuousMode) {
                                // Manual mode: just send it
                                this.listening = false;
                                this.recognition.stop();
                                this.sendToAI(transcript);
                            } else {
                                if (this.requireWakeWord) {
                                    // Wake word detection
                                    const textToLower = transcript.toLowerCase();
                                    const wakeWords = ['funkira', 'funki', 'kira'];

                                    if (wakeWords.some(w => textToLower.includes(w))) {
                                        console.log('Funkira wake word heard: ', transcript);
                                        this.listening = false;
                                        this.recognition.stop();
                                        this.sendToAI(transcript);
                                    } else {
                                        console.log('Funkira ignored: ', transcript);
                                    }
                                } else {
                                    // React to everything
                                    console.log('Funkira (No Wake-Word) heard: ', transcript);
                                    this.listening = false;
                                    this.recognition.stop();
                                    this.sendToAI(transcript);
                                }
                            }
                        }
                    };
                } else {
                    console.error("Speech Recognition API is not supported in this browser.");
                }
            },

            playUnclickSound() {
                const unclickAudio = document.getElementById('audio-funki-unclick');
                if (unclickAudio) {
                    unclickAudio.currentTime = 0;
                    unclickAudio.volume = 0.6;
                    unclickAudio.play().catch(e => console.log(e));
                }
            },

            async openFunkiView() {
                this.showFunkiView = true;
                t3.isShuttingDown = false;
                t3.shutdownTime = null;

                const startAnimation = () => {
                    if (t3.isShuttingDown) return; // Abort if user closed during video

                    // Play Init Sound
                    const initAudio = document.getElementById('audio-funki-init');
                    if(initAudio) {
                        initAudio.currentTime = 0;
                        initAudio.volume = 0.8;
                        initAudio.play().catch(e => console.log("Audio play prevented", e));
                    }

                    // Setup & Play Background Music
                    t3.bgAudio = document.getElementById('audio-funki-background');
                    t3.ambientAudio = document.getElementById('audio-funki-default-ambient');

                    if (t3.ambientAudio) {
                        t3.ambientAudio.volume = 0;
                        t3.ambientAudio.play().catch(e => console.log(e));
                    }

                    if (t3.bgAudio) {
                        // Inherit global mute state
                        t3.bgAudio.muted = this.isAudioMuted;
                        t3.bgAudio.volume = 0; // Start at 0, fade in
                        t3.bgAudio.play().catch(e => console.log("Audio play prevented", e));
                        // Fade in slightly delayed
                        setTimeout(() => {
                            let volInt = setInterval(() => {
                                if(!this.showFunkiView || t3.isShuttingDown) { clearInterval(volInt); return; }

                                if (this.isAudioMuted) {
                                    // Fade up ambient hum quietly if main bg is muted
                                    if (t3.ambientAudio && t3.ambientAudio.volume < 0.05) {
                                        t3.ambientAudio.volume = Math.min(t3.ambientAudio.volume + 0.005, 0.05);
                                    }
                                } else {
                                    // Fade up main bg
                                    let targetVol = this.bgVolume / 100;
                                    if(t3.bgAudio.volume < targetVol) {
                                        t3.bgAudio.volume = Math.min(t3.bgAudio.volume + 0.01, targetVol);
                                    } else clearInterval(volInt);
                                }
                            }, 50);
                        }, 500);
                    }

                    // Setup Heartbeat Audio
                    t3.heartbeatAudio = document.getElementById('audio-funki-heartbeat');
                    if (t3.heartbeatAudio) {
                        t3.heartbeatAudio.volume = 0;
                        t3.heartbeatAudio.playbackRate = 1.0;
                        t3.heartbeatAudio.play().catch(e => console.log("Audio play prevented", e));
                    }

                    this.$nextTick(() => {
                        this.initThreeJS();
                        setTimeout(() => {
                            this.toggleSpeech();
                        }, 500);
                    });
                };

                // Load Chart.js
                if (typeof Chart === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdn.jsdelivr.net/npm/chart.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                // Load Three.js dynamically to prevent multiple instances warning
                if (typeof THREE === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                // Load OrbitControls
                if (typeof THREE.OrbitControls === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                // Load CSS2DRenderer
                if (typeof THREE.CSS2DRenderer === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/renderers/CSS2DRenderer.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                // Start animation ONLY AFTER all scripts are fully loaded
                startAnimation();
            },

            closeFunkiView() {
                if (t3.isShuttingDown) return; // Prevent double clicks
                t3.isShuttingDown = true;



                this.listening = false; // Stop listening visually

                // Stop processing audio loops
                const pulseAudio = document.getElementById('audio-funki-pulse');
                if(pulseAudio) {
                    pulseAudio.pause();
                    pulseAudio.currentTime = 0;
                }

                // Stop microphone playing
                this.continuousMode = false;
                if (this.recognition) this.recognition.stop();
                if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                if (this.synthesis) this.synthesis.cancel();
                t3.shutdownTime = performance.now();
                document.body.style.cursor = 'default';

                // Play Shutdown Sound
                const shutdownAudio = document.getElementById('audio-funki-shutdown');
                if (shutdownAudio) {
                    shutdownAudio.currentTime = 0;
                    shutdownAudio.volume = 0.8;
                    shutdownAudio.play().catch(e => console.log(e));
                }

                // Stop Heartbeat softly
                if (t3.heartbeatAudio) {
                    let hbInt = setInterval(() => {
                        if (t3.heartbeatAudio.volume > 0.05) t3.heartbeatAudio.volume -= 0.05;
                        else {
                            t3.heartbeatAudio.volume = 0;
                            t3.heartbeatAudio.pause();
                            t3.heartbeatAudio.currentTime = 0;
                            clearInterval(hbInt);
                        }
                    }, 50);
                }

                const globalBgAudio = document.getElementById('audio-funki-background');
                if (globalBgAudio && !globalBgAudio.paused) {
                    let bgInt = setInterval(() => {
                        if (globalBgAudio.volume > 0.05) globalBgAudio.volume -= 0.05;
                        else {
                            globalBgAudio.volume = 0;
                            globalBgAudio.pause();
                            globalBgAudio.currentTime = 0;
                            clearInterval(bgInt);
                        }
                    }, 50);
                }

                const globalAmbAudio = document.getElementById('audio-funki-default-ambient');
                if (globalAmbAudio && !globalAmbAudio.paused) {
                    let ambInt = setInterval(() => {
                        if (globalAmbAudio.volume > 0.05) globalAmbAudio.volume -= 0.05;
                        else {
                            globalAmbAudio.volume = 0;
                            globalAmbAudio.pause();
                            globalAmbAudio.currentTime = 0;
                            clearInterval(ambInt);
                        }
                    }, 50);
                }

                // Wait 2 seconds for the core to shrink/turn black
                setTimeout(() => {
                    this.showFunkiView = false; // Triggers Alpine fade-out (lasts 1s)
                    
                    // Wait for Alpine fade to finish before destroying the scene visually
                    setTimeout(() => {
                        this.destroyThreeJS();
                        document.body.style.overflow = 'auto'; // ALWAYS ensure scroll is restored
                    }, 1000);
                }, 2000);
            }, // Closing bracket for closeFunkiView method
            toggleBackgroundAudio() {
                this.isAudioMuted = !this.isAudioMuted;
                const bgAudio = document.getElementById('audio-funki-background');
                const ambientAudio = document.getElementById('audio-funki-default-ambient');

                if (bgAudio) {
                    bgAudio.muted = this.isAudioMuted;
                    if (!this.isAudioMuted && !t3.isShuttingDown) {
                        bgAudio.volume = this.bgVolume / 100;
                    }
                }

                if (ambientAudio) {
                    if (this.isAudioMuted && !t3.isShuttingDown) {
                        // Play quiet hum
                        ambientAudio.volume = 0.05;
                    } else {
                        // Silence hum
                        ambientAudio.volume = 0;
                    }
                }
            },

            initThreeJS() {
                const container = document.getElementById('funki-canvas-container');
                if (!container) return;

                // Cleanup previous instances just in case
                container.innerHTML = '';

                t3.scene = new THREE.Scene();

                t3.camera = new THREE.PerspectiveCamera(50, window.innerWidth / window.innerHeight, 1.0, 3000);
                t3.camera.position.z = 500;
                t3.camera.position.y = 50;
                t3.camera.lookAt(0, 0, 0);

                // REUSE WebGL Renderer to prevent context limit exhaustion (16 contexts max per tab)
                if (!t3.renderer) {
                    t3.renderer = new THREE.WebGLRenderer({ antialias: false, alpha: true, powerPreference: "high-performance" });
                    t3.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 0.75)); // CRITICAL: Prevent GPU TDR timeout on 4K AMD/Apple screens by raymarching slightly lower res
                }
                container.appendChild(t3.renderer.domElement);
                // Force size AFTER appending to the visible DOM
                t3.renderer.setSize(window.innerWidth, window.innerHeight);
                t3.camera.aspect = window.innerWidth / window.innerHeight;
                t3.camera.updateProjectionMatrix();

                // Setup CSS2DRenderer
                const cssContainer = document.getElementById('css2d-container');
                if (cssContainer) cssContainer.innerHTML = ''; // Reset
                if (THREE.CSS2DRenderer && cssContainer) {
                    if (!t3.cssRenderer) {
                        t3.cssRenderer = new THREE.CSS2DRenderer();
                        t3.cssRenderer.domElement.style.position = 'absolute';
                        t3.cssRenderer.domElement.style.top = '0px';
                        t3.cssRenderer.domElement.style.pointerEvents = 'none';
                    }
                    t3.cssRenderer.setSize(window.innerWidth, window.innerHeight);
                    cssContainer.appendChild(t3.cssRenderer.domElement);
                }

                // Attach controls to the main WebGL canvas so mouse drag works correctly
                t3.controls = new THREE.OrbitControls(t3.camera, t3.renderer.domElement);
                t3.controls.enableDamping = true;
                t3.controls.dampingFactor = 0.05;
                t3.controls.autoRotate = true; // Chill vibe
                t3.controls.autoRotateSpeed = 0.3;
                t3.controls.maxDistance = 800;
                // CRITICAL: Prevent camera from entering the proxy box
                t3.controls.minDistance = 200;

                // Add Lights
                t3.scene.add(new THREE.AmbientLight(0x111122));
                t3.coreLight = new THREE.PointLight(0x10b981, 2, 400);
                t3.scene.add(t3.coreLight);

                // Volumetric Raymarching Proxy Box
                // Increased box size significantly to support massive displacement and thick aura
                const coreGeometry = new THREE.BoxGeometry(250, 250, 250);

                const raymarchVertexShader = `
                    varying vec3 vWorldPosition;
                    varying vec3 vLocalPosition;

                    void main() {
                        vLocalPosition = position;
                        vec4 worldPosition = modelMatrix * vec4(position, 1.0);
                        vWorldPosition = worldPosition.xyz;
                        gl_Position = projectionMatrix * viewMatrix * worldPosition;
                    }
                `;

                const raymarchFragmentShader = `
                    uniform float time;
                    uniform vec3 glowColor;
                    uniform vec3 cameraPos;
                    uniform float hoverState;
                    uniform float hoverTime;
                    uniform float initProgress;
                    uniform float shutdownProgress;
                    uniform float isThinking; // ADDED FOR LOADING ANIMATION

                    varying vec3 vWorldPosition;
                    varying vec3 vLocalPosition;

                    // Rotational matrices
                    mat3 rotY(float a) {
                        float s = sin(a), c = cos(a);
                        return mat3(c, 0.0, s, 0.0, 1.0, 0.0, -s, 0.0, c);
                    }
                    mat3 rotX(float a) {
                        float s = sin(a), c = cos(a);
                        return mat3(1.0, 0.0, 0.0, 0.0, c, -s, 0.0, s, c);
                    }

                    // --- EPIC Smooth Organic Fluid Displacement ---
                    float smoothFluid(vec3 p) {
                        float timeScale = time * (1.2 + isThinking * 2.0); // SPEED UP WHEN THINKING

                        // Epic twisting vortex effect (Slower in center, faster on edges)
                        float l = length(p.xz);
                        float angle = l * 0.05 - timeScale * 0.8;
                        float s = sin(angle), c = cos(angle);
                        p.xz *= mat2(c, -s, s, c);

                        // Layer 1: Massive planetary waves
                        float d1 = sin(p.x*0.06 + timeScale) * cos(p.y*0.05 - timeScale*0.8) * sin(p.z*0.07 + timeScale);

                        // Layer 2: Faster magma flows
                        float d2 = cos(p.x*0.12 - timeScale*1.5) * sin(p.y*0.13 + timeScale*1.2) * cos(p.z*0.11 - timeScale);

                        // Layer 3: Energized surface ripples
                        float d3 = sin(p.x*0.25 + p.y*0.25 + timeScale*3.0) * cos(p.z*0.25 - timeScale*2.0);

                        return (d1 * 18.0) + (d2 * 9.0) + (d3 * 4.0);
                    }

                    // Signed Distance Field for the Core
                    float map(vec3 p) {
                        float critical = min(hoverTime / 4.0, 1.0); // Reaches max critical at 4 seconds

                        // Dynamic rotation, spins aggressively on hover or thinking
                        float rotSpeed = time * (0.02 + hoverState * 0.05 + critical * 0.3 + isThinking * 0.15);
                        p = rotY(rotSpeed) * rotX(rotSpeed * 0.6) * p;

                        // Base size shrinks on init/shutdown, gets super fat on hover
                        float baseRadius = 45.0 + hoverState * 8.0 + critical * 12.0;
                        baseRadius *= smoothstep(0.0, 0.8, initProgress);
                        baseRadius *= (1.0 - smoothstep(0.0, 1.0, shutdownProgress));

                        // Apply the perfectly smooth but massive fluid displacement
                        float displacement = smoothFluid(p);

                        float d = length(p) - baseRadius;
                        d -= displacement * smoothstep(0.2, 1.0, initProgress);

                        // Epic melting transition
                        if (shutdownProgress > 0.01) {
                            float melt = sin(p.y * 0.15 - time * 4.0) * cos(p.x * 0.15) * 45.0;
                            d += melt * shutdownProgress;
                        }

                        return d;
                    }

                    void main() {
                        vec3 ro = cameraPos;
                        vec3 rd = normalize(vWorldPosition - ro);

                        float t = distance(ro, vWorldPosition) + 0.1;
                        float maxT = t + 240.0; // Extend ray distance for larger aura

                        vec3 accumulatedColor = vec3(0.0);
                        float accumulatedAlpha = 0.0;

                        // Pure, deeply saturated colors! No white out in the center.
                        float critical = min(hoverTime / 4.0, 1.0);

                        // Glow gets much hotter and brighter in center, but stays true to the base color (Green/Yellow/Red)
                        vec3 currentGlowColor = mix(glowColor, glowColor * 1.5, hoverState * 0.95);
                        // If thinking, pump the core intensity
                        currentGlowColor = mix(currentGlowColor, currentGlowColor * 1.8, isThinking);

                        vec3 criticalColor = vec3(1.0, 0.05, 0.0); // Pure deep red warning
                        currentGlowColor = mix(currentGlowColor, criticalColor * 2.0, critical);

                        vec3 hotCoreColor = currentGlowColor * (2.5 + critical * 2.0 + isThinking); // Force the center to be intensely saturated

                        // Raymarching Loop (Thick, voluminous plasma logic)
                        for(int i = 0; i < 45; i++) {
                            vec3 p = ro + rd * t;
                            float d = map(p);

                            float proximity = abs(d);
                            // Exponential falloff mimics light scattering in dense gas/plasma
                            float density = exp(-proximity * 0.08);

                            if (d < 0.0) {
                                // Inside: Pure hot energy blocking light
                                accumulatedColor += hotCoreColor * 0.2;
                                accumulatedAlpha += 0.2;
                            } else {
                                // Outside: Aura and corona
                                accumulatedColor += currentGlowColor * density * 0.1;
                                accumulatedColor += hotCoreColor * exp(-proximity * 0.4) * 0.05; // Bright inner rim
                                accumulatedAlpha += density * 0.02;
                            }

                            // Safe smooth step
                            t += max(proximity * 0.5, 0.5);

                            // Early exit
                            if (t > maxT || accumulatedAlpha > 0.99) break;
                        }

                        // Dynamic Pulse System (Huge heartbeats or hyper thinking)
                        float speedBoost = critical * 15.0 + isThinking * 25.0;
                        float breathingPulse = 0.8 + 0.3 * sin(time * (2.5 + speedBoost));
                        float quickPulse = 0.7 + 0.5 * sin(time * (7.0 + speedBoost));
                        float combinedPulse = mix(breathingPulse, quickPulse, hoverState);

                        vec3 finalColor = accumulatedColor * combinedPulse * smoothstep(0.2, 1.0, initProgress);

                        // Ambient hover corona waggle (outer energy flares)
                        float softWaggle = sin(vWorldPosition.x * 0.03 + time * 3.0) * (1.5 + isThinking * 1.0);
                        finalColor += currentGlowColor * max(hoverState, isThinking * 0.5) * softWaggle * 0.15;

                        // Shutdown desaturation
                        float luma = dot(finalColor, vec3(0.299, 0.587, 0.114));
                        finalColor = mix(finalColor, vec3(luma) * 0.3, shutdownProgress);

                        float finalAlpha = min(accumulatedAlpha, 1.0);
                        finalAlpha *= smoothstep(0.0, 0.5, initProgress);
                        finalAlpha *= (1.0 - smoothstep(0.5, 1.0, shutdownProgress));

                        if (finalAlpha <= 0.01) discard;

                        gl_FragColor = vec4(finalColor, finalAlpha);
                    }
                `;

                t3.raymarchUniforms = {
                    time: { value: 0 },
                    glowColor: { value: new THREE.Color(0x00ff88) },
                    cameraPos: { value: t3.camera.position },
                    hoverState: { value: 0.0 },
                    hoverTime: { value: 0.0 },
                    initProgress: { value: 0.0 },
                    shutdownProgress: { value: 0.0 },
                    isThinking: { value: 0.0 } // ADDED FOR LOADING ANIMATION
                };

                t3.coreMaterial = new THREE.ShaderMaterial({
                    uniforms: t3.raymarchUniforms,
                    vertexShader: raymarchVertexShader,
                    fragmentShader: raymarchFragmentShader,
                    transparent: true,
                    side: THREE.FrontSide, // Render starting from the outside of the proxy box
                    depthWrite: false
                });

                t3.coreMesh = new THREE.Mesh(coreGeometry, t3.coreMaterial);
                t3.scene.add(t3.coreMesh);

                // --- NEW: Distant Glowing Planet ---
                const planetPos = new THREE.Vector3(800, 300, -1200);
                const planetGeo = new THREE.SphereGeometry(150, 32, 32);

                // Create a basic shader for an atmospheric glow on the planet
                const planetMat = new THREE.ShaderMaterial({
                    uniforms: {
                        color: { value: new THREE.Color(0x0a1a3a) },
                        glowColor: { value: new THREE.Color(0x1a4a8a) },
                        viewVector: { value: new THREE.Vector3() }
                    },
                    vertexShader: `
                        varying vec3 vNormal;
                        void main() {
                            vNormal = normalize(normalMatrix * normal);
                            gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                        }
                    `,
                    fragmentShader: `
                        uniform vec3 color;
                        uniform vec3 glowColor;
                        varying vec3 vNormal;
                        void main() {
                            float intensity = pow(0.65 - dot(vNormal, vec3(0, 0, 1.0)), 2.0);
                            gl_FragColor = vec4(mix(color, glowColor, intensity), 1.0);
                        }
                    `,
                    transparent: true,
                    blending: THREE.AdditiveBlending
                });

                t3.planetMesh = new THREE.Mesh(planetGeo, planetMat);
                t3.planetMesh.position.copy(planetPos);
                t3.scene.add(t3.planetMesh);
                // -------------------------------------------------------------

                // Invisible Hitbox specifically for tight raycasting (matches visual core size)
                const hitboxGeo = new THREE.SphereGeometry(35, 16, 16); // Scaled down matching the core
                const hitboxMat = new THREE.MeshBasicMaterial({ visible: false });
                t3.hitboxMesh = new THREE.Mesh(hitboxGeo, hitboxMat);
                t3.scene.add(t3.hitboxMesh);

                // Attach Diagnostic Panel as a CSS2DObject (Mapped to 3D)
                if (THREE.CSS2DRenderer && THREE.CSS2DObject) {
                    const panelElement = document.getElementById('diagnostic-panel');
                    if (panelElement) {
                        t3.cssObject = new THREE.CSS2DObject(panelElement);
                        t3.cssObject.scale.set(0.85, 0.85, 0.85); // Make the UI slightly smaller
                        // Start it off-screen, it gets dynamically positioned in animate()
                        t3.cssObject.position.set(-9999, 0, 0);
                        // Do NOT parent to the hitbox, parent to scene so we can control it via camera vector
                        t3.scene.add(t3.cssObject); 
                    }
                }



                t3.startTime = performance.now();
                t3.lastActivityTime = performance.now();

                // Setup resize listener & Raycaster
                window.addEventListener('resize', this.onWindowResize.bind(this));

                t3.raycaster = new THREE.Raycaster();
                t3.mouse = new THREE.Vector2(-9999, -9999);
                t3.mouseMoveListener = (event) => {
                    t3.mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
                    t3.mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
                    t3.lastActivityTime = performance.now();
                };
                window.addEventListener('mousemove', t3.mouseMoveListener);

                // Start anim loop
                this.animate();

                // Add Click Event Listener
                t3.clickListener = (event) => {
                    t3.lastActivityTime = performance.now();
                    if (t3.raycaster && t3.camera && t3.hitboxMesh && !t3.isShuttingDown && !this.showInfoPanel) {
                        t3.raycaster.setFromCamera(t3.mouse, t3.camera);
                        const intersects = t3.raycaster.intersectObject(t3.hitboxMesh);
                        if (intersects.length > 0) {
                            // Play click sound
                            const clickAudio = document.getElementById('audio-funki-click');
                            if (clickAudio) {
                                clickAudio.currentTime = 0;
                                clickAudio.volume = 0.6;
                                clickAudio.play().catch(e => console.log(e));
                            }

                            // Open panel
                            this.showInfoPanel = true;
                        }
                    }
                };
                window.addEventListener('click', t3.clickListener);

                t3.currentColor = new THREE.Color(0x00ff88);
                t3.targetColor = new THREE.Color(0x00ff88);
                t3.currentThinking = 0.0;
                t3.targetThinking = 0.0;

                // Ensure initial color reflects state directly
                this.updateCoreColor(true);
            },

            updateCoreColor(instant = false) {
                if(!t3.raymarchUniforms || !t3.coreLight || !t3.targetColor) return;

                let targetColorHex = 0x00ff88; // default green
                if (this.thinking) {
                    targetColorHex = 0xff66b2; // Deep glowing pink/rosa
                } else if (this.systemState === 'good' || this.systemState === true) {
                    targetColorHex = 0x00ff88; // Green
                } else if (this.systemState === 'warning') {
                    targetColorHex = 0xffcc00; // Yellow
                } else if (this.systemState === 'error' || this.systemState === false) {
                    targetColorHex = 0xff3333; // Red
                }

                t3.targetColor.setHex(targetColorHex);
                t3.targetThinking = this.thinking ? 1.0 : 0.0;

                // If marked as instant (like on init), bypass lerp and apply immediately
                if (instant) {
                    t3.currentColor.copy(t3.targetColor);
                    t3.currentThinking = t3.targetThinking;

                    t3.raymarchUniforms.glowColor.value.copy(t3.currentColor);
                    t3.raymarchUniforms.isThinking.value = t3.currentThinking;
                    t3.coreLight.color.copy(t3.currentColor);
                }
            },

            animate() {
                if (!this.showFunkiView) return;

                // Use the bound loop helper to avoid closure memory leaks every frame
                t3.animationId = requestAnimationFrame(this.boundAnimate);

                const now = performance.now();
                const delta = t3.lastFrameTime ? (now - t3.lastFrameTime) / 1000.0 : 0.016;
                t3.lastFrameTime = now;

                const elapsedTime = now - t3.startTime;

                // Update Shader Time uniforms
                const time = performance.now() * 0.001;
                let initProg = 0.0;
                let shutProg = 0.0;

                if(t3.raymarchUniforms) {
                    // Smoothly Lerp shader colors and thinking speeds
                    if (t3.currentColor && t3.targetColor) {
                        // 4% movement per frame gives a silky ~0.5s transition
                        t3.currentColor.lerp(t3.targetColor, 0.04);
                        t3.currentThinking += (t3.targetThinking - t3.currentThinking) * 0.04;

                        t3.raymarchUniforms.glowColor.value.copy(t3.currentColor);
                        t3.raymarchUniforms.isThinking.value = t3.currentThinking;

                        if(t3.coreLight) t3.coreLight.color.copy(t3.currentColor);
                    }

                    initProg = Math.min(elapsedTime / 3000.0, 1.0);

                    t3.raymarchUniforms.time.value = time;
                    t3.raymarchUniforms.cameraPos.value.copy(t3.camera.position);
                    t3.raymarchUniforms.initProgress.value = initProg;

                    if (t3.isShuttingDown) {
                        const sdTime = performance.now() - t3.shutdownTime;
                        shutProg = Math.min(sdTime / 2500.0, 1.0);
                        t3.raymarchUniforms.shutdownProgress.value = shutProg;
                    } else {
                        t3.raymarchUniforms.shutdownProgress.value = 0.0;
                    }
                }

                // Fade environment in/out to match core
                if (t3.planetMesh) {
                    t3.planetMesh.rotation.y = time * 0.05;
                    t3.planetMesh.material.opacity = initProg * (1.0 - shutProg) * 0.6; // Keep planet subtle
                }

                // Raycasting Interaction (Hovering the Hitbox, NOT the massive proxy box)
                if (t3.raycaster && t3.camera && t3.hitboxMesh && t3.raymarchUniforms && !t3.isShuttingDown) {
                    t3.raycaster.setFromCamera(t3.mouse, t3.camera);
                    const intersects = t3.raycaster.intersectObject(t3.hitboxMesh);

                    if (intersects.length > 0) {
                        document.body.style.cursor = 'pointer';
                        t3.raymarchUniforms.hoverState.value += (1.0 - t3.raymarchUniforms.hoverState.value) * 0.05; // Slower transition
                        t3.raymarchUniforms.hoverTime.value += delta;
                        if(t3.controls) t3.controls.autoRotateSpeed = 0.15; // Spin moderately faster instead of chaotic
                    } else {
                        document.body.style.cursor = 'default';
                        t3.raymarchUniforms.hoverState.value += (0.0 - t3.raymarchUniforms.hoverState.value) * 0.05; // Slower transition
                        t3.raymarchUniforms.hoverTime.value = Math.max(0.0, t3.raymarchUniforms.hoverTime.value - delta * 2.0); // Cool down faster
                        if(t3.controls) t3.controls.autoRotateSpeed = 0.05; // Return to chill speed
                    }
                } else if (t3.isShuttingDown && t3.raymarchUniforms) {
                     t3.raymarchUniforms.hoverState.value += (0.0 - t3.raymarchUniforms.hoverState.value) * 0.1;
                     t3.raymarchUniforms.hoverTime.value = Math.max(0.0, t3.raymarchUniforms.hoverTime.value - delta * 3.0);
                }

                // Smooth heartbeat audio control based on hoverState
                if (t3.heartbeatAudio && t3.raymarchUniforms && t3.raymarchUniforms.initProgress.value >= 1.0 && !t3.isShuttingDown) {
                    const hover = t3.raymarchUniforms.hoverState.value; // goes from 0 to 1 smoothly
                    // Max volume on hover 0.6, base 0.0
                    const targetVolume = hover * 0.6;
                    // High pitch/speed on hover, normal speed otherwise.
                    // To prevent changing pitch randomly we scale it between 1.0 and 1.8 (slower max)
                    const targetRate = 1.0 + hover * 0.8;

                    t3.heartbeatAudio.volume += (targetVolume - t3.heartbeatAudio.volume) * 0.05;
                    t3.heartbeatAudio.playbackRate += (targetRate - t3.heartbeatAudio.playbackRate) * 0.05;
                }

                // Smoothly pulsating Core Proxy Box (Make overall animation 25% smaller)
                if (t3.coreMesh) {
                    const pulse = 0.75 + Math.sin(time * 2.0) * 0.015;
                    t3.coreMesh.scale.set(pulse, pulse, pulse);
                }

                // Dynamic UI Anchoring (Screen-relative 3D Positioning)
                let panelsVisible = this.showInfoPanel || this.showChartPanel;

                // Smooth FOV zoom
                let targetFov = panelsVisible ? 60 : 50;
                if (Math.abs(t3.camera.fov - targetFov) > 0.1) {
                    t3.camera.fov += (targetFov - t3.camera.fov) * 0.05;
                    t3.camera.updateProjectionMatrix();
                }

                if (t3.cssObject) {
                    if (panelsVisible) {
                        // Dynamically calculate visible world size at the depth of the core (origin 0,0,0)
                        let dist = t3.camera.position.length();
                        let vFov = (t3.camera.fov * Math.PI) / 180;
                        let visibleHeight = 2 * Math.tan(vFov / 2) * dist;
                        let visibleWidth = visibleHeight * t3.camera.aspect;

                        // Calculate percentage-based screen offsets
                        // Wide screens -> push further left (-28%). Narrow/Mobile screens -> keep near center (-15%)
                        let percentX = t3.camera.aspect > 1.2 ? -0.28 : -0.15;
                        let percentY = 0.08; // 8% above center
                        
                        let offsetX = visibleWidth * percentX;
                        let offsetY = visibleHeight * percentY;

                        // Align vectors with the camera's local viewport angles
                        let rightVector = new THREE.Vector3(1, 0, 0).applyQuaternion(t3.camera.quaternion);
                        let upVector = new THREE.Vector3(0, 1, 0).applyQuaternion(t3.camera.quaternion);

                        // Start at core (0,0,0) and apply relative offsets
                        let targetPos = new THREE.Vector3(0, 0, 0);
                        targetPos.add(rightVector.multiplyScalar(offsetX));
                        targetPos.add(upVector.multiplyScalar(offsetY));
                        
                        // Smoothly glide into position
                        t3.cssObject.position.lerp(targetPos, 0.15);
                    } else {
                        // Tuck object away so it doesn't block rays
                        t3.cssObject.position.set(-9999, 0, 0);
                    }
                }

                // --- IDLE LOGIC ---
                if (!t3.lastActivityTime) t3.lastActivityTime = now;

                // Reset timer on active thinking/speaking but IGNORE steady listening
                if (this.thinking || this.isOutputActive()) {
                    t3.lastActivityTime = now;
                }

                const idleSeconds = (now - t3.lastActivityTime) / 1000.0;

                // Handle Waiting Heartbeat Audio (Starts at 20s)
                const waitingAudio = document.getElementById('audio-funki-waiting');
                if (waitingAudio) {
                    if (this.continuousMode && idleSeconds >= 20 && !this.thinking && !this.isOutputActive()) {
                        if (waitingAudio.paused) {
                            waitingAudio.volume = 0.3; // Low volume
                            waitingAudio.play().catch(e => console.log('Waiting audio blocked', e));
                        }
                    } else {
                        if (!waitingAudio.paused) {
                            waitingAudio.pause();
                            waitingAudio.currentTime = 0;
                        }
                    }
                }

                // Update Controls
                if (t3.controls) {
                    t3.controls.update();
                }

                if(t3.renderer && t3.scene && t3.camera) {
                    t3.renderer.render(t3.scene, t3.camera);
                }

                if(t3.cssRenderer && t3.scene && t3.camera) {
                    t3.cssRenderer.render(t3.scene, t3.camera);
                }
            },

            onWindowResize() {
                if (!t3.camera || !t3.renderer) return;
                t3.camera.aspect = window.innerWidth / window.innerHeight;
                t3.camera.updateProjectionMatrix();
                t3.renderer.setSize(window.innerWidth, window.innerHeight);
                if(t3.cssRenderer) {
                    t3.cssRenderer.setSize(window.innerWidth, window.innerHeight);
                }
            },

            destroyThreeJS() {
                if (t3.animationId) {
                    cancelAnimationFrame(t3.animationId);
                }
                window.removeEventListener('resize', this.onWindowResize);
                if(t3.mouseMoveListener) {
                    window.removeEventListener('mousemove', t3.mouseMoveListener);
                }
                if(t3.clickListener) {
                    window.removeEventListener('click', t3.clickListener);
                }

                if (t3.controls) {
                    t3.controls.dispose();
                    t3.controls = null;
                }

                // IMPORTANT: We explicitly do NOT destroy the WebGL renderer here.
                // We leave it registered in memory inside the `t3` object.
                // Browsers hard-limit tabs to roughly 8-16 WebGL contexts. If we create a new context
                // every time the popup opens, it permanently breaks until a full page reload.
                // We just clear out the objects from the scene to save live memory.
                if (t3.scene) {
                    // Primitive deep dispose
                    t3.scene.traverse((object) => {
                        if (object.isMesh || object.isPoints || object.isLine) {
                            if (object.geometry) object.geometry.dispose();
                            if (object.material) {
                                if (Array.isArray(object.material)) object.material.forEach(m => m.dispose());
                                else object.material.dispose();
                            }
                        }
                    });

                    if (t3.cssObject && t3.cssObject.element) {
                        t3.cssObject.element.style.display = 'none';
                        if (t3.cssObject.parent) {
                            t3.cssObject.parent.remove(t3.cssObject);
                        }
                    }
                    t3.scene.clear();
                }

                const container = document.getElementById('funki-canvas-container');
                if (container) container.innerHTML = '';
            }
        }));
    });
</script>
