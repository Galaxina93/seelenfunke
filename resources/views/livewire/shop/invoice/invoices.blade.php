<div class="p-2 md:p-6 bg-gray-50 min-h-screen" x-data="{ draftBtnText: 'Entwurf speichern' }"
     x-on:reset-draft-success.window="setTimeout(() => { $wire.draftSuccess = false }, 3000)">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold font-serif text-gray-800">Rechnungsverwaltung</h2>
            <p class="text-sm text-gray-500">Shop-Bestellungen und manuelle Belege.</p>
        </div>
        <div class="flex flex-wrap gap-2 w-full md:w-auto">
            <button wire:click="toggleManualCreate"
                    class="flex-1 md:flex-none bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition shadow-sm text-sm font-bold uppercase">
                {{ $isCreatingManual ? 'Zurück zur Liste' : '+ Rechnung erstellen' }}
            </button>
            <button wire:click="generateForPaidOrders" wire:loading.attr="disabled"
                    class="flex-1 md:flex-none bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark shadow-sm text-sm font-bold uppercase">
                <span>Bulk-Action</span>
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
