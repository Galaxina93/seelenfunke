<div class="min-h-screen bg-gray-50 p-4 md:p-6">

    {{-- VIEW 1: BESTELLÜBERSICHT (LISTE) --}}
    @if(!$selectedOrderId)

        {{-- STATS HEADER --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-blue-50 rounded-full text-blue-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Bestellungen Gesamt</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-yellow-50 rounded-full text-yellow-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Offene Bestellungen</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['open'] }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-green-50 rounded-full text-green-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Umsatz Heute</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['revenue_today'] / 100, 2, ',', '.') }} €</p>
                </div>
            </div>
        </div>


        {{-- TOOLBAR & SUCHE --}}
        <div class="bg-white rounded-t-xl border border-gray-200 p-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="relative w-full md:w-96">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche (Nr, Name)..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div class="flex gap-2 w-full md:w-auto overflow-x-auto no-scrollbar">
                <select wire:model.live="statusFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary">
                    <option value="">Alle Status</option>
                    <option value="pending">Wartend</option>
                    <option value="processing">In Bearbeitung</option>
                    <option value="shipped">Versendet</option>
                    <option value="completed">Abgeschlossen</option>
                    <option value="cancelled">Storniert</option>
                </select>
                <select wire:model.live="paymentFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary">
                    <option value="">Alle Zahlungen</option>
                    <option value="paid">Bezahlt</option>
                    <option value="unpaid">Offen</option>
                </select>
            </div>
        </div>


        @include("livewire.shop.order.orders-partials.table")

        {{-- VIEW 2: DETAIL ANSICHT (SPLIT SCREEN) --}}
    @else
        {{-- FIX: Main Container handles split. Mobile: Vertical Stack (col), Desktop: Horizontal (row) --}}
        <div class="h-[calc(100vh-3rem)] flex flex-col bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">

            @include("livewire.shop.order.orders-partials.detail-header")

            {{-- SPLIT CONTENT --}}
            <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">
                @include('livewire.shop.shared.order-offer-detail-content', [
                    'model' => $selectedOrder,
                    'context' => 'order',
                    'selectedItemId' => $selectedOrderItemId,
                    'previewItem' => $this->previewItem // Nutzt die Computed Property aus Orders.php
                ])
            </div>

        </div>
    @endif

    {{-- VERSAND-SICHERHEITS-MODAL --}}
    @include("livewire.shop.order.orders-partials.shipping-modal")

</div>
