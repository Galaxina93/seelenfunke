@php
    $headerMfgOverview = $this->selectedOrder->getManufacturingOverview();
    $isPendingStart = $this->selectedOrder->status === 'pending';
    $activeTabStep = (int) ($this->activeOrderStep ?? 1);
    $step1Done = (bool) ($headerMfgOverview['all_step_one_done'] ?? false) || in_array($this->selectedOrder->status, ['shipped', 'completed'], true);
    $step2Done = in_array($this->selectedOrder->status, ['shipped', 'completed'], true);
    $step3Done = $this->selectedOrder->status === 'completed';
    $showStepPipeline = !$isPendingStart && !in_array($this->selectedOrder->status, ['cancelled', 'refunded'], true);

    $nextInQueue = $this->nextQueueOrder;
    $hasDiffShipping = $this->selectedOrder->hasDifferentShippingAddress();

    $orderStatusDotClass = match ($this->selectedOrder->status) {
        'pending' => 'bg-amber-500',
        'processing' => 'bg-[var(--theme-color)]',
        'shipped' => 'bg-sky-500',
        'completed' => 'bg-emerald-500',
        'cancelled', 'refunded', 'failed' => 'bg-red-500',
        default => 'bg-gray-400',
    };

    $paymentStatusDotClass = match ($this->selectedOrder->payment_status) {
        'paid' => 'bg-emerald-500',
        'refunded' => 'bg-gray-400 dark:bg-gray-500',
        default => 'bg-amber-500',
    };
@endphp

<div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200/80 dark:border-gray-800 px-4 sm:px-6 py-3.5 sm:py-4 shrink-0 z-30 space-y-3 rounded-t-[2.5rem]">

    {{-- EBENE 1: IDENTITÄT LINKS · BEIDE STATUS-DROPDOWNS & SCHLIESSEN-BUTTON RECHTS --}}
    <div class="flex flex-wrap sm:flex-nowrap items-center justify-between gap-x-3 gap-y-2.5">

        {{-- Links: Bestellnummer + Kurzbezug (Kunde, Datum, Artikelanzahl) --}}
        <div class="order-1 min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white tracking-tight leading-none">
                    #{{ $this->selectedOrder->order_number }}
                </h1>

                @if($this->selectedOrder->is_express)
                    <x-shop.express-info iconClass="w-3.5 h-3.5" class="text-gray-800 dark:text-gray-100 border border-primary/40 text-[9px] font-black px-2 py-0.5 rounded-md uppercase tracking-widest shadow-2xs" />
                @endif
            </div>
            <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 mt-1 truncate">
                {{ $this->selectedOrder->customer_name }} · {{ $this->selectedOrder->created_at->format('d.m.Y H:i') }} Uhr · {{ $this->selectedOrder->items->count() }} Artikel
            </p>
        </div>

        {{-- Rechts (auf Mobile sauber als 2-Spalten-Grid): Bestellstatus & Zahlungsstatus (exakt h-9 + Custom-Chevron) --}}
        <div class="order-3 sm:order-2 w-full sm:w-auto grid grid-cols-2 sm:flex items-center gap-2 shrink-0">
            {{-- Bestellstatus --}}
            <div class="relative flex items-center min-w-0">
                <span class="pointer-events-none absolute left-3 w-2 h-2 rounded-full {{ $orderStatusDotClass }}"></span>
                <select wire:change="updateStatus('{{ $this->selectedOrder->id }}', $event.target.value)"
                        title="Bestellstatus"
                        aria-label="Bestellstatus"
                        class="w-full sm:w-auto h-9 pl-7 pr-8 rounded-xl bg-gray-50 dark:bg-gray-950/90 border border-gray-200/80 dark:border-gray-800 hover:border-[var(--theme-color-50)] focus:border-[var(--theme-color)] text-gray-900 dark:text-white text-[11px] font-bold tracking-wide appearance-none cursor-pointer outline-none transition-colors truncate">
                    <option value="pending" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" @selected($this->selectedOrder->status == 'pending')>Wartend</option>
                    <option value="processing" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" @selected($this->selectedOrder->status == 'processing')>In Bearbeitung</option>
                    <option value="shipped" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" @selected($this->selectedOrder->status == 'shipped')>Versendet</option>
                    <option value="completed" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" @selected($this->selectedOrder->status == 'completed')>Abgeschlossen</option>
                    <option value="cancelled" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" @selected($this->selectedOrder->status == 'cancelled')>Storniert</option>
                </select>
                <span class="pointer-events-none absolute right-2.5 text-gray-400 dark:text-gray-500 flex items-center">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </span>
            </div>

            {{-- Zahlungsstatus --}}
            <div class="relative flex items-center min-w-0">
                <span class="pointer-events-none absolute left-3 w-2 h-2 rounded-full {{ $paymentStatusDotClass }}"></span>
                <select wire:model="payment_status"
                        wire:change="saveStatus"
                        title="Zahlungsstatus"
                        aria-label="Zahlungsstatus"
                        class="w-full sm:w-auto h-9 pl-7 pr-8 rounded-xl bg-gray-50 dark:bg-gray-950/90 border border-gray-200/80 dark:border-gray-800 hover:border-[var(--theme-color-50)] focus:border-[var(--theme-color)] text-gray-900 dark:text-white text-[11px] font-bold tracking-wide appearance-none cursor-pointer outline-none transition-colors truncate">
                    <option value="unpaid" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" @selected(!in_array($this->selectedOrder->payment_status, ['paid', 'refunded'], true))>Zahlung: Offen</option>
                    <option value="paid" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" @selected($this->selectedOrder->payment_status == 'paid')>Zahlung: Bezahlt</option>
                    <option value="refunded" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" @selected($this->selectedOrder->payment_status == 'refunded')>Zahlung: Erstattet</option>
                </select>
                <span class="pointer-events-none absolute right-2.5 text-gray-400 dark:text-gray-500 flex items-center">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </span>
            </div>
        </div>

        {{-- Klar getrennter, ruhiger Schließen-Button (×) --}}
        <div class="order-2 sm:order-3 flex items-center gap-2.5 shrink-0">
            <div class="hidden sm:block h-6 w-px bg-gray-200/80 dark:bg-gray-800"></div>
            <button type="button"
                    wire:click="closeDetail"
                    title="Zurück zur Übersicht"
                    aria-label="Bestelldetails schließen"
                    class="w-9 h-9 rounded-xl bg-gray-50 dark:bg-gray-950/90 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200/80 dark:border-gray-800 hover:border-[var(--theme-color-50)] text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white flex items-center justify-center transition-colors cursor-pointer shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- EBENE 2: HARMONISCHE SEGMENTED-INFO-TOOLBAR · WORKFLOW-PIPELINE & KONTEXT-AKTIONEN --}}
    <div class="pt-3 border-t border-gray-200/80 dark:border-gray-800 flex flex-col xl:flex-row xl:items-center justify-between gap-2.5">

        {{-- Links: Einheitliche 3er Segmented-Pill-Leiste (Kosten | Kunde | Adresse) --}}
        <div role="toolbar"
             aria-label="Bestelldetails Schnellzugriff"
             class="grid grid-cols-3 sm:inline-grid sm:grid-flow-col sm:auto-cols-auto items-center gap-1 h-9 p-0.5 rounded-xl bg-gray-100/80 dark:bg-gray-950/90 border border-gray-200/80 dark:border-gray-800 w-full sm:w-auto shrink-0">

            {{-- 1. Kosten --}}
            <button type="button"
                    @click="openHeaderModalClient('costs')"
                    title="Kostenübersicht"
                    aria-label="Kostenübersicht"
                    :class="headerModal === 'costs'
                        ? 'bg-[var(--theme-color-15)] text-gray-900 dark:text-white border-[var(--theme-color)] shadow-2xs'
                        : 'bg-transparent text-gray-600 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-white hover:bg-white/80 dark:hover:bg-gray-900/70'"
                    class="h-full px-2 sm:px-3.5 rounded-[9px] border inline-flex items-center justify-center gap-1.5 text-[10px] sm:text-[11px] font-bold tracking-wide transition-all cursor-pointer">
                <svg class="w-3.5 h-3.5 shrink-0 transition-colors"
                     :class="headerModal === 'costs' ? 'text-[var(--theme-color)]' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 7.756a4.5 4.5 0 100 8.488M7.5 10.5h5.25m-5.25 3h5.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="truncate">Kosten</span>
            </button>

            {{-- 3. Kunde --}}
            <button type="button"
                    @click="openHeaderModalClient('customer')"
                    title="Kunden Infos"
                    aria-label="Kunden Infos"
                    :class="headerModal === 'customer'
                        ? 'bg-[var(--theme-color-15)] text-gray-900 dark:text-white border-[var(--theme-color)] shadow-2xs'
                        : 'bg-transparent text-gray-600 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-white hover:bg-white/80 dark:hover:bg-gray-900/70'"
                    class="h-full px-2 sm:px-3.5 rounded-[9px] border inline-flex items-center justify-center gap-1.5 text-[10px] sm:text-[11px] font-bold tracking-wide transition-all cursor-pointer">
                <svg class="w-3.5 h-3.5 shrink-0 transition-colors"
                     :class="headerModal === 'customer' ? 'text-[var(--theme-color)]' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span class="truncate">Kunde</span>
            </button>

            {{-- 4. Adresse --}}
            <button type="button"
                    @click="openHeaderModalClient('shipping')"
                    title="Lieferadresse{{ $hasDiffShipping ? ' (Abweichend)' : '' }}"
                    aria-label="Lieferadresse"
                    :class="headerModal === 'shipping'
                        ? 'bg-[var(--theme-color-15)] text-gray-900 dark:text-white border-[var(--theme-color)] shadow-2xs'
                        : ({{ $hasDiffShipping ? 'true' : 'false' }}
                            ? 'bg-white/80 dark:bg-gray-900/70 text-gray-900 dark:text-white border-[var(--theme-color-40)] hover:border-[var(--theme-color)]'
                            : 'bg-transparent text-gray-600 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-white hover:bg-white/80 dark:hover:bg-gray-900/70')"
                    class="h-full px-2 sm:px-3.5 rounded-[9px] border inline-flex items-center justify-center gap-1.5 text-[10px] sm:text-[11px] font-bold tracking-wide transition-all cursor-pointer">
                <svg class="w-3.5 h-3.5 shrink-0 transition-colors"
                     :class="(headerModal === 'shipping' || {{ $hasDiffShipping ? 'true' : 'false' }}) ? 'text-[var(--theme-color)]' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="truncate">Adresse</span>
                @if($hasDiffShipping)
                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)] shrink-0" title="Abweichende Lieferadresse"></span>
                @endif
            </button>
        </div>

        {{-- Rechts: 3-Schritte-Pipeline (falls aktiv) + ruhige h-9 Kontext-Aktionen --}}
        <div class="flex flex-wrap sm:flex-nowrap items-center justify-between xl:justify-end gap-2 min-w-0">

            @if($showStepPipeline)
                <div class="flex items-center gap-1 h-9 p-0.5 rounded-xl bg-gray-100/80 dark:bg-gray-950/90 border border-gray-200/80 dark:border-gray-800 w-full sm:w-auto overflow-x-auto no-scrollbar shrink-0">
                    {{-- 1: In Bearbeitung --}}
                    <button type="button"
                            wire:click="setOrderStep(1)"
                            class="h-full flex items-center gap-1.5 px-2.5 sm:px-3 rounded-[9px] border text-[10px] font-bold tracking-wide transition-all cursor-pointer shrink-0 {{ $activeTabStep === 1
                                ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-2xs'
                                : ($step1Done
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/15'
                                    : 'bg-transparent text-gray-600 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-white') }}">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-black {{ $activeTabStep === 1 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step1Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                            {{ $step1Done ? '✓' : '1' }}
                        </span>
                        <span>In Bearbeitung ({{ $headerMfgOverview['completed_items'] }}/{{ $headerMfgOverview['total_items'] }})</span>
                    </button>

                    <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>

                    {{-- 2: Versand --}}
                    <button type="button"
                            wire:click="setOrderStep(2)"
                            class="h-full flex items-center gap-1.5 px-2.5 sm:px-3 rounded-[9px] border text-[10px] font-bold tracking-wide transition-all cursor-pointer shrink-0 {{ $activeTabStep === 2
                                ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-2xs'
                                : ($step2Done
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/15'
                                    : 'bg-transparent text-gray-600 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-white') }}">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-black {{ $activeTabStep === 2 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step2Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                            {{ $step2Done ? '✓' : '2' }}
                        </span>
                        <span>Versand</span>
                    </button>

                    <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>

                    {{-- 3: Abgeschlossen --}}
                    <button type="button"
                            wire:click="setOrderStep(3)"
                            class="h-full flex items-center gap-1.5 px-2.5 sm:px-3 rounded-[9px] border text-[10px] font-bold tracking-wide transition-all cursor-pointer shrink-0 {{ $activeTabStep === 3
                                ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-2xs'
                                : ($step3Done
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/15'
                                    : 'bg-transparent text-gray-600 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-white') }}">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-black {{ $activeTabStep === 3 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step3Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                            {{ $step3Done ? '✓' : '3' }}
                        </span>
                        <span>Abgeschlossen</span>
                    </button>
                </div>
            @endif

            @if(!$this->selectedOrder->isOnlyDigital() || $this->selectedOrder->canBePermanentlyDeleted() || $nextInQueue)
                <div class="flex items-center gap-1.5 ml-auto sm:ml-0 shrink-0">
                    @if(!$this->selectedOrder->isOnlyDigital())
                        <button type="button"
                                wire:click="openDhlModal('{{ $this->selectedOrder->id }}')"
                                title="DHL Label-Assistent öffnen"
                                class="h-9 px-3 rounded-xl bg-gray-50 dark:bg-gray-950/90 hover:bg-[var(--theme-color-15)] text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white border border-gray-200/80 dark:border-gray-800 hover:border-[var(--theme-color)] inline-flex items-center justify-center gap-1.5 text-[11px] font-bold tracking-wide transition-all cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <span>DHL</span>
                        </button>
                    @endif

                    @if($this->selectedOrder->canBePermanentlyDeleted())
                        <button type="button"
                                wire:click="delete('{{ $this->selectedOrder->id }}')"
                                wire:confirm="Unbezahlte Bestellung #{{ $this->selectedOrder->order_number }} unwiderruflich löschen?"
                                title="Bestellung unwiderruflich löschen"
                                class="h-9 px-2.5 rounded-xl bg-red-50/70 dark:bg-red-950/30 hover:bg-red-600 text-red-600 dark:text-red-400 hover:text-white border border-red-200/80 dark:border-red-900/50 inline-flex items-center justify-center gap-1.5 text-[11px] font-bold tracking-wide transition-all cursor-pointer">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            <span class="hidden sm:inline">Löschen</span>
                            <span class="sr-only">Bestellung unwiderruflich löschen</span>
                        </button>
                    @endif

                    @if($nextInQueue)
                        <button type="button"
                                wire:click="openDetail('{{ $nextInQueue['id'] }}')"
                                title="Direkt weiter zu #{{ $nextInQueue['order_number'] }}{{ !empty($nextInQueue['product_name']) ? ' (' . $nextInQueue['product_name'] . ')' : '' }}"
                                class="h-9 px-3 rounded-xl border inline-flex items-center justify-center gap-1.5 text-[11px] font-bold tracking-wide transition-all cursor-pointer {{ !empty($nextInQueue['is_same_product'])
                                    ? 'bg-[var(--theme-color-15)] text-gray-900 dark:text-white border-[var(--theme-color-50)] hover:bg-[var(--theme-color)] hover:text-gray-950'
                                    : 'bg-gray-50 dark:bg-gray-950/90 text-gray-700 dark:text-gray-300 border-gray-200/80 dark:border-gray-800 hover:border-[var(--theme-color-50)] hover:text-gray-900 dark:hover:text-white' }}">
                            <span>Weiter</span>
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>

</div>
