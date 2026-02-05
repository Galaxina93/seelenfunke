@if($currentStep === 1)
    <div class="space-y-6">
        {{-- Karte 1: Grunddaten --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <h2 class="text-2xl font-serif text-gray-900 mb-6">1. Basisinformationen</h2>
            <div class="space-y-6">
                <div>
                    <div class="flex items-center gap-1.5 mb-2">
                        <label class="block text-sm font-bold text-gray-800">Produktname *</label>
                        {{-- Zentraler Tooltip --}}
                        @include('components.alerts.info-tooltip', ['key' => 'name'])
                    </div>
                    <input type="text" wire:model.live="name" class="w-full px-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" placeholder="z.B. Seelen-Kristall">
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <label class="block text-sm font-bold text-gray-800">Preis *</label>
                            @include('components.alerts.info-tooltip', ['key' => 'price'])
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-gray-400 font-bold">€</span>
                            <input type="number" step="0.01" wire:model.live="price_input" class="w-full pl-10 pr-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-mono font-bold" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-2">Vergleichspreis</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-gray-300 font-bold">€</span>
                            <input type="number" step="0.01" wire:model.live="compare_price_input" class="w-full pl-10 pr-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-mono text-gray-400" placeholder="z.B. 59.90">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Beschreibung</label>
                    <textarea wire:model.live="description" rows="6" class="w-full px-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" placeholder="Erzähle die Geschichte des Produkts..."></textarea>
                </div>
            </div>
        </div>

        {{-- Staffelpreise --}}
        <livewire:shop.product.product-tier-pricing
            :product="$product"
            :currentPrice="$price_input"
        />

        {{-- Steuer & Mehrwertsteuer --}}
        <livewire:shop.product.product-tax
            :product="$product"
        />

        {{-- Karte 2: SEO & Marketing --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <div class="flex items-center justify-between mb-4 border-b pb-2">
                <div class="flex items-center gap-1.5">
                    <h3 class="text-lg font-serif font-bold text-gray-900">SEO & Suchmaschinen</h3>
                    @include('components.alerts.info-tooltip', ['key' => 'seo'])
                </div>
                <span class="text-xs text-gray-400 uppercase tracking-widest font-bold">Optional</span>
            </div>
            <div class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-bold text-gray-800">Seitentitel (Meta Title)</label>
                        <span class="text-xs text-gray-400">{{ strlen($seo_title) }}/60 Zeichen</span>
                    </div>
                    <input type="text" wire:model.live="seo_title" class="w-full px-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" placeholder="{{ $name }}">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-bold text-gray-800">Beschreibung (Meta Description)</label>
                        <span class="text-xs text-gray-400">{{ strlen($seo_description) }}/160 Zeichen</span>
                    </div>
                    <textarea wire:model.live="seo_description" rows="3" class="w-full px-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" placeholder="Eine kurze Zusammenfassung für Google..."></textarea>
                </div>
                <div>
                    <div class="flex items-center gap-1.5 mb-1">
                        <label class="block text-sm font-bold text-gray-800">URL-Handle (Slug)</label>
                        @include('components.alerts.info-tooltip', ['key' => 'slug'])
                    </div>
                    <div class="flex items-center">
                        <span class="bg-gray-100 border border-r-0 border-gray-300 text-gray-500 px-4 py-3.5 rounded-l-xl text-sm font-medium">/produkt/</span>
                        <input type="text" wire:model.live="slug_input" class="w-full px-4 py-3.5 rounded-r-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-mono text-sm">
                    </div>
                    @error('slug_input') <p class="text-xs text-red-500 mt-1 font-bold">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Karte 3: Identifikatoren --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
            <h3 class="text-lg font-serif font-bold text-gray-900 mb-4 border-b pb-2">Produktidentifikatoren</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="flex items-center gap-1.5 mb-2">
                        <label class="block text-sm font-bold text-gray-800">Artikelnummer (SKU) *</label>
                        @include('components.alerts.info-tooltip', ['key' => 'sku'])
                    </div>
                    <input type="text" wire:model.live="sku" class="w-full px-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" placeholder="z.B. GLAS-001">
                    @if(empty($sku)) <p class="text-[10px] text-red-500 mt-1 uppercase font-bold tracking-tight">Dieses Feld ist erforderlich</p> @endif
                </div>
                <div>
                    <div class="flex items-center gap-1.5 mb-2">
                        <label class="block text-sm font-bold text-gray-800">Barcode (GTIN/EAN)</label>
                        @include('components.alerts.info-tooltip', ['key' => 'barcode'])
                    </div>
                    <input type="text" wire:model.live="barcode" class="w-full px-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" placeholder="Global Trade Item Number">
                </div>
                <div class="md:col-span-2">
                    <div class="flex items-center gap-1.5 mb-2">
                        <label class="block text-sm font-bold text-gray-800">Marke / Hersteller</label>
                        @include('components.alerts.info-tooltip', ['key' => 'brand'])
                    </div>
                    <input type="text" wire:model.live="brand" class="w-full px-4 py-3.5 rounded-xl border border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" placeholder="z.B. Mein Seelenfunke">
                </div>
            </div>
        </div>
    </div>
@endif
