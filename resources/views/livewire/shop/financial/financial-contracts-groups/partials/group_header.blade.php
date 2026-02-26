<div
    wire:click="toggleGroup('{{ $group->id }}')"
    class="p-5 sm:p-6 flex justify-between items-center cursor-pointer hover:bg-gray-800/30 transition-colors duration-300 group/header"
>
    {{-- Linker Bereich: Name (Editable) & Status --}}
    <div class="flex items-center gap-4 sm:gap-5 flex-1">
        <div class="w-1.5 h-10 rounded-full shrink-0 {{ $group->type === 'income' ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.8)]' : 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.8)]' }}"></div>
        <div class="flex-1 min-w-0">
            @if($editingGroupId === $group->id)
                <div class="flex items-center gap-2 sm:gap-3" @click.stop>
                    <input type="text" wire:model="tempGroupName" class="text-sm font-bold border border-gray-700 bg-gray-950 text-white rounded-xl px-3 py-2 w-full max-w-[200px] focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none shadow-inner" wire:keydown.enter="updateGroup">
                    <button wire:click="updateGroup" class="p-2 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg hover:bg-emerald-500 hover:text-gray-900 transition-all shadow-inner" title="Speichern">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </button>
                    <button wire:click="cancelEditGroup" class="p-2 bg-gray-900 text-gray-400 border border-gray-700 rounded-lg hover:text-white hover:bg-gray-800 transition-all shadow-inner" title="Abbrechen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            @else
                <span class="font-serif font-bold text-lg sm:text-xl text-white tracking-wide cursor-pointer group-hover/header:text-primary transition-colors flex items-center gap-3 group/edit" wire:click.stop="editGroup('{{ $group->id }}')">
                    <span class="truncate">{{ $group->name }}</span>
                    <svg class="w-4 h-4 text-gray-600 opacity-0 group-hover/edit:opacity-100 hover:text-primary transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </span>
            @endif
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-1 block">
                {{ $group->items->count() }} Positionen
            </span>
        </div>
    </div>

    {{-- Rechter Bereich: Summen & Löschen & Toggle --}}
    <div class="flex items-center gap-4 sm:gap-6 shrink-0">
        <div class="text-right hidden sm:block">
            <div class="text-base font-mono font-bold tracking-tight {{ $group->type === 'income' ? 'text-emerald-400 drop-shadow-[0_0_8px_currentColor]' : 'text-red-400 drop-shadow-[0_0_8px_currentColor]' }}">
                {{ number_format($groupMonthly, 2, ',', '.') }} € <span class="text-[10px] text-gray-500 uppercase tracking-widest font-black drop-shadow-none">/ mtl.</span>
            </div>
            <div class="text-[10px] font-black text-gray-600 uppercase tracking-widest mt-0.5">
                {{ number_format($groupYearly, 2, ',', '.') }} € / Jahr
            </div>
        </div>

        <div class="flex items-center gap-1 sm:gap-2">
            {{-- Delete Button (Icon im Header) --}}
            <button
                wire:click.stop="deleteGroup('{{ $group->id }}')"
                wire:confirm="Möchten Sie die Gruppe '{{ $group->name }}' wirklich löschen? Dies ist nur möglich, wenn keine Verträge mehr enthalten sind."
                class="p-2 text-gray-600 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-all"
                title="Gruppe löschen"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>

            {{-- Toggle Icon --}}
            <div class="text-gray-500 group-hover/header:text-white transition-colors p-2 cursor-pointer bg-gray-900 rounded-full border border-gray-800 shadow-inner group-hover/header:border-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transform transition-transform duration-300 {{ $activeGroupId === $group->id ? 'rotate-180 text-primary' : '' }}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>
    </div>
</div>
