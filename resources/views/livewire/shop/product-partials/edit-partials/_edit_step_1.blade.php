@if($currentStep === 1)
    <div class="space-y-6">
        {{-- Karte 1: Grunddaten --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <h2 class="text-2xl font-serif text-gray-900 mb-6">1. Basisinformationen</h2>
            <div class="space-y-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <label class="block text-sm font-bold text-gray-800">Produktname *</label>
                        <div x-data="{ show: false }" class="relative inline-block ml-1">
                            <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                            <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['name'] }}</div>
                        </div>
                    </div>
                    <input type="text" wire:model.live="name" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="z.B. Seelen-Kristall">
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-2">Preis *</label>
                        <input type="number" step="0.01" wire:model.live="price_input" class="w-full pl-8 pr-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition font-mono" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-2">Vergleichspreis</label>
                        <input type="number" step="0.01" wire:model.live="compare_price_input" class="w-full pl-8 pr-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition font-mono text-gray-500" placeholder="z.B. 59.90">
                    </div>
                </div>



                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Beschreibung</label>
                    <textarea wire:model.live="description" rows="6" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="Erzähle die Geschichte des Produkts..."></textarea>
                </div>
            </div>
        </div>

        {{-- Staffelpreise --}}
        <livewire:shop.product-tier-pricing
            :product="$product"
            :currentPrice="$price_input"
        />

        {{-- Steuer & Mehrwertsteuer --}}
        <livewire:shop.product-tax
            :product="$product"
        />

        {{-- Karte 2: SEO & Marketing --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <div class="flex items-center justify-between mb-4 border-b pb-2">
                <h3 class="text-lg font-serif font-bold text-gray-900">SEO & Suchmaschinen</h3>
                <span class="text-xs text-gray-400 uppercase tracking-widest">Optional</span>
            </div>
            <div class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-bold text-gray-800">Seitentitel (Meta Title)</label>
                        <span class="text-xs text-gray-400">{{ strlen($seo_title) }}/60 Zeichen</span>
                    </div>
                    <input type="text" wire:model.live="seo_title" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="{{ $name }}">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-bold text-gray-800">Beschreibung (Meta Description)</label>
                        <span class="text-xs text-gray-400">{{ strlen($seo_description) }}/160 Zeichen</span>
                    </div>
                    <textarea wire:model.live="seo_description" rows="3" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="Eine kurze Zusammenfassung für Google..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-1">URL-Handle (Slug)</label>
                    <div class="flex items-center">
                        <span class="bg-gray-100 border border-r-0 border-gray-300 text-gray-500 px-3 py-3 rounded-l-lg text-sm">/produkt/</span>
                        <input type="text" wire:model.live="slug_input" class="w-full px-4 py-3 rounded-r-lg border border-gray-300 focus:border-primary focus:ring-primary transition font-mono text-sm">
                    </div>
                    @error('slug_input') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Karte 3: Identifikatoren --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <h3 class="text-lg font-serif font-bold text-gray-900 mb-4 border-b pb-2">Produktidentifikatoren</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <label class="block text-sm font-bold text-gray-800">Artikelnummer (SKU) *</label>
                        <div x-data="{ show: false }" class="relative inline-block ml-1">
                            <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                            <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['sku'] }}</div>
                        </div>
                    </div>
                    <input type="text" wire:model.live="sku" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="z.B. GLAS-001">
                    @if(empty($sku)) <p class="text-xs text-red-500 mt-1">Pflichtfeld</p> @endif
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <label class="block text-sm font-bold text-gray-800">Barcode (GTIN/EAN)</label>
                        <div x-data="{ show: false }" class="relative inline-block ml-1">
                            <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                            <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['barcode'] }}</div>
                        </div>
                    </div>
                    <input type="text" wire:model.live="barcode" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="">
                </div>
                <div class="md:col-span-2">
                    <div class="flex items-center gap-2 mb-2">
                        <label class="block text-sm font-bold text-gray-800">Marke / Hersteller</label>
                        <div x-data="{ show: false }" class="relative inline-block ml-1">
                            <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                            <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['brand'] }}</div>
                        </div>
                    </div>
                    <input type="text" wire:model.live="brand" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition" placeholder="z.B. Eigenmarke">
                </div>
            </div>
        </div>
    </div>
@endif
