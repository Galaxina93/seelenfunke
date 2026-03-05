<div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
    @php
        $headers = [
            'order_number' => ['label' => 'Nr. / Prio', 'sortable' => true],
            'created_at' => ['label' => 'Datum / Ziel', 'sortable' => true],
            'customer' => ['label' => 'Kunde', 'sortable' => true],
            'total' => ['label' => 'Summe', 'align' => 'right', 'sortable' => true],
            'payment' => ['label' => 'Zahlung & Beleg', 'align' => 'center', 'sortable' => true],
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

        // Dark Theme Colors für Status
        $statusColors = [
            'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
            'processing' => 'bg-primary/10 text-primary border-primary/30',
            'shipped' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
            'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'cancelled' => 'bg-red-500/10 text-red-400 border-red-500/30',
        ];

        // Dark Theme Colors für Payment
        $paymentColors = [
            'paid' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'unpaid' => 'bg-gray-800 text-gray-400 border-gray-700',
            'refunded' => 'bg-red-500/10 text-red-400 border-red-500/30'
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
                // Logik: Ist die Bestellung "erledigt"?
                $isDone = in_array($order->status, ['completed', 'cancelled', 'refunded']);

                // Logik: Ist die Deadline überschritten?
                $isOverdue = $order->deadline
                             && $order->deadline->isPast()
                             && !in_array($order->status, ['shipped', 'completed', 'cancelled']);

                $rowClass = '';
                if ($isOverdue) {
                    $rowClass = 'bg-red-900/10 border-l-4 border-l-red-500';
                } elseif ($order->is_express && !$isDone) {
                    $rowClass = 'bg-amber-900/10 border-l-4 border-l-amber-500';
                } elseif ($isDone) {
                    // Erledigte Aufgaben ausgrauen
                    $rowClass = 'bg-gray-950/50 opacity-60 grayscale-[0.5] hover:opacity-100 hover:grayscale-0 transition-all border-l-4 border-l-transparent';
                } else {
                    $rowClass = 'hover:bg-gray-800/40 border-l-4 border-l-transparent';
                }
            @endphp

            <tr class="transition-all duration-300 group text-sm border-b border-gray-800 last:border-b-0 {{ $rowClass }}">

                {{-- SPALTE 1: NR & PRIO --}}
                <td class="px-6 py-5 whitespace-nowrap align-middle">
                    <div class="flex flex-col">
                        <span class="font-mono font-bold text-gray-300 group-hover:text-primary transition-colors cursor-pointer text-base tracking-wide"
                              wire:click="openDetail('{{ $order->id }}')">
                            {{ $order->order_number }}
                        </span>

                        @if($order->is_express)
                            <span class="inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-red-400 mt-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_currentColor]"></span> Express
                                @if($order->deadline)
                                    <span class="text-gray-600 font-medium">|</span>
                                    <span class="{{ $isOverdue ? 'text-red-500 drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500' }}">Ziel: {{ $order->deadline->format('d.m.y') }}</span>
                                @endif
                            </span>
                        @endif
                    </div>
                </td>

                {{-- SPALTE 2: DATUM & DEADLINE --}}
                <td class="px-6 py-5 text-gray-400 whitespace-nowrap align-middle">
                    <div class="font-medium text-gray-300">{{ $order->created_at->format('d.m.Y') }} <span class="text-[10px] text-gray-500 ml-1">{{ $order->created_at->format('H:i') }}</span></div>

                    @if($order->deadline && !$order->is_express)
                        <div class="mt-1 text-[10px] font-bold uppercase tracking-widest {{ $isOverdue ? 'text-red-400 animate-pulse drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500' }}">
                            Frist: {{ $order->deadline->format('d.m.Y') }}
                        </div>
                    @endif
                </td>

                {{-- SPALTE 3: KUNDE --}}
                <td class="px-6 py-5 align-middle">
                    <div class="font-bold text-white truncate max-w-[200px]">{{ $order->customer_name }}</div>
                    <div class="text-[11px] text-gray-500 truncate max-w-[200px]">{{ $order->email }}</div>
                </td>

                {{-- SPALTE 4: SUMME --}}
                <td class="px-6 py-5 text-right font-serif font-bold text-primary text-lg align-middle whitespace-nowrap">
                    {{ number_format($order->total_price / 100, 2, ',', '.') }} €
                </td>

                {{-- SPALTE 5: ZAHLUNG & RECHNUNG --}}
                <td class="px-6 py-5 text-center align-middle">
                    <div class="flex flex-col items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest border {{ $paymentColors[$order->payment_status] ?? 'bg-gray-800 border-gray-700 text-gray-400' }}">
                            {{ $paymentMap[$order->payment_status] ?? $order->payment_status }}
                        </span>

                        @if($order->invoices && $order->invoices->isNotEmpty())
                            <div class="flex gap-1">
                                @foreach($order->invoices as $invoice)
                                    <a href="{{ route('invoice.download', $invoice->id) }}"
                                       target="_blank"
                                       wire:click.stop
                                       class="text-[9px] font-bold text-gray-400 bg-gray-950 border border-gray-700 px-2 py-0.5 rounded hover:bg-gray-800 hover:text-white transition-colors"
                                       title="Rechnung {{ $invoice->invoice_number }}">
                                        PDF
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </td>

                {{-- SPALTE 6: STATUS (SELECT) --}}
                <td class="px-6 py-5 text-center align-middle">
                    <div class="relative inline-block w-40" wire:click.stop>
                        <select
                            wire:change="updateStatus('{{ $order->id }}', $event.target.value)"
                            wire:loading.attr="disabled"
                            class="appearance-none w-full text-[10px] font-black uppercase tracking-widest rounded-xl border border-transparent py-2 pl-4 pr-8 cursor-pointer focus:ring-2 focus:ring-primary/20 transition-all outline-none {{ $statusColors[$order->status] ?? 'bg-gray-800 text-gray-400' }}"
                        >
                            @foreach($statusMap as $value => $label)
                                <option value="{{ $value }}" class="bg-gray-900 text-white font-bold" {{ $order->status === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-current opacity-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                </td>

                {{-- SPALTE 7: ACTIONS --}}
                <td class="px-6 py-5 text-right align-middle">
                    <button wire:click="openDetail('{{ $order->id }}')"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-950 border border-gray-800 text-gray-400 hover:bg-primary hover:border-primary hover:text-gray-900 transition-all duration-300 transform hover:-translate-y-0.5 shadow-inner">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-6 py-16 text-center text-gray-500">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-950 border border-gray-800 mb-4 shadow-inner">
                        <svg class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    </div>
                    <p class="font-serif italic text-lg">Keine Bestellungen gefunden.</p>
                </td>
            </tr>
        @endforelse

        {{-- Mobile Content --}}
        <x-slot name="mobileSlot">
            @foreach($orders as $order)
                @php
                    $isDone = in_array($order->status, ['completed', 'cancelled', 'refunded']);
                    $isOverdue = $order->deadline && $order->deadline->isPast() && !in_array($order->status, ['shipped', 'completed', 'cancelled']);

                    $bgClass = '';
                    if ($isOverdue) $bgClass = 'bg-red-900/10 border-l-4 border-l-red-500';
                    elseif ($order->is_express && !$isDone) $bgClass = 'bg-amber-900/10 border-l-4 border-l-amber-500';
                    elseif ($isDone) $bgClass = 'bg-gray-950/50 opacity-70 border-l-4 border-l-transparent';
                    else $bgClass = 'border-l-4 border-l-transparent';
                @endphp
                <div wire:click="openDetail('{{ $order->id }}')"
                     class="p-5 active:bg-gray-800/40 transition-colors cursor-pointer border-b border-gray-800 last:border-b-0 {{ $bgClass }}">

                    <div class="flex justify-between items-start mb-4">
                        <div class="flex flex-col gap-1.5">
                            <span class="font-mono font-bold text-gray-300 text-base tracking-wide flex items-center gap-2">
                                {{ $order->order_number }}
                                @if($order->shipping_address && serialize($order->billing_address) !== serialize($order->shipping_address))
                                    <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Abweichende Lieferadresse"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                @endif
                            </span>
                            @if($order->is_express || $order->deadline)
                                <span class="text-[9px] font-black {{ $order->is_express ? 'text-red-400' : 'text-gray-500' }} uppercase tracking-widest flex items-center gap-1.5">
                                    @if($order->is_express) <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_currentColor]"></span> EXPRESS @else ZIEL @endif
                                    @if($order->deadline)
                                        <span class="text-gray-700">|</span> <span class="{{ $isOverdue ? 'text-red-400 font-black animate-pulse drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500' }}">{{ $order->deadline->format('d.m.y') }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>

                        <div class="relative inline-block w-36" wire:click.stop>
                            <select
                                wire:change="updateStatus('{{ $order->id }}', $event.target.value)"
                                class="appearance-none w-full text-[9px] font-black uppercase tracking-widest rounded-xl border border-transparent py-2 pl-3 pr-6 cursor-pointer focus:ring-2 focus:ring-primary/20 shadow-sm outline-none {{ $statusColors[$order->status] ?? 'bg-gray-800 text-gray-400' }}"
                            >
                                @foreach($statusMap as $value => $label)
                                    <option value="{{ $value }}" class="bg-gray-900 text-white" {{ $order->status === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-current opacity-50">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between items-end">
                        <div class="flex-1 min-w-0 pr-4">
                            <div class="text-sm font-bold text-white truncate">{{ $order->customer_name }}</div>
                            <div class="text-[10px] font-medium text-gray-500 mt-0.5 mb-2">{{ $order->created_at->format('d.m.Y') }}</div>

                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border {{ $paymentColors[$order->payment_status] ?? 'bg-gray-800 border-gray-700 text-gray-400' }}">
                                    {{ $paymentMap[$order->payment_status] ?? $order->payment_status }}
                                </span>
                                @if($order->invoices && $order->invoices->isNotEmpty())
                                    <div class="flex gap-1">
                                        @foreach($order->invoices as $invoice)
                                            <a href="{{ route('invoice.download', $invoice->id) }}"
                                               target="_blank"
                                               wire:click.stop
                                               class="inline-flex items-center gap-1 text-[9px] font-bold bg-gray-950 border border-gray-700 px-1.5 py-0.5 rounded text-gray-400 shadow-sm hover:text-white transition-colors">
                                                PDF
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="text-right font-serif font-bold text-xl text-primary whitespace-nowrap">
                            {{ number_format($order->total_price / 100, 2, ',', '.') }} €
                        </div>
                    </div>
                </div>
            @endforeach
        </x-slot>
    </x-table.master>
</div>
