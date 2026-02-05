<div>
    <div>
        @if($showModal && $invoice)
            <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-2 md:p-4">
                <div class="bg-gray-200 w-full max-w-5xl h-[95vh] md:h-[90vh] rounded-2xl flex flex-col shadow-2xl overflow-hidden relative animate-modal-up">
                    {{-- Modal Header Toolbar --}}
                    <div class="bg-white p-4 border-b flex justify-between items-center shrink-0">
                        <div>
                            <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                <span style="color: #C5A059">Belegvorschau:</span> {{ $invoice->invoice_number }}
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
                            <button wire:click="downloadPdf" class="text-white px-4 py-1.5 rounded-lg hover:opacity-90 text-xs font-bold transition shadow-sm flex items-center gap-2" style="background-color: #C5A059">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                PDF Download
                            </button>
                            <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-1.5 rounded-lg hover:bg-black text-xs font-bold transition shadow-sm">Drucken</button>
                            <button wire:click="closeModal" class="bg-gray-100 text-gray-500 p-1.5 rounded-lg hover:bg-gray-200 transition">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Invoice Area --}}
                    <div class="flex-1 overflow-y-auto p-4 md:p-12 bg-gray-500/20 flex justify-center scrollbar-hide">
                        {{-- Das Blatt Papier --}}
                        <div id="printable-invoice" class="bg-white w-full max-w-[210mm] min-h-[297mm] shadow-2xl p-8 md:p-[15mm] text-gray-800 relative flex flex-col" style="font-family: sans-serif; font-size: 11px; line-height: 1.4;">

                            @php
                                $data = $invoice->toFormattedArray();
                                $ownerName = shop_setting('owner_name', 'Mein Seelenfunke');
                                $proprietor = shop_setting('owner_proprietor', 'Alina Steinhauer');
                                $ownerStreet = shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
                                $ownerCity = shop_setting('owner_city', '38518 Gifhorn');
                                $ownerEmail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
                                $ownerWeb = shop_setting('owner_website', 'www.mein-seelenfunke.de');
                                $ownerIban = shop_setting('owner_iban', 'Wird nachgereicht');
                                $ownerBic = shop_setting('owner_bic', '');
                                $taxId = shop_setting('owner_tax_id', '19/143/11624');
                                $ustId = shop_setting('owner_ust_id');
                                $court = shop_setting('owner_court', 'Gifhorn');
                                $isSmallBusiness = (bool)shop_setting('is_small_business', false);
                            @endphp

                            {{-- STEMPEL FIXIERT --}}
                            @if($invoice->status === 'paid' && $invoice->type !== 'cancellation')
                                <div class="absolute top-[20%] left-1/2 -translate-x-1/2 -rotate-[20deg] border-[8px] border-[#16a34a] text-[#16a34a] font-black text-7xl md:text-8xl p-4 px-10 select-none pointer-events-none uppercase tracking-tighter opacity-20 z-0">
                                    Bezahlt
                                </div>
                            @endif

                            @if($invoice->status === 'cancelled' || $invoice->type === 'cancellation')
                                <div class="absolute top-[20%] left-1/2 -translate-x-1/2 -rotate-[15deg] border-[8px] border-[#dc2626] text-[#dc2626] font-black text-6xl md:text-7xl p-4 px-10 select-none pointer-events-none uppercase tracking-tighter opacity-20 z-0">
                                    Storniert
                                </div>
                            @endif

                            {{-- Content Layer --}}
                            <div class="relative z-10 flex flex-col flex-1">

                                {{-- Header: Logo & Titel --}}
                                <div class="flex justify-between items-end mb-8 pb-5" style="border-bottom: 2px solid #C5A059;">
                                    <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" style="width: 220px;" alt="{{ $ownerName }}">
                                    <div class="text-right">
                                        <div style="font-size: 22px; font-weight: bold; color: #C5A059; text-transform: uppercase; margin-bottom: 5px;">
                                            @if($invoice->type === 'cancellation') STORNO-RECHNUNG @else RECHNUNG @endif
                                        </div>
                                        <div class="text-gray-500 text-[10px]">
                                            <strong>Nummer:</strong> {{ $invoice->invoice_number }}<br>
                                            <strong>Datum:</strong> {{ $invoice->invoice_date->format('d.m.Y') }}<br>
                                            @if($invoice->delivery_date) <strong>Leistungsdatum:</strong> {{ $invoice->delivery_date->format('d.m.Y') }}<br> @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Meta: Adressen --}}
                                <div class="flex justify-between mb-10">
                                    <div class="w-1/2">
                                        <div class="text-[8px] text-gray-400 underline mb-1">{{ $ownerName }} · {{ $ownerStreet }} · {{ $ownerCity }}</div>
                                        <div class="text-[11px] leading-relaxed">
                                            <strong>
                                                @if(!empty($data['contact']['firma'])) {{ $data['contact']['firma'] }}<br> @endif
                                                {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}
                                            </strong><br>
                                            {{ $data['billing_address']['address'] ?? '' }}<br>
                                            @if(!empty($data['billing_address']['address_addition'])) {{ $data['billing_address']['address_addition'] }}<br> @endif
                                            {{ $data['billing_address']['postal_code'] ?? '' }} {{ $data['billing_address']['city'] ?? '' }}<br>
                                            {{ $data['billing_address']['country'] ?? 'DE' }}
                                        </div>
                                    </div>
                                    <div class="w-1/2 text-right text-[11px] leading-relaxed">
                                        <strong>{{ $ownerName }}</strong><br>
                                        Inh. {{ $proprietor }}<br>
                                        {{ $ownerStreet }}<br>
                                        {{ $ownerCity }}<br>
                                        Deutschland<br><br>
                                        E-Mail: {{ $ownerEmail }}<br>
                                        Web: {{ str_replace(['http://', 'https://'], '', $ownerWeb) }}
                                    </div>
                                </div>

                                {{-- Betreff & Header Text --}}
                                <div class="mb-6">
                                    <div style="font-size: 13px; font-weight: bold; margin-bottom: 10px;">{{ $invoice->subject ?? 'Rechnung' }}</div>
                                    <div class="whitespace-pre-line text-gray-700">
                                        {!! nl2br(e($invoice->parsed_header_text)) !!}
                                    </div>
                                </div>

                                {{-- Items Table --}}
                                <table class="w-full mb-8 border-collapse">
                                    <thead>
                                    <tr class="border-b" style="border-color: #eee;">
                                        <th class="text-left py-2 text-[10px] text-gray-400 uppercase tracking-wider">Artikel & Konfiguration</th>
                                        <th class="text-right py-2 text-[10px] text-gray-400 uppercase tracking-wider w-16">Menge</th>
                                        <th class="text-right py-2 text-[10px] text-gray-400 uppercase tracking-wider w-24">Preis</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                    @php
                                        $displayItems = $invoice->items;
                                        if($invoice->type === 'cancellation' && count($displayItems) === 0 && $invoice->parent_id) {
                                            $parentInvoice = \App\Models\Invoice::find($invoice->parent_id);
                                            if($parentInvoice) { $displayItems = $parentInvoice->items; }
                                        }
                                    @endphp
                                    @foreach($displayItems as $item)
                                        <tr>
                                            <td class="py-4 align-top">
                                                <strong class="text-[13px] text-gray-900 block mb-1">{{ is_object($item) ? $item->product_name : $item['product_name'] }}</strong>

                                                @php
                                                    $config = null;
                                                    if(is_object($item) && isset($item->configuration)) {
                                                        $config = $item->configuration;
                                                    } elseif(is_array($item) && isset($item['configuration'])) {
                                                        $config = $item['configuration'];
                                                    }
                                                @endphp

                                                @if($config)
                                                    <div class="text-[10px] text-gray-500 italic leading-snug">
                                                        @foreach($config as $label => $value)
                                                            @if(!empty($value) && !in_array($label, ['image', 'product_image_path', 'logo_storage_path', 'text_x', 'text_y', 'logo_x', 'logo_y']))
                                                                @php
                                                                    $displayValue = is_array($value)
                                                                        ? implode(', ', array_map(fn($v) => is_array($v) ? json_encode($v) : $v, $value))
                                                                        : $value;
                                                                @endphp
                                                                <strong>{{ ucfirst($label) }}:</strong> {{ $displayValue }}@if(!$loop->last) · @endif
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="py-4 align-top text-right font-medium text-gray-900 text-[12px]">{{ is_object($item) ? $item->quantity : $item['quantity'] }}x</td>
                                            <td class="py-4 align-top text-right font-bold text-gray-900 text-[12px]">
                                                {{ number_format((is_object($item) ? $item->total_price : $item['total_price']) / 100, 2, ',', '.') }} €
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>

                                {{-- Totals Area --}}
                                <div class="mt-auto pt-4 border-t-2 border-gray-100">
                                    <div class="flex justify-end">
                                        <div class="w-full max-w-[280px] space-y-1.5 text-right">
                                            <div class="flex justify-between text-gray-500">
                                                <span>Warenwert (Brutto):</span>
                                                @php
                                                    $totalGrossNum = (float)($invoice->total / 100);
                                                    $shippingGrossNum = (float)($invoice->shipping_cost / 100);
                                                    $expressGrossNum = ($invoice->is_express) ? (float)shop_setting('express_surcharge', 2500) / 100 : 0;
                                                    $goodsGrossCalculated = $totalGrossNum - $shippingGrossNum - $expressGrossNum;
                                                @endphp
                                                <span class="font-medium">{{ number_format($goodsGrossCalculated, 2, ',', '.') }} €</span>
                                            </div>

                                            @if($invoice->shipping_cost > 0)
                                                <div class="flex justify-between text-gray-500">
                                                    <span>Versandkosten:</span>
                                                    <span class="font-medium">{{ number_format($invoice->shipping_cost / 100, 2, ',', '.') }} €</span>
                                                </div>
                                            @endif

                                            @if($invoice->is_express)
                                                <div class="flex justify-between text-red-500">
                                                    <span>Express-Service:</span>
                                                    <span class="font-medium">{{ number_format($expressGrossNum, 2, ',', '.') }} €</span>
                                                </div>
                                            @endif

                                            <div class="flex justify-between pt-3 border-t-2 border-gray-100" style="font-size: 16px; font-weight: bold; color: #C5A059;">
                                                <span>Gesamtsumme:</span>
                                                <span>{{ number_format($invoice->total / 100, 2, ',', '.') }} €</span>
                                            </div>

                                            <div class="pt-2 text-[10px] text-gray-400 italic space-y-0.5">
                                                <div class="flex justify-between">
                                                    <span>Nettobetrag:</span>
                                                    <span>{{ number_format(($invoice->total - $invoice->tax_amount) / 100, 2, ',', '.') }} €</span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span>{{ $isSmallBusiness ? 'Umsatzsteuerfrei gem. § 19 UStG.' : 'MwSt. ('.shop_setting('default_tax_rate', 19).'%):' }}</span>
                                                    <span>@if(!$isSmallBusiness) {{ number_format($invoice->tax_amount / 100, 2, ',', '.') }} € @endif</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Footer Text --}}
                                <div class="mt-10 whitespace-pre-line text-gray-600 italic">
                                    {!! nl2br(e($invoice->parsed_footer_text)) !!}
                                </div>

                                {{-- MODERN FOOTER - SIMULATION --}}
                                <div class="mt-12 pt-8" style="border-top: 1px solid #eee; color: #777;">
                                    <div class="flex justify-between text-[9px] leading-relaxed">
                                        <div class="w-1/3 text-left">
                                            <span style="color: #C5A059; font-weight: bold; text-transform: uppercase; font-size: 8px; letter-spacing: 0.8px; margin-bottom: 5px; display: block;">Unternehmen</span>
                                            <strong>{{ $ownerName }}</strong><br>
                                            Inhaberin {{ $proprietor }}<br>
                                            {{ $ownerStreet }}<br>
                                            {{ $ownerCity }}
                                        </div>
                                        <div class="w-1/3 text-left pl-4">
                                            <span style="color: #C5A059; font-weight: bold; text-transform: uppercase; font-size: 8px; letter-spacing: 0.8px; margin-bottom: 5px; display: block;">Kontakt</span>
                                            E-Mail: {{ $ownerEmail }}<br>
                                            Web: {{ str_replace(['http://', 'https://'], '', $ownerWeb) }}<br>
                                            USt-IdNr.: {{ $ustId ?? 'n.a.' }}<br>
                                            Steuernummer: {{ $taxId }}
                                        </div>
                                        <div class="w-1/3 text-left pl-4">
                                            <span style="color: #C5A059; font-weight: bold; text-transform: uppercase; font-size: 8px; letter-spacing: 0.8px; margin-bottom: 5px; display: block;">Bankverbindung</span>
                                            IBAN: {{ $ownerIban }}<br>
                                            @if($ownerBic) BIC: {{ $ownerBic }}<br> @endif
                                            Gerichtsstand: {{ $court }}
                                        </div>
                                    </div>
                                </div>

                            </div> {{-- End Content Layer --}}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
