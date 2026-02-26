<div class="w-full lg:w-1/2 h-1/2 lg:h-full bg-gray-950 flex flex-col border-l-0 lg:border-l border-gray-800">
    <div class="flex-1 p-6 md:p-8 h-full overflow-y-auto custom-scrollbar">
        @if($previewItem)
            <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 flex flex-col overflow-hidden mb-6 h-full min-h-[500px]">

                {{-- Preview Header --}}
                <div class="bg-gray-950 border-b border-gray-800 px-6 sm:px-8 py-5 shrink-0 flex justify-between items-center shadow-inner">
                    <div class="min-w-0 pr-4">
                        <h3 class="font-bold text-white text-lg truncate tracking-wide">{{ $previewItem->product_name }}</h3>
                        <p class="text-[9px] text-primary font-black uppercase tracking-[0.2em] mt-1">Live-Konfigurations-Vorschau</p>
                    </div>
                    @if($isDigitalItem)
                        <span class="bg-blue-500/10 border border-blue-500/30 text-blue-400 text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest shadow-[0_0_15px_rgba(59,130,246,0.15)] shrink-0">Digital</span>
                    @elseif($isService)
                        <span class="bg-gray-800 border border-gray-700 text-gray-300 text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest shadow-inner shrink-0">Service</span>
                    @endif
                </div>

                {{-- Configurator Content --}}
                <div class="flex-1 bg-transparent relative overflow-hidden">
                    @if($previewItem->product)
                        {{-- HINWEIS: Hier wird die Configurator-Komponente geladen. Sie sollte ebenfalls im Dark Mode rendern,
                             falls sie nicht ohnehin systemweit dunkel ist. --}}
                        <div class="absolute inset-0 w-full h-full">
                            <livewire:shop.configurator.configurator
                                :product="$previewItem->product->id"
                                :initialData="$previewItem->configuration"
                                :qty="$previewItem->quantity"
                                context="preview"
                                :key="'admin-preview-'.$previewItem->id"
                            />
                        </div>
                    @else
                        <div class="p-12 flex flex-col items-center justify-center h-full text-red-500 font-bold italic text-center">
                            <svg class="w-16 h-16 mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Das ursprüngliche Produkt wurde aus dem System gelöscht.
                        </div>
                    @endif
                </div>

                {{-- Footer Info --}}
                @if($fingerprint)
                    <div class="bg-emerald-900/10 px-6 sm:px-8 py-4 border-t border-emerald-500/20 flex flex-col sm:flex-row sm:items-center gap-4 shadow-inner z-10 relative">
                        <div class="p-2 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shrink-0 self-start sm:self-auto shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div class="text-xs text-emerald-100/70 font-medium leading-relaxed">
                            <strong class="text-emerald-400 font-bold uppercase tracking-widest text-[9px] block mb-0.5">Signierte Konfiguration</strong>
                            Dieser Stand entspricht exakt dem Kundenwunsch zum Zeitpunkt der Bestellung.
                        </div>
                    </div>
                @endif
            </div>

        @else
            <div class="h-full flex flex-col items-center justify-center text-center text-gray-500 p-12 bg-gray-900/30 rounded-[3rem] border border-gray-800 border-dashed">
                <div class="w-24 h-24 bg-gray-950 shadow-inner border border-gray-800 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-12 h-12 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </div>
                <p class="font-serif font-bold text-xl text-white mb-2">Ansicht leer</p>
                <p class="text-sm font-medium">Wähle eine Position links aus,<br>um die Konfiguration und Dateien zu prüfen.</p>
            </div>
        @endif
    </div>
</div>
