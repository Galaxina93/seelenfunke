<div class="bg-white min-h-screen text-gray-800" style="--theme-color: #C9A66B; --theme-color-10: #C9A66B1A; --theme-color-20: #C9A66B33; --theme-color-30: #C9A66B4D; --theme-color-50: #C9A66B80;">
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

                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-serif font-bold text-gray-800 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 bg-primary rounded-full animate-pulse"></span>
                            Live-Vorschau deines Gutscheins
                        </h3>
                        <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-primary/10 text-primary">
                            Schritt {{ $currentStep }}/3
                        </span>
                    </div>

                    {{-- Die Gutschein-Karte selbst (In edlem, hellem Creme-/Champagner-Farbton) --}}
                    <div class="w-full aspect-[1.586/1] bg-gradient-to-br from-[#FAF7F2] via-[#FDFBF7] to-[#F3ECE0] border-2 border-primary/35 rounded-2xl p-6 relative flex flex-col justify-between shadow-md overflow-hidden min-h-[260px]">
                        {{-- Goldene Eckenverzierungen --}}
                        <div class="absolute top-2.5 left-2.5 w-4 h-4 border-t-2 border-l-2 border-primary/60"></div>
                        <div class="absolute top-2.5 right-2.5 w-4 h-4 border-t-2 border-r-2 border-primary/60"></div>
                        <div class="absolute bottom-2.5 left-2.5 w-4 h-4 border-b-2 border-l-2 border-primary/60"></div>
                        <div class="absolute bottom-2.5 right-2.5 w-4 h-4 border-b-2 border-r-2 border-primary/60"></div>

                        {{-- Logo im Hintergrund --}}
                        <div class="absolute inset-0 flex items-center justify-center opacity-[0.04] pointer-events-none select-none">
                            <img src="{{ URL::to('/shop/projekt/logo/mein-seelenfunke-logo.svg') }}" class="w-64 h-auto" alt="Mein Seelenfunke Logo">
                        </div>

                        {{-- Header --}}
                        <div class="flex justify-between items-start gap-3 z-10">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-primary font-bold">Geschenkgutschein</p>
                                <p class="text-[10px] text-gray-500 tracking-wider font-mono mt-0.5">SEELENFUNKE-XXXX-XXXX</p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-3xl sm:text-4xl font-serif font-bold text-primary whitespace-nowrap">
                                    {{ number_format($this->selectedAmount, 2, ',', '.') }}&nbsp;€
                                </span>
                            </div>
                        </div>

                        {{-- Body (Nachricht & Empfänger) --}}
                        <div class="my-4 z-10">
                            @if(trim((string) $recipientName) !== '')
                                <p class="text-xs text-gray-500 mb-0.5">Für:</p>
                                <p class="text-lg font-serif font-bold text-gray-900 tracking-wide truncate mb-2">{{ $recipientName }}</p>
                            @else
                                <p class="text-xs text-gray-400 italic mb-3">Empfänger wird in Schritt 2 eingetragen...</p>
                            @endif

                            @if(trim((string) $personalMessage) !== '')
                                <p class="text-xs text-gray-500 mb-0.5">Deine Nachricht:</p>
                                <p class="text-sm font-sans italic text-gray-700 line-clamp-3 leading-relaxed">„{{ $personalMessage }}“</p>
                            @else
                                <p class="text-xs text-gray-400 italic">Persönliche Widmung (optional)...</p>
                            @endif
                        </div>

                        {{-- Footer --}}
                        <div class="flex justify-between items-end border-t border-primary/20 pt-3 z-10">
                            <p class="text-[9px] text-gray-500">Einlösbar auf www.mein-seelenfunke.de</p>
                            <p class="text-[9px] text-primary font-bold uppercase tracking-wider">Mein Seelenfunke</p>
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

            {{-- STEP-BY-STEP KONFIGURATOR (Rechts, 7 Columns) --}}
            <div class="lg:col-span-7 space-y-6" id="voucher-wizard-top">

                {{-- Produktdetails Header --}}
                <div>
                    <span class="inline-block px-3 py-1 bg-primary/10 border border-primary/20 rounded-full text-xs font-semibold tracking-wider text-primary mb-3 uppercase">Exklusives Geschenk</span>
                    <h2 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 tracking-wide mb-2">Deinen Gutschein konfigurieren</h2>
                    <p class="text-gray-600 text-sm sm:text-base leading-relaxed">
                        In 3 einfachen Schritten zu deinem persönlichen Geschenkgutschein – wir begleiten dich Schritt für Schritt bis zum Warenkorb.
                    </p>
                </div>

                {{-- Fortschrittsanzeige (Step-by-Step Stepper) --}}
                <div class="bg-gray-50 border border-gray-200/80 rounded-3xl p-4 sm:p-5">
                    <div class="grid grid-cols-3 gap-2 sm:gap-4 relative">
                        @php
                            $stepsMeta = [
                                1 => [
                                    'title' => 'Gutscheinwert',
                                    'sub'   => $this->isStep1Valid ? number_format($this->selectedAmount, 2, ',', '.') . ' € gewählt' : 'Betrag wählen',
                                    'valid' => $this->isStep1Valid,
                                ],
                                2 => [
                                    'title' => 'Personalisierung',
                                    'sub'   => $this->isStep2Valid ? 'Für ' . \Illuminate\Support\Str::limit($recipientName, 16) : 'Empfänger & Gruß',
                                    'valid' => $this->isStep2Valid,
                                ],
                                3 => [
                                    'title' => 'Versand & Kauf',
                                    'sub'   => $deliveryMethod === 'post' ? 'Postversand' : 'E-Mail PDF',
                                    'valid' => $this->isStep3Valid,
                                ],
                            ];
                        @endphp

                        @foreach($stepsMeta as $stepNum => $meta)
                            @php
                                $isActive = $currentStep === $stepNum;
                                $isDone = $currentStep > $stepNum && $meta['valid'];
                                $canClick = $stepNum < $currentStep || $stepNum <= $maxReachedStep;
                            @endphp
                            <button type="button"
                                    wire:click="goToStep({{ $stepNum }})"
                                    class="flex items-center gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-2xl text-left transition-all border {{ $isActive ? 'bg-white border-primary shadow-sm ring-2 ring-primary/15' : ($isDone ? 'bg-white/80 border-emerald-200 hover:border-primary/40 cursor-pointer' : 'bg-transparent border-transparent opacity-60 cursor-pointer') }}">
                                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center font-serif font-bold text-xs sm:text-sm shrink-0 transition-colors {{ $isActive ? 'bg-primary text-white shadow-xs' : ($isDone ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-600') }}">
                                    @if($isDone)
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        0{{ $stepNum }}
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs sm:text-sm font-bold truncate {{ $isActive ? 'text-gray-900' : ($isDone ? 'text-emerald-900' : 'text-gray-600') }}">
                                        {{ $meta['title'] }}
                                    </p>
                                    <p class="text-[10px] truncate hidden sm:block {{ $isActive ? 'text-primary font-semibold' : 'text-gray-400' }}">
                                        {{ $meta['sub'] }}
                                    </p>
                                </div>
                            </button>
                        @endforeach
                    </div>

                    {{-- Progress Bar Linie --}}
                    <div class="mt-3.5 h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-primary rounded-full transition-all duration-500"
                             style="width: {{ $currentStep === 1 ? '33.33%' : ($currentStep === 2 ? '66.66%' : '100%') }}"></div>
                    </div>
                </div>

                {{-- Kompakte Zusammenfassung bereits abgeschlossener Schritte --}}
                @if($currentStep > 1)
                    <div class="flex flex-wrap items-center gap-2.5 px-1">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-xs text-emerald-900">
                            <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span>Wert: <strong>{{ number_format($this->selectedAmount, 2, ',', '.') }} €</strong></span>
                            <button type="button" wire:click="goToStep(1)" class="ml-1 text-[11px] font-bold text-primary hover:underline cursor-pointer">Ändern</button>
                        </div>

                        @if($currentStep > 2 && $this->isStep2Valid)
                            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-xs text-emerald-900">
                                <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>Für: <strong>{{ \Illuminate\Support\Str::limit($recipientName, 24) }}</strong></span>
                                <button type="button" wire:click="goToStep(2)" class="ml-1 text-[11px] font-bold text-primary hover:underline cursor-pointer">Ändern</button>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ================================================================= --}}
                {{-- SCHRITT 1: GUTSCHEINWERT WÄHLEN --}}
                {{-- ================================================================= --}}
                @if($currentStep === 1)
                    <div class="bg-white border border-gray-200/80 rounded-3xl p-6 sm:p-8 shadow-sm animate-fade-in">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 flex items-center gap-2">
                                <span class="text-primary">01.</span>
                                Wähle den Gutscheinwert
                            </h3>
                            <span class="text-xs font-semibold text-gray-400">Schritt 1 von 3</span>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-500 mb-6">
                            Wähle einen der beliebtesten Gutscheinbeträge oder gib deinen individuellen Wunschbetrag ein.
                        </p>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                            @foreach($presets as $p)
                                <button type="button"
                                        wire:click="setAmount({{ $p }})"
                                        class="py-4 px-3 sm:px-6 rounded-2xl border-2 font-serif font-bold text-lg tracking-wide transition-all duration-300 cursor-pointer {{ $amount === $p ? 'border-primary bg-primary/10 text-primary shadow-sm' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">
                                    {{ $p }} €
                                </button>
                            @endforeach
                        </div>

                        {{-- Wunschbetrag Button & Input --}}
                        <div>
                            <button type="button"
                                    wire:click="setAmount('custom')"
                                    class="w-full py-3.5 px-4 sm:px-6 rounded-2xl border-2 font-serif font-bold text-sm tracking-wider transition-all duration-300 cursor-pointer {{ $amount === 'custom' ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">
                                ✨ Anderen Wunschbetrag wählen
                            </button>

                            @if($amount === 'custom')
                                <div class="mt-4 animate-fade-in">
                                    <label class="block text-xs uppercase tracking-wider text-gray-600 font-semibold mb-2 ml-1">
                                        Dein Wunschbetrag in Euro <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="number"
                                               step="5"
                                               min="5"
                                               max="1000"
                                               wire:model.live="customAmount"
                                               wire:keydown.enter.prevent="nextStep"
                                               placeholder="Wunschbetrag eingeben (z. B. 75)"
                                               class="w-full bg-white border-2 @error('customAmount') border-red-500 focus:border-red-500 focus:ring-red-500/20 @elseif($this->isStep1Valid) border-emerald-500 focus:border-emerald-500 @else border-gray-300 focus:border-primary focus:ring-primary/20 @enderror rounded-2xl py-4 pl-6 pr-12 text-lg font-serif font-bold text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2">
                                        <span class="absolute right-6 top-1/2 -translate-y-1/2 text-gray-500 font-serif font-bold text-lg">€</span>
                                    </div>
                                    <span class="text-[11px] text-gray-500 mt-1.5 block ml-1">
                                        Erlaubt sind Beträge von <strong>5 € bis 1.000 €</strong> in vollen 5er-Schritten (z. B. 15 €, 35 €, 75 €, 150 €).
                                    </span>

                                    @error('customAmount')
                                        <div class="mt-3 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
                                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <span>{{ $message }}</span>
                                        </div>
                                    @elseif($this->isStep1Valid)
                                        <div class="mt-2.5 text-emerald-700 text-xs font-semibold flex items-center gap-1.5 ml-1">
                                            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            <span>Wunschbetrag von {{ number_format($this->selectedAmount, 2, ',', '.') }} € ist gültig.</span>
                                        </div>
                                    @enderror
                                </div>
                            @endif

                            @error('amount')
                                <div class="mt-3 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold">
                                    ⚠️ {{ $message }}
                                </div>
                            @enderror
                        </div>

                        {{-- Footer Navigation Schritt 1 --}}
                        <div class="mt-8 pt-6 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="text-xs text-gray-500">
                                Gewählter Gutscheinwert: <strong class="text-gray-900 font-serif text-base ml-1">{{ number_format($this->selectedAmount, 2, ',', '.') }} €</strong>
                            </div>
                            <button type="button"
                                    wire:click="nextStep"
                                    class="w-full sm:w-auto px-7 py-3.5 bg-primary text-white font-serif font-bold text-base rounded-2xl hover:bg-primary/90 transition-all shadow-md shadow-primary/10 flex items-center justify-center gap-2.5 cursor-pointer">
                                <span>Weiter zur Personalisierung</span>
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- ================================================================= --}}
                {{-- SCHRITT 2: PERSONALISIERUNG (EMPFÄNGER & WIDMUNG) --}}
                {{-- ================================================================= --}}
                @if($currentStep === 2)
                    <div class="bg-white border border-gray-200/80 rounded-3xl p-6 sm:p-8 shadow-sm animate-fade-in">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 flex items-center gap-2">
                                <span class="text-primary">02.</span>
                                Personalisierung
                            </h3>
                            <span class="text-xs font-semibold text-gray-400">Schritt 2 von 3</span>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-500 mb-6">
                            Trage den Namen der beschenkten Person und optional deine persönliche Grußbotschaft ein. Beides erscheint direkt in der Live-Vorschau.
                        </p>

                        <div class="space-y-6">
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-gray-700 font-bold mb-2 ml-1">
                                    Für wen ist der Gutschein? (Empfänger-Name) <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       wire:model.live="recipientName"
                                       wire:keydown.enter.prevent="nextStep"
                                       placeholder="z. B. Alina Steinhauer"
                                       class="w-full bg-white border-2 @error('recipientName') border-red-500 focus:border-red-500 focus:ring-red-500/20 @elseif(mb_strlen(trim((string) $recipientName)) >= 2) border-emerald-500 focus:border-emerald-500 @else border-gray-300 focus:border-primary focus:ring-primary/20 @enderror rounded-2xl py-3.5 px-5 text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2">

                                @error('recipientName')
                                    <div class="mt-2.5 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
                                        <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span>{{ $message }}</span>
                                    </div>
                                @elseif(mb_strlen(trim((string) $recipientName)) >= 2)
                                    <span class="text-emerald-700 text-xs font-semibold mt-1.5 flex items-center gap-1.5 ml-1">
                                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Empfänger-Name ist übernommen.
                                    </span>
                                @else
                                    <span class="text-[11px] text-gray-400 mt-1.5 block ml-1">Pflichtfeld · Mindestens 2 Zeichen.</span>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-xs uppercase tracking-wider text-gray-700 font-bold mb-2 ml-1">
                                    Deine persönliche Grußbotschaft <span class="text-gray-400 font-normal">(Optional)</span>
                                </label>
                                <textarea wire:model.live="personalMessage"
                                          rows="3"
                                          maxlength="160"
                                          placeholder="Schreibe eine liebevolle Widmung für die Gutscheinkarte..."
                                          class="w-full bg-white border-2 @error('personalMessage') border-red-500 focus:border-red-500 @else border-gray-300 focus:border-primary @enderror rounded-2xl py-3.5 px-5 text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 resize-none"></textarea>
                                <div class="flex justify-between items-center mt-1.5 px-1">
                                    <span class="text-[11px] text-gray-500">Wird auf dem Gutschein unter dem Empfängernamen abgedruckt</span>
                                    <span class="text-[11px] {{ mb_strlen((string) $personalMessage) > 160 ? 'text-red-600 font-bold' : 'text-gray-500 font-medium' }}">{{ mb_strlen((string) $personalMessage) }}/160</span>
                                </div>
                                @error('personalMessage')
                                    <span class="text-red-600 text-xs font-semibold mt-1 block">⚠️ {{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        {{-- Footer Navigation Schritt 2 --}}
                        <div class="mt-8 pt-6 border-t border-gray-100 flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
                            <button type="button"
                                    wire:click="previousStep"
                                    class="w-full sm:w-auto px-6 py-3.5 rounded-2xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-bold text-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                <span>Zurück zum Wert</span>
                            </button>

                            <button type="button"
                                    wire:click="nextStep"
                                    class="w-full sm:w-auto px-7 py-3.5 bg-primary text-white font-serif font-bold text-base rounded-2xl hover:bg-primary/90 transition-all shadow-md shadow-primary/10 flex items-center justify-center gap-2.5 cursor-pointer">
                                <span>Weiter zur Versandart</span>
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- ================================================================= --}}
                {{-- SCHRITT 3: VERSANDMETHODE & ABSCHLUSS --}}
                {{-- ================================================================= --}}
                @if($currentStep === 3)
                    <div class="bg-white border border-gray-200/80 rounded-3xl p-6 sm:p-8 shadow-sm animate-fade-in space-y-6">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 flex items-center gap-2">
                                    <span class="text-primary">03.</span>
                                    Versandmethode & Abschluss
                                </h3>
                                <span class="text-xs font-semibold text-gray-400">Schritt 3 von 3</span>
                            </div>
                            <p class="text-xs sm:text-sm text-gray-500">
                                Entscheide, ob dein Gutschein sofort digital per E-Mail oder als gedruckte Karte im edlen Umschlag mit goldenem Wachssiegel per Post versendet werden soll.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <button type="button"
                                    wire:click="$set('deliveryMethod', 'email')"
                                    class="p-5 rounded-2xl border-2 text-left transition-all duration-300 flex flex-col justify-between cursor-pointer {{ $deliveryMethod === 'email' ? 'border-primary bg-primary/10 shadow-xs' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                                <div class="flex items-center justify-between w-full mb-3">
                                    <span class="text-sm font-bold {{ $deliveryMethod === 'email' ? 'text-primary' : 'text-gray-800' }}">📧 Per E-Mail (PDF)</span>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-green-500/10 text-green-700 border border-green-500/20 font-bold">Gratis</span>
                                </div>
                                <span class="text-xs text-gray-500 leading-relaxed">Sofortige Zustellung nach Zahlung per PDF-Anhang. Ideal für Last-Minute-Geschenke.</span>
                            </button>

                            <button type="button"
                                    wire:click="$set('deliveryMethod', 'post')"
                                    class="p-5 rounded-2xl border-2 text-left transition-all duration-300 flex flex-col justify-between cursor-pointer {{ $deliveryMethod === 'post' ? 'border-primary bg-primary/10 shadow-xs' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                                <div class="flex items-center justify-between w-full mb-3">
                                    <span class="text-sm font-bold {{ $deliveryMethod === 'post' ? 'text-primary' : 'text-gray-800' }}">✉️ Per Post im Umschlag</span>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20 font-bold">+ {{ number_format($this->postShippingCost, 2, ',', '.') }} €</span>
                                </div>
                                <span class="text-xs text-gray-500 leading-relaxed">Gedruckte Klappkarte im schwarzen Umschlag mit goldenem Siegel-Aufkleber.</span>
                            </button>
                        </div>

                        @if($deliveryMethod === 'email')
                            <div class="animate-fade-in">
                                <label class="block text-xs uppercase tracking-wider text-gray-700 font-bold mb-2 ml-1">
                                    E-Mail-Adresse für den Gutschein-Empfang <span class="text-red-500">*</span>
                                </label>
                                <input type="email"
                                       wire:model.live="recipientEmail"
                                       wire:keydown.enter.prevent="addToCart"
                                       placeholder="z. B. empfaenger@example.com"
                                       class="w-full bg-white border-2 @error('recipientEmail') border-red-500 focus:border-red-500 focus:ring-red-500/20 @elseif($this->isStep3Valid) border-emerald-500 focus:border-emerald-500 @else border-gray-300 focus:border-primary focus:ring-primary/20 @enderror rounded-2xl py-3.5 px-5 text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2">

                                @error('recipientEmail')
                                    <div class="mt-2.5 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
                                        <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span>{{ $message }}</span>
                                    </div>
                                @elseif($this->isStep3Valid)
                                    <span class="text-emerald-700 text-xs font-semibold mt-1.5 flex items-center gap-1.5 ml-1">
                                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        E-Mail-Adresse ist gültig – dein Gutschein ist bereit für den Warenkorb!
                                    </span>
                                @else
                                    <span class="text-[11px] text-gray-500 mt-1.5 block ml-1">Pflichtfeld · Hierhin wird der Gutschein digital nach Abschluss des Kaufs versendet.</span>
                                @enderror
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
                                            <span>Edler schwarzer Umschlag mit goldenen Linien</span>
                                        </li>
                                        <li class="flex items-center gap-2 text-gray-700">
                                            <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            <span>Goldener Herzkranz-Siegel-Aufkleber</span>
                                        </li>
                                        <li class="flex items-center gap-2 text-gray-700">
                                            <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            <span>Hochwertig gedruckte Gutschein-Klappkarte</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endif

                        {{-- Finale Zusammenfassung & In den Warenkorb legen (Luftig & über volle Breite) --}}
                        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 sm:p-7 space-y-6">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs border-b border-gray-200/80 pb-5">
                                <div>
                                    <span class="text-gray-400 block uppercase tracking-wider text-[10px] font-bold mb-1">Gutscheinwert</span>
                                    <span class="font-bold text-gray-900 text-sm whitespace-nowrap">{{ number_format($this->selectedAmount, 2, ',', '.') }}&nbsp;€</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block uppercase tracking-wider text-[10px] font-bold mb-1">Empfänger</span>
                                    <span class="font-bold text-gray-900 text-sm truncate block">{{ $recipientName ?: '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block uppercase tracking-wider text-[10px] font-bold mb-1">Zustellung</span>
                                    <span class="font-bold text-gray-900 text-sm whitespace-nowrap">
                                        {{ $deliveryMethod === 'post' ? 'Per Post (+ ' . number_format($this->postShippingCost, 2, ',', '.') . ' €)' : 'Per E-Mail (Gratis)' }}
                                    </span>
                                </div>
                            </div>

                            {{-- Gesamtsumme Zeile (mit whitespace-nowrap gegen Zeilenumbruch beim €-Zeichen) --}}
                            <div class="flex flex-wrap items-baseline justify-between gap-4">
                                <div>
                                    <p class="text-xs uppercase tracking-wider text-gray-500 font-bold">Gesamtsumme</p>
                                    @if($this->shippingCost > 0)
                                        <span class="text-xs text-gray-500">inkl. Postversand im edlen Umschlag</span>
                                    @else
                                        <span class="text-xs text-emerald-600 font-semibold">Versandkostenfrei per E-Mail</span>
                                    @endif
                                </div>

                                <span class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 whitespace-nowrap">
                                    {{ number_format($this->finalTotal, 2, ',', '.') }}&nbsp;€
                                </span>
                            </div>

                            {{-- Aktions-Buttons: In den Warenkorb legen über die volle Breite --}}
                            <div class="space-y-3 pt-1">
                                <button type="button"
                                        wire:click="addToCart"
                                        class="w-full py-4 px-8 bg-primary text-white font-serif font-bold text-lg rounded-2xl hover:bg-primary/90 hover:scale-[1.01] active:scale-[0.99] transition-all duration-300 shadow-md shadow-primary/15 flex items-center justify-center gap-3 cursor-pointer whitespace-nowrap">
                                    <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                    <span>In den Warenkorb legen</span>
                                </button>

                                <button type="button"
                                        wire:click="previousStep"
                                        class="w-full py-3 px-5 rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 hover:text-gray-900 font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                    <span>Zurück zu Schritt 2 (Personalisierung)</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                @if(session()->has('error'))
                    <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm">
                        {{ session('error') }}
                    </div>
                @endif

            </div>
        </div>

        {{-- CROSS-SELLING / PASSENDE PRODUKTE UNTERHALB DES ZWEI-SPALTEN-DESIGNS --}}
        @include('livewire.shop.product.partials.product-show.related-products')
    </div>
</div>
