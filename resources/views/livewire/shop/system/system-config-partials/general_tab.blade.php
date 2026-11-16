<div x-show="activeTab === 'general'" class="space-y-6 md:space-y-8 animate-fade-in">
    {{-- KARTE 1: SHOP-DOMAINS & LIVEGANG-STATUS --}}
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8 relative overflow-hidden">
        <div class="hidden dark:block absolute top-0 right-0 w-64 h-64 bg-[var(--theme-color)]/5 rounded-full blur-[80px] -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-800 pb-4 mb-8">
            <h3 class="text-sm sm:text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-3 tracking-wide">
                <span class="w-8 h-8 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                </span>
                Shop-Domains &amp; Livegang-Status
            </h3>
            <div class="flex items-center gap-2">
                <span class="text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700">2-Kanal-Architektur</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
            {{-- 1. HAUPT-SHOP: PERSONALISIERTE MANUFAKTUR --}}
            <div class="p-5 rounded-2xl bg-gray-50/60 dark:bg-gray-800/40 border border-gray-200/70 dark:border-gray-800 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] text-[var(--theme-color)] font-bold border border-[var(--theme-color-20)]">mein-seelenfunke.de</span>
                        <div x-data="{ open: false }" class="relative inline-flex items-center" @mouseenter="open = true" @mouseleave="open = false">
                            <button type="button" @click.prevent="open = !open" title="{{ $infoTexts['shop_is_live'] }}" class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-300 text-[10px] font-black flex items-center justify-center hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-colors">!</button>
                            <div x-show="open" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 w-72 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-3 text-[11px] leading-relaxed text-gray-700 dark:text-gray-200 shadow-xl pointer-events-none">
                                {{ $infoTexts['shop_is_live'] }}
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }} !mb-1 !ml-0 font-bold">Personalisierter Shop (Live-Freigabe)</label>
                    <button type="button" wire:click="toggleShopIsLive" role="switch" aria-checked="{{ $shop_is_live ? 'true' : 'false' }}" class="{{ $checkboxContainerClass }} w-full justify-between !py-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="font-bold text-gray-900 dark:text-white text-sm">Status</span>
                            @if($shop_is_live)
                                <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider border bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30">Live (Öffentlich)</span>
                            @else
                                <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider border bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30">In Vorbereitung (Gesperrt)</span>
                            @endif
                        </div>
                        <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out {{ $shop_is_live ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-800' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $shop_is_live ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </span>
                    </button>
                </div>
            </div>

            {{-- 2. DIGITAL-SHOP: SOFORT-DOWNLOADS & E-BOOKS --}}
            <div class="p-5 rounded-2xl bg-gray-50/60 dark:bg-gray-800/40 border border-gray-200/70 dark:border-gray-800 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 font-bold border border-cyan-500/20">digital.mein-seelenfunke.de</span>
                        <div x-data="{ open: false }" class="relative inline-flex items-center" @mouseenter="open = true" @mouseleave="open = false">
                            <button type="button" @click.prevent="open = !open" title="{{ $infoTexts['digital_domain_is_live'] }}" class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-300 text-[10px] font-black flex items-center justify-center hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-colors">!</button>
                            <div x-show="open" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 w-72 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-3 text-[11px] leading-relaxed text-gray-700 dark:text-gray-200 shadow-xl pointer-events-none">
                                {{ $infoTexts['digital_domain_is_live'] }}
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }} !mb-1 !ml-0 font-bold">Digitaler Shop (Live-Freigabe)</label>
                    <button type="button" wire:click="toggleDigitalDomainIsLive" role="switch" aria-checked="{{ $digital_domain_is_live ? 'true' : 'false' }}" class="{{ $checkboxContainerClass }} w-full justify-between !py-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="font-bold text-gray-900 dark:text-white text-sm">Status</span>
                            @if($digital_domain_is_live)
                                <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider border bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30">Live (Öffentlich)</span>
                            @else
                                <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider border bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30">In Vorbereitung (Gesperrt)</span>
                            @endif
                        </div>
                        <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out {{ $digital_domain_is_live ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-800' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $digital_domain_is_live ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </span>
                    </button>
                </div>
            </div>

            {{-- 3. GLOBALER WARTUNGSMODUS (TEMPORÄRE WARTUNG) --}}
            <div class="p-5 rounded-2xl bg-gray-50/60 dark:bg-gray-800/40 border border-gray-200/70 dark:border-gray-800 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-red-500/10 text-red-600 dark:text-red-400 font-bold border border-red-500/20">Alle Domains</span>
                        @include('components.alerts.info-tooltip', ['key' => 'maintenance_mode'])
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }} !mb-1 !ml-0 font-bold">Wartungsmodus (HTTP 503)</label>
                    <button type="button" wire:click="toggleMaintenanceMode" role="switch" aria-checked="{{ !empty($settings['maintenance_mode']) ? 'true' : 'false' }}" class="{{ $checkboxContainerClass }} w-full justify-between !py-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="font-bold text-gray-900 dark:text-white text-sm">Modus</span>
                            @if(!empty($settings['maintenance_mode']))
                                <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider border bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/30">Aktiv (Wartungsseite)</span>
                            @else
                                <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider border bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30">Inaktiv (Regulärer Betrieb)</span>
                            @endif
                        </div>
                        <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out {{ !empty($settings['maintenance_mode']) ? 'bg-red-500' : 'bg-gray-200 dark:bg-gray-800' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ !empty($settings['maintenance_mode']) ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </span>
                    </button>
                </div>
            </div>

            {{-- 4. 3D-SHOWROOM MULTI-USER & RAUM-CHAT --}}
            <div class="p-5 rounded-2xl bg-gray-50/60 dark:bg-gray-800/40 border border-gray-200/70 dark:border-gray-800 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-purple-500/10 text-purple-600 dark:text-purple-400 font-bold border border-purple-500/20">3D-Welt</span>
                        @include('components.alerts.info-tooltip', ['key' => 'showroom_multiplayer_enabled'])
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }} !mb-1 !ml-0 font-bold">3D-Multiplayer (12er-Räume &amp; Chat)</label>
                    <button type="button" wire:click="toggleShowroomMultiplayer" role="switch" aria-checked="{{ !empty($settings['showroom_multiplayer_enabled']) ? 'true' : 'false' }}" class="{{ $checkboxContainerClass }} w-full justify-between !py-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="font-bold text-gray-900 dark:text-white text-sm">Showroom</span>
                            @if(!empty($settings['showroom_multiplayer_enabled']))
                                <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider border bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30">Aktiv (12er-Räume)</span>
                            @else
                                <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black uppercase tracking-wider border bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/30">Solo-Modus</span>
                            @endif
                        </div>
                        <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out {{ !empty($settings['showroom_multiplayer_enabled']) ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-800' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ !empty($settings['showroom_multiplayer_enabled']) ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- KARTE 2: STEUERN & FINANZEN (§ 19 UStG & MwSt-Sätze) --}}
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8 relative overflow-hidden">
        <h3 class="text-sm sm:text-base font-serif font-bold text-gray-900 dark:text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-100 dark:border-gray-800 pb-4">
            <span class="w-8 h-8 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner flex items-center justify-center italic font-serif shrink-0">%</span>
            Steuern &amp; Finanzen (§ 19 UStG &amp; MwSt-Sätze)
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Steuer-Modus (§ 19 UStG)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'is_small_business'])
                </div>
                <div class="space-y-3">
                    <button type="button" wire:click="initiateSmallBusinessToggle" class="{{ $checkboxContainerClass }} w-full text-left !items-start sm:!items-center">
                        <div class="relative flex items-center shrink-0 mt-0.5 sm:mt-0">
                            <input type="checkbox" {{ !empty($settings['is_small_business']) ? 'checked' : '' }} class="peer sr-only pointer-events-none" readonly>
                            <div class="w-5 h-5 bg-gray-100 dark:bg-gray-900 border-2 border-gray-300 dark:border-gray-700 rounded transition-all {{ !empty($settings['is_small_business']) ? 'bg-[var(--theme-color)] border-[var(--theme-color)]' : '' }} shadow-sm dark:shadow-inner"></div>
                            <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 {{ !empty($settings['is_small_business']) ? 'opacity-100' : 'opacity-0' }} transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                <span class="font-bold text-gray-900 dark:text-white text-sm group-hover:text-[var(--theme-color)] transition-colors">Kleinunternehmerregelung</span>
                                <span class="text-[9px] px-2 py-0.5 rounded-full font-black uppercase tracking-wider border shrink-0 {{ $this->taxStatusSummary['badge_color'] }}">
                                    {{ $this->taxStatusSummary['badge'] }}
                                </span>
                            </div>
                            <span class="text-[10px] text-gray-500 font-medium mt-1 block leading-normal">{{ $this->taxStatusSummary['desc'] }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Standard-MwSt Satz (%)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'default_tax_rate'])
                </div>
                <div class="relative">
                    <input type="number" step="0.1" wire:model="settings.default_tax_rate" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-black">%</span>
                </div>
                @error('settings.default_tax_rate') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Ermäßigter MwSt Satz (%)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'reduced_tax_rate'])
                </div>
                <div class="relative">
                    <input type="number" step="0.1" wire:model="settings.reduced_tax_rate" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-black">%</span>
                </div>
                @error('settings.reduced_tax_rate') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Admin E-Mail Benachrichtigung</label>
                    @include('components.alerts.info-tooltip', ['key' => 'send_order_email_to_admin'])
                </div>
                <label class="{{ $checkboxContainerClass }}">
                    <div class="relative flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.send_order_email_to_admin" class="peer sr-only">
                        <div class="w-5 h-5 bg-gray-100 dark:bg-gray-900 border-2 border-gray-300 dark:border-gray-700 rounded transition-all peer-checked:bg-[var(--theme-color)] peer-checked:border-[var(--theme-color)] shadow-sm dark:shadow-inner"></div>
                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="block font-bold text-gray-900 dark:text-white text-sm group-hover:text-[var(--theme-color)] transition-colors">Bestell-Mails erhalten</span>
                        <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wider mt-0.5 block">Admin erhält Kopie der Bestellung</span>
                    </div>
                </label>
            </div>
        </div>
    </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-3 tracking-wide border-b border-gray-100 dark:border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            Angebots-Logik, Herzensprojekt & Gebühren-Kalkulation
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 xl:gap-8">
            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Angebots-Gültigkeit (Tage)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'order_quote_validity_days'])
                </div>
                <input type="number" wire:model="settings.order_quote_validity_days" class="{{ $inputClass }} !text-lg !font-bold">
                @error('settings.order_quote_validity_days') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Herzensprojekt Spendenbeitrag ({{ \App\Services\HerzensprojektService::resolveUnitLabel(true) }})</label>
                    @include('components.alerts.info-tooltip', ['key' => 'herzensprojekt_cents_per_order'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.herzensprojekt_cents_per_order" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">€</span>
                </div>
                @error('settings.herzensprojekt_cents_per_order') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Stripe-Gebühr Kalkulation (%)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'stripe_fee_percent'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.stripe_fee_percent" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">%</span>
                </div>
                @error('settings.stripe_fee_percent') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Stripe-Fixgebühr Kalkulation</label>
                    @include('components.alerts.info-tooltip', ['key' => 'stripe_fee_fixed_cents'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.stripe_fee_fixed_cents" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">€</span>
                </div>
                @error('settings.stripe_fee_fixed_cents') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- § 19 UStG TRANSITION CONFIRMATION MODAL --}}
    @if($showSmallBusinessModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-950/75 backdrop-blur-sm transition-opacity" wire:click="cancelSmallBusinessTransition"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-900 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-gray-200 dark:border-gray-800 p-6 sm:p-8">
                    <div class="flex items-start gap-4 mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="modal-title">
                                @if(!$pendingSmallBusinessState)
                                    Kleinunternehmerregelung deaktivieren
                                @else
                                    Kleinunternehmerregelung aktivieren
                                @endif
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Wichtiger steuerrechtlicher Hinweis gemäß § 19 UStG (Ganzjahres-Prinzip)
                            </p>
                        </div>
                    </div>

                    {{-- Hinweisbox Gesetzlicher Grundsatz --}}
                    <div class="mb-6 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-200 leading-relaxed space-y-2">
                        <p class="font-bold flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                            <span>Gesetzliche Regelung (§ 19 UStG):</span>
                        </p>
                        <p>
                            Die Kleinunternehmerregelung gilt steuerrechtlich <strong>stets für ein volles Kalenderjahr (01.01. bis 31.12.)</strong>.
                            Ein Verzicht oder Wegfall mitten im Jahr führt dazu, dass <strong>rückwirkend für alle Umsätze seit dem 01. Januar</strong> die reguläre Umsatzsteuer ans Finanzamt abgeführt werden muss.
                        </p>
                    </div>

                    @if(!$pendingSmallBusinessState)
                        {{-- DEAKTIVIERUNG: Optionen anbieten --}}
                        <div class="space-y-4 mb-8">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                Wie soll die Umstellung erfolgen?
                            </label>

                            <label class="flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition-all {{ $smallBusinessTransitionMode === 'immediate_retroactive' ? 'border-[var(--theme-color)] bg-[var(--theme-color-5)] dark:bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300' }}">
                                <input type="radio" wire:model="smallBusinessTransitionMode" value="immediate_retroactive" class="mt-1 text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                                <div class="min-w-0">
                                    <span class="block text-sm font-bold text-gray-900 dark:text-white">Sofort umstellen (Gilt für das gesamte Steuerjahr {{ now()->year }})</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block leading-relaxed">
                                        • <strong>Ab sofort:</strong> Shop & Rechnungen weisen reguläre MwSt aus.<br>
                                        • <strong>Bisherige Umsätze {{ now()->year }}:</strong> Die UStVA-Zentrale rechnet die USt automatisch aus den bisherigen Brutto-Umsätzen heraus (Steuerschuld für das Finanzamt).<br>
                                        • <strong>Vorsteuer:</strong> Der Vorsteuerabzug auf alle Betriebsausgaben aus {{ now()->year }} wird aktiviert.
                                    </span>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition-all {{ $smallBusinessTransitionMode === 'next_year' ? 'border-[var(--theme-color)] bg-[var(--theme-color-5)] dark:bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300' }}">
                                <input type="radio" wire:model="smallBusinessTransitionMode" value="next_year" class="mt-1 text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                                <div class="min-w-0">
                                    <span class="block text-sm font-bold text-gray-900 dark:text-white">Geplant zum 01.01.{{ now()->year + 1 }} umstellen (Empfohlen)</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block leading-relaxed">
                                        • Dein Shop bleibt für den Rest von {{ now()->year }} noch Kleinunternehmer (§ 19 UStG).<br>
                                        • Pünktlich zum 01.01.{{ now()->year + 1 }} schaltet das System automatisch auf Regelbesteuerung um. Keine rückwirkenden Steuerberechnungen nötig.
                                    </span>
                                </div>
                            </label>
                        </div>
                    @else
                        {{-- AKTIVIERUNG --}}
                        <div class="mb-8 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            Du aktivierst die Kleinunternehmerregelung für das Steuerjahr {{ now()->year }}. Im Checkout und auf Rechnungen wird ab sofort keine Umsatzsteuer mehr berechnet.
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <button type="button" wire:click="cancelSmallBusinessTransition" class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Abbrechen
                        </button>
                        <button type="button" wire:click="confirmSmallBusinessTransition" class="px-6 py-2.5 rounded-xl bg-[var(--theme-color)] text-white text-xs font-bold hover:opacity-95 shadow-md transition-all">
                            Bestätigen & Übernehmen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
