<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="min-h-screen bg-gray-50 flex flex-col items-center py-12 px-4 sm:px-6 lg:px-8">

    <div class="w-full max-w-4xl">

        {{-- VIEW: ERROR --}}
        @if($viewState === 'error')
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-red-100 text-center animate-fade-in">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Hinweis</h2>
                <p class="text-gray-600 mb-6">{{ $errorMessage }}</p>
                <a href="/" class="text-[var(--theme-color)] font-bold hover:underline">Zur Startseite</a>
            </div>

            {{-- VIEW: SUCCESS REJECTED --}}
        @elseif($viewState === 'success_rejected')
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 text-center animate-fade-in">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-6">
                    <svg class="h-8 w-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Angebot abgelehnt</h2>
                <p class="text-gray-600 mb-6">
                    Sie haben das Angebot abgelehnt. Wir danken Ihnen dennoch für Ihr Interesse.
                </p>
                <a href="/" class="text-[var(--theme-color)] font-bold hover:underline">Zur Startseite</a>
            </div>

            {{-- VIEW: EDITOR (CONFIGURATOR) --}}
        @elseif($viewState === 'editor' && $editingItem)
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 animate-fade-in-up">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="font-bold text-lg text-gray-800">Position bearbeiten: {{ $editingItem->product_name ?? 'Produkt' }}</h2>
                    <button wire:click="cancelEdit" class="text-sm text-gray-500 hover:text-gray-900 flex items-center gap-1 transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Abbrechen
                    </button>
                </div>

                <div class="p-0">
                    {{--
                        Wir nutzen den Configurator im 'calculator' Context.
                        Dieser emittet 'calculator-save', was wir in QuoteAcceptance.php abfangen.
                    --}}
                    <livewire:shop.product.product-configurator.product-configurator
                        :product="$editingItem->product"
                        :initialData="$editingItem->configuration"
                        :qty="$editingItem->quantity"
                        context="calculator"
                        :key="'quote-edit-'.$editingItem->id"
                    />
                </div>
            </div>

            {{-- VIEW: DASHBOARD (MAIN) --}}
        @else
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 animate-fade-in">

                {{-- Header --}}
                <div class="bg-[var(--theme-color-10)] p-6 border-b border-[var(--theme-color-20)] text-center">
                    <h1 class="text-2xl font-serif font-bold text-gray-900">Ihr persönliches Angebot</h1>
                    <p class="text-[var(--theme-color)] font-medium mt-1">Nr. {{ $quote->quote_number }}</p>
                </div>

                {{-- Status Bar --}}
                <div class="bg-gray-50 px-6 py-3 border-b border-gray-100 flex justify-between items-center text-sm">
                    <span class="text-gray-500">Erstellt am {{ $quote->created_at->format('d.m.Y') }}</span>
                    @if($quote->expires_at->isPast())
                        <span class="text-red-600 font-bold flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Abgelaufen
                        </span>
                    @else
                        <span class="text-green-600 font-bold flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Gültig bis {{ $quote->expires_at->format('d.m.Y') }}
                        </span>
                    @endif
                </div>

                <div class="p-6 sm:p-10 space-y-8">

                    {{-- Intro --}}
                    <div class="text-center space-y-2">
                        <p class="text-lg text-gray-700">
                            Hallo <strong>{{ $quote->first_name }} {{ $quote->last_name }}</strong>,
                        </p>
                        <p class="text-gray-600">
                            hier können Sie Ihr Angebot prüfen, anpassen oder direkt zur Zahlung übergehen.
                        </p>
                    </div>

                    @if (session()->has('success'))
                        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative text-center text-sm flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ session('success') }}
                        </div>
                    @endif

                    @php
                        $hasStockWarning = false;
                        foreach($quote->items as $i) {
                            if($i->product && $i->product->track_quantity && $i->quantity > $i->product->quantity) {
                                $hasStockWarning = true;
                                break;
                            }
                        }
                    @endphp

                    @if($hasStockWarning)
                        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-5 py-4 rounded-xl relative text-sm flex items-start gap-4 mb-4 shadow-sm animate-fade-in">
                            <svg class="w-6 h-6 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div>
                                <h4 class="font-bold text-amber-900 mb-1">Hinweis zur Produktionszeit</h4>
                                <p class="text-amber-800/90 leading-relaxed">
                                    Einer oder mehrere Artikel überschreiten unseren sofortigen Lagerbestand. Dies ist üblich – wir fertigen die fehlenden Stücke speziell für Sie an oder ordern Hersteller-Kontingente nach. Bitte beachten Sie die konkreten Wiederbeschaffungszeiten (ETA) direkt bei den betroffenen Positionen unten.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Items List --}}
                    <div class="border rounded-xl overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3">Produkt</th>
                                <th class="px-4 py-3 text-center">Menge</th>
                                <th class="px-4 py-3 text-right">Summe</th>
                                <th class="px-4 py-3 text-right"></th> {{-- Action Col --}}
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($quote->items as $item)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-4 py-3 align-middle">
                                        <div class="font-bold text-gray-900">{{ $item->product_name }}</div>
                                        @if(!empty($item->configuration['text']))
                                            <div class="text-xs text-gray-500 mt-0.5">Gravur: "{{ Str::limit($item->configuration['text'], 30) }}"</div>
                                        @endif
                                        @php
                                            $pStock = $item->product ? $item->product->quantity : 0;
                                            $pTracked = $item->product ? $item->product->track_quantity : false;
                                            $etaText = '';
                                            if ($pTracked && $item->quantity > $pStock && $item->product) {
                                                $supplier = $item->product->supplier;
                                                if ($supplier && $supplier->lead_time_land_days > 0) {
                                                    $etaDays = $supplier->lead_time_land_days + 2; // + 2 Tage Puffer
                                                    $etaText = "(ca. {$etaDays} Werktage durch Lieferant {$supplier->name})";
                                                } else {
                                                    $etaText = "(ca. 10-14 Werktage)";
                                                }
                                            }
                                        @endphp
                                        @if($pTracked && $item->quantity > $pStock)
                                            <div class="mt-2 inline-flex items-center gap-1.5 px-2 py-1.5 rounded-md bg-amber-100/80 text-amber-800 border border-amber-200/50 text-[10px] uppercase font-bold tracking-wider">
                                                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <span>{{ max(0, $pStock) }} ab Lager / {{ $item->quantity - max(0, $pStock) }} Nachproduktion <span class="bg-amber-200/60 px-1.5 py-0.5 rounded text-amber-900 ml-1 tracking-normal">{{ $etaText }}</span></span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center align-middle font-bold">{{ $item->quantity }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap align-middle font-mono">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</td>
                                    <td class="px-4 py-3 text-right align-middle">
                                        @if($quote->isValid())
                                            <button wire:click="editItem('{{ $item->id }}')" class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-lg transition" title="Bearbeiten">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        {{-- CALCULATION SUMMARY (Angepasst an Kundenansicht Brutto) --}}
                        <div class="bg-gray-50 p-6 border-t border-gray-200">
                            <div class="flex flex-col gap-2 max-w-xs ml-auto">

                                @php
                                    // Shop Settings laden
                                    $isSmallBusiness = (bool)shop_setting('is_small_business', false);
                                    $taxRate = (float)shop_setting('default_tax_rate', 19.0);

                                    $hasExpress = $quote->is_express;
                                    $formatted = $quote->toFormattedArray();
                                    $expressStr = $formatted['express_price'] ?? '0,00';
                                    $expressCost = (int)round((float)str_replace(['.', ','], ['', '.'], $expressStr) * 100);
                                    $shippingCost = $quote->shipping_cost_calculated ?? ($quote->shipping_price ?? 0);

                                    // Berechnungen für die Anzeige (Alles Brutto Basis wie in der Mail)
                                    $grossTotal = $quote->gross_total; // Gesamtsumme Brutto aus DB

                                    // Warenwert Brutto = Gesamt Brutto - Versand - Express (ohne Gutschein, reiner Artikelwert)
                                    $goodsGross = $grossTotal - $shippingCost - $expressCost;

                                    // Gutscheinwert berechnen
                                    $discountAmount = 0;
                                    if ($activeCoupon) {
                                        if ($activeCoupon['type'] === 'fixed') {
                                            $discountAmount = $activeCoupon['value'];
                                        } elseif ($activeCoupon['type'] === 'percent') {
                                            $discountAmount = (int) round($goodsGross * ($activeCoupon['value'] / 100));
                                        }
                                        $discountAmount = min($discountAmount, $goodsGross);
                                    }

                                    // Neue Gesamtsumme
                                    $displayGrossTotal = max(0, $grossTotal - $discountAmount);
                                @endphp

                                {{-- 1. Warenwert (Brutto) --}}
                                <div class="flex justify-between text-sm text-gray-600">
                                    <span>Warenwert (Brutto)</span>
                                    <span>{{ number_format($goodsGross / 100, 2, ',', '.') }} €</span>
                                </div>

                                {{-- 2. Express (Brutto) --}}
                                @if($hasExpress)
                                    <div class="flex justify-between text-sm text-blue-600 font-medium">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            <span>Express-Service</span>
                                        </div>
                                        <span>{{ number_format($expressCost / 100, 2, ',', '.') }} €</span>
                                    </div>
                                @endif

                                {{-- 3. Versandkosten (Brutto) --}}
                                <div class="flex justify-between text-sm text-gray-600">
                                    <span>Versand & Verpackung</span>
                                    @if($shippingCost > 0)
                                        <span>{{ number_format($shippingCost / 100, 2, ',', '.') }} €</span>
                                    @else
                                        <span class="text-green-600 font-bold uppercase text-[10px]">Kostenlos</span>
                                    @endif
                                </div>

                                {{-- Gutschein Rabatt-Zeile --}}
                                @if($activeCoupon)
                                    <div class="flex justify-between text-sm text-green-600 font-bold mt-1 bg-green-50 p-1.5 rounded-md -mx-1.5 px-1.5">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <span>Gutschein ({{ $activeCoupon['code'] }})</span>
                                        </div>
                                        <span>- {{ number_format($discountAmount / 100, 2, ',', '.') }} €</span>
                                    </div>
                                @endif

                                {{-- Divider --}}
                                <div class="border-t border-gray-200 my-2"></div>

                                {{-- 4. Gesamtsumme (Fett) --}}
                                <div class="flex justify-between items-end mb-2">
                                    <span class="font-bold text-gray-900">Gesamtsumme</span>
                                    <span class="font-bold text-xl text-[var(--theme-color)]">{{ number_format($displayGrossTotal / 100, 2, ',', '.') }} €</span>
                                </div>

                                {{-- 5. Steuer-Hinweis (Klein & Dezent) --}}
                                <div class="text-right text-[11px] text-gray-400 italic leading-tight">
                                    <div class="flex justify-between gap-4">
                                        <span>Nettowarenwert:</span>
                                        <span>{{ number_format($quote->net_total / 100, 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between gap-4 mt-0.5">
                                        @if(!$isSmallBusiness)
                                            <span>Enthaltene MwSt. ({{ number_format($taxRate, 0) }}%):</span>
                                            <span>{{ number_format($quote->tax_total / 100, 2, ',', '.') }} €</span>
                                        @else
                                            <span>Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Gutschein & Aktionen --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        {{-- Gutschein Area --}}
                        <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm flex flex-col justify-center">
                            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v1m4.5 4.5l-4.5 4.5M12 20a8 8 0 100-16 8 8 0 000 16z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11l-3 3-3-3" /></svg>
                                Haben Sie einen Gutscheincode?
                            </h3>

                            @if($activeCoupon)
                                <div class="flex items-center justify-between bg-green-50 border border-green-200 rounded-lg p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-green-100 p-2 rounded-full">
                                            <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-green-800">{{ $activeCoupon['code'] }} aktiv</p>
                                            <p class="text-xs text-green-600">Gutschein erfolgreich angewendet.</p>
                                        </div>
                                    </div>
                                    <button wire:click="removeCoupon" class="text-xs text-red-500 hover:text-red-700 hover:underline transition">Entfernen</button>
                                </div>
                            @elseif($quote->isValid())
                                @if (session()->has('coupon_success'))
                                    <div class="mb-3 p-3 bg-green-50 text-green-700 text-sm rounded-lg border border-green-200 flex items-center gap-2">
                                        {{ session('coupon_success') }}
                                    </div>
                                @endif
                                
                                <form wire:submit.prevent="applyCoupon" class="flex gap-2">
                                    <div class="flex-1 relative">
                                        <input wire:model="couponCodeInput" type="text" placeholder="Code eingeben" 
                                               class="w-full text-base border-gray-300 rounded-lg shadow-sm focus:border-[var(--theme-color)] focus:ring focus:ring-[var(--theme-color)] focus:ring-opacity-50 uppercase placeholder:normal-case font-mono @error('couponCodeInput') border-red-500 @enderror">
                                        @error('couponCodeInput')
                                            <p class="text-red-500 text-xs mt-1 absolute -bottom-5 left-0">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button type="submit" wire:loading.attr="disabled" class="bg-gray-900 text-white px-5 py-2.5 rounded-lg hover:bg-gray-800 transition font-bold text-sm whitespace-nowrap">
                                        Einlösen
                                    </button>
                                </form>
                                <p class="text-[11px] text-amber-600/80 mt-3 flex items-center justify-center gap-1.5 font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Tipp: Aktuelle Gutscheine finden Sie rechts in der Aktionsleiste!
                                </p>
                            @else
                                <div class="text-sm border border-gray-200 bg-gray-50 text-gray-500 p-4 rounded-lg">
                                    Gutscheine können nur für noch gültige Angebote eingelöst werden.
                                </div>
                            @endif
                        </div>

                        {{-- Actions Area --}}
                        <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm flex flex-col justify-center">
                        <h3 class="font-bold text-gray-800 mb-4 text-center">Wie möchten Sie fortfahren?</h3>

                        <div class="flex justify-center">

                            {{-- 2. Zur Kasse (Groß) --}}
                            @if($quote->isValid())
                                <button wire:click="proceedToCheckout" wire:loading.attr="disabled" class="w-full sm:w-auto px-10 py-4 bg-gradient-to-r from-[var(--theme-color)] to-[var(--theme-color)]-dark text-white rounded-full hover:shadow-lg hover:scale-[1.02] transition font-bold text-base shadow-md flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    <span>Zur Kasse & Bezahlen</span>
                                </button>
                            @else
                                <button disabled class="px-6 py-3 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed font-bold text-sm">
                                    Angebot nicht mehr gültig
                                </button>
                            @endif

                        </div>

                        {{-- 3. Ablehnen --}}
                        @if($quote->isValid())
                            <div class="mt-6 text-center">
                                <button wire:click="rejectQuote" wire:confirm="Möchten Sie das Angebot wirklich ablehnen?" class="text-xs text-gray-400 hover:text-red-500 hover:underline transition">
                                    Kein Interesse? Angebot ablehnen.
                                </button>
                            </div>
                        @endif
                        </div>
                    </div>

                </div>
            </div>
        @endif
    </div>
</div>
