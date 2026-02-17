<div class="w-full lg:w-1/2 h-1/2 lg:h-full bg-gray-50 flex flex-col border-l-0 lg:border-l border-gray-200">
    <div class="flex-1 p-6 h-full overflow-y-auto custom-scrollbar">
        @if($previewItem)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex flex-col overflow-hidden mb-6">

                {{-- Preview Header --}}
                <div class="bg-white border-b border-gray-100 px-6 py-4 shrink-0 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-gray-900">{{ $previewItem->product_name }}</h3>
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest">Konfigurations-Vorschau</p>
                    </div>
                    @if($isDigitalItem)
                        <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-1 rounded uppercase">Digital</span>
                    @elseif($isService)
                        <span class="bg-gray-800 text-white text-[10px] font-bold px-2 py-1 rounded uppercase">Service</span>
                    @endif
                </div>

                {{-- Configurator Content --}}
                <div class="flex-1 bg-gray-50/50 relative overflow-hidden">
                    @if($previewItem->product)
                        <livewire:shop.configurator.configurator
                            :product="$previewItem->product->id"
                            :initialData="$previewItem->configuration"
                            :qty="$previewItem->quantity"
                            context="preview"
                            :key="'admin-preview-'.$previewItem->id"
                        />
                    @else
                        <div class="p-12 flex items-center justify-center h-full text-red-400 font-bold italic">
                            Das ursprüngliche Produkt wurde aus dem System gelöscht.
                        </div>
                    @endif
                </div>

                {{-- Footer Info --}}
                @if($fingerprint)
                    <div class="bg-green-50 px-6 py-3 border-t border-green-100 flex items-center gap-3">
                        <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <div class="text-[10px] text-green-800">
                            <strong>Signierte Konfiguration:</strong> Dieser Stand entspricht exakt dem Kundenwunsch zum Zeitpunkt der Bestellung.
                        </div>
                    </div>
                @endif
            </div>

        @else
            <div class="h-full flex flex-col items-center justify-center text-center text-gray-400 p-12">
                <div class="w-20 h-20 bg-white shadow-sm border border-gray-100 rounded-3xl flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </div>
                <p class="font-medium text-gray-500">Wähle eine Position links aus,</p>
                <p class="text-sm">um die Konfiguration und Dateien zu prüfen.</p>
            </div>
        @endif
    </div>
</div>
