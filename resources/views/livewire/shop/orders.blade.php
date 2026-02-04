<div class="min-h-screen bg-gray-50 p-4 md:p-6">

    {{-- VIEW 1: BESTELLÜBERSICHT (LISTE) --}}
    @if(!$selectedOrderId)

        {{-- STATS HEADER --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-8">
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

        {{-- TOOLBAR & SUCHE --}}
        <div class="bg-white rounded-t-xl border border-gray-200 p-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="relative w-full md:w-96">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche (Nr, Name)..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div class="flex gap-2 w-full md:w-auto overflow-x-auto no-scrollbar">
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

        {{-- CONTAINER FÜR DIE BESTELL-LISTE --}}
        <div class="bg-white border-x border-b border-gray-200 shadow-sm rounded-b-xl overflow-hidden">
            @php
                $headers = [
                    'order_number' => ['label' => 'Nr.', 'sortable' => true],
                    'created_at' => ['label' => 'Datum', 'sortable' => true],
                    'customer' => ['label' => 'Kunde', 'sortable' => true],
                    'total' => ['label' => 'Summe', 'align' => 'right', 'sortable' => true],
                    'payment' => ['label' => 'Zahlung', 'align' => 'center', 'sortable' => true],
                    'status' => ['label' => 'Status', 'align' => 'center', 'sortable' => true],
                    'actions' => ['label' => 'Aktionen', 'align' => 'right']
                ];

                // Mapping für die Status-Farben und Namen
                $statusMap = [
                    'pending' => 'Wartend', 'processing' => 'In Bearbeitung', 'shipped' => 'Versendet',
                    'completed' => 'Abgeschlossen', 'cancelled' => 'Storniert'
                ];
                $paymentMap = ['paid' => 'Bezahlt', 'unpaid' => 'Offen', 'refunded' => 'Erstattet'];
                $statusColors = [
                    'pending' => 'bg-yellow-50 text-yellow-700 border border-yellow-200',
                    'processing' => 'bg-blue-50 text-blue-700 border border-blue-200',
                    'shipped' => 'bg-purple-50 text-purple-700 border border-purple-200',
                    'completed' => 'bg-green-50 text-green-700 border border-green-200',
                    'cancelled' => 'bg-red-50 text-red-700 border border-red-200',
                ];
                $paymentColors = [
                    'paid' => 'bg-green-100 text-green-800',
                    'unpaid' => 'bg-gray-100 text-gray-800',
                    'refunded' => 'bg-red-100 text-red-800'
                ];
            @endphp

            {{--
               WICHTIG: Falls deine Livewire-Klasse $sortField und $sortDirection nutzt,
               solltest du sie hier explizit übergeben, damit die Icons in der Master-Tabelle erscheinen.
            --}}
            <x-table.master
                :headers="$headers"
                :rows="$orders"
                :sortField="$sortField ?? null"
                :sortDirection="$sortDirection ?? 'asc'"
            >
                {{-- Desktop Content (Haupt-Slot) --}}
                @forelse($orders as $order)
                    <tr class="hover:bg-gray-50/50 transition-colors group text-sm cursor-pointer" wire:click="openDetail('{{ $order->id }}')">
                        <td class="px-6 py-4 font-mono font-bold text-primary hover:underline whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-primary">{{ $order->order_number }}</span>

                                {{-- Hinweis auf abweichende Lieferadresse mit Tooltip --}}
                                @php
                                    $isDifferent = $order->shipping_address && serialize($order->billing_address) !== serialize($order->shipping_address);
                                @endphp

                                @if($isDifferent)
                                    <div class="relative group inline-block">
                                        {{-- Das Ausrufezeichen Icon --}}
                                        <svg class="w-4 h-4 text-amber-500 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>

                                        {{-- Der Tooltip (erscheint bei Hover) --}}
                                        <div class="absolute bottom-full z-50 left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-[100] w-64 bg-gray-900 text-white p-3 rounded-lg shadow-xl text-[11px] leading-relaxed">
                                            <div class="font-bold text-amber-400 uppercase tracking-wider mb-1 flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                                Abweichende Lieferadresse:
                                            </div>
                                            <div class="border-t border-gray-700 pt-1 mt-1">
                                                {{ $order->shipping_address['first_name'] }} {{ $order->shipping_address['last_name'] }}<br>
                                                @if(!empty($order->shipping_address['company'])) {{ $order->shipping_address['company'] }}<br> @endif
                                                {{ $order->shipping_address['address'] }}<br>
                                                {{ $order->shipping_address['postal_code'] }} {{ $order->shipping_address['city'] }}<br>
                                                <span class="font-bold text-gray-400">{{ $order->shipping_address['country'] }}</span>
                                            </div>
                                            {{-- Kleiner Pfeil nach unten --}}
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-8 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
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
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100' }}">
                        {{ $paymentMap[$order->payment_status] ?? $order->payment_status }}
                    </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100' }}">
                        {{ $statusMap[$order->status] ?? $order->status }}
                    </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-blue-600 hover:underline text-xs font-bold">Öffnen</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            Keine Bestellungen gefunden.
                        </td>
                    </tr>
                @endforelse

                {{-- Mobile Content (Benannter Slot) --}}
                <x-slot name="mobileSlot">
                    @foreach($orders as $order)
                        <div wire:click="openDetail('{{ $order->id }}')" class="p-4 active:bg-gray-50 transition-colors cursor-pointer border-b last:border-b-0">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-mono font-bold text-primary flex items-center gap-2">
                                    {{ $order->order_number }}
                                    @if($order->shipping_address && serialize($order->billing_address) !== serialize($order->shipping_address))
                                        <svg class="w-3 h-3 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    @endif
                                </span>
                                <span @class(['px-2 py-0.5 rounded-full text-[10px] font-bold uppercase', $statusColors[$order->status] ?? 'bg-gray-100'])>
                            {{ $statusMap[$order->status] ?? $order->status }}
                        </span>
                            </div>
                            <div class="flex justify-between items-end">
                                <div>
                                    <div class="text-sm font-bold text-gray-900">{{ $order->customer_name }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $order->email }}</div>
                                </div>
                                <div class="text-right font-bold text-lg text-primary">
                                    {{ number_format($order->total_price / 100, 2, ',', '.') }} €
                                </div>
                            </div>
                        </div>
                    @endforeach
                </x-slot>
            </x-table.master>
        </div>

        {{-- IMAGE ÜBERSICHTSMATERIAL --}}
        <div class="mt-4">{{ $orders->links() }}</div>

        {{-- VIEW 2: DETAIL ANSICHT (SPLIT SCREEN) --}}
    @else
        {{-- FIX: Main Container handles split. Mobile: Vertical Stack (col), Desktop: Horizontal (row) --}}
        <div class="h-[calc(100vh-3rem)] flex flex-col bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">

            {{-- Detail Header --}}
            <div class="bg-white border-b border-gray-200 px-4 md:px-6 py-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shrink-0 z-20 relative">
                <div class="flex items-center gap-4">
                    <button wire:click="closeDetail" class="text-gray-500 hover:text-gray-900 flex items-center gap-1 text-sm font-bold transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span class="hidden sm:inline">Zurück zur Liste</span>
                        <span class="sm:hidden">Zurück</span>
                    </button>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <div>
                        <h1 class="text-lg md:text-xl font-serif font-bold text-gray-900 flex flex-wrap items-center gap-2">
                            #{{ $this->selectedOrder->order_number }}
                            <span class="text-xs font-sans font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                {{ $this->selectedOrder->created_at->format('d.m.Y H:i') }}
                            </span>
                        </h1>
                    </div>
                </div>

                {{-- Status Actions --}}
                <div class="flex gap-2 w-full sm:w-auto">
                    <select wire:change="updateStatus('{{ $this->selectedOrder->id }}', $event.target.value)" class="w-full sm:w-auto text-sm border-gray-300 rounded-lg py-1.5 pl-3 pr-8 shadow-sm focus:ring-primary focus:border-primary">
                        <option value="pending" @selected($this->selectedOrder->status == 'pending')>Wartend</option>
                        <option value="processing" @selected($this->selectedOrder->status == 'processing')>In Bearbeitung</option>
                        <option value="shipped" @selected($this->selectedOrder->status == 'shipped')>Versendet</option>
                        <option value="completed" @selected($this->selectedOrder->status == 'completed')>Abgeschlossen</option>
                        <option value="cancelled" @selected($this->selectedOrder->status == 'cancelled')>Storniert</option>
                    </select>
                </div>
            </div>

            {{-- SPLIT CONTENT: Mobile = Column (Stacked), Desktop = Row (Side by Side) --}}
            <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">

                {{-- LINKS: Order Details & Liste (Scrollbar) --}}
                <div class="w-full lg:w-1/2 h-1/2 lg:h-full overflow-y-auto border-b lg:border-b-0 border-r-0 lg:border-r border-gray-200 bg-white custom-scrollbar z-10">
                    <div class="p-4 md:p-6 space-y-6 md:space-y-8">
                        {{-- Kundendaten --}}
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 md:gap-6">
                            {{-- Rechnungsadresse --}}
                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Rechnungsadresse
                                </h3>
                                <div class="text-sm text-gray-900 leading-snug">
                                    <span class="font-bold">{{ $this->selectedOrder->billing_address['first_name'] }} {{ $this->selectedOrder->billing_address['last_name'] }}</span><br>
                                    @if(!empty($this->selectedOrder->billing_address['company'])) {{ $this->selectedOrder->billing_address['company'] }}<br> @endif
                                    {{ $this->selectedOrder->billing_address['address'] }}<br>
                                    {{ $this->selectedOrder->billing_address['postal_code'] }} {{ $this->selectedOrder->billing_address['city'] }}<br>
                                    {{ $this->selectedOrder->billing_address['country'] }}
                                </div>
                                <div class="mt-2 text-xs text-blue-600 truncate">{{ $this->selectedOrder->email }}</div>
                            </div>

                            {{-- Lieferadresse (Abweichend Prüfung) --}}
                            @php
                                $isDifferent = $this->selectedOrder->shipping_address && serialize($this->selectedOrder->billing_address) !== serialize($this->selectedOrder->shipping_address);
                            @endphp
                            <div @class(['p-4 rounded-xl border', $isDifferent ? 'bg-amber-50 border-amber-200 shadow-sm' : 'bg-gray-50 border-gray-100'])>
                                <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Lieferadresse
                                    @if($isDifferent)
                                        <span class="ml-auto text-[9px] bg-amber-500 px-1.5 py-0.5 rounded-full font-black tracking-widest uppercase animate-pulse">Abweichend!</span>
                                    @endif
                                </h3>
                                <div class="text-sm text-gray-900 leading-snug">
                                    @if($this->selectedOrder->shipping_address)
                                        <span class="font-bold">{{ $this->selectedOrder->shipping_address['first_name'] }} {{ $this->selectedOrder->shipping_address['last_name'] }}</span><br>
                                        @if(!empty($this->selectedOrder->shipping_address['company'])) {{ $this->selectedOrder->shipping_address['company'] }}<br> @endif
                                        {{ $this->selectedOrder->shipping_address['address'] }}<br>
                                        {{ $this->selectedOrder->shipping_address['postal_code'] }} {{ $this->selectedOrder->shipping_address['city'] }}<br>
                                        {{ $this->selectedOrder->shipping_address['country'] }}
                                    @else
                                        <span class="italic text-gray-400">Wie Rechnungsadresse</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Zahlung & Info --}}
                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 xl:col-span-2">
                                <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    Zahlung & Info
                                </h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Zahlungsstatus:</span>
                                        <span class="font-bold {{ $this->selectedOrder->payment_status == 'paid' ? 'text-green-600' : 'text-yellow-600' }}">
                                            {{ $this->selectedOrder->payment_status == 'paid' ? 'Bezahlt' : 'Offen' }}
                                        </span>
                                    </div>
                                    @if($this->selectedOrder->payment_status !== 'paid')
                                        <button wire:click="markAsPaid('{{ $this->selectedOrder->id }}')" class="w-full text-xs bg-white border border-gray-300 rounded px-2 py-1 hover:bg-gray-50 font-bold shadow-sm transition">
                                            Als bezahlt markieren
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Dynamischer Storno-Bereich --}}
                        @if($status === 'cancelled')
                            <div class="mt-6 animate-fade-in-up">

                                {{-- FALL A: BEREITS GESPEICHERT (GRÜN) --}}
                                @if($selectedOrder->status === 'cancelled' && !empty($selectedOrder->cancellation_reason))
                                    <div class="bg-green-50 border border-green-200 rounded-xl p-5 transition-all">
                                        <div class="flex items-start gap-3 mb-3">
                                            <div class="mt-0.5 bg-green-100 rounded-full p-1">
                                                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-green-800 text-sm">Stornierungsgrund gespeichert</h4>
                                                <p class="text-xs text-green-700">Dieser Grund ist hinterlegt und für den Kunden sichtbar.</p>
                                            </div>
                                        </div>

                                        <textarea
                                            wire:model="cancellationReason"
                                            rows="2"
                                            class="w-full rounded-lg border-green-300 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400"
                                            placeholder="Grund bearbeiten..."
                                        ></textarea>
                                        {{-- Optional: Kleiner Speicher-Button auch hier --}}
                                        <div class="mt-2 text-right">
                                            <button wire:click="saveStatus" class="text-xs font-bold text-green-700 hover:text-green-900 underline">Änderung speichern</button>
                                        </div>
                                    </div>

                                    {{-- FALL B: NEU / PFLICHTFELD (ROT) --}}
                                @else
                                    <div class="bg-red-50 border border-red-200 rounded-xl p-5 transition-all">
                                        <div class="flex items-start gap-3 mb-3">
                                            <svg class="w-5 h-5 text-red-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <div>
                                                <h4 class="font-bold text-red-700 text-sm">Stornierungsgrund erforderlich</h4>
                                                <p class="text-xs text-red-600">Bitte geben Sie einen Grund an, um die Stornierung abzuschließen.</p>
                                            </div>
                                        </div>

                                        <textarea
                                            wire:model="cancellationReason"
                                            rows="3"
                                            class="w-full rounded-lg border-red-300 focus:border-red-500 focus:ring-red-500 text-sm placeholder-red-300"
                                            placeholder="z.B. Artikel nicht lieferbar oder Kundenwunsch..."
                                        ></textarea>
                                        @error('cancellationReason')
                                        <p class="text-red-600 text-xs mt-1 font-bold flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            {{ $message }}
                                        </p>
                                        @enderror

                                        {{-- BUTTON HINZUGEFÜGT --}}
                                        <div class="mt-4 flex justify-end">
                                            <button wire:click="saveStatus" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-red-700 transition shadow-sm flex items-center gap-2">
                                                <span>Stornierung bestätigen</span>
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Artikelliste MIT DATEIEN UND INFOS --}}
                        <div>
                            <h3 class="font-bold text-gray-900 mb-4 px-1 flex items-center justify-between">
                                <span>Positionen</span>
                                <span class="text-xs font-normal text-gray-400">Klicke zum Anzeigen</span>
                            </h3>
                            <div class="space-y-3">
                                @foreach($this->selectedOrder->items as $item)
                                    <div
                                        wire:click="selectItemForPreview('{{ $item->id }}')"
                                        class="cursor-pointer border rounded-xl p-3 transition-all relative overflow-hidden group
                                        {{ $selectedOrderItemId == $item->id ? 'border-primary ring-1 ring-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                                    >
                                        {{-- Hauptzeile: Produkt --}}
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-4">
                                                <div class="h-14 w-14 bg-white rounded-lg border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                                    @php
                                                        $conf = $item->configuration;
                                                        $imgPath = $conf['preview_file'] ?? ($conf['logo_storage_path'] ?? ($item->product->preview_image_path ?? null));
                                                    @endphp
                                                    @if($imgPath && file_exists(public_path('storage/'.$imgPath)))
                                                        <img src="{{ asset('storage/'.$imgPath) }}" class="h-full w-full object-contain">
                                                    @elseif($imgPath && file_exists(public_path($imgPath)))
                                                        <img src="{{ asset($imgPath) }}" class="h-full w-full object-contain">
                                                    @else
                                                        <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    @endif
                                                </div>

                                                <div>
                                                    <div class="font-bold text-gray-900 text-sm">{{ $item->product_name }}</div>
                                                    <div class="text-xs text-gray-500 mt-0.5">{{ $item->quantity }} Stück á {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</div>
                                                </div>
                                            </div>

                                            <div class="text-right">
                                                <div class="font-mono font-bold text-gray-900 text-sm">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</div>
                                                @if($selectedOrderItemId == $item->id)
                                                    <div class="text-[10px] text-primary font-bold mt-1 bg-white px-2 py-0.5 rounded-full shadow-sm inline-block">WIRD ANGEZEIGT</div>
                                                @else
                                                    <div class="text-[10px] text-gray-400 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">Anzeigen &rarr;</div>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- NEU: Details & Dateien Anzeige --}}
                                        @if(!empty($conf))
                                            <div class="mt-3 pt-3 border-t border-gray-200/60 grid grid-cols-1 xl:grid-cols-2 gap-4 text-xs">

                                                {{-- 1. Gravurtext --}}
                                                @if(!empty($conf['text']))
                                                    <div>
                                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Gravurtext</span>
                                                        <div class="font-serif italic text-gray-800 bg-gray-50 px-2 py-1.5 rounded border border-gray-100">
                                                            "{{ $conf['text'] }}"
                                                        </div>
                                                    </div>
                                                @endif

                                                {{-- 2. Anmerkungen --}}
                                                @if(!empty($conf['notes']))
                                                    <div>
                                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Kunden-Anmerkung</span>
                                                        <div class="text-gray-700 bg-yellow-50 px-2 py-1.5 rounded border border-yellow-100">
                                                            {{ $conf['notes'] }}
                                                        </div>
                                                    </div>
                                                @endif

                                                {{-- 3. Dateien --}}
                                                @php
                                                    $files = $conf['files'] ?? [];
                                                    // Falls nur ein Logo-Pfad da ist und nicht im Files-Array:
                                                    if(empty($files) && !empty($conf['logo_storage_path'])) {
                                                        $files[] = $conf['logo_storage_path'];
                                                    }
                                                @endphp

                                                @if(count($files) > 0)
                                                    <div class="col-span-1 xl:col-span-2">
                                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Hochgeladene Dateien ({{ count($files) }})</span>
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach($files as $file)
                                                                <a href="{{ asset('storage/'.$file) }}" target="_blank" download class="flex items-center gap-2 bg-white border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 hover:border-primary hover:text-primary transition group/btn">
                                                                    <svg class="w-4 h-4 text-gray-500 group-hover/btn:text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                                    <span class="truncate max-w-[150px]">{{ basename($file) }}</span>
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Abrechnung --}}
                        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                            <h4 class="text-xs font-bold uppercase text-gray-500 mb-4 border-b border-gray-200 pb-2">Abrechnung</h4>
                            <div class="space-y-3 text-sm">
                                @php $originalSum = $this->selectedOrder->subtotal_price + ($this->selectedOrder->volume_discount ?? 0); @endphp
                                <div class="flex justify-between text-gray-600"><span>Warenwert</span><span>{{ number_format($originalSum / 100, 2, ',', '.') }} €</span></div>
                                @if(isset($this->selectedOrder->volume_discount) && $this->selectedOrder->volume_discount > 0)
                                    <div class="flex justify-between text-green-600"><span>Mengenrabatt</span><span>-{{ number_format($this->selectedOrder->volume_discount / 100, 2, ',', '.') }} €</span></div>
                                @endif
                                @if(isset($this->selectedOrder->discount_amount) && $this->selectedOrder->discount_amount > 0)
                                    <div class="flex justify-between text-green-600"><span>Gutschein ({{ $this->selectedOrder->coupon_code }})</span><span>-{{ number_format($this->selectedOrder->discount_amount / 100, 2, ',', '.') }} €</span></div>
                                @endif
                                <div class="flex justify-between text-gray-600"><span>Versand</span><span>{{ $this->selectedOrder->shipping_price > 0 ? number_format($this->selectedOrder->shipping_price / 100, 2, ',', '.') . ' €' : 'Kostenlos' }}</span></div>
                                <div class="pt-3 mt-1 border-t border-gray-200 flex justify-between items-end"><span class="font-bold text-gray-900">Gesamtsumme</span><span class="text-xl font-bold text-primary">{{ number_format($this->selectedOrder->total_price / 100, 2, ',', '.') }} €</span></div>
                            </div>
                        </div>

                        {{-- Löschen --}}
                        <div class="pt-6 border-t border-gray-100">
                            <button wire:click="delete('{{ $this->selectedOrder->id }}')" wire:confirm="Bestellung endgültig löschen?" class="text-red-500 hover:text-red-700 text-xs font-bold flex items-center gap-1 hover:underline">Bestellung endgültig löschen</button>
                        </div>
                    </div>
                </div>

                {{-- RECHTS: Configurator (FIX: Scrollbar auf den Parent Container) --}}
                <div class="w-full lg:w-1/2 h-1/2 lg:h-full bg-gray-50 flex flex-col border-l-0 lg:border-l border-gray-200 overflow-hidden">
                    <div class="flex-1 p-4 md:p-6 bg-gray-100 h-full overflow-y-auto custom-scrollbar">
                        @if($this->previewItem)
                            {{-- Karte muss wachsen können, kein overflow-hidden hier! --}}
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex flex-col min-h-0">
                                {{-- Header Configurator --}}
                                <div class="bg-white border-b border-gray-100 px-4 md:px-6 py-4 flex justify-between items-center shrink-0">
                                    <div>
                                        <h3 class="font-bold text-gray-800">{{ $this->previewItem->product_name }}</h3>
                                        <p class="text-xs text-gray-400">Artikel-ID: {{ $this->previewItem->product_id }}</p>
                                    </div>

                                    <div class="text-right text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded">
                                        Konfiguration anzeigen
                                    </div>
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

                                {{-- CONFIGURATOR COMPONENT --}}
                                {{-- Wir entfernen h-full, damit es scrollen kann wenn nötig, oder lassen den Browser entscheiden --}}
                                <div class="relative">
                                    <livewire:shop.configurator
                                        :product="$this->previewItem->product"
                                        :initialData="$this->previewItem->configuration"
                                        :qty="$this->previewItem->quantity"
                                        context="preview"
                                        :key="'order-conf-'.$this->previewItem->id"
                                    />
                                </div>
                            </div>
                        @else
                            <div class="h-full flex flex-col items-center justify-center text-gray-400 space-y-4">
                                <p class="font-medium">Wähle links eine Position aus.</p>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endif

</div>
