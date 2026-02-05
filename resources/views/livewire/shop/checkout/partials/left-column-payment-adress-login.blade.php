{{-- LINKE SPALTE: Daten --}}
<div class="lg:col-span-7 space-y-6">

    {{-- Gast / Login Hinweis --}}
    @if(!auth()->guard('customer')->check())
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm" x-data="{ showLogin: false }" wire:key="checkout-login-box">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Bereits Kunde?</h3>
                    <p class="text-sm text-gray-500">Melde dich an, um deine gespeicherten Adressdaten zu laden.</p>
                </div>
                <button type="button" @click="showLogin = !showLogin" class="text-sm font-bold text-primary hover:text-primary-dark whitespace-nowrap focus:outline-none">
                    <span x-text="showLogin ? 'Abbrechen' : 'Jetzt Anmelden'"></span>
                </button>
            </div>

            {{-- Inline Login Form --}}
            <div x-show="showLogin" x-collapse class="mt-4 pt-4 border-t border-gray-100" style="display: none;">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">E-Mail</label>
                        <input type="email" wire:model="loginEmail" class="block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm">
                        @error('loginEmail') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Passwort</label>
                        <input type="password" wire:model="loginPassword" class="block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm">
                        @error('loginPassword') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if($loginError)
                    <div class="mt-2 text-red-500 text-sm font-bold">{{ $loginError }}</div>
                @endif

                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="loginUser" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-black transition">
                        <span wire:loading.remove wire:target="loginUser">Anmelden & Daten laden</span>
                        <span wire:loading wire:target="loginUser">Lade...</span>
                    </button>
                </div>
            </div>
        </div>
    @else
        <div class="bg-green-50 p-4 rounded-xl border border-green-100 flex items-center gap-3 text-green-800 text-sm" wire:key="checkout-logged-in-msg">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>Angemeldet als <strong>{{ auth()->guard('customer')->user()->first_name }}</strong>. Deine Daten wurden übernommen.</span>
        </div>
    @endif

    {{-- Kontakt & Rechnung mit FIXED Auto-Collapse Logik --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"
         wire:key="checkout-address-section"
         x-data="{
            isOpen: true,

            // Hilfsfunktion: Prüft ob ein Wert existiert und nicht leer ist
            isValid(val) {
                return val && typeof val === 'string' && val.trim().length > 0;
            },

            // Hauptprüfung
            checkCompletion() {
                // Zugriff auf die Livewire-Properties
                let e = $wire.email;
                let f = $wire.first_name;
                let l = $wire.last_name;
                let a = $wire.address;
                let p = $wire.postal_code;
                let c = $wire.city;

                // Prüfen ob alle Pflichtfelder ausgefüllt sind
                if (this.isValid(e) && this.isValid(f) && this.isValid(l) && this.isValid(a) && this.isValid(p) && this.isValid(c)) {
                    this.isOpen = false; // Nur dann zuklappen
                } else {
                    this.isOpen = true; // Sonst offen lassen
                }
            }
         }"
         x-init="checkCompletion()"
         @checkout-updated.window="checkCompletion()">

        <div class="p-6 sm:p-8 flex justify-between items-center cursor-pointer hover:bg-gray-50/50 transition-colors" @click="isOpen = !isOpen">
            <h2 class="text-xl font-serif font-bold text-gray-900 flex items-center gap-2">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary text-sm font-bold">1</span>
                Rechnungsdetails
            </h2>
            <div class="flex items-center gap-3">
                {{-- Anzeige 'Vollständig' nur wenn zugeklappt (was impliziert, dass es valide ist durch die Logik oben) --}}
                <template x-if="!isOpen">
                    <div class="text-right mr-2 animate-fade-in">
                        <p class="text-[10px] uppercase font-bold text-green-600">Vollständig</p>
                        <p class="text-xs text-gray-500 font-medium" x-text="$wire.first_name + ' ' + $wire.last_name"></p>
                    </div>
                </template>
                <svg class="w-5 h-5 text-gray-400 transition-transform duration-300" :class="isOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        <div x-show="isOpen" x-collapse>
            <div class="px-6 sm:px-8 pb-8">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2 pt-2">
                    {{-- Email --}}
                    <div class="sm:col-span-2">
                        <label for="email" class="block text-sm font-medium text-gray-700">E-Mail Adresse <span class="text-red-500">*</span></label>
                        <input type="email" wire:model.live.blur="email" id="email"
                               class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 transition-colors @error('email') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                               placeholder="max@muster.de">
                        @error('email') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    {{-- Name --}}
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">Vorname <span class="text-red-500">*</span></label>
                        <input type="text" wire:model.live.blur="first_name" id="first_name" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('first_name') border-red-300 @enderror">
                        @error('first_name') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Nachname <span class="text-red-500">*</span></label>
                        <input type="text" wire:model.live.blur="last_name" id="last_name" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('last_name') border-red-300 @enderror">
                        @error('last_name') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    {{-- Firma --}}
                    <div class="sm:col-span-2">
                        <label for="company" class="block text-sm font-medium text-gray-700">Firma (Optional)</label>
                        <input type="text" wire:model.live.blur="company" id="company" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3">
                    </div>

                    {{-- Adresse --}}
                    <div class="sm:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700">Straße & Hausnummer <span class="text-red-500">*</span></label>
                        <input type="text" wire:model.live.blur="address" id="address" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('address') border-red-300 @enderror">
                        @error('address') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    {{-- Stadt & PLZ --}}
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700">PLZ <span class="text-red-500">*</span></label>
                        <input type="text" wire:model.live.blur="postal_code" id="postal_code" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('postal_code') border-red-300 @enderror">
                        @error('postal_code') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700">Stadt <span class="text-red-500">*</span></label>
                        <input type="text" wire:model.live.blur="city" id="city" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('city') border-red-300 @enderror">
                        @error('city') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    {{-- Land --}}
                    <div class="sm:col-span-2">
                        <label for="country" class="block text-sm font-medium text-gray-700">Land <span class="text-red-500">*</span></label>
                        <select wire:model.live="country" id="country" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3">
                            @foreach(shop_setting('active_countries', ['DE' => 'Deutschland']) as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>

                        <div class="mt-2 text-xs text-gray-500 animate-fade-in">
                            @if($country === 'DE')
                                <span class="text-green-600 font-bold">Tipp:</span> Versandkostenfrei ab {{ number_format(shop_setting('shipping_free_threshold', 5000) / 100, 2, ',', '.') }} € Warenwert. Sonst {{ number_format(shop_setting('shipping_flat_de', 490) / 100, 2, ',', '.') }} €.
                            @else
                                <span class="text-blue-600 font-bold">Hinweis:</span> Internationale Versandkosten werden nach Gewicht & Zone berechnet.
                            @endif
                        </div>

                        @error('country') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- ERWEITERUNG ABWEICHENDE LIEFERADRESSE --}}
                <div class="mt-8 pt-6 border-t border-gray-100">
                    <div class="flex items-center gap-3 bg-gray-50 p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-100 transition-colors" x-on:click="$wire.set('has_separate_shipping', !@js($has_separate_shipping))">
                        <input type="checkbox" wire:model.live="has_separate_shipping" class="h-5 w-5 text-primary rounded border-gray-300 focus:ring-primary cursor-pointer">
                        <span class="text-sm font-bold text-gray-700">Lieferadresse weicht von Rechnungsadresse ab</span>
                    </div>

                    <div x-show="$wire.has_separate_shipping" x-collapse style="display: none;">
                        <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2 bg-white p-6 rounded-2xl border border-primary/20 shadow-sm">
                            <h3 class="sm:col-span-2 text-md font-bold text-primary flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                Wohin dürfen wir liefern?
                            </h3>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Vorname <span class="text-red-500">*</span></label>
                                <input type="text" wire:model.live.blur="shipping_first_name" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 sm:text-sm py-3 focus:bg-white focus:ring-primary focus:border-primary transition-colors @error('shipping_first_name') border-red-300 @enderror">
                                @error('shipping_first_name') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nachname <span class="text-red-500">*</span></label>
                                <input type="text" wire:model.live.blur="shipping_last_name" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 sm:text-sm py-3 focus:bg-white focus:ring-primary focus:border-primary transition-colors @error('shipping_last_name') border-red-300 @enderror">
                                @error('shipping_last_name') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Firma (Optional)</label>
                                <input type="text" wire:model.live.blur="shipping_company" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 sm:text-sm py-3 focus:bg-white focus:ring-primary focus:border-primary transition-colors">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Straße & Hausnummer <span class="text-red-500">*</span></label>
                                <input type="text" wire:model.live.blur="shipping_address" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 sm:text-sm py-3 focus:bg-white focus:ring-primary focus:border-primary transition-colors @error('shipping_address') border-red-300 @enderror">
                                @error('shipping_address') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">PLZ <span class="text-red-500">*</span></label>
                                <input type="text" wire:model.live.blur="shipping_postal_code" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 sm:text-sm py-3 focus:bg-white focus:ring-primary focus:border-primary transition-colors @error('shipping_postal_code') border-red-300 @enderror">
                                @error('shipping_postal_code') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Stadt <span class="text-red-500">*</span></label>
                                <input type="text" wire:model.live.blur="shipping_city" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 sm:text-sm py-3 focus:bg-white focus:ring-primary focus:border-primary transition-colors @error('shipping_city') border-red-300 @enderror">
                                @error('shipping_city') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Land <span class="text-red-500">*</span></label>
                                <select wire:model.live="shipping_country" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 sm:text-sm py-3 focus:bg-white focus:ring-primary focus:border-primary cursor-pointer @error('shipping_country') border-red-300 @enderror">
                                    @foreach(shop_setting('active_countries', ['DE' => 'Deutschland']) as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('shipping_country') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                {{-- ENDE: ERWEITERUNG ABWEICHENDE LIEFERADRESSE --}}
            </div>
        </div>
    </div>

    {{-- Zahlung (Stripe Element) --}}
    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-sm" wire:ignore wire:key="stripe-payment-container">
        <h2 class="text-xl font-serif font-bold text-gray-900 mb-6 flex items-center gap-2">
            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary text-sm font-bold">2</span>
            Zahlungsmethode
        </h2>

        <div class="mb-4 text-sm text-gray-500 bg-gray-50 p-4 rounded-lg border border-gray-200">
            <p class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Sichere SSL-Verschlüsselung. Wähle unten deine bevorzugte Zahlungsart.
            </p>
        </div>

        <div id="payment-element">
            {{-- Stripe injiziert hier das Iframe --}}
        </div>

        <div id="payment-message" class="hidden mt-4 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg"></div>
    </div>
</div>
