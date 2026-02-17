{{-- VORSCHAU & QUICK-CONTROLS --}}
<div class="bg-gray-50 flex flex-col items-center sticky top-0 z-40 border-b border-gray-200 shadow-sm shrink-0 select-none pb-6" x-ref="stage">

    {{-- Der Konfigurator-Container (Max 600px, aber full responsive) --}}
    <div class="relative w-full max-w-[455px] md:max-w-[600px] aspect-square bg-white rounded-xl shadow-lg overflow-hidden border-4 border-white ring-1 ring-gray-100 mt-4"
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

        {{-- Positionierungs-Hilfen (Führungslinien) --}}
        <div x-show="showVGuide" class="absolute left-1/2 top-0 bottom-0 w-0.5 bg-primary/40 z-30 pointer-events-none"></div>
        <div x-show="showHGuide" class="absolute top-1/2 left-0 right-0 h-0.5 bg-primary/40 z-30 pointer-events-none"></div>

        {{-- Markierung des gravierbaren Bereichs --}}
        <div id="stage-overlay" class="absolute inset-0 z-10 pointer-events-auto">
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

            {{-- TEXT LAYERS --}}
            <template x-for="(textItem, index) in texts" :key="textItem.id">
                <div class="absolute z-20 touch-none"
                     style="transform: translate(-50%, -50%);"
                     :style="{
                        left: textItem.x + '%',
                        top: textItem.y + '%',
                        transform: `translate(-50%, -50%) rotate(${textItem.rotation || 0}deg)`
                     }"
                     @mousedown.stop="startAction($event, 'text', index, 'drag')"
                     @touchstart.stop="startAction($event, 'text', index, 'drag')">

                    <div class="p-2 rounded transition-all min-w-[20px] text-center relative"
                         :class="{ 'border-2 border-primary border-dashed bg-white/10 backdrop-blur-sm': (selectedType === 'text' && selectedIndex === index && context !== 'preview') }">

                        {{-- Inline Editing Input (Mit scaleFactor für korrekte Größe) --}}
                        <template x-if="context !== 'preview' && selectedType === 'text' && selectedIndex === index">
                            <textarea
                                x-model="texts[index].text"
                                x-init="fitTextarea($el)"
                                @input="fitTextarea($el)"
                                class="bg-transparent border-none p-0 m-0 focus:ring-0 text-center font-bold resize-none overflow-hidden block leading-normal whitespace-pre"
                                style="outline: none; width: auto; min-width: 50px;"
                                :style="`font-size: ${(16 * textItem.size) * scaleFactor}px; font-family: ${fontMap[textItem.font] || 'Arial'}; color: rgba(255, 255, 255, 0.9); line-height: 1.1;`"
                                placeholder="Text..."></textarea>
                        </template>

                        {{-- Statische Anzeige (Mit scaleFactor) --}}
                        <template x-if="context === 'preview' || !(selectedType === 'text' && selectedIndex === index)">
                            <p class="leading-normal font-bold whitespace-pre-line pointer-events-none w-max"
                               :class="alignMap[textItem.align]"
                               :style="`font-size: ${(16 * textItem.size) * scaleFactor}px; font-family: ${fontMap[textItem.font] || 'Arial'}; color: rgba(255, 255, 255, 0.9);`"
                               x-text="textItem.text ? textItem.text : (context !== 'preview' ? 'Hier tippen' : '')">
                            </p>
                        </template>

                        {{-- ACTION ICONS AM TEXTFELD (OBERHALB) --}}
                        <template x-if="selectedType === 'text' && selectedIndex === index && context !== 'preview'">
                            <div class="absolute -top-14 left-1/2 -translate-x-1/2 flex items-center gap-1 z-50 bg-white/95 backdrop-blur p-1.5 rounded-full shadow-2xl border border-gray-100">

                                {{-- Schriftart Picker (Öffnet nach unten) --}}
                                <div class="relative">
                                    <button @click.stop="showFontMenu = !showFontMenu" class="p-2 hover:bg-gray-100 rounded-full text-gray-700 transition-colors" title="Schriftart">
                                        <x-heroicon-m-language class="w-4 h-4"/>
                                    </button>
                                    <div x-show="showFontMenu" @click.outside="showFontMenu = false" class="absolute top-full mt-2 left-1/2 -translate-x-1/2 bg-white shadow-2xl rounded-2xl border border-gray-100 p-2 min-w-[150px] animate-fade-in-down z-[60]">
                                        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest px-3 py-1 mb-1 border-b border-gray-50">Schriftart wählen</div>
                                        <div class="max-h-40 overflow-y-auto custom-scrollbar pr-1">
                                            <template x-for="(style, name) in fontMap">
                                                <button @click.stop="texts[selectedIndex].font = name; showFontMenu = false" class="block w-full text-left px-3 py-2.5 text-sm hover:bg-primary/10 rounded-lg text-gray-700 transition-colors" :style="`font-family: ${style}`" x-text="name"></button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                {{-- Ausrichtung --}}
                                <button @click.stop="toggleAlignment()" class="p-2 hover:bg-gray-100 rounded-full text-gray-700 transition-colors" title="Ausrichtung">
                                    <template x-if="texts[selectedIndex].align === 'left'"><x-heroicon-m-bars-3-bottom-left class="w-4 h-4"/></template>
                                    <template x-if="texts[selectedIndex].align === 'center'"><x-heroicon-m-bars-3 class="w-4 h-4"/></template>
                                    <template x-if="texts[selectedIndex].align === 'right'"><x-heroicon-m-bars-3-bottom-right class="w-4 h-4"/></template>
                                </button>

                                <div class="w-px h-4 bg-gray-200 mx-1"></div>

                                {{-- Hinzufügen --}}
                                <button wire:click="addText" class="p-2 hover:bg-green-50 rounded-full text-green-600 transition-colors" title="Text +">
                                    <x-heroicon-m-plus-circle class="w-4 h-4"/>
                                </button>

                                {{-- Skalieren --}}
                                <button @mousedown.stop="startAction($event, 'text', index, 'resize')" @touchstart.stop="startAction($event, 'text', index, 'resize')" class="p-2 hover:bg-gray-100 rounded-full text-primary cursor-se-resize" title="Größe">
                                    <x-heroicon-m-arrows-pointing-out class="w-4 h-4"/>
                                </button>

                                {{-- Rotieren --}}
                                <button @mousedown.stop="startAction($event, 'text', index, 'rotate')" @touchstart.stop="startAction($event, 'text', index, 'rotate')" class="p-2 hover:bg-gray-100 rounded-full text-primary transition-colors" title="Drehen">
                                    <x-heroicon-m-arrow-path class="w-4 h-4"/>
                                </button>

                                <div class="w-px h-4 bg-gray-200 mx-1"></div>

                                {{-- Löschen --}}
                                <button @click.stop="$wire.removeText(index)" class="p-2 hover:bg-red-50 rounded-full text-red-500" title="Entfernen">
                                    <x-heroicon-m-trash class="w-4 h-4"/>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- LOGO LAYERS --}}
            <template x-for="(logoItem, index) in logos" :key="logoItem.id">
                <div class="absolute z-20 touch-none"
                     style="transform: translate(-50%, -50%);"
                     :style="{
                        left: logoItem.x + '%',
                        top: logoItem.y + '%',
                        width: (logoItem.size * scaleFactor) + 'px',
                        transform: `translate(-50%, -50%) rotate(${logoItem.rotation || 0}deg)`
                     }"
                     @mousedown.stop="startAction($event, 'logo', index, 'drag')"
                     @touchstart.stop="startAction($event, 'logo', index, 'drag')">

                    <div class="relative p-1" :class="{ 'border-2 border-primary border-dashed bg-white/20': (selectedType === 'logo' && selectedIndex === index) }">
                        <img :src="logoItem.url" class="w-full h-auto pointer-events-none opacity-80 mix-blend-multiply">

                        {{-- Icons am Logo (Oberhalb) --}}
                        <template x-if="selectedType === 'logo' && selectedIndex === index && context !== 'preview'">
                            <div class="absolute -top-14 left-1/2 -translate-x-1/2 flex items-center gap-1 z-50 bg-white/95 backdrop-blur p-1.5 rounded-full shadow-2xl border border-gray-100">
                                <button @mousedown.stop="startAction($event, 'logo', index, 'resize')" @touchstart.stop="startAction($event, 'logo', index, 'resize')" class="p-2 hover:bg-gray-100 rounded-full text-primary"><x-heroicon-m-arrows-pointing-out class="w-4 h-4"/></button>
                                <button @mousedown.stop="startAction($event, 'logo', index, 'rotate')" @touchstart.stop="startAction($event, 'logo', index, 'rotate')" class="p-2 hover:bg-gray-100 rounded-full text-primary"><x-heroicon-m-arrow-path class="w-4 h-4"/></button>
                                <div class="w-px h-4 bg-gray-200 mx-1"></div>
                                <button @click.stop="$wire.toggleLogo('saved', logoItem.value)" class="p-2 hover:bg-red-50 rounded-full text-red-500"><x-heroicon-m-trash class="w-4 h-4"/></button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
