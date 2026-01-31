<div class="bg-gray-50 min-h-screen py-12 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8 sr-only">Checkout</h1>

        <form id="payment-form" class="lg:grid lg:grid-cols-12 lg:gap-x-12 xl:gap-x-16">

            {{-- LINKE SPALTE: Daten --}}
            <div class="lg:col-span-7 space-y-6">

                {{-- Gast / Login Hinweis --}}
                @if(!auth()->guard('customer')->check())
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm" x-data="{ showLogin: false }">
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
                    <div class="bg-green-50 p-4 rounded-xl border border-green-100 flex items-center gap-3 text-green-800 text-sm">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Angemeldet als <strong>{{ auth()->guard('customer')->user()->first_name }}</strong>. Deine Daten wurden übernommen.</span>
                    </div>
                @endif

                {{-- Kontakt & Rechnung --}}
                <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-sm">
                    <h2 class="text-xl font-serif font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary text-sm font-bold">1</span>
                        Rechnungsdetails
                    </h2>

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">

                        {{-- Email --}}
                        <div class="sm:col-span-2">
                            <label for="email" class="block text-sm font-medium text-gray-700">E-Mail Adresse <span class="text-red-500">*</span></label>
                            <input type="email" wire:model.live="email" id="email"
                                   class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 transition-colors @error('email') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                                   placeholder="max@muster.de">
                            @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Name --}}
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">Vorname <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="first_name" id="first_name" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('first_name') border-red-300 @enderror">
                            @error('first_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Nachname <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="last_name" id="last_name" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('last_name') border-red-300 @enderror">
                            @error('last_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Firma --}}
                        <div class="sm:col-span-2">
                            <label for="company" class="block text-sm font-medium text-gray-700">Firma (Optional)</label>
                            <input type="text" wire:model.live="company" id="company" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3">
                        </div>

                        {{-- Adresse --}}
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700">Straße & Hausnummer <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="address" id="address" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('address') border-red-300 @enderror">
                            @error('address') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Stadt & PLZ --}}
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700">PLZ <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="postal_code" id="postal_code" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('postal_code') border-red-300 @enderror">
                            @error('postal_code') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">Stadt <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="city" id="city" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3 @error('city') border-red-300 @enderror">
                            @error('city') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Land --}}
                        <div class="sm:col-span-2">
                            <label for="country" class="block text-sm font-medium text-gray-700">Land <span class="text-red-500">*</span></label>
                            {{-- wire:model.live sorgt für sofortige Aktualisierung bei Änderung --}}
                            <select wire:model.live="country" id="country" class="mt-1 block w-full rounded-lg bg-gray-50 border-gray-300 shadow-sm focus:bg-white focus:border-primary focus:ring-primary sm:text-sm py-3">
                                @foreach(config('shop.countries', ['DE' => 'Deutschland']) as $code => $name)
                                    {{-- 'default_tax_rate' ist kein Land, daher rausfiltern --}}
                                    @if($code !== 'default_tax_rate')
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endif
                                @endforeach
                            </select>

                            {{-- Dynamischer Hinweis --}}
                            <div class="mt-2 text-xs text-gray-500 animate-fade-in">
                                @if($country === 'DE')
                                    <span class="text-green-600 font-bold">Tipp:</span> Versandkostenfrei ab 50,00 € Warenwert. Sonst 4,90 €.
                                @else
                                    <span class="text-blue-600 font-bold">Hinweis:</span> Internationale Versandkosten werden nach Gewicht & Zone berechnet.
                                @endif
                            </div>

                            @error('country') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Zahlung (Stripe Element) --}}
                <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-sm">
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

                    {{-- wire:ignore verhindert, dass Livewire das Stripe Element beim Rerender zerstört --}}
                    <div wire:ignore>
                        <div id="payment-element"></div>
                    </div>

                    <div id="payment-message" class="hidden mt-4 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg"></div>
                </div>
            </div>

            {{-- RECHTE SPALTE: Zusammenfassung --}}
            <div class="mt-10 lg:mt-0 lg:col-span-5 h-full">
                <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-lg sticky top-24">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Bestellübersicht</h2>

                    {{--
                        LOGIK: Progressbar NUR für Deutschland (50€ Regel).
                        Fürs Ausland nur einen Hinweis.
                    --}}
                    @php
                        $threshold = 5000; // 50,00 Euro
                        $currentValue = $totals['subtotal_gross'];
                        $percent = $threshold > 0 ? min(100, ($currentValue / $threshold) * 100) : 100;
                        $missing = $totals['missing_for_free_shipping'];
                        $isFree = $totals['is_free_shipping'];
                    @endphp

                    @if($country === 'DE')
                        <div class="mb-6 bg-gray-50 p-4 rounded-xl border border-gray-200">
                            @if($isFree)
                                <div class="flex items-center gap-2 text-green-600 font-bold text-sm">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Glückwunsch! Deine Bestellung ist versandkostenfrei.
                                </div>
                            @else
                                <p class="text-sm text-gray-700 font-medium mb-2">
                                    Noch <span class="text-primary font-bold">{{ number_format($missing / 100, 2, ',', '.') }} €</span> bis zum <span class="text-green-600 font-bold">kostenlosen Versand!</span>
                                </p>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-primary h-2.5 rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Hinweis für Ausland --}}
                        <div class="mb-6 bg-blue-50 p-4 rounded-xl border border-blue-100 text-blue-800 text-sm flex gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Für Lieferungen nach <strong>{{ config('shop.countries.'.$country, $country) }}</strong> gelten gesonderte Versandkosten.</span>
                        </div>
                    @endif

                    {{-- Warenkorb Items --}}
                    <ul role="list" class="divide-y divide-gray-200 text-sm font-medium text-gray-900">
                        @foreach($cart->items as $item)
                            <li class="flex items-start py-6 space-x-4">
                                @if(isset($item->product->preview_image_path))
                                    <img src="{{ Storage::url($item->product->preview_image_path) }}" alt="{{ $item->product->name }}" class="flex-none w-20 h-20 rounded-md object-cover bg-gray-100 border border-gray-200">
                                @elseif(isset($item->product->media_gallery[0]))
                                    <img src="{{ Storage::url($item->product->media_gallery[0]['path']) }}" alt="{{ $item->product->name }}" class="flex-none w-20 h-20 rounded-md object-cover bg-gray-100 border border-gray-200">
                                @else
                                    <div class="flex-none w-20 h-20 rounded-md bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-300">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <div class="flex-auto space-y-1">
                                    <h3 class="text-gray-900 font-bold">{{ $item->product->name }}</h3>
                                    <p class="text-gray-500">{{ $item->quantity }}x</p>

                                    {{-- Konfigurations-Details --}}
                                    @if(!empty($item->configuration) && is_array($item->configuration))
                                        <div class="text-xs text-gray-500 space-y-1">
                                            @if(isset($item->configuration['texts']))
                                                @foreach($item->configuration['texts'] as $t)
                                                    <div class="bg-gray-50 inline-block px-1.5 py-0.5 rounded border border-gray-100">
                                                        "{{ Str::limit($t['text'], 20) }}"
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-gray-900">{{ number_format($item->unit_price / 100, 2, ',', '.') }} €</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Summenblock --}}
                    <div class="border-t border-gray-100 pt-6 space-y-3">
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Zwischensumme</span>
                            <span>{{ number_format($totals['subtotal_gross'] / 100, 2, ',', '.') }} €</span>
                        </div>

                        {{-- Rabatte --}}
                        @if($totals['volume_discount'] > 0)
                            <div class="flex items-center justify-between text-sm text-green-600">
                                <span>Mengenrabatt</span>
                                <span>-{{ number_format($totals['volume_discount'] / 100, 2, ',', '.') }} €</span>
                            </div>
                        @endif

                        @if($totals['discount_amount'] > 0)
                            <div class="flex items-center justify-between text-sm text-green-600">
                                <span>Gutschein</span>
                                <span>-{{ number_format($totals['discount_amount'] / 100, 2, ',', '.') }} €</span>
                            </div>
                        @endif

                        {{-- Versandkosten --}}
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Versand ({{ $country }})</span>
                            @if($totals['shipping'] > 0)
                                <span class="font-medium text-gray-900">{{ number_format($totals['shipping'] / 100, 2, ',', '.') }} €</span>
                            @else
                                <span class="font-bold text-green-600">Kostenlos</span>
                            @endif
                        </div>

                        <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                            <span class="text-base font-bold text-gray-900">Gesamtsumme</span>
                            <span class="text-xl font-bold text-primary">{{ number_format($totals['total'] / 100, 2, ',', '.') }} €</span>
                        </div>

                        {{-- STEUERANZEIGE (FIX: Dynamische Schleife) --}}
                        <div class="space-y-1 pt-2 border-t border-dashed border-gray-200 mt-2">
                            @if(isset($totals['taxes_breakdown']) && count($totals['taxes_breakdown']) > 0)
                                @foreach($totals['taxes_breakdown'] as $taxRate => $taxAmount)
                                    <div class="flex justify-between text-xs text-gray-400">
                                        <span>Enthaltene MwSt. ({{ $taxRate }}%)</span>
                                        <span>{{ number_format($taxAmount / 100, 2, ',', '.') }} €</span>
                                    </div>
                                @endforeach
                            @else
                                {{-- Fallback --}}
                                <div class="text-xs text-gray-400 text-right">
                                    inkl. {{ number_format($totals['tax'] / 100, 2, ',', '.') }} € MwSt.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Rechtliches (Checkboxen) --}}
                    <div class="mt-8 space-y-4 bg-gray-50 p-4 rounded-xl">
                        <div class="flex items-start">
                            <div class="flex h-5 items-center">
                                <input id="terms" wire:model.live="terms_accepted" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="terms" class="font-medium text-gray-700 cursor-pointer select-none">
                                    Ich habe die <a href="/agb" target="_blank" class="text-primary underline hover:text-primary-dark">AGB</a> gelesen und akzeptiere diese. <span class="text-red-500">*</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex h-5 items-center">
                                <input id="privacy" wire:model.live="privacy_accepted" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="privacy" class="font-medium text-gray-700 cursor-pointer select-none">
                                    Ich habe die <a href="/datenschutz" target="_blank" class="text-primary underline hover:text-primary-dark">Datenschutzerklärung</a> zur Kenntnis genommen. <span class="text-red-500">*</span>
                                </label>
                            </div>
                        </div>
                        @if($errors->has('terms_accepted') || $errors->has('privacy_accepted'))
                            <div class="text-red-500 text-xs mt-2 font-bold">Bitte stimme den rechtlichen Bedingungen zu.</div>
                        @endif
                    </div>

                    <div class="mt-8">
                        <button id="submit-button" type="submit" @disabled(!$terms_accepted || !$privacy_accepted) class="w-full rounded-full border border-transparent bg-gray-900 py-4 px-4 text-base font-bold text-white shadow-lg shadow-gray-900/20 hover:bg-black focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed disabled:shadow-none transition-all transform enabled:hover:-translate-y-1">
                            <span id="button-text">Zahlungspflichtig bestellen</span>
                            <div id="spinner" class="hidden flex items-center justify-center gap-2">
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Verarbeite...
                            </div>
                        </button>
                        <p class="text-xs text-gray-400 text-center mt-4">Durch Klicken auf den Button schließt du einen zahlungspflichtigen Kaufvertrag ab.</p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- STRIPE JS --}}
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            let stripe, elements, paymentElement;
            const stripeKey = "{{ $stripeKey }}";

            // Funktion zum Initialisieren (oder neu laden bei Preisänderung)
            async function initializeStripe() {
                // Wir holen das neueste ClientSecret direkt aus der Komponente
                // Das ist wichtig, weil sich der Betrag (Versand) und damit das Secret bei Länderwechsel ändert.
                const clientSecret = await @this.get('clientSecret');

                if (!stripeKey || !clientSecret) {
                    console.error("Stripe Konfiguration fehlt.");
                    return;
                }

                // Initialisierung
                stripe = Stripe(stripeKey);
                const appearance = { theme: 'stripe', variables: { colorPrimary: '#C5A059', borderRadius: '8px' } };

                // Bestehendes Element entfernen falls vorhanden (Cleanup)
                const container = document.getElementById("payment-element");
                container.innerHTML = '';

                elements = stripe.elements({ appearance, clientSecret });
                paymentElement = elements.create("payment", { layout: "tabs" });
                paymentElement.mount("#payment-element");
            }

            // Start Initialisierung
            initializeStripe();

            // Listener für Änderungen im Backend (z.B. Land geändert -> neuer Preis -> neues Secret)
            // Wir nutzen den Hook 'commit', um nach jedem Livewire Update zu prüfen
            Livewire.hook('commit', ({ component, succeed }) => {
                succeed(() => {
                    // Hier könnte man optimieren und nur neu laden wenn sich clientSecret geändert hat.
                    // Da createPaymentIntent im Backend bei jedem Country-Change läuft, ist ein Re-Mount sicher.
                    // Optional: Prüfen ob wir im Checkout Component sind
                    if(component.name === 'shop.checkout') {
                        // initializeStripe(); // Falls Stripe bei Updates zickt, hier einkommentieren.
                        // Normalerweise reicht einmaliges Laden, solange der Intent ID gleich bleibt.
                        // Bei neuer Intent ID muss neu geladen werden.
                    }
                })
            });

            // Alternativ: Expliziter Event Listener vom Backend (sauberer)
            Livewire.on('checkout-updated', () => {
                initializeStripe();
            });


            const form = document.getElementById('payment-form');
            const submitButton = document.getElementById('submit-button');
            const spinner = document.getElementById('spinner');
            const buttonText = document.getElementById('button-text');
            const messageContainer = document.getElementById('payment-message');

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if(submitButton.disabled) return;
                setLoading(true);
                messageContainer.classList.add("hidden");

                try {
                    // 1. Validierung und Order-Erstellung im Backend
                    const orderId = await @this.validateAndCreateOrder();

                    if(!orderId) {
                        setLoading(false);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }

                    // 2. Stripe Bestätigung
                    const { error } = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: "{{ route('checkout.success') }}",
                            payment_method_data: {
                                billing_details: {
                                    name: @this.get('first_name') + ' ' + @this.get('last_name'),
                                    email: @this.get('email'),
                                    address: {
                                        city: @this.get('city'),
                                        country: @this.get('country'),
                                        line1: @this.get('address'),
                                        postal_code: @this.get('postal_code')
                                    }
                                }
                            }
                        },
                    });

                    if (error) {
                        showMessage(error.type === "card_error" || error.type === "validation_error" ? error.message : "Ein unerwarteter Fehler ist aufgetreten.");
                        setLoading(false);
                    }
                } catch (error) {
                    console.error("System Error:", error);
                    showMessage("Verbindungsfehler. Bitte versuche es erneut.");
                    setLoading(false);
                }
            });

            function setLoading(isLoading) {
                if (isLoading) { submitButton.disabled = true; spinner.classList.remove("hidden"); buttonText.classList.add("hidden"); }
                else { submitButton.disabled = false; spinner.classList.add("hidden"); buttonText.classList.remove("hidden"); }
            }
            function showMessage(text) { messageContainer.classList.remove("hidden"); messageContainer.textContent = text; messageContainer.scrollIntoView({ behavior: "smooth", block: "center" }); }
        });
    </script>
</div>
