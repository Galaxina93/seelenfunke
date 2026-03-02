<div x-show="$wire.showNodeForm" x-cloak class="absolute inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-fade-in">
    <div class="bg-gray-900 rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-10 shadow-[0_0_50px_rgba(0,0,0,0.8)] border border-gray-800 w-full max-w-lg transform transition-all max-h-[90vh] overflow-y-auto custom-scrollbar animate-modal-up" @click.away="$wire.set('showNodeForm', false)">
        <h4 class="text-xl sm:text-2xl font-serif font-bold text-white mb-6 border-b border-gray-800 pb-4 tracking-tight">Neuen Knoten erstellen</h4>
        <div class="space-y-5">
            <div>
                <label class="text-[9px] font-black text-primary uppercase tracking-widest block mb-2 ml-1">Name des Systems</label>
                <input type="text" wire:model="newNode.label" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-bold py-3.5 px-4 outline-none shadow-inner transition-all">
            </div>
            <div>
                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Beschreibung</label>
                <input type="text" wire:model="newNode.description" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm py-3.5 px-4 outline-none shadow-inner transition-all">
            </div>
            <div>
                <label class="text-[9px] font-black text-blue-400 uppercase tracking-widest block mb-2 ml-1">URL / Link (Optional)</label>
                <input type="url" wire:model="newNode.link" placeholder="https://..." class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 text-sm py-3.5 px-4 font-mono outline-none shadow-inner transition-all placeholder-gray-700">
            </div>
            <div>
                <label class="text-[9px] font-black text-emerald-500 uppercase tracking-widest block mb-2 ml-1">Panel / Modul (Optional)</label>
                <select wire:model="newNode.component_key" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm py-3.5 px-4 cursor-pointer font-bold outline-none shadow-inner appearance-none transition-all">
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
            </div>
            <div class="grid grid-cols-2 gap-4 border-t border-gray-800 pt-5">
                <div>
                    <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Kategorie</label>
                    <select wire:model="newNode.type" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm py-3 px-4 cursor-pointer font-bold outline-none shadow-inner">
                        <option value="core">Zentrum / ERP</option>
                        <option value="sales">Verkauf / Shop</option>
                        <option value="finance">Finanzen / Bank</option>
                        <option value="api">API / Technik</option>
                        <option value="default">Standard</option>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2 ml-1">Status</label>
                    <select wire:model="newNode.status" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm py-3 px-4 cursor-pointer font-bold outline-none shadow-inner">
                        <option value="active">Aktiv</option>
                        <option value="planned">Geplant</option>
                        <option value="inactive">Inaktiv</option>
                    </select>
                </div>
            </div>
            <div class="pt-4 border-t border-gray-800">
                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-4 ml-1">Symbol / Logo wählen</label>
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach(['datev', 'dhl', 'etsy', 'finom', 'google', 'mittwald', 'stripe', 'firebase'] as $brand)
                        <button wire:click="$set('newNode.icon', '{{ $brand }}')" class="w-12 h-12 sm:w-14 sm:h-14 p-2 rounded-2xl border-2 transition-all flex items-center justify-center {{ $newNode['icon'] === $brand ? 'bg-primary/10 border-primary shadow-glow' : 'bg-gray-950 border-gray-800 shadow-inner hover:border-gray-600' }}">
                            <img src="/images/projekt/brands/{{ $brand }}.svg" alt="{{ $brand }}" class="w-full h-full object-contain">
                        </button>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach(['cube', 'sparkles', 'shopping-bag', 'shopping-cart', 'credit-card', 'currency-euro', 'building-library', 'document-text', 'server', 'device-phone-mobile', 'globe-alt', 'truck'] as $icon)
                        <button wire:click="$set('newNode.icon', '{{ $icon }}')" class="w-10 h-10 sm:w-12 sm:h-12 p-2.5 rounded-xl border-2 transition-all flex items-center justify-center {{ $newNode['icon'] === $icon ? 'bg-primary/10 text-primary border-primary shadow-glow' : 'bg-gray-950 border-gray-800 text-gray-500 shadow-inner hover:text-white' }}">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="w-full h-full" />
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-800">
                <button wire:click="$set('showNodeForm', false)" class="px-5 py-3 text-gray-400 font-bold text-[10px] uppercase tracking-widest hover:text-white bg-gray-950 hover:bg-gray-800 rounded-xl transition-all border border-gray-800">Abbrechen</button>
                <button wire:click="createNode" class="px-6 py-3 bg-primary text-gray-900 font-black text-[10px] uppercase tracking-widest rounded-xl shadow-glow hover:bg-primary-dark transition-all active:scale-95">Erstellen</button>
            </div>
        </div>
    </div>
</div>
