<div x-data="{ open: false }">
    <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up transition-colors hover:border-gray-700">

        {{-- Header (Klickbar zum Aufklappen) & Controls --}}
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5 transition-all" :class="open ? 'mb-6 border-b border-gray-800 pb-5' : ''">

            {{-- Klickbarer Titelbereich --}}
            <div @click="open = !open" class="flex items-center gap-4 cursor-pointer group flex-1 w-full lg:w-auto">
                <div class="p-2 rounded-xl bg-gray-950 border border-gray-800 text-gray-500 group-hover:text-primary group-hover:border-primary/30 transition-all shadow-inner shrink-0">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="open ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>
                <h3 class="text-xl font-serif font-bold text-white tracking-wide group-hover:text-primary transition-colors">Kategorien</h3>

                @if(!$isManaging)
                    <span class="text-[9px] font-black uppercase tracking-widest text-primary bg-primary/10 border border-primary/20 px-2.5 py-1 rounded-lg shadow-inner animate-pulse whitespace-nowrap ml-2">
                        {{ count($selectedCategories) }} gewählt
                    </span>
                @endif
            </div>

            {{-- Controls (Buttons rechts - stoppen Click-Event fürs Accordion) --}}
            <div class="flex flex-row items-center justify-between w-full lg:w-auto gap-4" @click.stop>
                @php
                    $typeColors = [
                        'physical' => 'bg-blue-500/10 text-blue-400 border-blue-500/20 shadow-[0_0_8px_currentColor]',
                        'digital' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20 shadow-[0_0_8px_currentColor]',
                        'service' => 'bg-orange-500/10 text-orange-400 border-orange-500/20 shadow-[0_0_8px_currentColor]',
                    ];
                    $typeName = match($product->type) {
                        'physical' => 'Physisch',
                        'digital' => 'Digital',
                        'service' => 'Service',
                        default => 'Allgemein'
                    };
                @endphp
                <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border shadow-inner hidden sm:inline-block {{ $typeColors[$product->type] ?? 'bg-gray-800 text-gray-400 border-gray-700' }}">
                    {{ $typeName }}
                </span>

                <button wire:click="toggleManageMode"
                        @click="open = true"
                        class="text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 shadow-inner border w-full sm:w-auto shrink-0
                               {{ $isManaging
                                  ? 'bg-primary border-primary/50 text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]'
                                  : 'bg-gray-950 border-gray-800 text-gray-400 hover:text-white hover:border-gray-600'
                               }}">
                    @if($isManaging)
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        Fertig
                    @else
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Verwalten
                    @endif
                </button>
            </div>
        </div>

        {{-- ================================================= --}}
        {{-- EINGEKLAPPTER INHALT --}}
        {{-- ================================================= --}}
        <div x-show="open" x-collapse style="display: none;">
            @if(!$isManaging)
                <div class="pt-2">
                    {{-- Search --}}
                    <div class="mb-5 relative group">
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               placeholder="Kategorie suchen..."
                               class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-gray-800 bg-gray-950 text-sm text-white focus:bg-black focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner outline-none placeholder-gray-600">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500 group-focus-within:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                    </div>

                    {{-- Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[350px] overflow-y-auto custom-scrollbar pr-2 pb-2">
                        @forelse($availableCategories as $category)
                            @php
                                $catId = $category['id'];
                                $catName = $category['name'];
                                $isSelected = in_array($catId, $selectedCategories);
                            @endphp
                            <button
                                wire:click="toggleCategory({{ $catId }})"
                                class="group relative flex items-center gap-4 p-4 rounded-2xl border text-left transition-all duration-300 shadow-inner
                                       {{ $isSelected
                                          ? 'border-primary bg-primary/10 shadow-[inset_0_0_15px_rgba(197,160,89,0.2)]'
                                          : 'border-gray-800 bg-gray-950 hover:border-gray-600 hover:bg-gray-900'
                                       }}">

                                {{-- Checkbox --}}
                                <div class="flex-shrink-0 w-6 h-6 rounded-lg border flex items-center justify-center transition-all shadow-inner
                                            {{ $isSelected ? 'bg-primary border-primary shadow-[0_0_10px_rgba(197,160,89,0.5)]' : 'border-gray-700 bg-gray-900 group-hover:border-gray-500' }}">
                                    @if($isSelected)
                                        <svg class="w-4 h-4 text-gray-900" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    @endif
                                </div>

                                <div class="flex flex-col min-w-0">
                                    <span class="text-sm font-bold truncate tracking-wide {{ $isSelected ? 'text-white' : 'text-gray-400 group-hover:text-gray-300' }}">
                                        {{ $catName }}
                                    </span>
                                </div>
                            </button>
                        @empty
                            <div class="col-span-full py-10 text-center text-[10px] font-black uppercase tracking-widest text-gray-600 bg-gray-950 rounded-2xl border border-gray-800 shadow-inner">
                                Keine Kategorien gefunden.
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-6 pt-5 border-t border-gray-800 flex items-start gap-3 text-[10px] font-black uppercase tracking-widest text-gray-500">
                        <svg class="w-4 h-4 text-blue-400 shrink-0 mt-0.5 drop-shadow-[0_0_5px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p class="leading-relaxed">Wählen Sie mindestens eine Kategorie, damit Ihr Produkt im Shopfilter gefunden wird.</p>
                    </div>
                </div>

            @else
                <div class="space-y-8 pt-2 animate-fade-in">
                    {{-- 1. Erstellen --}}
                    <div class="bg-gray-950 p-5 sm:p-6 rounded-[1.5rem] border border-gray-800 shadow-inner">
                        <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3 ml-1">Neue Kategorie erstellen</label>
                        <div class="flex gap-3">
                            <input type="text"
                                   wire:model.live="newCategoryName"
                                   wire:keydown.enter="createCategory"
                                   placeholder="Name der Kategorie..."
                                   class="flex-1 px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-900 text-white text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none shadow-inner placeholder-gray-600">

                            <button wire:click="createCategory"
                                    class="bg-primary text-gray-900 px-6 py-3.5 rounded-xl text-lg font-black hover:bg-primary-dark transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-[0_0_15px_rgba(197,160,89,0.2)] shrink-0 flex items-center justify-center"
                                    @if(empty($newCategoryName)) disabled @endif>
                                +
                            </button>
                        </div>
                        @error('newCategoryName') <span class="text-[10px] font-bold uppercase tracking-widest text-red-400 mt-2 block ml-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- 2. Liste Bearbeiten --}}
                    <div class="max-h-[400px] overflow-y-auto custom-scrollbar space-y-3 pr-2">
                        @foreach($availableCategories as $category)
                            @php
                                $catId = $category['id'];
                                $catName = $category['name'];
                            @endphp

                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 bg-gray-950 border border-gray-800 rounded-2xl shadow-inner hover:border-gray-700 transition-colors group gap-4" wire:key="cat-{{ $catId }}">

                                @if($editingCategoryId === $catId)
                                    <div class="flex flex-1 w-full items-center gap-3 mr-3 animate-fade-in">
                                        <input type="text"
                                               wire:model="editingCategoryName"
                                               wire:keydown.enter="updateCategory"
                                               class="w-full px-4 py-2.5 text-sm font-bold border border-primary bg-gray-900 text-white rounded-xl focus:ring-2 focus:ring-primary/30 outline-none shadow-inner">

                                        <button wire:click="updateCategory" class="text-gray-900 bg-emerald-500 hover:bg-emerald-400 p-3 rounded-xl shadow-[0_0_10px_rgba(16,185,129,0.3)] transition-all shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></button>
                                        <button wire:click="cancelEditing" class="text-gray-400 bg-gray-900 border border-gray-700 hover:text-white p-3 rounded-xl transition-all shadow-inner shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                    </div>
                                @else
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full shrink-0 {{ in_array($catId, $selectedCategories) ? 'bg-primary shadow-[0_0_8px_currentColor]' : 'bg-gray-700' }}"></div>
                                        <span class="text-sm font-bold text-gray-300 tracking-wide truncate">{{ $catName }}</span>
                                    </div>

                                    <div class="flex items-center gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity w-full sm:w-auto justify-end">
                                        <button wire:click="startEditing({{ $catId }}, '{{ addslashes($catName) }}')" class="p-2.5 text-gray-500 hover:text-primary bg-gray-900 border border-gray-800 hover:border-primary/30 rounded-xl transition-all shadow-inner" title="Umbenennen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </button>
                                        <button wire:confirm="Sicher? Dies löscht die Kategorie endgültig."
                                                wire:click="deleteCategory({{ $catId }})"
                                                class="p-2.5 text-gray-500 hover:text-red-400 bg-gray-900 border border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="Löschen">
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
