<div class="bg-gray-900/50 backdrop-blur-xl border-b border-gray-800 px-6 py-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-5 shrink-0 z-20">

    <div class="flex items-center gap-4 sm:gap-6">
        <button wire:click="closeDetail" class="group flex items-center gap-3 text-gray-500 hover:text-white transition-colors">
            <div class="w-10 h-10 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center group-hover:bg-primary group-hover:border-primary group-hover:text-gray-900 transition-all duration-300 shadow-inner">
                <svg class="w-5 h-5 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </div>
            <span class="text-[10px] font-black uppercase tracking-widest hidden sm:inline transition-colors">Übersicht</span>
        </button>

        <div class="h-8 w-px bg-gray-800"></div>

        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl sm:text-2xl font-serif font-bold text-white tracking-tight">
                    #{{ $this->selectedOrder->order_number }}
                </h1>
                @if($this->selectedOrder->is_express)
                    <span class="bg-red-500/10 text-red-400 border border-red-500/30 text-[9px] font-black px-2.5 py-1 rounded-md uppercase tracking-widest flex items-center gap-1.5 shadow-[0_0_15px_rgba(239,68,68,0.15)]">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_currentColor]"></span> Express
                    </span>
                @endif
            </div>
            <p class="text-[10px] font-medium text-gray-500 mt-1 uppercase tracking-widest">
                Eingegangen am <span class="text-gray-400 font-bold">{{ $this->selectedOrder->created_at->format('d.m.Y') }}</span> um <span class="text-gray-400 font-bold">{{ $this->selectedOrder->created_at->format('H:i') }}</span> Uhr
            </p>
        </div>
    </div>

    {{-- Status Actions --}}
    <div class="w-full justify-between sm:justify-end flex flex-row items-center gap-3 sm:w-auto">

        {{-- Payment Status --}}
        <div class="flex items-center gap-2">
            <div class="relative w-full sm:w-auto">
                <select wire:model="payment_status" wire:change="saveStatus"
                        class="appearance-none w-full sm:w-48 bg-gray-950 hover:bg-gray-900 border border-gray-800 text-white text-xs font-bold rounded-xl py-2.5 pl-4 pr-10 shadow-inner focus:ring-2 focus:ring-primary/50 focus:border-primary cursor-pointer transition-all outline-none tracking-wide">
                    <option value="unpaid" class="bg-gray-900 text-white" @selected($this->selectedOrder->payment_status == 'unpaid')>❌ Offen</option>
                    <option value="paid" class="bg-gray-900 text-white" @selected($this->selectedOrder->payment_status == 'paid')>✅ Bezahlt</option>
                    <option value="refunded" class="bg-gray-900 text-white" @selected($this->selectedOrder->payment_status == 'refunded')>💳 Erstattet</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>
            </div>
        </div>

        {{-- Order Status --}}
        <div class="flex items-center gap-2">
            <div class="relative w-full sm:w-auto">
                {{-- Achtung: Hier verwenden wir updateStatus direkt oder wir binden es auch an model/saveStatus? Der aktuelle Code nutzt wire:change="updateStatus" --}}
                <select wire:change="updateStatus('{{ $this->selectedOrder->id }}', $event.target.value)"
                        class="appearance-none w-full sm:w-56 bg-gray-950 hover:bg-gray-900 border border-gray-800 text-white text-xs font-bold rounded-xl py-2.5 pl-4 pr-10 shadow-inner focus:ring-2 focus:ring-primary/50 focus:border-primary cursor-pointer transition-all outline-none tracking-wide">
                    <option value="pending" class="bg-gray-900 text-white" @selected($this->selectedOrder->status == 'pending')>🟠 Wartend</option>
                    <option value="processing" class="bg-gray-900 text-white" @selected($this->selectedOrder->status == 'processing')>🔵 In Bearbeitung</option>
                    <option value="shipped" class="bg-gray-900 text-white" @selected($this->selectedOrder->status == 'shipped')>🟣 Versendet</option>
                    <option value="completed" class="bg-gray-900 text-white" @selected($this->selectedOrder->status == 'completed')>🟢 Abgeschlossen</option>
                    <option value="cancelled" class="bg-gray-900 text-white" @selected($this->selectedOrder->status == 'cancelled')>🔴 Storniert</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>
            </div>
        </div>

    </div>
</div>
