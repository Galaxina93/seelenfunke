<div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">

    {{-- Header mit Umschalter --}}
    <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
        <div class="flex items-center gap-1.5">
            <h3 class="text-lg font-serif font-bold text-gray-900">Kategorien</h3>
            @if(!$isManaging)
                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full animate-pulse">
                    {{ count($selectedCategories) }} gewählt
                </span>
            @endif
        </div>

        <div class="flex items-center gap-3">
            {{-- Typ-Badge --}}
            @php
                $typeColors = [
                    'physical' => 'bg-blue-50 text-blue-700',
                    'digital' => 'bg-indigo-50 text-indigo-700',
                    'service' => 'bg-orange-50 text-orange-700',
                ];
                $typeName = match($product->type) {
                    'physical' => 'Physisch',
                    'digital' => 'Digital',
                    'service' => 'Service',
                    default => 'Allgemein'
                };
            @endphp
            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded hidden sm:inline-block {{ $typeColors[$product->type] ?? 'bg-gray-100' }}">
                {{ $typeName }}
            </span>

            {{-- Manage Button --}}
            <button wire:click="toggleManageMode"
                    class="text-xs font-bold px-3 py-1.5 rounded-lg transition-all flex items-center gap-1
                           {{ $isManaging
                              ? 'bg-gray-900 text-white shadow-md'
                              : 'bg-white border border-gray-200 text-gray-500 hover:border-[#C5A059] hover:text-[#C5A059]'
                           }}">
                @if($isManaging)
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    Fertig
                @else
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Verwalten
                @endif
            </button>
        </div>
    </div>

    {{-- ================================================= --}}
    {{-- ANSICHT 1: AUSWAHL MODUS (Standard) --}}
    {{-- ================================================= --}}
    @if(!$isManaging)
        {{-- Search --}}
        <div class="mb-4 relative">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Kategorie suchen..."
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
        </div>

        {{-- Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[300px] overflow-y-auto custom-scrollbar pr-2">
            @forelse($availableCategories as $category)
                @php
                    $catId = $category['id'];
                    $catName = $category['name'];
                    $isSelected = in_array($catId, $selectedCategories);
                @endphp
                <button
                    wire:click="toggleCategory({{ $catId }})"
                    class="group relative flex items-center gap-3 p-3 rounded-xl border text-left transition-all duration-200
                           {{ $isSelected
                              ? 'border-[#C5A059] bg-[#C5A059]/5 shadow-sm'
                              : 'border-gray-100 hover:border-gray-300 hover:bg-gray-50'
                           }}">

                    {{-- Checkbox --}}
                    <div class="flex-shrink-0 w-5 h-5 rounded border flex items-center justify-center transition-colors
                                {{ $isSelected ? 'bg-[#C5A059] border-[#C5A059]' : 'border-gray-300 bg-white group-hover:border-gray-400' }}">
                        @if($isSelected)
                            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                        @endif
                    </div>

                    <div class="flex flex-col">
                        <span class="text-xs font-bold {{ $isSelected ? 'text-gray-900' : 'text-gray-600' }}">
                            {{ $catName }}
                        </span>
                    </div>
                </button>
            @empty
                <div class="col-span-full py-8 text-center text-gray-400 text-xs">
                    Keine Kategorien gefunden.
                </div>
            @endforelse
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100 flex items-start gap-2 text-xs text-gray-500">
            <svg class="w-4 h-4 text-blue-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <p>Wählen Sie mindestens eine Kategorie, damit Ihr Produkt im Shopfilter gefunden wird.</p>
        </div>

        {{-- ================================================= --}}
        {{-- ANSICHT 2: MANAGEMENT MODUS (CRUD) --}}
        {{-- ================================================= --}}
    @else
        <div class="space-y-6">

            {{-- 1. Erstellen --}}
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Neue Kategorie</label>
                <div class="flex gap-2">
                    {{-- HIER WURDE .live HINZUGEFÜGT --}}
                    <input type="text"
                           wire:model.live="newCategoryName"
                           wire:keydown.enter="createCategory"
                           placeholder="Name der Kategorie..."
                           class="flex-1 px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-[#C5A059] focus:ring-[#C5A059]">

                    <button wire:click="createCategory"
                            class="bg-[#C5A059] text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-[#b08d4d] transition disabled:opacity-50 disabled:cursor-not-allowed"
                            @if(empty($newCategoryName)) disabled @endif>
                        +
                    </button>
                </div>
                @error('newCategoryName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- 2. Liste Bearbeiten --}}
            <div class="max-h-[400px] overflow-y-auto custom-scrollbar space-y-2">
                @foreach($availableCategories as $category)
                    @php
                        $catId = $category['id'];
                        $catName = $category['name'];
                    @endphp

                    <div class="flex items-center justify-between p-3 bg-white border border-gray-100 rounded-xl hover:border-gray-200 transition group" wire:key="cat-{{ $catId }}">

                        {{-- Editier Modus für Zeile --}}
                        @if($editingCategoryId === $catId)
                            <div class="flex flex-1 items-center gap-2 mr-2">
                                <input type="text"
                                       wire:model="editingCategoryName"
                                       wire:keydown.enter="updateCategory"
                                       class="w-full px-2 py-1 text-sm border border-[#C5A059] rounded focus:ring-1 focus:ring-[#C5A059]">

                                <button wire:click="updateCategory" class="text-green-600 hover:bg-green-50 p-1 rounded"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg></button>
                                <button wire:click="cancelEditing" class="text-gray-400 hover:bg-gray-100 p-1 rounded"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                            </div>
                        @else
                            {{-- Anzeige Modus für Zeile --}}
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-2 rounded-full {{ in_array($catId, $selectedCategories) ? 'bg-[#C5A059]' : 'bg-gray-300' }}"></div>
                                <span class="text-sm font-medium text-gray-700">{{ $catName }}</span>
                            </div>

                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="startEditing({{ $catId }}, '{{ addslashes($catName) }}')" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Umbenennen">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </button>
                                <button wire:confirm="Sicher? Dies löscht die Kategorie endgültig."
                                        wire:click="deleteCategory({{ $catId }})"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Löschen">
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
