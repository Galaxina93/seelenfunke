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

