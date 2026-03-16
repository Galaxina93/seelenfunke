<div>
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 mt-4 border-t border-gray-800/80">
        
        <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-4">
            <div>
                <h2 class="text-3xl font-black text-white mb-2 uppercase tracking-wider font-mono">Externe Agenten (Headless)</h2>
                <p class="text-gray-400 font-mono text-sm max-w-2xl">Fernsteuerung externer KI-Engines über REST-API Endpunkte. Konfigurationen werden direkt live auf dem Zielsystem persistiert.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Toni Agent Card (View Mode Only) -->
            <div wire:click="editExternalAgent('toni')" class="bg-black/40 backdrop-blur-md border border-gray-800/60 shadow-[0_0_20px_rgba(0,0,0,0.3)] hover:border-indigo-500 rounded-3xl p-6 transition-all cursor-pointer group relative overflow-hidden col-span-1">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity"></div>
                
                <div class="relative z-10 flex items-start justify-between mb-5">
                    <div class="flex items-center gap-4">
                        <div class="h-14 w-14 rounded-2xl flex items-center justify-center bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 shadow-[0_0_15px_currentColor] group-hover:scale-110 transition-transform relative">
                            <div class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full {{ $connectionError ? 'bg-red-500' : 'bg-emerald-500' }} border-2 border-gray-900 shadow-sm z-20"></div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white mb-0.5 group-hover:text-indigo-400 transition-colors font-mono">Toni (Python)</h3>
                            @if($connectionError)
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
                            <span class="w-1.5 h-1.5 shrink-0 rounded-full {{ $connectionError ? 'bg-red-500' : 'bg-emerald-500 shadow-[0_0_5px_#10b981]' }}"></span>
                            LLM: {{ $llm_model ?: '?' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-end">
                        <span class="flex items-center gap-1.5 text-pink-500/70 group-hover:text-pink-400 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full {{ $connectionError ? 'bg-red-500' : 'bg-emerald-500 shadow-[0_0_5px_#10b981]' }}"></span>
                            TTS: Toni XTTS
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
