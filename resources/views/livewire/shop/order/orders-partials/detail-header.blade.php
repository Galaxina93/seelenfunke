{{-- Detail Header --}}
<div
    class="bg-white border-b border-gray-200 px-4 md:px-6 py-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shrink-0 z-20 relative">
    <div class="flex items-center gap-4">
        <button wire:click="closeDetail"
                class="text-gray-500 hover:text-gray-900 flex items-center gap-1 text-sm font-bold transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span class="hidden sm:inline">Zurück zur Liste</span>
            <span class="sm:hidden">Zurück</span>
        </button>
        <div class="h-6 w-px bg-gray-300"></div>
        <div>
            <h1 class="text-lg md:text-xl font-serif font-bold text-gray-900 flex flex-wrap items-center gap-2">
                #{{ $this->selectedOrder->order_number }}
                <span class="text-xs font-sans font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                {{ $this->selectedOrder->created_at->format('d.m.Y H:i') }}
                            </span>
            </h1>
        </div>
    </div>

    {{-- Status Actions --}}
    <div class="flex gap-2 w-full sm:w-auto">
        <select wire:change="updateStatus('{{ $this->selectedOrder->id }}', $event.target.value)"
                class="w-full sm:w-auto text-sm border-gray-300 rounded-lg py-1.5 pl-3 pr-8 shadow-sm focus:ring-primary focus:border-primary">
            <option value="pending" @selected($this->selectedOrder->status == 'pending')>Wartend</option>
            <option value="processing" @selected($this->selectedOrder->status == 'processing')>In
                Bearbeitung
            </option>
            <option value="shipped" @selected($this->selectedOrder->status == 'shipped')>Versendet</option>
            <option value="completed" @selected($this->selectedOrder->status == 'completed')>Abgeschlossen
            </option>
            <option value="cancelled" @selected($this->selectedOrder->status == 'cancelled')>Storniert
            </option>
        </select>
    </div>
</div>
