<div class="relative w-full max-w-3xl mx-auto aspect-square rounded-[2.5rem] overflow-hidden shadow-inner border border-gray-800 flex items-center justify-center mb-8 bg-gray-950"
     wire:ignore
     @mousedown="addPoint($event)"
     @mousemove.window="dragPoint($event)"
     @mouseup.window="stopDragPoint($event)"
     :style="bgPath ? `background-image: url('${bgPath}'); background-size: cover; background-position: center;` : ''">

    <div x-show="modelPath" x-ref="adminContainer3d" class="absolute inset-0 w-full h-full z-10" style="display: none;"></div>

    <div x-ref="adminContainer2d"
         x-show="showDrawingBoard"
         x-transition.opacity.duration.300ms
         class="absolute inset-0 w-full h-full z-20 pointer-events-auto" :class="modelPath ? 'bg-gray-950/60 backdrop-blur-[2px]' : 'bg-gray-900/40'">

        <template x-if="fallbackImg">
            <img :src="fallbackImg" class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-40 mix-blend-screen">
        </template>

        <div x-show="configSettings.area_shape !== 'custom'" class="absolute border border-emerald-500/50 bg-emerald-500/10 z-10 pointer-events-none transition-all shadow-[inset_0_0_20px_rgba(16,185,129,0.2)]"
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
                fill="rgba(16, 185, 129, 0.15)"
                stroke="#10b981"
                stroke-width="0.3"
                stroke-linejoin="round"
                style="filter: drop-shadow(0 0 5px rgba(16,185,129,0.5));" />
        </svg>

        <template x-if="configSettings.area_shape === 'custom'">
            <template x-for="(point, idx) in (configSettings.custom_points || [])" :key="idx">
                <div class="point-handle absolute w-6 h-6 bg-gray-900 border-2 border-primary rounded-full z-20 cursor-move -translate-x-1/2 -translate-y-1/2 shadow-[0_0_10px_rgba(197,160,89,0.5)] flex items-center justify-center hover:scale-125 transition-transform"
                     :style="`left: ${point.x}%; top: ${point.y}%;`"
                     @mousedown.stop="startDragPoint(idx, $event)">
                    <button @click.stop="configSettings.custom_points.splice(idx, 1); $nextTick(() => updateTexture());"
                            class="text-red-400 font-bold hover:text-red-300" style="font-size: 10px;">×</button>
                </div>
            </template>
        </template>
    </div>
</div>
