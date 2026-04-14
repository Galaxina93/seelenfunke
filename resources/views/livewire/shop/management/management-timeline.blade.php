<div style="--theme-color: var(--theme-color); --theme-color-5: var(--theme-color)0D; --theme-color-10: var(--theme-color)1A; --theme-color-20: var(--theme-color)33; --theme-color-50: var(--theme-color)80;" class="p-6 md:p-10 min-h-[calc(100vh-6rem)]">
    {{-- HAUPT-HEADER --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6 relative z-20">
        <div>
             <h2 class="text-3xl lg:text-4xl font-serif font-black text-white tracking-widest drop-shadow-[0_0_15px_rgba(255,255,255,0.2)]">MANAGEMENT ZEITACHSE</h2>
             <div class="w-24 h-1 bg-[var(--theme-color)] rounded-full mt-2 shadow-[0_0_10px_var(--theme-color-50)]"></div>
             <p class="text-[10px] font-black uppercase text-gray-500 tracking-[0.3em] mt-3">Strategische Vorausschau & Lebensereignisse</p>
        </div>

        <button wire:click="create" class="h-14 px-8 rounded-[1.5rem] bg-gray-900 border border-gray-800 text-xs font-black tracking-widest uppercase text-white hover:text-[var(--theme-color)] hover:border-[var(--theme-color-50)] transition-all shadow-inner flex items-center justify-center gap-3">
            <x-heroicon-m-plus class="w-5 h-5"/>
            <span>Neues Ereignis (Meilenstein / Roadblock)</span>
        </button>
    </div>

    {{-- ZEITACHSE RENDER --}}
    <div class="relative mt-12 max-w-5xl mx-auto pl-8 sm:pl-16">
        {{-- Vertikaler Strich --}}
        <div class="absolute left-0 top-4 bottom-0 w-1 bg-gradient-to-b from-[var(--theme-color-50)] via-gray-800 to-transparent rounded-full drop-shadow-[0_0_5px_var(--theme-color-50)]"></div>

        <div class="space-y-12 pb-20">
            @forelse($events as $event)
                @php
                    // Styling logic based on type and impact
                    $border = 'border-gray-800';
                    $dot = 'bg-gray-500';
                    $bg = 'bg-gray-900/50';
                    $glow = '';
                    $icon = 'heroicon-o-calendar';

                    if ($event->type === 'roadblock') {
                        $border = 'border-red-500/30';
                        $dot = 'bg-red-500';
                        $bg = 'bg-red-950/20';
                        $glow = 'shadow-[0_0_30px_rgba(239,68,68,0.2)]';
                        $icon = 'heroicon-o-exclamation-triangle';
                    } elseif ($event->type === 'milestone') {
                        $border = 'border-[var(--theme-color-50)]';
                        $dot = 'bg-[var(--theme-color)]';
                        $bg = 'bg-[var(--theme-color-5)]';
                        $glow = 'shadow-[0_0_30px_var(--theme-color-30)]';
                        $icon = 'heroicon-o-flag';
                    } elseif ($event->type === 'launch') {
                        $border = 'border-emerald-500/50';
                        $dot = 'bg-emerald-500';
                        $bg = 'bg-emerald-950/20';
                        $glow = 'shadow-[0_0_30px_rgba(16,185,129,0.2)]';
                        $icon = 'heroicon-o-rocket-launch';
                    }

                    $isActive = $event->start_date <= now() && ($event->end_date === null || $event->end_date >= now()->startOfDay());
                    $isPast = $event->end_date !== null && $event->end_date < now()->startOfDay();
                    if ($isPast) {
                        $bg = 'bg-gray-900/20 opacity-60';
                        $border = 'border-gray-900';
                        $glow = '';
                    }
                @endphp

                <div class="relative group cursor-pointer" wire:click="edit('{{ $event->id }}')">
                    {{-- Punkt auf der Achse --}}
                    <div class="absolute -left-[35px] sm:-left-[67px] top-6 w-4 h-4 rounded-full {{ $dot }} {{ $isActive ? 'animate-pulse drop-shadow-[0_0_8px_currentColor]' : '' }} border-4 border-[#0a0a0a] transition-all transform group-hover:scale-150"></div>

                    {{-- Event Card --}}
                    <div class="rounded-[2.5rem] border {{ $border }} {{ $bg }} {{ $glow }} p-6 md:p-8 transition-all hover:bg-gray-800/80 group-hover:border-[var(--theme-color)]">
                        <div class="flex flex-col sm:flex-row sm:justify-between items-start gap-4">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <x-dynamic-component :component="$icon" class="w-5 h-5 text-gray-400 group-hover:text-[var(--theme-color)] transition-colors" />
                                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">
                                        {{ $event->type }} • Impact: {{ $event->impact_level }}
                                        @if($isActive) <span class="text-emerald-500 ml-2 animate-pulse">[ AKTIV ]</span> @endif
                                        @if($isPast) <span class="text-gray-600 ml-2">[ VERGANGEN ]</span> @endif
                                    </span>
                                </div>
                                <h3 class="text-2xl font-serif font-black text-white group-hover:text-[var(--theme-color)] transition-colors">{{ $event->title }}</h3>
                                
                                <div class="flex flex-wrap items-center gap-2 mt-4 text-[10px] font-bold tracking-wider uppercase text-gray-400">
                                    <div class="bg-gray-950 border border-gray-800 rounded-lg px-3 py-1 flex items-center gap-2">
                                        <x-heroicon-m-calendar class="w-3 h-3 text-[var(--theme-color)]"/>
                                        <span>Start: {{ $event->start_date->format('d.m.Y') }}</span>
                                    </div>
                                    @if($event->end_date)
                                      <x-heroicon-m-arrow-right class="w-3 h-3 text-gray-600"/>
                                      <div class="bg-gray-950 border border-gray-800 rounded-lg px-3 py-1 flex items-center gap-2">
                                          <x-heroicon-m-flag class="w-3 h-3 text-red-400"/>
                                          <span>Ende: {{ $event->end_date->format('d.m.Y') }}</span>
                                      </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($event->description)
                            <div class="mt-6 text-sm text-gray-400 leading-relaxed border-t border-gray-800/50 pt-4">
                                {{ $event->description }}
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-20 bg-gray-900/40 rounded-[3rem] border border-dashed border-gray-800">
                    <x-heroicon-o-clock class="w-12 h-12 text-gray-700 mx-auto mb-4" />
                    <p class="text-sm text-gray-500 font-medium italic">Die Zeitachse ist noch leer. Definiere deine Meilensteine.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- MODAL --}}
    @if($showModal)
        <div class="fixed inset-0 z-[9999] flex items-end md:items-center justify-center p-0 md:p-4 md:py-8 bg-black/80 backdrop-blur-md animate-fade-in">
            <div class="bg-gray-900 w-full md:max-w-3xl max-h-[calc(100dvh-6rem)] md:h-auto md:max-h-full rounded-t-[2.5rem] md:rounded-[3rem] shadow-[0_0_100px_rgba(0,0,0,1)] flex flex-col transform animate-modal-up border-t md:border border-gray-800">

                {{-- Header --}}
                <div class="bg-gray-950/80 px-5 py-5 md:px-8 md:py-6 border-b border-gray-800 flex justify-between items-center shrink-0 shadow-inner">
                    <div class="flex-1 min-w-0 pr-4">
                        <h3 class="text-xl md:text-2xl font-serif font-bold text-white tracking-tight truncate">{{ $editingId ? 'Ereignis bearbeiten' : 'Neues Ereignis' }}</h3>
                        <p class="text-[8px] md:text-[10px] font-black uppercase text-[var(--theme-color)] tracking-wider md:tracking-[0.3em] mt-1 truncate">Status: {{ $editingId ? 'In Bearbeitung' : 'Neu erstellen' }}</p>
                    </div>
                    <button wire:click="$set('showModal', false)" class="shrink-0 w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-gray-900 border border-gray-700 rounded-xl md:rounded-2xl text-gray-500 hover:text-white hover:bg-red-500/20 hover:border-red-500 transition-all shadow-inner">
                        <x-heroicon-m-x-mark class="w-5 h-5 md:w-6 md:h-6" />
                    </button>
                </div>

                {{-- Formular --}}
                <div class="flex-1 overflow-y-auto custom-scrollbar p-5 md:p-8 space-y-6 md:space-y-8 pb-32 md:pb-10 bg-gray-950/30">
                    <div class="space-y-2">
                        <label class="label-xs">Titel</label>
                        <input type="text" wire:model="title" class="input-dark text-base md:text-lg" placeholder="z.B. Gewerbeanmeldung">
                        @error('title') <span class="text-red-400 text-[9px] font-black uppercase ml-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                        <div class="space-y-2">
                            <label class="label-xs">Typ</label>
                            <div class="relative group">
                                <select wire:model="type" class="input-dark appearance-none pr-10 cursor-pointer text-sm">
                                    <option value="event" class="bg-gray-900">Meldung / Normal</option>
                                    <option value="milestone" class="bg-gray-900">Meilenstein (Ziel)</option>
                                    <option value="roadblock" class="bg-gray-900">Roadblock (Ausfall / OP)</option>
                                    <option value="phase" class="bg-gray-900">Phase (Langfristig)</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-600 group-focus-within:text-[var(--theme-color)]"><x-heroicon-m-chevron-down class="w-4 h-4"/></div>
                            </div>
                            @error('type') <span class="text-red-400 text-[9px] font-black uppercase ml-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="space-y-2">
                            <label class="label-xs">Impact (Einfluss auf Routine)</label>
                            <div class="relative group">
                                <select wire:model="impact_level" class="input-dark appearance-none pr-10 cursor-pointer text-sm">
                                    <option value="low" class="bg-gray-900">Niedrig (Läuft nebenbei)</option>
                                    <option value="medium" class="bg-gray-900">Mittel (Wichtig)</option>
                                    <option value="high" class="bg-gray-900">Hoch (Unterbricht/diktiert Alltag)</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-600 group-focus-within:text-[var(--theme-color)]"><x-heroicon-m-chevron-down class="w-4 h-4"/></div>
                            </div>
                            @error('impact_level') <span class="text-red-400 text-[9px] font-black uppercase ml-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Zeitstrahl --}}
                    <div class="bg-gray-900 rounded-[1.5rem] md:rounded-[2rem] border border-gray-800 p-5 md:p-6 space-y-5 md:space-y-6 shadow-inner relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-[var(--theme-color-5)] rounded-bl-full blur-2xl pointer-events-none"></div>
                        <div class="flex flex-col border-b border-gray-800 pb-4 relative z-10 gap-1">
                            <span class="text-[10px] font-black uppercase text-gray-500 tracking-[0.2em]">Zeitraum</span>
                            <span class="text-[9px] text-gray-600">Offenes Ende: Zweites Feld leer lassen.</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 md:gap-8 relative z-10">
                            <div class="space-y-3">
                                <span class="text-[9px] font-black text-[var(--theme-color)] uppercase ml-1 tracking-widest drop-shadow-[0_0_5px_currentColor]">Beginn</span>
                                <input type="date" wire:model="start_date" class="input-dark-sm [color-scheme:dark] w-full text-xs">
                                @error('start_date') <span class="text-red-400 text-[9px] font-black uppercase ml-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="space-y-3">
                                <span class="text-[9px] font-black text-[var(--theme-color)] uppercase ml-1 tracking-widest drop-shadow-[0_0_5px_currentColor]">Ende</span>
                                <input type="date" wire:model="end_date" class="input-dark-sm [color-scheme:dark] w-full text-xs">
                                @error('end_date') <span class="text-red-400 text-[9px] font-black uppercase ml-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="label-xs">Details / Instruktion für KI</label>
                        <textarea wire:model="description" rows="3" class="input-dark resize-none text-sm placeholder:italic placeholder-gray-600" placeholder="z.B. Ich falle 4 Wochen komplett aus. Alle Marketing-Tasks pausieren."></textarea>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="p-5 md:p-10 bg-gray-950/80 border-t border-gray-800 flex flex-col-reverse sm:flex-row justify-between items-stretch sm:items-center gap-3 sm:gap-4 shrink-0 shadow-2xl">
                    @if($editingId)
                        <button wire:click="delete" wire:confirm="Soll dieses Event wirklich gelöscht werden?" class="w-full sm:w-auto h-12 md:h-14 px-6 rounded-xl md:rounded-2xl text-[10px] font-black uppercase tracking-widest text-red-500 hover:bg-red-500/10 transition-all border border-red-900/30 bg-gray-900 shadow-inner flex items-center justify-center gap-3 group">
                            <x-heroicon-o-trash class="w-5 h-5 transition-transform group-hover:scale-110" />
                            <span>Löschen</span>
                        </button>
                    @else
                        <div class="hidden sm:block"></div>
                    @endif
                    <div class="flex flex-col-reverse sm:flex-row gap-3 sm:gap-4 flex-1 sm:justify-end">
                        <button wire:click="$set('showModal', false)" class="w-full sm:w-auto h-12 md:h-14 px-8 rounded-xl md:rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-400 bg-gray-900 border border-gray-800 hover:text-white transition-all shadow-inner justify-center items-center flex">
                            Abbrechen
                        </button>
                        <button wire:click="save" class="w-full sm:w-auto h-12 md:h-14 px-10 rounded-xl md:rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-950 bg-[var(--theme-color)] hover:bg-white shadow-[0_0_30px_var(--theme-color-40)] transition-all active:scale-95 flex items-center justify-center gap-3">
                            <span wire:loading.remove wire:target="save">Speichern</span>
                            <span wire:loading wire:target="save" class="animate-pulse italic">Speichert...</span>
                            <x-heroicon-m-check class="w-5 h-5 stroke-2" />
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

    {{-- STYLES --}}
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .label-xs { display: block; font-size: 9px; font-weight: 900; color: #6b7280; text-transform: uppercase; letter-spacing: 0.25em; margin-bottom: 8px; margin-left: 4px; }

        /* Zentrale Styling-Klasse für Dark-Inputs */
        .input-dark {
            width: 100%;
            background-color: #030712; /* gray-950 */
            border: 1px solid #1f2937; /* gray-800 */
            border-radius: 1.25rem;
            padding: 1rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.5);
            outline: none;
        }
        .input-dark:focus {
            border-color: var(--theme-color);
            box-shadow: 0 0 20px var(--theme-color-10), inset 0 2px 4px rgba(0,0,0,0.5);
        }

        .input-dark-sm {
            width: 100%;
            background-color: #030712;
            border: 1px solid #1f2937;
            border-radius: 1rem;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 800;
            color: white;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5);
            outline: none;
            transition: border-color 0.3s ease;
        }
        .input-dark-sm:focus {
            border-color: var(--theme-color);
        }

        .shadow-glow { box-shadow: 0 0 15px var(--theme-color-30); }

        @media (max-width: 768px) {
            .animate-modal-up { animation: modal-slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
            @keyframes modal-slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
        }

        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--theme-color); }
    </style>
</div>
