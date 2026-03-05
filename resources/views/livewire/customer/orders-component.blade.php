<div>
    <div class="p-6 lg:p-10 min-h-full flex flex-col relative z-10">

        {{-- SEITEN-HEADER (Wird ausgeblendet, wenn man in der Detailansicht ist) --}}
        @if(!$selectedOrderId)
            <div class="mb-10 flex flex-col sm:flex-row sm:items-end justify-between gap-6 animate-fade-in-up">
                <div>
                    <h1 class="text-4xl md:text-5xl font-serif font-bold text-white tracking-tight">Meine Bestellungen</h1>
                    <p class="text-gray-400 mt-2 text-sm uppercase tracking-widest font-bold">Die Historie deiner Schätze</p>
                </div>

                {{-- SUCHE --}}
                <div class="relative w-full sm:w-96 group">
                    <div class="absolute inset-0 bg-primary/20 rounded-full blur opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <input wire:model.live.debounce.300ms="searchOrder" type="text" placeholder="Bestellnummer suchen..." class="relative w-full rounded-full border border-gray-700 bg-gray-900 text-white shadow-inner focus:border-primary focus:ring-primary text-sm px-6 py-4 pl-14 transition-all placeholder-gray-500 font-medium tracking-wide outline-none">
                    <svg class="w-6 h-6 text-gray-500 absolute left-5 top-3.5 group-hover:text-primary transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
        @endif

        @php
            $countries = shop_setting('active_countries', []);
            $getCountryName = fn($code) => $countries[$code] ?? $code;
        @endphp

        @if($selectedOrderId && $selectedOrder)
            {{-- ========================================== --}}
            {{-- DETAIL ANSICHT --}}
            {{-- ========================================== --}}
            <div class="space-y-10 animate-fade-in-up w-full max-w-7xl mx-auto">

                {{-- NAVIGATION & BESTELLNUMMER --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                    <button wire:click="resetOrderView" class="inline-flex items-center text-gray-400 hover:text-white transition-all font-bold tracking-widest uppercase text-xs group px-6 py-3 rounded-full bg-gray-900 border border-gray-800 hover:border-gray-600 shadow-lg hover:shadow-xl">
                    <span class="w-8 h-8 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center mr-3 group-hover:border-gray-500 transition-colors shadow-inner text-gray-300 group-hover:text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </span>
                        Zurück zur Übersicht
                    </button>
                    <div class="text-left sm:text-right">
                        <h1 class="text-3xl md:text-4xl font-serif font-bold text-white flex items-center sm:justify-end gap-4 mb-2">
                            Bestellung #{{ $selectedOrder->order_number }}
                            @if($selectedOrder->is_express)
                                <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest bg-gradient-to-r from-red-500/20 to-pink-600/20 text-red-400 border border-red-500/50 shadow-[0_0_20px_rgba(239,68,68,0.2)]">
                                🚀 Express
                            </span>
                            @endif
                        </h1>
                        <p class="text-sm text-gray-500 font-bold uppercase tracking-widest">
                            Erfasst am <time datetime="{{ $selectedOrder->created_at }}" class="text-primary">{{ $selectedOrder->created_at->format('d.m.Y - H:i') }} Uhr</time>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

                    {{-- LINKE SPALTE: Artikel --}}
                    <div class="lg:col-span-2 space-y-8">

                        {{-- Status Bar / Progress --}}
                        <div class="bg-gray-900/80 backdrop-blur-md shadow-2xl border border-gray-800 rounded-[2.5rem] p-8">
                            @php
                                $status = $selectedOrder->status;
                                $progress = match($status) { 'pending' => '15%', 'processing' => '50%', 'shipped' => '80%', 'completed' => '100%', 'cancelled', 'refunded' => '100%', default => '0%' };
                                $barColor = match($status) { 'cancelled', 'refunded' => 'bg-red-500', 'completed' => 'bg-emerald-500', default => 'bg-primary' };
                                $isProcessing = in_array($status, ['processing', 'shipped', 'completed']);
                                $isShipped = in_array($status, ['shipped', 'completed']);
                                $isCompleted = $status === 'completed';
                                $isCancelled = in_array($status, ['cancelled', 'refunded']);
                            @endphp

                            <div class="flex justify-between items-end mb-4 px-2">
                                <span class="text-xs font-black text-gray-500 uppercase tracking-[0.2em]">Aktueller Status</span>
                                @php
                                    $statusBadge = match($status) {
                                        'completed' => 'text-emerald-400 bg-emerald-500/10 border border-emerald-500/20',
                                        'shipped' => 'text-blue-400 bg-blue-500/10 border border-blue-500/20',
                                        'cancelled', 'refunded' => 'text-red-400 bg-red-500/10 border border-red-500/20',
                                        'processing' => 'text-primary bg-primary/10 border border-primary/20 shadow-glow',
                                        default => 'text-gray-400 bg-gray-800 border border-gray-700'
                                    };
                                @endphp
                                <span class="text-xs font-black px-4 py-1.5 rounded-full {{ $statusBadge }}">
                                {{ match($status) { 'pending'=>'Wartend', 'processing'=>'In Bearbeitung', 'shipped'=>'Unterwegs', 'completed'=>'Zugestellt', 'cancelled'=>'Storniert', 'refunded'=>'Erstattet', default=>'Unbekannt' } }}
                            </span>
                            </div>

                            <div class="overflow-hidden h-4 mb-6 text-xs flex rounded-full bg-gray-800 shadow-inner">
                                <div style="width: {{ $progress }}" class="flex flex-col text-center whitespace-nowrap text-white justify-center {{ $barColor }} transition-all duration-1000 ease-out relative overflow-hidden shadow-[0_0_20px_currentColor]">
                                    <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                </div>
                            </div>

                            @if(!$isCancelled)
                                <div class="grid grid-cols-4 text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-600 text-center">
                                    <span class="text-primary">Bestellt</span>
                                    <span class="{{ $isProcessing ? 'text-primary' : '' }}">Bearbeitung</span>
                                    <span class="{{ $isShipped ? 'text-primary' : '' }}">Unterwegs</span>
                                    <span class="{{ $isCompleted ? 'text-emerald-500' : '' }}">Zugestellt</span>
                                </div>
                            @else
                                <div class="mt-6 bg-red-500/10 border border-red-500/30 rounded-2xl p-5 flex items-center justify-center gap-4 text-red-400">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <div>
                                        <p class="font-bold text-lg">Bestellung storniert</p>
                                        @if($selectedOrder->cancellation_reason) <p class="text-xs opacity-80 mt-1 uppercase tracking-widest">{{ $selectedOrder->cancellation_reason }}</p> @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Artikel Liste --}}
                        <div class="bg-gray-900/80 backdrop-blur-md shadow-2xl border border-gray-800 rounded-[2.5rem] overflow-hidden">
                            <div class="px-8 py-6 border-b border-gray-800 bg-gray-950/50">
                                <h3 class="text-2xl font-bold text-white font-serif tracking-tight">Artikelübersicht</h3>
                            </div>

                            <ul role="list" class="divide-y divide-gray-800/50">
                                @foreach($selectedOrder->items as $item)
                                    <li class="p-8 flex flex-col sm:flex-row gap-8 hover:bg-gray-800/20 transition-colors">
                                        {{-- Produktbild --}}
                                        <div class="h-32 w-32 flex-shrink-0 overflow-hidden rounded-2xl border border-gray-700 bg-gray-950 shadow-lg relative group">
                                            @if($item->product && !empty($item->product->media_gallery) && isset($item->product->media_gallery[0]))
                                                <img src="{{ Storage::url($item->product->media_gallery[0]['path']) }}" class="h-full w-full object-cover object-center group-hover:scale-110 transition-transform duration-700">
                                            @elseif($item->product && $item->product->preview_image_path)
                                                <img src="{{ Storage::url($item->product->preview_image_path) }}" class="h-full w-full object-cover object-center group-hover:scale-110 transition-transform duration-700">
                                            @else
                                                <div class="h-full w-full flex items-center justify-center text-gray-700">
                                                    <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                </div>
                                            @endif
                                            @php $type = $item->product->type ?? 'physical'; @endphp
                                            <div class="absolute top-2 left-2 px-2.5 py-1 bg-black/80 backdrop-blur text-[9px] font-black uppercase tracking-widest text-white rounded-md border border-gray-700">
                                                {{ match($type) { 'digital' => 'Digital', 'service' => 'Service', default => 'Artikel' } }}
                                            </div>
                                        </div>

                                        <div class="flex-1 flex flex-col justify-between">
                                            <div>
                                                <div class="flex justify-between items-start mb-2">
                                                    <h4 class="text-xl font-bold text-white tracking-tight">{{ $item->product_name }}</h4>
                                                    <p class="text-xl font-serif font-bold text-primary ml-4 whitespace-nowrap">
                                                        {{ number_format(($item->total_price / 100), 2, ',', '.') }} €
                                                    </p>
                                                </div>
                                                <p class="text-sm text-gray-400 font-medium">
                                                    {{ $item->quantity }}x à {{ number_format(($item->unit_price / 100), 2, ',', '.') }} €
                                                </p>
                                            </div>

                                            <div class="mt-6 flex flex-col items-start gap-4">
                                                @if(!empty($item->configuration))
                                                    <button wire:click="openPreview('{{ $item->id }}')" class="inline-flex items-center gap-3 px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $previewItemId == $item->id ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.4)]' : 'bg-gray-800 text-gray-300 hover:bg-gray-700 border border-gray-700' }}">
                                                        <span>{{ $previewItemId == $item->id ? 'Design verbergen' : 'Design ansehen' }}</span>
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </li>

                                    @if($previewItemId == $item->id && $this->previewItem)
                                        <div class="bg-gray-950 p-8 border-y border-gray-800 animate-fade-in-down shadow-inner">
                                            <div class="flex justify-between items-center mb-6">
                                                <h4 class="text-sm font-black text-gray-500 uppercase tracking-[0.3em]">Manufaktur Details</h4>
                                                @if($item->config_fingerprint)
                                                    <span class="text-[10px] bg-green-500/10 text-green-400 px-3 py-1 rounded-full border border-green-500/30 flex items-center gap-2 font-black uppercase tracking-widest shadow-[0_0_10px_rgba(16,185,129,0.2)]">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                    Versiegelt
                                                </span>
                                                @endif
                                            </div>
                                            <div class="bg-gray-900 rounded-[2rem] border border-gray-800 overflow-hidden shadow-2xl w-full">
                                                <livewire:shop.configurator.configurator :product="$this->previewItem->product" :initialData="$this->previewItem->configuration" :qty="$this->previewItem->quantity" context="preview" :key="'conf-'.$this->previewItem->id" design="dark" />
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </ul>

                            <div class="bg-gray-950 p-8 border-t border-gray-800">
                                <div class="flex justify-between text-sm text-gray-400 mb-3 font-medium">
                                    <span>Zwischensumme</span>
                                    <span>{{ number_format($selectedOrder->subtotal_price / 100, 2, ',', '.') }} €</span>
                                </div>

                                @if($selectedOrder->discount_amount > 0)
                                    <div class="flex justify-between text-sm text-emerald-400 mb-3 font-bold">
                                        <span>Rabatt @if($selectedOrder->coupon_code) ({{ $selectedOrder->coupon_code }}) @endif</span>
                                        <span>- {{ number_format($selectedOrder->discount_amount / 100, 2, ',', '.') }} €</span>
                                    </div>
                                @endif

                                <div class="flex justify-between text-sm text-gray-400 mb-3 font-medium">
                                    <span>Versandkosten</span>
                                    @if($selectedOrder->shipping_price == 0)
                                        <span class="text-emerald-400 font-bold uppercase tracking-widest text-[10px] mt-1">Kostenlos</span>
                                    @else
                                        <span>{{ number_format($selectedOrder->shipping_price / 100, 2, ',', '.') }} €</span>
                                    @endif
                                </div>

                                <div class="flex justify-between text-xs text-gray-600 mb-6 font-medium">
                                    <span>Enthaltene MwSt.</span>
                                    <span>{{ number_format($selectedOrder->tax_amount / 100, 2, ',', '.') }} €</span>
                                </div>

                                <div class="border-t border-gray-800 pt-6 flex justify-between items-center">
                                    <span class="font-bold text-white text-xl uppercase tracking-widest">Gesamtsumme</span>
                                    <span class="font-serif font-bold text-3xl text-primary">{{ number_format($selectedOrder->total_price / 100, 2, ',', '.') }} €</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- RECHTE SPALTE: Info & Dokumente --}}
                    <div class="space-y-8">
                        <div class="bg-gray-900/80 backdrop-blur-md shadow-2xl border border-gray-800 rounded-[2.5rem] p-8">
                            <h3 class="text-xs font-black text-gray-500 mb-6 uppercase tracking-[0.3em] flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                Transaktion
                            </h3>
                            @php
                                $paymentBadge = match($selectedOrder->payment_status) {
                                    'paid' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30',
                                    'unpaid' => 'text-amber-400 bg-amber-500/10 border-amber-500/30',
                                    'refunded' => 'text-red-400 bg-red-500/10 border-red-500/30',
                                    default => 'text-gray-400 bg-gray-800 border-gray-700'
                                };
                            @endphp
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-medium text-white">{{ match($selectedOrder->payment_method) { 'paypal'=>'PayPal', 'stripe'=>'Kreditkarte', 'invoice'=>'Rechnung', default=>'Online Zahlung' } }}</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border {{ $paymentBadge }}">
                                {{ match($selectedOrder->payment_status) { 'paid' => 'Bezahlt', 'unpaid' => 'Offen', 'refunded' => 'Erstattet', default => ucfirst($selectedOrder->payment_status) } }}
                            </span>
                            </div>
                            <p class="text-xs text-gray-500 font-medium">
                                Datum: {{ $selectedOrder->payment_status === 'paid' ? ($selectedOrder->invoices->first()?->paid_at?->format('d.m.Y H:i') ?? '-') : 'Ausstehend' }}
                            </p>
                        </div>

                        <div class="bg-gray-900/80 backdrop-blur-md shadow-2xl border border-gray-800 rounded-[2.5rem] p-8">
                            <h3 class="text-xs font-black text-gray-500 mb-6 uppercase tracking-[0.3em] flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                Dokumente
                            </h3>
                            @if($selectedOrder->invoices->isEmpty())
                                <div class="text-center py-8 bg-gray-950 rounded-2xl border border-gray-800">
                                    <svg class="mx-auto h-10 w-10 text-gray-700 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest">Rechnung wird erstellt</p>
                                </div>
                            @else
                                <div class="space-y-4">
                                    @foreach($selectedOrder->invoices as $invoice)
                                        <div class="group flex items-center justify-between p-4 rounded-2xl border transition-all duration-300 {{ $invoice->isCreditNote() ? 'border-red-500/30 bg-red-500/5 hover:bg-red-500/10' : 'border-gray-700 bg-gray-800/50 hover:bg-gray-800 hover:border-primary/50' }}">
                                            <div class="flex items-center gap-4">
                                                <div class="{{ $invoice->isCreditNote() ? 'text-red-500' : 'text-primary' }}">
                                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold tracking-wider {{ $invoice->isCreditNote() ? 'text-red-400' : 'text-white group-hover:text-primary' }} transition-colors">
                                                        {{ $invoice->invoice_number }}
                                                    </p>
                                                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1">{{ $invoice->created_at->format('d.m.Y') }}</p>
                                                </div>
                                            </div>
                                            <a href="{{ route('invoice.download', $invoice->id) }}" target="_blank" class="w-10 h-10 rounded-full bg-gray-900 border border-gray-700 flex items-center justify-center text-gray-400 group-hover:text-primary group-hover:border-primary transition-all shadow-lg" title="Herunterladen">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="bg-gray-900/80 backdrop-blur-md shadow-2xl border border-gray-800 rounded-[2.5rem] p-8">
                            <div class="mb-8">
                                <h3 class="text-xs font-black text-gray-500 mb-4 uppercase tracking-[0.3em] flex items-center gap-3">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    Lieferadresse
                                </h3>
                                @php $ship = $selectedOrder->shipping_address ?? $selectedOrder->billing_address; @endphp
                                <address class="text-sm text-gray-400 not-italic leading-loose pl-6 border-l-2 border-primary/50">
                                    <span class="text-white font-bold block mb-2 text-base">{{ $ship['first_name'] ?? '' }} {{ $ship['last_name'] ?? '' }}</span>
                                    @if(!empty($ship['company'])) <span class="text-primary">{{ $ship['company'] }}</span><br> @endif
                                    {{ $ship['address'] ?? '' }}<br>
                                    {{ $ship['postal_code'] ?? '' }} {{ $ship['city'] ?? '' }}<br>
                                    {{ $getCountryName($ship['country'] ?? 'DE') }}
                                </address>
                            </div>
                            <div class="pt-8 border-t border-gray-800">
                                <h3 class="text-xs font-black text-gray-500 mb-4 uppercase tracking-[0.3em] flex items-center gap-3">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                    Rechnungsadresse
                                </h3>
                                <address class="text-sm text-gray-500 not-italic leading-relaxed pl-6 border-l-2 border-gray-800">
                                    <span class="text-gray-300 font-bold block mb-1">{{ $selectedOrder->billing_address['first_name'] ?? '' }} {{ $selectedOrder->billing_address['last_name'] ?? '' }}</span>
                                    @if(!empty($selectedOrder->billing_address['company'])) {{ $selectedOrder->billing_address['company'] }}<br> @endif
                                    {{ $selectedOrder->billing_address['address'] ?? '' }}<br>
                                    {{ $selectedOrder->billing_address['postal_code'] ?? '' }} {{ $selectedOrder->billing_address['city'] ?? '' }}<br>
                                    {{ $getCountryName($selectedOrder->billing_address['country'] ?? 'DE') }}
                                </address>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        @else
            {{-- ========================================== --}}
            {{-- LISTEN ANSICHT (Übersicht aller Bestellungen) --}}
            {{-- ========================================== --}}
            <div class="space-y-8 animate-fade-in-up delay-100 w-full max-w-7xl mx-auto">
                @forelse($orders as $o)
                    <div class="bg-gray-900 rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden hover:border-primary/50 transition-all duration-500 group" wire:key="order-list-{{ $o->id }}">
                        <div class="px-8 py-6 bg-gray-800/40 border-b border-gray-800 flex flex-wrap items-center justify-between gap-6">
                            <div class="flex items-center gap-6">
                                <div class="w-16 h-16 rounded-full bg-gray-950 border border-gray-700 flex items-center justify-center text-primary font-serif font-bold text-2xl shadow-inner group-hover:border-primary/50 transition-colors">
                                    {{ substr($o->order_number, -2) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <span class="font-bold text-white text-lg tracking-wide">#{{ $o->order_number }}</span>
                                        @if($o->is_express)
                                            <span class="bg-red-500/10 text-red-400 text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-widest border border-red-500/30">Express</span>
                                        @endif
                                    </div>
                                    <p class="text-xs font-medium text-gray-500 uppercase tracking-widest">{{ $o->created_at->format('d. F Y') }} <span class="mx-2 text-gray-700">|</span> {{ $o->items->count() }} Artikel</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-8">
                                <div class="text-right hidden sm:block">
                                    <p class="text-[10px] text-gray-500 uppercase font-black tracking-widest mb-1">Gesamtbetrag</p>
                                    <p class="font-serif font-bold text-2xl text-primary">{{ number_format($o->total_price / 100, 2, ',', '.') }} €</p>
                                </div>
                                <button wire:click="showOrder('{{ $o->id }}')" class="flex items-center gap-4 bg-gray-950 border border-gray-800 hover:border-primary text-gray-300 hover:text-primary px-6 py-3 rounded-full text-xs font-black uppercase tracking-widest transition-all shadow-xl group-hover:shadow-[0_0_20px_rgba(197,160,89,0.2)]">
                                    Details
                                    <div class="w-8 h-8 rounded-full bg-gray-900 group-hover:bg-primary/20 flex items-center justify-center transition-colors">
                                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div class="p-8 relative group/slider"
                             x-data="{
                                    scrollAmount: 0,
                                    container: null,
                                    init() { this.container = this.$refs.sliderContainer; },
                                    scroll(direction) {
                                        const scrollVal = 300;
                                        if(direction === 'left') this.container.scrollBy({ left: -scrollVal, behavior: 'smooth' });
                                        else this.container.scrollBy({ left: scrollVal, behavior: 'smooth' });
                                    }
                                 }">

                            @php $limit = 10; @endphp

                            @if($o->items->count() > 4)
                                <button @click.stop="scroll('left')" class="absolute left-4 top-1/3 -translate-y-1/2 z-10 w-12 h-12 bg-gray-900/90 backdrop-blur border border-gray-700 rounded-full shadow-2xl flex items-center justify-center text-gray-400 hover:text-primary hover:border-primary transition-all opacity-0 group-hover/slider:opacity-100 disabled:opacity-0 -translate-x-4 group-hover/slider:translate-x-0 duration-500">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                </button>
                            @endif

                            <div x-ref="sliderContainer" class="flex gap-6 overflow-x-auto pb-6 scrollbar-hide snap-x relative z-0" style="scrollbar-width: none; -ms-overflow-style: none;">
                                @foreach($o->items->take($limit) as $item)
                                    <div class="snap-start shrink-0 w-40 relative group/item cursor-pointer" wire:click="showOrder('{{ $o->id }}')">
                                        <div class="aspect-square rounded-2xl bg-gray-950 border border-gray-800 overflow-hidden relative mb-4 shadow-lg group-hover/item:shadow-[0_0_25px_rgba(197,160,89,0.3)] group-hover/item:border-primary/50 transition-all duration-500">
                                            @if($item->product && !empty($item->product->media_gallery) && isset($item->product->media_gallery[0]))
                                                <img src="{{ Storage::url($item->product->media_gallery[0]['path']) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover/item:scale-110">
                                            @elseif($item->product && $item->product->preview_image_path)
                                                <img src="{{ Storage::url($item->product->preview_image_path) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover/item:scale-110">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-gray-700"><svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                            @endif

                                            @if($item->quantity > 1)
                                                <div class="absolute top-2 right-2 bg-primary text-gray-900 text-[10px] font-black uppercase px-2 py-1 rounded-lg shadow-lg">
                                                    {{ $item->quantity }}x
                                                </div>
                                            @endif
                                        </div>
                                        <p class="text-sm font-bold text-gray-300 truncate group-hover/item:text-white transition-colors text-center" title="{{ $item->product_name }}">
                                            {{ $item->product_name }}
                                        </p>
                                    </div>
                                @endforeach

                                @if($o->items->count() > $limit)
                                    <div class="snap-start shrink-0 w-32 flex items-center justify-center">
                                        <button wire:click="showOrder('{{ $o->id }}')" class="text-xs font-black uppercase tracking-widest text-gray-500 hover:text-primary transition-all flex flex-col items-center gap-2 group/more">
                                        <span class="w-16 h-16 rounded-full bg-gray-950 border border-gray-800 group-hover/more:border-primary flex items-center justify-center text-2xl shadow-inner transition-colors">
                                            +{{ $o->items->count() - $limit }}
                                        </span>
                                            weitere
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @if($o->items->count() > 4)
                                <button @click.stop="scroll('right')" class="absolute right-4 top-1/3 -translate-y-1/2 z-10 w-12 h-12 bg-gray-900/90 backdrop-blur border border-gray-700 rounded-full shadow-2xl flex items-center justify-center text-gray-400 hover:text-primary hover:border-primary transition-all opacity-0 group-hover/slider:opacity-100 translate-x-4 group-hover/slider:translate-x-0 duration-500">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            @endif

                            <div class="mt-4 pt-6 border-t border-gray-800/50 flex justify-between items-center">
                                @php
                                    $statusColor = match($o->status) {
                                        'completed' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
                                        'shipped' => 'text-blue-400 bg-blue-500/10 border-blue-500/20',
                                        'cancelled' => 'text-red-400 bg-red-500/10 border-red-500/20',
                                        'processing' => 'text-primary bg-primary/10 border-primary/20',
                                        default => 'text-gray-400 bg-gray-800 border-gray-700'
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
                                <div class="flex items-center gap-3 px-4 py-2 rounded-full border text-[10px] font-black uppercase tracking-widest {{ $statusColor }}">
                                    @if($o->status === 'shipped') <svg class="w-4 h-4 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> @endif
                                    @if($o->status === 'completed') <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> @endif
                                    {{ $statusLabel }}
                                </div>

                                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-600 hidden sm:block">
                                    Letztes Update: <span class="text-gray-400">{{ $o->updated_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-gray-900 rounded-[3rem] shadow-2xl border border-gray-800 p-20 text-center animate-fade-in-up">
                        <div class="w-32 h-32 bg-gray-950 rounded-full flex items-center justify-center mx-auto mb-8 text-gray-700 border border-gray-800 shadow-inner">
                            <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2-2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <h2 class="text-3xl font-serif font-bold text-white mb-4">Noch keine Bestellungen</h2>
                        <p class="text-gray-400 text-lg mb-10 max-w-lg mx-auto">Die Chronik deiner Seelenstücke ist noch leer. Zeit, die Manufaktur zu erkunden.</p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center gap-3 bg-gradient-to-r from-primary to-primary-dark text-gray-900 px-10 py-5 rounded-2xl font-black uppercase tracking-widest shadow-[0_0_30px_rgba(197,160,89,0.4)] hover:scale-105 transition-all">
                            Zum Shop
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>
                @endforelse
            </div>

            <div class="mt-12 text-gray-400 max-w-7xl mx-auto w-full">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
