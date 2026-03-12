<div class="relative flex-1 w-full h-full bg-gray-950/40 overflow-hidden"
     :class="action === 'pan' ? 'cursor-grabbing' : 'cursor-grab'"
     x-ref="canvas"
     wire:poll.1s="pollAiState"
     @mousedown="onCanvasMouseDown($event)"
     @mousemove="onMove($event)"
     @mouseup="stopAction()"
     @mouseleave="stopAction()"
     @wheel.prevent="onZoom($event)"
     @touchstart="onCanvasTouchStart($event)"
     @touchmove.prevent="onMoveTouch($event)"
     @touchend="stopAction()">

    {{-- Dot-Grid Hintergrund (Dark Mode) --}}
    <div class="absolute inset-0 pointer-events-none opacity-20 origin-top-left"
         :style="`background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px); background-size: ${40 * scale}px ${40 * scale}px; transform: translate(${panX}px, ${panY}px);`"></div>

    <svg class="absolute pointer-events-none" style="width: 0; height: 0; position: absolute;">
        <defs>
            <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                <feGaussianBlur stdDeviation="4" result="blur" />
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
                    <path :d="calculatePath(edge)" fill="none" :stroke="getEdgeColor(edge.status)"
                          :stroke-width="edge.status === 'active' ? 3 : 2"
                          :stroke-dasharray="edge.status === 'inactive' ? '6,6' : (edge.status === 'planned' ? '4,4' : 'none')"
                          class="transition-colors duration-300"
                          :opacity="edge.status === 'active' ? '0.2' : '0.4'" />

                    <path :d="calculatePath(edge)" fill="none" :stroke="getEdgeColor(edge.status)"
                          :stroke-width="edge.status !== 'inactive' ? 4 : 0"
                          :opacity="edge.status !== 'inactive' ? 1 : 0"
                          stroke-linecap="round" pathLength="100" stroke-dasharray="2 98" filter="url(#glow)">
                        <animate attributeName="stroke-dashoffset" values="100;0" dur="2.5s" repeatCount="indefinite" />
                    </path>

                    <g class="pointer-events-auto cursor-default">
                        <rect :x="getMidPoint(edge).x - ((edge.label || '').length * 4.5) - 15"
                              :y="getMidPoint(edge).y - 14"
                              :width="((edge.label || '').length * 9) + 30" height="28" rx="10"
                              fill="#111827" :stroke="getEdgeColor(edge.status)" stroke-width="1.5"
                              class="shadow-[0_0_10px_rgba(0,0,0,0.5)] transition-colors" />

                        <circle :cx="getMidPoint(edge).x + ((edge.label || '').length * 4.5) + 15"
                                :cy="getMidPoint(edge).y" r="10" fill="#ef4444"
                                class="cursor-pointer opacity-0 group-hover/edge:opacity-100 transition-opacity"
                                @click="if(confirm('Verbindung wirklich löschen?')) $wire.deleteEdge(edge.id)" />

                        <text :x="getMidPoint(edge).x + ((edge.label || '').length * 4.5) + 15"
                              :y="getMidPoint(edge).y + 3.5" font-size="12" font-weight="bold" fill="white"
                              text-anchor="middle" class="cursor-pointer opacity-0 group-hover/edge:opacity-100 transition-opacity"
                              @click="if(confirm('Verbindung wirklich löschen?')) $wire.deleteEdge(edge.id)">×</text>

                        <text :x="getMidPoint(edge).x" :y="getMidPoint(edge).y + 3.5"
                              font-size="9" font-weight="900" text-anchor="middle" :fill="getEdgeColor(edge.status)"
                              class="pointer-events-none select-none font-sans uppercase tracking-[0.15em] drop-shadow-md">
                            <tspan x-text="edge.label || ''"></tspan>
                        </text>

                        <foreignObject x-show="edge && edge.description" :x="getMidPoint(edge).x - 100" :y="getMidPoint(edge).y + 20"
                                       width="200" height="100" class="pointer-events-none opacity-0 group-hover/edge:opacity-100 transition-opacity" style="z-index: 50;">
                            <div xmlns="http://www.w3.org/1999/xhtml" class="bg-gray-900 border border-gray-700 text-gray-300 text-[10px] font-medium p-3 rounded-xl shadow-2xl text-center leading-relaxed">
                                <strong class="block text-primary mb-1 uppercase tracking-widest" x-text="edge.label"></strong>
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
                <div class="absolute bottom-full mb-4 bg-gray-900 border border-gray-700 text-white text-sm p-4 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-[0_20px_40px_rgba(0,0,0,0.8)] w-56 text-center" style="z-index: 9999;">
                    <strong x-text="node.label" class="block mb-1 text-primary text-base font-serif tracking-wide"></strong>
                    <span x-text="node.description" class="text-[11px] text-gray-400 leading-relaxed block mb-3"></span>
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-gray-950 py-1 rounded-md mb-2" x-text="'Typ: ' + node.type"></div>
                    <template x-if="node.component_key">
                        <div class="text-[9px] font-black text-emerald-400 uppercase tracking-widest" x-text="'Panel: ' + node.component_key"></div>
                    </template>
                </div>

                {{-- Action Buttons --}}
                <button @click.stop="if(confirm('Knotenpunkt wirklich löschen?')) $wire.deleteNode(node.id)" class="absolute -top-2 -right-2 sm:-top-3 sm:-right-3 w-6 h-6 sm:w-8 sm:h-8 bg-red-500/10 border border-red-500/50 hover:bg-red-500 text-red-500 hover:text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all z-30 shadow-lg">
                    <x-heroicon-m-x-mark class="w-3 h-3 sm:w-4 sm:h-4" />
                </button>

                <button @click.stop="$wire.openEditForm(node.id)" class="absolute -bottom-2 -left-2 sm:-bottom-3 sm:-left-3 w-6 h-6 sm:w-8 sm:h-8 bg-gray-800 border border-gray-700 hover:bg-primary hover:border-primary hover:text-gray-900 text-gray-400 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all z-30 shadow-lg">
                    <x-heroicon-m-cog-6-tooth class="w-3 h-3 sm:w-4 sm:h-4" />
                </button>

                <template x-if="node.link">
                    <a :href="node.link" target="_blank" @click.stop class="absolute -top-2 -left-2 sm:-top-3 sm:-left-3 w-6 h-6 sm:w-8 sm:h-8 bg-blue-500/10 border border-blue-500/50 hover:bg-blue-500 text-blue-400 hover:text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all z-30 shadow-lg">
                        <x-heroicon-m-link class="w-3 h-3 sm:w-4 sm:h-4" />
                    </a>
                </template>

                <template x-if="node.component_key">
                    <button @click.stop="$wire.openNodePanel(node.id)" class="absolute -bottom-2 -right-2 sm:-bottom-3 sm:-right-3 w-6 h-6 sm:w-8 sm:h-8 bg-emerald-500/10 border border-emerald-500/50 hover:bg-emerald-500 text-emerald-400 hover:text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all z-30 shadow-lg">
                        <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3 sm:w-4 sm:h-4" />
                    </button>
                </template>

                {{-- Node Box --}}
                <div class="w-16 h-16 sm:w-24 sm:h-24 rounded-2xl sm:rounded-[1.5rem] flex items-center justify-center border-2 transition-all duration-300 hover:scale-110 select-none shadow-inner relative overflow-hidden"
                     :class="getNodeClasses(node)"
                     :style="getNodeGlow(node)"
                     @mousedown.stop="startDragNode($event, index)"
                     @touchstart.stop.prevent="startDragNode($event, index)"
                     @dblclick.stop="handleNodeDblClick(node)">

                    {{-- Status Ping Overlay (Live API Check Feature) --}}
                    <div x-show="apiStatuses[node.id] === 'up'" class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-emerald-500 rounded-full shadow-[0_0_8px_#10b981] animate-pulse"></div>
                    <div x-show="apiStatuses[node.id] === 'down'" class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 rounded-full shadow-[0_0_8px_#ef4444] animate-pulse"></div>

                    {{-- AI LIVE HUD HIGHLIGHT --}}
                    <div x-show="activeMap === 'ai' && liveAiPulse && liveAiPulse.active_node === node.icon" 
                         class="absolute inset-0 z-0 bg-indigo-500/20 shadow-[0_0_50px_rgba(99,102,241,0.6)] animate-pulse border-2 border-indigo-400 rounded-[inherit]">
                    </div>

                    <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-transparent pointer-events-none z-10"></div>

                    <template x-if="isImageLogo(node.icon)">
                        <img :src="getLogoUrl(node.icon)" class="w-8 h-8 sm:w-14 sm:h-14 object-contain pointer-events-none drop-shadow-md relative z-10" :alt="node.label">
                    </template>

                    <template x-if="!isImageLogo(node.icon)">
                        <div class="pointer-events-none relative z-10">
                            <x-heroicon-s-cube          class="w-8 h-8 sm:w-12 sm:h-12" x-show="!node.icon || node.icon === 'cube'" />
                            <x-heroicon-s-sparkles      class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'sparkles'" />
                            <x-heroicon-s-shopping-bag  class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'shopping-bag'" />
                            <x-heroicon-s-shopping-cart class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'shopping-cart'" />
                            <x-heroicon-s-credit-card   class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'credit-card'" />
                            <x-heroicon-s-currency-euro class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'currency-euro'" />
                            <x-heroicon-s-building-library class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'building-library'" />
                            <x-heroicon-s-document-text class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'document-text'" />
                            <x-heroicon-s-server        class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'server' || node.icon === 'circle-stack'" />
                            <x-heroicon-s-device-phone-mobile class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'device-phone-mobile'" />
                            <x-heroicon-s-globe-alt     class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'globe-alt'" />
                            <x-heroicon-s-truck         class="w-8 h-8 sm:w-12 sm:h-12" x-show="node.icon === 'truck'" />
                        </div>
                    </template>
                </div>

                {{-- Node Label --}}
                <template x-if="node.link">
                    <a :href="node.link" target="_blank" @click.stop
                       class="mt-2 sm:mt-3 bg-gray-900/90 backdrop-blur-md px-3 sm:px-4 py-1 sm:py-1.5 rounded-lg border border-gray-700 shadow-lg whitespace-nowrap cursor-pointer hover:bg-primary hover:border-primary transition-colors group/link">
                        <span class="text-[8px] sm:text-[10px] font-black text-gray-300 group-hover/link:text-gray-900 uppercase tracking-widest" x-text="node.label"></span>
                    </a>
                </template>
                <template x-if="!node.link">
                    <div class="mt-2 sm:mt-3 bg-gray-900/90 backdrop-blur-md px-3 sm:px-4 py-1 sm:py-1.5 rounded-lg border border-gray-700 shadow-lg whitespace-nowrap pointer-events-none">
                        <span class="text-[8px] sm:text-[10px] font-black text-gray-300 uppercase tracking-widest" x-text="node.label"></span>
                    </div>
                </template>
            </div>
        </template>

    </div>

    {{-- AI HUD FLOATING OVERLAY --}}
    <div x-show="activeMap === 'ai' && liveAiPulse && liveAiPulse.action_text"
         x-transition:enter="transition ease-out duration-300 transform origin-bottom"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200 transform origin-bottom"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="absolute bottom-6 sm:bottom-12 left-1/2 -translate-x-1/2 bg-gray-950/90 backdrop-blur-2xl border px-6 sm:px-10 py-4 sm:py-5 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.6)] z-40 text-center pointer-events-none min-w-[300px]" 
         :class="liveAiPulse && liveAiPulse.pulse_color === 'red' ? 'border-red-500/50 shadow-[0_20px_50px_rgba(239,68,68,0.3)]' : (liveAiPulse && liveAiPulse.pulse_color === 'emerald' ? 'border-emerald-500/50 shadow-[0_20px_50px_rgba(16,185,129,0.3)]' : 'border-indigo-500/40 shadow-[0_20px_50px_rgba(99,102,241,0.3)]')">
        <div class="text-[9px] sm:text-[11px] uppercase font-black tracking-[0.2em] mb-1.5"
             :class="liveAiPulse && liveAiPulse.pulse_color === 'red' ? 'text-red-400' : (liveAiPulse && liveAiPulse.pulse_color === 'emerald' ? 'text-emerald-400' : 'text-indigo-400')">Live AI Prozess Status</div>
        <div class="text-sm sm:text-lg font-bold text-white font-mono flex items-center justify-center gap-3">
            <span class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full animate-ping"
                  :class="liveAiPulse && liveAiPulse.pulse_color === 'red' ? 'bg-red-500' : (liveAiPulse && liveAiPulse.pulse_color === 'emerald' ? 'bg-emerald-500' : 'bg-indigo-500')"></span>
            <span x-text="liveAiPulse?.action_text || 'System lauscht...'"></span>
        </div>
    </div>
</div>
