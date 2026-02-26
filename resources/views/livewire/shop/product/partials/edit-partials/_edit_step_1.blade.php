@if($currentStep === 1)
    <div class="space-y-6 md:space-y-8">

        @php
            $cardClass = "bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8 animate-fade-in-up";
            $inputClassStep1 = "w-full px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-950 text-white placeholder-gray-600 focus:bg-black focus:border-primary focus:ring-2 focus:ring-primary/30 transition-all shadow-inner outline-none";
            $labelClassStep1 = "block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2 ml-1";
        @endphp

        {{-- Produkttyp Auswahl --}}
        <div class="{{ $cardClass }}">
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white mb-6 tracking-wide">Produkttyp wählen</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                {{-- OPTION: PHYSISCH --}}
                <label class="cursor-pointer relative group">
                    <input type="radio" wire:model.live="type" value="physical" class="sr-only">
                    <div class="p-6 rounded-[1.5rem] border-2 transition-all duration-300 ease-in-out text-center h-full flex flex-col items-center justify-center gap-4
                                {{ $type === 'physical'
                                    ? 'border-primary bg-primary/10 text-white shadow-[0_0_20px_rgba(197,160,89,0.15)] scale-[1.02]'
                                    : 'border-gray-800 bg-gray-950 text-gray-500 hover:border-gray-700 hover:bg-gray-900/50 hover:shadow-lg'
                                }}">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-1 transition-colors shadow-inner
                                    {{ $type === 'physical' ? 'bg-primary border border-primary/50 drop-shadow-[0_0_10px_currentColor]' : 'bg-gray-900 border border-gray-800 opacity-60' }}">📦</div>
                        <div>
                            <span class="font-serif font-bold text-xl block mb-1">Physisch</span>
                            <span class="text-[9px] font-black uppercase tracking-widest opacity-70 block leading-relaxed">Versandartikel mit Konfigurator</span>
                        </div>
                        <div class="absolute top-4 right-4 text-primary transition-opacity {{ $type === 'physical' ? 'opacity-100 drop-shadow-[0_0_5px_currentColor]' : 'opacity-0' }}">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        </div>
                    </div>
                </label>

                {{-- OPTION: DIGITAL --}}
                <label class="cursor-pointer relative group">
                    <input type="radio" wire:model.live="type" value="digital" class="sr-only">
                    <div class="p-6 rounded-[1.5rem] border-2 transition-all duration-300 ease-in-out text-center h-full flex flex-col items-center justify-center gap-4
                                {{ $type === 'digital'
                                    ? 'border-primary bg-primary/10 text-white shadow-[0_0_20px_rgba(197,160,89,0.15)] scale-[1.02]'
                                    : 'border-gray-800 bg-gray-950 text-gray-500 hover:border-gray-700 hover:bg-gray-900/50 hover:shadow-lg'
                                }}">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-1 transition-colors shadow-inner
                                    {{ $type === 'digital' ? 'bg-primary border border-primary/50 drop-shadow-[0_0_10px_currentColor]' : 'bg-gray-900 border border-gray-800 opacity-60' }}">☁️</div>
                        <div>
                            <span class="font-serif font-bold text-xl block mb-1">Digital</span>
                            <span class="text-[9px] font-black uppercase tracking-widest opacity-70 block leading-relaxed">Download oder E-Book</span>
                        </div>
                        <div class="absolute top-4 right-4 text-primary transition-opacity {{ $type === 'digital' ? 'opacity-100 drop-shadow-[0_0_5px_currentColor]' : 'opacity-0' }}">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        </div>
                    </div>
                </label>

                {{-- OPTION: DIENSTLEISTUNG --}}
                <label class="cursor-pointer relative group">
                    <input type="radio" wire:model.live="type" value="service" class="sr-only">
                    <div class="p-6 rounded-[1.5rem] border-2 transition-all duration-300 ease-in-out text-center h-full flex flex-col items-center justify-center gap-4
                                {{ $type === 'service'
                                    ? 'border-primary bg-primary/10 text-white shadow-[0_0_20px_rgba(197,160,89,0.15)] scale-[1.02]'
                                    : 'border-gray-800 bg-gray-950 text-gray-500 hover:border-gray-700 hover:bg-gray-900/50 hover:shadow-lg'
                                }}">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-1 transition-colors shadow-inner
                                    {{ $type === 'service' ? 'bg-primary border border-primary/50 drop-shadow-[0_0_10px_currentColor]' : 'bg-gray-900 border border-gray-800 opacity-60' }}">🤝</div>
                        <div>
                            <span class="font-serif font-bold text-xl block mb-1">Dienstleistung</span>
                            <span class="text-[9px] font-black uppercase tracking-widest opacity-70 block leading-relaxed">Service, Termin oder Beratung</span>
                        </div>
                        <div class="absolute top-4 right-4 text-primary transition-opacity {{ $type === 'service' ? 'opacity-100 drop-shadow-[0_0_5px_currentColor]' : 'opacity-0' }}">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        {{-- Karte 1: Grunddaten --}}
        <div class="{{ $cardClass }}">
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white mb-8 tracking-wide border-b border-gray-800 pb-4">1. Basisinformationen</h2>
            <div class="space-y-6">
                <div>
                    <div class="flex items-center gap-2 mb-2 ml-1">
                        <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Produktname *</label>
                        @include('components.alerts.info-tooltip', ['key' => 'name'])
                    </div>
                    <input type="text" wire:model.live="name" class="{{ $inputClassStep1 }}" placeholder="z.B. Seelen-Kristall">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Preis *</label>
                            @include('components.alerts.info-tooltip', ['key' => 'price'])
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">€</span>
                            <input type="number" step="0.01" wire:model.live="price_input" class="{{ $inputClassStep1 }} pl-10 font-mono text-lg font-bold" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelClassStep1 }}">Vergleichspreis</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-600 font-bold">€</span>
                            <input type="number" step="0.01" wire:model.live="compare_price_input" class="{{ $inputClassStep1 }} pl-10 font-mono text-lg text-gray-400" placeholder="z.B. 59.90">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClassStep1 }}">Beschreibung</label>
                    <textarea wire:model.live="description" rows="6" class="{{ $inputClassStep1 }} resize-none leading-relaxed" placeholder="Erzähle die Geschichte des Produkts..."></textarea>
                </div>
            </div>
        </div>

        {{-- Steuer & Mehrwertsteuer --}}
        <livewire:shop.product.product-tax :product="$product" />

        {{-- Karte 2: SEO --}}
        <div class="{{ $cardClass }}">
            <div class="flex items-center justify-between mb-6 border-b border-gray-800 pb-4">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-serif font-bold text-white tracking-wide">SEO & Suchmaschinen</h3>
                    @include('components.alerts.info-tooltip', ['key' => 'seo'])
                </div>
                <span class="text-[9px] text-gray-600 uppercase tracking-widest font-black bg-gray-950 px-3 py-1 rounded-md shadow-inner border border-gray-800">Optional</span>
            </div>
            <div class="space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-2 ml-1">
                        <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Seitentitel (Meta Title)</label>
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-600">{{ strlen($seo_title) }}/60 Zeichen</span>
                    </div>
                    <input type="text" wire:model.live="seo_title" class="{{ $inputClassStep1 }}" placeholder="{{ $name }}">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2 ml-1">
                        <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Beschreibung (Meta Description)</label>
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-600">{{ strlen($seo_description) }}/160 Zeichen</span>
                    </div>
                    <textarea wire:model.live="seo_description" rows="3" class="{{ $inputClassStep1 }} resize-none" placeholder="Eine kurze Zusammenfassung für Google..."></textarea>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-2 ml-1">
                        <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">URL-Handle (Slug)</label>
                        @include('components.alerts.info-tooltip', ['key' => 'slug'])
                    </div>
                    <div class="flex items-center shadow-inner rounded-xl overflow-hidden border border-gray-800">
                        <span class="bg-gray-900 px-4 py-3.5 text-gray-500 text-xs font-black uppercase tracking-widest">/produkt/</span>
                        <input type="text" wire:model.live="slug_input" class="w-full px-4 py-3.5 bg-gray-950 text-white focus:bg-black outline-none font-mono text-sm transition-colors border-l border-gray-800">
                    </div>
                    @error('slug_input') <p class="text-[10px] text-red-400 mt-2 font-bold uppercase tracking-widest">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Karte 3: Identifikatoren --}}
        <div class="{{ $cardClass }}">
            <h3 class="text-lg font-serif font-bold text-white mb-6 border-b border-gray-800 pb-4 tracking-wide">Produktidentifikatoren</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2 ml-1">
                        <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Artikelnummer (SKU) *</label>
                        @include('components.alerts.info-tooltip', ['key' => 'sku'])
                    </div>
                    <input type="text" wire:model.live="sku" class="{{ $inputClassStep1 }} font-mono" placeholder="z.B. GLAS-001">
                    @if(empty($sku)) <p class="text-[9px] text-red-400 mt-2 uppercase font-black tracking-widest">Dieses Feld ist erforderlich</p> @endif
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-2 ml-1">
                        <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Barcode (GTIN/EAN)</label>
                        @include('components.alerts.info-tooltip', ['key' => 'barcode'])
                    </div>
                    <input type="text" wire:model.live="barcode" class="{{ $inputClassStep1 }} font-mono" placeholder="Global Trade Item Number">
                </div>
                <div class="md:col-span-2">
                    <div class="flex items-center gap-2 mb-2 ml-1">
                        <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Marke / Hersteller</label>
                        @include('components.alerts.info-tooltip', ['key' => 'brand'])
                    </div>
                    <input type="text" wire:model.live="brand" class="{{ $inputClassStep1 }}" placeholder="z.B. Mein Seelenfunke">
                </div>
            </div>
        </div>
    </div>
@endif
