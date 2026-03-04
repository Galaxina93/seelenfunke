<div class="p-4 lg:p-8 bg-transparent min-h-screen font-sans antialiased text-gray-300" x-data="{ activeTab: 'general' }">
    <div class="max-w-6xl mx-auto animate-fade-in-up">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div>
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-tight">Shop-Konfiguration</h1>
                <p class="text-xs sm:text-sm text-gray-400 mt-2 font-medium">Zentrale Steuerung aller Shop-Parameter und rechtlichen Grundlagen.</p>
            </div>
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                x-data="{ saved: @entangle('saved') }"
                x-effect="if (saved) { setTimeout(() => { saved = false; $wire.resetSaved(); }, 3000) }"
                :class="saved ? 'bg-emerald-500 text-gray-900 shadow-[0_0_20px_rgba(16,185,129,0.4)]' : 'bg-primary text-gray-900 hover:bg-primary-dark hover:scale-[1.02] shadow-[0_0_15px_rgba(197,160,89,0.2)]'"
                class="px-8 py-3.5 rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all flex items-center gap-3 min-w-[240px] justify-center border border-transparent">

                <svg wire:loading class="animate-spin h-5 w-5 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <template x-if="saved">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-900 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Erfolgreich gespeichert!</span>
                    </div>
                </template>

                <template x-if="!saved">
                    <div class="flex items-center gap-2" wire:loading.remove>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        <span>Einstellungen sichern</span>
                    </div>
                </template>
            </button>
        </div>

        {{-- Tab Navigation --}}
        <div class="flex border-b border-gray-800 mb-8 overflow-x-auto no-scrollbar gap-2 sm:gap-6">
            <button @click="activeTab = 'general'" :class="activeTab === 'general' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'" class="whitespace-nowrap pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all">Allgemein & Steuern</button>
            <button @click="activeTab = 'products'" :class="activeTab === 'products' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'" class="whitespace-nowrap pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all">Produkt & Marketing</button>
            <button @click="activeTab = 'shipping'" :class="activeTab === 'shipping' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'" class="whitespace-nowrap pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all">Versand & Länder</button>
            <button @click="activeTab = 'owner'" :class="activeTab === 'owner' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'" class="whitespace-nowrap pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all">Stammdaten (Impressum)</button>
        </div>

        @php
            $inputClass = "w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm p-3.5 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all shadow-inner outline-none placeholder-gray-600";
            $labelClass = "block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2 ml-1";
            $checkboxContainerClass = "flex items-center gap-4 cursor-pointer p-4 rounded-2xl border border-gray-800 bg-gray-950 hover:border-gray-700 hover:bg-gray-900/80 transition-all shadow-inner group";
        @endphp

        <div class="grid grid-cols-1 gap-8">

            {{-- TAB: ALLGEMEIN --}}
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

            {{-- TAB: PRODUKT & MARKETING --}}
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

            {{-- TAB: VERSAND --}}
            <div x-show="activeTab === 'shipping'" class="space-y-6 md:space-y-8 animate-fade-in" style="display: none;">
                <div class="bg-amber-500/10 border border-amber-500/20 p-5 rounded-[2rem] shadow-inner relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 text-amber-500/10 rotate-12 pointer-events-none">
                        <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 relative z-10">
                        <div class="flex items-center gap-4">
                            <div class="p-2.5 bg-amber-500/20 text-amber-400 rounded-xl shadow-inner shrink-0">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <p class="text-sm text-amber-200/80 font-medium leading-relaxed">
                                Länder werden zentral in der <strong class="text-amber-400 tracking-wide">Versandverwaltung</strong> aktiviert.
                            </p>
                        </div>
                        <a href="{{ route('admin.shipping') }}" class="text-[9px] font-black uppercase tracking-widest bg-amber-500 text-gray-900 px-5 py-2.5 rounded-xl hover:bg-amber-400 hover:scale-105 transition-all shadow-[0_0_15px_rgba(245,158,11,0.2)] shrink-0">Verwalten</a>
                    </div>
                </div>

                {{-- Versandkosten & Konditionen --}}
                <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
                    <h3 class="text-sm sm:text-base font-serif font-bold text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-800 pb-4">
                        <div class="p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 shadow-inner shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        Versandkosten & Konditionen
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 xl:gap-8">
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
                </div>

                <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
                    <h3 class="text-sm sm:text-base font-serif font-bold text-white mb-6 flex items-center gap-3 tracking-wide border-b border-gray-800 pb-4">
                        <div class="p-2 rounded-xl bg-gray-800 border border-gray-700 text-gray-400 shadow-inner shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064" /></svg>
                        </div>
                        Aktive Lieferländer
                    </h3>
                    <div class="flex flex-wrap gap-3">
                        @forelse($this->activeShippingCountries as $code => $name)
                            <span class="inline-flex items-center px-4 py-2.5 rounded-xl bg-gray-950 text-gray-300 text-[10px] font-black uppercase tracking-widest border border-gray-800 shadow-inner">
                                <img src="https://flagcdn.com/16x12/{{ strtolower($code) }}.png" class="mr-3 rounded-[2px] opacity-80" alt="{{ $code }}">
                                {{ $name }}
                            </span>
                        @empty
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-600 bg-gray-950 px-4 py-3 rounded-xl border border-gray-800 w-full text-center">Keine Länder in den Versandzonen hinterlegt.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- TAB: STAMMDATEN --}}
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
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="{{ $labelClass }} !mb-0 !ml-0">E-Mail für Kunden</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_email'])
                            </div>
                            <input type="email" wire:model="settings.owner_email" class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label class="{{ $labelClass }}">Telefonnummer</label>
                            <input type="text" wire:model="settings.owner_phone" class="{{ $inputClass }}">
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

        </div>
    </div>
</div>
