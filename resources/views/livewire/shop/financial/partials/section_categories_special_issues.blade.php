<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ expanded: @entangle('showSpecialSection') }">
    {{-- Header --}}
    <div class="flex justify-between items-center p-6 cursor-pointer select-none bg-white hover:bg-gray-50 transition-colors" @click="expanded = !expanded">
        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Kategorien & Sonderausgaben
            <span class="text-xs font-normal text-gray-400 ml-2 bg-gray-100 px-2 py-0.5 rounded">{{ $selectedMonth }}/{{ $selectedYear }}</span>
        </h2>
        <svg class="w-5 h-5 text-gray-400 transform transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </div>

    {{-- Body --}}
    <div x-show="expanded" x-collapse class="border-t border-gray-100 bg-gray-50/30">
        <div class="p-6 flex flex-col gap-6">

            {{-- 1. Bereich: Chart --}}
            <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-gray-700">Kategorien Übersicht</h3>
                    <div class="flex gap-2 items-center">
                        <select wire:model.live="chartFilter" class="text-xs border-gray-300 rounded-lg focus:ring-orange-400">
                            <option value="last_12_months">Letzte 12 Monate</option>
                            <option value="this_year">Dieses Jahr</option>
                            <option value="custom">Benutzerdefiniert</option>
                        </select>
                        @if($chartFilter === 'custom')
                            <input type="date" wire:model.live="dateFrom" class="text-xs border-gray-300 rounded-lg focus:ring-orange-400">
                            <input type="date" wire:model.live="dateTo" class="text-xs border-gray-300 rounded-lg focus:ring-orange-400">
                        @endif
                    </div>
                </div>
                <div class="relative h-64 w-full flex justify-center" wire:ignore>
                    <canvas id="specialPieChart"></canvas>
                </div>
            </div>

            {{-- 2. Bereich: Kategorien & Liste --}}
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 shadow-sm w-full">

                {{-- Inline: Kategorien Verwaltung --}}
                <div class="mt-2 pb-6 border-b border-gray-200">
                    <h4 class="text-xs font-bold text-gray-400 uppercase mb-3">Kategorien verwalten</h4>
                    <div class="flex gap-2 mb-4 justify-center">
                        <input type="text" wire:model="newCategoryName" placeholder="Neue Kategorie anlegen..." class="w-1/2 text-sm rounded-lg border-gray-300 bg-white p-2 focus:ring-orange-400">
                        <button wire:click="createCategory" class="bg-gray-800 text-white px-4 rounded-lg hover:bg-gray-700 text-lg font-bold shadow transition">+</button>
                    </div>
                    <div class="flex flex-wrap gap-2 justify-center max-h-[150px] overflow-y-auto p-1">
                        @foreach($this->manageableCategories as $cat)
                            <div class="flex justify-between items-center text-xs group bg-white p-2 rounded border border-gray-200 shadow-sm hover:shadow-md transition">
                                @if($editingCategoryId === $cat->id)
                                    <div class="flex items-center gap-1">
                                        <input type="text" wire:model="editCategoryName" class="w-24 text-xs border-gray-300 rounded p-1">
                                        <button wire:click="updateCategory" class="text-green-600 font-bold px-1">✓</button>
                                        <button wire:click="cancelEditCategory" class="text-gray-400 px-1">x</button>
                                    </div>
                                @else
                                    <span class="truncate font-medium text-gray-600 px-1">{{ $cat->name }}</span>
                                    <div class="flex gap-1 pl-2 border-l border-gray-100 ml-1">
                                        <button wire:click="startEditCategory('{{ $cat->id }}')" class="text-blue-400 hover:text-blue-600">✎</button>
                                        <button wire:click="deleteCategory('{{ $cat->id }}')" class="text-red-300 hover:text-red-500">×</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Liste & Suche --}}
                <div class="space-y-4 mt-6 animate-fade-in-down">

                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-bold text-gray-700">Sonderausgaben bearbeiten</h3>
                        <span class="text-xs text-gray-400">{{ $specials->total() }} Einträge</span>
                    </div>

                    {{-- Suche --}}
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" wire:model.live="specialSearch" placeholder="Einträge suchen (Titel, Ort, Kategorie)..." class="w-full text-sm rounded-lg border-gray-300 focus:ring-orange-400 bg-white pl-10 py-2.5 shadow-sm">
                    </div>

                    {{-- Items Loop --}}
                    <div class="flex flex-col gap-3">
                        @forelse($specials as $special)
                            <div wire:key="special-{{ $special->id }}" class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                                @if($editingSpecialId === $special->id)
                                    {{-- INLINE EDIT MODE --}}
                                    <div class="space-y-4">
                                        <div class="flex justify-between items-start border-b border-gray-100 pb-2 mb-2">
                                            <span class="text-xs font-bold text-orange-500 uppercase tracking-wider">Bearbeiten</span>
                                            <button wire:click="cancelEditSpecial" class="text-gray-400 hover:text-gray-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <input type="text" wire:model="editSpecialTitle" class="w-full text-sm rounded-lg border-gray-300 p-2 bg-gray-50 text-gray-800 placeholder-gray-400 focus:ring-orange-400">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="relative w-full">
                                                    <input list="category-options-edit-{{ $special->id }}" wire:model="editSpecialCategory" class="w-full text-sm rounded-lg border-gray-300 p-2 bg-gray-50 text-gray-800 focus:ring-orange-400">
                                                    <datalist id="category-options-edit-{{ $special->id }}">
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat }}"></option>
                                                        @endforeach
                                                    </datalist>
                                                </div>
                                                <input type="text" wire:model="editSpecialLocation" class="w-full text-sm rounded-lg border-gray-300 p-2 bg-gray-50 text-gray-800 focus:ring-orange-400">
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="relative w-full">
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs">€</span>
                                                <input type="number" step="0.01" wire:model="editSpecialAmount" class="w-full text-sm rounded-lg border-gray-300 pl-8 p-2 font-mono bg-gray-50 text-gray-800 focus:ring-orange-400">
                                            </div>
                                            <input type="date" wire:model="editSpecialDate" class="w-full text-sm rounded-lg border-gray-300 p-2 bg-gray-50 text-gray-800 focus:ring-orange-400">
                                        </div>

                                        {{-- Extended Business & File Settings --}}
                                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                            <div class="flex items-center gap-2 mb-3">
                                                <input type="checkbox" wire:model.live="editSpecialIsBusiness" class="rounded text-orange-500 focus:ring-orange-400 border-gray-300 w-4 h-4">
                                                <span class="text-sm font-bold text-gray-700 select-none">Gewerblich</span>
                                            </div>

                                            @if($editSpecialIsBusiness)
                                                <div class="grid grid-cols-2 gap-3 mb-4 animate-fade-in-down">
                                                    <input type="text" wire:model="editSpecialInvoiceNumber" placeholder="Rechnungsnummer" class="text-xs rounded border-gray-300 focus:ring-orange-400 w-full">
                                                    <select wire:model="editSpecialTaxRate" class="text-xs rounded border-gray-300 focus:ring-orange-400 w-full">
                                                        <option value="19">19% MwSt.</option>
                                                        <option value="7">7% MwSt.</option>
                                                        <option value="0">0% / Steuerfrei</option>
                                                    </select>
                                                </div>
                                            @endif

                                            <div class="border-t border-gray-200 pt-3">
                                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Belege / Dateien</label>

                                                {{-- Existing Files --}}
                                                @if(!empty($editSpecialFiles))
                                                    <div class="flex flex-wrap gap-2 mb-3">
                                                        @foreach($editSpecialFiles as $index => $filePath)
                                                            <div class="flex items-center gap-2 bg-white border border-gray-300 px-2 py-1.5 rounded-lg text-xs shadow-sm">
                                                                <a href="{{ Storage::url($filePath) }}" target="_blank" class="text-blue-500 hover:text-blue-700 hover:underline flex items-center gap-1 max-w-[120px] truncate">
                                                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                                                    Beleg {{ $index + 1 }}
                                                                </a>
                                                                <button wire:click="removeFileFromSpecial({{ $index }})" class="text-gray-400 hover:text-red-500 font-bold transition">×</button>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                {{-- Upload New --}}
                                                <input type="file" wire:model="newEditFiles" multiple class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white file:text-orange-500 file:border-orange-100 file:border hover:file:bg-orange-50 transition cursor-pointer">
                                                <div wire:loading wire:target="newEditFiles" class="text-[10px] text-orange-500 mt-1 pl-1">Lade Dateien hoch...</div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-3 pt-2">
                                            <button wire:click="cancelEditSpecial" class="text-xs text-gray-500 hover:text-gray-700 font-medium px-2">Abbrechen</button>
                                            <button wire:click="updateSpecial('{{ $special->id }}')" class="bg-teal-600 text-white px-5 py-2 rounded-lg text-xs font-bold shadow hover:bg-teal-700 transition transform active:scale-95">Speichern</button>
                                        </div>
                                    </div>
                                @else
                                    {{-- DISPLAY MODE --}}
                                    <div class="flex justify-between items-center cursor-pointer" wire:click="editSpecial('{{ $special->id }}')">
                                        <div class="flex-1 min-w-0 pr-4">
                                            <div class="flex items-center gap-2 mb-1">
                                                <div class="font-bold text-gray-800 text-sm truncate">{{ $special->title }}</div>
                                                @if($special->is_business)
                                                    <span class="bg-blue-50 text-blue-600 border border-blue-100 text-[10px] px-2 py-0.5 rounded-full font-bold">Gewerbe</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 flex flex-wrap gap-x-4 gap-y-1 items-center">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    {{ $special->execution_date->format('d.m.Y') }}
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                                    {{ $special->category }}
                                                </span>
                                                @if($special->location)
                                                    <span class="flex items-center gap-1">
                                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                        {{ $special->location }}
                                                    </span>
                                                @endif
                                                @if(!empty($special->file_paths))
                                                    <span class="flex items-center gap-1 text-orange-500 font-bold bg-orange-50 px-1.5 py-0.5 rounded">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                                        {{ count($special->file_paths) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-mono font-bold text-base {{ $special->amount > 0 ? 'text-emerald-600' : 'text-orange-500' }}">
                                                {{ number_format($special->amount, 2, ',', '.') }} €
                                            </div>
                                            <button wire:click.stop="deleteSpecial('{{ $special->id }}')" class="text-xs text-red-300 hover:text-red-600 mt-1 transition font-medium">Entfernen</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-sm text-gray-400 py-12 border border-dashed border-gray-200 rounded-lg">Keine Einträge gefunden.</div>
                        @endforelse
                    </div>

                    <div class="mt-4">
                        {{ $specials->links() }}
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
