<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-1.5">
            <h3 class="text-lg font-serif font-bold text-gray-900">Versand & Lieferung</h3>

            {{-- Zentraler Tooltip: Physisch --}}
            @include('components.alerts.info-tooltip', ['key' => 'is_physical'])
        </div>
    </div>

    <div class="space-y-6">
        {{-- Toggle Switch --}}
        <div class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-gray-200 shadow-sm transition-all hover:border-primary/30">
            <button type="button"
                    wire:click="$toggle('is_physical_product')"
                    class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 {{ $is_physical_product ? 'bg-green-600' : 'bg-gray-200' }}"
                    role="switch"
                    aria-checked="{{ $is_physical_product ? 'true' : 'false' }}">
                <span class="sr-only">Physisches Produkt aktivieren</span>
                <span aria-hidden="true"
                      class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out {{ $is_physical_product ? 'translate-x-5' : 'translate-x-0' }}">
            </span>
            </button>
            <div class="cursor-pointer select-none" wire:click="$toggle('is_physical_product')">
                <label class="block text-sm font-bold text-gray-900 cursor-pointer">Physisches Produkt</label>
                <p class="text-xs text-gray-500 italic">Deaktivieren für digitale Dienstleistungen oder Gutscheine.</p>
            </div>
        </div>

        {{-- Detailbereich --}}
        @if($is_physical_product)
            <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100 space-y-8 animate-fade-in-up">

                {{-- Zeile 1: Gewicht & Klasse --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                    {{-- Gewicht --}}
                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500">Gewicht</label>
                            @include('components.alerts.info-tooltip', ['key' => 'weight'])
                        </div>
                        <div class="relative">
                            <input type="number" wire:model.blur="weight"
                                   class="w-full px-4 py-3.5 rounded-xl border border-gray-300 bg-white text-gray-900 font-medium focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all pr-12 shadow-sm"
                                   placeholder="0">
                            <span class="absolute right-4 top-3.5 text-sm text-gray-400 font-bold font-serif">g</span>
                        </div>
                        @error('weight') <span class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</span> @enderror
                    </div>

                    {{-- Versandklasse --}}
                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500">Versandklasse</label>
                            @include('components.alerts.info-tooltip', ['key' => 'shipping_class'])
                        </div>
                        <div class="relative">
                            <select wire:model.blur="shipping_class"
                                    class="w-full px-4 py-3.5 rounded-xl border border-gray-300 bg-white text-gray-900 font-medium focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="">Standard (Paket)</option>
                                <option value="brief">Brief / Großbrief</option>
                                <option value="paket_s">Paket S (bis 2kg)</option>
                                <option value="paket_m">Paket M (bis 5kg)</option>
                                <option value="sperrgut">Sperrgut</option>
                                <option value="spedition">Spedition</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>
                        @error('shipping_class') <span class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Zeile 2: Maße --}}
                <div class="pt-4 border-t border-gray-200">
                    <div class="flex items-center gap-1.5 mb-4">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500">Abmessungen (L x B x H in mm)</label>
                        @include('components.alerts.info-tooltip', ['key' => 'dimensions'])
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="relative group">
                            <input type="number" wire:model.blur="length"
                                   class="w-full px-3 py-4 text-center rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm"
                                   placeholder="0">
                            <label class="absolute -bottom-5 left-0 w-full text-[9px] text-center text-gray-400 font-bold uppercase tracking-tighter">Länge</label>
                        </div>
                        <div class="relative group">
                            <input type="number" wire:model.blur="width"
                                   class="w-full px-3 py-4 text-center rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm"
                                   placeholder="0">
                            <label class="absolute -bottom-5 left-0 w-full text-[9px] text-center text-gray-400 font-bold uppercase tracking-tighter">Breite</label>
                        </div>
                        <div class="relative group">
                            <input type="number" wire:model.blur="height"
                                   class="w-full px-3 py-4 text-center rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm"
                                   placeholder="0">
                            <label class="absolute -bottom-5 left-0 w-full text-[9px] text-center text-gray-400 font-bold uppercase tracking-tighter">Höhe</label>
                        </div>
                    </div>
                    @if($errors->has('length') || $errors->has('width') || $errors->has('height'))
                        <div class="mt-8 flex items-center gap-2 text-red-500 bg-red-50 p-3 rounded-lg border border-red-100 animate-pulse">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <span class="text-xs font-bold uppercase">Ungültige Maße</span>
                        </div>
                    @endif
                </div>

                {{-- Spacer für Labels unten --}}
                <div class="h-4"></div>
            </div>
        @endif
    </div>
</div>
