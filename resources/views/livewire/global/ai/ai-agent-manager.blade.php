<div>
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-black/90 backdrop-blur-md p-6 sm:p-10 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.05)] border border-emerald-900/40 relative overflow-hidden mb-8 mt-8">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-cpu-chip class="w-40 h-40 text-emerald-500 drop-shadow-[0_0_20px_rgba(16,185,129,1)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-black text-emerald-500 tracking-widest uppercase shadow-emerald-500/20 drop-shadow-md font-mono">Interne Agenten</h1>
                <p class="text-emerald-700 mt-2 text-sm font-bold uppercase tracking-widest font-mono">Verwalte hier das interne Multi-Agenten-System. Jeder Agent erhält seine Identität und Werkzeuge dynamisch über seine zugewiesene KI-Rolle.</p>
            </div>
            <div class="relative z-10 bg-gray-950 p-2 rounded-xl border border-emerald-900/50 shadow-inner flex items-center gap-3">
                <button wire:click="syncAll" class="px-6 py-2.5 bg-gray-900/80 text-gray-400 border border-gray-700/50 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-gray-800 hover:text-white transition-all flex items-center justify-center gap-2 font-mono group">
                    <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="syncAll" /> Sync Alle
                </button>
                <button wire:click="createAgent" class="px-6 py-2.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-emerald-500 hover:text-black hover:border-emerald-500 hover:shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all flex items-center justify-center gap-2 font-mono">
                    <x-heroicon-o-plus class="w-4 h-4" /> Agent Erschaffen
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($agents as $agent)
                @php
                    $agentColor = $agent->color ?? 'cyan-500';
                    $agentIcon = $agent->icon ?? 'sparkles';
                    $statusColor = $agent->is_active ? 'bg-emerald-500' : 'bg-red-500';
                    
                    $rawModel = $agent->model ?? 'Standard';
                    if(str_contains($rawModel, 'Ministral')) $shortModel = 'Ministral 14B';
                    elseif(str_contains($rawModel, 'Devstral')) $shortModel = 'Devstral 24B';
                    elseif(str_contains($rawModel, 'GPT-OSS')) $shortModel = 'GPT-OSS 120B';
                    else $shortModel = explode(' ', $rawModel)[0];
                @endphp
                <div wire:click="editAgent('{{ $agent->id }}')" class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] hover:border-current text-{{ $agentColor }} hover:shadow-[0_0_25px_currentColor] rounded-3xl p-6 transition-all cursor-pointer group relative overflow-hidden font-mono {{ !$agent->is_active ? 'opacity-60 grayscale hover:opacity-100 hover:grayscale-0' : '' }}">

                    <div class="absolute inset-0 bg-current/5 to-transparent pointer-events-none opacity-0 group-hover:opacity-10 transition-opacity"></div>

                    <div class="relative z-10 flex items-start justify-between mb-5">
                        <div class="flex items-center gap-4">
                            @if($agent->profile_picture)
                                <div class="h-20 w-20 rounded-2xl overflow-hidden border border-{{ $agentColor }}/30 shadow-[0_0_15px_currentColor] text-{{ $agentColor }} bg-gray-900 group-hover:scale-110 transition-transform relative shrink-0">
                                    <div class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full {{ $statusColor }} border-2 border-gray-900 shadow-sm z-20"></div>
                                    <img src="{{ Storage::url($agent->profile_picture) }}" class="w-full h-full object-cover">
                                </div>
                            @else
                                <div class="h-20 w-20 rounded-2xl flex items-center justify-center bg-{{ $agentColor }}/20 text-{{ $agentColor }} border border-{{ $agentColor }}/30 shadow-[0_0_15px_currentColor] group-hover:scale-110 transition-transform relative shrink-0">
                                    <div class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full {{ $statusColor }} border-2 border-gray-900 shadow-sm z-20"></div>
                                    @if(str_starts_with($agent->icon, 'bi-'))
                                        <i class="{{ $agent->icon }} text-4xl drop-shadow-[0_0_10px_currentColor]"></i>
                                    @elseif(str_starts_with(trim($agent->icon), '<svg'))
                                        <div class="w-10 h-10 [&>svg]:w-full [&>svg]:h-full drop-shadow-[0_0_10px_currentColor]">{!! $agent->icon !!}</div>
                                    @else
                                        <x-dynamic-component :component="'heroicon-o-' . $agentIcon" class="w-10 h-10" />
                                    @endif
                                </div>
                            @endif

                            <div>
                                <h3 class="text-xl font-bold text-gray-200 mb-0.5 group-hover:text-current transition-colors font-mono">{{ $agent->name }}</h3>
                                @if($agent->is_active)
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest inline-block">Online</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-red-500/20 text-red-500 border border-red-500/30 uppercase tracking-widest inline-block">Offline</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <p class="relative z-10 text-xs text-gray-400 line-clamp-2 h-8 font-mono mb-4">{{ $agent->role_description ?? 'Spezialisierung nicht definiert.' }}</p>

                    {{-- Kontext Auslastung / Kognitiver Speicher --}}
                    @php
                        $load = $contextLoads[$agent->id] ?? ['tokens' => 0, 'max' => 100, 'percent' => 0];
                        $barColor = $load['percent'] < 40 ? 'bg-emerald-500' : ($load['percent'] < 75 ? 'bg-amber-500' : 'bg-red-500 animate-pulse');
                        $textColor = $load['percent'] < 40 ? 'text-emerald-400' : ($load['percent'] < 75 ? 'text-amber-400' : 'text-red-400 font-bold');
                    @endphp
                    <div class="relative z-10 mb-5">
                        <div class="flex justify-between items-end mb-1">
                            <div class="flex items-center gap-1.5 relative group cursor-help">
                                <h4 class="text-[9px] font-black uppercase text-gray-500 tracking-widest flex items-center gap-1.5 group-hover:text-gray-400 transition-colors">
                                    <i class="bi bi-cpu text-xs"></i> Kognitiver Speicher
                                </h4>
                                <i class="bi bi-info-circle text-[9px] text-gray-500 group-hover:text-primary transition-colors"></i>
                                <div class="absolute bottom-[calc(100%+8px)] left-0 w-[200px] p-2.5 bg-gray-900 border border-gray-700 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.8)] text-[9px] text-gray-400 font-sans normal-case tracking-normal opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                                    <div class="absolute -bottom-1 left-4 w-2 h-2 bg-gray-900 border-b border-r border-gray-700 transform rotate-45"></div>
                                    <strong class="text-white block mb-0.5 mt-0">Basis-Auslastung:</strong>
                                    Zeigt in Tokens an, wie viel "Gehirnkapazität" der Agent allein durch seine Rollenbeschreibung und die zugewiesenen Infrastruktur-Werkzeuge verbraucht.
                                </div>
                            </div>
                            <span class="text-[9px] font-mono {{ $textColor }}">{{ number_format($load['tokens'], 0, ',', '.') }} / {{ number_format($load['max'], 0, ',', '.') }}</span>
                        </div>
                        <div class="w-full h-1.5 bg-gray-900 rounded-full overflow-hidden border border-gray-800 relative">
                            <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-1000 {{ $barColor }} shadow-[0_0_8px_currentColor]" 
                                 style="width: {{ $load['percent'] }}%"></div>
                        </div>
                    </div>

                    <div class="relative z-10 pt-4 border-t border-gray-800/80 flex flex-col gap-2 text-[11px] font-mono uppercase tracking-widest">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1.5 text-gray-500 group-hover:text-gray-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path fill-rule="evenodd" d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM10 7a3 3 0 100 6 3 3 0 000-6zM15.657 5.404a.75.75 0 10-1.06-1.06l-1.061 1.06a.75.75 0 001.06 1.06l1.06-1.06zM6.464 14.596a.75.75 0 10-1.06-1.06l-1.06 1.06a.75.75 0 001.06 1.06l1.06-1.06zM18 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 0118 10zM5 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 015 10zM14.596 15.657a.75.75 0 001.06-1.06l-1.06-1.061a.75.75 0 10-1.06 1.06l1.06 1.06zM5.404 6.464a.75.75 0 001.06-1.06l-1.06-1.06a.75.75 0 10-1.061 1.06l1.06 1.06z" clip-rule="evenodd" /></svg>
                                {{ $agent->tools->count() }} Skills
                            </span>
                            <span class="flex items-center gap-1.5 text-indigo-500/70 group-hover:text-indigo-400 transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full {{ isset($pingResults[$agent->id]) ? (in_array($pingResults[$agent->id]['llm'], ['Offline', 'Fehler']) ? 'bg-red-500' : 'bg-emerald-500 shadow-[0_0_5px_#10b981]') : 'bg-gray-500' }}"></span>
                                LLM: {{ $shortModel }}
                            </span>
                        </div>
                        <div class="flex items-center justify-end">
                            <span class="flex items-center gap-1.5 text-pink-500/70 group-hover:text-pink-400 transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full {{ isset($pingResults[$agent->id]) ? (in_array($pingResults[$agent->id]['tts'], ['Offline', 'Fehler']) ? 'bg-red-500' : ($pingResults[$agent->id]['tts'] === 'Inaktiv' ? 'bg-gray-500' : 'bg-emerald-500 shadow-[0_0_5px_#10b981]')) : 'bg-gray-500' }}"></span>
                                TTS: {{ $agent->tts_provider === 'toni_xttsv2' ? 'Toni XTTS' : ($agent->tts_provider === 'none' ? 'Inaktiv' : 'ElevenLabs') }}
                            </span>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-800/80 flex items-center justify-between gap-4">
                            <button wire:click.stop="pingTest('{{ $agent->id }}')" class="shrink-0 px-3 py-1.5 bg-gray-900/50 hover:bg-current/10 hover:text-current text-gray-400 border border-gray-700 hover:border-current/50 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all shadow-inner group/ping">

                            <span wire:loading.remove wire:target="pingTest('{{ $agent->id }}')" class="flex items-center gap-2 whitespace-nowrap">
                                <x-heroicon-o-signal class="w-3.5 h-3.5 shrink-0 group-hover/ping:animate-pulse" />
                                Ping Test
                            </span>

                                                    <span wire:loading.flex wire:target="pingTest('{{ $agent->id }}')" class="items-center gap-2 text-current opacity-80 whitespace-nowrap">
                                <svg class="animate-spin h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Pinging...
                            </span>

                            </button>

                            <div class="flex flex-col text-right text-[10px] uppercase font-bold tracking-widest min-w-0">
                                @if(isset($pingResults[$agent->id]))
                                    <span class="{{ $pingResults[$agent->id]['llm'] === 'Offline' || $pingResults[$agent->id]['llm'] === 'Fehler' ? 'text-red-400' : 'text-emerald-400 drop-shadow-[0_0_5px_rgba(52,211,153,0.5)]' }} truncate">LLM: {{ $pingResults[$agent->id]['llm'] }}</span>
                                    <span class="{{ $pingResults[$agent->id]['tts'] === 'Offline' || $pingResults[$agent->id]['tts'] === 'Fehler' ? 'text-red-400' : ($pingResults[$agent->id]['tts'] === 'Inaktiv' ? 'text-gray-500' : 'text-emerald-400 drop-shadow-[0_0_5px_rgba(52,211,153,0.5)]') }} truncate">TTS: {{ $pingResults[$agent->id]['tts'] }}</span>
                                @else
                                    <span class="text-gray-600 opacity-50 block mt-1">Status Unbekannt</span>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <livewire:global.ai.external-agent-manager />
</div>

