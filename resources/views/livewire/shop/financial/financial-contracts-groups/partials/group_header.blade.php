<div
    wire:click="toggleGroup('{{ $group->id }}')"
    class="p-4 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors"
>
    {{-- Linker Bereich: Name (Editable) & Status --}}
    <div class="flex items-center gap-4 flex-1">
        <div class="w-1.5 h-8 rounded-full {{ $group->type === 'income' ? 'bg-emerald-400' : 'bg-rose-400' }}"></div>
        <div class="flex-1">
            @if($editingGroupId === $group->id)
                <div class="flex items-center gap-2" @click.stop>
                    <input type="text" wire:model="tempGroupName" class="text-sm border-gray-300 rounded px-2 py-1 w-full max-w-[150px]" wire:keydown.enter="updateGroup">
                    <button wire:click="updateGroup" class="text-green-600 hover:text-green-800"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></button>
                    <button wire:click="cancelEditGroup" class="text-gray-400 hover:text-gray-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
            @else
                <span class="font-bold text-gray-700 block cursor-pointer hover:text-blue-600 flex items-center gap-2 group" wire:click.stop="editGroup('{{ $group->id }}')">
                                            {{ $group->name }}
                                            <svg class="w-3 h-3 text-gray-300 opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </span>
            @endif
            <span class="text-xs text-gray-400">
                                        {{ $group->items->count() }} Positionen
                                    </span>
        </div>
    </div>

    {{-- Rechter Bereich: Summen & Löschen & Toggle --}}
    <div class="flex items-center gap-6">
        <div class="text-right hidden sm:block">
            <div class="text-sm font-bold {{ $group->type === 'income' ? 'text-emerald-600' : 'text-rose-500' }}">
                {{ number_format($groupMonthly, 2, ',', '.') }} € / mtl.
            </div>
            <div class="text-xs text-gray-400">
                {{ number_format($groupYearly, 2, ',', '.') }} € / Jahr
            </div>
        </div>

        {{-- Delete Button (Icon im Header) --}}
        <button
            wire:click.stop="deleteGroup('{{ $group->id }}')"
            wire:confirm="Möchten Sie die Gruppe '{{ $group->name }}' wirklich löschen? Dies ist nur möglich, wenn keine Verträge mehr enthalten sind."
            class="text-gray-300 hover:text-red-500 transition p-1"
            title="Gruppe löschen"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </button>

        {{-- Toggle Icon --}}
        <div class="text-gray-400 cursor-pointer p-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform transition-transform {{ $activeGroupId === $group->id ? 'rotate-180' : '' }}" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </div>
    </div>
</div>
