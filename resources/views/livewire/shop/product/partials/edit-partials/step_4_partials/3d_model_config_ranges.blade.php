<div x-data="{ open: false }" x-show="modelPath" style="display: none;" class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner group transition-colors hover:border-gray-700 w-full overflow-hidden">
    <div @click="open = !open" class="flex items-center justify-between cursor-pointer transition-all" :class="open ? 'border-b border-gray-800 pb-4' : ''">
        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-3 group-hover:text-white transition-colors">
            <span class="w-5 h-5 rounded-lg bg-gray-800 flex items-center justify-center text-white shadow-inner shrink-0 transition-colors group-hover:bg-primary group-hover:text-gray-900">2</span>
            3D-Modell
        </h4>

        <div class="flex items-center gap-3 shrink-0">
            <button type="button" @click.stop="resetModel()" x-show="open" x-transition class="text-[9px] text-gray-500 font-black uppercase tracking-widest hover:text-red-400 transition-colors bg-gray-900 hover:bg-red-500/10 px-3 py-1.5 rounded-lg border border-gray-800 hover:border-red-500/30 shadow-inner">Reset</button>
            <div class="p-1 text-gray-500 group-hover:text-white transition-colors">
                <svg class="w-4 h-4 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </div>
        </div>
    </div>

    <div x-show="open" x-collapse style="display: none;">
        @php
            $rangeInputClassModel = "w-16 shrink-0 px-2 py-1.5 text-[10px] text-center font-mono font-bold rounded-lg border border-gray-800 bg-gray-900 text-white shadow-inner focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all";
            $labelClassModel = "text-[9px] font-black text-gray-500 uppercase tracking-widest w-20 shrink-0 text-left";
            $rangeSliderClassModel = "flex-1 min-w-[50px] h-1.5 bg-gray-900 rounded-lg appearance-none cursor-pointer border border-gray-800 shadow-inner
                                [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3.5 [&::-webkit-slider-thumb]:h-3.5
                                [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:shadow-[0_0_10px_rgba(197,160,89,0.8)]
                                hover:[&::-webkit-slider-thumb]:scale-125 transition-all";
        @endphp

        <div class="flex flex-col gap-4 pt-5">

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassModel }}">Material</span>
                <select x-model="configSettings.material_type" @change="applyMaterial()" class="flex-1 min-w-0 text-[10px] font-bold uppercase tracking-wider py-1.5 px-3 rounded-lg border border-gray-800 bg-gray-900 text-white shadow-inner focus:ring-2 focus:ring-primary/30 focus:border-primary cursor-pointer outline-none appearance-none">
                    <option value="glass" class="bg-gray-950">Glas (Transparent)</option>
                    <option value="wood" class="bg-gray-950">Holz (Matt)</option>
                    <option value="metal" class="bg-gray-950">Metall (Glänzend)</option>
                    <option value="plastic" class="bg-gray-950">Kunststoff</option>
                </select>
            </div>

            <div class="flex items-center gap-3 w-full mt-2">
                <span class="{{ $labelClassModel }}" title="Skalierung">Größe %</span>
                <input type="range" x-model.number="configSettings.model_scale" @input="applyModelTransforms()" min="1" max="500" step="0.1" class="{{ $rangeSliderClassModel }}">
                <input type="number" x-model.number="configSettings.model_scale" @input="applyModelTransforms()" class="{{ $rangeInputClassModel }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassModel }}">Pos X</span>
                <input type="range" x-model.number="configSettings.model_pos_x" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="{{ $rangeSliderClassModel }}">
                <input type="number" x-model.number="configSettings.model_pos_x" @input="applyModelTransforms()" class="{{ $rangeInputClassModel }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassModel }}">Pos Y</span>
                <input type="range" x-model.number="configSettings.model_pos_y" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="{{ $rangeSliderClassModel }}">
                <input type="number" x-model.number="configSettings.model_pos_y" @input="applyModelTransforms()" class="{{ $rangeInputClassModel }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassModel }}">Pos Z</span>
                <input type="range" x-model.number="configSettings.model_pos_z" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="{{ $rangeSliderClassModel }}">
                <input type="number" x-model.number="configSettings.model_pos_z" @input="applyModelTransforms()" class="{{ $rangeInputClassModel }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full pt-4 mt-2 border-t border-gray-800">
                <span class="{{ $labelClassModel }}">Rot X°</span>
                <input type="range" x-model.number="configSettings.model_rot_x" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="{{ $rangeSliderClassModel }}">
                <input type="number" x-model.number="configSettings.model_rot_x" @input="applyModelTransforms()" class="{{ $rangeInputClassModel }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassModel }}">Rot Y°</span>
                <input type="range" x-model.number="configSettings.model_rot_y" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="{{ $rangeSliderClassModel }}">
                <input type="number" x-model.number="configSettings.model_rot_y" @input="applyModelTransforms()" class="{{ $rangeInputClassModel }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassModel }}">Rot Z°</span>
                <input type="range" x-model.number="configSettings.model_rot_z" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="{{ $rangeSliderClassModel }}">
                <input type="number" x-model.number="configSettings.model_rot_z" @input="applyModelTransforms()" class="{{ $rangeInputClassModel }}" step="0.01">
            </div>
        </div>
    </div>
</div>
