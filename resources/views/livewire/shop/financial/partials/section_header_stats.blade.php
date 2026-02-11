<div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">

    {{-- OBERER BEREICH: STATISTIKEN --}}
    <div class="p-8 border-b border-gray-100 relative">
        {{-- Export Button oben rechts --}}
        <div class="absolute top-2 right-6">
            <button wire:click="downloadTaxExport" class="flex items-center gap-2 bg-gradient-to-r from-gray-800 to-gray-700 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider hover:shadow-lg transition transform hover:-translate-y-0.5">
                <svg xmlns="[http://www.w3.org/2000/svg](http://www.w3.org/2000/svg)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export {{ $selectedMonth }}/{{ $selectedYear }}
            </button>
        </div>

        <div class="flex flex-col md:flex-row items-center justify-between gap-8">
            {{-- Linke Seite der Stats: Hauptzahl --}}
            <div class="flex-1 text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-3 mb-1">
                    <span class="text-sm font-bold text-gray-400 uppercase tracking-wider">Frei Verfügbar</span>
                    <label class="inline-flex items-center cursor-pointer group">
                        <input type="checkbox" wire:model.live="excludeSpecialExpenses" class="sr-only peer">
                        <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:bg-teal-500 after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                        <span class="ms-2 text-xs text-gray-400 group-hover:text-gray-600 transition select-none">Ohne Sonderausgaben</span>
                    </label>
                </div>
                <div class="text-5xl font-extrabold tracking-tight {{ ($excludeSpecialExpenses ? ($stats['total_budget'] + $stats['fixed_expenses']) : $stats['available']) >= 0 ? 'text-teal-600' : 'text-red-500' }}">
                    {{ number_format($excludeSpecialExpenses ? ($stats['total_budget'] + $stats['fixed_expenses']) : $stats['available'], 2, ',', '.') }} €
                </div>
                <p class="text-xs text-gray-400 mt-2">Inkl. Shop-Umsatz: <span class="font-bold text-teal-600">+{{ number_format($stats['shop_income'], 2, ',', '.') }} €</span></p>
            </div>

            {{-- Rechte Seite der Stats --}}
            <div class="flex flex-col sm:flex-row gap-8 text-center sm:text-left">
                <div>
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Monatsbudget</div>
                    <div class="text-xl font-bold text-emerald-600">+ {{ number_format($stats['total_budget'], 2, ',', '.') }} €</div>
                </div>
                <div>
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Fixkosten</div>
                    <div class="text-xl font-bold text-rose-500">{{ number_format($stats['fixed_expenses'], 2, ',', '.') }} €</div>
                </div>
                <div class="transition-opacity duration-300 {{ $excludeSpecialExpenses ? 'opacity-30 grayscale' : 'opacity-100' }}">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Sonderausgaben</div>
                    <div class="text-xl font-bold text-orange-500">{{ number_format($stats['special_expenses'], 2, ',', '.') }} €</div>
                </div>
            </div>
        </div>
    </div>

    {{-- UNTERER BEREICH: SCHNELLERFASSUNG --}}
    <div class="p-8 bg-gray-50">
        <h3 class="text-base font-bold text-gray-700 text-center mb-6 flex items-center justify-center gap-2">
            <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Schnellerfassung Sonderausgabe
        </h3>

        <div class="max-w-3xl mx-auto flex flex-col gap-4">
            <div class="w-full">
                <input type="text" wire:model="specialTitle" placeholder="Was? (z.B. Tanken)" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
                @error('specialTitle') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="relative w-full">
                    <input list="cat-opts" wire:model="specialCategory" placeholder="Kategorie" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
                    <datalist id="cat-opts">@foreach($categories as $cat)<option value="{{ $cat }}"></option>@endforeach</datalist>
                </div>
                <input type="text" wire:model="specialLocation" placeholder="Wo?" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="relative w-full">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">€</span>
                    <input type="number" step="0.01" wire:model="specialAmount" placeholder="0.00" class="pl-8 w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 font-mono bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
                    @error('specialAmount') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <input type="date" wire:model="specialDate" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 cursor-pointer bg-white shadow-sm text-gray-800 transition hover:border-orange-300">
                    @error('specialDate') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Gewerbe Toggle Area --}}
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 mb-2">
                    <input type="checkbox" wire:model.live="specialIsBusiness" id="bizCheck" class="rounded text-orange-500 focus:ring-orange-400 border-gray-300 w-4 h-4">
                    <label for="bizCheck" class="text-sm font-bold text-gray-700 select-none">Als Gewerbliche Ausgabe markieren</label>
                </div>

                @if($specialIsBusiness)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3 animate-fade-in-down">
                        <input type="text" wire:model="specialInvoiceNumber" placeholder="Rechnungsnummer" class="text-sm rounded-lg border-gray-300 focus:ring-orange-400 w-full">
                        <select wire:model="specialTaxRate" class="text-sm rounded-lg border-gray-300 focus:ring-orange-400 w-full">
                            <option value="19">19% MwSt.</option>
                            <option value="7">7% MwSt.</option>
                            <option value="0">0% / Steuerfrei</option>
                        </select>
                    </div>
                @endif

                {{-- Dateiupload --}}
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Belege / Rechnungen</label>
                    <input type="file" wire:model="specialFiles" multiple class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">
                    <div wire:loading wire:target="specialFiles" class="text-xs text-orange-500 mt-1">Lade Dateien...</div>
                </div>
            </div>

            <button wire:click="createSpecial" class="w-full bg-orange-500 text-white px-8 py-3 rounded-xl text-base font-bold hover:bg-orange-600 transition shadow-lg shadow-orange-100 transform hover:scale-[1.01] active:scale-95">
                Eintrag speichern
            </button>
        </div>
    </div>
</div>
