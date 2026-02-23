<div class="px-6 lg:px-10 pt-6 pb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center relative z-20 bg-white/90 backdrop-blur-md border-b border-slate-100 shrink-0 gap-4">
    <div>
        <h3 class="text-xl lg:text-2xl font-serif font-bold text-slate-900">Company Architecture Map</h3>
        <p class="text-[10px] lg:text-xs font-mono text-slate-400 mt-1 uppercase tracking-tighter">Live Ökosystem & API Schnittstellen</p>
    </div>
    <div class="flex flex-wrap gap-2 sm:gap-3 w-full sm:w-auto">
        <button @click="resetView()" class="flex-1 sm:flex-none px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-widest hover:bg-slate-200 hover:text-slate-900 transition-colors shadow-sm flex items-center justify-center gap-1.5" title="Ansicht zentrieren">
            <x-heroicon-m-arrows-pointing-in class="w-4 h-4" /> Ansicht zentrieren
        </button>
        <button wire:click="$toggle('showEdgeForm')" class="flex-1 sm:flex-none px-4 py-2.5 border border-slate-200 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-widest hover:border-primary hover:text-primary transition-colors bg-white shadow-sm flex items-center justify-center gap-1.5">
            <x-heroicon-m-arrows-right-left class="w-4 h-4" /> Verbindung
        </button>
        <button wire:click="$toggle('showNodeForm')" class="w-full sm:w-auto px-6 py-2.5 bg-primary text-white rounded-xl text-xs font-bold uppercase tracking-widest hover:bg-primary-dark transition-colors shadow-lg shadow-primary/30 flex items-center justify-center gap-1.5">
            <x-heroicon-m-plus class="w-4 h-4" /> Knotenpunkt erstellen
        </button>
    </div>
</div>
