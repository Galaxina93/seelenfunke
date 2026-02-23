<div x-show="$wire.showEdgeForm" x-cloak
     class="absolute inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white rounded-[2rem] p-8 shadow-2xl w-full max-w-lg border border-slate-100 transform transition-all"
         @click.away="$wire.set('showEdgeForm', false)">
        <h4 class="text-2xl font-serif font-bold text-slate-900 mb-6 border-b border-slate-100 pb-4">Verbindung herstellen</h4>
        <div class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Start (Von)</label>
                    <select wire:model="newEdge.source_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm py-3.5 px-4 font-bold focus:ring-2 focus:ring-primary/30 cursor-pointer">
                        <option value="">Wählen...</option>
                        @foreach($nodes as $n)
                            <option value="{{ $n['id'] }}">{{ $n['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Ziel (Nach)</label>
                    <select wire:model="newEdge.target_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm py-3.5 px-4 font-bold focus:ring-2 focus:ring-primary/30 cursor-pointer">
                        <option value="">Wählen...</option>
                        @foreach($nodes as $n)
                            <option value="{{ $n['id'] }}">{{ $n['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs font-bold text-primary uppercase tracking-widest block mb-1.5">Beschriftung (Kurz)</label>
                <input type="text" wire:model="newEdge.label" placeholder="z.B. API Sync" class="w-full bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/30 text-sm font-bold py-3.5 px-4">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Beschreibung (Tooltip)</label>
                <input type="text" wire:model="newEdge.description" placeholder="Was genau passiert auf dieser Verbindung?" class="w-full bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/30 text-sm py-3.5 px-4">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Status der Linie</label>
                <select wire:model="newEdge.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm py-3.5 px-4 font-bold focus:ring-2 focus:ring-primary/30 cursor-pointer">
                    <option value="active">Aktiv (Gold pulsierend)</option>
                    <option value="planned">Geplant (Gestrichelt Orange)</option>
                    <option value="inactive">Inaktiv (Rot)</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-100">
                <button wire:click="$set('showEdgeForm', false)" class="px-6 py-3 text-slate-500 font-bold text-sm hover:bg-slate-100 rounded-xl transition">Abbrechen</button>
                <button wire:click="createEdge" class="px-8 py-3 bg-primary text-white font-bold text-sm uppercase tracking-widest rounded-xl shadow-lg shadow-primary/30 hover:bg-primary-dark transition transform active:scale-95">Verbinden</button>
            </div>
        </div>
    </div>
</div>
