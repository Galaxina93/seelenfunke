{{-- VORSCHAU --}}
<div class="bg-gray-50 flex flex-col items-center sticky top-0 z-20 border-b border-gray-200 shadow-sm shrink-0 select-none pb-4">

    <div class="relative w-full max-w-[350px] md:max-w-[400px] aspect-square bg-white rounded-xl shadow-lg overflow-hidden border-4 border-white ring-1 ring-gray-100 mt-4"
         x-ref="container">

        {{-- Bild --}}
        @if($this->previewImage)
            <img src="{{ $this->previewImage }}" class="absolute inset-0 w-full h-full object-contain z-0 pointer-events-none">
        @else
            <div class="absolute inset-0 bg-gray-50 flex items-center justify-center text-gray-300"><span class="text-xs font-medium">Kein Bild verfügbar</span></div>
        @endif

        {{-- Arbeitsbereich --}}
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

        {{-- TEXT LAYERS (Loop) --}}
        <template x-for="(textItem, index) in texts" :key="textItem.id">
            <div class="absolute z-20 touch-none"
                 :class="context !== 'preview' ? 'cursor-move group' : ''"
                 style="transform: translate(-50%, -50%);"
                 :style="{ left: textItem.x + '%', top: textItem.y + '%' }"
                 @mousedown="startDrag($event, 'text', index)"
                 @touchstart="startDrag($event, 'text', index)">

                <div class="p-1 rounded transition-colors w-auto text-center"
                     :class="{ 'border border-primary': (selectedType === 'text' && selectedIndex === index && context !== 'preview'), 'border border-transparent': context === 'preview' }">
                    {{-- WICHTIG: Alles in einer Zeile lassen wegen whitespace-pre --}}
                    <p class="leading-tight font-bold whitespace-pre pointer-events-none w-max"
                       :class="alignMap[textItem.align]"
                       :style="`font-size: ${16 * textItem.size}px; font-family: ${fontMap[textItem.font] || 'Arial'}; background: linear-gradient(to bottom, #cfc09f 22%, #634f2c 24%, #cfc09f 26%, #cfc09f 27%, #ffecb3 40%, #3a2c0f 78%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; color: #C5A059; text-shadow: 1px 1px 2px rgba(255,255,255,0.5);`"><span x-text="textItem.text ? textItem.text : (context !== 'preview' ? 'Ihr Text' : '')"></span></p>
                </div>
            </div>
        </template>

        {{-- LOGO LAYERS (Loop) --}}
        @if($configSettings['allow_logo'])
            @foreach($this->renderedLogos as $index => $logoData)
                {{-- Alpine Loop nicht möglich wegen Blade URL rendering, daher hier Mapping via Index --}}
                <div class="absolute z-20 touch-none"
                     :class="context !== 'preview' ? 'cursor-move group' : ''"
                     style="transform: translate(-50%, -50%);"
                     :style="{ left: logos[{{ $index }}].x + '%', top: logos[{{ $index }}].y + '%' }"
                     @mousedown="startDrag($event, 'logo', {{ $index }})"
                     @touchstart="startDrag($event, 'logo', {{ $index }})">

                    <div class="p-1 rounded transition-colors"
                         :class="{ 'border border-primary': (selectedType === 'logo' && selectedIndex === {{ $index }} && context !== 'preview'), 'border border-transparent': context === 'preview' }">
                        <div :style="{ width: logos[{{ $index }}].size + 'px' }" class="relative">
                            <img src="{{ $logoData['url'] }}" class="w-full h-auto object-contain drop-shadow-md pointer-events-none">
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- MOBILE REGLER (Dynamisch je nach Auswahl) --}}
    @if($context !== 'preview')
        <div class="w-full max-w-[350px] md:max-w-[400px] mt-4 space-y-3 px-4 h-16">

            {{-- Regler für TEXT --}}
            <div x-show="selectedType === 'text' && texts[selectedIndex]" class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm transition-all">
                <label class="flex justify-between text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">
                    <span>Schriftgröße (Text <span x-text="selectedIndex + 1"></span>)</span>
                    <span x-text="texts[selectedIndex] ? Math.round(texts[selectedIndex].size * 100) + '%' : ''" class="text-primary"></span>
                </label>
                <input type="range"
                       :value="texts[selectedIndex] ? texts[selectedIndex].size : 1.0"
                       @input="updateSize($event.target.value)"
                       min="0.5" max="3.0" step="0.1"
                       class="w-full h-2 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-primary">
            </div>

            {{-- Regler für LOGO --}}
            <div x-show="selectedType === 'logo' && logos[selectedIndex]" class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm transition-all">
                <label class="flex justify-between text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">
                    <span>Bildgröße (Bild <span x-text="selectedIndex + 1"></span>)</span>
                    <span x-text="logos[selectedIndex] ? logos[selectedIndex].size + 'px' : ''" class="text-primary"></span>
                </label>
                <input type="range"
                       :value="logos[selectedIndex] ? logos[selectedIndex].size : 130"
                       @input="updateSize($event.target.value)"
                       min="30" max="250" step="5"
                       class="w-full h-2 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-green-600">
            </div>

            <div x-show="!selectedType" class="flex items-center justify-center h-full text-xs text-gray-400">
                Klicken Sie auf ein Element in der Vorschau zum Bearbeiten
            </div>
        </div>
    @endif
</div>
