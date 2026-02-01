<div class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- ========================================= --}}
        {{-- ANSICHT: DETAIL (Einzelne Bestellung)     --}}
        {{-- ========================================= --}}
        @if($viewMode === 'detail' && $order)

            <div class="space-y-8">
                {{-- HEADER & NAVIGATION --}}
                <div class="flex items-center justify-between flex-wrap gap-4">
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
                                $progress = match($order->status) {
                                    'pending' => '15%',
                                    'processing' => '50%',
                                    'shipped' => '80%',
                                    'completed' => '100%',
                                    'cancelled', 'refunded' => '100%',
                                    default => '0%'
                                };

                                $barColor = match($order->status) {
                                    'cancelled', 'refunded' => 'bg-red-500',
                                    'completed' => 'bg-green-500',
                                    default => 'bg-primary'
                                };

                                $isProcessing = in_array($order->status, ['processing', 'shipped', 'completed']);
                                $isShipped = in_array($order->status, ['shipped', 'completed']);
                                $isCompleted = $order->status === 'completed';
                                $isCancelled = in_array($order->status, ['cancelled', 'refunded']);
                            @endphp

                            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-100">
                                <div style="width: {{ $progress }}" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center {{ $barColor }} transition-all duration-500 ease-out"></div>
                            </div>

                            @if(!$isCancelled)
                                <div class="flex justify-between text-[10px] sm:text-xs font-medium text-gray-400">
                                    <span class="text-primary font-bold">Eingegangen</span>
                                    <span class="{{ $isProcessing ? 'text-primary font-bold' : '' }}">Bearbeitung</span>
                                    <span class="{{ $isShipped ? 'text-primary font-bold' : '' }}">Versendet</span>
                                    <span class="{{ $isCompleted ? 'text-green-600 font-bold' : '' }}">Erfolg</span>
                                </div>
                            @else
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
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Artikel Liste --}}
                        <div class="space-y-12">
                            <h2 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4">Gekaufte Artikel</h2>

                            @foreach($order->items as $item)
                                <div class="space-y-6" wire:key="order-item-{{ $item->id }}">
                                    <div class="flex flex-col sm:flex-row gap-6">
                                        {{-- Produktbild --}}
                                        <div class="shrink-0 flex justify-center sm:block">
                                            @if(isset($item->product->media_gallery[0]))
                                                <img src="{{ asset('storage/'.$item->product->media_gallery[0]['path']) }}" alt="{{ $item->product_name }}" class="w-24 h-24 sm:w-32 sm:h-32 object-cover rounded-lg bg-gray-100 border border-gray-100 shadow-sm">
                                            @else
                                                <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-lg bg-gray-100 border border-gray-100 flex items-center justify-center text-gray-300">
                                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Details --}}
                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <h3 class="font-bold text-gray-900 text-lg">
                                                    {{ $item->product_name }}
                                                </h3>
                                                <p class="text-gray-500 text-sm mt-1">{{ number_format($item->unit_price / 100, 2, ',', '.') }} € pro Stück</p>

                                                @if(!empty($item->configuration))
                                                    <div class="mt-4 flex flex-wrap gap-2">
                                                        <button
                                                            wire:click="openPreview('{{ $item->id }}')"
                                                            class="inline-flex items-center gap-2 text-xs font-bold px-4 py-2 rounded-full border transition {{ $previewItemId == $item->id ? 'bg-primary text-white border-primary shadow-md' : 'bg-white text-gray-700 border-gray-300 hover:border-primary hover:text-primary' }}"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                            {{ $previewItemId == $item->id ? 'Vorschau ausblenden' : 'Design-Vorschau anzeigen' }}
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="sm:text-right flex flex-col sm:justify-between h-full">
                                                <div class="hidden md:block">
                                                    <div class="text-sm font-medium text-gray-900">Lieferstatus</div>
                                                    <p class="text-sm text-gray-500 mt-1">
                                                        @if($order->status === 'shipped' || $order->status === 'completed')
                                                            <span class="text-green-600 flex items-center justify-end gap-1 font-bold">
                                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                                Versendet mit DHL
                                                            </span>
                                                        @elseif($order->status === 'cancelled')
                                                            <span class="text-red-500 font-bold">Storniert</span>
                                                        @else
                                                            <span class="text-amber-600 italic">In Vorbereitung</span>
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="font-serif font-bold text-xl text-gray-900">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</p>
                                                    <p class="text-xs text-gray-400">Menge: {{ $item->quantity }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- INTEGRIERTE KONFIGURATIONS-VORSCHAU --}}
                                    @if($previewItemId == $item->id && $this->previewItem)
                                        <div class="mt-6 bg-gray-100 rounded-2xl p-1 sm:p-4 border border-gray-200 animate-fade-in">
                                            <div class="bg-white rounded-xl overflow-hidden shadow-inner border border-gray-100">
                                                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                                                    <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Deine Konfiguration</h4>
                                                    <button wire:click="closePreview" class="text-gray-400 hover:text-red-500 transition">
                                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>

                                                {{-- In der orders.blade.php unter der Konfigurations-Vorschau --}}
                                                @if($item->config_fingerprint)
                                                    <div class="mt-4 flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-100">
                                                        <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                        </svg>
                                                        <div class="text-[10px] text-green-800 leading-tight">
                                                            <p class="font-bold uppercase">Produktkonfiguration – Digitales Echtheits-Siegel</p>
                                                            <p class="font-mono text-green-600">{{ substr($item->config_fingerprint, 0, 16) }}</p>
                                                            <p class="mt-1 text-green-700">
                                                                Hinweis: Diese Produktkonfiguration wurde bei der Bestellung eindeutig versiegelt.
                                                                Nachträgliche Änderungen am Konfigurationszustand sind nicht möglich.
                                                            </p>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="p-0 sm:p-6 overflow-hidden">
                                                    <livewire:shop.configurator
                                                        :product="$this->previewItem->product"
                                                        :initialData="$this->previewItem->configuration"
                                                        :qty="$this->previewItem->quantity"
                                                        context="preview"
                                                        :key="'order-conf-'.$this->previewItem->id"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                @if(!$loop->last) <div class="border-b border-gray-50"></div> @endif
                                    @endforeach
                                </div>
                        </div>
                    </div>

                    {{-- FOOTER AREA: Abrechnung & Info --}}
                    <div class="bg-gray-50 border-t border-gray-200 p-6 sm:p-10 grid grid-cols-1 lg:grid-cols-2 gap-10">

                        {{-- LINKE SPALTE: Adressen & Zahlung --}}
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm mb-4 uppercase tracking-widest border-b border-gray-200 pb-2">Rechnungsadresse</h4>
                                    <address class="not-italic text-sm text-gray-600 leading-relaxed">
                                        <span class="font-bold text-gray-900">{{ $order->billing_address['first_name'] }} {{ $order->billing_address['last_name'] }}</span><br>
                                        @if(!empty($order->billing_address['company'])) {{ $order->billing_address['company'] }}<br> @endif
                                        {{ $order->billing_address['address'] }}<br>
                                        {{ $order->billing_address['postal_code'] }} {{ $order->billing_address['city'] }}<br>
                                        {{ $order->billing_address['country'] }}
                                    </address>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm mb-4 uppercase tracking-widest border-b border-gray-200 pb-2">Zahlung</h4>
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="h-8 w-12 bg-white border border-gray-200 rounded flex items-center justify-center shadow-sm">
                                            <svg class="h-5 w-auto text-gray-600" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm0 4h16V6H4v2zm0 2v8h16v-8H4z"/></svg>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <p class="font-medium text-gray-900">
                                                @if($order->payment_method === 'paypal') PayPal
                                                @elseif($order->payment_method === 'stripe') Kreditkarte / Stripe
                                                @else Online Zahlung @endif
                                            </p>
                                            <p class="text-xs {{ $order->payment_status === 'paid' ? 'text-green-600 font-bold' : '' }}">
                                                {{ $order->payment_status === 'paid' ? 'Vollständig bezahlt' : ucfirst($order->payment_status) }}
                                            </p>
                                        </div>
                                    </div>

                                    @if($order->invoices->isNotEmpty())
                                        <div class="mt-4 space-y-2">
                                            @foreach($order->invoices as $inv)
                                                <a href="{{ route('invoice.download', $inv->id) }}" class="flex items-center text-sm font-bold text-primary hover:text-primary-dark transition">
                                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    Rechnung {{ $inv->invoice_number }} laden
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- RECHTE SPALTE: Detaillierte Kostenaufstellung --}}
                        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm ring-4 ring-gray-50">
                            <h4 class="font-bold text-gray-900 text-sm mb-4 uppercase tracking-widest">Kostenübersicht</h4>

                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between text-gray-600">
                                    <dt>Warenwert</dt>
                                    <dd>{{ number_format($order->subtotal_price / 100, 2, ',', '.') }} €</dd>
                                </div>

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

                                <div class="border-t border-gray-50 my-2 pt-2 flex justify-between text-gray-900 font-medium">
                                    <dt>Zwischensumme</dt>
                                    <dd>{{ number_format(($order->subtotal_price - $order->volume_discount - $order->discount_amount) / 100, 2, ',', '.') }} €</dd>
                                </div>

                                <div class="flex justify-between text-gray-600">
                                    <dt>Versand & Verpackung</dt>
                                    <dd>
                                        @if($order->shipping_price == 0)
                                            <span class="text-green-600 font-bold uppercase text-[10px]">Kostenlos</span>
                                        @else
                                            {{ number_format($order->shipping_price / 100, 2, ',', '.') }} €
                                        @endif
                                    </dd>
                                </div>

                                <div class="flex justify-between text-gray-400 text-[10px] italic">
                                    <dt>Enthaltene MwSt. (19%)</dt>
                                    <dd>{{ number_format($order->tax_amount / 100, 2, ',', '.') }} €</dd>
                                </div>

                                <div class="border-t-2 border-gray-900 pt-4 flex justify-between items-center mt-4">
                                    <dt class="font-serif font-bold text-gray-900 text-lg uppercase tracking-tight">Gesamtsumme</dt>
                                    <dd class="font-serif font-bold text-primary text-2xl tracking-tight">{{ number_format($order->total_price / 100, 2, ',', '.') }} €</dd>
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
                    <p class="mt-2 text-sm text-gray-600">Verfolge deine Lieferungen oder sieh dir deine Bestellhistorie an.</p>
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
                                    <div class="bg-gray-100 p-3 rounded-xl text-gray-500 shadow-inner">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-900">
                                            {{ $o->order_number }}
                                            <span class="text-[10px] font-normal text-gray-400 ml-1 uppercase tracking-widest">· {{ $o->created_at->format('d.m.Y') }}</span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <span class="font-bold text-primary">{{ number_format($o->total_price / 100, 2, ',', '.') }} €</span> · {{ $o->items->count() }} Position(en)
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 ml-auto">
                                    @php
                                        $statusClass = match($o->status) {
                                            'completed' => 'bg-green-100 text-green-800',
                                            'shipped' => 'bg-blue-100 text-blue-800',
                                            'processing' => 'bg-amber-100 text-amber-800',
                                            'cancelled', 'refunded' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        $statusText = match($o->status) {
                                            'pending' => 'Wartend',
                                            'processing' => 'Bearbeitung',
                                            'shipped' => 'Versendet',
                                            'completed' => 'Erfolgt',
                                            'cancelled' => 'Storniert',
                                            'refunded' => 'Erstattet',
                                            default => 'Unbekannt'
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-tighter {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>

                                    <button wire:click="showOrder('{{ $o->id }}')" class="w-10 h-10 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-gray-900 transition">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="p-12 text-center text-gray-500">
                            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            </div>
                            <p class="font-serif italic">Bisher wurden keine Bestellungen getätigt.</p>
                            <a href="{{ route('shop') }}" class="text-primary font-bold hover:underline mt-4 inline-block">Entdecke unseren Shop</a>
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="mt-8">
                {{ $orders->links() }}
            </div>

        @endif
    </div>
</div>
