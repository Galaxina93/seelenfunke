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

            {{-- 2. Bereich: Chart --}}
            <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-gray-700">Kategorien Übersicht</h3>
                    <div class="flex gap-2 items-center">
                        <select wire:model.live="chartFilter" class="text-xs border-gray-300 rounded-lg">
                            <option value="last_12_months">Letzte 12 Monate</option>
                            <option value="this_year">Dieses Jahr</option>
                            <option value="custom">Benutzerdefiniert</option>
                        </select>
                        @if($chartFilter === 'custom')
                            <input type="date" wire:model.live="dateFrom" class="text-xs border-gray-300 rounded-lg">
                            <input type="date" wire:model.live="dateTo" class="text-xs border-gray-300 rounded-lg">
                        @endif
                    </div>
                </div>
                <div class="relative h-64 w-full flex justify-center" wire:ignore>
                    <canvas id="specialPieChart"></canvas>
                </div>
            </div>

            {{-- 1. Bereich: Formular zum Erstellen (Volle Breite) --}}
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 shadow-sm w-full">

                {{-- Inline: Kategorien Verwaltung --}}
                <div class="mt-4 border-t border-gray-200 pt-4 animate-fade-in-up">
                    <div class="flex gap-2 mb-4 justify-center">
                        <input type="text" wire:model="newCategoryName" placeholder="Neue Kategorie" class="w-1/2 text-sm rounded-lg border-gray-300 bg-white p-2">
                        <button wire:click="createCategory" class="bg-gray-800 text-white px-4 rounded-lg hover:bg-gray-700 text-sm font-bold shadow">+</button>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-[200px] overflow-y-auto">
                        @foreach($this->manageableCategories as $cat)
                            <div class="flex justify-between items-center text-xs group bg-white p-2 rounded border border-gray-100 shadow-sm">
                                @if($editingCategoryId === $cat->id)
                                    <div class="flex items-center w-full">
                                        <input type="text" wire:model="editCategoryName" class="w-full text-xs border-gray-300 rounded p-1">
                                        <button wire:click="updateCategory" class="text-green-600 ml-1">✓</button>
                                        <button wire:click="cancelEditCategory" class="text-gray-400 ml-1">x</button>
                                    </div>
                                @else
                                    <span class="truncate">{{ $cat->name }}</span>
                                    <div class="flex gap-1 opacity-50 group-hover:opacity-100">
                                        <button wire:click="startEditCategory('{{ $cat->id }}')" class="text-blue-500">✎</button>
                                        <button wire:click="deleteCategory('{{ $cat->id }}')" class="text-red-400">x</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Liste & Suche (Toggled) --}}
                <div class="space-y-4 mt-2 border-t border-gray-200 pt-6 animate-fade-in-down">
                    {{-- Suche --}}
                    <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                        <input type="text" wire:model.live="specialSearch" placeholder="Suchen..." class="w-full text-sm rounded-lg border-gray-300 focus:ring-orange-400 bg-white pl-10 py-2.5">
                    </div>

                    {{-- Items Loop --}}
                    <div class="flex flex-col gap-3">
                        @forelse($specials as $special)
                            <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm hover:shadow-md transition-all group">
                                @if($editingSpecialId === $special->id)
                                    {{-- Inline Edit Mode --}}
                                    <div class="space-y-4">
                                        <input type="text" wire:model="editSpecialTitle" class="w-full text-sm rounded-lg border-gray-300 p-2 bg-gray-50 text-gray-800">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="relative w-full">
                                                <input list="category-options-edit-{{ $special->id }}" wire:model="editSpecialCategory" class="w-full text-sm rounded-lg border-gray-300 p-2 bg-gray-50 text-gray-800">
                                                <datalist id="category-options-edit-{{ $special->id }}">
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat }}"></option>
                                                    @endforeach
                                                </datalist>
                                            </div>
                                            <input type="text" wire:model="editSpecialLocation" class="w-full text-sm rounded-lg border-gray-300 p-2 bg-gray-50 text-gray-800">
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="relative w-full">
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs">€</span>
                                                <input type="number" step="0.01" wire:model="editSpecialAmount" class="w-full text-sm rounded-lg border-gray-300 pl-8 p-2 font-mono bg-gray-50 text-gray-800">
                                            </div>
                                            <input type="date" wire:model="editSpecialDate" class="w-full text-sm rounded-lg border-gray-300 p-2 bg-gray-50 text-gray-800">
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <label class="inline-flex items-center cursor-pointer">
                                                <input type="checkbox" wire:model="editSpecialIsBusiness" class="sr-only peer">
                                                <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                <span class="ms-2 text-xs font-medium text-gray-600">Gewerbe</span>
                                            </label>
                                            <div class="flex gap-2">
                                                <button wire:click="cancelEditSpecial" class="text-xs text-gray-500 hover:text-gray-700 font-medium">Abbrechen</button>
                                                <button wire:click="updateSpecial('{{ $special->id }}')" class="bg-teal-600 text-white px-4 py-1.5 rounded text-xs font-bold shadow hover:bg-teal-700">Speichern</button>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    {{-- Display Mode --}}
                                    <div class="flex justify-between items-center cursor-pointer" wire:click="editSpecial('{{ $special->id }}')">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <div class="font-semibold text-sm text-gray-800">{{ $special->title }}</div>
                                                @if($special->is_business)
                                                    <span class="bg-blue-100 text-blue-600 text-[10px] px-1.5 py-0.5 rounded font-medium border border-blue-200">Gewerbe</span>
                                                @else
                                                    <span class="bg-gray-100 text-gray-600 text-[10px] px-1.5 py-0.5 rounded font-medium border border-gray-200">Privat</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 flex gap-2 flex-wrap mt-1">
                                                <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> {{ $special->execution_date->format('d.m.Y') }}</span>
                                                @if($special->category) <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg> {{ $special->category }}</span> @endif
                                                @if($special->location) <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg> {{ $special->location }}</span> @endif
                                            </div>
                                        </div>
                                        <div class="text-right pl-3">
                                            <div class="font-bold text-base whitespace-nowrap {{ $special->amount > 0 ? 'text-emerald-600' : ($special->amount < 0 ? 'text-orange-500' : 'text-gray-400') }}">
                                                {{ number_format($special->amount, 2, ',', '.') }} €
                                            </div>
                                            <button wire:click.stop="deleteSpecial('{{ $special->id }}')" class="text-xs text-red-300 opacity-0 group-hover:opacity-100 hover:text-red-500 transition mt-1 font-medium">Löschen</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-sm text-gray-400 py-12 border border-dashed border-gray-200 rounded-lg">
                                Keine Sonderausgaben gefunden.
                            </div>
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
