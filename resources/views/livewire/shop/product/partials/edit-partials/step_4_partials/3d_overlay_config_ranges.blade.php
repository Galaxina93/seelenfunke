<div x-data="{ open: false }" x-show="modelPath" style="display: none;" class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner group transition-colors hover:border-blue-500/30 w-full overflow-hidden">
    <div @click="open = !open" class="flex items-center justify-between cursor-pointer transition-all" :class="open ? 'border-b border-blue-500/20 pb-4' : ''">
        <h4 class="text-[10px] font-black text-blue-400 uppercase tracking-widest flex items-center gap-3 group-hover:text-blue-300 transition-colors drop-shadow-[0_0_8px_currentColor]">
            <span class="w-5 h-5 rounded-lg bg-blue-500/20 border border-blue-500/30 flex items-center justify-center text-blue-300 shadow-inner shrink-0 transition-colors group-hover:bg-blue-500 group-hover:text-gray-900 group-hover:border-transparent">3</span>
            Overlay (Gravur)
        </h4>

        <div class="flex items-center gap-3 shrink-0">
            <button type="button" @click.stop="resetOverlay()" x-show="open" x-transition class="text-[9px] text-gray-500 font-black uppercase tracking-widest hover:text-red-400 transition-colors bg-gray-900 hover:bg-red-500/10 px-3 py-1.5 rounded-lg border border-gray-800 hover:border-red-500/30 shadow-inner">Reset</button>
            <div class="p-1 text-gray-500 group-hover:text-blue-400 transition-colors">
                <svg class="w-4 h-4 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </div>
        </div>
    </div>

    <div x-show="open" x-collapse style="display: none;">
        @php
            $rangeInputClassBlue = "w-16 shrink-0 px-2 py-1.5 text-[10px] text-center font-mono font-bold rounded-lg border border-gray-800 bg-gray-900 text-white shadow-inner focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition-all";
            $labelClassBlue = "text-[9px] font-black text-blue-500/80 uppercase tracking-widest w-20 shrink-0 text-left";
            $rangeSliderClassBlue = "flex-1 min-w-[50px] h-1.5 bg-gray-900 rounded-lg appearance-none cursor-pointer border border-gray-800 shadow-inner
                                [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3.5 [&::-webkit-slider-thumb]:h-3.5
                                [&::-webkit-slider-thumb]:bg-blue-500 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:shadow-[0_0_10px_rgba(59,130,246,0.8)]
                                hover:[&::-webkit-slider-thumb]:scale-125 transition-all";
        @endphp

        <div class="flex flex-col gap-4 pt-5">
            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassBlue }}" title="Skalierung">Größe %</span>
                <input type="range" x-model.number="configSettings.engraving_scale" @input="applyModelTransforms()" min="1" max="500" step="0.1" class="{{ $rangeSliderClassBlue }}">
                <input type="number" x-model.number="configSettings.engraving_scale" @input="applyModelTransforms()" class="{{ $rangeInputClassBlue }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassBlue }}">Pos X</span>
                <input type="range" x-model.number="configSettings.engraving_pos_x" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="{{ $rangeSliderClassBlue }}">
                <input type="number" x-model.number="configSettings.engraving_pos_x" @input="applyModelTransforms()" class="{{ $rangeInputClassBlue }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassBlue }}">Pos Y</span>
                <input type="range" x-model.number="configSettings.engraving_pos_y" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="{{ $rangeSliderClassBlue }}">
                <input type="number" x-model.number="configSettings.engraving_pos_y" @input="applyModelTransforms()" class="{{ $rangeInputClassBlue }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassBlue }}" title="Tiefe im Material">Pos Z</span>
                <input type="range" x-model.number="configSettings.engraving_pos_z" @input="applyModelTransforms()" min="-200" max="200" step="0.1" class="{{ $rangeSliderClassBlue }}">
                <input type="number" x-model.number="configSettings.engraving_pos_z" @input="applyModelTransforms()" class="{{ $rangeInputClassBlue }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full pt-4 mt-2 border-t border-gray-800">
                <span class="{{ $labelClassBlue }}">Rot X°</span>
                <input type="range" x-model.number="configSettings.engraving_rot_x" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="{{ $rangeSliderClassBlue }}">
                <input type="number" x-model.number="configSettings.engraving_rot_x" @input="applyModelTransforms()" class="{{ $rangeInputClassBlue }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassBlue }}">Rot Y°</span>
                <input type="range" x-model.number="configSettings.engraving_rot_y" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="{{ $rangeSliderClassBlue }}">
                <input type="number" x-model.number="configSettings.engraving_rot_y" @input="applyModelTransforms()" class="{{ $rangeInputClassBlue }}" step="0.01">
            </div>

            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassBlue }}">Rot Z°</span>
                <input type="range" x-model.number="configSettings.engraving_rot_z" @input="applyModelTransforms()" min="-180" max="180" step="0.1" class="{{ $rangeSliderClassBlue }}">
                <input type="number" x-model.number="configSettings.engraving_rot_z" @input="applyModelTransforms()" class="{{ $rangeInputClassBlue }}" step="0.01">
            </div>
        </div>
    </div>
</div>
