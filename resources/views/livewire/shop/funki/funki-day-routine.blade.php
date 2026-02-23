<div>
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 min-h-[600px] flex flex-col relative overflow-hidden">
        {{-- Dekorativer Hintergrund --}}
        <div class="absolute top-0 right-0 w-32 h-full bg-gradient-to-l from-amber-50 to-transparent opacity-50 pointer-events-none"></div>

        <div class="flex justify-between items-center mb-8 relative z-10">
            <div>
                <h3 class="text-2xl font-serif font-bold text-slate-900">Tagesroutine</h3>
                <p class="text-xs font-mono text-slate-400 mt-1 uppercase tracking-tighter">Dein Bio-Rhythmus</p>
            </div>
            <button wire:click="create" class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center hover:bg-primary transition-colors shadow-lg active:scale-95">
                <x-heroicon-m-plus class="w-5 h-5" />
            </button>
        </div>

        {{-- LISTE --}}
        @if(!$isEditing)
            @php
                // Ermittle die aktuell aktive Routine
                $nowFormat = date('H:i');
                $activeId = null;
                $sortedRoutines = $routines->sortBy('start_time')->values();

                foreach ($sortedRoutines as $i => $r) {
                    $start = date('H:i', strtotime($r->start_time));

                    if ($r->duration_minutes > 0) {
                        $end = date('H:i', strtotime($r->start_time . " +{$r->duration_minutes} minutes"));
                    } else {
                        $next = $sortedRoutines[$i + 1] ?? $sortedRoutines[0];
                        $end = date('H:i', strtotime($next->start_time));
                    }

                    // Normale Zeitspanne (z.B. 09:00 - 12:00)
                    if ($start <= $end) {
                        if ($nowFormat >= $start && $nowFormat < $end) {
                            $activeId = $r->id;
                            break;
                        }
                    }
                    // Zeitspanne über Mitternacht (z.B. 22:00 - 09:00)
                    else {
                        if ($nowFormat >= $start || $nowFormat < $end) {
                            $activeId = $r->id;
                            break;
                        }
                    }
                }
            @endphp

            <div class="space-y-5 overflow-y-auto custom-scrollbar pr-4 flex-1 relative z-10 pt-2 pb-6">
                @foreach($sortedRoutines as $r)
                    @php
                        $isActive = $r->id === $activeId;
                    @endphp

                    <div class="flex items-center gap-4 group">
                        {{-- Uhrzeit --}}
                        <div class="w-12 text-right font-mono text-xs font-bold {{ $isActive ? 'text-primary' : 'text-slate-400' }}">
                            {{ \Carbon\Carbon::parse($r->start_time)->format('H:i') }}
                        </div>

                        {{-- Timeline Dot --}}
                        <div class="relative flex items-center justify-center">
                            @if($isActive)
                                <span class="absolute inline-flex h-6 w-6 rounded-full bg-primary opacity-30 animate-ping"></span>
                                <div class="w-3.5 h-3.5 rounded-full bg-primary ring-4 ring-primary/20 shadow-[0_0_10px_rgba(197,160,89,0.6)] z-10"></div>
                            @else
                                <div class="w-3 h-3 rounded-full border-2 border-slate-200 bg-white group-hover:border-primary group-hover:scale-125 transition-all z-10"></div>
                            @endif
                            {{-- Verbindungslinie --}}
                            @if(!$loop->last)
                                <div class="absolute top-3 w-px h-16 bg-slate-100 z-0"></div>
                            @endif
                        </div>

                        {{-- Routine Karte --}}
                        <div class="flex-1 relative transition-all duration-300 cursor-pointer
                            {{ $isActive
                                ? 'bg-primary/5 border-primary ring-1 ring-primary/30 shadow-lg scale-[1.02] z-20 rounded-2xl p-5'
                                : 'bg-slate-50 border-slate-100 rounded-2xl p-4 hover:bg-white hover:shadow-md hover:border-primary/30' }}"
                             wire:click="edit('{{ $r->id }}')">

                            @if($isActive)
                                <span class="absolute -top-3 right-4 bg-primary text-white text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full shadow-md">
                                    Jetzt aktiv
                                </span>
                            @endif

                            <div class="flex justify-between items-start gap-4">
                                <div class="flex-1">
                                    <h4 class="font-bold {{ $isActive ? 'text-primary-dark text-base' : 'text-slate-800 text-sm' }}">
                                        {{ $r->title }}
                                    </h4>
                                    <p class="mt-1.5 leading-relaxed {{ $isActive ? 'text-slate-700 text-xs font-medium' : 'text-slate-500 text-xs line-clamp-1 group-hover:line-clamp-none transition-all' }}">
                                        {{ $r->message }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-[10px] font-black px-2 py-1 rounded border
                                    {{ $isActive ? 'bg-white text-primary border-primary/20 shadow-sm' : 'bg-white border-slate-100 text-slate-400' }}">
                                    {{ $r->duration_minutes }} min
                                </div>
                            </div>
                        </div>

                        {{-- Löschen Button --}}
                        <button wire:click="delete('{{ $r->id }}')" wire:confirm="Routine löschen?" class="opacity-0 group-hover:opacity-100 text-slate-300 hover:text-red-500 transition-opacity p-2 shrink-0">
                            <x-heroicon-m-trash class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            {{-- EDITOR --}}
            <div class="flex-1 flex flex-col animate-fade-in relative z-10">
                <div class="space-y-5 bg-slate-50 p-6 rounded-2xl border border-slate-100">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-1 block">Uhrzeit Start</label>
                            <input type="time" wire:model="r_time" class="w-full bg-white border-none rounded-xl font-bold text-slate-800 focus:ring-primary shadow-sm">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-1 block">Dauer (Minuten)</label>
                            <input type="number" wire:model="r_duration" class="w-full bg-white border-none rounded-xl text-sm font-bold focus:ring-primary shadow-sm">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-1 block">Titel der Routine</label>
                        <input type="text" wire:model="r_title" class="w-full bg-white border-none rounded-xl text-sm font-bold focus:ring-primary shadow-sm" placeholder="z.B. Deep Work Phase">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-1 block">Funkis Anweisung / Motivation</label>
                        <textarea wire:model="r_message" class="w-full bg-white border-none rounded-xl text-sm h-32 focus:ring-primary shadow-sm resize-none leading-relaxed" placeholder="Klare Anweisungen für diesen Zeitblock..."></textarea>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button wire:click="cancel" class="flex-1 py-3.5 text-xs font-bold uppercase tracking-widest text-slate-500 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Abbrechen</button>
                    <button wire:click="save" class="flex-1 py-3.5 text-xs font-bold uppercase tracking-widest text-white bg-slate-900 rounded-xl hover:bg-primary shadow-lg transition-colors">Speichern</button>
                </div>
            </div>
        @endif
    </div>
</div>
