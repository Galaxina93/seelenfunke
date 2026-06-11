<div class="bg-white border border-gray-200/80 rounded-3xl p-5 shadow-sm relative overflow-hidden group">
    {{-- Decorative Background Glow --}}
    <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all duration-700"></div>

    <div class="space-y-4 relative z-10">
        <div>
            <h3 class="text-sm font-serif font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Gutschein-Wert prüfen
            </h3>
            <p class="text-[11px] text-gray-500 mt-1 leading-relaxed">
                Gib deinen Gutscheincode ein, um den aktuellen Wert und das Ablaufdatum abzufragen.
            </p>
        </div>

        <form wire:submit.prevent="checkBalance" class="space-y-2">
            <div class="flex gap-2">
                <input type="text"
                       wire:model="code"
                       placeholder="z. B. SEELENFUNKE-XXXX-XXXX"
                       class="flex-1 bg-white border border-gray-300 rounded-xl py-2 px-3 text-xs uppercase tracking-wider font-mono text-center text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20">
                
                <button type="submit"
                        class="shrink-0 px-4 py-2 bg-primary text-white font-serif font-bold text-xs rounded-xl hover:bg-primary/90 hover:scale-105 active:scale-95 transition-all duration-300 shadow-md shadow-primary/10">
                    Prüfen
                </button>
            </div>
            @error('code') <span class="text-red-600 text-[10px] mt-1 block pl-1">⚠️ {{ $message }}</span> @enderror
        </form>

        @if($result)
            <div class="p-3.5 rounded-xl bg-primary/5 border border-primary/20 animate-fade-in flex justify-between items-center text-xs">
                <div>
                    <p class="text-[9px] uppercase tracking-wider text-gray-500 font-semibold mb-0.5">Aktuelles Guthaben</p>
                    <span class="text-base font-serif font-bold text-primary">{{ $result['balance'] }}</span>
                </div>
                <div class="text-right">
                    <p class="text-[9px] text-gray-400 mb-0.5">Gültig bis</p>
                    <span class="font-semibold text-gray-700 text-xs">{{ $result['valid_until'] }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
