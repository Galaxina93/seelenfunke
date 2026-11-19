@php
    $laserCatalog = \App\Models\Product\Product::getLaserEquipmentCatalog();
    $laserPdfGuides = \App\Models\Product\Product::getLaserPdfGuides();
@endphp

<div data-step-4-2-root class="space-y-8">

    {{-- ================================================================= --}}
    {{-- KACHEL 1: XTOOL LASER-PARAMETER (PHYSISCHE SVG-EXPORT-GEOMETRIE)  --}}
    {{-- ================================================================= --}}
    <div class="bg-gray-50 dark:bg-gray-950 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                </svg>
                <h4 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest">xTool Laser-Parameter (SVG-Geometrie)</h4>
                @include('components.alerts.info-tooltip', ['text' => 'Physische Millimeter-Maße der Arbeitsfläche für den maßstabsgetreuen 1:1 SVG-Export an xTool Studio.'])
            </div>

            <div class="inline-flex items-center p-1 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <button type="button"
                        @click="configSettings.overlay_type = 'plane'"
                        :class="configSettings.overlay_type !== 'cylinder' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-2xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all cursor-pointer">
                    Flachbett
                </button>
                <button type="button"
                        @click="configSettings.overlay_type = 'cylinder'"
                        :class="configSettings.overlay_type === 'cylinder' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-2xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                        class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all cursor-pointer">
                    Rotary (Rund)
                </button>
            </div>
        </div>

        {{-- MODUS: ZYLINDER / RUND --}}
        <div x-show="configSettings.overlay_type === 'cylinder'" x-transition class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Ø Arbeitsbereich Oben *</label>
                        @include('components.alerts.info-tooltip', ['key' => 'xtool_rotary_diameter_help'])
                    </div>
                    <div class="relative">
                        <input type="number" x-model.number="configSettings.xtool_d_top" step="0.1"
                               :class="!(Number(configSettings.xtool_d_top) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                               class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-3 text-sm font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner" placeholder="80">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400 dark:text-gray-600">mm</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Ø Arbeitsbereich Unten</label>
                        @include('components.alerts.info-tooltip', ['key' => 'xtool_rotary_diameter_help'])
                    </div>
                    <div class="relative">
                        <input type="number" x-model.number="configSettings.xtool_d_bottom" step="0.1"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner" placeholder="60">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400 dark:text-gray-600">mm</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Reine Gravierhöhe *</label>
                        @include('components.alerts.info-tooltip', ['key' => 'xtool_plane_height_help'])
                    </div>
                    <div class="relative">
                        <input type="number" x-model.number="configSettings.xtool_height" step="0.1"
                               :class="!(Number(configSettings.xtool_height) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                               class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-3 text-sm font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner" placeholder="150">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400 dark:text-gray-600">mm</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 border-t border-gray-200 dark:border-gray-800 pt-5">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Sperrzone Oben (Trinkrand)</label>
                        @include('components.alerts.info-tooltip', ['text' => 'Abstand in Millimetern vom oberen Glasrand bis zum Beginn der Gravurzone.'])
                    </div>
                    <div class="relative">
                        <input type="number" x-model.number="configSettings.xtool_offset_top" step="0.1"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner" placeholder="30">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400 dark:text-gray-600">mm</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Sperrzone Unten (Boden)</label>
                        @include('components.alerts.info-tooltip', ['text' => 'Abstand in Millimetern vom Glasboden bis zum Ende der Gravurzone (Rollenbereich).'])
                    </div>
                    <div class="relative">
                        <input type="number" x-model.number="configSettings.xtool_offset_bottom" step="0.1"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner" placeholder="40">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400 dark:text-gray-600">mm</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- MODUS: FLACHBETT (EBENE) --}}
        <div x-show="configSettings.overlay_type !== 'cylinder'" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Gravier-Breite (X) *</label>
                    @include('components.alerts.info-tooltip', ['text' => 'Physische Breite (X) der 100 %-Bühne in Millimetern für den 1:1 SVG-Export.'])
                </div>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_plane_width" step="0.1"
                           :class="!(Number(configSettings.xtool_plane_width) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                           class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-3 text-sm font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner" placeholder="180">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400 dark:text-gray-600">mm</span>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Gravier-Höhe (Y) *</label>
                    @include('components.alerts.info-tooltip', ['text' => 'Physische Höhe (Y) der 100 %-Bühne in Millimetern für den 1:1 SVG-Export.'])
                </div>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_plane_height" step="0.1"
                           :class="!(Number(configSettings.xtool_plane_height) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                           class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-3 text-sm font-mono font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner" placeholder="180">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400 dark:text-gray-600">mm</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- KACHEL 2: ANLEITUNG (VORAUSSETZUNGEN & XTOOL STUDIO BEARBEITUNG)  --}}
    {{-- ================================================================= --}}
    <div class="bg-gray-50 dark:bg-gray-950 p-4 sm:p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner space-y-7"
         x-data="{
             openProfileIndex: null,
             init() {
                 this.ensureLaserProfilesInitialized();
                 this.$watch('configSettings.xtool_focus_mm', () => {
                     if (!Array.isArray(this.configSettings.xtool_profiles) || this.configSettings.xtool_profiles.length === 0) {
                         this.ensureLaserProfilesInitialized();
                     }
                 });
             },
             buildProfileFromPrimary(title = 'Standard', isDefault = true) {
                 const cs = this.configSettings || {};
                 const rawDot = Number(cs.xtool_dot_duration_us) || 0;
                 return {
                     id: 'prof_' + Math.random().toString(36).slice(2, 9),
                     title: title,
                     is_default: Boolean(isDefault),
                     focus_mm: cs.xtool_focus_mm ?? '',
                     element_type: cs.xtool_element_type || 'Bild',
                     engrave_tab: cs.xtool_engrave_tab || 'Gravieren',
                     preset_mode: cs.xtool_preset_mode || 'Benutzerdefiniert',
                     dot_duration_us: rawDot > 0 ? Math.max(1, Math.min(3000, rawDot)) : '',
                     power_percent: cs.xtool_power_percent ?? '',
                     dpi: cs.xtool_dpi ?? '',
                     frequency_khz: cs.xtool_frequency_khz ?? '',
                     speed_mms: Number(cs.xtool_speed_mms) > 0 ? Number(cs.xtool_speed_mms) : 200,
                     passes: cs.xtool_passes ?? 1,
                     bitmap_mode: cs.xtool_bitmap_mode || 'Jarvis',
                     engrave_mode: cs.xtool_engrave_mode || 'Bidirektional',
                     vector_note: cs.xtool_vector_note || '',
                     photo_studio: null
                 };
             },
             ensureLaserProfilesInitialized() {
                 if (!this.configSettings || typeof this.configSettings !== 'object') return;
                 if (!Array.isArray(this.configSettings.xtool_profiles) || this.configSettings.xtool_profiles.length === 0) {
                     const initial = this.buildProfileFromPrimary('Standard', true);
                     this.configSettings.xtool_profiles = [initial];
                     const hasFocus = Number(String(initial.focus_mm || '').replace(',', '.')) > 0;
                     this.openProfileIndex = hasFocus ? null : 0;
                 } else {
                     this.configSettings.xtool_profiles.forEach(p => {
                         if (p && typeof p === 'object' && p.photo_studio === undefined) {
                             p.photo_studio = null;
                         }
                     });
                     let hasDef = this.configSettings.xtool_profiles.some(p => p && p.is_default);
                     if (!hasDef && this.configSettings.xtool_profiles[0]) {
                         this.configSettings.xtool_profiles[0].is_default = true;
                     }
                 }
             },
             syncDefaultProfileToPrimary() {
                 if (!this.configSettings || !Array.isArray(this.configSettings.xtool_profiles)) return;
                 const def = this.configSettings.xtool_profiles.find(p => p && p.is_default) || this.configSettings.xtool_profiles[0];
                 if (!def) return;
                 const dotVal = Number(def.dot_duration_us) || 0;
                 if (dotVal > 3000) {
                     def.dot_duration_us = 3000;
                 }
                 this.configSettings.xtool_focus_mm = def.focus_mm;
                 this.configSettings.xtool_element_type = def.element_type || 'Bild';
                 this.configSettings.xtool_engrave_tab = def.engrave_tab || 'Gravieren';
                 this.configSettings.xtool_preset_mode = def.preset_mode || 'Benutzerdefiniert';
                 this.configSettings.xtool_dot_duration_us = dotVal > 0 ? Math.max(1, Math.min(3000, dotVal)) : def.dot_duration_us;
                 this.configSettings.xtool_power_percent = def.power_percent;
                 this.configSettings.xtool_dpi = def.dpi;
                 this.configSettings.xtool_frequency_khz = def.frequency_khz;
                 this.configSettings.xtool_speed_mms = Number(def.speed_mms) > 0 ? Number(def.speed_mms) : 200;
                 this.configSettings.xtool_passes = def.passes;
                 this.configSettings.xtool_bitmap_mode = def.bitmap_mode || '';
                 this.configSettings.xtool_engrave_mode = def.engrave_mode || '';
                 this.configSettings.xtool_vector_note = def.vector_note || '';
             },
             onProfileFieldChanged(idx) {
                 if (!Array.isArray(this.configSettings.xtool_profiles)) return;
                 const p = this.configSettings.xtool_profiles[idx];
                 if (!p) return;
                 const d = Number(p.dot_duration_us);
                 if (Number.isFinite(d) && d > 3000) {
                     p.dot_duration_us = 3000;
                 }
                 if (p.is_default) {
                     this.syncDefaultProfileToPrimary();
                 }
             },
             toggleProfileAccordion(idx) {
                 this.openProfileIndex = (this.openProfileIndex === idx) ? null : idx;
             },
             setDefaultLaserProfile(idx) {
                 if (!Array.isArray(this.configSettings.xtool_profiles)) return;
                 this.configSettings.xtool_profiles.forEach((p, i) => {
                     if (p) p.is_default = (i === idx);
                 });
                 this.syncDefaultProfileToPrimary();
             },
             addLaserProfile() {
                 this.ensureLaserProfilesInitialized();
                 const list = this.configSettings.xtool_profiles;
                 const source = list.find(p => p && p.is_default) || list[list.length - 1] || this.buildProfileFromPrimary('Standard', false);
                 const nextNum = list.length + 1;
                 const copy = {
                     id: 'prof_' + Date.now().toString(36) + '_' + nextNum,
                     title: 'Profil ' + nextNum,
                     is_default: false,
                     focus_mm: source.focus_mm ?? 19.5,
                     element_type: source.element_type || 'Bild',
                     engrave_tab: source.engrave_tab || 'Gravieren',
                     preset_mode: source.preset_mode || 'Benutzerdefiniert',
                     dot_duration_us: Math.max(1, Math.min(3000, Number(source.dot_duration_us) || 210)),
                     power_percent: Number(source.power_percent) || 45,
                     dpi: Number(source.dpi) || 600,
                     frequency_khz: Number(source.frequency_khz) || 65,
                     speed_mms: Number(source.speed_mms) || 200,
                     passes: Number(source.passes) || 1,
                     bitmap_mode: source.bitmap_mode || 'Jarvis',
                     engrave_mode: source.engrave_mode || 'Bidirektional',
                     vector_note: source.vector_note || '',
                     photo_studio: (source && source.photo_studio && typeof source.photo_studio === 'object')
                         ? JSON.parse(JSON.stringify(source.photo_studio))
                         : null
                 };
                 this.configSettings.xtool_profiles.push(copy);
                 this.openProfileIndex = this.configSettings.xtool_profiles.length - 1;
             },
             removeLaserProfile(idx) {
                 if (!Array.isArray(this.configSettings.xtool_profiles) || this.configSettings.xtool_profiles.length <= 1) return;
                 const wasDefault = Boolean(this.configSettings.xtool_profiles[idx]?.is_default);
                 this.configSettings.xtool_profiles.splice(idx, 1);
                 if (wasDefault && this.configSettings.xtool_profiles[0]) {
                     this.configSettings.xtool_profiles[0].is_default = true;
                     this.syncDefaultProfileToPrimary();
                 }
                 if (this.openProfileIndex === idx) {
                     this.openProfileIndex = null;
                 } else if (this.openProfileIndex !== null && this.openProfileIndex > idx) {
                     this.openProfileIndex--;
                 }
             },
             fmtProfileFocus(val) {
                 const n = Number(String(val ?? '').replace(',', '.'));
                 if (!Number.isFinite(n) || n <= 0) return '0 mm';
                 return (Math.abs(n - Math.round(n)) < 0.05 ? String(Math.round(n)) : n.toFixed(1).replace('.', ',')) + ' mm';
             },
             calcDotPct(val) {
                 const v = Math.max(1, Math.min(3000, Number(val) || 1));
                 return (((v - 1) / 2999) * 100).toFixed(2);
             },
             isLaserGuideComplete() {
                 const dot = Number(configSettings.xtool_dot_duration_us);
                 return Boolean(
                     configSettings.xtool_equipment_key &&
                     String(configSettings.xtool_process_mode || '').trim() !== '' &&
                     String(configSettings.xtool_material || '').trim() !== '' &&
                     Number(String(configSettings.xtool_focus_mm || '').replace(',', '.')) > 0 &&
                     dot >= 1 && dot <= 3000 &&
                     Number(configSettings.xtool_power_percent) > 0 &&
                     Number(configSettings.xtool_dpi) > 0 &&
                     Number(configSettings.xtool_passes) > 0 &&
                     Number(configSettings.xtool_frequency_khz) > 0 &&
                     String(configSettings.xtool_bitmap_mode || '').trim() !== '' &&
                     String(configSettings.xtool_engrave_mode || '').trim() !== ''
                 );
             },
             selectEquipment(key, defaultMode) {
                 configSettings.xtool_equipment_key = key;
                 if (!String(configSettings.xtool_process_mode || '').trim()) {
                     configSettings.xtool_process_mode = defaultMode;
                 }
             }
         }">

        {{-- Header Kachel 2 --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 dark:border-gray-800 pb-4">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <h4 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest">Anleitung: Voraussetzungen &amp; xTool Bearbeitung</h4>
                @include('components.alerts.info-tooltip', ['text' => 'Definiert das benötigte Laser-Equipment (inkl. PDF-Aufbauanleitung) und die exakten xTool Studio Gravurwerte. Diese Anleitung erscheint automatisch auch in jeder Bestellung unter /admin/orders.'])
            </div>

            <span x-show="isLaserGuideComplete()" x-cloak
                  class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
                ✓ Anleitung vollständig
            </span>
            <span x-show="!isLaserGuideComplete()" x-cloak
                  class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest bg-red-50 dark:bg-red-500/15 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/30">
                ✕ Laser-Werte fehlen
            </span>
        </div>

        {{-- TEIL A: VORAUSSETZUNGEN (EQUIPMENT & PDF-ANLEITUNG) --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <label class="text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">1. Voraussetzungen &amp; Equipment-Aufbau *</label>
                @include('components.alerts.info-tooltip', ['text' => 'Wähle den physischen Aufbau am xTool F2 Ultra UV. Bei Spezial-Equipment lässt sich die bebilderte PDF-Anleitung direkt per Klick öffnen.'])
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach($laserCatalog as $eqKey => $eqMeta)
                    <div @click="selectEquipment(@js($eqKey), @js($eqMeta['default_process_mode']))"
                         :class="configSettings.xtool_equipment_key === @js($eqKey)
                             ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-xs'
                             : (!configSettings.xtool_equipment_key ? 'border-red-300 dark:border-red-500/40 bg-white dark:bg-gray-900 hover:border-gray-400' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-700')"
                         class="p-4 rounded-2xl border transition-all cursor-pointer flex flex-col justify-between gap-3">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-black text-gray-900 dark:text-white">{{ $eqMeta['title'] }}</span>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @include('components.alerts.info-tooltip', ['text' => $eqMeta['tooltip']])
                                    <span x-show="configSettings.xtool_equipment_key === @js($eqKey)" x-cloak
                                          class="w-4 h-4 rounded-full bg-[var(--theme-color)] text-white dark:text-gray-900 flex items-center justify-center text-[9px] font-black">✓</span>
                                </div>
                            </div>
                            <span class="inline-block text-[9px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $eqMeta['badge'] }}</span>
                        </div>

                        @if(!empty($eqMeta['pdf_file']))
                            <div class="flex items-center gap-1.5" @click.stop>
                                <a href="{{ asset($eqMeta['pdf_file']) }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color)] hover:text-white dark:hover:text-gray-900 text-gray-700 dark:text-gray-300 text-[9px] font-black uppercase tracking-wider transition-all border border-gray-200 dark:border-gray-700">
                                    <span>Öffnen</span>
                                </a>
                                <a href="{{ asset($eqMeta['pdf_file']) }}"
                                   download="{{ $eqMeta['pdf_filename'] }}"
                                   title="PDF-Anleitung herunterladen"
                                   class="inline-flex items-center justify-center px-2.5 py-1.5 rounded-xl bg-white dark:bg-gray-900 hover:bg-[var(--theme-color)] hover:text-white dark:hover:text-gray-900 text-gray-700 dark:text-gray-300 text-[9px] font-black uppercase tracking-wider transition-all border border-gray-200 dark:border-gray-700">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                            </div>
                        @else
                            <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Kein Umbau nötig</span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Zentrale Leiste aller 3 hinterlegten PDF-Anleitungen zum direkten Öffnen & Herunterladen --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200">Hinterlegte xTool PDF-Anleitungen</span>
                        @include('components.alerts.info-tooltip', ['text' => 'Alle 3 PDF-Anleitungen liegen fest im System unter storage/app/public/produkte/laser-anleitungen/ sowie im KI-Workspace (/admin/ai-workspace -> Berufsleben/Laser-Anleitungen) und können hier jederzeit geöffnet oder heruntergeladen werden.'])
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                    @foreach($laserPdfGuides as $pdfGuide)
                        <div :class="configSettings.xtool_equipment_key === @js($pdfGuide['equipment_key'])
                                 ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]'
                                 : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950'"
                             class="p-3 rounded-xl border flex flex-col justify-between gap-2.5 transition-all">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="block text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $pdfGuide['badge'] }}</span>
                                    <span class="block text-xs font-black text-gray-900 dark:text-white truncate mt-0.5" title="{{ $pdfGuide['title'] }}">{{ $pdfGuide['title'] }}</span>
                                </div>
                                <span x-show="configSettings.xtool_equipment_key === @js($pdfGuide['equipment_key'])" x-cloak
                                      class="shrink-0 px-2 py-0.5 rounded-md bg-[var(--theme-color)] text-white dark:text-gray-900 text-[8px] font-black uppercase tracking-wider">
                                    Aktiv
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <a href="{{ $pdfGuide['url'] }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all">
                                    <span>Öffnen</span>
                                </a>
                                <a href="{{ $pdfGuide['url'] }}"
                                   download="{{ $pdfGuide['filename'] }}"
                                   class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg bg-[var(--theme-color)] hover:opacity-90 text-white dark:text-gray-900 text-[9px] font-black uppercase tracking-wider transition-all">
                                    <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>PDF ziehen</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Kurzer Praxis-/Einlege-Hinweis --}}
            <div class="pt-1">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Einlege-Hinweis / Besonderheit (Optional)</label>
                    @include('components.alerts.info-tooltip', ['text' => 'Kurzer Praxishinweis zur Ausrichtung im Laser, z. B. Standfuß frei schweben lassen oder Shop-SVG nicht invertieren.'])
                </div>
                <input type="text"
                       x-model="configSettings.xtool_setup_note"
                       placeholder="z.B. Schwarze Unterlegplatte nutzen, Standfuß frei über Vorderkante schweben lassen"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-2.5 text-xs font-medium text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
            </div>
        </div>

        {{-- TEIL B: WIE MAN ES BEARBEITET (1:1 XTOOL STUDIO MASKE MIT MULTI-LASERPROFILEN) --}}
        <div class="border-t border-gray-200 dark:border-gray-800 pt-6 space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">2. Wie man es bearbeitet (xTool Studio Parameter) *</label>
                    @include('components.alerts.info-tooltip', ['text' => 'Verwalte mehrere benannte xTool-Laserprofile pro Produkt (z. B. Schwarz 100 kHz auf Lamellenrost, Cockpit-Sweetspot 65 kHz, Weiß / Silber-Kontrast). Das Standard-Profil ist automatisch im Live-Cockpit vorausgewählt.'])
                </div>

                <button type="button"
                        data-add-laser-profile-btn
                        @click="addLaserProfile()"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-[var(--theme-color)] text-white dark:text-gray-900 text-[10px] font-black uppercase tracking-widest shadow-2xs hover:opacity-90 transition-all cursor-pointer">
                    <span>+ Laserprofil hinzufügen</span>
                </button>
            </div>

            {{-- Grunddaten: Modus | Material --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5 min-w-0">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Modus *</label>
                    <input type="text"
                           list="xtoolProcessModesList"
                           x-model="configSettings.xtool_process_mode"
                           placeholder="z.B. Prozess auf flacher Oberfläche"
                           :class="!String(configSettings.xtool_process_mode || '').trim() ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                           class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-4 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                    <datalist id="xtoolProcessModesList">
                        <option value="Prozess auf flacher Oberfläche"></option>
                        <option value="Walze - Rotationsaufsatz"></option>
                        <option value="Spannfutter - Rotationsaufsatz"></option>
                        <option value="Gleiches Design - Stapelverarbeitung"></option>
                        <option value="Innengravur - 3D-Miniatur"></option>
                        <option value="Gebogene Oberfläche"></option>
                    </datalist>
                </div>

                <div class="space-y-1.5 min-w-0">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Material *</label>
                    <input type="text"
                           list="xtoolMaterialsList"
                           x-model="configSettings.xtool_material"
                           placeholder="z.B. Edelstahl 14K IP vergoldet"
                           :class="!String(configSettings.xtool_material || '').trim() ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                           class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-4 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                    <datalist id="xtoolMaterialsList">
                        <option value="Edelstahl 14K IP vergoldet (PVD)"></option>
                        <option value="Edelstahl"></option>
                        <option value="K9 Kristalltrophäe"></option>
                        <option value="0,2 mm Aluminiumlegierung"></option>
                        <option value="Eloxiertes Aluminium (Visitenkarte)"></option>
                        <option value="925er Sterlingsilber"></option>
                        <option value="925er Gold-Vermeil"></option>
                        <option value="Messing"></option>
                        <option value="Klares Glas"></option>
                        <option value="Schieferstein"></option>
                        <option value="Holz"></option>
                    </datalist>
                </div>
            </div>

            {{-- Mobiloptimierte Laserprofil-Tabelle mit Aufklapp-Ansicht (Accordion, ab 360px bis Desktop) --}}
            <div data-xtool-profiles-table
                 class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden divide-y divide-gray-200 dark:divide-gray-800">
                <template x-for="(prof, pIdx) in (Array.isArray(configSettings.xtool_profiles) ? configSettings.xtool_profiles : [])" :key="prof.id || ('idx_' + pIdx)">
                    <div class="w-full min-w-0 transition-colors"
                         :class="prof.is_default ? 'bg-[var(--theme-color-10)]/40' : 'bg-white dark:bg-gray-900'">
                        {{-- Zugeklappte Tabellenzeile --}}
                        <div @click="toggleProfileAccordion(pIdx)"
                             class="px-3.5 sm:px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 cursor-pointer select-none hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition-colors">
                            <div class="min-w-0 flex-1 space-y-1 sm:space-y-0.5">
                                <div class="flex flex-wrap items-center gap-2 min-w-0">
                                    <span class="text-xs font-black text-gray-900 dark:text-white break-words"
                                          x-text="prof.title || ('Profil ' + (pIdx + 1))"></span>
                                    <span x-show="prof.is_default"
                                          x-cloak
                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--theme-color)] text-white dark:text-gray-900 text-[9px] font-black uppercase tracking-wider shrink-0">
                                        ✓ Standard
                                    </span>
                                    <span x-show="prof.photo_studio && typeof prof.photo_studio === 'object'"
                                          x-cloak
                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider shrink-0">
                                        Foto-Werte hinterlegt
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[10px] font-mono font-bold text-gray-500 dark:text-gray-400">
                                    <span x-text="fmtProfileFocus(prof.focus_mm)"></span>
                                    <span>·</span>
                                    <span x-text="(Number(prof.power_percent) || 0) + ' %'"></span>
                                    <span>·</span>
                                    <span x-text="(Number(prof.dot_duration_us) || 0) + ' µs / ' + (Number(prof.speed_mms) || 200) + ' mm/s'"></span>
                                    <span>·</span>
                                    <span x-text="(Number(prof.frequency_khz) || 0) + ' kHz'"></span>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-1.5 shrink-0" @click.stop>
                                <button type="button"
                                        x-show="!prof.is_default"
                                        x-cloak
                                        @click="setDefaultLaserProfile(pIdx)"
                                        class="px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color)] hover:text-white dark:hover:text-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                    Standard
                                </button>

                                <button type="button"
                                        @click="toggleProfileAccordion(pIdx)"
                                        :title="openProfileIndex === pIdx ? 'Zuklappen' : 'Aufklappen'"
                                        class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-800 hover:border-[var(--theme-color)] text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 flex items-center justify-center transition-all cursor-pointer">
                                    <svg class="w-4 h-4 transition-transform duration-200"
                                         :class="openProfileIndex === pIdx ? 'rotate-180 text-[var(--theme-color)]' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <button type="button"
                                        x-show="Array.isArray(configSettings.xtool_profiles) && configSettings.xtool_profiles.length > 1"
                                        x-cloak
                                        @click="removeLaserProfile(pIdx)"
                                        title="Profil löschen"
                                        class="w-8 h-8 rounded-lg bg-white dark:bg-gray-900 hover:bg-red-50 dark:hover:bg-red-950/40 text-gray-400 hover:text-red-600 dark:hover:text-red-400 border border-gray-200 dark:border-gray-700 flex items-center justify-center transition-all cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Aufgeklappter Profil-Editor (alle 13 xTool-Parameter + Titel) --}}
                        <div x-show="openProfileIndex === pIdx"
                             x-cloak
                             class="px-3.5 sm:px-5 py-4 bg-gray-50/80 dark:bg-gray-950/80 border-t border-gray-200 dark:border-gray-800 space-y-4">

                            {{-- Zeile A: Profil-Titel | Fokus (mm) --}}
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                                <div class="sm:col-span-8 space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Profil-Titel *</label>
                                    <input type="text"
                                           x-model="prof.title"
                                           @input="onProfileFieldChanged(pIdx)"
                                           placeholder="z. B. Schwarz (Anlass-Gravur 100 kHz)"
                                           class="w-full min-w-0 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3.5 py-2 text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none">
                                </div>

                                <div class="sm:col-span-4 space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Fokus (mm) *</label>
                                    <div class="relative">
                                        <input type="number"
                                               step="0.1"
                                               min="0.1"
                                               max="150"
                                               x-model.number="prof.focus_mm"
                                               @input="onProfileFieldChanged(pIdx)"
                                               placeholder="21.5"
                                               :class="!(Number(String(prof.focus_mm || '').replace(',', '.')) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                                               class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-3.5 py-2 pr-10 text-xs font-mono font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">mm</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Zeile B: Elementtyp | Gravur-Art | Vorgabe --}}
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Elementtyp</label>
                                    <select x-model="prof.element_type"
                                            @change="onProfileFieldChanged(pIdx)"
                                            class="w-full min-w-0 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none cursor-pointer">
                                        <option value="Bild">Bild</option>
                                        <option value="Vektor">Vektor</option>
                                        <option value="3D-Modell">3D-Modell</option>
                                    </select>
                                </div>

                                <div class="space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Gravur-Art</label>
                                    <select x-model="prof.engrave_tab"
                                            @change="onProfileFieldChanged(pIdx)"
                                            class="w-full min-w-0 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none cursor-pointer">
                                        <option value="Gravieren">Gravieren</option>
                                        <option value="Farbgravur">Farbgravur</option>
                                        <option value="Konturgravur">Konturgravur</option>
                                    </select>
                                </div>

                                <div class="space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Vorgabe</label>
                                    <select x-model="prof.preset_mode"
                                            @change="onProfileFieldChanged(pIdx)"
                                            class="w-full min-w-0 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none cursor-pointer">
                                        <option value="Benutzerdefiniert">Benutzerdefiniert</option>
                                        <option value="Referenz">Referenz</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Zeile C: Punktdauer (1-3000 µs inkl. Slider) | Leistung (%) | DPI | Frequenz (kHz) | Speed (mm/s) | Durchgänge --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                                <div class="sm:col-span-2 space-y-1.5 min-w-0 bg-white dark:bg-gray-900 p-2.5 rounded-xl border"
                                     :class="!(Number(prof.dot_duration_us) >= 1 && Number(prof.dot_duration_us) <= 3000) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'">
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Punktdauer (1-3000 µs) *</label>
                                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] border border-[var(--theme-color-30)]">
                                            <input type="number"
                                                   min="1"
                                                   max="3000"
                                                   step="1"
                                                   x-model.number="prof.dot_duration_us"
                                                   @input="onProfileFieldChanged(pIdx)"
                                                   placeholder="1100"
                                                   class="w-14 text-right font-mono text-xs font-black bg-transparent text-[var(--theme-color)] focus:outline-none">
                                            <span class="text-[9px] font-mono font-bold text-[var(--theme-color)]">µs</span>
                                        </div>
                                    </div>
                                    <input type="range"
                                           min="1"
                                           max="3000"
                                           step="1"
                                           x-model.number="prof.dot_duration_us"
                                           @input="onProfileFieldChanged(pIdx)"
                                           :style="`accent-color: var(--theme-color); background: linear-gradient(to right, var(--theme-color) 0%, var(--theme-color) ${calcDotPct(prof.dot_duration_us)}%, rgba(156,163,175,0.3) ${calcDotPct(prof.dot_duration_us)}%, rgba(156,163,175,0.3) 100%);`"
                                           class="w-full h-1.5 rounded-full appearance-none cursor-pointer">
                                </div>

                                <div class="space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Leistung (%) *</label>
                                    <div class="relative">
                                        <input type="number"
                                               min="1"
                                               max="100"
                                               step="1"
                                               x-model.number="prof.power_percent"
                                               @input="onProfileFieldChanged(pIdx)"
                                               placeholder="95"
                                               :class="!(Number(prof.power_percent) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                                               class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-3 py-2 pr-8 text-xs font-mono font-black text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none">
                                        <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">%</span>
                                    </div>
                                </div>

                                <div class="space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">DPI *</label>
                                    <div class="relative">
                                        <input type="number"
                                               min="50"
                                               max="2540"
                                               step="1"
                                               x-model.number="prof.dpi"
                                               @input="onProfileFieldChanged(pIdx)"
                                               placeholder="1270"
                                               :class="!(Number(prof.dpi) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                                               class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-3 py-2 pr-10 text-xs font-mono font-black text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none">
                                        <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[9px] font-bold text-gray-400">DPI</span>
                                    </div>
                                </div>

                                <div class="space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Frequenz *</label>
                                    <div class="relative">
                                        <input type="number"
                                               min="1"
                                               max="200"
                                               step="1"
                                               x-model.number="prof.frequency_khz"
                                               @input="onProfileFieldChanged(pIdx)"
                                               placeholder="100"
                                               :class="!(Number(prof.frequency_khz) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                                               class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-3 py-2 pr-10 text-xs font-mono font-black text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none">
                                        <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[9px] font-bold text-gray-400">kHz</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 lg:grid-cols-1 gap-2">
                                    <div class="space-y-1 min-w-0">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Speed (mm/s)</label>
                                        <input type="number"
                                               min="1"
                                               max="4000"
                                               step="1"
                                               x-model.number="prof.speed_mms"
                                               @input="onProfileFieldChanged(pIdx)"
                                               placeholder="25"
                                               class="w-full min-w-0 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2 text-xs font-mono font-black text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none">
                                    </div>
                                </div>
                            </div>

                            {{-- Zeile D: Durchgänge | Bitmap-Modus | Richtung | Vektor-Hinweis --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                                <div class="lg:col-span-2 space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Durchgänge *</label>
                                    <div class="relative">
                                        <input type="number"
                                               min="1"
                                               max="20"
                                               step="1"
                                               x-model.number="prof.passes"
                                               @input="onProfileFieldChanged(pIdx)"
                                               placeholder="1"
                                               :class="!(Number(prof.passes) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                                               class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-3 py-2 pr-7 text-xs font-mono font-black text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none">
                                        <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">&times;</span>
                                    </div>
                                </div>

                                <div class="lg:col-span-3 space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Bitmap-Modus *</label>
                                    <select x-model="prof.bitmap_mode"
                                            @change="onProfileFieldChanged(pIdx)"
                                            :class="!String(prof.bitmap_mode || '').trim() ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                                            class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none cursor-pointer">
                                        <option value="">Bitte wählen...</option>
                                        <option value="Jarvis">Jarvis</option>
                                        <option value="Stucki">Stucki</option>
                                        <option value="Floyd">Floyd</option>
                                        <option value="Atkinson">Atkinson</option>
                                        <option value="Graustufen">Graustufen</option>
                                        <option value="Schwellenwert">Schwellenwert</option>
                                        <option value="Konturgravur (3D)">Konturgravur (3D)</option>
                                        <option value="Flächenfüllung (Vektor)">Flächenfüllung (Vektor)</option>
                                    </select>
                                </div>

                                <div class="lg:col-span-3 space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Richtung *</label>
                                    <select x-model="prof.engrave_mode"
                                            @change="onProfileFieldChanged(pIdx)"
                                            :class="!String(prof.engrave_mode || '').trim() ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                                            class="w-full min-w-0 bg-white dark:bg-gray-900 border rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none cursor-pointer">
                                        <option value="">Bitte wählen...</option>
                                        <option value="Bidirektional">Bidirektional</option>
                                        <option value="Zweidirektional">Zweidirektional</option>
                                        <option value="Unidirektional">Unidirektional</option>
                                        <option value="Eindirektional">Eindirektional</option>
                                    </select>
                                </div>

                                <div class="sm:col-span-2 lg:col-span-4 space-y-1 min-w-0">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Vektor-Hinweis</label>
                                    <input type="text"
                                           x-model="prof.vector_note"
                                           @input="onProfileFieldChanged(pIdx)"
                                           placeholder="z. B. Gravieren · 95 % · 25 mm/s · 1000 L/cm · 100 kHz"
                                           class="w-full min-w-0 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3.5 py-2 text-xs font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none">
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Direkt-Speichern Button am Ende der Kachel --}}
        <div class="flex items-center justify-end pt-3 border-t border-gray-200 dark:border-gray-800">
            <button type="button"
                    wire:click="save"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--theme-color)] text-white dark:text-gray-900 text-[10px] font-black uppercase tracking-widest shadow-xs hover:opacity-90 transition-all cursor-pointer">
                <svg wire:loading.remove wire:target="save" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <svg wire:loading wire:target="save" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span>Laser-Werte &amp; Anleitung speichern</span>
            </button>
        </div>
    </div>

</div>
