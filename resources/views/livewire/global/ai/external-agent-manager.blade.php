<div>
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 mt-4 border-t border-gray-800/80">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-black/90 backdrop-blur-md p-6 sm:p-10 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.05)] border border-emerald-900/40 relative overflow-hidden mb-8 mt-4">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-server-stack class="w-40 h-40 text-emerald-500 drop-shadow-[0_0_20px_rgba(16,185,129,1)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-black text-emerald-500 tracking-widest uppercase shadow-emerald-500/20 drop-shadow-md font-mono">Externe Agenten (Headless)</h1>
                <p class="text-emerald-700 mt-2 text-sm font-bold uppercase tracking-widest font-mono">Fernsteuerung externer KI-Engines über REST-API Endpunkte. Konfigurationen werden live persistiert.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Toni Agent Card (View Mode Only) -->
            <div wire:click="editExternalAgent('toni')" class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] hover:border-indigo-500 text-indigo-500 hover:shadow-[0_0_25px_currentColor] rounded-3xl p-6 transition-all cursor-pointer group relative overflow-hidden col-span-1 font-mono">
                <div class="absolute inset-0 bg-current/5 to-transparent pointer-events-none opacity-0 group-hover:opacity-10 transition-opacity"></div>
                
                <div class="relative z-10 flex items-start justify-between mb-5">
                    <div class="flex items-center gap-4">
                        <div class="h-14 w-14 rounded-2xl flex items-center justify-center bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 shadow-[0_0_15px_currentColor] group-hover:scale-110 transition-transform relative">
                            <div class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full {{ !$pingRan ? 'bg-gray-500' : ($connectionError ? 'bg-red-500' : 'bg-emerald-500') }} border-2 border-gray-900 shadow-sm z-20"></div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-200 mb-0.5 group-hover:text-current transition-colors font-mono">Toni (Python)</h3>
                            @if(!$pingRan)
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-gray-500/20 text-gray-500 border border-gray-500/30 uppercase tracking-widest inline-block">Ungeprüft</span>
                            @elseif($connectionError)
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-red-500/20 text-red-500 border border-red-500/30 uppercase tracking-widest inline-block">Offline</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest inline-block">Online</span>
                            @endif
                        </div>
                    </div>
                </div>

                <p class="relative z-10 text-xs text-gray-400 line-clamp-2 h-8 font-mono mb-4">Python External Engine. Steuert Text-to-Speech und lokales Inferencing via REST-API.</p>

                <div class="relative z-10 pt-4 border-t border-gray-800/80 flex flex-col gap-2 text-[11px] font-mono uppercase tracking-widest">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5 text-gray-500 group-hover:text-gray-300 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 001 5.5V14a1.5 1.5 0 001.5 1.5h15A1.5 1.5 0 0019 14V5.5A1.5 1.5 0 0017.5 4h-15zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm0 4a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                            Port 8000
                        </span>
                        <span class="flex items-center gap-1.5 text-indigo-500/70 group-hover:text-indigo-400 transition-colors truncate max-w-[120px]">
                            <span class="w-1.5 h-1.5 shrink-0 rounded-full {{ !$pingRan ? 'bg-gray-500' : ($connectionError ? 'bg-red-500' : 'bg-emerald-500 shadow-[0_0_5px_#10b981]') }}"></span>
                            LLM: {{ $pingRan ? ($llm_model ?: '?') : 'Unbekannt' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-end">
                        <span class="flex items-center gap-1.5 text-pink-500/70 group-hover:text-pink-400 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full {{ !$pingRan ? 'bg-gray-500' : ($connectionError ? 'bg-red-500' : 'bg-emerald-500 shadow-[0_0_5px_#10b981]') }}"></span>
                            TTS: Toni XTTS
                        </span>
                    </div>
                </div>

                <!-- Ping Test Button Action Area -->
                <div class="mt-4 pt-4 border-t border-gray-800/80 flex items-center justify-between">
                    <button wire:click.stop="fetchStatus" class="px-3 py-1.5 bg-gray-900/50 hover:bg-current/10 hover:text-current text-gray-400 border border-gray-700 hover:border-current/50 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all shadow-inner flex items-center gap-2 group/ping">
                        <span wire:loading.remove wire:target="fetchStatus" class="flex items-center gap-2"><x-heroicon-o-signal class="w-3.5 h-3.5 group-hover/ping:animate-pulse" /> Ping Test</span>
                        <span wire:loading wire:target="fetchStatus" class="flex items-center gap-2 text-current opacity-80"><svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Pinging...</span>
                    </button>
                    
                    <div class="flex flex-col text-right text-[10px] uppercase font-bold tracking-widest w-1/2">
                        @if(!$pingRan)
                            <span class="text-gray-600 opacity-50 block mt-1">Status Unbekannt</span>
                        @elseif($connectionError)
                            <span class="text-red-400 truncate">Fehler / Offline</span>
                        @else
                            <span class="text-emerald-400 drop-shadow-[0_0_5px_rgba(52,211,153,0.5)] truncate">Verbunden</span>
                        @endif
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
