<div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200 dark:border-gray-800 px-5 sm:px-8 py-4 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 shrink-0 z-30">

    {{-- LINKS: Zurück-Button + Auftragsnummer + Zahlstatus-Pill --}}
    <div class="flex items-center gap-3.5 sm:gap-5">
        <button wire:click="closeDetail" class="group flex items-center gap-2.5 text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer">
            <div class="w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center group-hover:bg-[var(--theme-color)] group-hover:border-[var(--theme-color)] group-hover:text-gray-900 transition-all duration-300 shadow-sm">
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </div>
            <span class="text-[10px] font-black uppercase tracking-widest hidden sm:inline">Übersicht</span>
        </button>

        <div class="h-7 w-px bg-gray-200 dark:bg-gray-800"></div>

        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                    #{{ $this->selectedOrder->order_number }}
                </h1>

                @if($this->selectedOrder->is_express)
                    <x-shop.express-info iconClass="w-3.5 h-3.5" class="text-gray-800 dark:text-gray-100 border border-primary/40 text-[9px] font-black px-2 py-0.5 rounded-md uppercase tracking-widest shadow-2xs" />
                @endif

                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider border {{ $this->selectedOrder->payment_status === 'paid'
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'
                    : ($this->selectedOrder->payment_status === 'refunded'
                        ? 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-700'
                        : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30') }}">
                    <span>{{ $this->selectedOrder->payment_status === 'paid' ? '✓ Bezahlt' : ($this->selectedOrder->payment_status === 'refunded' ? 'Erstattet' : 'Zahlung offen') }}</span>
                </span>
            </div>
            <p class="text-[10px] font-medium text-gray-500 dark:text-gray-400 mt-0.5">
                {{ $this->selectedOrder->customer_name }} · {{ $this->selectedOrder->created_at->format('d.m.Y H:i') }} Uhr · {{ $this->selectedOrder->items->count() }} {{ $this->selectedOrder->items->count() === 1 ? 'Artikel' : 'Artikel' }}
            </p>
        </div>
    </div>

    {{-- MITTE: ZENTRIERTE SCHRITT-PIPELINE --}}
    @php
        $headerMfgOverview = $this->selectedOrder->getManufacturingOverview();
        $isPendingStart = $this->selectedOrder->status === 'pending';
        $activeTabStep = (int) ($this->activeOrderStep ?? 1);
        $step1Done = (bool) ($headerMfgOverview['all_step_one_done'] ?? false) || in_array($this->selectedOrder->status, ['shipped', 'completed'], true);
        $step2Done = in_array($this->selectedOrder->status, ['shipped', 'completed'], true);
        $step3Done = $this->selectedOrder->status === 'completed';
    @endphp
    @if(!$isPendingStart && !in_array($this->selectedOrder->status, ['cancelled', 'refunded'], true))
        <div class="flex items-center gap-1.5 bg-gray-100/90 dark:bg-gray-950/90 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner w-full lg:w-auto overflow-x-auto justify-center">
            {{-- Tab 1: In Bearbeitung --}}
            <button type="button"
                    wire:click="setOrderStep(1)"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-[10px] sm:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer shrink-0 {{ $activeTabStep === 1
                        ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm'
                        : ($step1Done
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/20'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white') }}">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black {{ $activeTabStep === 1 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step1Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                    {{ $step1Done ? '✓' : '1' }}
                </span>
                <span>Schritt 1 · In Bearbeitung ({{ $headerMfgOverview['completed_items'] }}/{{ $headerMfgOverview['total_items'] }})</span>
            </button>

            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>

            {{-- Tab 2: Versand --}}
            <button type="button"
                    wire:click="setOrderStep(2)"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-[10px] sm:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer shrink-0 {{ $activeTabStep === 2
                        ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm'
                        : ($step2Done
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/20'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white') }}">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black {{ $activeTabStep === 2 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step2Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                    {{ $step2Done ? '✓' : '2' }}
                </span>
                <span>Schritt 2 · Versand</span>
            </button>

            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>

            {{-- Tab 3: Abgeschlossen --}}
            <button type="button"
                    wire:click="setOrderStep(3)"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-[10px] sm:text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer shrink-0 {{ $activeTabStep === 3
                        ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm'
                        : ($step3Done
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/20'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white') }}">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black {{ $activeTabStep === 3 ? 'bg-gray-950 text-[var(--theme-color)]' : ($step3Done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300') }}">
                    {{ $step3Done ? '✓' : '3' }}
                </span>
                <span>Schritt 3 · Abgeschlossen</span>
            </button>
        </div>
    @endif

    {{-- RECHTS: Nächster Auftrag & kompaktes Optionen-Menü (Manuelle Status-Overrides bei Bedarf) --}}
    <div x-data="{ showHeaderMenu: false }" class="flex items-center gap-2.5 self-end lg:self-center relative">
        @php
            $nextInQueue = $this->nextQueueOrder;
        @endphp
        @if($nextInQueue)
            <button type="button"
                    wire:click="openDetail('{{ $nextInQueue['id'] }}')"
                    title="Direkt weiter zu #{{ $nextInQueue['order_number'] }}{{ !empty($nextInQueue['product_name']) ? ' (' . $nextInQueue['product_name'] . ')' : '' }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider border transition-all cursor-pointer {{ !empty($nextInQueue['is_same_product'])
                        ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-40)] hover:bg-[var(--theme-color)] hover:text-gray-900'
                        : 'bg-gray-100 dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)] hover:text-gray-900 dark:hover:text-white' }}">
                <span>{{ !empty($nextInQueue['is_same_product']) ? 'Nächster gleicher Artikel' : 'Nächster Auftrag' }}</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        @endif

        <button type="button"
                @click="showHeaderMenu = !showHeaderMenu"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            <span>Status / Optionen</span>
        </button>

        {{-- Dropdown für manuelle Status-Korrektur & Direkt-Label --}}
        <div x-cloak
             x-show="showHeaderMenu"
             @click.outside="showHeaderMenu = false"
             x-transition
             class="absolute right-0 top-full mt-2 w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-2xl p-4 space-y-3 z-50">
            <div class="text-[9px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100 dark:border-gray-800 pb-1.5">
                Manuelle Verwaltung (#{{ $this->selectedOrder->order_number }})
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase tracking-wider text-gray-500 mb-1">Zahlungsstatus</label>
                <select wire:model="payment_status" wire:change="saveStatus"
                        class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold rounded-xl py-2 px-3 cursor-pointer outline-none">
                    <option value="unpaid" @selected($this->selectedOrder->payment_status == 'unpaid')>❌ Offen</option>
                    <option value="paid" @selected($this->selectedOrder->payment_status == 'paid')>✅ Bezahlt</option>
                    <option value="refunded" @selected($this->selectedOrder->payment_status == 'refunded')>💳 Erstattet</option>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase tracking-wider text-gray-500 mb-1">Bestellstatus (wird autonom gesetzt)</label>
                <select wire:change="updateStatus('{{ $this->selectedOrder->id }}', $event.target.value)"
                        class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold rounded-xl py-2 px-3 cursor-pointer outline-none">
                    <option value="pending" @selected($this->selectedOrder->status == 'pending')>🟠 Wartend</option>
                    <option value="processing" @selected($this->selectedOrder->status == 'processing')>🔵 In Bearbeitung</option>
                    <option value="shipped" @selected($this->selectedOrder->status == 'shipped')>🟣 Versendet</option>
                    <option value="completed" @selected($this->selectedOrder->status == 'completed')>🟢 Abgeschlossen</option>
                    <option value="cancelled" @selected($this->selectedOrder->status == 'cancelled')>🔴 Storniert</option>
                </select>
            </div>

            @if(!$this->selectedOrder->isOnlyDigital())
                <div class="pt-2 border-t border-gray-100 dark:border-gray-800 space-y-2">
                    <button type="button"
                            wire:click="openDhlModal('{{ $this->selectedOrder->id }}')"
                            @click="showHeaderMenu = false"
                            class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-[var(--theme-color)] text-gray-950 rounded-xl font-black text-[10px] uppercase tracking-wider cursor-pointer">
                        <span>DHL Label-Assistent öffnen</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

</div>
