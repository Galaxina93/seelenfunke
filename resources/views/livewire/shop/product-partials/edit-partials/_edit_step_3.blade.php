@if($currentStep === 3)
    <div class="space-y-6">
        {{-- Karte: Attribute --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">

            {{-- Header im gleichen Stil wie ProductShipping --}}
            <div class="mb-8 border-b border-gray-100 pb-6">
                <h2 class="text-2xl font-serif text-gray-900">3. Attribute</h2>
            </div>

            <div class="p-6 bg-gray-50 rounded-xl border border-gray-100 transition-all space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($productAttributes as $key => $val)
                        <div>
                            {{-- Label Zeile --}}
                            <div class="flex items-center gap-2 mb-2">
                                <label class="block text-sm font-bold text-gray-800">
                                    {{ $key }}
                                    @if($key === 'Gewicht') <span class="text-xs font-normal text-gray-500">(in Gramm)</span> @endif
                                    *
                                </label>

                                @if(isset($infoTexts[$key]))
                                    <div x-data="{ show: false }" class="relative inline-block ml-1">
                                        <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                                        </button>
                                        <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">
                                            {{ $infoTexts[$key] }}
                                            {{-- Kleiner Pfeil für Tooltip (wie bei Shipping) --}}
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Inputs im neuen Look (Größer, border-focus) --}}
                            @if($key === 'Gewicht')
                                <input type="number"
                                       wire:model.live="productAttributes.{{ $key }}"
                                       class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition"
                                       placeholder="z.B. 250">
                            @else
                                <input type="text"
                                       wire:model.live="productAttributes.{{ $key }}"
                                       class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition"
                                       placeholder="Pflichtfeld">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- NEU: Versand & Lieferung --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <livewire:shop.product-shipping
                :product="$product"
            />
        </div>

        {{-- Karte: Lagerbestand --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-serif font-bold text-gray-900">Lager & Verfügbarkeit</h3>
                <div x-data="{ show: false }" class="relative inline-block ml-1">
                    <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                    <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['Lager'] }}</div>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="track_qty" wire:model.live="track_quantity" class="w-5 h-5 rounded text-primary focus:ring-primary border-gray-300">
                    <label for="track_qty" class="text-gray-800 font-medium cursor-pointer">Bestand automatisch verfolgen</label>
                </div>
                @if($track_quantity)
                    <div class="flex items-end gap-6 p-4 bg-gray-50 rounded-xl border border-gray-100 transition-all">
                        <div class="w-32">
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Verfügbar</label>
                            <input type="number" wire:model.live="quantity" class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary">
                        </div>
                        <div class="flex-1 pb-2">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="continue_selling" wire:model.live="continue_selling" class="w-4 h-4 rounded text-primary border-gray-300">
                                <label for="continue_selling" class="text-sm text-gray-600 cursor-pointer">Weitervorkauf erlauben, wenn ausverkauft</label>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
