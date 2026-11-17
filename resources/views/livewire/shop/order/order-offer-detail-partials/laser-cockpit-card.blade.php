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
        $lp = is_array($cockpitData['laser_params'] ?? null)
            ? $cockpitData['laser_params']
            : app(\App\Services\Laser\XtoolLaserCockpitService::class)->resolveEffectiveLaserParameters($previewItem, $cockpitData['side'] ?? 'front');
        $equipModes = is_array($cockpitData['equipment_modes'] ?? null)
            ? $cockpitData['equipment_modes']
            : \App\Services\Laser\XtoolLaserCockpitService::getSupportedEquipmentModes();
        $cockpitSideJs = (string) ($cockpitData['side'] ?? 'front');
        $openScopeKey = $previewItem->id . '-' . $cockpitSideJs;
        $bitmapOptions = [
            'Jarvis',
            'Stucki',
            'Floyd',
            'Atkinson',
            'Graustufen',
            'Schwellenwert',
            'Konturgravur (3D)',
            'Flächenfüllung (Vektor)',
        ];
        if (!empty($lp['bitmap_mode']) && !in_array($lp['bitmap_mode'], $bitmapOptions, true)) {
            $bitmapOptions[] = $lp['bitmap_mode'];
        }
        $engraveModeOptions = ['Zweidirektional', 'Eindirektional', 'Bidirektional', 'Unidirektional'];
        if (!empty($lp['engrave_mode']) && !in_array($lp['engrave_mode'], $engraveModeOptions, true)) {
            $engraveModeOptions[] = $lp['engrave_mode'];
        }
        $equipmentDefaultProcessModes = [];
        foreach ($equipModes as $eqK => $eqM) {
            $equipmentDefaultProcessModes[$eqK] = (string) ($eqM['process_mode'] ?? '');
        }
        $handbookPresets = \App\Services\Laser\XtoolLaserCockpitService::getStepOnePresetOptions($previewItem->product);
    @endphp
    <div x-data="{
            itemId: '{{ $previewItem->id }}',
            sideKey: '{{ $cockpitSideJs }}',
            openScopeKey: '{{ $openScopeKey }}',
            equipmentDefaultModes: @js($equipmentDefaultProcessModes),
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
            cockpitZoom: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? Number(window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].zoom || 1) : 1,
            cockpitPanX: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? Number(window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].panX || 0) : 0,
            cockpitPanY: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? Number(window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].panY || 0) : 0,
            _zoomFocusX: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].focusX : null,
            _zoomFocusY: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].focusY : null,
            startPanX: 0,
            startPanY: 0,
            isDraggingMinimap: false,
            _minimapOffsetX: 0,
            _minimapOffsetY: 0,
            get frameCenterX() {
                return Math.max(0, Math.min(100, ((Number(this.xMm) || 100) / 200) * 100));
            },
            get frameCenterY() {
                return Math.max(0, Math.min(100, ((Number(this.yMm) || 100) / 200) * 100));
            },
            saveCockpitZoomScope() {
                window.__sfLaserCockpitZoom = window.__sfLaserCockpitZoom || {};
                window.__sfLaserCockpitZoom[this.openScopeKey] = {
                    zoom: this.cockpitZoom,
                    panX: this.cockpitPanX,
                    panY: this.cockpitPanY,
                    focusX: this._zoomFocusX,
                    focusY: this._zoomFocusY
                };
            },
            setCockpitZoom(val) {
                const z = Math.max(1.0, Math.min(4.0, Number(val) || 1.0));
                if (z <= 1.01) {
                    this.cockpitZoom = 1.0;
                    this.cockpitPanX = 0.0;
                    this.cockpitPanY = 0.0;
                    this._zoomFocusX = null;
                    this._zoomFocusY = null;
                    this.saveCockpitZoomScope();
                    return;
                }
                if (this._zoomFocusX === null || this._zoomFocusY === null || !Number.isFinite(Number(this._zoomFocusX)) || !Number.isFinite(Number(this._zoomFocusY))) {
                    this._zoomFocusX = this.frameCenterX;
                    this._zoomFocusY = this.frameCenterY;
                }
                const maxPan = 50 * (z - 1);
                this.cockpitZoom = Number(z.toFixed(3));
                this.cockpitPanX = Number(Math.max(-maxPan, Math.min(maxPan, (50 - this._zoomFocusX) * z)).toFixed(2));
                this.cockpitPanY = Number(Math.max(-maxPan, Math.min(maxPan, (50 - this._zoomFocusY) * z)).toFixed(2));
                this.saveCockpitZoomScope();
            },
            stepCockpitZoom(delta) {
                const next = Math.max(1.0, Math.min(4.0, Number((Number(this.cockpitZoom || 1.0) + Number(delta || 0)).toFixed(2))));
                this.setCockpitZoom(next);
            },
            resetCockpitZoom() {
                this.setCockpitZoom(1.0);
            },
            handleCockpitWheel(e) {
                const hasModifier = Boolean(e.ctrlKey || e.metaKey);
                if (!hasModifier && this.cockpitZoom <= 1.001 && e.deltaY >= 0) return;
                if (e.cancelable) e.preventDefault();
                const step = hasModifier ? 0.15 : 0.20;
                this.stepCockpitZoom(e.deltaY < 0 ? step : -step);
            },
            getCockpitStageTransformStyle() {
                if (this.cockpitZoom <= 1.001) {
                    return 'transform-origin: 50% 50%; transform: none; -webkit-transform: none;';
                }
                return `transform-origin: 50% 50%; transform: translate3d(${this.cockpitPanX}%, ${this.cockpitPanY}%, 0) scale(${this.cockpitZoom}); -webkit-transform: translate3d(${this.cockpitPanX}%, ${this.cockpitPanY}%, 0) scale(${this.cockpitZoom});`;
            },
            getCockpitMinimapViewportStyle() {
                const z = Math.max(1.0, Number(this.cockpitZoom || 1.0));
                const vw = 100 / z;
                const vh = 100 / z;
                const left = Math.max(0, Math.min(100 - vw, 50 - (50 + Number(this.cockpitPanX || 0)) / z));
                const top = Math.max(0, Math.min(100 - vh, 50 - (50 + Number(this.cockpitPanY || 0)) / z));
                return `left: ${left.toFixed(2)}%; top: ${top.toFixed(2)}%; width: ${vw.toFixed(2)}%; height: ${vh.toFixed(2)}%;`;
            },
            startMinimapDrag(event) {
                if (this.cockpitZoom <= 1.01) return;
                const minimap = this.$refs.cockpitMinimap;
                if (!minimap) return;
                const rect = minimap.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const mx = ((clientX - rect.left) / Math.max(1, rect.width)) * 100;
                const my = ((clientY - rect.top) / Math.max(1, rect.height)) * 100;

                const z = Math.max(1.01, Number(this.cockpitZoom || 1.01));
                const cx = 50 - Number(this.cockpitPanX || 0) / z;
                const cy = 50 - Number(this.cockpitPanY || 0) / z;
                const halfW = 50 / z;
                const halfH = 50 / z;

                this.isDraggingMinimap = true;
                if (Math.abs(mx - cx) <= halfW && Math.abs(my - cy) <= halfH) {
                    this._minimapOffsetX = mx - cx;
                    this._minimapOffsetY = my - cy;
                } else {
                    this._minimapOffsetX = 0;
                    this._minimapOffsetY = 0;
                    this.updateMinimapFromEvent(event);
                }
            },
            updateMinimapFromEvent(event) {
                if (!this.isDraggingMinimap || this.cockpitZoom <= 1.01) return;
                const minimap = this.$refs.cockpitMinimap;
                if (!minimap) return;
                const rect = minimap.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const mx = ((clientX - rect.left) / Math.max(1, rect.width)) * 100;
                const my = ((clientY - rect.top) / Math.max(1, rect.height)) * 100;

                const targetCx = mx - (this._minimapOffsetX || 0);
                const targetCy = my - (this._minimapOffsetY || 0);
                const z = Math.max(1.01, Number(this.cockpitZoom || 1.01));
                const maxPan = 50 * (z - 1);

                const newPanX = Math.max(-maxPan, Math.min(maxPan, (50 - targetCx) * z));
                const newPanY = Math.max(-maxPan, Math.min(maxPan, (50 - targetCy) * z));

                this.cockpitPanX = Number(newPanX.toFixed(2));
                this.cockpitPanY = Number(newPanY.toFixed(2));
                this._zoomFocusX = Number((50 - this.cockpitPanX / z).toFixed(2));
                this._zoomFocusY = Number((50 - this.cockpitPanY / z).toFixed(2));
                this.saveCockpitZoomScope();
            },
            showLaserParams: window.__sfLaserParamsOpen === '{{ $openScopeKey }}',
            hasLaserOverrides: @js((bool) ($lp['has_overrides'] ?? false)),
            laserParams: {
                equipment_key: @js((string) ($lp['equipment_key'] ?? 'flatbed_standard')),
                process_mode: @js((string) ($lp['process_mode'] ?? 'Prozess auf flacher Oberfläche')),
                material: @js((string) ($lp['material'] ?? 'Edelstahl')),
                focus_mm: {{ (float) ($lp['focus_mm'] ?? 3.0) }},
                element_type: @js((string) ($lp['element_type'] ?? 'Bild')),
                engrave_tab: @js((string) ($lp['engrave_tab'] ?? 'Gravieren')),
                preset_mode: @js((string) ($lp['preset_mode'] ?? 'Benutzerdefiniert')),
                dot_duration_us: {{ (int) ($lp['dot_duration_us'] ?? 120) }},
                power_percent: {{ (int) ($lp['power_percent'] ?? 70) }},
                speed_mms: {{ (int) ($lp['speed_mms'] ?? 200) }},
                dpi: {{ (int) ($lp['dpi'] ?? 500) }},
                passes: {{ (int) ($lp['passes'] ?? 1) }},
                frequency_khz: {{ (int) ($lp['frequency_khz'] ?? 60) }},
                bitmap_mode: @js((string) ($lp['bitmap_mode'] ?? 'Graustufen')),
                engrave_mode: @js((string) ($lp['engrave_mode'] ?? 'Bidirektional')),
                vector_note: @js((string) ($lp['vector_note'] ?? ''))
            },
            toggleLaserParams() {
                this.showLaserParams = !this.showLaserParams;
                window.__sfLaserParamsOpen = this.showLaserParams ? this.openScopeKey : null;
            },
            handbookPresets: @js($handbookPresets),
            handbookPresetKey: '',
            applyHandbookPreset() {
                const preset = this.handbookPresets.find(p => p.key === this.handbookPresetKey);
                if (!preset) return;
                const bmp = preset.bitmap || null;
                const vec = preset.vector || null;
                const main = bmp || vec;
                if (!main) return;
                if (preset.equipment_key && this.equipmentDefaultModes[preset.equipment_key] !== undefined) {
                    this.laserParams.equipment_key = preset.equipment_key;
                    this.laserParams.process_mode = this.equipmentDefaultModes[preset.equipment_key] || this.laserParams.process_mode;
                }
                this.laserParams.focus_mm = Math.max(0.1, Number(main.focus_mm) || 0.1);
                this.laserParams.power_percent = Number(main.power_percent);
                this.laserParams.frequency_khz = Number(main.frequency_khz);
                this.laserParams.passes = Number(main.passes);
                this.laserParams.dpi = Number(main.dpi);
                if (bmp) {
                    this.laserParams.element_type = 'Bild';
                    this.laserParams.dot_duration_us = Number(bmp.dot_duration_us);
                    this.laserParams.bitmap_mode = String(bmp.bitmap_mode || 'Jarvis');
                } else {
                    this.laserParams.element_type = 'Vektor';
                    this.laserParams.bitmap_mode = 'Schwellenwert';
                }
                if (vec) {
                    this.laserParams.speed_mms = Number(vec.speed_mms);
                    this.laserParams.vector_note = `Gravieren · ${vec.power_percent} % · ${vec.speed_mms} mm/s · ${vec.lpc} Linien/cm · ${vec.frequency_khz} kHz` + (Number(vec.passes) > 1 ? ` · ${vec.passes}x` : '');
                }
                this.laserParams.preset_mode = 'Referenz';
                this.persistLaserParams();
            },
            onEquipmentChange() {
                const defMode = this.equipmentDefaultModes[this.laserParams.equipment_key] || '';
                if (defMode !== '') {
                    this.laserParams.process_mode = defMode;
                }
                this.persistLaserParams();
            },
            persistLaserParams() {
                this.hasLaserOverrides = true;
                $wire.updateLaserParameters(this.itemId, this.sideKey, this.laserParams);
            },
            syncLaserParamsFromServer(detail) {
                const p = Array.isArray(detail) ? (detail[0] || {}) : (detail || {});
                const next = p.params || null;
                if (!next) return;
                if (next.equipment_key !== undefined) this.laserParams.equipment_key = String(next.equipment_key);
                if (next.process_mode !== undefined) this.laserParams.process_mode = String(next.process_mode);
                if (next.material !== undefined) this.laserParams.material = String(next.material);
                if (next.focus_mm !== undefined) this.laserParams.focus_mm = Number(next.focus_mm);
                if (next.element_type !== undefined) this.laserParams.element_type = String(next.element_type);
                if (next.engrave_tab !== undefined) this.laserParams.engrave_tab = String(next.engrave_tab);
                if (next.preset_mode !== undefined) this.laserParams.preset_mode = String(next.preset_mode);
                if (next.dot_duration_us !== undefined) this.laserParams.dot_duration_us = Number(next.dot_duration_us);
                if (next.power_percent !== undefined) this.laserParams.power_percent = Number(next.power_percent);
                if (next.speed_mms !== undefined) this.laserParams.speed_mms = Number(next.speed_mms);
                if (next.dpi !== undefined) this.laserParams.dpi = Number(next.dpi);
                if (next.passes !== undefined) this.laserParams.passes = Number(next.passes);
                if (next.frequency_khz !== undefined) this.laserParams.frequency_khz = Number(next.frequency_khz);
                if (next.bitmap_mode !== undefined) this.laserParams.bitmap_mode = String(next.bitmap_mode);
                if (next.engrave_mode !== undefined) this.laserParams.engrave_mode = String(next.engrave_mode);
                if (next.vector_note !== undefined) this.laserParams.vector_note = String(next.vector_note);
                if (next.has_overrides !== undefined) this.hasLaserOverrides = Boolean(next.has_overrides);
            },
            fmtMm(v) {
                const parsed = typeof v === 'string' ? parseFloat(v.replace(',', '.')) : Number(v);
                const n = Number.isFinite(parsed) ? parsed : 0;
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
                if (this.cockpitZoom > 1.01) {
                    this._zoomFocusX = this.frameCenterX;
                    this._zoomFocusY = this.frameCenterY;
                    this.setCockpitZoom(this.cockpitZoom);
                }
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
                this.startPanX = Number(this.cockpitPanX) || 0;
                this.startPanY = Number(this.cockpitPanY) || 0;
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
                        this.startResizeDistPx = Math.max(4, Math.hypot(clientX - centerX, clientY - centerY));
                    }
                }
            },
            moveDrag(e) {
                if (this.isDraggingMinimap) {
                    this.updateMinimapFromEvent(e);
                    return;
                }
                if (!this.dragging) return;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;

                if (this.dragMode === 'pan') {
                    const vpEl = this.$refs.bedViewport || this.$refs.bedStage;
                    if (!vpEl) return;
                    const vpRect = vpEl.getBoundingClientRect();
                    if (!vpRect.width || !vpRect.height) return;
                    const z = Math.max(1.01, Number(this.cockpitZoom || 1.01));
                    const maxPan = 50 * (z - 1);
                    const dPanX = ((clientX - this.startClientX) / vpRect.width) * 100;
                    const dPanY = ((clientY - this.startClientY) / vpRect.height) * 100;
                    this.cockpitPanX = Number(Math.max(-maxPan, Math.min(maxPan, this.startPanX + dPanX)).toFixed(2));
                    this.cockpitPanY = Number(Math.max(-maxPan, Math.min(maxPan, this.startPanY + dPanY)).toFixed(2));
                    this._zoomFocusX = Number((50 - this.cockpitPanX / z).toFixed(2));
                    this._zoomFocusY = Number((50 - this.cockpitPanY / z).toFixed(2));
                    this.saveCockpitZoomScope();
                    return;
                }

                if (!this.$refs.bedStage) return;
                const rect = this.$refs.bedStage.getBoundingClientRect();
                if (!rect.width || !rect.height) return;

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
                    const ratio = currDistPx / Math.max(4, this.startResizeDistPx);
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
                if (this.isDraggingMinimap) {
                    this.isDraggingMinimap = false;
                    return;
                }
                if (!this.dragging) return;
                const wasPan = this.dragMode === 'pan';
                this.dragging = false;
                if (!wasPan) {
                    this.persistCoords();
                }
            }
         }"
         @laser-cockpit-sync.window="syncCoordsFromServer($event.detail)"
         @laser-params-sync.window="syncLaserParamsFromServer($event.detail)"
         wire:key="laser-cockpit-{{ $previewItem->id }}-{{ $cockpitData['side'] }}"
         class="bg-white dark:bg-gray-900/90 rounded-[1.5rem] sm:rounded-[2rem] border border-gray-200 dark:border-gray-800 shrink-0 relative">

        <style>
            @keyframes sfLaserBtnSpin {
                to { transform: rotate(360deg); }
            }
            .sf-laser-btn-spin {
                animation: sfLaserBtnSpin 0.75s linear infinite;
            }
            .sf-laser-action-btn:disabled {
                opacity: 0.75;
                cursor: wait;
            }
            .sf-laser-range {
                -webkit-appearance: none;
                appearance: none;
                width: 100%;
                height: 6px;
                border-radius: 9999px;
                outline: none;
                cursor: pointer;
                accent-color: var(--theme-color);
                background: linear-gradient(
                    to right,
                    var(--theme-color) 0%,
                    var(--theme-color) var(--val-pct, 50%),
                    rgba(156, 163, 175, 0.30) var(--val-pct, 50%),
                    rgba(156, 163, 175, 0.30) 100%
                );
            }
            html.dark .sf-laser-range {
                background: linear-gradient(
                    to right,
                    var(--theme-color) 0%,
                    var(--theme-color) var(--val-pct, 50%),
                    rgba(75, 85, 99, 0.55) var(--val-pct, 50%),
                    rgba(75, 85, 99, 0.55) 100%
                );
            }
            .sf-laser-range::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                width: 16px;
                height: 16px;
                border-radius: 9999px;
                background: var(--theme-color);
                border: 2px solid #ffffff;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
                cursor: pointer;
                transition: transform 0.12s ease, box-shadow 0.12s ease;
            }
            html.dark .sf-laser-range::-webkit-slider-thumb {
                border-color: #030712;
            }
            .sf-laser-range:hover::-webkit-slider-thumb,
            .sf-laser-range:focus::-webkit-slider-thumb {
                transform: scale(1.12);
                box-shadow: 0 0 0 4px var(--theme-color-20);
            }
            .sf-laser-range::-moz-range-track {
                height: 6px;
                border-radius: 9999px;
                background: rgba(156, 163, 175, 0.30);
            }
            html.dark .sf-laser-range::-moz-range-track {
                background: rgba(75, 85, 99, 0.55);
            }
            .sf-laser-range::-moz-range-progress {
                height: 6px;
                border-radius: 9999px;
                background: var(--theme-color);
            }
            .sf-laser-range::-moz-range-thumb {
                width: 16px;
                height: 16px;
                border-radius: 9999px;
                background: var(--theme-color);
                border: 2px solid #ffffff;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
                cursor: pointer;
                transition: transform 0.12s ease, box-shadow 0.12s ease;
            }
            html.dark .sf-laser-range::-moz-range-thumb {
                border-color: #030712;
            }
            .sf-laser-range:hover::-moz-range-thumb,
            .sf-laser-range:focus::-moz-range-thumb {
                transform: scale(1.12);
                box-shadow: 0 0 0 4px var(--theme-color-20);
            }
        </style>

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
                            @mousedown.stop
                            @touchstart.stop
                            wire:click="triggerLaserFraming('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}', laserParams)"
                            wire:loading.attr="disabled"
                            wire:target="triggerLaserFraming"
                            title="Blauen Laser-Rahmen zur Kontrolle auf dem Rohling projizieren"
                            class="sf-laser-action-btn inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer disabled:opacity-70 disabled:cursor-wait">
                        <span wire:loading.remove wire:target="triggerLaserFraming">Rahmen-Vorschau</span>
                        <span wire:loading wire:target="triggerLaserFraming">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-3 h-3 shrink-0 sf-laser-btn-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.75" opacity="0.25"></circle>
                                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.75" stroke-linecap="round"></path>
                                </svg>
                                <span>Rahmen-Vorschau</span>
                            </span>
                        </span>
                    </button>
                    <button type="button"
                            @click.stop
                            @mousedown.stop
                            @touchstart.stop
                            wire:click.prevent="downloadLaserXcsFile('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}', laserParams)"
                            wire:loading.attr="disabled"
                            wire:target="downloadLaserXcsFile"
                            title="Manuelles xTool Studio Backup-Projekt (.xcs) herunterladen"
                            class="sf-laser-action-btn inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer disabled:opacity-70 disabled:cursor-wait">
                        <span wire:loading.remove wire:target="downloadLaserXcsFile">.xcs Backup</span>
                        <span wire:loading wire:target="downloadLaserXcsFile">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-3 h-3 shrink-0 sf-laser-btn-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.75" opacity="0.25"></circle>
                                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.75" stroke-linecap="round"></path>
                                </svg>
                                <span>.xcs Backup</span>
                            </span>
                        </span>
                    </button>
                    <span class="text-[9px] font-mono font-bold text-gray-500 dark:text-gray-400">
                        {{ $cockpitData['camera_updated_at'] }}
                    </span>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6 space-y-3 sm:space-y-4">
            {{-- Schlanke Zoom-Steuerleiste (100 % bis 400 %) analog Produktkonfigurator --}}
            <div data-cockpit-zoom-bar
                 @mousedown.stop
                 @touchstart.stop
                 class="flex items-center gap-2 sm:gap-2.5 px-3 py-1.5 rounded-xl border w-full max-w-[460px] mx-auto bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-100 select-none">
                <button type="button"
                        data-cockpit-zoom-out
                        @click.stop="stepCockpitZoom(-0.25)"
                        :disabled="cockpitZoom <= 1.01"
                        :class="cockpitZoom > 1.01 ? 'hover:bg-[var(--theme-color-15)] active:scale-90 cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                        class="w-6 h-6 rounded-full flex items-center justify-center transition-all select-none shrink-0"
                        title="Herauszoomen">
                    <x-heroicon-m-magnifying-glass-minus class="w-4 h-4 text-[var(--theme-color)]" />
                </button>
                <input type="range"
                       data-cockpit-zoom-slider
                       min="1"
                       max="4"
                       step="0.05"
                       :value="cockpitZoom"
                       @mousedown.stop
                       @touchstart.stop
                       @input.stop="setCockpitZoom($event.target.value)"
                       :style="`--val-pct: ${Math.max(0, Math.min(100, (((Number(cockpitZoom) || 1) - 1) / 3) * 100)).toFixed(2)}%;`"
                       class="sf-laser-range flex-1 min-w-0"
                       title="Kamerabett zoomen (100 % bis 400 %)">
                <button type="button"
                        data-cockpit-zoom-in
                        @click.stop="stepCockpitZoom(0.25)"
                        :disabled="cockpitZoom >= 3.99"
                        :class="cockpitZoom < 3.99 ? 'hover:bg-[var(--theme-color-15)] active:scale-90 cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                        class="w-6 h-6 rounded-full flex items-center justify-center transition-all select-none shrink-0"
                        title="Heranzoomen">
                    <x-heroicon-m-magnifying-glass-plus class="w-4 h-4 text-[var(--theme-color)]" />
                </button>
                <button type="button"
                        data-cockpit-zoom-reset
                        @click.stop="resetCockpitZoom()"
                        :class="cockpitZoom > 1.01 ? 'text-[var(--theme-color)] hover:underline cursor-pointer' : 'text-gray-400 dark:text-gray-500 cursor-default'"
                        class="min-w-[38px] text-right font-mono text-[10px] font-black tracking-tight transition-colors select-none shrink-0"
                        title="Zoom auf 100 % zurücksetzen">
                    <span x-text="Math.round(cockpitZoom * 100) + '%'">100%</span>
                </button>
            </div>

            {{-- 200x200 mm Live-Kamera-Arbeitsfläche (Zoom 100 %-400 %, Pan, Minimap, Smooth Drag & Drop + Rotation) --}}
            <div x-ref="bedViewport"
                 data-cockpit-bed-viewport
                 @wheel="handleCockpitWheel($event)"
                 @mousedown="startDrag($event, cockpitZoom > 1.01 ? 'pan' : 'move')"
                 @mousemove.window="moveDrag($event)"
                 @mouseup.window="stopDrag()"
                 @touchstart.passive="startDrag($event, cockpitZoom > 1.01 ? 'pan' : 'move')"
                 @touchmove.window.passive="moveDrag($event)"
                 @touchend.window="stopDrag()"
                 :class="[dragging || isDraggingMinimap ? 'cursor-grabbing ring-2 ring-cyan-400/60' : 'cursor-grab', cockpitZoom > 1.01 ? 'touch-none' : '']"
                 class="relative w-full max-w-[460px] mx-auto aspect-square rounded-2xl overflow-hidden border border-gray-300 dark:border-gray-700 bg-gray-950 select-none shadow-inner transition-shadow">

                {{-- Gezoomter & gepannter 200x200 mm Kamera-Stage-Layer --}}
                <div x-ref="bedStage"
                     data-cockpit-bed-stage
                     class="absolute inset-0 w-full h-full"
                     :style="getCockpitStageTransformStyle()">

                    <img src="{{ $cockpitData['camera_url'] }}"
                         wire:key="laser-cam-img-{{ $previewItem->id }}-{{ $cockpitData['refresh_token'] ?? 0 }}"
                         alt="xTool F2 Ultra UV Kamera 200x200 mm"
                         class="w-full h-full object-cover pointer-events-none"
                         draggable="false">

                    {{-- Dezentrales 20 mm Lineal-Raster (200x200 mm) --}}
                    <div class="absolute inset-0 pointer-events-none opacity-20"
                         style="background-image: linear-gradient(to right, rgba(255,255,255,0.22) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.22) 1px, transparent 1px); background-size: 10% 10%;"></div>

                    {{-- Bündige 1:1 Gravurflächen-Kontur (#00bcd4) + WYSIWYG-Motiv + Drehgriff + 4 Resize-Handles --}}
                    <div data-cockpit-blue-frame
                         @mousedown.stop.prevent="startDrag($event, 'move')"
                         @touchstart.stop.passive="startDrag($event, 'move')"
                         :class="dragging && dragMode === 'move' ? 'cursor-grabbing' : 'cursor-move'"
                         class="absolute z-10 pointer-events-auto touch-none {{ $isCircleContour ? 'rounded-full' : '' }}"
                         :style="`left: ${(xMm / 200) * 100}%; top: ${(yMm / 200) * 100}%; width: ${(wMm / 200) * 100}%; height: ${(hMm / 200) * 100}%; transform: translate(-50%, -50%) rotate(${angleDeg}deg);`">
                        <svg viewBox="0 0 {{ $baseViewW }} {{ $baseViewH }}"
                             preserveAspectRatio="none"
                             class="w-full h-full overflow-visible pointer-events-none">
                            <path d="{{ $cockpitData['ghost_d_path'] }}"
                                  fill="rgba(0, 188, 212, 0.06)"
                                  stroke="#00bcd4"
                                  stroke-width="1.8"
                                  stroke-dasharray="5,3.5"
                                  vector-effect="non-scaling-stroke" />
                        </svg>
                        <img src="{{ $cockpitData['preview_data_url'] }}"
                             alt="Gravur-Overlay"
                             class="absolute inset-0 w-full h-full object-fill pointer-events-none {{ $isDarkSurfaceOverlay ? 'drop-shadow-[0_1px_2px_rgba(0,0,0,0.45)]' : 'drop-shadow-[0_0_1.5px_rgba(255,255,255,0.55)]' }}"
                             draggable="false">

                        {{-- Dreh-Griff (oben mittig an der Kontur, per scale(1 / cockpitZoom) kompakt gehalten) --}}
                        <button type="button"
                                data-rotate-handle
                                @mousedown.stop.prevent="startDrag($event, 'rotate')"
                                @touchstart.stop.passive="startDrag($event, 'rotate')"
                                title="Gedrückt halten zum Drehen"
                                style="left: 50%; top: 0%; transform: translate(-50%, -60%) scale(1);"
                                :style="`left: 50%; top: 0%; transform: translate(-50%, -60%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-5 h-5 rounded-full bg-cyan-400 hover:bg-cyan-300 text-gray-950 border border-gray-950 shadow-md flex items-center justify-center text-[10px] font-black cursor-grab active:cursor-grabbing">
                            &#8635;
                        </button>

                        {{-- 4 Proportionale Resize-Handles (NW, NE, SE, SW) direkt auf der blauen Kontur --}}
                        <button type="button"
                                data-resize-handle="nw"
                                @mousedown.stop.prevent="startDrag($event, 'resize')"
                                @touchstart.stop.passive="startDrag($event, 'resize')"
                                title="Größe ändern"
                                style="left: {{ $hPosMin }}; top: {{ $hPosMin }}; transform: translate(-50%, -50%) scale(1);"
                                :style="`left: {{ $hPosMin }}; top: {{ $hPosMin }}; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 border border-gray-950 shadow-md cursor-nwse-resize"></button>
                        <button type="button"
                                data-resize-handle="ne"
                                @mousedown.stop.prevent="startDrag($event, 'resize')"
                                @touchstart.stop.passive="startDrag($event, 'resize')"
                                title="Größe ändern"
                                style="left: {{ $hPosMax }}; top: {{ $hPosMin }}; transform: translate(-50%, -50%) scale(1);"
                                :style="`left: {{ $hPosMax }}; top: {{ $hPosMin }}; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 border border-gray-950 shadow-md cursor-nesw-resize"></button>
                        <button type="button"
                                data-resize-handle="se"
                                @mousedown.stop.prevent="startDrag($event, 'resize')"
                                @touchstart.stop.passive="startDrag($event, 'resize')"
                                title="Größe ändern"
                                style="left: {{ $hPosMax }}; top: {{ $hPosMax }}; transform: translate(-50%, -50%) scale(1);"
                                :style="`left: {{ $hPosMax }}; top: {{ $hPosMax }}; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 border border-gray-950 shadow-md cursor-nwse-resize"></button>
                        <button type="button"
                                data-resize-handle="sw"
                                @mousedown.stop.prevent="startDrag($event, 'resize')"
                                @touchstart.stop.passive="startDrag($event, 'resize')"
                                title="Größe ändern"
                                style="left: {{ $hPosMin }}; top: {{ $hPosMax }}; transform: translate(-50%, -50%) scale(1);"
                                :style="`left: {{ $hPosMin }}; top: {{ $hPosMax }}; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 border border-gray-950 shadow-md cursor-nesw-resize"></button>

                        {{-- Mittelpunkt-Fadenkreuz --}}
                        <div style="left: 50%; top: 50%; transform: translate(-50%, -50%) scale(1);"
                             :style="`left: 50%; top: 50%; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                             class="pointer-events-none absolute w-2.5 h-2.5 rounded-full bg-cyan-400 border border-gray-950 shadow-sm"></div>
                    </div>
                </div>

                {{-- Navigator-Minimap oben rechts zum millimetergenauen Verschieben des Bildausschnitts bei aktivem Zoom --}}
                <div x-cloak
                     x-show="cockpitZoom > 1.01"
                     style="display: none;"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-90"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-90"
                     x-ref="cockpitMinimap"
                     data-cockpit-zoom-minimap
                     @mousedown.stop.prevent="startMinimapDrag($event)"
                     @touchstart.stop.passive="startMinimapDrag($event)"
                     title="Bildausschnitt auf dem 200x200 mm Laserbett verschieben"
                     class="absolute top-2.5 right-2.5 z-40 w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden shadow-[0_8px_24px_rgba(0,0,0,0.45)] border-2 border-[var(--theme-color)] bg-gray-950/95 cursor-grab active:cursor-grabbing select-none touch-none pointer-events-auto">
                    <img src="{{ $cockpitData['camera_url'] }}"
                         class="absolute inset-0 w-full h-full object-cover pointer-events-none opacity-85"
                         alt="">
                    <div class="absolute z-[2] border border-cyan-400 bg-cyan-400/20 pointer-events-none {{ $isCircleContour ? 'rounded-full' : '' }}"
                         :style="`left: ${(xMm / 200) * 100}%; top: ${(yMm / 200) * 100}%; width: ${Math.max(4, (wMm / 200) * 100)}%; height: ${Math.max(4, (hMm / 200) * 100)}%; transform: translate(-50%, -50%) rotate(${angleDeg}deg);`"></div>
                    <div class="absolute z-[5] border-2 border-[var(--theme-color)] bg-[var(--theme-color-15)] rounded-[3px] pointer-events-none shadow-[0_0_0_999px_rgba(0,0,0,0.48)]"
                         :style="getCockpitMinimapViewportStyle()">
                        <div class="absolute inset-0 flex items-center justify-center opacity-80">
                            <div class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)] shadow-sm"></div>
                        </div>
                    </div>
                </div>

                <div x-cloak
                     x-show="showLaserDetails"
                     style="display: none;"
                     :class="cockpitZoom > 1.01 ? 'pr-24 sm:pr-28' : ''"
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

                {{-- Aktives Status-Badge während Kamera/KI-Ausrichtung oder Übertragung an xTool --}}
                <div wire:loading
                     wire:target="refreshLaserCameraAndAlign"
                     class="absolute bottom-2.5 left-0 w-full px-2.5 z-30 pointer-events-none">
                    <div class="flex justify-center">
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-950/90 border border-[var(--theme-color)] text-white shadow-lg text-[10px] font-black uppercase tracking-wider">
                            <svg class="w-3.5 h-3.5 shrink-0 text-[var(--theme-color)] sf-laser-btn-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.75" opacity="0.25"></circle>
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.75" stroke-linecap="round"></path>
                            </svg>
                            <span>Kamera &amp; KI-Ausrichtung...</span>
                        </span>
                    </div>
                </div>

                <div wire:loading
                     wire:target="sendJobDirectlyToLaser"
                     class="absolute bottom-2.5 left-0 w-full px-2.5 z-30 pointer-events-none">
                    <div class="flex justify-center">
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-950/90 border border-[var(--theme-color)] text-white shadow-lg text-[10px] font-black uppercase tracking-wider">
                            <svg class="w-3.5 h-3.5 shrink-0 text-[var(--theme-color)] sf-laser-btn-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.75" opacity="0.25"></circle>
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.75" stroke-linecap="round"></path>
                            </svg>
                            <span>Übertrage an xTool...</span>
                        </span>
                    </div>
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

            {{-- Direkte Laserparameter-Leiste & Clean-Design Parameter-Editor (Schritt 1: In Bearbeitung) --}}
            <div class="space-y-2">
                <div @click="toggleLaserParams()"
                     class="bg-gray-50 dark:bg-gray-950 px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] flex flex-wrap items-center justify-between gap-2 transition-colors cursor-pointer select-none">
                    <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-mono font-bold text-gray-700 dark:text-gray-300 min-w-0">
                        <span x-cloak
                              x-show="hasLaserOverrides"
                              @if(empty($lp['has_overrides'])) style="display: none;" @endif
                              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-40)] font-sans text-[9px] font-black uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)]"></span>
                            <span>Angepasst</span>
                        </span>
                        <span class="font-sans font-black text-gray-900 dark:text-white truncate max-w-[180px]" x-text="laserParams.material">{{ $lp['material'] }}</span>
                        <span class="text-gray-400">·</span>
                        <span x-text="fmtMm(laserParams.focus_mm) + ' mm'">{{ $lp['focus_formatted'] }}</span>
                        <span class="text-gray-400">·</span>
                        <span x-text="laserParams.power_percent + ' %'">{{ $lp['power_percent'] }} %</span>
                        <span class="text-gray-400">·</span>
                        <span x-text="laserParams.dot_duration_us + ' µs'">{{ $lp['dot_duration_us'] }} µs</span>
                        <span class="text-gray-400">·</span>
                        <span x-text="laserParams.frequency_khz + ' kHz'">{{ $lp['frequency_khz'] }} kHz</span>
                        <span class="text-gray-400">·</span>
                        <span x-text="laserParams.dpi + ' DPI'">{{ $lp['dpi'] }} DPI</span>
                        <span class="text-gray-400">·</span>
                        <span x-text="laserParams.passes + '×'">{{ $lp['passes'] }}&times;</span>
                    </div>

                    <div class="flex items-center gap-1.5 ml-auto shrink-0" @click.stop>
                        <button type="button"
                                @click.stop
                                @mousedown.stop
                                @touchstart.stop
                                wire:click.prevent="downloadLaserXcsFile('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}', laserParams)"
                                wire:loading.attr="disabled"
                                wire:target="downloadLaserXcsFile"
                                title="xTool Studio Projekt (.xcs) mit aktuellen Parametern herunterladen"
                                class="sf-laser-action-btn inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer disabled:opacity-70 disabled:cursor-wait">
                            <span wire:loading.remove wire:target="downloadLaserXcsFile">.xcs Backup</span>
                            <span wire:loading wire:target="downloadLaserXcsFile">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3 h-3 shrink-0 sf-laser-btn-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.75" opacity="0.25"></circle>
                                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.75" stroke-linecap="round"></path>
                                    </svg>
                                    <span>.xcs Backup</span>
                                </span>
                            </span>
                        </button>
                        <button type="button"
                                @click="toggleLaserParams()"
                                :class="showLaserParams ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)]' : 'bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)]'"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer">
                            <span>&#9881;&#65039; Laserparameter anpassen</span>
                        </button>
                    </div>
                </div>

                <div x-cloak
                     x-show="showLaserParams"
                     style="display: none;"
                     class="bg-gray-50 dark:bg-gray-950 p-3 rounded-xl border border-gray-200 dark:border-gray-800 space-y-2.5">
                    @if(!empty($handbookPresets))
                        {{-- Zeile 0: Validierte xTool F2 Ultra UV Handbuch-Presets (PDF 1 & PDF 2, Vektor + Bitmap) --}}
                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-[var(--theme-color-30)]">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-[var(--theme-color)]">Handbuch-Preset</label>
                            <select x-model="handbookPresetKey"
                                    @change="applyHandbookPreset()"
                                    data-sf-handbook-presets="{{ count($handbookPresets) }}"
                                    class="w-full mt-0.5 text-xs font-bold bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none cursor-pointer">
                                <option value="">Preset wählen</option>
                                @foreach(collect($handbookPresets)->groupBy('group') as $presetGroup => $presetsInGroup)
                                    <optgroup label="{{ $presetGroup }}">
                                        @foreach($presetsInGroup as $hbPreset)
                                            <option value="{{ $hbPreset['key'] }}">{{ $hbPreset['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    {{-- Zeile 1: Aufbau (Equipment) · Prozess-Modus · Material · Fokus (mm) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Aufbau</label>
                            <select x-model="laserParams.equipment_key"
                                    @change="onEquipmentChange()"
                                    class="w-full mt-0.5 text-xs font-bold bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none cursor-pointer">
                                @foreach($equipModes as $eqKey => $eqMeta)
                                    <option value="{{ $eqKey }}">{{ $eqMeta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Modus</label>
                            <input type="text"
                                   list="sfLaserProcessModes-{{ $previewItem->id }}"
                                   x-model="laserParams.process_mode"
                                   @change="persistLaserParams()"
                                   class="w-full mt-0.5 text-xs font-bold bg-transparent text-gray-900 dark:text-white focus:outline-none">
                            <datalist id="sfLaserProcessModes-{{ $previewItem->id }}">
                                <option value="Prozess auf flacher Oberfläche"></option>
                                <option value="Walze - Rotationsaufsatz"></option>
                                <option value="Spannfutter - Rotationsaufsatz"></option>
                                <option value="Gleiches Design - Stapelverarbeitung"></option>
                                <option value="Innengravur - 3D-Miniatur"></option>
                                <option value="Gebogene Oberfläche"></option>
                            </datalist>
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Material</label>
                            <input type="text"
                                   list="sfLaserMaterials-{{ $previewItem->id }}"
                                   x-model="laserParams.material"
                                   @change="persistLaserParams()"
                                   class="w-full mt-0.5 text-xs font-bold bg-transparent text-gray-900 dark:text-white focus:outline-none">
                            <datalist id="sfLaserMaterials-{{ $previewItem->id }}">
                                <option value="14K vergoldeter Edelstahl"></option>
                                <option value="Edelstahl"></option>
                                <option value="0,2 mm Aluminiumlegierung"></option>
                                <option value="Eloxiertes Aluminium"></option>
                                <option value="K9 Kristalltrophäe"></option>
                                <option value="K9 Kristallglas"></option>
                                <option value="Klares Glas"></option>
                                <option value="Schieferstein"></option>
                                <option value="Messing"></option>
                                <option value="Holz"></option>
                                <option value="Acryl"></option>
                                <option value="Leder"></option>
                                <option value="Keramik"></option>
                            </datalist>
                        </div>

                        <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-400">Fokus (mm)</label>
                            <input type="number"
                                   step="0.1"
                                   min="0.1"
                                   max="150"
                                   x-model="laserParams.focus_mm"
                                   @change="persistLaserParams()"
                                   class="w-20 text-right font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                        </div>
                    </div>

                    {{-- Zeile 2: Elementtyp · Gravur-Art · Vorgabe --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Elementtyp</label>
                            <select x-model="laserParams.element_type"
                                    @change="persistLaserParams()"
                                    class="w-full mt-0.5 text-xs font-bold bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none cursor-pointer">
                                <option value="Bild">Bild</option>
                                <option value="Vektor">Vektor</option>
                                <option value="3D-Modell">3D-Modell</option>
                            </select>
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Gravur-Art</label>
                            <select x-model="laserParams.engrave_tab"
                                    @change="persistLaserParams()"
                                    class="w-full mt-0.5 text-xs font-bold bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none cursor-pointer">
                                <option value="Gravieren">Gravieren</option>
                                <option value="Farbgravur">Farbgravur</option>
                                <option value="Konturgravur">Konturgravur</option>
                            </select>
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Vorgabe</label>
                            <select x-model="laserParams.preset_mode"
                                    @change="persistLaserParams()"
                                    class="w-full mt-0.5 text-xs font-bold bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none cursor-pointer">
                                <option value="Benutzerdefiniert">Benutzerdefiniert</option>
                                <option value="Referenz">Referenz</option>
                            </select>
                        </div>
                    </div>

                    {{-- Zeile 3: 4 Theme-Color Schieberegler: Punktdauer, Leistung, DPI & Frequenz --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div class="bg-white dark:bg-gray-900 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Punktdauer (µs)</label>
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] border border-[var(--theme-color-30)]">
                                    <input type="number"
                                           step="5"
                                           min="1"
                                           max="5000"
                                           x-model.number="laserParams.dot_duration_us"
                                           @change="persistLaserParams()"
                                           class="w-14 text-right font-mono text-xs font-black bg-transparent text-[var(--theme-color)] focus:outline-none">
                                    <span class="text-[9px] font-mono font-bold text-[var(--theme-color)]">µs</span>
                                </div>
                            </div>
                            <input type="range"
                                   min="1"
                                   :max="Math.max(1000, Number(laserParams.dot_duration_us) || 1000)"
                                   step="5"
                                   @mousedown.stop
                                   @touchstart.stop
                                   x-model.number="laserParams.dot_duration_us"
                                   @change="persistLaserParams()"
                                   :style="`--val-pct: ${Math.max(0, Math.min(100, (((Number(laserParams.dot_duration_us) || 1) - 1) / (Math.max(1000, Number(laserParams.dot_duration_us) || 1000) - 1)) * 100))}%;`"
                                   class="sf-laser-range">
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Leistung (%)</label>
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] border border-[var(--theme-color-30)]">
                                    <input type="number"
                                           step="1"
                                           min="1"
                                           max="100"
                                           x-model.number="laserParams.power_percent"
                                           @change="persistLaserParams()"
                                           class="w-11 text-right font-mono text-xs font-black bg-transparent text-[var(--theme-color)] focus:outline-none">
                                    <span class="text-[9px] font-mono font-bold text-[var(--theme-color)]">%</span>
                                </div>
                            </div>
                            <input type="range"
                                   min="1"
                                   max="100"
                                   step="1"
                                   @mousedown.stop
                                   @touchstart.stop
                                   x-model.number="laserParams.power_percent"
                                   @change="persistLaserParams()"
                                   :style="`--val-pct: ${Math.max(0, Math.min(100, (((Number(laserParams.power_percent) || 1) - 1) / 99) * 100))}%;`"
                                   class="sf-laser-range">
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">DPI</label>
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] border border-[var(--theme-color-30)]">
                                    <input type="number"
                                           step="1"
                                           min="50"
                                           max="2540"
                                           list="sfLaserDpi-{{ $previewItem->id }}"
                                           x-model.number="laserParams.dpi"
                                           @change="persistLaserParams()"
                                           class="w-14 text-right font-mono text-xs font-black bg-transparent text-[var(--theme-color)] focus:outline-none">
                                    <span class="text-[9px] font-mono font-bold text-[var(--theme-color)]">DPI</span>
                                </div>
                                <datalist id="sfLaserDpi-{{ $previewItem->id }}">
                                    <option value="254"></option>
                                    <option value="300"></option>
                                    <option value="500"></option>
                                    <option value="600"></option>
                                    <option value="800"></option>
                                    <option value="1000"></option>
                                    <option value="1270"></option>
                                    <option value="2540"></option>
                                </datalist>
                            </div>
                            <input type="range"
                                   min="50"
                                   :max="Math.max(1270, Number(laserParams.dpi) || 1270)"
                                   step="10"
                                   @mousedown.stop
                                   @touchstart.stop
                                   x-model.number="laserParams.dpi"
                                   @change="persistLaserParams()"
                                   :style="`--val-pct: ${Math.max(0, Math.min(100, (((Number(laserParams.dpi) || 50) - 50) / (Math.max(1270, Number(laserParams.dpi) || 1270) - 50)) * 100))}%;`"
                                   class="sf-laser-range">
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Frequenz (kHz)</label>
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] border border-[var(--theme-color-30)]">
                                    <input type="number"
                                           step="1"
                                           min="1"
                                           max="200"
                                           x-model.number="laserParams.frequency_khz"
                                           @change="persistLaserParams()"
                                           class="w-12 text-right font-mono text-xs font-black bg-transparent text-[var(--theme-color)] focus:outline-none">
                                    <span class="text-[9px] font-mono font-bold text-[var(--theme-color)]">kHz</span>
                                </div>
                            </div>
                            <input type="range"
                                   min="1"
                                   :max="Math.max(150, Number(laserParams.frequency_khz) || 150)"
                                   step="1"
                                   @mousedown.stop
                                   @touchstart.stop
                                   x-model.number="laserParams.frequency_khz"
                                   @change="persistLaserParams()"
                                   :style="`--val-pct: ${Math.max(0, Math.min(100, (((Number(laserParams.frequency_khz) || 1) - 1) / (Math.max(150, Number(laserParams.frequency_khz) || 150) - 1)) * 100))}%;`"
                                   class="sf-laser-range">
                        </div>
                    </div>

                    {{-- Zeile 4: Speed (mm/s) · Durchgänge · Bitmap · Richtung --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Speed (mm/s)</label>
                            <input type="number"
                                   step="1"
                                   min="1"
                                   max="4000"
                                   x-model.number="laserParams.speed_mms"
                                   @change="persistLaserParams()"
                                   class="w-full mt-0.5 font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Durchgänge</label>
                            <input type="number"
                                   step="1"
                                   min="1"
                                   max="20"
                                   x-model.number="laserParams.passes"
                                   @change="persistLaserParams()"
                                   class="w-full mt-0.5 font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Bitmap</label>
                            <select x-model="laserParams.bitmap_mode"
                                    @change="persistLaserParams()"
                                    class="w-full mt-0.5 text-xs font-bold bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none cursor-pointer">
                                @foreach($bitmapOptions as $bmpOpt)
                                    <option value="{{ $bmpOpt }}">{{ $bmpOpt }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Richtung</label>
                            <select x-model="laserParams.engrave_mode"
                                    @change="persistLaserParams()"
                                    class="w-full mt-0.5 text-xs font-bold bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none cursor-pointer">
                                @foreach($engraveModeOptions as $engOpt)
                                    <option value="{{ $engOpt }}">{{ $engOpt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Zeile 5: Vektortext / Zusatzparameter + Komfort-Aktionen (mobil gestapelt, erst ab 2xl nebeneinander) --}}
                    <div data-sf-laser-action-row class="flex flex-col 2xl:flex-row 2xl:items-end gap-2 pt-0.5 w-full min-w-0">
                        <div class="bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800 w-full min-w-0 2xl:flex-1">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-gray-400">Vektortext</label>
                            <input type="text"
                                   placeholder="z. B. 80 % · 200 mm/s · 60 kHz"
                                   x-model="laserParams.vector_note"
                                   @change="persistLaserParams()"
                                   class="w-full min-w-0 mt-0.5 font-mono text-xs font-bold bg-transparent text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none">
                        </div>

                        <div data-sf-laser-action-buttons class="flex flex-wrap items-stretch gap-1.5 w-full min-w-0 2xl:w-auto 2xl:justify-end">
                            <button type="button"
                                    wire:click="resetOrderItemLaserParameters('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="resetOrderItemLaserParameters,resetLaserParameters"
                                    class="sf-laser-action-btn flex-1 basis-full sm:basis-auto min-w-0 whitespace-normal text-center inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                <span>Standardwerte laden</span>
                            </button>

                            <button type="button"
                                    wire:click="saveOrderLaserParametersToProduct('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}', laserParams)"
                                    wire:loading.attr="disabled"
                                    wire:target="saveOrderLaserParametersToProduct"
                                    class="sf-laser-action-btn flex-1 basis-full sm:basis-auto min-w-0 whitespace-normal text-center inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                <span>Als Produkt-Standard speichern</span>
                            </button>

                            <button type="button"
                                    data-sf-laser-apply-btn
                                    @click.stop="persistLaserParams()"
                                    class="flex-1 basis-full sm:basis-auto min-w-0 whitespace-normal text-center inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-[var(--theme-color)] text-gray-950 border border-[var(--theme-color)] text-[9px] font-black uppercase tracking-wider hover:brightness-95 transition-all cursor-pointer">
                                <span>Übernehmen</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Schlanker 2-Schritte-Laser-Ablauf: 1. Kamera & KI-Ausrichten -> 2. An Laser senden --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                {{-- SCHRITT 1 --}}
                <button type="button"
                        wire:click="refreshLaserCameraAndAlign('{{ $cockpitData['side'] }}', laserParams)"
                        wire:loading.attr="disabled"
                        wire:target="refreshLaserCameraAndAlign,sendJobDirectlyToLaser"
                        class="sf-laser-action-btn flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-[10px] sm:text-[11px] font-black uppercase tracking-widest transition-all cursor-pointer disabled:opacity-70 disabled:cursor-wait {{ $wfStep === 1
                            ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                            : 'bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-800' }}">
                    <span wire:loading.remove wire:target="refreshLaserCameraAndAlign" class="inline-flex items-center justify-center gap-2">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 {{ $wfStep > 1 ? 'bg-[var(--theme-color)] text-gray-950' : ($wfStep === 1 ? 'bg-gray-950 text-[var(--theme-color)]' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300') }}">
                            {{ $wfStep > 1 ? '✓' : '1' }}
                        </span>
                        <span>Kamera &amp; KI-Ausrichten</span>
                    </span>
                    <span wire:loading wire:target="refreshLaserCameraAndAlign">
                        <span class="inline-flex items-center gap-2">
                            <svg class="w-5 h-5 shrink-0 sf-laser-btn-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.75" opacity="0.25"></circle>
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.75" stroke-linecap="round"></path>
                            </svg>
                            <span>Richte aus...</span>
                        </span>
                    </span>
                </button>

                {{-- SCHRITT 2 --}}
                <button type="button"
                        wire:click="sendJobDirectlyToLaser('{{ $previewItem->id }}', '{{ $cockpitData['side'] }}', laserParams)"
                        wire:loading.attr="disabled"
                        wire:target="refreshLaserCameraAndAlign,sendJobDirectlyToLaser"
                        class="sf-laser-action-btn flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl border text-[10px] sm:text-[11px] font-black uppercase tracking-widest transition-all cursor-pointer disabled:opacity-70 disabled:cursor-wait {{ $wfStep === 2
                            ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm'
                            : ($wfStep >= 3
                                ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-40)]'
                                : 'bg-white dark:bg-gray-900 hover:border-[var(--theme-color)] text-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-800') }}">
                    <span wire:loading.remove wire:target="sendJobDirectlyToLaser" class="inline-flex items-center justify-center gap-2">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 {{ $wfStep >= 3 ? 'bg-[var(--theme-color)] text-gray-950' : ($wfStep === 2 ? 'bg-gray-950 text-[var(--theme-color)]' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300') }}">
                            {{ $wfStep >= 3 ? '✓' : '2' }}
                        </span>
                        <span>An Laser senden</span>
                    </span>
                    <span wire:loading wire:target="sendJobDirectlyToLaser">
                        <span class="inline-flex items-center gap-2">
                            <svg class="w-5 h-5 shrink-0 sf-laser-btn-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.75" opacity="0.25"></circle>
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.75" stroke-linecap="round"></path>
                            </svg>
                            <span>Sende an Laser...</span>
                        </span>
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif
