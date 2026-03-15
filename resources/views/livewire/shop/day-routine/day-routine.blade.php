<div>
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8 min-h-[600px] flex flex-col relative overflow-hidden transition-all duration-500">
        {{-- Dekorativer Hintergrund (Gold-Glow passend zum Projekt) --}}
        <div class="absolute top-0 right-0 w-48 h-full bg-gradient-to-l from-primary/5 to-transparent opacity-50 pointer-events-none"></div>

        <div class="flex justify-between items-center mb-8 relative z-10">
            <div>
                <h3 class="text-2xl font-serif font-bold text-white tracking-tight">Tagesroutine</h3>
                <p class="text-[10px] font-black text-gray-500 mt-1 uppercase tracking-[0.2em]">Dein Bio-Rhythmus</p>
            </div>
            <button wire:click="create" class="w-10 h-10 rounded-xl bg-gray-950 text-white flex items-center justify-center border border-gray-800 hover:border-primary hover:text-primary transition-all shadow-inner active:scale-95 group">
                <x-heroicon-m-plus class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
            </button>
        </div>

        {{-- LISTE --}}
        @if(!$isEditing)
            @php
                $nowFormat = date('H:i');
                $activeId = null;
                $sortedRoutines = $routines->sortBy('start_time')->values();

                foreach ($sortedRoutines as $i => $r) {
                    $start = date('H:i', strtotime($r->start_time));
                    $end = ($r->duration_minutes > 0)
                        ? date('H:i', strtotime($r->start_time . " +{$r->duration_minutes} minutes"))
                        : date('H:i', strtotime(($sortedRoutines[$i + 1] ?? $sortedRoutines[0])->start_time));

                    if ($start <= $end) {
                        if ($nowFormat >= $start && $nowFormat < $end) { $activeId = $r->id; break; }
                    } else {
                        if ($nowFormat >= $start || $nowFormat < $end) { $activeId = $r->id; break; }
                    }
                }
            @endphp

            <div class="space-y-6 overflow-y-auto custom-scrollbar pr-4 flex-1 relative z-10 pt-2 pb-6">
                @foreach($sortedRoutines as $r)
                    @php $isActive = $r->id === $activeId; @endphp

                    <div class="flex items-center gap-4 group">
                        {{-- Uhrzeit --}}
                        <div class="w-12 text-right font-mono text-xs font-bold {{ $isActive ? 'text-primary drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]' : 'text-gray-600' }}">
                            {{ \Carbon\Carbon::parse($r->start_time)->format('H:i') }}
                        </div>

                        {{-- Timeline Dot --}}
                        <div class="relative flex items-center justify-center">
                            @if($isActive)
                                <span class="absolute inline-flex h-6 w-6 rounded-full bg-primary opacity-20 animate-ping"></span>
                                <div class="w-3.5 h-3.5 rounded-full bg-primary ring-4 ring-primary/20 shadow-[0_0_15px_rgba(197,160,89,1)] z-10"></div>
                            @else
                                <div class="w-3 h-3 rounded-full border-2 border-gray-700 bg-gray-950 group-hover:border-primary/50 group-hover:scale-125 transition-all duration-500 z-10"></div>
                            @endif

                            @if(!$loop->last)
                                <div class="absolute top-3 w-px h-16 bg-gray-800 z-0"></div>
                            @endif
                        </div>

                        {{-- Routine Karte --}}
                        <div class="flex-1 relative transition-all duration-500 ease-in-out cursor-pointer rounded-2xl p-4 sm:p-5 border
                            {{ $isActive
                                ? 'bg-primary/10 border-primary/40 shadow-[0_0_25px_rgba(197,160,89,0.15)] scale-[1.02] z-20'
                                : 'bg-gray-950/50 border-gray-800 hover:border-gray-600 hover:bg-gray-900 shadow-inner' }}"
                             wire:click="edit('{{ $r->id }}')">

                            @if($isActive)
                                <span class="absolute -top-3 right-4 bg-primary text-gray-900 text-[8px] font-black uppercase tracking-widest px-3 py-1 rounded-full shadow-lg">
                                    In Aktion
                                </span>
                            @endif

                            <div class="flex justify-between items-start gap-4">
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold {{ $isActive ? 'text-white text-base' : 'text-gray-300 text-sm group-hover:text-white' }} transition-colors">
                                        {{ $r->title }}
                                    </h4>

                                    <div class="mt-1.5 overflow-hidden transition-all duration-500 ease-in-out
                                        {{ $isActive ? 'max-h-40' : 'max-h-5 group-hover:max-h-40' }}">
                                        <p class="leading-relaxed {{ $isActive ? 'text-gray-400 text-xs font-medium' : 'text-gray-500 text-[11px]' }}">
                                            {{ $r->message }}
                                        </p>
                                    </div>
                                </div>
                                <div class="shrink-0 text-[9px] font-black px-2 py-1 rounded border
                                    {{ $isActive ? 'bg-gray-900 text-primary border-primary/30' : 'bg-gray-950 border-gray-800 text-gray-600' }} shadow-inner">
                                    {{ $r->duration_minutes }} MIN
                                </div>
                            </div>
                        </div>

                        {{-- Löschen Button --}}
                        <button wire:click="delete('{{ $r->id }}')" wire:confirm="Routine löschen?" class="opacity-0 group-hover:opacity-100 text-gray-600 hover:text-red-500 transition-all duration-300 p-2 shrink-0 transform hover:scale-110">
                            <x-heroicon-m-trash class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            {{-- EDITOR --}}
            <div class="flex-1 flex flex-col animate-fade-in relative z-10">
                <div class="space-y-6 bg-gray-950/50 p-6 rounded-3xl border border-gray-800 shadow-inner">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Uhrzeit Start</label>
                            <input type="time" wire:model="r_time" class="w-full bg-gray-900 border-gray-800 rounded-xl font-bold text-white focus:border-primary focus:ring-primary/20 shadow-inner transition-all [color-scheme:dark]">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Dauer (Minuten)</label>
                            <input type="number" wire:model="r_duration" class="w-full bg-gray-900 border-gray-800 rounded-xl text-sm font-bold text-white focus:border-primary focus:ring-primary/20 shadow-inner transition-all">
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Titel der Routine</label>
                        <input type="text" wire:model="r_title" class="w-full bg-gray-900 border-gray-800 rounded-xl text-sm font-bold text-white focus:border-primary focus:ring-primary/20 shadow-inner transition-all placeholder-gray-700" placeholder="z.B. Deep Work Phase">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Funkis Anweisung</label>
                        <textarea wire:model="r_message" class="w-full bg-gray-900 border-gray-800 rounded-xl text-sm h-32 focus:border-primary focus:ring-primary/20 shadow-inner resize-none leading-relaxed text-white placeholder-gray-700" placeholder="Klare Anweisungen für diesen Zeitblock..."></textarea>
                    </div>
                </div>
                <div class="mt-8 flex gap-4">
                    <button wire:click="cancel" class="flex-1 py-4 text-[10px] font-black uppercase tracking-widest text-gray-500 bg-gray-900 border border-gray-800 rounded-xl hover:bg-gray-800 hover:text-white transition-all shadow-lg">Abbrechen</button>
                    <button wire:click="save" class="flex-1 py-4 text-[10px] font-black uppercase tracking-widest text-gray-900 bg-primary rounded-xl hover:bg-primary-dark transition-all shadow-[0_0_20px_rgba(197,160,89,0.3)]">Speichern</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Custom Scrollbar Style für die Card --}}
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(197, 160, 89, 0.4); }
    </style>
</div>
