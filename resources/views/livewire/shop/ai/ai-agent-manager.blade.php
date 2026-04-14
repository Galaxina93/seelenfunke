<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div>
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div class="mb-12 text-center mt-4 font-sans">
            <h1 class="text-3xl sm:text-4xl font-black text-[var(--theme-color)] drop-shadow-sm">
                KI Agenten Manager
            </h1>
            <p class="text-gray-400 mt-2 text-sm">
                Verwalte Modelle, Profile und Zugriffsrechte der internen KI-Instanzen.
            </p>
        </div>

        <div class="flex justify-end mb-8 relative z-10">
            <div class="bg-gray-950 p-2 rounded-xl border border-gray-800 shadow-inner flex items-center gap-3">
                <button wire:click="syncAll" class="px-6 py-2.5 bg-gray-900/80 text-gray-400 border border-gray-700/50 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-gray-800 hover:text-white transition-all flex items-center justify-center gap-2 font-mono group">
                    <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="syncAll" /> Sync Alle
                </button>
                <button wire:click="createAgent" class="px-6 py-2.5 bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] rounded-xl text-xs font-black uppercase tracking-widest hover:bg-[var(--theme-color)] hover:text-black hover:border-[var(--theme-color)] hover:shadow-xl shadow-[var(--theme-color-10)] transition-all flex items-center justify-center gap-2 font-mono">
                    <x-heroicon-o-plus class="w-4 h-4" /> Agent Erschaffen
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($agents as $agent)
                @php
                    $agentColor = $agent->department ? $agent->department->color : ($agent->color ?? 'cyan-500');
                    $agentIcon = $agent->department ? $agent->department->icon : ($agent->icon ?? 'sparkles');
                    $statusColor = $agent->is_active ? 'bg-[var(--theme-color)]' : 'bg-red-500';

                    $rawModel = $agent->model ?? 'Standard';
                    if(str_contains($rawModel, 'Ministral')) $shortModel = 'Ministral';
                    elseif(str_contains($rawModel, 'Devstral')) $shortModel = 'Devstral';
                    elseif(str_contains($rawModel, 'GPT-OSS')) $shortModel = 'GPT-OSS';
                    else $shortModel = explode(' ', $rawModel)[0];
                @endphp
                <div x-data="{ expanded: false }" class="bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] hover:border-current text-{{ $agentColor }} hover:shadow-[0_0_25px_currentColor] rounded-3xl p-6 transition-all group relative overflow-hidden font-mono {{ !$agent->is_active ? 'opacity-60 grayscale hover:opacity-100 hover:grayscale-0' : '' }}">

                    <div class="absolute inset-0 bg-current/5 to-transparent pointer-events-none opacity-0 group-hover:opacity-10 transition-opacity"></div>

                    <div class="relative z-10 flex flex-col items-center">
                        <div wire:click="editAgent('{{ $agent->id }}')" class="cursor-pointer w-full flex flex-col items-center pb-2">
                            @if($agent->profile_picture)
                                <div class="rounded-2xl overflow-hidden border border-{{ $agentColor }}/30 shadow-[0_0_15px_currentColor] text-{{ $agentColor }} bg-gray-900 group-hover:scale-105 transition-all duration-300 relative shrink-0"
                                     :class="expanded ? 'w-20 h-20 mb-4' : 'w-32 h-32 xl:w-40 xl:h-40 mb-6'">
                                    <div class="absolute top-2 right-2 w-3 h-3 rounded-full {{ $statusColor }} border-2 border-gray-900 shadow-sm z-20"></div>
                                    <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" class="w-full h-full object-cover">
                                </div>
                            @else
                                <div class="rounded-2xl flex items-center justify-center bg-{{ $agentColor }}/20 text-{{ $agentColor }} border border-{{ $agentColor }}/30 shadow-[0_0_15px_currentColor] group-hover:scale-105 transition-all duration-300 relative shrink-0"
                                     :class="expanded ? 'w-20 h-20 mb-4' : 'w-32 h-32 xl:w-40 xl:h-40 mb-6'">
                                    <div class="absolute top-2 right-2 w-3 h-3 rounded-full {{ $statusColor }} border-2 border-gray-900 shadow-sm z-20"></div>
                                    @if(str_starts_with($agent->icon, 'bi-'))
                                        <i class="{{ $agent->icon }} drop-shadow-[0_0_10px_currentColor] transition-all" :class="expanded ? 'text-4xl' : 'text-6xl'"></i>
                                    @elseif(str_starts_with(trim($agent->icon), '<svg'))
                                        <div class="[&>svg]:w-full [&>svg]:h-full drop-shadow-[0_0_10px_currentColor] transition-all" :class="expanded ? 'w-10 h-10' : 'w-16 h-16'">{!! $agent->icon !!}</div>
                                    @else
                                        <x-dynamic-component :component="'heroicon-o-' . $agentIcon" class="transition-all" :class="expanded ? 'w-10 h-10' : 'w-16 h-16'" />
                                    @endif
                                </div>
                            @endif

                            <h3 class="font-bold text-gray-200 mb-2 group-hover:text-current transition-all font-mono" :class="expanded ? 'text-xl' : 'text-2xl'">{{ $agent->name }}</h3>
                            @if($agent->is_active)
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-[var(--theme-color-20)] text-[var(--theme-color)] border border-[var(--theme-color-30)] uppercase tracking-widest inline-block">Online</span>
                            @else
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-red-500/20 text-red-500 border border-red-500/30 uppercase tracking-widest inline-block">Offline</span>
                            @endif
                        </div>

                        <div @click.stop="expanded = !expanded" class="mt-4 pt-4 border-t border-gray-800/80 cursor-pointer text-gray-500 hover:text-current transition-colors font-bold text-xs uppercase tracking-widest w-full flex items-center justify-center gap-2">
                            <span x-show="!expanded" class="flex items-center gap-2"><x-heroicon-o-chevron-down class="w-4 h-4"/> Details anzeigen</span>
                            <span x-show="expanded" class="flex items-center gap-2" x-cloak><x-heroicon-o-chevron-up class="w-4 h-4"/> Details ausblenden</span>
                        </div>
                    </div>

                    <div x-show="expanded" x-collapse x-cloak>
                        <p class="relative z-10 text-xs text-center text-gray-400 line-clamp-2 h-8 font-mono mb-4 mt-4 cursor-pointer" wire:click="editAgent('{{ $agent->id }}')">{{ $agent->role_description ?? 'Spezialisierung nicht definiert.' }}</p>

                        {{-- Kontext Auslastung / Kognitiver Speicher --}}
                    @php
                        $load = $contextLoads[$agent->id] ?? ['tokens' => 0, 'max' => 100, 'percent' => 0];
                        $barColor = $load['percent'] < 40 ? 'bg-[var(--theme-color)]' : ($load['percent'] < 75 ? 'bg-amber-500' : 'bg-red-500 animate-pulse');
                        $textColor = $load['percent'] < 40 ? 'text-[var(--theme-color)]' : ($load['percent'] < 75 ? 'text-amber-400' : 'text-red-400 font-bold');
                    @endphp
                    <div class="relative z-10 mb-5">
                        <div class="flex justify-between items-end mb-1">
                            <div class="flex items-center gap-1.5">
                                <h4 class="text-[9px] font-black uppercase text-gray-500 tracking-widest flex items-center gap-1.5">
                                    <i class="bi bi-cpu text-xs"></i> Kognitiver Speicher
                                </h4>
                                <div class="relative group/tooltip cursor-help flex items-center justify-center">
                                    <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 hover:text-white transition-colors" />
                                    <div class="absolute bottom-[calc(100%+8px)] left-1/2 -translate-x-1/2 w-[200px] p-2.5 bg-gray-900 border border-gray-700 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.8)] text-[9px] text-gray-400 font-sans normal-case tracking-normal opacity-0 group-hover/tooltip:opacity-100 transition-opacity pointer-events-none z-50">
                                        <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 border-b border-r border-gray-700 transform rotate-45"></div>
                                        <strong class="text-white block mb-0.5 mt-0">Basis-Auslastung:</strong>
                                        Zeigt in Tokens an, wie viel "Gehirnkapazität" der Agent allein durch seine Rollenbeschreibung und die zugewiesenen Infrastruktur-Werkzeuge verbraucht.
                                    </div>
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
                                <span class="w-1.5 h-1.5 rounded-full {{ isset($pingResults[$agent->id]) ? (in_array($pingResults[$agent->id]['llm'], ['Offline', 'Fehler']) ? 'bg-red-500' : 'bg-[var(--theme-color)] shadow-[0_0_5px_var(--theme-color)]') : 'bg-gray-500' }}"></span>
                                LLM: {{ $shortModel }}
                            </span>
                        </div>
                        <div class="flex items-center justify-end">
                            <span class="flex items-center gap-1.5 {{ !$agent->tts_enabled ? 'text-gray-500 group-hover:text-gray-400' : 'text-pink-500/70 group-hover:text-pink-400' }} transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full {{ !$agent->tts_enabled ? 'bg-gray-500' : (isset($pingResults[$agent->id]) ? (in_array($pingResults[$agent->id]['tts'], ['Offline', 'Fehler']) ? 'bg-red-500' : ($pingResults[$agent->id]['tts'] === 'Inaktiv' ? 'bg-gray-500' : 'bg-[var(--theme-color)] shadow-[0_0_5px_var(--theme-color)]')) : 'bg-gray-500') }}"></span>
                                TTS: {{ !$agent->tts_enabled ? 'Deaktiviert' : ($agent->tts_provider === 'toni_xttsv2' ? 'Toni XTTS' : 'Inaktiv') }}
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
                                    <span class="{{ $pingResults[$agent->id]['llm'] === 'Offline' || $pingResults[$agent->id]['llm'] === 'Fehler' ? 'text-red-400' : 'text-[var(--theme-color)] drop-shadow-[0_0_5px_rgba(52,211,153,0.5)]' }} truncate">LLM: {{ $pingResults[$agent->id]['llm'] }}</span>
                                    <span class="{{ $pingResults[$agent->id]['tts'] === 'Offline' || $pingResults[$agent->id]['tts'] === 'Fehler' ? 'text-red-400' : (in_array($pingResults[$agent->id]['tts'], ['Inaktiv', 'Deaktiviert']) ? 'text-gray-500' : 'text-[var(--theme-color)] drop-shadow-[0_0_5px_rgba(52,211,153,0.5)]') }} truncate">TTS: {{ $pingResults[$agent->id]['tts'] }}</span>
                                @else
                                    <span class="text-gray-600 opacity-50 block mt-1">Status Unbekannt</span>
                                @endif
                            </div>
                        </div>

                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <livewire:shop.ai.external-agent-manager />
</div>


</div>
