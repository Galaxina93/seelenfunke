@if($context !== 'preview')
    <div :class="isMaximized ? 'px-4 py-2.5' : 'p-4'"
         class="border-t z-30 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] shrink-0 {{ $isDark ? 'bg-gray-950 border-gray-800' : 'bg-white border-gray-200' }}">
        <div :class="isMaximized ? 'max-w-6xl mx-auto space-y-2' : 'max-w-4xl mx-auto space-y-3.5'">
            {{-- VERPACKUNGS-UPGRADES FÜR NICHT-PERSONALISIERBARE PHYSISCHE PRODUKTE SOWIE SCHNELL-PERSONALISIERUNG IM FOOTER (BEI STUDIO LIEGEN SIE IN DER RECHTEN STUDIO-SPALTE) --}}
            @if((!$this->product->isPersonalizable() || $this->product->usesCustomFieldsPersonalization()) && $context !== 'template_admin' && $context !== 'order_edit' && $this->type === 'physical' && !$isDigital && !$isService && count($this->availableAddons) > 0)
                <div class="space-y-2 animate-fade-in-up"
                     x-data="{ previewAddon: null }"
                     @keydown.escape.window="if (previewAddon) previewAddon = null">
                    <span class="block text-[10px] font-black uppercase tracking-widest {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">
                        🎁 Verpackung &amp; Extras (Optional hinzubuchen)
                    </span>
                    <div class="grid grid-cols-1 gap-2.5">
                        @foreach($this->availableAddons as $addon)
                            @php
                                $isAddonSelected = in_array($addon['id'], $selectedAddons, true);
                                $addonFormattedPrice = number_format(((int) $addon['price_cents']) / 100, 2, ',', '.');
                                $addonImageUrl = $addon['image_url'] ?? \App\Models\Product\Product::resolveAddonImageUrl($addon);
                            @endphp
                            <div wire:click="toggleAddon('{{ $addon['id'] }}')"
                                 role="button"
                                 tabindex="0"
                                 @keydown.enter.prevent="$wire.toggleAddon('{{ $addon['id'] }}')"
                                 @keydown.space.prevent="$wire.toggleAddon('{{ $addon['id'] }}')"
                                 class="w-full text-left border rounded-xl p-3 transition-all flex items-center justify-between gap-3.5 cursor-pointer group {{ $isAddonSelected ? ($isDark ? 'bg-amber-900/20 border-primary ring-1 ring-primary/50 shadow-sm' : 'bg-amber-50/80 border-primary ring-1 ring-primary/40 shadow-xs') : ($isDark ? 'bg-gray-900 border-gray-800 hover:border-primary/40' : 'bg-white border-gray-200 hover:border-primary/40 shadow-2xs') }}">
                                <div class="flex items-center gap-3.5 min-w-0 flex-1">
                                    <span class="w-5 h-5 rounded flex items-center justify-center border shrink-0 transition-colors {{ $isAddonSelected ? 'bg-primary border-primary text-gray-900 shadow-2xs' : ($isDark ? 'border-gray-600 bg-gray-950 group-hover:border-primary/60' : 'border-gray-300 bg-white group-hover:border-primary/60') }}">
                                        @if($isAddonSelected)
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </span>
                                    @if($addonImageUrl)
                                        <div class="relative w-12 h-12 rounded-xl overflow-hidden border shrink-0 {{ $isAddonSelected ? 'border-primary ring-2 ring-primary/30' : ($isDark ? 'border-gray-700 bg-gray-950' : 'border-gray-200 bg-gray-50') }}">
                                            <img src="{{ $addonImageUrl }}" alt="{{ $addon['name'] }}" class="w-full h-full object-cover">
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <span class="text-xs sm:text-sm font-bold block leading-tight {{ $isDark ? 'text-gray-100' : 'text-gray-800' }}">
                                            {{ $addon['name'] }}
                                        </span>
                                    </div>
                                </div>
                                <span class="font-bold text-primary bg-primary/10 px-2.5 py-1 rounded-lg text-xs whitespace-nowrap shrink-0">
                                    + {{ $addonFormattedPrice }}&nbsp;€
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div :class="isMaximized ? 'flex flex-col lg:flex-row lg:items-center gap-2' : 'space-y-3'">
                @if($context !== 'calculator')
                    <div x-ref="confirmBox"
                         :class="[
                             (confirmWarning || {{ $errors->has('config_confirmed') ? 'true' : 'false' }})
                                 ? '{{ $isDark ? 'bg-red-950/40 border-red-500 ring-2 ring-red-500/30 shadow-[0_0_20px_rgba(239,68,68,0.2)]' : 'bg-red-50 border-red-400 ring-2 ring-red-500/20 shadow-sm' }}'
                                 : '{{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-gray-50 border-gray-200' }}',
                             isMaximized ? 'px-3 py-2 flex-1 min-w-0' : 'p-3'
                         ]"
                         class="rounded-xl border transition-all duration-200">
                        <label class="flex items-start gap-2.5 cursor-pointer group">
                            <input type="checkbox"
                                   wire:model.live="config_confirmed"
                                   @change="if($el.checked) confirmWarning = false"
                                   :class="(confirmWarning || {{ $errors->has('config_confirmed') ? 'true' : 'false' }}) ? 'border-red-500 ring-2 ring-red-500/30' : '{{ $isDark ? 'border-gray-700 bg-gray-950' : 'border-gray-300' }}'"
                                   class="mt-0.5 w-4 h-4 text-primary rounded focus:ring-primary cursor-pointer transition-all shrink-0">
                            <span class="text-[11px] leading-snug transition-colors {{ $isDark ? 'text-gray-400 group-hover:text-gray-200' : 'text-gray-600 group-hover:text-gray-900' }}">
                                @if($context === 'template_admin')
                                    Ich bestätige, dass die Vorlage korrekt konfiguriert ist und als Basis für Kunden dient.
                                @elseif($isDigital)
                                    Ich stimme ausdrücklich zu, dass mit der Ausführung des Vertrags vor Ablauf der Widerrufsfrist begonnen wird. Mir ist bekannt, dass ich durch diese Zustimmung mein Widerrufsrecht verliere.
                                @elseif(!$this->product->isPersonalizable())
                                    Ich bestätige meine Bestellung. Da dieses Produkt nicht personalisiert ist, gilt das gesetzliche 14-tägige Widerrufsrecht.
                                @elseif($this->product->usesCustomFieldsPersonalization())
                                    Ich habe meine Wunschgravur-Angaben auf korrekte Schreibweise geprüft. Individualisierte Artikel werden speziell nach Kundenwunsch gefertigt und sind vom <strong>Widerrufsrecht ausgeschlossen</strong>.
                                @else
                                    Ich habe meine Texte, Logos und Positionen geprüft. Die Vorschau ist eine <strong>Visualisierung</strong>; handwerkliche Abweichungen sind möglich. Individualisierte Artikel sind vom <strong>Widerrufsrecht ausgeschlossen</strong>.
                                @endif
                                @if($context !== 'template_admin')
                                    <a href="{{ route('agb') }}" target="_blank" class="text-primary underline font-bold ml-1">AGB</a> &amp; <a href="{{ route('widerruf') }}" target="_blank" class="text-primary underline font-bold">Widerrufsbelehrung</a>.
                                @endif
                            </span>
                        </label>
                        <div x-show="confirmWarning" x-cloak x-transition class="mt-1.5 pt-1.5 border-t border-red-200/70 dark:border-red-800/60 flex items-center gap-1.5 text-xs font-bold text-red-600 dark:text-red-400">
                            <svg class="w-4 h-4 shrink-0 text-red-500 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <span>Bitte setze noch diesen Haken zur Bestätigung deiner Angaben, um fortzufahren.</span>
                        </div>
                        @error('config_confirmed')
                            <p class="text-xs text-red-600 dark:text-red-400 font-bold mt-1 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>
                @endif

                @if($context !== 'template_admin' && ($this->product->type ?? 'physical') !== 'geschenkgutschein')
                    @php
                        $configuratorBonusSparks = \App\Services\Gamification\GameConfig::calculateSparksForCents((int) $totalPrice);
                    @endphp
                    @if($configuratorBonusSparks > 0)
                        <div :class="isMaximized ? 'lg:w-auto shrink-0' : 'w-full'"
                             class="flex items-center justify-between gap-2.5 px-3.5 py-2 rounded-xl border {{ $isDark ? 'bg-amber-500/10 border-[#C5A059]/40 text-[#f5dca0]' : 'bg-amber-50/90 border-amber-200/90 text-amber-900' }} text-xs">
                            <span class="flex items-center gap-1.5 font-semibold whitespace-nowrap">
                                <span>✨</span>
                                <span>Seelenfunken Treue-Bonus (100&nbsp;%):</span>
                            </span>
                            <span class="font-mono font-black px-2.5 py-0.5 rounded-full whitespace-nowrap {{ $isDark ? 'bg-[#C5A059]/25 text-[#f5dca0]' : 'bg-amber-200/70 text-amber-950' }}">
                                +{{ number_format($configuratorBonusSparks, 0, ',', '.') }} ✨ Seelenfunken
                            </span>
                        </div>
                    @endif
                @endif
            </div>

            <div class="flex flex-col sm:flex-row gap-3 h-auto sm:h-12">
                @if($context !== 'template_admin' && $context !== 'order_edit')
                        <div class="relative w-full sm:w-28 h-12 rounded-xl border transition-all flex items-center {{ $isDark ? 'bg-gray-900 border-gray-800 hover:border-gray-700 focus-within:border-primary focus-within:bg-gray-950' : 'bg-gray-100 border-transparent hover:border-gray-300 focus-within:border-primary focus-within:bg-white' }}">
                            <label class="absolute left-3 text-[9px] font-bold uppercase tracking-wider pointer-events-none {{ $isDark ? 'text-gray-500' : 'text-gray-500' }}">Menge</label>

                            {{-- COMPACT SELECT: text-lg statt text-xl, padding leicht reduziert --}}
                            <select wire:model.live="qty" class="appearance-none bg-transparent w-full h-full font-bold text-lg focus:outline-none cursor-pointer pl-16 pr-8 {{ $isDark ? 'text-white' : 'text-gray-900' }}">
                                @for($i = 1; $i <= 100; $i++)
                                    <option value="{{$i}}" class="text-gray-900 bg-white" style="text-align: left; padding-left: 10px;">{{$i}}</option>
                                @endfor
                            </select>

                            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none {{ $isDark ? 'text-gray-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                @endif

                <button type="button"
                        @click.prevent="submitConfig()"
                        :disabled="isSaving"
                        wire:loading.attr="disabled"
                        :class="saved
                            ? 'bg-emerald-600 hover:bg-emerald-700 text-white scale-[1.01] shadow-[0_10px_28px_rgba(16,185,129,0.35)]'
                            : (isSaving
                                ? 'opacity-90 pointer-events-none cursor-wait {{ $isDark ? 'bg-primary text-gray-900' : 'bg-gray-900 text-white' }}'
                                : '{{ $isDark ? 'bg-primary text-gray-900 hover:bg-primary-dark cursor-pointer active:scale-[0.99]' : 'bg-gray-900 text-white hover:bg-black cursor-pointer active:scale-[0.99]' }}')"
                        class="flex-1 h-20 sm:h-12 rounded-2xl sm:rounded-xl font-black sm:font-bold text-xl sm:text-base tracking-wide sm:tracking-normal w-full transition-all duration-500 ease-out flex items-center justify-center shadow-2xl sm:shadow-lg hover:shadow-2xl disabled:shadow-none relative overflow-hidden group">
                    @if($context !== 'template_admin')
                        <div class="absolute left-0 top-0 bottom-0 bg-black/10 px-4 flex flex-col justify-center items-start border-r border-black/10 min-w-[100px] sm:min-w-[90px] pointer-events-none transition-colors duration-500">
                            <span class="font-serif font-bold leading-none tracking-wide text-lg sm:text-base {{ $qty > 1 ? 'sm:text-sm' : 'sm:text-base' }}">{{ number_format($totalPrice / 100, 2, ',', '.') }} €</span>
                            @if($qty > 1)
                                <span class="text-xs sm:text-[9px] opacity-80 font-normal leading-none mt-1 sm:mt-0.5">je {{ number_format($currentPrice / 100, 2, ',', '.') }} €</span>
                            @endif
                        </div>
                        <div class="pl-[105px] sm:pl-24 pr-4 w-full flex items-center justify-center h-full relative">
                    @else
                        <div class="w-full flex items-center justify-center h-full relative">
                    @endif

                        {{-- STATUS: ERFOLG --}}
                        <div x-show="saved"
                             x-cloak
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-2"
                             class="flex items-center gap-2 text-white">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ $context === 'template_admin' ? 'Gespeichert!' : 'Hinzugefügt!' }}</span>
                        </div>

                        {{-- STATUS: LADEN --}}
                        <div x-show="!saved && isSaving"
                             x-cloak
                             x-transition:enter="transition ease-out duration-250"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="flex items-center gap-2.5">
                            <svg class="animate-spin h-5 w-5 text-current shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="font-bold text-base sm:text-sm tracking-normal">
                                @if($context === 'calculator')
                                    Wird übernommen...
                                @elseif($context === 'add')
                                    Wird hinzugefügt...
                                @elseif($context === 'template_admin')
                                    Vorlage wird gespeichert...
                                @else
                                    Wird gespeichert...
                                @endif
                            </span>
                        </div>

                        {{-- STATUS: NORMALER BUTTON-TEXT --}}
                        <div x-show="!saved && !isSaving"
                             x-transition:enter="transition ease-out duration-250"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             class="flex items-center gap-2">
                            <span>
                                @if($context === 'add')
                                    In den Warenkorb
                                @elseif($context === 'edit')
                                    Speichern
                                @elseif($context === 'order_edit')
                                    Änderungen speichern
                                @elseif($context === 'calculator')
                                    Übernehmen
                                @elseif($context === 'template_admin')
                                    Vorlage speichern
                                @endif
                            </span>
                            @if($context === 'calculator')
                                <svg class="w-5 h-5 hidden sm:inline-block transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            @endif
                        </div>
                    </div>
                </button>
            </div>

            @if($context !== 'template_admin')
                <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-[10px] {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">
                    <span>
                        @if(!empty($this->priceBreakdown['compare_at_cents']))
                            <span class="font-semibold {{ $isDark ? 'text-gray-300' : 'text-gray-600' }}">30-Tage-Bestpreis (§ 11 PAngV): {{ number_format($this->priceBreakdown['compare_at_cents'] / 100, 2, ',', '.') }} €</span> ·
                        @elseif(($this->product->compare_at_price ?? 0) > ($this->product->price ?? 0))
                            <span class="font-semibold {{ $isDark ? 'text-gray-300' : 'text-gray-600' }}">30-Tage-Bestpreis (§ 11 PAngV): {{ number_format($this->product->compare_at_price / 100, 2, ',', '.') }} €</span> ·
                        @endif
                        @if((bool) shop_setting('is_small_business', false))
                            Endpreis gem. § 19 UStG (keine Ausweisung der USt.)
                        @else
                            Alle Preise inkl. gesetzl. MwSt.
                        @endif
                        @if(!$isDigital && ($this->product->type ?? 'physical') === 'physical')
                            · <a href="{{ route('versand') }}" target="_blank" class="underline hover:text-primary">zzgl. Versandkosten</a>
                        @else
                            · versandkostenfrei
                        @endif
                    </span>
                </div>
            @endif

            @if($context !== 'template_admin' && $context !== 'calculator')
                <div x-show="saved"
                     x-cloak
                     x-collapse.duration.400ms
                     class="w-full pt-2.5 space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <a href="{{ route('checkout') }}"
                           data-sf-portal="checkout"
                           class="w-full bg-gradient-to-r from-[#C5A059] to-[#b38c42] text-gray-950 text-xs sm:text-sm rounded-xl font-black uppercase tracking-wider flex items-center justify-center gap-2 transition-all duration-300 group py-2.5 px-4 shadow-md hover:brightness-110">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>Direkt zur Kasse</span>
                        </a>
                        <a href="{{ route('cart') }}"
                           data-sf-portal="cart"
                           class="w-full border-2 text-xs sm:text-sm rounded-xl font-bold flex items-center justify-center gap-2 transition-all duration-300 group py-2.5 px-4 {{ $isDark ? 'border-primary text-primary hover:bg-primary hover:text-gray-900' : 'border-primary text-gray-900 hover:bg-primary hover:text-white' }}">
                            <span>Zum Warenkorb wechseln</span>
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>
                    </div>
                    <div class="flex items-center justify-center gap-1.5 text-[10px] font-medium {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">
                        <span>Express-Checkout mit:</span>
                        <span class="font-bold {{ $isDark ? 'text-gray-300' : 'text-gray-700' }}">PayPal • Apple Pay • Google Pay • Klarna</span>
                    </div>
                </div>
            @endif

            @if($context === 'add')
                <div x-show="!isMaximized" class="pt-3 border-t {{ $isDark ? 'border-gray-800/80' : 'border-gray-100' }}">
                    <div class="w-fit sm:w-full mx-auto flex flex-col sm:grid sm:grid-cols-3 items-start sm:items-center justify-center gap-2.5 sm:gap-2">
                        <div class="flex items-center sm:justify-center gap-2.5">
                            <div class="w-7 h-7 rounded-full {{ $isDark ? 'bg-primary/15 border-primary/30 text-primary' : 'bg-primary/10 border-primary/20 text-primary' }} border flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            </div>
                            <span class="text-[11px] font-bold {{ $isDark ? 'text-gray-300' : 'text-gray-700' }} leading-tight">Freude schenken</span>
                        </div>
                        <div class="flex items-center sm:justify-center gap-2.5">
                            <div class="w-7 h-7 rounded-full {{ $isDark ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400' : 'bg-green-50 border-green-100 text-green-600' }} border flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                </svg>
                            </div>
                            @php
                                $confHerzensEuro = \App\Services\HerzensprojektService::resolveFormattedEuro();
                                $confHerzensUnit = \App\Services\HerzensprojektService::resolveUnitLabel(true);
                            @endphp
                            <a href="{{ route('manufacture') }}#herzensprojekte" target="_blank" title="Wir führen {{ $confHerzensEuro }} € {{ $confHerzensUnit }} aus unseren Unternehmenserlösen an unsere regionalen Herzensprojekte für Kinder ab (Details & Transparenzhinweis)" class="text-[11px] font-bold {{ $isDark ? 'text-gray-300 hover:text-primary' : 'text-gray-700 hover:text-primary' }} leading-tight transition-colors">Soziales leisten ({{ $confHerzensEuro }}&nbsp;€ {{ $confHerzensUnit }})</a>
                        </div>
                        <div class="flex items-center sm:justify-center gap-2.5">
                            <div class="w-7 h-7 rounded-full {{ $isDark ? 'bg-amber-500/15 border-amber-500/30 text-amber-400' : 'bg-amber-50 border-amber-200/80 text-amber-600' }} border flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                                </svg>
                            </div>
                            <span class="text-[11px] font-bold {{ $isDark ? 'text-gray-300' : 'text-gray-700' }} leading-tight">Funken sammeln</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
