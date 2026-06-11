<div class="bg-white min-h-screen text-gray-800">
    {{-- Header / Hero Section (Analog zur Kollektion/Blog) --}}
    <div class="bg-gray-50 border-b border-gray-100 py-16 sm:py-24 text-center">
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4 tracking-wide">
            Geschenkgutscheine
        </h1>
        <p class="text-gray-500 max-w-2xl mx-auto text-base sm:text-lg leading-relaxed px-4">
            Verschenke magische Momente voller Emotionen und Liebe. <br class="hidden sm:inline">
            Edel gestaltet und flexibel einlösbar für all unsere handgefertigten Produkte.
        </p>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 xl:gap-16 items-start">

            {{-- INTERAKTIVE LIVE-VORSCHAU (Links, 5 Columns) --}}
            <div class="lg:col-span-5 space-y-8 lg:sticky lg:top-28">
                <div class="bg-white border border-gray-200/80 rounded-3xl p-5 sm:p-8 shadow-sm relative overflow-hidden group">
                    {{-- Deko Hintergrundlicht --}}
                    <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all duration-700"></div>

                    <h3 class="text-lg font-serif font-bold text-gray-800 mb-6 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 bg-primary rounded-full animate-pulse"></span>
                        Live-Vorschau deines Gutscheins
                    </h3>

                    {{-- Die Gutschein-Karte selbst (Bleibt dunkel-luxuriös für Kontrast und Wertigkeit) --}}
                    <div class="w-full aspect-[1.586/1] bg-gradient-to-br from-gray-900 to-gray-800 border-2 border-primary/30 rounded-2xl p-6 relative flex flex-col justify-between shadow-xl overflow-hidden min-h-[260px]">
                        {{-- Goldene Eckenverzierungen --}}
                        <div class="absolute top-2 left-2 w-4 h-4 border-t-2 border-l-2 border-primary/50"></div>
                        <div class="absolute top-2 right-2 w-4 h-4 border-t-2 border-r-2 border-primary/50"></div>
                        <div class="absolute bottom-2 left-2 w-4 h-4 border-b-2 border-l-2 border-primary/50"></div>
                        <div class="absolute bottom-2 right-2 w-4 h-4 border-b-2 border-r-2 border-primary/50"></div>

                        {{-- Logo im Hintergrund --}}
                        <div class="absolute inset-0 flex items-center justify-center opacity-5 pointer-events-none select-none">
                            <img src="{{ URL::to('/shop/projekt/logo/mein-seelenfunke-logo.svg') }}" class="w-64 h-auto">
                        </div>

                        {{-- Header --}}
                        <div class="flex justify-between items-start z-10">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-primary/80 font-semibold">Geschenkgutschein</p>
                                <p class="text-[10px] text-gray-400 tracking-wider font-mono">SEELENFUNKE-XXXX-XXXX</p>
                            </div>
                            <div class="text-right">
                                <span class="text-3xl sm:text-4xl font-serif font-bold text-primary">
                                    {{ number_format($this->selectedAmount, 2, ',', '.') }} €
                                </span>
                            </div>
                        </div>

                        {{-- Body (Nachricht & Empfänger) --}}
                        <div class="my-4 z-10">
                            @if($recipientName)
                                <p class="text-xs text-gray-400 mb-1">Für:</p>
                                <p class="text-lg font-serif font-bold text-white tracking-wide truncate mb-2">{{ $recipientName }}</p>
                            @else
                                <p class="text-xs text-gray-500 italic mb-3">Empfänger eintragen...</p>
                            @endif

                            @if($personalMessage)
                                <p class="text-xs text-gray-400 mb-1">Deine Nachricht:</p>
                                <p class="text-sm font-sans italic text-gray-300 line-clamp-3 leading-relaxed">„{{ $personalMessage }}“</p>
                            @else
                                <p class="text-xs text-gray-500 italic">Persönliche Nachricht hinzufügen...</p>
                            @endif
                        </div>

                        {{-- Footer --}}
                        <div class="flex justify-between items-end border-t border-gray-800/80 pt-3 z-10">
                            <p class="text-[9px] text-gray-400">Einlösbar auf www.mein-seelenfunke.de</p>
                            <p class="text-[9px] text-primary/80 font-bold uppercase tracking-wider">Mein Seelenfunke</p>
                        </div>
                    </div>

                    {{-- Hinweis zur Versandart --}}
                    <div class="mt-6 p-4 rounded-xl bg-gray-50 border border-gray-200/80 text-xs text-gray-600 flex items-start gap-3">
                        @if($deliveryMethod === 'email')
                            <svg class="w-5 h-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <div>
                                <strong class="text-gray-800">Digitaler Versand (E-Mail):</strong>
                                <p class="mt-0.5">Der Gutschein wird sofort nach erfolgreicher Zahlung als hochauflösendes PDF an {{ $recipientEmail ?: 'die angegebene E-Mail-Adresse' }} versendet. Perfekt zum Selbstdrucken oder Weiterleiten.</p>
                            </div>
                        @else
                            <svg class="w-5 h-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm-2 4h12M5 15h14" /></svg>
                            <div>
                                <strong class="text-gray-800">Postalischer Versand (+ {{ number_format($this->shippingCost, 2, ',', '.') }} €):</strong>
                                <p class="mt-0.5">Wir drucken deinen Gutschein auf einer Klappkarte und versenden ihn in dem edlen schwarzen Umschlag mit goldenem Siegel-Aufkleber an deine Lieferadresse.</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Gutschein-Wert-Prüfer --}}
                @livewire('shop.marketing.marketing-voucher-balance-checker')

                {{-- Vertrauenselemente / Conversion-Booster --}}
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="p-3 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-xs text-primary font-bold font-serif mb-1">100% Sicher</p>
                        <p class="text-[10px] text-gray-500">Stripe SSL Zahlung</p>
                    </div>
                    <div class="p-3 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <p class="text-xs text-primary font-bold font-serif mb-1">Flexibel</p>
                        <p class="text-[10px] text-gray-500">Teileinlösung möglich</p>
                    </div>
                    <a href="{{ route('agb') }}#gutscheine-verjaehrung" target="_blank" rel="noopener" class="p-3 bg-white border border-gray-200 hover:border-primary/50 transition-colors rounded-2xl shadow-sm block">
                        <p class="text-xs text-primary font-bold font-serif mb-1">3 Jahre Gültig</p>
                        <p class="text-[10px] text-gray-500 underline decoration-gray-300">BGB Verjährungsfrist</p>
                    </a>
                </div>
            </div>

            {{-- CONFIGURATOR FORMULAR (Rechts, 7 Columns) --}}
            <div class="lg:col-span-7 space-y-8">

                {{-- Produktdetails Header --}}
                <div>
                    <span class="inline-block px-3 py-1 bg-primary/10 border border-primary/20 rounded-full text-xs font-semibold tracking-wider text-primary mb-3 uppercase">Exklusives Geschenk</span>
                    <h2 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 tracking-wide mb-3">Deinen Gutschein konfigurieren</h2>
                    <p class="text-gray-600 text-sm sm:text-base leading-relaxed">
                        Passe deinen Gutschein individuell an. Wähle den gewünschten Betrag, trage den Empfänger und deine persönliche Botschaft ein, und wähle die gewünschte Zustellungsart.
                    </p>
                </div>

                {{-- Betragsauswahl --}}
                <div class="bg-white border border-gray-200/80 rounded-3xl p-5 sm:p-8 shadow-sm">
                    <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="text-primary">01.</span>
                        Wähle den Gutscheinwert
                    </h3>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                        @foreach($presets as $p)
                            <button type="button"
                                    wire:click="setAmount({{ $p }})"
                                    class="py-4 px-3 sm:px-6 rounded-2xl border-2 font-serif font-bold text-lg tracking-wide transition-all duration-300 {{ $amount === $p ? 'border-primary bg-primary/10 text-primary shadow-sm' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">
                                {{ $p }} €
                            </button>
                        @endforeach
                    </div>

                    {{-- Wunschbetrag Button & Input --}}
                    <div>
                        <button type="button"
                                wire:click="setAmount('custom')"
                                class="w-full py-3 px-3 sm:px-6 rounded-2xl border-2 font-serif font-bold text-sm tracking-wider transition-all duration-300 mb-4 {{ $amount === 'custom' ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">
                            ✨ Anderen Wunschbetrag wählen
                        </button>

                        @if($amount === 'custom')
                            <div class="relative animate-fade-in">
                                <input type="number"
                                       wire:model.live="customAmount"
                                       placeholder="Wunschbetrag eingeben (z. B. 75)"
                                       class="w-full bg-white border @error('customAmount') border-red-500 focus:border-red-500 focus:ring-red-500/20 @else border-gray-300 focus:border-primary focus:ring-primary/20 @enderror rounded-2xl py-4 pl-6 pr-12 text-lg font-serif font-bold text-primary placeholder-gray-400 focus:outline-none focus:ring-1">
                                <span class="absolute right-6 top-1/2 -translate-y-1/2 text-gray-500 font-serif font-bold text-lg">€</span>
                            </div>
                            <span class="text-[10px] text-gray-400 mt-1 block ml-2">Erlaubt sind Beträge von 5 € bis 1000 € in vollen 5er Schritten (z.B. 15 €, 50 €, 125 €).</span>
                            @error('customAmount') <span class="text-red-600 text-xs mt-2 block ml-2">⚠️ {{ $message }}</span> @enderror
                        @endif
                    </div>
                </div>

                {{-- Empfänger & Nachricht --}}
                <div class="bg-white border border-gray-200/80 rounded-3xl p-5 sm:p-8 shadow-sm">
                    <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="text-primary">02.</span>
                        Personalisierung
                    </h3>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-gray-600 font-semibold mb-2 ml-1">Für wen ist der Gutschein? (Empfänger-Name)</label>
                            <input type="text"
                                   wire:model.live="recipientName"
                                   placeholder="z. B. Alina Steinhauer"
                                   class="w-full bg-white border border-gray-300 rounded-2xl py-3.5 px-5 text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                            @error('recipientName') <span class="text-red-600 text-xs mt-2 block ml-1">⚠️ {{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs uppercase tracking-wider text-gray-600 font-semibold mb-2 ml-1">Deine persönliche Grußbotschaft (Optional)</label>
                            <textarea wire:model.live="personalMessage"
                                      rows="3"
                                      maxlength="160"
                                      placeholder="Schreibe eine liebevolle Widmung..."
                                      class="w-full bg-white border border-gray-300 rounded-2xl py-3.5 px-5 text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 resize-none"></textarea>
                            <div class="flex justify-between items-center mt-1.5 px-1">
                                <span class="text-[10px] text-gray-500">Maximal 160 Zeichen</span>
                                <span class="text-[10px] {{ strlen($personalMessage) > 160 ? 'text-red-600 font-bold' : 'text-gray-500' }}">{{ strlen($personalMessage) }}/160</span>
                            </div>
                            @error('personalMessage') <span class="text-red-600 text-xs mt-1 block">⚠️ {{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Versandart & Adresse --}}
                <div class="bg-white border border-gray-200/80 rounded-3xl p-5 sm:p-8 shadow-sm">
                    <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="text-primary">03.</span>
                        Versandmethode
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <button type="button"
                                wire:click="$set('deliveryMethod', 'email')"
                                class="p-5 rounded-2xl border-2 text-left transition-all duration-300 flex flex-col justify-between {{ $deliveryMethod === 'email' ? 'border-primary bg-primary/10' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                            <div class="flex items-center justify-between w-full mb-3">
                                <span class="text-sm font-bold {{ $deliveryMethod === 'email' ? 'text-primary' : 'text-gray-800' }}">📧 Per E-Mail</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/10 text-green-700 border border-green-500/20 font-bold">Gratis</span>
                            </div>
                            <span class="text-xs text-gray-500 leading-relaxed">Sofortige Zustellung nach Zahlung per PDF-Anhang. Ideal für Last-Minute-Geschenke.</span>
                        </button>

                        <button type="button"
                                wire:click="$set('deliveryMethod', 'post')"
                                class="p-5 rounded-2xl border-2 text-left transition-all duration-300 flex flex-col justify-between {{ $deliveryMethod === 'post' ? 'border-primary bg-primary/10' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                            <div class="flex items-center justify-between w-full mb-3">
                                <span class="text-sm font-bold {{ $deliveryMethod === 'post' ? 'text-primary' : 'text-gray-800' }}">✉️ Per Post</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20 font-bold">+ {{ number_format($this->postShippingCost, 2, ',', '.') }} €</span>
                            </div>
                            <span class="text-xs text-gray-500 leading-relaxed">Gedruckte Klappkarte im schwarzen Umschlag mit goldenem Siegel-Aufkleber.</span>
                        </button>
                    </div>

                    @if($deliveryMethod === 'email')
                        <div class="animate-fade-in">
                            <label class="block text-xs uppercase tracking-wider text-gray-600 font-semibold mb-2 ml-1">E-Mail-Adresse des Empfängers</label>
                            <input type="email"
                                   wire:model.live="recipientEmail"
                                   placeholder="z. B. empfaenger@example.com"
                                   class="w-full bg-white border border-gray-300 rounded-2xl py-3.5 px-5 text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                            <span class="text-[10px] text-gray-500 mt-1.5 block ml-1">Hierhin wird der Gutschein digital nach Abschluss des Kaufs versendet.</span>
                            @error('recipientEmail') <span class="text-red-600 text-xs mt-2 block ml-1">⚠️ {{ $message }}</span> @enderror
                        </div>
                    @else
                        <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 text-xs text-gray-600 animate-fade-in space-y-4">
                            <div>
                                <span class="font-bold text-gray-800 block mb-1">📍 Wichtiger Hinweis zur Post-Zustellung:</span>
                                Die Lieferadresse für den Post-Gutschein gibst du ganz bequem im nächsten Schritt direkt während des Checkouts an. Falls du noch andere physische Artikel bestellst, senden wir alles zusammen in einem liebevoll verpackten Paket.
                            </div>
                            
                            <div class="border-t border-gray-200/80 pt-3 space-y-2">
                                <span class="font-bold text-gray-800 block">Deine Vorteile beim Postversand:</span>
                                <ul class="space-y-1.5 pl-0.5">
                                    <li class="flex items-center gap-2 text-gray-700">
                                        <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>Edler Umschlag mit goldenen Linien</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-gray-700">
                                        <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>Goldener Siegel-Aufkleber</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-gray-700">
                                        <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        <span>Hochwertiges Design</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Preisübersicht & Kaufen Button --}}
                <div class="bg-gray-50 border border-gray-200 rounded-3xl p-5 sm:p-8 flex flex-col sm:flex-row justify-between items-center gap-6">
                    <div class="text-center sm:text-left">
                        <p class="text-xs uppercase tracking-wider text-gray-500 font-semibold mb-1">Gesamtsumme</p>
                        <div class="flex items-baseline gap-2 justify-center sm:justify-start">
                            <span class="text-3xl sm:text-4xl font-serif font-bold text-gray-900">{{ number_format($this->finalTotal, 2, ',', '.') }} €</span>
                            @if($this->shippingCost > 0)
                                <span class="text-xs text-gray-500">(inkl. Versand)</span>
                            @endif
                        </div>
                    </div>

                    <button type="button"
                            wire:click="addToCart"
                            class="w-full sm:w-auto px-8 py-4 bg-primary text-white font-serif font-bold text-lg rounded-2xl hover:bg-primary/90 hover:scale-105 active:scale-95 transition-all duration-300 shadow-md shadow-primary/10 flex items-center justify-center gap-3">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        In den Warenkorb legen
                    </button>
                </div>

                @if(session()->has('error'))
                    <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm">
                        {{ session('error') }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
