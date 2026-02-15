<div x-data="{ open: false }" class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden relative group">
    <div @click="open = !open" class="p-6 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition-colors">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-orange-50 text-orange-500 rounded-full">
                <span class="text-xl">📂</span>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800">Kategorien verwalten</h2>
                <p class="text-gray-500 text-xs mt-0.5">Erstellen & Bearbeiten</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-xs font-bold bg-gray-100 text-gray-500 px-3 py-1 rounded-full uppercase tracking-wider">
                {{ count($this->manageableCategories) }} Kategorien
            </div>
            <svg class="w-5 h-5 text-gray-400 transform transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="display: none;">
        <div class="p-8 pt-0 border-t border-gray-100">
            <div class="absolute top-0 right-0 w-32 h-32 bg-orange-50 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none opacity-50"></div>

            <div class="mt-8 mb-10 relative z-10">
                <div class="flex flex-col md:flex-row gap-4 bg-gray-50 p-2 rounded-2xl border border-gray-200 focus-within:ring-2 focus-within:ring-orange-200 focus-within:border-orange-400 transition-all shadow-inner">
                    <div class="relative flex-grow">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <input
                            wire:model="newCategoryName"
                            type="text"
                            class="block w-full pl-12 pr-4 py-3 bg-transparent border-none focus:ring-0 text-gray-800 placeholder-gray-400 font-medium text-lg"
                            placeholder="Neue Kategorie benennen..."
                            wire:keydown.enter="createCategory"
                        >
                    </div>
                    <button
                        wire:click="createCategory"
                        class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-orange-200 transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 whitespace-nowrap"
                    >
                        <span>Erstellen</span>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                </div>
                @error('newCategoryName') <span class="text-sm text-red-500 mt-2 font-medium block pl-2">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($this->manageableCategories as $cat)
                    <div class="group relative bg-white border border-gray-100 rounded-xl p-4 shadow-sm hover:shadow-md hover:border-orange-200 transition-all duration-300" wire:key="cat-{{ $cat->id }}">
                        @if($editingCategoryId === $cat->id)
                            <div class="flex items-center gap-2 animate-fade-in">
                                <input type="text" wire:model="editCategoryName" class="w-full text-sm font-bold text-gray-800 border-b-2 border-orange-400 focus:outline-none bg-transparent px-1 py-1" autofocus wire:keydown.enter="updateCategory">
                                <button wire:click="updateCategory" class="p-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition-colors" title="Speichern">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button wire:click="cancelEditCategory" class="p-2 bg-gray-100 text-gray-500 rounded-lg hover:bg-gray-200 transition-colors" title="Abbrechen">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @else
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="w-2 h-8 bg-orange-100 rounded-full group-hover:bg-orange-400 transition-colors shrink-0"></div>
                                    <span class="font-bold text-gray-700 truncate group-hover:text-orange-600 transition-colors">{{ $cat->name }}</span>
                                    <span class="text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full border border-gray-100">{{ $cat->usage_count }}x</span>
                                </div>
                                <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity duration-200">
                                    <button wire:click="editCategory('{{ $cat->id }}', '{{ $cat->name }}')" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Bearbeiten">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button wire:click="deleteCategory('{{ $cat->id }}')" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Löschen">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
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
