{{-- Erstellungs-Formular --}}
<div class="flex flex-col md:flex-row justify-end items-center gap-4 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-gray-200">
    <div class="flex items-center gap-3">
        <span class="text-sm font-bold text-gray-700">E-Rechnung</span>
        @include('components.alerts.info-tooltip', ['key' => 'e_invoice'])
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" wire:model.live="manualInvoice.is_e_invoice" class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
        </label>
    </div>
    <div class="flex gap-2">
        <button wire:click="saveManualInvoice('draft')"
                class="px-4 py-2 rounded-lg transition text-sm font-bold uppercase {{ $draftSuccess ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
            {{ $draftSuccess ? 'Gespeichert' : 'Entwurf speichern' }}
        </button>
        <button wire:click="saveManualInvoice('paid')"
                class="px-4 py-2 rounded-lg transition text-sm font-bold uppercase {{ $saveSuccess ? 'bg-green-500 text-white' : 'bg-green-600 text-white hover:bg-green-700' }}">
            {{ $saveSuccess ? 'Rechnung erstellt' : 'Abschließen' }}
        </button>
    </div>
</div>

@if ($errors->any())
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-xl">
        <h4 class="text-red-800 font-bold mb-1 uppercase text-xs">Korrektur erforderlich:</h4>
        <ul class="list-disc list-inside text-xs text-red-700">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid lg:grid-cols-2 gap-8 animate-fade-in">
    <div class="space-y-8">
        {{-- Empfänger --}}
        <div class="bg-white p-6 rounded-2xl shadow-sm border {{ $errors->has('manualInvoice.last_name') ? 'border-red-300' : 'border-gray-200' }}">
            <div class="flex items-center justify-between mb-4 border-b pb-2">
                <h3 class="text-lg font-bold text-gray-900">Empfänger</h3>
                <div class="flex items-center gap-2">
                    @include('components.alerts.info-tooltip', ['key' => 'customer'])
                    <select wire:model.live="selectedCustomerId" class="border-gray-300 rounded-lg text-sm p-1 focus:border-primary">
                        <option value="">Bestandskunde wählen...</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->last_name }}, {{ $c->first_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="col-span-2">
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">E-Mail Adresse*</label>
                    <input type="email" wire:model.live="manualInvoice.customer_email" class="w-full border-2 rounded-lg text-sm p-3 focus:ring-0 shadow-sm {{ $errors->has('manualInvoice.customer_email') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Firma (Optional)</label>
                    <input type="text" wire:model.live="manualInvoice.company" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Vorname*</label>
                    <input type="text" wire:model.live="manualInvoice.first_name" class="w-full border-2 rounded-lg text-sm p-3 shadow-sm {{ $errors->has('manualInvoice.first_name') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Nachname*</label>
                    <input type="text" wire:model.live="manualInvoice.last_name" class="w-full border-2 rounded-lg text-sm p-3 shadow-sm {{ $errors->has('manualInvoice.last_name') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Straße & Hausnummer*</label>
                    <input type="text" wire:model.live="manualInvoice.address" class="w-full border-2 rounded-lg text-sm p-3 shadow-sm {{ $errors->has('manualInvoice.address') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Adresszusatz</label>
                    <input type="text" wire:model.live="manualInvoice.address_addition" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">PLZ*</label>
                    <input type="text" wire:model.live="manualInvoice.postal_code" class="w-full border-2 rounded-lg text-sm p-3 shadow-sm {{ $errors->has('manualInvoice.postal_code') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Stadt*</label>
                    <input type="text" wire:model.live="manualInvoice.city" class="w-full border-2 rounded-lg text-sm p-3 shadow-sm {{ $errors->has('manualInvoice.city') ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                </div>
            </div>
        </div>

        {{-- Rechnungsinformationen --}}
        <div class="bg-white p-6 rounded-2xl shadow-sm border {{ $errors->has('manualInvoice.invoice_number') ? 'border-red-300' : 'border-gray-200' }}">
            <div class="flex items-center gap-2 mb-4 border-b pb-2">
                <h3 class="text-lg font-bold text-gray-900">Rechnungsinformationen</h3>
                @include('components.alerts.info-tooltip', ['key' => 'invoice_info'])
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Rechnungsdatum*</label>
                    <input type="date" wire:model.live="manualInvoice.invoice_date" class="w-full border-2 rounded-lg text-sm p-3 shadow-sm {{ $errors->has('manualInvoice.invoice_date') ? 'border-red-400' : 'border-gray-300' }}">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Lieferdatum / Zeitraum*</label>
                    <input type="date" wire:model.live="manualInvoice.delivery_date" class="w-full border-2 rounded-lg text-sm p-3 shadow-sm {{ $errors->has('manualInvoice.delivery_date') ? 'border-red-400' : 'border-gray-300' }}">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Rechnungsnummer*</label>
                    <input type="text" wire:model.live="manualInvoice.invoice_number" class="w-full border-2 rounded-lg text-sm p-3 font-mono shadow-sm {{ $errors->has('manualInvoice.invoice_number') ? 'border-red-400' : 'border-gray-300' }}">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Referenznummer</label>
                    <input type="text" wire:model.live="manualInvoice.reference_number" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900 shadow-sm">
                </div>
                <div class="col-span-2 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Zahlungsziel (Datum)</label>
                        <input type="date" wire:model.live="manualInvoice.due_date" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900 shadow-sm bg-gray-50">
                    </div>
                    <div>
                        <div class="flex items-center gap-1 mb-1">
                            <label class="block text-xs font-bold uppercase text-gray-700">Zahlungsziel (Tage)</label>
                            @include('components.alerts.info-tooltip', ['key' => 'due_date'])
                        </div>
                        <input type="number" wire:model.live="manualInvoice.due_days" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900 shadow-sm">
                    </div>
                </div>
            </div>
        </div>

        {{-- Kopftext --}}
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <div class="flex items-center gap-2 mb-4 border-b pb-2">
                <h3 class="text-lg font-bold text-gray-900">Kopftext</h3>
                @include('components.alerts.info-tooltip', ['key' => 'header_text'])
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Betreff*</label>
                    <input type="text" wire:model.live="manualInvoice.subject" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900 shadow-sm focus:border-primary focus:ring-0">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Anschreiben (Freitext)</label>
                    <textarea wire:model.live.debounce.500ms="manualInvoice.header_text" rows="8" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900 shadow-sm focus:border-primary focus:ring-0 resize-none" placeholder="Geben Sie hier das Anschreiben ein..."></textarea>
                </div>
            </div>
        </div>

        {{-- Positionen --}}
        <div class="bg-white p-6 rounded-2xl shadow-sm border {{ $errors->has('manualInvoice.items') ? 'border-red-300' : 'border-gray-200' }} overflow-x-auto">
            <h3 class="text-lg font-bold mb-4 border-b pb-2 text-gray-900">Positionen</h3>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left text-xs font-bold uppercase text-gray-500 border-b">
                    <th class="pb-2">Produkt / Service*</th>
                    <th class="pb-2 text-center">Menge*</th>
                    <th class="pb-2 text-right">Preis Netto*</th>
                    <th class="pb-2 text-center">USt.</th>
                    <th class="pb-2 text-right">Betrag</th>
                    <th class="pb-2"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($manualInvoice['items'] as $index => $item)
                    <tr class="border-b" wire:key="item-{{ $index }}">
                        <td class="py-3">
                            <input type="text" wire:model.live="manualInvoice.items.{{$index}}.product_name" class="w-full border-2 rounded-lg p-2 text-sm {{ $errors->has('manualInvoice.items.'.$index.'.product_name') ? 'border-red-300' : 'border-gray-200' }}">
                        </td>
                        <td class="py-3 w-16">
                            <input type="number" wire:model.live="manualInvoice.items.{{$index}}.quantity" class="w-full border-2 rounded-lg p-2 text-sm text-center {{ $errors->has('manualInvoice.items.'.$index.'.quantity') ? 'border-red-300' : 'border-gray-200' }}">
                        </td>
                        <td class="py-3 w-24">
                            <input type="number" step="0.01" wire:model.live="manualInvoice.items.{{$index}}.unit_price" class="w-full border-2 rounded-lg p-2 text-sm text-right {{ $errors->has('manualInvoice.items.'.$index.'.unit_price') ? 'border-red-300' : 'border-gray-200' }}">
                        </td>
                        <td class="py-3 w-20 text-center">
                            <select wire:model.live="manualInvoice.items.{{$index}}.tax_rate" class="w-full border-2 border-gray-200 rounded-lg p-2 text-xs">
                                <option value="19">19%</option>
                                <option value="7">7%</option>
                                <option value="0">0%</option>
                            </select>
                        </td>
                        <td class="py-3 w-24 text-right font-bold">
                            {{ number_format(($item['unit_price'] ?: 0) * ($item['quantity'] ?: 0), 2, ',', '.') }} €
                        </td>
                        <td class="py-3 pl-2">
                            <button wire:click="removeItem({{$index}})" class="text-red-500 hover:bg-red-50 p-1 rounded transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12" stroke-width="2"/></svg>
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <button wire:click="addItem" class="text-primary text-sm font-bold mt-4 flex items-center gap-1">+ Zeile hinzufügen</button>
        </div>

        {{-- Kosten & Rabatte --}}
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold mb-4 border-b pb-2 text-gray-900">Gesamtrabatte / Aufschläge</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-[10px] font-bold text-gray-600 uppercase mb-1 block">Versand (€)</label>
                    <input type="number" step="0.01" wire:model.live="manualInvoice.shipping_cost" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-right text-gray-900 shadow-sm">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-600 uppercase mb-1 block text-red-600">Gutschein (€)</label>
                    <input type="number" step="0.01" wire:model.live="manualInvoice.discount_amount" class="w-full border-2 border-red-200 rounded-lg text-sm p-3 text-right text-red-600 font-bold shadow-sm">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-600 uppercase mb-1 block text-red-600">Mengenrabatt (€)</label>
                    <input type="number" step="0.01" wire:model.live="manualInvoice.volume_discount" class="w-full border-2 border-red-200 rounded-lg text-sm p-3 text-right text-red-600 font-bold shadow-sm">
                </div>
            </div>
        </div>

        {{-- Fußtext --}}
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <div class="flex items-center gap-2 mb-4 border-b pb-2">
                <h3 class="text-lg font-bold text-gray-900">Fußtext</h3>
                @include('components.alerts.info-tooltip', ['key' => 'footer_text'])
            </div>

            <div class="space-y-4">
                <div>
                    <div class="flex items-center gap-1 mb-1">
                        <label class="block text-xs font-bold uppercase text-gray-700">Zahlungshinweise & Grußformel (Freitext)</label>
                        @include('components.alerts.info-tooltip', ['key' => 'variables'])
                    </div>
                    <textarea wire:model.live.debounce.500ms="manualInvoice.footer_text" rows="6" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900 shadow-sm focus:border-primary focus:ring-0 resize-none font-mono" placeholder="Zahlungsinformationen hier eingeben..."></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Vorschau --}}
    <div class="hidden lg:block">
        <div class="sticky top-6 scale-[0.85] xl:scale-90 origin-top">
            <div class="bg-white shadow-2xl p-8 xl:p-10 min-h-[297mm] w-[210mm] mx-auto text-sm text-gray-800 border flex flex-col">
                {{-- Briefkopf --}}
                <div class="flex justify-between border-b-2 border-primary pb-6 mb-8">
                    <div class="font-serif text-2xl font-bold text-primary italic">Mein Seelenfunke</div>
                    <div class="text-right">
                        <div class="uppercase font-bold text-primary tracking-widest text-xl">Rechnung</div>
                        <div class="text-xs text-gray-500 font-mono">{{ $manualInvoice['invoice_number'] ?: '---' }}</div>
                    </div>
                </div>

                {{-- Adressen & Info --}}
                <div class="flex justify-between mb-10">
                    <div class="w-1/2">
                        <div class="text-[9px] text-gray-400 underline mb-2 italic">Mein Seelenfunke · Carl-Goerdeler-Ring 26 · 38518 Gifhorn</div>
                        <div class="font-bold text-gray-900 leading-snug">
                            @if($manualInvoice['company']) {{ $manualInvoice['company'] }}<br> @endif
                            {{ $manualInvoice['first_name'] ?: 'Vorname' }} {{ $manualInvoice['last_name'] ?: 'Nachname' }}<br>
                            {{ $manualInvoice['address'] ?: 'Straße Hausnummer' }}<br>
                            @if($manualInvoice['address_addition']) {{ $manualInvoice['address_addition'] }}<br> @endif
                            {{ $manualInvoice['postal_code'] ?: 'PLZ' }} {{ $manualInvoice['city'] ?: 'Stadt' }}<br>
                            {{ $manualInvoice['country'] }}
                        </div>
                    </div>
                    <div class="text-right text-xs">
                        <table class="ml-auto border-separate border-spacing-y-1">
                            <tr><td class="text-gray-400 pr-4 uppercase font-bold text-[10px]">Datum:</td><td class="bg-gray-50 px-2 py-0.5 rounded">{{ $manualInvoice['invoice_date'] ? date('d.m.Y', strtotime($manualInvoice['invoice_date'])) : date('d.m.Y') }}</td></tr>
                            <tr><td class="text-gray-400 pr-4 uppercase font-bold text-[10px]">Leistung:</td><td class="bg-gray-50 px-2 py-0.5 rounded">{{ $manualInvoice['delivery_date'] ? date('d.m.Y', strtotime($manualInvoice['delivery_date'])) : date('d.m.Y') }}</td></tr>
                            <tr><td class="text-gray-400 pr-4 uppercase font-bold text-[10px]">Fällig:</td><td class="font-bold text-primary bg-primary/5 px-2 py-0.5 rounded">{{ $manualInvoice['due_date'] ? date('d.m.Y', strtotime($manualInvoice['due_date'])) : '---' }}</td></tr>
                            @if($manualInvoice['reference_number'])
                                <tr><td class="text-gray-400 pr-4 uppercase font-bold text-[10px]">Ref:</td><td class="bg-gray-50 px-2 py-0.5 rounded">{{ $manualInvoice['reference_number'] }}</td></tr>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- Kopftext --}}
                <div class="mb-8">
                    <div class="font-bold text-lg mb-4 border-l-4 border-primary pl-4 py-1">{{ $manualInvoice['subject'] ?: 'Betreffzeile' }}</div>
                    <div class="whitespace-pre-line text-gray-700 leading-relaxed">{{ $manualInvoice['header_text'] }}</div>
                </div>

                {{-- Tabelle --}}
                <table class="w-full mb-8 border-collapse">
                    <thead class="bg-gray-800 text-white text-[10px] uppercase font-bold tracking-widest">
                    <tr>
                        <th class="py-3 px-4 rounded-tl-lg text-left">Pos</th>
                        <th class="py-3 px-4 text-left">Bezeichnung</th>
                        <th class="py-3 px-4 text-center">Menge</th>
                        <th class="py-3 px-4 text-right">Einzel Brutto</th>
                        <th class="py-3 px-4 text-right rounded-tr-lg">Gesamt</th>
                    </tr>
                    </thead>
                    <tbody class="text-xs">
                    @foreach($manualInvoice['items'] as $index => $item)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="py-4 px-4 text-gray-400 font-mono">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="py-4 px-4 text-gray-900 font-bold uppercase tracking-tight">{{ $item['product_name'] ?: 'Artikelliste...' }}</td>
                            <td class="py-4 px-4 text-center">{{ $item['quantity'] }}</td>
                            <td class="py-4 px-4 text-right">{{ number_format($item['unit_price'] ?: 0, 2, ',', '.') }} €</td>
                            <td class="py-4 px-4 text-right font-bold text-gray-900">{{ number_format(($item['unit_price'] ?: 0) * ($item['quantity'] ?: 1), 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                {{-- Summen --}}
                <div class="w-[300px] ml-auto space-y-2 bg-gray-50 p-6 rounded-2xl border border-gray-100 mb-8">
                    <div class="flex justify-between text-gray-500 text-[10px] uppercase font-bold"><span>Gesamt Netto</span><span>{{ number_format($totalsPreview['net'], 2, ',', '.') }} €</span></div>
                    <div class="flex justify-between text-gray-500 text-[10px] uppercase font-bold"><span>Umsatzsteuer</span><span>{{ number_format($totalsPreview['tax'], 2, ',', '.') }} €</span></div>
                    @if($manualInvoice['shipping_cost'] > 0)
                        <div class="flex justify-between text-gray-500 text-[10px] uppercase font-bold"><span>Versandkosten</span><span>{{ number_format((float)$manualInvoice['shipping_cost'], 2, ',', '.') }} €</span></div>
                    @endif

                    {{-- Getrennte Rabattaufstellung --}}
                    @if($manualInvoice['volume_discount'] > 0)
                        <div class="flex justify-between text-red-500 text-[10px] uppercase font-bold italic"><span>Mengenrabatt</span><span>-{{ number_format((float)$manualInvoice['volume_discount'], 2, ',', '.') }} €</span></div>
                    @endif
                    @if($manualInvoice['discount_amount'] > 0)
                        <div class="flex justify-between text-red-500 text-[10px] uppercase font-bold italic"><span>Gutschein</span><span>-{{ number_format((float)$manualInvoice['discount_amount'], 2, ',', '.') }} €</span></div>
                    @endif

                    <div class="flex justify-between font-black text-2xl border-t border-gray-200 pt-4 mt-2 text-gray-900 tracking-tighter">
                        <span>Gesamtbetrag</span>
                        <span class="text-primary">{{ number_format($totalsPreview['gross'], 2, ',', '.') }} €</span>
                    </div>
                </div>

                {{-- Fußtext --}}
                <div class="mt-4 border-t border-gray-100 italic text-gray-500 whitespace-pre-line text-[11px] leading-relaxed">
                    @php
                        $previewFooter = str_replace(
                            ['[%ZAHLUNGSZIEL%]', '[%KONTAKTPERSON%]', '[%RECHNUNGSNUMMER%]'],
                            [
                                '<span class="text-primary font-bold">'.($manualInvoice['due_date'] ? date('d.m.Y', strtotime($manualInvoice['due_date'])) : '---').'</span>',
                                '<span class="font-bold">'.shop_setting('owner_proprietor', 'Alina Steinhauer').'</span>',
                                '<span class="font-mono">'.$manualInvoice['invoice_number'].'</span>'
                            ],
                            $manualInvoice['footer_text']
                        );
                    @endphp
                    {!! $previewFooter !!}
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
            </div>
        </div>
    </div>
</div>
