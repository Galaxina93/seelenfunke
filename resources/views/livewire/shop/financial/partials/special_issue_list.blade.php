<div class="space-y-6 w-full">

    {{-- Volle Breite: Tabelle & Header --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full">

        {{-- Neuer Kompakter Header mit integriertem Chart --}}
        <div class="p-6 md:p-8 border-b border-gray-800 bg-gray-950 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6 shadow-inner w-full">

            {{-- Links: Titel & Suche --}}
            <div class="flex-1 w-full xl:max-w-md">
                <h2 class="text-xl font-serif font-bold text-white tracking-wide mb-4 flex items-center gap-3">
                    <svg class="w-6 h-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Buchungsjournal
                </h2>
                <div class="relative w-full group">
                    <input type="text" wire:model.live.debounce.300ms="specialSearch"
                           class="w-full pl-12 pr-4 py-3.5 bg-gray-900 border border-gray-800 rounded-xl text-sm text-white focus:bg-black focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 transition-all shadow-inner outline-none placeholder-gray-500 font-medium"
                           placeholder="Suchen nach Titel, Ort oder Kategorie...">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-500 group-focus-within:text-orange-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
            </div>

            {{-- Rechts: Kompakte Kostenverteilung (Vergrößert für Tooltips) --}}
            <div class="flex items-center gap-4 sm:gap-6 bg-gray-900/50 p-4 rounded-2xl border border-gray-800 shadow-inner w-full xl:w-auto shrink-0">
                <div wire:ignore class="relative w-32 h-32 sm:w-36 sm:h-36 shrink-0 flex items-center justify-center">
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="flex-1 xl:w-64 space-y-2">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2 border-b border-gray-800 pb-1">Top Kostenverteilung</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                        @foreach($chartLabels as $index => $label)
                            @if($index < 4)
                                @php
                                    $opacities = ['100', '80', '60', '40'];
                                    $opacity = $opacities[$index] ?? '20';
                                @endphp
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-2 truncate pr-2">
                                        <span class="w-2 h-2 rounded-full bg-orange-500 shrink-0" style="opacity: {{ (int)$opacity / 100 }};"></span>
                                        <span class="font-bold text-gray-300 truncate" title="{{ $label }}">{{ \Illuminate\Support\Str::limit($label, 12) }}</span>
                                    </span>
                                    <span class="font-mono font-bold text-white">{{ number_format($chartData[$index] ?? 0, 0, ',', '.') }}€</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div>
            {{-- ========================================== --}}
            {{-- DESKTOP ANSICHT (Tabelle mit großen Inline-Inputs) --}}
            {{-- ========================================== --}}
            <div class="hidden md:block overflow-x-visible w-full pb-4">
                <table class="w-full text-left border-collapse table-fixed">
                    <thead>
                    <tr class="bg-gray-900/50 text-gray-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-800 shadow-inner">
                        <th class="px-6 py-5 w-[160px]">Datum</th>
                        <th class="px-4 py-5 w-[35%]">Titel / Ort</th>
                        <th class="px-4 py-5 w-[180px]">Kategorie</th>
                        <th class="px-4 py-5 w-[160px] text-right">Betrag</th>
                        <th class="px-4 py-5 w-[140px] text-center">Belege</th>
                        <th class="px-6 py-5 w-[80px] text-center"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                    @forelse($specials as $special)
                        <tr class="hover:bg-gray-800/20 transition-colors group">
                            {{-- Datum --}}
                            <td class="px-6 py-4 align-top">
                                <input type="date"
                                       value="{{ \Carbon\Carbon::parse($special->execution_date)->format('Y-m-d') }}"
                                       wire:change="updateSpecialField('{{ $special->id }}', 'execution_date', $event.target.value)"
                                       class="w-full bg-gray-900/40 border border-transparent hover:border-gray-700 hover:bg-gray-900 focus:bg-gray-950 focus:border-orange-500 rounded-xl text-sm font-bold text-gray-300 px-3 py-2.5 outline-none transition-all cursor-pointer shadow-inner [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)]">
                            </td>

                            {{-- Titel & Ort & Business --}}
                            <td class="px-4 py-4 align-top">
                                <div class="flex flex-col gap-2">
                                    <input type="text"
                                           value="{{ $special->title }}"
                                           wire:change="updateSpecialField('{{ $special->id }}', 'title', $event.target.value)"
                                           placeholder="Titel eingeben..."
                                           class="w-full bg-gray-900/40 border border-transparent hover:border-gray-700 hover:bg-gray-900 focus:bg-gray-950 focus:border-orange-500 rounded-xl font-bold text-white text-base tracking-wide px-3 py-2.5 outline-none transition-all shadow-inner placeholder-gray-600">

                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 relative flex items-center">
                                            <svg class="w-4 h-4 text-gray-500 absolute left-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <input type="text"
                                                   value="{{ $special->location }}"
                                                   wire:change="updateSpecialField('{{ $special->id }}', 'location', $event.target.value)"
                                                   placeholder="Ort hinzufügen..."
                                                   class="w-full bg-gray-900/30 border border-transparent hover:border-gray-700 hover:bg-gray-900 focus:bg-gray-950 focus:border-orange-500 rounded-lg text-xs font-medium text-gray-400 uppercase tracking-wider pl-9 pr-3 py-2 outline-none transition-all shadow-inner">
                                        </div>

                                        <label class="flex items-center gap-2 cursor-pointer group-checkbox bg-gray-900/30 hover:bg-gray-900 px-3 py-2 rounded-lg border border-transparent hover:border-gray-700 transition-all">
                                            <div class="relative flex items-center">
                                                <input type="checkbox"
                                                       @checked($special->is_business)
                                                       wire:change="updateSpecialField('{{ $special->id }}', 'is_business', $event.target.checked)"
                                                       class="peer sr-only">
                                                <div class="w-4 h-4 bg-gray-950 border border-gray-600 rounded transition-all peer-checked:bg-blue-500 peer-checked:border-blue-500 shadow-inner"></div>
                                                <svg class="absolute w-3 h-3 left-[2px] top-[2px] text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 peer-checked:text-blue-400 transition-colors">B2B</span>
                                        </label>

                                        @if($special->is_business)
                                            <input type="text"
                                                   value="{{ $special->invoice_number }}"
                                                   wire:change="updateSpecialField('{{ $special->id }}', 'invoice_number', $event.target.value)"
                                                   placeholder="Rechnungsnr."
                                                   class="w-28 bg-gray-900/30 border border-transparent hover:border-gray-700 hover:bg-gray-900 focus:bg-gray-950 focus:border-blue-500 rounded-lg text-[10px] font-mono text-blue-400 px-3 py-2 outline-none transition-all shadow-inner">
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Kategorie --}}
                            <td class="px-4 py-4 align-top">
                                <input type="text" list="cat-list-{{$special->id}}"
                                       value="{{ $special->category }}"
                                       wire:change="updateSpecialField('{{ $special->id }}', 'category', $event.target.value)"
                                       placeholder="Kategorie..."
                                       class="w-full bg-gray-900/40 border border-transparent hover:border-gray-700 hover:bg-gray-900 focus:bg-gray-950 focus:border-orange-500 rounded-xl text-xs font-black uppercase tracking-widest text-gray-400 px-3 py-2.5 outline-none transition-all shadow-inner">
                                <datalist id="cat-list-{{$special->id}}">
                                    @foreach($this->manageableCategories as $cat)
                                        <option value="{{ $cat->name }}">
                                    @endforeach
                                </datalist>
                            </td>

                            {{-- Betrag --}}
                            <td class="px-4 py-4 align-top text-right">
                                <div class="flex items-center justify-end bg-gray-900/40 border border-transparent hover:border-gray-700 hover:bg-gray-900 focus-within:bg-gray-950 focus-within:border-orange-500 rounded-xl transition-all shadow-inner overflow-hidden pr-3">
                                    <input type="number" step="0.01"
                                           value="{{ $special->amount }}"
                                           wire:change="updateSpecialField('{{ $special->id }}', 'amount', $event.target.value)"
                                           class="w-full bg-transparent text-right font-mono font-bold text-lg {{ $special->amount < 0 ? 'text-red-400' : 'text-emerald-400' }} px-3 py-2 outline-none">
                                    <span class="text-gray-500 font-bold shrink-0">€</span>
                                </div>
                            </td>

                            {{-- Belege (Inline Upload & Löschen) --}}
                            <td class="px-4 py-4 align-top text-center">
                                <div class="flex flex-col items-center gap-2">
                                    @php
                                        $files = is_string($special->file_paths) ? json_decode($special->file_paths, true) : $special->file_paths;
                                    @endphp
                                    @if(!empty($files) && count($files) > 0)
                                        <div class="flex justify-center -space-x-2">
                                            @foreach($files as $index => $path)
                                                <div class="relative group/file">
                                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($path) }}" target="_blank" class="w-9 h-9 rounded-full bg-gray-900 border-2 border-gray-700 flex items-center justify-center text-gray-400 hover:border-orange-500 hover:text-orange-400 hover:z-10 transition-all shadow-md" title="Beleg ansehen">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                                    </a>
                                                    {{-- Rotes X zum Löschen des einzelnen Belegs --}}
                                                    <button type="button" wire:click="deleteSpecialFile('{{ $special->id }}', {{ $index }})" wire:confirm="Beleg löschen?" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full items-center justify-center hidden group-hover/file:flex shadow-lg hover:scale-110 transition-transform z-20">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="relative group/upload flex justify-center w-full">
                                        <label class="w-full cursor-pointer text-[9px] font-black uppercase tracking-widest text-gray-400 bg-gray-900/50 hover:bg-gray-900 py-2 rounded-lg border border-transparent hover:border-gray-700 transition-all hover:text-orange-400 flex items-center justify-center gap-1 shadow-inner">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                            Upload
                                            <input type="file" class="hidden" wire:model="quickUploadFile" wire:click="$set('uploadingMissingSpecialId', '{{ $special->id }}')">
                                        </label>
                                        @if($uploadingMissingSpecialId === $special->id)
                                            <div wire:loading wire:target="quickUploadFile" class="absolute -top-1 -right-1 bg-gray-900 rounded-full p-1 border border-gray-800 shadow-sm z-10">
                                                <svg class="animate-spin h-4 w-4 text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Aktion (Nur noch Komplett Löschen) --}}
                            <td class="px-6 py-4 align-top text-center">
                                <button wire:click="deleteSpecial('{{ $special->id }}')" wire:confirm="Eintrag komplett löschen?" class="p-2.5 mt-1 text-gray-600 bg-gray-950 border border-gray-800 rounded-xl hover:text-red-400 hover:border-red-500/30 transition-all shadow-inner opacity-0 group-hover:opacity-100" title="Buchung löschen">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-16 text-center text-gray-500 font-serif italic text-lg">Keine Einträge gefunden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ========================================== --}}
            {{-- MOBILE ANSICHT (Inline Cards) --}}
            {{-- ========================================== --}}
            <div class="block md:hidden divide-y divide-gray-800/50">
                @forelse($specials as $special)
                    <div class="p-5 hover:bg-gray-800/30 transition-colors">

                        {{-- Mobile Titel & Betrag --}}
                        <div class="flex justify-between items-start gap-3 mb-3">
                            <div class="flex-1 min-w-0">
                                <input type="text"
                                       value="{{ $special->title }}"
                                       wire:change="updateSpecialField('{{ $special->id }}', 'title', $event.target.value)"
                                       placeholder="Titel..."
                                       class="w-full bg-gray-900/40 border border-transparent hover:border-gray-700 focus:bg-gray-950 focus:border-orange-500 rounded-xl text-base font-bold text-white px-3 py-2 outline-none transition-all shadow-inner">
                            </div>
                            <div class="shrink-0 flex items-center bg-gray-900/40 border border-transparent hover:border-gray-700 focus-within:bg-gray-950 focus-within:border-orange-500 rounded-xl shadow-inner transition-all overflow-hidden pr-2">
                                <input type="number" step="0.01"
                                       value="{{ $special->amount }}"
                                       wire:change="updateSpecialField('{{ $special->id }}', 'amount', $event.target.value)"
                                       class="bg-transparent text-right font-mono font-bold text-base w-24 {{ $special->amount < 0 ? 'text-red-400' : 'text-emerald-400' }} px-2 py-2 outline-none">
                                <span class="text-gray-500 font-bold ml-1">€</span>
                            </div>
                        </div>

                        {{-- Mobile Datum & Ort --}}
                        <div class="flex flex-col gap-2 mb-3">
                            <input type="date"
                                   value="{{ \Carbon\Carbon::parse($special->execution_date)->format('Y-m-d') }}"
                                   wire:change="updateSpecialField('{{ $special->id }}', 'execution_date', $event.target.value)"
                                   class="w-full bg-gray-900/40 border border-transparent hover:border-gray-700 focus:bg-gray-950 focus:border-orange-500 rounded-xl text-sm font-medium text-gray-400 px-3 py-2 outline-none transition-all shadow-inner [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)] cursor-pointer">

                            <div class="flex items-center relative">
                                <svg class="w-4 h-4 text-gray-500 absolute left-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <input type="text"
                                       value="{{ $special->location }}"
                                       wire:change="updateSpecialField('{{ $special->id }}', 'location', $event.target.value)"
                                       placeholder="Ort hinzufügen..."
                                       class="w-full bg-gray-900/30 border border-transparent hover:border-gray-700 focus:bg-gray-950 focus:border-orange-500 rounded-xl text-xs font-medium text-gray-400 uppercase tracking-wider pl-9 pr-3 py-2 outline-none transition-all shadow-inner">
                            </div>
                        </div>

                        {{-- Mobile Bottom Bar --}}
                        <div class="flex flex-col gap-3 mt-4 pt-4 border-t border-gray-800/50">

                            {{-- FIX 2: Container hat jetzt gap-3, das Input nimmt "flex-1 w-full" statt w-32 --}}
                            <div class="flex items-center justify-between w-full gap-3">
                                <div class="flex-1 min-w-0">
                                    <input type="text" list="cat-list-mobile-{{$special->id}}"
                                           value="{{ $special->category }}"
                                           wire:change="updateSpecialField('{{ $special->id }}', 'category', $event.target.value)"
                                           class="w-full bg-gray-900/40 border border-transparent hover:border-gray-700 focus:bg-gray-950 focus:border-orange-500 rounded-xl text-xs font-black uppercase tracking-widest text-gray-400 px-3 py-2 outline-none transition-all shadow-inner">
                                    <datalist id="cat-list-mobile-{{$special->id}}">
                                        @foreach($this->manageableCategories as $cat)
                                            <option value="{{ $cat->name }}">
                                        @endforeach
                                    </datalist>
                                </div>

                                <label class="shrink-0 flex items-center gap-2 cursor-pointer group-checkbox bg-gray-900/30 hover:bg-gray-900 px-3 py-2 rounded-xl border border-transparent hover:border-gray-700 transition-all">
                                    <div class="relative flex items-center">
                                        <input type="checkbox"
                                               @checked($special->is_business)
                                               wire:change="updateSpecialField('{{ $special->id }}', 'is_business', $event.target.checked)"
                                               class="peer sr-only">
                                        <div class="w-4 h-4 bg-gray-950 border border-gray-600 rounded transition-all peer-checked:bg-blue-500 peer-checked:border-blue-500 shadow-inner"></div>
                                        <svg class="absolute w-3 h-3 left-[2px] top-[2px] text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 peer-checked:text-blue-400 transition-colors">B2B</span>
                                </label>
                            </div>

                            {{-- FIX 1: Belege auch mobil anzeigen (immer klickbares rotes X für Touch-Geräte) --}}
                            @php
                                $files = is_string($special->file_paths) ? json_decode($special->file_paths, true) : $special->file_paths;
                            @endphp
                            @if(!empty($files) && count($files) > 0)
                                <div class="flex flex-wrap gap-3 py-2">
                                    @foreach($files as $index => $path)
                                        <div class="relative">
                                            <a href="{{ \Illuminate\Support\Facades\Storage::url($path) }}" target="_blank" class="w-10 h-10 rounded-xl bg-gray-900 border border-gray-700 flex items-center justify-center text-gray-400 active:border-orange-500 active:text-orange-400 transition-all shadow-md">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                            </a>
                                            <button type="button" wire:click="deleteSpecialFile('{{ $special->id }}', {{ $index }})" wire:confirm="Beleg löschen?" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center shadow-lg active:scale-95 transition-transform z-20">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex items-center justify-between w-full mt-1">
                                <div class="flex-1 pr-4">
                                    <div class="relative group/upload w-full">
                                        <label class="w-full cursor-pointer p-2.5 text-xs font-black uppercase tracking-widest text-gray-400 bg-gray-950 border border-gray-800 rounded-xl shadow-inner hover:text-orange-400 transition-colors flex items-center justify-center gap-2">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                            Beleg hochladen
                                            <input type="file" class="hidden" wire:model="quickUploadFile" wire:click="$set('uploadingMissingSpecialId', '{{ $special->id }}')">
                                        </label>
                                        @if($uploadingMissingSpecialId === $special->id)
                                            <div wire:loading wire:target="quickUploadFile" class="absolute inset-0 bg-gray-900 rounded-xl border border-orange-500/50 flex items-center justify-center z-10 text-orange-400">
                                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <button wire:click="deleteSpecial('{{ $special->id }}')" wire:confirm="Eintrag wirklich löschen?" class="shrink-0 p-2.5 text-gray-500 bg-gray-950 border border-gray-800 rounded-xl shadow-inner hover:text-red-400 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>

                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Keine Buchungen vorhanden</span>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Pagination --}}
        @if($specials->hasPages())
            <div class="p-5 border-t border-gray-800 bg-gray-950/30">
                {{ $specials->links() }}
            </div>
        @endif
    </div>
</div>
