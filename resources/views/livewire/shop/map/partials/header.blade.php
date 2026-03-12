<div class="px-4 sm:px-8 py-5 flex flex-col lg:flex-row justify-between items-start lg:items-center relative z-20 bg-gray-950/80 backdrop-blur-md border-b border-gray-800 shrink-0 gap-4 shadow-inner">
    <div>
        <h3 class="text-lg sm:text-2xl font-serif font-bold text-white tracking-tight">System Architektur Map</h3>
        
        <div class="mt-2 flex gap-2">
            <button wire:click="switchMap('erp')" class="px-3 py-1 text-[10px] font-black uppercase tracking-widest rounded-full transition-all border {{ $activeMap === 'erp' ? 'bg-primary/20 text-primary border-primary/50 shadow-[0_0_10px_rgba(197,160,89,0.2)]' : 'bg-gray-900 text-gray-400 border-gray-800 hover:text-white' }}">
                ERP Ökosystem
            </button>
            <button wire:click="switchMap('ai')" class="px-3 py-1 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest rounded-full transition-all border {{ $activeMap === 'ai' ? 'bg-indigo-500/20 text-indigo-400 border-indigo-500/50 shadow-[0_0_10px_rgba(99,102,241,0.2)]' : 'bg-gray-900 text-gray-400 border-gray-800 hover:text-white' }}">
                KI-Architektur
                @if($activeMap === 'ai')
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                @endif
            </button>
        </div>
    </div>

    {{-- Button Group: Scrollbar on mobile --}}
    <div class="flex overflow-x-auto no-scrollbar w-full lg:w-auto gap-2 sm:gap-3 pb-1 lg:pb-0">
        <button wire:click="checkApiStatuses" wire:loading.attr="disabled" class="shrink-0 px-4 py-3 bg-gray-900 border border-gray-800 text-emerald-400 rounded-xl text-[9px] font-black uppercase tracking-widest hover:border-emerald-500/50 hover:bg-emerald-500/10 transition-all shadow-inner flex items-center justify-center gap-2 active:scale-95 group">
            <span wire:loading.remove wire:target="checkApiStatuses" class="flex items-center gap-2">
                <x-heroicon-m-signal class="w-4 h-4 group-hover:animate-pulse" /> Live Ping
            </span>
            <span wire:loading wire:target="checkApiStatuses" class="flex items-center gap-2 text-gray-500">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                Prüfe...
            </span>
        </button>

        <button @click="resetView()" class="shrink-0 px-4 py-3 bg-gray-900 border border-gray-800 text-gray-400 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-gray-800 hover:text-white transition-all shadow-inner flex items-center justify-center gap-2 active:scale-95">
            <x-heroicon-m-arrows-pointing-in class="w-4 h-4" /> Reset Ansicht
        </button>

        <button wire:click="$toggle('showEdgeForm')" class="shrink-0 px-4 py-3 bg-gray-900 border border-gray-700 text-gray-300 rounded-xl text-[9px] font-black uppercase tracking-widest hover:border-primary/50 hover:text-primary transition-all shadow-inner flex items-center justify-center gap-2 active:scale-95">
            <x-heroicon-m-arrows-right-left class="w-4 h-4" /> Verbindung
        </button>

        <button wire:click="$toggle('showNodeForm')" class="shrink-0 px-5 py-3 bg-primary text-gray-900 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-primary-dark transition-all shadow-[0_0_15px_rgba(197,160,89,0.3)] hover:scale-[1.02] flex items-center justify-center gap-2">
            <x-heroicon-m-plus class="w-4 h-4 stroke-2" /> Knoten
        </button>
    </div>
</div>
