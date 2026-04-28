@if($editingCategoryId === $cat->id)
    <div class="flex items-center gap-2 animate-fade-in">
        <input type="text" wire:model="editCategoryName" class="w-full text-sm font-bold text-white border-b-2 border-[var(--theme-color)] focus:outline-none bg-transparent px-2 py-1.5" autofocus wire:keydown.enter="updateCategory">
        <button wire:click="updateCategory" class="p-2 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 rounded-lg hover:bg-emerald-500 hover:text-white transition-all shadow-inner" title="Speichern">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </button>
        <button wire:click="cancelEditCategory" class="p-2 bg-gray-900 border border-gray-700 text-gray-400 rounded-lg hover:text-white hover:border-gray-500 transition-all shadow-inner" title="Abbrechen">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
@else
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3 overflow-hidden">
            <div class="w-1.5 h-8 bg-gray-800 rounded-full group-hover/cat:bg-[var(--theme-color)] group-hover/cat:shadow-[0_0_8px_var(--theme-color-80)] transition-all shrink-0"></div>
            <span class="font-bold text-gray-300 truncate group-hover/cat:text-white transition-colors text-sm tracking-wide">{{ $cat->name }}</span>
            <span class="text-[9px] font-black text-gray-500 bg-gray-900 px-2 py-0.5 rounded-md border border-gray-800 shadow-inner group-hover/cat:border-gray-700">{{ $cat->usage_count }}x</span>
        </div>
        <div class="flex items-center gap-1.5 opacity-100 sm:opacity-0 sm:group-hover/cat:opacity-100 transition-opacity duration-300 bg-gray-950 pl-2">
            <button wire:click="editCategory('{{ $cat->id }}', '{{ $cat->name }}')" class="p-2 text-gray-500 hover:text-blue-400 hover:bg-blue-500/10 rounded-xl transition-all" title="Bearbeiten">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            </button>
            <button wire:click="deleteCategory('{{ $cat->id }}')" class="p-2 text-gray-500 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-all" title="Löschen">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
        </div>
    </div>
@endif
