<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    {{-- Linke Spalte: Tabelle --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                <h2 class="text-lg font-bold text-gray-800">Buchungsjournal</h2>

                {{-- Suche --}}
                <div class="relative w-full sm:w-64">
                    <input type="text" wire:model.live.debounce.300ms="specialSearch"
                           class="w-full pl-10 pr-4 py-2 bg-white border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-200 focus:border-orange-500 shadow-sm transition-all"
                           placeholder="Suchen...">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="bg-gray-50/50 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="p-4 font-semibold">Datum</th>
                        <th class="p-4 font-semibold">Titel / Ort</th>
                        <th class="p-4 font-semibold">Kategorie</th>
                        <th class="p-4 font-semibold text-right">Betrag</th>
                        <th class="p-4 font-semibold text-center">Beleg</th>
                        <th class="p-4 text-center">Aktion</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($specials as $special)
                        <tr class="hover:bg-orange-50/30 transition-colors group">

                            @if($editingSpecialId === $special->id)
                                <td colspan="6" class="p-4 bg-orange-50/50">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label class="text-[10px] uppercase text-gray-500 font-bold mb-1 block">Titel</label>
                                            <input type="text" wire:model="editSpecialTitle" class="bg-white rounded-lg border border-gray-300 shadow-sm text-sm w-full focus:ring-orange-500 focus:border-orange-500" placeholder="Titel">
                                        </div>
                                        <div>
                                            <label class="text-[10px] uppercase text-gray-500 font-bold mb-1 block">Betrag</label>
                                            <input type="number" step="0.01" wire:model="editSpecialAmount" class="bg-white rounded-lg border border-gray-300 shadow-sm text-sm w-full focus:ring-orange-500 focus:border-orange-500" placeholder="Betrag">
                                        </div>
                                        <div>
                                            <label class="text-[10px] uppercase text-gray-500 font-bold mb-1 block">Datum</label>
                                            <input type="date" wire:model="editSpecialDate" class="bg-white rounded-lg border border-gray-300 shadow-sm text-sm w-full focus:ring-orange-500 focus:border-orange-500">
                                        </div>
                                        <div>
                                            <label class="text-[10px] uppercase text-gray-500 font-bold mb-1 block">Kategorie</label>
                                            <input type="text" list="cat-edit-list" wire:model="editSpecialCategory" class="bg-white rounded-lg border border-gray-300 shadow-sm text-sm w-full focus:ring-orange-500 focus:border-orange-500" placeholder="Kategorie">
                                            <datalist id="cat-edit-list">
                                                @foreach($this->manageableCategories as $cat)
                                                    <option value="{{ $cat->name }}">
                                                @endforeach
                                            </datalist>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 mb-4">
                                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                            <input type="checkbox" wire:model.live="editSpecialIsBusiness" class="rounded text-orange-500 focus:ring-orange-500 bg-white border-gray-300">
                                            <span>Geschäftlich?</span>
                                        </label>
                                        @if($editSpecialIsBusiness)
                                            <input type="text" wire:model="editSpecialInvoiceNumber" placeholder="Rechnungsnr." class="text-xs rounded border border-gray-300 bg-white p-2 w-32">
                                        @endif
                                    </div>

                                    <div class="mb-4">
                                        <label class="text-[10px] uppercase text-gray-500 font-bold mb-1 block">Dateien</label>

                                        {{-- Liste der bereits vorhandenen Dateien --}}
                                        @if(count($editSpecialExistingFiles) > 0)
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                @foreach($editSpecialExistingFiles as $index => $path)
                                                    <div class="relative group flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs">
                                                        {{-- Link zur Datei --}}
                                                        <a href="{{ Storage::url($path) }}" target="_blank" class="text-blue-600 hover:underline truncate max-w-[150px]">
                                                            {{ basename($path) }}
                                                        </a>

                                                        {{-- Löschen Button (Rotes X) --}}
                                                        <button type="button"
                                                                wire:click="removeExistingFile({{ $index }})"
                                                                class="text-gray-400 hover:text-red-500 transition-colors focus:outline-none">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Upload Feld für NEUE Dateien --}}
                                        <div class="relative">
                                            <input type="file" wire:model="newEditFiles" multiple class="text-xs text-gray-500 bg-white p-2 rounded border border-gray-200 w-full file:mr-4 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                                        </div>

                                        {{-- Ladeindikator --}}
                                        <div wire:loading wire:target="newEditFiles" class="text-xs text-orange-500 mt-1">
                                            Dateien werden hochgeladen...
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button wire:click="cancelEditSpecial" class="px-4 py-2 text-xs font-bold text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-sm">Abbrechen</button>
                                        <button wire:click="saveSpecial" class="px-4 py-2 text-xs font-bold text-white bg-green-500 rounded-lg hover:bg-green-600 shadow-md">Speichern</button>
                                    </div>
                                </td>
                            @else
                                <td class="p-4 text-sm text-gray-600 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($special->execution_date)->format('d.m.Y') }}
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-gray-800">{{ $special->title }}</div>
                                    @if($special->location)
                                        <div class="text-xs text-gray-400 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            {{ $special->location }}
                                        </div>
                                    @endif
                                    @if($special->is_business)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800 mt-1">Business</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ $special->category }}</span>
                                </td>
                                <td class="p-4 text-right font-mono font-bold {{ $special->amount < 0 ? 'text-red-500' : 'text-green-600' }}">
                                    {{ number_format($special->amount, 2, ',', '.') }} €
                                </td>
                                <td class="p-4 text-center">
                                    @if(!empty($special->file_paths) && count($special->file_paths) > 0)
                                        <div class="flex justify-center -space-x-2">
                                            @foreach($special->file_paths as $path)
                                                <a href="{{ Storage::url($path) }}" target="_blank" class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-orange-500 hover:border-orange-300 hover:z-10 transition-all shadow-sm" title="Beleg ansehen">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="relative group/upload flex justify-center">
                                            <label class="cursor-pointer text-gray-300 hover:text-orange-500 transition-colors">
                                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                                <input type="file" class="hidden" wire:model="quickUploadFile" wire:click="$set('uploadingMissingSpecialId', '{{ $special->id }}')">
                                            </label>
                                            @if($uploadingMissingSpecialId === $special->id)
                                                <div wire:loading wire:target="quickUploadFile" class="absolute top-0 right-0">
                                                    <svg class="animate-spin h-5 w-5 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button wire:click="editSpecial('{{ $special->id }}')" class="text-gray-400 hover:text-blue-500 transition-colors"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
                                        <button wire:click="deleteSpecial('{{ $special->id }}')" wire:confirm="Wirklich löschen?" class="text-gray-400 hover:text-red-500 transition-colors"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-400">Keine Einträge gefunden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">
                {{ $specials->links() }}
            </div>
        </div>
    </div>

    {{-- Rechte Spalte: Chart --}}
    <div class="space-y-6">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-6">Verteilung (Top 5)</h3>
            <div wire:ignore class="relative h-64 w-full">
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="mt-6 space-y-3">
                @foreach($chartLabels as $index => $label)
                    @if($index < 5)
                        <div class="flex items-center justify-between text-sm">
                                        <span class="flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full bg-orange-{{ max(100, 500 - ($index * 100)) }}"></span>
                                            {{ $label }}
                                        </span>
                            <span class="font-bold text-gray-700">{{ number_format($chartData[$index] ?? 0, 2, ',', '.') }} €</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
