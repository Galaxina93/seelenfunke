<div>
    <div class="p-8 bg-gray-50 border-t border-gray-100">

        {{-- Success Message --}}
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

        <div class="max-w-3xl mx-auto space-y-4">

            {{-- AUTO-FILL NACHRICHT (Die blaue Box) --}}
            @if($isAutoFilled)
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl flex items-center gap-3 shadow-sm animate-pulse">
                    <div class="bg-blue-100 p-2 rounded-full">
                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold text-sm">E-Rechnung erkannt!</p>
                        <p class="text-xs text-blue-600">Daten für Betrag, Datum und Rechnungsnummer wurden automatisch übernommen.</p>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <input type="text" wire:model="specialTitle" placeholder="Titel / Verwendungszweck" class="w-full rounded-xl border-gray-200 bg-white py-3 px-4 shadow-sm focus:border-orange-400 focus:ring focus:ring-orange-200 transition-all text-sm font-medium">
                    @error('specialTitle') <span class="text-xs text-red-500 ml-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <input type="text" wire:model="specialAmount" placeholder="Betrag (z.B. 49.99)" class="w-full rounded-xl border-gray-200 bg-white py-3 px-4 shadow-sm focus:border-orange-400 focus:ring focus:ring-orange-200 transition-all text-sm font-medium">
                    @error('specialAmount') <span class="text-xs text-red-500 ml-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <input type="text" list="category-list" wire:model="specialCategory" placeholder="Kategorie (z.B. Tanken)" class="w-full rounded-xl border-gray-200 bg-white py-3 px-4 shadow-sm focus:border-orange-400 focus:ring focus:ring-orange-200 transition-all text-sm font-medium">
                    <datalist id="category-list">
                        @foreach($categories as $catName)
                            <option value="{{ $catName }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <input type="date" wire:model="specialDate" class="w-full rounded-xl border-gray-200 bg-white py-3 px-4 shadow-sm focus:border-orange-400 focus:ring focus:ring-orange-200 transition-all text-sm font-medium text-gray-500">
                    @error('specialDate') <span class="text-xs text-red-500 ml-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <input type="text" wire:model="specialLocation" placeholder="Ort / Geschäft (Optional)" class="w-full rounded-xl border-gray-200 bg-white py-3 px-4 shadow-sm focus:border-orange-400 focus:ring focus:ring-orange-200 transition-all text-sm font-medium">

            {{-- Business Toggle --}}
            <div x-data="{ open: @entangle('specialIsBusiness') }" class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-gray-600">Geschäftliche Ausgabe?</span>
                    <button type="button"
                            x-on:click="open = !open; $wire.set('specialIsBusiness', open)"
                            :class="open ? 'bg-orange-500' : 'bg-gray-200'"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                        <span aria-hidden="true" :class="open ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>

                @if($specialIsBusiness)
                    <div class="mt-4 grid grid-cols-2 gap-4 animate-fade-in">
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
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2">
                        Belege / E-Rechnungen (ZUGFeRD/XRechnung)
                    </label>
                    <input type="file" wire:model="specialFiles" multiple accept=".pdf,.xml,.jpg,.png,.jpeg" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100">

                    <div wire:loading wire:target="specialFiles" class="text-xs text-orange-500 mt-1 flex items-center gap-1">
                        <svg class="animate-spin h-4 w-4 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Analysiere Datei...
                    </div>
                </div>
            </div>

            <button wire:click="createSpecial" class="w-full bg-orange-500 text-white px-8 py-3 rounded-xl text-base font-bold hover:bg-orange-600 transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                Speichern
            </button>

        </div>
    </div>
</div>
