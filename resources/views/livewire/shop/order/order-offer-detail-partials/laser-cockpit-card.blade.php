@php
    $cockpitData = ($previewItem && $previewItem->product && $previewItem->product->isPhysical() && empty($isDigitalItem) && empty($isService) && method_exists($this, 'getLaserCockpitDataProperty'))
        ? $this->laserCockpitData
        : null;
@endphp

@if($cockpitData)
    @php
        $fmtDim = static function (float $v): string {
            if (abs($v - round($v)) < 0.005) {
                return number_format($v, 0, ',', '.');
            }
            if (abs(($v * 10.0) - round($v * 10.0)) < 0.05) {
                return number_format($v, 1, ',', '.');
            }

            return number_format($v, 2, ',', '.');
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
        $productProfiles = is_array($cockpitData['product_profiles'] ?? null)
            ? $cockpitData['product_profiles']
            : $previewItem->product->getLaserProfiles();

        $calcServerPct = static function ($val, float $min, float $max): string {
            if ($max <= $min) {
                return '0.00';
            }
            $clamped = max($min, min($max, (float) $val));
            return number_format((($clamped - $min) / ($max - $min)) * 100.0, 2, '.', '');
        };

        $initDotUs = max(1, min(3000, (int) ($lp['dot_duration_us'] ?? 120)));
        $initPowerPctVal = max(1, min(100, (int) ($lp['power_percent'] ?? 70)));
        $initDpiVal = max(50, min(2540, (int) ($lp['dpi'] ?? 500)));
        $initFreqVal = max(1, min(200, (int) ($lp['frequency_khz'] ?? 60)));

        $initDotBarPct = $calcServerPct($initDotUs, 1, 3000);
        $initPowerBarPct = $calcServerPct($initPowerPctVal, 1, 100);
        $initDpiBarPct = $calcServerPct($initDpiVal, 50, 2540);
        $initFreqBarPct = $calcServerPct($initFreqVal, 1, 200);

        $initFrameLeftPct = number_format(((float) $cockpitData['center_x_mm'] / 200.0) * 100.0, 4, '.', '');
        $initFrameTopPct = number_format(((float) $cockpitData['center_y_mm'] / 200.0) * 100.0, 4, '.', '');
        $initFrameWidthPct = number_format(((float) $cockpitData['width_mm'] / 200.0) * 100.0, 4, '.', '');
        $initFrameHeightPct = number_format(((float) $cockpitData['height_mm'] / 200.0) * 100.0, 4, '.', '');
        $initFrameAngle = number_format((float) $cockpitData['angle_deg'], 2, '.', '');

        $cockpitBootPayload = [
            'xMm'                   => (float) $cockpitData['center_x_mm'],
            'yMm'                   => (float) $cockpitData['center_y_mm'],
            'angleDeg'              => (float) $cockpitData['angle_deg'],
            'wMm'                   => (float) $cockpitData['width_mm'],
            'hMm'                   => (float) $cockpitData['height_mm'],
            'defaultWMm'            => (float) $cockpitData['width_mm'],
            'defaultHMm'            => (float) $cockpitData['height_mm'],
            'hasLaserOverrides'     => (bool) ($lp['has_overrides'] ?? false),
            'equipmentDefaultModes' => $equipmentDefaultProcessModes,
            'handbookPresets'       => $handbookPresets,
            'productProfiles'       => array_values($productProfiles),
            'laserParams'           => [
                'equipment_key'   => (string) ($lp['equipment_key'] ?? 'flatbed_standard'),
                'process_mode'    => (string) ($lp['process_mode'] ?? 'Prozess auf flacher Oberfläche'),
                'material'        => (string) ($lp['material'] ?? 'Edelstahl'),
                'focus_mm'        => (float) ($lp['focus_mm'] ?? 3.0),
                'element_type'    => (string) ($lp['element_type'] ?? 'Bild'),
                'engrave_tab'     => (string) ($lp['engrave_tab'] ?? 'Gravieren'),
                'preset_mode'     => (string) ($lp['preset_mode'] ?? 'Benutzerdefiniert'),
                'dot_duration_us' => $initDotUs,
                'power_percent'   => $initPowerPctVal,
                'speed_mms'       => (int) ($lp['speed_mms'] ?? 200),
                'dpi'             => $initDpiVal,
                'passes'          => (int) ($lp['passes'] ?? 1),
                'frequency_khz'   => $initFreqVal,
                'bitmap_mode'     => (string) ($lp['bitmap_mode'] ?? 'Graustufen'),
                'engrave_mode'    => (string) ($lp['engrave_mode'] ?? 'Bidirektional'),
                'vector_note'     => (string) ($lp['vector_note'] ?? ''),
            ],
        ];
    @endphp
    <div x-data="{
            itemId: '{{ $previewItem->id }}',
            sideKey: '{{ $cockpitSideJs }}',
            openScopeKey: '{{ $openScopeKey }}',
            equipmentDefaultModes: {},
            dragging: false,
            dragMode: 'move',
            _hasMovedDuringDrag: false,
            _dragStageRect: null,
            _coordSeq: 0,
            _lockedCoords: null,
            _paramSeq: 0,
            _activeSliderKey: null,
            startClientX: 0,
            startClientY: 0,
            lastClientX: 0,
            lastClientY: 0,
            startXMm: 100,
            startYMm: 100,
            startAngleDeg: 0,
            startWMm: 40,
            startHMm: 40,
            defaultWMm: 40,
            defaultHMm: 40,
            startResizeDistPx: 40,
            lastResizeDistPx: 40,
            _rawXMm: 100,
            _rawYMm: 100,
            _rawAngleDeg: 0,
            _rawWMm: 40,
            _rawHMm: 40,
            shiftHeld: false,
            _keyPersistTimer: null,
            xMm: 100,
            yMm: 100,
            angleDeg: 0,
            wMm: 40,
            hMm: 40,
            cockpitZoom: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? Number(window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].zoom || 1) : 1,
            cockpitPanX: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? Number(window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].panX || 0) : 0,
            cockpitPanY: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? Number(window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].panY || 0) : 0,
            _zoomFocusX: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].focusX : null,
            _zoomFocusY: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}']) ? window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].focusY : null,
            bedBgDim: (window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}'] && window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].bgDim !== undefined) ? Number(window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].bgDim || 0) : 0,
            highContrastOverlay: Boolean(window.__sfLaserCockpitZoom && window.__sfLaserCockpitZoom['{{ $openScopeKey }}'] && window.__sfLaserCockpitZoom['{{ $openScopeKey }}'].highContrast),
            startPanX: 0,
            startPanY: 0,
            isDraggingMinimap: false,
            _minimapOffsetX: 0,
            _minimapOffsetY: 0,
            showLaserParams: window.__sfLaserParamsOpen === '{{ $openScopeKey }}',
            hasLaserOverrides: false,
            productProfiles: [],
            handbookPresets: [],
            handbookPresetKey: '',
            showNewProfileForm: false,
            newProfileTitle: '',
            laserParams: {
                equipment_key: 'flatbed_standard',
                process_mode: 'Prozess auf flacher Oberfläche',
                material: 'Edelstahl',
                focus_mm: 21.5,
                element_type: 'Bild',
                engrave_tab: 'Gravieren',
                preset_mode: 'Benutzerdefiniert',
                dot_duration_us: 120,
                power_percent: 70,
                speed_mms: 200,
                dpi: 500,
                passes: 1,
                frequency_khz: 60,
                bitmap_mode: 'Graustufen',
                engrave_mode: 'Bidirektional',
                vector_note: ''
            },
            init() {
                this.bootFromDataset();
                this.$nextTick(() => {
                    this.refreshAllRangeFills();
                });
                if (typeof MutationObserver !== 'undefined') {
                    const obs = new MutationObserver(() => {
                        if (this.$refs.cockpitBootPayload) {
                            const raw = this.$refs.cockpitBootPayload.getAttribute('data-boot');
                            if (raw && raw.startsWith('{')) {
                                const parsed = JSON.parse(raw);
                                if (Array.isArray(parsed.productProfiles)) {
                                    this.productProfiles = parsed.productProfiles;
                                }
                            }
                        }
                        if (this._activeSliderKey === null) {
                            this.refreshAllRangeFills();
                        }
                    });
                    obs.observe(this.$el, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-boot'] });
                }
            },
            bootFromDataset() {
                const el = this.$refs.cockpitBootPayload;
                if (!el) return;
                const raw = el.getAttribute('data-boot');
                if (!raw || !raw.startsWith('{')) return;
                const d = JSON.parse(raw);
                this.equipmentDefaultModes = d.equipmentDefaultModes || {};
                this.handbookPresets = Array.isArray(d.handbookPresets) ? d.handbookPresets : [];
                this.productProfiles = Array.isArray(d.productProfiles) ? d.productProfiles : [];
                this.defaultWMm = Number(d.defaultWMm) || 40;
                this.defaultHMm = Number(d.defaultHMm) || 40;
                this.startWMm = this.defaultWMm;
                this.startHMm = this.defaultHMm;
                this.xMm = Number(d.xMm) || 100;
                this.yMm = Number(d.yMm) || 100;
                this.angleDeg = Number(d.angleDeg) || 0;
                this.wMm = Number(d.wMm) || this.defaultWMm;
                this.hMm = Number(d.hMm) || this.defaultHMm;
                this._rawXMm = this.xMm;
                this._rawYMm = this.yMm;
                this._rawAngleDeg = this.angleDeg;
                this._rawWMm = this.wMm;
                this._rawHMm = this.hMm;
                this.hasLaserOverrides = Boolean(d.hasLaserOverrides);
                if (d.laserParams && typeof d.laserParams === 'object') {
                    this.applyParamObjectLocally(d.laserParams);
                }
            },
            applyParamObjectLocally(next) {
                if (!next || typeof next !== 'object') return;
                if (next.equipment_key !== undefined) this.laserParams.equipment_key = String(next.equipment_key);
                if (next.process_mode !== undefined) this.laserParams.process_mode = String(next.process_mode);
                if (next.material !== undefined) this.laserParams.material = String(next.material);
                if (next.focus_mm !== undefined) this.laserParams.focus_mm = Number(next.focus_mm);
                if (next.element_type !== undefined) this.laserParams.element_type = String(next.element_type);
                if (next.engrave_tab !== undefined) this.laserParams.engrave_tab = String(next.engrave_tab);
                if (next.preset_mode !== undefined) this.laserParams.preset_mode = String(next.preset_mode);
                if (next.dot_duration_us !== undefined) {
                    this.laserParams.dot_duration_us = Math.max(1, Math.min(3000, Math.round(Number(next.dot_duration_us) || 1)));
                }
                if (next.power_percent !== undefined) {
                    this.laserParams.power_percent = Math.max(1, Math.min(100, Math.round(Number(next.power_percent) || 1)));
                }
                if (next.speed_mms !== undefined) {
                    this.laserParams.speed_mms = Math.max(1, Math.min(4000, Math.round(Number(next.speed_mms) || 200)));
                }
                if (next.dpi !== undefined) {
                    this.laserParams.dpi = Math.max(50, Math.min(2540, Math.round(Number(next.dpi) || 500)));
                }
                if (next.passes !== undefined) {
                    this.laserParams.passes = Math.max(1, Math.min(20, Math.round(Number(next.passes) || 1)));
                }
                if (next.frequency_khz !== undefined) {
                    this.laserParams.frequency_khz = Math.max(1, Math.min(200, Math.round(Number(next.frequency_khz) || 60)));
                }
                if (next.bitmap_mode !== undefined) this.laserParams.bitmap_mode = String(next.bitmap_mode);
                if (next.engrave_mode !== undefined) this.laserParams.engrave_mode = String(next.engrave_mode);
                if (next.vector_note !== undefined) this.laserParams.vector_note = String(next.vector_note);
            },
            calcRangePct(val, min, max) {
                const lo = Number(min);
                const hi = Number(max);
                if (!Number.isFinite(hi) || hi <= lo) return 0;
                const parsed = Number(val);
                const clamped = Number.isFinite(parsed) ? Math.max(lo, Math.min(hi, parsed)) : lo;
                return Number((((clamped - lo) / (hi - lo)) * 100).toFixed(2));
            },
            rangeFillStyle(val, min, max) {
                return `--val-pct: ${this.calcRangePct(val, min, max)}%;`;
            },
            refreshAllRangeFills() {
                if (!this.$el) return;
                const ranges = this.$el.querySelectorAll('[data-sf-param-range]');
                ranges.forEach((el) => {
                    const key = el.getAttribute('data-sf-param-range');
                    if (!key || this.laserParams[key] === undefined) return;
                    const min = Number(el.getAttribute('min') || 1);
                    const max = Number(el.getAttribute('max') || 100);
                    const val = Math.max(min, Math.min(max, Number(this.laserParams[key]) || min));
                    if (String(el.value) !== String(val)) {
                        el.value = String(val);
                    }
                    const pct = this.calcRangePct(val, min, max);
                    el.style.setProperty('--val-pct', pct + '%');
                });
            },
            beginSliderDrag(key) {
                this._activeSliderKey = key;
            },
            onSliderInput(key, rawVal, min, max, el) {
                this._activeSliderKey = key;
                const clamped = Math.max(min, Math.min(max, Math.round(Number(rawVal) || min)));
                this.laserParams[key] = clamped;
                this._paramSeq++;
                if (el && el.style) {
                    el.style.setProperty('--val-pct', this.calcRangePct(clamped, min, max) + '%');
                }
            },
            onSliderCommit(key, rawVal, min, max, el) {
                const clamped = Math.max(min, Math.min(max, Math.round(Number(rawVal) || min)));
                this.laserParams[key] = clamped;
                this._activeSliderKey = null;
                if (el && el.style) {
                    el.style.setProperty('--val-pct', this.calcRangePct(clamped, min, max) + '%');
                }
                this.persistLaserParams();
            },
            onNumericParamChange(key, min, max) {
                const clamped = Math.max(min, Math.min(max, Math.round(Number(this.laserParams[key]) || min)));
                this.laserParams[key] = clamped;
                this.refreshAllRangeFills();
                this.persistLaserParams();
            },
            get frameCenterX() {
                return Math.max(0, Math.min(100, ((Number(this.xMm) || 100) / 200) * 100));
            },
            get frameCenterY() {
                return Math.max(0, Math.min(100, ((Number(this.yMm) || 100) / 200) * 100));
            },
            getBlueFrameStyle() {
                const active = this._lockedCoords || { x: this.xMm, y: this.yMm, angle: this.angleDeg, w: this.wMm, h: this.hMm };
                const lx = ((Number(active.x) || 100) / 200) * 100;
                const ty = ((Number(active.y) || 100) / 200) * 100;
                const wd = ((Number(active.w) || this.defaultWMm) / 200) * 100;
                const ht = ((Number(active.h) || this.defaultHMm) / 200) * 100;
                const ang = Number(active.angle) || 0;
                return `left: ${lx}%; top: ${ty}%; width: ${wd}%; height: ${ht}%; transform: translate(-50%, -50%) rotate(${ang}deg);`;
            },
            saveCockpitZoomScope() {
                window.__sfLaserCockpitZoom = window.__sfLaserCockpitZoom || {};
                window.__sfLaserCockpitZoom[this.openScopeKey] = {
                    zoom: this.cockpitZoom,
                    panX: this.cockpitPanX,
                    panY: this.cockpitPanY,
                    focusX: this._zoomFocusX,
                    focusY: this._zoomFocusY,
                    bgDim: this.bedBgDim,
                    highContrast: this.highContrastOverlay
                };
            },
            setBedBgDim(val) {
                this.bedBgDim = Math.max(0, Math.min(65, Math.round(Number(val) || 0)));
                this.saveCockpitZoomScope();
            },
            toggleHighContrastOverlay() {
                this.highContrastOverlay = !this.highContrastOverlay;
                this.saveCockpitZoomScope();
            },
            getCameraBgFilterStyle() {
                const dim = Math.max(0, Math.min(65, Number(this.bedBgDim) || 0));
                if (dim <= 0) return '';
                const br = (1 - (dim / 100)).toFixed(3);
                const ct = (1 + (dim / 220)).toFixed(3);
                return `filter: brightness(${br}) contrast(${ct});`;
            },
            getOverlayContrastStyle() {
                if (!this.highContrastOverlay) return '';
                return 'filter: brightness(0.18) sepia(1) hue-rotate(165deg) saturate(5) drop-shadow(0 0 1px #fff); opacity: 0.95;';
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
            toggleLaserParams() {
                this.showLaserParams = !this.showLaserParams;
                window.__sfLaserParamsOpen = this.showLaserParams ? this.openScopeKey : null;
                if (this.showLaserParams) {
                    this.$nextTick(() => this.refreshAllRangeFills());
                }
            },
            applyHandbookPreset() {
                const rawKey = String(this.handbookPresetKey || '');
                if (!rawKey) return;

                if (rawKey.startsWith('profile:')) {
                    const profId = rawKey.slice(8);
                    const prof = this.productProfiles.find(p => String(p.id) === profId);
                    if (prof) {
                        this.applyParamObjectLocally(prof);
                        if (prof.equipment_key && this.equipmentDefaultModes[prof.equipment_key] !== undefined && !prof.process_mode) {
                            this.laserParams.process_mode = this.equipmentDefaultModes[prof.equipment_key] || this.laserParams.process_mode;
                        }
                        this.hasLaserOverrides = true;
                        const seq = ++this._paramSeq;
                        this.$nextTick(() => this.refreshAllRangeFills());
                        const res = $wire.applyProductLaserProfile(this.itemId, this.sideKey, profId, seq);
                        if (res && typeof res.then === 'function') {
                            res.then(() => this.$nextTick(() => this.refreshAllRangeFills()));
                        }
                    }
                    return;
                }

                const preset = this.handbookPresets.find(p => p.key === rawKey);
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
                this.laserParams.power_percent = Math.max(1, Math.min(100, Number(main.power_percent) || 1));
                this.laserParams.frequency_khz = Math.max(1, Math.min(200, Number(main.frequency_khz) || 40));
                this.laserParams.passes = Math.max(1, Math.min(20, Number(main.passes) || 1));
                this.laserParams.dpi = Math.max(50, Math.min(2540, Number(main.dpi) || 500));
                if (bmp) {
                    this.laserParams.element_type = 'Bild';
                    this.laserParams.dot_duration_us = Math.max(1, Math.min(3000, Number(bmp.dot_duration_us) || 120));
                    this.laserParams.bitmap_mode = String(bmp.bitmap_mode || 'Jarvis');
                } else {
                    this.laserParams.element_type = 'Vektor';
                    this.laserParams.bitmap_mode = 'Schwellenwert';
                }
                if (vec) {
                    this.laserParams.speed_mms = Math.max(1, Math.min(4000, Number(vec.speed_mms) || 200));
                    this.laserParams.vector_note = `Gravieren · ${vec.power_percent} % · ${vec.speed_mms} mm/s · ${vec.lpc} Linien/cm · ${vec.frequency_khz} kHz` + (Number(vec.passes) > 1 ? ` · ${vec.passes}x` : '');
                }
                this.laserParams.preset_mode = 'Referenz';
                this.refreshAllRangeFills();
                this.persistLaserParams();
            },
            saveCurrentAsProductProfile() {
                const title = String(this.newProfileTitle || '').trim();
                if (!title) return;
                this._paramSeq++;
                const payload = { ...this.laserParams };
                this.showNewProfileForm = false;
                this.newProfileTitle = '';
                const res = $wire.saveOrderLaserParametersAsProductProfile(this.itemId, this.sideKey, title, payload, false);
                if (res && typeof res.then === 'function') {
                    res.then(() => this.$nextTick(() => this.refreshAllRangeFills()));
                }
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
                const seq = ++this._paramSeq;
                this.refreshAllRangeFills();
                const res = $wire.updateLaserParameters(this.itemId, this.sideKey, { ...this.laserParams }, seq);
                if (res && typeof res.then === 'function') {
                    res.then(() => this.$nextTick(() => this.refreshAllRangeFills()));
                }
            },
            syncLaserParamsFromServer(detail) {
                const p = Array.isArray(detail) ? (detail[0] || {}) : (detail || {});
                if (Array.isArray(p.profiles)) {
                    this.productProfiles = p.profiles;
                }
                if (p.active_profile_id) {
                    this.handbookPresetKey = 'profile:' + String(p.active_profile_id);
                }
                if (this._activeSliderKey !== null) {
                    return;
                }
                if (p.seq !== undefined && p.seq !== null && Number(p.seq) < this._paramSeq) {
                    return;
                }
                const next = p.params || null;
                if (!next) return;
                this.applyParamObjectLocally(next);
                if (next.has_overrides !== undefined) {
                    this.hasLaserOverrides = Boolean(next.has_overrides);
                }
                this.$nextTick(() => this.refreshAllRangeFills());
            },
            fmtMm(v) {
                const parsed = typeof v === 'string' ? parseFloat(v.replace(',', '.')) : Number(v);
                const n = Number.isFinite(parsed) ? parsed : 0;
                if (Math.abs(n - Math.round(n)) < 0.005) {
                    return Math.round(n).toString();
                }
                if (Math.abs((n * 10) - Math.round(n * 10)) < 0.05) {
                    return n.toFixed(1).replace('.', ',');
                }
                return n.toFixed(2).replace('.', ',');
            },
            syncCoordsFromServer(detail) {
                if (this.dragging) return;
                const p = Array.isArray(detail) ? (detail[0] || {}) : (detail || {});
                if (p.source === 'ack') {
                    const ackSeq = Number(p.seq || 0);
                    if (ackSeq < this._coordSeq) {
                        return;
                    }
                    if (ackSeq === this._coordSeq) {
                        this._lockedCoords = null;
                    }
                    return;
                }
                this._lockedCoords = null;
                if (p.x !== undefined) { this.xMm = Number(p.x); this._rawXMm = this.xMm; }
                if (p.y !== undefined) { this.yMm = Number(p.y); this._rawYMm = this.yMm; }
                if (p.angle !== undefined) { this.angleDeg = Number(p.angle); this._rawAngleDeg = this.angleDeg; }
                if (p.w !== undefined) { this.wMm = Number(p.w); this._rawWMm = this.wMm; }
                if (p.h !== undefined) { this.hMm = Number(p.h); this._rawHMm = this.hMm; }
                if (this.cockpitZoom > 1.01) {
                    this._zoomFocusX = this.frameCenterX;
                    this._zoomFocusY = this.frameCenterY;
                    this.setCockpitZoom(this.cockpitZoom);
                }
            },
            lockCurrentCoords() {
                const seq = ++this._coordSeq;
                this._lockedCoords = {
                    seq: seq,
                    x: Number(this.xMm) || 100,
                    y: Number(this.yMm) || 100,
                    angle: Number(this.angleDeg) || 0,
                    w: Number(this.wMm) || this.defaultWMm,
                    h: Number(this.hMm) || this.defaultHMm
                };
                return seq;
            },
            persistCoords() {
                if (this._keyPersistTimer) {
                    clearTimeout(this._keyPersistTimer);
                    this._keyPersistTimer = null;
                }
                const seq = this.lockCurrentCoords();
                $wire.updateLaserCoordinates(
                    this.itemId,
                    this._lockedCoords.x,
                    this._lockedCoords.y,
                    this._lockedCoords.angle,
                    this._lockedCoords.w,
                    this._lockedCoords.h,
                    seq
                );
            },
            debouncedPersistCoords(delayMs = 250) {
                if (this._keyPersistTimer) {
                    clearTimeout(this._keyPersistTimer);
                }
                this._keyPersistTimer = setTimeout(() => {
                    this._keyPersistTimer = null;
                    this.persistCoords();
                }, delayMs);
            },
            nudge(dx, dy, da, fineMode = false) {
                const scale = fineMode ? 0.04 : 1.0;
                this._rawXMm = Math.max(5, Math.min(195, (Number(this.xMm) || 100) + dx * scale));
                this._rawYMm = Math.max(5, Math.min(195, (Number(this.yMm) || 100) + dy * scale));
                this._rawAngleDeg = Math.max(-45, Math.min(45, (Number(this.angleDeg) || 0) + da * scale));
                this.xMm = Math.round(this._rawXMm * 100) / 100;
                this.yMm = Math.round(this._rawYMm * 100) / 100;
                this.angleDeg = Math.round(this._rawAngleDeg * 100) / 100;
                this.persistCoords();
            },
            handleViewportKeydown(e) {
                const tag = (e.target && e.target.tagName) ? String(e.target.tagName).toUpperCase() : '';
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                    return;
                }
                if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                    return;
                }
                e.preventDefault();
                const fineMode = !!e.shiftKey;
                this.shiftHeld = fineMode;
                const step = fineMode ? 0.02 : 0.20;
                let dx = 0;
                let dy = 0;
                if (e.key === 'ArrowLeft') dx = -step;
                else if (e.key === 'ArrowRight') dx = step;
                else if (e.key === 'ArrowUp') dy = -step;
                else if (e.key === 'ArrowDown') dy = step;

                this._rawXMm = Math.max(5, Math.min(195, (Number.isFinite(Number(this._rawXMm)) ? Number(this._rawXMm) : (Number(this.xMm) || 100)) + dx));
                this._rawYMm = Math.max(5, Math.min(195, (Number.isFinite(Number(this._rawYMm)) ? Number(this._rawYMm) : (Number(this.yMm) || 100)) + dy));
                this.xMm = Math.round(this._rawXMm * 100) / 100;
                this.yMm = Math.round(this._rawYMm * 100) / 100;
                this.lockCurrentCoords();
                this.debouncedPersistCoords(250);
            },
            startDrag(e, mode = 'move') {
                if (e.button !== undefined && e.button !== 0) return;
                if (this.$refs.bedViewport && typeof this.$refs.bedViewport.focus === 'function') {
                    this.$refs.bedViewport.focus({ preventScroll: true });
                }
                this.dragging = true;
                this.dragMode = mode;
                this._hasMovedDuringDrag = false;
                this.shiftHeld = !!e.shiftKey;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                this.startClientX = clientX;
                this.startClientY = clientY;
                this.lastClientX = clientX;
                this.lastClientY = clientY;
                this.startPanX = Number(this.cockpitPanX) || 0;
                this.startPanY = Number(this.cockpitPanY) || 0;
                this.startXMm = Number(this.xMm) || 100;
                this.startYMm = Number(this.yMm) || 100;
                this.startAngleDeg = Number(this.angleDeg) || 0;
                this.startWMm = Math.max(2.5, Number(this.wMm) || this.defaultWMm);
                this.startHMm = Math.max(2.5, Number(this.hMm) || this.defaultHMm);
                this._rawXMm = this.startXMm;
                this._rawYMm = this.startYMm;
                this._rawAngleDeg = this.startAngleDeg;
                this._rawWMm = this.startWMm;
                this._rawHMm = this.startHMm;
                this._dragStageRect = null;
                if (this.$refs.bedStage) {
                    const rect = this.$refs.bedStage.getBoundingClientRect();
                    if (rect.width && rect.height) {
                        this._dragStageRect = {
                            left: rect.left,
                            top: rect.top,
                            width: rect.width,
                            height: rect.height
                        };
                        if (mode === 'resize') {
                            const centerX = rect.left + (this.startXMm / 200) * rect.width;
                            const centerY = rect.top + (this.startYMm / 200) * rect.height;
                            this.startResizeDistPx = Math.max(4, Math.hypot(clientX - centerX, clientY - centerY));
                            this.lastResizeDistPx = this.startResizeDistPx;
                        }
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
                const fineMode = !!e.shiftKey;
                this.shiftHeld = fineMode;

                if (this.dragMode === 'pan') {
                    const z = Math.max(1.01, Number(this.cockpitZoom || 1.01));
                    const stageRect = this._dragStageRect || (this.$refs.bedStage ? this.$refs.bedStage.getBoundingClientRect() : null);
                    if (!stageRect || !stageRect.width || !stageRect.height) return;
                    const unscaledW = stageRect.width / z;
                    const unscaledH = stageRect.height / z;
                    const maxPan = 50 * (z - 1);
                    const dPanX = ((clientX - this.startClientX) / Math.max(1, unscaledW)) * 100;
                    const dPanY = ((clientY - this.startClientY) / Math.max(1, unscaledH)) * 100;
                    this.cockpitPanX = Number(Math.max(-maxPan, Math.min(maxPan, this.startPanX + dPanX)).toFixed(2));
                    this.cockpitPanY = Number(Math.max(-maxPan, Math.min(maxPan, this.startPanY + dPanY)).toFixed(2));
                    this._zoomFocusX = Number((50 - this.cockpitPanX / z).toFixed(2));
                    this._zoomFocusY = Number((50 - this.cockpitPanY / z).toFixed(2));
                    this.lastClientX = clientX;
                    this.lastClientY = clientY;
                    this.saveCockpitZoomScope();
                    return;
                }

                if (!this.$refs.bedStage && !this._dragStageRect) return;
                const rect = this._dragStageRect || this.$refs.bedStage.getBoundingClientRect();
                if (!rect.width || !rect.height) return;

                if (Math.hypot(clientX - this.startClientX, clientY - this.startClientY) < 0.25 && !this._hasMovedDuringDrag) {
                    return;
                }
                this._hasMovedDuringDrag = true;

                const prevX = this.lastClientX;
                const prevY = this.lastClientY;
                const frameDx = clientX - prevX;
                const frameDy = clientY - prevY;
                this.lastClientX = clientX;
                this.lastClientY = clientY;
                const gear = fineMode ? 0.15 : 1.0;

                if (this.dragMode === 'rotate') {
                    const centerX = rect.left + (this._rawXMm / 200) * rect.width;
                    const centerY = rect.top + (this._rawYMm / 200) * rect.height;
                    const a0 = Math.atan2(prevY - centerY, prevX - centerX) * (180 / Math.PI);
                    const a1 = Math.atan2(clientY - centerY, clientX - centerX) * (180 / Math.PI);
                    let deltaAngle = a1 - a0;
                    while (deltaAngle > 180) deltaAngle -= 360;
                    while (deltaAngle < -180) deltaAngle += 360;
                    this._rawAngleDeg = Math.max(-45, Math.min(45, this._rawAngleDeg + (deltaAngle * gear)));
                    this.angleDeg = Math.round(this._rawAngleDeg * 100) / 100;
                    this.lockCurrentCoords();
                    return;
                }

                if (this.dragMode === 'resize') {
                    const centerX = rect.left + (this._rawXMm / 200) * rect.width;
                    const centerY = rect.top + (this._rawYMm / 200) * rect.height;
                    const currDistPx = Math.max(4, Math.hypot(clientX - centerX, clientY - centerY));
                    const rawRatio = currDistPx / Math.max(4, this.lastResizeDistPx);
                    const dampedRatio = 1 + ((rawRatio - 1) * gear);
                    this.lastResizeDistPx = currDistPx;
                    let nextW = this._rawWMm * dampedRatio;
                    let nextH = this._rawHMm * dampedRatio;
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
                    this._rawWMm = Math.max(2.5, Math.min(195, nextW));
                    this._rawHMm = Math.max(2.5, Math.min(195, nextH));
                    this.wMm = Math.round(this._rawWMm * 100) / 100;
                    this.hMm = Math.round(this._rawHMm * 100) / 100;
                    this.lockCurrentCoords();
                    return;
                }

                const stepDxMm = (frameDx / rect.width) * 200 * gear;
                const stepDyMm = (frameDy / rect.height) * 200 * gear;
                this._rawXMm = Math.max(5, Math.min(195, this._rawXMm + stepDxMm));
                this._rawYMm = Math.max(5, Math.min(195, this._rawYMm + stepDyMm));
                this.xMm = Math.round(this._rawXMm * 100) / 100;
                this.yMm = Math.round(this._rawYMm * 100) / 100;
                this.lockCurrentCoords();
            },
            stopDrag() {
                if (this.isDraggingMinimap) {
                    this.isDraggingMinimap = false;
                    return;
                }
                if (!this.dragging) return;
                const wasPan = this.dragMode === 'pan';
                const didMove = this._hasMovedDuringDrag;
                this.dragging = false;
                this._dragStageRect = null;
                this._hasMovedDuringDrag = false;
                if (!wasPan && didMove) {
                    this.persistCoords();
                }
            }
         }"
         @laser-cockpit-sync.window="syncCoordsFromServer($event.detail)"
         @laser-params-sync.window="syncLaserParamsFromServer($event.detail)"
         @keydown.window="if ($event.key === 'Shift') shiftHeld = true"
         @keyup.window="if ($event.key === 'Shift') shiftHeld = false"
         @blur.window="shiftHeld = false"
         wire:key="laser-cockpit-{{ $previewItem->id }}-{{ $cockpitData['side'] }}"
         class="bg-white dark:bg-gray-900/90 rounded-[1.5rem] sm:rounded-[2rem] border border-gray-200 dark:border-gray-800 shrink-0 relative">

        <span x-ref="cockpitBootPayload"
              class="hidden"
              data-boot="{{ json_encode($cockpitBootPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></span>

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
                    <span class="text-[9px] font-mono font-bold text-gray-500 dark:text-gray-400">
                        {{ $cockpitData['camera_updated_at'] }}
                    </span>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6 space-y-3 sm:space-y-4">
            {{-- Schlanke Zoom-Steuerleiste (100 % bis 400 %) + 0 ms Anti-Blend-Sichtbarkeitshilfe --}}
            <div data-cockpit-zoom-bar
                 @mousedown.stop
                 @touchstart.stop
                 class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-2.5 px-3 py-1.5 rounded-xl border w-full max-w-[460px] mx-auto bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-100 select-none">
                <div class="flex items-center gap-1.5 flex-1 min-w-[160px]">
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
                            class="min-w-[36px] text-right font-mono text-[10px] font-black tracking-tight transition-colors select-none shrink-0"
                            title="Zoom auf 100 % zurücksetzen">
                        <span x-text="Math.round(cockpitZoom * 100) + '%'">100%</span>
                    </button>
                </div>

                <div class="hidden sm:block h-4 w-px bg-gray-200 dark:bg-gray-800 shrink-0"></div>

                {{-- 0 ms Sichtbarkeits-Hilfe gegen Metall-Blendung: Abdunkeln (0-65 %) & Motiv-Kontrast --}}
                <div class="flex items-center justify-between sm:justify-end gap-1.5 w-full sm:w-auto shrink-0">
                    <label data-cockpit-bg-dimmer
                           title="Kamerabild abdunkeln gegen Metall-Spiegelungen (0 % bis 65 %)"
                           class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[9px] font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">
                        <span>Abdunkeln</span>
                        <input type="range"
                               min="0"
                               max="65"
                               step="5"
                               :value="bedBgDim"
                               @mousedown.stop
                               @touchstart.stop
                               @input.stop="setBedBgDim($event.target.value)"
                               class="w-12 h-1.5 accent-[var(--theme-color)] cursor-pointer">
                    </label>

                    <button type="button"
                            data-cockpit-overlay-contrast
                            @click.stop="toggleHighContrastOverlay()"
                            :class="highContrastOverlay
                                ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)]'
                                : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)]'"
                            title="Hochkontrast-Blueprint für helle Gravurschrift auf glänzendem Metall"
                            class="px-2 py-1 rounded-lg border text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer shrink-0">
                        Motiv-Kontrast
                    </button>
                </div>
            </div>

            {{-- 200x200 mm Live-Kamera-Arbeitsfläche (Zoom 100 %-400 %, Pan, Minimap, Shift 0,01 mm Fein-Getriebe) --}}
            <div x-ref="bedViewport"
                 tabindex="0"
                 data-cockpit-bed-viewport
                 @keydown="handleViewportKeydown($event)"
                 @wheel="handleCockpitWheel($event)"
                 @mousedown="startDrag($event, cockpitZoom > 1.01 ? 'pan' : 'move')"
                 @mousemove.window="moveDrag($event)"
                 @mouseup.window="stopDrag()"
                 @touchstart.passive="startDrag($event, cockpitZoom > 1.01 ? 'pan' : 'move')"
                 @touchmove.window.passive="moveDrag($event)"
                 @touchend.window="stopDrag()"
                 :class="[dragging || isDraggingMinimap ? 'cursor-grabbing ring-2 ring-cyan-400/60' : 'cursor-grab', cockpitZoom > 1.01 ? 'touch-none' : '']"
                 class="relative w-full max-w-[460px] mx-auto aspect-square rounded-2xl overflow-hidden border border-gray-300 dark:border-gray-700 bg-gray-950 select-none shadow-inner transition-shadow focus:outline-none focus:ring-2 focus:ring-[var(--theme-color-50)]">

                {{-- Gezoomter & gepannter 200x200 mm Kamera-Stage-Layer --}}
                <div x-ref="bedStage"
                     data-cockpit-bed-stage
                     class="absolute inset-0 w-full h-full"
                     :style="getCockpitStageTransformStyle()">

                    <img src="{{ $cockpitData['camera_url'] }}"
                         wire:key="laser-cam-img-{{ $previewItem->id }}-{{ $cockpitData['refresh_token'] ?? 0 }}"
                         alt="xTool F2 Ultra UV Kamera 200x200 mm"
                         :style="getCameraBgFilterStyle()"
                         class="w-full h-full object-cover pointer-events-none"
                         draggable="false">

                    {{-- Dezentrales 20 mm Lineal-Raster (200x200 mm) --}}
                    <div class="absolute inset-0 pointer-events-none opacity-20"
                         style="background-image: linear-gradient(to right, rgba(255,255,255,0.22) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.22) 1px, transparent 1px); background-size: 10% 10%;"></div>

                    {{-- Doppel-Kontrast-Rahmen (haarfeine dunkle Kontur + #00d8ff Cyan) + WYSIWYG-Motiv + Drehgriff + 4 Resize-Handles --}}
                    <div data-cockpit-blue-frame
                         @mousedown.stop.prevent="startDrag($event, 'move')"
                         @touchstart.stop.passive="startDrag($event, 'move')"
                         :class="dragging && dragMode === 'move' ? 'cursor-grabbing' : 'cursor-move'"
                         class="absolute z-10 pointer-events-auto touch-none {{ $isCircleContour ? 'rounded-full' : '' }}"
                         style="left: {{ $initFrameLeftPct }}%; top: {{ $initFrameTopPct }}%; width: {{ $initFrameWidthPct }}%; height: {{ $initFrameHeightPct }}%; transform: translate(-50%, -50%) rotate({{ $initFrameAngle }}deg);"
                         :style="getBlueFrameStyle()">
                        <svg viewBox="0 0 {{ $baseViewW }} {{ $baseViewH }}"
                             preserveAspectRatio="none"
                             class="w-full h-full overflow-visible pointer-events-none">
                            <path d="{{ $cockpitData['ghost_d_path'] }}"
                                  fill="none"
                                  stroke="rgba(15, 23, 42, 0.70)"
                                  stroke-width="1.45"
                                  vector-effect="non-scaling-stroke" />
                            <path d="{{ $cockpitData['ghost_d_path'] }}"
                                  fill="none"
                                  stroke="#00d8ff"
                                  stroke-width="0.85"
                                  stroke-dasharray="3.5,2.5"
                                  vector-effect="non-scaling-stroke" />
                        </svg>
                        <img src="{{ $cockpitData['preview_data_url'] }}"
                             alt="Gravur-Overlay"
                             :style="getOverlayContrastStyle()"
                             class="absolute inset-0 w-full h-full object-fill pointer-events-none {{ $isDarkSurfaceOverlay ? 'drop-shadow-[0_1px_2px_rgba(0,0,0,0.45)]' : 'drop-shadow-[0_0_1.5px_rgba(255,255,255,0.55)]' }}"
                             draggable="false">

                        {{-- Dreh-Griff (leicht über die Kerbe abgesetzt, per scale(1 / cockpitZoom) kompakt gehalten) --}}
                        <button type="button"
                                data-rotate-handle
                                @mousedown.stop.prevent="startDrag($event, 'rotate')"
                                @touchstart.stop.passive="startDrag($event, 'rotate')"
                                title="Gedrückt halten zum Drehen"
                                style="left: 50%; top: 0%; transform: translate(-50%, -85%) scale(1);"
                                :style="`left: 50%; top: 0%; transform: translate(-50%, -85%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-3.5 h-3.5 rounded-full bg-cyan-400 hover:bg-cyan-300 text-gray-950 border-[0.75px] border-gray-950 shadow-sm flex items-center justify-center text-[8px] leading-none font-black cursor-grab active:cursor-grabbing after:content-[''] after:absolute after:-inset-2">
                            &#8635;
                        </button>

                        {{-- 4 Proportionale Resize-Handles (NW, NE, SE, SW) dezent auf der blauen Kontur --}}
                        <button type="button"
                                data-resize-handle="nw"
                                @mousedown.stop.prevent="startDrag($event, 'resize')"
                                @touchstart.stop.passive="startDrag($event, 'resize')"
                                title="Größe ändern"
                                style="left: {{ $hPosMin }}; top: {{ $hPosMin }}; transform: translate(-50%, -50%) scale(1);"
                                :style="`left: {{ $hPosMin }}; top: {{ $hPosMin }}; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-2 h-2 rounded-full bg-cyan-400 hover:bg-cyan-300 border-[0.75px] border-gray-950 shadow-sm cursor-nwse-resize after:content-[''] after:absolute after:-inset-2"></button>
                        <button type="button"
                                data-resize-handle="ne"
                                @mousedown.stop.prevent="startDrag($event, 'resize')"
                                @touchstart.stop.passive="startDrag($event, 'resize')"
                                title="Größe ändern"
                                style="left: {{ $hPosMax }}; top: {{ $hPosMin }}; transform: translate(-50%, -50%) scale(1);"
                                :style="`left: {{ $hPosMax }}; top: {{ $hPosMin }}; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-2 h-2 rounded-full bg-cyan-400 hover:bg-cyan-300 border-[0.75px] border-gray-950 shadow-sm cursor-nesw-resize after:content-[''] after:absolute after:-inset-2"></button>
                        <button type="button"
                                data-resize-handle="se"
                                @mousedown.stop.prevent="startDrag($event, 'resize')"
                                @touchstart.stop.passive="startDrag($event, 'resize')"
                                title="Größe ändern"
                                style="left: {{ $hPosMax }}; top: {{ $hPosMax }}; transform: translate(-50%, -50%) scale(1);"
                                :style="`left: {{ $hPosMax }}; top: {{ $hPosMax }}; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-2 h-2 rounded-full bg-cyan-400 hover:bg-cyan-300 border-[0.75px] border-gray-950 shadow-sm cursor-nwse-resize after:content-[''] after:absolute after:-inset-2"></button>
                        <button type="button"
                                data-resize-handle="sw"
                                @mousedown.stop.prevent="startDrag($event, 'resize')"
                                @touchstart.stop.passive="startDrag($event, 'resize')"
                                title="Größe ändern"
                                style="left: {{ $hPosMin }}; top: {{ $hPosMax }}; transform: translate(-50%, -50%) scale(1);"
                                :style="`left: {{ $hPosMin }}; top: {{ $hPosMax }}; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                                class="pointer-events-auto touch-none absolute w-2 h-2 rounded-full bg-cyan-400 hover:bg-cyan-300 border-[0.75px] border-gray-950 shadow-sm cursor-nesw-resize after:content-[''] after:absolute after:-inset-2"></button>

                        {{-- Mittelpunkt-Fadenkreuz --}}
                        <div style="left: 50%; top: 50%; transform: translate(-50%, -50%) scale(1);"
                             :style="`left: 50%; top: 50%; transform: translate(-50%, -50%) scale(${(1 / cockpitZoom).toFixed(4)});`"
                             class="pointer-events-none absolute w-1.5 h-1.5 rounded-full bg-cyan-400 border-[0.75px] border-gray-950 shadow-sm"></div>
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

                {{-- HUD oben links: Shift-Feinmodus Badge (immer bei Shift) + 0,01 mm Koordinaten (bei Details) --}}
                <div :class="cockpitZoom > 1.01 ? 'pr-24 sm:pr-28' : ''"
                     class="absolute top-2.5 left-2.5 right-2.5 z-20 pointer-events-none flex flex-wrap items-center justify-between gap-1.5">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span x-cloak
                              x-show="shiftHeld"
                              style="display: none;"
                              data-cockpit-shift-badge
                              class="px-2.5 py-1 rounded-lg bg-[var(--theme-color)] text-gray-950 font-mono text-[10px] font-black uppercase tracking-wider shadow-md">
                            SHIFT · FEIN-MODUS (0,01 MM)
                        </span>
                        <span x-cloak
                              x-show="showLaserDetails"
                              style="display: none;"
                              class="px-2.5 py-1 rounded-lg bg-gray-950/85 border border-gray-700 text-white font-mono text-[10px] font-black">
                            X: <span x-text="(Number(xMm) || 0).toFixed(2)"></span> mm · Y: <span x-text="(Number(yMm) || 0).toFixed(2)"></span> mm · <span x-text="(Number(angleDeg) || 0).toFixed(2)"></span>°
                        </span>
                        <span x-cloak
                              x-show="showLaserDetails"
                              style="display: none;"
                              class="px-2 py-1 rounded-lg bg-cyan-950/85 border border-cyan-500/40 text-cyan-300 font-mono text-[10px] font-bold"
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

            {{-- Manuelle Koordinaten & Feinjustierung (0,01 mm Präzision) --}}
            <div x-cloak
                 x-show="showLaserDetails"
                 style="display: none;"
                 class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center bg-gray-50 dark:bg-gray-950 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                    <span class="text-[9px] font-black uppercase tracking-wider text-gray-400">X (mm)</span>
                    <input type="number"
                           step="0.01"
                           min="5"
                           max="195"
                           x-model.number="xMm"
                           @change="persistCoords()"
                           class="w-16 text-right font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                </div>
                <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                    <span class="text-[9px] font-black uppercase tracking-wider text-gray-400">Y (mm)</span>
                    <input type="number"
                           step="0.01"
                           min="5"
                           max="195"
                           x-model.number="yMm"
                           @change="persistCoords()"
                           class="w-16 text-right font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                </div>
                <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                    <span class="text-[9px] font-black uppercase tracking-wider text-gray-400">Winkel</span>
                    <input type="number"
                           step="0.01"
                           min="-45"
                           max="45"
                           x-model.number="angleDeg"
                           @change="persistCoords()"
                           class="w-16 text-right font-mono text-xs font-black bg-transparent text-gray-900 dark:text-white focus:outline-none">
                </div>
                <div class="flex items-center justify-center gap-1">
                    <button type="button" @click="nudge(-0.5, 0, 0, $event.shiftKey)" title="Nach links (Shift = 0,02 mm)" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&larr;</button>
                    <button type="button" @click="nudge(0, -0.5, 0, $event.shiftKey)" title="Nach oben (Shift = 0,02 mm)" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&uarr;</button>
                    <button type="button" @click="nudge(0, 0.5, 0, $event.shiftKey)" title="Nach unten (Shift = 0,02 mm)" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&darr;</button>
                    <button type="button" @click="nudge(0.5, 0, 0, $event.shiftKey)" title="Nach rechts (Shift = 0,02 mm)" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&rarr;</button>
                    <button type="button" @click="nudge(0, 0, -0.5, $event.shiftKey)" title="Gegen Uhrzeigersinn drehen (Shift = 0,02°)" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&#8634;</button>
                    <button type="button" @click="nudge(0, 0, 0.5, $event.shiftKey)" title="Im Uhrzeigersinn drehen (Shift = 0,02°)" class="px-2 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-800 text-xs font-black text-gray-700 dark:text-gray-200 cursor-pointer">&#8635;</button>
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
                    {{-- Zeile 0: Produkt-Profile (ganz oben) + validierte xTool F2 Ultra UV Handbuch-Presets + direkt als Profil speichern --}}
                    <div class="bg-white dark:bg-gray-900 px-3 py-2 rounded-lg border border-[var(--theme-color-30)] space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="block text-[9px] font-black uppercase tracking-wider text-[var(--theme-color)]">Produkt-Profile &amp; Handbuch-Preset</label>
                            <button type="button"
                                    data-sf-toggle-new-profile
                                    @click.stop="showNewProfileForm = !showNewProfileForm"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] text-[var(--theme-color)] hover:text-gray-950 border border-[var(--theme-color-30)] text-[9px] font-black uppercase tracking-wider transition-colors cursor-pointer">
                                <span>+ Als Produkt-Profil speichern</span>
                            </button>
                        </div>
                        <select x-model="handbookPresetKey"
                                @change="applyHandbookPreset()"
                                data-sf-handbook-presets="{{ count($handbookPresets) }}"
                                data-sf-product-profiles="{{ count($productProfiles) }}"
                                class="w-full text-xs font-bold bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none cursor-pointer">
                            <option value="">Profil / Preset wählen</option>
                            @if(!empty($productProfiles))
                                <optgroup label="Produkt-Profile">
                                    @foreach($productProfiles as $prodProf)
                                        <option value="profile:{{ $prodProf['id'] }}">
                                            {{ $prodProf['title'] }}{{ !empty($prodProf['is_default']) ? ' (Standard)' : '' }} · {{ $fmtDim((float) ($prodProf['focus_mm'] ?? 0)) }} mm · {{ (int) ($prodProf['power_percent'] ?? 0) }} % · {{ (int) ($prodProf['dot_duration_us'] ?? 0) }} µs
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if(!empty($handbookPresets))
                                @foreach(collect($handbookPresets)->groupBy('group') as $presetGroup => $presetsInGroup)
                                    <optgroup label="{{ $presetGroup }}">
                                        @foreach($presetsInGroup as $hbPreset)
                                            <option value="{{ $hbPreset['key'] }}">{{ $hbPreset['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            @endif
                        </select>

                        <div x-cloak
                             x-show="showNewProfileForm"
                             style="display: none;"
                             class="flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5 pt-1.5 border-t border-gray-200 dark:border-gray-800">
                            <input type="text"
                                   x-model="newProfileTitle"
                                   @keydown.enter.prevent="saveCurrentAsProductProfile()"
                                   placeholder="Profil-Titel (z. B. Rückseite Widmung)"
                                   class="flex-1 min-w-0 px-2.5 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:outline-none">
                            <button type="button"
                                    @click.stop="saveCurrentAsProductProfile()"
                                    class="px-3 py-1.5 rounded-lg bg-[var(--theme-color)] text-gray-950 text-[9px] font-black uppercase tracking-wider hover:brightness-95 transition cursor-pointer shrink-0">
                                Profil anlegen
                            </button>
                        </div>
                    </div>
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

                    {{-- Zeile 3: 4 Theme-Color Schieberegler: Punktdauer (1-3000 µs), Leistung, DPI & Frequenz --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div class="bg-white dark:bg-gray-900 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-800 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Punktdauer (µs)</label>
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] border border-[var(--theme-color-30)]">
                                    <input type="number"
                                           step="1"
                                           min="1"
                                           max="3000"
                                           x-model.number="laserParams.dot_duration_us"
                                           @change="onNumericParamChange('dot_duration_us', 1, 3000)"
                                           class="w-14 text-right font-mono text-xs font-black bg-transparent text-[var(--theme-color)] focus:outline-none">
                                    <span class="text-[9px] font-mono font-bold text-[var(--theme-color)]">µs</span>
                                </div>
                            </div>
                            <input type="range"
                                   data-sf-param-range="dot_duration_us"
                                   min="1"
                                   max="3000"
                                   step="1"
                                   value="{{ $initDotUs }}"
                                   :value="laserParams.dot_duration_us"
                                   @pointerdown.stop="beginSliderDrag('dot_duration_us')"
                                   @mousedown.stop="beginSliderDrag('dot_duration_us')"
                                   @touchstart.stop.passive="beginSliderDrag('dot_duration_us')"
                                   @input="onSliderInput('dot_duration_us', $event.target.value, 1, 3000, $event.target)"
                                   @change="onSliderCommit('dot_duration_us', $event.target.value, 1, 3000, $event.target)"
                                   style="--val-pct: {{ $initDotBarPct }}%;"
                                   :style="rangeFillStyle(laserParams.dot_duration_us, 1, 3000)"
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
                                           @change="onNumericParamChange('power_percent', 1, 100)"
                                           class="w-11 text-right font-mono text-xs font-black bg-transparent text-[var(--theme-color)] focus:outline-none">
                                    <span class="text-[9px] font-mono font-bold text-[var(--theme-color)]">%</span>
                                </div>
                            </div>
                            <input type="range"
                                   data-sf-param-range="power_percent"
                                   min="1"
                                   max="100"
                                   step="1"
                                   value="{{ $initPowerPctVal }}"
                                   :value="laserParams.power_percent"
                                   @pointerdown.stop="beginSliderDrag('power_percent')"
                                   @mousedown.stop="beginSliderDrag('power_percent')"
                                   @touchstart.stop.passive="beginSliderDrag('power_percent')"
                                   @input="onSliderInput('power_percent', $event.target.value, 1, 100, $event.target)"
                                   @change="onSliderCommit('power_percent', $event.target.value, 1, 100, $event.target)"
                                   style="--val-pct: {{ $initPowerBarPct }}%;"
                                   :style="rangeFillStyle(laserParams.power_percent, 1, 100)"
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
                                           @change="onNumericParamChange('dpi', 50, 2540)"
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
                                   data-sf-param-range="dpi"
                                   min="50"
                                   max="2540"
                                   step="1"
                                   value="{{ $initDpiVal }}"
                                   :value="laserParams.dpi"
                                   @pointerdown.stop="beginSliderDrag('dpi')"
                                   @mousedown.stop="beginSliderDrag('dpi')"
                                   @touchstart.stop.passive="beginSliderDrag('dpi')"
                                   @input="onSliderInput('dpi', $event.target.value, 50, 2540, $event.target)"
                                   @change="onSliderCommit('dpi', $event.target.value, 50, 2540, $event.target)"
                                   style="--val-pct: {{ $initDpiBarPct }}%;"
                                   :style="rangeFillStyle(laserParams.dpi, 50, 2540)"
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
                                           @change="onNumericParamChange('frequency_khz', 1, 200)"
                                           class="w-12 text-right font-mono text-xs font-black bg-transparent text-[var(--theme-color)] focus:outline-none">
                                    <span class="text-[9px] font-mono font-bold text-[var(--theme-color)]">kHz</span>
                                </div>
                            </div>
                            <input type="range"
                                   data-sf-param-range="frequency_khz"
                                   min="1"
                                   max="200"
                                   step="1"
                                   value="{{ $initFreqVal }}"
                                   :value="laserParams.frequency_khz"
                                   @pointerdown.stop="beginSliderDrag('frequency_khz')"
                                   @mousedown.stop="beginSliderDrag('frequency_khz')"
                                   @touchstart.stop.passive="beginSliderDrag('frequency_khz')"
                                   @input="onSliderInput('frequency_khz', $event.target.value, 1, 200, $event.target)"
                                   @change="onSliderCommit('frequency_khz', $event.target.value, 1, 200, $event.target)"
                                   style="--val-pct: {{ $initFreqBarPct }}%;"
                                   :style="rangeFillStyle(laserParams.frequency_khz, 1, 200)"
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

            {{-- Aufklappbares Medien-Dropdown direkt unter Laserparameter anpassen --}}
            @include('livewire.shop.order.order-overview-partials.media-dropdown-card', [
                'targetItem' => $previewItem,
                'exportSide' => $cockpitData['side'],
                'inCockpit' => true,
                'showItemSwitcher' => false,
            ])

            {{-- Blendfreie Kameraaufnahme: kompakter 3-Wege-Umschalter direkt beim Kamera-Button --}}
            @php
                $activeCamLight = $cockpitData['camera_light_mode'] ?? ($this->laserCameraLightMode ?? 'anti_glare');
            @endphp
            <div data-cockpit-light-mode
                 class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">
                    <svg class="w-3.5 h-3.5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Kamera-Licht</span>
                </span>

                <div class="grid grid-cols-3 sm:inline-flex items-center gap-1 p-0.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 w-full sm:w-auto">
                    <button type="button"
                            wire:click="setLaserCameraLightMode('anti_glare', '{{ $previewItem->id }}')"
                            class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer {{ $activeCamLight === 'anti_glare'
                                ? 'bg-[var(--theme-color)] text-gray-950 shadow-2xs'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        Blendfrei (LED aus)
                    </button>
                    <button type="button"
                            wire:click="setLaserCameraLightMode('dim', '{{ $previewItem->id }}')"
                            class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer {{ $activeCamLight === 'dim'
                                ? 'bg-[var(--theme-color)] text-gray-950 shadow-2xs'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        Gedimmt
                    </button>
                    <button type="button"
                            wire:click="setLaserCameraLightMode('normal', '{{ $previewItem->id }}')"
                            class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer {{ $activeCamLight === 'normal'
                                ? 'bg-[var(--theme-color)] text-gray-950 shadow-2xs'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        Licht an
                    </button>
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
