@if($currentStep === 3)
    <div class="space-y-6">

        {{-- ========================================================= --}}
        {{-- 1. KATEGORIEN (FÜR ALLE TYPEN RELEVANT) --}}
        {{-- ========================================================= --}}
        <livewire:shop.product.product-categories :product="$product" />

        {{-- ========================================================= --}}
        {{-- 2. ATTRIBUTE (DETAILS) - NEUE KOMPONENTE --}}
        {{-- ========================================================= --}}
        {{-- Die Komponente regelt das Speichern eigenständig und lädt die Daten vom Produkt --}}
        <livewire:shop.product.product-attributes :product="$product" />

        {{-- ========================================================= --}}
        {{-- 3. VERSAND & LIEFERUNG - NUR BEI PHYSISCH --}}
        {{-- ========================================================= --}}
        @if($type === 'physical')
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
                <livewire:shop.product.product-shipping :product="$product" />
            </div>
        @endif

        {{-- ========================================================= --}}
        {{-- 4. LAGERBESTAND - BEI PHYSISCH & SERVICE --}}
        {{-- ========================================================= --}}
        @if($type !== 'digital')
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">
                <div class="flex items-center justify-between mb-6 border-b pb-4">
                    <div class="flex items-center gap-1.5">
                        <h3 class="text-lg font-serif font-bold text-gray-900">
                            {{ $type === 'service' ? 'Verfügbarkeit & Plätze' : 'Lager & Verfügbarkeit' }}
                        </h3>
                        @include('components.alerts.info-tooltip', ['key' => 'Lager'])
                    </div>
                    @if($track_quantity)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $quantity > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $quantity > 0 ? ($type === 'service' ? 'Verfügbar' : 'Auf Lager') : 'Ausverkauft' }}
                        </span>
                    @endif
                </div>

                <div class="space-y-6">
                    <div class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-gray-200 shadow-sm transition-all hover:border-primary/30">
                        <button type="button"
                                wire:click="$toggle('track_quantity')"
                                class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 {{ $track_quantity ? 'bg-green-600' : 'bg-gray-200' }}"
                                role="switch"
                                aria-checked="{{ $track_quantity ? 'true' : 'false' }}">
                            <span class="sr-only">Limitierung aktivieren</span>
                            <span aria-hidden="true"
                                  class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out {{ $track_quantity ? 'translate-x-5' : 'translate-x-0' }}">
                            </span>
                        </button>
                        <div class="cursor-pointer select-none" wire:click="$toggle('track_quantity')">
                            <label class="block text-sm font-bold text-gray-900 cursor-pointer">
                                {{ $type === 'service' ? 'Plätze limitieren' : 'Bestand automatisch verfolgen' }}
                            </label>
                            <p class="text-xs text-gray-500 italic">
                                {{ $type === 'service' ? 'Begrenzt die Anzahl der buchbaren Termine.' : 'Ermöglicht die Überwachung der verfügbaren Stückzahl.' }}
                            </p>
                        </div>
                    </div>

                    @if($track_quantity)
                        <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100 space-y-6 animate-fade-in-up">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                                <div>
                                    <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-2">
                                        {{ $type === 'service' ? 'Freie Plätze' : 'Aktuell Verfügbar' }}
                                    </label>
                                    <div class="relative">
                                        <input type="number" wire:model.live="quantity" class="w-full px-4 py-3.5 rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" placeholder="0">
                                        <span class="absolute right-4 top-3.5 text-xs text-gray-400 font-bold uppercase">Stk.</span>
                                    </div>
                                </div>
                                <div class="md:pt-6">
                                    <label class="flex items-center gap-3 cursor-pointer group">
                                        <div class="relative flex items-center">
                                            <input type="checkbox" id="continue_selling" wire:model.live="continue_selling" class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary transition-all">
                                        </div>
                                        <div class="select-none">
                                            <span class="block text-sm font-bold text-gray-800 group-hover:text-primary transition-colors">Überverkauf erlauben</span>
                                            <span class="block text-[11px] text-gray-500 leading-tight">Ermöglicht Bestellungen auch wenn das Limit erreicht ist.</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ========================================================= --}}
        {{-- 4. STAFFELPREISE - BEI PHYSISCH --}}
        {{-- ========================================================= --}}
        @if($type === 'physical')
            <div class="animate-fade-in-up">
                <livewire:shop.product.product-tier-pricing
                    :product="$product"
                    :currentPrice="$price_input"
                />
            </div>
        @endif
    </div>
@endif
