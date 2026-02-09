{{-- CONTAINER FÜR DIE BESTELL-LISTE --}}
<div class="bg-white border-x border-b border-gray-200 shadow-sm rounded-b-xl overflow-hidden">
    @php
        $headers = [
            'order_number' => ['label' => 'Nr. / Prio', 'sortable' => true], // Label angepasst
            'created_at' => ['label' => 'Datum / Ziel', 'sortable' => true], // Label angepasst
            'customer' => ['label' => 'Kunde', 'sortable' => true],
            'total' => ['label' => 'Summe', 'align' => 'right', 'sortable' => true],
            'payment' => ['label' => 'Zahlung', 'align' => 'center', 'sortable' => true],
            'status' => ['label' => 'Status', 'align' => 'center', 'sortable' => true],
            'actions' => ['label' => 'Aktionen', 'align' => 'right']
        ];

        $statusMap = [
            'pending' => 'Wartend', 'processing' => 'In Bearbeitung', 'shipped' => 'Versendet',
            'completed' => 'Abgeschlossen', 'cancelled' => 'Storniert'
        ];
        $paymentMap = ['paid' => 'Bezahlt', 'unpaid' => 'Offen', 'refunded' => 'Erstattet'];

        // Status Farben
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

    <x-table.master
        :headers="$headers"
        :rows="$orders"
        :sortField="$sortField ?? null"
        :sortDirection="$sortDirection ?? 'asc'"
    >
        {{-- Desktop Content --}}
        @forelse($orders as $order)
            @php
                // Logik für die Markierung (Alarmstufe Rot wenn Express + Deadline vorbei + nicht fertig)
                $isOverdue = $order->is_express
                             && $order->deadline
                             && $order->deadline->isPast()
                             && !in_array($order->status, ['shipped', 'completed', 'cancelled']);

                // Hintergrundfarbe bestimmen
                $rowClass = '';
                if ($isOverdue) {
                    $rowClass = 'bg-red-50 border-l-4 border-red-500'; // ALARM
                } elseif ($order->is_express) {
                    $rowClass = 'bg-amber-50/60'; // Express (Goldig)
                } else {
                    $rowClass = 'hover:bg-gray-50/50'; // Standard
                }
            @endphp

            <tr class="transition-colors group text-sm border-b last:border-b-0 {{ $rowClass }}">

                {{-- SPALTE 1: NR & PRIO --}}
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex flex-col">
                        <span class="font-mono font-bold text-primary hover:underline cursor-pointer text-base"
                              wire:click="openDetail('{{ $order->id }}')">
                            {{ $order->order_number }}
                        </span>

                        {{-- Express Badge --}}
                        @if($order->is_express)
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase text-amber-600 mt-1">
                                🚀 Express
                            </span>
                        @endif
                    </div>
                </td>

                {{-- SPALTE 2: DATUM & DEADLINE --}}
                <td class="px-6 py-4 text-gray-500 whitespace-nowrap align-top">
                    <div>{{ $order->created_at->format('d.m.Y H:i') }}</div>

                    {{-- Deadline Anzeige --}}
                    @if($order->deadline)
                        <div class="mt-1 flex items-center gap-1 text-xs font-bold {{ $isOverdue ? 'text-red-600 animate-pulse' : 'text-gray-400' }}">
                            @if($isOverdue) ⚠️ @else 🏁 @endif
                            Ziel: {{ $order->deadline->format('d.m.Y') }}
                        </div>
                    @endif
                </td>

                {{-- SPALTE 3: KUNDE --}}
                <td class="px-6 py-4 align-top">
                    <div class="font-medium text-gray-900">{{ $order->customer_name }}</div>
                    <div class="text-xs text-gray-400">{{ $order->email }}</div>
                </td>

                {{-- SPALTE 4: SUMME --}}
                <td class="px-6 py-4 text-right font-bold text-gray-900 align-top">
                    {{ number_format($order->total_price / 100, 2, ',', '.') }} €
                </td>

                {{-- SPALTE 5: ZAHLUNG --}}
                <td class="px-6 py-4 text-center align-top">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100' }}">
                        {{ $paymentMap[$order->payment_status] ?? $order->payment_status }}
                    </span>
                </td>

                {{-- SPALTE 6: STATUS --}}
                <td class="px-6 py-4 text-center align-top">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100' }}">
                        {{ $statusMap[$order->status] ?? $order->status }}
                    </span>
                </td>

                {{-- SPALTE 7: ACTIONS --}}
                <td class="px-6 py-4 text-right align-top">
                    <span class="text-blue-600 hover:underline text-xs font-bold cursor-pointer"
                          wire:click="openDetail('{{ $order->id }}')">
                        Öffnen
                    </span>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                    Keine Bestellungen gefunden.
                </td>
            </tr>
        @endforelse

        {{-- Mobile Content --}}
        <x-slot name="mobileSlot">
            @foreach($orders as $order)
                @php
                    $isOverdue = $order->is_express && $order->deadline && $order->deadline->isPast() && !in_array($order->status, ['shipped', 'completed', 'cancelled']);
                @endphp
                <div wire:click="openDetail('{{ $order->id }}')"
                     class="p-4 active:bg-gray-50 transition-colors cursor-pointer border-b last:border-b-0 {{ $isOverdue ? 'bg-red-50 border-l-4 border-red-500' : ($order->is_express ? 'bg-amber-50' : '') }}">

                    <div class="flex justify-between items-start mb-2">
                        <div class="flex flex-col">
                            <span class="font-mono font-bold text-primary flex items-center gap-2">
                                {{ $order->order_number }}
                                @if($order->shipping_address && serialize($order->billing_address) !== serialize($order->shipping_address))
                                    <svg class="w-3 h-3 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                @endif
                            </span>
                            @if($order->is_express)
                                <span class="text-[10px] font-bold text-amber-600 uppercase flex items-center gap-1 mt-0.5">
                                    🚀 Express
                                    @if($order->deadline)
                                        <span class="{{ $isOverdue ? 'text-red-600' : 'text-gray-500' }}">({{ $order->deadline->format('d.m.') }})</span>
                                    @endif
                                </span>
                            @endif
                        </div>

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

{{-- Pagination Links --}}
<div class="mt-4">{{ $orders->links() }}</div>
