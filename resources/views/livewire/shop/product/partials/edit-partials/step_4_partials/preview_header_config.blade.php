<div class="flex flex-wrap items-center justify-between gap-5 mb-8 w-full max-w-full border-b border-gray-800 pb-6">

    {{-- TEXT BEREICH --}}
    <div class="w-full xl:w-auto flex-1 min-w-[200px] shrink-0">
        <h3 class="font-serif font-bold text-xl text-white mb-1.5 tracking-wide">Vorschau & Arbeitsbereich</h3>
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 max-w-sm leading-relaxed">Definieren Sie die Gravurfläche und 3D-Ansicht.</p>
    </div>

    {{-- KONTROLL-BEREICH (Buttons & Selects) --}}
    <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">

        {{-- RAYCAST NEU BERECHNEN BUTTON --}}
        <button type="button" x-show="configSettings.overlay_type === 'cylinder'" @click="updateOverlayGeometry()"
                class="flex flex-1 sm:flex-none items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-[9px] font-black uppercase tracking-widest text-emerald-400 hover:bg-emerald-500 hover:text-gray-900 transition-all shadow-inner shrink-0 group">
            <svg class="w-4 h-4 group-active:rotate-180 transition-transform duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Raycast Berechnen
        </button>

        {{-- DRAG & DROP TOOLBAR --}}
        <div class="flex flex-wrap items-center gap-2 bg-blue-900/10 border border-blue-500/20 p-1.5 rounded-xl shadow-inner w-full sm:w-auto" x-show="modelPath">
            <span class="text-[9px] font-black uppercase tracking-widest text-blue-400 drop-shadow-[0_0_8px_currentColor] ml-2 hidden sm:block shrink-0">3D-Drag:</span>

            <select x-model="transformTarget" @change="updateTransformTarget()"
                    class="flex-1 sm:flex-none text-[10px] font-bold uppercase tracking-wider py-2 px-3 border border-blue-500/30 bg-gray-950 rounded-lg text-blue-300 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none cursor-pointer appearance-none shadow-inner text-center sm:text-left min-w-[140px]">
                <option value="none" class="bg-gray-900">Kamera steuern</option>
                <option value="model" class="bg-gray-900">Modell bewegen</option>
                <option value="overlay" class="bg-gray-900">Overlay bewegen</option>
                <option value="area" class="bg-gray-900">Arbeitsbereich</option>
            </select>

            <select x-show="transformTarget !== 'none'" x-model="transformMode" @change="updateTransformTarget()"
                    class="flex-1 sm:flex-none text-[10px] font-bold uppercase tracking-wider py-2 px-3 border border-blue-500/30 bg-gray-950 rounded-lg text-blue-300 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none cursor-pointer appearance-none shadow-inner text-center sm:text-left min-w-[110px]">
                <option value="translate" class="bg-gray-900">Verschieben</option>
                <option value="rotate" class="bg-gray-900">Drehen</option>
                <option value="scale" class="bg-gray-900">Skalieren</option>
            </select>
        </div>

        {{-- PROJEKTIONS-WAHL (3D-Wrap: Flach / Zylindrisch) --}}
        <div class="flex flex-1 sm:flex-none bg-blue-950/40 p-1.5 rounded-xl border border-blue-500/30 shadow-inner shrink-0 min-w-[140px]" x-show="modelPath">
            <button type="button" @click="configSettings.overlay_type = 'plane'"
                    class="flex-1 px-2 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-center"
                    :class="(!configSettings.overlay_type || configSettings.overlay_type === 'plane') ? 'bg-blue-600 text-white shadow-sm' : 'text-blue-400 hover:text-white'">
                3D-Flach
            </button>
            <button type="button" @click="configSettings.overlay_type = 'cylinder'"
                    class="flex-1 px-2 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-center"
                    :class="configSettings.overlay_type === 'cylinder' ? 'bg-blue-600 text-white shadow-sm' : 'text-blue-400 hover:text-white'">
                3D-Rund
            </button>
        </div>

        {{-- FORM-WAHL (2D Clipping: Eckig / Rund / Polygon) --}}
        <div class="flex flex-1 sm:flex-none bg-gray-950 p-1.5 rounded-xl border border-gray-800 shadow-inner shrink-0 min-w-[200px]">
            <button type="button" @click="configSettings.area_shape = 'rect'"
                    class="flex-1 px-2 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-center"
                    :class="configSettings.area_shape === 'rect' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-500 hover:text-white'">
                Eckig
            </button>
            <button type="button" @click="configSettings.area_shape = 'circle'"
                    class="flex-1 px-2 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-center"
                    :class="configSettings.area_shape === 'circle' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-500 hover:text-white'">
                Rund
            </button>
            <button type="button" @click="configSettings.area_shape = 'custom'"
                    class="flex-1 px-2 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-center"
                    :class="configSettings.area_shape === 'custom' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-500 hover:text-white'">
                Polygon
            </button>
        </div>

        {{-- NEU: BEIDSEITIGE GRAVUR TOGGLE --}}
        <div class="flex flex-1 sm:flex-none items-center bg-gray-950 p-1.5 rounded-xl border border-gray-800 shadow-inner shrink-0 cursor-pointer hover:border-purple-500/50 transition-colors" @click="configSettings.has_back_side = !configSettings.has_back_side">
            <div class="px-3 py-2 flex items-center gap-2">
                <div class="relative w-8 h-4 bg-gray-800 rounded-full shadow-inner overflow-hidden">
                    <div class="absolute top-0.5 left-0.5 w-3 h-3 rounded-full transition-transform duration-300"
                         :class="configSettings.has_back_side ? 'bg-purple-500 translate-x-4 shadow-[0_0_8px_rgba(168,85,247,0.8)]' : 'bg-gray-500'"></div>
                </div>
                <span class="text-[9px] font-black uppercase tracking-widest transition-colors"
                      :class="configSettings.has_back_side ? 'text-purple-400 drop-shadow-[0_0_5px_currentColor]' : 'text-gray-500'">
                    Rückseite Aktiv
                </span>
            </div>
        </div>

        {{-- ZEICHENBRETT TOGGLE --}}
        <button type="button" @click="showDrawingBoard = !showDrawingBoard"
                class="w-full sm:w-auto px-5 py-3 sm:py-2.5 rounded-xl border text-[9px] font-black uppercase tracking-widest transition-all shadow-inner shrink-0 text-center"
                :class="showDrawingBoard ? 'bg-primary/10 border-primary/30 text-primary drop-shadow-[0_0_8px_currentColor]' : 'bg-gray-950 border-gray-800 text-gray-500 hover:text-white'">
            <span x-text="showDrawingBoard ? 'Brett ausblenden' : 'Brett einblenden'"></span>
        </button>

    </div>
</div>
