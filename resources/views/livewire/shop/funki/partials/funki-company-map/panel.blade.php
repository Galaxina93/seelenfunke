<div x-show="$wire.showNodePanel" x-cloak
     class="absolute inset-0 z-50 flex items-stretch justify-end bg-black/60 backdrop-blur-md">
    <div class="bg-gray-950 w-full sm:max-w-md md:max-w-2xl lg:max-w-3xl h-full flex flex-col shadow-[[-20px_0_50px_rgba(0,0,0,0.8)]] border-l border-gray-800 overflow-hidden transform transition-transform animate-slide-in-right"
         @click.away="$wire.closeNodePanel()">

        {{-- Panel Header --}}
        @if($activePanelNode)
            <div class="px-6 sm:px-8 pt-8 pb-6 border-b border-gray-800 bg-gray-900/80 flex items-center justify-between shrink-0 shadow-inner">
                <div class="flex items-center gap-4 sm:gap-5 min-w-0">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-gray-950 border border-gray-800 shadow-inner flex items-center justify-center shrink-0">
                        <x-heroicon-s-sparkles class="w-6 h-6 sm:w-7 sm:h-7 text-primary drop-shadow-[0_0_10px_rgba(197,160,89,0.5)]" />
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-xl sm:text-2xl font-serif font-bold text-white tracking-tight truncate">{{ $activePanelNode['label'] }}</h4>
                        <p class="text-[9px] sm:text-[10px] text-gray-500 font-black uppercase tracking-[0.2em] mt-1 truncate">{{ $activePanelNode['description'] ?? 'System Detailansicht' }}</p>
                    </div>
                </div>
                <button wire:click="closeNodePanel" class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 rounded-full bg-gray-950 border border-gray-800 hover:bg-red-500/10 hover:text-red-500 hover:border-red-500/30 flex items-center justify-center transition-all text-gray-400 shadow-inner">
                    <x-heroicon-m-x-mark class="w-5 h-5 sm:w-6 sm:h-6" />
                </button>
            </div>

            {{-- Panel Inhalt --}}
            <div class="flex-1 overflow-y-auto p-6 sm:p-8 custom-scrollbar relative bg-gray-950/40">

                {{-- NEUES FEATURE: SYSTEM HEALTH & ENV STATUS --}}
                @if(!empty($envStatus))
                    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-inner mb-8">
                        <h5 class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-4">Environment Keys Check</h5>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($envStatus as $key => $isSet)
                                <div class="flex items-center justify-between bg-gray-950 px-4 py-3 rounded-xl border border-gray-800 shadow-inner">
                                    <span class="text-[10px] font-mono text-gray-300">{{ $key }}</span>
                                    @if($isSet)
                                        <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full shadow-[0_0_8px_#10b981]"></span>
                                    @else
                                        <span class="w-2.5 h-2.5 bg-red-500 rounded-full shadow-[0_0_8px_#ef4444] animate-pulse"></span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @switch($activePanelNode['component_key'] ?? '')

                    @case('products') <livewire:shop.funki.panels.products-panel :key="'panel-products'" /> @break
                    @case('orders')   <livewire:shop.funki.panels.orders-panel :key="'panel-orders'" /> @break
                    @case('customers')<livewire:shop.funki.panels.customers-panel :key="'panel-customers'" /> @break
                    @case('finances') <livewire:shop.funki.panels.finances-panel :key="'panel-finances'" /> @break
                    @case('analytics')<livewire:shop.funki.panels.analytics-panel :key="'panel-analytics'" /> @break
                    @case('settings') <livewire:shop.funki.panels.settings-panel :key="'panel-settings'" /> @break
                    @case('shipping') <livewire:shop.funki.panels.shipping-panel :key="'panel-shipping'" /> @break
                    @case('api_logs') <livewire:shop.funki.panels.api-logs-panel :key="'panel-api-logs'" /> @break

                    @default
                        {{-- Fallback --}}
                        <div class="flex flex-col items-center justify-center h-full py-20 text-gray-500 text-center">
                            <div class="w-24 h-24 bg-gray-900 rounded-full border border-gray-800 flex items-center justify-center shadow-inner mb-6">
                                <x-heroicon-o-cube class="w-10 h-10 text-gray-700" />
                            </div>
                            <p class="font-serif font-bold text-2xl text-white mb-3">Kein Modul verknüpft</p>
                            <p class="text-sm font-medium text-gray-500 max-w-xs leading-relaxed">Bearbeite diesen Knoten und weise ihm ein Modul zu, um hier Daten anzuzeigen.</p>
                            <button wire:click="$set('showNodePanel', false)" wire:then="openEditForm('{{ $activePanelNode['id'] }}')"
                                    class="mt-8 px-6 py-3 bg-gray-900 border border-gray-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:border-primary hover:text-primary transition-all shadow-lg">
                                Jetzt zuweisen
                            </button>
                        </div>
                @endswitch
            </div>
        @endif
    </div>
</div>

<style>
    .animate-slide-in-right { animation: slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }
    @media (max-width: 640px) {
        .animate-slide-in-right { animation: slideInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideInUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
    }
</style>
