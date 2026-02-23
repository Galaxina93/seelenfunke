<div class="relative flex-1 w-full h-full bg-slate-50 overflow-hidden"
     :class="action === 'pan' ? 'cursor-grabbing' : 'cursor-grab'"
     x-ref="canvas"
     @mousedown="onCanvasMouseDown($event)"
     @mousemove="onMove($event)"
     @mouseup="stopAction()"
     @mouseleave="stopAction()"
     @wheel.prevent="onZoom($event)"
     @touchstart="onCanvasTouchStart($event)"
     @touchmove.prevent="onMoveTouch($event)"
     @touchend="stopAction()">

    {{-- Dot-Grid Hintergrund --}}
    <div class="absolute inset-0 pointer-events-none opacity-20 origin-top-left"
         :style="`background-image: radial-gradient(#C5A059 1px, transparent 1px); background-size: ${40 * scale}px ${40 * scale}px; transform: translate(${panX}px, ${panY}px);`"></div>

    {{-- SVG Defs (Glow-Filter) --}}
    <svg class="absolute pointer-events-none" style="width: 0; height: 0; position: absolute;">
        <defs>
            <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                <feGaussianBlur stdDeviation="3" result="blur" />
                <feMerge>
                    <feMergeNode in="blur" />
                    <feMergeNode in="SourceGraphic" />
                </feMerge>
            </filter>
        </defs>
    </svg>

    {{-- Transformierter Canvas-Inhalt --}}
    <div class="absolute inset-0 transform-gpu origin-top-left" :style="`transform: translate(${panX}px, ${panY}px) scale(${scale});`">

        {{-- EDGES --}}
        <template x-for="edge in edges" :key="'edge-'+edge.id">
            <svg class="absolute inset-0 w-full h-full pointer-events-none z-10" style="overflow: visible;">
                <g class="group/edge">
                    {{-- Hintergrundlinie --}}
                    <path :d="calculatePath(edge)"
                          fill="none"
                          :stroke="getEdgeColor(edge.status)"
                          :stroke-width="edge.status === 'active' ? 3 : 2"
                          :stroke-dasharray="edge.status === 'inactive' ? '6,6' : (edge.status === 'planned' ? '4,4' : 'none')"
                          class="transition-colors duration-300"
                          :opacity="edge.status === 'active' ? '0.3' : '0.5'" />

                    {{-- Animierter Pulse --}}
                    <path :d="calculatePath(edge)"
                          fill="none"
                          :stroke="getEdgeColor(edge.status)"
                          :stroke-width="edge.status !== 'inactive' ? 4 : 0"
                          :opacity="edge.status !== 'inactive' ? 1 : 0"
                          stroke-linecap="round"
                          pathLength="100"
                          stroke-dasharray="2 98"
                          filter="url(#glow)">
                        <animate attributeName="stroke-dashoffset"
                                 values="100;0"
                                 dur="2s"
                                 repeatCount="indefinite" />
                    </path>

                    {{-- Label & Delete --}}
                    <g class="pointer-events-auto cursor-default">
                        <rect :x="getMidPoint(edge).x - ((edge.label || '').length * 4.5) - 15"
                              :y="getMidPoint(edge).y - 14"
                              :width="((edge.label || '').length * 9) + 30"
                              height="28"
                              rx="14"
                              fill="white"
                              :stroke="getEdgeColor(edge.status)"
                              stroke-width="1.5"
                              class="shadow-sm transition-colors" />

                        <circle :cx="getMidPoint(edge).x + ((edge.label || '').length * 4.5) + 15"
                                :cy="getMidPoint(edge).y"
                                r="8"
                                fill="#ef4444"
                                class="cursor-pointer opacity-0 group-hover/edge:opacity-100 transition-opacity"
                                @click="$wire.deleteEdge(edge.id)" />
                        <text :x="getMidPoint(edge).x + ((edge.label || '').length * 4.5) + 15"
                              :y="getMidPoint(edge).y + 3"
                              font-size="10"
                              fill="white"
                              text-anchor="middle"
                              class="cursor-pointer opacity-0 group-hover/edge:opacity-100 transition-opacity"
                              @click="$wire.deleteEdge(edge.id)">×</text>

                        <text :x="getMidPoint(edge).x"
                              :y="getMidPoint(edge).y + 4"
                              font-size="11"
                              font-weight="900"
                              text-anchor="middle"
                              :fill="getEdgeColor(edge.status)"
                              class="pointer-events-none select-none font-sans uppercase tracking-widest">
                            <tspan x-text="edge.label || ''"></tspan>
                        </text>

                        {{-- Tooltip --}}
                        <foreignObject x-show="edge && edge.description"
                                       :x="getMidPoint(edge).x - 100"
                                       :y="getMidPoint(edge).y + 20"
                                       width="200"
                                       height="100"
                                       class="pointer-events-none opacity-0 group-hover/edge:opacity-100 transition-opacity"
                                       style="z-index: 50;">
                            <div xmlns="http://www.w3.org/1999/xhtml" class="bg-slate-900 text-white text-[11px] p-3 rounded-xl shadow-xl text-center leading-tight">
                                <strong class="block text-primary mb-1" x-text="edge.label"></strong>
                                <span x-text="edge.description"></span>
                            </div>
                        </foreignObject>
                    </g>
                </g>
            </svg>
        </template>

        {{-- NODES --}}
        <template x-for="(node, index) in nodes" :key="'node-'+node.id">
            <div class="absolute z-20 flex flex-col items-center transform -translate-x-1/2 -translate-y-1/2 group"
                 :style="`left: ${node.pos_x}%; top: ${node.pos_y}%;`">

                {{-- Hover Tooltip --}}
                <div class="absolute bottom-full mb-4 bg-slate-900 text-white text-sm p-4 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-2xl w-56 text-center" style="z-index: 9999;">
                    <strong x-text="node.label" class="block mb-1 text-primary text-base"></strong>
                    <span x-text="node.description" class="text-xs text-slate-300 leading-tight block mb-2"></span>
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400" x-text="'Typ: ' + node.type"></div>
                    <template x-if="node.link">
                        <div class="text-[9px] font-bold text-blue-400 mt-1 truncate" x-text="node.link"></div>
                    </template>
                    <template x-if="node.component_key">
                        <div class="text-[9px] font-bold text-emerald-400 mt-1 uppercase" x-text="'Panel: ' + node.component_key"></div>
                    </template>
                </div>

                {{-- Löschen Button (oben rechts) --}}
                <button @click.stop="$wire.deleteNode(node.id)"
                        class="absolute -top-3 -right-3 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-30 shadow-md">
                    <x-heroicon-m-x-mark class="w-5 h-5" />
                </button>

                {{-- Bearbeiten Button (unten links) — NEU --}}
                <button @click.stop="$wire.openEditForm(node.id)"
                        class="absolute -bottom-3 -left-3 w-8 h-8 bg-slate-700 hover:bg-slate-900 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-30 shadow-md">
                    <x-heroicon-m-cog-6-tooth class="w-4 h-4" />
                </button>

                {{-- Link Button (oben links) --}}
                <template x-if="node.link">
                    <a :href="node.link" target="_blank" @click.stop
                       class="absolute -top-3 -left-3 w-8 h-8 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-30 shadow-md">
                        <x-heroicon-m-link class="w-4 h-4" />
                    </a>
                </template>

                {{-- Panel-Öffnen Button (unten rechts) — wenn component_key vorhanden --}}
                <template x-if="node.component_key">
                    <button @click.stop="$wire.openNodePanel(node.id)"
                            class="absolute -bottom-3 -right-3 w-8 h-8 bg-emerald-500 hover:bg-emerald-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-30 shadow-md">
                        <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                    </button>
                </template>

                {{-- Node Icon Box --}}
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl flex items-center justify-center border-4 transition-all duration-300 bg-white hover:scale-110 select-none"
                     :class="getNodeClasses(node)"
                     :style="getNodeGlow(node)"
                     @mousedown.stop="startDragNode($event, index)"
                     @touchstart.stop.prevent="startDragNode($event, index)"
                     @dblclick.stop="handleNodeDblClick(node)">

                    <template x-if="isImageLogo(node.icon)">
                        <img :src="getLogoUrl(node.icon)" class="w-12 h-12 sm:w-14 sm:h-14 object-contain pointer-events-none" :alt="node.label">
                    </template>

                    <template x-if="!isImageLogo(node.icon)">
                        <div class="pointer-events-none">
                            <x-heroicon-s-cube          class="w-10 h-10 sm:w-12 sm:h-12" x-show="!node.icon || node.icon === 'cube'" />
                            <x-heroicon-s-sparkles      class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'sparkles'" />
                            <x-heroicon-s-shopping-bag  class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'shopping-bag'" />
                            <x-heroicon-s-shopping-cart class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'shopping-cart'" />
                            <x-heroicon-s-credit-card   class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'credit-card'" />
                            <x-heroicon-s-currency-euro class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'currency-euro'" />
                            <x-heroicon-s-building-library class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'building-library'" />
                            <x-heroicon-s-document-text class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'document-text'" />
                            <x-heroicon-s-server        class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'server'" />
                            <x-heroicon-s-device-phone-mobile class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'device-phone-mobile'" />
                            <x-heroicon-s-globe-alt     class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'globe-alt'" />
                            <x-heroicon-s-truck         class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'truck'" />
                            <x-heroicon-s-fire          class="w-10 h-10 sm:w-12 sm:h-12" x-show="node.icon === 'firebase'" />
                        </div>
                    </template>
                </div>

                {{-- Node Label --}}
                <template x-if="node.link">
                    <a :href="node.link" target="_blank" @click.stop
                       class="mt-3 bg-white/95 backdrop-blur-md px-4 py-1.5 rounded-lg border border-slate-200 shadow-sm whitespace-nowrap cursor-pointer hover:bg-primary hover:text-white transition-colors group/link">
                        <span class="text-[10px] sm:text-xs font-black text-slate-800 group-hover/link:text-white uppercase tracking-widest" x-text="node.label"></span>
                    </a>
                </template>
                <template x-if="!node.link">
                    <div class="mt-3 bg-white/95 backdrop-blur-md px-4 py-1.5 rounded-lg border border-slate-200 shadow-sm whitespace-nowrap pointer-events-none">
                        <span class="text-[10px] sm:text-xs font-black text-slate-800 uppercase tracking-widest" x-text="node.label"></span>
                    </div>
                </template>
            </div>
        </template>

    </div>{{-- Ende transformierter Inhalt --}}
</div>{{-- Ende Canvas --}}
