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
                        <div class="space-y-2">
                            {{-- Label Zeile --}}
                            <div class="flex items-center gap-1.5">
                                <label class="block text-sm font-bold text-gray-800 tracking-wide uppercase text-[11px]">
                                    {{ $key }}
                                    @if($key === 'Gewicht')
                                        <span class="text-[10px] font-normal text-gray-400 italic">(in Gramm)</span>
                                    @endif
                                    <span class="text-red-400">*</span>
                                </label>

                                {{-- Zentraler Tooltip-Aufruf --}}
                                @include('components.alerts.info-tooltip', ['key' => $key])
                            </div>

                            {{-- Inputs im optimierten E-Commerce Look --}}
                            <div class="relative">
                                @if($key === 'Gewicht')
                                    <input type="number"
                                           wire:model.live="productAttributes.{{ $key }}"
                                           class="w-full px-4 py-3.5 rounded-xl border border-gray-300 bg-white text-gray-900 font-medium focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm"
                                           placeholder="z.B. 250">
                                    <span class="absolute right-4 top-3.5 text-gray-400 text-xs font-bold">g</span>
                                @else
                                    <input type="text"
                                           wire:model.live="productAttributes.{{ $key }}"
                                           class="w-full px-4 py-3.5 rounded-xl border border-gray-300 bg-white text-gray-900 font-medium focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm"
                                           placeholder="Eingabe erforderlich...">
                                @endif
                            </div>
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
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
            <div class="flex items-center justify-between mb-6 border-b pb-4">
                <div class="flex items-center gap-1.5">
                    <h3 class="text-lg font-serif font-bold text-gray-900">Lager & Verfügbarkeit</h3>
                    {{-- Zentraler Tooltip --}}
                    @include('components.alerts.info-tooltip', ['key' => 'Lager'])
                </div>
                @if($track_quantity)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $quantity > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                {{ $quantity > 0 ? 'Auf Lager' : 'Ausverkauft' }}
            </span>
                @endif
            </div>

            <div class="space-y-6">
                {{-- Bestand verfolgen Toggle --}}
                <div class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-gray-200 shadow-sm transition-all hover:border-primary/30">
                    <button type="button"
                            wire:click="$toggle('track_quantity')"
                            class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 {{ $track_quantity ? 'bg-green-600' : 'bg-gray-200' }}"
                            role="switch"
                            aria-checked="{{ $track_quantity ? 'true' : 'false' }}">
                        <span class="sr-only">Bestandsführung aktivieren</span>
                        <span aria-hidden="true"
                              class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out {{ $track_quantity ? 'translate-x-5' : 'translate-x-0' }}">
                </span>
                    </button>
                    <div class="cursor-pointer select-none" wire:click="$toggle('track_quantity')">
                        <label class="block text-sm font-bold text-gray-900 cursor-pointer">Bestand automatisch verfolgen</label>
                        <p class="text-xs text-gray-500 italic">Ermöglicht die Überwachung der verfügbaren Stückzahl.</p>
                    </div>
                </div>

                @if($track_quantity)
                    <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100 space-y-6 animate-fade-in-up">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">

                            {{-- Verfügbare Menge --}}
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-2">Aktuell Verfügbar</label>
                                <div class="relative">
                                    <input type="number"
                                           wire:model.live="quantity"
                                           class="w-full px-4 py-3.5 rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm"
                                           placeholder="0">
                                    <span class="absolute right-4 top-3.5 text-xs text-gray-400 font-bold uppercase">Stk.</span>
                                </div>
                            </div>

                            {{-- Backorder Logik --}}
                            <div class="md:pt-6">
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <div class="relative flex items-center">
                                        <input type="checkbox"
                                               id="continue_selling"
                                               wire:model.live="continue_selling"
                                               class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary transition-all">
                                    </div>
                                    <div class="select-none">
                                        <span class="block text-sm font-bold text-gray-800 group-hover:text-primary transition-colors">Weitervorkauf erlauben</span>
                                        <span class="block text-[11px] text-gray-500 leading-tight">Ermöglicht Bestellungen auch bei Bestand ≤ 0 (Backorder).</span>
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
