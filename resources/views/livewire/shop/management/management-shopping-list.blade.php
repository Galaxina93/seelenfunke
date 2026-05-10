<div class="p-4 sm:p-6 pb-24" style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <div class="max-w-7xl mx-auto space-y-6">
        
        {{-- HEADER --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <x-heroicon-o-shopping-cart class="w-8 h-8 text-[var(--theme-color)]" />
                <h1 class="text-2xl font-bold text-white">Einkaufsliste</h1>
            </div>
            
            <div class="flex w-full sm:w-auto bg-gray-900/50 p-1 rounded-xl border border-gray-800 shrink-0 overflow-x-auto custom-scrollbar">
                <button wire:click="$set('activeTab', 'needed')" class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap {{ $activeTab === 'needed' ? 'bg-[var(--theme-color)] text-gray-900 shadow-[0_0_15px_var(--theme-color)]' : 'text-gray-400 hover:text-white' }}">
                    Einkaufen
                </button>
                <button wire:click="$set('activeTab', 'all')" class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap {{ $activeTab === 'all' ? 'bg-[var(--theme-color)] text-gray-900 shadow-[0_0_15px_var(--theme-color)]' : 'text-gray-400 hover:text-white' }}">
                    Inventar
                </button>
                <button wire:click="$set('activeTab', 'categories')" class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap {{ $activeTab === 'categories' ? 'bg-[var(--theme-color)] text-gray-900 shadow-[0_0_15px_var(--theme-color)]' : 'text-gray-400 hover:text-white' }}">
                    Kategorien
                </button>
            </div>
        </div>

        @if($activeTab === 'categories')
            {{-- KATEGORIEN VERWALTUNG --}}
            <div class="space-y-8 animate-fade-in-up">
                <div class="bg-gray-900/50 border border-gray-800 rounded-3xl p-6 shadow-inner">
                    <h2 class="text-lg font-bold text-white mb-4">Neue Kategorie anlegen</h2>
                    <div class="flex flex-col md:flex-row gap-4">
                        <input type="text" wire:model.defer="newCategoryName" placeholder="Name der Kategorie..." 
                               class="flex-1 bg-gray-950 border-2 border-gray-800 rounded-xl px-4 py-3 text-white placeholder-gray-600 focus:outline-none focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] transition-all">
                        
                        <div class="flex-1 bg-gray-950 border-2 border-gray-800 rounded-xl px-4 py-3 overflow-x-auto flex items-center gap-4 custom-scrollbar">
                            @foreach($availableIcons as $icon)
                                <button wire:click="$set('newCategoryIcon', '{{ $icon }}')" class="shrink-0 p-2 rounded-lg transition-all {{ $newCategoryIcon === $icon ? 'bg-[var(--theme-color)] text-gray-900' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                                    @svg('heroicon-o-'.$icon, 'w-6 h-6')
                                </button>
                            @endforeach
                        </div>

                        <button wire:click="addCategory" class="px-6 py-3 bg-[var(--theme-color)] text-gray-900 font-bold rounded-xl hover:bg-white transition-all shadow-lg active:scale-95 whitespace-nowrap">
                            Speichern
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($categories as $category)
                        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex items-center justify-between group transition-all hover:border-[var(--theme-color-40)]">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center text-[var(--theme-color)]">
                                    @svg('heroicon-o-'.($category->icon ?: 'folder'), 'w-6 h-6')
                                </div>
                                <span class="text-white font-semibold">{{ $category->name }}</span>
                            </div>
                            <button wire:click="deleteCategory('{{ $category->id }}')" class="p-2 text-gray-600 hover:text-red-500 transition-colors opacity-100 md:opacity-0 group-hover:opacity-100">
                                <x-heroicon-o-trash class="w-5 h-5" />
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            {{-- QUICK ADD FÜR PRODUKTE --}}
            <div class="relative group mt-6 mb-8 animate-fade-in-up">
                <div class="absolute inset-0 bg-[var(--theme-color-10)] rounded-xl md:rounded-3xl blur-md opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                <div class="relative flex flex-col md:flex-row bg-gray-950 border-2 border-gray-800 rounded-xl md:rounded-3xl focus-within:ring-4 focus-within:ring-[var(--theme-color-20)] focus-within:border-[var(--theme-color)] transition-all shadow-inner overflow-hidden">
                    
                    <select wire:model.defer="selectedCategoryId" class="bg-gray-900 text-gray-300 border-r border-gray-800 px-4 py-3.5 md:py-6 outline-none focus:outline-none appearance-none cursor-pointer font-semibold min-w-[150px]">
                        <option value="">Ohne Kategorie</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>

                    <input type="text" wire:model.defer="newItemName" wire:keydown.enter="addItem" 
                           placeholder="Neues Produkt (z.B. Milch)..." 
                           class="flex-1 pl-4 md:pl-6 pr-14 md:pr-48 py-3.5 md:py-6 bg-transparent outline-none border-none text-base md:text-xl font-bold text-white placeholder:text-gray-600">
                    
                    <button wire:click="addItem" 
                            class="absolute right-1.5 md:right-2 top-1.5 md:top-2 bottom-1.5 md:bottom-2 px-4 md:px-8 bg-[var(--theme-color)] text-gray-900 rounded-lg md:rounded-2xl flex items-center justify-center hover:bg-white transition-all shadow-lg active:scale-95 disabled:bg-gray-800 disabled:text-gray-600">
                        <x-heroicon-o-plus class="w-5 h-5 md:w-8 md:h-8 stroke-[3]" />
                        <span class="hidden sm:inline-block ml-2 font-black uppercase text-sm tracking-widest">Hinzufügen</span>
                    </button>
                </div>
            </div>

            {{-- ÄLTESTE PRODUKTE (VORSCHLÄGE) --}}
            @if($activeTab === 'needed' && $oldestItems->count() > 0)
                <div class="mb-8 animate-fade-in-up" style="animation-delay: 100ms;">
                    <div class="flex items-center gap-2 mb-3 px-2">
                        <x-heroicon-o-clock class="w-4 h-4 text-gray-500" />
                        <h3 class="text-xs font-black uppercase tracking-widest text-gray-500">Lange nicht gekauft</h3>
                    </div>
                    <div class="flex overflow-x-auto gap-3 pb-4 px-2 custom-scrollbar snap-x">
                        @foreach($oldestItems as $item)
                            <button wire:click="toggleItemStatus('{{ $item->id }}')" 
                                    class="shrink-0 snap-start bg-gray-900 border border-gray-800 hover:border-[var(--theme-color)] hover:bg-[var(--theme-color-10)] text-white px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all group text-left">
                                <div class="w-2 h-2 rounded-full bg-[var(--theme-color)] opacity-50 group-hover:opacity-100 group-hover:shadow-[0_0_8px_var(--theme-color)] transition-all"></div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold truncate max-w-[150px]">{{ $item->name }}</span>
                                    <span class="text-[9px] text-gray-500 whitespace-nowrap">Zuletzt: {{ $item->last_purchased_at->diffForHumans() }}</span>
                                </div>
                                <div class="ml-2 w-6 h-6 rounded-lg bg-gray-800 group-hover:bg-[var(--theme-color)] flex items-center justify-center transition-colors">
                                    <x-heroicon-o-plus class="w-4 h-4 text-gray-400 group-hover:text-gray-900" />
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- LISTE --}}
            <div class="space-y-8 animate-fade-in-up">
                @forelse($groupedItems as $categoryName => $items)
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 pl-2 text-gray-500">
                            @php
                                $catObj = $categories->firstWhere('name', $categoryName);
                                $iconName = $catObj ? $catObj->icon : 'folder';
                            @endphp
                            @svg('heroicon-o-'.$iconName, 'w-4 h-4')
                            <h2 class="text-xs font-black uppercase tracking-widest">{{ $categoryName }}</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            @foreach($items as $item)
                                <div class="group flex items-center justify-between bg-gray-900 border border-gray-800 hover:border-[var(--theme-color-40)] rounded-2xl p-4 transition-all hover:bg-gray-800/50">
                                    <div class="flex items-center gap-4 flex-1 cursor-pointer" wire:click="toggleItemStatus('{{ $item->id }}')">
                                        <div class="relative w-8 h-8 rounded-full border-2 transition-colors flex items-center justify-center {{ $item->status === 'stocked' ? 'bg-[var(--theme-color)] border-[var(--theme-color)]' : 'border-gray-600 group-hover:border-[var(--theme-color)]' }}">
                                            @if($item->status === 'stocked')
                                                <x-heroicon-o-check class="w-5 h-5 text-gray-900" />
                                            @endif
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-white font-semibold text-lg truncate {{ $item->status === 'stocked' ? 'line-through text-gray-500' : '' }}">{{ $item->name }}</span>
                                            @if($activeTab === 'all' && $item->last_purchased_at)
                                                <span class="text-[10px] text-gray-500 font-medium">Zuletzt gekauft: {{ $item->last_purchased_at->diffForHumans() }}</span>
                                            @elseif($activeTab === 'all' && !$item->last_purchased_at)
                                                <span class="text-[10px] text-red-500 font-medium">Noch nie gekauft</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 bg-gray-900/30 rounded-3xl border border-gray-800 border-dashed">
                        <x-heroicon-o-shopping-bag class="w-16 h-16 text-gray-700 mx-auto mb-4" />
                        <h3 class="text-gray-400 font-semibold mb-2">Die Liste ist leer</h3>
                        <p class="text-gray-600 text-sm">Füge Produkte oben hinzu, um sie zu planen.</p>
                    </div>
                @endforelse
            </div>
        @endif
        
    </div>
</div>
