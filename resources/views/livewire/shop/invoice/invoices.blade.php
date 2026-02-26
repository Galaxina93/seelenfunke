<div class="p-4 md:p-8 bg-transparent min-h-screen font-sans antialiased text-gray-300" x-data="{ draftBtnText: 'Entwurf speichern' }"
     x-on:reset-draft-success.window="setTimeout(() => { $wire.draftSuccess = false }, 3000)">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold font-serif text-white tracking-tight">Rechnungsverwaltung</h2>
            <p class="text-xs sm:text-sm text-gray-400 mt-1 font-medium">Shop-Bestellungen und manuelle Belege.</p>
        </div>
        <div class="flex flex-wrap gap-3 w-full md:w-auto">
            <button wire:click="toggleManualCreate"
                    class="flex-1 md:flex-none bg-gray-900 border border-gray-700 text-gray-300 px-5 py-2.5 rounded-xl hover:bg-gray-800 hover:text-white transition-all shadow-inner text-[10px] sm:text-xs font-black uppercase tracking-widest flex items-center justify-center gap-2">
                {{ $isCreatingManual ? 'Zurück zur Liste' : '+ Rechnung erstellen' }}
            </button>
            <button wire:click="generateForPaidOrders" wire:loading.attr="disabled"
                    class="flex-1 md:flex-none bg-primary border border-primary/50 text-gray-900 px-5 py-2.5 rounded-xl hover:bg-primary-dark transition-all shadow-[0_0_20px_rgba(197,160,89,0.3)] text-[10px] sm:text-xs font-black uppercase tracking-widest flex items-center justify-center gap-2 hover:scale-[1.02]">
                <span wire:loading.remove wire:target="generateForPaidOrders">Bulk-Action</span>
                <span wire:loading wire:target="generateForPaidOrders" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    Wird ausgeführt...
                </span>
            </button>
        </div>
    </div>

    @if($isCreatingManual)
        @include('livewire.shop.invoice.partials.invoice_create')
    @else
        @include('livewire.shop.invoice.partials.invoice_main_table')
    @endif

    <livewire:shop.invoice.invoice-preview/>
</div>
