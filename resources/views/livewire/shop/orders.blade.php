<div class="min-h-screen bg-gray-50 p-4 md:p-6">

    {{-- VIEW 1: BESTELLÜBERSICHT (LISTE) --}}
    @if(!$selectedOrderId)

        @include("livewire.shop.orders-partials.header-stats-and-search")

        @include("livewire.shop.orders-partials.table")

        {{-- VIEW 2: DETAIL ANSICHT (SPLIT SCREEN) --}}
    @else
        {{-- FIX: Main Container handles split. Mobile: Vertical Stack (col), Desktop: Horizontal (row) --}}
        <div
            class="h-[calc(100vh-3rem)] flex flex-col bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">

            @include("livewire.shop.orders-partials.detail-header")

            {{-- SPLIT CONTENT: Mobile = Column (Stacked), Desktop = Row (Side by Side) --}}
            <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">

                {{-- LINKS: Order Details & Liste (Scrollbar) --}}
                @include("livewire.shop.orders-partials.left-column")

                {{-- RECHTS: Configurator (FIX: Scrollbar auf den Parent Container) --}}
                @include("livewire.shop.orders-partials.right-column")

            </div>
        </div>
    @endif

</div>
