{{-- CONTAINER FÜR DIE BESTELL-LISTE --}}
<div class="bg-white border-x border-b border-gray-200 shadow-sm rounded-b-xl overflow-hidden">
    @php
        $headers = [
            'order_number' => ['label' => 'Nr. / Prio', 'sortable' => true],
            'created_at' => ['label' => 'Datum / Ziel', 'sortable' => true],
            'customer' => ['label' => 'Kunde', 'sortable' => true],
            'total' => ['label' => 'Summe', 'align' => 'right', 'sortable' => true],
            'payment' => ['label' => 'Zahlung & Beleg', 'align' => 'center', 'sortable' => true], // Label angepasst
            'status' => ['label' => 'Status', 'align' => 'center', 'sortable' => true],
            'actions' => ['label' => 'Aktionen', 'align' => 'right']
        ];

        $statusMap = [
            'pending' => 'Wartend',
            'processing' => 'In Bearbeitung',
            'shipped' => 'Versendet',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Storniert'
        ];

        $paymentMap = ['paid' => 'Bezahlt', 'unpaid' => 'Offen', 'refunded' => 'Erstattet'];

        // Status Farben (angepasst für Select-Hintergründe)
        $statusColors = [
            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'processing' => 'bg-blue-100 text-blue-800 border-blue-200',
            'shipped' => 'bg-purple-100 text-purple-800 border-purple-200',
            'completed' => 'bg-green-100 text-green-800 border-green-200',
            'cancelled' => 'bg-red-100 text-red-800 border-red-200',
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
                // Logik für die Markierung
                $isOverdue = $order->is_express
                             && $order->deadline
                             && $order->deadline->isPast()
                             && !in_array($order->status, ['shipped', 'completed', 'cancelled']);

                // Zeilen-Hintergrundfarbe
                $rowClass = '';
                if ($isOverdue) {
                    $rowClass = 'bg-red-50 border-l-4 border-red-500'; // ALARM
                } elseif ($order->is_express) {
                    $rowClass = 'bg-amber-50/60'; // Express
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

                {{-- SPALTE 5: ZAHLUNG & RECHNUNG (NEU) --}}
                <td class="px-6 py-4 text-center align-top">
                    <div class="flex flex-col items-center gap-2">
                        {{-- Zahlungsstatus Badge --}}
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100' }}">
                            {{ $paymentMap[$order->payment_status] ?? $order->payment_status }}
                        </span>

                        {{-- [NEU] Rechnungs-Links --}}
                        @if($order->invoices && $order->invoices->isNotEmpty())
                            @foreach($order->invoices as $invoice)
                                <a href="{{ route('invoice.download', $invoice->id) }}"
                                   target="_blank"
                                   wire:click.stop
                                   class="text-[10px] text-gray-500 hover:text-primary flex items-center gap-1 transition-colors group/inv"
                                   title="Rechnung herunterladen">
                                    <svg class="w-3 h-3 text-gray-400 group-hover/inv:text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="underline decoration-dotted">{{ $invoice->invoice_number }}</span>
                                </a>
                            @endforeach
                        @endif
                    </div>
                </td>

                {{-- SPALTE 6: STATUS --}}
                <td class="px-6 py-4 text-center align-top">
                    <div class="relative inline-block w-40">
                        <select
                            wire:change="updateStatus('{{ $order->id }}', $event.target.value)"
                            wire:loading.attr="disabled"
                            class="appearance-none w-full text-xs font-bold rounded-full border-0 py-1.5 pl-3 pr-8 cursor-pointer focus:ring-2 focus:ring-primary/20 transition-all shadow-sm outline-none {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}"
                        >
                            @foreach($statusMap as $value => $label)
                                <option value="{{ $value }}" class="bg-white text-gray-900" {{ $order->status === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        {{-- Custom Arrow Icon --}}
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
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

                    <div class="flex justify-between items-start mb-3">
                        <div class="flex flex-col gap-1">
                            <span class="font-mono font-bold text-primary flex items-center gap-2">
                                {{ $order->order_number }}
                                @if($order->shipping_address && serialize($order->billing_address) !== serialize($order->shipping_address))
                                    <svg class="w-3 h-3 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                @endif
                            </span>
                            @if($order->is_express)
                                <span class="text-[10px] font-bold text-amber-600 uppercase flex items-center gap-1">
                                    🚀 Express
                                    @if($order->deadline)
                                        <span class="{{ $isOverdue ? 'text-red-600' : 'text-gray-500' }}">({{ $order->deadline->format('d.m.') }})</span>
                                    @endif
                                </span>
                            @endif
                        </div>

                        {{-- MOBIL: Status Select (mit click.stop um Detail-Öffnung zu verhindern) --}}
                        <div class="relative inline-block w-32" wire:click.stop>
                            <select
                                wire:change="updateStatus('{{ $order->id }}', $event.target.value)"
                                class="appearance-none w-full text-[10px] font-bold rounded-full border-0 py-1 pl-2 pr-6 cursor-pointer focus:ring-2 focus:ring-primary/20 shadow-sm {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}"
                            >
                                @foreach($statusMap as $value => $label)
                                    <option value="{{ $value }}" class="bg-white text-gray-900" {{ $order->status === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-between items-end">
                        <div>
                            <div class="text-sm font-bold text-gray-900">{{ $order->customer_name }}</div>
                            <div class="text-[11px] text-gray-400">{{ $order->email }}</div>

                            {{-- [NEU] Rechnung Mobil --}}
                            @if($order->invoices && $order->invoices->isNotEmpty())
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @foreach($order->invoices as $invoice)
                                        <a href="{{ route('invoice.download', $invoice->id) }}"
                                           target="_blank"
                                           wire:click.stop
                                           class="inline-flex items-center gap-1 text-[10px] bg-white border border-gray-200 px-1.5 py-0.5 rounded text-gray-500 hover:text-primary hover:border-primary">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            RE: {{ $invoice->invoice_number }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
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
