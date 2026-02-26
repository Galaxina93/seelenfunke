<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">

    {{-- Linke Spalte: Tabelle --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">
            <div class="p-6 md:p-8 border-b border-gray-800 bg-gray-950 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-5 shadow-inner">
                <h2 class="text-xl font-serif font-bold text-white tracking-wide">Buchungsjournal</h2>

                {{-- Suche --}}
                <div class="relative w-full sm:w-72 group">
                    <input type="text" wire:model.live.debounce.300ms="specialSearch"
                           class="w-full pl-12 pr-4 py-3 bg-gray-950 border border-gray-800 rounded-xl text-sm text-white focus:bg-black focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 transition-all shadow-inner outline-none placeholder-gray-600"
                           placeholder="Suchen nach Titel...">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-500 group-focus-within:text-orange-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto w-full no-scrollbar pb-2">
                <table class="w-full text-left min-w-[700px] border-collapse">
                    <thead>
                    <tr class="bg-gray-900/50 text-gray-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-800">
                        <th class="px-6 md:px-8 py-5">Datum</th>
                        <th class="px-4 py-5">Titel / Ort</th>
                        <th class="px-4 py-5">Kategorie</th>
                        <th class="px-4 py-5 text-right">Betrag</th>
                        <th class="px-4 py-5 text-center">Beleg</th>
                        <th class="px-6 md:px-8 py-5 text-center">Aktion</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                    @forelse($specials as $special)
                        <tr class="hover:bg-gray-800/30 transition-colors group">

                            @if($editingSpecialId === $special->id)
                                <td colspan="6" class="p-6 md:p-8 bg-gray-950/80 shadow-inner">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                                        <div>
                                            <label class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1 block">Titel</label>
                                            <input type="text" wire:model="editSpecialTitle" class="bg-gray-900 border border-gray-700 text-white rounded-xl shadow-inner text-sm w-full p-3 focus:bg-black focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all" placeholder="Titel">
                                        </div>
                                        <div>
                                            <label class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1 block">Betrag</label>
                                            <input type="number" step="0.01" wire:model="editSpecialAmount" class="bg-gray-900 border border-gray-700 text-white rounded-xl shadow-inner text-sm w-full p-3 focus:bg-black focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all font-mono" placeholder="Betrag">
                                        </div>
                                        <div>
                                            <label class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1 block">Datum</label>
                                            <input type="date" wire:model="editSpecialDate" class="bg-gray-900 border border-gray-700 text-gray-400 rounded-xl shadow-inner text-sm w-full p-3 focus:bg-black focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)] cursor-pointer">
                                        </div>
                                        <div>
                                            <label class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1 block">Kategorie</label>
                                            <input type="text" list="cat-edit-list" wire:model="editSpecialCategory" class="bg-gray-900 border border-gray-700 text-white rounded-xl shadow-inner text-sm w-full p-3 focus:bg-black focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all" placeholder="Kategorie">
                                            <datalist id="cat-edit-list">
                                                @foreach($this->manageableCategories as $cat)
                                                    <option value="{{ $cat->name }}">
                                                @endforeach
                                            </datalist>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-5 mb-6 bg-gray-900 p-4 rounded-[1.5rem] border border-gray-800 shadow-inner">
                                        <label class="flex items-center gap-3 text-xs font-bold text-gray-400 cursor-pointer group-checkbox">
                                            <div class="relative flex items-center">
                                                <input type="checkbox" wire:model.live="editSpecialIsBusiness" class="peer sr-only">
                                                <div class="w-5 h-5 bg-gray-950 border-2 border-gray-700 rounded transition-all peer-checked:bg-orange-500 peer-checked:border-orange-500"></div>
                                                <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <span class="uppercase tracking-widest text-[9px] group-checkbox-hover:text-white transition-colors">Geschäftlich?</span>
                                        </label>
                                        @if($editSpecialIsBusiness)
                                            <input type="text" wire:model="editSpecialInvoiceNumber" placeholder="Rechnungsnr." class="text-xs rounded-lg border border-gray-700 bg-gray-950 text-white p-2.5 w-40 focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none shadow-inner font-mono">
                                        @endif
                                    </div>

                                    <div class="mb-6">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2 ml-1 block">Dateien</label>

                                        {{-- Liste der bereits vorhandenen Dateien --}}
                                        @if(count($editSpecialExistingFiles) > 0)
                                            <div class="flex flex-wrap gap-3 mb-4">
                                                @foreach($editSpecialExistingFiles as $index => $path)
                                                    <div class="relative group flex items-center gap-3 px-4 py-2 bg-gray-900 border border-gray-800 rounded-xl text-xs shadow-inner">
                                                        {{-- Link zur Datei --}}
                                                        <a href="{{ Storage::url($path) }}" target="_blank" class="text-blue-400 hover:text-white transition-colors truncate max-w-[150px] font-bold">
                                                            {{ basename($path) }}
                                                        </a>

                                                        {{-- Löschen Button (Rotes X) --}}
                                                        <button type="button"
                                                                wire:click="removeExistingFile({{ $index }})"
                                                                class="text-gray-500 hover:text-red-400 transition-colors focus:outline-none p-1 rounded hover:bg-gray-800">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Upload Feld für NEUE Dateien --}}
                                        <div class="relative w-full">
                                            <input type="file" wire:model="newEditFiles" multiple class="block w-full text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-orange-500/10 file:text-orange-400 file:border file:border-orange-500/20 hover:file:bg-orange-500/20 file:transition-colors file:cursor-pointer cursor-pointer border border-gray-800 rounded-xl bg-gray-950 p-1">
                                        </div>

                                        {{-- Ladeindikator --}}
                                        <div wire:loading wire:target="newEditFiles" class="text-[10px] font-bold text-orange-400 mt-2 uppercase tracking-widest flex items-center gap-2">
                                            <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                            Dateien werden hochgeladen...
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-800">
                                        <button wire:click="cancelEditSpecial" class="px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-gray-400 bg-gray-900 border border-gray-800 rounded-xl hover:bg-gray-800 hover:text-white transition-colors shadow-inner">Abbrechen</button>
                                        <button wire:click="saveSpecial" class="px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-gray-900 bg-emerald-500 rounded-xl hover:bg-emerald-400 hover:shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:scale-[1.02] transition-all">Speichern</button>
                                    </div>
                                </td>
                            @else
                                <td class="px-6 md:px-8 py-5 text-sm text-gray-400 whitespace-nowrap font-medium align-middle">
                                    {{ \Carbon\Carbon::parse($special->execution_date)->format('d.m.Y') }}
                                </td>
                                <td class="px-4 py-5 align-middle">
                                    <div class="font-bold text-white text-base tracking-wide">{{ $special->title }}</div>
                                    @if($special->location)
                                        <div class="text-[10px] font-medium text-gray-500 flex items-center gap-1.5 mt-1 uppercase tracking-wider">
                                            <svg class="w-3 h-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            {{ $special->location }}
                                        </div>
                                    @endif
                                    @if($special->is_business)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[8px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-400 border border-blue-500/20 mt-2 shadow-inner">Business</span>
                                    @endif
                                </td>
                                <td class="px-4 py-5 align-middle">
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest bg-gray-900 border border-gray-800 text-gray-400 shadow-inner truncate max-w-[150px]">{{ $special->category }}</span>
                                </td>
                                <td class="px-4 py-5 text-right font-mono font-bold text-lg whitespace-nowrap align-middle {{ $special->amount < 0 ? 'text-red-400 drop-shadow-[0_0_8px_currentColor]' : 'text-emerald-400' }}">
                                    {{ number_format($special->amount, 2, ',', '.') }} €
                                </td>
                                <td class="px-4 py-5 text-center align-middle">
                                    @if(!empty($special->file_paths) && count($special->file_paths) > 0)
                                        <div class="flex justify-center -space-x-2">
                                            @foreach($special->file_paths as $path)
                                                <a href="{{ Storage::url($path) }}" target="_blank" class="w-10 h-10 rounded-full bg-gray-900 border-2 border-gray-800 flex items-center justify-center text-gray-500 hover:text-orange-400 hover:border-orange-500/50 hover:bg-gray-800 hover:z-10 transition-all shadow-lg" title="Beleg ansehen">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="relative group/upload flex justify-center">
                                            <label class="cursor-pointer text-gray-600 hover:text-orange-400 bg-gray-950 p-2.5 rounded-full border border-gray-800 transition-all shadow-inner hover:shadow-[0_0_15px_rgba(249,115,22,0.15)]">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                                <input type="file" class="hidden" wire:model="quickUploadFile" wire:click="$set('uploadingMissingSpecialId', '{{ $special->id }}')">
                                            </label>
                                            @if($uploadingMissingSpecialId === $special->id)
                                                <div wire:loading wire:target="quickUploadFile" class="absolute -top-1 -right-1 bg-gray-900 rounded-full p-0.5 border border-gray-800 shadow-sm">
                                                    <svg class="animate-spin h-4 w-4 text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 md:px-8 py-5 text-center align-middle">
                                    <div class="flex items-center justify-center gap-3 opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <button wire:click="editSpecial('{{ $special->id }}')" class="p-2.5 text-gray-500 bg-gray-950 border border-gray-800 rounded-xl hover:text-blue-400 hover:border-blue-500/30 transition-all shadow-inner" title="Bearbeiten">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                        <button wire:click="deleteSpecial('{{ $special->id }}')" wire:confirm="Wirklich löschen?" class="p-2.5 text-gray-500 bg-gray-950 border border-gray-800 rounded-xl hover:text-red-400 hover:border-red-500/30 transition-all shadow-inner" title="Löschen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-16 text-center text-gray-500 font-serif italic text-lg">Keine Einträge gefunden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination im Dark Mode Look --}}
            @if($specials->hasPages())
                <div class="p-5 border-t border-gray-800 bg-gray-900/30">
                    {{ $specials->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Rechte Spalte: Chart --}}
    <div class="space-y-6">
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8 sticky top-24">
            <h3 class="text-sm font-serif font-bold text-white mb-6 tracking-wide border-b border-gray-800 pb-4">Kostenverteilung (Top 5)</h3>
            <div wire:ignore class="relative h-64 w-full">
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="mt-8 space-y-4">
                @foreach($chartLabels as $index => $label)
                    @if($index < 5)
                        @php
                            // Dynamische Opacity für die Liste, um den Donut-Chart Farben zu ähneln
                            $opacities = ['100', '80', '60', '40', '20'];
                            $opacity = $opacities[$index] ?? '10';
                        @endphp
                        <div class="flex items-center justify-between text-sm p-3 bg-gray-950 rounded-xl border border-gray-800 shadow-inner group hover:border-gray-700 transition-colors">
                            <span class="flex items-center gap-3">
                                <span class="w-3 h-3 rounded-full bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.8)]" style="opacity: {{ (int)$opacity / 100 }};"></span>
                                <span class="font-bold text-gray-300 group-hover:text-white transition-colors">{{ $label }}</span>
                            </span>
                            <span class="font-mono font-bold text-white whitespace-nowrap">{{ number_format($chartData[$index] ?? 0, 2, ',', '.') }} €</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
