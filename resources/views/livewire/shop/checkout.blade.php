<div class="bg-gray-50 min-h-screen py-12 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8 sr-only">Checkout</h1>

        <form id="payment-form" class="lg:grid lg:grid-cols-12 lg:gap-x-12 lg:items-start xl:gap-x-16">

            {{-- LINKE SPALTE: Daten --}}
            <div class="lg:col-span-7 space-y-6">

                {{-- Gast / Login Hinweis (NUR anzeigen, wenn NICHT als Customer eingeloggt) --}}
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
                        <div x-show="showLogin" x-collapse class="mt-4 pt-4 border-t border-gray-100">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">E-Mail</label>
                                    <input type="email" wire:model="loginEmail" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                    @error('loginEmail') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Passwort</label>
                                    <input type="password" wire:model="loginPassword" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
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
                    {{-- Wenn eingeloggt, zeige Info --}}
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
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm py-3 transition-colors @error('email') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                                   placeholder="max@muster.de">
                            @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Name --}}
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">Vorname <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="first_name" id="first_name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm py-3 @error('first_name') border-red-300 @enderror">
                            @error('first_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Nachname <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="last_name" id="last_name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm py-3 @error('last_name') border-red-300 @enderror">
                            @error('last_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Firma --}}
                        <div class="sm:col-span-2">
                            <label for="company" class="block text-sm font-medium text-gray-700">Firma (Optional)</label>
                            <input type="text" wire:model.live="company" id="company" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm py-3">
                        </div>

                        {{-- Adresse --}}
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700">Straße & Hausnummer <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="address" id="address" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm py-3 @error('address') border-red-300 @enderror">
                            @error('address') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Stadt & PLZ --}}
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700">PLZ <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="postal_code" id="postal_code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm py-3 @error('postal_code') border-red-300 @enderror">
                            @error('postal_code') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">Stadt <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live="city" id="city" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm py-3 @error('city') border-red-300 @enderror">
                            @error('city') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Land --}}
                        <div class="sm:col-span-2">
                            <label for="country" class="block text-sm font-medium text-gray-700">Land <span class="text-red-500">*</span></label>
                            <select wire:model.live="country" id="country" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm py-3 bg-white">
                                <option value="DE">Deutschland</option>
                                <option value="AT">Österreich</option>
                                <option value="CH">Schweiz</option>
                            </select>
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
                            Sichere SSL-Verschlüsselung. Wähle unten deine bevorzugte Zahlungsart (Kreditkarte, PayPal, Klarna, etc.).
                        </p>
                    </div>

                    <div id="payment-element" wire:ignore>
                    </div>
                    <div id="payment-message" class="hidden mt-4 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg"></div>
                </div>

            </div>

            {{-- RECHTE SPALTE: Zusammenfassung --}}
            <div class="mt-10 lg:mt-0 lg:col-span-5">
                <div class="bg-white p-6 sm:p-8 rounded-2xl border border-gray-100 shadow-lg sticky top-24">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Bestellübersicht</h2>

                    <ul role="list" class="divide-y divide-gray-200 text-sm font-medium text-gray-900">
                        @foreach($cart->items as $item)
                            <li class="flex items-start py-6 space-x-4">
                                @if(isset($item->product->media_gallery[0]))
                                    <img src="{{ asset('storage/'.$item->product->media_gallery[0]['path']) }}" alt="{{ $item->product->name }}" class="flex-none w-20 h-20 rounded-md object-cover bg-gray-100 border border-gray-200">
                                @else
                                    <div class="flex-none w-20 h-20 rounded-md bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-300">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <div class="flex-auto space-y-1">
                                    <h3 class="text-gray-900 font-bold">{{ $item->product->name }}</h3>
                                    <p class="text-gray-500">{{ $item->quantity }}x</p>
                                    @if(isset($item->configuration['text']))
                                        <p class="text-xs text-gray-400 bg-gray-50 inline-block px-2 py-1 rounded">Gravur: {{ Str::limit($item->configuration['text'], 15) }}</p>
                                    @endif
                                </div>
                                <p class="flex-none font-bold text-gray-900">{{ number_format($item->unit_price / 100, 2, ',', '.') }} €</p>
                            </li>
                        @endforeach
                    </ul>

                    <div class="border-t border-gray-100 pt-6 space-y-3">

                        {{-- 1. ECHTER WARENWERT (Original) --}}
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Warenwert</span>
                            <span>{{ number_format($totals['subtotal_original'] / 100, 2, ',', '.') }} €</span>
                        </div>

                        {{-- 2. MENGENRABATT --}}
                        @if($totals['volume_discount'] > 0)
                            <div class="flex items-center justify-between text-sm text-green-600">
                                <div class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span>Mengenrabatt</span>
                                </div>
                                <span>-{{ number_format($totals['volume_discount'] / 100, 2, ',', '.') }} €</span>
                            </div>

                            {{-- Zwischenlinie zur Verdeutlichung --}}
                            <div class="border-b border-gray-100 my-2"></div>

                            {{-- Zwischensumme (Optional, aber hilfreich für Verständnis) --}}
                            <div class="flex items-center justify-between text-sm text-gray-500 italic">
                                <span>Zwischensumme</span>
                                <span>{{ number_format($totals['subtotal_gross'] / 100, 2, ',', '.') }} €</span>
                            </div>
                        @endif

                        {{-- 3. GUTSCHEIN --}}
                        @if($totals['discount_amount'] > 0)
                            <div class="flex items-center justify-between text-sm text-green-600 font-medium">
                                <span>Gutschein ({{ $totals['coupon_code'] }})</span>
                                <span>-{{ number_format($totals['discount_amount'] / 100, 2, ',', '.') }} €</span>
                            </div>
                        @endif

                        {{-- 4. VERSAND --}}
                        @if($totals['shipping'] > 0)
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span>Versand</span>
                                <span>{{ number_format($totals['shipping'] / 100, 2, ',', '.') }} €</span>
                            </div>
                        @else
                            <div class="flex items-center justify-between text-sm text-green-600">
                                <span>Versand</span>
                                <span>Kostenlos</span>
                            </div>
                        @endif

                        {{-- 5. ENDSUMME --}}
                        <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                            <span class="text-base font-bold text-gray-900">Gesamtsumme</span>
                            <span class="text-xl font-bold text-gray-900">{{ number_format($totals['total'] / 100, 2, ',', '.') }} €</span>
                        </div>
                        <div class="text-xs text-gray-400 text-right">
                            inkl. {{ number_format($totals['tax'] / 100, 2, ',', '.') }} € MwSt.
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
                        {{--
                            BUTTON LOGIK:
                            Deaktiviert via AlpineJS oder nativ, solange Checkboxen fehlen.
                            Zusätzlich disabled während 'wire:loading' oder JS-Processing.
                        --}}
                        <button id="submit-button" type="submit"
                                @disabled(!$terms_accepted || !$privacy_accepted)
                                class="w-full rounded-full border border-transparent bg-gray-900 py-4 px-4 text-base font-bold text-white shadow-lg shadow-gray-900/20
                                       hover:bg-black focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
                                       disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed disabled:shadow-none
                                       transition-all transform enabled:hover:-translate-y-1">
                            <span id="button-text">Zahlungspflichtig bestellen</span>
                            <div id="spinner" class="hidden flex items-center justify-center gap-2">
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Verarbeite...
                            </div>
                        </button>

                        <p class="text-xs text-gray-400 text-center mt-4">
                            Durch Klicken auf den Button schließt du einen zahlungspflichtigen Kaufvertrag ab.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- STRIPE JS SCRIPT --}}
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            const stripe = Stripe("{{ env('STRIPE_KEY') }}");
            const clientSecret = "{{ $clientSecret }}";

            // Design an "Mein Seelenfunke" CI anpassen
            const appearance = {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#C5A059',
                    colorBackground: '#ffffff',
                    colorText: '#1f2937',
                    colorDanger: '#ef4444',
                    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
                    spacingUnit: '4px',
                    borderRadius: '8px',
                },
            };

            // Payment Element (Erkennt automatisch PayPal, Apple Pay, etc. basierend auf Dashboard Einstellungen)
            const elements = stripe.elements({ appearance, clientSecret });
            const paymentElement = elements.create("payment", {
                layout: "tabs",
            });
            paymentElement.mount("#payment-element");

            const form = document.getElementById('payment-form');
            const submitButton = document.getElementById('submit-button');
            const spinner = document.getElementById('spinner');
            const buttonText = document.getElementById('button-text');
            const messageContainer = document.getElementById('payment-message');

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                // 1. Prüfen ob Button disabled ist (falls User via Konsole trickst)
                if(submitButton.disabled) return;

                setLoading(true);
                messageContainer.classList.add("hidden");

                try {
                    // 2. Livewire Validierung & Order Erstellung triggern (PHP)
                    const orderId = await @this.validateAndCreateOrder();

                    if(!orderId) {
                        setLoading(false);
                        // Livewire zeigt Validierungsfehler automatisch an den Feldern an
                        // Scrollen zum ersten Fehler wäre hier nett:
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }

                    // 3. Zahlung bei Stripe bestätigen
                    // Stripe sammelt die Daten aus dem Payment Element selbst
                    const { error } = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            // Nach Erfolg hierhin leiten
                            return_url: "{{ route('checkout.success') }}",
                            payment_method_data: {
                                billing_details: {
                                    name: @this.get('first_name') + ' ' + @this.get('last_name'),
                                    email: @this.get('email'),
                                    address: {
                                        city: @this.get('city'),
                                        country: @this.get('country'),
                                        line1: @this.get('address'),
                                        postal_code: @this.get('postal_code'),
                                    }
                                }
                            }
                        },
                    });

                    // Dieser Code wird nur ausgeführt, wenn ein Fehler auftrat (z.B. Karte abgelehnt)
                    // Bei Erfolg leitet Stripe automatisch weiter.
                    if (error) {
                        if (error.type === "card_error" || error.type === "validation_error") {
                            showMessage(error.message);
                        } else {
                            showMessage("Ein unerwarteter Fehler ist aufgetreten.");
                        }
                        setLoading(false);
                    }

                } catch (error) {
                    console.error("System Error:", error);
                    showMessage("Verbindungsfehler. Bitte versuche es erneut.");
                    setLoading(false);
                }
            });

            function setLoading(isLoading) {
                if (isLoading) {
                    submitButton.disabled = true;
                    spinner.classList.remove("hidden");
                    buttonText.classList.add("hidden");
                } else {
                    submitButton.disabled = false;
                    spinner.classList.add("hidden");
                    buttonText.classList.remove("hidden");
                }
            }

            function showMessage(messageText) {
                messageContainer.classList.remove("hidden");
                messageContainer.textContent = messageText;

                // Scroll zum Fehler
                messageContainer.scrollIntoView({ behavior: "smooth", block: "center" });
            }
        });
    </script>
</div>
