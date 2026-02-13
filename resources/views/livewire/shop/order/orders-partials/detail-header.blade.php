<div class="bg-white border-b border-gray-100 px-6 py-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shrink-0 z-20 shadow-[0_4px_20px_-10px_rgba(0,0,0,0.05)]">

    <div class="flex items-center gap-4">
        <button wire:click="closeDetail" class="group flex items-center gap-2 text-gray-400 hover:text-gray-900 transition-colors">
            <div class="w-8 h-8 rounded-full bg-gray-50 group-hover:bg-gray-100 flex items-center justify-center border border-gray-200 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </div>
            <span class="text-sm font-bold hidden sm:inline">Übersicht</span>
        </button>

        <div class="h-8 w-px bg-gray-200"></div>

        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-serif font-bold text-gray-900">
                    #{{ $this->selectedOrder->order_number }}
                </h1>
                @if($this->selectedOrder->is_express)
                    <span class="bg-red-50 text-red-600 border border-red-100 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Express</span>
                @endif
            </div>
            <p class="text-xs text-gray-400 mt-0.5">
                Eingegangen am {{ $this->selectedOrder->created_at->format('d.m.Y \u\m H:i') }} Uhr
            </p>
        </div>
    </div>

    {{-- Status Actions --}}
    <div class="w-full sm:w-auto flex items-center gap-3">
        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider hidden sm:block">Status:</label>
        <div class="relative">
            <select wire:change="updateStatus('{{ $this->selectedOrder->id }}', $event.target.value)"
                    class="appearance-none w-full sm:w-48 bg-gray-50 hover:bg-white border border-gray-200 text-gray-700 text-sm rounded-lg py-2 pl-4 pr-10 shadow-sm focus:ring-primary focus:border-primary font-bold cursor-pointer transition-colors">
                <option value="pending" @selected($this->selectedOrder->status == 'pending')>🟠 Wartend</option>
                <option value="processing" @selected($this->selectedOrder->status == 'processing')>🔵 In Bearbeitung</option>
                <option value="shipped" @selected($this->selectedOrder->status == 'shipped')>🟣 Versendet</option>
                <option value="completed" @selected($this->selectedOrder->status == 'completed')>🟢 Abgeschlossen</option>
                <option value="cancelled" @selected($this->selectedOrder->status == 'cancelled')>🔴 Storniert</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </div>
        </div>
    </div>
</div>
