<div class="bg-gray-900/50 border border-gray-800 rounded-2xl overflow-hidden mb-6" x-data="{ open: true }">
    <button @click="open = !open" class="w-full flex items-center justify-between p-5 bg-gray-950/50 hover:bg-gray-900 transition-colors">
        <div class="flex items-center gap-3">
            <span class="w-6 h-6 rounded-lg bg-gray-800 text-gray-400 flex items-center justify-center text-[10px] font-black">1</span>
            <h4 class="text-xs font-black text-white uppercase tracking-widest">Arbeitsbereich (2D)</h4>
        </div>
        <svg class="w-5 h-5 text-gray-500 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
    </button>

    <div x-show="open" x-collapse class="p-5 border-t border-gray-800 space-y-6">

        <div x-show="configSettings.area_shape !== 'custom'" class="space-y-5" x-transition>
            <div class="flex items-center gap-4">
                <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-gray-500">Links %</label>
                <input type="range" x-model.number="configSettings.area_left" @input="updateTexture()" min="0" max="100" step="0.1" class="flex-1 accent-primary h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                <input type="number" x-model.number="configSettings.area_left" @input="updateTexture()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-primary outline-none">
            </div>
            <div class="flex items-center gap-4">
                <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-gray-500">Oben %</label>
                <input type="range" x-model.number="configSettings.area_top" @input="updateTexture()" min="0" max="100" step="0.1" class="flex-1 accent-primary h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                <input type="number" x-model.number="configSettings.area_top" @input="updateTexture()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-primary outline-none">
            </div>
            <div class="flex items-center gap-4">
                <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-gray-500">Breite %</label>
                <input type="range" x-model.number="configSettings.area_width" @input="updateTexture()" min="1" max="100" step="0.1" class="flex-1 accent-primary h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                <input type="number" x-model.number="configSettings.area_width" @input="updateTexture()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-primary outline-none">
            </div>
            <div class="flex items-center gap-4">
                <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-gray-500">Höhe %</label>
                <input type="range" x-model.number="configSettings.area_height" @input="updateTexture()" min="1" max="100" step="0.1" class="flex-1 accent-primary h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                <input type="number" x-model.number="configSettings.area_height" @input="updateTexture()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-primary outline-none">
            </div>
        </div>

        <div x-show="configSettings.area_shape === 'custom'" class="space-y-4" style="display: none;" x-transition>

            <div class="flex flex-col gap-3 mb-4 border-b border-gray-800 pb-4">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <h5 class="text-[11px] font-black uppercase tracking-widest text-emerald-400">Polygon-Koordinaten</h5>
                        <p class="text-[9px] text-gray-500 mt-1">Punkte (X/Y) in % für exakte Formen definieren.</p>
                    </div>

                    <div class="flex items-center bg-gray-950 p-1.5 rounded-xl border border-gray-800 shadow-inner cursor-pointer hover:border-blue-500/50 transition-colors shrink-0" @click="configSettings.mirror_polygon = !configSettings.mirror_polygon; updateTexture();">
                        <div class="px-2 py-1 flex items-center gap-2">
                            <div class="relative w-7 h-3.5 bg-gray-800 rounded-full shadow-inner overflow-hidden">
                                <div class="absolute top-0.5 left-0.5 w-2.5 h-2.5 rounded-full transition-transform duration-300"
                                     :class="configSettings.mirror_polygon ? 'bg-blue-500 translate-x-3.5 shadow-[0_0_8px_rgba(59,130,246,0.8)]' : 'bg-gray-500'"></div>
                            </div>
                            <span class="text-[9px] font-black uppercase tracking-widest transition-colors"
                                  :class="configSettings.mirror_polygon ? 'text-blue-400 drop-shadow-[0_0_5px_currentColor]' : 'text-gray-500'">
                                Spiegelung
                            </span>
                        </div>
                    </div>
                </div>

                {{-- NEUER PUNKT BUTTON --}}
                <button type="button" @click="addNewDefaultPoint()" class="w-full px-4 py-3 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500 hover:text-gray-900 border border-emerald-500/20 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex items-center justify-center gap-2 shadow-inner">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Neuen Polygon-Punkt hinzufügen
                </button>

                {{-- KOPIEREN BUTTON (mit Fallback für lokale Entwicklung) --}}
                <button type="button"
                        x-data="{ copied: false }"
                        @click="
                            let code = configSettings.custom_points.map(p => `['x' => ${p.x}, 'y' => ${p.y}]`).join(',\n');

                            let showSuccess = () => {
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            };

                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(code).then(showSuccess);
                            } else {
                                // Fallback für lokale unverschlüsselte Umgebung (.test)
                                let ta = document.createElement('textarea');
                                ta.value = code;
                                ta.style.position = 'fixed';
                                ta.style.left = '-999999px';
                                document.body.appendChild(ta);
                                ta.focus();
                                ta.select();
                                try {
                                    document.execCommand('copy');
                                    showSuccess();
                                } catch (err) {
                                    console.error('Kopieren fehlgeschlagen:', err);
                                }
                                document.body.removeChild(ta);
                            }
                        "
                        class="w-full px-4 py-2.5 bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white border border-gray-700 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex items-center justify-center gap-2 shadow-inner">
                    <svg x-show="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    <svg x-show="copied" style="display: none;" class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    <span x-text="copied ? 'Kopiert!' : 'Alle Punkte kopieren'"></span>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2 p-4">
                <template x-for="(point, index) in configSettings.custom_points" :key="index">
                    <div class="flex items-center gap-3 p-3.5 bg-gray-950 border border-gray-800 rounded-xl hover:border-emerald-500/30 transition-colors shadow-inner relative group">

                        <span class="absolute -top-2 -left-2 w-6 h-6 bg-gray-800 border border-gray-700 text-gray-300 rounded-full flex items-center justify-center text-[10px] font-black shadow-sm" x-text="index + 1"></span>

                        <div class="flex-1 flex flex-col gap-2 ml-2">
                            <div class="flex items-center bg-gray-900 border border-gray-800 rounded-lg overflow-hidden focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-colors">
                                <span class="px-3 py-2.5 bg-gray-950 border-r border-gray-800 text-[10px] font-black text-gray-500 w-12 text-center shrink-0">X %</span>
                                <input type="number" x-model.number="point.x" @input="updateTexture()" step="0.1" class="w-full bg-transparent border-none px-3 py-2 text-xs text-white outline-none">
                            </div>
                            <div class="flex items-center bg-gray-900 border border-gray-800 rounded-lg overflow-hidden focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-colors">
                                <span class="px-3 py-2.5 bg-gray-950 border-r border-gray-800 text-[10px] font-black text-gray-500 w-12 text-center shrink-0">Y %</span>
                                <input type="number" x-model.number="point.y" @input="updateTexture()" step="0.1" class="w-full bg-transparent border-none px-3 py-2 text-xs text-white outline-none">
                            </div>
                        </div>

                        <button type="button" @click="deletePoint(index);" class="w-10 h-10 flex items-center justify-center rounded-lg bg-red-500/5 text-gray-500 hover:bg-red-500 hover:text-white border border-transparent hover:border-red-600 transition-colors shrink-0" title="Punkt löschen">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>

    </div>
</div>
