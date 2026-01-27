<div>
    <div>
        @if($showModal && $invoice)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                <div class="bg-gray-100 w-full max-w-4xl h-[90vh] rounded-xl flex flex-col shadow-2xl overflow-hidden">

                    {{-- Toolbar --}}
                    <div class="bg-white p-4 border-b flex justify-between items-center shrink-0">
                        <h3 class="font-bold text-gray-800">
                            Vorschau: {{ $invoice->invoice_number }}
                            @if($invoice->status === 'cancelled') <span class="text-red-600">(STORNIERT)</span> @endif
                        </h3>
                        <div class="flex gap-2">
                            @if($invoice->type === 'invoice' && $invoice->status !== 'cancelled')
                                <button wire:click="cancelInvoice"
                                        wire:confirm="Soll diese Rechnung wirklich storniert werden? Es wird eine Gegenbuchung erzeugt."
                                        class="bg-red-50 text-red-600 px-3 py-1.5 rounded hover:bg-red-100 text-sm font-bold border border-red-200">
                                    Stornieren
                                </button>
                            @endif
                            <button onclick="window.print()" class="bg-gray-800 text-white px-3 py-1.5 rounded hover:bg-black text-sm font-bold">
                                Drucken / PDF
                            </button>
                            <button wire:click="closeModal" class="text-gray-500 hover:text-gray-800 px-3">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- A4 Preview Container --}}
                    <div class="flex-1 overflow-y-auto p-8 bg-gray-500/10 flex justify-center">

                        {{-- DAS RECHNUNGSPAPIER --}}
                        <div class="bg-white w-[210mm] min-h-[297mm] shadow-lg p-[20mm] text-gray-800 text-sm leading-normal relative">

                            {{-- Absender / Header --}}
                            <div class="flex justify-between items-start mb-12 border-b border-gray-100 pb-8">
                                <div>
                                    {{-- Logo Platzhalter --}}
                                    <div class="font-serif text-2xl font-bold text-primary mb-2">Mein Seelenfunke</div>
                                    <div class="text-xs text-gray-500">
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
                                    <table class="text-right ml-auto text-xs">
                                        <tr>
                                            <td class="text-gray-500 pr-4">Nummer:</td>
                                            <td class="font-bold">{{ $invoice->invoice_number }}</td>
                                            </li>
                                        <tr>
                                            <td class="text-gray-500 pr-4">Datum:</td>
                                            <td>{{ $invoice->invoice_date->format('d.m.Y') }}</td>
                                            </li>
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
                                <div class="text-xs text-gray-400 underline mb-2">Mein Seelenfunke, Carl-Goerdeler-Ring 26, 38518 Gifhorn</div>
                                <div class="font-bold text-base">
                                    {{ $invoice->billing_address['company'] ?? '' }}<br>
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
                                    <p class="mt-2">hiermit stornieren wir die Rechnung Nr. <strong>{{ $invoice->parent->invoice_number ?? '???' }}</strong>. Der Betrag wird Ihnen erstattet.</p>
                                @else
                                    <p>Vielen Dank für Ihre Bestellung. Wir stellen Ihnen folgende Leistungen in Rechnung:</p>
                                @endif
                            </div>

                            {{-- Tabelle --}}
                            <table class="w-full mb-8 border-collapse">
                                <thead>
                                <tr class="border-b-2 border-gray-800 text-xs uppercase font-bold text-gray-600">
                                    <th class="text-left py-2">Pos.</th>
                                    <th class="text-left py-2">Bezeichnung</th>
                                    <th class="text-right py-2">Menge</th>
                                    <th class="text-right py-2">Einzel</th>
                                    <th class="text-right py-2">Gesamt</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($invoice->items as $index => $item)
                                    <tr class="border-b border-gray-200">
                                        <td class="py-3 text-gray-500 w-10">{{ $index + 1 }}</td>
                                        <td class="py-3">
                                            <span class="font-bold block">{{ $item->product_name }}</span>
                                            @if(!empty($item->configuration))
                                                <span class="text-xs text-gray-500">
                                                Konfiguration vorhanden
                                            </span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right">{{ $item->quantity }}</td>
                                        <td class="py-3 text-right">
                                            {{-- Bei Storno negative Preise anzeigen --}}
                                            {{ number_format(($invoice->isCreditNote() ? -$item->unit_price : $item->unit_price) / 100, 2, ',', '.') }} €
                                        </td>
                                        <td class="py-3 text-right font-bold">
                                            {{ number_format(($invoice->isCreditNote() ? -$item->total_price : $item->total_price) / 100, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot class="text-right">
                                <tr>
                                    <td colspan="4" class="pt-4 pb-1 text-gray-500">Zwischensumme (Netto/Brutto gemischt)</td>
                                    <td class="pt-4 pb-1 font-medium">{{ number_format($invoice->subtotal / 100, 2, ',', '.') }} €</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="py-1 text-gray-500">Versandkosten</td>
                                    <td class="py-1 font-medium">{{ number_format($invoice->shipping_cost / 100, 2, ',', '.') }} €</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="py-1 text-gray-500">Enthaltene MwSt. (19%)</td>
                                    <td class="py-1 text-xs text-gray-500">{{ number_format($invoice->tax_amount / 100, 2, ',', '.') }} €</td>
                                </tr>
                                <tr class="text-lg">
                                    <td colspan="4" class="pt-4 font-bold">Gesamtbetrag</td>
                                    <td class="pt-4 font-bold text-primary">{{ number_format($invoice->total / 100, 2, ',', '.') }} €</td>
                                </tr>
                                </tfoot>
                            </table>

                            {{-- Footer --}}
                            <div class="mt-auto pt-12 border-t border-gray-200 text-xs text-gray-500 flex justify-between">
                                <div>
                                    <strong>Bankverbindung:</strong><br>
                                    Bank: FOLGT<br>
                                    IBAN: FOLGT<br>
                                    BIC: FOLGT
                                </div>
                                <div>
                                    <strong>Kontakt:</strong><br>
                                    Web: www.mein-seelenfunke.de<br>
                                    Mail: kontakt@mein-seelenfunke.de<br>
                                    USt-ID: FOLGT
                                </div>
                                <div>
                                    Es gelten unsere AGB.<br>
                                    Gerichtsstand ist Gifhorn.
                                </div>
                            </div>

                            {{-- Stempel bei Bezahlt --}}
                            @if($invoice->status === 'paid' && !$invoice->isCreditNote())
                                <div class="absolute top-1/3 right-20 border-4 border-green-600 text-green-600 font-bold text-4xl p-4 rotate-[-15deg] opacity-30 select-none pointer-events-none">
                                    BEZAHLT
                                </div>
                            @endif
                            @if($invoice->status === 'cancelled')
                                <div class="absolute top-1/3 right-20 border-4 border-red-600 text-red-600 font-bold text-4xl p-4 rotate-[-15deg] opacity-50 select-none pointer-events-none">
                                    STORNIERT
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
