<div>
    <div>
        @if($showModal && $invoice)
            {{-- Hintergrund-Overlay --}}
            <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-2 md:p-4">

                {{-- Modal Container --}}
                <div class="bg-gray-100 w-full max-w-5xl h-[95vh] md:h-[90vh] rounded-2xl flex flex-col shadow-2xl overflow-hidden relative animate-modal-up">

                    {{-- Toolbar --}}
                    <div class="bg-white p-4 border-b flex justify-between items-center shrink-0">
                        <div>
                            <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                Vorschau: {{ $invoice->invoice_number }}
                                @if($invoice->status === 'cancelled')
                                    <span class="bg-red-100 text-red-600 text-[10px] px-2 py-0.5 rounded-full uppercase font-bold">Storniert</span>
                                @endif
                            </h3>
                        </div>
                        <div class="flex gap-2">
                            @if($invoice->type === 'invoice' && $invoice->status !== 'cancelled')
                                <button wire:click="cancelInvoice"
                                        wire:confirm="Soll diese Rechnung wirklich storniert werden? Es wird eine Gegenbuchung erzeugt."
                                        class="hidden md:block bg-red-50 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-100 text-xs font-bold border border-red-200 transition">
                                    Stornieren
                                </button>
                            @endif
                            <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-1.5 rounded-lg hover:bg-black text-xs font-bold transition shadow-sm">
                                Drucken / PDF
                            </button>
                            <button wire:click="closeModal" class="bg-gray-100 text-gray-500 p-1.5 rounded-lg hover:bg-gray-200 transition">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- A4 Preview Container (Scrollbar-Bereich) --}}
                    <div class="flex-1 overflow-y-auto p-4 md:p-12 bg-gray-500/10 flex justify-center scrollbar-hide">

                        {{-- DAS RECHNUNGSPAPIER --}}
                        <div id="printable-invoice" class="bg-white w-full md:w-[210mm] min-h-[297mm] shadow-lg p-6 md:p-[20mm] text-gray-800 text-xs md:text-sm leading-normal relative">

                            {{-- Absender / Header --}}
                            <div class="flex justify-between items-start mb-12 border-b border-gray-100 pb-8">
                                <div>
                                    <div class="font-serif text-2xl font-bold text-primary mb-2">Mein Seelenfunke</div>
                                    <div class="text-[10px] md:text-xs text-gray-500 leading-tight">
                                        Alina Steinhauer<br>
                                        Carl-Goerdeler-Ring 26<br>
                                        38518 Gifhorn<br>
                                        Deutschland
                                    </div>
                                </div>
                                <div class="text-right">
                                    <h1 class="text-xl font-bold uppercase tracking-wider text-gray-900 mb-2">
                                        {{ $invoice->isCreditNote() ? 'Gutschrift / Storno' : 'Rechnung' }}
                                    </h1>
                                    <table class="text-right ml-auto text-[11px] md:text-xs">
                                        <tr>
                                            <td class="text-gray-500 pr-4">Nummer:</td>
                                            <td class="font-bold">{{ $invoice->invoice_number }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-gray-500 pr-4">Datum:</td>
                                            <td>{{ $invoice->invoice_date->format('d.m.Y') }}</td>
                                        </tr>
                                        @if($invoice->type === 'invoice')
                                            <tr>
                                                <td class="text-gray-500 pr-4">Kundennr.:</td>
                                                <td>{{ $invoice->customer_id ? substr($invoice->customer_id, 0, 8) : 'Gast' }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td class="text-gray-500 pr-4">Bestellung:</td>
                                            <td>{{ $invoice->order->order_number ?? '-' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            {{-- Empfänger --}}
                            <div class="mb-12">
                                <div class="text-[9px] md:text-xs text-gray-400 underline mb-2 italic">Mein Seelenfunke, Carl-Goerdeler-Ring 26, 38518 Gifhorn</div>
                                <div class="font-bold text-sm md:text-base leading-snug text-gray-900">
                                    {{ $invoice->billing_address['company'] ?? '' }}@if(!empty($invoice->billing_address['company']))<br>@endif
                                    {{ $invoice->billing_address['first_name'] }} {{ $invoice->billing_address['last_name'] }}<br>
                                    {{ $invoice->billing_address['address'] }}<br>
                                    {{ $invoice->billing_address['postal_code'] }} {{ $invoice->billing_address['city'] }}<br>
                                    {{ $invoice->billing_address['country'] }}
                                </div>
                            </div>

                            {{-- Intro Text --}}
                            <div class="mb-8">
                                @if($invoice->isCreditNote())
                                    <p>Sehr geehrte Damen und Herren,</p>
                                    <p class="mt-2 text-gray-900">hiermit stornieren wir die Rechnung Nr. <strong>{{ $invoice->parent->invoice_number ?? '???' }}</strong>. Der Betrag wird Ihnen erstattet.</p>
                                @else
                                    <p class="text-gray-900">Vielen Dank für Ihre Bestellung. Wir stellen Ihnen folgende Leistungen in Rechnung:</p>
                                @endif
                            </div>

                            {{-- Tabelle --}}
                            <table class="w-full mb-8 border-collapse">
                                <thead>
                                <tr class="border-b-2 border-gray-800 text-[10px] md:text-xs uppercase font-bold text-gray-600">
                                    <th class="text-left py-2">Pos.</th>
                                    <th class="text-left py-2">Bezeichnung</th>
                                    <th class="text-right py-2">Menge</th>
                                    <th class="text-right py-2">Einzel</th>
                                    <th class="text-right py-2">Gesamt</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($invoice->items as $index => $item)
                                    <tr class="border-b border-gray-100">
                                        <td class="py-3 text-gray-400 w-8 md:w-10">{{ $index + 1 }}</td>
                                        <td class="py-3">
                                            <span class="font-bold block text-gray-900">{{ $item->product_name }}</span>
                                            @if(!empty($item->configuration))
                                                <span class="text-[10px] text-gray-400 italic">Individuelle Konfiguration vorhanden</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right text-gray-900">{{ $item->quantity }}</td>
                                        <td class="py-3 text-right text-gray-900">
                                            {{ number_format(($invoice->isCreditNote() ? -$item->unit_price : $item->unit_price) / 100, 2, ',', '.') }} €
                                        </td>
                                        <td class="py-3 text-right font-bold text-gray-900">
                                            {{ number_format(($invoice->isCreditNote() ? -$item->total_price : $item->total_price) / 100, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot class="text-right">
                                <tr>
                                    <td colspan="4" class="pt-6 pb-1 text-gray-500 font-medium">Zwischensumme</td>
                                    <td class="pt-6 pb-1 font-bold text-gray-900">{{ number_format($invoice->subtotal / 100, 2, ',', '.') }} €</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="py-1 text-gray-500 font-medium">Versandkosten</td>
                                    <td class="py-1 font-bold text-gray-900">{{ number_format($invoice->shipping_cost / 100, 2, ',', '.') }} €</td>
                                </tr>

                                {{-- RABATTE ANZEIGEN --}}
                                @if($invoice->discount_amount > 0)
                                    <tr>
                                        <td colspan="4" class="py-1 text-red-500 font-medium italic">Abzüglich Gutschein</td>
                                        <td class="py-1 text-red-500 font-bold">-{{ number_format($invoice->discount_amount / 100, 2, ',', '.') }} €</td>
                                    </tr>
                                @endif
                                @if($invoice->volume_discount > 0)
                                    <tr>
                                        <td colspan="4" class="py-1 text-red-500 font-medium italic">Abzüglich Mengenrabatt</td>
                                        <td class="py-1 text-red-500 font-bold">-{{ number_format($invoice->volume_discount / 100, 2, ',', '.') }} €</td>
                                    </tr>
                                @endif

                                <tr>
                                    <td colspan="4" class="py-1 text-gray-400 italic">
                                        Enthaltene MwSt. ({{ config("shop.countries." . ($invoice->billing_address['country'] ?? 'DE') . ".tax_rate", 19) }}%)
                                    </td>
                                    <td class="py-1 text-xs text-gray-400 font-medium">{{ number_format($invoice->tax_amount / 100, 2, ',', '.') }} €</td>
                                </tr>
                                <tr class="text-base md:text-lg">
                                    <td colspan="4" class="pt-4 font-bold text-gray-900 uppercase tracking-tight">Gesamtbetrag</td>
                                    <td class="pt-4 font-bold text-primary">{{ number_format($invoice->total / 100, 2, ',', '.') }} €</td>
                                </tr>
                                </tfoot>
                            </table>

                            {{-- Footer Informationen --}}
                            <div class="mt-auto pt-12 border-t border-gray-100 text-[10px] text-gray-400 grid grid-cols-3 gap-4">
                                <div>
                                    <strong class="text-gray-600 uppercase">Bankverbindung:</strong><br>
                                    Bank: Sparkasse Gifhorn<br>
                                    IBAN: DE89 XXXX XXXX XXXX<br>
                                    BIC: XXXXXXXX
                                </div>
                                <div>
                                    <strong class="text-gray-600 uppercase">Kontakt:</strong><br>
                                    Web: www.mein-seelenfunke.de<br>
                                    Mail: kontakt@mein-seelenfunke.de<br>
                                    USt-ID: DE3456789
                                </div>
                                <div class="text-right">
                                    Es gelten unsere AGB.<br>
                                    Gerichtsstand ist Gifhorn.<br>
                                    Vielen Dank für Ihren Einkauf!
                                </div>
                            </div>

                            {{-- Wasserzeichen / Stempel --}}
                            @if($invoice->status === 'paid' && !$invoice->isCreditNote())
                                <div class="absolute top-1/3 right-10 md:right-20 border-[6px] border-green-600/20 text-green-600/20 font-black text-6xl md:text-8xl p-4 rotate-[-15deg] select-none pointer-events-none uppercase tracking-tighter shadow-sm">
                                    Bezahlt
                                </div>
                            @endif
                            @if($invoice->status === 'cancelled')
                                <div class="absolute top-1/3 right-10 md:right-20 border-[6px] border-red-600/30 text-red-600/30 font-black text-6xl md:text-8xl p-4 rotate-[-15deg] select-none pointer-events-none uppercase tracking-tighter shadow-sm">
                                    Storniert
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
