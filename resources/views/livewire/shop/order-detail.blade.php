<div>
    <div class="p-6 max-w-7xl mx-auto">

        {{-- HEADER --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.orders') }}" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                    <h1 class="text-3xl font-serif font-bold text-gray-900">{{ $order->order_number }}</h1>
                    <span class="px-3 py-1 rounded-full text-sm font-bold {{ $order->status_color }}">
                    {{ ucfirst($order->status) }}
                </span>
                </div>
                <p class="text-gray-500 mt-1 ml-9">Bestellt am {{ $order->created_at->format('d.m.Y \u\m H:i') }} Uhr</p>
            </div>

            <div class="flex gap-3">
                <button class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg font-bold hover:bg-gray-50 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Rechnung
                </button>
                <button wire:click="saveStatus" class="bg-primary text-white px-6 py-2 rounded-lg font-bold hover:bg-primary-dark shadow-sm">
                    Speichern
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- LINKE SPALTE: Items & Status --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- ARTIKEL LISTE --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="font-bold text-gray-800">Positionen</h3>
                    </div>
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-gray-100">
                        @foreach($order->items as $item)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        @if($item->product && isset($item->product->media_gallery[0]))
                                            <img src="{{ asset('storage/'.$item->product->media_gallery[0]['path']) }}" class="w-12 h-12 rounded object-cover bg-gray-100">
                                        @else
                                            <div class="w-12 h-12 rounded bg-gray-100"></div>
                                        @endif
                                        <div>
                                            <div class="font-bold text-gray-900">{{ $item->product_name }}</div>
                                            @if($item->configuration)
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    @foreach($item->configuration as $key => $val)
                                                        @if(is_string($val)) <span class="mr-2">{{ $key }}: {{ \Illuminate\Support\Str::limit($val, 15) }}</span> @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right text-gray-500">{{ $item->quantity }} x</td>
                                <td class="px-6 py-4 text-right font-medium">{{ number_format($item->unit_price / 100, 2, ',', '.') }} €</td>
                                <td class="px-6 py-4 text-right font-bold">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50/50">
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-right text-sm text-gray-500">Zwischensumme</td>
                            <td class="px-6 py-3 text-right font-medium">{{ number_format($order->subtotal_price / 100, 2, ',', '.') }} €</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-1 text-right text-sm text-gray-500">Versand</td>
                            <td class="px-6 py-1 text-right font-medium">{{ number_format($order->shipping_price / 100, 2, ',', '.') }} €</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-1 text-right text-sm text-gray-500">Steuern</td>
                            <td class="px-6 py-1 text-right font-medium">{{ number_format($order->tax_amount / 100, 2, ',', '.') }} €</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right text-base font-bold text-gray-900">Gesamtsumme</td>
                            <td class="px-6 py-4 text-right text-xl font-bold text-primary">{{ number_format($order->total_price / 100, 2, ',', '.') }} €</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- STATUS & NOTIZEN --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-4">Bearbeitung</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Bestellstatus</label>
                            <select wire:model="status" class="w-full border-gray-300 rounded-lg focus:ring-primary">
                                <option value="pending">Wartend</option>
                                <option value="processing">In Bearbeitung</option>
                                <option value="shipped">Versendet</option>
                                <option value="completed">Abgeschlossen</option>
                                <option value="cancelled">Storniert</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Zahlungsstatus</label>
                            <select wire:model="payment_status" class="w-full border-gray-300 rounded-lg focus:ring-primary">
                                <option value="unpaid">Offen</option>
                                <option value="paid">Bezahlt</option>
                                <option value="refunded">Erstattet</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Interne Notizen</label>
                            <textarea wire:model="notes" rows="3" class="w-full border-gray-300 rounded-lg focus:ring-primary" placeholder="Trackingnummer, Kundenwünsche, etc."></textarea>
                        </div>
                    </div>
                </div>

            </div>

            {{-- RECHTE SPALTE: Kunde --}}
            <div class="space-y-8">

                {{-- KUNDE --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Kunde
                    </h3>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p class="font-bold text-gray-900 text-lg">{{ $order->customer_name }}</p>
                        <p class="text-primary">{{ $order->email }}</p>
                        @if(isset($order->billing_address['phone']))
                            <p>{{ $order->billing_address['phone'] }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-2">Kunde seit: {{ $order->customer ? $order->customer->created_at->format('Y') : 'Gast' }}</p>
                    </div>
                </div>

                {{-- ADRESSEN --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="font-bold text-gray-800 mb-4">Rechnungsadresse</h3>
                    <div class="text-sm text-gray-600 leading-relaxed">
                        {{ $order->billing_address['first_name'] }} {{ $order->billing_address['last_name'] }}<br>
                        @if(!empty($order->billing_address['company'])) {{ $order->billing_address['company'] }}<br> @endif
                        {{ $order->billing_address['address'] }}<br>
                        {{ $order->billing_address['postal_code'] }} {{ $order->billing_address['city'] }}<br>
                        {{ $order->billing_address['country'] }}
                    </div>

                    <div class="h-px bg-gray-100 my-4"></div>

                    <h3 class="font-bold text-gray-800 mb-4">Versandadresse</h3>
                    <div class="text-sm text-gray-600 leading-relaxed">
                        {{-- Hier vereinfacht gleich Rechnungsadresse, oder separate Logik --}}
                        {{ $order->shipping_address['first_name'] ?? $order->billing_address['first_name'] }}
                        {{ $order->shipping_address['last_name'] ?? $order->billing_address['last_name'] }}<br>
                        {{ $order->shipping_address['address'] ?? $order->billing_address['address'] }}<br>
                        {{ $order->shipping_address['postal_code'] ?? $order->billing_address['postal_code'] }}
                        {{ $order->shipping_address['city'] ?? $order->billing_address['city'] }}<br>
                        {{ $order->shipping_address['country'] ?? $order->billing_address['country'] }}
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
