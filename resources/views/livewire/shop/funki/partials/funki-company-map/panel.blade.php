<div x-show="$wire.showNodePanel" x-cloak
     class="absolute inset-0 z-50 flex items-stretch justify-end bg-slate-900/40 backdrop-blur-sm">
    <div class="bg-white w-full max-w-3xl h-full flex flex-col shadow-2xl border-l border-slate-100 overflow-hidden"
         @click.away="$wire.closeNodePanel()">

        {{-- Panel Header --}}
        @if($activePanelNode)
            <div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-slate-900 flex items-center justify-center">
                        <x-heroicon-s-sparkles class="w-7 h-7 text-primary" />
                    </div>
                    <div>
                        <h4 class="text-xl font-serif font-bold text-slate-900">{{ $activePanelNode['label'] }}</h4>
                        <p class="text-xs text-slate-400 font-mono uppercase">{{ $activePanelNode['description'] ?? '' }}</p>
                    </div>
                </div>
                <button wire:click="closeNodePanel" class="w-10 h-10 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">
                    <x-heroicon-m-x-mark class="w-5 h-5 text-slate-600" />
                </button>
            </div>

            {{-- Panel Inhalt: dynamisch je nach component_key --}}
            <div class="flex-1 overflow-y-auto p-8">
                @switch($activePanelNode['component_key'] ?? '')

                    @case('products')
                        <livewire:shop.funki.panels.products-panel :key="'panel-products'" />
                        @break

                    @case('orders')
                        <livewire:shop.funki.panels.orders-panel :key="'panel-orders'" />
                        @break

                    @case('customers')
                        <livewire:shop.funki.panels.customers-panel :key="'panel-customers'" />
                        @break

                    @case('finances')
                        <livewire:shop.funki.panels.finances-panel :key="'panel-finances'" />
                        @break

                    @case('analytics')
                        <livewire:shop.funki.panels.analytics-panel :key="'panel-analytics'" />
                        @break

                    @case('settings')
                        <livewire:shop.funki.panels.settings-panel :key="'panel-settings'" />
                        @break

                    @case('shipping')
                        <livewire:shop.funki.panels.shipping-panel :key="'panel-shipping'" />
                        @break

                    @case('api_logs')
                        <livewire:shop.funki.panels.api-logs-panel :key="'panel-api-logs'" />
                        @break

                    @default
                        {{-- Fallback: Node-Info ohne Panel --}}
                        <div class="text-center py-20 text-slate-400">
                            <x-heroicon-o-cube class="w-16 h-16 mx-auto mb-4 opacity-30" />
                            <p class="font-bold text-lg mb-2 text-slate-500">Kein Panel verknüpft</p>
                            <p class="text-sm">Bearbeite diesen Knoten und weise ihm ein Panel zu, um hier Daten anzuzeigen.</p>
                            <button wire:click="$set('showNodePanel', false)" wire:then="openEditForm('{{ $activePanelNode['id'] }}')"
                                    class="mt-6 px-6 py-2.5 bg-primary text-white rounded-xl text-xs font-bold uppercase tracking-widest hover:bg-primary-dark transition">
                                Jetzt Panel zuweisen
                            </button>
                        </div>
                @endswitch
            </div>
        @endif
    </div>
</div>
