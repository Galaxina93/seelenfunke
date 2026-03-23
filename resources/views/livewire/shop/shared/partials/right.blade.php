<div class="w-full lg:w-1/2 flex-1 lg:flex-none flex flex-col bg-gray-950 border-l-0 lg:border-l border-gray-800 min-h-[600px] lg:min-h-0">
    <div class="flex-1 p-4 sm:p-6 md:p-8 h-full overflow-y-auto custom-scrollbar flex flex-col gap-6">
        @if($previewItem)
            <div class="bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] sm:rounded-[2.5rem] shadow-2xl border border-gray-800 flex flex-col overflow-hidden shrink-0">
                <div class="bg-gray-950 border-b border-gray-800 px-5 sm:px-8 py-4 sm:py-5 shrink-0 flex justify-between items-center shadow-inner">
                    <div class="min-w-0 pr-4">
                        <h3 class="font-bold text-white text-base sm:text-lg truncate tracking-wide">{{ $previewItem->product_name }}</h3>
                        <p class="text-[9px] text-primary font-black uppercase tracking-[0.2em] mt-1">Live-Konfigurations-Vorschau</p>
                    </div>
                    @if($isDigitalItem)
                        <span class="bg-blue-500/10 border border-blue-500/30 text-blue-400 text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest shadow-[0_0_15px_rgba(59,130,246,0.15)] shrink-0">Digital</span>
                    @elseif($isService)
                        <span class="bg-gray-800 border border-gray-700 text-gray-300 text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest shadow-inner shrink-0">Service</span>
                    @endif
                </div>

                <div class="bg-transparent relative w-full flex flex-col min-h-[500px] flex-1">
                    @if($previewItem->product)
                        <div class="w-full flex-1 relative flex flex-col">
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

                @if($fingerprint)
                    <div class="bg-emerald-900/10 px-5 sm:px-8 py-4 border-t border-emerald-500/20 flex flex-col sm:flex-row sm:items-center gap-4 shadow-inner z-10 shrink-0">
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

            @if(!empty($previewItem->configuration['notes']))
                <div class="bg-amber-900/10 rounded-[1.5rem] sm:rounded-[2rem] shadow-inner border border-amber-500/20 overflow-hidden shrink-0">
                    <div class="bg-amber-500/10 px-5 sm:px-6 py-3 sm:py-4 border-b border-amber-500/20">
                        <h3 class="text-[9px] sm:text-[10px] font-black text-amber-400 uppercase tracking-widest flex items-center gap-2 drop-shadow-[0_0_8px_currentColor]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            Besondere Kundenwünsche
                        </h3>
                    </div>
                    <div class="p-5 sm:p-6 text-xs sm:text-sm text-amber-100/80 leading-relaxed font-medium">
                        <div class="bg-amber-950/50 rounded-2xl p-4 sm:p-5 border border-amber-900 shadow-inner italic">
                            "{!! nl2br(e($previewItem->configuration['notes'])) !!}"
                        </div>
                    </div>
                </div>
            @endif
        @else
            <div class="h-full flex flex-col items-center justify-center text-center text-gray-500 p-8 sm:p-12 bg-gray-900/30 rounded-[2rem] sm:rounded-[3rem] border border-gray-800 border-dashed shrink-0 min-h-[300px]">
                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gray-950 shadow-inner border border-gray-800 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 sm:w-12 sm:h-12 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </div>
                <p class="font-serif font-bold text-lg sm:text-xl text-white mb-2">Ansicht leer</p>
                <p class="text-xs sm:text-sm font-medium">Wähle eine Position links aus,<br>um die Konfiguration und Dateien zu prüfen.</p>
            </div>
        @endif
    </div>
</div>
