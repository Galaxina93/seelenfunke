{{-- Globale Hellmodus-Großansicht für Produkt-Zusätze / Extras mit Seelenfunke-Funken-Animation --}}
<div x-data="{
        addonModalOpen: false,
        addonData: null,
        openAddonModal(detail) {
            if (!detail) return;
            this.addonData = {
                name: detail.name || 'Produkt-Extra',
                price: detail.price || '',
                image: detail.image || '',
                description: (detail.description || '').trim()
            };
            this.addonModalOpen = true;
        },
        closeAddonModal() {
            this.addonModalOpen = false;
            setTimeout(() => { if (!this.addonModalOpen) this.addonData = null; }, 250);
        }
     }"
     @open-addon-modal.window="openAddonModal($event.detail)"
     @keydown.escape.window="if (addonModalOpen) closeAddonModal()">

    <style>
        @keyframes sfAddonSparkFloatMain {
            0%, 100% { transform: translateY(0) scale(1) rotate(0deg); filter: drop-shadow(0 0 12px rgba(197,160,89,0.55)); }
            50% { transform: translateY(-7px) scale(1.08) rotate(8deg); filter: drop-shadow(0 0 22px rgba(197,160,89,0.85)); }
        }
        @keyframes sfAddonSparkFloatSubA {
            0%, 100% { transform: translate(0, 0) scale(0.85) rotate(0deg); opacity: 0.65; }
            50% { transform: translate(-5px, -9px) scale(1.18) rotate(-15deg); opacity: 1; }
        }
        @keyframes sfAddonSparkFloatSubB {
            0%, 100% { transform: translate(0, 0) scale(0.9) rotate(0deg); opacity: 0.55; }
            50% { transform: translate(6px, -7px) scale(1.15) rotate(18deg); opacity: 1; }
        }
        @keyframes sfAddonHighlightShimmer {
            0%, 100% { box-shadow: 0 8px 25px -6px rgba(197, 160, 89, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.9); }
            50% { box-shadow: 0 12px 32px -4px rgba(197, 160, 89, 0.28), inset 0 1px 0 rgba(255, 255, 255, 1); }
        }
    </style>

    <template x-teleport="body">
        <div x-show="addonModalOpen"
             x-cloak
             style="display: none;"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[2147483647] flex items-center justify-center p-4 sm:p-6 bg-[#262016]/45 backdrop-blur-md"
             @click="closeAddonModal()">

            <div class="relative w-full max-w-md rounded-[2rem] overflow-hidden shadow-[0_25px_70px_-15px_rgba(120,90,40,0.35)] border border-[#e6d7bc] bg-[#fdfbf7] text-gray-900 p-6 sm:p-7 transform transition-all"
                 x-show="addonModalOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-3"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                 @click.stop>

                {{-- Sanfter goldener Lichtschein im Hintergrund (Hellmodus) --}}
                <div class="pointer-events-none absolute -top-20 left-1/2 -translate-x-1/2 w-72 h-40 bg-gradient-to-b from-[#C5A059]/20 via-amber-200/10 to-transparent blur-2xl"></div>

                {{-- Schließen-Button oben rechts --}}
                <button type="button"
                        @click="closeAddonModal()"
                        class="absolute top-4 right-4 z-20 w-9 h-9 rounded-full flex items-center justify-center bg-white hover:bg-[#f7f1e6] text-gray-500 hover:text-gray-900 border border-[#e6d7bc] shadow-sm transition-all hover:rotate-90"
                        title="Schließen">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <template x-if="addonData">
                    <div class="relative z-10 flex flex-col items-center text-center">

                        {{-- Animierte Seelenfunke-Sterne (wie unter "Teile deine Erfahrung") --}}
                        <div class="relative w-16 h-12 mb-1 flex items-center justify-center select-none pointer-events-none">
                            <div class="absolute inset-0 bg-[#C5A059]/20 rounded-full blur-xl animate-pulse"></div>
                            {{-- Hauptstern Mitte --}}
                            <svg style="animation: sfAddonSparkFloatMain 4s ease-in-out infinite;" class="w-9 h-9 text-[#C5A059] relative z-10" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 1L14.8 9.2L23 12L14.8 14.8L12 23L9.2 14.8L1 12L9.2 9.2L12 1Z"/>
                            </svg>
                            {{-- Kleiner Funken oben rechts --}}
                            <svg style="animation: sfAddonSparkFloatSubA 3.2s ease-in-out infinite 0.4s;" class="w-4 h-4 text-amber-400 absolute -top-0.5 right-0.5 z-20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"/>
                            </svg>
                            {{-- Kleiner Funken unten links --}}
                            <svg style="animation: sfAddonSparkFloatSubB 3.6s ease-in-out infinite 1.1s;" class="w-3.5 h-3.5 text-amber-500 absolute bottom-0 left-0.5 z-20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"/>
                            </svg>
                        </div>

                        <span class="text-[10px] font-bold uppercase tracking-[0.22em] text-[#a37e36] mb-1">
                            Manufaktur-Upgrade &bull; Großansicht
                        </span>

                        {{-- Großes Bild im edlen Hellmodus-Rahmen --}}
                        <template x-if="addonData.image">
                            <div class="w-full aspect-square max-h-64 rounded-2xl overflow-hidden mt-2 mb-5 flex items-center justify-center bg-gradient-to-b from-white to-[#faf6ef] border border-[#eadcc4] p-4 shadow-[inset_0_2px_10px_rgba(197,160,89,0.08)]">
                                <img :src="addonData.image"
                                     :alt="addonData.name"
                                     class="w-full h-full object-contain rounded-xl drop-shadow-[0_10px_20px_rgba(0,0,0,0.12)] transition-transform duration-500 hover:scale-105">
                            </div>
                        </template>

                        {{-- Titel & Preis-Badge --}}
                        <div class="w-full flex flex-col items-center gap-2 mb-4">
                            <h4 class="font-serif text-xl sm:text-2xl font-bold text-gray-900 leading-tight" x-text="addonData.name"></h4>
                            <template x-if="addonData.price">
                                <span class="inline-flex items-center gap-1.5 text-xs font-extrabold px-3.5 py-1 rounded-full bg-[#fdf6e7] text-[#9a732a] border border-[#e5cf9e] shadow-sm"
                                      x-text="addonData.price"></span>
                            </template>
                        </div>

                        {{-- Gehighlightete Kurzbeschreibung (nur in der Großansicht, falls vorhanden) --}}
                        <template x-if="addonData.description">
                            <div style="animation: sfAddonHighlightShimmer 4s ease-in-out infinite;"
                                 class="relative w-full rounded-2xl bg-gradient-to-br from-[#fffdf9] via-[#faf4e6] to-[#f5ead4] border border-[#e2cca0] px-4 py-3.5 mb-5 text-left overflow-hidden">
                                <div class="flex items-start gap-2.5">
                                    <div class=" shrink-0 mt-0.5 w-6 h-6 rounded-full bg-[#C5A059]/15 border border-[#C5A059]/30 flex items-center justify-center text-[#a37e36]">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 1L14.8 9.2L23 12L14.8 14.8L12 23L9.2 14.8L1 12L9.2 9.2L12 1Z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[#9a732a] mb-0.5">
                                            Details zum Extra
                                        </div>
                                        <p class="text-xs sm:text-sm text-gray-800 leading-relaxed font-medium"
                                           x-text="addonData.description"></p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Schließen-Button unten --}}
                        <button type="button"
                                @click="closeAddonModal()"
                                class="w-full py-2.5 px-5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all bg-gradient-to-r from-[#C5A059] to-[#b38b40] hover:from-[#b89249] hover:to-[#9e7830] text-white shadow-md shadow-[#C5A059]/25">
                            Schließen
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
