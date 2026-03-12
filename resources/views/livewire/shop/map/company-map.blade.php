<section class="bg-gray-900/80 backdrop-blur-xl rounded-[1.5rem] sm:rounded-[2.5rem] shadow-2xl border border-gray-800 relative overflow-hidden transition-all duration-500 flex flex-col h-full w-full"
         x-data="companyMapData({ nodes: @entangle('nodes').live, edges: @entangle('edges').live, liveAiPulse: @entangle('liveAiState'), activeMap: @entangle('activeMap') })">

    <div class="absolute top-0 left-0 w-1 sm:w-1.5 h-full bg-gradient-to-b from-primary to-primary-dark z-10 pointer-events-none opacity-80"></div>

    {{-- Toast Notification (Alpine) für Funki Log Feedbacks --}}
    <div x-data="{ show: false, msg: '', type: 'info' }"
         @funki-toast.window="msg = $event.detail.message; type = $event.detail.type; show = true; setTimeout(() => show = false, 4000)"
         class="fixed top-24 left-1/2 -translate-x-1/2 z-[9999] pointer-events-none">
        <div x-show="show" x-transition.opacity.duration.300ms
             :class="type === 'error' ? 'bg-red-500/10 border-red-500 text-red-400' : 'bg-emerald-500/10 border-emerald-500 text-emerald-400'"
             class="px-6 py-3 rounded-full border shadow-2xl backdrop-blur-md flex items-center gap-3 text-xs font-bold tracking-wide">
            <span x-text="msg"></span>
        </div>
    </div>

    {{-- HEADER --}}
    @include('livewire.shop.map.partials.header')

    {{-- CANVAS --}}
    @include('livewire.shop.map.partials.canvas')

    {{-- ===================== MODAL: NEUER KNOTEN ===================== --}}
    @include('livewire.shop.map.partials.modal-new-knot')

    {{-- ===================== MODAL: KNOTEN BEARBEITEN ===================== --}}
    @include('livewire.shop.map.partials.modal-knot')

    {{-- ===================== MODAL: VERBINDUNG ===================== --}}
    @include('livewire.shop.map.partials.modal-connection')

    {{-- ===================== NODE PANEL (intelligente Datenanzeige) ===================== --}}
    @include('livewire.shop.map.partials.panel')

    {{-- ===================== ALPINE.JS ===================== --}}
    @include('livewire.shop.map.partials.scripts')

</section>
