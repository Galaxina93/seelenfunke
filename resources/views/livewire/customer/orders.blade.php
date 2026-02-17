<div class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- ========================================= --}}
        {{-- ANSICHT: DETAIL (Einzelne Bestellung)     --}}
        {{-- ========================================= --}}
        @if($viewMode === 'detail' && $order)

            <div class="space-y-8 animate-fade-in-up">
                {{-- HEADER & NAVIGATION --}}
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <button wire:click="resetView" class="flex items-center text-gray-500 hover:text-gray-900 transition font-medium group px-4 py-2 rounded-full hover:bg-white hover:shadow-sm">
                        <span class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center mr-2 group-hover:border-gray-400 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </span>
                        Zurück zur Übersicht
                    </button>
                    <div class="text-right">
                        <h1 class="text-2xl font-serif font-bold text-gray-900 flex items-center justify-end gap-3">
                            Bestellung #{{ $order->order_number }}
                            @if($order->is_express)
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-gradient-to-r from-red-500 to-pink-600 text-white shadow-md transform hover:scale-105 transition-transform">
                                    🚀 Express
                                </span>
                            @endif
                        </h1>
                        <p class="text-sm text-gray-500 mt-1">
                            Bestellt am <time datetime="{{ $order->created_at }}" class="font-medium text-gray-900">{{ $order->created_at->format('d.m.Y') }}</time>
                        </p>
                    </div>
                </div>

                <div class="bg-white shadow-xl shadow-gray-200/50 border border-gray-100 rounded-2xl overflow-hidden">

                    {{-- PRODUKTLISTE & STATUS --}}
                    <div class="p-6 sm:p-10 space-y-10">

                        {{-- FORTSCHRITTSBALKEN (Progress Bar) --}}
                        <div class="relative">
                            @php
                                $status = $order->status;
                                $progress = match($status) { 'pending' => '15%', 'processing' => '50%', 'shipped' => '80%', 'completed' => '100%', 'cancelled', 'refunded' => '100%', default => '0%' };
                                $barColor = match($status) { 'cancelled', 'refunded' => 'bg-red-500', 'completed' => 'bg-green-500', default => 'bg-primary' };
                                $isProcessing = in_array($status, ['processing', 'shipped', 'completed']);
                                $isShipped = in_array($status, ['shipped', 'completed']);
                                $isCompleted = $status === 'completed';
                                $isCancelled = in_array($status, ['cancelled', 'refunded']);
                            @endphp

                            {{-- Status Label oben drüber für Mobile --}}
                            <div class="flex justify-between items-end mb-2">
                                <span class="text-sm font-bold text-gray-900 uppercase tracking-widest">Status</span>
                                <span class="text-xs font-bold px-2 py-1 rounded bg-gray-100 text-gray-600">
                                    {{ match($status) { 'pending'=>'Wartend', 'processing'=>'In Bearbeitung', 'shipped'=>'Versendet', 'completed'=>'Zugestellt', 'cancelled'=>'Storniert', 'refunded'=>'Erstattet', default=>'Unbekannt' } }}
                                </span>
                            </div>

                            <div class="overflow-hidden h-3 mb-4 text-xs flex rounded-full bg-gray-100 inner-shadow">
                                <div style="width: {{ $progress }}" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center {{ $barColor }} transition-all duration-1000 ease-out relative overflow-hidden">
                                    <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                </div>
                            </div>

                            @if(!$isCancelled)
                                <div class="grid grid-cols-4 text-[10px] sm:text-xs font-medium text-gray-400 text-center">
                                    <span class="text-primary font-bold">Bestellt</span>
                                    <span class="{{ $isProcessing ? 'text-primary font-bold' : '' }}">Bearbeitung</span>
                                    <span class="{{ $isShipped ? 'text-primary font-bold' : '' }}">Unterwegs</span>
                                    <span class="{{ $isCompleted ? 'text-green-600 font-bold' : '' }}">Zugestellt</span>
                                </div>
                            @else
                                <div class="mt-4 bg-red-50 border border-red-100 rounded-xl p-4 flex items-center justify-center gap-3 text-red-700">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <div>
                                        <p class="font-bold">Bestellung storniert</p>
                                        @if($order->cancellation_reason) <p class="text-xs opacity-80">{{ $order->cancellation_reason }}</p> @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Artikel Liste --}}
                        <div class="space-y-8">
                            <h2 class="text-xl font-serif font-bold text-gray-900 border-b border-gray-100 pb-4">Inhalt dieser Lieferung</h2>

                            {{-- SCROLL CONTAINER: Begrenzt die Höhe und ermöglicht Scrollen --}}
                            <div class="max-h-[600px] overflow-y-auto pr-2 custom-scrollbar overscroll-contain space-y-4">
                                @foreach($order->items as $item)
                                    <div class="group relative bg-white rounded-2xl p-4 border border-gray-100 hover:border-primary/30 hover:shadow-lg transition-all duration-300" wire:key="item-{{ $item->id }}">
                                        <div class="flex flex-col sm:flex-row gap-6">
                                            {{-- Bild --}}
                                            <div class="shrink-0 relative overflow-hidden rounded-xl w-full sm:w-32 h-32 bg-gray-50 border border-gray-100 group-hover:scale-[1.02] transition-transform duration-500">
                                                @if(isset($item->product->media_gallery[0]))
                                                    <img src="{{ asset('storage/'.$item->product->media_gallery[0]['path']) }}" class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-gray-300"><svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                                @endif

                                                {{-- Typ Badge --}}
                                                @php $type = $item->product->type ?? 'physical'; @endphp
                                                <div class="absolute top-2 left-2 px-2 py-1 bg-white/90 backdrop-blur text-[10px] font-bold uppercase rounded-md shadow-sm border border-gray-100">
                                                    {{ match($type) { 'digital' => 'Digital', 'service' => 'Service', default => 'Artikel' } }}
                                                </div>
                                            </div>

                                            {{-- Content --}}
                                            <div class="flex-1 flex flex-col justify-between py-1">
                                                <div>
                                                    <div class="flex justify-between items-start">
                                                        <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ $item->product_name }}</h3>
                                                        <p class="font-serif font-bold text-lg text-gray-900 whitespace-nowrap">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</p>
                                                    </div>
                                                    <p class="text-sm text-gray-500 mt-1">Menge: {{ $item->quantity }}x <span class="mx-1">|</span> Einzel: {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</p>
                                                </div>

                                                <div class="mt-4 flex items-center justify-between">
                                                    {{-- Button für Vorschau --}}
                                                    @if(!empty($item->configuration))
                                                        <button wire:click="openPreview('{{ $item->id }}')" class="text-sm font-bold text-primary hover:text-primary-dark flex items-center gap-2 group/btn">
                                                            <span>{{ $previewItemId == $item->id ? 'Konfiguration schließen' : 'Design ansehen' }}</span>
                                                            <svg class="w-4 h-4 transition-transform group-hover/btn:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                        </button>
                                                    @else
                                                        <div></div> {{-- Spacer --}}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- INLINE PREVIEW --}}
                                        @if($previewItemId == $item->id && $this->previewItem)
                                            <div class="mt-6 bg-gray-50 rounded-xl p-4 border-t border-gray-100 animate-fade-in-down">
                                                <div class="flex justify-between items-center mb-4 px-2">
                                                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest">Gespeicherte Konfiguration</h4>
                                                    @if($item->config_fingerprint)
                                                        <span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full border border-green-200 flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                            Versiegelt
                                                        </span>
                                                    @endif
                                                </div>
                                                {{-- HIER GEÄNDERT: max-w-[41rem] --}}
                                                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm max-w-[41rem] mx-auto w-full">
                                                    <livewire:shop.configurator.configurator :product="$this->previewItem->product" :initialData="$this->previewItem->configuration" :qty="$this->previewItem->quantity" context="preview" :key="'conf-'.$this->previewItem->id" />
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- FOOTER: Adressen & Summen --}}
                    <div class="bg-gray-50/50 border-t border-gray-200 p-6 sm:p-10 grid grid-cols-1 lg:grid-cols-2 gap-12">
                        <div class="space-y-8">
                            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                                <h4 class="font-bold text-gray-900 text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    Lieferadresse
                                </h4>
                                <address class="not-italic text-sm text-gray-600 leading-relaxed">
                                    @php $addr = $order->shipping_address ?? $order->billing_address; @endphp
                                    <span class="font-bold text-gray-900 block mb-1">{{ $addr['first_name'] }} {{ $addr['last_name'] }}</span>
                                    @if(!empty($addr['company'])) {{ $addr['company'] }}<br> @endif
                                    {{ $addr['address'] }}<br>
                                    {{ $addr['postal_code'] }} {{ $addr['city'] }}<br>
                                    {{ $addr['country'] }}
                                </address>
                            </div>

                            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                                <h4 class="font-bold text-gray-900 text-xs uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    Zahlung
                                </h4>
                                <p class="text-sm font-medium text-gray-900 mb-1">
                                    {{ match($order->payment_method) { 'paypal'=>'PayPal', 'stripe'=>'Kreditkarte', 'invoice'=>'Rechnung', default=>'Online Zahlung' } }}
                                </p>
                                <p class="text-xs {{ $order->payment_status === 'paid' ? 'text-green-600 font-bold' : 'text-amber-600' }}">
                                    {{ $order->payment_status === 'paid' ? 'Bezahlt am ' . ($order->invoices->first()?->paid_at?->format('d.m.Y') ?? '-') : 'Ausstehend' }}
                                </p>
                                @if($order->invoices->isNotEmpty())
                                    <div class="mt-4 pt-4 border-t border-gray-100">
                                        @foreach($order->invoices as $inv)
                                            <a href="{{ route('invoice.download', $inv->id) }}" class="inline-flex items-center text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition-colors">
                                                <svg class="w-3 h-3 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                Rechnung #{{ $inv->invoice_number }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <x-shop.cost-summary :model="$order" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========================================= --}}
            {{-- ANSICHT: LISTE (MODERN & HOCHWERTIG)    --}}
            {{-- ========================================= --}}
        @else

            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-10 gap-4 animate-fade-in">
                <div>
                    <h1 class="text-3xl font-serif font-bold text-gray-900">Meine Bestellungen</h1>
                    <p class="mt-2 text-sm text-gray-500">Alle deine Seelenstücke auf einen Blick.</p>
                </div>
                <div class="relative w-full sm:w-72">
                    <input wire:model.live="search" type="text" placeholder="Bestellnummer suchen..." class="w-full rounded-full border-gray-200 bg-white shadow-sm focus:border-primary focus:ring-primary text-sm px-5 py-3 pl-11 transition-all hover:border-gray-300">
                    <svg class="w-5 h-5 text-gray-400 absolute left-4 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="space-y-6">
                @forelse($orders as $o)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-xl hover:border-primary/20 transition-all duration-300 group" wire:key="order-list-{{ $o->id }}">

                        {{-- KOPFZEILE DER KARTE --}}
                        <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-white border border-gray-200 flex items-center justify-center text-primary font-serif font-bold text-lg shadow-sm">
                                    {{ substr($o->order_number, -2) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">#{{ $o->order_number }}</span>
                                        @if($o->is_express)
                                            <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase border border-red-200">Express</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $o->created_at->format('d. F Y') }} • {{ $o->items->count() }} Artikel</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-6">
                                <div class="text-right hidden sm:block">
                                    <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Gesamtbetrag</p>
                                    <p class="font-serif font-bold text-lg text-gray-900">{{ number_format($o->total_price / 100, 2, ',', '.') }} €</p>
                                </div>
                                {{-- BUTTON: DETAILS ANZEIGEN (wie im Beispiel: "Liefernachweis") --}}
                                <button wire:click="showOrder('{{ $o->id }}')" class="flex items-center gap-2 bg-white border border-gray-200 hover:border-primary text-gray-700 hover:text-primary px-4 py-2 rounded-full text-xs font-bold uppercase tracking-wide transition-all shadow-sm group-hover:shadow-md">
                                    Details anzeigen
                                    <div class="w-5 h-5 rounded-full bg-gray-100 group-hover:bg-primary/10 flex items-center justify-center transition-colors">
                                        <svg class="w-3 h-3 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                </button>
                            </div>
                        </div>

                        {{-- BODY: PRODUKT SLIDER (Horizontal Scroll mit Pfeilen) --}}
                        <div class="p-6 relative group/slider"
                             x-data="{
                                        scrollAmount: 0,
                                        container: null,
                                        init() { this.container = this.$refs.sliderContainer; },
                                        scroll(direction) {
                                            const scrollVal = 200; // Pixel pro Klick
                                            if(direction === 'left') this.container.scrollBy({ left: -scrollVal, behavior: 'smooth' });
                                            else this.container.scrollBy({ left: scrollVal, behavior: 'smooth' });
                                        }
                                     }">

                            {{-- Konfiguration: Wie viele Artikel sollen maximal im Slider zu sehen sein? --}}
                            @php $limit = 10; @endphp

                            {{-- PFEIL LINKS --}}
                            @if($o->items->count() > 3)
                                <button @click.stop="scroll('left')"
                                        class="absolute left-2 top-1/3 -translate-y-1/2 z-10 w-8 h-8 bg-white/90 backdrop-blur border border-gray-200 rounded-full shadow-md flex items-center justify-center text-gray-600 hover:text-primary hover:border-primary transition-all opacity-0 group-hover/slider:opacity-100 disabled:opacity-0 translate-x-[-10px] group-hover/slider:translate-x-0 duration-300">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                </button>
                            @endif

                            {{-- SLIDER CONTAINER --}}
                            <div x-ref="sliderContainer" class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide snap-x relative z-0" style="scrollbar-width: none; -ms-overflow-style: none;">

                                {{-- KORREKTUR: Wir nutzen take($limit), damit nur die ersten X Items gerendert werden --}}
                                @foreach($o->items->take($limit) as $item)
                                    <div class="snap-start shrink-0 w-32 relative group/item cursor-pointer" wire:click="showOrder('{{ $o->id }}')">
                                        {{-- Bild Container --}}
                                        <div class="aspect-square rounded-xl bg-gray-50 border border-gray-100 overflow-hidden relative mb-2 shadow-sm group-hover/item:shadow-md transition-all">
                                            @if(isset($item->product->media_gallery[0]))
                                                <img src="{{ asset('storage/'.$item->product->media_gallery[0]['path']) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover/item:scale-110">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-gray-300"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                            @endif

                                            {{-- Menge Badge --}}
                                            @if($item->quantity > 1)
                                                <div class="absolute top-1 right-1 bg-black/70 backdrop-blur text-white text-[10px] font-bold px-1.5 py-0.5 rounded-md shadow-sm">
                                                    {{ $item->quantity }}x
                                                </div>
                                            @endif
                                        </div>
                                        {{-- Titel (Truncated) --}}
                                        <p class="text-xs font-medium text-gray-700 truncate group-hover/item:text-primary transition-colors" title="{{ $item->product_name }}">
                                            {{ $item->product_name }}
                                        </p>
                                    </div>
                                @endforeach

                                {{-- "Mehr" Platzhalter: Erscheint nur, wenn es WIRKLICH mehr Items gibt als das Limit --}}
                                @if($o->items->count() > $limit)
                                    <div class="snap-start shrink-0 w-20 flex items-center justify-center">
                                        <button wire:click="showOrder('{{ $o->id }}')" class="text-xs font-bold text-gray-400 hover:text-primary transition flex flex-col items-center gap-1">
                                            <span class="text-lg">+{{ $o->items->count() - $limit }}</span>
                                            <span>weitere</span>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            {{-- PFEIL RECHTS --}}
                            @if($o->items->count() > 3)
                                <button @click.stop="scroll('right')"
                                        class="absolute right-2 top-1/3 -translate-y-1/2 z-10 w-8 h-8 bg-white/90 backdrop-blur border border-gray-200 rounded-full shadow-md flex items-center justify-center text-gray-600 hover:text-primary hover:border-primary transition-all opacity-0 group-hover/slider:opacity-100 translate-x-[10px] group-hover/slider:translate-x-0 duration-300">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            @endif

                            {{-- FOOTER DER KARTE: Status & Tracking --}}
                            <div class="mt-2 pt-4 border-t border-gray-50 flex justify-between items-center">
                                @php
                                    $statusColor = match($o->status) {
                                        'completed' => 'text-green-600 bg-green-50 border-green-100',
                                        'shipped' => 'text-blue-600 bg-blue-50 border-blue-100',
                                        'cancelled' => 'text-red-600 bg-red-50 border-red-100',
                                        'processing' => 'text-amber-600 bg-amber-50 border-amber-100',
                                        default => 'text-gray-600 bg-gray-100 border-gray-200'
                                    };
                                    $statusLabel = match($o->status) {
                                        'completed' => 'Zustellung erfolgreich',
                                        'shipped' => 'Unterwegs zu dir',
                                        'cancelled' => 'Storniert',
                                        'processing' => 'Wird vorbereitet',
                                        'pending' => 'Bestellung eingegangen',
                                        default => 'Unbekannt'
                                    };
                                @endphp
                                <div class="flex items-center gap-2 px-3 py-1 rounded-full border text-xs font-bold {{ $statusColor }}">
                                    @if($o->status === 'shipped') <svg class="w-3 h-3 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> @endif
                                    @if($o->status === 'completed') <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> @endif
                                    {{ $statusLabel }}
                                </div>

                                <div class="text-xs text-gray-400 font-medium hidden sm:block">
                                    Aktualisiert: {{ $o->updated_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center animate-fade-in">
                        <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 text-gray-300">
                            <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <h2 class="text-xl font-serif font-bold text-gray-900 mb-2">Noch keine Bestellungen</h2>
                        <p class="text-gray-500 mb-8 max-w-md mx-auto">Es sieht so aus, als hättest du noch keine Seelenstücke bestellt. Entdecke jetzt unsere Kollektion.</p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 bg-primary text-white px-8 py-3 rounded-full font-bold shadow-lg shadow-primary/30 hover:bg-primary-dark hover:-translate-y-1 transition-all">
                            Zum Shop
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>
                @endforelse
            </div>

            <div class="mt-8">
                {{ $orders->links() }}
            </div>

        @endif
    </div>
</div>
