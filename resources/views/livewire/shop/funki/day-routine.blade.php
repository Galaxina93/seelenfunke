<div>
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 min-h-[600px] flex flex-col relative overflow-hidden">
        {{-- Dekorativer Hintergrund --}}
        <div class="absolute top-0 right-0 w-32 h-full bg-gradient-to-l from-amber-50 to-transparent opacity-50 pointer-events-none"></div>

        <div class="flex justify-between items-center mb-8 relative z-10">
            <div>
                <h3 class="text-2xl font-serif font-bold text-slate-900">Tagesroutine</h3>
                <p class="text-xs font-mono text-slate-400 mt-1 uppercase tracking-tighter">Dein Bio-Rhythmus</p>
            </div>
            <button wire:click="create" class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center hover:bg-primary transition-colors shadow-lg">
                <x-heroicon-m-plus class="w-5 h-5" />
            </button>
        </div>

        {{-- LISTE --}}
        @if(!$isEditing)
            <div class="space-y-4 overflow-y-auto custom-scrollbar pr-2 flex-1 relative z-10">
                @foreach($routines as $r)
                    <div class="flex items-center gap-4 group">
                        <div class="w-16 text-right font-mono text-xs font-bold text-slate-400">
                            {{ \Carbon\Carbon::parse($r->start_time)->format('H:i') }}
                        </div>

                        {{-- Timeline Dot --}}
                        <div class="w-3 h-3 rounded-full border-2 border-slate-200 bg-white group-hover:border-primary group-hover:scale-125 transition-all"></div>

                        <div class="flex-1 bg-slate-50 border border-slate-100 rounded-2xl p-4 hover:bg-white hover:shadow-md transition-all cursor-pointer group-hover:border-primary/20"
                             wire:click="edit('{{ $r->id }}')">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-slate-800 text-sm">{{ $r->title }}</h4>
                                    <p class="text-xs text-slate-500 mt-1 line-clamp-1">{{ $r->message }}</p>
                                </div>
                                <div class="text-[10px] font-black bg-white px-2 py-1 rounded border border-slate-100 text-slate-400">
                                    {{ $r->duration_minutes }} min
                                </div>
                            </div>
                        </div>

                        <button wire:click="delete('{{ $r->id }}')" class="opacity-0 group-hover:opacity-100 text-slate-300 hover:text-red-500 transition-opacity p-2">
                            <x-heroicon-m-trash class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            {{-- EDITOR --}}
            <div class="flex-1 flex flex-col animate-fade-in relative z-10">
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Uhrzeit</label>
                        <input type="time" wire:model="r_time" class="w-full bg-slate-50 border-none rounded-xl font-bold text-slate-800 focus:ring-primary">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Titel</label>
                        <input type="text" wire:model="r_title" class="w-full bg-slate-50 border-none rounded-xl text-sm focus:ring-primary" placeholder="z.B. Mittagessen">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Dauer (Min)</label>
                        <input type="number" wire:model="r_duration" class="w-full bg-slate-50 border-none rounded-xl text-sm focus:ring-primary">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Funkis Tipp (Nachricht)</label>
                        <textarea wire:model="r_message" class="w-full bg-slate-50 border-none rounded-xl text-sm h-24 focus:ring-primary" placeholder="Was soll Funki sagen?"></textarea>
                    </div>
                </div>
                <div class="mt-auto pt-4 flex gap-3">
                    <button wire:click="cancel" class="flex-1 py-3 text-xs font-bold uppercase text-slate-400 bg-slate-50 rounded-xl hover:bg-slate-100">Abbrechen</button>
                    <button wire:click="save" class="flex-1 py-3 text-xs font-bold uppercase text-white bg-slate-900 rounded-xl hover:bg-primary shadow-lg">Speichern</button>
                </div>
            </div>
        @endif
    </div>
</div>
