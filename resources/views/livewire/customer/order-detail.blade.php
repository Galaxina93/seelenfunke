<div>
    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">

        {{-- Header mit Back Button --}}
        <div class="mb-6 flex items-center justify-between">
            <a href="{{ route('customer.orders') }}" class="flex items-center text-gray-500 hover:text-gray-900 transition">
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Zurück zur Übersicht
            </a>
            <h1 class="text-2xl font-serif font-bold text-gray-900">{{ $order->order_number }}</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- LINKE SPALTE: Artikel --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Gekaufte Artikel</h3>
                    </div>
                    <ul role="list" class="divide-y divide-gray-200">
                        @foreach($order->items as $item)
                            <li class="p-4 flex items-start space-x-4">
                                {{-- Placeholder Bild --}}
                                <div class="h-16 w-16 bg-gray-100 rounded-md flex-shrink-0 border border-gray-200"></div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-bold text-gray-900">{{ $item->product_name }}</h4>
                                    <p class="text-sm text-gray-500">Menge: {{ $item->quantity }}</p>
                                    <p class="text-sm font-medium text-gray-900 mt-1">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</p>

                                    @if(!empty($item->configuration))
                                        <div class="mt-2 text-xs text-gray-500 bg-gray-50 p-2 rounded">
                                            Konfiguration vorhanden (Logo/Text)
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="px-4 py-4 sm:px-6 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                        <span class="font-bold text-gray-700">Gesamtsumme</span>
                        <span class="font-bold text-xl text-primary">{{ number_format($order->total_price / 100, 2, ',', '.') }} €</span>
                    </div>
                </div>
            </div>

            {{-- RECHTE SPALTE: Info & Dokumente --}}
            <div class="space-y-6">

                {{-- Status Box --}}
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider">Status</h3>
                    <div class="flex flex-col gap-2">
                        <div class="flex justify-between items-center">
                            <span>Bestellung:</span>
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $order->status_color }}">
                            {{ ucfirst($order->status) }}
                        </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Zahlung:</span>
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $order->payment_status_color ?? 'bg-gray-100' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                        </div>
                    </div>
                </div>

                {{-- Dokumente / Rechnungen --}}
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 mb-4 uppercase tracking-wider">Dokumente</h3>

                    @if($order->invoices->isEmpty())
                        <p class="text-sm text-gray-400 italic">Noch keine Rechnung verfügbar.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($order->invoices as $invoice)
                                <div class="flex items-center justify-between p-3 rounded-lg border {{ $invoice->isCreditNote() ? 'border-red-100 bg-red-50' : 'border-gray-100 bg-gray-50' }}">
                                    <div>
                                        <p class="text-sm font-bold {{ $invoice->isCreditNote() ? 'text-red-700' : 'text-gray-900' }}">
                                            {{ $invoice->invoice_number }}
                                        </p>
                                        <p class="text-xs text-gray-500">{{ $invoice->created_at->format('d.m.Y') }}</p>
                                    </div>

                                    {{-- Download Route Button --}}
                                    <a href="{{ route('invoice.download', $invoice->id) }}" target="_blank" class="p-2 text-gray-400 hover:text-gray-900">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Lieferadresse --}}
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 mb-2 uppercase tracking-wider">Lieferadresse</h3>
                    <address class="text-sm text-gray-900 not-italic leading-relaxed">
                        {{ $order->shipping_address['first_name'] }} {{ $order->shipping_address['last_name'] }}<br>
                        {{ $order->shipping_address['address'] }}<br>
                        {{ $order->shipping_address['postal_code'] }} {{ $order->shipping_address['city'] }}<br>
                        {{ $order->shipping_address['country'] }}
                    </address>
                </div>

            </div>
        </div>
    </div>
</div>
