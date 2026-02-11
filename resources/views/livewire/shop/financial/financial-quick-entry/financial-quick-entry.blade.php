<div>
    <div class="p-8 bg-gray-50 border-t border-gray-100">

        {{-- Success Message lokal anzeigen, damit man sieht, dass es geklappt hat --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="mb-4 bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center gap-2 text-sm shadow-sm animate-fade-in-down">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('success') }}
            </div>
        @endif

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
                    <input list="cat-opts-quick" wire:model="specialCategory" placeholder="Kategorie" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
                    <datalist id="cat-opts-quick">@foreach($this->categories as $cat)<option value="{{ $cat }}"></option>@endforeach</datalist>
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
                    <input type="checkbox" wire:model.live="specialIsBusiness" id="bizCheckQuick" class="rounded text-orange-500 focus:ring-orange-400 border-gray-300 w-4 h-4">
                    <label for="bizCheckQuick" class="text-sm font-bold text-gray-700 select-none">Als Gewerbliche Ausgabe markieren</label>
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
