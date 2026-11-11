{{-- FIX: Wenn Context = 'edit', dann kein sticky, damit es im Warenkorb Modal scrollt! --}}
<div :class="isMaximized ? 'configurator-studio-preview custom-scrollbar flex flex-col items-center justify-start p-2 sm:p-4 lg:p-5 border-b lg:border-b-0 lg:border-r select-none {{ $isDark ? 'bg-gray-950 border-gray-800' : 'bg-gray-50/50 border-gray-200' }}' : 'flex flex-col items-center z-40 border-b shadow-[0_4px_30px_rgba(0,0,0,0.03)] shrink-0 select-none px-3 sm:px-5 pb-3 sm:pb-4 {{ ($context === 'edit' || !empty($isBeginnerStudioMode)) ? 'relative' : 'sticky top-0' }} {{ $isDark ? 'bg-gray-950/80 backdrop-blur-xl border-gray-800' : 'bg-gray-50/30 border-gray-100' }}'"
     :style="isMaximized ? 'min-width: 0;' : ''"
     x-ref="stage">

    {{-- BLAUER HINWEIS (2D / 3D WECHSEL IN EINEM EINZIGEN CONTAINER, MOBIL VOLLE BREITE & KOMPAKT) --}}
    <div x-show="showNotice"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2 scale-98"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-2 scale-98"
         class="w-full max-w-[455px] md:max-w-[600px] px-1 sm:px-2 mt-1 mb-2 shrink-0">
        <div class="flex items-start gap-2.5 px-3 py-2.5 sm:p-3 rounded-xl border shadow-sm transition-all duration-300 {{ $isDark ? 'bg-blue-950/40 border-blue-800/60 text-blue-200 shadow-[0_0_20px_rgba(37,99,235,0.1)]' : 'bg-blue-50/90 border-blue-200 text-blue-900' }}">
            {{-- ICON (SOFORTIGER WECHSEL OHNE VERZÖGERUNG) --}}
            <div class="shrink-0 mt-0.5">
                <span x-show="config.modelPath && !showDrawingBoard" x-cloak>
                    <x-heroicon-s-exclamation-circle class="w-4 h-4 sm:w-5 sm:h-5 {{ $isDark ? 'text-blue-400' : 'text-blue-600' }}" />
                </span>
                <span x-show="showDrawingBoard || !config.modelPath">
                    <x-heroicon-s-information-circle class="w-4 h-4 sm:w-5 sm:h-5 {{ $isDark ? 'text-blue-400' : 'text-blue-600' }}" />
                </span>
            </div>

            {{-- TEXT-INHALT (KOMPAKT & PRÄZISE) --}}
            <div class="text-[11px] sm:text-xs leading-snug flex-1 min-w-0">
                {{-- 3D EXPERTEN-HINWEIS --}}
                <div x-show="config.modelPath && !showDrawingBoard" x-cloak>
                    <p class="font-bold uppercase tracking-wider text-[10px] mb-0.5 flex items-center gap-1.5 {{ $isDark ? 'text-blue-300' : 'text-blue-700' }}">
                        Hinweis zur 3D-Vorschau
                    </p>
                    <p class="{{ $isDark ? 'text-blue-200/90' : 'text-blue-800' }}">
                        Die 3D-Ansicht dient zur räumlichen Orientierung. Unsere Experten prüfen und optimieren die Ausrichtung vor der Gravur, minimale fertigungsbedingte Abweichungen sind möglich. Die 3D-Ansicht kann leicht vom Original abweichen.
                    </p>
                </div>

                {{-- 2D GESTALTUNGS-HINWEIS --}}
                <div x-show="showDrawingBoard || !config.modelPath">
                    <p class="font-bold uppercase tracking-wider text-[10px] mb-0.5 flex items-center gap-1.5 {{ $isDark ? 'text-blue-300' : 'text-blue-700' }}"
                       x-text="config.isBeginnerMode ? 'Hinweis zur Neulings-Vorschau' : 'Hinweis zur 2D-Gestaltung'">
                        Hinweis zur 2D-Gestaltung
                    </p>
                    <p x-show="!config.isBeginnerMode" class="{{ $isDark ? 'text-blue-200/90' : 'text-blue-800' }}">
                        Platziere, drehe und skaliere Texte, Motive oder eigene Bilder frei auf der Fläche. Produktionsbedingt sind minimale <a href="/agb#konfigurator" target="_blank" rel="noopener noreferrer" class="underline font-semibold hover:opacity-80 transition-opacity">Abweichungen</a> zur Vorschau möglich.
                    </p>
                    <p x-show="config.isBeginnerMode" x-cloak class="{{ $isDark ? 'text-blue-200/90' : 'text-blue-800' }}">
                        Im Neulings-Modus sind Symbol (oben mittig) und Wunschtext (unten mittig) fest an der optimalen Position ausgerichtet. Deine Eingaben unten aktualisieren diese 2D- & 3D-Vorschau automatisch in Echtzeit.
                    </p>
                </div>
            </div>

            {{-- BUTTON ZUM AUSBLENDEN --}}
            <button type="button" @click="toggleNotice(false)" class="text-blue-400 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-200 shrink-0 p-0.5 rounded-lg transition-colors cursor-pointer" title="Hinweis ausblenden">
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
         class="w-full max-w-[455px] md:max-w-[600px] px-1 sm:px-2 mb-2">
        <div class="flex items-start gap-2.5 px-3 py-2.5 sm:p-3 rounded-xl border shadow-sm {{ $isDark ? 'bg-amber-950/40 border-amber-800/60 text-amber-200 shadow-[0_0_20px_rgba(245,158,11,0.1)]' : 'bg-amber-50 border-amber-200 text-amber-900' }}">
            <div class="shrink-0 mt-0.5">
                <x-heroicon-s-exclamation-triangle class="w-4 h-4 sm:w-5 sm:h-5 {{ $isDark ? 'text-amber-400' : 'text-amber-600' }}" />
            </div>
            <div class="text-[11px] sm:text-xs leading-snug flex-1 min-w-0">
                <p class="font-bold uppercase tracking-wider text-[10px] mb-0.5 {{ $isDark ? 'text-amber-300' : 'text-amber-800' }}">
                    Hinweis zur Lasergravur
                </p>
                <p class="{{ $isDark ? 'text-amber-200/90' : 'text-amber-800' }}">
                    Emojis & farbige Symbole können nicht per Laser graviert werden und wurden entfernt. Nutze bitte unsere Symbol-Bibliothek.
                </p>
            </div>
            <button @click="showEmojiWarning = false" class="text-amber-500 hover:text-amber-700 shrink-0 p-0.5">
                <x-heroicon-m-x-mark class="w-4 h-4" />
            </button>
        </div>
    </div>

    {{-- OBEN AUSSERHALB DER ANSICHT: LINKS (ZURÜCK / VOR PFEILE) | MITTIG (2D / 3D SWITCH) | RECHTS (MAXIMAL) --}}
    <div :class="isMaximized ? 'w-[min(90vw,min(58vh,600px))]' : 'w-full max-w-[455px] md:max-w-[600px]'"
         class="mt-4 sm:mt-5 mb-2 px-2 sm:px-3 flex flex-col gap-2 relative z-50 shrink-0">

        <div class="flex items-center justify-between gap-1.5 sm:gap-2 w-full">
            {{-- LINKS: ZURÜCK (UNDO) & VOR (REDO) PFEIL-BUTTONS (Bewusst ohne Text-Label „Zurück“ / „Vor“, nur als kompakte Pfeil-Icons) --}}
            <div class="flex items-center justify-start min-w-[74px] sm:min-w-[96px]">
                @if($context !== 'preview')
                <div x-show="(showDrawingBoard || !config.modelPath) && context !== 'preview' && !config.isBeginnerMode"
                     data-snapshot-ignore="true"
                     class="flex items-center gap-0.5 p-1 rounded-xl shadow-sm border backdrop-blur-md transition-all pointer-events-auto {{ $isDark ? 'bg-gray-900/95 border-gray-800 text-gray-100' : 'bg-white/95 border-gray-200/90 text-gray-800' }}">
                    {{-- ZURÜCK-BUTTON (Undo / Rückgängig) --}}
                    <button type="button"
                            @click.stop="undo()"
                            :disabled="!canUndo"
                            :class="canUndo
                                ? '{{ $isDark ? 'text-white hover:bg-gray-800 active:scale-95' : 'text-gray-900 hover:bg-gray-100 active:scale-95' }} cursor-pointer'
                                : '{{ $isDark ? 'text-gray-500 opacity-70' : 'text-gray-400 opacity-80' }} cursor-not-allowed'"
                            class="p-1.5 rounded-lg transition-all flex items-center justify-center select-none"
                            title="Rückgängig (Strg+Z)"
                            aria-label="Rückgängig (Strg+Z)">
                        <span :class="canUndo ? 'text-primary' : ''" class="flex items-center shrink-0 transition-colors">
                            <x-heroicon-m-arrow-uturn-left class="w-4 h-4 stroke-[2.2]" />
                        </span>
                    </button>
                    <div class="w-px h-4 {{ $isDark ? 'bg-gray-700' : 'bg-gray-300' }}"></div>
                    {{-- VOR-BUTTON (Redo / Wiederholen) --}}
                    <button type="button"
                            @click.stop="redo()"
                            :disabled="!canRedo"
                            :class="canRedo
                                ? '{{ $isDark ? 'text-white hover:bg-gray-800 active:scale-95' : 'text-gray-900 hover:bg-gray-100 active:scale-95' }} cursor-pointer'
                                : '{{ $isDark ? 'text-gray-500 opacity-70' : 'text-gray-400 opacity-80' }} cursor-not-allowed'"
                            class="p-1.5 rounded-lg transition-all flex items-center justify-center select-none"
                            title="Wiederholen (Strg+Y)"
                            aria-label="Wiederholen (Strg+Y)">
                        <span :class="canRedo ? 'text-primary' : ''" class="flex items-center shrink-0 transition-colors">
                            <x-heroicon-m-arrow-uturn-right class="w-4 h-4 stroke-[2.2]" />
                        </span>
                    </button>
                </div>
                @endif
            </div>

            {{-- MITTIG: 2D / 3D SWITCH --}}
            <div class="flex items-center justify-center">
                <div x-show="config.modelPath" class="p-1 rounded-full shadow-sm border inline-flex items-center gap-1 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}">
                    <button type="button" @click="showDrawingBoard = true" class="px-4 sm:px-5 py-1.5 rounded-full text-[11px] sm:text-xs font-black uppercase tracking-widest transition-all duration-300 cursor-pointer" :class="showDrawingBoard ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : '{{ $isDark ? 'text-gray-500 hover:text-white hover:bg-gray-800' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50' }}'">2D</button>
                    <button type="button" @click="showDrawingBoard = false" class="px-4 sm:px-5 py-1.5 rounded-full text-[11px] sm:text-xs font-black uppercase tracking-widest transition-all duration-300 cursor-pointer" :class="!showDrawingBoard ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : '{{ $isDark ? 'text-gray-500 hover:text-white hover:bg-gray-800' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50' }}'">3D</button>
                </div>
            </div>

            {{-- RECHTS: MAXIMAL / VERKLEINERN BUTTON --}}
            <div class="flex items-center justify-end min-w-[74px] sm:min-w-[96px]">
                @if($context !== 'preview')
                <div x-show="context !== 'preview'" data-snapshot-ignore="true" class="pointer-events-auto">
                    <button type="button"
                            @click.stop="toggleMaximize()"
                            class="px-2.5 py-2 rounded-xl shadow-sm border backdrop-blur-md transition-all flex items-center gap-1.5 cursor-pointer {{ $isDark ? 'bg-gray-900/95 border-gray-800 text-gray-200 hover:text-white hover:bg-gray-800' : 'bg-white/95 border-gray-200/90 text-gray-800 hover:text-gray-950 hover:bg-gray-100' }}"
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
            </div>
        </div>
    </div>

    {{-- OBEN AUSSEN STICKY DIREKT AN DER VORSCHAU (VOLLE BREITE, OHNE AUSSEN-BORDER): + TEXT | + BILD | SYMBOLE | SPRÜCHE + ZOOM-REGLER --}}
    <div x-show="(showDrawingBoard || !config.modelPath) && context !== 'preview'"
         x-cloak
         data-snapshot-ignore="true"
         :class="isMaximized ? 'w-[min(90vw,min(58vh,600px))]' : 'w-full max-w-[455px] md:max-w-[600px]'"
         class="px-1 sm:px-2 pb-1.5 flex flex-col gap-1.5 relative z-40 pointer-events-auto schwebender-werkzeugkasten shrink-0">

        {{-- REIHE 1: + TEXT | + BILD | SYMBOLE | SPRÜCHE (Nur im Studio-Editor, nicht im Neulings-Modus) --}}
        <div x-show="!config.isBeginnerMode"
             class="flex items-center justify-around gap-1 sm:gap-2 w-full">
            <button type="button"
                    @mousedown.stop @click.stop="addNewTextElement()"
                    class="flex-1 flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 cursor-pointer transition-all px-2 py-1.5 sm:py-2 rounded-xl group select-none {{ $isDark ? 'text-primary hover:text-white hover:bg-primary/20' : 'text-[#9A7B3E] hover:text-[#7A5E28] hover:bg-[#C5A059]/12' }}"
                    title="Neues Textfeld hinzufügen">
                <span class="font-serif font-black text-xs sm:text-sm leading-none flex items-center pointer-events-none text-[#C5A059]">Aa+</span>
                <span class="text-[10px] sm:text-xs font-bold tracking-tight pointer-events-none whitespace-nowrap">+ Text</span>
            </button>

            @if($configSettings['allow_logo'] ?? true)
                <div class="w-px h-5 {{ $isDark ? 'bg-gray-800' : 'bg-gray-200/80' }}"></div>
                <button type="button"
                        @mousedown.stop @click.stop="toggleTab('files')"
                        :class="activeTab === 'files' ? '{{ $isDark ? 'bg-primary/20 text-primary' : 'bg-[#C5A059]/15 text-[#7A5E28]' }}' : '{{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}'"
                        class="relative flex-1 flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 cursor-pointer transition-all px-2 py-1.5 sm:py-2 rounded-xl group select-none"
                        title="Eigene Dateien & Bilder hochladen">
                    <span class="sr-only">Eigene Dateien</span>
                    @if(count($uploaded_files ?? []) > 0)
                        <span class="absolute top-0.5 right-1.5 w-4 h-4 rounded-full bg-primary text-gray-900 text-[9px] font-black flex items-center justify-center shadow-2xs pointer-events-none">{{ count($uploaded_files) }}</span>
                    @endif
                    <x-heroicon-o-photo class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none text-[#C5A059] shrink-0" />
                    <span class="text-[10px] sm:text-xs font-bold tracking-tight pointer-events-none whitespace-nowrap">+ Bild</span>
                </button>
            @endif

            <div class="w-px h-5 {{ $isDark ? 'bg-gray-800' : 'bg-gray-200/80' }}"></div>
            <button type="button"
                    @mousedown.stop @click.stop="toggleTab('symbols')"
                    :class="activeTab === 'symbols' ? '{{ $isDark ? 'bg-primary/20 text-primary' : 'bg-[#C5A059]/15 text-[#7A5E28]' }}' : '{{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}'"
                    class="flex-1 flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 cursor-pointer transition-all px-2 py-1.5 sm:py-2 rounded-xl group select-none"
                    title="Symbole auswählen">
                <x-heroicon-o-sparkles class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none text-[#C5A059] shrink-0" />
                <span class="text-[10px] sm:text-xs font-bold tracking-tight pointer-events-none whitespace-nowrap">Symbole</span>
            </button>

            @if(!empty($quotes))
                <div class="w-px h-5 {{ $isDark ? 'bg-gray-800' : 'bg-gray-200/80' }}"></div>
                <button type="button"
                        @mousedown.stop @click.stop="toggleTab('quotes')"
                        :class="activeTab === 'quotes' ? '{{ $isDark ? 'bg-primary/20 text-primary' : 'bg-[#C5A059]/15 text-[#7A5E28]' }}' : '{{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}'"
                        class="flex-1 flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 cursor-pointer transition-all px-2 py-1.5 sm:py-2 rounded-xl group select-none"
                        title="Sprüche & Widmungen einfügen">
                    <x-heroicon-o-chat-bubble-bottom-center-text class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none text-[#C5A059] shrink-0" />
                    <span class="text-[10px] sm:text-xs font-bold tracking-tight pointer-events-none whitespace-nowrap">Sprüche</span>
                </button>
            @endif
        </div>

        {{-- REIHE 2: ZOOM-REGLER AUSSERHALB OBERHALB DER VORSCHAU ÜBER DIE VOLLE BREITE --}}
        @if($context !== 'preview')
        <div @mousedown.stop
             @touchstart.stop
             class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl border w-full transition-all {{ $isDark ? 'bg-gray-900/90 border-gray-800 text-gray-100' : 'bg-white/95 border-gray-200/80 text-gray-800 shadow-2xs' }}">
            <button type="button"
                    @click.stop="stepStageZoom(-0.15)"
                    :disabled="stageZoom <= 1.01"
                    :class="stageZoom > 1.01 ? 'hover:text-primary active:scale-90 cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                    class="w-6 h-6 rounded-full flex items-center justify-center transition-all select-none shrink-0"
                    title="Herauszoomen">
                <x-heroicon-m-magnifying-glass-minus class="w-4 h-4 text-[#C5A059]" />
            </button>
            <input type="range"
                   min="1"
                   max="3"
                   step="0.02"
                   :value="stageZoom"
                   @input.stop="setStageZoom($event.target.value)"
                   class="flex-1 h-1.5 rounded-lg appearance-none cursor-pointer accent-[#C5A059] {{ $isDark ? 'bg-gray-700' : 'bg-gray-200' }}"
                   title="Arbeitsfläche zoomen (100% - 300%)">
            <button type="button"
                    @click.stop="stepStageZoom(0.15)"
                    :disabled="stageZoom >= 2.99"
                    :class="stageZoom < 2.99 ? 'hover:text-primary active:scale-90 cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                    class="w-6 h-6 rounded-full flex items-center justify-center transition-all select-none shrink-0"
                    title="Heranzoomen">
                <x-heroicon-m-magnifying-glass-plus class="w-4 h-4 text-[#C5A059]" />
            </button>
            <button type="button"
                    @click.stop="resetStageZoom()"
                    :class="stageZoom > 1.01 ? 'text-[#9A7B3E] hover:text-primary cursor-pointer' : 'text-gray-400 cursor-default'"
                    class="min-w-[38px] text-right text-[10px] font-black tracking-tight transition-colors select-none shrink-0"
                    title="Zoom zurücksetzen (100%)">
                <span x-text="Math.round(stageZoom * 100) + '%'">100%</span>
            </button>
        </div>
        @endif
    </div>

    {{-- BÜHNE --}}
    <div class="relative overflow-hidden border-[6px] rounded-t-2xl group transition-[border-color,box-shadow,background-color] duration-200 {{ $isDark ? 'bg-gray-900 border-gray-800 ring-1 ring-gray-700' : 'bg-white border-white ring-1 ring-gray-100' }}"
         :class="[
             isMaximized ? 'shadow-2xl' : 'w-full max-w-[455px] md:max-w-[600px] aspect-square shadow-[0_10px_40px_rgba(0,0,0,0.08)]',
             (showDrawingBoard && context !== 'preview' && !config.isBeginnerMode && (selectedIndex !== null || isCropping)) ? 'rounded-b-none' : 'rounded-b-2xl'
         ]"
         :style="isMaximized
             ? 'width: min(90vw, min(58vh, 600px)); height: min(90vw, min(58vh, 600px)); aspect-ratio: 1 / 1; flex-shrink: 0;' + (activeBgImage ? ` background-image: url('${activeBgImage}'); background-size: cover; background-position: center;` : '')
             : (activeBgImage ? `background-image: url('${activeBgImage}'); background-size: cover; background-position: center;` : '')"
         x-ref="container"
         @mousedown="deselectAll($event)"
         @touchstart="deselectAll($event)">

        {{-- AUTOMATISCHES JAHRESZEITEN- & FEIERTAGS-HINTERGRUNDVIDEO (2D & 3D im Hintergrund hinter dem Produkt) --}}
        <template x-if="activeBgVideo && videoEnabled">
            <video x-ref="bgVideo"
                   data-snapshot-ignore="true"
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

        @if($context !== 'preview')
        {{-- EXTRA-ZOOMFENSTER (NAVIGATOR / MINIMAP) OBEN RECHTS ZUM MM-GENAUEN VERSCHIEBEN --}}
        <div x-show="stageZoom > 1.01 && (showDrawingBoard || !config.modelPath) && context !== 'preview'"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-90"
             data-snapshot-ignore="true"
             x-ref="zoomMinimap"
             @mousedown.stop.prevent="startMinimapDrag($event)"
             @touchstart.stop.prevent="startMinimapDrag($event)"
             title="Ausschnitt im Zoomfenster millimetergenau verschieben"
             class="absolute top-2.5 right-2.5 z-40 w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden shadow-[0_8px_24px_rgba(0,0,0,0.28)] border-2 border-[#C5A059] bg-white/95 backdrop-blur-md cursor-grab active:cursor-grabbing select-none touch-none pointer-events-auto">
            {{-- Miniatur-Hintergrund --}}
            <template x-if="activeBgImage">
                <img :src="activeBgImage" class="absolute inset-0 w-full h-full object-cover pointer-events-none opacity-85" alt="">
            </template>
            {{-- Miniatur-Produkt-Overlay --}}
            <img x-show="Boolean(activeOverlayImg || config.fallbackImg)"
                 :src="activeOverlayImg || config.fallbackImg"
                 class="absolute inset-0 w-full h-full object-contain pointer-events-none z-[2]"
                 alt="">
            {{-- Miniatur-Arbeitsfläche (Grüne Gravurkontur zur Orientierung) --}}
            <svg x-show="config.area_shape === 'custom' && config.custom_points && config.custom_points.length > 0"
                 class="absolute inset-0 w-full h-full z-[3] pointer-events-none"
                 viewBox="0 0 100 100"
                 preserveAspectRatio="none">
                <polygon :points="getSmoothPolygonSvgPoints(config.custom_points)"
                         fill="rgba(16, 185, 129, 0.08)"
                         stroke="#10b981"
                         stroke-width="1.2" />
            </svg>
            {{-- Verschiebbarer goldener Zoom-Ausschnitt-Rahmen --}}
            <div class="absolute z-[5] border-2 border-[#C5A059] bg-[#C5A059]/15 rounded-[3px] pointer-events-none shadow-[0_0_0_999px_rgba(0,0,0,0.42)]"
                 :style="getMinimapViewportStyle()">
                <div class="absolute inset-0 flex items-center justify-center opacity-75">
                    <div class="w-1.5 h-1.5 rounded-full bg-[#C5A059] shadow-sm"></div>
                </div>
            </div>
        </div>
        @endif

        {{-- 3D Ansicht --}}
        <div wire:ignore
             x-show="config.modelPath"
             x-ref="container3d"
             @contextmenu.prevent
             class="absolute inset-0 w-full h-full cursor-move transition-opacity duration-500 select-none"
             :class="showDrawingBoard ? 'opacity-0 invisible pointer-events-none z-0' : 'opacity-100 visible z-10'"
             :style="showDrawingBoard ? 'visibility: hidden; z-index: 0;' : 'visibility: visible; z-index: 10;'"></div>

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
        <div wire:ignore
             x-ref="drawingBoard"
             x-show="showDrawingBoard || !config.modelPath"
             @contextmenu.prevent
             class="absolute inset-0 w-full h-full z-20 pointer-events-auto"
             :style="isCapturing
                 ? 'isolation: isolate; transform: none; -webkit-transform: none;'
                 : `isolation: isolate; transform-origin: 50% 50%; transform: translate3d(${stagePanX}%, ${stagePanY}%, 0) scale(${stageZoom}); -webkit-transform: translate3d(${stagePanX}%, ${stagePanY}%, 0) scale(${stageZoom});`">

            <template x-if="activeBgImage && stageZoom > 1.001 && !isCapturing">
                <img :src="activeBgImage"
                     data-snapshot-ignore="true"
                     class="absolute inset-0 w-full h-full object-cover pointer-events-none z-[1]"
                     alt="">
            </template>

            <img x-show="Boolean(activeOverlayImg || config.fallbackImg)"
                 :src="activeOverlayImg || config.fallbackImg"
                 x-on:error="if (config.fallbackImg && $el.src !== config.fallbackImg) { $el.src = config.fallbackImg; }"
                 loading="eager"
                 decoding="sync"
                 :style="hasTransparentOverlay
                     ? 'transform: translate3d(0,0,0); -webkit-transform: translate3d(0,0,0); backface-visibility: hidden; -webkit-backface-visibility: hidden; will-change: transform; filter: drop-shadow(0 12px 24px rgba(0,0,0,0.45));'
                     : 'transform: translate3d(0,0,0); -webkit-transform: translate3d(0,0,0); backface-visibility: hidden; -webkit-backface-visibility: hidden; will-change: transform;'"
                 class="absolute inset-0 w-full h-full z-[15] object-contain pointer-events-none transition-opacity duration-500 {{ $isDark ? 'opacity-95 group-hover:opacity-100' : 'opacity-100' }}">

            <div x-show="config.area_shape !== 'custom'" data-snapshot-ignore="true" class="absolute z-[22] border-2 border-emerald-500 bg-emerald-500/5 pointer-events-none transition-all duration-500"
                 :style="`
                    top: ${config.area_top || 0}%;
                    left: ${config.area_left || 0}%;
                    width: ${config.area_width || 100}%;
                    height: ${config.area_height || 100}%;
                    border-radius: ${config.area_shape === 'circle' ? '50%' : '0'};
                    box-shadow: 0 0 0 9999px {{ $isDark ? 'rgba(0,0,0,0.4)' : 'rgba(255,255,255,0.4)' }};
                 `">
            </div>

            <svg x-show="config.area_shape === 'custom' && config.custom_points && config.custom_points.length > 0"
                 data-snapshot-ignore="true"
                 class="absolute inset-0 w-full h-full z-[22] pointer-events-none"
                 viewBox="0 0 100 100"
                 preserveAspectRatio="none">
                <polygon :points="getSmoothPolygonSvgPoints(config.custom_points)"
                         fill="rgba(16, 185, 129, 0.03)"
                         stroke="#10b981"
                         stroke-width="0.32"
                         stroke-linejoin="round"
                         stroke-linecap="round" />
            </svg>

            {{-- Hilfslinien fürs Snapping --}}
            <div x-show="showGuideX" data-snapshot-ignore="true" class="absolute top-0 bottom-0 border-l border-primary/60 border-dashed pointer-events-none transition-opacity" :style="'left: ' + areaCenterX + '%; z-index: 23;'"></div>
            <div x-show="showGuideY" data-snapshot-ignore="true" class="absolute left-0 right-0 border-t border-primary/60 border-dashed pointer-events-none transition-opacity" :style="'top: ' + areaCenterY + '%; z-index: 23;'"></div>

            {{-- CLIP-CONTAINER FÜR ELEMENTE --}}
            <div class="absolute z-[25] overflow-hidden pointer-events-none"
                 :style="`
                        top: ${config.area_shape === 'custom' ? 0 : (config.area_top ?? 0)}%;
                        left: ${config.area_shape === 'custom' ? 0 : (config.area_left ?? 0)}%;
                        width: ${config.area_shape === 'custom' ? 100 : (config.area_width || 100)}%;
                        height: ${config.area_shape === 'custom' ? 100 : (config.area_height || 100)}%;
                        border-radius: ${config.area_shape === 'circle' ? '50%' : '0'};
                        clip-path: ${
                            config.area_shape === 'custom' && config.custom_points && config.custom_points.length > 0
                            ? getSmoothPolygonClipPath(config.custom_points)
                            : 'none'
                        };
                     `">

                <div x-show="currentTexts.length === 0 && currentLogos.length === 0 && context !== 'preview' && !config.isBeginnerMode"
                     data-snapshot-ignore="true"
                     class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 pointer-events-auto">
                    <button type="button"
                            @mousedown.stop
                            @touchstart.stop
                            @click.stop="addFallbackText()"
                            class="px-5 py-2.5 backdrop-blur-md border rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-1.5 cursor-pointer {{ $isDark ? 'bg-gray-900/88 text-primary border-[#C5A059]/45 hover:bg-primary hover:text-gray-950' : 'bg-white/90 text-[#9A7B3E] border-[#C5A059]/55 hover:bg-[#C5A059] hover:text-white hover:border-[#C5A059] shadow-[0_8px_24px_rgba(197,160,89,0.22)]' }}">
                        <span class="font-serif text-xs leading-none">Aa+</span>
                        <span>+ Text hinzufügen</span>
                    </button>

                    @if($configSettings['allow_logo'] ?? true)
                        <button type="button"
                                @mousedown.stop
                                @touchstart.stop
                                @click.stop="activeTab = 'files'"
                                class="px-4 py-2 backdrop-blur-md border rounded-xl text-[10px] font-bold uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 cursor-pointer {{ $isDark ? 'bg-gray-900/80 text-gray-200 border-gray-700 hover:border-[#C5A059] hover:text-[#C5A059]' : 'bg-white/85 text-gray-700 border-gray-200/90 hover:border-[#C5A059] hover:text-[#9A7B3E] shadow-sm' }}">
                            <x-heroicon-o-photo class="w-3.5 h-3.5 text-[#C5A059]" />
                            <span>+ Bild / Motiv laden</span>
                        </button>
                    @endif

                    <div class="flex items-center justify-center gap-1.5">
                        <button type="button"
                                @mousedown.stop
                                @touchstart.stop
                                @click.stop="activeTab = 'symbols'"
                                class="px-3 py-1.5 backdrop-blur-md border rounded-full text-[9px] font-bold uppercase tracking-wider transition-all flex items-center gap-1 cursor-pointer {{ $isDark ? 'bg-gray-900/75 text-gray-300 border-gray-700 hover:border-[#C5A059] hover:text-[#C5A059]' : 'bg-white/80 text-gray-600 border-gray-200/80 hover:border-[#C5A059] hover:text-[#9A7B3E] shadow-2xs' }}">
                            <x-heroicon-o-sparkles class="w-3 h-3 text-[#C5A059]" />
                            <span>Symbole</span>
                        </button>
                        @if(!empty($quotes))
                            <button type="button"
                                    @mousedown.stop
                                    @touchstart.stop
                                    @click.stop="activeTab = 'quotes'"
                                    class="px-3 py-1.5 backdrop-blur-md border rounded-full text-[9px] font-bold uppercase tracking-wider transition-all flex items-center gap-1 cursor-pointer {{ $isDark ? 'bg-gray-900/75 text-gray-300 border-gray-700 hover:border-[#C5A059] hover:text-[#C5A059]' : 'bg-white/80 text-gray-600 border-gray-200/80 hover:border-[#C5A059] hover:text-[#9A7B3E] shadow-2xs' }}">
                                <x-heroicon-o-chat-bubble-bottom-center-text class="w-3 h-3 text-[#C5A059]" />
                                <span>Sprüche</span>
                            </button>
                        @endif
                    </div>
                </div>

                <template x-for="(logoItem,index) in currentLogos" :key="'content-logo-' + activeSide + '-' + (logoItem.id || index)">
                    <div x-show="currentLogos[index] !== undefined"
                         :class="(context === 'preview' || config.isBeginnerMode) ? 'absolute pointer-events-none select-none' : 'absolute touch-none pointer-events-auto group/item'"
                         :style="currentLogos[index] ? `
                            left: ${config.area_shape === 'custom' ? (currentLogos[index].x ?? areaCenterX) : ((currentLogos[index].x ?? areaCenterX)-(config.area_left ?? 0))/(config.area_width || 100)* 100}%;
                            top: ${config.area_shape === 'custom' ? (currentLogos[index].y ?? areaCenterY) : ((currentLogos[index].y ?? areaCenterY)-(config.area_top ?? 0))/(config.area_height || 100)* 100}%;
                            width: ${((currentLogos[index].size || 100) * scaleFactor)}px;
                            transform: translate(-50%,-50%) rotate(${currentLogos[index].rotation || 0}deg);
                            z-index: ${getElementZIndex('logo', index)}
                         ` : 'display:none;'"
                         @mousedown.stop="if (!config.isBeginnerMode) startAction($event,'logo',index,'drag')"
                         @touchstart.stop="if (!config.isBeginnerMode) startAction($event,'logo',index,'drag')">

                        <div class="logo-crop-wrapper relative transition-colors duration-150 rounded-lg ring-2"
                             :class="(context === 'preview' || config.isBeginnerMode)
                                 ? 'ring-transparent'
                                 : (selectedType==='logo' && selectedIndex===index
                                     ? (isCropping
                                         ? 'ring-primary/40 bg-black/20 backdrop-blur-[1px]'
                                         : (isDarkSurface ? 'ring-primary bg-white/10 backdrop-blur-[2px] shadow-[0_0_15px_rgba(197,160,89,0.25)]' : 'ring-primary {{ $isDark ? 'bg-white/10' : 'bg-white/40' }} backdrop-blur-sm shadow-lg'))
                                     : (isDarkSurface ? 'ring-transparent hover:ring-primary/60 hover:bg-white/5' : 'ring-transparent hover:ring-gray-500 {{ $isDark ? 'hover:bg-white/5' : 'hover:bg-white/10' }}'))">
                            <template x-if="currentLogos[index] !== undefined">
                                <div class="relative w-full h-auto">
                                    {{-- Abgedunkelte Hintergrund-Vorschau des abgeschnittenen Bereichs während des Zuschneidens --}}
                                    <template x-if="isCropping && selectedType==='logo' && selectedIndex===index">
                                        <img :src="currentLogos[index].url"
                                             data-snapshot-ignore="true"
                                             decoding="sync"
                                             class="w-full h-auto pointer-events-none rounded-lg select-none"
                                             :style="isDarkSurface
                                                 ? 'filter: brightness(0) invert(1) opacity(0.22);'
                                                 : 'opacity: 0.22;'">
                                    </template>

                                    {{-- Hauptbild (im Zuschnitt-Modus live per clip-path beschnitten) --}}
                                    <img :src="currentLogos[index].url"
                                         decoding="sync"
                                         :class="(isCropping && selectedType==='logo' && selectedIndex===index) ? 'absolute inset-0 w-full h-full object-contain pointer-events-none rounded-lg' : 'w-full h-auto pointer-events-none rounded-lg'"
                                         :style="(isDarkSurface
                                             ? 'filter: brightness(0) invert(1) opacity(0.93) drop-shadow(0px 1px 3px rgba(0,0,0,0.85)) drop-shadow(0px 0px 5px rgba(255,255,255,0.2));'
                                             : 'opacity: 0.92; filter: drop-shadow(0px 0px 3px rgba(255,255,255,0.75)) drop-shadow(0px 1px 2px rgba(0,0,0,0.25));')
                                             + ((isCropping && selectedType==='logo' && selectedIndex===index) ? (' clip-path: ' + getLogoClipPath() + ';') : '')">
                                </div>
                            </template>

                            {{-- STANDARD-ECKEN (Verschieben, Drehen, Löschen, Größe) WENN NICHT IM ZUSCHNITT-MODUS --}}
                            <template x-if="selectedType==='logo' && selectedIndex===index && !isCropping && context!=='preview' && !config.isBeginnerMode && currentLogos[index] !== undefined">
                                <div data-snapshot-ignore="true">
                                    <div @mousedown.stop="startAction($event,'logo',index,'drag')" @touchstart.stop.prevent="startAction($event,'logo',index,'drag')" class="active-control-corner absolute -top-4 -left-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-move transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-400 hover:text-primary' : 'bg-white border-gray-100 text-gray-500 hover:text-primary' }}" :style="`z-index:200; transform: scale(${(1 / (stageZoom || 1)).toFixed(3)});`"><x-heroicon-m-arrows-pointing-out class="w-4 h-4 rotate-45" /></div>
                                    <div @mousedown.stop="startAction($event,'logo',index,'rotate')" @touchstart.stop.prevent="startAction($event,'logo',index,'rotate')" class="active-control-corner absolute -top-4 -right-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-alias transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-primary hover:text-primary-dark' : 'bg-white border-gray-100 text-primary hover:text-primary-dark' }}" :style="`z-index:200; transform: scale(${(1 / (stageZoom || 1)).toFixed(3)});`"><x-heroicon-m-arrow-path class="w-4 h-4" /></div>
                                    <div @mousedown.stop @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()" class="active-control-corner absolute -bottom-4 -left-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-pointer transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-red-400 hover:text-red-500' : 'bg-white border-gray-100 text-red-500 hover:text-red-700' }}" :style="`z-index:200; transform: scale(${(1 / (stageZoom || 1)).toFixed(3)});`"><x-heroicon-m-trash class="w-4 h-4" /></div>
                                    <div @mousedown.stop="startAction($event,'logo',index,'resize')" @touchstart.stop.prevent="startAction($event,'logo',index,'resize')" class="active-control-corner absolute -bottom-4 -right-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-se-resize transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-primary hover:text-primary-dark' : 'bg-white border-gray-100 text-primary hover:text-primary-dark' }}" :style="`z-index:200; transform: scale(${(1 / (stageZoom || 1)).toFixed(3)});`"><svg class="w-4 h-4 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                </div>
                            </template>

                            {{-- INTERAKTIVER ZUSCHNITT-RAHMEN MIT 4 KANTEN-BALKEN & 4 ECKEN --}}
                            <template x-if="selectedType==='logo' && selectedIndex===index && isCropping && context!=='preview' && currentLogos[index] !== undefined">
                                <div data-snapshot-ignore="true"
                                     class="crop-control-handle absolute border-2 border-primary pointer-events-none"
                                     :style="`
                                         top: ${cropBox.top}%;
                                         right: ${cropBox.right}%;
                                         bottom: ${cropBox.bottom}%;
                                         left: ${cropBox.left}%;
                                         border-radius: ${cropBox.shape === 'circle' ? '9999px' : '4px'};
                                         box-shadow: 0 0 0 1px rgba(0,0,0,0.45);
                                         z-index: 210;
                                     `">
                                    {{-- Obere Kante (N) --}}
                                    <div @mousedown.stop="startCropDrag($event, 'n')" @touchstart.stop.prevent="startCropDrag($event, 'n')"
                                         class="crop-control-handle pointer-events-auto absolute -top-2.5 left-1/2 -translate-x-1/2 w-10 h-4 flex items-center justify-center cursor-ns-resize">
                                        <span class="w-8 h-1.5 rounded-full bg-primary border border-gray-900 shadow-sm"></span>
                                    </div>
                                    {{-- Untere Kante (S) --}}
                                    <div @mousedown.stop="startCropDrag($event, 's')" @touchstart.stop.prevent="startCropDrag($event, 's')"
                                         class="crop-control-handle pointer-events-auto absolute -bottom-2.5 left-1/2 -translate-x-1/2 w-10 h-4 flex items-center justify-center cursor-ns-resize">
                                        <span class="w-8 h-1.5 rounded-full bg-primary border border-gray-900 shadow-sm"></span>
                                    </div>
                                    {{-- Linke Kante (W) --}}
                                    <div @mousedown.stop="startCropDrag($event, 'w')" @touchstart.stop.prevent="startCropDrag($event, 'w')"
                                         class="crop-control-handle pointer-events-auto absolute top-1/2 -left-2.5 -translate-y-1/2 w-4 h-10 flex items-center justify-center cursor-ew-resize">
                                        <span class="w-1.5 h-8 rounded-full bg-primary border border-gray-900 shadow-sm"></span>
                                    </div>
                                    {{-- Rechte Kante (E) --}}
                                    <div @mousedown.stop="startCropDrag($event, 'e')" @touchstart.stop.prevent="startCropDrag($event, 'e')"
                                         class="crop-control-handle pointer-events-auto absolute top-1/2 -right-2.5 -translate-y-1/2 w-4 h-10 flex items-center justify-center cursor-ew-resize">
                                        <span class="w-1.5 h-8 rounded-full bg-primary border border-gray-900 shadow-sm"></span>
                                    </div>

                                    {{-- 4 Ecken (NW, NE, SW, SE) --}}
                                    <div @mousedown.stop="startCropDrag($event, 'nw')" @touchstart.stop.prevent="startCropDrag($event, 'nw')"
                                         class="crop-control-handle pointer-events-auto absolute -top-2 -left-2 w-4 h-4 rounded-full bg-white border-2 border-primary shadow-md cursor-nwse-resize"></div>
                                    <div @mousedown.stop="startCropDrag($event, 'ne')" @touchstart.stop.prevent="startCropDrag($event, 'ne')"
                                         class="crop-control-handle pointer-events-auto absolute -top-2 -right-2 w-4 h-4 rounded-full bg-white border-2 border-primary shadow-md cursor-nesw-resize"></div>
                                    <div @mousedown.stop="startCropDrag($event, 'sw')" @touchstart.stop.prevent="startCropDrag($event, 'sw')"
                                         class="crop-control-handle pointer-events-auto absolute -bottom-2 -left-2 w-4 h-4 rounded-full bg-white border-2 border-primary shadow-md cursor-nesw-resize"></div>
                                    <div @mousedown.stop="startCropDrag($event, 'se')" @touchstart.stop.prevent="startCropDrag($event, 'se')"
                                         class="crop-control-handle pointer-events-auto absolute -bottom-2 -right-2 w-4 h-4 rounded-full bg-white border-2 border-primary shadow-md cursor-nwse-resize"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-for="(textItem,index) in currentTexts" :key="'content-text-' + activeSide + '-' + (textItem.id || index)">
                    <div x-show="currentTexts[index] !== undefined && ((context !== 'preview' && !config.isBeginnerMode && !isCapturing) || (currentTexts[index]?.text && String(currentTexts[index].text).trim() !== ''))"
                         :class="(context === 'preview' || config.isBeginnerMode) ? 'absolute pointer-events-none select-none' : 'absolute touch-none pointer-events-auto group/item'"
                         :style="currentTexts[index] ? `
                            left: ${config.area_shape === 'custom' ? (currentTexts[index].x ?? areaCenterX) : ((currentTexts[index].x ?? areaCenterX)-(config.area_left ?? 0))/(config.area_width || 100)* 100}%;
                            top: ${config.area_shape === 'custom' ? (currentTexts[index].y ?? areaCenterY) : ((currentTexts[index].y ?? areaCenterY)-(config.area_top ?? 0))/(config.area_height || 100)* 100}%;
                            transform-origin: ${currentTexts[index].align==='left'?'0% 50%':(currentTexts[index].align==='right'?'100% 50%':'50% 50%')};
                            transform: translate(${currentTexts[index].align==='left'?'0%':(currentTexts[index].align==='right'?'-100%':'-50%')},-50%) rotate(${currentTexts[index].rotation || 0}deg);
                            z-index: ${getElementZIndex('text', index)}
                         ` : 'display:none;'"
                         @mousedown.stop="if (!config.isBeginnerMode) startAction($event,'text',index,'drag')"
                         @touchstart.stop="if (!config.isBeginnerMode) startAction($event,'text',index,'drag')">

                        <div class="relative transition-colors duration-150 rounded-lg p-1.5 border-[1.5px]"
                             @click.stop="if (!config.isBeginnerMode && !isDragging && !isResizing && !isRotating && !_justTransformed && currentTexts[index] && currentTexts[index].id) { focusedTextId = currentTexts[index].id; selectItem('text', index); const ta = $el.querySelector('textarea[data-id]'); if (ta && document.activeElement !== ta) ta.focus(); }"
                             :class="(context === 'preview' || config.isBeginnerMode)
                                 ? 'border-transparent cursor-default'
                                 : (selectedType==='text' && selectedIndex===index
                                     ? (focusedTextId === currentTexts[index]?.id
                                         ? (isDarkSurface
                                             ? 'border-primary border-solid bg-gray-950/35 backdrop-blur-[3px] config-typing-ready-box-dark cursor-move'
                                             : 'border-primary border-solid {{ $isDark ? 'bg-gray-900/35' : 'bg-white/65' }} backdrop-blur-sm config-typing-ready-box-light cursor-move')
                                         : (isDarkSurface
                                             ? 'border-primary border-dashed bg-white/10 backdrop-blur-[2px] shadow-[0_0_15px_rgba(197,160,89,0.25)] cursor-move'
                                             : 'border-primary border-dashed {{ $isDark ? 'bg-white/10' : 'bg-white/40' }} backdrop-blur-sm shadow-lg cursor-move'))
                                     : (isDarkSurface ? 'border-transparent hover:border-primary/60 hover:bg-white/5 cursor-move' : 'border-transparent hover:border-gray-500 {{ $isDark ? 'hover:bg-white/5' : 'hover:bg-white/10' }} cursor-move'))">

                            {{-- ANIMIERTE SCHREIBBEREITSCHAFTS-ANZEIGE BEI LEEREM, FOKUSSIERTEM TEXTFELD --}}
                            <template x-if="currentTexts[index] && focusedTextId === currentTexts[index].id && (!currentTexts[index].text || currentTexts[index].text.length === 0) && context !== 'preview' && !config.isBeginnerMode">
                                <div data-snapshot-ignore="true" class="pointer-events-none select-none">
                                    {{-- Schwebendes Status-Badge oberhalb des Textfelds --}}
                                    <div :style="`transform: translateX(-50%) scale(${(1 / (stageZoom || 1)).toFixed(3)}); transform-origin: bottom center;`"
                                         class="
                                        absolute -top-8 left-1/2 z-[210]
                                        px-2.5 py-0.5 rounded-full whitespace-nowrap
                                        flex items-center gap-1.5
                                        text-[9px] font-black uppercase tracking-wider
                                        backdrop-blur-md border border-[#C5A059]/50
                                        {{ $isDark ? 'bg-gray-900/90 text-primary shadow-[0_4px_14px_rgba(0,0,0,0.35)]' : 'bg-white/90 text-[#9A7B3E] shadow-[0_4px_14px_rgba(197,160,89,0.22)]' }}
                                        config-typing-badge-pop
                                    ">
                                        <span class="relative flex h-2 w-2">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-80"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                        </span>
                                        <span>Schreibbereit</span>
                                    </div>

                                    {{-- Animierter Blink-Cursor + sanfter Fokus-Impuls direkt im leeren Eingabefeld --}}
                                    <div class="absolute inset-1.5 flex items-center"
                                         :class="currentTexts[index].align === 'left' ? 'justify-start pl-1' : (currentTexts[index].align === 'right' ? 'justify-end pr-1' : 'justify-center')">
                                        <div class="flex items-center gap-1">
                                            <span class="inline-block rounded-full config-blinking-caret"
                                                  :style="`
                                                      width: 2.5px;
                                                      height: ${Math.max(18, Math.round((20 * (currentTexts[index].size || 1)) * scaleFactor * 1.05))}px;
                                                      background: ${isDarkSurface ? '#fbbf24' : '#C5A059'};
                                                      box-shadow: 0 0 8px rgba(197, 160, 89, 0.9), 0 0 2px rgba(0, 0, 0, 0.6);
                                                  `"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="currentTexts[index] !== undefined && (context === 'preview' || config.isBeginnerMode)">
                                <div class="bg-transparent font-bold block whitespace-pre m-0 border-0 outline-none shadow-none select-none pointer-events-none"
                                     :class="alignMap[currentTexts[index]?.align || 'center']"
                                     :style="`
                                         padding: 4px 10px;
                                         font-size: ${(20 * (currentTexts[index]?.size || 1)) * scaleFactor}px;
                                         font-family: ${fontMap[currentTexts[index]?.font] || 'Arial'};
                                         line-height: 1.28;
                                         color: ${isDarkSurface ? 'rgba(248, 250, 252, 0.98)' : 'rgba(17, 24, 39, 0.96)'};
                                         -webkit-text-fill-color: ${isDarkSurface ? '#f8fafc' : '#111827'};
                                         filter: ${isDarkSurface
                                             ? 'drop-shadow(0px 1px 2px rgba(0,0,0,0.98)) drop-shadow(0px 0px 4px rgba(0,0,0,0.92)) drop-shadow(0px 0px 8px rgba(0,0,0,0.75))'
                                             : 'drop-shadow(0px 0px 3px rgba(255,255,255,0.95)) drop-shadow(0px 0px 6px rgba(255,255,255,0.85)) drop-shadow(0px 1px 2px rgba(0,0,0,0.35))'};
                                         white-space: pre !important;
                                     `"
                                     x-text="currentTexts[index]?.text || ''"></div>
                            </template>

                            <template x-if="currentTexts[index] !== undefined && context !== 'preview' && !config.isBeginnerMode">
                                <textarea x-model="currentTexts[index].text"
                                          :data-id="currentTexts[index].id"
                                          :rows="(currentTexts[index].text?.match(/\n/g) || []).length + 1"
                                          x-effect="if (textItem && document.activeElement !== $el && $el.value !== (textItem.text || '')) { $el.value = textItem.text || ''; fitTextarea(textItem.id, $el); }"
                                          x-init="
                                                if (currentTexts[index]) fitTextarea(currentTexts[index].id, $el);
                                                $watch('currentTexts[index]?.text', () => { if (currentTexts[index]) fitTextarea(currentTexts[index].id, $el); });
                                                $watch('currentTexts[index]?.size', () => $nextTick(() => { if (currentTexts[index]) fitTextarea(currentTexts[index].id, $el); }));
                                                $watch('currentTexts[index]?.font', () => setTimeout(() => { if (currentTexts[index]) fitTextarea(currentTexts[index].id, $el); }, 50));
                                                $nextTick(() => { if (currentTexts[index]) fitTextarea(currentTexts[index].id, $el); });
                                          "
                                          @focus="if (currentTexts[index]) { focusedTextId = currentTexts[index].id; selectItem('text', index); }"
                                          @click.stop="if (!_justTransformed && currentTexts[index]) { focusedTextId = currentTexts[index].id; selectItem('text', index); }"
                                          @input="handleTextInput(currentTexts[index], $el, index)"
                                          @blur="if (currentTexts[index] && focusedTextId === currentTexts[index].id) { focusedTextId = null; } recordHistory()"
                                          wrap="off"
                                          class="bg-transparent font-bold resize-none overflow-hidden block whitespace-pre m-0 border-0 outline-none shadow-none ring-0 focus:placeholder-transparent select-text"
                                          :class="[alignMap[currentTexts[index]?.align || 'center'], focusedTextId === currentTexts[index]?.id ? 'cursor-text' : 'cursor-move']"
                                          :style="`
                                                width: ${ensureTextDims(currentTexts[index]).width};
                                                height: ${ensureTextDims(currentTexts[index]).height};
                                                padding: 4px 10px;
                                                font-size: ${getEffectiveTextareaFontSizePx(currentTexts[index])}px;
                                                font-family: ${fontMap[currentTexts[index]?.font] || 'Arial'};
                                                line-height: 1.28;
                                                color: ${isDarkSurface ? 'rgba(248, 250, 252, 0.98)' : 'rgba(17, 24, 39, 0.96)'};
                                                -webkit-text-fill-color: ${isDarkSurface ? '#f8fafc' : '#111827'};
                                                caret-color: ${(focusedTextId === currentTexts[index]?.id && (!currentTexts[index]?.text || currentTexts[index]?.text.length === 0)) ? 'transparent' : '#C5A059'};
                                                filter: ${isDarkSurface
                                                    ? 'drop-shadow(0px 1px 2px rgba(0,0,0,0.98)) drop-shadow(0px 0px 4px rgba(0,0,0,0.92)) drop-shadow(0px 0px 8px rgba(0,0,0,0.75))'
                                                    : 'drop-shadow(0px 0px 3px rgba(255,255,255,0.95)) drop-shadow(0px 0px 6px rgba(255,255,255,0.85)) drop-shadow(0px 1px 2px rgba(0,0,0,0.35))'};
                                                white-space: pre !important;
                                            `"
                                          :placeholder="(focusedTextId === currentTexts[index]?.id || context === 'preview' || isCapturing) ? '' : 'Text eingeben...'">
                                </textarea>
                            </template>

                            <template x-if="selectedType==='text' && selectedIndex===index && context!=='preview' && !config.isBeginnerMode && currentTexts[index] !== undefined">
                                <div data-snapshot-ignore="true">
                                    <div @mousedown.stop="startAction($event,'text',index,'drag')" @touchstart.stop.prevent="startAction($event,'text',index,'drag')" class="active-control-corner absolute -top-4 -left-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-move transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-400 hover:text-primary' : 'bg-white border-gray-100 text-gray-500 hover:text-primary' }}" :style="`z-index:200; transform: scale(${(1 / (stageZoom || 1)).toFixed(3)});`"><x-heroicon-m-arrows-pointing-out class="w-4 h-4 rotate-45" /></div>
                                    <div @mousedown.stop="startAction($event,'text',index,'rotate')" @touchstart.stop.prevent="startAction($event,'text',index,'rotate')" class="active-control-corner absolute -top-4 -right-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-alias transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-primary hover:text-primary-dark' : 'bg-white border-gray-100 text-primary hover:text-primary-dark' }}" :style="`z-index:200; transform: scale(${(1 / (stageZoom || 1)).toFixed(3)});`"><x-heroicon-m-arrow-path class="w-4 h-4" /></div>
                                    <div @mousedown.stop @click.stop="deleteSelectedItem()" @touchstart.stop.prevent="deleteSelectedItem()" class="active-control-corner absolute -bottom-4 -left-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-pointer transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-red-400 hover:text-red-500' : 'bg-white border-gray-100 text-red-500 hover:text-red-700' }}" :style="`z-index:200; transform: scale(${(1 / (stageZoom || 1)).toFixed(3)});`"><x-heroicon-m-trash class="w-4 h-4" /></div>
                                    <div @mousedown.stop="startAction($event,'text',index,'resize')" @touchstart.stop.prevent="startAction($event,'text',index,'resize')" class="active-control-corner absolute -bottom-4 -right-4 w-8 h-8 rounded-full shadow-md border flex items-center justify-center cursor-se-resize transition-colors {{ $isDark ? 'bg-gray-800 border-gray-700 text-primary hover:text-primary-dark' : 'bg-white border-gray-100 text-primary hover:text-primary-dark' }}" :style="`z-index:200; transform: scale(${(1 / (stageZoom || 1)).toFixed(3)});`"><svg class="w-4 h-4 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>

    {{-- UNTEN AUSSEN STICKY DIREKT AN DER VORSCHAU (VOLLE BREITE): VEREINTE AKTIONS- & WERKZEUGLEISTE --}}
    <div x-show="showDrawingBoard && context !== 'preview' && !config.isBeginnerMode && (selectedIndex !== null || isCropping)"
         x-cloak
         data-snapshot-ignore="true"
         :class="isMaximized ? 'w-[min(90vw,min(58vh,600px))]' : 'w-full max-w-[455px] md:max-w-[600px]'"
         class="rounded-b-2xl border-x border-b px-2 sm:px-3 py-2 flex flex-wrap items-center justify-center sm:justify-around gap-1 sm:gap-1.5 relative z-50 pointer-events-auto schwebender-werkzeugkasten shrink-0 {{ $isDark ? 'bg-gray-900/95 border-gray-800 shadow-lg' : 'bg-white/95 border-gray-200/90 shadow-[0_10px_28px_rgba(0,0,0,0.06)]' }}">

        {{-- MODUS A: ALLE WERKZEUGE LOGISCH IN 2 ÜBERSICHTLICHEN REIHEN (Reihe 1: Hauptwerkzeuge | Reihe 2 ab Kopie-Icon: Kopie, Ausrichten, Löschen) --}}
        <template x-if="!isCropping && selectedIndex !== null">
            <div class="flex flex-col gap-1.5 w-full">

                {{-- REIHE 1: VERSCHIEBEN | DREHEN | GRÖSSE | ZUSCHNEIDEN (Bild) bzw. SCHRIFT & FORMAT (Text) --}}
                <div class="flex items-center justify-around gap-1.5 sm:gap-2 w-full pb-1.5 border-b {{ $isDark ? 'border-gray-800/80' : 'border-gray-100' }}">

                    {{-- 1. VERSCHIEBEN --}}
                    <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'drag')"
                         @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'drag')"
                         class="flex-1 flex flex-col items-center justify-center cursor-move transition-colors px-2 py-1.5 rounded-xl group select-none {{ $isDark ? 'text-gray-300 hover:text-white hover:bg-gray-800/80' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}"
                         title="Gedrückt halten zum Verschieben">
                        <x-heroicon-m-arrows-pointing-out class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                        <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Verschieben</span>
                    </div>

                    {{-- 2. DREHEN --}}
                    <div @mousedown.stop="startAction($event, selectedType, selectedIndex, 'rotate')"
                         @touchstart.stop.prevent="startAction($event, selectedType, selectedIndex, 'rotate')"
                         class="flex-1 flex flex-col items-center justify-center cursor-alias transition-colors px-2 py-1.5 rounded-xl group select-none {{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}"
                         title="Gedrückt halten zum Drehen">
                        <x-heroicon-m-arrow-path class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                        <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Drehen</span>
                    </div>

                    {{-- 3. GRÖSSE (Klick öffnet Schieberegler, Ziehen skaliert direkt) --}}
                    <div class="relative flex-1 flex justify-center">
                        <button type="button"
                                @click.stop="if (!hasDraggedDistance) { showSizeMenu = !showSizeMenu; showFontMenu = false; showAlignMenu = false; showPosMenu = false; }"
                                @mousedown.stop="startAction($event, selectedType, selectedIndex, 'resize')"
                                @touchstart.stop="startAction($event, selectedType, selectedIndex, 'resize')"
                                :class="showSizeMenu ? '{{ $isDark ? 'bg-gray-800 text-primary' : 'bg-[#C5A059]/15 text-[#9A7B3E]' }}' : '{{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}'"
                                class="w-full flex flex-col items-center justify-center cursor-pointer transition-colors px-2 py-1.5 rounded-xl group select-none"
                                title="Größe ändern (Klicken für Regler oder Ziehen)">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 rotate-90 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 20L20 4M20 4H14M20 4V10M4 20H10M4 20V14" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Größe</span>
                        </button>
                        <div x-show="showSizeMenu"
                             @click.outside="showSizeMenu = false"
                             x-cloak
                             class="absolute bottom-[calc(100%+10px)] left-1/2 -translate-x-1/2 w-56 rounded-2xl shadow-2xl border p-4 z-[100] {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200' }}">
                            <div class="flex justify-between text-[10px] font-black tracking-widest text-gray-400 uppercase mb-2.5"><span>Klein</span><span>Groß</span></div>
                            <template x-if="selectedType === 'text' && currentTexts[selectedIndex] !== undefined">
                                <input type="range" min="0.1" max="5" step="0.05" x-model="currentTexts[selectedIndex].size" @change="recordHistory()" class="w-full accent-primary h-2 rounded-lg appearance-none cursor-pointer {{ $isDark ? 'bg-gray-800' : 'bg-gray-100' }}">
                            </template>
                            <template x-if="selectedType === 'logo' && currentLogos[selectedIndex] !== undefined">
                                <input type="range" min="10" max="500" step="1" x-model="currentLogos[selectedIndex].size" @change="recordHistory()" class="w-full accent-primary h-2 rounded-lg appearance-none cursor-pointer {{ $isDark ? 'bg-gray-800' : 'bg-gray-100' }}">
                            </template>
                        </div>
                    </div>

                    {{-- 4. ZUSCHNEIDEN (Nur bei ausgewähltem Bild/Motiv) --}}
                    <template x-if="selectedType === 'logo' && currentLogos[selectedIndex] !== undefined">
                        <button type="button"
                                @mousedown.stop @click.stop="startCropMode()"
                                class="flex-1 flex flex-col items-center justify-center cursor-pointer transition-colors px-2 py-1.5 rounded-xl group select-none {{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}"
                                title="Bild zuschneiden">
                            <x-heroicon-m-scissors class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                            <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Zuschneiden</span>
                        </button>
                    </template>

                    {{-- 5. TEXT-WERKZEUGE: SCHRIFT & FORMAT (Nur bei ausgewähltem Text) --}}
                    <template x-if="selectedType === 'text' && currentTexts[selectedIndex] !== undefined">
                        <div class="contents">
                            <div class="w-px h-6 mx-0.5 {{ $isDark ? 'bg-gray-800' : 'bg-gray-200' }}"></div>

                            <div class="relative flex-1 flex justify-center">
                                <button type="button"
                                        @click.stop="showFontMenu = !showFontMenu; showSizeMenu = false; showAlignMenu = false; showPosMenu = false"
                                        :class="showFontMenu ? '{{ $isDark ? 'bg-gray-800 text-primary' : 'bg-[#C5A059]/15 text-[#9A7B3E]' }}' : '{{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}'"
                                        class="w-full flex flex-col items-center justify-center px-2 py-1 rounded-xl transition-colors cursor-pointer select-none"
                                        title="Schriftart wählen">
                                    <span class="font-serif font-black text-sm leading-none h-4 flex items-center">Aa</span>
                                    <span class="text-[9px] font-bold text-[#C5A059] mt-0.5 leading-tight max-w-[80px] truncate" x-text="currentTexts[selectedIndex]?.font || lastSelectedFont || 'Arial'"></span>
                                </button>
                                <div x-show="showFontMenu"
                                     @click.outside="showFontMenu = false"
                                     x-cloak
                                     class="absolute bottom-[calc(100%+10px)] right-0 sm:left-1/2 sm:-translate-x-1/2 w-56 rounded-2xl shadow-2xl border overflow-hidden z-[100] flex flex-col max-h-64 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200' }}">
                                    <div class="overflow-y-auto p-2 custom-scrollbar space-y-1">
                                        <template x-for="(fontName, fontKey) in fontMap">
                                            <button type="button"
                                                    @click.stop="setTextFont(fontKey)"
                                                    class="w-full text-left px-4 py-2.5 text-sm rounded-xl truncate transition-colors cursor-pointer {{ $isDark ? 'hover:bg-gray-800' : 'hover:bg-gray-50' }}"
                                                    :style="{ fontFamily: fontName }"
                                                    :class="currentTexts[selectedIndex].font === fontKey ? 'text-primary font-bold {{ $isDark ? 'bg-primary/10' : 'bg-primary/5' }}' : '{{ $isDark ? 'text-gray-300' : 'text-gray-700' }}'">
                                                <span x-text="fontKey"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="relative flex-1 flex justify-center">
                                <button type="button"
                                        @click.stop="showAlignMenu = !showAlignMenu; showFontMenu = false; showSizeMenu = false; showPosMenu = false"
                                        :class="showAlignMenu ? '{{ $isDark ? 'bg-gray-800 text-primary' : 'bg-[#C5A059]/15 text-[#9A7B3E]' }}' : '{{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}'"
                                        class="w-full flex flex-col items-center justify-center px-2 py-1.5 rounded-xl transition-colors cursor-pointer select-none"
                                        title="Textausrichtung">
                                    <div class="w-4 h-4 sm:w-5 sm:h-5 flex items-center justify-center">
                                        <template x-if="currentTexts[selectedIndex].align === 'left'"><svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></template>
                                        <template x-if="currentTexts[selectedIndex].align === 'center' || !currentTexts[selectedIndex].align"><svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></template>
                                        <template x-if="currentTexts[selectedIndex].align === 'right'"><svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></template>
                                    </div>
                                    <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5">Format</span>
                                </button>
                                <div x-show="showAlignMenu"
                                     @click.outside="showAlignMenu = false"
                                     x-cloak
                                     class="absolute bottom-[calc(100%+10px)] right-0 rounded-2xl shadow-2xl border p-1.5 z-[100] flex gap-1 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200' }}">
                                    <button type="button" @click.stop="setTextAlign('left')" class="p-2.5 rounded-xl hover:text-primary transition-colors cursor-pointer {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M3.75 12h10.5m-10.5 5.25h16.5" /></svg></button>
                                    <button type="button" @click.stop="setTextAlign('center')" class="p-2.5 rounded-xl hover:text-primary transition-colors cursor-pointer {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M7.5 12h9m-9 5.25h9" /></svg></button>
                                    <button type="button" @click.stop="setTextAlign('right')" class="p-2.5 rounded-xl hover:text-primary transition-colors cursor-pointer {{ $isDark ? 'hover:bg-gray-800 text-gray-400' : 'hover:bg-gray-50 text-gray-500' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 6.75h16.5M10.5 12h9.75m-16.5 5.25h16.5" /></svg></button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- REIHE 2 (AB KOPIE-ICON): KOPIE | HORIZ. | VERTIK. | MITTE | LÖSCHEN --}}
                <div class="flex items-center justify-around gap-1.5 sm:gap-2 w-full">
                    <button type="button"
                            @click.stop="duplicateElement"
                            class="flex-1 flex flex-col items-center justify-center px-2 py-1.5 rounded-xl transition-colors cursor-pointer select-none {{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}"
                            title="Element duplizieren">
                        <x-heroicon-m-square-2-stack class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                        <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Kopie</span>
                    </button>

                    <button type="button"
                            @click.stop="centerHorizontal"
                            class="flex-1 flex flex-col items-center justify-center px-2 py-1.5 rounded-xl transition-colors cursor-pointer select-none {{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}"
                            title="Horizontal zentrieren">
                        <x-heroicon-m-pause class="w-4 h-4 sm:w-5 sm:h-5 rotate-90 pointer-events-none" />
                        <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Horiz.</span>
                    </button>

                    <button type="button"
                            @click.stop="centerVertical"
                            class="flex-1 flex flex-col items-center justify-center px-2 py-1.5 rounded-xl transition-colors cursor-pointer select-none {{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-primary/10' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/12' }}"
                            title="Vertikal zentrieren">
                        <x-heroicon-m-pause class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                        <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Vertik.</span>
                    </button>

                    <button type="button"
                            @click.stop="centerBoth"
                            class="flex-1 flex flex-col items-center justify-center px-2 py-1.5 bg-primary/10 hover:bg-primary/20 text-[#9A7B3E] dark:text-primary rounded-xl transition-colors cursor-pointer select-none"
                            title="Exakt in die Mitte setzen">
                        <x-heroicon-m-viewfinder-circle class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                        <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Mitte</span>
                    </button>

                    <div class="w-px h-6 mx-0.5 {{ $isDark ? 'bg-gray-800' : 'bg-gray-200' }}"></div>

                    <button type="button"
                            @mousedown.stop @click.stop="deleteSelectedItem()"
                            class="flex-1 flex flex-col items-center justify-center cursor-pointer transition-colors px-2 py-1.5 rounded-xl group select-none {{ $isDark ? 'text-gray-300 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-700 hover:text-red-600 hover:bg-red-50/80' }}"
                            title="Ausgewähltes Element löschen">
                        <x-heroicon-m-trash class="w-4 h-4 sm:w-5 sm:h-5 pointer-events-none" />
                        <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Löschen</span>
                    </button>
                </div>
            </div>
        </template>

        {{-- MODUS B: AKTIVER ZUSCHNITT-MODUS (Eckig, Rund, Original, Abbrechen, Übernehmen) --}}
        <template x-if="isCropping">
            <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 w-full">
                <button type="button"
                        @mousedown.stop @click.stop="cropBox.shape = 'rect'"
                        :class="cropBox.shape === 'rect' ? 'text-[#9A7B3E] bg-[#C5A059]/15 border-[#C5A059]/45' : '{{ $isDark ? 'text-gray-300 hover:text-white' : 'text-gray-700 hover:text-[#9A7B3E]' }} border-transparent'"
                        class="flex flex-col items-center justify-center cursor-pointer border transition-colors px-3 py-1.5 rounded-xl select-none">
                    <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="4" y="5" width="16" height="14" rx="2" /></svg>
                    <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Eckig</span>
                </button>

                <button type="button"
                        @mousedown.stop @click.stop="cropBox.shape = 'circle'"
                        :class="cropBox.shape === 'circle' ? 'text-[#9A7B3E] bg-[#C5A059]/15 border-[#C5A059]/45' : '{{ $isDark ? 'text-gray-300 hover:text-white' : 'text-gray-700 hover:text-[#9A7B3E]' }} border-transparent'"
                        class="flex flex-col items-center justify-center cursor-pointer border transition-colors px-3 py-1.5 rounded-xl select-none">
                    <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8" /></svg>
                    <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Rund</span>
                </button>

                <template x-if="currentLogos[selectedIndex] && (currentLogos[selectedIndex].original_url || cropBox.top > 0 || cropBox.right > 0 || cropBox.bottom > 0 || cropBox.left > 0)">
                    <button type="button"
                            @mousedown.stop @click.stop="resetLogoToOriginal()"
                            class="flex flex-col items-center justify-center cursor-pointer transition-colors px-2.5 py-1.5 rounded-xl select-none {{ $isDark ? 'text-gray-300 hover:text-primary hover:bg-gray-800/70' : 'text-gray-700 hover:text-[#9A7B3E] hover:bg-[#C5A059]/15' }}"
                            title="Zuschnitt zurücksetzen">
                        <x-heroicon-m-arrow-uturn-left class="w-4 h-4 pointer-events-none" />
                        <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Original</span>
                    </button>
                </template>

                <div class="w-px h-7 mx-0.5 self-center {{ $isDark ? 'bg-gray-700' : 'bg-[#C5A059]/30' }}"></div>

                <button type="button"
                        @mousedown.stop @click.stop="cancelCropMode()"
                        class="flex flex-col items-center justify-center cursor-pointer transition-colors px-2.5 py-1.5 rounded-xl select-none {{ $isDark ? 'text-gray-300 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-700 hover:text-red-600 hover:bg-red-50/80' }}">
                    <x-heroicon-m-x-mark class="w-4 h-4 pointer-events-none" />
                    <span class="text-[9px] font-bold uppercase tracking-tight mt-0.5 pointer-events-none">Abbrechen</span>
                </button>

                <button type="button"
                        @mousedown.stop @click.stop="applyCrop()"
                        :disabled="isApplyingCrop"
                        class="flex items-center gap-1.5 cursor-pointer bg-[#C5A059] text-white hover:bg-[#b08d48] font-bold text-xs px-3.5 py-2 rounded-xl shadow-md transition-all select-none">
                    <x-heroicon-m-check class="w-4 h-4 stroke-[2.5] pointer-events-none" />
                    <span x-text="isApplyingCrop ? 'Speichert...' : 'Übernehmen'"></span>
                </button>
            </div>
        </template>
    </div>

    {{-- EBENEN- & ELEMENT-AUSWAHL (Damit verdeckte Texte/Bilder jederzeit anklickbar und nach oben hebbar sind) --}}
    <div x-show="showDrawingBoard && context !== 'preview' && !config.isBeginnerMode && (currentTexts.length + currentLogos.length) > 1"
         x-cloak
         class="w-full max-w-[455px] md:max-w-[600px] px-1 sm:px-2 mt-3 relative z-40">
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 rounded-2xl border shadow-sm {{ $isDark ? 'bg-gray-900/90 border-gray-800 text-gray-300' : 'bg-white border-gray-200/80 text-gray-700' }}">
            <div class="flex items-center gap-1.5 flex-wrap flex-1 min-w-0">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 mr-1 flex items-center gap-1 shrink-0">
                    <x-heroicon-m-square-3-stack-3d class="w-3.5 h-3.5 text-primary" />
                    Ebenen:
                </span>
                <template x-for="(tItem, tIdx) in currentTexts" :key="'layer-chip-text-' + activeSide + '-' + (tItem.id || tIdx)">
                    <button type="button"
                            @click.stop="selectItem('text', tIdx); bringToFront()"
                            :class="selectedType === 'text' && selectedIndex === tIdx
                                ? 'bg-primary text-gray-900 font-bold border-primary shadow-sm'
                                : '{{ $isDark ? 'bg-gray-800 text-gray-300 border-gray-700 hover:border-primary/60' : 'bg-gray-50 text-gray-700 border-gray-200 hover:border-primary/60' }}'"
                            class="px-2.5 py-1 rounded-lg border text-[11px] transition-all flex items-center gap-1 max-w-[140px] truncate cursor-pointer"
                            title="Anklicken & auf obere Ebene holen">
                        <span class="font-serif font-black text-[10px] opacity-80">T</span>
                        <span class="truncate" x-text="(tItem.text && tItem.text.trim().length > 0) ? tItem.text.trim() : ('Text ' + (tIdx + 1))"></span>
                    </button>
                </template>
                <template x-for="(lItem, lIdx) in currentLogos" :key="'layer-chip-logo-' + activeSide + '-' + (lItem.id || lIdx)">
                    <button type="button"
                            @click.stop="selectItem('logo', lIdx)"
                            :class="selectedType === 'logo' && selectedIndex === lIdx
                                ? 'bg-primary text-gray-900 font-bold border-primary shadow-sm'
                                : '{{ $isDark ? 'bg-gray-800 text-gray-300 border-gray-700 hover:border-primary/60' : 'bg-gray-50 text-gray-700 border-gray-200 hover:border-primary/60' }}'"
                            class="px-2.5 py-1 rounded-lg border text-[11px] transition-all flex items-center gap-1 cursor-pointer"
                            title="Bild auswählen">
                        <x-heroicon-m-photo class="w-3 h-3 opacity-80 shrink-0" />
                        <span x-text="'Bild/Motiv ' + (lIdx + 1)"></span>
                    </button>
                </template>
            </div>

            <div class="flex items-center gap-1 shrink-0" x-show="selectedIndex !== null">
                <button type="button" @click.stop="bringToFront()" class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border transition-colors flex items-center gap-1 cursor-pointer {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-200 hover:text-primary hover:border-primary' : 'bg-gray-50 border-gray-200 text-gray-700 hover:text-primary hover:border-primary' }}" title="Ausgewähltes Element ganz nach vorne holen">
                    <x-heroicon-m-arrow-up class="w-3 h-3 text-primary" />
                    <span>Nach oben</span>
                </button>
                <button type="button" @click.stop="sendToBack()" class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border transition-colors flex items-center gap-1 cursor-pointer {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-200 hover:text-primary hover:border-primary' : 'bg-gray-50 border-gray-200 text-gray-700 hover:text-primary hover:border-primary' }}" title="Ausgewähltes Element in den Hintergrund setzen">
                    <x-heroicon-m-arrow-down class="w-3 h-3 text-gray-400" />
                    <span>Nach unten</span>
                </button>
            </div>
        </div>
    </div>

    {{-- VORDERSEITE / RÜCKSEITE SWITCH (Direkt unter dem Rahmen) --}}
    <div x-show="config.has_back_side && !config.isBeginnerMode && (context !== 'preview' || (texts_back && texts_back.some(t => t && t.text && String(t.text).trim() !== '')) || (logos_back && logos_back.length > 0))" x-cloak class="mt-5 shadow-sm border rounded-full p-1 flex items-center justify-center relative z-40 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-100' }}">
        <button type="button"
                @click="switchSide('front')"
                :class="activeSide === 'front' ? 'bg-primary text-white shadow-md' : '{{ $isDark ? 'text-gray-500 hover:text-white' : 'text-gray-500 hover:text-gray-800' }}'"
                class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-300 w-36 text-center cursor-pointer">
            Vorderseite
        </button>
        <button type="button"
                @click="switchSide('back')"
                :class="activeSide === 'back' ? 'bg-purple-500 text-white shadow-md' : '{{ $isDark ? 'text-gray-500 hover:text-white' : 'text-gray-500 hover:text-gray-800' }}'"
                class="px-6 py-2.5 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-300 w-36 text-center cursor-pointer">
            Rückseite
        </button>
    </div>

    {{-- DEZENTER URHEBERRECHTS- & KOPIERSCHUTZ-VERMERK (§§ 2, 10, 69a UrhG · § 3 Abs. 10 AGB) --}}
    @if($context !== 'preview')
    <div x-show="!config.isBeginnerMode"
         x-cloak
         data-snapshot-ignore="true"
         class="mt-2 flex items-center justify-center gap-1 text-[9px] font-medium tracking-wider select-none {{ $isDark ? 'text-gray-600' : 'text-gray-400' }}"
         title="Alle 3D-Modelle (.glb), Overlays, Vorlagen und das 2D/3D-Gravur-Studio von Mein-Seelenfunke (Inhaberin: Alina Steinhauer) sind urheber- und wettbewerbsrechtlich geschützt (§§ 2, 10, 69a UrhG, § 4 Nr. 3 UWG, § 3 Abs. 10 AGB).">
        <span>© Mein-Seelenfunke 2D/3D-Gravur-Studio · Urheberrechtlich geschützt</span>
    </div>
    @endif

</div>
