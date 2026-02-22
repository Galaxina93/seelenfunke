<div class="relative w-full max-w-3xl mx-auto aspect-square rounded-2xl overflow-hidden shadow-inner border-4 border-gray-100 flex items-center justify-center mb-8 bg-slate-100"
     wire:ignore
     @mousedown="addPoint($event)"
     @mousemove.window="dragPoint($event)"
     @mouseup.window="stopDragPoint($event)"
     :style="bgPath ? `background-image: url('${bgPath}'); background-size: cover; background-position: center;` : ''">

    <div x-show="modelPath" x-ref="adminContainer3d" class="absolute inset-0 w-full h-full z-10" style="display: none;"></div>

    <div x-ref="adminContainer2d"
         x-show="showDrawingBoard"
         x-transition.opacity.duration.200ms
         class="absolute inset-0 w-full h-full z-20 pointer-events-auto" :class="modelPath ? 'bg-black/10 backdrop-blur-[1px]' : 'bg-white'">

        <template x-if="fallbackImg">
            <img :src="fallbackImg" class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-50">
        </template>

        <div x-show="configSettings.area_shape !== 'custom'" class="absolute border-2 border-green-500 bg-green-500/20 z-10 pointer-events-none transition-all"
             :style="`
                            top:${configSettings.area_top || 0}%;
                            left:${configSettings.area_left || 0}%;
                            width:${configSettings.area_width || 100}%;
                            height:${configSettings.area_height || 100}%;
                            border-radius:${configSettings.area_shape === 'circle' ? '50%' : '0'};
                         `" style="display: none;"></div>

        <svg x-show="configSettings.area_shape === 'custom'"
             class="absolute inset-0 w-full h-full z-10 pointer-events-none"
             viewBox="0 0 100 100"
             preserveAspectRatio="none"
             style="display: none;">
            <polygon
                :points="configSettings.custom_points ? configSettings.custom_points
        .filter(p => p && typeof p === 'object' && isFinite(p.x) && isFinite(p.y))
        .map(p => p.x + ',' + p.y).join(' ') : ''"
                fill="rgba(16, 185, 129, 0.4)"
                stroke="#10b981"
                stroke-width="0.5"
                stroke-linejoin="round" />
        </svg>

        <template x-if="configSettings.area_shape === 'custom'">
            <template x-for="(point, idx) in (configSettings.custom_points || [])" :key="idx">
                <div class="point-handle absolute w-5 h-5 bg-white border-2 border-primary rounded-full z-20 cursor-move -translate-x-1/2 -translate-y-1/2 shadow-lg flex items-center justify-center hover:scale-110"
                     :style="`left: ${point.x}%; top: ${point.y}%;`"
                     @mousedown.stop="startDragPoint(idx, $event)">
                    {{-- WICHTIG: updateTexture() NACH dem Splicen aufrufen --}}
                    <button @click.stop="configSettings.custom_points.splice(idx, 1); $nextTick(() => updateTexture());"
                            class="text-red-500 font-bold" style="font-size: 9px;">×</button>
                </div>
            </template>
        </template>
    </div>
</div>
