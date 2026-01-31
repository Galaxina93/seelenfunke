<div class="p-2 md:p-6 bg-gray-50 min-h-screen">
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
            <button wire:click="generateForPaidOrders" wire:loading.attr="disabled" class="flex-1 md:flex-none bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark shadow-sm text-sm font-bold uppercase">
                <span wire:loading.remove>Bulk-Action</span>
                <span wire:loading>...</span>
            </button>
        </div>
    </div>

    @if($isCreatingManual)
        {{-- Erstellungs-Formular --}}
        <div class="grid lg:grid-cols-2 gap-8 animate-fade-in">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                <h3 class="text-lg font-bold mb-4 border-b pb-2 text-gray-900">Kundendaten</h3>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="col-span-2">
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">E-Mail Adresse</label>
                        <input type="email" wire:model.live="manualInvoice.customer_email" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 focus:border-primary focus:ring-0 text-gray-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Vorname</label>
                        <input type="text" wire:model.live="manualInvoice.first_name" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Nachname</label>
                        <input type="text" wire:model.live="manualInvoice.last_name" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Straße & Hausnummer</label>
                        <input type="text" wire:model.live="manualInvoice.address" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">PLZ</label>
                        <input type="text" wire:model.live="manualInvoice.postal_code" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Stadt</label>
                        <input type="text" wire:model.live="manualInvoice.city" class="w-full border-2 border-gray-300 rounded-lg text-sm p-3 text-gray-900">
                    </div>
                </div>

                <h3 class="text-lg font-bold mb-4 border-b pb-2 text-gray-900">Positionen</h3>
                @foreach($manualInvoice['items'] as $index => $item)
                    <div class="flex gap-2 mb-3 items-end p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <div class="flex-1">
                            <label class="text-[10px] font-bold text-gray-600 uppercase">Produkt / Leistung</label>
                            <input type="text" wire:model.live="manualInvoice.items.{{$index}}.product_name" class="w-full border-2 border-gray-300 rounded-lg text-sm p-2 text-gray-900">
                        </div>
                        <div class="w-16">
                            <label class="text-[10px] font-bold text-gray-600 uppercase">Menge</label>
                            <input type="number" wire:model.live="manualInvoice.items.{{$index}}.quantity" class="w-full border-2 border-gray-300 rounded-lg text-sm p-2 text-center text-gray-900">
                        </div>
                        <div class="w-24">
                            <label class="text-[10px] font-bold text-gray-600 uppercase">Preis (€)</label>
                            <input type="number" step="0.01" wire:model.live="manualInvoice.items.{{$index}}.unit_price" class="w-full border-2 border-gray-300 rounded-lg text-sm p-2 text-right text-gray-900">
                        </div>
                        <button wire:click="removeItem({{$index}})" class="p-2 text-red-500 hover:bg-red-50 rounded-lg">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12" stroke-width="2"/></svg>
                        </button>
                    </div>
                @endforeach
                <button wire:click="addItem" class="text-primary text-sm font-bold mt-2 flex items-center gap-1">+ Zeile hinzufügen</button>

                <h3 class="text-lg font-bold mt-8 mb-4 border-b pb-2 text-gray-900">Kosten & Rabatte</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-600 uppercase mb-1 block">Versand (€)</label>
                        <input type="number" step="0.01" wire:model.live="manualInvoice.shipping_cost" class="w-full border-2 border-gray-300 rounded-lg text-sm p-2 text-right text-gray-900">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-600 uppercase mb-1 block text-red-600">Gutschein (€)</label>
                        <input type="number" step="0.01" wire:model.live="manualInvoice.discount_amount" class="w-full border-2 border-red-200 rounded-lg text-sm p-2 text-right text-red-600 font-bold">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-600 uppercase mb-1 block text-red-600">Rabatt (€)</label>
                        <input type="number" step="0.01" wire:model.live="manualInvoice.volume_discount" class="w-full border-2 border-red-200 rounded-lg text-sm p-2 text-right text-red-600 font-bold">
                    </div>
                </div>

                <button wire:click="saveManualInvoice" class="w-full mt-10 bg-green-600 text-white py-4 rounded-xl font-bold shadow-lg hover:bg-green-700 transition uppercase tracking-widest">
                    Rechnung jetzt erstellen
                </button>
            </div>

            {{-- Vorschau --}}
            <div class="hidden lg:block">
                <div class="sticky top-6 scale-90 origin-top">
                    <div class="bg-white shadow-2xl p-10 min-h-[297mm] w-[210mm] mx-auto text-sm text-gray-800">
                        <div class="flex justify-between border-b pb-8 mb-8">
                            <div class="font-serif text-2xl font-bold text-primary">Mein Seelenfunke</div>
                            <div class="text-right uppercase font-bold text-gray-400">Vorschau</div>
                        </div>
                        <div class="mb-10 font-bold text-gray-900">
                            {{ $manualInvoice['first_name'] ?: 'Vorname' }} {{ $manualInvoice['last_name'] ?: 'Nachname' }}<br>
                            {{ $manualInvoice['address'] ?: 'Straße Hausnummer' }}<br>
                            {{ $manualInvoice['postal_code'] ?: 'PLZ' }} {{ $manualInvoice['city'] ?: 'Stadt' }}
                        </div>
                        <table class="w-full mb-8">
                            <thead class="border-b-2 border-gray-800 text-left text-xs uppercase font-bold text-gray-600">
                            <tr><th class="py-2">Bezeichnung</th><th class="text-right">Menge</th><th class="text-right">Gesamt</th></tr>
                            </thead>
                            <tbody>
                            @php $preSub = 0; @endphp
                            @foreach($manualInvoice['items'] as $item)
                                @php $line = ($item['unit_price'] ?: 0) * ($item['quantity'] ?: 1); $preSub += $line; @endphp
                                <tr class="border-b"><td class="py-3 text-gray-900">{{ $item['product_name'] ?: '...' }}</td><td class="text-right">{{ $item['quantity'] }}</td><td class="text-right font-bold">{{ number_format($line, 2, ',', '.') }} €</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="w-1/2 ml-auto space-y-2">
                            <div class="flex justify-between text-gray-500"><span>Zwischensumme</span><span>{{ number_format($preSub, 2, ',', '.') }} €</span></div>
                            <div class="flex justify-between text-gray-500"><span>Versand</span><span>{{ number_format((float)$manualInvoice['shipping_cost'], 2, ',', '.') }} €</span></div>
                            <div class="flex justify-between text-red-500 font-bold"><span>Rabatte</span><span>-{{ number_format((float)$manualInvoice['discount_amount'] + (float)$manualInvoice['volume_discount'], 2, ',', '.') }} €</span></div>
                            <div class="flex justify-between font-bold text-xl border-t pt-4 text-gray-900"><span>Gesamt</span><span class="text-primary">{{ number_format($preSub + (float)$manualInvoice['shipping_cost'] - (float)$manualInvoice['discount_amount'] - (float)$manualInvoice['volume_discount'], 2, ',', '.') }} €</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Listenansicht --}}
        <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 mb-4">
            <div class="relative flex-1">
                <input type="text" wire:model.live="search" placeholder="Suche..." class="w-full pl-10 border-gray-300 rounded-lg text-sm text-gray-900">
                <svg class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2"/></svg>
            </div>
            <select wire:model.live="filterType" class="border-gray-300 rounded-lg text-sm text-gray-900">
                <option value="">Alle Typen</option>
                <option value="invoice">Rechnungen</option>
                <option value="cancellation">Stornos</option>
            </select>
        </div>

        <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
            <div class="hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-500 font-bold uppercase text-[10px]">
                    <tr>
                        <th class="px-6 py-4">Nummer</th>
                        <th class="px-6 py-4">Kunde</th>
                        <th class="px-6 py-4 text-right">Betrag</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($invoices as $inv)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-mono font-bold text-gray-900">{{ $inv->invoice_number }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ $inv->billing_address['first_name'] }} {{ $inv->billing_address['last_name'] }}</td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900">{{ number_format($inv->total / 100, 2, ',', '.') }} €</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase {{ $inv->status == 'paid' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">{{ $inv->status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-3">
                                <button wire:click="downloadPdf('{{ $inv->id }}')" class="text-gray-400 hover:text-primary transition" title="PDF Kopie"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-width="2"/></svg></button>
                                <button wire:click="$dispatch('openInvoicePreview', { id: '{{ $inv->id }}' })" class="text-primary font-bold text-xs uppercase transition">Vorschau</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center text-gray-400">Keine Belege gefunden.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t bg-gray-50">{{ $invoices->links() }}</div>
        </div>
    @endif
    <livewire:shop.invoice-preview />
</div>
