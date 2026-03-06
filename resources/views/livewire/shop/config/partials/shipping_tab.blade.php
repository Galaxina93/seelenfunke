<div x-show="activeTab === 'shipping'" class="space-y-6 md:space-y-8 animate-fade-in" style="display: none;">
    {{-- Versandkosten & Konditionen --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            Versandkosten & Konditionen
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 xl:gap-8 mb-10">
            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Standard Versand</label>
                    @include('components.alerts.info-tooltip', ['key' => 'shipping_cost'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.shipping_cost" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">€</span>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Versandkostenfrei ab</label>
                    @include('components.alerts.info-tooltip', ['key' => 'shipping_free_threshold'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.shipping_free_threshold" class="{{ $inputClass }} !text-lg !font-bold !pr-10 !text-emerald-400 !border-emerald-500/30 focus:!ring-emerald-500/20 focus:!border-emerald-400">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-emerald-500/50 font-bold">€</span>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Express-Aufschlag</label>
                    @include('components.alerts.info-tooltip', ['key' => 'express_surcharge'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.express_surcharge" class="{{ $inputClass }} !text-lg !font-bold !pr-10 !text-red-400 !border-red-500/30 focus:!ring-red-500/20 focus:!border-red-400">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-red-500/50 font-bold">€</span>
                </div>
            </div>
        </div>

        <div class="pt-8 border-t border-gray-800">
            <livewire:shop.config.delivery-times />
        </div>

        <div class="pt-8 border-t border-gray-800">
            <livewire:shop.shipping.shipping />
        </div>

    </div>
</div>
