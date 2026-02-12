<div class="p-4 lg:p-10 bg-gray-50 min-h-screen" x-data="{ activeTab: 'general' }">
    <div class="max-w-6xl mx-auto">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div>
                <h1 class="text-3xl font-serif font-bold text-gray-900">Shop-Konfiguration</h1>
                <p class="text-gray-500 mt-1">Zentrale Steuerung aller Shop-Parameter und rechtlichen Grundlagen.</p>
            </div>
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                x-data="{ saved: @entangle('saved') }"
                x-effect="if (saved) { setTimeout(() => { saved = false; $wire.resetSaved(); }, 3000) }"
                :class="saved ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-900 hover:bg-black'"
                class="text-white px-8 py-3 rounded-full font-bold shadow-lg transition-all flex items-center gap-2 min-w-[200px] justify-center">

                <svg wire:loading class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <template x-if="saved">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-white animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                        <span>Einstellungen speichern</span>
                    </div>
                </template>
            </button>
        </div>

        {{-- Tab Navigation --}}
        <div class="flex border-b border-gray-200 mb-8 overflow-x-auto no-scrollbar">
            <button @click="activeTab = 'general'" :class="activeTab === 'general' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap pb-4 px-6 border-b-2 font-bold text-sm transition-all">Allgemein & Steuern</button>
            <button @click="activeTab = 'products'" :class="activeTab === 'products' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap pb-4 px-6 border-b-2 font-bold text-sm transition-all">Produkt & Marketing</button>
            <button @click="activeTab = 'stripe'" :class="activeTab === 'stripe' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap pb-4 px-6 border-b-2 font-bold text-sm transition-all">Zahlung (Stripe)</button>
            <button @click="activeTab = 'shipping'" :class="activeTab === 'shipping' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap pb-4 px-6 border-b-2 font-bold text-sm transition-all">Versand & Länder</button>
            <button @click="activeTab = 'owner'" :class="activeTab === 'owner' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap pb-4 px-6 border-b-2 font-bold text-sm transition-all">Stammdaten (Impressum)</button>
            <button @click="activeTab = 'scheduler'" :class="activeTab === 'scheduler' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap pb-4 px-6 border-b-2 font-bold text-sm transition-all">Automatisierung</button>
        </div>

        <div class="grid grid-cols-1 gap-8">

            {{-- TAB: ALLGEMEIN --}}
            <div x-show="activeTab === 'general'" class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center italic font-serif">%</span>
                        Steuer & Status
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">Steuer-Modus</label>
                                @include('components.alerts.info-tooltip', ['key' => 'is_small_business'])
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer p-4 rounded-xl border border-gray-300 bg-gray-50 hover:bg-white transition-all">
                                <input type="checkbox" wire:model="settings.is_small_business" class="w-5 h-5 text-primary rounded border-gray-300 focus:ring-primary">
                                <div>
                                    <span class="block font-bold text-gray-900 text-sm">Kleinunternehmerregelung</span>
                                    <span class="text-xs text-gray-500">Aktiviert § 19 UStG (keine MwSt-Berechnung)</span>
                                </div>
                            </label>
                        </div>
                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">Wartungsmodus</label>
                                @include('components.alerts.info-tooltip', ['key' => 'maintenance_mode'])
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer p-4 rounded-xl border border-gray-300 bg-gray-50 hover:bg-white transition-all">
                                <input type="checkbox" wire:model="settings.maintenance_mode" class="w-5 h-5 text-red-600 rounded border-gray-300 focus:ring-red-500">
                                <div>
                                    <span class="block font-bold text-gray-900 text-sm">Shop offline schalten</span>
                                    <span class="text-xs text-gray-500">Frontend für Kunden sperren (Maintenance)</span>
                                </div>
                            </label>
                        </div>
                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">Standard-MwSt Satz (%)</label>
                                @include('components.alerts.info-tooltip', ['key' => 'default_tax_rate'])
                            </div>
                            <div class="relative">
                                <input type="number" wire:model="settings.default_tax_rate" class="w-full rounded-xl border-gray-300 bg-white focus:border-primary focus:ring-primary py-3 px-4 text-lg font-bold">
                                <span class="absolute right-4 top-3.5 text-gray-400 font-bold">%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center gap-1 mb-6">
                        <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <h3 class="text-lg font-bold text-gray-900">Angebots-Logik</h3>
                        @include('components.alerts.info-tooltip', ['key' => 'order_quote_validity_days'])
                    </div>
                    <div class="max-w-xs">
                        <label class="block text-xs font-bold text-gray-500 mb-2 uppercase tracking-wider">Gültigkeit (Tage)</label>
                        <input type="number" wire:model="settings.order_quote_validity_days" class="w-full rounded-xl border-gray-300 focus:border-primary py-3 px-4 font-bold">
                    </div>
                </div>
            </div>

            {{-- TAB: PRODUKT & MARKETING --}}
            <div x-show="activeTab === 'products'" class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        Lager & Digitale Produkte
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">Kritischer Lagerbestand</label>
                                @include('components.alerts.info-tooltip', ['key' => 'inventory_low_stock_threshold'])
                            </div>
                            <div class="relative">
                                <input type="number" wire:model="settings.inventory_low_stock_threshold" class="w-full rounded-xl border-gray-300 bg-white focus:border-primary focus:ring-primary py-3 px-4 text-lg font-bold">
                                <span class="absolute right-4 top-3.5 text-gray-400 text-xs font-bold uppercase">Stück</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Ab dieser Menge erscheint eine Warnung im Dashboard.</p>
                        </div>

                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">Versandlogik</label>
                                @include('components.alerts.info-tooltip', ['key' => 'skip_shipping_for_digital'])
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer p-4 rounded-xl border border-gray-300 bg-gray-50 hover:bg-white transition-all">
                                <input type="checkbox" wire:model="settings.skip_shipping_for_digital" class="w-5 h-5 text-primary rounded border-gray-300 focus:ring-primary">
                                <div>
                                    <span class="block font-bold text-gray-900 text-sm">Versand bei Digitalprodukten überspringen</span>
                                    <span class="text-xs text-gray-500">Keine Versandkosten, wenn nur Downloads im Warenkorb liegen.</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB: STRIPE --}}
            <div x-show="activeTab === 'stripe'" class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <svg class="w-6 h-6 text-[#635BFF]" fill="currentColor" viewBox="0 0 24 24"><path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.895-1.352 2.222-1.352 1.85 0 3.287.669 3.287.669l.608-3.541s-1.465-.631-3.669-.631c-3.155 0-5.187 1.637-5.187 4.298 0 2.871 2.585 4.025 5.518 5.044 2.193.75 2.766 1.488 2.766 2.384 0 1.258-1.503 1.706-3.045 1.706-2.316 0-3.957-.887-3.957-.887l-.683 3.633s1.77.712 4.148.712c3.705 0 5.617-1.802 5.617-4.492 0-3.08-2.613-4.14-5.269-5.046z"/></svg>
                        Stripe API Konfiguration
                    </h3>

                    <div class="mb-6 bg-blue-50 text-blue-700 p-4 rounded-xl text-sm border border-blue-100">
                        <strong>Hinweis:</strong> Ob der Test- oder Live-Modus aktiv ist, wird automatisch anhand der Schlüssel erkannt.
                        <br><code>pk_test_...</code> = Testmodus (Sandbox) | <code>pk_live_...</code> = Livemodus (Echtgeld)
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        <div class="bg-primary/5 p-6 rounded-2xl border border-primary/10">
                            <div class="flex items-center gap-1 mb-3">
                                <label class="text-[11px] font-bold text-primary uppercase tracking-widest">Publishable Key</label>
                                @include('components.alerts.info-tooltip', ['key' => 'stripe_publishable_key'])
                            </div>
                            <input type="text" wire:model="settings.stripe_publishable_key"
                                   class="w-full rounded-xl border-primary/20 bg-white focus:ring-4 focus:ring-primary/10 focus:border-primary py-4 px-6 font-mono tracking-wider text-sm text-primary shadow-inner outline-none"
                                   placeholder="pk_test_... oder pk_live_...">
                        </div>

                        <div class="bg-primary/5 p-6 rounded-2xl border border-primary/10">
                            <div class="flex items-center gap-1 mb-3">
                                <label class="text-[11px] font-bold text-primary uppercase tracking-widest">Secret Key</label>
                                @include('components.alerts.info-tooltip', ['key' => 'stripe_secret_key'])
                            </div>
                            <input type="password" wire:model="settings.stripe_secret_key"
                                   class="w-full rounded-xl border-primary/20 bg-white focus:ring-4 focus:ring-primary/10 focus:border-primary py-4 px-6 font-mono tracking-wider text-sm text-primary shadow-inner outline-none"
                                   placeholder="sk_test_... oder sk_live_...">
                        </div>

                        <div class="bg-primary/5 p-6 rounded-2xl border border-primary/10">
                            <div class="flex items-center gap-1 mb-3">
                                <label class="text-[11px] font-bold text-primary uppercase tracking-widest">Webhook Secret</label>
                                @include('components.alerts.info-tooltip', ['key' => 'stripe_webhook_secret'])
                            </div>
                            <input type="password" wire:model="settings.stripe_webhook_secret"
                                   class="w-full rounded-xl border-primary/20 bg-white focus:ring-4 focus:ring-primary/10 focus:border-primary py-4 px-6 font-mono tracking-wider text-sm text-primary shadow-inner outline-none"
                                   placeholder="whsec_...">
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB: VERSAND --}}
            <div x-show="activeTab === 'shipping'" class="space-y-6">
                <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-amber-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p class="text-sm text-amber-700">Länder werden zentral in der <span class="font-bold">Versandverwaltung</span> aktiviert.</p>
                        </div>
                        <a href="{{ route('admin.shipping') }}" class="text-xs font-bold bg-amber-200 text-amber-800 px-3 py-1 rounded-full hover:bg-amber-300 transition shadow-sm">Verwalten →</a>
                    </div>
                </div>

                {{-- Versandkosten & Konditionen --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Versandkosten & Konditionen
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">Standard Versandkosten</label>
                                @include('components.alerts.info-tooltip', ['key' => 'shipping_cost'])
                            </div>
                            <div class="relative">
                                <input type="number" step="0.01" wire:model="settings.shipping_cost" class="w-full rounded-xl border-gray-300 bg-white focus:border-primary focus:ring-primary py-3 px-4 text-lg font-bold">
                                <span class="absolute right-4 top-3.5 text-gray-400 font-bold">€</span>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">Versandkostenfrei ab</label>
                                @include('components.alerts.info-tooltip', ['key' => 'shipping_free_threshold'])
                            </div>
                            <div class="relative">
                                <input type="number" step="0.01" wire:model="settings.shipping_free_threshold" class="w-full rounded-xl border-gray-300 bg-white focus:border-primary focus:ring-primary py-3 px-4 text-lg font-bold">
                                <span class="absolute right-4 top-3.5 text-gray-400 font-bold">€</span>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">Express-Aufschlag</label>
                                @include('components.alerts.info-tooltip', ['key' => 'express_surcharge'])
                            </div>
                            <div class="relative">
                                <input type="number" step="0.01" wire:model="settings.express_surcharge" class="w-full rounded-xl border-gray-300 bg-white focus:border-primary focus:ring-primary py-3 px-4 text-lg font-bold">
                                <span class="absolute right-4 top-3.5 text-gray-400 font-bold">€</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064" /></svg>
                        Aktive Lieferländer
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($this->activeShippingCountries as $code => $name)
                            <span class="inline-flex items-center px-4 py-2 rounded-full bg-white text-gray-700 text-xs font-bold border border-gray-200 shadow-sm">
                                <img src="https://flagcdn.com/16x12/{{ strtolower($code) }}.png" class="mr-2 rounded-sm" alt="{{ $code }}">
                                {{ $name }}
                            </span>
                        @empty
                            <p class="text-sm text-gray-400 italic">Keine Länder in den Versandzonen hinterlegt.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- TAB: STAMMDATEN --}}
            <div x-show="activeTab === 'owner'" class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-8 border-b pb-4 flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        Rechtliche Stammdaten (Impressum & Rechnungen)
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                        <div class="md:col-span-2">
                            <div class="flex items-center gap-1 mb-2">
                                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Shop-Marke / Unternehmensname</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_name'])
                            </div>
                            <input type="text" wire:model="settings.owner_name"
                                   class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary py-4 px-5 font-bold text-gray-900 shadow-sm transition-all outline-none">
                        </div>

                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Inhaberin</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_proprietor'])
                            </div>
                            <input type="text" wire:model="settings.owner_proprietor"
                                   class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary py-3.5 px-5 text-gray-900 font-medium transition-all outline-none">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">Webseite</label>
                            <input type="text" wire:model="settings.owner_website"
                                   class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary py-3.5 px-5 text-gray-900 font-medium transition-all outline-none">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">Straße & Hausnummer</label>
                            <input type="text" wire:model="settings.owner_street"
                                   class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary py-3.5 px-5 text-gray-900 font-medium transition-all outline-none">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">PLZ & Ort</label>
                            <input type="text" wire:model="settings.owner_city"
                                   class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary py-3.5 px-5 text-gray-900 font-medium transition-all outline-none">
                        </div>

                        <div>
                            <div class="flex items-center gap-1 mb-2">
                                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">E-Mail für Kunden</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_email'])
                            </div>
                            <input type="email" wire:model="settings.owner_email"
                                   class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary py-3.5 px-5 text-gray-900 font-medium transition-all outline-none">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">Telefonnummer</label>
                            <input type="text" wire:model="settings.owner_phone"
                                   class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary py-3.5 px-5 text-gray-900 font-medium transition-all outline-none">
                        </div>

                        {{-- Erweiterte Behördliche Daten --}}
                        <div class="md:col-span-2 mt-6 pt-6 border-t border-gray-100">
                            <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-6 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                Behördliche Identifikationsnummern
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Steuernummer</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_tax_id'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_tax_id" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">USt-IdNr.</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_ust_id'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_ust_id" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Steuer-ID (Persönlich)</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_tax_ident_nr'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_tax_ident_nr" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Finanzamt-Nr.</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_finanzamt_nr'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_finanzamt_nr" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Wirtschafts-Ident-Nr.</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_economic_ident_nr'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_economic_ident_nr" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Sozialversicherungs-Nr.</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_social_security_nr'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_social_security_nr" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Krankenkassen-Nr.</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_health_insurance_nr'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_health_insurance_nr" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Agentur f. Arbeit Nr.</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_agency_labor_nr'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_agency_labor_nr" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                                <div>
                                    <div class="flex items-center gap-1 mb-2">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Gerichtsstand</label>
                                        @include('components.alerts.info-tooltip', ['key' => 'owner_court'])
                                    </div>
                                    <input type="text" wire:model="settings.owner_court" class="w-full rounded-lg border-gray-200 bg-gray-50 focus:bg-white focus:ring-1 focus:border-primary py-2.5 px-3 text-sm font-semibold text-gray-700 outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 bg-primary/5 p-6 rounded-2xl border border-primary/10 mt-4">
                            <div class="flex items-center gap-1 mb-3">
                                <label class="text-[11px] font-bold text-primary uppercase tracking-widest">Bankverbindung (IBAN)</label>
                                @include('components.alerts.info-tooltip', ['key' => 'owner_iban'])
                            </div>
                            <input type="text" wire:model="settings.owner_iban"
                                   class="w-full rounded-xl border-primary/20 bg-white focus:ring-4 focus:ring-primary/10 focus:border-primary py-4 px-6 font-mono tracking-[0.2em] text-lg text-primary shadow-inner outline-none"
                                   placeholder="DE00 0000 0000 0000 0000 00">
                            <p class="text-[10px] text-primary/60 mt-2 italic">Hinweis: Diese IBAN wird auf Rechnungen für Vorkasse-Zahlungen angezeigt.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB: AUTOMATISIERUNG --}}
            <div x-show="activeTab === 'scheduler'" class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-8 border-b pb-4 flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Automatisierung & System-Aufgaben
                    </h3>
                    <livewire:shop.scheduler.scheduler-manager />
                </div>
            </div>

        </div>
    </div>
</div>
