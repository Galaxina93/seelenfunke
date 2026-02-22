<div class="space-y-3" x-show="modelPath" style="display: none;">
    <div class="flex justify-between items-center border-b border-blue-200 pb-1.5">
        <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wider">3. Overlay (Gravur)</h4>
        <button type="button" @click="resetOverlay()" class="text-[9px] text-red-500 font-bold uppercase hover:underline">Reset</button>
    </div>

    <div class="flex items-center gap-2 mt-[26px]">
        <span class="text-[10px] font-bold text-blue-600 w-12" title="Skalierung">Größe %</span>
        <input type="range" x-model.number="configSettings.engraving_scale" @input="applyModelTransforms()" min="1" max="500" step="0.1" class="flex-1 accent-blue-600">
        <input type="number" x-model.number="configSettings.engraving_scale" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-blue-500" step="0.01">
    </div>

    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-blue-600 w-12">Pos X</span>
        <input type="range" x-model.number="configSettings.engraving_pos_x" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="flex-1 accent-blue-600">
        <input type="number" x-model.number="configSettings.engraving_pos_x" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-blue-500" step="0.01">
    </div>
    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-blue-600 w-12">Pos Y</span>
        <input type="range" x-model.number="configSettings.engraving_pos_y" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="flex-1 accent-blue-600">
        <input type="number" x-model.number="configSettings.engraving_pos_y" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-blue-500" step="0.01">
    </div>
    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-blue-600 w-12" title="Tiefe im Material">Pos Z</span>
        <input type="range" x-model.number="configSettings.engraving_pos_z" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="flex-1 accent-blue-600">
        <input type="number" x-model.number="configSettings.engraving_pos_z" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-blue-500" step="0.01">
    </div>

    <div class="flex items-center gap-2 mt-2">
        <span class="text-[10px] font-bold text-blue-600 w-12">Rot X°</span>
        <input type="range" x-model.number="configSettings.engraving_rot_x" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="flex-1 accent-blue-600">
        <input type="number" x-model.number="configSettings.engraving_rot_x" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-blue-500" step="0.01">
    </div>
    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-blue-600 w-12">Rot Y°</span>
        <input type="range" x-model.number="configSettings.engraving_rot_y" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="flex-1 accent-blue-600">
        <input type="number" x-model.number="configSettings.engraving_rot_y" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-blue-500" step="0.01">
    </div>
    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-blue-600 w-12">Rot Z°</span>
        <input type="range" x-model.number="configSettings.engraving_rot_z" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="flex-1 accent-blue-600">
        <input type="number" x-model.number="configSettings.engraving_rot_z" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-blue-500" step="0.01">
    </div>
</div>
