<div class="animate-fade-in-up">
    {{-- Erstellungs-Formular Toolbar --}}
    <div class="flex flex-col md:flex-row justify-end items-center gap-5 mb-8 bg-gray-900/80 backdrop-blur-md p-4 sm:p-5 rounded-[2rem] shadow-2xl border border-gray-800 relative z-20">
        <div class="flex items-center gap-3 bg-gray-950 px-4 py-2 rounded-xl border border-gray-800 shadow-inner">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">E-Rechnung</span>
            @include('components.alerts.info-tooltip', ['key' => 'e_invoice'])
            <label class="relative inline-flex items-center cursor-pointer ml-1">
                <input type="checkbox" wire:model.live="manualInvoice.is_e_invoice" class="sr-only peer">
                <div class="w-10 h-5 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-gray-400 after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary peer-checked:after:bg-gray-900 border border-gray-700 shadow-inner"></div>
            </label>
        </div>
        <div class="flex gap-3 w-full sm:w-auto">
            <button wire:click="saveManualInvoice('draft')"
                    class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl transition-all text-[10px] sm:text-xs font-black uppercase tracking-widest shadow-inner {{ $draftSuccess ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-gray-800 text-gray-400 border border-gray-700 hover:bg-gray-700 hover:text-white' }}">
                {{ $draftSuccess ? 'Gespeichert' : 'Entwurf speichern' }}
            </button>
            <button wire:click="saveManualInvoice('paid')"
                    class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl transition-all text-[10px] sm:text-xs font-black uppercase tracking-widest shadow-lg {{ $saveSuccess ? 'bg-emerald-500 text-gray-900 shadow-[0_0_20px_rgba(16,185,129,0.4)]' : 'bg-primary border border-primary/50 text-gray-900 hover:bg-primary-dark hover:scale-[1.02] shadow-[0_0_15px_rgba(197,160,89,0.2)]' }}">
                {{ $saveSuccess ? 'Wird umgeleitet...' : 'Abschließen' }}
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-8 p-5 bg-red-900/10 border-l-4 border-red-500 rounded-r-2xl shadow-inner backdrop-blur-sm">
            <h4 class="text-red-400 font-black mb-2 uppercase text-[10px] tracking-widest flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Korrektur erforderlich:
            </h4>
            <ul class="list-disc list-inside text-xs text-red-300/80 space-y-1 font-medium pl-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6 xl:gap-8">
        <div class="space-y-6 xl:space-y-8">

            {{-- Empfänger --}}
            <div class="bg-gray-900/50 backdrop-blur-md p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border {{ $errors->has('manualInvoice.last_name') ? 'border-red-500/50 shadow-[0_0_15px_rgba(239,68,68,0.1)]' : 'border-gray-800' }}">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 border-b border-gray-800 pb-4 gap-4">
                    <h3 class="text-sm font-serif font-bold text-white tracking-wide">Empfänger</h3>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        @include('components.alerts.info-tooltip', ['key' => 'customer'])
                        <select wire:model.live="selectedCustomerId" class="flex-1 sm:flex-none bg-gray-950 border border-gray-700 text-gray-300 rounded-xl text-xs font-bold p-2 focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none shadow-inner cursor-pointer">
                            <option value="">Bestandskunde wählen...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->last_name }}, {{ $c->first_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @php
                    $inputClass = "w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm p-3 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all shadow-inner outline-none placeholder-gray-600";
                    $errorClass = "border-red-500/50 focus:ring-red-500/30 focus:border-red-500 bg-red-900/10";
                @endphp

                <div class="grid grid-cols-2 gap-5">
                    <div class="col-span-2">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">E-Mail Adresse *</label>
                        <input type="email" wire:model.live="manualInvoice.customer_email" class="{{ $inputClass }} {{ $errors->has('manualInvoice.customer_email') ? $errorClass : '' }}">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Firma (Optional)</label>
                        <input type="text" wire:model.live="manualInvoice.company" class="{{ $inputClass }}">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Vorname *</label>
                        <input type="text" wire:model.live="manualInvoice.first_name" class="{{ $inputClass }} {{ $errors->has('manualInvoice.first_name') ? $errorClass : '' }}">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Nachname *</label>
                        <input type="text" wire:model.live="manualInvoice.last_name" class="{{ $inputClass }} {{ $errors->has('manualInvoice.last_name') ? $errorClass : '' }}">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Straße & Hausnummer *</label>
                        <input type="text" wire:model.live="manualInvoice.address" class="{{ $inputClass }} {{ $errors->has('manualInvoice.address') ? $errorClass : '' }}">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Adresszusatz</label>
                        <input type="text" wire:model.live="manualInvoice.address_addition" class="{{ $inputClass }}">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">PLZ *</label>
                        <input type="text" wire:model.live="manualInvoice.postal_code" class="{{ $inputClass }} {{ $errors->has('manualInvoice.postal_code') ? $errorClass : '' }}">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Stadt *</label>
                        <input type="text" wire:model.live="manualInvoice.city" class="{{ $inputClass }} {{ $errors->has('manualInvoice.city') ? $errorClass : '' }}">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Land *</label>
                        <select wire:model.live="manualInvoice.country" class="{{ $inputClass }} cursor-pointer appearance-none">
                            @foreach(shop_setting('active_countries', ['DE' => 'Deutschland']) as $code => $name)
                                <option value="{{ $code }}" class="bg-gray-900">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Rechnungsinformationen --}}
            <div class="bg-gray-900/50 backdrop-blur-md p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border {{ $errors->has('manualInvoice.invoice_number') ? 'border-red-500/50 shadow-[0_0_15px_rgba(239,68,68,0.1)]' : 'border-gray-800' }}">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-800 pb-4">
                    <h3 class="text-sm font-serif font-bold text-white tracking-wide">Rechnungsinformationen</h3>
                    @include('components.alerts.info-tooltip', ['key' => 'invoice_info'])
                </div>
                <div class="grid grid-cols-2 gap-5">
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Rechnungsdatum *</label>
                        <input type="date" wire:model.live="manualInvoice.invoice_date" class="{{ $inputClass }} {{ $errors->has('manualInvoice.invoice_date') ? $errorClass : '' }} [&::-webkit-calendar-picker-indicator]:filter-[invert(1)] cursor-pointer">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Lieferdatum / Zeitraum *</label>
                        <input type="date" wire:model.live="manualInvoice.delivery_date" class="{{ $inputClass }} {{ $errors->has('manualInvoice.delivery_date') ? $errorClass : '' }} [&::-webkit-calendar-picker-indicator]:filter-[invert(1)] cursor-pointer">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Rechnungsnummer *</label>
                        <input type="text" wire:model.live="manualInvoice.invoice_number" class="{{ $inputClass }} font-mono tracking-wider {{ $errors->has('manualInvoice.invoice_number') ? $errorClass : '' }}">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Referenznummer</label>
                        <input type="text" wire:model.live="manualInvoice.reference_number" class="{{ $inputClass }}">
                    </div>
                    <div class="col-span-2 grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Zahlungsziel (Datum)</label>
                            <input type="date" wire:model.live="manualInvoice.due_date" class="{{ $inputClass }} bg-gray-900 text-gray-400 [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)]">
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1.5 ml-1">
                                <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Zahlungsziel (Tage)</label>
                                @include('components.alerts.info-tooltip', ['key' => 'due_date'])
                            </div>
                            <input type="number" wire:model.live="manualInvoice.due_days" class="{{ $inputClass }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kopftext --}}
            <div class="bg-gray-900/50 backdrop-blur-md p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-800 pb-4">
                    <h3 class="text-sm font-serif font-bold text-white tracking-wide">Kopftext</h3>
                    @include('components.alerts.info-tooltip', ['key' => 'header_text'])
                </div>
                <div class="space-y-5">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Betreff *</label>
                        <input type="text" wire:model.live="manualInvoice.subject" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Anschreiben (Freitext)</label>
                        <textarea wire:model.live.debounce.500ms="manualInvoice.header_text" rows="6" class="{{ $inputClass }} resize-none leading-relaxed" placeholder="Geben Sie hier das Anschreiben ein..."></textarea>
                    </div>
                </div>
            </div>

            {{-- Positionen --}}
            <div class="bg-gray-900/50 backdrop-blur-md p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border {{ $errors->has('manualInvoice.items') ? 'border-red-500/50' : 'border-gray-800' }} overflow-hidden">
                <h3 class="text-sm font-serif font-bold text-white tracking-wide mb-6 border-b border-gray-800 pb-4">Positionen</h3>
                <div class="overflow-x-auto w-full no-scrollbar pb-2">
                    <table class="w-full text-sm min-w-[600px] border-collapse">
                        <thead>
                        <tr class="text-left text-[9px] font-black uppercase tracking-widest text-gray-500 border-b border-gray-800">
                            <th class="pb-3 pl-1">Produkt / Service *</th>
                            <th class="pb-3 text-center w-24">Menge *</th>
                            <th class="pb-3 text-right w-32">Preis Netto *</th>
                            <th class="pb-3 text-center w-24">USt.</th>
                            <th class="pb-3 text-right w-32 pr-2">Betrag</th>
                            <th class="pb-3 w-12 text-center">Del</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                        @foreach($manualInvoice['items'] as $index => $item)
                            <tr class="group" wire:key="item-{{ $index }}">
                                <td class="py-3 pr-2">
                                    <input type="text" wire:model.live="manualInvoice.items.{{$index}}.product_name" class="{{ $inputClass }} {{ $errors->has('manualInvoice.items.'.$index.'.product_name') ? $errorClass : '' }} p-2.5">
                                </td>
                                <td class="py-3 px-2">
                                    <input type="number" wire:model.live="manualInvoice.items.{{$index}}.quantity" class="{{ $inputClass }} {{ $errors->has('manualInvoice.items.'.$index.'.quantity') ? $errorClass : '' }} p-2.5 text-center">
                                </td>
                                <td class="py-3 px-2">
                                    <input type="number" step="0.01" wire:model.live="manualInvoice.items.{{$index}}.unit_price" class="{{ $inputClass }} {{ $errors->has('manualInvoice.items.'.$index.'.unit_price') ? $errorClass : '' }} p-2.5 text-right font-mono">
                                </td>
                                <td class="py-3 px-2">
                                    <select wire:model.live="manualInvoice.items.{{$index}}.tax_rate" class="{{ $inputClass }} p-2.5 text-xs text-center appearance-none cursor-pointer">
                                        <option value="19" class="bg-gray-900">19%</option>
                                        <option value="7" class="bg-gray-900">7%</option>
                                        <option value="0" class="bg-gray-900">0%</option>
                                    </select>
                                </td>
                                <td class="py-3 pl-2 pr-4 text-right font-mono font-bold text-white whitespace-nowrap">
                                    {{ number_format(($item['unit_price'] ?: 0) * ($item['quantity'] ?: 0), 2, ',', '.') }} €
                                </td>
                                <td class="py-3 text-center">
                                    <button wire:click="removeItem({{$index}})" class="text-gray-600 hover:text-red-400 bg-gray-950 border border-gray-800 hover:border-red-500/30 p-2 rounded-xl transition-all shadow-inner hover:shadow-[0_0_10px_rgba(239,68,68,0.2)]">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button wire:click="addItem" class="text-primary text-[10px] font-black uppercase tracking-widest mt-4 inline-flex items-center gap-2 hover:text-white transition-colors bg-primary/10 border border-primary/20 px-4 py-2 rounded-xl">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Zeile hinzufügen
                </button>
            </div>

            {{-- Kosten & Rabatte --}}
            <div class="bg-gray-900/50 backdrop-blur-md p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800">
                <h3 class="text-sm font-serif font-bold text-white tracking-wide mb-6 border-b border-gray-800 pb-4">Gesamtrabatte / Aufschläge</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Versand (€)</label>
                        <input type="number" step="0.01" wire:model.live="manualInvoice.shipping_cost" class="{{ $inputClass }} text-right font-mono">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-red-400 mb-1.5 ml-1 drop-shadow-[0_0_8px_currentColor]">Gutschein (€)</label>
                        <input type="number" step="0.01" wire:model.live="manualInvoice.discount_amount" class="{{ $inputClass }} !border-red-500/30 !text-red-400 text-right font-mono font-bold focus:!border-red-500 focus:!ring-red-500/30 !bg-red-900/10">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-red-400 mb-1.5 ml-1 drop-shadow-[0_0_8px_currentColor]">Mengenrabatt (€)</label>
                        <input type="number" step="0.01" wire:model.live="manualInvoice.volume_discount" class="{{ $inputClass }} !border-red-500/30 !text-red-400 text-right font-mono font-bold focus:!border-red-500 focus:!ring-red-500/30 !bg-red-900/10">
                    </div>
                </div>
            </div>

            {{-- Fußtext --}}
            <div class="bg-gray-900/50 backdrop-blur-md p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-800 pb-4">
                    <h3 class="text-sm font-serif font-bold text-white tracking-wide">Fußtext</h3>
                    @include('components.alerts.info-tooltip', ['key' => 'footer_text'])
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-1.5 ml-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Zahlungshinweise & Grußformel</label>
                        @include('components.alerts.info-tooltip', ['key' => 'variables'])
                    </div>
                    <textarea wire:model.live.debounce.500ms="manualInvoice.footer_text" rows="5" class="{{ $inputClass }} resize-none font-mono text-xs leading-relaxed text-gray-400" placeholder="Zahlungsinformationen hier eingeben..."></textarea>
                </div>
            </div>
        </div>

        {{-- Vorschau (Rechte Spalte) --}}
        <div class="hidden xl:block">
            <div class="sticky top-10 scale-[0.9] origin-top">
                <div class="bg-white shadow-[0_20px_60px_rgba(0,0,0,0.8)] p-12 min-h-[297mm] w-[210mm] mx-auto text-sm text-gray-800 border-t-8 border-primary relative">

                    @if(shop_setting('is_small_business', false))
                        <div class="absolute top-6 right-1/2 translate-x-1/2 bg-gray-100 text-gray-400 text-[9px] font-black px-4 py-1.5 rounded-full uppercase tracking-widest border border-gray-200 select-none opacity-80">Kleinunternehmer-Modus</div>
                    @endif

                    {{-- Briefkopf --}}
                    <div class="flex justify-between border-b-2 border-primary pb-8 mb-10 mt-4">
                        <div class="font-serif text-3xl font-bold text-primary italic">Mein Seelenfunke</div>
                        <div class="text-right">
                            <div class="uppercase font-black text-gray-900 tracking-[0.3em] text-2xl mb-1">Rechnung</div>
                            <div class="text-xs font-mono font-bold text-gray-500 tracking-wider">{{ $manualInvoice['invoice_number'] ?: '---' }}</div>
                        </div>
                    </div>

                    {{-- Adressen & Info --}}
                    <div class="flex justify-between mb-12">
                        <div class="w-1/2">
                            <div class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-3 pb-1 border-b border-gray-100 inline-block">Mein Seelenfunke · C.-Goerdeler-Ring 26 · 38518 Gifhorn</div>
                            <div class="font-bold text-gray-900 text-base leading-relaxed mt-2">
                                @if($manualInvoice['company']) <span class="text-primary">{{ $manualInvoice['company'] }}</span><br> @endif
                                {{ $manualInvoice['first_name'] ?: 'Vorname' }} {{ $manualInvoice['last_name'] ?: 'Nachname' }}<br>
                                {{ $manualInvoice['address'] ?: 'Straße Hausnummer' }}<br>
                                @if($manualInvoice['address_addition']) {{ $manualInvoice['address_addition'] }}<br> @endif
                                {{ $manualInvoice['postal_code'] ?: 'PLZ' }} {{ $manualInvoice['city'] ?: 'Stadt' }}<br>
                                <span class="uppercase text-[10px] font-black tracking-widest text-gray-500 mt-1 block">{{ $manualInvoice['country'] }}</span>
                            </div>
                        </div>
                        <div class="text-right text-xs">
                            <table class="ml-auto border-separate border-spacing-y-2">
                                <tr><td class="text-gray-400 pr-5 uppercase font-bold text-[10px] tracking-wider text-right">Datum:</td><td class="bg-gray-50 border border-gray-100 px-3 py-1 rounded font-mono font-bold text-gray-700">{{ $manualInvoice['invoice_date'] ? date('d.m.Y', strtotime($manualInvoice['invoice_date'])) : date('d.m.Y') }}</td></tr>
                                <tr><td class="text-gray-400 pr-5 uppercase font-bold text-[10px] tracking-wider text-right">Leistung:</td><td class="bg-gray-50 border border-gray-100 px-3 py-1 rounded font-mono font-bold text-gray-700">{{ $manualInvoice['delivery_date'] ? date('d.m.Y', strtotime($manualInvoice['delivery_date'])) : date('d.m.Y') }}</td></tr>
                                <tr><td class="text-gray-400 pr-5 uppercase font-bold text-[10px] tracking-wider text-right">Fällig:</td><td class="font-bold text-primary bg-primary/5 border border-primary/20 px-3 py-1 rounded font-mono">{{ $manualInvoice['due_date'] ? date('d.m.Y', strtotime($manualInvoice['due_date'])) : '---' }}</td></tr>
                                @if($manualInvoice['reference_number'])
                                    <tr><td class="text-gray-400 pr-5 uppercase font-bold text-[10px] tracking-wider text-right">Ref:</td><td class="bg-gray-50 border border-gray-100 px-3 py-1 rounded font-mono font-bold text-gray-700">{{ $manualInvoice['reference_number'] }}</td></tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    {{-- Kopftext --}}
                    <div class="mb-10">
                        <div class="font-bold text-xl mb-5 border-l-4 border-primary pl-4 py-1 text-gray-900">{{ $manualInvoice['subject'] ?: 'Betreffzeile' }}</div>
                        <div class="whitespace-pre-line text-gray-600 leading-relaxed">{{ $manualInvoice['header_text'] }}</div>
                    </div>

                    {{-- Tabelle Vorschau --}}
                    <table class="w-full mb-10 border-collapse">
                        <thead class="bg-gray-900 text-white text-[10px] uppercase font-black tracking-widest">
                        <tr>
                            <th class="py-4 px-5 text-left w-12 rounded-tl-xl">Pos</th>
                            <th class="py-4 px-5 text-left">Bezeichnung</th>
                            <th class="py-4 px-5 text-center w-20">Menge</th>
                            <th class="py-4 px-5 text-right w-32">Einzel (Brutto)</th>
                            <th class="py-4 px-5 text-right w-36 rounded-tr-xl">Gesamt</th>
                        </tr>
                        </thead>
                        <tbody class="text-sm">
                        @foreach($manualInvoice['items'] as $index => $item)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="py-5 px-5 text-gray-400 font-mono font-bold align-top">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="py-5 px-5 align-top">
                                    <div class="text-gray-900 font-bold uppercase tracking-wide">{{ $item['product_name'] ?: 'Artikelliste...' }}</div>
                                    @php
                                        $config = is_object($item) ? ($item->configuration ?? null) : ($item['configuration'] ?? null);
                                    @endphp
                                    @if($config)
                                        <div class="text-[10px] text-gray-500 italic mt-1.5 leading-relaxed font-medium">
                                            @foreach($config as $label => $value)
                                                @if(!empty($value) && !in_array($label, ['image', 'product_image_path', 'logo_storage_path', 'text_x', 'text_y', 'logo_x', 'logo_y']))
                                                    @php
                                                        $displayValue = is_array($value)
                                                            ? implode(', ', array_map(fn($v) => (is_array($v) || is_object($v)) ? json_encode($v) : $v, $value))
                                                            : $value;
                                                    @endphp
                                                    <strong>{{ ucfirst($label) }}:</strong> {{ $displayValue }}@if(!$loop->last) · @endif
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="py-5 px-5 text-center align-top font-bold text-gray-700">{{ $item['quantity'] }}</td>
                                <td class="py-5 px-5 text-right align-top whitespace-nowrap font-mono text-gray-600">{{ number_format($item['unit_price'] ?: 0, 2, ',', '.') }} €</td>
                                <td class="py-5 px-5 text-right align-top font-bold font-mono text-gray-900 whitespace-nowrap bg-gray-50/50">{{ number_format(($item['unit_price'] ?: 0) * ($item['quantity'] ?: 1), 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    {{-- Summen Vorschau --}}
                    <div class="w-[320px] ml-auto space-y-3 bg-gray-50 p-6 rounded-2xl border border-gray-100 mb-10 text-right">
                        <div class="flex justify-between items-center text-gray-500 text-[10px] uppercase font-black tracking-widest">
                            <span>Gesamt Netto</span>
                            <span class="whitespace-nowrap font-mono text-sm">{{ number_format($totalsPreview['net'], 2, ',', '.') }} €</span>
                        </div>
                        @if(!shop_setting('is_small_business', false))
                            <div class="flex justify-between items-center text-gray-500 text-[10px] uppercase font-black tracking-widest">
                                <span>Umsatzsteuer</span>
                                <span class="whitespace-nowrap font-mono text-sm">{{ number_format($totalsPreview['tax'], 2, ',', '.') }} €</span>
                            </div>
                        @endif
                        @if($manualInvoice['shipping_cost'] > 0)
                            <div class="flex justify-between items-center text-gray-500 text-[10px] uppercase font-black tracking-widest pt-2 border-t border-gray-200">
                                <span>Versandkosten</span>
                                <span class="whitespace-nowrap font-mono text-sm">{{ number_format((float)$manualInvoice['shipping_cost'], 2, ',', '.') }} €</span>
                            </div>
                        @endif
                        @if($manualInvoice['volume_discount'] > 0)
                            <div class="flex justify-between items-center text-red-500 text-[10px] uppercase font-black tracking-widest pt-2 border-t border-gray-200">
                                <span>Mengenrabatt</span>
                                <span class="whitespace-nowrap font-mono text-sm">-{{ number_format((float)$manualInvoice['volume_discount'], 2, ',', '.') }} €</span>
                            </div>
                        @endif
                        @if($manualInvoice['discount_amount'] > 0)
                            <div class="flex justify-between items-center text-red-500 text-[10px] uppercase font-black tracking-widest pt-2 border-t border-gray-200">
                                <span>Gutschein</span>
                                <span class="whitespace-nowrap font-mono text-sm">-{{ number_format((float)$manualInvoice['discount_amount'], 2, ',', '.') }} €</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center font-black text-2xl border-t-2 border-gray-900 pt-5 mt-4 text-gray-900 tracking-tighter">
                            <span class="uppercase text-lg">Gesamtbetrag</span>
                            <span class="text-primary font-serif whitespace-nowrap">{{ number_format($totalsPreview['gross'], 2, ',', '.') }} €</span>
                        </div>
                        @if(shop_setting('is_small_business', false))
                            <div class="pt-3 text-[8px] text-gray-400 font-bold uppercase tracking-widest leading-relaxed">Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.</div>
                        @endif
                    </div>

                    {{-- Fußtext Vorschau --}}
                    <div class="mt-4 border-t border-gray-200 pt-8 italic text-gray-600 whitespace-pre-line text-xs leading-loose font-medium">
                        @php
                            $previewFooter = str_replace(
                                ['[%ZAHLUNGSZIEL%]', '[%KONTAKTPERSON%]', '[%RECHNUNGSNUMMER%]'],
                                [
                                    '<span class="text-primary font-bold px-1 bg-primary/5 rounded">'.($manualInvoice['due_date'] ? date('d.m.Y', strtotime($manualInvoice['due_date'])) : '---').'</span>',
                                    '<span class="font-bold text-gray-900">'.shop_setting('owner_proprietor', 'Alina Steinhauer').'</span>',
                                    '<span class="font-mono font-bold bg-gray-100 px-1 rounded">'.$manualInvoice['invoice_number'].'</span>'
                                ],
                                $manualInvoice['footer_text']
                            );
                        @endphp
                        {!! $previewFooter !!}
                    </div>

                    {{-- Dynamischer Footer Vorschau --}}
                    <div class="mt-auto pt-10 border-t border-gray-200 text-center text-gray-400">
                        <p class="text-[10px] leading-relaxed uppercase tracking-widest font-medium">
                            <strong class="text-gray-600">{{ shop_setting('owner_name') }}</strong> | Inh. {{ shop_setting('owner_proprietor') }}<br>
                            {{ shop_setting('owner_street') }}, {{ shop_setting('owner_city') }}<br>
                            <span class="lowercase tracking-normal">{{ shop_setting('owner_email') }}</span> | {{ str_replace(['http://', 'https://'], '', shop_setting('owner_website')) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
