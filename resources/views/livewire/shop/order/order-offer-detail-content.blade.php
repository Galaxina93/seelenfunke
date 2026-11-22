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
@endphp

@if($isOrder && $model->status === 'pending')
    {{-- START-ANSICHT FÜR WARTENDE BESTELLUNGEN --}}
    @once
        <style>
            .sf-order-magic-ring {
                position: absolute;
                inset: 0;
                padding: 1.5px;
                border-radius: inherit;
                pointer-events: none;
                z-index: 20;
                overflow: hidden;
                -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
                -webkit-mask-composite: xor;
                mask-composite: exclude;
            }
            .sf-order-magic-ring::before {
                content: '';
                position: absolute;
                left: 50%;
                top: 50%;
                width: 240%;
                aspect-ratio: 1 / 1;
                transform: translate(-50%, -50%) rotate(0deg);
                background: conic-gradient(
                    from 0deg,
                    transparent 0deg,
                    var(--theme-color-20) 50deg,
                    var(--theme-color) 105deg,
                    #C5A059 135deg,
                    #FDE68A 158deg,
                    #FFFBEB 172deg,
                    #C5A059 196deg,
                    var(--theme-color-20) 255deg,
                    transparent 360deg
                );
                animation: sfOrderMagicRingSpin 2.6s linear infinite;
                will-change: transform;
            }
            .sf-order-magic-star {
                display: inline-block;
                animation: sfOrderMagicStarPulse 1.4s ease-in-out infinite;
                will-change: transform, opacity;
            }
            .sf-order-micro-spark {
                position: absolute;
                pointer-events: none;
                color: #C5A059;
                font-size: 9px;
                line-height: 1;
                opacity: 0;
                animation: sfOrderMicroSparkRise 1.5s ease-out infinite;
                will-change: transform, opacity;
            }
            .sf-order-magic-btn-shimmer::after {
                content: '';
                position: absolute;
                inset: 0;
                pointer-events: none;
                background: linear-gradient(
                    110deg,
                    transparent 20%,
                    rgba(255, 251, 235, 0.42) 50%,
                    transparent 80%
                );
                transform: translateX(-120%);
                animation: sfOrderMagicBtnSweep 1.6s ease-in-out infinite;
                will-change: transform;
            }
            @keyframes sfOrderMagicRingSpin {
                from { transform: translate(-50%, -50%) rotate(0deg); }
                to   { transform: translate(-50%, -50%) rotate(360deg); }
            }
            @keyframes sfOrderMagicStarPulse {
                0%, 100% { transform: scale(0.94) rotate(0deg); opacity: 0.88; }
                50%      { transform: scale(1.14) rotate(12deg); opacity: 1; }
            }
            @keyframes sfOrderMicroSparkRise {
                0%   { transform: translate3d(0, 6px, 0) scale(0.55); opacity: 0; }
                30%  { opacity: 0.95; }
                100% { transform: translate3d(var(--sx, 0px), -20px, 0) scale(1.05); opacity: 0; }
            }
            @keyframes sfOrderMagicBtnSweep {
                0%   { transform: translateX(-120%); }
                100% { transform: translateX(120%); }
            }
            @media (prefers-reduced-motion: reduce) {
                .sf-order-magic-ring::before,
                .sf-order-magic-star,
                .sf-order-micro-spark,
                .sf-order-magic-btn-shimmer::after {
                    animation: none;
                }
            }
        </style>
    @endonce
    @php
        $hasAnyPendingSpecials = $mfgOverview && (
            !empty($mfgOverview['has_notes'])
            || !empty($mfgOverview['has_gift_wrap'])
            || !empty($mfgOverview['has_gift_box'])
        );
    @endphp
    <div class="w-full flex-1 flex flex-col items-center justify-start sm:justify-center p-4 sm:p-10 overflow-y-auto custom-scrollbar">
        <div x-data="{ startingMagic: false }"
             data-pending-order-card
             :class="startingMagic ? 'border-[var(--theme-color-50)]' : 'border-gray-200 dark:border-gray-800'"
             class="max-w-lg w-full shrink-0 bg-white dark:bg-gray-900/95 border rounded-[2.5rem] p-8 sm:p-10 shadow-xl dark:shadow-2xl text-center flex flex-col items-center relative transition-colors duration-300">

            {{-- Zarter umlaufender Seelenfunke-Magie-Rand beim Starten --}}
            <div x-cloak
                 x-show="startingMagic"
                 x-transition.opacity.duration.200ms
                 style="display: none;"
                 class="sf-order-magic-ring"
                 aria-hidden="true"></div>

            <div class="relative w-16 h-16 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center mb-5 shadow-sm">
                <svg x-show="!startingMagic" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>

                <div x-cloak
                     x-show="startingMagic"
                     style="display: none;"
                     class="relative w-full h-full flex items-center justify-center"
                     aria-hidden="true">
                    <span class="sf-order-magic-star text-2xl leading-none select-none text-[var(--theme-color)]">✦</span>
                    <span class="sf-order-micro-spark" style="left: 18%; top: 34%; --sx: -5px; animation-delay: 0s;">✦</span>
                    <span class="sf-order-micro-spark" style="right: 18%; top: 30%; --sx: 6px; animation-delay: 0.35s;">✦</span>
                    <span class="sf-order-micro-spark" style="left: 32%; top: 20%; --sx: -3px; animation-delay: 0.7s;">✦</span>
                    <span class="sf-order-micro-spark" style="right: 28%; top: 44%; --sx: 4px; animation-delay: 1.05s;">✦</span>
                </div>
            </div>

            <h2 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight mb-2">
                Wartend
            </h2>

            <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-6">
                Auftrag #{{ $model->order_number }} · {{ $billing['name'] }} · {{ $model->items->count() }} Artikel
            </p>

            {{-- Konfigurator-Vorgaben NUR einblenden, wenn tatsächlich gebucht/vorhanden --}}
            @if($hasAnyPendingSpecials)
                <div class="w-full bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 mb-6 text-left space-y-2">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-1">
                        Konfigurator-Vorgaben
                    </div>
                    @if(!empty($mfgOverview['has_notes']))
                        <div class="flex items-start justify-between gap-2 text-xs">
                            <span class="font-bold text-gray-600 dark:text-gray-400 shrink-0">Kundenwunsch:</span>
                            <span class="font-bold text-[var(--theme-color)] text-right">{{ $mfgOverview['notes_summary'] }}</span>
                        </div>
                    @endif
                    @if(!empty($mfgOverview['has_gift_wrap']))
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="font-bold text-gray-600 dark:text-gray-400">Geschenkpapier:</span>
                            <span class="font-bold text-[var(--theme-color)]">{{ $mfgOverview['gift_wrap_summary'] }}</span>
                        </div>
                    @endif
                    @if(!empty($mfgOverview['has_gift_box']))
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="font-bold text-gray-600 dark:text-gray-400">Edle Schmuckbox:</span>
                            <span class="font-bold text-[var(--theme-color)]">{{ $mfgOverview['gift_box_summary'] }}</span>
                        </div>
                    @endif
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
                            <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-2xl border bg-gray-50 dark:bg-gray-950/80 border-gray-200 dark:border-gray-800">
                                <div class="min-w-0">
                                    <div class="text-[10px] font-black uppercase tracking-widest {{ $isSupOrdered ? 'text-[var(--theme-color)]' : 'text-gray-900 dark:text-white' }}">
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
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors cursor-pointer {{ $isSupOrdered ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-40)]' : 'bg-[var(--theme-color)] text-gray-950' }}">
                                        <span>{{ $isSupOrdered ? 'Bestellt ✓' : 'Als bestellt markieren' }}</span>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <button type="button"
                    data-start-order-btn
                    @click="if (!startingMagic) { startingMagic = true; $wire.startOrderProcessing(); }"
                    :disabled="startingMagic"
                    :class="startingMagic ? 'sf-order-magic-btn-shimmer cursor-wait' : 'hover:brightness-95 cursor-pointer'"
                    class="relative overflow-hidden w-full sm:w-auto min-w-[240px] inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-[var(--theme-color)] text-gray-950 text-sm font-black uppercase tracking-widest shadow-lg transition-all">
                <span x-show="!startingMagic" class="inline-flex items-center justify-center gap-3">
                    <span>Jetzt starten</span>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </span>
                <span x-cloak x-show="startingMagic" style="display: none;" class="inline-flex items-center justify-center gap-2">
                    <span>✦ Magie wird aktiviert...</span>
                </span>
            </button>
        </div>
    </div>

@elseif($isOrder)
    {{-- ZENTRIERTER 3-SCHRITTE-KERNABLAUF --}}
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
        $hasPhysicalCockpit = $previewItem && $previewItem->product && $previewItem->product->isPhysical() && !$isDigitalItem && !$isService;
        $activeStepOneTab = $hasPhysicalCockpit ? (string) ($this->stepOneTab ?? 'cockpit') : 'preview';
        $hasApplicableItemChecklist = $previewChecklist && (
            !empty($previewChecklist['notes']['has_note'])
            || !empty($previewChecklist['gift_wrap']['selected'])
            || !empty($previewChecklist['gift_box']['selected'])
        );
    @endphp

    <div class="w-full flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-8 flex flex-col items-center">
        <div class="max-w-3xl w-full shrink-0 space-y-6">

            {{-- ================================================================= --}}
            {{-- SCHRITT 1: IN BEARBEITUNG (2-Tab Ansicht: Cockpit & Vorschau) --}}
            {{-- ================================================================= --}}
            @if($currentOrderStep === 1)
                <div class="bg-white dark:bg-gray-900/95 border border-gray-200 dark:border-gray-800 rounded-[2rem] sm:rounded-[2.5rem] p-5 sm:p-7 shadow-xl space-y-5">

                    @php
                        $previewHasBack = \App\Livewire\Shop\Order\OrderOverview::itemHasConfiguredBackSide($previewItem);
                        $previewEngravedSides = is_array($previewItem->configuration['laser_engraved_sides'] ?? null)
                            ? $previewItem->configuration['laser_engraved_sides']
                            : [];
                        $previewFrontDone = !empty($previewEngravedSides['front']);
                        $previewBackDone = !empty($previewEngravedSides['back']);
                        $previewDoneSidesCount = ($previewFrontDone ? 1 : 0) + ($previewBackDone ? 1 : 0);
                        $previewNeedsBackSwitch = $previewHasBack
                            && !$previewBackDone
                            && ($this->laserCockpitSide ?? 'front') !== 'back';
                    @endphp

                    {{-- Kopfzeile + rechts oben schlichter Text-Toggle "Details anzeigen" ohne Hintergrund --}}
                    <div class="flex flex-col gap-3.5 pb-4 border-b border-gray-200 dark:border-gray-800">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-xl bg-[var(--theme-color)] text-gray-950 flex items-center justify-center text-sm font-black shadow-sm shrink-0">
                                    1
                                </span>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-gray-900 dark:text-white">
                                            Schritt 1 · In Bearbeitung
                                        </h2>
                                        @if($totalOrderItems > 1)
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                                Artikel {{ $activeItemIndex }} von {{ $totalOrderItems }}
                                            </span>
                                        @endif
                                        @if($previewHasBack)
                                            <span data-step1-two-sided-badge
                                                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider {{ $previewBackDone
                                                      ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30'
                                                      : 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-40)]' }}">
                                                @if(!$previewBackDone)
                                                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)]"></span>
                                                @endif
                                                <span>2 Seiten ({{ $previewDoneSidesCount }}/2{{ $previewBackDone ? ' ✓' : '' }})</span>
                                            </span>
                                        @endif
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

                            <div class="flex items-center gap-4 ml-auto">
                                {{-- Stückzahl-Zähler direkt sichtbar bei Menge > 1 --}}
                                @if($previewItem && $previewItem->quantity > 1)
                                    <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-950 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-800">
                                        <button type="button"
                                                wire:click="decrementCompletedQuantity('{{ $previewItem->id }}')"
                                                class="w-6 h-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer disabled:opacity-40"
                                                @if($previewItem->completed_quantity <= 0) disabled @endif>-</button>
                                        <div class="text-center px-1">
                                            <div class="text-[10px] font-mono font-bold text-gray-900 dark:text-white">
                                                {{ $previewItem->completed_quantity }}/{{ $previewItem->quantity }}
                                            </div>
                                        </div>
                                        <button type="button"
                                                wire:click="incrementCompletedQuantity('{{ $previewItem->id }}')"
                                                class="w-6 h-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer disabled:opacity-40"
                                                @if($previewItem->completed_quantity >= $previewItem->quantity) disabled @endif>+</button>
                                    </div>
                                @endif

                                @if($hasPhysicalCockpit)
                                    <button type="button"
                                            x-show="stepOneTab === 'cockpit'"
                                            @click="showLaserDetails = !showLaserDetails"
                                            class="bg-transparent border-0 p-0 text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] transition-colors cursor-pointer">
                                        <span x-text="showLaserDetails ? 'Details ausblenden' : 'Details anzeigen'">Details anzeigen</span>
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Artikel-Auswahl bei Bestellungen mit mehreren Positionen --}}
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
                                                    ? 'bg-[var(--theme-color-10)] text-[var(--theme-color)] border-[var(--theme-color-30)]'
                                                    : 'bg-gray-50 dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]') }}">
                                        <span class="w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-black {{ $isCurrentPill ? 'bg-gray-950 text-[var(--theme-color)]' : ($ordItem->is_completed ? 'bg-[var(--theme-color)] text-gray-950' : 'bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400') }}">
                                            {{ $ordItem->is_completed ? '✓' : $loop->iteration }}
                                        </span>
                                        <span class="truncate max-w-[180px]">{{ $ordItem->quantity }}× {{ $ordItem->product_name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        {{-- 2-TAB UMSCHALTER: Tab 1 "xTool F2 Ultra UV · Live-Laser-Cockpit" | Tab 2 "Live-Konfigurations-Vorschau" --}}
                        @if($hasPhysicalCockpit)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 bg-gray-100 dark:bg-gray-950 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-800">
                                <button type="button"
                                        @click="switchStepOneTab('cockpit')"
                                        :class="stepOneTab === 'cockpit'
                                            ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm border border-gray-200/80 dark:border-gray-800'
                                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-transparent'"
                                        class="px-3.5 py-2.5 rounded-xl text-[10px] sm:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                    xTool F2 Ultra UV · Live-Laser-Cockpit
                                </button>
                                <button type="button"
                                        @click="switchStepOneTab('preview')"
                                        :class="stepOneTab === 'preview'
                                            ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm border border-gray-200/80 dark:border-gray-800'
                                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-transparent'"
                                        class="px-3.5 py-2.5 rounded-xl text-[10px] sm:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                    Live-Konfigurations-Vorschau
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- TAB 1: LIVE-LASER-COCKPIT | TAB 2: LIVE-KONFIGURATIONS-VORSCHAU (0 ms Client-Side Switch) --}}
                    @if($hasPhysicalCockpit)
                        <div x-show="stepOneTab === 'cockpit'">
                            @include('livewire.shop.order.order-offer-detail-partials.laser-cockpit-card')
                        </div>
                    @endif

                    @if($previewItem)
                        @php
                            $isItemCustomFields = (($previewItem->configuration['personalization_mode'] ?? null) === 'custom_fields')
                                || !empty($previewItem->configuration['custom_fields_data']);
                            $isAdminOrderEditable = !in_array((string) ($model->status ?? ''), ['shipped', 'completed', 'cancelled', 'refunded'], true);
                            $adminBlueprintTimeline = \App\Livewire\Shop\Order\OrderOverview::buildItemBlueprintTimeline($previewItem);
                        @endphp
                        <div @if($hasPhysicalCockpit) x-cloak x-show="stepOneTab === 'preview'" style="display: none;" @endif
                             class="space-y-3">
                            <div class="rounded-[1.5rem] sm:rounded-[2rem] border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 overflow-hidden {{ $isItemCustomFields ? 'min-h-0' : 'min-h-[500px]' }} flex flex-col">
                                @if($previewItem->product)
                                    @if($isAdminOrderEditable)
                                        <livewire:shop.product.product-configurator.product-configurator
                                            :product="$previewItem->product->id"
                                            :cartItem="$previewItem"
                                            :qty="$previewItem->quantity"
                                            context="order_edit"
                                            :key="'admin-step1-edit-'.$previewItem->id"
                                        />
                                    @else
                                        <livewire:shop.product.product-configurator.product-configurator
                                            :product="$previewItem->product->id"
                                            :initialData="$previewItem->configuration"
                                            :qty="$previewItem->quantity"
                                            context="preview"
                                            :key="'admin-step1-preview-'.$previewItem->id"
                                        />
                                    @endif
                                @else
                                    <div class="p-10 text-center text-xs font-bold text-gray-500">
                                        Produkt nicht mehr im Katalog vorhanden.
                                    </div>
                                @endif
                            </div>

                            {{-- Schlanke Blueprint-Verlaufsleiste für den Admin inkl. Lightbox im selben Fenster --}}
                            @if(!empty($adminBlueprintTimeline))
                                <div data-admin-blueprint-history
                                     x-data="{
                                        timeline: @js($adminBlueprintTimeline),
                                        lbOpen: false,
                                        lbIdx: 0,
                                        lbSide: 'front',
                                        openBlueprint(idx) {
                                            this.lbIdx = idx;
                                            this.lbSide = 'front';
                                            this.lbOpen = true;
                                        },
                                        closeBlueprint() {
                                            this.lbOpen = false;
                                        },
                                        activeEntry() {
                                            return this.timeline[this.lbIdx] || this.timeline[0] || {};
                                        }
                                     }"
                                     @keydown.escape.window="if (lbOpen) closeBlueprint()"
                                     class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950/90 border border-gray-200 dark:border-gray-800 space-y-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                            Blueprint-Verlauf ({{ count($adminBlueprintTimeline) }})
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($adminBlueprintTimeline as $bIdx => $bStep)
                                            <button type="button"
                                                    @click="openBlueprint({{ $bIdx }})"
                                                    class="flex items-center gap-3 p-2.5 rounded-xl border text-left transition-all cursor-pointer {{ $bStep['is_current']
                                                        ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)]'
                                                        : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }}">
                                                <div class="w-11 h-11 rounded-lg bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 overflow-hidden flex items-center justify-center shrink-0">
                                                    @if(!empty($bStep['front_url']))
                                                        <img src="{{ $bStep['front_url'] }}" alt="Version {{ $bStep['version'] }}" class="w-full h-full object-contain">
                                                    @else
                                                        <span class="text-[10px] font-mono font-black text-[var(--theme-color)]">V{{ $bStep['version'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-900 dark:text-white">
                                                            Version {{ $bStep['version'] }}
                                                        </span>
                                                        @if($bStep['is_current'])
                                                            <span class="px-1.5 py-0.5 rounded bg-[var(--theme-color)] text-gray-950 text-[9px] font-black uppercase tracking-wider">
                                                                Aktuell
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[11px] font-bold text-gray-700 dark:text-gray-300 truncate">
                                                        {{ $bStep['actor_label'] }}
                                                    </div>
                                                    @if(!empty($bStep['saved_at_label']))
                                                        <div class="text-[10px] font-mono text-gray-500 dark:text-gray-400 truncate">
                                                            {{ $bStep['saved_at_label'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>

                                    {{-- In-Window Lightbox für den Admin Blueprint-Vergleich --}}
                                    <div x-cloak
                                         data-admin-blueprint-lightbox
                                         :class="lbOpen ? 'flex' : 'hidden'"
                                         @click.self="closeBlueprint()"
                                         class="hidden fixed inset-0 z-[165] items-center justify-center bg-black/75 backdrop-blur-sm p-4">
                                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden flex flex-col max-h-[90vh]">
                                            <div class="px-5 py-3.5 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex flex-wrap items-center justify-between gap-2">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <template x-for="(st, idx) in timeline" :key="'adm-bp-' + st.version + '-' + idx">
                                                        <button type="button"
                                                                @click="lbIdx = idx; if (!st.has_back) lbSide = 'front';"
                                                                :class="lbIdx === idx
                                                                    ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)]'
                                                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800'"
                                                                class="px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                                            <span x-text="'Version ' + st.version + (st.is_current ? ' · Aktuell' : '')"></span>
                                                        </button>
                                                    </template>
                                                </div>

                                                <div class="flex items-center gap-2 ml-auto">
                                                    <div :class="activeEntry().has_back ? 'inline-flex' : 'hidden'"
                                                         class="p-0.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                                        <button type="button"
                                                                @click="lbSide = 'front'"
                                                                :class="lbSide === 'front' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-600 dark:text-gray-400'"
                                                                class="px-2 py-1 rounded-md text-[10px] font-black uppercase cursor-pointer">
                                                            Vorderseite
                                                        </button>
                                                        <button type="button"
                                                                @click="lbSide = 'back'"
                                                                :class="lbSide === 'back' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-600 dark:text-gray-400'"
                                                                class="px-2 py-1 rounded-md text-[10px] font-black uppercase cursor-pointer">
                                                            Rückseite
                                                        </button>
                                                    </div>

                                                    <button type="button"
                                                            @click="closeBlueprint()"
                                                            class="w-8 h-8 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-gray-900 dark:hover:text-white flex items-center justify-center cursor-pointer">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="p-6 bg-[#FAF8F5] dark:bg-gray-950 flex-1 overflow-auto flex items-center justify-center min-h-[300px]">
                                                <template x-if="(lbSide === 'back' && activeEntry().back_url) ? activeEntry().back_url : activeEntry().front_url">
                                                    <img :src="(lbSide === 'back' && activeEntry().back_url) ? activeEntry().back_url : activeEntry().front_url"
                                                         alt="Blueprint"
                                                         class="max-w-full max-h-[62vh] object-contain rounded-xl">
                                                </template>
                                                <template x-if="!((lbSide === 'back' && activeEntry().back_url) ? activeEntry().back_url : activeEntry().front_url)">
                                                    <span class="text-xs font-bold text-gray-500">Kein Blueprint-Vorschaubild für diese Version hinterlegt.</span>
                                                </template>
                                            </div>

                                            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex flex-wrap items-center justify-between gap-2 text-xs">
                                                <span class="font-bold text-gray-900 dark:text-white" x-text="'Version ' + (activeEntry().version || 1) + ' · ' + (activeEntry().actor_label || '')"></span>
                                                <span class="font-mono text-[11px] text-gray-500 dark:text-gray-400" x-text="activeEntry().saved_at_label || ''"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- LIEFERANTEN-NACHBESTELLUNG (NUR BEI FEHLENDEM BESTAND) --}}
                    @if($previewItem && $previewItem->requiresSupplierProcurement())
                        @php
                            $stepSupOrdered = $previewItem->isSupplierOrderPlaced();
                            $stepMissingQty = $previewItem->getMissingSupplierQuantity();
                            $stepReorderUrl = $previewItem->getSupplierReorderUrl();
                            $stepSupName = $previewItem->getSupplierName();
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-2xl border bg-gray-50 dark:bg-gray-950/80 border-gray-200 dark:border-gray-800">
                            <div class="min-w-0">
                                <div class="text-[10px] font-black uppercase tracking-widest {{ $stepSupOrdered ? 'text-[var(--theme-color)]' : 'text-gray-900 dark:text-white' }}">
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
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors cursor-pointer {{ $stepSupOrdered ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-40)]' : 'bg-[var(--theme-color)] text-gray-950' }}">
                                    <span>{{ $stepSupOrdered ? 'Bestellt ✓' : 'Als bestellt markieren' }}</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- KONFIGURATOR-VORGABEN NUR ANZEIGEN, FALLS TATSÄCHLICH GEBUCHT / AUSGEFÜLLT --}}
                    @if($previewItem && $hasApplicableItemChecklist)
                        <div class="space-y-2 pt-1">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                Konfigurator-Vorgaben für {{ $previewItem->product_name }}
                            </div>

                            @if($previewChecklist['notes']['has_note'])
                                <div wire:click="toggleItemChecklistTask('{{ $previewItem->id }}', 'notes')"
                                     class="flex items-center justify-between gap-3 p-3.5 rounded-2xl border bg-white dark:bg-gray-900 transition-all cursor-pointer {{ $previewChecklist['notes']['checked'] ? 'border-[var(--theme-color-50)]' : 'border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }}">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 {{ $previewChecklist['notes']['checked'] ? 'bg-[var(--theme-color)] border-[var(--theme-color)] text-gray-950' : 'border-[var(--theme-color)] text-transparent' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-[10px] font-black uppercase tracking-wider text-[var(--theme-color)]">Kundenwunsch</div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white break-words">„{{ $previewChecklist['notes']['text'] }}“</div>
                                        </div>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-black uppercase tracking-wider {{ $previewChecklist['notes']['checked'] ? 'text-[var(--theme-color)]' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $previewChecklist['notes']['checked'] ? 'Erledigt ✓' : 'Offen' }}
                                    </span>
                                </div>
                            @endif

                            @if($previewChecklist['gift_wrap']['selected'])
                                <div wire:click="toggleItemChecklistTask('{{ $previewItem->id }}', 'gift_wrap')"
                                     class="flex items-center justify-between gap-3 p-3.5 rounded-2xl border bg-white dark:bg-gray-900 transition-all cursor-pointer {{ $previewChecklist['gift_wrap']['checked'] ? 'border-[var(--theme-color-50)]' : 'border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }}">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 {{ $previewChecklist['gift_wrap']['checked'] ? 'bg-[var(--theme-color)] border-[var(--theme-color)] text-gray-950' : 'border-[var(--theme-color)] text-transparent' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-[10px] font-black uppercase tracking-wider text-[var(--theme-color)]">Geschenkpapier</div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white">{{ $previewChecklist['gift_wrap']['instruction'] }}</div>
                                        </div>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-black uppercase tracking-wider {{ $previewChecklist['gift_wrap']['checked'] ? 'text-[var(--theme-color)]' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $previewChecklist['gift_wrap']['checked'] ? 'Erledigt ✓' : ($previewChecklist['gift_wrap']['mode'] === 'loose' ? 'Beilegen' : 'Einpacken') }}
                                    </span>
                                </div>
                            @endif

                            @if($previewChecklist['gift_box']['selected'])
                                <div wire:click="toggleItemChecklistTask('{{ $previewItem->id }}', 'gift_box')"
                                     class="flex items-center justify-between gap-3 p-3.5 rounded-2xl border bg-white dark:bg-gray-900 transition-all cursor-pointer {{ $previewChecklist['gift_box']['checked'] ? 'border-[var(--theme-color-50)]' : 'border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }}">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 {{ $previewChecklist['gift_box']['checked'] ? 'bg-[var(--theme-color)] border-[var(--theme-color)] text-gray-950' : 'border-[var(--theme-color)] text-transparent' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-[10px] font-black uppercase tracking-wider text-[var(--theme-color)]">Edle Schmuckbox</div>
                                            <div class="text-xs font-bold text-gray-900 dark:text-white">{{ $previewChecklist['gift_box']['instruction'] }}</div>
                                        </div>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-black uppercase tracking-wider {{ $previewChecklist['gift_box']['checked'] ? 'text-[var(--theme-color)]' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $previewChecklist['gift_box']['checked'] ? 'Erledigt ✓' : 'In Box legen' }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Haupt-Weiter-Button für Schritt 1 (2-Seiten-Schutznetz: führt auf Vorderseite zuerst zur Rückseite 2/2) --}}
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        @if($previewItem && $previewNeedsBackSwitch)
                            <button type="button"
                                    data-step1-switch-to-back-cta
                                    @click="switchStepOneTab('cockpit')"
                                    wire:click="switchLaserCockpitSide('back')"
                                    class="flex-1 flex items-center justify-center gap-2.5 px-6 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs sm:text-sm font-black uppercase tracking-widest shadow-md transition-all cursor-pointer">
                                <span>Zur Rückseite wechseln (Seite 2/2)</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                            <button type="button"
                                    data-step1-skip-back-guard-btn
                                    x-on:click="if (confirm('Achtung: Für diesen Artikel wurde eine Rückseite konfiguriert. Ist die Rückseite wirklich schon fertig gelasert?')) { $wire.{{ $openOrderItemsCount > 1 ? "completeCurrentItemAndAdvance('{$previewItem->id}', true)" : 'completeStepOneAndProceedToShipping(true)' }} }"
                                    class="px-4 py-4 rounded-2xl bg-gray-100 dark:bg-gray-800 hover:border-[var(--theme-color)] text-gray-600 dark:text-gray-300 text-[10px] font-black uppercase tracking-wider border border-gray-200 dark:border-gray-700 transition-all cursor-pointer shrink-0">
                                {{ $openOrderItemsCount > 1 ? 'Nächster Artikel' : 'Direkt zur Verpackung' }}
                            </button>
                        @elseif($previewItem && $openOrderItemsCount > 1)
                            <button type="button"
                                    wire:click="completeCurrentItemAndAdvance('{{ $previewItem->id }}')"
                                    class="flex-1 flex items-center justify-center gap-2.5 px-5 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs font-black uppercase tracking-widest shadow-md transition-all cursor-pointer">
                                <span>Nächster Artikel</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                            <button type="button"
                                    wire:click="completeStepOneAndProceedToShipping"
                                    class="px-5 py-4 rounded-2xl bg-gray-100 dark:bg-gray-800 hover:border-[var(--theme-color)] text-gray-800 dark:text-gray-200 text-xs font-black uppercase tracking-wider border border-gray-200 dark:border-gray-700 transition-all cursor-pointer shrink-0">
                                Weiter zur Verpackung
                            </button>
                        @else
                            <button type="button"
                                    wire:click="completeStepOneAndProceedToShipping"
                                    class="w-full flex items-center justify-center gap-2.5 px-6 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs sm:text-sm font-black uppercase tracking-widest shadow-md transition-all cursor-pointer">
                                <span>Weiter zur Verpackung</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        @endif
                    </div>
                </div>

            {{-- ================================================================= --}}
            {{-- SCHRITT 2: VERPACKUNG & VERSAND (Cleanes Schwarz-Gold-Design) --}}
            {{-- ================================================================= --}}
            @elseif($currentOrderStep === 2)
                @php
                    $packingPlan = $model->getPackingStationPlan();
                @endphp
                <div class="bg-white dark:bg-gray-900/95 border border-gray-200 dark:border-gray-800 rounded-[2rem] sm:rounded-[2.5rem] p-5 sm:p-8 shadow-xl space-y-6">

                    {{-- Kopfzeile (ohne Subtext) --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-gray-200 dark:border-gray-800">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl bg-[var(--theme-color)] text-gray-950 flex items-center justify-center text-sm font-black shadow-sm shrink-0">
                                2
                            </span>
                            <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-gray-900 dark:text-white">
                                Schritt 2 · Verpackung &amp; Versand
                            </h2>
                        </div>
                        <span class="px-3 py-1 rounded-xl bg-gray-100 dark:bg-gray-950 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-800 text-[10px] font-mono font-black">
                            {{ $packingPlan['dhl_class'] }} · {{ number_format($packingPlan['total_weight_kg'], 2, ',', '.') }} kg
                        </span>
                    </div>

                    {{-- 1. BENÖTIGTE KARTONS & ZUORDNUNG (Auf einen Blick klar) --}}
                    <div class="space-y-2.5">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                            1. Benötigte Kartons &amp; Verpackungs-Zuordnung
                        </div>

                        @if($packingPlan['outer_package_count'] > 1)
                            <div class="px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2 text-xs">
                                <span class="font-bold text-gray-900 dark:text-white">{{ $packingPlan['outer_package_count'] }}× Versandpaket</span>
                                <span class="font-medium text-gray-500 dark:text-gray-400">{{ $packingPlan['outer_box_hint'] }}</span>
                            </div>
                        @endif

                        @if(!empty($packingPlan['cartons']))
                            <div class="space-y-2">
                                @foreach($packingPlan['cartons'] as $carton)
                                    <div class="px-4 py-3.5 rounded-2xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="px-2.5 py-1 rounded-lg bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)] font-mono text-xs font-black shrink-0">
                                                {{ $carton['count'] }}×
                                            </span>
                                            <div class="min-w-0">
                                                <div class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white truncate">
                                                    {{ $carton['label'] }}
                                                </div>
                                                @if(!empty($carton['dimensions']))
                                                    <div class="text-[11px] font-mono text-gray-500 dark:text-gray-400">
                                                        {{ $carton['dimensions'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="text-xs font-bold text-gray-700 dark:text-gray-300 sm:text-right">
                                            {{ implode(', ', $carton['products']) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="px-4 py-3 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs font-medium text-gray-500">
                                Digitaler Auftrag (kein physischer Versandkarton benötigt)
                            </div>
                        @endif
                    </div>

                    {{-- 2. CHRONOLOGISCHE PACK-CHECKLISTE (Cleanes Schwarz-Gold ohne bunte Boxen, nur zutreffende Punkte) --}}
                    @php
                        $packingChecklistItems = $mfgOverview['packing_checklist_items'] ?? $model->resolvePackingChecklistItems();
                    @endphp
                    @if(!empty($packingChecklistItems))
                        <div class="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-800">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                2. Pack-Checkliste
                            </div>

                            @foreach($packingChecklistItems as $checkItem)
                                <div wire:click="toggleOrderPackingCheck('{{ $checkItem['key'] }}')"
                                     class="flex items-center justify-between gap-3 px-4 py-3.5 rounded-2xl bg-white dark:bg-gray-950 border transition-all cursor-pointer {{ $checkItem['checked']
                                        ? 'border-[var(--theme-color-50)]'
                                        : 'border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }}">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 {{ $checkItem['checked']
                                            ? 'bg-[var(--theme-color)] border-[var(--theme-color)] text-gray-950'
                                            : 'border-[var(--theme-color)] text-transparent' }}">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <span class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">
                                            {{ $checkItem['label'] }}
                                        </span>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-black uppercase tracking-wider {{ $checkItem['checked'] ? 'text-[var(--theme-color)]' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ $checkItem['checked'] ? 'Erledigt ✓' : 'Offen' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Bereits erzeugte Versandlabels (DHL & Großbrief) zum direkten 1-Klick-Nachdrucken --}}
                    @if($model->shipments->isNotEmpty())
                        <div class="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-800">
                            <div class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">
                                Erstellte Versandlabels ({{ $model->shipments->count() }})
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach($model->shipments as $shipment)
                                    @php
                                        $hasLabelPdf = !empty($shipment->shipping_label_path);
                                        $isBrief = ($shipment->carrier ?? '') === 'deutsche_post_brief';
                                    @endphp
                                    <div class="flex items-center justify-between gap-2 p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                                        <div class="min-w-0">
                                            <div class="text-[9px] font-black uppercase tracking-wider text-gray-400">
                                                {{ $isBrief ? 'Deutsche Post Großbrief' : 'DHL Paket ' . $loop->iteration }}
                                            </div>
                                            <div class="text-xs font-mono font-bold text-[var(--theme-color)] truncate">{{ $shipment->tracking_number }}</div>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button type="button"
                                                    wire:click="printDhlLabelViaFunkenwerk('{{ $shipment->id }}')"
                                                    class="px-3 py-1.5 rounded-lg bg-[var(--theme-color)] text-gray-950 text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                                MUNBYN Druck
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

                    {{-- 3. HAUPTAKTIONEN --}}
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if(!$model->isOnlyDigital())
                            <button type="button"
                                    wire:click="openDhlModal('{{ $model->id }}')"
                                    class="flex items-center justify-center gap-2.5 px-5 py-4 rounded-2xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-xs font-black uppercase tracking-wider shadow-md transition-all cursor-pointer">
                                <span class="w-5 h-5 rounded-full bg-gray-950 text-[var(--theme-color)] flex items-center justify-center text-[10px] font-black">1</span>
                                <span>{{ $model->isPostalVoucherOnly() ? 'Großbrief-Label erstellen & drucken' : 'Versandlabel erstellen & drucken' }}</span>
                            </button>
                        @endif
                        <button type="button"
                                wire:click="completeStepTwoShipped(true)"
                                class="flex items-center justify-center gap-2.5 px-5 py-4 rounded-2xl bg-gray-900 dark:bg-white hover:opacity-90 text-white dark:text-gray-950 text-xs font-black uppercase tracking-wider shadow-md transition-all cursor-pointer">
                            <span class="w-5 h-5 rounded-full bg-[var(--theme-color)] text-gray-950 flex items-center justify-center text-[10px] font-black">2</span>
                            <span>Fertig verpackt &amp; versendet</span>
                        </button>
                    </div>
                </div>

            {{-- ================================================================= --}}
            {{-- SCHRITT 3: BESTELLUNG ABGESCHLOSSEN --}}
            {{-- ================================================================= --}}
            @else
                <div class="bg-white dark:bg-gray-900/95 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] p-8 sm:p-10 shadow-xl text-center flex flex-col items-center space-y-5">
                    <div class="w-16 h-16 rounded-2xl bg-[var(--theme-color-15)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center shadow-sm">
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
                            <span class="font-black text-[var(--theme-color)]">
                                {{ (int) $mfgOverview['total_items'] === 1 ? '✓ 1 Artikel erledigt' : ('✓ Alle ' . $mfgOverview['total_items'] . ' Artikel erledigt') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-500">2. Verpackung &amp; Versand:</span>
                            <span class="font-black text-[var(--theme-color)]">✓ Versendet{{ $model->tracking_number ? ' (' . $model->tracking_number . ')' : '' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-500">3. Auftragsstatus:</span>
                            <span class="font-black text-[var(--theme-color)]">
                                {{ $model->status === 'completed' ? '✓ Abgeschlossen' : 'Bereit zum Abschluss' }}
                            </span>
                        </div>
                    </div>

                    <div class="w-full max-w-md flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
                        @if($model->status !== 'completed')
                            <button type="button"
                                    wire:click="completeStepThreeOrder"
                                    class="flex-1 flex items-center justify-center gap-2 px-6 py-4 rounded-2xl bg-gray-900 dark:bg-white hover:opacity-90 text-white dark:text-gray-950 text-xs font-black uppercase tracking-widest shadow-md transition-all cursor-pointer">
                                <svg class="w-4 h-4 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
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
        </div>
    </div>
@else
    {{-- ANGEBOTS-ANSICHT (Quote Context) --}}
    @include('livewire.shop.order.order-offer-detail-partials.left')
    @include('livewire.shop.order.order-offer-detail-partials.right')
@endif
