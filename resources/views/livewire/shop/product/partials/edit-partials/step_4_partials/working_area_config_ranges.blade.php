<div x-data="{ open: false }" class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner group transition-colors hover:border-gray-700 w-full overflow-hidden">
    <div @click="open = !open" class="flex items-center justify-between cursor-pointer transition-all" :class="open ? 'border-b border-gray-800 pb-4' : ''">
        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-3 group-hover:text-white transition-colors">
            <span class="w-5 h-5 rounded-lg bg-gray-800 flex items-center justify-center text-white shadow-inner shrink-0 transition-colors group-hover:bg-primary group-hover:text-gray-900">1</span>
            Arbeitsbereich (2D)
        </h4>
        <div class="p-1 text-gray-500 group-hover:text-white transition-colors shrink-0">
            <svg class="w-4 h-4 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="display: none;">
        @php
            $rangeInputClassWA = "w-16 shrink-0 px-2 py-1.5 text-[10px] text-center font-mono font-bold rounded-lg border border-gray-800 bg-gray-900 text-white shadow-inner focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all";
            $labelClassWA = "text-[9px] font-black text-gray-500 uppercase tracking-widest w-20 shrink-0 text-left";
            $rangeSliderClass = "flex-1 min-w-[50px] h-1.5 bg-gray-900 rounded-lg appearance-none cursor-pointer border border-gray-800 shadow-inner
                                [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3.5 [&::-webkit-slider-thumb]:h-3.5
                                [&::-webkit-slider-thumb]:bg-gray-400 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:shadow-md
                                hover:[&::-webkit-slider-thumb]:bg-white hover:[&::-webkit-slider-thumb]:scale-110 transition-all";
        @endphp

        <div x-show="configSettings.area_shape !== 'custom'" class="flex flex-col gap-4 pt-5" style="display: none;" x-transition>
            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassWA }}">Links %</span>
                <input type="range" x-model.number="configSettings.area_left" @input="updateTexture()" min="0" max="100" step="0.1" class="{{ $rangeSliderClass }}">
                <input type="number" x-model.number="configSettings.area_left" @input="updateTexture()" class="{{ $rangeInputClassWA }}" step="0.01">
            </div>
            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassWA }}">Oben %</span>
                <input type="range" x-model.number="configSettings.area_top" @input="updateTexture()" min="0" max="100" step="0.1" class="{{ $rangeSliderClass }}">
                <input type="number" x-model.number="configSettings.area_top" @input="updateTexture()" class="{{ $rangeInputClassWA }}" step="0.01">
            </div>
            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassWA }}">Breite %</span>
                <input type="range" x-model.number="configSettings.area_width" @input="updateTexture()" min="1" max="100" step="0.1" class="{{ $rangeSliderClass }}">
                <input type="number" x-model.number="configSettings.area_width" @input="updateTexture()" class="{{ $rangeInputClassWA }}" step="0.01">
            </div>
            <div class="flex items-center gap-3 w-full">
                <span class="{{ $labelClassWA }}">Höhe %</span>
                <input type="range" x-model.number="configSettings.area_height" @input="updateTexture()" min="1" max="100" step="0.1" class="{{ $rangeSliderClass }}">
                <input type="number" x-model.number="configSettings.area_height" @input="updateTexture()" class="{{ $rangeInputClassWA }}" step="0.01">
            </div>
        </div>

        <div x-show="configSettings.area_shape === 'custom'" style="display: none;" x-transition>
            <div class="bg-gray-900/50 p-5 rounded-2xl border border-gray-800 shadow-inner text-center mt-5">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 leading-relaxed mb-4">Ziehen Sie die Punkte direkt im Zeichenbrett, um die Form frei zu definieren.</p>
                <button type="button" @click="configSettings.custom_points = [{x:20,y:20}, {x:80,y:20}, {x:80,y:80}, {x:20,y:80}]; updateTexture();" class="px-6 py-2.5 bg-gray-900 hover:bg-gray-800 border border-gray-700 rounded-xl text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-all shadow-inner">Punkte zurücksetzen</button>
            </div>
        </div>
    </div>
</div>
