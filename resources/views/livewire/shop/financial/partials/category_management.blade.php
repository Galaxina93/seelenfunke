<div x-data="{ open: false }" class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden relative group">
    <div @click="open = !open" class="p-6 md:p-8 flex items-center justify-between cursor-pointer hover:bg-gray-800/50 transition-colors">
        <div class="flex items-center gap-4">
            <div class="p-2.5 bg-orange-500/10 border border-orange-500/20 text-orange-400 rounded-xl shadow-inner">
                <span class="text-2xl drop-shadow-[0_0_10px_currentColor]">📂</span>
            </div>
            <div>
                <h2 class="text-xl font-serif font-bold text-white tracking-wide">Kategorien verwalten</h2>
                <p class="text-gray-400 text-xs mt-1 font-medium">Erstellen & Bearbeiten</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-[10px] font-black bg-gray-950 border border-gray-800 text-gray-500 px-4 py-1.5 rounded-lg uppercase tracking-widest shadow-inner hidden sm:block">
                {{ count($this->manageableCategories) }} Kategorien
            </div>
            <div class="w-10 h-10 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center text-gray-500 group-hover:text-primary group-hover:border-primary/50 transition-all shadow-inner">
                <svg class="w-5 h-5 transform transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>
    </div>

    <div x-show="open" x-collapse style="display: none;">
        <div class="p-6 md:p-8 pt-0 border-t border-gray-800 relative">
            <div class="absolute top-0 right-0 w-64 h-64 bg-orange-500/10 rounded-full blur-[80px] -mr-16 -mt-16 pointer-events-none opacity-50"></div>

            <div class="mt-6 mb-10 relative z-10">
                <div class="flex flex-col md:flex-row gap-4 bg-gray-950 p-2.5 rounded-[1.5rem] border border-gray-800 focus-within:ring-2 focus-within:ring-orange-500/30 focus-within:border-orange-500 transition-all shadow-inner">
                    <div class="relative flex-grow">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <input
                            wire:model="newCategoryName"
                            type="text"
                            class="block w-full pl-12 pr-4 py-3 bg-transparent border-none focus:ring-0 text-white placeholder-gray-600 font-medium text-sm outline-none"
                            placeholder="Neue Kategorie benennen..."
                            wire:keydown.enter="createCategory"
                        >
                    </div>
                    <button
                        wire:click="createCategory"
                        class="bg-orange-500/90 border border-orange-400/50 hover:bg-orange-500 text-white font-black uppercase tracking-widest text-[10px] py-3.5 px-8 rounded-xl shadow-[0_0_20px_rgba(249,115,22,0.2)] transition-all transform hover:scale-[1.02] flex items-center justify-center gap-3 whitespace-nowrap"
                    >
                        <span>Erstellen</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                </div>
                @error('newCategoryName') <span class="text-[10px] font-bold text-red-400 uppercase tracking-widest mt-3 block pl-4 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @foreach($this->manageableCategories as $cat)
                    <div class="group/cat relative bg-gray-950 border border-gray-800 rounded-2xl p-4 shadow-inner hover:border-orange-500/50 hover:shadow-[0_0_15px_rgba(249,115,22,0.1)] transition-all duration-300" wire:key="cat-{{ $cat->id }}">
                        @if($editingCategoryId === $cat->id)
                            <div class="flex items-center gap-2 animate-fade-in">
                                <input type="text" wire:model="editCategoryName" class="w-full text-sm font-bold text-white border-b-2 border-orange-500 focus:outline-none bg-transparent px-2 py-1.5" autofocus wire:keydown.enter="updateCategory">
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
                                    <div class="w-1.5 h-8 bg-gray-800 rounded-full group-hover/cat:bg-orange-500 group-hover/cat:shadow-[0_0_8px_rgba(249,115,22,0.8)] transition-all shrink-0"></div>
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
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
