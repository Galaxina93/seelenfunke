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
            <div class="w-10 h-10 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center text-gray-500 group-hover:text-[var(--theme-color)] group-hover:border-[var(--theme-color-50)] transition-all shadow-inner">
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

            <div x-data="{
                initSortable() {
                    if (typeof Sortable === 'undefined') {
                        let script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js';
                        script.onload = () => this.bindSortables();
                        document.head.appendChild(script);
                    } else {
                        this.bindSortables();
                    }
                },
                bindSortables() {
                    const privateList = this.$refs.privateList;
                    const businessList = this.$refs.businessList;
                    
                    Sortable.create(privateList, {
                        group: 'categories',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: (evt) => {
                            if (evt.to === privateList) {
                                @this.updateCategoryType(evt.item.dataset.id, false);
                            }
                        }
                    });

                    Sortable.create(businessList, {
                        group: 'categories',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: (evt) => {
                            if (evt.to === businessList) {
                                @this.updateCategoryType(evt.item.dataset.id, true);
                            }
                        }
                    });
                }
            }" x-init="initSortable()">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    {{-- Private Categories --}}
                    <div class="bg-gray-950/50 border border-gray-800 rounded-2xl p-6">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-800">
                            <div class="p-2 bg-blue-500/10 text-blue-400 rounded-lg shadow-inner">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </div>
                            <h3 class="text-lg font-serif font-bold text-white tracking-wide">Private Kategorien</h3>
                        </div>
                        <div x-ref="privateList" class="space-y-3 min-h-[100px]">
                            @foreach($this->manageableCategories->where('is_business', false) as $cat)
                                <div data-id="{{ $cat->id }}" class="group/cat cursor-grab active:cursor-grabbing relative bg-gray-950 border border-gray-800 rounded-xl p-3 shadow-inner hover:border-blue-500/50 hover:shadow-[0_0_15px_rgba(59,130,246,0.1)] transition-all duration-300" wire:key="cat-{{ $cat->id }}">
                                    @include('livewire.shop.accounting.partials.category_item_inner', ['cat' => $cat])
                                </div>
                            @endforeach
                            @if($this->manageableCategories->where('is_business', false)->isEmpty())
                                <div class="text-xs text-gray-500 text-center py-4 border border-dashed border-gray-800 rounded-xl">Keine privaten Kategorien - Elemente hierhin ziehen</div>
                            @endif
                        </div>
                    </div>

                    {{-- Business Categories --}}
                    <div class="bg-gray-950/50 border border-gray-800 rounded-2xl p-6">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-800">
                            <div class="p-2 bg-orange-500/10 text-orange-400 rounded-lg shadow-inner">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            </div>
                            <h3 class="text-lg font-serif font-bold text-white tracking-wide">Gewerbliche Kategorien</h3>
                        </div>
                        <div x-ref="businessList" class="space-y-3 min-h-[100px]">
                            @foreach($this->manageableCategories->where('is_business', true) as $cat)
                                <div data-id="{{ $cat->id }}" class="group/cat cursor-grab active:cursor-grabbing relative bg-gray-950 border border-gray-800 rounded-xl p-3 shadow-inner hover:border-orange-500/50 hover:shadow-[0_0_15px_rgba(249,115,22,0.1)] transition-all duration-300" wire:key="cat-{{ $cat->id }}">
                                    @include('livewire.shop.accounting.partials.category_item_inner', ['cat' => $cat])
                                </div>
                            @endforeach
                            @if($this->manageableCategories->where('is_business', true)->isEmpty())
                                <div class="text-xs text-gray-500 text-center py-4 border border-dashed border-gray-800 rounded-xl">Keine gewerblichen Kategorien - Elemente hierhin ziehen</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
