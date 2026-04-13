<div class="mb-8 w-full">
    @if($currentMission)
        @php $agent = $currentMission->agent; @endphp
        <div x-data="{ expanded: false }" class="relative bg-gradient-to-r from-gray-900 via-gray-950 to-gray-900 border border-[var(--theme-color-50)] rounded-2xl p-4 md:p-6 shadow-[0_0_40px_rgba(197,160,89,0.15)] overflow-hidden group">
            <!-- Glow background effect -->
            <div class="absolute inset-0 bg-[var(--theme-color-50)] opacity-5 group-hover:opacity-10 transition-opacity"></div>
            
            <div class="relative z-10 flex flex-col sm:flex-row gap-4 sm:gap-6 items-start sm:items-center cursor-pointer select-none" @click="expanded = !expanded">
                
                <!-- Icon Badge / Agent Image -->
                <div class="shrink-0 relative">
                    <div class="absolute -inset-2 bg-[var(--theme-color)] opacity-20 blur-xl rounded-full"></div>
                    <div class="w-14 h-14 md:w-16 md:h-16 bg-gray-950 border-2 border-[var(--theme-color)] rounded-full flex items-center justify-center shadow-inner relative z-10 overflow-hidden">
                        @if($agent && $agent->profile_picture)
                            <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" alt="{{ $agent->name }}" class="w-full h-full object-cover">
                        @else
                            <x-heroicon-m-bolt class="w-6 h-6 md:w-8 md:h-8 text-[var(--theme-color)] animate-pulse" />
                        @endif
                    </div>
                </div>

                <!-- Header Text (Always visible) -->
                <div class="flex-1 flex justify-between items-center w-full">
                    <div>
                        <div class="flex items-center gap-3 mb-1.5">
                            <span class="text-[10px] sm:text-xs font-black uppercase text-[var(--theme-color)] tracking-widest bg-[var(--theme-color-10)] px-2 py-0.5 rounded border border-[var(--theme-color-30)]">
                                {{ $agent ? $agent->name : 'CEO MISSION' }}
                            </span>
                            <span class="text-[10px] font-black uppercase text-gray-500 tracking-widest hidden sm:inline">
                                Generiert am {{ $currentMission->created_at->format('d.m.Y H:i') }}
                            </span>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-400 font-medium whitespace-nowrap" x-show="!expanded">
                            Mission anzeigen ...
                        </p>
                    </div>
                    
                    <div class="text-[var(--theme-color)] transition-transform duration-300 bg-[var(--theme-color-10)] p-2 rounded-full cursor-pointer hover:bg-[var(--theme-color-20)]" :class="expanded ? 'rotate-180' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Expandable Mission Content -->
            <div x-show="expanded" x-collapse x-cloak>
                <div class="mt-5 pt-5 border-t border-[var(--theme-color-20)] relative z-10 ml-0 sm:ml-[88px]">
                    <div class="flex items-center gap-3 mb-3 sm:hidden">
                        <span class="text-[10px] font-black uppercase text-gray-500 tracking-widest">
                            Generiert am {{ $currentMission->created_at->format('d.m.Y H:i') }}
                        </span>
                    </div>
                    <p class="text-sm md:text-base lg:text-lg text-white font-serif italic leading-relaxed whitespace-pre-wrap">
                        "{{ $currentMission->mission_text }}"
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="relative bg-gray-900/50 border border-gray-800 border-dashed rounded-2xl p-6 flex flex-col items-center justify-center text-center">
            <x-heroicon-o-chat-bubble-bottom-center-text class="w-8 h-8 text-gray-600 mb-3" />
            <h3 class="text-gray-400 font-black tracking-widest uppercase text-xs">Keine aktive Mission angefordert</h3>
            <p class="text-gray-500 text-sm mt-1">Klicke oben auf "⚡ Was jetzt?", um eine Express-Analyse durchzuführen.</p>
        </div>
    @endif
</div>
