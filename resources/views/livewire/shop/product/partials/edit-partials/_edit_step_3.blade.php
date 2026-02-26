@if($currentStep === 3)
    <div class="space-y-6 md:space-y-8 animate-fade-in-up">

        {{-- ========================================================= --}}
        {{-- 1. KATEGORIEN (FÜR ALLE TYPEN RELEVANT) --}}
        {{-- ========================================================= --}}
        <livewire:shop.product.product-categories :product="$product" />

        {{-- ========================================================= --}}
        {{-- 2. ATTRIBUTE (DETAILS) - NEUE KOMPONENTE --}}
        {{-- ========================================================= --}}
        <livewire:shop.product.product-attributes :product="$product" />

        {{-- ========================================================= --}}
        {{-- 3. VERSAND & LIEFERUNG - NUR BEI PHYSISCH --}}
        {{-- ========================================================= --}}
        @if($type === 'physical')
            <livewire:shop.product.product-shipping :product="$product" />
        @endif

        {{-- ========================================================= --}}
        {{-- 4. LAGERBESTAND - BEI PHYSISCH & SERVICE --}}
        {{-- ========================================================= --}}
        @if($type !== 'digital')
            <div x-data="{ open: false }" class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800 transition-colors hover:border-gray-700 animate-fade-in-up">

                {{-- Header (Klickbar zum Aufklappen) --}}
                <div @click="open = !open" class="flex items-center justify-between cursor-pointer group transition-all" :class="open ? 'mb-6 border-b border-gray-800 pb-5' : ''">
                    <div class="flex items-center gap-4">
                        <div class="p-2 rounded-xl bg-gray-950 border border-gray-800 text-gray-500 group-hover:text-primary group-hover:border-primary/30 transition-all shadow-inner shrink-0">
                            <svg class="w-5 h-5 transition-transform duration-300" :class="open ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-xl font-serif font-bold text-white tracking-wide group-hover:text-primary transition-colors">
                                {{ $type === 'service' ? 'Verfügbarkeit & Plätze' : 'Lager & Verfügbarkeit' }}
                            </h3>
                            <div @click.stop>
                                @include('components.alerts.info-tooltip', ['key' => 'Lager'])
                            </div>
                        </div>
                    </div>

                    @if($track_quantity)
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest shadow-inner border hidden sm:inline-flex {{ $quantity > 0 ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20' }}">
                            {{ $quantity > 0 ? ($type === 'service' ? 'Verfügbar' : 'Auf Lager') : 'Ausverkauft' }}
                        </span>
                    @endif
                </div>

                {{-- Eingeklappter Inhalt --}}
                <div x-show="open" x-collapse style="display: none;">
                    <div class="space-y-6 pt-2">
                        <div class="flex items-center gap-5 p-5 bg-gray-950 rounded-2xl border border-gray-800 shadow-inner transition-all hover:border-gray-700 group">
                            <button type="button"
                                    wire:click="$toggle('track_quantity')"
                                    class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none shadow-inner {{ $track_quantity ? 'bg-emerald-500/20 border-emerald-500/50' : 'bg-gray-800 border-gray-700' }}"
                                    role="switch"
                                    aria-checked="{{ $track_quantity ? 'true' : 'false' }}">
                                <span class="sr-only">Limitierung aktivieren</span>
                                <span aria-hidden="true"
                                      class="pointer-events-none inline-block h-6 w-6 transform rounded-full shadow-[0_0_10px_rgba(0,0,0,0.5)] ring-0 transition duration-300 ease-in-out {{ $track_quantity ? 'translate-x-5 bg-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.8)]' : 'translate-x-0 bg-gray-500' }}">
                                </span>
                            </button>
                            <div class="cursor-pointer select-none flex-1" wire:click="$toggle('track_quantity')">
                                <label class="block text-sm font-bold text-white cursor-pointer group-hover:text-primary transition-colors">
                                    {{ $type === 'service' ? 'Plätze limitieren' : 'Bestand automatisch verfolgen' }}
                                </label>
                                <p class="text-[10px] font-medium text-gray-500 mt-0.5">
                                    {{ $type === 'service' ? 'Begrenzt die Anzahl der buchbaren Termine.' : 'Ermöglicht die Überwachung der verfügbaren Stückzahl.' }}
                                </p>
                            </div>
                        </div>

                        @if($track_quantity)
                            <div class="p-6 sm:p-8 bg-gray-950/50 rounded-2xl border border-gray-800 shadow-inner space-y-6 animate-fade-in-down">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                                    <div>
                                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2 ml-1">
                                            {{ $type === 'service' ? 'Freie Plätze' : 'Aktuell Verfügbar' }}
                                        </label>
                                        <div class="relative">
                                            <input type="number" wire:model.live="quantity" class="w-full px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-900 text-white font-mono font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner outline-none" placeholder="0">
                                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] text-gray-500 font-black uppercase tracking-widest">Stk.</span>
                                        </div>
                                    </div>
                                    <div class="md:pt-6">
                                        <label class="flex items-center gap-4 cursor-pointer group p-3 rounded-xl hover:bg-gray-900 transition-colors border border-transparent hover:border-gray-800">
                                            <div class="relative flex items-center shrink-0">
                                                <input type="checkbox" id="continue_selling" wire:model.live="continue_selling" class="peer sr-only">
                                                <div class="w-5 h-5 bg-gray-900 border-2 border-gray-700 rounded transition-all peer-checked:bg-primary peer-checked:border-primary shadow-inner"></div>
                                                <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <div class="select-none min-w-0">
                                                <span class="block text-sm font-bold text-gray-300 group-hover:text-white transition-colors">Überverkauf erlauben</span>
                                                <span class="block text-[10px] text-gray-500 font-medium mt-0.5">Ermöglicht Bestellungen auch wenn das Limit erreicht ist.</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
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
