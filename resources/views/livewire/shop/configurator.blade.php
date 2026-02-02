{{-- SCRIPT: Global definieren --}}
@script
<script>
    window.universalConfigurator = function(configData) {
        return {
            ...configData.wireModels, // enthält texts & logos arrays via entangle
            fontMap: configData.fonts,
            alignMap: { 'left': 'text-left', 'center': 'text-center', 'right': 'text-right' },
            area: {
                top: parseFloat(configData.config.area_top || 10),
                left: parseFloat(configData.config.area_left || 10),
                width: parseFloat(configData.config.area_width || 80),
                height: parseFloat(configData.config.area_height || 80),
                shape: configData.config.area_shape || 'rect'
            },
            context: configData.context,
            isDragging: false,
            // currentElement Struktur: { type: 'text'|'logo', index: 0 }
            currentElement: null,
            dragOffsetX: 0,
            dragOffsetY: 0,

            // Für die Regler oben (damit sie auch da bleiben, wenn man nicht mehr draggt)
            selectedType: null, // 'text' oder 'logo'
            selectedIndex: null,

            init() {
                this.onDrag = this.handleDrag.bind(this);
                this.stopDrag = this.handleStop.bind(this);

                // Standardauswahl: Erster Text, wenn vorhanden
                if(this.texts.length > 0) {
                    this.selectItem('text', 0);
                } else if (this.logos.length > 0) {
                    this.selectItem('logo', 0);
                }

                // Watchers für neue Items
                this.$watch('texts', val => {
                    if(val.length > 0 && this.selectedType !== 'text') {
                        // Wenn ein neuer Text dazu kommt und wir grad kein Text bearbeiten, auswählen
                        this.selectItem('text', val.length - 1);
                    }
                });
                this.$watch('logos', val => {
                    if(val.length > 0 && this.selectedType !== 'logo') {
                        this.selectItem('logo', val.length - 1);
                    }
                });
            },

            selectItem(type, index) {
                if(this.context === 'preview') return; // Sperre für Preview
                this.selectedType = type;
                this.selectedIndex = index;
            },

            startDrag(event, type, index) {
                if(this.context === 'preview') return; // Sperre für Preview
                this.isDragging = true;
                this.currentElement = { type: type, index: index };
                this.selectItem(type, index);

                if(event.cancelable) event.preventDefault();

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();

                // Startwerte holen
                let item;
                if (type === 'text') item = this.texts[index];
                else item = this.logos[index];

                let currentPercentX = parseFloat(item.x);
                let currentPercentY = parseFloat(item.y);

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
                if (!this.isDragging || !this.currentElement || this.context === 'preview') return;
                if(event.cancelable) event.preventDefault();

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const container = this.$refs.container.getBoundingClientRect();

                let mouseX = clientX - container.left;
                let mouseY = clientY - container.top;

                let newCenterX = mouseX - this.dragOffsetX;
                let newCenterY = mouseY - this.dragOffsetY;

                // Umrechnung in Prozent
                let percentX = (newCenterX / container.width) * 100;
                let percentY = (newCenterY / container.height) * 100;

                if (this.area.shape === 'circle') {
                    // --- KREIS LOGIK ---
                    const centerX = this.area.left + (this.area.width / 2);
                    const centerY = this.area.top + (this.area.height / 2);
                    const radiusX = this.area.width / 2;
                    const radiusY = this.area.height / 2;

                    let dx = percentX - centerX;
                    let dy = percentY - centerY;
                    let distance = (dx * dx) / (radiusX * radiusX) + (dy * dy) / (radiusY * radiusY);

                    if (distance > 1) {
                        let angle = Math.atan2(dy, dx);
                        percentX = centerX + radiusX * Math.cos(angle);
                        percentY = centerY + radiusY * Math.sin(angle);
                    }
                } else {
                    // --- RECHTECK LOGIK (Standard) ---
                    let minX = this.area.left;
                    let maxX = this.area.left + this.area.width;
                    let minY = this.area.top;
                    let maxY = this.area.top + this.area.height;

                    percentX = Math.max(minX, Math.min(maxX, percentX));
                    percentY = Math.max(minY, Math.min(maxY, percentY));
                }

                // Update der Livewire-Daten
                if (this.currentElement.type === 'text') {
                    this.texts[this.currentElement.index].x = percentX;
                    this.texts[this.currentElement.index].y = percentY;
                } else {
                    this.logos[this.currentElement.index].x = percentX;
                    this.logos[this.currentElement.index].y = percentY;
                }
            },

            handleStop() {
                this.isDragging = false;
                this.currentElement = null;
                window.removeEventListener('mousemove', this.onDrag);
                window.removeEventListener('touchmove', this.onDrag);
                window.removeEventListener('mouseup', this.stopDrag);
                window.removeEventListener('touchend', this.stopDrag);
            },

            // Slider Change Handler
            updateSize(size) {
                if(this.context === 'preview') return; // Sperre für Preview
                if (this.selectedType === 'text' && this.texts[this.selectedIndex]) {
                    this.texts[this.selectedIndex].size = parseFloat(size);
                } else if (this.selectedType === 'logo' && this.logos[this.selectedIndex]) {
                    this.logos[this.selectedIndex].size = parseInt(size);
                }
            }
        }
    }
</script>
@endscript

<div class="h-full flex flex-col bg-white"
     x-data="window.universalConfigurator({
        wireModels: {
            texts: @entangle('texts').live,
            logos: @entangle('logos').live
        },
        config: {{ Js::from($configSettings) }},
        fonts: {{ Js::from($fonts) }},
        context: '{{ $context }}'
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

        {{-- FORMULAR --}}
        <div class="p-6 space-y-6 text-sm max-w-2xl mx-auto {{ $context === 'preview' ? 'opacity-60 grayscale-[0.5] pointer-events-none' : '' }}">

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
                    <select wire:model.live="qty" wire:change="calculatePrice" class="appearance-none w-full pl-4 pr-10 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 font-bold text-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all cursor-pointer" {{ $context === 'preview' ? 'disabled' : '' }}>
                        @for($i = 1; $i <= 100; $i++) <option value="{{ $i }}">{{ $i }}x</option> @endfor
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg></div>
                </div>
            </div>

            {{-- 2. GRAVUR TEXTE (Liste) --}}
            <div class="space-y-4 pt-2 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-bold text-gray-900 uppercase tracking-wide">Gravuren</label>
                    @if($context !== 'preview')
                        <button wire:click="addText" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded-full font-bold transition flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Weitere Gravur
                        </button>
                    @endif
                </div>

                <div class="space-y-4">
                    @foreach($texts as $index => $textItem)
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 relative group" wire:key="text-field-{{ $textItem['id'] }}">

                            {{-- Header mit Löschen Button --}}
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-[10px] font-bold text-gray-400 uppercase">Gravur #{{ $index + 1 }}</span>
                                @if(count($texts) > 1 && $context !== 'preview')
                                    <button wire:click="removeText({{ $index }})" class="text-red-400 hover:text-red-600 text-xs p-1">Löschen</button>
                                @endif
                            </div>

                            {{-- Text Input --}}
                            <textarea
                                wire:model.live="texts.{{ $index }}.text"
                                rows="2"
                                class="w-full p-3 rounded-lg border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-primary focus:ring-1 focus:ring-primary text-sm resize-none mb-3"
                                placeholder="Ihr Wunschtext..."
                                x-on:focus="selectItem('text', {{ $index }})"
                                {{ $context === 'preview' ? 'readonly' : '' }}
                            ></textarea>

                            {{-- Controls --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <select wire:model.live="texts.{{ $index }}.font" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white text-xs text-gray-700 focus:border-primary focus:ring-1 focus:ring-primary cursor-pointer" {{ $context === 'preview' ? 'disabled' : '' }}>
                                        @foreach($fonts as $fontName => $css) <option value="{{ $fontName }}">{{ $fontName }}</option> @endforeach
                                    </select>
                                </div>
                                <div>
                                    <select wire:model.live="texts.{{ $index }}.align" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white text-xs text-gray-700 focus:border-primary focus:ring-1 focus:ring-primary cursor-pointer" {{ $context === 'preview' ? 'disabled' : '' }}>
                                        @foreach($alignmentOptions as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 3. MEDIEN --}}
            @if($configSettings['allow_logo'])
                <div class="space-y-3 pt-4 border-t border-gray-100">
                    <label class="text-sm font-bold text-gray-900 uppercase tracking-wide flex items-center gap-2">
                        <span>Medien</span>
                        <span class="text-[10px] font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded">Bilder & PDFs</span>
                    </label>

                    {{-- INFO BOX (nur wenn kein Preview) --}}
                    @if($context !== 'preview')
                        <div class="bg-blue-50/80 border border-blue-100 rounded-xl p-4 flex gap-4 items-start shadow-sm">
                            <div class="shrink-0 text-blue-500 mt-0.5">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-blue-900 text-sm mb-1">Professioneller Design-Check inklusive</h4>
                                <p class="text-sm text-blue-800/80 leading-relaxed">
                                    Sie können mehrere Bilder hochladen und positionieren. Wir prüfen jede Datei manuell.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Upload Bereich --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                        @if($context !== 'preview')
                            <input type="file" wire:model.live="new_files" multiple class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-black file:transition-colors file:cursor-pointer cursor-pointer">
                            <div wire:loading wire:target="new_files" class="text-xs text-primary mt-2">Dateien werden hochgeladen...</div>

                            @error('new_files.*') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        @endif

                        {{-- Liste der Dateien --}}
                        <div class="mt-4 space-y-2">

                            {{-- 1. Bereits gespeicherte Dateien --}}
                            @foreach($uploaded_files as $index => $path)
                                @php
                                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                                    $isImage = in_array(strtolower($ext), ['jpg','jpeg','png','webp']);
                                    $isActive = $this->isLogoActive('saved', $path);
                                @endphp
                                <div class="flex items-center justify-between bg-white p-2 rounded border {{ $isActive ? 'border-green-500 ring-1 ring-green-500' : 'border-gray-200' }}">
                                    <div class="flex items-center gap-3">
                                        @if($isImage)
                                            <img src="{{ asset('storage/'.$path) }}" class="h-10 w-10 object-cover rounded bg-gray-100">
                                        @else
                                            <div class="h-10 w-10 flex items-center justify-center bg-gray-100 rounded text-gray-500 font-bold text-xs">{{ strtoupper($ext) }}</div>
                                        @endif
                                        <div class="text-xs truncate max-w-[150px]">{{ basename($path) }}</div>
                                    </div>
                                    @if($context !== 'preview')
                                        <div class="flex gap-2">
                                            @if($isImage)
                                                <button wire:click="toggleLogo('saved', '{{ $path }}')" class="text-[10px] px-2 py-1 rounded {{ $isActive ? 'bg-green-100 text-green-700 font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                                    {{ $isActive ? 'Vorschau an' : 'Als Vorschau' }}
                                                </button>
                                            @endif
                                            <button wire:click="removeFile({{ $index }})" class="text-red-500 hover:text-red-700 p-1">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-[10px] bg-green-50 text-green-700 px-2 py-1 rounded">Vorschau aktiv</span>
                                    @endif
                                </div>
                            @endforeach

                            {{-- 2. Neue (temporäre) Dateien --}}
                            @foreach($new_files as $index => $file)
                                @php
                                    $isImage = in_array(strtolower($file->extension()), ['jpg','jpeg','png','webp']);
                                    $isActive = $this->isLogoActive('new', $index);
                                @endphp
                                <div class="flex items-center justify-between bg-white p-2 rounded border {{ $isActive ? 'border-green-500 ring-1 ring-green-500' : 'border-blue-200 border-dashed' }}">
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
                                            <button wire:click="toggleLogo('new', {{ $index }})" class="text-[10px] px-2 py-1 rounded {{ $isActive ? 'bg-green-100 text-green-700 font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                                {{ $isActive ? 'Vorschau an' : 'Als Vorschau' }}
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
                <textarea wire:model="notes" rows="2" class="w-full p-4 rounded-xl border border-yellow-200 bg-yellow-50/50 text-gray-900 placeholder-gray-400 focus:bg-yellow-50 focus:border-yellow-400 focus:ring-1 focus:ring-yellow-400/50 transition-all text-sm leading-relaxed resize-none" placeholder="Haben Sie Sonderwünsche?" {{ $context === 'preview' ? 'readonly' : '' }}></textarea>
            </div>

        </div>
    </div>

    {{-- FOOTER --}}
    @if($context !== 'preview')
        <div class="p-4 border-t border-gray-200 bg-white z-30 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] shrink-0"
             x-data="{ saved: false }"
             x-on:cart-updated.window="saved = true; setTimeout(() => saved = false, 6000)">

            <div class="max-w-2xl mx-auto flex flex-col gap-4"> {{-- Gap-4 für mehr Abstand zwischen den Buttons --}}

                {{-- HAUPT-BUTTON --}}
                <button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    :class="saved ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-900 hover:bg-black'"
                    class="w-full text-white py-4 rounded-full font-bold text-lg hover:scale-[1.01] active:scale-[0.99] transition-all duration-300 flex items-center justify-center gap-3 shadow-lg disabled:opacity-70 disabled:cursor-not-allowed"
                >
                    {{-- Zustand: Laden --}}
                    <svg wire:loading class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>

                    {{-- Zustand: Erfolg --}}
                    <template x-if="saved">
                        <div class="flex items-center gap-2 animate-fade-in">
                            <svg class="w-6 h-6 text-white animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Erfolgreich hinzugefügt!</span>
                        </div>
                    </template>

                    {{-- Zustand: Normal --}}
                    <template x-if="!saved">
                    <span wire:loading.remove>
                        @if($context === 'add') In den Warenkorb
                        @elseif($context === 'edit') Änderungen speichern
                        @elseif($context === 'calculator') Übernehmen
                        @endif
                    </span>
                    </template>

                    <span wire:loading>Verarbeite...</span>
                </button>

                {{-- SEKUNDÄR-BUTTON: Warenkorb (Erscheint nur bei Erfolg) --}}
                <div x-show="saved"
                     x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="w-full"
                     style="display: none;">

                    <a href="{{ route('cart') }}"
                       class="w-full border-2 border-gray-900 text-gray-900 py-3.5 rounded-full font-bold text-base flex items-center justify-center gap-2 hover:bg-gray-900 transition-all duration-300 group">
                        <span>Jetzt zum Warenkorb</span>
                        <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
