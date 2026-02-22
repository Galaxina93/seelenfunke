<div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
    <h3 class="font-bold text-gray-900 mb-2">Echte Produktmaße (1:1 Skalierung)</h3>
    <p class="text-xs text-gray-500 mb-6">Wichtig für die korrekte spätere PDF-Druckgenerierung.</p>

    <div class="grid grid-cols-3 gap-4">
        <div class="relative group">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Breite (X)</label>
            <input type="number" wire:model.blur="width" class="w-full px-3 py-3 text-center rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm">
            <span class="absolute right-3 top-9 text-[10px] font-bold text-gray-300 pointer-events-none">mm</span>
        </div>
        <div class="relative group">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Höhe (Y)</label>
            <input type="number" wire:model.blur="height" class="w-full px-3 py-3 text-center rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm">
            <span class="absolute right-3 top-9 text-[10px] font-bold text-gray-300 pointer-events-none">mm</span>
        </div>
        <div class="relative group">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Tiefe (Z)</label>
            <input type="number" wire:model.blur="length" class="w-full px-3 py-3 text-center rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm">
            <span class="absolute right-3 top-9 text-[10px] font-bold text-gray-300 pointer-events-none">mm</span>
        </div>
    </div>

    <div class="mt-4 pt-4 border-t border-gray-200">
        <div class="relative group">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Gewicht</label>
            <input type="number" wire:model.blur="weight" class="w-full px-3 py-3 text-center rounded-xl border border-gray-300 bg-white text-gray-900 font-bold focus:border-primary transition-all shadow-sm">
            <span class="absolute right-3 top-9 text-[10px] font-bold text-gray-300 pointer-events-none">g</span>
        </div>
    </div>
</div>
