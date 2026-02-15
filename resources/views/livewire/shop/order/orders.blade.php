<div class="min-h-screen bg-gray-50 p-4 md:p-8">

    {{-- VIEW 1: LISTEN --}}
    @if(!$selectedOrderId)

        {{-- 1. EXTENDED STATS DASHBOARD --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-10">

            {{-- Card 1: Offene Aufgaben --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 bg-blue-500 rounded-r-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Offene Aufgaben</p>
                        <h3 class="text-3xl font-serif font-bold text-gray-900 group-hover:text-blue-600 transition-colors">{{ $stats['open'] }}</h3>
                    </div>
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
            </div>

            {{-- Card 2: Express Warnung --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 bg-red-500 rounded-r-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Davon Express</p>
                        <h3 class="text-3xl font-serif font-bold text-gray-900 {{ $stats['open_express'] > 0 ? 'text-red-600' : '' }}">{{ $stats['open_express'] }}</h3>
                    </div>
                    <div class="p-3 bg-red-50 text-red-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                </div>
            </div>

            {{-- Card 3: Umsatz Heute --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 bg-green-500 rounded-r-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Umsatz Heute</p>
                        <h3 class="text-2xl font-serif font-bold text-gray-900 group-hover:text-green-600 transition-colors">{{ number_format($stats['revenue_today'] / 100, 2, ',', '.') }} €</h3>
                        <p class="text-[10px] text-gray-400 mt-1">Ø Korb: {{ number_format($stats['avg_cart'] / 100, 2, ',', '.') }} €</p>
                    </div>
                    <div class="p-3 bg-green-50 text-green-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
            </div>

            {{-- Card 4: Umsatz Monat --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 bg-purple-500 rounded-r-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Umsatz {{ \Carbon\Carbon::now()->translatedFormat('F') }}</p>
                        <h3 class="text-2xl font-serif font-bold text-gray-900 group-hover:text-purple-600 transition-colors">{{ number_format($stats['revenue_month'] / 100, 0, ',', '.') }} €</h3>
                        <p class="text-[10px] text-gray-400 mt-1">Total: {{ $stats['total'] }} Orders</p>
                    </div>
                    <div class="p-3 bg-purple-50 text-purple-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- FILTER LEISTE --}}
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8 bg-white p-2 rounded-xl shadow-sm border border-gray-100">
            <div class="relative w-full md:w-96 group">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche nach Nr., Name, Mail..." class="w-full pl-10 pr-4 py-2 border-none bg-transparent text-sm focus:ring-0 placeholder-gray-400">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2 group-focus-within:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div class="flex gap-2 w-full md:w-auto overflow-x-auto no-scrollbar px-2">
                <select wire:model.live="statusFilter" class="px-3 py-1.5 bg-gray-50 border-0 rounded-lg text-xs font-bold text-gray-600 focus:ring-2 focus:ring-primary cursor-pointer hover:bg-gray-100">
                    <option value="">Status: Alle</option>
                    <option value="pending">Wartend</option>
                    <option value="processing">In Bearbeitung</option>
                    <option value="shipped">Versendet</option>
                    <option value="completed">Abgeschlossen</option>
                    <option value="cancelled">Storniert</option>
                </select>
                <select wire:model.live="paymentFilter" class="px-3 py-1.5 bg-gray-50 border-0 rounded-lg text-xs font-bold text-gray-600 focus:ring-2 focus:ring-primary cursor-pointer hover:bg-gray-100">
                    <option value="">Zahlung: Alle</option>
                    <option value="paid">Bezahlt</option>
                    <option value="unpaid">Offen</option>
                </select>
            </div>
        </div>

        {{-- TABELLE 1: AKTUELLE AUFGABEN --}}
        @if(count($activeOrders) > 0)
            <div class="mb-12 animate-fade-in-up">
                <div class="flex items-center gap-3 mb-4 px-1">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
                    </span>
                    <h2 class="text-xl font-serif font-bold text-gray-900">Aktuelle Aufgaben</h2>
                    <span class="bg-primary/10 text-primary text-xs font-bold px-2 py-1 rounded-md">{{ count($activeOrders) }}</span>
                </div>

                {{-- Wir übergeben die aktiven Orders unter dem Variablennamen $orders an die Tabelle --}}
                @include('livewire.shop.order.orders-partials.table', ['orders' => $activeOrders])
            </div>
        @endif

        {{-- TABELLE 2: ARCHIV --}}
        @if($archivedOrders->isNotEmpty())
            <div class="animate-fade-in-up" style="animation-delay: 100ms;">
                <div class="flex items-center gap-3 mb-4 px-1 opacity-70">
                    <div class="h-3 w-3 bg-gray-300 rounded-full"></div>
                    <h2 class="text-xl font-serif font-bold text-gray-500">Archiv & Erledigt</h2>
                </div>

                <div class="opacity-90 hover:opacity-100 transition-opacity duration-300">
                    @include('livewire.shop.order.orders-partials.table', ['orders' => $archivedOrders])
                </div>
            </div>
        @endif

        @if(count($activeOrders) === 0 && $archivedOrders->isEmpty())
            <div class="text-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm border-dashed">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-50 mb-4">
                    <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Keine Bestellungen gefunden</h3>
                <p class="text-sm text-gray-500">Es scheint ruhig zu sein, oder der Filter passt nicht.</p>
            </div>
        @endif

        {{-- VIEW 2: DETAIL ANSICHT --}}
    @else
        <div class="h-[calc(100vh-2rem)] flex flex-col bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden animate-fade-in-up">
            @include("livewire.shop.order.orders-partials.detail-header")
            <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">
                @include('livewire.shop.shared.order-offer-detail-content', [
                    'model' => $selectedOrder,
                    'context' => 'order',
                    'selectedItemId' => $selectedOrderItemId,
                    'previewItem' => $this->previewItem
                ])
            </div>
        </div>
    @endif

    {{-- VERSAND-MODAL --}}
    @include("livewire.shop.order.orders-partials.shipping-modal")

</div>
