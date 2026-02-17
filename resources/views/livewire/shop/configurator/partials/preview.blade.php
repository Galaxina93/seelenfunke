{{-- VORSCHAU & QUICK-CONTROLS --}}
<div class="bg-gray-50 flex flex-col items-center sticky top-0 z-40 border-b border-gray-200 shadow-sm shrink-0 select-none pb-4">

    {{-- Der eigentliche Konfigurator-Container --}}
    <div class="relative w-full max-w-[350px] md:max-w-[400px] aspect-square bg-white rounded-xl shadow-lg overflow-hidden border-4 border-white ring-1 ring-gray-100 mt-4"
         x-ref="container"
         @mousedown.self="deselectAll"
    >

        {{-- Hintergrund-Produktbild --}}
        @if($this->previewImage)
            <img src="{{ $this->previewImage }}" class="absolute inset-0 w-full h-full object-contain z-0 pointer-events-none">
        @else
            <div class="absolute inset-0 bg-gray-50 flex items-center justify-center text-gray-300">
                <span class="text-xs font-medium">Kein Bild verfügbar</span>
            </div>
        @endif

        {{-- Markierung des gravierbaren Bereichs --}}
        <div class="absolute border-2 border-green-500 bg-green-500/10 pointer-events-none z-10"
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
                 :class="context !== 'preview' ? 'cursor-move group' : ''"
                 style="transform: translate(-50%, -50%);"
                 :style="{ left: textItem.x + '%', top: textItem.y + '%' }"
                 @mousedown="startDrag($event, 'text', index)"
                 @touchstart="startDrag($event, 'text', index)">

                <div class="p-1 rounded transition-all min-w-[40px] text-center relative"
                     :class="{ 'border border-primary bg-white/30 backdrop-blur-sm': (selectedType === 'text' && selectedIndex === index && context !== 'preview') }">

                    {{-- INLINE EDITING INPUT: KEIN .stop MEHR BEI MOUSEDOWN! --}}
                    <template x-if="context !== 'preview' && selectedType === 'text' && selectedIndex === index">
                        <input type="text"
                               x-model="texts[index].text"
                               class="bg-transparent border-none p-0 m-0 focus:ring-0 text-center font-bold w-max min-w-[100px]"
                               style="outline: none;"
                               :style="`font-size: ${16 * textItem.size}px; font-family: ${fontMap[textItem.font] || 'Arial'}; color: #C5A059;`"
                               placeholder="Text tippen...">
                    </template>

                    {{-- STATISCHE ANZEIGE --}}
                    <template x-if="context === 'preview' || !(selectedType === 'text' && selectedIndex === index)">
                        <p class="leading-tight font-bold whitespace-pre pointer-events-none w-max"
                           :class="alignMap[textItem.align]"
                           :style="`font-size: ${16 * textItem.size}px; font-family: ${fontMap[textItem.font] || 'Arial'}; background: linear-gradient(to bottom, #cfc09f 22%, #634f2c 24%, #cfc09f 26%, #cfc09f 27%, #ffecb3 40%, #3a2c0f 78%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; color: #C5A059; text-shadow: 1px 1px 2px rgba(255,255,255,0.5);`"
                           x-text="textItem.text ? textItem.text : (context !== 'preview' ? 'Hier tippen' : '')">
                        </p>
                    </template>

                    {{-- Delete Button --}}
                    <template x-if="context !== 'preview' && texts.length > 1">
                        <button @click.stop="$wire.removeText(index)"
                                class="absolute -top-3 -right-3 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity z-30 shadow-sm">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- LOGO LAYERS --}}
        <template x-for="(logoItem, index) in logos" :key="logoItem.id">
            <div class="absolute z-20 touch-none"
                 :class="context !== 'preview' ? 'cursor-move group' : ''"
                 style="transform: translate(-50%, -50%);"
                 :style="{ left: logoItem.x + '%', top: logoItem.y + '%', width: logoItem.size + 'px' }"
                 @mousedown="startDrag($event, 'logo', index)"
                 @touchstart="startDrag($event, 'logo', index)">

                <div class="relative">
                    <img :src="logoItem.url" class="w-full h-auto pointer-events-none opacity-80 mix-blend-multiply">
                    <template x-if="context !== 'preview'">
                        <button @click.stop="$wire.toggleLogo('saved', logoItem.value)"
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow-sm">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- STICKY TOOLBAR --}}
    @if($context !== 'preview')
        <div class="w-full max-w-[350px] md:max-w-[400px] mt-4 px-4 space-y-3">

            {{-- TEXT TOOLBAR --}}
            <template x-if="selectedType === 'text' && texts && texts[selectedIndex]">
                <div class="bg-white p-3 rounded-2xl border border-primary/20 shadow-sm animate-fade-in">
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div class="space-y-1">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Schriftart</label>
                            <select x-model="texts[selectedIndex].font" class="w-full text-[11px] font-bold border-gray-200 rounded-lg py-1.5 focus:ring-primary/20">
                                @foreach($fonts as $fontName => $css) <option value="{{ $fontName }}">{{ $fontName }}</option> @endforeach
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Ausrichtung</label>
                            <select x-model="texts[selectedIndex].align" class="w-full text-[11px] font-bold border-gray-200 rounded-lg py-1.5 focus:ring-primary/20">
                                @foreach($alignmentOptions as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                            </select>
                        </div>
                    </div>

                    <label class="flex justify-between text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 px-1">
                        <span>Textgröße</span>
                        <span x-text="Math.round((texts[selectedIndex]?.size || 1) * 100) + '%'" class="text-primary"></span>
                    </label>
                    <input type="range" :value="texts[selectedIndex]?.size" @input="updateSize($event.target.value)" min="0.5" max="3.0" step="0.1" class="w-full h-1.5 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-primary">
                </div>
            </template>

            {{-- LOGO TOOLBAR --}}
            <template x-if="selectedType === 'logo' && logos && logos[selectedIndex]">
                <div class="bg-white p-3 rounded-2xl border border-green-200 shadow-sm animate-fade-in">
                    <label class="flex justify-between text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 px-1">
                        <span>Bildgröße</span>
                        <span x-text="(logos[selectedIndex]?.size || 130) + 'px'" class="text-primary"></span>
                    </label>
                    <input type="range" :value="logos[selectedIndex]?.size" @input="updateSize($event.target.value)" min="30" max="250" step="5" class="w-full h-1.5 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-green-600">
                </div>
            </template>

            {{-- Globale Aktionen --}}
            <div class="flex gap-2">
                <button wire:click="addText" class="flex-1 bg-white border border-gray-200 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:border-primary hover:text-primary transition-all flex items-center justify-center gap-2 shadow-sm active:scale-95">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M12 4v16m8-8H4"/></svg>
                    Weiteren Text hinzufügen
                </button>
            </div>
        </div>
    @endif
</div>
