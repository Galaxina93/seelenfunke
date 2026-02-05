{{-- RECHTS: Configurator (FIX: Scrollbar auf den Parent Container) --}}
<div
    class="w-full lg:w-1/2 h-1/2 lg:h-full bg-gray-50 flex flex-col border-l-0 lg:border-l border-gray-200 overflow-hidden">
    <div class="flex-1 p-4 md:p-6 bg-gray-100 h-full overflow-y-auto custom-scrollbar">
        @if($this->previewItem)
            {{-- Karte muss wachsen können, kein overflow-hidden hier! --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex flex-col min-h-0">
                {{-- Header Configurator --}}
                <div
                    class="bg-white border-b border-gray-100 px-4 md:px-6 py-4 flex justify-between items-center shrink-0">
                    <div>
                        <h3 class="font-bold text-gray-800">{{ $this->previewItem->product_name }}</h3>
                        <p class="text-xs text-gray-400">
                            Artikel-ID: {{ $this->previewItem->product_id }}</p>
                    </div>

                    <div class="text-right text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded">
                        Konfiguration anzeigen
                    </div>
                </div>

                {{-- In der orders.blade.php unter der Konfigurations-Vorschau --}}
                @if($this->previewItem->config_fingerprint)
                    <div
                        class="mt-4 flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-100">
                        <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <div class="text-[10px] text-green-800 leading-tight">
                            <p class="font-bold uppercase">Produktkonfiguration – Digitales
                                Echtheits-Siegel</p>
                            <p class="font-mono text-green-600">{{ substr($this->previewItem->config_fingerprint, 0, 16) }}</p>
                            <p class="mt-1 text-green-700">
                                Hinweis: Diese Produktkonfiguration wurde bei der Bestellung eindeutig
                                versiegelt.
                                Nachträgliche Änderungen am Konfigurationszustand sind nicht möglich.
                            </p>
                        </div>
                    </div>
                @endif

                {{-- CONFIGURATOR COMPONENT --}}
                {{-- Wir entfernen h-full, damit es scrollen kann wenn nötig, oder lassen den Browser entscheiden --}}
                <div class="relative">
                    <livewire:shop.configurator
                        :product="$this->previewItem->product"
                        :initialData="$this->previewItem->configuration"
                        :qty="$this->previewItem->quantity"
                        context="preview"
                        :key="'order-conf-'.$this->previewItem->id"
                    />
                </div>
            </div>
        @else
            <div class="h-full flex flex-col items-center justify-center text-gray-400 space-y-4">
                <p class="font-medium">Wähle links eine Position aus.</p>
            </div>
        @endif
    </div>
</div>
