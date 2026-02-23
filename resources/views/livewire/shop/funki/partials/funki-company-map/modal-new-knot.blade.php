<div x-show="$wire.showNodeForm" x-cloak
     class="absolute inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white rounded-[2rem] p-8 shadow-2xl w-full max-w-lg border border-slate-100 transform transition-all max-h-[90vh] overflow-y-auto"
         @click.away="$wire.set('showNodeForm', false)">
        <h4 class="text-2xl font-serif font-bold text-slate-900 mb-6 border-b border-slate-100 pb-4">Neuen Knoten erstellen</h4>
        <div class="space-y-5">
            <div>
                <label class="text-xs font-bold text-primary uppercase tracking-widest block mb-1.5">Name des Systems</label>
                <input type="text" wire:model="newNode.label" class="w-full bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/30 text-sm font-bold py-3.5 px-4">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Beschreibung</label>
                <input type="text" wire:model="newNode.description" class="w-full bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/30 text-sm py-3.5 px-4">
            </div>
            <div>
                <label class="text-xs font-bold text-blue-500 uppercase tracking-widest block mb-1.5">URL / Link (Optional)</label>
                <input type="url" wire:model="newNode.link" placeholder="https://..." class="w-full bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-300 text-sm py-3.5 px-4 font-mono">
            </div>
            <div>
                <label class="text-xs font-bold text-emerald-600 uppercase tracking-widest block mb-1.5">Panel / Modul (Optional)</label>
                <select wire:model="newNode.component_key" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm py-3.5 px-4 cursor-pointer font-bold focus:ring-2 focus:ring-emerald-300">
                    <option value="">— Kein Panel —</option>
                    <option value="products">📦 Produkte</option>
                    <option value="orders">🛒 Bestellungen</option>
                    <option value="customers">👤 Kunden</option>
                    <option value="finances">💶 Finanzen</option>
                    <option value="analytics">📊 Analytics</option>
                    <option value="settings">⚙️ Einstellungen</option>
                    <option value="shipping">🚚 Versand</option>
                    <option value="api_logs">🔌 API Logs</option>
                </select>
                <p class="text-[10px] text-slate-400 mt-1">Wenn gesetzt, kann der Knoten ein interaktives Daten-Panel öffnen.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Kategorie</label>
                    <select wire:model="newNode.type" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm py-3.5 px-4 cursor-pointer font-bold">
                        <option value="core">Zentrum / ERP</option>
                        <option value="sales">Verkauf / Shop</option>
                        <option value="finance">Finanzen / Bank</option>
                        <option value="api">API / Technik</option>
                        <option value="default">Standard</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Status</label>
                    <select wire:model="newNode.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm py-3.5 px-4 cursor-pointer font-bold">
                        <option value="active">Aktiv</option>
                        <option value="planned">Geplant</option>
                        <option value="inactive">Inaktiv</option>
                    </select>
                </div>
            </div>
            <div class="pt-2">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest block mb-3">Symbol / Logo wählen</label>
                <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Spezifische Marken</p>
                <div class="flex flex-wrap gap-3 mb-4">
                    @foreach(['datev', 'dhl', 'etsy', 'finom', 'google', 'mittwald', 'stripe', 'firebase'] as $brand)
                        <button wire:click="$set('newNode.icon', '{{ $brand }}')"
                                class="w-14 h-14 p-2 rounded-xl border-2 transition-all flex items-center justify-center {{ $newNode['icon'] === $brand ? 'bg-primary/5 border-primary shadow-md' : 'bg-white border-slate-200 hover:border-primary/50' }}">
                            <img src="/images/projekt/brands/{{ $brand }}.svg" alt="{{ $brand }}" class="w-full h-full object-contain">
                        </button>
                    @endforeach
                </div>
                <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Allgemeine Icons</p>
                <div class="flex flex-wrap gap-3">
                    @foreach(['cube', 'sparkles', 'shopping-bag', 'shopping-cart', 'credit-card', 'currency-euro', 'building-library', 'document-text', 'server', 'device-phone-mobile', 'globe-alt', 'truck'] as $icon)
                        <button wire:click="$set('newNode.icon', '{{ $icon }}')"
                                class="w-12 h-12 p-2 rounded-xl border-2 transition-all flex items-center justify-center {{ $newNode['icon'] === $icon ? 'bg-primary/5 text-primary border-primary shadow-md' : 'bg-white border-slate-200 text-slate-400 hover:border-primary/50' }}">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="w-6 h-6" />
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-slate-100">
                <button wire:click="$set('showNodeForm', false)" class="px-6 py-3 text-slate-500 font-bold text-sm hover:bg-slate-100 rounded-xl transition">Abbrechen</button>
                <button wire:click="createNode" class="px-8 py-3 bg-primary text-white font-bold text-sm uppercase tracking-widest rounded-xl shadow-lg shadow-primary/30 hover:bg-primary-dark transition transform active:scale-95">Erstellen</button>
            </div>
        </div>
    </div>
</div>
