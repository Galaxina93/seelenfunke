<div x-show="activeTab === 'owner'" class="space-y-6 md:space-y-8 animate-fade-in" style="display: none;">
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-10 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full blur-[80px] -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>

        <h3 class="text-lg sm:text-xl font-serif font-bold text-white mb-8 border-b border-gray-800 pb-5 flex items-center gap-3 tracking-wide">
            <div class="p-2.5 rounded-xl bg-primary/10 border border-primary/20 text-primary shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
            </div>
            Rechtliche Stammdaten (Impressum & Rechnungen)
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 relative z-10">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Shop-Marke / Unternehmensname</label>
                    @include('components.alerts.info-tooltip', ['key' => 'owner_name'])
                </div>
                <input type="text" wire:model="settings.owner_name" class="{{ $inputClass }} !text-base !font-bold">
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Inhaberin</label>
                    @include('components.alerts.info-tooltip', ['key' => 'owner_proprietor'])
                </div>
                <input type="text" wire:model="settings.owner_proprietor" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Webseite</label>
                <input type="text" wire:model="settings.owner_website" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Straße & Hausnummer</label>
                <input type="text" wire:model="settings.owner_street" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">PLZ & Ort</label>
                <input type="text" wire:model="settings.owner_city" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="{{ $labelClass }}">Telefonnummer</label>
                <input type="text" wire:model="settings.owner_phone" class="{{ $inputClass }}">
            </div>

            {{-- E-Mail Routing & Postfächer --}}
            <div class="md:col-span-2 mt-6 pt-6 border-t border-orange-500/20">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-1.5 bg-orange-500/10 rounded-lg text-orange-400 shadow-inner border border-orange-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <h4 class="text-[10px] font-black text-orange-400 uppercase tracking-widest drop-shadow-[0_0_8px_currentColor]">E-Mail Routing & Postfächer</h4>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- 1. Kontakt / Allgemein --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1.5 ml-1">
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Kontakt / Allgemein *</label>
                            <div x-data="{show: false}" class="relative inline-block" @click.stop>
                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-500 hover:text-orange-400 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 p-3 bg-gray-900 border border-gray-700 text-gray-300 text-[10px] rounded-xl shadow-2xl z-50 text-center font-medium">{{ $infoTexts['owner_email'] }}<div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-700"></div></div>
                            </div>
                        </div>
                        <input type="email" wire:model.blur="settings.owner_email" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl px-4 py-3 text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none shadow-inner transition-all placeholder:text-gray-600">
                    </div>

                    {{-- 2. Impressum --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1.5 ml-1">
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Impressum & Rechtliches</label>
                            <div x-data="{show: false}" class="relative inline-block" @click.stop>
                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-500 hover:text-orange-400 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 p-3 bg-gray-900 border border-gray-700 text-gray-300 text-[10px] rounded-xl shadow-2xl z-50 text-center font-medium">{{ $infoTexts['owner_email_impressum'] }}<div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-700"></div></div>
                            </div>
                        </div>
                        <input type="email" wire:model.blur="settings.owner_email_impressum" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl px-4 py-3 text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none shadow-inner transition-all placeholder:text-gray-600">
                    </div>

                    {{-- 3. Rechnungen --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1.5 ml-1">
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Buchhaltung & Rechnungen</label>
                            <div x-data="{show: false}" class="relative inline-block" @click.stop>
                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-500 hover:text-orange-400 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 p-3 bg-gray-900 border border-gray-700 text-gray-300 text-[10px] rounded-xl shadow-2xl z-50 text-center font-medium">{{ $infoTexts['owner_email_invoices'] }}<div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-700"></div></div>
                            </div>
                        </div>
                        <input type="email" wire:model.blur="settings.owner_email_invoices" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl px-4 py-3 text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none shadow-inner transition-all placeholder:text-gray-600">
                    </div>

                    {{-- 4. Backup --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1.5 ml-1">
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">System & Backups</label>
                            <div x-data="{show: false}" class="relative inline-block" @click.stop>
                                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-500 hover:text-orange-400 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 p-3 bg-gray-900 border border-gray-700 text-gray-300 text-[10px] rounded-xl shadow-2xl z-50 text-center font-medium">{{ $infoTexts['owner_email_backup'] }}<div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-700"></div></div>
                            </div>
                        </div>
                        <input type="email" wire:model.blur="settings.owner_email_backup" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl px-4 py-3 text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none shadow-inner transition-all placeholder:text-gray-600">
                    </div>
                </div>
            </div>

            {{-- Erweiterte Behördliche Daten --}}
            <div class="md:col-span-2 mt-8 pt-8 border-t border-gray-800 relative z-20">
                <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-3">
                                <span class="p-1.5 rounded-lg bg-gray-800 text-gray-500 shadow-inner">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                </span>
                    Behördliche Identifikationsnummern
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">Steuernummer</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_tax_id'])
                        </div>
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
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">Steuer-ID (Persönlich)</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_tax_ident_nr'])
                        </div>
                        <input type="text" wire:model="settings.owner_tax_ident_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">Finanzamt-Nr.</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_finanzamt_nr'])
                        </div>
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
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">Sozialversicherungs-Nr.</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_social_security_nr'])
                        </div>
                        <input type="text" wire:model="settings.owner_social_security_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">Krankenkassen-Nr.</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_health_insurance_nr'])
                        </div>
                        <input type="text" wire:model="settings.owner_health_insurance_nr" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClass }} !mb-0 !ml-0">Agentur f. Arbeit Nr.</label>
                            @include('components.alerts.info-tooltip', ['key' => 'owner_agency_labor_nr'])
                        </div>
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
            <div class="md:col-span-2 bg-primary/5 p-6 sm:p-8 rounded-[2rem] border border-primary/20 mt-6 shadow-inner relative z-10 group">

                {{-- Glanz-Effekt --}}
                <div class="absolute inset-0 overflow-hidden rounded-[2rem] pointer-events-none">
                    <div class="absolute inset-0 bg-gradient-to-r from-primary/0 via-primary/5 to-primary/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000 ease-in-out"></div>
                </div>

                <div class="relative z-20">
                    <h4 class="text-sm font-black text-primary uppercase tracking-widest mb-6 flex items-center gap-3 drop-shadow-[0_0_8px_currentColor]">
                                    <span class="p-2 rounded-lg bg-primary/10 border border-primary/20 shadow-inner">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                    </span>
                        Bankverbindung
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="{{ $labelClass }} !mb-0 !ml-0 text-primary/80">Name der Bank</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_bank_name'])
                            </div>
                            <input type="text" wire:model="settings.owner_bank_name" class="{{ $inputClass }} border-primary/30 focus:border-primary" placeholder="z.B. Volksbank">
                        </div>

                        <div>
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="{{ $labelClass }} !mb-0 !ml-0 text-primary/80">BIC / SWIFT</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_bic'])
                            </div>
                            <input type="text" wire:model="settings.owner_bic" class="{{ $inputClass }} border-primary/30 focus:border-primary font-mono uppercase" placeholder="GENODE...">
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="text-[10px] font-black text-primary uppercase tracking-[0.2em] drop-shadow-[0_0_8px_currentColor]">IBAN</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_iban'])
                            </div>
                            <input type="text" wire:model="settings.owner_iban"
                                   class="w-full rounded-xl bg-gray-950/80 border border-primary/30 focus:border-primary focus:ring-2 focus:ring-primary/20 py-4 px-6 font-mono tracking-[0.2em] text-lg sm:text-xl text-primary shadow-inner outline-none text-center uppercase"
                                   placeholder="DE00 0000 0000 0000 0000 00">
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="{{ $labelClass }} !mb-0 !ml-0 text-primary/80">Adresse der Bank (Optional)</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_bank_address'])
                            </div>
                            <input type="text" wire:model="settings.owner_bank_address" class="{{ $inputClass }} border-primary/30 focus:border-primary" placeholder="Straße, PLZ Ort">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-primary/10">
                        <p class="text-[9px] font-black uppercase tracking-widest text-primary/60 text-center">Hinweis: Diese Daten werden auf Rechnungen für Vorkasse-Zahlungen angezeigt.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
