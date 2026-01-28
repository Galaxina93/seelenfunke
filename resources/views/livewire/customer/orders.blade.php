<div class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- ========================================= --}}
        {{-- ANSICHT: DETAIL (Einzelne Bestellung)     --}}
        {{-- ========================================= --}}
        @if($viewMode === 'detail' && $order)

            <div class="space-y-8">
                {{-- HEADER & NAVIGATION --}}
                <div class="flex items-center justify-between">
                    <button wire:click="resetView" class="flex items-center text-gray-500 hover:text-gray-900 transition font-medium group">
                        <span class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center mr-2 group-hover:border-gray-400 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </span>
                        Zurück zur Übersicht
                    </button>
                    <div class="text-right">
                        <h1 class="text-2xl font-serif font-bold text-gray-900">Bestellung {{ $order->order_number }}</h1>
                        <p class="text-sm text-gray-500">
                            Bestellt am <time datetime="{{ $order->created_at }}">{{ $order->created_at->format('d.m.Y') }}</time>
                            <span class="mx-1">·</span>
                            <span class="font-medium text-gray-900">
                                @php
                                    // Status Übersetzung für den Header
                                    $statusLabel = match($order->status) {
                                        'pending' => 'Wartend',
                                        'processing' => 'In Bearbeitung',
                                        'shipped' => 'Versendet',
                                        'completed' => 'Abgeschlossen',
                                        'cancelled' => 'Storniert',
                                        'refunded' => 'Erstattet',
                                        default => 'Unbekannt'
                                    };
                                @endphp
                                {{ $statusLabel }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">

                    {{-- PRODUKTLISTE & STATUS --}}
                    <div class="p-6 sm:p-10 space-y-10">

                        {{-- FORTSCHRITTSBALKEN (Progress Bar) --}}
                        <div class="relative">
                            @php
                                // Breite des Balkens basierend auf Status
                                $progress = match($order->status) {
                                    'pending' => '15%',      // Schritt 1: Wartend
                                    'processing' => '50%',   // Schritt 2: In Bearbeitung
                                    'shipped' => '80%',      // Schritt 3: Versendet
                                    'completed' => '100%',   // Schritt 4: Abgeschlossen
                                    'cancelled', 'refunded' => '100%', // Bei Abbruch voll, aber rot
                                    default => '0%'
                                };

                                // Farbe des Balkens
                                $barColor = match($order->status) {
                                    'cancelled', 'refunded' => 'bg-red-500', // Rot bei Abbruch
                                    'completed' => 'bg-green-500',           // Grün bei Erfolg
                                    default => 'bg-primary'                  // Gold/Primary bei laufendem Prozess
                                };

                                // Logik für Text-Highlighting unter dem Balken
                                // Wir prüfen, ob der aktuelle Status "weiter" ist als der Label-Schritt
                                $isProcessing = in_array($order->status, ['processing', 'shipped', 'completed']);
                                $isShipped = in_array($order->status, ['shipped', 'completed']);
                                $isCompleted = $order->status === 'completed';
                                $isCancelled = in_array($order->status, ['cancelled', 'refunded']);
                            @endphp

                            {{-- Der Balken --}}
                            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-100">
                                <div style="width: {{ $progress }}" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center {{ $barColor }} transition-all duration-500 ease-out"></div>
                            </div>

                            {{-- Die Labels unter dem Balken --}}
                            @if(!$isCancelled)
                                <div class="flex justify-between text-xs font-medium text-gray-400">
                                    <span class="text-primary font-bold">Bestellung eingegangen</span>
                                    <span class="{{ $isProcessing ? 'text-primary font-bold' : '' }}">In Bearbeitung</span>
                                    <span class="{{ $isShipped ? 'text-primary font-bold' : '' }}">Versendet</span>
                                    <span class="{{ $isCompleted ? 'text-green-600 font-bold' : '' }}">Abgeschlossen</span>
                                </div>
                            @else
                                {{-- NEU: Storno Grund Anzeige --}}
                                <div class="mt-6 bg-red-50 border border-red-100 rounded-lg p-4 text-center">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <span class="text-red-600 font-bold text-sm uppercase tracking-wider flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Auftrag storniert
                                        </span>

                                        @if($order->cancellation_reason)
                                            <p class="text-gray-700 text-sm max-w-lg">
                                                <span class="font-semibold">Grund:</span> {{ $order->cancellation_reason }}
                                            </p>
                                        @else
                                            <p class="text-gray-500 text-xs italic">Kein spezifischer Grund angegeben.</p>
                                        @endif

                                        <p class="text-xs text-gray-500 mt-2">
                                            Bei Fragen wenden Sie sich bitte an unseren Support.
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Artikel Liste --}}
                        <div class="space-y-8">
                            <h2 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4">Gekaufte Artikel</h2>

                            @foreach($order->items as $item)
                                <div class="flex flex-col sm:flex-row gap-6">
                                    {{-- Produktbild --}}
                                    <div class="shrink-0">
                                        @if(isset($item->product->media_gallery[0]))
                                            <img src="{{ asset('storage/'.$item->product->media_gallery[0]['path']) }}" alt="{{ $item->product_name }}" class="w-24 h-24 object-cover rounded-lg bg-gray-100 border border-gray-100">
                                        @else
                                            <div class="w-24 h-24 rounded-lg bg-gray-100 border border-gray-100 flex items-center justify-center text-gray-300">
                                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Details --}}
                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <h3 class="font-bold text-gray-900 text-lg">
                                                <a href="{{ route('product.show', $item->product->slug ?? '#') }}" class="hover:text-primary transition">
                                                    {{ $item->product_name }}
                                                </a>
                                            </h3>
                                            <p class="text-gray-500 text-sm mt-1">{{ number_format($item->unit_price / 100, 2, ',', '.') }} € pro Stück</p>

                                            @if(!empty($item->configuration))
                                                <div class="mt-3 bg-gray-50 rounded-md p-3 text-xs text-gray-600 space-y-1">
                                                    <p class="font-bold text-gray-800">Konfiguration:</p>
                                                    @if(isset($item->configuration['text']))
                                                        <p>Gravur: "{{ $item->configuration['text'] }}"</p>
                                                    @endif
                                                    @if(isset($item->configuration['font']))
                                                        <p>Schriftart: {{ $item->configuration['font'] }}</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Logistik Info (Status abhängig) --}}
                                        <div class="sm:text-right">
                                            <div class="text-sm font-medium text-gray-900">Lieferstatus</div>
                                            <p class="text-sm text-gray-500 mt-1">
                                                @if($order->status === 'shipped' || $order->status === 'completed')
                                                    Versendet mit DHL<br>
                                                    <a href="#" class="text-primary hover:underline">Sendung verfolgen &rarr;</a>
                                                @elseif($order->status === 'cancelled')
                                                    <span class="text-red-500">Storniert</span>
                                                @else
                                                    Noch nicht versendet
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Preis Gesamt für Item --}}
                                    <div class="text-right sm:w-32">
                                        <p class="font-bold text-gray-900">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</p>
                                        <p class="text-xs text-gray-400">Menge: {{ $item->quantity }}</p>
                                    </div>
                                </div>
                                @if(!$loop->last) <div class="border-b border-gray-100"></div> @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- FOOTER AREA: Abrechnung & Info --}}
                    <div class="bg-gray-50 border-t border-gray-200 p-6 sm:p-10 grid grid-cols-1 lg:grid-cols-2 gap-10">

                        {{-- LINKE SPALTE: Adressen & Zahlung --}}
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm mb-3">Rechnungsadresse</h4>
                                    <address class="not-italic text-sm text-gray-600 leading-relaxed">
                                        {{ $order->billing_address['first_name'] }} {{ $order->billing_address['last_name'] }}<br>
                                        @if(!empty($order->billing_address['company'])) {{ $order->billing_address['company'] }}<br> @endif
                                        {{ $order->billing_address['address'] }}<br>
                                        {{ $order->billing_address['postal_code'] }} {{ $order->billing_address['city'] }}<br>
                                        {{ $order->billing_address['country'] }}
                                    </address>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm mb-3">Zahlungsinformationen</h4>
                                    <div class="flex items-center gap-3">
                                        {{-- Icon Platzhalter --}}
                                        <div class="h-8 w-12 bg-white border border-gray-200 rounded flex items-center justify-center">
                                            <svg class="h-5 w-auto text-gray-600" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm0 4h16V6H4v2zm0 2v8h16v-8H4z"/></svg>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <p class="font-medium">
                                                @if($order->payment_method === 'paypal') PayPal
                                                @elseif($order->payment_method === 'stripe') Kreditkarte / Stripe
                                                @else Online Zahlung @endif
                                            </p>
                                            <p class="text-xs">
                                                {{ $order->payment_status === 'paid' ? 'Bezahlt' : ucfirst($order->payment_status) }}
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Rechnungsdownload Button --}}
                                    @if($order->invoices->isNotEmpty())
                                        <div class="mt-4">
                                            @foreach($order->invoices as $inv)
                                                <a href="{{ route('invoice.download', $inv->id) }}" class="inline-flex items-center text-sm font-bold text-primary hover:text-primary-dark mb-2">
                                                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    Rechnung {{ $inv->invoice_number }} laden
                                                </a><br>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- RECHTE SPALTE: Detaillierte Kostenaufstellung --}}
                        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                            <h4 class="font-bold text-gray-900 text-sm mb-4">Kostenübersicht</h4>

                            <dl class="space-y-3 text-sm">
                                {{-- 1. Warenwert --}}
                                <div class="flex justify-between text-gray-600">
                                    <dt>Warenwert</dt>
                                    <dd>{{ number_format($order->subtotal_price / 100, 2, ',', '.') }} €</dd>
                                </div>

                                {{-- 2. Rabatte --}}
                                @if($order->volume_discount > 0)
                                    <div class="flex justify-between text-green-600">
                                        <dt>Mengenrabatt</dt>
                                        <dd>-{{ number_format($order->volume_discount / 100, 2, ',', '.') }} €</dd>
                                    </div>
                                @endif

                                @if($order->discount_amount > 0)
                                    <div class="flex justify-between text-green-600">
                                        <dt>Gutschein ({{ $order->coupon_code }})</dt>
                                        <dd>-{{ number_format($order->discount_amount / 100, 2, ',', '.') }} €</dd>
                                    </div>
                                @endif

                                {{-- Zwischensumme nach Rabatt --}}
                                <div class="border-t border-gray-100 my-2 pt-2 flex justify-between text-gray-600">
                                    <dt>Zwischensumme</dt>
                                    <dd>{{ number_format(($order->subtotal_price - $order->volume_discount - $order->discount_amount) / 100, 2, ',', '.') }} €</dd>
                                </div>

                                {{-- 3. Versand --}}
                                <div class="flex justify-between text-gray-600">
                                    <dt>Versand & Verpackung</dt>
                                    <dd>
                                        @if($order->shipping_price == 0)
                                            <span class="text-green-600 font-bold">Kostenlos</span>
                                        @else
                                            {{ number_format($order->shipping_price / 100, 2, ',', '.') }} €
                                        @endif
                                    </dd>
                                </div>

                                {{-- 4. Steuer Info (Nur Anzeige) --}}
                                <div class="flex justify-between text-gray-400 text-xs italic">
                                    <dt>Enthaltene MwSt. (19%)</dt>
                                    <dd>{{ number_format($order->tax_amount / 100, 2, ',', '.') }} €</dd>
                                </div>

                                {{-- 5. Gesamtsumme --}}
                                <div class="border-t border-gray-200 pt-4 flex justify-between items-center mt-2">
                                    <dt class="font-bold text-gray-900 text-base">Gesamtsumme</dt>
                                    <dd class="font-bold text-primary text-xl">{{ number_format($order->total_price / 100, 2, ',', '.') }} €</dd>
                                </div>
                            </dl>
                        </div>

                    </div>
                </div>
            </div>


            {{-- ========================================= --}}
            {{-- ANSICHT: LISTE (Alle Bestellungen)        --}}
            {{-- ========================================= --}}
        @else

            <div class="sm:flex sm:items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-serif font-bold text-gray-900">Meine Bestellungen</h1>
                    <p class="mt-2 text-sm text-gray-600">Verfolge deine Lieferungen oder sieh dir alte Bestellungen an.</p>
                </div>
                <div class="mt-4 sm:mt-0 relative">
                    <input wire:model.live="search" type="text" placeholder="Suche nach Nr..." class="rounded-full border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm px-4 py-2 w-full sm:w-64 pl-10">
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <ul class="divide-y divide-gray-100">
                    @forelse($orders as $o)
                        <li class="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between flex-wrap gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="bg-gray-100 p-2 rounded-lg text-gray-500">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-900">
                                            {{ $o->order_number }}
                                            <span class="text-xs font-normal text-gray-500 ml-1">· {{ $o->created_at->format('d.m.Y') }}</span>
                                        </p>
                                        <p class="text-sm text-gray-500 mt-0.5">
                                            {{ number_format($o->total_price / 100, 2, ',', '.') }} € · {{ $o->items->count() }} Artikel
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 ml-auto">
                                    {{-- Status Badge Deutsch für Liste --}}
                                    @php
                                        $statusClass = match($o->status) {
                                            'completed' => 'bg-green-100 text-green-800',
                                            'shipped' => 'bg-blue-100 text-blue-800',
                                            'processing' => 'bg-blue-50 text-blue-600',
                                            'cancelled', 'refunded' => 'bg-red-100 text-red-800',
                                            default => 'bg-yellow-100 text-yellow-800' // Pending
                                        };
                                        $statusText = match($o->status) {
                                            'pending' => 'Wartend',
                                            'processing' => 'In Bearbeitung',
                                            'shipped' => 'Versendet',
                                            'completed' => 'Abgeschlossen',
                                            'cancelled' => 'Storniert',
                                            'refunded' => 'Erstattet',
                                            default => 'Unbekannt'
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>

                                    <button wire:click="showOrder('{{ $o->id }}')" class="text-gray-400 hover:text-primary transition">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="p-12 text-center text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            <p>Noch keine Bestellungen vorhanden.</p>
                            <a href="{{ route('shop') }}" class="text-primary hover:underline mt-2 inline-block">Jetzt stöbern</a>
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="mt-6">
                {{ $orders->links() }}
            </div>

        @endif
    </div>
</div>
