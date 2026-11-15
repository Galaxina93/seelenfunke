<div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200 dark:border-gray-800 px-4 sm:px-6 py-3.5 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-3.5 shrink-0 z-30">

    {{-- LINKS: Nur Zurück-Pfeil + Auftragsnummer + Zahlstatus-Pill --}}
    <div class="flex items-center gap-3 sm:gap-4 shrink-0">
        <button type="button"
                wire:click="closeDetail"
                title="Zurück zur Übersicht"
                aria-label="Zurück zur Übersicht"
                class="group text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer shrink-0">
            <div class="w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center group-hover:bg-[var(--theme-color)] group-hover:border-[var(--theme-color)] group-hover:text-gray-900 transition-all duration-300 shadow-sm">
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </div>
        </button>

        <div class="h-7 w-px bg-gray-200 dark:bg-gray-800"></div>

        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                    #{{ $this->selectedOrder->order_number }}
                </h1>

                @if($this->selectedOrder->is_express)
                    <x-shop.express-info iconClass="w-3.5 h-3.5" class="text-gray-800 dark:text-gray-100 border border-primary/40 text-[9px] font-black px-2 py-0.5 rounded-md uppercase tracking-widest shadow-2xs" />
                @endif

                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider border {{ $this->selectedOrder->payment_status === 'paid'
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'
                    : ($this->selectedOrder->payment_status === 'refunded'
                        ? 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-700'
                        : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30') }}">
                    <span>{{ $this->selectedOrder->payment_status === 'paid' ? '✓ Bezahlt' : ($this->selectedOrder->payment_status === 'refunded' ? 'Erstattet' : 'Zahlung offen') }}</span>
                </span>
            </div>
            <p class="text-[10px] font-medium text-gray-500 dark:text-gray-400 mt-0.5">
                {{ $this->selectedOrder->customer_name }} · {{ $this->selectedOrder->created_at->format('d.m.Y H:i') }} Uhr · {{ $this->selectedOrder->items->count() }} Artikel
            </p>
        </div>
    </div>

    {{-- MITTE: KOMPAKTE PIPELINE OHNE WORT "SCHRITT" --}}
    @php
        $headerMfgOverview = $this->selectedOrder->getManufacturingOverview();
        $isPendingStart = $this->selectedOrder->status === 'pending';
        $activeTabStep = (int) ($this->activeOrderStep ?? 1);
        $step1Done = (bool) ($headerMfgOverview['all_step_one_done'] ?? false) || in_array($this->selectedOrder->status, ['shipped', 'completed'], true);
        $step2Done = in_array($this->selectedOrder->status, ['shipped', 'completed'], true);
        $step3Done = $this->selectedOrder->status === 'completed';
    @endphp
    @if(!$isPendingStart && !in_array($this->selectedOrder->status, ['cancelled', 'refunded'], true))
        <div class="flex items-center gap-1 bg-gray-100/90 dark:bg-gray-950/90 p-1 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner w-full xl:w-auto overflow-x-auto justify-center shrink-0">
            {{-- 1: In Bearbeitung --}}
            <button type="button"
                    wire:click="setOrderStep(1)"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer shrink-0 {{ $activeTabStep === 1
                        ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm'
                        : ($step1Done
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/20'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white') }}">
                <span class="w-4.5 h-4.5 rounded-full flex items-center justify-center text-[9px] font-black {{ $activeTabStep === 1 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step1Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                    {{ $step1Done ? '✓' : '1' }}
                </span>
                <span>In Bearbeitung ({{ $headerMfgOverview['completed_items'] }}/{{ $headerMfgOverview['total_items'] }})</span>
            </button>

            <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>

            {{-- 2: Versand --}}
            <button type="button"
                    wire:click="setOrderStep(2)"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer shrink-0 {{ $activeTabStep === 2
                        ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm'
                        : ($step2Done
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/20'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white') }}">
                <span class="w-4.5 h-4.5 rounded-full flex items-center justify-center text-[9px] font-black {{ $activeTabStep === 2 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step2Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                    {{ $step2Done ? '✓' : '2' }}
                </span>
                <span>Versand</span>
            </button>

            <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>

            {{-- 3: Abgeschlossen --}}
            <button type="button"
                    wire:click="setOrderStep(3)"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer shrink-0 {{ $activeTabStep === 3
                        ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm'
                        : ($step3Done
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/20'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white') }}">
                <span class="w-4.5 h-4.5 rounded-full flex items-center justify-center text-[9px] font-black {{ $activeTabStep === 3 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step3Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                    {{ $step3Done ? '✓' : '3' }}
                </span>
                <span>Abgeschlossen</span>
            </button>
        </div>
    @endif

    {{-- RECHTS: 4 beschriftete Schnellzugriff-Icons + direkt sichtbare Status/Optionen --}}
    <div class="flex flex-wrap items-center gap-1.5 self-end xl:self-center">
        @php
            $nextInQueue = $this->nextQueueOrder;
            $hasDiffShipping = $this->selectedOrder->hasDifferentShippingAddress();
        @endphp

        {{-- 1. Dateien --}}
        <button type="button"
                @click="openHeaderModalClient('files')"
                title="Dateien & Downloads"
                :class="headerModal === 'files'
                    ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                    : 'bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800'"
                class="min-w-[52px] px-2.5 py-1.5 rounded-xl border flex flex-col items-center justify-center gap-0.5 transition-all cursor-pointer">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            <span class="text-[8px] font-black uppercase tracking-wider leading-none">Dateien</span>
        </button>

        {{-- 2. Kosten --}}
        <button type="button"
                @click="openHeaderModalClient('costs')"
                title="Kostenübersicht"
                aria-label="Kostenübersicht"
                :class="headerModal === 'costs'
                    ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                    : 'bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800'"
                class="min-w-[52px] px-2.5 py-1.5 rounded-xl border flex flex-col items-center justify-center gap-0.5 transition-all cursor-pointer">
            <span class="h-4 flex items-center justify-center text-xs font-black leading-none">&euro;</span>
            <span class="text-[8px] font-black uppercase tracking-wider leading-none">Kosten</span>
        </button>

        {{-- 3. Kunde --}}
        <button type="button"
                @click="openHeaderModalClient('customer')"
                title="Kunden Infos"
                aria-label="Kunden Infos"
                :class="headerModal === 'customer'
                    ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                    : 'bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800'"
                class="min-w-[52px] px-2.5 py-1.5 rounded-xl border flex flex-col items-center justify-center gap-0.5 transition-all cursor-pointer">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span class="text-[8px] font-black uppercase tracking-wider leading-none">Kunde</span>
        </button>

        {{-- 4. Adresse --}}
        <button type="button"
                @click="openHeaderModalClient('shipping')"
                title="Lieferadresse{{ $hasDiffShipping ? ' (Abweichend)' : '' }}"
                aria-label="Lieferadresse"
                :class="headerModal === 'shipping'
                    ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                    : ({{ $hasDiffShipping ? 'true' : 'false' }}
                        ? 'bg-white dark:bg-gray-950 text-[var(--theme-color)] border-[var(--theme-color-50)] hover:border-[var(--theme-color)]'
                        : 'bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800')"
                class="relative min-w-[52px] px-2.5 py-1.5 rounded-xl border flex flex-col items-center justify-center gap-0.5 transition-all cursor-pointer">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-[8px] font-black uppercase tracking-wider leading-none">Adresse</span>
            @if($hasDiffShipping)
                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-[var(--theme-color)]"></span>
            @endif
        </button>

        <div class="hidden sm:block h-7 w-px bg-gray-200 dark:bg-gray-800 mx-0.5"></div>

        {{-- Direkt eingeblendete Status- & Options-Steuerung (ohne Extra-Button/Dropdown) --}}
        <select wire:model="payment_status"
                wire:change="saveStatus"
                title="Zahlungsstatus"
                aria-label="Zahlungsstatus"
                class="h-10 bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-50)] text-gray-900 dark:text-white text-[10px] font-black uppercase tracking-wider rounded-xl px-2.5 cursor-pointer outline-none transition-colors">
            <option value="unpaid" @selected($this->selectedOrder->payment_status == 'unpaid')>Zahlung: Offen</option>
            <option value="paid" @selected($this->selectedOrder->payment_status == 'paid')>Zahlung: Bezahlt</option>
            <option value="refunded" @selected($this->selectedOrder->payment_status == 'refunded')>Zahlung: Erstattet</option>
        </select>

        <select wire:change="updateStatus('{{ $this->selectedOrder->id }}', $event.target.value)"
                title="Bestellstatus"
                aria-label="Bestellstatus"
                class="h-10 bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-50)] text-gray-900 dark:text-white text-[10px] font-black uppercase tracking-wider rounded-xl px-2.5 cursor-pointer outline-none transition-colors">
            <option value="pending" @selected($this->selectedOrder->status == 'pending')>Wartend</option>
            <option value="processing" @selected($this->selectedOrder->status == 'processing')>In Bearbeitung</option>
            <option value="shipped" @selected($this->selectedOrder->status == 'shipped')>Versendet</option>
            <option value="completed" @selected($this->selectedOrder->status == 'completed')>Abgeschlossen</option>
            <option value="cancelled" @selected($this->selectedOrder->status == 'cancelled')>Storniert</option>
        </select>

        @if(!$this->selectedOrder->isOnlyDigital())
            <button type="button"
                    wire:click="openDhlModal('{{ $this->selectedOrder->id }}')"
                    title="DHL Label-Assistent öffnen"
                    class="min-w-[52px] px-2.5 py-1.5 rounded-xl bg-[var(--theme-color-15)] hover:bg-[var(--theme-color)] text-[var(--theme-color)] hover:text-gray-950 border border-[var(--theme-color-40)] flex flex-col items-center justify-center gap-0.5 transition-all cursor-pointer">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span class="text-[8px] font-black uppercase tracking-wider leading-none">DHL</span>
            </button>
        @endif

        @if($this->selectedOrder->canBePermanentlyDeleted())
            <button type="button"
                    wire:click="delete('{{ $this->selectedOrder->id }}')"
                    wire:confirm="Unbezahlte Bestellung #{{ $this->selectedOrder->order_number }} unwiderruflich löschen?"
                    title="Bestellung unwiderruflich löschen"
                    class="min-w-[52px] px-2.5 py-1.5 rounded-xl bg-red-50 dark:bg-red-950/30 hover:bg-red-600 text-red-600 dark:text-red-400 hover:text-white border border-red-200 dark:border-red-800/50 flex flex-col items-center justify-center gap-0.5 transition-all cursor-pointer">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span class="text-[8px] font-black uppercase tracking-wider leading-none">Löschen</span>
                <span class="sr-only">Bestellung unwiderruflich löschen</span>
            </button>
        @endif

        @if($nextInQueue)
            <button type="button"
                    wire:click="openDetail('{{ $nextInQueue['id'] }}')"
                    title="Direkt weiter zu #{{ $nextInQueue['order_number'] }}{{ !empty($nextInQueue['product_name']) ? ' (' . $nextInQueue['product_name'] . ')' : '' }}"
                    class="min-w-[52px] px-2.5 py-1.5 rounded-xl border flex flex-col items-center justify-center gap-0.5 transition-all cursor-pointer {{ !empty($nextInQueue['is_same_product'])
                        ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-40)] hover:bg-[var(--theme-color)] hover:text-gray-900'
                        : 'bg-gray-100 dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)] hover:text-gray-900 dark:hover:text-white' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                <span class="text-[8px] font-black uppercase tracking-wider leading-none">Weiter</span>
            </button>
        @endif
    </div>

</div>
