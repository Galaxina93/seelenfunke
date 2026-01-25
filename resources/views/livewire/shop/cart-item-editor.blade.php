{{-- SCRIPT: Global definieren --}}
@script
<script>
    window.cartItemEditorData = function(configData) {
        return {
            ...configData.wireModels,
            fontMap: configData.fonts,
            alignMap: { 'left': 'text-left', 'center': 'text-center', 'right': 'text-right' },
            area: {
                top: parseFloat(configData.config.area_top || 10),
                left: parseFloat(configData.config.area_left || 10),
                width: parseFloat(configData.config.area_width || 80),
                height: parseFloat(configData.config.area_height || 80)
            },
            isDragging: false,
            currentElement: null,
            dragOffsetX: 0,
            dragOffsetY: 0,

            init() {
                this.onDrag = this.handleDrag.bind(this);
                this.stopDrag = this.handleStop.bind(this);
            },

            startDrag(event, type) {
                this.isDragging = true;
                this.currentElement = type;
                if(event.cancelable) event.preventDefault();

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();

                let currentPercentX = (type === 'text') ? this.textX : this.logoX;
                let currentPercentY = (type === 'text') ? this.textY : this.logoY;

                currentPercentX = parseFloat(currentPercentX);
                currentPercentY = parseFloat(currentPercentY);

                let currentPixelX = (currentPercentX / 100) * container.width;
                let currentPixelY = (currentPercentY / 100) * container.height;

                let mousePixelX = clientX - container.left;
                let mousePixelY = clientY - container.top;

                this.dragOffsetX = mousePixelX - currentPixelX;
                this.dragOffsetY = mousePixelY - currentPixelY;

                window.addEventListener('mousemove', this.onDrag);
                window.addEventListener('touchmove', this.onDrag, { passive: false });
                window.addEventListener('mouseup', this.stopDrag);
                window.addEventListener('touchend', this.stopDrag);
            },

            handleDrag(event) {
                if (!this.isDragging) return;
                if(event.cancelable) event.preventDefault();

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();

                let mouseX = clientX - container.left;
                let mouseY = clientY - container.top;

                let newCenterX = mouseX - this.dragOffsetX;
                let newCenterY = mouseY - this.dragOffsetY;

                let percentX = (newCenterX / container.width) * 100;
                let percentY = (newCenterY / container.height) * 100;

                let minX = this.area.left;
                let maxX = this.area.left + this.area.width;
                let minY = this.area.top;
                let maxY = this.area.top + this.area.height;

                percentX = Math.max(minX, Math.min(maxX, percentX));
                percentY = Math.max(minY, Math.min(maxY, percentY));

                if (this.currentElement === 'text') {
                    this.textX = percentX;
                    this.textY = percentY;
                } else if (this.currentElement === 'logo') {
                    this.logoX = percentX;
                    this.logoY = percentY;
                }
            },

            handleStop() {
                this.isDragging = false;
                this.currentElement = null;
                window.removeEventListener('mousemove', this.onDrag);
                window.removeEventListener('touchmove', this.onDrag);
                window.removeEventListener('mouseup', this.stopDrag);
                window.removeEventListener('touchend', this.stopDrag);
            }
        }
    }
</script>
@endscript

<div class="h-full flex flex-col bg-white"
     x-data="window.cartItemEditorData({
        wireModels: {
            textX: @entangle('text_x').live,
            textY: @entangle('text_y').live,
            logoX: @entangle('logo_x').live,
            logoY: @entangle('logo_y').live,
            engravingText: @entangle('engraving_text').live,
            selectedFont: @entangle('engraving_font').live,
            textAlign: @entangle('engraving_align').live,
            textSize: @entangle('text_size').live,
            logoSize: @entangle('logo_size').live
        },
        config: {{ Js::from($config) }},
        fonts: {{ Js::from($fonts) }}
     })">

    {{-- SCROLLABLE CONTENT --}}
    <div class="flex-1 overflow-y-auto custom-scrollbar">

        {{-- VORSCHAU --}}
        <div class="bg-gray-50 flex justify-center sticky p-4 top-0 z-20 border-b border-gray-200 shadow-sm shrink-0 select-none">
            {{-- Container --}}
            <div class="relative w-full max-w-[400px] aspect-square bg-white rounded-xl shadow-lg overflow-hidden border-4 border-white ring-1 ring-gray-100"
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
                        boxShadow: '0 0 0 9999px rgba(239, 68, 68, 0.2)'
                     }">
                    <span class="absolute top-0 left-0 bg-green-500 text-white text-[8px] px-1 font-bold">Bereich</span>
                </div>

                {{-- Text Layer --}}
                <div class="absolute z-20 cursor-move group touch-none"
                     style="transform: translate(-50%, -50%);"
                     :style="{ left: textX + '%', top: textY + '%' }"
                     @mousedown="startDrag($event, 'text')"
                     @touchstart="startDrag($event, 'text')">

                    <div class="border border-transparent group-hover:border-primary/50 p-1 rounded transition-colors w-auto text-center"
                         :class="{ 'border-primary': currentElement === 'text' }">
                        {{-- MINIMIERTER HTML CODE IM P-TAG --}}
                        <p class="leading-tight font-bold whitespace-pre pointer-events-none w-max" :class="alignMap[textAlign]" :style="`font-size: ${16 * textSize}px; font-family: ${fontMap[selectedFont] || 'Arial'}; background: linear-gradient(to bottom, #cfc09f 22%, #634f2c 24%, #cfc09f 26%, #cfc09f 27%, #ffecb3 40%, #3a2c0f 78%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; color: #C5A059; text-shadow: 1px 1px 2px rgba(255,255,255,0.5);`"><span x-text="engravingText ? engravingText : 'Ihr Text'"></span></p>
                    </div>
                </div>

                {{-- Logo Layer --}}
                @if($config['allow_logo'])
                    @if($uploaded_logo || $existing_logo_path)
                        <div class="absolute z-20 cursor-move group touch-none"
                             style="transform: translate(-50%, -50%);"
                             :style="{ left: logoX + '%', top: logoY + '%' }"
                             @mousedown="startDrag($event, 'logo')"
                             @touchstart="startDrag($event, 'logo')">

                            <div class="border border-transparent group-hover:border-primary/50 p-1 rounded transition-colors"
                                 :class="{ 'border-primary': currentElement === 'logo' }">
                                <div :style="{ width: logoSize + 'px' }" class="relative">
                                    @if($uploaded_logo)
                                        <img src="{{ $uploaded_logo->temporaryUrl() }}" class="w-full h-auto object-contain drop-shadow-md pointer-events-none">
                                    @elseif($existing_logo_path)
                                        <img src="{{ asset('storage/'.$existing_logo_path) }}" class="w-full h-auto object-contain drop-shadow-md pointer-events-none">
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="absolute z-10 pointer-events-none opacity-30 border border-dashed border-gray-400 p-1 rounded"
                             :style="{ left: logoX + '%', top: logoY + '%', transform: 'translate(-50%, -50%)' }">
                            <span class="text-[8px] font-bold uppercase tracking-wider">Logo</span>
                        </div>
                    @endif
                @endif

            </div>
        </div>

        {{-- FORMULAR --}}
        <div class="p-4 space-y-4 text-sm">

            {{-- 1. MENGE --}}
            <div class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wider">Menge:</label>
                    <div class="relative">
                        <select wire:model.live="qty" wire:change="calculatePrice" class="appearance-none w-20 pl-3 pr-8 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-900 font-bold text-base focus:ring-2 focus:ring-primary focus:border-transparent cursor-pointer">
                            @for($i = 1; $i <= 50; $i++) <option value="{{ $i }}">{{ $i }}x</option> @endfor
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="font-serif font-bold text-lg text-primary block leading-none">{{ number_format($totalPrice / 100, 2, ',', '.') }} €</span>
                    <span class="text-[10px] text-gray-400">{{ number_format($currentPrice / 100, 2, ',', '.') }} €/Stk. (@if($product->tax_included) inkl. @else zzgl. @endif MwSt.)</span>
                </div>
            </div>

            {{-- 2. LOGO / BILD --}}
            @if($config['allow_logo'])
                <div class="space-y-3 pt-2 border-t border-gray-100">
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wide flex items-center gap-2">
                        <span>Logo / Bild</span>
                        <span class="text-[10px] font-normal text-gray-400 bg-gray-100 px-1.5 rounded">Optional</span>
                    </label>

                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-3">
                        @if($existing_logo_path && !$uploaded_logo)
                            <div class="flex items-center gap-3 mb-3 bg-white p-2 rounded-lg border border-green-100 shadow-sm">
                                <img src="{{ asset('storage/'.$existing_logo_path) }}" class="h-8 w-8 object-cover rounded bg-gray-100">
                                <div class="leading-tight">
                                    <p class="text-xs font-bold text-gray-900">Gespeichert</p>
                                    <p class="text-[10px] text-green-600">Aktuelles Logo verwenden</p>
                                </div>
                            </div>
                        @endif

                        <input type="file" wire:model.live="uploaded_logo" class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-gray-900 file:text-white hover:file:bg-black file:transition-colors file:cursor-pointer cursor-pointer">

                        @if($uploaded_logo || $existing_logo_path)
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <label class="flex justify-between text-xs font-bold text-gray-500 mb-2">
                                    <span>Größe anpassen</span>
                                    <span x-text="logoSize + 'px'"></span>
                                </label>
                                <input type="range" wire:model.live="logo_size" min="30" max="400" step="5" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-primary">
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- 3. GRAVUR ANPASSEN --}}
            <div class="space-y-3 pt-2 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wide">Gravur Text</label>
                    <span class="text-[10px] text-gray-400" x-text="(engravingText ? engravingText.length : 0) + '/100'"></span>
                </div>

                <textarea wire:model.live="engraving_text" rows="2" class="w-full p-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-sm leading-snug resize-none shadow-sm" placeholder="Ihr Wunschtext..."></textarea>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1">Schriftart</label>
                        <select wire:model.live="engraving_font" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-xs text-gray-900 focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer">
                            @foreach($fonts as $fontName => $css) <option value="{{ $fontName }}">{{ $fontName }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1">Ausrichtung</label>
                        <select wire:model.live="engraving_align" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-xs text-gray-900 focus:border-primary focus:ring-1 focus:ring-primary transition-all cursor-pointer">
                            @foreach($alignmentOptions as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                        </select>
                    </div>
                </div>

                {{-- Text Größe --}}
                <div>
                    <label class="flex justify-between text-xs font-bold text-gray-500 mb-2 ml-1">
                        <span>Schriftgröße</span>
                        <span x-text="(textSize * 100).toFixed(0) + '%'"></span>
                    </label>
                    <input type="range" wire:model.live="text_size" min="0.5" max="3.0" step="0.1" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-primary">
                </div>
            </div>

            {{-- 4. ANMERKUNGEN --}}
            <div class="pt-2 border-t border-gray-100">
                <label class="text-[10px] font-bold text-gray-500 mb-1.5 block uppercase tracking-wide">Anmerkung (Optional)</label>
                <textarea wire:model="notes" rows="1" class="w-full p-3 rounded-lg border border-yellow-200 bg-yellow-50/50 text-gray-900 placeholder-gray-400 focus:bg-yellow-50 focus:border-yellow-400 focus:ring-1 focus:ring-yellow-400/50 transition-all text-xs leading-relaxed resize-none" placeholder="Sonderwünsche?"></textarea>
            </div>
        </div>
    </div>

    {{-- FOOTER --}}
    <div class="p-4 border-t border-gray-200 bg-white z-30 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] shrink-0">
        <button
            wire:click="save"
            class="w-full bg-gray-900 text-white py-3 rounded-full font-bold text-base
           hover:bg-black hover:scale-[1.01] active:scale-[0.99]
           transition-all duration-200 flex items-center justify-center gap-2 shadow-lg"
        >
            <!-- Spinner: immer da -->
            <svg
                wire:loading.class="opacity-100 animate-spin"
                wire:loading.remove.class="opacity-0"
                class="h-4 w-4 text-white opacity-0 transition-opacity"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10"
                        stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0
                 C5.373 0 0 5.373 0 12h4zm2 5.291
                 A7.962 7.962 0 014 12H0
                 c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>

            <!-- Text -->
            <span wire:loading.remove>Änderungen speichern</span>
            <span wire:loading>Speichere…</span>
        </button>

    </div>
</div>
