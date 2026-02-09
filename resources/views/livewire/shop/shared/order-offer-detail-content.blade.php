{{--
    MASTER TEMPLATE FÜR DETAIL-ANSICHTEN (ORDERS & QUOTES)
    Pfad: resources/views/livewire/shop/shared/detail-content.blade.php
    Erwartet:
    - $model: Das Order oder QuoteRequest Objekt
    - $context: 'order' oder 'quote'
    - $selectedItemId: ID des aktuell ausgewählten Items für die Vorschau
--}}

@php
    // -------------------------------------------------------------------------
    // 1. DATEN NORMALISIERUNG
    // -------------------------------------------------------------------------
    $isOrder = $context === 'order';
    $isQuote = $context === 'quote';

    // ADRESSDATEN
    if ($isOrder) {
        $billing = [
            'name' => $model->billing_address['first_name'] . ' ' . $model->billing_address['last_name'],
            'company' => $model->billing_address['company'] ?? null,
            'address' => $model->billing_address['address'],
            'city_zip' => $model->billing_address['postal_code'] . ' ' . $model->billing_address['city'],
            'country' => $model->billing_address['country'],
            'email' => $model->email
        ];
        $shipping = $model->shipping_address ?? null;
    } else {
        // Quote Logic
        $billing = [
            'name' => $model->first_name . ' ' . $model->last_name,
            'company' => $model->company ?? null,
            'address' => trim(($model->street ?? '') . ' ' . ($model->house_number ?? '')),
            'city_zip' => ($model->postal ?? '') . ' ' . ($model->city ?? ''),
            'country' => $model->country ?? 'DE',
            'email' => $model->email
        ];
        $shipping = null; // Quotes haben meist keine abweichende Lieferadresse in dieser Phase
    }

    // PREISE
    $subtotal = $isOrder ? $model->subtotal_price : $model->net_total;
    $taxTotal = $isOrder ? $model->tax_amount : $model->tax_total;
    $grossTotal = $isOrder ? $model->total_price : $model->gross_total;
    $shippingCost = $model->shipping_price ?? 0;

    // EINSTELLUNGEN
    $isSmallBusiness = (bool)shop_setting('is_small_business', false);
    $taxRate = (float)shop_setting('default_tax_rate', 19.0);
    $expressSurchargeGross = (int)shop_setting('express_surcharge', 2500);
@endphp

<div class="w-full lg:w-1/2 h-1/2 lg:h-full overflow-y-auto border-b lg:border-b-0 border-r-0 lg:border-r border-gray-200 bg-white custom-scrollbar z-10">
    <div class="p-4 md:p-6 space-y-6 md:space-y-8">

        {{-- ========================================================================
             SECTION 1: EXPRESS ALERT
             ======================================================================== --}}
        @if($model->is_express)
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
                        @if($model->deadline)
                            <p class="text-red-900 text-sm font-medium mt-0.5">
                                🏁 Deadline: <strong>{{ \Carbon\Carbon::parse($model->deadline)->format('d.m.Y') }}</strong>
                            </p>
                        @else
                            <p class="text-red-800/70 text-xs mt-0.5">Schnellstmögliche Bearbeitung gewünscht.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ========================================================================
             SECTION 2: KUNDENDATEN & ADRESSEN
             ======================================================================== --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 md:gap-6">

            {{-- Rechnungsadresse / Antragsteller --}}
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $isOrder ? 'Rechnungsadresse' : 'Antragsteller' }}
                </h3>
                <div class="text-sm text-gray-900 leading-snug">
                    <span class="font-bold">{{ $billing['name'] }}</span><br>
                    @if(!empty($billing['company'])) {{ $billing['company'] }}<br> @endif
                    {{ $billing['address'] }}<br>
                    {{ $billing['city_zip'] }}<br>
                    {{ $billing['country'] }}
                </div>
                <div class="mt-2 text-xs text-blue-600 truncate">
                    <a href="mailto:{{ $billing['email'] }}" class="hover:underline">{{ $billing['email'] }}</a>
                </div>
                @if($isQuote && $model->phone)
                    <div class="mt-1 text-xs text-gray-500">{{ $model->phone }}</div>
                @endif
            </div>

            {{-- Lieferadresse (Nur bei Orders oder wenn shipping vorhanden) --}}
            @if($isOrder)
                @php
                    $isDifferent = $shipping && serialize($model->billing_address) !== serialize($model->shipping_address);
                @endphp
                <div @class(['p-4 rounded-xl border', $isDifferent ? 'bg-amber-50 border-amber-200 shadow-sm' : 'bg-gray-50 border-gray-100'])>
                    <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Lieferadresse
                        @if($isDifferent)
                            <span class="ml-auto text-[9px] bg-amber-500 px-1.5 py-0.5 rounded-full font-black tracking-widest uppercase animate-pulse">Abweichend!</span>
                        @endif
                    </h3>
                    <div class="text-sm text-gray-900 leading-snug">
                        @if($shipping)
                            <span class="font-bold">{{ $shipping['first_name'] }} {{ $shipping['last_name'] }}</span><br>
                            @if(!empty($shipping['company'])) {{ $shipping['company'] }}<br> @endif
                            {{ $shipping['address'] }}<br>
                            {{ $shipping['postal_code'] }} {{ $shipping['city'] }}<br>
                            {{ $shipping['country'] }}
                        @else
                            <span class="italic text-gray-400">Wie Rechnungsadresse</span>
                        @endif
                    </div>
                </div>
            @elseif($isQuote)
                {{-- Bei Angeboten: Interne Notizen anzeigen statt Lieferadresse --}}
                <div class="bg-amber-50/30 p-4 rounded-xl border border-amber-100/50">
                    <h3 class="text-[10px] lg:text-xs font-bold uppercase text-amber-600/70 mb-2 flex items-center gap-1 tracking-wider">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Interne Notizen
                    </h3>
                    <div class="text-sm text-amber-900 italic leading-snug">
                        {{ $model->admin_notes ?: 'Keine internen Notizen.' }}
                    </div>
                </div>
            @endif

            {{-- Zahlung & Status (Nur ORDER) --}}
            @if($isOrder)
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 xl:col-span-2">
                    <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Zahlung & Info
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Zahlungsstatus:</span>
                            <span class="font-bold {{ $model->payment_status == 'paid' ? 'text-green-600' : 'text-yellow-600' }}">
                                {{ $model->payment_status == 'paid' ? 'Bezahlt' : 'Offen' }}
                            </span>
                        </div>
                        @if($model->payment_status !== 'paid')
                            <button wire:click="markAsPaid('{{ $model->id }}')" class="w-full text-xs bg-white border border-gray-300 rounded px-2 py-1 hover:bg-gray-50 font-bold shadow-sm transition">
                                Als bezahlt markieren
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- ========================================================================
             SECTION 3: STORNO MANAGEMENT (Nur ORDER)
             ======================================================================== --}}
        @if($isOrder && isset($this->status) && $this->status === 'cancelled')
            <div class="mt-6 animate-fade-in-up">
                @if($model->status === 'cancelled' && !empty($model->cancellation_reason))
                    {{-- Status: Bereits storniert --}}
                    <div class="bg-green-50 border border-green-200 rounded-xl p-5 transition-all">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="mt-0.5 bg-green-100 rounded-full p-1">
                                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-green-800 text-sm">Stornierungsgrund gespeichert</h4>
                                <p class="text-xs text-green-700">Dieser Grund ist hinterlegt und für den Kunden sichtbar.</p>
                            </div>
                        </div>
                        <textarea wire:model="cancellationReason" rows="2" class="w-full rounded-lg border-green-300 bg-white focus:border-green-500 focus:ring-green-500 text-sm placeholder-gray-400" placeholder="Grund bearbeiten..."></textarea>
                        <div class="mt-2 text-right">
                            <button wire:click="saveStatus" class="text-xs font-bold text-green-700 hover:text-green-900 underline">Änderung speichern</button>
                        </div>
                    </div>
                @else
                    {{-- Status: Neu stornieren --}}
                    <div class="bg-red-50 border border-red-200 rounded-xl p-5 transition-all">
                        <div class="flex items-start gap-3 mb-3">
                            <svg class="w-5 h-5 text-red-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div>
                                <h4 class="font-bold text-red-700 text-sm">Stornierungsgrund erforderlich</h4>
                                <p class="text-xs text-red-600">Bitte geben Sie einen Grund an, um die Stornierung abzuschließen.</p>
                            </div>
                        </div>
                        <textarea wire:model="cancellationReason" rows="3" class="w-full rounded-lg border-red-300 focus:border-red-500 focus:ring-red-500 text-sm placeholder-red-300" placeholder="z.B. Artikel nicht lieferbar oder Kundenwunsch..."></textarea>
                        @error('cancellationReason')
                        <p class="text-red-600 text-xs mt-1 font-bold flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ $message }}</p>
                        @enderror
                        <div class="mt-4 flex justify-end">
                            <button wire:click="saveStatus" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-red-700 transition shadow-sm flex items-center gap-2">
                                <span>Stornierung bestätigen</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- ========================================================================
             SECTION 4: ARTIKELLISTE (Für beide gleich)
             ======================================================================== --}}
        <div>
            <h3 class="font-bold text-gray-900 mb-4 px-1 flex items-center justify-between">
                <span>Positionen</span>
                <span class="text-xs font-normal text-gray-400">Klicke zum Anzeigen</span>
            </h3>
            <div class="space-y-3">
                @foreach($model->items as $item)
                    <div wire:click="selectItemForPreview('{{ $item->id }}')"
                         class="cursor-pointer border rounded-xl p-3 transition-all relative overflow-hidden group
                                {{ $selectedItemId == $item->id ? 'border-primary ring-1 ring-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                    >
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-4">
                                <div class="h-14 w-14 bg-white rounded-lg border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                    @php
                                        $conf = $item->configuration;
                                        $imgPath = $conf['preview_file'] ?? ($conf['logo_storage_path'] ?? ($item->product->preview_image_path ?? null));
                                    @endphp
                                    @if($imgPath)
                                        <img src="{{ asset('storage/'.$imgPath) }}" class="h-full w-full object-contain">
                                    @else
                                        <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900 text-sm">{{ $item->product_name }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $item->quantity }} Stück á {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold text-gray-900 text-sm">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</div>
                                @if($selectedItemId == $item->id)
                                    <div class="text-[10px] text-primary font-bold mt-1 bg-white px-2 py-0.5 rounded-full shadow-sm inline-block">WIRD ANGEZEIGT</div>
                                @else
                                    <div class="text-[10px] text-gray-400 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">Anzeigen &rarr;</div>
                                @endif
                            </div>
                        </div>

                        {{-- Item Details --}}
                        @if(!empty($conf))
                            <div class="mt-3 pt-3 border-t border-gray-200/60 grid grid-cols-1 xl:grid-cols-2 gap-4 text-xs">
                                @if(!empty($conf['text']))
                                    <div>
                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Gravurtext</span>
                                        <div class="font-serif italic text-gray-800 bg-gray-50 px-2 py-1.5 rounded border border-gray-100">"{{ $conf['text'] }}"</div>
                                    </div>
                                @endif
                                @if(!empty($conf['notes']))
                                    <div>
                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Kunden-Anmerkung</span>
                                        <div class="text-gray-700 bg-yellow-50 px-2 py-1.5 rounded border border-yellow-100">{{ $conf['notes'] }}</div>
                                    </div>
                                @endif
                                @php
                                    $files = $conf['files'] ?? [];
                                    if(empty($files) && !empty($conf['logo_storage_path'])) { $files[] = $conf['logo_storage_path']; }
                                @endphp
                                @if(count($files) > 0)
                                    <div class="col-span-1 xl:col-span-2">
                                        <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Hochgeladene Dateien ({{ count($files) }})</span>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($files as $file)
                                                <a href="{{ asset('storage/'.$file) }}" target="_blank" download class="flex items-center gap-2 bg-white border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 hover:border-primary hover:text-primary transition group/btn">
                                                    <svg class="w-4 h-4 text-gray-500 group-hover/btn:text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
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

        {{-- ========================================================================
             SECTION 5: ABRECHNUNG (Dynamisch für beide)
             ======================================================================== --}}
        <div>
            <x-shop.cost-summary :model="$model" />
        </div>

        {{-- ========================================================================
             SECTION 6: LÖSCHEN (Nur ORDER)
             ======================================================================== --}}
        @if($isOrder)
            <div class="pt-6 border-t border-gray-100">
                <button wire:click="delete('{{ $model->id }}')" wire:confirm="Bestellung endgültig löschen?" class="text-red-500 hover:text-red-700 text-xs font-bold flex items-center gap-1 hover:underline">
                    Bestellung endgültig löschen
                </button>
            </div>
        @endif

    </div>
</div>
