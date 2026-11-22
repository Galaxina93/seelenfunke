<div x-show="activeTab === 'owner'" class="space-y-6 md:space-y-8 animate-fade-in" style="display: none;">
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-10 relative">
        <div class="hidden dark:block absolute inset-0 overflow-hidden rounded-[2.5rem] pointer-events-none">
            <div class="absolute top-0 right-0 w-64 h-64 bg-[var(--theme-color)]/5 rounded-full blur-[80px] -translate-y-1/2 translate-x-1/3"></div>
        </div>

        <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white mb-8 border-b border-gray-100 dark:border-gray-800 pb-5 flex items-center gap-3 tracking-wide">
            <div class="p-2.5 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
            </div>
            Firmenstammdaten (Shop, Rechnungen, DHL)
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 relative z-10 mb-12">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0 text-[var(--theme-color-80)]">Unternehmensname / Shop-Marke</label>
                    @include('components.alerts.info-tooltip', ['key' => 'company_name'])
                </div>
                <input type="text" wire:model="settings.company_name" class="{{ $inputClass }} !text-base !font-bold border-[var(--theme-color-30)] focus:border-[var(--theme-color)]">
            </div>

            <div class="md:col-span-2 h-px bg-gray-100 dark:bg-gray-800 my-2"></div>

            <div>
                <label class="{{ $labelClass }}">Straße</label>
                <input type="text" wire:model="settings.company_street" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Hausnummer</label>
                <input type="text" wire:model="settings.company_street_number" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Postleitzahl</label>
                <input type="text" wire:model="settings.company_zip" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Stadt</label>
                <input type="text" wire:model="settings.company_city" class="{{ $inputClass }}">
            </div>

            <div class="md:col-span-2">
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Land (z.B. DE, AT, CH)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'company_country'])
                </div>
                <input type="text" maxlength="2" wire:model="settings.company_country" class="{{ $inputClass }} uppercase font-mono max-w-[120px]">
                @error('settings.company_country') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2 h-px bg-gray-100 dark:bg-gray-800 my-2"></div>

            <div>
                <label class="{{ $labelClass }}">Öffentliche Telefonnummer</label>
                <input type="text" wire:model="settings.company_phone" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Öffentliche Firmen-Email</label>
                <input type="email" wire:model="settings.company_email" class="{{ $inputClass }}">
                @error('settings.company_email') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>


        <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white mb-8 border-b border-gray-100 dark:border-gray-800 pb-5 flex items-center gap-3 tracking-wide">
            <div class="p-2.5 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            </div>
            Inhaber-Daten (Impressumspflicht & Intern)
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 relative z-10">
            <div class="md:col-span-2">
                <label class="{{ $labelClass }} text-gray-500">Interner Bezeichner</label>
                <input type="text" wire:model="settings.owner_name" class="{{ $inputClass }}">
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Inhaberin (Impressum)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'owner_proprietor'])
                </div>
                <input type="text" wire:model="settings.owner_proprietor" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Webseite</label>
                <input type="text" wire:model="settings.owner_website" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Private Straße & Hausnr.</label>
                <input type="text" wire:model="settings.owner_street" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Private PLZ & Ort</label>
                <input type="text" wire:model="settings.owner_city" class="{{ $inputClass }}">
            </div>

            <div class="md:col-span-2">
                <label class="{{ $labelClass }}">Private Notfall-Telefonnummer</label>
                <input type="text" wire:model="settings.owner_phone" class="{{ $inputClass }}">
            </div>

            {{-- E-Mail Routing & Postfächer --}}
            <div class="md:col-span-2 mt-6 pt-6 border-t border-gray-200 dark:border-[var(--theme-color-20)]">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-1.5 bg-[var(--theme-color-10)] rounded-lg text-[var(--theme-color)] shadow-inner border border-[var(--theme-color-20)]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <h4 class="text-[10px] font-black text-[var(--theme-color)] uppercase tracking-widest drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">E-Mail Routing & Postfächer</h4>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- 1. Kontakt / Allgemein --}}
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2 mb-1.5 ml-1">
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Zentrale E-Mail-Adresse (Kontakt, Impressum, Rechnungen & System) *</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_email'])
                        </div>
                        <input type="email" wire:model.blur="settings.owner_email" class="w-full bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] outline-none shadow-sm dark:shadow-inner transition-all placeholder:text-gray-400 dark:placeholder:text-gray-600">
                        @error('settings.owner_email') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Erweiterte Behördliche Daten --}}
            <div class="md:col-span-2 mt-8 pt-8 border-t border-gray-200 dark:border-[var(--theme-color-20)] relative z-20">
                <h4 class="text-[10px] font-black text-[var(--theme-color)] uppercase tracking-widest mb-6 flex items-center gap-3 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">
                    <span class="p-1.5 rounded-lg bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] shadow-inner">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </span>
                    Behördliche Identifikationsnummern
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="{{ $labelClass }}">Steuernummer</label>
                        <input type="text" wire:model="settings.owner_tax_id" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">USt-IdNr.</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_ust_id'])
                        </div>
                        <input type="text" wire:model="settings.owner_ust_id" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Steuer-ID (Persönlich)</label>
                        <input type="text" wire:model="settings.owner_tax_ident_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Finanzamt-Nr.</label>
                        <input type="text" wire:model="settings.owner_finanzamt_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">Wirtschafts-Ident-Nr.</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_economic_ident_nr'])
                        </div>
                        <input type="text" wire:model="settings.owner_economic_ident_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Sozialversicherungs-Nr.</label>
                        <input type="text" wire:model="settings.owner_social_security_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Krankenkassen-Nr.</label>
                        <input type="text" wire:model="settings.owner_health_insurance_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Agentur f. Arbeit Nr.</label>
                        <input type="text" wire:model="settings.owner_agency_labor_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">Gerichtsstand</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_court'])
                        </div>
                        <input type="text" wire:model="settings.owner_court" class="{{ $inputClass }}">
                    </div>
                </div>
            </div>

            {{-- Bankverbindung Box (High Highlight) --}}
            <div class="md:col-span-2 bg-[var(--theme-color)]/5 p-6 sm:p-8 rounded-[2rem] border border-[var(--theme-color-20)] mt-6 shadow-sm dark:shadow-inner relative z-10 group">

                {{-- Glanz-Effekt --}}
                <div class="absolute inset-0 overflow-hidden rounded-[2rem] pointer-events-none">
                    <div class="absolute inset-0 bg-gradient-to-r from-[var(--theme-color)]/0 via-primary/5 to-primary/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000 ease-in-out"></div>
                </div>

                <div class="relative z-20">
                    <h4 class="text-sm font-black text-[var(--theme-color)] uppercase tracking-widest mb-6 flex items-center gap-3 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">
                        <span class="p-2 rounded-lg bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] shadow-inner">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                        </span>
                        Bankverbindung
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="{{ $labelClass }} text-[var(--theme-color)]/80">Name der Bank</label>
                            <input type="text" wire:model="settings.owner_bank_name" class="{{ $inputClass }} border-[var(--theme-color-30)] focus:border-[var(--theme-color)]" placeholder="z.B. Volksbank">
                        </div>

                        <div>
                            <label class="{{ $labelClass }} text-[var(--theme-color)]/80">BIC / SWIFT</label>
                            <input type="text" wire:model="settings.owner_bic" class="{{ $inputClass }} border-[var(--theme-color-30)] focus:border-[var(--theme-color)] font-mono uppercase" placeholder="GENODE...">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-[var(--theme-color)] uppercase tracking-[0.2em] mb-2 ml-1 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">IBAN</label>
                            <input type="text" wire:model="settings.owner_iban"
                                   class="w-full rounded-xl bg-white dark:bg-gray-950/80 border border-[var(--theme-color-30)] focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color)]/20 py-4 px-6 font-mono tracking-[0.2em] text-lg sm:text-xl text-[var(--theme-color)] shadow-sm dark:shadow-inner outline-none text-center uppercase placeholder-[var(--theme-color-30)]"
                                   placeholder="DE00 0000 0000 0000 0000 00">
                        </div>

                        <div class="md:col-span-2">
                            <label class="{{ $labelClass }} text-[var(--theme-color)]/80">Adresse der Bank (Optional)</label>
                            <input type="text" wire:model="settings.owner_bank_address" class="{{ $inputClass }} border-[var(--theme-color-30)] focus:border-[var(--theme-color)]" placeholder="Straße, PLZ Ort">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-[var(--theme-color)]/10">
                        <p class="text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)]/60 text-center">Hinweis: Diese Daten werden im Rechnungs-Footer sowie bei B2B-Rechnungskauf angezeigt.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
