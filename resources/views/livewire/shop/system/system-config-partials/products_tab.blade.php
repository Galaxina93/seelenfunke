<div x-show="activeTab === 'products'" class="space-y-6 md:space-y-8 animate-fade-in" style="display: none;">
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-gray-900 dark:text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-100 dark:border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner shrink-0">
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
                @error('settings.inventory_low_stock_threshold') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Versandlogik</label>
                    @include('components.alerts.info-tooltip', ['key' => 'skip_shipping_for_digital'])
                </div>
                <label class="{{ $checkboxContainerClass }}">
                    <div class="relative flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.skip_shipping_for_digital" class="peer sr-only">
                        <div class="w-5 h-5 bg-gray-100 dark:bg-gray-900 border-2 border-gray-300 dark:border-gray-700 rounded transition-all peer-checked:bg-[var(--theme-color)] peer-checked:border-[var(--theme-color)] shadow-sm dark:shadow-inner"></div>
                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="block font-bold text-gray-900 dark:text-white text-sm group-hover:text-[var(--theme-color)] transition-colors">Versand überspringen (Digitale Produkte)</span>
                    </div>
                </label>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Preispflege im Admin</label>
                    @include('components.alerts.info-tooltip', ['key' => 'prices_entered_gross'])
                </div>
                <label class="{{ $checkboxContainerClass }}">
                    <div class="relative flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.prices_entered_gross" class="peer sr-only">
                        <div class="w-5 h-5 bg-gray-100 dark:bg-gray-900 border-2 border-gray-300 dark:border-gray-700 rounded transition-all peer-checked:bg-[var(--theme-color)] peer-checked:border-[var(--theme-color)] shadow-sm dark:shadow-inner"></div>
                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="block font-bold text-gray-900 dark:text-white text-sm group-hover:text-[var(--theme-color)] transition-colors">Bruttopreise (inkl. MwSt.)</span>
                    </div>
                </label>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-gray-900 dark:text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-100 dark:border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
            </div>
            Google Tag &amp; Google Ads
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Google Tag</label>
                    @include('components.alerts.info-tooltip', ['key' => 'google_tag_enabled'])
                </div>
                <label class="{{ $checkboxContainerClass }} justify-between">
                    <span class="font-bold text-gray-900 dark:text-white text-sm group-hover:text-[var(--theme-color)] transition-colors">Google Tag aktivieren</span>
                    <div class="relative inline-flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.google_tag_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-800 rounded-full peer peer-checked:bg-[var(--theme-color)] transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-transform peer-checked:after:translate-x-5"></div>
                    </div>
                </label>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Google Shopping-Feed</label>
                    @include('components.alerts.info-tooltip', ['key' => 'google_shopping_require_real_photos'])
                </div>
                <label class="{{ $checkboxContainerClass }} justify-between">
                    <span class="font-bold text-gray-900 dark:text-white text-sm group-hover:text-[var(--theme-color)] transition-colors">Echtfoto-Pflicht (Shopping-Feed)</span>
                    <div class="relative inline-flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.google_shopping_require_real_photos" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-800 rounded-full peer peer-checked:bg-[var(--theme-color)] transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-transform peer-checked:after:translate-x-5"></div>
                    </div>
                </label>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Google Tag / Ads ID</label>
                    @include('components.alerts.info-tooltip', ['key' => 'google_tag_id'])
                </div>
                <input type="text" wire:model="settings.google_tag_id" placeholder="AW-123456789" class="{{ $inputClass }} font-mono uppercase">
                @error('settings.google_tag_id') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Conversion-Label (Kauf)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'google_ads_conversion_label'])
                </div>
                <input type="text" wire:model="settings.google_ads_conversion_label" placeholder="AbCdEfGhIjKlMnOpQr" class="{{ $inputClass }} font-mono">
                @error('settings.google_ads_conversion_label') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>
</div>
