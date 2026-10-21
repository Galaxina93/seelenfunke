<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 p-6 sm:p-8 min-h-[600px] flex flex-col relative overflow-hidden transition-all duration-500">
        {{-- Dekorativer Hintergrund (Gold-Glow passend zum Projekt) --}}
        <div class="absolute top-0 right-0 w-48 h-full bg-gradient-to-l from-[var(--theme-color)]/5 to-transparent opacity-50 pointer-events-none"></div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 relative z-10">
            <div>
                <h3 class="text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Tagesroutine</h3>
                <p class="text-[10px] font-black text-gray-500 mt-1 uppercase tracking-[0.2em]">Dein Bio-Rhythmus</p>
            </div>
            <div class="flex items-center gap-3">
                @if(!$isEditing)
                    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-semibold">
                        <button wire:click="$set('filterDays', 'all')" class="px-3 py-1.5 rounded-lg transition-all {{ $filterDays === 'all' ? 'bg-[var(--theme-color)] text-gray-900 font-bold shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">Alle</button>
                        <button wire:click="$set('filterDays', 'weekdays')" class="px-3 py-1.5 rounded-lg transition-all {{ $filterDays === 'weekdays' ? 'bg-[var(--theme-color)] text-gray-900 font-bold shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">Werktags</button>
                        <button wire:click="$set('filterDays', 'weekend')" class="px-3 py-1.5 rounded-lg transition-all {{ $filterDays === 'weekend' ? 'bg-[var(--theme-color)] text-gray-900 font-bold shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">Wochenende</button>
                    </div>
                @endif
                <button wire:click="create" class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-950 text-gray-700 dark:text-white flex items-center justify-center border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-all dark:shadow-inner active:scale-95 group" title="Neue Routine erstellen">
                    <x-heroicon-m-plus class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
                </button>
            </div>
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

            <div class="space-y-4 overflow-y-auto custom-scrollbar pr-4 flex-1 relative z-10 pt-2 pb-6">
                @forelse($sortedRoutines as $r)
                    @php $isActive = $r->id === $activeId; @endphp

                    <div class="flex items-stretch gap-4 group">
                        {{-- Uhrzeit --}}
                        <div class="w-12 text-right font-mono text-xs font-bold pt-4 shrink-0 {{ $isActive ? 'text-[var(--theme-color)] dark:drop-shadow-[0_0_8px_var(--theme-color-50)]' : 'text-gray-500 dark:text-gray-600' }}">
                            {{ \Carbon\Carbon::parse($r->start_time)->format('H:i') }}
                        </div>

                        {{-- Timeline Continuous Track & Dot --}}
                        <div class="relative flex flex-col items-center justify-start shrink-0 w-4 self-stretch pt-4">
                            @if(!$loop->first)
                                <div class="absolute top-0 {{ $loop->last ? 'h-4' : 'bottom-0' }} w-0.5 bg-gray-200 dark:bg-gray-800 -translate-x-1/2 left-1/2 z-0"></div>
                            @elseif(!$loop->last)
                                <div class="absolute top-4 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-800 -translate-x-1/2 left-1/2 z-0"></div>
                            @endif

                            @if($isActive)
                                <span class="absolute top-4 inline-flex h-6 w-6 rounded-full bg-[var(--theme-color)] opacity-20 animate-ping"></span>
                                <div class="w-3.5 h-3.5 rounded-full bg-[var(--theme-color)] ring-4 ring-[var(--theme-color-20)] dark:shadow-[0_0_15px_var(--theme-color)] z-10"></div>
                            @else
                                <div class="w-3 h-3 rounded-full border-2 {{ $r->is_active ? 'border-gray-400 dark:border-gray-600 bg-white dark:bg-gray-950' : 'border-gray-300 dark:border-gray-800 bg-gray-200 dark:bg-gray-900 opacity-40' }} group-hover:border-[var(--theme-color-50)] group-hover:scale-125 transition-all duration-300 z-10"></div>
                            @endif
                        </div>

                        {{-- Routine Karte --}}
                        <div class="flex-1 relative transition-all duration-300 ease-in-out cursor-pointer rounded-2xl p-4 sm:p-5 border min-h-[100px] flex flex-col justify-center
                            {{ !$r->is_active ? 'opacity-60 bg-gray-50/50 dark:bg-gray-950/30 border-gray-200 dark:border-gray-800/60' : ($isActive
                                ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] dark:shadow-[0_0_25px_var(--theme-color-15)] scale-[1.01] z-20'
                                : 'bg-gray-50 dark:bg-gray-950/50 border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-900 dark:shadow-inner') }}"
                             wire:click="edit('{{ $r->id }}')">

                            @if($isActive)
                                <span class="absolute -top-3 right-4 bg-[var(--theme-color)] text-gray-900 text-[8px] font-black uppercase tracking-widest px-3 py-1 rounded-full dark:shadow-lg">
                                    In Aktion
                                </span>
                            @endif

                            <div class="flex justify-between items-start gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="font-bold {{ $isActive ? 'text-gray-900 dark:text-white text-base' : 'text-gray-700 dark:text-gray-300 text-sm group-hover:text-gray-900 dark:group-hover:text-white' }} transition-colors">
                                            {{ $r->title }}
                                        </h4>
                                        @if($r->days_of_week && $r->days_of_week !== 'all')
                                            <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                                {{ $r->days_of_week === 'weekdays' ? 'Mo - Fr' : 'Sa - So' }}
                                            </span>
                                        @endif
                                        @if(!$r->is_active)
                                            <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-800 text-gray-500">Pausiert</span>
                                        @endif
                                    </div>

                                    <div class="mt-1.5">
                                        <p class="leading-relaxed {{ $isActive ? 'text-gray-700 dark:text-gray-300 text-xs font-medium' : 'text-gray-500 dark:text-gray-400 text-xs' }}">
                                            {{ $r->message }}
                                        </p>
                                    </div>
                                </div>
                                <div class="shrink-0 text-[9px] font-black px-2.5 py-1 rounded border
                                    {{ $isActive ? 'bg-white dark:bg-gray-900 text-[var(--theme-color)] border-[var(--theme-color-30)]' : 'bg-gray-100 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }} dark:shadow-inner">
                                    {{ $r->duration_minutes }} MIN
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity self-center shrink-0">
                            <button wire:click="toggleActive('{{ $r->id }}')" class="p-2 text-gray-400 hover:text-[var(--theme-color)] transition-colors" title="{{ $r->is_active ? 'Routine pausieren' : 'Routine aktivieren' }}">
                                @if($r->is_active)
                                    <x-heroicon-m-pause class="w-4 h-4" />
                                @else
                                    <x-heroicon-m-play class="w-4 h-4" />
                                @endif
                            </button>
                            <button wire:click="delete('{{ $r->id }}')" wire:confirm="Routine löschen?" class="text-gray-400 hover:text-red-500 transition-all p-2 transform hover:scale-110" title="Löschen">
                                <x-heroicon-m-trash class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-400 dark:text-gray-600">
                        <x-heroicon-o-clock class="w-12 h-12 mx-auto mb-3 opacity-40" />
                        <p class="text-xs uppercase tracking-widest font-black">Keine Routinen für diesen Filter vorhanden</p>
                    </div>
                @endforelse
            </div>
        @else
            {{-- EDITOR --}}
            <div class="flex-1 flex flex-col animate-fade-in relative z-10">
                <div class="space-y-6 bg-gray-50 dark:bg-gray-950/50 p-6 rounded-3xl border border-gray-200 dark:border-gray-800 dark:shadow-inner">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Uhrzeit Start</label>
                            <input type="time" wire:model="r_time" class="w-full bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 rounded-xl font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-[var(--theme-color-20)] dark:shadow-inner transition-all">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Dauer (Minuten)</label>
                            <input type="number" wire:model="r_duration" class="w-full bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 rounded-xl text-sm font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-[var(--theme-color-20)] dark:shadow-inner transition-all">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Typ der Routine</label>
                            <select wire:model="r_type" class="w-full bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 rounded-xl text-sm font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-[var(--theme-color-20)] dark:shadow-inner transition-all">
                                <option value="general">Allgemeine Arbeit (General)</option>
                                <option value="sleep">Schlafenszeit / Nachtruhe (Sleep)</option>
                                <option value="break">Pause / Entspannung (Break)</option>
                                <option value="food">Essen / Trinken (Food)</option>
                                <option value="sport">Sport / Bewegung (Sport)</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Wochentage</label>
                            <select wire:model="r_days_of_week" class="w-full bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 rounded-xl text-sm font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-[var(--theme-color-20)] dark:shadow-inner transition-all">
                                <option value="all">Täglich (Mo - So)</option>
                                <option value="weekdays">Nur Werktags (Mo - Fr)</option>
                                <option value="weekend">Nur Wochenende (Sa - So)</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Titel der Routine</label>
                        <input type="text" wire:model="r_title" class="w-full bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 rounded-xl text-sm font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-[var(--theme-color-20)] dark:shadow-inner transition-all placeholder-gray-400 dark:placeholder-gray-700" placeholder="z.B. Deep Work Phase">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">Funkis Anweisung</label>
                        <textarea wire:model="r_message" class="w-full bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 rounded-xl text-sm h-32 focus:border-[var(--theme-color)] focus:ring-[var(--theme-color-20)] dark:shadow-inner resize-none leading-relaxed text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-700" placeholder="Klare Anweisungen für diesen Zeitblock..."></textarea>
                    </div>
                </div>
                <div class="mt-8 flex gap-4">
                    <button wire:click="cancel" class="flex-1 py-4 text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-all dark:shadow-lg">Abbrechen</button>
                    <button wire:click="save" class="flex-1 py-4 text-[10px] font-black uppercase tracking-widest text-gray-900 bg-[var(--theme-color)] rounded-xl hover:brightness-110 transition-all dark:shadow-[0_0_20px_var(--theme-color-30)]">Speichern</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Custom Scrollbar Style für die Card --}}
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--theme-color-40); }
    </style>
</div>
