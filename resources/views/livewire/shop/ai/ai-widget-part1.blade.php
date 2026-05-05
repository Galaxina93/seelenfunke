<div x-data="funkiView('{{ $agentColor ?? 'emerald-500' }}', '{{ $agentId }}', 'good', 42, 0, 0, '', {{ $widgetConfig ? $widgetConfig->volume : 15 }}, '{{ addslashes($agentName ?? "System") }}')"
     wire:ignore
     @open-funkira.window="openFunkiView()"
     @close-funkira.window="closeFunkiView()"
     @funki-event.window="updateFunkiStatus($event.detail.state)"
     @funki-force-stop.window="stopSpeech()"
     @ai-speech-feedback.window="speakFeedback($event.detail.text)"
     @agent-changed.window="updateAgentConfig($event.detail.color, $event.detail.name, $event.detail.wakeWord, $event.detail.agentId)"
     @ai-switch-agent.window="$wire.set('agentId', $event.detail.agent_id)"
     @toggle-mapfocus.window="console.log('toggle-mapfocus', $event.detail); let d = $event.detail; if(Array.isArray(d)) d = d[0]; if(d && d.payload) d = d.payload; isMapFocus = (d && (d.active === true || d.active === 'true' || d.active === 1)); if(isMapFocus) { isMapMode = true; }"
     @map-fly-to.window="console.log('map-fly-to', $event.detail); isMapFocus = true; isMapMode = true; if(typeof window.flyToLocation === 'function') { let p = $event.detail; if(Array.isArray(p)) p = p[0]; if(p && p.payload) p = p.payload; window.flyToLocation(p.lng, p.lat, p.zoom, p.pitch, p.markerText); }"
     @toggle-livedata.window="console.log('toggle-livedata', $event.detail); let d = $event.detail; if(Array.isArray(d)) d = d[0]; if(d && d.payload) d = d.payload; isFlightDataActive = (d && (d.active === true || d.active === 'true' || d.active === 1));"
     @ai-show-news.window="console.log('ai-show-news via alpine', $event.detail); showNewsPanel = true; /* The panel injection is still handled by window.addEventListener in part3 since it touches innerHTML */"
     @keyup.escape.window="closeFunkiView()"
     @change="$wire.saveWidgetConfig({ volume: bgVolume, agentId: activeAgentId })">

<style>
    .mapboxgl-ctrl-bottom-left, .mapboxgl-ctrl-bottom-right { display: none !important; }
    .mapboxgl-popup-close-button {
        color: white !important;
        font-size: 20px !important;
        padding: 4px 8px !important;
        background: rgba(255, 0, 68, 0.8) !important;
        border-radius: 4px !important;
        right: 5px !important;
        top: 5px !important;
        line-height: 1 !important;
        transition: all 0.2s ease;
    }
    .mapboxgl-popup-close-button:hover {
        background: rgba(255, 0, 68, 1) !important;
        transform: scale(1.1);
    }
</style>

<div x-data="{ dockOpen: false }"
     class="fixed right-0 top-1/2 -translate-y-1/2 z-[99999] flex items-center transition-transform duration-500 ease-[cubic-bezier(0.23,1,0.32,1)] pointer-events-none"
     :class="dockOpen ? 'translate-x-0' : 'translate-x-[calc(100%-8px)]'">

    <!-- INTERAKTIVE GLOW-ZONE (Analog zu action_dock.blade.php) -->
    <div @click="dockOpen = !dockOpen"
         class="absolute left-0 top-0 bottom-0 w-12 cursor-pointer flex items-center justify-center group pointer-events-auto"
         style="margin-left: -25px;">

        <div class="relative flex items-center justify-center">
            <div class="absolute inset-0 w-6 h-16 bg-emerald-500/40 rounded-full blur-md animate-pulse"></div>
            <div class="relative w-6 h-16 bg-emerald-500 rounded-full shadow-[0_0_20px_rgba(16,185,129,1)] transition-all duration-300 group-hover:h-20"
                 :class="dockOpen ? 'opacity-20' : 'opacity-100'">
            </div>
            <div class="absolute text-gray-900 transition-transform duration-500"
                 :class="dockOpen ? 'rotate-0' : 'rotate-180'">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                    <path d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- DAS HAUPT-DOCK -->
    <div x-show="!(isMobile && isMapFocus)" class="bg-black/95 backdrop-blur-xl border-l border-t border-b border-emerald-900/50 rounded-l-3xl shadow-[-10px_0_30px_rgba(16,185,129,0.15)] flex flex-col w-24 max-h-[80vh] overflow-y-auto pointer-events-auto custom-scrollbar transition-all duration-300 group/dock relative p-4">
        <div class="flex flex-col gap-3">
            <div class="text-[10px] font-black uppercase tracking-widest text-emerald-500/50 border-b border-emerald-900/30 pb-3 mb-1 flex flex-col gap-3">
                <div class="flex flex-col gap-3">
                    <div class="leading-tight break-words break-all hyphens-auto flex flex-col gap-2 w-full">
                        <div class="flex items-center justify-between">
                            <span>Live-AI</span>
                            <button wire:click.stop="createNewChat" class="text-[var(--theme-color)] hover:text-white" title="Neuen Chat erstellen">
                                <x-heroicon-o-plus class="w-3 h-3" />
                            </button>
                        </div>
                        <select wire:model.live="agentId" class="w-full bg-black/50 border border-emerald-900/50 rounded text-[9px] text-emerald-400 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 py-1 pl-1 pr-4 appearance-none cursor-pointer uppercase tracking-wider outline-none">
                            @foreach($availableAgents as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="currentChatSessionId" class="w-full bg-black/50 border border-emerald-900/50 rounded text-[9px] text-emerald-400 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 py-1 pl-1 pr-4 appearance-none cursor-pointer uppercase tracking-wider outline-none" title="Chat Referenz">
                            @foreach($this->chatSessions() as $chat)
                                <option value="{{ $chat->id }}">{{ Str::limit($chat->title, 15) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button x-show="continuousMode" @click="fullStop()" x-cloak class="text-rose-500 hover:text-rose-400 flex items-center justify-center gap-1.5 bg-rose-900/40 px-2 py-1.5 rounded-lg border border-rose-500/30 transition-colors w-full shadow-inner" title="Alles stoppen & Mikrofon aus">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 shrink-0">
                            <rect x="5" y="5" width="10" height="10" rx="1" />
                        </svg>
                        <span>Mic Aus</span>
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-4 items-start border-b border-emerald-900/30 pb-4 mb-1">
                @if($agentIsActive)
                <button @click="$dispatch('open-funkira'); if(!isLiveMode) toggleLiveMode(); dockOpen = false" class="w-full text-[var(--theme-color)] hover:opacity-80 flex items-center justify-center gap-2 bg-[var(--theme-color-10)] px-3 py-2.5 rounded-xl border border-[var(--theme-color-30)] transition-all hover:scale-[1.02] shadow-[0_0_15px_var(--theme-color-10)]" style="--theme-color: #10b981; --theme-color-10: rgba(16,185,129,0.1); --theme-color-30: rgba(16,185,129,0.3);" title="Zentrum Öffnen">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                        <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12z"/>
                        <circle cx="10" cy="10" r="3"/>
                    </svg>
                    <span class="font-bold tracking-widest text-[10px] uppercase">3D</span>
                </button>
                @endif

                <!-- Live Mode Toggle -->
                <label class="flex items-center gap-2 cursor-pointer group" title="Live-AI (Echtzeit Sprachmodus)">
                    <div class="relative w-8 h-4 transition-colors rounded-full" :class="isLiveMode ? 'bg-emerald-500' : 'bg-gray-700'">
                        <input type="checkbox" x-on:click.prevent="toggleLiveMode()" :checked="isLiveMode" class="sr-only">
                        <div class="absolute w-4 h-4 transition-transform bg-white rounded-full shadow-md" :class="isLiveMode ? 'translate-x-4' : 'translate-x-0'"></div>
                    </div>
                    <div class="flex items-center gap-2 text-gray-400 group-hover:text-emerald-400 transition-colors text-xs font-mono font-bold">
                        <x-heroicon-o-bolt class="w-4 h-4" />
                    </div>
                </label>
            </div>
        </div>
    </div>
</div>

    <template x-teleport="body">
        <div x-show="showFunkiView"
             x-transition:enter="transition ease-out duration-1000"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-1000"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             style="display: none; height: 100dvh;"
             class="fixed inset-0 z-[99999] bg-[#03050a] overflow-hidden font-mono">

    <!-- Mapbox GL JS -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.css" rel="stylesheet" />
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.js"></script>

    <!-- Mapbox Map Container (Background) -->
    <div id="funki-map-container" class="absolute inset-0 w-full h-full z-0 transition-opacity duration-1000 opacity-100 pointer-events-auto" style="filter: contrast(1.1) brightness(0.9) saturate(1.2);"></div>

    <!-- Sci-Fi HUD Overlay (Grid + Scanlines + Vignette) -->
    <div class="absolute inset-0 z-0 pointer-events-none opacity-30 mix-blend-screen"
         style="background-image: linear-gradient(rgba(0, 240, 255, 0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 240, 255, 0.15) 1px, transparent 1px); background-size: 50px 50px;">
    </div>
    <!-- Scanlines -->
    <div class="absolute inset-0 z-0 pointer-events-none opacity-10 mix-blend-overlay"
         style="background: repeating-linear-gradient(0deg, transparent, transparent 2px, #000 2px, #000 4px);">
    </div>
    <!-- Vignette / Glow -->
    <div class="absolute inset-0 z-0 pointer-events-none"
         style="box-shadow: inset 0 0 150px 50px rgba(1, 6, 15, 0.9);">
    </div>

    <!-- CSS2D Container for HTML elements in 3D -->
    <div id="css2d-container" class="absolute inset-0 w-full h-full pointer-events-none z-10" style="pointer-events: none;"></div>

    <!-- Canvas Container (3D Hologram) -->
    <div id="funki-canvas-container" class="absolute inset-0 w-full h-full z-10 transition-all duration-1000 ease-[cubic-bezier(0.34,1.56,0.64,1)] transform-gpu origin-bottom-right" :class="isMapFocus ? 'scale-[0.25] -translate-x-[2vw] -translate-y-[15vh] pointer-events-none rounded-3xl overflow-hidden' : (isMapMode ? 'pointer-events-none' : 'pointer-events-auto')"></div>
    <div id="funki-mapbox-container" class="absolute inset-0 w-full h-full z-[5] transition-all duration-1000 ease-[cubic-bezier(0.23,1,0.32,1)]" 
         :class="isMapMode ? 'opacity-100 scale-100 pointer-events-auto shadow-[inset_0_0_200px_rgba(0,0,0,0.9)]' : 'opacity-0 scale-110 blur-xl pointer-events-none'"
         :style="isMapMode ? 'filter: brightness(0.6) sepia(0.3) hue-rotate(180deg) saturate(2.0);' : 'filter: brightness(0.5);'"></div>

    <!-- Sci-Fi News Panel (Stunning Hologram) -->
    <div x-show="showNewsPanel" x-transition:enter="transition ease-out duration-1000 delay-300" x-transition:enter-start="opacity-0 translate-y-12 scale-90" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-500" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-12 scale-90" class="absolute left-6 bottom-6 z-50 w-[420px] max-w-[90vw] pointer-events-auto" style="display: none;">
        <div class="bg-black/40 border border-cyan-400/40 rounded-2xl p-5 backdrop-blur-3xl shadow-[0_0_50px_rgba(0,240,255,0.2),inset_0_0_20px_rgba(0,240,255,0.1)] relative overflow-hidden group">
            <!-- Glitch decoration lines -->
            <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-cyan-300 to-transparent opacity-80 shadow-[0_0_10px_rgba(0,240,255,1)]"></div>
            <div class="absolute bottom-0 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-cyan-500/50 to-transparent"></div>
            <div class="absolute -left-1 top-6 w-1 h-16 bg-cyan-400 rounded-full shadow-[0_0_15px_rgba(0,240,255,1)] group-hover:h-24 transition-all duration-500"></div>
            <div class="absolute -right-1 top-12 w-[2px] h-8 bg-cyan-500/50 rounded-full"></div>
            
            <div class="flex justify-between items-center mb-5 border-b border-cyan-500/20 pb-3">
                <div class="flex items-center gap-3">
                    <div class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500 shadow-[0_0_10px_rgba(239,68,68,1)]"></span>
                    </div>
                    <h3 class="text-cyan-300 font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-shadow-[0_0_10px_rgba(0,240,255,0.5)]">
                        Global Intercept
                    </h3>
                </div>
                <button @click="showNewsPanel = false" class="text-cyan-500/60 hover:text-cyan-300 transition-colors bg-cyan-900/20 rounded-full p-1 border border-cyan-500/20 hover:border-cyan-500/50 hover:shadow-[0_0_15px_rgba(0,240,255,0.3)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <div id="news-panel-content" class="flex flex-col gap-4 max-h-[400px] overflow-y-auto custom-scrollbar">
                <!-- News items will be injected here -->
            </div>
        </div>
    </div>

    <!-- UI Overlay Navigation -->
    <div class="absolute top-6 right-6 z-50 flex flex-col items-end gap-2" x-transition:enter="transition ease-out duration-1000 delay-500" x-transition:enter-start="opacity-0 translate-y-[-20px]" x-transition:enter-end="opacity-100 translate-y-0">

        <!-- Control Bar: Aufgaben, Log, Pause, Stop, Agent -->
        <x-backend.ai-widget-navigation :available-agents="$availableAgents" />

        <!-- Token Usage -->
        <div x-show="tokenUsage" x-transition class="flex items-center gap-2 px-3 py-1 mt-1 bg-gray-900/80 border border-gray-700 rounded-lg shadow-[0_0_15px_rgba(16,185,129,0.2)] backdrop-blur-md" style="display: none;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-emerald-400"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.5 10a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd" /></svg>
            <span class="text-[10px] font-mono font-bold text-gray-300 tracking-wider" x-text="tokenUsage"></span>
        </div>

        <!-- Action Debug Log -->
        <div class="flex flex-col items-end w-full">
            <div x-show="showDebugLog" x-collapse x-transition class="w-80 mt-2 p-3 bg-black/60 border border-emerald-900/50 rounded-lg backdrop-blur-md shadow-[0_0_20px_rgba(16,185,129,0.1)] flex flex-col gap-2 max-h-80 overflow-y-auto pointer-events-auto custom-scrollbar" style="display: none;">
                <div class="text-[8px] font-black uppercase tracking-widest text-emerald-500/50 border-b border-emerald-900/30 pb-2 mb-1 sticky top-0 bg-black/80 z-10">KI Aktionen (Live-Log)</div>
                <template x-for="(log, i) in funkiLogs.slice().reverse()" :key="i">
                    <div class="text-[10px] font-mono leading-tight text-gray-300 break-words flex flex-col gap-1 border-b border-white/5 pb-2 last:border-0 last:pb-0" x-data="{ expandedLog: false }">
                        <div class="flex justify-between items-center text-[8px] mb-0.5">
                            <span class="text-emerald-500/60" x-text="log.time"></span>
                            <span x-show="log.role === 'user'" class="text-blue-400 bg-blue-500/10 px-1 py-0.5 rounded">User</span>
                            <span x-show="log.role === 'ai'" class="text-cyan-400 bg-cyan-500/10 px-1 py-0.5 rounded">Funkira</span>
                            <span x-show="log.role === 'tool'" class="text-purple-400 bg-purple-500/10 px-1 py-0.5 rounded">Tool</span>
                        </div>
                        <div class="flex gap-2">
                            <span x-show="log.role === 'user'" class="text-blue-500 shrink-0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 mt-0.5"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg></span>
                            <span x-show="log.role === 'ai'" class="text-cyan-500 shrink-0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 mt-0.5"><path fill-rule="evenodd" d="M11 2a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V3a1 1 0 00-1-1h-4zm-7 1a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4a1 1 0 00-1-1h-4v4H4V5h4a1 1 0 001-1V3a2 2 0 00-2-2H4z" clip-rule="evenodd" /></svg></span>
                            <span x-show="log.role === 'tool'" class="text-purple-500 shrink-0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 mt-0.5"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.381z" clip-rule="evenodd" /></svg></span>
                            <div class="flex flex-col w-full">
                                <span class="leading-relaxed" x-text="expandedLog ? log.message.replace(/<speak>/gi, '').replace(/<\/speak>/gi, '') : log.message.replace(/<speak>/gi, '').replace(/<\/speak>/gi, '').substring(0, 150) + (log.message.length > 150 ? '...' : '')"></span>
                                <button x-show="log.message.length > 150" @click="expandedLog = !expandedLog" class="text-[8px] uppercase font-bold text-emerald-400 mt-1 hover:text-white self-start bg-emerald-900/30 px-1.5 py-0.5 rounded transition-colors">
                                    <span x-text="expandedLog ? 'Weniger anzeigen' : 'Mehr lesen'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Widget Task List (Live Log) -->
    @include('livewire.shop.ai.blocks.widget-tasks')

    <!-- Widget Files & Plans (Removed, now managed centrally in workspace) -->



    <!-- Bottom Right Controls (Audio & Close) -->
    <div class="absolute right-6 z-50 flex flex-col items-end gap-3 pointer-events-auto" style="bottom: max(1.5rem, env(safe-area-inset-bottom));" x-transition:enter="transition ease-out duration-1000 delay-500" x-transition:enter-start="opacity-0 translate-y-[20px]" x-transition:enter-end="opacity-100 translate-y-0">

        <!-- Audio Toggle & Slider -->
        <div x-data="{ showVol: false }" @click.outside="showVol = false" class="flex items-center gap-2 px-3 py-1 bg-gray-900/80 border border-gray-700 rounded-full shadow-glow backdrop-blur-md transition-all hover:border-emerald-500 hover:bg-black">
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
            <button @click="showVol = !showVol" class="w-6 h-8 flex justify-center items-center text-gray-500 hover:text-gray-300 transition-colors" title="Lautstärke regeln">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                </svg>
            </button>
            <input type="range" min="0" max="100" x-model="bgVolume"
                   x-show="showVol" x-transition
                   class="w-20 h-1 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-emerald-500"
                   title="Lautstärke" />
        </div>

        <!-- Close Button -->
        <button @click="closeFunkiView()" class="w-12 h-12 bg-gray-900/80 border border-gray-700 rounded-full flex items-center justify-center hover:text-white hover:border-emerald-500 hover:bg-black transition-all shadow-[0_0_15px_rgba(16,185,129,0.2)] backdrop-blur-md group" title="Zentrum verlassen">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-300 group-hover:text-red-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 9l-3 3m0 0l3 3m-3-3h7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>
    </div>

    <!-- Push to Talk Mobile Anchor -->
    <div x-show="isMobile && !continuousMode" class="absolute left-1/2 -translate-x-1/2 z-[100] flex flex-col items-center gap-2 pointer-events-auto" style="display: none; bottom: max(2.5rem, calc(env(safe-area-inset-bottom) + 1rem));">
        <span class="text-[10px] font-mono tracking-widest text-emerald-400/80 uppercase" x-show="!listening && !thinking">Halten zum Sprechen</span>
        <span class="text-[10px] font-mono tracking-widest text-cyan-400 uppercase animate-pulse" x-show="listening" x-text="activeAgentName + ' hört zu...'"></span>
        <span class="text-[10px] font-mono tracking-widest text-purple-400 uppercase animate-pulse" x-show="thinking" x-text="activeAgentName + ' verarbeitet...'"></span>

        <button
            @touchstart.prevent="startPushToTalk()"
            @mousedown.prevent="startPushToTalk()"
            @touchend.prevent="stopPushToTalk()"
            @mouseup.prevent="stopPushToTalk()"
            @mouseleave.prevent="stopPushToTalk()"
            :class="{'bg-emerald-600 border-emerald-400 scale-110 shadow-[0_0_30px_rgba(16,185,129,0.6)]': listening, 'bg-gray-800 border-gray-600 text-gray-400 hover:border-emerald-500': !listening, 'opacity-50 pointer-events-none': thinking || isOutputActive()}"
            class="w-20 h-20 rounded-full border-2 flex items-center justify-center transition-all duration-200 backdrop-blur-md active:scale-95 touch-none">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-10 h-10" :class="{'text-white': listening, 'text-gray-400': !listening}">
              <path d="M7 4a3 3 0 016 0v6a3 3 0 11-6 0V4z" />
              <path d="M5.5 9.643a.75.75 0 00-1.5 0V10c0 3.06 2.29 5.585 5.25 5.954V17.5h-1.5a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5h-1.5v-1.546A6.001 6.001 0 0016 10v-.357a.75.75 0 00-1.5 0V10a4.5 4.5 0 01-9 0v-.357z" />
            </svg>
        </button>
    </div>

    <!-- Audio Elements -->
    <audio id="audio-funki-background" src="{{ asset('shop/ai/sounds/ai_background.mp3') }}" preload="auto" playsinline webkit-playsinline loop></audio>
    <audio id="audio-funki-default-ambient" src="{{ asset('shop/ai/sounds/ai_default_universum.mp3') }}" preload="auto" playsinline webkit-playsinline loop></audio>
    <audio id="audio-funki-pulse" src="{{ asset('shop/ai/sounds/ai_pulse.mp3') }}" preload="auto" playsinline webkit-playsinline loop></audio>
    <audio id="audio-funki-init" src="{{ asset('shop/ai/sounds/ai_Initialize.mp3') }}" preload="auto" playsinline webkit-playsinline></audio>
    <audio id="audio-funki-shutdown" src="{{ asset('shop/ai/sounds/ai_shutdown.mp3') }}" preload="auto" playsinline webkit-playsinline></audio>
    <audio id="audio-funki-heartbeat" src="{{ asset('shop/ai/sounds/ai_heartbeat.mp3') }}" preload="auto" playsinline webkit-playsinline loop></audio>
    <audio id="audio-funki-click" src="{{ asset('shop/ai/sounds/ai_click.mp3') }}" preload="auto" playsinline webkit-playsinline></audio>
    <audio id="audio-funki-unclick" src="{{ asset('shop/ai/sounds/ai_unclick.mp3') }}" preload="auto" playsinline webkit-playsinline></audio>

    <!-- Unified Floating UI Panel (Mapped to 3D Space) -->
    <div id="diagnostic-panel"></div>

    <!-- Generative UI Master Modal extracted to layout -->

    </template>
</div>
