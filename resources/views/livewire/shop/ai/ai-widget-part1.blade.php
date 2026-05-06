<div x-data="funkiView('{{ $agentColor ?? 'emerald-500' }}', '{{ $agentId }}', 'good', 42, 0, 0, '', {{ $widgetConfig ? $widgetConfig->volume : 15 }}, '{{ addslashes($agentName ?? "System") }}', {{ ($widgetConfig && isset($widgetConfig->allow_voice_interruption)) ? ($widgetConfig->allow_voice_interruption ? 'true' : 'false') : 'null' }})"
     wire:ignore
     @open-funkira.window="openFunkiView()"
     @close-funkira.window="closeFunkiView()"
     @funki-event.window="updateFunkiStatus($event.detail.state)"
     @funki-force-stop.window="stopSpeech()"
     @ai-speech-feedback.window="speakFeedback($event.detail.text)"
     @agent-changed.window="updateAgentConfig($event.detail.color, $event.detail.name, $event.detail.wakeWord, $event.detail.agentId)"
     @ai-switch-agent.window="handleAgentSwitch($event.detail.agent_id)"
     @toggle-mapfocus.window="console.log('toggle-mapfocus', $event.detail); let d = $event.detail; if(Array.isArray(d)) d = d[0]; if(d && d.payload) d = d.payload; isMapFocus = (d && (d.active === true || d.active === 'true' || d.active === 1)); if(isMapFocus) { isMapMode = true; }"
     @map-fly-to.window="console.log('map-fly-to', $event.detail); isMapFocus = true; isMapMode = true; if(typeof window.flyToLocation === 'function') { let p = $event.detail; if(Array.isArray(p)) p = p[0]; if(p && p.payload) p = p.payload; window.flyToLocation(p.lng, p.lat, p.zoom, p.pitch, p.markerText); }"
     @toggle-livedata.window="console.log('toggle-livedata', $event.detail); let d = $event.detail; if(Array.isArray(d)) d = d[0]; if(d && d.payload) d = d.payload; isFlightDataActive = (d && (d.active === true || d.active === 'true' || d.active === 1));"
     @map-mark-news-events.window="console.log('map-mark-news-events', $event.detail); isMapFocus = true; isMapMode = true; if(typeof window.markNewsEvents === 'function') { let p = $event.detail; if(Array.isArray(p)) p = p[0]; if(p && p.payload) p = p.payload; window.markNewsEvents(p.markers); }"
     @map-mark-places.window="console.log('map-mark-places', $event.detail); isMapFocus = true; isMapMode = true; if(typeof window.markPlaces === 'function') { let p = $event.detail; if(Array.isArray(p)) p = p[0]; if(p && p.payload) p = p.payload; window.markPlaces(p.markers); }"
     @map-clear-markers.window="console.log('map-clear-markers'); if(typeof window.clearMarkers === 'function') { window.clearMarkers(); }"
     @ui-focus-widget.window="setMainScreenWidget($event.detail.type, $event.detail.index)"
     @ui-clear-focus.window="clearMainScreenWidget()"
     @ai-show-news.window="console.log('ai-show-news via alpine', $event.detail); showNewsPanel = true; /* The panel injection is still handled by window.addEventListener in part3 since it touches innerHTML */"
     @ai-show-persona.window="console.log('ai-show-persona', $event.detail); personaWidgets.unshift($event.detail.payload || $event.detail); showFunkiView = true; setMainScreenWidget('persona', 0);"
     @ai-toggle-youtube-mute.window="let d = $event.detail; let mute = (d && (d.mute === true || d.mute === 'true' || d.mute === 1)); youtubeWidgets = youtubeWidgets.map(w => { let url = new URL(w.video); url.searchParams.set('mute', mute ? '1' : '0'); w.video = url.toString(); return w; });"
     @ai-summarize-youtube.window="let d = $event.detail; let idx = (d && typeof d.index !== 'undefined' && d.index !== null) ? d.index : ((mainScreenWidget && mainScreenWidget.type === 'youtube') ? mainScreenWidget.index : 0); let vid = youtubeWidgets[idx]; if(vid) { let targetUrl = vid.url || vid.video; $wire.set('input', 'Fasse bitte dieses Video zusammen und erstelle ein News-Widget mit den wichtigsten Punkten: ' + targetUrl); $wire.sendMessage(); } else { console.warn('Kein Video gefunden für Zusammenfassung'); }"
     @toggle-voice-interruption.window="allowVoiceInterruption = !allowVoiceInterruption; $wire.saveWidgetConfig({ volume: bgVolume, agentId: activeAgentId, allowVoiceInterruption: allowVoiceInterruption });"
     @keyup.escape.window="closeFunkiView()"
     @change="$wire.saveWidgetConfig({ volume: bgVolume, agentId: activeAgentId, allowVoiceInterruption: allowVoiceInterruption })">

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

    <!-- Mapbox GL JS & Anime.js -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.css" rel="stylesheet" />
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>

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
    <div id="css2d-container" class="absolute inset-0 w-full h-full z-10" :class="isMapFocus ? 'pointer-events-none' : 'pointer-events-auto'"></div>

    <!-- Main Screen Focus Backdrop -->
    <div x-show="mainScreenWidget" x-transition.opacity 
         class="fixed inset-0 bg-black/80 z-[9998] pointer-events-auto backdrop-blur-sm" 
         @click="clearMainScreenWidget()" style="display: none;"></div>

    <!-- Canvas Container (3D Hologram) -->
    <div id="funki-canvas-container" class="absolute inset-0 w-full h-full z-10 transition-all duration-1000 ease-[cubic-bezier(0.34,1.56,0.64,1)] transform-gpu origin-bottom-right" :class="isMapFocus ? 'scale-[0.25] -translate-x-[2vw] -translate-y-[15vh] pointer-events-none rounded-3xl overflow-hidden' : (isMapMode ? 'pointer-events-none' : 'pointer-events-auto')"></div>
    <div id="funki-mapbox-container" class="absolute inset-0 w-full h-full z-[5] transition-all duration-1000 ease-[cubic-bezier(0.23,1,0.32,1)]"
         :class="isMapMode ? 'opacity-100 scale-100 pointer-events-auto shadow-[inset_0_0_200px_rgba(0,0,0,0.9)]' : 'opacity-0 scale-110 blur-xl pointer-events-none'"
         :style="isMapMode ? 'filter: brightness(0.6) sepia(0.3) hue-rotate(180deg) saturate(2.0);' : 'filter: brightness(0.5);'"></div>

    <!-- News UI Panel (2D Overlay) -->
    <div x-show="newsWidgets && newsWidgets.length" x-transition 
         class="absolute left-2 sm:left-6 top-20 sm:top-6 bottom-20 sm:bottom-6 w-[calc(100%-1rem)] sm:w-[350px] pointer-events-none flex flex-col gap-4 overflow-y-auto custom-scrollbar" 
         :class="(mainScreenWidget && mainScreenWidget.type === 'news') ? 'z-[9999]' : 'z-[60]'" style="display: none;" x-cloak>
        <template x-for="(article, index) in newsWidgets" :key="index">
            <div :class="{
                    'fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[90vw] md:w-[70vw] max-w-[1000px] h-auto max-h-[90vh] z-[9999] shadow-[0_0_80px_rgba(16,185,129,0.3)] flex flex-col': mainScreenWidget && mainScreenWidget.type === 'news' && mainScreenWidget.index === index,
                    'w-full shrink-0 relative': !(mainScreenWidget && mainScreenWidget.type === 'news' && mainScreenWidget.index === index),
                    '!opacity-20 !blur-md pointer-events-none': mainScreenWidget && mainScreenWidget.type === 'news' && mainScreenWidget.index !== index
                 }"
                 class="bg-black/80 rounded-xl p-4 backdrop-blur-xl font-mono text-sm pointer-events-auto overflow-hidden origin-left transition-all duration-700 ease-[cubic-bezier(0.23,1,0.32,1)]"
                 :style="{ 'border': '1px solid ' + getHexColorStr(agentColor) + '80', 'box-shadow': '0 0 30px ' + getHexColorStr(agentColor) + '33', 'color': getHexColorStr(agentColor) }"
                 x-init="
                    if (typeof anime !== 'undefined') {
                        anime({
                            targets: $el,
                            translateX: [-100, 0],
                            opacity: [0, 1],
                            scale: [0.9, 1],
                            delay: index * 150,
                            easing: 'easeOutElastic(1, .6)',
                            duration: 1200
                        });
                    } else {
                        $el.style.opacity = 1;
                    }
                 "
                 @mouseenter="
                    if(typeof anime !== 'undefined') {
                        anime({
                            targets: $el,
                            scale: 1.02,
                            boxShadow: `0 0 50px ${getHexColorStr(agentColor)}80`,
                            duration: 400,
                            easing: 'easeOutExpo'
                        });
                    }
                 "
                 @mouseleave="
                    if(typeof anime !== 'undefined') {
                        anime({
                            targets: $el,
                            scale: 1.0,
                            boxShadow: `0 0 30px ${getHexColorStr(agentColor)}33`,
                            duration: 600,
                            easing: 'easeOutExpo'
                        });
                    }
                 ">

                <!-- Background Grid Pattern -->
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSA0MCAwIEwgMCAwIDAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-30"></div>

                <!-- Number Badge -->
                <span class="absolute top-2 left-2 z-20 bg-black/60 text-white font-bold px-1.5 py-0.5 rounded text-[10px] backdrop-blur-md border border-white/10" x-text="'#' + (index + 1)"></span>

                <div class="relative z-10 flex flex-col h-full" x-data="{ copySuccess: false }">
                    <a :href="article.url || '#'" target="_blank" class="block group cursor-pointer shrink-0">
                        <img x-show="article.image" :src="article.image" 
                             :class="(mainScreenWidget && mainScreenWidget.type === 'news' && mainScreenWidget.index === index) ? 'h-[40vh] max-h-[500px]' : 'h-40'"
                             class="w-full object-cover rounded-md mb-3 border shadow-md transition-all duration-700 group-hover:scale-[1.02]" :style="{ 'border-color': getHexColorStr(agentColor) + '4D' }" />

                        <div x-show="article.video" 
                             :class="(mainScreenWidget && mainScreenWidget.type === 'news' && mainScreenWidget.index === index) ? 'h-[40vh] max-h-[500px]' : 'h-40'"
                             class="w-full mb-3 rounded-md overflow-hidden border shadow-md transition-all duration-700" :style="{ 'border-color': getHexColorStr(agentColor) + '4D' }">
                            <iframe :src="article.video" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                        </div>

                        <h3 class="font-bold mb-2 pr-8 uppercase drop-shadow-md leading-tight group-hover:underline" :style="{ 'color': getHexColorStr(agentColor), 'filter': 'brightness(1.2)' }" x-text="article.title"></h3>
                    </a>
                    <p :class="(mainScreenWidget && mainScreenWidget.type === 'news' && mainScreenWidget.index === index) ? 'text-sm md:text-lg overflow-y-auto custom-scrollbar flex-1' : 'line-clamp-4 text-[11px]'" 
                       class="opacity-80 leading-relaxed mb-3 text-white transition-all duration-700" x-text="article.description"></p>
                    <div class="flex justify-between items-center border-t pt-3 shrink-0" :style="{ 'border-color': getHexColorStr(agentColor) + '4D' }">
                        <span class="text-[9px] uppercase font-bold tracking-widest opacity-80" x-text="article.source || 'LIVE FEED'"></span>
                        <div class="flex gap-2">
                            <!-- Copy Link Button -->
                            <button x-show="article.url" @click="navigator.clipboard.writeText(article.url); copySuccess = true; setTimeout(function(){copySuccess = false}, 2000)" 
                                    class="p-2 bg-black/50 hover:bg-white/10 rounded border transition-colors cursor-pointer relative" 
                                    :style="{ 'border-color': getHexColorStr(agentColor) + '80', 'color': getHexColorStr(agentColor) }" title="Link kopieren">
                                <svg x-show="!copySuccess" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <svg x-show="copySuccess" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                            <a :href="article.url || '#'" target="_blank" class="px-4 py-2 bg-black/50 hover:bg-white/10 rounded border transition-colors uppercase font-bold cursor-pointer min-w-16 text-center" :style="{ 'border-color': getHexColorStr(agentColor) + '80', 'color': getHexColorStr(agentColor) }">Öffnen</a>
                        </div>
                    </div>
                <div class="absolute top-2 right-2 z-20 flex gap-2">
                    <button @click="mainScreenWidget && mainScreenWidget.type === 'news' && mainScreenWidget.index === index ? clearMainScreenWidget() : setMainScreenWidget('news', index)" class="p-2 bg-black/50 rounded-full border backdrop-blur-md transition-colors hover:bg-white/10 cursor-pointer" :style="{ 'border-color': getHexColorStr(agentColor) + '4D', 'color': getHexColorStr(agentColor) }" title="Hauptschirm An/Aus">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                    </button>
                    <button @click="newsWidgets.splice(index, 1)" class="p-2 bg-black/50 rounded-full border backdrop-blur-md transition-colors hover:bg-white/10 cursor-pointer" :style="{ 'border-color': getHexColorStr(agentColor) + '4D', 'color': getHexColorStr(agentColor) }">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- YouTube Video Pool (2D Overlay) -->
    <div x-show="youtubeWidgets && youtubeWidgets.length" x-transition
         class="absolute right-4 sm:right-32 top-24 bottom-20 w-[calc(100%-2rem)] sm:w-[450px] pointer-events-none flex flex-col gap-4 overflow-y-auto custom-scrollbar items-end" 
         :class="(mainScreenWidget && mainScreenWidget.type === 'youtube') ? 'z-[9999]' : 'z-[50]'" style="display: none;" x-cloak>
        <template x-for="(vid, index) in youtubeWidgets" :key="vid.id || index">
            <div :class="{
                    'fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[90vw] md:w-[80vw] h-[80vh] max-h-[800px] rounded-2xl z-[10000]': mainScreenWidget && mainScreenWidget.type === 'youtube' && mainScreenWidget.index === index,
                    'w-full shrink-0 relative': !(mainScreenWidget && mainScreenWidget.type === 'youtube' && mainScreenWidget.index === index),
                    '!opacity-20 !blur-md pointer-events-none': mainScreenWidget && mainScreenWidget.type === 'youtube' && mainScreenWidget.index !== index
                 }"
                 class="overflow-hidden group pointer-events-auto origin-right transition-all duration-700 ease-[cubic-bezier(0.23,1,0.32,1)]"
                 x-init="
                    if (typeof anime !== 'undefined') {
                        anime({
                            targets: $el,
                            translateX: [100, 0],
                            opacity: [0, 1],
                            scale: [0.8, 1],
                            delay: index * 100,
                            easing: 'easeOutElastic(1, .6)',
                            duration: 1500
                        });
                    } else {
                        $el.style.opacity = 1;
                    }
                 "
                 :style="(mainScreenWidget && mainScreenWidget.type === 'youtube' && mainScreenWidget.index === index) ? 'padding: 0; background: #000; box-shadow: 0 0 50px rgba(0,0,0,0.8); border: 1px solid rgba(255,255,255,0.1);' : '-webkit-mask-image: radial-gradient(ellipse at center, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 95%); mask-image: radial-gradient(ellipse at center, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 95%); background: radial-gradient(circle at center, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 80%); padding: 1.5rem;'">
                
                <div class="relative w-full aspect-video rounded-xl overflow-hidden shadow-[0_0_50px_rgba(0,0,0,0.8)] border border-white/5 transition-transform duration-700 group-hover:scale-105">
                    <iframe :src="vid.video" class="w-full h-full pointer-events-auto" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    
                    <!-- Hover Controls Overlay -->
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col justify-between p-4 backdrop-blur-sm pointer-events-none">
                        
                        <div class="flex justify-between items-start w-full">
                            <h3 class="font-bold text-white text-sm drop-shadow-md leading-tight line-clamp-2 pr-8" x-text="vid.title"></h3>
                            <!-- Focus & Close Buttons -->
                            <div class="flex gap-2 shrink-0">
                                <button @click="mainScreenWidget && mainScreenWidget.type === 'youtube' && mainScreenWidget.index === index ? clearMainScreenWidget() : setMainScreenWidget('youtube', index)" class="pointer-events-auto p-2 bg-black/50 hover:bg-emerald-500/80 hover:text-white rounded-full border border-white/20 backdrop-blur-md transition-all cursor-pointer text-gray-300" title="Hauptschirm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                </button>
                                <button @click="youtubeWidgets.splice(index, 1)" class="pointer-events-auto p-2 bg-black/50 hover:bg-red-500/80 hover:text-white rounded-full border border-white/20 backdrop-blur-md transition-all cursor-pointer text-gray-300" title="Schließen">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end items-center gap-2 pointer-events-auto">
                            <a :href="vid.url || '#'" target="_blank" class="px-4 py-1.5 bg-black/50 hover:bg-white/20 text-white rounded border border-white/30 transition-colors uppercase font-bold text-xs cursor-pointer tracking-wider">
                                Öffnen
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Persona Pool (2D Overlay) -->
    <div x-show="personaWidgets && personaWidgets.length" x-transition
         class="absolute left-1/2 -translate-x-1/2 top-24 bottom-20 w-[calc(100%-2rem)] sm:w-[800px] pointer-events-none flex flex-col gap-4 overflow-y-auto custom-scrollbar items-center" 
         :class="(mainScreenWidget && mainScreenWidget.type === 'persona') ? 'z-[9999]' : 'z-[50]'" style="display: none;" x-cloak>
        <template x-for="(persona, index) in personaWidgets" :key="index">
            <div :class="{
                    'fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[90vw] md:w-[80vw] h-[80vh] max-h-[800px] z-[10000]': mainScreenWidget && mainScreenWidget.type === 'persona' && mainScreenWidget.index === index,
                    'w-full shrink-0 relative': !(mainScreenWidget && mainScreenWidget.type === 'persona' && mainScreenWidget.index === index),
                    '!opacity-20 !blur-md pointer-events-none': mainScreenWidget && mainScreenWidget.type === 'persona' && mainScreenWidget.index !== index
                 }"
                 class="overflow-hidden group pointer-events-auto transition-all duration-700 ease-[cubic-bezier(0.23,1,0.32,1)] bg-gray-950/90 backdrop-blur-3xl border border-red-900/50 rounded-3xl shadow-[0_0_40px_rgba(220,38,38,0.15)] relative"
                 x-init="
                    if (typeof anime !== 'undefined') {
                        anime({
                            targets: $el,
                            translateY: [-100, 0],
                            opacity: [0, 1],
                            scale: [0.8, 1],
                            delay: index * 100,
                            easing: 'easeOutElastic(1, .6)',
                            duration: 1500
                        });
                    } else {
                        $el.style.opacity = 1;
                    }
                 "
                 :style="(mainScreenWidget && mainScreenWidget.type === 'persona' && mainScreenWidget.index === index) ? 'box-shadow: 0 0 50px rgba(220,38,38,0.5); border: 1px solid rgba(220,38,38,0.8);' : ''">
                 
                 <div class="absolute top-4 right-4 z-50 flex gap-2">
                    <button @click="mainScreenWidget && mainScreenWidget.type === 'persona' && mainScreenWidget.index === index ? clearMainScreenWidget() : setMainScreenWidget('persona', index)" class="p-2 bg-black/50 hover:bg-emerald-500/80 hover:text-white rounded-full border border-white/20 backdrop-blur-md transition-all cursor-pointer text-gray-300" title="Hauptschirm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                    </button>
                    <button @click="personaWidgets.splice(index, 1)" class="p-2 bg-black/50 hover:bg-red-500/80 hover:text-white rounded-full border border-white/20 backdrop-blur-md transition-all cursor-pointer text-gray-300" title="Schließen">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="p-6 h-full overflow-y-auto custom-scrollbar relative">
                    <div class="absolute inset-0 bg-[linear-gradient(rgba(220,38,38,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(220,38,38,0.05)_1px,transparent_1px)] bg-[size:20px_20px] opacity-20 pointer-events-none"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 border border-red-500/10 rounded-full animate-[spin_10s_linear_infinite] pointer-events-none"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[32rem] h-[32rem] border border-red-500/5 rounded-full animate-[spin_15s_linear_infinite_reverse] pointer-events-none"></div>
                    
                    <div class="relative z-10 flex flex-col gap-6">
                        
                        <!-- Top Secret Header -->
                        <div class="flex items-center justify-between border-b border-red-900/50 pb-4 pr-24">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 16 16"><path d="M5.338 1.59a61 61 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.7 10.7 0 0 0 2.287 2.233c.346.244.652.42.893.533q.18.085.293.118a1 1 0 0 0 .216.058l.004.001.002.001h.002l.002-.001.004-.001a1 1 0 0 0 .216-.058q.114-.033.293-.118c.24-.113.547-.29.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.8 11.8 0 0 1-2.517 2.453 7 7 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7 7 0 0 1-1.048-.625 11.8 11.8 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 63 63 0 0 1 5.072.56z"/><path d="M9.5 6.5a1.5 1.5 0 0 1-1 1.415l.385 1.99a.5.5 0 0 1-.491.595h-.788a.5.5 0 0 1-.49-.595l.384-1.99a1.5 1.5 0 1 1 2-1.415"/></svg>
                                <div>
                                    <div class="text-red-500 text-[10px] uppercase tracking-[0.3em] font-bold font-mono">Top Secret // Eyes Only</div>
                                    <div class="text-gray-400 text-xs font-mono" x-text="'Profil-Akte: ' + (persona.name ? btoa(persona.name).substring(0,8) : 'XXX') + '-2025'"></div>
                                </div>
                            </div>
                            <div class="px-3 py-1 bg-red-950/50 border border-red-800 text-red-400 text-[10px] font-mono tracking-widest uppercase rounded hidden sm:block">
                                Classified
                            </div>
                        </div>

                        <!-- Profile Overview -->
                        <div class="flex flex-col md:flex-row gap-6">
                            <!-- Image / Photo -->
                            <div class="w-full md:w-1/3 shrink-0 relative">
                                <div class="aspect-[3/4] w-full rounded-xl bg-gray-900 border-2 border-gray-800 relative overflow-hidden flex items-center justify-center">
                                    <template x-if="persona.image_url">
                                        <div>
                                            <img :src="persona.image_url" :alt="persona.name" class="w-full h-full object-cover filter contrast-125 saturate-50 sepia-[.2]">
                                            <div class="absolute inset-0 bg-red-500 mix-blend-overlay opacity-20 pointer-events-none"></div>
                                            <div class="absolute inset-0 bg-[repeating-linear-gradient(0deg,transparent,transparent_2px,rgba(0,0,0,0.3)_2px,rgba(0,0,0,0.3)_4px)] pointer-events-none"></div>
                                        </div>
                                    </template>
                                    <template x-if="!persona.image_url">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-700" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                                        </svg>
                                    </template>
                                    <div class="absolute top-2 right-2 flex gap-1">
                                        <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                                        <div class="text-[8px] font-mono text-red-500 tracking-widest">LIVE</div>
                                    </div>
                                </div>
                                <!-- Status Bar under image -->
                                <div class="mt-3 bg-gray-900 p-2 rounded border border-gray-800 flex items-center justify-between font-mono text-[10px]">
                                    <span class="text-gray-500">STATUS:</span>
                                    <span :class="(persona.status && persona.status.toLowerCase() == 'verstorben') ? 'text-gray-400' : 'text-emerald-400'" class="font-bold uppercase tracking-wider" x-text="persona.status || 'Unknown'"></span>
                                </div>
                            </div>

                            <!-- Bio Data -->
                            <div class="w-full md:w-2/3 flex flex-col gap-4">
                                <div>
                                    <h2 class="text-3xl font-bold text-white tracking-tight uppercase" x-text="persona.name || 'Unbekannt'"></h2>
                                    <template x-if="persona.aliases">
                                        <div class="text-red-400 font-mono text-xs tracking-widest uppercase mt-1" x-text="'AKA: ' + persona.aliases"></div>
                                    </template>
                                </div>

                                <div class="grid grid-cols-2 gap-3 mt-2">
                                    <div class="bg-gray-900/50 p-3 rounded-lg border border-gray-800/50">
                                        <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 font-mono">Herkunft</div>
                                        <div class="text-sm text-gray-300 font-medium" x-text="persona.origin || 'Classified'"></div>
                                    </div>
                                    <div class="bg-gray-900/50 p-3 rounded-lg border border-gray-800/50">
                                        <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 font-mono">Geburtsdatum</div>
                                        <div class="text-sm text-gray-300 font-medium" x-text="persona.birth_date || 'REDACTED'"></div>
                                    </div>
                                </div>

                                <div class="bg-black/40 p-4 rounded-xl border border-gray-800 mt-2 relative">
                                    <div class="absolute -top-2 left-4 px-2 bg-gray-950 text-[9px] uppercase tracking-widest font-bold text-red-500 font-mono">Zusammenfassung</div>
                                    <p class="text-sm text-gray-300 leading-relaxed font-sans" x-text="persona.summary || 'Keine Geheimdienst-Informationen verfügbar.'"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Section: Career & Associates -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4 border-t border-gray-800/50 pt-6">
                            <!-- Timeline -->
                            <div>
                                <div class="text-[10px] uppercase tracking-widest font-bold text-gray-500 font-mono mb-4 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="currentColor" viewBox="0 0 16 16"><path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483zm.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.993.123a8 8 0 0 1-.23 1.155l-.964-.267q.069-.247.12-.501m-.952 2.379q.276-.436.486-.908l.914.405q-.24.54-.555 1.038zm-.964 1.205q.183-.183.35-.378l.758.653a8 8 0 0 1-.401.432zM8 15a7 7 0 0 0 4.545-1.677l.745.666A8 8 0 0 1 8 16zm-4.545-1.677A7 7 0 0 0 8 15v1a8 8 0 0 1-5.29-2.011zm-1.778-2.343A7 7 0 0 0 3.455 15l-.666.745A8 8 0 0 1 1 10.98zm-1.282-3.8A7 7 0 0 0 1 10.98l-1 0a8 8 0 0 1 .45-4.148zm1.02-3.084A7 7 0 0 0 1 7.18l-1 0a8 8 0 0 1 1.72-4.59l.755.656zm2.253-1.666A7 7 0 0 0 3.655 2.1l-.666-.745A8 8 0 0 1 5.92 0zM8 1a7 7 0 0 0-2.08.318l-.297-.954A8 8 0 0 1 8 0zM8 3.5a.5.5 0 0 0-.5.5v4a.5.5 0 0 0 .146.354l3 3a.5.5 0 0 0 .708-.708L8.5 7.793V4a.5.5 0 0 0-.5-.5"/></svg>
                                    Career Timeline
                                </div>
                                <template x-if="persona.career_timeline && persona.career_timeline.length > 0">
                                    <div class="relative border-l border-gray-800 ml-2 space-y-4">
                                        <template x-for="ct in persona.career_timeline">
                                            <div class="relative pl-4">
                                                <div class="absolute -left-[5px] top-1.5 w-2 h-2 rounded-full bg-red-900 border border-red-500 shadow-[0_0_8px_rgba(220,38,38,0.8)]"></div>
                                                <div class="text-[10px] font-mono text-red-400" x-text="ct.year || ''"></div>
                                                <div class="text-xs text-gray-300" x-text="ct.event || ''"></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!persona.career_timeline || persona.career_timeline.length === 0">
                                    <div class="text-xs text-gray-600 italic font-mono">No timeline data available.</div>
                                </template>
                            </div>

                            <!-- Associates -->
                            <div>
                                <div class="text-[10px] uppercase tracking-widest font-bold text-gray-500 font-mono mb-4 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M6 3.5A1.5 1.5 0 0 1 7.5 2h1A1.5 1.5 0 0 1 10 3.5v1A1.5 1.5 0 0 1 8.5 6v1H14a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0v-1A.5.5 0 0 1 2 7h5.5V6A1.5 1.5 0 0 1 6 4.5zM8.5 5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5zM0 11.5A1.5 1.5 0 0 1 1.5 10h1A1.5 1.5 0 0 1 4 11.5v1A1.5 1.5 0 0 1 2.5 14h-1A1.5 1.5 0 0 1 0 12.5zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm6.5 0A1.5 1.5 0 0 1 9.5 10h1a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 10.5 14h-1A1.5 1.5 0 0 1 8 12.5zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm6.5 0a1.5 1.5 0 0 1 1.5-1.5h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1a1.5 1.5 0 0 1-1.5-1.5zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/></svg>
                                    Known Associates
                                </div>
                                <template x-if="persona.known_associates && persona.known_associates.length > 0">
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="assoc in persona.known_associates">
                                            <div class="px-3 py-1.5 bg-gray-900 border border-gray-700 rounded-lg text-xs text-gray-300 font-medium flex items-center gap-2 hover:border-red-500 transition-colors cursor-default">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-gray-500" fill="currentColor" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/></svg>
                                                <span x-text="assoc"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!persona.known_associates || persona.known_associates.length === 0">
                                    <div class="text-xs text-gray-600 italic font-mono">No known associates logged.</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- UI Overlay Navigation -->
    <div x-show="showFunkiView" class="absolute top-6 right-6 z-50 flex flex-col items-end gap-2" x-transition:enter="transition ease-out duration-1000 delay-500" x-transition:enter-start="opacity-0 translate-y-[-20px]" x-transition:enter-end="opacity-100 translate-y-0">
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
                                <span class="leading-relaxed" x-text="expandedLog ? stripSpeak(log.message) : stripSpeak(log.message).substring(0, 150) + (stripSpeak(log.message).length != stripSpeak(log.message).substring(0,150).length ? '...' : '')"></span>
                                <button x-show="log.message.length != log.message.substring(0,150).length" @click="expandedLog = !expandedLog" class="text-[8px] uppercase font-bold text-emerald-400 mt-1 hover:text-white self-start bg-emerald-900/30 px-1.5 py-0.5 rounded transition-colors">
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

    <!-- Bottom Right Controls (Audio & Close) -->
    <div x-show="showFunkiView" class="absolute right-6 z-50 flex flex-col items-end gap-3 pointer-events-auto" style="bottom: max(1.5rem, env(safe-area-inset-bottom));" x-transition:enter="transition ease-out duration-1000 delay-500" x-transition:enter-start="opacity-0 translate-y-[20px]" x-transition:enter-end="opacity-100 translate-y-0">
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
        </div>
    </template>
</div>
