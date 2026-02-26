<div class="flex flex-wrap items-center justify-between gap-5 mb-8 w-full max-w-full">

    {{-- TEXT BEREICH --}}
    <div class="w-full xl:w-auto flex-1 min-w-[200px] shrink-0">
        <h3 class="font-serif font-bold text-xl text-white mb-1.5 tracking-wide">Vorschau & Arbeitsbereich</h3>
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 max-w-sm leading-relaxed">Definieren Sie die Gravurfläche und 3D-Ansicht.</p>
    </div>

    {{-- KONTROLL-BEREICH (Buttons & Selects) - Bricht sauber um! --}}
    <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">

        {{-- DRAG & DROP TOOLBAR --}}
        <div class="flex flex-wrap items-center gap-2 bg-blue-900/10 border border-blue-500/20 p-1.5 rounded-xl shadow-inner w-full sm:w-auto" x-show="modelPath">
            <span class="text-[9px] font-black uppercase tracking-widest text-blue-400 drop-shadow-[0_0_8px_currentColor] ml-2 hidden sm:block shrink-0">3D-Drag:</span>

            <select x-model="transformTarget" @change="updateTransformTarget()"
                    class="flex-1 sm:flex-none text-[10px] font-bold uppercase tracking-wider py-2 px-3 border border-blue-500/30 bg-gray-950 rounded-lg text-blue-300 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none cursor-pointer appearance-none shadow-inner text-center sm:text-left min-w-[120px]">
                <option value="none" class="bg-gray-900">Kamera steuern</option>
                <option value="model" class="bg-gray-900">Modell bewegen</option>
                <option value="overlay" class="bg-gray-900">Overlay bewegen</option>
            </select>

            <select x-show="transformTarget !== 'none'" x-model="transformMode" @change="updateTransformTarget()"
                    class="flex-1 sm:flex-none text-[10px] font-bold uppercase tracking-wider py-2 px-3 border border-blue-500/30 bg-gray-950 rounded-lg text-blue-300 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none cursor-pointer appearance-none shadow-inner text-center sm:text-left min-w-[110px]">
                <option value="translate" class="bg-gray-900">Verschieben</option>
                <option value="rotate" class="bg-gray-900">Drehen</option>
                <option value="scale" class="bg-gray-900">Skalieren</option>
            </select>
        </div>

        {{-- FORM-WAHL (Eckig / Rund / Polygon) --}}
        <div class="flex flex-1 sm:flex-none bg-gray-950 p-1.5 rounded-xl border border-gray-800 shadow-inner shrink-0 min-w-[200px]">
            <button type="button" @click="configSettings.area_shape = 'rect'; updateTexture()"
                    class="flex-1 px-2 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-center"
                    :class="configSettings.area_shape === 'rect' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-500 hover:text-white'">
                Eckig
            </button>
            <button type="button" @click="configSettings.area_shape = 'circle'; updateTexture()"
                    class="flex-1 px-2 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-center"
                    :class="configSettings.area_shape === 'circle' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-500 hover:text-white'">
                Rund
            </button>
            <button type="button" @click="configSettings.area_shape = 'custom'; updateTexture()"
                    class="flex-1 px-2 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-center"
                    :class="configSettings.area_shape === 'custom' ? 'bg-gray-800 text-white shadow-sm' : 'text-gray-500 hover:text-white'">
                Polygon
            </button>
        </div>

        {{-- ZEICHENBRETT TOGGLE --}}
        <button type="button" @click="showDrawingBoard = !showDrawingBoard"
                class="w-full sm:w-auto px-5 py-3 sm:py-2.5 rounded-xl border text-[9px] font-black uppercase tracking-widest transition-all shadow-inner shrink-0 text-center"
                :class="showDrawingBoard ? 'bg-primary/10 border-primary/30 text-primary drop-shadow-[0_0_8px_currentColor]' : 'bg-gray-950 border-gray-800 text-gray-500 hover:text-white'">
            <span x-text="showDrawingBoard ? 'Brett ausblenden' : 'Brett einblenden'"></span>
        </button>

    </div>
</div>
