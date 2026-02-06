<div class="min-h-screen bg-gray-50 flex flex-col items-center py-12 px-4 sm:px-6 lg:px-8">

    <div class="w-full max-w-4xl">

        {{-- VIEW: ERROR --}}
        @if($viewState === 'error')
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-red-100 text-center animate-fade-in">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Hinweis</h2>
                <p class="text-gray-600 mb-6">{{ $errorMessage }}</p>
                <a href="/" class="text-primary font-bold hover:underline">Zur Startseite</a>
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
                <a href="/" class="text-primary font-bold hover:underline">Zur Startseite</a>
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
                    <livewire:shop.configurator.configurator
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
                <div class="bg-primary/10 p-6 border-b border-primary/20 text-center">
                    <h1 class="text-2xl font-serif font-bold text-gray-900">Ihr persönliches Angebot</h1>
                    <p class="text-primary font-medium mt-1">Nr. {{ $quote->quote_number }}</p>
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
                                    $expressCost = $hasExpress ? (int)shop_setting('express_surcharge', 2500) : 0;
                                    $shippingCost = $quote->shipping_cost_calculated ?? ($quote->shipping_price ?? 0);

                                    // Berechnungen für die Anzeige (Alles Brutto Basis wie in der Mail)
                                    $grossTotal = $quote->gross_total; // Gesamtsumme Brutto aus DB

                                    // Warenwert Brutto = Gesamt Brutto - Versand - Express
                                    $goodsGross = $grossTotal - $shippingCost - $expressCost;
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

                                {{-- Divider --}}
                                <div class="border-t border-gray-200 my-2"></div>

                                {{-- 4. Gesamtsumme (Fett) --}}
                                <div class="flex justify-between items-end mb-2">
                                    <span class="font-bold text-gray-900">Gesamtsumme</span>
                                    <span class="font-bold text-xl text-primary">{{ number_format($grossTotal / 100, 2, ',', '.') }} €</span>
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

                    {{-- Actions Area --}}
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <h3 class="font-bold text-gray-800 mb-4 text-center">Wie möchten Sie fortfahren?</h3>

                        <div class="flex justify-center">

                            {{-- 2. Zur Kasse (Groß) --}}
                            @if($quote->isValid())
                                <button wire:click="proceedToCheckout" wire:loading.attr="disabled" class="w-full sm:w-auto px-10 py-4 bg-gradient-to-r from-primary to-primary-dark text-white rounded-full hover:shadow-lg hover:scale-[1.02] transition font-bold text-base shadow-md flex items-center justify-center gap-2">
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
        @endif
    </div>
</div>
