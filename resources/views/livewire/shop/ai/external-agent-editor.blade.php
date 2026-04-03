<div>
    <div class="max-w-7xl mx-auto">
        <!-- Header / Back Button -->
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.ai-dashboard', ['activeTab' => 'agents']) }}" class="h-10 w-10 bg-gray-900 border border-gray-700/50 rounded-xl flex items-center justify-center text-gray-400 hover:text-white hover:border-gray-500 transition-all shadow-sm group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 group-hover:-translate-x-1 transition-transform">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-3xl font-black text-white uppercase tracking-wider font-mono flex items-center gap-3">
                        Toni Editor
                        @if($connectionError)
                            <span class="w-3 h-3 rounded-full bg-red-500 shadow-[0_0_10px_#ef4444]" title="Offline"></span>
                        @else
                            <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_10px_#10b981]" title="Verbunden"></span>
                        @endif
                    </h2>
                    <p class="text-gray-400 font-mono text-xs">Externe Python-Engine verwalten (Port 8000)</p>
                </div>
            </div>
            
            <button wire:click="fetchConfig" class="text-gray-400 hover:text-white flex items-center gap-2 bg-gray-900 border border-gray-700/50 px-4 py-2 rounded-xl transition-all shadow-sm hover:border-gray-500 font-mono text-xs uppercase" title="Konfiguration neu laden">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" wire:loading.class="animate-spin" wire:target="fetchConfig">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Sync
            </button>
        </div>

        @if($connectionError)
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-6 font-mono text-sm flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" /></svg>
                {{ $connectionError }}
            </div>
        @endif

        @if($saveSuccess)
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-3 rounded-xl mb-6 font-mono text-sm flex items-center gap-3 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 11.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" /></svg>
                Konfiguration erfolgreich auf Toni überschrieben!
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            
            <!-- Left Side: Visuals, Core Identity -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Profile Block -->
                <div class="bg-black/40 backdrop-blur-md border border-gray-800/60 shadow-[0_0_20px_rgba(0,0,0,0.3)] rounded-3xl p-6 text-center">
                    <div class="h-32 w-32 mx-auto rounded-3xl bg-indigo-500/20 text-indigo-400 border-2 border-indigo-500 shadow-[0_0_20px_rgba(99,102,241,0.2)] flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </div>
                    <h3 class="font-black text-xl text-white font-mono uppercase tracking-widest mb-1">Toni AI</h3>
                    <p class="text-xs text-gray-400 font-mono">Headless Python Engine</p>
                </div>

                <!-- Voice Form -->
                <div class="bg-black/40 backdrop-blur-md border border-gray-800/60 shadow-[0_0_20px_rgba(0,0,0,0.3)] rounded-3xl p-6">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-emerald-400"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" /></svg>
                        Aktive Stimme (TTS)
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        @forelse($voices as $voice)
                            <div wire:click="$set('voice_preset', '{{ $voice['id'] }}')" 
                                 class="cursor-pointer border-2 rounded-2xl p-2 transition-all text-center group bg-black/50 {{ $voice_preset === $voice['id'] ? 'border-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.2)]' : 'border-gray-800 hover:border-emerald-500/50' }}">
                                <div class="w-12 h-12 mx-auto rounded-full overflow-hidden mb-2 border-2 {{ $voice_preset === $voice['id'] ? 'border-emerald-500' : 'border-gray-700' }} group-hover:scale-105 transition-transform">
                                    @if(isset($voice['image_file']) && $voice['image_file'])
                                        <img src="{{ rtrim($toniUrl, '/') }}/{{ ltrim($voice['image_file'], '/') }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="bg-gray-800 w-full h-full flex items-center justify-center text-gray-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" /></svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="font-mono text-[10px] text-white uppercase tracking-wider">{{ $voice['name'] ?? $voice['id'] }}</div>
                            </div>
                        @empty
                            <div class="col-span-2 py-4 text-center text-gray-500 font-mono text-xs border border-dashed border-gray-700 rounded-xl">
                                Keine Stimmen gefunden.
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-6">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">LLM Engine</label>
                        <select wire:model.defer="llm_hoster" class="w-full bg-gray-900 border border-gray-700/50 rounded-xl shadow-inner focus:border-indigo-500 text-white text-xs p-2.5 font-mono mb-2">
                            <option value="lokal">Lokal (Ollama)</option>
                            <option value="mittwald">Mittwald Cluster</option>
                            <option value="openai">OpenAI (Online)</option>
                            <option value="anthropic">Anthropic (Online)</option>
                        </select>
                        <input type="text" wire:model.defer="llm_model" placeholder="Model Tag (z.B. llama3)" class="w-full bg-gray-900 border border-gray-700/50 rounded-xl shadow-inner focus:border-indigo-500 text-white text-xs p-2.5 font-mono">
                    </div>
                </div>
            </div>

            <!-- Right Side: Presets & Form -->
            <div class="lg:col-span-2 form-container">
                <form wire:submit.prevent="saveConfig" class="bg-black/40 backdrop-blur-md border border-gray-800/60 shadow-[0_0_20px_rgba(0,0,0,0.3)] rounded-3xl p-8">
                    
                    <div class="mb-4">
                        <span class="px-2 py-1 rounded bg-teal-500/20 text-teal-400 font-bold text-[10px] font-mono uppercase tracking-widest border border-teal-500/30">Persönlichkeits-Voreinstellungen (Presets)</span>
                        <p class="text-[11px] text-gray-400 mt-2 font-mono">Ein Klick überschreibt den System-Prompt und die Temperatur passend zum jeweiligen Modus.</p>
                    </div>

                    <!-- PRESETS CARDS -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                        
                        <!-- CEO -->
                        <div wire:click="setPreset('ceo')" class="cursor-pointer border rounded-2xl p-4 text-center group transition-all hover:bg-teal-900/20 hover:border-teal-500/50 {{ $temperature == 0.2 ? 'border-teal-500 bg-teal-900/40 shadow-[0_0_15px_rgba(20,184,166,0.3)]' : 'border-gray-700/80 bg-gray-900/50' }}">
                            <div class="text-2xl mb-2 group-hover:scale-110 transition-transform">🚀</div>
                            <h4 class="font-bold text-xs uppercase tracking-wider mb-2 font-mono transition-colors {{ $temperature == 0.2 ? 'text-teal-400' : 'text-white' }}">CEO-Modus</h4>
                            <p class="text-[10px] leading-relaxed group-hover:text-gray-300 {{ $temperature == 0.2 ? 'text-teal-100' : 'text-gray-400' }}">Absolut effizient. Fokus auf Skalierung und maximalen Geschäftserfolg. Kein Smalltalk, harte Fakten.</p>
                        </div>
                        
                        <!-- Kollege -->
                        <div wire:click="setPreset('kollege')" class="cursor-pointer border rounded-2xl p-4 text-center group transition-all hover:bg-orange-900/20 hover:border-orange-500/50 {{ $temperature == 0.6 ? 'border-orange-500 bg-orange-900/40 shadow-[0_0_15px_rgba(249,115,22,0.3)]' : 'border-gray-700/80 bg-gray-900/50' }}">
                            <div class="text-2xl mb-2 group-hover:scale-110 transition-transform">⚖️</div>
                            <h4 class="font-bold text-xs uppercase tracking-wider mb-2 font-mono transition-colors {{ $temperature == 0.6 ? 'text-orange-400' : 'text-white' }}">Kollege</h4>
                            <p class="text-[10px] leading-relaxed group-hover:text-gray-300 {{ $temperature == 0.6 ? 'text-orange-100' : 'text-gray-400' }}">Professioneller Erfolg mit menschlicher Note. Zielorientiert, charismatisch und humorvoll.</p>
                        </div>

                        <!-- Feierabend -->
                        <div wire:click="setPreset('feierabend')" class="cursor-pointer border rounded-2xl p-4 text-center group transition-all hover:bg-blue-900/20 hover:border-blue-500/50 {{ $temperature == 0.9 ? 'border-blue-500 bg-blue-900/40 shadow-[0_0_15px_rgba(59,130,246,0.3)]' : 'border-gray-700/80 bg-gray-900/50' }}">
                            <div class="text-2xl mb-2 group-hover:scale-110 transition-transform">🛋️</div>
                            <h4 class="font-bold text-xs uppercase tracking-wider mb-2 font-mono transition-colors {{ $temperature == 0.9 ? 'text-blue-400' : 'text-white' }}">Feierabend</h4>
                            <p class="text-[10px] leading-relaxed group-hover:text-gray-300 {{ $temperature == 0.9 ? 'text-blue-100' : 'text-gray-400' }}">Empathischer Begleiter. Fokus auf Alltag, Familie und aktives ungestörtes Brainstorming.</p>
                        </div>

                    </div>

                    <!-- PROMPT EDITOR -->
                    <div class="space-y-6 border-t border-gray-800/80 pt-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>
                                Master System-Prompt
                            </label>
                            <textarea wire:model.defer="system_prompt" rows="8" class="w-full bg-gray-900 border border-gray-700/50 rounded-xl shadow-inner focus:border-indigo-500 text-white text-sm p-4 font-mono leading-relaxed transition-all"></textarea>
                            <div class="mt-3 flex items-start gap-2 bg-blue-900/20 border border-blue-500/30 p-3 rounded-lg text-[11px] text-blue-200/80 font-mono">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 shrink-0 mt-0.5 text-blue-400"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 01.67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 11-.671-1.34l.041-.022zM12 9a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" /></svg>
                                INFO: Dieser Text bildet die Kern-Grundanweisung (Persona), die bei jeder Anfrage als System-Message an die Python Engine geschickt wird.
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.866 8.21 8.21 0 003 2.48z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z" /></svg>
                                    Temperatur (Kreativität & Halluzination)
                                </span>
                                <span class="text-indigo-400 font-bold bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/20">{{ number_format((float)$temperature, 2) }}</span>
                            </label>
                            <input type="range" wire:model.live="temperature" min="0.0" max="2.0" step="0.1" class="w-full mt-3 accent-indigo-500 bg-gray-900 rounded-lg appearance-none cursor-pointer h-2 border border-gray-700/50">
                        </div>
                    </div>

                    <!-- ACTION FOOTER -->
                    <div class="mt-8 pt-6 border-t border-gray-800/80 flex justify-end">
                        <button type="submit" class="bg-teal-500 hover:bg-teal-400 text-black font-extrabold py-3 px-8 rounded-xl shadow-[0_0_20px_rgba(20,184,166,0.3)] hover:shadow-[0_0_30px_rgba(20,184,166,0.5)] transition-all flex items-center gap-2 font-mono uppercase tracking-widest disabled:opacity-50 disabled:cursor-not-allowed" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveConfig">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd" /></svg>
                            </span>
                            <span wire:loading wire:target="saveConfig">
                                <svg class="animate-spin h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </span>
                            Speichern
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
