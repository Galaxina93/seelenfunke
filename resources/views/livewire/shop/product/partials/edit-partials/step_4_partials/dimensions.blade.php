@php
    $laserCatalog = \App\Models\Product\Product::getLaserEquipmentCatalog();
    $laserPdfGuides = \App\Models\Product\Product::getLaserPdfGuides();
@endphp

<div class="space-y-8">

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
    <div class="bg-gray-50 dark:bg-gray-950 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner space-y-7"
         x-data="{
             isLaserGuideComplete() {
                 return Boolean(
                     configSettings.xtool_equipment_key &&
                     String(configSettings.xtool_process_mode || '').trim() !== '' &&
                     String(configSettings.xtool_material || '').trim() !== '' &&
                     Number(String(configSettings.xtool_focus_mm || '').replace(',', '.')) > 0 &&
                     Number(configSettings.xtool_dot_duration_us) > 0 &&
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

        {{-- TEIL B: WIE MAN ES BEARBEITET (1:1 XTOOL STUDIO MASKE) --}}
        <div class="border-t border-gray-200 dark:border-gray-800 pt-6 space-y-5">
            <div class="flex items-center justify-between">
                <label class="text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">2. Wie man es bearbeitet (xTool Studio Parameter) *</label>
                @include('components.alerts.info-tooltip', ['text' => '1:1 Übernahme der Felder aus dem rechten Parameter-Panel von xTool Studio.'])
            </div>

            {{-- Zeile 1: Modus | Material | Fokus (mm) --}}
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-5 space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Modus *</label>
                    <input type="text"
                           list="xtoolProcessModesList"
                           x-model="configSettings.xtool_process_mode"
                           placeholder="z.B. Prozess auf flacher Oberfläche"
                           :class="!String(configSettings.xtool_process_mode || '').trim() ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                           class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                    <datalist id="xtoolProcessModesList">
                        <option value="Prozess auf flacher Oberfläche"></option>
                        <option value="Walze - Rotationsaufsatz"></option>
                        <option value="Spannfutter - Rotationsaufsatz"></option>
                        <option value="Gleiches Design - Stapelverarbeitung"></option>
                        <option value="Innengravur - 3D-Miniatur"></option>
                    </datalist>
                </div>

                <div class="md:col-span-4 space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Material *</label>
                    <input type="text"
                           list="xtoolMaterialsList"
                           x-model="configSettings.xtool_material"
                           placeholder="z.B. K9 Kristalltrophäe"
                           :class="!String(configSettings.xtool_material || '').trim() ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                           class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                    <datalist id="xtoolMaterialsList">
                        <option value="K9 Kristalltrophäe"></option>
                        <option value="0,2 mm Aluminiumlegierung"></option>
                        <option value="Edelstahl"></option>
                        <option value="Messing"></option>
                        <option value="Klares Glas"></option>
                        <option value="Schieferstein"></option>
                        <option value="Holz"></option>
                    </datalist>
                </div>

                <div class="md:col-span-3 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Fokus / Abstand *</label>
                        @include('components.alerts.info-tooltip', ['text' => 'Brennpunkt-Wert im mm-Feld von xTool Studio (z. B. 33.1 mm oder 19.9 mm).'])
                    </div>
                    <div class="relative">
                        <input type="number"
                               step="0.1"
                               x-model.number="configSettings.xtool_focus_mm"
                               placeholder="33.1"
                               :class="!(Number(String(configSettings.xtool_focus_mm || '').replace(',', '.')) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                               class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-2.5 pr-12 text-xs font-mono font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-gray-400 dark:text-gray-500">mm</span>
                    </div>
                </div>
            </div>

            {{-- Zeile 2: Ebene (Bild/Vektor/3D) | Tab (Gravieren/Farbgravur) | Einstellung (Benutzerdefiniert/Referenz) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Element-Typ</label>
                    <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                        @foreach(['Bild', 'Vektor', '3D-Modell'] as $elOpt)
                            <button type="button"
                                    @click="configSettings.xtool_element_type = @js($elOpt)"
                                    :class="(configSettings.xtool_element_type || 'Bild') === @js($elOpt) ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-2xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                                    class="py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                {{ $elOpt }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Gravur-Tab</label>
                    <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                        @foreach(['Gravieren', 'Farbgravur', 'Konturgravur'] as $tabOpt)
                            <button type="button"
                                    @click="configSettings.xtool_engrave_tab = @js($tabOpt)"
                                    :class="(configSettings.xtool_engrave_tab || 'Gravieren') === @js($tabOpt) ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-2xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                                    class="py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer truncate px-1">
                                {{ $tabOpt }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Einstellungs-Art</label>
                    <select x-model="configSettings.xtool_preset_mode"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all cursor-pointer">
                        <option value="Benutzerdefiniert">Benutzerdefiniert</option>
                        <option value="Referenz">Referenz</option>
                    </select>
                </div>
            </div>

            {{-- Zeile 3: Punktdauer (µs) | Leistung (%) | DPI | Bearbeitungsanzahl | Frequenz (kHz) --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Punktdauer *</label>
                    <div class="relative">
                        <input type="number"
                               min="1"
                               step="1"
                               x-model.number="configSettings.xtool_dot_duration_us"
                               placeholder="500"
                               :class="!(Number(configSettings.xtool_dot_duration_us) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                               class="w-full bg-white dark:bg-gray-900 border rounded-xl px-3.5 py-2.5 pr-10 text-xs font-mono font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-gray-400 dark:text-gray-500">µs</span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Leistung *</label>
                    <div class="relative">
                        <input type="number"
                               min="1"
                               max="100"
                               step="1"
                               x-model.number="configSettings.xtool_power_percent"
                               placeholder="100"
                               :class="!(Number(configSettings.xtool_power_percent) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                               class="w-full bg-white dark:bg-gray-900 border rounded-xl px-3.5 py-2.5 pr-9 text-xs font-mono font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-gray-400 dark:text-gray-500">%</span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">DPI *</label>
                    <div class="relative">
                        <input type="number"
                               min="1"
                               step="1"
                               x-model.number="configSettings.xtool_dpi"
                               placeholder="600"
                               :class="!(Number(configSettings.xtool_dpi) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                               class="w-full bg-white dark:bg-gray-900 border rounded-xl px-3.5 py-2.5 pr-12 text-xs font-mono font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 dark:text-gray-500">DPI</span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Bearbeitungsanzahl *</label>
                    <div class="relative">
                        <input type="number"
                               min="1"
                               max="20"
                               step="1"
                               x-model.number="configSettings.xtool_passes"
                               placeholder="1"
                               :class="!(Number(configSettings.xtool_passes) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                               class="w-full bg-white dark:bg-gray-900 border rounded-xl px-3.5 py-2.5 pr-8 text-xs font-mono font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-gray-400 dark:text-gray-500">&times;</span>
                    </div>
                </div>

                <div class="space-y-1.5 col-span-2 sm:col-span-1">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Frequenz *</label>
                    <div class="relative">
                        <input type="number"
                               min="1"
                               step="1"
                               x-model.number="configSettings.xtool_frequency_khz"
                               placeholder="40"
                               :class="!(Number(configSettings.xtool_frequency_khz) > 0) ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                               class="w-full bg-white dark:bg-gray-900 border rounded-xl px-3.5 py-2.5 pr-12 text-xs font-mono font-black text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 dark:text-gray-500">kHz</span>
                    </div>
                </div>
            </div>

            {{-- Zeile 4: Bitmap-Modus | Gravurmodus | Vektor-Zusatz (Optional) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Bitmap-Modus *</label>
                    <select x-model="configSettings.xtool_bitmap_mode"
                            :class="!String(configSettings.xtool_bitmap_mode || '').trim() ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                            class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all cursor-pointer">
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

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 block">Gravurmodus *</label>
                    <select x-model="configSettings.xtool_engrave_mode"
                            :class="!String(configSettings.xtool_engrave_mode || '').trim() ? 'border-red-300 dark:border-red-500/40' : 'border-gray-300 dark:border-gray-700'"
                            class="w-full bg-white dark:bg-gray-900 border rounded-xl px-4 py-2.5 text-xs font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all cursor-pointer">
                        <option value="">Bitte wählen...</option>
                        <option value="Zweidirektional">Zweidirektional</option>
                        <option value="Eindirektional">Eindirektional</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Vektor-Text / Symbole (Opt.)</label>
                        @include('components.alerts.info-tooltip', ['text' => 'Optionaler Zusatzwert für separate Vektor-Textebenen in derselben SVG (z. B. 60 % · 80 mm/s · 300 L/cm · 40 kHz).'])
                    </div>
                    <input type="text"
                           x-model="configSettings.xtool_vector_note"
                           placeholder="z.B. Gravieren · 60 % · 80 mm/s · 300 L/cm · 40 kHz"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-2.5 text-xs font-medium text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none transition-all shadow-inner">
                </div>
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
