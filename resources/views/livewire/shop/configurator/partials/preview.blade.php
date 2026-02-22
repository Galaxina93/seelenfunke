<div class="bg-gray-50 flex flex-col items-center sticky top-0 z-40 border-b border-gray-200 shadow-sm shrink-0 select-none pb-6" x-ref="stage">

    {{-- KUNDEN TOGGLE 2D / 3D --}}
    <div x-show="config.modelPath" class="mb-4 bg-white p-1 rounded-full shadow-sm border border-gray-200 inline-flex items-center gap-1">
        <button @click="showDrawingBoard = true" class="px-6 py-2 rounded-full text-xs font-bold transition-colors" :class="showDrawingBoard ? 'bg-primary text-white shadow' : 'text-gray-500 hover:bg-gray-100'">2D Editor</button>
        <button @click="showDrawingBoard = false" class="px-6 py-2 rounded-full text-xs font-bold transition-colors" :class="!showDrawingBoard ? 'bg-primary text-white shadow' : 'text-gray-500 hover:bg-gray-100'">3D Ansicht</button>
    </div>

    <div class="relative w-full max-w-[455px] md:max-w-[600px] aspect-square bg-slate-100 rounded-2xl shadow-lg overflow-hidden border-4 border-white ring-1 ring-gray-100"
         :style="(!showDrawingBoard && config.bgPath) ? `background-image: url('${config.bgPath}'); background-size: cover; background-position: center;` : ''"
         x-ref="container"
         @mousedown="deselectAll($event)"
         @touchstart="deselectAll($event)">

        {{-- 3D Ansicht --}}
        <div wire:ignore x-show="config.modelPath" x-ref="container3d" class="absolute inset-0 w-full h-full z-10 cursor-move" :class="showDrawingBoard ? 'opacity-0 pointer-events-none' : 'opacity-100'"></div>

        {{-- 2D Ansicht & Zeichenbrett --}}
        <div x-show="showDrawingBoard || !config.modelPath" class="absolute inset-0 w-full h-full z-20 bg-white pointer-events-auto transition-opacity duration-300">

            <template x-if="config.fallbackImg">
                <img :src="config.fallbackImg" class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-60">
            </template>

            <div class="absolute border-2 border-green-500 bg-green-500/10 pointer-events-none transition-all duration-300"
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
                    box-shadow: ${config.area_shape === 'custom' ? 'none' : '0 0 0 9999px rgba(255,255,255,0.5)'};
                 `">
            </div>

            {{-- Hilfslinien fürs Snapping --}}
            <div x-show="showGuideX" class="absolute top-0 bottom-0 border-l border-primary/50 border-dashed pointer-events-none" style="left: 50%; z-index: 10;"></div>
            <div x-show="showGuideY" class="absolute left-0 right-0 border-t border-primary/50 border-dashed pointer-events-none" style="top: 50%; z-index: 10;"></div>

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

                <template x-for="(textItem,index) in texts" :key="'content-text-'+textItem.id">
                    <div class="absolute touch-none pointer-events-auto group"
                         :style="`
                    left: ${config.area_shape === 'custom' ? (textItem.x || 50) : ((textItem.x || 50)-(config.area_left || 0))/(config.area_width || 100)* 100}%;
                    top: ${config.area_shape === 'custom' ? (textItem.y || 50) : ((textItem.y || 50)-(config.area_top || 0))/(config.area_height || 100)* 100}%;
                    transform-origin: ${textItem.align==='left'?'0% 50%':(textItem.align==='right'?'100% 50%':'50% 50%')};
                    transform: translate(${textItem.align==='left'?'0%':(textItem.align==='right'?'-100%':'-50%')},-50%) rotate(${textItem.rotation || 0}deg);
                    z-index: ${selectedIndex===index?100:10}
                 `"
                         @mousedown.stop="startAction($event,'text',index,'drag')"
                         @touchstart.stop="startAction($event,'text',index,'drag')">

                        <div class="relative transition-all rounded p-1" :class="selectedIndex===index && context!=='preview'?'border-2 border-primary border-dashed ring-4 ring-primary/20 bg-white/50 backdrop-blur-sm shadow-sm':'border-2 border-transparent'">

                            <template x-if="texts[index]">
                        <textarea x-model="texts[index].text"
                                  :data-id="texts[index]?.id"
                                  :rows="(texts[index]?.text?.match(/\n/g) || []).length + 1"
                                  x-init="
                                $watch('texts[index].text', () => texts[index] && fitTextarea(texts[index].id, $el));
                                $watch('texts[index].size', () => texts[index] && $nextTick(() => fitTextarea(texts[index].id, $el)));
                                $watch('texts[index].font', () => texts[index] && setTimeout(() => fitTextarea(texts[index].id, $el), 50));
                                $nextTick(() => texts[index] && fitTextarea(texts[index].id, $el));
                            "
                                  @input="texts[index] && fitTextarea(texts[index].id, $el)"
                                  wrap="off"
                                  class="bg-transparent font-bold resize-none overflow-hidden block whitespace-pre p-0 m-0 border-0 outline-none shadow-none ring-0 select-none text-center"
                                  :class="alignMap[texts[index]?.align || 'center']"
                                  :style="`
                                width: ${texts[index] && textDims[texts[index].id]?.width || 'auto'};
                                height: ${texts[index] && textDims[texts[index].id]?.height || 'auto'};
                                font-size: ${(20 * (texts[index]?.size || 1)) * scaleFactor}px;
                                font-family: ${texts[index] && fontMap[texts[index].font] || 'Arial'};
                                line-height: 1.15;
                                color: rgba(255, 255, 255, 0.85);
                                text-shadow: 0 0 1px rgba(255,255,255,0.3), 0 0 2px rgba(255,255,255,0.2);
                                filter: drop-shadow(0px 0px 1px rgba(0,0,0,0.1));
                                mix-blend-mode: overlay;
                            `"
                                  placeholder="Text eingeben...">
                        </textarea>
                            </template>

                            <template x-if="selectedIndex===index && context!=='preview'">
                                <div>
                                    <div @mousedown.stop="startAction($event,'text',index,'drag')" @touchstart.stop.prevent="startAction($event,'text',index,'drag')" class="active-control-corner absolute -top-4 -left-4 w-8 h-8 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center cursor-move text-slate-600" style="z-index:200;"><x-heroicon-m-arrows-pointing-out class="w-4 h-4 rotate-45" /></div>
                                    <div @mousedown.stop="startAction($event,'text',index,'rotate')" @touchstart.stop.prevent="startAction($event,'text',index,'rotate')" class="active-control-corner absolute -top-4 -right-4 w-8 h-8 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center cursor-alias text-primary" style="z-index:200;"><x-heroicon-m-arrow-path class="w-4 h-4" /></div>
                                    <div @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()" class="active-control-corner absolute -bottom-4 -left-4 w-8 h-8 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center cursor-pointer text-red-500" style="z-index:200;"><x-heroicon-m-trash class="w-4 h-4" /></div>
                                    <div @mousedown.stop="startAction($event,'text',index,'resize')" @touchstart.stop.prevent="startAction($event,'text',index,'resize')" class="active-control-corner absolute -bottom-4 -right-4 w-8 h-8 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center cursor-se-resize text-primary" style="z-index:200;"><svg class="w-4 h-4 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-for="(logoItem,index) in logos" :key="'content-logo-'+logoItem.id">
                    <div class="absolute touch-none pointer-events-auto group"
                         :style="`
                    left: ${config.area_shape === 'custom' ? (logoItem.x || 50) : ((logoItem.x || 50)-(config.area_left || 0))/(config.area_width || 100)* 100}%;
                    top: ${config.area_shape === 'custom' ? (logoItem.y || 50) : ((logoItem.y || 50)-(config.area_top || 0))/(config.area_height || 100)* 100}%;
                    width: ${(logoItem.size * scaleFactor)}px;
                    transform: translate(-50%,-50%) rotate(${logoItem.rotation || 0}deg);
                    z-index: ${selectedIndex===index?100:10}
                 `"
                         @mousedown.stop="startAction($event,'logo',index,'drag')"
                         @touchstart.stop="startAction($event,'logo',index,'drag')">

                        <div class="relative transition-all rounded p-1" :class="selectedIndex===index && context!=='preview'?'border-2 border-primary border-dashed ring-4 ring-primary/20 bg-white/50 backdrop-blur-sm shadow-sm':'border-2 border-transparent'">
                            <img :src="logoItem.url" class="w-full h-auto pointer-events-none opacity-80 mix-blend-multiply">

                            <template x-if="selectedIndex===index && context!=='preview'">
                                <div>
                                    <div @mousedown.stop="startAction($event,'logo',index,'drag')" @touchstart.stop.prevent="startAction($event,'logo',index,'drag')" class="active-control-corner absolute -top-4 -left-4 w-8 h-8 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center cursor-move text-slate-600" style="z-index:200;"><x-heroicon-m-arrows-pointing-out class="w-4 h-4 rotate-45" /></div>
                                    <div @mousedown.stop="startAction($event,'logo',index,'rotate')" @touchstart.stop.prevent="startAction($event,'logo',index,'rotate')" class="active-control-corner absolute -top-4 -right-4 w-8 h-8 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center cursor-alias text-primary" style="z-index:200;"><x-heroicon-m-arrow-path class="w-4 h-4" /></div>
                                    <div @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()" class="active-control-corner absolute -bottom-4 -left-4 w-8 h-8 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center cursor-pointer text-red-500" style="z-index:200;"><x-heroicon-m-trash class="w-4 h-4" /></div>
                                    <div @mousedown.stop="startAction($event,'logo',index,'resize')" @touchstart.stop.prevent="startAction($event,'logo',index,'resize')" class="active-control-corner absolute -bottom-4 -right-4 w-8 h-8 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center cursor-se-resize text-primary" style="z-index:200;"><svg class="w-4 h-4 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- KOMPAKTE SCHWEBENDE WERKZEUGLEISTE ALS FALLBACK FÜR ABGESCHNITTENE ICONS --}}
            <div x-show="selectedIndex !== null && showDrawingBoard && context !== 'preview'"
                 x-transition
                 class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-white/95 backdrop-blur-md shadow-xl border border-slate-200 rounded-full px-3 py-1.5 flex items-center gap-2 sm:gap-3 z-[200] pointer-events-auto schwebender-werkzeugkasten">

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'drag')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'drag')"
                     class="cursor-move text-slate-600 hover:text-primary transition-colors p-2 rounded-full hover:bg-slate-100" title="Verschieben">
                    <x-heroicon-m-arrows-pointing-out class="w-5 h-5" />
                </div>

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'rotate')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'rotate')"
                     class="cursor-alias text-slate-600 hover:text-primary transition-colors p-2 rounded-full hover:bg-slate-100" title="Rotieren">
                    <x-heroicon-m-arrow-path class="w-5 h-5" />
                </div>

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'resize')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'resize')"
                     class="cursor-se-resize text-slate-600 hover:text-primary transition-colors p-2 rounded-full hover:bg-slate-100" title="Skalieren">
                    <svg class="w-5 h-5 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="w-px h-6 bg-slate-300 mx-1"></div>

                <div @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()"
                     class="cursor-pointer text-slate-600 hover:text-red-500 transition-colors p-2 rounded-full hover:bg-red-50" title="Löschen">
                    <x-heroicon-m-trash class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>

    {{-- GLOBALE EINSTELLUNGEN (Toolbar darunter) --}}
    <div x-show="selectedIndex !== null && showDrawingBoard && context !== 'preview'" class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mt-4 bg-white shadow-xl border border-slate-100 p-2 sm:p-3 rounded-2xl animate-fade-in-up relative z-50 w-full max-w-[98vw] md:max-w-xl mx-auto" x-cloak>
        <template x-if="selectedType === 'text' && texts[selectedIndex]">
            <div class="flex items-center gap-1 sm:gap-2">
                <div class="relative">
                    <button @click="showFontMenu = !showFontMenu; showSizeMenu = false; showAlignMenu = false; showPosMenu = false" class="flex flex-col items-center px-3 py-2 hover:bg-slate-50 rounded-xl transition-colors group relative" :class="showFontMenu ? 'bg-slate-100 text-primary' : 'text-slate-500'">
                        <div class="flex items-center justify-center w-6 h-6 font-serif font-black text-lg leading-none">Aa</div>
                        <span class="text-[9px] font-bold uppercase mt-1 text-slate-500">Schrift</span>
                    </button>
                    <div x-show="showFontMenu" @click.outside="showFontMenu = false" class="absolute bottom-full mb-3 left-0 w-48 bg-white rounded-xl shadow-xl border overflow-hidden z-[100] flex flex-col max-h-48">
                        <div class="overflow-y-auto p-1 custom-scrollbar">
                            <template x-for="(fontName, fontKey) in fontMap">
                                <button @click="texts[selectedIndex].font = fontKey; showFontMenu = false; updateTexture()" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 rounded-lg truncate transition-colors" :style="{ fontFamily: fontName }" :class="texts[selectedIndex].font === fontKey ? 'text-primary font-bold bg-primary/5' : 'text-slate-700'"><span x-text="fontKey"></span></button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <button @click="showAlignMenu = !showAlignMenu; showFontMenu = false; showSizeMenu = false; showPosMenu = false" class="flex flex-col items-center px-3 py-2 hover:bg-slate-50 rounded-xl transition-colors group relative" :class="showAlignMenu ? 'bg-slate-100 text-primary' : 'text-slate-500'">
                        <div class="w-6 h-6 flex items-center justify-center">
                            <template x-if="texts[selectedIndex].align === 'left'"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></template>
                            <template x-if="texts[selectedIndex].align === 'center' || !texts[selectedIndex].align"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></template>
                            <template x-if="texts[selectedIndex].align === 'right'"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></template>
                        </div>
                        <span class="text-[9px] font-bold uppercase mt-1 text-slate-500">Format</span>
                    </button>
                    <div x-show="showAlignMenu" @click.outside="showAlignMenu = false" class="absolute bottom-full mb-3 left-1/2 -translate-x-1/2 bg-white rounded-xl shadow-xl border p-1.5 z-[100] flex gap-1">
                        <button @click="texts[selectedIndex].align = 'left'; showAlignMenu = false; updateTexture()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-primary"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></button>
                        <button @click="texts[selectedIndex].align = 'center'; showAlignMenu = false; updateTexture()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-primary"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></button>
                        <button @click="texts[selectedIndex].align = 'right'; showAlignMenu = false; updateTexture()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-primary"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></button>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="selectedType === 'text'">
            <div class="w-px h-8 bg-slate-200 mx-0.5 sm:mx-1"></div>
        </template>

        <div class="relative">
            <template x-if="((selectedType === 'text' && texts[selectedIndex]) || (selectedType === 'logo' && logos[selectedIndex]))">
                <button @click="showSizeMenu = !showSizeMenu; showFontMenu = false; showAlignMenu = false; showPosMenu = false" class="flex flex-col items-center px-3 py-2 hover:bg-slate-50 rounded-xl transition-colors group" :class="showSizeMenu ? 'bg-slate-100 text-primary' : 'text-slate-500'">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span class="text-[9px] font-bold uppercase mt-1 text-slate-500">Größe</span>
                </button>
            </template>
            <div x-show="showSizeMenu" @click.outside="showSizeMenu = false" class="absolute bottom-full mb-3 left-1/2 -translate-x-1/2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 p-4 z-[100]" x-cloak>
                <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase mb-2"><span>Klein</span><span>Groß</span></div>
                <template x-if="selectedType === 'text' && texts[selectedIndex]">
                    <input type="range" min="0.1" max="5" step="0.05" x-model="texts[selectedIndex].size" @input="updateTexture()" class="w-full accent-primary h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer">
                </template>
                <template x-if="selectedType === 'logo' && logos[selectedIndex]">
                    <input type="range" min="10" max="500" step="1" x-model="logos[selectedIndex].size" @input="updateTexture()" class="w-full accent-primary h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer">
                </template>
            </div>
        </div>

        <div class="w-px h-8 bg-slate-200 mx-0.5 sm:mx-1"></div>

        <button @click="duplicateElement" class="flex flex-col items-center px-3 py-2 hover:bg-slate-50 rounded-xl text-slate-500 transition-colors">
            <x-heroicon-m-square-2-stack class="w-6 h-6" />
            <span class="text-[9px] font-bold uppercase mt-1 text-slate-500">Kopie</span>
        </button>

        <div class="w-px h-8 bg-slate-200 mx-0.5 sm:mx-1"></div>

        <button @click="centerHorizontal" class="flex flex-col items-center px-3 py-2 hover:bg-slate-50 rounded-xl text-slate-500 transition-colors">
            <x-heroicon-m-pause class="w-6 h-6 rotate-90" />
            <span class="text-[9px] font-bold uppercase mt-1 text-slate-500">H-Mitte</span>
        </button>
        <button @click="centerVertical" class="flex flex-col items-center px-3 py-2 hover:bg-slate-50 rounded-xl text-slate-500 transition-colors">
            <x-heroicon-m-pause class="w-6 h-6" />
            <span class="text-[9px] font-bold uppercase mt-1 text-slate-500">V-Mitte</span>
        </button>
        <button @click="centerBoth" class="flex flex-col items-center px-3 py-2 bg-primary/10 hover:bg-primary/20 text-primary rounded-xl transition-colors">
            <x-heroicon-m-viewfinder-circle class="w-6 h-6" />
            <span class="text-[9px] font-bold uppercase mt-1 text-primary">Mitte</span>
        </button>
    </div>
</div>
