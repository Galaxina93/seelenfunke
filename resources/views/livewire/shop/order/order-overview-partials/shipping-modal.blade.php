@if($confirmingShipmentId)
    <div class="fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-black/80 backdrop-blur-md p-4 pt-[15vh] sm:pt-4 transition-all">
        <div class="bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all animate-fade-in-up border border-gray-800">

            <div class="bg-gray-950 px-6 py-5 border-b border-gray-800 flex items-center gap-4">
                <div class="bg-gray-900 p-2 rounded-full text-[var(--theme-color)] shadow-inner border border-gray-800">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                </div>
                <div>
                    <h3 class="text-lg font-serif font-bold text-white">Versand bestätigen</h3>
                    <p class="text-[10px] uppercase tracking-widest text-[var(--theme-color)] font-bold">Kundenkommunikation</p>
                </div>
            </div>

            <div class="p-6">
                <p class="text-sm text-gray-400 mb-6 leading-relaxed">
                    Soll der Status auf <strong class="text-white">"Versendet"</strong> gesetzt werden und der Kunde eine <span class="text-[var(--theme-color)] underline decoration-[var(--theme-color-50)] decoration-2 underline-offset-2">automatische E-Mail</span> erhalten?
                </p>

                <div class="flex flex-col gap-3">
                    <button wire:click="confirmShipment(true)" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-[var(--theme-color)] text-gray-900 rounded-xl font-bold hover:bg-opacity-90 transition-all shadow-[0_0_15px_rgba(var(--theme-color-rgb,197,160,89),0.2)] hover:shadow-[0_0_20px_rgba(var(--theme-color-rgb,197,160,89),0.4)] transform hover:-translate-y-0.5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Ja, Status ändern & Mail senden
                    </button>

                    <button wire:click="confirmShipment(false)" class="w-full px-4 py-3 bg-gray-950 border border-gray-800 text-gray-400 rounded-xl font-bold hover:bg-gray-800 hover:text-white transition-colors">
                        Nur Status ändern (Still)
                    </button>
                </div>
            </div>

            <div class="bg-gray-950 px-6 py-4 border-t border-gray-800 flex justify-center">
                <button wire:click="cancelShipment" class="text-xs font-bold text-gray-500 hover:text-white uppercase tracking-widest transition-colors">
                    Abbrechen
                </button>
            </div>
        </div>
    </div>
@endif
