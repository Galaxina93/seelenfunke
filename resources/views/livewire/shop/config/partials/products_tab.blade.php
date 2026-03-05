<div x-show="activeTab === 'products'" class="space-y-6 md:space-y-8 animate-fade-in" style="display: none;">
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
            </div>
            Lager & Digitale Produkte
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Kritischer Lagerbestand</label>
                    @include('components.alerts.info-tooltip', ['key' => 'inventory_low_stock_threshold'])
                </div>
                <div class="relative">
                    <input type="number" wire:model="settings.inventory_low_stock_threshold" class="{{ $inputClass }} !text-lg !font-bold !pr-16">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-600 text-[9px] font-black uppercase tracking-widest">Stück</span>
                </div>
                <p class="text-[10px] text-gray-500 mt-2 font-medium ml-1">Ab dieser Menge erscheint eine Warnung im Dashboard.</p>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Versandlogik</label>
                    @include('components.alerts.info-tooltip', ['key' => 'skip_shipping_for_digital'])
                </div>
                <label class="{{ $checkboxContainerClass }}">
                    <div class="relative flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.skip_shipping_for_digital" class="peer sr-only">
                        <div class="w-5 h-5 bg-gray-900 border-2 border-gray-700 rounded transition-all peer-checked:bg-primary peer-checked:border-primary shadow-inner"></div>
                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="block font-bold text-white text-sm group-hover:text-primary transition-colors">Versand überspringen</span>
                        <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wider mt-0.5 block">Nur Downloads = Kostenlos</span>
                    </div>
                </label>
            </div>
        </div>
    </div>
</div>
