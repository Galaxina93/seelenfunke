<div class="relative w-full animate-fade-in-up" x-data="{ expanded: false }">

    {{-- HAUPT-CONTAINER: Clean White Look (Dezenter & Kleiner) --}}
    <div class="bg-white rounded-[2rem] shadow-lg shadow-slate-200/50 border border-slate-100 p-5 md:p-6 relative overflow-hidden group">

        {{-- Dezentere Hintergrund-Effekte --}}
        <div class="absolute top-0 right-0 w-48 h-48 bg-blue-50 rounded-full blur-[60px] opacity-40 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-orange-50 rounded-full blur-[60px] opacity-30 pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row gap-6 items-start">

            {{-- 1. FUNKI AVATAR (Der Trigger - Kleiner) --}}
            <div class="shrink-0 flex flex-col items-center gap-2 md:pt-2">
                <div class="relative cursor-pointer transition-transform duration-300 hover:scale-105 active:scale-95"
                     @click="expanded = !expanded">
                    {{-- Pulsierender Ring --}}
                    <div class="absolute inset-0 bg-blue-100 rounded-2xl animate-ping opacity-50" x-show="!expanded"></div>

                    <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                         class="w-16 h-16 md:w-20 md:h-20 rounded-2xl object-cover relative z-10"
                         alt="Funki">

                    {{-- Status Dot --}}
                    <div class="absolute -bottom-1 -right-1 bg-green-500 w-3 h-3 rounded-full border-2 border-white z-20"></div>
                </div>

                <button @click="expanded = !expanded" class="text-[9px] font-bold uppercase tracking-widest text-slate-400 hover:text-blue-500 transition-colors flex items-center gap-1">
                    <i class="bi bi-hand-index-thumb"></i> Klick mich!
                </button>
            </div>

            {{-- 2. SPRECHBLASE (Content Area - Kompakter) --}}
            <div class="flex-1 w-full pt-1">

                {{-- Bubble Container --}}
                <div class="relative bg-slate-50 rounded-2xl p-4 border border-slate-100 transition-all duration-500">

                    {{-- Pfeil links (Desktop) --}}
                    <div class="hidden md:block absolute top-6 -left-2 w-4 h-4 bg-slate-50 border-l border-b border-slate-100 transform rotate-45"></div>

                    {{-- HEADER: Immer sichtbar (Nächste Aktion) --}}
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[9px] font-black text-blue-500 uppercase tracking-[0.2em]">Funkis Radar</span>
                                <span class="w-1 h-1 rounded-full bg-blue-500 animate-pulse"></span>
                            </div>

                            @if($this->nextScheduledSend)
                                <h3 class="text-slate-800 font-bold text-base leading-tight">
                                    "Als nächstes: <span class="text-blue-600">{{ $this->nextScheduledSend['subject'] }}</span>"
                                </h3>
                                <p class="text-[10px] text-slate-500 mt-1 font-medium flex items-center gap-1">
                                    <i class="bi bi-clock"></i> Geplant: {{ $this->nextScheduledSend['send_date']->format('d.m.Y') }}
                                </p>
                            @else
                                <h3 class="text-slate-800 font-bold text-base">"Alles ruhig im Orbit!"</h3>
                                <p class="text-[10px] text-slate-500 mt-1">Keine anstehenden Kampagnen.</p>
                            @endif
                        </div>

                        {{-- Toggle Icon --}}
                        <button @click="expanded = !expanded" class="shrink-0 w-6 h-6 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-blue-500 transition-all shadow-sm">
                            <i class="bi bi-chevron-down text-xs transition-transform duration-300" :class="expanded ? 'rotate-180' : ''"></i>
                        </button>
                    </div>

                    {{-- EXPANDABLE CONTENT (Integrierte Stats) --}}
                    <div x-show="expanded" x-collapse style="display: none;">
                        <div class="pt-4 mt-4 border-t border-slate-200/60 space-y-6">

                            {{-- A: Kommende Termine --}}
                            <div>
                                <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-2">Kommende Missionen</h4>
                                <div class="space-y-1.5">
                                    @php
                                        $upcoming = $this->calendarData->where('is_action', true)->where('status', 'scheduled')->take(3);
                                    @endphp

                                    @forelse($upcoming as $item)
                                        <div class="flex items-center justify-between p-2 bg-white rounded-lg border border-slate-100 shadow-sm hover:border-blue-200 transition-colors">
                                            <div class="flex items-center gap-2 overflow-hidden">
                                                <div class="w-6 h-6 rounded bg-blue-50 text-blue-500 flex items-center justify-center shrink-0">
                                                    <i class="bi bi-envelope-paper-fill text-[10px]"></i>
                                                </div>
                                                <span class="text-[10px] font-bold text-slate-700 truncate">{{ str_replace('📧 ', '', $item['title']) }}</span>
                                            </div>
                                            <span class="text-[9px] font-mono text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100">
                                                {{ $item['date']->format('d.m.') }}
                                            </span>
                                        </div>
                                    @empty
                                        <div class="text-[10px] text-slate-400 italic pl-1">Keine weiteren Termine.</div>
                                    @endforelse
                                </div>
                            </div>

                            {{-- B: System Status (Integrierte Kacheln - Kompakt) --}}
                            <div>
                                {{-- Header mit Tooltip --}}
                                <div class="flex items-center gap-2 mb-3">
                                    <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-wider">System Integrität</h4>

                                    @if(isset($infoTexts['system_integrity']))
                                        <div x-data="{ show: false }" class="relative inline-block">
                                            <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-slate-300 hover:text-blue-500 transition-colors focus:outline-none">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                            <div x-show="show" x-cloak
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 translate-y-1"
                                                 x-transition:enter-end="opacity-100 translate-y-0"
                                                 class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-slate-800 text-white text-[10px] leading-relaxed rounded-xl shadow-xl z-50 text-center font-normal pointer-events-none">
                                                {{ $infoTexts['system_integrity'] }}
                                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Grid Content --}}
                                <div class="grid grid-cols-2 gap-3">

                                    {{-- Automatisierung --}}
                                    <div class="bg-white p-3 rounded-xl border border-slate-100 shadow-sm flex flex-col items-center text-center">
                                        <div class="w-8 h-8 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center mb-1">
                                            <i class="bi bi-cpu-fill text-xs"></i>
                                        </div>
                                        <div class="text-sm font-black text-slate-800 leading-none">
                                            {{ $activeTemplatesCount }}<span class="text-slate-300 text-[10px]">/{{ count($availableEvents) }}</span>
                                        </div>
                                        <div class="text-[8px] font-bold text-slate-400 uppercase tracking-tight mt-0.5">Aktive Zyklen</div>
                                    </div>

                                    {{-- Abonnenten --}}
                                    <div class="bg-white p-3 rounded-xl border border-slate-100 shadow-sm flex flex-col items-center text-center">
                                        <div class="w-8 h-8 rounded-full bg-green-50 text-green-500 flex items-center justify-center mb-1">
                                            <i class="bi bi-people-fill text-xs"></i>
                                        </div>
                                        <div class="text-sm font-black text-slate-800 leading-none">
                                            {{ $subscriberCount }}
                                        </div>
                                        <div class="text-[8px] font-bold text-slate-400 uppercase tracking-tight mt-0.5">Empfänger</div>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                    {{-- End Expandable --}}

                </div>
            </div>
        </div>
    </div>
</div>
