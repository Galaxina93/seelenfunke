<div x-data="{ showLog: false }" class="fixed bottom-4 right-4 z-50 flex flex-col items-end">
    <!-- Matrix Style Log Popup -->
    <div x-show="showLog" x-collapse x-transition class="w-80 mt-2 p-3 bg-black/90 border border-emerald-900/50 rounded-lg backdrop-blur-md shadow-[0_0_20px_rgba(16,185,129,0.1)] flex flex-col gap-2 max-h-80 overflow-y-auto pointer-events-auto custom-scrollbar mb-4" style="display: none;">
        <div class="text-[8px] font-black uppercase tracking-widest text-emerald-500/50 border-b border-emerald-900/30 pb-2 mb-2 sticky top-0 bg-black/80 z-10 flex flex-col gap-2">
            <div class="flex justify-between items-center">
                <span>KI Aktionen (Live-Log)</span>
                <div class="flex items-center gap-2">
                    <button @click="$dispatch('open-funkira'); showLog = false" class="text-emerald-500 hover:text-emerald-400 flex items-center gap-1 bg-emerald-900/30 px-2 py-0.5 rounded border border-emerald-500/30 transition-colors" title="Zentrum Öffnen">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                            <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12z"/>
                            <circle cx="10" cy="10" r="3"/>
                        </svg>
                        <span>KI-3D-Zentrum</span>
                    </button>
                    <button @click="showLog = false" class="text-emerald-500/50 hover:text-emerald-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="flex flex-col gap-2 border-t border-emerald-900/20 pt-2 items-start" x-data="funkiView()">
                <label class="flex items-center gap-2 cursor-pointer group" title="Zuhören (Immer hören)">
                    <div class="relative w-6 h-3 transition-colors rounded-full" :class="continuousMode ? 'bg-emerald-500' : 'bg-gray-700'">
                        <input type="checkbox" x-model="continuousMode" @change="toggleMobileContinuous()" class="sr-only">
                        <div class="absolute w-3 h-3 transition-transform bg-white rounded-full shadow-md" :class="continuousMode ? 'translate-x-3' : 'translate-x-0'"></div>
                    </div>
                    <div class="flex items-center gap-1 text-gray-400 group-hover:text-emerald-400 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path d="M7 4a3 3 0 016 0v6a3 3 0 11-6 0V4z" /><path d="M5.5 9.643a.75.75 0 00-1.5 0V10c0 3.06 2.29 5.585 5.25 5.954V17.5h-1.5a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5h-1.5v-1.546A6.001 6.001 0 0016 10v-.357a.75.75 0 00-1.5 0V10a4.5 4.5 0 01-9 0v-.357z" /></svg>
                        <span>Immer hören</span>
                    </div>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer group" title="Aktivierungswort (Wake-Word) nutzen">
                    <div class="relative w-6 h-3 transition-colors rounded-full" :class="requireWakeWord ? 'bg-emerald-500' : 'bg-gray-700'">
                        <input type="checkbox" x-model="requireWakeWord" class="sr-only">
                        <div class="absolute w-3 h-3 transition-transform bg-white rounded-full shadow-md" :class="requireWakeWord ? 'translate-x-3' : 'translate-x-0'"></div>
                    </div>
                    <div class="flex items-center gap-1 text-gray-400 group-hover:text-emerald-400 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
                        <span>Aktivierungswort</span>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Livewire Stream / DB Logs -->
        <div wire:poll.5s class="text-[10px] font-mono p-2 space-y-2 flex flex-col-reverse">
            @forelse($logs as $log)
                <div x-data="{ expanded: false }" @click="expanded = !expanded" class="border border-emerald-900/40 bg-emerald-950/20 p-2 rounded relative overflow-hidden group cursor-pointer hover:bg-emerald-950/40 transition-colors">
                    <div class="absolute inset-y-0 left-0 w-1 bg-{{ $log->agent ? $log->agent->color : 'gray' }}-500 opacity-60"></div>
                    <div class="flex justify-between items-start pl-2 mb-1">
                        <span class="text-{{ $log->agent ? $log->agent->color : 'gray' }}-500 font-bold uppercase tracking-wider text-[9px] flex items-center gap-1">
                            @if($log->agent)
                                @if(str_contains($log->agent->icon, 'bi-'))
                                    <i class="{{ $log->agent->icon }}"></i>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.5 10a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd" /></svg>
                                @endif
                                {{ $log->agent->name }}
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.5 10a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd" /></svg> SYSTEM
                            @endif
                        </span>
                        <span class="text-emerald-700/60 text-[8px] flex items-center gap-1">
                            {{ $log->created_at->format('H:i:s') }}
                            <svg x-show="!expanded" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            <svg x-show="expanded" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M14.78 11.78a.75.75 0 0 1-1.06 0L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd"/></svg>
                        </span>
                    </div>
                    <div class="pl-2 text-emerald-400 font-bold text-[9px] uppercase tracking-wider">{{ $log->action_id }}</div>
                    <div class="pl-2 text-emerald-600/90 text-[10px] mt-1 leading-snug break-words">
                        <div x-show="!expanded">{{ \Illuminate\Support\Str::limit($log->message, 80) }}</div>
                        <div x-show="expanded" style="display: none;" class="whitespace-pre-wrap">{{ $log->message }}</div>
                    </div>
                </div>
            @empty
                <div class="text-center text-emerald-600/80">> SYSTEM::STREAM_INITIALIZED<span class="animate-pulse">_</span></div>
            @endforelse
        </div>
    </div>

    <!-- Toggle Button -->
    <button @mouseenter="showLog = true" @click="showLog = !showLog" class="bg-black border border-emerald-800/80 hover:border-emerald-500 hover:bg-emerald-900/20 text-emerald-500 rounded-full h-12 w-12 flex items-center justify-center shadow-[0_0_15px_rgba(16,185,129,0.2)] transition-all relative overflow-hidden group">
        <!-- Ping Animation Background Core -->
        <div class="absolute inset-0 rounded-full bg-emerald-500/30 animate-[ping_3s_cubic-bezier(0,0,0.2,1)_infinite]"></div>
        
        <div class="absolute inset-0 bg-emerald-500/10 blur-md group-hover:bg-emerald-500/20 transition-all"></div>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 relative z-10 transition-transform group-hover:scale-110">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09l2.846.813-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
        </svg>
    </button>
</div>

<div x-data="funkiView()"
     wire:ignore
     @open-funkira.window="openFunkiView()"
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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-emerald-400"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.5 10a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd" /></svg>
                <span class="text-[10px] font-mono font-bold text-gray-300 tracking-wider" x-text="tokenUsage"></span>
            </div>

            <!-- Audio Stop -->
            <button x-show="isOutputActive()" @click="stopSpeech()" class="px-3 py-2 bg-red-900/80 border border-red-700 rounded-lg text-[10px] font-black uppercase tracking-widest text-red-100 hover:text-white hover:border-red-400 hover:bg-red-800 transition-all shadow-[0_0_15px_rgba(239,68,68,0.5)] flex items-center gap-2 backdrop-blur-md" title="Funkira unterbrechen">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM6.75 6.75a.75.75 0 01.75-.75h5a.75.75 0 01.75.75v5a.75.75 0 01-.75.75h-5a.75.75 0 01-.75-.75v-5z" clip-rule="evenodd" /></svg> Stopp
            </button>
        </div>

        <!-- Mobile: Listening Mode Toggle (PTT vs Continuous) relocated here -->
        <label x-show="isMobile" class="flex items-center gap-2 px-3 py-1 bg-gray-900/80 border border-gray-700 rounded-lg shadow-[0_0_15px_rgba(16,185,129,0.2)] backdrop-blur-md cursor-pointer hover:border-emerald-500 transition-colors" title="Knopf drücken (PTT) vs Dauerhaft zuhören (Stellt Musik stumm)">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Immer hören</span>
            <div class="relative inline-block w-8 outline-none focus:outline-none">
                <input type="checkbox" x-model="continuousMode" @change="toggleMobileContinuous()" class="peer sr-only">
                <div class="block h-4 bg-gray-700 rounded-full peer-checked:bg-emerald-500 transition-all"></div>
                <div class="dot absolute left-1 top-1 w-2 h-2 bg-white rounded-full transition peer-checked:translate-x-4"></div>
            </div>
        </label>

        <!-- Action Debug Log -->
        <div x-data="{ showDebugLog: false }" class="flex flex-col items-end w-full">
            <button @click="showDebugLog = !showDebugLog" x-show="funkiLogs.length > 0" class="mt-2 text-[10px] text-emerald-500/50 hover:text-emerald-400 transition-colors uppercase tracking-widest bg-black/40 px-2 py-1 rounded backdrop-blur-sm border border-emerald-900/30 flex items-center gap-1">
                <svg x-show="!showDebugLog" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 001 5.5v9A1.5 1.5 0 002.5 16h15a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0017.5 4h-15zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm0 4a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                <svg x-show="showDebugLog" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                Log <span x-show="!showDebugLog" x-text="'('+funkiLogs.length+')'"></span>
            </button>
            <div x-show="showDebugLog" x-collapse x-transition class="w-80 mt-2 p-3 bg-black/60 border border-emerald-900/50 rounded-lg backdrop-blur-md shadow-[0_0_20px_rgba(16,185,129,0.1)] flex flex-col gap-2 max-h-80 overflow-y-auto pointer-events-auto custom-scrollbar" style="display: none;">
                <div class="text-[8px] font-black uppercase tracking-widest text-emerald-500/50 border-b border-emerald-900/30 pb-2 mb-1 sticky top-0 bg-black/80 z-10">KI Aktionen (Live-Log)</div>
                <template x-for="(log, i) in funkiLogs.slice().reverse()" :key="i">
                    <div class="text-[10px] font-mono leading-tight text-gray-300 break-words flex flex-col gap-1 border-b border-white/5 pb-2 last:border-0 last:pb-0">
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
                            <span class="leading-relaxed" x-text="log.message.substring(0, 150) + (log.message.length > 150 ? '...' : '')"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Bottom Left Controls (Desktop Wake Word) -->
    <div class="absolute bottom-6 left-6 z-50 flex flex-col items-start gap-4" x-transition:enter="transition ease-out duration-1000 delay-500" x-transition:enter-start="opacity-0 translate-y-[20px]" x-transition:enter-end="opacity-100 translate-y-0">

        <!-- Desktop: Wake Word Toggle -->
        <label x-show="!isMobile" class="flex items-center gap-2 px-3 py-1 bg-gray-900/80 border border-gray-700 rounded-lg shadow-[0_0_15px_rgba(16,185,129,0.2)] backdrop-blur-md cursor-pointer hover:border-emerald-500 transition-colors" title="Aktivierungswort (Funkira) nutzen oder auf jedes Wort reagieren">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Aktivierungswort</span>
            <div class="relative inline-block w-8 outline-none focus:outline-none">
                <input type="checkbox" x-model="requireWakeWord" class="peer sr-only">
                <div class="block h-4 bg-gray-700 rounded-full peer-checked:bg-emerald-500 transition-all"></div>
                <div class="dot absolute left-1 top-1 w-2 h-2 bg-white rounded-full transition peer-checked:translate-x-4"></div>
            </div>
        </label>
    </div>

    <!-- Bottom Right Controls (Audio & Close) -->
    <div class="absolute bottom-6 right-6 z-50 flex flex-col items-end gap-3" x-transition:enter="transition ease-out duration-1000 delay-500" x-transition:enter-start="opacity-0 translate-y-[20px]" x-transition:enter-end="opacity-100 translate-y-0">

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
        <button @click="closeFunkiView()" class="w-12 h-12 bg-gray-900/80 border border-gray-700 rounded-full flex items-center justify-center hover:text-white hover:border-emerald-500 hover:bg-black transition-all shadow-[0_0_15px_rgba(16,185,129,0.2)] backdrop-blur-md group" title="Zentrum verlassen">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-300 group-hover:text-red-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 9l-3 3m0 0l3 3m-3-3h7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>
    </div>

    <!-- Push to Talk Mobile Anchor -->
    <div x-show="isMobile && !continuousMode" class="absolute bottom-10 left-1/2 -translate-x-1/2 z-[100] flex flex-col items-center gap-2 pointer-events-auto" style="display: none;">
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
    <audio id="audio-funki-background" src="{{ asset('funkira/sounds/funkira_background.mp3') }}" preload="auto" playsinline webkit-playsinline loop></audio>
    <audio id="audio-funki-default-ambient" src="{{ asset('funkira/sounds/funkira_default_universum.mp3') }}" preload="auto" playsinline webkit-playsinline loop></audio>
    <audio id="audio-funki-pulse" src="{{ asset('funkira/sounds/funkira_pulse.mp3') }}" preload="auto" playsinline webkit-playsinline loop></audio>
    <audio id="audio-funki-init" src="{{ asset('funkira/sounds/funkira_Initialize.mp3') }}" preload="auto" playsinline webkit-playsinline></audio>
    <audio id="audio-funki-shutdown" src="{{ asset('funkira/sounds/funkira_shutdown.mp3') }}" preload="auto" playsinline webkit-playsinline></audio>
    <audio id="audio-funki-heartbeat" src="{{ asset('funkira/sounds/funkira_heartbeat.mp3') }}" preload="auto" playsinline webkit-playsinline loop></audio>
    <audio id="audio-funki-click" src="{{ asset('funkira/sounds/funkira_click.mp3') }}" preload="auto" playsinline webkit-playsinline></audio>
    <audio id="audio-funki-unclick" src="{{ asset('funkira/sounds/funkira_unclick.mp3') }}" preload="auto" playsinline webkit-playsinline></audio>

    <!-- Unified Floating UI Panel (Mapped to 3D Space) -->
    <div id="diagnostic-panel"></div>

    <!-- Generative UI Master Modal extracted to layout -->

    </template>
</div>
