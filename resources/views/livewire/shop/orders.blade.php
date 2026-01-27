<div class="p-6">

    {{-- STATS HEADER (Unverändert gut) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-blue-50 rounded-full text-blue-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Bestellungen Gesamt</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-yellow-50 rounded-full text-yellow-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Offene Bestellungen</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['open'] }}</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-green-50 rounded-full text-green-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Umsatz Heute</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['revenue_today'] / 100, 2, ',', '.') }} €</p>
            </div>
        </div>
    </div>

    {{-- TOOLBAR --}}
    <div class="bg-white rounded-t-xl border border-gray-200 p-4 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="relative w-full md:w-96">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche (Nr, Name)..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto">
            <select wire:model.live="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary">
                <option value="">Alle Status</option>
                <option value="pending">Wartend</option>
                <option value="processing">In Bearbeitung</option>
                <option value="shipped">Versendet</option>
                <option value="completed">Abgeschlossen</option>
                <option value="cancelled">Storniert</option>
            </select>
            <select wire:model.live="paymentFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary">
                <option value="">Alle Zahlungen</option>
                <option value="paid">Bezahlt</option>
                <option value="unpaid">Offen</option>
            </select>
        </div>
    </div>

    {{-- TABELLE --}}
    <div class="bg-white border-x border-b border-gray-200 shadow-sm overflow-x-auto rounded-b-xl">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="bg-gray-50/50 text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" wire:click="sortBy('order_number')">Nr.</th>
                <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" wire:click="sortBy('created_at')">Datum</th>
                <th class="px-6 py-4">Kunde</th>
                <th class="px-6 py-4 text-right">Summe</th>
                <th class="px-6 py-4 text-center">Zahlung</th>
                <th class="px-6 py-4 text-center">Status</th>
                <th class="px-6 py-4 text-right">Aktionen</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($orders as $order)
                <tr class="hover:bg-gray-50/50 transition-colors group text-sm">
                    <td class="px-6 py-4 font-mono font-bold text-gray-900">
                        <button wire:click="openDetail('{{ $order->id }}')" class="text-primary hover:underline">
                            {{ $order->order_number }}
                        </button>
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        {{ $order->created_at->format('d.m.Y H:i') }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">{{ $order->customer_name }}</div>
                        <div class="text-xs text-gray-400">{{ $order->email }}</div>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-900">
                        {{ number_format($order->total_price / 100, 2, ',', '.') }} €
                    </td>
                    <td class="px-6 py-4 text-center">
                        {{-- Shortcut für Zahlung --}}
                        <button wire:click="markAsPaid('{{ $order->id }}')"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer hover:opacity-80 transition {{ $order->payment_status_color }}"
                                title="Klicken um auf 'Bezahlt' zu setzen">
                            {{ ucfirst($order->payment_status) }}
                        </button>
                    </td>
                    <td class="px-6 py-4 text-center">
                        {{-- Shortcut für Status --}}
                        <div class="relative inline-block text-left" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $order->status_color }} hover:ring-2 ring-offset-1 ring-gray-200 transition">
                                {{ ucfirst($order->status) }}
                                <svg class="w-3 h-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div x-show="open" class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-xl z-20 border border-gray-100 py-1 origin-top-right text-xs" style="display: none;">
                                <button wire:click="updateStatus('{{ $order->id }}', 'processing')" @click="open = false" class="block w-full text-left px-4 py-2 hover:bg-blue-50 text-blue-700">In Bearbeitung</button>
                                <button wire:click="updateStatus('{{ $order->id }}', 'shipped')" @click="open = false" class="block w-full text-left px-4 py-2 hover:bg-purple-50 text-purple-700">Versendet</button>
                                <button wire:click="updateStatus('{{ $order->id }}', 'completed')" @click="open = false" class="block w-full text-left px-4 py-2 hover:bg-green-50 text-green-700">Abgeschlossen</button>
                                <button wire:click="updateStatus('{{ $order->id }}', 'cancelled')" @click="open = false" class="block w-full text-left px-4 py-2 hover:bg-red-50 text-red-700 border-t border-gray-100">Stornieren</button>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <button wire:click="openDetail('{{ $order->id }}')" class="p-2 rounded hover:bg-blue-50 text-gray-400 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">Keine Bestellungen gefunden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>

    {{-- DETAIL MODAL --}}
    @if($showDetailModal && $this->selectedOrder)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm" wire:click="closeDetail"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal Panel --}}
                <div class="inline-block align-middle bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-5xl w-full">

                    {{-- Header --}}
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center sticky top-0 z-10">
                        <div>
                            <h3 class="text-xl font-bold font-serif text-gray-900">Bestellung #{{ $this->selectedOrder->order_number }}</h3>
                            <p class="text-xs text-gray-500 mt-1">Eingegangen am {{ $this->selectedOrder->created_at->format('d.m.Y \u\m H:i') }} Uhr</p>
                        </div>
                        <button wire:click="closeDetail" class="p-2 bg-white rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 shadow-sm transition">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-6 max-h-[80vh] overflow-y-auto bg-gray-50/50">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                            {{-- LINKE SPALTE: Artikelliste --}}
                            <div class="lg:col-span-2 space-y-6">
                                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 font-bold text-gray-700">Bestellte Artikel</div>

                                    <div class="divide-y divide-gray-100">
                                        @foreach($this->selectedOrder->items as $item)
                                            <div class="p-6 flex flex-col sm:flex-row gap-6">

                                                {{-- VISUELLE VORSCHAU (Der "Calculation PDF Style") --}}
                                                <div class="shrink-0">
                                                    @php
                                                        $conf = $item->configuration ?? [];
                                                        $imgPath = $conf['product_image_path'] ?? null;
                                                    @endphp

                                                    <div class="relative w-32 h-32 bg-gray-100 rounded-lg border border-gray-200 overflow-hidden shadow-inner flex items-center justify-center">
                                                        @if($imgPath && file_exists(public_path($imgPath)))
                                                            {{-- Hintergrundbild --}}
                                                            <div class="absolute inset-0 bg-contain bg-center bg-no-repeat" style="background-image: url('{{ asset($imgPath) }}');"></div>

                                                            {{-- BLAUER PUNKT (Text) --}}
                                                            @if(isset($conf['text_x']))
                                                                <div class="absolute w-3 h-3 bg-blue-500 rounded-full border-2 border-white shadow-md z-10"
                                                                     style="left: {{ $conf['text_x'] }}%; top: {{ $conf['text_y'] }}%; transform: translate(-50%, -50%);"
                                                                     title="Text Position"></div>
                                                            @endif

                                                            {{-- GRÜNER PUNKT (Logo) --}}
                                                            @if(isset($conf['logo_x']) && !empty($conf['logo_storage_path']))
                                                                <div class="absolute w-3 h-3 bg-green-500 rounded-full border-2 border-white shadow-md z-10"
                                                                     style="left: {{ $conf['logo_x'] }}%; top: {{ $conf['logo_y'] }}%; transform: translate(-50%, -50%);"
                                                                     title="Logo Position"></div>
                                                            @endif
                                                        @else
                                                            <span class="text-xs text-gray-400">Keine Vorschau</span>
                                                        @endif
                                                    </div>

                                                    {{-- Legende --}}
                                                    <div class="flex justify-center gap-2 mt-2 text-[10px] text-gray-500 font-medium">
                                                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Text</span>
                                                        @if(!empty($conf['logo_storage_path']))
                                                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Logo</span>
                                                        @endif
                                                    </div>
                                                </div>

                                                {{-- DATEN --}}
                                                <div class="flex-1 space-y-3">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h4 class="font-bold text-gray-900 text-lg">{{ $item->product_name }}</h4>
                                                            <p class="text-sm text-gray-500">Menge: {{ $item->quantity }}x</p>
                                                        </div>
                                                        <div class="text-right font-mono font-bold text-gray-900">
                                                            {{ number_format($item->total_price / 100, 2, ',', '.') }} €
                                                        </div>
                                                    </div>

                                                    {{-- Konfig Details --}}
                                                    @if(!empty($conf))
                                                        <div class="bg-gray-50 rounded-lg p-3 text-sm space-y-1.5 border border-gray-100">
                                                            @if(!empty($conf['text']))
                                                                <div class="flex gap-2">
                                                                    <span class="text-gray-400 w-16 text-xs uppercase tracking-wide pt-0.5">Gravur:</span>
                                                                    <span class="font-bold text-gray-800 break-all">"{{ $conf['text'] }}"</span>
                                                                </div>
                                                                <div class="flex gap-2">
                                                                    <span class="text-gray-400 w-16 text-xs uppercase tracking-wide">Schrift:</span>
                                                                    <span class="text-gray-700">{{ $conf['font'] }} <span class="text-gray-400">({{ $conf['text_size'] }}x)</span></span>
                                                                </div>
                                                            @endif

                                                            @if(!empty($conf['logo_storage_path']))
                                                                <div class="flex gap-2 items-center mt-2 pt-2 border-t border-gray-200">
                                                                    <span class="text-gray-400 w-16 text-xs uppercase tracking-wide">Logo:</span>
                                                                    <a href="{{ asset('storage/'.$conf['logo_storage_path']) }}" target="_blank" class="text-blue-600 hover:underline text-xs flex items-center gap-1">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                                        Datei herunterladen
                                                                    </a>
                                                                </div>
                                                            @endif

                                                            @if(!empty($conf['notes']))
                                                                <div class="mt-2 pt-2 border-t border-gray-200 text-xs">
                                                                    <span class="font-bold text-yellow-600 bg-yellow-50 px-1 rounded">Kundenhinweis:</span>
                                                                    <span class="italic text-gray-600">{{ $conf['notes'] }}</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Summen --}}
                                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                                        <div class="flex justify-end gap-8 text-sm">
                                            <div class="text-gray-500">Zwischensumme: <span class="font-medium text-gray-900">{{ number_format($this->selectedOrder->subtotal_price / 100, 2, ',', '.') }} €</span></div>
                                            <div class="text-gray-500">MwSt: <span class="font-medium text-gray-900">{{ number_format($this->selectedOrder->tax_amount / 100, 2, ',', '.') }} €</span></div>
                                            <div class="text-xl font-bold text-primary">Gesamt: {{ number_format($this->selectedOrder->total_price / 100, 2, ',', '.') }} €</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- RECHTE SPALTE: Meta Daten --}}
                            <div class="space-y-6">

                                {{-- Kunde --}}
                                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                                    <h4 class="font-bold text-gray-800 border-b pb-3 mb-3 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        Kunde
                                    </h4>
                                    <div class="text-sm">
                                        <p class="font-bold text-lg text-gray-900">{{ $this->selectedOrder->customer_name }}</p>
                                        <p class="text-gray-500">{{ $this->selectedOrder->email }}</p>
                                        @if(isset($this->selectedOrder->billing_address['phone']))
                                            <p class="text-gray-400 mt-1">{{ $this->selectedOrder->billing_address['phone'] }}</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Adresse --}}
                                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                                    <h4 class="font-bold text-gray-800 border-b pb-3 mb-3 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        Rechnungsadresse
                                    </h4>
                                    <div class="text-sm text-gray-600 leading-relaxed">
                                        {{ $this->selectedOrder->billing_address['first_name'] }} {{ $this->selectedOrder->billing_address['last_name'] }}<br>
                                        @if(!empty($this->selectedOrder->billing_address['company'])) {{ $this->selectedOrder->billing_address['company'] }}<br> @endif
                                        {{ $this->selectedOrder->billing_address['address'] }}<br>
                                        {{ $this->selectedOrder->billing_address['postal_code'] }} {{ $this->selectedOrder->billing_address['city'] }}<br>
                                        <span class="uppercase font-bold text-xs text-gray-400">{{ $this->selectedOrder->billing_address['country'] }}</span>
                                    </div>
                                </div>

                                {{-- Verwaltung --}}
                                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                                    <h4 class="font-bold text-gray-800 border-b pb-3 mb-4">Verwaltung</h4>

                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
                                            <select wire:change="updateStatus('{{ $this->selectedOrder->id }}', $event.target.value)" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary focus:border-primary">
                                                <option value="pending" @selected($this->selectedOrder->status == 'pending')>Wartend</option>
                                                <option value="processing" @selected($this->selectedOrder->status == 'processing')>In Bearbeitung</option>
                                                <option value="shipped" @selected($this->selectedOrder->status == 'shipped')>Versendet</option>
                                                <option value="completed" @selected($this->selectedOrder->status == 'completed')>Abgeschlossen</option>
                                                <option value="cancelled" @selected($this->selectedOrder->status == 'cancelled')>Storniert</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Zahlung</label>
                                            @if($this->selectedOrder->payment_status === 'paid')
                                                <div class="w-full bg-green-50 text-green-700 px-3 py-2 rounded-lg text-sm font-bold border border-green-200 flex items-center justify-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Bezahlt
                                                </div>
                                            @else
                                                <button wire:click="markAsPaid('{{ $this->selectedOrder->id }}')" class="w-full bg-yellow-50 hover:bg-yellow-100 text-yellow-700 border border-yellow-200 px-3 py-2 rounded-lg text-sm font-bold transition">
                                                    Als bezahlt markieren
                                                </button>
                                            @endif
                                        </div>

                                        <div class="pt-4 mt-4 border-t border-gray-100">
                                            <button wire:click="delete('{{ $this->selectedOrder->id }}')" wire:confirm="Bestellung endgültig löschen?" class="w-full text-red-500 hover:text-red-700 text-sm font-bold hover:bg-red-50 px-3 py-2 rounded-lg transition text-left flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Bestellung löschen
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 px-6 py-4 flex justify-end">
                        <button wire:click="closeDetail" class="bg-gray-900 text-white px-6 py-2.5 rounded-lg hover:bg-black font-bold shadow-md transition transform hover:-translate-y-0.5">
                            Schließen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
