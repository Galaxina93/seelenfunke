<div>
    <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">

        {{-- Helper für Ländernamen --}}
        @php
            $countries = config('shop.countries', []);
            $getCountryName = fn($code) => $countries[$code] ?? $code;
        @endphp

        {{-- Header mit Back Button --}}
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <a href="{{ route('customer.orders') }}" class="flex items-center text-gray-500 hover:text-gray-900 transition mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Zurück zur Übersicht
                </a>
                <h1 class="text-3xl font-serif font-bold text-gray-900">
                    Bestellung <span class="text-primary">{{ $order->order_number }}</span>
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Bestellt am {{ $order->created_at->format('d.m.Y \u\m H:i') }} Uhr
                </p>
            </div>

            {{-- Aktionen (z.B. Rechnung downloaden Button oben) --}}
            @if($order->invoices->isNotEmpty())
                <a href="{{ route('invoice.download', $order->invoices->first()->id) }}" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                    <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Rechnung PDF
                </a>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- LINKE SPALTE: Artikel & Summen --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Artikel Liste --}}
                <div class="bg-white shadow sm:rounded-xl overflow-hidden border border-gray-100">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="text-lg font-bold text-gray-900 font-serif">Artikelübersicht</h3>
                    </div>

                    <ul role="list" class="divide-y divide-gray-100">
                        @foreach($order->items as $item)
                            <li class="p-6 flex items-start space-x-6">
                                {{-- Produktbild --}}
                                <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                    @if($item->product && $item->product->preview_image_path)
                                        <img src="{{ Storage::url($item->product->preview_image_path) }}" alt="{{ $item->product_name }}" class="h-full w-full object-cover object-center">
                                    @elseif($item->product && !empty($item->product->media_gallery) && isset($item->product->media_gallery[0]))
                                        <img src="{{ Storage::url($item->product->media_gallery[0]['path']) }}" alt="{{ $item->product_name }}" class="h-full w-full object-cover object-center">
                                    @else
                                        <div class="h-full w-full flex items-center justify-center text-gray-300">
                                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex-1">
                                    <div class="flex justify-between">
                                        <h4 class="text-base font-bold text-gray-900">{{ $item->product_name }}</h4>
                                        <p class="text-base font-bold text-gray-900 ml-4">
                                            {{ number_format(($item->total_price / 100), 2, ',', '.') }} €
                                        </p>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">
                                        {{ $item->quantity }}x à {{ number_format(($item->unit_price / 100), 2, ',', '.') }} €
                                    </p>

                                    {{-- Konfigurations-Details --}}
                                    @if(!empty($item->configuration) && is_array($item->configuration))
                                        <div class="mt-3 bg-gray-50 rounded-lg p-3 text-sm border border-gray-100">
                                            <p class="text-xs font-bold text-gray-500 uppercase mb-1">Personalisierung:</p>

                                            {{-- Texte --}}
                                            @if(!empty($item->configuration['texts']))
                                                @foreach($item->configuration['texts'] as $textConfig)
                                                    <div class="flex gap-2 text-gray-700 mb-1">
                                                        <span class="text-gray-400">•</span>
                                                        <span>"{{ $textConfig['text'] ?? '' }}"</span>
                                                        <span class="text-xs text-gray-400">({{ $textConfig['font'] ?? 'Standard' }})</span>
                                                    </div>
                                                @endforeach
                                            @endif

                                            {{-- Logos --}}
                                            @if(!empty($item->configuration['logos']))
                                                <div class="flex gap-2 text-gray-700 mt-1">
                                                    <span class="text-gray-400">•</span>
                                                    <span>{{ count($item->configuration['logos']) }} Logo(s) hochgeladen</span>
                                                </div>
                                            @endif

                                            {{-- Andere Optionen --}}
                                            @foreach($item->configuration as $key => $val)
                                                @if(!in_array($key, ['texts', 'logos', 'preview_image']) && !is_array($val))
                                                    <div class="flex gap-2 text-gray-700">
                                                        <span class="text-gray-400">•</span>
                                                        <span class="capitalize">{{ str_replace('_', ' ', $key) }}: {{ $val }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Summenblock --}}
                    <div class="bg-gray-50 p-6 border-t border-gray-200">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Zwischensumme</span>
                            <span>{{ number_format($order->subtotal_price / 100, 2, ',', '.') }} €</span>
                        </div>

                        {{-- Rabatte --}}
                        @if($order->discount_amount > 0)
                            <div class="flex justify-between text-sm text-green-600 mb-2">
                                <span>Rabatt @if($order->coupon_code) ({{ $order->coupon_code }}) @endif</span>
                                <span>- {{ number_format($order->discount_amount / 100, 2, ',', '.') }} €</span>
                            </div>
                        @endif

                        {{-- Versand --}}
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Versandkosten</span>
                            @if($order->shipping_price == 0)
                                <span class="text-green-600 font-medium">Kostenlos</span>
                            @else
                                <span>{{ number_format($order->shipping_price / 100, 2, ',', '.') }} €</span>
                            @endif
                        </div>

                        {{-- MwSt (Info) --}}
                        <div class="flex justify-between text-xs text-gray-400 mb-4">
                            <span>Enthaltene MwSt.</span>
                            <span>{{ number_format($order->tax_amount / 100, 2, ',', '.') }} €</span>
                        </div>

                        <div class="border-t border-gray-200 pt-4 flex justify-between items-center">
                            <span class="font-bold text-gray-900 text-lg">Gesamtsumme</span>
                            <span class="font-bold text-xl text-primary">{{ number_format($order->total_price / 100, 2, ',', '.') }} €</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RECHTE SPALTE: Info & Dokumente --}}
            <div class="space-y-6">

                {{-- Status Box --}}
                <div class="bg-white shadow sm:rounded-xl p-6 border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider border-b border-gray-100 pb-2">Bestellstatus</h3>
                    <div class="space-y-4">
                        <div>
                            <span class="text-xs text-gray-500 block mb-1">Bearbeitungsstatus</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $order->status_color }}">
                                {{ match($order->status) {
                                    'pending' => 'Eingegangen',
                                    'processing' => 'In Bearbeitung',
                                    'shipped' => 'Versendet',
                                    'completed' => 'Abgeschlossen',
                                    'cancelled' => 'Storniert',
                                    default => ucfirst($order->status)
                                } }}
                            </span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500 block mb-1">Zahlung</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($order->payment_status === 'refunded' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                {{ match($order->payment_status) {
                                    'paid' => 'Bezahlt',
                                    'unpaid' => 'Offen',
                                    'refunded' => 'Erstattet',
                                    default => ucfirst($order->payment_status)
                                } }}
                            </span>
                            <div class="text-xs text-gray-400 mt-1">
                                Methode: {{ ucfirst($order->payment_method ?? 'Standard') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Dokumente / Rechnungen --}}
                <div class="bg-white shadow sm:rounded-xl p-6 border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider border-b border-gray-100 pb-2">Dokumente</h3>

                    @if($order->invoices->isEmpty())
                        <div class="text-center py-4">
                            <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <p class="text-xs text-gray-400 italic">Rechnung wird erstellt...</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($order->invoices as $invoice)
                                <div class="flex items-center justify-between p-3 rounded-lg border {{ $invoice->isCreditNote() ? 'border-red-100 bg-red-50' : 'border-gray-100 bg-gray-50' }} hover:shadow-sm transition">
                                    <div class="flex items-center gap-3">
                                        <div class="{{ $invoice->isCreditNote() ? 'text-red-500' : 'text-gray-400' }}">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold {{ $invoice->isCreditNote() ? 'text-red-700' : 'text-gray-900' }}">
                                                {{ $invoice->invoice_number }}
                                            </p>
                                            <p class="text-xs text-gray-500">{{ $invoice->created_at->format('d.m.Y') }}</p>
                                        </div>
                                    </div>

                                    {{-- Download Route Button --}}
                                    <a href="{{ route('invoice.download', $invoice->id) }}" target="_blank" class="p-2 text-gray-400 hover:text-primary transition" title="Herunterladen">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Adressen --}}
                <div class="bg-white shadow sm:rounded-xl p-6 border border-gray-100">

                    {{-- Rechnungsadresse --}}
                    <div class="mb-6">
                        <h3 class="text-xs font-bold text-gray-500 mb-2 uppercase tracking-wider flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                            Rechnungsanschrift
                        </h3>
                        <address class="text-sm text-gray-700 not-italic leading-relaxed pl-5 border-l-2 border-gray-100">
                            {{ $order->billing_address['first_name'] ?? '' }} {{ $order->billing_address['last_name'] ?? '' }}<br>
                            @if(!empty($order->billing_address['company'])) {{ $order->billing_address['company'] }}<br> @endif
                            {{ $order->billing_address['address'] ?? '' }}<br>
                            {{ $order->billing_address['postal_code'] ?? '' }} {{ $order->billing_address['city'] ?? '' }}<br>
                            {{ $getCountryName($order->billing_address['country'] ?? 'DE') }}
                        </address>
                    </div>

                    {{-- Lieferadresse --}}
                    <div>
                        <h3 class="text-xs font-bold text-gray-500 mb-2 uppercase tracking-wider flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                            Lieferanschrift
                        </h3>
                        @php $ship = $order->shipping_address ?? $order->billing_address; @endphp
                        <address class="text-sm text-gray-700 not-italic leading-relaxed pl-5 border-l-2 border-gray-100">
                            {{ $ship['first_name'] ?? '' }} {{ $ship['last_name'] ?? '' }}<br>
                            @if(!empty($ship['company'])) {{ $ship['company'] }}<br> @endif
                            {{ $ship['address'] ?? '' }}<br>
                            {{ $ship['postal_code'] ?? '' }} {{ $ship['city'] ?? '' }}<br>
                            {{ $getCountryName($ship['country'] ?? 'DE') }}
                        </address>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
