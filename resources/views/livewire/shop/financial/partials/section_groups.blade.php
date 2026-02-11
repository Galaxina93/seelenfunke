<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ expanded: @entangle('showGroupsSection') }">
    {{-- Header --}}
    <div class="flex justify-between items-center p-6 cursor-pointer select-none bg-white hover:bg-gray-50 transition-colors" @click="expanded = !expanded">
        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            Verträge & Gruppen
        </h2>
        <svg class="w-5 h-5 text-gray-400 transform transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </div>

    {{-- Body --}}
    <div x-show="expanded" x-collapse class="p-6 border-t border-gray-100 bg-gray-50/30">

        {{-- Gruppe hinzufügen --}}
        <div class="flex gap-2 mb-6" x-data="{ open: false }">
            <div x-show="open" @click.away="open = false" class="flex gap-2" x-transition>
                <input type="text" wire:model="newGroupName" placeholder="Name" class="text-sm rounded-lg border-gray-300 focus:ring-primary w-32 whitespace-nowrap bg-white text-gray-800">
                <select wire:model="newGroupType" class="text-sm rounded-lg border-gray-300 focus:ring-primary hidden">
                    <option value="expense">Ausgabe</option>
                    <option value="income">Einnahme</option>
                </select>
                <button wire:click="createGroup; open=false" class="bg-primary text-white px-3 rounded-lg text-sm hover:bg-primary-dark transition">OK</button>
            </div>
            <button @click="open = !open" x-show="!open" class="text-primary bg-white border border-gray-200 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Neue Gruppe
            </button>
        </div>

        {{-- Gruppen Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @forelse($groups as $group)
                @php
                    $groupMonthly = 0;
                    $groupYearly = 0;
                    foreach($group->items as $item) {
                        $groupMonthly += $item->amount / $item->interval_months;
                        $groupYearly += ($item->amount / $item->interval_months) * 12;
                    }
                @endphp

                <div class="bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm transition-all duration-300 {{ $activeGroupId === $group->id ? 'ring-2 ring-primary/20 shadow-md lg:col-span-2' : '' }}">
                    {{-- Group Header --}}
                    <div
                        wire:click="toggleGroup('{{ $group->id }}')"
                        class="p-4 flex justify-between items-center cursor-pointer bg-white hover:bg-gray-50 transition-colors"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-1.5 h-8 rounded-full {{ $groupMonthly >= 0 ? 'bg-emerald-400' : 'bg-rose-400' }}"></div>
                            <div>
                                <span class="font-bold text-gray-700 block">{{ $group->name }}</span>
                                <span class="text-xs text-gray-400">
                                    {{ $group->items->count() }} Positionen
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-6">
                            <div class="text-right hidden sm:block">
                                <div class="text-sm font-bold {{ $groupMonthly > 0 ? 'text-emerald-600' : ($groupMonthly < 0 ? 'text-rose-500' : 'text-gray-400') }}">
                                    {{ number_format($groupMonthly, 2, ',', '.') }} € / mtl.
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ number_format($groupYearly, 2, ',', '.') }} € / Jahr
                                </div>
                            </div>
                            <div class="text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform transition-transform {{ $activeGroupId === $group->id ? 'rotate-180' : '' }}" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Group Body --}}
                    @if($activeGroupId === $group->id)
                        <div class="border-t border-gray-100 bg-gray-50/50 p-4">

                            {{-- Items Liste --}}
                            <div class="space-y-3 mb-6">
                                @foreach($group->items as $item)
                                    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                                        @if($editingItemId === $item->id)
                                            {{-- INLINE EDIT MODE --}}
                                            <div class="space-y-4">
                                                <div class="text-xs uppercase font-bold text-gray-400 tracking-wider">Kostenstelle bearbeiten</div>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <input type="text" wire:model="itemName" placeholder="Bezeichnung" class="text-sm rounded-lg border-gray-300 focus:ring-primary w-full bg-gray-100 text-gray-800">
                                                        @error('itemName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                                    </div>
                                                    <div class="relative">
                                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">€</span>
                                                        <input type="number" step="0.01" wire:model="itemAmount" placeholder="0.00" class="pl-8 text-sm rounded-lg border-gray-300 focus:ring-primary w-full font-mono bg-gray-100 text-gray-800">
                                                        @error('itemAmount') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                    <select wire:model="itemInterval" class="text-sm rounded-lg border-gray-300 focus:ring-primary w-full cursor-pointer bg-gray-100 text-gray-800">
                                                        <option value="1">Monatlich</option>
                                                        <option value="3">Quartalsweise</option>
                                                        <option value="6">Halbjährlich</option>
                                                        <option value="12">Jährlich</option>
                                                        <option value="24">Alle 2 Jahre</option>
                                                    </select>
                                                    <div class="w-full">
                                                        <input type="date" wire:model="itemDate" class="text-sm rounded-lg border-gray-300 focus:ring-primary w-full cursor-pointer bg-gray-100 text-gray-800">
                                                        @error('itemDate') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                                    </div>
                                                    <input type="file" wire:model="itemFile" class="text-xs py-2 text-gray-500">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="inline-flex items-center cursor-pointer">
                                                        <input type="checkbox" wire:model="itemIsBusiness" class="sr-only peer">
                                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                                        <span class="ms-3 text-sm font-medium text-gray-700">Gewerblicher Eintrag</span>
                                                    </label>
                                                </div>
                                                <textarea wire:model="itemDescription" placeholder="Notizen..." class="w-full text-sm rounded-lg border-gray-300 focus:ring-primary mb-2 bg-gray-100 text-gray-800" rows="2"></textarea>
                                                <div class="flex justify-end gap-3 pt-2">
                                                    <button wire:click="cancelItemEdit" class="text-xs text-gray-500 hover:text-gray-700 font-medium">Abbrechen</button>
                                                    <button wire:click="saveItem" wire:loading.attr="disabled" class="bg-gray-800 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 transition shadow-lg shadow-gray-200">Speichern</button>
                                                </div>
                                            </div>
                                        @else
                                            {{-- DISPLAY MODE --}}
                                            <div class="flex justify-between items-start group">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <div class="font-semibold text-gray-800">{{ $item->name }}</div>
                                                        @if($item->is_business)
                                                            <span class="bg-blue-100 text-blue-600 text-[10px] px-1.5 py-0.5 rounded font-medium border border-blue-200">Gewerbe</span>
                                                        @else
                                                            <span class="bg-gray-100 text-gray-600 text-[10px] px-1.5 py-0.5 rounded font-medium border border-gray-200">Privat</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-1 flex flex-wrap gap-2 items-center">
                                                        <span class="bg-gray-100 px-2 py-0.5 rounded text-gray-600">{{ $item->first_payment_date->format('d.m.') }}</span>
                                                        <span>
                                                            @switch($item->interval_months)
                                                                @case(1) Monatlich @break
                                                                @case(3) Quartalsweise @break
                                                                @case(6) Halbjährlich @break
                                                                @case(12) Jährlich @break
                                                                @case(24) Alle 2 Jahre @break
                                                            @endswitch
                                                        </span>
                                                        @if($item->contract_file_path)
                                                            <a href="{{ Storage::url($item->contract_file_path) }}" target="_blank" class="text-primary hover:underline flex items-center gap-1">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                                                Vertrag
                                                            </a>
                                                        @endif
                                                    </div>
                                                    @if($item->description)
                                                        <div class="text-xs text-gray-400 mt-1 italic">{{ $item->description }}</div>
                                                    @endif
                                                </div>
                                                <div class="text-right pl-4">
                                                    <div class="font-bold whitespace-nowrap {{ $item->amount > 0 ? 'text-emerald-600' : ($item->amount < 0 ? 'text-rose-500' : 'text-gray-400') }}">
                                                        {{ number_format($item->amount, 2, ',', '.') }} €
                                                    </div>
                                                    <div class="flex gap-3 justify-end mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <button wire:click="openItemForm('{{ $group->id }}', '{{ $item->id }}')" class="text-xs text-primary hover:underline font-medium">Bearbeiten</button>
                                                        <button wire:click.stop="deleteItem('{{ $item->id }}')" wire:confirm="Wirklich löschen?" class="text-xs text-rose-400 hover:underline">Löschen</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Create New Form --}}
                            @if(!$editingItemId)
                                <div class="bg-gray-100 rounded-lg p-5 border border-dashed border-gray-300 relative">
                                    <div class="text-xs uppercase font-bold text-gray-400 mb-3 tracking-wider">
                                        Neue Kostenstelle hinzufügen
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <input type="text" wire:model="itemName" placeholder="Bezeichnung (z.B. Haftpflicht)" class="text-sm rounded-lg border-gray-300 focus:ring-primary w-full bg-white text-gray-800">
                                            @error('itemName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">€</span>
                                            <input type="number" step="0.01" wire:model="itemAmount" placeholder="0.00 (Negativ für Ausgabe)" class="pl-8 text-sm rounded-lg border-gray-300 focus:ring-primary w-full font-mono bg-white text-gray-800">
                                            @error('itemAmount') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                        <select wire:model="itemInterval" class="text-sm rounded-lg border-gray-300 focus:ring-primary w-full cursor-pointer bg-white text-gray-800">
                                            <option value="1">Monatlich</option>
                                            <option value="3">Quartalsweise</option>
                                            <option value="6">Halbjährlich</option>
                                            <option value="12">Jährlich</option>
                                            <option value="24">Alle 2 Jahre</option>
                                        </select>
                                        <div class="w-full">
                                            <input type="date" wire:model="itemDate" class="text-sm rounded-lg border-gray-300 focus:ring-primary w-full cursor-pointer bg-white text-gray-800">
                                            @error('itemDate') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                        </div>
                                        <input type="file" wire:model="itemFile" class="text-xs py-2 text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-primary hover:file:bg-teal-100">
                                    </div>

                                    <div class="mb-4">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" wire:model="itemIsBusiness" class="sr-only peer">
                                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                            <span class="ms-3 text-sm font-medium text-gray-700">Gewerblicher Eintrag</span>
                                        </label>
                                    </div>

                                    <textarea wire:model="itemDescription" placeholder="Vertragsnummer, Kundennummer, Notizen..." class="w-full text-sm rounded-lg border-gray-300 focus:ring-primary mb-4 bg-white text-gray-800" rows="2"></textarea>

                                    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                                        <button class="text-xs text-rose-400 hover:text-rose-600 transition" wire:click="deleteGroup('{{ $group->id }}')" onclick="confirm('Gruppe wirklich löschen?') || event.stopImmediatePropagation()">Gruppe löschen</button>
                                        <div class="flex gap-3">
                                            @if($itemName)
                                                <button wire:click="resetItemForm" class="text-xs text-gray-500 hover:text-gray-700 font-medium">Abbrechen</button>
                                            @endif
                                            {{-- FIX: Direkt saveNewItem aufrufen --}}
                                            <button wire:click="saveNewItem('{{ $group->id }}')" wire:loading.attr="disabled" class="bg-gray-800 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 transition shadow-lg shadow-gray-200">Speichern</button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-12 text-gray-400 bg-white rounded-xl border border-dashed border-gray-300 lg:col-span-2">
                    <svg class="w-12 h-12 mx-auto text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    <p>Noch keine Gruppen.</p>
                    <p class="text-sm">Erstelle deine erste Gruppe oben rechts.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
