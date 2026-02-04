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
            <tr class="hover:bg-gray-50/50 transition-colors group text-sm">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        {{-- Bestellnummer als Klick-Ziel --}}
                        <span class="font-mono font-bold text-primary hover:underline cursor-pointer"
                              wire:click="openDetail('{{ $order->id }}')">
                            {{ $order->order_number }}
                        </span>
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
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100' }}">
                        {{ $paymentMap[$order->payment_status] ?? $order->payment_status }}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100' }}">
                        {{ $statusMap[$order->status] ?? $order->status }}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    {{-- Öffnen Button als Klick-Ziel --}}
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

        {{-- Mobile Content (Benannter Slot) --}}
        <x-slot name="mobileSlot">
            @foreach($orders as $order)
                <div wire:click="openDetail('{{ $order->id }}')"
                     class="p-4 active:bg-gray-50 transition-colors cursor-pointer border-b last:border-b-0">
                    <div class="flex justify-between items-start mb-2">
                        <span class="font-mono font-bold text-primary flex items-center gap-2">
                            {{ $order->order_number }}
                            @if($order->shipping_address && serialize($order->billing_address) !== serialize($order->shipping_address))
                                <svg class="w-3 h-3 text-amber-500" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round"
                                                                 stroke-width="2"
                                                                 d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
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

{{-- Pagination Links --}}
<div class="mt-4">{{ $orders->links() }}</div>
