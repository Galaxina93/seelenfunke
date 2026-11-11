{{-- ÜBERGEORDNETER WRAPPER --}}
<div class="flex flex-col items-center w-full max-w-3xl mx-auto mb-8 relative"
     wire:ignore
     @mousemove.window="dragPoint($event)"
     @mouseup.window="stopDragPoint($event)">

    {{-- DIE VORSCHAU-KACHEL (3D / 2D) --}}
    <div class="relative w-full aspect-square rounded-[2.5rem] overflow-hidden shadow-inner border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-950 shrink-0"
         @mousedown="addPoint($event)"
         :style="bgPath ? `background-image: url('${bgPath}'); background-size: cover; background-position: center;` : ''">

        {{-- ANIMIERTES THEMEN-HINTERGRUNDVIDEO (2D & 3D im Hintergrund hinter dem Produkt) --}}
        <template x-if="bgVideoUrl && (configSettings.enable_background_video !== false)">
            <video x-ref="adminBgVideo"
                   :key="bgVideoUrl"
                   :src="bgVideoUrl"
                   x-init="$el.muted = true; $el.defaultMuted = true; $el.play().catch(() => {})"
                   autoplay
                   loop
                   muted
                   playsinline
                   class="absolute inset-0 w-full h-full object-cover pointer-events-none z-[5] opacity-95"></video>
        </template>

        {{-- LADEBILDSCHIRM FÜR RAYCASTING --}}
        <div x-show="isRaycasting" x-transition.opacity
             class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-white/80 dark:bg-gray-950/80 backdrop-blur-md" style="display: none;">
            <svg class="animate-spin h-10 w-10 text-blue-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span class="text-[11px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 dark:drop-shadow-[0_0_8px_currentColor] animate-pulse">Berechne Raycasting...</span>
            <span class="text-[9px] text-gray-500 dark:text-gray-400 mt-2 font-medium">3D-Form wird abgetastet</span>
        </div>

        {{-- 3D ANSICHT --}}
        <div x-show="modelPath" x-ref="adminContainer3d" class="absolute inset-0 w-full h-full z-10" style="display: none;"></div>

        {{-- 2D ZEICHENBRETT --}}
        <div x-ref="adminContainer2d"
             x-show="showDrawingBoard"
             x-transition.opacity.duration.300ms
             class="absolute inset-0 w-full h-full z-20 pointer-events-auto" :class="modelPath ? 'bg-black/25 backdrop-blur-[1px]' : 'bg-gray-100/20 dark:bg-gray-900/20'">

            <template x-if="fallbackImg">
                <img :src="fallbackImg" class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-100">
            </template>

            {{-- Pulsierendes Glühen (Eckig/Rund) --}}
            <div x-show="configSettings.area_shape !== 'custom'" class="absolute border border-emerald-400 bg-emerald-500/20 z-10 pointer-events-none transition-all animate-[pulse_3s_ease-in-out_infinite] shadow-sm dark:shadow-[0_0_30px_rgba(16,185,129,0.3)]"
                 :style="`
                                top:${configSettings.area_top || 0}%;
                                left:${configSettings.area_left || 0}%;
                                width:${configSettings.area_width || 100}%;
                                height:${configSettings.area_height || 100}%;
                                border-radius:${configSettings.area_shape === 'circle' ? '50%' : '0'};
                             `" style="display: none;"></div>

            {{-- Pulsierendes Glühen (Polygon) --}}
            <svg x-show="configSettings.area_shape === 'custom'"
                 class="absolute inset-0 w-full h-full z-10 pointer-events-none animate-[pulse_3s_ease-in-out_infinite] drop-shadow-none dark:drop-shadow-[0_0_10px_rgba(16,185,129,0.5)]"
                 viewBox="0 0 100 100"
                 preserveAspectRatio="none"
                 style="display: none;">
                <polygon
                    :points="configSettings.custom_points ? configSettings.custom_points
            .filter(p => p && typeof p === 'object' && isFinite(p.x) && isFinite(p.y))
            .map(p => p.x + ',' + p.y).join(' ') : ''"
                    fill="rgba(16, 185, 129, 0.2)"
                    stroke="#34d399"
                    stroke-width="0.3"
                    stroke-linejoin="round" />
            </svg>

            {{-- Polygon Ankerpunkte --}}
            <template x-if="configSettings.area_shape === 'custom' && showPolygonHandles">
                <template x-for="(point, idx) in (configSettings.custom_points || [])" :key="idx">
                    <div class="point-handle group absolute bg-white dark:bg-gray-900 border-[2px] border-[var(--theme-color)] rounded-full z-20 cursor-move -translate-x-1/2 -translate-y-1/2 shadow-sm dark:shadow-[0_0_10px_rgba(197,160,89,0.5)] flex items-center justify-center hover:scale-125 hover:z-30 transition-all"
                         :class="(configSettings.custom_points && configSettings.custom_points.length > 35) ? 'w-4 h-4' : 'w-7 h-7'"
                         :style="`left: ${point.x}%; top: ${point.y}%;`"
                         @mousedown.stop="startDragPoint(idx, $event)"
                         @touchstart.stop="startDragPoint(idx, $event)">

                        <span x-text="idx + 1"
                              :class="(configSettings.custom_points && configSettings.custom_points.length > 35) ? 'text-[7px]' : 'text-[11px]'"
                              class="absolute font-black text-[var(--theme-color)] pointer-events-none transition-opacity duration-200 group-hover:opacity-0"></span>

                        <button @click.stop="deletePoint(idx)"
                                class="absolute text-red-500 hover:text-red-400 font-bold pointer-events-auto opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                style="font-size: 14px; line-height: 1; padding-bottom: 1px;">&times;</button>
                    </div>
                </template>
            </template>

            {{-- NEULINGS-POSITIONS-VORSCHAU (Symbol oben mittig & Wunschtext unten mittig) --}}
            <template x-if="showBeginnerPositionPreview">
                <div class="absolute inset-0 pointer-events-none z-30">
                    {{-- Symbol-Marker oben mittig --}}
                    <div class="absolute -translate-x-1/2 -translate-y-1/2 flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-emerald-400 bg-emerald-500/20 backdrop-blur-[1px] shadow-md transition-all"
                         :style="`left: ${configSettings.beginner_symbol_x ?? 50}%; top: ${configSettings.beginner_symbol_y ?? 36}%; width: ${Math.max(26, (configSettings.beginner_symbol_size ?? 45) * 0.9)}px; height: ${Math.max(26, (configSettings.beginner_symbol_size ?? 45) * 0.9)}px;`">
                        <span class="text-emerald-300 font-black leading-none text-sm">♥</span>
                        <span class="text-[7px] font-black uppercase tracking-wider text-emerald-200 mt-0.5">Symbol</span>
                    </div>
                    {{-- Wunschtext-Marker unten mittig --}}
                    <div class="absolute -translate-x-1/2 -translate-y-1/2 px-2.5 py-1 rounded-lg border-2 border-dashed border-[#C5A059] bg-gray-950/65 backdrop-blur-[1px] shadow-md whitespace-nowrap transition-all"
                         :style="`left: ${configSettings.beginner_text_x ?? 50}%; top: ${configSettings.beginner_text_y ?? 64}%; font-size: ${Math.max(9, (configSettings.beginner_text_size ?? 0.85) * 14)}px;`">
                        <span class="font-serif font-bold text-[#F3E5AB]">Wunschtext</span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- NEU: VORDERSEITE / RÜCKSEITE SWITCH (Außerhalb & Sticky) --}}
    <div x-show="configSettings.has_back_side" x-transition
         class="sticky bottom-6 mt-6 bg-white/90 dark:bg-gray-900/90 backdrop-blur-xl shadow-md dark:shadow-2xl border border-gray-200 dark:border-gray-700 rounded-full p-1.5 flex items-center z-50">
        <button @click="activeSide = 'front'"
                :class="activeSide === 'front' ? 'bg-[var(--theme-color)] text-gray-900 dark:shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                class="px-6 py-3 sm:px-8 sm:py-3.5 rounded-full text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all duration-300">
            Vorderseite
        </button>
        <button @click="activeSide = 'back'"
                :class="activeSide === 'back' ? 'bg-purple-500 text-white dark:text-gray-900 dark:shadow-[0_0_10px_rgba(168,85,247,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                class="px-6 py-3 sm:px-8 sm:py-3.5 rounded-full text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all duration-300">
            Rückseite
        </button>
    </div>

</div>
