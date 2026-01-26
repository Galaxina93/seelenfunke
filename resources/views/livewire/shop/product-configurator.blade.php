{{-- SCRIPT: Global definieren für Drag & Drop Logik --}}
@script
<script>
    window.productConfiguratorData = function(data) {
        return {
            ...data.wire,
            fontMap: data.fonts,
            alignMap: { 'left': 'text-left', 'center': 'text-center', 'right': 'text-right' },
            area: {
                top: parseFloat(data.config.area_top || 10),
                left: parseFloat(data.config.area_left || 10),
                width: parseFloat(data.config.area_width || 80),
                height: parseFloat(data.config.area_height || 80)
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

<div class="bg-white rounded-2xl border border-gray-200 shadow-xl overflow-hidden"
     x-data="window.productConfiguratorData({
        wire: {
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

    {{-- HEADER --}}
    <div class="bg-gray-50 px-8 py-5 border-b border-gray-200 flex justify-between items-center">
        <h3 class="font-serif font-bold text-xl text-gray-900">Artikel konfigurieren</h3>
        @if(!empty($product->tier_pricing))
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                Staffelpreise verfügbar
            </span>
        @endif
    </div>

    {{-- INFO-BOX: Hinweis zur Positionierung --}}
    <div class="mx-8 my-6 bg-blue-50 border-l-4 border-blue-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    Die Vorschau dient zur reinen Orientierung. Unsere Experten übernehmen die exakte Positionierung von Bild und Text.
                </p>
            </div>
        </div>
    </div>

    {{-- BEREICH 1: INTERAKTIVE VORSCHAU --}}
    <div class="bg-gray-100 p-8 flex flex-col items-center justify-center relative border-b border-gray-200 select-none">

        <h4 class="absolute top-4 left-1/2 -translate-x-1/2 text-[10px] font-bold text-gray-400 uppercase tracking-widest bg-white px-3 py-1 rounded-full shadow-sm z-30 pointer-events-none">
            Drag & Drop Editor
        </h4>

        {{--
            Vorschau-Container
            Exakt 400x400px (passend zur Laserfläche 20x20cm bei 1mm=2px)
        --}}
        <div class="relative w-full max-w-[400px] aspect-square bg-white rounded-2xl shadow-xl overflow-hidden border-4 border-white transition-all duration-300"
             x-ref="container">

            {{--
                HINTERGRUND / OVERLAY
                Wichtig: Kein extra Wrapper-Div hier!
                Die Klasse 'absolute inset-0' positioniert es direkt im 'container'.
            --}}
            @if($this->previewImage)
                <img src="{{ $this->previewImage }}"
                     class="absolute inset-0 w-full h-full object-contain z-0 pointer-events-none">
            @else
                <div class="absolute inset-0 flex items-center justify-center text-gray-300 bg-gray-50 z-0">
                    <span class="text-xs font-bold">Kein Bild</span>
                </div>
            @endif

            {{-- ARBEITSBEREICH (Grüner Rahmen) --}}
            <div class="absolute border-2 border-green-500 bg-green-500/10 pointer-events-none z-10"
                 :style="{
                top: area.top + '%',
                left: area.left + '%',
                width: area.width + '%',
                height: area.height + '%',
                boxShadow: '0 0 0 9999px rgba(239, 68, 68, 0.2)'
             }">
                <span class="absolute top-0 left-0 bg-green-500 text-white text-[8px] px-1 font-bold">Erlaubt</span>
            </div>

            {{-- TEXT LAYER --}}
            <div class="absolute z-20 cursor-move group touch-none"
                 style="transform: translate(-50%, -50%);"
                 :style="{ left: textX + '%', top: textY + '%' }"
                 @mousedown="startDrag($event, 'text')"
                 @touchstart="startDrag($event, 'text')">

                <div class="border border-transparent group-hover:border-primary/50 p-1 rounded transition-colors w-auto text-center"
                     :class="{ 'border-primary': currentElement === 'text' }">
                    <p class="leading-tight font-bold whitespace-pre pointer-events-none w-max"
                       :class="alignMap[textAlign]"
                       :style="`font-size: ${16 * textSize}px; font-family: ${fontMap[selectedFont] || 'Arial'}; background: linear-gradient(to bottom, #cfc09f 22%, #634f2c 24%, #cfc09f 26%, #cfc09f 27%, #ffecb3 40%, #3a2c0f 78%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; color: #C5A059; text-shadow: 1px 1px 2px rgba(255,255,255,0.5);`">
                        <span x-text="engravingText ? engravingText : 'Ihr Text'"></span>
                    </p>
                </div>
            </div>

            {{-- LOGO LAYER --}}
            @if($config['allow_logo'])
                @if($uploaded_logo)
                    <div class="absolute z-20 cursor-move group touch-none"
                         style="transform: translate(-50%, -50%);"
                         :style="{ left: logoX + '%', top: logoY + '%' }"
                         @mousedown="startDrag($event, 'logo')"
                         @touchstart="startDrag($event, 'logo')">

                        <div class="border border-transparent group-hover:border-primary/50 p-1 rounded transition-colors"
                             :class="{ 'border-primary': currentElement === 'logo' }">
                            <div :style="{ width: logoSize + 'px' }" class="relative">
                                <img src="{{ $uploaded_logo->temporaryUrl() }}" class="w-full h-auto object-contain drop-shadow-md pointer-events-none">
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Platzhalter für Logo-Position, falls gewünscht --}}
                    <div class="absolute z-10 pointer-events-none opacity-30 border border-dashed border-gray-400 p-1 rounded"
                         :style="{ top: logoY + '%', left: logoX + '%', transform: 'translate(-50%, -50%)' }">
                        <span class="text-[8px] font-bold uppercase tracking-wider">Logo</span>
                    </div>
                @endif
            @endif

        </div>

        {{-- QUICK-CONTROLS (Regler) --}}
        <div class="w-full max-w-[400px] mt-4 space-y-3">
            {{-- Wenn Text oder Logo vorhanden ist, zeigen wir die Regler --}}
            <div x-show="(engravingText && engravingText.length > 0) @if($config['allow_logo'] && $uploaded_logo) || true @endif"
                 class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 animate-fade-in-up">

                <div class="grid grid-cols-1 gap-4">
                    {{-- Text Größe --}}
                    <div x-show="engravingText && engravingText.length > 0" class="flex items-center gap-4">
                        <div class="flex flex-col w-full">
                            <div class="flex justify-between text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Schriftgröße</span>
                                <span x-text="Math.round(textSize * 100) + '%'" class="text-primary bg-primary/10 px-1.5 rounded"></span>
                            </div>
                            <input type="range" wire:model.live="text_size" min="0.5" max="3.0" step="0.1"
                                   class="w-full h-2 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-blue-500 hover:accent-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    {{-- Logo Größe --}}
                    @if($config['allow_logo'] && $uploaded_logo)
                        <div class="flex items-center gap-4 pt-3 border-t border-gray-100">
                            <div class="flex flex-col w-full">
                                <div class="flex justify-between text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">
                                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Logogröße</span>
                                    <span x-text="logoSize + 'px'" class="text-primary bg-primary/10 px-1.5 rounded"></span>
                                </div>
                                <input type="range" wire:model.live="logo_size" min="30" max="200" step="5"
                                       class="w-full h-2 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-green-500 hover:accent-green-600 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Hinweis wenn leer --}}
            <div x-show="(!engravingText || engravingText.length === 0) @if($config['allow_logo']) && !{{ $uploaded_logo ? 'true' : 'false' }} @endif"
                 class="text-center p-3 text-xs text-gray-400 bg-white/50 rounded-xl border border-gray-200/50 border-dashed">
                Tippe unten Text ein oder lade ein Logo hoch, um hier Größenregler zu sehen.
            </div>
        </div>

        <p class="text-center text-[10px] text-gray-400 mt-4 max-w-xs leading-relaxed">
            Platzieren Sie Elemente im grünen Bereich.
        </p>
    </div>

    {{-- EINSTELLUNGEN --}}
    <div class="p-6 md:p-8 space-y-8 bg-white">

        {{-- Menge --}}
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <label class="block text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">Menge & Preis</label>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <select wire:model.live="qty" wire:change="calculatePrice" class="appearance-none w-28 pl-4 pr-10 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 font-bold text-lg focus:ring-2 focus:ring-primary focus:border-transparent focus:bg-white transition-all cursor-pointer">
                        @for($i = 1; $i <= 150; $i++) <option value="{{ $i }}">{{ $i }}x</option> @endfor
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></div>
                </div>
                <div class="flex flex-col">
                    <span class="font-serif font-bold text-2xl text-primary">{{ number_format($totalPrice / 100, 2, ',', '.') }} €</span>
                    <span class="text-xs text-gray-500">{{ number_format($currentPrice / 100, 2, ',', '.') }} € / Stk. <span class="text-gray-400">(@if($product->tax_included) inkl. @else zzgl. @endif MwSt.)</span></span>
                </div>
            </div>
        </div>

        {{-- Logo Upload --}}
        @if($config['allow_logo'])
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-bold text-gray-900 uppercase tracking-wide">Logo / Bild</label>
                    <span class="text-xs text-gray-400">Optional</span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                    <input type="file" wire:model.live="uploaded_logo" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-black file:transition-colors file:cursor-pointer cursor-pointer">

                    @if($uploaded_logo)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <label class="flex justify-between text-xs font-bold text-gray-500 mb-2">
                                <span>Größe anpassen</span>
                                <span x-text="logoSize + 'px'"></span>
                            </label>
                            <input type="range" wire:model.live="logo_size" min="30" max="200" step="5" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-primary">
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Gravur Text --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <label class="text-sm font-bold text-gray-900 uppercase tracking-wide">Gravur anpassen</label>
                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-full">{{ strlen($engraving_text) }}/100</span>
            </div>

            <textarea wire:model.live="engraving_text" rows="2" class="w-full p-4 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-base leading-relaxed resize-none shadow-sm" placeholder="Geben Sie hier Ihren Wunschtext ein..."></textarea>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2 ml-1">Schriftart</label>
                    <select wire:model.live="engraving_font" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                        @foreach($fonts as $fontName => $css) <option value="{{ $fontName }}">{{ $fontName }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2 ml-1">Ausrichtung</label>
                    <select wire:model.live="engraving_align" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                        @foreach($alignmentOptions as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </div>
            </div>

            {{-- Slider für Textgröße --}}
            <div>
                <label class="flex justify-between text-xs font-bold text-gray-500 mb-2 ml-1">
                    <span>Schriftgröße</span>
                    <span x-text="(textSize * 100).toFixed(0) + '%'"></span>
                </label>
                <input type="range" wire:model.live="text_size" min="0.5" max="3.0" step="0.1" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-primary">
            </div>
        </div>

        {{-- Anmerkungen --}}
        <div class="pt-4 border-t border-gray-100">
            <label class="text-xs font-bold text-gray-500 mb-2 block uppercase tracking-wide">Interne Anmerkungen</label>
            <textarea wire:model="notes" rows="2" class="w-full p-4 rounded-xl border border-yellow-200 bg-yellow-50/50 text-gray-900 placeholder-gray-400 focus:bg-yellow-50 focus:border-yellow-400 focus:ring-2 focus:ring-yellow-400/20 transition-all text-sm leading-relaxed resize-none" placeholder="Haben Sie Sonderwünsche?"></textarea>
        </div>

    </div>

    {{-- FOOTER --}}
    <div class="bg-gray-50 px-8 py-6 border-t border-gray-200">
        @if($product->isAvailable())
            <button x-data="{ state: 'idle' }"
                    @product-added-to-cart.window="state = 'success'; setTimeout(() => state = 'idle', 2000)"
                    wire:click="addToCart"
                    wire:loading.attr="disabled"
                    :class="state === 'success' ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-900 hover:bg-black'"
                    class="w-full text-white px-8 py-4 rounded-full font-bold text-lg transition-all duration-300 flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed group shadow-xl shadow-gray-200 hover:scale-[1.01] active:scale-[0.99]">
                <svg wire:loading class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span wire:loading.remove class="flex items-center gap-2">
                    <span x-show="state === 'idle'" class="flex items-center gap-2">
                        <span>In den Warenkorb</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                    </span>
                    <span x-show="state === 'success'" class="flex items-center gap-2" x-cloak>
                        <span>Hinzugefügt!</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </span>
                </span>
                <span wire:loading>Verarbeite...</span>
            </button>
        @else
            <button disabled class="w-full bg-gray-200 text-gray-400 px-8 py-4 rounded-full font-bold cursor-not-allowed flex items-center justify-center gap-2">
                <span>Derzeit ausverkauft</span>
            </button>
        @endif
    </div>
</div>
