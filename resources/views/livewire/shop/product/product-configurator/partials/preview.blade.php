{{-- FIX: Wenn Context = 'edit', dann kein sticky, damit es im Warenkorb Modal scrollt! --}}
<div :class="isMaximized ? 'configurator-studio-preview custom-scrollbar flex flex-col items-center justify-start lg:justify-center p-2 sm:p-4 lg:p-6 border-b lg:border-b-0 lg:border-r select-none {{ $isDark ? 'bg-gray-950 border-gray-800' : 'bg-gray-50/50 border-gray-200' }}' : 'flex flex-col items-center z-40 border-b shadow-[0_4px_30px_rgba(0,0,0,0.03)] shrink-0 select-none pb-8 {{ $context === 'edit' ? 'relative' : 'sticky top-0' }} {{ $isDark ? 'bg-gray-950/80 backdrop-blur-xl border-gray-800' : 'bg-gray-50/30 border-gray-100' }}'"
     :style="isMaximized ? 'min-width: 0;' : ''"
     x-ref="stage">

    {{-- OBERE BEDIENLEISTE (2D / 3D TOGGLE) --}}
    <div x-show="config.modelPath" class="mt-4 mb-3 sm:mb-4 flex justify-center relative z-50">
        {{-- KUNDEN TOGGLE 2D / 3D --}}
        <div class="p-1.5 rounded-full shadow-sm border inline-flex items-center gap-1 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}">
            <button type="button" @click="showDrawingBoard = true" class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-300 cursor-pointer" :class="showDrawingBoard ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : '{{ $isDark ? 'text-gray-500 hover:text-white hover:bg-gray-800' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50' }}'">2D Editor</button>
            <button type="button" @click="showDrawingBoard = false" class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-300 cursor-pointer" :class="!showDrawingBoard ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : '{{ $isDark ? 'text-gray-500 hover:text-white hover:bg-gray-800' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50' }}'">3D Ansicht</button>
        </div>
    </div>

    {{-- BLAUER HINWEIS (2D / 3D WECHSEL IN EINEM EINZIGEN CONTAINER) --}}
    <div x-show="showNotice"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2 scale-98"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-2 scale-98"
         class="w-full max-w-[455px] md:max-w-[600px] px-3 sm:px-4 mb-4">
        <div class="flex items-start gap-3 p-3.5 sm:p-4 rounded-2xl border shadow-sm transition-all duration-300 {{ $isDark ? 'bg-blue-950/40 border-blue-800/60 text-blue-200 shadow-[0_0_20px_rgba(37,99,235,0.1)]' : 'bg-blue-50/90 border-blue-200 text-blue-900' }}">
            {{-- ICON (SOFORTIGER WECHSEL OHNE VERZÖGERUNG) --}}
            <div class="shrink-0 mt-0.5">
                <span x-show="config.modelPath && !showDrawingBoard" x-cloak>
                    <x-heroicon-s-exclamation-circle class="w-5 h-5 {{ $isDark ? 'text-blue-400' : 'text-blue-600' }}" />
                </span>
                <span x-show="showDrawingBoard || !config.modelPath">
                    <x-heroicon-s-information-circle class="w-5 h-5 {{ $isDark ? 'text-blue-400' : 'text-blue-600' }}" />
                </span>
            </div>

            {{-- TEXT-INHALT --}}
            <div class="text-xs sm:text-[13px] leading-relaxed flex-1">
                {{-- 3D EXPERTEN-HINWEIS --}}
                <div x-show="config.modelPath && !showDrawingBoard" x-cloak>
                    <p class="font-bold uppercase tracking-wider text-[10px] sm:text-xs mb-1 flex items-center gap-1.5 {{ $isDark ? 'text-blue-300' : 'text-blue-700' }}">
                        Hinweis zur 3D-Vorschau
                    </p>
                    <p class="{{ $isDark ? 'text-blue-200/90' : 'text-blue-800' }}">
                        Die 3D-Ansicht dient der räumlichen Orientierung und Visualisierung Ihres Designs. Vor der finalen Fertigung überprüfen unsere Experten die Positionierung und Ausrichtung im Detail und optimieren diese bei Bedarf für ein perfektes Gravurergebnis. Fertigungstechnisch bedingt können minimale Abweichungen auftreten.
                    </p>
                </div>

                {{-- 2D GESTALTUNGS-HINWEIS --}}
                <div x-show="showDrawingBoard || !config.modelPath">
                    <p class="font-bold uppercase tracking-wider text-[10px] sm:text-xs mb-1 flex items-center gap-1.5 {{ $isDark ? 'text-blue-300' : 'text-blue-700' }}">
                        Hinweis zur 2D-Gestaltung
                    </p>
                    <p class="{{ $isDark ? 'text-blue-200/90' : 'text-blue-800' }}">
                        Platzieren und arrangieren Sie Ihren persönlichen Wunschtext, gewünschte Motive/Icons sowie eigene Bilder frei auf der Gestaltungsfläche. Nutzen Sie die Werkzeuge zum Verschieben, Drehen und Skalieren, um Ihr individuelles Design optimal zu positionieren. Bitte beachten Sie, dass die grafische Vorschau von Texten, Symbolen und Motiven produktionsbedingt minimale <a href="/agb#konfigurator" target="_blank" rel="noopener noreferrer" class="underline font-semibold hover:opacity-80 transition-opacity">Abweichungen</a> zum gefertigten Endprodukt aufweisen kann.
                    </p>
                </div>
            </div>

            {{-- BUTTON ZUM AUSBLENDEN --}}
            <button type="button" @click="toggleNotice(false)" class="text-blue-400 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-200 shrink-0 p-1 rounded-lg transition-colors cursor-pointer" title="Hinweis ausblenden">
                <x-heroicon-m-x-mark class="w-4 h-4" />
            </button>
        </div>
    </div>

    {{-- EMOJI- & SONDERZEICHEN-WARNUNG --}}
    <div x-show="showEmojiWarning"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="w-full max-w-[455px] md:max-w-[600px] px-3 sm:px-4 mb-4">
        <div class="flex items-start gap-3 p-3.5 sm:p-4 rounded-2xl border shadow-sm {{ $isDark ? 'bg-amber-950/40 border-amber-800/60 text-amber-200 shadow-[0_0_20px_rgba(245,158,11,0.1)]' : 'bg-amber-50 border-amber-200 text-amber-900' }}">
            <div class="shrink-0 mt-0.5">
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 {{ $isDark ? 'text-amber-400' : 'text-amber-600' }}" />
            </div>
            <div class="text-xs sm:text-[13px] leading-relaxed flex-1">
                <p class="font-bold uppercase tracking-wider text-[10px] sm:text-xs mb-1 {{ $isDark ? 'text-amber-300' : 'text-amber-800' }}">
                    Hinweis zur Lasergravur
                </p>
                <p class="{{ $isDark ? 'text-amber-200/90' : 'text-amber-800' }}">
                    Emojis & farbige Symbole können per Lasergravur nicht dargestellt werden und wurden automatisch entfernt. Nutzen Sie bitte unsere Symbol-Bibliothek für Gravurmotive.
                </p>
            </div>
            <button @click="showEmojiWarning = false" class="text-amber-500 hover:text-amber-700 shrink-0 p-1">
                <x-heroicon-m-x-mark class="w-4 h-4" />
            </button>
        </div>
    </div>

    {{-- BÜHNE --}}
    <div class="relative overflow-hidden border-[6px] group transition-[border-color,box-shadow,background-color] duration-200 {{ $isDark ? 'bg-gray-900 border-gray-800 ring-1 ring-gray-700' : 'bg-white border-white ring-1 ring-gray-100' }}"
         :class="isMaximized ? 'shadow-2xl rounded-2xl' : 'w-full max-w-[455px] md:max-w-[600px] aspect-square shadow-[0_10px_40px_rgba(0,0,0,0.08)]'"
         :style="isMaximized
             ? 'width: min(92vw, min(62vh, 680px)); height: min(92vw, min(62vh, 680px)); aspect-ratio: 1 / 1; flex-shrink: 0;' + (activeBgImage ? ` background-image: url('${activeBgImage}'); background-size: cover; background-position: center;` : '')
             : (activeBgImage ? `background-image: url('${activeBgImage}'); background-size: cover; background-position: center;` : '')"
         x-ref="container"
         @mousedown="deselectAll($event)"
         @touchstart="deselectAll($event)">

        {{-- AUTOMATISCHES JAHRESZEITEN- & FEIERTAGS-HINTERGRUNDVIDEO (2D & 3D im Hintergrund hinter dem Produkt) --}}
        <template x-if="activeBgVideo && videoEnabled && !isCapturing">
            <video x-ref="bgVideo"
                   :key="activeBgVideo"
                   :src="activeBgVideo"
                   x-init="$el.muted = true; $el.defaultMuted = true; $el.play().catch(() => {})"
                   x-effect="if (activeBgVideo && videoEnabled) { $el.muted = true; $el.play().catch(() => {}); }"
                   autoplay
                   loop
                   muted
                   playsinline
                   class="absolute inset-0 w-full h-full object-cover pointer-events-none transition-opacity duration-500 z-[5] opacity-95"></video>
        </template>

        {{-- UNDO / REDO CONTROLS (ZURÜCK / VOR) - OBEN LINKS AUF GLEICHER EBENE WIE MAXIMAL --}}
        @if($context !== 'preview')
        <div x-show="(showDrawingBoard || !config.modelPath) && !isCapturing && context !== 'preview'"
             class="absolute top-3 left-3 z-40 flex items-center gap-0.5 p-1 rounded-xl shadow-md border backdrop-blur-md transition-all pointer-events-auto {{ $isDark ? 'bg-gray-900/95 border-gray-700/90 text-gray-100' : 'bg-white/95 border-gray-200/90 text-gray-800' }}">
            <button type="button"
                    @click.stop="undo()"
                    :disabled="!canUndo"
                    :class="canUndo
                        ? '{{ $isDark ? 'text-white hover:bg-gray-800 active:scale-95' : 'text-gray-900 hover:bg-gray-100 active:scale-95' }} cursor-pointer'
                        : '{{ $isDark ? 'text-gray-500 opacity-70' : 'text-gray-400 opacity-80' }} cursor-not-allowed'"
                    class="px-2 py-1 rounded-lg transition-all flex items-center gap-1.5 select-none"
                    title="Rückgängig (Strg+Z)">
                <span :class="canUndo ? 'text-primary' : ''" class="flex items-center shrink-0 transition-colors">
                    <x-heroicon-m-arrow-uturn-left class="w-4 h-4 stroke-[2.2]" />
                </span>
                <span class="text-[10px] font-black uppercase tracking-wider hidden sm:inline">Zurück</span>
            </button>
            <div class="w-px h-4 {{ $isDark ? 'bg-gray-700' : 'bg-gray-300' }}"></div>
            <button type="button"
                    @click.stop="redo()"
                    :disabled="!canRedo"
                    :class="canRedo
                        ? '{{ $isDark ? 'text-white hover:bg-gray-800 active:scale-95' : 'text-gray-900 hover:bg-gray-100 active:scale-95' }} cursor-pointer'
                        : '{{ $isDark ? 'text-gray-500 opacity-70' : 'text-gray-400 opacity-80' }} cursor-not-allowed'"
                    class="px-2 py-1 rounded-lg transition-all flex items-center gap-1.5 select-none"
                    title="Wiederholen (Strg+Y)">
                <span class="text-[10px] font-black uppercase tracking-wider hidden sm:inline">Vor</span>
                <span :class="canRedo ? 'text-primary' : ''" class="flex items-center shrink-0 transition-colors">
                    <x-heroicon-m-arrow-uturn-right class="w-4 h-4 stroke-[2.2]" />
                </span>
            </button>
        </div>

        {{-- MAXIMAL / MINIMAL TOGGLE BUTTON --}}
        <div x-show="!isCapturing && context !== 'preview'" class="absolute top-3 right-3 z-40 pointer-events-auto">
            <button type="button"
                    @click.stop="toggleMaximize()"
                    class="px-2.5 py-1.5 rounded-xl shadow-md border backdrop-blur-md transition-all flex items-center gap-1.5 cursor-pointer {{ $isDark ? 'bg-gray-900/95 border-gray-700/90 text-gray-200 hover:text-white hover:bg-gray-800' : 'bg-white/95 border-gray-200/90 text-gray-800 hover:text-gray-950 hover:bg-gray-100' }}"
                    :title="isMaximized ? 'Normalansicht (Esc)' : 'Maximal-Ansicht'">
                <template x-if="!isMaximized">
                    <div class="flex items-center gap-1.5">
                        <x-heroicon-m-arrows-pointing-out class="w-4 h-4 text-primary" />
                        <span class="text-[10px] font-black uppercase tracking-wider hidden sm:inline">Maximal</span>
                    </div>
                </template>
                <template x-if="isMaximized">
                    <div class="flex items-center gap-1.5">
                        <x-heroicon-m-arrows-pointing-in class="w-4 h-4 text-primary" />
                        <span class="text-[10px] font-black uppercase tracking-wider hidden sm:inline">Verkleinern</span>
                    </div>
                </template>
            </button>
        </div>
        @endif

        {{-- 3D Ansicht --}}
        <div wire:ignore x-show="config.modelPath" x-ref="container3d" class="absolute inset-0 w-full h-full z-10 cursor-move transition-opacity duration-500" :class="showDrawingBoard ? 'opacity-0 pointer-events-none' : 'opacity-100'"></div>

        {{-- Lade-Overlay für 3D --}}
        <div x-show="config.modelPath && !modelLoaded && !showDrawingBoard" x-transition.opacity.duration.300ms class="absolute inset-0 z-30 flex flex-col items-center justify-center backdrop-blur-md pointer-events-none {{ $isDark ? 'bg-gray-950/70' : 'bg-white/80' }}">
            <svg class="w-10 h-10 text-primary animate-spin mb-4 drop-shadow-lg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-primary drop-shadow-lg" x-text="loadingProgress > 0 ? `3D Modell wird geladen... ${loadingProgress}%` : '3D Modell wird geladen...'"></span>

            {{-- Ladebalken --}}
            <div class="w-48 h-1.5 mt-4 rounded-full overflow-hidden {{ $isDark ? 'bg-gray-800' : 'bg-gray-200' }}">
                <div class="h-full bg-primary transition-all duration-300" :style="`width: ${loadingProgress}%`"></div>
            </div>
        </div>

        {{-- 2D Ansicht & Zeichenbrett --}}
        <div x-show="showDrawingBoard || !config.modelPath" class="absolute inset-0 w-full h-full pointer-events-auto">

            <template x-if="activeOverlayImg || config.fallbackImg">
                <img :src="activeOverlayImg || config.fallbackImg" class="absolute inset-0 w-full h-full z-[15] object-contain pointer-events-none transition-opacity duration-500 {{ $isDark ? 'opacity-95 group-hover:opacity-100' : 'opacity-100' }}">
            </template>

            <div x-show="!isCapturing" class="absolute z-[22] border-2 border-emerald-500 bg-emerald-500/5 pointer-events-none transition-all duration-500"
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
                    box-shadow: ${config.area_shape === 'custom' ? 'none' : ('0 0 0 9999px ' + ('{{ $isDark }}' === '1' ? 'rgba(0,0,0,0.4)' : 'rgba(255,255,255,0.4)'))};
                 `">
            </div>

            {{-- Hilfslinien fürs Snapping --}}
            <div x-show="showGuideX && !isCapturing" class="absolute top-0 bottom-0 border-l border-primary/60 border-dashed pointer-events-none transition-opacity" :style="'left: ' + areaCenterX + '%; z-index: 23;'"></div>
            <div x-show="showGuideY && !isCapturing" class="absolute left-0 right-0 border-t border-primary/60 border-dashed pointer-events-none transition-opacity" :style="'top: ' + areaCenterY + '%; z-index: 23;'"></div>

            {{-- CLIP-CONTAINER FÜR ELEMENTE --}}
            <div class="absolute z-[25] overflow-hidden pointer-events-none transition-all duration-300"
                 :style="`
                        top: ${config.area_shape === 'custom' ? 0 : (config.area_top ?? 0)}%;
                        left: ${config.area_shape === 'custom' ? 0 : (config.area_left ?? 0)}%;
                        width: ${config.area_shape === 'custom' ? 100 : (config.area_width || 100)}%;
                        height: ${config.area_shape === 'custom' ? 100 : (config.area_height || 100)}%;
                        border-radius: ${config.area_shape === 'circle' ? '50%' : '0'};
                        clip-path: ${
                            config.area_shape === 'custom' && config.custom_points && config.custom_points.length > 0
                            ? 'polygon(' + config.custom_points.map(p => p.x + '% ' + p.y + '%').join(', ') + ')'
                            : 'none'
                        };
                     `">

                <div x-show="currentTexts.length === 0 && currentLogos.length === 0 && context !== 'preview' && !isCapturing" class="absolute inset-0 flex items-center justify-center pointer-events-auto">
                    <button @click="addFallbackText()"
                            :class="isDarkSurface ? 'bg-gray-900/90 text-white border-gray-600 hover:bg-primary hover:text-gray-900 hover:border-primary' : '{{ $isDark ? 'bg-gray-900/90 text-gray-300 border-gray-700 hover:bg-primary hover:text-gray-900 hover:border-primary' : 'bg-white/90 text-gray-800 border-gray-200 hover:bg-primary hover:text-white hover:border-primary' }}'"
                            class="px-6 py-3 backdrop-blur-sm border rounded-2xl shadow-lg text-[10px] font-black uppercase tracking-widest transition-all">
                        + Text hinzufügen
                    </button>
                </div>

                <template x-for="(textItem,index) in currentTexts" :key="'content-text-' + activeSide + '-' + (textItem.id || index)">
                    <div x-show="currentTexts[index] !== undefined" class="absolute touch-none pointer-events-auto group/item"
                         :style="currentTexts[index] ? `
                            left: ${config.area_shape === 'custom' ? (currentTexts[index].x ?? areaCenterX) : ((currentTexts[index].x ?? areaCenterX)-(config.area_left ?? 0))/(config.area_width || 100)* 100}%;
                            top: ${config.area_shape === 'custom' ? (currentTexts[index].y ?? areaCenterY) : ((currentTexts[index].y ?? areaCenterY)-(config.area_top ?? 0))/(config.area_height || 100)* 100}%;
                            transform-origin: ${currentTexts[index].align==='left'?'0% 50%':(currentTexts[index].align==='right'?'100% 50%':'50% 50%')};
                            transform: translate(${currentTexts[index].align==='left'?'0%':(currentTexts[index].align==='right'?'-100%':'-50%')},-50%) rotate(${currentTexts[index].rotation || 0}deg);
                            z-index: ${selectedIndex===index?100:10}
                         ` : 'display:none;'"
                         @mousedown.stop="startAction($event,'text',index,'drag')"
                         @touchstart.stop="startAction($event,'text',index,'drag')">

                        <div class="relative transition-all rounded-lg p-1.5 border-[1.5px]"
                             :class="selectedIndex===index && context!=='preview'
                                 ? (isDarkSurface ? 'border-primary border-dashed bg-white/10 backdrop-blur-[2px] shadow-[0_0_15px_rgba(197,160,89,0.25)]' : 'border-primary border-dashed {{ $isDark ? 'bg-white/10' : 'bg-white/40' }} backdrop-blur-sm shadow-lg')
                                 : (isDarkSurface ? 'border-transparent hover:border-primary/60 hover:bg-white/5' : 'border-transparent hover:border-gray-500 {{ $isDark ? 'hover:bg-white/5' : 'hover:bg-white/10' }}')">

                            <template x-if="currentTexts[index] !== undefined && !isCapturing">
                                <textarea x-model="textItem.text"
                                          :data-id="textItem.id"
                                          :rows="(textItem.text?.match(/\n/g) || []).length + 1"
                                          x-init="
                                                $watch('textItem.text', () => fitTextarea(textItem.id, $el));
                                                $watch('textItem.size', () => $nextTick(() => fitTextarea(textItem.id, $el)));
                                                $watch('textItem.font', () => setTimeout(() => fitTextarea(textItem.id, $el), 50));
                                                $nextTick(() => fitTextarea(textItem.id, $el));
                                          "
                                          @input="handleTextInput(textItem, $el)"
                                          @blur="recordHistory()"
                                          wrap="off"
                                          class="bg-transparent font-bold resize-none overflow-hidden block whitespace-pre p-0 m-0 border-0 outline-none shadow-none ring-0 select-none"
                                          :class="alignMap[textItem.align || 'center']"
                                          :style="`
                                                width: ${textDims[textItem.id]?.width || 'auto'};
                                                height: ${textDims[textItem.id]?.height || 'auto'};
                                                font-size: ${(20 * (textItem.size || 1)) * scaleFactor}px;
                                                font-family: ${fontMap[textItem.font] || 'Arial'};
                                                line-height: 1.15;
                                                color: ${isDarkSurface ? 'rgba(248, 250, 252, 0.96)' : 'rgba(17, 24, 39, 0.95)'};
                                                -webkit-text-fill-color: ${isDarkSurface ? '#f8fafc' : '#111827'};
                                                caret-color: #C5A059;
                                                filter: ${isDarkSurface
                                                    ? 'drop-shadow(0px 1px 3px rgba(0,0,0,0.9)) drop-shadow(0px 0px 6px rgba(255,255,255,0.25))'
                                                    : 'drop-shadow(0px 0px 3px rgba(255,255,255,0.75)) drop-shadow(0px 1px 2px rgba(0,0,0,0.25))'};
                                                white-space: pre !important;
                                            `"
                                          placeholder="Text eingeben...">
                                </textarea>
                            </template>
                            <template x-if="currentTexts[index] !== undefined && isCapturing">
                                <div x-text="textItem.text"
                                     class="bg-transparent font-bold overflow-visible block p-0 m-0 border-0 outline-none shadow-none ring-0 select-none"
                                     :class="alignMap[textItem.align || 'center']"
                                     :style="`
                                           width: ${textDims[textItem.id]?.width || 'auto'};
                                           height: auto !important;
                                           font-size: ${(20 * (textItem.size || 1)) * scaleFactor}px;
                                           font-family: ${fontMap[textItem.font] || 'Arial'};
                                           line-height: 1.15;
                                           color: ${isDarkSurface ? 'rgba(248, 250, 252, 0.96)' : 'rgba(17, 24, 39, 0.95)'};
                                           -webkit-text-fill-color: ${isDarkSurface ? '#f8fafc' : '#111827'};
                                           filter: ${isDarkSurface
                                               ? 'drop-shadow(0px 1px 3px rgba(0,0,0,0.9)) drop-shadow(0px 0px 6px rgba(255,255,255,0.25))'
                                               : 'drop-shadow(0px 0px 3px rgba(255,255,255,0.75)) drop-shadow(0px 1px 2px rgba(0,0,0,0.25))'};
                                           white-space: pre !important;
                                       `">
                                </div>
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
                            left: ${config.area_shape === 'custom' ? (currentLogos[index].x ?? areaCenterX) : ((currentLogos[index].x ?? areaCenterX)-(config.area_left ?? 0))/(config.area_width || 100)* 100}%;
                            top: ${config.area_shape === 'custom' ? (currentLogos[index].y ?? areaCenterY) : ((currentLogos[index].y ?? areaCenterY)-(config.area_top ?? 0))/(config.area_height || 100)* 100}%;
                            width: ${((currentLogos[index].size || 100) * scaleFactor)}px;
                            transform: translate(-50%,-50%) rotate(${currentLogos[index].rotation || 0}deg);
                            z-index: ${selectedIndex===index?100:10}
                         ` : 'display:none;'"
                         @mousedown.stop="startAction($event,'logo',index,'drag')"
                         @touchstart.stop="startAction($event,'logo',index,'drag')">

                        <div class="relative transition-all rounded-lg ring-2"
                             :class="selectedIndex===index && context!=='preview'
                                 ? (isDarkSurface ? 'ring-primary bg-white/10 backdrop-blur-[2px] shadow-[0_0_15px_rgba(197,160,89,0.25)]' : 'ring-primary {{ $isDark ? 'bg-white/10' : 'bg-white/40' }} backdrop-blur-sm shadow-lg')
                                 : (isDarkSurface ? 'ring-transparent hover:ring-primary/60 hover:bg-white/5' : 'ring-transparent hover:ring-gray-500 {{ $isDark ? 'hover:bg-white/5' : 'hover:bg-white/10' }}')">
                            <template x-if="currentLogos[index] !== undefined">
                                <img crossorigin="anonymous"
                                     :src="currentLogos[index].url"
                                     class="w-full h-auto pointer-events-none rounded-lg transition-all duration-200"
                                     :style="isDarkSurface
                                         ? 'filter: brightness(0) invert(1) opacity(0.93) drop-shadow(0px 1px 3px rgba(0,0,0,0.85)) drop-shadow(0px 0px 5px rgba(255,255,255,0.2));'
                                         : 'opacity: 0.92; filter: drop-shadow(0px 0px 3px rgba(255,255,255,0.75)) drop-shadow(0px 1px 2px rgba(0,0,0,0.25));'">
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
                 class="absolute bottom-6 left-1/2 -translate-x-1/2 backdrop-blur-xl shadow-2xl border rounded-2xl sm:rounded-full px-3 sm:px-4 py-1.5 sm:py-2 flex items-center gap-1 sm:gap-2 z-[200] pointer-events-auto schwebender-werkzeugkasten {{ $isDark ? 'bg-gray-950/90 border-gray-700' : 'bg-gray-900/90 border-gray-700' }}">

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'drag')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'drag')"
                     class="flex flex-col items-center justify-center cursor-move text-gray-300 hover:text-white hover:bg-gray-800/70 transition-colors px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-xl group select-none" title="Verschieben">
                    <x-heroicon-m-arrows-pointing-out class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                    <span class="text-[9px] sm:text-[10px] font-semibold tracking-tight mt-0.5 text-gray-400 group-hover:text-white pointer-events-none">Verschieben</span>
                </div>

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'rotate')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'rotate')"
                     class="flex flex-col items-center justify-center cursor-alias text-gray-300 hover:text-primary hover:bg-primary/10 transition-colors px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-xl group select-none" title="Drehen">
                    <x-heroicon-m-arrow-path class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                    <span class="text-[9px] sm:text-[10px] font-semibold tracking-tight mt-0.5 text-gray-400 group-hover:text-primary pointer-events-none">Drehen</span>
                </div>

                <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'resize')" @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'resize')"
                     class="flex flex-col items-center justify-center cursor-se-resize text-gray-300 hover:text-primary hover:bg-primary/10 transition-colors px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-xl group select-none" title="Skalieren">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 rotate-90 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="text-[9px] sm:text-[10px] font-semibold tracking-tight mt-0.5 text-gray-400 group-hover:text-primary pointer-events-none">Größe</span>
                </div>

                <div class="w-px h-7 bg-gray-700 mx-0.5 sm:mx-1 self-center"></div>

                <div @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()"
                     class="flex flex-col items-center justify-center cursor-pointer text-gray-300 hover:text-red-400 hover:bg-red-500/10 transition-colors px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-xl group select-none" title="Löschen">
                    <x-heroicon-m-trash class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                    <span class="text-[9px] sm:text-[10px] font-semibold tracking-tight mt-0.5 text-gray-400 group-hover:text-red-400 pointer-events-none">Löschen</span>
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
                                <button @click="currentTexts[selectedIndex].font = fontKey; showFontMenu = false; updateTexture(); recordHistory()" class="w-full text-left px-4 py-2.5 text-sm rounded-xl truncate transition-colors {{ $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-50' }}" :style="{ fontFamily: fontName }" :class="currentTexts[selectedIndex].font === fontKey ? 'text-primary font-bold {{ $isDark ? 'bg-primary/10' : 'bg-primary/5' }}' : '{{ $isDark ? 'text-gray-300' : 'text-gray-700' }}'"><span x-text="fontKey"></span></button>
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
                        <button @click="setTextAlign('left')" class="p-3 rounded-xl hover:text-primary transition-colors {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></button>
                        <button @click="setTextAlign('center')" class="p-3 rounded-xl hover:text-primary transition-colors {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></button>
                        <button @click="setTextAlign('right')" class="p-3 rounded-xl hover:text-primary transition-colors {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></button>
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
                    <input type="range" min="0.1" max="5" step="0.05" x-model="currentTexts[selectedIndex].size" @change="recordHistory()" class="w-full accent-primary h-2 rounded-lg appearance-none cursor-pointer {{ $isDark ? 'bg-gray-800' : 'bg-gray-100' }}">
                </template>
                <template x-if="selectedType === 'logo' && currentLogos[selectedIndex] !== undefined">
                    <input type="range" min="10" max="500" step="1" x-model="currentLogos[selectedIndex].size" @change="recordHistory()" class="w-full accent-primary h-2 rounded-lg appearance-none cursor-pointer {{ $isDark ? 'bg-gray-800' : 'bg-gray-100' }}">
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
            <button @click="centerHorizontal" class="flex flex-col items-center justify-center min-w-[3rem] px-2 py-2 rounded-xl text-gray-500 transition-colors {{ $isDark ? 'hover:bg-gray-800 hover:text-gray-300' : 'hover:bg-gray-50 hover:text-gray-700' }}" title="Horizontal zentrieren">
                <x-heroicon-m-pause class="w-5 h-5 rotate-90" />
                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest mt-1">Horiz.</span>
            </button>
            <button @click="centerVertical" class="flex flex-col items-center justify-center min-w-[3rem] px-2 py-2 rounded-xl text-gray-500 transition-colors {{ $isDark ? 'hover:bg-gray-800 hover:text-gray-300' : 'hover:bg-gray-50 hover:text-gray-700' }}" title="Vertikal zentrieren">
                <x-heroicon-m-pause class="w-5 h-5" />
                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest mt-1">Vertik.</span>
            </button>
            <button @click="centerBoth" class="flex flex-col items-center justify-center min-w-[3rem] px-2 py-2 bg-primary/10 hover:bg-primary/20 text-primary rounded-xl transition-colors shadow-sm" title="Exakt in die Mitte">
                <x-heroicon-m-viewfinder-circle class="w-5 h-5" />
                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest mt-1">Mitte</span>
            </button>
        </div>
    </div>

</div>
