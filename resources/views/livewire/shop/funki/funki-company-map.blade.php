<section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 relative overflow-hidden transition-all duration-500 mt-6 flex flex-col h-[85vh] min-h-[800px]"
         x-data="companyMapData({ nodes: @entangle('nodes').live, edges: @entangle('edges').live })">

    <div class="absolute top-0 left-0 w-2 h-full bg-primary z-10 pointer-events-none"></div>

    {{-- HEADER --}}
    @include('livewire.shop.funki.partials.funki-company-map.header')

    {{-- CANVAS --}}
    @include('livewire.shop.funki.partials.funki-company-map.canvas')

    {{-- ===================== MODAL: NEUER KNOTEN ===================== --}}
    @include('livewire.shop.funki.partials.funki-company-map.modal-new-knot')

    {{-- ===================== MODAL: KNOTEN BEARBEITEN ===================== --}}
    @include('livewire.shop.funki.partials.funki-company-map.modal-knot')

    {{-- ===================== MODAL: VERBINDUNG ===================== --}}
    @include('livewire.shop.funki.partials.funki-company-map.modal-connection')

    {{-- ===================== NODE PANEL (intelligente Datenanzeige) ===================== --}}
    @include('livewire.shop.funki.partials.funki-company-map.panel')

    {{-- ===================== ALPINE.JS ===================== --}}
    @include('livewire.shop.funki.partials.funki-company-map.scripts')

</section>
