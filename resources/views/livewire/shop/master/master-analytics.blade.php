<div class="p-4 md:p-6 bg-transparent min-h-screen space-y-8 relative z-10">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @include('livewire.shop.master.master-analytics-partials.header')

    @if($showMission)
        @include('livewire.shop.master.master-analytics-partials.master-mission-banner')
    @endif

    @include('livewire.shop.master.master-analytics-partials.master_scores')

    @if($widgetConfig['capacities'] ?? true)
        <livewire:shop.master.master-shop-capacity />
        <livewire:shop.master.master-storage-capacity />
    @endif

    @if($widgetConfig['profit'] ?? true)
        @include('livewire.shop.master.master-analytics-partials.profit')
    @endif

    @if($widgetConfig['ecommerce'] ?? true)
        @include('livewire.shop.master.master-analytics-partials.charts')
    @endif

    @if($widgetConfig['traffic'] ?? true)
        <div class="border-t border-gray-800 pt-8 mt-8">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                @include('livewire.shop.master.master-analytics-partials.traffic')
            </div>
        </div>
    @endif

    @if($widgetConfig['visitors'] ?? true)
        <div class="border-t border-gray-800 pt-8 mt-8">
            @include('livewire.shop.master.master-analytics-partials.customers')
        </div>
    @endif

    {{-- WIDGETS CONFIG MODAL --}}
    @if($showWidgetModal)
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm shadow-2xl z-50 flex items-start pt-20 justify-center border border-gray-800 p-4" x-data @keydown.escape.window="$wire.set('showWidgetModal', false)">
            <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-md overflow-hidden relative shadow-2xl">
                <div class="p-6 border-b border-gray-800 flex justify-between items-center bg-gray-900/50">
                    <h3 class="text-white font-bold tracking-widest uppercase text-sm flex items-center gap-2">
                        <x-heroicon-o-adjustments-horizontal class="w-5 h-5 text-[var(--theme-color)]" />
                        Widgets verwalten
                    </h3>
                    <button wire:click="$set('showWidgetModal', false)" class="text-gray-500 hover:text-white transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    {{-- Capacity --}}
                    <label class="flex items-center justify-between p-3 rounded-xl border border-gray-800 bg-gray-950/50 hover:bg-gray-800/50 cursor-pointer transition-colors">
                        <span class="text-sm font-bold text-gray-300">Speicher & Produktions-Last</span>
                        <input type="checkbox" wire:model.live="widgetConfig.capacities" class="form-checkbox bg-gray-900 border-gray-700 text-[var(--theme-color)] focus:ring-[var(--theme-color)] rounded">
                    </label>
                    {{-- Profit --}}
                    <label class="flex items-center justify-between p-3 rounded-xl border border-gray-800 bg-gray-950/50 hover:bg-gray-800/50 cursor-pointer transition-colors">
                        <span class="text-sm font-bold text-gray-300">Gewinn-Entwicklung</span>
                        <input type="checkbox" wire:model.live="widgetConfig.profit" class="form-checkbox bg-gray-900 border-gray-700 text-[var(--theme-color)] focus:ring-[var(--theme-color)] rounded">
                    </label>
                    {{-- eCommerce --}}
                    <label class="flex items-center justify-between p-3 rounded-xl border border-gray-800 bg-gray-950/50 hover:bg-gray-800/50 cursor-pointer transition-colors">
                        <span class="text-sm font-bold text-gray-300">E-Commerce Einblicke</span>
                        <input type="checkbox" wire:model.live="widgetConfig.ecommerce" class="form-checkbox bg-gray-900 border-gray-700 text-[var(--theme-color)] focus:ring-[var(--theme-color)] rounded">
                    </label>
                    {{-- Traffic --}}
                    <label class="flex items-center justify-between p-3 rounded-xl border border-gray-800 bg-gray-950/50 hover:bg-gray-800/50 cursor-pointer transition-colors">
                        <span class="text-sm font-bold text-gray-300">Besucher & Analysen</span>
                        <input type="checkbox" wire:model.live="widgetConfig.traffic" class="form-checkbox bg-gray-900 border-gray-700 text-[var(--theme-color)] focus:ring-[var(--theme-color)] rounded">
                    </label>
                    {{-- Customers --}}
                    <label class="flex items-center justify-between p-3 rounded-xl border border-gray-800 bg-gray-950/50 hover:bg-gray-800/50 cursor-pointer transition-colors">
                        <span class="text-sm font-bold text-gray-300">Kundengewinnung & Wachstum</span>
                        <input type="checkbox" wire:model.live="widgetConfig.visitors" class="form-checkbox bg-gray-900 border-gray-700 text-[var(--theme-color)] focus:ring-[var(--theme-color)] rounded">
                    </label>
                </div>
            </div>
        </div>
    @endif

    {{-- ABANDONED CARTS MODAL --}}
    @if($showAbandonedCarts)
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flexitems-center justify-center p-4 xl:p-10 flex items-center" x-data @keydown.escape.window="$wire.set('showAbandonedCarts', false)">
            <div class="bg-gray-900 border border-gray-700 rounded-3xl w-full max-w-5xl max-h-full overflow-hidden relative shadow-[0_0_50px_rgba(245,158,11,0.1)] flex flex-col">
                <div class="p-6 border-b border-gray-800 flex justify-between items-center bg-gray-950/80">
                    <div>
                        <h3 class="text-white font-bold font-serif text-2xl flex items-center gap-3">
                            <x-heroicon-o-shopping-cart class="w-8 h-8 text-amber-500" />
                            Verlassene Körbe Detailansicht
                        </h3>
                        <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mt-1">Umsatzpotenzial und liegengebliebene Warenkörbe</p>
                    </div>
                    <button wire:click="$set('showAbandonedCarts', false)" class="text-gray-500 bg-gray-800/50 p-2 rounded-full hover:bg-gray-700 hover:text-white transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                    @if(isset($stats['abandoned_carts']['details']) && count($stats['abandoned_carts']['details']) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-800">
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Status / Alter</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Warenkorb ID</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Benutzer / Gast</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest text-right">Potenzial</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['abandoned_carts']['details'] as $cart)
                                        <tr class="border-b border-gray-800/50 hover:bg-gray-800/20 transition-colors">
                                            <td class="p-3 align-middle">
                                                <div class="flex items-center gap-2">
                                                    @if($cart['status'] === 'green')
                                                        <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]" title="Kürzlich verlassen"></span>
                                                    @elseif($cart['status'] === 'yellow')
                                                        <span class="w-3 h-3 rounded-full bg-yellow-500 shadow-[0_0_8px_rgba(234,179,8,0.8)]" title="Länger verlassen"></span>
                                                    @else
                                                        <span class="w-3 h-3 rounded-full bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.8)]" title="Sehr alt"></span>
                                                    @endif
                                                    <span class="text-xs font-bold text-gray-400">{{ $cart['age'] }}</span>
                                                </div>
                                            </td>
                                            <td class="p-3 align-middle text-xs font-mono text-gray-500">{{ substr($cart['id'], 0, 8) }}...</td>
                                            <td class="p-3 align-middle">
                                                @if($cart['customer'])
                                                    <div class="font-bold text-sm text-white">{{ $cart['customer'] }}</div>
                                                    @if($cart['email'])
                                                        <div class="text-[10px] text-gray-500">{{ $cart['email'] }}</div>
                                                    @endif
                                                @else
                                                    <span class="text-xs font-black text-gray-500 bg-gray-800 px-2 py-1 rounded">Gast</span>
                                                @endif
                                            </td>
                                            <td class="p-3 align-middle text-right font-black text-amber-500 group relative">
                                                {{ number_format($cart['total'], 2, ',', '.') }} €
                                                <div class="text-[9px] text-gray-500">{{ $cart['items_count'] }} Artikel</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-12 flex flex-col items-center justify-center text-gray-500">
                            <x-heroicon-o-shopping-bag class="w-12 h-12 mb-4 opacity-50" />
                            <p class="font-bold text-sm">Keine verlassenen Warenkörbe gefunden.</p>
                            <p class="text-xs mt-1">Im gewählten Zeitraum gab es keine Abbrüche.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @include('livewire.shop.master.master-analytics-partials.scripts')

</div>
