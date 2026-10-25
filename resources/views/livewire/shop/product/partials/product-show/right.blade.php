<div class="w-full" @scroll-to-config.window="document.getElementById('configurator-start').scrollIntoView({ behavior: 'smooth', block: 'start' })">

    <div id="configurator-start" class="lg:sticky lg:top-24 w-full scroll-mt-24">
        <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 mb-4 leading-tight">
            {{ $this->product->name }}
        </h1>

        <div class="flex items-center gap-4 mb-6">
            {{-- Preis --}}
            <div class="flex flex-col">
                <div class="flex items-baseline gap-3 flex-wrap">
                    <span x-data="{ displayPrice: '{{ number_format($this->product->price / 100, 2, ',', '.') }} €' }"
                          @variant-selected.window="if ($event.detail && $event.detail.formattedPrice) { displayPrice = $event.detail.formattedPrice; }"
                          x-text="displayPrice"
                          class="text-2xl font-bold text-[var(--theme-color)] transition-all">
                        {{ number_format($this->product->price / 100, 2, ',', '.') }} €
                    </span>
                    @if($this->product->compare_at_price > $this->product->price)
                        <span class="text-lg text-gray-400 line-through decoration-red-400">
                            {{ number_format($this->product->compare_at_price / 100, 2, ',', '.') }} €
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-red-500 text-white shadow-sm">
                            {{ $this->product->configurator_settings['autopilot_badge_text'] ?? 'Angebot' }}
                        </span>
                    @endif
                </div>
                @if($this->product->compare_at_price > $this->product->price)
                    <span class="text-[11px] text-gray-400 mt-0.5">
                        Niedrigster Gesamtpreis der letzten 30 Tage vor der Preisermäßigung: {{ number_format($this->product->compare_at_price / 100, 2, ',', '.') }} €
                    </span>
                @endif

                <div class="flex flex-col gap-1 mt-1">
                    @php
                        $isSmallBusiness = filter_var(shop_setting('is_small_business', false), FILTER_VALIDATE_BOOLEAN);
                        $freeThreshold   = (int) shop_setting('shipping_free_threshold', 5000);
                        $shippingCost    = (int) shop_setting('shipping_cost', 490);

                        $type            = $this->product->type;
                        $isDigital       = $type === 'digital';
                        $isService       = $type === 'service';
                        $isPhysical      = $type === 'physical';

                        $isFree          = !$isPhysical || ($this->product->price >= $freeThreshold);

                        $isTrulyOutOfStock = $this->product->track_quantity &&
                                             $this->product->quantity <= 0 &&
                                             !$this->product->continue_selling_when_out_of_stock;

                    @endphp

                    {{-- Steuerhinweis --}}
                    <span class="text-xs text-gray-500">
                        @if($isSmallBusiness)
                            <span class="italic">Gemäß § 19 UStG wird keine Umsatzsteuer berechnet</span>
                        @else
                            @if($this->product->tax_included) inkl. MwSt. @else zzgl. MwSt. @endif
                        @endif
                    </span>

                    {{-- Dynamischer Versandhinweis --}}
                    @if($isDigital)
                        <span class="text-xs font-bold text-blue-600 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z" />
                                <path d="M9 13h2v5a1 1 0 11-2 0v-5z" />
                            </svg> Sofort-Download (Versandkostenfrei)
                        </span>
                    @elseif($isService)
                        <span class="text-xs font-bold text-orange-600 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                            </svg> Kein Versand (Dienstleistung)
                        </span>
                    @elseif($isFree)
                        <span class="text-xs font-bold text-green-700 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                                <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z" />
                            </svg> Kostenloser Versand
                        </span>
                    @else
                        <span class="text-xs text-gray-500">
                            zzgl. {{ number_format($shippingCost / 100, 2, ',', '.') }} € Versand
                            <span class="text-gray-400 font-medium">(frei ab {{ number_format($freeThreshold / 100, 2, ',', '.') }} €)</span>
                            <a href="{{ route('versand') }}" target="_blank" class="underline hover:text-[var(--theme-color)] ml-1 transition-colors">Details</a>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sternebewertung mit Anker-Sprung zu den Bewertungen (direkt über dem Lagerbestand) --}}
        @php
            $detailRatingAvg = round((float) ($this->product->average_rating ?? 0), 1);
            $detailRatingCount = (int) ($this->product->review_count ?? 0);
        @endphp
        <div class="mb-4">
            <div class="inline-flex items-center gap-2.5">
                <div class="flex items-center">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="w-4 h-4 shrink-0 {{ $i <= round($detailRatingAvg) ? 'text-amber-400' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                        </svg>
                    @endfor
                </div>
                @if($detailRatingCount > 0)
                    <span class="text-xs font-bold text-gray-900">{{ number_format($detailRatingAvg, 1, ',', '.') }}</span>
                @endif
                <button type="button"
                        @click="$dispatch('scroll-to-reviews')"
                        class="text-xs font-semibold text-gray-500 hover:text-[var(--theme-color)] underline underline-offset-2 transition-colors cursor-pointer">
                    @if($detailRatingCount > 0)
                        ({{ $detailRatingCount }} {{ $detailRatingCount === 1 ? 'Bewertung' : 'Bewertungen' }})
                    @else
                        (0 Bewertungen)
                    @endif
                </button>
            </div>
        </div>

        <livewire:shop.system.system-delivery-display :product="$this->product" />

        {{-- Kurz-Beschreibung --}}
        @if($this->product->short_description)
            <div class="text-gray-600 leading-relaxed mb-8 break-words w-full">
                {{ $this->product->short_description }}
            </div>
        @endif

        <hr class="border-gray-100 mb-8 w-full block clear-both">

        {{-- KONFIGURATOR EINBINDUNG --}}
        @if($isTrulyOutOfStock)
            <div class="bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200 p-8 text-center shadow-inner w-full">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white shadow-sm mb-4">
                    <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </div>
                <h3 class="text-xl font-serif font-bold text-gray-900 mb-2">Dieses Seelenstück macht gerade Pause</h3>
                <p class="text-gray-600 text-sm leading-relaxed mb-6">Es tut uns leid, aber dieses Produkt ist momentan vergriffen. Wir sorgen bereits für Nachschub. Schau doch bald wieder vorbei oder entdecke andere Schätze im Shop.</p>
                <a href="{{ route('shop') }}" class="inline-flex items-center justify-center px-6 py-3 rounded-full bg-gray-900 text-white font-bold text-sm hover:bg-black transition-all">Zurück zur Kollektion</a>
            </div>
        @elseif($isDigital)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-xl p-8 transition-all hover:shadow-2xl w-full">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center gap-4">
                        <div class="bg-blue-50 text-blue-600 p-3 rounded-xl"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg></div>
                        <div>
                            <h4 class="font-bold text-gray-900">Digitales Produkt</h4>
                            <p class="text-xs text-gray-500">Nach der Zahlung sofort als Download verfügbar.</p>
                        </div>
                    </div>
                    <livewire:shop.product.product-configurator.product-configurator :product="$product" context="add" />
                </div>
            </div>
        @elseif($isService)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-xl p-8 transition-all hover:shadow-2xl w-full">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center gap-4">
                        <div class="bg-orange-50 text-orange-600 p-3 rounded-xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900">Service buchen</h4>
                            <p class="text-xs text-gray-500">Persönliche Beratung & Dienstleistung</p>
                        </div>
                    </div>
        @elseif(!$this->product->isPersonalizable())
            <div class="bg-white rounded-2xl border border-gray-200 shadow-xl p-8 transition-all hover:shadow-2xl w-full">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center gap-4">
                        <div class="bg-gray-50 text-gray-600 p-3 rounded-xl border border-gray-100">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900">Standard / Fertig Set</h4>
                            <p class="text-xs text-gray-500">Dieses Produkt ist ohne Personalisierung sofort bestellbar.</p>
                        </div>
                    </div>
                    <livewire:shop.product.product-configurator.product-configurator :product="$product" context="add" />
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 shadow-xl overflow-hidden transition-all w-full min-h-[400px] flex flex-col relative">

                @if($showTemplateSelection)
                    {{-- 1. STARTBILDSCHIRM: VORLAGE ODER SELBER MACHEN --}}
                    <div class="flex-1 flex flex-col items-center justify-center p-8 bg-gray-50/50">
                        <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6 text-center">Wie möchtest du starten?</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                            <button type="button" wire:click="openTemplatesList" class="bg-white border border-gray-200 p-6 rounded-3xl shadow-sm hover:shadow-xl hover:border-[var(--theme-color-50)] transition-all group text-left flex flex-col items-center text-center">
                                <div class="w-16 h-16 bg-[var(--theme-color-10)] rounded-full flex items-center justify-center text-[var(--theme-color)] mb-4 group-hover:scale-110 transition-transform">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">Vorlage nutzen</h3>
                                <p class="text-gray-500 text-xs">Wähle aus unseren Vorlagen und passe nur noch die Texte an.</p>
                            </button>

                            <button type="button" wire:click="startCustomConfig" class="bg-white border border-gray-200 p-6 rounded-3xl shadow-sm hover:shadow-xl hover:border-gray-400 transition-all group text-left flex flex-col items-center text-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 mb-4 group-hover:scale-110 transition-transform">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">Selber Konfigurieren</h3>
                                <p class="text-gray-500 text-xs">Starte mit einem leeren Produkt und werde kreativ.</p>
                            </button>
                        </div>

                        @php
                            $tutorialConfig = app(\App\Services\ProductConfiguratorService::class)->getTutorialConfig();
                        @endphp
                        @if(!empty($tutorialConfig['enabled']) && !empty($tutorialConfig['exists']) && !empty($tutorialConfig['url']))
                            <div x-data="{ showTutorial: false }" class="w-full mt-6 pt-6 border-t border-gray-200/80 flex flex-col items-center">
                                <button type="button"
                                        @click="showTutorial = !showTutorial"
                                        class="inline-flex items-center gap-2.5 px-5 py-2.5 rounded-2xl border transition-all text-xs font-bold cursor-pointer select-none"
                                        :class="showTutorial
                                            ? 'bg-white text-gray-900 border-[var(--theme-color)] ring-2 ring-[var(--theme-color-20)] shadow-sm hover:bg-gray-50'
                                            : 'bg-white text-gray-700 border-gray-200 shadow-xs hover:border-[var(--theme-color)] hover:text-gray-900 hover:shadow-md'">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center transition-colors"
                                          :class="showTutorial ? 'bg-[var(--theme-color-20)] text-gray-900' : 'bg-[var(--theme-color-10)] text-[var(--theme-color)]'">
                                        <svg x-show="!showTutorial" class="w-3.5 h-3.5 translate-x-[0.5px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        <svg x-show="showTutorial" x-cloak class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </span>
                                    <span x-text="showTutorial ? 'Tutorial schließen' : 'Tutorial ansehen'">Tutorial ansehen</span>
                                    <span x-show="!showTutorial" class="text-[10px] font-medium text-gray-400 hidden sm:inline">• Kurz erklärt</span>
                                </button>

                                {{-- Lazy-Loaded Video Player: Wird erst beim Klick auf "Tutorial ansehen" geladen --}}
                                <template x-if="showTutorial">
                                    <div class="w-full mt-4 rounded-2xl overflow-hidden border border-gray-200 bg-white shadow-lg transition-all">
                                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200/80 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] animate-pulse"></span>
                                                <span class="text-[11px] font-bold text-gray-800 tracking-wide">So nutzt du den Produktkonfigurator</span>
                                            </div>
                                            <span class="text-[10px] text-gray-500 font-medium">Schritt-für-Schritt Anleitung</span>
                                        </div>
                                        <video src="{{ $tutorialConfig['url'] }}"
                                               controls
                                               autoplay
                                               playsinline
                                               preload="metadata"
                                               class="w-full aspect-video object-contain bg-[#FAF8F5] block">
                                            Dein Browser unterstützt leider keine HTML5-Videos.
                                        </video>
                                    </div>
                                </template>
                            </div>
                        @endif
                    </div>

                @elseif($showTemplatesList)
                    {{-- 2. LISTE DER VORLAGEN --}}
                    <div class="flex-1 p-6 bg-gray-50/50">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-serif font-bold text-gray-900">Design Vorlagen</h2>
                            <button type="button" x-on:click="$wire.$set('showTemplatesList', false); $wire.$set('showTemplateSelection', true)" class="text-xs font-bold text-gray-500 hover:text-gray-900 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Zurück
                            </button>
                        </div>

                        <div class="bg-blue-50/70 border border-blue-200/60 rounded-xl p-4 mb-6 relative overflow-hidden backdrop-blur-sm shadow-sm transition-all hover:shadow-md">
                            <div class="relative flex items-start gap-3">
                                <div class="bg-blue-100/50 text-blue-600 p-2 rounded-lg shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div class="pt-1">
                                    <h4 class="font-bold text-blue-900 text-sm mb-1">Ausschluss vom Widerrufsrecht</h4>
                                    <p class="text-[11px] text-blue-800/90 leading-relaxed">
                                        Bitte beachte, dass unsere Design-Vorlagen als Basis für deine Personalisierung dienen. Da der Artikel nach deiner Bestellung individuell für dich angefertigt wird, ist er – <span class="font-bold">auch wenn du keine Änderungen an der Vorlage vornimmst</span> – als personalisiertes Produkt vom <a href="{{ route('agb') }}#widerruf" target="_blank" class="underline font-bold hover:text-blue-900 transition-colors">Widerrufsrecht ausgeschlossen</a>.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($productTemplates as $tpl)
                                <div wire:click="selectTemplate('{{ $tpl['id'] }}')" class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-lg hover:border-[var(--theme-color)] transition-all cursor-pointer group">
                                    <div class="aspect-square w-full bg-gray-100 relative overflow-hidden flex items-center justify-center">
                                        @if(!empty($tpl['preview_image']))
                                            <img src="{{ \App\Models\Product\ProductTemplate::resolveImageUrl($tpl['preview_image']) }}"
                                                 alt="{{ $tpl['name'] ?? $product->name }} – Design-Vorlage"
                                                 loading="lazy"
                                                 decoding="async"
                                                 @if($tpl['is_ai_generated'] ?? true) data-is-ai-generated="true" @endif
                                                 class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                                            @if($tpl['is_ai_generated'] ?? true)
                                                <span style="font-size: 6.5px; line-height: 1; padding: 1.5px 4px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                                      class="pointer-events-none select-none absolute bottom-1.5 right-1.5 z-10 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                                    KI-generiert
                                                </span>
                                            @endif
                                        @else
                                            <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        @endif
                                        <div class="absolute inset-0 bg-[var(--theme-color)]/0 group-hover:bg-[var(--theme-color-10)] transition-colors"></div>
                                    </div>
                                    <div class="p-4 text-center">
                                        <h3 class="font-bold text-gray-900 text-sm group-hover:text-[var(--theme-color)] transition-colors">{{ $tpl['name'] }}</h3>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                @else
                    {{-- 3. DER EIGENTLICHE KONFIGURATOR --}}
                    <div class="p-0" @cancel-configurator.window="$wire.cancelConfig()">
                        <livewire:shop.product.product-configurator.product-configurator
                            :product="$product"
                            context="add"
                            :initialData="$currentConfig"
                            :key="'conf-'.$product->id.'-'.md5(json_encode($currentConfig))"
                        />
                    </div>
                @endif

            </div>
        @endif

        {{-- USP Icons --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-12 w-full">
            @if($isDigital)
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">🚀</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Sofort verfügbar</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Direkter Download nach Zahlung</p>
                </div>
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">📱</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Überall lesen</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Optimiert für alle Geräte</p>
                </div>
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">🌿</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Nachhaltig</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Kein Versand, keine Verpackung</p>
                </div>
            @elseif($isService)
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">🎓</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Expertenwissen</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Persönliche Beratung vom Profi</p>
                </div>
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">📹</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Online Termin</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Bequem von Zuhause aus</p>
                </div>
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">🤝</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Individuell</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Lösungen für dein Projekt</p>
                </div>
            @else
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">🛡️</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Premium Qualität</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Manuelle Endkontrolle in der Manufaktur</p>
                </div>
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">✨</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Handveredelt</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Persönlich für dich gelasert</p>
                </div>
                <div class="p-6 bg-white border border-gray-100 rounded-2xl text-center hover:shadow-md transition-shadow">
                    <span class="text-2xl mb-2 block">📦</span>
                    <h4 class="text-xs font-bold uppercase tracking-tight text-gray-900">Sorgfältig verpackt</h4>
                    <p class="text-[10px] text-gray-500 mt-1">Liebevoll & sicher gepolstert</p>
                </div>
            @endif
        </div>
    </div>

    @php
        $rawDescriptionMobile = trim((string) $this->product->description);
        $sentencesMobile = preg_split('/(?<=[.!?])\s+/u', $rawDescriptionMobile, 3, PREG_SPLIT_NO_EMPTY);
        $teaserDescriptionMobile = isset($sentencesMobile[0], $sentencesMobile[1])
            ? $sentencesMobile[0] . ' ' . $sentencesMobile[1]
            : \Illuminate\Support\Str::limit($rawDescriptionMobile, 180);
        $validAttributesMobile = array_filter($this->product->attributes ?? [], fn($v) => !empty($v));
    @endphp
    <div class="flex lg:hidden flex-col mt-10 w-full clear-both"
         data-nosnippet
         x-data="{ descOpenMobile: false, propsOpenMobile: false }">
        <div class="border-t border-gray-100 pt-6 w-full block space-y-4">
            @if($rawDescriptionMobile !== '')
                <div class="bg-gray-50/70 rounded-2xl p-5 border border-gray-100 w-full">
                    <button type="button"
                            @click="descOpenMobile = !descOpenMobile"
                            class="w-full flex items-center justify-between gap-4 text-left group cursor-pointer">
                        <h3 class="font-serif text-xl font-bold text-gray-900">Beschreibung</h3>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[var(--theme-color)] shrink-0">
                            <span x-text="descOpenMobile ? 'Weniger anzeigen' : 'Weiterlesen'">Weiterlesen</span>
                            <svg class="w-4 h-4 transition-transform duration-300" :class="descOpenMobile ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>

                    <div x-show="!descOpenMobile"
                         @click="descOpenMobile = true"
                         class="mt-3 text-gray-600 text-sm leading-relaxed break-words line-clamp-3 cursor-pointer">
                        {{ $teaserDescriptionMobile }}
                    </div>

                    <div x-show="descOpenMobile"
                         x-cloak
                         class="mt-4 pt-3 border-t border-gray-200/60 text-gray-600 text-sm leading-relaxed break-words whitespace-pre-line w-full">
                        {!! nl2br(e($rawDescriptionMobile)) !!}
                    </div>
                </div>
            @endif

            @if(!empty($validAttributesMobile))
                <div class="bg-gray-50/70 rounded-2xl border border-gray-100 w-full overflow-hidden">
                    <button type="button"
                            @click="propsOpenMobile = !propsOpenMobile"
                            class="w-full p-5 flex items-center justify-between gap-4 text-left cursor-pointer">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            <h4 class="text-sm font-bold uppercase text-gray-900 tracking-wider">Eigenschaften</h4>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-white text-gray-500 border border-gray-200">
                                {{ count($validAttributesMobile) }} Details
                            </span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 shrink-0">
                            <span x-text="propsOpenMobile ? 'Ausblenden' : 'Anzeigen'">Anzeigen</span>
                            <svg class="w-4 h-4 transition-transform duration-300" :class="propsOpenMobile ? 'rotate-180 text-[var(--theme-color)]' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>

                    <div x-show="propsOpenMobile"
                         x-cloak
                         class="px-5 pb-5 pt-2 border-t border-gray-200/60">
                        <dl class="grid grid-cols-1 gap-y-3.5 text-sm w-full">
                            @foreach($validAttributesMobile as $key => $val)
                                <div class="flex flex-col sm:flex-row sm:justify-between border-b border-gray-200/70 pb-2.5 last:border-0 last:pb-0 w-full">
                                    <dt class="text-gray-500 text-xs mb-0.5 sm:mb-0">{{ $key }}</dt>
                                    <dd class="font-bold text-gray-900 break-words text-left sm:text-right w-full sm:w-1/2">{{ $val }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
            @endif
        </div>

        <div id="kundenbewertungen-mobile" class="mt-6 scroll-mt-24 w-full block clear-both">
            <livewire:shop.product.product-reviews :product="$product" />
        </div>
    </div>

</div>
