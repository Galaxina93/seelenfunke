<div x-data="{ open: false }" class="w-full min-w-0">
    <div class="bg-black/95 backdrop-blur-xl p-5 sm:p-6 rounded-2xl shadow-[0_0_20px_rgba(16,185,129,0.05)] border border-emerald-900/50 animate-fade-in-up transition-colors hover:border-emerald-800/60 w-full min-w-0">
        
        {{-- Header --}}
        <div class="flex flex-wrap lg:flex-nowrap items-start lg:items-center justify-between gap-4 sm:gap-5 transition-all" :class="open ? 'mb-5 border-b border-emerald-900/40 pb-4' : ''">
            
            <div @click="open = !open" class="flex items-center gap-3 sm:gap-4 cursor-pointer group flex-1 min-w-[200px] w-full lg:w-auto">
                <div class="p-2 rounded-xl bg-black border border-emerald-900/50 text-emerald-700 group-hover:text-emerald-400 group-hover:border-emerald-500/50 transition-all shadow-inner shrink-0">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="open ? 'rotate-180 text-emerald-400' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>
                <h3 class="text-lg sm:text-xl font-mono font-bold text-emerald-500 tracking-wider group-hover:text-emerald-400 transition-colors truncate">
                    > KATEGORIE_WÄHLEN
                </h3>
            </div>

            <div class="flex flex-wrap items-center justify-start sm:justify-end w-full lg:w-auto gap-3" @click.stop>
                <button wire:click="toggleManageMode"
                        @click="open = true"
                        class="text-[10px] font-mono uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 shadow-inner border w-full sm:w-auto shrink-0
                               {{ $isManaging
                                  ? 'bg-emerald-500/10 border-emerald-500/50 text-emerald-400 shadow-[0_0_15px_rgba(16,185,129,0.2)]'
                                  : 'bg-black border-emerald-900/50 text-emerald-700 hover:text-emerald-400 hover:border-emerald-700'
                               }}">
                    @if($isManaging)
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        [Fertig]
                    @else
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        [Verwalten]
                    @endif
                </button>
            </div>
        </div>

        {{-- Eingeklappter Inhalt --}}
        <div x-show="open" x-collapse style="display: none;">
            @if(!$isManaging)
                <div class="pt-2">
                    {{-- Search --}}
                    <div class="mb-5 relative group">
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               placeholder=">_ Kategorie suchen..."
                               class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-emerald-900/50 bg-black text-sm font-mono text-emerald-500 focus:bg-black focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 transition-all shadow-inner outline-none placeholder-emerald-900/50">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-emerald-800 group-focus-within:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                    </div>

                    {{-- Grid Selector --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[350px] overflow-y-auto custom-scrollbar pr-2 pb-2">
                        @forelse($categories as $category)
                            @php
                                $catId = $category['id'];
                                $catName = $category['name'];
                                $isSelected = ($catId === $selectedCategoryId);
                            @endphp
                            <button
                                wire:click="selectCategory({{ $catId }})"
                                class="group relative flex items-center gap-4 p-4 rounded-xl border text-left transition-all duration-300 shadow-inner min-w-0
                                       {{ $isSelected
                                          ? 'border-emerald-500 bg-emerald-500/10 shadow-[inset_0_0_15px_rgba(16,185,129,0.1)]'
                                          : 'border-emerald-900/40 bg-black hover:border-emerald-700/60 hover:bg-emerald-950/20'
                                       }}">
                                
                                <div class="flex-shrink-0 w-6 h-6 rounded-lg border flex items-center justify-center transition-all shadow-inner
                                            {{ $isSelected ? 'bg-emerald-500 border-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.5)]' : 'border-emerald-900 bg-black group-hover:border-emerald-700' }}">
                                    @if($isSelected)
                                        <svg class="w-4 h-4 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    @endif
                                </div>
                                <div class="flex flex-col min-w-0 flex-1">
                                    <span class="text-sm font-mono font-bold truncate tracking-wide {{ $isSelected ? 'text-emerald-400' : 'text-emerald-700 group-hover:text-emerald-500' }}">
                                        {{ $catName }}
                                    </span>
                                </div>
                            </button>
                        @empty
                            <div class="col-span-full py-10 text-center text-[10px] font-mono uppercase tracking-widest text-emerald-800/60 bg-black rounded-2xl border border-emerald-900/40 shadow-inner">
                                Keine Kategorien im System gefunden.
                            </div>
                        @endforelse
                    </div>
                </div>

            @else
                {{-- Edit Mode --}}
                <div class="space-y-8 pt-2 animate-fade-in font-mono">
                    {{-- 1. Create --}}
                    <div class="bg-black p-5 sm:p-6 rounded-xl border border-emerald-900/40 shadow-[inset_0_0_20px_rgba(16,185,129,0.02)] w-full">
                        <label class="block text-[9px] font-bold uppercase tracking-[0.2em] text-emerald-600 mb-3 pl-1">Neue Kategorie anlegen</label>
                        <div class="flex gap-3 w-full">
                            <input type="text"
                                   wire:model.live="newCategoryName"
                                   wire:keydown.enter="createCategory"
                                   placeholder=">_ Name eingeben..."
                                   class="flex-1 px-4 py-3.5 rounded-xl border border-emerald-900/50 bg-gray-950 text-emerald-400 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 transition-all outline-none shadow-inner placeholder-emerald-900/50 min-w-0">

                            <button wire:click="createCategory"
                                    class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/50 px-6 py-3.5 rounded-xl text-lg font-black hover:bg-emerald-500 hover:text-black transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-[0_0_15px_rgba(16,185,129,0.1)] shrink-0 flex items-center justify-center cursor-pointer"
                                    @if(empty($newCategoryName)) disabled @endif>
                                +
                            </button>
                        </div>
                        @error('newCategoryName') <span class="text-[10px] uppercase tracking-widest text-red-500 mt-2 block ml-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- 2. List & Edit --}}
                    <div class="max-h-[400px] overflow-y-auto custom-scrollbar space-y-3 pr-2 w-full">
                        @foreach($categories as $category)
                            @php
                                $catId = $category['id'];
                                $catName = $category['name'];
                            @endphp

                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 bg-black border border-emerald-900/40 rounded-xl shadow-inner hover:border-emerald-800/60 transition-colors group gap-4 min-w-0" wire:key="cat-manage-{{ $catId }}">
                                
                                @if($editingCategoryId === $catId)
                                    <div class="flex flex-1 w-full items-center gap-2 sm:gap-3 mr-0 sm:mr-3 animate-fade-in min-w-0">
                                        <input type="text"
                                               wire:model="editingCategoryName"
                                               wire:keydown.enter="updateCategory"
                                               class="w-full flex-1 px-4 py-2.5 text-sm font-bold border border-emerald-500 bg-gray-950 text-emerald-400 rounded-xl focus:ring-1 focus:ring-emerald-500/30 outline-none shadow-[inset_0_0_10px_rgba(16,185,129,0.1)] min-w-0">
                                        
                                        <button wire:click="updateCategory" class="text-black bg-emerald-500 hover:bg-emerald-400 p-3 rounded-xl shadow-[0_0_10px_rgba(16,185,129,0.3)] transition-all shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></button>
                                        <button wire:click="cancelEditing" class="text-emerald-700 bg-black border border-emerald-900 hover:text-emerald-500 hover:border-emerald-700 p-3 rounded-xl transition-all shadow-inner shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                    </div>
                                    @error('editingCategoryName') <span class="text-[10px] uppercase tracking-widest text-red-500 block">{{ $message }}</span> @enderror
                                @else
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-2 h-2 rounded-full shrink-0 {{ ($catId === $selectedCategoryId) ? 'bg-emerald-500 shadow-[0_0_8px_currentColor]' : 'bg-emerald-900/50' }}"></div>
                                        <span class="text-sm font-bold text-emerald-600 tracking-wide truncate">{{ $catName }}</span>
                                    </div>

                                    <div class="flex items-center gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity w-full sm:w-auto justify-end shrink-0 mt-2 sm:mt-0">
                                        <button wire:click="startEditing({{ $catId }}, '{{ addslashes($catName) }}')" class="p-2 text-emerald-700 hover:text-emerald-400 bg-black border border-emerald-900/50 hover:border-emerald-500/50 rounded-lg transition-all shadow-inner" title="Rename">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </button>
                                        <button wire:confirm="WARNING_ NODE DELETION IS PERMANENT. PROCEED?"
                                                wire:click="deleteCategory({{ $catId }})"
                                                class="p-2 text-emerald-800/60 hover:text-red-500 bg-black border border-emerald-900/30 hover:border-red-500/40 rounded-lg transition-all shadow-inner" title="Delete">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
