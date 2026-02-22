<div class="space-y-3">
    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider border-b border-gray-200 pb-1.5">1. Arbeitsbereich (2D)</h4>

    <div x-show="configSettings.area_shape !== 'custom'" class="space-y-3" style="display: none;">
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-gray-500 w-12">Links %</span>
            <input type="range" x-model.number="configSettings.area_left" @input="updateTexture()" min="0" max="100" step="0.1" class="flex-1 accent-primary">
            <input type="number" x-model.number="configSettings.area_left" @input="updateTexture()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-gray-500 w-12">Oben %</span>
            <input type="range" x-model.number="configSettings.area_top" @input="updateTexture()" min="0" max="100" step="0.1" class="flex-1 accent-primary">
            <input type="number" x-model.number="configSettings.area_top" @input="updateTexture()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-gray-500 w-12">Breite %</span>
            <input type="range" x-model.number="configSettings.area_width" @input="updateTexture()" min="1" max="100" step="0.1" class="flex-1 accent-primary">
            <input type="number" x-model.number="configSettings.area_width" @input="updateTexture()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-gray-500 w-12">Höhe %</span>
            <input type="range" x-model.number="configSettings.area_height" @input="updateTexture()" min="1" max="100" step="0.1" class="flex-1 accent-primary">
            <input type="number" x-model.number="configSettings.area_height" @input="updateTexture()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
        </div>
    </div>

    <div x-show="configSettings.area_shape === 'custom'" style="display: none;">
        <p class="text-[10px] text-gray-500 leading-relaxed mb-3">Ziehen Sie die Punkte direkt im Zeichenbrett oben, um die Form frei zu definieren.</p>
        <button type="button" @click="configSettings.custom_points = [{x:20,y:20}, {x:80,y:20}, {x:80,y:80}, {x:20,y:80}]; updateTexture();" class="w-full py-1.5 bg-white border border-gray-300 rounded text-[10px] font-bold text-gray-700 hover:text-red-500 transition-colors shadow-sm">Punkte zurücksetzen</button>
    </div>
</div>
