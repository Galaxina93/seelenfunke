{{-- LINKS: Order Details & Liste (Scrollbar) --}}
<div class="w-full lg:w-1/2 h-1/2 lg:h-full overflow-y-auto border-b lg:border-b-0 border-r-0 lg:border-r border-gray-200 bg-white custom-scrollbar z-10">
    <div class="p-4 md:p-6 space-y-6 md:space-y-8">

        {{-- EXPRESS ALERT BOX --}}
        @if($this->selectedOrder->is_express)
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-2 -mr-2 w-16 h-16 bg-red-600 rounded-full opacity-10 blur-xl"></div>
                <div class="flex items-center gap-4 relative z-10">
                    <div class="bg-white p-2 rounded-full shadow-sm border border-red-100 text-red-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-red-700 font-bold uppercase tracking-wider text-sm flex items-center gap-2">
                            Express Bestellung
                            <span class="bg-red-600 text-white text-[10px] px-2 py-0.5 rounded-full animate-pulse">DRINGEND</span>
                        </h3>
                        @if($this->selectedOrder->deadline)
                            <p class="text-red-900 text-sm font-medium mt-0.5">
                                🏁 Deadline: <strong>{{ $this->selectedOrder->deadline->format('d.m.Y') }}</strong>
                            </p>
                        @else
                            <p class="text-red-800/70 text-xs mt-0.5">Schnellstmögliche Bearbeitung gewünscht.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Kundendaten --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 md:gap-6">
            {{-- Rechnungsadresse --}}
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Rechnungsadresse
                </h3>
                <div class="text-sm text-gray-900 leading-snug">
                    <span class="font-bold">{{ $this->selectedOrder->billing_address['first_name'] }} {{ $this->selectedOrder->billing_address['last_name'] }}</span><br>
                    @if(!empty($this->selectedOrder->billing_address['company']))
                        {{ $this->selectedOrder->billing_address['company'] }}<br>
                    @endif
                    {{ $this->selectedOrder->billing_address['address'] }}<br>
                    {{ $this->selectedOrder->billing_address['postal_code'] }} {{ $this->selectedOrder->billing_address['city'] }}
                    <br>
                    {{ $this->selectedOrder->billing_address['country'] }}
                </div>
                <div class="mt-2 text-xs text-blue-600 truncate">{{ $this->selectedOrder->email }}</div>
            </div>

            {{-- Lieferadresse (Abweichend Prüfung) --}}
            @php
                $isDifferent = $this->selectedOrder->shipping_address && serialize($this->selectedOrder->billing_address) !== serialize($this->selectedOrder->shipping_address);
            @endphp
            <div @class(['p-4 rounded-xl border', $isDifferent ? 'bg-amber-50 border-amber-200 shadow-sm' : 'bg-gray-50 border-gray-100'])>
                <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Lieferadresse
                    @if($isDifferent)
                        <span class="ml-auto text-[9px] bg-amber-500 px-1.5 py-0.5 rounded-full font-black tracking-widest uppercase animate-pulse">Abweichend!</span>
                    @endif
                </h3>
                <div class="text-sm text-gray-900 leading-snug">
                    @if($this->selectedOrder->shipping_address)
                        <span class="font-bold">{{ $this->selectedOrder->shipping_address['first_name'] }} {{ $this->selectedOrder->shipping_address['last_name'] }}</span>
                        <br>
                        @if(!empty($this->selectedOrder->shipping_address['company']))
                            {{ $this->selectedOrder->shipping_address['company'] }}<br>
                        @endif
                        {{ $this->selectedOrder->shipping_address['address'] }}<br>
                        {{ $this->selectedOrder->shipping_address['postal_code'] }} {{ $this->selectedOrder->shipping_address['city'] }}
                        <br>
                        {{ $this->selectedOrder->shipping_address['country'] }}
                    @else
                        <span class="italic text-gray-400">Wie Rechnungsadresse</span>
                    @endif
                </div>
            </div>

            {{-- Zahlung & Info --}}
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 xl:col-span-2">
                <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    Zahlung & Info
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Zahlungsstatus:</span>
                        <span class="font-bold {{ $this->selectedOrder->payment_status == 'paid' ? 'text-green-600' : 'text-yellow-600' }}">
                            {{ $this->selectedOrder->payment_status == 'paid' ? 'Bezahlt' : 'Offen' }}
                        </span>
                    </div>
                    @if($this->selectedOrder->payment_status !== 'paid')
                        <button wire:click="markAsPaid('{{ $this->selectedOrder->id }}')"
                                class="w-full text-xs bg-white border border-gray-300 rounded px-2 py-1 hover:bg-gray-50 font-bold shadow-sm transition">
                            Als bezahlt markieren
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Dynamischer Storno-Bereich --}}
        @if($status === 'cancelled')
            <div class="mt-6 animate-fade-in-up">
                {{-- FALL A: BEREITS GESPEICHERT (GRÜN) --}}
                @if($selectedOrder->status === 'cancelled' && !empty($selectedOrder->cancellation_reason))
                    <div class="bg-green-50 border border-green-200 rounded-xl p-5 transition-all">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="mt-0.5 bg-green-100 rounded-full p-1">
                                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-green-800 text-sm">Stornierungsgrund gespeichert</h4>
                                <p class="text-xs text-green-700">Dieser Grund ist hinterlegt und für den Kunden sichtbar.</p>
                            </div>
                        </div>

                        <textarea
                            wire:model="cancellationReason"
                            rows="2"
                            class="w-full rounded-lg border-green-300 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400"
                            placeholder="Grund bearbeiten..."
                        ></textarea>
                        <div class="mt-2 text-right">
                            <button wire:click="saveStatus"
                                    class="text-xs font-bold text-green-700 hover:text-green-900 underline">
                                Änderung speichern
                            </button>
                        </div>
                    </div>
                    {{-- FALL B: NEU / PFLICHTFELD (ROT) --}}
                @else
                    <div class="bg-red-50 border border-red-200 rounded-xl p-5 transition-all">
                        <div class="flex items-start gap-3 mb-3">
                            <svg class="w-5 h-5 text-red-600 mt-0.5" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div>
                                <h4 class="font-bold text-red-700 text-sm">Stornierungsgrund erforderlich</h4>
                                <p class="text-xs text-red-600">Bitte geben Sie einen Grund an, um die Stornierung abzuschließen.</p>
                            </div>
                        </div>

                        <textarea
                            wire:model="cancellationReason"
                            rows="3"
                            class="w-full rounded-lg border-red-300 focus:border-red-500 focus:ring-red-500 text-sm placeholder-red-300"
                            placeholder="z.B. Artikel nicht lieferbar oder Kundenwunsch..."
                        ></textarea>
                        @error('cancellationReason')
                        <p class="text-red-600 text-xs mt-1 font-bold flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $message }}
                        </p>
                        @enderror

                        <div class="mt-4 flex justify-end">
                            <button wire:click="saveStatus"
                                    class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-red-700 transition shadow-sm flex items-center gap-2">
                                <span>Stornierung bestätigen</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Artikelliste MIT DATEIEN UND INFOS --}}
        <div>
            <h3 class="font-bold text-gray-900 mb-4 px-1 flex items-center justify-between">
                <span>Positionen</span>
                <span class="text-xs font-normal text-gray-400">Klicke zum Anzeigen</span>
            </h3>
            <div class="space-y-3">
                @foreach($this->selectedOrder->items as $item)
                    <div wire:click="selectItemForPreview('{{ $item->id }}')"
                         class="cursor-pointer border rounded-xl p-3 transition-all relative overflow-hidden group
                                {{ $selectedOrderItemId == $item->id ? 'border-primary ring-1 ring-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                    >
                        {{-- Hauptzeile: Produkt --}}
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-4">
                                <div class="h-14 w-14 bg-white rounded-lg border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                    @php
                                        $conf = $item->configuration;
                                        $imgPath = $conf['preview_file'] ?? ($conf['logo_storage_path'] ?? ($item->product->preview_image_path ?? null));
                                    @endphp
                                    @if($imgPath && file_exists(public_path('storage/'.$imgPath)))
                                        <img src="{{ asset('storage/'.$imgPath) }}" class="h-full w-full object-contain">
                                    @elseif($imgPath && file_exists(public_path($imgPath)))
                                        <img src="{{ asset($imgPath) }}" class="h-full w-full object-contain">
                                    @else
                                        <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    @endif
                                </div>

                                <div>
                                    <div class="font-bold text-gray-900 text-sm">{{ $item->product_name }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $item->quantity }} Stück á {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</div>
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="font-mono font-bold text-gray-900 text-sm">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</div>
                                @if($selectedOrderItemId == $item->id)
                                    <div class="text-[10px] text-primary font-bold mt-1 bg-white px-2 py-0.5 rounded-full shadow-sm inline-block">WIRD ANGEZEIGT</div>
                                @else
                                    <div class="text-[10px] text-gray-400 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">Anzeigen &rarr;</div>
                                @endif
                            </div>
                        </div>

                        {{-- Details & Dateien Anzeige --}}
                        @if(!empty($conf))
                            <div class="mt-3 pt-3 border-t border-gray-200/60 grid grid-cols-1 xl:grid-cols-2 gap-4 text-xs">
                                {{-- 1. Gravurtext --}}
                                @if(!empty($conf['text']))
                                    <div>
                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Gravurtext</span>
                                        <div class="font-serif italic text-gray-800 bg-gray-50 px-2 py-1.5 rounded border border-gray-100">"{{ $conf['text'] }}"</div>
                                    </div>
                                @endif

                                {{-- 2. Anmerkungen --}}
                                @if(!empty($conf['notes']))
                                    <div>
                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Kunden-Anmerkung</span>
                                        <div class="text-gray-700 bg-yellow-50 px-2 py-1.5 rounded border border-yellow-100">{{ $conf['notes'] }}</div>
                                    </div>
                                @endif

                                {{-- 3. Dateien --}}
                                @php
                                    $files = $conf['files'] ?? [];
                                    if(empty($files) && !empty($conf['logo_storage_path'])) {
                                        $files[] = $conf['logo_storage_path'];
                                    }
                                @endphp

                                @if(count($files) > 0)
                                    <div class="col-span-1 xl:col-span-2">
                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Hochgeladene Dateien ({{ count($files) }})</span>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($files as $file)
                                                <a href="{{ asset('storage/'.$file) }}" target="_blank" download
                                                   class="flex items-center gap-2 bg-white border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 hover:border-primary hover:text-primary transition group/btn">
                                                    <svg class="w-4 h-4 text-gray-500 group-hover/btn:text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                    </svg>
                                                    <span class="truncate max-w-[150px]">{{ basename($file) }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Abrechnung --}}
        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
            <h4 class="text-xs font-bold uppercase text-gray-500 mb-4 border-b border-gray-200 pb-2">Abrechnung</h4>

            @php
                // Einstellungen laden
                $taxRate = (float)shop_setting('default_tax_rate', 19.0);

                // Small Business
                $isSmallBusiness = (bool)shop_setting('is_small_business', false);

                // Express berechnen (Brutto aus Settings holen)
                $expressGross = $this->selectedOrder->is_express ? (int) shop_setting('express_surcharge', 2500) : 0;

                // Netto-Anteil des Express-Zuschlags berechnen (um ihn vom Warenwert abzuziehen)
                $expressNet = $expressGross / (1 + ($taxRate / 100));

                // Ursprüngliche Summe (enthält bei der Bestellung oft schon den rechnerischen Zuschlag im Total)
                // Wir nehmen den Subtotal und bereinigen ihn für die Anzeige
                $originalSum = $this->selectedOrder->subtotal_price + ($this->selectedOrder->volume_discount ?? 0);

                // Falls Express aktiv ist, ziehen wir den Netto-Anteil vom angezeigten Warenwert ab,
                // damit wir den Zuschlag separat listen können, ohne die Summe zu verfälschen.
                $displaySubtotal = $this->selectedOrder->is_express ? ($originalSum - $expressNet) : $originalSum;
            @endphp

            <div class="space-y-3 text-sm">
                {{-- 1. Warenwert (Produkte) --}}
                <div class="flex justify-between text-gray-600">
                    <span>Warenwert</span>
                    <span>{{ number_format($displaySubtotal / 100, 2, ',', '.') }} €</span>
                </div>

                {{-- 2. Rabatte --}}
                @if(isset($this->selectedOrder->volume_discount) && $this->selectedOrder->volume_discount > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Mengenrabatt</span>
                        <span>-{{ number_format($this->selectedOrder->volume_discount / 100, 2, ',', '.') }} €</span>
                    </div>
                @endif

                @if(isset($this->selectedOrder->discount_amount) && $this->selectedOrder->discount_amount > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Gutschein ({{ $this->selectedOrder->coupon_code }})</span>
                        <span>-{{ number_format($this->selectedOrder->discount_amount / 100, 2, ',', '.') }} €</span>
                    </div>
                @endif

                {{-- 3. Express Service (Separat gelistet!) --}}
                @if($this->selectedOrder->is_express)
                    <div class="flex justify-between text-red-600 font-bold bg-red-50 p-2 rounded border border-red-100">
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            Express-Service Aufschlag
                        </span>
                        <span>+ {{ number_format($expressGross / 100, 2, ',', '.') }} €</span>
                    </div>
                @endif

                {{-- 4. Versand (Klassisch) --}}
                <div class="flex justify-between text-gray-600">
                    <span>Versandkosten</span>
                    <span>{{ $this->selectedOrder->shipping_price > 0 ? number_format($this->selectedOrder->shipping_price / 100, 2, ',', '.') . ' €' : 'Kostenlos' }}</span>
                </div>

                {{-- [NEU] MWST BEREICH HIER EINFÜGEN --}}
                @if(!$isSmallBusiness)
                    {{-- Standard Regelbesteuerung --}}
                    <div class="flex justify-between text-gray-500 text-xs">
                        <span>Enthaltene MwSt. ({{ number_format($taxRate, 0) }}%)</span>
                        <span>{{ number_format($this->selectedOrder->tax_amount / 100, 2, ',', '.') }} €</span>
                    </div>
                @else
                    {{-- Kleinunternehmer --}}
                    <div class="flex justify-between text-[10px] text-gray-500 italic pb-1">
                        <span>Steuerfrei gemäß § 19 UStG</span>
                        <span>0,00 €</span>
                    </div>
                @endif
                {{-- [ENDE NEU] --}}

                {{-- 5. Gesamtsumme --}}
                <div class="pt-3 mt-1 border-t border-gray-200 flex justify-between items-end">
                    <span class="font-bold text-gray-900">Gesamtsumme</span>
                    <span class="text-xl font-bold text-primary">{{ number_format($this->selectedOrder->total_price / 100, 2, ',', '.') }} €</span>
                </div>
            </div>
        </div>

        {{-- Löschen --}}
        <div class="pt-6 border-t border-gray-100">
            <button wire:click="delete('{{ $this->selectedOrder->id }}')"
                    wire:confirm="Bestellung endgültig löschen?"
                    class="text-red-500 hover:text-red-700 text-xs font-bold flex items-center gap-1 hover:underline">
                Bestellung endgültig löschen
            </button>
        </div>
    </div>
</div>
