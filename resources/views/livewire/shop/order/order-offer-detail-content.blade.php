@php
    // DATEN NORMALISIERUNG
    $isOrder = $context === 'order';
    $isQuote = $context === 'quote';

    // ADRESSDATEN ZUSAMMENSTELLEN
    if ($isOrder) {
        $b = is_array($model->billing_address) ? $model->billing_address : [];
        $firstName = $b['first_name'] ?? '';
        $lastName = $b['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        $postal = $b['postal_code'] ?? '';
        $city = $b['city'] ?? '';
        $cityZip = trim($postal . ' ' . $city);

        $billing = [
            'name' => $fullName !== '' ? $fullName : ($model->customer_name ?? 'Gast'),
            'company' => $b['company'] ?? null,
            'address' => $b['address'] ?? '',
            'city_zip' => $cityZip,
            'country' => $b['country'] ?? 'DE',
            'email' => $model->email ?? ($b['email'] ?? '')
        ];
        $shipping = $model->shipping_address ?? null;
    } else {
        $billing = [
            'name' => trim(($model->first_name ?? '') . ' ' . ($model->last_name ?? '')),
            'company' => $model->company ?? null,
            'address' => trim(($model->street ?? '') . ' ' . ($model->house_number ?? '')),
            'city_zip' => trim(($model->postal ?? '') . ' ' . ($model->city ?? '')),
            'country' => $model->country ?? 'DE',
            'email' => $model->email ?? ''
        ];
        $shipping = null;
    }

    // TYPEN PRÜFUNG FÜR PREVIEW
    $isDigitalItem = isset($previewItem->configuration['is_digital']) && $previewItem->configuration['is_digital'];
    $isService = isset($previewItem->product->type) && $previewItem->product->type === 'service';
    $fingerprint = $previewItem->config_fingerprint ?? null;

    $mfgOverview = $isOrder ? $model->getManufacturingOverview() : null;
    $currentOrderStep = $isOrder ? (int) ($this->activeOrderStep ?? 1) : 1;
    $isOptionalOpen = $isOrder && !empty($this->showOptionalOrderDetails);
@endphp

@if($isOrder && $model->status === 'pending')
    {{-- KACHEL 1: START-ANSICHT FÜR WARTENDE BESTELLUNGEN (Mittig zentrierte Kachel "Wartend" + Button "Jetzt starten") --}}
    <div class="w-full flex-1 flex flex-col items-center {{ $isOptionalOpen ? 'justify-start' : 'justify-start sm:justify-center' }} p-4 sm:p-10 overflow-y-auto custom-scrollbar">
        <div class="max-w-lg w-full shrink-0 bg-white dark:bg-gray-900/95 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] p-8 sm:p-10 shadow-xl dark:shadow-2xl text-center flex flex-col items-center relative">
            <div class="w-16 h-16 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center mb-5 shadow-sm">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h2 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight mb-2">
                Wartend
            </h2>

            <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-6">
                Auftrag #{{ $model->order_number }} · {{ $billing['name'] }} · {{ $model->items->count() }} {{ $model->items->count() === 1 ? 'Artikel' : 'Artikel' }}
            </p>

            {{-- Kompakte Vorschau der 3 Produktkonfigurator-Vorgaben --}}
            @if($mfgOverview)
                <div class="w-full bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 mb-7 text-left space-y-2.5">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-1">
                        Konfigurator-Checkliste für Schritt 1
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="font-bold text-gray-600 dark:text-gray-400">1. Besondere Wünsche:</span>
                        <span class="font-black {{ $mfgOverview['has_notes'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $mfgOverview['has_notes'] ? 'Ja (Kundenwunsch beachten)' : 'Nein' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="font-bold text-gray-600 dark:text-gray-400">2. Geschenkpapier:</span>
                        <span class="font-black {{ $mfgOverview['has_gift_wrap'] ? 'text-[var(--theme-color)]' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $mfgOverview['gift_wrap_summary'] }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="font-bold text-gray-600 dark:text-gray-400">3. Edle Schmuckbox:</span>
                        <span class="font-black {{ $mfgOverview['has_gift_box'] ? 'text-[var(--theme-color)]' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $mfgOverview['gift_box_summary'] }}
                        </span>
                    </div>
                </div>
            @endif

            @if($model->hasSupplierProcurementItems())
                <div class="w-full mb-6 space-y-2 text-left">
                    @foreach($model->items as $supItem)
                        @if($supItem->requiresSupplierProcurement())
                            @php
                                $isSupOrdered = $supItem->isSupplierOrderPlaced();
                                $missingSupQty = $supItem->getMissingSupplierQuantity();
                                $supReorderUrl = $supItem->getSupplierReorderUrl();
                                $supName = $supItem->getSupplierName();
                            @endphp
                            <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-2xl border {{ $isSupOrdered ? 'bg-sky-500/5 border-sky-500/30 dark:bg-sky-950/30' : 'bg-amber-500/10 border-amber-500/40 dark:bg-amber-950/30' }}">
                                <div class="min-w-0">
                                    <div class="text-[10px] font-black uppercase tracking-widest {{ $isSupOrdered ? 'text-sky-700 dark:text-sky-300' : 'text-amber-700 dark:text-amber-300' }}">
                                        @if($isSupOrdered)
                                            Rohling beim Lieferanten bestellt ✓
                                        @else
                                            Rohling nachbestellen ({{ $missingSupQty }}× benötigt)
                                        @endif
                                    </div>
                                    <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 truncate">
                                        {{ $supItem->product_name }}{{ $supName ? ' · ' . $supName : '' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @if($supReorderUrl)
                                        <a href="{{ $supReorderUrl }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-700 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-colors">
                                            <span>Kauflink</span>
                                        </a>
                                    @endif
                                    <button type="button"
                                            wire:click.prevent="toggleSupplierOrdered('{{ $supItem->id }}')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors cursor-pointer {{ $isSupOrdered ? 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30 hover:bg-sky-500/25' : 'bg-amber-500 text-gray-950 hover:bg-amber-400 border border-amber-600/30' }}">
                                        <span>{{ $isSupOrdered ? 'Bestellt ✓' : 'Als bestellt markieren' }}</span>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <button type="button"
                    wire:click="startOrderProcessing"
                    class="w-full sm:w-auto min-w-[240px] inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-sm font-black uppercase tracking-widest shadow-lg transition-all cursor-pointer">
                <span>Jetzt starten</span>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </button>

            <button type="button"
                    wire:click="toggleOptionalOrderDetails"
                    aria-expanded="{{ $isOptionalOpen ? 'true' : 'false' }}"
                    class="mt-5 inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-[var(--theme-color)] transition-colors cursor-pointer">
                <span>{{ $isOptionalOpen ? 'Zusatzinfos wieder ausblenden' : 'Optional: Produktkonfigurator & Bestelldetails vorab prüfen' }}</span>
                <svg class="w-3.5 h-3.5 transition-transform {{ $isOptionalOpen ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            @if($model->canBePermanentlyDeleted() && (auth('admin')->check() || \App\Services\AI\AiAuthHelper::isAdmin()))
                <div class="mt-6 pt-5 border-t border-gray-200 dark:border-gray-800 w-full flex justify-center">
                    <button type="button"
                            wire:click="delete('{{ $model->id }}')"
                            wire:confirm="Unbezahlte Bestellung #{{ $model->order_number }} wirklich unwiderruflich aus der Datenbank löschen?"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/40 hover:bg-red-600 hover:text-white transition-all cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>Bestellung unwiderruflich löschen</span>
                    </button>
                </div>
            @endif
        </div>

        @if($isOptionalOpen)
            <div class="w-full max-w-6xl mx-auto mt-6 shrink-0 flex flex-col lg:flex-row rounded-[2rem] border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xl">
                @include('livewire.shop.order.order-offer-detail-partials.left', ['isEmbeddedOptionalPanel' => true])
                @include('livewire.shop.order.order-offer-detail-partials.right', ['suppressCockpitInRight' => true, 'isEmbeddedOptionalPanel' => true])
            </div>
        @endif
    </div>

@elseif($isOrder)
    {{-- ZENTRIERTER 3-SCHRITTE-KERNABLAUF (Schritt 1: Laser-Cockpit je Artikel -> Schritt 2: Verpackung & Versand -> Schritt 3: Abgeschlossen) --}}
    @php
        $previewChecklist = $previewItem ? $previewItem->resolveManufacturingChecklist() : null;
        $totalOrderItems = $model->items->count();
        $openOrderItemsCount = $model->items->where('is_completed', false)->count();
        $activeItemIndex = 1;
        foreach ($model->items->values() as $idx => $it) {
            if ($previewItem && (string) $it->id === (string) $previewItem->id) {
                $activeItemIndex = $idx + 1;
                break;
            }
        }
    @endphp

    <div class="w-full flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-8 flex flex-col items-center">
        <div class="max-w-3xl w-full shrink-0 space-y-6">

            {{-- ================================================================= --}}
            {{-- SCHRITT 1: IN BEARBEITUNG (Zentriertes Live-Laser-Cockpit je Artikel) --}}
            {{-- ================================================================= --}}
            @if($currentOrderStep === 1)
                <div class="bg-white dark:bg-gray-900/95 border border-gray-200 dark:border-gray-800 rounded-[2rem] sm:rounded-[2.5rem] p-5 sm:p-7 shadow-xl space-y-5">

                    {{-- Kopfzeile + Großbestellungs-/Mehrpositions-Umschalter --}}
                    <div class="flex flex-col gap-3.5 pb-4 border-b border-gray-200 dark:border-gray-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-xl bg-[var(--theme-color)] text-gray-950 flex items-center justify-center text-sm font-black shadow-sm shrink-0">
                                    1
                                </span>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-gray-900 dark:text-white">
                                            Schritt 1 · In Bearbeitung
                                        </h2>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                            Artikel {{ $activeItemIndex }} von {{ $totalOrderItems }}
                                        </span>
                                    </div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-0.5">
                                        @if($previewItem)
                                            Aktueller Artikel: <strong class="text-gray-900 dark:text-white">{{ $previewItem->quantity }}× {{ $previewItem->product_name }}</strong>
                                            @if(!empty($previewItem->configuration['variant_name']))
                                                <span>({{ $previewItem->configuration['variant_name'] }})</span>
                                            @endif
                                        @endif
                                    </p>
                                </div>
                            </div>

                            {{-- Stückzahl-Zähler direkt sichtbar bei Menge > 1 (Großbestellungen) --}}
                            @if($previewItem && $previewItem->quantity > 1)
                                <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-950 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-800">
                                    <button type="button"
                                            wire:click="decrementCompletedQuantity('{{ $previewItem->id }}')"
                                            class="w-6 h-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer disabled:opacity-40"
                                            @if($previewItem->completed_quantity <= 0) disabled @endif>-</button>
                                    <div class="text-center px-1">
                                        <div class="text-[9px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                            Stück {{ min($previewItem->quantity, $previewItem->completed_quantity + 1) }} von {{ $previewItem->quantity }}
                                        </div>
                                        <div class="text-[10px] font-mono font-bold text-gray-700 dark:text-gray-300">
                                            {{ $previewItem->completed_quantity }}/{{ $previewItem->quantity }} gelasert
                                        </div>
                                    </div>
                                    <button type="button"
                                            wire:click="incrementCompletedQuantity('{{ $previewItem->id }}')"
                                            class="w-6 h-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer disabled:opacity-40"
                                            @if($previewItem->completed_quantity >= $previewItem->quantity) disabled @endif>+</button>
                                </div>
                            @endif
                        </div>

                        {{-- Artikel-Tabs bei Bestellungen mit mehreren Positionen (Skaliert auch für Großbestellungen übersichtlich) --}}
                        @if($totalOrderItems > 1)
                            <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-1">
                                @foreach($model->items as $ordItem)
                                    @php
                                        $isCurrentPill = $previewItem && (string) $ordItem->id === (string) $previewItem->id;
                                    @endphp
                                    <button type="button"
                                            wire:click="selectItemForPreview('{{ $ordItem->id }}')"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider border transition-all cursor-pointer shrink-0 {{ $isCurrentPill
                                                ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                                                : ($ordItem->is_completed
                                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20'
                                                    : 'bg-gray-50 dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]') }}">
                                        <span class="w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-black {{ $isCurrentPill ? 'bg-gray-950 text-[var(--theme-color)]' : ($ordItem->is_completed ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400') }}">
                                            {{ $ordItem->is_completed ? '✓' : $loop->iteration }}
                                        </span>
                                        <span class="truncate max-w-[180px]">{{ $ordItem->quantity }}× {{ $ordItem->product_name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- ZENTRIERTES XTOOL F2 ULTRA UV · LIVE-LASER-COCKPIT FÜR DEN AKTUELLEN ARTIKEL --}}
                    @include('livewire.shop.order.order-offer-detail-partials.laser-cockpit-card')

                    {{-- LIEFERANTEN-NACHBESTELLUNG (DIREKT SICHTBAR BEI BESTAND 0) --}}
                    @if($previewItem && $previewItem->requiresSupplierProcurement())
                        @php
                            $stepSupOrdered = $previewItem->isSupplierOrderPlaced();
                            $stepMissingQty = $previewItem->getMissingSupplierQuantity();
                            $stepReorderUrl = $previewItem->getSupplierReorderUrl();
                            $stepSupName = $previewItem->getSupplierName();
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-2xl border {{ $stepSupOrdered ? 'bg-sky-500/5 border-sky-500/30 dark:bg-sky-950/30' : 'bg-amber-500/10 border-amber-500/40 dark:bg-amber-950/30' }}">
                            <div class="min-w-0">
                                <div class="text-[10px] font-black uppercase tracking-widest {{ $stepSupOrdered ? 'text-sky-700 dark:text-sky-300' : 'text-amber-700 dark:text-amber-300' }}">
                                    @if($stepSupOrdered)
                                        Rohling beim Lieferanten bestellt ✓
                                    @else
                                        Rohling nachbestellen ({{ $stepMissingQty }}× benötigt)
                                    @endif
                                </div>
                                <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 truncate">
                                    Auftragsfertigung{{ $stepSupName ? ' · ' . $stepSupName : '' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                @if($stepReorderUrl)
                                    <a href="{{ $stepReorderUrl }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-700 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-colors">
                                        <span>Kauflink</span>
                                    </a>
                                @endif
                                <button type="button"
                                        wire:click.prevent="toggleSupplierOrdered('{{ $previewItem->id }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors cursor-pointer {{ $stepSupOrdered ? 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30 hover:bg-sky-500/25' : 'bg-amber-500 text-gray-950 hover:bg-amber-400 border border-amber-600/30' }}">
                                    <span>{{ $stepSupOrdered ? 'Bestellt ✓' : 'Als bestellt markieren' }}</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- KOMPAKTE XTOOL ANLEITUNG & LASER-WERTE DIREKT UNTER DEM COCKPIT --}}
                    @if($previewItem && $previewItem->product && $previewItem->product->isPhysical() && $previewItem->product->isPersonalizable() && !$isDigitalItem && !$isService)
                        @php
                            $stepLaserGuide = $previewItem->product->getLaserInstructionSummary();
                        @endphp
                        @if($stepLaserGuide['is_complete'])
                            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-950/80 p-3.5 space-y-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-900 dark:text-white">xTool Anleitung</span>
                                        <span class="px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] text-[9px] font-black uppercase tracking-wider">{{ $stepLaserGuide['material'] }}</span>
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 text-[9px] font-mono font-black">Fokus {{ $stepLaserGuide['focus_formatted'] }}</span>
                                    </div>
                                    <button type="button"
                                            x-data="{ copiedStepLaser: false }"
                                            @click="if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(@js($stepLaserGuide['copy_summary'])); } else { const ta = document.createElement('textarea'); ta.value = @js($stepLaserGuide['copy_summary']); document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); } copiedStepLaser = true; setTimeout(() => copiedStepLaser = false, 1800)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider border transition-all cursor-pointer"
                                            :class="copiedStepLaser ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)]'">
                                        <span x-show="!copiedStepLaser">Werte kopieren</span>
                                        <span x-show="copiedStepLaser" x-cloak>✓ Kopiert</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5 text-center">
                                    <div class="bg-white dark:bg-gray-900 rounded-lg px-2 py-1.5 border border-gray-200/70 dark:border-gray-800">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">Puls</span>
                                        <span class="block text-[11px] font-mono font-black text-gray-900 dark:text-white">{{ $stepLaserGuide['dot_duration_us'] }} µs</span>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 rounded-lg px-2 py-1.5 border border-gray-200/70 dark:border-gray-800">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">Leistung</span>
                                        <span class="block text-[11px] font-mono font-black text-gray-900 dark:text-white">{{ $stepLaserGuide['power_percent'] }} %</span>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 rounded-lg px-2 py-1.5 border border-gray-200/70 dark:border-gray-800">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">DPI</span>
                                        <span class="block text-[11px] font-mono font-black text-gray-900 dark:text-white">{{ $stepLaserGuide['dpi'] }}</span>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 rounded-lg px-2 py-1.5 border border-gray-200/70 dark:border-gray-800">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">Durchgänge</span>
                                        <span class="block text-[11px] font-mono font-black text-gray-900 dark:text-white">{{ $stepLaserGuide['passes'] }}&times;</span>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 rounded-lg px-2 py-1.5 border border-gray-200/70 dark:border-gray-800">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">Bitmap</span>
                                        <span class="block text-[11px] font-bold text-gray-900 dark:text-white truncate">{{ $stepLaserGuide['bitmap_mode'] }}</span>
                                    </div>
                                    <div class="bg-white dark:bg-gray-900 rounded-lg px-2 py-1.5 border border-gray-200/70 dark:border-gray-800">
                                        <span class="block text-[8px] font-black uppercase tracking-wider text-gray-400">Richtung</span>
                                        <span class="block text-[11px] font-bold text-gray-900 dark:text-white truncate">{{ $stepLaserGuide['engrave_mode'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- KOMPAKTE KONFIGURATOR-CHECKLISTE FÜR DIESEN ARTIKEL (Wünsche, Geschenkpapier, Schmuckbox) --}}
                    @if($previewItem && $previewChecklist)
                        <div class="space-y-2 pt-1">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 flex items-center justify-between">
                                <span>Konfigurator-Vorgaben für {{ $previewItem->product_name }}</span>
                                <span>{{ $mfgOverview['completed_items'] }} / {{ $mfgOverview['total_items'] }} Artikel fertig</span>
                            </div>

                            {{-- 1. Besondere Wünsche an die Manufaktur --}}
                            <div wire:click="toggleItemChecklistTask('{{ $previewItem->id }}', 'notes')"
                                 class="flex items-start justify-between gap-3 p-3 rounded-2xl border transition-all cursor-pointer {{ $previewChecklist['notes']['has_note']
                                    ? ($previewChecklist['notes']['checked']
                                        ? 'bg-emerald-500/10 border-emerald-500/40 text-gray-900 dark:text-white'
                                        : 'bg-amber-500/10 border-amber-500/50 text-gray-900 dark:text-white shadow-sm')
                                    : 'bg-gray-50 dark:bg-gray-950/70 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <div class="w-5 h-5 rounded-lg border-2 flex items-center justify-center shrink-0 mt-0.5 {{ $previewChecklist['notes']['checked']
                                        ? 'bg-emerald-500 border-emerald-500 text-white'
                                        : 'border-amber-500 text-transparent' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] font-black uppercase tracking-wider {{ $previewChecklist['notes']['has_note'] && !$previewChecklist['notes']['checked'] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-700 dark:text-gray-300' }}">
                                            1. Besondere Wünsche an die Manufaktur: {{ $previewChecklist['notes']['has_note'] ? 'JA' : 'NEIN' }}
                                        </div>
                                        <div class="text-xs font-bold mt-0.5 break-words {{ $previewChecklist['notes']['has_note'] ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $previewChecklist['notes']['has_note'] ? ('„' . $previewChecklist['notes']['text'] . '“') : 'Keine besonderen Wünsche angegeben' }}
                                        </div>
                                    </div>
                                </div>
                                <span class="shrink-0 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider {{ $previewChecklist['notes']['checked'] ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500 text-gray-950' }}">
                                    {{ $previewChecklist['notes']['has_note'] ? ($previewChecklist['notes']['checked'] ? 'Erledigt ✓' : 'Jetzt beachten') : 'OK ✓' }}
                                </span>
                            </div>

                            {{-- 2. Geschenkpapier --}}
                            <div wire:click="toggleItemChecklistTask('{{ $previewItem->id }}', 'gift_wrap')"
                                 class="flex items-start justify-between gap-3 p-3 rounded-2xl border transition-all cursor-pointer {{ $previewChecklist['gift_wrap']['selected']
                                    ? ($previewChecklist['gift_wrap']['checked']
                                        ? 'bg-emerald-500/10 border-emerald-500/40 text-gray-900 dark:text-white'
                                        : 'bg-[var(--theme-color-10)] border-[var(--theme-color-50)] text-gray-900 dark:text-white shadow-sm')
                                    : 'bg-gray-50 dark:bg-gray-950/70 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <div class="w-5 h-5 rounded-lg border-2 flex items-center justify-center shrink-0 mt-0.5 {{ $previewChecklist['gift_wrap']['checked']
                                        ? 'bg-emerald-500 border-emerald-500 text-white'
                                        : 'border-[var(--theme-color)] text-transparent' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                            2. Geschenkpapier ausgewählt: {{ $previewChecklist['gift_wrap']['short_label'] }}
                                        </div>
                                        <div class="text-xs font-bold mt-0.5 {{ $previewChecklist['gift_wrap']['selected'] ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $previewChecklist['gift_wrap']['instruction'] }}
                                        </div>
                                    </div>
                                </div>
                                <span class="shrink-0 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider {{ $previewChecklist['gift_wrap']['checked'] ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-[var(--theme-color)] text-gray-950' }}">
                                    {{ $previewChecklist['gift_wrap']['selected'] ? ($previewChecklist['gift_wrap']['checked'] ? 'Erledigt ✓' : ($previewChecklist['gift_wrap']['mode'] === 'loose' ? 'Dazu legen' : 'Verpacken')) : 'Nein ✓' }}
                                </span>
                            </div>

                            {{-- 3. Edle Schmuckbox --}}
                            <div wire:click="toggleItemChecklistTask('{{ $previewItem->id }}', 'gift_box')"
                                 class="flex items-start justify-between gap-3 p-3 rounded-2xl border transition-all cursor-pointer {{ $previewChecklist['gift_box']['selected']
                                    ? ($previewChecklist['gift_box']['checked']
                                        ? 'bg-emerald-500/10 border-emerald-500/40 text-gray-900 dark:text-white'
                                        : 'bg-[var(--theme-color-10)] border-[var(--theme-color-50)] text-gray-900 dark:text-white shadow-sm')
                                    : 'bg-gray-50 dark:bg-gray-950/70 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <div class="w-5 h-5 rounded-lg border-2 flex items-center justify-center shrink-0 mt-0.5 {{ $previewChecklist['gift_box']['checked']
                                        ? 'bg-emerald-500 border-emerald-500 text-white'
                                        : 'border-[var(--theme-color)] text-transparent' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                            3. Edle Schmuckbox: {{ $previewChecklist['gift_box']['short_label'] }}
                                        </div>
                                        <div class="text-xs font-bold mt-0.5 {{ $previewChecklist['gift_box']['selected'] ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $previewChecklist['gift_box']['instruction'] }}
                                        </div>
                                    </div>
                                </div>
                                <span class="shrink-0 px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider {{ $previewChecklist['gift_box']['checked'] ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-[var(--theme-color)] text-gray-950' }}">
                                    {{ $previewChecklist['gift_box']['selected'] ? ($previewChecklist['gift_box']['checked'] ? 'Erledigt ✓' : 'In Box legen') : 'Nein ✓' }}
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Haupt-Weiter-Button für Schritt 1 (Wiederholend bis alle Artikel der Bestellung erledigt sind -> dann Schritt 2) --}}
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        @if($previewItem && $openOrderItemsCount > 1)
                            <button type="button"
                                    wire:click="completeCurrentItemAndAdvance('{{ $previewItem->id }}')"
                                    class="flex-1 flex items-center justify-center gap-2.5 px-5 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs font-black uppercase tracking-widest shadow-md transition-all cursor-pointer">
                                <span>Artikel {{ $activeItemIndex }} erledigt &amp; weiter zum nächsten Artikel</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                            <button type="button"
                                    wire:click="completeStepOneAndProceedToShipping"
                                    class="px-4 py-4 rounded-2xl bg-gray-100 dark:bg-gray-800 hover:bg-emerald-500 hover:text-gray-950 text-gray-700 dark:text-gray-300 text-[10px] font-black uppercase tracking-wider border border-gray-200 dark:border-gray-700 transition-all cursor-pointer shrink-0">
                                Alle {{ $totalOrderItems }} Artikel fertig &rarr; Schritt 2
                            </button>
                        @else
                            <button type="button"
                                    wire:click="completeStepOneAndProceedToShipping"
                                    class="w-full flex items-center justify-center gap-2.5 px-6 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs sm:text-sm font-black uppercase tracking-widest shadow-md transition-all cursor-pointer">
                                <span>Artikel fertig gelasert &amp; weiter zu Schritt 2 (Verpackung &amp; Versand)</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        @endif
                    </div>
                </div>

            {{-- ================================================================= --}}
            {{-- SCHRITT 2: VERPACKUNG & VERSAND (Zentrierter Packtisch-Assistent) --}}
            {{-- ================================================================= --}}
            @elseif($currentOrderStep === 2)
                @php
                    $packingPlan = $model->getPackingStationPlan();
                @endphp
                <div class="bg-white dark:bg-gray-900/95 border border-gray-200 dark:border-gray-800 rounded-[2rem] sm:rounded-[2.5rem] p-5 sm:p-8 shadow-xl space-y-6">

                    {{-- Kopfzeile --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-gray-200 dark:border-gray-800">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-[var(--theme-color)] text-gray-950 flex items-center justify-center text-sm font-black shadow-sm shrink-0">
                                2
                            </span>
                            <div>
                                <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-gray-900 dark:text-white">
                                    Schritt 2 · Verpackung &amp; Versand
                                </h2>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-0.5">
                                    Verpacke die Produkte wie unten angegeben, hake Dankeskarte &amp; Warnaufkleber ab und drucke das DHL-Label.
                                </p>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-xl bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] text-[10px] font-mono font-black">
                            {{ $packingPlan['dhl_class'] }} · {{ number_format($packingPlan['total_weight_kg'], 2, ',', '.') }} kg
                        </span>
                    </div>

                    {{-- 1. WELCHE KARTONS WIE OFT FÜR WELCHE PRODUKTE? --}}
                    <div class="space-y-3">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                            1. Benötigte Kartons &amp; Verpackungs-Zuordnung für diese Bestellung
                        </div>

                        {{-- Umkarton-Empfehlung --}}
                        <div class="p-3.5 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <span class="text-lg">📦</span>
                                <div>
                                    <div class="text-[10px] font-black uppercase tracking-wider text-[var(--theme-color)]">
                                        Empfohlener Versandkarton / Umkarton ({{ $packingPlan['outer_package_count'] }}× Paket)
                                    </div>
                                    <div class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $packingPlan['outer_box_hint'] }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Aggregierte Karton-Stückliste --}}
                        @if(!empty($packingPlan['cartons']))
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach($packingPlan['cartons'] as $carton)
                                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex flex-col justify-between gap-1.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-xs font-black text-gray-900 dark:text-white">
                                                {{ $carton['count'] }}× {{ $carton['label'] }}
                                            </span>
                                            @if(!empty($carton['dimensions']))
                                                <span class="px-2 py-0.5 rounded-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 font-mono text-[9px] font-bold text-gray-600 dark:text-gray-300 shrink-0">
                                                    {{ $carton['dimensions'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                            Für: <strong class="text-gray-700 dark:text-gray-200">{{ implode(', ', $carton['products']) }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Artikelgenaue Packanweisung --}}
                        <div class="space-y-2">
                            @foreach($packingPlan['items'] as $pItem)
                                <div class="p-3.5 rounded-2xl bg-white dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <div class="text-xs font-black text-gray-900 dark:text-white">
                                            {{ $pItem['quantity'] }}× {{ $pItem['product_name'] }}
                                            @if(!empty($pItem['variant_name']))
                                                <span class="text-[10px] font-bold text-[var(--theme-color)]">({{ $pItem['variant_name'] }})</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 mt-0.5">
                                            Verpackung: <span class="text-gray-800 dark:text-gray-200 font-bold">{{ $pItem['box_label'] }}</span>
                                            @if(!empty($pItem['box_dimensions']))
                                                <span class="font-mono text-[10px]">({{ $pItem['box_dimensions'] }})</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-col sm:items-end gap-1">
                                        @foreach($pItem['packing_instructions'] as $instr)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-800 text-[10px] font-bold">
                                                {{ $instr }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- 2. VERBINDLICHE PACKTISCH-PRÜFUNG (Dankeskarte & Warnaufkleber Zerbrechlich) --}}
                    <div class="space-y-2.5 pt-2 border-t border-gray-200 dark:border-gray-800">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                            2. Pflicht-Prüfung vor dem Verschließen des Pakets
                        </div>

                        {{-- Prüfpunkt A: Dankeskarte beigelegt? --}}
                        <div wire:click="toggleOrderPackingCheck('thank_you_card')"
                             class="flex items-center justify-between gap-3 p-3.5 rounded-2xl border transition-all cursor-pointer {{ $mfgOverview['thank_you_card_checked']
                                ? 'bg-emerald-500/10 border-emerald-500/40 text-gray-900 dark:text-white'
                                : 'bg-amber-500/10 border-amber-500/50 text-gray-900 dark:text-white shadow-sm' }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center shrink-0 {{ $mfgOverview['thank_you_card_checked']
                                    ? 'bg-emerald-500 border-emerald-500 text-white'
                                    : 'border-amber-500 text-transparent' }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div>
                                    <div class="text-xs font-black uppercase tracking-wider">
                                        Dankeskarte beigelegt?
                                    </div>
                                    <div class="text-[11px] font-medium text-gray-600 dark:text-gray-300">
                                        Mein-Seelenfunke Dankeskarte oben auf die verpackte Ware legen.
                                    </div>
                                </div>
                            </div>
                            <span class="shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {{ $mfgOverview['thank_you_card_checked'] ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-gray-950' }}">
                                {{ $mfgOverview['thank_you_card_checked'] ? 'Beigelegt ✓' : 'Jetzt prüfen' }}
                            </span>
                        </div>

                        {{-- Prüfpunkt B: Warnaufkleber (Zerbrechlich / Vorsicht Glas) --}}
                        <div wire:click="toggleOrderPackingCheck('fragile_sticker')"
                             class="flex items-center justify-between gap-3 p-3.5 rounded-2xl border transition-all cursor-pointer {{ $mfgOverview['fragile_sticker_checked']
                                ? 'bg-emerald-500/10 border-emerald-500/40 text-gray-900 dark:text-white'
                                : ($mfgOverview['requires_fragile_sticker']
                                    ? 'bg-red-500/10 border-red-500/50 text-gray-900 dark:text-white shadow-sm'
                                    : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300') }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-6 h-6 rounded-lg border-2 flex items-center justify-center shrink-0 {{ $mfgOverview['fragile_sticker_checked']
                                    ? 'bg-emerald-500 border-emerald-500 text-white'
                                    : ($mfgOverview['requires_fragile_sticker'] ? 'border-red-500 text-transparent' : 'border-gray-400 text-transparent') }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div>
                                    <div class="text-xs font-black uppercase tracking-wider flex flex-wrap items-center gap-2">
                                        <span>Prüfung Warnaufkleber (Zerbrechlich / Vorsicht Glas)</span>
                                        @if($mfgOverview['requires_fragile_sticker'])
                                            <span class="px-2 py-0.5 rounded bg-red-500/20 text-red-700 dark:text-red-300 border border-red-500/40 text-[9px] font-black">
                                                PFLICHT BEI GLAS / KRISTALL / SCHIEFER
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] font-medium text-gray-600 dark:text-gray-300">
                                        @if($mfgOverview['requires_fragile_sticker'])
                                            Zerbrechliche Ware enthalten ({{ implode(', ', $mfgOverview['fragile_items']) }}) -> Warnaufkleber gut sichtbar außen aufkleben!
                                        @else
                                            Kein Glas/Kristall erkannt (Aufkleber optional bzw. nicht erforderlich).
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <span class="shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {{ $mfgOverview['fragile_sticker_checked'] ? 'bg-emerald-500 text-white' : ($mfgOverview['requires_fragile_sticker'] ? 'bg-red-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                                {{ $mfgOverview['fragile_sticker_checked'] ? 'Geprüft ✓' : ($mfgOverview['requires_fragile_sticker'] ? 'Aufkleben!' : 'Optional') }}
                            </span>
                        </div>
                    </div>

                    {{-- Bereits erzeugte DHL-Labels zum direkten 1-Klick-Nachdrucken --}}
                    @if($model->shipments->isNotEmpty())
                        <div class="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-800">
                            <div class="text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">
                                Erstellte DHL-Versandlabels ({{ $model->shipments->count() }})
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach($model->shipments as $shipment)
                                    @php
                                        $hasLabelPdf = !empty($shipment->shipping_label_path);
                                    @endphp
                                    <div class="flex items-center justify-between gap-2 p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                                        <div class="min-w-0">
                                            <div class="text-[9px] font-black uppercase tracking-wider text-gray-400">Paket {{ $loop->iteration }}</div>
                                            <div class="text-xs font-mono font-bold text-[var(--theme-color)] truncate">{{ $shipment->tracking_number }}</div>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button type="button"
                                                    wire:click="printDhlLabelViaFunkenwerk('{{ $shipment->id }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-[var(--theme-color)] text-gray-950 text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                                🖨️ MUNBYN Druck
                                            </button>
                                            @if($hasLabelPdf)
                                                <button type="button"
                                                        wire:click="downloadDhlLabel('{{ $shipment->id }}')"
                                                        class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-[10px] font-black text-gray-700 dark:text-gray-200 cursor-pointer">
                                                    PDF
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- 3. HAUPTAKTIONEN: LABEL DRUCKEN & WEITER ZU SCHRITT 3 (BESTELLUNG ABGESCHLOSSEN) --}}
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if(!$model->isOnlyDigital())
                            <button type="button"
                                    wire:click="openDhlModal('{{ $model->id }}')"
                                    class="flex items-center justify-center gap-2.5 px-5 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs font-black uppercase tracking-wider shadow-md transition-all cursor-pointer">
                                <span class="w-5 h-5 rounded-full bg-gray-950 text-[var(--theme-color)] flex items-center justify-center text-[10px] font-black">1</span>
                                <span>DHL Label erstellen &amp; drucken</span>
                            </button>
                        @endif
                        <button type="button"
                                wire:click="completeStepTwoShipped(true)"
                                class="flex items-center justify-center gap-2.5 px-5 py-4 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-gray-950 text-xs font-black uppercase tracking-wider shadow-md transition-all cursor-pointer">
                            <span class="w-5 h-5 rounded-full bg-gray-950 text-emerald-400 flex items-center justify-center text-[10px] font-black">2</span>
                            <span>Fertig verpackt &amp; versendet &rarr; Schritt 3</span>
                        </button>
                    </div>
                </div>

            {{-- ================================================================= --}}
            {{-- SCHRITT 3: BESTELLUNG ABGESCHLOSSEN (Zentrierte Abschluss-Kachel) --}}
            {{-- ================================================================= --}}
            @else
                <div class="bg-white dark:bg-gray-900/95 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] p-8 sm:p-10 shadow-xl text-center flex flex-col items-center space-y-5">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shadow-sm">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>

                    <div>
                        <h2 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                            Schritt 3 · Bestellung abgeschlossen
                        </h2>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mt-1">
                            Auftrag #{{ $model->order_number }} · {{ $billing['name'] }}
                        </p>
                    </div>

                    <div class="w-full max-w-md bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 text-left space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-500">1. Laser-Fertigung:</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400">✓ Alle {{ $mfgOverview['total_items'] }} Artikel erledigt</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-500">2. Verpackung &amp; Versand:</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400">✓ Versendet{{ $model->tracking_number ? ' (' . $model->tracking_number . ')' : '' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-500">3. Auftragsstatus:</span>
                            <span class="font-black {{ $model->status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : 'text-[var(--theme-color)]' }}">
                                {{ $model->status === 'completed' ? '✓ Abgeschlossen' : 'Bereit zum Abschluss' }}
                            </span>
                        </div>
                    </div>

                    <div class="w-full max-w-md flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
                        @if($model->status !== 'completed')
                            <button type="button"
                                    wire:click="completeStepThreeOrder"
                                    class="flex-1 flex items-center justify-center gap-2 px-6 py-4 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-gray-950 text-xs font-black uppercase tracking-widest shadow-md transition-all cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span>Bestellung endgültig abschließen</span>
                            </button>
                        @endif

                        @if(!empty($this->nextQueueOrder))
                            <button type="button"
                                    wire:click="openDetail('{{ $this->nextQueueOrder['id'] }}')"
                                    class="flex-1 flex items-center justify-center gap-2 px-6 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs font-black uppercase tracking-widest shadow-md transition-all cursor-pointer">
                                <span>Nächster Auftrag (#{{ $this->nextQueueOrder['order_number'] }})</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ================================================================= --}}
            {{-- OPTIONALE ZUSATZINFOS (Produktkonfigurator, Laser-Steckbrief, Dateien & Downloads, Lieferadresse & Kostenübersicht) --}}
            {{-- ================================================================= --}}
            <div class="w-full flex justify-center pt-1">
                <button type="button"
                        wire:click="toggleOptionalOrderDetails"
                        aria-expanded="{{ $isOptionalOpen ? 'true' : 'false' }}"
                        class="w-full flex items-center justify-between gap-3 px-5 py-3.5 rounded-2xl bg-white/80 dark:bg-gray-900/80 hover:bg-white dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 shadow-sm transition-all cursor-pointer">
                    <div class="flex flex-wrap items-center gap-2 text-left">
                        <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">Optional einblendbar:</span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300">
                            Produktkonfigurator (Kunden-Ansicht) · Laser-Steckbrief · Dateien &amp; Downloads · Lieferadresse &amp; Kostenübersicht
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0 text-[10px] font-black uppercase tracking-wider text-gray-500">
                        <span>{{ $isOptionalOpen ? 'Ausblenden' : 'Einblenden' }}</span>
                        <svg class="w-4 h-4 transition-transform duration-300 {{ $isOptionalOpen ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
            </div>
        </div>

        {{-- AUFKLAPPBARES 2-SEITEN-DETAIL-PANEL (Optional anzeigbar) --}}
        @if($isOptionalOpen)
            <div class="w-full max-w-7xl mx-auto mt-6 shrink-0 flex flex-col lg:flex-row rounded-[2rem] border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl">
                @include('livewire.shop.order.order-offer-detail-partials.left', ['isEmbeddedOptionalPanel' => true])
                @include('livewire.shop.order.order-offer-detail-partials.right', ['suppressCockpitInRight' => ($currentOrderStep === 1), 'isEmbeddedOptionalPanel' => true])
            </div>
        @endif
    </div>
@else
    {{-- ANGEBOTS-ANSICHT (Quote Context) --}}
    @include('livewire.shop.order.order-offer-detail-partials.left')
    @include('livewire.shop.order.order-offer-detail-partials.right')
@endif
