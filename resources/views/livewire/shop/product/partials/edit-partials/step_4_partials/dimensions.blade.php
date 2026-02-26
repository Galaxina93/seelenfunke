<div class="bg-gray-900/50 backdrop-blur-md p-6 sm:p-8 rounded-[2rem] border border-gray-800 shadow-inner">
    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-primary shadow-[0_0_8px_currentColor]"></span>
        Echte Produktmaße (1:1 Skalierung)
    </h3>
    <p class="text-xs text-gray-500 mb-6 font-medium">Wichtig für die korrekte spätere PDF-Druckgenerierung.</p>

    @php
        $dimInputClass = "w-full px-4 py-3.5 text-center rounded-xl border border-gray-800 bg-gray-950 text-white font-mono font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner outline-none";
        $dimLabelClass = "block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2";
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="relative group">
            <label class="{{ $dimLabelClass }}">Breite (X)</label>
            <input type="number" wire:model.blur="width" class="{{ $dimInputClass }}">
            <span class="absolute right-4 top-10 text-[9px] font-black text-gray-600 pointer-events-none uppercase tracking-widest">mm</span>
        </div>
        <div class="relative group">
            <label class="{{ $dimLabelClass }}">Höhe (Y)</label>
            <input type="number" wire:model.blur="height" class="{{ $dimInputClass }}">
            <span class="absolute right-4 top-10 text-[9px] font-black text-gray-600 pointer-events-none uppercase tracking-widest">mm</span>
        </div>
        <div class="relative group">
            <label class="{{ $dimLabelClass }}">Tiefe (Z)</label>
            <input type="number" wire:model.blur="length" class="{{ $dimInputClass }}">
            <span class="absolute right-4 top-10 text-[9px] font-black text-gray-600 pointer-events-none uppercase tracking-widest">mm</span>
        </div>
    </div>

    <div class="mt-6 pt-6 border-t border-gray-800">
        <div class="relative group max-w-xs">
            <label class="{{ $dimLabelClass }}">Gewicht</label>
            <input type="number" wire:model.blur="weight" class="{{ $dimInputClass }}">
            <span class="absolute right-4 top-10 text-[9px] font-black text-gray-600 pointer-events-none uppercase tracking-widest">g</span>
        </div>
    </div>
</div>
