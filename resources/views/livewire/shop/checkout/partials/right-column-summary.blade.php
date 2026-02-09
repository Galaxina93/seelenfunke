{{-- RECHTE SPALTE: Zusammenfassung --}}
<div class="mt-10 lg:mt-0 lg:col-span-5 h-full">
    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-lg sticky top-24">
        <h2 class="text-lg font-medium text-gray-900 mb-6">Bestellübersicht</h2>

        @php
            // Dynamisches Laden der Schwelle aus den Shop-Settings (Cent-Wert)
            $threshold = (int) shop_setting('shipping_free_threshold', 5000);
            $currentValue = $totals['subtotal_gross'];

            // Prozentuale Berechnung basierend auf dem dynamischen Schwellenwert
            $percent = $threshold > 0 ? min(100, ($currentValue / $threshold) * 100) : 100;

            // Werte aus dem CartService (welcher bereits die Settings nutzt)
            $missing = $totals['missing_for_free_shipping'];
            $isFree = $totals['is_free_shipping'];
        @endphp

        {{-- 1. Versandkostenfrei-Balken --}}
        @if($country === 'DE')
            <div class="mb-6 bg-gray-50 p-4 rounded-xl border border-gray-200">
                @if($isFree)
                    <div class="flex items-center gap-2 text-green-600 font-bold text-sm">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 13l4 4L19 7"/>
                        </svg>
                        Glückwunsch! Deine Bestellung ist versandkostenfrei.
                    </div>
                @else
                    <p class="text-sm text-gray-700 font-medium mb-2">
                        Noch <span class="text-primary font-bold">{{ number_format($missing / 100, 2, ',', '.') }} €</span>
                        bis zum <span class="text-green-600 font-bold">kostenlosen Versand!</span>
                    </p>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-primary h-2.5 rounded-full transition-all duration-500"
                             style="width: {{ $percent }}%"></div>
                    </div>
                @endif
            </div>
        @else
            <div
                class="mb-6 bg-blue-50 p-4 rounded-xl border border-blue-100 text-blue-800 text-sm flex gap-3 shadow-sm animate-fade-in">
                <svg class="w-5 h-5 flex-shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>

                @php
                    // Dynamisches Mapping der aktiven Länderliste
                    $activeCountries = shop_setting('active_countries', []);
                    $countryName = $activeCountries[$country] ?? $country;
                @endphp

                <span>
                    Für Lieferungen nach <strong class="text-blue-900">{{ $countryName }}</strong> gelten gesonderte Versandkosten.
                </span>
            </div>
        @endif

        {{-- 2. Artikelliste --}}
        <ul role="list" class="divide-y divide-gray-200 text-sm font-medium text-gray-900">
            @foreach($cart->items as $item)
                <li class="flex items-start py-6 space-x-4">
                    @php
                        $displayImage = null;

                        // 1. Suche nach dem ersten echten Bild in der Media Gallery
                        if (!empty($item->product->media_gallery)) {
                            foreach ($item->product->media_gallery as $media) {
                                if (($media['type'] ?? '') === 'image' && !empty($media['path'])) {
                                    $displayImage = $media['path'];
                                    break;
                                }
                            }
                        }

                        // 2. Fallback: Wenn Galerie leer, nutze das Preview Image
                        if (!$displayImage && !empty($item->product->preview_image_path)) {
                            $displayImage = $item->product->preview_image_path;
                        }
                    @endphp

                    @if($displayImage)
                        <img src="{{ Storage::url($displayImage) }}"
                             alt="{{ $item->product->name }}"
                             class="flex-none w-20 h-20 rounded-md object-cover bg-gray-100 border border-gray-200 shadow-sm">
                    @else
                        {{-- 3. Letzter Fallback: Platzhalter Icon --}}
                        <div
                            class="flex-none w-20 h-20 rounded-md bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-300">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    @endif

                    <div class="flex-auto space-y-1">
                        <h3 class="text-gray-900 font-bold">{{ $item->product->name }}</h3>
                        <p class="text-gray-500">{{ $item->quantity }}x</p>
                        @if(!empty($item->configuration) && is_array($item->configuration))
                            <div class="text-xs text-gray-500 space-y-1">
                                @if(isset($item->configuration['texts']))
                                    @foreach($item->configuration['texts'] as $t)
                                        <div
                                            class="bg-gray-50 inline-block px-1.5 py-0.5 rounded border border-gray-100">
                                            "{{ Str::limit($t['text'], 20) }}"
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-900">{{ number_format($item->unit_price / 100, 2, ',', '.') }}
                            &nbsp;€</p>
                    </div>
                </li>
            @endforeach
        </ul>

        {{-- 3. Zahlen / Summen via Master Component --}}
        <div class="mt-6 pt-6 border-t border-gray-100">
            <x-shop.cost-summary :totals="$totals" :country="$country" :showTitle="false" />
        </div>

        {{-- 4. Checkboxen --}}
        <div class="mt-8 space-y-4 bg-gray-50 p-4 rounded-xl">
            <div class="flex items-start">
                <div class="flex h-5 items-center">
                    <input id="terms" wire:model.live="terms_accepted" type="checkbox"
                           class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                </div>
                <div class="ml-3 text-sm">
                    <label for="terms" class="font-medium text-gray-700 cursor-pointer select-none">
                        Ich habe die <a href="/agb" target="_blank"
                                        class="text-primary underline hover:text-primary-dark">AGB</a>
                        gelesen und akzeptiere diese. <span class="text-red-500">*</span>
                    </label>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex h-5 items-center">
                    <input id="privacy" wire:model.live="privacy_accepted" type="checkbox"
                           class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                </div>
                <div class="ml-3 text-sm">
                    <label for="privacy"
                           class="font-medium text-gray-700 cursor-pointer select-none">
                        Ich habe die <a href="/datenschutz" target="_blank"
                                        class="text-primary underline hover:text-primary-dark">Datenschutzerklärung</a>
                        zur Kenntnis genommen. <span class="text-red-500">*</span>
                    </label>
                </div>
            </div>
            @if($errors->has('terms_accepted') || $errors->has('privacy_accepted'))
                <div class="text-red-500 text-xs mt-2 font-bold">Bitte stimme den rechtlichen
                    Bedingungen zu.
                </div>
            @endif
        </div>

        {{-- 5. Button --}}
        <div class="mt-8">
            <button id="submit-button" type="submit"
                    @disabled(!$terms_accepted || !$privacy_accepted) class="w-full rounded-full border border-transparent bg-gray-900 py-4 px-4 text-base font-bold text-white shadow-lg shadow-gray-900/20 hover:bg-black focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed disabled:shadow-none transition-all transform enabled:hover:-translate-y-1">
                <span id="button-text">Zahlungspflichtig bestellen</span>
                <div id="spinner" class="hidden flex items-center justify-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                         fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Verarbeite...
                </div>
            </button>
            <p class="text-xs text-gray-400 text-center mt-4">Durch Klicken auf den Button schließt
                du einen zahlungspflichtigen Kaufvertrag ab.</p>
        </div>
    </div>
</div>
