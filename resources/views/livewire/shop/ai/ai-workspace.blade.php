<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="h-auto min-h-[calc(100dvh-4rem)] lg:h-[calc(100vh-6rem)] w-full font-mono text-[var(--theme-color)] flex flex-col pt-4 overflow-hidden relative"
     x-data="{
        showWorkspaceMobile: false,
        isChatFullScreen: false,
        init() {
            this.scrollToBottom();
            $wire.$watch('messages', () => { setTimeout(() => this.scrollToBottom(), 50) });
            $wire.$watch('typingAgents', () => { setTimeout(() => this.scrollToBottom(), 50) });
        },
        scrollToBottom() {
            let el = document.getElementById('chat-scroll-container');
            if(el) el.scrollTop = el.scrollHeight;
        }
     }"
     x-on:start-ai-inference.window="
        $event.detail.agentIds.forEach(id => {
            $wire.processAgent(id);
        });
     ">
    
    <!-- Neon Header -->
    <div x-show="!isChatFullScreen" class="text-center mb-4 lg:mb-6 shrink-0 relative z-10 w-full px-4 lg:px-6">
        <h1 class="text-3xl font-black tracking-widest uppercase shadow-emerald-500/20 drop-shadow-md text-[var(--theme-color)]">KI-Zentrale</h1>
        <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">Multi-Agenten Arbeitsfläche & Kommunikation</p>
        
        <button @click="showWorkspaceMobile = !showWorkspaceMobile" class="lg:hidden mt-3 text-xs font-bold uppercase tracking-widest bg-gray-900 border border-gray-800 text-[var(--theme-color)] px-4 py-2 rounded-xl">
            <span x-text="showWorkspaceMobile ? 'Arbeitsbereich ausblenden' : 'Arbeitsbereich anzeigen'"></span>
        </button>
    </div>

    <!-- Main Workspace Container -->
    <div class="flex-1 flex flex-col lg:flex-row gap-4 lg:gap-6 px-4 lg:px-6 pb-4 lg:pb-6 overflow-hidden relative"
         x-data="workspaceCanvas()">
         
        <!-- Left Sidebar: Tools & Agents -->
        <div x-show="!isChatFullScreen" class="w-full lg:w-72 bg-gray-950 border border-gray-800 rounded-2xl p-4 flex flex-col shrink-0 z-10 shadow-xl shadow-[var(--theme-color-10)]">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between mb-2 lg:mb-4 border-b border-gray-800 pb-2 gap-2">
                <h3 class="text-xs uppercase tracking-widest text-gray-400">Bereite Agenten <span class="hidden lg:inline">(Ziehbar)</span></h3>
                <button wire:click="syncAll" wire:loading.attr="disabled" class="text-[10px] uppercase font-bold tracking-widest px-2 py-1 rounded bg-gray-900 border border-gray-700 text-gray-400 hover:text-white hover:border-gray-500 transition-colors flex items-center gap-1 group">
                    <svg wire:loading.remove wire:target="syncAll" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 group-hover:text-[var(--theme-color)] transition-colors"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    <svg wire:loading wire:target="syncAll" class="animate-spin h-3 w-3 text-[var(--theme-color)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Sync Alle
                </button>
            </div>
            
            <div class="lg:flex-1 flex lg:flex-col overflow-x-auto lg:overflow-x-hidden lg:overflow-y-auto space-x-3 lg:space-x-0 lg:space-y-3 custom-scrollbar pb-2 lg:pb-0 pr-0 lg:pr-2">
                <h4 class="text-[9px] uppercase tracking-widest text-gray-500 font-bold mb-1 ml-1 shrink-0 hidden lg:block">Interne Agenten</h4>
                
                @foreach($agents as $agent)
                    @php $isActiveForChat = in_array($agent->id, $activeAgentIds); @endphp
                    <div class="agent-draggable w-[260px] lg:w-auto min-w-[260px] lg:min-w-0 bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-xl p-3 flex items-center gap-3 cursor-grab {{ $isActiveForChat ? '!border-[var(--theme-color-50)] shadow-[0_0_15px_rgba(var(--theme-color-rgb),0.1)]' : 'lg:hover:border-[var(--theme-color-30)]' }} transition-colors group shrink-0 lg:shrink relative"
                         draggable="true"
                         x-on:dragstart="startDrag($event, '{{ $agent->id }}')">
                        
                        <div class="relative shrink-0">
                            <div class="w-10 h-10 rounded-full bg-gray-900 border border-gray-700 overflow-hidden flex items-center justify-center relative">
                                @if($isActiveForChat)
                                    <span class="absolute inset-0 bg-[var(--theme-color)] opacity-20 animate-pulse-slow z-0"></span>
                                @endif
                                @if($agent->profile_picture)
                                    <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" class="w-full h-full object-cover relative z-10">
                                @else
                                    <span class="text-xs text-gray-500 group-hover:text-[var(--theme-color)] font-bold relative z-10">{{ substr($agent->name, 0, 2) }}</span>
                                @endif
                            </div>
                            
                            <!-- Ping Status Ring (Now outside the overflow-hidden layer) -->
                            @if(isset($pingResults[$agent->id]['llm']))
                                <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-gray-900 z-30 {{ $pingResults[$agent->id]['llm'] === 'Offline' || $pingResults[$agent->id]['llm'] === 'Fehler' ? 'bg-red-500' : ($pingResults[$agent->id]['llm'] === 'Inaktiv' ? 'bg-gray-500' : 'bg-emerald-500') }}" title="LLM: {{ $pingResults[$agent->id]['llm'] }} | TTS: {{ $pingResults[$agent->id]['tts'] ?? '-' }}"></div>
                            @endif
                        </div>
                        
                        <div class="min-w-0 flex-1 pointer-events-none">
                            <h4 class="text-sm font-bold text-gray-300 truncate group-hover:text-[var(--theme-color)]">{{ $agent->name }}</h4>
                            <p class="text-[10px] text-gray-600 truncate">{{ $agent->role->name ?? 'Keine Rolle' }}</p>
                        </div>
                        
                        <!-- Chat & Options Toggle Buttons -->
                        <div class="ml-auto shrink-0 z-20 flex items-center gap-1.5 opacity-90 lg:opacity-40 lg:group-hover:opacity-100 transition-opacity">
                            <button @click="$dispatch('open-role-manager', { roleId: '{{ $agent->ai_role_id }}' })" 
                                    title="Rolle verwalten" 
                                    class="w-6 h-6 rounded-md border border-gray-700 text-gray-400 hover:text-cyan-400 hover:border-cyan-500/50 hover:bg-cyan-900/20 bg-gray-900 flex items-center justify-center transition-all cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
                            </button>
                            <button @click="$dispatch('open-agent-manager', { agentId: '{{ $agent->id }}' })" 
                                    title="Agent konfigurieren" 
                                    class="w-6 h-6 rounded-md border border-gray-700 text-gray-400 hover:text-indigo-400 hover:border-indigo-500/50 hover:bg-indigo-900/20 bg-gray-900 flex items-center justify-center transition-all cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </button>
                            <button wire:click="toggleAgent('{{ $agent->id }}')" 
                                    title="{{ $isActiveForChat ? 'Aus dem Chat entfernen' : 'Dem Chat beitreten' }}" 
                                    class="w-6 h-6 rounded-md border flex items-center justify-center transition-all cursor-pointer {{ $isActiveForChat ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)] text-[var(--theme-color)]' : 'border-gray-700 text-gray-600 hover:text-white hover:border-gray-500 bg-gray-900' }}">
                                <x-heroicon-s-chat-bubble-left-right class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                @endforeach
                
                <!-- Ghost Button: + Agent erschaffen -->
                <button @click="$dispatch('open-agent-manager', { agentId: 'new' })" class="w-[260px] lg:w-auto min-w-[260px] lg:min-w-0 bg-transparent border-2 border-dashed border-gray-800 rounded-xl p-3 flex lg:flex-col items-center justify-center gap-3 hover:border-[var(--theme-color-50)] hover:bg-[var(--theme-color-10)] transition-all group shrink-0 lg:shrink h-auto lg:h-[70px] lg:py-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-600 group-hover:text-[var(--theme-color)] transition-colors"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 group-hover:text-[var(--theme-color)] transition-colors">+ Agent erschaffen</span>
                </button>

                <div class="hidden lg:block w-px h-1 bg-transparent"></div> <!-- Spacer for mobile flex -->

                <!-- Externe Agenten -->
                <h4 class="text-[9px] uppercase tracking-widest text-gray-500 font-bold lg:mt-6 mb-1 ml-1 shrink-0 lg:pt-4 lg:border-t border-gray-800/50 hidden lg:block">Externe Agenten</h4>
                
                <!-- Toni Card -->
                <div class="agent-draggable w-[260px] lg:w-auto min-w-[260px] lg:min-w-0 bg-gray-900/50 border border-gray-800 rounded-xl p-3 flex items-center gap-3 transition-colors group shrink-0 lg:shrink opacity-80 hover:opacity-100 relative">
                    <div class="relative shrink-0">
                        <div class="w-10 h-10 rounded-full bg-gray-950 border border-gray-700 overflow-hidden flex items-center justify-center relative">
                            <span class="text-xs text-gray-500 group-hover:text-rose-500 font-bold relative z-10">To</span>
                        </div>
                        @if(isset($pingResults['toni']['llm']))
                            <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-gray-900 z-30 {{ $pingResults['toni']['llm'] === 'Offline' || $pingResults['toni']['llm'] === 'Fehler' ? 'bg-red-500' : 'bg-emerald-500' }}" title="LLM: {{ $pingResults['toni']['llm'] }} | TTS: {{ $pingResults['toni']['tts'] ?? '-' }}"></div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 pointer-events-none">
                        <h4 class="text-sm font-bold text-gray-300 truncate group-hover:text-rose-400">Toni (Voice)</h4>
                        <p class="text-[10px] text-gray-600 truncate">TTS & STT Provider</p>
                    </div>
                    <div class="ml-auto shrink-0 z-20 flex items-center gap-1.5 opacity-90 lg:opacity-40 lg:group-hover:opacity-100 transition-opacity">
                        <button @click="$dispatch('open-external-agent', { agentId: 'toni' })" 
                                title="Toni konfigurieren" 
                                class="w-6 h-6 rounded-md border border-gray-700 text-gray-400 hover:text-rose-400 hover:border-rose-500/50 hover:bg-rose-900/20 bg-gray-900 flex items-center justify-center transition-all cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-800 flex flex-col gap-3">
                <button wire:click="$set('activeWorkspaceView', 'settings')" class="w-full py-2 bg-gray-900 border border-gray-800 text-gray-400 rounded-lg text-xs font-bold uppercase tracking-widest hover:text-white hover:bg-gray-800 hover:border-gray-700 transition-colors shadow-inner flex justify-center items-center gap-2">
                    <x-heroicon-o-cog-6-tooth class="w-4 h-4"/> Einstellungen
                </button>
                <button wire:click="$set('activeWorkspaceView', 'gen-ui')" class="w-full py-2 bg-gray-900 border border-gray-800 text-gray-400 rounded-lg text-xs font-bold uppercase tracking-widest hover:text-white hover:bg-gray-800 hover:border-gray-700 transition-colors shadow-inner flex justify-center items-center gap-2">
                    <x-heroicon-o-window class="w-4 h-4"/> Generative UI
                </button>
                <button wire:click="$set('activeWorkspaceView', 'knowledge-base')" class="w-full py-2 bg-gray-900 border border-gray-800 text-gray-400 rounded-lg text-xs font-bold uppercase tracking-widest hover:text-white hover:bg-gray-800 hover:border-gray-700 transition-colors shadow-inner flex justify-center items-center gap-2">
                    <x-heroicon-o-book-open class="w-4 h-4"/> Wissensdatenbank
                </button>
                <button wire:click="clearChat" wire:confirm="Sicher, dass du den Chat-Verlauf restlos wipen möchtest?" class="w-full py-2 bg-red-900/20 text-red-500 border border-red-900/50 rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-red-900/40 transition-colors">
                    Chat & Tokens Leeren
                </button>
            </div>
        </div>

        <!-- The Main Split Area (Right) -->
        <div class="flex-1 flex flex-col gap-4 overflow-hidden h-full">
            @if($activeWorkspaceView === 'settings')
                <div wire:key="settings-view-container" class="flex-1 overflow-y-auto w-full h-full relative rounded-2xl flex flex-col bg-gray-950/80 border border-gray-800 p-6 pb-28 lg:p-10 shadow-2xl backdrop-blur-md custom-scrollbar">
                    <div class="fixed bottom-4 left-4 right-4 lg:absolute lg:bottom-auto lg:left-auto lg:right-4 top-auto lg:top-4 z-50 mb-4 lg:mb-0 shrink-0 shadow-2xl lg:shadow-none">
                        <button wire:click="$set('activeWorkspaceView', 'workspace')" class="w-full lg:w-auto justify-center bg-[var(--theme-color-10)] lg:bg-gray-950 border border-[var(--theme-color-50)] lg:border-gray-800 text-[var(--theme-color)] lg:text-gray-400 px-4 py-3.5 lg:py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:text-white hover:border-gray-600 transition-all shadow-[inset_0_0_15px_var(--theme-color-10)] lg:shadow-xl flex items-center gap-2 backdrop-blur-3xl lg:backdrop-blur-xl shrink-0 z-50">
                            <x-heroicon-o-arrow-left class="w-4 h-4"/> Zurück zur Schaltzentrale
                        </button>
                    </div>
                    
                    <div class="max-w-3xl w-full mx-auto relative z-10 pt-8 lg:pt-0">
                        <div class="mb-10 text-center">
                            <h2 class="text-3xl font-black text-white tracking-widest uppercase inline-flex items-center gap-3">
                                <x-heroicon-o-cog-6-tooth class="w-8 h-8 text-[var(--theme-color)]" /> Einstellungen
                            </h2>
                            <p class="text-sm font-mono text-gray-400 tracking-wider mt-2">Zentrale Verwaltung für deinen Workspace</p>
                        </div>

                        <div class="space-y-6">
                            <!-- KI Ausführungspläne Panel -->
                            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-inner relative overflow-hidden group">
                                <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                                
                                <div class="flex items-start justify-between gap-6 relative z-10">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-200 mb-1 flex items-center gap-2">
                                            <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-[var(--theme-color)]" /> Generierte Ausführungspläne immer durchführen
                                        </h3>
                                        <p class="text-sm font-mono text-gray-400 leading-relaxed max-w-2xl">
                                            Wenn diese Option deaktiviert ist (Sicherheitsmodus), hält die Künstliche Intelligenz nach dem Erstellen des Schlachtplans ("Todo-Liste") an und fragt dich erst um Erlaubnis. Wenn diese Option aktiviert wird, führt die KI die geplanten Schritte sofort automatisiert der Reihe nach aus.
                                        </p>
                                    </div>
                                    <div class="shrink-0 mt-1">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" wire:model.live="autoApprovePlan" class="sr-only peer">
                                            <div class="w-14 h-7 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:bg-[var(--theme-color)] shadow-inner transition-colors duration-300"></div>
                                            <div class="absolute left-[2px] top-[2px] bg-white border border-gray-300 rounded-full h-6 w-6 transition-transform duration-300 peer-checked:translate-x-7 peer-checked:border-white shadow-sm pointer-events-none"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            </div>

                            <!-- KI HOSTING TARIFE -->
                            <div class="mt-12 mb-6 text-center">
                                <h2 class="text-2xl font-black text-white tracking-widest uppercase inline-flex items-center gap-3">
                                    <x-heroicon-o-server-stack class="w-7 h-7 text-[var(--theme-color)]" /> KI Hosting Tarife
                                </h2>
                                <p class="text-xs font-mono text-gray-400 tracking-wider mt-2">Zentrale LLM-Zugangssteuerung & Paketverwaltung</p>
                            </div>

                            @if(session()->has('message'))
                                <div class="bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] p-3 rounded-lg text-xs font-sans text-center mb-6 shadow-inner">
                                    {{ session('message') }}
                                </div>
                            @endif

                            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                                <!-- Tarife Liste -->
                                <div class="xl:col-span-2 space-y-6">
                                    @foreach($this->aiPlans as $plan)
                                        <div class="bg-gray-900 border {{ $plan->is_active ? 'border-[var(--theme-color-50)] shadow-[0_0_15px_rgba(var(--theme-color-rgb),0.2)]' : 'border-gray-800' }} rounded-2xl p-6 relative overflow-hidden transition-all group">
                                            @if($plan->is_active)
                                                <div class="absolute top-0 right-0 -mt-2 -mr-2 w-16 h-16 bg-[var(--theme-color-20)] rounded-full blur-xl pointer-events-none"></div>
                                                <div class="absolute top-4 right-4 text-[var(--theme-color)] flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest">
                                                    <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] animate-pulse"></span> Aktiv
                                                </div>
                                            @endif

                                            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-4 relative z-10">
                                                <div>
                                                    <h3 class="text-xl font-black text-white group-hover:text-[var(--theme-color)] transition-colors">{{ $plan->name }}</h3>
                                                    @if($plan->description)
                                                        <p class="text-xs text-[var(--theme-color-80)] mt-1 font-mono">{!! nl2br(e($plan->description)) !!}</p>
                                                    @endif
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <div class="text-2xl font-black text-white">{{ number_format($plan->price_monthly, 2, ',', '.') }} €<span class="text-xs text-gray-500 font-normal">/Monat</span></div>
                                                    <div class="text-xs text-gray-500 font-mono mt-1">
                                                        {{ $plan->token_limit ? number_format($plan->token_limit, 0, ',', '.') . ' Tokens' : 'Unlimitiert / Extern' }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Alpine Accordion für Features -->
                                            @if(is_array($plan->features) && count($plan->features) > 0)
                                                <div class="mt-6 border-t border-gray-800/60 pt-4" x-data>
                                                    <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Vorteile & Features</h4>
                                                    <div class="space-y-2">
                                                        @foreach($plan->features as $idx => $feature)
                                                            <details class="group/details bg-gray-950/50 rounded-xl border border-gray-800/50 overflow-hidden [&_summary::-webkit-details-marker]:hidden">
                                                                <summary class="flex items-center gap-3 p-3 cursor-pointer select-none hover:bg-gray-800/30 transition-colors list-none">
                                                                    <div class="shrink-0 text-[var(--theme-color)] mt-0.5">
                                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" transform="scale(0.8) translate(2,2)" d="M5 13l4 4L19 7" /></svg>
                                                                    </div>
                                                                    <span class="text-sm font-bold text-gray-300 {{ empty($feature['description']) ? 'pointer-events-none' : '' }}">{{ $feature['title'] }}</span>
                                                                    
                                                                    @if(!empty($feature['description']))
                                                                        <span class="ml-auto text-gray-500 group-open/details:rotate-180 transition-transform">
                                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                                        </span>
                                                                    @endif
                                                                </summary>
                                                                
                                                                @if(!empty($feature['description']))
                                                                    <div class="px-10 pb-4 text-xs font-sans text-gray-400 leading-relaxed bg-gray-950/30">
                                                                        {{ $feature['description'] }}
                                                                    </div>
                                                                @endif
                                                            </details>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="mt-6 flex flex-wrap gap-2 relative z-10 pt-4 border-t border-gray-800/60">
                                                @if(!$plan->is_active)
                                                    <button wire:click="setActivePlan({{ $plan->id }})" class="bg-[var(--theme-color)] text-gray-900 border border-[var(--theme-color-50)] text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg hover:bg-[var(--theme-color-80)] transition-colors shadow-lg">
                                                        Tarif aktivieren
                                                    </button>
                                                @endif
                                                <button wire:click="editPlan({{ $plan->id }})" class="bg-gray-800 text-gray-300 border border-gray-700 text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg hover:bg-gray-700 hover:text-white transition-colors">
                                                    Bearbeiten
                                                </button>
                                                @if(!$plan->is_active)
                                                    <button wire:click="deletePlan({{ $plan->id }})" wire:confirm="Paket wirklich löschen?" class="bg-gray-950 text-red-500 border border-gray-800 hover:border-red-500/50 hover:bg-red-950/30 text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-lg transition-colors ml-auto">
                                                        Löschen
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Editor / Creator Form -->
                                <div class="xl:col-span-1">
                                    <div class="bg-gray-900 border border-[var(--theme-color-20)] rounded-2xl p-6 sticky top-6 shadow-2xl shadow-black/50">
                                        <h3 class="text-lg font-black text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                                            @if($editingPlanId)
                                                <x-heroicon-o-pencil-square class="w-5 h-5 text-[var(--theme-color)]" /> Paket Editieren
                                            @else
                                                <x-heroicon-o-plus-circle class="w-5 h-5 text-[var(--theme-color)]" /> Neuer Tarif
                                            @endif
                                        </h3>

                                        <form wire:submit.prevent="saveNewPlan" class="space-y-4">
                                            <div>
                                                <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Paketname</label>
                                                <input type="text" wire:model="newPlanName" required class="w-full bg-gray-950 border border-gray-800 rounded-lg text-sm text-white px-4 py-2.5 focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] transition-colors">
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Preis (€/Mt)</label>
                                                    <input type="number" step="0.01" wire:model="newPlanPrice" required class="w-full bg-gray-950 border border-gray-800 rounded-lg text-sm text-white px-4 py-2.5 focus:border-[var(--theme-color)] transition-colors">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Tokens (0=Endlos)</label>
                                                    <input type="number" wire:model="newPlanTokens" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-sm text-white px-4 py-2.5 focus:border-[var(--theme-color)] transition-colors" placeholder="e.g. 5000000">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Beschreibung / Untertitel</label>
                                                <textarea wire:model="newPlanDescription" rows="2" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-sm text-white px-4 py-2 focus:border-[var(--theme-color)] transition-colors resize-none" placeholder="z.B. Nur in den ersten 3 Monaten..."></textarea>
                                            </div>

                                            <!-- Dynamische Features (Wiederholer) -->
                                            <div class="border-t border-gray-800 pt-4 mt-2">
                                                <div class="flex justify-between items-center mb-2">
                                                    <label class="block text-[10px] uppercase font-bold text-[var(--theme-color)]">Vorteil-Liste (Haken)</label>
                                                    <button type="button" wire:click="addFeatureRow" class="text-xs text-gray-400 hover:text-white transition-colors bg-gray-800 hover:bg-gray-700 px-2 py-0.5 rounded flex items-center gap-1">+ Zeile</button>
                                                </div>
                                                
                                                <div class="space-y-3 max-h-[300px] overflow-y-auto px-1 -mx-1 custom-scrollbar">
                                                    @foreach($newPlanFeatures as $index => $feat)
                                                        <div class="bg-gray-950 p-3 rounded-xl border border-gray-800 relative group/row">
                                                            <input type="text" wire:model="newPlanFeatures.{{ $index }}.title" placeholder="Titel / Bulletpoint" class="w-full bg-transparent border-none text-sm text-white focus:ring-0 px-0 py-1 mb-1 font-bold placeholder-gray-600">
                                                            <textarea wire:model="newPlanFeatures.{{ $index }}.description" rows="2" placeholder="Erklärungstext (optional)" class="w-full bg-gray-900 border border-gray-800 rounded text-xs text-gray-400 focus:border-[var(--theme-color)] transition-colors px-2 py-1.5 resize-none"></textarea>
                                                            
                                                            <button type="button" wire:click="removeFeatureRow({{ $index }})" class="absolute top-2 right-2 opacity-0 group-hover/row:opacity-100 text-red-500 hover:bg-red-500/20 p-1 rounded transition-all">
                                                                <x-heroicon-s-x-mark class="w-3 h-3" />
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="pt-4 flex gap-2">
                                                <button type="submit" class="flex-1 bg-[var(--theme-color)] text-gray-900 border border-[var(--theme-color-50)] text-xs font-bold uppercase tracking-widest px-4 py-3 rounded-xl hover:bg-[var(--theme-color-80)] transition-colors text-center shadow-[0_0_15px_rgba(var(--theme-color-rgb),0.3)]">
                                                    {{ $editingPlanId ? 'Speichern' : 'Hinzufügen' }}
                                                </button>
                                                @if($editingPlanId)
                                                    <button type="button" wire:click="cancelEdit" class="bg-gray-800 text-gray-300 border border-gray-700 text-xs font-bold uppercase tracking-widest px-4 py-3 rounded-xl hover:bg-gray-700 transition-colors shrink-0">
                                                        Abbrechen
                                                    </button>
                                                @endif
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($activeWorkspaceView === 'knowledge-base')
                <div wire:key="kb-view-container" class="flex-1 overflow-y-auto w-full h-full relative rounded-2xl flex flex-col">
                    <div class="fixed bottom-4 left-4 right-4 lg:absolute lg:bottom-auto lg:left-auto lg:right-4 top-auto lg:top-4 z-50 mb-4 lg:mb-0 shrink-0 shadow-2xl lg:shadow-none">
                        <button wire:click="$set('activeWorkspaceView', 'workspace')" class="w-full lg:w-auto justify-center bg-[var(--theme-color-10)] lg:bg-gray-950 border border-[var(--theme-color-50)] lg:border-gray-800 text-[var(--theme-color)] lg:text-gray-400 px-4 py-3.5 lg:py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:text-white hover:border-gray-600 transition-all shadow-[inset_0_0_15px_var(--theme-color-10)] lg:shadow-xl flex items-center gap-2 backdrop-blur-3xl lg:backdrop-blur-xl shrink-0 z-50">
                            <x-heroicon-o-arrow-left class="w-4 h-4"/> Zurück zur Schaltzentrale
                        </button>
                    </div>
                    <livewire:shop.ai.ai-knowledge-base />
                </div>
            @elseif($activeWorkspaceView === 'gen-ui')
                <div wire:key="gen-ui-view-container" class="flex-1 overflow-y-auto w-full h-full relative rounded-2xl flex flex-col">
                    <div class="fixed bottom-4 left-4 right-4 lg:absolute lg:bottom-auto lg:left-auto lg:right-4 top-auto lg:top-4 z-50 mb-4 lg:mb-0 shrink-0 shadow-2xl lg:shadow-none">
                        <button wire:click="$set('activeWorkspaceView', 'workspace')" class="w-full lg:w-auto justify-center bg-[var(--theme-color-10)] lg:bg-gray-950 border border-[var(--theme-color-50)] lg:border-gray-800 text-[var(--theme-color)] lg:text-gray-400 px-4 py-3.5 lg:py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:text-white hover:border-gray-600 transition-all shadow-[inset_0_0_15px_var(--theme-color-10)] lg:shadow-xl flex items-center gap-2 backdrop-blur-3xl lg:backdrop-blur-xl shrink-0 z-50">
                            <x-heroicon-o-arrow-left class="w-4 h-4"/> Zurück zur Schaltzentrale
                        </button>
                    </div>
                    <livewire:shop.ai.ai-visualization-registry />
                </div>
            @else
                <div wire:key="workspace-main-view" class="flex-1 flex flex-col gap-4 overflow-hidden h-full w-full" x-data="{ activeTab: 'chat' }">
                    <!-- Navigation Tabs -->
                    <div x-show="!isChatFullScreen" class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 rounded-xl px-6 flex justify-center gap-8 text-sm tracking-wider font-mono shrink-0 shadow-lg relative overflow-hidden">
                        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-[var(--theme-color-50)] to-transparent"></div>
                        <button @click="activeTab = 'chat'" class="py-3 px-2 relative transition-colors border-b-2" :class="activeTab === 'chat' ? 'text-[var(--theme-color)] border-[var(--theme-color)] shadow-[0_4px_15px_-3px_var(--theme-color-30)] font-bold' : 'text-gray-400 border-transparent hover:text-gray-200'"><x-heroicon-o-chat-bubble-left-right class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Workspace & Chat</button>
                        <button @click="activeTab = 'plans'" class="py-3 px-2 relative transition-colors border-b-2" :class="activeTab === 'plans' ? 'text-[var(--theme-color)] border-[var(--theme-color)] shadow-[0_4px_15px_-3px_var(--theme-color-30)] font-bold' : 'text-gray-400 border-transparent hover:text-gray-200'"><x-heroicon-o-document-text class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Pläne @if(count($this->artifacts) > 0)<span class="ml-1 bg-[var(--theme-color)] text-black text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-lg">{{ count($this->artifacts) }}</span>@endif</button>
                        <button @click="activeTab = 'files'" class="py-3 px-2 relative transition-colors border-b-2" :class="activeTab === 'files' ? 'text-[var(--theme-color)] border-[var(--theme-color)] shadow-[0_4px_15px_-3px_var(--theme-color-30)] font-bold' : 'text-gray-400 border-transparent hover:text-gray-200'"><x-heroicon-o-folder class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Dateien @if(count($this->globalFiles) > 0)<span class="ml-1 bg-gray-700 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-lg">{{ count($this->globalFiles) }}</span>@endif</button>
                    </div>

                    <!-- Chat/Workspace View -->
                    <div x-show="activeTab === 'chat'" 
                         x-ref="workspaceContainer"
                         class="flex-1 flex flex-col gap-2 overflow-hidden h-full w-full relative"
                         x-data="{
                             chatHeightPercent: @entangle('chatHeightPercent'),
                             isDragging: false,
                             startY: 0,
                             startChatHeight: 0,
                             containerH: 0,
                             ticking: false,
                             startDrag(e) {
                                 this.isDragging = true;
                                 this.startY = e.clientY !== undefined ? e.clientY : e.touches[0].clientY;
                                 this.startChatHeight = this.chatHeightPercent;
                                 this.containerH = this.$refs.workspaceContainer.clientHeight || 1;
                                 document.body.style.userSelect = 'none';
                                 
                                 if(!document.getElementById('drag-overlay')) {
                                     let overlay = document.createElement('div');
                                     overlay.id = 'drag-overlay';
                                     overlay.style.position = 'fixed';
                                     overlay.style.inset = '0';
                                     overlay.style.zIndex = '99999';
                                     overlay.style.cursor = 'row-resize';
                                     document.body.appendChild(overlay);
                                 }
                             },
                             onDrag(e) {
                                 if (!this.isDragging) return;
                                 
                                 const y = e.clientY !== undefined ? e.clientY : (e.touches && e.touches.length > 0 ? e.touches[0].clientY : null);
                                 if (y === null) return;
                                 
                                 if (this.ticking) return;
                                 this.ticking = true;
                                 
                                 requestAnimationFrame(() => {
                                     const deltaY = y - this.startY;
                                     const deltaPercent = (deltaY / this.containerH) * 100;
                                     let newPercent = this.startChatHeight - deltaPercent;
                                     newPercent = Math.max(15, Math.min(85, newPercent));
                                     this.chatHeightPercent = newPercent;
                                     this.ticking = false;
                                 });
                             },
                             stopDrag() {
                                 if (!this.isDragging) return;
                                 this.isDragging = false;
                                 this.ticking = false;
                                 document.body.style.userSelect = '';
                                 
                                 const overlay = document.getElementById('drag-overlay');
                                 if(overlay) overlay.remove();
                                 
                                 $wire.saveLayoutPercent(Math.round(this.chatHeightPercent));
                             }
                         }"
                         @mousemove.window="onDrag($event)"
                         @mouseup.window="stopDrag()"
                         @touchmove.window="onDrag($event)"
                         @touchend.window="stopDrag()">
                    <!-- TOP: Workspace Kanban Canvas -->
                    <div x-show="!isChatFullScreen" class="min-h-0 shrink-0 rounded-2xl border border-gray-800 relative overflow-hidden shadow-[inset_0_0_50px_rgba(0,0,0,1)] bg-[#050505]" :class="showWorkspaceMobile ? 'flex' : 'hidden lg:flex'" :style="'height: calc(' + (100 - chatHeightPercent) + '% - 0.75rem);'" style="background-image: linear-gradient(var(--theme-color-5) 1px, transparent 1px), linear-gradient(90deg, var(--theme-color-5) 1px, transparent 1px); background-size: 3rem 3rem;">
                    <div class="absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-emerald-500/5 to-transparent pointer-events-none"></div>
                
                <!-- Heartbeat Monitor (Ultra-Realistic HTML5 Canvas) -->
                <!-- Heartbeat Monitor (Ultra-Realistic HTML5 Canvas) -->
                <div wire:ignore class="absolute top-0 inset-x-0 h-16 pointer-events-none z-10 overflow-hidden" 
                     x-data="ekgMonitorComponent()" 
                     x-init="initCanvas()">
                    <canvas x-ref="canvas" class="w-full h-full opacity-60"></canvas>
                </div>

                <script>
                    function ekgMonitorComponent() {
                        return {
                            initCanvas() {
                                const canvas = this.$refs.canvas;
                                const ctx = canvas.getContext('2d');
                                
                                let width, height;
                                
                                const resize = () => {
                                    const rect = canvas.getBoundingClientRect();
                                    const dpr = window.devicePixelRatio || 1;
                                    canvas.width = rect.width * dpr;
                                    canvas.height = rect.height * dpr;
                                    width = canvas.width;
                                    height = canvas.height;
                                    ctx.scale(dpr, dpr);
                                    width = rect.width;
                                    height = rect.height;
                                };

                                window.addEventListener('resize', resize);
                                resize();

                                const points = [];
                                let currentX = 0;
                                const speed = 0.8; 
                                const MAX_AGE = 300; 
                                let frameCount = 0;
                                let isAgentActive = false;
                                let isWorkerRunning = true;

                                const animate = () => {
                                    requestAnimationFrame(animate);
                                    frameCount++;
                                    
                                    ctx.clearRect(0, 0, width, height);

                                    if (frameCount % 30 === 0) {
                                        const workerNode = document.getElementById('worker-status-node');
                                        isWorkerRunning = workerNode ? workerNode.getAttribute('data-running') === 'true' : true;
                                        const activeTask = document.querySelector('span.bg-cyan-500\\/10');
                                        const typingAgent = document.querySelector('.animate-bounce');
                                        
                                        // The heart only beats if background queue worker is strictly running AND doing work (or typing).
                                        isAgentActive = !!(activeTask || typingAgent) && isWorkerRunning;
                                        
                                        // Bonus: If the worker is DEAD, turn the line into a flatline RED immediately
                                        if (!isWorkerRunning) {
                                            isAgentActive = false;
                                        }
                                    }

                                    currentX += 1.5 * speed;
                                    if (currentX >= width) {
                                        currentX = 0;
                                        points.length = 0; 
                                    }

                                    let y = height / 2;
                                    let localX = currentX % 250; 
                                    
                                    if (isAgentActive && localX > 100 && localX < 150) {
                                        if (localX < 110) { 
                                            y -= Math.sin((localX - 100) * Math.PI / 10) * 3; 
                                        } else if (localX < 115) { 
                                            y += (localX - 110) * 1.5; 
                                        } else if (localX < 120) { 
                                            y += 7.5 - (localX - 115) * 8; 
                                        } else if (localX < 125) { 
                                            y += -32.5 + (localX - 120) * 9; 
                                        } else if (localX < 130) { 
                                            y += 12.5 - (localX - 125) * 2.5; 
                                        } else if (localX < 145) { 
                                            y -= Math.sin((localX - 130) * Math.PI / 15) * 4; 
                                        }
                                    } else {
                                        y += (Math.sin(currentX * 0.05) * 0.5);
                                    }

                                    y += (Math.random() - 0.5) * 0.3;

                                    points.push({ x: currentX, y: y, age: 0 });

                                    if (points.length > 1) {
                                        for (let i = 1; i < points.length; i++) {
                                            const p1 = points[i - 1];
                                            const p2 = points[i];
                                            
                                            p1.age += 1;
                                            if (p1.age > MAX_AGE) continue;
                                            
                                            const alpha = Math.max(0, 1 - (p1.age / MAX_AGE));
                                            
                                            ctx.beginPath();
                                            ctx.moveTo(p1.x, p1.y);
                                            ctx.lineTo(p2.x, p2.y);
                                            
                                            if (!isWorkerRunning) {
                                                ctx.strokeStyle = `rgba(239, 68, 68, ${alpha * 0.8})`; // Red Flatline
                                            } else if (isAgentActive) {
                                                ctx.strokeStyle = `rgba(16, 185, 129, ${alpha})`;
                                            } else {
                                                ctx.strokeStyle = `rgba(16, 185, 129, ${alpha * 0.4})`;
                                            }
                                            
                                            ctx.lineWidth = 1.5;
                                            ctx.lineJoin = 'round';
                                            ctx.lineCap = 'round';
                                            ctx.stroke();
                                        }
                                    }

                                    while (points.length > 0 && points[0].age > MAX_AGE) {
                                        points.shift();
                                    }

                                    ctx.beginPath();
                                    ctx.arc(currentX, y, 4, 0, Math.PI * 2);
                                    const gradient = ctx.createRadialGradient(currentX, y, 0, currentX, y, 8);
                                    
                                    if (!isWorkerRunning) {
                                        gradient.addColorStop(0, 'rgba(239, 68, 68, 0.8)'); 
                                        gradient.addColorStop(1, 'rgba(220, 38, 38, 0)');
                                    } else if (isAgentActive) {
                                        gradient.addColorStop(0, 'rgba(52, 211, 153, 0.9)'); 
                                        gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                                    } else {
                                        gradient.addColorStop(0, 'rgba(52, 211, 153, 0.3)'); 
                                        gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                                    }
                                    ctx.fillStyle = gradient;
                                    ctx.fill();
                                    
                                    ctx.beginPath();
                                    ctx.arc(currentX, y, 1.5, 0, Math.PI * 2);
                                    if (!isWorkerRunning) {
                                        ctx.fillStyle = '#fca5a5';
                                    } else {
                                        ctx.fillStyle = isAgentActive ? '#a7f3d0' : '#4ade80';
                                    }
                                    ctx.fill();
                                };
                                
                                setTimeout(animate, 100);
                            }
                        }
                    }
                </script>

                <!-- Background Processing Info Icon -->
                <div class="absolute top-3 right-4 z-20" x-data="{ showInfo: false }" @click.away="showInfo = false">
                    <button type="button" @click.prevent="showInfo = !showInfo" class="w-6 h-6 rounded-full bg-gray-900 border border-gray-700 flex items-center justify-center text-gray-400 hover:text-emerald-400 hover:border-emerald-500/50 cursor-pointer transition-all shadow-xl shadow-black relative z-30">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                    </button>
                    <!-- Tooltip -->
                    <div x-show="showInfo" x-transition style="display: none;" class="absolute top-full right-0 mt-2 w-72 bg-gray-950 border border-emerald-500/30 text-gray-300 text-[10px] p-4 rounded-xl shadow-2xl shadow-emerald-500/10 font-sans leading-relaxed pointer-events-auto z-40">
                        <strong class="{{ $this->isWorkerRunning ? 'text-emerald-400' : 'text-red-500' }} block mb-1 uppercase tracking-widest text-[9px] cursor-help pointer-events-auto">
                            {{ $this->isWorkerRunning ? 'Hintergrund-Herzschlag' : 'WARNUNG: WORKER OFFLINE' }}
                        </strong>
                        Sobald ein Agent einer Aufgabe zugewiesen ist, arbeitet er komplett autark im Hintergrundfenster.<br><br>
                        Der EKG Monitor zieht eine flache Linie, bis Hintergrundaktiviät (Tasks oder Chat) wahrgenommen wird.<br><br>
                        Das System schlägt nur aus, wenn der Worker reell läuft.
                        Damit dies lokal aktiv ist, muss der Queue Worker laufen:<br>
                        <code class="block bg-gray-900 text-emerald-300 p-1.5 mt-1.5 rounded border border-gray-800 font-mono text-[9px] break-all">php artisan queue:work</code>
                        <div class="mt-3 bg-gray-900 p-2 rounded text-gray-400 text-[8px] font-mono border border-gray-800 shadow-inner">
                            <span class="text-cyan-500 font-bold">Diagnose-Pings:</span><br>
                            {{ $this->workerDiagnostic }}
                        </div>
                    </div>
                </div>

                <div class="w-full h-full pt-20 pb-4 px-4 lg:pt-24 lg:pb-8 lg:px-8 overflow-y-auto lg:overflow-hidden lg:flex lg:flex-col" id="war-room-canvas" wire:poll.2s>
                    <!-- Hidden attribute exposing worker state to JS (Must be inside polled DOM) -->
                    <div id="worker-status-node" class="hidden" data-running="{{ $this->isWorkerRunning ? 'true' : 'false' }}"></div>

                    <!-- ============================== -->
                    <!-- MOBILE: KACHEL-ANSICHT         -->
                    <!-- ============================== -->
                    <div class="flex lg:hidden w-full flex-wrap gap-4 items-start content-start">
@foreach($tasks as $task)
                        <div class="task-node relative w-full lg:w-80 {{ $task->parent_task_id ? 'ml-0 lg:ml-8 border-l-4 border-l-[var(--theme-color-50)]' : '' }} bg-gray-950/90 backdrop-blur-md border {{ $task->status === 'completed' ? 'border-[var(--theme-color-50)] shadow-xl shadow-[var(--theme-color-10)]' : ($task->status === 'processing' ? 'border-cyan-500/50 shadow-[0_0_15px_rgba(6,182,212,0.1)] animate-pulse-slow' : 'border-gray-800') }} rounded-2xl p-5 flex flex-col transition-all shrink-0"
                             @if($task->status === 'pending')
                                 x-on:dragover.prevent="dragOver($event)"
                                 x-on:dragleave.prevent="dragLeave($event)"
                                 x-on:drop.prevent="dropTask($event, '{{ $task->id }}')"
                             @endif
                             id="task-{{ $task->id }}">
                            
                            @if($task->parent_task_id)
                                <div class="absolute -left-10 top-8 text-[var(--theme-color-30)] hidden lg:block">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                </div>
                            @endif
                            
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">#{{ substr($task->id, 0, 8) }}</span>
                                <div class="flex items-center gap-2">
                                    @if($task->status === 'completed')
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] uppercase tracking-widest">Fertig</span>
                                        <button wire:click="undoTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-cyan-400 p-1 rounded-md hover:bg-cyan-500/10 transition-colors"
                                                title="Aufgabe rückgängig machen (Umkehren)">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                                        </button>
                                        <button wire:click="restartTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-emerald-400 p-1 rounded-md hover:bg-emerald-500/10 transition-colors"
                                                title="Aufgabe komplett neu starten">
                                            <x-heroicon-o-arrow-path class="w-4 h-4" />
                                        </button>
                                        <button wire:click="archiveTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-orange-400 p-1 rounded-md hover:bg-orange-500/10 transition-colors"
                                                title="Archivieren & Ausblenden">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v2.25c0 1.08-.896 1.95-2 1.95H5.75c-1.104 0-2-.87-2-1.95v-2.25M12 21.75V11.25m-3 3.75 3-3.75 3 3.75M9 7.5h6" />
                                            </svg>
                                        </button>
                                        <button wire:click="deleteTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-red-500 p-1 rounded-md hover:bg-red-500/10 transition-colors"
                                                wire:confirm="Aufgabe unwiderruflich löschen?"
                                                title="Aufgabe unwiderruflich löschen">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    @elseif($task->status === 'processing')
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 uppercase tracking-widest flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse-slow"></span> Läuft
                                            </span>
                                            <button type="button" wire:click="cancelTask('{{ $task->id }}')" 
                                                    class="text-gray-500 hover:text-red-400 p-0.5 rounded-md hover:bg-red-500/10 transition-colors"
                                                    title="Task stoppen (Abbruch)">
                                                <x-heroicon-s-x-mark class="w-4 h-4" />
                                            </button>
                                        </div>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold {{ $task->status === 'failed' ? 'bg-red-500/10 text-red-400 border-red-500/30' : 'bg-gray-800 text-gray-400 border-gray-700' }} border uppercase tracking-widest">
                                            {{ $task->status === 'failed' ? 'Fehlgeschlagen' : 'Wartet' }}
                                        </span>
                                        @if($task->status === 'failed' || $task->status === 'completed')
                                            <button wire:click="restartTask('{{ $task->id }}')" 
                                                    class="text-gray-500 hover:text-emerald-400 p-1 rounded-md hover:bg-emerald-500/10 transition-colors"
                                                    title="Aufgabe komplett neu starten">
                                                <x-heroicon-o-arrow-path class="w-4 h-4" />
                                            </button>
                                            <button x-data @click="let a = prompt('Gibt es Ergänzungen? Aufgabe wird danach neu gestartet.'); if(a) { $wire.appendAndRestartTask('{{ $task->id }}', a) }"
                                                    class="text-gray-500 hover:text-emerald-400 p-1 rounded-md hover:bg-emerald-500/10 transition-colors"
                                                    title="Aufgabe ergänzen & neu starten">
                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                            </button>
                                        @endif
                                        <button wire:click="deleteTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-red-500 p-1 rounded-md hover:bg-red-500/10 transition-colors"
                                                wire:confirm="Aufgabe unwiderruflich löschen?"
                                                title="Aufgabe unwiderruflich löschen">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            </div>
                            
                            <p class="text-sm text-gray-300 leading-relaxed font-sans mb-4">{{ $task->prompt }}</p>

                            @if(!empty($task->ui_metadata['attachments']) || !empty($task->ui_metadata['local_uploads']))
                                <div class="flex flex-wrap gap-2 mb-4 -mt-2">
                                    @foreach($task->ui_metadata['attachments'] ?? [] as $att)
                                        <div class="px-2 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/30 rounded-md text-[10px] font-mono flex items-center gap-1.5 shadow-sm">
                                            <x-heroicon-o-document-text class="w-3 h-3" />
                                            {{ basename($att) }}
                                        </div>
                                    @endforeach
                                    @foreach($task->ui_metadata['local_uploads'] ?? [] as $upl)
                                        <div class="px-2 py-1 bg-purple-500/10 text-purple-400 border border-purple-500/30 rounded-md text-[10px] font-mono flex items-center gap-1.5 shadow-sm">
                                            <x-heroicon-o-paper-clip class="w-3 h-3" />
                                            {{ $upl['name'] ?? 'Upload' }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($task->status === 'processing' && $task->assigned_agent_id)
                                @php
                                    $agentState = \Illuminate\Support\Facades\Cache::get('ai_live_state_' . $task->assigned_agent_id);
                                    $colorMap = [
                                        'indigo' => ['bg' => 'rgba(99, 102, 241, 0.1)', 'border' => 'rgba(99, 102, 241, 0.3)', 'text' => '#818cf8'],
                                        'yellow' => ['bg' => 'rgba(234, 179, 8, 0.1)', 'border' => 'rgba(234, 179, 8, 0.3)', 'text' => '#facc15'],
                                        'orange' => ['bg' => 'rgba(249, 115, 22, 0.1)', 'border' => 'rgba(249, 115, 22, 0.3)', 'text' => '#fb923c'],
                                        'emerald' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'border' => 'rgba(16, 185, 129, 0.3)', 'text' => '#34d399'],
                                        'cyan' => ['bg' => 'rgba(6, 182, 212, 0.1)', 'border' => 'rgba(6, 182, 212, 0.3)', 'text' => '#22d3ee'],
                                        'green' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'border' => 'rgba(34, 197, 94, 0.3)', 'text' => '#4ade80'],
                                    ];
                                @endphp
                                @if($agentState)
                                    @php
                                        $pcs = $colorMap[$agentState['pulse_color']] ?? $colorMap['indigo'];
                                    @endphp
                                    <div class="mb-4 p-2.5 rounded-xl flex items-center gap-3 relative overflow-hidden transition-all shadow-inner" 
                                         style="background-color: {{ $pcs['bg'] }}; border: 1px solid {{ $pcs['border'] }};">
                                        
                                        <div class="w-7 h-7 rounded-full flex justify-center items-center shrink-0 border"
                                             style="border-color: {{ $pcs['border'] }}; color: {{ $pcs['text'] }}; background-color: rgba(0,0,0,0.2);">
                                            <x-dynamic-component :component="'heroicon-o-' . $agentState['active_node']" 
                                                                 class="w-4 h-4 animate-pulse-slow" />
                                        </div>

                                        <div class="flex-1 min-w-0 pr-1">
                                            <span class="block text-[8px] uppercase tracking-widest font-bold mb-0.5" style="color: {{ $pcs['text'] }}; opacity: 0.6;">
                                                Sub-Prozess
                                            </span>
                                            <span class="block text-xs font-mono truncate" style="color: {{ $pcs['text'] }};" title="{{ $agentState['action_text'] }}">
                                                {{ $agentState['action_text'] }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            @endif

                            <div class="mt-auto pt-4 border-t border-gray-800/80 lg:pointer-events-none">
                                @if($task->assigned_agent_id && $task->agent)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-gray-900 border border-gray-700 overflow-hidden shrink-0">
                                            @if($task->agent->profile_picture)
                                                <img src="{{ \Illuminate\Support\Str::startsWith($task->agent->profile_picture, 'shop/') ? asset($task->agent->profile_picture) : Storage::url($task->agent->profile_picture) }}" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <span class="text-xs text-gray-400 font-bold truncate">{{ $task->agent->name }}</span>
                                        @if($task->agent->provider === 'openai')
                                            <span class="ml-auto text-[9px] uppercase tracking-wider text-green-400 font-bold bg-green-500/10 border border-green-500/20 px-1.5 py-0.5 rounded shadow-inner" title="OpenAI GPT">GPT</span>
                                        @elseif($task->agent->provider === 'anthropic')
                                            <span class="ml-auto text-[9px] uppercase tracking-wider text-orange-400 font-bold bg-orange-500/10 border border-orange-500/20 px-1.5 py-0.5 rounded shadow-inner" title="Anthropic Claude">CLAUDE</span>
                                        @else
                                            <span class="ml-auto text-[9px] uppercase tracking-wider text-blue-400 font-bold bg-blue-500/10 border border-blue-500/20 px-1.5 py-0.5 rounded shadow-inner" title="Google Gemini">GEMINI</span>
                                        @endif
                                    </div>
                                @elseif($task->status === 'pending')
                                    <div class="hidden lg:flex h-8 border border-dashed border-gray-700 rounded-lg items-center justify-center text-xs text-gray-500 bg-gray-950/20 task-dropzone transition-colors drop-target-highlight pointer-events-none">
                                        Agent hier ablegen
                                    </div>
                                    <div class="lg:hidden">
                                         <select class="w-full h-9 bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-lg text-xs text-[var(--theme-color)] focus:ring-emerald-500 focus:border-[var(--theme-color)]"
                                                 wire:change="assignAgent('{{ $task->id }}', $event.target.value)">
                                             <option value="">Agent zuweisen...</option>
                                             @foreach($agents as $agent)
                                                 <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                             @endforeach
                                         </select>
                                    </div>
                                @endif
                            </div>

                            <!-- EXECUTION PLAN (Todo List) -->
                            @if(isset($task->ui_metadata['execution_plan']) && count($task->ui_metadata['execution_plan']) > 0)
                                <div class="mt-4 mb-2 space-y-2 relative z-10 w-full bg-gray-900/40 p-3 rounded-lg border border-gray-800/80">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-[10px] uppercase tracking-widest text-gray-500 font-bold flex items-center gap-2">
                                            <x-heroicon-o-clipboard-document-list class="w-3.5 h-3.5"/> KI Ausführungsplan
                                        </h4>
                                        @if($task->status === 'awaiting_approval')
                                            <div class="flex items-center" x-data="{ dropOpen: false }">
                                                <button wire:click="approvePlan('{{ $task->id }}')" class="flex items-center gap-1.5 px-3 py-1 bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-20)] border border-[var(--theme-color-50)] text-[var(--theme-color)] rounded-l-md text-[9px] font-bold uppercase tracking-widest transition-colors">
                                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> Erlauben
                                                </button>
                                                <div class="relative">
                                                    <button @click="dropOpen = !dropOpen" @click.away="dropOpen = false" class="flex items-center justify-center px-1.5 py-1 bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-20)] border-y border-r border-[var(--theme-color-50)] text-[var(--theme-color)] rounded-r-md transition-colors">
                                                        <x-heroicon-o-chevron-down class="w-3.5 h-3.5" />
                                                    </button>
                                                    <div x-show="dropOpen" style="display:none;" class="absolute right-0 top-full mt-1 w-40 bg-gray-900 border border-[var(--theme-color-50)] rounded-md shadow-xl overflow-hidden z-30">
                                                        <button wire:click="approvePlanAlways('{{ $task->id }}')" class="w-full text-left px-3 py-2 text-[9px] font-bold uppercase tracking-widest text-gray-300 hover:text-white hover:bg-[var(--theme-color-20)] transition-colors">
                                                            Immer erlauben
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    @foreach($task->ui_metadata['execution_plan'] as $step)
                                        <div class="flex items-start gap-2 text-xs font-sans">
                                            <div class="shrink-0 mt-0.5">
                                                @if($step['status'] === 'pending')
                                                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2" stroke-dasharray="2 2" stroke-linecap="round"></circle></svg>
                                                @elseif($step['status'] === 'processing')
                                                    <svg class="w-4 h-4 text-emerald-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                @elseif($step['status'] === 'completed')
                                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                @elseif($step['status'] === 'failed')
                                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                @endif
                                            </div>
                                            <div class="flex-1">
                                                <div class="{{ $step['status'] === 'completed' ? 'text-gray-400 line-through' : ($step['status'] === 'processing' ? 'text-[var(--theme-color)]' : 'text-gray-400') }}">
                                                    <span class="font-mono text-[9px] opacity-70">SCHRITT {{ $step['id'] }}:</span> {{ $step['description'] ?: '...' }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($task->status === 'completed' && $task->response_content)
                                <div class="mt-4 pt-4 border-t border-gray-800 relative z-20" x-data="{ expanded: false }">
                                    <div class="text-xs text-gray-300 bg-[var(--theme-color-10)] rounded p-3 font-sans break-words bg-opacity-30 relative"
                                         :class="expanded ? 'max-h-none' : 'max-h-[80px] overflow-hidden'">
                                        
                                        <!-- Render the full Markdown safely via wire:ignore so Alpine/Marked can pick it up if needed, or just plain text -->
                                        @if(strlen($task->response_content) > 80)
                                            <div wire:ignore class="ai-markdown-content" x-init="
                                                 if (window.renderAiMarkdown) { $el.innerHTML = window.renderAiMarkdown(@js($task->response_content)); } 
                                                 else { $el.innerText = @js($task->response_content); }">
                                            </div>
                                            
                                            <!-- Gradient overlay when collapsed -->
                                            <div x-show="!expanded" class="absolute bottom-0 left-0 right-0 h-10 bg-gradient-to-t from-[var(--theme-color-10)] to-transparent pointer-events-none"></div>
                                        @else
                                            <div class="font-sans whitespace-pre-wrap">{{ $task->response_content }}</div>
                                        @endif
                                    </div>
                                    
                                    @if(strlen($task->response_content) > 80)
                                        <button @click="expanded = !expanded" class="w-full text-center text-[10px] uppercase tracking-widest font-bold text-[var(--theme-color)] hover:text-white mt-1 p-1 flex justify-center items-center gap-1">
                                            <span x-text="expanded ? 'Einklappen' : 'Vollständige Antwort lesen'"></span>
                                            <svg x-show="!expanded" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                            <svg x-show="expanded" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                    </div>

                    <!-- ============================== -->
                    <!-- DESKTOP: TABELLEN-ANSICHT      -->
                    <!-- ============================== -->
                    <div class="hidden lg:flex lg:flex-col lg:flex-1 min-h-0 w-full text-white">
                        @if($tasks->isNotEmpty())
                            <div class="overflow-x-auto overflow-y-auto flex-1 rounded-xl border border-gray-800 bg-gray-950/50 backdrop-blur-md shadow-2xl custom-scrollbar">
                                <table class="w-full text-left border-collapse relative">
                                    <thead class="sticky top-0 z-20 bg-gray-900 shadow-sm border-b border-gray-800">
                                        <tr class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">
                                            <th class="px-6 py-4">ID & Aufgabe</th>
                                            <th class="px-6 py-4 w-48">Agent</th>
                                            <th class="px-6 py-4 w-56">Status</th>
                                            <th class="px-6 py-4 w-32 text-right">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-800/60">
                                        @foreach($tasks as $task)
                                        <tr class="transition-colors hover:bg-gray-900/40 {{ $task->parent_task_id ? 'bg-gray-900/20' : '' }}"
                                            @if($task->status === 'pending')
                                                x-on:dragover.prevent="dragOver($event)"
                                                x-on:dragleave.prevent="dragLeave($event)"
                                                x-on:drop.prevent="dropTask($event, '{{ $task->id }}')"
                                            @endif
                                            id="task-desktop-{{ $task->id }}">
                                            
                                            <!-- ID & Aufgabe (+ Anhänge) -->
                                            <td class="px-6 py-5 align-top w-1/2">
                                                <div class="flex items-center gap-2 mb-2">
                                                    @if($task->parent_task_id)
                                                        <svg class="w-4 h-4 text-[var(--theme-color-50)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                                    @endif
                                                    <span class="text-[10px] font-mono text-[var(--theme-color-50)] uppercase tracking-widest">#{{ substr($task->id, 0, 8) }}</span>
                                                    @if(!empty($task->ui_metadata['attachments']) || !empty($task->ui_metadata['local_uploads']))
                                                        <div class="flex flex-wrap gap-1.5 ml-2">
                                                        @foreach($task->ui_metadata['attachments'] ?? [] as $att)
                                                            <span class="px-1.5 py-0.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/30 rounded text-[9px] font-mono items-center inline-flex gap-1 shadow-sm"><x-heroicon-o-document-text class="w-3 h-3" /> {{ basename($att) }}</span>
                                                        @endforeach
                                                        @foreach($task->ui_metadata['local_uploads'] ?? [] as $upl)
                                                            <span class="px-1.5 py-0.5 bg-purple-500/10 text-purple-400 border border-purple-500/30 rounded text-[9px] font-mono items-center inline-flex gap-1 shadow-sm"><x-heroicon-o-paper-clip class="w-3 h-3" /> {{ $upl['name'] ?? 'Upload' }}</span>
                                                        @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                                <p class="text-sm text-gray-200 leading-relaxed font-sans mb-3">{{ $task->prompt }}</p>

                                                <!-- EXECUTION PLAN (Todo List) -->
                                                @if(isset($task->ui_metadata['execution_plan']) && count($task->ui_metadata['execution_plan']) > 0)
                                                    <div class="mb-3 space-y-2 relative z-10 w-full bg-gray-900/40 p-3 rounded-lg border border-gray-800/80">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <h4 class="text-[10px] uppercase tracking-widest text-gray-500 font-bold flex items-center gap-2">
                                                                <x-heroicon-o-clipboard-document-list class="w-3.5 h-3.5"/> KI Ausführungsplan
                                                            </h4>
                                                            @if($task->status === 'awaiting_approval')
                                                                <div class="flex items-center" x-data="{ dropOpen: false }">
                                                                    <button wire:click="approvePlan('{{ $task->id }}')" class="flex items-center gap-1.5 px-3 py-1 bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-20)] border border-[var(--theme-color-50)] text-[var(--theme-color)] rounded-l-md text-[9px] font-bold uppercase tracking-widest transition-colors">
                                                                        <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> Erlauben
                                                                    </button>
                                                                    <div class="relative">
                                                                        <button @click="dropOpen = !dropOpen" @click.away="dropOpen = false" class="flex items-center justify-center px-1.5 py-1 bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-20)] border-y border-r border-[var(--theme-color-50)] text-[var(--theme-color)] rounded-r-md transition-colors">
                                                                            <x-heroicon-o-chevron-down class="w-3.5 h-3.5" />
                                                                        </button>
                                                                        <div x-show="dropOpen" style="display:none;" class="absolute right-0 top-full mt-1 w-40 bg-gray-900 border border-[var(--theme-color-50)] rounded-md shadow-xl overflow-hidden z-30">
                                                                            <button wire:click="approvePlanAlways('{{ $task->id }}')" class="w-full text-left px-3 py-2 text-[9px] font-bold uppercase tracking-widest text-gray-300 hover:text-white hover:bg-[var(--theme-color-20)] transition-colors">
                                                                                Immer erlauben
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        @foreach($task->ui_metadata['execution_plan'] as $step)
                                                            <div class="flex items-start gap-2 text-xs font-sans">
                                                                <div class="shrink-0 mt-0.5">
                                                                    @if($step['status'] === 'pending')
                                                                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2" stroke-dasharray="2 2" stroke-linecap="round"></circle></svg>
                                                                    @elseif($step['status'] === 'processing')
                                                                        <svg class="w-4 h-4 text-emerald-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                                    @elseif($step['status'] === 'completed')
                                                                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                    @elseif($step['status'] === 'failed')
                                                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                    @endif
                                                                </div>
                                                                <div class="flex-1">
                                                                    <div class="{{ $step['status'] === 'completed' ? 'text-gray-400 line-through' : ($step['status'] === 'processing' ? 'text-[var(--theme-color)]' : 'text-gray-400') }}">
                                                                        <span class="font-mono text-[9px] opacity-70">SCHRITT {{ $step['id'] }}:</span> {{ $step['description'] ?: '...' }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                <!-- RESPONSE (Collapsible) -->
                                                @if($task->status === 'completed' && $task->response_content)
                                                    <div class="mt-3 relative z-10" x-data="{ expanded: false }">
                                                         <div class="text-[12px] text-gray-300 bg-[var(--theme-color-10)] rounded-lg p-3 font-sans break-words bg-opacity-40 relative border border-[var(--theme-color-20)]"
                                                              :class="expanded ? 'max-h-none pb-8' : 'max-h-[70px] overflow-hidden'">
                                                             @if(strlen($task->response_content) > 80)
                                                                 <div wire:ignore class="ai-markdown-content" x-init="if (window.renderAiMarkdown) { $el.innerHTML = window.renderAiMarkdown(@js($task->response_content)); } else { $el.innerText = @js($task->response_content); }"></div>
                                                                 <div x-show="!expanded" class="absolute bottom-0 left-0 right-0 h-10 bg-gradient-to-t from-[var(--theme-color-10)] to-transparent pointer-events-none rounded-b-lg"></div>
                                                             @else
                                                                 <div class="font-sans whitespace-pre-wrap">{{ $task->response_content }}</div>
                                                             @endif
                                                         </div>
                                                         @if(strlen($task->response_content) > 80)
                                                             <button @click="expanded = !expanded" class="w-full text-center text-[10px] uppercase tracking-widest font-bold text-[var(--theme-color)] hover:text-white mt-1 p-1.5 flex justify-center items-center gap-1 transition-colors">
                                                                 <span x-text="expanded ? 'Einklappen' : 'Vollständige Antwort lesen'"></span>
                                                                 <svg x-show="!expanded" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                                                 <svg x-show="expanded" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                                                             </button>
                                                         @endif
                                                    </div>
                                                @endif
                                            </td>

                                            <!-- Agent -->
                                            <td class="px-6 py-5 align-top">
                                                @if($task->assigned_agent_id && $task->agent)
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-10 h-10 rounded-full bg-gray-900 border border-gray-700 overflow-hidden shrink-0 shadow-md">
                                                            @if($task->agent->profile_picture)
                                                                <img src="{{ \Illuminate\Support\Str::startsWith($task->agent->profile_picture, 'shop/') ? asset($task->agent->profile_picture) : Storage::url($task->agent->profile_picture) }}" class="w-full h-full object-cover">
                                                            @endif
                                                        </div>
                                                        <div class="flex flex-col gap-0.5">
                                                             <span class="block text-xs text-gray-300 font-bold truncate">{{ $task->agent->name }}</span>
                                                             @if($task->agent->provider === 'openai')
                                                                 <span class="text-[9px] uppercase tracking-wider text-green-400 font-bold w-max">GPT</span>
                                                             @elseif($task->agent->provider === 'anthropic')
                                                                 <span class="text-[9px] uppercase tracking-wider text-orange-400 font-bold w-max">CLAUDE</span>
                                                             @else
                                                                 <span class="text-[9px] uppercase tracking-wider text-blue-400 font-bold w-max">GEMINI</span>
                                                             @endif
                                                        </div>
                                                    </div>
                                                @elseif($task->status === 'pending')
                                                    <div class="h-10 px-3 border border-dashed border-gray-700 rounded-lg items-center justify-center text-xs text-gray-500 bg-gray-950/20 task-dropzone transition-colors drop-target-highlight pointer-events-none flex">
                                                        Agent ablegen
                                                    </div>
                                                @endif
                                            </td>

                                            <!-- Status -->
                                            <td class="px-6 py-5 align-top">
                                                @if($task->status === 'completed')
                                                    <span class="px-2.5 py-1 rounded text-[10px] font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] uppercase tracking-widest shadow-sm">Fertig</span>
                                        @elseif($task->status === 'awaiting_approval')
                                            <span class="px-2.5 py-1 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/30 uppercase tracking-widest shadow-sm flex items-center w-max gap-1">
                                                <x-heroicon-o-hand-raised class="w-3 h-3" /> Warten auf Freigabe
                                            </span>
                                                @elseif($task->status === 'processing')
                                                    <div class="flex flex-col gap-2.5">
                                                        <span class="inline-flex w-fit px-2.5 py-1 rounded text-[10px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 uppercase tracking-widest items-center gap-1.5 shadow-[0_0_10px_rgba(6,182,212,0.1)]">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse-slow"></span> Läuft
                                                        </span>
                                                        
                                                        @if($task->assigned_agent_id)
                                                            @php
                                                                $agentState = \Illuminate\Support\Facades\Cache::get('ai_live_state_' . $task->assigned_agent_id);
                                                                $colorMap = [
                                                                      'indigo' => ['bg' => 'rgba(99, 102, 241, 0.1)', 'border' => 'rgba(99, 102, 241, 0.3)', 'text' => '#818cf8'],
                                                                      'yellow' => ['bg' => 'rgba(234, 179, 8, 0.1)', 'border' => 'rgba(234, 179, 8, 0.3)', 'text' => '#facc15'],
                                                                      'orange' => ['bg' => 'rgba(249, 115, 22, 0.1)', 'border' => 'rgba(249, 115, 22, 0.3)', 'text' => '#fb923c'],
                                                                      'emerald' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'border' => 'rgba(16, 185, 129, 0.3)', 'text' => '#34d399'],
                                                                      'cyan' => ['bg' => 'rgba(6, 182, 212, 0.1)', 'border' => 'rgba(6, 182, 212, 0.3)', 'text' => '#22d3ee'],
                                                                      'green' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'border' => 'rgba(34, 197, 94, 0.3)', 'text' => '#4ade80'],
                                                                ];
                                                            @endphp
                                                            @if($agentState)
                                                                @php $pcs = $colorMap[$agentState['pulse_color']] ?? $colorMap['indigo']; @endphp
                                                                <div class="p-2 rounded-lg flex items-center gap-2 border bg-opacity-50" style="background-color: {{ $pcs['bg'] }}; border-color: {{ $pcs['border'] }};">
                                                                     <x-dynamic-component :component="'heroicon-o-' . $agentState['active_node']" class="w-3.5 h-3.5 shrink-0 animate-pulse-slow" style="color: {{ $pcs['text'] }};" />
                                                                     <span class="text-[10px] font-mono truncate max-w-[160px]" style="color: {{ $pcs['text'] }};" title="{{ $agentState['action_text'] }}">{{ $agentState['action_text'] }}</span>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="px-2.5 py-1 inline-flex w-fit rounded text-[10px] font-bold border uppercase tracking-widest shadow-sm {{ $task->status === 'failed' ? 'bg-red-500/10 text-red-400 border-red-500/30' : 'bg-gray-800 text-gray-400 border-gray-700' }}">
                                                        {{ $task->status === 'failed' ? 'Fehlgeschlagen' : 'Wartet' }}
                                                    </span>
                                                @endif
                                            </td>

                                            <!-- Aktionen -->
                                            <td class="px-6 py-5 align-top text-right">
                                                <div class="flex justify-end items-center gap-2">
                                                    @if($task->status === 'processing')
                                                        <button type="button" wire:click="cancelTask('{{ $task->id }}')" class="text-gray-500 hover:text-red-400 p-2 rounded-lg hover:bg-red-500/10 transition-colors shadow-sm" title="Task stoppen">
                                                            <x-heroicon-s-x-mark class="w-4 h-4" />
                                                        </button>
                                                    @else
                                                        @if($task->status === 'completed' || $task->status === 'failed')
                                                            <button wire:click="restartTask('{{ $task->id }}')" class="text-gray-500 hover:text-emerald-400 p-2 rounded-lg hover:bg-emerald-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" title="Neu starten">
                                                                <x-heroicon-o-arrow-path class="w-4 h-4" />
                                                            </button>
                                                            <button x-data @click="let a = prompt('Was möchtest du als Ergänzung/Fehlermeldung hinzufügen?'); if(a) { $wire.appendAndRestartTask('{{ $task->id }}', a) }" class="text-gray-500 hover:text-emerald-400 p-2 rounded-lg hover:bg-emerald-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" title="Ergänzen & Neu starten">
                                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                            </button>
                                                        @endif
                                                        @if($task->status === 'completed')
                                                            <button wire:click="undoTask('{{ $task->id }}')" class="text-gray-500 hover:text-cyan-400 p-2 rounded-lg hover:bg-cyan-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" title="Aufgabe rückgängig machen (Umkehren)">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                                                            </button>
                                                            <button wire:click="archiveTask('{{ $task->id }}')" class="text-gray-500 hover:text-orange-400 p-2 rounded-lg hover:bg-orange-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" title="Archivieren & Ausblenden">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v2.25c0 1.08-.896 1.95-2 1.95H5.75c-1.104 0-2-.87-2-1.95v-2.25M12 21.75V11.25m-3 3.75 3-3.75 3 3.75M9 7.5h6" /></svg>
                                                            </button>
                                                        @endif
                                                        <button wire:click="deleteTask('{{ $task->id }}')" class="text-gray-500 hover:text-red-500 p-2 rounded-lg hover:bg-red-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" wire:confirm="Aufgabe unwiderruflich löschen?" title="Aufgabe löschen">
                                                            <x-heroicon-o-trash class="w-4 h-4" />
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if($tasks->isEmpty())
                        <div class="w-full h-[60vh] flex flex-col items-center justify-center text-gray-600 opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mb-4">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <p class="uppercase tracking-widest text-sm font-bold">Die Arbeitsfläche ist leer</p>
                            <p class="text-xs mt-2 font-sans">Schreibe mit der KI um Tasks generieren zu lassen.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Drag Handle -->
            <div x-show="!isChatFullScreen" class="h-2 cursor-row-resize rounded-full bg-gray-900/50 hover:bg-[var(--theme-color-30)] hover:shadow-[0_0_10px_var(--theme-color-30)] transition-all items-center justify-center mx-auto w-32 shrink-0 z-50 group"
                 :class="showWorkspaceMobile ? 'flex' : 'hidden lg:flex'"
                 @mousedown.prevent="startDrag($event)"
                 @touchstart.prevent="startDrag($event)">
                <div class="w-12 h-0.5 rounded-full bg-gray-600 group-hover:bg-[var(--theme-color)] transition-colors"></div>
            </div>

            <!-- BOTTOM: AI Chat Console -->
            <div class="shrink-0 rounded-2xl border border-gray-800 bg-gray-900/80 backdrop-blur-xl flex flex-col overflow-hidden relative shadow-[0_0_30px_rgba(0,0,0,0.5)] min-h-0 lg:flex-none" :style="isChatFullScreen ? '' : ((window.innerWidth < 1024 && !showWorkspaceMobile) ? 'height: 100%; min-height: 400px;' : 'height: calc(' + chatHeightPercent + '% - 0.75rem);')" :class="{'!fixed !inset-0 !m-0 !p-0 !z-[99999] !h-[100dvh] !w-[100vw] !rounded-none !border-none !bg-gray-950': isChatFullScreen, 'flex-1': (!isChatFullScreen && (window.innerWidth < 1024 && !showWorkspaceMobile))}">
                
                <!-- Fullscreen Toggle Button (Mobile) -->
                <button @click="isChatFullScreen = !isChatFullScreen" class="lg:hidden absolute top-4 right-4 z-50 text-gray-400 hover:text-white transition-colors bg-gray-900/80 hover:bg-gray-800 p-2 rounded-xl backdrop-blur-md border border-gray-700 shadow-xl" title="Chat maximieren">
                    <svg x-show="!isChatFullScreen" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                    </svg>
                    <svg style="display: none;" x-show="isChatFullScreen" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                    </svg>
                </button>
                
                <!-- Chat Log Area -->
                <div id="chat-scroll-container" class="flex-1 overflow-y-auto p-4 lg:p-6 space-y-6 custom-scrollbar scroll-smooth"
                     x-data="{
                         scrollToBottom() { this.$el.scrollTop = this.$el.scrollHeight; },
                         observeScroll() {
                             const observer = new MutationObserver(() => {
                                 if (this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight < 300) {
                                     this.scrollToBottom();
                                 }
                             });
                             observer.observe(this.$el, { childList: true, subtree: true, characterData: true });
                         }
                     }"
                     x-init="setTimeout(() => { scrollToBottom(); observeScroll(); }, 50);">
                     
                    @forelse($this->messages as $msg)
                        <div class="flex flex-col {{ $msg['role'] === 'user' ? 'items-end' : 'items-start' }} animate-fade-in-up" wire:key="msg-key-{{ md5(substr($msg['content'], 0, 50) . $loop->index) }}">
                            <div class="flex items-center gap-3 mb-1.5 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                                <div class="w-10 h-10 rounded shrink-0 flex justify-center items-center {{ $msg['color'] ? 'bg-'.$msg['color'].'/10 border-'.$msg['color'].'/40 text-'.$msg['color'] : 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] text-[var(--theme-color)]' }} shadow-[0_0_10px_currentColor] overflow-hidden">
                                    @if(isset($msg['profile_picture']) && $msg['profile_picture'])
                                        @php
                                            $pp = $msg['profile_picture'];
                                            $src = (str_starts_with($pp, 'images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/'))
                                                   ? asset($pp) : (\Illuminate\Support\Str::startsWith($pp, 'shop/') ? asset($pp) : Storage::url($pp));
                                        @endphp
                                        <img src="{{ $src }}" class="w-full h-full object-cover" alt="Profile">
                                    @else
                                        <x-dynamic-component :component="'heroicon-o-' . str_replace(['bi-stars', 'bi-'], ['sparkles', ''], ($msg['icon'] ?: 'cpu-chip'))" class="w-6 h-6" />
                                    @endif
                                </div>
                                <span class="text-xs font-bold {{ $msg['color'] ? 'text-'.$msg['color'] : 'text-[var(--theme-color)]' }} tracking-widest uppercase truncate max-w-[200px]">{{ $msg['name'] }}</span>
                            </div>
                            <div class="max-w-[90%] lg:max-w-[85%] text-sm lg:text-base leading-relaxed p-3 px-4 rounded-xl {{ $msg['role'] === 'user' ? 'bg-gray-950 border border-gray-700 text-gray-300 rounded-tr-none shadow-md' : 'bg-[var(--theme-color-10)] text-gray-200 rounded-tl-none border border-gray-800 shadow-xl shadow-[var(--theme-color-10)]' }}">
                                @if($msg['role'] === 'user')
                                    @if(!empty($msg['attachments']) || !empty($msg['local_uploads']))
                                        <div class="flex flex-wrap gap-2 mb-2">
                                            @if(!empty($msg['attachments']))
                                                @foreach($msg['attachments'] as $att)
                                                    <div class="flex items-center gap-1 bg-gray-900 border border-gray-700 text-emerald-400 text-[10px] uppercase font-bold tracking-widest px-2 py-0.5 rounded shadow-inner">
                                                        <x-heroicon-o-document-text class="w-3 h-3" />
                                                        <span>{{ basename($att) }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                            @if(!empty($msg['local_uploads']))
                                                @foreach($msg['local_uploads'] as $file)
                                                    <div class="flex items-center gap-1 bg-gray-900 border border-gray-700 text-cyan-400 text-[10px] uppercase font-bold tracking-widest px-2 py-0.5 rounded shadow-inner">
                                                        <x-heroicon-o-paper-clip class="w-3 h-3" />
                                                        <span>{{ $file['name'] ?? 'Upload' }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    @endif
                                    <div class="font-mono">{!! nl2br(e($msg['content'])) !!}</div>
                                @else
                                    <div wire:ignore class="ai-markdown-content w-full overflow-x-auto custom-scrollbar font-sans" x-data="{ content: @js($msg['content']) }" x-init="
                                        const render = () => { $el.innerHTML = window.renderAiMarkdown(content); };
                                        if (window.renderAiMarkdown) { render(); } else { setTimeout(render, 500); }
                                    "></div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-gray-400/40 font-mono tracking-widest gap-4">
                            <x-heroicon-o-chat-bubble-left-right class="w-12 h-12 opacity-50" />
                            <p>Keine aktiven Gespräche. Der Raum ist still.</p>
                        </div>
                    @endforelse

                    <!-- Typing Indicators -->
                    @foreach($typingAgents as $tId)
                        @php $tAgent = $agents->firstWhere('id', $tId); @endphp
                        @if($tAgent)
                        <div class="flex flex-col items-start animate-fade-in-up" wire:key="typing-{{ $tId }}">
                            <div class="flex items-center gap-3 mb-1.5 ">
                                <div class="w-10 h-10 rounded shrink-0 flex justify-center items-center bg-{{ $tAgent->color }}/10 border border-{{ $tAgent->color }}/40 shadow-[0_0_10px_currentColor] text-{{ $tAgent->color }} overflow-hidden">
                                     @if($tAgent->profile_picture)
                                         <img src="{{ \Illuminate\Support\Str::startsWith($tAgent->profile_picture, 'shop/') || \Illuminate\Support\Str::startsWith($tAgent->profile_picture, 'images/') || \Illuminate\Support\Str::startsWith($tAgent->profile_picture, '/') ? asset($tAgent->profile_picture) : Storage::url($tAgent->profile_picture) }}" class="w-full h-full object-cover">
                                     @else
                                         <x-dynamic-component :component="'heroicon-o-' . str_replace(['bi-stars', 'bi-'], ['sparkles', ''], ($tAgent->icon ?: 'cpu-chip'))" class="w-6 h-6" />
                                     @endif
                                </div>
                                <span class="text-xs font-bold text-{{ $tAgent->color }} tracking-widest uppercase">{{ $tAgent->name }}</span>
                            </div>
                            <div class="max-w-[85%] px-5 py-3 rounded-xl bg-[var(--theme-color-10)] text-gray-200 rounded-tl-none border border-gray-800 shadow-xl shadow-[var(--theme-color-10)]">
                                <div class="font-mono text-sm leading-relaxed flex items-center gap-3">
                                    <span class="flex gap-1.5 pt-1">
                                        <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                                        <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                                        <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce"></span>
                                    </span>
                                    <span class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-gray-500 uppercase tracking-widest animate-pulse-slow font-bold">Tippe...</span>
                                        <button type="button" wire:click="abortInference('{{ $tId }}')" title="Denkprozess sofort abbrechen" class="w-4 h-4 rounded bg-red-500/10 border border-red-500/30 text-red-500 hover:bg-red-500/30 hover:text-red-400 flex items-center justify-center transition-colors">
                                            <x-heroicon-s-x-mark class="w-3 h-3" />
                                        </button>
                                    </span>
                                </div>
                                <div wire:stream="thought_{{ $tId }}" class="mt-2 text-xs flex flex-col gap-1 font-mono text-[var(--theme-color)] opacity-70 empty:hidden empty:mt-0"></div>
                                <div wire:stream="answer_{{ $tId }}" class="mt-3 text-sm font-sans text-gray-200 leading-relaxed whitespace-pre-wrap empty:hidden empty:mt-0"></div>
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>

                <!-- Input Area / Form -->
                <div class="p-3 bg-gray-950 border-t border-gray-800 z-20 shrink-0 relative"
                     x-data="{ isDropping: false }"
                     x-on:dragover.prevent="isDropping = true"
                     x-on:dragleave.prevent="isDropping = false"
                     x-on:drop.prevent="isDropping = false; $wire.uploadMultiple('uploadedFiles', $event.dataTransfer.files)">
                     
                    <!-- Drop Overlay -->
                    <div x-show="isDropping" style="display: none;" class="absolute inset-0 bg-[var(--theme-color-10)] border-2 border-dashed border-[var(--theme-color)] z-50 flex items-center justify-center backdrop-blur-sm m-2 rounded-xl">
                        <div class="text-center">
                            <x-heroicon-o-cloud-arrow-up class="w-12 h-12 text-[var(--theme-color)] mx-auto mb-2 animate-bounce" />
                            <span class="text-xl font-bold text-[var(--theme-color)] drop-shadow-md">Dateien ablegen</span>
                        </div>
                    </div>

                    <div class="mx-auto relative" x-data="{
                        async insertMention(filePath) {
                            let inputEl = document.getElementById('workspaceChatInput');
                            let val = inputEl.value;
                            let pos = inputEl.selectionStart;
                            let lastAt = val.lastIndexOf('@', pos - 1);
                            if (lastAt !== -1) {
                                let newValue = val.substring(0, lastAt) + val.substring(pos);
                                $wire.set('input', newValue);
                                await $wire.addAttachment(filePath);
                                requestAnimationFrame(() => {
                                    inputEl.value = newValue;
                                    inputEl.focus();
                                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                                });
                            }
                        },
                        checkMention() {
                            let inputEl = document.getElementById('workspaceChatInput');
                            if (!inputEl) return;
                            let val = inputEl.value;
                            let pos = inputEl.selectionStart;
                            let lastAt = val.lastIndexOf('@', pos - 1);
                            if (lastAt !== -1 && (lastAt === 0 || val[lastAt-1].match(/\s/))) {
                                let query = val.substring(lastAt + 1, pos);
                                if (!query.includes(' ') && !query.includes('\n')) {
                                    $wire.searchFilesForMention(query);
                                    return;
                                }
                            }
                            if ($wire.mentionQuery !== '') $wire.searchFilesForMention('');
                        },
                        handleEnter(e) {
                            if (e.shiftKey) return;
                            e.preventDefault();
                            
                            // Check if a mention dropdown is currently visible
                            let fm = document.querySelector('#mention-dropdown .mention-item');
                            if (fm) {
                                fm.click();
                                return;
                            }
                            
                            // Check if actively typing a mention (to prevent accidental send before dropdown loads)
                            let val = e.target.value;
                            let pos = e.target.selectionStart;
                            let lastAt = val.lastIndexOf('@', pos - 1);
                            if (lastAt !== -1 && (lastAt === 0 || val[lastAt-1].match(/\s/))) {
                                let query = val.substring(lastAt + 1, pos);
                                if (!query.includes(' ') && !query.includes('\n')) {
                                    // User is typing a mention but dropdown hasn't loaded yet. Do nothing!
                                    return;
                                }
                            }
                            
                            // Send message
                            $wire.sendMessage();
                        }
                    }">

                        <!-- Attachment Badges -->
                        @if(!empty($attachments) || !empty($uploadedFiles))
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach($attachments as $idx => $att)
                                <div wire:key="att-{{ md5($att . $idx) }}" class="flex items-center gap-1 bg-gray-900 border border-gray-700 text-gray-300 text-xs px-2 py-1 rounded">
                                    <x-heroicon-o-document-text class="w-3 h-3" />
                                    <span>{{ basename($att) }}</span>
                                    <button type="button" wire:click="removeAttachment('{{ $idx }}')" class="hover:text-red-400 ml-1"><x-heroicon-s-x-mark class="w-3.5 h-3.5" /></button>
                                </div>
                            @endforeach
                            @foreach($uploadedFiles as $idx => $file)
                                <div wire:key="up-{{ $idx }}" class="flex items-center gap-1 bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] text-xs px-2 py-1 rounded">
                                    <x-heroicon-o-paper-clip class="w-3 h-3" />
                                    <span>{{ is_object($file) ? $file->getClientOriginalName() : 'Uploading...' }}</span>
                                    <button type="button" wire:click="$removeUpload('uploadedFiles', '{{ is_object($file) ? $file->getFilename() : $idx }}')" class="hover:text-red-400 ml-1"><x-heroicon-s-x-mark class="w-3.5 h-3.5" /></button>
                                </div>
                            @endforeach
                        </div>
                        @endif

                        <!-- Mention Dropdown -->
                        @if(!empty($mentionResults))
                        <div id="mention-dropdown" class="absolute bottom-[calc(100%+0.5rem)] left-0 w-80 max-h-60 overflow-y-auto custom-scrollbar bg-gray-900 border border-[var(--theme-color-40)] rounded-xl shadow-2xl z-50">
                            <div class="text-[10px] text-gray-500 uppercase px-3 py-1 bg-gray-950 border-b border-gray-800">Dateien einfügen</div>
                            @foreach($mentionResults as $result)
                                <button type="button" @click="insertMention('{{ addslashes($result) }}')" class="mention-item w-full text-left px-3 py-2 hover:bg-[var(--theme-color-20)] border-b border-gray-800/50">
                                    <div class="flex items-center gap-2 text-sm text-gray-300 font-mono"><x-heroicon-o-document-text class="w-4 h-4 shrink-0" /><span class="truncate">{{ basename($result) }}</span></div>
                                    <div class="text-[10px] text-gray-500 truncate ml-6">{{ dirname($result) }}</div>
                                </button>
                            @endforeach
                        </div>
                        @endif

                        <form wire:submit.prevent="sendMessage" class="relative w-full">
                            <div class="absolute left-2 top-1/2 -translate-y-1/2 flex items-center z-10">
                                <label class="cursor-pointer text-gray-500 hover:text-[var(--theme-color)] p-1">
                                    <x-heroicon-o-paper-clip class="w-5 h-5" />
                                    <input type="file" wire:model="uploadedFiles" multiple class="hidden" />
                                </label>
                            </div>
                            <textarea id="workspaceChatInput" wire:model="input"
                                   @keydown.enter="handleEnter($event)"
                                   @keyup="checkMention" @click="checkMention"
                                   rows="1"
                                   style="padding-left: 90px;"
                                   class="w-full bg-gray-900 border border-gray-800 rounded-lg pr-12 py-3 text-[var(--theme-color)] focus:border-[var(--theme-color)] focus:ring-[var(--theme-color-30)] text-sm shadow-inner font-sans outline-none resize-none custom-scrollbar" 
                                   placeholder="Nachricht eingeben..." autofocus></textarea>
                                   
                            <button type="button" wire:click="createTaskFromChat" title="Als neue Aufgabe auf dem Board ablegen" class="absolute left-10 top-1/2 -translate-y-1/2 h-8 w-10 z-10 bg-emerald-500/10 border border-emerald-500/30 rounded-md hover:bg-emerald-500/20 text-emerald-500 flex justify-center items-center transition-all cursor-pointer">
                                <x-heroicon-o-queue-list class="w-5 h-5" />
                            </button>
                                   
                            <button type="submit" title="An die KI senden" class="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 z-10 bg-[var(--theme-color-20)] border border-[var(--theme-color-50)] rounded-md hover:bg-[var(--theme-color-40)] text-[var(--theme-color)] flex justify-center items-center transition-all cursor-pointer">
                                <x-heroicon-s-paper-airplane class="w-4 h-4 hover:translate-x-0.5" />
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            </div> <!-- /End Chat View Wrapper -->
        <!-- PLANS TAB CONTENT -->
        <div x-show="activeTab === 'plans'" x-cloak class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-gray-900/50">
            @if(count($this->artifacts) > 0)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 h-full">
                    <!-- Sidebar: List of Plans -->
                    <div class="md:col-span-1 border-r border-gray-800 pr-4 space-y-2">
                        @foreach($this->artifacts as $idx => $art)
                            <button type="button" 
                                    wire:key="artifact-{{ md5($art['name'] ?? $idx) }}"
                                    @click="$dispatch('open-artifact', { id: {{ $idx }} })"
                                    class="w-full text-left p-3 rounded-lg border transition-all hover:border-[var(--theme-color)] {{ $loop->first ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)]' : 'bg-gray-950 border-gray-800' }}">
                                <div class="font-bold text-[var(--theme-color)] text-sm mb-1 truncate"><x-heroicon-o-document-check class="w-4 h-4 inline-block -mt-0.5" /> {{ $art['name'] }}</div>
                                <div class="text-[10px] text-gray-500 font-mono">{{ \Carbon\Carbon::createFromTimestamp($art['last_modified'])->diffForHumans() }}</div>
                            </button>
                        @endforeach
                    </div>

                    <!-- Main View: Artifact Viewer -->
                    <div class="md:col-span-3 h-full flex flex-col pt-2" 
                         x-data="{ 
                            currentArtifactId: 0,
                            artifacts: @js($this->artifacts),
                            get current() { return this.artifacts[this.currentArtifactId] || null; },
                            viewMode: 'markdown' // 'markdown' or 'code'
                         }"
                         @open-artifact.window="currentArtifactId = $event.detail.id; viewMode = 'markdown'">
                        
                        <template x-if="current">
                            <div class="flex flex-col h-full bg-gray-950 rounded-xl border border-gray-800 shadow-xl overflow-hidden">
                                <!-- Viewer Header -->
                                <div class="bg-[var(--theme-color-10)] border-b border-gray-800 px-4 py-3 flex justify-between items-center">
                                    <div class="font-mono text-[var(--theme-color)] font-bold text-sm tracking-widest uppercase">
                                        <x-heroicon-o-document-text class="w-5 h-5 inline-block mr-2" />
                                        <span x-text="current.filename"></span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button @click="viewMode = 'markdown'" :class="viewMode === 'markdown' ? 'bg-[var(--theme-color)] text-black' : 'bg-gray-800 text-gray-400 hover:text-white'" class="px-3 py-1 text-xs font-bold rounded shadow-sm">Preview</button>
                                        <button @click="viewMode = 'code'"     :class="viewMode === 'code' ? 'bg-[var(--theme-color)] text-black' : 'bg-gray-800 text-gray-400 hover:text-white'" class="px-3 py-1 text-xs font-bold rounded shadow-sm">RAW Editor</button>
                                    </div>
                                </div>
                                <!-- Viewer Body -->
                                <div class="flex-1 overflow-y-auto custom-scrollbar p-6 bg-gray-900 relative">
                                    <!-- Markdown Preview -->
                                    <div x-show="viewMode === 'markdown'" 
                                         class="ai-markdown-content w-full"
                                         x-html="window.renderAiMarkdown ? window.renderAiMarkdown(current.content) : current.content">
                                    </div>

                                    <!-- RAW Code Block -->
                                    <div x-show="viewMode === 'code'" style="display: none;" class="w-full h-full">
                                        <textarea class="w-full h-full bg-gray-950 text-emerald-400 font-mono text-sm p-4 border border-gray-800 rounded outline-none custom-scrollbar" readonly x-html="current.content"></textarea>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            @else
                <div class="h-full flex flex-col items-center justify-center text-gray-500 font-mono space-y-4">
                    <x-heroicon-o-document-magnifying-glass class="w-20 h-20 opacity-20" />
                    <p>Noch keine Pläne / Artefakte in diesem Projektordner generiert.</p>
                </div>
            @endif
        </div>

        <!-- FILES TAB CONTENT -->
        <div wire:key="tab-files" x-show="activeTab === 'files'" x-cloak x-data="{ zoomImage: null }" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-gray-900/50 relative">
            @php
                $computedFiles = $this->globalFiles;
            @endphp
            
            <!-- LIGHTBOX OVERLAY -->
            <div x-show="zoomImage" x-cloak class="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4 backdrop-blur-sm shadow-2xl">
                <div @click.outside="zoomImage = null" class="relative max-w-5xl w-full max-h-full flex justify-center shadow-[0_0_50px_black] rounded-xl">
                    <button @click="zoomImage = null" class="absolute -top-12 right-0 text-white hover:text-red-500 transition-colors drop-shadow-md">
                        <x-heroicon-o-x-mark class="w-10 h-10"/>
                    </button>
                    <img :src="zoomImage" class="max-w-full max-h-[85vh] object-contain rounded-lg border border-gray-800 shadow-[0_0_30px_rgba(255,255,255,0.05)]">
                </div>
            </div>

            @if(count($computedFiles) > 0)
                <div wire:key="files-grid" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
                    @foreach($computedFiles as $file)
                        @php
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (str_ends_with(strtolower($file['name']), '.blade.php')) $ext = 'blade';
                            
                            $props = match($ext) {
                                'php' => ['icon' => 'heroicon-o-code-bracket-square', 'class' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/30'],
                                'blade' => ['icon' => 'heroicon-o-rectangle-group', 'class' => 'text-orange-400 border-orange-900/50 bg-orange-950/30'],
                                'js', 'ts', 'vue', 'json' => ['icon' => 'heroicon-o-command-line', 'class' => 'text-yellow-400 border-yellow-900/50 bg-yellow-950/30'],
                                'css', 'scss', 'html' => ['icon' => 'heroicon-o-globe-alt', 'class' => 'text-sky-400 border-sky-900/50 bg-sky-950/30'],
                                'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => ['icon' => 'heroicon-o-photo', 'class' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/30'],
                                'pdf', 'csv', 'xlsx' => ['icon' => 'heroicon-o-table-cells', 'class' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/30'],
                                default => ['icon' => 'heroicon-o-document-text', 'class' => 'text-gray-400 border-gray-700 bg-gray-900']
                            };
                        @endphp
                        <div wire:key="global-file-{{ md5($file['path'] ?? $file['name']) }}" class="border rounded-xl p-4 flex flex-col items-center justify-center text-center gap-3 transition-all hover:scale-105 hover:bg-opacity-80 shadow-md {{ $props['class'] }} relative overflow-hidden group">
                            <!-- Type Badge -->
                            <div class="absolute top-2 right-2 text-[8px] uppercase font-bold px-1.5 py-0.5 rounded opacity-50 bg-black/40 {{ $file['type'] === 'project_file' ? 'text-cyan-400' : 'text-emerald-400' }}">
                                {{ $file['type'] === 'project_file' ? 'Project' : 'Upload' }}
                            </div>
                            
                            @if(in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp']) && $file['type'] === 'local_upload')
                                <!-- Real Image Preview if Local Upload -->
                                <button type="button" @click="zoomImage = '{{ !empty($file['temporary_url']) ? $file['temporary_url'] : Storage::url($file['path']) }}'" class="cursor-pointer">
                                    <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-fuchsia-500/50 shadow-lg group-hover:border-fuchsia-400 group-hover:scale-110 group-hover:rotate-3 transition-transform relative">
                                        @if(isset($file['is_pending']))
                                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center backdrop-blur-sm z-10">
                                                <span class="text-[8px] font-bold text-white uppercase tracking-widest animate-pulse">Syncing...</span>
                                            </div>
                                        @endif
                                        <img src="{{ !empty($file['temporary_url']) ? $file['temporary_url'] : Storage::url($file['path']) }}" class="w-full h-full object-cover">
                                    </div>
                                </button>
                            @else
                                <div class="w-16 h-16 rounded-full bg-black/40 border border-current shadow-inner flex justify-center items-center">
                                    @svg($props['icon'], 'w-8 h-8 opacity-80')
                                </div>
                            @endif
                            <div class="font-mono text-xs font-bold leading-tight break-all line-clamp-2 w-full px-1" title="{{ basename($file['name']) }}">{{ basename($file['name']) }}</div>
                            <div class="text-[9px] opacity-70 mb-1 w-full truncate px-1" title="{{ dirname($file['path']) }}">{{ dirname($file['path']) === '.' ? 'Root' : dirname($file['path']) }}</div>
                            
                            <!-- Delete File Button -->
                            <div class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                @if(isset($file['is_pending']) && !empty($file['livewire_filename']))
                                    <button type="button" wire:click="$removeUpload('uploadedFiles', '{{ $file['livewire_filename'] }}')" class="bg-red-500 text-white rounded p-1 shadow-md hover:bg-red-600 hover:scale-110 transition-all" title="Upload abbrechen">
                                        <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                    </button>
                                @else
                                    <button type="button" wire:click="removeGlobalFile('{{ $file['type'] }}', '{{ addslashes($file['path']) }}')" class="bg-red-500 text-white rounded p-1 shadow-md hover:bg-red-600 hover:scale-110 transition-all" title="Aus Projekt entfernen">
                                        <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div wire:key="files-empty" class="h-full flex flex-col items-center justify-center text-gray-500 font-mono space-y-4">
                    <x-heroicon-o-folder-open class="w-20 h-20 opacity-20" />
                    <p>Noch keine Dateien in diese KI-Session eingeladen.</p>
                </div>
            @endif
        </div>

            </div>
            @endif
        </div>
    </div>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('workspaceCanvas', () => ({
                draggedAgentId: null,
                startDrag(event, agentId) {
                    this.draggedAgentId = agentId;
                    event.dataTransfer.effectAllowed = 'copyMove';
                    event.dataTransfer.setData('text/plain', agentId);
                    setTimeout(() => event.target.classList.add('opacity-30'), 0);
                },
                dragOver(event) {
                    let taskNode = event.currentTarget;
                    if(!taskNode.classList.contains('border-[var(--theme-color)]')) {
                        taskNode.classList.add('border-[var(--theme-color)]', 'bg-[var(--theme-color-10)]');
                    }
                },
                dragLeave(event) {
                    let taskNode = event.currentTarget;
                    taskNode.classList.remove('border-[var(--theme-color)]', 'bg-[var(--theme-color-10)]');
                },
                dropTask(event, taskId) {
                    let taskNode = event.currentTarget;
                    taskNode.classList.remove('border-[var(--theme-color)]', 'bg-[var(--theme-color-10)]');
                    if(this.draggedAgentId && taskId) {
                        try { @this.assignAgent(taskId, this.draggedAgentId); } catch(e) {}
                    }
                    this.draggedAgentId = null;
                }
            }));
            
            document.addEventListener('dragend', () => {
                document.querySelectorAll('.agent-draggable').forEach(el => el.classList.remove('opacity-30'));
            });
        });
    </script>
    
    @push('scripts')
    <!-- Marked.js, DOMPurify, Highlight.js for Chat Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    
    <script>
        document.addEventListener('alpine:init', () => {
            if (typeof marked !== 'undefined') {
                marked.setOptions({
                    highlight: function(code, lang) {
                        const language = hljs.getLanguage(lang) ? lang : 'plaintext';
                        return hljs.highlight(code, { language }).value;
                    },
                    gfm: true, breaks: true
                });
                const renderer = new marked.Renderer();
                
                renderer.code = function(...args) {
                    let token = typeof args[0] === 'object' ? args[0] : null;
                    let code = token ? token.text : args[0];
                    let lang = token ? token.lang : args[1];
                    let highlightedCode = '';
                    try { highlightedCode = hljs.highlight(code, { language: hljs.getLanguage(lang) ? lang : 'plaintext' }).value; } 
                    catch(e) { highlightedCode = code.replace(/</g, "&lt;").replace(/>/g, "&gt;"); }
                    return `<div class="my-3 rounded-xl overflow-hidden border border-gray-800 bg-gray-950 text-xs font-mono"><div class="px-3 py-1.5 bg-gray-900 border-b border-gray-800"><span class="text-gray-500 uppercase">${lang||'code'}</span></div><div class="p-4 overflow-x-auto custom-scrollbar"><pre class="!bg-transparent !m-0 !p-0"><code class="hljs text-gray-300 leading-relaxed">${highlightedCode}</code></pre></div></div>`;
                };
                
                window.renderAiMarkdown = function(md) {
                    const html = marked.parse(md, { renderer });
                    return DOMPurify.sanitize(html);
                };
            }
        });
    </script>
    @endpush

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--theme-color-30); border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--theme-color-50); }
        .ai-markdown-content p { margin-bottom: 0.5rem; }
        .ai-markdown-content ul { list-style-type: disc; padding-left: 1.5rem; }
        .ai-markdown-content h3 { font-weight: bold; margin-top: 1rem; color: #fff; }
        .ai-markdown-content code:not(.hljs) { background-color: rgba(255,255,255,0.1); padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-family: monospace; font-size: 0.85em; color: var(--theme-color); }
    </style>

    <!-- Management Modals (Isolated via wire:ignore) -->
    <div wire:ignore>
        <!-- Role Manager Modal -->
        <div x-data="{ showRoleManager: false }" 
             x-on:open-role-manager.window="
                showRoleManager = true; 
                if($event.detail.roleId) { 
                    Livewire.dispatchTo('shop.ai.ai-role-manager', 'edit-role', { roleId: $event.detail.roleId }); 
                } else { 
                    Livewire.dispatchTo('shop.ai.ai-role-manager', 'edit-role', { roleId: null }); 
                }
             ">
            
            <div x-show="showRoleManager" style="display: none;" 
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity duration-300" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                
                <div class="relative w-[95vw] h-[95vh] lg:w-[90vw] lg:h-[90vh] bg-gray-950 border border-emerald-500/30 rounded-3xl overflow-auto custom-scrollbar shadow-2xl flex flex-col" 
                     x-transition:enter="ease-out duration-300 transform"
                     x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="ease-in duration-200 transform"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                     @click.away="showRoleManager = false">
                    
                    <button @click="showRoleManager = false" class="absolute top-4 right-4 z-50 text-gray-400 hover:text-white p-2 bg-gray-900/50 rounded-full border border-gray-700/50 hover:bg-gray-800 transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                    
                    <div class="flex-1 w-full relative">
                        <livewire:shop.ai.ai-role-manager />
                    </div>
                </div>
            </div>
        </div>

        <!-- Internal Agent Editor Modal -->
        <div x-data="{ showAgentManager: false }" 
             x-on:open-agent-manager.window="
                showAgentManager = true; 
                let aid = $event.detail.agentId || 'new';
                Livewire.dispatchTo('shop.ai.ai-agent-editor', 'edit-agent', { id: aid }); 
             "
             x-on:close-agent-manager.window="showAgentManager = false">
            
            <div x-show="showAgentManager" style="display: none;" 
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity duration-300" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                
                <div class="relative w-[95vw] h-[95vh] lg:w-[90vw] lg:h-[90vh] bg-[#050505] border border-gray-800 rounded-3xl overflow-auto custom-scrollbar shadow-[0_0_50px_rgba(0,0,0,1)] flex flex-col pt-10" 
                     x-transition:enter="ease-out duration-300 transform"
                     x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="ease-in duration-200 transform"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                     @click.away="if(!document.querySelector('.ck-body-wrapper')) showAgentManager = false">
                    
                    <button @click="showAgentManager = false" class="absolute top-4 right-4 z-50 text-gray-400 hover:text-white p-2 bg-gray-900/50 rounded-full border border-gray-700/50 hover:bg-gray-800 transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                    
                    <div class="flex-1 w-full relative">
                        <livewire:shop.ai.ai-agent-editor />
                    </div>
                </div>
            </div>
        </div>

        <!-- External Agent Editor Modal -->
        <div x-data="{ showExternalAgentManager: false }" 
             x-on:open-external-agent.window="
                showExternalAgentManager = true; 
                Livewire.dispatchTo('shop.ai.external-agent-editor', 'edit-external-agent', { agentId: $event.detail.agentId }); 
             "
             x-on:close-external-agent-manager.window="showExternalAgentManager = false">
            
            <div x-show="showExternalAgentManager" style="display: none;" 
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity duration-300" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                
                <div class="relative w-[95vw] h-[95vh] lg:w-[90vw] lg:h-[90vh] bg-[#050505] border border-rose-900/40 rounded-3xl overflow-auto custom-scrollbar shadow-[0_0_50px_rgba(225,29,72,0.1)] flex flex-col pt-10" 
                     x-transition:enter="ease-out duration-300 transform"
                     x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="ease-in duration-200 transform"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                     @click.away="showExternalAgentManager = false">
                    
                    <button @click="showExternalAgentManager = false" class="absolute top-4 right-4 z-50 text-gray-400 hover:text-white p-2 bg-gray-900/50 rounded-full border border-gray-700/50 hover:bg-gray-800 transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                    
                    <div class="flex-1 w-full relative">
                        <livewire:shop.ai.external-agent-editor />
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</div>
