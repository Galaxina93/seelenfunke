<div x-show="activeTab === 'general'" class="space-y-6 md:space-y-8 animate-fade-in">
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full blur-[80px] -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>

        <h3 class="text-sm sm:text-base font-serif font-bold text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-800 pb-4">
            <span class="w-8 h-8 rounded-xl bg-primary/10 border border-primary/20 text-primary shadow-inner flex items-center justify-center italic font-serif shrink-0">%</span>
            Steuer & Status
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Steuer-Modus</label>
                    @include('components.alerts.info-tooltip', ['key' => 'is_small_business'])
                </div>
                <label class="{{ $checkboxContainerClass }}">
                    <div class="relative flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.is_small_business" class="peer sr-only">
                        <div class="w-5 h-5 bg-gray-900 border-2 border-gray-700 rounded transition-all peer-checked:bg-primary peer-checked:border-primary shadow-inner"></div>
                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="block font-bold text-white text-sm group-hover:text-primary transition-colors">Kleinunternehmerregelung</span>
                        <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wider mt-0.5 block">Aktiviert § 19 UStG (keine MwSt)</span>
                    </div>
                </label>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Wartungsmodus</label>
                    @include('components.alerts.info-tooltip', ['key' => 'maintenance_mode'])
                </div>
                <label class="{{ $checkboxContainerClass }}">
                    <div class="relative flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.maintenance_mode" class="peer sr-only">
                        <div class="w-5 h-5 bg-gray-900 border-2 border-gray-700 rounded transition-all peer-checked:bg-red-500 peer-checked:border-red-500 shadow-inner"></div>
                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="block font-bold text-white text-sm group-hover:text-red-400 transition-colors">Shop/Konfigurator offline!</span>
                        <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wider mt-0.5 block">Die Shopseite und der Konfigurator sind nicht mehr erreichbar.</span>
                    </div>
                </label>
            </div>

            <div class="md:col-span-2 max-w-sm">
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Standard-MwSt Satz (%)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'default_tax_rate'])
                </div>
                <div class="relative">
                    <input type="number" wire:model="settings.default_tax_rate" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-black">%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-white mb-6 flex items-center gap-3 tracking-wide border-b border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-primary/10 border border-primary/20 text-primary shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            Angebots-Logik
        </h3>
        <div class="max-w-xs">
            <div class="flex items-center gap-2 mb-2 ml-1">
                <label class="{{ $labelClass }} !mb-0 !ml-0">Gültigkeit (Tage)</label>
                @include('components.alerts.info-tooltip', ['key' => 'order_quote_validity_days'])
            </div>
            <input type="number" wire:model="settings.order_quote_validity_days" class="{{ $inputClass }} !text-lg !font-bold">
        </div>
    </div>
</div>
