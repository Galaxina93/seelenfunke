<div x-show="activeTab === 'general'" class="space-y-6 md:space-y-8 animate-fade-in">
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8 relative overflow-hidden">
        <div class="hidden dark:block absolute top-0 right-0 w-64 h-64 bg-[var(--theme-color)]/5 rounded-full blur-[80px] -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>

        <h3 class="text-sm sm:text-base font-serif font-bold text-gray-900 dark:text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-100 dark:border-gray-800 pb-4">
            <span class="w-8 h-8 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner flex items-center justify-center italic font-serif shrink-0">%</span>
            Steuer & Status
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Steuer-Modus (§ 19 UStG)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'is_small_business'])
                </div>
                <div class="space-y-3">
                    <button type="button" wire:click="initiateSmallBusinessToggle" class="{{ $checkboxContainerClass }} w-full text-left">
                        <div class="relative flex items-center shrink-0">
                            <input type="checkbox" {{ !empty($settings['is_small_business']) ? 'checked' : '' }} class="peer sr-only pointer-events-none" readonly>
                            <div class="w-5 h-5 bg-gray-100 dark:bg-gray-900 border-2 border-gray-300 dark:border-gray-700 rounded transition-all {{ !empty($settings['is_small_business']) ? 'bg-[var(--theme-color)] border-[var(--theme-color)]' : '' }} shadow-sm dark:shadow-inner"></div>
                            <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 {{ !empty($settings['is_small_business']) ? 'opacity-100' : 'opacity-0' }} transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="block font-bold text-gray-900 dark:text-white text-sm group-hover:text-[var(--theme-color)] transition-colors">Kleinunternehmerregelung</span>
                                <span class="text-[9px] px-2 py-0.5 rounded-full font-black uppercase tracking-wider border {{ $this->taxStatusSummary['badge_color'] }}">
                                    {{ $this->taxStatusSummary['badge'] }}
                                </span>
                            </div>
                            <span class="text-[10px] text-gray-500 font-medium mt-0.5 block leading-tight">{{ $this->taxStatusSummary['desc'] }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Wartungsmodus</label>
                    @include('components.alerts.info-tooltip', ['key' => 'maintenance_mode'])
                </div>
                <label class="{{ $checkboxContainerClass }}">
                    <div class="relative flex items-center shrink-0">
                        <input type="checkbox" wire:model="settings.maintenance_mode" class="peer sr-only">
                        <div class="w-5 h-5 bg-gray-100 dark:bg-gray-900 border-2 border-gray-300 dark:border-gray-700 rounded transition-all peer-checked:bg-red-500 peer-checked:border-red-500 shadow-sm dark:shadow-inner"></div>
                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <span class="block font-bold text-gray-900 dark:text-white text-sm group-hover:text-red-500 dark:group-hover:text-red-400 transition-colors">Shop/Konfigurator offline!</span>
                        <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wider mt-0.5 block">Die Shopseite und der Konfigurator sind nicht mehr erreichbar.</span>
                    </div>
                </label>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Standard-MwSt Satz (%)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'default_tax_rate'])
                </div>
                <div class="relative">
                    <input type="number" wire:model="settings.default_tax_rate" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-black">%</span>
                </div>
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

    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-3 tracking-wide border-b border-gray-100 dark:border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner shrink-0">
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
