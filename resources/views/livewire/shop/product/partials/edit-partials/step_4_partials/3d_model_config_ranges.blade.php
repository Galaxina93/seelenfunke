<div class="space-y-3" x-show="modelPath" style="display: none;">
    <div class="flex justify-between items-center border-b border-gray-200 pb-1.5">
        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">2. 3D-Modell</h4>
        <button type="button" @click="resetModel()" class="text-[9px] text-red-500 font-bold uppercase hover:underline">Reset</button>
    </div>

    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-gray-500 w-12">Material</span>
        <select x-model="configSettings.material_type" @change="applyMaterial()" class="flex-1 text-[10px] py-1 px-2 rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary cursor-pointer">
            <option value="glass">Glas (Transparent)</option>
            <option value="wood">Holz (Matt)</option>
            <option value="metal">Metall (Glänzend)</option>
            <option value="plastic">Kunststoff</option>
        </select>
    </div>

    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-gray-500 w-12" title="Skalierung">Größe %</span>
        <input type="range" x-model.number="configSettings.model_scale" @input="applyModelTransforms()" min="1" max="500" step="0.1" class="flex-1 accent-primary">
        <input type="number" x-model.number="configSettings.model_scale" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
    </div>

    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-gray-500 w-12">Pos X</span>
        <input type="range" x-model.number="configSettings.model_pos_x" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="flex-1 accent-primary">
        <input type="number" x-model.number="configSettings.model_pos_x" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
    </div>
    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-gray-500 w-12">Pos Y</span>
        <input type="range" x-model.number="configSettings.model_pos_y" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="flex-1 accent-primary">
        <input type="number" x-model.number="configSettings.model_pos_y" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
    </div>
    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-gray-500 w-12">Pos Z</span>
        <input type="range" x-model.number="configSettings.model_pos_z" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="flex-1 accent-primary">
        <input type="number" x-model.number="configSettings.model_pos_z" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
    </div>

    <div class="flex items-center gap-2 mt-2">
        <span class="text-[10px] font-bold text-gray-500 w-12">Rot X°</span>
        <input type="range" x-model.number="configSettings.model_rot_x" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="flex-1 accent-primary">
        <input type="number" x-model.number="configSettings.model_rot_x" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
    </div>
    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-gray-500 w-12">Rot Y°</span>
        <input type="range" x-model.number="configSettings.model_rot_y" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="flex-1 accent-primary">
        <input type="number" x-model.number="configSettings.model_rot_y" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
    </div>
    <div class="flex items-center gap-2">
        <span class="text-[10px] font-bold text-gray-500 w-12">Rot Z°</span>
        <input type="range" x-model.number="configSettings.model_rot_z" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="flex-1 accent-primary">
        <input type="number" x-model.number="configSettings.model_rot_z" @input="applyModelTransforms()" class="w-14 px-1 py-0.5 text-[10px] text-center rounded border-gray-300 shadow-sm focus:ring-1 focus:border-primary" step="0.01">
    </div>
</div>
