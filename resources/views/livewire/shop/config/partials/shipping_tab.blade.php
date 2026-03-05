<div x-show="activeTab === 'shipping'" class="space-y-6 md:space-y-8 animate-fade-in" style="display: none;">
    <div class="bg-amber-500/10 border border-amber-500/20 p-5 rounded-[2rem] shadow-inner relative overflow-hidden">
        <div class="absolute -right-4 -top-4 text-amber-500/10 rotate-12 pointer-events-none">
            <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 relative z-10">
            <div class="flex items-center gap-4">
                <div class="p-2.5 bg-amber-500/20 text-amber-400 rounded-xl shadow-inner shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <p class="text-sm text-amber-200/80 font-medium leading-relaxed">
                    Länder werden zentral in der <strong class="text-amber-400 tracking-wide">Versandverwaltung</strong> aktiviert.
                </p>
            </div>
            <a href="{{ route('admin.shipping') }}" class="text-[9px] font-black uppercase tracking-widest bg-amber-500 text-gray-900 px-5 py-2.5 rounded-xl hover:bg-amber-400 hover:scale-105 transition-all shadow-[0_0_15px_rgba(245,158,11,0.2)] shrink-0">Verwalten</a>
        </div>
    </div>

    {{-- Versandkosten & Konditionen --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-white mb-8 flex items-center gap-3 tracking-wide border-b border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            Versandkosten & Konditionen
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 xl:gap-8">
            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Standard Versand</label>
                    @include('components.alerts.info-tooltip', ['key' => 'shipping_cost'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.shipping_cost" class="{{ $inputClass }} !text-lg !font-bold !pr-10">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">€</span>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Versandkostenfrei ab</label>
                    @include('components.alerts.info-tooltip', ['key' => 'shipping_free_threshold'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.shipping_free_threshold" class="{{ $inputClass }} !text-lg !font-bold !pr-10 !text-emerald-400 !border-emerald-500/30 focus:!ring-emerald-500/20 focus:!border-emerald-400">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-emerald-500/50 font-bold">€</span>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-2 ml-1">
                    <label class="{{ $labelClass }} !mb-0 !ml-0">Express-Aufschlag</label>
                    @include('components.alerts.info-tooltip', ['key' => 'express_surcharge'])
                </div>
                <div class="relative">
                    <input type="number" step="0.01" wire:model="settings.express_surcharge" class="{{ $inputClass }} !text-lg !font-bold !pr-10 !text-red-400 !border-red-500/30 focus:!ring-red-500/20 focus:!border-red-400">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-red-500/50 font-bold">€</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
        <h3 class="text-sm sm:text-base font-serif font-bold text-white mb-6 flex items-center gap-3 tracking-wide border-b border-gray-800 pb-4">
            <div class="p-2 rounded-xl bg-gray-800 border border-gray-700 text-gray-400 shadow-inner shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064" /></svg>
            </div>
            Aktive Lieferländer
        </h3>
        <div class="flex flex-wrap gap-3">
            @forelse($this->activeShippingCountries as $code => $name)
                <span class="inline-flex items-center px-4 py-2.5 rounded-xl bg-gray-950 text-gray-300 text-[10px] font-black uppercase tracking-widest border border-gray-800 shadow-inner">
                                <img src="https://flagcdn.com/16x12/{{ strtolower($code) }}.png" class="mr-3 rounded-[2px] opacity-80" alt="{{ $code }}">
                                {{ $name }}
                            </span>
            @empty
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-600 bg-gray-950 px-4 py-3 rounded-xl border border-gray-800 w-full text-center">Keine Länder in den Versandzonen hinterlegt.</p>
            @endforelse
        </div>
    </div>
</div>
