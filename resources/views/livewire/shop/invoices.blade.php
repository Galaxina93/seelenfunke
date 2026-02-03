<div class="p-2 md:p-6 bg-gray-50 min-h-screen" x-data="{ draftBtnText: 'Entwurf speichern' }" x-on:reset-draft-success.window="setTimeout(() => { $wire.draftSuccess = false }, 3000)">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold font-serif text-gray-800">Rechnungsverwaltung</h2>
            <p class="text-sm text-gray-500">Shop-Bestellungen und manuelle Belege.</p>
        </div>
        <div class="flex flex-wrap gap-2 w-full md:w-auto">
            <button wire:click="toggleManualCreate" class="flex-1 md:flex-none bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition shadow-sm text-sm font-bold uppercase">
                {{ $isCreatingManual ? 'Zurück zur Liste' : '+ Rechnung erstellen' }}
            </button>
            {{-- Bulk Aktion: Bleibt als manuelle Auslösung für spezifische Wartungsszenarien, Automation läuft über Order-Events --}}
            <button wire:click="generateForPaidOrders" wire:loading.attr="disabled" class="flex-1 md:flex-none bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark shadow-sm text-sm font-bold uppercase">
                <span wire:loading.remove>Bulk-Action</span>
                <span wire:loading>...</span>
            </button>
        </div>
    </div>

    @if($isCreatingManual)
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
    @else
        {{-- Listenansicht --}}
        <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 mb-4 shadow-sm items-center">
            <div class="relative flex-1 w-full md:max-w-md">
                <input type="text" wire:model.live="search" placeholder="Suche nach Nummer oder Name..." class="w-full pl-10 border-gray-300 rounded-lg text-sm text-gray-900 focus:ring-primary focus:border-primary">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2"/></svg>
                </div>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <select wire:model.live="filterType" class="flex-1 md:flex-none border-gray-300 rounded-lg text-sm text-gray-900 bg-gray-50 p-2 focus:ring-primary">
                    <option value="">Alle Belege</option>
                    <option value="invoice">Rechnungen</option>
                    <option value="draft">Entwürfe</option>
                    <option value="cancellation">Stornos</option>
                </select>
            </div>
        </div>

        <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-gray-50 text-gray-500 font-bold uppercase text-[10px] tracking-widest border-b">
                    <tr>
                        <th class="px-6 py-4">Belegnummer</th>
                        <th class="px-6 py-4">Empfänger</th>
                        <th class="px-6 py-4 text-right">Bruttobetrag</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($invoices as $inv)
                        <tr class="hover:bg-gray-50 transition-colors group">
                            <td class="px-6 py-4 font-mono font-bold text-gray-900">
                                <div class="flex items-center gap-2">
                                    {{ $inv->invoice_number }}
                                    @if($inv->is_e_invoice)
                                        <span class="bg-blue-100 text-blue-600 text-[8px] px-1 rounded uppercase font-black">E</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-gray-900 font-medium">
                                    {{ $inv->billing_address['company'] ? $inv->billing_address['company'] . ' (' . $inv->billing_address['last_name'] . ')' : $inv->billing_address['first_name'] . ' ' . $inv->billing_address['last_name'] }}
                                </div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-tighter">{{ $inv->invoice_date->format('d.m.Y') }}</div>
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900 text-base tracking-tighter">{{ number_format($inv->total / 100, 2, ',', '.') }} €</td>
                            <td class="px-6 py-4 text-center">
                                @if($inv->status == 'paid')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-green-100 text-green-700 border border-green-200">Final</span>
                                @elseif($inv->status == 'draft')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-50 text-amber-700 border border-amber-200">Entwurf</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-blue-50 text-blue-700 border border-blue-200">Offen</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="downloadPdf('{{ $inv->id }}')" class="p-1 text-gray-400 hover:text-primary transition" title="PDF Kopie">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </button>
                                    @if($inv->status === 'draft')
                                        <button wire:click="editDraft('{{ $inv->id }}')" class="text-amber-600 font-black text-xs uppercase hover:underline">Bearbeiten</button>
                                    @endif
                                    <button wire:click="$dispatch('openInvoicePreview', { id: '{{ $inv->id }}' })" class="text-primary font-black text-xs uppercase hover:underline">Vorschau</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">Keine Belege gefunden.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- MOBILE ANSICHT --}}
            <div class="md:hidden divide-y divide-gray-100">
                @forelse($invoices as $inv)
                    <div class="p-4 bg-white active:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex flex-col">
                                <span class="font-mono font-bold text-gray-900 text-sm flex items-center gap-1">
                                    {{ $inv->invoice_number }}
                                    @if($inv->is_e_invoice) <span class="text-[8px] bg-blue-100 text-blue-600 px-1 rounded">E</span> @endif
                                </span>
                                <span class="text-[10px] text-gray-400 uppercase font-bold">{{ $inv->invoice_date->format('d.m.Y') }}</span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border {{ $inv->status == 'paid' ? 'bg-green-100 text-green-800' : ($inv->status == 'draft' ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-blue-100 text-blue-800') }}">
                                {{ $inv->status }}
                            </span>
                        </div>

                        <div class="flex justify-between items-end">
                            <div>
                                <div class="text-sm font-bold text-gray-900">{{ $inv->billing_address['last_name'] }}</div>
                                <div class="text-base font-black text-primary mt-1">{{ number_format($inv->total / 100, 2, ',', '.') }} €</div>
                            </div>
                            <div class="flex gap-4">
                                <button wire:click="downloadPdf('{{ $inv->id }}')" class="p-2 bg-gray-50 rounded-lg text-gray-500 border border-gray-100 shadow-sm">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </button>
                                @if($inv->status === 'draft')
                                    <button wire:click="editDraft('{{ $inv->id }}')" class="px-3 py-2 bg-amber-50 text-amber-600 rounded-lg text-xs font-black uppercase">Edit</button>
                                @endif
                                <button wire:click="$dispatch('openInvoicePreview', { id: '{{ $inv->id }}' })" class="px-3 py-2 bg-primary/10 text-primary rounded-lg text-xs font-black uppercase">Preview</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-400 italic text-sm">Keine Belege gefunden.</div>
                @endforelse
            </div>

            <div class="p-4 border-t bg-gray-50">{{ $invoices->links() }}</div>
        </div>
    @endif
    <livewire:shop.invoice-preview />
</div>
