<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
    <div>
        <h3 class="font-bold text-lg text-gray-900">Vorschau & Arbeitsbereich</h3>
        <p class="text-xs text-gray-500">Definieren Sie die Gravurfläche und justieren Sie die 3D-Ansicht.</p>
    </div>

    <div class="flex flex-col sm:flex-row items-center gap-4">
        {{-- DRAG & DROP TOOLBAR (TransformControls) --}}
        <div class="flex items-center gap-2 bg-blue-50 border border-blue-100 p-1.5 rounded-lg shadow-sm" x-show="modelPath">
            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-800 ml-2">3D-Drag:</span>
            <select x-model="transformTarget" @change="updateTransformTarget()" class="text-xs py-1 px-2 border-blue-200 bg-white rounded-md text-blue-900 font-medium focus:ring-blue-500 focus:border-blue-500 outline-none">
                <option value="none">Deaktiviert (Kamera steuern)</option>
                <option value="model">Modell bewegen</option>
                <option value="overlay">Overlay bewegen</option>
            </select>
            <select x-show="transformTarget !== 'none'" x-model="transformMode" @change="updateTransformTarget()" class="text-xs py-1 px-2 border-blue-200 bg-white rounded-md text-blue-900 font-medium focus:ring-blue-500 focus:border-blue-500 outline-none">
                <option value="translate">Verschieben</option>
                <option value="rotate">Drehen</option>
                <option value="scale">Skalieren</option>
            </select>
        </div>

        <div class="flex bg-white p-1 rounded-lg border border-gray-200 shadow-sm">
            <button type="button" @click="configSettings.area_shape = 'rect'; updateTexture()" class="px-3 py-1.5 rounded-md text-xs font-bold transition" :class="configSettings.area_shape === 'rect' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-700'">Eckig</button>
            <button type="button" @click="configSettings.area_shape = 'circle'; updateTexture()" class="px-3 py-1.5 rounded-md text-xs font-bold transition" :class="configSettings.area_shape === 'circle' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-700'">Rund</button>
            <button type="button" @click="configSettings.area_shape = 'custom'; updateTexture()" class="px-3 py-1.5 rounded-md text-xs font-bold transition" :class="configSettings.area_shape === 'custom' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-700'">Polygon</button>
        </div>
        <button type="button" @click="showDrawingBoard = !showDrawingBoard" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition" :class="showDrawingBoard ? 'bg-blue-50 border-blue-200 text-blue-700 shadow-inner' : 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50 shadow-sm'">
            <span x-text="showDrawingBoard ? 'Zeichenbrett ausblenden' : 'Zeichenbrett einblenden'"></span>
        </button>
    </div>
</div>
