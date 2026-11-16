@php
    $cockpitData = ($previewItem && $previewItem->product && $previewItem->product->isPhysical() && empty($isDigitalItem) && empty($isService) && method_exists($this, 'getLaserCockpitDataProperty'))
        ? $this->laserCockpitData
        : null;
@endphp

@if($cockpitData)
    @php
        $fmtDim = static function (float $v): string {
            return abs($v - round($v)) < 0.05
                ? number_format($v, 0, ',', '.')
                : number_format($v, 1, ',', '.');
        };
        $wfStep = (int) ($cockpitData['workflow_step'] ?? 1);
        $isCircleContour = (($cockpitData['contour_shape'] ?? 'rect') === 'circle');
        $hPosMin = $isCircleContour ? '14.64%' : '0%';
        $hPosMax = $isCircleContour ? '85.36%' : '100%';
        $baseViewW = max(0.1, (float) ($cockpitData['base_width_mm'] ?? $cockpitData['width_mm'] ?? 40.0));
        $baseViewH = max(0.1, (float) ($cockpitData['base_height_mm'] ?? $cockpitData['height_mm'] ?? 40.0));
        $isDarkSurfaceOverlay = !empty($cockpitData['is_dark_surface']);
    @endphp
    <div x-data="{
            itemId: '{{ $previewItem->id }}',
            dragging: false,
            dragMode: 'move',
            startClientX: 0,
            startClientY: 0,
            startXMm: 0,
            startYMm: 0,
            startAngleDeg: 0,
            startWMm: {{ (float) $cockpitData['width_mm'] }},
            startHMm: {{ (float) $cockpitData['height_mm'] }},
            startResizeDistPx: 40,
            xMm: {{ (float) $cockpitData['center_x_mm'] }},
            yMm: {{ (float) $cockpitData['center_y_mm'] }},
            angleDeg: {{ (float) $cockpitData['angle_deg'] }},
            wMm: {{ (float) $cockpitData['width_mm'] }},
            hMm: {{ (float) $cockpitData['height_mm'] }},
            fmtMm(v) {
                const n = Number(v) || 0;
                return Math.abs(n - Math.round(n)) < 0.05
                    ? Math.round(n).toString()
                    : n.toFixed(1).replace('.', ',');
            },
            syncCoordsFromServer(detail) {
                const p = Array.isArray(detail) ? (detail[0] || {}) : (detail || {});
                if (p.x !== undefined) this.xMm = Number(p.x);
                if (p.y !== undefined) this.yMm = Number(p.y);
                if (p.angle !== undefined) this.angleDeg = Number(p.angle);
                if (p.w !== undefined) this.wMm = Number(p.w);
                if (p.h !== undefined) this.hMm = Number(p.h);
            },
            persistCoords() {
                $wire.updateLaserCoordinates(
                    this.itemId,
                    Number(this.xMm) || 100,
                    Number(this.yMm) || 100,
                    Number(this.angleDeg) || 0,
                    Number(this.wMm) || {{ (float) $cockpitData['width_mm'] }},
                    Number(this.hMm) || {{ (float) $cockpitData['height_mm'] }}
                );
            },
            nudge(dx, dy, da) {
                this.xMm = Math.round(Math.max(5, Math.min(195, (Number(this.xMm) || 100) + dx)) * 10) / 10;
                this.yMm = Math.round(Math.max(5, Math.min(195, (Number(this.yMm) || 100) + dy)) * 10) / 10;
                this.angleDeg = Math.round(Math.max(-45, Math.min(45, (Number(this.angleDeg) || 0) + da)) * 10) / 10;
                this.persistCoords();
            },
            startDrag(e, mode = 'move') {
                if (e.button !== undefined && e.button !== 0) return;
                this.dragging = true;
                this.dragMode = mode;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                this.startClientX = clientX;
                this.startClientY = clientY;
                this.startXMm = Number(this.xMm) || 100;
                this.startYMm = Number(this.yMm) || 100;
                this.startAngleDeg = Number(this.angleDeg) || 0;
                this.startWMm = Math.max(2.5, Number(this.wMm) || {{ (float) $cockpitData['width_mm'] }});
                this.startHMm = Math.max(2.5, Number(this.hMm) || {{ (float) $cockpitData['height_mm'] }});
                if (mode === 'resize' && this.$refs.bedStage) {
                    const rect = this.$refs.bedStage.getBoundingClientRect();
                    if (rect.width && rect.height) {
                        const centerX = rect.left + (this.startXMm / 200) * rect.width;
                        const centerY = rect.top + (this.startYMm / 200) * rect.height;
                        this.startResizeDistPx = Math.max(8, Math.hypot(clientX - centerX, clientY - centerY));
                    }
                }
            },
            moveDrag(e) {
                if (!this.dragging) return;
                const rect = this.$refs.bedStage.getBoundingClientRect();
                if (!rect.width || !rect.height) return;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;

                if (this.dragMode === 'rotate') {
                    const centerX = rect.left + ((Number(this.xMm) || 100) / 200) * rect.width;
                    const centerY = rect.top + ((Number(this.yMm) || 100) / 200) * rect.height;
                    const a0 = Math.atan2(this.startClientY - centerY, this.startClientX - centerX) * (180 / Math.PI);
                    const a1 = Math.atan2(clientY - centerY, clientX - centerX) * (180 / Math.PI);
                    let deltaAngle = a1 - a0;
                    while (deltaAngle > 180) deltaAngle -= 360;
                    while (deltaAngle < -180) deltaAngle += 360;
                    const nextAngle = Math.max(-45, Math.min(45, this.startAngleDeg + deltaAngle));
                    this.angleDeg = Math.round(nextAngle * 10) / 10;
                    return;
                }

                if (this.dragMode === 'resize') {
                    const centerX = rect.left + ((Number(this.xMm) || 100) / 200) * rect.width;
                    const centerY = rect.top + ((Number(this.yMm) || 100) / 200) * rect.height;
                    const currDistPx = Math.max(4, Math.hypot(clientX - centerX, clientY - centerY));
                    const ratio = currDistPx / Math.max(8, this.startResizeDistPx);
                    let nextW = this.startWMm * ratio;
                    let nextH = this.startHMm * ratio;
                    const major = Math.max(nextW, nextH);
                    if (major < 10) {
                        const f = 10 / Math.max(0.01, major);
                        nextW *= f;
                        nextH *= f;
                    } else if (major > 195) {
                        const f = 195 / major;
                        nextW *= f;
                        nextH *= f;
                    }
                    this.wMm = Math.round(Math.max(2.5, Math.min(195, nextW)) * 10) / 10;
                    this.hMm = Math.round(Math.max(2.5, Math.min(195, nextH)) * 10) / 10;
                    return;
                }

                const dxMm = ((clientX - this.startClientX) / rect.width) * 200;
                const dyMm = ((clientY - this.startClientY) / rect.height) * 200;
                const nextX = Math.max(5, Math.min(195, this.startXMm + dxMm));
                const nextY = Math.max(5, Math.min(195, this.startYMm + dyMm));
                this.xMm = Math.round(nextX * 10) / 10;
                this.yMm = Math.round(nextY * 10) / 10;
            },
            stopDrag() {
                if (!this.dragging) return;
                this.dragging = false;
                this.persistCoords();
            }
         }"
         @laser-cockpit-sync.window="syncCoordsFromServer($event.detail)"
         wire:key="laser-cockpit-{{ $previewItem->id }}-{{ $cockpitData['side'] }}"
         class="bg-white dark:bg-gray-900/90 rounded-[1.5rem] sm:rounded-[2rem] border border-gray-200 dark:border-gray-800 shrink-0 relative">

        <div x-cloak
             x-show="showLaserDetails || {{ $cockpitData['has_back'] ? 'true' : 'false' }}"
             @if(!$cockpitData['has_back']) style="display: none;" @endif
             class="bg-gray-50 dark:bg-gray-950 px-5 sm:px-6 py-3 rounded-t-[1.5rem] sm:rounded-t-[2rem] border-b border-gray-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-2 relative z-30">
            <div x-show="showLaserDetails" class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-wider {{ $cockpitData['laser_online'] ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $cockpitData['laser_online'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    <span>{{ $cockpitData['laser_ip'] }}</span>
                </span>
                <span class="px-2 py-0.5 rounded-lg bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border border-cyan-500/30 text-[9px] font-black uppercase tracking-wider">
                    KI {{ $cockpitData['confidence'] }} %
                </span>
                <div class="relative group/cockpitinfo inline-flex items-center">
                    <span class="w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] flex items-center justify-center text-[9px] font-black cursor-help transition-colors">!</span>
                    <div class="pointer-events-none opacity-0 group-hover/cockpitinfo:opacity-100 group-hover/cockpitinfo:pointer-events-auto transition-opacity duration-150 absolute top-full left-1/2 -translate-x-1/2 sm:left-0 sm:translate-x-0 mt-2 z-[90] w-72 sm:w-80 p-4 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl text-[11px] leading-relaxed text-gray-700 dark:text-gray-300 space-y-2">
                        <div class="font-black uppercase tracking-wider text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1.5">
                            xTool F2 Ultra UV ({{ $cockpitData['device_sn'] }})
                        </div>
                        <p>200 × 200 mm entzerrtes Live-Kamerabild (10 px = 1,0 mm). Die cyanfarbene 1:1 Kontur umschließt bündig die Gravurfläche und lässt sich per Maus verschieben, drehen und skalieren.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 ml-auto">
                @if($cockpitData['has_back'])
                    <button type="button"
                            wire:click="switchLaserCockpitSide('front')"
                            class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider border transition-all cursor-pointer {{ $cockpitData['side'] === 'front' ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)]' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                        Vorderseite
                    </button>
                    <button type="button"
                            wire:click="switchLaserCockpitSide('back')"
                            class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider border transition-all cursor-pointer {{ $cockpitData['side'] === 'back' ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)]' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                        Rückseite
                    </button>
                @endif

                <div x-show="showLaserDetails" class="flex flex-wrap items-center gap-1.5">
                    <button type="button"
                            wire:click="triggerLaserFraming('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}')"
                            wire:loading.attr="disabled"
                            title="Blauen Laser-Rahmen zur Kontrolle auf dem Rohling projizieren"
                            class="px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer">
                        Rahmen-Vorschau
                    </button>
                    <button type="button"
                            wire:click.prevent="downloadLaserXcsFile('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}')"
                            title="Manuelles xTool Studio Backup-Projekt (.xcs) herunterladen"
                            class="px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer">
                        .xcs Backup
                    </button>
                    <span class="text-[9px] font-mono font-bold text-gray-500 dark:text-gray-400">
                        {{ $cockpitData['camera_updated_at'] }}
                    </span>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6 space-y-4">
            {{-- 200x200 mm Live-Kamera-Arbeitsfläche (Smooth Drag & Drop + Rotation) --}}
            <div x-ref="bedStage"
                 @mousedown="startDrag($event, 'move')"
                 @mousemove.window="moveDrag($event)"
                 @mouseup.window="stopDrag()"
                 @touchstart.passive="startDrag($event, 'move')"
                 @touchmove.passive="moveDrag($event)"
                 @touchend.window="stopDrag()"
                 :class="dragging ? 'cursor-grabbing ring-2 ring-cyan-400/60' : 'cursor-grab'"
                 class="relative w-full max-w-[460px] mx-auto aspect-square rounded-2xl overflow-hidden border border-gray-300 dark:border-gray-700 bg-gray-950 select-none shadow-inner transition-shadow">

                <img src="{{ $cockpitData['camera_url'] }}"
                     wire:key="laser-cam-img-{{ $previewItem->id }}-{{ $cockpitData['refresh_token'] ?? 0 }}"
                     alt="xTool F2 Ultra UV Kamera 200x200 mm"
                     class="w-full h-full object-cover pointer-events-none"
                     draggable="false">

                {{-- Dezentrales 20 mm Lineal-Raster (200x200 mm) --}}
                <div class="absolute inset-0 pointer-events-none opacity-20"
                     style="background-image: linear-gradient(to right, rgba(255,255,255,0.22) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.22) 1px, transparent 1px); background-size: 10% 10%;"></div>

                <div x-cloak
                     x-show="showLaserDetails"
                     style="display: none;"
                     class="absolute top-2.5 left-2.5 right-2.5 z-20 pointer-events-none flex flex-wrap items-center justify-between gap-1.5">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="px-2.5 py-1 rounded-lg bg-gray-950/85 border border-gray-700 text-white font-mono text-[10px] font-black">
                            X: <span x-text="(Number(xMm) || 0).toFixed(1)"></span> mm · Y: <span x-text="(Number(yMm) || 0).toFixed(1)"></span> mm · <span x-text="(Number(angleDeg) || 0).toFixed(1)"></span>°
                        </span>
                        <span class="px-2 py-1 rounded-lg bg-cyan-950/85 border border-cyan-500/40 text-cyan-300 font-mono text-[10px] font-bold"
                              x-text="fmtMm(wMm) + ' × ' + fmtMm(hMm) + ' mm'">
                            {{ $fmtDim((float) $cockpitData['width_mm']) }} × {{ $fmtDim((float) $cockpitData['height_mm']) }} mm
                        </span>
                    </div>
                </div>

                @if(!empty($cockpitData['last_status']))
                    <div x-cloak
                         x-show="showLaserDetails"
                         style="display: none;"
                         class="absolute bottom-2.5 left-2.5 right-2.5 z-20 pointer-events-none flex justify-center">
                        <span class="px-3 py-1 rounded-lg bg-emerald-950/90 border border-emerald-500/40 text-emerald-300 font-mono text-[10px] font-bold truncate">
                            ✓ {{ $cockpitData['last_status'] }}
                        </span>
                    </div>
                @endif

                {{-- Bündige 1:1 Gravurflächen-Kontur (#00bcd4) + WYSIWYG-Motiv + Drehgriff + 4 Resize-Handles --}}
                <div class="absolute z-10 pointer-events-none"
                     :style="`left: ${(xMm / 200) * 100}%; top: ${(yMm / 200) * 100}%; width: ${(wMm / 200) * 100}%; height: ${(hMm / 200) * 100}%; transform: translate(-50%, -50%) rotate(${angleDeg}deg);`">
                    <svg viewBox="0 0 {{ $baseViewW }} {{ $baseViewH }}"
                         preserveAspectRatio="none"
                         class="w-full h-full overflow-visible">
                        <path d="{{ $cockpitData['ghost_d_path'] }}"
                              fill="rgba(0, 188, 212, 0.06)"
                              stroke="#00bcd4"
                              stroke-width="1.8"
                              stroke-dasharray="5,3.5"
                              vector-effect="non-scaling-stroke" />
                    </svg>
                    <img src="{{ $cockpitData['preview_data_url'] }}"
                         alt="Gravur-Overlay"
                         class="absolute inset-0 w-full h-full object-fill {{ $isDarkSurfaceOverlay ? 'drop-shadow-[0_1px_2px_rgba(0,0,0,0.45)]' : 'drop-shadow-[0_0_1.5px_rgba(255,255,255,0.55)]' }}"
                         draggable="false">

                    {{-- Dreh-Griff (oben mittig an der Kontur) --}}
                    <button type="button"
                            @mousedown.stop.prevent="startDrag($event, 'rotate')"
                            @touchstart.stop.passive="startDrag($event, 'rotate')"
                            title="Gedrückt halten zum Drehen"
                            class="pointer-events-auto absolute -top-3 left-1/2 -translate-x-1/2 w-5 h-5 rounded-full bg-cyan-400 hover:bg-cyan-300 text-gray-950 border border-gray-950 shadow-md flex items-center justify-center text-[10px] font-black cursor-grab active:cursor-grabbing">
                        &#8635;
                    </button>

                    {{-- 4 Proportionale Resize-Handles (NW, NE, SE, SW) direkt auf der blauen Kontur --}}
                    <button type="button"
                            data-resize-handle="nw"
                            @mousedown.stop.prevent="startDrag($event, 'resize')"
                            @touchstart.stop.passive="startDrag($event, 'resize')"
                            title="Größe ändern"
                            style="left: {{ $hPosMin }}; top: {{ $hPosMin }};"
                            class="pointer-events-auto absolute -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 border border-gray-950 shadow-md cursor-nwse-resize"></button>
                    <button type="button"
                            data-resize-handle="ne"
                            @mousedown.stop.prevent="startDrag($event, 'resize')"
                            @touchstart.stop.passive="startDrag($event, 'resize')"
                            title="Größe ändern"
                            style="left: {{ $hPosMax }}; top: {{ $hPosMin }};"
                            class="pointer-events-auto absolute -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 border border-gray-950 shadow-md cursor-nesw-resize"></button>
                    <button type="button"
                            data-resize-handle="se"
                            @mousedown.stop.prevent="startDrag($event, 'resize')"
                            @touchstart.stop.passive="startDrag($event, 'resize')"
                            title="Größe ändern"
                            style="left: {{ $hPosMax }}; top: {{ $hPosMax }};"
                            class="pointer-events-auto absolute -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 border border-gray-950 shadow-md cursor-nwse-resize"></button>
                    <button type="button"
                            data-resize-handle="sw"
                            @mousedown.stop.prevent="startDrag($event, 'resize')"
                            @touchstart.stop.passive="startDrag($event, 'resize')"
                            title="Größe ändern"
                            style="left: {{ $hPosMin }}; top: {{ $hPosMax }};"
                            class="pointer-events-auto absolute -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 border border-gray-950 shadow-md cursor-nesw-resize"></button>

                    {{-- Mittelpunkt-Fadenkreuz --}}
                    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full bg-cyan-400 border border-gray-950 shadow-sm"></div>
                </div>
            </div>

            {{-- Manuelle Koordinaten & Feinjustierung (Standardmäßig ausgeblendet -> 0 ms via "Details anzeigen") --}}
            <div x-cloak
                 x-show="showLaserDetails"
                 style="display: none;"
                 class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center bg-gray-50 dark:bg-gray-950 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                    <span class="text-[9px] font-black uppercase tracking-wider text-gray-400">X (mm)</span>
                    <input type="number"
                           step="0.1"
                           min="5"
                           max="195"
                           x-model.number="xMm"
                           @change="persistCoords()"
                           class="w-16 text-right font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                </div>
                <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                    <span class="text-[9px] font-black uppercase tracking-wider text-gray-400">Y (mm)</span>
                    <input type="number"
                           step="0.1"
                           min="5"
                           max="195"
                           x-model.number="yMm"
                           @change="persistCoords()"
                           class="w-16 text-right font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                </div>
                <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                    <span class="text-[9px] font-black uppercase tracking-wider text-gray-400">Winkel</span>
                    <input type="number"
                           step="0.1"
                           min="-45"
                           max="45"
                           x-model.number="angleDeg"
                           @change="persistCoords()"
                           class="w-16 text-right font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                </div>
                <div class="flex items-center justify-center gap-1">
                    <button type="button" @click="nudge(-0.5, 0, 0)" title="0,5 mm nach links" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&larr;</button>
                    <button type="button" @click="nudge(0, -0.5, 0)" title="0,5 mm nach oben" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&uarr;</button>
                    <button type="button" @click="nudge(0, 0.5, 0)" title="0,5 mm nach unten" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&darr;</button>
                    <button type="button" @click="nudge(0.5, 0, 0)" title="0,5 mm nach rechts" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&rarr;</button>
                    <button type="button" @click="nudge(0, 0, -0.5)" title="-0,5° drehen" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&#8634;</button>
                    <button type="button" @click="nudge(0, 0, 0.5)" title="+0,5° drehen" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&#8635;</button>
                </div>
            </div>

            {{-- Schlanker 2-Schritte-Laser-Ablauf: 1. Kamera & KI-Ausrichten -> 2. An Laser senden --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                {{-- SCHRITT 1 --}}
                <button type="button"
                        wire:click="refreshLaserCameraAndAlign"
                        wire:loading.attr="disabled"
                        class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-[10px] sm:text-[11px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $wfStep === 1
                            ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                            : 'bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-800' }}">
                    <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 {{ $wfStep > 1 ? 'bg-[var(--theme-color)] text-gray-950' : ($wfStep === 1 ? 'bg-gray-950 text-[var(--theme-color)]' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300') }}">
                        {{ $wfStep > 1 ? '✓' : '1' }}
                    </span>
                    <span>Kamera &amp; KI-Ausrichten</span>
                </button>

                {{-- SCHRITT 2 --}}
                <button type="button"
                        wire:click="sendJobDirectlyToLaser('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}')"
                        wire:loading.attr="disabled"
                        class="flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-[10px] sm:text-[11px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $wfStep === 2
                            ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                            : ($wfStep >= 3
                                ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-40)]'
                                : 'bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-800') }}">
                    <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 {{ $wfStep >= 3 ? 'bg-[var(--theme-color)] text-gray-950' : ($wfStep === 2 ? 'bg-gray-950 text-[var(--theme-color)]' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300') }}">
                        {{ $wfStep >= 3 ? '✓' : '2' }}
                    </span>
                    <span>An Laser senden</span>
                </button>
            </div>
        </div>
    </div>
@endif
