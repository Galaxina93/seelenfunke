<div class="w-full" @scroll-to-config.window="document.getElementById('configurator-start').scrollIntoView({ behavior: 'smooth', block: 'start' })">

    <div id="configurator-start" class="lg:sticky lg:top-24 w-full scroll-mt-24">
        <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 mb-2 leading-tight">
            {{ $this->product->name }}
        </h1>

        <div class="flex items-center gap-4 mb-6">
            {{-- Preis --}}
            <div class="flex flex-col">
                <div class="flex items-baseline gap-3">
                    <span class="text-2xl font-bold text-primary">
                        {{ number_format($this->product->price / 100, 2, ',', '.') }} €
                    </span>
                    @if($this->product->compare_at_price > $this->product->price)
                        <span class="text-lg text-gray-400 line-through decoration-red-400">
                            {{ number_format($this->product->compare_at_price / 100, 2, ',', '.') }} €
                        </span>
                    @endif
                </div>

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
                            inkl. MwSt. <span class="italic">(Steuerbefreit gem. § 19 UStG)</span>
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
                            <a href="{{ route('versand') }}" target="_blank" class="underline hover:text-primary ml-1 transition-colors">Details</a>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Lagerbestand & SKU --}}
        <div class="flex items-center gap-4 mb-8 mt-4 text-sm w-full flex-wrap">
            @if($this->product->track_quantity)
                @if($this->product->quantity > 0)
                    <span class="inline-flex items-center gap-1.5 text-green-700 font-medium">
                        <span class="relative flex h-2.5 w-2.5">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                        </span>
                        {{ $isService ? 'Plätze verfügbar' : 'Auf Lager, sofort lieferbar' }}
                    </span>
                @elseif($this->product->continue_selling_when_out_of_stock)
                    <span class="inline-flex items-center gap-1.5 text-amber-600 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ $isService ? 'Warteliste verfügbar' : 'Verfügbar auf Nachbestellung' }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-red-600 font-bold bg-red-50 px-3 py-1 rounded-full border border-red-100 animate-pulse">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        {{ $isService ? 'Derzeit ausgebucht' : 'Derzeit leider vergriffen' }}
                    </span>
                @endif
            @endif

            @if($this->product->sku)
                <span class="text-gray-400 border-l border-gray-200 pl-4 ml-2">Art.-Nr.: {{ $this->product->sku }}</span>
            @endif
        </div>

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
                    @livewire('shop.configurator.configurator', ['product' => $product, 'context' => 'add'])
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
                    @livewire('shop.configurator.configurator', ['product' => $product, 'context' => 'add'])
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 shadow-xl overflow-hidden transition-all hover:shadow-2xl w-full min-h-[400px] flex flex-col relative">

                @if($showTemplateSelection)
                    {{-- 1. STARTBILDSCHIRM: VORLAGE ODER SELBER MACHEN --}}
                    <div class="flex-1 flex flex-col items-center justify-center p-8 bg-gray-50/50">
                        <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6 text-center">Wie möchtest du starten?</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                            <button type="button" wire:click="openTemplatesList" class="bg-white border border-gray-200 p-6 rounded-3xl shadow-sm hover:shadow-xl hover:border-primary/50 transition-all group text-left flex flex-col items-center text-center">
                                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center text-primary mb-4 group-hover:scale-110 transition-transform">
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
                    </div>

                @elseif($showTemplatesList)
                    {{-- 2. LISTE DER VORLAGEN --}}
                    <div class="flex-1 p-6 bg-gray-50/50">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-serif font-bold text-gray-900">Design Vorlagen</h2>
                            <button type="button" wire:click="$set('showTemplatesList', false); $set('showTemplateSelection', true)" class="text-xs font-bold text-gray-500 hover:text-gray-900 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                Zurück
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($productTemplates as $tpl)
                                <div wire:click="selectTemplate('{{ $tpl['id'] }}')" class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-lg hover:border-primary transition-all cursor-pointer group">
                                    <div class="h-40 bg-gray-100 relative overflow-hidden flex items-center justify-center">
                                        @if(!empty($tpl['preview_image']))
                                            <img src="{{ asset('storage/'.$tpl['preview_image']) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        @else
                                            <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        @endif
                                        <div class="absolute inset-0 bg-primary/0 group-hover:bg-primary/10 transition-colors"></div>
                                    </div>
                                    <div class="p-4 text-center">
                                        <h3 class="font-bold text-gray-900 text-sm group-hover:text-primary transition-colors">{{ $tpl['name'] }}</h3>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                @else
                    {{-- 3. DER EIGENTLICHE KONFIGURATOR --}}
                    @if(!empty($productTemplates))
                        <div class="bg-gray-50 px-6 py-3 border-b flex justify-between items-center shrink-0">
                            <span class="text-sm font-medium text-gray-600">Gestaltung</span>
                            <button type="button" wire:click="cancelConfig" class="text-xs text-gray-500 hover:text-red-500 transition-colors">
                                Andere Vorlage wählen
                            </button>
                        </div>
                    @endif

                    <div class="p-0">
                        @livewire('shop.configurator.configurator', [
                            'product' => $product,
                            'context' => 'add',
                            'initialData' => $currentConfig
                        ], key('conf-'.$product->id.'-'.microtime(true)))
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

    {{-- ========================================== --}}
    {{-- 3. PRODUKTDETAILS & BEWERTUNGEN (NUR MOBILE) --}}
    {{-- Diese Blöcke erscheinen exakt unterhalb des Konfigurators auf Handys --}}
    {{-- ========================================== --}}
    <div class="flex lg:hidden flex-col mt-12 w-full clear-both">
        <div class="border-t border-gray-100 pt-8 w-full block">
            <h3 class="font-serif text-2xl font-bold text-gray-900 mb-4">Beschreibung</h3>
            <div class="text-gray-600 text-sm leading-relaxed break-words whitespace-pre-line w-full">
                {!! nl2br(e($this->product->description)) !!}
            </div>

            @if(!empty($this->product->attributes))
                <div class="mt-10 bg-gray-50 rounded-2xl p-6 sm:p-8 border border-gray-100 w-full">
                    <h4 class="text-sm font-bold uppercase text-gray-900 mb-6 tracking-wider flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        Eigenschaften
                    </h4>
                    <dl class="grid grid-cols-1 gap-y-4 text-sm w-full">
                        @foreach($this->product->attributes as $key => $val)
                            @if(!empty($val))
                                <div class="flex flex-col sm:flex-row sm:justify-between border-b border-gray-200 pb-3 last:border-0 last:pb-0 w-full">
                                    <dt class="text-gray-500 mb-1 sm:mb-0">{{ $key }}</dt>
                                    <dd class="font-bold text-gray-900 break-words text-left sm:text-right w-full sm:w-1/2">{{ $val }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>

        {{-- Kundenbewertungen (Mobile Ansicht) --}}
        <div id="kundenbewertungen-mobile" class="mt-16 sm:mt-24 scroll-mt-24 w-full block clear-both">
            <livewire:shop.product.product-reviews :product="$product" />
        </div>
    </div>

</div>
