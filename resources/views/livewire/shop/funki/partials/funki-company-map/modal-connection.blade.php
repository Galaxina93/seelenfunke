<div x-show="$wire.showEdgeForm" x-cloak
     class="absolute inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-fade-in">
    <div class="bg-gray-900 rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-10 shadow-[0_0_50px_rgba(0,0,0,0.8)] w-full max-w-lg border border-gray-800 transform transition-all animate-modal-up"
         @click.away="$wire.set('showEdgeForm', false)">

        <h4 class="text-xl sm:text-2xl font-serif font-bold text-white mb-6 border-b border-gray-800 pb-4 tracking-tight">Verbindung herstellen</h4>

        <div class="space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Start (Von)</label>
                    <div class="relative">
                        <select wire:model="newEdge.source_id" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm py-3.5 px-4 font-bold focus:ring-2 focus:ring-primary/30 outline-none shadow-inner cursor-pointer appearance-none transition-all">
                            <option value="">Wählen...</option>
                            @foreach($nodes as $n)
                                <option value="{{ $n['id'] }}">{{ $n['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500"><x-heroicon-m-chevron-down class="w-4 h-4"/></div>
                    </div>
                </div>
                <div>
                    <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Ziel (Nach)</label>
                    <div class="relative">
                        <select wire:model="newEdge.target_id" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm py-3.5 px-4 font-bold focus:ring-2 focus:ring-primary/30 outline-none shadow-inner cursor-pointer appearance-none transition-all">
                            <option value="">Wählen...</option>
                            @foreach($nodes as $n)
                                <option value="{{ $n['id'] }}">{{ $n['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500"><x-heroicon-m-chevron-down class="w-4 h-4"/></div>
                    </div>
                </div>
            </div>
            <div>
                <label class="text-[9px] font-black text-primary uppercase tracking-widest block mb-2 ml-1">Beschriftung (Kurz)</label>
                <input type="text" wire:model="newEdge.label" placeholder="z.B. API Sync" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl focus:ring-2 focus:ring-primary/30 text-sm font-bold py-3.5 px-4 outline-none shadow-inner transition-all placeholder-gray-600">
            </div>
            <div>
                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Beschreibung (Tooltip)</label>
                <input type="text" wire:model="newEdge.description" placeholder="Was genau passiert auf dieser Verbindung?" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl focus:ring-2 focus:ring-primary/30 text-sm py-3.5 px-4 outline-none shadow-inner transition-all placeholder-gray-600">
            </div>
            <div>
                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Status der Linie</label>
                <div class="relative">
                    <select wire:model="newEdge.status" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm py-3.5 px-4 font-bold focus:ring-2 focus:ring-primary/30 outline-none shadow-inner cursor-pointer appearance-none transition-all">
                        <option value="active">Aktiv (Gold pulsierend)</option>
                        <option value="planned">Geplant (Gestrichelt Orange)</option>
                        <option value="inactive">Inaktiv (Rot)</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500"><x-heroicon-m-chevron-down class="w-4 h-4"/></div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-800">
                <button wire:click="$set('showEdgeForm', false)" class="px-5 py-3 text-gray-400 font-bold text-[10px] uppercase tracking-widest hover:text-white bg-gray-950 hover:bg-gray-800 rounded-xl transition-all border border-gray-800">Abbrechen</button>
                <button wire:click="createEdge" class="px-6 py-3 bg-primary text-gray-900 font-black text-[10px] uppercase tracking-widest rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-primary-dark transition-all hover:scale-[1.02] active:scale-95">Verbinden</button>
            </div>
        </div>
    </div>
</div>
