<div class="p-6 lg:p-10 min-h-full flex flex-col relative z-10">

    {{-- SEITEN-HEADER --}}
    <div class="mb-10 flex flex-col sm:flex-row sm:items-end justify-between gap-6 animate-fade-in-up">
        <div>
            <h1 class="text-4xl md:text-5xl font-serif font-bold text-white tracking-tight">Der Funki Shop</h1>
            <p class="text-gray-400 mt-2 text-sm uppercase tracking-widest font-bold">Rüste deinen magischen Begleiter aus</p>
        </div>

        {{-- SUCHE --}}
        <div class="relative w-full sm:w-96 group">
            <div class="absolute inset-0 bg-primary/20 rounded-full blur opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Item suchen..." class="relative w-full rounded-full border border-gray-700 bg-gray-900 text-white shadow-inner focus:border-primary focus:ring-primary text-sm px-6 py-4 pl-14 transition-all placeholder-gray-500 font-medium tracking-wide outline-none">
            <svg class="w-6 h-6 text-gray-500 absolute left-5 top-3.5 group-hover:text-primary transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    {{-- INHALT: FILTER & GRID --}}
    <div class="space-y-8 animate-fade-in-up delay-100">

        {{-- Die Filter Leiste --}}
        @include('livewire.customer.partials.header_filter')

        {{-- Das eigentliche Shop-Grid --}}
        <div class="bg-gray-900/50 rounded-[2.5rem] border border-gray-800 shadow-2xl overflow-hidden backdrop-blur-sm">
            @include('livewire.customer.partials.shop-grid')
        </div>

        {{-- Paginierung --}}
        <div class="mt-8">
            {{ $items->links() }}
        </div>

    </div>
</div>
