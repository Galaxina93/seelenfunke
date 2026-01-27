{{-- SCRIPT: Global definieren für Drag & Drop (Unverändert) --}}
@script
<script>
    window.universalConfigurator = function(configData) {
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
     x-data="window.universalConfigurator({
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
        config: {{ Js::from($configSettings) }},
        fonts: {{ Js::from($fonts) }}
     })">

    {{-- SCROLLABLE CONTENT --}}
    <div class="flex-1 overflow-y-auto custom-scrollbar pb-20">

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
                     :style="{ top: area.top + '%', left: area.left + '%', width: area.width + '%', height: area.height + '%' }">
                </div>

                {{-- Text Layer --}}
                <div class="absolute z-20 cursor-move group touch-none"
                     style="transform: translate(-50%, -50%);"
                     :style="{ left: textX + '%', top: textY + '%' }"
                     @mousedown="startDrag($event, 'text')"
                     @touchstart="startDrag($event, 'text')">

                    <div class="border border-transparent group-hover:border-primary/50 p-1 rounded transition-colors w-auto text-center"
                         :class="{ 'border-primary': currentElement === 'text' }">
                        <p class="leading-tight font-bold whitespace-pre pointer-events-none w-max" :class="alignMap[textAlign]" :style="`font-size: ${16 * textSize}px; font-family: ${fontMap[selectedFont] || 'Arial'}; background: linear-gradient(to bottom, #cfc09f 22%, #634f2c 24%, #cfc09f 26%, #cfc09f 27%, #ffecb3 40%, #3a2c0f 78%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; color: #C5A059; text-shadow: 1px 1px 2px rgba(255,255,255,0.5);`"><span x-text="engravingText ? engravingText : 'Ihr Text'"></span></p>
                    </div>
                </div>

                {{-- Logo Layer (Hier war der Fehler: Wir nutzen jetzt die Computed Property) --}}
                @if($configSettings['allow_logo'] && $this->previewUrl)
                    <div class="absolute z-20 cursor-move group touch-none"
                         style="transform: translate(-50%, -50%);"
                         :style="{ left: logoX + '%', top: logoY + '%' }"
                         @mousedown="startDrag($event, 'logo')"
                         @touchstart="startDrag($event, 'logo')">

                        <div class="border border-transparent group-hover:border-primary/50 p-1 rounded transition-colors"
                             :class="{ 'border-primary': currentElement === 'logo' }">
                            <div :style="{ width: logoSize + 'px' }" class="relative">
                                {{-- Hier wird jetzt sicher die korrekte URL (Temp oder Storage) geladen --}}
                                <img src="{{ $this->previewUrl }}" class="w-full h-auto object-contain drop-shadow-md pointer-events-none">
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- MOBILE REGLER --}}
            <div class="w-full max-w-[350px] md:max-w-[400px] mt-4 space-y-3 px-4">
                <div x-show="engravingText && engravingText.length > 0" class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm">
                    <label class="flex justify-between text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">
                        <span>Schriftgröße</span>
                        <span x-text="Math.round(textSize * 100) + '%'" class="text-primary"></span>
                    </label>
                    <input type="range" wire:model.live="text_size" min="0.5" max="3.0" step="0.1" class="w-full h-2 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-primary">
                </div>

                {{-- Regler nur anzeigen, wenn auch wirklich ein Bild aktiv ist --}}
                @if($configSettings['allow_logo'] && $this->previewUrl)
                    <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm">
                        <label class="flex justify-between text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">
                            <span>Bildgröße</span>
                            <span x-text="logoSize + 'px'" class="text-primary"></span>
                        </label>
                        <input type="range" wire:model.live="logo_size" min="30" max="250" step="5" class="w-full h-2 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-green-600">
                    </div>
                @endif
            </div>
        </div>

        {{-- FORMULAR --}}
        <div class="p-6 space-y-6 text-sm max-w-2xl mx-auto">

            {{-- 1. MENGE --}}
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <label class="text-xs font-bold text-gray-900 uppercase tracking-wider">Menge</label>
                    <div class="text-right">
                        <span class="font-serif font-bold text-xl text-primary block leading-none">{{ number_format($totalPrice / 100, 2, ',', '.') }} €</span>
                        <span class="text-[10px] text-gray-400">Einzelpreis: {{ number_format($currentPrice / 100, 2, ',', '.') }} €</span>
                    </div>
                </div>
                <div class="relative w-full">
                    <select wire:model.live="qty" wire:change="calculatePrice" class="appearance-none w-full pl-4 pr-10 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 font-bold text-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all cursor-pointer">
                        @for($i = 1; $i <= 100; $i++) <option value="{{ $i }}">{{ $i }}x</option> @endfor
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></div>
                </div>
                @if(!empty($product->tier_pricing) && is_array($product->tier_pricing))
                    <div class="mt-4 pt-4 border-t border-dashed border-gray-200">
                        <span class="text-[10px] font-bold uppercase text-green-600 block mb-2">Mengenrabatte verfügbar:</span>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-center p-2 bg-gray-50 rounded border border-gray-100 {{ $qty < collect($product->tier_pricing)->min('qty') ? 'ring-1 ring-primary bg-primary/5' : '' }}">
                                <div class="text-[10px] text-gray-500">1 Stk.</div>
                                <div class="font-bold text-gray-900">{{ number_format($product->price / 100, 2, ',', '.') }} €</div>
                            </div>
                            @foreach(collect($product->tier_pricing)->sortBy('qty') as $tier)
                                @php
                                    $tierPrice = $product->price * (1 - $tier['percent'] / 100);
                                    $active = $qty >= $tier['qty'];
                                @endphp
                                <div class="text-center p-2 bg-gray-50 rounded border border-gray-100 {{ $active ? 'ring-1 ring-green-500 bg-green-50' : '' }}">
                                    <div class="text-[10px] text-gray-500">ab {{ $tier['qty'] }} Stk.</div>
                                    <div class="font-bold text-green-700">{{ number_format($tierPrice / 100, 2, ',', '.') }} €</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- 2. GRAVUR TEXT --}}
            <div class="space-y-3 pt-2 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-bold text-gray-900 uppercase tracking-wide">Gravur Text</label>
                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-full" x-text="(engravingText ? engravingText.length : 0) + '/100'"></span>
                </div>
                <textarea wire:model.live="engraving_text" rows="3" class="w-full p-4 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-base leading-relaxed resize-none shadow-sm" placeholder="Ihr Wunschtext hier eingeben..."></textarea>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Schriftart</label>
                        <select wire:model.live="engraving_font" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                            @foreach($fonts as $fontName => $css) <option value="{{ $fontName }}">{{ $fontName }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Ausrichtung</label>
                        <select wire:model.live="engraving_align" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                            @foreach($alignmentOptions as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- 3. MEDIEN (UPDATE: Jetzt mit korrekter Vorschau-Logik) --}}
            @if($configSettings['allow_logo'])
                <div class="space-y-3 pt-4 border-t border-gray-100">
                    <label class="text-sm font-bold text-gray-900 uppercase tracking-wide flex items-center gap-2">
                        <span>Medien</span>
                        <span class="text-[10px] font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded">Bilder & PDFs</span>
                    </label>

                    {{-- Upload Bereich --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                        <input type="file" wire:model.live="new_files" multiple class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-black file:transition-colors file:cursor-pointer cursor-pointer">
                        <div wire:loading wire:target="new_files" class="text-xs text-primary mt-2">Dateien werden hochgeladen...</div>

                        @error('new_files.*') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                        {{-- Liste der Dateien --}}
                        <div class="mt-4 space-y-2">

                            {{-- 1. Bereits gespeicherte Dateien --}}
                            @foreach($uploaded_files as $index => $path)
                                @php
                                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                                    $isImage = in_array(strtolower($ext), ['jpg','jpeg','png','webp']);
                                    $isPreview = ($active_preview === $path);
                                @endphp
                                <div class="flex items-center justify-between bg-white p-2 rounded border {{ $isPreview ? 'border-green-500 ring-1 ring-green-500' : 'border-gray-200' }}">
                                    <div class="flex items-center gap-3">
                                        @if($isImage)
                                            <img src="{{ asset('storage/'.$path) }}" class="h-10 w-10 object-cover rounded bg-gray-100">
                                        @else
                                            <div class="h-10 w-10 flex items-center justify-center bg-gray-100 rounded text-gray-500 font-bold text-xs">{{ strtoupper($ext) }}</div>
                                        @endif
                                        <div class="text-xs truncate max-w-[150px]">{{ basename($path) }}</div>
                                    </div>
                                    <div class="flex gap-2">
                                        @if($isImage)
                                            {{-- Button für gespeicherte Bilder --}}
                                            <button wire:click="setPreview('saved', '{{ $path }}')" class="text-[10px] px-2 py-1 rounded {{ $isPreview ? 'bg-green-100 text-green-700 font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                                {{ $isPreview ? 'Vorschau aktiv' : 'Als Vorschau' }}
                                            </button>
                                        @endif
                                        <button wire:click="removeFile({{ $index }})" class="text-red-500 hover:text-red-700 p-1">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach

                            {{-- 2. Neue (temporäre) Dateien --}}
                            @foreach($new_files as $index => $file)
                                @php
                                    $isImage = in_array(strtolower($file->extension()), ['jpg','jpeg','png','webp']);
                                    $isPreview = ($active_preview === 'new_' . $index);
                                @endphp
                                <div class="flex items-center justify-between bg-white p-2 rounded border {{ $isPreview ? 'border-green-500 ring-1 ring-green-500' : 'border-blue-200 border-dashed' }}">
                                    <div class="flex items-center gap-3">
                                        @if($isImage)
                                            <img src="{{ $file->temporaryUrl() }}" class="h-10 w-10 object-cover rounded bg-gray-100">
                                        @else
                                            <div class="h-10 w-10 flex items-center justify-center bg-gray-100 rounded text-gray-500 font-bold text-xs">{{ strtoupper($file->extension()) }}</div>
                                        @endif
                                        <div>
                                            <div class="text-xs text-blue-600 font-bold">NEU</div>
                                            <div class="text-xs truncate max-w-[150px]">{{ $file->getClientOriginalName() }}</div>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        @if($isImage)
                                            {{-- Button für neue Bilder (mit Index) --}}
                                            <button wire:click="setPreview('new', {{ $index }})" class="text-[10px] px-2 py-1 rounded {{ $isPreview ? 'bg-green-100 text-green-700 font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                                {{ $isPreview ? 'Vorschau aktiv' : 'Als Vorschau' }}
                                            </button>
                                        @endif
                                        <button wire:click="removeNewFile({{ $index }})" class="text-gray-400 hover:text-red-500">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- 4. ANMERKUNGEN --}}
            <div class="pt-4 border-t border-gray-100">
                <label class="text-xs font-bold text-gray-500 mb-2 block uppercase tracking-wide">Interne Anmerkungen</label>
                <textarea wire:model="notes" rows="2" class="w-full p-4 rounded-xl border border-yellow-200 bg-yellow-50/50 text-gray-900 placeholder-gray-400 focus:bg-yellow-50 focus:border-yellow-400 focus:ring-1 focus:ring-yellow-400/50 transition-all text-sm leading-relaxed resize-none" placeholder="Haben Sie Sonderwünsche?"></textarea>
            </div>

        </div>
    </div>

    {{-- FOOTER --}}
    <div class="p-4 border-t border-gray-200 bg-white z-30 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] shrink-0">
        <button
            wire:click="save"
            class="w-full bg-gray-900 text-white py-4 rounded-full font-bold text-lg hover:bg-black hover:scale-[1.01] active:scale-[0.99] transition-all duration-200 flex items-center justify-center gap-3 shadow-lg"
        >
            <span wire:loading.remove>
                @if($context === 'add') In den Warenkorb
                @elseif($context === 'edit') Änderungen speichern
                @elseif($context === 'calculator') Übernehmen
                @endif
            </span>
            <span wire:loading>Verarbeite...</span>
        </button>
    </div>
</div>
