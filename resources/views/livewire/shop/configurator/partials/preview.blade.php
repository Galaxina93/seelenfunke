{{-- VORSCHAU & QUICK-CONTROLS --}}
<div class="bg-gray-50 flex flex-col items-center sticky top-0 z-40 border-b border-gray-200 shadow-sm shrink-0 select-none pb-6" x-ref="stage">

    {{-- Der Konfigurator-Container --}}
    <div class="relative w-full max-w-[455px] md:max-w-[600px] aspect-square bg-white rounded-t-xl shadow-lg overflow-hidden border-4 border-white ring-1 ring-gray-100"
         x-ref="container"
         @mousedown="deselectAll($event)"
         @touchstart="deselectAll($event)"
    >

        {{-- Hintergrund-Produktbild --}}
        @if($this->previewImage)
            <img src="{{ $this->previewImage }}" class="absolute inset-0 w-full h-full object-contain z-0 pointer-events-none">
        @else
            <div class="absolute inset-0 bg-gray-50 flex items-center justify-center text-gray-300">
                <span class="text-xs font-medium">Kein Bild verfügbar</span>
            </div>
        @endif

        {{-- Positionierungs-Hilfen --}}
        <div x-show="showVGuide" class="absolute left-1/2 top-0 bottom-0 w-0.5 bg-primary/40 z-30 pointer-events-none"></div>
        <div x-show="showHGuide" class="absolute top-1/2 left-0 right-0 h-0.5 bg-primary/40 z-30 pointer-events-none"></div>

        {{-- STAGE OVERLAY --}}
        <div id="stage-overlay" class="absolute inset-0 z-10 pointer-events-auto">

            {{-- Der grüne Rahmen (Visuelle Anzeige) --}}
            <div class="absolute border-2 border-green-500 bg-green-500/10 pointer-events-none"
                 :style="{
                            top: area.top + '%',
                            left: area.left + '%',
                            width: area.width + '%',
                            height: area.height + '%',
                            borderRadius: (area.shape === 'circle' ? '50%' : '0'),
                            boxShadow: '0 0 0 9999px rgba(239, 68, 68, 0.15)'
                         }">
            </div>

            {{-- EBENE 1: ECHTER INHALT (ABGESCHNITTEN) --}}
            <div class="absolute overflow-hidden pointer-events-none"
                 :style="{
                            top: area.top + '%',
                            left: area.left + '%',
                            width: area.width + '%',
                            height: area.height + '%',
                            borderRadius: (area.shape === 'circle' ? '50%' : '0')
                         }">

                {{-- TEXT CONTENT (Echt) --}}
                <template x-for="(textItem, index) in texts" :key="'content-text-'+textItem.id">
                    <div class="absolute touch-none pointer-events-auto"
                         style="transform: translate(-50%, -50%);"
                         :style="{
                            left: ((textItem.x - area.left) / area.width * 100) + '%',
                            top: ((textItem.y - area.top) / area.height * 100) + '%',
                            transform: `translate(-50%, -50%) rotate(${textItem.rotation || 0}deg)`
                         }"
                         @mousedown.stop="startAction($event, 'text', index, 'drag')"
                         @touchstart.stop="startAction($event, 'text', index, 'drag')">

                        <div class="border-2 border-transparent">
                            <textarea
                                x-model="texts[index].text"
                                x-init="fitTextarea($el)"
                                @input="fitTextarea($el)"
                                class="bg-transparent font-bold resize-none overflow-hidden block whitespace-pre p-0 m-0 border-0 outline-none shadow-none ring-0 focus:ring-0 focus:outline-none focus:border-none"
                                :class="alignMap[textItem.align]"
                                style="width: auto; min-width: 50px; background: transparent; appearance: none; -webkit-appearance: none;"
                                :style="`font-size: ${(13 * textItem.size) * scaleFactor}px;
                                         font-family: ${fontMap[textItem.font] || 'Arial'};
                                         color: rgba(255, 255, 255, 0.9);
                                         line-height: 1;
                                         height: auto;`"
                                placeholder="Text..."></textarea>
                        </div>
                    </div>
                </template>

                {{-- LOGO CONTENT (Echt) --}}
                <template x-for="(logoItem, index) in logos" :key="'content-logo-'+logoItem.id">
                    <div class="absolute touch-none pointer-events-auto"
                         style="transform: translate(-50%, -50%);"
                         :style="{
                            left: ((logoItem.x - area.left) / area.width * 100) + '%',
                            top: ((logoItem.y - area.top) / area.height * 100) + '%',
                            width: (logoItem.size * scaleFactor) + 'px',
                            transform: `translate(-50%, -50%) rotate(${logoItem.rotation || 0}deg)`
                         }"
                         @mousedown.stop="startAction($event, 'logo', index, 'drag')"
                         @touchstart.stop="startAction($event, 'logo', index, 'drag')">
                        <div class="relative p-1">
                            <img :src="logoItem.url" class="w-full h-auto pointer-events-none opacity-80 mix-blend-multiply">
                        </div>
                    </div>
                </template>
            </div>


            {{-- EBENE 2: STEUERUNG (NICHT ABGESCHNITTEN) --}}
            <div class="absolute pointer-events-none"
                 :style="{
                            top: area.top + '%',
                            left: area.left + '%',
                            width: area.width + '%',
                            height: area.height + '%',
                            overflow: 'visible'
                         }">

                {{-- AKTIVER TEXT RAHMEN --}}
                <template x-if="selectedType === 'text' && selectedIndex !== null && texts[selectedIndex]">
                    <div class="absolute"
                         style="transform: translate(-50%, -50%); z-index: 50;"
                         :style="{
                            left: ((texts[selectedIndex].x - area.left) / area.width * 100) + '%',
                            top: ((texts[selectedIndex].y - area.top) / area.height * 100) + '%',
                            transform: `translate(-50%, -50%) rotate(${texts[selectedIndex].rotation || 0}deg)`
                         }">

                        <div class="p-2 border-2 border-primary border-dashed relative rounded pointer-events-none">

                            <div class="absolute inset-0 pointer-events-none">
                                {{-- Drag --}}
                                <div @mousedown.stop="startAction($event, 'text', selectedIndex, 'drag')"
                                     @touchstart.stop.prevent="startAction($event, 'text', selectedIndex, 'drag')"
                                     class="absolute top-0 left-0 -translate-x-1/2 -translate-y-1/2 w-6 h-6 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center pointer-events-auto cursor-move z-50 hover:scale-110 transition-transform">
                                    <x-heroicon-m-arrows-pointing-out class="w-3 h-3 text-slate-600 rotate-45" />
                                </div>
                                {{-- Rotate --}}
                                <div @mousedown.stop="startAction($event, 'text', selectedIndex, 'rotate')"
                                     @touchstart.stop.prevent="startAction($event, 'text', selectedIndex, 'rotate')"
                                     class="absolute top-0 right-0 translate-x-1/2 -translate-y-1/2 w-6 h-6 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center pointer-events-auto cursor-alias text-primary z-50 hover:scale-110 transition-transform">
                                    <x-heroicon-m-arrow-path class="w-3.5 h-3.5" />
                                </div>
                                {{-- Delete --}}
                                <div @click.stop="$wire.removeText(selectedIndex)"
                                     @touchstart.stop.prevent="$wire.removeText(selectedIndex)"
                                     class="absolute bottom-0 left-0 -translate-x-1/2 translate-y-1/2 w-6 h-6 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center pointer-events-auto cursor-pointer text-red-500 z-50 hover:scale-110 transition-transform">
                                    <x-heroicon-m-trash class="w-3.5 h-3.5" />
                                </div>
                                {{-- Resize --}}
                                <div @mousedown.stop="startAction($event, 'text', selectedIndex, 'resize')"
                                     @touchstart.stop.prevent="startAction($event, 'text', selectedIndex, 'resize')"
                                     class="absolute rotate-90 bottom-0 right-0 translate-x-1/2 translate-y-1/2 w-6 h-6 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center pointer-events-auto cursor-se-resize text-primary z-50 hover:scale-110 transition-transform">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                            </div>

                            <p class="font-bold whitespace-pre opacity-0 pointer-events-none block m-0 p-0 border-0 outline-none flex items-center justify-center"
                               :class="alignMap[texts[selectedIndex].align]"
                               :style="`font-size: ${(13 * texts[selectedIndex].size) * scaleFactor}px;
                                        font-family: ${fontMap[texts[selectedIndex].font] || 'Arial'};
                                        line-height: 2;
                                        min-width: 50px;`"
                               x-text="texts[selectedIndex].text ? texts[selectedIndex].text : 'Text...'">
                            </p>
                        </div>
                    </div>
                </template>

                {{-- AKTIVER LOGO RAHMEN --}}
                <template x-if="selectedType === 'logo' && selectedIndex !== null && logos[selectedIndex]">
                    <div class="absolute"
                         style="transform: translate(-50%, -50%); z-index: 50;"
                         :style="{
                            left: ((logos[selectedIndex].x - area.left) / area.width * 100) + '%',
                            top: ((logos[selectedIndex].y - area.top) / area.height * 100) + '%',
                            width: (logos[selectedIndex].size * scaleFactor) + 'px',
                            transform: `translate(-50%, -50%) rotate(${logos[selectedIndex].rotation || 0}deg)`
                         }">

                        <div class="relative w-full h-full p-1 border-2 border-primary border-dashed">
                            <div class="absolute inset-0 pointer-events-none">
                                <div @mousedown.stop="startAction($event, 'logo', selectedIndex, 'drag')"
                                     @touchstart.stop.prevent="startAction($event, 'logo', selectedIndex, 'drag')"
                                     class="absolute top-0 left-0 -translate-x-1/2 -translate-y-1/2 w-6 h-6 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center pointer-events-auto cursor-move z-50">
                                    <x-heroicon-m-arrows-pointing-out class="w-3 h-3 text-slate-600 rotate-45" />
                                </div>
                                <div @mousedown.stop="startAction($event, 'logo', selectedIndex, 'rotate')"
                                     @touchstart.stop.prevent="startAction($event, 'logo', selectedIndex, 'rotate')"
                                     class="absolute top-0 right-0 translate-x-1/2 -translate-y-1/2 w-6 h-6 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center pointer-events-auto cursor-alias text-primary z-50">
                                    <x-heroicon-m-arrow-path class="w-3.5 h-3.5" />
                                </div>
                                <div @click.stop="$wire.toggleLogo('saved', logos[selectedIndex].value)"
                                     @touchstart.stop.prevent="$wire.toggleLogo('saved', logos[selectedIndex].value)"
                                     class="absolute bottom-0 left-0 -translate-x-1/2 translate-y-1/2 w-6 h-6 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center pointer-events-auto cursor-pointer text-red-500 z-50">
                                    <x-heroicon-m-trash class="w-3.5 h-3.5" />
                                </div>
                                <div @mousedown.stop="startAction($event, 'logo', selectedIndex, 'resize')"
                                     @touchstart.stop.prevent="startAction($event, 'logo', selectedIndex, 'resize')"
                                     class="absolute bottom-0 right-0 translate-x-1/2 translate-y-1/2 w-6 h-6 rotate-90 bg-white rounded-full shadow-lg border border-slate-200 flex items-center justify-center pointer-events-auto cursor-se-resize text-primary z-50">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                            </div>
                            <img :src="logos[selectedIndex].url" class="w-full h-auto opacity-0 pointer-events-none">
                        </div>
                    </div>
                </template>
            </div>

        </div>
    </div>

    {{-- QUICK-CONTROL LEISTE (Mobil optimiert mit Labels) --}}
    <div x-show="selectedIndex !== null && context !== 'preview'"
         class="flex items-center justify-center gap-1 sm:gap-3 mt-4 bg-white shadow-xl border border-slate-100 p-1.5 sm:p-2 rounded-2xl animate-fade-in-up relative z-50 max-w-[98vw]">

        <template x-if="selectedType === 'text' && selectedIndex !== null && texts[selectedIndex]">
            <div class="relative">
                <button @click="showFontMenu = !showFontMenu; showSizeMenu = false; showAlignMenu = false" class="flex flex-col items-center p-2 hover:bg-slate-50 rounded-xl transition-colors group relative" :class="showFontMenu ? 'bg-slate-100 text-primary' : 'text-slate-500'">
                    <div class="flex items-center justify-center w-6 h-6 font-serif font-black text-lg leading-none">Aa</div>
                    <span class="text-[8px] font-bold uppercase tracking-tighter mt-1 sm:hidden">Schrift</span>
                    <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap hidden sm:block">Schriftart</span>
                </button>
                <div x-show="showFontMenu" @click.outside="showFontMenu = false" class="absolute bottom-full mb-3 left-0 w-48 bg-white rounded-xl shadow-xl border border-slate-100 overflow-hidden z-[60] flex flex-col max-h-48">
                    <div class="overflow-y-auto p-1 scrollbar-thin scrollbar-thumb-slate-200">
                        <template x-for="(fontName, fontKey) in fontMap">
                            <button @click="texts[selectedIndex].font = fontKey; showFontMenu = false" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 rounded-lg transition-colors truncate" :style="{ fontFamily: fontName }" :class="texts[selectedIndex].font === fontKey ? 'text-primary font-bold bg-primary/5' : 'text-slate-700'">
                                <span x-text="fontName"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="selectedType === 'text' && selectedIndex !== null && texts[selectedIndex]">
            <div class="relative">
                <button @click="showSizeMenu = !showSizeMenu; showFontMenu = false; showAlignMenu = false" class="flex flex-col items-center p-2 hover:bg-slate-50 rounded-xl transition-colors group relative" :class="showSizeMenu ? 'bg-slate-100 text-primary' : 'text-slate-500'">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span class="text-[8px] font-bold uppercase tracking-tighter mt-1 sm:hidden">Größe</span>
                    <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap hidden sm:block">Größe</span>
                </button>
                <div x-show="showSizeMenu" @click.outside="showSizeMenu = false" class="absolute bottom-full mb-3 left-1/2 -translate-x-1/2 w-40 bg-white rounded-xl shadow-xl border border-slate-100 p-4 z-[60]">
                    <div class="flex flex-col gap-2">
                        <div class="flex justify-between text-[10px] font-bold text-slate-400 uppercase"><span>Klein</span><span>Groß</span></div>
                        <input type="range" min="0.5" max="8" step="0.1" x-model="texts[selectedIndex].size" @input="$nextTick(() => { const el = document.querySelectorAll('textarea')[selectedIndex]; if(el) fitTextarea(el); })" class="w-full accent-primary h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer">
                        <div class="text-center text-xs font-bold text-slate-700" x-text="parseFloat(texts[selectedIndex].size).toFixed(1)"></div>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="selectedType === 'text' && selectedIndex !== null && texts[selectedIndex]">
            <div class="relative">
                <button @click="showAlignMenu = !showAlignMenu; showFontMenu = false; showSizeMenu = false" class="flex flex-col items-center p-2 hover:bg-slate-50 rounded-xl transition-colors group relative" :class="showAlignMenu ? 'bg-slate-100 text-primary' : 'text-slate-500'">
                    <div class="w-6 h-6 flex items-center justify-center">
                        <template x-if="texts[selectedIndex].align === 'left'"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></template>
                        <template x-if="texts[selectedIndex].align === 'center'"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></template>
                        <template x-if="texts[selectedIndex].align === 'right'"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></template>
                    </div>
                    <span class="text-[8px] font-bold uppercase tracking-tighter mt-1 sm:hidden">Ausricht.</span>
                    <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap hidden sm:block">Ausrichtung</span>
                </button>
                <div x-show="showAlignMenu" @click.outside="showAlignMenu = false" class="absolute bottom-full mb-3 left-1/2 -translate-x-1/2 bg-white rounded-xl shadow-xl border border-slate-100 p-1.5 z-[60] flex gap-1">
                    <button @click="texts[selectedIndex].align = 'left'; showAlignMenu = false" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-primary transition-colors" :class="texts[selectedIndex].align === 'left' ? 'bg-primary/10 text-primary' : ''"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></button>
                    <button @click="texts[selectedIndex].align = 'center'; showAlignMenu = false" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-primary transition-colors" :class="texts[selectedIndex].align === 'center' ? 'bg-primary/10 text-primary' : ''"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></button>
                    <button @click="texts[selectedIndex].align = 'right'; showAlignMenu = false" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-primary transition-colors" :class="texts[selectedIndex].align === 'right' ? 'bg-primary/10 text-primary' : ''"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></button>
                </div>
            </div>
        </template>

        <template x-if="selectedType === 'text'"><div class="w-px h-8 bg-slate-200 mx-0.5 sm:mx-1"></div></template>

        <button @click="duplicateElement" class="flex flex-col items-center p-2 hover:bg-slate-50 rounded-xl transition-colors group relative">
            <x-heroicon-m-square-2-stack class="w-6 h-6 text-slate-500" />
            <span class="text-[8px] font-bold uppercase tracking-tighter mt-1 sm:hidden">Kopie</span>
            <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap hidden sm:block">Duplizieren</span>
        </button>

        <button @click="selectedType === 'text' ? $wire.removeText(selectedIndex) : $wire.toggleLogo('saved', logos[selectedIndex].value)" class="flex flex-col items-center p-2 hover:bg-rose-50 rounded-xl transition-colors group relative">
            <x-heroicon-m-trash class="w-6 h-6 text-rose-500" />
            <span class="text-[8px] font-bold uppercase tracking-tighter mt-1 sm:hidden">LÖSCHEN</span>
            <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap hidden sm:block">Löschen</span>
        </button>

        <div class="w-px h-8 bg-slate-200 mx-0.5 sm:mx-1"></div>

        <button @click="centerHorizontal" class="flex flex-col items-center p-2 hover:bg-slate-50 rounded-xl transition-colors group relative">
            <x-heroicon-m-pause class="w-6 h-6 text-slate-500 rotate-90" />
            <span class="text-[8px] font-bold uppercase tracking-tighter mt-1 sm:hidden">H-Mitte</span>
            <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap hidden sm:block">H-Zentrieren</span>
        </button>

        <button @click="centerVertical" class="flex flex-col items-center p-2 hover:bg-slate-50 rounded-xl transition-colors group relative">
            <x-heroicon-m-pause class="w-6 h-6 text-slate-500" />
            <span class="text-[8px] font-bold uppercase tracking-tighter mt-1 sm:hidden">V-Mitte</span>
            <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap hidden sm:block">V-Zentrieren</span>
        </button>

        <button @click="centerBoth" class="flex flex-col items-center p-2 bg-primary/10 text-primary rounded-xl transition-colors group relative">
            <x-heroicon-m-viewfinder-circle class="w-6 h-6" />
            <span class="text-[8px] font-bold uppercase tracking-tighter mt-1 sm:hidden">Mitte</span>
            <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap hidden sm:block">Zentrieren</span>
        </button>
    </div>
</div>
