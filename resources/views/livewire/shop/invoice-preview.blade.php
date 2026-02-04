<div>
    <div>
        @if($showModal && $invoice)
            <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-2 md:p-4">
                <div class="bg-gray-100 w-full max-w-5xl h-[95vh] md:h-[90vh] rounded-2xl flex flex-col shadow-2xl overflow-hidden relative animate-modal-up">
                    {{-- Modal Header --}}
                    <div class="bg-white p-4 border-b flex justify-between items-center shrink-0">
                        <div>
                            <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                Vorschau: {{ $invoice->invoice_number }}
                                @if($invoice->status === 'cancelled')
                                    <span class="bg-red-100 text-red-600 text-[10px] px-2 py-0.5 rounded-full uppercase font-bold">Storniert</span>
                                @elseif($invoice->status === 'draft')
                                    <span class="bg-gray-200 text-gray-600 text-[10px] px-2 py-0.5 rounded-full uppercase font-bold">Entwurf</span>
                                @endif
                                @if($invoice->is_e_invoice)
                                    <span class="bg-blue-100 text-blue-600 text-[10px] px-2 py-0.5 rounded-full uppercase font-bold tracking-widest">E-Rechnung</span>
                                @endif
                            </h3>
                        </div>
                        <div class="flex gap-2">
                            @if($invoice->type === 'invoice' && $invoice->status !== 'cancelled' && $invoice->status !== 'draft')
                                <button wire:click="cancelInvoice" wire:confirm="Soll diese Rechnung wirklich storniert werden? Es wird eine Gegenbuchung erzeugt." class="hidden md:block bg-red-50 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-100 text-xs font-bold border border-red-200 transition">Stornieren</button>
                            @endif
                            <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-1.5 rounded-lg hover:bg-black text-xs font-bold transition shadow-sm">Drucken / PDF</button>
                            <button wire:click="closeModal" class="bg-gray-100 text-gray-500 p-1.5 rounded-lg hover:bg-gray-200 transition">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12" stroke-width="2"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Invoice Area --}}
                    <div class="flex-1 overflow-y-auto p-2 md:p-12 bg-gray-500/10 flex justify-center scrollbar-hide">
                        <div id="printable-invoice" class="bg-white w-full max-w-[210mm] min-h-[297mm] shadow-lg p-4 md:p-[20mm] text-gray-800 text-xs md:text-sm leading-normal relative overflow-x-auto flex flex-col">

                            {{-- STEMPEL-LAYER --}}
                            @if($invoice->status === 'paid' && $invoice->type !== 'cancellation')
                                {{-- GRÜNER BEZAHLT STEMPEL --}}
                                <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 border-[12px] border-green-600/20 text-green-600/20 font-black text-6xl md:text-9xl p-4 rotate-[-20deg] select-none pointer-events-none uppercase tracking-tighter z-0" style="color: rgba(22, 163, 74, 0.2); border-color: rgba(22, 163, 74, 0.2);">
                                    Bezahlt
                                </div>
                            @endif

                            @if($invoice->status === 'cancelled' || $invoice->type === 'cancellation')
                                {{-- ROTER STORNIERT STEMPEL --}}
                                <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 border-[12px] border-red-600/20 text-red-600/20 font-black text-6xl md:text-9xl p-4 rotate-[-15deg] select-none pointer-events-none uppercase tracking-tighter z-0" style="color: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.2);">
                                    Storniert
                                </div>
                            @endif

                            {{-- Content Layer (z-10 sorgt dafür, dass Text über dem Stempel bleibt) --}}
                            <div class="relative z-10 flex flex-col flex-1">
                                <div class="flex justify-between items-start mb-8 md:mb-12 border-b border-gray-100 pb-8">
                                    <div>
                                        <div class="font-serif text-xl md:text-2xl font-bold text-primary mb-2">Mein Seelenfunke</div>
                                        <div class="text-[9px] md:text-xs text-gray-500 leading-tight">Alina Steinhauer<br>Carl-Goerdeler-Ring 26<br>38518 Gifhorn<br>Deutschland</div>
                                    </div>
                                    <div class="text-right">
                                        <h1 class="text-lg md:text-xl font-bold uppercase tracking-wider text-gray-900 mb-2">
                                            {{ $invoice->isCreditNote() ? 'Gutschrift' : 'Rechnung' }}
                                        </h1>
                                        <table class="text-right ml-auto text-[10px] md:text-xs">
                                            <tr><td class="text-gray-500 pr-4 uppercase font-bold text-[8px]">Nummer:</td><td class="font-bold">{{ $invoice->invoice_number }}</td></tr>
                                            <tr><td class="text-gray-500 pr-4 uppercase font-bold text-[8px]">Datum:</td><td>{{ $invoice->invoice_date->format('d.m.Y') }}</td></tr>
                                            @if($invoice->delivery_date)<tr><td class="text-gray-500 pr-4 uppercase font-bold text-[8px]">Leistung:</td><td>{{ $invoice->delivery_date->format('d.m.Y') }}</td></tr>@endif
                                            @if($invoice->reference_number)<tr><td class="text-gray-500 pr-4 uppercase font-bold text-[8px]">Referenz:</td><td>{{ $invoice->reference_number }}</td></tr>@endif
                                        </table>
                                    </div>
                                </div>

                                <div class="mb-10 md:mb-12">
                                    <div class="text-[8px] md:text-xs text-gray-400 underline mb-2 italic">Mein Seelenfunke, Carl-Goerdeler-Ring 26, 38518 Gifhorn</div>
                                    <div class="font-bold text-sm md:text-base leading-snug text-gray-900">
                                        @if(!empty($invoice->billing_address['company'])) {{ $invoice->billing_address['company'] }}<br> @endif
                                        {{ $invoice->billing_address['first_name'] }} {{ $invoice->billing_address['last_name'] }}<br>
                                        {{ $invoice->billing_address['address'] }}<br>
                                        @if(!empty($invoice->billing_address['address_addition'])) {{ $invoice->billing_address['address_addition'] }}<br> @endif
                                        {{ $invoice->billing_address['postal_code'] }} {{ $invoice->billing_address['city'] }}<br>
                                        {{ $invoice->billing_address['country'] }}
                                    </div>
                                </div>

                                <div class="mb-8 prose prose-sm max-w-none">
                                    <div class="font-bold text-base mb-4 border-l-2 border-primary pl-4">{{ $invoice->subject }}</div>
                                    <div class="text-gray-700 whitespace-pre-line leading-relaxed">{!! $invoice->parsed_header_text !!}</div>
                                </div>

                                <table class="w-full mb-8 border-collapse">
                                    <thead>
                                    <tr class="border-b-2 border-gray-800 text-[10px] md:text-xs uppercase font-bold text-gray-600">
                                        <th class="text-left py-2">Pos</th>
                                        <th class="text-left py-2">Bezeichnung</th>
                                        <th class="text-right py-2">Menge</th>
                                        <th class="text-right py-2">Einzel</th>
                                        <th class="text-center py-2">USt.</th>
                                        <th class="text-right py-2">Gesamt</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($invoice->items as $index => $item)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-3 text-gray-400 w-8">{{ $index + 1 }}</td>
                                            <td class="py-3">
                                                <span class="font-bold block text-gray-900">{{ is_object($item) ? $item->product_name : $item['product_name'] }}</span>
                                                @php $config = is_object($item) ? $item->configuration : ($item['configuration'] ?? null); @endphp
                                                @if($config && !empty($config['text']))
                                                    <span class="text-[10px] text-gray-400 italic">Gravur: {{ $config['text'] }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3 text-right">{{ is_object($item) ? $item->quantity : $item['quantity'] }}</td>
                                            <td class="py-3 text-right">{{ number_format((is_object($item) ? $item->unit_price : $item['unit_price']) / 100, 2, ',', '.') }} €</td>
                                            <td class="py-3 text-center text-[10px]">{{ is_object($item) ? $item->tax_rate : $item['tax_rate'] }}%</td>
                                            <td class="py-3 text-right font-bold">{{ number_format((is_object($item) ? $item->total_price : $item['total_price']) / 100, 2, ',', '.') }} €</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                    <tfoot class="text-right">
                                    <tr>
                                        <td colspan="5" class="pt-6 pb-1 text-gray-500 font-medium text-[10px] uppercase">Zwischensumme Netto</td>
                                        <td class="pt-6 pb-1 font-bold text-gray-900">{{ number_format(($invoice->subtotal - $invoice->tax_amount) / 100, 2, ',', '.') }} €</td>
                                    </tr>
                                    @if($invoice->shipping_cost > 0)
                                        <tr><td colspan="5" class="py-1 text-gray-500 text-[10px] uppercase">Versandkosten</td><td class="py-1 font-bold">{{ number_format($invoice->shipping_cost / 100, 2, ',', '.') }} €</td></tr>
                                    @endif

                                    @if($invoice->volume_discount > 0)
                                        <tr><td colspan="5" class="py-1 text-red-500 text-[10px] uppercase italic">Abzüglich Mengenrabatt</td><td class="py-1 text-red-500 font-bold">-{{ number_format($invoice->volume_discount / 100, 2, ',', '.') }} €</td></tr>
                                    @endif
                                    @if($invoice->discount_amount > 0)
                                        <tr><td colspan="5" class="py-1 text-red-500 text-[10px] uppercase italic">Abzüglich Gutschein</td><td class="py-1 text-red-500 font-bold">-{{ number_format($invoice->discount_amount / 100, 2, ',', '.') }} €</td></tr>
                                    @endif

                                    <tr><td colspan="5" class="py-1 text-gray-400 italic">Umsatzsteuer Betrag</td><td class="py-1 text-xs text-gray-400 font-medium">{{ number_format($invoice->tax_amount / 100, 2, ',', '.') }} €</td></tr>
                                    <tr class="text-base md:text-lg"><td colspan="5" class="pt-4 font-black text-gray-900 uppercase tracking-tighter">Gesamt Brutto</td><td class="pt-4 font-black text-primary border-t-2 border-primary">{{ number_format($invoice->total / 100, 2, ',', '.') }} €</td></tr>
                                    </tfoot>
                                </table>

                                <div class="mb-12 prose prose-sm max-w-none italic text-gray-600 whitespace-pre-line">
                                    {!! $invoice->parsed_footer_text !!}
                                </div>

                                {{-- Dynamischer Footer --}}
                                <div class="mt-auto pt-8 border-t border-gray-100 text-center">
                                    <p class="text-[11px] text-gray-600 leading-relaxed">
                                        <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong> | Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                                        {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}<br>
                                        <span class="text-primary">{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}</span> |
                                        <span>{{ str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de')) }}</span>
                                    </p>
                                    <p class="text-[9px] text-gray-400 mt-3 leading-tight tracking-tight uppercase">
                                        IBAN: {{ shop_setting('owner_iban', 'Wird nachgereicht') }} |
                                        Steuernummer: {{ shop_setting('owner_tax_id') }}
                                        @if(shop_setting('owner_ust_id')) | USt-IdNr.: {{ shop_setting('owner_ust_id') }} @endif
                                        | Gerichtsstand: {{ shop_setting('owner_court', 'Gifhorn') }}
                                    </p>
                                </div>
                            </div> {{-- End Content Layer --}}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
