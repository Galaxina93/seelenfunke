<div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden w-full">
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
            'pending' => 'bg-orange-500/10 text-orange-400 border-orange-500/30',
            'processing' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
            'shipped' => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
            'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'cancelled' => 'bg-red-500/10 text-red-400 border-red-500/30',
            'refunded' => 'bg-gray-500/10 text-gray-400 border-gray-500/30',
        ];

        // Dark Theme Colors für Payment
        $paymentColors = [
            'paid' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'unpaid' => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400 border-gray-300 dark:border-gray-700',
            'refunded' => 'bg-gray-500/10 text-gray-400 border-gray-500/30'
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
                    $rowClass = 'bg-red-50 dark:bg-red-900/10 border-l-4 border-l-red-500';
                } elseif ($order->is_express && !$isDone) {
                    $rowClass = 'bg-amber-50 dark:bg-amber-900/10 border-l-4 border-l-amber-500';
                } elseif ($isDone) {
                    // Erledigte Aufgaben ausgrauen
                    $rowClass = 'bg-gray-50/50 dark:bg-gray-950/50 opacity-60 grayscale-[0.5] hover:opacity-100 hover:grayscale-0 transition-all border-l-4 border-l-transparent';
                } else {
                    $rowClass = 'hover:bg-gray-50 dark:hover:bg-gray-800/40 border-l-4 border-l-transparent';
                }

                $hasItems = $order->items && $order->items->isNotEmpty();
                $totalPositions = $hasItems ? $order->items->count() : 0;
                $totalUnits = $hasItems ? (int) $order->items->sum('quantity') : 0;
                $isOnlyDigitalOrder = $hasItems && $order->isOnlyDigital();
                $dhlPreview = (!$isOnlyDigitalOrder && $hasItems) ? $order->getDhlWeightDetails(1, 'box') : null;
                $estPackages = $dhlPreview ? (int) ($dhlPreview['recommended_package_count'] ?? 1) : 1;
                if ($estPackages > 1 && $dhlPreview) {
                    $dhlPreview = $order->getDhlWeightDetails($estPackages, 'box');
                }
            @endphp

            <tr class="transition-all duration-300 group text-sm {{ $hasItems ? 'border-b-0' : 'border-b border-gray-200 dark:border-gray-800 last:border-b-0' }} {{ $rowClass }}">

                {{-- SPALTE 1: NR & PRIO --}}
                <td class="px-6 {{ $hasItems ? 'pt-5 pb-2.5' : 'py-5' }} whitespace-nowrap align-middle">
                    <div class="flex flex-col">
                        <span class="font-mono font-bold text-gray-700 dark:text-gray-300 group-hover:text-[var(--theme-color)] transition-colors cursor-pointer text-base tracking-wide"
                              wire:click="openDetail('{{ $order->id }}')">
                            {{ $order->order_number }}
                        </span>

                        @if($order->is_express)
                            <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-200 mt-1">
                                <x-shop.express-info iconClass="w-3.5 h-3.5" />
                                @if($order->deadline)
                                    <span class="text-gray-600 font-medium">|</span>
                                    <span class="{{ $isOverdue ? 'text-red-500 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500' }}">Ziel: {{ $order->deadline->format('d.m.y') }}</span>
                                @endif
                            </span>
                        @endif
                    </div>
                </td>

                {{-- SPALTE 2: DATUM & DEADLINE --}}
                <td class="px-6 {{ $hasItems ? 'pt-5 pb-2.5' : 'py-5' }} text-gray-400 whitespace-nowrap align-middle">
                    <div class="font-medium text-gray-800 dark:text-gray-300">{{ $order->created_at->format('d.m.Y') }} <span class="text-[10px] text-gray-500 ml-1">{{ $order->created_at->format('H:i') }}</span></div>

                    @if($order->deadline && !$order->is_express)
                        <div class="mt-1 text-[10px] font-bold uppercase tracking-widest {{ $isOverdue ? 'text-red-600 dark:text-red-400 animate-pulse drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500' }}">
                            Frist: {{ $order->deadline->format('d.m.Y') }}
                        </div>
                    @endif
                </td>

                {{-- SPALTE 3: KUNDE --}}
                <td class="px-6 {{ $hasItems ? 'pt-5 pb-2.5' : 'py-5' }} align-middle">
                    <div class="flex items-center gap-1.5">
                        <span class="font-bold text-gray-900 dark:text-white truncate max-w-[200px]">{{ $order->customer_name }}</span>
                        @if($order->is_influencer_order)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] shrink-0">
                                Influencer
                            </span>
                        @endif
                    </div>
                    <div class="text-[11px] text-gray-500 truncate max-w-[200px]">{{ $order->email }}</div>
                </td>

                {{-- SPALTE 4: SUMME --}}
                <td class="px-6 {{ $hasItems ? 'pt-5 pb-2.5' : 'py-5' }} text-right font-serif font-bold text-[var(--theme-color)] text-lg align-middle whitespace-nowrap">
                    {{ number_format($order->total_price / 100, 2, ',', '.') }} €
                </td>

                {{-- SPALTE 5: ZAHLUNG & RECHNUNG --}}
                <td class="px-6 {{ $hasItems ? 'pt-5 pb-2.5' : 'py-5' }} text-center align-middle">
                    <div class="flex flex-col items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest border {{ $paymentColors[$order->payment_status] ?? 'bg-gray-800 border-gray-700 text-gray-400' }}">
                            {{ $paymentMap[$order->payment_status] ?? $order->payment_status }}
                        </span>

                        @if($order->invoices && $order->invoices->isNotEmpty())
                            <div class="flex gap-1">
                                @foreach($order->invoices as $invoice)
                                    <a href="{{ route('invoice.download', $invoice->id) }}"
                                       target="_blank"
                                       onclick="event.stopPropagation()"
                                       class="text-[9px] font-bold text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 px-2 py-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors"
                                       title="Rechnung {{ $invoice->invoice_number }}">
                                        PDF
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </td>

                {{-- SPALTE 6: STATUS (SELECT) --}}
                <td class="px-6 {{ $hasItems ? 'pt-5 pb-2.5' : 'py-5' }} text-center align-middle">
                    <div class="flex flex-col items-center gap-2">
                        <div class="relative inline-block w-40" onclick="event.stopPropagation()">
                            <select
                                wire:change="updateStatus('{{ $order->id }}', $event.target.value)"
                                wire:loading.attr="disabled"
                                class="appearance-none w-full text-[10px] font-black uppercase tracking-widest rounded-xl border border-transparent py-2 pl-4 pr-8 cursor-pointer focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all outline-none {{ $statusColors[$order->status] ?? 'bg-gray-800 text-gray-400' }}"
                            >
                                @foreach($statusMap as $value => $label)
                                    <option value="{{ $value }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-bold" {{ $order->status === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-current opacity-50">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>

                        {{-- Progress Bar --}}
                        @php
                            $totalItems = $order->items->count();
                            $completedItems = $order->items->where('is_completed', true)->count();
                            $progressPct = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0;
                        @endphp
                        @if($totalItems > 0 && !in_array($order->status, ['cancelled', 'refunded']))
                            <div class="w-32 bg-gray-100 dark:bg-gray-950 rounded-full h-1 overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner flex cursor-help" title="{{ $completedItems }} von {{ $totalItems }} Positionen erledigt">
                                <div class="bg-emerald-500 h-full transition-all duration-700 ease-out shadow-[0_0_10px_rgba(16,185,129,0.5)]" style="width: {{ $progressPct }}%"></div>
                            </div>
                        @endif
                    </div>
                </td>

                {{-- SPALTE 7: ACTIONS --}}
                @php
                    $validLabelShipments = $order->shipments
                        ? $order->shipments->filter(fn($s) => !empty($s->shipping_label_path))
                        : collect();
                    $hasPrintableLabel = !$isOnlyDigitalOrder
                        && !in_array($order->status, ['cancelled', 'refunded'])
                        && $validLabelShipments->isNotEmpty();
                    $labelCount = $validLabelShipments->count();
                    $firstTracking = $validLabelShipments->first()?->tracking_number;
                    $wasJustPrinted = ($this->lastPrintedOrderId ?? null) === (string) $order->id;

                    if ($isOnlyDigitalOrder) {
                        $printReasonText = 'Kein Druck: Rein digitales Produkt';
                    } elseif (in_array($order->status, ['cancelled', 'refunded'])) {
                        $printReasonText = 'Kein Druck: Bestellung storniert';
                    } elseif (!$hasPrintableLabel) {
                        $printReasonText = 'Kein Label vorhanden – erst DHL-Label erstellen';
                    } elseif ($wasJustPrinted) {
                        $printReasonText = 'An Funkenwerk gesendet (' . ($firstTracking ?: 'DHL') . ')';
                    } else {
                        $printReasonText = $labelCount > 1
                            ? "{$labelCount} DHL-Labels bereit – via Funkenwerk drucken"
                            : "DHL-Label ({$firstTracking}) via Funkenwerk drucken";
                    }
                @endphp
                <td class="px-6 {{ $hasItems ? 'pt-5 pb-2.5' : 'py-5' }} text-right align-middle">
                    <div class="inline-flex items-center justify-end gap-2">
                        <div class="relative group/print">
                            <button type="button"
                                    wire:click.stop="printOrderLabelViaFunkenwerk('{{ $order->id }}')"
                                    title="{{ $printReasonText }}"
                                    class="relative inline-flex items-center justify-center w-10 h-10 rounded-full border transition-all duration-300 shadow-sm dark:shadow-inner {{ $hasPrintableLabel
                                        ? ($wasJustPrinted
                                            ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500 hover:text-white cursor-pointer transform hover:-translate-y-0.5'
                                            : 'bg-[var(--theme-color-10)] border-[var(--theme-color-30)] text-[var(--theme-color)] hover:bg-[var(--theme-color)] hover:border-[var(--theme-color)] hover:text-gray-900 cursor-pointer transform hover:-translate-y-0.5')
                                        : 'bg-gray-100/70 dark:bg-gray-900/40 border-gray-200 dark:border-gray-800/80 text-gray-300 dark:text-gray-600 opacity-50 cursor-not-allowed' }}">
                                <span wire:loading.remove wire:target="printOrderLabelViaFunkenwerk('{{ $order->id }}')">
                                    @if($wasJustPrinted && $hasPrintableLabel)
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                    @endif
                                </span>
                                <span wire:loading wire:target="printOrderLabelViaFunkenwerk('{{ $order->id }}')" class="animate-spin w-4 h-4 border-2 border-current border-t-transparent rounded-full"></span>

                                @if($hasPrintableLabel)
                                    <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 ring-2 ring-white dark:ring-gray-900"></span>
                                    </span>
                                @endif
                            </button>

                            {{-- Direktes Hover-Feedback-Badge über dem Icon --}}
                            <div class="pointer-events-none opacity-0 group-hover/print:opacity-100 transition-opacity duration-200 absolute bottom-full right-0 mb-2 z-50 whitespace-nowrap px-2.5 py-1.5 rounded-lg text-[10px] font-bold shadow-lg border {{ $hasPrintableLabel ? 'bg-gray-900 text-white border-emerald-500/40' : 'bg-gray-900 text-amber-300 border-amber-500/40' }}">
                                {{ $printReasonText }}
                            </div>
                        </div>

                        <button wire:click="openDetail('{{ $order->id }}')"
                                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:bg-[var(--theme-color)] hover:border-[var(--theme-color)] hover:text-gray-900 transition-all duration-300 transform hover:-translate-y-0.5 shadow-sm dark:shadow-inner">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>
                </td>
            </tr>

            {{-- EXTRA ZEILE: KOMPAKTE PRODUKT- & PAKET-KURZANSICHT (Horizontal scrollbar) --}}
            @if($hasItems)
                <tr class="!border-t-0 border-b border-gray-200 dark:border-gray-800 last:border-b-0 transition-all duration-300 {{ $rowClass }}">
                    <td colspan="7" class="px-6 pt-0 pb-4">
                        <div class="flex items-center gap-2.5 bg-gray-50/80 dark:bg-gray-950/60 border border-gray-200/80 dark:border-gray-800/80 rounded-xl px-3 py-1.5">
                            {{-- Logistik- & Mengen-Einschätzung (Links fixiert) --}}
                            <div class="flex items-center gap-2 shrink-0 text-[10px] font-bold text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 shadow-2xs" title="{{ $totalUnits }} Stück verteilt auf {{ $totalPositions }} Position(en)">
                                    <span class="font-black text-[var(--theme-color)]">{{ $totalUnits }}×</span>
                                    <span>{{ $totalUnits === 1 ? 'Produkt' : 'Produkte' }}</span>
                                </span>

                                @if($isOnlyDigitalOrder)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-500/10 border border-blue-500/20 text-blue-600 dark:text-blue-400">
                                        ⚡ Digital
                                    </span>
                                @elseif($dhlPreview)
                                    @if(!empty($dhlPreview['can_fit_in_envelope']))
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400" title="Für Luftpolstertasche / Großbrief geeignet">
                                            ✉️ 1 Tasche · {{ number_format($dhlPreview['total_weight_kg'] ?? 0.1, 2, ',', '.') }} kg
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md {{ $estPackages > 1 ? 'bg-amber-500/15 border-amber-500/30 text-amber-700 dark:text-amber-300' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }} border" title="{{ $dhlPreview['recommended_box_hint'] ?? ($dhlPreview['dhl_class'] ?? 'DHL Paket') }}">
                                            📦 {{ $estPackages }} {{ $estPackages === 1 ? 'Paket' : 'Pakete' }} · {{ number_format($dhlPreview['total_weight_kg'] ?? 0.5, 2, ',', '.') }} kg
                                        </span>
                                    @endif
                                @endif

                                @if(!in_array($order->status, ['completed', 'cancelled', 'refunded']))
                                    @if($order->hasPendingSupplierProcurement())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/40 text-amber-700 dark:text-amber-300 font-black uppercase tracking-wider" title="Mindestens ein Rohling muss für diese Auftragsfertigung noch beim Lieferanten bestellt werden">
                                            📦 Rohling bestellen
                                        </span>
                                    @elseif($order->hasSupplierProcurementItems())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-500/10 border border-sky-500/30 text-sky-700 dark:text-sky-300 font-bold" title="Rohling wurde beim Lieferanten bestellt (im Zulauf)">
                                            🚚 Rohling bestellt
                                        </span>
                                    @endif
                                @endif
                            </div>

                            <div class="h-4 w-px bg-gray-200 dark:bg-gray-800 shrink-0"></div>

                            {{-- Horizontal scrollbare Produkt-Kurzansicht (Bilder + Namen klein & kompakt) --}}
                            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5 flex-1 min-w-0">
                                @foreach($order->items as $item)
                                    @php
                                        $conf = is_array($item->configuration) ? $item->configuration : [];
                                        $snapshotImg = null;
                                        if (!empty($conf['snapshot_path'])) {
                                            $snapshotImg = is_array($conf['snapshot_path']) ? ($conf['snapshot_path']['front'] ?? null) : $conf['snapshot_path'];
                                        }
                                        $thumbUrl = $snapshotImg
                                            ? asset('storage/' . ltrim($snapshotImg, '/'))
                                            : $item->resolveDisplayImageUrl();
                                    @endphp
                                    <button type="button"
                                            wire:click="openDetail('{{ $order->id }}')"
                                            class="inline-flex items-center gap-2 pl-1 pr-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 border border-gray-200/90 dark:border-gray-800 hover:border-[var(--theme-color-50)] transition-all shrink-0 group/chip text-left shadow-2xs cursor-pointer"
                                            title="{{ $item->quantity }}× {{ $item->product_name }}{{ $item->product?->sku ? ' (' . $item->product->sku . ')' : '' }}">
                                        <span class="w-6 h-6 rounded-md bg-gray-100 dark:bg-gray-950 border border-gray-200/70 dark:border-gray-800 overflow-hidden shrink-0 flex items-center justify-center">
                                            @if($thumbUrl)
                                                <img src="{{ $thumbUrl }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover" loading="lazy">
                                            @else
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            @endif
                                        </span>
                                        <span class="text-[11px] font-black text-[var(--theme-color)] font-mono">{{ $item->quantity }}×</span>
                                        <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300 group-hover/chip:text-gray-900 dark:group-hover/chip:text-white max-w-[160px] truncate">{{ $item->product_name }}</span>
                                        @if($item->is_completed)
                                            <span class="text-[10px] text-emerald-500 font-black" title="Position fertiggestellt">✓</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="7" class="px-6 py-16 text-center text-gray-500">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 mb-4 shadow-sm dark:shadow-inner">
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
                    if ($isOverdue) $bgClass = 'bg-red-50 dark:bg-red-900/10 border-l-4 border-l-red-500';
                    elseif ($order->is_express && !$isDone) $bgClass = 'bg-amber-50 dark:bg-amber-900/10 border-l-4 border-l-amber-500';
                    elseif ($isDone) $bgClass = 'bg-gray-50/50 dark:bg-gray-950/50 opacity-70 border-l-4 border-l-transparent';
                    else $bgClass = 'border-l-4 border-l-transparent';
                @endphp
                <div wire:click="openDetail('{{ $order->id }}')"
                     class="p-5 active:bg-gray-100 dark:active:bg-gray-800/40 transition-colors cursor-pointer border-b border-gray-200 dark:border-gray-800 last:border-b-0 {{ $bgClass }}">

                    <div class="flex justify-between items-start mb-4">
                        <div class="flex flex-col gap-1.5">
                            <span class="font-mono font-bold text-gray-700 dark:text-gray-300 text-base tracking-wide flex items-center gap-2">
                                {{ $order->order_number }}
                                @if($order->shipping_address && serialize($order->billing_address) !== serialize($order->shipping_address))
                                    <svg class="w-3.5 h-3.5 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Abweichende Lieferadresse"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                @endif
                            </span>
                            @if($order->is_express || $order->deadline)
                                <span class="text-[9px] font-black {{ $order->is_express ? 'text-gray-700 dark:text-gray-200' : 'text-gray-500' }} uppercase tracking-widest inline-flex items-center gap-1">
                                    @if($order->is_express)
                                        <x-shop.express-info iconClass="w-3.5 h-3.5" />
                                    @else
                                        ZIEL
                                    @endif
                                    @if($order->deadline)
                                        <span class="text-gray-700">|</span> <span class="{{ $isOverdue ? 'text-red-400 font-black animate-pulse drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500' }}">{{ $order->deadline->format('d.m.y') }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>

                        <div class="relative inline-block w-36" onclick="event.stopPropagation()">
                            <select
                                wire:change="updateStatus('{{ $order->id }}', $event.target.value)"
                                class="appearance-none w-full text-[9px] font-black uppercase tracking-widest rounded-xl border border-transparent py-2 pl-3 pr-6 cursor-pointer focus:ring-2 focus:ring-[var(--theme-color-20)] shadow-sm outline-none {{ $statusColors[$order->status] ?? 'bg-gray-800 text-gray-400' }}"
                            >
                                @foreach($statusMap as $value => $label)
                                    <option value="{{ $value }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" {{ $order->status === $value ? 'selected' : '' }}>
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
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $order->customer_name }}</span>
                                @if($order->is_influencer_order)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] shrink-0">
                                        Influencer
                                    </span>
                                @endif
                            </div>
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
                                               onclick="event.stopPropagation()"
                                               class="inline-flex items-center gap-1 text-[9px] font-bold bg-gray-100 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 px-1.5 py-0.5 rounded text-gray-600 dark:text-gray-400 shadow-sm hover:text-gray-900 dark:hover:text-white transition-colors">
                                                PDF
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="text-right flex flex-col items-end">
                            <span class="font-serif font-bold text-xl text-[var(--theme-color)] whitespace-nowrap">
                                {{ number_format($order->total_price / 100, 2, ',', '.') }} €
                            </span>
                            @php
                                $totalItems = $order->items->count();
                                $completedItems = $order->items->where('is_completed', true)->count();
                                $progressPct = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0;
                            @endphp
                            @if($totalItems > 0 && !in_array($order->status, ['cancelled', 'refunded']))
                                <div class="mt-2 w-20 bg-gray-100 dark:bg-gray-950 rounded-full h-1 overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner flex">
                                    <div class="bg-emerald-500 h-full transition-all duration-700 ease-out shadow-[0_0_10px_rgba(16,185,129,0.5)]" style="width: {{ $progressPct }}%"></div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($order->items && $order->items->isNotEmpty())
                        @php
                            $mUnits = (int) $order->items->sum('quantity');
                            $mDigital = $order->isOnlyDigital();
                            $mDhl = !$mDigital ? $order->getDhlWeightDetails(1, 'box') : null;
                            $mPackages = $mDhl ? (int) ($mDhl['recommended_package_count'] ?? 1) : 1;
                            if ($mPackages > 1 && $mDhl) {
                                $mDhl = $order->getDhlWeightDetails($mPackages, 'box');
                            }
                        @endphp
                        <div class="mt-3 pt-2.5 border-t border-gray-200/70 dark:border-gray-800/70 flex items-center gap-2 overflow-x-auto no-scrollbar">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-[10px] font-bold text-gray-700 dark:text-gray-300 shrink-0">
                                <span class="font-black text-[var(--theme-color)]">{{ $mUnits }}×</span> Prod.
                            </span>
                            @if($mDigital)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-500/10 border border-blue-500/20 text-[10px] font-bold text-blue-600 dark:text-blue-400 shrink-0">⚡ Digital</span>
                            @elseif($mDhl)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-[10px] font-bold text-gray-600 dark:text-gray-400 shrink-0">
                                    {{ !empty($mDhl['can_fit_in_envelope']) ? '✉️ 1 Tasche' : '📦 ' . $mPackages . ' ' . ($mPackages === 1 ? 'Paket' : 'Pakete') }} · {{ number_format($mDhl['total_weight_kg'] ?? 0.5, 2, ',', '.') }} kg
                                </span>
                            @endif

                            @foreach($order->items as $item)
                                @php
                                    $conf = is_array($item->configuration) ? $item->configuration : [];
                                    $snapshotImg = !empty($conf['snapshot_path'])
                                        ? (is_array($conf['snapshot_path']) ? ($conf['snapshot_path']['front'] ?? null) : $conf['snapshot_path'])
                                        : null;
                                    $thumbUrl = $snapshotImg ? asset('storage/' . ltrim($snapshotImg, '/')) : $item->resolveDisplayImageUrl();
                                @endphp
                                <span class="inline-flex items-center gap-1.5 pl-1 pr-2 py-0.5 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 shrink-0">
                                    <span class="w-5 h-5 rounded bg-gray-100 dark:bg-gray-900 overflow-hidden shrink-0 flex items-center justify-center">
                                        @if($thumbUrl)
                                            <img src="{{ $thumbUrl }}" alt="{{ $item->product_name }}" class="w-full h-full object-cover" loading="lazy">
                                        @endif
                                    </span>
                                    <span class="text-[10px] font-black text-[var(--theme-color)] font-mono">{{ $item->quantity }}×</span>
                                    <span class="text-[10px] font-medium text-gray-700 dark:text-gray-300 max-w-[120px] truncate">{{ $item->product_name }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </x-slot>
    </x-table.master>
</div>
