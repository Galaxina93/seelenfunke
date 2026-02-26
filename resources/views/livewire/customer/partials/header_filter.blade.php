{{-- FILTER BEREICH --}}
<div class="px-6 sm:px-8 py-6 bg-gray-900/90 backdrop-blur-xl border-b border-gray-800 flex flex-col lg:flex-row justify-between items-center gap-5 relative z-10 shadow-lg">
    <div class="flex bg-gray-950 p-1.5 rounded-xl border border-gray-800 w-full lg:w-auto overflow-x-auto no-scrollbar shadow-inner">
        <button wire:click="$set('filterType', 'all')" class="flex-1 sm:flex-none px-5 py-2.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all whitespace-nowrap {{ $filterType === 'all' ? 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'text-gray-500 hover:text-white' }}">Alle Items</button>
        <button wire:click="$set('filterType', 'background')" class="flex-1 sm:flex-none px-5 py-2.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all whitespace-nowrap {{ $filterType === 'background' ? 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'text-gray-500 hover:text-white' }}">Hintergründe</button>
        <button wire:click="$set('filterType', 'frame')" class="flex-1 sm:flex-none px-5 py-2.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all whitespace-nowrap {{ $filterType === 'frame' ? 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'text-gray-500 hover:text-white' }}">Rahmen</button>
        <button wire:click="$set('filterType', 'skin')" class="flex-1 sm:flex-none px-5 py-2.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all whitespace-nowrap {{ $filterType === 'skin' ? 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'text-gray-500 hover:text-white' }}">Skins</button>
    </div>

    <div class="flex w-full lg:w-auto gap-4 group relative">
        <select wire:model.live="filterRarity" class="w-full bg-gray-950 border border-gray-800 text-white text-[10px] font-black uppercase tracking-widest rounded-xl px-5 py-3 focus:ring-2 focus:ring-primary/30 focus:border-primary cursor-pointer outline-none shadow-inner appearance-none pr-10 transition-colors hover:border-gray-700">
            <option value="all">Alle Seltenheiten</option>
            <option value="common">Gewöhnlich</option>
            <option value="rare">Selten</option>
            <option value="epic">Episch</option>
            <option value="legendary">Legendär</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 group-focus-within:text-primary transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
        </div>
    </div>
</div>
