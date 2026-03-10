{{-- FIX: Wenn Context = 'edit', dann kein sticky, damit es im Warenkorb Modal scrollt! --}}
<div class="flex flex-col items-center z-40 border-b shadow-[0_4px_30px_rgba(0,0,0,0.03)] shrink-0 select-none pb-8 {{ $context === 'edit' ? 'relative' : 'sticky top-0' }} {{ $isDark ? 'bg-gray-950/80 backdrop-blur-xl border-gray-800' : 'bg-gray-50/30 border-gray-100' }}" x-ref="stage">

    {{-- KUNDEN TOGGLE 2D / 3D --}}
    <div x-show="config.modelPath" class="mb-6 mt-4 p-1.5 rounded-full shadow-sm border inline-flex items-center gap-1 relative z-50 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}">
        <button @click="showDrawingBoard = true" class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-300" :class="showDrawingBoard ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : '{{ $isDark ? 'text-gray-500 hover:text-white hover:bg-gray-800' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50' }}'">2D Editor</button>
        <button @click="showDrawingBoard = false" class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-300" :class="!showDrawingBoard ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : '{{ $isDark ? 'text-gray-500 hover:text-white hover:bg-gray-800' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50' }}'">3D Ansicht</button>
    </div>

    {{-- BÜHNE --}}
    <div class="relative w-full max-w-[455px] md:max-w-[600px] aspect-square shadow-[0_10px_40px_rgba(0,0,0,0.08)] overflow-hidden border-[6px] group {{ $isDark ? 'bg-gray-900 border-gray-800 ring-1 ring-gray-700' : 'bg-white border-white ring-1 ring-gray-100' }}"
         :style="(!showDrawingBoard && config.bgPath) ? `background-image: url('${config.bgPath}'); background-size: cover; background-position: center;` : ''"
         x-ref="container"
         @mousedown="deselectAll($event)"
         @touchstart="deselectAll($event)">

        {{-- 3D Ansicht --}}
        <div wire:ignore x-show="config.modelPath" x-ref="container3d" class="absolute inset-0 w-full h-full z-10 cursor-move transition-opacity duration-500" :class="showDrawingBoard ? 'opacity-0 pointer-events-none' : 'opacity-100'"></div>

        {{-- 2D Ansicht & Zeichenbrett --}}
        <div x-show="showDrawingBoard || !config.modelPath" class="absolute inset-0 w-full h-full z-20 pointer-events-auto transition-opacity duration-500 {{ $isDark ? 'bg-gray-950' : 'bg-gray-50' }}">

            <template x-if="config.fallbackImg">
                <img :src="config.fallbackImg" class="absolute inset-0 w-full h-full object-contain pointer-events-none transition-opacity duration-500 {{ $isDark ? 'opacity-20 mix-blend-screen filter invert brightness-0 group-hover:opacity-40' : 'opacity-40 mix-blend-multiply group-hover:opacity-60' }}">
            </template>

            <div class="absolute border-2 border-emerald-500 bg-emerald-500/5 pointer-events-none transition-all duration-500"
                 :style="`
                    top: ${config.area_shape === 'custom' ? 0 : config.area_top || 0}%;
                    left: ${config.area_shape === 'custom' ? 0 : config.area_left || 0}%;
                    width: ${config.area_shape === 'custom' ? 100 : config.area_width || 100}%;
                    height: ${config.area_shape === 'custom' ? 100 : config.area_height || 100}%;
                    border-radius: ${config.area_shape === 'circle' ? '50%' : '0'};
                    clip-path: ${
                        config.area_shape === 'custom' && config.custom_points && config.custom_points.length > 0
                        ? 'polygon(' + config.custom_points.map(p => p.x + '% ' + p.y + '%').join(', ') + ')'
                        : 'none'
                    };
                    box-shadow: ${config.area_shape === 'custom' ? 'none' : ('0 0 0 9999px ' + ('{{ $isDark }}' === '1' ? 'rgba(0,0,0,0.7)' : 'rgba(255,255,255,0.7)'))};
                 `">
            </div>

            {{-- Hilfslinien fürs Snapping --}}
            <div x-show="showGuideX" class="absolute top-0 bottom-0 border-l border-primary/60 border-dashed pointer-events-none transition-opacity" style="left: 50%; z-index: 10;"></div>
            <div x-show="showGuideY" class="absolute left-0 right-0 border-t border-primary/60 border-dashed pointer-events-none transition-opacity" style="top: 50%; z-index: 10;"></div>

            {{-- CLIP-CONTAINER FÜR ELEMENTE --}}
            <div class="absolute overflow-hidden pointer-events-none transition-all duration-300"
                 :style="`
                        top: ${config.area_shape === 'custom' ? 0 : config.area_top || 0}%;
                        left: ${config.area_shape === 'custom' ? 0 : config.area_left || 0}%;
                        width: ${config.area_shape === 'custom' ? 100 : config.area_width || 100}%;
                        height: ${config.area_shape === 'custom' ? 100 : config.area_height || 100}%;
                        border-radius: ${config.area_shape === 'circle' ? '50%' : '0'};
                        clip-path: ${
                            config.area_shape === 'custom' && config.custom_points && config.custom_points.length > 0
                            ? 'polygon(' + config.custom_points.map(p => p.x + '% ' + p.y + '%').join(', ') + ')'
                            : 'none'
                        };
                     `">

                <div x-show="currentTexts.length === 0 && currentLogos.length === 0 && context !== 'preview'" class="absolute inset-0 flex items-center justify-center pointer-events-auto">
                    <button @click="addFallbackText()" class="px-6 py-3 backdrop-blur-sm border rounded-2xl shadow-lg text-[10px] font-black uppercase tracking-widest transition-all {{ $isDark ? 'bg-gray-900/90 text-gray-300 border-gray-700 hover:bg-primary hover:text-gray-900 hover:border-primary' : 'bg-white/90 text-gray-800 border-gray-200 hover:bg-primary hover:text-white hover:border-primary' }}">
                        + Text hinzufügen
                    </button>
                </div>

                <template x-for="(textItem,index) in currentTexts" :key="'content-text-' + activeSide + '-' + (textItem.id || index)">
                    <div x-show="currentTexts[index] !== undefined" class="absolute touch-none pointer-events-auto group/item"
                         :style="currentTexts[index] ? `
                            left: ${config.area_shape === 'custom' ? (currentTexts[index].x || 50) : ((currentTexts[index].x || 50)-(config.area_left || 0))/(config.area_width || 100)* 100}%;
                            top: ${config.area_shape === 'custom' ? (currentTexts[index].y || 50) : ((currentTexts[index].y || 50)-(config.area_top || 0))/(config.area_height || 100)* 100}%;
                            transform-origin: ${currentTexts[index].align==='left'?'0% 50%':(currentTexts[index].align==='right'?'100% 50%':'50% 50%')};
                            transform: translate(${currentTexts[index].align==='left'?'0%':(currentTexts[index].align==='right'?'-100%':'-50%')},-50%) rotate(${currentTexts[index].rotation || 0}deg);
                            z-index: ${selectedIndex===index?100:10}
                         ` : 'display:none;'"
                         @mousedown.stop="startAction($event,'text',index,'drag')"
                         @touchstart.stop="startAction($event,'text',index,'drag')">

                        <div class="relative transition-all rounded-lg p-1.5" :class="selectedIndex===index && context!=='preview'?'border-[1.5px] border-primary border-dashed {{ $isDark ? 'bg-white/10' : 'bg-white/40' }} backdrop-blur-sm shadow-lg':'border-[1.5px] border-transparent hover:border-gray-500 {{ $isDark ? 'hover:bg-white/5' : 'hover:bg-white/10' }}'">

                            <template x-if="currentTexts[index] !== undefined">
                                <textarea x-model="textItem.text"
                                          :data-id="textItem.id"
                                          :rows="(textItem.text?.match(/\n/g) || []).length + 1"
                                          x-init="
                                                $watch('textItem.text', () => fitTextarea(textItem.id, $el));
                                                $watch('textItem.size', () => $nextTick(() => fitTextarea(textItem.id, $el)));
                                                $watch('textItem.font', () => setTimeout(() => fitTextarea(textItem.id, $el), 50));
                                                $nextTick(() => fitTextarea(textItem.id, $el));
                                          "
                                          @input="fitTextarea(textItem.id, $el)"
                                          wrap="off"
                                          class="bg-transparent font-bold resize-none overflow-hidden block whitespace-pre p-0 m-0 border-0 outline-none shadow-none ring-0 select-none text-center"
                                          :class="alignMap[textItem.align || 'center']"
                                          :style="`
                                                width: ${textDims[textItem.id]?.width || 'auto'};
                                                height: ${textDims[textItem.id]?.height || 'auto'};
                                                font-size: ${(20 * (textItem.size || 1)) * scaleFactor}px;
                                                font-family: ${fontMap[textItem.font] || 'Arial'};
                                                line-height: 1.15;
                                                color: rgba({{ $isDark ? '255,255,255' : '0,0,0' }}, 1);
                                                filter: {{ $isDark ? 'drop-shadow(0px 0px 5px rgba(255,255,255,0.4)) grayscale(100%) brightness(1.5)' : 'drop-shadow(0px 0px 3px rgba(0,0,0,0.3)) grayscale(100%)' }};
                                                -webkit-text-fill-color: {{ $isDark ? 'white' : 'black' }};
                                            `"
                                          placeholder="Text eingeben...">
                                </textarea>
                            </template>

                            <template x-if="selectedIndex===index && context!=='preview' && currentTexts[index] !== undefined">
                                <div>
                                    <div @mousedown.stop="startAction($event,'text',index,'drag')" @touchstart.stop.prevent="startAction($event,'text',index,'drag')" class="active-control-corner absolute -top-4 -left-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-move transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-400 hover:text-primary' : 'bg-white border-gray-100 text-gray-500 hover:text-primary' }}" style="z-index:200;"><x-heroicon-m-arrows-pointing-out class="w-4 h-4 rotate-45" /></div>
                                    <div @mousedown.stop="startAction($event,'text',index,'rotate')" @touchstart.stop.prevent="startAction($event,'text',index,'rotate')" class="active-control-corner absolute -top-4 -right-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-alias transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-primary hover:text-primary-dark' : 'bg-white border-gray-100 text-primary hover:text-primary-dark' }}" style="z-index:200;"><x-heroicon-m-arrow-path class="w-4 h-4" /></div>
                                    <div @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()" class="active-control-corner absolute -bottom-4 -left-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-pointer transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-red-400 hover:text-red-500' : 'bg-white border-gray-100 text-red-500 hover:text-red-700' }}" style="z-index:200;"><x-heroicon-m-trash class="w-4 h-4" /></div>
                                    <div @mousedown.stop="startAction($event,'text',index,'resize')" @touchstart.stop.prevent="startAction($event,'text',index,'resize')" class="active-control-corner absolute -bottom-4 -right-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-se-resize transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-primary hover:text-primary-dark' : 'bg-white border-gray-100 text-primary hover:text-primary-dark' }}" style="z-index:200;"><svg class="w-4 h-4 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-for="(logoItem,index) in currentLogos" :key="'content-logo-' + activeSide + '-' + (logoItem.id || index)">
                    <div x-show="currentLogos[index] !== undefined" class="absolute touch-none pointer-events-auto group/item"
                         :style="currentLogos[index] ? `
                            left: ${config.area_shape === 'custom' ? (currentLogos[index].x || 50) : ((currentLogos[index].x || 50)-(config.area_left || 0))/(config.area_width || 100)* 100}%;
                            top: ${config.area_shape === 'custom' ? (currentLogos[index].y || 50) : ((currentLogos[index].y || 50)-(config.area_top || 0))/(config.area_height || 100)* 100}%;
                            width: ${((currentLogos[index].size || 100) * scaleFactor)}px;
                            transform: translate(-50%,-50%) rotate(${currentLogos[index].rotation || 0}deg);
                            z-index: ${selectedIndex===index?100:10}
                         ` : 'display:none;'"
                         @mousedown.stop="startAction($event,'logo',index,'drag')"
                         @touchstart.stop="startAction($event,'logo',index,'drag')">

                        <div class="relative transition-all rounded-lg ring-2" :class="selectedIndex===index && context!=='preview'?'ring-primary {{ $isDark ? 'bg-white/10' : 'bg-white/40' }} backdrop-blur-sm shadow-lg':'ring-transparent hover:ring-gray-500 {{ $isDark ? 'hover:bg-white/5' : 'hover:bg-white/10' }}'">
                            <template x-if="currentLogos[index] !== undefined">
                                <img crossorigin="anonymous" :src="currentLogos[index].url" class="w-full h-auto pointer-events-none opacity-90 drop-shadow-md rounded-lg {{ $isDark ? 'filter invert brightness-0' : '' }}">
                            </template>

                            <template x-if="selectedIndex===index && context!=='preview' && currentLogos[index] !== undefined">
                                <div>
                                    <div @mousedown.stop="startAction($event,'logo',index,'drag')" @touchstart.stop.prevent="startAction($event,'logo',index,'drag')" class="active-control-corner absolute -top-4 -left-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-move transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-400 hover:text-primary' : 'bg-white border-gray-100 text-gray-500 hover:text-primary' }}" style="z-index:200;"><x-heroicon-m-arrows-pointing-out class="w-4 h-4 rotate-45" /></div>
                                    <div @mousedown.stop="startAction($event,'logo',index,'rotate')" @touchstart.stop.prevent="startAction($event,'logo',index,'rotate')" class="active-control-corner absolute -top-4 -right-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-alias transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-primary hover:text-primary-dark' : 'bg-white border-gray-100 text-primary hover:text-primary-dark' }}" style="z-index:200;"><x-heroicon-m-arrow-path class="w-4 h-4" /></div>
                                    <div @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()" class="active-control-corner absolute -bottom-4 -left-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-pointer transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-red-400 hover:text-red-500' : 'bg-white border-gray-100 text-red-500 hover:text-red-700' }}" style="z-index:200;"><x-heroicon-m-trash class="w-4 h-4" /></div>
                                    <div @mousedown.stop="startAction($event,'logo',index,'resize')" @touchstart.stop.prevent="startAction($event,'logo',index,'resize')" class="active-control-corner absolute -bottom-4 -right-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-se-resize transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-primary hover:text-primary-dark' : 'bg-white border-gray-100 text-primary hover:text-primary-dark' }}" style="z-index:200;"><svg class="w-4 h-4 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- KOMPAKTE SCHWEBENDE WERKZEUGLEISTE ALS FALLBACK FÜR ABGESCHNITTENE ICONS --}}
            <div x-show="selectedIndex !== null && showDrawingBoard && context !== 'preview'"
                 x-transition
                 class="absolute bottom-6 left-1/2 -translate-x-1/2 backdrop-blur-xl shadow-2xl border rounded-full px-4 py-2 flex items-center gap-3 sm:gap-4 z-[200] pointer-events-auto schwebender-werkzeugkasten {{ $isDark ? 'bg-gray-950/90 border-gray-700' : 'bg-gray-900/90 border-gray-700' }}">

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'drag')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'drag')"
                     class="cursor-move text-gray-400 hover:text-white hover:bg-gray-800 transition-colors p-2 rounded-full" title="Verschieben">
                    <x-heroicon-m-arrows-pointing-out class="w-5 h-5" />
                </div>

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'rotate')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'rotate')"
                     class="cursor-alias text-gray-400 hover:text-primary hover:bg-primary/10 transition-colors p-2 rounded-full" title="Rotieren">
                    <x-heroicon-m-arrow-path class="w-5 h-5" />
                </div>

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'resize')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'resize')"
                     class="cursor-se-resize text-gray-400 hover:text-primary hover:bg-primary/10 transition-colors p-2 rounded-full" title="Skalieren">
                    <svg class="w-5 h-5 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="w-px h-6 bg-gray-700 mx-1"></div>

                <div @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()"
                     class="cursor-pointer text-gray-400 hover:text-red-400 hover:bg-red-500/10 transition-colors p-2 rounded-full" title="Löschen">
                    <x-heroicon-m-trash class="w-5 h-5" />
                </div>
            </div>
        </div>

    </div>

    {{-- VORDERSEITE / RÜCKSEITE SWITCH (Direkt unter dem Rahmen) --}}
    <div x-show="config.has_back_side" x-cloak class="mt-6 shadow-sm border rounded-full p-1 flex items-center justify-center relative z-50 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}">
        <button @click="activeSide = 'front'"
                :class="activeSide === 'front' ? 'bg-primary text-white shadow-md' : '{{ $isDark ? 'text-gray-500 hover:text-white' : 'text-gray-500 hover:text-gray-800' }}'"
                class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-300 w-36 text-center">
            Vorderseite
        </button>
        <button @click="activeSide = 'back'; if (texts_back.length === 0 && logos_back.length === 0) { addFallbackText(); }"
                :class="activeSide === 'back' ? 'bg-purple-500 text-white shadow-md' : '{{ $isDark ? 'text-gray-500 hover:text-white' : 'text-gray-500 hover:text-gray-800' }}'"
                class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-300 w-36 text-center">
            Rückseite
        </button>
    </div>

    {{-- GLOBALE EINSTELLUNGEN (Toolbar darunter) --}}
    <div x-show="selectedIndex !== null && showDrawingBoard && context !== 'preview'" class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mt-6 shadow-[0_10px_30px_rgba(0,0,0,0.05)] border p-2 sm:p-3 rounded-[1.5rem] animate-fade-in-up relative z-50 w-full max-w-[98vw] md:max-w-xl mx-auto {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}" x-cloak>

        <template x-if="selectedType === 'text' && currentTexts[selectedIndex] !== undefined">
            <div class="flex items-center gap-1 sm:gap-2">
                <div class="relative">
                    <button @click="showFontMenu = !showFontMenu; showSizeMenu = false; showAlignMenu = false; showPosMenu = false" class="flex flex-col items-center px-4 py-2 rounded-xl transition-colors group relative {{ $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-50' }}" :class="showFontMenu ? '{{ $isDark ? 'bg-gray-800 text-primary' : 'bg-gray-100 text-primary' }}' : 'text-gray-500'">
                        <div class="flex items-center justify-center w-6 h-6 font-serif font-black text-lg leading-none">Aa</div>
                        <span class="text-[9px] font-black uppercase tracking-widest mt-1">Schrift</span>
                    </button>
                    <div x-show="showFontMenu" @click.outside="showFontMenu = false" class="absolute bottom-[calc(100%+12px)] left-0 w-56 rounded-2xl shadow-2xl border overflow-hidden z-[100] flex flex-col max-h-64 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}">
                        <div class="overflow-y-auto p-2 custom-scrollbar space-y-1">
                            <template x-for="(fontName, fontKey) in fontMap">
                                <button @click="currentTexts[selectedIndex].font = fontKey; showFontMenu = false; updateTexture()" class="w-full text-left px-4 py-2.5 text-sm rounded-xl truncate transition-colors {{ $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-50' }}" :style="{ fontFamily: fontName }" :class="currentTexts[selectedIndex].font === fontKey ? 'text-primary font-bold {{ $isDark ? 'bg-primary/10' : 'bg-primary/5' }}' : '{{ $isDark ? 'text-gray-300' : 'text-gray-700' }}'"><span x-text="fontKey"></span></button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <button @click="showAlignMenu = !showAlignMenu; showFontMenu = false; showSizeMenu = false; showPosMenu = false" class="flex flex-col items-center px-4 py-2 rounded-xl transition-colors group relative {{ $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-50' }}" :class="showAlignMenu ? '{{ $isDark ? 'bg-gray-800 text-primary' : 'bg-gray-100 text-primary' }}' : 'text-gray-500'">
                        <div class="w-6 h-6 flex items-center justify-center">
                            <template x-if="currentTexts[selectedIndex].align === 'left'"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></template>
                            <template x-if="currentTexts[selectedIndex].align === 'center' || !currentTexts[selectedIndex].align"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></template>
                            <template x-if="currentTexts[selectedIndex].align === 'right'"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></template>
                        </div>
                        <span class="text-[9px] font-black uppercase tracking-widest mt-1">Format</span>
                    </button>
                    <div x-show="showAlignMenu" @click.outside="showAlignMenu = false" class="absolute bottom-[calc(100%+12px)] left-1/2 -translate-x-1/2 rounded-2xl shadow-2xl border p-2 z-[100] flex gap-1 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}">
                        <button @click="currentTexts[selectedIndex].align = 'left'; showAlignMenu = false; updateTexture()" class="p-3 rounded-xl hover:text-primary transition-colors {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></button>
                        <button @click="currentTexts[selectedIndex].align = 'center'; showAlignMenu = false; updateTexture()" class="p-3 rounded-xl hover:text-primary transition-colors {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></button>
                        <button @click="currentTexts[selectedIndex].align = 'right'; showAlignMenu = false; updateTexture()" class="p-3 rounded-xl hover:text-primary transition-colors {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></button>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="selectedType === 'text' && currentTexts[selectedIndex] !== undefined">
            <div class="w-px h-10 mx-1 sm:mx-2 {{ $isDark ? 'bg-gray-800' : 'bg-gray-200' }}"></div>
        </template>

        <div class="relative">
            <template x-if="selectedType !== null && ((selectedType === 'text' && currentTexts[selectedIndex] !== undefined) || (selectedType === 'logo' && currentLogos[selectedIndex] !== undefined))">
                <button @click="showSizeMenu = !showSizeMenu; showFontMenu = false; showAlignMenu = false; showPosMenu = false" class="flex flex-col items-center px-4 py-2 rounded-xl transition-colors group {{ $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-50' }}" :class="showSizeMenu ? '{{ $isDark ? 'bg-gray-800 text-primary' : 'bg-gray-100 text-primary' }}' : 'text-gray-500'">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span class="text-[9px] font-black uppercase tracking-widest mt-1">Größe</span>
                </button>
            </template>
            <div x-show="showSizeMenu" @click.outside="showSizeMenu = false" class="absolute bottom-[calc(100%+12px)] left-1/2 -translate-x-1/2 w-56 rounded-2xl shadow-2xl border p-5 z-[100] {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}" x-cloak>
                <div class="flex justify-between text-[10px] font-black tracking-widest text-gray-400 uppercase mb-3"><span>Klein</span><span>Groß</span></div>

                <template x-if="selectedType === 'text' && currentTexts[selectedIndex] !== undefined">
                    <input type="range" min="0.1" max="5" step="0.05" x-model="currentTexts[selectedIndex].size" @input="updateTexture()" class="w-full accent-primary h-2 rounded-lg appearance-none cursor-pointer {{ $isDark ? 'bg-gray-800' : 'bg-gray-100' }}">
                </template>
                <template x-if="selectedType === 'logo' && currentLogos[selectedIndex] !== undefined">
                    <input type="range" min="10" max="500" step="1" x-model="currentLogos[selectedIndex].size" @input="updateTexture()" class="w-full accent-primary h-2 rounded-lg appearance-none cursor-pointer {{ $isDark ? 'bg-gray-800' : 'bg-gray-100' }}">
                </template>
            </div>
        </div>

        <div class="w-px h-10 mx-1 sm:mx-2 {{ $isDark ? 'bg-gray-800' : 'bg-gray-200' }}"></div>

        <button @click="duplicateElement" class="flex flex-col items-center px-4 py-2 rounded-xl text-gray-500 transition-colors {{ $isDark ? 'hover:bg-gray-800 hover:text-gray-300' : 'hover:bg-gray-50 hover:text-gray-700' }}">
            <x-heroicon-m-square-2-stack class="w-6 h-6" />
            <span class="text-[9px] font-black uppercase tracking-widest mt-1">Kopie</span>
        </button>

        <div class="w-px h-10 mx-1 sm:mx-2 {{ $isDark ? 'bg-gray-800' : 'bg-gray-200' }}"></div>

        <div class="flex items-center gap-1">
            <button @click="centerHorizontal" class="flex flex-col items-center px-3 py-2 rounded-xl text-gray-500 transition-colors {{ $isDark ? 'hover:bg-gray-800 hover:text-gray-300' : 'hover:bg-gray-50 hover:text-gray-700' }}" title="Horizontal zentrieren">
                <x-heroicon-m-pause class="w-5 h-5 rotate-90" />
            </button>
            <button @click="centerVertical" class="flex flex-col items-center px-3 py-2 rounded-xl text-gray-500 transition-colors {{ $isDark ? 'hover:bg-gray-800 hover:text-gray-300' : 'hover:bg-gray-50 hover:text-gray-700' }}" title="Vertikal zentrieren">
                <x-heroicon-m-pause class="w-5 h-5" />
            </button>
            <button @click="centerBoth" class="flex flex-col items-center px-4 py-2 bg-primary/10 hover:bg-primary/20 text-primary rounded-xl transition-colors shadow-sm" title="Exakt in die Mitte">
                <x-heroicon-m-viewfinder-circle class="w-5 h-5" />
            </button>
        </div>
    </div>

</div>
