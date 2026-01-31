<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <h3 class="text-lg font-serif font-bold text-gray-900">Versand & Lieferung</h3>

            {{-- Tooltip: Physisch --}}
            <div x-data="{ show: false }" class="relative inline-block ml-2">
                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                </button>
                <div x-show="show" x-cloak x-transition.opacity class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs leading-relaxed rounded shadow-lg z-50 text-center">
                    {{ $infoTexts['is_physical'] }}
                    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="space-y-6">
        {{-- Toggle Switch --}}
        <div class="flex items-center gap-3">
            <button type="button"
                    wire:click="$toggle('is_physical_product')"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 {{ $is_physical_product ? 'bg-green-600' : 'bg-gray-200' }}"
                    role="switch"
                    aria-checked="{{ $is_physical_product ? 'true' : 'false' }}">
                <span class="sr-only">Physisches Produkt aktivieren</span>
                <span aria-hidden="true"
                      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $is_physical_product ? 'translate-x-5' : 'translate-x-0' }}">
            </span>
            </button>
            <label wire:click="$toggle('is_physical_product')" class="text-gray-800 font-medium cursor-pointer select-none">
                Dies ist ein physisches Produkt
            </label>
        </div>

        {{-- Detailbereich --}}
        @if($is_physical_product)
            <div class="p-6 bg-gray-50 rounded-xl border border-gray-100 transition-all space-y-6">

                {{-- Zeile 1: Gewicht & Klasse --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- Gewicht --}}
                    <div>
                        <div class="flex items-center mb-1">
                            <label class="block text-xs font-bold uppercase text-gray-500">Gewicht</label>

                            {{-- Tooltip Weight --}}
                            <div x-data="{ show: false }" class="relative inline-block ml-1">
                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">
                                    {{ $infoTexts['weight'] }}
                                </div>
                            </div>
                        </div>
                        <div class="relative">
                            <input type="number" wire:model.blur="weight" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary pr-8" placeholder="0">
                            <span class="absolute right-3 top-2 text-sm text-gray-400 font-bold">g</span>
                        </div>
                        @error('weight') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Versandklasse --}}
                    <div>
                        <div class="flex items-center mb-1">
                            <label class="block text-xs font-bold uppercase text-gray-500">Versandklasse</label>

                            {{-- Tooltip Class --}}
                            <div x-data="{ show: false }" class="relative inline-block ml-1">
                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">
                                    {{ $infoTexts['shipping_class'] }}
                                </div>
                            </div>
                        </div>
                        <select wire:model.blur="shipping_class" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary bg-white">
                            <option value="">Standard</option>
                            <option value="brief">Brief / Großbrief</option>
                            <option value="paket_s">Paket S (bis 2kg)</option>
                            <option value="paket_m">Paket M (bis 5kg)</option>
                            <option value="sperrgut">Sperrgut</option>
                            <option value="spedition">Spedition</option>
                        </select>
                        @error('shipping_class') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Zeile 2: Maße --}}
                <div>
                    <div class="flex items-center mb-1">
                        <label class="block text-xs font-bold uppercase text-gray-500">Abmessungen (L x B x H)</label>

                        {{-- Tooltip Dimensions --}}
                        <div x-data="{ show: false }" class="relative inline-block ml-1">
                            <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                            <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">
                                {{ $infoTexts['dimensions'] }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="relative">
                            <input type="number" wire:model.blur="length" class="w-full px-3 py-2 text-center rounded border border-gray-300 focus:ring-primary focus:border-primary" placeholder="0">
                            <span class="absolute -bottom-4 left-0 w-full text-[10px] text-center text-gray-400 font-bold uppercase">Länge</span>
                        </div>
                        <div class="relative">
                            <input type="number" wire:model.blur="width" class="w-full px-3 py-2 text-center rounded border border-gray-300 focus:ring-primary focus:border-primary" placeholder="0">
                            <span class="absolute -bottom-4 left-0 w-full text-[10px] text-center text-gray-400 font-bold uppercase">Breite</span>
                        </div>
                        <div class="relative">
                            <input type="number" wire:model.blur="height" class="w-full px-3 py-2 text-center rounded border border-gray-300 focus:ring-primary focus:border-primary" placeholder="0">
                            <span class="absolute -bottom-4 left-0 w-full text-[10px] text-center text-gray-400 font-bold uppercase">Höhe</span>
                        </div>
                    </div>
                    @if($errors->has('length') || $errors->has('width') || $errors->has('height'))
                        <span class="text-red-500 text-xs mt-5 block">Bitte gültige Maße angeben.</span>
                    @endif
                </div>

                {{-- Spacer für Labels unten --}}
                <div class="h-2"></div>
            </div>
        @endif
    </div>
</div>
